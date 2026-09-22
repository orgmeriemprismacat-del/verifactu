-- UC-111: one promotional grant per verified student (not a prepaid cash balance).
-- Additive migration: do not modify the historical 000005 entitlement schema.
-- A grant never creates a payment_transaction or a bank payment allocation.

CREATE TABLE IF NOT EXISTS novice_promotion_grant (
    UUID_ENTITLEMENT CHAR(36) NOT NULL PRIMARY KEY,
    HOLDER_PARTY_KEY VARCHAR(100) NOT NULL,
    ORIGIN_UUID_OPERATION CHAR(36) NOT NULL,
    UUID_VALIDATION CHAR(36) NOT NULL,
    UUID_FACTURA CHAR(36) NOT NULL,
    ORIGINAL_CASH_AMOUNT DECIMAL(12,2) NOT NULL,
    AVAILABLE_AMOUNT DECIMAL(12,2) NOT NULL,
    CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_novice_original_positive CHECK (ORIGINAL_CASH_AMOUNT > 0),
    CONSTRAINT chk_novice_available_range CHECK (AVAILABLE_AMOUNT >= 0 AND AVAILABLE_AMOUNT <= ORIGINAL_CASH_AMOUNT),
    UNIQUE KEY uq_novice_grant_person (HOLDER_PARTY_KEY),
    UNIQUE KEY uq_novice_grant_origin (ORIGIN_UUID_OPERATION),
    UNIQUE KEY uq_novice_grant_validation (UUID_VALIDATION),
    KEY idx_novice_grant_invoice (UUID_FACTURA),
    CONSTRAINT fk_novice_grant_entitlement
        FOREIGN KEY (UUID_ENTITLEMENT) REFERENCES commercial_entitlement(UUID_ENTITLEMENT),
    CONSTRAINT fk_novice_grant_origin
        FOREIGN KEY (ORIGIN_UUID_OPERATION) REFERENCES commercial_operation(UUID_OPERATION),
    CONSTRAINT fk_novice_grant_validation
        FOREIGN KEY (UUID_VALIDATION) REFERENCES discount_validation(UUID_VALIDATION),
    CONSTRAINT fk_novice_grant_invoice
        FOREIGN KEY (UUID_FACTURA) REFERENCES factura(UUID_FACTURA)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
