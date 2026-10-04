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
- L’AJAX transmet `idInsc` i el llegat concentra consulta, UPDATE, URL i SMTP.
- El saldo es deriva principalment de `A_PAGAR-PAGAMENT`.
- Els POST antics continuen actius fins al cutover; no es reclassifiquen com a segurs pel sol fet d’existir el bridge nou.

## 2. FINAL — arquitectura implementada a la branca d’auditoria

```mermaid
classDiagram
direction LR
class SifDebtClaimBridge {
  <<IMPLEMENTAT>>
  +preview(invoice)
  +recordNotice(invoice,stage,options)
  +reconcileAfterPayment(invoice,options)
}
class DebtClaimInternalApi {
  <<IMPLEMENTAT>>
  +preview
  +record_notice
  +reconcile_after_payment
}
class DebtClaimCoordinator {
  <<IMPLEMENTAT>>
  +preview(actor,criteria) array
  +recordNotice(actor,payload) array
  +reconcileAfterPayment(actor,payload) array
}
class DebtClaimCaseRepository {
  <<IMPLEMENTAT>>
  +findByInvoice()
  +create()
  +findReusableEvent()
  +appendEvent()
  +updateCase()
}
class DebtSnapshotRepository {
  <<IMPLEMENTAT>>
  +findByUuid()
  +findByNumVisible()
}
class NotificationOutboxRepository {
  <<IMPLEMENTAT>>
  +enqueue()
  +cancelPendingForInvoice()
}
class OperationalEventRepository {
  <<REUTILITZAT>>
  +append()
}
class ClaimPaymentService {
  <<IMPLEMENTAT>>
  +registerByUuid()
  +registerByNumVisible()
}
class PaymentService
SifDebtClaimBridge --> DebtClaimInternalApi : CSRF + HMAC client
DebtClaimInternalApi --> DebtClaimCoordinator : actor + roles
DebtClaimCoordinator --> DebtClaimCaseRepository
DebtClaimCoordinator --> DebtSnapshotRepository
DebtClaimCoordinator --> NotificationOutboxRepository
DebtClaimCoordinator --> OperationalEventRepository
ClaimPaymentService --> PaymentService
```

## 3. Responsabilitats FINAL

### Frontera intranet
`ajax/facturacio/sifDebtClaim.php` exigeix feature flag, POST, mateix origen, AJAX, CSRF, actor/rol i permís d’edició. No accepta `ID_INSC` com a autoritat fiscal: exigeix `UUID_FACTURA` o `NUM_VISIBLE`.

### `DebtClaimCoordinator`
Autoritat del cicle de reclamació per acció explícita d’operador. Revalida saldo abans del commit, evita regressions d’etapa, registra events idempotents i no crea cap efecte fiscal/monetari per un avís.

### `DebtClaimCaseRepository`
Persistència versionada de `debt_claim_case` i historial append-only `debt_claim_event`, amb `IDEMPOTENCY_KEY`, `PAYLOAD_HASH`, actor, request/correlation IDs i saldo abans/després.

### `DebtSnapshotRepository`
Construeix saldo des de `factura` + `payment_allocation` + moviments `CHARGE/COMPENSATION/REFUND`. Resol el receptor fiscal de la factura. **No** inventa venciment/pròrroga: UC-096 continua pendent.

### `NotificationOutboxRepository`
Encola avisos idempotents i cancel·la els `PENDING` quan el deute queda a zero. `NotificationOutboxDeliveryService` tracta `CANCELLED` com a no enviable.

### `ClaimPaymentService`
Registra el cobrament real posterior contra la factura existent. La reconciliació del claim es fa després amb `DebtClaimCoordinator::reconcileAfterPayment()`.

## 4. Estat

- Classes ACTUAL: **IMPLEMENTADES LEGACY / inspecció estàtica**.
- Nucli FINAL SIF: **IMPLEMENTAT A `audit/uc-012-2026-10-03`**.
- Frontera segura intranet: **IMPLEMENTADA PERÒ DESACTIVADA PER FEATURE FLAG**.
- Proves: **ESCRITES; GitHub Actions continua en cua**.
- Automatització per dates: **BLOQUEJADA PER UC-096**.
- Cutover/preproducció: **PENDENTS**.
