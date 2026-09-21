# UC-02 · Registrar un pagament sobre factura existent — fitxa i UML integrats

**Estat:** `PaymentService` i `PaymentRepository` estan contrastats a la branca documental `docs/uml-fitxes-integrades-2026-09-20`; la vinculació final de cadascuna de les pantalles i les regles d'autorització no es consideren acreditades. **Casos relacionats:** UC-01 (emissió inicial), UC-04 (factura emesa abans de cobrar), UC-22 (transferència), UC-23 (fracció), UC-24 (cobrament de reclamació), UC-28 (devolució) i UC-29a (compensació).

## 1. Fitxa de cas d'ús

| Camp | Especificació |
| --- | --- |
| Actor que inicia l'acció | Operador o procés automàtic mitjançant el canal corresponent, amb validació de permisos **pendent d'acreditar en la integració final**. |
| Objectiu | Registrar un moviment econòmic confirmat i assignar-lo a una factura fiscal ja existent; no tornar a emetre aquesta factura. |
| Precondicions | Factura SIF identificada; confirmació del cobrament obtinguda fora d'aquest servei; import i assignació coneguts; clau idempotent pròpia del moviment. |
| Entrada comuna exigida pel validador actual | `idempotency_key`, `movement_type`, `method`, `source_channel`, `amount`, `movement_date`, `allocations`. |
| Tipus i mètodes admesos pel validador | Tipus `CHARGE`, `REFUND`, `COMPENSATION`; mètodes `REDSYS`, `TRANSFERENCIA`, `COMPENSACIO`, `MANUAL`. Les normes específiques de cada tipus pertanyen als casos relacionats. |
| Assignacions | `allocations` ha de contenir almenys una entrada amb `uuid_factura`, `amount` i `allocation_type`; el repositori les recorre individualment. |
| Resultat del servei | `ok`, `idempotency_reused`, `uuid_payment`. El servei genèric **no retorna necessàriament** el nou `ESTAT_COBRAMENT`. |

### 1.1. Flux principal executable

1. L'adaptador específic obté l'evidència del cobrament confirmat i identifica la factura preexistent. **El servei genèric no comprova per si sol la identitat de l'operador ni l'evidència bancària.**
2. `PaymentService::registerPayment(payload)` valida els camps amb `PaymentPayloadValidator`.
3. Obre una transacció i cerca `payment_transaction` amb la mateixa clau idempotent, amb bloqueig.
4. Si no existeix el moviment, `PaymentRepository::createPayment()` genera un UUID, crea el registre econòmic amb `ESTAT=CONFIRMED` i enregistra cada assignació.
5. Després de cada assignació, el repositori bloqueja i consulta la factura, suma moviments `CHARGE`/`COMPENSATION` i `REFUND` i recalcula `factura.ESTAT_COBRAMENT` mitjançant `PaymentStatusCalculator`.
6. Confirma la transacció i retorna `uuid_payment`. **No genera `factura_registres`, número fiscal, hash ni cua fiscal.**

### 1.2. Alternatives i situacions d'error

| Situació | Comportament implementat o pendent |
| --- | --- |
| A1. Mateixa clau idempotent | El servei retorna el moviment existent amb `idempotency_reused=true`; no crea una segona assignació. |
| A2. Col·lisió SQL de clau única | Captura l'error `23000`, rellegeix el pagament per la clau en una nova transacció i retorna el moviment trobat. |
| A3. Pagament inferior al total | `PaymentStatusCalculator` retorna `PARTIAL` quan l'import net és positiu però inferior al total. |
| A4. Pagament igual al total | L'estat calculat és `PAID`. |
| A5. Pagament superior al total | L'estat calculat és `OVERPAID`; la decisió operativa sobre l'excés correspon a UC-104, no queda resolta automàticament aquí. |
| E1. Assignació a factura inexistent | El repositori falla en intentar consultar la factura; la transacció no s'ha de confirmar parcialment. |
| E2. Falta un camp o un mètode no és admès | El validador rebutja la petició. |
| Límits coneguts | El validador genèric verifica la forma i la numericitat dels imports però **no acredita per si sol** saldo disponible, autorització, suma d'assignacions igual a l'import, evidència del cobrament ni coherència semàntica entre mètode i tipus. Cal cobrir-ho per canal i cas específic. |

La implementació també calcula estats `PENDING`, `PARTIALLY_REFUNDED` i `REFUNDED` segons càrrecs i devolucions; **aquestes devolucions no equivalen a tornar a emetre una factura ni substitueixen el procés fiscal que pugui correspondre**.

**Persistència principal:** `payment_transaction`, `payment_allocation` i actualització de `factura.ESTAT_COBRAMENT`. No atribuir al servei genèric l'escriptura dels registres d'auditoria funcionals que estan previstos als documents d'estat final però no apareixen en aquest camí de codi.

**Proves localitzades (no executades en aquesta revisió):** `RegisterPaymentTest::testRegisterPaymentCreatesTransactionAndAllocationOnly` i `testRegisterPaymentReusesSamePaymentForSameIdempotencyKey`.

### 1.3. Revisió: la imputació a factura no és una imputació a inscripció — PENDENT

El registre existent `payment_transaction` → `payment_allocation` actualitza l'estat de la **factura**, però l'assignació no conté `ID_INSC`. UC-02 ha d'identificar i validar també l'import corresponent a **cada inscripció** abans de donar per completat un cobrament, una fracció, una devolució o una compensació. Si una transferència de 200 € cobreix dues inscripcions, hi ha **un moviment extern** de 200 € i **dues atribucions internes** (p. ex. 100 € i 100 € quan les dades reals ho justifiquin), vinculades al mateix cobrament; no dues entrades de caixa. La suma atribuïda s'ha de reconciliar amb la suma assignada a les factures i amb el moviment extern. Una reassignació posterior entre inscripcions no pot cridar `registerPayment(CHARGE)` com si arribessin diners nous.

