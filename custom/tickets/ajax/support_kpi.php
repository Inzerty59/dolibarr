<?php

define('NOTOKENRENEWAL', 1);

$res = 0;
if (!$res && file_exists(dirname(__FILE__).'/../../../main.inc.php')) {
	require_once dirname(__FILE__).'/../../../main.inc.php';
	$res = 1;
}
if (!$res) {
	die('Cannot find Dolibarr');
}

require_once DOL_DOCUMENT_ROOT.'/custom/tickets/lib/support_kpi.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/tickets/class/supportkpiservice.class.php';

tickets_support_kpi_require_access();
tickets_support_kpi_check_token();

$langs->load('tickets@tickets');
$langs->load('ticket');
$langs->load('projects');

$service = new SupportKpiService($db, $conf, $langs);
$payload = $service->getDashboardData($user, array(
	'project_id' => GETPOST('project_id', 'int'),
	'assignee_id' => GETPOST('assignee_id', 'int'),
	'status' => GETPOST('status', 'alphanohtml'),
	'start_date' => GETPOST('start_date', 'alphanohtml'),
	'end_date' => GETPOST('end_date', 'alphanohtml'),
));

tickets_support_kpi_json_response($payload);
