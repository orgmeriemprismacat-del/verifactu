# UC-014 — Diagrames d'activitat ACTUAL i FINAL per pàgina i apartat

**Data d'auditoria:** 29/09/2026  
**Compliment RM-037:** aquest document separa pàgines, apartats i processos servidor. Les capçaleres, cookies i components comuns no es reclasifiquen com UC-014.

## 0. Superfícies i apartats

| ID | Pàgina/procés | Apartats UC-014 |
| --- | --- | --- |
| P-CUR-01 | `pagina_confirmacio_inscripcio_automatic.php` + `PagamentCursAutomatic::mostrarPaginaConfirmacio()` | A càrrega; B estat/preu; C targeta/transferència; D variants/descompte compartides |
| P-CUR-02 | `pagina_pagament_automatic.php` + `PagamentCursAutomatic::mostrar()` | A dades curs; B pendent; C targeta; D transferència; E fraccionat |
| P-CUR-03 | `pagina_efectuar_pagament_automatic.php` | A POST; B DS_ORDER; C imports; D formulari Redsys; E cancel·lar/confirmar |
| P-CUR-04 | Redsys + `realitzaPagamentAutomatic.php` | A recepció; B validació; C factura; D cobrament/fracció; E correus/estat |
| P-CUR-05 | `respostaOkPagamentAutomatic.php` / `respostaKoPagamentAutomatic.php` | A retorn navegador; B missatge; C consulta estat real FINAL |
| P-CUR-06 | SIF asíncron | A intenció; B callback; C cua; D worker; E factura/cobrament; F atribució quantitativa; G sync/outbox |

## 1. P-CUR-01 — Confirmació d'inscripció

### 1.1 ACTUAL

```plantuml
@startuml
title P-CUR-01 ACTUAL | Confirmació d'inscripció
start
:Carregar pàgina;
:JS extern omple el contenidor de confirmació;
:PagamentCursAutomatic recupera inscripció per IDPAG;
:Recupera curs/edició;
:Calcula A_PAGAR - PAGAMENT;
if (Curs JASOM?) then (Sí)
  :Consulta recent_titulat;
  note right
    UC-111 comparteix aquesta pantalla.
    Existeix una inicialització escrita com comparació ==.
  end note
endif
if (Import pendent?) then (Sí)
  :Decideix si mostra targeta/transferència;
else (No)
  :Mostra estat pagat;
endif
:Mostra confirmació i accions disponibles;
stop
@enduml
```

### 1.2 FINAL

```plantuml
@startuml
title P-CUR-01 FINAL | Confirmació basada en estat servidor
start
:Resoldre inscripció i actor al servidor;
:Revalidar curs/edició/places/estat comercial;
:Calcular import pendent des del ledger i snapshot vigent;
if (Acció de pagament permesa?) then (Sí)
  :Mostrar import i opcions habilitades;
else (No)
  :Mostrar motiu/estat sense permetre saltar controls;
endif
:No crear factura ni CHARGE;
stop
@enduml
```

## 2. P-CUR-02 — Pàgina de pagament

### 2.1 ACTUAL

```plantuml
@startuml
title P-CUR-02 ACTUAL | Estat i opcions
start
:SELECT inscripcions per IDPAG;
:SELECT curs per ANY/MES/CURS;
:Calcula faltaPagar;
if (faltaPagar > 0?) then (No)
  :Mostra curs pagat;
  stop
else (Sí)
  if (Targeta habilitada?) then (Sí)
    :Renderitza formulari targeta;
  endif
  if (Transferència habilitada?) then (Sí)
    :Renderitza instruccions transferència;
  endif
  if (Fraccionat?) then (Sí)
    :Mostra pagat, pendent i import a introduir;
  endif
endif
:Usuari inicia pas de confirmació TPV;
stop
@enduml
```

### 2.2 FINAL

```plantuml
@startuml
title P-CUR-02 FINAL | Opcions amb dades autoritatives
start
:Carregar estat econòmic des de SIF/ledger;
:Carregar regles comercials i elegibilitat;
if (No hi ha saldo pendent?) then (Sí)
  :Mostrar pagat i impedir nova intenció;
  stop
endif
:Calcular límits de pagament al servidor;
if (Fraccionament permès?) then (Sí)
  :Proposar imports vàlids;
else (No)
  :Fixar import pendent complet;
endif
:El navegador només selecciona una opció;
:El servidor recomputa abans de crear intenció;
stop
@enduml
```

## 3. P-CUR-03 — Confirmació i enviament a Redsys

### 3.1 ACTUAL

