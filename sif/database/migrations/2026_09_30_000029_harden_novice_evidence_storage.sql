-- UC-111 / A111-02: auditable private storage lifecycle for documentary
-- evidence. Existing evidence rows, if any, are deliberately classified as
-- LEGACY_UNVERIFIED until an explicit reconciliation proves the referenced
-- object exists in approved private storage.
--
-- No public URL or original filename is persisted by this migration.
ALTER TABLE discount_evidence
    ADD COLUMN STORAGE_STATUS VARCHAR(30) NOT NULL DEFAULT 'LEGACY_UNVERIFIED',
    ADD COLUMN UPLOAD_IDEMPOTENCY_KEY VARCHAR(140) NULL,
    ADD COLUMN ORIGINAL_NAME_HASH CHAR(64) NULL,
    ADD COLUMN STORED_AT DATETIME NULL,
    ADD COLUMN STORAGE_FAILED_AT DATETIME NULL,
    ADD COLUMN LAST_STORAGE_ERROR_CODE VARCHAR(80) NULL,
    ADD UNIQUE KEY uq_discount_evidence_upload_idempotency (UPLOAD_IDEMPOTENCY_KEY),
    ADD KEY idx_discount_evidence_storage_status
        (UUID_VALIDATION, STORAGE_STATUS, CREATED_AT),
    ADD CONSTRAINT chk_discount_evidence_storage_status CHECK (
        STORAGE_STATUS IN (
            'LEGACY_UNVERIFIED',
            'PREPARED',
            'READY',
            'FAILED',
            'DELETED'
        )
    ),
    ADD CONSTRAINT chk_discount_evidence_storage_dates CHECK (
        (STORAGE_STATUS = 'PREPARED'
            AND STORED_AT IS NULL AND DELETED_AT IS NULL)
        OR (STORAGE_STATUS = 'READY'
            AND STORED_AT IS NOT NULL AND DELETED_AT IS NULL)
        OR (STORAGE_STATUS = 'FAILED'
            AND STORED_AT IS NULL
            AND STORAGE_FAILED_AT IS NOT NULL
            AND LAST_STORAGE_ERROR_CODE IS NOT NULL)
        OR (STORAGE_STATUS = 'DELETED'
            AND DELETED_AT IS NOT NULL
            AND DELETION_PROOF_HASH IS NOT NULL)
        OR STORAGE_STATUS = 'LEGACY_UNVERIFIED'
    );
