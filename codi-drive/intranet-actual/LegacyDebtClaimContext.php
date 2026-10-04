<?php

final class LegacyDebtClaimContext
{
    private const SURFACE_ROUTES = [
        'RECORDATORI' => '/facturacio/recordatori-pagament/',
        'PRIMERA_RECLAMACIO' => '/facturacio/primera-reclamacio/',
        'RECLAMACIO_FINAL' => '/facturacio/reclamacio-final/',
        'MOROSOS' => '/facturacio/morosos/',
    ];

    public static function open(string $surface): array
    {
        $root = __DIR__;
        if (!chdir($root)) {
            throw new RuntimeException('No es pot resoldre l’arrel de la intranet', 500);
        }

        ob_start();
        require $root . '/inc/comprovarSessio.php';
        ob_end_clean();

        if (!isset($configOk) || !$configOk || !isset($_SESSION['usuari']) || !isset($_SESSION['intranet'])) {
            throw new RuntimeException('Sessió no autoritzada', 401);
        }

        require_once $root . '/Intranet.php';
        require_once $root . '/LegacyInvoiceReadAuthorization.php';

        $user = unserialize($_SESSION['usuari']);
        $intranet = unserialize($_SESSION['intranet']);
        if (!is_object($user) || !is_object($intranet)) {
            throw new RuntimeException('Sessió no vàlida', 401);
        }

        $permissionPage = self::routeForSurface($surface);
        LegacyInvoiceReadAuthorization::assertCanView($user, $permissionPage);

        return [$user, $intranet, $root, $permissionPage];
    }

    public static function routeForSurface(string $surface): string
    {
        $surface = strtoupper(trim($surface));
        if ($surface === '' || !isset(self::SURFACE_ROUTES[$surface])) {
            throw new InvalidArgumentException('Superfície de morositat no vàlida', 422);
        }

        return self::SURFACE_ROUTES[$surface];
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_debt_claim'])) {
            $_SESSION['csrf_debt_claim'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['csrf_debt_claim'];
    }

    public static function persist($user, $intranet): void
    {
        if (is_object($user)) {
            $_SESSION['usuari'] = serialize($user);
        }
        if (is_object($intranet)) {
            $_SESSION['intranet'] = serialize($intranet);
        }
    }
}
