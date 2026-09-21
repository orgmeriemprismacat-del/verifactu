-- Execute only as the database administrator after selecting the SIF database.
-- Application accounts receive explicit INSERT/SELECT rights; no UPDATE or DELETE
-- is granted on immutable event, attempt, access or evidence tables.

CREATE ROLE IF NOT EXISTS 'sif_app_writer', 'sif_worker', 'sif_auditor_readonly';

GRANT SELECT, INSERT ON payment_action_event TO 'sif_app_writer';
GRANT SELECT, INSERT ON sif_audit_event TO 'sif_app_writer';
GRANT SELECT, INSERT ON operational_event TO 'sif_app_writer';
GRANT SELECT, INSERT ON billing_profile_history TO 'sif_app_writer';
GRANT SELECT, INSERT ON course_change_event TO 'sif_app_writer';
GRANT SELECT, INSERT ON enrollment_cancellation_event TO 'sif_app_writer';
GRANT SELECT, INSERT ON fiscal_document_access TO 'sif_app_writer';
GRANT SELECT, INSERT ON fiscal_export_access TO 'sif_app_writer';
GRANT SELECT, INSERT, UPDATE ON commercial_operation TO 'sif_app_writer';
GRANT SELECT, INSERT ON commercial_operation_party TO 'sif_app_writer';
GRANT SELECT, INSERT, UPDATE ON discount_validation TO 'sif_app_writer';
GRANT SELECT, INSERT, UPDATE ON payment_link TO 'sif_app_writer';

GRANT SELECT, INSERT ON aeat_submission_attempt TO 'sif_worker';
GRANT SELECT, INSERT ON notification_delivery_attempt TO 'sif_worker';
GRANT SELECT, INSERT ON sif_incident_action TO 'sif_worker';
GRANT SELECT, INSERT ON backup_restore_evidence TO 'sif_worker';

GRANT SELECT ON factura TO 'sif_auditor_readonly';
GRANT SELECT ON factura_linia TO 'sif_auditor_readonly';
GRANT SELECT ON factura_registres TO 'sif_auditor_readonly';
GRANT SELECT ON factura_registre_control TO 'sif_auditor_readonly';
GRANT SELECT ON payment_transaction TO 'sif_auditor_readonly';
GRANT SELECT ON payment_allocation TO 'sif_auditor_readonly';
GRANT SELECT ON payment_action_event TO 'sif_auditor_readonly';
GRANT SELECT ON sif_audit_event TO 'sif_auditor_readonly';
GRANT SELECT ON operational_event TO 'sif_auditor_readonly';
GRANT SELECT ON aeat_submission_attempt TO 'sif_auditor_readonly';
GRANT SELECT ON factura_documents TO 'sif_auditor_readonly';
GRANT SELECT ON fiscal_document_access TO 'sif_auditor_readonly';
GRANT SELECT ON fiscal_export TO 'sif_auditor_readonly';
GRANT SELECT ON fiscal_export_access TO 'sif_auditor_readonly';
GRANT SELECT ON sif_version TO 'sif_auditor_readonly';
GRANT SELECT ON sif_declaration TO 'sif_auditor_readonly';
GRANT SELECT ON commercial_operation TO 'sif_auditor_readonly';
GRANT SELECT ON commercial_operation_party TO 'sif_auditor_readonly';
GRANT SELECT ON discount_validation TO 'sif_auditor_readonly';
GRANT SELECT ON payment_link TO 'sif_auditor_readonly';

-- Assign roles with GRANT role TO user only after the real service accounts have
-- been created. Keep those account names and credentials outside this repository.
