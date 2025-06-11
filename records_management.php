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

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Date\Date;
use Fabrik\Helpers\Worker;

// Require the abstract plugin class
require_once COM_FABRIK_FRONTEND . '/models/plugin-list.php';

/**
 *  Add an records_management button to run PHP
 *
 * @package     Joomla.Plugin
 * @subpackage  Fabrik.list.php
 */

class PlgFabrik_ListRecords_management extends plgFabrik_List
{

	/*
	* Init function
	*/
	protected function init() 
	{
        $canUse = !in_array(false, FabrikWorker::getPluginManager()->runPlugins("canApproveRequests", $this->getModel()->getFormModel(), "form"));

		$opts = new StdClass;
		$opts->listId = $this->getModel()->getId();
		$opts->canUse = $canUse;

		$this->jsScriptTranslation();
		$this->loadJS($opts);
	}

	/**
	 * Function to get request to check deadlines
	 * 
	 * @return		Void
	 */
	public function onCheckDeadlines()
	{
		$app = Factory::getApplication();
		$filter = JFilterInput::getInstance();

		$request = $filter->clean($_REQUEST, 'array');
		$listId = $request['listid'];
		$response = new StdClass;

		try {
			$this->checkDeadlines($listId);
		} catch (Exception $e) {
			$response->error = true;
			$response->message = $e->getMessage();
		}

		echo json_encode($response);
	}

	/**
	 * Function to check deadlines
	 * 
	 * @param		int			$listId			The list ID to check deadlines for
	 * 
	 * @throws 		Exception
	 */
	protected function checkDeadlines($listId)
	{
		$listModel = Factory::getApplication()->bootComponent('com_fabrik')->getMVCFactory()->createModel('List', 'FabrikFEModel');

		if((int)$listId <= 0) {
			throw new Exception(Text::_('PLG_FABRIK_LIST_RECORDS_MANAGEMENT_ERROR_INVALID_LIST_ID'));
		}

		$auxListModel = $this->getAuxiliarListModel($listId);
		$listModel->setId($listId);

		$data = $listModel->getData()[0];
		$auxData = $auxListModel->getData()[0];

		if(empty($data) || empty($auxData)) {
			return;
		}

		foreach ($data as $row) {
			$row = $listModel->removeTableNameFromSaveData((array) $row);

			$documentCategory = $row['categoria_raw'] ?? '';
			$dataDocumentCategory = $this->getDataFromAuxiliaryList($auxListModel, $documentCategory);

			if(empty($dataDocumentCategory || empty($dataDocumentCategory) || $dataDocumentCategory['destinacao_final_2'] == '1') || in_array($row['status_raw'], [5, 7])) {
				continue;
			}

			switch ($row['status_raw']) {
				case '1':
					$this->checkDeadlinesForCurrentStatus($row, $dataDocumentCategory, $listModel);
					break;

				case '2':
					$this->checkDeadlinesForMiddleStatus($row, $dataDocumentCategory, $listModel);
					break;
			}
		}

		return true;
	}

	/**
	 * This method checks the deadlines for the current status
	 * 
	 * @param		Array		$row					The row data
	 * @param		Array		$dataDocumentCategory	The data from the auxiliary list
	 * @param		Object		$listModel				The list model
	 * 
	 */
	private function checkDeadlinesForCurrentStatus($row, $dataDocumentCategory, $listModel)
	{
		$db = Factory::getContainer()->get('DatabaseDriver');

		$timeZone = new \DateTimeZone($this->config->get('offset'));
		$dataEventCurrent = $row['evento_fase_corrente_raw'] ?? '';
		$yearToCheck = $dataDocumentCategory['fase_corrente_raw'];
		$tableName = $listModel->getFormModel()->getTableName();

		if(empty($dataEventCurrent) || (empty($yearToCheck) && $yearToCheck !== '0')) {
			return;
		}

		try {
			$date = Factory::getDate($dataEventCurrent, $timeZone);
			$date = new Date($date, $timeZone);

			$now = Factory::getDate('now', $timeZone);
			$now = new Date($now, $timeZone);
		} catch (\Throwable $th) {
			return;
		}

		$intervalToCheck = new DateInterval('P' . $yearToCheck . 'Y');
		$date = $date->add($intervalToCheck);

		// Update the status if the date is greater than now or if the year to check is 0 and the year is different from the current year
		if($now > $date) {
			$update = new stdClass();
			$update->id = $row['id_raw'];
			$update->status = 3;

			$db->updateObject($tableName, $update, 'id');
		}
	}

