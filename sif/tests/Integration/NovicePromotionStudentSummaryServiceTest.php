<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Service\NovicePromotionStudentSummaryService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class NovicePromotionStudentSummaryServiceTest
{
    public function testStudentSummaryShowsGrantedAppliedAndAvailableWithoutExposingCode(): void
    {
        $db = TestDatabase::fresh();
        $uuidOperation = (new UuidGenerator())->generate();
        $uuidValidation = (new UuidGenerator())->generate();
        $uuidEntitlement = (new UuidGenerator())->generate();
        $uuidOriginInvoice = (new UuidGenerator())->generate();
        $uuidApplication = (new UuidGenerator())->generate();
        $uuidDestination = (new UuidGenerator())->generate();
        $uuidInvoice = (new UuidGenerator())->generate();

        $db->prepare(
            "INSERT INTO commercial_operation
             (UUID_OPERATION, IDEMPOTENCY_KEY, OPERATION_TYPE, SOURCE_CHANNEL,
              SOURCE_TYPE, SOURCE_ID, PRODUCT_TYPE, PRODUCT_CODE, PRODUCT_EDITION,
              CLASSIFICATION, CLASSIFICATION_REASON, STATUS, CURRENCY,
              GROSS_AMOUNT, DISCOUNT_AMOUNT, NET_AMOUNT, PRICE_SNAPSHOT_JSON, TAX_SNAPSHOT_JSON)
             VALUES (?, ?, 'ENROLLMENT', 'WEB', 'CURS', '7001', 'CURS', 'JASOM', '2026/09',
                     'BILLABLE', 'NOVICE_DECIDED', 'PAID', 'EUR',
                     '90.00', '0.00', '90.00', '{}', '{}')"
        )->execute([$uuidOperation, 'TEST|SUMMARY|ORIGIN']);

        $db->prepare(
            "INSERT INTO commercial_operation_party
             (UUID_OPERATION, PARTY_KEY, PARTY_ROLE, NIF_CIF, NOM_RAO,
              PRODUCT_CODE, PRODUCT_EDITION, LINE_AMOUNT, SNAPSHOT_JSON)
             VALUES (?, 'person:summary', 'PARTICIPANT', '12345678Z', 'Persona Resum',
                     'JASOM', '2026/09', '90.00', '{}')"
        )->execute([$uuidOperation]);

        $db->prepare(
            "INSERT INTO discount_validation
             (UUID_VALIDATION, UUID_OPERATION, DISCOUNT_TYPE, SUBJECT_PARTY_KEY,
              STATUS, RULE_VERSION, RULE_SNAPSHOT_JSON, REQUESTED_AT,
              VALIDATED_AT, VALIDATED_BY, IDEMPOTENCY_KEY)
             VALUES (?, ?, 'NOVICE_TEACHER', 'person:summary', 'VALIDATED',
                     'NOVICE_JASOM_V1', '{}', '2026-09-29 10:00:00',
                     '2026-09-29 10:05:00', 'secretaria:test', 'TEST|SUMMARY|VALIDATION')"
        )->execute([$uuidValidation, $uuidOperation]);

        $db->prepare(
            "INSERT INTO commercial_entitlement
             (UUID_ENTITLEMENT, ENTITLEMENT_TYPE, HOLDER_PARTY_KEY, ORIGIN_UUID_OPERATION,
              STATUS, CURRENCY, FACE_VALUE, RULE_VERSION, RULE_SNAPSHOT_JSON,
              ISSUED_AT, EXPIRES_AT, CODE_HASH, IDEMPOTENCY_KEY)
             VALUES (?, 'FUTURE_DISCOUNT', 'person:summary', ?, 'ACTIVE', 'EUR',
                     '90.00', 'NOVICE_JASOM_V1', '{}',
                     '2026-09-29 10:10:00', '2027-09-29 10:10:00', ?, 'TEST|SUMMARY|ENTITLEMENT')"
        )->execute([$uuidEntitlement, $uuidOperation, hash('sha256', 'NOV-SECRET-NOT-EXPOSED')]);

        $db->prepare(
            "INSERT INTO factura
             (UUID_FACTURA, IDEMPOTENCY_KEY, TIPUS_SERIE, ANY_FACT, NUM_SEQ, NUM_VISIBLE,
              TIPUS_FACTURA, DATA_EMISSIO, ESTAT_COBRAMENT, ESTAT_FACTURA, ESTAT_AEAT,
              BILLING_NOM_RAO, BILLING_NIF_CIF, IMPORT_BASE, DESC_IMPORT,
              BASE_IMPOSABLE, IVA_REGIM, IVA_PCT, IVA_IMPORT, TOTAL, SOURCE_CHANNEL)
             VALUES (?, 'TEST|SUMMARY|ORIGIN_FACT', 'A', 2026, 9990, 'A-2026-9990',
                     'F1', '2026-09-29 10:09:00', 'PAID', 'ISSUED', 'PENDING',
                     'Persona Resum', '12345678Z', '90.00', '0.00',
                     '90.00', 'EXEMPT', '0.00', '0.00', '90.00', 'WEB')"
        )->execute([$uuidOriginInvoice]);

        $db->prepare(
            "INSERT INTO novice_promotion_grant
             (UUID_ENTITLEMENT, UUID_VALIDATION, ORIGIN_UUID_OPERATION, UUID_FACTURA,
              HOLDER_PARTY_KEY, ORIGINAL_CASH_AMOUNT, AVAILABLE_AMOUNT, CREATED_AT)
             VALUES (?, ?, ?, ?, 'person:summary', '90.00', '20.00', '2026-09-29 10:10:00')"
        )->execute([$uuidEntitlement, $uuidValidation, $uuidOperation, $uuidOriginInvoice]);

        $db->prepare(
            "INSERT INTO novice_promotion_code_outbox
             (UUID_ENTITLEMENT, STATUS, TOKEN_CIPHERTEXT, TOKEN_NONCE,
              TOKEN_TAG, WRAP_KEY_VERSION, SENT_AT, CREATED_AT, UPDATED_AT)
             VALUES (?, 'SENT', 'ciphertext-not-token', ?, ?, 'test-v1',
                     '2026-09-29 10:12:00', '2026-09-29 10:11:00', '2026-09-29 10:12:00')"
        )->execute([
            $uuidEntitlement,
            random_bytes(12),
            random_bytes(16),
        ]);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Service\NovicePromotionStudentSummaryService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class NovicePromotionStudentSummaryServiceTest
{
    public function testStudentSummaryShowsGrantedAppliedAndAvailableWithoutExposingCode(): void
    {
        $db = TestDatabase::fresh();
        $uuidOperation = (new UuidGenerator())->generate();
        $uuidValidation = (new UuidGenerator())->generate();
        $uuidEntitlement = (new UuidGenerator())->generate();
        $uuidOriginInvoice = (new UuidGenerator())->generate();
        $uuidApplication = (new UuidGenerator())->generate();
        $uuidDestination = (new UuidGenerator())->generate();
        $uuidInvoice = (new UuidGenerator())->generate();

        $db->prepare(
            "INSERT INTO commercial_operation
             (UUID_OPERATION, IDEMPOTENCY_KEY, OPERATION_TYPE, SOURCE_CHANNEL,
              SOURCE_TYPE, SOURCE_ID, PRODUCT_TYPE, PRODUCT_CODE, PRODUCT_EDITION,
              CLASSIFICATION, CLASSIFICATION_REASON, STATUS, CURRENCY,
              GROSS_AMOUNT, DISCOUNT_AMOUNT, NET_AMOUNT, PRICE_SNAPSHOT_JSON, TAX_SNAPSHOT_JSON)
             VALUES (?, ?, 'ENROLLMENT', 'WEB', 'CURS', '7001', 'CURS', 'JASOM', '2026/09',
                     'BILLABLE', 'NOVICE_DECIDED', 'PAID', 'EUR',
                     '90.00', '0.00', '90.00', '{}', '{}')"
        )->execute([$uuidOperation, 'TEST|SUMMARY|ORIGIN']);

        $db->prepare(
            "INSERT INTO commercial_operation_party
             (UUID_OPERATION, PARTY_KEY, PARTY_ROLE, NIF_CIF, NOM_RAO,
              PRODUCT_CODE, PRODUCT_EDITION, LINE_AMOUNT, SNAPSHOT_JSON)
             VALUES (?, 'person:summary', 'PARTICIPANT', '12345678Z', 'Persona Resum',
                     'JASOM', '2026/09', '90.00', '{}')"
        )->execute([$uuidOperation]);

        $db->prepare(
            "INSERT INTO discount_validation
             (UUID_VALIDATION, UUID_OPERATION, DISCOUNT_TYPE, SUBJECT_PARTY_KEY,
              STATUS, RULE_VERSION, RULE_SNAPSHOT_JSON, REQUESTED_AT,
              VALIDATED_AT, VALIDATED_BY, IDEMPOTENCY_KEY)
             VALUES (?, ?, 'NOVICE_TEACHER', 'person:summary', 'VALIDATED',
                     'NOVICE_JASOM_V1', '{}', '2026-09-29 10:00:00',
                     '2026-09-29 10:05:00', 'secretaria:test', 'TEST|SUMMARY|VALIDATION')"
        )->execute([$uuidValidation, $uuidOperation]);

        $db->prepare(
            "INSERT INTO commercial_entitlement
             (UUID_ENTITLEMENT, ENTITLEMENT_TYPE, HOLDER_PARTY_KEY, ORIGIN_UUID_OPERATION,
              STATUS, CURRENCY, FACE_VALUE, RULE_VERSION, RULE_SNAPSHOT_JSON,
              ISSUED_AT, EXPIRES_AT, CODE_HASH, IDEMPOTENCY_KEY)
             VALUES (?, 'FUTURE_DISCOUNT', 'person:summary', ?, 'ACTIVE', 'EUR',
                     '90.00', 'NOVICE_JASOM_V1', '{}',
                     '2026-09-29 10:10:00', '2027-09-29 10:10:00', ?, 'TEST|SUMMARY|ENTITLEMENT')"
        )->execute([$uuidEntitlement, $uuidOperation, hash('sha256', 'NOV-SECRET-NOT-EXPOSED')]);

        $db->prepare(
            "INSERT INTO factura
             (UUID_FACTURA, IDEMPOTENCY_KEY, TIPUS_SERIE, ANY_FACT, NUM_SEQ, NUM_VISIBLE,
              TIPUS_FACTURA, DATA_EMISSIO, ESTAT_COBRAMENT, ESTAT_FACTURA, ESTAT_AEAT,
              BILLING_NOM_RAO, BILLING_NIF_CIF, IMPORT_BASE, DESC_IMPORT,
              BASE_IMPOSABLE, IVA_REGIM, IVA_PCT, IVA_IMPORT, TOTAL, SOURCE_CHANNEL)
             VALUES (?, 'TEST|SUMMARY|ORIGIN_FACT', 'A', 2026, 9990, 'A-2026-9990',
                     'F1', '2026-09-29 10:09:00', 'PAID', 'ISSUED', 'PENDING',
                     'Persona Resum', '12345678Z', '90.00', '0.00',
                     '90.00', 'EXEMPT', '0.00', '0.00', '90.00', 'WEB')"
        )->execute([$uuidOriginInvoice]);

        $db->prepare(
            "INSERT INTO novice_promotion_grant
             (UUID_ENTITLEMENT, UUID_VALIDATION, ORIGIN_UUID_OPERATION, UUID_FACTURA,
              HOLDER_PARTY_KEY, ORIGINAL_CASH_AMOUNT, AVAILABLE_AMOUNT, CREATED_AT)
             VALUES (?, ?, ?, ?, 'person:summary', '90.00', '20.00', '2026-09-29 10:10:00')"
        )->execute([$uuidEntitlement, $uuidValidation, $uuidOperation, $uuidOriginInvoice]);

        $db->prepare(
            "INSERT INTO novice_promotion_code_outbox
             (UUID_ENTITLEMENT, STATUS, TOKEN_CIPHERTEXT, TOKEN_NONCE,
              TOKEN_TAG, WRAP_KEY_VERSION, SENT_AT, CREATED_AT, UPDATED_AT)
             VALUES (?, 'SENT', 'ciphertext-not-token', ?, ?, 'test-v1',
                     '2026-09-29 10:12:00', '2026-09-29 10:11:00', '2026-09-29 10:12:00')"
        )->execute([
            $uuidEntitlement,
            random_bytes(12),
            random_bytes(16),
        ]);

        $db->prepare(
            "INSERT INTO commercial_operation
             (UUID_OPERATION, IDEMPOTENCY_KEY, OPERATION_TYPE, SOURCE_CHANNEL,
              SOURCE_TYPE, SOURCE_ID, PRODUCT_TYPE, PRODUCT_CODE, PRODUCT_EDITION,
              CLASSIFICATION, CLASSIFICATION_REASON, STATUS, CURRENCY,
              GROSS_AMOUNT, DISCOUNT_AMOUNT, NET_AMOUNT, PRICE_SNAPSHOT_JSON, TAX_SNAPSHOT_JSON,
              UUID_FACTURA)
             VALUES (?, ?, 'ENROLLMENT', 'WEB', 'CURS', '8001', 'CURS', 'CURS-DESTI', '2026/10',
                     'BILLABLE', 'STANDARD', 'PAID', 'EUR',
                     '100.00', '70.00', '30.00', '{}', '{}', ?)"
        )->execute([$uuidDestination, 'TEST|SUMMARY|DEST', $uuidInvoice]);

        $db->prepare(
            "INSERT INTO factura
             (UUID_FACTURA, IDEMPOTENCY_KEY, TIPUS_SERIE, ANY_FACT, NUM_SEQ, NUM_VISIBLE,
              TIPUS_FACTURA, DATA_EMISSIO, ESTAT_COBRAMENT, ESTAT_FACTURA, ESTAT_AEAT,
              BILLING_NOM_RAO, BILLING_NIF_CIF, IMPORT_BASE, DESC_IMPORT,
              BASE_IMPOSABLE, IVA_REGIM, IVA_PCT, IVA_IMPORT, TOTAL, SOURCE_CHANNEL)
             VALUES (?, 'TEST|SUMMARY|FACT', 'A', 2026, 9991, 'A-2026-9991',
                     'F1', '2026-10-10 10:00:00', 'PAID', 'ISSUED', 'PENDING',
                     'Persona Resum', '12345678Z', '100.00', '70.00',
                     '30.00', 'EXEMPT', '0.00', '0.00', '30.00', 'WEB')"
        )->execute([$uuidInvoice]);

        $db->prepare(
            "INSERT INTO novice_promotion_application
             (UUID_APPLICATION, UUID_ENTITLEMENT, UUID_DESTINATION_OPERATION,
              UUID_DESTINATION_FACTURA, IDEMPOTENCY_KEY, REQUEST_FINGERPRINT,
              AMOUNT, DESTINATION_ORDINARY_NET_AMOUNT, STATUS,
              RESERVED_AT, RESERVATION_EXPIRES_AT, APPLIED_AT)
             VALUES (?, ?, ?, ?, 'TEST|SUMMARY|APP', ?, '70.00', '100.00', 'APPLIED',
                     '2026-10-10 09:00:00', '2026-10-10 11:00:00', '2026-10-10 10:01:00')"
        )->execute([
            $uuidApplication, $uuidEntitlement, $uuidDestination, $uuidInvoice,
            hash('sha256', 'summary-app'),
        ]);

        $summary = (new NovicePromotionStudentSummaryService())->byIdentity($db, '12.345.678-Z');

        Assert::same(1, count($summary['rights']));
        $right = $summary['rights'][0];
        Assert::same('PARTIALLY_USED', $right['display_status']);
        Assert::same('90.00', $right['original_amount']);
        Assert::same('70.00', $right['applied_amount']);
        Assert::same('0.00', $right['reserved_amount']);
        Assert::same('20.00', $right['available_amount']);
        Assert::same('SENT', $right['delivery_status']);
        Assert::same('JASOM', $right['origin']['product_code']);
        Assert::same(1, count($right['applications']));
        Assert::same('APPLIED', $right['applications'][0]['status']);
        Assert::same('70.00', $right['applications'][0]['amount']);
        Assert::same('A-2026-9991', $right['applications'][0]['invoice_number']);

        $serialized = json_encode($summary, JSON_THROW_ON_ERROR);
        Assert::same(false, str_contains($serialized, 'NOV-SECRET-NOT-EXPOSED'));
        Assert::same(false, str_contains($serialized, 'ciphertext-not-token'));
    }
}
