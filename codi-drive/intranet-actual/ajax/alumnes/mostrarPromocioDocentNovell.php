<?php

declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuari'], $_SESSION['intranet'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'UNAUTHENTICATED'], JSON_UNESCAPED_UNICODE);
    exit;
}

$dni = trim((string) ($_GET['dni'] ?? ''));
if ($dni === '' || strlen($dni) > 30) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'INVALID_IDENTITY'], JSON_UNESCAPED_UNICODE);
    exit;
}

$sifRoot = rtrim((string) (getenv('SIF_APP_ROOT') ?: dirname(__DIR__, 4) . '/sif'), '/');
$autoload = $sifRoot . '/src/autoload.php';
$configFile = $sifRoot . '/config/sif.php';

if (!is_file($autoload) || !is_file($configFile)) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'SIF_NOT_CONFIGURED'], JSON_UNESCAPED_UNICODE);
    exit;
}

require $autoload;

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Service\NovicePromotionStudentSummaryService;

try {
    $config = require $configFile;
    $db = ConnectionFactory::make($config);
    $summary = (new NovicePromotionStudentSummaryService())->byIdentity($db, $dni);

    echo json_encode(
        ['ok' => true] + $summary,
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
} catch (\Throwable $exception) {
    http_response_code(500);
    echo json_encode(
        ['ok' => false, 'error' => 'PROMOTION_SUMMARY_UNAVAILABLE'],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
}
