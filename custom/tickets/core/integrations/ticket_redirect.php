<?php
/* Copyright (C) 2025 Florent
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file        htdocs/custom/tickets/core/integrations/ticket_redirect.php
 * \ingroup     tickets
 * \brief       Redirection vers sélection de projet
 */

// Ce fichier sera inclus au début de ticket/card.php via un hook

global $db, $user, $conf, $langs;

// Charger les fichiers nécessaires
require_once DOL_DOCUMENT_ROOT.'/custom/tickets/lib/tickets.lib.php';

// Si on est sur la création d'un ticket
if (isset($action) && $action == 'create' && !isset($fk_project)) {
	// Vérifier que le module est actif
	if (!empty($conf->tickets->enabled)) {
		// Rediriger vers la sélection de projet
		header('Location: '.DOL_URL_ROOT.'/custom/tickets/select_project.php');
		exit;
	}
}
