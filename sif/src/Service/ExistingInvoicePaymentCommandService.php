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
        $legacyFacturaRelacionada = (int) ($selector['legacy_factura_relacionada'] ?? 0);
        $selectorCount = ($uuidFactura !== '' ? 1 : 0)
            + ($numVisible !== '' ? 1 : 0)
            + ($legacyFacturaRelacionada > 0 ? 1 : 0);
        if ($selectorCount !== 1) {
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

        if ($uuidFactura !== '') {
            $result = $this->manualPayments->registerByUuid(
                $sifDb,
                $uuidFactura,
                $payment
            );
        } elseif ($numVisible !== '') {
            $result = $this->manualPayments->registerByNumVisible(
                $sifDb,
                $numVisible,
                $payment
            );
        } else {
            $result = $this->manualPayments->registerByLegacyFacturaRelacionada(
                $sifDb,
                $legacyFacturaRelacionada,
                $payment
            );
        }

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
                // Payment registration is already successful at the domain level.
                // When an outer audit transaction owns the commit, it will commit the
                // CHARGE and terminal audit event after this method returns. A
                // projection failure must never request a second CHARGE on retry.
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
