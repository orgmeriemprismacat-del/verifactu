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

### 1.3. Registre llegat de regal, codi al document fiscal i lliurament del dret

**Compra i consulta reals del llegat.** El procediment de PrisMa identifica `regal.ID` com a origen; `buscarRegNoPayByCodi` i `buscarRegNoPayByDni` localitzen regals pendents per codi o DNI del comprador, `buscarRegalById` recupera `NOM_CURS/CCURS/NOMC/NIFC/MAILC/CODI/FACT_REL/ORIGEN/DESTI`, i `updFactRegal` desa `regal.FACT_REL`. El correu històric de compra inclou el codi i un enllaç a la targeta regal PDF. Aquesta **targeta comercial** i la factura fiscal emesa al **comprador** són documents diferents, amb destinataris i permisos diferents; `FACT_REL` és un agrupador llegat, no el substitut de `UUID_FACTURA` i del vincle posterior a l'inscripció.

**Risc concret del constructor de factura.** `LegacyGiftInvoicePayloadBuilder::build()` carrega `CODI` i el passa a `line()`, que fixa `detail = 'Codi regal ' . $code`; `giftMetadata()` torna a incloure `code` en el payload. **El codi bescanviable pot quedar exposat en el detall del document fiscal i en les traces/payloads que es conservin**, encara que només s'hagués d'enviar a qui té dret al regal. Per a noves emissions, cal decidir i implementar una política de presentació que separi el **codi operatiu íntegre**, que s'ha de lliurar de forma controlada, d'una referència comercial/fiscal que no permeti bescanviar-lo. No afirmar que el builder actual ja protegeix aquest valor ni reutilitzar l'`OBSERVACIONS` llegat com a magatzem segur. Les factures **ja emeses** no es reescriuen per ocultar-lo: revisar la distribució i, si hi ha exposició, obrir incidència amb abast documentat.

**Comprar no és lliurar ni consumir.** `LegacyGiftSnapshotRepository::loadByCode()` consulta la fila de `regal` per `CODI`; el builder emet la factura del comprador, però **no** implementa una transició atòmica `ACTIVE→CONSUMED` ni una alta acadèmica vinculada al beneficiari. Una notificació Redsys validada tampoc acredita per si sola que s'hagi creat el dret, enviat la targeta o obtingut una plaça al curs. Després de l'ingrés i la factura confirmats, la fase de lliurament ha de verificar el destinatari i l'estat del dret, i un retry de correu **no** emet una segona factura ni un nou codi de valor.

**Bescanvi i diners disponibles.** Quan el beneficiari introdueix el codi, UC-18 ha d'identificar **regal/compra/factura/pagament** originals i bloquejar el consum concurrent, crear o recuperar `ID_INSC`, comprovar plaça/curs i registrar la part aplicada. Per a un regal comprat i ingressat, el bescanvi del mateix valor és **aplicació interna del dret**, no un altre `CHARGE` de l'alumne. Si es paga una diferència externa, documentar separadament titular, import i document fiscal que correspongui. Una baixa o retorn després del bescanvi comprova comprador/pagador original i saldo no consumit: posseir el codi no acredita titularitat del reemborsament ni dret a veure el PDF del comprador.

### 1.4. Proves del codi i de la separació de documents (no executades)

| ID | Escenari | Resultat exigible |
| --- | --- | --- |
| RG-119-01 | El constructor rep `CODI` bescanviable i genera la factura | Detectar-ne presència en `detail/giftMetadata`; definir presentació no bescanviable per noves emissions, sense reescriure factures ja emeses. |
| RG-119-02 | S'ha cobrat el regal però falla el correu amb la targeta | Reprendre només lliurament del dret al destinatari autoritzat, no factura ni ingrés. |
| RG-119-03 | Beneficiari consulta PDF fiscal del comprador per posseir el codi | No concedir accés a factura per la sola possessió; targeta i factura separades. |
| RG-119-04 | Dos bescanvis simultanis del mateix codi | Un únic consum/destí acreditat o incidència; no dues places ni segon `CHARGE`. |
| RG-119-05 | Regal de 100 € i curs triat de 120 €, diferència de 20 € abonada | Origen regal i ingrés addicional traçats separadament, amb classificació fiscal prèvia. |
| RG-119-06 | Beneficiari demana devolució d'un regal pagat per una altra persona | Verificar titular econòmic, import disponible i operació original abans de decidir un reemborsament real. |

