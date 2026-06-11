<?php
/*
 * T6 - Custom tickets module descriptor.
 *
 * This file registers the custom ticket-template feature in Dolibarr:
 * menus, SQL tables, hooks and triggers. It does not render business UI by
 * itself; it only tells Dolibarr where the module entry points are.
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modTickets extends DolibarrModules
{

	/**
	 * Configure the custom Tickets module declaration.
	 *
	 * Dolibarr reads this when the module is enabled/refreshed. The important
	 * parts for T6 are:
	 * - hooks: ticketcard/projectcard so we can extend native pages safely.
	 * - triggers: project/template association after project save.
	 * - menus: template list and template creation under the native Ticket menu.
	 */
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
			'js' => 0,
			'hooks' => array(
				'data' => array(
					'ticketcard',
					'ticket',
					'projectcard',
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
	'titre' => 'TicketTemplates',
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
	'titre' => 'NewTicketTemplate',
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
	'titre' => 'ListTicketTemplates',
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

	/**
	 * Install/update custom SQL tables and register Dolibarr metadata.
	 */
	public function init($langs = null)
	{
	   $sql = array();
       $this->_load_tables('/tickets/sql/');
       return $this->_init($sql, $langs);
	}

	/**
	 * Disable module metadata using Dolibarr's standard removal mechanism.
	 *
	 * Tables are intentionally not dropped here, following Dolibarr's usual
	 * module behavior: disabling a module should not delete business data.
	 */
	public function remove($langs = null)
	{
		$sql = array();

		return $this->_remove($sql, $langs);
	}
}
