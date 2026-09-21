# UC-23 · Registrar una fracció de pagament — fitxa i UML integrats

**Objectiu:** registrar un cobrament parcial o una de diverses quotes d'una factura ja emesa, sense crear una altra factura per cada fracció. UC-23 especialitza UC-02. L'assignació funcional de calendaris de quotes, avisos i deute pendent **no** està resolta pel servei aquí examinat.

**Fonts executables:** `ManualInstallmentPaymentService`, `ManualInstallmentPaymentPayloadBuilder`, `ManualPaymentInvoiceRepository`, `PaymentService`, `PaymentRepository`.

## 1. Fitxa de cas d'ús

| Camp | Regla |
| --- | --- |
| Actor | Operador autoritzat d'intranet; autorització del canal final pendent d'acreditar. |
| Disparador | Es confirma el cobrament d'una fracció d'una factura existent. |
| Factura | Identificació per `UUID_FACTURA` o `NUM_VISIBLE`. |
| Camps obligatoris del builder | `amount`/`import`/`pagament` positiu; `movement_date`/`data_pag`/`dataPag`; `id_insc`/`id_inscripcio`/`inscription_id` numèric positiu; `user`/`usuari`/`created_by` no buit. |
| Moviment generat | `CHARGE`, `method=MANUAL`, `source_channel=INTRANET`, `allocation_type=INSTALLMENT_PAYMENT` per defecte, una assignació a factura. |
| Identificació del moviment | `MANUAL|FRACCIO|ID_INSC:<id>|DATA:<dia>|IMPORT:<import>|USUARI:<usuari>`. |
| Sortida | `ok`, `uuid_payment`, `idempotency_reused`, `uuid_factura` i `num_visible`; el pagament genèric actualitza `ESTAT_COBRAMENT`. |

### 1.1. Flux principal verificat

1. L'operador identifica la inscripció, la factura ja existent, l'import i la data del cobrament. **No es pot deduir del builder que les dades acadèmiques o l'entrada bancària estiguin comprovades.**
2. `ManualInstallmentPaymentService` localitza la factura per UUID o número; si no la troba, retorna error.
3. `ManualInstallmentPaymentPayloadBuilder` valida import, data, identificador d'inscripció i usuari; construeix un `CHARGE` manual amb `provider_ref=FRACCIO|ID_INSC:<id>|USUARI:<usuari>`.
4. `PaymentService` cerca el moviment per clau idempotent dins la transacció; si no existeix, `PaymentRepository` crea el moviment, una assignació i recalcula l'estat de cobrament.
5. La factura inicial conserva el número fiscal. Diferents fraccions generen moviments econòmics diferents i poden deixar-la en `PARTIAL` fins a `PAID`.

### 1.2. Alternatives, errors i punts per decidir

| Situació | Comportament |
| --- | --- |
| F1. Primer cobrament menor que el total | `PaymentStatusCalculator` calcula `PARTIAL` quan el cobrament net és positiu i inferior al total. |
| F2. Segona fracció completa el total | El calculador retorna `PAID`; cap fracció crea nova factura o nou registre fiscal. |
| F3. Reintent de la mateixa clau | El servei retorna el `uuid_payment` anterior. |
| E1. Factura desconeguda | Error de validació abans de crear moviment. |
| E2. Import no positiu, data absent, identificador d'inscripció invàlid o usuari absent | Rebuig per `ManualInstallmentPaymentPayloadBuilder`. |
| **P1. Diverses quotes idèntiques el mateix dia** | La clau només distingeix inscripció + **dia** + import + usuari, no una referència única de quota. Dues quotes legítimes del mateix import el mateix dia podrien ser interpretades com un reintent: cal un identificador de fracció/operació si el negoci admet aquest cas. |
| **P2. Fracció sobre inscripció/factura no relacionada** | El servei busca la factura per separat i el builder accepta `id_insc` d'entrada; en el camí consultat **no es comprova la correspondència real entre la inscripció i la factura**. |
| **P3. Cobrament real vs apunt manual** | `method=MANUAL` no acredita una transferència o càrrec bancari; l'origen i la justificació de cada moviment s'han de validar abans. |
| **P4. Imports totals i calendari** | No s'ha localitzat en aquest servei la validació del calendari de quotes ni un bloqueig específic d'import superior al pendent; el repositori pot calcular `OVERPAID`. |

