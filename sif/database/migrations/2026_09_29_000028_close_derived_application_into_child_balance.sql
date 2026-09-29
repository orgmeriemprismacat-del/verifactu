-- UC-111: when an APPLIED use of a cancellation-derived promotional balance
-- is itself cancelled and converted into another derived balance, the source
-- application becomes historical. It must not remain an active exposure and
-- it must not restore its parent balance.
--
-- Additive migration; do not rewrite 000013/000017/000020 hashes.
ALTER TABLE novice_promotion_derived_application
    ADD CONSTRAINT chk_novice_derived_application_converted CHECK (
        (STATUS = 'CONVERTED_TO_DERIVED'
            AND CLOSED_AT IS NOT NULL
            AND REASON_CODE = 'CONVERTED_TO_DERIVED')
        OR STATUS <> 'CONVERTED_TO_DERIVED'
    );
