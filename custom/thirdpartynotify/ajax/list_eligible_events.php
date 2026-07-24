<?php

define('NOTOKENRENEWAL', 1);
require_once __DIR__.'/../class/bootstrap.inc.php';
require_once __DIR__.'/../class/thirdpartynotify.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var User $user
 */

header('Content-Type: application/json; charset=UTF-8');

function thirdpartynotify_events_json($payload, $status = 200)
{
	http_response_code($status);
	print json_encode($payload);
	exit;
}

$token = GETPOST('token', 'alpha');
if (empty($token) || !hash_equals((string) currentToken(), (string) $token)) {
	thirdpartynotify_events_json(array('success' => false, 'error' => 'token invalide'), 403);
}

$socid = GETPOSTINT('socid');
if ($socid <= 0) {
	thirdpartynotify_events_json(array('success' => false, 'error' => 'Paramètres invalides'), 400);
}

if (!$user->hasRight('societe', 'lire')) {
	thirdpartynotify_events_json(array('success' => false, 'error' => 'Accès interdit'), 403);
}
if (!$user->hasRight('agenda', 'myactions', 'read') && !$user->hasRight('agenda', 'allactions', 'read')) {
	thirdpartynotify_events_json(array('success' => false, 'error' => 'Accès interdit'), 403);
}
restrictedArea($user, 'societe', $socid, '&societe');

$sql = "SELECT a.id";
$sql .= " FROM ".MAIN_DB_PREFIX."actioncomm as a";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."c_actioncomm as c ON c.id = a.fk_action";
$sql .= " WHERE a.entity IN (".getEntity('agenda').")";
$sql .= " AND a.fk_soc = ".((int) $socid);
$sql .= ThirdpartyNotify::getManualActionSqlFilter('c');

if (!$user->hasRight('agenda', 'allactions', 'read')) {
	$sql .= " AND (a.fk_user_author = ".((int) $user->id)." OR a.fk_user_action = ".((int) $user->id);
	$sql .= " OR EXISTS (SELECT ar.rowid FROM ".MAIN_DB_PREFIX."actioncomm_resources as ar";
	$sql .= " WHERE ar.fk_actioncomm = a.id AND ar.element_type = 'user' AND ar.fk_element = ".((int) $user->id)."))";
}

$resql = $db->query($sql);
if (!$resql) {
	dol_syslog('ThirdpartyNotify eligible events error: '.$db->lasterror(), LOG_ERR);
	thirdpartynotify_events_json(array('success' => false, 'error' => 'Erreur technique'), 500);
}

$ids = array();
while ($obj = $db->fetch_object($resql)) {
	$ids[] = (int) $obj->id;
}

thirdpartynotify_events_json(array('success' => true, 'ids' => $ids, 'newToken' => currentToken()));
