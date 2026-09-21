# UC-104 · Gestionar un excés de cobrament sense atribució fictícia

**Objectiu canònic:** un excés real queda **sense assignar** o passa a devolució/saldo segons una decisió autoritzada; no es força l'estat `PAID` d'una factura ni es modifica l'import fiscal per absorbir-lo. **Estat:** `PaymentService` i `PaymentRepository` creen moviments amb assignacions a factura; `ManualRefundService` registra un `REFUND` real i `CreditBalanceService` crea/aplica un crèdit. **No s'ha acreditat** un coordinador que detecti i controli l'import **no assignat a factura**. `PaymentPayloadValidator` exigeix almenys una assignació, però **no comprova que la suma de les assignacions coincideixi amb l'import del moviment**; `PaymentRepository::createPayment()` insereix l'import total del moviment i les assignacions rebudes. És tècnicament possible passar `amount=120` amb una assignació de `100`, però no hi ha en aquest camí un expedient, estat i protecció de concurrència del sobrant de `20` ni una garantia general de conservació dels imports.

## 1. Fitxa de cas d'ús

| Dada | Contracte funcional i evidència |
| --- | --- |
| Actors | Operador de cobraments i responsable autoritzat per resoldre titularitat/retorn; pagador original quan sigui necessari. L'alumne inscrit pot no ser la persona que ha pagat. |
| Entrada | Referència bancària/TPV, `UUID_PAYMENT` real si existeix, import ingressat, factura/es, receptor i pagador, total ja assignat, retorns i fons atribuïts a cada `ID_INSC`. |
| Classificació | Diferenciar: import ingressat superior a deute; pagament duplicat **real**; notificació TPV repetida **sense segon ingrés**; transferència d'empresa per diverses factures; diners encara no identificats. No equiparar cap d'aquests casos. |
| Saldo objectiu | `excesDisponible = importExternReal - importJaAssignat - retornExternReal - altresAplicacionsJustificades`, amb tipus de moviment/signatura correcta, decimals, moneda i locks. És fórmula de **disseny**, no càlcul executable de `PaymentService`. |
| Persistència existent | `payment_transaction` conserva els moviments reals; cada fila de `payment_allocation` exigeix factura. El moviment pot tenir una diferència aritmètica respecte a les assignacions, però el servei actual no en controla la destinació, les reserves ni l'estat pendent. `credit_balance` emmagatzema saldo concedit i `payment_action_event` pot auditar petició/resultat quan l'adaptador usa el gateway. |
| Mancança precisa | No s'ha identificat una ruta completa i **controlada** d'`UNALLOCATED_EXTERNAL_RECEIPT` sense factura al PHP consultat. Un ingrés completament sense assignacions és rebutjat pel validador; un ingrés parcialment assignat pot passar, però **sense control del saldo no assignat**. **No** crear una assignació fictícia de l'excés ni declarar resolta la conciliació pel sol fet d'haver desat una diferència aritmètica. |
| Efecte fiscal | Un pagament excessiu no implica automàticament canviar `factura.TOTAL` ni emetre factura/rectificativa; les conseqüències d'un servei/preu realment diferent requereixen UC-74. |
| Efecte per inscripció | Només la part realment aplicada al servei es registra com `EXTERNAL → ID_INSC`; un sobrant encara no atribuït **no pertany per defecte** a cap inscripció del grup. |

### 1.1. Flux objectiu

