<?php

$root = dirname(__DIR__, 2);
require_once $root . '/LegacyInvoiceReadContext.php';
require_once $root . '/LegacyInvoiceMutationAuthorization.php';

$user = null;
$intranet = null;

try {
    [$user, $intranet] = LegacyInvoiceReadContext::open();

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['GET', 'POST'], true)) {
        throw new RuntimeException('Mètode no permès', 405);
    }
    if ($method === 'POST') {
        LegacyInvoiceMutationAuthorization::assertSameOrigin();
    }
    $request = $method === 'POST' ? $_POST : $_GET;

    $dnies = (string) ($request['dnies'] ?? '');
    $orderBy = (string) ($request['orderBy'] ?? '');
    $asc = (string) ($request['asc'] ?? '');

    $dniCandidates = array_values(array_filter(
        array_map('trim', explode('|', $dnies)),
        static fn (string $value): bool => $value !== ''
    ));
    if (count($dniCandidates) > 2000 || strlen($dnies) > 128000) {
        throw new InvalidArgumentException('Massa candidats de factura', 422);
    }

    echo $intranet->mostrarTaulaUsuaris2_Alumnes($dnies, $orderBy, $asc);
} catch (Throwable $exception) {
    $code = (int) $exception->getCode();
    http_response_code($code >= 400 && $code <= 599 ? $code : 500);
    echo 'Error: ' . $exception->getMessage();
} finally {
    LegacyInvoiceReadContext::persist($user, $intranet);
}
