# UC-21 · Empresa o responsable paga inscripcions — fitxa i UML integrats

**Abast:** un pagador extern (empresa, escola o persona responsable) assumeix el cost d'una o més inscripcions, sovint necessita una factura **abans** de fer la transferència. Aquest cas determina **qui és el receptor fiscal, quines inscripcions queden cobertes i com s'assignarà el cobrament**; UC-04 és únicament el nucli reutilitzable d'emissió abans de cobrar i UC-02 el registre posterior del moviment.

**Estat:** `[PARCIAL_AVANÇAT]`. Flux intern operatiu: intranet segura → API interna signada → preview autoritatiu → fingerprint → confirmació → factura SIF pendent. El cobrament posterior, el guard Redsys CURS serialitzat, el ledger quantitatiu explícit per participant i les polítiques fail-closed de consulta/PDF ja estan implementats. Continuen pendents l'autenticador/portal extern del receptor, l'evidència E2E en `sif_test/sif_pre` i la validació operativa final.

## 1. Fitxa de cas d'ús

| Camp | Especificació del cas |
| --- | --- |
| Actor principal | Empresa o persona responsable que assumeix el pagament. L'operador autoritzat gestiona la preparació de la factura i la imputació; l'accés extern segur està pendent de verificar. |
| Disparador | Un centre, empresa o responsable sol·licita la factura per pagar una o més inscripcions; o comunica/realitza posteriorment el pagament d'aquesta factura. |
| Precondició funcional | Identificar el pagador real i el **receptor fiscal** que ha de constar a la factura, les inscripcions/participants coberts i els imports corresponents. Pagador, receptor i participant no són necessàriament la mateixa persona. |
| Document previ | Si només hi ha pressupost editable, no s'ha d'etiquetar com a factura emesa ni donar-li número fiscal. Si s'emet una factura amb número, és una factura real encara que el cobrament estigui pendent. |
| Contracte d'emissió implementat | `InvoiceBeforePaymentService::issueBeforePayment()` requereix clau idempotent o referència, prohibeix bloc `payment` no nul, força `source_channel=INTRANET` i `emesa_abans_cobrament=1`, i delega a `InvoiceService`. |
| Contracte de cobrament implementat | `PaymentService::registerPayment()` amb `allocations` cap a `uuid_factura` ja existent. No torna a invocar `issueInvoice()` si la factura ja existeix. |
| Resultat esperat | Una sola factura fiscal amb receptor i línies congelats i referències internes a les inscripcions; un o diversos moviments econòmics assignats posteriorment, amb estat de cobrament independent. |

### 1.1. Flux funcional objectiu i passos amb codi acreditat

1. L'empresa/responsable indica participants, curs/edició i dades fiscals. L'operador verifica si es tracta d'un grup d'escola/empresa o d'una persona responsable de grup particular. **La documentació de fluxos especifica que el receptor d'una factura de grup no és sempre una entitat.**
2. Es determina la fotografia de les dades de facturació, imports, descomptes i relacions d'inscripció que ha de contenir la factura. La regla funcional és que una persona pot pagar per diverses inscripcions, però cadascuna manté la seva identitat.
3. Si l'empresa necessita factura abans del pagament, el canal ha de construir el payload per a `InvoiceBeforePaymentService` sense moviment inicial; el nucli `InvoiceService` crea una factura real i la deixa amb `EMESA_ABANS_COBRAMENT=1`, `ESTAT_COBRAMENT=PENDING`, número fiscal, registre fiscal i entrada a `fiscal_queue`.
4. El receptor rep o consulta la factura per un canal amb permisos. **La generació del document i l'enllaç segur no són garanties aportades per `InvoiceBeforePaymentService`** i continuen pendents de verificar.
5. Quan s'acredita el cobrament, el canal executa UC-02/UC-22 amb la clau idempotent **del pagament** i una assignació a la factura prèvia. El SIF actualitza `ESTAT_COBRAMENT` a partir dels moviments i no genera una segona factura fiscal.
6. Si canvien les persones inscrites o l'import després de l'emissió, cal obrir una gestió posterior i classificar l'efecte fiscal (UC-05/UC-71/UC-74), no editar les línies fiscals emeses.

### 1.2. Fluxos alternatius, excepcions i llacunes

