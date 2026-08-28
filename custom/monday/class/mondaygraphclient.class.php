<?php

class MondayGraphClient
{
    const GRAPH_BASE_URL = 'https://graph.microsoft.com/v1.0';
    const TOKEN_BASE_URL = 'https://login.microsoftonline.com';

    private $tenantId;
    private $clientId;
    private $clientSecret;
    private $accessToken = '';
    private $accessTokenExpiresAt = 0;
    public $error = '';

    public function __construct($tenantId, $clientId, $clientSecret)
    {
        $this->tenantId = trim((string) $tenantId);
        $this->clientId = trim((string) $clientId);
        $this->clientSecret = (string) $clientSecret;
    }

    public function isConfigured()
    {
        return $this->tenantId !== '' && $this->clientId !== '' && $this->clientSecret !== '';
    }

    public function get($url)
    {
        if (!$this->ensureAccessToken()) {
            return false;
        }

        $absoluteUrl = preg_match('/^https?:\/\//i', (string) $url) ? $url : self::GRAPH_BASE_URL.$url;
        return $this->request('GET', $absoluteUrl, array(
            'Authorization: Bearer '.$this->accessToken,
            'Accept: application/json'
        ));
    }

    private function ensureAccessToken()
    {
        if ($this->accessToken !== '' && $this->accessTokenExpiresAt > time() + 60) {
            return true;
        }

        if (!$this->isConfigured()) {
            $this->error = 'Microsoft Graph credentials are incomplete.';
            return false;
        }

        $url = self::TOKEN_BASE_URL.'/'.rawurlencode($this->tenantId).'/oauth2/v2.0/token';
        $body = http_build_query(array(
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope' => 'https://graph.microsoft.com/.default',
            'grant_type' => 'client_credentials'
        ), '', '&');

        $response = $this->request('POST', $url, array(
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ), $body);

        if (!is_array($response) || empty($response['access_token'])) {
            $this->error = $this->error ?: 'Microsoft Graph token response is invalid.';
            return false;
        }

        $this->accessToken = (string) $response['access_token'];
        $this->accessTokenExpiresAt = time() + max(300, (int) ($response['expires_in'] ?? 3600));

        return true;
    }

    private function request($method, $url, array $headers, $body = null)
    {
        if (!function_exists('curl_init')) {
            $this->error = 'PHP cURL extension is required for Microsoft Graph.';
            return false;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $curlError = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            $this->error = 'Microsoft Graph HTTP error: '.$curlError;
            return false;
        }

        $decoded = json_decode((string) $raw, true);
        if ($status < 200 || $status >= 300) {
            $message = is_array($decoded) && !empty($decoded['error']['message']) ? $decoded['error']['message'] : substr((string) $raw, 0, 500);
            $this->error = 'Microsoft Graph returned HTTP '.$status.': '.$message;
            return false;
        }

        return is_array($decoded) ? $decoded : array();
    }
}
