<?php
/* Copyright (C) 2001-2005  Rodolphe Quiedeville    <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012  Regis Houssin           <regis.houssin@inodbox.com>
 * Copyright (C) 2015       Jean-François Ferry     <jfefe@aternatik.fr>
 * Copyright (C) 2024       Frédéric France         <frederic.france@free.fr>
 * Copyright (C) 2025		SuperAdmin
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file       kanban/kanbanindex.php
 *	\ingroup    kanban
 *	\brief      Home page of kanban top menu
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

// Load translation files required by the page
$langs->loadLangs(array("kanban@kanban"));

$action = GETPOST('action', 'aZ09');

$now = dol_now();
$max = getDolGlobalInt('MAIN_SIZE_SHORTLIST_LIMIT', 5);

// Security check - Protection if external user
$socid = GETPOSTINT('socid');
if (!empty($user->socid) && $user->socid > 0) {
	$action = '';
	$socid = $user->socid;
}

// Initialize a technical object to manage hooks. Note that conf->hooks_modules contains array
//$hookmanager->initHooks(array($object->element.'index'));

// Security check (enable the most restrictive one)
//if ($user->socid > 0) accessforbidden();
//if ($user->socid > 0) $socid = $user->socid;
//if (!isModEnabled('kanban')) {
//	accessforbidden('Module not enabled');
//}
//if (! $user->hasRight('kanban', 'myobject', 'read')) {
//	accessforbidden();
//}
//restrictedArea($user, 'kanban', 0, 'kanban_myobject', 'myobject', '', 'rowid');
//if (empty($user->admin)) {
//	accessforbidden('Must be admin');
//}


/*
 * Actions
 */

// None


/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);

llxHeader("", $langs->trans("KanbanArea"), '', '', 0, 0, '', '', '', 'mod-kanban page-index');
print '<script>var dolibarr_token = "'.newToken().'";</script>';

//print load_fiche_titre($langs->trans("KanbanArea"), '', 'kanban.png@kanban');

print '<div class="fichecenter"><div class="fichethirdleft">';


/* BEGIN MODULEBUILDER DRAFT MYOBJECT
// Draft MyObject
if (isModEnabled('kanban') && $user->hasRight('kanban', 'read')) {
	$langs->load("orders");

	$sql = "SELECT c.rowid, c.ref, c.ref_client, c.total_ht, c.tva as total_tva, c.total_ttc, s.rowid as socid, s.nom as name, s.client, s.canvas";
	$sql.= ", s.code_client";
	$sql.= " FROM ".MAIN_DB_PREFIX."commande as c";
	$sql.= ", ".MAIN_DB_PREFIX."societe as s";
	$sql.= " WHERE c.fk_soc = s.rowid";
	$sql.= " AND c.fk_statut = 0";
	$sql.= " AND c.entity IN (".getEntity('commande').")";
	if ($socid)	$sql.= " AND c.fk_soc = ".((int) $socid);

	$resql = $db->query($sql);
	if ($resql)
	{
		$total = 0;
		$num = $db->num_rows($resql);

		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<th colspan="3">'.$langs->trans("DraftMyObjects").($num?'<span class="badge marginleftonlyshort">'.$num.'</span>':'').'</th></tr>';

		$var = true;
		if ($num > 0)
		{
			$i = 0;
			while ($i < $num)
			{

				$obj = $db->fetch_object($resql);
				print '<tr class="oddeven"><td class="nowrap">';

				$myobjectstatic->id=$obj->rowid;
				$myobjectstatic->ref=$obj->ref;
				$myobjectstatic->ref_client=$obj->ref_client;
				$myobjectstatic->total_ht = $obj->total_ht;
				$myobjectstatic->total_tva = $obj->total_tva;
				$myobjectstatic->total_ttc = $obj->total_ttc;

				print $myobjectstatic->getNomUrl(1);
				print '</td>';
				print '<td class="nowrap">';
				print '</td>';
				print '<td class="right" class="nowrap">'.price($obj->total_ttc).'</td></tr>';
				$i++;
				$total += $obj->total_ttc;
			}
			if ($total>0)
			{

				print '<tr class="liste_total"><td>'.$langs->trans("Total").'</td><td colspan="2" class="right">'.price($total)."</td></tr>";
			}
		}
		else
		{

			print '<tr class="oddeven"><td colspan="3" class="opacitymedium">'.$langs->trans("NoOrder").'</td></tr>';
		}
		print "</table><br>";

		$db->free($resql);
	}
	else
	{
		dol_print_error($db);
	}
}
END MODULEBUILDER DRAFT MYOBJECT */


