# UC-020 — Diagrames d'activitat ACTUAL i FINAL per pàgina i apartat

**Revisió:** 03/10/2026  
**Cas:** UC-20 · Aplicar Alumne PrisMa  
**Objectiu:** cobrir totes les superfícies on la regla Alumne PrisMa es consulta, aplica, reutilitza o condiciona un pagament.  
**Etiqueta ACTUAL:** lectura estàtica del repositori; no prova de desplegament.  
**Etiqueta FINAL:** contracte objectiu; quan una peça ja és executable s'indica explícitament com a implementada/revalidada.

## 0. Índex de pàgines i apartats

| ID | Superfície | Apartats UC-020 |
| --- | --- | --- |
| P01 | Pàgina pública de descomptes | text Alumne PrisMa, taula orientativa, accés a inscripció |
| P02 | Formulari públic d'inscripció | document, checks/promocions, càlcul de preu, confirmació |
| P03 | Confirmació activa | token, estat de la inscripció, import/saldo, mètodes habilitats |
| P04 | Pàgina activa de pagament | token, saldo, targeta, transferència |
| P05 | Intranet «Validar descomptes» | consulta pendent, decisió, alternativa AP, notificació |
| P06 | Intranet «Canvi de curs» | antecedent històric, prioritat de descomptes, tarifa AP del nou curs |

## 1. P01 · Pàgina pública de descomptes

**Fonts ACTUALS:** `pagina_descomptes.php`, `ajax/mostrar_pagina_descomptes.php`, `PaginaDescomptes.php`.

### 1.1. P01-A — ACTUAL · text Alumne PrisMa

```plantuml
@startuml
title P01-A | ACTUAL | informació pública Alumne PrisMa
start
:Obrir pàgina pública de descomptes;
:Instanciar PaginaDescomptes;
:Renderitzar apartat "Alumnes PrisMa";
:Mostrar text "esteu fent o hàgiu fet un curs";
:Mostrar que el DNI recalcularà automàticament el preu;
note right
  Aquesta pàgina informa.
  No valida l'historial de la persona.
end note
stop
@enduml
```

### 1.2. P01-B — ACTUAL · taula de preus orientativa

```plantuml
@startuml
title P01-B | ACTUAL | taula orientativa
start
:Llegir durades de curs disponibles;
:Seleccionar un ID_PREU representatiu per hores;
:Llegir preu ordinari;
:Llegir descomptes.PREU per TIPUS=1;
:Mostrar original i preu amb descompte;
note right
  La selecció de l'ID_PREU i del descompte
  no usa exactament els mateixos filtres
  que calcularPreu.php.
end note
stop
@enduml
```

### 1.3. P01 — FINAL

```plantuml
@startuml
title P01 | FINAL | informació alineada amb política canònica
start
:Carregar política pública versionada;
:Mostrar definició d'elegibilitat ratificada;
:Mostrar tarifes representatives només si mapegen una oferta vigent;
if (La tarifa depèn de curs/edició?) then (Sí)
 :No prometre un preu genèric únic;
 :Indicar que l'oferta exacta es calcula en seleccionar producte/edició;
else (No)
 :Mostrar tarifa vigent;
endif
:Enllaçar a inscripció;
stop
@enduml
```

## 2. P02 · Formulari públic d'inscripció

**Fonts ACTUALS:** `InscripcioCurs.php`, `mostrarInscripcions.min.js`, `ajax/calcularPreu.php`, `inc/buscarAlumnePrisMa.php`, `ajax/enviarInscripcio.php`.

### 2.1. P02-A — ACTUAL · document i disparadors de recàlcul

```plantuml
@startuml
title P02-A | ACTUAL | document, checks i recàlcul
start
:Persona introdueix DNI/passaport o modifica checks/edició;
if (Esdeveniment change/blur/click?) then (Sí)
 :Cridar calcularPreu();
endif
:Crear AJAX asíncron;
note right
  No s'ha localitzat cancel·lació
  de la petició anterior ni request/version ID.
end note
stop
@enduml
```

