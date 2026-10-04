# UC-29a · Aplicar saldo com a compensació — fitxa i UML integrats

**Objectiu:** consumir un saldo existent per cobrir, totalment o parcialment, l'import pendent d'una factura SIF, amb un moviment `COMPENSATION`. No es crea una factura nova ni es fa cap transferència bancària. Relacions: UC-29 (saldo previ), UC-02 (comptabilització del moviment), UC-06 (decisió econòmica), UC-05 (rectificació si el servei facturat canvia).

**Estat reconciliat 2026-10-04:** servei, repositoris i proves ampliats. A més del hash de payload, `target_enrollment_id` permet registrar `COMPENSATION_ALLOCATION` amb `UUID_CREDIT`, payment, factura i línia en la mateixa transacció. Només es permet atribuir valor monetari a la inscripció si el crèdit està completament respaldat per `CREDIT_CREATE`. Titularitat creuada, identitat d'ordre per dues aplicacions iguals i UI continuen pendents.

## 1. Fitxa de cas d'ús

| Camp | Especificació |
| --- | --- |
| Actor principal | Operador autoritzat. |
| Precondicions | Saldo `credit_balance` existent i `ACTIVE`, factura identificada per UUID o número visible, import pendent positiu, import de compensació positiu. |
| Entrades | `uuid_credit`, factura, `amount`/`import`, `movement_date`/`data_moviment`/`data`; opcionals: `idempotency_key`, `target_enrollment_id`, `notes`, `allocation_type`, `correlation_id`, `uuid_operation`. |
| Comprovacions existents | Bloqueig del saldo i de la factura; relectura del moviment per clau idempotent; saldo actiu; import no superior al saldo disponible **ni a l'import pendent de factura**. |
| Efecte econòmic | `payment_transaction` amb `TIPUS_MOVIMENT=COMPENSATION`, mètode `COMPENSACIO`, referència al saldo, una assignació `CREDIT_COMPENSATION`, consum de `credit_balance.IMPORT_DISPONIBLE` i recàlcul de l'estat de cobrament de factura. |
| Resultat | `uuid_payment`, `uuid_credit`, `uuid_factura`, `num_visible`, `import_disponible`, `credit_estat` i `idempotency_reused`. |

### 1.1. Flux principal implementat

1. `CreditBalanceService::applyCreditByUuid()` o `applyCreditByNumVisible()` obre una transacció de `TransactionRunner`.
2. `lockedCompensationContext()` bloqueja el saldo i la factura (`FOR UPDATE`) i construeix el moviment amb `CreditBalancePayloadBuilder::forCompensation()`.
3. El constructor usa `idempotency_key` explícita si el caller l'aporta; en cas contrari deriva `COMPENSACIO|UUID_CREDIT:<uuid>|FACT:<número>|IMPORT:<quantitat>`. Després força `movement_type=COMPENSATION`, `method=COMPENSACIO`, `source_channel=INTRANET` i `provider_ref=uuid_credit`; `PaymentPayloadValidator` valida el payload.
4. Si existeix ja un `payment_transaction` amb la mateixa clau, es retorna el resultat reutilitzat **sense consumir de nou el saldo**.
5. Si és un moviment nou, `assertCreditCanBeApplied()` verifica `ESTAT=ACTIVE`, import disponible i pendent a la factura. Aquest últim es calcula com a màxim entre zero i total menys càrrecs/compensacions més devolucions.
6. `PaymentRepository::createPayment()` inscriu el moviment i l'assignació i actualitza `factura.ESTAT_COBRAMENT`.
7. `consumeCredit()` descompta l'import del saldo. Si queda `0.00`, marca `USED`; si resta import, `ACTIVE`. Es confirma **la mateixa transacció** per al moviment i el consum.

### 1.2. Alternatives, errors i límits concrets

