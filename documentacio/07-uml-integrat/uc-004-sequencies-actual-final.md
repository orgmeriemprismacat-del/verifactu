# UC-004 · Diagrames de seqüència ACTUAL / FINAL

**Cas d'ús:** UC-004 — Emetre factura abans de cobrar  
**Data d'auditoria estàtica:** 2026-09-29  
**Principi:** separar el flux que realment executa avui la intranet del flux FINAL SIF. Cap diagrama FINAL implica desplegament verificat.

## 1. Seqüència ACTUAL — càrrega, permís de pàgina i selecció

```mermaid
sequenceDiagram
autonumber
actor Op as Operador
participant B as Browser
participant G as general.js
participant MM as ajax/mostrarMain.php
participant I as Intranet
participant UI as Usuari
participant DBI as BD intranet
participant S as mostrarInformacioInscripcio_generaFactura.php
participant DBW as BD web

Op->>B: Obre /alumnes/genera-factura-abans-pagar/
B->>MM: GET mostrarMain.php?url=pathname
MM->>DBI: SELECT apartat + ROLS_VISUALITZAR
MM->>UI: tePermisVisualitzacio(rols)
alt sense permís de visualització
  MM-->>B: "No tens permisos..."
else amb permís
  MM->>I: mostrarPage(usuari)
  I->>DBI: buscarTotesEntitats()
  I-->>MM: HTML passos 1,2,3 + entitats
  MM-->>B: HTML
end

B->>G: carregar general.js
G->>DBI: via AJAX consultaRolsEdicio + consultaRolsUsuari
G-->>B: tePermisEdicio calculat al client

Op->>B: Cerca NIF/NIE
B->>S: GET dni
S->>I: mostrarInformacioInscripcio_generaFactura_Alumnes(dni)
I->>DBW: buscarInfoInscDniData
DBW-->>I: files
I-->>S: taula HTML amb valors i IDs
S-->>B: HTML
Op->>B: afegeix/elimina inscripcions
Note over B
El navegador conserva idsInsc, cursos, edicions,
preuTotal i conceptes.
end note
```

### Lectura d'auditoria

- La visualització de pàgina té comprovació servidor.
- El permís d'edició usat per la pantalla es calcula al client.
- Les dades econòmiques i funcionals que s'enviaran després es componen parcialment a partir del DOM.

## 2. Seqüència ACTUAL — emissió llegada de factura abans de cobrar

```mermaid
sequenceDiagram
autonumber
actor Op as Operador
participant B as Browser / JS UC-004
participant E as generaFacturaElectronica_Factures.php
participant I as Intranet
participant DBI as BD intranet
participant DBW as BD web
participant D as mostraDadesFacturaElectronica_Factures.php
participant R as mostraInscripcionsFacturaElectronica_Factures.php

Op->>B: Continua des de pas 1
B->>B: comprova tePermisEdicio
B->>B: recorre DOM i idsInsc.push(id)
B->>B: suma #apagar-id a preuTotal
B->>B: construeix cursos / edicions / concepte1
B->>B: AJAX calcularTextData per concepte2
Op->>B: Selecciona entitat i prem Genera factura
B->>E: POST empresa, conceptes, preu, cursos, edicions, inscripcions, observacions
E->>I: generarFacturaElectronica_Alumnes(...)
I->>DBI: buscarEntitat per text empresa
DBI-->>I: CIF, raó, adreça, CP, població
I->>DBW: buscarLastOrdreFact(any, "A")
DBW-->>I: darrer ordre
I->>I: ordre = darrer + 1
I->>DBW: buscarLastFact()
DBW-->>I: darrer ID factura
I->>I: factura = darrer + 1
I->>DBW: INSERT legacy factures
loop cada ID rebut del navegador
  I->>DBW: UPDATE inscripció amb FACTURA_RELACIONADA, "Paga empresa", CIF
end
I-->>E: HTML amb factura creada + inscripcions actualitzades
E-->>B: HTML
B->>D: POST factura
D->>I: mostraDadesFacturaElectronica_Alumnes(factura)
I->>DBW: buscarInfoFactura
I-->>B: dades HTML
B->>R: POST factura
R->>I: mostraInscripcionsFacturaElectronica_Alumnes(factura)
I->>DBW: buscarInscFactRel
I-->>B: participants HTML
```

### Punts de fallada ACTUAL

