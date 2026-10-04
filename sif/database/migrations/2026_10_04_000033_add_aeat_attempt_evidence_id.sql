-- UC-009: structured link between a local submission attempt and its private AEAT evidence bundle.
-- Existing attempts remain NULL; no heuristic backfill is performed.
ALTER TABLE aeat_submission_attempt
    ADD COLUMN EVIDENCE_ID VARCHAR(64) NULL AFTER REQUEST_HASH,
    ADD KEY idx_aeat_attempt_evidence (EVIDENCE_ID);
