# UC-24 · Registrar un cobrament de reclamació — fitxa i UML integrats

**Identitat funcional:** registrar un cobrament **ja confirmat** després d'una reclamació per impagament, imputant-lo a la factura SIF que continua vigent. Reclamar o enviar recordatoris és una operació de comunicació diferent (UC-43). La morositat no comporta donar de baixa la inscripció (UC-27), rectificar la factura (UC-05) ni emetre una factura nova (UC-01).

**Estat contrastat:** `ClaimPaymentService`, `ClaimPaymentPayloadBuilder` i les proves de servei existeixen al repositori; la pantalla, els enllaços de cobrament i els correus finals de reclamació figuren pendents a la documentació de fluxos.

## 1. Fitxa de cas d'ús

| Camp | Especificació particular |
| --- | --- |
| Actor | Operador de facturació/cobraments, amb autorització del canal pendent d'acreditar. |
| Disparador | Un import reclamat es cobra i l'operador disposa de la justificació del cobrament. |
| Precondició econòmica | Factura SIF ja emesa i identificada; el servei analitzat no verifica per si sol el justificant bancari ni l'historial complet de la reclamació. |
| Identificació | `uuid_factura` o `num_visible`, resolts per `ManualPaymentInvoiceRepository`. |
| Camps obligatoris del constructor | Import positiu `amount`/`import`/`pagament` i `movement_date`/`data_pag`/`dataPag`. En absència de referència també és obligatori `created_by`/`user`/`usuari`. |
| Mètode | `TRANSFERENCIA` per defecte; el builder també admet `MANUAL`; `source_channel=INTRANET`. |
| Imputació | Moviment `CHARGE` i una assignació `allocation_type=CLAIM_PAYMENT` a la factura original. |
| Resultat | `ok`, `uuid_payment`, `idempotency_reused`, `uuid_factura`, `num_visible`. |

### 1.1. Flux principal comprovat al codi

1. L'operador identifica la factura i l'import confirmat. El seguiment de fases de morositat, les comunicacions i la comprovació de l'ingrés són **precondicions externes** a aquest servei.
2. `ClaimPaymentService::registerByUuid()` o `registerByNumVisible()` rebutja identificadors buits, consulta la factura i falla si no la troba.
3. `ClaimPaymentPayloadBuilder::forExistingInvoice()` crea `movement_type=CHARGE`, `source_channel=INTRANET`, mètode i assignació `CLAIM_PAYMENT`. Pren la referència d'una de les claus `claim_reference`, `reclamation_ref`, `reclamacio_ref`, `reference`, `referencia`, `referencia_bancaria`.
4. Si existeix referència, la clau idempotent serà `CLAIM|REF:<referència_normalitzada>`. Si no existeix, exigeix usuari i genera `CLAIM|FACT:<factura>|DATA:<dia>|IMPORT:<import>|USUARI:<usuari>`.
5. `PaymentService::registerPayment()` valida i cerca el moviment existent per clau idempotent; si és nou, `PaymentRepository` crea `payment_transaction`, `payment_allocation` i recalcula `ESTAT_COBRAMENT`. El servei retorna els identificadors de moviment i factura.

### 1.2. Alternatives i errors

| Escenari | Resposta observada o requisit pendent |
| --- | --- |
| R1. Cobrament complet després de reclamar | El calculador econòmic pot establir `PAID`; el cobrament no crea una nova factura fiscal. |
| R2. Cobrament parcial | El calculador pot establir `PARTIAL` i continua existint un import pendent; cal que la pantalla actualitzi l'estat de reclamació de manera diferenciada. |
| R3. Reintent equivalent | `PaymentService` retorna el mateix `uuid_payment` sense registrar dues vegades el cobrament. |
| E1. Factura absent | Rebuig per `ClaimPaymentService` abans del moviment econòmic. |
| E2. Import no positiu, data absent o mètode invàlid | Rebuig per `ClaimPaymentPayloadBuilder`. |
| E3. Sense referència ni usuari | El constructor rebutja l'operació, perquè necessita `created_by` per formar la clau de reserva. |
| **P1. Referència no unívoca** | La clau `CLAIM|REF:<referència>` no incorpora la factura ni l'import; cal comprovar que una referència no identifica cobraments diferents. |
| **P2. Reclamació vs cobrament** | El constructor pot registrar el pagament sense verificar l'existència d'un expedient de reclamació ni el seu estat; aquests passos s'han d'integrar al flux de gestió. |
| **P3. Comunicació / URL** | El servei de registre no envia el correu de reclamació ni genera un enllaç de pagament; el document de fluxos els dona per pendents. |
| **P4. Registre d'accions** | No s'ha acreditat que aquesta ruta de servei escrigui totes les traces transversals `payment_action_event` i les notificacions previstes pel disseny final. |

