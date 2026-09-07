<?php

require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once __DIR__.'/mondaycandidateemail.class.php';

class MondayInboundEmailResolver
{
    private $db;
    private $candidateEmailService;

    public function __construct($db)
    {
        $this->db = $db;
        $this->candidateEmailService = new MondayCandidateEmail($db);
    }

    public function resolveFromHeaders($headers)
    {
        $normalizedHeaders = $this->normalizeHeaders($headers);
        $addresses = $this->extractAddressesFromHeaders($normalizedHeaders);

        $candidateTokenMap = array();
        $invalidCandidateSeen = false;
        $matchedEmail = '';

        foreach ($addresses as $address) {
            $tokenInfo = $this->extractTokenFromAddress($address);
            if (!empty($tokenInfo['token'])) {
                $token = $tokenInfo['token'];
                $candidateTokenMap[$token] = true;
                if ($matchedEmail === '') {
                    $matchedEmail = $address;
                }
                continue;
            }

            if (!empty($tokenInfo['invalid'])) {
                $invalidCandidateSeen = true;
            }
        }

        $result = array(
            'success' => false,
            'status' => 'token_absent',
            'message' => 'Aucun token candidat détecté.',
            'candidate_found' => false,
            'token_present' => $invalidCandidateSeen || !empty($candidateTokenMap),
            'token_valid' => false,
            'matched_email' => ''
        );

        if (empty($candidateTokenMap)) {
            if ($invalidCandidateSeen) {
                $result['status'] = 'token_invalid';
                $result['message'] = 'Le token candidat est invalide.';
            }

            $this->logResult($result['status'], array(
                'addresses' => count($addresses),
                'tokens' => 0,
                'invalid' => (int) $invalidCandidateSeen
            ));

            return $result;
        }

        if ($invalidCandidateSeen) {
            $result['status'] = 'token_invalid';
            $result['message'] = 'Le token candidat est invalide.';

            $this->logResult($result['status'], array(
                'addresses' => count($addresses),
                'tokens' => count($candidateTokenMap),
                'invalid' => 1
            ));

            return $result;
        }

        if (count($candidateTokenMap) > 1) {
            $result['status'] = 'multiple_matches';
            $result['message'] = 'Plusieurs tokens candidats différents ont été détectés.';
            $result['token_valid'] = true;

            $this->logResult($result['status'], array(
                'addresses' => count($addresses),
                'tokens' => count($candidateTokenMap)
            ));

            return $result;
        }

        $token = array_keys($candidateTokenMap);
        $token = reset($token);

        $matches = $this->candidateEmailService->findTasksByInboundEmailToken($token, 2);
        if (count($matches) === 0) {
            $result['status'] = 'no_candidate';
            $result['message'] = 'Aucun candidat correspondant.';
            $result['token_valid'] = true;

            $this->logResult($result['status'], array(
                'addresses' => count($addresses),
                'tokens' => 1,
                'token_hash' => $this->maskToken($token)
            ));

            return $result;
        }

        if (count($matches) > 1) {
            $result['status'] = 'multiple_matches';
            $result['message'] = 'Plusieurs correspondances candidates sont trouvées.';
            $result['token_valid'] = true;

            $this->logResult($result['status'], array(
                'addresses' => count($addresses),
                'tokens' => 1,
                'token_hash' => $this->maskToken($token),
                'matches' => count($matches)
            ));

            return $result;
        }

        $candidate = $matches[0];
        if (!$this->candidateEmailService->isManagedWorkspaceLabel($candidate['workspace_label'])) {
            $result['status'] = 'no_candidate';
            $result['message'] = 'Aucun candidat correspondant.';
            $result['token_valid'] = true;

            $this->logResult($result['status'], array(
                'addresses' => count($addresses),
                'tokens' => 1,
                'token_hash' => $this->maskToken($token),
                'workspace_allowed' => 0
            ));

            return $result;
        }

        $result = array(
            'success' => true,
            'status' => 'candidate_found',
            'message' => 'Candidat identifié.',
            'candidate_found' => true,
            'token_present' => true,
            'token_valid' => true,
            'matched_email' => $matchedEmail,
            'candidate' => array(
                'task_id' => (int) $candidate['id'],
                'label' => (string) $candidate['label'],
                'group_label' => (string) $candidate['group_label'],
                'workspace_label' => (string) $candidate['workspace_label']
            )
        );

        $this->logResult($result['status'], array(
            'addresses' => count($addresses),
            'tokens' => 1,
            'token_hash' => $this->maskToken($token),
            'workspace_allowed' => 1
        ));

        return $result;
    }

