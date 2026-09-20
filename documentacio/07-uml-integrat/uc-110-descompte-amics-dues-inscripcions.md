# UC-110 · Descompte d'amics: dues inscripcions, un pagador

**Objectiu canònic:** dues persones poden inscriure's a **cursos diferents** i compartir una operació/intenció i un pagador, però cada participant, curs, edició, base, descompte i import final s'ha de congelar separadament. **Decisió bloquejant:** la factura pot correspondre a dues línies al pagador o a factures separades segons receptors; el fet de compartir `IDPAG` o pagador **no resol la identitat del receptor fiscal**.

**Estat del codi:** `RedsysGroupInvoiceService` i `LegacyGroupInvoicePayloadBuilder` admeten un snapshot amb N `items`, una línia `INSCRIPCIO` i una relació per participant, i una relació addicional `GRUP`. **Això no acredita que el descompte d'amics estigui integrat a la ruta de grup ni que la promoció sigui fiscalment una factura única**. El builder de grup usa `responsible` com a receptor, `IDPAG` de grup i tracta totes les línies com a grup; manca un `FriendsDiscountCheckoutService` verificat que autoritzi regles/preus/receptors individuals.

## 1. Fitxa funcional específica

| Element | Regla |
| --- | --- |
| Actors | Amic A, amic B, pagador designat, ecommerce i operador que resol diferències fiscals. El pagador pot no ser receptor únic de les dues prestacions. |
| Entrada | Dos participants amb `ID_INSC_A` i `ID_INSC_B`, dos productes/edicions potencialment diferents, bases de preu, regla/percentatge aprovat, descompte **per línia**, total, `UUID_OPERATION`, pagador, receptor/s i un identificador de compra. |
| Regla comercial | Cal validar que **ambdues** inscripcions compleixen les condicions reals de la promoció i calcular el descompte sobre la **base de cadascun dels cursos**. El percentatge i les incompatibilitats no queden fixats pel builder fiscal; no inventar-ne cap. |
| Model SQL | `commercial_operation_party` diferencia rols i participants i `commercial_operation_line` desa cada producte/edició, `PARTICIPANT_PARTY_KEY`, base/descompte/total i snapshot. `operation_line_invoice_link` permet traçar línies comercials a línies fiscals, **sense decidir** si hi ha una o dues factures. Són definicions SQL, no writer executat acreditat. |
| Fons | Una única transacció bancària **si** realment es fa un pagament conjunt; si s'emeten dues factures, una mateixa entrada es pot assignar a totes dues només amb suma de trams coherent. El ledger individual proposat ha de portar import A i B, **no dues entrades externes del total global**. |
| Correccions | Si una persona abandona abans d'emetre, revalidar promoció i imports **d'ambdues** línies abans de congelar. Si canvia després de la factura, UC-16a/16b, 71/72 i classificació fiscal/retorn corresponents; no editar directament la factura anterior. |

### 1.1. Flux objectiu

1. El canal identifica els dos amics i evita inscripcions duplicades UC-107 per persona, producte i edició. No es dedueix relació de parentiu, pagador o receptor del fet de compartir email.
2. Valida les condicions de la promoció d'amics segons regla vigent, conserva justificació i percentatge/descompte **per participant**. Per exemple, bases diferents generen imports de descompte diferents quan la regla és percentual.
3. Classifica receptor/s fiscal/s i agrupació de línies abans del TPV amb decisió de negoci/assessoria. **No** convertir automàticament el snapshot al format `GRUP` perquè existeixi un builder de N línies.
4. UC-112 congela cada producte, edició, plaça, import i receptor; un intent Redsys `DS_ORDER` només s'ha de crear quan l'adaptador determinat disposa d'un handler vàlid per la composició comercial.
5. Si hi ha un **únic ingrés conjunt confirmat**, UC-03 crea/reutilitza un `UUID_PAYMENT` extern; segons classificació, la seva suma s'assigna a la factura única o als dos documents, i els imports individuals d'A i B són moviments **interns** d'atribució.
6. La relació comercial conserva ambdós `ID_INSC`, `UUID_OPERATION`, `UUID_PAYMENT` i `UUID_FACTURA` corresponents. La comprovació final garanteix que `netA + netB = importConjunt` per pagament total, sense barrejar conceptes ni registrar `CHARGE` dues vegades.
7. En un pagament denegat no es crea ingrés; si una plaça es perd després de cobrar, no es descarta el cobrament real ni es concedeix una plaça fictícia: incidència i tractament individual.

### 1.2. Alternatives i proves concretes

| Cas | Comportament |
| --- | --- |
| A curs de 80 € i B de 120 €, promoció percentual | Congelar base i descompte d'A i B independentment segons percentatge aprovat; no repartir un descompte global per meitats. Imports **il·lustratius**, no tarifa real. |
| Un pagador, dues factures per receptors diferents | **Un** moviment bancari si la transacció és conjunta, dues imputacions fiscals que sumen el total i dues atribucions per `ID_INSC`. La capacitat de repartir un pagament existent UC-56 continua pendent d'implementació completa. |
| Dos participants i un curs diferent per cadascun | Conservar dues línies i edicions; no reconstruir tots dos cursos a partir del primer `IDPAG` o del primer `CURS`. |
| Baixa d'A abans del TPV | Reavaluar si B encara té dret al descompte i crear nova oferta acceptada; no reutilitzar el snapshot antic amb import contradictori. |
| Baixa d'A després de cobrar | Identificar import **atribuït a A** i titular del retorn; no retornar el total conjunt ni retirar el servei B automàticament. |
| Callback repetit | Reutilitzar factura/es i `UUID_PAYMENT` de l'operació, sense duplicar l'accés de cap participant. |

