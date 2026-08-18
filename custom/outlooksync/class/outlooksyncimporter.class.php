<?php

require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/outlooksync/class/outlooksyncgraphclient.class.php';

class OutlooksyncImporter
{
	/** @var DoliDB */
	public $db;

	/** @var string */
	public $error = '';

	/** @var array<int,string> */
	public $errors = array();

	public function __construct($db)
	{
		$this->db = $db;
	}

	public function syncFromOutlook()
	{
		global $conf;

		if (!isModEnabled('outlooksync')) {
			return 0;
		}

		$mailboxes = $this->getMailboxes();
		if (empty($mailboxes)) {
			dol_syslog('Outlooksync import skipped: OUTLOOKSYNC_ALLOWED_MAILBOXES is empty', LOG_WARNING);
			return 0;
		}

		$client = new OutlooksyncGraphClient($this->db);
		$error = 0;
		foreach ($mailboxes as $mailbox) {
			$result = $this->syncMailbox($mailbox, $client, $conf);
			if ($result < 0) {
				$error++;
			}
		}

		return $error > 0 ? -1 : 0;
	}

	private function syncMailbox($mailbox, OutlooksyncGraphClient $client, Conf $conf)
	{
		$deltaLink = $this->getDeltaLink($mailbox, $conf);
		$error = 0;

		do {
			$response = $client->getCalendarViewDelta($mailbox, $deltaLink);
			if (!empty($response['error'])) {
				$this->saveStateError($mailbox, $response['error'], $conf);
				dol_syslog('Outlooksync import error for '.$mailbox.': '.$response['error'], LOG_ERR);
				return -1;
			}

			foreach (!empty($response['value']) ? $response['value'] : array() as $event) {
				if (!empty($event['@removed'])) {
					$result = $this->deleteDolibarrEvent($mailbox, $event, $conf);
				} else {
					$result = $this->upsertDolibarrEvent($mailbox, $event, $conf);
				}
				if ($result < 0) {
					$error++;
				}
			}

			$deltaLink = '';
			if (!empty($response['@odata.nextLink'])) {
				$deltaLink = $response['@odata.nextLink'];
			} elseif (!empty($response['@odata.deltaLink'])) {
				$this->saveDeltaLink($mailbox, $response['@odata.deltaLink'], $conf);
			}
		} while ($deltaLink !== '');

		return $error > 0 ? -1 : 0;
	}

	private function upsertDolibarrEvent($mailbox, array $event, Conf $conf)
	{
		if (empty($event['id']) || !$this->isMailboxOrganizer($mailbox, $event)) {
			return 0;
		}
		if (!empty($event['isCancelled'])) {
			return 0;
		}

		$organizer = $this->findUserByEmail($mailbox);
		if (empty($organizer)) {
			dol_syslog('Outlooksync import skipped '.$event['id'].': organizer '.$mailbox.' not found in Dolibarr users', LOG_WARNING);
			return 0;
		}

		$mapping = $this->getMappingByOutlookId($mailbox, $event['id'], $conf);
		$action = new ActionComm($this->db);
		if (!empty($mapping['fk_actioncomm'])) {
			if ($action->fetch((int) $mapping['fk_actioncomm']) <= 0) {
				$mapping = array();
			}
		}

		$this->fillActionFromOutlook($action, $event, $organizer);
		$userassigned = $this->resolveAssignedUsers($event, (int) $organizer->id);
		$contacts = $this->resolveContacts($event, $userassigned['emails']);
		$action->userassigned = $userassigned['users'];
		$action->socpeopleassigned = $contacts['contacts'];
		if (!empty($contacts['first_socid'])) {
			$action->socid = $contacts['first_socid'];
		}
		if (!empty($contacts['first_contact_id'])) {
			$action->contact_id = $contacts['first_contact_id'];
		}

		$this->db->begin();
		if (empty($mapping)) {
			$result = $action->create($organizer, 1);
		} else {
			$result = $action->update($organizer, 1);
		}

		if ($result < 0) {
			$this->db->rollback();
			$error = 'Dolibarr action save failed for Outlook event '.$event['id'].': '.$action->error.' '.implode(',', $action->errors);
			$this->saveMappingError(0, (int) $organizer->id, $mailbox, $event['id'], $error, $conf);
			dol_syslog('Outlooksync import '.$error, LOG_ERR);
			return -1;
		}

		$actionId = empty($mapping) ? (int) $result : (int) $action->id;
		$this->saveMapping($actionId, (int) $organizer->id, $mailbox, $event['id'], $conf);
		$this->db->commit();
		return 0;
	}