## 2. Diagrama UML de casos d'ús — actors, transicions i accions independents

**UC-119 és la coordinació de diverses operacions, no una única crida síncrona.** Comprar (UC-17), activar el dret quan el cobrament sigui vàlid, lliurar/reexpedir la targeta, bescanviar (UC-18) i resoldre un codi caducat/duplicat (UC-18a) tenen actors, dates, permisos i resultats diferents. Les fletxes entre UCs de la figura no han de suggerir que UC-18 s'executa sempre en comprar ni que reenviar la targeta crea un dret nou.

```plantuml
@startuml
left to right direction
actor "Comprador / pagador" as Buyer
actor "Destinatari / beneficiari" as Recipient
actor "Redsys" as Bank
actor "Operador autoritzat" as O
actor "Worker de notificacions" as W
rectangle "SIF PrisMa — cicle de regal" {
 usecase "UC-17\nComprar i facturar regal" as Buy
 usecase "UC-119 / activació\nCrear/activar dret després de pagament" as Activate
 usecase "UC-119 / lliurament\nEnviar targeta o codi al destinatari" as Deliver
 usecase "UC-119 / reexpedició\nReenviar targeta sense nou dret" as Resend
 usecase "UC-18\nBescanviar dret per inscripció" as Redeem
 usecase "UC-18a\nGestionar codi caducat, duplicat o disputat" as Error
 usecase "UC-71/72\nCanvi o baixa posterior" as Change
 usecase "UC-28/29\nRetorn o saldo classificat" as Money
}
Buyer --> Buy
Bank --> Buy
W --> Activate
W --> Deliver
O --> Resend
Recipient --> Redeem
O --> Error
O --> Change
O --> Money
note right of Buy
 No pressuposa bescanvi posterior.
 Una compra denegada no activa dret pagat.
end note
note right of Resend
 Recupera el mateix dret/codi vàlid.
 Cap segona factura o CHARGE.
end note
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
 +deliverGift(uuidEntitlement,recipient,requestId) result
 +resendGift(uuidEntitlement,recipient,requestId) result
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

## 6. Activació i comunicació del regal — accions diferenciades

### 6.1. Acció pròpia: activar el dret només després d'una compra confirmada — DISSENY

**Actor/disparador:** worker autoritzat rep una confirmació **validada** de cobrament de la compra; el comprador no pot activar un dret de valor pagat manualment sense evidència. **Precondicions:** `DS_ORDER` validada, import de Redsys igual al regal, UUID de la compra/factura/pagament persistit i estat no retornat, identificador únic de regal. `RedsysGiftInvoiceService::issueSnapshot()` comprova la notificació i l'import i delega `issueInvoice()`, però **no crea ni activa `commercial_entitlement`**. **Postcondició objectiu:** únic dret amb origen i valor disponibles, `CODE_HASH` i event de `ACTIVATE`; cap matrícula, lliurament o segon `CHARGE` per activar-lo.

```mermaid
sequenceDiagram
autonumber
participant TPV as RedsysCallbackWorker/UC-03 [PHP]
participant Gift as RedsysGiftInvoiceService [PHP]
participant Invoice as InvoiceService [PHP]
participant Life as GiftLifecycleCoordinator [DISSENY]
participant Ent as CommercialEntitlementRepository [DISSENY]
participant DB as BD fiscal i dret
TPV->>Gift: issueFromIntentSnapshot(dsOrder,snapshot) amb notificació VALIDATED
Gift->>Invoice: issueInvoice(payload de REGAL)
Invoice->>DB: COMMIT factura i operació de compra segons contracte comú
Invoice-->>Gift: UUID_FACTURA i resultat de compra
Gift-->>Life: Resultat confirmat i identificador regal [integració pendent]
Life->>Ent: Bloquejar origen de regal, validar una sola activació
alt Activació prèvia del mateix regal i compra
 Ent-->>Life: UUID_ENTITLEMENT existent, cap dret nou
else Compra sense pagament confirmat o compra retornada
 Ent-->>Life: Bloquejar activació de valor no disponible
