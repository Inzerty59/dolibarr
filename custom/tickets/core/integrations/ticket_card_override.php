<?php

global $conf, $user, $db, $action, $massaction;

if (!empty($conf->tickets->enabled)) {
	if (isset($action) && $action == 'create' && !GETPOST('fk_project') && !GETPOST('project_id')) {
		header('Location: '.DOL_URL_ROOT.'/custom/tickets/select_project.php');
		exit;
	}
}