**Proves localitzades, no executades:** `ManualInstallmentPaymentServiceTest::testRegistersInstallmentsAgainstExistingInvoiceWithoutDuplicatingFiscalRecord`, `testRegistersInstallmentByVisibleInvoiceNumber` i `testRejectsUnknownInvoiceBeforeRegisteringInstallment`.

### 1.3. Revisió: cada fracció també és una atribució monetària per inscripció — PENDENT

El builder coneix `id_insc` i l'inclou a la **clau idempotent** i a `provider_ref`, però `PaymentRepository` no desa `ID_INSC` a `payment_transaction` ni a `payment_allocation`. UC-23 ha de crear una entrada d'atribució per **cada fracció efectivament cobrada** de la inscripció i referenciar-ne el `UUID_PAYMENT`; reintentar la mateixa fracció no torna a incrementar el seu saldo. Si el pagament cobreix quotes d'inscripcions diferents, cal desglossament explícit. Un calendari de fraccions previstes no és diner cobrat: no produeix entrades `RECEIPT_ALLOCATION` abans de la confirmació.

[Proposta de registre per inscripció](00-revisio-moviments-inscripcions.md).

### 1.4. Fraccionament de negoci, diversos intents i excepció de pack — contrast amb el xat original

**Regla operativa declarada:** el primer pagament es fa habitualment abans de començar el curs i l'import s'ha d'haver completat en acabar-lo; si no hi ha pagament inicial, la gestió pot concedir una excepció justificada fins a la segona setmana (UC-96). Les quotes previstes, les reclamacions i l'estat de matrícula no són moviments de caixa: registrar només els cobraments realment confirmats. L'excepció, la durada i els avisos finals no es dedueixen de `ManualInstallmentPaymentService` i s'han de verificar en el circuit de gestió.

**F-REDSYS — un `IDPAG`, diverses operacions bancàries:** el xat original confirma que un mateix IDPAG pot aparèixer amb diversos `DS_ORDER`, per fraccionaments o intents denegats i després acceptats. IDPAG identifica el context de l'enllaç/inscripció, **no** una clau global de deduplicació de tots els cobraments. Cada cobrament Redsys real es concilia pel `DS_ORDER` corresponent i per la notificació validada (UC-03/51), mentre que una notificació denegada no genera CHARGE. Si la fracció arriba per transferència, es verifica la referència de l'entrada bancària (UC-22); no registrar la mateixa entrada després com una fracció MANUAL addicional.

**F-LEGACY — `FRACCIO` i `DATA PAG`:** al llegat, el text FRACCIO pot concatenar imports i dates dels abonaments; `PAGAMENT` acumula el pagat i `DATA PAG` pot completar-se quan la inscripció està totalment pagada. El SIF conserva cada moviment original amb data pròpia a `payment_transaction` i la seva assignació; la representació textual del llegat és només resum sincronitzat. En el modal actual «Passar pagaments» l'import introduït, la data, el banc i la columna de fracció s'han de contrastar amb la factura/inscripció real al servidor, sense derivar un CHARGE a partir d'un càlcul visual.

**F-PACK — excepció que no s'ha d'esborrar:** aquest cas UC-23 descriu **diversos cobraments d'una factura ja emesa**, que no generen noves factures. En canvi, el xat original diu que, en un pack dividit excepcionalment des de la intranet, el circuit històric pot generar **més d'una factura, segons els pagaments**. La documentació final del pack també contempla una factura per cada pagament real en aquella variant excepcional. No traslladar automàticament la regla «una factura per totes les fraccions» a aquest altre circuit: cal classificar si existeix una única factura prèvia o si són operacions/parts facturables diferents (UC-15/16), amb imports, línies i justificació aprovats. **No està acreditat aquí un orquestrador que resolgui automàticament les dues variants.**

**F-IDENT — identificar fraccions repetides:** la clau manual actual concatena ID_INSC, dia, import i usuari, però omet un identificador únic d'operació/quota i la factura. Dos cobraments legítims del mateix import i dia podrien fusionar-se; una petició amb mateix conjunt d'aquests camps però factura diferent també podria retornar el moviment anterior. Distingir reintent exacte d'ingrés nou, exigir clau estable per moviment confirmat, validar la relació inscripció/factura i les assignacions, i comparar el payload original per detectar conflictes. Són controls pendents d'integració, no garanties de l'implementat.

### 1.5. Proves d'acceptació específiques de fraccionament (no executades)