### 2.2. P02-B — ACTUAL · selecció del candidat

```plantuml
@startuml
title P02-B | ACTUAL | calcularPreu.php
start
:SELECT descomptes vigents per ID_PREU/curs/mes;
:Recórrer candidats ordenats per TIPUS descendent;
if (Promoció/prioritat > 10?) then (Sí)
 :Seleccionar candidat;
elseif (TIPUS 8/7/6/5/4 i check corresponent?) then (Sí)
 :Seleccionar candidat documental/USOC;
elseif (TIPUS=2 i Carnet Jove?) then (Sí)
 :Comprovar Carnet Jove;
elseif (TIPUS=1?) then (Sí)
 :Incloure buscarAlumnePrisMa.php;
 :Consultar historial per DNI;
 if (Elegible?) then (Sí)
  :Seleccionar tarifa AP;
 else (No)
  :Continuar;
 endif
else
 :Continuar;
endif
:Retornar TIPUS|PREU|missatges;
stop
@enduml
```

### 2.3. P02-C — ACTUAL · concurrència de respostes

```plantuml
@startuml
title P02-C | ACTUAL | possible resposta obsoleta
start
:AJAX P1 amb estat formulari A;
:Usuari modifica formulari;
:AJAX P2 amb estat formulari B;
if (P2 respon primer?) then (Sí)
 :globals = oferta B;
endif
if (P1 respon després?) then (Sí)
 :globals = oferta A;
 note right
   No es comprova que A continuï
   corresponent a l'estat actual.
 end note
endif
stop
@enduml
```

### 2.4. P02-D — ACTUAL · codi promocional vs Alumne PrisMa

```plantuml
@startuml
title P02-D | ACTUAL | promoció i globals comercials
start
:Partir d'un tipusPreuAplicat/preuInscripcio existent;
:Validar codi promocional;
if (Codi vàlid?) then (Sí)
 :Modificar preuInscripcio;
 :Modificar promocioAplicada;
 note right
   aplicarPreuCodiPromocions()
   no garanteix reassignar tipusPreuAplicat.
 end note
else (No)
 :Recalcular sense codi;
endif
stop
@enduml
```

### 2.5. P02-E — ACTUAL · confirmació i inserció

```plantuml
@startuml
title P02-E | ACTUAL | confirmar inscripció
start
:Validar camps personals;
if (Hi ha algun error de camp?) then (Sí)
 :Mostrar errors;
 :Afegir avís si preuInscripcio <= 0;
 stop
else (No)
 :Continuar encara que el control <=0 no sigui una precondició independent;
endif
:Comprovar curs ja realitzat/duplicat;
:Enviar GET a enviarInscripcio.php;
:Enviar tipusDescompte=tipusPreuAplicat;
:Enviar preuDescompte=preuInscripcio;
:Enviar promocions globals;
if (tipusDescompte == 1?) then (Sí)
 :Servidor revalida historial AP;
 :Servidor deriva TIPUS_CURS de `informacio`;
 :Servidor rellegeix tarifa base/AP vigent;
 :Rebutja tarifa ambigua o AP + promoció;
 :Sobreescriu import client amb tarifa servidor;
:Conservar tarifa servidor fins a l'INSERT;
note right
  UC020-94 (03/10): corregit un overwrite tardà
  que tornava a carregar preuDescompte del client.
end note
endif
:INSERT inscripcions;
stop
@enduml
```

### 2.6. P02 — FINAL · oferta servidor immutable

```plantuml
@startuml
title P02 | FINAL | càlcul i acceptació d'oferta
start
:Capturar estat complet del formulari;
:Enviar consulta amb request_version;
:Servidor valida persona, producte, edició i política;
:Cridar CommercialOfferService::createOrReuse();
:Persistir/reutilitzar commercial_operation + discount_validation + operational_event;
:Retornar offer_id/UUID_OPERATION + imports + expiry;
if (Arriba resposta d'una versió antiga?) then (Sí)
 :Descartar;
 stop
endif
:Mostrar oferta actual;
if (Usuari confirma?) then (Sí)
 :Enviar offer_id, no import/tipus declarat pel client;
 :Servidor rellegeix oferta i comprova vigència/versió;
 if (Oferta ACCEPTABLE?) then (Sí)
  :Crear inscripció vinculada a UUID_OPERATION;
 else (No)
  :Retornar conflicte/recalcular;
 endif
endif
stop
@enduml
```

