<?php

declare(strict_types=1);

namespace Prisma\Sif\Domain;

/**
 * Pure, conservative UC-111 fiscal-reference consistency check.
 *
 * An issued rectificative and its persisted factura_rectificacio link are
 * necessary evidence, NOT authorization to issue promotional or cash credit.
 * Any economic eligibility still requires the authenticated cancellation
 * policy and separate approval of the derived right.
 */
final class NovicePromotionRectificationEvidencePolicy
{
    public function assertCancellationReference(
        array $originalInvoice,
        array $rectificativeInvoice,
        array $rectificationLink
    ): void {
        $originalUuid = (string) ($originalInvoice['UUID_FACTURA'] ?? '');
        $rectificativeUuid = (string) ($rectificativeInvoice['UUID_FACTURA'] ?? '');

        if ($originalUuid === '' || $rectificativeUuid === ''
            || $originalUuid === $rectificativeUuid
            || !in_array((string) ($originalInvoice['TIPUS_FACTURA'] ?? ''), ['F1', 'F2'], true)
            || (string) ($originalInvoice['ESTAT_FACTURA'] ?? '') !== 'ISSUED'
            || preg_match('/^R[1-5]$/D', (string) ($rectificativeInvoice['TIPUS_FACTURA'] ?? '')) !== 1
            || (string) ($rectificativeInvoice['ESTAT_FACTURA'] ?? '') !== 'ISSUED'
            || (string) ($rectificationLink['UUID_FACTURA_RECTIFICADA'] ?? '') !== $originalUuid
            || (string) ($rectificationLink['UUID_FACTURA_RECTIFICATIVA'] ?? '') !== $rectificativeUuid
            || trim((string) ($rectificationLink['MOTIU'] ?? '')) === ''
            || trim((string) ($rectificationLink['MODE_RECTIFICACIO'] ?? '')) === ''
        ) {
            throw new \InvalidArgumentException('No matching issued fiscal rectificative for the original course invoice.');
        }

        $originalDate = (string) ($originalInvoice['DATA_EMISSIO'] ?? '');
        $rectificationDate = (string) ($rectificativeInvoice['DATA_EMISSIO'] ?? '');
        if ($originalDate === '' || $rectificationDate === ''
            || $rectificationDate < $originalDate
        ) {
            throw new \InvalidArgumentException('Rectificative predates the original issued invoice.');
        }
    }
}