| Cas | Comportament |
| --- | --- |
| S1. Aplicació parcial | Es conserva el saldo restant i l'estat `ACTIVE`; la factura pot passar a `PARTIAL`. |
| S2. Consum de tot el saldo | `IMPORT_DISPONIBLE=0.00` i estat `USED`; no implica que tota la factura estigui pagada si l'import pendent era superior. |
| S3. Cobertura total de factura | `PaymentStatusCalculator` recalcula `PAID` quan el total net cobreix exactament el total. |
| S4. Reintent mateix saldo/factura/import | La mateixa clau reutilitza el pagament **si el payload coincideix**; payload diferent amb la mateixa K retorna conflicte i no torna a reduir el saldo. |
| E1. Saldo/factura desconegut o saldo no actiu | Rebuig. |
| E2. Import superior al saldo disponible o al pendent de factura | Rebuig abans de crear moviment. |
| E3. Error SQL duplicat concurrent | El servei obre una nova transacció i recupera el moviment per clau; no s'ha de consumir el saldo dues vegades. |
| **P1. Titularitat** | No es veu en `assertCreditCanBeApplied()` cap comprovació d'identitat entre `HOLDER_ID`/`HOLDER_NIF_CIF` i el receptor/pagador de la factura. És una validació funcional i de permisos pendent. |
| **P2. Idempotència de quantitats coincidents** | La K derivada continua sent saldo+factura+import, però el builder ja accepta `idempotency_key` explícita. Això permet dues aplicacions legítimes de la mateixa quantitat amb K diferents, mentre el reintent d'una ordre concreta reutilitza només la seva K. L'orquestrador encara ha de construir aquesta identitat estable. |
| P3. Tipus d'assignació personalitzable | El builder permet `allocation_type` opcional; cal acotar al contracte funcional i no confiar en l'entrada del client sense autorització. |
| P4. Origen fiscal del saldo | El servei no emet una rectificativa ni verifica automàticament que l'origen del crèdit estigui fiscalment resolt; correspon al procés que el va crear. |

**Proves existents, no executades aquí:** `CreditBalanceServiceTest::testAppliesCreditAsCompensationAndConsumesAvailableBalanceOnce`, `testAppliesFullCreditByVisibleInvoiceNumberAndMarksCreditUsed`, `testRejectsApplyingMoreThanAvailableCredit`, `testRejectsApplyingMoreThanInvoiceOutstandingAmount`.

### 1.3. Revisió: destí d'inscripció — IMPLEMENTAT PARCIALMENT

`CreditBalanceService` pot conservar una fila quantitativa per una inscripció beneficiària explícita mitjançant `target_enrollment_id`: valida la `factura_linia`, registra `COMPENSATION_ALLOCATION` i consumeix saldo en la mateixa transacció. UC-29a ha de registrar `CREDIT → INSCRIPCIÓ` per cada import aplicat, amb `UUID_CREDIT`, `UUID_PAYMENT` i l'assignació a factura relacionats. La suma de les atribucions no pot superar el saldo consumit; cap consum de saldo no és un ingrés bancari nou. Cal validar titularitat del crèdit i permís d'aplicar-lo a cada participant.

[Model i reconciliació proposats](00-revisio-moviments-inscripcions.md).

### 1.4. Autorització, dues aplicacions legítimes i destinació real — contrast amb el xat original

**A-TITULAR — pagar amb saldo no és fer un descompte:** el cas d'ús consumeix fons/valor ja reconeguts en un `credit_balance` i registra un moviment `COMPENSATION`; no redueix automàticament el preu fiscal de la factura ni és un `CHARGE` bancari nou. Abans d'aplicar-lo a una factura d'empresa, grup, USOC o d'una altra persona, validar titular econòmic, consentiment i autorització de l'ús del saldo per a **cada inscripció beneficiària**; el servei actual comprova saldo i pendent de factura, però no acredita aquesta comprovació entre titulars.

**A-DOBLE — dos usos de mateix import sobre la mateixa factura:** la clau derivada continua fusionant per defecte saldo+factura+import, però ara `idempotency_key` explícita permet modelar ordres A i B diferents. El servei conserva comparació de payload, locks i revalidació del saldo; per tant, dues K diferents poden consumir 20 € + 20 € si hi ha saldo/deute suficients, mentre el reintent d'A reutilitza només A. La responsabilitat pendent és que l'orquestrador generi/guardi una identitat d'ordre estable i autoritzada.

**A-CANVI — destí d'una compensació després de baixa o canvi:** si es crea un saldo per baixa i després s'aplica a una altra inscripció, conservar la traça `INSCRIPCIÓ_ORIGEN → CREDIT → INSCRIPCIÓ_DESTÍ` i els UUID_CREDIT/UUID_PAYMENT. Si el destinatari canvia de curs o es reactiva la baixa, no restaurar saldo consumit amb un simple UPDATE: cal classificar nous traspassos i efectes fiscals. Una compensació a factura de grup amb N participants requereix repartiment real, no atribució total a cadascú.

