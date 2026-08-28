<?php

require_once __DIR__.'/mondaygraphclient.class.php';
require_once __DIR__.'/mondayinboundemailprocessor.class.php';

class MondayGraphInboundSync
{
    const STATE_TABLE = 'monday_graph_sync_state';

    private $db;
    private $graphClient;
    private $processor;
    public $error = '';
    public $errors = array();
    public $output = '';

    public function __construct($db, MondayGraphClient $graphClient = null, MondayInboundEmailProcessor $processor = null)
    {
        $this->db = $db;
        $this->graphClient = $graphClient ?: new MondayGraphClient(
            getDolGlobalString('MONDAY_GRAPH_TENANT_ID', ''),
            getDolGlobalString('MONDAY_GRAPH_CLIENT_ID', ''),
            $this->getClientSecret()
        );
        $this->processor = $processor ?: new MondayInboundEmailProcessor($db);
    }

    public function runScheduledSync()
    {
        if (!getDolGlobalInt('MONDAY_GRAPH_INBOUND_ENABLE')) {
            $this->output = 'Microsoft Graph inbound sync disabled.';
            return 0;
        }

        if (!$this->ensureStateSchema()) {
            return -1;
        }

        if (!$this->graphClient->isConfigured()) {
            $this->error = 'Microsoft Graph inbound sync is not configured.';
            dol_syslog(__CLASS__.' '.$this->error, LOG_WARNING);
            $this->output = $this->error;
            return 0;
        }

        $mailboxes = $this->getRecruiterMailboxes();
        if (empty($mailboxes)) {
            $this->error = 'MONDAY_GRAPH_RECRUITER_MAILBOXES is empty.';
            dol_syslog(__CLASS__.' '.$this->error, LOG_WARNING);
            $this->output = $this->error;
            return 0;
        }

        $processed = 0;
        $mailboxOutputs = array();
        foreach ($mailboxes as $mailbox) {
            $result = $this->syncMailbox($mailbox);
            $processed += (int) $result['processed'];
            $mailboxOutputs[] = $mailbox.': '.$result['processed'].' imported';
            if (!empty($result['error'])) {
                $this->errors[] = $mailbox.': '.$result['error'];
            }
        }

        if (!empty($this->errors)) {
            $this->error = implode('; ', $this->errors);
            $this->output = $this->error;
            return -1;
        }

        $this->output = 'Microsoft Graph inbound sync OK: '.$processed.' imported. '.implode('; ', $mailboxOutputs);
        return 0;
    }

    private function syncMailbox($mailbox)
    {
        $mailbox = strtolower(trim((string) $mailbox));
        $state = $this->getState($mailbox);
        $isInitialSync = empty($state['delta_link']);
        $url = !empty($state['delta_link']) ? $state['delta_link'] : $this->buildInitialDeltaUrl($mailbox);
        $processed = 0;
        $newDeltaLink = '';

        do {
            $payload = $this->graphClient->get($url);
            if ($payload === false) {
                $this->saveError($mailbox, $this->graphClient->error);
                return array('processed' => $processed, 'error' => $this->graphClient->error);
            }

            foreach (($payload['value'] ?? array()) as $message) {
                if (!is_array($message) || !$this->messageHasTrackingBcc($message)) {
                    continue;
                }
                if ($isInitialSync && !$this->isInsideBootstrapWindow($message)) {
                    continue;
                }

                $result = $this->processor->processMessage($this->mapGraphMessageToProcessorParameters($mailbox, $message, $this->fetchGraphAttachments($mailbox, $message)));
                if (!empty($result['handled'])) {
                    $processed++;
                }
            }

            if (!empty($payload['@odata.nextLink'])) {
                $url = $payload['@odata.nextLink'];
                continue;
            }

            $newDeltaLink = (string) ($payload['@odata.deltaLink'] ?? '');
            $url = '';
        } while ($url !== '');

        if ($newDeltaLink !== '') {
            $this->saveSuccess($mailbox, $newDeltaLink);
        }

        return array('processed' => $processed, 'error' => '');
    }

    private function buildInitialDeltaUrl($mailbox)
    {
        $select = 'id,internetMessageId,subject,body,from,toRecipients,ccRecipients,bccRecipients,sentDateTime,hasAttachments';
        return '/users/'.rawurlencode($mailbox).'/mailFolders/sentitems/messages/delta?$select='.$select.'&changeType=created';
    }

