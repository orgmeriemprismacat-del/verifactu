<?php

declare(strict_types=1);

/**
 * Legacy gateway-side guard for UC-111.
 *
 * Call BEFORE building the Redsys form, including for direct POSTs to the
 * checkout page. Reads the enrollment/novice decision from the database:
 * browser-supplied course codes, prices or validation flags never authorize
 * payment. This guard is NOT the full signed SIF payment-intent service.
 */
final class JasomNovicePaymentGate
{
    public static function assertCanPrepare(mysqli $db, array $post): array
    {
        $idpag = trim((string) ($post['idPag'] ?? ''));
        if ($idpag === '' || !ctype_digit($idpag) || (int) $idpag < 1) {
            throw new RuntimeException('PAYMENT_NOT_AVAILABLE');
        }

        $stmt = $db->prepare(
            "SELECT i.ID, i.CURS, i.A_PAGAR, i.PAGAMENT, r.VALIDAT
             FROM inscripcions i
             LEFT JOIN recent_titulat r ON r.ID_INSC = i.ID
             WHERE i.IDPAG = ?
               AND i." . self::activeEnrollmentPredicate() . "
             LIMIT 2"
        );
        $stmt->bind_param('s', $idpag);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows !== 1) {
            $stmt->close();
            throw new RuntimeException('PAYMENT_NOT_AVAILABLE');
        }

        $stmt->bind_result($enrollmentId, $courseCode, $coursePrice, $alreadyPaid, $noviceDecision);
        $stmt->fetch();
        $stmt->close();

        if ((string) $courseCode === 'JASOM' && $noviceDecision !== null) {
            // 0 = waiting for the secretary; 1 = approved; 2 = denied.
            // A null/unknown value on an existing row is NOT a denial.
            if (!in_array((string) $noviceDecision, ['1', '2'], true)) {
                throw new RuntimeException('NOVICE_REVIEW_PENDING');
            }
        }

        $total = self::cents((string) $coursePrice);
        $paid = self::cents((string) ($alreadyPaid ?? '0.00'));
        $requestedRaw = trim(str_replace(',', '.', (string) ($post['importPagare'] ?? '')));
        $requested = self::cents($requestedRaw);

        if ($total <= 0 || $paid < 0 || $paid >= $total || $requested <= 0
            || $requested > $total - $paid
        ) {
            throw new RuntimeException('PAYMENT_NOT_AVAILABLE');
        }

        // Prevent a tampered enrollment ID/course code in the checkout POST
        // from being used to set a different payment callback destination.
        if (isset($post['codiCurs']) && (string) $post['codiCurs'] !== (string) $courseCode) {
            throw new RuntimeException('PAYMENT_NOT_AVAILABLE');
        }

        return [
            'idpag' => $idpag,
            'enrollment_id' => (int) $enrollmentId,
            'course_code' => (string) $courseCode,
            'total_amount' => self::amount($total),
            'already_paid_amount' => self::amount($paid),
            'payment_amount' => self::amount($requested),
            'novice_decision' => $noviceDecision === null ? null : (int) $noviceDecision,
        ];
    }

    private static function activeEnrollmentPredicate(): string
    {
        return "(\`INSC CURS\` = '0' OR \`INSC CURS\` = '1' OR \`INSC CURS\` = 'M')";
    }

    private static function cents(string $value): int
    {
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $value)) {
            throw new RuntimeException('PAYMENT_NOT_AVAILABLE');
        }

        [$euros, $decimal] = array_pad(explode('.', $value, 2), 2, '');
        return (int) $euros * 100 + (int) str_pad($decimal, 2, '0');
    }

    private static function amount(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