**Persistència executable d'aquest cas:** moviment `CHARGE` a `payment_transaction`, assignació `CLAIM_PAYMENT` a `payment_allocation`, i actualització de l'estat de cobrament de la factura. No crea número fiscal, registre `ALTA`, rectificativa ni cua fiscal.

**Proves localitzades, no executades:** `ClaimPaymentServiceTest::testRegistersClaimPaymentAgainstExistingInvoiceWithoutFiscalIssue`, `testRegistersClaimPaymentByVisibleInvoiceNumber` i `testRejectsUnknownInvoiceBeforeRegisteringClaimPayment`.

### 1.3. Revisió: el cobrament reclamat s'ha d'atribuir a l'operació correcta — PENDENT

`CLAIM_PAYMENT` és un tipus d'assignació **a factura**, no una assignació monetària al nivell de la inscripció reclamada. El registre de l'ingrés confirmat ha de conservar identificador de reclamació, `UUID_PAYMENT` i l'import atribuït a cada inscripció que es cobra. Si una reclamació és compartida per diverses inscripcions, l'import s'ha de repartir segons deute efectiu, no duplicar en cada inscripció. Reintentar una transferència ja registrada com a pagament ordinari no ha de crear un cobrament nou amb clau `CLAIM` diferent; la conciliació del fet bancari original és una validació pendent.

[Registre transversal proposat](00-revisio-moviments-inscripcions.md).

### 1.4. Fases reals de reclamació i cobrament — contrast amb el xat original

**Calendari i estats descrits per PrisMa, no pel ClaimPaymentService:** un primer pagament es demana abans de començar el curs i cal completar-lo en acabar. Quan una persona no paga a l'inici, es reclama l'import; amb justificació, pot continuar inscrita fins a la segona setmana (UC-96). Aleshores, si **no ha pagat res**, el procediment històric preveu una baixa administrativa; si hi ha algun import abonat, el cas pot continuar en seguiment. També hi ha recordatoris l'últim dia del curs, una setmana més tard i al cap d'un mes, i després la consideració de morositat amb noves reclamacions. Cada fase té un apartat propi a la intranet. **Aquests terminis són informació del funcionament explicat al xat**, no un cronograma automàtic acreditat ni una regla fiscal. La decisió de baixa es tramita separadament per UC-27/72; reclamar no és donar de baixa ni anul·lar la factura.

**R-ACADEMIC — matrícula, deute i factura:** INSC_CURS='M' correspon a morositat en el llegat; no significa factura fiscal anul·lada, pagament negat ni absència de cobrament parcial. En els casos descrits, un participant que ha completat el curs sense pagar-lo del tot pot mantenir-se en morositat i no estar disponible l'opció ordinària de baixa; aquestes condicions d'elegibilitat són de gestió i s'han de validar al servidor. El SIF ha de distingir `ESTAT_COBRAMENT` de l'estat acadèmic i de la fase de reclamació.

**R-CROSS — transferència d'un deute ja cobrat per altra via:** abans d'invocar `ClaimPaymentService` cal verificar la referència bancària, DS_ORDER/IDPAG o justificant del **cobrament efectiu**, comparar-lo també amb pagaments UC-22/23 i obrir la factura original/els imports ja aplicats. Les claus actuals `CLAIM|REF:<referència>` i `TRANSFERENCIA|REF:<referència>` no són iguals; per tant, la idempotència interna de PaymentService per **una sola clau** no prova absència de duplicat intercanal. Si el mateix ingrés ja consta, correlacionar-lo amb la reclamació i no crear un segon CHARGE; si el banc encara no l'ha confirmat, registrar seguiment, no cobrament.

