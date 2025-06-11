<?php
/**
 * Add an records_management button to run PHP or get a result from a function
 * 
 * @package     Joomla.Plugin
 * @subpackage  Fabrik.list.php
 * @copyright   Copyright (C) 2005-2016  Media A-Team, Inc. - All rights reserved.
 * @license     GNU/GPL http://www.gnu.org/copyleft/gpl.html
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

// Require the abstract plugin class
require_once COM_FABRIK_FRONTEND . '/models/plugin-list.php';

/**
 *  Add an records_management button to run PHP
 *
 * @package     Joomla.Plugin
 * @subpackage  Fabrik.list.php
 */

class PlgFabrik_ListAction extends plgFabrik_List
{

	/*
	* Init function
	*/
	protected function init() 
	{
		// If can't use just stop
		if(!$canUse) {
			return;
		}
		$this->loadJS($opts);
	}

	/* 
	* run the php code
	* Ajax functions must to be public 
	*/
	public function onProcessPHPAction() 
	{
		$filter = JFilterInput::getInstance();
		$request = $filter->clean($_REQUEST, 'array');
		$phpCode = $request['phpString'];	
		$response = new StdClass;
		try {
			// Run the code
			$functionReturn = eval($phpCode);
			$response->return = true;
			echo json_encode($response);
		} catch (Exception $e) {
			// Report error
			$response->return = false;
			$response->errorMsg = 'Error on executing PHP: ' .  $e->getMessage() . "\n";
			echo json_encode($response);
		}		
	}

	/*
	* Function to load the javascript code for the plugin
	*/
	protected function loadJS($opts) 
	{
		$optsJson = json_encode($opts);
		$this->opts = $optsJson;
	}

	public function onloadJavascriptInstance($args)
    {
		parent::onLoadJavascriptInstance($args);
		$this->init();
		$opts = json_encode($this->opts);

        $this->jsInstance = "new FbListAction({$opts})";

        return true;
    }

    public function loadJavascriptClassName_result()
    {
        return 'FbListAction';
    }

	/*
	* Function run on when list is being loaded
	* Used to trigger the init function
	*/
	public function onLoadData(&$args) 
	{
		$this->init();
	}

}
