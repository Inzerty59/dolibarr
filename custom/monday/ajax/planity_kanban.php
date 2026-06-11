<?php

define('NOTOKENRENEWAL', 1);
require_once __DIR__.'/../../../main.inc.php';
require_once __DIR__.'/../planity_kanban.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var User $user
 */

header('Content-Type: application/json; charset=UTF-8');

function planity_kanban_response($payload, $status = 200)
{
	http_response_code($status);
	print json_encode($payload);
	exit;
}

if (empty($user->id)) {
	planity_kanban_response(array('success' => false, 'error' => 'Accès interdit'), 403);
}

if (!planity_kanban_load_service()) {
	planity_kanban_response(array('success' => false, 'error' => 'Module Notifications tiers indisponible'), 500);
}

$service = new ThirdpartyNotify($db);
$isAdminView = planity_kanban_user_is_admin($user);
$action = GETPOST('action', 'alpha');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
	$cards = $service->fetchKanbanCardsForUser($conf->entity, $user->id, $isAdminView);
	if ($cards === -1) {
		dol_syslog('Planity Kanban list error: '.$db->lasterror(), LOG_ERR);
		planity_kanban_response(array('success' => false, 'error' => 'Erreur technique'), 500);
	}

	$cards['_meta'] = array('isAdminView' => $isAdminView);
	planity_kanban_response($cards);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_status') {
	$token = GETPOST('token', 'alpha');
	if (empty($token) || !hash_equals((string) currentToken(), (string) $token)) {
		planity_kanban_response(array('success' => false, 'error' => 'token invalide'), 403);
	}

	$cardId = GETPOSTINT('planity_kanban_card_id');
	$status = GETPOST('planity_kanban_status', 'alpha');
	if ($cardId <= 0) {
		planity_kanban_response(array('success' => false, 'error' => 'Paramètres invalides'), 400);
	}

	$result = $service->updateKanbanCardStatus($conf->entity, $user->id, $cardId, $status, $isAdminView);
	if ($result < 0) {
		dol_syslog('Planity Kanban update error: '.$db->lasterror(), LOG_ERR);
		planity_kanban_response(array('success' => false, 'error' => 'Erreur technique'), 500);
	}
	if ($result === 0) {
		planity_kanban_response(array('success' => false, 'error' => 'Carte introuvable'), 404);
	}

	planity_kanban_response(array('success' => true));
}

planity_kanban_response(array('success' => false, 'error' => 'Action invalide'), 400);
