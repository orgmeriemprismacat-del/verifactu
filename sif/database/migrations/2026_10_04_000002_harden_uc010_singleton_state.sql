-- UC-010 · Hardening addicional del singleton d'activació.
-- Migració additiva: conserva les migracions UC-010 anteriors.
--
-- Objectius:
--  * migrar de manera segura una ACTIVE preexistent al pointer singleton;
--  * impedir files singleton amb ID diferent d'1;
--  * impedir DELETE directe del pointer de governança.

UPDATE sif_version_state AS state_row
JOIN (
    SELECT MIN(UUID_VERSION) AS UUID_VERSION, COUNT(*) AS ACTIVE_COUNT
    FROM sif_version
    WHERE STATUS = 'ACTIVE'
) AS active_state
    ON active_state.ACTIVE_COUNT = 1
SET state_row.ACTIVE_UUID_VERSION = active_state.UUID_VERSION
WHERE state_row.ID = 1
  AND state_row.ACTIVE_UUID_VERSION IS NULL;

ALTER TABLE sif_version_state
    ADD CONSTRAINT chk_sif_version_state_singleton_uc010
        CHECK (ID = 1);

DROP TRIGGER IF EXISTS trg_sif_version_state_no_delete;
CREATE TRIGGER trg_sif_version_state_no_delete
BEFORE DELETE ON sif_version_state
FOR EACH ROW
SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'sif_version_state singleton cannot be deleted';