    private function mapGraphMessageToProcessorParameters($mailbox, array $message, array $attachments = array())
    {
        $sentDateTime = (string) ($message['sentDateTime'] ?? '');
        $timestamp = $sentDateTime !== '' ? strtotime($sentDateTime) : false;

        return array(
            'collector_id' => 0,
            'source_key' => 'graph:'.$mailbox,
            'uid' => (string) ($message['id'] ?? ''),
            'message_id' => (string) ($message['internetMessageId'] ?? ''),
            'subject' => (string) ($message['subject'] ?? ''),
            'messagetext' => $this->extractGraphBody($message),
            'from' => $this->formatGraphRecipients(array($message['from'] ?? array())),
            'to' => $this->formatGraphRecipients($message['toRecipients'] ?? array()),
            'cc' => $this->formatGraphRecipients($message['ccRecipients'] ?? array()),
            'bcc' => $this->formatGraphRecipients($message['bccRecipients'] ?? array()),
            'header' => array(
                'Date' => $timestamp !== false ? date(DATE_RFC2822, $timestamp) : '',
                'Message-ID' => (string) ($message['internetMessageId'] ?? ''),
                'Bcc' => $this->formatGraphRecipients($message['bccRecipients'] ?? array())
            ),
            'overview' => (object) array(
                'date' => $timestamp !== false ? $timestamp : dol_now()
            ),
            'attachments' => $attachments
        );
    }

    private function fetchGraphAttachments($mailbox, array $message)
    {
        if (empty($message['hasAttachments']) || empty($message['id'])) {
            return array();
        }

        $attachments = array();
        $messageId = (string) $message['id'];
        $url = '/users/'.rawurlencode($mailbox).'/messages/'.rawurlencode($messageId).'/attachments?$select=id,name,contentType,size,isInline';
        do {
            $payload = $this->graphClient->get($url);
            if ($payload === false) {
                dol_syslog(__CLASS__.' unable to read Graph attachments for mailbox='.$mailbox.' error='.$this->graphClient->error, LOG_WARNING);
                return $attachments;
            }

            foreach (($payload['value'] ?? array()) as $attachment) {
                if (!is_array($attachment) || !empty($attachment['isInline']) || empty($attachment['id'])) {
                    continue;
                }

                $detail = $this->graphClient->get('/users/'.rawurlencode($mailbox).'/messages/'.rawurlencode($messageId).'/attachments/'.rawurlencode((string) $attachment['id']));
                if ($detail === false) {
                    dol_syslog(__CLASS__.' unable to read Graph attachment content for mailbox='.$mailbox.' error='.$this->graphClient->error, LOG_WARNING);
                    continue;
                }

                if (($detail['@odata.type'] ?? '') !== '#microsoft.graph.fileAttachment' || empty($detail['contentBytes'])) {
                    continue;
                }

                $content = base64_decode((string) $detail['contentBytes'], true);
                if ($content === false) {
                    continue;
                }

                $attachments[] = array(
                    'name' => (string) ($detail['name'] ?? $attachment['name'] ?? 'attachment'),
                    'content' => $content,
                    'mimetype' => (string) ($detail['contentType'] ?? $attachment['contentType'] ?? 'application/octet-stream')
                );
            }

            if (!empty($payload['@odata.nextLink'])) {
                $url = $payload['@odata.nextLink'];
                continue;
            }

            $url = '';
        } while ($url !== '');

        return $attachments;
    }

    private function isInsideBootstrapWindow(array $message)
    {
        $days = (int) getDolGlobalString('MONDAY_GRAPH_BOOTSTRAP_LOOKBACK_DAYS', '30');
        if ($days <= 0) {
            return true;
        }

        $sentDateTime = (string) ($message['sentDateTime'] ?? '');
        $timestamp = $sentDateTime !== '' ? strtotime($sentDateTime) : false;
        if ($timestamp === false) {
            return true;
        }

        return $timestamp >= dol_now() - ($days * 86400);
    }

    private function messageHasTrackingBcc(array $message)
    {
        $baseEmail = strtolower(trim((string) getDolGlobalString('MONDAY_INBOUND_EMAIL_BASE', '')));
        if ($baseEmail === '' || strpos($baseEmail, '@') === false) {
            return false;
        }

        $atPos = strrpos($baseEmail, '@');
        $baseLocal = substr($baseEmail, 0, $atPos);
        $baseDomain = substr($baseEmail, $atPos + 1);
        foreach (($message['bccRecipients'] ?? array()) as $recipient) {
            $email = $this->extractGraphRecipientEmail($recipient);
            if (preg_match('/^'.preg_quote($baseLocal, '/').'\+[A-Z0-9]+@'.preg_quote($baseDomain, '/').'$/i', $email)) {
                return true;
            }
        }

        return false;
    }

