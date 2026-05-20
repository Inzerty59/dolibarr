<?php

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';

class InterfaceTicketsEmail extends DolibarrTriggers
{
	public function __construct($db)
	{
		$this->db = $db;
		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = 'ticket';
		$this->description = 'Custom ticket email notifications based on Dolibarr email templates.';
		$this->version = self::VERSIONS['prod'];
		$this->picto = 'ticket';
	}

	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (!isModEnabled('ticket') || empty($object->element) || $object->element !== 'ticket') {
			return 0;
		}

		$events = $this->getEventsForAction($action, $object);
		if (empty($events)) {
			return 0;
		}

		$error = 0;
		foreach ($events as $eventCode => $recipientType) {
			$template = $this->fetchTemplateForEvent($eventCode, (int) $object->fk_project);
			if (empty($template)) {
				dol_syslog('Ticket email trigger skipped '.$eventCode.' for ticket '.((int) $object->id).': no active template', LOG_DEBUG);
				continue;
			}

			$sendto = $this->getRecipients($recipientType, $object);
			if ($sendto === '') {
				dol_syslog('Ticket email trigger skipped '.$eventCode.' for ticket '.((int) $object->id).': no recipient for '.$recipientType, LOG_WARNING);
				continue;
			}

			$result = $this->sendTemplateMail($template, $sendto, $eventCode, $object, $user, $langs);
			if ($result < 0) {
				$error++;
			}
		}

