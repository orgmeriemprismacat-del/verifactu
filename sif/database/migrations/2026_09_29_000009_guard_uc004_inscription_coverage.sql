-- UC-004: serialize and persist the inscription origins claimed by
-- invoice-before-payment operations without imposing a global one-invoice-per-
-- inscription rule on every invoice flow.
--
-- A different idempotency key cannot claim the same INSCRIPCIO origin twice
-- through the UC-004 service. The claim is inserted in the same transaction as
-- the fiscal graph, so a conflict rolls the invoice, sequence, chain and queue
-- back together.
--
-- This table is deliberately UC-004-specific. Cross-channel business rules
-- (split payer, USOC, group/entity participation, rectifications, etc.) still
-- require their own coverage classifier and must not be encoded as a global
-- UNIQUE on fact_rels.
--
-- Existing SIF invoices already marked EMESA_ABANS_COBRAMENT are backfilled
-- from their INSCRIPCIO/ORIGIN fact_rels. If historical UC-004 data contains
-- more than one invoice claiming the same inscription, the UNIQUE constraint
-- makes the INSERT fail closed. Reconcile that fiscal conflict explicitly
-- before marking this migration as applied.

CREATE TABLE IF NOT EXISTS invoice_before_payment_coverage (
    ID BIGINT AUTO_INCREMENT PRIMARY KEY,
    SOURCE_TYPE VARCHAR(30) NOT NULL,
    SOURCE_ID BIGINT NOT NULL,
    UUID_FACTURA CHAR(36) NOT NULL,
    IDEMPOTENCY_KEY VARCHAR(100) NOT NULL,
    CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_invoice_before_payment_source (SOURCE_TYPE, SOURCE_ID),
    KEY idx_invoice_before_payment_uuid (UUID_FACTURA),
    KEY idx_invoice_before_payment_idempotency (IDEMPOTENCY_KEY),
    CONSTRAINT fk_invoice_before_payment_factura
        FOREIGN KEY (UUID_FACTURA) REFERENCES factura(UUID_FACTURA)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO invoice_before_payment_coverage (
    SOURCE_TYPE,
    SOURCE_ID,
    UUID_FACTURA,
    IDEMPOTENCY_KEY
)
SELECT
    'INSCRIPCIO',
    fr.SOURCE_ID,
    f.UUID_FACTURA,
    f.IDEMPOTENCY_KEY
FROM factura AS f
INNER JOIN fact_rels AS fr
    ON fr.UUID_FACTURA = f.UUID_FACTURA
WHERE f.EMESA_ABANS_COBRAMENT = 1
  AND fr.SOURCE_TYPE = 'INSCRIPCIO'
  AND fr.RELATION_TYPE = 'ORIGIN'
  AND fr.SOURCE_ID IS NOT NULL;