1. **Concurrència de numeració:** el patró “darrer + 1” no mostra un lock fiscal equivalent al SIF.
2. **Confiança en client:** el servidor rep import, conceptes, llista d'inscripcions i text d'entitat compostos al navegador.
3. **Duplicació interna del client:** `idsInsc` és global; al pas 2 es reinicien `cursos`, `edicions` i `preuTotal`, però no s'ha observat el reset equivalent d'`idsInsc`.
4. **Atomicitat:** l'INSERT de factura i els UPDATE d'inscripcions no estan envoltats per una transacció unitària observada.
5. **Idempotència:** no hi ha clau idempotent del cas al circuit llegat.
6. **Resposta:** retorna fragments HTML, no un contracte tipificat CREATED/REUSED/CONFLICT/ERROR.
7. **Fiscalitat:** aquest camí no passa pel registre encadenat/cua fiscal del nou SIF.

## 3. Seqüència ACTUAL — previsualització i descàrrega

```mermaid
sequenceDiagram
autonumber
actor Op as Operador
participant B as Browser
participant P as mostraPrevFactura_Factures.php
participant DL as descarregaFactura.php
participant I as Intranet
participant DBW as BD web
participant FS as Sistema de fitxers
participant RM as eliminarArxiu.php

Op->>B: Previsualitza
B->>P: GET factura
P->>I: modalConsultaFactura_Factures(factura)
I->>I: generaFactura(factura, false)
I->>DBW: buscarInfoFactura
DBW-->>I: dades
I-->>B: HTML factura

Op->>B: Descarrega
B->>DL: GET id factura
DL->>I: generaFactura(id, true)
I->>DBW: buscarInfoFactura
I->>DBW: UPDATE GENERAT si encara buit
I->>FS: Dompdf + file_put_contents(filename)
I-->>B: filename
B->>FS: descarrega fitxer
B->>RM: GET filename
RM->>FS: unlink(filename)
```

Aquest flux de document és llegat. No equival a custòdia immutable per UUID, versió i hash.

## 4. Seqüència FINAL — preparar i emetre UC-004 al SIF

Aquest diagrama manté la vista de l'arquitectura HTTP FINAL: `Uc004Controller`, `Authorization` i el classificador de cobertura transversal continuen pendents. La reconstrucció de selecció/receptor/imports i el fingerprint ja tenen implementació executable **CLI no productiva**, detallada a la seqüència 4.1.

```mermaid
sequenceDiagram
autonumber
actor Op as Operador
participant C as Uc004Controller [PROPOSAT]
participant A as Authorization [PROPOSAT]
participant SL as SelectionLoader [PROPOSAT]
participant BR as BillingResolver [PROPOSAT]
participant CG as CrossChannelCoverage [PROPOSAT]
participant LC as LineCalculator [PROPOSAT]
participant IBP as InvoiceBeforePaymentService
participant PB as InvoiceBeforePaymentPayloadBuilder
participant IS as InvoiceService
participant V as InvoicePayloadValidator
participant IV as PayloadIdempotencyValidator
participant TR as TransactionRunner
participant SEQ as FiscalSequenceRepository
participant IR as InvoiceRepository
participant BPC as InvoiceBeforePaymentCoverageRepository
participant DB as BD SIF
participant LS as LegacySync [PROPOSAT]
participant DOC as DocumentService [PROPOSAT]

Op->>C: Emetre factura abans de cobrar(selectionIds, billingEntityId, requestId)
C->>A: assertCanIssue(actor, scope)
A-->>C: autoritzat

C->>SL: loadSelectedInscriptions(selectionIds)
SL-->>C: snapshot real de les inscripcions
C->>SL: deduplicate + assertSameAllowedContext()
SL-->>C: selecció validada

C->>BR: resolveByInternalId(billingEntityId)
BR-->>C: snapshot fiscal del receptor

C->>CG: findExistingCoverage(sourceIds)
alt cobertura incompatible ja facturada
  CG-->>C: CONFLICT amb factura existent
  C-->>Op: conflicte, sense nova factura
else cobertura admissible
  CG-->>C: OK
  C->>LC: buildLines + calculateTotals des de servidor
  LC-->>C: lines + totals
  C->>IBP: issueBeforePayment(input sense payment)
  IBP->>PB: build(input)
  alt payment no nul o falta clau/referència
    PB-->>C: VALIDATION_ERROR
    C-->>Op: error validació
  else payload preparat
    PB-->>IBP: source=INTRANET, emesa_abans_cobrament=1
    IBP->>IS: issueInvoice(payload)
    IS->>V: validate(payload)
    V-->>IS: payload estructural validat
    IS->>TR: run()
    TR->>DB: BEGIN
    IS->>IR: findByIdempotencyKey(key, FOR UPDATE)
    alt mateixa clau ja existeix
      IR-->>IS: factura existent + hash
      IS->>IV: assertMatches(payload, storedHash)
      alt payload diferent o hash no demostrable
        IV-->>IS: CONFLICT
        IS-->>C: error
      else equivalència exacta
        IV-->>IS: OK
        IS-->>TR: resultat REUSED
      end
    else nova clau
      IS->>SEQ: next(series, year)
      SEQ->>DB: reserva seqüència
      IS->>IR: lockChainState()
      IR->>DB: SELECT fiscal_chain_state FOR UPDATE
      IS->>IR: createInvoiceGraph(...)
      IR->>DB: INSERT factura
      IR->>DB: INSERT factura_linia
      IR->>DB: INSERT factura_registres
      IR->>DB: UPDATE fiscal_chain_state
      IR->>DB: INSERT fiscal_queue
      IR->>DB: INSERT fact_rels
      IR-->>IS: UUID + NUM_VISIBLE
      IS->>BPC: claim(relations, UUID, idempotency_key)
      BPC->>DB: INSERT invoice_before_payment_coverage
      alt origen ja reclamat per una altra operació UC-004
        DB-->>BPC: duplicate uq_invoice_before_payment_source
        BPC-->>IS: PDOException
        IS-->>TR: excepció
        TR->>DB: ROLLBACK
        IS-->>C: CONFLICT 409
        C-->>Op: conflicte, cap segona factura confirmada
      else claim acceptat
        BPC-->>IS: OK
        IS-->>TR: resultat CREATED
        TR->>DB: COMMIT
        TR-->>IS: resultat confirmat
        IS-->>IBP: resultat
        IBP-->>C: UUID + número + reused
        C->>LS: syncAfterSifCommit(UUID) si cal
        C->>DOC: ensureDocument(UUID)
        C-->>Op: factura emesa / cobrament PENDING / document READY o PENDING
      end
    end
  end
end
```

