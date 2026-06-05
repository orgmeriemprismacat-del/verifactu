<?php

namespace Prisma\Sif\Repository;

final class RedsysNotificationRepository
{
    public function findByDsOrder(\PDO $db, string $dsOrder, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT * FROM redsys_notifications WHERE DS_ORDER = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([$dsOrder]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function recordReceived(
        \PDO $db,
        string $dsOrder,
        ?int $idpag,
        mixed $amount,
        string $responseCode,
        bool $signatureValid,
        ?array $rawPayload = null,
        string $status = 'RECEIVED'
    ): array {
        try {
            $db->prepare(
                'INSERT INTO redsys_notifications (
                    DS_ORDER, IDPAG, IMPORT, RESPONSE_CODE, STATUS, RAW_PAYLOAD_JSON, SIGNATURE_VALID
                ) VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $dsOrder,
                $idpag,
                number_format((float) $amount, 2, '.', ''),
                $responseCode,
                $status,
                $this->encodeRawPayload($rawPayload),
                $signatureValid ? 1 : 0,
            ]);

            return [
                'duplicate' => false,
                'ds_order' => $dsOrder,
                'status' => $status,
            ];
        } catch (\PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                return [
                    'duplicate' => true,
                    'ds_order' => $dsOrder,
                    'status' => 'DUPLICATE',
                ];
            }

            throw $exception;
        }
    }

    private function encodeRawPayload(?array $payload): ?string
    {
        if ($payload === null) {
            return null;
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);

        if ($json === false) {
            throw new \RuntimeException('Could not encode Redsys raw payload.');
        }

        return $json;
    }
}
