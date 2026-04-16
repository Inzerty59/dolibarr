<?php

$res = 0;
if (!$res && file_exists(dirname(__FILE__).'/../../../main.inc.php')) {
	require_once dirname(__FILE__).'/../../../main.inc.php';
	$res = 1;
}

if (!$res) {
	die('Cannot find Dolibarr');
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

if (!$user->admin) {
	accessforbidden();
}

llxHeader('', 'Configuration Monplugin');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">Retour a la liste des modules</a>';
print load_fiche_titre('Configuration Monplugin', $linkback, 'title_setup');

print '<p>Pas de configuration pour le moment.</p>';

llxFooter();
