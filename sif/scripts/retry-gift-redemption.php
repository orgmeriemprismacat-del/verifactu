<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\CommercialEntitlementRepository;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;
use Prisma\Sif\Service\GiftEnrollmentStager;
use Prisma\Sif\Service\GiftRedemptionOrchestrator;
use Prisma\Sif\Service\GiftRedemptionService;
use Prisma\Sif\Service\GiftRedemptionTrustedContextResolver;
use Prisma\Sif\Service\LegacyGiftUsageReconciler;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script is CLI-only.\n");
    exit(2);
}

$enrollmentId = null;
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--enrollment-id=')) {
        $enrollmentId = substr($argument, strlen('--enrollment-id='));
    }
}

if (!is_numeric($enrollmentId) || (int) $enrollmentId <= 0) {
    fwrite(STDERR, "Usage: php retry-gift-redemption.php --enrollment-id=<ID_INSC>\n");
    exit(2);
}

$config = require dirname(__DIR__) . '/config/sif.php';
$sifDb = ConnectionFactory::make($config);
$legacyDb = ConnectionFactory::makeLegacy($config);
$enrollmentId = (int) $enrollmentId;

$statement = $legacyDb->prepare(
    'SELECT pag_observacions FROM inscripcions WHERE ID = ?'
);
$statement->execute([$enrollmentId]);
$giftCode = trim((string) $statement->fetchColumn());
if ($giftCode === '' || strlen($giftCode) > 200) {
    throw SifException::conflict('Committed gift enrollment has no recoverable gift code');
}

$entitlements = new CommercialEntitlementRepository(new UuidGenerator());
$orchestrator = new GiftRedemptionOrchestrator(
    new GiftRedemptionTrustedContextResolver($entitlements),
    new GiftEnrollmentStager(new UuidGenerator(), $entitlements),
    new GiftRedemptionService(
        $entitlements,
        new EnrollmentFundMovementRepository(new UuidGenerator())
    ),
    new LegacyGiftUsageReconciler()
);

try {
    $result = $orchestrator->execute(
        $sifDb,
        $legacyDb,
        $enrollmentId,
        $giftCode,
        'WEB',
        'UC018-RECOVERY-INSC-' . $enrollmentId,
        'gift-redemption-recovery-cli'
    );

    fwrite(
        STDOUT,
        json_encode(
            [
                'ok' => true,
                'enrollment_id' => $enrollmentId,
                'stage' => $result['stage'],
                'redemption' => $result['redemption'],
                'legacy_reconciliation' => $result['legacy_reconciliation'],
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
        ) . PHP_EOL
    );
    exit(0);
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        json_encode(
            [
                'ok' => false,
                'enrollment_id' => $enrollmentId,
                'error_class' => get_class($exception),
                'error' => $exception->getMessage(),
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
        ) . PHP_EOL
    );
    exit(1);
}