| ID | Escenari | Resultat a acreditar |
| --- | --- | --- |
| FR-01 | Dues fraccions reals contra una factura ja emesa | Dos UUID_PAYMENT, PARTIAL i PAID segons cobrament net; cap factura nova. |
| FR-02 | Un IDPAG amb dos DS_ORDER legítims acceptats | Dues fraccions diferents; cap col·lapse de les dues per IDPAG. |
| FR-03 | DS_ORDER denegat, després un altre acceptat | Cap CHARGE pel denegat; CHARGE només pel confirmat. |
| FR-04 | Dues fraccions manuals iguals el mateix dia i usuari | Identificador diferenciat del fet real; no fusionar cobraments legítims. |
| FR-05 | Mateixa clau manual sobre factura diferent | Conflicte de payload/relació, no reutilitzar una fracció aliena. |
| FR-06 | Transferència ja registrada i cercada a la columna FRACCIO | Una sola entrada bancària i cap segon CHARGE de tipus MANUAL. |
| FR-07 | Una fracció entra després de baixa, canvi o factura ja corregida | Relectura de l'estat i incidència/regularització, no imputació al destí obsolet. |
| FR-08 | Pack excepcional dividit per decisió de la intranet | Identificar explícitament el circuit de facturació del pack, sense aplicar a cegues la regla d'UC-23. |
## 2. Diagrama UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Operador autoritzat" as O
rectangle "SIF PrisMa" {
 usecase "UC-23\nRegistrar una fracció" as Fr
 usecase "Localitzar factura\ni inscripció" as Find
 usecase "UC-02\nRegistrar moviment econòmic" as Pay
 usecase "Consultar import pendent" as Pending
}
O --> Fr
Fr ..> Find : <<include>>
Fr ..> Pay : <<include>>
Fr ..> Pending : <<include>> (objectiu de validació)
@enduml
```

**Nota:** consultar/verificar l'import pendent abans del registre figura com a validació **objectiu**; el camí de `ManualInstallmentPaymentService` consultat no l'executa abans de delegar a `PaymentService`.

## 3. Subdiagrama de classes

```mermaid
classDiagram
direction LR
class ManualInstallmentPaymentService {
 +registerByUuid(db,uuidFactura,input) array
 +registerByNumVisible(db,numVisible,input) array
 -registerForInvoice(invoice,input) array
}
class ManualInstallmentPaymentPayloadBuilder {
 +forExistingInvoice(uuidFactura,input) array
}
class ManualPaymentInvoiceRepository {
 +findByUuid(db,uuid,forUpdate) array
 +findByNumVisible(db,numVisible,forUpdate) array
}
class PaymentService {
 +registerPayment(payload) array
}
class PaymentPayloadValidator {
 +validate(payload) array
}
class PaymentRepository {
 +findByIdempotencyKey(db,key,forUpdate) array
 +createPayment(db,payload) array
}
class PaymentStatusCalculator {
 +calculate(total,charges,refunds) string
}
ManualInstallmentPaymentService --> ManualPaymentInvoiceRepository : factura
ManualInstallmentPaymentService --> ManualInstallmentPaymentPayloadBuilder : import/inscripció/usuari
ManualInstallmentPaymentService --> PaymentService : càrrec
PaymentService --> PaymentPayloadValidator : valida
PaymentService --> PaymentRepository : crea/reutilitza
PaymentRepository --> PaymentStatusCalculator : recalcula
```

## 4. Diagrama de seqüència — dues fraccions i reintent

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant UI as Adaptador intranet [pendent]
participant S as ManualInstallmentPaymentService
participant IR as ManualPaymentInvoiceRepository
participant B as ManualInstallmentPaymentPayloadBuilder
participant PS as PaymentService
participant PR as PaymentRepository
participant DB as BD SIF
O->>UI: Registrar primera fracció (factura, inscripció, usuari, import, data)
UI->>S: registerByUuid(db,uuidFactura,input1)
S->>IR: findByUuid(db,uuidFactura)
IR->>DB: SELECT factura
alt No hi ha factura
 S--xUI: Error de validació
else Factura localitzada
 S->>B: forExistingInvoice(uuidFactura,input1)
 B-->>S: payload CHARGE / INSTALLMENT_PAYMENT
 S->>PS: registerPayment(payload1)
 PS->>PR: findByIdempotencyKey(key1,true)
 alt Mateixa fracció ja registrada
  PR-->>PS: UUID anterior
 else Primera vegada
  PS->>PR: createPayment(payload1)
  PR->>DB: INSERT payment_transaction i allocation
  PR->>DB: UPDATE factura.ESTAT_COBRAMENT (p. ex. PARTIAL)
 end
 PS-->>S: UUID pagament1
 S-->>UI: Resultat primera fracció
 O->>UI: Registrar altra fracció amb clau diferent
 UI->>S: registerByUuid(db,uuidFactura,input2)
 S->>B: forExistingInvoice(uuidFactura,input2)
 B-->>S: payload CHARGE (key2)
 S->>PS: registerPayment(payload2)
 PS->>PR: createPayment(payload2) si key2 nova
 PR->>DB: INSERT segon moviment i recalcular estat
 PS-->>S: UUID pagament2
 S-->>UI: Resultat segona fracció
end
Note over S,DB: No hi ha una segona emissió fiscal pel cobrament fraccionat
```

