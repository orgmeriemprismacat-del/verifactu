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
        $configured = getenv('INTRANET_ALLOWED_ORIGINS') ?: 'https://intranet.prisma.cat';
        $allowedOrigins = array_values(array_filter(array_map(
            static fn (string $value): string => rtrim(trim($value), '/'),
            preg_split('/[;,]/', $configured) ?: []
        )));

        if ($allowedOrigins === []) {
            throw new RuntimeException('No hi ha orígens de la intranet configurats', 403);
        }

        if ($origin !== '' && !in_array(rtrim($origin, '/'), $allowedOrigins, true)) {
            throw new RuntimeException('Origen de petició no autoritzat', 403);
        }

        if ($origin === '' && $referer !== '') {
            $refererOrigin = parse_url($referer, PHP_URL_SCHEME) . '://' . parse_url($referer, PHP_URL_HOST);
            $refererPort = parse_url($referer, PHP_URL_PORT);
            if ($refererPort !== null) {
                $refererOrigin .= ':' . $refererPort;
            }
            if (!in_array($refererOrigin, $allowedOrigins, true)) {
                throw new RuntimeException('Origen de petició no autoritzat', 403);
            }
        }

        $requestedWith = strtolower(trim((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
        if ($requestedWith !== 'xmlhttprequest') {
            throw new RuntimeException('Petició AJAX requerida', 403);
        }
    }
}
