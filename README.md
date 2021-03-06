Referencial Photos in Symfony2

Webservice REST which allow provide the ID photo for a person (from personal datas LDAP) 

Allow :
---
- provide the personal photo of one user to an authorized application (by ip address or dns host) ;
- from a security token generated on demand (usable only once, valid for 2 minutes) obtained from ID LDAP user (uid), or student card number (supannEtuId), or employee ID number (supannEmpId) ;
- according to the choice of the user stored in a field on LDAP (usePhoto : TRUE or FALSE). If TRUE, the photo of the user can be returned. If FALSE, the default photo with text "authorization refused" is returned ; 
The storage :
---
- one side in metadatas (database stored the ID LDAP user (uid) and the fingerprint SHA1 of the photo) ;
- other side in binaries (the path of the stored image is builted from the fingerprint SHA1 of the photo, example: if the fingerprint SHA1 is 8a7b908fdac1eedc8acc8f7758f19a33faf2eb72 then the photo will be stored in 8a/7b/908fdac1eedc8acc8f7758f19a33faf2eb72.jpg) ;

Client side uses :
---
- Photo in applications symfony 2 : https://github.com/l3-team/PhotoBundle
- Photo in esup-mon-dossier-web version2 : https://github.com/l3-team/Lille3PhotoEsupMonDossierWebV2
- Photo in esup-mon-dossier-web version3 : https://github.com/l3-team/Lille3PhotoEsupMonDossierWebV3
- Photo with routes /binary*

