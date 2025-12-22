<?php
/* Copyright (C) 2025 Florent
 *
 * Init file for Tickets module - Intercept ticket creation redirects
 */

global $conf, $action;

// Vérifier si on est dans le contexte de la création d'un ticket sans projet
if (!empty($conf->tickets->enabled)) {
	// Récupérer l'action depuis GET ou POST
	$req_action = GETPOST('action', 'alpha');
	$fk_project = GETPOST('fk_project', 'int') ? GETPOST('fk_project', 'int') : GETPOST('project_id', 'int');
	
	// Si on est sur ticket/card.php en création sans projet
	if (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/ticket/card.php') !== false) {
		if ($req_action == 'create' && !$fk_project) {
			// Rediriger vers la sélection de projet
			header('Location: '.DOL_URL_ROOT.'/custom/tickets/select_project.php');
			exit;
		}
	}
}
