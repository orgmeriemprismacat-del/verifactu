<?php

final class LegacyInvoiceMutationAuthorization
{
    public static function assertCanEdit($user, $intranet, string $page = '/alumnes/factura/'): void
    {
        if (!is_object($user)
            || !method_exists($user, 'tePermisVisualitzacio')
            || !is_object($intranet)
            || !method_exists($intranet, 'consultaRolsEdiicio')) {
            throw new RuntimeException('No es pot validar el permís d’edició', 403);
        }

        $rolesEditar = (string) $intranet->consultaRolsEdiicio($page);
        if ($rolesEditar === '' || !$user->tePermisVisualitzacio($rolesEditar)) {
            throw new RuntimeException('No tens permisos per modificar factures', 403);
        }
    }

    public static function assertSameOrigin(): void
    {
        $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
        $referer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
        $expected = 'https://intranet.prisma.cat';

        if ($origin !== '' && $origin !== $expected) {
            throw new RuntimeException('Origen de petició no autoritzat', 403);
        }

        if ($origin === '' && $referer !== '' && !str_starts_with($referer, $expected . '/')) {
            throw new RuntimeException('Origen de petició no autoritzat', 403);
        }

        $requestedWith = strtolower(trim((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
        if ($requestedWith !== 'xmlhttprequest') {
            throw new RuntimeException('Petició AJAX requerida', 403);
        }
    }
}
