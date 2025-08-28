<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$langs->load("mymodule@mymodule");

if ($_SERVER['REQUEST_METHOD']==='POST'
    && isset($_POST['toggle_group_id'], $_POST['collapsed'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $gid       = (int)$_POST['toggle_group_id'];
    $collapsed = (int)$_POST['collapsed'];
    $db->query("
        UPDATE llx_myworkspace_group
           SET collapsed = $collapsed
         WHERE rowid = $gid
    ");
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['tasks_group_id'])) {
    $gid = (int)$_GET['tasks_group_id'];
    $res = $db->query("SELECT rowid, label FROM llx_myworkspace_task WHERE fk_group = $gid ORDER BY position ASC");
    $out = [];
    while ($o = $db->fetch_object($res)) {
        $out[] = ['id'=>$o->rowid,'label'=>$o->label];
    }
    header('Content-Type: application/json');
    echo json_encode($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_task_group_id'], $_POST['task_label'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $gid   = (int)$_POST['add_task_group_id'];
    $label = $db->escape($_POST['task_label']);
    $datec = date('Y-m-d H:i:s'); // NOUVEAU : Date de création
    $r     = $db->query("SELECT MAX(position) as m FROM llx_myworkspace_task WHERE fk_group=$gid");
    $p     = ($r && $o=$db->fetch_object($r)) ? $o->m+1 : 0;
    
    // MODIFIER cette ligne pour inclure datec :
    $db->query("INSERT INTO llx_myworkspace_task (fk_group,label,position,datec) VALUES ($gid,'$label',$p,'$datec')");
    exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['rename_task_id'], $_POST['rename_task_label'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $tid   = (int)$_POST['rename_task_id'];
    $label = $db->escape($_POST['rename_task_label']);
    $db->query("UPDATE llx_myworkspace_task SET label='$label' WHERE rowid=$tid");
    exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_task_id'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $tid = (int)$_POST['delete_task_id'];
    $db->query("DELETE FROM llx_myworkspace_task WHERE rowid=$tid");
    exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reorder_tasks'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $order = json_decode($_POST['reorder_tasks'], true);
    foreach ($order as $i=>$tid) {
        $db->query("UPDATE llx_myworkspace_task SET position=$i WHERE rowid=".(int)$tid);
    }
    exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reorder_workspaces'])) {
    if ($_POST['token']!==$_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $order = json_decode($_POST['reorder_workspaces'], true);
    foreach ($order as $i=>$id) {
        $db->query("UPDATE llx_myworkspace SET position=$i WHERE rowid=".(int)$id);
    }
    exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reorder_groups'])) {
    if ($_POST['token']!==$_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $order = json_decode($_POST['reorder_groups'], true);
    foreach ($order as $i=>$id) {
        $db->query("UPDATE llx_myworkspace_group SET position=$i WHERE rowid=".(int)$id);
    }
    exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && !empty($_POST['new_workspace'])) {
    if ($_POST['token']!==$_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $nw = dol_htmlentities($_POST['new_workspace'],ENT_QUOTES,'UTF-8');
    $r  = $db->query("SELECT MAX(position) as m FROM llx_myworkspace");
    $p  = ($r && $o=$db->fetch_object($r))?$o->m+1:0;
    $db->query("INSERT INTO llx_myworkspace(label,position) VALUES('".$db->escape($nw)."',$p)");
    
    if (isset($_POST['ajax']) || 
        (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') ||
        (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false)) {
        $newId = $db->last_insert_id();
        header('Content-Type: application/json');
        echo json_encode(['id' => $newId, 'label' => $nw]);
        exit;
    }
    
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['rename_workspace_id'],$_POST['rename_workspace_label'])) {
    if ($_POST['token']!==$_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $id=(int)$_POST['rename_workspace_id'];
    $lab=$db->escape($_POST['rename_workspace_label']);
    $db->query("UPDATE llx_myworkspace SET label='$lab' WHERE rowid=$id");
    exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_workspace_id'])) {
    if ($_POST['token']!==$_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $db->query("DELETE FROM llx_myworkspace WHERE rowid=".(int)$_POST['delete_workspace_id']);
    exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_group_workspace_id'],$_POST['group_label'])) {
    if ($_POST['token']!==$_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $fw=(int)$_POST['add_group_workspace_id'];
    $lb=$db->escape($_POST['group_label']);
    $r=$db->query("SELECT MAX(position) as m FROM llx_myworkspace_group WHERE fk_workspace=$fw");
    $p=($r&&$o=$db->fetch_object($r))?$o->m+1:0;
    $db->query("INSERT INTO llx_myworkspace_group(fk_workspace,label,position) VALUES($fw,'$lb',$p)");
    exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['rename_group_id'],$_POST['group_label'])) {
    if ($_POST['token']!==$_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $id=(int)$_POST['rename_group_id'];
    $lb=$db->escape($_POST['group_label']);
    $db->query("UPDATE llx_myworkspace_group SET label='$lb' WHERE rowid=$id");
    exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_group_id'])) {
    if ($_POST['token']!==$_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $db->query("DELETE FROM llx_myworkspace_group WHERE rowid=".(int)$_POST['delete_group_id']);
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['columns_group_id'])) {
    $gid = (int)$_GET['columns_group_id'];
    $res = $db->query("SELECT rowid, label, type FROM llx_myworkspace_column WHERE fk_group = $gid ORDER BY position ASC");
    $out = [];
    while ($o = $db->fetch_object($res)) {
        $out[] = ['id'=>$o->rowid,'label'=>$o->label,'type'=>$o->type];
    }
    header('Content-Type: application/json');
    echo json_encode($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_column_group_id'], $_POST['column_label'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $gid   = (int)$_POST['add_column_group_id'];
    $label = $db->escape($_POST['column_label']);
    $type  = isset($_POST['column_type']) ? $db->escape($_POST['column_type']) : 'text';
    $res = $db->query("SELECT fk_workspace FROM llx_myworkspace_group WHERE rowid = $gid");
    $ws = $db->fetch_object($res);
    $fk_workspace = $ws ? (int)$ws->fk_workspace : 0;
    $r     = $db->query("SELECT MAX(position) as m FROM llx_myworkspace_column WHERE fk_group=$gid");
    $p     = ($r && $o=$db->fetch_object($r)) ? $o->m+1 : 0;
    $db->query("INSERT INTO llx_myworkspace_column (fk_workspace, fk_group, label, position, type) VALUES ($fk_workspace, $gid, '$label', $p, '$type')");
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['column_options'])) {
    $cid = (int)$_GET['column_options'];
    $res = $db->query("SELECT rowid, label, color FROM llx_myworkspace_column_option WHERE fk_column = $cid ORDER BY position ASC");
    $out = [];
    while ($o = $db->fetch_object($res)) {
        $out[] = ['id'=>$o->rowid,'label'=>$o->label,'color'=>$o->color];
    }
    header('Content-Type: application/json');
    echo json_encode($out);
    exit;
}

// Nouvelle route pour récupérer les infos de la colonne
if ($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['column_info'])) {
    $cid = (int)$_GET['column_info'];
    $res = $db->query("SELECT rowid, label, type FROM llx_myworkspace_column WHERE rowid = $cid");
    if ($o = $db->fetch_object($res)) {
        header('Content-Type: application/json');
        echo json_encode(['id'=>$o->rowid,'label'=>$o->label,'type'=>$o->type]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error'=>'Column not found']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_option_column_id'], $_POST['option_label'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $cid   = (int)$_POST['add_option_column_id'];
    $label = $db->escape($_POST['option_label']);
    $color = isset($_POST['option_color']) ? $db->escape($_POST['option_color']) : '#cccccc';
    
    $existing = $db->query("SELECT rowid FROM llx_myworkspace_column_option WHERE fk_column = $cid AND label = '$label'");
    if ($db->num_rows($existing) > 0) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Une option avec ce nom existe déjà']);
        exit;
    }
    
    $r = $db->query("SELECT MAX(position) as m FROM llx_myworkspace_column_option WHERE fk_column=$cid");
    $p = ($r && $o=$db->fetch_object($r)) ? $o->m+1 : 0;
    $db->query("INSERT INTO llx_myworkspace_column_option (fk_column,label,color,position) VALUES ($cid,'$label','$color',$p)");
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['rename_option_id'], $_POST['rename_option_label'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $oid   = (int)$_POST['rename_option_id'];
    $label = $db->escape($_POST['rename_option_label']);
    
    $res = $db->query("SELECT fk_column FROM llx_myworkspace_column_option WHERE rowid = $oid");
    $opt = $db->fetch_object($res);
    if ($opt) {
        $existing = $db->query("SELECT rowid FROM llx_myworkspace_column_option WHERE fk_column = {$opt->fk_column} AND label = '$label' AND rowid != $oid");
        if ($db->num_rows($existing) > 0) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Une option avec ce nom existe déjà']);
            exit;
        }
    }
    
    $db->query("UPDATE llx_myworkspace_column_option SET label='$label' WHERE rowid=$oid");
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_option_id'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $oid = (int)$_POST['delete_option_id'];
    $db->query("DELETE FROM llx_myworkspace_column_option WHERE rowid=$oid");
    exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_option_color'], $_POST['option_color'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $oid   = (int)$_POST['update_option_color'];
    $color = $db->escape($_POST['option_color']);
    $db->query("UPDATE llx_myworkspace_column_option SET color='$color' WHERE rowid=$oid");
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['rename_column_id'], $_POST['rename_column_label'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $tid   = (int)$_POST['rename_column_id'];
    $label = $db->escape($_POST['rename_column_label']);
    $db->query("UPDATE llx_myworkspace_column SET label='$label' WHERE rowid=$tid");
    exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_column_id'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $tid = (int)$_POST['delete_column_id'];
    $db->query("DELETE FROM llx_myworkspace_column WHERE rowid=$tid");
    exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reorder_columns'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $order = json_decode($_POST['reorder_columns'], true);
    foreach ($order as $i=>$tid) {
        $db->query("UPDATE llx_myworkspace_column SET position=$i WHERE rowid=".(int)$tid);
    }
    exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reorder_tasks_columns'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $order = json_decode($_POST['reorder_tasks_columns'], true);
    foreach ($order as $i=>$id) {
        $db->query("UPDATE llx_myworkspace_task SET fk_column=".(int)$id." WHERE rowid=".(int)$id);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['task_cells'])) {
    $tid = (int)$_GET['task_cells'];
    $res = $db->query("SELECT fk_column, value FROM llx_myworkspace_cell WHERE fk_task = $tid");
    $out = [];
    while ($o = $db->fetch_object($res)) {
        $out[$o->fk_column] = $o->value;
    }
    header('Content-Type: application/json');
    echo json_encode($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_cell_task'], $_POST['save_cell_column'], $_POST['save_cell_value'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $tid = (int)$_POST['save_cell_task'];
    $cid = (int)$_POST['save_cell_column'];
    $val = $db->escape($_POST['save_cell_value']);
    
    $db->query("INSERT INTO llx_myworkspace_cell (fk_task, fk_column, value) VALUES ($tid, $cid, '$val')
                ON DUPLICATE KEY UPDATE value = '$val'");
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['task_comments'])) {
    $tid = (int)$_GET['task_comments'];
    $res = $db->query("
        SELECT c.rowid, c.comment, c.datec, c.fk_user, u.firstname, u.lastname 
        FROM llx_myworkspace_comment c
        LEFT JOIN llx_user u ON u.rowid = c.fk_user
        WHERE c.fk_task = $tid 
        ORDER BY c.datec DESC
    ");
    $out = [];
    while ($o = $db->fetch_object($res)) {
        $out[] = [
            'id' => $o->rowid,
            'comment' => $o->comment,
            'date' => $o->datec,
            'user_id' => $o->fk_user,
            'user_name' => trim($o->firstname . ' ' . $o->lastname) ?: 'Utilisateur'
        ];
    }
    header('Content-Type: application/json');
    echo json_encode($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_comment_task'], $_POST['comment_text'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $tid = (int)$_POST['add_comment_task'];
    $comment = $db->escape($_POST['comment_text']);
    $uid = $user->id;
    $date = date('Y-m-d H:i:s');
    
    // Insérer le commentaire
    $sql = "INSERT INTO llx_myworkspace_comment (fk_task, fk_user, comment, datec) VALUES ($tid, $uid, '$comment', '$date')";
    $result = $db->query($sql);
    
    if (!$result) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Erreur lors de l\'insertion du commentaire']);
        exit;
    }
    
    $new_id = $db->last_insert_id('llx_myworkspace_comment');
    if (!$new_id) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Impossible de récupérer l\'ID du commentaire']);
        exit;
    }
    
    // Récupérer les données du commentaire créé
    $res = $db->query("
        SELECT c.rowid, c.comment, c.datec, c.fk_user, u.firstname, u.lastname 
        FROM llx_myworkspace_comment c
        LEFT JOIN llx_user u ON u.rowid = c.fk_user
        WHERE c.rowid = $new_id
    ");
    
    $comment_data = $db->fetch_object($res);
    if (!$comment_data) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Commentaire créé mais impossible de le récupérer']);
        exit;
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'id' => $comment_data->rowid,
        'comment' => $comment_data->comment,
        'date' => $comment_data->datec,
        'user_id' => $comment_data->fk_user,
        'user_name' => trim($comment_data->firstname . ' ' . $comment_data->lastname) ?: 'Utilisateur'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['edit_comment_id'], $_POST['edit_comment_text'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $cid = (int)$_POST['edit_comment_id'];
    $comment = $db->escape($_POST['edit_comment_text']);
    $uid = $user->id;
    
    $res = $db->query("SELECT fk_user FROM llx_myworkspace_comment WHERE rowid = $cid");
    $owner = $db->fetch_object($res);
    
    if ($owner && $owner->fk_user == $uid) {
        $db->query("UPDATE llx_myworkspace_comment SET comment = '$comment' WHERE rowid = $cid");
        echo 'OK';
    } else {
        http_response_code(403);
        echo 'Accès refusé';
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_comment_id'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $cid = (int)$_POST['delete_comment_id'];
    $uid = $user->id;
    
    $res = $db->query("SELECT fk_user FROM llx_myworkspace_comment WHERE rowid = $cid");
    $owner = $db->fetch_object($res);
    
    if ($owner && $owner->fk_user == $uid) {
        $db->query("DELETE FROM llx_myworkspace_comment WHERE rowid = $cid");
        echo 'OK';
    } else {
        http_response_code(403);
        echo 'Accès refusé';
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['task_details'])) {
    $tid = (int)$_GET['task_details'];
    $res = $db->query("
        SELECT t.rowid, t.label, t.datec, g.label as group_label
        FROM llx_myworkspace_task t
        LEFT JOIN llx_myworkspace_group g ON g.rowid = t.fk_group
        WHERE t.rowid = $tid
    ");
    
    if ($task = $db->fetch_object($res)) {
        header('Content-Type: application/json');
        echo json_encode([
            'id' => $task->rowid,
            'label' => $task->label,
            'datec' => $task->datec,
            'group_label' => $task->group_label
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Tâche non trouvée']);
    }
    exit;
}

$res = $db->query("SELECT rowid,label FROM llx_myworkspace ORDER BY position ASC");
$workspaces = [];
while ($o=$db->fetch_object($res)) $workspaces[] = $o;

llxHeader("", "Mes espaces", "");
$formtoken = newToken();

$leftmenu = '<h3>Espaces de travail</h3>'
    . '<form method="POST" style="margin:10px 0;">'
    . '<input name="new_workspace" placeholder="Nouvel espace" required style="width:70%;cursor:pointer;">'
    . "<input type=\"hidden\" name=\"token\" value=\"$formtoken\">"
    . '<button style="padding:2px 8px;cursor:pointer;">+</button>'
    . '</form>'
    . '<ul id="workspace-list" style="list-style:none;padding:0;">';
foreach ($workspaces as $w) {
    $leftmenu .= '<li class="workspace-item" data-id="'.$w->rowid.'" '
               . 'style="padding:8px;cursor:pointer;">'
               . dol_escape_htmltag($w->label)
               . '</li>';
}
$leftmenu .= '</ul>';

ob_start();
?>
<link rel="stylesheet" href="<?php echo DOL_URL_ROOT ?>/custom/monday/css/main.css">

<div class="workspace-container">
  <div class="main-content" id="main-content"></div>
  
  <div id="task-detail-panel" class="task-detail-panel">
    <div class="panel-header">
      <h3 id="task-detail-title">Détail de la tâche</h3>
      <button id="close-panel" class="close-panel-btn">×</button>
    </div>
    
    <div class="panel-content">
      <div class="task-info-section">
        <h4>Informations</h4>
        <div class="task-meta">
          <div class="task-meta-item">
            <strong>Tâche :</strong>
            <span id="task-name-display"></span>
            <button id="edit-task-name" class="edit-btn">✎</button>
          </div>
          <div class="task-meta-item">
            <strong>Groupe :</strong>
            <span id="task-group-display"></span>
          </div>
          <div class="task-meta-item">
            <strong>Créée :</strong>
            <span id="task-created-display"></span>
          </div>
        </div>
      </div>
      
      <div class="comments-section">
        <h4>Commentaires</h4>
        
        <div class="add-comment-form">
          <textarea id="new-comment-text" placeholder="Ajouter un commentaire..." rows="3"></textarea>
          <button id="add-comment-btn">Publier</button>
        </div>
        
        <div id="comments-list" class="comments-list">
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script>
window.leftmenu = <?php echo json_encode($leftmenu); ?>;
window.formtoken = <?php echo json_encode($formtoken); ?>;
</script>
<script src="<?php echo DOL_URL_ROOT ?>/custom/monday/js/main.js"></script>

<?php
echo ob_get_clean();
llxFooter();
$db->close();
?>
