<?php

require_once __DIR__ . '/LegacyUsocCourseChangeDestinationStoreInterface.php';

final class LegacyUsocCourseChangeDestinationMysqlStore
    implements LegacyUsocCourseChangeDestinationStoreInterface
{
    private ConnexioWeb $db;

    public function __construct(?ConnexioWeb $db = null)
    {
        $this->db = $db ?? new ConnexioWeb();
        $this->db->connectarBD();
    }

    public function __destruct()
    {
        $this->db->desconectarBD();
    }

    public function acquire(string $lockName, int $timeoutSeconds = 10): void
    {
        $stmt = $this->db->prepare('SELECT GET_LOCK(?, ?)');
        if ($stmt === false) {
            throw new RuntimeException('Could not prepare USOC destination reservation lock');
        }

        $stmt->bind_param('si', $lockName, $timeoutSeconds);
        $stmt->execute();
        $stmt->bind_result($acquired);
        $stmt->fetch();
        $this->db->closeStmt();

        if ((int) $acquired !== 1) {
            throw new RuntimeException(
                'Could not acquire USOC course change destination lock',
                409
            );
        }
    }

    public function release(string $lockName): void
    {
        $stmt = $this->db->prepare('SELECT RELEASE_LOCK(?)');
        if ($stmt === false) {
            return;
        }

        $stmt->bind_param('s', $lockName);
        $stmt->execute();
        $this->db->closeStmt();
    }

    public function source(int $idInsc): array
    {
        $stmt = $this->db->prepare(
            "SELECT ID, IDPAG, TIPUS_DESC, VALID_DESC, `INSC CURS`
             FROM inscripcions
             WHERE ID = ?
             LIMIT 2"
        );
        if ($stmt === false) {
            throw new RuntimeException('Could not prepare USOC source reservation lookup');
        }

        $stmt->bind_param('i', $idInsc);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows !== 1) {
            $this->db->closeStmt();
            throw new RuntimeException(
                'A single USOC source enrollment is required for destination reservation',
                409
            );
        }

        $stmt->bind_result($id, $idpag, $tipusDesc, $validDesc, $status);
        $stmt->fetch();
        $this->db->closeStmt();

        return [
            'id' => (int) $id,
            'idpag' => (int) $idpag,
            'tipus_desc' => (int) $tipusDesc,
            'valid_desc' => (int) $validDesc,
            'status' => (string) $status,
        ];
    }

    public function findByMarker(string $marker): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT ID, IDPAG, `ANY`, MES, CURS, A_PAGAR, PAGAMENT,
                    TIPUS_DESC, VALID_DESC, `INSC CURS`, pag_observacions
             FROM inscripcions
             WHERE pag_observacions = ?
             LIMIT 2"
        );
        if ($stmt === false) {
            throw new RuntimeException('Could not prepare USOC destination marker lookup');
        }

        $stmt->bind_param('s', $marker);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            $this->db->closeStmt();
            return null;
        }

        if ($stmt->num_rows !== 1) {
            $this->db->closeStmt();
            throw new RuntimeException(
                'USOC course change reservation marker is not unique',
                409
            );
        }

        $stmt->bind_result(
            $id,
            $idpag,
            $year,
            $month,
            $course,
            $amount,
            $paid,
            $tipusDesc,
            $validDesc,
            $status,
            $storedMarker
        );
        $stmt->fetch();
        $this->db->closeStmt();

        return [
            'id' => (int) $id,
            'idpag' => (int) $idpag,
            'year' => (string) $year,
            'month' => (string) $month,
            'course' => (string) $course,
            'a_pagar' => number_format((float) $amount, 2, '.', ''),
            'pagament' => number_format((float) $paid, 2, '.', ''),
            'tipus_desc' => (int) $tipusDesc,
            'valid_desc' => (int) $validDesc,
            'status' => (string) $status,
            'marker' => (string) $storedMarker,
        ];
    }

    public function insertFromSource(
        int $sourceId,
        string $targetYear,
        string $targetMonth,
        string $targetCourse,
        string $targetStudentTotal,
        string $marker
    ): array {
        $sql =
            "INSERT INTO inscripcions (
                `ANY`, MES, CURS, DATA_INSC,
                NOM, COGNOMS, CORREU, DNI, ADRECA, Codi_Postal, Poblacio,
                PERFIL, Titulacio, TELEFON,
                A_PAGAR, FRACCIO, PAGAMENT, `DATA PAG`,
                FACTURA_RELACIONADA, `INSC CURS`, TIPUS_INSC,
                OBSERVACIONS, COMENTARIS, pag_observacions,
                FRACCIONAT, usuari, INSC_MAILING, CONEGUT,
                IDPAG, TIPUS_DESC, VALID_DESC
             )
             SELECT
                ?, ?, ?, CURRENT_TIMESTAMP,
                NOM, COGNOMS, CORREU, DNI, ADRECA, Codi_Postal, Poblacio,
                PERFIL, Titulacio, TELEFON,
                CAST(? AS DECIMAL(10,2)), FRACCIO, 0, NULL,
                NULL, '0', TIPUS_INSC,
                OBSERVACIONS, COMENTARIS, ?,
                0, usuari, INSC_MAILING, CONEGUT,
                IDPAG, 4, 1
             FROM inscripcions
             WHERE ID = ?
               AND TIPUS_DESC = 4
               AND VALID_DESC = 1
               AND IDPAG > 0
               AND `INSC CURS` IN ('0', '1', 'M')
             LIMIT 1";

        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Could not prepare USOC destination reservation insert');
        }

        $year = (int) $targetYear;
        $stmt->bind_param(
            'issssi',
            $year,
            $targetMonth,
            $targetCourse,
            $targetStudentTotal,
            $marker,
            $sourceId
        );
        $stmt->execute();
        $affected = (int) $stmt->affected_rows;
        $this->db->closeStmt();

        if ($affected !== 1) {
            throw new RuntimeException(
                'USOC destination reservation source changed before insert',
                409
            );
        }

        $id = (int) $this->db->lastInsertId();
        if ($id <= 0) {
            throw new RuntimeException(
                'USOC destination reservation did not return an enrollment id',
                409
            );
        }

        return $this->findByMarker($marker)
            ?? throw new RuntimeException(
                'USOC destination reservation could not be reloaded',
                409
            );
    }
}
