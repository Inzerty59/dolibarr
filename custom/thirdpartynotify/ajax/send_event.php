<?php

define('NOTOKENRENEWAL', 1);
require_once __DIR__.'/../class/bootstrap.inc.php';
require_once __DIR__.'/../class/thirdpartynotify.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 * @var Societe $mysoc
 */

header('Content-Type: application/json; charset=UTF-8');

function thirdpartynotify_send_json($payload, $status = 200)
{
	http_response_code($status);
	print json_encode($payload);
	exit;
}

$token = GETPOST('token', 'alpha');
if (empty($token) || !hash_equals((string) currentToken(), (string) $token)) {
	thirdpartynotify_send_json(array('success' => false, 'error' => 'Bad token'), 403);
}

$socid = GETPOSTINT('socid');
$actioncommId = GETPOSTINT('actioncomm_id');
if ($socid <= 0 || $actioncommId <= 0) {
	thirdpartynotify_send_json(array('success' => false, 'error' => 'Bad parameters'), 400);
}

if (!$user->hasRight('societe', 'lire')) {
	thirdpartynotify_send_json(array('success' => false, 'error' => 'Forbidden'), 403);
}
if (!$user->hasRight('agenda', 'myactions', 'read') && !$user->hasRight('agenda', 'allactions', 'read')) {
	thirdpartynotify_send_json(array('success' => false, 'error' => 'Forbidden'), 403);
}

$thirdparty = new Societe($db);
if ($thirdparty->fetch($socid) <= 0) {
	thirdpartynotify_send_json(array('success' => false, 'error' => 'Third party not found'), 404);
}
restrictedArea($user, 'societe', $socid, '&societe');

$event = new ActionComm($db);
if ($event->fetch($actioncommId) <= 0) {
	thirdpartynotify_send_json(array('success' => false, 'error' => 'Event not found'), 404);
}
if ((int) $event->socid !== (int) $socid) {
	thirdpartynotify_send_json(array('success' => false, 'error' => 'Event does not belong to this third party'), 403);
}
if ($event->type === 'systemauto' || $event->type_code === 'AC_OTH_AUTO') {
	thirdpartynotify_send_json(array('success' => false, 'error' => 'Cet evenement automatique ne peut pas etre notifie'), 403);
}
if (!$user->hasRight('agenda', 'allactions', 'read')) {
	$isOwnEvent = ((int) $event->authorid === (int) $user->id) || ((int) $event->userownerid === (int) $user->id);
	$isAssigned = is_array($event->userassigned) && array_key_exists((int) $user->id, $event->userassigned);
	if (!$isOwnEvent && !$isAssigned) {
		thirdpartynotify_send_json(array('success' => false, 'error' => 'Forbidden'), 403);
	}
}

$service = new ThirdpartyNotify($db);
$recipients = $service->getRecipientEmails($conf->entity);
if ($recipients === -1) {
	dol_syslog('ThirdpartyNotify recipients error: '.$db->lasterror(), LOG_ERR);
	thirdpartynotify_send_json(array('success' => false, 'error' => 'Erreur technique'), 500);
}

$from = '';
$replyto = '';
if (!empty($user->email) && isValidEmail($user->email)) {
	$replyto = $user->getFullName($langs).' <'.$user->email.'>';
}

if (getDolGlobalString('MAIN_MAIL_SMTPS_ID') && isValidEmail(getDolGlobalString('MAIN_MAIL_SMTPS_ID'))) {
	$from = getDolGlobalString('MAIN_MAIL_SMTPS_ID');
} elseif (getDolGlobalString('MAIN_MAIL_FORCE_FROM') && isValidEmail(getDolGlobalString('MAIN_MAIL_FORCE_FROM'))) {
	$from = getDolGlobalString('MAIN_MAIL_FORCE_FROM');
} elseif (getDolGlobalString('MAIN_MAIL_EMAIL_FROM') && isValidEmail(getDolGlobalString('MAIN_MAIL_EMAIL_FROM'))) {
	$from = getDolGlobalString('MAIN_MAIL_EMAIL_FROM');
} elseif (!empty($mysoc->email) && isValidEmail($mysoc->email)) {
	$from = $mysoc->email;
}
if ($from === '' && !empty($recipients)) {
	thirdpartynotify_send_json(array('success' => false, 'error' => 'No valid sender email configured'), 500);
}