1. UC-25/56 detecta que hi ha un ingrés extern acreditat superior a l'obligació identificada, i comprova referència/ordre i si `UUID_PAYMENT` ja existeix. Un callback duplicat de la mateixa operació **no** constitueix excés de caixa.
2. El coordinador pendent identifica pagador real, factura/es, inscripcions i import ja atribuït. Per a una factura única de 100 € i un ingrés real de 120 €, separa **100 € aplicables** i **20 € pendents de decisió**, no canvia `factura.TOTAL` a 120 €.
3. Desa un expedient amb import i origen del sobrant, estat `PENDING_DECISION` conceptual, actor i correlació; fins a implementar el seguiment/conciliació del saldo no assignat, la ruta actual de `PaymentService` **no cobreix aquest estat explícitament**.
4. La persona autoritzada decideix: assignar a un altre deute acreditat del mateix pagador (UC-56), tramitar retorn **quan s'executa realment** (UC-28), o concedir saldo a titular legitimat (UC-29), amb les implicacions fiscals classificades quan pertoqui.
5. Si s'assigna a una altra factura, es reutilitza `UUID_PAYMENT` i es registra atribució interna per les inscripcions afectades, sense crear un segon `CHARGE`. Si es retorna, `ManualRefundService` és **registre de retorn efectuat**, no ordre automàtica al banc.
6. Si es crea saldo, `CreditBalanceService::createCredit()` pot desar-lo, però el servei **no comprova automàticament** que l'import provingui d'aquest excés concret ni registra per inscripció la sortida: enllaç, identitat del titular i ledger romanen pendents.
7. El cas es tanca només després de conciliar ingrés inicial, trams assignats, saldo o retorn i imports individuals. `ESTAT_COBRAMENT=PAID` de la factura no tanca per si sol un excés extern encara pendent.

### 1.2. Alternatives i proves

| Situació | Control |
| --- | --- |
| Ingrés 120 €, factura 100 € | Aplicar 100 €; 20 € no assignats fins a decisió; un segon `CHARGE` de 20 € és fals. |
| Dues notificacions per una sola operació de 100 € | UC-51/25a: un cobrament i cap excés extern. |
| Empresa ingressa 300 € per tres factures de 100 € | Una entrada real i tres assignacions fiscals; atribucions per inscripció separades quan calgui. |
| Es retorna el sobrant de 20 € | Registrar `REFUND` només després de la sortida efectiva i referenciar ingrés i pagador originals; no tornar l'import a qualsevol alumne del grup. |
| Es concedeix saldo de 20 € | `credit_balance` no substitueix prova del sobrant ni rastre dels fons; l'aplicació futura serà `COMPENSATION`, no nou ingrés. |
| Pagament existent reprocessat amb clau diferent | Comprovar referència bancària real; `PaymentService` només deduplica per clau idempotent, no detecta totes les duplicitats externes. |

**Bloquejant:** persistència i conciliació d'ingressos no assignats, permissos/titularitat, verificació import retornable, idempotència entre canals i registre quantitatiu d'atribucions per inscripció. Cap prova de UC-104 executada.

### 1.3. Excés real, doble notificació i decisió de devolució o saldo — xat i operativa

**Decisió de negoci comunicada.** Quan una persona ingressa **més diners dels que correspon pagar**, PrisMa consulta si vol **devolució** o deixar l'excedent **com a saldo per una altra inscripció**. Aquesta decisió es pren sobre el diner efectivament ingressat que queda disponible: una notificació Redsys repetida amb la mateixa `DS_ORDER` no és un segon pagament ni genera un excés de caixa. Tampoc tota diferència entre `A_PAGAR` i `PAGAMENT` és un excés: en una factura prèvia pot existir deute pendent i, en grups, el pagament pot cobrir més d'una factura legítima.

**Detecció per origen i titular.** Abans de proposar retorn/saldo, cercar `UUID_PAYMENT` i referència bancària/TPV, totes les `payment_allocation`, possibles altres factures del pagador, devolucions reals i titular econòmic. En una transferència d'empresa amb una factura pendent de 100 € i una altra de 20 €, ingressar 120 € **no és excés de 20 €** si la transferència efectivament cobreix totes dues; el mateix import pot ser excés si la segona factura no està relacionada amb el pagador. No atribuir el sobrant a un alumne del grup per defecte.

