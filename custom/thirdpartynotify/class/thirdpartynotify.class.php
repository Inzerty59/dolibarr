<?php

class ThirdpartyNotify
{
	public const CONTEXT_GLOBAL = 'thirdparty_messaging';
	public const KANBAN_STATUS_PENDING = 'pending';
	public const KANBAN_STATUS_RUNNING = 'running';
	public const KANBAN_STATUS_ARCHIVED = 'archived';

	/** @var DoliDB */
	private $db;

	public function __construct($db)
	{
		$this->db = $db;
	}

	public function getSelectedUsers($entity, $contextType = self::CONTEXT_GLOBAL, $fkContext = 0)
	{
		$users = array();
		$sql = "SELECT u.rowid, u.firstname, u.lastname, u.login, u.email, u.statut";
		$sql .= " FROM ".MAIN_DB_PREFIX."thirdpartynotify_user as n";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."user as u ON u.rowid = n.fk_user";
		$sql .= " WHERE n.entity = ".((int) $entity);
		$sql .= " AND n.context_type = '".$this->db->escape($contextType)."'";
		$sql .= " AND n.fk_context = ".((int) $fkContext);
		$sql .= " AND n.active = 1";
		$sql .= " AND u.statut = 1";
		$sql .= " ORDER BY u.firstname ASC, u.lastname ASC, u.login ASC";

		$resql = $this->db->query($sql);
		if (!$resql) {
			return -1;
		}

		while ($obj = $this->db->fetch_object($resql)) {
			$name = trim($obj->firstname.' '.$obj->lastname);
			if ($name === '') {
				$name = $obj->login;
			}
			$users[] = array(
				'id' => (int) $obj->rowid,
				'name' => $name,
				'email' => (string) $obj->email,
				'initials' => $this->buildInitials($name, $obj->login),
			);
		}

		return $users;
	}

	public function getSelectableUsers($entity)
	{
		$users = array();
		$sql = "SELECT rowid, firstname, lastname, login, email";
		$sql .= " FROM ".MAIN_DB_PREFIX."user";
		$sql .= " WHERE statut = 1";
		$sql .= " AND entity IN (0, ".((int) $entity).")";
		$sql .= " ORDER BY firstname ASC, lastname ASC, login ASC";

		$resql = $this->db->query($sql);
		if (!$resql) {
			return -1;
		}

		while ($obj = $this->db->fetch_object($resql)) {
			$name = trim($obj->firstname.' '.$obj->lastname);
			if ($name === '') {
				$name = $obj->login;
			}
			$users[] = array(
				'id' => (int) $obj->rowid,
				'name' => $name,
				'email' => (string) $obj->email,
				'initials' => $this->buildInitials($name, $obj->login),
			);
		}

		return $users;
	}

	public function replaceSelectedUsers($entity, array $userIds, $currentUserId, $contextType = self::CONTEXT_GLOBAL, $fkContext = 0)
	{
		$cleanIds = array();
		foreach ($userIds as $userId) {
			$userId = (int) $userId;
			if ($userId > 0) {
				$cleanIds[$userId] = $userId;
			}
		}

		$validIds = $this->filterValidUserIds($entity, array_values($cleanIds));
		if ($validIds === -1) {
			return -1;
		}

		$this->db->begin();

		$sql = "DELETE FROM ".MAIN_DB_PREFIX."thirdpartynotify_user";
		$sql .= " WHERE entity = ".((int) $entity);
		$sql .= " AND context_type = '".$this->db->escape($contextType)."'";
		$sql .= " AND fk_context = ".((int) $fkContext);
		if (!$this->db->query($sql)) {
			$this->db->rollback();
			return -1;
		}

		foreach ($validIds as $userId) {
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."thirdpartynotify_user (";
			$sql .= "entity, fk_user, context_type, fk_context, active, date_creation, fk_user_creat, fk_user_modif";
			$sql .= ") VALUES (";
			$sql .= ((int) $entity).", ";
			$sql .= ((int) $userId).", ";
			$sql .= "'".$this->db->escape($contextType)."', ";
			$sql .= ((int) $fkContext).", ";
			$sql .= "1, ";
			$sql .= "'".$this->db->idate(dol_now())."', ";
			$sql .= ((int) $currentUserId).", ";
			$sql .= ((int) $currentUserId);
			$sql .= ")";
			if (!$this->db->query($sql)) {
				$this->db->rollback();
				return -1;
			}
		}

		$this->db->commit();
		return count($validIds);
	}

