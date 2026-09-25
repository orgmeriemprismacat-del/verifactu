-- UC-111: provenance of a NOVICE promotional amount after a destination
-- course is rectified/cancelled and creates a DISTINCT cancellation right.
--
-- This is an additive TRACE model, NOT an instruction to issue rectificative
-- invoices, bank refunds, tax movements or commercial credit balances.
-- The fiscal workflow must first validate/store its real rectificative.
-- Do NOT automatically extend the expiry of the original novice grant.

CREATE TABLE IF NOT EXISTS novice_promotion_derived_balance (
    UUID_DERIVED_BALANCE CHAR(36) NOT NULL PRIMARY KEY,
    ROOT_UUID_ENTITLEMENT CHAR(36) NOT NULL,
    PARENT_UUID_DERIVED_BALANCE CHAR(36) NULL,
    SOURCE_UUID_APPLICATION CHAR(36) NULL,
    SOURCE_UUID_DERIVED_APPLICATION CHAR(36) NULL,
    UUID_DESTINATION_OPERATION CHAR(36) NOT NULL,
    UUID_RECTIFICATIVE_FACTURA CHAR(36) NOT NULL,
    HOLDER_PARTY_KEY VARCHAR(100) NOT NULL,
    PROMOTIONAL_ORIGIN_AMOUNT DECIMAL(12,2) NOT NULL,
    AVAILABLE_PROMOTIONAL_AMOUNT DECIMAL(12,2) NOT NULL,
    STATUS VARCHAR(30) NOT NULL DEFAULT 'PENDING_FISCAL_REVIEW',
    ISSUED_AT DATETIME NULL,
    EXPIRES_AT DATETIME NULL,
    CANCELLED_AT DATETIME NULL,
    CANCELLATION_REASON VARCHAR(80) NULL,
    POLICY_SNAPSHOT_JSON JSON NOT NULL,
    IDEMPOTENCY_KEY VARCHAR(140) NOT NULL UNIQUE,
    CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_novice_derived_original_application (SOURCE_UUID_APPLICATION),
    UNIQUE KEY uq_novice_derived_child_application (SOURCE_UUID_DERIVED_APPLICATION),
    KEY idx_novice_derived_root (ROOT_UUID_ENTITLEMENT, STATUS),
    KEY idx_novice_derived_parent (PARENT_UUID_DERIVED_BALANCE),
    CONSTRAINT fk_novice_derived_root
        FOREIGN KEY (ROOT_UUID_ENTITLEMENT)
        REFERENCES novice_promotion_grant(UUID_ENTITLEMENT),
    CONSTRAINT fk_novice_derived_parent
        FOREIGN KEY (PARENT_UUID_DERIVED_BALANCE)
        REFERENCES novice_promotion_derived_balance(UUID_DERIVED_BALANCE),
    CONSTRAINT fk_novice_derived_source_application
        FOREIGN KEY (SOURCE_UUID_APPLICATION)
        REFERENCES novice_promotion_application(UUID_APPLICATION),
    CONSTRAINT fk_novice_derived_operation
        FOREIGN KEY (UUID_DESTINATION_OPERATION)
        REFERENCES commercial_operation(UUID_OPERATION),
    CONSTRAINT fk_novice_derived_rectificative
        FOREIGN KEY (UUID_RECTIFICATIVE_FACTURA)
        REFERENCES factura(UUID_FACTURA),
    CONSTRAINT chk_novice_derived_amounts CHECK (
        PROMOTIONAL_ORIGIN_AMOUNT > 0
        AND AVAILABLE_PROMOTIONAL_AMOUNT >= 0
        AND AVAILABLE_PROMOTIONAL_AMOUNT <= PROMOTIONAL_ORIGIN_AMOUNT
    ),
    CONSTRAINT chk_novice_derived_source CHECK (
        (SOURCE_UUID_APPLICATION IS NOT NULL AND SOURCE_UUID_DERIVED_APPLICATION IS NULL
            AND PARENT_UUID_DERIVED_BALANCE IS NULL)
        OR (SOURCE_UUID_APPLICATION IS NULL AND SOURCE_UUID_DERIVED_APPLICATION IS NOT NULL
            AND PARENT_UUID_DERIVED_BALANCE IS NOT NULL)
    ),
    CONSTRAINT chk_novice_derived_status CHECK (
        STATUS IN ('PENDING_FISCAL_REVIEW','ACTIVE','CANCELLED','EXPIRED')
    ),
    CONSTRAINT chk_novice_derived_dates CHECK (
        (STATUS = 'PENDING_FISCAL_REVIEW' AND ISSUED_AT IS NULL AND EXPIRES_AT IS NULL)
        OR (STATUS <> 'PENDING_FISCAL_REVIEW' AND ISSUED_AT IS NOT NULL
            AND EXPIRES_AT IS NOT NULL AND EXPIRES_AT > ISSUED_AT)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Explicit provenance for EVERY later spending of a derived right. A
-- parent balance is debited at reservation, not again on confirmation.
-- Rectified child applications remain in the historical ledger, but are
-- excluded from the ACTIVE exposure of the root.
CREATE TABLE IF NOT EXISTS novice_promotion_derived_application (
    UUID_DERIVED_APPLICATION CHAR(36) NOT NULL PRIMARY KEY,
    UUID_DERIVED_BALANCE CHAR(36) NOT NULL,
    ROOT_UUID_ENTITLEMENT CHAR(36) NOT NULL,
    UUID_DESTINATION_OPERATION CHAR(36) NOT NULL,
    UUID_DESTINATION_FACTURA CHAR(36) NULL,
    AMOUNT DECIMAL(12,2) NOT NULL,
    STATUS VARCHAR(30) NOT NULL,
    RESERVED_AT DATETIME NOT NULL,
    APPLIED_AT DATETIME NULL,
    CLOSED_AT DATETIME NULL,
    IDEMPOTENCY_KEY VARCHAR(140) NOT NULL UNIQUE,
    UNIQUE KEY uq_novice_derived_dest (UUID_DESTINATION_OPERATION),
    KEY idx_novice_derived_application_root (ROOT_UUID_ENTITLEMENT, STATUS),
    CONSTRAINT fk_novice_derived_application_balance
        FOREIGN KEY (UUID_DERIVED_BALANCE)
        REFERENCES novice_promotion_derived_balance(UUID_DERIVED_BALANCE),
    CONSTRAINT fk_novice_derived_application_root
        FOREIGN KEY (ROOT_UUID_ENTITLEMENT)
        REFERENCES novice_promotion_grant(UUID_ENTITLEMENT),
    CONSTRAINT fk_novice_derived_application_operation
        FOREIGN KEY (UUID_DESTINATION_OPERATION)
        REFERENCES commercial_operation(UUID_OPERATION),
    CONSTRAINT fk_novice_derived_application_invoice
        FOREIGN KEY (UUID_DESTINATION_FACTURA)
        REFERENCES factura(UUID_FACTURA),
    CONSTRAINT chk_novice_derived_application_amount CHECK (AMOUNT > 0),
    CONSTRAINT chk_novice_derived_application_status CHECK (
        STATUS IN ('RESERVED','APPLIED','TRANSFERRED','CONVERTED_TO_DERIVED',
                   'RELEASED','CANCELLED')
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE novice_promotion_derived_balance
    ADD CONSTRAINT fk_novice_derived_source_child
        FOREIGN KEY (SOURCE_UUID_DERIVED_APPLICATION)
        REFERENCES novice_promotion_derived_application(UUID_DERIVED_APPLICATION);

-- A course CHANGE is a MOVE of attribution, not a new consumption.
-- Its fiscal transaction must exist before the transfer is confirmed.
CREATE TABLE IF NOT EXISTS novice_promotion_application_transfer (
    UUID_TRANSFER CHAR(36) NOT NULL PRIMARY KEY,
    ROOT_UUID_ENTITLEMENT CHAR(36) NOT NULL,
    UUID_ORIGINAL_APPLICATION CHAR(36) NULL,
    UUID_DERIVED_APPLICATION CHAR(36) NULL,
    FROM_UUID_OPERATION CHAR(36) NOT NULL,
    TO_UUID_OPERATION CHAR(36) NOT NULL,
    UUID_RECTIFICATIVE_FACTURA CHAR(36) NOT NULL,
    AMOUNT DECIMAL(12,2) NOT NULL,
    STATUS VARCHAR(30) NOT NULL DEFAULT 'PENDING_FISCAL_REVIEW',
    IDEMPOTENCY_KEY VARCHAR(140) NOT NULL UNIQUE,
    CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONFIRMED_AT DATETIME NULL,
    UNIQUE KEY uq_novice_transfer_original (UUID_ORIGINAL_APPLICATION),
    UNIQUE KEY uq_novice_transfer_derived (UUID_DERIVED_APPLICATION),
    KEY idx_novice_transfer_root (ROOT_UUID_ENTITLEMENT, STATUS),
    CONSTRAINT fk_novice_transfer_root
        FOREIGN KEY (ROOT_UUID_ENTITLEMENT) REFERENCES novice_promotion_grant(UUID_ENTITLEMENT),
    CONSTRAINT fk_novice_transfer_original
        FOREIGN KEY (UUID_ORIGINAL_APPLICATION) REFERENCES novice_promotion_application(UUID_APPLICATION),
    CONSTRAINT fk_novice_transfer_derived
        FOREIGN KEY (UUID_DERIVED_APPLICATION) REFERENCES novice_promotion_derived_application(UUID_DERIVED_APPLICATION),
    CONSTRAINT fk_novice_transfer_from
        FOREIGN KEY (FROM_UUID_OPERATION) REFERENCES commercial_operation(UUID_OPERATION),
    CONSTRAINT fk_novice_transfer_to
        FOREIGN KEY (TO_UUID_OPERATION) REFERENCES commercial_operation(UUID_OPERATION),
    CONSTRAINT fk_novice_transfer_rectificative
        FOREIGN KEY (UUID_RECTIFICATIVE_FACTURA) REFERENCES factura(UUID_FACTURA),
    CONSTRAINT chk_novice_transfer_amount CHECK (AMOUNT > 0),
    CONSTRAINT chk_novice_transfer_different_dest CHECK (FROM_UUID_OPERATION <> TO_UUID_OPERATION),
    CONSTRAINT chk_novice_transfer_source CHECK (
        (UUID_ORIGINAL_APPLICATION IS NOT NULL AND UUID_DERIVED_APPLICATION IS NULL)
        OR (UUID_ORIGINAL_APPLICATION IS NULL AND UUID_DERIVED_APPLICATION IS NOT NULL)
    ),
    CONSTRAINT chk_novice_transfer_status CHECK (
        STATUS IN ('PENDING_FISCAL_REVIEW','CONFIRMED','CANCELLED')
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