| Escenari | Comportament i criteri |
| --- | --- |
| E1. Receptor empresa/escola | Si el grup és d'una escola o empresa, la documentació indica que la factura s'emet a l'entitat; les inscripcions continuen individualitzades per participant. |
| E2. Grup d'amics o persona particular | La factura pot anar al responsable particular. **No deduir que `billing` ha de ser sempre una empresa** del fet que el cas es digui «Empresa/responsable». |
| E3. Empresa que encara no ha pagat | L'emissió genera factura real amb número fiscal i registre; el pagament continua `PENDING`. La documentació explica que el tractament històric d'exportació/comptabilització podia considerar-la cobrada: el nou model ha de mostrar els dos estats separats. |
| E4. Pagament parcial o transferència posterior | Registrar moviment i assignació sobre el mateix `uuid_factura`; el calculador econòmic determina `PARTIAL` o `PAID` segons la suma neta. |
| E5. Factura ja emesa, participants/import modificats | No tornar a construir una factura amb les dades vives: cal registrar canvi i resoldre si la factura exigeix rectificació o altra acció fiscal validada. |
| **P1. Assignació per participant** | La selecció, imports i relacions es reconstrueixen al servidor i el cobrament global es pot distribuir explícitament amb `JointInvoiceEnrollmentFundAllocationService` sobre `enrollment_fund_movement`, vinculant `UUID_PAYMENT`, `UUID_FACTURA`, línia fiscal i `ID_INSC_DESTI`. Pendent només la validació operativa/UI i preproducció. |
| **P2. Dades i permisos** | L'operador intern ja està protegit per sessió, rol, CSRF i API interna signada. L'accés extern del pagador/receptor al document continua pendent; els participants no han de rebre visibilitat implícita sobre la factura conjunta. |
| **P3. IDPAG i flux manual de grup** | `ManualGroupInvoiceService::issueFromLegacyGroupPayment()` demana import de pagament i `ManualGroupInvoicePayloadBuilder` adjunta un bloc de pagament inicial; **no s'ha de reutilitzar directament com si fos l'emissió abans de cobrar de UC-21**. |
| **P4. Titular de saldos** | Si posteriorment hi ha retorn/saldo, la documentació exigeix determinar el titular econòmic quan pagava una empresa o responsable. UC-29 no ho valida per si sol. |
| **P5. Pagament i receptor divergents** | L'endpoint genèric de pagaments no verifica en el servei de domini que el pagador de la transferència correspongui a l'obligació de la factura; control de gestió pendent d'integració. |

**Persistència del nucli acreditada:** `factura`, `factura_linia`, `factura_registres`, `fiscal_queue`, `fact_rels` si s'aporten; després `payment_transaction` i `payment_allocation` vinculats a la factura. **No s'ha acreditat** un esquema d'agrupació comercial específic de UC-21 amb totes les relacions de participants operatiu a la pantalla final.

**Proves localitzades, no executades:** `InvoiceBeforePaymentServiceTest` acredita un flux de factura abans de cobrar sense moviment inicial; `RegisterPaymentTest` registra posteriorment un pagament sense duplicar el registre fiscal. **No són una prova d'extrem a extrem d'una empresa amb N participants i accés extern.**

### 1.3. Revisió: un sol pagador, múltiples inscripcions — PENDENT

L'empresa pot pagar **una sola transferència i una sola factura** que cobreixin N persones; el registre econòmic ha de conservar `UUID_PAYMENT` **únic** i atribuir explícitament a cada `ID_INSC` el seu import, curs/edició i relació a la línia/operació. `fact_rels` informa de la relació documental, però **no** quantifica el que s'ha atribuït a cada participant. La factura prèvia sense pagament no crea cap atribució. Si un participant canvia de curs o causa baixa, només es traspassa/retorna la quantitat atribuïda al seu cas, amb titular econòmic i permisos de l'empresa validats, sense modificar el cobrament global dels altres participants.

[Registre proposat de moviments per inscripció](00-revisio-moviments-inscripcions.md).

```mermaid
sequenceDiagram
autonumber
actor E as Empresa/responsable
participant UI as Canal autoritzat [pendent]
participant P as PaymentService [existent]
participant L as EnrollmentFundMovementRepository [PROPOSTA]
participant DB as BD SIF
E->>UI: Confirmar pagament de N inscripcions
UI->>UI: Validar factura, titular i import de cada participant
UI->>P: registerPayment() d'un únic CHARGE confirmat
P->>DB: INSERT payment_transaction i payment_allocation
P-->>UI: UUID_PAYMENT únic
loop Cada participant amb import validat
 UI->>L: append(EXTERNAL → inscripció, import, UUID_PAYMENT_ORIGIN)
 L->>DB: INSERT atribució individual immutable
end
UI->>UI: Reconciliar sumes i retornar resultat
Note over UI,DB: Seqüència OBJECTIU: el servei actual no fa els INSERT per inscripció
```

### 1.4. Contrast amb la pantalla real i control de doble facturació — integració pendent

**Evidència del circuit antic:** la pantalla «Generar factura abans de pagar» cerca per NIF/NIE, afegeix inscripcions, suma imports A_PAGAR i només permet continuar quan les seleccionades corresponen al mateix curs i edició. Després es tria l'entitat, es revisen concepte i preu i s'emet la factura. Aquesta limitació de la pantalla històrica no prohibeix en abstracte una factura multiconcepte (UC-88). La previsualització/descàrrega antiga regenera el PDF; la futura consulta SIF ha de servir el document fiscal custodiat, no reconstruir-lo a partir de dades vives.