**Persistència no acreditada del sobrant.** El servei actual `PaymentPayloadValidator` exigeix una o més assignacions a factura i `PaymentRepository::createPayment()` insereix les que rep. Per tant, la fitxa no ha de donar per implementat un import extern confirmat **parcialment sense assignar** ni un origen bancari separat del crèdit concedit. Cal definir la ruta d'ingrés no assignat i enllaçar la posterior decisió UC-28/29/56 amb l'entrada única, sense inventar `UUID_FACTURA` ni augmentar `factura.TOTAL` per quadrar la caixa.

**Moment de sortida o aplicació.** Acceptar una devolució deixa una ordre o decisió **pendent** fins que l'entitat bancària realment retorna diners; UC-28 registra després el `REFUND` únic. Concedir saldo no és un `REFUND` ni un `CHARGE` addicional, i UC-29 ha de conservar l'origen i titular perquè la futura `COMPENSATION` no torni a comptar ingressos. Si queda una fase sense executar, informar-la separadament en comptes de tancar l'expedient quan la factura ja està `PAID`.

### 1.4. Proves addicionals d'excés segons origen (no executades)

| ID | Escenari | Resultat exigible |
| --- | --- | --- |
| EX-01 | Dues notificacions del mateix pagament de 100 € | Un ingrés; cap excés fals ni saldo duplicat. |
| EX-02 | Transferència de 120 € per dues factures legítimes de 100 € i 20 € | Una entrada, dues assignacions; cap excés. |
| EX-03 | Transferència de 120 € per factura única de 100 € | Excedent 20 € sense factura fictícia, decisió pendent i origen traçat. |
| EX-04 | Alumne de grup demana retorn de diners pagats per empresa | Verificar titular econòmic abans de retornar o crear crèdit. |
| EX-05 | Client tria devolució però banc encara no l'ha executat | Cap `REFUND` confirmat; registrar decisió/estat pendent. |
| EX-06 | Client tria saldo i després l'utilitza | Un crèdit d'origen i una aplicació `COMPENSATION`, no segon CHARGE. |

## 2. UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Operador cobraments" as Op
actor "Responsable autoritzada" as Resp
rectangle "SIF · excés real de cobrament" {
 usecase "UC-104\nGestionar excés cobrat" as Main
 usecase "Conciliar entrada bancària única" as Find
 usecase "Separar import aplicat i sobrant" as Split
 usecase "UC-56\nAssignar a un altre deute" as Allocate
 usecase "UC-28\nRegistrar retorn real" as Refund
 usecase "UC-29\nConcedir saldo autoritzat" as Credit
}
Op --> Main
Resp --> Main
Main ..> Find : <<include>>
Main ..> Split : <<include>>
Resp --> Allocate
Resp --> Refund
Resp --> Credit
@enduml
```

## 3. Classes — límit del model de pagaments real

```mermaid
classDiagram
direction LR
class OverpaymentResolutionService {
 <<DISSENY: no implementada>>
 +identify(uuidPayment) result
 +resolve(caseId,decision) result
}
class UnallocatedReceiptRepository {
 <<DISSENY: model no acreditat>>
 +trackOriginAndAvailableAmount(db,receipt) result
}
class PaymentService {
 <<PHP existent: exigeix allocations>>
 +registerPayment(payload) array
}
class PaymentRepository {
 <<PHP existent>>
 +createPayment(db,payload) array
}
class ManualRefundService {
 <<PHP existent: retorn executat>>
 +registerByUuid(db,uuidFactura,input) array
}
class CreditBalanceService {
 <<PHP existent>>
 +createCredit(input) array
}
class EnrollmentFundMovementRepository {
 <<PROPOSTA: no implementada>>
 +append(db,movement) string
}
OverpaymentResolutionService --> UnallocatedReceiptRepository : sobrant i origen
OverpaymentResolutionService ..> ManualRefundService : si retorn efectiu [DISSENY]
OverpaymentResolutionService ..> CreditBalanceService : si saldo aprovat [DISSENY]
OverpaymentResolutionService --> EnrollmentFundMovementRepository : trams atribuïts
PaymentService --> PaymentRepository : moviment i factura
```

## 4. Seqüència — ingressat 120 €, deguts 100 € (DISSENY)

```mermaid
sequenceDiagram
autonumber
actor Op as Operador
participant S as OverpaymentResolutionService [DISSENY]
participant B as Prova d'ingrés bancari
participant P as PaymentService [PHP]
participant U as UnallocatedReceiptRepository [DISSENY]
participant L as Ledger d'inscripció [PROPOSTA]
participant Refund as ManualRefundService [PHP]
participant Credit as CreditBalanceService [PHP]
Op->>S: Revisar transferència 120 €, factura F de 100 €
S->>B: Verificar entrada única i pagador
B-->>S: Referència i 120 € acreditats
S->>U: Registrar origen extern i pendent 20 € [mètode pendent]
S->>P: Registrar una sola entrada real amb atribució 100 € [model a ampliar]
Note over P,U: El PHP pot desar 120 amb allocation de 100, però no reserva ni gestiona el sobrant de 20 amb estat propi
S->>L: Atribuir 100 € a la inscripció legitimada
alt Decideix retornar 20 € i consta sortida bancària
 Op->>Refund: Registrar REFUND real de 20 € amb origen verificat
 Refund-->>S: UUID_PAYMENT_REFUND
 S->>L: Registrar sortida interna corresponent
