-- UC-111: authenticated account + proof of control of the recipient email.
-- The confirmation secret is NOT the promotional code and must never be stored
-- as plaintext. The future mail transport must be trusted and private.
CREATE TABLE IF NOT EXISTS novice_promotion_email_challenge (
    UUID_CHALLENGE CHAR(36) NOT NULL PRIMARY KEY,
    UUID_ENTITLEMENT CHAR(36) NOT NULL,
    HOLDER_PARTY_KEY VARCHAR(100) NOT NULL,
    EMAIL VARCHAR(180) NOT NULL,
    TOKEN_HASH CHAR(64) NOT NULL UNIQUE,
    CREATED_AT DATETIME NOT NULL,
    EXPIRES_AT DATETIME NOT NULL,
    SENT_AT DATETIME NULL,
    CONSUMED_AT DATETIME NULL,
    INVALIDATED_AT DATETIME NULL,
    FAILED_ATTEMPTS INT NOT NULL DEFAULT 0,
    KEY idx_novice_email_challenge_right (UUID_ENTITLEMENT, CREATED_AT),
    CONSTRAINT fk_novice_email_challenge_right
        FOREIGN KEY (UUID_ENTITLEMENT) REFERENCES commercial_entitlement(UUID_ENTITLEMENT),
    CONSTRAINT chk_novice_email_challenge_attempts
        CHECK (FAILED_ATTEMPTS >= 0 AND FAILED_ATTEMPTS <= 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
