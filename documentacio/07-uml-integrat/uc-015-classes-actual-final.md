# UC-015 · Classes ACTUAL / FINAL — Comprar pack

**Data d'auditoria:** 2026-09-29  
**Abast:** ecommerce PrisMa, pay.prisma.cat, Redsys i SIF.  
**Criteri:** separar estrictament classes i responsabilitats observades al codi actual de les responsabilitats objectiu.

## 1. Classes ACTUAL — web i llegat

```mermaid
classDiagram
direction LR
class Pack {
  +__construct(idPack, dispositiu, modeLlistat)
  +obtenirPreu()
  +obtenirEdicions()
}
class EdicioPack {
  +obtenirIdPreu()
  +obtenirAny()
  +obtenirMes()
  +obtenirCodiCurs()
  +inscripcioOberta(dies)
}
class InscripcioPack {
  +mostrar()
  +mostrarSelect()
  +mostrarInput()
}
class EnviarInscripcioPack {
  <<script PHP>>
  +crea IDPAG
  +insereix N inscripcions
  +genera URL pagament
  +envia correus
}
class RealitzaPagamentPackAutomatic {
  <<callback legacy>>
  +llegeix Redsys
  +crea factura legacy
  +reparteix PAGAMENT
  +actualitza FRACCIO
  +envia correus
}
Pack --> EdicioPack : conté N edicions
InscripcioPack --> EdicioPack : mostra components
EnviarInscripcioPack --> InscripcioPack : rep dades formulari
EnviarInscripcioPack --> EdicioPack : determina components/preus
RealitzaPagamentPackAutomatic --> EnviarInscripcioPack : usa IDPAG creat
```

### Responsabilitats observades

- `Pack.php`: carrega la definició del pack, components, disponibilitat i metadades.
- `EdicioPack.php`: resol edició, curs, dates, preu i obertura.
- `InscripcioPack.php`: genera el formulari.
- `enviarInscripcioPack.php`: rep dades de navegador, calcula/rep imports, genera `IDPAG` i crea N files `inscripcions`.
- `realitzaPagamentPackAutomatic.php`: processa el resultat Redsys al llegat i escriu directament `factures` i `inscripcions`.

## 2. Classes ACTUAL — SIF ja implementat

```mermaid
classDiagram
direction LR
class RedsysPaymentIntentService {
  +create(db,input) array
}
class RedsysCallbackService {
  +receiveCallback(db,payload,signatureValid) array
}
class RedsysCallbackDispatcher {
  +process(db,job) array
}
class RedsysCallbackWorker {
  +runOne(db,workerId,now) array
}
class RedsysPackInvoiceService {
  +sourceType() string
  +issueFromIntentSnapshot(db,dsOrder,snapshot) array
  +issueFromValidatedNotification(sifDb,legacyDb,dsOrder) array
}
class LegacyPackSnapshotRepository {
  +loadByIdpag(db,idpag,amount) array
}
class LegacyPackInvoicePayloadBuilder {
  +build(snapshot) array
}
class RedsysInvoicePayloadBuilder {
  +buildFromValidatedNotification(db,dsOrder,payload) array
}
class InvoiceService {
  +issueInvoice(payload) array
}
class InvoiceRepository
class PaymentRepository

RedsysCallbackWorker --> RedsysCallbackDispatcher
RedsysCallbackDispatcher --> RedsysPackInvoiceService
RedsysPackInvoiceService --> LegacyPackInvoicePayloadBuilder
RedsysPackInvoiceService --> RedsysInvoicePayloadBuilder
RedsysPackInvoiceService --> LegacyPackSnapshotRepository : fallback legacy
RedsysPackInvoiceService --> InvoiceService
InvoiceService --> InvoiceRepository
InvoiceService --> PaymentRepository
```

### Implementat i verificat per inspecció

- `RedsysPaymentIntentService` accepta `SOURCE_TYPE=PACK` i conserva `SNAPSHOT_JSON`.
- `RedsysCallbackService` exigeix signatura vàlida i concilia import, moneda i terminal amb la intenció.
- `RedsysPackInvoiceService` emet des del snapshot de la intenció.
- A partir de l'auditoria del 2026-09-29 el servei també bloqueja si **total factura != import Redsys validat**.
- `InvoiceService` centralitza numeració i idempotència fiscal.

## 3. Classes FINAL

```mermaid
classDiagram
direction LR
class PackCheckoutAdapter {
  +preparePackOperation(input) PackSnapshot
  +createPaymentIntent(snapshot) Intent
}
class PackCommercialSnapshotValidator {
  +validateComposition(snapshot)
  +validateOrderedComponents(snapshot)
  +validatePrice(snapshot)
  +validateFiscalReceiver(snapshot)
}
class RedsysPaymentIntentService
class RedsysCallbackService
class RedsysPackInvoiceService
class InvoiceService
class EnrollmentFundMovementRepository {
  +appendExternalAllocation(movement)
  +appendTransfer(movement)
  +reverse(movement)
}
class AcademicEnrollmentSyncService {
  +syncAfterCommit(operation)
}
class NotificationOutboxService {
  +enqueueAfterCommit(template,event)
}

PackCheckoutAdapter --> PackCommercialSnapshotValidator
PackCheckoutAdapter --> RedsysPaymentIntentService
RedsysCallbackService --> RedsysPackInvoiceService
RedsysPackInvoiceService --> InvoiceService
RedsysPackInvoiceService --> EnrollmentFundMovementRepository : N atribucions
RedsysPackInvoiceService --> AcademicEnrollmentSyncService : postcommit
RedsysPackInvoiceService --> NotificationOutboxService : postcommit
```

## 4. Diferències bloquejants ACTUAL → FINAL

| Responsabilitat | ACTUAL | FINAL |
|---|---|---|
| Preu definitiu | Part del valor arriba del navegador | Backend autoritatiu + snapshot |
| Identitat operació | `MAX(IDPAG)+1` | Identitat central/idempotent |
| Ordinal components | Derivat de files/ordre SQL | Ordinal comercial congelat |
| Receptor fiscal | Pot provenir del primer item | Receptor confirmat al snapshot |
| Callback | Script legacy amb escriptures directes | Callback SIF + cua + worker |
| Numeració | Taula legacy | Seqüència fiscal SIF |
| Distribució monetària | Camps `PAGAMENT/A_PAGAR` | Ledger quantitatiu per ID_INSC |
| Notificacions | PHP directe | Outbox postcommit |

## 5. Estat

- **Documentat:** sí.
- **Implementat parcial:** sí.
- **Verificat per inspecció:** sí.
- **Pendent:** adaptador ecommerce, snapshot comercial complet, ledger per inscripció, sincronització acadèmica i retirada del callback fiscal llegat.
