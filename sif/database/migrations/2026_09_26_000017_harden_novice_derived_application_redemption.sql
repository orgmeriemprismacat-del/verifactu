-- UC-111 / DEC-23: make every use of a cancellation-derived promotional
-- balance independently reservable, idempotent and auditable.
--
-- The derived balance is COMMERCIAL PROMOTIONAL value, never prepaid cash.
-- RESERVED already debits AVAILABLE_PROMOTIONAL_AMOUNT so concurrent checkouts
-- cannot spend the same value twice. APPLIED must not debit it a second time.
ALTER TABLE novice_promotion_derived_application
    ADD COLUMN REQUEST_FINGERPRINT CHAR(64) NULL,
    ADD COLUMN DESTINATION_ORDINARY_NET_AMOUNT DECIMAL(12,2) NULL,
    ADD COLUMN RESERVATION_EXPIRES_AT DATETIME NULL,
    ADD COLUMN RELEASED_AT DATETIME NULL,
    ADD COLUMN REASON_CODE VARCHAR(80) NULL,
    ADD CONSTRAINT chk_novice_derived_application_reservation CHECK (
        (STATUS = 'RESERVED'
            AND REQUEST_FINGERPRINT IS NOT NULL
            AND DESTINATION_ORDINARY_NET_AMOUNT IS NOT NULL
            AND DESTINATION_ORDINARY_NET_AMOUNT >= AMOUNT
            AND RESERVATION_EXPIRES_AT IS NOT NULL
            AND RESERVATION_EXPIRES_AT > RESERVED_AT
            AND APPLIED_AT IS NULL AND RELEASED_AT IS NULL)
        OR STATUS <> 'RESERVED'
    ),
    ADD CONSTRAINT chk_novice_derived_application_applied CHECK (
        (STATUS = 'APPLIED'
            AND UUID_DESTINATION_FACTURA IS NOT NULL
            AND APPLIED_AT IS NOT NULL
            AND DESTINATION_ORDINARY_NET_AMOUNT IS NOT NULL
            AND DESTINATION_ORDINARY_NET_AMOUNT >= AMOUNT)
        OR STATUS <> 'APPLIED'
    ),
    ADD CONSTRAINT chk_novice_derived_application_released CHECK (
        (STATUS = 'RELEASED' AND RELEASED_AT IS NOT NULL AND REASON_CODE IS NOT NULL)
        OR STATUS <> 'RELEASED'
    );

CREATE INDEX idx_novice_derived_application_expiry
    ON novice_promotion_derived_application (STATUS, RESERVATION_EXPIRES_AT);
