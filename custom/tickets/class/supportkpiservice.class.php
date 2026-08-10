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
		$tickets = $this->fetchTickets($user, $filters);
		$projects = $this->buildProjectRows($projects, $tickets);

		return array(
			'filters' => $filters,
			'filter_options' => array(
				'projects' => $this->getProjectOptions($user),
				'assignees' => $this->getAssigneeOptions($user),
				'statuses' => $this->getStatusOptions(),
			),
			'summary' => $this->buildSummary($projects, $tickets),
			'status_metric' => $this->buildStatusMetric($projects),
			'resolution_metric' => $this->buildResolutionMetric($projects, $tickets),
			'projects' => array_values($projects),
			'tickets' => $tickets,
		);
	}

	public function sanitizeFilters(array $input)
	{
		$status = isset($input['status']) ? trim((string) $input['status']) : '';
		if (!in_array($status, array('', self::STATUS_OPEN, self::STATUS_IN_PROGRESS, self::STATUS_CLOSED), true)) {
			$status = '';
		}

		return array(
			'project_id' => !empty($input['project_id']) ? max(0, (int) $input['project_id']) : 0,
			'assignee_id' => !empty($input['assignee_id']) ? max(0, (int) $input['assignee_id']) : 0,
			'status' => $status,
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
		tickets_support_kpi_csv_put_row($out, array('Projet', 'Type', 'Client', 'Ouverts', 'En cours', 'Fermes', 'Delai moyen'));
		foreach ($data['projects'] as $project) {
			tickets_support_kpi_csv_put_row($out, array(
				$project['project_ref'].' - '.$project['project_title'],
				$project['project_type_label'],
				$project['thirdparty_name'],
				$project['open'],
				$project['in_progress'],
				$project['closed'],
				$project['avg_resolution_label'],
			));
		}

		tickets_support_kpi_csv_put_row($out, array());
		tickets_support_kpi_csv_put_row($out, array('Tickets'));
		tickets_support_kpi_csv_put_row($out, array('Ref', 'Sujet', 'Projet', 'Client', 'Statut', 'Assigne', 'Date creation', 'Date fermeture', 'Delai resolution'));
		foreach ($data['tickets'] as $ticket) {
			tickets_support_kpi_csv_put_row($out, array(
				$ticket['ref'],
				$ticket['subject'],
				$ticket['project_ref'].' - '.$ticket['project_title'],
				$ticket['thirdparty_name'],
				$ticket['status_label'],
				$ticket['assignee_name'],
				$ticket['date_creation_label'],
				$ticket['date_close_label'],
				$ticket['resolution_label'],
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

	private function fetchTickets(User $user, array $filters)
	{
		$sql = "SELECT t.rowid, t.ref, t.subject, t.fk_statut, t.fk_user_assign, t.datec, t.date_close,";
		$sql .= " p.rowid as project_id, p.ref as project_ref, p.title as project_title,";
		$sql .= " s.nom as thirdparty_name, u.firstname as assignee_firstname, u.lastname as assignee_lastname";
		$sql .= " FROM ".MAIN_DB_PREFIX."ticket as t";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."projet as p ON p.rowid = t.fk_project";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON s.rowid = p.fk_soc";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON u.rowid = t.fk_user_assign";
		$sql .= " WHERE t.entity IN (".getEntity('ticket').")";
		$sql .= " AND p.entity IN (".getEntity('project').")";
		$sql .= " AND p.fk_statut = 1";
		$sql .= " AND t.fk_project > 0";
		$sql .= $this->buildProjectAccessSql($user);

		if ($filters['project_id'] > 0) {
			$sql .= " AND t.fk_project = ".((int) $filters['project_id']);
		}
		if ($filters['assignee_id'] > 0) {
			$sql .= " AND t.fk_user_assign = ".((int) $filters['assignee_id']);
		}
		if ($filters['status'] !== '') {
			$sql .= " AND t.fk_statut IN (".implode(',', $this->getStatusIdsForFilter($filters['status'])).")";
		}
		if ($filters['start_date'] !== '') {
			$sql .= " AND t.datec >= '".$this->db->idate($this->parseDateStart($filters['start_date']))."'";
		}
		if ($filters['end_date'] !== '') {
			$sql .= " AND t.datec <= '".$this->db->idate($this->parseDateEnd($filters['end_date']))."'";
		}

		$sql .= " ORDER BY p.title ASC, t.datec DESC, t.rowid DESC";

		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' SQL error: '.$this->db->lasterror(), LOG_ERR);
			return array();
		}

		$tickets = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$createdTs = $this->db->jdate($obj->datec);
			$closedTs = $this->db->jdate($obj->date_close);
			$resolutionSeconds = $this->getResolutionSeconds($createdTs, $closedTs);

			$tickets[] = array(
				'id' => (int) $obj->rowid,
				'ref' => (string) $obj->ref,
				'subject' => (string) $obj->subject,
				'status' => (int) $obj->fk_statut,
				'status_group' => $this->getStatusGroup((int) $obj->fk_statut),
				'status_label' => $this->getTicketStatusLabel((int) $obj->fk_statut),
				'assignee_id' => (int) $obj->fk_user_assign,
				'assignee_name' => $this->formatUserName($obj->assignee_firstname, $obj->assignee_lastname),
				'project_id' => (int) $obj->project_id,
				'project_ref' => (string) $obj->project_ref,
				'project_title' => (string) $obj->project_title,
				'thirdparty_name' => (string) $obj->thirdparty_name,
				'date_creation' => $createdTs,
				'date_creation_label' => $createdTs ? dol_print_date($createdTs, 'dayhour') : '',
				'date_close' => $closedTs,
				'date_close_label' => $closedTs ? dol_print_date($closedTs, 'dayhour') : '',
				'resolution_seconds' => $resolutionSeconds,
				'resolution_label' => $this->formatDuration($resolutionSeconds),
				'ticket_url' => DOL_URL_ROOT.'/ticket/card.php?id='.(int) $obj->rowid,
			);
		}

		return $tickets;
	}

	private function buildProjectRows(array $projects, array $tickets)
	{
		foreach ($tickets as $ticket) {
			$projectId = $ticket['project_id'];
			if (!isset($projects[$projectId])) {
				continue;
			}

			$projects[$projectId]['total']++;
			if ($ticket['status_group'] === self::STATUS_IN_PROGRESS) {
				$projects[$projectId]['in_progress']++;
			} elseif ($ticket['status_group'] === self::STATUS_CLOSED) {
				$projects[$projectId]['closed']++;
			} else {
				$projects[$projectId]['open']++;
			}

			if ($ticket['resolution_seconds'] !== null) {
				$projects[$projectId]['resolution_count']++;
				$projects[$projectId]['resolution_seconds_total'] += $ticket['resolution_seconds'];
			}
		}

		foreach ($projects as &$project) {
			if ($project['resolution_count'] > 0) {
				$project['avg_resolution_seconds'] = (int) round($project['resolution_seconds_total'] / $project['resolution_count']);
				$project['avg_resolution_label'] = $this->formatDuration($project['avg_resolution_seconds']);
			}
			unset($project['resolution_count'], $project['resolution_seconds_total']);
		}
		unset($project);

		return $projects;
	}

	private function buildSummary(array $projects, array $tickets)
	{
		$summary = array(
			'open' => 0,
			'in_progress' => 0,
			'closed' => 0,
			'total' => count($tickets),
			'resolution_count' => 0,
			'resolution_seconds_total' => 0,
			'avg_resolution_seconds' => null,
			'avg_resolution_label' => 'Aucune donnee',
		);

		foreach ($projects as $project) {
			$summary['open'] += $project['open'];
			$summary['in_progress'] += $project['in_progress'];
			$summary['closed'] += $project['closed'];
		}

		foreach ($tickets as $ticket) {
			if ($ticket['resolution_seconds'] !== null) {
				$summary['resolution_count']++;
				$summary['resolution_seconds_total'] += $ticket['resolution_seconds'];
			}
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

	private function buildResolutionMetric(array $projects, array $tickets)
	{
		$validTickets = 0;
		$totalSeconds = 0;
		foreach ($tickets as $ticket) {
			if ($ticket['resolution_seconds'] !== null) {
				$validTickets++;
				$totalSeconds += $ticket['resolution_seconds'];
			}
		}

		$average = $validTickets > 0 ? (int) round($totalSeconds / $validTickets) : null;
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
			'valid_rows' => $validTickets,
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

	private function getAssigneeOptions(User $user)
	{
		$sql = "SELECT DISTINCT u.rowid, u.firstname, u.lastname";
		$sql .= " FROM ".MAIN_DB_PREFIX."ticket as t";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."projet as p ON p.rowid = t.fk_project";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."user as u ON u.rowid = t.fk_user_assign";
		$sql .= " WHERE t.entity IN (".getEntity('ticket').")";
		$sql .= " AND p.entity IN (".getEntity('project').")";
		$sql .= " AND p.fk_statut = 1";
		$sql .= " AND t.fk_user_assign > 0";
		$sql .= $this->buildProjectAccessSql($user);
		$sql .= " ORDER BY u.lastname ASC, u.firstname ASC";

		$resql = $this->db->query($sql);
		$options = array();
		while ($resql && $obj = $this->db->fetch_object($resql)) {
			$options[] = array(
				'id' => (int) $obj->rowid,
				'label' => $this->formatUserName($obj->firstname, $obj->lastname),
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

	private function getStatusOptions()
	{
		return array(
			array('id' => self::STATUS_OPEN, 'label' => 'Ouverts'),
			array('id' => self::STATUS_IN_PROGRESS, 'label' => 'En cours'),
			array('id' => self::STATUS_CLOSED, 'label' => 'Fermes'),
		);
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

	private function getStatusIdsForFilter($status)
	{
		if ($status === self::STATUS_IN_PROGRESS) {
			return array(Ticket::STATUS_IN_PROGRESS);
		}
		if ($status === self::STATUS_CLOSED) {
			return array(Ticket::STATUS_CLOSED, Ticket::STATUS_CANCELED);
		}

		return array(Ticket::STATUS_NOT_READ, Ticket::STATUS_READ, Ticket::STATUS_ASSIGNED, Ticket::STATUS_NEED_MORE_INFO, Ticket::STATUS_WAITING);
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

	private function getTicketStatusLabel($status)
	{
		$labels = array(
			Ticket::STATUS_NOT_READ => 'Non lu',
			Ticket::STATUS_READ => 'Lu',
			Ticket::STATUS_ASSIGNED => 'Assigne',
			Ticket::STATUS_IN_PROGRESS => 'En cours',
			Ticket::STATUS_NEED_MORE_INFO => 'Attente info',
			Ticket::STATUS_WAITING => 'En attente',
			Ticket::STATUS_CLOSED => 'Ferme / resolu',
			Ticket::STATUS_CANCELED => 'Annule',
		);

		return isset($labels[$status]) ? $labels[$status] : 'Inconnu';
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

	private function getResolutionSeconds($createdTs, $closedTs)
	{
		if (empty($createdTs) || empty($closedTs) || $closedTs < $createdTs) {
			return null;
		}

		return (int) $closedTs - (int) $createdTs;
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

	private function formatUserName($firstname, $lastname)
	{
		$name = trim((string) $firstname.' '.(string) $lastname);
		return $name !== '' ? $name : 'Non assigne';
	}
}
