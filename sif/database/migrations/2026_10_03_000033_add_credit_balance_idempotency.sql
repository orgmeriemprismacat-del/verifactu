-- UC-006 / UC-029: optional caller-provided idempotency for generic credit balances.
-- Existing balances remain valid with NULL idempotency fields. New callers that
-- supply a key get payload-equivalence protection without guessing business
-- identity from SOURCE_TYPE/SOURCE_ID.

ALTER TABLE credit_balance
    ADD COLUMN IDEMPOTENCY_KEY VARCHAR(160) NULL AFTER UUID_CREDIT,
    ADD COLUMN IDEMPOTENCY_PAYLOAD_HASH CHAR(64) NULL AFTER IDEMPOTENCY_KEY,
    ADD UNIQUE KEY uq_credit_balance_idempotency (IDEMPOTENCY_KEY),
    ADD CONSTRAINT chk_credit_balance_idempotency_pair CHECK (
        (IDEMPOTENCY_KEY IS NULL AND IDEMPOTENCY_PAYLOAD_HASH IS NULL)
        OR
        (IDEMPOTENCY_KEY IS NOT NULL AND IDEMPOTENCY_PAYLOAD_HASH IS NOT NULL)
    );
