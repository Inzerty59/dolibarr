<?php
/* Copyright (C) 2025 Florent
 *
 * Ce fichier est inclus avant ticket/card.php pour intercepter la création sans projet
 */

global $conf, $user, $db, $action, $massaction;

// Vérifier que le module tickets est activé
if (!empty($conf->tickets->enabled)) {
	// Si on essaie de créer un ticket sans sélectionner un projet
	if (isset($action) && $action == 'create' && !GETPOST('fk_project') && !GETPOST('project_id')) {
		// Rediriger vers la sélection de projet
		header('Location: '.DOL_URL_ROOT.'/custom/tickets/select_project.php');
		exit;
	}
}
