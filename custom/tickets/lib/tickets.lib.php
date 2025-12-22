<?php

function ticketsAdminPrepareHead($object = null)
{
	global $langs, $conf;

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath('/admin/setup.php?mainmenu=home', 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = dol_buildpath('/admin/about.php?mainmenu=home', 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	return $head;
}

function getProjectsAssignedToUser($db, $user)
{
	$projects = array();

	$sql = "SELECT DISTINCT p.rowid, p.ref, p.title, p.status, p.datec, p.datee";
	$sql .= " FROM " . MAIN_DB_PREFIX . "projet as p";
	$sql .= " WHERE p.status = 1";
	$sql .= " AND (";
	$sql .= "   p.fk_user_creat = " . $user->id;
	$sql .= "   OR EXISTS (SELECT 1 FROM " . MAIN_DB_PREFIX . "projet_task_time WHERE fk_user = " . $user->id . " AND fk_project = p.rowid)"; // Time logged
	$sql .= " )";
	$sql .= " ORDER BY p.datec DESC";

	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$projects[] = $obj;
		}
	}

	return $projects;
}