	private function deleteDolibarrEvent($mailbox, array $event, Conf $conf)
	{
		if (empty($event['id'])) {
			return 0;
		}

		$mapping = $this->getMappingByOutlookId($mailbox, $event['id'], $conf);
		if (empty($mapping['fk_actioncomm'])) {
			return 0;
		}

		$organizer = $this->findUserByEmail($mailbox);
		if (empty($organizer)) {
			return 0;
		}

		$action = new ActionComm($this->db);
		if ($action->fetch((int) $mapping['fk_actioncomm']) <= 0) {
			$this->deleteMapping((int) $mapping['rowid']);
			return 0;
		}

		$this->db->begin();
		$result = $action->delete($organizer, 1);
		if ($result < 0) {
			$this->db->rollback();
			dol_syslog('Outlooksync import delete failed for action '.((int) $mapping['fk_actioncomm']).' Outlook event '.$event['id'], LOG_ERR);
			return -1;
		}
		$this->deleteMapping((int) $mapping['rowid']);
		$this->db->commit();
		return 0;
	}

	private function fillActionFromOutlook(ActionComm $action, array $event, User $organizer)
	{
		$action->label = html_entity_decode((string) ($event['subject'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$action->type_code = 'AC_RDV';
		$action->type_id = 5;
		$action->datep = $this->graphDateToTimestamp($event['start'] ?? array());
		$action->datef = $this->graphDateToTimestamp($event['end'] ?? array());
		$action->fulldayevent = !empty($event['isAllDay']) ? 1 : 0;
		$action->location = (string) ($event['location']['displayName'] ?? '');
		$action->note_private = $this->cleanOutlookBody((string) ($event['body']['content'] ?? ''));
		$action->percentage = 0;
		$action->priority = 0;
		$action->transparency = 0;
		$action->userownerid = (int) $organizer->id;
	}

	private function graphDateToTimestamp(array $date)
	{
		if (empty($date['dateTime'])) {
			return dol_now();
		}
		$value = $date['dateTime'];
		if (strpos($value, 'Z') === false && strpos($value, '+') === false) {
			$value .= 'Z';
		}
		$timestamp = strtotime($value);
		return $timestamp > 0 ? $timestamp : dol_now();
	}

	private function cleanOutlookBody($body)
	{
		$body = preg_replace('/<a[^>]+href="https:\/\/teams\.microsoft\.com\/[^"]+"[^>]*>.*?<\/a>/is', '', $body);
		return trim($body);
	}

	private function resolveAssignedUsers(array $event, $organizerId)
	{
		$users = array($organizerId => array('id' => $organizerId, 'transparency' => 0, 'mandatory' => 1));
		$userEmails = array();
		foreach ($this->getAttendeeEmails($event) as $email) {
			$user = $this->findUserByEmail($email);
			if (!empty($user)) {
				$users[(int) $user->id] = array('id' => (int) $user->id, 'transparency' => 0, 'mandatory' => 1);
				$userEmails[strtolower($email)] = true;
			}
		}

		return array('users' => $users, 'emails' => $userEmails);
	}

	private function resolveContacts(array $event, array $userEmails)
	{
		$contacts = array();
		$firstSocid = 0;
		$firstContactId = 0;
		foreach ($this->getAttendeeEmails($event) as $email) {
			if (!empty($userEmails[strtolower($email)])) {
				continue;
			}
			$contact = $this->findContactByEmail($email);
			if (!empty($contact)) {
				$contacts[(int) $contact->id] = array('id' => (int) $contact->id);
				if (empty($firstContactId)) {
					$firstContactId = (int) $contact->id;
					$firstSocid = (int) $contact->socid;
				}
			}
		}

		return array('contacts' => $contacts, 'first_socid' => $firstSocid, 'first_contact_id' => $firstContactId);
	}

	private function getAttendeeEmails(array $event)
	{
		$emails = array();
		foreach (!empty($event['attendees']) ? $event['attendees'] : array() as $attendee) {
			$email = strtolower(trim((string) ($attendee['emailAddress']['address'] ?? '')));
			if ($email !== '') {
				$emails[$email] = $email;
			}
		}
		return array_values($emails);
	}

	private function isMailboxOrganizer($mailbox, array $event)
	{
		$organizerEmail = strtolower(trim((string) ($event['organizer']['emailAddress']['address'] ?? '')));
		return $organizerEmail === '' || $organizerEmail === strtolower($mailbox);
	}

	private function findUserByEmail($email)
	{
		$user = new User($this->db);
		return ($user->fetch(0, '', '', 0, -1, $email) > 0) ? $user : null;
	}

	private function findContactByEmail($email)
	{
		$contact = new Contact($this->db);
		return ($contact->fetch(0, null, '', $email) > 0) ? $contact : null;
	}

	private function getMailboxes()
	{
		$allowed = trim(getDolGlobalString('OUTLOOKSYNC_ALLOWED_MAILBOXES'));
		if ($allowed === '') {
			return array();
		}

		$mailboxes = array();
		foreach (explode(',', strtolower($allowed)) as $email) {
			$email = trim($email);
			if ($email !== '') {
				$mailboxes[$email] = $email;
			}
		}
		return array_values($mailboxes);
	}

	private function getMappingByOutlookId($mailbox, $eventId, Conf $conf)
	{
		$sql = 'SELECT rowid, fk_actioncomm, fk_user FROM '.MAIN_DB_PREFIX.'outlooksync_event';
		$sql .= " WHERE entity = ".((int) $conf->entity)." AND user_email = '".$this->db->escape($mailbox)."' AND outlook_event_id = '".$this->db->escape($eventId)."'";
		$resql = $this->db->query($sql);
		if ($resql && ($obj = $this->db->fetch_object($resql))) {
			return array('rowid' => (int) $obj->rowid, 'fk_actioncomm' => (int) $obj->fk_actioncomm, 'fk_user' => (int) $obj->fk_user);
		}
		return array();
	}

	private function saveMapping($actionId, $userId, $mailbox, $eventId, Conf $conf)
	{
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'outlooksync_event';
		$sql .= ' (entity, fk_actioncomm, fk_user, user_email, outlook_event_id, datec, last_error)';
		$sql .= " VALUES (".((int) $conf->entity).", ".$actionId.", ".$userId.", '".$this->db->escape($mailbox)."', '".$this->db->escape($eventId)."', '".$this->db->idate(dol_now())."', null)";
		$sql .= " ON DUPLICATE KEY UPDATE fk_actioncomm = ".$actionId.", fk_user = ".$userId.", last_error = null";
		$this->db->query($sql);
	}

	private function saveMappingError($actionId, $userId, $mailbox, $eventId, $error, Conf $conf)
	{
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'outlooksync_event';
		$sql .= ' (entity, fk_actioncomm, fk_user, user_email, outlook_event_id, datec, last_error)';
		$sql .= " VALUES (".((int) $conf->entity).", ".$actionId.", ".$userId.", '".$this->db->escape($mailbox)."', '".$this->db->escape($eventId)."', '".$this->db->idate(dol_now())."', '".$this->db->escape($error)."')";
		$sql .= " ON DUPLICATE KEY UPDATE last_error = '".$this->db->escape($error)."'";
		$this->db->query($sql);
	}

	private function deleteMapping($rowid)
	{
		$this->db->query('DELETE FROM '.MAIN_DB_PREFIX.'outlooksync_event WHERE rowid = '.((int) $rowid));
	}

	private function getDeltaLink($mailbox, Conf $conf)
	{
		$sql = 'SELECT delta_link FROM '.MAIN_DB_PREFIX.'outlooksync_state';
		$sql .= " WHERE entity = ".((int) $conf->entity)." AND user_email = '".$this->db->escape($mailbox)."'";
		$resql = $this->db->query($sql);
		if ($resql && ($obj = $this->db->fetch_object($resql))) {
			return (string) $obj->delta_link;
		}
		return '';
	}

	private function saveDeltaLink($mailbox, $deltaLink, Conf $conf)
	{
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'outlooksync_state (entity, user_email, delta_link, datec, last_error)';
		$sql .= " VALUES (".((int) $conf->entity).", '".$this->db->escape($mailbox)."', '".$this->db->escape($deltaLink)."', '".$this->db->idate(dol_now())."', null)";
		$sql .= " ON DUPLICATE KEY UPDATE delta_link = '".$this->db->escape($deltaLink)."', last_error = null";
		$this->db->query($sql);
	}

	private function saveStateError($mailbox, $error, Conf $conf)
	{
		$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'outlooksync_state (entity, user_email, delta_link, datec, last_error)';
		$sql .= " VALUES (".((int) $conf->entity).", '".$this->db->escape($mailbox)."', null, '".$this->db->idate(dol_now())."', '".$this->db->escape($error)."')";
		$sql .= " ON DUPLICATE KEY UPDATE last_error = '".$this->db->escape($error)."'";
		$this->db->query($sql);
	}
}