**Risc addicional comprovat al codi:** `PaymentPayloadValidator` només exigeix imports numèrics i no acredita que `SUM(allocations.amount)=payment.amount`, que cada import sigui estrictament positiu ni que una clau reutilitzada porti el mateix payload; són validacions pendents. [Model i invariants de fons per inscripció](00-revisio-moviments-inscripcions.md).

### 1.4. Identificar factura prèvia a «Passar pagaments» i a Redsys

**Intranet llegada.** A `/alumnes/pagaments/`, el procediment `efectuarPagamentFacturaGenerada()` consulta `buscarPagamentsByFact`, `A_PAGAR`, `PAGAMENT`, `FACTURA_RELACIONADA`, `FRACCIO`, `IDPAG` i dades del receptor; en el circuit antic calcula el nou pendent amb imports de la inscripció. En la integració SIF, si la factura fiscal ja és real i està emesa —inclosa la d'empresa abans de cobrar—, registrar el moviment amb `UUID_FACTURA` i la clau de la **transacció externa**, sense modificar receptor, concepte, import ni número de factura. Els camps `PAGAMENT` i `DATA PAG` del llegat només poden sincronitzar-se **després** del commit econòmic com a resum.

**Redsys i una factura ja emesa amb una altra clau.** La validació de `DS_ORDER` acredita quin intent TPV ha notificat Redsys; **no acredita que calgui emetre factura nova**. La factura prèvia pot haver estat emesa per un procés de grup/empresa o manual amb un `idempotency_key` diferent del callback. El despatxador ha de localitzar la cobertura per `fact_rels/ID_INSC/UUID_FACTURA` i, si és inequívoca, registrar el `CHARGE` contra el document existent. Si hi ha diversos candidats o la inscripció ha canviat/ha estat donada de baixa, conservar prova del cobrament real i derivar a conciliació; no forçar un nou `issueInvoice()`.

**Límits monetaris del servei actual.** `PaymentPayloadValidator` comprova camps, que les assignacions siguin un array no buit i la numericitat dels imports, però no acredita que la **suma de trams** coincideixi amb l'entrada externa ni que tot tram estigui disponible. `PaymentService::existingResult()` retorna un `UUID_PAYMENT` per la mateixa clau sense comparar el nou payload amb el `PAYLOAD_HASH` antic: l'operació objectiu ha de detectar conflictes de mateixa clau/import/destí diferents. `PaymentRepository::createPayment()` crea un moviment **nou** amb totes les assignacions, no permet afegir trams a un UUID ja confirmat; la cerca i l'assignació d'un cobrament existent corresponen a UC-56/105 encara pendents d'un writer segur.

**Diverses factures i participants.** Una transferència real pot cobrir més d'una factura; cada `payment_allocation` ha de referenciar quantitat i UUID de destí, mentre que en grups/packs el futur registre de fons **per `ID_INSC`** ha de reflectir l'atribució individual exacta. `FACTURA_RELACIONADA` i `IDPAG` poden agrupar diversos participants i intents i no són claus de deduplicació de diner. La factura fiscal emesa abans del cobrament manté `EMESA_ABANS_COBRAMENT=1` encara després de quedar pagada; el fet econòmic posterior no ha de canviar aquesta dada històrica ni marcar `E_FACT` automàticament.

### 1.5. Proves d'un pagament posterior amb cobertura fiscal (no executades)

| ID | Escenari | Resultat exigible |
| --- | --- | --- |
| CP-02-01 | Factura prèvia d'empresa pagada per una transferència real | Un `UUID_PAYMENT`, assignació contra la factura existent, sense factura nova. |
| CP-02-02 | Redsys confirma una DS_ORDER nova sobre una factura SIF antiga | Deduplicar per transacció i vincular a la factura prèvia, no pel nou IDPAG sol. |
| CP-02-03 | Factura de grup amb N inscrits i un CHARGE únic | Assignació a factura i atribucions individuals documentades segons import real. |
| CP-02-04 | Mateixa clau econòmica però import o assignacions nous | Conflicte de contingut; no retornar èxit de la petició incompatible. |
| CP-02-05 | Transferència de 150 € ja registrada però falta assignar 50 € | Reutilitzar l'UUID amb UC-56/105; no fer un segon CHARGE de 50 €. |
| CP-02-06 | `PAGAMENT` llegat diu cobrat però no hi ha prova externa | Investigar banc/TPV, sense moviment fiscal/econòmic inventat. |
| CP-02-07 | Cobrat real però sincronització acadèmica fallida | Conservar UUID_PAYMENT i reprendre UC-47/53; no reprocessar l'ingrés. |


### 1.6. Idempotència de pagament v2 a main: hash de petició i compatibilitat històrica

A main, PaymentService depèn de PayloadIdempotencyValidatorInterface i **compara el contingut del reintent amb PAYLOAD_HASH** abans de retornar un UUID_PAYMENT existent. Per a PAYLOAD_HASH_VERSION=2, assertMatches() utilitza SHA-256 de la petició canonitzada: importa el contingut i no l'ordre arbitrari de claus associatives JSON. Per a files històriques amb versió 1, serialitza la petició en l'ordre original d'entrada i compara els bytes amb el hash antic; **no reinterpretar un hash v1 com v2**. Una versió desconeguda i una petició diferent retornen conflicte. PaymentRepository::createPayment() escriu PAYLOAD_HASH_VERSION=2 a main; la migració 2026_09_21_000007 afegeix la columna amb DEFAULT 1 per a files prèvies.

