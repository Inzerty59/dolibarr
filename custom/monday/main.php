<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$langs->load("mymodule@mymodule");

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
    $r     = $db->query("SELECT MAX(position) as m FROM llx_myworkspace_task WHERE fk_group=$gid");
    $p     = ($r && $o=$db->fetch_object($r)) ? $o->m+1 : 0;
    $db->query("INSERT INTO llx_myworkspace_task (fk_group,label,position) VALUES ($gid,'$label',$p)");
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
</div>

<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script>
$(function(){
  $('.side-nav .vmenu').prepend(<?php echo json_encode($leftmenu) ?>);
  const token = <?php echo json_encode($formtoken) ?>;

  $('#workspace-list').sortable({
    cursor:'pointer',
    update(){
      const order = $('#workspace-list .workspace-item')
        .map((_,el)=>el.dataset.id).get();
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

  $(document).on('mouseenter','button, .workspace-item, .group-toggle, .group-label, .rename-group, .delete-group, .add-row-btn', function(){
    $(this).css('cursor','pointer');
  });

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
                        <th style="border:1px solid #ddd;padding:4px;">Tâche</th>
                        <th style="border:1px solid #ddd;padding:4px;">Statut</th>
                        <th style="border:1px solid #ddd;padding:4px;">Deadline</th>
                        <th style="border:1px solid #ddd;padding:4px;">Priorité</th>
                        <th style="border:1px solid #ddd;padding:4px;">Temps estimé</th>
                        <th style="border:1px solid #ddd;padding:4px;">Temps réel</th>
                      </tr>
                    </thead>
                    <tbody></tbody>
                  </table>
                  <button class="add-row-btn" style="padding:4px 8px;">+ Ajouter tâche</button>
                </div>
              </div>
            `);
            $('#group-list').append($grp);

            fetch(`?tasks_group_id=${g.id}`)
              .then(r=>r.json())
              .then(tasks=>{
                tasks.forEach(t=>{
                  $grp.find('tbody').append(`
                    <tr data-id="${t.id}">
                      <td style="border:1px solid #ddd;padding:4px;">${t.label}</td>
                      <td style="border:1px solid #ddd;padding:4px;"></td>
                      <td style="border:1px solid #ddd;padding:4px;"></td>
                      <td style="border:1px solid #ddd;padding:4px;"></td>
                      <td style="border:1px solid #ddd;padding:4px;"></td>
                      <td style="border:1px solid #ddd;padding:4px;"></td>
                    </tr>
                  `);
                });
                initTaskSortable();
              });
          });
          initGroupSortable();

          $('.group-toggle').off('click').on('click',function(){
            const $b=$(this).closest('.group').find('.group-body');
            $b.toggle();
            $(this).text($b.is(':visible')?'▼':'►');
          });

          $('#group-list').off('click','.rename-group').on('click','.rename-group',function(){
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
          });

          $('#group-list').off('click','.delete-group').on('click','.delete-group',function(){
            const $g=$(this).closest('.group');
            const gid=$g.data('id');
            if(!confirm('Supprimer ce groupe ?')) return;
            const fd=new FormData();
            fd.append('delete_group_id',gid);
            fd.append('token',token);
            fetch('',{method:'POST',body:fd}).then(()=>loadGroups(wid));
          });

          $('#group-list').off('click','.add-row-btn').on('click','.add-row-btn',function(){
            const gid=$(this).closest('.group').data('id');
            const lbl=prompt('Nom de la tâche :');
            if(!lbl) return;
            const fd=new FormData();
            fd.append('add_task_group_id',gid);
            fd.append('task_label',lbl);
            fd.append('token',token);
            fetch('',{method:'POST',body:fd}).then(()=>loadGroups(wid));
          });

          $('#group-list').off('click','tbody tr').on('click','tbody tr',function(){
            const rid=$(this).data('id');
            const old=$(this).find('td').first().text();
            const nw=prompt('Modifier le nom de la tâche :',old);
            if(!nw) return;
            const fd=new FormData();
            fd.append('rename_task_id',rid);
            fd.append('rename_task_label',nw);
            fd.append('token',token);
            fetch('',{method:'POST',body:fd}).then(()=>loadGroups(wid));
          });

          $('#group-list').off('dblclick','tbody tr').on('dblclick','tbody tr',function(){
            const rid=$(this).data('id');
            if(!confirm('Supprimer cette tâche ?')) return;
            const fd=new FormData();
            fd.append('delete_task_id',rid);
            fd.append('token',token);
            fetch('',{method:'POST',body:fd}).then(()=>loadGroups(wid));
          });
        });
    }

    loadGroups(wsId);
  });
});
</script>

<?php
print "<style>#blockvmenusearch{display:none!important;}</style>";

llxFooter();
$db->close();
?>
