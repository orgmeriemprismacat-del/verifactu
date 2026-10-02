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
use Prisma\Sif\Repository\UsocValidationDecisionRepository;
use Prisma\Sif\Repository\EnrollmentCancellationEventRepository;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;
use Prisma\Sif\Repository\OperationalEventRepository;
use Prisma\Sif\Repository\RectificationRepository;
use Prisma\Sif\Repository\UsocLifecycleExecutionRepository;
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
use Prisma\Sif\Service\UsocLifecycleGuardService;
use Prisma\Sif\Service\UsocLifecyclePlanService;
use Prisma\Sif\Service\UsocValidationDecisionService;
use Prisma\Sif\Service\ManualRectificationPayloadBuilder;
use Prisma\Sif\Service\ManualRectificationService;
use Prisma\Sif\Service\ManualRefundPayloadBuilder;
use Prisma\Sif\Service\ManualRefundService;
use Prisma\Sif\Service\UsocCancellationExecutionService;
use Prisma\Sif\Service\UsocCourseChangePreviewService;
use Prisma\Sif\Service\UsocCourseChangeTargetResolver;
use Prisma\Sif\Service\UsocCourseChangeFundPlanService;
use Prisma\Sif\Service\UsocCourseChangeExecutionPreparationService;
use Prisma\Sif\Service\UsocCourseChangeExecutionService;
use Prisma\Sif\Service\UsocCourseChangeIdempotency;
use Prisma\Sif\Service\UsocCourseChangeInvoicePayloadBuilder;
use Prisma\Sif\Service\UsocCourseChangeExecutionPreparationService;
use Prisma\Sif\Service\UsocCourseChangeDestinationBindingService;

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

    if ($action === 'lifecycle_guard') {
        $idInsc = positiveInt($payload['id_insc'] ?? null, 'Invalid USOC inscription ID');
        $idpag = positiveInt($payload['idpag'] ?? null, 'Invalid USOC IDPAG');
        $operation = strtolower(trim((string) ($payload['operation'] ?? '')));
        if (!in_array($operation, ['course_change', 'cancellation'], true)) {
            throw SifException::validation('Invalid USOC lifecycle operation');
        }

        JsonResponse::send([
            'ok' => true,
            'guard' => (new UsocLifecycleGuardService($cases))->check(
                $db,
                $idInsc,
                $idpag,
                $operation
            ),
        ]);
        return;
    }

    if ($action === 'lifecycle_plan') {
        $idInsc = positiveInt($payload['id_insc'] ?? null, 'Invalid USOC inscription ID');
        $idpag = positiveInt($payload['idpag'] ?? null, 'Invalid USOC IDPAG');
        $operation = strtolower(trim((string) ($payload['operation'] ?? '')));
        if (!in_array($operation, ['course_change', 'cancellation'], true)) {
            throw SifException::validation('Invalid USOC lifecycle operation');
        }

        $guard = new UsocLifecycleGuardService($cases);
        JsonResponse::send([
            'ok' => true,
            'plan' => (new UsocLifecyclePlanService($cases, $guard))->plan(
                $db,
                $idInsc,
                $idpag,
                $operation
            ),
        ]);
        return;
    }

    if ($action === 'course_change_preview') {
        $idInsc = positiveInt($payload['id_insc'] ?? null, 'Invalid USOC inscription ID');
        $idpag = positiveInt($payload['idpag'] ?? null, 'Invalid USOC IDPAG');
        $target = $payload['target'] ?? null;
        if (!is_array($target)) {
            throw SifException::validation('Invalid USOC course change target input');
        }

        $guard = new UsocLifecycleGuardService($cases);
        $service = new UsocCourseChangePreviewService(
            new UsocLifecyclePlanService($cases, $guard),
            new UsocCourseChangeTargetResolver(),
            new UsocCourseChangeFundPlanService()
        );

        JsonResponse::send([
            'ok' => true,
            'preview' => $service->preview(
                $db,
                $idInsc,
                $idpag,
                $target
            ),
        ]);
        return;
    }

    if ($action === 'prepare_course_change') {
        $idInsc = positiveInt($payload['id_insc'] ?? null, 'Invalid USOC inscription ID');
        $idpag = positiveInt($payload['idpag'] ?? null, 'Invalid USOC IDPAG');
        $requestId = requiredRequestId($payload['request_id'] ?? null);
        $target = $payload['target'] ?? null;
        if (!is_array($target)) {
            throw SifException::validation('Invalid USOC course change target input');
        }

        $guard = new UsocLifecycleGuardService($cases);
        $preview = new UsocCourseChangePreviewService(
            new UsocLifecyclePlanService($cases, $guard),
            new UsocCourseChangeTargetResolver(),
            new UsocCourseChangeFundPlanService()
        );
        $service = new UsocCourseChangeExecutionPreparationService(
            $preview,
            new UsocLifecycleExecutionRepository(new UuidGenerator())
        );

        JsonResponse::send([
            'ok' => true,
            'preparation' => $service->prepare(
                $db,
                $idInsc,
                $idpag,
                $requestId,
                $actorId,
                $roles,
                $target
            ),
        ]);
        return;
    }

    if ($action === 'bind_course_change_destination') {
        $requestId = requiredString($payload['request_id'] ?? null, 'Missing USOC course change request id');
        $sourceIdInsc = positiveInt($payload['source_id_insc'] ?? null, 'Invalid USOC source inscription ID');
        $sourceIdpag = positiveInt($payload['source_idpag'] ?? null, 'Invalid USOC source IDPAG');
        $destinationIdInsc = positiveInt(
            $payload['destination_id_insc'] ?? null,
            'Invalid USOC destination inscription ID'
        );
        $destinationIdpag = positiveInt(
            $payload['destination_idpag'] ?? null,
            'Invalid USOC destination IDPAG'
        );
        $reservationMarker = requiredString(
            $payload['reservation_marker'] ?? null,
            'Missing USOC destination reservation marker'
        );
        $targetStudentTotal = requiredString(
            $payload['target_student_total'] ?? null,
            'Missing USOC destination student total'
        );

        $service = new UsocCourseChangeDestinationBindingService(
            new UsocLifecycleExecutionRepository(new UuidGenerator())
        );

        JsonResponse::send([
            'ok' => true,
            'binding' => $service->bind(
                $db,
                $requestId,
                $sourceIdInsc,
                $sourceIdpag,
                $destinationIdInsc,
                $destinationIdpag,
                $reservationMarker,
                $targetStudentTotal
            ),
        ]);
        return;
    }

    if ($action === 'course_change_prepare') {
        $idInsc = positiveInt($payload['id_insc'] ?? null, 'Invalid USOC inscription ID');
        $idpag = positiveInt($payload['idpag'] ?? null, 'Invalid USOC IDPAG');
        $target = $payload['target'] ?? null;
        if (!is_array($target)) {
            throw SifException::validation('Invalid USOC course change target input');
        }

        $guard = new UsocLifecycleGuardService($cases);
        $preview = new UsocCourseChangePreviewService(
            new UsocLifecyclePlanService($cases, $guard),
            new UsocCourseChangeTargetResolver(),
            new UsocCourseChangeFundPlanService()
        );
        $service = new UsocCourseChangeExecutionPreparationService(
            $preview,
            new UsocLifecycleExecutionRepository(new UuidGenerator())
        );

        JsonResponse::send([
            'ok' => true,
            'preparation' => $service->prepare(
                $db,
                $idInsc,
                $idpag,
                requiredRequestId($payload['request_id'] ?? null),
                (string) ($actor['actor_id'] ?? ''),
                (array) ($actor['roles'] ?? []),
                $target
            ),
        ]);
        return;
    }

    if ($action === 'course_change_execution_status') {
        $requestId = requiredRequestId($payload['request_id'] ?? null);
        $execution = (new UsocLifecycleExecutionRepository(new UuidGenerator()))
            ->findByRequestId($db, $requestId);

        if ($execution === null || (string) ($execution['OPERATION'] ?? '') !== 'COURSE_CHANGE') {
            throw SifException::conflict('USOC course change execution not found');
        }
        if ((string) $execution['ACTOR_ID'] !== (string) ($actor['actor_id'] ?? '')) {
            throw SifException::forbidden('USOC course change execution belongs to another actor');
        }

        JsonResponse::send([
            'ok' => true,
            'execution' => $execution,
        ]);
        return;
    }

    if ($action === 'execute_course_change') {
        $idInsc = positiveInt($payload['id_insc'] ?? null, 'Invalid USOC inscription ID');
        $idpag = positiveInt($payload['idpag'] ?? null, 'Invalid USOC IDPAG');
        $targetIdInsc = positiveInt(
            $payload['target_id_insc'] ?? null,
            'Invalid USOC destination inscription ID'
        );
        $input = $payload['input'] ?? null;
        if (!is_array($input)) {
            throw SifException::validation('Invalid USOC course change execution input');
        }

        $configuredBilling = $usocConfig['entity_billing'] ?? null;
        if (
            !isset($input['entity_billing'])
            && is_array($configuredBilling)
            && trim((string) ($configuredBilling['name'] ?? '')) !== ''
            && trim((string) ($configuredBilling['nif'] ?? '')) !== ''
        ) {
            $input['entity_billing'] = $configuredBilling;
        }

        $keys = new UsocCourseChangeIdempotency();
        $service = new UsocCourseChangeExecutionService(
            new UsocLifecycleExecutionRepository(new UuidGenerator()),
            new ManualPaymentInvoiceRepository(),
            new ManualRectificationService(
                new ManualPaymentInvoiceRepository(),
                new RectificationRepository(),
                new ManualRectificationPayloadBuilder(),
                buildInvoiceService($db)
            ),
            new UsocCourseChangeInvoicePayloadBuilder($keys),
            buildInvoiceService($db),
            new PaymentService(
                new TransactionRunner($db),
                new PaymentPayloadValidator(),
                new PaymentRepository(new UuidGenerator(), new PaymentStatusCalculator())
            ),
            new EnrollmentFundMovementRepository(new UuidGenerator()),
            $cases,
            new OperationalEventRepository(new UuidGenerator()),
            $keys
        );

        JsonResponse::send([
            'ok' => true,
            'execution' => $service->execute(
                $db,
                $idInsc,
                $idpag,
                requiredRequestId($payload['request_id'] ?? null),
                (string) ($actor['actor_id'] ?? ''),
                (array) ($actor['roles'] ?? []),
                $targetIdInsc,
                $input
            ),
        ]);
        return;
    }

    if ($action === 'begin_validation_decision') {
        $legacyDb = ConnectionFactory::makeLegacy($config);
        $service = new UsocValidationDecisionService(
            new UsocValidationDecisionRepository(new UuidGenerator())
        );

        JsonResponse::send([
            'ok' => true,
            'decision' => $service->begin(
                $db,
                $legacyDb,
                requiredRequestId($payload['request_id'] ?? null),
                positiveInt($payload['id_insc'] ?? null, 'Invalid USOC inscription ID'),
                desiredValidDesc($payload['desired_valid_desc'] ?? null),
                (string) ($actor['actor_id'] ?? ''),
                (array) ($actor['roles'] ?? [])
            ),
        ]);
        return;
    }

    if ($action === 'complete_validation_decision') {
        $legacyDb = ConnectionFactory::makeLegacy($config);
        $service = new UsocValidationDecisionService(
            new UsocValidationDecisionRepository(new UuidGenerator())
        );

        JsonResponse::send([
            'ok' => true,
            'decision' => $service->complete(
                $db,
                $legacyDb,
                requiredRequestId($payload['request_id'] ?? null),
                (string) ($actor['actor_id'] ?? '')
            ),
        ]);
        return;
    }

    if ($action === 'cancellation_execution_status') {
        $requestId = requiredRequestId($payload['request_id'] ?? null);
        $execution = (new UsocLifecycleExecutionRepository(new UuidGenerator()))
            ->findByRequestId($db, $requestId);

        if ($execution === null) {
            throw SifException::conflict('USOC cancellation execution not found');
        }
        if ((string) $execution['ACTOR_ID'] !== (string) ($actor['actor_id'] ?? '')) {
            throw SifException::forbidden('USOC cancellation execution belongs to another actor');
        }

        JsonResponse::send([
            'ok' => true,
            'execution' => $execution,
        ]);
        return;
    }

    if ($action === 'execute_cancellation') {
        $idInsc = positiveInt($payload['id_insc'] ?? null, 'Invalid USOC inscription ID');
        $idpag = positiveInt($payload['idpag'] ?? null, 'Invalid USOC IDPAG');
        $input = $payload['input'] ?? null;
        if (!is_array($input)) {
            throw SifException::validation('Invalid USOC cancellation execution input');
        }

        $paymentService = new PaymentService(
            new TransactionRunner($db),
            new PaymentPayloadValidator(),
            new PaymentRepository(new UuidGenerator(), new PaymentStatusCalculator())
        );
        $guard = new UsocLifecycleGuardService($cases);

        $service = new UsocCancellationExecutionService(
            new UsocLifecyclePlanService($cases, $guard),
            new UsocLifecycleExecutionRepository(new UuidGenerator()),
            new ManualRectificationService(
                new ManualPaymentInvoiceRepository(),
                new RectificationRepository(),
                new ManualRectificationPayloadBuilder(),
                buildInvoiceService($db)
            ),
            new ManualRefundService(
                new ManualPaymentInvoiceRepository(),
                new ManualRefundPayloadBuilder(),
                $paymentService
            ),
            new OperationalEventRepository(new UuidGenerator()),
            new EnrollmentCancellationEventRepository(new UuidGenerator())
        );

        JsonResponse::send([
            'ok' => true,
            'execution' => $service->execute(
                $db,
                $idInsc,
                $idpag,
                requiredRequestId($payload['request_id'] ?? null),
                (string) ($actor['actor_id'] ?? ''),
                (array) ($actor['roles'] ?? []),
                $input
            ),
        ]);
        return;
    }

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

function desiredValidDesc(mixed $value): int
{
    if (!is_numeric($value) || !in_array((int) $value, [1, 2], true)) {
        throw SifException::validation('Invalid desired USOC validation decision');
    }

    return (int) $value;
}

function requiredRequestId(mixed $value): string
{
    $requestId = trim((string) $value);
    if (
        $requestId === ''
        || strlen($requestId) > 120
        || preg_match('/^[A-Za-z0-9._:-]+$/D', $requestId) !== 1
    ) {
        throw SifException::validation('Invalid USOC validation request id');
    }

    return $requestId;
}

function requiredString(mixed $value, string $message): string
{
    $text = trim((string) $value);
    if ($text === '') {
        throw SifException::validation($message);
    }

    return $text;
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
