<?php

$res = 0;
if (!$res) {
	@set_include_path(dirname(__FILE__) . '/../../');
}
if (!$res) {
	@set_include_path(dirname(__FILE__) . '/../../../');
}
require_once 'main.inc.php';

if (!$user->id) {
	accessforbidden();
}

header('Location: '.DOL_URL_ROOT.'/custom/tickets/select_project.php');
exit;
