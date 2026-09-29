-- UC-111 / DEC-18:
-- When an APPLIED derived-balance use is moved to another course, it becomes
-- historical TRANSFERRED evidence. It must not remain an active exposure in
-- root-refund planning and it must never restore the parent derived balance.
ALTER TABLE novice_promotion_derived_application
    ADD CONSTRAINT chk_novice_derived_application_transferred CHECK (
        (STATUS = 'TRANSFERRED'
            AND CLOSED_AT IS NOT NULL
            AND REASON_CODE = 'TRANSFERRED_TO_COURSE')
        OR STATUS <> 'TRANSFERRED'
    );

-- A successive transfer closes its predecessor transfer as historical
-- CANCELLED/TRANSFERRED_TO_COURSE. The current exposure is the child transfer,
-- never both predecessor and child.
CREATE INDEX idx_novice_transfer_previous_status
    ON novice_promotion_application_transfer (PREVIOUS_UUID_TRANSFER, STATUS);