**Variant E5-bis — empresa inscrita com a contacte i doble factura:** el xat original descriu una inscripció grupal feta per l'empresa en què el contacte ha utilitzat el CIF de l'entitat com a identificador; després d'emetre factura prèvia, la cerca per CIF a «Passar pagaments» pot iniciar indegudament una segona factura. La cerca fiscal definitiva ha d'identificar les inscripcions i llurs relacions amb UUID_FACTURA/fact_rels, IDPAG i, quan existeixi, FACTURA_RELACIONADA històric; **el CIF tot sol no identifica una factura única**. Si hi ha diverses factures legítimes del mateix CIF, cal seleccionar i validar la factura exacta, o obrir incidència; no agrupar-les ni generar-ne una altra per defecte.

**Control previ d'enllaços i concurrència (implementat per CURS):** `EnrollmentPaymentFlowLockRepository` serialitza per `INSCRIPCIO`; UC-021 comprova dins del lock factures SIF existents i intencions Redsys actives; Redsys revalida `invoice_before_payment_coverage` dins la mateixa transacció abans d'emetre. Nova intenció després de coverage i callback tardà també es rebutgen. Si una projecció/sincronització posterior falla, **es conserva la realitat fiscal/econòmica** i es reprèn de manera idempotent.

**Pagament posterior:** quan la factura preexistent cobreix les inscripcions, «Passar pagaments», la transferència o el callback autoritzat han de registrar i assignar el cobrament a aquell UUID_FACTURA (UC-02/22), no invocar el camí ManualGroupInvoiceService que emet una factura amb pagament inicial. Una factura pendent d'empresa pot conservar **el seu propi enllaç segur de pagament**, encara que els individuals incompatibles quedin inactius. El pagament parcial deixa PARTIAL i el cobrament total PAID sense alterar el número de factura ni crear un nou registre de venda per aquest únic cobrament.

**Idempotència de negoci implementada:** `InvoiceService` compara el hash canònic del payload abans de reutilitzar una factura; un canvi de receptor, participants, línies o imports sota la mateixa clau provoca conflicte. El document i el correu al receptor es mantenen separats de la comunicació als participants: `visible_alumne=0` i la política de document exigeix `invoice_scope=FULL` explícit.

### 1.5. Proves d'acceptació específiques del circuit d'empresa (no executades)

| ID | Escenari | Resultat que cal acreditar |
| --- | --- | --- |
| E21-01 | Inscripció d'empresa amb factura prèvia sense pagar | Una única factura real, PENDING i cap moviment de cobrament fictici. |
| E21-02 | N inscripcions del mateix curs i edició | Receptor fiscal correcte, N relacions i imports verificats al servidor, no només al HTML. |
| E21-03 | Cercar el grup pel CIF de contacte després de la factura prèvia | S'obre la factura existent per UUID/relacions i el cobrament no emet una segona factura. |
| E21-04 | Dues factures legítimes del mateix CIF | No es confonen; selecció per inscripcions/UUID o incidència si hi ha ambigüitat. |
| E21-05 | Inscripció ja pagada, ja facturada o amb Redsys en curs | Bloqueig de cobertura incompatible i conciliació de l'estat real abans d'emetre. |
| E21-06 | Enllaç individual antic o callback tardà després d'assumir l'empresa el pagament | Cap segona factura; si existeix cobrament real, conservar-ne l'evidència i obrir conciliació. |
| E21-07 | Mateixa clau idempotent amb receptor/imports/participants diferents | Conflicte explícit, sense reutilitzar un resultat econòmic incompatible. |
| E21-08 | Dues transferències parcials sobre factura prèvia | Dos cobraments reals associats a la mateixa factura, PARTIAL i després PAID. |
| E21-09 | Fallada de la intranet o dels enllaços després del commit fiscal | Factura conservada i incidència/reintent; ni factura local alternativa ni via individual duplicada. |
| E21-10 | Alumne participant consulta la factura conjunta | No accedeix a dades d'altres participants; l'empresa/receptor només amb autorització del servidor. |
## 2. Diagrama UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Empresa o responsable" as Company
actor "Operador de facturació" as Operator
rectangle "SIF PrisMa" {
 usecase "UC-21\nEmpresa/responsable paga\ninscripcions" as U21
 usecase "Identificar receptor,\nparticipants i imports" as Identify
 usecase "UC-04\nEmetre factura abans\nde cobrar" as Before
 usecase "UC-02\nRegistrar pagament posterior" as Later
 usecase "UC-07\nConsultar factura autoritzada" as Consult
 usecase "UC-05\nRectificar si canvia factura emesa" as Rect
}
Company --> U21
Operator --> U21
U21 ..> Identify : <<include>>
U21 ..> Before : <<include>> (quan necessita factura prèvia)
Company --> Consult
Operator --> Later
Operator --> Rect
note bottom of Later
  Acció posterior i independent:
  no genera una segona factura.
