<?php

require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';

class CandidateRetentionMailService
{
    const CAMPAIGN_POOL = 'candidate_retention_pool_2y';
    const CAMPAIGN_PROCESSING = 'candidate_retention_processing_2y';

    /** @var DoliDB */
    private $db;

    /** @var Conf */
    private $conf;

    public $error = '';
    public $errors = [];
    public $output = '';

    public function __construct($db)
    {
        global $conf;

        $this->db = $db;
        $this->conf = $conf;
    }

    public function doScheduledJob($parameters = '')
    {
        $limit = 100;
        if (preg_match('/(?:^|[,\s])limit=(\d+)/', (string) $parameters, $matches)) {
            $limit = max(1, min(500, (int) $matches[1]));
        }

        $result = $this->processDueCandidates($limit);
        $this->output = sprintf(
            'Candidate retention mail job: %d sent, %d failed, %d skipped.',
            $result['sent'],
            $result['failed'],
            $result['skipped']
        );
        if (!empty($result['errors'])) {
            $this->error = implode('; ', array_slice($result['errors'], 0, 5));
        }

        return empty($result['errors']) ? 0 : -1;
    }

    public function processDueCandidates($limit = 100)
    {
        return $this->processDueCandidatesForTask($limit, 0, true);
    }

    public function previewDueCandidates($limit = 100, $taskId = 0)
    {
        $this->ensureLogTable();
        $candidates = $this->fetchDueCandidates((int) $limit, (int) $taskId);
        foreach ($candidates as &$candidate) {
            $log = $this->getCampaignLog($candidate['task_id'], $candidate['campaign']);
            $candidate['last_status'] = $log ? $log['status'] : '';
            $candidate['already_sent'] = $log && $log['status'] === 'sent';
            $candidate['draft'] = $this->buildDraft($candidate);
        }
        unset($candidate);

        return $candidates;
    }

    public function processDueCandidatesForTask($limit = 100, $taskId = 0, $allowSend = true)
    {
        $this->ensureLogTable();

        $stats = [
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $candidates = $this->fetchDueCandidates((int) $limit, (int) $taskId);
        foreach ($candidates as $candidate) {
            if (!$allowSend) {
                $stats['skipped']++;
                continue;
            }

            $result = $this->processCandidate($candidate);
            if ($result === 'sent') {
                $stats['sent']++;
            } elseif ($result === 'failed') {
                $stats['failed']++;
            } else {
                $stats['skipped']++;
            }
        }

        return $stats;
    }

    public function getLatestFailure($taskId)
    {
        $this->ensureLogTable();
        $taskId = (int) $taskId;
        if ($taskId <= 0) {
            return null;
        }

        $sql = "SELECT status, error_message, recipient, subject, date_attempt
                  FROM ".MAIN_DB_PREFIX."monday_candidate_retention_mail_log
                 WHERE fk_task = ".$taskId."
              ORDER BY date_attempt DESC, rowid DESC";
        $res = $this->db->query($sql);
        if (!$res || !($row = $this->db->fetch_object($res))) {
            return null;
        }
        if ($row->status !== 'failed') {
            return null;
        }

        return [
            'status' => $row->status,
            'error_message' => $row->error_message,
            'recipient' => $row->recipient,
            'subject' => $row->subject,
            'date_attempt' => $row->date_attempt,
        ];
    }

    private function processCandidate($candidate)
    {
        $existingLog = $this->getCampaignLog($candidate['task_id'], $candidate['campaign']);
        if ($existingLog && $existingLog['status'] === 'sent') {
            return 'skipped';
        }

        $draft = $this->buildDraft($candidate);
        $recipient = trim((string) $candidate['recipient']);

        if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $message = $recipient === '' ? 'Adresse email non renseignée.' : 'Adresse email invalide : '.$recipient;
            $this->recordFailure($candidate, $draft, $message, $existingLog);
            return 'failed';
        }

        $from = $this->getSenderEmail();
        if ($from === '') {
            $this->recordFailure($candidate, $draft, 'Email expéditeur Dolibarr non configuré ou invalide.', $existingLog);
            return 'failed';
        }

        $mail = new CMailFile(
            $draft['subject'],
            $recipient,
            $from,
            $this->mailBodyToHtml($draft['body']),
            [],
            [],
            [],
            '',
            '',
            0,
            1,
            '',
            '',
            'monday-retention-'.$candidate['campaign'].'-'.$candidate['task_id']
        );

        if (!$mail->sendfile()) {
            $error = !empty($mail->error) ? $mail->error : implode(', ', (array) $mail->errors);
            $this->recordFailure($candidate, $draft, 'Erreur lors de l’envoi du mail'.($error ? ' : '.$error : '.'), $existingLog);
            return 'failed';
        }

        $this->insertLog($candidate, $draft, 'sent', '');
        $this->addComment(
            (int) $candidate['task_id'],
            $this->getSystemUserId(),
            '<div class="candidate-mail-copy candidate-retention-mail-copy">'
            .'<strong>Email automatique RGPD envoyé le '.dol_print_date(dol_now(), 'dayhour').'</strong><br>'
            .'<strong>Destinataire :</strong> '.dol_escape_htmltag($recipient).'<br>'
            .'<strong>Sujet :</strong> '.dol_escape_htmltag($draft['subject']).'<br>'
            .'<strong>Message :</strong><br>'.nl2br(dol_escape_htmltag($draft['body']))
            .'</div>'
        );

        return 'sent';
    }