### 4.1. Seqüència — quotes previstes, cobrament verificat i reintent (OBJECTIU)

```mermaid
sequenceDiagram
autonumber
actor O as Gestió
participant UI as Passar pagaments [adaptador pendent]
participant R as Conciliació Redsys/banc [PENDENT]
participant P as PaymentService [existent]
participant DB as BD SIF
O->>UI: Informar fracció d'una factura preexistent
UI->>R: Verificar fet real per DS_ORDER/referència o justificant
alt Quota prevista o intent denegat
 UI-->>O: Mantenir pendent, cap CHARGE
else Cobrament confirmat i nou
 UI->>UI: Comprovar ID_INSC, factura, import restant i clau de moviment
 UI->>P: registerPayment(CHARGE, assignació a factura preexistent)
 P->>DB: INSERT payment_transaction i payment_allocation
 P-->>UI: UUID_PAYMENT
 UI-->>O: Estat PARTIAL/PAID, sense factura nova
else Ingrés ja registrat o identificador en conflicte
 UI-->>O: Reutilitzar moviment equivalent o obrir incidència, sense nou CHARGE
end
Note over UI,R: Calendari i conciliació de fraccions encara no són funcions acreditades del servei manual.
```
**Precisió addicional sobre les claus:** `ManualInstallmentPaymentPayloadBuilder::forExistingInvoice()` tampoc incorpora una clau `idempotency_key` explícita que arribi a `$input`: sempre calcula la seva pròpia clau amb `idempotencyKey()`. Per això aportar un identificador bancari estable en el payload **no resol** la col·lisió sense modificar el contracte del builder i del control d'equivalència.

### 4.2. Seqüència específica: dues fraccions legítimes idèntiques el mateix dia — COL·LISIÓ REAL DEL CONTRACTE ACTUAL

