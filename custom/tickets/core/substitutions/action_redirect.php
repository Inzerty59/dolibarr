<?php

global $conf, $_REQUEST, $hookmanager;

if (empty($conf->tickets->enabled)) {
	return;
}

if (strpos($_SERVER['SCRIPT_NAME'] ?? '', 'ticket/card.php') !== false) {
	$action = $_GET['action'] ?? $_POST['action'] ?? null;
	$fk_project = $_GET['fk_project'] ?? $_POST['fk_project'] ?? null;
	
	if ($action == 'create' && empty($fk_project)) {
		header('Location: '.DOL_URL_ROOT.'/custom/tickets/select_project.php');
		exit;
	}
}
