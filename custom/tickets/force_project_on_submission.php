<?php

if (!empty($_SESSION['ticket_forced_project'])) {
	$forced_project_id = (int)$_SESSION['ticket_forced_project'];
	
	$action = GETPOST('action', 'alpha');
	
	if (in_array($action, ['add', 'create', 'save'])) {
		$_POST['projectid'] = $forced_project_id;
		$_GET['projectid'] = $forced_project_id;
		$_REQUEST['projectid'] = $forced_project_id;
		$GLOBALS['projectid'] = $forced_project_id;
	}
}
