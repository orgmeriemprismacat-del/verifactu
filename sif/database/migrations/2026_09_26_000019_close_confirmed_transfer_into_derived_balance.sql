-- UC-111: when the CURRENT course represented by a CONFIRMED transfer is
-- later cancelled and converted into a new derived cancellation balance,
-- preserve why/when that transfer ceased to be the active application.
--
-- CANCELLED + CLOSE_REASON=CONVERTED_TO_DERIVED is historical provenance,
-- NOT a refund of the original JASOM and NOT restoration of its balance.
ALTER TABLE novice_promotion_application_transfer
    ADD COLUMN CLOSED_AT DATETIME NULL,
    ADD COLUMN CLOSE_REASON VARCHAR(80) NULL,
    ADD CONSTRAINT chk_novice_transfer_closed_state CHECK (
        (STATUS = 'CANCELLED' AND CLOSED_AT IS NOT NULL AND CLOSE_REASON IS NOT NULL)
        OR STATUS <> 'CANCELLED'
    );
