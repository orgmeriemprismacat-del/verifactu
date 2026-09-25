-- UC-111: an approved transfer is attribution of PREVIOUSLY consumed promotion,
-- not a new debit/grant/application or a new bank payment. Record the
-- replacement final invoice and its already settled amount for audit.
-- Pending reviews remain unissued and cannot be treated as confirmed.
ALTER TABLE novice_promotion_application_transfer
    ADD COLUMN UUID_DESTINATION_FACTURA CHAR(36) NULL,
    ADD COLUMN FINAL_NET_AMOUNT DECIMAL(12,2) NULL,
    ADD COLUMN ORDINARY_NET_BEFORE_PROMOTION DECIMAL(12,2) NULL,
    ADD COLUMN APPROVAL_DECISION_ID VARCHAR(100) NULL,
    ADD COLUMN APPROVED_BY VARCHAR(100) NULL,
    ADD COLUMN APPROVED_AT DATETIME NULL,
    ADD UNIQUE KEY uq_novice_transfer_destination (TO_UUID_OPERATION),
    ADD UNIQUE KEY uq_novice_transfer_final_decision (APPROVAL_DECISION_ID),
    ADD CONSTRAINT fk_novice_transfer_destination_invoice
        FOREIGN KEY (UUID_DESTINATION_FACTURA) REFERENCES factura(UUID_FACTURA),
    ADD CONSTRAINT chk_novice_transfer_confirmation_evidence CHECK (
        (STATUS <> 'CONFIRMED')
        OR (CONFIRMED_AT IS NOT NULL
            AND UUID_DESTINATION_FACTURA IS NOT NULL
            AND FINAL_NET_AMOUNT IS NOT NULL AND FINAL_NET_AMOUNT >= 0
            AND ORDINARY_NET_BEFORE_PROMOTION IS NOT NULL
            AND ORDINARY_NET_BEFORE_PROMOTION >= AMOUNT
            AND FINAL_NET_AMOUNT = ORDINARY_NET_BEFORE_PROMOTION - AMOUNT
            AND APPROVAL_DECISION_ID IS NOT NULL
            AND APPROVED_BY IS NOT NULL AND APPROVED_AT IS NOT NULL)
    );