else Compra i pagament verificats, cap dret previ
 Ent->>DB: INSERT dret ACTIVE + event ACTIVATE amb origen i hash [OBJECTIU]
 Ent-->>Life: UUID_ENTITLEMENT
end
Note over Gift,Ent: L'activació i el writer d'entitlement no consten com a implementats. No derivar dret pagat del fet que el codi existeixi al llegat.
```

### 6.2. Acció pròpia: lliurar la targeta/codi després d'activar el dret — DISSENY

**Actor/disparador:** procés de notificacions, quan existeix un dret activat i una destinació de lliurament legitimada; el destinatari pot ser diferent del comprador. **Dades:** identificador opac de dret, canal, identitat/destinació validada, identificador de notificació i URL o targeta comercial amb permisos. **Resultat:** lliurament o estat pendent/error auditable; **no** nova emissió de factura, activació de valor, inscripció ni cobrament. El correu antic inclou el codi i l'enllaç a la targeta; això no acredita un outbox del nou SIF ni l'entrega final al destinatari.

```plantuml
@startuml
left to right direction
actor "Worker de notificacions" as W
actor "Gestió autoritzada" as O
rectangle "SIF PrisMa — lliurament de regal (OBJECTIU)" {
 usecase "UC-119 / LLIURAMENT\nEnviar targeta comercial de dret actiu" as Send
 usecase "Validar destinatari i canal" as Validate
 usecase "Encolar notificació amb identificador únic" as Queue
 usecase "UC-119 / REEXPEDICIÓ\nReintentar lliurament fallit" as Resend
 usecase "Registrar resultat de notificació" as Audit
}
W --> Send
O --> Resend
Send ..> Validate : <<include>>
Send ..> Queue : <<include>>
Send ..> Audit : <<include>>
Resend ..> Validate : <<include>>
Resend ..> Audit : <<include>>
note bottom of Resend
 No crea una compra, factura ni dret nou.
 Recupera la mateixa operació comercial.
end note
@enduml
```

```mermaid
sequenceDiagram
autonumber
actor W as Worker/gestió
participant Life as Coordinador regal [DISSENY]
participant Ent as Dret comercial [SQL, writer PENDENT]
participant Out as Outbox/notificacions [DISSENY]
participant Mail as Canal d'entrega
W->>Life: deliverGift(uuidEntitlement,recipient,requestId)
Life->>Ent: Consultar estat, titular/destinatari i valor
alt Dret no activat, consumit sense permís o destinatari contradictori
 Ent-->>Life: Bloqueig de lliurament
 Life-->>W: Denegació o incidència, cap correu amb codi
else Dret actiu i destinació legitimada
 Ent-->>Life: Identificador del mateix dret
 Life->>Out: Encolar notificació idempotent per dret + destinatari + versió
 Out->>Mail: Enviar targeta/codi per canal segur
 alt Fallada o resultat incert de transport
  Mail-->>Out: ERROR/UNKNOWN
  Out-->>W: Pendent d'investigar/reintentar mateixa notificació
 else Proveïdor confirma acceptació
  Mail-->>Out: Identificador de lliurament/acceptació del proveïdor
  Out-->>W: Enviament acceptat, lliurament efectiu al destinatari no deduïble automàticament
 end
end
Note over Life,Out: Aquest outbox i la comprovació del destinatari són disseny pendent, no fer aparèixer el codi en factura fiscal ni logs de notificació.
```

### 6.3. Acció pròpia: reenviar després de fallada, sense recomprar ni regenerar — DISSENY

**Actor/disparador:** gestió tracta un enviament que ha fallat o una petició legitimada de reexpedició. **Precondicions:** dret i comprador originals identificats, codi encara utilitzable o condició de reexpedició aprovada, adreça de destí verificada, consulta d'enviaments anteriors. **Postcondició:** mateixa operació i dret comercial; una nova prova de notificació o reintent d'una ja iniciada, mai un segon document fiscal/cobrament ni un nou dret econòmic. Enviar un codi vençut com si continués actiu seria una informació incorrecta; derivar a UC-18a quan correspongui.

```mermaid
sequenceDiagram
autonumber
actor O as Operador de gestió
participant UI as Panell de regals [PENDENT]
participant Life as GiftLifecycleCoordinator [DISSENY]
participant Ent as Dret i compra d'origen [DISSENY/LECTURA]
participant Out as Notification outbox [DISSENY]
participant Mail as Canal de notificació
O->>UI: Reenviar targeta per incidència d'entrega
UI->>Life: resendGift(uuidEntitlement,recipient,requestId) [mètode proposat]
Life->>Ent: Llegir compra confirmada, estat dret i destinació
alt Destinatari sense permís o codi vençut/cancel·lat
 Ent-->>Life: DENIED o EXPIRED
 Life-->>UI: Denegar reexpedició, derivar UC-18a si cal
