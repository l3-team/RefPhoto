<?php

namespace Lille3\PhotoBundle\Entity;

use OpenLdapObject\Entity;
use OpenLdapObject\Annotations as OLO;

/**
 * @OLO\Dn(value="ou=accounts")
 * @OLO\Entity({"inetOrgPerson"})
 */
class People extends Entity {
	/**
	 * @OLO\Column(type="string")
	 * @OLO\Index
	 */
	protected $uid;

        /**
	 * @OLO\Column(type="string", strict=false)
	 */
	protected $supannEtuId;
        
	/**
	 * @OLO\Column(type="array")
	 */
	protected $eduPersonAffiliation;
        
        /**
	 * @OLO\Column(type="string")
	 */
	protected $eduPersonPrimaryAffiliation;

	/**
         * @OLO\Column(type="array")
         */
        private $udlListeServices;

	/**
         * @OLO\Column(type="string")
         */
        private $supannEmpId;

    public function getUid() {
        return $this->uid;
    }

    public function setUid($value) {
        $this->uid = $value;
        return $this;
    }

    public function getSupannEtuId() {
        return $this->supannEtuId;
    }

    public function setSupannEtuId($value) {
        $this->supannEtuId = $value;
        return $this;
    }
    
    public function getEduPersonAffiliation() {
            return $this->eduPersonAffiliation;
    }

    public function setEduPersonAffiliation($value) {
            $this->eduPersonAffiliation = $value;
            return $this;
    }
        
    public function getEduPersonPrimaryAffiliation() {
            return $this->eduPersonPrimaryAffiliation;
    }

    public function setEduPersonPrimaryAffiliation($value) {
            $this->eduPersonPrimaryAffiliation = $value;
            return $this;
    }    

    public function addEduPersonAffiliation($value) {
        $this->eduPersonAffiliation->add($value);
        return $this;
    }

    public function removeEduPersonAffiliation($value) {
        $this->eduPersonAffiliation->removeElement($value);
        return $this;
    }

    public function getUdlListeServices() {
        return $this->udlListeServices;
    }

    public function setUdlListeServices($value) {
        $this->udlListeServices = $value;
        return $this;
    }

    public function addUdlListeServices($value) {
	$this->udlListeServices->add($value);
	return $this;
    }

    public function removeUdlListeServices($value) {
	$this->udlListeServices->removeElement($value);
	return $this;
    }

    public function getSupannEmpId() {
        return $this->supannEmpId;
    }

    public function setSupannEmpId($value) {
        $this->supannEmpId = $value;
        return $this;
    }

}
