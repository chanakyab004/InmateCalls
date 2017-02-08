<?php

class IndexController extends Zend_Controller_Action
{

	public function init()
	{
		/* Initialize action controller here */
		$this->_session = new Zend_Session_Namespace('phonesurvey');
		$this->_config = Zend_Controller_Front::getInstance()->getParam('bootstrap');
	}

	public function indexAction()
	{
		if(isset($this->_session->authenticated)){
			$this->_redirect('index/getssndetails');
		}
		
		if(isset($_POST['login'])){
			$email = $this->_getParam ( 'username' );
			$password = $this->_getParam ( 'password' );

			if ($email == '' || $password == '') {
				$this->view->error = 'Please provide username and password.';
			} else {
				$auth_session_xml = $this->_helper->sSNAuthHelper->checkCredentials($email, $password);
				if ($auth_session_xml !== false && is_null($auth_session_xml) === false) {
					$auth_session_object = simplexml_load_string($auth_session_xml);
					$auth_session_data = get_object_vars($auth_session_object);
					$auth_status_data = get_object_vars($auth_session_data['status']);
					$message = $auth_status_data['message'];
					
					if ($auth_status_data['value'] == Application_Model_Constants::GATEKEEPER_STATUS_VALID || $auth_status_data['value'] == Application_Model_Constants::GATEKEEPER_STATUS_CODE_SENT) {
						$auth_permissions_data = get_object_vars($auth_session_data['permissions']);
						
						if (is_array($auth_permissions_data) === true && array_key_exists('permission', $auth_permissions_data) === true)
						{
							if (is_array($auth_permissions_data['permission']) === true)
							{
								$auth_permissions = $auth_permissions_data['permission'];
							}
							else
							{
								$auth_permissions = array($auth_permissions_data['permission']);
							}
						}	
						$this->_session->email = $email;
						$this->_session->user_id = (string) $auth_session_object->user_id;
						$this->_session->user = $email;
						$this->_session->session_id = (string) $auth_session_object->session_id;
						
						if($auth_status_data['value'] == Application_Model_Constants::GATEKEEPER_STATUS_VALID) {
							if (in_array(Application_Model_Constants::PERM_SURVEY_USER, $auth_permissions) === true)
							{
								$this->_session->authenticated = true;
								$this->_session->role_name = $this->_config->getOption('survey_role');
								$this->_redirect('index/getssndetails');
							} else {
								$this->view->error = "Invalid permissions to access this application.";
							}
						}
						else if($auth_status_data['value'] == Application_Model_Constants::GATEKEEPER_STATUS_CODE_SENT){
							$this->_redirect('index/validate');
						}
					} else {
						$this->view->error = $message;
					}
				} else {
					$this->view->error = 'Invalid credentials, please check your entries.';
				}
			}
		}
		
		$redirect = $this->_getParam('redirect');
		if($redirect) {
			$this->view->error = "Session has expired. Please login again";
		}
	}

	public function validateAction(){
		if(isset($this->_session->authenticated)){
			$this->_redirect('index/getssndetails');
		}
		
		if(isset($_POST['verify_code'])){
			$code = $this->_getParam ( 'code' );
		
			if ($code == '') {
				$this->view->error = 'Please provide PIN CODE to verify.';
			} else {
				$auth_session_xml = $this->_helper->sSNAuthHelper->checkCode($this->_session->session_id,$code);
				if ($auth_session_xml !== false && is_null($auth_session_xml) === false)
				{
					$auth_session_object = simplexml_load_string($auth_session_xml);
				
					$auth_session_data = get_object_vars($auth_session_object);
					$auth_status_data = get_object_vars($auth_session_data['status']);
				
					if ($auth_status_data['value'] == Application_Model_Constants::GATEKEEPER_STATUS_INVALID_CODE)
					{
						$this->view->error = 'Invalid PIN.';
					}
					else
					{
						$auth_permissions = $this->_helper->sSNAuthHelper->getAuthPermissions($auth_session_xml);
						if (in_array(Application_Model_Constants::PERM_SURVEY_USER, $auth_permissions) === true)
						{
							$this->_session->authenticated = true;
							$this->_session->role_name = $this->_config->getOption('survey_role');
							$this->_redirect('index/getssndetails');
						} else {
							$this->view->error = "Invalid permissions to access this application.";
						}
					}
				}
				else
				{
					$this->view->error= 'Invalid PIN';
				}
			}
		}
		
		if(isset($_POST['resend_code'])){
			$auth_session_xml = $this->_helper->sSNAuthHelper->resendCode($this->_session->session_id);
			$this->view->error = 'PIN Code Resent';
		}
	}
	
