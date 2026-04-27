<?php
$API_URL = getenv('DOLIBARR_API_URL');
$API_KEY = getenv('DOLIBARR_API_KEY');
$CSV_FILE = __DIR__ . "/tiers_contacts.csv";

function apiGet($url, $apiKey) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["DOLAPIKEY: $apiKey"]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

$tiers = apiGet("$API_URL/thirdparties?limit=0", $API_KEY);

#http://localhost:8080/api/index.php/contacts?thirdparty_ids=1814

// Ouvrir le CSV + écrire les en-têtes
$fp = fopen($CSV_FILE, 'w');
if ($fp === false) {
    throw new RuntimeException("Impossible de créer le fichier CSV: " . $CSV_FILE);
}
 
fputcsv($fp, ['tier_id', 'tier_name', 'firstname', 'lastname', 'phone_pro', 'phone_mobile', 'email', 'poste'], ';');

// Boucle tiers
foreach ($tiers as $tier) {
    $tierId = $tier['id'] ?? '';
    $tierName = $tier['name'] ?? '';

    // Paramètre API correct pour contacts d'un tiers
    $contacts = apiGet("$API_URL/contacts?thirdparty_ids={$tierId}&limit=0", $API_KEY);

    if (empty($contacts)) {
        // Ligne vide si aucun contact
        fputcsv($fp, [$tierId, $tierName, '', '', '', '', '', ''], ';');
        continue;
    }

    // 1 ligne CSV par contact
    foreach ($contacts as $c) {
        fputcsv($fp, [
            $tierId,
            $tierName,
            $c['firstname'] ?? '',
            $c['lastname'] ?? '',
            $c['phone_pro'] ?? '',
            $c['phone_mobile'] ?? '',
            $c['email'] ?? '',
            $c['poste'] ?? ''
        ], ';');
    }
}

// Fin export
fclose($fp);
echo "Export CSV terminé: $CSV_FILE\n";
?>



