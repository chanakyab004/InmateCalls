<?php
class Application_Model_DbTable_UserDetails extends Zend_Db_Table_Abstract
{
	protected $_name = 'user_details';
	public function insertUserDetails($fullname,$finalssn,$dob,$address1,$address2,$address3,$phonetype,$name,$phone,$reachable,$reason,$other_reason){
		$db = Zend_Db_Table::getDefaultAdapter ();
		if(!$reachable){
			$reachable='';
		}
		if(!$reason){
			$reason='';
		}
		try{
			$data = array (
					'name' => $fullname,
					'ssn' => $finalssn,
					'date_of_birth' => $dob,
					'address1' => $address1,
					'address2' => $address2,
					'address3' => $address3,
					'phone_relation' => $phonetype,
					'name_on_phone' => $name,
					'mobile_no' => $phone,
					'is_reachable' => $reachable,
					'reason' => $reason,
					'other_reason' => $other_reason
			);
			 
			$result = $db->insert ($this->_name, $data );
			return $result;
		}
		catch ( Exception $e ){
			error_log('Error : '. $e->getMessage() ,0);
			Zend_Registry::get('log')->log('Exception : '. $e->getMessage() . $e->getTraceAsString(),LOG_ERR);
			return false;
		}
	}
}