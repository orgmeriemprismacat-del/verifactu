# UC-119 · Cicle complet d'un regal o codi de bescanvi

**Objectiu:** vincular la compra d'un regal, la factura del comprador, el pagament real, la titularitat del dret, el lliurament del codi, el bescanvi per una inscripció i qualsevol expiració/canvi/devolució **sense comptar dues vegades el mateix diner**. Aquesta és la coordinació de UC-17 (compra), UC-18 (bescanvi) i UC-18a (excepcions); no els substitueix.

**Estat contrastat:** compra i facturació parcialment implementades per `RedsysGiftInvoiceService`, `LegacyGiftInvoicePayloadBuilder` i `InvoiceService`. Les migracions defineixen `commercial_operation`, `commercial_operation_party`, `commercial_operation_line`, `operation_line_invoice_link`, `commercial_entitlement` i `commercial_entitlement_event`. **No s'ha identificat al PHP SIF un orquestrador d'extrem a extrem, un writer complet de drets/consums o una conciliació de fons de regal cap a inscripció.** Els components de coordinació representats aquí són **DISSENY**, encara que la BD ja tingui taules.

## 1. Fitxa del cas

| Element | Contracte i separació de responsabilitats |
| --- | --- |
| Actors | Comprador i pagador del regal, beneficiari/tenidor del codi, operador autoritzat, Redsys o banc. Poden ser persones diferents; no deduir el propietari del valor del nom de la persona inscrita. |
| Operació d'origen | `commercial_operation` amb `UUID_OPERATION`, tipus `GIFT` o classificació documentada, receptor/participants a `commercial_operation_party`, preu/versionat i UUID de factura/pagament quan existeixin. **La presència de les columnes no acredita un writer que les pobli avui.** |
| Factura de compra | UC-17: factura vinculada al regal i comprador/receptor fiscal, amb una entrada `CHARGE` només si el TPV ha confirmat un cobrament. El codi regal no substitueix el `UUID_PAYMENT` real. |
| Dret comercial | `commercial_entitlement` definit a BD amb `ENTITLEMENT_TYPE=GIFT`, `CODE_HASH`, `HOLDER_PARTY_KEY`, `ORIGIN_UUID_OPERATION`, `CONSUMED_UUID_OPERATION`, `RULE_VERSION`, valor, moneda, caducitat i estat. |
| Historial del dret | `commercial_entitlement_event` amb accions `ISSUE`, `ACTIVATE`, `VALIDATE`, `RESERVE`, `RELEASE`, `CONSUME`, `EXPIRE`, `CANCEL`, `REVERSE` i denegacions, segons diccionari; el SQL és contracte de dades, **no prova d'execució**. |
| Bescanvi | UC-18 crea o vincula una inscripció i marca el dret consumit **una sola vegada**. Per defecte, no crea segona factura/cobrament pel valor del regal ja facturat i ingressat; les diferències exigeixen classificació pròpia. |
| Efecte monetari | En el ledger objectiu: ingrés extern real → dret REGAL en UC-17, després dret REGAL → inscripció a UC-18. És **una entrada de caixa i una aplicació interna del seu valor**, no dos `CHARGE`. |
| Resultat final | Historial que permet seguir operació de compra → factura/pagament → dret/tenidor → inscripció beneficiària → saldo/retorn/rectificativa quan correspongui, amb UUIDs i imports per tram. |

### 1.1. Flux funcional complet objectiu

1. El comprador confirma producte/edició de regal, dades fiscals, destinatari i import; s'identifica l'operació comercial i es congela el snapshot abans del TPV (UC-63). El preu esperat i el dret a emetre un codi no són un ingrés bancari.
2. Redsys confirma el pagament signat, UC-03 processa el job i UC-17 emet/reutilitza la factura al comprador i registra l'únic `payment_transaction CHARGE`. Si el cobrament és denegat, **no** activar un dret de valor pagat.
3. El sistema objectiu emet/activa el dret `GIFT` vinculant la compra i l'origen del valor a `UUID_PAYMENT`; guarda el **hash** del codi en comptes de publicar-lo en logs/URLs. El lliurament al destinatari és una fase posterior amb permisos/canal i prova de lliurament propis.
4. El beneficiari demana bescanvi (UC-18); es valida titular, codi, vigència, estat i valor disponible. Es bloqueja/reserva dret i plaça, es crea o vincula inscripció i es marca `CONSUMED` amb `CONSUMED_UUID_OPERATION` i historial. **Cal conciliació entre dues BDs si la inscripció és al llegat: no fingir commit distribuït.**
5. Es registra el traspàs intern **`REGAL → ID_INSC`** per la quantitat aplicada, referenciant el pagament de compra UC-17. Si s'ha de pagar una diferència real, es crea **un cobrament addicional diferenciat** només quan sigui confirmat; no s'incrementa falsament l'import de la compra original.
6. Quan el valor no s'aplica totalment, es conserva saldo/dret residual o es classifica devolució conforme a condicions, titularitat, import cobrat i tractament fiscal; una diferència de preu no és automàticament un descompte ni una rectificativa.
7. Si el regal caduca, es disputa o es torna a usar, UC-18a bloqueja l'aplicació i obre incidència quan correspongui; el cas es resol sense alterar silenciosament la factura o crear una segona matrícula.
8. Un canvi/baixa després del bescanvi consulta **factura i pagament de compra, dret consumit, inscripció de destí i titular del retorn**; deriva a UC-71/72 i, si cal, UC-05/28/29. No retorna directament diners al beneficiari per defecte si els va pagar un altre titular.