	public function getRecipientEmails($entity, $contextType = self::CONTEXT_GLOBAL, $fkContext = 0)
	{
		$recipients = array();
		$users = $this->getSelectedUsers($entity, $contextType, $fkContext);
		if ($users === -1) {
			return -1;
		}

		foreach ($users as $user) {
			$email = trim($user['email']);
			if ($email !== '' && isValidEmail($email)) {
				$recipients[$email] = $user['name'].' <'.$email.'>';
			}
		}

		return array_values($recipients);
	}

	public function createKanbanCardsForSelectedUsers($entity, $event, $thirdparty, array $contacts)
	{
		$this->ensureKanbanTable();

		$selectedUsers = $this->getSelectedUsers($entity);
		if ($selectedUsers === -1) {
			return -1;
		}

		$status = $this->mapProgressToKanbanStatus((int) $event->percentage);
		$contactsJson = json_encode(array_values($contacts));
		$count = 0;

		foreach ($selectedUsers as $selectedUser) {
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."thirdpartynotify_kanban_card (";
			$sql .= "entity, fk_user_dest, fk_actioncomm, fk_soc, event_ref, event_label, event_date_start, event_date_end, contacts_json, status, date_creation";
			$sql .= ") VALUES (";
			$sql .= ((int) $entity).", ";
			$sql .= ((int) $selectedUser['id']).", ";
			$sql .= ((int) $event->id).", ";
			$sql .= ((int) $thirdparty->id).", ";
			$sql .= "'".$this->db->escape('#'.((int) $event->id))."', ";
			$sql .= "'".$this->db->escape((string) $event->label)."', ";
			$sql .= ($event->datep ? "'".$this->db->idate($event->datep)."'" : "NULL").", ";
			$sql .= ($event->datef ? "'".$this->db->idate($event->datef)."'" : "NULL").", ";
			$sql .= "'".$this->db->escape((string) $contactsJson)."', ";
			$sql .= "'".$this->db->escape($status)."', ";
			$sql .= "'".$this->db->idate(dol_now())."'";
			$sql .= ") ON DUPLICATE KEY UPDATE ";
			$sql .= "event_label = VALUES(event_label), ";
			$sql .= "event_date_start = VALUES(event_date_start), ";
			$sql .= "event_date_end = VALUES(event_date_end), ";
			$sql .= "contacts_json = VALUES(contacts_json), ";
			$sql .= "status = VALUES(status)";

			if (!$this->db->query($sql)) {
				return -1;
			}
			$count++;
		}

		return $count;
	}

	public function fetchKanbanCardsForUser($entity, $userId, $includeAll = false)
	{
		$this->ensureKanbanTable();

		$cards = array(
			self::KANBAN_STATUS_PENDING => array(),
			self::KANBAN_STATUS_RUNNING => array(),
			self::KANBAN_STATUS_ARCHIVED => array(),
		);

		$sql = "SELECT k.rowid, k.fk_user_dest, k.fk_actioncomm, k.fk_soc, k.event_ref, k.event_label, k.event_date_start, k.event_date_end, k.contacts_json, k.status, s.nom as thirdparty_name,";
		$sql .= " u.firstname, u.lastname, u.login";
		$sql .= " FROM ".MAIN_DB_PREFIX."thirdpartynotify_kanban_card as k";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON s.rowid = k.fk_soc";
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON u.rowid = k.fk_user_dest";
		$sql .= " WHERE k.entity = ".((int) $entity);
		if (!$includeAll) {
			$sql .= " AND k.fk_user_dest = ".((int) $userId);
		}
		$sql .= " ORDER BY k.date_creation DESC, k.rowid DESC";

		$resql = $this->db->query($sql);
		if (!$resql) {
			return -1;
		}

		while ($obj = $this->db->fetch_object($resql)) {
			$status = $this->normalizeKanbanStatus($obj->status);
			$contacts = json_decode((string) $obj->contacts_json, true);
			$recipientName = trim($obj->firstname.' '.$obj->lastname);
			if ($recipientName === '') {
				$recipientName = $obj->login;
			}
			$cards[$status][] = array(
				'id' => (int) $obj->rowid,
				'recipient_id' => (int) $obj->fk_user_dest,
				'recipient_name' => (string) $recipientName,
				'actioncomm_id' => (int) $obj->fk_actioncomm,
				'socid' => (int) $obj->fk_soc,
				'thirdparty_name' => (string) $obj->thirdparty_name,
				'ref' => (string) $obj->event_ref,
				'label' => (string) $obj->event_label,
				'date_start' => $obj->event_date_start ? $this->db->jdate($obj->event_date_start) : 0,
				'date_end' => $obj->event_date_end ? $this->db->jdate($obj->event_date_end) : 0,
				'date_start_label' => $obj->event_date_start ? dol_print_date($this->db->jdate($obj->event_date_start), 'dayhour', 'tzuserrel') : '',
				'date_end_label' => $obj->event_date_end ? dol_print_date($this->db->jdate($obj->event_date_end), 'dayhour', 'tzuserrel') : '',
				'contacts' => is_array($contacts) ? $contacts : array(),
				'status' => $status,
			);
		}

		return $cards;
	}