**Abast del hash:** protegeix la petició del moviment i totes les allocations que s'hi passen en aquella alta. No prova que el banc hagi rebut dos ingressos diferents quan s'han fet servir claus diferents, no recupera automàticament un saldo parcial disponible per una altra factura i **no resol** que PaymentPayloadValidator encara accepti imports de trams zero/negatius o SUM(allocations)>amount. La comprovació addicional de referència externa real, titular, assignació econòmica a una altra factura i saldo roman pendent a UC-22/56/105.

| Prova de main o pendent | Resultat |
| --- | --- |
| PayloadIdempotencyFlowTest::testPaymentRetryComparesAmountAndAllocations [definida] | Mateixa clau amb import/assignacions diferents no es reutilitza en silenci; és conflicte. |
| PayloadIdempotencyFlowTest::testLegacyPaymentHashUsesLegacySerializationUntilMigrated [definida] | V1 conserva semàntica de bytes/ordre d'entrada; V2 compara payload canonitzat. |
| CP-02-17 [pendent] | Dues claus diferents referides a un únic abonament bancari exigeixen prova del fet extern; el hash de cada clau no deduplica entre claus. |

## 2. Diagrama UML de casos d'ús

El cas de cobrament posterior utilitza l'operació comuna UC-02; les variants de transferència i fracció afegeixen les seves regles i fitxes pròpies.

```plantuml
@startuml
left to right direction
actor "Operador autoritzat" as Op
actor "Procés de cobrament" as Proc
rectangle "SIF PrisMa" {
  usecase "UC-02\nRegistrar pagament\nsobre factura existent" as U2
  usecase "Validar moviment\ni assignacions" as Val
  usecase "Calcular estat\nde cobrament" as Estat
  usecase "UC-22\nRegistrar transferència" as Trans
  usecase "UC-23\nRegistrar fracció" as Frac
  usecase "UC-28\nRegistrar devolució" as Refund
}
Op --> U2
Proc --> U2
U2 ..> Val : <<include>>
U2 ..> Estat : <<include>>
Trans ..> U2 : <<include>>
Frac ..> U2 : <<include>>
Refund ..> U2 : <<include>>
@enduml
```

### Vista Mermaid del cas general de cobrament sobre factura

```mermaid
flowchart LR
  a_0["Operador autoritzat"]
  a_1["Procés de cobrament"]
  subgraph SIF_BOUNDARY["SIF PrisMa"]
    u_0(["UC-02<br/>Registrar pagament<br/>sobre factura existent"])
    u_1(["Validar moviment<br/>i assignacions"])
    u_2(["Calcular estat<br/>de cobrament"])
    u_3(["UC-22<br/>Registrar transferència"])
    u_4(["UC-23<br/>Registrar fracció"])
    u_5(["UC-28<br/>Registrar devolució"])
  end
  a_0 --> u_0
  a_1 --> u_0
  u_0 -.->|include| u_1
  u_0 -.->|include| u_2
  u_3 -.->|include| u_0
  u_4 -.->|include| u_0
  u_5 -.->|include| u_0
```

## 3. Subdiagrama de classes executables

```mermaid
classDiagram
direction LR
class PaymentService {
 +registerPayment(payload) array
 -createOrReusePayment(payload) array
 -reusePaymentAfterDuplicateKey(key) array
}
class PaymentPayloadValidator {
 +validate(payload) array
}
class TransactionRunner {
 +run(callback) mixed
}
class PaymentRepository {
 +findByIdempotencyKey(db,key,forUpdate) array
 +createPayment(db,payload) array
 -createAllocation(db,uuidPayment,allocation) void
 -refreshInvoicePaymentStatus(db,uuidFactura) void
}
class PaymentStatusCalculator {
 +calculate(invoiceTotal,charges,refunds) string
}
class UuidGenerator {
 +generate() string
}
class ManualPaymentService {
 +registerByUuid(db,uuidFactura,input) array
 +registerByNumVisible(db,numVisible,input) array
}
class ManualPaymentInvoiceRepository {
 +findByUuid(db,uuid,forUpdate) array
 +findByNumVisible(db,number,forUpdate) array
}
class ManualPaymentPayloadBuilder {
 +forExistingInvoice(uuidFactura,input) array
}
ManualPaymentService --> ManualPaymentInvoiceRepository : identifica factura
ManualPaymentService --> ManualPaymentPayloadBuilder : construeix moviment
ManualPaymentService --> PaymentService : delega
PaymentService --> PaymentPayloadValidator : valida
PaymentService --> TransactionRunner : transacció
PaymentService --> PaymentRepository : persisteix o reutilitza
PaymentRepository --> PaymentStatusCalculator : recalcula estat
PaymentRepository --> UuidGenerator : UUID
```

**Observació:** `ManualPaymentService` és l'adaptador de servei per al pagament manual d'una factura identificada per UUID o número; `sif/public/api/payments/register.php` instancia **directament** `PaymentService`. No donar per implementat un recorregut de pantalla que no s'ha traçat.

### 3.1. Projecció del model general: control d'identitat de l'ingrés i invariants monetaris — DISSENY PENDENT

El subdiagrama executiu anterior descriu el PHP que hi ha; aquest **segon subdiagrama és exclusivament contracte de disseny** i no afegeix mètodes ficticis a `PaymentService` actual. El guard ha d'identificar un fet extern únic abans de crear un `CHARGE`, verificar titularitat/assignacions i comparar reintents contradictoris.

