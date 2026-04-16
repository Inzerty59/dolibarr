<?php

$res = 0;
if (!$res && file_exists(dirname(__FILE__).'/../../main.inc.php')) {
	require_once dirname(__FILE__).'/../../main.inc.php';
	$res = 1;
}

if (!$res) {
	die('Cannot find Dolibarr');
}

if (!$user->id) {
	accessforbidden();
}
if (!$user->rights->monplugin->create) {
    accessforbidden();
}

$action = GETPOST('action', 'alpha');

if ($action == 'add') {
	$titre = GETPOST('titre', 'alphanohtml');
	$description = GETPOST('description', 'restricthtml');
	$status = GETPOSTINT('status'); 
	if (!empty($titre)) {
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."monplugin_todo";
		$sql .= " ( description,titre, status, date_creation, fk_user_creat)";
		$sql .= " VALUES (";
		$sql .= " '".$db->escape($description)."',";
		$sql .= " '".$db->escape($titre)."',";
		$sql .= "".((int) $status).",";
		$sql .= " '".$db->idate(dol_now())."',";
		$sql .= " ".((int) $user->id);
		$sql .= ")";

		$resql = $db->query($sql);

		if ($resql) {
			setEventMessages('Todo ajoutee', null, 'mesgs');
			header('Location: '.$_SERVER['PHP_SELF']);
			exit;
		} else {
			setEventMessages($db->lasterror(), null, 'errors');
		}
	} else {
		setEventMessages('Le titre est obligatoire', null, 'errors');
	}
}
if ($user->rights->monplugin->create) {
llxHeader('', 'Todos');
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="add">';

print '<table class="border centpercent">';
print '<tr>';
print '<td class="titlefield">Titre</td>';
print '<td><input type="text" name="titre" class="flat minwidth300" required></td>';
print '</tr>';

print '<tr>';
print '<td>Description</td>';
print '<td><textarea name="description" class="flat centpercent" rows="3"></textarea></td>';
print '</tr>';

print '<tr>';
print '<td style="padding: 15px">status</td>';
print '<td style = "padding: 10px">';
print '<select name="status" class="flat" style="padding: 3px">';
print '<option value="0">A faire</option>';
print '<option value="1">En cours</option>';
print '<option value="2">Terminé</option>';
print '<option value="3">Validé</option>';
print '</select>';
print '</td>';
print '</tr>';

print '</table>';


print '<div class="center">';
print '<input type="submit" class="button" value="Ajouter">';
print '</div>';

print '</form>';

print '<br>';

$sql = "SELECT rowid, titre, description, status, date_creation";
$sql .= " FROM ".MAIN_DB_PREFIX."monplugin_todo";
$sql .= " ORDER BY rowid DESC";


$resql = $db->query($sql);

print '<table class="liste centpercent">';
print '<tr class="liste_titre">';
print '<th>ID</th>';
print '<th>Titre</th>';
print '<th>Description</th>';
print '<th>Statut</th>';
print '<th>Date creation</th>';
print '</tr>';

if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		print '<tr class="oddeven">';
		print '<td>'.((int) $obj->rowid).'</td>';
		print '<td>'.dol_escape_htmltag($obj->titre).'</td>';
		print '<td>'.dol_escape_htmltag($obj->description).'</td>';
		$statusLabel = array(
			0 => 'a faire', 
			1 => 'en cours', 
			2=>  'terminé', 
			3 => 'validé'
		); 
		
		print '<td>'.dol_escape_htmltag($statusLabel[(int) $obj->status] ?? 'Inconnu').'</td>';
		print '<td>'.dol_print_date($db->jdate($obj->date_creation), 'dayhour').'</td>';
		print '</tr>';
	}
} else {
	print '<tr><td colspan="5" class="error">'.$db->lasterror().'</td></tr>';
}

print '</table>';

llxFooter();
}


