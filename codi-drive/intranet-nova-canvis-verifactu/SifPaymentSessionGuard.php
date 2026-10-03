<?php

final class SifPaymentSessionGuard
{
    private const CSRF_KEY = 'sif_uc022_payment_csrf';

    public function actor(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $stored = $_SESSION['usuari'] ?? null;
        if ($stored === null) {
            throw new RuntimeException('Intranet session is not authenticated', 401);
        }

        $user = is_string($stored) ? @unserialize($stored) : $stored;
        if (!is_object($user) || !method_exists($user, 'getUsuari') || !method_exists($user, 'getRols')) {
            throw new RuntimeException('Invalid intranet user session', 401);
        }

        $userText = $user->getUsuari();
        $actorId = is_object($userText) && method_exists($userText, 'get')
            ? trim((string) $userText->get())
            : '';

        if ($actorId === '') {
            throw new RuntimeException('Invalid intranet actor', 401);
        }

        $roles = $this->loadCurrentRoles($actorId);
        if ($roles === []) {
            throw new RuntimeException('Intranet actor has no active roles', 403);
        }

        if (method_exists($user, 'replaceRols')) {
            $user->replaceRols(implode('|', $roles));
        }
        $_SESSION['usuari'] = serialize($user);

        return [
            'actor_id' => $actorId,
            'roles' => $roles,
        ];
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
            throw new RuntimeException('Invalid payment CSRF token', 403);
        }
    }

    private function loadCurrentRoles(string $actorId): array
    {
        $db = new ConnexioIntranet();
        $db->connectarBD();

        try {
            $stmt = $db->prepare('SELECT ROLS FROM usuaris WHERE USUARI = ? LIMIT 1');
            if ($stmt === false) {
                throw new RuntimeException('Could not prepare intranet role lookup', 500);
            }

            $stmt->bind_param('s', $actorId);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows() !== 1) {
                $db->closeStmt();
                throw new RuntimeException('Intranet actor is not active', 401);
            }

            $stmt->bind_result($rolesRaw);
            $stmt->fetch();
            $db->closeStmt();
        } finally {
            $db->desconectarBD();
        }

        $roles = [];
        foreach (explode('|', (string) $rolesRaw) as $role) {
            $value = strtoupper(trim($role));
            if ($value !== '') {
                $roles[$value] = true;
            }
        }

        return array_keys($roles);
    }
}
