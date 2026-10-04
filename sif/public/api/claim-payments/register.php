<?php

require dirname(__DIR__, 3) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\PaymentStatusCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Http\JsonResponse;
use Prisma\Sif\Repository\ClaimPaymentExternalReceiptRepository;
use Prisma\Sif\Repository\ClaimPaymentInvoiceLinkRepository;
use Prisma\Sif\Repository\InternalApiRequestRepository;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\PaymentActionEventRepository;
use Prisma\Sif\Repository\PaymentRepository;
use Prisma\Sif\Service\ClaimPaymentBalanceGuard;
use Prisma\Sif\Service\ClaimPaymentLegacySyncService;
use Prisma\Sif\Service\ClaimPaymentPayloadBuilder;
use Prisma\Sif\Service\ClaimPaymentReceiptResolver;
use Prisma\Sif\Service\ClaimPaymentService;
use Prisma\Sif\Service\InternalApiAuthenticator;
use Prisma\Sif\Service\PaymentActionGateway;
use Prisma\Sif\Service\PaymentPayloadValidator;
use Prisma\Sif\Service\PaymentService;

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
    $legacyDb = ConnectionFactory::makeLegacy($config);

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
        (string) ($internalApi['claim_payment_signed_path'] ?? '/api/claim-payments/register.php')
    );

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        throw SifException::validation('Invalid JSON');
    }

    $claimConfig = $config['claim_payments'] ?? [];
    $manageRoles = (array) ($claimConfig['manage_roles'] ?? []);
    $actorRole = assertClaimPaymentRole($actor, $manageRoles);

    $uuidFactura = trim((string) ($payload['uuid_factura'] ?? ''));
    $numVisible = trim((string) ($payload['num_visible'] ?? ''));
    if ($uuidFactura !== '' && $numVisible !== '') {
        throw SifException::validation('Provide at most one claim payment invoice selector');
    }

    $sourceInscriptionId = claimPaymentPositiveInt(
        $payload['source_inscription_id'] ?? null,
        'Invalid claim payment inscription ID'
    );
    $claimCaseId = claimPaymentIdentifier(
        $payload['claim_case_id'] ?? null,
        'claim case id'
    );
    $externalReceiptType = claimPaymentExternalReceiptType(
        $payload['external_receipt_type'] ?? null
    );
    $externalReceiptId = claimPaymentIdentifier(
        $payload['external_receipt_id'] ?? null,
        'external receipt id'
    );

    $paymentInput = $payload['payment'] ?? null;
    if (!is_array($paymentInput)) {
        throw SifException::validation('Invalid claim payment input');
    }

    // The new API contract separates case identity from the external economic
    // fact. Legacy ambiguous reference fields are deliberately ignored here.
    foreach ([
        'claim_reference',
        'reclamation_ref',
        'reclamacio_ref',
        'reference',
        'referencia',
        'referencia_bancaria',
        'receipt_id',
    ] as $legacyReferenceField) {
        unset($paymentInput[$legacyReferenceField]);
    }
    $paymentInput['external_receipt_type'] = $externalReceiptType;
    $paymentInput['external_receipt_id'] = $externalReceiptId;

    // Internal identity is authoritative. Never accept actor attribution from
    // browser-controlled payment fields.
    $paymentInput['created_by'] = (string) ($actor['actor_id'] ?? '');

    $paymentService = new PaymentService(
        new TransactionRunner($db),
        new PaymentPayloadValidator(),
        new PaymentRepository(new UuidGenerator(), new PaymentStatusCalculator())
    );
    $claimService = new ClaimPaymentService(
        new ManualPaymentInvoiceRepository(),
        new ClaimPaymentPayloadBuilder(),
        $paymentService
    );
    $paymentAudit = new PaymentActionEventRepository(new UuidGenerator());
    $gateway = new PaymentActionGateway(
        $db,
        new TransactionRunner($db),
        $paymentAudit
    );

    $requestId = (string) ($actor['request_id'] ?? '');
    $auditContext = [
        'request_id' => $requestId,
        'correlation_id' => $requestId,
        'action' => 'LINK_CLAIM_PAYMENT',
        'source_environment' => claimPaymentAuditEnvironment((string) ($config['env'] ?? 'local')),
        'source_channel' => 'INTRANET',
        'actor_type' => 'HUMAN',
        'actor_id' => (string) ($actor['actor_id'] ?? ''),
        'actor_role' => $actorRole,
        'reason_code' => 'CLAIM_PAYMENT_CONFIRMED',
        'changeset' => [
            'claim_case_id' => $claimCaseId,
            'source_inscription_id' => $sourceInscriptionId,
            'external_receipt_type' => $externalReceiptType,
            'external_receipt_id' => $externalReceiptId,
            'invoice_selector' => $uuidFactura !== ''
                ? ['type' => 'uuid', 'value' => $uuidFactura]
                : ($numVisible !== ''
                    ? ['type' => 'num_visible', 'value' => $numVisible]
                    : ['type' => 'inscription_origin', 'value' => (string) $sourceInscriptionId]),
        ],
        'occurred_at' => (new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid')))
            ->format('Y-m-d H:i:s.u'),
    ];

    $invoiceLinks = new ClaimPaymentInvoiceLinkRepository();
    $receiptResolver = new ClaimPaymentReceiptResolver(
        new ClaimPaymentExternalReceiptRepository()
    );
    $balanceGuard = new ClaimPaymentBalanceGuard();
    $legacySyncService = new ClaimPaymentLegacySyncService();

    $result = $gateway->run(
        $auditContext,
        function (PDO $transactionDb) use (
            $claimService,
            $invoiceLinks,
            $receiptResolver,
            $balanceGuard,
            $legacySyncService,
            $legacyDb,
            $sourceInscriptionId,
            $uuidFactura,
            $numVisible,
            $externalReceiptType,
            $externalReceiptId,
            $paymentInput
        ): array {
            if ($uuidFactura !== '') {
                $resolved = $invoiceLinks->assertUuidMatches(
                    $transactionDb,
                    $uuidFactura,
                    $sourceInscriptionId
                );
            } elseif ($numVisible !== '') {
                $resolved = $invoiceLinks->assertNumVisibleMatches(
                    $transactionDb,
                    $numVisible,
                    $sourceInscriptionId
                );
            } else {
                $resolved = $invoiceLinks->resolveUniqueOriginForInscription(
                    $transactionDb,
                    $sourceInscriptionId
                );
            }

            $targetUuid = (string) $resolved['UUID_FACTURA'];
            $relationIdpag = (int) ($resolved['IDPAG'] ?? 0);
            if ($relationIdpag <= 0) {
                throw SifException::conflict(
                    'Claim payment invoice relation has no valid IDPAG'
                );
            }

            $effectivePaymentInput = $paymentInput;
            $effectivePaymentInput['idpag'] = $relationIdpag;

            $existing = $receiptResolver->resolveExisting(
                $transactionDb,
                $externalReceiptType,
                $externalReceiptId,
                $targetUuid,
                (string) ($effectivePaymentInput['amount'] ?? ''),
                $relationIdpag
            );
            if ($existing !== null) {
                $existing['num_visible'] = (string) $resolved['NUM_VISIBLE'];
                $existing['_legacy_sync'] = [
                    'idpag' => $relationIdpag,
                    'id_insc' => $sourceInscriptionId,
                    'uuid_factura' => $targetUuid,
                    'num_visible' => (string) $resolved['NUM_VISIBLE'],
                ];
                return $existing;
            }

            $receiptResolver->assertMayCreateNew($externalReceiptType);

            $legacySyncService->assertBaselineSynchronized(
                $transactionDb,
                $legacyDb,
                $sourceInscriptionId,
                $relationIdpag,
                $targetUuid
            );

            $outstandingBefore = $balanceGuard->assertMayCharge(
                $transactionDb,
                $targetUuid,
                (string) ($effectivePaymentInput['amount'] ?? '')
            );

            $created = $claimService->registerByUuidInTransaction(
                $transactionDb,
                $targetUuid,
                $effectivePaymentInput
            );
            $created['outstanding_before'] = $outstandingBefore;
            $created['_legacy_sync'] = [
                'idpag' => $relationIdpag,
                'id_insc' => $sourceInscriptionId,
                'uuid_factura' => $targetUuid,
                'num_visible' => (string) $resolved['NUM_VISIBLE'],
            ];

            return $created;
        }
    );

    $legacySyncContext = $result['_legacy_sync'] ?? null;
    unset($result['_legacy_sync']);
    if (!is_array($legacySyncContext)) {
        throw SifException::conflict('Missing claim payment legacy sync context');
    }

    $legacyAuditBase = [
        'uuid_payment' => (string) ($result['uuid_payment'] ?? ''),
        'payment_idempotency_key' => (string) ($result['payment_idempotency_key'] ?? ''),
        'request_id' => $requestId,
        'correlation_id' => $requestId,
        'action' => 'SYNC_LEGACY',
        'source_environment' => claimPaymentAuditEnvironment((string) ($config['env'] ?? 'local')),
        'source_channel' => 'LEGACY_SYNC',
        'actor_type' => 'HUMAN',
        'actor_id' => (string) ($actor['actor_id'] ?? ''),
        'actor_role' => $actorRole,
        'reason_code' => 'CLAIM_PAYMENT_LEGACY_PROJECTION',
        'changeset' => [
            'idpag' => (int) ($legacySyncContext['idpag'] ?? 0),
            'id_insc' => (int) ($legacySyncContext['id_insc'] ?? 0),
            'uuid_factura' => (string) ($legacySyncContext['uuid_factura'] ?? ''),
            'num_visible' => (string) ($legacySyncContext['num_visible'] ?? ''),
        ],
        'occurred_at' => (new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid')))
            ->format('Y-m-d H:i:s.u'),
    ];
    $paymentAudit->append($db, $legacyAuditBase + [
        'result' => 'REQUESTED',
        'is_terminal' => false,
    ]);

    $legacyDb->beginTransaction();
    try {
        $legacySync = $legacySyncService->syncAfterSifSuccess(
            $db,
            $legacyDb,
            (int) ($legacySyncContext['id_insc'] ?? 0),
            (int) ($legacySyncContext['idpag'] ?? 0),
            (string) ($legacySyncContext['uuid_factura'] ?? ''),
            (string) ($legacySyncContext['num_visible'] ?? ''),
            (string) ($paymentInput['amount'] ?? '')
        );
        $legacyDb->commit();

        $paymentAudit->append($db, array_merge(
            $legacyAuditBase,
            [
                'result' => 'SUCCEEDED',
                'is_terminal' => true,
                'changeset' => array_merge(
                    (array) ($legacyAuditBase['changeset'] ?? []),
                    ['legacy_sync_result' => $legacySync]
                ),
            ]
        ));
    } catch (Throwable $legacyException) {
        if ($legacyDb->inTransaction()) {
            $legacyDb->rollBack();
        }

        $paymentAudit->append($db, $legacyAuditBase + [
            'result' => 'FAILED',
            'is_terminal' => true,
            'error_code' => claimPaymentErrorCode($legacyException),
        ]);

        $result['claim_case_id'] = $claimCaseId;
        $result['source_inscription_id'] = $sourceInscriptionId;
        $result['external_receipt_type'] = $externalReceiptType;
        $result['external_receipt_id'] = $externalReceiptId;

        JsonResponse::send([
            'ok' => false,
            'error' => 'El cobrament ha quedat registrat al SIF, però la projecció legacy requereix reconciliació.',
            'payment_persisted' => true,
            'requires_reconciliation' => true,
            'payment' => $result,
        ], 409);
        return;
    }

    $result['claim_case_id'] = $claimCaseId;
    $result['source_inscription_id'] = $sourceInscriptionId;
    $result['external_receipt_type'] = $externalReceiptType;
    $result['external_receipt_id'] = $externalReceiptId;
    $result['legacy_payment_sync'] = $legacySync;

    JsonResponse::send([
        'ok' => true,
        'payment' => $result,
    ]);
} catch (Throwable $exception) {
    JsonResponse::fromThrowable($exception);
}

function assertClaimPaymentRole(array $actor, array $allowedRoles): string
{
    $actorRoles = array_values(array_filter(array_map(
        static fn (mixed $role): string => strtoupper(trim((string) $role)),
        (array) ($actor['roles'] ?? [])
    )));
    $allowed = array_values(array_filter(array_map(
        static fn (mixed $role): string => strtoupper(trim((string) $role)),
        $allowedRoles
    )));

    if ($allowed === []) {
        throw SifException::forbidden('Claim payment manage roles are not configured');
    }

    foreach ($actorRoles as $role) {
        if (in_array($role, $allowed, true)) {
            return $role;
        }
    }

    throw SifException::forbidden('Claim payment role is not authorized');
}

function claimPaymentAuditEnvironment(string $environment): string
{
    return match (strtolower(trim($environment))) {
        'production', 'prod' => 'PRODUCTION',
        'preproduction', 'pre', 'staging' => 'PREPRODUCTION',
        'test', 'testing' => 'TEST',
        'migration' => 'MIGRATION',
        default => 'DEVELOPMENT',
    };
}


function claimPaymentIdentifier(mixed $value, string $label): string
{
    $identifier = trim((string) $value);
    if (
        $identifier === ''
        || strlen($identifier) > 120
        || preg_match('/^[A-Za-z0-9._:\/-]+$/D', $identifier) !== 1
    ) {
        throw SifException::validation('Invalid ' . $label);
    }

    return $identifier;
}


function claimPaymentPositiveInt(mixed $value, string $message): int
{
    if (filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
        throw SifException::validation($message);
    }

    return (int) $value;
}


function claimPaymentExternalReceiptType(mixed $value): string
{
    $type = strtoupper(trim((string) $value));
    if (!in_array($type, ['BANK_REFERENCE', 'DS_ORDER', 'PROVIDER_REF'], true)) {
        throw SifException::validation('Invalid external receipt type');
    }

    return $type;
}


function claimPaymentErrorCode(Throwable $exception): string
{
    $short = (new ReflectionClass($exception))->getShortName();
    $normalized = strtoupper((string) preg_replace('/[^A-Z0-9_]+/i', '_', $short));

    return $normalized !== '' ? substr($normalized, 0, 80) : 'LEGACY_SYNC_ERROR';
}
