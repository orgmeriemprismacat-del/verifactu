<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;

final class ManualPaymentService
{
    public function __construct(
        private ManualPaymentInvoiceRepository $invoices,
        private ManualPaymentPayloadBuilder $manualPayments,
        private PaymentService $payments,
        private ?JointInvoiceEnrollmentFundAllocationService $participantFunds = null
    ) {
    }

    public function registerByUuid(\PDO $sifDb, string $uuidFactura, array $input): array
    {
        $uuidFactura = trim($uuidFactura);
        if ($uuidFactura === '') {
            throw SifException::validation('Missing SIF invoice UUID');
        }

        $invoice = $this->invoices->findByUuid($sifDb, $uuidFactura);
        if ($invoice === null) {
            throw SifException::validation('SIF invoice not found for manual payment');
        }

        return $this->registerForInvoice($sifDb, $invoice, $input);
    }

    public function registerByNumVisible(\PDO $sifDb, string $numVisible, array $input): array
    {
        $numVisible = trim($numVisible);
        if ($numVisible === '') {
            throw SifException::validation('Missing SIF invoice number');
        }

        $invoice = $this->invoices->findByNumVisible($sifDb, $numVisible);
        if ($invoice === null) {
            throw SifException::validation('SIF invoice not found for manual payment');
        }

        return $this->registerForInvoice($invoice, $input);
    }

    private function registerForInvoice(\PDO $sifDb, array $invoice, array $input): array
    {
        $input['num_visible'] = $input['num_visible'] ?? ($invoice['NUM_VISIBLE'] ?? null);
        $payload = $this->manualPayments->forExistingInvoice((string) $invoice['UUID_FACTURA'], $input);
        $result = $this->payments->registerPayment($payload);
        $result['uuid_factura'] = $invoice['UUID_FACTURA'];
        $result['num_visible'] = $invoice['NUM_VISIBLE'];

        if (array_key_exists('participant_allocations', $input)) {
            if (!is_array($input['participant_allocations']) || $this->participantFunds === null) {
                throw SifException::validation(
                    'Participant allocations require the UC-021 allocation service'
                );
            }

            $result['participant_allocations'] = $this->participantFunds->allocate(
                $sifDb,
                (string) $result['uuid_payment'],
                (string) $invoice['UUID_FACTURA'],
                $input['participant_allocations'],
                'MANUAL_PAYMENT|' . (string) $result['uuid_payment']
            );
        }

        return $result;
    }
}
