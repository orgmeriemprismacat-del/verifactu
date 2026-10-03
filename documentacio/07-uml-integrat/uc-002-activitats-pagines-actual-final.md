# UC-002 · Activitats ACTUAL / FINAL per pàgina i apartat

**Data:** 2026-10-03  
**Objectiu:** traçar cada superfície visible/executable i evitar confondre codi existent amb disseny final.

## P-PAG-01 · `/alumnes/pagaments/` — cerca

### ACTUAL
~~~mermaid
flowchart TD
A[Obrir Pagaments] --> B[Buscar per DNI, codi regal o factura]
B --> C{exactament un criteri?}
C -- no --> E[Error client]
C -- si --> D[GET buscarInfomacioPagament.php]
D --> F[mostrarPagaments()]
F --> G[Render resultats]
~~~

**Estat:** JS i endpoint de cerca localitzats; cos de `Intranet::mostrarPagaments()` no auditable perquè el snapshot `Intranet.php` és buit.

### FINAL
La cerca ha de ser només lectura, amb sessió/rol, minimització de dades i traça d’accés quan la governança ho exigeixi. No ha de registrar cap moviment econòmic.

## P-PAG-02 · Resultat i edició del cobrament

### ACTUAL
~~~mermaid
flowchart TD
A[Seleccionar registre] --> B[Editar import/data/banc]
B --> C[Validacions JS]
C --> D{efact=1?}
D -- si --> E[GET modal confirmacio]
E --> F[Confirmar]
D -- no --> F
F --> G[aplicarPagament]
~~~

**Estat:** implementat al JS. Les validacions client no substitueixen les del servidor.

### FINAL
La UI ha de mostrar factura SIF identificada, import pendent, evidència/referència del cobrament, destí per inscripció quan pertoqui i estat de conciliació.

## P-PAG-03 · `ajax/alumnes/efectuarPagament.php` — mutació llegada

### ACTUAL després de l’auditoria
~~~mermaid
flowchart TD
A[POST] --> B{sessio valida?}
B -- no --> X[401]
B -- si --> C{origen AJAX autoritzat?}
C -- no --> Y[403]
C -- si --> D{rol de /alumnes/pagaments/?}
D -- no --> Y
D -- si --> E{import>0, data+banc, efact valid?}
E -- no --> Z[422]
E -- si --> F[Intranet::efectuarPagament]
F --> G[Resposta text/html]
~~~

**Implementat:** POST-only, `Cache-Control: no-store`, sessió, comprovació d’origen/XHR, rol i validació servidor d’import/data/banc.

**Pendent:** token CSRF explícit si es requereix més enllà del guard d’origen; clau idempotent durable; resposta JSON tipificada; integració demostrable amb SIF. El cos de `Intranet::efectuarPagament()` no és al repositori.

## P-PAG-04 · `/api/payments/register.php`

### ACTUAL
~~~mermaid
flowchart TD
A[JSON input] --> B[PaymentService]
B --> C[PaymentPayloadValidator]
C --> D[PaymentRepository]
D --> E[payment_transaction + allocation]
E --> F[recalcular ESTAT_COBRAMENT]
~~~

**Implementat:** nucli econòmic.

**Pendent bloquejant:** POST-only, autenticació HMAC/replay guard, actor/rol i audit gateway en aquesta frontera concreta.

### FINAL
~~~mermaid
flowchart TD
A[POST signat] --> B[InternalApiAuthenticator]
B --> C[PaymentAuthorizationPolicy]
C --> D[ExternalReceiptReconciler]
D --> E[PaymentActionGateway REQUESTED]
E --> F[PaymentService]
F --> G[Ledger per factura]
G --> H[Ledger per ID_INSC si aplica]
H --> I[Audit terminal]
I --> J[JSON tipificat]
~~~

## P-PAG-05 · Preflight / preview / process manual

### ACTUAL
- `preflight-manual-payment.php`: CLI-only, rebutja producció i comprova BD/taules/seed fiscal.
- `preview-manual-payment.php`: CLI-only, rebutja producció, resol factura i construeix payload sense mutar.
- `process-manual-payment.php`: camí executable de pagament manual.

**Estat:** implementat; les proves de servei cobreixen UUID, número visible, reintent i pagament parcial.

### FINAL
Mantenir aquests scripts com a eina controlada de preproducció/operació, amb actor, evidència i correlació si s’utilitzen fora de test.

## P-PAG-06 · Persistència econòmica i estat

### ACTUAL
~~~mermaid
flowchart TD
A[PaymentRepository createPayment] --> B[INSERT payment_transaction]
B --> C[INSERT N payment_allocation]
C --> D[lock factura]
D --> E[sum charges/compensations/refunds]
E --> F[PaymentStatusCalculator]
F --> G[UPDATE factura.ESTAT_COBRAMENT]
~~~

**Estat:** implementat. La validació comuna ara obliga imports positius i conservació monetària abans d’entrar al repositori.

### FINAL
Afegir constraints DB equivalents si es decideix defensa en profunditat; actualment les taules base no imposen `IMPORT > 0` ni suma entre capçalera i assignacions.

## P-PAG-07 · Atribució per inscripció

### ACTUAL
La migració `2026_09_30_000030_add_enrollment_fund_movement.sql` i `EnrollmentFundMovementRepository` existeixen. El repositori valida imports positius, ordre, tipus i idempotència de l’atribució.

**Però:** el `PaymentRepository` genèric de UC-002 no l’invoca.

### FINAL
Quan una factura cobreix una o més inscripcions, el moviment extern únic ha de mantenir atribucions `EXTERNAL_ALLOCATION` per `ID_INSC`, reconciliades amb la factura i el total del cobrament.

## P-PAG-08 · Auditoria operacional

### ACTUAL
`PaymentActionGateway` i `PaymentActionEventRepository` existeixen i la taula `payment_action_event` està modelada, però el registre genèric `api/payments/register.php -> PaymentService` no passa per aquesta capa.

### FINAL
Cada `REQUESTED`, `SUCCEEDED`, `REUSED`, `REJECTED` o `FAILED` ha de conservar request/correlation/actor/role i enllaç amb `UUID_PAYMENT`.

## P-PAG-09 · Reintents i conflictes

### ACTUAL
- mateixa clau + payload equivalent: reutilització;
- mateixa clau + payload diferent: 409;
- col·lisió SQL concurrent: rellegir i comparar;
- hash v1 històric: semàntica legacy;
- hash v2: payload canònic.

**Estat:** implementat al SIF i cobert per proves definides.

### FINAL
La mateixa propietat ha d’arribar també a la pantalla llegada mitjançant un request-id/idempotency-key durable; encara no es pot implementar correctament sense el cos real del servei llegat o un adaptador SIF explícit.

## P-PAG-10 · Errors i recuperació

### ACTUAL
El nucli SIF fa rollback de la transacció si falla una assignació/factura. La pantalla llegada retorna text/HTML i no té contracte de recuperació SIF acreditat.

### FINAL
Resposta tipificada `CREATED | REUSED | CONFLICT | PENDING_RETRY | ERROR`, sense repetir el cobrament quan només ha fallat la sincronització secundaria.
