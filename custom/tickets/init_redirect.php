<?php

global $conf, $action;

if (!empty($conf->tickets->enabled)) {
	$req_action = GETPOST('action', 'alpha');
	$fk_project = GETPOST('fk_project', 'int') ? GETPOST('fk_project', 'int') : GETPOST('project_id', 'int');
	
	if (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/ticket/card.php') !== false) {
		if ($req_action == 'create' && !$fk_project) {
			header('Location: '.DOL_URL_ROOT.'/custom/tickets/select_project.php');
			exit;
		}
	}
}
