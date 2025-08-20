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
                              ${c.type === 'select' ? `<button class="manage-options-btn" data-cid="${c.id}" style="display:block;width:100%;border:none;background:transparent;cursor:pointer;padding:4px;">Gérer options</button>` : ''}
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
                              const inputHtml = `<input type="number" class="cell-input cell-number" 
                            data-task="${t.id}" 
                            data-column="${c.id}" 
                            value="${cellValue}" 
                            style="border:none;background:transparent;width:100%;padding:2px;text-align:right;"
                            step="any"
                            onblur="saveCellValue(this)"
                            onkeydown="if(event.key==='Enter') saveCellValue(this)">`;
                              cellPromises.push(Promise.resolve(inputHtml));
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
                            $grp.find('tbody').append(`<tr data-id="${t.id}">${tds}</tr>`);
                            
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
                        <div style="font-size:12px;color:#666;">Saisie numérique uniquement</div>
                      </div>
                    </button>
                    <button class="type-choice" data-type="select" style="padding:15px;border:2px solid #e0e0e0;background:#f9f9f9;cursor:pointer;border-radius:8px;display:flex;align-items:center;gap:15px;font-size:14px;transition:all 0.2s;">
                      <span style="font-size:20px;">📋</span>
                      <div style="text-align:left;">
                        <div style="font-weight:bold;">Liste déroulante</div>
                        <div style="font-size:12px;color:#666;">Options prédéfinies avec couleurs</div>
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
              
              fetch(`?column_options=${cid}`)
                .then(r=>r.json())
                .then(options=>{
                  const modal = $(`
                    <div id="options-modal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;display:flex;align-items:center;justify-content:center;">
                      <div style="background:white;padding:20px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.3);min-width:400px;max-height:80vh;overflow-y:auto;">
                        <h3>Gérer les options</h3>
                        
                        <div id="options-list" style="margin:15px 0;">
                          ${options.map(opt => `
                            <div class="option-row" data-id="${opt.id}" style="display:flex;align-items:center;gap:10px;margin:8px 0;padding:8px;border:1px solid #ddd;border-radius:4px;">
                              <div style="width:20px;height:20px;background:${opt.color};border-radius:3px;"></div>
                              <span class="option-label" style="flex:1;">${opt.label}</span>
                              <button class="rename-option" data-id="${opt.id}" style="padding:4px 8px;border:none;background:#f3f3f3;cursor:pointer;">✎</button>
                              <button class="delete-option" data-id="${opt.id}" style="padding:4px 8px;border:none;background:#f3f3f3;cursor:pointer;">✖</button>
                            </div>
                          `).join('')}
                        </div>
                        
                        <hr style="margin:15px 0;">
                        <h4>Ajouter une nouvelle option</h4>
                        <div style="display:flex;gap:10px;align-items:center;margin:10px 0;">
                          <input type="text" id="new-option-name" placeholder="Nom de l'option" style="flex:1;padding:8px;border:1px solid #ddd;">
                        </div>
                        
                        <h5>Couleurs prédéfinies :</h5>
                        <div id="color-palette" style="display:grid;grid-template-columns:repeat(8,1fr);gap:5px;margin:10px 0;">
                          <div class="color-option" data-color="#ff0000" style="width:30px;height:30px;background:#ff0000;border:2px solid #ddd;cursor:pointer;border-radius:4px;" title="Rouge"></div>
                          <div class="color-option" data-color="#ff8000" style="width:30px;height:30px;background:#ff8000;border:2px solid #ddd;cursor:pointer;border-radius:4px;" title="Orange"></div>
                          <div class="color-option" data-color="#ffff00" style="width:30px;height:30px;background:#ffff00;border:2px solid #ddd;cursor:pointer;border-radius:4px;" title="Jaune"></div>
                          <div class="color-option" data-color="#80ff00" style="width:30px;height:30px;background:#80ff00;border:2px solid #ddd;cursor:pointer;border-radius:4px;" title="Vert clair"></div>
                          <div class="color-option" data-color="#00ff00" style="width:30px;height:30px;background:#00ff00;border:2px solid #ddd;cursor:pointer;border-radius:4px;" title="Vert"></div>
                          <div class="color-option" data-color="#00ff80" style="width:30px;height:30px;background:#00ff80;border:2px solid #ddd;cursor:pointer;border-radius:4px;" title="Vert menthe"></div>
                          <div class="color-option" data-color="#00ffff" style="width:30px;height:30px;background:#00ffff;border:2px solid #ddd;cursor:pointer;border-radius:4px;" title="Cyan"></div>
                          <div class="color-option" data-color="#0080ff" style="width:30px;height:30px;background:#0080ff;border:2px solid #ddd;cursor:pointer;border-radius:4px;" title="Bleu clair"></div>
                          <div class="color-option" data-color="#0000ff" style="width:30px;height:30px;background:#0000ff;border:2px solid #ddd;cursor:pointer;border-radius:4px;" title="Bleu"></div>
                          <div class="color-option" data-color="#8000ff" style="width:30px;height:30px;background:#8000ff;border:2px solid #ddd;cursor:pointer;border-radius:4px;" title="Violet"></div>
                          <div class="color-option" data-color="#ff00ff" style="width:30px;height:30px;background:#ff00ff;border:2px solid #ddd;cursor:pointer;border-radius:4px;" title="Magenta"></div>
                          <div class="color-option" data-color="#ff0080" style="width:30px;height:30px;background:#ff0080;border:2px solid #ddd;cursor:pointer;border-radius:4px;" title="Rose"></div>
                          <div class="color-option" data-color="#333333" style="width:30px;height:30px;background:#333333;border:2px solid #ddd;cursor:pointer;border-radius:4px;" title="Gris foncé"></div>
                          <div class="color-option" data-color="#666666" style="width:30px;height:30px;background:#666666;border:2px solid #ddd;cursor:pointer;border-radius:4px;" title="Gris moyen"></div>
                          <div class="color-option" data-color="#999999" style="width:30px;height:30px;background:#999999;border:2px solid #ddd;cursor:pointer;border-radius:4px;" title="Gris clair"></div>
                          <div class="color-option" data-color="#cccccc" style="width:30px;height:30px;background:#cccccc;border:2px solid #ddd;cursor:pointer;border-radius:4px;" title="Gris très clair"></div>
                        </div>
                        
                        <h5>Ou couleur personnalisée :</h5>
                        <div style="display:flex;gap:10px;align-items:center;margin:10px 0;">
                          <input type="color" id="custom-color" value="#cccccc" style="width:50px;height:35px;border:none;cursor:pointer;">
                          <button id="use-custom" style="padding:8px 12px;background:#007cba;color:white;border:none;cursor:pointer;">Utiliser cette couleur</button>
                        </div>
                        
                        <div style="margin-top:20px;text-align:right;">
                          <button id="close-options" style="padding:8px 16px;background:#ccc;border:none;cursor:pointer;">Fermer</button>
                        </div>
                      </div>
                    </div>
                  `);
                  
                  $('body').append(modal);
                  
                  let selectedColor = '#cccccc';

                  function addNewOption() {
                    const optLabel = $('#new-option-name').val().trim();
                    
                    if(!optLabel) {
                      alert('Le nom de l\'option est obligatoire');
                      return;
                    }
                    
                    const fd = new FormData();
                    fd.append('add_option_column_id', cid);
                    fd.append('option_label', optLabel);
                    fd.append('option_color', selectedColor);
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
                            .then(opts=>{
                              const newOpt = opts.find(o => o.label === optLabel);
                              if(newOpt) {
                                const newRow = $(`
                                  <div class="option-row" data-id="${newOpt.id}" style="display:flex;align-items:center;gap:10px;margin:8px 0;padding:8px;border:1px solid #ddd;border-radius:4px;">
                                    <div style="width:20px;height:20px;background:${selectedColor};border-radius:3px;"></div>
                                    <span class="option-label" style="flex:1;">${optLabel}</span>
                                    <button class="rename-option" data-id="${newOpt.id}" style="padding:4px 8px;border:none;background:#f3f3f3;cursor:pointer;">✎</button>
                                    <button class="delete-option" data-id="${newOpt.id}" style="padding:4px 8px;border:none;background:#f3f3f3;cursor:pointer;">✖</button>
                                  </div>
                                `);
                                $('#options-list').append(newRow);
                                
                                attachOptionHandlers(newRow);
                              }
                              
                              $('#new-option-name').val('');
                              $('#custom-color').val('#cccccc');
                              $('.color-option').css('border', '2px solid #ddd');
                              selectedColor = '#cccccc';
                            });
                        }
                      });
                  }

                  $('.color-option').click(function(){
                    selectedColor = $(this).data('color');
                    $('.color-option').css('border', '2px solid #ddd');
                    $(this).css('border', '3px solid #000'); 
                    addNewOption();
                  });

                  $('#use-custom').click(function(){
                    selectedColor = $('#custom-color').val();
                    addNewOption();
                  });

                  $('#new-option-name').keydown(function(e){
                    if(e.key === 'Enter') {
                      selectedColor = '#cccccc';
                      addNewOption();
                    }
                  });

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
                  }

                  $('.rename-option').click(function(){
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
                  
                  $('.delete-option').click(function(){
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
                  
                  $('#close-options').click(function(){
                    modal.remove();
                    loadGroups(wid);
                  });
                  
                  modal.click(function(e){
                    if(e.target === modal[0]) {
                      modal.remove();
                      loadGroups(wid);
                    }
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

$(document).off('click.columnmenu').on('click.columnmenu', function(e) {
  if (!$(e.target).closest('.column-menu-btn, .column-menu, .group-toggle').length) {
    $('.column-menu').hide();
  }
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
}
.cell-number:focus {
  background: #f8f9fa !important;
  border: 1px solid #007cba !important;
  border-radius: 3px;
}
.cell-number::-webkit-outer-spin-button,
.cell-number::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
.cell-number[type=number] {
  -moz-appearance: textfield;
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

@media (max-width: 768px) {
  .deadline-cell input[type="date"] {
    font-size: 9px !important;
    width: 45% !important;
  }
  .days-remaining {
    font-size: 9px !important;
  }
}

.deadline-start, .deadline-end {
  border-radius: 3px !important;
  transition: border-color 0.2s;
}
.deadline-start:focus, .deadline-end:focus {
  border-color: #007cba !important;
  outline: none;
  box-shadow: 0 0 0 2px rgba(0, 124, 186, 0.25);
}

.deadline-cell .days-remaining:empty::before {
  content: "Définir les dates";
  color: #6c757d;
  font-style: italic;
  font-size: 10px;
}

#blockvmenusearch{display:none!important;}
</style>

<?php
llxFooter();
$db->close();
?>