    private function extractGraphBody(array $message)
    {
        if (!empty($message['body']['content'])) {
            $content = (string) $message['body']['content'];
            if (strtolower((string) ($message['body']['contentType'] ?? '')) === 'html') {
                return trim(html_entity_decode(strip_tags(str_replace(array('<br>', '<br/>', '<br />'), "\n", $content)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }

            return $content;
        }

        return '';
    }

    private function formatGraphRecipients($recipients)
    {
        if (!is_array($recipients)) {
            return '';
        }

        $emails = array();
        foreach ($recipients as $recipient) {
            $email = $this->extractGraphRecipientEmail($recipient);
            if ($email !== '') {
                $emails[] = $email;
            }
        }

        return implode(', ', $emails);
    }

    private function extractGraphRecipientEmail($recipient)
    {
        if (!is_array($recipient)) {
            return '';
        }

        return strtolower(trim((string) ($recipient['emailAddress']['address'] ?? '')));
    }

    private function getRecruiterMailboxes()
    {
        $configured = (string) getDolGlobalString('MONDAY_GRAPH_RECRUITER_MAILBOXES', '');
        $mailboxes = array();
        foreach (preg_split('/[,;\n]+/', $configured) as $mailbox) {
            $mailbox = strtolower(trim((string) $mailbox));
            if ($mailbox !== '' && filter_var($mailbox, FILTER_VALIDATE_EMAIL)) {
                $mailboxes[$mailbox] = true;
            }
        }

        return array_keys($mailboxes);
    }

    private function getClientSecret()
    {
        $secret = getenv('MONDAY_GRAPH_CLIENT_SECRET');
        if ($secret === false || $secret === '') {
            $secret = getenv('OUTLOOKSYNC_CLIENT_SECRET');
        }

        return $secret === false ? '' : (string) $secret;
    }

    private function getState($mailbox)
    {
        $sql = "SELECT delta_link
                  FROM ".MAIN_DB_PREFIX.self::STATE_TABLE."
                 WHERE mailbox_email = '".$this->db->escape($mailbox)."'
                   AND folder_name = 'SentItems'
                 LIMIT 1";
        $res = $this->db->query($sql);
        if ($res && ($obj = $this->db->fetch_object($res))) {
            return array('delta_link' => (string) $obj->delta_link);
        }

        return array('delta_link' => '');
    }

    private function saveSuccess($mailbox, $deltaLink)
    {
        $sql = "INSERT INTO ".MAIN_DB_PREFIX.self::STATE_TABLE."
                (mailbox_email, folder_name, delta_link, last_success_at, last_error, datec)
                VALUES
                ('".$this->db->escape($mailbox)."', 'SentItems', '".$this->db->escape($deltaLink)."', '".$this->db->idate(dol_now())."', NULL, '".$this->db->idate(dol_now())."')
                ON DUPLICATE KEY UPDATE delta_link = VALUES(delta_link), last_success_at = VALUES(last_success_at), last_error = NULL";
        $this->db->query($sql);
    }

    private function saveError($mailbox, $error)
    {
        $sql = "INSERT INTO ".MAIN_DB_PREFIX.self::STATE_TABLE."
                (mailbox_email, folder_name, delta_link, last_error, datec)
                VALUES
                ('".$this->db->escape($mailbox)."', 'SentItems', '', '".$this->db->escape((string) $error)."', '".$this->db->idate(dol_now())."')
                ON DUPLICATE KEY UPDATE last_error = VALUES(last_error)";
        $this->db->query($sql);
    }

    private function ensureStateSchema()
    {
        $sql = "SELECT 1
                  FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = '".$this->db->escape(MAIN_DB_PREFIX.self::STATE_TABLE)."'
                 LIMIT 1";
        $res = $this->db->query($sql);
        if (!$res || $this->db->num_rows($res) === 0) {
            $this->error = 'Missing '.MAIN_DB_PREFIX.self::STATE_TABLE.' table. Run Monday SQL migration.';
            dol_syslog(__CLASS__.' '.$this->error, LOG_ERR);
            return false;
        }

        return true;
    }
}
