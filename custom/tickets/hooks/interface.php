<?php
/* Copyright (C) 2025 Florent
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file        htdocs/custom/tickets/hooks/interface.php
 * \ingroup     tickets
 * \brief       Hooks du module Tickets
 */

/**
 * Hook to modify "New Ticket" button on projects
 *
 * @param array $parameters Parameters
 * @param object $object Current object
 * @param string $action Current action
 * @param HookManager $hookmanager HookManager
 * @return int
 */
function ticketsHookTicketNewButton($parameters, $object, $action, $hookmanager)
{
	global $conf, $user, $db, $langs;

	if (empty($conf->tickets->enabled)) {
		return 0;
	}

	// If we're on a project page and there's a "Create Ticket" button
	if ($object->element == 'project' && !empty($object->id)) {
		// Redirect ticket creation through our module
		// This will be handled by modifying the card.php button
		return 1;
	}

	return 0;
}
