<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$langs->load("mymodule@mymodule");

// Reorder workspaces
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reorder_workspaces'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $order = json_decode($_POST['reorder_workspaces'], true);
    foreach ($order as $index => $id) {
        $db->query("UPDATE llx_myworkspace SET position = " . ((int)$index) . " WHERE rowid = " . ((int)$id));
    }
    exit;
}
// Reorder groups
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reorder_groups'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $order = json_decode($_POST['reorder_groups'], true);
    foreach ($order as $index => $id) {
        $db->query("UPDATE llx_myworkspace_group SET position = " . ((int)$index) . " WHERE rowid = " . ((int)$id));
    }
    exit;
}
// Add workspace
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['new_workspace'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $new_workspace = dol_htmlentities($_POST['new_workspace'], ENT_QUOTES, 'UTF-8');
    $res = $db->query("SELECT MAX(position) as maxpos FROM llx_myworkspace");
    $pos = ($res && $obj = $db->fetch_object($res)) ? $obj->maxpos + 1 : 0;
    $db->query("INSERT INTO llx_myworkspace (label, position) VALUES ('" . $db->escape($new_workspace) . "', $pos)");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
// Rename workspace
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_workspace_id'], $_POST['rename_workspace_label'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $id = (int)$_POST['rename_workspace_id'];
    $label = $db->escape($_POST['rename_workspace_label']);
    $db->query("UPDATE llx_myworkspace SET label = '".$label."' WHERE rowid = ".$id);
    exit;
}
// Delete workspace
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_workspace_id'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $id = (int)$_POST['delete_workspace_id'];
    $db->query("DELETE FROM llx_myworkspace WHERE rowid = ".$id);
    exit;
}
// Add group
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_group_workspace_id'], $_POST['group_label'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $fk_workspace = (int)$_POST['add_group_workspace_id'];
    $label = $db->escape($_POST['group_label']);
    $res = $db->query("SELECT MAX(position) as maxpos FROM llx_myworkspace_group WHERE fk_workspace = $fk_workspace");
    $pos = ($res && $obj = $db->fetch_object($res)) ? $obj->maxpos + 1 : 0;
    $db->query("INSERT INTO llx_myworkspace_group (fk_workspace, label, position) VALUES ($fk_workspace, '".$label."', $pos)");
    exit;
}
// Rename group
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_group_id'], $_POST['group_label'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $id = (int)$_POST['rename_group_id'];
    $label = $db->escape($_POST['group_label']);
    $db->query("UPDATE llx_myworkspace_group SET label = '".$label."' WHERE rowid = ".$id);
    exit;
}
// Delete group
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_group_id'])) {
    if ($_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $id = (int)$_POST['delete_group_id'];
    $db->query("DELETE FROM llx_myworkspace_group WHERE rowid = ".$id);
    exit;
}

// Fetch workspaces
$res = $db->query("SELECT rowid, label FROM llx_myworkspace ORDER BY position ASC, label ASC");
$workspaces = [];
if ($res) while ($obj = $db->fetch_object($res)) $workspaces[] = $obj;

llxHeader("", "Mes espaces", "");
$formtoken = newToken();

// Sidebar menu HTML
$leftmenu = '<h3>Espaces de travail</h3>' .
    '<form method="POST" id="add-workspace-form" style="margin:10px 0;">' .
    '<input type="text" name="new_workspace" placeholder="Nouvel espace" required style="width:70%; cursor:pointer;">' .
    '<input type="hidden" name="token" value="'.$formtoken.'">' .
    '<button type="submit" style="padding:2px 8px; cursor:pointer;">+</button>' .
    '</form>' .
    '<ul id="workspace-list" style="list-style:none;padding:0;">';
foreach ($workspaces as $w) {
    $leftmenu .= '<li class="workspace-item" data-id="'.$w->rowid.'" style="padding:8px; cursor:pointer;">'.dol_escape_htmltag($w->label).'</li>';
}
$leftmenu .= '</ul>';

