<?php

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modThirdpartynotify extends DolibarrModules
{
	public function __construct($db)
	{
		global $conf, $langs;

		$this->db = $db;
		$this->numero = 500002;
		$this->rights_class = 'thirdpartynotify';
		$this->family = 'crm';
		$this->module_position = '92';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'ThirdpartynotifyDescription';
		$this->descriptionlong = 'ThirdpartynotifyDescriptionLong';
		$this->editor_name = 'Inzerty';
		$this->editor_url = '';
		$this->version = '1.0.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'fa-bell';

		$this->module_parts = array(
			'triggers' => 0,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'models' => 0,
			'css' => array('/thirdpartynotify/css/thirdpartynotify.css.php'),
			'js' => array('/thirdpartynotify/js/thirdpartynotify.js.php'),
			'hooks' => array(),
			'moduleforexternal' => 0,
		);

		$this->dirs = array();
		$this->config_page_url = array('setup.php@thirdpartynotify');
		$this->hidden = 0;
		$this->depends = array('modSociete', 'modAgenda');
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array('thirdpartynotify@thirdpartynotify');
		$this->phpmin = array(7, 1);
		$this->need_dolibarr_version = array(19, 0);
		$this->need_javascript_ajax = 1;
		$this->warnings_activation = array();
		$this->warnings_activation_ext = array();
		$this->const = array();
		$this->tabs = array();
		$this->dictionaries = array();
		$this->boxes = array();
		$this->cronjobs = array();
		$this->rights = array();
		$this->menu = array();

		if (is_object($langs)) {
			$langs->load('thirdpartynotify@thirdpartynotify');
		}

		if (!isModEnabled('thirdpartynotify')) {
			$conf->thirdpartynotify = new stdClass();
			$conf->thirdpartynotify->enabled = 0;
		}
	}

	public function init($options = '')
	{
		$result = $this->_load_tables('/thirdpartynotify/sql/');
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
