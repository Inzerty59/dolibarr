<?php

$res = 0;
if (!$res) {
	@set_include_path(dirname(__FILE__) . '/../../');
}
if (!$res) {
	@set_include_path(dirname(__FILE__) . '/../../../');
}
require_once 'main.inc.php';

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/tickets/lib/tickets.lib.php';

if (!$user->admin) {
	accessforbidden();
}

$langs->load('admin');
$langs->load('tickets@tickets');

$page_name = "TicketsAbout";
llxHeader("", $langs->trans($page_name), "", "", 0, 0, null, array(), false);

$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1">' . $langs->trans("BackToModuleList") . '</a>';
print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

$head = ticketsAdminPrepareHead();
$titre = $langs->trans("About");
print dol_get_fiche_head($head, 'about', $titre, -1, 'gear');

dol_htmloutput_mesg($langs->trans('TicketsAboutText'), '', 'note');

print dol_get_fiche_end();

llxFooter();
