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

// Récupérer les commentaires d'une tâche
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

// Ajouter un commentaire
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_comment_task'], $_POST['comment_text'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $tid = (int)$_POST['add_comment_task'];
    $comment = $db->escape($_POST['comment_text']);
    $uid = $user->id;
    $date = date('Y-m-d H:i:s');
    
    $db->query("INSERT INTO llx_myworkspace_comment (fk_task, fk_user, comment, datec) VALUES ($tid, $uid, '$comment', '$date')");
    
    // Retourner le commentaire créé
    $new_id = $db->last_insert_id();
    $res = $db->query("
        SELECT c.rowid, c.comment, c.datec, c.fk_user, u.firstname, u.lastname 
        FROM llx_myworkspace_comment c
        LEFT JOIN llx_user u ON u.rowid = c.fk_user
        WHERE c.rowid = $new_id
    ");
    $comment_data = $db->fetch_object($res);
    
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

// Modifier un commentaire
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['edit_comment_id'], $_POST['edit_comment_text'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $cid = (int)$_POST['edit_comment_id'];
    $comment = $db->escape($_POST['edit_comment_text']);
    $uid = $user->id;
    
    // Vérifier que l'utilisateur est propriétaire du commentaire
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

// Supprimer un commentaire
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_comment_id'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $cid = (int)$_POST['delete_comment_id'];
    $uid = $user->id;
    
    // Vérifier que l'utilisateur est propriétaire du commentaire
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

// Récupérer les détails d'une tâche
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
<link rel="stylesheet" href="<?php echo DOL_URL_ROOT ?>/custom/monday/styles.css">

<div class="workspace-container">
  <div class="main-content" id="main-content"></div>
  
  <!-- NOUVEAU : Panneau latéral de détail des tâches -->
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
          <!-- Les commentaires seront ajoutés ici dynamiquement -->
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script>
$(function(){
  $('.side-nav .vmenu').prepend(<?php echo json_encode($leftmenu) ?>);
  const token = <?php echo json_encode($formtoken) ?>;

  window.saveCellValue = function(input) {
    const taskId = $(input).data('task');
    const columnId = $(input).data('column');
    const value = $(input).val();
    
    if($(input).is('select')) {
      applySelectColor($(input));
    }
    
    const fd = new FormData();
    fd.append('save_cell_task', taskId);
    fd.append('save_cell_column', columnId);
    fd.append('save_cell_value', value);
    fd.append('token', token);
    
    fetch('', {method: 'POST', body: fd});
  };

  window.validateNumberInput = function(input) {
    const value = input.value;
    const allowedPattern = /^[0-9€$.,\s-]*$/;
    
    if (!allowedPattern.test(value)) {
      input.value = value.replace(/[^0-9€$.,\s-]/g, '');
    }
  };

  window.openTagsSelector = function(cell) {
    const $cell = $(cell);
    const taskId = $cell.data('task');
    const columnId = $cell.data('column');
    
    fetch(`?column_options=${columnId}`)
      .then(r=>r.json())
      .then(options=>{
        const selectedTags = [];
        $cell.find('.tag-item').each(function(){
          const tagId = parseInt($(this).data('tag-id'));
          if(tagId && !selectedTags.includes(tagId)) {
            selectedTags.push(tagId);
          }
        });
        
        console.log('Tags actuellement sélectionnés:', selectedTags);
        
        const modal = $(`
          <div id="tags-modal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;display:flex;align-items:center;justify-content:center;">
            <div style="background:white;padding:20px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.3);min-width:400px;max-height:80vh;overflow-y:auto;">
              <h3>Sélectionner des étiquettes</h3>
              
              <div id="available-tags" style="margin:15px 0;">
                ${options.map(opt => {
                  const isSelected = selectedTags.includes(parseInt(opt.id));
                  console.log(`Option ${opt.label} (ID: ${opt.id}) - Sélectionnée: ${isSelected}`); // Debug
                  return `
                    <div class="tag-option ${isSelected ? 'selected' : ''}" data-tag-id="${opt.id}" style="display:inline-block;margin:5px;padding:6px 12px;background:#87CEEB;color:white;border-radius:15px;cursor:pointer;border:2px solid ${isSelected ? '#000' : 'transparent'};">
                      ${opt.label}
                    </div>
                  `;
                }).join('')}
              </div>
              
              ${options.length === 0 ? '<p style="text-align:center;color:#666;font-style:italic;">Aucune étiquette disponible. Utilisez "Gérer options" dans le menu de la colonne pour en créer.</p>' : ''}
              
              <div style="margin-top:20px;text-align:right;display:flex;gap:10px;justify-content:flex-end;">
                <button id="save-tags" style="padding:8px 16px;background:#007cba;color:white;border:none;cursor:pointer;border-radius:4px;">Sauvegarder</button>
                <button id="cancel-tags" style="padding:8px 16px;background:#ccc;border:none;cursor:pointer;border-radius:4px;">Annuler</button>
              </div>
            </div>
          </div>
        `);
        
        $('body').append(modal);
        
        $('.tag-option').click(function(){
          $(this).toggleClass('selected');
          if($(this).hasClass('selected')) {
            $(this).css('border', '2px solid #000');
          } else {
            $(this).css('border', '2px solid transparent');
          }
        });
        
        $('#save-tags').click(function(){
          const selectedTagIds = [];
          $('.tag-option.selected').each(function(){
            selectedTagIds.push(parseInt($(this).data('tag-id')));
          });
          
          console.log('Tags à sauvegarder:', selectedTagIds);
          
          const fd = new FormData();
          fd.append('save_cell_task', taskId);
          fd.append('save_cell_column', columnId);
          fd.append('save_cell_value', JSON.stringify(selectedTagIds));
          fd.append('token', token);
          
          fetch('', {method: 'POST', body: fd}).then(()=>{
            modal.remove();
            
            fetch(`?column_options=${columnId}`)
              .then(r=>r.json())
              .then(allOptions=>{
                let tagsHtml = `
                  <div class="selected-tags" style="display:flex;flex-wrap:wrap;gap:3px;margin-bottom:5px;">
                `;
                
                selectedTagIds.forEach(tagId => {
                  const tag = allOptions.find(opt => parseInt(opt.id) === tagId);
                  if(tag) {
                    tagsHtml += `
                      <span class="tag-item" data-tag-id="${tag.id}" style="background:${tag.color};color:white;padding:2px 6px;border-radius:12px;font-size:11px;display:flex;align-items:center;gap:4px;">
                        ${tag.label}
                        <span class="remove-tag" onclick="removeTag(event, this)" style="cursor:pointer;font-weight:bold;">×</span>
                      </span>
                    `;
                  }
                });
                
                tagsHtml += `
                  </div>
                  <div class="add-tag-hint" style="color:#999;font-size:10px;font-style:italic;">+ Cliquer pour ajouter des étiquettes</div>
                `;
                
                $cell.html(tagsHtml);
              });
          });
        });
        
        $('#cancel-tags').click(function(){
          modal.remove();
        });
        
        modal.click(function(e){
          if(e.target === modal[0]) {
            modal.remove();
          }
        });
      });
  };

  window.removeTag = function(event, tagElement) {
    event.stopPropagation();
    const $cell = $(tagElement).closest('.tags-cell');
    const taskId = $cell.data('task');
    const columnId = $cell.data('column');
    
    $(tagElement).closest('.tag-item').remove();
    
    const remainingTags = [];
    $cell.find('.tag-item').each(function(){
      remainingTags.push($(this).data('tag-id'));
    });
    
    const fd = new FormData();
    fd.append('save_cell_task', taskId);
    fd.append('save_cell_column', columnId);
    fd.append('save_cell_value', JSON.stringify(remainingTags));
    fd.append('token', token);
    
    fetch('', {method: 'POST', body: fd}).then(()=>{
      console.log('Tag supprimé et sauvegardé');
    });
  };

  window.updateDeadline = function(input) {
    const $cell = $(input).closest('.deadline-cell');
    const taskId = $cell.data('task');
    const columnId = $cell.data('column');
    
    const startDate = $cell.find('.deadline-start').val();
    const endDate = $cell.find('.deadline-end').val();
    const value = `${startDate}|${endDate}`;
    
    let daysText = '';
    let daysClass = '';
    if(endDate) {
      const today = new Date();
      const end = new Date(endDate);
      const diffTime = end - today;
      const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
      
      if(diffDays > 0) {
        daysText = `${diffDays} jour${diffDays > 1 ? 's' : ''} restant${diffDays > 1 ? 's' : ''}`;
        daysClass = diffDays <= 3 ? 'deadline-urgent' : (diffDays <= 7 ? 'deadline-warning' : 'deadline-ok');
      } else if(diffDays === 0) {
        daysText = "Aujourd'hui";
        daysClass = 'deadline-urgent';
      } else {
        daysText = `En retard de ${Math.abs(diffDays)} jour${Math.abs(diffDays) > 1 ? 's' : ''}`;
        daysClass = 'deadline-overdue';
      }
    }
    
    const $daysDiv = $cell.find('.days-remaining');
    $daysDiv.text(daysText).removeClass('deadline-urgent deadline-warning deadline-ok deadline-overdue').addClass(daysClass);
    
    const fd = new FormData();
    fd.append('save_cell_task', taskId);
    fd.append('save_cell_column', columnId);
    fd.append('save_cell_value', value);
    fd.append('token', token);
    
    fetch('', {method: 'POST', body: fd});
  };

  function applySelectColor($select) {
    const selectedValue = $select.val();
    if(selectedValue) {
      const selectedOption = $select.find(`option[value="${selectedValue}"]`);
      const style = selectedOption.attr('style');
      if(style) {
        const color = style.match(/background:([^;]+)/)?.[1];
        if(color) {
          $select.css('background-color', color);
          return;
        }
      }
    }
    $select.css('background-color', 'transparent');
  }

  $('#workspace-list').sortable({
    cursor:'pointer',
    update(){
      const order = $('#workspace-list .workspace-item').map((_,el)=>el.dataset.id).get();
      fetch('',{method:'POST',body:new URLSearchParams({
        reorder_workspaces: JSON.stringify(order),
        token: token
      })});
    }
  }).disableSelection();

  function initGroupSortable(){
    $('#group-list').sortable({
      cursor:'pointer',
      update(){
        const order = $('#group-list .group').map((_,el)=>el.dataset.id).get();
        fetch('',{method:'POST',body:new URLSearchParams({
          reorder_groups: JSON.stringify(order),
          token: token
        })});
      }
    }).disableSelection();
  }
  function initTaskSortable(){
    $('.group-body tbody').sortable({
      cursor:'pointer',
      update(){
        const order = $(this).children().map((_,tr)=>tr.dataset.id).get();
        fetch('',{method:'POST',body:new URLSearchParams({
          reorder_tasks: JSON.stringify(order),
          token: token
        })});
      }
    }).disableSelection();
  }

  $(document).on('click','.workspace-item', function(){
    const wsId    = this.dataset.id;
    const wsLabel = this.textContent;
    $('#main-content').html(`
      <div style="display:flex;align-items:center;gap:10px;">
        <h2 style="margin:0;cursor:pointer;">${wsLabel}</h2>
        <button id="rename-btn" style="padding:2px 6px;">✎</button>
        <button id="delete-btn" style="padding:2px 6px;">✖</button>
      </div>
      <button id="add-group-btn" style="margin:1rem 0;">+ Ajouter un groupe</button>
      <div id="group-list"></div>
    `);

    $('#rename-btn').click(()=>{
      const n=prompt("Nouveau nom de l'espace :",wsLabel);
      if(!n) return;
      const fd=new FormData(); fd.append('rename_workspace_id',wsId);
      fd.append('rename_workspace_label',n); fd.append('token',token);
      fetch('',{method:'POST',body:fd}).then(()=>location.reload());
    });
    $('#delete-btn').click(()=>{
      if(!confirm('Supprimer cet espace ?')) return;
      const fd=new FormData(); fd.append('delete_workspace_id',wsId);
      fd.append('token',token);
      fetch('',{method:'POST',body:fd}).then(()=>location.reload());
    });
    $('#add-group-btn').click(()=>{
      const n=prompt('Nom du groupe :');
      if(!n) return;
      const fd=new FormData(); fd.append('add_group_workspace_id',wsId);
      fd.append('group_label',n); fd.append('token',token);
      fetch('',{method:'POST',body:fd}).then(()=>loadGroups(wsId));
    });

    function loadGroups(wid){
      fetch(`get_groups.php?wid=${wid}`)
        .then(r=>r.json()).then(groups=>{
          $('#group-list').empty();
          groups.forEach(g=>{
            fetch(`?columns_group_id=${g.id}`)
              .then(r=>r.json())
              .then(cols=>{
                let ths = `
                  <th style="border:1px solid #ddd;padding:4px;"></th>
                  <th style="border:1px solid #ddd;padding:4px;"></th>
                  <th style="border:1px solid #ddd;padding:4px;">Tâche</th>
                `;
                cols.forEach(c=>{
                  ths += `<th style="border:1px solid #ddd;padding:4px;position:relative;">
                            <span class="column-label" data-cid="${c.id}" style="cursor:pointer;">${c.label}</span>
                            <button class="column-menu-btn" data-cid="${c.id}" style="border:none;background:transparent;cursor:pointer;padding:0 2px;">⋮</button>
                            <div class="column-menu" style="display:none;position:absolute;right:0;top:22px;background:#fff;border:1px solid #ccc;z-index:10;">
                              <button class="rename-column-btn" data-cid="${c.id}" style="display:block;width:100%;border:none;background:transparent;cursor:pointer;padding:4px;">Renommer</button>
                              <button class="delete-column-btn" data-cid="${c.id}" style="display:block;width:100%;border:none;background:transparent;cursor:pointer;padding:4px;">Supprimer</button>
                              ${(c.type === 'select' || c.type === 'tags') ? `<button class="manage-options-btn" data-cid="${c.id}" style="display:block;width:100%;border:none;background:transparent;cursor:pointer;padding:4px;">Gérer options</button>` : ''}
                            </div>
                         </th>`;
                });
                ths += `<th style="border:1px solid #ddd;padding:4px;">
                          <button class="add-column-btn" data-gid="${g.id}" style="padding:2px 6px;">+</button>
                        </th>`;

                const $grp = $(`
                  <div class="group" data-id="${g.id}">
                    <div class="group-header" style="display:flex;justify-content:space-between;padding:8px;background:#f3f3f3;">
                      <div style="display:flex;align-items:center;gap:8px;">
                        <span class="group-toggle">▼</span>
                        <span class="group-label">${g.label}</span>
                      </div>
                      <div>
                        <button class="rename-group">✎</button>
                        <button class="delete-group">✖</button>
                      </div>
                    </div>
                    <div class="group-body" style="padding:10px;">
                      <table style="width:100%;border-collapse:collapse;margin-bottom:8px;">
                        <thead>
                          <tr style="background:#fafafa;">
                            ${ths}
                          </tr>
                        </thead>
                        <tbody></tbody>
                      </table>
                      <button class="add-row-btn" style="padding:4px 8px;">+ Ajouter tâche</button>
                    </div>
                  </div>
                `);

                if (g.collapsed === 1) {
                  $grp.find('.group-body').hide();
                  $grp.find('.group-toggle').text('►');
                }

                $('#group-list').append($grp);

                fetch(`?tasks_group_id=${g.id}`)
                  .then(r=>r.json())
                  .then(tasks=>{
                    tasks.forEach(t=>{
                      let tds = `
                        <td style="border:1px solid #ddd;padding:4px;text-align:center;">
                          <button class="rename-task-row" style="border:none;background:transparent;cursor:pointer;">✎</button>
                        </td>
                        <td style="border:1px solid #ddd;padding:4px;text-align:center;">
                          <button class="delete-task-row" style="border:none;background:transparent;cursor:pointer;">✖</button>
                        </td>
                        <td style="border:1px solid #ddd;padding:4px;">${t.label}</td>
                      `;
                      
                      fetch(`?task_cells=${t.id}`)
                        .then(r=>r.json())
                        .then(cells=>{
                          let cellPromises = [];
                          cols.forEach(c=>{
                            const cellValue = cells[c.id] || '';
                            
                            if(c.type === 'select') {
                              const promise = fetch(`?column_options=${c.id}`)
                                .then(r=>r.json())
                                .then(options=>{
                                  let selectHtml = `<select class="cell-select" data-task="${t.id}" data-column="${c.id}" 
                                                           style="border:none;background:transparent;width:100%;padding:2px;"
                                                           onchange="saveCellValue(this)">
                                                     <option value="">-- Choisir --</option>`;
                                  options.forEach(opt=>{
                                    const selected = cellValue == opt.id ? 'selected' : '';
                                    selectHtml += `<option value="${opt.id}" ${selected} style="background:${opt.color};">${opt.label}</option>`;
                                  });
                                  selectHtml += '</select>';
                                  return selectHtml;
                                });
                              cellPromises.push(promise);
                            } else if(c.type === 'number') {
                              const inputHtml = `<input type="text" class="cell-input cell-number" 
                                data-task="${t.id}" 
                                data-column="${c.id}" 
                                value="${cellValue}" 
                                style="border:none;background:transparent;width:100%;padding:2px;text-align:right;"
                                pattern="[0-9€$.,\\s-]*"
                                onblur="saveCellValue(this)"
                                onkeydown="if(event.key==='Enter') saveCellValue(this)"
                                oninput="validateNumberInput(this)">`;
                              cellPromises.push(Promise.resolve(inputHtml));
                            } else if(c.type === 'tags') {
                              const promise = fetch(`?column_options=${c.id}`)
                                .then(r=>r.json())
                                .then(options=>{
                                  const selectedTags = cellValue ? JSON.parse(cellValue) : [];
                                  
                                  let tagsHtml = `
                                    <div class="tags-cell" data-task="${t.id}" data-column="${c.id}" style="min-height:30px;padding:3px;border:1px dashed #ddd;cursor:pointer;" onclick="openTagsSelector(this)">
                                      <div class="selected-tags" style="display:flex;flex-wrap:wrap;gap:3px;margin-bottom:5px;">
                                  `;
                                  
                                  selectedTags.forEach(tagId => {
                                    const tag = options.find(opt => opt.id == tagId);
                                    if(tag) {
                                      tagsHtml += `
                                        <span class="tag-item" data-tag-id="${tag.id}" style="background:${tag.color};color:white;padding:2px 6px;border-radius:12px;font-size:11px;display:flex;align-items:center;gap:4px;">
                                          ${tag.label}
                                          <span class="remove-tag" onclick="removeTag(event, this)" style="cursor:pointer;font-weight:bold;">×</span>
                                        </span>
                                      `;
                                    }
                                  });
                                  
                                  tagsHtml += `
                                      </div>
                                      <div class="add-tag-hint" style="color:#999;font-size:10px;font-style:italic;">+ Cliquer pour ajouter des étiquettes</div>
                                    </div>
                                  `;
                                  
                                  return tagsHtml;
                                });
                              cellPromises.push(promise);
                            } else if(c.type === 'deadline') {
                              const dates = cellValue ? cellValue.split('|') : ['', ''];
                              const startDate = dates[0] || '';
                              const endDate = dates[1] || '';
                              
                              let daysText = '';
                              let daysClass = '';
                              if(endDate) {
                                const today = new Date();
                                const end = new Date(endDate);
                                const diffTime = end - today;
                                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                                
                                if(diffDays > 0) {
                                  daysText = `${diffDays} jour${diffDays > 1 ? 's' : ''} restant${diffDays > 1 ? 's' : ''}`;
                                  daysClass = diffDays <= 3 ? 'deadline-urgent' : (diffDays <= 7 ? 'deadline-warning' : 'deadline-ok');
                                } else if(diffDays === 0) {
                                  daysText = "Aujourd'hui";
                                  daysClass = 'deadline-urgent';
                                } else {
                                  daysText = `En retard de ${Math.abs(diffDays)} jour${Math.abs(diffDays) > 1 ? 's' : ''}`;
                                  daysClass = 'deadline-overdue';
                                }
                              }
                              
                              const inputHtml = `
                                <div class="deadline-cell" data-task="${t.id}" data-column="${c.id}">
                                  <div style="display:flex;gap:5px;margin-bottom:3px;">
                                    <input type="date" class="deadline-start" value="${startDate}" 
                                           style="border:1px solid #ddd;padding:2px;font-size:10px;width:48%;"
                                           placeholder="Début"
                                           onchange="updateDeadline(this)">
                                    <input type="date" class="deadline-end" value="${endDate}" 
                                           style="border:1px solid #ddd;padding:2px;font-size:10px;width:48%;"
                                           placeholder="Fin"
                                           onchange="updateDeadline(this)">
                                  </div>
                                  <div class="days-remaining ${daysClass}" style="font-size:11px;text-align:center;font-weight:bold;">${daysText}</div>
                                </div>
                              `;
                              cellPromises.push(Promise.resolve(inputHtml));
                            } else if(c.type === 'date') {
                              const inputHtml = `<input type="date" class="cell-input cell-date" 
                                                        data-task="${t.id}" 
                                                        data-column="${c.id}" 
                                                        value="${cellValue}" 
                                                        style="border:none;background:transparent;width:100%;padding:2px;cursor:pointer;"
                                                        onblur="saveCellValue(this)"
                                                        onchange="saveCellValue(this)">`;
                              cellPromises.push(Promise.resolve(inputHtml));
                            } else {
                              const inputHtml = `<input type="text" class="cell-input" 
                                                        data-task="${t.id}" 
                                                        data-column="${c.id}" 
                                                        value="${cellValue}" 
                                                        style="border:none;background:transparent;width:100%;padding:2px;"
                                                        onblur="saveCellValue(this)"
                                                        onkeydown="if(event.key==='Enter') saveCellValue(this)">`;
                              cellPromises.push(Promise.resolve(inputHtml));
                            }
                          });
                          
                          Promise.all(cellPromises).then(cellsHtml=>{
                            cellsHtml.forEach(cellHtml=>{
                              tds += `<td style="border:1px solid #ddd;padding:4px;">${cellHtml}</td>`;
                            });
                            tds += `<td style="border:1px solid #ddd;padding:4px;"></td>`;
                            const $taskRow = $(`<tr data-id="${t.id}" style="cursor:pointer;">${tds}</tr>`);
                            $grp.find('tbody').append($taskRow);
                            
                            $taskRow.find('td:nth-child(3)').click(function(e) {
                              // Éviter le clic si on clique sur un bouton
                              if ($(e.target).is('button')) return;
                              
                              const taskName = $(this).text();
                              const groupName = $grp.find('.group-label').text();
                              openTaskDetail(t.id, taskName, groupName);
                            });
                            
                            $grp.find('select.cell-select').each(function(){
                              applySelectColor($(this));
                            });
                          });
                        });
                    });
                    initTaskSortable();
                  });
              });
          });

          initGroupSortable();

          $('#group-list').off('click','.add-column-btn').on('click','.add-column-btn',function(e){
            e.stopPropagation();
            const gid = $(this).data('gid');
            const lbl = prompt('Nom de la colonne :');
            if(!lbl) return;
            
            const typeModal = $(`
              <div id="type-modal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1001;display:flex;align-items:center;justify-content:center;">
                <div style="background:white;padding:25px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.3);min-width:350px;">
                  <h3 style="margin:0 0 20px 0;text-align:center;color:#333;">Choisir le type de colonne</h3>
                  <div style="display:flex;flex-direction:column;gap:12px;margin:20px 0;">
                    <button class="type-choice" data-type="text" style="padding:15px;border:2px solid #e0e0e0;background:#f9f9f9;cursor:pointer;border-radius:8px;display:flex;align-items:center;gap:15px;font-size:14px;transition:all 0.2s;">
                      <span style="font-size:20px;">📝</span>
                      <div style="text-align:left;">
                        <div style="font-weight:bold;">Texte</div>
                        <div style="font-size:12px;color:#666;">Saisie libre de texte</div>
                      </div>
                    </button>
                    <button class="type-choice" data-type="number" style="padding:15px;border:2px solid #e0e0e0;background:#f9f9f9;cursor:pointer;border-radius:8px;display:flex;align-items:center;gap:15px;font-size:14px;transition:all 0.2s;">
                      <span style="font-size:20px;">🔢</span>
                      <div style="text-align:left;">
                        <div style="font-weight:bold;">Nombre</div>
                        <div style="font-size:12px;color:#666;">Saisie numérique uniquement
</div>
                      </div>
                    </button>
                    <button class="type-choice" data-type="select" style="padding:15px;border:2px solid #e0e0e0;background:#f9f9f9;cursor:pointer;border-radius:8px;display:flex;align-items:center;gap:15px;font-size:14px;transition:all 0.2s;">
                      <span style="font-size:20px;">📋</span>
                      <div style="text-align:left;">
                        <div style="font-weight:bold;">Liste déroulante</div>
                        <div style="font-size:12px;color:#666;">Options prédéfinies avec couleurs</div>
                      </div>
                    </button>
                    <button class="type-choice" data-type="tags" style="padding:15px;border:2px solid #e0e0e0;background:#f9f9f9;cursor:pointer;border-radius:8px;display:flex;align-items:center;gap:15px;font-size:14px;transition:all 0.2s;">
                      <span style="font-size:20px;">🏷️</span>
                      <div style="text-align:left;">
                        <div style="font-weight:bold;">Étiquettes</div>
                        <div style="font-size:12px;color:#666;">Tags multiples</div>
                      </div>
                    </button>
                    <button class="type-choice" data-type="date" style="padding:15px;border:2px solid #e0e0e0;background:#f9f9f9;cursor:pointer;border-radius:8px;display:flex;align-items:center;gap:15px;font-size:14px;transition:all 0.2s;">
                      <span style="font-size:20px;">📅</span>
                      <div style="text-align:left;">
                        <div style="font-weight:bold;">Date</div>
                        <div style="font-size:12px;color:#666;">Sélecteur de calendrier</div>
                      </div>
                    </button>
                    <button class="type-choice" data-type="deadline" style="padding:15px;border:2px solid #e0e0e0;background:#f9f9f9;cursor:pointer;border-radius:8px;display:flex;align-items:center;gap:15px;font-size:14px;transition:all 0.2s;">
                      <span style="font-size:20px;">⏰</span>
                      <div style="text-align:left;">
                        <div style="font-weight:bold;">Échéance</div>
                        <div style="font-size:12px;color:#666;">Période avec décompte des jours</div>
                      </div>
                    </button>
                  </div>
                  <div style="text-align:center;margin-top:20px;">
                    <button id="cancel-type" style="padding:10px 20px;background:#ccc;border:none;cursor:pointer;border-radius:6px;color:#666;">Annuler</button>
                  </div>
                </div>
              </div>
            `);
            
            $('body').append(typeModal);
            
            $('.type-choice').hover(
              function() {
                $(this).css({
                  'border-color': '#007cba',
                  'background': '#f0f8ff',
                  'transform': 'translateY(-2px)',
                  'box-shadow': '0 4px 12px rgba(0,124,186,0.15)'
                });
              },
              function() {
                $(this).css({
                  'border-color': '#e0e0e0',
                  'background': '#f9f9f9',
                  'transform': 'translateY(0)',
                  'box-shadow': 'none'
                });
              }
            );
            
            $('.type-choice').click(function(){
              const type = $(this).data('type');
              const fd = new FormData();
              fd.append('add_column_group_id',gid);
              fd.append('column_label',lbl);
              fd.append('column_type',type);
              fd.append('token',token);
              fetch('',{method:'POST',body:fd}).then(()=>{
                typeModal.remove();
                loadGroups(wid);
              });
            });
            
            $('#cancel-type').click(function(){
              typeModal.remove();
            });
            
            typeModal.click(function(e){
              if(e.target === typeModal[0]) {
                typeModal.remove();
              }
            });
          });

          $('.group-toggle').off('click').on('click',function(e){
            e.stopPropagation();
            const $g    = $(this).closest('.group');
            const $body = $g.find('.group-body');
            $body.toggle();
            $(this).text($body.is(':visible') ? '▼' : '►');
            const newState = $body.is(':visible') ? 0 : 1;
            const fd = new FormData();
            fd.append('toggle_group_id', $g.data('id'));
            fd.append('collapsed', newState);
            fd.append('token', token);
            fetch('',{method:'POST',body:fd});
          });

          $('#group-list')
            .off('click','.rename-group').on('click','.rename-group',function(){
              const $g=$(this).closest('.group');
              const gid=$g.data('id');
              const old=$g.find('.group-label').text();
              const nw=prompt('Nouveau nom du groupe :',old);
              if(!nw) return;
              const fd=new FormData();
              fd.append('rename_group_id',gid);
              fd.append('group_label',nw);
              fd.append('token',token);
              fetch('',{method:'POST',body:fd}).then(()=>loadGroups(wid));
            })
            .off('click','.delete-group').on('click','.delete-group',function(){
              const $g=$(this).closest('.group');
              const gid=$g.data('id');
              if(!confirm('Supprimer ce groupe ?')) return;
              const fd=new FormData();
              fd.append('delete_group_id',gid);
              fd.append('token',token);
              fetch('',{method:'POST',body:fd}).then(()=>loadGroups(wid));
            })
            .off('click','.add-row-btn').on('click','.add-row-btn',function(){
              const gid=$(this).closest('.group').data('id');
              const lbl=prompt('Nom de la tâche :');
              if(!lbl) return;
              const fd=new FormData();
              fd.append('add_task_group_id',gid);
              fd.append('task_label',lbl);
              fd.append('token',token);
              fetch('',{method:'POST',body:fd}).then(()=>loadGroups(wid));
            })
            .off('click','.delete-task-row').on('click','.delete-task-row',function(e){
              e.stopPropagation();
              const $tr = $(this).closest('tr');
              const tid = $tr.data('id');
              if(!confirm('Supprimer cette tâche ?')) return;
              const fd=new FormData();
              fd.append('delete_task_id',tid);
              fd.append('token',token);
              fetch('',{method:'POST',body:fd}).then(()=>loadGroups(wid));
            })
            .off('click','.rename-task-row').on('click','.rename-task-row',function(e){
              e.stopPropagation();
              const $tr = $(this).closest('tr');
              const tid = $tr.data('id');
              const old = $tr.find('td:nth-child(3)').text();
              const nw = prompt('Modifier le nom de la tâche :', old);
              if(!nw) return;
              const fd=new FormData();
              fd.append('rename_task_id',tid);
              fd.append('rename_task_label',nw);
              fd.append('token',token);
              fetch('',{method:'POST',body:fd}).then(()=>loadGroups(wid));
            })
            .off('click','.rename-column-btn').on('click','.rename-column-btn',function(e){
              e.stopPropagation();
              const cid = $(this).data('cid');
              const old = $(this).closest('.column-menu').siblings('.column-label').text();
              const nw = prompt('Nouveau nom de la colonne :', old);
              if(!nw) return;
              const fd = new FormData();
              fd.append('rename_column_id', cid);
              fd.append('rename_column_label', nw);
              fd.append('token', token);
              fetch('',{method:'POST',body:fd}).then(()=>loadGroups(wid));
            })
            .off('click','.delete-column-btn').on('click','.delete-column-btn',function(e){
              e.stopPropagation();
              const cid = $(this).data('cid');
              if(!confirm('Supprimer cette colonne ?')) return;
              const fd = new FormData();
              fd.append('delete_column_id', cid);
              fd.append('token', token);
              fetch('',{method:'POST',body:fd}).then(()=>loadGroups(wid));
            })
            .off('click','.manage-options-btn').on('click','.manage-options-btn',function(e){
              e.stopPropagation();
              const cid = $(this).data('cid');
              
              fetch(`?columns_group_id=${$(this).closest('.group').data('id')}`)
                .then(r=>r.json())
                .then(columns=>{
                  const currentColumn = columns.find(col => col.id == cid);
                  const isSelectType = currentColumn && currentColumn.type === 'select';
                  
                  fetch(`?column_options=${cid}`)
                    .then(r=>r.json())
                    .then(options=>{
                      const modal = $(`
                        <div id="options-modal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;display:flex;align-items:center;justify-content:center;">
                          <div style="background:white;padding:20px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.3);min-width:400px;max-height:80vh;overflow-y:auto;">
                            <h3>Gérer les options</h3>
                            
                            <div id="options-list" class="options-list" style="margin:15px 0;">
                              ${options.map(opt => `
                                <div class="option-row" data-id="${opt.id}" style="display:flex;align-items:center;gap:10px;margin:8px 0;padding:8px;border:1px solid #ddd;border-radius:4px;">
                                  <div style="width:20px;height:20px;background:${opt.color};border-radius:3px;"></div>
                                  <span class="option-label" style="flex:1;">${opt.label}</span>
                                  <button class="rename-option" data-id="${opt.id}" style="padding:4px 8px;border:none;background:#f3f3f3;cursor:pointer;">✎</button>
                                  <button class="delete-option" data-id="${opt.id}" style="padding:4px 8px;border:none;background:#f3f3f3;cursor:pointer;">✖</button>
                                  ${isSelectType ? `<button class="change-color-option" data-id="${opt.id}" style="padding:4px 8px;border:none;background:#f3f3f3;cursor:pointer;">🎨</button>` : ''}
                                </div>
                              `).join('')}
                            </div>
                            
                            <hr style="margin:15px 0;">
                            <h4>Ajouter une nouvelle option</h4>
                            <div style="display:flex;gap:10px;align-items:center;margin:10px 0;">
                              <input type="text" id="new-option-name" placeholder="Nom de l'option" style="flex:1;padding:8px;border:1px solid #ddd;">
                              ${isSelectType ? `
                                <input type="color" id="new-option-color" value="#007cba" style="width:40px;height:32px;border:none;cursor:pointer;">
                              ` : ''}
                              <button id="add-new-option-btn" style="padding:8px 12px;background:#007cba;color:white;border:none;cursor:pointer;border-radius:4px;">Ajouter</button>
                            </div>
                            
                            ${isSelectType ? `
                              <div style="margin:10px 0;">
                                <label style="font-weight:bold;margin-bottom:5px;display:block;">Couleurs prédéfinies :</label>
                                <div style="display:flex;flex-wrap:wrap;gap:5px;">
                                  <div class="preset-color" data-color="#007cba" style="width:25px;height:25px;background:#007cba;border:2px solid #ddd;cursor:pointer;border-radius:3px;"></div>
                                  <div class="preset-color" data-color="#28a745" style="width:25px;height:25px;background:#28a745;border:2px solid #ddd;cursor:pointer;border-radius:3px;"></div>
                                  <div class="preset-color" data-color="#dc3545" style="width:25px;height:25px;background:#dc3545;border:2px solid #ddd;cursor:pointer;border-radius:3px;"></div>
                                  <div class="preset-color" data-color="#ffc107" style="width:25px;height:25px;background:#ffc107;border:2px solid #ddd;cursor:pointer;border-radius:3px;"></div>
                                  <div class="preset-color" data-color="#fd7e14" style="width:25px;height:25px;background:#fd7e14;border:2px solid #ddd;cursor:pointer;border-radius:3px;"></div>
                                  <div class="preset-color" data-color="#6f42c1" style="width:25px;height:25px;background:#6f42c1;border:2px solid #ddd;cursor:pointer;border-radius:3px;"></div>
                                </div>
                              </div>
                            ` : ''}
                            
                            <div style="margin-top:20px;text-align:right;">
                              <button id="close-options" style="padding:8px 16px;background:#ccc;border:none;cursor:pointer;">Fermer</button>
                            </div>
                          </div>
                        </div>
                      `);
                      
                      $('body').append(modal);
                      
                      if(isSelectType) {
                        $('.preset-color').click(function(){
                          const color = $(this).data('color');
                          $('#new-option-color').val(color);
                          $('.preset-color').css('border', '2px solid #ddd');
                          $(this).css('border', '2px solid #000');
                        });
                      }
                      
                      function attachOptionHandlers($row) {
                        $row.find('.rename-option').click(function(){
                          const optId = $(this).data('id');
                          const $row = $(this).closest('.option-row');
                          const oldLabel = $row.find('.option-label').text();
                          const newLabel = prompt('Nouveau nom:', oldLabel);
                          if(!newLabel || newLabel === oldLabel) return;
                          
                          const fd = new FormData();
                          fd.append('rename_option_id', optId);
                          fd.append('rename_option_label', newLabel);
                          fd.append('token', token);
                          
                          fetch('',{method:'POST',body:fd})
                            .then(r=>r.text())
                            .then(response=>{
                              try {
                                const json = JSON.parse(response);
                                if(json.error) {
                                  alert(json.error);
                                }
                              } catch(e) {
                                $row.find('.option-label').text(newLabel);
                              }
                            });
                        });
                        
                        $row.find('.delete-option').click(function(){
                          const optId = $(this).data('id');
                          const $row = $(this).closest('.option-row');
                          const optLabel = $row.find('.option-label').text();
                          
                          if(!confirm(`Supprimer l'option "${optLabel}" ?`)) return;
                          
                          const fd = new FormData();
                          fd.append('delete_option_id', optId);
                          fd.append('token', token);
                          
                          fetch('',{method:'POST',body:fd}).then(()=>{
                            $row.remove();
                          });
                        });
                        
                        if(isSelectType) {
                          $row.find('.change-color-option').click(function(){
                            const optId = $(this).data('id');
                            const $row = $(this).closest('.option-row');
                            const $colorDiv = $row.find('div[style*="background"]');
                            
                            const colorModal = $(`
                              <div style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);z-index:1001;display:flex;align-items:center;justify-content:center;">
                                <div style="background:white;padding:20px;border-radius:8px;">
                                  <h4>Choisir une couleur</h4>
                                  <input type="color" id="color-picker" style="width:60px;height:40px;border:none;cursor:pointer;">
                                  <div style="margin:10px 0;display:flex;flex-wrap:wrap;gap:5px;">
                                    <div class="color-preset" data-color="#007cba" style="width:30px;height:30px;background:#007cba;border:2px solid #ddd;cursor:pointer;border-radius:3px;"></div>
                                    <div class="color-preset" data-color="#28a745" style="width:30px;height:30px;background:#28a745;border:2px solid #ddd;cursor:pointer;border-radius:3px;"></div>
                                    <div class="color-preset" data-color="#dc3545" style="width:30px;height:30px;background:#dc3545;border:2px solid #ddd;cursor:pointer;border-radius:3px;"></div>
                                  </div>
                                  <div style="text-align:right;margin-top:15px;">
                                    <button id="save-color" style="padding:8px 16px;background:#007cba;color:white;border:none;cursor:pointer;">OK</button>
                                    <button id="cancel-color" style="padding:8px 16px;background:#ccc;border:none;cursor:pointer;">Annuler</button>
                                  </div>
                                </div>
                              </div>
                            `);
                            
                            $('body').append(colorModal);
                            
                            $('.color-preset').click(function(){
                              $('#color-picker').val($(this).data('color'));
                            });
                            
                            $('#save-color').click(function(){
                              const newColor = $('#color-picker').val();
                              const fd = new FormData();
                              fd.append('update_option_color', optId);
                              fd.append('option_color', newColor);
                              fd.append('token', token);
                              
                              fetch('',{method:'POST',body:fd}).then(()=>{
                                $colorDiv.css('background', newColor);
                                colorModal.remove();
                              });
                            });
                            
                            $('#cancel-color').click(function(){
                              colorModal.remove();
                            });
                          });
                        }
                      }
                      
                      function addNewOption() {
                        const optLabel = $('#new-option-name').val().trim();
                        
                        if(!optLabel) {
                          alert('Le nom de l\'option est obligatoire');
                          return;
                        }
                        
                        const optColor = isSelectType ? $('#new-option-color').val() : '#87CEEB';
                        
                        const fd = new FormData();
                        fd.append('add_option_column_id', cid);
                        fd.append('option_label', optLabel);
                        fd.append('option_color', optColor);
                        fd.append('token', token);
                        
                        fetch('',{method:'POST',body:fd})
                          .then(r=>r.text())
                          .then(response=>{
                            try {
                              const json = JSON.parse(response);
                              if(json.error) {
                                alert(json.error);
                                return;
                              }
                            } catch(e) {
                              fetch(`?column_options=${cid}`)
                                .then(r=>r.json())
                                .then(updatedOptions=>{
                                  const newOption = updatedOptions.find(opt => opt.label === optLabel);
                                  if(newOption) {
                                    const newRow = $(`
                                      <div class="option-row" data-id="${newOption.id}" style="display:flex;align-items:center;gap:10px;margin:8px 0;padding:8px;border:1px solid #ddd;border-radius:4px;">
                                        <div style="width:20px;height:20px;background:${newOption.color};border-radius:3px;"></div>
                                        <span class="option-label" style="flex:1;">${optLabel}</span>
                                        <button class="rename-option" data-id="${newOption.id}" style="padding:4px 8px;border:none;background:#f3f3f3;cursor:pointer;">✎</button>
                                        <button class="delete-option" data-id="${newOption.id}" style="padding:4px 8px;border:none;background:#f3f3f3;cursor:pointer;">✖</button>
                                        ${isSelectType ? `<button class="change-color-option" data-id="${newOption.id}" style="padding:4px 8px;border:none;background:#f3f3f3;cursor:pointer;">🎨</button>` : ''}
                                      </div>
                                    `);
                                    $('#options-list').append(newRow);
                                    attachOptionHandlers(newRow);
                                    $('#new-option-name').val('');
                                    if(isSelectType) $('#new-option-color').val('#007cba');
                                    
                                    if(isSelectType) {
                                      $(`select[data-column="${cid}"]`).each(function(){
                                        const currentValue = $(this).val();
                                        const $select = $(this);
                                        
                                        $select.append(`<option value="${newOption.id}" style="background:${newOption.color};">${optLabel}</option>`);
                                        
                                        $select.val(currentValue);
                                        applySelectColor($select);
                                      });
                                    }
                                  }
                                });
                            }
                          });
                      }
                      
                      $('.option-row').each(function(){
                        attachOptionHandlers($(this));
                      });
                      
                      $('#add-new-option-btn').click(function(){
                        addNewOption();
                      });
                      
                      $('#new-option-name').keydown(function(e){
                        if(e.key === 'Enter') {
                          addNewOption();
                        }
                      });
                      
                      $('#close-options').click(function(){
                        modal.remove();
                      });
                      
                      modal.click(function(e){
                        if(e.target === modal[0]) {
                          modal.remove();
                        }
                      });
                    });
                });
            })
            .off('click','.column-menu-btn').on('click','.column-menu-btn',function(e){
              e.stopPropagation();
              $('.column-menu').hide();
              $(this).siblings('.column-menu').toggle();
            })
            .off('click','.column-menu').on('click','.column-menu',function(e){
              e.stopPropagation();
            });

        });
    }

    loadGroups(wsId);
  });
});

// Variables globales pour le panneau
let currentTaskId = null;

// Fonction pour ouvrir le panneau de détail d'une tâche
window.openTaskDetail = function(taskId, taskName, groupName) {
  currentTaskId = taskId;
  
  // Mettre à jour les informations de base
  $('#task-detail-title').text('Détail de la tâche');
  $('#task-name-display').text(taskName);
  $('#task-group-display').text(groupName);
  $('#task-created-display').text('Chargement...'); // Temporaire
  
  // Ouvrir le panneau
  $('#task-detail-panel').addClass('open');
  
  // Charger les détails complets de la tâche
  fetch(`?task_details=${taskId}`)
    .then(r => r.json())
    .then(task => {
      if (task.datec) {
        // Formater la date de création
        const createdDate = new Date(task.datec);
        const formattedDate = createdDate.toLocaleDateString('fr-FR', {
          day: '2-digit',
          month: '2-digit',
          year: 'numeric',
          hour: '2-digit',
          minute: '2-digit',
          second: '2-digit'
        });
        $('#task-created-display').text(formattedDate);
      } else {
        $('#task-created-display').text('Date non disponible');
      }
      
      // Mettre à jour les autres informations si nécessaire
      $('#task-name-display').text(task.label);
      $('#task-group-display').text(task.group_label);
    })
    .catch(err => {
      console.error('Erreur lors du chargement des détails:', err);
      $('#task-created-display').text('Erreur de chargement');
    });
  
  // Charger les commentaires
  loadComments(taskId);
};

// Fonction pour fermer le panneau
window.closeTaskDetail = function() {
  $('#task-detail-panel').removeClass('open');
  currentTaskId = null;
};

// Fonction pour charger les commentaires
function loadComments(taskId) {
  fetch(`?task_comments=${taskId}`)
    .then(r => r.json())
    .then(comments => {
      const $commentsList = $('#comments-list');
      $commentsList.empty();
      
      if (comments.length === 0) {
        $commentsList.append(`
          <div class="no-comments" style="text-align:center;color:#666;font-style:italic;padding:20px;">
            Aucun commentaire pour cette tâche
          </div>
        `);
        return;
      }
      
      comments.forEach(comment => {
        const commentDate = new Date(comment.date);
        const formattedDate = commentDate.toLocaleDateString('fr-FR', {
          day: '2-digit',
          month: '2-digit',
          year: 'numeric',
          hour: '2-digit',
          minute: '2-digit'
        });
        
        const $comment = $(`
          <div class="comment-item" data-comment-id="${comment.id}">
            <div class="comment-header">
              <span class="comment-author">${comment.user_name}</span>
              <span class="comment-date">${formattedDate}</span>
            </div>
            <div class="comment-text">${comment.comment}</div>
            <div class="comment-actions">
              <button class="comment-action-btn edit-comment-btn" data-comment-id="${comment.id}">Modifier</button>
              <button class="comment-action-btn delete-comment-btn" data-comment-id="${comment.id}">Supprimer</button>
            </div>
          </div>
        `);
        
        $commentsList.append($comment);
      });
    })
    .catch(err => {
      console.error('Erreur lors du chargement des commentaires:', err);
    });
}

// Fonction pour ajouter un commentaire
function addComment() {
  const commentText = $('#new-comment-text').val().trim();
  
  if (!commentText) {

    alert('Veuillez saisir un commentaire');
    return;
  }
  
  if (!currentTaskId) {
    alert('Erreur: aucune tâche sélectionnée');
    return;
  }
  
  const fd = new FormData();
  fd.append('add_comment_task', currentTaskId);
  fd.append('comment_text', commentText);
  fd.append('token', token);
  
  fetch('', {method: 'POST', body: fd})
    .then(r => r.json())
    .then(comment => {
      $('#new-comment-text').val('');
      loadComments(currentTaskId); // Recharger tous les commentaires
    })
    .catch(err => {
      console.error('Erreur lors de l\'ajout du commentaire:', err);
      alert('Erreur lors de l\'ajout du commentaire');
    });
}

// Gestionnaires d'événements pour le panneau
$('#close-panel').click(closeTaskDetail);

$('#add-comment-btn').click(addComment);

$('#new-comment-text').keydown(function(e) {
  if (e.ctrlKey && e.key === 'Enter') {
    addComment();
  }
});

// Gestionnaire pour modifier le nom de la tâche
$('#edit-task-name').click(function() {
  const currentName = $('#task-name-display').text();
  const newName = prompt('Nouveau nom de la tâche:', currentName);
  
  if (!newName || newName === currentName) return;
  
  const fd = new FormData();
  fd.append('rename_task_id', currentTaskId);
  fd.append('rename_task_label', newName);
  fd.append('token', token);
  
  fetch('', {method: 'POST', body: fd})
    .then(() => {
      $('#task-name-display').text(newName);
      // Recharger la vue principale pour refléter le changement
      const wsId = $('.workspace-item[style*="font-weight: bold"], .workspace-item[style*="background"]').data('id') || $('.workspace-item').first().data('id');
      if (wsId) loadGroups(wsId);
    })
    .catch(err => {
      console.error('Erreur lors de la modification du nom:', err);
      alert('Erreur lors de la modification du nom');
    });
});

// Gestionnaires pour les actions sur les commentaires (délégués)
$(document).on('click', '.edit-comment-btn', function() {
  const commentId = $(this).data('comment-id');
  const $commentItem = $(this).closest('.comment-item');
  const $commentText = $commentItem.find('.comment-text');
  const currentText = $commentText.text();
  
  // Créer le formulaire d'édition
  const $editForm = $(`
    <div class="edit-comment-form">
      <textarea class="edit-comment-textarea">${currentText}</textarea>
      <div class="edit-comment-actions">
        <button class="save-edit-btn" data-comment-id="${commentId}">Sauver</button>
        <button class="cancel-edit-btn">Annuler</button>
      </div>
    </div>
  `);
  
  $commentText.hide();
  $commentItem.find('.comment-actions').hide();
  $commentItem.append($editForm);
});

$(document).on('click', '.save-edit-btn', function() {
  const commentId = $(this).data('comment-id');
  const $commentItem = $(this).closest('.comment-item');
  const newText = $commentItem.find('.edit-comment-textarea').val().trim();
  
  if (!newText) {
    alert('Le commentaire ne peut pas être vide');
    return;
  }
  
  const fd = new FormData();
  fd.append('edit_comment_id', commentId);
  fd.append('edit_comment_text', newText);
  fd.append('token', token);
  
  fetch('', {method: 'POST', body: fd})
    .then(r => r.text())
    .then(response => {
      if (response === 'OK') {
        loadComments(currentTaskId); // Recharger les commentaires
      } else {
        alert('Erreur lors de la modification');
      }
    })
    .catch(err => {
      console.error('Erreur lors de la modification:', err);
      alert('Erreur lors de la modification');
    });
});

$(document).on('click', '.cancel-edit-btn', function() {
  const $commentItem = $(this).closest('.comment-item');
  $commentItem.find('.edit-comment-form').remove();
  $commentItem.find('.comment-text, .comment-actions').show();
});

$(document).on('click', '.delete-comment-btn', function() {
  const commentId = $(this).data('comment-id');
  
  if (!confirm('Êtes-vous sûr de vouloir supprimer ce commentaire ?')) {
    return;
  }
  
  const fd = new FormData();
  fd.append('delete_comment_id', commentId);
  fd.append('token', token);
  
  fetch('', {method: 'POST', body: fd})
    .then(r => r.text())
    .then(response => {
      if (response === 'OK') {
        loadComments(currentTaskId); // Recharger les commentaires
      } else {
        alert('Erreur lors de la suppression');
      }
    })
    .catch(err => {
      console.error('Erreur lors de la suppression:', err);
      alert('Erreur lors de la suppression');
    });
});
</script>

<style>
.column-menu {
  min-width: 120px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.column-menu button:hover {
  background: #f3f3f3;
}
.color-option:hover {
  transform: scale(1.1);
  transition: transform 0.1s;
}
.cell-date {
  cursor: pointer;
}
.cell-date::-webkit-calendar-picker-indicator {
  cursor: pointer;
  opacity: 0.7;
}
.cell-date::-webkit-calendar-picker-indicator:hover {
  opacity: 1;
}

.cell-number {
  cursor: pointer;
  font-family: 'Courier New', monospace;
  font-weight: 500;
}
.cell-number:focus {
  background: #f8f9fa !important;
  border: 1px solid #007cba !important;
  border-radius: 3px;
  box-shadow: 0 0 0 2px rgba(0, 124, 186, 0.1);
}
.cell-number:invalid {
  border: 1px solid #dc3545 !important;
  background: #fff5f5 !important;
}
.cell-number:focus:valid {
  color: #28a745;
}

.type-choice {
  transition: all 0.2s ease;
}

.deadline-cell {
  min-height: 50px;
  padding: 3px;
}
.deadline-ok {
  color: #155724;
  background: #d1e7dd;
  padding: 2px 6px;
  border-radius: 4px;
}
.deadline-warning {
  color: #664d03;
  background: #fff3cd;
  padding: 2px 6px;
  border-radius: 4px;
}
.deadline-urgent {
  color: #842029;
  background: #f8d7da;
  padding: 2px 6px;
  border-radius: 4px;
}
.deadline-overdue {
  color: #842029;
  background: #f5c2c7;
  padding: 2px 6px;
  border-radius: 4px;
  font-weight: bold;
}

.tags-cell {
  transition: all 0.2s ease;
}
.tags-cell:hover {
  background: #f8f9fa !important;
  border-color: #007cba !important;
}

.remove-tag {
  opacity: 0.7;
  transition: opacity 0.2s;
}
.remove-tag:hover {
  opacity: 1;
}
.tag-option {
  transition: all 0.2s ease;
}
.tag-option:hover {
  box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}
.add-tag-hint {
  transition: opacity 0.2s;
}
.tags-cell:hover .add-tag-hint {
  opacity: 0.7;
}

/* Panneau latéral de détail des tâches */
.workspace-container {
  display: flex;
  height: 100vh;
  overflow: hidden;
}

.main-content {
  flex: 1;
  overflow-y: auto;
  transition: width 0.3s ease;
}

.task-detail-panel {
  width: 0;
  min-width: 0;
  background: #fff;
  border-left: 1px solid #ddd;
  overflow: hidden;
  transition: width 0.3s ease;
  display: flex;
  flex-direction: column;
}

.task-detail-panel.open {
  width: 400px;
  min-width: 400px;
}

.panel-header {
  padding: 20px;
  border-bottom: 1px solid #eee;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #f8f9fa;
}

.panel-header h3 {
  margin: 0;
  font-size: 18px;
  color: #333;
}

.close-panel-btn {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #666;
  padding: 0;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  transition: background 0.2s;
}

.close-panel-btn:hover {
  background: #e9ecef;
}

.panel-content {
  flex: 1;
  padding: 20px;
  overflow-y: auto;
}

.task-info-section {
  margin-bottom: 30px;
}

.task-info-section h4 {
  margin: 0 0 15px 0;
  color: #333;
  font-size: 16px;
  border-bottom: 2px solid #007cba;
  padding-bottom: 5px;
}

.task-meta {
  background: #f8f9fa;
  padding: 15px;
  border-radius: 6px;
}

.task-meta-item {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 10px;
  padding: 8px 0;
}

.task-meta-item:last-child {
  margin-bottom: 0;
}

.task-meta-item strong {
  min-width: 80px;
  color: #555;
  font-size: 14px;
}

.edit-btn {
  background: none;
  border: none;
  cursor: pointer;
  color: #007cba;
  font-size: 14px;
  padding: 2px 4px;
  border-radius: 3px;
  transition: background 0.2s;
}

.edit-btn:hover {
  background: #e9ecef;
}

.comments-section h4 {
  margin: 0 0 20px 0;
  color: #333;
  font-size: 16px;
  border-bottom: 2px solid #007cba;
  padding-bottom: 5px;
}

.add-comment-form {
  margin-bottom: 25px;
  padding: 15px;
  background: #f8f9fa;
  border-radius: 6px;
}

.add-comment-form textarea {
  width: 100%;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-family: inherit;
  font-size: 14px;
  resize: vertical;
  min-height: 80px;
}

.add-comment-form textarea:focus {
  outline: none;
  border-color: #007cba;
  box-shadow: 0 0 0 2px rgba(0, 124, 186, 0.1);
}

#add-comment-btn {
  margin-top: 10px;
  padding: 8px 16px;
  background: #007cba;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 14px;
  transition: background 0.2s;
}

#add-comment-btn:hover {
  background: #0056a3;
}

.comments-list {
  max-height: 500px;
  overflow-y: auto;
}

.comment-item {
  background: white;
  border: 1px solid #e9ecef;
  border-radius: 6px;
  padding: 15px;
  margin-bottom: 15px;
  transition: box-shadow 0.2s;
}

.comment-item:hover {
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.comment-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.comment-author {
  font-weight: bold;
  color: #007cba;
  font-size: 14px;
}

.comment-date {
  font-size: 12px;
  color: #666;
}

.comment-text {
  color: #333;
  line-height: 1.4;
  margin-bottom: 10px;
  font-size: 14px;
  white-space: pre-wrap;
}

.comment-actions {
  display: flex;
  gap: 10px;
}

.comment-action-btn {
  background: none;
  border: none;
  cursor: pointer;
  color: #666;
  font-size: 12px;
  padding: 4px 8px;
  border-radius: 3px;
  transition: all 0.2s;
}

.comment-action-btn:hover {
  background: #f8f9fa;
  color: #007cba;
}

.edit-comment-form {
  margin-top: 10px;
}

.edit-comment-form textarea {
  width: 100%;
  padding: 8px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-family: inherit;
  font-size: 14px;
  min-height: 60px;
}

.edit-comment-actions {
  margin-top: 8px;
  display: flex;
  gap: 8px;
}

.save-edit-btn, .cancel-edit-btn {
  padding: 4px 12px;
  border: none;
  border-radius: 3px;
  cursor: pointer;
  font-size: 12px;
}

.save-edit-btn {
  background: #28a745;
  color: white;
}

.cancel-edit-btn {
  background: #6c757d;
  color: white;
}

/* Responsive */
@media (max-width: 768px) {
  .task-detail-panel.open {
    width: 100%;
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    z-index: 1000;
  }
  
  .main-content {
    width: 100%;
  }
}
</style>

<?php
llxFooter();
$db->close();
?>