        dol_syslog(
            'TICKET EMAIL TRIGGER: '.$action,
            LOG_DEBUG
        );
		return $error ? -1 : 1;
	}

	private function getEventsForAction($action, $object)
	{
		if ($action === 'TICKET_CREATE') {
			$events = array(
				'ticket_create_customer' => 'customer',
			);

			if (!empty($object->fk_user_assign)) {
				$events['ticket_assigned_internal'] = 'assigned_user';
			}

			return $events;
		}

		if ($action === 'TICKET_ASSIGNED') {
			return array(
				'ticket_assigned_customer' => 'customer',
				'ticket_assigned_internal' => 'assigned_user',
			);
		}

		if ($action === 'TICKET_CLOSE') {
			return array(
				'ticket_resolved_customer' => 'customer',
			);
		}

		if ($action === 'TICKET_MODIFY') {
			if ($this->isInProgressStatusTransition($object)) {
				return array(
					'ticket_in_progress_customer' => 'customer',
				);
			}

			if ($this->isClosedStatusTransition($object)) {
				return array(
					'ticket_resolved_customer' => 'customer',
				);
			}
		}

		return array();
	}

	private function isInProgressStatusTransition($object)
	{
		return $this->isStatusTransitionTo($object, 3);
	}

	private function isClosedStatusTransition($object)
	{
		$newStatus = $this->getNewStatus($object);

		if (!in_array($newStatus, array(8, 9), true)) {
			return false;
		}

		$oldStatus = $this->getOldStatus($object);

		return !in_array($oldStatus, array(8, 9), true);
	}

	private function isStatusTransitionTo($object, $expectedStatus)
	{
		$newStatus = $this->getNewStatus($object);
		if ($newStatus !== (int) $expectedStatus) {
			return false;
		}

		$oldStatus = $this->getOldStatus($object);

		return $oldStatus !== (int) $expectedStatus;
	}

	private function getNewStatus($object)
	{
		$newStatus = null;
		if (isset($object->context['newstatus'])) {
			$newStatus = (int) $object->context['newstatus'];
		} elseif (isset($object->status)) {
			$newStatus = (int) $object->status;
		} elseif (isset($object->fk_statut)) {
			$newStatus = (int) $object->fk_statut;
		}

		return $newStatus;
	}

	private function getOldStatus($object)
	{
		$oldStatus = null;
		if (!empty($object->oldcopy) && isset($object->oldcopy->status)) {
			$oldStatus = (int) $object->oldcopy->status;
		} elseif (!empty($object->oldcopy) && isset($object->oldcopy->fk_statut)) {
			$oldStatus = (int) $object->oldcopy->fk_statut;
		}

		return $oldStatus;
	}

	private function fetchTemplateForEvent($eventCode, $projectId)
	{
		global $conf;

		$sql = "SELECT cet.rowid, cet.topic, cet.content, cet.email_from, cet.email_tocc, cet.email_tobcc";
		$sql .= " FROM ".MAIN_DB_PREFIX."tickets_email_template as tet";
		$sql .= " INNER JOIN ".MAIN_DB_PREFIX."c_email_templates as cet ON cet.rowid = tet.fk_email_template";
		$sql .= " WHERE tet.entity = ".((int) $conf->entity);
		$sql .= " AND tet.active = 1";
		$sql .= " AND cet.active = 1";
		$sql .= " AND tet.event_code = '".$this->db->escape($eventCode)."'";
		$sql .= " AND (tet.fk_project = ".((int) $projectId)." OR tet.fk_project IS NULL)";
		$sql .= " ORDER BY tet.fk_project DESC";
		$sql .= $this->db->plimit(1);

		$resql = $this->db->query($sql);
		if ($resql && $this->db->num_rows($resql) > 0) {
			return $this->db->fetch_object($resql);
		}

		return null;
	}

	private function getRecipients($recipientType, $object)
	{
		if ($recipientType === 'assigned_user') {
			if (empty($object->fk_user_assign)) {
				return '';
			}

			$assignedUser = new User($this->db);
			if ($assignedUser->fetch((int) $object->fk_user_assign) <= 0 || empty($assignedUser->email)) {
				dol_syslog('Ticket email trigger cannot notify assigned user '.((int) $object->fk_user_assign).' for ticket '.((int) $object->id).': user not found or empty email', LOG_WARNING);
				return '';
			}

			return $assignedUser->email;
		}

		return $this->getCustomerRecipients($object);
	}

	private function getCustomerRecipients($object)
	{
		$contactId = $this->getContextContactId($object);

		if ($contactId === -3) {
			return '';
		}

		if ($contactId === -2) {
			return $this->getAllThirdpartyContactEmails($object);
		}

		if ($contactId > 0) {
			return $this->getContactEmail($contactId, $object);
		}

		return $this->getFirstLinkedThirdpartyContactEmail($object);
	}

	private function getContextContactId($object)
	{
		if (isset($object->context['contact_id'])) {
			return (int) $object->context['contact_id'];
		}

		return 0;
	}

	private function getContactEmail($contactId, $object)
	{
		$contact = new Contact($this->db);
		if ($contact->fetch((int) $contactId) <= 0) {
			return '';
		}

		if (empty($contact->email) || empty($contact->statut)) {
			return '';
		}

		if (!empty($object->fk_soc) && (int) $contact->socid !== (int) $object->fk_soc) {
			dol_syslog('Ticket email trigger skipped contact '.((int) $contactId).' because it does not belong to ticket thirdparty '.((int) $object->fk_soc), LOG_WARNING);
			return '';
		}

		return $contact->email;
	}

	private function getFirstLinkedThirdpartyContactEmail($object)
	{
		$linkedContacts = $object->listeContact(-1, 'external');

		if (!empty($linkedContacts) && is_array($linkedContacts)) {
			foreach ($linkedContacts as $contact) {
				if (!empty($contact['email']) && !empty($contact['statuscontact'])) {
					return $contact['email'];
				}
			}
		}

		return '';
	}

	private function getAllThirdpartyContactEmails($object)
	{
		if (empty($object->fk_soc)) {
			return '';
		}

		$emails = array();

		$sql = "SELECT DISTINCT email";
		$sql .= " FROM ".MAIN_DB_PREFIX."socpeople";
		$sql .= " WHERE fk_soc = ".((int) $object->fk_soc);
		$sql .= " AND statut = 1";
		$sql .= " AND email IS NOT NULL AND email <> ''";

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$emails[] = $obj->email;
			}
		}

		return implode(', ', $emails);
	}

	private function sendTemplateMail($template, $sendto, $eventCode, $object, User $user, Translate $langs)
	{
		global $conf, $mysoc;

		$substitutionarray = getCommonSubstitutionArray($langs, 0, null, $object);
		$this->completeTicketSubstitutions($substitutionarray, $object, $eventCode, $user);
		complete_substitutions_array($substitutionarray, $langs, $object);

		$subject = make_substitutions((string) $template->topic, $substitutionarray, $langs);
		$message = make_substitutions((string) $template->content, $substitutionarray, $langs);

		$from = !empty($template->email_from) ? $template->email_from : getDolGlobalString('TICKET_NOTIFICATION_EMAIL_FROM');
		if (empty($from)) {
			$from = getDolGlobalString('MAIN_MAIL_EMAIL_FROM');
		}
		if (empty($from) && !empty($mysoc->email)) {
			$from = $mysoc->email;
		}

		$trackid = 'tic'.((int) $object->id);
		$mailfile = new CMailFile($subject, $sendto, $from, $message, array(), array(), array(), !empty($template->email_tocc) ? $template->email_tocc : '', !empty($template->email_tobcc) ? $template->email_tobcc : '', 0, -1, '', '', $trackid, '', 'ticket');

		if ($mailfile->error) {
			$this->errors[] = $mailfile->error;
			return -1;
		}

		$result = $mailfile->sendfile();
		if (!$result) {
			$this->errors = array_merge($this->errors, $mailfile->errors);
			return -1;
		}

		return 1;
	}

	private function completeTicketSubstitutions(&$substitutionarray, $object, $eventCode, User $user)
	{
		global $langs;

		$assignedName = '';
		if (!empty($object->fk_user_assign)) {
			$assignedUser = new User($this->db);
			if ($assignedUser->fetch((int) $object->fk_user_assign) > 0) {
				$assignedName = $assignedUser->getFullName($langs);
			}
		}

		$clientName = '';
		if (!empty($object->fk_soc)) {
			$object->fetch_thirdparty();
			$clientName = $object->thirdparty->name;
		}

		$resolution = '';
		if (isset($object->resolution)) {
			$resolution = (string) $object->resolution;
		}

		$ticketDate = dol_print_date($object->datec ?: dol_now(), 'dayhour');
		$ticketUrl = dol_buildpath('/ticket/card.php', 2).'?track_id='.urlencode($object->track_id);

		$substitutionarray['__TICKET_REF__'] = $object->ref;
		$substitutionarray['__TICKET_TRACK_ID__'] = $object->track_id;
		$substitutionarray['__TICKET_SUBJECT__'] = $object->subject;
		$substitutionarray['__TICKET_CLIENT__'] = $clientName;
		$substitutionarray['__TICKET_DATE__'] = $ticketDate;
		$substitutionarray['__TICKET_ASSIGNED__'] = $assignedName;
		$substitutionarray['__TICKET_RESOLUTION__'] = $resolution;
		$substitutionarray['__TICKET_EVENT__'] = $eventCode;
		$substitutionarray['__TICKET_URL__'] = $ticketUrl;
		$substitutionarray['__SENDER_FULLNAME__'] = $user->getFullName($langs);

		$substitutionarray['{ref}'] = $object->ref;
		$substitutionarray['{track_id}'] = $object->track_id;
		$substitutionarray['{label}'] = $object->subject;
		$substitutionarray['{subject}'] = $object->subject;
		$substitutionarray['{thirdparty_name}'] = $clientName;
		$substitutionarray['{client}'] = $clientName;
		$substitutionarray['{date}'] = $ticketDate;
		$substitutionarray['{assigned}'] = $assignedName;
		$substitutionarray['{resolution}'] = $resolution;
		$substitutionarray['{url}'] = $ticketUrl;
	}
}
