<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$langs->load("mymodule@mymodule");

// Gestion du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['new_workspace'])) {
    // Vérification manuelle du token CSRF
    if (empty($_POST['token']) || $_POST['token'] !== $_SESSION['newtoken']) {
        accessforbidden('CSRF token invalid');
    }
    $new_workspace = dol_htmlentities($_POST['new_workspace'], ENT_QUOTES, 'UTF-8');
    $db->query("INSERT INTO llx_myworkspace (label) VALUES ('" . $db->escape($new_workspace) . "')");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Renommage d’un espace
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_workspace_id'], $_POST['rename_workspace_label'])) {
    if (empty($_POST['token']) || $_POST['token'] !== $_SESSION['newtoken']) {
        accessforbidden('CSRF token invalid');
    }
    $id = (int) $_POST['rename_workspace_id'];
    $label = $db->escape($_POST['rename_workspace_label']);
    $db->query("UPDATE llx_myworkspace SET label = '".$label."' WHERE rowid = ".$id);
    exit;
}

// Suppression d’un espace
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_workspace_id'])) {
    if (empty($_POST['token']) || $_POST['token'] !== $_SESSION['newtoken']) {
        accessforbidden('CSRF token invalid');
    }
    $id = (int) $_POST['delete_workspace_id'];
    $db->query("DELETE FROM llx_myworkspace WHERE rowid = ".$id);
    exit;
}


// Récupération des espaces
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
                    <h2 style="margin:0;">${label}</h2>
                    <button id="delete-btn" style="padding:2px 6px; color:red;">🗑️</button>
                </div>
            `;

            document.getElementById('delete-btn').addEventListener('click', function() {
                if (!confirm("Supprimer cet espace ?")) return;
                var formData = new FormData();
                formData.append('delete_workspace_id', id);
                formData.append('token', csrfToken);
                fetch('', {
                    method: 'POST',
                    body: formData
                }).then(() => location.reload());
            });
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
    <div class="main-content" id="main-content">
    </div>
</div>
EOT;

print '<style>#blockvmenusearch { display: none !important; }</style>';

llxFooter();
$db->close();
?>
