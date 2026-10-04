<?php

final class SifLegacyPaymentProjection
{
    public function __construct(private ConnexioWeb $db)
    {
    }

    public function apply(
        string $externalBankEventId,
        string $numFact,
        string $amount,
        string $movementDate
    ): array {
        $externalBankEventId = trim($externalBankEventId);
        $numFact = trim($numFact);
        $normalizedAmount = number_format((float) $amount, 2, '.', '');
        $movementDate = trim($movementDate);

        if ($externalBankEventId === '' || $numFact === '' || (float) $normalizedAmount <= 0) {
            throw new RuntimeException('Invalid legacy payment projection payload', 422);
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr($movementDate, 0, 10));
        if (!$date || $date->format('Y-m-d') !== substr($movementDate, 0, 10)) {
            throw new RuntimeException('Invalid legacy projection movement date', 422);
        }
        $movementDate = $date->format('Y-m-d');

        $payloadHash = hash('sha256', json_encode([
            'external_bank_event_id' => $externalBankEventId,
            'num_fact' => $numFact,
            'amount' => $normalizedAmount,
            'movement_date' => $movementDate,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->db->connectarBD();
        $conn = $this->db->connexio;

        try {
            $conn->begin_transaction();

            $existing = $this->lockProjection($conn, $externalBankEventId);
            if ($existing !== null) {
                if (!hash_equals((string) $existing['PAYLOAD_HASH'], $payloadHash)) {
                    throw new RuntimeException('Legacy projection idempotency conflict', 409);
                }
                if ($existing['STATE'] === 'APPLIED') {
                    $conn->commit();
                    return ['status' => 'REUSED'];
                }
            } else {
                $stmt = $conn->prepare(
                    'INSERT INTO sif_legacy_payment_projection
                    (EXTERNAL_BANK_EVENT_ID, NUM_FACT, AMOUNT, MOVEMENT_DATE, PAYLOAD_HASH, STATE)
                    VALUES (?, ?, ?, ?, ?, \'PENDING\')'
                );
                if (!$stmt) {
                    throw new RuntimeException('Could not prepare legacy projection reservation', 500);
                }
                $stmt->bind_param('ssdss', $externalBankEventId, $numFact, $normalizedAmount, $movementDate, $payloadHash);
                if (!$stmt->execute()) {
                    $stmt->close();
                    throw new RuntimeException('Could not reserve legacy projection', 500);
                }
                $stmt->close();
            }

            $facturaRelacionada = $this->invoiceRelationForUpdate($conn, $numFact);
            $remaining = (float) $normalizedAmount;

            $stmt = $conn->prepare(
                "SELECT ID, A_PAGAR, PAGAMENT
                 FROM inscripcions
                 WHERE FACTURA_RELACIONADA = ?
                   AND (`INSC CURS` = '0' OR `INSC CURS` = '1' OR `INSC CURS` = 'M')
                 ORDER BY ID
                 FOR UPDATE"
            );
            if (!$stmt) {
                throw new RuntimeException('Could not prepare legacy inscription projection', 500);
            }
            $stmt->bind_param('d', $facturaRelacionada);
            $stmt->execute();
            $stmt->bind_result($id, $aPagar, $pagament);

            $rows = [];
            while ($stmt->fetch()) {
                $rows[] = [
                    'id' => (int) $id,
                    'a_pagar' => (float) $aPagar,
                    'pagament' => (float) $pagament,
                ];
            }
            $stmt->close();

            if ($rows === []) {
                throw new RuntimeException('No legacy inscriptions found for generated invoice', 409);
            }

            foreach ($rows as $row) {
                if ($remaining <= 0.00001) {
                    break;
                }

                $outstanding = max(0.0, $row['a_pagar'] - $row['pagament']);
                if ($outstanding <= 0.00001) {
                    continue;
                }

                $delta = min($remaining, $outstanding);
                $newPaid = round($row['pagament'] + $delta, 2);
                $remaining = round($remaining - $delta, 2);

                $update = $conn->prepare(
                    'UPDATE inscripcions
                     SET PAGAMENT = ?, `DATA PAG` = CASE WHEN ? >= A_PAGAR THEN ? ELSE `DATA PAG` END
                     WHERE ID = ?'
                );
                if (!$update) {
                    throw new RuntimeException('Could not prepare legacy inscription update', 500);
                }
                $update->bind_param('ddsi', $newPaid, $newPaid, $movementDate, $row['id']);
                if (!$update->execute()) {
                    $update->close();
                    throw new RuntimeException('Could not update legacy inscription payment', 500);
                }
                $update->close();
            }

            if ($remaining > 0.00001) {
                throw new RuntimeException(
                    'Legacy projection cannot represent the full payment without over-allocating inscriptions',
                    409
                );
            }

            $stmt = $conn->prepare(
                "UPDATE sif_legacy_payment_projection
                 SET STATE = 'APPLIED', ERROR_MESSAGE = NULL, APPLIED_AT = NOW()
                 WHERE EXTERNAL_BANK_EVENT_ID = ?"
            );
            if (!$stmt) {
                throw new RuntimeException('Could not prepare legacy projection completion', 500);
            }
            $stmt->bind_param('s', $externalBankEventId);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            return ['status' => 'APPLIED'];
        } catch (Throwable $exception) {
            $conn->rollback();
            $this->recordFailure(
                $conn,
                $externalBankEventId,
                $numFact,
                $normalizedAmount,
                $movementDate,
                $payloadHash,
                $exception->getMessage()
            );
            throw $exception;
        } finally {
            $this->db->desconectarBD();
        }
    }

    private function lockProjection(mysqli $conn, string $externalBankEventId): ?array
    {
        $stmt = $conn->prepare(
            'SELECT PAYLOAD_HASH, STATE
             FROM sif_legacy_payment_projection
             WHERE EXTERNAL_BANK_EVENT_ID = ?
             FOR UPDATE'
        );
        if (!$stmt) {
            throw new RuntimeException('Could not prepare legacy projection lookup', 500);
        }
        $stmt->bind_param('s', $externalBankEventId);
        $stmt->execute();
        $stmt->bind_result($payloadHash, $state);

        $row = null;
        if ($stmt->fetch()) {
            $row = ['PAYLOAD_HASH' => $payloadHash, 'STATE' => $state];
        }
        $stmt->close();

        return $row;
    }

    private function invoiceRelationForUpdate(mysqli $conn, string $numFact): int
    {
        $stmt = $conn->prepare(
            'SELECT factura_relacionada
             FROM factures
             WHERE NUM = ?
             LIMIT 1
             FOR UPDATE'
        );
        if (!$stmt) {
            throw new RuntimeException('Could not prepare legacy invoice lookup', 500);
        }
        $stmt->bind_param('s', $numFact);
        $stmt->execute();
        $stmt->bind_result($facturaRelacionada);

        if (!$stmt->fetch()) {
            $stmt->close();
            throw new RuntimeException('Generated invoice does not exist in legacy projection', 409);
        }
        $stmt->close();

        return (int) $facturaRelacionada;
    }

    private function recordFailure(
        mysqli $conn,
        string $eventId,
        string $numFact,
        string $amount,
        string $movementDate,
        string $payloadHash,
        string $error
    ): void {
        $error = mb_substr($error, 0, 500, 'UTF-8');

        $stmt = $conn->prepare(
            "INSERT INTO sif_legacy_payment_projection
             (EXTERNAL_BANK_EVENT_ID, NUM_FACT, AMOUNT, MOVEMENT_DATE, PAYLOAD_HASH, STATE, ERROR_MESSAGE)
             VALUES (?, ?, ?, ?, ?, 'FAILED', ?)
             ON DUPLICATE KEY UPDATE
               STATE = IF(PAYLOAD_HASH = VALUES(PAYLOAD_HASH), 'FAILED', STATE),
               ERROR_MESSAGE = IF(PAYLOAD_HASH = VALUES(PAYLOAD_HASH), VALUES(ERROR_MESSAGE), ERROR_MESSAGE),
               UPDATED_AT = NOW()"
        );
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('ssdsss', $eventId, $numFact, $amount, $movementDate, $payloadHash, $error);
        $stmt->execute();
        $stmt->close();
    }
}
