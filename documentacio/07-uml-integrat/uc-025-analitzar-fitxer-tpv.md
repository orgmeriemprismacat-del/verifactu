# UC-25 · Analitzar un fitxer TPV i proposar la conciliació

**Objectiu del catàleg:** identificar en el fitxer del proveïdor TPV operacions `CONCILIADA`, `DUPLICADA`, pendents o amb incidència, sense transformar automàticament una fila ambigua en un nou cobrament o factura. La documentació de fluxos indica expressament que **«el fitxer TPV no emet ni registra pagaments per si sol si hi ha qualsevol ambigüitat»**. UC-25a diagnostica `IDPAG` repetits; UC-56 cerca/assigna un cobrament identificat; UC-53 resol divergències entre sistemes.

**Estat contrastat:** existeixen `payment_transaction`, `payment_allocation`, `redsys_notifications`, `redsys_payment_intent`, `reconciliation_run` i `reconciliation_item` a SQL, i serveis executables de pagament/callback Redsys per operacions individuals. **No s'ha identificat en `sif/src` un parser/importador de fitxer TPV, un `TpvFileReconciliationService` ni una pantalla de revisió de fitxers implementats.** La conciliació de fitxers descrita és un **contracte objectiu**, no un import bancari executable.

## 1. Fitxa funcional específica

| Camp | Comportament |
| --- | --- |
| Actor | Operador de facturació/pagaments amb accés a dades bancàries, i responsable tècnica per incidències. El fitxer TPV és font externa, no instrucció autoritzada per generar pagaments automàticament. |
| Entrada | Fitxer i identificador/hash del lot, entitat/proveïdor, data, ordre/referència `DS_ORDER`, `IDPAG` quan existeixi, codi resultat, import, divisa, terminal i identificació de l'operació bancària si el proveïdor la facilita. El parser concret, columnes i formats **no s'han acreditat**. |
| Comparació | Per fila, cercar intenció Redsys, notificació `VALIDATED`/`ERROR`, job asíncron, `payment_transaction`, `payment_allocation`, factura i, quan pertoqui, `fact_rels` de la inscripció. Una coincidència d'`IDPAG` sola **no** deduplica un cobrament. |
| Resultats | `CONCILIADA` si origen i import coincideixen amb un moviment real existent; `DUPLICADA` si una entrada bancària repetida és demostrablement el mateix cobrament; `PENDENT` si manca dada o processament; `INCIDENCIA` quan hi ha import, ordre, titular o assignació contradictoris. Són **categories funcionals del catàleg**, no valors executables verificats d'un parser. |
| Persistència prevista | `reconciliation_run` per fitxer/hash/lot i `reconciliation_item` per fila, amb referència de font, `UUID_PAYMENT`/`UUID_FACTURA` quan existeixin, diferència/JSON i estat de resolució. Les taules estan definides, **el writer del fitxer no està acreditat**. |
| Efecte econòmic | Analitzar una fila no és un nou `CHARGE`. Si el banc acredita una operació real absent, UC-56/02 ha d'assegurar la seva identitat i registrar **una vegada** l'ingrés amb `payment_allocation`. Si el pagament ja existeix, s'enllaça/reconcilia, no es recrea. |
| Fons d'inscripció | En packs/grups una única fila TPV pot correspondre a **N inscripcions**; les atribucions internes han de sumar l'import real únic del moviment, sense dividir a parts iguals per defecte. El ledger per inscripció és **proposta pendent**. |

### 1.1. Flux objectiu de conciliació

1. L'operador puja el fitxer en canal autoritzat. El sistema calcula hash, conserva origen/versió/format i crea una revisió idempotent. Les credencials, PAN/CVV i informació de targeta innecessària **no** s'incorporen a la BD ni als logs.
2. El parser **pendent** normalitza cada fila en ordre, import en decimals, resultat, divisa, data i referència. Files desconegudes o malformades queden en incidència, no es «corregeixen» amb valors inventats.
3. El reconciliador consulta per `DS_ORDER` i identificació d'operació, després per `IDPAG` com a **pista contextual**, i enllaça intenció Redsys, notificació, cua, moviment existent, factura i assignacions. Distingeix denegació, autorització sense worker processat, moviment processat i possible retorn posterior.
4. Compara la fila amb el `payment_transaction` existent: import real, data/proveïdor, divisa quan disponible, `PAYLOAD_HASH`, `IDPAG`, `DS_ORDER` i estat. Una segona fila equivalent al mateix `UUID_PAYMENT` no crea una entrada nova.
5. Guarda un `reconciliation_item` amb resultat i evidència per a la fila. Els resultats han de ser reproduïbles a partir de la versió exacta del fitxer i de l'instant de la BD. Un reintent idèntic de lot no ha de crear una segona conciliació activa indistinguible.
6. Per pendents/contradiccions es requereix acció explícita: completar el job original UC-52, cercar i assignar un cobrament UC-56, investigar un `IDPAG` duplicat UC-25a o obrir incidència UC-08. **El fitxer no salta el control del callback signat**.
7. Només quan s'ha acreditat que existeix un ingrés real que **no consta** al SIF, una ordre separada autoritzada pot registrar-lo amb clau idempotent independent, comprovant que no és el mateix ingrés ja registrat per callback/worker.
8. Després de registrar o enllaçar el pagament, les assignacions a factura i les atribucions individuals d'inscripció s'han de conciliar amb el mateix import de caixa i sense duplicació.

### 1.2. Alternatives i proves específiques

