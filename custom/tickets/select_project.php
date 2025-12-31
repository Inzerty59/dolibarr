<?php

$res = 0;
if (!$res && file_exists(dirname(__FILE__) . '/../../main.inc.php')) {
	require_once dirname(__FILE__) . '/../../main.inc.php';
	$res = 1;
}

if (!$res) {
	die('Cannot find Dolibarr');
}

if (!$user->id) {
	accessforbidden();
}

if (!$user->rights->ticket->read) {
	accessforbidden();
}

$langs->load('projects');
$langs->load('tickets');

// Afficher uniquement les projets auxquels l'utilisateur est rattaché
// La table llx_element_contact avec fk_c_type_contact 44,45,46,47 = contacts projet
$sql = "SELECT DISTINCT p.rowid, p.ref, p.title, p.datec FROM " . MAIN_DB_PREFIX . "projet as p";
$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "element_contact as ec ON ec.element_id = p.rowid";
$sql .= " WHERE ec.fk_socpeople = " . (int)$user->id;
$sql .= " AND ec.fk_c_type_contact IN (44, 45, 46, 47)"; // Types contacts projet (internal/external leader/contributor)
$sql .= " AND ec.statut = 4"; // Statut actif
$sql .= " ORDER BY p.datec DESC";

$resql = $db->query($sql);
$num = $db->num_rows($resql);

llxHeader("", "Sélectionner un Projet", "");
?>
<div class="fichecenter">
	<div class="fichehalfdis">
		<div class="boxol">
			<h2>Sélectionner un Projet</h2>
			<?php if ($num > 0) { ?>
			<table class="liste">
				<tr class="liste_titre">
					<th><?php echo $langs->trans("Ref"); ?></th>
					<th><?php echo $langs->trans("Label"); ?></th>
					<th>&nbsp;</th>
				</tr>
				<?php while ($obj = $db->fetch_object($resql)) { ?>
				<tr class="oddeven">
					<td><?php echo $obj->ref; ?></td>
					<td><?php echo $obj->title; ?></td>
					<td><a href="<?php echo DOL_URL_ROOT; ?>/custom/ticket/card.php?action=create&projectid=<?php echo (int)$obj->rowid; ?>" class="butAction"><?php echo $langs->trans("CreateTicket"); ?></a></td>
				</tr>
				<?php } ?>
			</table>
			<?php } else { ?>
			<p><?php echo $langs->trans("NoActiveProjectAssignedToUser"); ?></p>
			<?php } ?>
		</div>
	</div>
</div>
<?php llxFooter(); ?>
