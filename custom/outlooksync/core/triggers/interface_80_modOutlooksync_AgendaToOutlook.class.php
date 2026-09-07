<?php

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/outlooksync/class/outlooksyncgraphclient.class.php';

class InterfaceAgendaToOutlook extends DolibarrTriggers
{
	public function __construct($db)
	{
		$this->db = $db;
		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = 'outlooksync';
		$this->description = 'Synchronise manually created Dolibarr agenda events to Outlook.';
		$this->version = self::VERSIONS['prod'];
		$this->picto = 'calendar';
	}

	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (!isModEnabled('outlooksync') || empty($object->element) || !in_array($object->element, array('action', 'actioncomm'), true)) {
			return 0;
		}

		if (!in_array($action, array('ACTION_CREATE', 'ACTION_MODIFY', 'ACTION_DELETE'), true)) {
			return 0;
		}

		if (!$this->isManualDolibarrEvent($object)) {
			return 0;
		}

		$client = new OutlooksyncGraphClient($this->db);
		if ($action === 'ACTION_DELETE') {
			return $this->deleteOutlookEvents($object, $client, $conf);
		}

		return $this->upsertOutlookEvents($object, $client, $conf);
	}

	private function upsertOutlookEvents($object, OutlooksyncGraphClient $client, Conf $conf)
	{
		$organizer = $this->getOrganizer($object);
		if (empty($organizer) || empty($organizer->email)) {
			dol_syslog('Outlooksync skipped action '.((int) $object->id).': organizer has no email', LOG_WARNING);
			return 0;
		}
		if (!$this->isMailboxAllowed($organizer->email)) {
			dol_syslog('Outlooksync skipped action '.((int) $object->id).' for organizer '.$organizer->email.': mailbox not allowed', LOG_WARNING);
			return 0;
		}

		$payload = $this->buildGraphPayload($object, (int) $organizer->id);
		$mapping = $this->getMapping((int) $object->id, (int) $organizer->id, $conf);
		$updatePayload = $this->buildGraphUpdatePayload($payload);
		$contentHash = $this->hashPayloadWithoutAttendees($updatePayload);
		$attendeesHash = $this->hashAttendees($payload['attendees']);
		if (!empty($mapping['outlook_event_id'])) {
			if (!empty($mapping['content_hash']) && $mapping['content_hash'] === $contentHash && $mapping['attendees_hash'] !== $attendeesHash) {
				$result = $client->updateEvent($organizer->email, $mapping['outlook_event_id'], array('attendees' => $payload['attendees']));
			} else {
				$result = $client->updateEvent($organizer->email, $mapping['outlook_event_id'], $updatePayload);
			}
		} else {
			$result = $client->createEvent($organizer->email, $payload);
		}

		if (!empty($result['error'])) {
			$this->saveMappingError((int) $object->id, (int) $organizer->id, $organizer->email, $result['error'], $conf);
			dol_syslog('Outlooksync error action '.((int) $object->id).' organizer '.((int) $organizer->id).': '.$result['error'], LOG_ERR);
			return -1;
		}

		if (empty($mapping['outlook_event_id']) && !empty($result['id'])) {
			$this->saveMapping((int) $object->id, (int) $organizer->id, $organizer->email, $result['id'], $contentHash, $attendeesHash, $conf);
		} else {
			$this->updateMappingHashes((int) $object->id, (int) $organizer->id, $contentHash, $attendeesHash, $conf);
		}

		return 0;
	}

	private function deleteMappingsForUnassignedUsers($actionId, array $assignedUsers, OutlooksyncGraphClient $client, Conf $conf)
	{
		$currentUserIds = array();
		foreach ($assignedUsers as $assignedUser) {
			$currentUserIds[(int) $assignedUser->id] = true;
		}

		$error = 0;
		foreach ($this->getMappings($actionId, $conf) as $mapping) {
			if (!empty($currentUserIds[(int) $mapping['fk_user']])) {
				continue;
			}

			$result = $client->deleteEvent($mapping['user_email'], $mapping['outlook_event_id']);
			if (!empty($result['error']) && strpos($result['error'], 'HTTP 404') === false) {
				$error++;
				$this->saveMappingError($actionId, (int) $mapping['fk_user'], $mapping['user_email'], $result['error'], $conf);
				continue;
			}
			$this->deleteMapping((int) $mapping['rowid']);
		}

		return $error;
	}

	private function deleteOutlookEvents($object, OutlooksyncGraphClient $client, Conf $conf)
	{
		$mappings = $this->getMappings((int) $object->id, $conf);
		$error = 0;
		foreach ($mappings as $mapping) {
			$result = $client->deleteEvent($mapping['user_email'], $mapping['outlook_event_id']);
			if (!empty($result['error']) && strpos($result['error'], 'HTTP 404') === false) {
				$error++;
				$this->saveMappingError((int) $object->id, (int) $mapping['fk_user'], $mapping['user_email'], $result['error'], $conf);
				dol_syslog('Outlooksync delete error action '.((int) $object->id).' user '.((int) $mapping['fk_user']).': '.$result['error'], LOG_ERR);
				continue;
			}
			$this->deleteMapping((int) $mapping['rowid']);
		}

		return $error > 0 ? -1 : 0;
	}

	private function buildGraphPayload($object, $organizerId)
	{
		$typeLabel = method_exists($object, 'getTypeLabel') ? $object->getTypeLabel(0) : $object->type_code;
		$typeLabel = html_entity_decode((string) $typeLabel, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$label = html_entity_decode((string) $object->label, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$subject = trim($typeLabel.' - '.$label);
		$location = trim((string) $object->location);
		$hasLocation = ($location !== '');
		$end = !empty($object->datef) ? $object->datef : ((int) $object->datep + 3600);

		$payload = array(
			'subject' => $subject,
			'body' => array(
				'contentType' => 'HTML',
				'content' => dol_htmlcleanlastbr((string) $object->note_private),
			),
			'start' => array(
				'dateTime' => gmdate('Y-m-d\TH:i:s', (int) $object->datep),
				'timeZone' => 'UTC',
			),
			'end' => array(
				'dateTime' => gmdate('Y-m-d\TH:i:s', $end),
				'timeZone' => 'UTC',
			),
			'location' => array(
				'displayName' => $hasLocation ? $location : 'Réunion Microsoft Teams',
			),
			'isOnlineMeeting' => true,
			'onlineMeetingProvider' => 'teamsForBusiness',
			'attendees' => $this->getAttendees($object, $organizerId),
		);

		if (!empty($object->fulldayevent)) {
			$payload['isAllDay'] = true;
		}

		return $payload;
	}

	private function buildGraphUpdatePayload(array $payload)
	{
		unset($payload['body']);
		return $payload;
	}

	private function hashPayloadWithoutAttendees(array $payload)
	{
		unset($payload['attendees']);
		return $this->hashArray($payload);
	}

	private function hashAttendees(array $attendees)
	{
		return $this->hashArray($attendees);
	}

	private function hashArray(array $data)
	{
		$this->recursiveKsort($data);
		return hash('sha256', json_encode($data));
	}

	private function recursiveKsort(array &$data)
	{
		foreach ($data as &$value) {
			if (is_array($value)) {
				$this->recursiveKsort($value);
			}
		}
		unset($value);
		ksort($data);
	}

	private function isManualDolibarrEvent($object)
	{
		if (!empty($object->email_msgid) || !empty($object->email_subject)) {
			return false;
		}
		if (!empty($object->elementtype) || !empty($object->fk_element) || !empty($object->elementid)) {
			return false;
		}
		if (empty($object->datep) || empty($object->label)) {
			return false;
		}
		return true;
	}

	private function getOrganizer($object)
	{
		$id = !empty($object->user_creation_id) ? (int) $object->user_creation_id : 0;
		if ($id <= 0 && !empty($object->fk_user_author)) {
			$id = (int) $object->fk_user_author;
		}
		if ($id <= 0 && !empty($object->userownerid)) {
			$id = (int) $object->userownerid;
		}
		if ($id <= 0) {
			return null;
		}

		$tmpuser = new User($this->db);
		return ($tmpuser->fetch($id) > 0) ? $tmpuser : null;
	}

	private function getAttendees($object, $organizerId)
	{
		$attendees = array();
		foreach ($this->getAssignedUsers($object) as $assignedUser) {
			if ((int) $assignedUser->id === (int) $organizerId || empty($assignedUser->email)) {
				continue;
			}
			$attendees[] = array(
				'emailAddress' => array(
					'address' => $assignedUser->email,
					'name' => $assignedUser->getFullName($GLOBALS['langs']),
				),
				'type' => 'required',
			);
		}

		return array_merge($attendees, $this->getContactAttendees($object));
	}

	private function getAssignedUsers($object)
	{
		$users = array();
		$ids = array();
		if (!empty($object->userassigned) && is_array($object->userassigned)) {
			foreach ($object->userassigned as $val) {
				$id = is_array($val) ? (int) $val['id'] : (int) $val;
				if ($id > 0) {
					$ids[$id] = true;
				}
			}
		}
		if (empty($ids) && !empty($object->userownerid)) {
			$ids[(int) $object->userownerid] = true;
		}

		foreach (array_keys($ids) as $id) {
			$tmpuser = new User($this->db);
			if ($tmpuser->fetch($id) > 0 && !empty($tmpuser->email)) {
				$users[] = $tmpuser;
			}
		}
		return $users;
	}

	private function getContactAttendees($object)
	{
		$attendees = array();
		$ids = array();
		if (!empty($object->socpeopleassigned) && is_array($object->socpeopleassigned)) {
			foreach ($object->socpeopleassigned as $val) {
				$id = is_array($val) ? (int) $val['id'] : (int) $val;
				if ($id > 0) {
					$ids[$id] = true;
				}
			}
		}
		if (empty($ids) && !empty($object->contact_id)) {
			$ids[(int) $object->contact_id] = true;
		}

		foreach (array_keys($ids) as $id) {
			$contact = new Contact($this->db);
			if ($contact->fetch($id) > 0 && !empty($contact->email)) {
				$attendees[] = array(
					'emailAddress' => array(
						'address' => $contact->email,
						'name' => trim($contact->firstname.' '.$contact->lastname),
					),
					'type' => 'required',
				);
			}
		}

		return $attendees;
	}

	private function isMailboxAllowed($email)
	{
		$allowed = trim(getDolGlobalString('OUTLOOKSYNC_ALLOWED_MAILBOXES'));
		if ($allowed === '') {
			return true;
		}
		$mailboxes = array_map('trim', explode(',', strtolower($allowed)));
		return in_array(strtolower($email), $mailboxes, true);
	}

	private function getMapping($actionId, $userId, Conf $conf)
	{
		$sql = 'SELECT rowid, outlook_event_id, content_hash, attendees_hash FROM '.MAIN_DB_PREFIX.'outlooksync_event';
		$sql .= ' WHERE entity = '.((int) $conf->entity).' AND fk_actioncomm = '.$actionId.' AND fk_user = '.$userId;
		$resql = $this->db->query($sql);
		if ($resql && ($obj = $this->db->fetch_object($resql))) {
			return array(
				'rowid' => (int) $obj->rowid,
				'outlook_event_id' => $obj->outlook_event_id,
				'content_hash' => $obj->content_hash,
				'attendees_hash' => $obj->attendees_hash,
			);
		}
		return array();
	}

	private function getMappings($actionId, Conf $conf)
	{
		$mappings = array();
		$sql = 'SELECT rowid, fk_user, user_email, outlook_event_id FROM '.MAIN_DB_PREFIX.'outlooksync_event';
		$sql .= ' WHERE entity = '.((int) $conf->entity).' AND fk_actioncomm = '.$actionId;
		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$mappings[] = array(
					'rowid' => (int) $obj->rowid,
					'fk_user' => (int) $obj->fk_user,
					'user_email' => $obj->user_email,
					'outlook_event_id' => $obj->outlook_event_id,
				);
			}
		}
		return $mappings;
	}

	private function saveMapping($actionId, $userId, $email, $eventId, $contentHash, $attendeesHash, Conf $conf)
	{
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'outlooksync_event';
		$sql .= ' (entity, fk_actioncomm, fk_user, user_email, outlook_event_id, content_hash, attendees_hash, datec, last_error)';
		$sql .= " VALUES (".((int) $conf->entity).", ".$actionId.", ".$userId.", '".$this->db->escape($email)."', '".$this->db->escape($eventId)."', '".$this->db->escape($contentHash)."', '".$this->db->escape($attendeesHash)."', '".$this->db->idate(dol_now())."', null)";
		$sql .= " ON DUPLICATE KEY UPDATE user_email = '".$this->db->escape($email)."', outlook_event_id = '".$this->db->escape($eventId)."', content_hash = '".$this->db->escape($contentHash)."', attendees_hash = '".$this->db->escape($attendeesHash)."', last_error = null";
		$this->db->query($sql);
	}

	private function updateMappingHashes($actionId, $userId, $contentHash, $attendeesHash, Conf $conf)
	{
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'outlooksync_event';
		$sql .= " SET content_hash = '".$this->db->escape($contentHash)."', attendees_hash = '".$this->db->escape($attendeesHash)."', last_error = null";
		$sql .= ' WHERE entity = '.((int) $conf->entity).' AND fk_actioncomm = '.$actionId.' AND fk_user = '.$userId;
		$this->db->query($sql);
	}

	private function saveMappingError($actionId, $userId, $email, $error, Conf $conf)
	{
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'outlooksync_event';
		$sql .= ' (entity, fk_actioncomm, fk_user, user_email, outlook_event_id, datec, last_error)';
		$sql .= " VALUES (".((int) $conf->entity).", ".$actionId.", ".$userId.", '".$this->db->escape($email)."', '', '".$this->db->idate(dol_now())."', '".$this->db->escape($error)."')";
		$sql .= " ON DUPLICATE KEY UPDATE user_email = '".$this->db->escape($email)."', last_error = '".$this->db->escape($error)."'";
		$this->db->query($sql);
	}

	private function clearMappingError($actionId, $userId, Conf $conf)
	{
		$sql = 'UPDATE '.MAIN_DB_PREFIX.'outlooksync_event SET last_error = null';
		$sql .= ' WHERE entity = '.((int) $conf->entity).' AND fk_actioncomm = '.$actionId.' AND fk_user = '.$userId;
		$this->db->query($sql);
	}

	private function deleteMapping($rowid)
	{
		$sql = 'DELETE FROM '.MAIN_DB_PREFIX.'outlooksync_event WHERE rowid = '.((int) $rowid);
		$this->db->query($sql);
	}
}