	/**
	 * This method checks the deadlines for the middle status
	 * 
	 * @param		Array		$row					The row data
	 * @param		Array		$dataDocumentCategory	The data from the auxiliary list
	 * @param		Object		$listModel				The list model
	 * 
	 * @throws 		Exception
	 */
	private function checkDeadlinesForMiddleStatus($row, $dataDocumentCategory, $listModel)
	{
		$db = Factory::getContainer()->get('DatabaseDriver');

		$timeZone = new \DateTimeZone($this->config->get('offset'));
		$dataEventMiddle = $row['evento_fase_intermediaria_raw'] ?? '';
		$yearToCheck = $dataDocumentCategory['fase_intermediaria_raw'] ?? '';
		$tableName = $listModel->getFormModel()->getTableName();

		if(empty($dataEventMiddle) || (empty($yearToCheck) && $yearToCheck !== '0')) {
			return;
		}

		try {
			$date = Factory::getDate($dataEventMiddle, $timeZone);
			$date = new Date($date, $timeZone);

			$now = Factory::getDate('now', $timeZone);
			$now = new Date($now, $timeZone);
		} catch (\Throwable $th) {
			return;
		}

		$intervalToCheck = new DateInterval('P' . $yearToCheck . 'Y');
		$date = $date->add($intervalToCheck);
		if($now > $date) {
			$update = new stdClass();
			$update->id = $row['id_raw'];
			$update->status = $dataDocumentCategory['destinacao_final_2_raw'];

			$db->updateObject($tableName, $update, 'id');
		}
	}
 
	/**
	 * This method gets the data from the auxiliary list based on the document category
	 * 
	 * @param		Object		$auxListModel			The auxiliary list model
	 * @param		String		$documentCategory		The document category to search for
	 * 
	 * @return 		Array
	 */
	private function getDataFromAuxiliaryList($auxListModel, $documentCategory)
	{
		if(!is_numeric($documentCategory)) {
			return;
		}

		$auxData = $auxListModel->getData()[0];
		foreach ($auxData as $auxRow) {
			$auxRow = $auxListModel->removeTableNameFromSaveData((array) $auxRow);

			if(isset($auxRow['id_raw']) && $auxRow['id_raw'] == $documentCategory) {
				return $auxRow;
			}
		}
	}

	/**
	 * This method gets the auxiliar list model
	 * 
	 * @param		int			$listId			The list ID to get the auxiliar list model for
	 */
	private function getAuxiliarListModel($listId)
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		$auxListModel = Factory::getApplication()->bootComponent('com_fabrik')->getMVCFactory()->createModel('List', 'FabrikFEModel');

		$query = $db->getQuery(true);
		$query->select($db->qn('id_lista'))
			->from($db->qn('adm_cloner_listas'))
			->where($db->qn('id_lista_principal') . ' = ' . $db->q($listId))
			->order($db->qn('id_lista') . ' DESC')
			->setLimit(1);
		$db->setQuery($query);
		$auxListId = $db->loadResult();

		if(!$auxListId) {
			throw new Exception(Text::_('PLG_FABRIK_LIST_RECORDS_MANAGEMENT_ERROR_NO_AUXILIARY_LIST'));
		}

		$auxListModel->setId($auxListId);
		return $auxListModel;
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

        $this->jsInstance = "new FbListRecords_management({$opts})";

        return true;
    }

    public function loadJavascriptClassName_result()
    {
        return 'FbListRecords_management';
    }

	/*
	* Function run on when list is being loaded
	* Used to trigger the init function
	*/
	public function onLoadData(&$args) 
	{
		$this->init();
	}

	/**
	 * This method in case of bad request will save the data in #__action_logs table
	 * 
	 * @return		Void
	 */
	public function onSaveLogs()
	{
        $db = Factory::getContainer()->get('DatabaseDriver');
		$app = Factory::getApplication();

		$input = $app->input;

		$query = $db->getQuery(true);
		$query->insert($db->qn("#__action_logs"))
			->columns(implode(",", $db->qn(["message_language_key", "message", "log_date", "extension", "user_id", "item_id"])))
			->values(implode(",", $db->q([
				Text::_("PLG_FABRIK_LIST_RECORDS_MANAGEMENT_ERROR"),
				$input->getString('message'),
				Date::getInstance()->toSql(),
				Text::_("PLG_FABRIK_LIST_RECORDS_MANAGEMENT"),
				$this->user->id,
				$input->getInt('Itemid')
			]))
		);
        $db->setQuery($query);
		$db->execute($query);
	}

	/**
     * Method sends message texts to javascript file
     *
	 * @return  	Void
     */
    function jsScriptTranslation()
    {
        Text::script('PLG_FABRIK_LIST_RECORDS_MANAGEMENT_ERROR');
		Text::script('PLG_FABRIK_LIST_RECORDS_MANAGEMENT_CHECK_DEADLINES');
		Text::script('PLG_FABRIK_LIST_RECORDS_MANAGEMENT_DEADLINES_CHECKED');
    }
}
