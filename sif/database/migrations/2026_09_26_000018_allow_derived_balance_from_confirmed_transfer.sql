-- UC-111 / DEC-18/21/23:
-- A course reached through one or more CONFIRMED transfers may itself be
-- cancelled. Its new cancellation-derived balance must point to the CURRENT
-- transfer, not back to the historical application before the course change.
--
-- Additive migration: preserve historical migration hashes.
ALTER TABLE novice_promotion_derived_balance
    DROP CHECK chk_novice_derived_source,
    ADD COLUMN SOURCE_UUID_TRANSFER CHAR(36) NULL AFTER SOURCE_UUID_DERIVED_APPLICATION,
    ADD UNIQUE KEY uq_novice_derived_source_transfer (SOURCE_UUID_TRANSFER),
    ADD CONSTRAINT fk_novice_derived_source_transfer
        FOREIGN KEY (SOURCE_UUID_TRANSFER)
        REFERENCES novice_promotion_application_transfer(UUID_TRANSFER),
    ADD CONSTRAINT chk_novice_derived_source CHECK (
        (
            SOURCE_UUID_APPLICATION IS NOT NULL
            AND SOURCE_UUID_DERIVED_APPLICATION IS NULL
            AND SOURCE_UUID_TRANSFER IS NULL
            AND PARENT_UUID_DERIVED_BALANCE IS NULL
        )
        OR (
            SOURCE_UUID_APPLICATION IS NULL
            AND SOURCE_UUID_DERIVED_APPLICATION IS NOT NULL
            AND SOURCE_UUID_TRANSFER IS NULL
            AND PARENT_UUID_DERIVED_BALANCE IS NOT NULL
        )
        OR (
            SOURCE_UUID_APPLICATION IS NULL
            AND SOURCE_UUID_DERIVED_APPLICATION IS NULL
            AND SOURCE_UUID_TRANSFER IS NOT NULL
        )
    );

CREATE INDEX idx_novice_derived_source_transfer
    ON novice_promotion_derived_balance (SOURCE_UUID_TRANSFER, STATUS);