**A-ANTIC — revisió de saldo antic:** no es caduca automàticament un saldo perquè han passat cinc anys. Quan s'hagi determinat revisió manual `review_after`, l'operador ha de revisar titular, origen, disponible i possibles moviments previs abans d'autoritzar l'ús; `CreditBalanceService` per si sol no acredita que la pantalla mostri aquesta revisió.

### 1.5. Proves d'acceptació addicionals (no executades)

| ID | Escenari | Resultat exigible |
| --- | --- | --- |
| CO-01 | Aplicar saldo de titular empresa a factura alumne sense autorització | Denegar o requerir decisió explícita, sense consum automàtic. |
| CO-02 | Aplicar 20 € i posteriorment uns altres 20 € legítims a mateixa factura | Dos moviments diferenciats si hi ha saldo/pendent, no fusió per clau coincident. |
| CO-03 | Reintent del primer moviment de 20 € | Retornar el mateix UUID_PAYMENT sense segon consum del crèdit. |
| CO-04 | Saldo de baixa aplicat a inscripció nova | Traça origen→credit→destí i un sol consum monetari. |
| CO-05 | Saldo amb revisió manual pendent després de cinc anys | Revisió de titular/origen abans d'ús; cap expiració automàtica. |
| CO-06 | Factura de grup amb diverses inscripcions | Repartiment quantitatiu i autorització per cada participant; no atribuir el mateix saldo sencer N vegades. |
## 2. Diagrama UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Operador autoritzat" as O
rectangle "SIF PrisMa" {
 usecase "UC-29a\nAplicar compensació" as Apply
 usecase "Consultar saldo disponible\ni factura pendent" as Check
 usecase "UC-02\nRegistrar moviment econòmic" as Pay
 usecase "Consumir saldo atòmicament" as Consume
 usecase "UC-29\nCrear saldo" as Create
}
O --> Apply
O --> Create
Apply ..> Check : <<include>>
Apply ..> Pay : <<include>>
Apply ..> Consume : <<include>>
note bottom of Create
  Operació prèvia separada;
  no es torna a crear saldo aquí
end note
@enduml
```

### Vista de casos d’ús per a GitHub (Mermaid)

```mermaid
flowchart LR
  actor_0["Operador autoritzat"]
  subgraph SIF_BOX["SIF PrisMa"]
    uc_0(["UC-29a<br/>Aplicar compensació"])
    uc_1(["Consultar saldo disponible<br/>i factura pendent"])
    uc_2(["UC-02<br/>Registrar moviment econòmic"])
    uc_3(["Consumir saldo atòmicament"])
    uc_4(["UC-29<br/>Crear saldo"])
  end
  actor_0 --> uc_0
  actor_0 --> uc_4
  uc_0 -.->|include| uc_1
  uc_0 -.->|include| uc_2
  uc_0 -.->|include| uc_3
