<?php
require '../../main.inc.php';
$wid = (int) $_GET['wid'];
$res = $db->query("SELECT rowid, label FROM llx_myworkspace_group WHERE fk_workspace = $wid ORDER BY position ASC");
$groups = [];
while ($obj = $db->fetch_object($res)) {
    $groups[] = ['id' => $obj->rowid, 'label' => $obj->label];
}
header('Content-Type: application/json');
echo json_encode($groups);