end note
@enduml
```

El diagrama és **funcional/objectiu**: l'accés de l'empresa a la consulta i el camí de pantalla no es declaren implementats. UC-04/UC-02 tenen cadascun una fitxa i una seqüència executables pròpies.

### Vista de casos d’ús per a GitHub (Mermaid)

```mermaid
flowchart LR
  actor_0["Empresa o responsable"]
  actor_1["Operador de facturació"]
  subgraph SIF_BOX["SIF PrisMa"]
    uc_0(["UC-21<br/>Empresa/responsable paga<br/>inscripcions"])
    uc_1(["Identificar receptor,<br/>participants i imports"])
    uc_2(["UC-04<br/>Emetre factura abans<br/>de cobrar"])
    uc_3(["UC-02<br/>Registrar pagament posterior"])
    uc_4(["UC-07<br/>Consultar factura autoritzada"])
    uc_5(["UC-05<br/>Rectificar si canvia factura emesa"])
  end
  actor_0 --> uc_0
  actor_1 --> uc_0
  uc_0 -.->|include| uc_1
  uc_0 -.->|include| uc_2
  actor_0 --> uc_4
  actor_1 --> uc_3
  actor_1 --> uc_5
```

El diagrama és **funcional/objectiu**: l'accés de l'empresa a la consulta i el camí de pantalla no es declaren implementats. UC-04/UC-02 tenen cadascun una fitxa i una seqüència executables pròpies.

## 3. Diagrama de classes del nucli utilitzat

```mermaid
classDiagram
direction LR
class InvoiceBeforePaymentService {
 +issueBeforePayment(input) array
}
class InvoiceBeforePaymentPayloadBuilder {
 +build(input) array
}
class InvoiceService {
 +issueInvoice(payload) array
}
class InvoiceRepository {
 +createInvoiceGraph(db,payload,seq,chainState) array
}
class PaymentService {
 +registerPayment(payload) array
}
class PaymentRepository {
 +createPayment(db,payload) array
}
class ManualGroupInvoiceService {
 +issueFromLegacyGroupPayment(legacyDb,idpag,input) array
}
class LegacyGroupSnapshotRepository {
 +loadByIdpag(legacyDb,idpag,amount) array
}
class ManualGroupInvoicePayloadBuilder {
 +buildFromSnapshot(snapshot,input) array
}
InvoiceBeforePaymentService --> InvoiceBeforePaymentPayloadBuilder : payload sense payment
InvoiceBeforePaymentService --> InvoiceService : emissió
InvoiceService --> InvoiceRepository : factura/relacions
PaymentService --> PaymentRepository : cobrament posterior
ManualGroupInvoiceService --> LegacyGroupSnapshotRepository : grup i responsable
ManualGroupInvoiceService --> ManualGroupInvoicePayloadBuilder : amb payment inicial
ManualGroupInvoiceService --> InvoiceService : altre camí, NO UC-21 abans cobrar
```

**Frontera del diagrama:** `ManualGroupInvoiceService` es mostra com a contrast amb un camí diferent, no com una crida feta per `InvoiceBeforePaymentService`. No es representa cap classe fictícia per crear un «pagament d'empresa» si no consta al codi.

### 3.1. Classes de coordinació reconciliades per a la cobertura de participants — IMPLEMENTACIÓ PARCIAL OPERATIVA

El model executable ja protegeix el solapament principal `CURS`: lock compartit per `INSCRIPCIO`, coverage UC-021, comprovació de factura SIF existent, intencions Redsys i revalidació de coverage al callback. El subdiagrama següent representa l'estat reconciliat.

```mermaid
classDiagram
direction LR
class CompanyInvoiceCoordinator {
 <<implementat funcionalment via CommandService/PreparationService>>
 +previewCoverage(command) result
 +confirmInvoice(command) result
}
class EnrollmentInvoiceCoverageGuard {
 <<implementat amb coverage + lock + guard SIF/Redsys>>
 +validateEnrollments(ids,receptor,operation) decision
}
class InvoiceBeforePaymentService {
 <<PHP existent>>
 +issueBeforePayment(input) array
}
class PaymentService {
 <<PHP existent: assigna a factura>>
 +registerPayment(payload) array
}
class EnrollmentFundMovementRepository {
 <<PROPOSTA: no implementada>>
 +append(db,movement) string
}
class RedsysPaymentIntentService {
 <<PHP existent: guard coverage + lock compartit CURS>>
 +create(db,input) array
}
CompanyInvoiceCoordinator --> EnrollmentInvoiceCoverageGuard : factura i inscripcions prèvies
EnrollmentInvoiceCoverageGuard ..> RedsysPaymentIntentService : coverage/intencions CURS [IMPLEMENTAT]
CompanyInvoiceCoordinator --> InvoiceBeforePaymentService : emetre una vegada
CompanyInvoiceCoordinator ..> PaymentService : cobrament posterior independent
CompanyInvoiceCoordinator ..> EnrollmentFundMovementRepository : atribució quantitativa [IMPLEMENTAT MANUAL]
```
## 4. Seqüència — empresa sol·licita factura i paga posteriorment (objectiu + nucli implementat)

```mermaid
sequenceDiagram
autonumber
actor E as Empresa/responsable
actor O as Operador autoritzat
participant UI as Intranet/accés extern [pendent]
participant IBP as InvoiceBeforePaymentService
participant B as InvoiceBeforePaymentPayloadBuilder
participant IS as InvoiceService
participant IR as InvoiceRepository
participant PS as PaymentService
participant PR as PaymentRepository
participant DB as BD SIF
E->>O: Aporta dades fiscals i inscripcions que assumirà
O->>UI: Identificar receptor i participants, validar imports
Note over O,UI: Construcció específica i autorització per participants: pendent d'acreditar
UI->>IBP: issueBeforePayment(input sense payment)
IBP->>B: build(input)
B-->>IBP: payload INTRANET + flag emissió prèvia
IBP->>IS: issueInvoice(payload)
IS->>DB: BEGIN de la transacció d'emissió
IS->>IR: Comprovar idempotència i crear factura si és nova
opt No existeix la factura
 IR->>DB: INSERT factura/línies/registre fiscal/cua/relacions
