<?php

require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once __DIR__.'/mondayinboundemailresolver.class.php';

class MondayInboundEmailProcessor
{
    const HOOK_TYPE = 'hookmondayinbound';
    const HOOK_CONTEXT = 'emailcolector';
    const TASK_FILES_DIR = '/myworkspace/tasks/';
    const PROCESSED_TABLE = 'monday_inbound_email';
    const DEFAULT_ATTACHMENT_MAX_SIZE = 26214400;
    const DEFAULT_ATTACHMENT_MAX_COUNT = 20;
    const DEFAULT_EMAIL_MAX_SIZE = 104857600;
    const DEFAULT_FORBIDDEN_EXTENSIONS = 'exe,msi,bat,cmd,com,scr,ps1,vbs,js,jar,dll,iso,sh,run,bin';

    private $db;
    private $resolver;

    public function __construct($db)
    {
        $this->db = $db;
        $this->resolver = new MondayInboundEmailResolver($db);
    }

    public function ensureEmailCollectorHookActions()
    {
        global $conf, $user;

        if (!isModEnabled('emailcollector')) {
            return true;
        }

        if (!$this->ensureHookContextDeclaration()) {
            return false;
        }

        $sql = "SELECT rowid
                  FROM ".MAIN_DB_PREFIX."emailcollector_emailcollector
                 WHERE entity = ".((int) $conf->entity)."
                   AND status = 1";
        $res = $this->db->query($sql);
        if (!$res) {
            return false;
        }

        $collectorIds = array();
        while ($obj = $this->db->fetch_object($res)) {
            $collectorIds[] = (int) $obj->rowid;
        }

        if (empty($collectorIds)) {
            return true;
        }

        $collectorIdsSql = implode(',', array_map('intval', $collectorIds));
        $existingSql = "SELECT rowid, fk_emailcollector, status
                          FROM ".MAIN_DB_PREFIX."emailcollector_emailcollectoraction
                         WHERE fk_emailcollector IN (".$collectorIdsSql.")
                           AND type = '".$this->db->escape(self::HOOK_TYPE)."'";
        $existingRes = $this->db->query($existingSql);
        $existing = array();
        if ($existingRes) {
            while ($obj = $this->db->fetch_object($existingRes)) {
                $existing[(int) $obj->fk_emailcollector] = (int) $obj->status;
            }
        }

        $now = $this->db->idate(dol_now());
        $creatorId = !empty($user->id) ? (int) $user->id : 0;
        $inserted = false;

        foreach ($collectorIds as $collectorId) {
            if (isset($existing[$collectorId])) {
                if ((int) $existing[$collectorId] === 0) {
                    $updateSql = "UPDATE ".MAIN_DB_PREFIX."emailcollector_emailcollectoraction
                                     SET status = 1, position = 999
                                   WHERE fk_emailcollector = ".$collectorId."
                                     AND type = '".$this->db->escape(self::HOOK_TYPE)."'";
                    if (!$this->db->query($updateSql)) {
                        dol_syslog(__CLASS__.' failed to reactivate hook action for collector='.$collectorId, LOG_ERR);
                        return false;
                    }
                }
                continue;
            }

            $insertSql = "INSERT INTO ".MAIN_DB_PREFIX."emailcollector_emailcollectoraction
                (fk_emailcollector, type, actionparam, date_creation, fk_user_creat, status, position)
                VALUES
                (".$collectorId.", '".$this->db->escape(self::HOOK_TYPE)."', '', '".$now."', ".$creatorId.", 1, 999)";
            if (!$this->db->query($insertSql)) {
                dol_syslog(__CLASS__.' failed to seed hook action for collector='.$collectorId, LOG_ERR);
                return false;
            }

            $inserted = true;
        }

        if ($inserted) {
            dol_syslog(__CLASS__.' hook action seeded for emailcollector', LOG_INFO);
        }

        return true;
    }

