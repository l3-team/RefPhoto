<?php
namespace Lille3\PhotoBundle\Service;

use OpenLdapObject\LdapClient\Client;
use OpenLdapObject\LdapClient\Connection;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Acl\Exception\Exception;

class PhotoService {
    const DEFAULT_PORT = 389;
    private $setting;
    private $oracle;
    private $photoPath;
    /**
     * @var Client
     */
    private $ldap;
    private $baseUrl;
    /**
     * @var \Memcached
     */
    private $memcached;
    private $db;
    private $resize;
    private $default;
    private $blocked;

    public function __construct($hostname = false, $port = false, $base_dn = false, $dn = false, $password = false, array $setting) {
        $this->setting = $setting;
        $this->photoPath = $setting['path'];
        $port = $port ? $port : self::DEFAULT_PORT;
        $connect = new Connection($hostname, $port);
        if ($dn && $password) {
            $connect->identify($dn, $password);
        }
        $this->ldap = $connect->connect();
        $this->ldap->setBaseDn($base_dn);
        $this->memcached = new \Memcached();
        $this->memcached->addServer($setting['memcached']['host'], $setting['memcached']['port']);
        $this->resize = $setting['resize'];
        $this->default = $setting['default'];
        $this->blocked = $setting['blocked'];
        try {
                $this->db = new \PDO('mysql:dbname=' . $setting['photo_db']['database'] . ';host=' . $setting['photo_db']['hostname'], $setting['photo_db']['username'], $setting['photo_db']['password'], array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION));
        } catch(\PDOException $e) {
                throw new Exception(date('d-m-Y H:i:s') . ':Impossible de se connecter à MySQL: ' . $e->getMessage());
        }