	public function getssndetailsAction()
	{
			if($this->_request->isPost())
			{
				if($_POST['get_details'])
				{
					$ssn = $this->getRequest()->getParam('ssn', '');
					$fn = $this->getRequest()->getParam('firstname', '');
					$ln = $this->getRequest()->getParam('lastname', '');
					$fullname = $fn." ".$ln;
					$tloxpapiwebservice = $this->_config->getOption('webservice');
					$wsdl = $tloxpapiwebservice['tloxpapi'];
					$client = new SoapClient($wsdl, array(
							'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
							'trace' => true
					));
					
					$tloxpapi = $this->_config->getOption('tloxpapi');
					
					$comprehensivePersonSearchInput = array('searchInput' => array(
							'Username' => $tloxpapi['username'],
							'Password' => $tloxpapi['password'],
							'DPPAPurpose' => '6',
							'GLBPurpose' => '8',
							'Version' => '27',
							'ShowActivePhones' => 'yes',
							'ShowOtherPhones' => 'yes',
							'ShowAddressDetails' => 'yes',
							//'NumberOfRelativeLevels' => 1,
							//'ShowAssociates' => 'yes',
							//'ShowPossibleAssociates' => 'yes',
							//'ShowTLOBusinessAssociations' => 'yes',
							'SSN' => $ssn,
							'FullName' => $fullname,
							'NumberOfRecords' => 10,
							'StartingRecord' => 1,
							//'OnBehalfOfUserid' => $this->_session->email
					));
					$response = '';
					try {
						$response = $client->ComprehensivePersonSearch($comprehensivePersonSearchInput);
					} catch (Exception $e) {
						Zend_Registry::get('log')->log('Exception '.$e->getMessage(),LOG_ERR);
					}
					$res = $response->ComprehensivePersonSearchResult;
					if(!isset($res->ErrorMessage)) {
						/* Results associated with relatives,associates,possible associates and business */
						/*$totalResult = $response->ComprehensivePersonSearchResult->Relatives->TLOPersonSummary;
						$associateResult = $response->ComprehensivePersonSearchResult->Associates->TLOPersonSummary;
						$possibleAssociateResult = $response->ComprehensivePersonSearchResult->PossibleAssociates->TLOPersonSummary;
						$businessResult = $response->ComprehensivePersonSearchResult->TLOBusinessAssociations->TLOBusinessRecord;*/
						$name = $response->ComprehensivePersonSearchResult->SearchSynopsis;
						$splitResult = explode(",", $name);
						$finalName = explode("=", $splitResult[0]);
						$data = $response->ComprehensivePersonSearchResult->OtherPhones->PhoneListing;
						$otherPhoneResult = Array();
						$otherNameResult = Array();
						$totalAddress = Array();
						$dob = $response->ComprehensivePersonSearchResult->DatesOfBirth->DateOfBirthRecord;
						$dobArray = $this->objectToArray($dob);
						$day = $dobArray[0]->DateOfBirth->Day;
						$month = $dobArray[0]->DateOfBirth->Month;
						$year = $dobArray[0]->DateOfBirth->Year;
						$finaldob = $year."-".$month."-".$day;
						$ssndata = $response->ComprehensivePersonSearchResult->SSNRecords->SSNRecord;
						$ssnArray = $this->objectToArray($ssndata);
						$finalssn = $ssnArray[0]->SSN;
						$address = $response->ComprehensivePersonSearchResult->Addresses->AddressRecord;
						$addressArray = $this->objectToArray($address);
						foreach($addressArray as $key){
							$line = $key->Address->Line1;
							$city = $key->Address->City;
							$state = $key->Address->State;
							$zip = $key->Address->Zip;
							$totalAddress[] = $line.",".$city.",".$state."-".$zip;
						}
						/*array declaration for different results */
						/*$relativePhoneResult = Array();
						$relativeNameResult = Array();
						$associatePhoneResult = Array();
						$associateNameResult = Array();
						$businessPhoneResult = Array();
						$businessNameResult = Array();*/
						$allPhoneResults = Array();
						foreach($data as $key){
							$resArray = $this->objectToArray($key);
							$otherNameResult[] = $resArray['ListingName'];
							$otherPhoneResult[] = $resArray['Phone'];
							$allPhoneResults[] = $resArray['Phone'];
						}
						/*Loops associated with relatives,associates,possible associates and business phone number results*/
						/*foreach($totalResult as $key){
							$res =$key->OtherPhones->PhoneListing;
							$res1 =$key->Names->Name;
							foreach($res as $key1){
								$finalres = $this->objectToArray($key1);
								$isExists = 0;
								for($i=0;$i<count($allPhoneResults);$i++){
									if($finalres['Phone']==$allPhoneResults[$i]){
										$isExists = 1;
									}
								}
								if($isExists == 0){
									$relativePhoneResult[] = $finalres['Phone'];
									$allPhoneResults[] = $finalres['Phone'];
									foreach($res1 as $name){
										$finalres1 = $this->objectToArray($name);
									}
									$relativeNameResult[] = $finalres1['FirstName']." ".$finalres1['MiddleName']." ".$finalres1['LastName'];
								}
							}
						}
						foreach($associateResult as $key){
							$res =$key->OtherPhones->PhoneListing;
							$res1 =$key->Names->Name;
							foreach($res as $key1){
								$finalres = $this->objectToArray($key1);
								$isExists = 0;
								for($i=0;$i<count($allPhoneResults);$i++){
									if($finalres['Phone']==$allPhoneResults[$i]){
										$isExists = 1;
									}
								}
								if($isExists == 0){
									$associatePhoneResult[] = $finalres['Phone'];
									$allPhoneResults[] = $finalres['Phone'];
									foreach($res1 as $name){
										$finalres1 = $this->objectToArray($name);
									}
									$associateNameResult[] = $finalres1['FirstName']." ".$finalres1['MiddleName']." ".$finalres1['LastName'];
								}
							}
						}
						foreach($possibleAssociateResult as $key){
							$res =$key->OtherPhones->PhoneListing;
							foreach($res as $key1){
								$finalres = $this->objectToArray($key1);
								$phoneResult[] = $finalres['Phone'];
							}
						}
						foreach($businessResult as $key){
							$res =$key->Addresses->TLOBusinessAddressRecord;//->Phones;//->PhoneListing;
							$res1 =$key->BusinessNames->BusinessName[0]->Name;
							foreach($res as $key1){
								$finalres = $this->objectToArray($key1);
								$finalres1 = $finalres['Phones']->PhoneListing;
								foreach($finalres1 as $key2){
									$finalres2 = $this->objectToArray($key2);
									$isExists = 0;
									for($i=0;$i<count($allPhoneResults);$i++){
										if($finalres2['Phone']==$allPhoneResults[$i]){
											$isExists = 1;
										}
									}
									if($isExists == 0){
										$businessPhoneResult[] = $finalres2['Phone'];
										$allPhoneResults[] = $finalres2['Phone'];
										$businessNameResult[] = $res1;
									}
								}
							}
						}*/
						$this->view->otherPhoneresult = $otherPhoneResult;
						$this->view->otherNameresult = $otherNameResult;
						/*passing respective results to the view */
						/*$this->view->relativePhoneresult = $relativePhoneResult;
						$this->view->relativeNameresult = $relativeNameResult;
						$this->view->associatePhoneresult = $associatePhoneResult;
						$this->view->associateNameresult = $associateNameResult;
						$this->view->businessPhoneresult = $businessPhoneResult;
						$this->view->businessNameresult = $businessNameResult;*/
						$this->view->fullname = $finalName[1];
						$this->view->dob = $finaldob;
						$this->view->address = $totalAddress;
						$this->view->finalssn = $finalssn;
					} else {
						$this->view->error = $res->ErrorMessage;
					}
				}
				if(isset($_POST['done']))
				{
					$fullname = $_POST['fullname'];
					$finalssn = $_POST['finalssn'];
					$dob = $_POST['dob'];
					$address = $_POST['address'];
					$phonetype = $_POST['phonetype'];
					$phone = $_POST['phone'];
					$name = $_POST['name'];
					$reachable = $_POST['reachable'];
					$reason = $_POST['reason'];
					$otherreason = $_POST['otherreason'];
					$k = 0;
					$result = 0;
					for($i=0;$i<count($phone);$i++)
					{
						if(!empty($reason[$i])){
							if($reason[$i]=='other')
							{
								$reason[$i]='Other';
								$other_reason=$otherreason[$k++];
							}
							else{
								$other_reason=NULL;
							}
							$userDetail = new Application_Model_DbTable_UserDetails();
							$result = $userDetail->insertUserDetails($fullname,$finalssn,$dob,$address[0],$address[1],$address[2],$phonetype[$i],$name[$i],$phone[$i],$reachable[$i],$reason[$i],$other_reason);
						}
					}
					if($result==1){
						$this->view->successresult = "Details saved successfully";
					}
				}
			}
	}
	
	public function thankyouAction()
	{
		
	}
		
	public function logoutAction()
	{
		Zend_Controller_Action_HelperBroker::getStaticHelper('SSNAuthHelper')->getUserLogout($this->_session->session_id,$_SERVER['REMOTE_ADDR']);
		Zend_Session::namespaceUnset('phonesurvey');
		Zend_Session::destroy( true );
		
		$redirect = $this->_getParam('redirect');
		
		if($redirect) {
			$this->_redirect('index/index/redirect/1');
		} else {
			$this->_redirect('index/index');
		}
	}

	function objectToArray($object)
	{
		$array=array();
		foreach($object as $member=>$data)
		{
			$array[$member]=$data;
		}
		return $array;
	}
}