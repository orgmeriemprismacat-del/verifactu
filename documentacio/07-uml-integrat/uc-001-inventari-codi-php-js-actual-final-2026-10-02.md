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
- propagació obligatòria de `uuid_operation_line` des de tots els builders i coverage guard comercial general;
- assembler servidor del snapshot AEAT complet.

## 5. Reconciliació amb `main` i execució — 2026-10-03

- `main`: `b0e8ff7150c5a8b415cc109d298d82f0db1f68df`; head de codi després de HARD-017: `276fb390cd3f4ac7157f831bb544a60e6330d157`.
- Compare: **150 ahead / 44 behind**, merge-base `549d7ef9280df3cd5249340e3785a4bf23a14b78`.
- Solapament directe entre canvis de main i paquet UC-001: `sif/config/sif.php` i `documentacio/07-uml-integrat/README.md`. També s’han inspeccionat canvis adjacents de pack/Redsys perquè són callers.
- La suite del head `88e5c922…` acredita PASS específic UC-001 (960/6 global). Després de HARD-017, el head de codi `276fb390…` executa també la prova nova amb **PASS** i queda **961/6 global**, amb les mateixes cinc fallades pack/UC-015 i una de signatura Redsys.

## 6. Observacions de superfície

1. `alumnes-factura.php` és principalment UC-007 i no emet factura SIF.
2. L’edició/anul·lació llegada del JS general passa per guards i ha de quedar bloquejada al cutover.
3. `alumnes-genera-factura-abans-pagar.php` és UC-004 i reutilitza el nucli sense permetre totals fiscals lliures al client.
4. El generic endpoint UC-001 és servidor-servidor; no s’ha localitzat cap JS públic que li enviï un snapshot fiscal complet de confiança.
