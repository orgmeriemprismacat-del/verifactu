<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class ExistingInvoicePaymentCommandService
{
    public function __construct(private ManualPaymentService $manualPayments)
    {
    }

    public function register(\PDO $sifDb, array $command): array
    {
        $selector = $command['selector'] ?? null;
        $payment = $command['payment'] ?? null;

        if (!is_array($selector) || !is_array($payment)) {
            throw SifException::validation('Existing invoice payment requires selector and payment objects');
        }

        $uuidFactura = trim((string) ($selector['uuid_factura'] ?? ''));
        $numVisible = trim((string) ($selector['num_visible'] ?? ''));
        if (($uuidFactura === '') === ($numVisible === '')) {
            throw SifException::validation(
                'Existing invoice payment requires exactly one invoice selector'
            );
        }

        $idempotencyKey = trim((string) ($payment['idempotency_key'] ?? ''));
        if ($idempotencyKey === '') {
            throw SifException::validation(
                'Existing invoice payment requires an explicit idempotency key'
            );
        }

        $result = $uuidFactura !== ''
            ? $this->manualPayments->registerByUuid($sifDb, $uuidFactura, $payment)
            : $this->manualPayments->registerByNumVisible($sifDb, $numVisible, $payment);

        $result['action'] = 'register_existing_invoice';
        $result['payment_committed'] = true;

        return $result;
    }
}
