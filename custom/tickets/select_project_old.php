<?php

$res = 0;
if (!$res && file_exists(dirname(__FILE__) . '/../../main.inc.php')) {
	require_once dirname(__FILE__) . '/../../main.inc.php';
	$res = 1;
}
if (!$res && file_exists(dirname(__FILE__) . '/../main.inc.php')) {
	require_once dirname(__FILE__) . '/../main.inc.php';
	$res = 1;
}
if (!$res && file_exists(dirname(__FILE__) . '/main.inc.php')) {
	require_once dirname(__FILE__) . '/main.inc.php';
	$res = 1;
}
if (!$res) {
	die('Error: Cannot find main.inc.php');
}

if (!$user->id) {
	accessforbidden();
}

if (!$user->rights->ticket->read) {
	accessforbidden();
}

$action = GETPOST('action', 'alpha');

$sql = "SELECT DISTINCT p.rowid, p.ref, p.title, p.status, p.datec, p.datee";
$sql .= " FROM " . MAIN_DB_PREFIX . "projet as p";
$sql .= " WHERE p.status = 1";
$sql .= " AND (";
$sql .= "   p.fk_user_creat = " . (int)$user->id;
$sql .= "   OR p.rowid IN (SELECT DISTINCT fk_project FROM " . MAIN_DB_PREFIX . "projet_task_time WHERE fk_user = " . (int)$user->id . ")"; // User has logged time
$sql .= " )";
$sql .= " ORDER BY p.datec DESC";

$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
	exit;
}

$num = $db->num_rows($resql);

llxHeader("", $langs->trans("SelectProject"), "");
?>
<div class="fichecenter">
	<div class="fichehalfdis">
		<div class="boxol" style="margin-top: 20px;">
			<h2 class="title"><?php echo $langs->trans("SelectProject"); ?></h2>
			<p class="subtitle"><?php echo $langs->trans("SelectProjectToCreateTicket"); ?></p>

			<?php if ($num > 0) { ?>
			<div class="div-table-responsive">
				<table class="liste">
					<thead>
						<tr class="liste_titre">
							<th class="left"><?php echo $langs->trans("Ref"); ?></th>
							<th class="left"><?php echo $langs->trans("Label"); ?></th>
							<th class="center"><?php echo $langs->trans("DateCreate"); ?></th>
							<th class="center" colspan="2"><?php echo $langs->trans("Action"); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						while ($obj = $db->fetch_object($resql)) {
							echo '<tr class="oddeven">';
							echo '<td class="left">' . $obj->ref . '</td>';
							echo '<td class="left">' . $obj->title . '</td>';
							echo '<td class="center">' . dol_print_date($obj->datec, 'day') . '</td>';
							echo '<td class="center"><a class="butAction" href="' . DOL_URL_ROOT . '/ticket/card.php?action=create&fk_project=' . (int)$obj->rowid . '">' . $langs->trans("CreateTicket") . '</a></td>';
							echo '</tr>';
						}
						?>
					</tbody>
				</table>
			</div>
			<?php } else { ?>
				<div class="opacitymedium" style="padding:20px; text-align:center;"><?php echo $langs->trans("NoActiveProjectAssignedToUser"); ?></div>
			<?php } ?>
		</div>
	</div>
</div>

<?php 
llxFooter();
