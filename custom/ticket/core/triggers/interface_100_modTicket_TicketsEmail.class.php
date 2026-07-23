<?php

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';

/**
 * Send only the custom ticket notifications that replace blocked native emails
 * or fill missing Dolibarr behaviours.
 */
class InterfaceTicketsEmail extends DolibarrTriggers
{
	const CONTACT_CHOICE_ALL_LINKED = -2;
	const CONTACT_CHOICE_NONE = -3;
	const CONTACT_LINK_STATUS_ALL = -1;
	const CONTACT_TYPE_ASSIGNED_USER = 'SUPPORTTEC';
	const MAIL_BODY_IS_HTML_AUTO = -1;
	const TICKET_STATUS_IN_PROGRESS = 3;
	const TRIGGER_RESULT_ERROR = -1;
	const TRIGGER_RESULT_NONE = 0;
	const TRIGGER_RESULT_OK = 1;

	/** @var Translate */
	private $langs;

	/** @var array<string,bool> */
	private static $sentAssignmentNotifications = array();

	public function __construct($db)
	{
		$this->db = $db;
		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = 'ticket';
		$this->description = 'Custom minimal ticket email notifications.';
		$this->version = self::VERSIONS['prod'];
		$this->picto = 'ticket';
	}

	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (!isModEnabled('ticket') || empty($object->element) || $object->element !== 'ticket') {
			return self::TRIGGER_RESULT_NONE;
		}

		$this->langs = $langs;
		$this->langs->load('ticket');
		$this->langs->load('ticketcustom@ticket');

		$sent = 0;
		$error = 0;

		if ($action === 'TICKET_CREATE') {
			if (!empty($object->notify_tiers_at_create)) {
				$this->collectMailResult($this->sendCustomerMail($object, 'create'), $sent, $error);
			}

			if (!empty($object->fk_user_assign)) {
				$this->collectMailResult($this->sendAssignedUserMail($object), $sent, $error);
			}
		}

		if ($action === 'TICKET_ASSIGNED') {
			$this->collectMailResult($this->sendAssignedUserMail($object), $sent, $error);
		}

		if ($action === 'TICKET_CLOSE') {
			$this->collectMailResult($this->sendCustomerMail($object, 'resolved'), $sent, $error);
		}

		if ($action === 'TICKET_MODIFY') {
			if ($this->isAssignedUserChanged($object)) {
				$this->collectMailResult($this->sendAssignedUserMail($object), $sent, $error);
			}

			if ($this->isStatusTransitionTo($object, self::TICKET_STATUS_IN_PROGRESS)) {
				$this->collectMailResult($this->sendCustomerMail($object, 'in_progress'), $sent, $error);
			}
		}

		if ($error > 0) {
			$this->reportMailErrors();
		}

