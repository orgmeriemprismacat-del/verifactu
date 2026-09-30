-- RM-011 / UC-015. Quantitative money attribution per enrollment.
-- One external bank movement remains one payment_transaction. This ledger
-- attributes that immutable payment to one or more ID_INSC without creating
-- additional CHARGE rows.

CREATE TABLE IF NOT EXISTS enrollment_fund_movement (
    ID BIGINT AUTO_INCREMENT PRIMARY KEY,
    UUID_MOVEMENT CHAR(36) NOT NULL UNIQUE,
    IDEMPOTENCY_KEY VARCHAR(160) NOT NULL UNIQUE,
    MOVEMENT_TYPE VARCHAR(40) NOT NULL,
    ORDRE INT NOT NULL,
    UUID_PAYMENT CHAR(36) NULL,
    UUID_FACTURA CHAR(36) NULL,
    ID_FACTURA_LINIA BIGINT NULL,
    ID_INSC_ORIGEN BIGINT NULL,
    ID_INSC_DESTI BIGINT NULL,
    IMPORT DECIMAL(12,2) NOT NULL,
    CURRENCY CHAR(3) NOT NULL DEFAULT 'EUR',
    UUID_OPERATION CHAR(36) NULL,
    CORRELATION_ID VARCHAR(120) NOT NULL,
    REVERSES_UUID_MOVEMENT CHAR(36) NULL,
    NOTES VARCHAR(255) NULL,
    CREATED_AT DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

    KEY idx_enrollment_fund_payment (UUID_PAYMENT, ORDRE),
    KEY idx_enrollment_fund_invoice (UUID_FACTURA, ORDRE),
    KEY idx_enrollment_fund_line (ID_FACTURA_LINIA),
    KEY idx_enrollment_fund_origin (ID_INSC_ORIGEN, CREATED_AT),
    KEY idx_enrollment_fund_destination (ID_INSC_DESTI, CREATED_AT),
    KEY idx_enrollment_fund_operation (UUID_OPERATION, CREATED_AT),
    KEY idx_enrollment_fund_reversal (REVERSES_UUID_MOVEMENT),

    CONSTRAINT fk_enrollment_fund_payment
        FOREIGN KEY (UUID_PAYMENT) REFERENCES payment_transaction(UUID_PAYMENT),
    CONSTRAINT fk_enrollment_fund_invoice
        FOREIGN KEY (UUID_FACTURA) REFERENCES factura(UUID_FACTURA),
    CONSTRAINT fk_enrollment_fund_line
        FOREIGN KEY (ID_FACTURA_LINIA) REFERENCES factura_linia(ID),
    CONSTRAINT fk_enrollment_fund_operation
        FOREIGN KEY (UUID_OPERATION) REFERENCES commercial_operation(UUID_OPERATION),
    CONSTRAINT fk_enrollment_fund_reversal
        FOREIGN KEY (REVERSES_UUID_MOVEMENT) REFERENCES enrollment_fund_movement(UUID_MOVEMENT),

    CONSTRAINT chk_enrollment_fund_amount CHECK (IMPORT > 0),
    CONSTRAINT chk_enrollment_fund_order CHECK (ORDRE > 0),
    CONSTRAINT chk_enrollment_fund_type CHECK (
        MOVEMENT_TYPE IN (
            'EXTERNAL_ALLOCATION',
            'INTERNAL_TRANSFER',
            'REVERSAL',
            'COMPENSATION_ALLOCATION'
        )
    ),
    CONSTRAINT chk_enrollment_fund_parties CHECK (
        (MOVEMENT_TYPE IN ('EXTERNAL_ALLOCATION', 'COMPENSATION_ALLOCATION')
            AND ID_INSC_ORIGEN IS NULL AND ID_INSC_DESTI IS NOT NULL
            AND UUID_PAYMENT IS NOT NULL)
        OR
        (MOVEMENT_TYPE = 'INTERNAL_TRANSFER'
            AND ID_INSC_ORIGEN IS NOT NULL AND ID_INSC_DESTI IS NOT NULL
            AND ID_INSC_ORIGEN <> ID_INSC_DESTI)
        OR
        (MOVEMENT_TYPE = 'REVERSAL'
            AND REVERSES_UUID_MOVEMENT IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