```plantuml
@startuml
title P-CUR-03 ACTUAL | Preparar Redsys
start
:Rebre POST idPag, curs, titular, email, imports, frac;
:Convertir importPagare;
:Crear DS_ORDER = time();
:Crear MerchantURL amb idPag/curs/dni/order/frac/import;
:Crear URL OK/KO;
:Crear DS_MERCHANT_AMOUNT = importPagare * 100;
:Signar petició Redsys;
:Renderitzar formulari;
if (Usuari confirma?) then (Sí)
  :POST a Redsys;
else (No)
  :Cancel·lar i tornar;
endif
stop
@enduml
```

### 3.2 FINAL

```plantuml
@startuml
title P-CUR-03 FINAL | Crear intenció abans del TPV
start
:Rebre només decisió de pagament i referència d'inscripció;
:Recarregar inscripció, preu, descompte, receptor i estat;
:Bloqueig/concurrència;
if (Dades vàlides i import > 0?) then (No)
  :Retornar error sense intenció;
  stop
endif
:Construir snapshot immutable;
:Crear/reutilitzar redsys_payment_intent;
if (Mateix DS_ORDER amb payload diferent?) then (Sí)
  :CONFLICT sense efectes;
  stop
endif
:Construir petició Redsys des de la intenció;
:MerchantURL identifica el callback sense transportar import negociable;
:Redirigir a Redsys;
stop
@enduml
```

**Implementació al repositori:** el pont candidat ja crea/reutilitza la intenció mitjançant `SifRedsysCourseIntentClient`. La MerchantURL SIF exigeix `SIF_REDSYS_COURSE_CUTOVER_ENABLED=1` i `SIF_REDSYS_CALLBACK_URL` HTTPS; la URL sola no activa el tall i amb el flag a `0` es conserva el callback llegat com a transició/rollback. El tall d'entorn continua **PENDENT D'ACREDITAR**.

## 4. P-CUR-04 — Callback servidor

### 4.1 ACTUAL

```plantuml
@startuml
title P-CUR-04 ACTUAL | Callback monolític
start
:Rebre GET funcional + POST Redsys;
:Decodificar MerchantParameters;
:Calcular signatura de notificació;
note right
  A la còpia auditada no s'ha localitzat
  la comparació posterior firma rebuda/calculada.
end note
:Llegir Ds_Order, Ds_Amount, Ds_Response;
if (Ds_Response autoritzat?) then (Sí)
  :SELECT inscripció per IDPAG;
  :SELECT curs;
  :Enviar avisos interns;
  :Calcular factura_relacionada;
  :Calcular ordre fiscal amb últim+1;
  :INSERT factures;
  :UPDATE PAGAMENT/FACTURA_RELACIONADA;
  if (pagat complet?) then (Sí)
    :UPDATE DATA PAG;
  endif
  if (fraccionat?) then (Sí)
    :UPDATE FRACCIO;
  endif
  :Enviar correus i altres efectes;
else (No)
  :Camí de denegació/error;
endif
stop
@enduml
```

### 4.2 FINAL

```plantuml
@startuml
title P-CUR-04 FINAL | Callback només valida i encola
start
:Rebre notificació Redsys;
:Validar signatura criptogràfica;
:Localitzar intenció per DS_ORDER;
:Comparar import, moneda, terminal i identitat;
if (No coincideix?) then (Sí)
  :Registrar incidència;
  :No facturar ni registrar CHARGE;
  stop
endif
:Persistir/reutilitzar notificació VALIDATED;
:Encolar/reutilitzar job;
:Retornar HTTP tècnic;
:Cap factura al procés HTTP del callback;
stop
@enduml
```

## 5. P-CUR-05 — Retorn del navegador

### 5.1 ACTUAL

```plantuml
@startuml
title P-CUR-05 ACTUAL | OK/KO navegador
start
:Redsys redirigeix a URL OK o KO;
if (OK?) then (Sí)
  :Mostrar "pagament registrat correctament";
else (No)
  :Mostrar pagament denegat/error;
endif
stop
@enduml
```

### 5.2 FINAL

```plantuml
@startuml
title P-CUR-05 FINAL | UX no autoritativa
start
:Redsys retorna el navegador;
:Consultar estat real per DS_ORDER/intent;
if (Worker ja ha confirmat cobrament?) then (Sí)
  :Mostrar pagament confirmat;
else (No)
  if (Denegat/invalidat?) then (Sí)
    :Mostrar denegació real;
  else (Pendent)
    :Mostrar "pagament en comprovació";
  endif
endif
:No crear ni corregir factura des del retorn navegador;
stop
@enduml
```