print '</div><div class="fichetwothirdright">';


/* BEGIN MODULEBUILDER LASTMODIFIED MYOBJECT
// Last modified myobject
if (isModEnabled('kanban') && $user->hasRight('kanban', 'read')) {
	$sql = "SELECT s.rowid, s.ref, s.label, s.date_creation, s.tms";
	$sql.= " FROM ".MAIN_DB_PREFIX."kanban_myobject as s";
	$sql.= " WHERE s.entity IN (".getEntity($myobjectstatic->element).")";
	//if ($socid)	$sql.= " AND s.rowid = $socid";
	$sql .= " ORDER BY s.tms DESC";
	$sql .= $db->plimit($max, 0);

	$resql = $db->query($sql);
	if ($resql)
	{
		$num = $db->num_rows($resql);
		$i = 0;

		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<th colspan="2">';
		print $langs->trans("BoxTitleLatestModifiedMyObjects", $max);
		print '</th>';
		print '<th class="right">'.$langs->trans("DateModificationShort").'</th>';
		print '</tr>';
		if ($num)
		{
			while ($i < $num)
			{
				$objp = $db->fetch_object($resql);

				$myobjectstatic->id=$objp->rowid;
				$myobjectstatic->ref=$objp->ref;
				$myobjectstatic->label=$objp->label;
				$myobjectstatic->status = $objp->status;

				print '<tr class="oddeven">';
				print '<td class="nowrap">'.$myobjectstatic->getNomUrl(1).'</td>';
				print '<td class="right nowrap">';
				print "</td>";
				print '<td class="right nowrap">'.dol_print_date($db->jdate($objp->tms), 'day')."</td>";
				print '</tr>';
				$i++;
			}

			$db->free($resql);
		} else {
			print '<tr class="oddeven"><td colspan="3" class="opacitymedium">'.$langs->trans("None").'</td></tr>';
		}
		print "</table><br>";
	}
}
*/

print '</div></div>';

print '<h2>Tableau de suivi des tâches</h2>';

// --- Calculs pour avancement global et projets ---
$project_cards = [];
$total_progress = 0;
$total_projects = 0;
$total_tickets = 0;

$sql_proj = "SELECT p.rowid, p.title
    FROM ".MAIN_DB_PREFIX."projet p
    WHERE p.entity = ".$conf->entity;
$res_proj = $db->query($sql_proj);

if ($res_proj) {
    while ($proj = $db->fetch_object($res_proj)) {
        // Moyenne d'avancement des tâches du projet
        $sql_tasks = "SELECT COUNT(*) as nb, AVG(progress) as avg_progress,
                 SUM(fk_statut != 2) as nb_en_cours
              FROM ".MAIN_DB_PREFIX."projet_task
              WHERE fk_projet = ".$proj->rowid." AND entity = ".$conf->entity;
        $res_tasks = $db->query($sql_tasks);
        $avg = 0; $nb = 0; $nb_en_cours = 0;
        if ($res_tasks && $row = $db->fetch_object($res_tasks)) {
            $avg = round($row->avg_progress, 1);
            $nb = (int)$row->nb;
            $nb_en_cours = (int)$row->nb_en_cours;
        }
        $project_cards[] = [
            'id' => $proj->rowid,
            'title' => $proj->title,
            'progress' => $avg,
            'nb' => $nb,
            'nb_en_cours' => $nb_en_cours
        ];
        $total_progress += $avg;
        $total_projects++;
        $total_tickets += $nb;
    }
}
$global_progress = $total_projects > 0 ? round($total_progress / $total_projects, 1) : 0;