else Decideix crear saldo autoritzat de 20 €
 Op->>Credit: createCredit(titular,20 €,origen)
 Credit-->>S: UUID_CREDIT
 S->>L: Enllaçar origen del saldo sense nou CHARGE
else No hi ha decisió
 S-->>Op: Sobrant pendent, factura F intacta
end
Note over S,L: Seqüència conceptual: no executar PaymentService amb total inconsistent 120 €/100 € com si el PHP actual resolgués el sobrant
```

### 4.1. Acció independent: identificar un excedent real, no una notificació duplicada — OBJECTIU

```plantuml
@startuml
left to right direction
actor "Operador de cobraments" as O
actor "Font bancària/TPV" as B
rectangle "SIF PrisMa — excedent" {
 usecase "UC-104 / DETECTAR\nIdentificar ingressos externs\ni deutes reals" as Find
 usecase "UC-25a / UC-51\nDescartar callback repetit\nsense nou ingrés" as Dup
 usecase "UC-56\nConsultar assignacions existents" as Allocate
 usecase "UC-104 / DECIDIR\nSeparar import aplicable i sobrant" as Decide
}
O --> Find
B --> Find
Find ..> Dup : <<include>> [OBJECTIU]
Find ..> Allocate : <<include>> [OBJECTIU]
O --> Decide
note bottom of Decide
 La decisió no és un moviment extern.
 No canvia la factura fiscal per ajustar-la als diners.
end note
@enduml
```

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant B as Prova bancària/TPV [EXTERNA]
participant R as Reconciliació d'ingrés UC-25/56 [PENDENT]
participant S as OverpaymentResolutionService [DISSENY]
participant U as Registre d'import no assignat [DISSENY]
O->>B: Comprovar cobrament aparent superior al pendent
B-->>O: Fets externs identificats per referència/DS_ORDER
O->>R: Comparar UUID_PAYMENT, ordres i assignacions de totes les factures del pagador
alt Mateix fet notificat dues vegades
 R-->>O: Recuperar UUID_PAYMENT, cap segon ingrés ni excedent
else Ingrés nou però hi ha un altre deute legítim del mateix pagador
 R-->>O: Distribució entre factures per validar, no excedent per defecte
else Import extern real superior al deute verificat
 R-->>S: Fet únic, import, deute, titular i sobrants
 S->>U: Conservar origen i import encara sense assignar [PENDENT]
 U-->>S: Identificador de l'excedent
 S-->>O: Expedient pendent de decisió, sense CHARGE fictici
end
Note over R,U: El PHP actual exigeix almenys una allocation al registrar el moviment, la custòdia d'excedents no assignats està pendent.
```

### 4.2. Acció independent: aplicar l'excedent a un deute diferent — OBJECTIU

