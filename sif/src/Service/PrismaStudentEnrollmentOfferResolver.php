<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Domain\PrismaStudentDiscountPolicy;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyPrismaStudentHistoryRepository;

/**
 * Resolves the authoritative Alumne PrisMa quote before a legacy enrollment is
 * inserted. This is deliberately a quote, not a persisted commercial_operation:
 * the enrollment does not have a canonical legacy ID yet.
 *
 * The caller may identify the requested person/course only. Eligibility and all
 * monetary values are resolved from the legacy database inside the SIF boundary.
 */
final class PrismaStudentEnrollmentOfferResolver
{
    public const PRICE_RULE_VERSION = 'LEGACY_AP_CURRENT_V1';

    public function __construct(
        private LegacyPrismaStudentHistoryRepository $history,
        private PrismaStudentDiscountPolicy $policy
    ) {
    }

    public function resolve(
        \PDO $legacyDb,
        string $document,
        int $year,
        string $month,
        string $courseCode
    ): array {
        $document = strtoupper(trim($document));
        $courseCode = strtoupper(trim($courseCode));
        $month = trim($month);

        if ($document === '' || strlen($document) > 40
            || $year < 1
            || !preg_match('/^(?:0[1-9]|1[0-2])$/D', $month)
            || $courseCode === '' || strlen($courseCode) > 80
        ) {
            throw SifException::validation('Invalid Alumne PrisMa enrollment offer lookup.');
        }

        $evaluationAt = $this->databaseNow($legacyDb);
        $decision = $this->policy->evaluate(
            $this->history->findByDocument($legacyDb, $document)
        );

        if (($decision['eligible'] ?? false) !== true) {
            return [
                'status' => 'NOT_ELIGIBLE',
                'discount_type' => 'ALUMNE_PRISMA',
                'rule_version' => (string) ($decision['rule_version'] ?? PrismaStudentDiscountPolicy::RULE_VERSION),
                'reason' => (string) ($decision['reason'] ?? 'NO_ELIGIBLE_HISTORY'),
                'evaluation_at' => $evaluationAt,
            ];
        }

        $courseRows = $this->many(
            $legacyDb,
            'SELECT `ANY`, MES, CURS, NOM_CURS, HORES, ID_PREU
             FROM curs
             WHERE `ANY` = ? AND MES = ? AND CURS = ?',
            [$year, $month, $courseCode]
        );
        if (count($courseRows) !== 1) {
            throw SifException::conflict(
                'Requested course edition for Alumne PrisMa is missing or ambiguous.'
            );
        }
        $course = $courseRows[0];

        $idPreu = $this->positiveInt($course['ID_PREU'] ?? null, 'course.ID_PREU');
        $hours = trim((string) ($course['HORES'] ?? ''));
        if ($hours === '') {
            throw SifException::conflict('Requested course hours are missing.');
        }

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
            throw SifException::conflict(
                'Current base tariff for Alumne PrisMa is missing or ambiguous.'
            );
        }
        $gross = $this->moneyToCents($baseRows[0]['IMPORT'] ?? null, 'current base tariff');

        // Compatibility with ajax/calcularPreu.php: DESCOMPTES.CURS may contain
        // the course code, the course hours, or TOTS.
        $discountRows = $this->many(
            $legacyDb,
            "SELECT PREU
             FROM descomptes
             WHERE DATAI <= ?
               AND (DATAF IS NULL OR DATAF >= ?)
               AND ID_PREU = ?
               AND (CURS = ? OR CURS = ? OR CURS = 'TOTS')
               AND (MES = 'TOTS' OR MES = ?)
               AND TIPUS = 1",
            [$evaluationAt, $evaluationAt, $idPreu, $courseCode, $hours, $month]
        );

        if ($discountRows === []) {
            return [
                'status' => 'ELIGIBLE_NO_PRICE',
                'discount_type' => 'ALUMNE_PRISMA',
                'rule_version' => (string) $decision['rule_version'],
                'reason' => (string) $decision['reason'],
                'evaluation_at' => $evaluationAt,
                'gross_amount' => $this->centsToMoney($gross),
                'price_rule_version' => self::PRICE_RULE_VERSION,
                'price_source' => ['id_preu' => $idPreu],
            ];
        }
        if (count($discountRows) !== 1) {
            throw SifException::conflict(
                'Current Alumne PrisMa tariff is ambiguous.'
            );
        }

        $net = $this->moneyToCents(
            $discountRows[0]['PREU'] ?? null,
            'current Alumne PrisMa tariff'
        );
        if ($gross <= 0 || $net <= 0 || $net >= $gross) {
            throw SifException::conflict(
                'Current Alumne PrisMa tariff is not a valid discounted price.'
            );
        }

        return [
            'status' => 'ELIGIBLE',
            'discount_type' => 'ALUMNE_PRISMA',
            'rule_version' => (string) $decision['rule_version'],
            'reason' => (string) $decision['reason'],
            'evaluation_at' => $evaluationAt,
            'course' => [
                'year' => (int) $course['ANY'],
                'month' => (string) $course['MES'],
                'code' => (string) $course['CURS'],
                'title' => (string) $course['NOM_CURS'],
                'hours' => $hours,
            ],
            'gross_amount' => $this->centsToMoney($gross),
            'discount_amount' => $this->centsToMoney($gross - $net),
            'net_amount' => $this->centsToMoney($net),
            'currency' => 'EUR',
            'price_rule_version' => self::PRICE_RULE_VERSION,
            'price_source' => ['id_preu' => $idPreu],
        ];
    }

    private function databaseNow(\PDO $legacyDb): string
    {
        $value = $legacyDb->query('SELECT CURRENT_TIMESTAMP')->fetchColumn();
        $value = trim((string) $value);
        if ($value === '' || strtotime($value) === false) {
            throw new \RuntimeException('Could not resolve legacy database time.');
        }

        return $value;
    }

    private function positiveInt(mixed $value, string $field): int
    {
        $raw = trim((string) $value);
        if ($raw === '' || !ctype_digit($raw) || (int) $raw < 1) {
            throw SifException::validation('Invalid ' . $field);
        }

        return (int) $raw;
    }

    private function moneyToCents(mixed $value, string $field): int
    {
        $raw = trim((string) $value);
        if (!preg_match('/^(\\d{1,10})(?:\\.(\\d{1,2}))?$/D', $raw, $matches)) {
            throw SifException::validation('Invalid ' . $field);
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
