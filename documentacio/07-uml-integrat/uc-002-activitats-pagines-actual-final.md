# UC-002 · Activitats ACTUAL / FINAL per pàgina i apartat

**Data:** 2026-10-04  
**Objectiu:** traçar cada superfície visible/executable de UC-002 i separar codi real de disseny FINAL.

## P-PAG-01 · `/alumnes/pagaments/` — entrada i cerca

### ACTUAL

~~~mermaid
flowchart TD
A[Obrir Pagaments] --> B[Buscar per DNI / codi regal / número factura]
B --> C{criteri vàlid?}
C -- no --> X[Error client]
C -- sí --> D[GET buscarInfomacioPagament.php]
D --> E[Intranet::mostrarPagaments]
E --> F[Consultes legacy]
F --> G[HTML de resultats]
~~~

**Documentat:** sí.  
**Implementat:** sí.  
**Verificat:** `alumnes-pagaments.php`, JS, endpoint i `Intranet::mostrarPagaments()` localitzats.  
**Pendent:** traça d'accés/minimització de dades si la política final ho exigeix.

### FINAL

La cerca només llegeix. Ha d'identificar factura SIF/estat/pendent sense registrar cap moviment.

---

## P-PAG-02 · Resultat — edició d'import, data, banc i observacions

### ACTUAL

~~~mermaid
flowchart TD
A[Resultat] --> B[Editar import]
B --> C[Editar data]
C --> D[Seleccionar banc]
D --> E[Observacions]
E --> F{validacions JS}
F -- error --> X[Missatge]
F -- ok --> G{efact = 1?}
G -- sí --> H[Modal de confirmació]
G -- no --> I[Mutació directa]
H --> I
~~~

**Risc:** la validació client no és frontera de seguretat. La branca afegeix validació al servidor.

### FINAL

La UI ha de mostrar també:
- UUID/número de factura SIF;
- import fiscal immutable;
- import pendent;
- referència/evidència externa;
- idempotency/request-id;
- estat de conciliació.

---

## P-PAG-03 · `mostrarModalConfPag.php` — confirmació de factura existent

### ACTUAL

~~~mermaid
flowchart TD
A[GET numFact] --> B[Intranet::mostrarModalConfPag]
B --> C[HTML modal]
C --> D[Operador confirma]
~~~

És lectura/confirmació visual; no és la mutació.

### FINAL

La modal ha de confirmar les dades resoltes pel servidor i no confiar en dades econòmiques manipulables del DOM.

---

## P-PAG-04 · `efectuarPagament.php` — frontera llegada de mutació

### ACTUAL després de l'auditoria

~~~mermaid
flowchart TD
A[POST] --> B{sessió vàlida?}
B -- no --> X[401]
B -- sí --> C{origen / XHR admès?}
C -- no --> Y[403]
C -- sí --> D{rol /alumnes/pagaments/?}
D -- no --> Y
D -- sí --> E{import > 0 i camps vàlids?}
E -- no --> Z[422]
E -- sí --> F[Intranet::efectuarPagament]
F --> G[Resposta legacy]
~~~

**Implementat a la branca:** POST-only, no-store, sessió, origen, XHR, rol i validació server-side.  
**Pendent:** CSRF sincronitzador explícit si es requereix; idempotència durable; resposta JSON tipificada; delegació al SIF.

---

## P-PAG-05 · `Intranet::efectuarPagament()` — bifurcació `efact`

### ACTUAL

~~~mermaid
flowchart TD
A[efectuarPagament] --> B{efact}
B -- 1 --> C[efectuarPagamentFacturaGenerada]
B -- 0 --> D[calcular any/ordre/número factura]
D --> E{tipus}
E --> F[Regal]
E --> G[Grupal]
E --> H[Pack]
E --> I[Inscripció]
F --> J[pot generar factura]
G --> J
H --> J
I --> J
~~~

**Conclusió d'abast:** només la branca `efact=1` representa directament “cobrar factura existent”. `efact=0` és superfície llegada de facturació-en-cobrament i s'ha de migrar als UC d'emissió corresponents.

---

## P-PAG-06 · `efectuarPagamentFacturaGenerada()` — factura existent

### ACTUAL corregit a la branca

~~~mermaid
flowchart TD
A[Buscar factura per NUM] --> B[Carregar factura/membres]
B --> C[Calcular pendent]
C --> D[UPDATE data_pagament + forma]
D --> E[Conservar IMPORT factura]
E --> F[Recórrer membres per ID]
F --> G{nova fracció < pendent membre?}
G -- sí --> H[PAGAMENT = anterior + fracció]
G -- no --> I[PAGAMENT = A_PAGAR]
H --> J[UPDATE inscripció]
I --> J
J --> K{més import?}
K -- sí --> F
K -- no --> L[actualitzar FRACCIO si aplica]
L --> M[enviar correus]
~~~

### Correccions acreditades

