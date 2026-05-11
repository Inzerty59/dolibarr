<?php

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modTickets extends DolibarrModules
{

	public function __construct($db)
	{
		global $conf, $langs;

		$this->db = $db;

		$this->numero = 500001;

		$this->rights_class = 'tickets';

		$this->family = "other";

		$this->module_position = '91';

		$this->name = preg_replace('/^mod/i', '', get_class($this));

		$this->description = "TicketsDescription";

		$this->editor_name = 'Florent';
		$this->editor_url = '';

		$this->version = '1.0';

		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);

		$this->picto = 'fa-ticket';

		$this->module_parts = array(
			'triggers' => 1,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'css' => 0,
			'js' => 1,
			'hooks' => array(
				'data' => array(
					'ticketcard',
					'ticket',
					'all'
				),
				'entity' => '0',
			),
			'models' => 0,
		);

		$this->rights = array();
		$r = 0;

		$this->rights[$r][0] = $this->numero.$r;
		$this->rights[$r][1] = 'Accès au module Tickets personnalisé';
		$this->rights[$r][4] = 'tickets';
		$this->rights[$r][5] = 'read';

		$this->dictionaries = array();

		$this->depends = array('modTicket');
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array('tickets@tickets');
		$this->config_page_url = array('setup.php@tickets');
		
		$this->menu = array();
$r = 0;

$this->menu[$r++] = array(
	'fk_menu' => 'fk_mainmenu=ticket',
	'type' => 'left',
	'titre' => 'Modèles des tickets',
	'prefix' => '<span class="fas fa-briefcase paddingright pictofixedwidth em092" style="color:#18bc9c"></span>',
	'mainmenu' => 'ticket',
	'leftmenu' => 'tickets_templates',
	'url' => '/custom/tickets/template_list.php',
	'langs' => 'tickets@tickets',
	'position' => 113,
	'enabled' => 'isModEnabled("tickets")',
	'perms' => '$user->admin',
	'target' => '',
	'user' => 0
);

$this->menu[$r++] = array(
	'fk_menu' => 'fk_mainmenu=ticket,fk_leftmenu=tickets_templates',
	'type' => 'left',
	'titre' => 'Créer un modèle',
	'mainmenu' => 'ticket',
	'leftmenu' => 'tickets_template_new',
	'url' => '/custom/tickets/template_card.php?action=newmodel&reset=1',
	'langs' => 'tickets@tickets',
	'position' => 114,
	'enabled' => 'isModEnabled("tickets")',
	'perms' => '$user->admin',
	'target' => '',
	'user' => 0
);

$this->menu[$r++] = array(
	'fk_menu' => 'fk_mainmenu=ticket,fk_leftmenu=tickets_templates',
	'type' => 'left',
	'titre' => 'Liste des modèles',
	'mainmenu' => 'ticket',
	'leftmenu' => 'tickets_template_list',
	'url' => '/custom/tickets/template_list.php',
	'langs' => 'tickets@tickets',
	'position' => 115,
	'enabled' => 'isModEnabled("tickets")',
	'perms' => '$user->admin',
	'target' => '',
	'user' => 0
);


		$this->hidden = 0;
		$this->disabled = 0;
		$this->url_models_to_document = 'sample%;model_form.tpl';
		$this->url_models_to_document_list = 'sample%;list.tpl';

		if (is_object($langs)) {
			$langs->load('tickets@tickets');
		}

		if (file_exists(__DIR__.'/../../../custom/tickets/init_redirect.php')) {
			require_once __DIR__.'/../../../custom/tickets/init_redirect.php';
		}
	}

	public function init($langs = null)
	{
	   $sql = array();
       $this->_load_tables('/tickets/sql/');
       return $this->_init($sql, $langs);
	}

	public function remove($langs = null)
	{
		$sql = array();

		return $this->_remove($sql, $langs);
	}
}
