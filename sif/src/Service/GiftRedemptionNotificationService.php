<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\NotificationOutboxRepository;

/**
 * Persists the logical UC-018 notifications after the gift has been consumed
 * and legacy usage reconciled.
 *
 * The outbox stores no raw email address, DNI, name or gift code. A future
 * delivery worker resolves the rendered message from the committed enrollment.
 */
final class GiftRedemptionNotificationService
{
    private const TEMPLATE_VERSION = 'v1';

    private const INTERNAL_RECIPIENTS = [
        'GIFT_REDEMPTION_ADMIN_DETAIL' => [
            'recipient_type' => 'INTERNAL',
            'address' => 'inscripcions@prisma.cat',
            'resolution' => 'FIXED_INSCRIPCIONS_PRISMA',
        ],
        'GIFT_REDEMPTION_ADMIN_CONFIRMATION' => [
            'recipient_type' => 'INTERNAL',
            'address' => 'inscripcions@prisma.cat',
            'resolution' => 'FIXED_INSCRIPCIONS_PRISMA',
        ],
        'GIFT_REDEMPTION_ARCHIVE_GMAIL' => [
            'recipient_type' => 'INTERNAL',
            'address' => 'inscripcions.prisma@gmail.com',
            'resolution' => 'FIXED_INSCRIPCIONS_GMAIL',
        ],
        'GIFT_REDEMPTION_ARCHIVE_SECRETARIA' => [
            'recipient_type' => 'INTERNAL',
            'address' => 'resguard.secretaria@prisma.cat',
            'resolution' => 'FIXED_SECRETARIA_ARCHIVE',
        ],
    ];

    public function __construct(private NotificationOutboxRepository $outbox)
    {
    }

    public function enqueue(
        \PDO $sifDb,
        \PDO $legacyDb,
        int $enrollmentId,
        string $giftCode,
        string $uuidEntitlement,
        string $uuidOperation
    ): array {
        if ($sifDb->inTransaction()) {
            throw new \LogicException(
                'Gift redemption notification enqueue owns its SIF transaction'
            );
        }

        $giftCode = trim($giftCode);
        $uuidEntitlement = trim($uuidEntitlement);
        $uuidOperation = trim($uuidOperation);

        if ($enrollmentId <= 0
            || $giftCode === ''
            || strlen($giftCode) > 200
            || $uuidEntitlement === ''
            || strlen($uuidEntitlement) > 36
            || $uuidOperation === ''
            || strlen($uuidOperation) > 36
        ) {
            throw SifException::validation(
                'Invalid gift redemption notification identity'
            );
        }

        $enrollment = $this->one(
            $legacyDb,
            'SELECT ID, CORREU, CURS, ANY, MES, pag_observacions
             FROM inscripcions
             WHERE ID = ?',
            [$enrollmentId]
        );
        if ($enrollment === null
            || (int) ($enrollment['ID'] ?? 0) !== $enrollmentId
            || trim((string) ($enrollment['pag_observacions'] ?? ''))
                !== $giftCode
        ) {
            throw SifException::conflict(
                'Gift notification enrollment does not match reconciled gift'
            );
        }

        $email = strtolower(trim((string) ($enrollment['CORREU'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw SifException::validation(
                'Gift redemption notification recipient is invalid'
            );
        }

        $gift = $this->one(
            $legacyDb,
            'SELECT ID, CODI, USAT
             FROM regal
             WHERE CODI = ?',
            [$giftCode]
        );
        if ($gift === null
            || trim((string) ($gift['CODI'] ?? '')) !== $giftCode
            || (int) ($gift['USAT'] ?? 0) !== $enrollmentId
        ) {
            throw SifException::conflict(
                'Gift notification requires completed legacy reconciliation'
            );
        }

        $origin = $this->one(
            $sifDb,
            'SELECT ce.UUID_ENTITLEMENT, ce.CONSUMED_UUID_OPERATION,
                    co.UUID_FACTURA, co.UUID_PAYMENT
             FROM commercial_entitlement ce
             INNER JOIN commercial_operation co
                ON co.UUID_OPERATION = ce.ORIGIN_UUID_OPERATION
             WHERE ce.UUID_ENTITLEMENT = ?',
            [$uuidEntitlement]
        );
        if ($origin === null
            || (string) ($origin['CONSUMED_UUID_OPERATION'] ?? '') !== $uuidOperation
            || trim((string) ($origin['UUID_FACTURA'] ?? '')) === ''
            || trim((string) ($origin['UUID_PAYMENT'] ?? '')) === ''
        ) {
            throw SifException::conflict(
                'Gift notification lacks reconciled entitlement origin'
            );
        }

        $courseCode = strtoupper(trim((string) ($enrollment['CURS'] ?? '')));
        $year = trim((string) ($enrollment['ANY'] ?? ''));
        $month = trim((string) ($enrollment['MES'] ?? ''));
        if ($courseCode === '' || $year === '' || $month === '') {
            throw SifException::validation(
                'Gift redemption notification course context is incomplete'
            );
        }

        $stableCorrelation = 'UC018|NOTIFY|INSC:' . $enrollmentId
            . '|ENT:' . substr(hash('sha256', $uuidEntitlement), 0, 24);
        $commonPayload = [
            'source_type' => 'GIFT_REDEMPTION',
            'enrollment_id' => $enrollmentId,
            'legacy_gift_id' => (int) $gift['ID'],
            'gift_code_hash' => hash('sha256', $giftCode),
            'uuid_entitlement' => $uuidEntitlement,
            'uuid_operation' => $uuidOperation,
            'course_code' => $courseCode,
            'course_edition' => $year . '/' . $month,
        ];

        $messages = [];
        foreach (self::INTERNAL_RECIPIENTS as $template => $recipient) {
            $messages[] = [
                'template_code' => $template,
                'recipient_type' => $recipient['recipient_type'],
                'recipient_hash' => hash(
                    'sha256',
                    strtolower((string) $recipient['address'])
                ),
                'recipient_resolution' => $recipient['resolution'],
            ];
        }
        $messages[] = [
            'template_code' => 'GIFT_REDEMPTION_STUDENT_CONFIRMATION',
            'recipient_type' => 'ALUMNE',
            'recipient_hash' => hash('sha256', $email),
            'recipient_resolution' => 'LEGACY_ENROLLMENT_EMAIL',
        ];

        $sifDb->beginTransaction();
        try {
            $results = [];
            foreach ($messages as $message) {
                $templateCode = (string) $message['template_code'];
                $results[$templateCode] = $this->outbox->enqueue($sifDb, [
                    'idempotency_key' =>
                        'NOTIFY|UC018|INSC:' . $enrollmentId
                        . '|TEMPLATE:' . $templateCode,
                    'template_code' => $templateCode,
                    'template_version' => self::TEMPLATE_VERSION,
                    'recipient_type' => $message['recipient_type'],
                    'recipient_hash' => $message['recipient_hash'],
                    'uuid_factura' => (string) $origin['UUID_FACTURA'],
                    'uuid_payment' => (string) $origin['UUID_PAYMENT'],
                    'correlation_id' => $stableCorrelation,
                    'payload' => $commonPayload + [
                        'recipient_resolution' =>
                            $message['recipient_resolution'],
                    ],
                ]);
            }

            $sifDb->commit();

            return [
                'status' => 'QUEUED',
                'count' => count($results),
                'notifications' => $results,
            ];
        } catch (\Throwable $exception) {
            if ($sifDb->inTransaction()) {
                $sifDb->rollBack();
            }
            throw $exception;
        }
    }

    private function one(\PDO $db, string $sql, array $params): ?array
    {
        $statement = $db->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }
}
