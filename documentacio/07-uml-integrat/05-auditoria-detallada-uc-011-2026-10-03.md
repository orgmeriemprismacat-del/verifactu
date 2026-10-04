# Auditoria detallada UC-011 · Importar factura històrica

**Data inicial:** 03/10/2026  
**Reconciliació i continuació:** 04/10/2026  
**Repositori:** orgmeriemprismacat-del/verifactu  
**Branca:** `audit/uc-011-2026-10-03`  
**PR:** #132

## 1. Abast revisat

S'han revisat:

- fitxa funcional;
- UML integrat;
- classes, seqüències i activitats ACTUAL/FINAL;
- PHP real;
- scripts CLI;
- proves unitàries/integració;
- esquema MySQL;
- idempotència;
- numeració fiscal;
- traçabilitat;
- frontend/backend llegat relacionat amb consulta, edició, anul·lació i regeneració;
- cut-over via `SifLegacyInvoiceMutationGuard`.

No existeix una UI/JS que executi UC-011. La migració és CLI.

## 2. Troballes corregides

### A-011-01 · Reús idempotent sense comparar contingut

**Abans:** mateixa clau → reuse silenciós.  
**Ara:** hash canònic sobre dades persistides; diferència → 409.

### A-011-02 · Data històrica inventable

**Abans:** podia caure a data actual.  
**Ara:** `issue_date/data_emissio` obligatòria.

### A-011-03 · Components de numeració contradictoris

**Ara:** sèrie/any/seq explícits han de coincidir amb `NUM_VISIBLE`.

### A-011-04 · Estat manipulable

**Ara:** sempre `HISTORICAL/NO_VERIFACTU`.

### A-011-05 · Visibilitat permissiva

**Ara:** `VISIBLE_ALUMNE=0` per defecte.

### A-011-06 · Camps històrics perduts

**Ara:** emissor, descripció d'operació i fiscalitat ampliada es conserven quan venen de la font.

### A-011-07 · Validació estructural insuficient

**Ara:** billing/imports/línies/dates/tipus/relacions/fiscalitat es validen abans de persistir.

### A-011-08 · Col·lisió de número detectada massa tard

**Ara:** `HistoricalInvoiceMigrationPreflight` bloqueja amb 409 un número ja assignat a una altra clau.

### A-011-09 · Risc de futura col·lisió amb fiscal_sequence

**Ara:** si existeix seqüència activa i el número històric és superior a `LAST_NUM`, el preflight bloqueja amb 409. No modifica la seqüència.

### A-011-10 · Sense traça funcional

**Ara:** import/reuse reeixit escriu `operational_event` i `sif_audit_event` dins la mateixa transacció.

## 3. PHP real

Flux FINAL:

`process-historical-invoice-migration.php`
→ `HistoricalInvoiceMigrationService`
→ `HistoricalInvoicePayloadBuilder`
→ `HistoricalInvoiceMigrationPreflight`
→ `HistoricalInvoiceMigrationRepository`
→ `OperationalEventRepository`
→ `SifAuditEventRepository`
→ MySQL.

No intervé `InvoiceService`, ni `PaymentService`, ni cua AEAT.

## 4. JavaScript real

`alumnes-factura-sif.js`:

- consulta SIF;
- mostra factures;
- fa fallback al llegat.

No importa factures històriques.

Els endpoints llegats de mutació/regeneració estan protegits per `SifLegacyInvoiceMutationGuard` quan els flags corresponents són actius.

## 5. Documents

`HistoricalInvoiceMigrationRepository::insertDocument()` només persisteix metadata. No:

- llegeix bytes;
- verifica existència física;
- recalcula SHA-256;
- copia a storage immutable.

`descarregaFactura.php` pot regenerar un PDF amb dades vives; això no és prova dels bytes originals.

## 6. Numeració i multiemissor

El preflight millora el comportament però no canvia el model.

Continuen existint:

- `UNIQUE(NUM_VISIBLE)`;
- `UNIQUE(TIPUS_SERIE,ANY_FACT,NUM_SEQ)`.

Per tant, dos emissors diferents amb el mateix número històric no poden coexistir fidelment en `factura` sense una decisió de model.

