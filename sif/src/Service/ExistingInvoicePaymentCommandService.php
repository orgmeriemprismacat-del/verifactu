<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class ExistingInvoicePaymentCommandService
{
    public function __construct(
        private ManualPaymentService $manualPayments,
        private ?ExistingInvoiceLegacyProjectionService $legacyProjection = null
    ) {
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

        if ($this->legacyProjection !== null) {
            try {
                $result['legacy_projection'] = $this->legacyProjection->build(
                    $sifDb,
                    (string) $result['uuid_factura']
                );
                $result['legacy_projection_status'] = 'READY';
            } catch (\Throwable $exception) {
                // The SIF payment is already committed at this point. A projection
                // failure must never turn the operation into a second CHARGE on retry.
                $result['legacy_projection_status'] = 'PENDING_RETRY';
                $result['legacy_projection_error'] = substr(
                    str_replace(["\r", "\n"], ' ', $exception->getMessage()),
                    0,
                    240
                );
            }
        }

        return $result;
    }
}