**Implementació al repositori:** `respostaOkPagamentAutomatic.php` i `respostaKoPagamentAutomatic.php` deleguen a `CoursePaymentReturnStatus`, que consulta per HMAC `SifRedsysCourseStatusClient` → `POST /api/redsys/course-status.php` → `RedsysCoursePaymentStatusService`. Els estats exposats són `PENDING`, `PROCESSING`, `CONFIRMED`, `REJECTED` i `REVIEW`; `CONFIRMED` exigeix job `PROCESSED` amb `UUID_FACTURA` i `UUID_PAYMENT`. Si la consulta SIF no està activa o falla, el retorn queda `UNVERIFIED/PENDING` i no afirma èxit.

**Verificació:** `RedsysCoursePaymentStatusServiceTest` i `RedsysCourseReturnBoundaryTest` han passat als tres workflows del PR #55. **Desplegament/preproducció real:** no acreditats.

## 6. P-CUR-06 — Worker SIF

### 6.1 ACTUAL

No existeix equivalent llegat separat: les responsabilitats es concentren en el callback.

### 6.2 FINAL

```plantuml
@startuml
title P-CUR-06 FINAL | Worker SIF
start
:claimNext() amb lock;
if (Hi ha job?) then (No)
  stop
endif
:Dispatcher resol sourceType=CURS;
:Carregar snapshot de la intenció;
:LegacyCourseInvoicePayloadBuilder.build();
:RedsysInvoicePayloadBuilder exigeix VALIDATED;
:InvoiceService.issueInvoice();
if (idempotència reutilitza resultat?) then (Sí)
  :Retornar UUIDs existents;
else (No)
  :Crear factura/línies/registre/cua AEAT;
  :Crear payment_transaction/allocation;
endif
:CourseEnrollmentFundAllocationService valida CHARGE/factura/línia/import;
:Crear/reutilitzar enrollment_fund_movement EXTERNAL_ALLOCATION per DS_ORDER + ID_INSC;
if (Falla atribució quantitativa?) then (Sí)
  :Retry/incidència sense projectar pagament al llegat;
  :No crear segona factura ni segon CHARGE;
  stop
endif
:CourseLegacyPaymentSyncService projecta PAGAMENT/DATA PAG/M→1;
if (Falla sync llegada?) then (Sí)
  :Registrar incidència/retry sense refacturar;
  stop
endif
:CoursePaymentNotificationService crea/reutilitza outbox CURS;
note right
  Això només acredita una ordre durable PENDING.
  El worker/transport d'email és UC-58 i continua pendent.
end note
if (Falla l'enqueue?) then (Sí)
  :Retry/incidència sense segona factura ni CHARGE;
  stop
endif
:Marcar job PROCESSED;
stop
@enduml
```

## 7. Apartats transversals i variants

### 7.1 Pagament parcial

```plantuml
@startuml
title UC-014 | Fraccionament FINAL
start
:Llegir total contractual i suma d'assignacions efectives;
:Calcular pendent;
:Validar import nou > 0 i <= pendent;
:Crear intenció pel tram;
:Callback validat crea un CHARGE immutable;
:Crear/reutilitzar `EXTERNAL_ALLOCATION` a `enrollment_fund_movement` per DS_ORDER + ID_INSC;
:Recalcular estat PAID/PARTIALLY_PAID des dels moviments confirmats;
:No sobreescriure l'històric de cobraments;
stop
@enduml
```

### 7.2 Callback duplicat

```plantuml
@startuml
title UC-014 | Duplicat FINAL
start
:Arriba callback DS_ORDER X;
:Buscar notificació/intenció;
if (Equivalent?) then (Sí)
  :Reutilitzar notificació/job/factura/pagament;
  :No crear segon ingrés;
else (No)
  :CONFLICT + incidència;
endif
stop
@enduml
```

### 7.3 Sincronització acadèmica fallida

```plantuml
@startuml
title UC-014 | Sync acadèmica FINAL
start
:Factura i CHARGE ja confirmats;
:Intentar actualitzar estat acadèmic/llegat;
if (Èxit?) then (Sí)
  :Marcar sincronització completada;
else (No)
  :Registrar incidència recuperable;
  :Reintentar la sincronització;
  :No emetre segona factura ni segon CHARGE;
endif
stop
@enduml
```

**Evidència A14-17 — 02/10/2026:** `CourseEnrollmentFundAllocationServiceTest` cobreix alta/reús, trams parcials i mismatch fail-closed; `RedsysCourseEndToEndSimulatedTest` exigeix un únic moviment al complet/duplicat i dos moviments amb suma 120,00 al parcial→complet. Suites SIF: **841 passed / 0 failed**.

## 8. Cobertura i límits

- Els JS externs referenciats per les pàgines actuals no s'han localitzat en la mateixa còpia auditada; la traça JS continua **PENDENT**.
- El runtime productiu no s'ha inspeccionat.
- UC-111 comparteix branques de la pantalla de pagament; aquesta fitxa només en deixa la dependència, no redefineix les seves regles.
- Taller i jornada continuen a UC-014a/014b.
