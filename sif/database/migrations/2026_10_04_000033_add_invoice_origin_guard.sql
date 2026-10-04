-- UC-003 / UC-004: transaction-scoped serialization key for invoice origins.
--
-- The previous cross-flow guard relied on SELECT ... FOR UPDATE over fact_rels.
-- When an INSCRIPCIO origin did not exist yet, mutual exclusion depended on
-- InnoDB gap-lock behaviour and therefore on the session isolation level.
--
-- This table materialises one durable mutex row per business origin. Both
-- UC-004 invoice-before-payment and UC-003 Redsys issuance upsert/lock the same
-- (SOURCE_TYPE, SOURCE_ID) key before deciding whether an invoice may be
-- created. The row is operational coordination state only; it is not a fiscal
-- record and does not claim that the origin has already been invoiced.

CREATE TABLE IF NOT EXISTS invoice_origin_guard (
    SOURCE_TYPE VARCHAR(30) NOT NULL,
    SOURCE_ID BIGINT NOT NULL,
    UPDATED_AT TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (SOURCE_TYPE, SOURCE_ID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
