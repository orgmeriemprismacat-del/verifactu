-- UC-111 / DEC-23: persistent review of the commercial consequences of
-- returning the original JASOM after novice promotion value has been issued.
--
-- This table does NOT issue a bank REFUND, debt CHARGE, invoice or credit.
-- While STATUS=PENDING_APPROVAL, commercial_entitlement.STATUS is frozen as
-- REFUND_REVIEW by the service so existing UC-111 spend/transfer services
-- (which require ACTIVE) fail closed.
CREATE TABLE IF NOT EXISTS novice_promotion_root_refund_review (
    UUID_REVIEW CHAR(36) NOT NULL PRIMARY KEY,
    ROOT_UUID_ENTITLEMENT CHAR(36) NOT NULL,
    HOLDER_PARTY_KEY VARCHAR(100) NOT NULL,
    ORIGIN_UUID_OPERATION CHAR(36) NOT NULL,
    PLAN_HASH CHAR(64) NOT NULL,
    PLAN_JSON JSON NOT NULL,
    STATUS VARCHAR(30) NOT NULL,
    REQUESTED_BY VARCHAR(100) NOT NULL,
    REQUEST_EVIDENCE_REF VARCHAR(140) NOT NULL,
    REQUESTED_AT DATETIME NOT NULL,
    DECIDED_BY VARCHAR(100) NULL,
    DECISION_REASON VARCHAR(80) NULL,
    DECIDED_AT DATETIME NULL,
    EXECUTED_AT DATETIME NULL,
    IDEMPOTENCY_KEY VARCHAR(140) NOT NULL,
    CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_novice_root_refund_idempotency (IDEMPOTENCY_KEY),
    KEY idx_novice_root_refund_root_status (ROOT_UUID_ENTITLEMENT, STATUS),
    KEY idx_novice_root_refund_origin (ORIGIN_UUID_OPERATION),
    CONSTRAINT fk_novice_root_refund_root
        FOREIGN KEY (ROOT_UUID_ENTITLEMENT)
        REFERENCES novice_promotion_grant(UUID_ENTITLEMENT),
    CONSTRAINT fk_novice_root_refund_origin
        FOREIGN KEY (ORIGIN_UUID_OPERATION)
        REFERENCES commercial_operation(UUID_OPERATION),
    CONSTRAINT chk_novice_root_refund_status CHECK (
        STATUS IN ('PENDING_APPROVAL','REJECTED','CANCELLED','EXECUTED')
    ),
    CONSTRAINT chk_novice_root_refund_decision CHECK (
        (STATUS = 'PENDING_APPROVAL'
            AND DECIDED_BY IS NULL AND DECISION_REASON IS NULL
            AND DECIDED_AT IS NULL AND EXECUTED_AT IS NULL)
        OR (STATUS IN ('REJECTED','CANCELLED')
            AND DECIDED_BY IS NOT NULL AND DECISION_REASON IS NOT NULL
            AND DECIDED_AT IS NOT NULL AND EXECUTED_AT IS NULL)
        OR (STATUS = 'EXECUTED'
            AND DECIDED_BY IS NOT NULL AND DECISION_REASON IS NOT NULL
            AND DECIDED_AT IS NOT NULL AND EXECUTED_AT IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