```mermaid
classDiagram
direction LR
class PaymentIngressGateway {
 <<DISSENY: no acreditat al PHP>>
 +registerConfirmedReceipt(command) result
}
class PaymentAuthorizationPolicy {
 <<DISSENY: no acreditat al PHP>>
 +authorize(actor,operation,factures) decision
}
class ExternalReceiptReconciler {
 <<DISSENY: no acreditat al PHP>>
 +identify(providerRef,dsOrder,bankEvidence) receipt
}
class PaymentPayloadEquivalenceGuard {
 <<DISSENY: no acreditat al PHP>>
 +validateMoneyAndReuse(payload,existing) decision
}
class PaymentService {
 <<PHP existent: no conté els guards anteriors>>
 +registerPayment(payload) array
}
class PaymentRepository {
 <<PHP existent>>
 +findByIdempotencyKey(db,key,forUpdate) array
}
PaymentIngressGateway --> PaymentAuthorizationPolicy : accés/abast
PaymentIngressGateway --> ExternalReceiptReconciler : fet real
PaymentIngressGateway --> PaymentPayloadEquivalenceGuard : sumes i equivalència
PaymentPayloadEquivalenceGuard ..> PaymentRepository : comparar moviment original [PENDENT]
PaymentIngressGateway --> PaymentService : només payload coherent
```
## 4. Diagrama de seqüència — registre genèric del moviment

```mermaid
sequenceDiagram
autonumber
actor Canal as Canal autoritzat
participant PS as PaymentService
participant PV as PaymentPayloadValidator
participant TR as TransactionRunner
participant PR as PaymentRepository
participant Calc as PaymentStatusCalculator
participant DB as BD SIF
Canal->>PS: registerPayment(payload amb allocations)
PS->>PV: validate(payload)
alt Contracte invàlid
 PV--xPS: Error de validació
 PS--xCanal: Error
else Contracte vàlid
 PV-->>PS: payload
 PS->>TR: run(callback)
 TR->>DB: BEGIN
 PS->>PR: findByIdempotencyKey(key,true)
 PR->>DB: SELECT payment_transaction FOR UPDATE
 alt Pagament existent
  PR-->>PS: uuid_payment existent
  Note over PS,PR: Reintent sense nova assignació
 else Pagament nou
  PS->>PR: createPayment(payload)
  PR->>DB: INSERT payment_transaction
  loop Cada assignació
   PR->>DB: INSERT payment_allocation
   PR->>DB: SELECT factura.TOTAL FOR UPDATE
   PR->>DB: SUM assignacions CHARGE/COMPENSATION i REFUND
   PR->>Calc: calculate(total,charges,refunds)
   Calc-->>PR: Estat calculat
   PR->>DB: UPDATE factura.ESTAT_COBRAMENT
  end
  PR-->>PS: uuid_payment nou
 end
 PS-->>TR: Resultat del callback (nou o reutilitzat)
 TR->>DB: COMMIT
 TR-->>PS: Resultat confirmat
 PS-->>Canal: ok, uuid_payment, idempotency_reused
end
```

## 5. Diagrama de seqüència — variant manual d'una factura existent

```mermaid
sequenceDiagram
autonumber
actor Op as Operador
participant Ad as Adaptador intranet [pendent]
participant MS as ManualPaymentService
participant IR as ManualPaymentInvoiceRepository
participant B as ManualPaymentPayloadBuilder
participant PS as PaymentService
participant DB as BD SIF
Op->>Ad: Identificar factura i validar cobrament
Note over Op,Ad: Accés / conciliació bancària no acreditats aquí
Ad->>MS: registerByUuid(db,uuidFactura,input)
MS->>IR: findByUuid(db,uuidFactura)
IR->>DB: SELECT factura
alt No existeix la factura
 IR-->>MS: null
 MS--xAd: Error de validació
else Factura trobada
 IR-->>MS: factura
 MS->>B: forExistingInvoice(uuidFactura,input)
 B-->>MS: CHARGE, INTRANET, allocations
 MS->>PS: registerPayment(payload)
 PS-->>MS: ok, uuid_payment, idempotency_reused
 MS-->>Ad: resultat + UUID/número factura
 Ad-->>Op: Resultat del cobrament
end
```

En la variant manual, el constructor normalitza l'import positiu, admet `TRANSFERENCIA` o `MANUAL`, requereix la data i genera la clau idempotent per referència o per combinació factura/data/import/banc. **L'evidència que una transferència ha arribat realment no es pot inferir d'aquesta construcció del payload.**

## 5.1. Seqüència d'error, reintent i conflicte de payload — nucli actual versus control objectiu

**Lectura del PHP actual:** `PaymentPayloadValidator::validate()` comprova camps i numericitat, però no exigeix `amount > 0`, imports assignats positius, suma d'assignacions igual al moviment ni una coincidència entre factura, titular i ingrés extern. A main, `PaymentRepository::hashPayload()` persisteix `PAYLOAD_HASH` versionat i `PaymentService::assertSamePayload()` **el compara** en reús de la clau; les comprovacions externes de titularitat/saldo continuen pendents. L'endpoint `public/api/payments/register.php` crida el servei directament; autorització de l'actor i prova bancària no queden acreditades només pel PHP de la ruta.

