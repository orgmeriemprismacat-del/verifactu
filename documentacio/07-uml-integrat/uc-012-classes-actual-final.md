# UC-012 — Classes ACTUAL / FINAL

## 1. ACTUAL — arquitectura observada

```mermaid
classDiagram
direction LR
class BrowserJS {
  +seleccionar(idInsc)
  +POST(idInsc)
}
class AjaxHandler {
  +session_start()
  +read POST idInsc
  +call Intranet
}
class Intranet {
  +updateSendMsg_Recordatori()
  +updateSendMsg_PrimeraReclamacio()
  +updateSendMsg_LastClaimPay()
  +updateSendMsg_*Defaulter()
}
class ConnexioWeb
class ConnexioIntranet
class MailSMTPComvive
class LegacyInscripcions {
  +A_PAGAR
  +PAGAMENT
  +data_reclamacio
  +reclamat
  +pag_observacions
  +INSC_CURSS
}
BrowserJS --> AjaxHandler
AjaxHandler --> Intranet
Intranet --> ConnexioWeb
Intranet --> ConnexioIntranet
Intranet --> MailSMTPComvive
ConnexioWeb --> LegacyInscripcions
```

### Observacions ACTUAL

- La pàgina/JS decideix quins registres enviar.
- L'AJAX transmet `idInsc`.
- `Intranet.php` concentra consulta, càlcul, UPDATE, generació d'URL i SMTP.
- Estat administratiu, comunicació i eventual baixa queden fortament acoblats.
- El saldo es deriva principalment de `A_PAGAR-PAGAMENT` al llegat.

## 2. FINAL — arquitectura requerida

```mermaid
classDiagram
direction LR
class DebtClaimController {
  +preview(command) ClaimPreview
  +execute(command) ClaimResult
}
class DebtClaimAuthorization {
  +assertAllowed(actor, action, scope)
}
class DebtClaimCoordinator {
  +evaluateDebt(invoice) DebtSnapshot
  +recordDecision(command) ClaimCase
  +scheduleNotice(claimCase) Notice
  +resolveAfterPayment(uuidPayment)
}
class ClaimCaseRepository {
  +findOpenByInvoice()
  +appendEvent()
  +closeOrReschedule()
}
class DebtSnapshotRepository {
  +loadInvoiceBalance()
  +loadPayer()
  +loadExtension()
}
class NotificationOutboxRepository {
  +enqueueIdempotent()
  +cancelPending()
}
class ClaimPaymentService {
  +registerByUuid()
  +registerByNumVisible()
}
class PaymentService
class OperationalEventRepository
DebtClaimController --> DebtClaimAuthorization
DebtClaimController --> DebtClaimCoordinator
DebtClaimCoordinator --> ClaimCaseRepository
DebtClaimCoordinator --> DebtSnapshotRepository
DebtClaimCoordinator --> NotificationOutboxRepository
DebtClaimCoordinator --> OperationalEventRepository
DebtClaimCoordinator --> ClaimPaymentService : només si hi ha ingrés real
ClaimPaymentService --> PaymentService
```

## 3. Responsabilitats FINAL

### `DebtClaimController`
Frontera autenticada. Valida CSRF/HMAC segons canal, request-id, idempotency-key, actor, rol i payload.

### `DebtClaimCoordinator`
Única autoritat per P-MOR-01..05. No envia correus directament ni modifica factura fiscal pel fet de reclamar.

### `ClaimCaseRepository`
Persistència append-only o versionada de l'expedient: estat, actor, deute observat, destinatari, canal, dates, decisió i correlació.

### `DebtSnapshotRepository`
Construeix saldo real a partir de factura + pagaments + devolucions + assignacions + pròrrogues; evita confiar només en camps denormalitzats del llegat.

### `NotificationOutboxRepository`
Crea una comunicació idempotent **després del commit** i permet cancel·lar/reprogramar avisos obsolets.

### `ClaimPaymentService`
Ja existeix. Només s'invoca després d'acreditar un ingrés real; conserva la factura existent.

## 4. Estat

- Classes ACTUAL: **IMPLEMENTADES**, inspecció estàtica.
- `ClaimPaymentService`: **IMPLEMENTAT + TESTS**.
- Classes FINAL de coordinació: **DISSENYADES / PENDENTS D'IMPLEMENTAR**.
