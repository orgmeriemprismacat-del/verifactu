<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyCourseSnapshotRepository;

final class ManualCourseInvoiceService
{
    public function __construct(
        private LegacyCourseSnapshotRepository $legacySnapshots,
        private ManualCourseInvoicePayloadBuilder $manualPayloads,
        private InvoiceService $invoices
    ) {
    }

    public function issueFromLegacyCoursePayment(\PDO $legacyDb, int $idpag, array $input): array
    {
        $idpag = $this->idpag($idpag);
        $amount = $this->amount($input);

        $snapshot = $this->legacySnapshots->loadByIdpag($legacyDb, $idpag, $amount);
        $payload = $this->manualPayloads->buildFromSnapshot($snapshot, array_replace($input, ['idpag' => $idpag]));
        $result = $this->invoices->issueInvoice($payload);
        $result['legacy_sync'] = [
            'relations' => $payload['relations'] ?? [],
            'estat_cobrament' => isset($payload['payment']) ? 'PAID' : 'PENDING',
        ];

        return $result;
    }

    private function idpag(int $idpag): int
    {
        if ($idpag <= 0) {
            throw SifException::validation('Invalid manual course IDPAG');
        }

        return $idpag;
    }

    private function amount(array $input): string
    {
        foreach (['amount', 'import', 'pagament'] as $key) {
            if (array_key_exists($key, $input) && $input[$key] !== '') {
                if (!is_numeric($input[$key])) {
                    throw SifException::validation('Invalid payment amount');
                }

                $amount = (float) $input[$key];
                if ($amount <= 0.0) {
                    throw SifException::validation('Invalid payment amount');
                }

                return number_format($amount, 2, '.', '');
            }
        }

        throw SifException::validation('Missing payment amount');
    }
}