ob_start();
?>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script>
$(function() {
    // Insert sidebar
    const nav = document.querySelector('.side-nav .vmenu');
    if (!nav) return;
    const menuDiv = document.createElement('div');
    menuDiv.innerHTML = REPLACEMENU;
    nav.prepend(menuDiv);
    const token = REPLACETOKEN;

    // Enable drag & drop for workspaces
    $('#workspace-list').sortable({
        cursor: 'pointer',
        update: function() {
            const order = $('#workspace-list .workspace-item')
                .map((_, el) => el.dataset.id)
                .get();
            fetch('', {
                method: 'POST',
                body: new URLSearchParams({
                    reorder_workspaces: JSON.stringify(order),
                    token: token
                })
            });
        }
    }).disableSelection();

    // Drag & drop for groups initializer
    const initGroupSortable = () => {
        $('#group-list').sortable({
            cursor: 'pointer',
            update: function() {
                const order = $('#group-list .group')
                    .map((_, el) => el.dataset.id)
                    .get();
                fetch('', {
                    method: 'POST',
                    body: new URLSearchParams({
                        reorder_groups: JSON.stringify(order),
                        token: token
                    })
                });
            }
        }).disableSelection();
    };

    // Apply pointer cursor to interactive elements
    menuDiv.querySelectorAll(
        '.workspace-item, button, .group, .rename-group, .delete-group, .group-toggle, .group-label'
    ).forEach(e => e.style.cursor = 'pointer');

    // Workspace click handler
    menuDiv.querySelectorAll('.workspace-item').forEach(item => {
        item.addEventListener('click', () => {
            const id    = item.dataset.id;
            const label = item.textContent;

            $('#main-content').html(`
                <div style="display:flex; align-items:center; gap:10px;">
                    <h2 id="workspace-label" style="margin:0;cursor:pointer;">${label}</h2>
                    <button id="rename-btn" style="padding:2px 6px;cursor:pointer;">✎</button>
                    <button id="delete-btn" style="padding:2px 6px;cursor:pointer;">✖</button>
                </div>
                <button id="add-group-btn" style="margin:1rem 0;cursor:pointer;">+ Ajouter un groupe</button>
                <div id="group-list"></div>
            `);

            // Rename workspace
            $('#rename-btn').click(() => {
                const newName = prompt('Nouveau nom de l\'espace :', label);
                if (!newName) return;
                const fd = new FormData();
                fd.append('rename_workspace_id', id);
                fd.append('rename_workspace_label', newName);
                fd.append('token', token);
                fetch('', { method:'POST', body: fd })
                  .then(() => location.reload());
            });

            // Delete workspace
            $('#delete-btn').click(() => {
                if (!confirm('Supprimer cet espace ?')) return;
                const fd = new FormData();
                fd.append('delete_workspace_id', id);
                fd.append('token', token);
                fetch('', { method:'POST', body: fd })
                  .then(() => location.reload());
            });

            // Load groups and setup their handlers
            const loadGroups = wid => {
                fetch(`get_groups.php?wid=${wid}`)
                    .then(r => r.json())
                    .then(data => {
                        $('#group-list').empty();
                        data.forEach(g => {
                            const div = $('<div>')
                                .addClass('group')
                                .attr('data-id', g.id)
                                .css({
                                    border: '1px solid #ccc',
                                    'border-radius': '5px',
                                    margin: '10px 0',
                                    cursor: 'pointer'
                                })
                                .html(`
                                    <div class="group-header" style="display:flex; justify-content:space-between; padding:8px; background:#f3f3f3;">
                                        <div style="display:flex; align-items:center; gap:8px;">
                                            <span class="group-toggle" style="cursor:pointer;">▼</span>
                                            <span class="group-label" data-id="${g.id}" style="font-weight:bold;cursor:pointer;">${g.label}</span>
                                        </div>
                                        <div>
                                            <button class="rename-group" style="cursor:pointer;">✎</button>
                                            <button class="delete-group" style="cursor:pointer;">✖</button>
                                        </div>
                                    </div>
                                    <div class="group-body" style="padding:10px;">(contenu futur)</div>
                                `);
                            $('#group-list').append(div);
                        });
                        initGroupSortable();

                        // Collapse/expand functionality
                        $('.group-toggle').click(function() {
                            const body = $(this)
                                .closest('.group')
                                .find('.group-body');
                            body.toggle();
                            $(this).text(body.is(':visible') ? '▼' : '►');
                        });

                        // Rename group
                        $('.rename-group').click(function() {
                            const parent   = $(this).closest('.group');
                            const gid      = parent.data('id');
                            const oldLabel = parent.find('.group-label').text();
                            const newLbl   = prompt('Nouveau nom du groupe :', oldLabel);
                            if (!newLbl) return;
                            const fd = new FormData();
                            fd.append('rename_group_id', gid);
                            fd.append('group_label', newLbl);
                            fd.append('token', token);
                            fetch('', { method:'POST', body: fd })
                              .then(() => loadGroups(wid));
                        });

                        // Delete group
                        $('.delete-group').click(function() {
                            if (!confirm('Supprimer ce groupe ?')) return;
                            const gid = $(this).closest('.group').data('id');
                            const fd  = new FormData();
                            fd.append('delete_group_id', gid);
                            fd.append('token', token);
                            fetch('', { method:'POST', body: fd })
                              .then(() => loadGroups(wid));
                        });
                    });
            };

            // Add group
            $('#add-group-btn').click(() => {
                const lbl = prompt('Nom du groupe :');
                if (!lbl) return;
                const fd = new FormData();
                fd.append('add_group_workspace_id', id);
                fd.append('group_label', lbl);
                fd.append('token', token);
                fetch('', { method:'POST', body: fd })
                  .then(() => loadGroups(id));
            });

            loadGroups(id);
        });
    });
});
</script>
<?php
$script = ob_get_clean();
$script = str_replace('REPLACEMENU', json_encode($leftmenu), $script);
$script = str_replace('REPLACETOKEN', json_encode($formtoken), $script);
print $script;
print "<link rel='stylesheet' href='custom/monday/styles.css'>";
print "<div class='workspace-container'><div class='main-content' id='main-content'></div></div>";
print "<style>#blockvmenusearch{display:none!important;}</style>";
llxFooter(); $db->close();
?>
