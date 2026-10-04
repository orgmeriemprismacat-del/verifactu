<?php

declare(strict_types=1);

/**
 * Server-authoritative checkout guard for UC-016 group payments.
 *
 * The browser may carry display values, but the payable amount, fiscal
 * responsible and participant list are rebuilt from legacy DB rows.
 * The automatic SIF path currently accepts only a full, previously-unpaid
 * group payment so InvoiceService cannot misclassify a partial group invoice.
 */
final class GroupPaymentGate
{
    public static function assertCanPrepare(mysqli $db, array $post): array
    {
        $idpagRaw = trim((string) ($post['idPag'] ?? ''));
        if ($idpagRaw === '' || !ctype_digit($idpagRaw) || (int) $idpagRaw <= 0) {
            throw new RuntimeException('GROUP_PAYMENT_NOT_AVAILABLE');
        }
        $idpag = (int) $idpagRaw;

        $stmtResponsible = $db->prepare(
            'SELECT NOM, COGNOMS, DNI, CORREU, ADRECA, Codi_Postal, Poblacio
             FROM respGrups
             WHERE IDPAG=?'
        );
        if (!$stmtResponsible) {
            throw new RuntimeException('GROUP_PAYMENT_NOT_AVAILABLE');
        }
        $stmtResponsible->bind_param('d', $idpag);
        $stmtResponsible->execute();
        $stmtResponsible->store_result();
        if ($stmtResponsible->num_rows !== 1) {
            $stmtResponsible->close();
            throw new RuntimeException('GROUP_PAYMENT_NOT_AVAILABLE');
        }
        $stmtResponsible->bind_result(
            $respNom,
            $respCognoms,
            $respDni,
            $respCorreu,
            $respAdreca,
            $respCp,
            $respPoblacio
        );
        $stmtResponsible->fetch();
        $stmtResponsible->close();

        $responsible = [
            'NOM' => $respNom,
            'COGNOMS' => $respCognoms,
            'DNI' => $respDni,
            'CORREU' => $respCorreu,
            'ADRECA' => $respAdreca,
            'Codi_Postal' => $respCp,
            'Poblacio' => $respPoblacio,
        ];

        $stmt = $db->prepare(
            "SELECT i.ID, i.IDPAG, i.ANY, i.MES, i.CURS, i.NOM, i.COGNOMS, i.DNI,
                    i.CORREU, i.A_PAGAR, i.PAGAMENT, i.FRACCIONAT, i.FACTURA_RELACIONADA,
                    c.NOM_CURS
             FROM inscripcions i
             INNER JOIN curs c ON c.ANY=i.ANY AND c.MES=i.MES AND c.CURS=i.CURS
             WHERE i.IDPAG=? AND i.TIPUS_INSC='G'
               AND i.`INSC CURS` IN ('0','1','M')
             ORDER BY i.ID"
        );
        if (!$stmt) {
            throw new RuntimeException('GROUP_PAYMENT_NOT_AVAILABLE');
        }
        $stmt->bind_param('d', $idpag);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result(
            $rowId,
            $rowIdpag,
            $rowAny,
            $rowMes,
            $rowCurs,
            $rowNom,
            $rowCognoms,
            $rowDni,
            $rowCorreu,
            $rowAPagar,
            $rowPagament,
            $rowFraccionat,
            $rowFacturaRelacionada,
            $rowNomCurs
        );

        $rows = [];
        while ($stmt->fetch()) {
            $rows[] = [
                'ID' => $rowId,
                'IDPAG' => $rowIdpag,
                'ANY' => $rowAny,
                'MES' => $rowMes,
                'CURS' => $rowCurs,
                'NOM' => $rowNom,
                'COGNOMS' => $rowCognoms,
                'DNI' => $rowDni,
                'CORREU' => $rowCorreu,
                'A_PAGAR' => $rowAPagar,
                'PAGAMENT' => $rowPagament,
                'FRACCIONAT' => $rowFraccionat,
                'FACTURA_RELACIONADA' => $rowFacturaRelacionada,
                'NOM_CURS' => $rowNomCurs,
            ];
        }
        $stmt->close();

        return self::authorizeRows($rows, $responsible, $post, $idpag);
    }