    private function ensureHookContextDeclaration()
    {
        $expectedValue = '["'.self::HOOK_CONTEXT.'"]';
        $safeName = $this->db->escape('MAIN_MODULE_MONDAY_HOOKS');
        $safeValue = $this->db->escape($expectedValue);

        $sql = "SELECT rowid, value
                  FROM ".MAIN_DB_PREFIX."const
                 WHERE name = '".$safeName."'
                 LIMIT 1";
        $res = $this->db->query($sql);
        if (!$res) {
            dol_syslog(__CLASS__.' failed to read Monday hook declaration', LOG_ERR);
            return false;
        }

        $row = $this->db->fetch_object($res);
        if ($row) {
            if ((string) $row->value === $expectedValue) {
                return true;
            }

            $updateSql = "UPDATE ".MAIN_DB_PREFIX."const
                             SET value = '".$safeValue."', type = 'chaine', visible = 0
                           WHERE rowid = ".((int) $row->rowid);
            if (!$this->db->query($updateSql)) {
                dol_syslog(__CLASS__.' failed to update Monday hook declaration', LOG_ERR);
                return false;
            }

            return true;
        }

        $insertSql = "INSERT INTO ".MAIN_DB_PREFIX."const (name, entity, value, type, visible, note)
                      VALUES ('".$safeName."', 0, '".$safeValue."', 'chaine', 0, 'Monday hook contexts')";
        if (!$this->db->query($insertSql)) {
            dol_syslog(__CLASS__.' failed to create Monday hook declaration', LOG_ERR);
            return false;
        }

        return true;
    }

    public function processCollectorMessage(array $parameters)
    {
        $headers = $this->buildResolvableHeaders($parameters);
        $resolution = $this->resolver->resolveFromHeaders($headers);

        if (empty($resolution['success']) || empty($resolution['candidate_found'])) {
            dol_syslog(__CLASS__.' ignored email status='.(isset($resolution['status']) ? $resolution['status'] : 'unknown').' token_present='.(int) !empty($resolution['token_present']), LOG_INFO);

            return array(
                'handled' => false,
                'success' => !empty($resolution['success']),
                'status' => isset($resolution['status']) ? $resolution['status'] : 'token_absent',
                'message' => isset($resolution['message']) ? $resolution['message'] : 'Aucun candidat correspondant.',
                'resolution' => $resolution
            );
        }

        $taskId = (int) $resolution['candidate']['task_id'];
        $recipient = !empty($resolution['matched_email']) ? $resolution['matched_email'] : '';
        $subject = trim((string) ($parameters['subject'] ?? ''));
        $body = trim((string) ($parameters['messagetext'] ?? ''));
        $receivedDate = $this->extractReceivedDate($headers, $parameters);
        $messageKey = $this->buildMessageKey($parameters, $recipient, $subject, $body, $receivedDate);

        if (!$this->isAllowedSender($parameters)) {
            return array(
                'handled' => false,
                'success' => true,
                'status' => 'sender_not_allowed',
                'message' => 'Expéditeur non autorisé.',
                'candidate_found' => true,
                'task_id' => $taskId,
                'comment_added' => false,
                'attachments_saved' => 0,
                'attachment_errors' => array(),
                'resolution' => $resolution
            );
        }

        if ($messageKey !== '' && $this->isAlreadyProcessed($messageKey)) {
            return array(
                'handled' => true,
                'success' => true,
                'status' => 'duplicate',
                'message' => 'Email déjà importé.',
                'candidate_found' => true,
                'task_id' => $taskId,
                'comment_added' => false,
                'attachments_saved' => 0,
                'attachment_errors' => array(),
                'resolution' => $resolution
            );
        }

        if ($this->hasExistingCandidateComment($taskId, $recipient, $subject, $body)) {
            if ($messageKey !== '') {
                $this->markProcessed($messageKey, $taskId, 0);
            }

            return array(
                'handled' => true,
                'success' => true,
                'status' => 'duplicate',
                'message' => 'Email déjà importé.',
                'candidate_found' => true,
                'task_id' => $taskId,
                'comment_added' => false,
                'attachments_saved' => 0,
                'attachment_errors' => array(),
                'resolution' => $resolution
            );
        }

        $commentId = $this->addCandidateComment($taskId, $recipient, $subject, $body, $receivedDate, $messageKey);
        $attachmentResult = $this->saveAttachments($taskId, $parameters['attachments'] ?? array());
        if ($messageKey !== '' && $commentId > 0) {
            $this->markProcessed($messageKey, $taskId, $commentId);
        }

        $result = array(
            'handled' => true,
            'success' => true,
            'status' => 'candidate_found',
            'message' => 'Candidat identifié.',
            'candidate_found' => true,
            'task_id' => $taskId,
            'comment_added' => $commentId > 0,
            'attachments_saved' => $attachmentResult['saved'],
            'attachment_errors' => $attachmentResult['errors'],
            'resolution' => $resolution
        );

        dol_syslog(__CLASS__.' processed candidate task='.$taskId.' comment='.(int) ($commentId > 0).' attachments='.(int) $attachmentResult['saved'], LOG_INFO);

        return $result;
    }

