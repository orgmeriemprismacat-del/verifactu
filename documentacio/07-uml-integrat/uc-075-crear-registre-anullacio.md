# UC-75 · Crear un registre fiscal d'anul·lació

**Objectiu canònic:** registrar una anul·lació fiscal immutable, encadenada, en cua i vinculada al document original **quan la classificació UC-74 determina que aquesta és la via correcta**. **Estat del catàleg: [DISSENY/BLOQUEJANT], però l'executor PHP de registre d'anul·lació sí existeix.** Pendent: condicions fiscals i circuit d'aprovació/autorització, proves de l'entorn real i coherència completa amb l'AEAT. No confondre amb cancel·lació de matrícula, devolució bancària o factura rectificativa.

## 1. Implementació acreditada i límits

`FiscalRecordService::createCancellationByUuid($uuidFactura,$input)` i `createCancellationByNumVisible()` anomenen el circuit intern `create('ANULACIO',...)`. Dins `TransactionRunner` es recupera la factura amb lock, es rebutja la històrica `NO_VERIFACTU`, es carrega **l'últim** `factura_registres`, es prepara el payload amb `FiscalRecordPayloadBuilder::cancellation()` i es calcula idempotència. Si no existeix registre anterior, es rebutja; si el darrer registre ja és `ANULACIO`, es rebutja una anul·lació nova **excepte si es reutilitza la clau ja trobada**.

`FiscalRecordRepository::create()` bloqueja `fiscal_chain_state`, afegeix **una fila `TIPUS_REGISTRE=ANULACIO`** a `factura_registres` referenciada a la **mateixa `UUID_FACTURA`**, calcula hash i anterior, actualitza cadena i insereix un job `AEAT|<idempotencyKey>` a `fiscal_queue`. També actualitza **`factura.ESTAT_FACTURA=CANCELLED`**; això és un canvi d'estat, **no una edició dels camps fiscals ni una eliminació física de la factura**. El servei retorna identificadors del registre i cua, però no acredita que l'AEAT ja l'hagi acceptat.

`FiscalRecordPayloadBuilder::cancellation()` construeix un payload que conté `aeat_record_type=RegistroAnulacion`, UUID i número de factura, estat original, dades del registre anterior (`ID`, `FISCAL_ORDER`, hash i estat AEAT), **motiu obligatori**, detall/actor/referència opcionals. Si no s'aporta referència, la clau és `ANULACIO|FACT:...|MOTIU:...`. **No s'ha acreditat** en aquest builder el contracte XML definitiu de l'AEAT ni una comprovació jurídica de la causa indicada.

## 2. Fitxa específica i invariants

| Element | Regla |
| --- | --- |
| Actors | Gestió/operador habilitat que demana la correcció, persona responsable del criteri fiscal i worker de la cua AEAT. El mètode PHP rep `input`; **no valida per si sol una aprovació de negoci per rol**. |
| Entrada | `UUID_FACTURA` o `NUM_VISIBLE`, motiu, detall/actor/referència, decisió UC-74, registre anterior i clau idempotent estable. Comprovar si hi ha factura rectificativa, ingrés o operació acadèmica que requeriria una altra via. |
| Persistència real | `factura_registres` append-only en l'ús del servei, hash anterior, `fiscal_chain_state`, `fiscal_queue` i `factura.ESTAT_FACTURA=CANCELLED`. El codi no fa `DELETE` d'`factura` ni `UPDATE` dels seus imports, receptor o línies en aquesta funció. |
| Idempotència | Cerca `fiscal_queue.IDEMPOTENCY_KEY` i reutilitza el mateix registre si el troba. **Risc pendent:** una referència proporcionada pot produir la mateixa clau fins i tot si canvia `reason`; el servei torna el resultat existent sense comprovar equivalència completa del payload nou. |
| Economia | Crear `ANULACIO` **no crea `REFUND`**, no esborra `CHARGE`, no mou imports entre inscripcions i no tramita una transferència bancària. Si hi ha diners implicats, UC-28/29/105 exigeix decisió i registre independent. |
| Estat AEAT | «En cua» no és «acceptat»; conservar eventual rebuig/intent posterior i operar UC-77 sense crear un segon registre per tornar-lo a enviar. |

### Flux funcional amb execució existent i aprovació pendent

1. UC-74 identifica el fet justificat i aprova explícitament que correspon **registre d'anul·lació**, no baixa o correcció ordinària d'una prestació; comprovar permisos i factura original. **Aquest classificador no és part de `FiscalRecordService`.**
2. L'adaptador crida `createCancellationByUuid()` amb motiu i referència idempotent vinculats a l'event. El servei obté factura, comprova `NO_VERIFACTU/HISTORICAL`, llegeix registre anterior i evita una segona anul·lació de l'original.
3. La transacció crea la nova fila fiscal i cua, actualitza la cadena i posa estat `CANCELLED`; si hi ha retry equivalent, retorna el registre de la mateixa clau.
4. El worker UC-77 tracta el job i guarda intents/resposta. L'estat fiscal del registre i l'acceptació efectiva s'han de consultar **separadament** de `ESTAT_FACTURA`.
5. Una inscripció que ja té cobrament **no es dona de baixa ni es retorna** perquè la factura passi a `CANCELLED`; cal expedient econòmic/acadèmic explícit i traça del valor per inscrit.

