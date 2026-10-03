# UC-005 · Diagrames de classes ACTUAL / FINAL

**Data:** 2026-10-03

## 1. ACTUAL — pantalla i llegat

```mermaid
classDiagram
direction LR
class AlumnesFacturaPage { <<LLEGAT>> }
class AlumnesFacturaJS { <<LLEGAT>> +search() +openInvoice() +edit() +cancel() }
class AlumnesFacturaSifJS { <<PARCIAL>> +searchSif() +viewSifInvoice() }
class GuardarDadesFacturaEndpoint { <<LLEGAT HARDENED>> +POST() }
class AnularFacturaEndpoint { <<LLEGAT HARDENED>> +POST() }
class SifLegacyInvoiceMutationGuard { <<EXISTEIX>> +assertLegacyMutationAllowed() }
class LegacyInvoiceMutationAuthorization { <<EXISTEIX>> +assertSameOrigin() +assertCanEdit() }
class Intranet { <<LLEGAT>> +guardarDadesFactura_Factures() +anularFactura() }
class LegacyDB { <<BD LLEGADA>> }

AlumnesFacturaPage --> AlumnesFacturaJS
AlumnesFacturaPage --> AlumnesFacturaSifJS
AlumnesFacturaJS --> GuardarDadesFacturaEndpoint
AlumnesFacturaJS --> AnularFacturaEndpoint
GuardarDadesFacturaEndpoint --> LegacyInvoiceMutationAuthorization
GuardarDadesFacturaEndpoint --> SifLegacyInvoiceMutationGuard
AnularFacturaEndpoint --> LegacyInvoiceMutationAuthorization
AnularFacturaEndpoint --> SifLegacyInvoiceMutationGuard
GuardarDadesFacturaEndpoint --> Intranet
AnularFacturaEndpoint --> Intranet
Intranet --> LegacyDB
```

## 2. SIF existent

```mermaid
classDiagram
direction LR
class ManualRectificationService { <<EXISTEIX>> +issueByUuid() +issueByNumVisible() -normalizeInput() }
class ManualRectificationPayloadBuilder { <<EXISTEIX>> +forOriginalInvoice() }
class ManualPaymentInvoiceRepository { <<EXISTEIX>> +findByUuid() +findByNumVisible() }
class RectificationRepository { <<EXISTEIX>> +linkRectification() +markOriginalRectified() }
class InvoiceService { <<EXISTEIX>> +issueInvoice() }
class InvoiceRepository { <<EXISTEIX>> +createInvoiceGraph() }
class Factura { <<SIF DB>> }
class FacturaRectificacio { <<SIF DB>> }
class FacturaRegistres { <<SIF DB>> }
class FiscalQueue { <<SIF DB>> }

ManualRectificationService --> ManualPaymentInvoiceRepository
ManualRectificationService --> ManualRectificationPayloadBuilder
ManualRectificationService --> InvoiceService
ManualRectificationService --> RectificationRepository
InvoiceService --> InvoiceRepository
InvoiceRepository --> Factura
InvoiceRepository --> FacturaRegistres
InvoiceRepository --> FiscalQueue
RectificationRepository --> FacturaRectificacio
RectificationRepository --> Factura
```

## 3. FINAL objectiu

```mermaid
classDiagram
direction LR
class Uc005Controller { <<PROPOSAT>> +preview() +confirm() }
class Uc005Authorization { <<PROPOSAT>> +assertCanRectify() }
class FiscalCorrectionClassifier { <<PROPOSAT/UC-74>> +classify() }
class RectificationSnapshotAssembler { <<PROPOSAT>> +fromOriginalAndCorrection() }
class ManualRectificationService { <<EXISTEIX/PARCIAL>> }
class RectificationTransactionService { <<PROPOSAT>> +issueAndLinkAtomically() }
class OperationalAudit { <<PROPOSAT>> +append() }
class DocumentService { <<PROPOSAT/PARCIAL TRANSVERSAL>> +ensureFiscalDocument() }

Uc005Controller --> Uc005Authorization
Uc005Controller --> FiscalCorrectionClassifier
FiscalCorrectionClassifier --> RectificationSnapshotAssembler
RectificationSnapshotAssembler --> RectificationTransactionService
RectificationTransactionService --> ManualRectificationService
RectificationTransactionService --> OperationalAudit
RectificationTransactionService --> DocumentService
```

## 4. Mismatch principal

El servei actual fa `InvoiceService::issueInvoice()` i, quan aquest ja ha confirmat la seva transacció, executa `linkRectification()` i `markOriginalRectified()`. Per tant el FINAL no es pot dibuixar com una única transacció fins que el codi ho implementi realment.
