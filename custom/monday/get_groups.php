<?php
require '../../main.inc.php';

$wid = isset($_GET['wid']) ? (int) $_GET['wid'] : 0;

$sql = "
    SELECT rowid
         , label
         , collapsed
         , task_column_label
      FROM llx_myworkspace_group
     WHERE fk_workspace = ".$wid."
  ORDER BY position ASC
";
$res = $db->query($sql);

$groups = [];
while ($obj = $db->fetch_object($res)) {
    $groups[] = [
        'id'        => (int)$obj->rowid,
        'label'     => $obj->label,
        'collapsed' => (int)$obj->collapsed,
        'task_column_label' => $obj->task_column_label ?: 'Tâche'
    ];
}

header('Content-Type: application/json');
echo json_encode($groups);
exit;