```

## 3. Diagrama UML de classes — compensació

```mermaid
classDiagram
direction LR
class CreditBalanceService {
 +applyCreditByUuid(uuidCredit,uuidFactura,input) array
 +applyCreditByNumVisible(uuidCredit,numVisible,input) array
 -lockedCompensationContext(db,uuidCredit,type,selector,input) array
 -assertCreditCanBeApplied(db,credit,invoice,amount) void
 -consumeCredit(db,credit,amount) array
}
class CreditBalancePayloadBuilder {
 +forCompensation(uuidCredit,uuidFactura,input,invoice) array
}
class CreditBalanceRepository {
 +findByUuid(db,uuidCredit,forUpdate) array
 +invoiceOutstandingAmount(db,uuidFactura) string
 +updateAvailableAmount(db,uuidCredit,available,status) void
}
class ManualPaymentInvoiceRepository {
 +findByUuid(db,uuidFactura,forUpdate) array
 +findByNumVisible(db,numVisible,forUpdate) array
}
class PaymentPayloadValidator {
 +validate(payload) array
}
class PaymentRepository {
 +findByIdempotencyKey(db,key,forUpdate) array
 +createPayment(db,payload) array
}
class TransactionRunner {
 +run(callback) mixed
}
CreditBalanceService --> TransactionRunner : transacció
CreditBalanceService --> CreditBalanceRepository : bloqueja/consumeix
CreditBalanceService --> ManualPaymentInvoiceRepository : bloqueja factura
CreditBalanceService --> CreditBalancePayloadBuilder : moviment
CreditBalanceService --> PaymentPayloadValidator : valida
CreditBalanceService --> PaymentRepository : inserció i idempotència
```

## 4. Diagrama de seqüència — aplicació del saldo

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant UI as Adaptador intranet [pendent]
participant S as CreditBalanceService
participant TR as TransactionRunner
participant CR as CreditBalanceRepository
participant IR as ManualPaymentInvoiceRepository
participant B as CreditBalancePayloadBuilder
participant V as PaymentPayloadValidator
participant PR as PaymentRepository
participant DB as BD SIF
O->>UI: Aplicar saldo (UUID saldo, factura, import, data)
Note over UI,S: Validar titularitat i permisos: pendent d'integració
UI->>S: applyCreditByUuid(uuidCredit,uuidFactura,input)
S->>TR: run(callback)
TR->>DB: BEGIN
S->>CR: findByUuid(uuidCredit,true)
CR->>DB: SELECT credit_balance FOR UPDATE
S->>IR: findByUuid(uuidFactura,true)
IR->>DB: SELECT factura FOR UPDATE
alt No existeix saldo/factura
 S--xUI: Error i rollback
else Context existent
 S->>B: forCompensation(saldo,factura,input)
 B-->>S: COMPENSATION i allocations
 S->>V: validate(payload)
 S->>PR: findByIdempotencyKey(key,true)
 alt Compensació existent
  PR-->>S: uuid_payment preexistent
  Note over S,DB: No torna a consumir saldo
 else Compensació nova
  S->>CR: invoiceOutstandingAmount(uuidFactura)
  CR->>DB: SELECT total i SUM moviments
  Note over S,DB: Validar saldo ACTIVE i import <= disponible i pendent
  alt Import excessiu o saldo no actiu
   S--xUI: Error i rollback
  else Import admès
   S->>PR: createPayment(payload)
   PR->>DB: INSERT transaction / allocation i UPDATE estat factura
   S->>CR: updateAvailableAmount(uuidCredit,restant,ACTIVE/USED)
   CR->>DB: UPDATE credit_balance
  end
 end
 TR->>DB: COMMIT
 S-->>UI: UUID pagament, saldo disponible i estat
 UI-->>O: Compensació registrada
end
```

### 4.1. Acció específica: reintent equivalent vs segona compensació real del mateix import

**Contracte reconciliat:** el builder accepta K explícita i conserva la K derivada com a fallback:

