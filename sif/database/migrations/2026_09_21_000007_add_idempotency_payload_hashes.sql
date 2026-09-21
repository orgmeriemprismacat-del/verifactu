-- Additive migration. NULL marks pre-migration invoices whose original request
-- cannot safely be reconstructed from the fiscal record alone.
ALTER TABLE factura
    ADD COLUMN IDEMPOTENCY_PAYLOAD_HASH CHAR(64) NULL;

-- Existing payment hashes used PHP JSON insertion order (v1).
-- New hashes canonicalize associative arrays (v2); never reinterpret v1 as v2.
ALTER TABLE payment_transaction
    ADD COLUMN PAYLOAD_HASH_VERSION TINYINT NOT NULL DEFAULT 1;