$contacts = array();
if (is_array($event->socpeopleassigned)) {
	foreach (array_keys($event->socpeopleassigned) as $contactId) {
		$contactId = (int) $contactId;
		if ($contactId <= 0) {
			continue;
		}
		$contact = new Contact($db);
		if ($contact->fetch($contactId) > 0) {
			$line = $contact->getFullName($langs);
			if (!empty($contact->phone_pro)) {
				$line .= ' - '.$contact->phone_pro;
			}
			if (!empty($contact->email)) {
				$line .= ' - '.$contact->email;
			}
			$contacts[] = $line;
		}
	}
}

$kanbanCount = $service->createKanbanCardsForSelectedUsers($conf->entity, $event, $thirdparty, $contacts);
if ($kanbanCount < 0) {
	dol_syslog('ThirdpartyNotify kanban creation error: '.$db->lasterror(), LOG_ERR);
	thirdpartynotify_send_json(array('success' => false, 'error' => 'Erreur technique'), 500);
}
if (empty($recipients)) {
	thirdpartynotify_send_json(array(
		'success' => true,
		'sent' => 0,
		'kanbanCards' => $kanbanCount,
		'errors' => array(),
		'message' => 'Carte(s) Kanban creee(s). Aucun email envoye car aucun destinataire avec email valide.',
		'newToken' => currentToken(),
	));
}

$subject = '['.$thirdparty->name.'] Evenement #'.$event->id.' a consulter';
if (!empty($event->label)) {
	$subject = '['.$thirdparty->name.'] Evenement #'.$event->id.' - '.$event->label;
}

$message = '<p>Bonjour,</p>';
$message .= '<p>Un evenement manuel lie au tiers <strong>'.dol_escape_htmltag($thirdparty->name).'</strong> vous a ete transmis.</p>';
$message .= '<table border="0" cellpadding="6" cellspacing="0">';
$message .= '<tr><td><strong>Reference evenement</strong></td><td>#'.((int) $event->id).'</td></tr>';
$message .= '<tr><td><strong>Tiers</strong></td><td>'.dol_escape_htmltag($thirdparty->name).'</td></tr>';
$message .= '<tr><td><strong>Evenement</strong></td><td>'.dol_escape_htmltag($event->label ?: ('#'.$event->id)).'</td></tr>';
if (!empty($event->datep)) {
	$message .= '<tr><td><strong>Date debut</strong></td><td>'.dol_print_date($event->datep, 'dayhour', 'tzuserrel').'</td></tr>';
}
if (!empty($event->datef) && (int) $event->datef !== (int) $event->datep) {
	$message .= '<tr><td><strong>Date fin</strong></td><td>'.dol_print_date($event->datef, 'dayhour', 'tzuserrel').'</td></tr>';
}
if (!empty($contacts)) {
	$message .= '<tr><td><strong>Contact(s) concerne(s)</strong></td><td>'.dol_escape_htmltag(implode(', ', $contacts)).'</td></tr>';
} else {
	$message .= '<tr><td><strong>Contact(s) concerne(s)</strong></td><td>Aucun contact selectionne</td></tr>';
}
$message .= '</table>';
$message .= '<p>Envoye depuis Dolibarr par '.dol_escape_htmltag($user->getFullName($langs)).'.</p>';

$sent = 0;
$errors = array();
foreach ($recipients as $recipient) {
	$mail = new CMailFile($subject, $recipient, $from, $message, array(), array(), array(), '', '', 0, 1, '', '', 'tpnotify'.$thirdparty->id.'-'.$event->id, '', 'standard', $replyto);
	if (!empty($mail->error)) {
		$errors[] = $recipient.': '.$mail->error;
		continue;
	}
	if ($mail->sendfile()) {
		$sent++;
	} else {
		$errors[] = $recipient.': '.$mail->error;
	}
}

if ($sent <= 0) {
	thirdpartynotify_send_json(array('success' => false, 'error' => implode("\n", $errors)), 500);
}

thirdpartynotify_send_json(array(
	'success' => true,
	'sent' => $sent,
	'kanbanCards' => $kanbanCount,
	'errors' => $errors,
	'message' => $sent.' email(s) envoye(s)',
	'newToken' => currentToken(),
));
