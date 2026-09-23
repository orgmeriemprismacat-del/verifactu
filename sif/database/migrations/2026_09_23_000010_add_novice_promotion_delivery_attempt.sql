-- UC-111: additive delivery readiness and claim tracking.
-- IMPORTANT: no code here verifies email ownership. The verified-recipient
-- record MUST be written solely by a separate authenticated confirmation
-- workflow that records evidence of control of the delivery address.
-- Do not prefill it from an enrollment form or the Redsys callback.

CREATE TABLE IF NOT EXISTS novice_promotion_verified_recipient (
    UUID_ENTITLEMENT CHAR(36) NOT NULL PRIMARY KEY,
    EMAIL VARCHAR(180) NOT NULL,
    VERIFIED_AT DATETIME NOT NULL,
    VERIFICATION_REF VARCHAR(140) NOT NULL UNIQUE,
    RECORDED_BY VARCHAR(100) NOT NULL,
    CONSTRAINT fk_novice_verified_recipient_right
        FOREIGN KEY (UUID_ENTITLEMENT) REFERENCES commercial_entitlement(UUID_ENTITLEMENT)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE novice_promotion_code_outbox
    ADD COLUMN CLAIM_ID CHAR(36) NULL,
    ADD COLUMN CLAIMED_AT DATETIME NULL,
    ADD COLUMN NEXT_ATTEMPT_AT DATETIME NULL,
    ADD KEY idx_novice_code_delivery (STATUS, NEXT_ATTEMPT_AT, CLAIMED_AT);

-- Migrations remain append-only. The existing encrypted token and CODE_HASH
-- MUST NOT be replaced when reclaiming a timed-out delivery attempt.
