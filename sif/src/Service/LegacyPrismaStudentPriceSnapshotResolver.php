<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

/**
 * Reconstructs the Alumne PrisMa commercial price that was valid when the
 * legacy enrollment was created. It fails closed if the historical tariff is
 * missing, ambiguous or no longer matches the enrollment's persisted A_PAGAR.
 */
final class LegacyPrismaStudentPriceSnapshotResolver
{
    public function resolve(\PDO $legacyDb, array $courseContext): array
    {
        $inscription = $courseContext['inscription'] ?? null;
        $course = $courseContext['course'] ?? null;
        if (!is_array($inscription) || !is_array($course)) {
            throw SifException::validation('Legacy course context is incomplete.');
        }

        foreach (['DATA_INSC', 'CURS', 'MES', 'A_PAGAR'] as $field) {
            if (!array_key_exists($field, $inscription) || $inscription[$field] === '' || $inscription[$field] === null) {
                throw SifException::validation('Missing legacy inscription commercial field ' . $field);
            }
        }
        foreach (['ID_PREU', 'HORES', 'NOM_CURS'] as $field) {
            if (!array_key_exists($field, $course) || $course[$field] === '' || $course[$field] === null) {
                throw SifException::validation('Missing legacy course commercial field ' . $field);
            }
        }

        $idPreu = $this->positiveInt($course['ID_PREU'], 'course.ID_PREU');
        $evaluationAt = (string) $inscription['DATA_INSC'];

        $baseRows = $this->many(
            $legacyDb,
            'SELECT IMPORT
             FROM preu
             WHERE ID = ?
               AND DATAI <= ?
               AND (DATAF IS NULL OR DATAF >= ?)',
            [$idPreu, $evaluationAt, $evaluationAt]
        );
        if (count($baseRows) !== 1) {
            throw SifException::conflict('Historical base tariff for Alumne PrisMa is missing or ambiguous.');
        }

        $discountRows = $this->many(
            $legacyDb,
            "SELECT PREU
             FROM descomptes
             WHERE ID_PREU = ?
               AND TIPUS = 1
               AND DATAI <= ?
               AND (DATAF IS NULL OR DATAF >= ?)
               AND (CURS = 'TOTS' OR CURS = ? OR CURS = ?)
               AND (MES = 'TOTS' OR MES = ?)",
            [
                $idPreu,
                $evaluationAt,
                $evaluationAt,
                (string) $inscription['CURS'],
                (string) $course['HORES'],
                (string) $inscription['MES'],
            ]
        );
        if (count($discountRows) !== 1) {
            throw SifException::conflict('Historical Alumne PrisMa tariff is missing or ambiguous.');
        }

        $gross = $this->moneyToCents($baseRows[0]['IMPORT'] ?? null, 'historical base tariff');
        $net = $this->moneyToCents($discountRows[0]['PREU'] ?? null, 'historical Alumne PrisMa tariff');
        $persistedNet = $this->moneyToCents($inscription['A_PAGAR'], 'legacy A_PAGAR');

        if ($gross <= 0 || $net <= 0 || $gross <= $net || $net !== $persistedNet) {
            throw SifException::conflict(
                'Historical Alumne PrisMa tariff does not reproduce the persisted enrollment price.'
            );
        }

        return [
            'gross_amount' => $this->centsToMoney($gross),
            'discount_amount' => $this->centsToMoney($gross - $net),
            'net_amount' => $this->centsToMoney($net),
            'course_title' => trim((string) $course['NOM_CURS']),
            'price_rule_version' => 'LEGACY_AP_TARIFF_AT_ENROLLMENT_V1',
            'price_source' => [
                'id_preu' => $idPreu,
                'evaluated_at' => $evaluationAt,
            ],
            'tax_snapshot' => [
                'regime' => 'EXEMPT',
                'iva_pct' => '0.00',
                'iva_import' => '0.00',
            ],
        ];
    }

    private function positiveInt(mixed $value, string $label): int
    {
        $raw = trim((string) $value);
        if ($raw === '' || !ctype_digit($raw) || (int) $raw < 1) {
            throw SifException::validation('Invalid ' . $label);
        }

        return (int) $raw;
    }

    private function moneyToCents(mixed $value, string $label): int
    {
        $raw = trim((string) $value);
        if (!preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/D', $raw, $matches)) {
            throw SifException::validation('Invalid ' . $label);
        }

        return (int) $matches[1] * 100 + (int) str_pad($matches[2] ?? '', 2, '0');
    }

    private function centsToMoney(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function many(\PDO $db, string $sql, array $parameters): array
    {
        $statement = $db->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }
}
