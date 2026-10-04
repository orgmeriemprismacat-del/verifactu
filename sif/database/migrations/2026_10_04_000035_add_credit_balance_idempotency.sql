-- UC-016B hardening: optional idempotency for credit creation.
-- Nullable columns preserve compatibility with legacy/manual credit flows.

ALTER TABLE credit_balance
    ADD COLUMN IDEMPOTENCY_KEY VARCHAR(160) NULL AFTER UUID_CREDIT,
    ADD COLUMN PAYLOAD_HASH CHAR(64) NULL AFTER IDEMPOTENCY_KEY,
    ADD UNIQUE KEY uq_credit_balance_idempotency (IDEMPOTENCY_KEY);
