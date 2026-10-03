<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\OperationalEventRepository;
use Prisma\Sif\Repository\UsocFinancingCaseRepository;
use Prisma\Sif\Repository\UsocLifecycleExecutionRepository;

final class UsocCourseChangeExecutionService
{
    public function __construct(
        private UsocLifecycleExecutionRepository $executions,
        private ManualPaymentInvoiceRepository $invoices,
        private ManualRectificationService $rectifications,
        private UsocCourseChangeInvoicePayloadBuilder $payloads,
        private InvoiceService $invoiceService,
        private PaymentService $paymentService,
        private EnrollmentFundMovementRepository $fundMovements,
        private UsocFinancingCaseRepository $cases,
        private OperationalEventRepository $events,
        private UsocCourseChangeIdempotency $keys
    ) {
    }

    public function execute(
        \PDO $db,
        int $idInsc,
        int $idpag,
        string $requestId,
        string $actorId,
        array $roles,
        int $targetIdInsc,
        array $input
    ): array {
        $requestId = trim($requestId);
        $actorId = trim($actorId);
        if ($idInsc <= 0 || $idpag <= 0 || $targetIdInsc <= 0 || $targetIdInsc === $idInsc) {
            throw SifException::validation('Invalid USOC course change execution identity');
        }
        if ($actorId === '') {
            throw SifException::validation('Missing USOC course change actor');
        }

        $execution = $this->executions->findByRequestId($db, $requestId);
        if ($execution === null) {
            throw SifException::conflict('USOC course change must be prepared before legacy mutation');
        }
        $this->assertExecutionIdentity($execution, $idInsc, $idpag, $actorId);

        if ((string) $execution['STATE'] === 'COMPLETED') {
            $result = $this->decode((string) ($execution['RESULT_JSON'] ?? ''), 'completed course change result');
            if ((int) ($result['target_id_insc'] ?? 0) !== $targetIdInsc) {
                throw SifException::conflict('USOC course change request is already bound to another target enrollment');
            }
            $result['idempotency_reused'] = true;
            return $result;
        }
        if ((string) $execution['STATE'] === 'REVIEW_REQUIRED') {
            throw SifException::conflict(
                'USOC course change requires review: ' . (string) ($execution['REVIEW_REASON'] ?? 'UNKNOWN')
            );
        }
        if ((string) $execution['STATE'] !== 'REQUESTED') {
            throw SifException::conflict('USOC course change is not executable from its current state');
        }

        $request = $this->decode((string) $execution['REQUEST_JSON'], 'course change request');
        $plan = $this->decode((string) $execution['PLAN_JSON'], 'course change plan');
        $targetMeta = $request['target'] ?? null;
        $target = $plan['target'] ?? null;
        $fundPlan = $plan['fund_plan']['payers'] ?? null;
        $lifecycle = $plan['lifecycle_plan']['actions'] ?? null;
        if (!is_array($targetMeta) || !is_array($target) || !is_array($fundPlan) || !is_array($lifecycle)) {
            throw SifException::conflict('Stored USOC course change plan is incomplete');
        }

        $destination = $this->decode(
            (string) ($execution['RESULT_JSON'] ?? ''),
            'bound USOC course change destination'
        );
        $targetIdpag = (int) ($destination['destination_idpag'] ?? 0);
        if (
            (string) ($destination['phase'] ?? '') !== 'DESTINATION_RESERVED'
            || (int) ($destination['source_id_insc'] ?? 0) !== $idInsc
            || (int) ($destination['source_idpag'] ?? 0) !== $idpag
            || (int) ($destination['destination_id_insc'] ?? 0) !== $targetIdInsc
            || $targetIdpag <= 0
            || $targetIdpag === $idpag
        ) {
            throw SifException::conflict(
                'USOC course change destination must be durably bound before execution'
            );
        }

        $effectiveAt = $this->dateTime($input['effective_at'] ?? null);
        $sourceStudent = $this->payerAction($lifecycle, 'student');
        $sourceEntity = $this->payerAction($lifecycle, 'entity');
        $studentSourceInvoice = $this->requiredInvoice($db, $sourceStudent, 'student');

        $entitySourceInvoice = null;
        $entitySourceUuid = trim((string) ($sourceEntity['invoice_uuid'] ?? ''));
        if ($entitySourceUuid !== '') {
            $entitySourceInvoice = $this->invoices->findByUuid($db, $entitySourceUuid);
            if ($entitySourceInvoice === null) {
                throw SifException::conflict('USOC source entity invoice no longer exists');
            }
        }

        $entityBilling = $entitySourceInvoice !== null
            ? $this->payloads->billingFromInvoice($entitySourceInvoice)
            : $this->payloads->normalizeBilling(
                is_array($input['entity_billing'] ?? null) ? $input['entity_billing'] : []
            );

        $this->assertSourceFundsStillMatchPlan(
            $db,
            (string) $sourceStudent['invoice_uuid'],
            $fundPlan['student'] ?? []
        );
        $this->assertSourceFundsStillMatchPlan(
            $db,
            $entitySourceUuid,
            $fundPlan['entity'] ?? []
        );

        $studentRectification = $this->rectifySource(
            $db,
            $requestId,
            'student',
            $studentSourceInvoice,
            $actorId
        );
        $entityRectification = $entitySourceInvoice === null
            ? null
            : $this->rectifySource($db, $requestId, 'entity', $entitySourceInvoice, $actorId);

        $studentInvoice = $this->invoiceService->issueInvoice(
            $this->payloads->student(
                $requestId,
                $targetIdInsc,
                $targetIdpag,
                $targetMeta,
                $target,
                $studentSourceInvoice,
                $actorId
            )
        );
        $entityInvoice = $this->invoiceService->issueInvoice(
            $this->payloads->entity(
                $requestId,
                $targetIdInsc,
                $targetIdpag,
                $targetMeta,
                $target,
                $entityBilling,
                $actorId
            )
        );

        $studentAmount = $this->money($target['target_student_total'] ?? null);
        $entityAmount = $this->money($target['target_entity_total'] ?? null);
        $this->cases->recordStudentInvoice(
            $db,
            $targetIdInsc,
            $targetIdpag,
            (string) $studentInvoice['uuid_factura'],
            $studentAmount,
            $entityAmount,
            $requestId
        );
        $this->cases->recordEntityInvoice(
            $db,
            $targetIdInsc,
            $targetIdpag,
            (string) $studentInvoice['uuid_factura'],
            (string) $entityInvoice['uuid_factura'],
            $studentAmount,
            $entityAmount,
            $requestId
        );

        $studentEconomic = $this->compensate(
            $db,
            $requestId,
            'student',
            $idInsc,
            $targetIdInsc,
            (string) $sourceStudent['invoice_uuid'],
            (string) $studentInvoice['uuid_factura'],
            $fundPlan['student'] ?? [],
            $effectiveAt
        );
        $entityEconomic = $this->compensate(
            $db,
            $requestId,
            'entity',
            $idInsc,
            $targetIdInsc,
            $entitySourceUuid,
            (string) $entityInvoice['uuid_factura'],
            $fundPlan['entity'] ?? [],
            $effectiveAt
        );

        $studentStatus = $this->invoicePaymentStatus($db, (string) $studentInvoice['uuid_factura']);
        $entityStatus = $this->invoicePaymentStatus($db, (string) $entityInvoice['uuid_factura']);
        $caseStatus = ($studentStatus === 'PAID' && $entityStatus === 'PAID')
            ? 'FINANCING_RECONCILED'
            : 'COURSE_CHANGE_PENDING_COLLECTION';

        $requiresFollowUp =
            (float) ($studentEconomic['excess_amount'] ?? '0.00') > 0.0
            || (float) ($entityEconomic['excess_amount'] ?? '0.00') > 0.0;

        $result = [
            'ok' => true,
            'operation' => 'course_change',
            'request_id' => $requestId,
            'source_id_insc' => $idInsc,
            'target_id_insc' => $targetIdInsc,
            'source_idpag' => $idpag,
            'target_idpag' => $targetIdpag,
            'state' => 'COMPLETED',
            'student' => [
                'source_invoice_uuid' => (string) $sourceStudent['invoice_uuid'],
                'rectification' => $studentRectification,
                'target_invoice_uuid' => (string) $studentInvoice['uuid_factura'],
                'target_num_visible' => (string) $studentInvoice['num_visible'],
                'economic' => $studentEconomic,
                'payment_status' => $studentStatus,
            ],
            'entity' => [
                'source_invoice_uuid' => $entitySourceUuid !== '' ? $entitySourceUuid : null,
                'rectification' => $entityRectification,
                'target_invoice_uuid' => (string) $entityInvoice['uuid_factura'],
                'target_num_visible' => (string) $entityInvoice['num_visible'],
                'economic' => $entityEconomic,
                'payment_status' => $entityStatus,
            ],
            'case_status' => $caseStatus,
            'requires_follow_up' => $requiresFollowUp,
            'follow_up_reason' => $requiresFollowUp
                ? 'EXCESS_REQUIRES_EXPLICIT_CREDIT_OR_REFUND'
                : null,
            'legacy_handoff_completed' => true,
            'idempotency_reused' => false,
        ];

        $db->beginTransaction();
        try {
            $this->cases->updateReconciliation(
                $db,
                $targetIdInsc,
                $targetIdpag,
                $studentStatus,
                $entityStatus,
                $caseStatus
            );
            $this->appendEvent(
                $db,
                $requestId,
                $actorId,
                $roles,
                $effectiveAt,
                'student',
                $idInsc,
                $targetIdInsc,
                $studentRectification,
                $studentEconomic,
                $studentInvoice
            );
            $this->appendEvent(
                $db,
                $requestId,
                $actorId,
                $roles,
                $effectiveAt,
                'entity',
                $idInsc,
                $targetIdInsc,
                $entityRectification,
                $entityEconomic,
                $entityInvoice
            );
            $this->executions->complete($db, $requestId, $result);
            $db->commit();
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }

        return $result;
    }

