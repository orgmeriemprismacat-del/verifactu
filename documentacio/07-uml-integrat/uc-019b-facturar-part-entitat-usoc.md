# UC-19b · Facturar la part corresponent a l'entitat USOC — fitxa i UML integrats

**Objectiu:** emetre **una factura diferenciada a l'entitat**, per la part de finançament que li correspon en una inscripció USOC. El nucli actual **no registra el cobrament de l'entitat en emetre-la**. Relacions: UC-19a (factura i CHARGE de l'alumne), UC-13 (expedient conjunt), UC-02/22 (cobrament posterior de la factura entitat), UC-71/72 (canvi o baixa que afecti les dues parts).

**Estat:** `UsocEntityInvoiceService::issueEntityFromExplicitInput()`, `LegacyUsocSnapshotRepository`, `LegacyUsocInvoicePayloadBuilder::buildEntityPayload()` i `InvoiceService` existeixen; la pantalla, els permisos, la decisió exacta del finançador, el document i l'assignació de diners per inscripció continuen pendents d'acreditar. No es declara cap prova executada en aquesta revisió.

## 1. Fitxa funcional específica

| Camp | Regla verificada o contracte pendent |
| --- | --- |
| Actors | Operador autoritzat prepara factura; entitat rep factura i paga posteriorment segons circuit acordat. |
| Disparador | Existeix l'import de l'entitat identificat en la compra USOC i es disposa de la factura de la part alumne, amb `student_invoice_uuid`. |
| Entrada del servei | `idpag` positiu, `student_amount > 0`, `amount > 0` de l'entitat, `student_invoice_uuid` no buit i objecte `billing` amb `name` i `nif` no buits. |
| Comprovació de l'inscrit | `LegacyUsocSnapshotRepository::loadByIdpag()` requereix inscripció USOC existent amb `TIPUS_DESC=4` i `VALID_DESC=1`; recupera curs i snapshot. |
| Construcció fiscal | `LegacyUsocInvoicePayloadBuilder::buildEntityPayload()` emet una línia «Diferencia USOC» vinculada a la mateixa inscripció `source_type=INSCRIPCIO`, amb receptor `billing` de l'entitat, `source_type=USOC_ENTITAT` i `source_channel=INTRANET`. |
| Idempotència base | Clau `INTRANET|USOC_ENTITAT|ID_INSC:<inscripció>|FACT_ALUMNE:<student_invoice_uuid>`. Reintents amb imports/receptor diferents però mateixa clau exigeixen **comparació del contingut i resolució d'incidència**, no una reutilització silenciosa. |
| Visibilitat | La relació entitat és `visible_alumne=0`; el control d'accés real al document encara ha de ser validat pel canal UC-07. |
| Efecte monetari inicial | **Cap:** el payload entitat no porta bloc `payment`; el servei retorna `payment_registered=false`. La factura entitat és una obligació pendent, no fons ja atribuïts a la inscripció. |
| Resultat | UUID i número de factura entitat, `student_invoice_uuid` associat; el seu estat fiscal/cobrament és independent de la factura alumne. |

### 1.1. Flux principal del servei

1. L'operador revisa l'expedient de l'alumne, el receptor i l'import de la part USOC. Abans de facturar cal **identificació fiscal explícita de l'entitat**: no es pot recuperar automàticament de les dades de l'alumne.
2. `UsocEntityInvoiceService::issueEntityFromExplicitInput($legacyDb,$input)` valida `idpag`, `student_amount`, `amount`, `student_invoice_uuid` i `billing.name/nif`.
3. `LegacyUsocSnapshotRepository::loadByIdpag()` recupera inscripció/curs, comprova `TIPUS_DESC=4` i `VALID_DESC=1` i normalitza els imports diferenciats.
4. `LegacyUsocInvoicePayloadBuilder::buildEntityPayload()` construeix factura amb línia `INSCRIPCIO`, import entitat, dades fiscals explícites i identificador de factura alumne en les metadades `usoc`; usa clau idempotent per inscripció + factura alumne.
5. `InvoiceService::issueInvoice()` crea o reutilitza **una factura nova de l'entitat**, amb número, registre fiscal, cadena i cua. El resultat de `UsocEntityInvoiceService` marca `payment_registered=false`. **El servei no ha cobrat la part de l'entitat.**
6. Quan arriba un pagament real d'entitat, el canal inicia UC-02/22/24 contra **la factura entitat**, i registra `payment_transaction CHARGE` únic i `payment_allocation` corresponent. Cap cobrament real no s'infereix de `student_amount` ni de `amount` en el payload fiscal.
7. Quan s'implementi el registre de fons per inscripció, el pagament entitat confirmat generarà una atribució específica a la mateixa inscripció amb **`UUID_PAYMENT` d'entitat**, independent de la de l'alumne. El deute pendent no crea una entrada fictícia.

### 1.2. Alternatives, errors i decisions pendents

| Cas | Comportament |
| --- | --- |
| Falten dades fiscals o `student_invoice_uuid` | Rebuig abans de generar factura entitat. |
| `amount` o `student_amount` zero/no positiu | Rebuig pel servei. |
| Inscripció no USOC validada o `IDPAG` no existent | El repositori/builder rebutgen. |
| Entitat encara no paga | Factura emesa i pendent; **no** `CHARGE`, `COMPENSATION` ni atribució de diner a inscripció. |
| Entitat paga parcialment | Cada fracció confirmada és un moviment econòmic independent sobre la factura entitat; cap nou `issueInvoice()` pel cobrament. |
| La factura alumne ja existeix, però es demana entitat amb import nou | La clau d'emissió pot coincidir; cal comparar payloads i evitar reaprofitar una factura anterior amb dades contradictòries. |
| Canvi de curs/baixa amb ambdues factures | Dues relacions fiscals a classificar; retorn/saldo separat per pagador i import, sense retornar la part entitat a l'alumne per defecte. |
| Doble facturació però un sol pagament d'alumne | **No** atribuir el total de les dues factures al cobrament de l'alumne; conciliar amb els dos moviments externs reals quan existeixin. |
| Relació entre factures | `student_invoice_uuid` consta en metadades/retorn del servei; **no** afirmar una FK o `fact_rels` específica entre factures sense confirmar la persistència efectiva d'aquest vincle al codi. |

**Proves localitzades, no executades:** `UsocEntityInvoiceServiceTest` comprova el servei amb entrada explícita; manca validar el cicle integral de dues factures, cobraments, titularitat i documents.

### 1.3. Diferència USOC: snapshot, receptor i cobrament posterior — contrast amb el xat original

En el cas habitual explicat, l'alumne ingressa inicialment **10 €** i USOC assumeix la diferència pactada. El valor de la factura d'entitat no s'ha de recalcular com a resta del camp viu `A_PAGAR - PAGAMENT`: abans de facturar es contrasta amb el snapshot de preu base, descompte, import d'alumne, import assumit per USOC, `TIPUS_DESC=4`, `VALID_DESC=1`, validació manual i dades fiscals explícites de l'entitat. El valor de 10 € és descriptiu del cas recuperat, **no** una constant universal de la facturació USOC.

Si USOC encara no ha abonat res, UC-19b emet només la seva factura **pendent**; el cobrament posterior contra el seu UUID és UC-02/22 i no torna a emetre la factura. Si una mateixa transferència real de l'entitat cobreix imports de diverses factures USOC, cal UC-105: un moviment bancari i assignacions diferenciades; `UsocEntityInvoiceService` no acredita aquest repartiment. La persona alumna no obté accés a la factura de l'entitat pel fet de compartir ID_INSC.

**Variant «Curs gratuït USOC»:** la documentació històrica cita `anticipi-preu-usoc`, però no estableix en aquesta fitxa l'import exacte que hi paga l'entitat ni quin document correspon si l'alumne paga zero. Si el circuit concret té `student_amount=0`, la ruta actual UC-19b exigeix `student_amount>0` i `student_invoice_uuid`, i no pot donar-se per compatible sense un disseny específic i una classificació fiscal aprovada. No simular una factura alumne o pagament inexistents.

**Proves addicionals, no executades:** factura entitat a receptor fiscal explícit i no al NIF alumne; diferència traçada al snapshot; dues factures de la mateixa inscripció amb estats de cobrament independents; dos pagaments parcials d'entitat sense nova factura; transferència multifactura concilada sense duplicar ingrés; import alumne zero desviat al circuit especial no resolt.
## 2. Diagrama UML de casos d'ús — factura entitat

```plantuml
@startuml
left to right direction
actor "Operador autoritzat" as O
actor "Entitat USOC" as E
rectangle "SIF · part entitat USOC" {
 usecase "UC-19b\nFacturar diferència a entitat" as U
 usecase "Verificar inscripció USOC\ni factura alumne" as Check
 usecase "Capturar billing\nde l'entitat" as Billing
 usecase "UC-01\nEmetre factura entitat\nsense cobrament" as Issue
 usecase "UC-02\nRegistrar cobrament real\nposterior" as Charge
}
O --> U
E --> Charge
U ..> Check : <<include>>
U ..> Billing : <<include>>
U ..> Issue : <<include>>
O --> Charge
note bottom of Charge
 Facturar l'entitat no és cobrar-la.
 La part alumne ja pertany a UC-19a.
end note
@enduml
```

### Vista de casos d’ús per a GitHub (Mermaid)

```mermaid
flowchart LR
  actor_0["Operador autoritzat"]
  actor_1["Entitat USOC"]
  subgraph SIF_BOX["SIF · part entitat USOC"]
    uc_0(["UC-19b<br/>Facturar diferència a entitat"])
    uc_1(["Verificar inscripció USOC<br/>i factura alumne"])
    uc_2(["Capturar billing<br/>de l'entitat"])
    uc_3(["UC-01<br/>Emetre factura entitat<br/>sense cobrament"])
    uc_4(["UC-02<br/>Registrar cobrament real<br/>posterior"])
  end
  actor_0 --> uc_0
  actor_1 --> uc_4
  uc_0 -.->|include| uc_1
  uc_0 -.->|include| uc_2
  uc_0 -.->|include| uc_3
  actor_0 --> uc_4
```

## 3. Diagrama de classes — implementació real

```mermaid
classDiagram
direction LR
class UsocEntityInvoiceService {
 +issueEntityFromExplicitInput(legacyDb,input) array
}
class LegacyUsocSnapshotRepository {
 +loadByIdpag(legacyDb,idpag,studentAmount,entityAmount) array
}
class LegacyUsocInvoicePayloadBuilder {
 +buildEntityPayload(snapshot,input) array
}
class InvoiceService {
 +issueInvoice(payload) array
}
class PaymentService {
 +registerPayment(payload) array
}
class ManualPaymentInvoiceRepository {
 +findByUuid(db,uuid,forUpdate) array
}
UsocEntityInvoiceService --> LegacyUsocSnapshotRepository : inscripció validada
UsocEntityInvoiceService --> LegacyUsocInvoicePayloadBuilder : factura entitat
UsocEntityInvoiceService --> InvoiceService : emissió sense payment
```

`PaymentService` és el servei de cobrament **posterior**, no una dependència directa del servei d'emissió de la part entitat.

## 4. Seqüència — emissió ara, cobrament quan s'acrediti

```mermaid
sequenceDiagram
autonumber
actor O as Operador autoritzat
participant UI as Intranet USOC [integració pendent]
participant S as UsocEntityInvoiceService
participant R as LegacyUsocSnapshotRepository
participant B as LegacyUsocInvoicePayloadBuilder
participant I as InvoiceService
participant P as PaymentService [acció UC-02 posterior]
participant Funds as Registre fons inscripció [PROPOSTA]
O->>UI: Seleccionar inscripció USOC i receptor fiscal entitat
UI->>S: issueEntityFromExplicitInput(legacyDb,input)
S->>S: Validar billing, student_invoice_uuid i imports
S->>R: loadByIdpag(idpag,student_amount,entity_amount)
alt Inscripció absent/no USOC validada
 R--xS: Error
 S--xUI: No emetre
else Inscripció USOC identificada
 R-->>S: Snapshot inscrit/curs/USOC
 S->>B: buildEntityPayload(snapshot,billing i UUID alumne)
 B-->>S: Factura entitat sense payment
 S->>I: issueInvoice(payload)
 I-->>S: UUID_FACTURA_ENTITAT
 S-->>UI: payment_registered=false, UUID i factura alumne
 Note over UI,Funds: Cap moviment monetari entitat fins al cobrament real
 O->>UI: Confirmar transferència/ingrés entitat més tard
 UI->>P: registerPayment(CHARGE, UUID_FACTURA_ENTITAT)
 P-->>UI: UUID_PAYMENT_ENTITAT
 opt Atribució per inscripció [NO IMPLEMENTADA]
  UI->>Funds: append(EXTERNAL→ID_INSC, entity_amount cobrat, UUID_PAYMENT_ENTITAT)
 end
end
```

## 4.1. Acció independent: comprovar l'expedient de dos pagadors abans d'emetre la factura d'entitat — DISSENY

**Actor/disparador:** gestió vol facturar la diferència a l'entitat després de rebre `entity_invoice_pending` de la part alumne. **Precondicions:** `ID_INSC`/IDPAG unívocs, afiliació validada, `UUID_FACTURA_ALUMNE` **existent i associat** a aquella inscripció i a l'import de l'alumne confirmat, proposta d'import de l'entitat i `billing` explícit de l'entitat legitimada. **Postcondició:** expedient correlacionat o incidència; la verificació no emet factura, no registra `CHARGE` ni dóna per pagada la diferència.

**Límit del PHP real:** `UsocEntityInvoiceService::assertExplicitEntityInput()` només comprova la presència i el valor no buit de `student_invoice_uuid`, i que `billing.name/nif` siguin no buits. `LegacyUsocSnapshotRepository::loadByIdpag()` comprova marcadors de la inscripció llegada, però **no rep ni consulta** el UUID de factura alumne en el SIF. `LegacyUsocInvoicePayloadBuilder::buildEntityPayload()` incorpora el UUID rebut a la clau idempotent i a metadades `usoc`; no prova l'existència, receptor, línia, import o pagament de la factura referenciada. `LegacyUsocSnapshotRepository::findInscription()` fa `WHERE IDPAG=? ORDER BY ID LIMIT 1`; un IDPAG compartit necessita verificació explícita de l'`ID_INSC` real de l'expedient, no només el primer resultat.

```plantuml
@startuml
left to right direction
actor "Operador de gestió autoritzat" as O
rectangle "SIF PrisMa — UC-19b / VERIFY (DISSENY)" {
 usecase "Comprovar expedient de doble pagador" as Verify
 usecase "Comprovar USOC validada i ID_INSC exacte" as Student
 usecase "Comprovar factura alumne real,\nimport i receptor" as Invoice
 usecase "Validar receptor i import\nd'entitat amb acord/snapshot" as Entity
 usecase "UC-19b / EMETRE\nFactura entitat" as Issue
}
O --> Verify
Verify ..> Student : <<include>>
Verify ..> Invoice : <<include>>
Verify ..> Entity : <<include>>
O --> Issue
@enduml
```

### Vista de casos d’ús per a GitHub (Mermaid)

```mermaid
flowchart LR
  actor_0["Operador de gestió autoritzat"]
  subgraph SIF_BOX["SIF PrisMa — UC-19b / VERIFY (DISSENY)"]
    uc_0(["Comprovar expedient de doble pagador"])
    uc_1(["Comprovar USOC validada i ID_INSC exacte"])
    uc_2(["Comprovar factura alumne real,<br/>import i receptor"])
    uc_3(["Validar receptor i import<br/>d'entitat amb acord/snapshot"])
    uc_4(["UC-19b / EMETRE<br/>Factura entitat"])
  end
  actor_0 --> uc_0
  uc_0 -.->|include| uc_1
  uc_0 -.->|include| uc_2
  uc_0 -.->|include| uc_3
  actor_0 --> uc_4
```

```mermaid
sequenceDiagram
autonumber
actor O as Gestió
participant UI as Panell USOC [PENDENT]
participant V as UsocFundingCaseValidator [DISSENY]
participant L as LegacyUsocSnapshotRepository [PHP; IDPAG i primer inscrit]
participant F as SIF factura + fact_rels + línies [LECTURA]
participant B as Dades receptor i acord USOC [LECTURA]
O->>UI: Comprovar facturació entitat per ID_INSC X i UUID_FACTURA_ALUMNE A
UI->>V: verify(X,idpag,A,studentAmount,entityAmount,billing)
V->>L: loadByIdpag(idpag,studentAmount,entityAmount) [PHP real]
L-->>V: Primer ID_INSC per IDPAG i flags TIPUS_DESC/VALID_DESC
alt ID_INSC retornat és diferent d'X o IDPAG ambigu
 V-->>UI: CONFLICT, no facturar una inscripció arbitrària
else Inscripció X USOC validada
 V->>F: Cercar UUID A real i verificar relacions/ID_INSC, receptor i imports
 alt UUID A inexistent, aliè o part alumne contradictòria
  F-->>V: CONFLICT
  V-->>UI: Rebutjar sense factura entitat
 else Factura alumne acreditada
  V->>B: Verificar import assumit, receptor entitat i versió d'acord
  alt Finançament o billing no justificats
   B-->>V: PENDING_REVIEW
   V-->>UI: Pendent de dades/autorització
  else Expedient coherent i autoritzat
   B-->>V: Validat per a l'operació concreta
   V-->>UI: Proposta d'emissió entitat preparada, encara no emesa
  end
 end
end
UI-->>O: Proposta validada, conflicte o incidència
Note over V,F: La validació del UUID d'alumne, ID_INSC exacte i acord d'entitat NO existeix a UsocEntityInvoiceService actual.
```

## 4.2. Acció independent: emetre la factura d'entitat o recuperar una emissió equivalent — PHP/PENDENT

**Actor/disparador:** operador autoritzat confirma les dades fiscals i l'import de l'entitat. El servei PHP existent construeix una factura **sense pagament** i delega `InvoiceService::issueInvoice()`. La seva clau actual és `INTRANET|USOC_ENTITAT|ID_INSC:<id>|FACT_ALUMNE:<uuid>` i **no incorpora import, receptor ni versió del finançament**. `InvoiceService::createOrReuseInvoice()` recupera per aquesta clau i, quan existeix, retorna `existingResultWithPaymentIfPresent()`, **sense comparar el payload rebut amb receptor/quantia originals**. Si s'ha emès a una entitat equivocada, un reintent amb `billing` rectificat no modifica la factura preexistent: cal obrir incidència/classificació fiscal, no fingir que l'ha corregida.

```plantuml
@startuml
left to right direction
actor "Gestió autoritzada" as G
actor "Entitat receptora/pagadora" as E
rectangle "SIF PrisMa — UC-19b / EMISSIÓ I REINTENT" {
 usecase "Emetre factura pendent\na receptor USOC explícit" as Issue
 usecase "Validar l'expedient i\nla versió del finançament" as Guard
 usecase "Distingir reintent idèntic de\npayload fiscal contradictori" as Idp
 usecase "UC-02\nRegistrar cobrament entitat posterior" as Pay
}
G --> Issue
G --> Guard
Issue ..> Guard : <<include>> [objectiu pendent]
Issue ..> Idp : <<include>> [objectiu pendent]
E --> Pay
G --> Pay
note bottom of Issue
 Cap CHARGE automàtic a l'entitat.
 Un reintent no ha de canviar silenciosament
 receptor ni import d'una factura emesa.
end note
@enduml
```

### Vista de casos d’ús per a GitHub (Mermaid)

```mermaid
flowchart LR
  actor_0["Gestió autoritzada"]
  actor_1["Entitat receptora/pagadora"]
  subgraph SIF_BOX["SIF PrisMa — UC-19b / EMISSIÓ I REINTENT"]
    uc_0(["Emetre factura pendent<br/>a receptor USOC explícit"])
    uc_1(["Validar l'expedient i<br/>la versió del finançament"])
    uc_2(["Distingir reintent idèntic de<br/>payload fiscal contradictori"])
    uc_3(["UC-02<br/>Registrar cobrament entitat posterior"])
  end
  actor_0 --> uc_0
  actor_0 --> uc_1
  uc_0 -.->|include| uc_1
  uc_0 -.->|include| uc_2
  actor_1 --> uc_3
  actor_0 --> uc_3
```

```mermaid
sequenceDiagram
autonumber
actor G as Gestió
participant UI as Panell/guard USOC [DISSENY]
participant S as UsocEntityInvoiceService [PHP]
participant B as LegacyUsocInvoicePayloadBuilder [PHP]
participant I as InvoiceService [PHP]
participant DB as factura i fact_rels SIF
G->>UI: Aprovar part entitat 90 a receptor E1, factura alumne A
UI->>UI: Validar ID_INSC/UUID alumne/receptor/finançament [PENDENT]
UI->>S: issueEntityFromExplicitInput(legacyDb,input E1/90/A)
S->>B: buildEntityPayload(snapshot,input)
B-->>S: Clau K = ID_INSC + FACT_ALUMNE A, sense import/receptor
S->>I: issueInvoice(payload E1/90 sense payment)
I->>DB: BEGIN + cerca per clau K
alt No existeix K
 I->>DB: Emissió factura E1/90, registre i cua, COMMIT
 I-->>S: UUID_FACTURA_ENTITAT nou
else Ja existeix K
 DB-->>I: Factura E1/90 preexistent
 I-->>S: UUID_FACTURA_ENTITAT anterior, idempotency_reused=true
end
S-->>UI: payment_registered=false, UUID_FACTURA_ENTITAT
G->>UI: Reintentar amb mateixa K però receptor E2 o import 85
UI->>S: issueEntityFromExplicitInput(input nou E2/85/A)
S->>B: buildEntityPayload(snapshot,input nou)
B-->>S: Mateixa clau K
S->>I: issueInvoice(payload nou)
I->>DB: BEGIN + trobar K existent
DB-->>I: UUID_FACTURA_ENTITAT de E1/90
I-->>S: idempotency_reused=true sense comparar E2/85 amb E1/90
S-->>UI: Retorn aparentment correcte amb factura fiscal anterior
UI-->>G: Guard objectiu ha de detectar CONFLICT i derivar UC-74, mai informar que E2/85 ha estat emès
Note over S,I: La recuperació per K és PHP real, el guard d'equivalència i l'expedient USOC són DISSENY. No es pot editar la factura fiscal anterior.
```

**Cobrament com a altra acció:** després d'emetre, `payment_registered=false` ha de continuar sent visible fins que hi hagi un `UUID_PAYMENT` real d'entitat. Rebre una transferència de l'entitat per diverses factures és UC-02/105 (un únic ingrés extern i múltiples assignacions), no `issueInvoice()` de nou.

| Prova pendent | Escenari | Resultat exigible |
| --- | --- | --- |
| UE-19b-07 | `student_invoice_uuid` existent però d'una altra inscripció o d'un altre receptor | Verificador de l'expedient rebutja, sense emetre factura entitat. |
| UE-19b-08 | Mateix `IDPAG` compartit per dues inscripcions, la d'USOC no és la primera de la consulta llegada | Seleccionar la inscripció explícita o bloquejar amb conflicte; no facturar automàticament el primer ID. |
| UE-19b-09 | Factura entitat E1/90 confirmada i resposta perduda; reintent E1/90 | Reús de la mateixa factura sense altra numeració, registre fiscal o `CHARGE`. |
| UE-19b-10 | Mateixa clau d'emissió però receptor E2 o import 85 | `CONFLICT` abans de reús semàntic; factura E1/90 anterior intacta, classificar correcció si escau. |
| UE-19b-11 | Factura entitat emesa i transferència posterior no confirmada | `payment_registered=false`, cap `CHARGE` fictici ni matrícula marcada totalment pagada. |

## 5. Traçabilitat

[Fitxa original UC-19b](../06-fitxes-funcionals/uc-019b.md) · [UC-13 doble facturació](uc-013-orquestrar-doble-facturacio-usoc.md) · [UC-19a part alumne](uc-019a-facturar-part-alumne-usoc.md) · [UC-02 cobrament](uc-002-registrar-cobrament-factura.md) · [Revisió de fons](00-revisio-moviments-inscripcions.md) · [UsocEntityInvoiceService](../../sif/src/Service/UsocEntityInvoiceService.php) · [LegacyUsocInvoicePayloadBuilder](../../sif/src/Service/LegacyUsocInvoicePayloadBuilder.php) · [LegacyUsocSnapshotRepository](../../sif/src/Repository/LegacyUsocSnapshotRepository.php) · [UsocEntityInvoiceServiceTest](../../sif/tests/Integration/UsocEntityInvoiceServiceTest.php).
