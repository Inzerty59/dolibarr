<?php

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modOutlooksync extends DolibarrModules
{
	public function __construct($db)
	{
		global $conf, $langs;

		$this->db = $db;
		$this->numero = 500004;
		$this->rights_class = 'outlooksync';
		$this->family = 'crm';
		$this->module_position = '94';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'OutlooksyncDescription';
		$this->descriptionlong = 'OutlooksyncDescriptionLong';
		$this->editor_name = 'Inzerty';
		$this->editor_url = '';
		$this->version = '1.0.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'fa-calendar';

		$this->module_parts = array(
			'triggers' => 1,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'models' => 0,
			'hooks' => array(),
			'moduleforexternal' => 0,
		);

		$this->dirs = array();
		$this->config_page_url = array('setup.php@outlooksync');
		$this->hidden = 0;
		$this->depends = array('modAgenda');
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array('outlooksync@outlooksync');
		$this->phpmin = array(7, 1);
		$this->need_dolibarr_version = array(19, 0);
		$this->const = array();
		$this->tabs = array();
		$this->dictionaries = array();
		$this->boxes = array();
		$this->cronjobs = array();
		$this->cronjobs[] = array(
			'label' => 'OutlooksyncImportOutlookEvents',
			'jobtype' => 'method',
			'class' => 'custom/outlooksync/class/outlooksyncimporter.class.php',
			'objectname' => 'OutlooksyncImporter',
			'method' => 'syncFromOutlook',
			'parameters' => '',
			'comment' => 'Synchronise Outlook calendar events to Dolibarr agenda',
			'frequency' => 5,
			'unitfrequency' => 60,
			'status' => 0,
			'test' => 'isModEnabled("outlooksync")',
		);
		$this->rights = array();
		$this->menu = array();

		if (is_object($langs)) {
			$langs->load('outlooksync@outlooksync');
		}

		if (!isModEnabled('outlooksync')) {
			$conf->outlooksync = new stdClass();
			$conf->outlooksync->enabled = 0;
		}
	}

	public function init($options = '')
	{
		$result = $this->_load_tables('/outlooksync/sql/');
		if ($result < 0) {
			return -1;
		}

		return $this->_init(array(), $options);
	}

	public function remove($options = '')
	{
		return $this->_remove(array(), $options);
	}
}
