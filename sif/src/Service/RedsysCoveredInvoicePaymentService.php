<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\InvoiceBeforePaymentCoverageRepository;

final class RedsysCoveredInvoicePaymentService
{
    public function __construct(
        private InvoiceBeforePaymentCoverageRepository $coverage,
        private PaymentService $payments
    ) {
    }

    public function registerIfCovered(
        \PDO $db,
        string $dsOrder,
        array $snapshot,
        array $invoicePayload
    ): ?array {
        $relations = $invoicePayload['relations'] ?? null;
        if (!is_array($relations) || $relations === []) {
            throw SifException::validation('Redsys covered invoice requires origin relations');
        }

        $claim = $this->claim($db, $relations, $snapshot, false);
        if ($claim === null) {
            return null;
        }

        $uuidFactura = (string) $claim['UUID_FACTURA'];
        $paymentPayload = $this->paymentPayload(
            $invoicePayload,
            $uuidFactura,
            $dsOrder,
            $snapshot
        );

        $payment = $this->payments->registerPaymentWithPrecondition(
            $paymentPayload,
            function (\PDO $txDb, array $validated) use (
                $relations,
                $snapshot,
                $uuidFactura
            ): void {
                $locked = $this->claim($txDb, $relations, $snapshot, true);
                if ($locked === null || (string) $locked['UUID_FACTURA'] !== $uuidFactura) {
                    throw SifException::conflict(
                        'Invoice-before-payment coverage changed during Redsys payment'
                    );
                }
                $this->assertReceivable($txDb, $uuidFactura, $snapshot, $validated['amount']);
            }
        );

        $invoice = $this->invoice($db, $uuidFactura, false);

        return [
            'ok' => true,
            'idempotency_reused' => (bool) ($payment['idempotency_reused'] ?? false),
            'invoice_reused' => true,
            'existing_invoice_payment' => true,
            'uuid_factura' => $uuidFactura,
            'num_visible' => (string) $invoice['NUM_VISIBLE'],
            'uuid_payment' => (string) $payment['uuid_payment'],
            'payment_status' => (string) $invoice['ESTAT_COBRAMENT'],
        ];
    }

    private function claim(
        \PDO $db,
        array $relations,
        array $snapshot,
        bool $forUpdate
    ): ?array {
        $claims = $this->coverage->findClaims($db, $relations, $forUpdate);
        if ($claims === []) {
            return null;
        }

        $idInsc = $this->inscriptionId($snapshot);
        if (count($claims) !== 1 || (int) $claims[0]['SOURCE_ID'] !== $idInsc) {
            throw SifException::conflict(
                'Redsys course must resolve exactly one invoice-before-payment claim'
            );
        }

        if (trim((string) ($claims[0]['UUID_FACTURA'] ?? '')) === '') {
            throw SifException::conflict('Invoice-before-payment claim has no invoice UUID');
        }

        return $claims[0];
    }