**R-PART — cobrament parcial:** una fracció confirmada deixa deute pendent i la reclamació continua només per l'import net encara degut, sense emetre nova factura ni reiniciar per defecte tota la seqüència de correus. Si es paga tot, actualitzar la fase de reclamació/estat acadèmic mitjançant una acció de gestió traçada, sense identificar automàticament l'estat M amb un estat fiscal. Els correus posteriors han d'utilitzar import pendent real, URL vigent i, quan correspongui, enllaç segur al PDF/QR de la factura ja emesa; no enviar recordatoris de pagament per imports cobrats.

### 1.5. Proves d'acceptació de morositat i reclamació (no executades)

| ID | Escenari | Resultat exigible |
| --- | --- | --- |
| RC-01 | Curs sense primer pagament; excepció justificada fins a segona setmana | Pròrroga i reclamació registrades; cap CHARGE inventat. |
| RC-02 | Segona setmana: zero pagat vs import parcial abonat | Separar decisió de baixa/seguiment de l'estat fiscal; aplicar el procediment de gestió autoritzat. |
| RC-03 | Recordatoris últim dia, +1 setmana, +1 mes | Fase, data, import pendent i comunicació traçats, sense enviament sobre deute ja satisfet. |
| RC-04 | Cobrament de reclamació ja registrat com a transferència o Redsys | Conciliació intercanal i cap segon CHARGE. |
| RC-05 | Cobrament parcial d'una factura reclamada | PARTIAL i reclamació només del pendent net, sense factura fiscal nova. |
| RC-06 | Cobrament total d'inscripció en estat M | PAID per pagament real; eventual estat acadèmic separat i auditat. |
| RC-07 | Callback tardà després de baixa/canvi/rectificació | Registrar el fet real i revisar-ne l'assignació, sense reactivar inscripció automàticament. |
| RC-08 | Correus de reclamació després de pagar | No repetir URL antiga; oferir document/factura amb permís i estat econòmic actualitzat. |
## 2. Diagrama de casos d'ús — PlantUML

```plantuml
@startuml
left to right direction
actor "Operador de cobraments" as O
rectangle "SIF PrisMa" {
  usecase "UC-24\nRegistrar cobrament reclamat" as Claim
  usecase "Localitzar factura vigent" as Find
  usecase "UC-02\nRegistrar pagament\nsobre factura existent" as Pay
  usecase "UC-43\nGestionar comunicacions\nde reclamació" as Mail
  usecase "UC-27\nDonar de baixa inscripció" as Drop
}
O --> Claim
Claim ..> Find : <<include>>
Claim ..> Pay : <<include>>
O --> Mail
O --> Drop
note bottom of Claim
  La reclamació no anul·la la factura.
  Els correus i la baixa són accions separades.
end note
@enduml
```

### Vista de casos d’ús per a GitHub (Mermaid)

```mermaid
flowchart LR
  actor_0["Operador de cobraments"]
  subgraph SIF_BOX["SIF PrisMa"]
    uc_0(["UC-24<br/>Registrar cobrament reclamat"])
    uc_1(["Localitzar factura vigent"])
    uc_2(["UC-02<br/>Registrar pagament<br/>sobre factura existent"])
    uc_3(["UC-43<br/>Gestionar comunicacions<br/>de reclamació"])
    uc_4(["UC-27<br/>Donar de baixa inscripció"])
  end
  actor_0 --> uc_0
  uc_0 -.->|include| uc_1
  uc_0 -.->|include| uc_2
  actor_0 --> uc_3
  actor_0 --> uc_4
```

## 3. Subdiagrama de classes — PHP observat

