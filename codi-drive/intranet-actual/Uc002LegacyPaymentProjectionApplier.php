<?php

final class Uc002LegacyPaymentProjectionApplier
{
    public function apply(
        array $projection,
        string $movementDate,
        string $uuidPayment
    ): array {
        $uuidFactura = trim((string) ($projection['uuid_factura'] ?? ''));
        $numVisible = trim((string) ($projection['num_visible'] ?? ''));
        $items = $projection['items'] ?? null;
        $uuidPayment = trim($uuidPayment);
        $movementDate = trim($movementDate);

        if ($uuidFactura === '' || $numVisible === '' || $uuidPayment === '') {
            throw new RuntimeException('Projecció UC-002 incompleta', 422);
        }
        if (!is_array($items) || $items === []) {
            throw new RuntimeException('La projecció UC-002 no conté inscripcions', 409);
        }
        if ($movementDate === '') {
            throw new RuntimeException('Data de pagament UC-002 no disponible', 422);
        }

        $connection = new ConnexioWeb();
        $connection->connectarBD();
        $db = $connection->connexio;

        if (!($db instanceof mysqli)) {
            $connection->desconectarBD();
            throw new RuntimeException('Connexió legacy UC-002 no disponible', 500);
        }

        $marker = 'SIF_PAYMENT ' . $uuidPayment;
        $updated = 0;
        $transactionStarted = false;

        try {
            $db->begin_transaction();
            $transactionStarted = true;

            foreach ($items as $item) {
                if (!is_array($item)) {
                    throw new RuntimeException('Item de projecció UC-002 no vàlid', 422);
                }

                $idInsc = (int) ($item['id_insc'] ?? 0);
                $lineTotal = $this->money($item['line_total'] ?? null, 'total línia');
                $projected = $this->money(
                    $item['projected_payment'] ?? null,
                    'pagament projectat'
                );
                $fullyPaid = ($item['fully_paid'] ?? false) === true;
                $expectedIdpag = isset($item['idpag']) && $item['idpag'] !== null
                    ? (int) $item['idpag']
                    : null;
                $expectedFactRel = isset($item['factura_relacionada'])
                    && $item['factura_relacionada'] !== null
                    ? (int) $item['factura_relacionada']
                    : null;

                if ($idInsc <= 0 || (float) $projected < 0.0) {
                    throw new RuntimeException('Identitat o import UC-002 no vàlid', 422);
                }

                $select = $db->prepare(
                    'SELECT A_PAGAR, PAGAMENT, FACTURA_RELACIONADA, IDPAG
                     FROM inscripcions
                     WHERE ID = ?
                     FOR UPDATE'
                );
                if (!$select) {
                    throw new RuntimeException('No es pot preparar la lectura UC-002', 500);
                }
                $select->bind_param('i', $idInsc);
                $select->execute();
                $select->bind_result($aPagar, $pagamentActual, $factRelActual, $idpagActual);

                if (!$select->fetch()) {
                    $select->close();
                    throw new RuntimeException(
                        'La inscripció projectada no existeix al llegat',
                        409
                    );
                }
                $select->close();

                if ($this->cents($aPagar) !== $this->cents($lineTotal)) {
                    throw new RuntimeException(
                        'A_PAGAR no coincideix amb la línia fiscal SIF',
                        409
                    );
                }

                if ($expectedIdpag !== null
                    && (int) $idpagActual > 0
                    && (int) $idpagActual !== $expectedIdpag
                ) {
                    throw new RuntimeException('IDPAG llegat divergent del SIF', 409);
                }

                if ($expectedFactRel !== null
                    && $factRelActual !== null
                    && $factRelActual !== ''
                    && (int) $factRelActual !== $expectedFactRel
                ) {
                    throw new RuntimeException(
                        'FACTURA_RELACIONADA llegada divergent del SIF',
                        409
                    );
                }

                $update = $db->prepare(
                    "UPDATE inscripcions
                     SET PAGAMENT = ?,
                         `DATA PAG` = CASE
                             WHEN ? = 1 THEN COALESCE(NULLIF(`DATA PAG`, ''), ?)
                             ELSE `DATA PAG`
                         END,
                         FRACCIONAT = CASE
                             WHEN ? > 0 AND ? < A_PAGAR THEN 1
                             ELSE FRACCIONAT
                         END,
                         FACTURA_RELACIONADA = COALESCE(FACTURA_RELACIONADA, ?),
                         OBSERVACIONS = CASE
                             WHEN LOCATE(?, COALESCE(OBSERVACIONS, '')) > 0
                                 THEN OBSERVACIONS
                             ELSE CONCAT(
                                 COALESCE(OBSERVACIONS, ''),
                                 CASE
                                     WHEN COALESCE(OBSERVACIONS, '') = '' THEN ''
                                     ELSE '\\n'
                                 END,
                                 ?, ' ', ?
                             )
                         END
                     WHERE ID = ?"
                );
                if (!$update) {
                    throw new RuntimeException(
                        'No es pot preparar la sincronització UC-002',
                        500
                    );
                }

                $fullyPaidInt = $fullyPaid ? 1 : 0;
                $projectedFloat = (float) $projected;
                $factRelToWrite = $expectedFactRel;
                $update->bind_param(
                    'sisddisssi',
                    $projected,
                    $fullyPaidInt,
                    $movementDate,
                    $projectedFloat,
                    $projectedFloat,
                    $factRelToWrite,
                    $marker,
                    $marker,
                    $numVisible,
                    $idInsc
                );
                $update->execute();
                if ($update->errno !== 0) {
                    $error = $update->error;
                    $update->close();
                    throw new RuntimeException(
                        'Error actualitzant la projecció UC-002: ' . $error,
                        500
                    );
                }
                $updated += max(0, $update->affected_rows);
                $update->close();
            }

            $db->commit();
            $transactionStarted = false;

            return [
                'status' => 'SYNCED',
                'uuid_factura' => $uuidFactura,
                'num_visible' => $numVisible,
                'uuid_payment' => $uuidPayment,
                'items' => count($items),
                'updated_rows' => $updated,
            ];
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                try {
                    $db->rollback();
                } catch (Throwable) {
                }
            }
            throw $exception;
        } finally {
            $connection->desconectarBD();
        }
    }

    private function money(mixed $value, string $label): string
    {
        $raw = trim(str_replace(',', '.', (string) $value));
        if (!preg_match('/^\\d{1,10}(?:\\.\\d{1,2})?$/D', $raw)) {
            throw new RuntimeException('Import UC-002 no vàlid: ' . $label, 422);
        }

        return number_format((float) $raw, 2, '.', '');
    }

    private function cents(mixed $value): int
    {
        $money = $this->money($value, 'money');
        [$euros, $decimals] = array_pad(explode('.', $money, 2), 2, '');

        return (int) $euros * 100 + (int) str_pad($decimals, 2, '0');
    }
}