		return $sent > 0 ? self::TRIGGER_RESULT_OK : self::TRIGGER_RESULT_NONE;
	}

	/**
	 * Aggregate send results so a real mail failure is not hidden by skipped recipients. 
	 */
	private function collectMailResult($result, &$sent, &$error)
	{
		if ($result < 0) {
			$error++;
			return;
		}

		if ($result > 0) {
			$sent++;
		}
	}

	/**
	 * Send a customer-facing ticket email to the selected/linked ticket recipients.
	 */
	private function sendCustomerMail($object, $event)
	{
		$recipients = $this->getCustomerRecipients($object);
		if (empty($recipients)) {
			dol_syslog('Custom ticket email skipped '.$event.' for ticket '.((int) $object->id).': no ticket contact recipient', LOG_DEBUG);
			return self::TRIGGER_RESULT_NONE;
		}

		return $this->sendCustomerMailToRecipients($recipients, $event, $object);
	}

	/**
	 * Send the internal assignment notification to the current assigned user.
	 */
	private function sendAssignedUserMail($object)
	{
		$assignedUser = $this->getAssignedUser($object);
		if (empty($assignedUser) || empty($assignedUser->email)) {
			dol_syslog('Custom ticket email skipped assignment for ticket '.((int) $object->id).': assigned user has no email', LOG_WARNING);
			return self::TRIGGER_RESULT_NONE;
		}

		$notificationKey = $this->getAssignmentNotificationKey($object, $assignedUser);
		if (!empty(self::$sentAssignmentNotifications[$notificationKey])) {
			dol_syslog('Custom ticket email skipped duplicate assignment notification for ticket '.((int) $object->id).' and user '.((int) $assignedUser->id), LOG_DEBUG);
			return self::TRIGGER_RESULT_NONE;
		}

		$template = $this->buildAssignedUserTemplate($object, $assignedUser);

		$result = $this->sendMail($assignedUser->email, $template['subject'], $template['body'], $object);
		if ($result > 0) {
			self::$sentAssignmentNotifications[$notificationKey] = true;
			$this->syncAssignedUserSupportContact($object, $assignedUser);
		}

		return $result;
	}

	/**
	 * Build a request-local key to avoid duplicate assignment emails.
	 */
	private function getAssignmentNotificationKey($object, User $assignedUser)
	{
		return ((int) $object->id).':'.((int) $assignedUser->id);
	}

	/**
	 * Resolve customer recipients according to the close/create popup choice.
	 *
	 * @return array<string,array{email:string,name:string}> Lowercase email => recipient data
	 */
	private function getCustomerRecipients($object)
	{
		$contactId = $this->getContextContactId($object);

		if ($contactId === self::CONTACT_CHOICE_NONE) {
			return array();
		}

		if ($contactId === self::CONTACT_CHOICE_ALL_LINKED) {
			return $this->getLinkedTicketContactEmails($object, true);
		}

		if ($contactId > 0) {
			$emails = array();
			$this->addContactEmail($emails, $contactId, $object);
			return $emails;
		}

		return $this->getLinkedTicketContactEmails($object);
	}

	/**
	 * Return the contact selector value posted by Dolibarr during create/close.
	 */
	private function getContextContactId($object)
	{
		if (isset($object->context['contact_id'])) {
			return (int) $object->context['contact_id'];
		}

		return 0;
	}

	/**
	 * Add a precise external contact if it belongs to the ticket thirdparty.
	 */
	private function addContactEmail(&$emails, $contactId, $object)
	{
		$contact = new Contact($this->db);
		if ($contact->fetch((int) $contactId) <= 0 || empty($contact->email) || empty($contact->statut)) {
			return;
		}

		if (!empty($object->fk_soc) && (int) $contact->socid !== (int) $object->fk_soc) {
			dol_syslog('Custom ticket email skipped contact '.((int) $contactId).' for ticket '.((int) $object->id).': contact is not on ticket thirdparty', LOG_WARNING);
			return;
		}

		$this->addEmail($emails, $contact->email, $this->getContactName($contact));
	}

	/**
	 * Return external contacts linked to the ticket. For the "all associated"
	 * choice, also include the assigned user explicitly without adding every
	 * internal linked contact to the customer-facing notification.
	 *
	 * @return array<string,array{email:string,name:string}> Lowercase email => recipient data
	 */
	private function getLinkedTicketContactEmails($object, $includeAssignedUser = false)
	{
		$emails = array();
		$linkedContacts = $object->listeContact(self::CONTACT_LINK_STATUS_ALL, 'external');

		$this->addLinkedContactEmails($emails, $linkedContacts);
		if ($includeAssignedUser) {
			$this->addAssignedUserEmail($emails, $object);
		}

		return $emails;
	}

	/**
	 * Add the current assigned user email when the user selected all associated
	 * recipients.
	 */
	private function addAssignedUserEmail(&$emails, $object)
	{
		$assignedUser = $this->getAssignedUser($object);
		if (empty($assignedUser) || empty($assignedUser->email)) {
			return;
		}

		$this->addEmail($emails, $assignedUser->email, $assignedUser->getFullName($this->langs));
	}

	/**
	 * Add emails returned by Ticket::listeContact().
	 */
	private function addLinkedContactEmails(&$emails, $linkedContacts)
	{
		if (empty($linkedContacts) || !is_array($linkedContacts)) {
			return;
		}

		foreach ($linkedContacts as $contact) {
			if (empty($contact['email']) || empty($contact['statuscontact'])) {
				continue;
			}

			$this->addEmail($emails, $contact['email'], $this->getLinkedContactName($contact));
		}
	}

	/**
	 * Add one email to a recipient set, using lowercase keys to avoid duplicates.
	 */
	private function addEmail(&$emails, $email, $name = '')
	{
		$email = trim((string) $email);
		if ($email === '') {
			return;
		}

		$key = strtolower($email);
		if (empty($emails[$key])) {
			$emails[$key] = array(
				'email' => $email,
				'name' => trim((string) $name),
			);
			return;
		}

		if (empty($emails[$key]['name']) && trim((string) $name) !== '') {
			$emails[$key]['name'] = trim((string) $name);
		}
	}

	/**
	 * Build a display name from a Ticket::listeContact() row.
	 */
	private function getLinkedContactName($contact)
	{
		$parts = array();

		foreach (array('firstname', 'lastname', 'nom', 'name', 'login') as $field) {
			if (!empty($contact[$field])) {
				$parts[] = $contact[$field];
			}
		}

		return trim(implode(' ', array_unique($parts)));
	}

	/**
	 * Build a display name from a Contact object.
	 */
	private function getContactName(Contact $contact)
	{
		$parts = array();

		if (!empty($contact->firstname)) {
			$parts[] = $contact->firstname;
		}
		if (!empty($contact->lastname)) {
			$parts[] = $contact->lastname;
		}

		return trim(implode(' ', $parts));
	}

	/**
	 * Detect a status change to a target status.
	 */
	private function isStatusTransitionTo($object, $expectedStatus)
	{
		$newStatus = $this->getNewStatus($object);
		if ($newStatus !== (int) $expectedStatus) {
			return false;
		}

		$oldStatus = $this->getOldStatus($object);

		return $oldStatus !== (int) $expectedStatus;
	}

	/**
	 * Detect assignment changes done through generic ticket update flows.
	 */
	private function isAssignedUserChanged($object)
	{
		$newAssignedUserId = $this->getAssignedUserId($object);
		if ($newAssignedUserId <= 0) {
			return false;
		}

		if (!empty($object->oldcopy) && property_exists($object->oldcopy, 'fk_user_assign')) {
			$oldAssignedUserId = (int) $object->oldcopy->fk_user_assign;
			return $newAssignedUserId !== $oldAssignedUserId;
		}

		return !$this->isAssignedUserLinkedAsSupportContact($object, $newAssignedUserId);
	}

	/**
	 * Read the new ticket status from context or object fields.
	 */
	private function getNewStatus($object)
	{
		if (isset($object->context['newstatus'])) {
			return (int) $object->context['newstatus'];
		}

		if (isset($object->status)) {
			return (int) $object->status;
		}

		if (isset($object->fk_statut)) {
			return (int) $object->fk_statut;
		}

		return null;
	}

	/**
	 * Read the previous ticket status from Dolibarr oldcopy.
	 */
	private function getOldStatus($object)
	{
		if (!empty($object->oldcopy) && isset($object->oldcopy->status)) {
			return (int) $object->oldcopy->status;
		}

		if (!empty($object->oldcopy) && isset($object->oldcopy->fk_statut)) {
			return (int) $object->oldcopy->fk_statut;
		}

		return null;
	}

	/**
	 * Build the short customer templates for create, in progress and close.
	 */
	private function buildCustomerTemplate($event, $object, $recipientName = '')
	{
		$greeting = $this->buildCustomerGreeting($object, $recipientName);
		$summary = $this->buildTicketSummary($object, true);

		if ($event === 'create') {
			return array(
				'subject' => $this->trans('TicketCustomSubjectCreate', '[Inzerty] Nouveau ticket cree - Ref %s', $object->ref),
				'body' => $greeting
					.'<p>'.$this->trans('TicketCustomBodyCreateIntro', 'Nous avons bien recu votre demande.').'</p>'
					.$summary
					.'<p>'.$this->trans('TicketCustomBodyCreateFooter', 'Notre equipe reviendra vers vous des que possible.').'</p>',
			);
		}

		if ($event === 'in_progress') {
			return array(
				'subject' => $this->trans('TicketCustomSubjectInProgress', '[Inzerty] Votre ticket %s est en cours de traitement', $object->ref),
				'body' => $greeting
					.'<p>'.$this->trans('TicketCustomBodyInProgressIntro', 'Votre ticket est maintenant en cours de traitement.').'</p>'
					.$summary
					.'<p>'.$this->trans('TicketCustomBodyInProgressFooter', 'Nous vous tiendrons informe de son avancement.').'</p>',
			);
		}

		if ($event === 'resolved') {
			$resolution = $this->getResolutionMessage($object);

			return array(
				'subject' => $this->trans('TicketCustomSubjectResolved', '[Inzerty] Ticket ferme - Ref %s', $object->ref),
				'body' => $greeting
					.'<p>'.$this->trans('TicketCustomBodyResolvedIntro', 'Votre ticket a ete ferme.').'</p>'
					.$summary
					.$resolution
					.'<p>'.$this->trans('TicketCustomBodyResolvedFooter', 'Vous pouvez repondre a ce message si un complement est necessaire.').'</p>',
			);
		}

		return array();
	}

	/**
	 * Build the customer greeting with the thirdparty name when available.
	 */
	private function buildCustomerGreeting($object, $recipientName = '')
	{
		$recipientName = trim((string) $recipientName);
		if ($recipientName !== '') {
			return '<p>'.$this->escape($this->trans('TicketCustomGreetingThirdparty', 'Bonjour %s,', $recipientName)).'</p>';
		}

		$thirdpartyName = $this->getThirdpartyName($object);
		if (trim($thirdpartyName) !== '') {
			return '<p>'.$this->escape($this->trans('TicketCustomGreetingThirdparty', 'Bonjour %s,', $thirdpartyName)).'</p>';
		}

		return '<p>'.$this->trans('TicketCustomGreetingGeneric', 'Bonjour,').'</p>';
	}

	/**
	 * Build the internal assignment template.
	 */
	private function buildAssignedUserTemplate($object, User $assignedUser)
	{
		$assignedName = $assignedUser->getFullName($this->langs);

		return array(
			'subject' => $this->trans('TicketCustomSubjectAssigned', '[Inzerty] Ticket %s assigne', $object->ref),
			'body' => '<p>'.$this->escape($this->trans('TicketCustomGreetingThirdparty', 'Bonjour %s,', $assignedName)).'</p>'
				.'<p>'.$this->trans('TicketCustomBodyAssignedIntro', 'Un ticket vous a ete attribue.').'</p>'
				.$this->buildTicketSummary($object, false)
				.'<p>'.$this->trans('TicketCustomBodyAssignedFooter', 'Merci de le prendre en charge depuis Dolibarr.').'</p>',
		);
	}

	/**
	 * Build the common ticket summary used in all templates.
	 */
	private function buildTicketSummary($object, $includeAssignedUser)
	{
		$rows = array(
			$this->trans('TicketCustomFieldTicket', 'Ticket') => $object->ref,
			$this->trans('TicketCustomFieldSubject', 'Sujet') => $object->subject,
			$this->trans('TicketCustomFieldSeverity', 'Sévérité') => $this->getSeverityLabel($object),
			$this->trans('TicketCustomFieldDate', 'Date') => $this->getTicketDate($object),
		);

		if ($includeAssignedUser) {
			$rows[$this->trans('TicketCustomFieldAssignedTo', 'Assigne a')] = $this->getAssignedUserName($object);
		}

		$html = '<p>';
		foreach ($rows as $label => $value) {
			if ((string) $value === '') {
				continue;
			}

			$html .= '<strong>'.$this->escape($label).' :</strong> '.$this->escape($value).'<br>';
		}

		return $html.'</p>';
	}

	/**
	 * Return the ticket creation date in the business timezone.
	 */
	private function getTicketDate($object)
	{
		$timestamp = !empty($object->datec) ? (int) $object->datec : dol_now();
		$timezone = $this->getEmailTimezone();

		try {
			$date = new DateTime('@'.$timestamp);
			$date->setTimezone(new DateTimeZone($timezone));
			return $date->format('d/m/Y H:i');
		} catch (Exception $e) {
			dol_syslog('Custom ticket email date timezone invalid: '.$timezone.'. Falling back to Dolibarr date formatting.', LOG_WARNING);
			return dol_print_date($timestamp, 'dayhour');
		}
	}

	/**
	 * Resolve the timezone used for email dates.
	 */
	private function getEmailTimezone()
	{
		$timezone = getDolGlobalString('TICKET_CUSTOM_EMAIL_TIMEZONE');
		if (!empty($timezone)) {
			return $timezone;
		}

		$timezone = getDolGlobalString('MAIN_SERVER_TZ');
		if (!empty($timezone) && $timezone !== 'auto') {
			return $timezone;
		}

		$timezone = getDolGlobalString('MAIN_DOLIBARR_USER_TIMEZONE');
		if (!empty($timezone)) {
			return $timezone;
		}

		if (!empty($_SESSION['dol_tz_string'])) {
			return $_SESSION['dol_tz_string'];
		}

		return 'Europe/Paris';
	}

	/**
	 * Fetch the ticket thirdparty name for customer greetings.
	 */
	private function getThirdpartyName($object)
	{
		if (empty($object->fk_soc)) {
			return '';
		}

		if (empty($object->thirdparty)) {
			$object->fetch_thirdparty();
		}

		return !empty($object->thirdparty->name) ? $object->thirdparty->name : '';
	}

	/**
	 * Fetch the assigned user display name.
	 */
	private function getAssignedUserName($object)
	{
		$assignedUser = $this->getAssignedUser($object);
		if (empty($assignedUser)) {
			return '';
		}

		return $assignedUser->getFullName($this->langs);
	}

	/**
	 * Check whether the assigned user is already linked with Dolibarr's support
	 * technician contact type. This is used as a fallback when TICKET_MODIFY is
	 * fired without oldcopy, which happens from the standard ticket edit form.
	 */
	private function isAssignedUserLinkedAsSupportContact($object, $assignedUserId)
	{
		$linkedContacts = $object->listeContact(self::CONTACT_LINK_STATUS_ALL, 'internal', 0, self::CONTACT_TYPE_ASSIGNED_USER);
		if (empty($linkedContacts) || !is_array($linkedContacts)) {
			return false;
		}

		foreach ($linkedContacts as $contact) {
			if (!empty($contact['id']) && (int) $contact['id'] === (int) $assignedUserId) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Keep the internal support contact aligned after an assignment made through
	 * the generic edit form. The dedicated Dolibarr assign action already does
	 * the same synchronization outside this trigger.
	 */
	private function syncAssignedUserSupportContact($object, User $assignedUser)
	{
		$linkedContacts = $object->listeContact(self::CONTACT_LINK_STATUS_ALL, 'internal', 0, self::CONTACT_TYPE_ASSIGNED_USER);
		$hasCurrentAssignedUser = false;

		if (!empty($linkedContacts) && is_array($linkedContacts)) {
			foreach ($linkedContacts as $contact) {
				if (!empty($contact['id']) && (int) $contact['id'] === (int) $assignedUser->id) {
					$hasCurrentAssignedUser = true;
					continue;
				}

				if (!empty($contact['rowid']) && $object->delete_contact((int) $contact['rowid'], 1) < 0) {
					dol_syslog('Custom ticket email could not unlink previous assigned support contact '.((int) $contact['rowid']).' for ticket '.((int) $object->id), LOG_WARNING);
				}
			}
		}

		if (!$hasCurrentAssignedUser && $object->add_contact((int) $assignedUser->id, self::CONTACT_TYPE_ASSIGNED_USER, 'internal', 1) < 0) {
			dol_syslog('Custom ticket email could not link assigned support contact '.((int) $assignedUser->id).' for ticket '.((int) $object->id), LOG_WARNING);
		}
	}

	/**
	 * Fetch the assigned user, falling back to a fresh DB lookup when the close
	 * trigger object does not carry fk_user_assign.
	 */
	private function getAssignedUser($object)
	{
		$assignedUserId = $this->getAssignedUserId($object);
		if (empty($assignedUserId)) {
			return null;
		}

		$assignedUser = new User($this->db);
		if ($assignedUser->fetch($assignedUserId) <= 0) {
			return null;
		}

		return $assignedUser;
	}

	/**
	 * Resolve the current assigned user id from object fields or directly from DB.
	 */
	private function getAssignedUserId($object)
	{
		if (!empty($object->fk_user_assign)) {
			return (int) $object->fk_user_assign;
		}

		if (empty($object->id)) {
			return 0;
		}

		$sql = "SELECT fk_user_assign FROM ".MAIN_DB_PREFIX."ticket WHERE rowid = ".((int) $object->id);
		$resql = $this->db->query($sql);
		if (!$resql || !$this->db->num_rows($resql)) {
			return 0;
		}

		$row = $this->db->fetch_object($resql);

		return !empty($row->fk_user_assign) ? (int) $row->fk_user_assign : 0;
	}

	/**
	 * Return the translated severity label when possible.
	 */
	private function getSeverityLabel($object)
	{
		if (!empty($object->severity_label)) {
			return $object->severity_label;
		}

		if (!empty($object->severity_code)) {
			$translated = $this->langs->trans('TicketSeverityShort'.$object->severity_code);
			if ($translated !== 'TicketSeverityShort'.$object->severity_code) {
				return $translated;
			}

			return $object->severity_code;
		}

		return '';
	}

	/**
	 * Return the optional resolution block.
	 */
	private function getResolutionMessage($object)
	{
		if (empty($object->resolution)) {
			return '';
		}

		return '<p><strong>'.$this->escape($this->trans('TicketCustomFieldResolution', 'Resolution')).' :</strong><br>'.nl2br($this->escape($object->resolution)).'</p>';
	}

	/**
	 * Send one email per recipient so every resolved recipient is explicit.
	 */
	private function sendCustomerMailToRecipients($recipients, $event, $object)
	{
		$sent = 0;
		$error = 0;

		foreach ($recipients as $recipient) {
			$template = $this->buildCustomerTemplate($event, $object, $recipient['name']);
			if (empty($template)) {
				continue;
			}

			$result = $this->sendMail($recipient['email'], $template['subject'], $template['body'], $object);
			if ($result < 0) {
				$error++;
			} elseif ($result > 0) {
				$sent++;
			}
		}

		if ($error > 0) {
			return self::TRIGGER_RESULT_ERROR;
		}

		return $sent > 0 ? self::TRIGGER_RESULT_OK : self::TRIGGER_RESULT_NONE;
	}

	/**
	 * Send a single HTML email through Dolibarr mailer.
	 */
	private function sendMail($sendto, $subject, $message, $object)
	{
		global $conf, $mysoc;

		$from = $this->getSenderEmail();

		$trackid = 'tic'.((int) $object->id);
		$mailfile = new CMailFile($subject, $sendto, $from, $message, array(), array(), array(), '', '', 0, self::MAIL_BODY_IS_HTML_AUTO, '', '', $trackid, '', 'ticket');

		if ($mailfile->error) {
			$this->errors = array_merge((array) $this->errors, array($mailfile->error));
			dol_syslog('Custom ticket email not sent to '.$sendto.' for ticket '.((int) $object->id).': '.$mailfile->error, LOG_ERR);
			return self::TRIGGER_RESULT_ERROR;
		}

		$result = $mailfile->sendfile();
		if (!$result) {
			$mailErrors = !empty($mailfile->errors) ? $mailfile->errors : array($mailfile->error);
			$this->errors = array_merge((array) $this->errors, (array) $mailErrors);
			dol_syslog('Custom ticket email not sent to '.$sendto.' for ticket '.((int) $object->id).': '.implode(' | ', (array) $mailErrors), LOG_ERR);
			return self::TRIGGER_RESULT_ERROR;
		}

		return self::TRIGGER_RESULT_OK;
	}

	/**
	 * Surface mail failures to the user without rolling back the ticket action.
	 */
	private function reportMailErrors()
	{
		$mailErrors = array_values(array_filter(array_unique((array) $this->errors)));
		$message = $this->trans('TicketCustomMailSendFailureWarning', "L'action a ete enregistree, mais au moins un email de notification n'a pas pu etre envoye.");

		if (function_exists('setEventMessages')) {
			setEventMessages($message, $mailErrors, 'warnings');
			return;
		}

		dol_syslog('Custom ticket email warning: '.$message.' '.implode(' | ', $mailErrors), LOG_WARNING);
	}

	/**
	 * Return a sender compatible with authenticated SMTP providers.
	 */
	private function getSenderEmail()
	{
		global $mysoc;

		$smtpLogin = getDolGlobalString('MAIN_MAIL_SMTPS_ID');
		if (!empty($smtpLogin) && filter_var($smtpLogin, FILTER_VALIDATE_EMAIL)) {
			return $smtpLogin;
		}

		$from = getDolGlobalString('TICKET_NOTIFICATION_EMAIL_FROM');
		if (empty($from)) {
			$from = getDolGlobalString('MAIN_MAIL_EMAIL_FROM');
		}
		if (empty($from) && !empty($mysoc->email)) {
			$from = $mysoc->email;
		}

		return $from;
	}

	/**
	 * Translate a custom key with a hardcoded fallback.
	 */
	private function trans($key, $fallback)
	{
		$args = func_get_args();
		$args = array_slice($args, 2);

		if (!empty($args)) {
			$translated = call_user_func_array(array($this->langs, 'transnoentitiesnoconv'), array_merge(array($key), $args));
			if ($translated === $key) {
				return vsprintf($fallback, $args);
			}

			return $translated;
		}

		$translated = $this->langs->transnoentitiesnoconv($key);

		if ($translated === $key) {
			$translated = $fallback;
		}

		return $translated;
	}

	/**
	 * Escape dynamic values before injecting them into HTML bodies.
	 */
	private function escape($value)
	{
		return dol_escape_htmltag((string) $value);
	}
}
