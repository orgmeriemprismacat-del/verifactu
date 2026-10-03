# UC-016 · Activitats per pàgina — ACTUAL / FINAL

**Objectiu d'aquest document:** completar la cobertura d'activitats de l'UC-016 sobre les superfícies que participen en el pagament i l'emissió de factura de grup. La preparació del grup abans del pagament ja està desglossada a [UC-118 activitats](uc-118-activitats-pagines-grup-actual-final.md).

## P01 · Pàgina de pagament de grup — `PagamentGrupAutomatic.php`

### ACTUAL

La pàgina carrega el grup per `IDPAG`, agrega `A_PAGAR` i `PAGAMENT`, obté dades de `respGrups` i presenta opcions de targeta o transferència. La UI envia `idPag` i imports al pas següent.

```plantuml
@startuml
title UC016 P01 ACTUAL - PagamentGrupAutomatic
start
:Rebre/desxifrar identificador de grup;
:Consultar inscripcions per IDPAG i TIPUS_INSC=G;
:Agregar A_PAGAR i PAGAMENT;
:Carregar responsable de respGrups;
if (Import pendent > 0?) then (Sí)
 :Mostrar targeta i transferència;
 :Incloure IDPAG/imports al formulari;
else (No)
 :Mostrar grup pagat;
endif
stop
@enduml
```

### FINAL

```plantuml
@startuml
title UC016 P01 FINAL - Estat autoritatiu del grup
start
:Resoldre token/actor i IDPAG al servidor;
:Carregar grup, receptor i participants elegibles;
:Revalidar estat, import pendent, versió i cobertura fiscal;
:Carregar regla/tram comercial congelable;
if (Grup pagable i coherent?) then (Sí)
 :Mostrar import autoritatiu i mètodes permesos;
 :No confiar imports del navegador;
else (No)
 :Bloquejar pagament i mostrar incidència/estat;
endif
stop
@enduml
```

**Estat:** ACTUAL implementat llegat; FINAL pendent de gate de grup i autorització completa.

## P02 · JS de la pàgina de pagament — `mostrarPagamentGrupAutomatic.min.js`

### ACTUAL

El JS carrega la vista per AJAX, valida camps al navegador i acaba enviant el formulari. Aquesta validació és UX, no autoritat de preu.

```plantuml
@startuml
title UC016 P02 ACTUAL - JS pagament grup
start
:GET mostrar_pagina_pagament_grup_automatic;
:Renderitzar vista;
:Usuari informa titular/import si escau;
:Validacions JS;
if (Validació client correcta?) then (Sí)
 :Enviar formulari al pas Redsys;
else (No)
 :Mostrar errors;
endif
stop
@enduml
```

### FINAL

```plantuml
@startuml
title UC016 P02 FINAL - Client no decideix import
start
:Renderitzar dades retornades pel backend;
:Usuari confirma pagament;
:Enviar només identificadors i dades necessàries;
:Backend recalcula/revalida grup i import;
if (Snapshot vigent?) then (Sí)
 :Crear/reutilitzar intenció SIF GRUP;
else (No)
 :Retornar conflicte i nova previsualització;
endif
stop
@enduml
```

**Estat:** JS ACTUAL localitzat; cal eliminar qualsevol dependència fiscal del valor hidden/client.

## P03 · Preparació Redsys — `pagina_efectuar_pagament_grup_automatic.php`

### ACTUAL

La pàgina conté integració nova per `PACK`: `PackPaymentGate` + `SifPaymentIntentClient`. Per `GRUP` no hi ha un gate equivalent; si no hi ha checkout PACK validat, conserva la URL del callback llegat.

```plantuml
@startuml
title UC016 P03 ACTUAL - Preparació Redsys compartida
start
:POST idPag i dades del formulari;
:Consultar TIPUS_INSC;
if (És PACK?) then (Sí)
 :PackPaymentGate reconstrueix checkout;
 :Crear intenció SIF PACK;
 :Usar callback SIF;
else (No - GRUP)
 :Continuar circuit històric;
 :Construir paràmetres Redsys;
 :Usar callback realitzaPagamentGrupAutomatic.php;
endif
stop
@enduml
```

### FINAL

```plantuml
@startuml
title UC016 P03 FINAL - Intenció SIF GRUP
start
:POST identificador de grup;
:GroupPaymentGate llegeix només BD;
:Validar responsable, membres, tram i imports;
:Construir snapshot immutable del grup;
:GroupIntentSnapshotValidator comprova invariant;
:Crear/reutilitzar RedsysPaymentIntent source=GRUP;
if (Intenció coherent?) then (Sí)
 :Signar paràmetres amb EXPECTED_AMOUNT;
 :Configurar callback SIF segur;
else (No)
 :No obrir Redsys;
endif
stop
@enduml
```

