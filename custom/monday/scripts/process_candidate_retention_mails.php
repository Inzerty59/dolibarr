<?php

if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', 1);
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', 1);
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', 1);
}
if (!defined('NOREQUIREAJAX')) {
    define('NOREQUIREAJAX', 1);
}
if (!defined('NOLOGIN')) {
    define('NOLOGIN', 1);
}

$res = 0;
if (!$res && file_exists(__DIR__.'/../../../main.inc.php')) {
    $res = require __DIR__.'/../../../main.inc.php';
}
if (!$res && file_exists(__DIR__.'/../../../../main.inc.php')) {
    $res = require __DIR__.'/../../../../main.inc.php';
}
if (!$res) {
    fwrite(STDERR, "Include of main fails\n");
    exit(1);
}

require_once __DIR__.'/../class/candidateretentionmailservice.class.php';

$limit = 100;
$taskId = 0;
$dryRun = false;
foreach ($argv as $arg) {
    if (preg_match('/^--limit=(\d+)$/', $arg, $matches)) {
        $limit = max(1, min(500, (int) $matches[1]));
    } elseif (preg_match('/^--task-id=(\d+)$/', $arg, $matches)) {
        $taskId = (int) $matches[1];
    } elseif ($arg === '--dry-run') {
        $dryRun = true;
    }
}

$service = new CandidateRetentionMailService($db);
$result = $dryRun
    ? ['dry_run' => true, 'candidates' => $service->previewDueCandidates($limit, $taskId)]
    : $service->processDueCandidatesForTask($limit, $taskId, true);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
exit(empty($result['errors']) ? 0 : 1);