    private function buildResolvableHeaders(array $parameters)
    {
        $headers = isset($parameters['header']) ? $parameters['header'] : array();
        if (is_string($headers)) {
            $merged = $headers;
        } elseif (is_array($headers)) {
            $merged = $headers;
        } elseif (is_object($headers)) {
            $merged = get_object_vars($headers);
        } else {
            $merged = array();
        }

        if (!is_array($merged)) {
            return $merged;
        }

        if (isset($parameters['overview'])) {
            $overview = $parameters['overview'];
            foreach (array('to' => 'To', 'cc' => 'Cc', 'delivered_to' => 'Delivered-To', 'x_original_to' => 'X-Original-To') as $sourceKey => $headerName) {
                $value = $this->readValue($overview, $sourceKey);
                if ($value === '' && strpos($sourceKey, '_') !== false) {
                    $value = $this->readValue($overview, str_replace('_', '-', $sourceKey));
                }
                if ($value !== '') {
                    $existingValue = isset($merged[$headerName]) ? $this->flattenValue($merged[$headerName]) : '';
                    $merged[$headerName] = trim(($existingValue !== '' ? $existingValue.', ' : '').$value);
                }
            }
        }

        foreach (array('to' => 'To', 'cc' => 'Cc') as $parameterKey => $headerName) {
            $value = $this->readValue($parameters, $parameterKey);
            if ($value !== '') {
                $existingValue = isset($merged[$headerName]) ? $this->flattenValue($merged[$headerName]) : '';
                $merged[$headerName] = trim(($existingValue !== '' ? $existingValue.', ' : '').$value);
            }
        }

        return $merged;
    }

