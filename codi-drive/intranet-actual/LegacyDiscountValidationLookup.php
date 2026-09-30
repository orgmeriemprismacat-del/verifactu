<?php

final class LegacyDiscountValidationLookup
{
    public function isUsoc(int $idInsc): bool
    {
        return (int) $this->enrollment($idInsc)['TIPUS_DESC'] === 4;
    }

    public function enrollment(int $idInsc): array
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
            $stmt = $db->prepare('SELECT ID, TIPUS_DESC, VALID_DESC, IDPAG FROM inscripcions WHERE ID = ?');
            if ($stmt === false) {
                throw new RuntimeException('Could not prepare legacy discount lookup');
            }

            $stmt->bind_param('i', $idInsc);
            if (!$stmt->execute()) {
                throw new RuntimeException('Could not execute legacy discount lookup');
            }

            $stmt->bind_result($id, $tipusDesc, $validDesc, $idpag);
            if (!$stmt->fetch()) {
                throw new RuntimeException('Enrollment not found for discount validation');
            }
            $stmt->close();

            return [
                'ID' => (int) $id,
                'TIPUS_DESC' => (int) $tipusDesc,
                'VALID_DESC' => (int) $validDesc,
                'IDPAG' => $idpag === null ? null : (int) $idpag,
            ];
        } finally {
            $db->close();
        }
    }
}
