<?php
/* Créer un ticket avec projet pré-rempli */

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

// Load ticket form
if ($fk_project) {
	// Redirect to ticket creation with project parameter
	// The native ticket/card.php will handle it
	header('Location: '.DOL_URL_ROOT.'/ticket/card.php?action=create&fk_project='.$fk_project);
	exit;
}

// If no project, redirect back to selection
header('Location: '.DOL_URL_ROOT.'/custom/tickets/select_project.php');
exit;
