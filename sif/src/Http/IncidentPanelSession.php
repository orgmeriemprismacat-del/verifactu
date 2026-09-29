<?php

namespace Prisma\Sif\Http;

use Prisma\Sif\Exception\SifException;

final class IncidentPanelSession
{
    private const ACTOR_KEY = 'sif_incident_panel_actor';
    private const CSRF_KEY = 'sif_incident_panel_csrf';

    public function __construct(private string $sessionName = 'SIFPANELSESSID')
    {
        $this->sessionName = trim($this->sessionName) !== '' ? trim($this->sessionName) : 'SIFPANELSESSID';
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name($this->sessionName);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/sif/',
            'domain' => '',
            'secure' => $this->isHttps(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }

    public function establish(array $actor): void
    {
        $actorId = trim((string) ($actor['actor_id'] ?? ''));
        $roles = is_array($actor['roles'] ?? null) ? $actor['roles'] : [];
        if ($actorId === '' || $roles === []) {
            throw SifException::unauthorized('Invalid SIF panel actor');
        }

        session_regenerate_id(true);
        $_SESSION[self::ACTOR_KEY] = [
            'actor_id' => $actorId,
            'roles' => array_values($roles),
            'request_id' => (string) ($actor['request_id'] ?? ''),
            'source_channel' => 'SIF_PANEL',
            'authenticated_at' => time(),
        ];
        $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
    }

    public function actor(): array
    {
        $actor = $_SESSION[self::ACTOR_KEY] ?? null;
        if (!is_array($actor)) {
            throw SifException::unauthorized('SIF panel session is not authenticated');
        }

        $actorId = trim((string) ($actor['actor_id'] ?? ''));
        $roles = is_array($actor['roles'] ?? null) ? $actor['roles'] : [];
        if ($actorId === '' || $roles === []) {
            throw SifException::unauthorized('Invalid SIF panel session');
        }

        return $actor;
    }

    public function csrfToken(): string
    {
        $this->actor();

        $token = (string) ($_SESSION[self::CSRF_KEY] ?? '');
        if ($token === '') {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::CSRF_KEY] = $token;
        }

        return $token;
    }

    public function assertCsrf(string $token): void
    {
        $stored = (string) ($_SESSION[self::CSRF_KEY] ?? '');
        if ($stored === '' || $token === '' || !hash_equals($stored, $token)) {
            throw SifException::forbidden('Invalid SIF panel CSRF token');
        }
    }

    public function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    private function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }
}
