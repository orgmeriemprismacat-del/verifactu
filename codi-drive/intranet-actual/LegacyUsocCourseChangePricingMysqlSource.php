<?php

require_once __DIR__ . '/LegacyUsocCourseChangePricingSourceInterface.php';

final class LegacyUsocCourseChangePricingMysqlSource
    implements LegacyUsocCourseChangePricingSourceInterface
{
    public function enrollment(int $idInsc): array
    {
        if ($idInsc <= 0) {
            throw new InvalidArgumentException('Invalid USOC course change enrollment id');
        }

        $db = new ConnexioWeb();
        $db->connectarBD();

        try {
            $stmt = $db->prepare(
                'SELECT DATA_INSC, TIPUS_DESC, VALID_DESC, IDPAG, ANY, MES, CURS
                 FROM inscripcions
                 WHERE ID = ?
                 LIMIT 2'
            );
            if ($stmt === false) {
                throw new RuntimeException('Could not prepare USOC source enrollment lookup');
            }

            $stmt->bind_param('i', $idInsc);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows !== 1) {
                $db->closeStmt();
                throw new RuntimeException(
                    'A single USOC source enrollment is required',
                    409
                );
            }

            $stmt->bind_result(
                $date,
                $discountType,
                $discountValid,
                $idpag,
                $year,
                $month,
                $course
            );
            $stmt->fetch();
            $db->closeStmt();

            return [
                'data_insc' => (string) $date,
                'tipus_desc' => (int) $discountType,
                'valid_desc' => (int) $discountValid,
                'idpag' => $idpag === null ? null : (int) $idpag,
                'year' => (string) $year,
                'month' => (string) $month,
                'course' => (string) $course,
            ];
        } finally {
            $db->desconectarBD();
        }
    }

    public function edition(string $year, string $month, string $course): array
    {
        $db = new ConnexioWeb();
        $db->connectarBD();

        try {
            $rows = [];

            $stmt = $db->prepare(
                'SELECT NOM_CURS, HORES, ID_PREU
                 FROM curs
                 WHERE ANY = ? AND MES = ? AND CURS = ?
                 LIMIT 2'
            );
            if ($stmt === false) {
                throw new RuntimeException('Could not prepare target course lookup');
            }
            $yearInt = (int) $year;
            $stmt->bind_param('iss', $yearInt, $month, $course);
            $stmt->execute();
            $stmt->bind_result($title, $hours, $priceId);
            while ($stmt->fetch()) {
                $rows[] = [
                    'kind' => 'COURSE',
                    'title' => (string) $title,
                    'hours' => (string) $hours,
                    'price_id' => (int) $priceId,
                ];
            }
            $db->closeStmt();

            $stmt = $db->prepare(
                'SELECT TITOL, HORES, ID_PREU
                 FROM jornades
                 WHERE ANY = ? AND MES = ? AND CODI_CURS = ?
                 LIMIT 2'
            );
            if ($stmt === false) {
                throw new RuntimeException('Could not prepare target workshop lookup');
            }
            $stmt->bind_param('iss', $yearInt, $month, $course);
            $stmt->execute();
            $stmt->bind_result($title, $hours, $priceId);
            while ($stmt->fetch()) {
                $rows[] = [
                    'kind' => 'WORKSHOP',
                    'title' => (string) $title,
                    'hours' => (string) $hours,
                    'price_id' => (int) $priceId,
                ];
            }
            $db->closeStmt();

            if (count($rows) !== 1) {
                throw new RuntimeException(
                    'A single course-change edition is required',
                    409
                );
            }

            if ($rows[0]['price_id'] <= 0 || trim($rows[0]['hours']) === '') {
                throw new RuntimeException('Invalid course-change edition pricing metadata', 409);
            }

            return $rows[0];
        } finally {
            $db->desconectarBD();
        }
    }

    public function activeStandardPrice(int $priceId): string
    {
        return $this->singleWebMoney(
            'SELECT IMPORT
             FROM preu
             WHERE ID = ?
               AND DATAI <= CURRENT_TIMESTAMP
               AND (DATAF IS NULL OR DATAF >= CURRENT_TIMESTAMP)
             LIMIT 2',
            static function ($stmt) use ($priceId): void {
                $stmt->bind_param('i', $priceId);
            },
            'A single active standard course price is required'
        );
    }

    public function activeUsocPrice(
        int $priceId,
        string $course,
        string $month,
        string $effectiveDate
    ): string {
        return $this->singleWebMoney(
            "SELECT PREU
             FROM descomptes
             WHERE TIPUS = 4
               AND ID_PREU = ?
               AND (CURS = ? OR CURS = 'TOTS')
               AND (MES = ? OR MES = 'TOTS')
               AND DATAI <= ?
               AND (? <= DATAF OR DATAF IS NULL)
             LIMIT 2",
            static function ($stmt) use (
                $priceId,
                $course,
                $month,
                $effectiveDate
            ): void {
                $stmt->bind_param(
                    'issss',
                    $priceId,
                    $course,
                    $month,
                    $effectiveDate,
                    $effectiveDate
                );
            },
            'A single active USOC target price is required'
        );
    }

    public function managementFee(string $hours): string
    {
        $db = new ConnexioIntranet();
        $db->connectarBD();

        try {
            $stmt = $db->prepare(
                "SELECT VALOR
                 FROM params
                 WHERE PARAM = 'despeses-gestio'
                   AND TIPUS = ?
                   AND DATAI <= CURRENT_TIMESTAMP
                   AND (DATAF IS NULL OR DATAF >= CURRENT_TIMESTAMP)
                 LIMIT 2"
            );
            if ($stmt === false) {
                throw new RuntimeException('Could not prepare management-fee lookup');
            }

            $stmt->bind_param('s', $hours);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows !== 1) {
                $db->closeStmt();
                throw new RuntimeException(
                    'A single active course-change management fee is required',
                    409
                );
            }

            $stmt->bind_result($value);
            $stmt->fetch();
            $db->closeStmt();

            return (string) $value;
        } finally {
            $db->desconectarBD();
        }
    }

    private function singleWebMoney(
        string $sql,
        callable $bind,
        string $error
    ): string {
        $db = new ConnexioWeb();
        $db->connectarBD();

        try {
            $stmt = $db->prepare($sql);
            if ($stmt === false) {
                throw new RuntimeException('Could not prepare USOC course-change pricing lookup');
            }

            $bind($stmt);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows !== 1) {
                $db->closeStmt();
                throw new RuntimeException($error, 409);
            }

            $stmt->bind_result($value);
            $stmt->fetch();
            $db->closeStmt();

            return (string) $value;
        } finally {
            $db->desconectarBD();
        }
    }
}
