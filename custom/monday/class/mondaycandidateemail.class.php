<?php

require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';

class MondayCandidateEmail
{
    const TOKEN_BYTES = 16;
    const TOKEN_ATTEMPTS = 20;

    private $db;
    private $hasTokenColumn = null;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function getAllowedWorkspaceLabels()
    {
        return array(
            'Candidatures à traiter IT PARIS',
            'Candidatures à traiter IT LILLE',
            'Vivier candidat Paris',
            'Vivier Candidats Lille'
        );
    }

    public function normalizeLabel($label)
    {
        $label = html_entity_decode((string) $label, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $label = dol_string_unaccent($label);
        $label = strtolower($label);
        return preg_replace('/[^a-z0-9]+/', '', $label);
    }

    public function isManagedWorkspaceLabel($label)
    {
        $normalized = $this->normalizeLabel($label);
        foreach ($this->getAllowedWorkspaceLabels() as $allowedLabel) {
            if ($normalized === $this->normalizeLabel($allowedLabel)) {
                return true;
            }
        }

        return false;
    }

    public function fetchTaskContext($taskId)
    {
        $taskId = (int) $taskId;
        if ($taskId <= 0) {
            return null;
        }

        $sql = "SELECT t.rowid,
                       t.label,
                       g.label AS group_label,
                       w.rowid AS workspace_id,
                       w.label AS workspace_label
                  FROM ".MAIN_DB_PREFIX."myworkspace_task t
             LEFT JOIN ".MAIN_DB_PREFIX."myworkspace_group g ON g.rowid = t.fk_group
             LEFT JOIN ".MAIN_DB_PREFIX."myworkspace w ON w.rowid = g.fk_workspace
                 WHERE t.rowid = ".$taskId;

        $res = $this->db->query($sql);
        if (!$res) {
            return null;
        }

        $task = $this->db->fetch_object($res);
        if (!$task) {
            return null;
        }

        $token = '';
        if ($this->hasTokenStorageColumn()) {
            $tokenSql = "SELECT inbound_email_token
                           FROM ".MAIN_DB_PREFIX."myworkspace_task
                          WHERE rowid = ".$taskId."
                          LIMIT 1";
            $tokenRes = $this->db->query($tokenSql);
            if ($tokenRes && ($tokenRow = $this->db->fetch_object($tokenRes)) && !empty($tokenRow->inbound_email_token)) {
                $token = (string) $tokenRow->inbound_email_token;
            }
        }

        return array(
            'id' => (int) $task->rowid,
            'label' => (string) $task->label,
            'token' => $token,
            'group_label' => (string) $task->group_label,
            'workspace_id' => (int) $task->workspace_id,
            'workspace_label' => (string) $task->workspace_label
        );
    }

    public function getBaseEmail()
    {
        return trim((string) getDolGlobalString('MONDAY_INBOUND_EMAIL_BASE', ''));
    }

    private function hasTokenStorageColumn()
    {
        if ($this->hasTokenColumn !== null) {
            return $this->hasTokenColumn;
        }

        $sql = "SHOW COLUMNS FROM ".MAIN_DB_PREFIX."myworkspace_task LIKE 'inbound_email_token'";
        $res = $this->db->query($sql);
        $this->hasTokenColumn = ($res && $this->db->num_rows($res) > 0);

        return $this->hasTokenColumn;
    }

    public function isValidInboundEmailToken($token)
    {
        return (bool) preg_match('/^[a-f0-9]{32}$/i', trim((string) $token));
    }

    public function findTasksByInboundEmailToken($token, $limit = 2)
    {
        $token = strtolower(trim((string) $token));
        if ($token === '' || !$this->isValidInboundEmailToken($token)) {
            return array();
        }

        if (!$this->hasTokenStorageColumn()) {
            return array();
        }

        $safeToken = $this->db->escape($token);
        $sql = "SELECT DISTINCT t.rowid
                  FROM ".MAIN_DB_PREFIX."myworkspace_task t
                 WHERE t.inbound_email_token = '".$safeToken."'
              ORDER BY t.rowid ASC
                 LIMIT ".((int) $limit > 0 ? (int) $limit : 2);

        $res = $this->db->query($sql);
        if (!$res) {
            return array();
        }

        $tasks = array();
        while ($obj = $this->db->fetch_object($res)) {
            $task = $this->fetchTaskContext((int) $obj->rowid);
            if ($task) {
                $tasks[] = $task;
            }
        }

        return $tasks;
    }

    private function ensureTokenStorageSchema()
    {
        if (!$this->hasTokenStorageColumn()) {
            dol_syslog(__CLASS__.' missing inbound_email_token column. Run Monday SQL migration.', LOG_ERR);
            return false;
        }

        $indexSql = "SHOW INDEX FROM ".MAIN_DB_PREFIX."myworkspace_task WHERE Key_name = 'uk_inbound_email_token'";
        $indexRes = $this->db->query($indexSql);
        if (!$indexRes || $this->db->num_rows($indexRes) === 0) {
            dol_syslog(__CLASS__.' missing uk_inbound_email_token index. Run Monday SQL migration.', LOG_ERR);
            return false;
        }

        return true;
    }

    public function buildInboundEmail($token)
    {
        $token = trim((string) $token);
        if ($token === '') {
            return '';
        }

        $baseEmail = $this->getBaseEmail();
        if ($baseEmail === '' || !filter_var($baseEmail, FILTER_VALIDATE_EMAIL)) {
            return '';
        }

        $parts = explode('@', $baseEmail, 2);
        if (count($parts) !== 2) {
            return '';
        }

        $localPart = trim($parts[0]);
        $domain = trim($parts[1]);
        if ($localPart === '' || $domain === '') {
            return '';
        }

        return $localPart.'+'.$token.'@'.$domain;
    }

    public function generateToken()
    {
        if (!$this->ensureTokenStorageSchema()) {
            return '';
        }

        return bin2hex(random_bytes(self::TOKEN_BYTES));
    }

    public function ensureToken($taskId)
    {
        if (!$this->ensureTokenStorageSchema()) {
            return '';
        }

        $task = $this->fetchTaskContext($taskId);
        if (!$task || !$this->isManagedWorkspaceLabel($task['workspace_label'])) {
            return '';
        }

        if (!empty($task['token'])) {
            return $task['token'];
        }

        for ($i = 0; $i < self::TOKEN_ATTEMPTS; $i++) {
            $token = $this->generateToken();
            if ($token === '') {
                continue;
            }

            $safeToken = $this->db->escape($token);
            $updateSql = "UPDATE ".MAIN_DB_PREFIX."myworkspace_task
                             SET inbound_email_token = '".$safeToken."'
                           WHERE rowid = ".(int) $task['id']." AND (inbound_email_token IS NULL OR inbound_email_token = '')";
            $updated = $this->db->query($updateSql);

            $check = $this->fetchTaskContext($task['id']);
            if ($check && !empty($check['token'])) {
                return $check['token'];
            }

            if (!$updated) {
                continue;
            }
        }

        return '';
    }

    public function getTaskEmailInfo($taskId)
    {
        if (!$this->ensureTokenStorageSchema()) {
            return array(
                'success' => false,
                'enabled' => true,
                'configured' => false,
                'message' => 'La migration du champ de token est requise.'
            );
        }

        $task = $this->fetchTaskContext($taskId);
        if (!$task) {
            return array(
                'success' => false,
                'enabled' => false,
                'message' => 'Candidat introuvable'
            );
        }

        if (!$this->isManagedWorkspaceLabel($task['workspace_label'])) {
            return array(
                'success' => true,
                'enabled' => false,
                'task_id' => (int) $task['id'],
                'workspace_label' => $task['workspace_label']
            );
        }

        $baseEmail = $this->getBaseEmail();
        if ($baseEmail === '' || !filter_var($baseEmail, FILTER_VALIDATE_EMAIL)) {
            return array(
                'success' => true,
                'enabled' => true,
                'configured' => false,
                'task_id' => (int) $task['id'],
                'workspace_label' => $task['workspace_label'],
                'message' => 'La constante MONDAY_INBOUND_EMAIL_BASE doit être configurée.'
            );
        }

        $token = $this->ensureToken($task['id']);
        if ($token === '') {
            return array(
                'success' => false,
                'enabled' => true,
                'configured' => true,
                'task_id' => (int) $task['id'],
                'workspace_label' => $task['workspace_label'],
                'message' => 'Impossible de générer le token.'
            );
        }

        return array(
            'success' => true,
            'enabled' => true,
            'configured' => true,
            'task_id' => (int) $task['id'],
            'workspace_label' => $task['workspace_label'],
            'email' => $this->buildInboundEmail($token),
            'token' => $token
        );
    }
}
