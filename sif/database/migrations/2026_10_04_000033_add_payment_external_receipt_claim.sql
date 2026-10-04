-- UC-023: cross-channel claim for one real external receipt.
--
-- The payment IDENTITY key is channel/use-case specific; this table provides
-- a second, global uniqueness boundary for the external economic fact.
-- Historical rows are not auto-backfilled because duplicate legacy bank
-- references require explicit reconciliation. New writes claim the receipt
-- atomically and a historical receipt is claimed when it is first reconciled.

CREATE TABLE IF NOT EXISTS payment_external_receipt_claim (
    RECEIPT_KEY VARCHAR(180) PRIMARY KEY,
    RECEIPT_TYPE VARCHAR(30) NOT NULL,
    RECEIPT_VALUE VARCHAR(120) NOT NULL,
    UUID_PAYMENT CHAR(36) NOT NULL,
    CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payment_external_receipt_type_value (RECEIPT_TYPE, RECEIPT_VALUE),
    KEY idx_payment_external_receipt_payment (UUID_PAYMENT),
    CONSTRAINT fk_payment_external_receipt_payment
        FOREIGN KEY (UUID_PAYMENT) REFERENCES payment_transaction(UUID_PAYMENT)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
