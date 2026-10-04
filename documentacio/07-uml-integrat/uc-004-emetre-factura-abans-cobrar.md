# UC-004 · Emetre una factura abans de cobrar — fitxa i UML integrats

**Revisió:** 2026-10-04  
**Base auditada:** `main@04a4847037735df3892008a62b108ebe77c98941`  
**Estat:** **PARCIAL AVANÇAT / CODI VERSIONAT**. Pantalla, bridge segur, endpoint SIF, reconstrucció autoritativa, idempotència, cobertura UC-004 i auditoria transaccional estan implementats. Resten `aeat_fields` oficials per entorns qualificats, document SIF per UUID, cobertura transversal, cobrament posterior E2E i evidència de preproducció.

> Aquesta síntesi no substitueix els fitxers específics de classes, seqüències, activitats i auditoria; els enllaça i fixa el contracte funcional vigent.

## 1. Objectiu funcional

UC-004 emet una **factura fiscal real abans que existeixi el cobrament**.

Garanties obligatòries:

- la factura queda `ISSUED` i econòmicament `PENDING`;
- no es crea `payment_transaction` ni `payment_allocation` inicial;
- el cobrament posterior s'aplica al **mateix `UUID_FACTURA`**;
- un retry equivalent reutilitza UUID/número;
- un payload divergent o una segona operació UC-004 sobre el mateix origen es rebutja;
- un error documental o AEAT no autoritza una nova emissió.

## 2. Fronteres ACTUAL / FINAL

### 2.1 ACTUAL històric llegat

El circuit original:

1. cercava inscripcions per NIF/NIE;
2. construïa selecció/import/conceptes al navegador;
3. identificava el receptor per text;
4. enviava el POST a `generaFacturaElectronica_Factures.php`;
5. `Intranet::generarFacturaElectronica_Alumnes()` numerava i inseria a la BD llegada;
6. el PDF es regenerava des de dades vives i s'eliminava temporalment per path.

Riscos observats: autoritat del DOM, numeració llegada, absència d'idempotència SIF, escriptura parcial, document no immutable i endpoint mutador invocable.

### 2.2 ACTUAL versionat després de l'auditoria

La branca auditada implementa:

```text
navegador
  → sifFacturaAbansPagar.php
    → sessió + permís d'edició + CSRF
      → SifInternalApiClient
        → HMAC + request_id + actor + rols
          → /api/factures/before-payment.php
            → anti-replay + rol SIF
              → InvoiceBeforePaymentCommandService
                → rellegir selecció/receptor/imports
                  → preview fingerprint
                  → confirm
                    → InvoiceBeforePaymentService
                      → InvoiceService
```

El vell `generaFacturaElectronica_Factures.php` queda **410 Gone** i ja no carrega dependències de mutació.

### 2.3 FINAL operatiu

El FINAL no necessita una segona arquitectura. Necessita completar:

- assembler AEAT server-side complet per UC-004 en PREPROD/PROD;
- circuit documental SIF per UUID i renderer fiscal versionat PDF/QR/XML;
- validació visual/normativa del document;
- classificador de cobertura entre canals/pagadors;
- cobrament posterior real sobre el mateix UUID;
- E2E/preproducció amb evidència;
- configuració/desplegament i runbook operatiu.

## 3. Contracte de seguretat

### Intranet

`SifInvoiceBeforePaymentAccess`:

- refresca rols;
- exigeix permís d'edició de la pàgina;
- genera/valida token CSRF amb TTL.

`sifFacturaAbansPagar.php`:

- només POST JSON;
- valida sessió;
- normalitza/deduplica `inscription_ids`;
- valida `entity_id`;
- valida `expected_fingerprint` en confirmació;
- no exposa el secret intern al navegador.

### SIF intern

`SifInternalApiClient` i `InternalApiAuthenticator` protegeixen:

- HTTPS;
- key id;
- timestamp;
- `request_id`;
- actor;
- rols;
- hash del cos exacte;
- HMAC SHA-256;
- anti-replay persistent.

El contracte UC-004 queda versionat com `UC004-V1`.

## 4. Reconstrucció autoritativa

La UI només aporta identificadors i dades no fiscals auxiliars.

El servidor rellegeix:

- inscripcions;
- curs/edició;
- `A_PAGAR`;
- receptor per `entity_id`;
- responsable fiscal actiu;
- línies/conceptes;
- totals;
- relacions `INSCRIPCIO/ORIGIN`.

`preview` calcula fingerprint. `confirm` rellegeix i exigeix coincidència abans d'emetre.

## 5. Idempotència i cobertura

`PayloadIdempotencyValidator` conserva un SHA-256 canònic del payload.

