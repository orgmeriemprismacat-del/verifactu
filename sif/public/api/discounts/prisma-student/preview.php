<?php

declare(strict_types=1);

require dirname(__DIR__, 4) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Domain\PrismaStudentDiscountPolicy;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Http\JsonResponse;
use Prisma\Sif\Repository\InternalApiRequestRepository;
use Prisma\Sif\Repository\LegacyPrismaStudentHistoryRepository;
use Prisma\Sif\Service\InternalApiAuthenticator;
use Prisma\Sif\Service\PrismaStudentEnrollmentOfferResolver;

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
    $config = require dirname(__DIR__, 4) . '/config/sif.php';
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
        (string) (
            $internalApi['prisma_student_offer_signed_path']
            ?? '/api/discounts/prisma-student/preview.php'
        )
    );

    assertPrismaStudentOfferRole(
        $actor,
        (array) (($config['prisma_student_offer'] ?? [])['preview_roles'] ?? [])
    );

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        throw SifException::validation('Invalid JSON');
    }
    rejectPrismaStudentOfferAuthorityFields($payload);

    $document = requiredPrismaStudentOfferString(
        $payload['document'] ?? null,
        'document',
        40
    );
    $year = positivePrismaStudentOfferYear($payload['year'] ?? null);
    $month = requiredPrismaStudentOfferMonth($payload['month'] ?? null);
    $courseCode = requiredPrismaStudentOfferString(
        $payload['course_code'] ?? null,
        'course_code',
        80
    );

    $offer = (new PrismaStudentEnrollmentOfferResolver(
        new LegacyPrismaStudentHistoryRepository(),
        new PrismaStudentDiscountPolicy()
    ))->resolve($legacyDb, $document, $year, $month, $courseCode);

    JsonResponse::send([
        'ok' => true,
        'offer' => $offer,
    ]);
} catch (\Throwable $exception) {
    JsonResponse::fromThrowable($exception);
}

function assertPrismaStudentOfferRole(array $actor, array $allowedRoles): void
{
    $actorRoles = array_values(array_filter(array_map(
        static fn (mixed $role): string => strtoupper(trim((string) $role)),
        (array) ($actor['roles'] ?? [])
    )));
    $allowed = array_values(array_filter(array_map(
        static fn (mixed $role): string => strtoupper(trim((string) $role)),
        $allowedRoles
    )));

    if ($allowed === [] || array_intersect($actorRoles, $allowed) === []) {
        throw SifException::forbidden('Alumne PrisMa offer preview role is not authorized');
    }
}

function rejectPrismaStudentOfferAuthorityFields(array $payload): void
{
    foreach ([
        'gross_amount',
        'discount_amount',
        'net_amount',
        'price',
        'preuCar',
        'preuDescompte',
        'tipusDescompte',
        'discount_type',
        'trusted_price_snapshot',
    ] as $forbidden) {
        if (array_key_exists($forbidden, $payload)) {
            throw SifException::validation(
                'Alumne PrisMa price authority must be resolved inside SIF: ' . $forbidden
            );
        }
    }
}

function requiredPrismaStudentOfferString(
    mixed $value,
    string $field,
    int $maxLength
): string {
    $value = trim((string) $value);
    if ($value === '' || strlen($value) > $maxLength) {
        throw SifException::validation('Invalid Alumne PrisMa offer field: ' . $field);
    }

    return $value;
}

function positivePrismaStudentOfferYear(mixed $value): int
{
    $raw = trim((string) $value);
    if ($raw === '' || !ctype_digit($raw) || (int) $raw < 1) {
        throw SifException::validation('Invalid Alumne PrisMa offer field: year');
    }

    return (int) $raw;
}

function requiredPrismaStudentOfferMonth(mixed $value): string
{
    $month = trim((string) $value);
    if (!preg_match('/^(?:0[1-9]|1[0-2])$/D', $month)) {
        throw SifException::validation('Invalid Alumne PrisMa offer field: month');
    }

    return $month;
}
