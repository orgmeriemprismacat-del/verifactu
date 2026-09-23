-- UC-111. Durable, encrypted one-code-per-right delivery outbox.
-- Codes are random, not derived from a person's identity or invoice number.
-- Only CODE_HASH is used at redemption. TOKEN_CIPHERTEXT must never be
-- returned by a public API, placed in logs or copied to analytical exports.
-- Keep wrapping keys outside Git/repository and rotate after exposure.

CREATE TABLE IF NOT EXISTS novice_promotion_code_outbox (
    UUID_ENTITLEMENT CHAR(36) NOT NULL PRIMARY KEY,
    TOKEN_NONCE VARBINARY(12) NOT NULL,
    TOKEN_TAG VARBINARY(16) NOT NULL,
    TOKEN_CIPHERTEXT VARBINARY(512) NOT NULL,
    WRAP_KEY_VERSION VARCHAR(30) NOT NULL,
    STATUS VARCHAR(30) NOT NULL DEFAULT 'PREPARED',
    ATTEMPTS INT NOT NULL DEFAULT 0,
    SENT_AT DATETIME NULL,
    LAST_ERROR_CODE VARCHAR(80) NULL,
    CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UPDATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_novice_code_entitlement
        FOREIGN KEY (UUID_ENTITLEMENT) REFERENCES commercial_entitlement(UUID_ENTITLEMENT),
    CONSTRAINT chk_novice_code_status
        CHECK (STATUS IN ('PREPARED', 'SENDING', 'SENT', 'FAILED')),
    CONSTRAINT chk_novice_code_attempts CHECK (ATTEMPTS >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
