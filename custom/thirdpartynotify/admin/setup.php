<?php

require_once __DIR__.'/../class/bootstrap.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

/**
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array('admin', 'thirdpartynotify@thirdpartynotify'));

if (!$user->admin) {
	accessforbidden();
}

llxHeader('', $langs->trans('ThirdpartynotifySetup'));

print load_fiche_titre($langs->trans('ThirdpartynotifySetup'), '', 'fa-bell');
print '<div class="info">';
print $langs->trans('ThirdpartynotifySetupHelp');
print '</div>';
print '<br>';
print '<div id="thirdpartynotify-admin-panel-mount"></div>';

llxFooter();
$db->close();