    private function recordFailure($candidate, $draft, $message, $existingLog = null)
    {
        $this->insertLog($candidate, $draft, 'failed', $message);
    }

    private function buildDraft($candidate)
    {
        $firstname = $this->getFirstname($candidate['label']);

        if ($candidate['campaign'] === self::CAMPAIGN_POOL) {
            return [
                'subject' => "Continuons l'aventure ensemble",
                'body' => "Bonjour ".$firstname.",\n\n"
                    ."Nous tenions tout d'abord à vous remercier pour le temps que vous nous avez accordé tout au long de notre processus de recrutement ainsi que pour la qualité de nos échanges.\n\n"
                    ."Malheureusement, cette opportunité n'a pas abouti. Malgré les qualités de votre profil, le client a fait un autre choix.\n\n"
                    ."Cette décision ne remet absolument pas en cause l'intérêt que nous portons à votre candidature. Au contraire, nous sommes convaincus que votre profil pourra correspondre à de futures opportunités.\n\n"
                    ."Sauf avis contraire de votre part, nous conserverons votre candidature dans notre base de données afin de pouvoir vous recontacter dès qu'un besoin correspondant à votre profil se présentera chez l'un de nos clients.\n\n"
                    ."Si vous ne souhaitez pas que votre candidature soit conservée dans notre vivier, il vous suffit de nous en informer par retour de mail.\n\n"
                    ."Nous continuerons à penser à vous lors de nos prochains recrutements et espérons avoir le plaisir de vous accompagner prochainement vers une nouvelle opportunité.\n\n"
                    ."Encore merci pour votre confiance et votre disponibilité.\n\n"
                    ."À très bientôt,\n\n"
                    ."L'équipe Inzerty",
            ];
        }

        return [
            'subject' => 'Votre profil nous intéresse pour de futures opportunités',
            'body' => "Bonjour ".$firstname.",\n\n"
                ."Nous vous remercions pour l'intérêt que vous portez à Inzerty ainsi que pour votre candidature.\n\n"
                ."Après étude de votre profil, nous ne disposons malheureusement pas, à ce jour, d'une opportunité correspondant pleinement à votre parcours et à vos attentes.\n\n"
                ."En revanche, votre profil a retenu notre attention. Sauf avis contraire de votre part, nous souhaiterions conserver votre candidature dans notre base de données afin de pouvoir vous recontacter dès qu'une opportunité en adéquation avec vos compétences et vos aspirations se présentera.\n\n"
                ."Nous travaillons quotidiennement avec de nombreux clients et de nouveaux besoins nous sont régulièrement confiés. Il est donc tout à fait possible que nous revenions rapidement vers vous.\n\n"
                ."Si vous ne souhaitez pas que votre candidature soit conservée dans notre vivier, il vous suffit de nous en informer par retour de mail.\n\n"
                ."Nous vous remercions pour la confiance que vous nous avez accordée et vous souhaitons une pleine réussite dans vos recherches.\n\n"
                ."À très bientôt,\n\n"
                ."L'équipe Inzerty",
        ];
    }