| Situació | Classificació/acció |
| --- | --- |
| Fila bancària duplicada en dos fitxers | Una mateixa entrada acreditada, dos registres d'evidència de fitxer si cal, **un sol moviment bancari**. |
| `DS_ORDER` existent, import diferent del cobrament SIF | `INCIDENCIA`, no reutilitzar l'UUID com si fos una coincidència correcta. |
| `IDPAG` repetit en diverses inscripcions, però `DS_ORDER` distint | UC-25a determina si hi ha pagaments separats o mateixa compra/grup; `IDPAG` no és clau universal de deduplicació. |
| Callback validat però worker en `RETRY` | `PENDENT` d'emissió; no forçar manualment una segona factura/pagament sense comprovar job i idempotència. |
| Una fila bancària de grup de N persones | Una transacció real, N atribucions internes, amb imports específics per línia. |
| Fitxer amb devolució | Classificar moviment de sortida **real** i enllaçar `REFUND` existent o tramitar-lo per la ruta pertinent; no netejar una fila de devolució sumant-la com un ingrés. |
| File reprocessat després d'una conciliació parcial | Reutilitzar referència/hash del lot, no duplicar `reconciliation_item` sense control, ni crear `CHARGE` per cada reexecució. |

**Proves no executades:** fitxer idèntic repetit, fila `DS_ORDER` duplicada, dos `IDPAG` coincidents amb ordres diferents, import contradictory, denegació TPV, job `RETRY`, retorn, group N inscripcions, rol denegat i transacció ja assignada.

## 2. Diagrama UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Operador pagaments" as O
actor "Responsable tècnica" as T
rectangle "SIF · conciliació fitxer TPV" {
 usecase "UC-25\nAnalitzar fitxer TPV" as Main
 usecase "Normalitzar i validar files" as Parse
 usecase "Comparar ordre i pagament real" as Match
 usecase "Classificar i registrar diferències" as Class
 usecase "UC-25a\nInvestigar IDPAG repetits" as Dup
 usecase "UC-56\nAssignar pagament identificat" as Alloc
}
O --> Main
T --> Dup
O --> Alloc
Main ..> Parse : <<include>>
Main ..> Match : <<include>>
Main ..> Class : <<include>>
@enduml
```

## 3. Subdiagrama de classes existent vs disseny

```mermaid
classDiagram
direction LR
class TpvFileReconciliationService {
 <<DISSENY: parser i conciliador no acreditats>>
 +analyse(file,actor) run
 +classify(row,snapshot) item
}
class TpvFileParser {
 <<DISSENY: format no acreditat>>
 +parse(file) rows
}
class ReconciliationRunRepository {
 <<DISSENY: taules SQL definides, writer no acreditat>>
 +findByInputHash(db,hash) run
 +appendItem(db,item) result
}
class PaymentRepository {
 <<PHP existent>>
 +findByIdempotencyKey(db,key,forUpdate) array
}
class RedsysNotificationRepository {
 <<PHP existent>>
 +findByDsOrder(db,dsOrder) array
}
class PaymentService {
 <<PHP existent: registre separable>>
 +registerPayment(payload) array
}
TpvFileReconciliationService --> TpvFileParser : extreure files
TpvFileReconciliationService --> ReconciliationRunRepository : resultat per fila
TpvFileReconciliationService --> RedsysNotificationRepository : ordre Redsys
TpvFileReconciliationService --> PaymentRepository : comprovar moviment existent
```

`PaymentService` és l'operació **separada** de registre autoritzat quan s'ha comprovat ingrés nou; **no** és cridada automàticament pel parser fictici.

## 4. Seqüència — conciliació sense duplicar ingressos (DISSENY)

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant UI as Intranet [pendent]
participant S as TpvFileReconciliationService [DISSENY]
participant Parser as TpvFileParser [DISSENY]
participant R as ReconciliationRunRepository [DISSENY]
participant Bank as Fitxer TPV del proveïdor
participant DB as BD SIF: intents, notificacions, payments
participant P as UC-56/02 [acció separada]
O->>UI: Pujar fitxer TPV
UI->>S: analyse(file,actor)
S->>R: Crear/reutilitzar run per hash i identitat de lot
S->>Parser: parse(file)
Parser->>Bank: Llegir bytes/columnes del fitxer subministrat
Bank-->>Parser: Files normalitzades
loop Per cada fila
 S->>DB: Cercar DS_ORDER, IDPAG contextual, UUID_PAYMENT i factura
 alt Moviment existent equivalent
  DB-->>S: UUID_PAYMENT amb mateix import/origen
  S->>R: appendItem(CONCILIADA o DUPLICADA)
 else No es pot acreditar encara moviment nou
  DB-->>S: Cap coincidència o contradicció
  S->>R: appendItem(PENDENT o INCIDENCIA)
 end
end
R-->>UI: Resum del lot i items per revisió
opt Operador confirma una nova entrada real i absent de SIF
 O->>P: Ordre autoritzada amb referència i idempotència pròpia
 P-->>O: UUID_PAYMENT nou o conflicte; assignació per factura
end
Note over S,P: El fitxer no emet factura ni CHARGE per si sol
```

## 5. Traçabilitat

[UC-25 original](../06-fitxes-funcionals/uc-025.md) · [UC-25a original](../06-fitxes-funcionals/uc-025a.md) · [UC-56 original](../06-fitxes-funcionals/uc-056.md) · [UC-53 divergències](uc-053-detectar-resoldre-divergencies.md) · [UC-03 Redsys](uc-003-processar-cobrament-redsys-asincron.md) · [UC-52 worker](uc-052-operar-cua-redsys.md) · [Document de fluxos i fitxer TPV](../03-canvis-pendents/04-fluxos-facturacio.md) · [Migració reconciliation_run/item](../../sif/database/migrations/2026_09_15_000003_add_functional_audit_control.sql) · [PaymentRepository](../../sif/src/Repository/PaymentRepository.php) · [Traça de fons](00-revisio-moviments-inscripcions.md).
