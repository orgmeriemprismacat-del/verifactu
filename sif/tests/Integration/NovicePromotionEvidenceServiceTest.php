<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\NovicePromotionEvidenceFilePolicy;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Service\FilesystemNovicePromotionPrivateEvidenceStorage;
use Prisma\Sif\Service\NovicePromotionEvidenceService;
use Prisma\Sif\Service\NovicePromotionPrivateEvidenceStorageInterface;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class NovicePromotionEvidenceServiceTest
{
    public function testStoresEvidenceOutsideWebrootAndPersistsVerifiedMetadata(): void
    {
        [$db, $uuidValidation] = $this->fixture();
        [$root, $source] = $this->files('qualification.pdf', '%PDF-1.4 synthetic evidence');

        try {
            $service = new NovicePromotionEvidenceService(
                new UuidGenerator(),
                new NovicePromotionEvidenceFilePolicy(1024 * 1024, ['application/pdf']),
                new FilesystemNovicePromotionPrivateEvidenceStorage($root)
            );

            $result = $service->store(
                $db,
                $uuidValidation,
                $source,
                'my-degree-certificate.pdf',
                'application/pdf',
                'student:canonical:12345678Z',
                'NOVICE-EVIDENCE|10|1',
                'NOVICE_ACADEMIC_QUALIFICATION',
                'NOVICE_REVIEW_3_MONTHS'
            );

            Assert::same('READY', $result['storage_status']);
            Assert::same(false, $result['idempotency_reused']);
            Assert::same(hash_file('sha256', $source), $result['content_hash']);

            $row = $db->query('SELECT * FROM discount_evidence')->fetch(\PDO::FETCH_ASSOC);
            Assert::same('READY', (string) $row['STORAGE_STATUS']);
            Assert::same('RESTRICTED', (string) $row['ACCESS_CLASSIFICATION']);
            Assert::same('ACADEMIC_QUALIFICATION', (string) $row['EVIDENCE_TYPE']);
            Assert::same(hash('sha256', 'my-degree-certificate.pdf'), (string) $row['ORIGINAL_NAME_HASH']);
            Assert::same(false, str_contains((string) $row['STORAGE_REF'], 'my-degree-certificate'));
            Assert::same(
                true,
                is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $row['STORAGE_REF']))
            );
        } finally {
            $this->cleanup($root);
        }
    }

    public function testRetryWithSameIdempotencyKeyReusesSingleReadyEvidence(): void
    {
        [$db, $uuidValidation] = $this->fixture();
        [$root, $source] = $this->files('qualification.pdf', '%PDF-1.4 same bytes');

        try {
            $service = new NovicePromotionEvidenceService(
                new UuidGenerator(),
                new NovicePromotionEvidenceFilePolicy(1024 * 1024, ['application/pdf']),
                new FilesystemNovicePromotionPrivateEvidenceStorage($root)
            );

            $first = $service->store(
                $db,
                $uuidValidation,
                $source,
                'first-name.pdf',
                'application/pdf',
                'student:canonical:12345678Z',
                'NOVICE-EVIDENCE|10|retry',
                'NOVICE_ACADEMIC_QUALIFICATION',
                'NOVICE_REVIEW_3_MONTHS'
            );
            $second = $service->store(
                $db,
                $uuidValidation,
                $source,
                'renamed-local-file.pdf',
                'application/pdf',
                'student:canonical:12345678Z',
                'NOVICE-EVIDENCE|10|retry',
                'NOVICE_ACADEMIC_QUALIFICATION',
                'NOVICE_REVIEW_3_MONTHS'
            );

            Assert::same(false, $first['idempotency_reused']);
            Assert::same(true, $second['idempotency_reused']);
            Assert::same($first['uuid_evidence'], $second['uuid_evidence']);
            Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM discount_evidence')->fetchColumn());
        } finally {
            $this->cleanup($root);
        }
    }

    public function testStorageFailureMarksPreparedEvidenceFailedWithoutClaimingCustody(): void
    {
        [$db, $uuidValidation] = $this->fixture();
        [$root, $source] = $this->files('qualification.pdf', '%PDF-1.4 failing storage');

        $storage = new class implements NovicePromotionPrivateEvidenceStorageInterface {
            public function putPrivate(
                string $sourceLocalPath,
                string $storageRef,
                string $mimeType,
                string $expectedSha256,
                int $expectedSizeBytes
            ): void {
                throw new \RuntimeException('synthetic storage outage');
            }

            public function deletePrivate(string $storageRef): void
            {
            }
        };

        try {
            $service = new NovicePromotionEvidenceService(
                new UuidGenerator(),
                new NovicePromotionEvidenceFilePolicy(1024 * 1024, ['application/pdf']),
                $storage
            );

            Assert::throws(\RuntimeException::class, static function () use ($service, $db, $uuidValidation, $source): void {
                $service->store(
                    $db,
                    $uuidValidation,
                    $source,
                    'qualification.pdf',
                    'application/pdf',
                    'student:canonical:12345678Z',
                    'NOVICE-EVIDENCE|10|failure',
                    'NOVICE_ACADEMIC_QUALIFICATION',
                    'NOVICE_REVIEW_3_MONTHS'
                );
            });

            $row = $db->query('SELECT STORAGE_STATUS, STORED_AT, STORAGE_FAILED_AT, LAST_STORAGE_ERROR_CODE FROM discount_evidence')->fetch(\PDO::FETCH_ASSOC);
            Assert::same('FAILED', (string) $row['STORAGE_STATUS']);
            Assert::same(null, $row['STORED_AT']);
            Assert::same(true, $row['STORAGE_FAILED_AT'] !== null);
            Assert::same('PRIVATE_STORAGE_WRITE_FAILED', (string) $row['LAST_STORAGE_ERROR_CODE']);
        } finally {
            $this->cleanup($root);
        }
    }

    private function fixture(): array
    {
        $db = TestDatabase::fresh();
        $uuidOperation = (new UuidGenerator())->generate();
        $uuidValidation = (new UuidGenerator())->generate();

        $db->prepare(
            'INSERT INTO commercial_operation
             (UUID_OPERATION, IDEMPOTENCY_KEY, OPERATION_TYPE, SOURCE_CHANNEL,
              SOURCE_TYPE, SOURCE_ID, PRODUCT_TYPE, PRODUCT_CODE, CLASSIFICATION,
              CLASSIFICATION_REASON, STATUS, CURRENCY, GROSS_AMOUNT, DISCOUNT_AMOUNT,
              NET_AMOUNT, PRICE_SNAPSHOT_JSON, TAX_SNAPSHOT_JSON)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $uuidOperation, 'NOVICE|EVIDENCE|' . $uuidOperation, 'ENROLLMENT', 'WEB',
            'CURS', '10', 'CURS', 'JASOM', 'PENDING_VALIDATION',
            'NOVICE_REVIEW', 'PENDING_VALIDATION', 'EUR',
            '90.00', '0.00', '90.00', '{}', '{}',
        ]);

        $db->prepare(
            'INSERT INTO discount_validation
             (UUID_VALIDATION, UUID_OPERATION, DISCOUNT_TYPE, SUBJECT_PARTY_KEY,
              STATUS, RULE_VERSION, RULE_SNAPSHOT_JSON, REQUESTED_AT, IDEMPOTENCY_KEY)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $uuidValidation,
            $uuidOperation,
            'NOVICE_TEACHER',
            'student:canonical:12345678Z',
            'PENDING',
            'NOVICE_JASOM_V1',
            '{"source":"evidence-test"}',
            '2026-10-02 00:00:00',
            'NOVICE|EVIDENCE|VALIDATION|' . $uuidValidation,
        ]);

        return [$db, $uuidValidation];
    }

    private function files(string $name, string $bytes): array
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sif-novice-evidence-' . bin2hex(random_bytes(6));
        if (!mkdir($root, 0700, true) && !is_dir($root)) {
            throw new \RuntimeException('Cannot create evidence test directory.');
        }

        $source = $root . DIRECTORY_SEPARATOR . $name;
        file_put_contents($source, $bytes);

        return [$root, $source];
    }

    private function cleanup(string $root): void
    {
        if (!is_dir($root)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($root);
    }
}