    private function isAllowedSender(array $parameters)
    {
        $baseEmail = strtolower(trim((string) getDolGlobalString('MONDAY_INBOUND_EMAIL_BASE', '')));
        if ($baseEmail === '' || !filter_var($baseEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $sender = $this->extractSenderEmail($parameters);
        if ($sender === '') {
            return false;
        }

        return strtolower($sender) === $baseEmail && $this->hasTrustedAuthenticationResults($parameters, $baseEmail);
    }

    private function hasTrustedAuthenticationResults(array $parameters, $baseEmail)
    {
        $domain = strtolower((string) substr(strrchr($baseEmail, '@'), 1));
        if ($domain === '') {
            return false;
        }

        $headers = $this->extractHeaderValues($parameters, 'Authentication-Results');
        if (empty($headers)) {
            return false;
        }

        foreach ($headers as $header) {
            $normalized = strtolower($header);
            if (preg_match('/dmarc=pass\b[^;]*header\.from='.preg_quote($domain, '/').'\b/', $normalized)) {
                return true;
            }
            if (preg_match('/dkim=pass\b[^;]*header\.i=(?:[^@;\s]+@|@)?'.preg_quote($domain, '/').'\b/', $normalized)) {
                return true;
            }
            if (preg_match('/spf=pass\b[^;]*smtp\.mailfrom=(?:[^@;\s]+@)?'.preg_quote($domain, '/').'\b/', $normalized)) {
                return true;
            }
        }

        return false;
    }

    private function extractHeaderValues(array $parameters, $headerName)
    {
        $values = array();
        if (empty($parameters['header'])) {
            return $values;
        }

        $headers = $parameters['header'];
        if (is_string($headers)) {
            if (preg_match_all('/^'.preg_quote($headerName, '/').':\s*(.+)$/im', $headers, $matches)) {
                foreach ($matches[1] as $value) {
                    $values[] = trim($value);
                }
            }

            return $values;
        }

        if (is_array($headers)) {
            foreach (array($headerName, strtolower($headerName)) as $key) {
                if (!empty($headers[$key])) {
                    $values[] = $this->flattenValue($headers[$key]);
                }
            }
        }

        return array_values(array_filter($values));
    }

    private function extractSenderEmail(array $parameters)
    {
        foreach (array('from', 'fromtext', 'sender') as $key) {
            $value = $this->readValue($parameters, $key);
            $email = $this->extractFirstEmail($value);
            if ($email !== '') {
                return $email;
            }
        }

        if (isset($parameters['overview'])) {
            foreach (array('from', 'sender') as $key) {
                $value = $this->readValue($parameters['overview'], $key);
                $email = $this->extractFirstEmail($value);
                if ($email !== '') {
                    return $email;
                }
            }
        }

        if (isset($parameters['header'])) {
            $headers = $parameters['header'];
            if (is_string($headers) && preg_match('/^From:\s*(.+)$/im', $headers, $matches)) {
                return $this->extractFirstEmail($matches[1]);
            }
            if (is_array($headers)) {
                foreach (array('from', 'From') as $key) {
                    if (!empty($headers[$key])) {
                        $email = $this->extractFirstEmail($this->flattenValue($headers[$key]));
                        if ($email !== '') {
                            return $email;
                        }
                    }
                }
            }
        }

        return '';
    }

    private function extractFirstEmail($value)
    {
        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', (string) $value, $matches)) {
            return strtolower(trim($matches[0]));
        }

        return '';
    }

    private function readValue($source, $key)
    {
        if (is_array($source) && isset($source[$key])) {
            return $this->flattenValue($source[$key]);
        }

        if (is_object($source)) {
            if (isset($source->$key)) {
                return $this->flattenValue($source->$key);
            }

            $camelKey = str_replace(' ', '', ucwords(str_replace(array('-', '_'), ' ', $key)));
            foreach (array('get'.$camelKey, $key) as $method) {
                if (method_exists($source, $method)) {
                    return $this->flattenValue($source->$method());
                }
            }
        }

        return '';
    }

    private function flattenValue($value)
    {
        if (is_array($value)) {
            $parts = array();
            foreach ($value as $item) {
                $part = $this->flattenValue($item);
                if ($part !== '') {
                    $parts[] = $part;
                }
            }

            return implode(', ', $parts);
        }

        if (is_object($value)) {
            if (method_exists($value, 'toString')) {
                return trim((string) $value->toString());
            }
            if (method_exists($value, 'getValue')) {
                return $this->flattenValue($value->getValue());
            }
            if (method_exists($value, '__toString')) {
                return trim((string) $value);
            }

            return $this->flattenValue(get_object_vars($value));
        }

        return trim((string) $value);
    }

    private function extractReceivedDate($headers, array $parameters)
    {
        $dateString = '';
        if (is_string($headers)) {
            if (preg_match('/^Date:\s*(.+)$/im', $headers, $matches)) {
                $dateString = trim($matches[1]);
            }
        } elseif (is_array($headers)) {
            foreach (array('date', 'Date') as $key) {
                if (!empty($headers[$key])) {
                    $dateString = trim((string) $headers[$key]);
                    break;
                }
            }
        }

        if ($dateString !== '') {
            $timestamp = strtotime($dateString);
            if ($timestamp !== false) {
                return date('Y-m-d H:i:s', $timestamp);
            }
        }

        if (!empty($parameters['overview']) && is_object($parameters['overview'])) {
            foreach (array('date', 'udate') as $property) {
                if (!empty($parameters['overview']->$property)) {
                    $candidateTimestamp = (int) $parameters['overview']->$property;
                    if ($candidateTimestamp > 0) {
                        return date('Y-m-d H:i:s', $candidateTimestamp);
                    }
                }
            }
        }

        return date('Y-m-d H:i:s');
    }

    private function addCandidateComment($taskId, $recipient, $subject, $body, $receivedDate, $messageKey = '')
    {
        $taskId = (int) $taskId;
        if ($taskId <= 0) {
            return 0;
        }

        $displayDate = dol_print_date(strtotime($receivedDate) ?: dol_now(), 'dayhour');
        $comment = '<div class="candidate-mail-copy"'.($messageKey !== '' ? ' data-message-key="'.dol_escape_htmltag($messageKey).'"' : '').'>';
        $comment .= '<strong>Email candidat envoyé le '.dol_escape_htmltag($displayDate).'</strong><br>';
        if (trim((string) $recipient) !== '') {
            $comment .= '<strong>Destinataire :</strong> '.dol_escape_htmltag($recipient).'<br>';
        }
        if (trim((string) $subject) !== '') {
            $comment .= '<strong>Sujet :</strong> '.dol_escape_htmltag($subject).'<br>';
        }
        $comment .= '<strong>Message :</strong><br>';
        $comment .= nl2br(dol_escape_htmltag($this->normalizeBody($body)));
        $comment .= '</div>';

        $commentSql = $this->db->escape($comment);
        $dateSql = $this->db->idate(strtotime($receivedDate) ?: dol_now());
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."myworkspace_comment
                (fk_task, fk_user, comment, font_family, font_size, font_weight, font_color, datec)
                VALUES
                (".$taskId.", 0, '".$commentSql."', 'Arial', 14, 400, '#000000', '".$dateSql."')";

        if (!$this->db->query($sql)) {
            dol_syslog(__CLASS__.' comment insert failed for task='.$taskId, LOG_ERR);
            return 0;
        }

        return (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'myworkspace_comment');
    }

    private function buildMessageKey(array $parameters, $recipient, $subject, $body, $receivedDate)
    {
        $messageId = $this->extractMessageId($parameters);
        $collectorId = isset($parameters['collector_id']) ? (int) $parameters['collector_id'] : 0;
        if ($messageId !== '') {
            return hash('sha256', $collectorId.'|message-id|'.$messageId);
        }

        $uid = $this->extractMessageUid($parameters);
        if ($uid !== '') {
            return hash('sha256', $collectorId.'|uid|'.$uid);
        }

        return hash('sha256', strtolower(trim($recipient)).'|'.strtolower(trim($subject)).'|'.$receivedDate.'|'.$this->normalizeBody($body));
    }

    private function extractMessageId(array $parameters)
    {
        foreach (array('message_id', 'message-id', 'Message-ID') as $key) {
            $value = $this->readValue($parameters, $key);
            if ($value !== '') {
                return trim($value, " <>\t\r\n");
            }
        }

        if (isset($parameters['overview'])) {
            foreach (array('message_id', 'message-id', 'Message-ID') as $key) {
                $value = $this->readValue($parameters['overview'], $key);
                if ($value !== '') {
                    return trim($value, " <>\t\r\n");
                }
            }
        }

        if (isset($parameters['header'])) {
            $headers = $parameters['header'];
            if (is_string($headers) && preg_match('/^Message-ID:\s*(.+)$/im', $headers, $matches)) {
                return trim($matches[1], " <>\t\r\n");
            }
            if (is_array($headers)) {
                foreach (array('message-id', 'Message-ID') as $key) {
                    if (!empty($headers[$key])) {
                        return trim($this->flattenValue($headers[$key]), " <>\t\r\n");
                    }
                }
            }
        }

        return '';
    }

    private function extractMessageUid(array $parameters)
    {
        if (isset($parameters['imapemail']) && is_object($parameters['imapemail']) && method_exists($parameters['imapemail'], 'getAttributes')) {
            $attributes = $parameters['imapemail']->getAttributes();
            if (is_array($attributes) && !empty($attributes['uid'])) {
                return (string) $attributes['uid'];
            }
        }

        return $this->readValue($parameters, 'uid');
    }

    private function isAlreadyProcessed($messageKey)
    {
        if (!$this->ensureProcessedSchema()) {
            return false;
        }

        $sql = "SELECT rowid
                  FROM ".MAIN_DB_PREFIX.self::PROCESSED_TABLE."
                 WHERE message_key = '".$this->db->escape($messageKey)."'
                 LIMIT 1";
        $res = $this->db->query($sql);

        return $res && $this->db->num_rows($res) > 0;
    }

    private function markProcessed($messageKey, $taskId, $commentId)
    {
        if (!$this->ensureProcessedSchema()) {
            return false;
        }

        $sql = "INSERT IGNORE INTO ".MAIN_DB_PREFIX.self::PROCESSED_TABLE."
                (message_key, fk_task, fk_comment, datec)
                VALUES
                ('".$this->db->escape($messageKey)."', ".((int) $taskId).", ".((int) $commentId).", '".$this->db->idate(dol_now())."')";

        return (bool) $this->db->query($sql);
    }

    private function ensureProcessedSchema()
    {
        $sql = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX.self::PROCESSED_TABLE." (
            rowid int(11) AUTO_INCREMENT PRIMARY KEY,
            message_key varchar(64) NOT NULL,
            fk_task int(11) NOT NULL,
            fk_comment int(11) NOT NULL DEFAULT 0,
            datec datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_monday_inbound_email_message_key (message_key),
            INDEX idx_monday_inbound_email_fk_task (fk_task)
        ) ENGINE=innodb";

        return (bool) $this->db->query($sql);
    }

