# UC-100 · Registrar una operació informativa o no facturable

**Objectiu original:** documentar un **event auditable** amb motiu i classificació `NONE`; no crear factura ni pagament. **Estat [DISSENY/PARCIAL].** Existeix el repositori PHP que escriu events operatius, però no s'ha acreditat un classificador ni un controlador que separin una acció merament informativa d'una venda, un ingrés real o una correcció fiscal.

## Evidència real i problema d'interpretació

`OperationalEventRepository::append(PDO,array)` comprova camps obligatoris (`operation_type, source_type, fiscal_impact, economic_impact, status, reason_code, actor_type, source_channel, correlation_id, occurred_at`) i desa a `operational_event` snapshots amb hash SHA-256, actor, `UUID_FACTURA/UUID_PAYMENT` **opcionals** i resultat. **No impedeix per si sol que un client digui `FISCAL_IMPACT=NONE` sobre una operació que realment és una venda**, ni proporciona servei de deduplicació de peticions. `InvoiceRepository::createInvoiceGraph()` és un altre circuit: genera factura, línies, registre encadenat i cua AEAT. UC-100 **no l'ha de cridar** quan la classificació informativa està acreditada.

| Fet concret | Classificació i decisió |
| --- | --- |
| Nota de seguiment d'una consulta de curs | Registrar que s'ha atès la consulta, subjecte/actor, instant i propòsit, sense inventar matrícula ni deute. `fiscal_impact=NONE` i `economic_impact=NONE` són valors **objectiu del cas**, no prova que qualsevol petició sigui no facturable. |
| Reserva/sol·licitud **sense** oferta acceptada ni ingrés | Anotar estat i caducitat de l'operació informativa; si després es confirma una venda, crear **un event nou derivat** i executar el circuit fiscal corresponent. No mutar el registre informatiu perquè «es converteixi» en factura emesa. |
| Col·laborador presenta un rebut | UC-65 és recepció de document de **proveïdor**, no factura de venda de cursos ni `CHARGE`. L'event informatiu pot referenciar el document sense reemetre'l. |
| Comprovant/transferència **real** descobert en una operació aparentment informativa | Reclassificar el fet per servei/emissor/receptor, preservar l'event anterior com a antecedent i derivar a UC-02/04/74. No deixar el cobrament real fora de conciliació per una etiqueta `NONE`. |
| Error de contacte o canvis acadèmics | Registrar el motiu si no hi ha efecte fiscal/econòmic, però aplicar UC-120/129 quan hi ha propagació real; un `operational_event` no prova que Moodle s'hagi actualitzat. |

### Flux funcional

1. L'operador autoritzat proposa `SOURCE_TYPE/SOURCE_ID`, acció, actor, motiu i snapshot **mínim**. El classificador **pendent** consulta si existeix factura, ingrés extern, acceptació d'oferta o dret acadèmic afectat.
2. Si la petició conté un fet fiscal o diner real, **no** cridar UC-100 com a camí abreujat; derivar a la comanda del cas corresponent, conservant la correlació.
3. Amb `NONE/NONE` motivat, crear event via `OperationalEventRepository::append()` amb autorització i `REQUEST_ID` de negoci. El PHP actual calcula hashes però **no imposa una clau idempotent pròpia**: servei pendent evita dues anotacions contradictòries per la mateixa comanda.
4. Presentar el resultat com a **registre informatiu**; cap `UUID_FACTURA`, numeració fiscal, `payment_transaction`, `payment_allocation`, `fiscal_queue` ni certificat acadèmic.
5. Si més tard hi ha venda/ingrés o incidència, afegir un nou event correlacionat i executar efecte real sense destruir l'històric de la petició.

**Proves:** operador intenta etiquetar una transferència de curs com a «informativa», consulta sense oferta, rebut de tutor, dues peticions amb mateixa clau i contingut divers, canvi acadèmic pendent de Moodle, event amb dades personals excessives i reintent després de perdre resposta.

**Pendents:** classificador/autorització del canal, idempotència d'`operational_event`, política de minimització de snapshots, relació amb oferta/operació i proves de derivació a facturació real.

### «No facturable» no és sinònim d'«import zero» ni de «pendent»

**Tres entrades reals amb abast diferent.** L'inventari `33-casos-us-sif.md` identifica `web-actual/ajax/enviarInscripcioTastet.php` com a alta **gratuïta a `inscripcions_reptes` sense cobrament**, mentre que `enviarInscripcio.php` crea inscripcions ordinàries **abans de pagar** i diferencia `tipusCurs='S'` per a cursos subvencionats. El primer pot correspondre a l'alta gratuïta UC-108; el segon **no** queda classificat `NONE` perquè `PAGAMENT=0` o `A_PAGAR=0` en un moment concret: pot haver-hi factura prèvia real, deute de l'empresa o finançament pendent de classificar per UC-109. La nota d'un contacte que només demana informació, una inscripció gratuïta, una factura abans de cobrar i una matrícula subvencionada són fets diferents malgrat que **cap** hagi generat encara un cobrament.

**Event, registre acadèmic i consentiment.** `OperationalEventRepository::append()` desa un event amb `OPERATION_TYPE/SOURCE_TYPE/SOURCE_ID`, impactes, motiu, actor i snapshots; **no** crea per si mateix `inscripcions_reptes`, ni concedeix plaça, ni prova accés Moodle. UC-108/113/129 ha de tramitar i verificar l'alta al sistema corresponent sense facturar-la per error. Un `CORREU` dins l'event o un avís d'accés al tastet **no** són confirmació de mailing comercial: UC-125 exigeix una decisió pròpia de subjecte/finalitat/canal, no copiar automàticament `mailing=sí` d'un registre informatiu.

