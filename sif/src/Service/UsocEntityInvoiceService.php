<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyUsocSnapshotRepository;

final class UsocEntityInvoiceService
{
    public function __construct(
        private LegacyUsocSnapshotRepository $legacySnapshots,
        private LegacyUsocInvoicePayloadBuilder $payloads,
        private InvoiceService $invoices
    ) {
    }

    public function issueEntityFromExplicitInput(\PDO $legacyDb, array $input): array
    {
        $this->assertExplicitEntityInput($input);
        $idpag = $this->positiveInt($input['idpag'], 'Invalid USOC IDPAG');
        $studentAmount = $this->positiveMoney($input['student_amount'], 'Invalid USOC student amount');
        $entityAmount = $this->positiveMoney($input['amount'], 'Invalid USOC entity amount');

        $snapshot = $this->legacySnapshots->loadByIdpag($legacyDb, $idpag, $studentAmount, $entityAmount);
        $payload = $this->payloads->buildEntityPayload($snapshot, $input);
        $result = $this->invoices->issueInvoice($payload);
        $result['payment_registered'] = false;
        $result['student_invoice_uuid'] = (string) $input['student_invoice_uuid'];

        return $result;
    }

    private function assertExplicitEntityInput(array $input): void
    {
        foreach (['idpag', 'student_amount', 'amount', 'student_invoice_uuid', 'billing'] as $field) {
            if (!array_key_exists($field, $input) || $input[$field] === '') {
                throw SifException::validation("Missing USOC entity field {$field}");
            }
        }

        if (!is_array($input['billing'])) {
            throw SifException::validation('Missing USOC entity field billing');
        }

        foreach (['name', 'nif'] as $field) {
            if (!array_key_exists($field, $input['billing']) || trim((string) $input['billing'][$field]) === '') {
                throw SifException::validation("Missing USOC billing.{$field}");
            }
        }

        if (trim((string) $input['student_invoice_uuid']) === '') {
            throw SifException::validation('Missing USOC entity field student_invoice_uuid');
        }
    }

    private function positiveInt(mixed $value, string $message): int
    {
        if (!is_numeric($value)) {
            throw SifException::validation($message);
        }

        $intValue = (int) $value;
        if ($intValue <= 0) {
            throw SifException::validation($message);
        }

        return $intValue;
    }

    private function positiveMoney(mixed $value, string $message): string
    {
        if (!is_numeric($value)) {
            throw SifException::validation($message);
        }

        $amount = (float) $value;
        if ($amount <= 0.0) {
            throw SifException::validation($message);
        }

        return number_format($amount, 2, '.', '');
    }
}