## 3. P03 · Confirmació activa

**Ruta activa:** `/confirmacio/...` → `pagina_confirmacio_inscripcio_automatic.php` → `PagamentCursAutomatic::mostrarPaginaConfirmacio()`.

### 3.1. P03-A — ACTUAL · token i càrrega

```plantuml
@startuml
title P03-A | ACTUAL | token de confirmació
start
:Rebre token opac;
:Decodificar AES-CBC/HMAC;
:Comprovar HMAC amb hash_equals();
if (Token íntegre?) then (No)
 :Bloquejar càrrega;
 stop
else (Sí)
 :Recuperar IDPAG;
 :Carregar PagamentCursAutomatic;
endif
stop
@enduml
```

### 3.2. P03-B — ACTUAL · resum i pagament

```plantuml
@startuml
title P03-B | ACTUAL | confirmació
start
:Llegir A_PAGAR, PAGAMENT, VALID_DESC;
:Calcular faltaPagar;
if (faltaPagar <= 0?) then (Sí)
 :Mostrar estat pagat/sense pendent;
 stop
endif
if (VALID_DESC == 1?) then (Sí)
 :Mostrar opcions previstes de cobrament;
else (No)
 :No habilitar targeta;
 :No oferir transferència en aquesta vista de confirmació;
endif
stop
@enduml
```

### 3.3. P03 — FINAL

```plantuml
@startuml
title P03 | FINAL | resum d'operació comercial
start
:Cridar PaymentLinkService::resolve(token);
:Validar hash, ACTIVE i expiració;
:Recuperar commercial_operation;
:Recuperar discount_validation vigent;
:Recuperar ledger de pagaments/factura;
:Calcular total, cobrat i pendent;
if (Oferta PAYABLE i link ACTIVE?) then (Sí)
 :Mostrar mètodes autoritzats;
else (No)
 :Mostrar PENDING/REVOKED/REQUIRES_ADJUSTMENT;
endif
stop
@enduml
```

## 4. P04 · Pàgina activa de pagament

**Ruta activa:** `/pagament/...` → `pagina_pagament_automatic.php` → `PagamentCursAutomatic::mostrar()`.

### 4.1. P04-A — ACTUAL · targeta

```plantuml
@startuml
title P04-A | ACTUAL | targeta
start
:Carregar inscripció;
:Calcular saldo pendent;
if (VALID_DESC == 1?) then (Sí)
 :Construir formulari de targeta;
else (No)
 :No construir targeta;
endif
stop
@enduml
```

### 4.2. P04-B — ACTUAL · transferència

```plantuml
@startuml
title P04-B | ACTUAL | transferència
start
:Carregar inscripció;
:Calcular saldo pendent;
:Construir instruccions de transferència;
note right
  En el mètode inspeccionat no hi ha
  la mateixa comprovació VALID_DESC==1
  que a la targeta.
end note
stop
@enduml
```

### 4.3. P04-C — ACTUAL · denegació + AP alternatiu

```plantuml
@startuml
title P04-C | ACTUAL | TIPUS_DESC=1 / VALID_DESC=2
start
:Descompte documental denegat;
:Comprovar Alumne PrisMa;
if (Elegible?) then (Sí)
 :TIPUS_DESC=1;
 :VALID_DESC=2;
 :A_PAGAR=preu AP;
endif
:Correu ofereix targeta + transferència;
:Obrir /pagament/;
:Targeta NO perquè VALID_DESC!=1;
:Transferència pot aparèixer;
stop
@enduml
```