```mermaid
sequenceDiagram
autonumber
actor O as Responsable autoritzat
participant S as OverpaymentResolutionService [DISSENY]
participant U as UnallocatedReceiptRepository [DISSENY]
participant A as ExistingPaymentAllocationService / UC-56 [DISSENY]
participant DB as BD fiscal SIF
O->>S: Aplicar part sobrera d'un ingrés existent a la factura F2
S->>U: Bloquejar i verificar UUID_PAYMENT, titular i excedent disponible
alt Titularitat no acreditada o import ja consumit
 U-->>S: Conflicte o saldo insuficient
 S-->>O: Rebuig o incidència, cap import nou
else Import suficient i deute de F2 confirmat
 S->>A: allocate(UUID_PAYMENT existent,F2,import,requestId)
 A->>DB: BEGIN, persistir assignació idempotent i recalcular F2 [PENDENT]
 A->>DB: COMMIT
 A-->>S: UUID_PAYMENT original, import imputat i saldo pendent
 S-->>O: Aplicació confirmada sense nou CHARGE
end
Note over S,A: No existeix ruta completa d'assignar pagament existent al PaymentService actual, aquesta seqüència és disseny.
```

### 4.3. Acció independent: retornar diners efectivament sortits — OBJECTIU i servei de registre existent

```mermaid
sequenceDiagram
autonumber
actor O as Responsable autoritzat
participant S as OverpaymentResolutionService [DISSENY]
participant B as Entitat bancària [EVIDÈNCIA EXTERNA]
participant U as Registre d'excedent [DISSENY]
participant R as ManualRefundService / UC-28 [PHP]
participant DB as BD fiscal SIF
O->>S: Autoritzar retorn d'excedent al pagador legitim
S->>U: Verificar origen, saldo retornable, titular i retorns previs
alt Sortida bancària no acreditada o retorn previ existent
 S-->>O: Pendent o reús del retorn anterior, cap REFUND nou
else Retorn bancari real confirmat
 B-->>S: Referència de sortida i import efectiu
 S->>R: registerByUuid(factura/input de retorn validat)
 R->>DB: Registrar REFUND i allocation a factura [PHP existent]
 R-->>S: UUID_PAYMENT_REFUND
 S->>U: Tancar tram sobrant amb referència única de retorn [PENDENT]
 S-->>O: Retorn acreditat, import sobrant restant
end
Note over S,DB: El servei de REFUND actual exigeix una factura, un sobrant encara no assignat necessita un contracte nou. No apuntar-lo a F1 només per satisfer el validador.
```

**Contracte bloquejant de retorn d'excés no assignat (contrast UC-28):** `ManualRefundPayloadBuilder::forExistingInvoice()` **exigeix** `UUID_FACTURA` i genera una `payment_allocation` `INVOICE_REFUND` a aquella factura. L'excés real de 20 € d'un ingrés de 120 € només imputat 100 € a F1 **no pertany necessàriament a F1**: registrar els 20 € com a `INVOICE_REFUND` de F1 per esquivar el validador distorsionaria el seu `ESTAT_COBRAMENT`. Cal un model de sortida vinculada a l'`UUID_PAYMENT` extern i al dret no assignat, amb import i pagador acreditats, **sense crear una assignació fiscal falsa**. No existeix aquesta ruta completa en el servei de refund examinat.

**Claus repetides en diversos canals:** `ManualRefundPayloadBuilder` genera `REFUND|REF:<reference>` quan la referència és present, sense factura/import, i sense referència usa factura+data truncada al dia+import+banc; **A main, `PaymentService::assertSamePayload()` compara el hash de la petició completa en reús de K (V1/V2): mateixa referència però import/factura diferents → CONFLICT, no retorn silenciós de l'UUID aliè.** Dos retorns bancaris reals diferents del mateix dia/import amb K i payload idèntics encara poden col·lidir i reutilitzar el primer; el hash no comprova el fet bancari extern ni el saldo retornable. En la resolució de l'excés, confirmar **l'operació externa única i el seu payload complet** abans de decidir reús. Vegeu les tres accions independents de la [UC-28, seccions 4.2–4.4](uc-028-registrar-devolucio.md).

