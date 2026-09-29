<?php

class SifInternalClient
{
    private $baseUrl;
    private $clientId;
    private $secret;

    public function __construct($baseUrl = null, $clientId = null, $secret = null)
    {
        $this->baseUrl = rtrim((string) ($baseUrl !== null ? $baseUrl : getenv('SIF_INTERNAL_API_BASE_URL')), '/');
        $this->clientId = trim((string) ($clientId !== null ? $clientId : getenv('SIF_INTERNAL_API_CLIENT_ID')));
        $this->secret = (string) ($secret !== null ? $secret : getenv('SIF_INTERNAL_API_SECRET'));

        if ($this->baseUrl === '' || $this->clientId === '' || $this->secret === '') {
            throw new RuntimeException('SIF internal API is not configured');
        }

        $parts = parse_url($this->baseUrl);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            throw new RuntimeException('SIF internal API must use HTTPS');
        }
    }

    public function viewInvoice($user, $uuid)
    {
        $uuid = trim((string) $uuid);
        if ($uuid === '') {
            throw new InvalidArgumentException('Missing invoice UUID');
        }

        return $this->get(
            '/factures/view.php',
            ['uuid' => $uuid],
            $user
        );
    }

    public function searchInvoices($user, array $criteria, $limit = 50)
    {
        $allowed = [
            'uuid_factura',
            'num_visible',
            'billing_nif',
            'billing_email',
            'factura_relacionada',
        ];
        $query = [];

        foreach ($allowed as $key) {
            if (isset($criteria[$key]) && $criteria[$key] !== '') {
                $query[$key] = $criteria[$key];
            }
        }

        $query['limit'] = max(1, min(100, (int) $limit));

        return $this->get('/factures/search.php', $query, $user);
    }

    private function get($endpoint, array $query, $user)
    {
        if (!is_object($user) || !method_exists($user, 'getUsuari') || !method_exists($user, 'getRols')) {
            throw new RuntimeException('Authenticated intranet user is required');
        }

        $actorId = trim((string) $user->getUsuari());
        $roles = $user->getRols();
        if ($actorId === '' || !is_array($roles)) {
            throw new RuntimeException('Invalid intranet user context');
        }

        $roles = array_values(array_filter(array_map('trim', array_map('strval', $roles))));
        $rolesHeader = implode('|', $roles);
        $url = $this->baseUrl . $endpoint;
        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            throw new RuntimeException('Invalid SIF URL');
        }

        $requestUri = (string) ($parts['path'] ?? '/');
        if (isset($parts['query']) && $parts['query'] !== '') {
            $requestUri .= '?' . $parts['query'];
        }

        $timestamp = (string) time();
        $canonical = implode("\n", [
            $this->clientId,
            $timestamp,
            'GET',
            $requestUri,
            $actorId,
            $rolesHeader,
        ]);
        $signature = hash_hmac('sha256', $canonical, $this->secret);

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'ignore_errors' => true,
                'timeout' => 10,
                'header' => implode("\r\n", [
                    'Accept: application/json',
                    'X-SIF-Client: ' . $this->clientId,
                    'X-SIF-Timestamp: ' . $timestamp,
                    'X-SIF-Actor-Id: ' . $actorId,
                    'X-SIF-Actor-Roles: ' . $rolesHeader,
                    'X-SIF-Signature: ' . $signature,
                ]),
            ],
        ]);

        $body = file_get_contents($url, false, $context);
        if ($body === false) {
            throw new RuntimeException('Could not reach SIF internal API');
        }

        $status = $this->statusCode(isset($http_response_header) ? $http_response_header : []);
        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            throw new RuntimeException('Invalid SIF JSON response');
        }

        if ($status < 200 || $status >= 300 || empty($payload['ok'])) {
            $message = isset($payload['error']) ? (string) $payload['error'] : 'SIF request failed';
            throw new RuntimeException($message, $status > 0 ? $status : 500);
        }

        return $payload;
    }

    private function statusCode(array $headers)
    {
        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string) $header, $matches)) {
                return (int) $matches[1];
            }
        }

        return 0;
    }
}