## 4.1. Seqüència IMPLEMENTADA A LA BRANCA — preview i confirmació des de les BDs llegades

```mermaid
sequenceDiagram
autonumber
actor Op as Operador tècnic / preproducció
participant P as preview-invoice-before-payment-from-legacy.php
participant C as process-invoice-before-payment-from-legacy.php
participant PREP as InvoiceBeforePaymentLegacyPreparationService
participant SEL as InvoiceBeforePaymentSelectionRepository
participant BILL as InvoiceBeforePaymentBillingPartyRepository
participant ASM as InvoiceBeforePaymentServerPayloadAssembler
participant PB as InvoiceBeforePaymentPayloadBuilder
participant FP as PayloadIdempotencyValidator
participant WEB as BD web llegada
participant INTRA as BD intranet llegada
participant IBP as InvoiceBeforePaymentService
participant IS as InvoiceService
participant DB as BD SIF

Op->>P: IDs inscripció + entityId + created_by
P->>PREP: prepare(...)
PREP->>SEL: loadByIds(ids)
SEL->>WEB: SELECT inscripcions + curs per ID
WEB-->>SEL: files autoritatives
PREP->>BILL: loadByEntityId(entityId)
BILL->>INTRA: SELECT entitat + responsable actiu
INTRA-->>BILL: snapshot receptor
PREP->>ASM: buildInput(selection,billing,context)
ASM-->>PREP: línies + total A_PAGAR + relacions + K estable
PREP->>PB: build(input)
PB-->>PREP: INTRANET + EMESA_ABANS_COBRAMENT=1
PREP->>FP: calculateHash(payload)
FP-->>PREP: fingerprint SHA-256
PREP-->>P: payload + fingerprint
P-->>Op: PREVIEW, cap escriptura fiscal

Op->>C: mateixa selecció + entityId + expected-fingerprint
C->>PREP: prepare(...) de nou
PREP->>WEB: rellegir inscripcions/curs
PREP->>INTRA: rellegir receptor
PREP->>FP: fingerprint actual
alt fingerprint actual != esperat
  C-->>Op: 409 / nou preview obligatori
else fingerprint coincideix
  C->>IBP: issueBeforePayment(input reconstruït)
  IBP->>IS: issueInvoice(payload)
  IS->>DB: transacció fiscal + claim UC-004
  DB-->>IS: CREATED o REUSED
  IS-->>IBP: UUID + número
  IBP-->>C: resultat
  C-->>Op: factura PENDING, sense payment
end
```

**Implementat:** lectura per IDs, entityId, mateix curs/edició, total des de `A_PAGAR`, receptor fiscal, fingerprint i relectura abans de confirmar.  
**Encara pendent:** autenticació/autorització/CSRF de la pantalla, classificador de cobertura transversal, document per UUID, auditoria operacional i sincronització llegada post-COMMIT.

## 5. Seqüència FINAL — col·lisió concurrent de la mateixa clau