// Statuts des tâches Dolibarr : 0 = À faire, 1 = En cours, 2 = Terminé
$status_labels = [
    0 => 'À faire',
    1 => 'En cours',
    2 => 'Terminé',
    3 => 'Archive'
];

$project_id = GETPOST('project_id','int');
if ($project_id) {
    foreach ($project_cards as $p) {
        if ($p['id'] == $project_id) {
            print '
            <style>
            #project-progress-bar {
                background: #f8f9fa;
                border-radius: 8px;
                padding: 18px 18px 10px 18px;
                margin-bottom: 28px;
                box-shadow: 0 1px 4px #eee;
                max-width: 600px;
            }
            .project-card-bar {
                background: #eee;
                border-radius: 8px;
                height: 22px;
                overflow: hidden;
                margin-top: 5px;
                margin-bottom: 8px;
            }
            .project-card-bar-inner {
                background: #007bff;
                height: 100%;
                transition: width 0.5s;
                border-radius: 8px;
            }
            </style>
            <div id="project-progress-bar">
                <b>'.$p['title'].' : '.$p['progress'].'%</b>
                <div class="project-card-bar">
                    <div class="project-card-bar-inner" style="width:'.$p['progress'].'%"></div>
                </div>
            </div>
            ';
            break;
        }
    }
}

// Récupérer les tâches de projet
$sql = "SELECT t.rowid, t.label, t.fk_statut, t.progress, t.fk_projet, 
        extrafields.priorite,
        extrafields.user_assign,
        u.firstname as assigned_firstname, u.lastname as assigned_lastname
        FROM ".MAIN_DB_PREFIX."projet_task t
        LEFT JOIN ".MAIN_DB_PREFIX."projet_task_extrafields extrafields ON t.rowid = extrafields.fk_object
        LEFT JOIN ".MAIN_DB_PREFIX."user u ON extrafields.user_assign = u.rowid
        WHERE t.entity = ".$conf->entity;
$project_id = GETPOST('project_id','int');
if ($project_id) {
    $sql .= " AND t.fk_projet = ".((int)$project_id);
}
$user_id = GETPOST('user_id','int');
if ($user_id) {
    $sql .= " AND extrafields.user_assign = ".((int)$user_id);
}
$resql = $db->query($sql);

$tasks = [0 => [], 1 => [], 2 => [], 3 => []];
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $statut = (isset($tasks[$obj->fk_statut]) ? $obj->fk_statut : 0);
        $tasks[$statut][] = $obj;
    }
}

print '<div id="kanban-board">';
foreach ($status_labels as $status => $label) {
    print '<div class="kanban-column" data-status="'.$status.'">';
    print '<div class="kanban-column-title">'.$label.'</div>';
    if (!empty($tasks[$status])) {
        foreach ($tasks[$status] as $task) {
            $taskUrl = DOL_URL_ROOT.'/projet/tasks/task.php?id='.$task->rowid;
            $assigned = ($task->assigned_firstname || $task->assigned_lastname)
                ? dol_escape_htmltag(trim($task->assigned_firstname.' '.$task->assigned_lastname))
                : 'Non assigné';
            $progress = is_numeric($task->progress) ? intval($task->progress) : 0;
            $priorite = isset($task->priorite) ? dol_escape_htmltag($task->priorite) : '';

            print '<div class="kanban-card" draggable="true" data-id="'.$task->rowid.'">';
            print '<a class="kanban-task-label" href="'.$taskUrl.'" target="_blank" style="text-decoration:none;color:inherit;">'.dol_escape_htmltag($task->label).'</a>';
            print '<span class="kanban-task-user">👤 '.$assigned.'</span>';
            print '<span class="kanban-task-progress">⏳ '.$progress.'%</span>';
            if ($priorite !== '') {
                print '<span class="kanban-task-priority">⭐ Priorité : '.$priorite.'</span>';
            }
            print '</div>';
        }
    } else {
        print '<div class="kanban-card" style="opacity:0.5;">Aucune tâche</div>';
    }
    print '</div>';
}
print '</div>';

