<?php

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modMonplugin extends DolibarrModules
{
	public function __construct($db)
	{
		$this->db = $db;
        global $conf;

		$this->numero = 500010;
		$this->rights_class = 'monplugin';
		$this->family = 'crm';
		$this->module_position = '93';

		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'MonpluginDescription : masha ';
		$this->version = '1.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'fa-puzzle-piece';

		$this->module_parts = array(
			'triggers' => 0,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'css' => array(),
			'js' => array(),
			'hooks' => array(),
		);

		$this->config_page_url = array('setup.php@monplugin');
		$this->langfiles = array();

		$this->depends = array();//le  module  ne  dependes  pas  dun autre
		$this->requiredby = array();// aucun autre  module  ne  depnds  de  mon module 
		$this->conflictwith = array(); // nest  pas  en conflit  avec un autre  mocule

		$this->rights = array();
		$r = 0;

		$this->rights[$r][0] = $this->numero.'01';
		$this->rights[$r][1] = 'Lire les elemenets Monplugin';
		$this->rights[$r][4] = 'read';
		$r++; 


		$this->rights[$r][0] = $this->numero.'02';
		$this->rights[$r][1] = 'créer les elemenets du module  Monplugin';
		$this->rights[$r][4] = 'create';
		$r++; 	
		
		$this->rights[$r][0] = $this->numero.'03';
		$this->rights[$r][1] = 'Modifier les elements du module Monplugin';
		$this->rights[$r][4] = 'write';
		$r++; 	

        
        $this->menu = array();
        $r = 0;

        $this->menu[$r++] = array(
            'fk_menu' => '',
            'type' => 'top',
            'titre' => 'Todo',
			'prefix' => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle"'),
            'mainmenu' => 'monplugin',
            'leftmenu' => '',
            'url' => '/custom/monplugin/monpluginindex.php',
            'langs' => '',
            'position' => 1000,
            'enabled' => 'isModEnabled("monplugin")',
            'perms' => '$user->rights->monplugin->read,$user->rights->monplugin->write,$user->rights->monplugin->create',   
            'target' => '',
            'user' => 2,
        );


	}

	public function init($langs = null)
	{	

		$result = $this -> _load_tables('/monplugin/sql/');
		if($result < 0 ){
			return -1; 
		}
		$sql = array();

		return $this->_init($sql, $langs);
	}

	public function remove($langs = null)
	{
		$sql = array();

		return $this->_remove($sql,$langs);
	}
}
