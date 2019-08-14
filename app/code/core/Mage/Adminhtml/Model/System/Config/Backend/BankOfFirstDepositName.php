<?php
class Mage_Adminhtml_Model_System_Config_Backend_BankOfFirstDepositName extends Mage_Core_Model_Config_Data
{
    public function _beforeSave()
    {
		$bofdn = $this->getValue();
		if(empty($bofdn))
			Mage::throwException("Banck Of First Deposit Name can't be empty!");
		if(strlen($bofdn) <3)
			Mage::throwException("Banck Of First Deposit Name must have at least 3 characteres in lenght!");			
    }
}