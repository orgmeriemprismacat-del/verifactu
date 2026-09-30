<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Exception\SifException;

final class InvoiceBeforePaymentBillingPartyRepository
{
    public function loadByEntityId(\PDO $legacyIntranetDb, int $entityId): array
    {
        if ($entityId <= 0) {
            throw SifException::validation('Invalid invoice before payment entity ID');
        }

        $stmt = $legacyIntranetDb->prepare(
            'SELECT ID, CIF, RAO, ADRECA, CP, POBLACIO, ID_RESP
             FROM entitats
             WHERE ID = ?
             LIMIT 1'
        );
        $stmt->execute([$entityId]);
        $entity = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!is_array($entity)) {
            throw SifException::conflict('Invoice before payment billing entity not found');
        }

        $name = trim((string) ($entity['RAO'] ?? ''));
        $nif = trim((string) ($entity['CIF'] ?? ''));
        if ($name === '' || $nif === '') {
            throw SifException::conflict('Invoice before payment billing entity has incomplete fiscal identity');
        }

        $responsibleId = $entity['ID_RESP'] ?? null;
        if ($responsibleId === null || $responsibleId === '' || !is_numeric($responsibleId) || (int) $responsibleId <= 0) {
            throw SifException::conflict(
                'Invoice before payment billing entity has no valid responsible record'
            );
        }

        $responsible = $this->loadActiveResponsible($legacyIntranetDb, (int) $responsibleId);
        if ($responsible === null) {
            throw SifException::conflict(
                'Invoice before payment billing entity has no active responsible record'
            );
        }

        return [
            'entity_id' => (int) $entity['ID'],
            'name' => $name,
            'nif' => $nif,
            'address' => $this->nullableString($entity['ADRECA'] ?? null),
            'cp' => $this->nullableString($entity['CP'] ?? null),
            'city' => $this->nullableString($entity['POBLACIO'] ?? null),
            'province' => null,
            'country' => 'ES',
            'email' => $responsible['email'] ?? null,
            'responsible' => $responsible,
        ];
    }

    private function loadActiveResponsible(\PDO $db, int $responsibleId): ?array
    {
        if ($responsibleId <= 0) {
            return null;
        }

        $stmt = $db->prepare(
            'SELECT NOM, COGNOMS, CORREU
             FROM entitats_resp
             WHERE ID_RESP = ?
               AND DATAI <= CURRENT_TIMESTAMP
               AND (DATAF IS NULL OR DATAF >= CURRENT_TIMESTAMP)
             ORDER BY DATAI DESC
             LIMIT 2'
        );
        $stmt->execute([$responsibleId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (!is_array($rows) || $rows === []) {
            return null;
        }

        if (count($rows) > 1) {
            throw SifException::conflict(
                'Invoice before payment billing entity has more than one active responsible record'
            );
        }

        $row = $rows[0];

        return [
            'name' => $this->nullableString($row['NOM'] ?? null),
            'surname' => $this->nullableString($row['COGNOMS'] ?? null),
            'email' => $this->nullableString($row['CORREU'] ?? null),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
