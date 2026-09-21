# UC-105 · Reassignar o repartir un pagament conservant la història

**Objectiu canònic:** conservar **assignació anterior, motiu, nova assignació i cronologia append-only** sense editar el moviment bancari original. UC-56 assigna un pagament existent que es troba sense atribució suficient; UC-105 canvia una assignació ja realitzada o divideix l'import entre més d'un destí. La rectificació de la factura, quan correspongui, és un cas fiscal separat.

**Estat verificat:** `payment_transaction` i `payment_allocation` existeixen, i `PaymentRepository::createPayment()` crea un moviment **nou** amb les assignacions del payload. `PaymentActionEventRepository` accepta `REALLOCATE`, `UNALLOCATE` i `SPLIT_ALLOCATION`, però l'acceptació d'aquests codis **no acredita un servei d'assignació**. `payment_allocation` en la migració principal no conté clau idempotent de la partida, estats, UUID d'event ni una relació append-only d'anul·lació. **No s'ha identificat** una implementació segura de `reallocateExistingPayment()` ni del ledger quantitatiu per inscripció.

## 1. Fitxa específica

| Aspecte | Regla funcional |
| --- | --- |
| Actor | Operador autoritzat i validador quan canvia factura, pagador/titular o destinatari; el sistema no permet traspàs de fons d'una empresa a una persona sense decisió legitimada. |
| Identitat | `UUID_PAYMENT` de la **mateixa entrada bancària**, assignació/es d'origen, `UUID_FACTURA` d'origen/destí, `ID_INSC` d'origen/destí quan n'hi ha, import, moneda, actor, motiu, request/correlation ID i versió actual. |
| Tipus | `REALLOCATE` canvia atribució A→B; `SPLIT_ALLOCATION` reparteix una part entre diversos destins; `UNALLOCATE` posa una part pendent de decisió sense convertir-la en cobrament nou. Són etiquetes de **traça** acceptades per `PaymentActionEventRepository`, no mètodes de negoci implementats. |
| Integritat | Suma dels imports assignats d'un cobrament real ≤ import extern disponible, després de retorns i altres sortides. La suma dels canvis interns d'un traspàs és zero a nivell de caixa; cap part d'una atribució anterior no desapareix de la història. |
| Model d'assignació fiscal actual | `payment_allocation` té `UUID_PAYMENT`, `UUID_FACTURA`, `IMPORT_ASSIGNAT`, `TIPUS_ASSIGNACIO`; no guarda `ID_INSC`. La correcció ha de deixar una traça anterior/nova auditable **abans** que es pugui considerar completa. |
| Registre individual necessari | `enrollment_fund_movement` **proposat**, amb una fila per transferència `ID_INSC_A → ID_INSC_B` i import exacte, `UUID_PAYMENT_ORIGIN` de traça i referència a l'event; **no** registrar `CHARGE` per a un traspàs intern. |
| Efecte fiscal | Reassignar diners entre factures **no** canvia els imports fiscals de cap factura ni autoritza a canviar receptor/concepte. Si també canvia el servei o la factura, UC-74 classifica una acció fiscal a part. |

### 1.1. Flux objectiu per trams

1. L'operador consulta `UUID_PAYMENT`, import real i totes les assignacions existents; el sistema mostra el destí de cada tram **amb import**, no només una llista de `fact_rels`.
2. Previsualitza estat actual i resultat: factura A/inscripció A, factura B/inscripció B, quantitat traspassada, import que roman, pagador/titular i efecte sobre deutes. Un pagament de 150 € ja assignat 150 € no es pot assignar novament 150 € a B sense retirar l'atribució a A.
3. El coordinador **pendent** bloqueja pagament, assignacions i saldos d'origen, comprova permisos, disponibilitat, moneda, cap retorn concurrent i versió; exigeix una clau idempotent per **cada tram** si hi ha diversos destins.
4. Escriu un event d'operació amb abans/després, actor i motiu. `PaymentActionGateway` pot donar `REQUESTED` i resultat terminal **només si el canal l'invoca**, però aquesta traça per si sola no conté un ledger per inscripció.
5. La persistència futura conserva els assentaments anteriors i registra la reversió/cessió i les noves assignacions sobre el **mateix `UUID_PAYMENT`**; recalcula l'estat de cobrament de **totes les factures afectades**. No fer `UPDATE payment_transaction.IMPORT` per redistribuir.
6. Per cada canvi d'atribució individual, afegeix una fila al ledger `enrollment_fund_movement` amb origen/destí, import, `UUID_PAYMENT_ORIGIN`, event i clau idempotent. Quan un pagament de grup es reparteix entre N inscrits, és **un** cobrament amb N atribucions, no N cobraments.
7. Si la BD académica llegada no actualitza la vista del participant, mantenir estat de sincronització pendent UC-47/53; **el commit SIF no implica commit automàtic al llegat**.

