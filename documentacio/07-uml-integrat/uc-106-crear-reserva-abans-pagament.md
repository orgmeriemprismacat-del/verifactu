# UC-106 · Crear reserva o inscripció abans del pagament

**Objectiu canònic:** la reserva queda identificada amb **edició, plaça, preu, regla comercial, pagador, receptor fiscal provisional, estat i caducitat**; crear-la encara **no implica factura ni cobrament**. Les regles sobre durada de reserva, moment de consum de plaça i autorització per alliberar/reobrir són **decisions pendents** a la fitxa original.

**Estat contrastat:** el repositori defineix `commercial_operation`, `commercial_operation_party` i `capacity_reservation` a SQL; `RedsysPaymentIntentService::create()` **sí** crea intencions TPV amb snapshot i estat `PENDING`. **No s'ha identificat** un `EnrollmentReservationService` que coordini alta llegada, reserva de capacitat i operació comercial. **Una intenció Redsys existent no és una plaça acadèmica reservada.**

## 1. Fitxa funcional específica

| Element | Contracte |
| --- | --- |
| Actors | Alumne/comprador, ecommerce i operador autoritzat; el pagador i receptor fiscal provisional poden ser diferents de la persona inscrita. |
| Entrada | Persona i identificador d'inscripció si existeix; `PRODUCT_CODE`, `PRODUCT_EDITION`, plaça o `RESOURCE_KEY`, quantitat, preu base, descompte/versionat, import previst, pagador, receptor fiscal provisional, moneda, termini i clau idempotent. |
| Persistència comercial definida | `commercial_operation.UUID_OPERATION`, `IDEMPOTENCY_KEY`, `OPERATION_TYPE`, `PRODUCT_TYPE/CODE/EDITION`, `PRICE_SNAPSHOT_JSON`, `CAPACITY_SNAPSHOT_JSON`, `TAX_SNAPSHOT_JSON`, `CLASSIFICATION`, `STATUS`, `EXPIRES_AT`, `UUID_INTENT/FACTURA/PAYMENT` opcionals. **Són camps SQL, no una escriptura PHP acreditada**. |
| Reserva de plaça definida | `capacity_reservation` preveu `UUID_CAPACITY_RESERVATION`, `UUID_OPERATION`, recurs, quantitat, estat, venciment, `LOCK_VERSION` i clau idempotent; el control de sobreaforament és UC-115 i **no deriva automàticament de la taula**. |
| Intenció TPV **implementada** | `RedsysPaymentIntentService::create()` valida snapshot JSON, `DS_ORDER`, `SOURCE_TYPE` dins `CURS/PACK/GRUP/REGAL/USOC_ALUMNE`, `SOURCE_ID`, import positiu, divisa i terminal; reutilitza una ordre existent si totes les dades rellevants coincideixen. |
| Efectes absents | Una reserva **no** insereix `payment_transaction`, `payment_allocation`, `factura` o `factura_registres` fins a executar els casos de cobrament/emissió corresponents. |
| Límit entre BDs | Reserva/acadèmia llegada i BD fiscal SIF poden requerir comanda/reintents amb correlació; **no** presumir una transacció SQL distribuïda o alta acadèmica confirmada perquè `redsys_payment_intent` tingui fila. |

### 1.1. Flux objectiu

1. El canal identifica persona, curs i edició, producte i sessió, comprova duplicat UC-107, aforament UC-115 i la política comercial vigent. Una relació amb el llegat no prova reserva de capacitat.
2. Crea/reutilitza una **operació de reserva idempotent** i desa snapshot de preu, places, pagador/receptor provisional, regla fiscal preliminar i caducitat acordada; els writers de `commercial_operation`/`capacity_reservation` continuen pendents.
3. Si es confirma una plaça, vincula `UUID_CAPACITY_RESERVATION` a l'operació i l'alta/inscripció llegada. Si la BD acadèmica falla, manté estat de conciliació i allibera o recupera la plaça segons política, sense marcar una matrícula definitiva silenciosament.
4. UC-112 congela els valors acceptats abans del TPV; només després UC-63 crea la intenció Redsys amb snapshot. **No tornar a calcular el preu en el callback** a partir de l'edició mutable.
5. Si no s'inicia o es denega el pagament, la reserva segueix l'estat i el termini aprovat. La **denegació** no és factura ni moviment de diners. Si es cobra i la reserva ha vençut, obrir incidència sobre disponibilitat abans de concedir una plaça fictícia.
6. Després de l'èxit real de cobrament/emissió, l'orquestració objectiu confirma o vincula acadèmicament l'alta i conserva `UUID_OPERATION`, `UUID_INTENT`, `UUID_FACTURA`, `UUID_PAYMENT` i `ID_INSC`, amb una única atribució del valor rebut per inscripció.

### 1.2. Alternatives i proves

