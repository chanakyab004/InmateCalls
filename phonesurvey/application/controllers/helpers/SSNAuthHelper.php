<?php
class Application_Controller_Helper_SSNAuthHelper extends Zend_Controller_Action_Helper_Abstract
{
	const AUTH_RETRIES = 1;
	private $config;
	public function __construct()
	{
		$this->config = Zend_Controller_Front::getInstance()->getParam('bootstrap');
		$webservice = $this->config->getOption('webservice');
		$this->auth_service_url = $webservice['gatekeeper_auth'];
	}
	
	protected function _doQuery($method='getSession',$params = array(), $checkSession=true)
	{
		$auth_session_xml = false;
		$index = 0;
		while ($auth_session_xml === false && $index < count(self::AUTH_RETRIES))
		{
			$this->_xmlRpcClient = new Zend_XmlRpc_Client($this->auth_service_url);
			try
			{
				$auth_session_xml = $this->_xmlRpcClient->call($method, $params);
			}
			catch (Exception $e)
			{
				Zend_Registry::get('log')->log('Exception : '. $e->getMessage() . $e->getTraceAsString(),LOG_ERR);
			}
			$index++;
		}
		return $auth_session_xml;
	}
	
	public function checkCredentials($email, $password)
	{
		return $this->_doQuery('login',array($email, $password, $_SERVER['REMOTE_ADDR']),false);
	}
	
	public function getUserPermission($session_id,$ip_address)
	{
		return $this->_doQuery('getSession',array($session_id,$ip_address),false);
	}
	
	public function getUserLogout($session_id,$ip_address)
	{
		return $this->_doQuery('logout',array($session_id,$ip_address),false);
	}
	
	public function checkCode($session_id,$pin)
	{
		return $this->_doQuery('checkCode',array(isset($session_id) ? $session_id : '', $pin, $_SERVER['REMOTE_ADDR']));
	}
	
	public function resendCode($session_id)
	{
		return $this->_doQuery('resendCode',array(isset($session_id) ? $session_id : '', $_SERVER['REMOTE_ADDR']));
	}
	
	public function getAuthPermissions($auth_session_xml = false)
	{
		$baseUrl = Zend_Controller_Front::getInstance()->getBaseUrl();
		$auth_permissions = array();
		if ($auth_session_xml === false)
		{
			$auth_session_xml = $this->getUserPermission($SESSION['phonesurvey']['session_id'],$_SERVER['REMOTE_ADDR']);			
		}	
		if ($auth_session_xml !== false && empty($auth_session_xml) === false)
		{
			$auth_session_object = simplexml_load_string($auth_session_xml);
				
			$auth_session_data = get_object_vars($auth_session_object);
				
			$auth_status_data = get_object_vars($auth_session_data['status']);
			
			if ($auth_status_data['value'] == Application_Model_Constants::GATEKEEPER_STATUS_VALID)
			{
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
			}
			elseif (in_array($auth_status_data['value'], array(Application_Model_Constants::GATEKEEPER_STATUS_CODE_SENT, Application_Model_Constants::GATEKEEPER_STATUS_INVALID_CODE)) === true)
			{
				$this->redirect($baseUrl.'/index/validate');
			}
			elseif (in_array($auth_status_data['value'], array(Application_Model_Constants::GATEKEEPER_STATUS_INVALIDIP, Application_Model_Constants::GATEKEEPER_STATUS_INVALIDCREDS, Application_Model_Constants::GATEKEEPER_STATUS_INVALID_SESSION, Application_Model_Constants::GATEKEEPER_STATUS_LOCKED)) === true)
			{
				$this->redirect($baseUrl.'/index/logout');
			}
		}
		return $auth_permissions;
	}
	
	private function redirect($url)
	{
		header('Location: '.$url);
		exit();
	}
}