	public function updateKanbanCardStatus($entity, $userId, $cardId, $status, $allowAnyUserCard = false)
	{
		$this->ensureKanbanTable();
		$status = $this->normalizeKanbanStatus($status);

		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."thirdpartynotify_kanban_card";
		$sql .= " WHERE rowid = ".((int) $cardId);
		$sql .= " AND entity = ".((int) $entity);
		if (!$allowAnyUserCard) {
			$sql .= " AND fk_user_dest = ".((int) $userId);
		}

		$resql = $this->db->query($sql);
		if (!$resql) {
			return -1;
		}
		if (!$this->db->fetch_object($resql)) {
			return 0;
		}

		$sql = "UPDATE ".MAIN_DB_PREFIX."thirdpartynotify_kanban_card";
		$sql .= " SET status = '".$this->db->escape($status)."'";
		$sql .= " WHERE rowid = ".((int) $cardId);
		$sql .= " AND entity = ".((int) $entity);
		if (!$allowAnyUserCard) {
			$sql .= " AND fk_user_dest = ".((int) $userId);
		}

		return $this->db->query($sql) ? 1 : -1;
	}

	public function ensureKanbanTable()
	{
		$sql = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."thirdpartynotify_kanban_card (";
		$sql .= "rowid integer AUTO_INCREMENT PRIMARY KEY,";
		$sql .= "entity integer NOT NULL DEFAULT 1,";
		$sql .= "fk_user_dest integer NOT NULL,";
		$sql .= "fk_actioncomm integer NOT NULL,";
		$sql .= "fk_soc integer NOT NULL,";
		$sql .= "event_ref varchar(32) NOT NULL,";
		$sql .= "event_label varchar(255) NOT NULL,";
		$sql .= "event_date_start datetime DEFAULT NULL,";
		$sql .= "event_date_end datetime DEFAULT NULL,";
		$sql .= "contacts_json text DEFAULT NULL,";
		$sql .= "status varchar(32) NOT NULL DEFAULT 'pending',";
		$sql .= "date_creation datetime NOT NULL,";
		$sql .= "tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,";
		$sql .= "UNIQUE KEY uk_thirdpartynotify_kanban_user_event (entity, fk_user_dest, fk_actioncomm),";
		$sql .= "KEY idx_thirdpartynotify_kanban_user_status (entity, fk_user_dest, status)";
		$sql .= ") ENGINE=innodb";

		return $this->db->query($sql) ? 1 : -1;
	}

	private function mapProgressToKanbanStatus($progress)
	{
		if ($progress >= 100) {
			return self::KANBAN_STATUS_ARCHIVED;
		}
		if ($progress > 0) {
			return self::KANBAN_STATUS_RUNNING;
		}
		return self::KANBAN_STATUS_PENDING;
	}

	private function normalizeKanbanStatus($status)
	{
		$allowed = array(self::KANBAN_STATUS_PENDING, self::KANBAN_STATUS_RUNNING, self::KANBAN_STATUS_ARCHIVED);
		return in_array($status, $allowed, true) ? $status : self::KANBAN_STATUS_PENDING;
	}

	private function filterValidUserIds($entity, array $userIds)
	{
		if (empty($userIds)) {
			return array();
		}

		$ids = array();
		foreach ($userIds as $userId) {
			$ids[] = (int) $userId;
		}

		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."user";
		$sql .= " WHERE statut = 1";
		$sql .= " AND entity IN (0, ".((int) $entity).")";
		$sql .= " AND rowid IN (".$this->db->sanitize(implode(',', $ids)).")";

		$resql = $this->db->query($sql);
		if (!$resql) {
			return -1;
		}

		$valid = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$valid[] = (int) $obj->rowid;
		}

		return $valid;
	}

	private function buildInitials($name, $fallback)
	{
		$source = trim($name) !== '' ? trim($name) : trim($fallback);
		$parts = preg_split('/\s+/', $source);
		$initials = '';
		foreach ($parts as $part) {
			if ($part !== '') {
				$initials .= strtoupper(substr($part, 0, 1));
			}
			if (strlen($initials) >= 2) {
				break;
			}
		}
		return $initials !== '' ? $initials : 'U';
	}
}
