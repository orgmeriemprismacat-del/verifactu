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
| `sif/src/Service/InvoiceService.php` | Idempotència, guard AEAT transversal en entorns qualificats, transacció, numeració, factura/reús, payment inicial amb `movement_date` estable obligatòria, projecció d’estats i traça `operational_event`/`sif_audit_event`. | Endurit a branca |
| `sif/src/Repository/InvoiceRepository.php` | Factura/línies/registre/control/cua/relacions, projecció d’estats i vincle relació↔línia unívoc. | Endurit |
| `sif/src/Repository/FiscalSequenceRepository.php` | Numeració per sèrie/any. | Main |
| `sif/src/Aeat/RegistrationSnapshot.php` | Snapshot AEAT condicional. | Parcial |
| `sif/src/Aeat/RecordFactory.php`, `RecordHash.php`, `XmlCodec.php` | Freeze, huella i XML/XSD. | Implementat; qualificació pendent |
| `sif/src/Repository/PaymentRepository.php` | Ledger i estat de cobrament. | Main |
| `sif/src/Repository/OperationalEventRepository.php` | Event funcional append-only de l’emissió/reús. | Integrat UC-001 |
| `sif/src/Repository/SifAuditEventRepository.php` | Auditoria tècnica `request_id/correlation_id`, actor, resultat i hash de sortida. | Nou |
| `sif/src/Repository/OperationLineInvoiceLinkRepository.php` | Writer idempotent `commercial_operation_line -> factura_linia` quan el payload aporta `uuid_operation_line`. | Nou |
| `sif/src/Repository/CommercialOperationRepository.php` | Lookup d'operació, resolució per `UUID_INTENT` i `linkInvoice()` transaccional/idempotent cap a `UUID_FACTURA`. | Endurit UC-001 |
| `sif/src/Service/PrismaStudentCourseCheckoutService.php` | En Alumne PrisMa crea/reutilitza operació, participant, validació, `commercial_operation_line` trusted i intent Redsys; congela `operation.uuid`/`line_uuid`. | Integrat UC-001/UC-020 |
| `sif/src/Service/LegacyCourseInvoicePayloadBuilder.php` | Propaga opcionalment `operation.uuid` i `operation.line_uuid` del snapshot trusted a `uuid_operation`/`uuid_operation_line`. | Endurit UC-001 |
| `sif/src/Service/RedsysInvoicePayloadBuilder.php` | Des de callback validat resol server-side `DS_ORDER → UUID_INTENT → UUID_OPERATION`; legacy sense operació continua compatible. | Endurit UC-001 |

## 2. Vies especialitzades que reutilitzen UC-001

- UC-004: `/api/factures/before-payment.php`, command service i coverage repository.
- Redsys: callback, cua/worker i handlers `Redsys*InvoiceService`.
- Manual: `ManualInvoiceService`, serveis curs/pack/grup/regal i builders.
- Rectificació: serveis específics; no s'ha de reduir a una petició lliure al generic endpoint.

## 3. Intranet i JavaScript relacionat

| Superfície | Fitxers localitzats | Observació |
| --- | --- | --- |
| Factures | `alumnes-factura.php`, `js/alumnes-factura-sif.js`, `js/alumnes-factura.js`, `ajax/alumnes/sifFactures.php` | SIF read-only + fallback llegat; el JS general redefineix `window.uc007SifSearch` al final de la càrrega. |
| Guards de mutació llegada | `LegacyInvoiceMutationAuthorization.php`, `SifLegacyInvoiceMutationGuard.php`, `ajax/alumnes/guardarDadesFactura_Factures.php`, `ajax/alumnes/anularFactura_Factures.php` | Sessió/rol/same-origin i bloqueig 409 si la factura és governada pel SIF; depèn dels flags de cutover. |
| Factura abans de pagar | `alumnes-genera-factura-abans-pagar.php`, `js/alumnes-genera-factura-abans-pagar.js`, `ajax/alumnes/sifFacturaAbansPagar.php`, `SifInternalApiClient.php` | UC-004: selecció → preview servidor/fingerprint → confirmació → UUID/número. |
| Consulta alumne | `js/alumnes-mostrar-alumne-sif.js` i adapters PHP | Lectura/visibilitat, no emissió directa. |

**No s'ha identificat un JS que hagi de construir lliurement el payload fiscal complet per `/api/factures/issue.php`.** La classificació i el càlcul han de romandre a servidor/adaptador de domini.

## 4. FINAL encara absent o incomplet

- guard general de cobertura comercial entre claus diferents;
- extensió de `commercial_operation`/`commercial_operation_line` a pack, grup, regal, USOC, manual i altres descomptes; el flux Alumne PrisMa ja està materialitzat end-to-end;
- coverage guard comercial general per callers que encara no creen una operació autoritativa;
- assembler servidor del snapshot AEAT complet.

## 5. Reconciliació amb `main` i execució — 2026-10-03

- PR canònica: **#145**, branca `audit/uc-001-reconciled-2026-10-03`, recreada directament des del `main` vigent del tall `b0e8ff7150c5a8b415cc109d298d82f0db1f68df`; l'antiga #114 queda supersedida i tancada sense merge.
- Reconciliació comprovada: **behind 0**; dels 132 fitxers canviats a `main` des del merge-base antic, només `sif/config/sif.php` i `documentacio/07-uml-integrat/README.md` solapaven amb els 30 fitxers inicials UC-001, i tots dos es van fusionar manualment.
- Evidència històrica: el head `88e5c922…` acreditava PASS específic UC-001; HARD-017 passava a `276fb390…` i la suite quedava 961 pass / 6 fail amb fallades alienes a UC-001.
- Evidència nova pendent d'execució: #145 incorpora `linkInvoice`, resolució operació per intent, persistència de `commercial_operation_line` per Alumne PrisMa i prova end-to-end. GitHub Actions estava saturat amb 541 runs en cua; no es declara PASS del head actual fins que finalitzi.

## 6. Observacions de superfície

1. `alumnes-factura.php` és principalment UC-007 i no emet factura SIF.
2. L’edició/anul·lació llegada del JS general passa per guards i ha de quedar bloquejada al cutover.
3. `alumnes-genera-factura-abans-pagar.php` és UC-004 i reutilitza el nucli sense permetre totals fiscals lliures al client.
4. El generic endpoint UC-001 és servidor-servidor; no s’ha localitzat cap JS públic que li enviï un snapshot fiscal complet de confiança.