### 4.4. P04 — FINAL

```plantuml
@startuml
title P04 | FINAL | autorització única de cobrament
start
:Cridar PaymentLinkService::resolve(token);
:Recuperar UUID_OPERATION i EXPECTED_AMOUNT;
:Comprovar ACTIVE, expiry i vigència de l'operació;
:Calcular saldo des del ledger;
if (Operació PAYABLE?) then (Sí)
 :Habilitar només mètodes autoritzats;
 :Crear/reutilitzar intenció Redsys des de snapshot;
note right
  IMPLEMENTAT per pagament targeta AP via course-intent; pendent per payment_link/altres canals
  payment_link/commercial_operation -> RedsysPaymentIntentService
end note
else (No)
 :No mostrar instruccions executables;
endif
stop
@enduml
```

## 5. P05 · Intranet «Validar descomptes»

**Aquest flux comparteix pàgina i decisions amb UC-116.** UC-020 només desenvolupa la branca de tarifa alternativa Alumne PrisMa i les seves conseqüències. La comanda actual ha estat revalidada el 03/10/2026 com POST + sessió + permís + CSRF + `requestId`.

### 5.1. P05-A — ACTUAL · cua de pendents

```plantuml
@startuml
title P05-A | ACTUAL | cua de validació
start
:Obrir /alumnes/validar-descomptes/;
:comprovarSessio.php revalida la pàgina;
:mostrarMain.php comprova permís de visualització;
:Consultar inscripcions amb VALID_DESC=0;
:Mostrar SÍ/NO + ENVIA;
stop
@enduml
```

### 5.2. P05-B — ACTUAL · comanda reconciliada 02/10

```plantuml
@startuml
title P05-B | ACTUAL | resolució protegida
start
:Secretaria prem ENVIA;
:JS genera requestId;
:JS envia POST idInsc/verificat/CSRF/requestId;
:Endpoint valida sessió i objectes;
:Validar CSRF amb hash_equals;
:Validar permís de /alumnes/validar-descomptes/;
:Validar idInsc, verificat i requestId;
if (requestId ja vist a sessió?) then (Sí)
 :Reutilitzar resultat idempotent;
 stop
endif
:Delegar a Intranet::sendMsgValidatCurosDescomptes();
if (USOC?) then (Sí)
 :begin/complete decisió SIF;
endif
:Guardar resultat per requestId;
stop
@enduml
```

### 5.3. P05-C — ACTUAL · acceptació

```plantuml
@startuml
title P05-C | ACTUAL | acceptar dret original
start
:verificat=1;
:Consultar tarifa del TIPUS_DESC 4..8;
:UPDATE VALID_DESC=1;
note right
  En aquesta branca inspeccionada
  no es torna a persistir A_PAGAR.
end note
:Preparar comunicacions;
stop
@enduml
```

### 5.4. P05-D — ACTUAL · denegació i AP

```plantuml
@startuml
title P05-D | ACTUAL | denegar i oferir AP
start
:verificat=0;
:Fixar VALID_DESC=2;
:Buscar tarifa AP;
:Consultar cnsAlumnePrisMa per DNI;
if (Elegible?) then (Sí)
 :TIPUS_DESC=1;
 :A_PAGAR=preu AP;
else (No)
 :TIPUS_DESC=0;
 :A_PAGAR=preu ordinari;
endif
:UPDATE per ID sense lock persistent de versió/estat esperat;
:Preparar correus;
stop
@enduml
```

### 5.5. P05-E — ACTUAL · notificació

```plantuml
@startuml
title P05-E | ACTUAL | persistència i SMTP
start
:Persistir decisió/import;
:Construir correu Secretaria;
:Intentar send();
:Construir correu persona;
:Intentar send();
:Retornar OK;
:JS considera èxit si resposta no conté "error";
stop
@enduml
```

### 5.6. P05 — FINAL