- sense K explícita → `UUID_CREDIT + factura + import`;
- K A + payload A repetit → mateix `UUID_PAYMENT_A`, cap segon consum;
- K A + payload material diferent → 409/CONFLICT;
- K B diferent + mateix saldo/factura/import → nova compensació legítima si saldo i deute continuen sent suficients;
- cada K genera el seu `COMPENSATION_ALLOCATION` idempotent.

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant S as CreditBalanceService
participant B as CreditBalancePayloadBuilder
participant PR as PaymentRepository
participant H as PayloadIdempotencyValidator
participant DB as BD SIF
O->>S: Aplicar 20 a saldo C/factura F, data D1
S->>B: forCompensation(C,F,20,D1)
B-->>S: K = explicit(K_A) o fallback C + F + 20
S->>PR: findByIdempotencyKey(K,true)
PR-->>S: no existeix
S->>PR: createPayment(payload A)
PR->>DB: guarda PAYLOAD_HASH_VERSION=2
S->>DB: consumeix saldo
S-->>O: UUID_PAYMENT_A
O->>S: Reintent exactament igual
S->>PR: findByIdempotencyKey(K,true)
PR-->>S: A + hash
S->>H: assertMatches(payload A,hash)
H-->>S: OK
S-->>O: UUID_PAYMENT_A, reused=true
O->>S: Mateix C/F/20 però data D2 o notes diferents
S->>PR: findByIdempotencyKey(K,true)
PR-->>S: A + hash
S->>H: assertMatches(payload B,hash)
H--xO: 409 CONFLICT
Note over S,DB: No hi ha segon consum d'A. Una ordre B legítima usa una K explícita diferent.
```

### 4.2. Acció d'integració pendent: generar la K d'ordre des del canal autoritzat

El servei ja accepta un identificador estable d'operació via `idempotency_key`; el pendent és que el canal autoritzat el generi i persisteixi a partir d'una ordre de negoci real, després de validar actor, titular, factura i inscripció destí. Cada aplicació continua bloquejant saldo/factura i comprovant payload, deute i disponible.

```plantuml
@startuml
left to right direction
actor "Operador autoritzat" as O
actor "Titular del saldo / aprovador" as H
rectangle "SIF PrisMa — compensació (OBJECTIU)" {
 usecase "UC-29a / COMANDA\nAplicar un import aprovat de saldo" as Apply
 usecase "Comprovar titular, factura i destí" as Authorize
 usecase "Comparar identitat d'operació\ni payload en reintent" as Idempotency
 usecase "Consumir saldo i crear COMPENSATION\nen transacció" as Commit
}
O --> Apply
H --> Authorize
Apply ..> Authorize : <<include>>
Apply ..> Idempotency : <<include>>
Apply ..> Commit : <<include>> [quan és nova i vàlida]
@enduml
```

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant UI as Canal autoritzat [PENDENT]
participant G as Guard titularitat i idempotència [PENDENT]
participant S as CreditBalanceService [K explícita implementada]
participant DB as BD SIF
O->>UI: Confirmar ordre B amb ID propi, saldo C, factura F, import 20
UI->>G: Verificar actor, titular, participants i identitat immutable d'ordre B
alt Mateix ID d'ordre amb payload contradictori
 G-->>UI: CONFLICT sense crear ni consumir saldo
else Ordre exactament repetida
 G-->>UI: UUID_PAYMENT anterior, cap segon consum
else Ordre B nova i autoritzada
 G-->>UI: Dades normalitzades i clau única de B
 UI->>S: applyCredit(C,F,20,idempotency_key=B)
 S->>DB: BEGIN + bloqueig saldo i factura
 alt Sense saldo suficient o factura sense pendent
  DB-->>S: Error de validació, ROLLBACK
  S-->>UI: Rebuig sense COMPENSATION
 else Fons disponibles i destí permès
  S->>DB: Inserir COMPENSATION B i consumir 20 del mateix saldo
  S->>DB: COMMIT
  S-->>UI: UUID_PAYMENT_B diferent del d'A
 end
end
UI-->>O: Reús, conflicte o nova aplicació acreditada
Note over UI,S: La K explícita ja és suportada; guard de titularitat/autorització i generació de la K des de negoci continuen pendents.
```

| ID de prova pendent | Escenari | Resultat requerit |
| --- | --- | --- |
| CO-07 | Ordres A i B diferents a C/F de 20 cadascuna, amb K explícites diferents | **Test afegit:** dos UUID_PAYMENT i consum total 40; CI pendent. |
| CO-08 | Reintent d'ordre A amb mateixa K i payload | **Test afegit dins CO-07:** reús de UUID_PAYMENT_A, sense nou consum. |
| CO-09 | Mateix ID d'ordre A però canvi de factura o data/origen material | Conflicte explícit abans de cap moviment. |
| CO-10 | Segona ordre B després de consumir el saldo per una altra operació | Rebuig per saldo insuficient, no reutilització casual d'A. |
| CO-11 | Reús d'A després que una altra compensació hagi modificat el saldo | Retornar identificador d'A i **distingir saldo actual de saldo posterior històric d'A**. |
## 5. Traçabilitat

[Fitxa original UC-29a](../06-fitxes-funcionals/uc-029a.md) · [UC-29 Crear saldo](uc-029-crear-saldo.md) · [UC-02 Pagament](uc-002-registrar-cobrament-factura.md) · [CreditBalanceService](../../sif/src/Service/CreditBalanceService.php) · [CreditBalancePayloadBuilder](../../sif/src/Service/CreditBalancePayloadBuilder.php) · [CreditBalanceRepository](../../sif/src/Repository/CreditBalanceRepository.php) · [PaymentRepository](../../sif/src/Repository/PaymentRepository.php) · [CreditBalanceServiceTest](../../sif/tests/Integration/CreditBalanceServiceTest.php).

**No acredita:** titularitat validada, repartiment multiinscripció automàtic, generació/autorització de la K d'ordre des de la UI, permisos ni prova final de pantalla. El ledger monetari rebutja crèdits sense `CREDIT_CREATE` de backing.
