<?php
/*
 * T6 - Triggers for the custom ticket-template feature.
 *
 * The trigger keeps persistence logic outside Dolibarr core. The project card
 * hook renders the "ticket template" selector, and this trigger saves the
 * selected template once Dolibarr has created or updated the project.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/interfaces.class.php';


class InterfaceTickets extends Interfaces
{

	public $db;

	/**
	 * Store the Dolibarr database handler used by trigger actions.
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Technical trigger name displayed by Dolibarr.
	 */
	public function getName()
	{
		return 'TicketsTriggers';
	}

	/**
	 * Human-readable trigger description displayed by Dolibarr.
	 */
	public function getDesc()
	{
		return "Triggers for Tickets module";
	}

	/**
	 * Dolibarr 23 calls runTrigger() on trigger classes.
	 *
	 * Keep the persistence logic in executeActions() so older code paths stay
	 * compatible, but expose the method expected by the current trigger manager.
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		return $this->executeActions($action, $object, $user, $langs, $conf);
	}

	/**
	 * React to Dolibarr business events required by T6.
	 *
	 * - TICKET_CREATE_REDIRECT: keeps the custom "select project first" flow.
	 * - PROJECT_CREATE/PROJECT_MODIFY: saves the template chosen on project card.
	 */
	public function executeActions($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if ($action == 'TICKET_CREATE_REDIRECT' && GETPOST('action') == 'create' && empty(GETPOST('fk_project'))) {
			if (!empty($conf->tickets->enabled)) {
				header('Location: '.DOL_URL_ROOT.'/custom/tickets/select_project.php');
				exit;
			}
		}

		if (($action === 'PROJECT_CREATE' || $action === 'PROJECT_MODIFY') && GETPOSTISSET('fk_ticket_template')) {
			return $this->saveProjectTicketTemplate($object, $user, $conf);
		}

		return 0;
	}

	/**
	 * Persist the selected ticket template after Dolibarr has created/updated the project.
	 *
	 * The project form is native, so the hook can only render the selector before
	 * submit. The trigger is the right minimal place to save it because the project
	 * id is available here and the core project card stays untouched.
	 */
	private function saveProjectTicketTemplate($object, User $user, Conf $conf)
	{
		if (empty($object->id)) {
			return 0;
		}

		$templateid = GETPOSTINT('fk_ticket_template');
		$projectid = (int) $object->id;

		$sql = "DELETE FROM ".MAIN_DB_PREFIX."tickets_project_template";
		$sql .= " WHERE fk_project = ".$projectid;
		$sql .= " AND entity = ".((int) $conf->entity);

		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		if ($templateid <= 0) {
			return 0;
		}

		$sql = "INSERT INTO ".MAIN_DB_PREFIX."tickets_project_template";
		$sql .= " (entity, fk_project, fk_template, datec, fk_user_create, fk_user_modif)";
		$sql .= " VALUES (".((int) $conf->entity).", ".$projectid.", ".$templateid.", '".$this->db->idate(dol_now())."', ".((int) $user->id).", ".((int) $user->id).")";

		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		return 0;
	}
}
