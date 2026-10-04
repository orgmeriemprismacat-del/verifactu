-- UC-010 · Hardening de persistència per governança de versió.
-- Migració additiva: no modifica 2026_10_03_000001_add_uc010_version_governance.sql.
--
-- Garanties:
--  * com a màxim una fila sif_version amb STATUS='ACTIVE', fins i tot amb SQL directe;
--  * estats UC-010 de sif_version i journal limitats al contracte documentat;
--  * sif_version_activation és append-only també a nivell de base de dades.

ALTER TABLE sif_version
    ADD COLUMN ACTIVE_UNIQUE_GUARD TINYINT
        GENERATED ALWAYS AS (
            CASE WHEN STATUS = 'ACTIVE' THEN 1 ELSE NULL END
        ) STORED,
    ADD UNIQUE KEY uq_sif_version_single_active (ACTIVE_UNIQUE_GUARD),
    ADD CONSTRAINT chk_sif_version_status_uc010
        CHECK (STATUS IN ('DRAFT', 'ACTIVE', 'SUPERSEDED'));

ALTER TABLE sif_version_activation
    ADD CONSTRAINT chk_sif_version_activation_status_uc010
        CHECK (STATUS = 'ACTIVATED');

DROP TRIGGER IF EXISTS trg_sif_version_activation_no_update;
CREATE TRIGGER trg_sif_version_activation_no_update
BEFORE UPDATE ON sif_version_activation
FOR EACH ROW
SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'sif_version_activation is immutable';

DROP TRIGGER IF EXISTS trg_sif_version_activation_no_delete;
CREATE TRIGGER trg_sif_version_activation_no_delete
BEFORE DELETE ON sif_version_activation
FOR EACH ROW
SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'sif_version_activation is immutable';