How it works (two steps use) :
---
- first the route /token/add/{uid} is accessed only for authorized application (example : http://server/refphoto/token/add/P7279), it prints the token valid for 2 minutes (example : c4ca4238a0b923820dcc509a6f75849b) ;
- then the route /image/{token} is accessed for public (example : http://server/refphoto/image/c4ca4238a0b923820dcc509a6f75849b), it shows the photo of the user of not (according the value TRUE ou FALSE of the LDAP field usePhoto on the LDAP record for the user) ;

Other routes :
---
- the route /tokenEtu/add/{supannEtuId} is accessed only for authorized application (example : http://server/refphoto/tokenEtu/add/8877665544), it prints the token valid for 2 minutes (example : c4ca4238a0b923820dcc509a6f75849b) ;
- the route /tokenPers/add/{supannEmpId} is accessed only for authorized application (example : http://server/refphoto/tokenPers/add/3007279), it prints the token valid for 2 minutes (example : c4ca4238a0b923820dcc509a6f75849b) ;
- the route /binary/{uid} is accessed only for authorized application (example : http://server/refphoto/binary/P7279), it shows the photo of the user (according his choice if the LDAP field usePhoto) ;
- the route /binaryEtu/{supannEtuId} is accessed only for authorized application (example : http://server/refphoto/binaryEtu/8877665544), it shows the photo of the user (according the LDAP field usePhoto) ;
- the route /binaryPers/{supannEmpId} is accessed only for authorized application (example : http://server/refphoto/binaryPers/3007279), it shows the photo of the user (according the LDAP field usePhoto) ;
- the route /download/{token} is accessed for public (example : http://server/refphoto/image/c4ca4238a0b923820dcc509a6f75849b), it show the photo without check the value of the LDAP field usePhoto;

Remarque : l'image est obtenue en 2 temps, d'abord on obtient un jeton, ensuite on obtient l'image à partir du jeton.
- the route /binaryDownload/{uid} is accessed only for authorized application (example : http://server/refphoto/binaryDownload/ST5063), it shows the photo without check the LDAP field usePhoto;

on obtient la photo quelque soit la valeur du temoin LDAP.

Remarque : ici pas de jeton token intermédiaire, la photo est obtenue directement en une seule url.

Pre-requisites :
---
* PHP webserver (which can run Symfony2 application) ;
* LDAP directory with schema SUPANN (with fields, uid, eduPersonAffiliation, eduPersonPrimaryAffiliation, supannEtuId and supannEmpId) ;
* LDAP field usePhoto (with possibles values TRUE or FALSE) ;
* MySQL database
* Memcached daemon
* Directory datas with JPEG Photos (to rename like : {supannEmpId}.jpg (for an employee person) or {supannEtuId}.jpg (for a student person) ;
* Directory binaries with write ACL unix for webserver (user www-data or apache) ;
* List for ip address of dns host for the authorized applications ;
* Optionnal : Ip address for the reverse proxy (for separated DMZ networks) ;

Installation
---
* the configuration is in file app/config/parameters.yml (just copy file app/config/parameters.yml.dist in app/config/parameters.yml and modify it) :
```
    ldap_hostname: ldap.univ.fr				# Server LDAP
    ldap_base_dn: 'dc=univ,dc=fr'			# basedn
    ldap_dn: 'cn=user,ou=ldapusers,dc=univ,dc=fr'	# user
    ldap_password: 'password'				# pass

    fieldldap_name: 'usePhoto'                          # name of the ldap field in your LDAP directory
    fieldldap_multivaluated: 'false'                    # 'true' if your field is multivaluated, 'false' otherwise...
    fieldldap_positivevalue: 'TRUE'                     # the value is your field is true
    fieldldap_negativevalue: 'FALSE'                    # the value is your field is false
    fieldldap_id: 'uid'                                 # the ldap field for the id of your persons in your ldap directory
    fieldldap_profil: 'edupersonprimaryaffiliation'     # the ldap field for the principal affiliation
    fieldldap_profils: 'edupersonaffiliation'           # the ldap field for the multiple affiliation of your persons
    fieldldap_idstudent: 'supannetuid'                  # the ldap field for student number
    fieldldap_idemployee: 'supannempid'                 # the ldap field for employee number
    fieldldap_studentvalues: ['student']                # the values for your students for affiliation
    fieldldap_employeevalues: ['faculty', 'employee', 'staff'] # the values for your employees for affiliation

    easyid_activated: 'false'				# if BLOB stored in DB in EasyID
    easyid_username: "easyid"				
    easyid_password: "password"
    easyid_connection: "localhost:1522/EASYID"

    files_activated: 'false'			# if files stored in /srv/photos/datas (filename {student_number}.jpg or {employee_number}.jpg)
    files_dirlocalread: '/srv/photos/datas'
    files_extfile: '.jpg'

    db_hostname: db.univ.fr
    db_username: dbuser
    db_password: dbpass
    db_database: "photo"

    photo_path: "/srv/photos/binaries"			# where the binaries photo store is stored
    photo_resize: "161x178"
    default_photo: /var/www/ws-refphotos/htdocs/default.jpg
    blocked_photo: /var/www/ws-refphotos/blocked.jpg
    forbidden_photo: /var/www/ws-refphotos/blocked.jpg



    memcached_host: localhost

    valid_server: ~                                     # address ip or dns server name for apps needs photos 

    xvalid_server: ~                                    # address ip or dns server name for apps needs photos without check the ldap field, can be empty with value "0" (works with codeapp)

    xvalid_codeapp: ~                                   # code application for route /token/{codeapp}/{uid} for without check the ldap field, can be empty with value "0"


    ip_reverseproxy: ~

    general_login: 'à renseigner'          # identifiants necessaires pour l'upload
    general_password: 'à renseigner'

```

example for values of authorized applications :
```
     valid_server:
        - 10.131.12.137
        - esup-mon-dossier-web.univ.fr
```

example for values of authorized applications without check the value of ldapfield (can be empty with "0" value);
```
     xvalid_server:
        - "0"
```

* apply ACL on app/cache and app/logs :
```
sudo setfacl -R -m u:www-data:rwx -m u:`whoami`:rwx app/cache app/logs binaries
sudo setfacl -dR -m u:www-data:rwx -m u:`whoami`:rwx app/cache app/logs binaries
```

* install the dependency :
```
composer install --optimize-autoloader
```

* create the schema of the MySQL database :
```
mysql -h dbserver.host.domain -u root -p < dump.sql
```

Availables commands :
---
- for import the photo of the user which UID is P7279 :
```
php app/console photo:user P7279
```
- for loops on all LDAP user :
```
php app/console photo:import
```
- for deletes photos of missings accounts LDAP ;
```
php app/console photo:delete
```

Notice : if memory problem for the command php app/console photo:import,
then modify the value in src/Lille3/PhotoBundle/Service/PhotoService.php at function importPhotoAll.
Increase the value of memory_limit : 
```
ini_set('memory_limit', '3000M');
```  


Usecase for upload
---
It is possible to upload a picture directly in the referencial photo/
For this it should just use a formulary HTML with method POST to the route /upload/{uid}.
And next modify just the field uid, login and password in the example bellow
Example for upload with uid "toto" :
```

<form method="post" action="http://serveur/refphoto/web/upload/toto" enctype="multipart/form-data">

	<label for="icone">Fichier :</label><br />

        <input type="file" name="file" id="file" /><br />

	<input type="hidden" name="login" value="identifiant à remplacer"/>

	<input type="hidden" name="password" value="mot de passe à remplacer"/>

        <input type="hidden" name="MAX_FILE_SIZE" value="1048576" />

        <input type="submit" name="submit" value="Envoyer" />

</form>
```