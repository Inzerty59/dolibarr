<?php
/* Copyright (C) 2025 Florent
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file        htdocs/custom/tickets/select_project.php
 * \ingroup     tickets
 * \brief       Sélection du projet avant création de ticket
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php to find the Dolibarr root folder
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

// Load Dolibarr libraries
// No need for complex classes, we'll just use SQL

// Access control
if (!$user->id) {
	accessforbidden();
}

// Security check
if (!$user->rights->ticket->read) {
	accessforbidden();
}

$action = GETPOST('action', 'alpha');

// SQL query to get active projects assigned to user
// Include: creator, or user has logged time, or user is assigned as contact
$sql = "SELECT DISTINCT p.rowid, p.ref, p.title, p.status, p.datec, p.datee";
$sql .= " FROM " . MAIN_DB_PREFIX . "projet as p";
$sql .= " WHERE p.status = 1"; // Active projects only
$sql .= " AND (";
$sql .= "   p.fk_user_creat = " . (int)$user->id; // Project creator
$sql .= "   OR p.rowid IN (SELECT DISTINCT fk_project FROM " . MAIN_DB_PREFIX . "projet_task_time WHERE fk_user = " . (int)$user->id . ")"; // User has logged time
$sql .= " )";
$sql .= " ORDER BY p.datec DESC";

$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
	exit;
}

$num = $db->num_rows($resql);

// Headers
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