    private function paymentPayload(
        array $invoicePayload,
        string $uuidFactura,
        string $dsOrder,
        array $snapshot
    ): array {
        $payment = $invoicePayload['payment'] ?? null;
        if (!is_array($payment)) {
            throw SifException::validation('Validated Redsys payload has no payment block');
        }

        $order = trim((string) ($payment['ds_order'] ?? ''));
        if ($order === '' || $order !== trim($dsOrder)) {
            throw SifException::conflict('Redsys order mismatch for covered invoice');
        }

        $amount = $this->positiveMoney(
            $payment['amount'] ?? null,
            'Invalid Redsys covered-invoice payment amount'
        );
        $snapshotAmount = $this->snapshotPaymentAmount($snapshot);
        if ($amount !== $snapshotAmount) {
            throw SifException::conflict(
                'Validated Redsys amount does not match the frozen intent snapshot'
            );
        }

        $movementType = strtoupper(trim((string) ($payment['movement_type'] ?? '')));
        $method = strtoupper(trim((string) ($payment['method'] ?? '')));
        $sourceChannel = strtoupper(trim((string) ($payment['source_channel'] ?? '')));
        if ($movementType !== 'CHARGE' || $method !== 'REDSYS' || $sourceChannel !== 'REDSYS') {
            throw SifException::conflict(
                'Covered Redsys payment requires CHARGE/REDSYS/REDSYS semantics'
            );
        }

        if (!array_key_exists('movement_date', $payment)
            || trim((string) $payment['movement_date']) === ''
        ) {
            throw SifException::validation(
                'Covered Redsys payment requires deterministic movement_date'
            );
        }
        $movementDate = trim((string) $payment['movement_date']);

        $canonicalKey = 'PAYMENT|REDSYS|ORDER:' . $dsOrder;
        if (trim((string) ($payment['idempotency_key'] ?? '')) !== $canonicalKey) {
            throw SifException::conflict(
                'Covered Redsys payment requires the canonical DS_ORDER idempotency key'
            );
        }
        if (trim((string) ($payment['provider_ref'] ?? '')) !== $order) {
            throw SifException::conflict(
                'Covered Redsys payment provider_ref must match DS_ORDER'
            );
        }

        $idpag = $this->positiveInt(
            $payment['idpag'] ?? null,
            'Invalid Redsys covered-invoice IDPAG'
        );
        if ($idpag !== $this->snapshotIdpag($snapshot)) {
            throw SifException::conflict(
                'Covered Redsys payment IDPAG does not match the frozen intent snapshot'
            );
        }

        return [
            'idempotency_key' => $canonicalKey,
            'movement_type' => $movementType,
            'method' => $method,
            'source_channel' => $sourceChannel,
            'amount' => $amount,
            'movement_date' => $movementDate,
            'provider_ref' => $order,
            'ds_order' => $order,
            'idpag' => $idpag,
            'reference' => $payment['reference'] ?? null,
            'notes' => $payment['notes']
                ?? 'UC-003 Redsys payment applied to UC-004 invoice',
            'allocations' => [[
                'uuid_factura' => $uuidFactura,
                'amount' => $amount,
                'allocation_type' => $payment['allocation_type'] ?? 'INVOICE_PAYMENT',
            ]],
        ];
    }