    private function rectifySource(
        \PDO $db,
        string $requestId,
        string $role,
        array $invoice,
        string $actorId
    ): array {
        $total = $this->money($invoice['TOTAL'] ?? null);
        $amount = '-' . $total;

        return $this->rectifications->issueByUuid(
            $db,
            (string) $invoice['UUID_FACTURA'],
            [
                'amount' => $amount,
                'reason' => 'CANVI_CURS',
                'mode' => 'SUBSTITUCIO',
                'reference' => $this->keys->key($requestId, $role, 'RECTIFY_SOURCE'),
                'concept' => 'Rectificació per canvi de curs USOC',
                'detail' => 'Substitució per canvi de curs USOC',
                'details' => 'UC-013 · canvi de curs · ' . strtoupper($role),
                'created_by' => $actorId,
            ]
        );
    }

    private function compensate(
        \PDO $db,
        string $requestId,
        string $role,
        int $sourceIdInsc,
        int $targetIdInsc,
        string $sourceInvoiceUuid,
        string $targetInvoiceUuid,
        array $plan,
        string $effectiveAt
    ): array {
        $amount = $this->money($plan['compensate_amount'] ?? '0.00');
        $due = $this->money($plan['amount_due'] ?? '0.00');
        $excess = $this->money($plan['excess_amount'] ?? '0.00');

        if ((float) $amount <= 0.0) {
            return [
                'compensate_amount' => '0.00',
                'amount_due' => $due,
                'excess_amount' => $excess,
                'uuid_payment' => null,
                'fund_movements' => [],
                'excess_resolution' => (float) $excess > 0.0 ? 'PENDING_EXPLICIT_RESOLUTION' : 'NONE',
            ];
        }
        if ($sourceInvoiceUuid === '') {
            throw SifException::conflict('Cannot compensate USOC funds without a source invoice');
        }

        $movements = $this->allocateSourceFunds(
            $db,
            $requestId,
            $role,
            $sourceIdInsc,
            $targetIdInsc,
            $sourceInvoiceUuid,
            $amount
        );

        $payment = $this->paymentService->registerPayment([
            'idempotency_key' => $this->keys->key($requestId, $role, 'COMPENSATE'),
            'movement_type' => 'COMPENSATION',
            'method' => 'COMPENSACIO',
            'source_channel' => 'INTRANET',
            'amount' => $amount,
            'movement_date' => $effectiveAt,
            'provider_ref' => 'USOC_CC_' . substr(hash('sha256', $requestId . '|' . $role), 0, 24),
            'notes' => 'UC-013 course change compensation from ' . $sourceInvoiceUuid,
            'allocations' => [[
                'uuid_factura' => $targetInvoiceUuid,
                'amount' => $amount,
                'allocation_type' => 'COURSE_CHANGE_COMPENSATION',
            ]],
        ]);

        return [
            'compensate_amount' => $amount,
            'amount_due' => $due,
            'excess_amount' => $excess,
            'uuid_payment' => (string) $payment['uuid_payment'],
            'fund_movements' => $movements,
            'excess_resolution' => (float) $excess > 0.0 ? 'PENDING_EXPLICIT_RESOLUTION' : 'NONE',
        ];
    }

