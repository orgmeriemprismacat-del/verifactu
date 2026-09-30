<?php

final class LegacyUsocContext
{
    public static function open(): array
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

        LegacyInvoiceReadAuthorization::assertCanView($user, '/alumnes/mostrar-alumne/');

        return [$user, $intranet, $root];
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
