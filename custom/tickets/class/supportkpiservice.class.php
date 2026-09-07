<?php

require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/ticket/class/ticket.class.php';

class SupportKpiService
{
	const STATUS_OPEN = 'open';
	const STATUS_IN_PROGRESS = 'in_progress';
	const STATUS_CLOSED = 'closed';

	/** @var DoliDB */
	private $db;

	/** @var Conf */
	private $conf;

	/** @var Translate */
	private $langs;

	public function __construct($db, $conf, $langs)
	{
		$this->db = $db;
		$this->conf = $conf;
		$this->langs = $langs;
	}

	public function getDashboardData(User $user, array $inputFilters)
	{
		$filters = $this->sanitizeFilters($inputFilters);
		$projects = $this->fetchProjects($user, $filters);
		$ticketStats = $this->fetchTicketStats($user, $filters);
		$taskStats = $this->fetchTaskConsumptionStats($user, $filters);
		$projects = $this->buildProjectRows($projects, $ticketStats, $taskStats);
		$summary = $this->buildSummary($projects);
		$statusMetric = $this->buildStatusMetric($projects);
		$resolutionMetric = $this->buildResolutionMetric($projects);
		foreach ($projects as &$project) {
			unset($project['resolution_seconds_total']);
		}
		unset($project);

		return array(
			'filters' => $filters,
			'filter_options' => array(
				'projects' => $this->getProjectOptions($user),
			),
			'summary' => $summary,
			'status_metric' => $statusMetric,
			'resolution_metric' => $resolutionMetric,
			'projects' => array_values($projects),
		);
	}

	public function sanitizeFilters(array $input)
	{
		return array(
			'project_id' => !empty($input['project_id']) ? max(0, (int) $input['project_id']) : 0,
			'start_date' => $this->sanitizeDate(isset($input['start_date']) ? $input['start_date'] : ''),
			'end_date' => $this->sanitizeDate(isset($input['end_date']) ? $input['end_date'] : ''),
		);
	}

	public function exportCsv(User $user, array $inputFilters)
	{
		$data = $this->getDashboardData($user, $inputFilters);
		$filename = 'kpi-support-'.date('Ymd-His').'.csv';

		header('Content-Type: text/csv; charset=UTF-8');
		header('Content-Disposition: attachment; filename="'.$filename.'"');

		$out = fopen('php://output', 'w');
		fwrite($out, chr(0xEF).chr(0xBB).chr(0xBF));

		tickets_support_kpi_csv_put_row($out, array('Synthese'));
		tickets_support_kpi_csv_put_row($out, array('Tickets ouverts', $data['summary']['open']));
		tickets_support_kpi_csv_put_row($out, array('Tickets en cours', $data['summary']['in_progress']));
		tickets_support_kpi_csv_put_row($out, array('Tickets fermes', $data['summary']['closed']));
		tickets_support_kpi_csv_put_row($out, array('Delai moyen de resolution', $data['summary']['avg_resolution_label']));
		tickets_support_kpi_csv_put_row($out, array());

		tickets_support_kpi_csv_put_row($out, array('Projets Dolibarr'));
		tickets_support_kpi_csv_put_row($out, array('Projet', 'Type', 'Client', 'Ouverts', 'En cours', 'Fermes', 'Taches avec temps', 'Temps consomme moyen'));
		foreach ($data['projects'] as $project) {
			tickets_support_kpi_csv_put_row($out, array(
				$project['project_ref'].' - '.$project['project_title'],
				$project['project_type_label'],
				$project['thirdparty_name'],
				$project['open'],
				$project['in_progress'],
				$project['closed'],
				$project['resolution_count'],
				$project['avg_resolution_label'],
			));
		}

		fclose($out);
		exit;
	}