Resultats:

| Cas | Resultat |
| --- | --- |
| mateixa key + mateix payload | reutilitza UUID/número |
| mateixa key + payload divergent | 409 |
| key diferent + mateixa inscripció UC-004 | 409 |
| cobertura ja existent durant preview | 409 abans de confirmar |
| carrera després del preview | UNIQUE transaccional bloqueja la segona emissió |

La taula `invoice_before_payment_coverage` és específica d'UC-004. **No** equival a una política global d'una única factura per inscripció en tots els canals.

## 6. Persistència fiscal

L'emissió nova crea dins del tall transaccional:

- `factura`;
- `factura_linia`;
- `factura_registres`;
- `fiscal_chain_state`;
- `fiscal_queue`;
- `fact_rels`;
- claim a `invoice_before_payment_coverage`;
- `operational_event = ISSUE_INVOICE` + `sif_audit_event` dins la mateixa transacció.

La correlació operacional és estable i derivada de la clau idempotent sense convertir-la en identificador fiscal alternatiu.

## 7. Document fiscal per UUID

**PENDENT al tronc actual.** La pantalla segura ja ha deixat d'usar el PDF temporal llegat com a resultat de l'emissió, però el `main` no conté encara el circuit UC-004 de job/worker/snapshot/storage per UUID. Aquest codi existia al PR #134, que no està fusionat i no tenia tots els checks globals verds.

El FINAL ha de garantir job idempotent per UUID+versió, snapshot fiscal immutable, storage privat verificat, estat `READY/PENDING/ERROR`, QR/XML/PDF versionats i reintent post-COMMIT sense reemetre la factura.

## 8. Cobrament posterior

El cobrament és UC-002/UC-022 segons el canal.

```text
UUID_FACTURA existent
  → PaymentService/registerPayment
    → payment_transaction
    → payment_allocation
    → recalcular ESTAT_COBRAMENT
```

No es torna a executar UC-004 i no es crea un segon registre fiscal `ALTA`.

## 9. ACTUAL/FINAL per artefacte

| Artefacte | Estat |
| --- | --- |
| Fitxa funcional | completa, reconciliada |
| Cas d'ús ACTUAL/FINAL | completa, revisió 04/10 |
| Classes ACTUAL/FINAL | completa |
| Seqüències ACTUAL/FINAL | completa |
| Activitats | **14 diagrames**, A004-P00…P06 ACTUAL/FINAL |
| Inventari PHP/JS | complet |
| UI → bridge SIF | implementat |
| CSRF/HMAC/anti-replay/rol | implementat |
| Preview/confirm fingerprint | implementat |
| Emissió/idempotència | implementat |
| Cobertura UC-004 | implementada |
| Auditoria operacional | implementada i atòmica (`ISSUE_INVOICE` + `sif_audit_event`) |
| Mutador llegat | retirat amb 410 |
| Document job/worker/storage | **pendent al main** |
| Renderer PDF/QR/XML | pendent |
| Cobertura transversal | pendent |
| Cobrament posterior E2E | pendent |
| Preproducció | pendent d'evidència |

## 10. Diagrames i traçabilitat

- [Cas d'ús ACTUAL/FINAL](uc-004-cas-us-actual-final.md)
- [Classes ACTUAL/FINAL](uc-004-classes-actual-final.md)
- [Seqüències ACTUAL/FINAL](uc-004-sequencies-actual-final.md)
- [Activitats ACTUAL/FINAL per pàgina/apartat](uc-004-activitats-actual-final.md)
- [Auditoria i mancances](uc-004-auditoria-tracabilitat-mancances.md)
- [Inventari](uc-004-inventari-artefactes.md)
- [Fitxa funcional](../06-fitxes-funcionals/uc-004.md)
- [Tancament d'auditoria 2026-10-04](uc-004-tancament-auditoria-2026-10-04.md)

## 11. Criteri de tancament

Es pot considerar **documentalment complet** quan els fitxers anteriors estan coherents amb el codi real.

Es pot considerar **implementat** quan el codi versionat conté les garanties descrites.

Es pot considerar **verificat en CI** només quan els checks del HEAD reconciliat passen.

Es pot considerar **operativament verificat** només després de:

1. assembler `aeat_fields` oficial UC-004 en entorns qualificats;
2. circuit documental SIF per UUID + renderer fiscal concret;
3. configuració efectiva;
4. preflight/migracions sobre `sif_test` / preproducció;
5. E2E pantalla → SIF → document;
6. E2E cobrament posterior sobre el mateix UUID;
7. prova concurrent;
8. evidències conservades.

**No confondre CI verd amb acceptació de preproducció.**