```mermaid
sequenceDiagram
autonumber
actor C as Canal o operador
participant G as Guard autorització/equivalència [DISSENY]
participant P as PaymentService [PHP]
participant V as PaymentPayloadValidator [PHP]
participant TR as TransactionRunner [PHP]
participant R as PaymentRepository [PHP]
participant DB as BD SIF
C->>G: Sol·licitar CHARGE amb fet extern, import i assignacions
alt No hi ha permís, prova de l'ingrés o suma monetària coherent
 G-->>C: Rebuig, cap escriptura [OBJECTIU]
else Petició validada pel canal objectiu
 G->>P: registerPayment(payload)
 P->>V: validate(payload)
 alt Validador PHP rebutja dades estructurals
  V--xP: Excepció
  P--xC: Error abans de la transacció
 else Dades estructurals acceptades
  V-->>P: payload
  P->>TR: run(callback)
  TR->>DB: BEGIN
  P->>R: findByIdempotencyKey(key,true)
  alt Clau ja existeix
   R-->>P: UUID_PAYMENT anterior
   Note over P,G: PHP main crida assertSamePayload() per V1/V2 i rebutja dades contradictòries amb la mateixa K.
   P->>P: assertSamePayload(payload,existing PAYLOAD_HASH/HASH_VERSION) [PHP main]
   P-->>TR: Reús només si hash de petició coincideix
  else Clau nova
   P->>R: createPayment(payload)
   alt La factura d'assignació no existeix o SQL falla
    R--xP: Excepció
    P--xTR: Propagar
    TR->>DB: ROLLBACK
    TR--xC: Error, sense COMMIT del moviment
   else INSERT i càlcul d'estat finalitzen
    R-->>P: UUID_PAYMENT nou
    P-->>TR: Resultat
   end
  end
  opt El callback acaba sense excepció
   TR->>DB: COMMIT
   TR-->>P: Resultat confirmat
   P-->>C: UUID_PAYMENT nou o reutilitzat
  end
 end
end
Note over P,R: Si SQL llença error de duplicat 23000, PaymentService obre una SEGONA transacció per rellegir la clau. No crea un segon CHARGE.
```

**El guard extern dibuixat no està implementat.** La comparació del PAYLOAD_HASH sobre **la mateixa clau** sí que és PHP main; no demostra identitat bancària entre claus diferents, titularitat del saldo ni disponibilitat per reassignar. El hash de petició no substitueix els controls monetaris pendents.

## 5.2. Acció específica: ingrés nou versus imputació d'un ingrés ja registrat

```plantuml
@startuml
left to right direction
actor "Operador autoritzat" as O
actor "Procés bancari/TPV" as B
rectangle "SIF PrisMa" {
 usecase "UC-02\nRegistrar nou moviment\nextern confirmat" as New
 usecase "Verificar identitat del fet\ni imports assignats" as Check
 usecase "UC-56\nAssignar UUID_PAYMENT\nja existent" as Reallocate
 usecase "UC-104\nResoldre excés no assignat" as Excess
}
O --> New
B --> New
New ..> Check : <<include>> [OBJECTIU]
O --> Reallocate
O --> Excess
note bottom of Reallocate
 No crea un segon payment_transaction.
 La ruta actual registerPayment()
 no implementa aquesta reassignació.
end note
@enduml
```

### Vista Mermaid de registre d'un ingrés extern nou

```mermaid
flowchart LR
  a_0["Operador autoritzat"]
  a_1["Procés bancari/TPV"]
  subgraph SIF_BOUNDARY["SIF PrisMa"]
    u_0(["UC-02<br/>Registrar nou moviment<br/>extern confirmat"])
    u_1(["Verificar identitat del fet<br/>i imports assignats"])
    u_2(["UC-56<br/>Assignar UUID_PAYMENT<br/>ja existent"])
    u_3(["UC-104<br/>Resoldre excés no assignat"])
  end
  a_0 --> u_0
  a_1 --> u_0
  u_0 -.->|include| u_1
  a_0 --> u_2
  a_0 --> u_3
```

| ID prova pendent | Petició | Resultat exigible |
| --- | --- | --- |
| CP-02-01 | Mateixa clau i mateix ingrés/assignacions | Mateix `UUID_PAYMENT`, cap segon `CHARGE`. |
| CP-02-02 | Mateixa clau amb import, factura o tipus canviats | Conflicte abans de retornar èxit; no fingir equivalència pel reús. |
| CP-02-03 | Import del moviment diferent de suma assignacions | Rebuig abans de l'INSERT; sense import perdut o inventat. |
| CP-02-04 | Dues claus per la mateixa referència bancària confirmada | Una sola entrada externa; conciliació del fet abans de `registerPayment`. |
| CP-02-05 | Factura inexistent durant l'INSERT de l'assignació | `ROLLBACK` del moviment nou i de les assignacions; cap èxit prematur. |
| CP-02-06 | Factura prèvia UC-04, transferència posterior | Reutilitzar UUID_FACTURA; un `UUID_PAYMENT` nou i cap segona factura fiscal. |
### 5.3. Acció independent: comprovar si un ingrés manual ja existeix per referència abans d'atribuir-lo a una factura — PHP existent / guard pendent

**Actor/disparador:** gestió introdueix una transferència confirmada amb referència bancària i factura destí; la mateixa referència pot aparèixer de nou perquè es tracta d'un reintent real o perquè una transferència legítima de 200 € cobreix dues factures. **Precondició objectiu:** identificar **una única entrada de banc** i les assignacions originals i pendents, titular, import, factura i inscripcions d'origen; no interpretar dues peticions d'interfície com dos ingressos externs. **Postcondició:** reutilitzar un `UUID_PAYMENT` per **la mateixa operació externa**, sense crear segon `CHARGE`, i comprovar si la factura sol·licitada ja té un tram assignat; si falta repartir l'ingrés existent, tramitar UC-56/105 amb writer segur o bloquejar-ho com a pendent, no retornar un fals pagament de B.

