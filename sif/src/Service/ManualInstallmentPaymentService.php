<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;

final class ManualInstallmentPaymentService
{
    public function __construct(
        private ManualPaymentInvoiceRepository $invoices,
        private ManualInstallmentPaymentPayloadBuilder $installments,
        private PaymentService $payments
    ) {
    }

    public function registerByUuid(\PDO $sifDb, string $uuidFactura, array $input, ?callable $afterPersist = null): array
    {
        $uuidFactura = trim($uuidFactura);
        if ($uuidFactura === '') {
            throw SifException::validation('Missing SIF invoice UUID');
        }

        $invoice = $this->invoices->findByUuid($sifDb, $uuidFactura);
        if ($invoice === null) {
            throw SifException::validation('SIF invoice not found for manual installment');
        }

        return $this->registerForInvoice($invoice, $input, $afterPersist);
    }

    public function registerByNumVisible(\PDO $sifDb, string $numVisible, array $input, ?callable $afterPersist = null): array
    {
        $numVisible = trim($numVisible);
        if ($numVisible === '') {
            throw SifException::validation('Missing SIF invoice number');
        }

        $invoice = $this->invoices->findByNumVisible($sifDb, $numVisible);
        if ($invoice === null) {
            throw SifException::validation('SIF invoice not found for manual installment');
        }

        return $this->registerForInvoice($invoice, $input, $afterPersist);
    }

    private function registerForInvoice(
        array $invoice,
        array $input,
        ?callable $afterPersist
    ): array {
        $payload = $this->installments->forExistingInvoice(
            (string) $invoice['UUID_FACTURA'],
            $input
        );

        $wrappedAfterPersist = $afterPersist === null
            ? null
            : function (\PDO $db, array $paymentPayload, array $paymentResult) use (
                $afterPersist,
                $invoice
            ): void {
                $enriched = $paymentResult;
                $enriched['uuid_factura'] = $invoice['UUID_FACTURA'];
                $enriched['num_visible'] = $invoice['NUM_VISIBLE'];
                $afterPersist($db, $paymentPayload, $enriched);
            };

        $result = $this->payments->registerPayment($payload, $wrappedAfterPersist);
        $result['uuid_factura'] = $invoice['UUID_FACTURA'];
        $result['num_visible'] = $invoice['NUM_VISIBLE'];

        return $result;
    }
}