    private function normalizeHeaders($headers)
    {
        if (is_string($headers)) {
            $headers = $this->parseRawHeaderString($headers);
        } elseif (is_object($headers)) {
            $headers = get_object_vars($headers);
        }

        if (!is_array($headers)) {
            return array();
        }

        $normalized = array();
        foreach ($headers as $name => $value) {
            $normalized[strtolower(trim((string) $name))] = $this->flattenHeaderValue($value);
        }

        return $normalized;
    }

    private function flattenHeaderValue($value)
    {
        if (is_array($value)) {
            $parts = array();
            foreach ($value as $item) {
                $flat = $this->flattenHeaderValue($item);
                if ($flat !== '') {
                    $parts[] = $flat;
                }
            }

            return implode(', ', $parts);
        }

        if (is_object($value)) {
            if (method_exists($value, 'getValue')) {
                $resolved = $value->getValue();
                if (is_string($resolved) || is_numeric($resolved)) {
                    return trim((string) $resolved);
                }
            }

            if (method_exists($value, '__toString')) {
                return trim((string) $value);
            }

            $vars = get_object_vars($value);
            if (!empty($vars)) {
                return $this->flattenHeaderValue($vars);
            }

            return '';
        }

        return trim((string) $value);
    }

    private function parseRawHeaderString($headers)
    {
        $parsed = array();
        $currentKey = '';
        $lines = preg_split("/\r\n|\n|\r/", (string) $headers);
        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            if (preg_match('/^[\t ]+(.+)$/', $line, $matches) && $currentKey !== '') {
                $parsed[$currentKey] .= ' '.trim($matches[1]);
                continue;
            }

            if (preg_match('/^([A-Za-z0-9\-]+):\s*(.*)$/', $line, $matches)) {
                $currentKey = strtolower(trim($matches[1]));
                $value = trim($matches[2]);
                if (!isset($parsed[$currentKey])) {
                    $parsed[$currentKey] = $value;
                } else {
                    $parsed[$currentKey] .= ', '.$value;
                }
            }
        }

        return $parsed;
    }

    private function extractAddressesFromHeaders($headers)
    {
        $addresses = array();
        foreach (array('cc', 'bcc') as $headerName) {
            if (empty($headers[$headerName])) {
                continue;
            }

            $values = $this->extractEmailAddresses($headers[$headerName]);
            foreach ($values as $email) {
                $addresses[$email] = true;
            }
        }

        return array_keys($addresses);
    }

    private function extractEmailAddresses($value)
    {
        $emails = array();
        $value = (string) $value;
        if ($value === '') {
            return $emails;
        }

        if (preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value, $matches)) {
            foreach ($matches[0] as $email) {
                $email = strtolower(trim($email));
                if ($email !== '') {
                    $emails[$email] = true;
                }
            }
        }

        return array_keys($emails);
    }

    private function extractTokenFromAddress($email)
    {
        $email = strtolower(trim((string) $email));
        if ($email === '') {
            return array('token' => '', 'invalid' => false);
        }

        $atPos = strrpos($email, '@');
        if ($atPos === false) {
            return array('token' => '', 'invalid' => false);
        }

        $localPart = substr($email, 0, $atPos);
        $domainPart = substr($email, $atPos + 1);
        if ($localPart === '' || $domainPart === '') {
            return array('token' => '', 'invalid' => false);
        }

        $plusPos = strrpos($localPart, '+');
        if ($plusPos === false) {
            return array('token' => '', 'invalid' => false);
        }

        $token = substr($localPart, $plusPos + 1);
        if ($token === '') {
            return array('token' => '', 'invalid' => true);
        }

        if (!$this->candidateEmailService->isValidInboundEmailToken($token)) {
            return array('token' => '', 'invalid' => true);
        }

        return array('token' => $token, 'invalid' => false);
    }

    private function maskToken($token)
    {
        $token = strtolower(trim((string) $token));
        if ($token === '') {
            return '';
        }

        return substr(hash('sha256', $token), 0, 12);
    }

    private function logResult($status, array $context = array())
    {
        $message = 'status='.$status;
        if (!empty($context)) {
            $message .= ' '.json_encode($context);
        }

        if (function_exists('dol_syslog')) {
            dol_syslog(__CLASS__.' '.$message, LOG_INFO);
            return;
        }

        error_log(__CLASS__.' '.$message);
    }
}