**Proves pendents:** referència idempotent amb motiu contradictori, anul·lació simultània, invoice `NO_VERIFACTU`, sense registre anterior, després de subsanació, amb pagaments reals, recuperació d'error de cua i contracte XML/AEAT. No s'han executat proves PHP en aquesta revisió.

## 3. UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Gestió autoritzada" as G
actor "Responsable fiscal" as F
actor "Worker AEAT" as A
rectangle "SIF · registre d'anul·lació" {
 usecase "UC-75\nCrear registre ANULACIO" as Main
 usecase "UC-74\nAprovar la via fiscal" as Decide
 usecase "Validar original i registre anterior" as Validate
 usecase "Persistir hash, cadena i cua" as Persist
 usecase "UC-77\nEnviar el mateix registre" as Queue
}
G --> Main
F --> Decide
A --> Queue
Main ..> Decide : <<include>> (política pendent)
Main ..> Validate : <<include>>
Main ..> Persist : <<include>>
@enduml
```

### Vista de casos d’ús per a GitHub (Mermaid)

```mermaid
flowchart LR
  actor_0["Gestió autoritzada"]
  actor_1["Responsable fiscal"]
  actor_2["Worker AEAT"]
  subgraph SIF_BOX["SIF · registre d'anul·lació"]
    uc_0(["UC-75<br/>Crear registre ANULACIO"])
    uc_1(["UC-74<br/>Aprovar la via fiscal"])
    uc_2(["Validar original i registre anterior"])
    uc_3(["Persistir hash, cadena i cua"])
    uc_4(["UC-77<br/>Enviar el mateix registre"])
  end
  actor_0 --> uc_0
  actor_1 --> uc_1
  actor_2 --> uc_4
  uc_0 -.->|include| uc_1
  uc_0 -.->|include| uc_2
  uc_0 -.->|include| uc_3
```

## 4. UML de classes — dependències PHP efectives

```mermaid
classDiagram
class FiscalCorrectionClassifier {
 <<DISSENY: UC-74 no acreditat>>
 +approve(uuidFactura,decision,actor) classification
}
class FiscalRecordService {
 <<PHP existent>>
 +createCancellationByUuid(uuidFactura,input) array
 +createCancellationByNumVisible(numVisible,input) array
}
class FiscalRecordPayloadBuilder {
 <<PHP existent>>
 +cancellation(invoice,previousRecord,input) array
 +idempotencyKey(recordType,invoice,payload,input) string
}
class ManualPaymentInvoiceRepository {
 <<PHP existent: consulta/lock factura>>
 +findByUuid(db,uuidFactura,forUpdate) array
}
class FiscalRecordRepository {
 <<PHP existent>>
 +latestForInvoice(db,uuidFactura,forUpdate) array
 +findQueuedResult(db,key,forUpdate) array
 +create(db,invoice,recordType,key,payload,markCancelled) array
}
FiscalCorrectionClassifier ..> FiscalRecordService : decisió prèvia [integració pendent]
FiscalRecordService --> ManualPaymentInvoiceRepository : original
FiscalRecordService --> FiscalRecordPayloadBuilder : payload
FiscalRecordService --> FiscalRecordRepository : registre fiscal i cua
```

## 5. UML de seqüència — anul·lació real sense devolució

```mermaid
sequenceDiagram
autonumber
actor G as Gestió
participant C as FiscalCorrectionClassifier [DISSENY]
participant S as FiscalRecordService [PHP]
participant I as ManualPaymentInvoiceRepository [PHP]
participant R as FiscalRecordRepository [PHP]
participant B as FiscalRecordPayloadBuilder [PHP]
participant Q as fiscal_queue [SQL]
G->>C: Sol·licitar anul·lació sobre factura F
C-->>G: Via ANULACIO aprovada i motiu [pendent]
G->>S: createCancellationByUuid(F,input)
S->>I: findByUuid(F,FOR UPDATE)
S->>R: latestForInvoice(F,FOR UPDATE)
alt Sense factura/registre o NO_VERIFACTU
 S-->>G: Rebuig de validació
else Original admissible
 S->>B: cancellation(factura,registreAnterior,input)
 S->>B: idempotencyKey(ANULACIO,...)
 S->>R: findQueuedResult(key,FOR UPDATE)
 alt Resultat ja existeix
  R-->>G: Registre existent, idempotency_reused=true
 else Primera execució
  S->>R: create(ANULACIO,markCancelled=true)
  R->>R: Lock cadena, hash i INSERT factura_registres
  R->>Q: Encolar payload immutable
  R->>I: Actualitzar només ESTAT_FACTURA=CANCELLED
  R-->>G: UUID_FACTURA, fiscal_order i queue_idempotency_key
 end
end
Note over S,Q: No es registra REFUND ni es certifica resposta AEAT en aquesta seqüència.
```

## 6. Traçabilitat

[UC-75 original](../06-fitxes-funcionals/uc-075.md) · [UC-74 classificador](uc-074-classificar-correccio-fiscal.md) · [UC-76 subsanació original](../06-fitxes-funcionals/uc-076.md) · [UC-77 cua original](../06-fitxes-funcionals/uc-077.md) · [FiscalRecordService](../../sif/src/Service/FiscalRecordService.php) · [FiscalRecordRepository](../../sif/src/Repository/FiscalRecordRepository.php) · [FiscalRecordPayloadBuilder](../../sif/src/Service/FiscalRecordPayloadBuilder.php) · [UC-28 retorn](uc-028-registrar-devolucio.md) · [Moviments per inscripció](00-revisio-moviments-inscripcions.md).
