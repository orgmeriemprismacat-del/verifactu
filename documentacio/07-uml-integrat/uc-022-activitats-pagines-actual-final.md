# UC-022 — Diagrames d'activitat ACTUAL i FINAL per pàgina i apartat

**Data:** 03/10/2026  
**Etiqueta ACTUAL:** codi inspeccionat, no prova de desplegament.  
**Etiqueta FINAL:** contracte objectiu, no implica implementació.

## 0. Índex

| ID | Superfície | Estat |
| --- | --- | --- |
| P01 | Intranet `/alumnes/pagaments/` — cerca | ACTUAL |
| P02 | Resultats — import/data/banc i confirmació | ACTUAL |
| P03 | `ajax/alumnes/efectuarPagament.php` | ACTUAL |
| P04 | Servei manual SIF | IMPLEMENTAT però no connectat al canal |
| P05 | Registre final una factura | FINAL / endpoint SIF parcialment implementat |
| P06 | Registre final multifactura | FINAL / UC-105 |

## 1. P01 — Cerca ACTUAL

```plantuml
@startuml
start
:Obrir /alumnes/pagaments/;
:comprovarSessio.php;
:Carregar main i JS;
:Introduir DNI o codi regal o número factura;
if (Cap criteri?) then (Sí)
 :Mostrar error;
 stop
endif
if (Més d'un criteri?) then (Sí)
 :Mostrar error;
 stop
endif
:GET buscarInfomacioPagament.php;
:Renderitzar resultats HTML;
stop
@enduml
```

## 2. P02 — Preparació i confirmació ACTUAL

```plantuml
@startuml
start
:Operador introdueix PAGAMENT;
:Operador introdueix DATA PAG;
:Operador selecciona BANC;
:JS valida import numèric i diferent de zero;
:JS valida format/data;
:JS valida banc;
if (Errors?) then (Sí)
 :Mostrar modal d'error;
 stop
endif
if (efact == 1?) then (Sí)
 :Mostrar modal previsualització;
 :Operador confirma;
endif
:aplicarPagament(...);
stop
@enduml
```

## 3. P03 — Mutació llegat ACTUAL

```plantuml
@startuml
start
:Enviar GET a efectuarPagament.php;
:session_start();
:unserialize usuari i intranet;
:Llegir id, tipus, pagament, data, banc, obs, numFact, efact des de GET;
:Cridar Intranet::efectuarPagament(...);
:Retornar text/HTML;
note right
  En l'endpoint inspeccionat no consta
  CSRF ni POST ni adaptador SIF.
end note
stop
@enduml
```

## 4. P04 — Nucli SIF disponible

```plantuml
@startuml
start
:Rebre UUID o número visible factura;
:ManualPaymentInvoiceRepository cerca factura;
if (No existeix?) then (Sí)
 :422 validation;
 stop
endif
:Builder valida import > 0, data i mètode;
:Construir CHARGE + una allocation;
:PaymentService valida payload;
:Cercar idempotency key FOR UPDATE;
if (Clau existent?) then (Sí)
 :Comparar payload hash;
 if (Equivalent?) then (Sí)
  :Retornar REUSED;
 else (No)
  :Retornar CONFLICT;
 endif
else (No)
 :INSERT payment_transaction;
 :INSERT payment_allocation;
 :Recalcular ESTAT_COBRAMENT;
 :Retornar CREATED;
endif
stop
@enduml
```

## 5. P05 — FINAL una factura

```plantuml
@startuml
start
 :POST /api/payments/manual-transfer.php;
:Validar HMAC, timestamp, request UUID, actor i rols;
:Bloquejar replay a internal_api_request;
 :Exigir external_bank_event_id del caller;
:Font/resolució bancària real encara pendent a intranet;
if (Entrada bancària no demostrada?) then (Sí)
 :REJECT/REVIEW;
 stop
endif
:Carregar factura SIF i estat actual;
:Comparar import assignat amb saldo/entrada;
if (Conflicte?) then (Sí)
 :CONFLICT sense mutació;
 stop
endif
:ManualPaymentService;
:Commit ledger SIF;
:Registrar auditoria;
:Encolar sincronització llegada;
:Retornar JSON CREATED/REUSED;
stop
@enduml
```

## 6. P06 — FINAL multifactura

```plantuml
@startuml
start
:Identificar una entrada bancària única;
:Seleccionar N factures vigents;
:Assignar import exacte a cada factura;
:Calcular suma trams;
if (Suma > import disponible?) then (Sí)
 :Bloquejar;
 stop
endif
if (Entrada ja existeix al SIF?) then (Sí)
 :No crear segon CHARGE;
 :UC-105 afegeix/reconcilia allocations sobre moviment existent;
else (No)
 :Crear un CHARGE amb N allocations;
endif
if (Resta excés/no assignat?) then (Sí)
 :Derivar UC-104;
endif
:Sincronització llegada posterior al commit;
stop
@enduml
```

## 7. Matriu de completitud

| Pàgina/apartat | ACTUAL documentat | FINAL documentat | Implementat | Verificat execució |
| --- | --- | --- | --- | --- |
| P01 cerca | sí | sí | llegat | no en aquesta auditoria |
| P02 validació/confirmació | sí | sí | llegat | no |
| P03 endpoint mutació | sí | sí | llegat | no |
| P04 servei SIF | sí | sí | sí | tests existents, no executats aquí |
| P05 adaptador autoritzat | n/a | sí | no | no |
| P06 multifactura | n/a | sí | no | no |


**Estat nou P05:** endpoint SIF + HMAC + rols + `external_bank_event_id` implementats; caller intranet, CSRF local del navegador i sincronització llegat continuen pendents.


## P07 — canal intranet segur implementat en branca

```plantuml
@startuml
start
:Usuari selecciona factura existent;
if (efact == 1?) then (sí)
  :Introduir import/data/banc;
  :Introduir ID únic del moviment bancari;
  if (banc == TPV/REDSYS?) then (sí)
    :Bloquejar i derivar a Redsys;
    stop
  endif
  :Obtenir CSRF autenticat;
  :POST registrarTransferenciaSif.php;
  :Refrescar rols vigents;
  :Signar HMAC server-to-server;
  :Anti-replay request UUID;
  if (factura existeix al SIF?) then (sí)
    :Registrar/reutilitzar payment;
    :Escriure audit REQUESTED + terminal;
    :Projectar acumulat SIF al llegat;
    if (sync llegat OK?) then (sí)
      :CREATED o REUSED;
    else (no)
      :PENDING_RETRY;
      :Reintentar amb mateix ID bancari;
    endif
  else (no)
    :Bloquejar;
    :Enviar a migració/reconciliació;
  endif
else (no)
  :Fora UC-022: flux d'emissió + cobrament;
endif
stop
@enduml
```

**Estat P07:** implementat en la branca; execució E2E i desplegament encara no verificats.
