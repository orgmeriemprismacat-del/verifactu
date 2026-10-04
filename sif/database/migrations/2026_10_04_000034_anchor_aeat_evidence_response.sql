-- UC-009: DB anchor for the protected AEAT response bundle.
-- Automatic evidence reconciliation requires these values to match the verified private files.
ALTER TABLE aeat_submission_attempt
    ADD COLUMN EVIDENCE_RESPONSE_SHA256 CHAR(64) NULL AFTER EVIDENCE_ID,
    ADD COLUMN EVIDENCE_HTTP_STATUS INT NULL AFTER EVIDENCE_RESPONSE_SHA256;
