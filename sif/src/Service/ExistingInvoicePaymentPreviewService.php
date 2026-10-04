<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;

final class ExistingInvoicePaymentPreviewService
{
    public function __construct(
        private ManualPaymentInvoiceRepository $invoices,
        private ExistingInvoiceLegacyProjectionService $legacyProjection
    ) {
    }

    public function preview(\PDO $db, array $selector): array
    {
        $uuidFactura = trim((string) ($selector['uuid_factura'] ?? ''));
        $numVisible = strtoupper(trim((string) ($selector['num_visible'] ?? '')));
        $legacyFacturaRelacionada = (int) ($selector['legacy_factura_relacionada'] ?? 0);
        $selectorCount = ($uuidFactura !== '' ? 1 : 0)
            + ($numVisible !== '' ? 1 : 0)
            + ($legacyFacturaRelacionada > 0 ? 1 : 0);

        if ($selectorCount !== 1) {
            throw SifException::validation(
                'Existing invoice preview requires exactly one invoice selector'
            );
        }

        if ($uuidFactura !== '') {
            $invoice = $this->invoices->findByUuid($db, $uuidFactura);
        } elseif ($numVisible !== '') {
            $invoice = $this->invoices->findByNumVisible($db, $numVisible);
        } else {
            $invoice = $this->invoices->findByLegacyFacturaRelacionada(
                $db,
                $legacyFacturaRelacionada
            );
        }

        if ($invoice === null) {
            throw SifException::notFound('SIF invoice not found for payment preview');
        }

        if (strtoupper((string) ($invoice['ESTAT_FACTURA'] ?? '')) !== 'ISSUED') {
            throw SifException::conflict('SIF invoice is not payable in its current state');
        }

        $projection = $this->legacyProjection->build(
            $db,
            (string) $invoice['UUID_FACTURA']
        );

        $totalCents = $this->cents($projection['invoice_total'] ?? null);
        $paidCents = min(
            $totalCents,
            max(0, $this->cents($projection['projected_total'] ?? null))
        );
        $pendingCents = max(0, $totalCents - $paidCents);

        return [
            'ok' => true,
            'action' => 'preview_existing_invoice',
            'invoice' => [
                'uuid_factura' => (string) $invoice['UUID_FACTURA'],
                'num_visible' => (string) $invoice['NUM_VISIBLE'],
                'any_fact' => (int) $invoice['ANY_FACT'],
                'data_emissio' => (string) $invoice['DATA_EMISSIO'],
                'estat_factura' => (string) $invoice['ESTAT_FACTURA'],
                'estat_cobrament' => (string) $invoice['ESTAT_COBRAMENT'],
                'emesa_abans_cobrament' => (int) $invoice['EMESA_ABANS_COBRAMENT'],
                'e_fact' => (int) $invoice['E_FACT'],
                'billing' => [
                    'name' => (string) $invoice['BILLING_NOM_RAO'],
                    'nif' => (string) $invoice['BILLING_NIF_CIF'],
                    'email' => (string) ($invoice['BILLING_EMAIL'] ?? ''),
                ],
                'total' => $this->amount($totalCents),
                'net_paid' => $this->amount($paidCents),
                'pending_amount' => $this->amount($pendingCents),
                'overpaid_amount' => (string) ($projection['overpaid_amount'] ?? '0.00'),
                'can_register_payment' => $pendingCents > 0,
            ],
            'legacy_projection' => $projection,
        ];
    }

    private function cents(mixed $value): int
    {
        $raw = trim(str_replace(',', '.', (string) $value));
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $raw)) {
            throw SifException::validation('Invalid money in existing invoice preview');
        }

        [$euros, $decimals] = array_pad(explode('.', $raw, 2), 2, '');

        return (int) $euros * 100 + (int) str_pad($decimals, 2, '0');
    }

    private function amount(int $cents): string
    {
        return intdiv($cents, 100)
            . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