```mermaid
classDiagram
direction LR
class ClaimPaymentService {
  +registerByUuid(sifDb,uuidFactura,input) array
  +registerByNumVisible(sifDb,numVisible,input) array
}
class ManualPaymentInvoiceRepository {
  +findByUuid(db,uuid,forUpdate) array
  +findByNumVisible(db,numVisible,forUpdate) array
}
class ClaimPaymentPayloadBuilder {
  +forExistingInvoice(uuidFactura,input) array
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
ClaimPaymentService --> ManualPaymentInvoiceRepository : identifica original
ClaimPaymentService --> ClaimPaymentPayloadBuilder : CHARGE CLAIM_PAYMENT
ClaimPaymentService --> PaymentService : delega moviment
PaymentService --> PaymentPayloadValidator : valida
PaymentService --> PaymentRepository : persisteix / reutilitza
PaymentRepository --> PaymentStatusCalculator : recalcula estat
```

## 4. Seqüència — cobrament de reclamació sobre factura existent

```mermaid
sequenceDiagram
autonumber
actor O as Operador cobraments
participant UI as Canal intranet [integració pendent]
participant CS as ClaimPaymentService
participant IR as ManualPaymentInvoiceRepository
participant B as ClaimPaymentPayloadBuilder
participant PS as PaymentService
participant PR as PaymentRepository
participant DB as BD fiscal SIF
O->>UI: Confirma cobrament reclamat i identifica factura
Note over O,UI: Comprovar justificant i fase de reclamació: pendent d'integració
UI->>CS: registerByUuid(db,uuidFactura,input)
CS->>IR: findByUuid(db,uuidFactura)
IR->>DB: SELECT factura
alt Factura absent
  IR-->>CS: null
  CS--xUI: Error de validació
else Factura identificada
  IR-->>CS: factura
  CS->>B: forExistingInvoice(uuidFactura,input)
  alt Import/data/referència-usuari invàlids
    B--xCS: Error de validació
    CS--xUI: Error
  else Payload CHARGE + CLAIM_PAYMENT vàlid
    B-->>CS: payload idempotent
    CS->>PS: registerPayment(payload)
    PS->>PR: findByIdempotencyKey(key,true) en transacció
    alt Reintent
      PR-->>PS: uuid_payment existent
    else Moviment nou
      PS->>PR: createPayment(payload)
      PR->>DB: INSERT payment_transaction + payment_allocation
      PR->>DB: UPDATE factura.ESTAT_COBRAMENT
    end
    PS-->>CS: ok,uuid_payment,idempotency_reused
    CS-->>UI: result + UUID i número factura
    UI-->>O: Mostra estat de cobrament
  end
end
Note over CS,DB: No hi ha emissió fiscal ni rectificativa per cobrar un impagat
```

### 4.1. Seqüència — seguiment, cobrament parcial i conciliació (OBJECTIU)

```mermaid
sequenceDiagram
autonumber
actor O as Gestió
participant Claims as Reclamacions intranet [integració pendent]
participant Bank as Banc/Redsys
participant Rec as Conciliació intercanal [DISSENY]
participant P as ClaimPaymentService [existent]
participant DB as BD SIF
O->>Claims: Consultar deute, fase de reclamació i factura original
Claims->>DB: Llegir pendent net i cobraments ja aplicats
alt Només s'envia recordatori o hi ha promesa de pagament
 Claims-->>O: Registrar fase/termini, sense CHARGE
else Es comunica un ingrés
 Claims->>Bank: Comprovar confirmació, import i identificador real
 Claims->>Rec: Buscar mateix fet a UC-22, UC-23 i Redsys
 alt Ingrés ja registrat
  Claims-->>O: Correlacionar reclamació amb moviment existent
 else Nou ingrés verificat
  Claims->>P: registerByUuid(factura original, cobrament)
  P->>DB: CHARGE i CLAIM_PAYMENT
  P-->>Claims: UUID_PAYMENT
  Claims-->>O: Pendent recalculat, reclamació segueix o es tanca
 end
end
Note over Claims,Rec: Seguiment de fases, detecció intercanal i correus finals encara són integració pendent.
```
### 4.2. Acció independent: identificar el cobrament d'una reclamació sense confondre expedient i operació bancària — DISSENY