```plantuml
@startuml
title P05 | FINAL | comanda versionada i alternativa AP
start
:POST/comanda autenticada;
:Validar CSRF, rol, permís específic, idempotency key;
:Rellegir sol·licitud amb versió esperada;
if (Continua PENDING?) then (No)
 :Retornar ALREADY_APPLIED o VERSION_CONFLICT;
 stop
endif
if (Secretaria accepta?) then (Sí)
 :Persistir discount_validation ACCEPTED;
 :Validar/conservar oferta autoritzada;
else (No)
 :Persistir decisió original REJECTED;
 :Executar PrismaStudentDiscountPolicy;
 if (AP elegible + tarifa?) then (Sí)
  :Persistir segona decisió AP ACCEPTED;
  :Crear/substituir oferta AP PAYABLE;
 else (No)
  :Crear/substituir oferta ordinària o incidència ELIGIBLE_NO_PRICE;
 endif
endif
:Registrar operational_event;
:Commit;
:Crear notificació posterior al commit;
stop
@enduml
```

## 6. P06 · Canvi de curs

**Font ACTUAL:** `Intranet::buscarPreuAPagar_modalCanviCurs()`.

### 6.1. P06-A — ACTUAL · antecedent

```plantuml
@startuml
title P06-A | ACTUAL | elegibilitat històrica
start
:Carregar ID, DNI i DATA_INSC originals;
if (Carnet Jove marcat?) then (Sí)
 :Marcar teCarnetJove;
else (No)
 :Buscar antecedent mateix DNI;
 :Exigir pagament>0 o curs regal;
 :Excloure D/M;
 :Excloure ID actual;
 :Exigir DATA_INSC <= data original;
 if (Existeix?) then (Sí)
  :esExalumne=true;
 endif
endif
stop
@enduml
```

### 6.2. P06-B — ACTUAL · prioritat de preu

```plantuml
@startuml
title P06-B | ACTUAL | preu del nou curs
start
:Recuperar ID_PREU del nou curs/edició;
if (Promoció >=10 que preserva l'import anterior?) then (Sí)
 :Conservar preu;
elseif (Descompte documental validat?) then (Sí)
 :Buscar tarifa per TIPUS_DESC/curs/mes/data original;
elseif (Carnet Jove?) then (Sí)
 :Buscar TIPUS=2 per ID_PREU;
elseif (esExalumne?) then (Sí)
 :Buscar TIPUS=1 per ID_PREU;
 note right
  Aquesta consulta no incorpora
  CURS ni MES.
 end note
else
 :Buscar preu ordinari;
endif
:Retornar preu;
stop
@enduml
```

### 6.3. P06 — FINAL

```plantuml
@startuml
title P06 | FINAL | reavaluació versionada en canvi de curs
start
:Carregar operació/inscripció origen;
:Definir evaluation_at segons regla ratificada;
:Executar la mateixa PrismaStudentDiscountPolicy;
:Identificar nova edició i tarifa exacta;
:Resoldre compatibilitat amb descompte preexistent;
if (Factura/cobrament ja existent?) then (Sí)
 :Classificar ajust i preservar històric;
else (No)
 :Crear nova versió/substitució de l'oferta;
endif
:Registrar before/after + correlation;
stop
@enduml
```

### 6.4. Estat d'implementació del FINAL

- `CommercialOfferService::createOrReuse()`: **implementat**; l'alta AP llegada encara no crea `offer_id`, però `enviarInscripcio.php` ja revalida AP al servidor abans de persistir.
- `PaymentLinkService::issue()/resolve()/revoke()`: **implementat**; encara no és la ruta canònica d'aquest checkout AP.
- Política `PrismaStudentDiscountPolicy`: **IMPLEMENTADA_COMPATIBILITAT** com `ALUMNE_PRISMA_WEB_LEGACY_V2`; decisions UC20-DEC-001…006 tancades a la fitxa v1.7.
- Connexió AP de pagament → `RedsysPaymentIntentService`: **IMPLEMENTADA** via `SifRedsysCourseIntentClient` / `course-intent` / `PrismaStudentCourseCheckoutService`. La coordinació específica amb `payment_link` continua pendent.