### 4.4. Acció independent: concedir saldo legítim sense nou ingrés — OBJECTIU

```mermaid
sequenceDiagram
autonumber
actor O as Responsable autoritzat
participant S as OverpaymentResolutionService [DISSENY]
participant U as Registre d'excedent i titular [DISSENY]
participant C as CreditBalanceService [PHP]
participant DB as credit_balance [SQL]
O->>S: Aprovar creació de saldo de l'excedent acreditat
S->>U: Bloquejar import disponible, origen i titular del dret
alt Import consumit, destinatari incorrecte o origen no acreditat
 U-->>S: Conflicte, cap saldo
else Import disponible i aprovació coherent
 S->>C: createCredit(input de saldo, import i titular) [CONNEXIÓ PENDENT]
 C->>DB: BEGIN, INSERT credit_balance, COMMIT [PHP existent]
 C-->>S: UUID_CREDIT i import disponible
 S->>U: Enllaçar UUID_CREDIT amb origen de l'excedent [PENDENT]
 S-->>O: Saldo concedit, aplicació futura és UC-29a
end
Note over S,C: createCredit() no demostra que l'excedent concret financi el saldo. La vinculació i conservació monetària són pendents.
```

### 4.5. Proves de tancament diferenciades per acció

| ID | Acció i condició | Resultat que cal acreditar |
| --- | --- | --- |
| EX-104-01 | Dues notificacions del mateix DS_ORDER, una única entrada bancària | Un sol UUID_PAYMENT i cap excedent de caixa fictici. |
| EX-104-02 | Un ingrés de 120 amb deute acreditat de 100 i cap altre deute | 100 imputables i 20 conservats fora de factura fins a decisió; no canviar TOTAL fiscal. |
| EX-104-03 | Sobren 20, s'apliquen a una segona factura legítima | Un únic ingrés extern original; assignació interna traçada, cap segon CHARGE. |
| EX-104-04 | Es decideix retorn de 20 però banc encara no l'ha executat | Expedient pendent, cap REFUND fins a evidència de sortida real. |
| EX-104-05 | Retorn real de sobrant no assignat a cap factura | Model i registre de sortida amb vincle a l'ingrés, sense inventar una assignació fiscal a F1. |
| EX-104-06 | Saldo de 20 ja concedit, petició repetida | Mateix dret econòmic i UUID_CREDIT idempotent; no duplicar saldo. |
| EX-104-07 | Sobrant 20 no imputat a F1 i operadora intenta registrar-lo amb `ManualRefundService` com `INVOICE_REFUND` de F1 | Bloquejar assignació fictícia i mantenir refund extern en expedient separat fins a model de sortida per ingrés d'origen; no alterar l'estat de cobrament de F1 per aquests 20. |
| EX-104-08 | Dos retorns reals de 20 el mateix dia/banc sense referència per la mateixa factura | Comparar identitat bancària per operació i no deduplicar només per factura+dia+import. |
### 4.6. Acció independent: autoritzar i registrar la decisió sobre els 20 € — DISSENY

**Disparador:** un excés ja confirmat i encara disponible. **Actor:** responsable amb permís específic. **Resultat:** decisió auditada, sense donar per executat un reemborsament ni crear automàticament un crèdit sense traçar-ne l'origen. Una decisió pendent és un estat vàlid. Cal controlar intents de resolució simultanis sobre els mateixos 20 €.

