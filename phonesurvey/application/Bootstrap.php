<?php

class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
	protected function _initLoad() {
		Zend_Session::start ();
		Zend_Registry::set('options',$this->getOptions());
	}
	
	protected function _initAutoload()
	{
		$autoloader = new Zend_Application_Module_Autoloader(array(
				'namespace' => 'Application',
				'basePath' => APPLICATION_PATH,
		));
	
		Zend_Controller_Action_HelperBroker::addPath(
				APPLICATION_PATH . '/controllers/helpers',
				'Application_Controller_Helper_');
		return $autoloader;
	}
	
	protected function _initPlugin() 
	{
		$this->bootstrap('FrontController');
		$fc = $this->getResource('FrontController');
		$fc->registerPlugin(new Application_Plugin_SessionCheck());
	}
	
	protected function _initLogConfig()
	{
		$log = $this->bootstrap("log")->getResource("log");
		Zend_Registry::set('log',$log);
	}
}