### 1.2. Invariants i variants de prova

| Escenari | Invariant exigida |
| --- | --- |
| Compra denegada i codi generat prematurament | Cap dret de valor pagat utilitzable, cap `CHARGE`, matrícula o atribució fictícia. |
| Callback/worker repetits | Una compra, una factura/ingrés real i un dret d'origen reutilitzat; cap segona emissió per reintentar una notificació equivalent. |
| Codi bescanviat dues vegades | Un sol `CONSUMED_UUID_OPERATION`; segona petició equivalent recupera la inscripció anterior o es rebutja si és contradictòria. |
| Lliurament a persona diferent del comprador | La recepció del codi no atorga per si sola accés a les dades fiscals del comprador ni al seu PDF. |
| Bescanvi sense plaça o fallada del llegat | Reservar/revertir o mantenir incidència; mai deixar dret consumit sense destinació reconciliable. |
| Regal nominal de 100 €, compra ingressada 100 € i curs de 100 € | `CHARGE` extern únic de 100 €, transferència del dret a inscripció 100 €, segon `CHARGE` de 0 € **no creat**. |
| Regal de 100 €, curs de 120 € i diferència ingressada de 20 € | Ingrés original 100 € + ingrés extern addicional verificat 20 €; dues procedències diferenciades atribuïdes a una inscripció, amb classificació fiscal del diferencial. |
| Regal de 100 €, curs de 80 € | Aplicació 80 €; 20 € restants subjectes a regla de dret/saldo/retorn i a qui és titular, sense crear un `REFUND` bancari fins a fer-lo efectiu. |
| Regal retornat després de bescanvi | Qualsevol devolució ha de verificar import disponible per dret/inscripció i pagador original, i correspondre a un reemborsament real. |
| Regal caducat | Estat `EXPIRED` i decisió operativa/contractual; no inventar automàticament un nou ingrés o anul·lació registral. |

## 2. Diagrama UML de casos d'ús — cicle complet

```plantuml
@startuml
left to right direction
actor "Comprador / pagador" as Buyer
actor "Destinatari / beneficiari" as Recipient
actor "Redsys" as Bank
actor "Operador de gestió" as O
rectangle "SIF · cicle de regal" {
 usecase "UC-119\nGestionar cicle del regal" as Main
 usecase "UC-17\nComprar i facturar regal" as Buy
 usecase "Crear i lliurar dret de regal" as Issue
 usecase "UC-18\nBescanviar dret per inscripció" as Redeem
 usecase "UC-18a\nGestionar caducitat/duplicat" as Error
 usecase "UC-71/72\nCanvi o baixa posterior" as Change
 usecase "UC-28/29\nRetorn o saldo classificat" as Money
}
Buyer --> Buy
Bank --> Buy
Recipient --> Redeem
O --> Error
O --> Change
O --> Money
Main ..> Buy : <<include>> (compra)
Main ..> Issue : <<include>> (cobrament confirmat)
Main ..> Redeem : <<include>> (fase futura)
@enduml
```

## 3. Classes del cicle — codi real i orquestració pendent

```mermaid
classDiagram
direction LR
class RedsysGiftInvoiceService {
 <<PHP existent: compra>>
 +issueFromIntentSnapshot(db,dsOrder,snapshot) array
}
class LegacyGiftInvoicePayloadBuilder {
 <<PHP existent: factura de compra>>
 +build(snapshot) array
}
class InvoiceService {
 <<PHP existent>>
 +issueInvoice(payload) array
}
class GiftLifecycleCoordinator {
 <<DISSENY: no acreditat>>
 +activatePaidGift(command) result
 +redeemGift(command) result
 +reconcileGift(uuidOperation) result
}
class CommercialOperationRepository {
 <<DISSENY: SQL definit, writer no acreditat>>
 +findByUuid(db,uuid) operation
}
class CommercialEntitlementRepository {
 <<DISSENY: SQL definit, writer no acreditat>>
 +lockByCodeHash(db,hash) entitlement
 +consume(db,id,uuidOperation) result
 +appendEvent(db,event) result
}
class EnrollmentGateway {
 <<DISSENY: llegat>>
 +createOrLinkEnrollment(command) result
}
class EnrollmentFundMovementRepository {
 <<PROPOSTA: no implementada>>
 +append(db,movement) result
}
RedsysGiftInvoiceService --> LegacyGiftInvoicePayloadBuilder : payload compra
RedsysGiftInvoiceService --> InvoiceService : factura i CHARGE de compra
GiftLifecycleCoordinator --> CommercialOperationRepository : operació comercial
GiftLifecycleCoordinator --> CommercialEntitlementRepository : dret i events
GiftLifecycleCoordinator --> EnrollmentGateway : bescanvi
GiftLifecycleCoordinator --> EnrollmentFundMovementRepository : aplicació valor
```

