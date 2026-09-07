<?php

require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';

class OutlooksyncGraphClient
{
	const GRAPH_URL = 'https://graph.microsoft.com/v1.0';
	const TOKEN_URL = 'https://login.microsoftonline.com/%s/oauth2/v2.0/token';

	/** @var DoliDB */
	private $db;

	/** @var string */
	private $accessToken = '';

	public function __construct($db)
	{
		$this->db = $db;
	}

	public function createEvent($mailbox, array $payload)
	{
		return $this->request('POST', '/users/'.rawurlencode($mailbox).'/events', $payload);
	}

	public function updateEvent($mailbox, $eventId, array $payload)
	{
		return $this->request('PATCH', '/users/'.rawurlencode($mailbox).'/events/'.rawurlencode($eventId), $payload);
	}

	public function deleteEvent($mailbox, $eventId)
	{
		return $this->request('DELETE', '/users/'.rawurlencode($mailbox).'/events/'.rawurlencode($eventId), null);
	}

	public function getCalendarViewDelta($mailbox, $deltaLink = '')
	{
		if ($deltaLink !== '') {
			return $this->request('GET', $deltaLink, null);
		}

		$pastDays = max(0, (int) getDolGlobalString('OUTLOOKSYNC_IMPORT_PAST_DAYS', '7'));
		$futureDays = max(1, (int) getDolGlobalString('OUTLOOKSYNC_IMPORT_FUTURE_DAYS', '365'));
		$query = http_build_query(array(
			'startDateTime' => gmdate('Y-m-d\TH:i:s\Z', dol_now() - ($pastDays * 86400)),
			'endDateTime' => gmdate('Y-m-d\TH:i:s\Z', dol_now() + ($futureDays * 86400)),
		));

		return $this->request('GET', '/users/'.rawurlencode($mailbox).'/calendarView/delta?'.$query, null);
	}

	private function request($method, $path, $payload)
	{
		$token = $this->getAccessToken();
		if ($token === '') {
			return array('error' => 'Missing Microsoft Graph access token');
		}

		$headers = array(
			'Authorization: Bearer '.$token,
			'Content-Type: application/json',
			'Accept: application/json',
			'Prefer: outlook.timezone="UTC"',
		);

		$url = (preg_match('/^https:\/\/graph\.microsoft\.com\//', $path) ? $path : self::GRAPH_URL.$path);
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		if ($payload !== null) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
		}
		$body = curl_exec($ch);
		$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		curl_close($ch);

		if ($body === false || $status < 200 || $status >= 300) {
			return array('error' => 'Graph '.$method.' '.$path.' failed: HTTP '.$status.' '.$error.' '.$body);
		}

		return $body !== '' ? json_decode($body, true) : array();
	}

	private function getAccessToken()
	{
		if ($this->accessToken !== '') {
			return $this->accessToken;
		}

		$tenant = getDolGlobalString('OUTLOOKSYNC_TENANT_ID');
		$clientId = getDolGlobalString('OUTLOOKSYNC_CLIENT_ID');
		$clientSecret = $this->getClientSecret();
		if ($tenant === '' || $clientId === '' || $clientSecret === '') {
			return '';
		}

		$postfields = http_build_query(array(
			'client_id' => $clientId,
			'client_secret' => $clientSecret,
			'scope' => 'https://graph.microsoft.com/.default',
			'grant_type' => 'client_credentials',
		));

		$ch = curl_init(sprintf(self::TOKEN_URL, rawurlencode($tenant)));
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
		$body = curl_exec($ch);
		$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		curl_close($ch);

		$data = $body !== false ? json_decode($body, true) : null;
		if ($status < 200 || $status >= 300 || empty($data['access_token'])) {
			dol_syslog('Outlooksync token error: HTTP '.$status.' '.$error.' '.$body, LOG_ERR);
			return '';
		}

		$this->accessToken = $data['access_token'];
		return $this->accessToken;
	}

	private function getClientSecret()
	{
		$secret = getenv('OUTLOOKSYNC_CLIENT_SECRET');
		if ($secret === false || $secret === '') {
			$secret = $_ENV['OUTLOOKSYNC_CLIENT_SECRET'] ?? '';
		}
		if ($secret === '' && !empty($_SERVER['OUTLOOKSYNC_CLIENT_SECRET'])) {
			$secret = $_SERVER['OUTLOOKSYNC_CLIENT_SECRET'];
		}
		return trim((string) $secret);
	}
}
