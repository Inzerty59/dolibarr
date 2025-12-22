<?php

require_once DOL_DOCUMENT_ROOT.'/core/class/interfaces.class.php';


class InterfaceTicketsTriggers extends Interfaces
{

	public $db;

	public function __construct($db)
	{
		$this->db = $db;
	}

	public function getName()
	{
		return 'TicketsTriggers';
	}

	public function getDesc()
	{
		return "Triggers for Tickets module";
	}

	public function executeActions($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if ($action == 'TICKET_CREATE_REDIRECT' && GETPOST('action') == 'create' && empty(GETPOST('fk_project'))) {
			if (!empty($conf->tickets->enabled)) {
				header('Location: '.DOL_URL_ROOT.'/custom/tickets/select_project.php');
				exit;
			}
		}

		return 0;
	}
}