### 1.2. Escenaris de prova

| Abans → després | Resultat i invariant |
| --- | --- |
| Una entrada real 120 € atribuïda a A; 80 € traspassats a B | A conserva 40 €, B rep 80 €, caixa externa segueix sent **120 €**, cap `CHARGE` nou. |
| Una entrada real 200 € d'empresa atribuïda a dues inscripcions (100/100) | Repartiment individual explícit sense canviar l'única entrada; els drets de devolució segueixen la titularitat real. |
| Assignar 80 € de l'A quan A només disposa de 50 € | Rebuig sense crear saldo negatiu, amb event d'intent/denegació i factura original intacta. |
| Reintent del mateix tram després d'un timeout | Recuperar mateix UUID/partida o conciliar estat abans de reprendre; no repetir una transferència interna. |
| Transferència parcial A→B quan hi ha un REFUND concurrent | Bloquejar/sumar retorn abans de determinar disponibilitat. |
| Canvi de factura però no d'inscripció | Registrar modificació d'imputació fiscal i recalcular deute sense fingir un moviment A→B quan l'atribució individual no ha canviat. |
| Canvi d'inscripció amb factura original ja emesa | Preservar el document fiscal i exigir classificació separada UC-74 quan l'operació comercial ho requereixi. |

**Bloquejants:** model append-only de modificació d'assignacions fiscals, operació idempotent per partida, servei de recalculació de totes les factures afectades, registre per inscripció i prova entre BDs. Ni una fila d'`payment_action_event` ni una consulta SQL simple tanquen UC-105.

### 1.3. Repartiments del llegat, canvi de curs i transferència multifactura

**Com es repartia al llegat.** Els procediments de «Passar pagaments» identifiquen vies diferents per inscripció (`I`), pack (`P`), grup (`G`) o regal (`R`), i mètodes que recorren els membres de grup per actualitzar `PAGAMENT`, `DATA PAG` i `FRACCIO`. Aquests UPDATEs poden repartir un **únic ingrés** entre diversos inscrits, però no constitueixen `payment_allocation` SIF ni garanteixen que s'hagin conservat tots els imports individuals. El nou circuit ha de recuperar els imports d'origen i destí **per `ID_INSC`**, sense recrear N `CHARGE` a partir de N files de matrícula.

**Transferència multifactura i prepagament.** Una escola pot fer una sola transferència per diverses factures de participants/cursos, i també pot haver-hi una factura emesa abans de cobrar. El pagament s'identifica per la seva referència/UUID extern únic, mentre que les assignacions són les quantitats per **factura fiscal concreta**. Per reassignar una transferència ja registrada d'A cap a B, conservar les assignacions d'A com a història i enllaçar la nova imputació al **mateix `UUID_PAYMENT`**; la sortida de caixa neta és zero. `FACTURA_RELACIONADA` pot agrupar documents A/R i no basta per triar els destins.

**Canvi A→B→C després de pagar.** Si els diners atribuïts a una inscripció passen a una altra arran d'un canvi de curs, el primer traspàs i la possible reversió s'han de correlacionar amb l'event original. Una devolució real posterior redueix el fons intern disponible i no pot coexistir amb una atribució íntegra del mateix import a C. Una variació de servei/concepte/import pot requerir **documents fiscals separats** UC-74/05, però traspassar diners no edita automàticament ni l'original A ni els seus correctors.

**Límit precís del PHP.** `PaymentRepository::createPayment()` crea `payment_transaction` amb totes les assignacions de la petició dins del registre d'un moviment nou; no és una API per reassignar una partida d'un `UUID_PAYMENT` ja existent. La disponibilitat d'etiquetes d'event `REALLOCATE`, `UNALLOCATE` o `SPLIT_ALLOCATION` tampoc acredita una reversió append-only implementada. La ruta de reassignació continua **disseny/bloquejant**, amb necessitat de bloquejos, identificació idempotent per tram, traça anterior/nova i recalculació d'estat de **totes** les factures afectades.

### 1.4. Proves de reassignació de diners ja cobrats (no executades)

| ID | Escenari | Resultat exigible |
| --- | --- | --- |
| RA-01 | Una transferència real cobreix dues factures d'empresa | Un UUID_PAYMENT, dues imputacions per import i receptor autoritzat. |
| RA-02 | Grup amb tres inscripcions i un únic pagament | Tres atribucions internes traçables i una sola entrada de caixa. |
| RA-03 | Canvi de curs A→B→C després de pagar | Trams i reversions correlacionats; no duplicar import a B i C. |
| RA-04 | Ja s'ha retornat part del pagament abans del traspàs | Comprovar disponibilitat neta i bloquejar sobreatribució. |
| RA-05 | `FACTURA_RELACIONADA` agrupa factura A i rectificativa R | Identificar `UUID_FACTURA` de cada imputació, no reassignar per l'agrupador sol. |
| RA-06 | Intent de reassignació amb autorització d'un participant, pagador empresa | Validar titularitat i permisos; no traspassar fons de tercer a l'alumne per defecte. |
| RA-07 | Reintent d'una mateixa transferència interna | Recuperar event/partides anteriors i saldo actual, cap segon traspàs. |