    private function fetchDueCandidates($limit, $taskId = 0)
    {
        $poolLabels = $this->normalizeList([
            'Vivier candidat Paris',
            'Vivier candidats Paris',
            'Vivier candidat Lille',
            'Vivier candidats Lille',
        ]);
        $processingLabels = $this->normalizeList([
            'Candidatures à traiter IT Paris',
            'Candidature à traiter IT Paris',
            'Candidatures à traiter IT Lille',
            'Candidature à traiter IT Lille',
        ]);

        $sql = "SELECT t.rowid, t.label, t.datec, g.label as group_label, w.label as workspace_label,
                       c.label as column_label, cell.value as cell_value
                  FROM ".MAIN_DB_PREFIX."myworkspace_task t
                  JOIN ".MAIN_DB_PREFIX."myworkspace_group g ON g.rowid = t.fk_group
                  JOIN ".MAIN_DB_PREFIX."myworkspace w ON w.rowid = g.fk_workspace
             LEFT JOIN ".MAIN_DB_PREFIX."myworkspace_column c ON c.fk_group = g.rowid
             LEFT JOIN ".MAIN_DB_PREFIX."myworkspace_cell cell ON cell.fk_task = t.rowid AND cell.fk_column = c.rowid
                 WHERE t.datec <= DATE_SUB(NOW(), INTERVAL 2 YEAR)";
        if ((int) $taskId > 0) {
            $sql .= " AND t.rowid = ".((int) $taskId);
        }
        $sql .= "
              ORDER BY t.datec ASC
                 LIMIT ".((int) $limit * 20);

        $res = $this->db->query($sql);
        $tasks = [];
        while ($res && $row = $this->db->fetch_object($res)) {
            $taskId = (int) $row->rowid;
            if (!isset($tasks[$taskId])) {
                $campaign = $this->resolveCampaign($row->workspace_label, $row->group_label, $poolLabels, $processingLabels);
                if ($campaign === '') {
                    continue;
                }

                $tasks[$taskId] = [
                    'task_id' => $taskId,
                    'label' => (string) $row->label,
                    'datec' => (string) $row->datec,
                    'group_label' => (string) $row->group_label,
                    'workspace_label' => (string) $row->workspace_label,
                    'campaign' => $campaign,
                    'recipient' => '',
                ];
            }

            if ($tasks[$taskId]['recipient'] === '' && $this->isEmailColumn($row->column_label)) {
                $tasks[$taskId]['recipient'] = trim((string) $row->cell_value);
            }
        }

        return array_slice(array_values($tasks), 0, $limit);
    }

    private function resolveCampaign($workspaceLabel, $groupLabel, $poolLabels, $processingLabels)
    {
        $workspace = $this->normalizeLabel($workspaceLabel);
        $group = $this->normalizeLabel($groupLabel);

        if (isset($poolLabels[$workspace]) || isset($poolLabels[$group])) {
            return self::CAMPAIGN_POOL;
        }
        if (isset($processingLabels[$workspace]) || isset($processingLabels[$group])) {
            return self::CAMPAIGN_PROCESSING;
        }

        return '';
    }