**Estat:** `GroupIntentSnapshotValidator` implementat en la branca d'auditoria; `GroupPaymentGate` i connexió de la pàgina encara pendents.

## P04 · Confirmació al navegador — `mostrarEfectuarPagamentGrupAutomatic.min.js`

### ACTUAL

El botó de confirmació executa `$('#frm').submit()`; no hi ha lògica fiscal pròpia al JS.

```plantuml
@startuml
title UC016 P04 ACTUAL - Confirmació targeta
start
:Mostrar dades i formulari Redsys;
:Usuari confirma;
:JS submit del formulari signat;
:Sortida cap a Redsys;
stop
@enduml
```

### FINAL

```plantuml
@startuml
title UC016 P04 FINAL - Formulari derivat d'intenció
start
:Mostrar resum de la intenció SIF;
:Usuari confirma;
:Enviar exactament ordre/import signats de la intenció;
:Cap recalcul al client;
stop
@enduml
```

**Estat:** gairebé compatible estructuralment; depèn que P03 sigui autoritatiu.

## P05 · Callback de grup — `realitzaPagamentGrupAutomatic.php`

### ACTUAL

El callback llegat processa resposta Redsys i, en cas autoritzat, consulta el grup, calcula numeració, insereix a `factures` i actualitza pagaments/factura relacionada de les inscripcions.

```plantuml
@startuml
title UC016 P05 ACTUAL - Callback llegat
start
:Rebre callback Redsys;
:Interpretar Ds_Response;
if (Autoritzat?) then (Sí)
 :Consultar respGrups i inscripcions;
 :Calcular dades/número de factura llegada;
 :INSERT factures;
 :Repartir import sobre inscripcions;
 :UPDATE PAGAMENT/FACTURA_RELACIONADA;
 :Enviar comunicacions;
else (No)
 :Registrar observació de denegació;
 :Enviar comunicacions;
endif
stop
@enduml
```

### FINAL

```plantuml
@startuml
title UC016 P05 FINAL - Callback SIF idempotent
start
:Rebre callback al servei SIF;
:Validar signatura, DS_ORDER i import contra intenció;
:Persistir notificació immutable;
:Encolar una vegada;
:Worker reclama job;
if (Notificació validada i coherent?) then (Sí)
 :RedsysGroupInvoiceService usa snapshot congelat;
 :InvoiceService crea/reutilitza factura i CHARGE;
 :Persistir registre fiscal/cua AEAT/relacions;
 :Sincronitzar llegat després del commit;
else (No)
 :Incidència correlacionada sense factura;
endif
stop
@enduml
```

**Estat:** SIF de grup existeix; substitució del callback llegat al canal web pendent.

## P06 · Nucli d'emissió — `RedsysGroupInvoiceService` + builders

### ACTUAL / IMPLEMENTAT SIF

```plantuml
@startuml
title UC016 P06 SIF - Emissió inicial de grup
start
:Carregar snapshot congelat o llegat;
:LegacyGroupInvoicePayloadBuilder crea N línies;
:Afegir relació GRUP i N relacions INSCRIPCIO;
:Afegir bloc payment Redsys validat;
:InvoiceService valida idempotència;
:Crear factura/línies/registre/relacions;
:Crear payment_transaction i allocation factura;
:Encolar registre fiscal;
stop
@enduml
```

**Verificat al repositori:** tests existents comproven N línies, relacions, `VISIBLE_ALUMNE=0`, idempotència i un únic pagament. La branca afegeix comprovació aritmètica per línia.

### FINAL addicional

```plantuml
@startuml
title UC016 P06 FINAL - Invariants addicionals
start
:Validar snapshot GRUP abans de Redsys;
:Validar base-descompte=total per línia;
:Validar suma de línies=EXPECTED_AMOUNT;
:Validar callback amount=EXPECTED_AMOUNT;
:Emetre/reutilitzar factura;
:Registrar atribució quantitativa per ID_INSC;
stop
@enduml
```

**Pendent:** atribució monetària individual i evidència callback↔intent↔import.

## P07 · Transferència/manual — `ManualGroupInvoiceService`

### ACTUAL SIF

