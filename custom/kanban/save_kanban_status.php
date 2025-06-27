<?php
// filepath: /home/florent/dev/dolibarr/custom/kanban/save_kanban_status.php
require '../../main.inc.php';

if (!formtoken_check($_POST['token'])) {
    http_response_code(403);
    echo 'Token CSRF invalide';
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$status = (int) ($_POST['status'] ?? 0);

if ($id > 0 && in_array($status, [0,1,2,3])) {
    $sql = "UPDATE ".MAIN_DB_PREFIX."projet_task SET fk_statut = ".$status." WHERE rowid = ".$id." AND entity = ".$conf->entity;
    $db->query($sql);
    echo 'OK';
} else {
    http_response_code(400);
    echo 'Erreur';
}