```mermaid
sequenceDiagram
autonumber
participant IS as InvoiceService
participant TR as TransactionRunner
participant DB as BD SIF
participant IR as InvoiceRepository
participant IV as PayloadIdempotencyValidator

IS->>TR: createOrReuseInvoice(payload)
TR->>DB: BEGIN
IS->>IR: findByIdempotencyKey(key, FOR UPDATE)
IR-->>IS: no existeix
Note over IS,DB: Una altra petició concurrent pot inserir la mateixa UNIQUE key
IS->>DB: INSERT via createInvoiceGraph()
DB-->>IS: PDOException 23000 duplicate key
TR->>DB: ROLLBACK
IS->>TR: nova transacció de recuperació
TR->>DB: BEGIN
IS->>IR: findByIdempotencyKey(key, FOR UPDATE)
IR-->>IS: factura guanyadora
IS->>IV: assertMatches(payload, IDEMPOTENCY_PAYLOAD_HASH)
alt payload equivalent
  IV-->>IS: OK
  TR->>DB: COMMIT
  IS-->>IS: REUSED mateix UUID/número
else payload diferent
  IV-->>IS: CONFLICT
  TR->>DB: ROLLBACK
end
```

## 5.1. Seqüència FINAL — dues claus UC-004 sobre la mateixa inscripció

```mermaid
sequenceDiagram
autonumber
participant A as Petició UC-004 K1
participant B as Petició UC-004 K2
participant IS as InvoiceService
participant IR as InvoiceRepository
participant CV as InvoiceBeforePaymentCoverageRepository
participant DB as BD SIF

A->>IS: issueInvoice(payload K1, source_id=900)
IS->>DB: BEGIN + crear graf fiscal K1
IS->>CV: claim INSCRIPCIO 900
CV->>DB: INSERT coverage(INSCRIPCIO,900,K1)
DB-->>CV: OK
IS->>DB: COMMIT

B->>IS: issueInvoice(payload K2, source_id=900)
IS->>DB: BEGIN + crear graf fiscal provisional K2
IS->>CV: claim INSCRIPCIO 900
CV->>DB: INSERT coverage(INSCRIPCIO,900,K2)
DB-->>CV: duplicate uq_invoice_before_payment_source
CV-->>IS: PDOException 23000
IS->>DB: ROLLBACK
IS-->>B: 409 CONFLICT
Note over IS,DB: El rollback elimina factura, seqüència, registre, cua i fact_rels de K2.
```

Aquest guard és **específic d'UC-004**. No substitueix la classificació de cobertura entre una factura UC-004 i factures d'altres canals o esquemes de pagador dividit.

## 6. Seqüència FINAL — cobrament posterior és un altre cas d'ús

```mermaid
sequenceDiagram
autonumber
actor Pay as Operador / procés de cobrament
participant UC02 as Adaptador UC-002
participant PS as PaymentService
participant DB as BD SIF

Pay->>UC02: fet bancari confirmat + UUID factura UC-004
UC02->>PS: registerPayment(paymentKey, allocations[UUID])
PS->>DB: transacció payment_transaction + payment_allocation
PS->>DB: recalcular estat de cobrament
DB-->>PS: resultat
PS-->>UC02: cobrament creat/reutilitzat
UC02-->>Pay: factura original actualitzada econòmicament
Note over UC02,DB: NO executar issueInvoice() de nou
```

## 7. Frontera amb l'endpoint genèric existent

`sif/public/api/factures/issue.php` crea directament `InvoiceService` i executa `issueInvoice($payload)`. Això és útil com a endpoint genèric d'emissió, però **no substitueix** el contracte UC-004 perquè:

- no passa per `InvoiceBeforePaymentPayloadBuilder`;
- no força per si mateix `source_channel=INTRANET`;
- no força `EMESA_ABANS_COBRAMENT=1`;
- el servei genèric permet bloc inicial `payment` si se li proporcionen dependències;
- no reconstrueix selecció, receptor ni imports des de fonts de servidor;
- no configura `InvoiceBeforePaymentCoverageRepository`; per tant un payload amb `EMESA_ABANS_COBRAMENT` falla tancat al servei i l'endpoint genèric no s'ha d'usar com a endpoint UC-004;
- no aplica el classificador de cobertura transversal entre altres canals/pagadors.

Per tancar UC-004 cal un adaptador explícit o un endpoint específic que invoqui el servei de cas d'ús corresponent.

## 8. Estat de verificació

- **Documentat:** sí, ACTUAL i FINAL separats.
- **Implementat:** circuit llegat ACTUAL i nucli SIF d'emissió/idempotència.
- **Integrat:** **no acreditat** per a pantalla UC-004 → `InvoiceBeforePaymentService`.
- **Proves:** existeixen proves d'integració del servei, però **no s'han executat en aquesta auditoria**.
- **Producció/preproducció:** no verificada.
