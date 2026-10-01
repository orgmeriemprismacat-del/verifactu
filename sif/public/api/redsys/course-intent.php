<?php

require dirname(__DIR__, 3) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Http\JsonResponse;
use Prisma\Sif\Repository\CommercialOperationPartyRepository;
use Prisma\Sif\Repository\CommercialOperationRepository;
use Prisma\Sif\Repository\DiscountValidationRepository;
use Prisma\Sif\Repository\InternalApiRequestRepository;
use Prisma\Sif\Repository\LegacyCourseSnapshotRepository;
use Prisma\Sif\Repository\LegacyPrismaStudentHistoryRepository;
use Prisma\Sif\Repository\OperationalEventRepository;
use Prisma\Sif\Repository\RedsysPaymentIntentRepository;
use Prisma\Sif\Service\CommercialOfferService;
use Prisma\Sif\Service\InternalApiAuthenticator;
use Prisma\Sif\Service\LegacyPrismaStudentPriceSnapshotResolver;
use Prisma\Sif\Service\PrismaStudentCourseCheckoutService;
use Prisma\Sif\Service\RedsysCoursePaymentIntentService;
use Prisma\Sif\Service\RedsysDsOrderGenerator;
use Prisma\Sif\Service\RedsysPaymentIntentService;
use Prisma\Sif\Domain\PrismaStudentDiscountPolicy;

header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    JsonResponse::send(['ok' => false, 'error' => 'Method not allowed'], 405);
    return;
}

$rawBody = file_get_contents('php://input');
if ($rawBody === false) {
    JsonResponse::send(['ok' => false, 'error' => 'Could not read request body'], 400);
    return;
}

try {
    $config = require dirname(__DIR__, 3) . '/config/sif.php';
    $sifDb = ConnectionFactory::make($config);
    $legacyDb = ConnectionFactory::makeLegacy($config);

    $internalApi = $config['internal_api'] ?? [];
    $actor = (new InternalApiAuthenticator(
        $sifDb,
        new InternalApiRequestRepository(),
        (string) ($internalApi['key_id'] ?? ''),
        (string) ($internalApi['secret'] ?? ''),
        (int) ($internalApi['max_clock_skew_seconds'] ?? 300)
    ))->authenticate(
        $_SERVER,
        $rawBody,
        'POST',
        (string) ($internalApi['redsys_course_intent_signed_path'] ?? '/api/redsys/course-intent.php')
    );

    if (!in_array('PAYMENT_CHANNEL', (array) ($actor['roles'] ?? []), true)) {
        throw SifException::forbidden('PAYMENT_CHANNEL role is required');
    }

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        throw SifException::validation('Invalid JSON');
    }

    $uuid = new UuidGenerator();
    $transactions = new TransactionRunner($sifDb);
    $operations = new CommercialOperationRepository();
    $intentRepository = new RedsysPaymentIntentRepository();
    $intentService = new RedsysPaymentIntentService($intentRepository, $uuid);
    $offers = new CommercialOfferService(
        $transactions,
        $operations,
        new DiscountValidationRepository(),
        new OperationalEventRepository($uuid),
        $uuid,
        new CommercialOperationPartyRepository()
    );

    $service = new RedsysCoursePaymentIntentService(
        new LegacyCourseSnapshotRepository(),
        $intentService,
        new RedsysDsOrderGenerator(),
        new PrismaStudentCourseCheckoutService(
            new LegacyPrismaStudentHistoryRepository(),
            new PrismaStudentDiscountPolicy(),
            $offers,
            $operations,
            $intentRepository,
            $intentService,
            $transactions
        ),
        new LegacyPrismaStudentPriceSnapshotResolver()
    );

    JsonResponse::send([
        'ok' => true,
        'intent' => $service->create($sifDb, $legacyDb, [
            'idpag' => $payload['idpag'] ?? null,
            'requested_amount' => $payload['requested_amount'] ?? null,
            'terminal' => $payload['terminal'] ?? '1',
            'created_by' => (string) ($actor['actor_id'] ?? 'pay-prisma-cat'),
        ]),
    ]);
} catch (\Throwable $exception) {
    JsonResponse::fromThrowable($exception);
}
