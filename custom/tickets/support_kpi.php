<?php

$res = 0;
if (!$res && file_exists(dirname(__FILE__).'/../../main.inc.php')) {
	require_once dirname(__FILE__).'/../../main.inc.php';
	$res = 1;
}
if (!$res) {
	die('Cannot find Dolibarr');
}

require_once DOL_DOCUMENT_ROOT.'/custom/tickets/lib/support_kpi.lib.php';

tickets_support_kpi_require_access();

$langs->load('tickets@tickets');
$langs->load('ticket');
$langs->load('projects');

$token = newToken();

llxHeader('', $langs->trans('SupportKpiTitle'), '', '', 0, 0, array('/custom/tickets/js/support_kpi.js'), array('/custom/monday/css/main.css'));

print load_fiche_titre($langs->trans('SupportKpiTitle'), '', 'ticket');
?>
<script>
window.supportKpiConfig = <?php echo json_encode(array(
	'endpoint' => DOL_URL_ROOT.'/custom/tickets/ajax/support_kpi.php',
	'exportEndpoint' => DOL_URL_ROOT.'/custom/tickets/export/support_kpi_csv.php',
	'token' => $token,
)); ?>;
</script>

<div class="kpi-page">
	<div class="kpi-header">
		<h2><?php echo dol_escape_htmltag($langs->trans('SupportKpiTitle')); ?></h2>
	</div>

	<div class="kpi-summary">
		<strong><?php echo dol_escape_htmltag($langs->trans('TicketProjects')); ?></strong>
		<span><?php echo dol_escape_htmltag($langs->trans('SupportKpiSubtitle')); ?></span>
	</div>

	<div class="kpi-filters">
		<label>
			<span><?php echo dol_escape_htmltag($langs->trans('Project')); ?></span>
			<select id="support-kpi-project">
				<option value=""><?php echo dol_escape_htmltag($langs->trans('AllProjects')); ?></option>
			</select>
		</label>
		<label>
			<span><?php echo dol_escape_htmltag($langs->trans('PeriodStart')); ?></span>
			<input type="date" id="support-kpi-start-date">
		</label>
		<label>
			<span><?php echo dol_escape_htmltag($langs->trans('PeriodEnd')); ?></span>
			<input type="date" id="support-kpi-end-date">
		</label>
		<label>
			<span><?php echo dol_escape_htmltag($langs->trans('Status')); ?></span>
			<select id="support-kpi-status">
				<option value=""><?php echo dol_escape_htmltag($langs->trans('AllStatuses')); ?></option>
			</select>
		</label>
		<label>
			<span><?php echo dol_escape_htmltag($langs->trans('AssignedTo')); ?></span>
			<select id="support-kpi-assignee">
				<option value=""><?php echo dol_escape_htmltag($langs->trans('AllAssignees')); ?></option>
			</select>
		</label>
		<div class="kpi-export-controls">
			<button id="support-kpi-apply" type="button"><?php echo dol_escape_htmltag($langs->trans('Apply')); ?></button>
			<button id="kpi-reset-filter" type="button"><?php echo dol_escape_htmltag($langs->trans('Reset')); ?></button>
			<button id="support-kpi-export" type="button"><?php echo dol_escape_htmltag($langs->trans('Export')); ?></button>
		</div>
	</div>

	<div id="support-kpi-results">
		<div class="kpi-loading"><?php echo dol_escape_htmltag($langs->trans('Loading')); ?></div>
	</div>
</div>
<?php

llxFooter();