end
IS->>DB: COMMIT de la transacció d'emissió
IS-->>IBP: uuid_factura i num_visible confirmats
IBP-->>UI: uuid_factura i num_visible confirmats
UI-->>E: Estat de factura emesa, accés al document [canal pendent]
Note over E,DB: Factura fiscal existent i cobrament econòmic encara PENDING
E->>O: Comunica/efectua pagament
O->>UI: Validar cobrament i factura preexistent
UI->>PS: registerPayment(payload amb allocation a uuid_factura)
PS->>DB: BEGIN de la transacció de cobrament
PS->>PR: crear o reutilitzar moviment
opt Moviment real no existent
 PR->>DB: INSERT payment_transaction i payment_allocation
 PR->>DB: UPDATE factura.ESTAT_COBRAMENT
end
PS->>DB: COMMIT de la transacció de cobrament
PS-->>UI: uuid_payment confirmat
UI-->>E: Resultat del cobrament [canal pendent]
Note over IS,DB: El cobrament no torna a executar issueInvoice()
```

### 4.1. Seqüència alternativa — modificació després d'emetre (disseny)

```mermaid
sequenceDiagram
actor O as Operador
participant UI as Gestió de participants [pendent]
participant Audit as Registre d'event comercial [disseny]
participant Decide as Classificació fiscal [disseny]
participant SIF as SIF · casos UC-05/UC-71
O->>UI: Afegir participant o modificar import
UI->>Audit: Conservar dades abans/després, motiu, actor i factura original
Audit-->>UI: Referència de l'event
UI->>Decide: Valorar si canvien línies, servei o import facturat
alt S'exigeix rectificació
 Decide->>SIF: Iniciar UC-05 amb enllaç a original
else Sense canvi fiscal
 Decide-->>UI: Només canvi comercial justificat
else No es pot classificar
 Decide-->>UI: Bloqueig i incidència pendent
end
Note over UI,SIF: Classes d'event/decisor representades com a disseny, no com a PHP executat
```

### 4.2. Seqüència addicional — cobertura segura i callback individual concurrent (OBJECTIU)

```mermaid
sequenceDiagram
autonumber
actor O as Gestió
participant UI as Intranet [adaptació pendent]
participant Guard as Control cobertura/locks [DISSENY]
participant TPV as Intencions individuals Redsys
participant SIF as InvoiceBeforePaymentService [existent]
participant Q as Incidències/reconciliació [integració pendent]
O->>UI: Seleccionar N inscripcions i receptor
UI->>Guard: Bloquejar operació i rellegir factures/cobraments per inscripció
Guard->>TPV: Consultar intents pendents i impedir noves vies individuals incompatibles
alt Cobrament individual confirmat o callback en curs
 TPV-->>UI: Estat ambigu o confirmat
 UI->>Q: Conservar evidència i resoldre abans d'emetre
 UI-->>O: Emissió suspesa
else Cobertura verificable i intencions incompatibles controlades
 UI->>SIF: issueBeforePayment(snapshot validat, sense payment)
 SIF-->>UI: UUID_FACTURA i NUM_VISIBLE
 alt Sincronització amb llegat fallida després del commit
  UI->>Q: Incidència i reintent idempotent de sincronització
  UI-->>O: Factura emesa, integració pendent, cap emissió alternativa
 else Sincronització completada
  UI-->>O: Factura prèvia i via de pagament de l'empresa
 end
end
Note over Guard,Q: Coordinació entre BDs/callbacks és pendent: aquest diagrama no acredita atomicitat distribuïda.
```
### 4.3. Acció pròpia: detectar cobertura fiscal existent abans d'emetre o pagar — OBJECTIU

La UC-21 té un disparador específic: un centre/responsable vol assumir N inscripcions. Abans d'emetre la factura d'empresa, el canal ha de diferenciar **receptor fiscal**, **pagador de l'ingrés** i **participants**. La clau idempotent de la petició d'UC-04 no identifica per si sola totes les factures prèvies amb una altra clau; `fact_rels` i les inscripcions són necessaris per detectar cobertura. Aquest control i el bloqueig dels intents TPV individuals no estan implementats pel servei `InvoiceBeforePaymentService` aïlladament.

```plantuml
@startuml
left to right direction
actor "Empresa / responsable" as E
actor "Operador autoritzat" as O
actor "Pagador que confirma ingrés" as P
rectangle "SIF PrisMa — empresa i participants" {
 usecase "UC-21\nDefinir receptor i cobertura\nde N inscripcions" as Main
 usecase "Comprovar factura preexistent\ni intencions pendents" as Coverage
 usecase "UC-04\nEmetre factura real\nsense cobrament" as Before
 usecase "UC-02 / UC-22\nRegistrar cobrament\nposterior sobre el mateix UUID" as Later
 usecase "UC-33\nDesactivar URL individual\nincompatible" as Revoke
 usecase "UC-74\nClassificar canvi de participants\ndesprés d'emetre" as Change
}
E --> Main
O --> Main
Main ..> Coverage : <<include>> [OBJECTIU]
O --> Before
P --> Later
O --> Revoke
O --> Change
note bottom of Later
 L'ingrés posterior NO és part
 de la transacció d'UC-04.