    private function ensureLogTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."monday_candidate_retention_mail_log (
            rowid integer AUTO_INCREMENT PRIMARY KEY,
            fk_task integer NOT NULL,
            campaign varchar(64) NOT NULL,
            status varchar(16) NOT NULL,
            recipient varchar(255) DEFAULT NULL,
            subject varchar(255) DEFAULT NULL,
            error_message text,
            date_attempt datetime NOT NULL,
            tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_monday_retention_task_campaign (fk_task, campaign),
            INDEX idx_monday_retention_status (status),
            INDEX idx_monday_retention_date_attempt (date_attempt)
        ) ENGINE=innodb";

        $this->db->query($sql);
    }

    private function getCampaignLog($taskId, $campaign)
    {
        $sql = "SELECT rowid, status, error_message, recipient, subject, date_attempt
                  FROM ".MAIN_DB_PREFIX."monday_candidate_retention_mail_log
                 WHERE fk_task = ".((int) $taskId)."
                   AND campaign = '".$this->db->escape($campaign)."'";
        $res = $this->db->query($sql);
        if (!$res || !($row = $this->db->fetch_object($res))) {
            return null;
        }

        return [
            'rowid' => (int) $row->rowid,
            'status' => (string) $row->status,
            'error_message' => (string) $row->error_message,
            'recipient' => (string) $row->recipient,
            'subject' => (string) $row->subject,
            'date_attempt' => (string) $row->date_attempt,
        ];
    }

    private function insertLog($candidate, $draft, $status, $errorMessage)
    {
        $date = date('Y-m-d H:i:s');
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."monday_candidate_retention_mail_log
                    (fk_task, campaign, status, recipient, subject, error_message, date_attempt)
                VALUES (
                    ".((int) $candidate['task_id']).",
                    '".$this->db->escape($candidate['campaign'])."',
                    '".$this->db->escape($status)."',
                    '".$this->db->escape((string) $candidate['recipient'])."',
                    '".$this->db->escape((string) $draft['subject'])."',
                    '".$this->db->escape((string) $errorMessage)."',
                    '".$this->db->escape($date)."'
                )
                ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    recipient = VALUES(recipient),
                    subject = VALUES(subject),
                    error_message = VALUES(error_message),
                    date_attempt = VALUES(date_attempt)";

        return (bool) $this->db->query($sql);
    }

    private function addComment($taskId, $userId, $comment)
    {
        $date = date('Y-m-d H:i:s');
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."myworkspace_comment
                    (fk_task, fk_user, comment, font_family, font_size, font_weight, font_color, datec)
                VALUES (
                    ".((int) $taskId).",
                    ".((int) $userId).",
                    '".$this->db->escape($comment)."',
                    'Arial',
                    14,
                    400,
                    '#000000',
                    '".$this->db->escape($date)."'
                )";

        return (bool) $this->db->query($sql);
    }

    private function getSenderEmail()
    {
        global $user;

        $candidates = [
            !empty($this->conf->global->MAIN_MAIL_EMAIL_FROM) ? $this->conf->global->MAIN_MAIL_EMAIL_FROM : '',
            !empty($this->conf->global->MAIN_MAIL_FORCE_FROM) ? $this->conf->global->MAIN_MAIL_FORCE_FROM : '',
            !empty($this->conf->global->MAIN_INFO_SOCIETE_MAIL) ? $this->conf->global->MAIN_INFO_SOCIETE_MAIL : '',
            !empty($user->email) ? $user->email : '',
        ];

        foreach ($candidates as $email) {
            $email = trim((string) $email);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return '';
    }

    private function getSystemUserId()
    {
        global $user;

        if (!empty($user->id)) {
            return (int) $user->id;
        }

        $res = $this->db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."user WHERE statut = 1 ORDER BY admin DESC, rowid ASC");
        if ($res && $row = $this->db->fetch_object($res)) {
            return (int) $row->rowid;
        }

        return 1;
    }

    private function mailBodyToHtml($body)
    {
        $body = trim(str_replace(["\r\n", "\r"], "\n", (string) $body));
        $paragraphs = preg_split("/\n{2,}/", $body);
        $html = '<div style="font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 1.55; color: #111;">';
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph !== '') {
                $html .= '<p style="margin: 0 0 14px 0;">'.nl2br(dol_escape_htmltag($paragraph), false).'</p>';
            }
        }
        $html .= '</div>';

        return $html;
    }

    private function isEmailColumn($label)
    {
        $normalized = $this->normalizeLabel($label);
        return in_array($normalized, ['email', 'mail', 'courriel', 'adressemail', 'adressemailcandidat'], true);
    }

    private function getFirstname($candidateName)
    {
        $parts = preg_split('/\s+/', trim((string) $candidateName));
        return !empty($parts[0]) ? $parts[0] : '';
    }

    private function normalizeList($labels)
    {
        $out = [];
        foreach ($labels as $label) {
            $out[$this->normalizeLabel($label)] = true;
        }

        return $out;
    }

    private function normalizeLabel($label)
    {
        $label = html_entity_decode((string) $label, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (function_exists('dol_string_unaccent')) {
            $label = dol_string_unaccent($label);
        }
        $label = strtolower($label);

        return preg_replace('/[^a-z0-9]+/', '', $label);
    }
}