    /** Pure policy for deterministic tests. */
    public static function authorizeRows(array $rows, array $responsible, array $post, int $idpag): array
    {
        if ($rows === []) {
            throw new RuntimeException('GROUP_PAYMENT_NOT_AVAILABLE');
        }

        $billingName = trim(
            (string) ($responsible['NOM'] ?? '')
            . ' '
            . (string) ($responsible['COGNOMS'] ?? '')
        );
        $billingNif = trim((string) ($responsible['DNI'] ?? ''));
        if ($billingName === '' || $billingNif === '') {
            throw new RuntimeException('GROUP_PAYMENT_NOT_AVAILABLE');
        }

        $items = [];
        $seen = [];
        $totalCents = 0;
        $paidCents = 0;

        foreach ($rows as $row) {
            $rowIdpag = (int) ($row['IDPAG'] ?? 0);
            $id = (int) ($row['ID'] ?? 0);
            if ($rowIdpag !== $idpag || $id <= 0 || isset($seen[$id])) {
                throw new RuntimeException('GROUP_PAYMENT_NOT_AVAILABLE');
            }
            $seen[$id] = true;

            $total = self::cents((string) ($row['A_PAGAR'] ?? ''));
            $paid = self::cents((string) ($row['PAGAMENT'] ?? '0.00'));
            if ($total <= 0 || $paid < 0 || $paid > $total) {
                throw new RuntimeException('GROUP_PAYMENT_NOT_AVAILABLE');
            }

            $courseTitle = trim((string) ($row['NOM_CURS'] ?? ''));
            $participantName = trim((string) ($row['NOM'] ?? ''));
            if ($courseTitle === '' || $participantName === '') {
                throw new RuntimeException('GROUP_PAYMENT_NOT_AVAILABLE');
            }

            $items[] = [
                'inscription' => [
                    'ID' => $id,
                    'IDPAG' => $idpag,
                    'ANY' => (int) ($row['ANY'] ?? 0),
                    'MES' => (string) ($row['MES'] ?? ''),
                    'CURS' => (string) ($row['CURS'] ?? ''),
                    'NOM' => (string) ($row['NOM'] ?? ''),
                    'COGNOMS' => (string) ($row['COGNOMS'] ?? ''),
                    'DNI' => (string) ($row['DNI'] ?? ''),
                    'CORREU' => (string) ($row['CORREU'] ?? ''),
                    'A_PAGAR' => self::amount($total),
                    'TOTAL' => self::amount($total),
                    'FACTURA_RELACIONADA' => $row['FACTURA_RELACIONADA'] ?? null,
                    'FRACCIONAT' => (int) ($row['FRACCIONAT'] ?? 0),
                ],
                'course' => [
                    'NOM_CURS' => $courseTitle,
                ],
            ];

            $totalCents += $total;
            $paidCents += $paid;
        }

        // The current invoice+initial-payment contract marks any invoice carrying
        // a payment block as PAID. Do not route historic/partial group payments
        // through it until partial settlement semantics are implemented.
        if ($paidCents !== 0) {
            throw new RuntimeException('GROUP_PARTIAL_REQUIRES_RECONCILIATION');
        }

        $pendingCents = $totalCents - $paidCents;
        if ($pendingCents <= 0) {
            throw new RuntimeException('GROUP_PAYMENT_NOT_AVAILABLE');
        }

        $requestedCents = self::cents((string) ($post['importPagare'] ?? ''));
        if ($requestedCents !== $pendingCents) {
            throw new RuntimeException('GROUP_PAYMENT_NOT_AVAILABLE');
        }

        return [
            'idpag' => $idpag,
            'source_type' => 'GRUP',
            'source_id' => (string) $idpag,
            'total_amount' => self::amount($totalCents),
            'already_paid_amount' => self::amount($paidCents),
            'pending_amount' => self::amount($pendingCents),
            'payment_amount' => self::amount($requestedCents),
            'snapshot' => [
                'responsible' => $responsible,
                'items' => $items,
                'payment' => [
                    'idpag' => $idpag,
                    'amount' => self::amount($requestedCents),
                ],
            ],
        ];
    }

    private static function cents(string $value): int
    {
        $value = trim(str_replace(',', '.', $value));
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $value)) {
            throw new RuntimeException('GROUP_PAYMENT_NOT_AVAILABLE');
        }

        [$euros, $decimal] = array_pad(explode('.', $value, 2), 2, '');
        return (int) $euros * 100 + (int) str_pad($decimal, 2, '0');
    }

    private static function amount(int $cents): string
    {
        return intdiv($cents, 100)
            . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