    private function allocateSourceFunds(
        \PDO $db,
        string $requestId,
        string $role,
        int $sourceIdInsc,
        int $targetIdInsc,
        string $sourceInvoiceUuid,
        string $amount
    ): array {
        $stmt = $db->prepare(
            "SELECT pt.UUID_PAYMENT, pt.TIPUS_MOVIMENT, pt.DATA_MOVIMENT, pa.IMPORT_ASSIGNAT
             FROM payment_allocation pa
             INNER JOIN payment_transaction pt ON pt.UUID_PAYMENT = pa.UUID_PAYMENT
             WHERE pa.UUID_FACTURA = ?
               AND pt.ESTAT = 'CONFIRMED'
               AND pt.TIPUS_MOVIMENT IN ('CHARGE', 'COMPENSATION')
             ORDER BY pt.DATA_MOVIMENT ASC, pt.UUID_PAYMENT ASC"
        );
        $stmt->execute([$sourceInvoiceUuid]);
        $charges = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $refundStmt = $db->prepare(
            "SELECT COALESCE(SUM(pa.IMPORT_ASSIGNAT), 0)
             FROM payment_allocation pa
             INNER JOIN payment_transaction pt ON pt.UUID_PAYMENT = pa.UUID_PAYMENT
             WHERE pa.UUID_FACTURA = ?
               AND pt.ESTAT = 'CONFIRMED'
               AND pt.TIPUS_MOVIMENT = 'REFUND'"
        );
        $refundStmt->execute([$sourceInvoiceUuid]);
        $refundRemaining = $this->cents((string) $refundStmt->fetchColumn());
        $needed = $this->cents($amount);
        $result = [];
        $order = 1;

        foreach ($charges as $charge) {
            $available = $this->cents((string) $charge['IMPORT_ASSIGNAT']);
            if ($refundRemaining > 0) {
                $deduct = min($refundRemaining, $available);
                $available -= $deduct;
                $refundRemaining -= $deduct;
            }
            if ($available <= 0 || $needed <= 0) {
                continue;
            }

            $take = min($available, $needed);
            $uuidPayment = (string) $charge['UUID_PAYMENT'];
            $key = $this->keys->key($requestId, $role, 'COMPENSATE')
                . '|SRC:' . substr(hash('sha256', $uuidPayment), 0, 16)
                . '|N:' . $order;

            $row = $this->fundMovements->insertOrReuseCompensationAllocation(
                $db,
                [
                    'idempotency_key' => $key,
                    'order' => $order,
                    'uuid_payment' => $uuidPayment,
                    'id_insc' => $targetIdInsc,
                    'amount' => $this->formatCents($take),
                    'correlation_id' => $requestId,
                    'notes' => 'UC-013 source ID_INSC ' . $sourceIdInsc
                        . ' invoice ' . $sourceInvoiceUuid,
                ]
            );
            $result[] = $row;
            $needed -= $take;
            $order++;
        }

        if ($needed !== 0) {
            throw SifException::conflict(
                'Stored USOC net-paid amount cannot be traced to confirmed source funds'
            );
        }

        return $result;
    }

