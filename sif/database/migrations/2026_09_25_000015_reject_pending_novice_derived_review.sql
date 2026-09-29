-- UC-111: denying a proposed cancellation must NOT require fake ISSUED_AT,
-- EXPIRES_AT or a spendable derived balance. Additive corrective migration:
-- do not change a migration file that may already have a recorded hash.
--
-- PENDING_FISCAL_REVIEW/REJECTED = NOT ISSUED and cannot be spent.
-- ACTIVE/CANCELLED/EXPIRED = a right that was actually issued.
ALTER TABLE novice_promotion_derived_balance
    DROP CHECK chk_novice_derived_status,
    DROP CHECK chk_novice_derived_dates,
    ADD CONSTRAINT chk_novice_derived_status CHECK (
        STATUS IN ('PENDING_FISCAL_REVIEW','REJECTED','ACTIVE','CANCELLED','EXPIRED')
    ),
    ADD CONSTRAINT chk_novice_derived_dates CHECK (
        (STATUS IN ('PENDING_FISCAL_REVIEW','REJECTED')
            AND ISSUED_AT IS NULL AND EXPIRES_AT IS NULL
            AND AVAILABLE_PROMOTIONAL_AMOUNT = 0)
        OR (STATUS IN ('ACTIVE','CANCELLED','EXPIRED')
            AND ISSUED_AT IS NOT NULL AND EXPIRES_AT IS NOT NULL
            AND EXPIRES_AT > ISSUED_AT)
    );
