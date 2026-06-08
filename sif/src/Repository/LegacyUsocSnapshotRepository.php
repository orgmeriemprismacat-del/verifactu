<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Exception\SifException;

final class LegacyUsocSnapshotRepository
{
    public function loadByIdpag(\PDO $legacyDb, int $idpag, mixed $studentPaymentAmount, mixed $usocAmount = null): array
    {
        if ($idpag <= 0) {
            throw SifException::validation('Invalid legacy USOC IDPAG');
        }

        $inscription = $this->findInscription($legacyDb, $idpag);
        if ($inscription === null) {
            throw SifException::conflict('Legacy USOC inscription not found for IDPAG');
        }

        $inscription['IDPAG'] = $idpag;
        $this->assertValidatedUsocInscription($inscription);

        $course = $this->findCourse($legacyDb, $inscription);
        if ($course === null) {
            throw SifException::conflict('Legacy course not found for USOC inscription');
        }

        $studentAmount = $this->positiveMoney($studentPaymentAmount, 'Invalid current USOC student payment amount');
        $entityAmount = $usocAmount === null || $usocAmount === ''
            ? null
            : $this->positiveMoney($usocAmount, 'Invalid USOC entity amount');

        return [
            'inscription' => $this->normalise($inscription),
            'course' => $course,
            'usoc' => [
                'student_amount' => $studentAmount,
                'entity_amount' => $entityAmount,
                'tipus_desc' => 4,
                'valid_desc' => 1,
            ],
            'payment' => [
                'amount' => $studentAmount,
                'idpag' => $idpag,
            ],
        ];
    }

    private function findInscription(\PDO $legacyDb, int $idpag): ?array
    {
        $stmt = $legacyDb->prepare(
            'SELECT ID, IDPAG, `ANY`, MES, CURS, NOM, COGNOMS, DNI, CORREU,
                    ADRECA, Codi_Postal, Poblacio, FACTURA_RELACIONADA,
                    A_PAGAR, PAGAMENT, TIPUS_DESC, VALID_DESC, FRACCIO, FRACCIONAT
             FROM inscripcions
             WHERE IDPAG = ?
               AND (`INSC CURS` = \'0\' OR `INSC CURS` = \'1\' OR `INSC CURS` = \'M\')
             ORDER BY ID
             LIMIT 1'
        );
        $stmt->execute([$idpag]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function findCourse(\PDO $legacyDb, array $inscription): ?array
    {
        foreach (['ANY', 'MES', 'CURS'] as $field) {
            if (!array_key_exists($field, $inscription) || $inscription[$field] === '') {
                throw SifException::validation("Missing legacy USOC inscription field {$field}");
            }
        }

        $stmt = $legacyDb->prepare(
            'SELECT NOM_CURS, DATAI, DATAF, HORES
             FROM curs
             WHERE `ANY` = ? AND MES = ? AND CURS = ?'
        );
        $stmt->execute([
            (int) $inscription['ANY'],
            (string) $inscription['MES'],
            (string) $inscription['CURS'],
        ]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function assertValidatedUsocInscription(array $inscription): void
    {
        $tipusDesc = $this->requiredInt($inscription, 'TIPUS_DESC', 'Missing legacy USOC TIPUS_DESC');
        if ($tipusDesc !== 4) {
            throw SifException::conflict('Legacy inscription is not a USOC discount');
        }

        $validDesc = $this->requiredInt($inscription, 'VALID_DESC', 'Missing legacy USOC VALID_DESC');
        if ($validDesc !== 1) {
            throw SifException::conflict('Legacy USOC discount is not validated');
        }
    }

    private function normalise(array $inscription): array
    {
        foreach (['ID', 'IDPAG', 'TIPUS_DESC', 'VALID_DESC'] as $field) {
            $inscription[$field] = $this->requiredInt(
                $inscription,
                $field,
                "Missing legacy USOC inscription field {$field}"
            );
        }

        return $inscription;
    }

    private function requiredInt(array $data, string $field, string $message): int
    {
        if (!array_key_exists($field, $data) || $data[$field] === '' || !is_numeric($data[$field])) {
            throw SifException::validation($message);
        }

        return (int) $data[$field];
    }

    private function positiveMoney(mixed $value, string $message): string
    {
        if (!is_numeric($value)) {
            throw SifException::validation($message);
        }

        $amount = (float) $value;
        if ($amount <= 0.0) {
            throw SifException::validation($message);
        }

        return number_format($amount, 2, '.', '');
    }
}
