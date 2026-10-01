<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\NotificationOutboxRepository;

final class CoursePaymentNotificationService
{
    public function __construct(private NotificationOutboxRepository $outbox)
    {
    }

    public function enqueue(
        \PDO $db,
        string $dsOrder,
        array $snapshot,
        array $invoiceResult,
        array $legacyPaymentSync
    ): array {
        $dsOrder = trim($dsOrder);
        if ($dsOrder === '') {
            throw SifException::validation('Missing course notification DS_ORDER');
        }

        $inscription = $snapshot['inscription'] ?? null;
        $course = $snapshot['course'] ?? null;
        $payment = $snapshot['payment'] ?? null;
        if (!is_array($inscription) || !is_array($course) || !is_array($payment)) {
            throw SifException::validation('Invalid course notification snapshot');
        }

        $email = strtolower(trim((string) ($inscription['CORREU'] ?? $inscription['correu'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw SifException::validation('Invalid course notification recipient');
        }

        $idpag = $this->positiveInt(
            $legacyPaymentSync['idpag'] ?? $payment['idpag'] ?? $inscription['IDPAG'] ?? null,
            'Missing course notification IDPAG'
        );
        $idInsc = $this->positiveInt(
            $legacyPaymentSync['id_insc'] ?? $inscription['ID'] ?? null,
            'Missing course notification inscription ID'
        );

        $snapshotIdpag = $payment['idpag'] ?? $inscription['IDPAG'] ?? null;
        $snapshotIdInsc = $inscription['ID'] ?? null;
        if ($snapshotIdpag !== null && (int) $snapshotIdpag !== $idpag) {
            throw SifException::conflict('Course notification IDPAG does not match payment sync');
        }
        if ($snapshotIdInsc !== null && (int) $snapshotIdInsc !== $idInsc) {
            throw SifException::conflict('Course notification inscription does not match payment sync');
        }

        $uuidFactura = trim((string) ($invoiceResult['uuid_factura'] ?? ''));
        $uuidPayment = trim((string) ($invoiceResult['uuid_payment'] ?? ''));
        $numVisible = trim((string) ($invoiceResult['num_visible'] ?? ''));
        if ($uuidFactura === '' || $uuidPayment === '' || $numVisible === '') {
            throw SifException::conflict('Course notification lacks persisted invoice/payment identity');
        }

        $paymentAmount = $this->money(
            $payment['amount'] ?? null,
            'Missing course notification payment amount'
        );
        $contractTotal = $this->money(
            $inscription['A_PAGAR'] ?? $payment['contract_total'] ?? null,
            'Missing course notification contract total'
        );
        $projectedPayment = $this->money(
            $legacyPaymentSync['projected_payment'] ?? null,
            'Missing course notification projected payment'
        );
        $confirmedAmount = $this->money(
            $legacyPaymentSync['confirmed_amount'] ?? null,
            'Missing course notification confirmed amount'
        );

        $paymentStatus = strtoupper(trim((string) ($legacyPaymentSync['status'] ?? '')));
        if (!in_array($paymentStatus, ['PARTIALLY_PAID', 'PAID'], true)) {
            throw SifException::validation('Invalid course notification payment status');
        }

        $remainingAfter = number_format(
            max(0.0, (float) $contractTotal - (float) $projectedPayment),
            2,
            '.',
            ''
        );
        if ($paymentStatus === 'PAID' && (float) $remainingAfter > 0.009) {
            throw SifException::conflict('Course notification PAID status conflicts with projected balance');
        }
        if ($paymentStatus === 'PARTIALLY_PAID' && (float) $remainingAfter <= 0.009) {
            throw SifException::conflict('Course notification partial status conflicts with projected balance');
        }

        $courseCode = trim((string) ($inscription['CURS'] ?? $inscription['curs'] ?? ''));
        $courseTitle = trim((string) ($course['NOM_CURS'] ?? $course['nom_curs'] ?? ''));
        if ($courseCode === '' || $courseTitle === '') {
            throw SifException::validation('Missing course notification course identity');
        }

        return $this->outbox->enqueue($db, [
            'idempotency_key' => 'NOTIFY|COURSE_PAYMENT_CONFIRMED|ORDER:' . $dsOrder,
            'template_code' => 'COURSE_PAYMENT_CONFIRMED',
            'template_version' => 'v1',
            'recipient_type' => 'ALUMNE',
            'recipient_hash' => hash('sha256', $email),
            'uuid_factura' => $uuidFactura,
            'uuid_payment' => $uuidPayment,
            'correlation_id' => 'REDSYS|' . $dsOrder,
            'payload' => [
                'source_type' => 'CURS',
                'ds_order' => $dsOrder,
                'idpag' => $idpag,
                'inscription_id' => $idInsc,
                'course_code' => $courseCode,
                'course_title' => $courseTitle,
                'payment_amount' => $paymentAmount,
                'confirmed_amount' => $confirmedAmount,
                'projected_payment' => $projectedPayment,
                'contract_total' => $contractTotal,
                'remaining_after' => $remainingAfter,
                'payment_status' => $paymentStatus,
                'num_visible' => $numVisible,
                'recipient_resolution' => 'LEGACY_INSCRIPTION_EMAIL_HASH',
            ],
        ]);
    }

    private function positiveInt(mixed $value, string $message): int
    {
        if (!is_numeric($value) || (int) $value <= 0) {
            throw SifException::validation($message);
        }

        return (int) $value;
    }

    private function money(mixed $value, string $message): string
    {
        if (!is_numeric($value) || (float) $value < 0.0) {
            throw SifException::validation($message);
        }

        return number_format((float) $value, 2, '.', '');
    }
}