else Dret vàlid i mateix origen
 Life->>Out: Recuperar notificació i validar nou destí autoritzat
 alt Ja enviat i petició és reintent equivalent
  Out-->>UI: Reutilitzar resultat/estat anterior sense missatge duplicat
 else Es justifica un nou enviament
  Out->>Mail: Reenviar la mateixa targeta/dret sense nova compra
  Mail-->>Out: Resultat/estat d'intent
  Out-->>UI: Nova evidència d'enviament del dret original
 end
end
UI-->>O: Estat de la comunicació, factura i UUID_PAYMENT originals intactes
Note over Life,Mail: El reenviament mai no ha de cridar InvoiceService::issueInvoice() ni PaymentService::registerPayment().
```

### 6.4. Contrast del codi exposat i proves específiques de les tres accions

`LegacyGiftInvoicePayloadBuilder::build()` construeix la clau de factura `LEGACY|REGAL|ID:<giftId>`, posa el `CODI` íntegre a `lines[].detail` i a `gift.code`, i pren com a receptor fiscal el comprador (`NOMC/NIFC`). `RedsysGiftInvoiceService` contrasta import de notificació validada amb import del regal i delega l'emissió; **no acredita un registre de drets, sistema de lliurament de codi ni comprovació de reutilització semàntica de la mateixa compra**. El codi bescanviable i el PDF fiscal han de tenir visibilitat independent, especialment quan destinatari i pagador són diferents. Les factures fiscals ja emeses no s'han de reescriure per amagar-hi el codi; cal decidir l'actuació sobre exposicions confirmades mitjançant incidència.

| Prova pendent | Escenari | Resultat que cal acreditar |
| --- | --- | --- |
| RG-119-06 | Intent de compra Redsys denegat però regal present al llegat | Cap entitlement de valor pagat activat ni `CHARGE` de compra. |
| RG-119-07 | Callback duplicat després d'activació | Mateix UUID_FACTURA/UUID_PAYMENT/UUID_ENTITLEMENT, un únic origen monetari i cap activació de valor addicional. |
| RG-119-08 | Comprador i destinatari diferents; correu de targeta enviat al destinatari | Accés al dret comercial sense accés implícit al PDF fiscal del comprador. |
| RG-119-09 | Fallada de correu després de compra i activació | Reintentar només notificació, sense segon `issueInvoice()`, codi nou ni `CHARGE`. |
| RG-119-10 | Sol·licitud de reexpedició amb codi consumit/expirat | Política d'accés i expiració comprovada; no prometre regal actiu ni reactivar-lo tàcitament. |
| RG-119-11 | `CODI` bescanviable apareix a factura fiscal emesa | Identificar exposició i limitar futures emissions segons decisió; document fiscal anterior immutable, sense usar el codi sol com a dret a PDF/retorn. |
## 7. Traçabilitat

[UC-119 original](../06-fitxes-funcionals/uc-119.md) · [UC-17 compra](uc-017-comprar-regal.md) · [UC-18 bescanvi](uc-018-bescanviar-regal.md) · [UC-18a incidències](uc-018a-regal-caducat-duplicat.md) · [Model de fons per inscripció](00-revisio-moviments-inscripcions.md) · [Taula commercial_operation](../../sif/database/migrations/2026_09_16_000004_add_commercial_operation_and_fiscal_fields.sql) · [Taules operació/dret/event](../../sif/database/migrations/2026_09_16_000005_add_operation_lifecycle_tables.sql) · [RedsysGiftInvoiceService](../../sif/src/Service/RedsysGiftInvoiceService.php).

**Proves no executades; el cicle integral, permisos, writer de drets, conciliació entre BDs i ledger quantitatiu continuen pendents.**
