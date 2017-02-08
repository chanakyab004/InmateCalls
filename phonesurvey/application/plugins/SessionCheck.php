<?php
class Application_Plugin_SessionCheck extends Zend_Controller_Plugin_Abstract {
	public function dispatchLoopStartup(Zend_Controller_Request_Abstract $request) {
		
		$controller = $request->getControllerName();
		$action = $request->getActionName();
		
		if (isset ( $_SESSION ['phonesurvey'] ['authenticated'] )) {
			$auth_session_xml = Zend_Controller_Action_HelperBroker::getStaticHelper ( 'sSNAuthHelper' )->getUserPermission ( $_SESSION ['phonesurvey'] ['session_id'], $_SERVER ['REMOTE_ADDR'] );
			
			if ($auth_session_xml !== false && empty ( $auth_session_xml ) === false) {
				$auth_session_object = simplexml_load_string ( $auth_session_xml );
				
				$auth_session_data = get_object_vars ( $auth_session_object );
				
				$auth_status_data = get_object_vars ( $auth_session_data ['status'] );
				
				if ($auth_status_data ['value'] == Application_Model_Constants::GATEKEEPER_STATUS_INVALID_SESSION) {
					$request->setModuleName ( 'default' )->setControllerName ( 'index' )->setActionName ( 'logout' )->setParam ( 'redirect', 1 );
				}
			}
		} else if (!($action == "validate" && isset($_SESSION ['phonesurvey'] ['session_id']))) {
			$request->setModuleName ( 'default' )->setControllerName ( 'index' )->setActionName ( 'index' );
		}
	}
}
?>