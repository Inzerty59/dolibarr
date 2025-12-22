<?php
/* Copyright (C) 2025 Florent
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file        htdocs/custom/tickets/core/substitutions/action_redirect.php
 * \ingroup     tickets
 * \brief       Substitution pour rediriger la création de tickets sans projet
 */

global $conf, $_REQUEST, $hookmanager;

// Ne charger que si le module est activé
if (empty($conf->tickets->enabled)) {
	return;
}

// Vérifier si on est sur la page de création de ticket
if (strpos($_SERVER['SCRIPT_NAME'] ?? '', 'ticket/card.php') !== false) {
	$action = $_GET['action'] ?? $_POST['action'] ?? null;
	$fk_project = $_GET['fk_project'] ?? $_POST['fk_project'] ?? null;
	
	// Si création de ticket sans projet, rediriger
	if ($action == 'create' && empty($fk_project)) {
		header('Location: '.DOL_URL_ROOT.'/custom/tickets/select_project.php');
		exit;
	}
}