## 7. Matriu ACTUAL → FINAL

| Superfície | ACTUAL | FINAL |
| --- | --- | --- |
| P01 informació | text i taula calculats amb criteris propis | text i tarifa alineats amb política versionada |
| P02 càlcul | globals JS + AJAX textual | offer_id/UUID_OPERATION servidor |
| P02 confirmació | import/tipus enviats pel client | acceptació d'oferta rellegida al servidor |
| P03 confirmació | `VALID_DESC` + camps llegats | estat d'operació + ledger |
| P04 targeta | `VALID_DESC==1` | `PAYABLE` + link actiu |
| P04 transferència | comprovació diferent de targeta | mateixa autorització que qualsevol cobrament |
| P05 resolució | POST + sessió + permís + CSRF + requestId idempotent | control persistent d'estat/versió i outbox com a evolució |
| P05 alternativa AP | `TIPUS_DESC=1, VALID_DESC=2` | decisió original REJECTED + decisió AP ACCEPTED |
| P06 canvi curs | política històrica específica | mateixa política versionada amb `evaluation_at` explícit |

## 8. Decisions canòniques que afecten els diagrames FINAL

1. `GENERAT=1`: **sí**, compatibilitat executable.
2. Factura emesa sense cobrament: **no**, per si sola no acredita AP.
3. Inscripció actual: **no**, s'exclou de l'historial; tampoc compta historial posterior a `DATA_INSC`.
4. `evaluation_at`: en matrícula llegada és `DATA_INSC`; l'historial posterior no acredita retroactivament.
5. AP + promoció: no acumulable en aquest tall i falla tancat; altres famílies continuen com a migració transversal.
6. Snapshot AP: congelat mentre la matrícula sigui pagable; expiració de `payment_link` separada.

## 9. Relacions amb altres casos

- **UC-116**: justificants documentals, revisió manual i denegació.
- **UC-014**: compra de curs i factura posterior.
- **UC-071**: canvi de curs.
- **UC-003**: callback/worker Redsys.
- **UC-073/074**: ajust/impacte fiscal quan ja hi ha document emès, segons classificació definitiva.

## 10. Criteri de completitud documental

Aquest dossier cobreix totes les superfícies identificades del UC-020. Si apareix una nova ruta que:

- calcula TIPUS=1,
- canvia `A_PAGAR` per condició Alumne PrisMa,
- decideix `VALID_DESC`,
- crea una intenció/link de pagament,
- o reconstrueix el descompte per facturar,

s'ha d'afegir com a pàgina/apartat nou i vincular-lo a la matriu d'auditoria.


## 11. Reconciliació de tancament — 02/10/2026

Per UC-020, P02 continua sent llegat en transport i UX, però ja no és autoritatiu monetàriament quan aplica AP: la persistència torna a calcular elegibilitat i preu. P05 també queda reclassificat: les notes històriques de GET/sense CSRF són superades pel codi actual POST/CSRF/permís/requestId.


## 12. Revalidació transversal — 03/10/2026

- **P01:** documentació pública localitzada; continua existint diferència entre text comercial ampli i criteri executable versionat.
- **P02:** preview JS continua subjecte a concurrència, però l'alta AP revalida historial/tarifa al servidor i, després d'UC020-94, el preu servidor arriba intacte a `A_PAGAR`.
- **P03/P04:** el canal de targeta actiu obté la intenció SIF i usa l'import retornat pel servidor; `payment_link` i transferència continuen pendents d'unificació.
- **P05:** resolució revalidada com POST + sessió + permís + CSRF + `requestId`; queda deute de concurrència/idempotència persistent a BD.
- **P06:** la lògica llegada de canvi de curs continua sent una superfície diferent; la seva convergència completa a la policy/oferta SIF és migració transversal.
- **Callback/factura:** el `main` actual incorpora proves E2E simulades de callback → worker → pagament/factura/sync/outbox. No substitueixen el gate real de preproducció.