## 2. UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Operador autoritzat" as Op
actor "Validador responsable" as Resp
rectangle "SIF · reassignació d'un pagament" {
 usecase "UC-105\nReassignar o repartir pagament" as Main
 usecase "Comprovar pagament i saldo origen" as Check
 usecase "Previsualitzar trams i titulars" as Preview
 usecase "Registrar reversió i noves atribucions" as Move
 usecase "UC-86\nAuditar decisió i resultat" as Audit
 usecase "UC-74\nClassificar impacte fiscal separat" as Fiscal
}
Op --> Main
Resp --> Main
Main ..> Check : <<include>>
Main ..> Preview : <<include>>
Main ..> Move : <<include>>
Main ..> Audit : <<include>>
Resp --> Fiscal
@enduml
```

## 3. Classes — infraestructura present, reassignació no implementada

```mermaid
classDiagram
direction LR
class PaymentReallocationService {
 <<DISSENY: no implementada>>
 +preview(uuidPayment,command) result
 +apply(command) result
}
class PaymentAllocationHistoryRepository {
 <<DISSENY: model històric no acreditat>>
 +lockPaymentAndAllocations(db,uuidPayment) state
 +recordReversalAndAssignments(db,command) result
}
class EnrollmentFundMovementRepository {
 <<PROPOSTA: no implementada>>
 +append(db,movement) string
 +balanceForEnrollment(db,idInsc) decimal
}
class PaymentActionGateway {
 <<PHP existent>>
 +run(auditContext,operation) mixed
}
class PaymentActionEventRepository {
 <<PHP existent>>
 +append(db,event) string
}
class PaymentRepository {
 <<PHP existent: només crea transacció i assignacions>>
 +createPayment(db,payload) array
}
PaymentReallocationService --> PaymentAllocationHistoryRepository : mateix UUID_PAYMENT
PaymentReallocationService --> EnrollmentFundMovementRepository : trams per inscripció
PaymentReallocationService ..> PaymentActionGateway : auditar via adaptador [DISSENY]
PaymentActionGateway --> PaymentActionEventRepository : cronologia, no diners
```

## 4. Seqüència — traspàs de 80 € A→B (DISSENY)

```mermaid
sequenceDiagram
autonumber
actor Op as Operador
participant C as PaymentReallocationService [DISSENY]
participant A as PaymentAllocationHistoryRepository [DISSENY]
participant E as EnrollmentFundMovementRepository [PROPOSTA]
participant Audit as PaymentActionGateway [PHP, si integrat]
participant DB as BD SIF
Op->>C: Traspassar 80 € de la inscripció A a B sobre UUID_PAYMENT P
C->>Audit: run(REALLOCATE,callback) [integració pendent]
Audit->>A: lockPaymentAndAllocations(P)
A->>DB: SELECT P i trams FOR UPDATE
A-->>C: Estat/versions, A disponible 120 €
C->>C: Validar titular, origen, import, destí i idempotència
alt No hi ha saldo, permís o resultat coherent
 C-->>Op: Rebuig auditat, ni CHARGE ni canvi de factura
else Import vàlid
 C->>A: recordReversalAndAssignments(P,A,B,80 €)
 A->>DB: Escriure història i recalcular factures A/B
 C->>E: append(REALLOCATION,A→B,80 €,UUID_PAYMENT_ORIGIN=P)
 E-->>C: UUID_MOVEMENT reutilitzable
 Audit-->>Op: SUCCEEDED/REUSED amb UUID_PAYMENT=P
end
Note over C,E: Operació i ledger objectius, no serveis PHP identificats avui
```

## 5. Traçabilitat

[UC-105 original](../06-fitxes-funcionals/uc-105.md) · [UC-56 assignar](uc-056-cercar-assignar-cobrament.md) · [UC-104 excés](uc-104-gestionar-exces-cobrament.md) · [UC-86 auditoria](uc-086-auditar-accio-pagament.md) · [UC-74 original](../06-fitxes-funcionals/uc-074.md) · [PaymentRepository](../../sif/src/Repository/PaymentRepository.php) · [PaymentActionGateway](../../sif/src/Service/PaymentActionGateway.php) · [Migració payment_allocation](../../sif/database/migrations/2026_06_02_000001_create_sif_core.sql) · [Model de fons individual](00-revisio-moviments-inscripcions.md).
