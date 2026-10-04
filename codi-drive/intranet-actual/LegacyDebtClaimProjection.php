<?php

final class LegacyDebtClaimProjection
{
    private const FINAL_REMINDER_MARKER = 'Reclamat fi de curs';
    private const FIRST_CLAIM_MARKER = '1a reclamació correu';

    public static function project(int $idInsc, string $surface, string $stage): array
    {
        if ($idInsc <= 0) {
            throw new InvalidArgumentException('ID_INSC no vàlid per projectar morositat', 422);
        }

        $surface = strtoupper(trim($surface));
        $stage = strtoupper(trim($stage));
        self::assertSupported($surface, $stage);

        require_once __DIR__ . '/ConnexioWeb.php';

        $db = new ConnexioWeb();
        $db->connectarBD();

        try {
            $db->connexio->begin_transaction();

            $stmt = $db->prepare(
                'SELECT `INSC CURS`, reclamat, pag_observacions
                 FROM inscripcions
                 WHERE ID = ?
                 FOR UPDATE'
            );
            if (!$stmt) {
                throw new RuntimeException('No es pot preparar la projecció de morositat', 500);
            }
            $stmt->bind_param('i', $idInsc);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows() !== 1) {
                $stmt->close();
                throw new RuntimeException('No s’ha trobat la inscripció a projectar', 404);
            }

            $stmt->bind_result($inscurs, $reclamat, $pagObservacions);
            $stmt->fetch();
            $stmt->close();

            if (strtoupper(trim((string) $inscurs)) !== '1') {
                throw new RuntimeException(
                    'La inscripció ja no és elegible per projectar la reclamació',
                    409
                );
            }

            $result = $surface === 'RECORDATORI'
                ? self::projectFinalReminder(
                    $db,
                    $idInsc,
                    (string) $reclamat,
                    (string) $pagObservacions
                )
                : self::projectFirstClaim(
                    $db,
                    $idInsc,
                    (string) $reclamat
                );

            $db->connexio->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($db->connexio instanceof mysqli) {
                $db->connexio->rollback();
            }
            throw $exception;
        } finally {
            if ($db->connexio instanceof mysqli) {
                $db->desconectarBD();
            }
        }
    }

    public static function isSupported(string $surface, string $stage): bool
    {
        $surface = strtoupper(trim($surface));
        $stage = strtoupper(trim($stage));

        return ($surface === 'RECORDATORI' && $stage === 'FINAL_REMINDER')
            || ($surface === 'PRIMERA_RECLAMACIO' && $stage === 'FIRST_CLAIM');
    }

    private static function assertSupported(string $surface, string $stage): void
    {
        if (!self::isSupported($surface, $stage)) {
            throw new RuntimeException(
                'La projecció legacy només està habilitada per recordatori i primera reclamació',
                409
            );
        }
    }

    private static function projectFinalReminder(
        ConnexioWeb $db,
        int $idInsc,
        string $reclamat,
        string $pagObservacions
    ): array {
        if (self::containsMarker($pagObservacions, self::FINAL_REMINDER_MARKER)) {
            return [
                'projected' => true,
                'projection_reused' => true,
                'surface' => 'RECORDATORI',
                'id_insc' => $idInsc,
            ];
        }

        $newReclamat = self::appendMarker($reclamat, self::FINAL_REMINDER_MARKER);
        $newPagObservacions = self::appendMarker(
            $pagObservacions,
            self::FINAL_REMINDER_MARKER
        );

        $stmt = $db->prepare(
            'UPDATE inscripcions
             SET data_reclamacio = CURRENT_TIMESTAMP,
                 pag_observacions = ?,
                 reclamat = ?
             WHERE ID = ? AND `INSC CURS` = \'1\''
        );
        if (!$stmt) {
            throw new RuntimeException('No es pot preparar la projecció del recordatori', 500);
        }
        $stmt->bind_param('ssi', $newPagObservacions, $newReclamat, $idInsc);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected !== 1) {
            throw new RuntimeException('La projecció del recordatori ha quedat en conflicte', 409);
        }

        return [
            'projected' => true,
            'projection_reused' => false,
            'surface' => 'RECORDATORI',
            'id_insc' => $idInsc,
        ];
    }

    private static function projectFirstClaim(
        ConnexioWeb $db,
        int $idInsc,
        string $reclamat
    ): array {
        $current = trim($reclamat);
        if (strcasecmp($current, self::FIRST_CLAIM_MARKER) === 0) {
            return [
                'projected' => true,
                'projection_reused' => true,
                'surface' => 'PRIMERA_RECLAMACIO',
                'id_insc' => $idInsc,
            ];
        }

        if ($current !== '') {
            throw new RuntimeException(
                'La inscripció ja té una reclamació legacy diferent; no es pot regressar',
                409
            );
        }

        $marker = self::FIRST_CLAIM_MARKER;
        $stmt = $db->prepare(
            'UPDATE inscripcions
             SET reclamat = ?, data_reclamacio = CURRENT_TIMESTAMP
             WHERE ID = ? AND `INSC CURS` = \'1\'
               AND (reclamat IS NULL OR reclamat = \'\')'
        );
        if (!$stmt) {
            throw new RuntimeException('No es pot preparar la projecció de primera reclamació', 500);
        }
        $stmt->bind_param('si', $marker, $idInsc);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected !== 1) {
            throw new RuntimeException('La projecció de primera reclamació ha quedat en conflicte', 409);
        }

        return [
            'projected' => true,
            'projection_reused' => false,
            'surface' => 'PRIMERA_RECLAMACIO',
            'id_insc' => $idInsc,
        ];
    }

    private static function appendMarker(string $value, string $marker): string
    {
        $value = trim($value);
        if ($value === '') {
            return $marker;
        }
        if (self::containsMarker($value, $marker)) {
            return $value;
        }

        return $value . '. ' . $marker;
    }

    private static function containsMarker(string $value, string $marker): bool
    {
        return stripos($value, $marker) !== false;
    }
}
