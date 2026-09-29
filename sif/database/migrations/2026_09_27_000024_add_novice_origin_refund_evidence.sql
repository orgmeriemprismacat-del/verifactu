-- UC-111 / DEC-23:
-- Commercial cancellation/recovery items must execute AFTER the actual JASOM
-- refund is confirmed, while the root remains frozen in REFUND_REVIEW.
ALTER TABLE novice_promotion_root_refund_review
    ADD COLUMN ORIGIN_REFUND_EVIDENCE_ID VARCHAR(140) NULL,
    ADD COLUMN ORIGIN_REFUND_CONFIRMED_AT DATETIME NULL,
    ADD COLUMN ORIGIN_REFUNDED_AMOUNT DECIMAL(12,2) NULL,
    ADD UNIQUE KEY uq_novice_origin_refund_evidence (ORIGIN_REFUND_EVIDENCE_ID),
    ADD CONSTRAINT chk_novice_origin_refund_execution_evidence CHECK (
        (STATUS <> 'EXECUTED')
        OR (
            ORIGIN_REFUND_EVIDENCE_ID IS NOT NULL
            AND ORIGIN_REFUND_CONFIRMED_AT IS NOT NULL
            AND ORIGIN_REFUNDED_AMOUNT IS NOT NULL
            AND ORIGIN_REFUNDED_AMOUNT > 0
        )
    );
