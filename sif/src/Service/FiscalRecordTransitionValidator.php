<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

/** Apply after idempotency lookup, both for read-only preview and creation. */
final class FiscalRecordTransitionValidator
{
    public function validate(array $invoice, array $previous, array $payload): void
    {
        $recordType = $payload['record_type'];
        $rejectedCancellation = $recordType === 'ANULACIO'
            && $previous['TIPUS_REGISTRE'] === 'ANULACIO'
            && $previous['ESTAT_AEAT'] === 'REJECTED'
            && ($payload['cancellation_mode'] ?? '') === 'RECHAZO_PREVIO';
        if ($recordType === 'ANULACIO' && $previous['TIPUS_REGISTRE'] === 'ANULACIO' && !$rejectedCancellation) {
            throw SifException::conflict('Invoice already has a cancellation record');
        }
        if ($recordType === 'SUBSANACIO' && $invoice['ESTAT_FACTURA'] === 'CANCELLED') {
            throw SifException::conflict('Cancelled invoices do not accept subsanation records');
        }
        if (isset($payload['aeat_original'])
            && (in_array($payload['subsanation_kind'] ?? '',
                ['RECHAZO_PREVIO', 'SIN_REGISTRO_PREVIO', 'SUBSANACION_RECHAZADA'], true)
                || ($payload['cancellation_mode'] ?? '') === 'RECHAZO_PREVIO')
            && $previous['ESTAT_AEAT'] !== 'REJECTED') {
            throw SifException::conflict('Previous AEAT rejection is required for this operation');
        }
    }
}
