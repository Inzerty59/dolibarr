<?php
// filepath: /home/florent/dev/dolibarr/custom/kanban/save_kanban_status.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Affiche les erreurs fatales même si le script plante
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error) {
        echo "Fatal error: ";
        print_r($error);
    }
});

require_once __DIR__.'/../../main.inc.php';

header('Content-Type: text/plain; charset=utf-8');

// Récupération des données POST (compatibilité avec certains serveurs)
$data = $_POST;
if (empty($data)) {
    parse_str(file_get_contents('php://input'), $data);
}

// Vérification des champs attendus
$id = isset($data['id']) ? (int)$data['id'] : 0;
$status = isset($data['status']) ? (int)$data['status'] : -1;
$token = isset($data['token']) ? $data['token'] : '';

if (!$id || $status < 0 || !$token) {
    http_response_code(400);
    echo "Paramètres manquants";
    exit;
}

// Vérification du token Dolibarr
if (!function_exists('dol_check_token')) {
    require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';
}
if (function_exists('dol_check_token')) {
    $token_function = 'dol_check_token';
} elseif (function_exists('checkToken')) {
    $token_function = 'checkToken';
} else {
    echo "Aucune fonction de vérification de token CSRF trouvée.";
    exit;
}

if (!$token_function($token)) {
    http_response_code(403);
    echo "Token CSRF invalide";
    exit;
}

// Vérification de la config Dolibarr
if (!isset($conf->entity)) {
    echo "Erreur : \$conf->entity non défini\n";
    exit;
}
if (!isset($db) || !isset($conf)) {
    echo "Dolibarr non initialisé";
    exit;
}

// Mise à jour du statut de la tâche en base
$sql = "UPDATE ".MAIN_DB_PREFIX."projet_task SET fk_statut = ".((int)$status)." WHERE rowid = ".((int)$id)." AND entity = ".$conf->entity;
$res = $db->query($sql);

if ($res) {
    echo "OK";
} else {
    http_response_code(500);
    echo "Erreur SQL : ".$db->lasterror()."\n";
    echo "Requête : $sql\n";
}
