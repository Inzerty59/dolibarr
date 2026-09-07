<?php

/**
 * Shared access and formatting helpers for the support KPI dashboard.
 */

function tickets_support_kpi_user_can_read()
{
	global $user;

	if (empty($user) || empty($user->id)) {
		return false;
	}

	$canReadTickets = !empty($user->rights->ticket->read) || (method_exists($user, 'hasRight') && $user->hasRight('ticket', 'read'));
	$canReadProjects = method_exists($user, 'hasRight') && $user->hasRight('projet', 'lire');

	return $canReadTickets && $canReadProjects;
}

function tickets_support_kpi_require_access()
{
	if (!isModEnabled('ticket') || !isModEnabled('projet') || !tickets_support_kpi_user_can_read()) {
		accessforbidden();
	}
}

function tickets_support_kpi_json_response($payload, $status = 200)
{
	http_response_code((int) $status);
	header('Content-Type: application/json; charset=UTF-8');
	echo json_encode($payload);
	exit;
}

function tickets_support_kpi_check_token()
{
	if (empty($_GET['token']) || empty($_SESSION['newtoken']) || $_GET['token'] !== $_SESSION['newtoken']) {
		accessforbidden('CSRF token invalid');
	}
}

function tickets_support_kpi_csv_safe_value($value)
{
	$value = (string) $value;
	if ($value !== '' && preg_match('/^[=+\-@]/', $value)) {
		return "'".$value;
	}

	return $value;
}

function tickets_support_kpi_csv_put_row($handle, array $row)
{
	fputcsv($handle, array_map('tickets_support_kpi_csv_safe_value', $row), ';');
}