No s'ha renumerat cap factura ni s'ha avançat `fiscal_sequence`.

## 7. Proves

### Existents/ampliades

- import/reuse;
- no registre fiscal;
- no cua;
- no seqüència;
- hash idempotent;
- aliases;
- camps fiscals;
- visibilitat.

### Noves governança

- events operatius/auditoria;
- número ocupat;
- seqüència activa incompatible;
- seqüència ja avançada;
- retry idempotent després d'activar seqüència.

### Verificació local

`php -l` passa per:

- `HistoricalInvoiceMigrationService.php`;
- `HistoricalInvoiceMigrationPreflight.php`;
- `HistoricalInvoiceMigrationGovernanceTest.php`.

La verificació MySQL completa del head actual depèn dels workflows del PR.

## 7.1. Continuació 04/10 · inventari, custòdia i cut-over

### A-011-11 · Inventari/reconciliació de lot — implementat

S'ha creat `HistoricalInvoiceInventoryService` i el CLI read-only `inventory-historical-invoices.php`. Contrasta `web.factures` amb `fact_rels/factura`, detecta omissions, divergències i ambigüitats, agrega per any/sèrie i pot verificar documents físics sense exposar NIF/CIF ni paths interns.

### A-011-12 · Custòdia d'originals — implementada en no-production

`HistoricalInvoiceDocumentCustodyService` copia PDF/XML a `SIF_DOCUMENT_ROOT`, calcula/verifica SHA-256, usa `STORAGE_REF`, preserva el path llegat i audita l'operació. La descàrrega real prioritza la còpia privada.

### A-011-13 · Cut-over llegat — cobert per proves

S'han afegit proves del `SifLegacyInvoiceMutationGuard`: 409 quan existeix factura SIF, 503 si UC-007/protecció no pot verificar l'estat, i comprovació que el guard s'executa abans de guardar, anul·lar o regenerar.

### A-011-14 · Preflight de migració controlada — implementat

`preflight-historical-invoices.php` comprova la font, imports existents, storage i flags de cut-over sense fer cap mutació.

## 8. Estat documentat / implementat / verificat / pendent

| Bloc | Documentat | Implementat | Verificat | Pendent |
| --- | --- | --- | --- | --- |
| Builder | sí | sí | tests/lint | — |
| Repository | sí | sí | tests | — |
| Idempotència material | sí | sí | tests | — |
| Preflight número | sí | sí | lint + test escrit | CI |
| Preflight fiscal_sequence | sí | sí | lint + tests escrits | CI |
| operational_event | sí | sí | lint + test escrit | CI |
| sif_audit_event | sí | sí | lint + test escrit | CI |
| JS executor | sí: no existeix | no necessari per CLI | contrastat | — |
| Cut-over llegat | sí | guard existent | codi contrastat | sif_pre |
| Custòdia original | sí | sí no-production | proves/codi | executar sif_pre + evidència |
| Inventari lot | sí | sí read-only | proves/codi | executar lot real |
| Cut-over llegat | sí | sí + tests | cobertura codi | prova entorn sif_pre |
| Multiemissor | sí | no | risc contrastat | decisió model |
| Canal productiu | sí | no | production bloquejada | implementar |

## 9. Criteri de tancament

### Es pot tancar

- auditoria documental;
- coherència PHP/UML;
- importació unitària no-production, subjecte al CI final.

### No es pot tancar encara

- migració massiva productiva;
- multiemissor;
- execució/evidència real d'inventari, custòdia i cut-over a `sif_pre`;
- canal productiu autoritzat;
- decisió final sobre sèrie↔tipus i toleràncies de dades històriques.

## 10. Navegació

[Fitxa](../06-fitxes-funcionals/uc-011.md) · [UML](uc-011-importar-factura-historica.md) · [Classes](uc-011-classes-actual-final.md) · [Seqüències](uc-011-sequencies-actual-final.md) · [Activitats](uc-011-activitats-actual-final.md) · [Traçabilitat](uc-011-tracabilitat-implementacio.md) · [Governança](uc-011-governanca-preflight-auditoria.md)
