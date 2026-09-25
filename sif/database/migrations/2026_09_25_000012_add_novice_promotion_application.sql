-- UC-111: a single novice promotion can fund MANY later course enrollments.
-- This ledger is a commercial discount, NOT credit_balance, CHARGE or
-- payment_allocation. A RESERVED application is already deducted from the
-- spendable AVAILABLE_AMOUNT to exclude concurrent overspending.
--
-- Destination operations and invoice issuance are managed by the existing
-- pricing/fiscal workflow; this migration never changes issued invoices.
CREATE TABLE IF NOT EXISTS novice_promotion_application (
    UUID_APPLICATION CHAR(36) NOT NULL PRIMARY KEY,
    UUID_ENTITLEMENT CHAR(36) NOT NULL,
    UUID_DESTINATION_OPERATION CHAR(36) NOT NULL,
    UUID_DESTINATION_FACTURA CHAR(36) NULL,
    IDEMPOTENCY_KEY VARCHAR(140) NOT NULL,
    REQUEST_FINGERPRINT CHAR(64) NOT NULL,
    AMOUNT DECIMAL(12,2) NOT NULL,
    STATUS VARCHAR(30) NOT NULL,
    RESERVED_AT DATETIME NOT NULL,
    RESERVATION_EXPIRES_AT DATETIME NOT NULL,
    APPLIED_AT DATETIME NULL,
    RELEASED_AT DATETIME NULL,
    REVERSED_AT DATETIME NULL,
    REASON_CODE VARCHAR(80) NULL,
    CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UPDATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_novice_application_idempotency (IDEMPOTENCY_KEY),
    UNIQUE KEY uq_novice_application_destination (UUID_DESTINATION_OPERATION),
    KEY idx_novice_application_right_state (UUID_ENTITLEMENT, STATUS, RESERVATION_EXPIRES_AT),
    KEY idx_novice_application_destination_invoice (UUID_DESTINATION_FACTURA),
    CONSTRAINT fk_novice_application_right
        FOREIGN KEY (UUID_ENTITLEMENT) REFERENCES novice_promotion_grant(UUID_ENTITLEMENT),
    CONSTRAINT fk_novice_application_destination
        FOREIGN KEY (UUID_DESTINATION_OPERATION) REFERENCES commercial_operation(UUID_OPERATION),
    CONSTRAINT fk_novice_application_invoice
        FOREIGN KEY (UUID_DESTINATION_FACTURA) REFERENCES factura(UUID_FACTURA),
    CONSTRAINT chk_novice_application_positive CHECK (AMOUNT > 0),
    CONSTRAINT chk_novice_application_status CHECK (
        STATUS IN ('RESERVED','APPLIED','RELEASED','REVERSED')
    ),
    CONSTRAINT chk_novice_application_reservation_dates CHECK (
        RESERVATION_EXPIRES_AT > RESERVED_AT
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
