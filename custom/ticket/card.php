<?php

$res = 0;
if (!$res && file_exists(dirname(__FILE__) . '/../../main.inc.php')) {
	require_once dirname(__FILE__) . '/../../main.inc.php';
	$res = 1;
}

$forced_project_id = 0;

if (!empty($conf->tickets->enabled)) {
	$action = GETPOST('action', 'alpha');
	$projectid = GETPOST('projectid', 'int');
	
	if ($action === 'create' && empty($projectid)) {
		header('Location: '.DOL_URL_ROOT.'/custom/tickets/select_project.php', true, 302);
		exit;
	}
	
	if ($action === 'create' && !empty($projectid)) {
		$_SESSION['ticket_forced_project'] = $projectid;
		$forced_project_id = $projectid;
	}
	
	if (!empty($_SESSION['ticket_forced_project'])) {
		$forced_project_id = (int)$_SESSION['ticket_forced_project'];
		$_GET['projectid'] = $forced_project_id;
		$_REQUEST['projectid'] = $forced_project_id;
		$_POST['projectid'] = $forced_project_id;
		$projectid = $forced_project_id;
	}
}

$GLOBALS['forced_project_id'] = $forced_project_id;

if (!empty($conf->tickets->enabled) && !empty($forced_project_id)) {
	require_once dirname(__FILE__) . '/../tickets/force_project_on_submission.php';
	
	echo '<style type="text/css">
		select[name="projectid"],
		#select2-projectid,
		.select2-container[aria-labelledby*="projectid"],
		.form-group:has(select[name="projectid"]),
		tr:has(select[name="projectid"]),
		tr:has(input[name="projectid"]) {
			display: none !important;
		}
	</style>';
	
	echo '<script type="text/javascript">
	(function() {
		var forcedId = ' . (int)$forced_project_id . ';
		
		// Chercher et forcer le select
		var selectEl = document.querySelector("select[name=\"projectid\"]");
		if (selectEl) {
			selectEl.value = forcedId;
			selectEl.disabled = true;
		}
		
		// S\'assurer qu\'il y a un input hidden
		var forms = document.querySelectorAll("form");
		forms.forEach(function(form) {
			if (!form.querySelector("input[name=\"projectid\"][type=\"hidden\"]")) {
				var hidden = document.createElement("input");
				hidden.type = "hidden";
				hidden.name = "projectid";
				hidden.value = forcedId;
				form.appendChild(hidden);
			}
		});
	})();
	</script>';
}

require_once DOL_DOCUMENT_ROOT.'/ticket/card.php';

// Nettoyage session
if (!empty($conf->tickets->enabled) && !empty($_SESSION['ticket_forced_project'])) {
	unset($_SESSION['ticket_forced_project']);
}

