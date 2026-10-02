# UC-001 · Auditoria i traçabilitat — 2026-10-02

**Base de reconciliació:** `main` `f7fa0822f82be96e842d9f2d031e643ab07f617c`.

## 1. Resultat executiu

| Bloc | Documentat | Implementat després d'aquesta branca | Inspecció | Execució |
| --- | --- | --- | --- | --- |
| Idempotència mateixa clau/payload | Sí | Sí | Sí | Pendent CI |
| Clau idempotent no buida/compatible SQL | Implícit | Sí | Sí | Pendent CI |
| Autenticació/rol a `issue.php` | Sí | Sí | Sí | Pendent CI |
| `created_by` no suplantable al generic endpoint | Sí | Sí | Sí | Pendent CI |
| Evitar CHARGE Redsys fabricat al generic endpoint | Sí per arquitectura | Sí | Sí | Pendent CI |
| Coherència capçalera↔línies | Sí | Sí | Sí | Pendent CI |
| `fact_rels.ID_FACTURA_LINIA` | Esquema sí | Sí si origen unívoc | Sí | Pendent CI |
| Cobertura entre claus diferents | Sí | Parcial UC-004; no general | Sí | Pendent |
| `commercial_operation` obligatòria | Sí/esquema | No al nucli UC-001 | Sí | Pendent |
| Events d'auditoria funcionals | Sí | No complet | Sí | Pendent |
| Snapshot AEAT oficial automàtic | Sí | No a tots els canals | Sí | Pendent |
| Historial AEAT per intent | Sí | Parcial/transversal | Sí | Pendent |
| Resposta amb estats fiscal/econòmic/documental | Sí | No completa | Sí | Pendent |

## 2. Resoltes o endurides en aquesta branca

1. El generic endpoint reutilitza `InternalApiAuthenticator`, anti-replay i rol d'escriptura.
2. `created_by` es deriva de l'actor autenticat.
3. `method/source_channel=REDSYS` queda bloquejat al generic endpoint; Redsys conserva callback + worker.
4. `emesa_abans_cobrament` queda bloquejat al generic endpoint; UC-004 conserva el seu endpoint amb coverage repository.
5. Amb `aeat_fields`, `ObligadoEmision` es deriva de la configuració servidor.
6. Clau idempotent buida o >100 caràcters queda bloquejada abans de BD.
7. Es validen suma d'import base, base imposable, IVA i total entre capçalera i línies.
8. `fact_rels.ID_FACTURA_LINIA` s'emplena quan l'origen identifica una única línia.

## 3. Pendents deliberadament

No s'han modificat sense contracte suficient:

- any fiscal de curs/pack/grup versus any acadèmic;
- combinacions exactes de sèrie/tipus per cada variant;
- generació completa d'`aeat_fields` pels builders comercials i transició de cadena interna a oficial;
- cobertura comercial general entre dues claus diferents;
- `commercial_operation` i `operation_line_invoice_link` obligatoris;
- `operational_event`, `sif_audit_event`, `factura_registre_control` i correlació;
- fencing de workers, resultat remot AEAT incert i `aeat_submission_attempt`;
- postcondició econòmica del reús quan falta el payment que figurava a la petició original;
- resposta enriquida amb estats AEAT/cobrament/document.

## 4. Proves incorporades

- `InvoicePayloadValidatorTest`: clau buida, clau massa llarga i totals incoherents.
- `InternalInvoiceIssueScopeResolverTest`: rol permès, denegat i fail-closed.
- `InternalInvoiceIssuePayloadPolicyTest`: actor/emissor servidor, bloqueig Redsys i UC-004.
- `InvoiceIssueHttpEndpointTest`: body cru signat i frontera interna.
- `IssueInvoiceTest`: `fact_rels.ID_FACTURA_LINIA` coincideix amb la línia fiscal d'origen.

## 5. Criteri de tancament

Les correccions només passen de **implementades/verificades per inspecció** a **verificades en execució** quan la suite MySQL i els checks del PR siguin verds.