**Una operació inicialment informativa pot tenir un ingrés posterior.** Si la petició es converteix en compra real, generar **una nova operació comercial traçada**, amb oferta acceptada, receptor, prestació, factura prèvia quan correspongui i registre de `CHARGE` només si hi ha entrada externa efectiva. No modificar l'event `NONE/NONE` inicial per fer semblar que ja era una factura. Si apareix una transferència real que no es pot relacionar encara amb un `ID_INSC`, registrar-la/reconciliar-la amb UC-56/82 i conservar la incidència: el `NONE` de la consulta d'origen no autoritza ignorar diners externs.

**Minimització i reús de la mateixa petició.** El repositori calcula un hash SHA-256 dels snapshots JSON, però no decideix **quins camps personals cal desar**, ni valida una clau de negoci que diferenciï una repetició equivalent d'un canvi de contingut. El controlador objectiu ha de conservar només les dades necessàries, identitat/rol d'actor, causa, font, referència comercial i resultat per fase; rebutjar el mateix identificador de petició amb impacte fiscal/econòmic contradictori. Cap línia de `operational_event` per si sola prova que s'hagi executat una baixa acadèmica, una devolució o una notificació.

### Proves d'operació gratuïta, pendent i informativa (no executades)

| ID | Escenari | Resultat exigible |
| --- | --- | --- |
| NF-100-01 | Tastet registrat a `inscripcions_reptes` sense preu ni cobrament | Alta gratuïta i accés segons política; cap factura/CHARGE només per aquest event. |
| NF-100-02 | Inscripció a curs ordinari amb `PAGAMENT=0` i factura prèvia emesa | Factura real pendent; no reclassificar-la com a no facturable per saldo zero ingressat. |
| NF-100-03 | Curs `tipusCurs='S'` amb quota individual zero i finançador pendent | UC-109 decideix el tractament real; no FREE_SAMPLE per defecte. |
| NF-100-04 | Consulta informativa i posterior venda telefònica acceptada | Event inicial intacte i nova operació UC-92; cobrament només quan real. |
| NF-100-05 | Email de confirmació de tastet amb mailing no acceptat | Avís operatiu sense subscripció comercial inventada. |
| NF-100-06 | Repetir `REQUEST_ID` amb impacte `NONE` i després `FISCAL` incompatible | Conflicte de decisió i historial, no reús silenciós del mateix event. |

## UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Operador autoritzat" as O
rectangle "SIF · traça d'acció no facturable" {
 usecase "UC-100\nRegistrar event informatiu" as Main
 usecase "Classificar impacte fiscal i econòmic" as Classify
 usecase "Escriure event amb actor/motiu/snapshot" as Append
 usecase "Derivar a emissió o cobrament real" as Derive
 usecase "Consultar resultat informatiu" as View
}
O --> Main
O --> View
Main ..> Classify : <<include>>
Main ..> Append : <<include>> (NONE/NONE acreditats)
Derive ..> Main : <<extend>> (fet amb impacte)
@enduml
```

## UML de classes

```mermaid
classDiagram
class NonBillableOperationService {
 <<DISSENY: control de negoci pendent>>
 +classify(request) decision
 +record(request,actor) event
}
class OperationalEventRepository {
 <<PHP existent: writer genèric>>
 +append(db,event) string
}
class CommercialOperationClassifier {
 <<DISSENY: fiscal/econòmic per servei>>
 +classify(request) decision
}
class InvoiceService {
 <<PHP existent: només quan hi ha venda facturable>>
 +issueInvoice(payload) array
}
NonBillableOperationService --> CommercialOperationClassifier : impedir NONE fals
NonBillableOperationService --> OperationalEventRepository : event immutable
NonBillableOperationService ..> InvoiceService : només altra comanda posterior
```

## UML de seqüència — nota informativa que després es converteix en venda

```mermaid
sequenceDiagram
actor O as Operador
participant S as NonBillableOperationService [DISSENY]
participant C as CommercialOperationClassifier [DISSENY]
participant E as OperationalEventRepository [PHP]
participant I as InvoiceService [PHP]
O->>S: Registrar consulta de curs sense compra ni pagament
S->>C: Classificar acceptació, factura i diners reals
alt Existeix transferència acreditada o venda confirmada
 C-->>S: Impacte fiscal/econòmic no NONE
 S-->>O: Cal seguir flux de venda/cobrament, no event únic informatiu
else Consulta efectivament informativa
 C-->>S: NONE/NONE amb motiu
 S->>E: append(actor,motiu,hash snapshot) [integració pendent]
 E-->>S: UUID_OPERATIONAL_EVENT
 S-->>O: Consulta registrada, sense factura ni CHARGE
 opt Posteriorment s'accepta una oferta
  O->>I: issueInvoice(payload autoritzat en una altra comanda)
  I-->>O: UUID_FACTURA originalment inexistent
 end
end
Note over S,E: El repositori genèric no decideix si el fet és realment no facturable.
```

## Traçabilitat

[UC-100 original](../06-fitxes-funcionals/uc-100.md) · [UC-65 rebut col·laborador](uc-065-presentar-factura-rebut-collaborador.md) · [UC-94 preu](uc-094-ajustar-manualment-import-pagar-justificacio.md) · [UC-92 venda](uc-092-registrar-venda-manual-intranet-telefon.md) · [UC-74 classificació](uc-074-classificar-correccio-fiscal.md) · [OperationalEventRepository](../../sif/src/Repository/OperationalEventRepository.php) · [InvoiceService](../../sif/src/Service/InvoiceService.php) · [Migració operational_event](../../sif/database/migrations/2026_09_15_000003_add_functional_audit_control.sql).
