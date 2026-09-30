<?php

final class LegacyNoviceValidationLookup
{
    public function status(int $idInsc): int
    {
        if ($idInsc <= 0) {
            throw new InvalidArgumentException('Invalid enrollment id');
        }

        $configPath = __DIR__ . '/parametres-connexio-intranet.php';
        if (!is_file($configPath)) {
            throw new RuntimeException('Legacy intranet database configuration is unavailable');
        }

        require $configPath;

        $db = new mysqli($servidor, $usuari, $pw, $bbdd);
        if ($db->connect_errno) {
            throw new RuntimeException('Could not connect to legacy intranet database');
        }

        try {
            $db->set_charset('utf8mb4');
            $stmt = $db->prepare(
                "SELECT r.VALIDAT
                 FROM recent_titulat r
                 JOIN inscripcions i ON i.ID = r.ID_INSC
                 WHERE r.ID_INSC = ? AND i.CURS = 'JASOM'
                 LIMIT 2"
            );
            if ($stmt === false) {
                throw new RuntimeException('Could not prepare novice validation lookup');
            }

            $stmt->bind_param('i', $idInsc);
            if (!$stmt->execute()) {
                throw new RuntimeException('Could not execute novice validation lookup');
            }

            $stmt->store_result();
            if ($stmt->num_rows !== 1) {
                $stmt->close();
                throw new RuntimeException('A single JASOM novice validation row is required');
            }

            $stmt->bind_result($status);
            $stmt->fetch();
            $stmt->close();

            $status = (int) $status;
            if (!in_array($status, [0, 1, 2], true)) {
                throw new RuntimeException('Invalid novice validation status');
            }

            return $status;
        } finally {
            $db->close();
        }
    }
}
