-- UC-009: fencing token for fiscal queue ownership.
-- A recovered/reclaimed job must not be completed by an obsolete worker attempt.
ALTER TABLE fiscal_queue
    ADD COLUMN CLAIM_TOKEN CHAR(36) NULL AFTER LOCKED_AT,
    ADD KEY idx_fiscal_queue_claim (STATUS, CLAIM_TOKEN);
