<?php
/* Copyright (C) 2025 Florent
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file        htdocs/custom/tickets/admin/setup.php
 * \ingroup     tickets
 * \brief       Configuration du module Tickets
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

// Load Dolibarr libraries
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/tickets/lib/tickets.lib.php';

// Security check
if (!$user->admin) {
	accessforbidden();
}

// Access control
$langs->load('admin');
$langs->load('tickets@tickets');

$action = GETPOST('action', 'alpha');

// ===========================================================================
// Actions
// ===========================================================================

// ===========================================================================
// View
// ===========================================================================

$page_name = "TicketsSetup";
llxHeader("", $langs->trans($page_name), "", "", 0, 0, null, array(), false);

$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1">' . $langs->trans("BackToModuleList") . '</a>';
print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

$head = ticketsAdminPrepareHead();
$titre = $langs->trans("Settings");
print dol_get_fiche_head($head, 'settings', $titre, -1, 'gear');

// Put here all module configuration options
dol_htmloutput_mesg($langs->trans('TicketsModuleInfo'), '', 'note');

print dol_get_fiche_end();

llxFooter();