end note
@enduml
```

```mermaid
sequenceDiagram
autonumber
actor E as Empresa o responsable
actor O as Operador
participant UI as Intranet [ADAPTADOR PENDENT]
participant G as Guard cobertura + lock compartit [IMPLEMENTAT CURS]
participant IR as Factures/relacions SIF [SQL existent]
participant T as Intencions Redsys individuals
participant B as InvoiceBeforePaymentService [PHP]
participant P as PaymentService [PHP]
participant Inc as Incidències [worker 409→INCIDENT]
E->>O: Sol·licitar una factura de les inscripcions I1…IN
O->>UI: Introduir receptor fiscal, participants i import
UI->>G: Recalcular al servidor snapshot i comprovar permisos
G->>IR: Rellegir factures existents per ID_INSC, receptor i obligació
G->>T: Identificar intencions TPV individuals ja iniciades
alt Factura equivalent ja existeix i no hi ha un cobrament nou
 G-->>UI: UUID_FACTURA existent, cap nova emissió
 UI-->>O: Consultar factura preexistent
else Existeix conflicte de cobertura, receptor o callback en curs
 G->>Inc: Obrir incidència i preservar referències de TPV
 G-->>UI: Suspensió de nova emissió fins a conciliar
 UI-->>O: No duplicar factura ni descartar ingrés bancari
else Cobertura nova i coherent amb intencions incompatibles controlades
 G-->>UI: Snapshot i petició fiscal autoritzats
 UI->>B: issueBeforePayment(payload sense payment)
 B-->>UI: UUID_FACTURA després del COMMIT de UC-04
 UI-->>E: Factura real pendent, PDF només si disponible i autoritzat
 opt Arriba pagament confirmat més tard
  E->>O: Comunicar transferència o pagament real
  O->>UI: Verificar origen, saldo i mateixa factura
  UI->>P: registerPayment(payload assignat al UUID_FACTURA existent)
  P-->>UI: UUID_PAYMENT després del COMMIT propi
  UI-->>E: Confirmació econòmica, no segona factura
 end
end
Note over G,Inc: Guard Redsys CURS i concurrència exacta estan implementats; l'autenticació externa del receptor continua pendent.
```

### 4.4. Acció pròpia: ingrés parcial d'empresa i atribució entre participants — IMPLEMENTAT EN FLUX MANUAL

Quan una empresa paga parcialment una factura que cobreix diverses inscripcions, `PaymentService` manté un únic `UUID_PAYMENT` assignat a la factura i `JointInvoiceEnrollmentFundAllocationService` exigeix un repartiment explícit `ID_INSC → import`. No es dedueix de `fact_rels` ni es reparteix equitativament per defecte. `enrollment_fund_movement` conserva línia fiscal, participant i import de cada part.

```mermaid
sequenceDiagram
autonumber
actor O as Gestió de cobraments
participant R as Conciliació bancària/TPV [CANAL PENDENT]
participant G as Validació de cobertura/imports [IMPLEMENTAT]
participant P as PaymentService [PHP]
participant L as enrollment_fund_movement [IMPLEMENTAT]
participant DB as BD fiscal SIF
O->>R: Confirmar ingrés de l'empresa per factura F i N participants
R-->>O: Prova d'un únic fet extern, import i pagador
O->>G: Revalidar factura F i parts atribuïbles per I1…IN
alt Import/participant no concorden o ingrés ja registrat contradictòriament
 G-->>O: Conflicte o recuperació d'UUID_PAYMENT existent
else Un únic ingrés real nou amb parts justificades
 G-->>O: Moviment extern i assignació a F validats
 O->>P: registerPayment(CHARGE real, allocation F)
 P->>DB: BEGIN, INSERT payment_transaction i payment_allocation
 P->>DB: COMMIT
 P-->>O: UUID_PAYMENT únic
 O->>L: Registrar distribució explícita ID_INSC→import
 L-->>O: Moviments idempotents per línia/participant
