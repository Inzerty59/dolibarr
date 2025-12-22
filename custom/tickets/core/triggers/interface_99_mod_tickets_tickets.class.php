<?php
/* Copyright (C) 2025 Florent
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file        htdocs/custom/tickets/core/triggers/interface_99_mod_tickets_tickets.class.php
 * \ingroup     tickets
 * \brief       Fichier des triggers du module Tickets
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/interfaces.class.php';

/**
 * Class of triggers for Tickets module
 */
class InterfaceTicketsTriggers extends Interfaces
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Trigger name
	 *
	 * @return string Name of trigger file
	 */
	public function getName()
	{
		return 'TicketsTriggers';
	}

	/**
	 * Trigger description
	 *
	 * @return string Description of trigger file
	 */
	public function getDesc()
	{
		return "Triggers for Tickets module";
	}

	/**
	 * Function called when action 'create' is done on ticket
	 * Redirects to project selection if no project selected
	 *
	 * @param string $action Action type
	 * @param object $object Object that triggers action
	 * @param User $user User
	 * @param Translate $langs Langs
	 * @param Conf $conf Config
	 * @return int Result: <0=Error, 0=Nothing to do, >0=Success
	 */
	public function executeActions($action, $object, User $user, Translate $langs, Conf $conf)
	{
		// Redirect to project selection before creating ticket without project
		if ($action == 'TICKET_CREATE_REDIRECT' && GETPOST('action') == 'create' && empty(GETPOST('fk_project'))) {
			// Module must be enabled
			if (!empty($conf->tickets->enabled)) {
				header('Location: '.DOL_URL_ROOT.'/custom/tickets/select_project.php');
				exit;
			}
		}

		return 0;
	}
}