**Límit PHP precís:** `ManualPaymentPayloadBuilder::idempotencyKey()` usa `<method>|REF:<reference>` quan hi ha referència, **sense factura ni import**. A `main`, `PaymentService::createOrReusePayment()` recupera el moviment per clau **i compara el hash de la petició completa** amb `assertSamePayload()`; per tant, una nova assignació a B sota la mateixa K produeix `CONFLICT`, no un reús silenciós. `ManualPaymentService::registerForInvoice()` afegeix a la resposta `uuid_factura` i `num_visible` de la **factura que ha rebut en aquesta petició**. Així, una primera alta A/100 i una segona sol·licitud B/100 amb la mateixa referència i K **s'han de rebutjar a `main` per divergència del payload**; no es crea cap assignació B. Si una petició duplicada és idèntica però el canal vol representar-la com a factura diferent, la resposta del servei s'ha de contrastar amb l'assignació real abans de mostrar-la com a cobrada. A diferència de dos ingressos reals, una transferència multifactura necessita conservar el mateix moviment i comprovar/repartir els trams; el PHP actual `PaymentRepository::createPayment()` només insereix assignacions en crear el moviment, no les afegeix a un moviment existent en el reús.

```plantuml
@startuml
left to right direction
actor "Gestió cobraments" as G
actor "Banc / evidència externa" as B
rectangle "SIF PrisMa — UC-02 / INGRÉS EXISTENT" {
 usecase "Localitzar ingrés real únic\ni assignacions preexistents" as Find
 usecase "Comprovar la factura i import sol·licitats\ncontra trams del moviment" as Compare
 usecase "UC-02 / NEW\nRegistrar CHARGE extern nou" as New
 usecase "UC-56/105\nAssignar saldo d'ingrés existent" as Allocate
}
G --> Find
B --> Find
Find ..> Compare : <<include>>
G --> New
G --> Allocate
note right of Allocate
 Reusar UUID_PAYMENT no significa
 que factura B ja tingui assignació.
end note
@enduml
```

### Vista Mermaid de consulta d'un ingrés extern preexistent

```mermaid
flowchart LR
  a_0["Gestió cobraments"]
  a_1["Banc / evidència externa"]
  subgraph SIF_BOUNDARY["SIF PrisMa — UC-02 / INGRÉS EXISTENT"]
    u_0(["Localitzar ingrés real únic<br/>i assignacions preexistents"])
    u_1(["Comprovar la factura i import sol·licitats<br/>contra trams del moviment"])
    u_2(["UC-02 / NEW<br/>Registrar CHARGE extern nou"])
    u_3(["UC-56/105<br/>Assignar saldo d'ingrés existent"])
  end
  a_0 --> u_0
  a_1 --> u_0
  u_0 -.->|include| u_1
  a_0 --> u_2
  a_0 --> u_3
```

```mermaid
sequenceDiagram
autonumber
actor G as Gestió
participant M as ManualPaymentService [PHP]
participant B as ManualPaymentPayloadBuilder [PHP]
participant P as PaymentService [PHP main]
participant H as PayloadIdempotencyValidatorInterface [PHP main]
participant DB as payment_transaction + payment_allocation [SQL]
participant A as Assignació segura P existent UC-56/105 [DISSENY]
G->>M: registerByUuid(FACTURA_A,100,reference=TRF-1)
M->>B: forExistingInvoice(FACTURA_A,input)
B-->>M: K=TRANSFERENCIA|REF:TRF-1, assignació A/100
M->>P: registerPayment(payload A)
P->>DB: BEGIN, INSERT CHARGE P_A + allocation A/100, hash V2, COMMIT
P-->>M: UUID_PAYMENT_A,idempotency_reused=false
M-->>G: P_A, uuid_factura=FACTURA_A
G->>M: registerByUuid(FACTURA_B,100,reference=TRF-1)
M->>B: forExistingInvoice(FACTURA_B,input)
B-->>M: Mateixa K, assignació sol·licitada B/100
M->>P: registerPayment(payload B)
P->>DB: BEGIN, findByIdempotencyKey(K,true)
DB-->>P: P_A i hash V2 del payload original A/100
P->>H: assertMatches(payload B/100,hash original A/100)
H--xP: CONFLICT per assignació diferent [PHP main]
P-->>G: Error, sense assignació a B ni nou CHARGE
G->>A: Revisar prova bancària i saldo P_A abans d'una eventual UC-56/105
alt Ingrés bancari de 200 però P_A nominalment 100
 A-->>G: Conciliar diferència banc/SIF abans d'assignar B
else Ingrés real de 100 completament assignat a A
 A-->>G: Saldo 0, denegar B, no duplicar ingrés
end
Note over P,H: El hash V2 compara mateixa clau i contingut, no deduplica dos moviments creats amb claus diferents.
```

### 5.4. Acció independent: rebutjar la reutilització d'una clau econòmica amb contingut diferent — PHP main per payload de K; contrast del fet extern i saldo pendent

