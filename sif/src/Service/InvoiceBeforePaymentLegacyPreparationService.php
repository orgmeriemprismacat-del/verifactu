<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Repository\InvoiceBeforePaymentBillingPartyRepository;
use Prisma\Sif\Repository\InvoiceBeforePaymentSelectionRepository;

final class InvoiceBeforePaymentLegacyPreparationService
{
    public function __construct(
        private InvoiceBeforePaymentSelectionRepository $selection,
        private InvoiceBeforePaymentBillingPartyRepository $billing,
        private InvoiceBeforePaymentServerPayloadAssembler $assembler,
        private InvoiceBeforePaymentPayloadBuilder $beforePaymentBuilder,
        private PayloadIdempotencyValidator $fingerprints,
        private ?InvoiceBeforePaymentAeatInputPolicy $aeatPolicy = null
    ) {
    }

    public function prepare(
        \PDO $legacyWebDb,
        \PDO $legacyIntranetDb,
        array $inscriptionIds,
        int $entityId,
        array $context = []
    ): array {
        $selection = $this->selection->loadByIds($legacyWebDb, $inscriptionIds);
        $billing = $this->billing->loadByEntityId($legacyIntranetDb, $entityId);
        $input = $this->assembler->buildInput($selection, $billing, $context);
        if ($this->aeatPolicy !== null) {
            $input = $this->aeatPolicy->prepare($input);
        }
        $payload = $this->beforePaymentBuilder->build($input);

        return [
            'input' => $input,
            'payload' => $payload,
            'fingerprint' => $this->fingerprints->calculateHash($payload),
            'selection' => [
                'ids' => array_values(array_map(
                    static fn (array $row): int => (int) $row['ID'],
                    $selection
                )),
                'count' => count($selection),
            ],
            'billing' => [
                'entity_id' => (int) $billing['entity_id'],
                'name' => (string) $billing['name'],
                'nif' => (string) $billing['nif'],
            ],
        ];
    }
}
