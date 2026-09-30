<?php

final class SifInvoiceBeforePaymentAccess
{
    private const PAGE_URL = '/alumnes/genera-factura-abans-pagar/';
    private const CSRF_SESSION_KEY = 'sif_uc004_csrf';
    private const CSRF_CREATED_KEY = 'sif_uc004_csrf_created_at';
    private const CSRF_TTL_SECONDS = 7200;

    public static function resolve(object $user, object $intranet): array
    {
        foreach (['getUsuari', 'getRols', 'tePermisVisualitzacio'] as $method) {
            if (!method_exists($user, $method)) {
                throw new RuntimeException('Authenticated user object is incomplete');
            }
        }

        foreach (['consultaRolsUsuari', 'consultaRolsEdiicio'] as $method) {
            if (!method_exists($intranet, $method)) {
                throw new RuntimeException('Intranet authorization object is incomplete');
            }
        }

        $currentRoles = trim((string) $intranet->consultaRolsUsuari());
        if (method_exists($user, 'replaceRols')) {
            $user->replaceRols($currentRoles);
        }

        $editRoles = trim((string) $intranet->consultaRolsEdiicio(self::PAGE_URL));
        if ($editRoles === '' || !$user->tePermisVisualitzacio($editRoles)) {
            throw new RuntimeException('Invoice-before-payment edit permission denied', 403);
        }

        $actorText = $user->getUsuari();
        $actorId = is_object($actorText) && method_exists($actorText, 'get')
            ? trim((string) $actorText->get())
            : '';

        $roles = $user->getRols();
        if ($actorId === '' || !is_array($roles) || $roles === []) {
            throw new RuntimeException('Authenticated actor has no usable identity or roles', 401);
        }

        $normalizedRoles = [];
        foreach ($roles as $role) {
            $value = strtoupper(trim((string) $role));
            if ($value !== '') {
                $normalizedRoles[$value] = true;
            }
        }

        if ($normalizedRoles === []) {
            throw new RuntimeException('Authenticated actor has no usable roles', 401);
        }

        return [
            'actor_id' => $actorId,
            'roles' => array_keys($normalizedRoles),
            'edit_roles' => $editRoles,
        ];
    }

    public static function csrfToken(): string
    {
        $token = (string) ($_SESSION[self::CSRF_SESSION_KEY] ?? '');
        $createdAt = (int) ($_SESSION[self::CSRF_CREATED_KEY] ?? 0);

        if (
            preg_match('/^[a-f0-9]{64}$/D', $token) !== 1
            || $createdAt <= 0
            || time() - $createdAt > self::CSRF_TTL_SECONDS
        ) {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::CSRF_SESSION_KEY] = $token;
            $_SESSION[self::CSRF_CREATED_KEY] = time();
        }

        return $token;
    }

    public static function assertCsrf(array $server): void
    {
        $expected = (string) ($_SESSION[self::CSRF_SESSION_KEY] ?? '');
        $createdAt = (int) ($_SESSION[self::CSRF_CREATED_KEY] ?? 0);
        $received = strtolower(trim((string) ($server['HTTP_X_CSRF_TOKEN'] ?? '')));

        if (
            preg_match('/^[a-f0-9]{64}$/D', $expected) !== 1
            || $createdAt <= 0
            || time() - $createdAt > self::CSRF_TTL_SECONDS
            || preg_match('/^[a-f0-9]{64}$/D', $received) !== 1
            || !hash_equals($expected, $received)
        ) {
            throw new RuntimeException('Invalid or expired CSRF token', 403);
        }
    }
}
