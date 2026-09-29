-- UC-111:
-- RECOVERY_RESOLVED is a state AFTER EXECUTED, therefore it must continue
-- requiring the already confirmed original-JASOM refund evidence.
--
-- Additive corrective migration: do not rewrite 000024/000025 hashes.
ALTER TABLE novice_promotion_root_refund_review
    DROP CHECK chk_novice_origin_refund_execution_evidence,
    ADD CONSTRAINT chk_novice_origin_refund_execution_evidence CHECK (
        (STATUS NOT IN ('EXECUTED','RECOVERY_RESOLVED'))
        OR (
            ORIGIN_REFUND_EVIDENCE_ID IS NOT NULL
            AND ORIGIN_REFUND_CONFIRMED_AT IS NOT NULL
            AND ORIGIN_REFUNDED_AMOUNT IS NOT NULL
            AND ORIGIN_REFUNDED_AMOUNT > 0
        )
    );
