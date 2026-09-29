-- UC-111 / DEC-23: approved root-JASOM refund commercial consequences.
--
-- Recovery items are AUDIT/WORKFLOW records for promotional value that is
-- still applied to active courses. They are NOT payment_transaction CHARGE,
-- factura, credit_balance or an automatic debt collection.
ALTER TABLE novice_promotion_root_refund_review
    ADD COLUMN APPROVAL_DECISION_ID VARCHAR(100) NULL,
    ADD COLUMN APPROVAL_EVIDENCE_REF VARCHAR(140) NULL,
    ADD UNIQUE KEY uq_novice_root_refund_decision (APPROVAL_DECISION_ID);

CREATE TABLE IF NOT EXISTS novice_promotion_root_refund_recovery (
    UUID_RECOVERY CHAR(36) NOT NULL PRIMARY KEY,
    UUID_REVIEW CHAR(36) NOT NULL,
    ROOT_UUID_ENTITLEMENT CHAR(36) NOT NULL,
    LOGICAL_APPLICATION_ID VARCHAR(120) NOT NULL,
    SOURCE_KIND VARCHAR(30) NOT NULL,
    SOURCE_UUID CHAR(36) NOT NULL,
    UUID_DESTINATION_OPERATION CHAR(36) NOT NULL,
    AMOUNT DECIMAL(12,2) NOT NULL,
    STATUS VARCHAR(30) NOT NULL DEFAULT 'PENDING_RECOVERY',
    CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    RESOLVED_AT DATETIME NULL,
    RESOLUTION_CODE VARCHAR(80) NULL,
    UNIQUE KEY uq_novice_root_refund_recovery_item
        (UUID_REVIEW, LOGICAL_APPLICATION_ID),
    KEY idx_novice_root_refund_recovery_root
        (ROOT_UUID_ENTITLEMENT, STATUS),
    CONSTRAINT fk_novice_root_refund_recovery_review
        FOREIGN KEY (UUID_REVIEW)
        REFERENCES novice_promotion_root_refund_review(UUID_REVIEW),
    CONSTRAINT fk_novice_root_refund_recovery_root
        FOREIGN KEY (ROOT_UUID_ENTITLEMENT)
        REFERENCES novice_promotion_grant(UUID_ENTITLEMENT),
    CONSTRAINT fk_novice_root_refund_recovery_operation
        FOREIGN KEY (UUID_DESTINATION_OPERATION)
        REFERENCES commercial_operation(UUID_OPERATION),
    CONSTRAINT chk_novice_root_refund_recovery_amount CHECK (AMOUNT > 0),
    CONSTRAINT chk_novice_root_refund_recovery_source CHECK (
        SOURCE_KIND IN ('ORIGINAL_APPLICATION','DERIVED_APPLICATION','TRANSFER')
    ),
    CONSTRAINT chk_novice_root_refund_recovery_status CHECK (
        STATUS IN ('PENDING_RECOVERY','RECOVERED','WAIVED','CANCELLED')
    ),
    CONSTRAINT chk_novice_root_refund_recovery_resolution CHECK (
        (STATUS = 'PENDING_RECOVERY'
            AND RESOLVED_AT IS NULL AND RESOLUTION_CODE IS NULL)
        OR (STATUS <> 'PENDING_RECOVERY'
            AND RESOLVED_AT IS NOT NULL AND RESOLUTION_CODE IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