| Cas | Resposta |
| --- | --- |
| Dos clics equivalents abans de pagar | Reutilitzar `UUID_OPERATION` i plaça associada; **no** crear dos `IDPAG` ni dos drets de matrícula. |
| Mateixa clau però curs/edició, preu o persona diferents | Conflicte, no tractar com a reintent equivalent. |
| Reserva vençuda sense cobrament | Alliberar plaça segons UC-115 i informar de nova validació de preu; **cap REFUND** de diners inexistents. |
| Callback confirmat quan la reserva ha vençut | Comprovar acceptació temporal/plaça real i obrir incidència o procés autoritzat; no descartar l'ingrés ni concedir plaça per defecte. |
| Empresa paga diverses inscripcions | Una operació/grup amb N participants explícits, un pagament real quan es produeixi i atribucions individuals, sense N CHARGE ficticis. |
| Factura abans del pagament sol·licitada per empresa | És UC-21: el fet fiscal específic pot antecedir el cobrament; **aquesta reserva UC-106 per si sola no crea factura**. |

**Pendents de tancament:** regles de caducitat, plaça i reobertura; schema/writer d'operacions; coordinació amb llegat; duplicats per persona/producte/edició; preus/receptor fiscal definitius; proves TPV tardà i idempotència.

## 2. Diagrama UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Alumne/comprador" as A
actor "Gestió acadèmica" as G
rectangle "Reserva comercial + inscripció" {
 usecase "UC-106\nCrear reserva abans de pagar" as Main
 usecase "UC-107\nDetectar matrícula duplicada" as Dup
 usecase "UC-115\nReservar plaça real" as Cap
 usecase "Guardar operació, preu i actors" as Save
 usecase "UC-112\nCongelar abans del TPV" as Freeze
 usecase "UC-63\nCrear intenció quan s'inicia pagament" as Intent
}
A --> Main
G --> Main
Main ..> Dup : <<include>>
Main ..> Cap : <<include>> (si reserva plaça)
Main ..> Save : <<include>>
Main ..> Freeze : <<include>> (abans de TPV)
A --> Intent
@enduml
```

## 3. Diagrama de classes: intenció executable vs reserva pendent

```mermaid
classDiagram
direction LR
class EnrollmentReservationService {
 <<DISSENY: no acreditat>>
 +reserve(command) result
 +expire(uuidOperation) result
 +confirm(uuidOperation,payment) result
}
class CommercialOperationRepository {
 <<DISSENY: taula SQL definida>>
 +findOrCreate(db,command) result
}
class CapacityReservationRepository {
 <<DISSENY: taula SQL definida>>
 +reserve(db,resource,operation) result
 +release(db,uuidReservation,reason) result
}
class LegacyEnrollmentGateway {
 <<DISSENY: BD llegada>>
 +createOrLink(command) result
}
class RedsysPaymentIntentService {
 <<PHP existent>>
 +create(db,input) array
}
class RedsysPaymentIntentRepository {
 <<PHP existent>>
 +findByDsOrder(db,dsOrder) array
 +insert(db,intent) array
}
EnrollmentReservationService --> CommercialOperationRepository : operació
EnrollmentReservationService --> CapacityReservationRepository : plaça
EnrollmentReservationService --> LegacyEnrollmentGateway : inscripció
RedsysPaymentIntentService --> RedsysPaymentIntentRepository : només intenció TPV
```

## 4. Seqüència — reservar i iniciar pagament (DISSENY/PARCIAL)

```mermaid
sequenceDiagram
autonumber
actor A as Alumne
participant UI as Ecommerce [integració pendent]
participant S as EnrollmentReservationService [DISSENY]
participant O as CommercialOperationRepository [DISSENY]
participant Cap as CapacityReservationRepository [DISSENY]
participant L as BD d'inscripcions llegada
participant I as RedsysPaymentIntentService [PHP]
A->>UI: Triar activitat i demanar reserva
UI->>S: reserve(persona,edició,preu,requestId)
S->>O: findOrCreate(operació i snapshots)
S->>Cap: reserve(recurs,plaça,caducitat) [UC-115]
alt No hi ha plaça o matrícula ja activa
 Cap-->>S: Conflicte o reús d'inscripció equivalent
 S-->>UI: No duplicar reserva/alta
else Reserva disponible
 Cap-->>S: UUID_CAPACITY_RESERVATION
 S->>L: Crear/vincular inscripció provisional
 L-->>S: ID_INSC
 S-->>UI: UUID_OPERATION, ID_INSC, plaça i venciment
 A->>UI: Confirmar compra
 UI->>I: create(DS_ORDER,snapshot congelat UC-112)
 I-->>UI: UUID_INTENT PENDING
end
Note over S,L: Reserva i coordinació llegat = DISSENY; intenció TPV ≠ plaça ni factura
```

## 5. Traçabilitat

[UC-106 original](../06-fitxes-funcionals/uc-106.md) · [UC-107 duplicat original](../06-fitxes-funcionals/uc-107.md) · [UC-115 aforament original](../06-fitxes-funcionals/uc-115.md) · [UC-63 intenció](uc-063-crear-intencio-redsys.md) · [UC-04 factura abans de pagar](uc-004-emetre-factura-abans-cobrar.md) · [RedsysPaymentIntentService](../../sif/src/Service/RedsysPaymentIntentService.php) · [Migració operació comercial](../../sif/database/migrations/2026_09_16_000004_add_commercial_operation_and_fiscal_fields.sql) · [Migració reserva capacitat](../../sif/database/migrations/2026_09_16_000005_add_operation_lifecycle_tables.sql).
