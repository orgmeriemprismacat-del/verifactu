<?php

final class LegacyDiscountValidationLookup
{
    public function isUsoc(int $idInsc): bool
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
            $stmt = $db->prepare('SELECT TIPUS_DESC FROM inscripcions WHERE ID = ?');
            if ($stmt === false) {
                throw new RuntimeException('Could not prepare legacy discount lookup');
            }

            $stmt->bind_param('i', $idInsc);
            if (!$stmt->execute()) {
                throw new RuntimeException('Could not execute legacy discount lookup');
            }

            $stmt->bind_result($tipusDesc);
            if (!$stmt->fetch()) {
                throw new RuntimeException('Enrollment not found for discount validation');
            }
            $stmt->close();

            return (int) $tipusDesc === 4;
        } finally {
            $db->close();
        }
    }
}