**Actor/disparador:** gestió rep la confirmació d'una entrada mentre hi ha una reclamació oberta per una factura original. **Identitats exigibles:** `claim_case_id` o referència de **l'expedient de reclamació**, `external_receipt_id` de **l'entrada econòmica real** (banc/Redsys) i `UUID_FACTURA` de la factura que continua vigent. Una referència de reclamació pot agrupar diversos cobraments parcials, i el mateix ingrés pot haver entrat al SIF pel canal UC-22, UC-23 o Redsys abans que el gestor obri la pantalla de morositat. **Postcondició:** fet extern i imputació identificats, o conflicte; consultar l'expedient no crea cap moviment de caixa.

**Límit del PHP:** `ClaimPaymentPayloadBuilder::claimReference()` prioritza `claim_reference`, `reclamation_ref`, `reclamacio_ref` abans de `reference`/referència bancària. Si l'adaptador transmet una referència d'expedient constant com a `claim_reference`, `idempotencyKey()` genera `CLAIM|REF:<referència expedient>` per **tots** els cobraments parcials d'aquell expedient, encara que les entrades externes siguin diferents. **A main, `PaymentService::assertSamePayload()` sí compara import i `payment_allocation` en reús de K:** si el segon ingrés canvia import/factura/referència efectiva, retorna conflicte, no el primer UUID com a ingrés del segon. Si l'event extern és diferent però tots els camps del payload són idèntics, el hash no el pot distingir i pot reutilitzar E1. La clau de l'expedient tampoc no incorpora factura ni identificador bancari per ingrés; el canvi a un altre destí amb mateixa K queda bloquejat pel hash, però **no genera la clau nova necessària per registrar E2**. Una `claim_reference` i una `bank_reference` han de ser camps **d'identitat diferents** al contracte objectiu, sense inferir que avui són dues columnes separades del SIF. **Persistència concreta:** el builder copia la referència escollida a `payload['reference']`; `PaymentRepository::createPayment()` desa aquest valor a `payment_transaction.REFERENCIA_BANCARIA` i la taula base no té una columna `CLAIM_CASE_ID`. Així, si `claim_reference` vol dir codi **intern** de l'expedient, el camp anomenat referència bancària acaba contenint el codi de reclamació, sense prova de la referència real de cada abonament. El builder també aporta `created_by`, però `createPayment()` no el persisteix en cap columna específica de l'apunt econòmic; no atribuir-li una auditoria d'actor completa.

```plantuml
@startuml
left to right direction
actor "Gestió de reclamacions" as G
actor "Banc / Redsys" as B
rectangle "SIF PrisMa — UC-24 / IDENTIFICAR INGRÉS (DISSENY)" {
 usecase "Identificar entrada vinculada\na expedient de reclamació" as Identify
 usecase "Distingir ID d'expedient\nd'ID bancari de cada abonament" as Distinct
 usecase "Consultar CHARGE i assignacions\nja persistits per qualsevol canal" as Existing
 usecase "UC-02\nRegistrar nou ingrés confirmat" as New
 usecase "UC-56\nVincular ingrés existent a la reclamació" as Link
}
G --> Identify
B --> Identify
Identify ..> Distinct : <<include>>
Identify ..> Existing : <<include>>
G --> New
G --> Link
@enduml
```

### Vista de casos d’ús per a GitHub (Mermaid)

```mermaid
flowchart LR
  actor_0["Gestió de reclamacions"]
  actor_1["Banc / Redsys"]
  subgraph SIF_BOX["SIF PrisMa — UC-24 / IDENTIFICAR INGRÉS (DISSENY)"]
    uc_0(["Identificar entrada vinculada<br/>a expedient de reclamació"])
    uc_1(["Distingir ID d'expedient<br/>d'ID bancari de cada abonament"])
    uc_2(["Consultar CHARGE i assignacions<br/>ja persistits per qualsevol canal"])
    uc_3(["UC-02<br/>Registrar nou ingrés confirmat"])
    uc_4(["UC-56<br/>Vincular ingrés existent a la reclamació"])
  end
  actor_0 --> uc_0
  actor_1 --> uc_0
  uc_0 -.->|include| uc_1
  uc_0 -.->|include| uc_2
  actor_0 --> uc_3
  actor_0 --> uc_4
```