**Actor/disparador:** un callback o una alta manual repeteix `idempotency_key=K` amb `IMPORT`, `TIPUS_MOVIMENT`, `UUID_FACTURA` d'assignació, referència bancària o titular nous. **Resultat objectiu:** comparar **sota bloqueig** el moviment existent, referència externa i **totes** les assignacions persistides, més origen i import extern; retornar el mateix UUID només si és **la mateixa petició semàntica** i l'estat extern coincideix. Amb contingut canviat, `CONFLICT` i incidència en lloc d'un fals `idempotency_reused=true`. A main, `PaymentService::assertSamePayload()` **sí que compara** `PAYLOAD_HASH` segons versió 1/2 sota transacció abans de retornar el UUID existent. Aquest control cobreix la petició completa per K, però **no compara** necessàriament dues referències bancàries externes relacionades sota claus diferents, ni comprova disponibilitat d'una assignació addicional sobre P. Per a dues factures dins d'una transferència única, comparar els trams reals, no exigir que cada crida genèrica de `registerPayment()` modifiqui el mateix UUID, perquè no implementa aquesta extensió.

```plantuml
@startuml
left to right direction
actor "Canal de cobrament" as C
actor "Responsable conciliació" as R
rectangle "SIF PrisMa — UC-02 / REÚS ECONÒMIC" {
 usecase "Registrar o recuperar moviment per K" as Reuse
 usecase "Contrastar referència externa, import,\ntipus i assignacions existents" as Compare
 usecase "UC-56/105\nRepartir import encara no assignat" as Split
}
C --> Reuse
Reuse ..> Compare : <<include>> [hash de K PHP main; prova bancària/atribució pendent]
R --> Split
@enduml
```

### Vista Mermaid de comprovació de reús econòmic per clau

```mermaid
flowchart LR
  a_0["Canal de cobrament"]
  a_1["Responsable conciliació"]
  subgraph SIF_BOUNDARY["SIF PrisMa — UC-02 / REÚS ECONÒMIC"]
    u_0(["Registrar o recuperar moviment per K"])
    u_1(["Contrastar referència externa, import,<br/>tipus i assignacions existents"])
    u_2(["UC-56/105<br/>Repartir import encara no assignat"])
  end
  a_0 --> u_0
  u_0 -.->|include| u_1
  a_1 --> u_2
```

```mermaid
sequenceDiagram
autonumber
actor C as Canal
participant G as PaymentExternalEvidenceGuard [DISSENY]
participant H as PayloadIdempotencyValidatorInterface [PHP main]
participant P as PaymentService [PHP]
participant R as PaymentRepository [PHP]
participant DB as payment_transaction/allocation [SQL]
C->>G: Reintentar K amb factura B/80, referència externa E
G->>R: Verificar fet bancari, titular i disponibilitat entre claus [PENDENT]
G->>P: registerPayment(payload) [PHP main]
P->>R: findByIdempotencyKey(K,true) [PHP main]
P->>H: assertMatches(payload,stored PAYLOAD_HASH) segons versió [PHP main]
alt K existeix amb A/40 o fet extern diferent
 R-->>G: Payload i assignacions preexistents incompatibles
 G-->>C: CONFLICT, no retornar UUID antic com a ingrés de B
else K existeix amb mateix fet extern i totes les assignacions equivalents
 R-->>G: Coincidència d'import, tipus, factura, referència i titular
 G-->>C: Reús verificat del moviment existent
else No existeix K i el banc acredita un nou ingrés diferent
 G->>P: registerPayment(payload validat)
 P->>DB: BEGIN, INSERT CHARGE i assignacions, COMMIT
 P-->>C: UUID_PAYMENT nou
end
Note over G,R: El lector i guard previ han de compartir una política de bloqueig amb la inserció efectiva, només consultar abans i deixar córrer una altra petició no evita la cursa.
```

| Prova pendent | Escenari | Resultat objectiu i comportament PHP a reproduir |
| --- | --- | --- |
| CP-02-08 | Alta A/100 amb clau K i transferència TRF-1; segona petició per B/100 amb la mateixa K | **PHP main: CONFLICT per payload/assignació diferent**, no atribueix B. Si la segona petició utilitza **una K diferent** amb la mateixa transferència, cal guard d'identitat bancària extern pendent. |
| CP-02-09 | Entrada externa/SIF real de 200 amb una sola assignació de 100 a A i pendent 100 de B | Mateix `UUID_PAYMENT` i dues assignacions només després del writer UC-56/105 i conciliació del sobrant; mai un segon `CHARGE` extern. Si el moviment SIF inicial era de 100, primer resoldre la diferència respecte al banc. |
| CP-02-10 | Mateixa clau K de pagament però import/tipus/assignació nous | **PHP main ja rebutja per assertSamePayload()**, sense segona assignació. Provar contracte V1/V2 i no confondre'l amb el guard entre claus pendent. |
| CP-02-11 | Dos workers fan reús/alta de K amb payloads diferents alhora | **PHP main** usa transacció i relectura després de duplicat SQL amb assertSamePayload(); prova de concurrència i prova d'unicitat del fet bancari entre claus continuen pendents. |

### 5.5. Acció independent: validar els imports totals i els trams abans de crear un CHARGE nou — PHP existent insuficient / guard PENDENT

**Actor/disparador:** el canal ha verificat un ingrés extern nou de 100 € i vol crear-ne un `payment_transaction` amb assignacions a una o diverses factures. **Precondició objectiu:** import extern estrictament positiu i expressat en cèntims; cada tram estrictament positiu, factura existent i titular compatible; `SUM(trams) <= IMPORT` del mateix moviment, amb política explícita si hi ha import **no assignat**; comprovar que `IDEMPOTENCY_KEY` representa el mateix ingrés bancari i no un altre. **Postcondició:** un sol `UUID_PAYMENT` per fet extern i assignacions coherents, o rebuig abans del registre. La suma exactament igual a l'import **no és sempre obligatòria** quan existeix un import pendent de distribuir: el que és inacceptable és consumir més del que s'ha ingressat o tractar el sobrant com a saldo lliure sense verificar-ne l'origen i els compromisos. Per a `REFUND` i `COMPENSATION`, les regles d'elegibilitat de la destinació i de disponibilitat són específiques.