- `factures.IMPORT` no es modifica pel cobrament;
- el pagament parcial s'acumula;
- s'eliminen echoes de depuració.

### PENDENT bloquejant

No hi ha transacció global, idempotència SIF, audit event ni retry de correu/sync.

---

## P-PAG-07 · `/api/payments/register.php` — comanda SIF

### ACTUAL a la branca

~~~mermaid
flowchart TD
A[POST raw JSON] --> B[InternalApiAuthenticator]
B --> C[claim request_id]
C --> D{rol a payments.write_roles?}
D -- no --> X[403]
D -- sí --> E[PaymentPayloadValidator]
E --> F[PaymentService]
F --> G[PaymentRepository]
G --> H[payment_transaction]
G --> I[payment_allocation]
I --> J[recalcular ESTAT_COBRAMENT]
J --> K[JSON UUID_PAYMENT + reused + actor/request]
~~~

**Implementat:** frontera segura + nucli.  
**Pendent:** PaymentActionGateway, evidència externa, atribució genèrica per inscripció.

---

## P-PAG-08 · Idempotència

### ACTUAL

~~~mermaid
flowchart TD
A[clau K] --> B[SELECT FOR UPDATE]
B --> C{existeix?}
C -- no --> D[crear hash v2 + payment]
C -- sí --> E{hash version}
E -- 1 --> F[comparar serialització legacy]
E -- 2 --> G[comparar payload canònic]
F --> H{equivalent?}
G --> H
H -- sí --> I[REUSED mateix UUID]
H -- no --> J[409 CONFLICT]
~~~

**Verificat per inspecció:** sí.  
**Tests:** definits.  
**Pendent:** portar la mateixa propietat fins a la UI llegada.

---

## P-PAG-09 · Invariants monetaris

### ACTUAL a la branca

~~~mermaid
flowchart TD
A[payload] --> B{amount > 0 i <=2 decimals?}
B -- no --> X[422]
B -- sí --> C[validar cada allocation >0]
C --> D[sumar en cèntims]
D --> E{SUM = amount?}
E -- no --> X
E -- sí --> F[continuar]
~~~

Això impedeix registrar un moviment de 120 € amb 100 € assignats, imports negatius o precisió monetària arbitrària.

---

## P-PAG-10 · Persistència de factura

### ACTUAL

`PaymentRepository` bloqueja la factura, suma:
- `CHARGE` i `COMPENSATION`;
- `REFUND`;

i delega l'estat a `PaymentStatusCalculator`.

**No crea ni modifica registres fiscals.**

### FINAL

Mantenir aquest model. La factura fiscal és immutable; només varia l'estat econòmic relacionat.

---

## P-PAG-11 · Auditoria funcional

### ACTUAL

`PaymentActionGateway`, `PaymentActionEventWriter`, `PaymentActionEventRepository` i `payment_action_event` existeixen.

**Però:** `payments/register.php` no els utilitza.

### FINAL

~~~mermaid
flowchart TD
A[REQUESTED] --> B[PaymentService]
B --> C{resultat}
C -- creat --> D[SUCCEEDED]
C -- reús --> E[REUSED]
C -- error --> F[FAILED/REJECTED]
D --> G[payment_action_event]
E --> G
F --> G
~~~

Cal redissenyar el boundary transaccional abans de connectar el gateway, perquè el runner actual no suporta transaccions imbricades.

---

## P-PAG-12 · Ledger per inscripció

### ACTUAL

Existeixen `EnrollmentFundMovementRepository` i serveis d'assignació en fluxos concrets.

### FINAL

Quan el cobrament cobreix una o més inscripcions:

~~~mermaid
flowchart TD
A[UUID_PAYMENT] --> B[payment_allocation a factura]
B --> C[resoldre línies SOURCE_TYPE=INSCRIPCIO]
C --> D[atribuir imports per ID_INSC]
D --> E[enrollment_fund_movement]
E --> F{sumes reconciliades?}
F -- no --> X[incidència]
F -- sí --> G[commit]
~~~

No s'han de crear cobraments nous per redistribuir diners ja rebuts.

---

## P-PAG-13 · Correus i efectes secundaris

### ACTUAL

El llegat envia correus dins `efectuarPagamentFacturaGenerada()`.

### FINAL

Els correus han de sortir d'un outbox/post-commit. Una fallada SMTP no pot convertir-se en motiu per repetir el cobrament.

---

## P-PAG-14 · Errors, retry i recuperació

### FINAL

~~~mermaid
flowchart TD
A[Comanda] --> B{SIF commit?}
B -- no --> C[ERROR / no CHARGE]
B -- sí --> D{sync llegat ok?}
D -- sí --> E[CREATED/REUSED]
D -- no --> F[PENDING_RETRY]
F --> G[retry sync amb mateix UUID_PAYMENT]
G --> H[mai segon CHARGE]
~~~

Contracte objectiu: `CREATED | REUSED | CONFLICT | PENDING_RETRY | ERROR`.
