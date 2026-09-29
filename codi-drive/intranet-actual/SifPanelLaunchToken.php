<?php

final class SifPanelLaunchToken
{
    private string $url;
    private string $path;
    private string $keyId;
    private string $secret;

    public function __construct(
        ?string $url = null,
        ?string $path = null,
        ?string $keyId = null,
        ?string $secret = null
    ) {
        $this->url = trim((string) ($url ?? getenv('SIF_PANEL_INCIDENTS_URL') ?: 'https://pay.prisma.cat/sif/incidencies/'));
        $this->path = trim((string) ($path ?? getenv('SIF_PANEL_INCIDENTS_PATH') ?: '/sif/incidencies/'));
        $this->keyId = trim((string) (
            $keyId ?? getenv('SIF_PANEL_LAUNCH_KEY_ID') ?: getenv('SIF_INTERNAL_API_KEY_ID') ?: ''
        ));
        $this->secret = trim((string) (
            $secret ?? getenv('SIF_PANEL_LAUNCH_SECRET') ?: getenv('SIF_INTERNAL_API_SECRET') ?: ''
        ));

        if ($this->url === '' || $this->path === '' || $this->keyId === '' || $this->secret === '') {
            throw new RuntimeException('SIF panel launch is not configured');
        }
        $parts = parse_url($this->url);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            throw new RuntimeException('SIF panel launch URL requires HTTPS');
        }
    }

    public function create(string $actorId, array $roles): array
    {
        $actorId = trim($actorId);
        if ($actorId === '') {
            throw new InvalidArgumentException('Missing SIF panel actor id');
        }

        $roles = $this->normalizeRoles($roles);
        if ($roles === []) {
            throw new InvalidArgumentException('Missing SIF panel actor roles');
        }

        $timestamp = (string) time();
        $requestId = $this->uuidV4();
        $canonicalRoles = implode(',', $roles);
        $canonical = implode("\n", [
            'SIF_PANEL_LAUNCH',
            'POST',
            $this->path,
            $timestamp,
            $requestId,
            $actorId,
            $canonicalRoles,
        ]);

        return [
            'url' => $this->url,
            'fields' => [
                'key_id' => $this->keyId,
                'timestamp' => $timestamp,
                'request_id' => $requestId,
                'actor_id' => $actorId,
                'roles' => $canonicalRoles,
                'signature' => hash_hmac('sha256', $canonical, $this->secret),
            ],
        ];
    }

    private function normalizeRoles(array $roles): array
    {
        $normalized = [];
        foreach ($roles as $role) {
            $value = strtoupper(trim((string) $role));
            if ($value !== '') {
                $normalized[$value] = true;
            }
        }
        $result = array_keys($normalized);
        sort($result, SORT_STRING);
        return $result;
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