**PHP contrastat:** `PaymentPayloadValidator` només requereix `is_numeric(payload.amount)` i `is_numeric(allocation.amount)`, com a mínim una assignació i valors enumerats de moviment/mètode; **no comprova** positivitat ni suma de trams. `PaymentService` l'executa abans del seu `TransactionRunner` i `PaymentRepository::createPayment()` insereix moviment i cada tram; `refreshInvoicePaymentStatus()` suma `IMPORT_ASSIGNAT` de cada factura sense limitar-ho a `payment_transaction.IMPORT` del mateix ingrés. Un `CHARGE` nominal de 100 amb F1/80+F2/80 pot persistir amb **160 € atribuïts**; una assignació negativa altera el saldo de la factura sense esdevenir un moviment `REFUND` real ni una reversió traçada. El test `RegisterPaymentTest` comprova únicament l'alta coherent 120/120 i un reintent equivalent, **no** imports negatius, zero o repartiment inconsistent.

```plantuml
@startuml
left to right direction
actor "Canal de cobrament" as C
actor "Banc / evidència externa" as B
rectangle "SIF PrisMa — UC-02 / VALIDAR CHARGE I TRAMS" {
 usecase "Validar nou ingrés i trams proposats" as Validate
 usecase "Comprovar identitat bancària,\nimport real i titular" as Bank
 usecase "Comprovar cada tram positiu\ni suma no superior a import" as Sum
 usecase "Registrar CHARGE i assignacions\nnomés amb invariant satisfet" as Record
 usecase "UC-56\nConciliar import no assignat" as Remainder
}
C --> Validate
B --> Bank
Validate ..> Bank : <<include>> [guard extern pendent]
Validate ..> Sum : <<include>> [guard pendent]
C --> Record
C --> Remainder
@enduml
```

```mermaid
sequenceDiagram
autonumber
actor C as Canal
participant V as PaymentPayloadValidator [PHP]
participant G as MoneyAllocationInvariantGuard [DISSENY]
participant S as PaymentService [PHP]
participant R as PaymentRepository [PHP]
participant DB as payment_transaction + payment_allocation [SQL]
C->>V: validate(CHARGE 100, F1/80 + F2/80)
V-->>C: Payload estructuralment validat [PHP: sense prova de suma]
C->>G: validateNewExternalReceipt(payload,evidence) [PENDENT]
alt F1/80 + F2/80 = 160 > ingrés 100
 G-->>C: CONFLICT, cap registre de CHARGE ni imputació
else Hi ha tram zero/negatiu o titular incompatible
 G-->>C: CONFLICT abans de cap escriptura
else Trams F1/80 + F2/20, ingrés 100 acreditat
 G-->>C: Invariant compatible [pendent d'equivalència de K]
 C->>S: registerPayment(payload validat)
 S->>R: findByIdempotencyKey(K,true) sota BEGIN
 R-->>S: K inexistent
 S->>R: createPayment(payload)
 R->>DB: INSERT CHARGE 100, F1/80 i F2/20
 R->>DB: Recalcular estats de F1 i F2, COMMIT del servei
 S-->>C: UUID_PAYMENT únic, trams sumen 100
end
Note over G,S: Guard previ i verificació transaccional a l'alta definitiva són DISSENY. La validació externa aïllada no protegeix contra canvis concurrents.
```

| Prova pendent | Escenari | Resultat objectiu |
| --- | --- | --- |
| CP-02-12 | `amount=100`, dues allocations F1/80 i F2/80 | Rebutjar sobreatribució abans de persistir res; validator PHP actual deixa passar estructura/imports numèrics. |
| CP-02-13 | `amount=100` i allocation F1/-20 o F1/0 | Rebutjar tram no positiu abans de qualsevol canvi d'`ESTAT_COBRAMENT`. |
| CP-02-14 | `amount=100`, F1/80 i 20 sense atribució | Admetre només amb política de saldo pendent i ingrés real acreditat; no mostrar F2 pagada fins a UC-56. |
| CP-02-15 | `amount=100`, F1/80 + F2/20 coherents; intent duplicat K amb repartiment F1/100 | Primer alta coherent; segon `CONFLICT` semàntic, no resposta que presenti distribució nova com a aplicada. |
| CP-02-16 | Pagament nominal 100, trams 160; estats individuals de les factures semblen coherents | Detectar invariant **per UUID_PAYMENT** independentment de `ESTAT_COBRAMENT` per factura, obrir diagnosi sense alterar factura fiscal. |

## 6. Traçabilitat

[Catàleg UC-02](../04-estat-final/33-casos-us-sif.md) · [Fitxa base UC-02](../06-fitxes-funcionals/uc-002.md) · [PaymentService](../../sif/src/Service/PaymentService.php) · [PaymentPayloadValidator](../../sif/src/Service/PaymentPayloadValidator.php) · [PaymentRepository](../../sif/src/Repository/PaymentRepository.php) · [PaymentStatusCalculator](../../sif/src/Domain/PaymentStatusCalculator.php) · [ManualPaymentService](../../sif/src/Service/ManualPaymentService.php) · [ManualPaymentPayloadBuilder](../../sif/src/Service/ManualPaymentPayloadBuilder.php) · [RegisterPaymentTest](../../sif/tests/Integration/RegisterPaymentTest.php).

**No acreditat:** execució de proves, conciliació bancaria real, permisos de la interfície, auditories transversals completes ni posada en producció.