	private function fetchProjects(User $user, array $filters = array())
	{
		$sql = "SELECT p.rowid, p.ref, p.title, s.nom as thirdparty_name,";
		$sql .= " COUNT(DISTINCT task.rowid) as task_count, COUNT(DISTINCT ticket.rowid) as ticket_count";
		$sql .= " FROM ".MAIN_DB_PREFIX."projet as p";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON s.rowid = p.fk_soc";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."projet_task as task ON task.fk_projet = p.rowid AND task.entity IN (".getEntity('project').")";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."ticket as ticket ON ticket.fk_project = p.rowid AND ticket.entity IN (".getEntity('ticket').")";
		$sql .= " WHERE p.entity IN (".getEntity('project').")";
		$sql .= " AND p.fk_statut = 1";
		$sql .= $this->buildProjectAccessSql($user);

		if (!empty($filters['project_id'])) {
			$sql .= " AND p.rowid = ".((int) $filters['project_id']);
		}

		$sql .= " GROUP BY p.rowid, p.ref, p.title, s.nom";
		$sql .= " ORDER BY p.title ASC, p.ref ASC";

		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' SQL error: '.$this->db->lasterror(), LOG_ERR);
			return array();
		}

		$projects = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$projects[(int) $obj->rowid] = array(
				'project_id' => (int) $obj->rowid,
				'project_ref' => (string) $obj->ref,
				'project_title' => (string) $obj->title,
				'project_type_label' => $this->getProjectTypeLabel((int) $obj->ticket_count, (int) $obj->task_count),
				'thirdparty_name' => (string) $obj->thirdparty_name,
				'open' => 0,
				'in_progress' => 0,
				'closed' => 0,
				'total' => 0,
				'resolution_count' => 0,
				'resolution_seconds_total' => 0,
				'avg_resolution_seconds' => null,
				'avg_resolution_label' => 'Aucune donnee',
			);
		}

		return $projects;
	}

	private function fetchTicketStats(User $user, array $filters)
	{
		$sql = "SELECT p.rowid as project_id, t.fk_statut, COUNT(t.rowid) as ticket_count";
		$sql .= " FROM ".MAIN_DB_PREFIX."ticket as t";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."projet as p ON p.rowid = t.fk_project";
		$sql .= " WHERE t.entity IN (".getEntity('ticket').")";
		$sql .= " AND p.entity IN (".getEntity('project').")";
		$sql .= " AND p.fk_statut = 1";
		$sql .= " AND t.fk_project > 0";
		$sql .= $this->buildProjectAccessSql($user);

		if ($filters['project_id'] > 0) {
			$sql .= " AND t.fk_project = ".((int) $filters['project_id']);
		}
		if ($filters['start_date'] !== '') {
			$sql .= " AND t.datec >= '".$this->db->idate($this->parseDateStart($filters['start_date']))."'";
		}
		if ($filters['end_date'] !== '') {
			$sql .= " AND t.datec <= '".$this->db->idate($this->parseDateEnd($filters['end_date']))."'";
		}

		$sql .= " GROUP BY p.rowid, t.fk_statut";

		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' SQL error: '.$this->db->lasterror(), LOG_ERR);
			return array();
		}

		$stats = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$projectId = (int) $obj->project_id;
			if (!isset($stats[$projectId])) {
				$stats[$projectId] = array('open' => 0, 'in_progress' => 0, 'closed' => 0);
			}
			$statusGroup = $this->getStatusGroup((int) $obj->fk_statut);
			$stats[$projectId][$statusGroup] += (int) $obj->ticket_count;
		}

		return $stats;
	}

	private function fetchTaskConsumptionStats(User $user, array $filters)
	{
		$sql = "SELECT p.rowid as project_id, COUNT(task.rowid) as task_count, SUM(task.duration_effective) as duration_total";
		$sql .= " FROM ".MAIN_DB_PREFIX."projet_task as task";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."projet as p ON p.rowid = task.fk_projet";
		$sql .= " WHERE task.entity IN (".getEntity('project').")";
		$sql .= " AND p.entity IN (".getEntity('project').")";
		$sql .= " AND p.fk_statut = 1";
		$sql .= " AND task.duration_effective IS NOT NULL";
		$sql .= " AND task.duration_effective > 0";
		$sql .= $this->buildProjectAccessSql($user);

		if ($filters['project_id'] > 0) {
			$sql .= " AND task.fk_projet = ".((int) $filters['project_id']);
		}
		if ($filters['start_date'] !== '') {
			$sql .= " AND task.dateo >= '".$this->db->idate($this->parseDateStart($filters['start_date']))."'";
		}
		if ($filters['end_date'] !== '') {
			$sql .= " AND task.dateo <= '".$this->db->idate($this->parseDateEnd($filters['end_date']))."'";
		}

		$sql .= " GROUP BY p.rowid";

		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' SQL error: '.$this->db->lasterror(), LOG_ERR);
			return array();
		}

		$stats = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$stats[(int) $obj->project_id] = array(
				'count' => (int) $obj->task_count,
				'total_seconds' => (int) $obj->duration_total,
			);
		}

		return $stats;
	}

	private function buildProjectRows(array $projects, array $ticketStats, array $taskStats)
	{
		foreach ($ticketStats as $projectId => $stats) {
			if (!isset($projects[$projectId])) {
				continue;
			}

			$projects[$projectId]['open'] = (int) $stats['open'];
			$projects[$projectId]['in_progress'] = (int) $stats['in_progress'];
			$projects[$projectId]['closed'] = (int) $stats['closed'];
			$projects[$projectId]['total'] = $projects[$projectId]['open'] + $projects[$projectId]['in_progress'] + $projects[$projectId]['closed'];
		}

		foreach ($taskStats as $projectId => $stats) {
			if (!isset($projects[$projectId])) {
				continue;
			}
			$projects[$projectId]['resolution_count'] = (int) $stats['count'];
			$projects[$projectId]['resolution_seconds_total'] = (int) $stats['total_seconds'];
		}

		foreach ($projects as &$project) {
			if ($project['resolution_count'] > 0) {
				$project['avg_resolution_seconds'] = (int) round($project['resolution_seconds_total'] / $project['resolution_count']);
				$project['avg_resolution_label'] = $this->formatDuration($project['avg_resolution_seconds']);
			}
		}
		unset($project);

		return $projects;
	}

	private function buildSummary(array $projects)
	{
		$summary = array(
			'open' => 0,
			'in_progress' => 0,
			'closed' => 0,
			'total' => 0,
			'resolution_count' => 0,
			'resolution_seconds_total' => 0,
			'avg_resolution_seconds' => null,
			'avg_resolution_label' => 'Aucune donnee',
		);

		foreach ($projects as $project) {
			$summary['open'] += $project['open'];
			$summary['in_progress'] += $project['in_progress'];
			$summary['closed'] += $project['closed'];
			$summary['total'] += $project['total'];
			$summary['resolution_count'] += $project['resolution_count'];
			$summary['resolution_seconds_total'] += $project['resolution_seconds_total'];
		}

		if ($summary['resolution_count'] > 0) {
			$summary['avg_resolution_seconds'] = (int) round($summary['resolution_seconds_total'] / $summary['resolution_count']);
			$summary['avg_resolution_label'] = $this->formatDuration($summary['avg_resolution_seconds']);
		}

		unset($summary['resolution_count'], $summary['resolution_seconds_total']);

		return $summary;
	}

	private function buildStatusMetric(array $projects)
	{
		$counts = array('open' => 0, 'in_progress' => 0, 'closed' => 0);
		foreach ($projects as $project) {
			$counts['open'] += $project['open'];
			$counts['in_progress'] += $project['in_progress'];
			$counts['closed'] += $project['closed'];
		}

		$total = array_sum($counts);
		$series = array(
			array('label' => 'Ouverts', 'count' => $counts['open'], 'color' => '#2f80ed'),
			array('label' => 'En cours', 'count' => $counts['in_progress'], 'color' => '#f2994a'),
			array('label' => 'Fermes', 'count' => $counts['closed'], 'color' => '#27ae60'),
		);

		foreach ($series as &$item) {
			$item['percentage'] = $total > 0 ? round(($item['count'] / $total) * 100, 1) : 0;
		}
		unset($item);

		return array(
			'title' => 'Repartition des tickets par statut',
			'total' => $total,
			'series' => $series,
		);
	}

	private function buildResolutionMetric(array $projects)
	{
		$validTasks = 0;
		$totalSeconds = 0;
		foreach ($projects as $project) {
			if ($project['avg_resolution_seconds'] !== null) {
				$validTasks += (int) $project['resolution_count'];
				$totalSeconds += (int) $project['resolution_seconds_total'];
			}
		}

		$average = $validTasks > 0 ? (int) round($totalSeconds / $validTasks) : null;
		$maxAverage = 0;
		foreach ($projects as $project) {
			if ($project['avg_resolution_seconds'] !== null) {
				$maxAverage = max($maxAverage, (int) $project['avg_resolution_seconds']);
			}
		}

		$series = array();
		foreach ($projects as $project) {
			if ($project['avg_resolution_seconds'] === null) {
				continue;
			}
			$series[] = array(
				'label' => $project['project_ref'],
				'count' => $project['avg_resolution_label'],
				'percentage' => $maxAverage > 0 ? round(($project['avg_resolution_seconds'] / $maxAverage) * 100, 1) : 0,
				'color' => '#655aa8',
			);
		}

		return array(
			'title' => 'Delai moyen de resolution',
			'valid_rows' => $validTasks,
			'average_label' => $this->formatDuration($average),
			'series' => $series,
		);
	}

	private function getProjectOptions(User $user)
	{
		$options = array();
		foreach ($this->fetchProjects($user) as $project) {
			$options[] = array(
				'id' => (int) $project['project_id'],
				'label' => trim($project['project_ref'].' - '.$project['project_title']),
			);
		}

		return $options;
	}

	private function getProjectTypeLabel($ticketCount, $taskCount)
	{
		$hasTickets = $ticketCount > 0;
		$hasTasks = $taskCount > 0;

		if ($hasTickets && $hasTasks) {
			return 'Projets Tickets + Taches';
		}
		if ($hasTickets) {
			return 'Projets Tickets';
		}
		if ($hasTasks) {
			return 'Projets Taches';
		}

		return 'Projet';
	}

	private function buildProjectAccessSql(User $user)
	{
		if ($user->hasRight('projet', 'all', 'lire')) {
			return '';
		}

		$projectstatic = new Project($this->db);
		$projectIds = $projectstatic->getProjectsAuthorizedForUser($user, 0, 1, $user->socid > 0 ? $user->socid : 0);
		if (empty($projectIds) || $projectIds === '0') {
			return ' AND 1 = 0';
		}

		$ids = array_filter(array_map('intval', explode(',', (string) $projectIds)));
		if (empty($ids)) {
			return ' AND 1 = 0';
		}

		return ' AND p.rowid IN ('.implode(',', $ids).')';
	}

	private function getStatusGroup($status)
	{
		if ($status === Ticket::STATUS_IN_PROGRESS) {
			return self::STATUS_IN_PROGRESS;
		}
		if (in_array($status, array(Ticket::STATUS_CLOSED, Ticket::STATUS_CANCELED), true)) {
			return self::STATUS_CLOSED;
		}

		return self::STATUS_OPEN;
	}

	private function sanitizeDate($value)
	{
		$value = trim((string) $value);
		return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
	}

	private function parseDateStart($date)
	{
		return strtotime($date.' 00:00:00');
	}

	private function parseDateEnd($date)
	{
		return strtotime($date.' 23:59:59');
	}

	private function formatDuration($seconds)
	{
		if ($seconds === null) {
			return 'Aucune donnee';
		}

		$seconds = (int) $seconds;
		if ($seconds === 0) {
			return '0 h';
		}

		$days = floor($seconds / 86400);
		$hours = floor(($seconds % 86400) / 3600);

		if ($days > 0) {
			return $days.' j '.str_pad((string) $hours, 2, '0', STR_PAD_LEFT).' h';
		}

		return max(1, (int) ceil($seconds / 3600)).' h';
	}

}
