<?php

require dirname(__DIR__, 3) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\HashCalculator;
use Prisma\Sif\Domain\PaymentStatusCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Http\JsonResponse;
use Prisma\Sif\Repository\FiscalSequenceRepository;
use Prisma\Sif\Repository\InternalApiRequestRepository;
use Prisma\Sif\Repository\InvoiceRepository;
use Prisma\Sif\Repository\LegacyUsocSnapshotRepository;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\PaymentRepository;
use Prisma\Sif\Repository\UsocFinancingCaseRepository;
use Prisma\Sif\Repository\UsocStudentInvoiceLinkRepository;
use Prisma\Sif\Service\InternalApiAuthenticator;
use Prisma\Sif\Service\InvoicePayloadValidator;
use Prisma\Sif\Service\InvoiceService;
use Prisma\Sif\Service\LegacyUsocInvoicePayloadBuilder;
use Prisma\Sif\Service\ManualPaymentPayloadBuilder;
use Prisma\Sif\Service\ManualPaymentService;
use Prisma\Sif\Service\PaymentPayloadValidator;
use Prisma\Sif\Service\PaymentService;
use Prisma\Sif\Service\UsocCaseReconciler;
use Prisma\Sif\Service\UsocEntityInvoiceService;
use Prisma\Sif\Service\UsocEntityPaymentService;

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
    $db = ConnectionFactory::make($config);

    $internalApi = $config['internal_api'] ?? [];
    $actor = (new InternalApiAuthenticator(
        $db,
        new InternalApiRequestRepository(),
        (string) ($internalApi['key_id'] ?? ''),
        (string) ($internalApi['secret'] ?? ''),
        (int) ($internalApi['max_clock_skew_seconds'] ?? 300)
    ))->authenticate(
        $_SERVER,
        $rawBody,
        'POST',
        (string) ($internalApi['usoc_signed_path'] ?? '/api/usoc/manage.php')
    );

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        throw SifException::validation('Invalid JSON');
    }

    $action = strtolower(trim((string) ($payload['action'] ?? '')));
    $usocConfig = $config['usoc'] ?? [];
    $readRoles = (array) ($usocConfig['read_roles'] ?? []);
    $manageRoles = (array) ($usocConfig['manage_roles'] ?? []);

    $cases = new UsocFinancingCaseRepository(new UuidGenerator());

    if ($action === 'view') {
        assertUsocRole($actor, array_merge($readRoles, $manageRoles), 'read');
        $idInsc = positiveInt($payload['id_insc'] ?? null, 'Invalid USOC inscription ID');
        $idpag = positiveInt($payload['idpag'] ?? null, 'Invalid USOC IDPAG');
        $case = $cases->findByInscriptionAndIdpag($db, $idInsc, $idpag);
        if ($case === null) {
            throw SifException::conflict('USOC financing case not found');
        }

        JsonResponse::send([
            'ok' => true,
            'case' => $case,
            'capabilities' => [
                'read' => true,
                'manage' => actorHasUsocRole($actor, $manageRoles),
            ],
        ]);
        return;
    }

    assertUsocRole($actor, $manageRoles, 'manage');

    if ($action === 'reconcile') {
        $idInsc = positiveInt($payload['id_insc'] ?? null, 'Invalid USOC inscription ID');
        $idpag = positiveInt($payload['idpag'] ?? null, 'Invalid USOC IDPAG');

        JsonResponse::send([
            'ok' => true,
            'case' => (new UsocCaseReconciler($cases))->reconcile($db, $idInsc, $idpag),
        ]);
        return;
    }

    if ($action === 'issue_entity_invoice') {
        $input = $payload['input'] ?? null;
        if (!is_array($input)) {
            throw SifException::validation('Invalid USOC entity invoice input');
        }

        $input['created_by'] = (string) ($actor['actor_id'] ?? '');
        $input['correlation_id'] = (string) ($actor['request_id'] ?? '');

        $legacyDb = ConnectionFactory::makeLegacy($config);
        $invoiceService = buildInvoiceService($db);
        $service = new UsocEntityInvoiceService(
            new LegacyUsocSnapshotRepository(),
            new LegacyUsocInvoicePayloadBuilder(),
            $invoiceService,
            new UsocStudentInvoiceLinkRepository(),
            $cases
        );

        JsonResponse::send([
            'ok' => true,
            'invoice' => $service->issueEntityFromExplicitInput($db, $legacyDb, $input),
        ]);
        return;
    }

    if ($action === 'register_entity_payment') {
        $uuidFactura = trim((string) ($payload['uuid_entity_invoice'] ?? ''));
        $paymentInput = $payload['payment'] ?? null;
        if ($uuidFactura === '' || !is_array($paymentInput)) {
            throw SifException::validation('Invalid USOC entity payment input');
        }

        $paymentService = new PaymentService(
            new TransactionRunner($db),
            new PaymentPayloadValidator(),
            new PaymentRepository(new UuidGenerator(), new PaymentStatusCalculator())
        );
        $service = new UsocEntityPaymentService(
            $cases,
            new ManualPaymentService(
                new ManualPaymentInvoiceRepository(),
                new ManualPaymentPayloadBuilder(),
                $paymentService
            ),
            new UsocCaseReconciler($cases)
        );

        JsonResponse::send([
            'ok' => true,
            'payment' => $service->registerByEntityInvoiceUuid($db, $uuidFactura, $paymentInput),
        ]);
        return;
    }

    throw SifException::validation('Unknown USOC action');
} catch (\Throwable $exception) {
    JsonResponse::fromThrowable($exception);
}

function actorHasUsocRole(array $actor, array $allowedRoles): bool
{
    $actorRoles = array_values(array_filter(array_map(
        static fn (mixed $role): string => strtoupper(trim((string) $role)),
        (array) ($actor['roles'] ?? [])
    )));
    $allowed = array_values(array_filter(array_map(
        static fn (mixed $role): string => strtoupper(trim((string) $role)),
        $allowedRoles
    )));

    return $allowed !== [] && array_intersect($actorRoles, $allowed) !== [];
}

function assertUsocRole(array $actor, array $allowedRoles, string $scope): void
{
    $actorRoles = array_values(array_filter(array_map(
        static fn (mixed $role): string => strtoupper(trim((string) $role)),
        (array) ($actor['roles'] ?? [])
    )));
    $allowed = array_values(array_filter(array_map(
        static fn (mixed $role): string => strtoupper(trim((string) $role)),
        $allowedRoles
    )));

    if (!actorHasUsocRole($actor, $allowedRoles)) {
        throw SifException::forbidden('USOC ' . $scope . ' role is not authorized');
    }
}

function positiveInt(mixed $value, string $message): int
{
    if (!is_numeric($value) || (int) $value <= 0) {
        throw SifException::validation($message);
    }

    return (int) $value;
}

function buildInvoiceService(\PDO $db): InvoiceService
{
    return new InvoiceService(
        new TransactionRunner($db),
        new InvoicePayloadValidator(),
        new FiscalSequenceRepository(),
        new InvoiceRepository(new UuidGenerator(), new HashCalculator()),
        new PaymentPayloadValidator(),
        new PaymentRepository(new UuidGenerator(), new PaymentStatusCalculator())
    );
}