    private function hasExistingCandidateComment($taskId, $recipient, $subject, $body)
    {
        $body = $this->normalizeBody($body);
        $snippet = mb_substr($body, 0, 80, 'UTF-8');
        if ($recipient === '' || $subject === '' || $snippet === '') {
            return false;
        }

        $sql = "SELECT rowid
                  FROM ".MAIN_DB_PREFIX."myworkspace_comment
                 WHERE fk_task = ".((int) $taskId)."
                   AND comment LIKE '%".$this->db->escape($recipient)."%'
                   AND comment LIKE '%".$this->db->escape($subject)."%'
                   AND comment LIKE '%".$this->db->escape($snippet)."%'
                 LIMIT 1";
        $res = $this->db->query($sql);

        return $res && $this->db->num_rows($res) > 0;
    }

    private function saveAttachments($taskId, $attachments)
    {
        $result = array(
            'saved' => 0,
            'errors' => array()
        );

        if (empty($attachments) || !is_array($attachments)) {
            return $result;
        }

        $maxCount = $this->getPositiveGlobalInt('MONDAY_INBOUND_ATTACHMENT_MAX_COUNT', self::DEFAULT_ATTACHMENT_MAX_COUNT);
        if (count($attachments) > $maxCount) {
            $result['errors'][] = 'too_many_attachments';
            return $result;
        }

        $dir = rtrim(DOL_DATA_ROOT, '/').self::TASK_FILES_DIR;
        if (!dol_is_dir($dir)) {
            if (dol_mkdir($dir) < 0) {
                $result['errors'][] = 'storage_unavailable';
                return $result;
            }
        }

        $maxFileSize = $this->getPositiveGlobalInt('MONDAY_INBOUND_ATTACHMENT_MAX_SIZE', self::DEFAULT_ATTACHMENT_MAX_SIZE);
        $maxEmailSize = $this->getPositiveGlobalInt('MONDAY_INBOUND_EMAIL_MAX_SIZE', self::DEFAULT_EMAIL_MAX_SIZE);
        $preparedAttachments = array();
        $totalSize = 0;

        foreach ($attachments as $attachment) {
            $originalName = $this->getAttachmentName($attachment);
            $content = $this->getAttachmentContent($attachment);
            $mimeType = $this->getAttachmentMimeType($attachment);

            if ($originalName === '' || $content === '') {
                $result['errors'][] = 'empty_attachment';
                continue;
            }

            $safeOriginalName = dol_sanitizeFileName($originalName);
            $extension = strtolower(pathinfo($safeOriginalName, PATHINFO_EXTENSION));
            if ($this->isForbiddenAttachmentExtension($extension)) {
                $result['errors'][] = 'forbidden_attachment_type';
                continue;
            }

            $filesize = strlen($content);
            if ($filesize > $maxFileSize) {
                $result['errors'][] = 'attachment_too_large';
                continue;
            }

            $totalSize += $filesize;
            $preparedAttachments[] = array(
                'original_name' => $safeOriginalName,
                'content' => $content,
                'mimetype' => $mimeType,
                'filesize' => $filesize
            );
        }

        if ($totalSize > $maxEmailSize) {
            $result['errors'][] = 'email_attachments_too_large';
            return $result;
        }

        foreach ($preparedAttachments as $attachment) {
            $safeOriginalName = $this->getAvailableOriginalName($taskId, $attachment['original_name']);
            $storedName = date('YmdHis').'_'.bin2hex(random_bytes(4)).'_'.$safeOriginalName;
            $filePath = $dir.$storedName;

            if (@file_put_contents($filePath, $attachment['content'], LOCK_EX) === false) {
                $result['errors'][] = 'file_write_failed';
                continue;
            }

            $sql = "INSERT INTO ".MAIN_DB_PREFIX."myworkspace_task_file
                    (fk_task, original_name, filename, filesize, mimetype, fk_user, datec)
                    VALUES
                    (".((int) $taskId).", '".$this->db->escape($safeOriginalName)."', '".$this->db->escape($storedName)."', ".((int) $attachment['filesize']).", '".$this->db->escape($attachment['mimetype'])."', 0, '".$this->db->idate(dol_now())."')";
            if (!$this->db->query($sql)) {
                @unlink($filePath);
                $result['errors'][] = 'db_insert_failed';
                continue;
            }

            $result['saved']++;
        }

        return $result;
    }

