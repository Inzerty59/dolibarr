<?php
/* Copyright (C) 2025 Florent
 *
 * Fichier d'interception global pour le module Tickets
 * À ajouter au début de /custom/index.php ou via main.inc.php
 */

// Intercepter avant le chargement de ticket/card.php
if (strpos($_SERVER['SCRIPT_NAME'] ?? '', 'ticket/card.php') !== false) {
	// Récupérer le contexte global
	global $conf;
	
	// Vérifier que le module tickets est activé
	if (!empty($conf->tickets->enabled)) {
		// Récupérer les paramètres
		$action = $_GET['action'] ?? $_POST['action'] ?? '';
		$fk_project = (int)($_GET['fk_project'] ?? $_POST['fk_project'] ?? 0);
		
		// Si création d'un ticket sans projet
		if ($action === 'create' && $fk_project === 0) {
			// Rediriger vers la sélection de projet
			header('Location: '.DOL_URL_ROOT.'/custom/tickets/select_project.php', true, 302);
			exit(0);
		}
	}
}
