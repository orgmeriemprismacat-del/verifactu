<?php

final class SifRectificationAccess
{
    private const PAGE_URL = '/alumnes/factura/';
    private const CSRF_SESSION_KEY = 'sif_uc005_csrf';
    private const CSRF_CREATED_KEY = 'sif_uc005_csrf_created_at';
    private const CSRF_TTL_SECONDS = 7200;

    public static function resolve(object $user, object $intranet): array
    {
        if (method_exists($intranet, 'consultaRolsUsuari') && method_exists($user, 'replaceRols')) {
            $user->replaceRols((string) $intranet->consultaRolsUsuari());
        }

        LegacyInvoiceMutationAuthorization::assertCanEdit($user, $intranet, self::PAGE_URL);
        [$actorId, $roles] = SifAuthenticatedActor::fromUser($user);

        $normalized = [];
        foreach ($roles as $role) {
            $value = strtoupper(trim((string) $role));
            if ($value !== '') {
                $normalized[$value] = true;
            }
        }

        if ($normalized === []) {
            throw new RuntimeException('No hi ha rols útils per rectificar factures', 401);
        }

        return [
            'actor_id' => $actorId,
            'roles' => array_keys($normalized),
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
            throw new RuntimeException('Token CSRF UC-005 invàlid o caducat', 403);
        }
    }
}
