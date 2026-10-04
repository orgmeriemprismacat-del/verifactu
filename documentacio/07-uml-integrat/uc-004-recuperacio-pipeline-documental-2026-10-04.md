# UC-004 · Recuperació selectiva del pipeline documental · 2026-10-04

**Base auditada:** `main@6c8137ff1652ac89a1a81ad18cf79fc4689b1757`  
**Branca:** `audit/uc-004-revalidacio-v2-2026-10-04`  
**Font candidata:** PR #134 · `audit/uc-004-reconciliacio-2026-10-03`

## 1. Objectiu

Determinar quines peces del pipeline documental del PR #134 es poden reutilitzar per UC-004 sense confondre codi candidat amb codi vigent ni recuperar a cegues un PR divergit.

La regla de seguretat és:

> un error de document després del COMMIT fiscal no pot provocar una nova emissió ni un segon número fiscal.

## 2. Què ja existeix al main

El tronc actual ja té infraestructura documental compartida:

- taula `document_job` definida a `2026_09_15_000003_add_functional_audit_control.sql`;
- `factura_documents` com a metadada documental vinculada a `UUID_FACTURA`;
- `fiscal_document_access` per auditar accessos;
- `DocumentRepository` per registrar metadades;
- lectura de metadades des d'`InvoiceQueryService`;
- endpoint intern signat `/api/documents/download.php`;
- storage lector privat i comprovació d'integritat;
- proxy intranet UC-080 per descarregar documents autoritzats.

Per tant, el buit del UC-004 **no és l'esquema ni la descàrrega**.

## 3. Què falta al runtime UC-004

Falten les peces productores:

1. programar un job documental idempotent després del COMMIT fiscal;
2. recuperar el snapshot fiscal immutable de la factura i verificar-ne el hash;
3. reclamar jobs amb lease i `SKIP LOCKED`;
4. reintentar jobs fallits sense reemetre la factura;
5. escriure bytes en storage privat de manera immutable;
6. registrar el `factura_documents.ID` i el hash dels bytes;
7. marcar el job `COMPLETED` només després de verificar storage + metadada;
8. generar incidència en error terminal;
9. disposar d'un renderer fiscal concret i versionat;
10. disposar d'un entrypoint/worker operatiu supervisat.

## 4. Codi candidat del PR #134

El PR #134 conté una implementació candidata de diverses peces:

- `DocumentJobRepository`;
- `InvoiceDocumentSnapshotRepository`;
- `InvoiceBeforePaymentDocumentQueueService`;
- `FiscalDocumentJobProcessor`;
- `PrivateDocumentWriter`;
- `FiscalInvoiceDocumentModelBuilder`;
- `AeatInvoiceQrUrlBuilder`;
- contractes de renderer/storage/PDF;
- proves d'idempotència, lease, stale recovery, integritat de snapshot i storage.

També modifica `DocumentRepository::registerDocument()` perquè retorni l'ID creat i accepti un estat documental explícit.

## 5. Evidència de CI del PR #134

El workflow específic:

- **UC-004 SIF secure flow checks: SUCCESS**.

També:

- **Intranet AO batch checks: SUCCESS**.

Els tres workflows globals amb error acabaven en la mateixa suite de 937 PASS / 6 FAIL. Les sis fallades localitzades eren:

- cinc regressions de packs/transport/privacitat;
- una regressió de `RedsysSignatureValidatorTest`.

No s'ha localitzat una fallada del pipeline documental UC-004 entre aquestes sis.

Això **augmenta la confiança per recuperar peces**, però no converteix #134 en un tall globalment verificat.

## 6. Recuperació recomanada

### Fase D1 — repository i snapshot

Recuperar/reconciliar sobre el main actual:

- `DocumentJobRepository`;
- `InvoiceDocumentSnapshotRepository`;
- proves associades.

Criteris:

- cap dependència de la branca antiga;
- schema actual compatible;
- snapshot basat en `factura_registres.PAYLOAD_JSON` i hash fiscal, no en dades vives llegades;
- CI completa verda.

### Fase D2 — storage i processor

Recuperar:

- contractes de storage/renderer;
- `PrivateDocumentWriter`;
- `FiscalDocumentJobProcessor`;
- gestió de lease/retry/stale;
- incidència en error terminal.

Criteris:

- no exposar paths privats;
- escriptura immutable;
- hash de bytes verificat;
- idempotència davant crash entre storage/metadada/complete.

### Fase D3 — productor UC-004 post-COMMIT

Només després de D1/D2:

- `InvoiceBeforePaymentDocumentQueueService`;
- connexió post-COMMIT des d'`InvoiceBeforePaymentService`.

La factura ja ha d'estar compromesa abans d'intentar la cua documental. Si falla el queue:

- la factura continua emesa;
- es retorna estat documental independent;
- un retry de UC-004 reutilitza el mateix `UUID_FACTURA`.

### Fase D4 — renderer i worker real

Encara cal un renderer concret i un entrypoint de worker desplegable.

No és suficient tenir `FiscalDocumentRendererInterface`.

El renderer ha de produir una representació fiscal versionada a partir del snapshot immutable i ha de quedar cobert per validació visual/normativa.

## 7. Per què no fusionar #134 sencer

No s'ha de fusionar #134 directament perquè:

- està divergit respecte del main vigent;
- conté canvis que avui ja han estat implementats d'una altra manera;
- barreja documentació, adaptador UC-004 i pipeline documental;
- el main ha evolucionat amb UC-001, UC-007, UC-080 i altres components compartits;
- el contracte actual del UC-004 ja és `UC004-V1` i el seu hardening s'ha reconciliat en #166.

La via segura és **port selectiu per responsabilitat + proves verdes sobre main actual**.

## 8. Estat

| Peça | Main actual | #134 candidat | Acció |
| --- | --- | --- | --- |
| schema `document_job` | Sí | usa el mateix model | conservar main |
| `factura_documents` | Sí | ampliació de retorn/status | reconciliar |
| download privat | Sí, UC-080 | no és el gap principal | conservar main |
| job repository PHP | No | Sí | D1 |
| snapshot verificat | No | Sí | D1 |
| queue UC-004 | No | Sí | D3 |
| lease/retry worker service | No | Sí | D2 |
| storage writer immutable | No | Sí | D2 |
| renderer concret | No acreditat | interface/model, no renderer final | D4 |
| worker entrypoint supervisable | No | no acreditat com a runtime final | D4 |

## 9. Criteri de tancament documental

Aquest document no declara implementat el pipeline. Defineix una ruta de recuperació segura i traçable.

UC-004 només podrà marcar **document fiscal per UUID = VERIFICAT** quan D1–D4 estiguin integrades al main vigent, les proves siguin verdes i existeixi evidència E2E de generació, custòdia i descàrrega del mateix `UUID_FACTURA`.