```mermaid
sequenceDiagram
autonumber
actor G as Gestió
participant R as ClaimExternalReceiptResolver [DISSENY]
participant B as Banc / Redsys [FONT EXTERNA]
participant F as Factura i deute net vigent [LECTURA]
participant P as payment_transaction/allocation [LECTURA]
participant C as ClaimPaymentService [PHP]
G->>R: Identificar abonament E2 per expedient CLAIM-7 i factura F
R->>F: Validar factura F, titular i deute actual
R->>B: Acreditar abonament E2, import, data i identitat real
alt E2 no acreditat o titular/destí incompatible
 R-->>G: PENDING/CONFLICT, cap CHARGE
else E2 confirmat
 R->>P: Cercar E2 globalment en Redsys/TRANSFERENCIA/CLAIM/FRACCIO
 alt E2 ja consta en una altra família de claus
  P-->>R: UUID_PAYMENT_E2 i assignació real
  R-->>G: Vincular expedient al moviment real [PENDENT], no nou CHARGE
 else E2 nou i diferent d'E1 del mateix expedient
  P-->>R: Cap moviment E2 previ, però comprovar clau CLAIM|REF de l'expedient
  alt CLAIM|REF:CLAIM-7 ja usada per E1
   R-->>G: CONFLICT de clau derivada, cal identitat idempotent PER INGRÉS [DISSENY]
  else Clau per ingrés nou segura i cobertura de factura comprovada
   R->>C: registerByUuid(F,input) després de guard [PENDENT]
   C-->>G: UUID_PAYMENT_E2 o incidència
  end
 end
end
Note over R,C: El resolvedor i el vincle d'expedient no són serveis executables acreditats.
```

### 4.3. Acció independent: registrar un segon cobrament parcial d'una mateixa reclamació — PHP actual versus resultat objectiu

**Actor/disparador:** el banc acredita un primer ingrés E1 de 40 € i, més tard, **un altre ingrés diferent** E2 de 30 € per la mateixa factura F i expedient de reclamació `CLAIM-7`. **Comportament PHP amb `claim_reference=CLAIM-7` a totes dues peticions:** el constructor genera la **mateixa** clau `CLAIM|REF:CLAIM-7`; **A main, `PaymentService` rebutja amb CONFLICT el segon intent** si l'import, la factura o un altre camp del payload difereixen del primer, en lloc de tornar el primer UUID. Per tant, **el segon cobrament no es registra** per aquest camí, encara que el banc l'hagi confirmat. Si E2 és una altra entrada real però el payload complet coincideix, el hash tampoc no pot distingir els dos fets i retorna el primer ingrés. Reutilitzar una clau només és correcte si la segona petició és una repetició **del mateix ingrés real**, no un nou pagament de la mateixa reclamació.

```plantuml
@startuml
left to right direction
actor "Gestió de cobraments" as G
actor "Banc" as B
rectangle "SIF PrisMa — UC-24 / SEGON INGRÉS PARCIAL" {
 usecase "Registrar nou ingrés parcial\nper un deute reclamat" as Record
 usecase "Comprovar identitat bancària E2\ndiferent d'E1" as Unique
 usecase "Comprovar factura i import\npendent real abans del CHARGE" as Debt
 usecase "Reutilitzar UUID_PAYMENT només\nper reintent equivalent d'E2" as Idp
}
G --> Record
B --> Unique
Record ..> Unique : <<include>> [guard pendent]
Record ..> Debt : <<include>> [guard pendent]
Record ..> Idp : <<include>> [guard pendent]
@enduml
```

### Vista de casos d’ús per a GitHub (Mermaid)

```mermaid
flowchart LR
  actor_0["Gestió de cobraments"]
  actor_1["Banc"]
  subgraph SIF_BOX["SIF PrisMa — UC-24 / SEGON INGRÉS PARCIAL"]
    uc_0(["Registrar nou ingrés parcial<br/>per un deute reclamat"])
    uc_1(["Comprovar identitat bancària E2<br/>diferent d'E1"])
    uc_2(["Comprovar factura i import<br/>pendent real abans del CHARGE"])
    uc_3(["Reutilitzar UUID_PAYMENT només<br/>per reintent equivalent d'E2"])
  end
  actor_0 --> uc_0
  actor_1 --> uc_1
  uc_0 -.->|include| uc_1
  uc_0 -.->|include| uc_2
  uc_0 -.->|include| uc_3
```

