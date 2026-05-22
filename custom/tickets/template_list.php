<?php
/*
 * T6 - Ticket templates list.
 *
 * This page belongs to the custom tickets module, not to Dolibarr core.
 * It lets administrators see existing ticket templates and open the
 * template edition page. A template is only a set of extra ticket fields;
 * native ticket fields stay owned by Dolibarr ticket/card.php.
 */

$res = 0;
if (!$res && file_exists(dirname(__FILE__).'/../../main.inc.php')) {
	require_once dirname(__FILE__).'/../../main.inc.php';
	$res = 1;
}
if (!$res) die('Cannot find Dolibarr');

if (!$user->id || !$user->admin) accessforbidden();

$langs->load('tickets@tickets');

llxHeader('', $langs->trans('Liste des modèles'), '');

print load_fiche_titre($langs->trans('Liste des modèles'), '', 'ticket');

$sql = "SELECT t.rowid, t.label, t.active, t.tms, COUNT(f.rowid) as nbfields";
$sql .= " FROM ".MAIN_DB_PREFIX."tickets_template as t";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."tickets_template_field as f ON f.fk_template = t.rowid";
$sql .= " WHERE t.entity IN (".getEntity('ticket').")";
$sql .= " AND t.active = 1";
$sql .= " GROUP BY t.rowid, t.label, t.active, t.tms";
$sql .= " ORDER BY t.label ASC";

$resql = $db->query($sql);

print '<table class="liste centpercent">';
print '<tr class="liste_titre">';
print '<th>Nom du modèle</th>';
print '<th class="center">Champs</th>';
print '<th class="center">Actif</th>';
print '<th>Modification</th>';
print '</tr>';

if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		print '<tr class="oddeven">';
			print '<td><a href="'.DOL_URL_ROOT.'/custom/tickets/template_card.php?action=editmodel&id='.(int) $obj->rowid.'">'.dol_escape_htmltag($obj->label).'</a></td>';
		print '<td class="center">'.(int) $obj->nbfields.'</td>';
		print '<td class="center">'.yn($obj->active).'</td>';
		print '<td>'.dol_print_date($db->jdate($obj->tms), 'dayhour').'</td>';
		print '</tr>';
	}
}

print '</table>';

llxFooter(); 

?>
