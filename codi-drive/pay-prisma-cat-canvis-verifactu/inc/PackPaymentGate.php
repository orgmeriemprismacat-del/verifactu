<?php

declare(strict_types=1);

/**
 * Server-authoritative checkout guard for UC-015 packs.
 *
 * New pack enrollments carry a minimal commercial snapshot in OBSERVACIONS.
 * This gate rebuilds the checkout exclusively from legacy DB rows and rejects
 * mixed/incomplete snapshots instead of trusting browser prices or ordering.
 */
final class PackPaymentGate
{
    public static function assertCanPrepare(mysqli $db, array $post): array
    {
        $idpagRaw = trim((string) ($post['idPag'] ?? ''));
        if ($idpagRaw === '' || !ctype_digit($idpagRaw) || (int) $idpagRaw <= 0) {
            throw new RuntimeException('PACK_PAYMENT_NOT_AVAILABLE');
        }
        $idpag = (int) $idpagRaw;

        $stmt = $db->prepare(
            "SELECT i.ID, i.IDPAG, i.ANY, i.MES, i.CURS, i.NOM, i.COGNOMS, i.DNI,
                    i.CORREU, i.ADRECA, i.Codi_Postal, i.Poblacio, i.A_PAGAR,
                    i.PAGAMENT, i.FRACCIONAT, i.OBSERVACIONS, c.NOM_CURS
             FROM inscripcions i
             INNER JOIN curs c ON c.ANY=i.ANY AND c.MES=i.MES AND c.CURS=i.CURS
             WHERE i.IDPAG=? AND i.TIPUS_INSC='P'
               AND i.`INSC CURS` IN ('0','1','M')"
        );
        if (!$stmt) {
            throw new RuntimeException('PACK_PAYMENT_NOT_AVAILABLE');
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
            $rowAdreca,
            $rowCp,
            $rowPoblacio,
            $rowAPagar,
            $rowPagament,
            $rowFraccionat,
            $rowObservacions,
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
                'ADRECA' => $rowAdreca,
                'Codi_Postal' => $rowCp,
                'Poblacio' => $rowPoblacio,
                'A_PAGAR' => $rowAPagar,
                'PAGAMENT' => $rowPagament,
                'FRACCIONAT' => $rowFraccionat,
                'OBSERVACIONS' => $rowObservacions,
                'NOM_CURS' => $rowNomCurs,
            ];
        }
        $stmt->close();

        if (count($rows) < 2) {
            throw new RuntimeException('PACK_PAYMENT_NOT_AVAILABLE');
        }

        $checkout = self::authorizeRows($rows, $post, $idpag);

        $stmtPack = $db->prepare(
            'SELECT TITOL, CODI FROM info_pack WHERE ID_PACK=? AND ESTAT=1'
        );
        if (!$stmtPack) {
            throw new RuntimeException('PACK_PAYMENT_NOT_AVAILABLE');
        }
        $packId = (int) $checkout['pack_id'];
        $stmtPack->bind_param('d', $packId);
        $stmtPack->execute();
        $stmtPack->bind_result($packTitle, $packCode);
        if (!$stmtPack->fetch() || trim((string) $packTitle) === '') {
            $stmtPack->close();
            throw new RuntimeException('PACK_PAYMENT_NOT_AVAILABLE');
        }
        $stmtPack->close();

        $checkout['pack_title'] = (string) $packTitle;
        $checkout['pack_code'] = (string) $packCode;
        $checkout['snapshot']['pack']['TITOL'] = (string) $packTitle;
        $checkout['snapshot']['pack']['CODI'] = (string) $packCode;

