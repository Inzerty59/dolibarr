<?php
// filepath: /home/florent/dev/dolibarr/custom/kanban/save_kanban_status.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error) {
        echo "Fatal error: ";
        print_r($error);
    }
});

require_once __DIR__.'/../../main.inc.php';
require_once __DIR__.'/../../core/lib/security2.lib.php';

header('Content-Type: text/plain; charset=utf-8');

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$status = isset($_POST['status']) ? (int)$_POST['status'] : 0;
$token = isset($_POST['token']) ? $_POST['token'] : '';

if (!$id || !in_array($status, [0,1,2,3])) {
    http_response_code(400);
    echo "Paramètres invalides";
    exit;
}

file_put_contents('/tmp/kanban_debug.log', "Inclu security2.lib.php\n", FILE_APPEND);
if (function_exists('formtoken_check')) {
    file_put_contents('/tmp/kanban_debug.log', "formtoken_check OK\n", FILE_APPEND);
} else {
    file_put_contents('/tmp/kanban_debug.log', "formtoken_check ABSENT\n", FILE_APPEND);
}

// if (!formtoken_check($token)) {
//     http_response_code(403);
//     echo "Token CSRF invalide";
//     exit;
// }

$sql = "UPDATE ".MAIN_DB_PREFIX."projet_task SET fk_statut = ".$status." WHERE rowid = ".$id." AND entity = ".$conf->entity;
$res = $db->query($sql);

if ($res) {
    echo "OK";
} else {
    http_response_code(500);
    echo "Erreur SQL : ".$db->lasterror();
}
