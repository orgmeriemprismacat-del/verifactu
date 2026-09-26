-- UC-111 / DEC-23:
-- Persist the handoff between FINAL business approval and the external bank
-- refund. The promotion stays frozen in commercial_entitlement.REFUND_REVIEW.
--
-- APPROVED_WAITING_REFUND is NOT execution and contains no bank-refund proof.
ALTER TABLE novice_promotion_root_refund_review
    DROP CHECK chk_novice_root_refund_status,
    DROP CHECK chk_novice_root_refund_decision,
    ADD CONSTRAINT chk_novice_root_refund_status CHECK (
        STATUS IN (
            'PENDING_APPROVAL',
            'APPROVED_WAITING_REFUND',
            'REJECTED',
            'CANCELLED',
            'EXECUTED'
        )
    ),
    ADD CONSTRAINT chk_novice_root_refund_decision CHECK (
        (
            STATUS = 'PENDING_APPROVAL'
            AND DECIDED_BY IS NULL
            AND DECISION_REASON IS NULL
            AND DECIDED_AT IS NULL
            AND EXECUTED_AT IS NULL
            AND APPROVAL_DECISION_ID IS NULL
            AND APPROVAL_EVIDENCE_REF IS NULL
            AND ORIGIN_REFUND_EVIDENCE_ID IS NULL
            AND ORIGIN_REFUND_CONFIRMED_AT IS NULL
            AND ORIGIN_REFUNDED_AMOUNT IS NULL
        )
        OR (
            STATUS = 'APPROVED_WAITING_REFUND'
            AND DECIDED_BY IS NOT NULL
            AND DECISION_REASON = 'APPROVED_ROOT_REFUND'
            AND DECIDED_AT IS NOT NULL
            AND EXECUTED_AT IS NULL
            AND APPROVAL_DECISION_ID IS NOT NULL
            AND APPROVAL_EVIDENCE_REF IS NOT NULL
            AND ORIGIN_REFUND_EVIDENCE_ID IS NULL
            AND ORIGIN_REFUND_CONFIRMED_AT IS NULL
            AND ORIGIN_REFUNDED_AMOUNT IS NULL
        )
        OR (
            STATUS IN ('REJECTED','CANCELLED')
            AND DECIDED_BY IS NOT NULL
            AND DECISION_REASON IS NOT NULL
            AND DECIDED_AT IS NOT NULL
            AND EXECUTED_AT IS NULL
            AND ORIGIN_REFUND_EVIDENCE_ID IS NULL
            AND ORIGIN_REFUND_CONFIRMED_AT IS NULL
            AND ORIGIN_REFUNDED_AMOUNT IS NULL
        )
        OR (
            STATUS = 'EXECUTED'
            AND DECIDED_BY IS NOT NULL
            AND DECISION_REASON = 'APPROVED_ROOT_REFUND'
            AND DECIDED_AT IS NOT NULL
            AND EXECUTED_AT IS NOT NULL
            AND APPROVAL_DECISION_ID IS NOT NULL
            AND APPROVAL_EVIDENCE_REF IS NOT NULL
            AND ORIGIN_REFUND_EVIDENCE_ID IS NOT NULL
            AND ORIGIN_REFUND_CONFIRMED_AT IS NOT NULL
            AND ORIGIN_REFUNDED_AMOUNT IS NOT NULL
            AND ORIGIN_REFUNDED_AMOUNT > 0
        )
    );

CREATE INDEX idx_novice_root_refund_handoff
    ON novice_promotion_root_refund_review (STATUS, DECIDED_AT, REQUESTED_AT);