        return $checkout;
    }

    /** Pure-ish policy over DB-fetched rows for deterministic tests. */
    public static function authorizeRows(array $rows, array $post, int $idpag): array
    {
        $items = [];
        $packId = null;
        $packTitle = null;
        $billing = null;
        $totalCents = 0;
        $paidCents = 0;
        $fraccionat = false;
        $ordinals = [];

        foreach ($rows as $row) {
            $meta = self::metadata((string) ($row['OBSERVACIONS'] ?? ''));
            foreach (['PACK', 'PACK_ORDINAL', 'PACK_BASE', 'PACK_DISCOUNT', 'PACK_DISCOUNT_PCT', 'PACK_TOTAL'] as $key) {
                if (!array_key_exists($key, $meta)) {
                    throw new RuntimeException('PACK_SNAPSHOT_NOT_READY');
                }
            }

            $rowPackId = self::positiveInt($meta['PACK']);
            if ($packId === null) {
                $packId = $rowPackId;
            } elseif ($packId !== $rowPackId) {
                throw new RuntimeException('PACK_PAYMENT_NOT_AVAILABLE');
            }

            $ordinal = self::positiveInt($meta['PACK_ORDINAL']);
            if (isset($ordinals[$ordinal])) {
                throw new RuntimeException('PACK_PAYMENT_NOT_AVAILABLE');
            }
            $ordinals[$ordinal] = true;

            $base = self::cents($meta['PACK_BASE']);
            $discount = self::cents($meta['PACK_DISCOUNT']);
            $total = self::cents($meta['PACK_TOTAL']);
            $discountPct = self::amount(self::cents($meta['PACK_DISCOUNT_PCT']));
            if ($base - $discount !== $total) {
                throw new RuntimeException('PACK_PAYMENT_NOT_AVAILABLE');
            }

            $dbTotal = self::cents((string) ($row['A_PAGAR'] ?? ''));
            if ($dbTotal !== $total) {
                throw new RuntimeException('PACK_PAYMENT_NOT_AVAILABLE');
            }

            $rowIdpag = (int) ($row['IDPAG'] ?? 0);
            if ($rowIdpag !== $idpag) {
                throw new RuntimeException('PACK_PAYMENT_NOT_AVAILABLE');
            }

            $paid = self::cents((string) ($row['PAGAMENT'] ?? '0.00'));
            if ($paid < 0 || $paid > $total) {
                throw new RuntimeException('PACK_PAYMENT_NOT_AVAILABLE');
            }

            $currentBilling = [
                'name' => trim((string) ($row['NOM'] ?? '') . ' ' . (string) ($row['COGNOMS'] ?? '')),
                'nif' => trim((string) ($row['DNI'] ?? '')),
                'address' => trim((string) ($row['ADRECA'] ?? '')),
                'cp' => trim((string) ($row['Codi_Postal'] ?? '')),
                'city' => trim((string) ($row['Poblacio'] ?? '')),
                'email' => trim((string) ($row['CORREU'] ?? '')),
                'country' => 'ES',
            ];
            if ($currentBilling['name'] === '' || $currentBilling['nif'] === '') {
                throw new RuntimeException('PACK_PAYMENT_NOT_AVAILABLE');
            }
            if ($billing === null) {
                $billing = $currentBilling;
            } elseif ($billing !== $currentBilling) {
                throw new RuntimeException('PACK_PAYMENT_NOT_AVAILABLE');
            }

            $items[] = [
                'ordinal' => $ordinal,
                'inscription' => [
                    'ID' => (int) ($row['ID'] ?? 0),
                    'IDPAG' => $idpag,
                    'ANY' => (int) ($row['ANY'] ?? 0),
                    'MES' => (string) ($row['MES'] ?? ''),
                    'CURS' => (string) ($row['CURS'] ?? ''),
                    'NOM' => (string) ($row['NOM'] ?? ''),
                    'COGNOMS' => (string) ($row['COGNOMS'] ?? ''),
                    'DNI' => (string) ($row['DNI'] ?? ''),
                    'CORREU' => (string) ($row['CORREU'] ?? ''),
                    'ADRECA' => (string) ($row['ADRECA'] ?? ''),
                    'Codi_Postal' => (string) ($row['Codi_Postal'] ?? ''),
                    'Poblacio' => (string) ($row['Poblacio'] ?? ''),
                    'A_PAGAR' => self::amount($total),
                    'TOTAL' => self::amount($total),
                    'IMPORT_BASE' => self::amount($base),
                    'DESC_IMPORT' => self::amount($discount),
                    'DESC_PCT' => $discountPct,
                    'FRACCIONAT' => (int) ($row['FRACCIONAT'] ?? 0),
                    'OBSERVACIONS' => (string) ($row['OBSERVACIONS'] ?? ''),
                ],
                'course' => [
                    'NOM_CURS' => trim((string) ($row['NOM_CURS'] ?? '')),
                ],
            ];

            if ($items[array_key_last($items)]['inscription']['ID'] <= 0
                || $items[array_key_last($items)]['course']['NOM_CURS'] === ''
            ) {
                throw new RuntimeException('PACK_PAYMENT_NOT_AVAILABLE');
            }

            $totalCents += $total;
            $paidCents += $paid;
            $fraccionat = $fraccionat || ((int) ($row['FRACCIONAT'] ?? 0) === 1);
        }

        ksort($ordinals);
        $expectedOrdinal = 1;
        foreach (array_keys($ordinals) as $ordinal) {
            if ($ordinal !== $expectedOrdinal++) {
                throw new RuntimeException('PACK_PAYMENT_NOT_AVAILABLE');
            }
        }
        usort($items, static fn (array $a, array $b): int => $a['ordinal'] <=> $b['ordinal']);

        // UC-015 current SIF contract issues the complete pack from one external CHARGE.
        // Historical/exceptional partial packs need the separate intranet reconciliation flow.
        if ($paidCents !== 0) {
            throw new RuntimeException('PACK_PARTIAL_REQUIRES_RECONCILIATION');
        }

        $pendingCents = $totalCents - $paidCents;
        if ($pendingCents <= 0) {
            throw new RuntimeException('PACK_PAYMENT_NOT_AVAILABLE');
        }

        $requestedRaw = trim(str_replace(',', '.', (string) ($post['importPagare'] ?? '')));
        $requestedCents = self::cents($requestedRaw);
        if ($requestedCents <= 0 || $requestedCents !== $pendingCents) {
            throw new RuntimeException('PACK_PAYMENT_NOT_AVAILABLE');
        }

        return [
            'idpag' => $idpag,
            'pack_id' => $packId,
            'source_type' => 'PACK',
            'source_id' => (string) $packId,
            'total_amount' => self::amount($totalCents),
            'already_paid_amount' => self::amount($paidCents),
            'pending_amount' => self::amount($pendingCents),
            'payment_amount' => self::amount($requestedCents),
            'fraccionat' => false,
            'snapshot' => [
                'pack' => ['ID_PACK' => $packId, 'TITOL' => 'Pack ' . $packId],
                'billing' => $billing,
                'items' => $items,
                'payment' => [
                    'idpag' => $idpag,
                    'amount' => self::amount($requestedCents),
                ],
            ],
        ];
    }

    private static function metadata(string $observations): array
    {
        $result = [];
        foreach (preg_split('/\s+/', trim($observations)) ?: [] as $token) {
            $parts = explode('|', $token, 2);
            if (count($parts) === 2) {
                $result[strtoupper(trim($parts[0]))] = trim($parts[1]);
            }
        }
        return $result;
    }

    private static function positiveInt(mixed $value): int
    {
        $raw = trim((string) $value);
        if ($raw === '' || !ctype_digit($raw) || (int) $raw <= 0) {
            throw new RuntimeException('PACK_PAYMENT_NOT_AVAILABLE');
        }
        return (int) $raw;
    }

    private static function cents(string $value): int
    {
        $value = trim(str_replace(',', '.', $value));
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $value)) {
            throw new RuntimeException('PACK_PAYMENT_NOT_AVAILABLE');
        }
        [$euros, $decimal] = array_pad(explode('.', $value, 2), 2, '');
        return (int) $euros * 100 + (int) str_pad($decimal, 2, '0');
    }

    private static function amount(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
