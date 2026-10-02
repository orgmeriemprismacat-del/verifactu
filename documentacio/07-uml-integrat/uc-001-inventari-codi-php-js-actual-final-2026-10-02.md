# UC-001 · Inventari PHP/JS ACTUAL / FINAL — 2026-10-02

## 1. PHP SIF directament implicat

| Fitxer | Responsabilitat ACTUAL | Estat |
| --- | --- | --- |
| `sif/public/api/factures/issue.php` | Entrada genèrica interna; POST signat, rol, policy i error 500 genèric. | Endurit a branca |
| `sif/scripts/preflight-invoice-issue.php` | Readiness del generic endpoint: HMAC, rols, path, emissor no-placeholder, anti-replay i taules requerides; no mostra secrets ni muta dades. | Nou |
| `sif/src/Service/InternalApiAuthenticator.php` | HMAC, timestamp, actor/rol i anti-replay. | Main |
| `sif/src/Service/InternalInvoiceIssueScopeResolver.php` | Rol d'escriptura UC-001. | Nou |
| `sif/src/Service/InternalInvoiceIssuePayloadPolicy.php` | Actor/rol servidor, no Redsys/no UC-004, emissor i `SistemaInformatico` server-owned; guard de snapshot oficial per entorn. | Nou |
| `sif/src/Service/InvoicePayloadValidator.php` | Estructura, exempció, idempotència i coherència monetària. | Endurit |
| `sif/src/Service/InvoiceService.php` | Idempotència, guard AEAT transversal en entorns qualificats, transacció, numeració, factura/reús, payment inicial, projecció d’estats i traça `operational_event`/`sif_audit_event`. | Endurit a branca |
| `sif/src/Repository/InvoiceRepository.php` | Factura/línies/registre/control/cua/relacions, projecció d’estats i vincle relació↔línia unívoc. | Endurit |
| `sif/src/Repository/FiscalSequenceRepository.php` | Numeració per sèrie/any. | Main |
| `sif/src/Aeat/RegistrationSnapshot.php` | Snapshot AEAT condicional. | Parcial |
| `sif/src/Aeat/RecordFactory.php`, `RecordHash.php`, `XmlCodec.php` | Freeze, huella i XML/XSD. | Implementat; qualificació pendent |
| `sif/src/Repository/PaymentRepository.php` | Ledger i estat de cobrament. | Main |
| `sif/src/Repository/OperationalEventRepository.php` | Event funcional append-only de l’emissió/reús. | Integrat UC-001 |
| `sif/src/Repository/SifAuditEventRepository.php` | Auditoria tècnica `request_id/correlation_id`, actor, resultat i hash de sortida. | Nou |
| `sif/src/Repository/OperationLineInvoiceLinkRepository.php` | Writer idempotent `commercial_operation_line -> factura_linia` quan el payload aporta `uuid_operation_line`. | Nou |

## 2. Vies especialitzades que reutilitzen UC-001

- UC-004: `/api/factures/before-payment.php`, command service i coverage repository.
- Redsys: callback, cua/worker i handlers `Redsys*InvoiceService`.
- Manual: `ManualInvoiceService`, serveis curs/pack/grup/regal i builders.
- Rectificació: serveis específics; no s'ha de reduir a una petició lliure al generic endpoint.

## 3. Intranet i JavaScript relacionat

| Superfície | Fitxers localitzats | Observació |
| --- | --- | --- |
| Factures | `js/alumnes-factura-sif.js`, `js/alumnes-factura.js`, `ajax/alumnes/sifFactures.php` | Consulta/gestió; permisos sempre al servidor. |
| Factura abans de pagar | `js/alumnes-genera-factura-abans-pagar.js`, `ajax/alumnes/sifFacturaAbansPagar.php`, `SifInternalApiClient.php` | Flux UC-004 dedicat. |
| Consulta alumne | `js/alumnes-mostrar-alumne-sif.js` i adapters PHP | Lectura/visibilitat, no emissió directa. |

**No s'ha identificat un JS que hagi de construir lliurement el payload fiscal complet per `/api/factures/issue.php`.** La classificació i el càlcul han de romandre a servidor/adaptador de domini.

## 4. FINAL encara absent o incomplet

- guard general de cobertura comercial entre claus diferents;
- propagació obligatòria de `uuid_operation_line` des de tots els builders i coverage guard comercial general;
- assembler servidor del snapshot AEAT complet.
