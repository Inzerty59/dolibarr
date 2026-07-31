<?php

define('NOTOKENRENEWAL', 1);
require_once __DIR__.'/../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once __DIR__.'/../class/mondaycandidateemail.class.php';

header('Content-Type: application/json; charset=UTF-8');

function monday_candidate_email_response($payload, $status = 200)
{
    http_response_code((int) $status);
    print json_encode($payload);
    exit;
}

if (empty($user->id)) {
    monday_candidate_email_response(array('success' => false, 'message' => 'Accès interdit'), 403);
}

$token = GETPOST('token', 'aZ09');
$sessionToken = isset($_SESSION['newtoken']) ? (string) $_SESSION['newtoken'] : '';
if (empty($token) || !hash_equals($sessionToken, (string) $token)) {
    monday_candidate_email_response(array('success' => false, 'message' => 'CSRF token invalide'), 403);
}

$taskId = GETPOSTINT('task_id');
if ($taskId <= 0) {
    monday_candidate_email_response(array('success' => false, 'message' => 'Identifiant candidat invalide'), 400);
}

$service = new MondayCandidateEmail($db);
$payload = $service->getTaskEmailInfo($taskId);

monday_candidate_email_response($payload);