    private function getPositiveGlobalInt($name, $default)
    {
        $value = (int) getDolGlobalString($name, (string) $default);

        return $value > 0 ? $value : (int) $default;
    }

    private function isForbiddenAttachmentExtension($extension)
    {
        $extension = strtolower(trim((string) $extension));
        if ($extension === '') {
            return false;
        }

        $configured = getDolGlobalString('MONDAY_INBOUND_ATTACHMENT_FORBIDDEN_EXTENSIONS', self::DEFAULT_FORBIDDEN_EXTENSIONS);
        $forbidden = array_filter(array_map('trim', explode(',', strtolower($configured))));

        return in_array($extension, $forbidden, true);
    }

    private function getAvailableOriginalName($taskId, $originalName)
    {
        $originalName = trim((string) $originalName);
        if ($originalName === '') {
            return 'attachment';
        }

        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $baseName = $extension !== '' ? substr($originalName, 0, -(strlen($extension) + 1)) : $originalName;
        if ($baseName === '') {
            $baseName = 'attachment';
        }

        $candidate = $originalName;
        $index = 1;
        while ($this->taskFileOriginalNameExists($taskId, $candidate)) {
            $candidate = $baseName.'('.$index.')'.($extension !== '' ? '.'.$extension : '');
            $index++;
        }

        return $candidate;
    }