    private function assertSourceFundsStillMatchPlan(
        \PDO $db,
        string $invoiceUuid,
        array $plan
    ): void {
        $expected = $this->money($plan['source_net_paid'] ?? '0.00');
        if ($invoiceUuid === '') {
            if ($expected !== '0.00') {
                throw SifException::conflict('USOC plan contains funds without a source invoice');
            }
            return;
        }

        $stmt = $db->prepare(
            "SELECT
                COALESCE(SUM(CASE
                    WHEN pt.ESTAT = 'CONFIRMED'
                     AND pt.TIPUS_MOVIMENT IN ('CHARGE', 'COMPENSATION')
                    THEN pa.IMPORT_ASSIGNAT ELSE 0 END), 0) AS CHARGED,
                COALESCE(SUM(CASE
                    WHEN pt.ESTAT = 'CONFIRMED'
                     AND pt.TIPUS_MOVIMENT = 'REFUND'
                    THEN pa.IMPORT_ASSIGNAT ELSE 0 END), 0) AS REFUNDED
             FROM payment_allocation pa
             INNER JOIN payment_transaction pt ON pt.UUID_PAYMENT = pa.UUID_PAYMENT
             WHERE pa.UUID_FACTURA = ?"
        );
        $stmt->execute([$invoiceUuid]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        $current = number_format(
            max(0.0, (float) ($row['CHARGED'] ?? 0) - (float) ($row['REFUNDED'] ?? 0)),
            2,
            '.',
            ''
        );

        if ($current !== $expected) {
            throw SifException::conflict(
                'USOC source funds changed after course change preparation'
            );
        }
    }

    private function appendEvent(
        \PDO $db,
        string $requestId,
        string $actorId,
        array $roles,
        string $effectiveAt,
        string $role,
        int $sourceIdInsc,
        int $targetIdInsc,
        ?array $rectification,
        array $economic,
        array $targetInvoice
    ): void {
        $roleUpper = strtoupper($role);
        $this->events->append($db, [
            'operation_type' => 'USOC_COURSE_CHANGE_' . $roleUpper,
            'source_type' => 'INSCRIPCIO',
            'source_id' => $sourceIdInsc,
            'uuid_factura' => (string) ($targetInvoice['uuid_factura'] ?? ''),
            'uuid_payment' => $economic['uuid_payment'] ?? null,
            'fiscal_impact' => 'RECTIFY_AND_REISSUE',
            'economic_impact' => (float) ($economic['compensate_amount'] ?? '0.00') > 0
                ? 'COMPENSATION'
                : 'NONE',
            'status' => (float) ($economic['excess_amount'] ?? '0.00') > 0
                ? 'COMPLETED_WITH_PENDING'
                : 'COMPLETED',
            'reason_code' => 'COURSE_CHANGE',
            'before_snapshot' => [
                'source_id_insc' => $sourceIdInsc,
                'payer_role' => $role,
                'rectification' => $rectification,
            ],
            'after_snapshot' => [
                'target_id_insc' => $targetIdInsc,
                'target_invoice' => $targetInvoice,
                'economic' => $economic,
            ],
            'actor_type' => 'USER',
            'actor_id' => $actorId,
            'actor_role' => $this->primaryRole($roles),
            'source_channel' => 'INTRANET',
            'correlation_id' => $requestId,
            'occurred_at' => $effectiveAt,
        ]);
    }

    private function requiredInvoice(\PDO $db, array $action, string $role): array
    {
        $uuid = trim((string) ($action['invoice_uuid'] ?? ''));
        if ($uuid === '') {
            throw SifException::conflict('Missing USOC source ' . $role . ' invoice');
        }
        $invoice = $this->invoices->findByUuid($db, $uuid);
        if ($invoice === null) {
            throw SifException::conflict('USOC source ' . $role . ' invoice not found');
        }
        return $invoice;
    }

    private function payerAction(array $actions, string $role): array
    {
        foreach ($actions as $action) {
            if (is_array($action) && strtolower((string) ($action['payer_role'] ?? '')) === $role) {
                return $action;
            }
        }
        throw SifException::conflict('Missing USOC lifecycle payer action: ' . $role);
    }

    private function assertExecutionIdentity(array $execution, int $idInsc, int $idpag, string $actorId): void
    {
        if (
            (int) $execution['ID_INSC'] !== $idInsc
            || (int) $execution['IDPAG'] !== $idpag
            || (string) $execution['OPERATION'] !== 'COURSE_CHANGE'
            || (string) $execution['ACTOR_ID'] !== $actorId
        ) {
            throw SifException::conflict('USOC prepared course change identity does not match execution');
        }
    }

    private function invoicePaymentStatus(\PDO $db, string $uuid): string
    {
        $stmt = $db->prepare('SELECT ESTAT_COBRAMENT FROM factura WHERE UUID_FACTURA = ?');
        $stmt->execute([$uuid]);
        $status = $stmt->fetchColumn();
        if ($status === false) {
            throw SifException::conflict('USOC destination invoice disappeared during execution');
        }
        return strtoupper(trim((string) $status));
    }

    private function decode(string $json, string $label): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw SifException::conflict('Invalid ' . $label);
        }
        return $decoded;
    }

    private function dateTime(mixed $value): string
    {
        $value = trim((string) $value);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        if (
            $value === ''
            || $date === false
            || (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))
            || $date->format('Y-m-d H:i:s') !== $value
        ) {
            throw SifException::validation('Invalid USOC course change effective_at');
        }
        return $value;
    }

    private function money(mixed $value): string
    {
        if (!is_numeric($value)) {
            throw SifException::conflict('Invalid stored USOC monetary value');
        }
        return number_format((float) $value, 2, '.', '');
    }

    private function cents(string $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    private function formatCents(int $value): string
    {
        return number_format($value / 100, 2, '.', '');
    }

    private function primaryRole(array $roles): ?string
    {
        foreach ($roles as $role) {
            $role = strtoupper(trim((string) $role));
            if ($role !== '') {
                return $role;
            }
        }
        return null;
    }
}