```mermaid
sequenceDiagram
autonumber
actor G as Operador
participant C as ClaimPaymentService [PHP]
participant B as ClaimPaymentPayloadBuilder [PHP]
participant S as PaymentService [PHP]
participant DB as payment_transaction/allocation [SQL]
G->>C: registerByUuid(F,amount=40,claim_reference=CLAIM-7, E1)
C->>B: forExistingInvoice(F,input E1)
B-->>C: K=CLAIM|REF:CLAIM-7
C->>S: registerPayment(CHARGE F/40,K)
S->>DB: BEGIN, INSERT UUID_PAYMENT_E1 i allocation F/40
S->>DB: COMMIT
S-->>C: UUID_PAYMENT_E1,idempotency_reused=false
C-->>G: Ingrés E1 enregistrat
G->>C: registerByUuid(F,amount=30,claim_reference=CLAIM-7, E2 real diferent)
C->>B: forExistingInvoice(F,input E2)
B-->>C: Mateixa K encara que import/ref bancària canviïn
C->>S: registerPayment(CHARGE F/30,K)
S->>DB: BEGIN, trobar moviment K = UUID_PAYMENT_E1 FOR UPDATE
DB-->>S: E1 amb IMPORT=40 i només allocation F/40
S->>S: assertSamePayload(CHARGE F/30,hash original F/40) [PHP main]
S--xC: CONFLICT per import diferent; E2 no queda enregistrat
C-->>G: No declarar segona quota pagada; corregir la identitat K per event extern
Note over B,DB: Amb la mateixa K i un payload completament idèntic, main reutilitzaria E1; el hash no distingeix dos ingressos bancaris realment diferents amb dades iguals.
```

### 4.4. Acció independent: verificar el deute net i tancar la reclamació sense crear una altra factura — DISSENY

**Actor/disparador:** després de rebre un ingrés, gestió vol considerar el deute reclamat extingit o continuar-ne el seguiment. **Precondicions:** factura fiscal original, assignacions `CHARGE`/`COMPENSATION`/`REFUND` reals, rectificatives si han variat l'obligació i identificació de la part de l'import objecte de reclamació. **Postcondició:** `CLAIM_PARTIAL`, `CLAIM_SETTLED` o `CLAIM_REVIEW` com a **estats d'expedient proposats, no enums actuals**, sempre separats de `factura.ESTAT_COBRAMENT`, estat acadèmic i possible registre AEAT. `ClaimPaymentService` no consulta ni actualitza l'estat d'un expedient de reclamació; que el seu resultat tingui `ok=true` no implica ni que el deute sigui zero ni que hagi sortit un correu de tancament.

```plantuml
@startuml
left to right direction
actor "Responsable de morositat" as R
rectangle "SIF PrisMa — UC-24 / TANCAR RECLAMACIÓ (DISSENY)" {
 usecase "Verificar deute net de l'expedient" as Close
 usecase "Consultar factures originals,\ncobraments i devolucions efectius" as Net
 usecase "Contrastar rectificatives\ni imports de reclamació" as Fiscal
 usecase "UC-43\nComunicar estat del deute a l'interessat" as Notice
}
R --> Close
Close ..> Net : <<include>>
Close ..> Fiscal : <<include>> [quan variï la factura]
R --> Notice
@enduml
```

### Vista de casos d’ús per a GitHub (Mermaid)

```mermaid
flowchart LR
  actor_0["Responsable de morositat"]
  subgraph SIF_BOX["SIF PrisMa — UC-24 / TANCAR RECLAMACIÓ (DISSENY)"]
    uc_0(["Verificar deute net de l'expedient"])
    uc_1(["Consultar factures originals,<br/>cobraments i devolucions efectius"])
    uc_2(["Contrastar rectificatives<br/>i imports de reclamació"])
    uc_3(["UC-43<br/>Comunicar estat del deute a l'interessat"])
  end
  actor_0 --> uc_0
  uc_0 -.->|include| uc_1
  uc_0 -.->|include| uc_2
  actor_0 --> uc_3
```

