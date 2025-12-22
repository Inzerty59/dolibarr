<?php

$res = 0;
if (!$res && file_exists(dirname(__FILE__) . '/../../main.inc.php')) {
	require_once dirname(__FILE__) . '/../../main.inc.php';
	$res = 1;
}

if (!$res) {
	die('Cannot find Dolibarr');
}

if (!$user->id) {
	accessforbidden();
}

if (!$user->rights->ticket->read) {
	accessforbidden();
}

$fk_project = GETPOST('fk_project', 'int');

if ($fk_project) {

	header('Location: '.DOL_URL_ROOT.'/ticket/card.php?action=create&fk_project='.$fk_project);
	exit;
}

header('Location: '.DOL_URL_ROOT.'/custom/tickets/select_project.php');
exit;
