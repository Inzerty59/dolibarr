<?php

if (strpos($_SERVER['SCRIPT_NAME'] ?? '', 'ticket/card.php') !== false) {
	global $conf;
	
	if (!empty($conf->tickets->enabled)) {
		$action = $_GET['action'] ?? $_POST['action'] ?? '';
		$fk_project = (int)($_GET['fk_project'] ?? $_POST['fk_project'] ?? 0);
		
		if ($action === 'create' && $fk_project === 0) {
		if ($action === 'create' && $fk_project === 0) {
			header('Location: '.DOL_URL_ROOT.'/custom/tickets/select_project.php', true, 302);
			exit(0);
		}
	}
}
