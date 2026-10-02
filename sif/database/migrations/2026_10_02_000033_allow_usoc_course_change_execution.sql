ALTER TABLE usoc_lifecycle_execution
    DROP CHECK chk_usoc_lifecycle_operation,
    ADD CONSTRAINT chk_usoc_lifecycle_operation
        CHECK (OPERATION IN ('CANCELLATION', 'COURSE_CHANGE'));
