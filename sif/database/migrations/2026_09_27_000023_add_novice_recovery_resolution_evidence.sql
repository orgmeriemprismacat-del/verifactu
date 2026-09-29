-- UC-111 / DEC-23:
-- Resolution of a PENDING_RECOVERY is evidence from an external/accounting
-- workflow. It does NOT create or infer a payment_transaction/factura.
ALTER TABLE novice_promotion_root_refund_recovery
    ADD COLUMN RESOLUTION_ID VARCHAR(100) NULL,
    ADD COLUMN RESOLVED_BY VARCHAR(100) NULL,
    ADD COLUMN RESOLUTION_EVIDENCE_REF VARCHAR(140) NULL,
    ADD UNIQUE KEY uq_novice_recovery_resolution_id (RESOLUTION_ID),
    ADD CONSTRAINT chk_novice_recovery_resolution_evidence CHECK (
        (STATUS = 'PENDING_RECOVERY'
            AND RESOLUTION_ID IS NULL
            AND RESOLVED_BY IS NULL
            AND RESOLUTION_EVIDENCE_REF IS NULL)
        OR (STATUS <> 'PENDING_RECOVERY'
            AND RESOLUTION_ID IS NOT NULL
            AND RESOLVED_BY IS NOT NULL
            AND RESOLUTION_EVIDENCE_REF IS NOT NULL)
    );