## 4. Seqüència completa — compra ingressada i bescanvi posterior

```mermaid
sequenceDiagram
autonumber
actor B as Comprador
actor R as Destinatari
participant TPV as Redsys/UC-03
participant Gift as RedsysGiftInvoiceService [COMPRA PHP]
participant Invoice as InvoiceService [PHP]
participant Life as GiftLifecycleCoordinator [DISSENY]
participant Ent as CommercialEntitlementRepository [DISSENY]
participant Enrol as Inscripcions llegades [integració pendent]
participant Funds as Ledger per inscripció [PROPOSTA]
B->>TPV: Comprar regal i pagar
TPV->>Gift: Callback validat i job processat
Gift->>Invoice: issueInvoice(payload REGAL + CHARGE)
Invoice-->>Gift: UUID_FACTURA i UUID_PAYMENT compra
Gift-->>Life: Resultat compra [integració futura]
Life->>Ent: Crear/activar dret GIFT amb compra original
Ent-->>Life: UUID_ENTITLEMENT, CODE_HASH i valor disponible
Life-->>R: Lliurar dret per canal segur [pendent]
Note over Invoice,Funds: Una sola entrada de diners real de la compra
R->>Life: Bescanviar codi per curs/edició
Life->>Ent: Validar titular/estat i reservar dret
alt Dret invàlid o consumit
 Ent-->>Life: Rebuig o resultat idempotent anterior
 Life-->>R: Sense nova inscripció ni CHARGE
else Dret vàlid
 Life->>Enrol: Crear/vincular inscripció idempotent
 Enrol-->>Life: ID_INSC
 Life->>Funds: append(REGAL→ID_INSC,importAplicat,UUID_PAYMENT compra)
 Life->>Ent: consume(UUID_ENTITLEMENT,operació bescanvi) i event
 Life-->>R: Bescanvi complet
end
Note over Life,Funds: El flux després de la compra és DISSENY, no codi executable verificat.
```

## 5. Seqüència alternativa — regal inferior al curs (DISSENY)

```mermaid
sequenceDiagram
actor R as Destinatari
participant Life as GiftLifecycleCoordinator [DISSENY]
participant Ent as Dret GIFT [DISSENY]
participant Payment as Canal pagament addicional [UC-02/03]
participant Funds as Ledger [PROPOSTA]
R->>Life: Bescanviar regal valor 100 € en curs de 120 €
Life->>Ent: Reservar valor 100 € amb traça
Life-->>R: Diferència real pendent de 20 €
R->>Payment: Pagar 20 € com a nova operació identificada
alt Pagament addicional denegat
 Payment-->>Life: Sense cobrament confirmat
 Life->>Ent: Alliberar reserva o mantenir pendent reconciliable
 Life-->>R: No declarar bescanvi complet
else Pagament addicional confirmat
 Payment-->>Life: UUID_PAYMENT_ADD i 20 € cobrats
 Life->>Funds: Aplicar REGAL→ID_INSC 100 € vinculats a compra original
 Life->>Funds: Atribuir EXTERNAL→ID_INSC 20 € vinculats a pagament nou
 Life->>Ent: CONSUME dret una sola vegada
 Life-->>R: Aplicació total 120 € amb dues procedències
end
```

## 6. Traçabilitat

[UC-119 original](../06-fitxes-funcionals/uc-119.md) · [UC-17 compra](uc-017-comprar-regal.md) · [UC-18 bescanvi](uc-018-bescanviar-regal.md) · [UC-18a incidències](uc-018a-regal-caducat-duplicat.md) · [Model de fons per inscripció](00-revisio-moviments-inscripcions.md) · [Taula commercial_operation](../../sif/database/migrations/2026_09_16_000004_add_commercial_operation_and_fiscal_fields.sql) · [Taules operació/dret/event](../../sif/database/migrations/2026_09_16_000005_add_operation_lifecycle_tables.sql) · [RedsysGiftInvoiceService](../../sif/src/Service/RedsysGiftInvoiceService.php).

**Proves no executades; el cicle integral, permisos, writer de drets, conciliació entre BDs i ledger quantitatiu continuen pendents.**