**Font:** `ManualInstallmentPaymentPayloadBuilder::idempotencyKey()` deriva la clau d'`ID_INSC`, dia de `movement_date`, import i usuari. La factura, la referència del banc, l'instant complet i una clau d'operació explícita **no participen** en aquesta derivació. `PaymentService::registerPayment()` retorna el moviment ja registrat per una clau igual sense comparar el nou payload. La prova existent repeteix expressament la primera fracció amb els mateixos valors; **no cobreix dos ingressos reals diferents que comparteixen aquests valors**.

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant A as Adaptador intranet [PENDENT]
participant B as ManualInstallmentPaymentPayloadBuilder [PHP]
participant S as PaymentService [PHP]
participant TR as TransactionRunner [PHP]
participant PR as PaymentRepository [PHP]
participant DB as BD SIF
O->>A: Comunicar ingrés real A de 40 el dia D, factura F, inscripció I, usuari U
A->>B: forExistingInvoice(F, input A)
B-->>A: clau K = I + D + 40 + U [sense factura/referència]
A->>S: registerPayment(payload A)
S->>TR: run(callback A)
TR->>DB: BEGIN
S->>PR: findByIdempotencyKey(K, true)
PR-->>S: No existeix
S->>PR: createPayment(payload A)
PR->>DB: INSERT CHARGE A i assignació a F
TR->>DB: COMMIT
TR-->>S: UUID_PAYMENT_A
S-->>A: idempotency_reused=false
A-->>O: A registrat
O->>A: Comunicar un ingrés B diferent de 40 el mateix dia D, I, U
A->>B: forExistingInvoice(F o una altra factura, input B)
B-->>A: mateixa clau K malgrat referència bancària diferent
A->>S: registerPayment(payload B)
S->>TR: run(callback B)
TR->>DB: BEGIN
S->>PR: findByIdempotencyKey(K, true)
PR-->>S: CHARGE A existent
TR->>DB: COMMIT sense crear B
S-->>A: idempotency_reused=true, UUID_PAYMENT_A
A-->>O: El servei retorna A, però NO demostra equivalència d'ingressos
Note over A,DB: Aquest és el comportament que es dedueix del PHP actual. El segon ingrés real quedaria sense registrar per aquesta via.
```

### 4.3. Seqüència objectiu: identificar i reconciliar fracció abans de registrar-la

**Disseny pendent:** establir un identificador immutable per **fet bancari real o fracció manual comprovada**, incloure factura i inscripció en el contracte d'equivalència, i evitar que un mateix ingrés es torni a registrar des d'UC-22/25/56 amb una altra clau. No es tracta de canviar únicament el format de la clau: cal també comprovar prova externa, relació inscripció/factura i imports/assignacions.

```plantuml
@startuml
left to right direction
actor "Operador de cobraments" as Op
actor "Origen bancari verificat" as Bank
rectangle "SIF PrisMa" {
 usecase "UC-23\nRegistrar fracció real" as Fr
 usecase "UC-56\nConciliar ingrés i factura" as Match
 usecase "UC-02\nRegistrar moviment idempotent" as Pay
 usecase "UC-86\nAuditar intent/decisió/resultat" as Audit
}
Op --> Fr
Bank --> Match
Fr ..> Match : <<include>> [OBJECTIU]
Fr ..> Pay : <<include>>
Fr ..> Audit : <<include>> [OBJECTIU]
@enduml
```

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant A as Adaptador autoritzat [PENDENT]
participant R as Reconciliació bancària/UC-56 [PENDENT]
participant G as Guard d'equivalència [PENDENT]
participant P as PaymentService [PHP]
participant DB as BD SIF
O->>A: Registrar ingrés amb referència única, factura F i inscripció I
A->>R: Validar fet bancari i relació I-F, import pendent i atribució
alt Ingrés no confirmat o factura/inscripció incongruent
 R-->>A: Rebuig o incidència; cap CHARGE
else Ingrés confirmat
 R-->>A: ID únic d'ingrés, import i destinació verificats
 A->>G: Comparar ID del fet i payload amb moviments existents
 alt Mateix ingrés i payload equivalent
  G-->>A: Reutilitzar UUID_PAYMENT existent
 else Mateix identificador amb import/factura contradictoris
  G-->>A: Conflicte i revisió; cap nou CHARGE
 else Ingrés nou i diferent, encara que import/dia siguin iguals
  G-->>A: Clau d'ingrés única i assignació validada
  A->>P: registerPayment(payload normalitzat)
  P->>DB: BEGIN, moviment, assignació i COMMIT
  P-->>A: UUID_PAYMENT nou després de COMMIT
 end
end
A-->>O: Moviment verificat, reutilitzat o incidència explícita
Note over A,G: Guard, autorització i conciliació no estan implementats pel builder/manual service aquí consultats.
```

| ID de prova pendent | Escenari | Resultat que cal acreditar |
| --- | --- | --- |
| FR-09 | Ingrés A i B reals amb mateix I, dia, import i usuari | Dos UUID_PAYMENT si existeixen dos fets bancaris diferents, sense duplicar la factura. |
| FR-10 | Reintent del mateix ingrés amb una altra referència de pantalla | Recuperar el mateix UUID_PAYMENT; cap segon CHARGE. |
| FR-11 | Mateixa clau manual sobre factura F1 i F2 diferents | Conflicte de contingut; no retornar el cobrament d'F1 com si fos el d'F2. |
| FR-12 | ID_INSC aportat no està vinculat a factura | Rebuig abans de `registerPayment()` o derivació a incidència, no imputació silenciosa. |
## 5. Traçabilitat

[Fitxa original UC-23](../06-fitxes-funcionals/uc-023.md) · [UC-02 revisada](uc-002-registrar-cobrament-factura.md) · [ManualInstallmentPaymentService](../../sif/src/Service/ManualInstallmentPaymentService.php) · [ManualInstallmentPaymentPayloadBuilder](../../sif/src/Service/ManualInstallmentPaymentPayloadBuilder.php) · [PaymentService](../../sif/src/Service/PaymentService.php) · [ManualInstallmentPaymentServiceTest](../../sif/tests/Integration/ManualInstallmentPaymentServiceTest.php).

**No acreditat:** autorització, verificació de l'ingrés, validació d'import pendent o calendari de quotes i correspondència real inscripció/factura.