        if ($this->setting['easyid']['activated'] == 'true') {
            $this->oracle = oci_connect($setting['easyid']['username'], $setting['easyid']['password'], $setting['easyid']['connection']);
            if(!$this->oracle) {
                throw new Exception(date('d-m-Y H:i:s') . ':Erreur de connexion à Oracle: ' . oci_error()['message']);
            }
        }
    }

    public function importAllPhoto(OutputInterface $output) {
        // Augmentation de la mémoire totale
        ini_set('memory_limit', '600M');

        // Récupération du LDAP
        $query = "(" . $this->setting['fieldldap']['id'] . "=*)";
        $users = $this->ldap->search($query, array($this->setting['fieldldap']['id'], $this->setting['fieldldap']['name'], $this->setting['fieldldap']['profil'], $this->setting['fieldldap']['profils'], $this->setting['fieldldap']['idstudent'], $this->setting['fieldldap']['idemployee']));
        unset($users['count']);

        // Liste des UID dans un tableau (pour limiter la consommation RAM)
        $uidList = array();
        for($i=0; $i<count($users);$i++) {
                $uidList[] = $users[$i][$this->setting['fieldldap']['id']][0];
        }

        // Suppression du tableau LDAP
        unset($users);

        // Importation de chaque utilisateur
        $i = 0;
        foreach($uidList as $uid) {
                $this->importUserPhoto($output, $uid);
                $i++;
        }

        $output->writeln(date('d-m-Y H:i:s') . ':<info>Importation de ' . $i . ' utilisateurs terminées</info>');
    }

    public function importUserPhoto(OutputInterface $output, $uid, $force = false) {
        // Récupération des informations
        $query = "(" . $this->setting['fieldldap']['id'] . "=" . $uid . ")";
        $user = $this->ldap->search($query, array($this->setting['fieldldap']['id'], $this->setting['fieldldap']['name'], $this->setting['fieldldap']['profil'], $this->setting['fieldldap']['profils'], $this->setting['fieldldap']['idstudent'], $this->setting['fieldldap']['idemployee']), 1);
        unset($user['count']);
        $user = (array_key_exists(0, $user)) ? $user[0] : array();

        // Pas d'information, on quitte
        if (count($user) == 0) return;

        // Si c'est une personne de type personnel
         if (in_array($user[$this->setting['fieldldap']['profil']][0], $this->setting['fieldldap']['employeevalues']))  {
                // Si c'est un personnel
                if (is_null($user[$this->setting['fieldldap']['idemployee']][0])) {
                        $output->writeln(date('d-m-Y H:i:s') . ':<error>Impossible de récupérer le numéro individu pour l\'uid '.$uid.'</error>');
                        return;
                }
                // On construit l'identifiant
                $externalReference = '0' . $user[$this->setting['fieldldap']['idemployee']][0];
                $id = $user[$this->setting['fieldldap']['idemployee']][0];
        // Sinon si c'est une personne de type étudiant        
        } elseif (in_array($user[$this->setting['fieldldap']['profil']][0], $this->setting['fieldldap']['studentvalues'])) {
                if (is_null($user[$this->setting['fieldldap']['idstudent']][0])) {
                        $output->writeln(date('d-m-Y H:i:s') . ':<error>Impossible de récupérer le numéro étudiant pour l\'uid '.$uid.'</error>');
                        return;
                }
                // On construit l'identifiant
                $externalReference = $user[$this->setting['fieldldap']['idstudent']][0];
                $id = $user[$this->setting['fieldldap']['idstudent']][0];
        } else {
                return;
        }

        // si la photo est stockée en BLOB dans la BDD dans EasyID
        if ($this->setting['easyid']['activated'] == 'true') {
            $stid = oci_parse($this->oracle, 'SELECT p.FIRSTNAME, p.LASTNAME, p.USERNAME, p.EXTERNAL_REFERENCE, sib.IMAGE_BLOB as IMAGE FROM squirel_person p left join squirel_image si on p.PHOTO_CROPPED_ID = si.IMAGE_ID left join SQUIREL_IMAGE_BLOB sib on si.BLOB_ID = sib.IMAGE_BLOB_ID WHERE p.EXTERNAL_REFERENCE=:ref');
            oci_bind_by_name($stid, ':ref', $externalReference);
            oci_execute($stid);

            $result = oci_fetch_assoc($stid);

        } else {

            $result = null;
        }

        // si la photo est stockée sur le filesystem du serveur
        if ($this->setting['easyidcomue']['activated'] == 'true') {
            $img = $this->getPhotoFromComue($uid, $id, $user[$this->setting['fieldldap']['profil']][0], $output);
        } else { 
            $img = null;
        }

        // Si l'utilisateur existe dans EasyID et qu'il a une image en BLOB ou une image filesystem
        if ( (is_array($result) && !is_null($result['IMAGE'])) || (!is_null($img)) ) {

                if (!is_null($result['IMAGE'])) {
                    $image = $result['IMAGE'];
                }

                // On prend en priorité le fichier du filesystem
                if (!is_null($img)) {
                    list($imageSha1, $originSha1) = $this->saveImage($img);

                // Sinon on prend le BLOB    
                } else {
                    list($imageSha1, $originSha1) = $this->saveImage($image->load());
                }


                // On récupère la vieille empreinte
                $oldOriginSha1 = $this->getOriginSha1ForUid($uid);

                // Si il existait déjà une photo
                if($oldOriginSha1 !== false) {

                        // Si c'est une nouvelle photo
                        if($oldOriginSha1 !== $originSha1) {

                                // On supprime l'ancienne photo
                                $this->deletePhoto($this->getSha1ForUid($uid));
                        } else {				
                                // Si c'est la même photo, on affiche un message et on arrête...
                                $output->writeln(date('d-m-Y H:i:s') . ':<error>Photo déjà existante pour l\'uid '.$uid.' (de type '.$user[$this->setting['fieldldap']['profil']][0].')</error>');
                                return;
                        }

                        $query = 'UPDATE sha1 SET sha1=:sha1, originsha1=:originsha1 WHERE uid = :uid';
                        $output->writeln(date('d-m-Y H:i:s') . ':<error>Photo mise à jour pour l\'uid '.$uid.' (de type '.$user[$this->setting['fieldldap']['profil']][0].')</error>');
                } else {
                        $query = 'INSERT INTO sha1(uid, sha1, originsha1) VALUE (:uid, :sha1, :originsha1)';
                        $output->writeln(date('d-m-Y H:i:s') . ':<error>Photo enregistrée pour l\'uid '.$uid.' (de type '.$user[$this->setting['fieldldap']['profil']][0].')</error>');
                }

                // Mise à jour du sha1 dans la BDD
                $update = $this->db->prepare($query);
                $update->execute(array(
                        'uid' => $uid,
                        'sha1' => utf8_decode($imageSha1),
                        'originsha1' => utf8_decode($originSha1)
                ));

                // Suppression du cache éventuel
                $this->memcached->delete('sha1_' . $uid);
        } else {
            $output->writeln(date('d-m-Y H:i:s') . ':<error>Pas de photo pour l\'uid '.$uid.'</error>');                    
        }
    }

    public function getPath($token) {

        $uid = $this->memcached->get('token_' . $token);

        if($uid === false) throw new NotFoundHttpException();
        //if($uid === false) return $this->default;

        $this->memcached->delete('token_' . $token);

        $sha1 = $this->getSha1ForUid($uid);

        if($sha1 != false) {
            $query = "(" . $this->setting['fieldldap']['id'] . "=" . $uid . ")";
            $user = $this->ldap->search($query, array($this->setting['fieldldap']['id'], $this->setting['fieldldap']['name'], $this->setting['fieldldap']['profil'], $this->setting['fieldldap']['profils'], $this->setting['fieldldap']['idstudent'], $this->setting['fieldldap']['idemployee']), 1);
            unset($user['count']);
            $user = (array_key_exists(0, $user)) ? $user[0] : array();

            if ($this->setting['fieldldap']['multivaluated'] == 'true') {

                if (!isset($user[$this->setting['fieldldap']['name']][0])) {
                    return $this->default;
                }

                if (in_array($this->setting['fieldldap']['negativevalue'], $user[$this->setting['fieldldap']['name']][0])) {
                    return $this->blocked;
                }

                if (in_array($this->setting['fieldldap']['positivevalue'], $user[$this->setting['fieldldap']['name']][0])) {
                    return $this->blocked;
                }

            } else {

                if (!isset($user[$this->setting['fieldldap']['name']][0])) {
                    return $this->default;
                }

                if ($user[$this->setting['fieldldap']['name']][0] == $this->setting['fieldldap']['negativevalue']) {
                    return $this->blocked;
                }


                if ($user[$this->setting['fieldldap']['name']][0] == $this->setting['fieldldap']['positivevalue']) {
                    return $this->photoPath . $this->buildPathWithSha1($sha1);
                }


            }

            return $this->blocked;
        }
        return $this->default;
    }

    public function getUidByCodEtu($codeetu) {
        // Récupération des informations
        $query = "(" . $this->setting['fieldldap']['idstudent'] . "=" . $codeetu . ")";
        $user = $this->ldap->search($query, array($this->setting['fieldldap']['id']), 1);

        unset($user['count']);
        $user = (array_key_exists(0, $user)) ? $user[0] : array();
        //var_dump($user);

        if (count($user) == 0) {
            return "E";

        } else {
            return $user['uid'][0];
        }
        return "E";
    }

    public function getUidByCodPers($codepers) {
        // Récupération des informations
        $query = "(" . $this->setting['fieldldap']['idemployee'] . "=" . $codepers . ")";
        $user = $this->ldap->search($query, array($this->setting['fieldldap']['id']), 1);

        unset($user['count']);
        $user = (array_key_exists(0, $user)) ? $user[0] : array();
        //var_dump($user);

        if (count($user) == 0) {
            return "P";

        } else {
            return $user['uid'][0];
        }
        return "P";
    }

    public function createToken($uid) {
        do {
                $token = substr(sha1($uid . '_' . uniqid() . '_' . time()), 0, 15);
        } while($this->memcached->get('token_' . $token) !== false);


        $this->memcached->set('token_' . $token, $uid, 60*2);

        return $token;
    }


    private function saveImage($imageContent) {
        $imageOriginSha1 = sha1($imageContent);

        if(is_array($this->resize)) {
                $imageContent = $this->resizeImage($imageContent);
                $imageSha1 = sha1($imageContent);
        } else {
                $imageSha1 = $imageOriginSha1;
        }

        $path = $this->buildPathWithSha1($imageSha1);

        if(!is_dir(dirname($this->photoPath . $path))) {
                mkdir(dirname($this->photoPath . $path), 0777, true);
        }

        if(!file_exists($this->photoPath . $path)) {
                file_put_contents($this->photoPath . $path, $imageContent);
        }

        return array($imageSha1, $imageOriginSha1);
    }

    private function deletePhoto($imageSha1) {
        $path = $this->buildPathWithSha1($imageSha1);

        if(file_exists($this->photoPath . $path)) {
            unlink($this->photoPath . $path);
        }
    }

    private function buildPathWithSha1($sha1) {
        return substr($sha1, 0, 2) . '/' . substr($sha1, 2, 2) . '/' . substr($sha1, 4) . '.jpg';
    }

    private function getSha1ForUid($uid) {
        $sha1 = $this->memcached->get('sha1_' . $uid);

        if($sha1!== false) return $sha1;

        // Récupération des données de la table MySQL faisant le lien uid => Sha1
        $query = $this->db->prepare('SELECT sha1 AS sha1 FROM sha1 WHERE uid = ?');
        $query->execute(array($uid));
        $mysqlData = $query->fetch(\PDO::FETCH_OBJ);
        if(!$mysqlData) return false;

        $this->memcached->set('sha1_' . $uid, $mysqlData->sha1);

        return is_object($mysqlData) ? $mysqlData->sha1 : false;
    }

    private function getOriginSha1ForUid($uid) {
        // Récupération des données de la table MySQL faisant le lien uid => originSha1
        $query = $this->db->prepare('SELECT originsha1 FROM sha1 WHERE uid = ?');
        $query->execute(array($uid));
        $mysqlData = $query->fetch(\PDO::FETCH_OBJ);
        if(!$mysqlData) return false;

        return is_object($mysqlData) ? $mysqlData->originsha1 : false;
    }

    private function resizeImage($imageContent) {
        $originImage = imagecreatefromstring($imageContent);
        $resizeImage = imagecreatetruecolor($this->resize[0], $this->resize[1]);

        $width = imagesx($originImage);
        $height = imagesy($originImage);
        if($width === $this->resize[0] && $height === $this->resize[1])
            return $imageContent;

        imagecopyresampled($resizeImage, $originImage, 0, 0, 0, 0, $this->resize[0], $this->resize[1], $width, $height);
        ob_start();
        imagejpeg($resizeImage);
        return ob_get_clean();
    }

    private function getPhotoFromComue($uid, $id, $type, $output) {

        $population = "";

        // on récupère le masque et le chemin selon le type de la personne
        $path = "";

        // on construit l'identifiant comue
        $id_comue = $id;
        // on récupère l'extension de fichier image
        $extfile = $this->setting['easyidcomue']['extfile'];
        // on récupère le répertoire de lecture locale
        $dirlocalread = $this->setting['easyidcomue']['dirlocalread'];

        // variables système
        $output_exec="";
        $contents=null;
        $return_val="";                        

        // on construit le nom du fichier
        $filename_photo = $dirlocalread . '/' . $id_comue . $extfile;

        $filename = "";

        // on lit le fichier photo si il existe
        if (file_exists($filename_photo)) {
            $filename = $filename_photo;
            // on ouvre le fichier et on en lit son contenu                
            $handle = fopen($filename_photo, "rb");
            $contents = fread($handle, filesize($filename_photo));
            fclose($handle);
            $return_val = 0;                
        } else {// on lit le fichier image par défaut
            //$contents = null;
            $filename = $this->default;
            $handle = fopen($this->default, "rb");
            $contents = fread($handle, filesize($this->default));
            fclose($handle);
            $return_val = 1;
        }

        // si y'a pas eu d'erreur...
        if ($return_val == 0) {
            // on poursuit le traitement...
            unset($output_exec);
            // ...
            return $contents;
        } else {
            $output->writeln(date('d-m-Y H:i:s') . ':<error>Fichier ' . $filename_photo .  ' introuvable pour l\'uid ' . $uid . ' (de type ' . $type . '). Utilisation du fichier ' . $filename . '</error>');
        }

        return $contents;
    }
}
