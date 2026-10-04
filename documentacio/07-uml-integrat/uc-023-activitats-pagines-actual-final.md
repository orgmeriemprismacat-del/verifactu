# UC-023 — Activitats per pàgina/apartat ACTUAL / FINAL

**Data:** 03/10/2026

## P01 — `alumnes-pagaments.php`

### ACTUAL
```mermaid
flowchart TD
 A[Entrar a Pagaments] --> B[Comprovar sessió general]
 B --> C[Carregar shell HTML]
 C --> D[Carregar alumnes-pagaments.js]
```

### FINAL
La pàgina pot continuar com a shell, però la mutació ha de delegar en una API de comanda autenticada i no en GET.

## P02 — cerca de cobrament

**Fitxers:** `js/alumnes-pagaments.js` + `ajax/alumnes/buscarInfomacioPagament.php`.

### ACTUAL
```mermaid
flowchart TD
 A[Introduir DNI/codi/factura] --> B{un sol criteri?}
 B -- no --> E[Error UI]
 B -- sí --> C[GET buscarInfomacioPagament.php]
 C --> D[HTML de resultats]
```

### FINAL
La consulta pot ser GET, però ha de retornar identificadors SIF inequívocs de factura/inscripció i estat actual.

## P03 — entrada de fracció al navegador

### ACTUAL
```mermaid
flowchart TD
 A[Introduir import] --> B[suma pagat + import]
 B --> C{supera a pagar?}
 C -- sí --> D[marcat visual danger]
 C -- no --> E[continuar]
 F[Introduir data] --> G[validació format i avís >5 dies/futur]
 H[Triar banc] --> I[validació client]
```

**Mancança:** cap d'aquestes comprovacions acredita una precondició server-side.

### FINAL
El navegador envia dades; el servidor recalcula import pendent, relació factura-inscripció, estat i elegibilitat sota lock.

## P04 — modal de confirmació

### ACTUAL
GET a `mostrarModalConfPag.php` només quan el flux ho demana.

### FINAL
La preview ha de provenir del mateix servei/guard que després valida la comanda, amb versió/etag o fingerprint per detectar estat canviat.

## P05 — `ajax/alumnes/efectuarPagament.php`

### ACTUAL
```mermaid
flowchart TD
 A[GET amb dades de cobrament] --> B[Deserialitzar sessió]
 B --> C[cridar Intranet->efectuarPagament]
 C --> D[retornar HTML]
```

**Riscos:** GET amb efecte persistent, contracte HTML, cap clau idempotent explícita, cap versió esperada visible.

### FINAL
```mermaid
flowchart TD
 A[POST JSON] --> B[Autenticació + permís + CSRF]
 B --> C[request_id + correlation_id + event_id]
 C --> D[relectura factura/inscripció]
 D --> E{event extern verificat i nou/equivalent?}
 E -- conflicte --> F[409 + audit]
 E -- reús --> G[200 REUSED]
 E -- nou --> H[ManualInstallmentPaymentService]
 H --> I[PaymentService transaccional]
 I --> J[200 CREATED + UUID_PAYMENT]
```

## P06 — endpoints històrics de dades/fracció

**Fitxers:** `guardarDadesPagament_ConsultaInformacio.php`, `guardarEnviarDadesPagament_ConsultaInformacio.php`.

### ACTUAL
Accepten per GET camps de pagament, data, IDPAG, observacions, `fraccio`, `comfraccio`, factura i recordatoris.

### FINAL
No han de crear un segon ledger paral·lel. Si segueixen existint com a UI administrativa, qualsevol canvi econòmic ha d'arribar al SIF per una comanda única i idempotent.

## P07 — servei SIF UC-023

### ACTUAL
Factura existent → builder → PaymentService → payment_transaction/allocation → estat.

### FINAL
Afegir abans del builder:
1. authorization policy;
2. destination guard ID_INSC↔factura;
3. external receipt reconciliation;
4. saldo pendent;
5. audit REQUESTED;
6. després del commit, terminal event i eventual outbox.

## Matriu de cobertura

| Pàgina/apartat | ACTUAL documentat | FINAL documentat | Implementat FINAL |
| --- | --- | --- | --- |
| P01 shell | sí | sí | no aplica/parcial |
| P02 cerca | sí | sí | parcial |
| P03 entrada client | sí | sí | no |
| P04 preview | sí | sí | no |
| P05 comanda | sí | sí | no |
| P06 endpoints històrics | sí | sí | no |
| P07 nucli SIF | sí | sí | parcial; event_id opcional en branca |
