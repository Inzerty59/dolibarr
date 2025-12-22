<?php
/* Simple redirect if needed - test version */

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

// Get projects - Show all active projects for now
$sql = "SELECT p.rowid, p.ref, p.title, p.datec FROM " . MAIN_DB_PREFIX . "projet as p";
$sql .= " WHERE p.rowid > 0"; // All projects
$sql .= " ORDER BY p.datec DESC";

$resql = $db->query($sql);
$num = $db->num_rows($resql);

llxHeader("", $langs->trans("SelectProject"), "");
?>
<div class="fichecenter">
	<div class="fichehalfdis">
		<div class="boxol">
			<h2><?php echo $langs->trans("SelectProject"); ?></h2>
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
					<td><a href="<?php echo DOL_URL_ROOT; ?>/custom/tickets/create_with_project.php?fk_project=<?php echo (int)$obj->rowid; ?>" class="butAction"><?php echo $langs->trans("CreateTicket"); ?></a></td>
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
