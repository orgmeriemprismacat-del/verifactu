-- UC-006 / UC-028 / UC-029.
-- Extend the enrollment fund ledger so a paid amount attributed to an
-- enrollment can leave that enrollment exactly once either as a confirmed
-- REFUND or as a credit balance. This does not create a second bank movement:
-- REFUND_EXIT references the real REFUND payment; CREDIT_CREATE references
-- the internal credit_balance row.

ALTER TABLE enrollment_fund_movement
    ADD COLUMN UUID_CREDIT CHAR(36) NULL AFTER UUID_PAYMENT,
    ADD KEY idx_enrollment_fund_credit (UUID_CREDIT),
    ADD CONSTRAINT fk_enrollment_fund_credit
        FOREIGN KEY (UUID_CREDIT) REFERENCES credit_balance(UUID_CREDIT);

ALTER TABLE enrollment_fund_movement
    DROP CHECK chk_enrollment_fund_type,
    DROP CHECK chk_enrollment_fund_parties,
    ADD CONSTRAINT chk_enrollment_fund_type CHECK (
        MOVEMENT_TYPE IN (
            'EXTERNAL_ALLOCATION',
            'INTERNAL_TRANSFER',
            'REVERSAL',
            'COMPENSATION_ALLOCATION',
            'REFUND_EXIT',
            'CREDIT_CREATE'
        )
    ),
    ADD CONSTRAINT chk_enrollment_fund_parties CHECK (
        (MOVEMENT_TYPE IN ('EXTERNAL_ALLOCATION', 'COMPENSATION_ALLOCATION')
            AND ID_INSC_ORIGEN IS NULL
            AND ID_INSC_DESTI IS NOT NULL
            AND UUID_PAYMENT IS NOT NULL
            AND UUID_CREDIT IS NULL)
        OR
        (MOVEMENT_TYPE = 'INTERNAL_TRANSFER'
            AND ID_INSC_ORIGEN IS NOT NULL
            AND ID_INSC_DESTI IS NOT NULL
            AND ID_INSC_ORIGEN <> ID_INSC_DESTI
            AND UUID_CREDIT IS NULL)
        OR
        (MOVEMENT_TYPE = 'REFUND_EXIT'
            AND ID_INSC_ORIGEN IS NOT NULL
            AND ID_INSC_DESTI IS NULL
            AND UUID_PAYMENT IS NOT NULL
            AND UUID_CREDIT IS NULL)
        OR
        (MOVEMENT_TYPE = 'CREDIT_CREATE'
            AND ID_INSC_ORIGEN IS NOT NULL
            AND ID_INSC_DESTI IS NULL
            AND UUID_CREDIT IS NOT NULL)
        OR
        (MOVEMENT_TYPE = 'REVERSAL'
            AND REVERSES_UUID_MOVEMENT IS NOT NULL)
    );