end
Note over P,L: El cobrament és realitat econòmica i es confirma primer; el ledger és post-commit idempotent. Si falla, el reintent completa la projecció sense eliminar el cobrament.
```

| ID de prova pendent | Escenari | Resultat exigible |
| --- | --- | --- |
| EM-21-01 | Mateixes inscripcions amb factura prèvia sota una altra clau | Recuperar factura existent o crear incidència; cap segon número fiscal. |
| EM-21-02 | Grup de 3 participants, empresa paga una part | Un `UUID_PAYMENT` per ingrés, factura comuna i atribució explícita exacta per participant; rebutjar si la suma no coincideix. |
| EM-21-03 | Intenció individual Redsys anterior a la factura d'empresa i callback tardà | Conciliar diner real sense segona factura ni perdre la reserva/estat de participant. |
| EM-21-04 | Empresa i participant tenen NIF diferent, accés al PDF des del portal | Només receptor/representant legítim autoritzat, mai exposició automàtica a tots els participants. |
| EM-21-05 | Reús de clau amb receptor o línies diferents | Rebuig per conflicte de contingut abans de donar equivalència; control pendent al nucli actual. |
## 5. Traçabilitat

[Fitxa anterior UC-21](../06-fitxes-funcionals/uc-021.md) · [Catàleg de casos](../04-estat-final/33-casos-us-sif.md) · [Fluxos de factura abans de cobrar i grup](../03-canvis-pendents/04-fluxos-facturacio.md) · [UC-04 revisada](uc-004-emetre-factura-abans-cobrar.md) · [UC-02 revisada](uc-002-registrar-cobrament-factura.md) · [InvoiceBeforePaymentService](../../sif/src/Service/InvoiceBeforePaymentService.php) · [ManualGroupInvoiceService](../../sif/src/Service/ManualGroupInvoiceService.php) · [LegacyGroupSnapshotRepository](../../sif/src/Repository/LegacyGroupSnapshotRepository.php) · [InvoiceBeforePaymentServiceTest](../../sif/tests/Integration/InvoiceBeforePaymentServiceTest.php).

**Pendent:** validació de receptor/pagador per cada cas real, congelació de participants i descomptes al flux previ, permisos d'empresa, prova d'extrem a extrem, enllaços segurs i conciliació del cobrament.


## 6. Diagrames ACTUAL / FINAL reconciliats per pàgina i apartat

### 6.1. Pàgina intranet `alumnes-genera-factura-abans-pagar.php` — ACTUAL

```mermaid
flowchart TD
 A[Obrir pantalla amb sessió] --> B[JS carrega token CSRF i entitats autoritzades]
 B --> C[Cercar NIF/NIE i seleccionar inscripcions]
 C --> D[Escollir entity_id]
 D --> E[POST preview al proxy intranet]
 E --> F[API SIF rellegeix inscripcions, curs i receptor]
 F --> G[Retorna línies, totals i fingerprint]
 G --> H{Usuari confirma sense canvis?}
 H -- no --> E
 H -- sí --> I[POST confirm amb expected_fingerprint]
 I --> J{Fingerprint i cobertura vàlids?}
 J -- no --> K[409 + nou preview]
 J -- sí --> L[Factura SIF real PENDING]
```

**Implementat:** cerca/selecció UI, receptor per ID intern, camps autoritatius read-only, preview, confirmació, control de 409 i presentació de UUID/número.  
**Pendent:** servir PDF/document per UUID, accés extern i integració explícita del cobrament posterior.

### 6.2. Proxy intranet `ajax/alumnes/sifFacturaAbansPagar.php` — ACTUAL

```mermaid
flowchart TD
 A[POST JSON] --> B{Sessió vàlida?}
 B -- no --> X1[401]
 B -- sí --> C[Resoldre actor i rol de la pàgina]
 C --> D{CSRF vàlid?}
 D -- no --> X2[403]
 D -- sí --> E[Normalitzar IDs, entity_id i observacions]
 E --> F[SifInternalApiClient: petició signada]
 F --> G{Resposta SIF}
 G -- 401/0/5xx --> X3[502 fail closed]
 G -- 4xx --> X4[Propagar estat funcional]
 G -- 2xx --> H[Retornar JSON]
```

### 6.3. API SIF `public/api/factures/before-payment.php` — ACTUAL

```mermaid
flowchart TD
 A[POST raw body] --> B[InternalApiAuthenticator]
 B --> C[InternalInvoiceBeforePaymentScopeResolver]
 C --> D[Construir repositoris legacy i SIF]
 D --> E{action}
 E -- preview --> F[CommandService.preview]
 F --> G[LegacyPreparationService]
 G --> H[SelectionRepository + BillingPartyRepository]
 H --> I[ServerPayloadAssembler + PayloadBuilder]
 I --> J[Retornar fingerprint, línies i totals]
 E -- confirm --> K[CommandService.confirm]
 K --> L[Repetir preparació autoritativa]
 L --> M{hash_equals expected fingerprint?}
 M -- no --> N[409 conflict]
 M -- sí --> O[InvoiceBeforePaymentService]
 O --> P[InvoiceService transaccional]
 P --> Q[Factura + línies + registre + queue + fact_rels]
 Q --> R[Claim invoice_before_payment_coverage]
 R --> S[COMMIT i resposta UUID/num]