```plantuml
@startuml
title UC016 P07 ACTUAL SIF - Cobrament manual grup
start
:Operador aporta IDPAG, import, data i referència;
:Carregar snapshot llegat;
:Construir factura de grup;
:Comprovar import cobrament=total factura;
:Crear clau idempotent;
:InvoiceService crea/reutilitza factura+CHARGE;
stop
@enduml
```

### FINAL

```plantuml
@startuml
title UC016 P07 FINAL - Manual autoritzat i conciliable
start
:Ordre autenticada amb actor/rol i evidència bancària;
:Revalidar factura prèvia/cobertura i estat;
if (Factura ja existeix?) then (Sí)
 :Registrar CHARGE contra factura existent;
else (No)
 :Classificar si emissió i cobrament poden ser atòmics;
endif
:Traçar referència, actor i atribucions per participant;
stop
@enduml
```

**Estat:** servei SIF implementat/provat en fixtures; integració operativa i permisos finals pendents.

## P08 · Afegir participant després d'emetre — UC-016A

### ACTUAL

No s'ha localitzat un endpoint/coordinador executable específic. Hi ha serveis genèrics d'emissió/rectificació i documentació UML.

```plantuml
@startuml
title UC016A ACTUAL - Sense orquestrador específic
start
:Factura de grup ja emesa;
:Existeixen serveis genèrics SIF;
note right
 No existeix evidència d'un flux únic
 addParticipantAfterIssue executable.
end note
stop
@enduml
```

### FINAL

```plantuml
@startuml
title UC016A FINAL - Alta postemissió
start
:Previsualitzar nou participant i grup original;
:Validar plaça, duplicat, actor, versió i tram;
:Classificar impacte fiscal sense editar original;
:Registrar event idempotent;
:Crear/vincular inscripció;
if (Cal document nou/corrector?) then (Sí)
 :Emetre document classificat;
endif
if (Arriba cobrament real nou?) then (Sí)
 :Registrar un CHARGE i atribuir-lo al nou ID_INSC;
endif
stop
@enduml
```

## P09 · Treure participant després d'emetre — UC-016B

### ACTUAL

No s'ha localitzat un coordinador executable de baixa individual de grup facturat.

```plantuml
@startuml
title UC016B ACTUAL - Peces genèriques sense coordinació
start
:Factura de grup ja emesa;
:Existeixen rectificació/refund/saldo genèrics;
note right
 No hi ha evidència d'un coordinador
 que quantifiqui la part individual.
end note
stop
@enduml
```

### FINAL

```plantuml
@startuml
title UC016B FINAL - Baixa postemissió
start
:Localitzar ID_INSC, línia fiscal i fons atribuïts;
:Validar baixa, titular, actor i idempotència;
:Calcular efecte comercial dels restants;
:Classificar correcció fiscal;
:Registrar baixa acadèmica;
if (Hi ha retorn real?) then (Sí)
 :Registrar REFUND al titular correcte;
elseif (Es crea saldo?) then (Sí)
 :Registrar CREDIT;
endif
:Conservar imports dels altres membres excepte correcció aprovada;
stop
@enduml
```

## Matriu final de pàgines/superfícies

| Superfície | ACTUAL | FINAL | Estat |
| --- | --- | --- | --- |
| P01 pagament grup | Llegat, dades agregades | estat autoritatiu | parcial |
| P02 JS pagament | validació client + submit | client no decideix preu | parcial |
| P03 preparar Redsys | SIF només PACK; GRUP llegat | gate + intent GRUP | pendent |
| P04 confirmació | submit Redsys | compatible si intenció és autoritativa | parcial |
| P05 callback | factura/updates llegats | callback+cua+worker SIF | pendent |
| P06 emissió SIF | implementada | completar invariants/ledger | parcial avançat |
| P07 manual | servei implementat | permisos/conciliació/evidència | parcial |
| P08 UC-016A | disseny | coordinador executable | pendent |
| P09 UC-016B | disseny | coordinador executable | pendent |

## Traçabilitat

[UC-016 fitxa](../06-fitxes-funcionals/uc-016.md) · [UC-016 UML](uc-016-facturar-grup.md) · [UC-016A](uc-016a-afegir-participant-grup-emes.md) · [UC-016B](uc-016b-treure-participant-grup-emes.md) · [UC-118 activitats](uc-118-activitats-pagines-grup-actual-final.md) · [UC-091 trams](uc-091-descompte-grup-per-trams.md) · [auditoria](../../00-control/auditoria-uc-016-2026-10-03.md).
