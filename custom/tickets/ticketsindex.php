<?php
/* Copyright (C) 2025 Florent
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file        htdocs/custom/tickets/ticketsindex.php
 * \ingroup     tickets
 * \brief       Accueil du module Tickets
 */

// Load Dolibarr environment
$res = 0;
if (!$res) {
	@set_include_path(dirname(__FILE__) . '/../../');
}
if (!$res) {
	@set_include_path(dirname(__FILE__) . '/../../../');
}
require_once 'main.inc.php';

// Access control
if (!$user->id) {
	accessforbidden();
}

// Redirect to select project page
header('Location: '.DOL_URL_ROOT.'/custom/tickets/select_project.php');
exit;
