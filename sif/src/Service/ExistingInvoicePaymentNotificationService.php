<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\NotificationOutboxRepository;

final class ExistingInvoicePaymentNotificationService
{
    public function __construct(private NotificationOutboxRepository $outbox)
    {
    }

    public function enqueue(
        \PDO $db,
        array $paymentResult,
        array $paymentInput,
        ?array $legacyProjection = null
    ): array {
        if (!$db->inTransaction()) {
            throw new \LogicException(
                'UC-002 notification enqueue requires the payment transaction'
            );
        }

        $uuidFactura = trim((string) ($paymentResult['uuid_factura'] ?? ''));
        $uuidPayment = trim((string) ($paymentResult['uuid_payment'] ?? ''));
        $numVisible = trim((string) ($paymentResult['num_visible'] ?? ''));
        $idempotencyKey = trim((string) ($paymentInput['idempotency_key'] ?? ''));

        if ($uuidFactura === '' || $uuidPayment === '' || $numVisible === '' || $idempotencyKey === '') {
            throw SifException::validation(
                'UC-002 notification requires persisted invoice/payment identity'
            );
        }

        $stmt = $db->prepare(
            'SELECT BILLING_EMAIL, BILLING_NOM_RAO, ESTAT_COBRAMENT, TOTAL
             FROM factura
             WHERE UUID_FACTURA = ?'
        );
        $stmt->execute([$uuidFactura]);
        $invoice = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($invoice)) {
            throw SifException::notFound('UC-002 notification invoice not found');
        }

        $email = strtolower(trim((string) ($invoice['BILLING_EMAIL'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'status' => 'SKIPPED',
                'reason' => 'NO_VALID_BILLING_EMAIL',
            ];
        }

        $amount = $this->money($paymentInput['amount'] ?? null);
        $movementDate = trim((string) ($paymentInput['movement_date'] ?? ''));
        $bank = trim((string) ($paymentInput['bank'] ?? ''));
        if ($movementDate === '' || $bank === '') {
            throw SifException::validation(
                'UC-002 notification requires payment date and bank'
            );
        }

        return $this->outbox->enqueue($db, [
            'idempotency_key' => 'NOTIFY|UC002|PAYMENT:' . $uuidPayment,
            'template_code' => 'EXISTING_INVOICE_PAYMENT_CONFIRMED',
            'template_version' => 'v1',
            'recipient_type' => 'BILLING',
            'recipient_hash' => hash('sha256', $email),
            'uuid_factura' => $uuidFactura,
            'uuid_payment' => $uuidPayment,
            'correlation_id' => $idempotencyKey,
            'payload' => [
                'source_type' => 'UC002_EXISTING_INVOICE',
                'num_visible' => $numVisible,
                'billing_name' => trim((string) ($invoice['BILLING_NOM_RAO'] ?? '')),
                'payment_amount' => $amount,
                'movement_date' => $movementDate,
                'bank' => $bank,
                'reference' => trim((string) ($paymentInput['reference'] ?? '')),
                'payment_status' => (string) ($invoice['ESTAT_COBRAMENT'] ?? ''),
                'invoice_total' => $this->money($invoice['TOTAL'] ?? null),
                'projected_total' => $legacyProjection !== null
                    ? (string) ($legacyProjection['projected_total'] ?? '')
                    : '',
                'remaining_after' => $legacyProjection !== null
                    ? $this->remaining(
                        $invoice['TOTAL'] ?? null,
                        $legacyProjection['projected_total'] ?? null
                    )
                    : '',
                'recipient_resolution' => 'SIF_INVOICE_BILLING_EMAIL_HASH',
            ],
        ]);
    }

    private function money(mixed $value): string
    {
        $raw = trim(str_replace(',', '.', (string) $value));
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $raw)) {
            throw SifException::validation('Invalid UC-002 notification amount');
        }

        return number_format((float) $raw, 2, '.', '');
    }

    private function remaining(mixed $total, mixed $paid): string
    {
        $totalCents = $this->cents($total);
        $paidCents = $this->cents($paid);

        return $this->amount(max(0, $totalCents - $paidCents));
    }

    private function cents(mixed $value): int
    {
        $money = $this->money($value);
        [$euros, $decimals] = explode('.', $money);

        return (int) $euros * 100 + (int) $decimals;
    }

    private function amount(int $cents): string
    {
        return intdiv($cents, 100)
            . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
