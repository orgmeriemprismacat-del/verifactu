ALTER TABLE usoc_validation_decision
    ADD COLUMN ACTIVE_ID_INSC BIGINT
        GENERATED ALWAYS AS (
            CASE WHEN STATE = 'REQUESTED' THEN ID_INSC ELSE NULL END
        ) STORED,
    ADD UNIQUE KEY uq_usoc_validation_active_inscription (ACTIVE_ID_INSC);
