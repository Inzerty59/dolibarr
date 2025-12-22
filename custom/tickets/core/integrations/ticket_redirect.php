<?php

global $db, $user, $conf, $langs;

require_once DOL_DOCUMENT_ROOT.'/custom/tickets/lib/tickets.lib.php';

if (isset($action) && $action == 'create' && !isset($fk_project)) {
	if (!empty($conf->tickets->enabled)) {
		header('Location: '.DOL_URL_ROOT.'/custom/tickets/select_project.php');
		exit;
	}
}
