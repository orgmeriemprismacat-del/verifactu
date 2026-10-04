<?php

final class Uc002LegacyInvoiceSelectorResolver
{
    public function resolve(string $legacyInvoiceNumber): array
    {
        $legacyInvoiceNumber = trim($legacyInvoiceNumber);
        if ($legacyInvoiceNumber === '' || strlen($legacyInvoiceNumber) > 30) {
            throw new RuntimeException('Número de factura llegada no vàlid', 422);
        }

        $connection = new ConnexioWeb();
        $connection->connectarBD();
        $db = $connection->connexio;

        if (!($db instanceof mysqli)) {
            $connection->desconectarBD();
            throw new RuntimeException('Connexió legacy UC-002 no disponible', 500);
        }

        try {
            $stmt = $db->prepare(
                'SELECT DISTINCT FACTURA_RELACIONADA
                 FROM factures
                 WHERE NUM = ?
                   AND FACTURA_RELACIONADA IS NOT NULL
                 LIMIT 2'
            );
            if (!$stmt) {
                throw new RuntimeException(
                    'No es pot preparar la resolució de factura UC-002',
                    500
                );
            }

            $stmt->bind_param('s', $legacyInvoiceNumber);
            $stmt->execute();
            $stmt->bind_result($facturaRelacionada);

            $matches = [];
            while ($stmt->fetch()) {
                $value = (int) $facturaRelacionada;
                if ($value > 0) {
                    $matches[$value] = true;
                }
            }
            $stmt->close();

            $ids = array_keys($matches);
            if (count($ids) === 0) {
                throw new RuntimeException(
                    'La factura llegada no té relació SIF identificable',
                    409
                );
            }
            if (count($ids) !== 1) {
                throw new RuntimeException(
                    'La factura llegada té més d’una relació possible',
                    409
                );
            }

            return [
                'legacy_factura_relacionada' => (int) $ids[0],
                'legacy_invoice_number' => $legacyInvoiceNumber,
            ];
        } finally {
            $connection->desconectarBD();
        }
    }
}
