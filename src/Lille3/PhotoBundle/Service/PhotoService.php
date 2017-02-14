<?php
namespace Lille3\PhotoBundle\Service;

use OpenLdapObject\Bundle\LdapObjectBundle\LdapWrapper;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Acl\Exception\Exception;

class PhotoService {
	private $setting;
	private $oracle;
	private $photoPath;
	/**
	 * @var LdapWrapper
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

	public function __construct(LdapWrapper $ldap, array $setting) {
		$this->setting = $setting;
		$this->photoPath = $setting['path'];
		$this->ldap = $ldap;
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
		$users = $this->ldap->getRepository('Lille3\PhotoBundle\Entity\People')->findBy(array());

		// Liste des UID dans un tableau (pour limiter la consommation RAM)
		$uidList = array();
		foreach($users as $user) {
			$uidList[] = $user->getUid();
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
		$people = $this->ldap->getRepository('Lille3\PhotoBundle\Entity\People')->find($uid);
		if(!$people) return;

		if(in_array('faculty', $people->getEduPersonAffiliation()->toArray()) || in_array('employee', $people->getEduPersonAffiliation()->toArray())) {
			// Si c'est un personnel
			//$externalReference = '0' . substr($uid, 1);
			if(is_null($people->getSupannEmpId())) {
				$output->writeln(date('d-m-Y H:i:s') . ':<error>Impossible de récupérer le numéro individu pour l\'uid '.$uid.'</error>');
				return;
			}
			$externalReference = '0' . $people->getSupannEmpId();
                        $id = $people->getSupannEmpId();
		} elseif(in_array('student', $people->getEduPersonAffiliation()->toArray())) {
			//if(is_null($people->getLille3CodeCarteEtu())) {
                        if(is_null($people->getSupannEtuId())) {
				$output->writeln(date('d-m-Y H:i:s') . ':<error>Impossible de récupérer le numéro étudiant pour l\'uid '.$uid.'</error>');
				return;
			}
			//$externalReference = $people->getLille3CodeCarteEtu();
                        $externalReference = $people->getSupannEtuId();
                        $id = $people->getSupannEtuId();
		} else {
			return;
		}

                if ($this->setting['easyid']['activated'] == 'true') {
                    $stid = oci_parse($this->oracle, 'SELECT IMAGE_BLOB as IMAGE FROM v_lil3_trombi WHERE external_reference=:ref');
                    oci_bind_by_name($stid, ':ref', $externalReference);
                    oci_execute($stid);

                    $result = oci_fetch_assoc($stid);
                    
                } else {
                    
                    $result = null;
                }

                if ($this->setting['easyidcomue']['activated'] == 'true') {
                    //$output->writeln('je passe ici');
                    $img = $this->getPhotoFromComue($uid, $id, $people->getEduPersonPrimaryAffiliation(), $output);
                    //echo $img;
                } else { 
                    $img = null;
                }
                
		// Si l'utilisateur existe dans EasyID et qu'il a une image ou une image dans le EasyID de la Comue
		if ( (is_array($result) && !is_null($result['IMAGE'])) || (!is_null($img)) ) {
                        
                        if (!is_null($result['IMAGE'])) {
                            $image = $result['IMAGE'];
                            //echo $image->load();
                        }

                        //echo $img;    
                        
                        if (!is_null($img)) {
                            //echo "je passe ici";
                            list($imageSha1, $originSha1) = $this->saveImage($img);
                        } else {
                            //echo "je passe là";
                            list($imageSha1, $originSha1) = $this->saveImage($image->load());
                        }
                        
                        
			// Récupération des données de la table MySQL faisant le lien uid => Sha1
			$oldOriginSha1 = $this->getOriginSha1ForUid($uid);

			// Si on avait des données
			if($oldOriginSha1 !== false) {
				// Si le Sha1 a changé
				if($oldOriginSha1 !== $originSha1) {
					// Suppression de l'ancienne image
					$this->deletePhoto($this->getSha1ForUid($uid));
				} else {				
					// Si il a pas changé, on s'arrête
					$output->writeln(date('d-m-Y H:i:s') . ':<error>Photo déjà existante pour l\'uid '.$uid.' (de type '.$people->getEduPersonPrimaryAffiliation().')</error>');
					return;
				}
				$query = 'UPDATE sha1 SET sha1=:sha1, originsha1=:originsha1 WHERE uid = :uid';
                                $output->writeln(date('d-m-Y H:i:s') . ':<error>Photo mise à jour pour l\'uid '.$uid.' (de type '.$people->getEduPersonPrimaryAffiliation().')</error>');
			} else {
				$query = 'INSERT INTO sha1(uid, sha1, originsha1) VALUE (:uid, :sha1, :originsha1)';
                                $output->writeln(date('d-m-Y H:i:s') . ':<error>Photo enregistrée pour l\'uid '.$uid.' (de type '.$people->getEduPersonPrimaryAffiliation().')</error>');
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

		$this->memcached->delete('token_' . $token);

		$sha1 = $this->getSha1ForUid($uid);

		if($sha1 != false) {
			$people = $this->ldap->getRepository('Lille3\PhotoBundle\Entity\People')->find($uid);
			if ($people->getUdlListeServices() == false) {				
                            return $this->default;
			}
			if (in_array('usePhotoFalse', $people->getUdlListeServices()->toArray())) {
			    return $this->blocked;
                        }
                        if (in_array('usePhotoTrue', $people->getUdlListeServices()->toArray())) {
                            return $this->photoPath . $this->buildPathWithSha1($sha1);
                        }
			return $this->blocked;
		}
		return $this->default;
	}

        public function getUidByCodEtu($codeetu) { 
            $uid = "";
            $requeteldap = Array();
            $requeteldap['supannEtuId'] = $codeetu;
            $people = $this->ldap->getRepository('Lille3\PhotoBundle\Entity\People')->findBy($requeteldap);
            if (!$people) return "E";
            $uid = $people[0]->getUid();
            return $uid;
        }

	public function getUidByCodPers($codepers) { 
            $uid = "";
            $requeteldap = Array();
            $requeteldap['supannEmpId'] = $codepers;
            $people = $this->ldap->getRepository('Lille3\PhotoBundle\Entity\People')->findBy($requeteldap);
            if (!$people) return "P";
            $uid = $people[0]->getUid();
            return $uid;
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
