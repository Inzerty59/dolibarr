<?php

require_once '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

/**
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array('admin', 'outlooksync@outlooksync'));

if (!$user->admin) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');

if ($action === 'set') {
	dolibarr_set_const($db, 'OUTLOOKSYNC_TENANT_ID', GETPOST('OUTLOOKSYNC_TENANT_ID', 'alphanohtml'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'OUTLOOKSYNC_CLIENT_ID', GETPOST('OUTLOOKSYNC_CLIENT_ID', 'alphanohtml'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'OUTLOOKSYNC_CLIENT_SECRET_EXPIRES_AT', GETPOST('OUTLOOKSYNC_CLIENT_SECRET_EXPIRES_AT', 'alphanohtml'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'OUTLOOKSYNC_ALLOWED_MAILBOXES', GETPOST('OUTLOOKSYNC_ALLOWED_MAILBOXES', 'restricthtml'), 'chaine', 0, '', $conf->entity);
	setEventMessages($langs->trans('OutlooksyncSettingsSaved'), null, 'mesgs');
}

llxHeader('', $langs->trans('OutlooksyncSetup'));

print load_fiche_titre($langs->trans('OutlooksyncSetup'), '', 'fa-calendar');
print '<div class="info">'.$langs->trans('OutlooksyncSetupHelp').'</div><br>';

print '<form method="post" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="set">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Parameter').'</td><td>'.$langs->trans('Value').'</td></tr>';
$fields = array(
	'OUTLOOKSYNC_TENANT_ID' => 'OutlooksyncTenantId',
	'OUTLOOKSYNC_CLIENT_ID' => 'OutlooksyncClientId',
	'OUTLOOKSYNC_CLIENT_SECRET_EXPIRES_AT' => 'OutlooksyncClientSecretExpiresAt',
	'OUTLOOKSYNC_ALLOWED_MAILBOXES' => 'OutlooksyncAllowedMailboxes',
);
foreach ($fields as $key => $label) {
	$value = getDolGlobalString($key);
	print '<tr><td>'.$langs->trans($label).'</td><td><input class="flat minwidth500" type="text" name="'.$key.'" value="'.dol_escape_htmltag($value).'"></td></tr>';
}
print '</table><br>';
print '<input type="submit" class="button button-save" value="'.$langs->trans('Save').'">';
print '</form>';

llxFooter();
$db->close();