    private function taskFileOriginalNameExists($taskId, $originalName)
    {
        $sql = "SELECT rowid
                  FROM ".MAIN_DB_PREFIX."myworkspace_task_file
                 WHERE fk_task = ".((int) $taskId)."
                   AND original_name = '".$this->db->escape($originalName)."'
                 LIMIT 1";
        $res = $this->db->query($sql);

        return $res && $this->db->num_rows($res) > 0;
    }

    private function getAttachmentName($attachment)
    {
        if (is_object($attachment)) {
            if (is_callable(array($attachment, 'getName'))) {
                return trim((string) $attachment->getName());
            }
            if (is_callable(array($attachment, 'getFilename'))) {
                return trim((string) $attachment->getFilename());
            }
        } elseif (is_array($attachment)) {
            foreach (array('name', 'filename', 'original_name') as $key) {
                if (!empty($attachment[$key])) {
                    return trim((string) $attachment[$key]);
                }
            }
        }

        return '';
    }

    private function getAttachmentContent($attachment)
    {
        if (is_object($attachment) && is_callable(array($attachment, 'getContent'))) {
            $content = $attachment->getContent();
            return is_string($content) ? $content : '';
        }

        if (is_array($attachment) && isset($attachment['content']) && is_string($attachment['content'])) {
            return $attachment['content'];
        }

        return '';
    }

    private function getAttachmentMimeType($attachment)
    {
        if (is_object($attachment)) {
            foreach (array('getMimeType', 'getContentType', 'getType') as $method) {
                if (is_callable(array($attachment, $method))) {
                    $value = $attachment->$method();
                    if (!empty($value)) {
                        return (string) $value;
                    }
                }
            }
        }

        if (is_array($attachment)) {
            foreach (array('mime', 'mimetype', 'content_type', 'type') as $key) {
                if (!empty($attachment[$key])) {
                    return (string) $attachment[$key];
                }
            }
        }

        return 'application/octet-stream';
    }

    private function normalizeBody($body)
    {
        $body = (string) $body;
        $body = str_replace(array("\r\n", "\r"), "\n", $body);

        return trim($body);
    }
}
