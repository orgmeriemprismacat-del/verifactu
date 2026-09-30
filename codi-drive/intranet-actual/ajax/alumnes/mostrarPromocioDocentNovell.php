<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

if (!filter_var(getenv('SIF_NOVICE_PROMOTION_UI_ENABLED') ?: '0', FILTER_VALIDATE_BOOLEAN)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'NOVICE_PROMOTION_UI_DISABLED'], JSON_UNESCAPED_UNICODE);
    return;
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'METHOD_NOT_ALLOWED'], JSON_UNESCAPED_UNICODE);
    return;
}

require_once $root . '/LegacyNovicePromotionContext.php';
require_once $root . '/LegacyInvoiceMutationAuthorization.php';
require_once $root . '/SifAuthenticatedActor.php';

$usuariObject = null;
$intranetObject = null;

try {
    [$usuariObject, $intranetObject] = LegacyNovicePromotionContext::open();
    LegacyInvoiceMutationAuthorization::assertSameOrigin();
    SifAuthenticatedActor::fromUser($usuariObject);

    $csrfSessio = (string) ($_SESSION['csrf_alumnes_lifecycle'] ?? '');
    $csrfRebut = (string) ($_POST['csrfToken'] ?? '');
    if ($csrfSessio === '' || $csrfRebut === '' || !hash_equals($csrfSessio, $csrfRebut)) {
        throw new RuntimeException('Token CSRF no vàlid', 403);
    }

    $dni = trim((string) ($_POST['dni'] ?? ''));
    if ($dni === '' || strlen($dni) > 30 || preg_match('/^[[:alnum:]. _-]+$/u', $dni) !== 1) {
        throw new InvalidArgumentException('Identitat no vàlida', 422);
    }

    $sifRoot = rtrim((string) (getenv('SIF_APP_ROOT') ?: dirname(__DIR__, 4) . '/sif'), '/');
    $autoload = $sifRoot . '/src/autoload.php';
    $configFile = $sifRoot . '/config/sif.php';

    if (!is_file($autoload) || !is_file($configFile)) {
        throw new RuntimeException('SIF no configurat', 503);
    }

    require $autoload;

    $config = require $configFile;
    $db = Prisma\Sif\Database\ConnectionFactory::make($config);
    $summary = (new Prisma\Sif\Service\NovicePromotionStudentSummaryService())->byIdentity($db, $dni);

    echo json_encode(
        ['ok' => true] + $summary,
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
} catch (Throwable $exception) {
    $code = (int) $exception->getCode();
    $status = $code >= 400 && $code <= 599 ? $code : 500;
    http_response_code($status);
    echo json_encode(
        [
            'ok' => false,
            'error' => $status >= 500 ? 'PROMOTION_SUMMARY_UNAVAILABLE' : $exception->getMessage(),
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
} finally {
    LegacyNovicePromotionContext::persist($usuariObject, $intranetObject);
}
