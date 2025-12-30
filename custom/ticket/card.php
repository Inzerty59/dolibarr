<?php
/**
 * Wrapper pour ticket/card.php qui verrouille le champ projet
 * Utilise une page intermédiaire avec injection JavaScript
 */

// Charger Dolibarr
require_once dirname(__FILE__) . '/../../main.inc.php';

$action = GETPOST('action', 'alpha');
$projectid = GETPOST('projectid', 'int');

// Si action create sans projet, rediriger vers la sélection
if ($action === 'create' && empty($projectid)) {
	header('Location: '.DOL_URL_ROOT.'/custom/tickets/select_project.php', true, 302);
	exit;
}

// Si action create avec projet, afficher la page native via redirection avec cookie
if ($action === 'create' && !empty($projectid)) {
	$_SESSION['ticket_forced_project'] = $projectid;
	
	// Récupérer les infos du projet
	$sql = "SELECT ref, title FROM ".MAIN_DB_PREFIX."projet WHERE rowid = ".intval($projectid);
	$resql = $db->query($sql);
	$project_ref = '';
	$project_title = '';
	if ($resql && $db->num_rows($resql) > 0) {
		$obj = $db->fetch_object($resql);
		$project_ref = addslashes($obj->ref);
		$project_title = addslashes($obj->title);
	}
	
	// Stocker dans un cookie pour que le script puisse le lire sur la page native
	setcookie('ticket_lock_project', json_encode([
		'id' => $projectid,
		'ref' => $project_ref,
		'title' => $project_title
	]), 0, '/');
	
	// Rediriger vers la page native - le script sera injecté via le module JS
	header('Location: '.DOL_URL_ROOT.'/ticket/card.php?action=create&projectid='.$projectid);
	exit;
}

// Pour les autres actions, rediriger vers le card.php natif
header('Location: '.DOL_URL_ROOT.'/ticket/card.php?'.http_build_query($_GET), true, 302);
exit;