print <<<EOT
<style>
#kanban-board {
    display: flex;
    gap: 20px;
    margin: 30px 0;
}
.kanban-column {
    border: 1px solid #ddd;
    border-radius: 6px;
    width: 300px;
    min-height: 300px;
    padding: 10px;
    display: flex;
    flex-direction: column;
}
.kanban-column[data-status="0"] { background: #f8f8f8;	}      /* À faire : gris */
.kanban-column[data-status="1"] { background: #fff3cd; }      /* En cours : orange clair */
.kanban-column[data-status="2"] { background: #d4edda; }      /* Terminé : vert clair */
.kanban-column[data-status="3"] { background: #f8d7da; }      /* Archive : rouge clair */

.kanban-column-title {
    font-weight: bold;
    margin-bottom: 10px;
}
.kanban-card {
    background: #fff;
    border: 1px solid #bbb;
    border-radius: 4px;
    padding: 8px;
    margin-bottom: 10px;
    box-shadow: 1px 1px 2px #eee;
    cursor: grab;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.kanban-card .kanban-task-label {
    font-weight: bold;
}
.kanban-card .kanban-task-user {
    font-size: 0.9em;
    color: #555;
}
.kanban-card .kanban-task-progress {
    font-size: 0.9em;
    color: #007bff;
}
.kanban-card .kanban-task-priority {
    font-size: 0.9em;
    color: #e67e22;
    font-weight: bold;
}
.kanban-add-btn {
    margin-top: 10px;
    background: #007bff;
    color: #fff;
    border: none;
    padding: 6px 10px;
    border-radius: 4px;
    cursor: pointer;
}
.kanban-delete-btn {
    background: #dc3545;
    color: #fff;
    border: none;
    border-radius: 3px;
    padding: 2px 6px;
    margin-left: 5px;
    cursor: pointer;
}
.kanban-edit-btn {
    background: #ffc107;
    color: #333;
    border: none;
    border-radius: 3px;
    padding: 2px 6px;
    margin-left: 5px;
    cursor: pointer;
}
.project-card-link:hover .project-card {
    box-shadow: 0 2px 8px #007bff33;
    border-color: #007bff;
    background: #f0f8ff;
}
</style>

<script>
let draggedCard = null;

// Drag & drop
document.querySelectorAll('.kanban-card').forEach(card => {
    card.addEventListener('dragstart', e => {
        draggedCard = card;
        setTimeout(() => card.style.display = "none", 0);
    });
    card.addEventListener('dragend', e => {
        draggedCard = null;
        card.style.display = "flex";
    });
});
document.querySelectorAll('.kanban-column').forEach(col => {
    col.addEventListener('dragover', e => {
        e.preventDefault();
    });
    col.addEventListener('drop', e => {
        if (draggedCard) {
            // Retire "Aucune tâche" s'il existe
            let empty = col.querySelector('.kanban-card[style*="opacity:0.5"]');
            if (empty) empty.remove();
            col.appendChild(draggedCard);

            // Sauvegarde le changement de statut
            let taskId = draggedCard.getAttribute('data-id');
            let newStatus = col.getAttribute('data-status');
            console.log('taskId:', taskId, 'newStatus:', newStatus, 'token:', window.dolibarr_token); // AJOUTE CETTE LIGNE
            fetch('/custom/kanban/save_kanban_status.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'id=' + encodeURIComponent(taskId) +
                      '&status=' + encodeURIComponent(newStatus) +
                      '&token=' + encodeURIComponent(window.dolibarr_token)
            });
        }
    });
});

// Ajouter une carte
document.querySelectorAll('.kanban-add-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const col = btn.parentElement;
        const newCard = document.createElement('div');
        newCard.className = 'kanban-card';
        newCard.draggable = true;
        newCard.innerHTML = 'Nouvelle tâche <button class="kanban-edit-btn">✏️</button><button class="kanban-delete-btn">🗑️</button>';
        col.insertBefore(newCard, btn);

        // Ajoute les events drag & drop et édition/suppression
        addCardEvents(newCard);
    });
});

// Editer/Supprimer une carte
function addCardEvents(card) {
    card.addEventListener('dragstart', e => {
        draggedCard = card;
        setTimeout(() => card.style.display = "none", 0);
    });
    card.addEventListener('dragend', e => {
        draggedCard = null;
        card.style.display = "flex";
    });

    card.querySelector('.kanban-delete-btn').addEventListener('click', () => {
        card.remove();
    });

    card.querySelector('.kanban-edit-btn').addEventListener('click', () => {
        const currentText = card.childNodes[0].textContent.trim();
        const input = document.createElement('input');
        input.type = 'text';
        input.value = currentText;
        card.insertBefore(input, card.firstChild);
        input.focus();
        input.onblur = () => {
            card.childNodes[1].textContent = input.value;
            input.remove();
        };
        input.onkeydown = e => {
            if (e.key === 'Enter') input.blur();
        };
        card.childNodes[1].textContent = '';
    });
}

// Initialiser les events sur les cartes existantes
document.querySelectorAll('.kanban-card').forEach(addCardEvents);

// Empêcher la propagation du clic sur les liens
document.querySelectorAll('.kanban-card a').forEach(link => {
    link.addEventListener('mousedown', e => e.stopPropagation());
});
</script>
EOT;

// Récupérer la liste des projets
$sql_projects = "SELECT rowid, title FROM ".MAIN_DB_PREFIX."projet WHERE entity = ".$conf->entity." ORDER BY title";
$resql_projects = $db->query($sql_projects);

// Récupérer la liste des utilisateurs
$sql_users = "SELECT rowid, login, firstname, lastname FROM ".MAIN_DB_PREFIX."user WHERE entity = ".$conf->entity." ORDER BY lastname, firstname";
$resql_users = $db->query($sql_users);

// Génère les options projets et utilisateurs côté PHP
$project_options = '<option value="">-- Tous les projets --</option>';
if ($resql_projects) {
    $pid = GETPOST('project_id','int');
    $resql_projects->data_seek(0);
    while ($proj = $resql_projects->fetch_object()) {
        $selected = ($pid == $proj->rowid) ? 'selected' : '';
        $project_options .= '<option value="'.$proj->rowid.'" '.$selected.'>'.dol_escape_htmltag($proj->title).'</option>';
    }
}

$user_options = '<option value="">-- Tous les utilisateurs --</option>';
if ($resql_users) {
    $uid = GETPOST('user_id','int');
    $resql_users->data_seek(0);
    while ($userobj = $resql_users->fetch_object()) {
        $uname = trim($userobj->firstname.' '.$userobj->lastname);
        $uname = $uname ? $uname : $userobj->login;
        $selected = ($uid == $userobj->rowid) ? 'selected' : '';
        $user_options .= '<option value="'.$userobj->rowid.'" '.$selected.'>'.dol_escape_htmltag($uname).'</option>';
    }
}

// JS pour insérer le formulaire dans la side-nav
print <<<EOT
<script>
document.addEventListener("DOMContentLoaded", function() {
    var sidenav = document.querySelector(".side-nav .vmenu");
    if (!sidenav) return;

    // Crée le bouton et la section cachée
    var globalBtnDiv = document.createElement("div");
    globalBtnDiv.innerHTML = `
        <button type="button" onclick="toggleGlobalProgress()" class="button button-small" style="width:100%;margin-bottom:8px;">
            <span class="fa fa-chart-bar" style="margin-right:6px;"></span>
            Avancement global des projets
        </button>
        <div id="global-progress-section" style="display:none;margin-top:10px;">
            <div style="margin-bottom:12px;">
                <b>Avancement global : {$global_progress}%</b>
                <div style="background:#eee;border-radius:8px;height:22px;overflow:hidden;margin-top:5px;">
                    <div style="background:#28a745;height:100%;width:{$global_progress}%;transition:width 0.5s;border-radius:8px;"></div>
                </div>
            </div>
            <!-- Cards projets -->
EOT;

// Affichage des cards projets
foreach ($project_cards as $p) {
    $projectUrl = DOL_URL_ROOT.'/projet/card.php?id='.$p['id'];
    print '<a href="'.$projectUrl.'" class="project-card-link" style="text-decoration:none;color:inherit;">';
    print '<div class="project-card">';
    print '<div class="project-card-title">'.dol_escape_htmltag($p['title']).'</div>';
    print '<div class="project-card-bar"><div class="project-card-bar-inner" style="width:'.$p['progress'].'%"></div></div>';
    print '<div style="font-size:0.95em;color:#555;">Avancement : <b>'.$p['progress'].'%</b></div>';
    print '<div style="font-size:0.95em;color:#007bff;">Tickets en cours : <b>'.$p['nb_en_cours'].'</b></div>';
    print '</div>';
    print '</a>';
}
print <<<EOT
        </div>
        <style>
        #global-progress-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 18px 18px 10px 18px;
            margin-bottom: 28px;
            box-shadow: 0 1px 4px #eee;
            max-width: 600px;
        }
        .project-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 6px;
            margin-bottom: 12px;
            padding: 12px 14px;
            box-shadow: 0 1px 2px #eee;
        }
        .project-card-title {
            font-weight: bold;
            margin-bottom: 6px;
        }
        .project-card-bar {
            background: #eee;
            border-radius: 8px;
            height: 14px;
            overflow: hidden;
            margin-top: 4px;
            margin-bottom: 6px;
        }
        .project-card-bar-inner {
            background: #007bff;
            height: 100%;
            transition: width 0.5s;
            border-radius: 8px;
        }
        </style>
    `;
    sidenav.insertBefore(globalBtnDiv, sidenav.firstChild);

    // Formulaire de filtres
    var filterForm = document.createElement("form");
    filterForm.method = "GET";
    filterForm.action = "";
    filterForm.style.margin = "20px 0";
    filterForm.innerHTML = `
        <div style="margin-bottom:12px;">
            <label for="project_filter"><b>Filtrer par projet :</b></label><br>
            <select name="project_id" id="project_filter" class="flat" style="width:100%;max-width:100%;" onchange="this.form.submit()">
                $project_options
            </select>
        </div>
        <div>
            <label for="user_filter"><b>Filtrer par utilisateur :</b></label><br>
            <select name="user_id" id="user_filter" class="flat" style="width:100%;max-width:100%;" onchange="this.form.submit()">
                $user_options
            </select>
        </div>
    `;
    sidenav.insertBefore(filterForm, globalBtnDiv.nextSibling);

    // Fonction JS pour afficher/masquer la section
    window.toggleGlobalProgress = function() {
        var sec = document.getElementById('global-progress-section');
        if (sec) sec.style.display = (sec.style.display === 'none' ? '' : 'none');
    }
});
</script>
EOT;

// Masque le bloc de recherche rapide
print '<style>#blockvmenusearch { display: none !important; }</style>';

// End of page
llxFooter();
$db->close();
