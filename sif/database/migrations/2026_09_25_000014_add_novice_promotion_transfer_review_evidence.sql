-- UC-111: preserve the authenticated operator and commercial approval
-- evidence reference on the pre-confirmation transfer review.
-- Nullable on migration so already pending draft rows remain readable; the
-- new stage service requires both fields to be non-empty on every new row.
ALTER TABLE novice_promotion_application_transfer
    ADD COLUMN REVIEW_ACTOR_ID VARCHAR(100) NULL,
    ADD COLUMN POLICY_EVIDENCE_REF VARCHAR(140) NULL;