```plantuml
@startuml
left to right direction
actor "Responsable autoritzat" as R
rectangle "SIF PrisMa — decisió sobre sobrant (OBJECTIU)" {
 usecase "UC-104 / decisió\nEscollir destí d'excés verificat" as Decide
 usecase "Validar saldo, pagador i autorització" as Check
 usecase "UC-56\nAssignar a deute justificat" as Allocate
 usecase "UC-28\nRegistrar retorn després d'executar-lo" as Refund
 usecase "UC-29\nConcedir saldo a titular acreditat" as Credit
}
R --> Decide
Decide ..> Check : <<include>>
R --> Allocate
R --> Refund
R --> Credit
note right of Decide
 La decisió no implica que cap de les
 tres operacions ja hagi succeït.
end note
@enduml
```

```mermaid
sequenceDiagram
autonumber
actor R as Responsable
participant UI as Panell d'incidència [PENDENT]
participant S as Coordinador d'excés [DISSENY]
participant DB as Ingrés/assignacions/decisió [OBJECTIU]
R->>UI: Obrir expedient de sobrant 20 i seleccionar destí
UI->>S: decide(caseId,action,actor,requestId)
S->>DB: Bloquejar ingrés, resolucions prèvies i import disponible
alt Falta permís o titularitat acreditada
 DB-->>S: Denegat
 S-->>UI: Cap efecte monetari
else Els 20 ja són objecte d'una altra resolució
 DB-->>S: Conflicte o decisió prèvia equivalent
 S-->>UI: Reutilitzar decisió equivalent o obrir incidència
else Decisió nova vàlida
 S->>DB: Guardar actor, import, destí, motiu i correlació [OBJECTIU]
 DB-->>S: Decisió documentada, pendent d'execució quan pertoqui
 S-->>UI: Sol·licitar l'acció UC-56, UC-28 o UC-29 per separat
end
UI-->>R: Estat i justificació, sense afirmar retorn bancari prematur
```

### 4.7. Contractes del codi que condicionen la resolució

| Control | Observació contrastada | Prova específica pendent |
| --- | --- | --- |
| Suma d'assignacions | `PaymentPayloadValidator` exigeix `count(allocations) >= 1` i imports numèrics, **no** exigeix igualtat entre import del moviment i suma assignada. | `amount=120` amb assignació `100`: guardar un únic ingrés i detectar 20 sense destí; impedir que un segon procés els assigni/retorni dues vegades. |
| Identitat de cobrament | `PaymentRepository` desa `PAYLOAD_HASH`, però `PaymentService::existingResult()` reutilitza la clau sense confrontar aquest hash ni les assignacions. | Repetir clau amb imports `120`/`100` o destins distints: conflicte explícit i cap moviment nou. |
| Devolució del sobrant | `ManualRefundPayloadBuilder` exigeix factura existent, import positiu i referència/data; emet assignació `INVOICE_REFUND`. | Excés sense factura a la qual assignar el retorn: no vincular-lo a una factura aliena ni fingir que el builder ja resol la titularitat. |
| Creació de saldo | `CreditBalancePayloadBuilder` rep titular/import/origen, però no reclama `UUID_PAYMENT` original ni verifica aritmètica del sobrant. | Dos intents de concedir els mateixos 20: un únic saldo traçat i bloqueig del segon intent, també entre canals. |
| Consum del saldo | `CreditBalanceService::applyCredit()` bloqueja saldo/factura i comprova disponible/pendent dins transacció, però no demostra origen legítim en crear saldo. | Aplicar crèdit a factura no autoritzada o creditor equivocat: rebuig sense COMPENSATION. |
## 5. Traçabilitat

[UC-104 original](../06-fitxes-funcionals/uc-104.md) · [UC-56 assignació](uc-056-cercar-assignar-cobrament.md) · [UC-86 auditoria](uc-086-auditar-accio-pagament.md) · [UC-28 retorn](uc-028-registrar-devolucio.md) · [UC-29 saldo](uc-029-crear-saldo.md) · [PaymentPayloadValidator](../../sif/src/Service/PaymentPayloadValidator.php) · [PaymentRepository](../../sif/src/Repository/PaymentRepository.php) · [CreditBalanceService](../../sif/src/Service/CreditBalanceService.php) · [Revisió transversal de fons](00-revisio-moviments-inscripcions.md).