```mermaid
sequenceDiagram
autonumber
actor R as Gestió
participant C as ClaimCaseReconciler [DISSENY]
participant F as factura/factura_registres/rectificacions [LECTURA]
participant P as payment_transaction/allocation [LECTURA]
participant E as Expedient de reclamació [DISSENY/LEGAT]
participant N as UC-43 notificacions [CANAL PENDENT]
R->>C: reviewClaim(claimCaseId,uuidFactura)
C->>F: Llegir factura vigent i canvis fiscals rellevants
C->>P: Calcular net per factura/obligació amb assignacions reals
C->>E: Contrastar principal reclamat, titular i ingressos ja reconeguts
alt Només s'ha fet promesa de pagament o ingrés no verificat
 C-->>R: PENDING/REVIEW, sense CHARGE nou ni «pagada»
else Queda import reclamat legítimament pendent
 C-->>R: CLAIM_PARTIAL / import i termini real pendents [DISSENY]
else Deute de l'expedient realment conciliat i zero
 C->>E: Tancar expedient amb actor, correlació i versions [DISSENY]
 E-->>C: CLAIM_SETTLED
 C-->>R: Expedient tancat, factura original no reemesa
 opt Informar del tancament al destinatari autoritzat
  R->>N: Preparar comunicació UC-43 amb document fiscal autoritzat [PENDENT]
 end
end
Note over C,N: Tancament d'expedient i missatgeria no implementats per ClaimPaymentService, no deduir-los d'idempotency_reused=true.
```

| Prova pendent | Escenari | Resultat exigible |
| --- | --- | --- |
| CR-24-06 | Factura F amb reclamació CLAIM-7; ingressos E1/40 i E2/30 reals amb la mateixa clau derivada de l'expedient | A main, segon payload d'import diferent → CONFLICT; el contracte final requereix clau per ingrés real diferent, conservant referència d'expedient separada. |
| CR-24-07 | Transferència E1 ja enregistrada UC-22 i operadora la registra des de UC-24 per la mateixa reclamació | Correlacionar UUID_PAYMENT_E1 amb el cas sense segon CHARGE; les claus `TRANSFERENCIA|REF` i `CLAIM|REF` són diferents. |
| CR-24-08 | Mateixa clau de reclamació sol·licitada per factura B mentre moviment original correspon a A | Detectar `UUID_PAYMENT_A` + `uuid_factura=B` a la resposta actual; contracte objectiu rebutja falsa assignació a B. |
| CR-24-09 | Primer cobrament parcial deixa deute i es prem «Tancar reclamació» | `CLAIM_PARTIAL`; factura/deute encara pendents i cap nova emissió fiscal. |
| CR-24-10 | Dues entrades reals han extingit l'import reclamat, però la factura original té altres obligacions no incloses en el cas | Tancar només l'expedient amb abast acreditat, sense afirmar `ESTAT_COBRAMENT=PAID` de tota la factura ni enviar document fiscal incorrecte. |
| CR-24-11 | `claim_reference=CLAIM-7` identifica l'expedient i l'ingrés extern E1 té referència bancària `BAN-101` | No presentar `CLAIM-7` com a referència bancària de E1; el PHP actual prioritza `CLAIM-7` i el desa a `REFERENCIA_BANCARIA`. Separar ambdós camps en el contracte futur i registrar prova bancària. |

## 5. Traçabilitat

[Fitxa anterior UC-24](../06-fitxes-funcionals/uc-024.md) · [Catàleg UC-24](../04-estat-final/33-casos-us-sif.md) · [Fluxos de morositat](../03-canvis-pendents/04-fluxos-facturacio.md) · [ClaimPaymentService](../../sif/src/Service/ClaimPaymentService.php) · [ClaimPaymentPayloadBuilder](../../sif/src/Service/ClaimPaymentPayloadBuilder.php) · [PaymentService](../../sif/src/Service/PaymentService.php) · [ClaimPaymentServiceTest](../../sif/tests/Integration/ClaimPaymentServiceTest.php).

**No acreditat:** proves executades, estat del banc, permisos, correus i enllaços finals, conciliació amb expedient de reclamació ni desplegament.
