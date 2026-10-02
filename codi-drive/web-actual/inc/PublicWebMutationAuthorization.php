<?php

final class PublicWebMutationAuthorization
{
    public static function assertSameOriginAjax(): void
    {
        $configured = getenv('WEB_ALLOWED_ORIGINS') ?: 'https://www.prisma.cat;https://prisma.cat';
        $allowedOrigins = array_values(array_filter(array_map(
            static fn (string $value): string => rtrim(trim($value), '/'),
            preg_split('/[;,]/', $configured) ?: []
        )));

        if ($allowedOrigins === []) {
            throw new RuntimeException('No hi ha orígens web configurats', 403);
        }

        $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
        $referer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));

        if ($origin !== '' && !in_array(rtrim($origin, '/'), $allowedOrigins, true)) {
            throw new RuntimeException('Origen de petició no autoritzat', 403);
        }

        if ($origin === '' && $referer !== '') {
            $scheme = (string) parse_url($referer, PHP_URL_SCHEME);
            $host = (string) parse_url($referer, PHP_URL_HOST);
            if ($scheme === '' || $host === '') {
                throw new RuntimeException('Origen de petició no autoritzat', 403);
            }

            $refererOrigin = strtolower($scheme) . '://' . strtolower($host);
            $refererPort = parse_url($referer, PHP_URL_PORT);
            if ($refererPort !== null) {
                $refererOrigin .= ':' . $refererPort;
            }

            if (!in_array(rtrim($refererOrigin, '/'), $allowedOrigins, true)) {
                throw new RuntimeException('Origen de petició no autoritzat', 403);
            }
        }

        $requestedWith = strtolower(trim((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
        if ($requestedWith !== 'xmlhttprequest') {
            throw new RuntimeException('Petició AJAX requerida', 403);
        }
    }
}
