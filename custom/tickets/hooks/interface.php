<?php

function ticketsHookTicketNewButton($parameters, $object, $action, $hookmanager)
{
	global $conf, $user, $db, $langs;

	if (empty($conf->tickets->enabled)) {
		return 0;
	}

	if ($object->element == 'project' && !empty($object->id)) {
		return 1;
	}

	return 0;
}