**Pendents:** definició comercial de la promoció d'amics, qui rep cada factura, handler comercial si no equival a grup, política de places, split fiscal de pagament existent i ledger per inscripció. No s'han executat proves específiques del descompte d'amics.

## 2. UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Amic A" as A
actor "Amic B" as B
actor "Pagador" as P
actor "Gestió/facturació" as G
rectangle "SIF · promoció d'amics" {
 usecase "UC-110\nComprar dos cursos amb descompte d'amics" as Main
 usecase "Validar dues inscripcions i elegibilitat" as Check
 usecase "Calcular dues línies i congelar-les" as Lines
 usecase "Decidir receptor/s fiscal/s" as Receiver
 usecase "UC-03\nConfirmar ingrés conjunt quan existeixi" as Pay
 usecase "Atribuir imports a A i B" as Funds
}
A --> Main
B --> Main
P --> Pay
G --> Receiver
Main ..> Check : <<include>>
Main ..> Lines : <<include>>
Main ..> Receiver : <<include>>
Funds ..> Main : <<extend>> (pagament confirmat)
@enduml
```

## 3. Diagrama de classes: builder genèric de grup ≠ integració d'amics

```mermaid
classDiagram
direction LR
class FriendsDiscountCheckoutService {
 <<DISSENY: no acreditat>>
 +prepare(personA,personB,payer,rule) operation
 +confirm(operation,classification) result
}
class FriendsDiscountPolicy {
 <<DISSENY: regla comercial pendent>>
 +validateAndPrice(twoLines) result
}
class CommercialOperationRepository {
 <<DISSENY: SQL definit>>
 +createWithTwoLines(db,command) operation
}
class LegacyGroupInvoicePayloadBuilder {
 <<PHP existent: builder de GRUP>>
 +build(snapshot) array
}
class RedsysGroupInvoiceService {
 <<PHP existent: handler GRUP>>
 +issueFromIntentSnapshot(db,dsOrder,snapshot) array
}
class EnrollmentFundMovementRepository {
 <<PROPOSTA: no implementada>>
 +append(db,movement) result
}
FriendsDiscountCheckoutService --> FriendsDiscountPolicy : import A/B
FriendsDiscountCheckoutService --> CommercialOperationRepository : rols i línies
FriendsDiscountCheckoutService --> EnrollmentFundMovementRepository : atribució individual si ingrés
RedsysGroupInvoiceService --> LegacyGroupInvoicePayloadBuilder : només quan GRUP sigui la ruta correcta
```

La classe d'orquestració de la promoció és **disseny**; no se li atribueix una crida actual al handler `GRUP`.

## 4. Seqüència — dos participants i ingrés conjunt (DISSENY/PARCIAL)

```mermaid
sequenceDiagram
autonumber
actor P as Pagador
participant U as Checkout d'amics [pendent]
participant F as FriendsDiscountCheckoutService [DISSENY]
participant O as commercial_operation + lines [SQL]
participant C as Classificador fiscal [pendent]
participant Bank as Redsys
participant I as Emissor SIF [PHP, ruta segons classificació]
participant L as Ledger per inscripció [PROPOSTA]
P->>U: Comprar curs d'A i curs de B conjuntament
U->>F: prepare(A,B,pagador,regla)
F->>O: Congelar dues línies, edicions, descomptes i rols
F->>C: Definir factura conjunta o dues factures/receptors
alt Classificació incompleta
 C-->>U: Pendent; sense factura ni pagament
else Classificació resolta, oferta acceptada
 C-->>F: Factura/es i imports per línia
 U->>Bank: Pagar import conjunt congelat
 Bank-->>U: Callback signat processat UC-03
 U->>I: Emetre/reutilitzar factura/es segons circuit aprovat
 I-->>U: UUID_FACTURA per document i UUID_PAYMENT de l'ingrés únic
 U->>L: Atribuir import net A i net B del mateix ingrés
 U-->>P: Resultat de les dues inscripcions
end
Note over F,L: Aquest checkout no està acreditat; el builder GRUP sol no prova fiscalitat d'amics.
```

## 5. Traçabilitat

[UC-110 original](../06-fitxes-funcionals/uc-110.md) · [UC-107 duplicats](uc-107-detectar-inscripcio-duplicada.md) · [UC-112 snapshot](uc-112-congelar-snapshot-abans-tpv.md) · [UC-16 grup](uc-016-facturar-grup.md) · [UC-56 assignar pagament](uc-056-cercar-assignar-cobrament.md) · [LegacyGroupInvoicePayloadBuilder](../../sif/src/Service/LegacyGroupInvoicePayloadBuilder.php) · [RedsysGroupInvoiceService](../../sif/src/Service/RedsysGroupInvoiceService.php) · [Taules línies i drets comercials](../../sif/database/migrations/2026_09_16_000005_add_operation_lifecycle_tables.sql) · [Ledger individual proposat](00-revisio-moviments-inscripcions.md).
