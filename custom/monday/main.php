<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$langs->load("mymodule@mymodule");

// Gestion du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['new_workspace'])) {
    if (empty($_POST['token']) || $_POST['token'] !== $_SESSION['newtoken']) {
        accessforbidden('CSRF token invalid');
    }
    $new_workspace = dol_htmlentities($_POST['new_workspace'], ENT_QUOTES, 'UTF-8');
    $db->query("INSERT INTO llx_myworkspace (label) VALUES ('" . $db->escape($new_workspace) . "')");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_workspace_id'], $_POST['rename_workspace_label'])) {
    if (empty($_POST['token']) || $_POST['token'] !== $_SESSION['newtoken']) {
        accessforbidden('CSRF token invalid');
    }
    $id = (int) $_POST['rename_workspace_id'];
    $label = $db->escape($_POST['rename_workspace_label']);
    $db->query("UPDATE llx_myworkspace SET label = '".$label."' WHERE rowid = ".$id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_workspace_id'])) {
    if (empty($_POST['token']) || $_POST['token'] !== $_SESSION['newtoken']) {
        accessforbidden('CSRF token invalid');
    }
    $id = (int) $_POST['delete_workspace_id'];
    $db->query("DELETE FROM llx_myworkspace WHERE rowid = ".$id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_group_workspace_id'], $_POST['group_label'])) {
    if (empty($_POST['token']) || $_POST['token'] !== $_SESSION['newtoken']) {
        accessforbidden('CSRF token invalid');
    }
    $fk_workspace = (int) $_POST['add_group_workspace_id'];
    $label = $db->escape($_POST['group_label']);
    $db->query("INSERT INTO llx_myworkspace_group (fk_workspace, label) VALUES ($fk_workspace, '".$label."')");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_group_id'], $_POST['group_label'])) {
    if (empty($_POST['token']) || $_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $id = (int) $_POST['rename_group_id'];
    $label = $db->escape($_POST['group_label']);
    $db->query("UPDATE llx_myworkspace_group SET label = '".$label."' WHERE rowid = ".$id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_group_id'])) {
    if (empty($_POST['token']) || $_POST['token'] !== $_SESSION['newtoken']) accessforbidden('CSRF token invalid');
    $id = (int) $_POST['delete_group_id'];
    $db->query("DELETE FROM llx_myworkspace_group WHERE rowid = ".$id);
    exit;
}

$res = $db->query("SELECT rowid, label FROM llx_myworkspace ORDER BY label ASC");
$workspaces = [];
if ($res) {
    while ($obj = $db->fetch_object($res)) {
        $workspaces[] = $obj;
    }
}

llxHeader("", "Mes espaces", "");

$formtoken = newToken();

$leftmenu = '<h3>Espaces de travail</h3>
<form method="POST" id="add-workspace-form" style="margin: 10px 0;">
    <input type="text" name="new_workspace" placeholder="Nouvel espace" required style="width:70%;">
    <input type="hidden" name="token" value="' . $formtoken . '">
    <button type="submit" style="padding:2px 8px;">+</button>
</form>
<ul id="workspace-list">';
foreach ($workspaces as $w) {
    $leftmenu .= '<li class="workspace-item" data-id="' . $w->rowid . '">' . dol_escape_htmltag($w->label) . '</li>';
}
$leftmenu .= '</ul>';

ob_start();
?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    var sidenav = document.querySelector(".side-nav .vmenu");
    if (!sidenav) return;
    var mondayMenuDiv = document.createElement("div");
    mondayMenuDiv.innerHTML = REPLACEMENU;
    sidenav.insertBefore(mondayMenuDiv, sidenav.firstChild);
    var csrfToken = REPLACETOKEN;

    mondayMenuDiv.querySelectorAll('.workspace-item').forEach(function(item) {
        item.addEventListener('click', function() {
            var id = item.dataset.id;
            var label = item.textContent;

            document.getElementById('main-content').innerHTML = `
                <div style="display: flex; align-items: center; gap: 10px;">
                    <h2 id="workspace-label" style="margin:0;">${label}</h2>
                    <button id="rename-btn" title="Renommer" style="padding:2px 6px;">✎</button>
                    <button id="delete-btn" title="Supprimer" style="padding:2px 6px;">✖</button>
                </div>
                <button id="add-group-btn" style="margin: 1rem 0;">+ Ajouter un groupe</button>
                <div id="group-list"></div>
            `;

            let renameMode = false;
            function triggerRenameSubmit() {
                const newLabel = document.getElementById('rename-input').value.trim();
                if (!newLabel) return alert("Le nom ne peut pas être vide.");
                var formData = new FormData();
                formData.append('rename_workspace_id', id);
                formData.append('rename_workspace_label', newLabel);
                formData.append('token', csrfToken);
                fetch('', { method: 'POST', body: formData }).then(() => location.reload());
            }

            document.getElementById('rename-btn').addEventListener('click', function() {
                if (!renameMode) {
                    const currentLabel = document.getElementById('workspace-label').textContent;
                    document.getElementById('workspace-label').outerHTML = `<input type="text" id="rename-input" value="${currentLabel}" style="font-size: 20px; font-weight: bold;">`;
                    document.getElementById('rename-input').focus();
                    document.getElementById('rename-input').addEventListener('keydown', function(e) {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            triggerRenameSubmit();
                        }
                    });
                    renameMode = true;
                } else triggerRenameSubmit();
            });

            document.getElementById('delete-btn').addEventListener('click', function() {
                if (!confirm("Supprimer cet espace ?")) return;
                var formData = new FormData();
                formData.append('delete_workspace_id', id);
                formData.append('token', csrfToken);
                fetch('', { method: 'POST', body: formData }).then(() => location.reload());
            });

            function loadGroups(workspaceId) {
                fetch(`get_groups.php?wid=${workspaceId}`)
                    .then(res => res.json())
                    .then(data => {
                        const groupList = document.getElementById('group-list');
                        groupList.innerHTML = '';
                        data.forEach(group => {
                            const g = document.createElement('div');
                            g.classList.add('group');
                            g.style.border = '1px solid #ccc';
                            g.style.borderRadius = '5px';
                            g.style.margin = '10px 0';
                            g.innerHTML = `
                                <div class="group-header" style="display:flex; justify-content:space-between; padding:8px; background:#f3f3f3;">
                                    <div style="display:flex; align-items:center; gap: 8px;">
                                        <span class="group-toggle" style="cursor:pointer;">▼</span>
                                        <span class="group-label" data-id="${group.id}" style="font-weight:bold;">${group.label}</span>
                                    </div>
                                    <div>
                                        <button class="rename-group">✎</button>
                                        <button class="delete-group">✖</button>
                                    </div>
                                </div>
                                <div class="group-body" style="padding:10px;">(contenu futur)</div>
                            `;
                            const body = g.querySelector('.group-body');
                            g.querySelector('.group-toggle').addEventListener('click', () => {
                                body.style.display = body.style.display === 'none' ? 'block' : 'none';
                                g.querySelector('.group-toggle').textContent = body.style.display === 'none' ? '►' : '▼';
                            });
                            g.querySelector('.rename-group').addEventListener('click', () => {
                                const labelSpan = g.querySelector('.group-label');
                                const newName = prompt("Nouveau nom du groupe :", labelSpan.textContent);
                                if (!newName) return;
                                const formData = new FormData();
                                formData.append('rename_group_id', group.id);
                                formData.append('group_label', newName);
                                formData.append('token', csrfToken);
                                fetch('', { method: 'POST', body: formData }).then(() => loadGroups(id));
                            });
                            g.querySelector('.delete-group').addEventListener('click', () => {
                                if (!confirm("Supprimer ce groupe ?")) return;
                                const formData = new FormData();
                                formData.append('delete_group_id', group.id);
                                formData.append('token', csrfToken);
                                fetch('', { method: 'POST', body: formData }).then(() => loadGroups(id));
                            });
                            groupList.appendChild(g);
                        });
                    });
            }

            document.getElementById('add-group-btn').addEventListener('click', function() {
                const label = prompt("Nom du groupe :");
                if (!label) return;
                const formData = new FormData();
                formData.append('add_group_workspace_id', id);
                formData.append('group_label', label);
                formData.append('token', csrfToken);
                fetch('', { method: 'POST', body: formData }).then(() => loadGroups(id));
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

print <<<EOT
<link rel="stylesheet" href="custom/monday/styles.css">
<div class="workspace-container">
    <div class="main-content" id="main-content"></div>
</div>
EOT;

print '<style>#blockvmenusearch { display: none !important; }</style>';

llxFooter();
$db->close();
?>