    private function assertReceivable(
        \PDO $db,
        string $uuidFactura,
        array $snapshot,
        mixed $paymentAmount
    ): void {
        $invoice = $this->invoice($db, $uuidFactura, true);
        if ((int) $invoice['EMESA_ABANS_COBRAMENT'] !== 1
            || (string) $invoice['ESTAT_FACTURA'] !== 'ISSUED'
        ) {
            throw SifException::conflict('Covered invoice is not an issued UC-004 invoice');
        }

        $invoiceTotal = $this->positiveMoney($invoice['TOTAL'], 'Invalid covered invoice total');
        if ($invoiceTotal !== $this->contractTotal($snapshot)) {
            throw SifException::conflict(
                'Covered invoice total does not match frozen course contract total'
            );
        }

        $idInsc = $this->inscriptionId($snapshot);
        $stmt = $db->prepare(
            "SELECT ID, TOTAL FROM factura_linia
             WHERE UUID_FACTURA = ?
               AND SOURCE_TYPE = 'INSCRIPCIO'
               AND SOURCE_ID = ?
             FOR UPDATE"
        );
        $stmt->execute([$uuidFactura, $idInsc]);
        $lines = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (count($lines) !== 1
            || $this->positiveMoney($lines[0]['TOTAL'] ?? null, 'Invalid covered line total')
                !== $invoiceTotal
        ) {
            throw SifException::conflict(
                'Covered course invoice must have one matching full-value inscription line'
            );
        }

        $stmt = $db->prepare(
            "SELECT COALESCE(SUM(CASE
                WHEN pt.TIPUS_MOVIMENT IN ('CHARGE','COMPENSATION') THEN pa.IMPORT_ASSIGNAT
                WHEN pt.TIPUS_MOVIMENT = 'REFUND' THEN -pa.IMPORT_ASSIGNAT
                ELSE 0 END), 0)
             FROM payment_allocation pa
             JOIN payment_transaction pt ON pt.UUID_PAYMENT = pa.UUID_PAYMENT
             WHERE pa.UUID_FACTURA = ?
               AND pt.ESTAT = 'CONFIRMED'"
        );
        $stmt->execute([$uuidFactura]);

        $remaining = $this->cents($invoiceTotal) - $this->signedCents($stmt->fetchColumn());
        $incoming = $this->cents(
            $this->positiveMoney($paymentAmount, 'Invalid covered-invoice payment amount')
        );
        if ($remaining <= 0 || $incoming > $remaining) {
            throw SifException::conflict(
                'Redsys payment exceeds the remaining balance of the covered invoice'
            );
        }
    }

    private function invoice(\PDO $db, string $uuidFactura, bool $forUpdate): array
    {
        $sql =
            'SELECT UUID_FACTURA, NUM_VISIBLE, TOTAL, ESTAT_FACTURA,
                    ESTAT_COBRAMENT, EMESA_ABANS_COBRAMENT
             FROM factura WHERE UUID_FACTURA = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([$uuidFactura]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw SifException::conflict(
                'Invoice-before-payment coverage points to a missing invoice'
            );
        }

        return $row;
    }

    private function inscriptionId(array $snapshot): int
    {
        $inscription = $snapshot['inscription'] ?? null;
        $raw = is_array($inscription) ? ($inscription['ID'] ?? $inscription['id'] ?? null) : null;
        if (!is_numeric($raw) || (int) $raw <= 0) {
            throw SifException::validation('Invalid inscription ID for covered Redsys payment');
        }

        return (int) $raw;
    }

    private function snapshotIdpag(array $snapshot): int
    {
        $inscription = $snapshot['inscription'] ?? null;
        $raw = is_array($inscription)
            ? ($inscription['IDPAG'] ?? $inscription['idpag'] ?? null)
            : null;

        return $this->positiveInt($raw, 'Missing frozen Redsys IDPAG');
    }

    private function positiveInt(mixed $value, string $message): int
    {
        $raw = trim((string) $value);
        if ($raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
            throw SifException::validation($message);
        }

        return (int) $raw;
    }

    private function snapshotPaymentAmount(array $snapshot): string
    {
        $payment = $snapshot['payment'] ?? null;
        $raw = is_array($payment) ? ($payment['amount'] ?? null) : null;

        return $this->positiveMoney($raw, 'Missing frozen Redsys payment amount');
    }

    private function contractTotal(array $snapshot): string
    {
        $inscription = $snapshot['inscription'] ?? null;
        $raw = is_array($inscription)
            ? ($inscription['A_PAGAR'] ?? $inscription['a_pagar'] ?? null)
            : null;

        return $this->positiveMoney($raw, 'Missing frozen course contract total');
    }

    private function positiveMoney(mixed $value, string $message): string
    {
        $raw = trim(str_replace(',', '.', (string) $value));
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $raw)) {
            throw SifException::validation($message);
        }

        $amount = $this->amount($this->unsignedCents($raw));
        if ($amount === '0.00') {
            throw SifException::validation($message);
        }

        return $amount;
    }

    private function cents(string $value): int
    {
        return $this->unsignedCents($value);
    }

    private function signedCents(mixed $value): int
    {
        $raw = trim(str_replace(',', '.', (string) $value));
        if (!preg_match('/^-?\d{1,12}(?:\.\d{1,2})?$/D', $raw)) {
            throw SifException::conflict('Invalid existing payment balance');
        }

        $negative = str_starts_with($raw, '-');
        if ($negative) {
            $raw = substr($raw, 1);
        }

        $cents = $this->unsignedCents($raw);

        return $negative ? -$cents : $cents;
    }

    private function unsignedCents(string $value): int
    {
        [$euros, $decimals] = array_pad(explode('.', $value, 2), 2, '');

        return (int) $euros * 100 + (int) str_pad($decimals, 2, '0');
    }

    private function amount(int $cents): string
    {
        return intdiv($cents, 100)
            . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
