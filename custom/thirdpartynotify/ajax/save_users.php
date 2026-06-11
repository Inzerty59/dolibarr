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

function thirdpartynotify_json($payload, $status = 200)
{
	http_response_code($status);
	print json_encode($payload);
	exit;
}

if (empty($user->admin)) {
	thirdpartynotify_json(array('success' => false, 'error' => 'Accès interdit'), 403);
}

$token = GETPOST('token', 'alpha');
if (empty($token) || !hash_equals((string) currentToken(), (string) $token)) {
	thirdpartynotify_json(array('success' => false, 'error' => 'token invalide'), 403);
}

$mode = GETPOST('mode', 'alpha');
$service = new ThirdpartyNotify($db);

if ($mode === 'list') {
	$users = $service->getSelectableUsers($conf->entity);
	$selected = $service->getSelectedUsers($conf->entity);
	if ($users === -1 || $selected === -1) {
		dol_syslog('ThirdpartyNotify save_users list error: '.$db->lasterror(), LOG_ERR);
		thirdpartynotify_json(array('success' => false, 'error' => 'Erreur technique'), 500);
	}
	thirdpartynotify_json(array(
		'success' => true,
		'users' => $users,
		'selected' => $selected,
		'newToken' => currentToken(),
	));
}

if ($mode === 'save') {
	$userIdsRaw = GETPOST('users', 'alphanohtml');
	$userIds = array();
	if ($userIdsRaw !== '') {
		foreach (explode(',', $userIdsRaw) as $userId) {
			$userIds[] = (int) $userId;
		}
	}

	$result = $service->replaceSelectedUsers($conf->entity, $userIds, $user->id);
	if ($result < 0) {
		dol_syslog('ThirdpartyNotify save_users save error: '.$db->lasterror(), LOG_ERR);
		thirdpartynotify_json(array('success' => false, 'error' => 'Erreur technique'), 500);
	}

	$selected = $service->getSelectedUsers($conf->entity);
	thirdpartynotify_json(array(
		'success' => true,
		'count' => $result,
		'selected' => $selected,
		'message' => 'Configuration enregistrée',
		'newToken' => currentToken(),
	));
}

thirdpartynotify_json(array('success' => false, 'error' => 'Bad mode'), 400);