```

### 6.4. Flux FINAL objectiu UC-021

```mermaid
flowchart TD
 A[Empresa/responsable demana assumir N inscripcions] --> B[Operador o portal autoritzat]
 B --> C[Snapshot server-side de participants, receptor, imports i descomptes]
 C --> D[Guard cross-channel: factures + coverage + pagaments + Redsys intents/callbacks]
 D --> E{Conflicte o diner ja confirmat?}
 E -- sí --> F[Conciliació/incidència; no emetre]
 E -- no --> G[Preview + fingerprint]
 G --> H[Confirmar]
 H --> I[Factura fiscal única PENDING]
 I --> J[Document per UUID amb ACL receptor/representant]
 I --> K[Desactivar o bloquejar vies individuals incompatibles]
 K --> L[Pagament real posterior]
 L --> M[PaymentService sobre mateix UUID_FACTURA]
 M --> N[PARTIAL o PAID]
 N --> O[Distribució quantitativa immutable per ID_INSC]
 O --> P[Notificacions separades receptor/participants]
```

## 7. Diagrama de classes ACTUAL reconciliat

```mermaid
classDiagram
direction LR
class IntranetPageJS
class SifInvoiceBeforePaymentAccess
class SifInternalApiClient
class InternalApiAuthenticator
class InternalInvoiceBeforePaymentScopeResolver
class InvoiceBeforePaymentCommandService
class InvoiceBeforePaymentLegacyPreparationService
class InvoiceBeforePaymentSelectionRepository
class InvoiceBeforePaymentBillingPartyRepository
class InvoiceBeforePaymentServerPayloadAssembler
class InvoiceBeforePaymentPayloadBuilder
class InvoiceBeforePaymentService
class InvoiceService
class InvoiceBeforePaymentCoverageRepository
class InvoiceRepository
class PaymentService

IntranetPageJS --> SifInvoiceBeforePaymentAccess : sessió/CSRF/rol
IntranetPageJS --> SifInternalApiClient : preview/confirm via proxy
SifInternalApiClient --> InternalApiAuthenticator : signatura API
InternalApiAuthenticator --> InternalInvoiceBeforePaymentScopeResolver : actor/rol
InternalInvoiceBeforePaymentScopeResolver --> InvoiceBeforePaymentCommandService
InvoiceBeforePaymentCommandService --> InvoiceBeforePaymentLegacyPreparationService
InvoiceBeforePaymentLegacyPreparationService --> InvoiceBeforePaymentSelectionRepository
InvoiceBeforePaymentLegacyPreparationService --> InvoiceBeforePaymentBillingPartyRepository
InvoiceBeforePaymentLegacyPreparationService --> InvoiceBeforePaymentServerPayloadAssembler
InvoiceBeforePaymentLegacyPreparationService --> InvoiceBeforePaymentPayloadBuilder
InvoiceBeforePaymentCommandService --> InvoiceBeforePaymentService : confirm
InvoiceBeforePaymentService --> InvoiceService
InvoiceService --> InvoiceRepository
InvoiceService --> InvoiceBeforePaymentCoverageRepository
PaymentService ..> InvoiceService : flux posterior independent
```

## 8. Activitat FINAL: cobrament posterior i protecció de participants

```mermaid
flowchart TD
 A[Factura conjunta existent] --> B[Arriba transferència/TPV]
 B --> C[Identificar UUID_FACTURA i pagador real]
 C --> D[Comprovar idempotència i saldo pendent]
 D --> E{Import admissible?}
 E -- no --> F[Conflicte / incidència]
 E -- sí --> G[Registrar payment_transaction]
 G --> H[Registrar payment_allocation a la factura]
 H --> I[Recalcular PENDING/PARTIAL/PAID]
 I --> J[Distribuir import per ID_INSC segons decisió explícita]
 J --> K[Persistir ledger individual immutable]
 K --> L[Notificar receptor; participants només estat propi]
```

**Implementat:** D per flux manual, J/K amb repartiment explícit sobre `enrollment_fund_movement`, i ACL fail-closed al SIF. **Pendent:** autenticació externa del receptor i canals d'empresa no manuals.

## 9. Verificació i mancances després de l'auditoria

- **Verificat per codi/tests:** selecció server-side, receptor per ID, construcció de línies per participant, totals, preview/fingerprint, confirmació, idempotència de payload, cobertura única UC-04, factura sense cobrament inicial, cobrament genèric posterior sense segon registre fiscal.
- **Evidència CI:** el workflow `UC-004 SIF secure flow checks` té execucions verdes anteriors. El SHA actual de `main` (`b0e8ff7`) té la suite general `SIF PHP MySQL tests` fallida; això impedeix etiquetar el cas complet com a verificat en l'estat actual.
- **Corregit en aquesta branca:** la factura conjunta deixa de marcar les relacions dels participants com `visible_alumne=1`; ara és `0` per defecte i hi ha assert de test.
- **Pendent crític:** autenticador/portal extern del receptor, prova final del nou lock concurrent en CI/preproducció, E2E de cobrament manual amb repartiment per participant, i conciliació operativa si falla una projecció post-commit.
