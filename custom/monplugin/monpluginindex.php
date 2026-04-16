<?php

$res = 0;
if (!$res && file_exists(dirname(__FILE__).'/../../main.inc.php')) {
	require_once dirname(__FILE__).'/../../main.inc.php'; #chargemeent  de fichier  qui initialise  dolibarr
	$res = 1;
}

if (!$res) {
	die('Cannot find Dolibarr');
}

if (!$user->id) {     #user nest  pas connecté
	accessforbidden();
}

$todosByStatus = array (
			0 => array(),  #chaque todo est une liste pour stocker lensemble des todos aux il pourra contenir 
			1 => array(), 
			3 => array(),
			2 => array() 
);                            
if (!$user->rights->monplugin->read) {
    accessforbidden();
}


$action = GETPOST('action', 'alpha');  // récupère 'setstatus'
$id     = GETPOSTINT('id');        // récupère 5
$status = GETPOSTINT('status');    // récupère 2

if ($action == 'setstatus' && $id > 0) {

	if ($status >= 0 && $status <= 3) {
		$sql = "UPDATE ".MAIN_DB_PREFIX."monplugin_todo";
		$sql .= " SET status = ".((int) $status);
		$sql .= " WHERE rowid = ".((int) $id);

		$resql = $db->query($sql);

		if ($resql) {
			header('Content-Type: application/json');
			echo json_encode(array('success' => true));
			exit;
		} else {
			header('Content-Type: application/json');
			echo json_encode(array('success' => false, 'error' => $db->lasterror()));
			exit;
		}
	}

	header('Content-Type: application/json');
	echo json_encode(array('success' => false, 'error' => 'Status invalide'));
	exit;
}
	

#prefix  MAIN_DB_PREFIX pour les tables de données dolibarr
$sql = "SELECT rowid, titre, description, status, date_creation";
$sql .= " FROM ".MAIN_DB_PREFIX."monplugin_todo";
$sql .= " ORDER BY rowid DESC";
# cette  requette  recupere  toutes la  table


$resql = $db->query($sql);
#resql recuppere  toutes  les lignes de  la table

if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$status = (int) $obj->status;
		if (!isset($todosByStatus[$status])) {                                                                    
			$status = 0;
		}
		$todosByStatus[$status][] = $obj;
	}
} else {
	setEventMessages($db->lasterror(), null, 'errors');
} 
###############################################################################

// quand on commence à glisser la carte














llxHeader('', 'La liste des taches');

print '<style>
.monplugin-layout {
	display: flex;
	flex-direction: row;
	gap: 10px;
	padding-top: 15px; 
	
}

	.monplugin-layout > div {
		flex: 1;
		min-height: 220px;
	}
	</style>';
if ($user->rights->monplugin->create) {
print '<div>';
print '<h1>Gestion des taches</h1>';
print '<p>Pour ajouter une  tache : </p>';
print '<p><a class="butAction" href="'.DOL_URL_ROOT.'/custom/monplugin/todo.php">Ajouter</a></p>';
print '</div>';
} 

print '<h1 style= ""> Mes taches actuelles</h1>';
print '<div class="monplugin-layout">';
#a faire                                                                                                                                                         
print '<div class = "colonne" data-status="0" style = "border-radius: 10px; padding: 10px; background: #FFB6C1 ; text-align: center;">';
print '<h3> a faire</h3>';
foreach ($todosByStatus[0] as $todo) {
	print '<div class="carte" draggable="true" data-id="'.((int) $todo->rowid).'" style="border-radius: 8px; border: 1px solid #868383; margin-bottom: 10px;padding:10px; text-align: left;">';
	print '<strong>'.dol_escape_htmltag($todo->titre).'</strong>';
	print '<p>'.dol_escape_htmltag($todo->description).'</p>';
	print '<small>Créé le : '.dol_print_date($db->jdate($todo->date_creation), 'dayhour').'</small>'; # quand  on clique sur  terminé   cette  update  est envoyé monpluginindex.php?action=setstatus&id=5&status=2 ce qui permettera  de  mettre  a  joure  la  bdd  avec update  
# on affiche tous  les  elements  de  tab dan  status  egale  a  zero
	print '</div>';
}
print '</div>';
#en cours
print '<div class = "colonne" data-status="1" style = "border-radius: 10px;padding: 10px; background: #FAA18F; text-align: center;">';
print '<h3> en cours</h3>';
foreach ($todosByStatus[1] as $todo) {
	print '<div class="carte" draggable="true" data-id="'.((int) $todo->rowid).'"  style=" border-radius: 8px; border: 1px solid #868383; margin-bottom: 10px;padding:10px; text-align: left;">';
	print '<strong>'.dol_escape_htmltag($todo->titre).'</strong>';
	print '<p>'.dol_escape_htmltag($todo->description).'</p>';
	print '<small>Créé le : '.dol_print_date($db->jdate($todo->date_creation), 'dayhour').'</small>';
	print '</div>';
}

print '</div>';
#terminé
print '<div class = "colonne" data-status="2" style = "border-radius: 10px;padding: 10px; background: #FCC6BB; text-align: center;">';
print '<h3> terminé</h3>';
foreach ($todosByStatus[2] as $todo) {
	print '<div class="carte" draggable="true" data-id="'.((int) $todo->rowid).'" style=" border-radius: 8px; border: 1px solid #868383; margin-bottom: 10px;padding:10px; text-align: left;">';
	print '<strong>'.dol_escape_htmltag($todo->titre).'</strong>';
	print '<p>'.dol_escape_htmltag($todo->description).'</p>';
	print '<small>Créé le : '.dol_print_date($db->jdate($todo->date_creation), 'dayhour').'</small>';
	print '</div>';
}

print '</div>';
#validé
print '<div class = "colonne" data-status="3" style = "border-radius: 10px;padding: 10px; background: #FEEBE7; text-align: center;">';
print '<h3> validé</h3>';
foreach ($todosByStatus[3] as $todo) {
	print '<div class="carte" draggable="true" data-id="'.((int) $todo->rowid).'" style="border-radius: 8px; border: 1px solid #868383; margin-bottom: 10px;padding:10px; text-align: left;">';
	print '<strong>'.dol_escape_htmltag($todo->titre).'</strong>';
	print '<p>'.dol_escape_htmltag($todo->description).'</p>';
	print '<small>Créé le : '.dol_print_date($db->jdate($todo->date_creation), 'dayhour').'</small>';
	print '</div>';
}
print '</div>';
print '</div>';

if ($user->rights->monplugin->write) {
		
print '<script>
let draggedTodoId = null;

document.querySelectorAll(".carte").forEach(function(carte) {
	carte.addEventListener("dragstart", function() {
		draggedTodoId = this.dataset.id;
	});
});

document.querySelectorAll(".colonne").forEach(function(colonne) {
	colonne.addEventListener("dragover", function(e) {
		e.preventDefault();
	});

	colonne.addEventListener("drop", function(e) {
		e.preventDefault();

		let status = this.dataset.status;

		if (!draggedTodoId || status === undefined) {
			return;
		}

			fetch("'.$_SERVER['PHP_SELF'].'?action=setstatus&token='.newToken().'&id=" + encodeURIComponent(draggedTodoId) + "&status=" + encodeURIComponent(status))
				.then(function(response) {
					return response.json();
				})
				.then(function(data) {
					if (data.success) {
						window.location.reload();
					} else {
						alert(data.error || "Erreur pendant le déplacement");
					}
				})
				.catch(function(error) {
					alert("Erreur JavaScript/backend: " + error);
				});
	});
});
</script>';
}
llxFooter();
