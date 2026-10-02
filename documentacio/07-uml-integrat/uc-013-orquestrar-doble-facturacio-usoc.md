# UC-13 · Orquestrar la doble facturació USOC — fitxa i UML integrats

**Objectiu:** conservar **dues obligacions/factures diferenciades** per una mateixa inscripció USOC: la part que paga l'alumne i la part que correspon a l'entitat. **No** confondre una única inscripció amb una única factura, ni interpretar un pagament Redsys de l'alumne com si hagués cobrat també la part de l'entitat.

**Estat contrastat:** existeixen `RedsysUsocInvoiceService`, `UsocEntityInvoiceService`, `UsocFinancingCaseRepository`, `UsocEntityPaymentService`, `UsocCaseReconciler`, `UsocValidationDecisionService` i `UsocValidationDecisionRepository`. La decisió manual legacy es coordina amb el SIF mitjançant `REQUESTED/COMMITTED/REVIEW_REQUIRED`; la factura alumne crea el checkpoint financer; la factura entitat exigeix aquest checkpoint abans d'emetre; i el cobrament entitat reconcilia l'expedient. No es presumeix una transacció distribuïda única entre BD legacy, SIF, Redsys i comunicacions.

## 1. Fitxa funcional del cas mare

| Element | Contracte |
| --- | --- |
| Actors | Alumne/pagador, entitat USOC com a receptor/pagador de la seva part, operador autoritzat de facturació, Redsys i worker SIF. |
| Precondició segons builder | `inscription.TIPUS_DESC=4` i `VALID_DESC=1`; `LegacyUsocInvoicePayloadBuilder` i repositori rebutgen una inscripció no classificada/validada com USOC. La comprovació de justificants i afiliació completa és UC-19 separada. |
| Identitat comercial | Una inscripció `ID`, `IDPAG`, curs i edició, import pagat per alumne i import corresponent a l'entitat **separats en el snapshot**. |
| Resultat part alumne | Factura a nom de l'alumne amb descompte USOC i import alumne; `payment_transaction CHARGE` Redsys només per aquest import, amb `UUID_FACTURA`/ `UUID_PAYMENT` associats. |
| Resultat part entitat | Factura fiscal distinta amb dades `billing` explícites de l'entitat i import de l'entitat; `UsocEntityInvoiceService` emet **sense registrar cobrament inicial**, deixa `payment_registered=false` i conserva `student_invoice_uuid`. |
| Resultat complet | Les dues factures i imports correlacionats a **una inscripció**, amb estat de cobrament propi per cadascuna i la part de l'entitat registrada com a deute fins a la confirmació d'un cobrament posterior real. |
| Traça de fons per inscripció | Un registre d'atribució de l'import de l'alumne quan arriba Redsys; un segon registre referenciat a **un altre pagament real** si l'entitat cobra després. No crear `CHARGE` fictici per l'import pendent. |

### 1.1. Flux objectiu amb serveis verificats

1. El procés comercial valida que l'operació és USOC i congela `student_amount`, `entity_amount`, receptor fiscal de cadascuna i `ID_INSC`; la validació de l'afiliació/condicions no es presumeix completada pel simple `TIPUS_DESC`.
2. L'alumne confirma compra i es crea intenció Redsys `SOURCE_TYPE=USOC_ALUMNE` amb snapshot, incloent **import de l'entitat positiu**, i import Redsys previst **només** de la part alumne (UC-63).
3. UC-03 valida el callback i `RedsysUsocInvoiceService::issueFromIntentSnapshot()` exigeix `usoc.entity_amount` positiu, construeix la factura de part alumne via `LegacyUsocInvoicePayloadBuilder::buildStudentPayload()`, incorpora el cobrament validat i crida `InvoiceService`.
4. Retorna `UUID_FACTURA` de l'alumne i dades `entity_invoice_pending`: `source_type=USOC_ENTITAT`, `entity_amount`, `student_invoice_uuid`, `idpag`, `requires_explicit_billing=true`. **Això no és una factura ja emesa a l'entitat.**
5. El canal de gestió recull **dades fiscals explícites de l'entitat** i confirma import/relació. `UsocEntityInvoiceService::issueEntityFromExplicitInput()` exigeix `idpag`, `student_amount`, `amount`, `student_invoice_uuid` i `billing.name/nif`, recarrega snapshot validat i emet la factura de l'entitat. Retorna `payment_registered=false`.
6. El pagament posterior de l'entitat segueix UC-02/22/24 segons el canal, imputat a la factura de l'entitat, sense tornar a emetre-la. Cada factura manté estat fiscal i de cobrament propi.
7. El registre monetari proposat atribueix a una sola inscripció **dos imports d'origen diferent**: part alumne quan el `CHARGE` Redsys s'ha confirmat, i part entitat **només** quan s'ha confirmat el seu ingrés. La suma d'atribucions ha de reconciliar-se amb els moviments originals i les dues factures, sense duplicar imports.
8. Un canvi de curs, baixa o rectificativa ha de tenir present **ambdues factures i pagadors**: no retornar diners de l'entitat a l'alumne ni reduir la factura de l'alumne per un deute de l'entitat sense decisió motivada.

### 1.2. Alternatives i incerteses que exigeixen decisions específiques

| Cas | Regla |
| --- | --- |
| Alumne paga i encara falta factura de l'entitat | Factura/pagament alumne confirmats; expedient USOC parcial, `entity_invoice_pending`; no marcar tot el cas complet ni crear cobrament entitat. |
| Dades fiscals de l'entitat absents | `UsocEntityInvoiceService` rebutja; cap factura fiscal a receptor deduït automàticament. |
| `TIPUS_DESC` diferent de 4 o `VALID_DESC` diferent de 1 | Constructor i repositori USOC rebutgen en les rutes consultades. |
| Callback alumne duplicat | Reutilitza factura i pagament de l'alumne, no genera repetidament factura entitat. |
| Factura entitat existent per mateix origen | La clau base del builder entitat combina inscripció i UUID de factura alumne; exigeix conciliació d'import/receptor davant un reintent amb entrada contradictòria. |
| Import de l'entitat que no s'arriba a cobrar | Es conserva deute o incidència; no crear `EXTERNAL → INSCRIPCIÓ` per l'import no ingressat. |
| Fracció entitat, subvenció alternativa o modificació posterior | Cal decidir receptor, concepte, import i classificació del finançament, sense canviar les factures emeses en lloc; UC-05/71/72 quan correspongui. |
| Factura i cobrament en BDs/canals diferents | No s'ha acreditat una transacció única alumne+entitat+inscripció; usar correlació, idempotència i conciliació entre fases. |

**Proves executades:** el flux de doble facturació/cobrament està cobert per `UsocEndToEndFlowTest`; la decisió durable legacy↔SIF està coberta per `UsocValidationDecisionServiceTest`, inclosos retries, conflictes i deriva post-commit. La validació navegador/preproducció continua separada de les proves d'integració.

### 1.2.a. Seqüència durable de validació USOC

```mermaid
sequenceDiagram
autonumber
actor G as Gestió
participant UI as Intranet validar descomptes
participant L as LegacyDiscountValidationLookup
participant C as SifInternalUsocClient
participant API as /api/usoc/manage.php
participant V as UsocValidationDecisionService
participant S as usoc_validation_decision
participant DB as Legacy inscripcions
G->>UI: Sí/No + requestId
UI->>L: isUsoc(ID_INSC)
alt No és TIPUS_DESC=4
 L-->>UI: false
 UI->>DB: flux legacy normal
else És USOC
 L-->>UI: true
 UI->>C: beginValidationDecision(requestId, ID_INSC, desired)
 C->>API: POST signat HMAC
 API->>V: begin(...)
 V->>DB: llegir TIPUS_DESC/VALID_DESC
 V->>S: crear/reutilitzar REQUESTED
 alt Retry i legacy ja coincideix
  V->>S: COMMITTED
  V-->>UI: should_apply_legacy=false
 else Estat contradictori
  V->>S: REVIEW_REQUIRED
  V-->>UI: conflicte
 else Pendent coherent
  V-->>UI: should_apply_legacy=true
  UI->>DB: aplicar VALID_DESC=1/2 + comunicació
  UI->>C: completeValidationDecision(requestId)
  C->>API: POST signat HMAC
  API->>V: complete(...)
  V->>DB: rellegir estat real
  alt Coincideix
   V->>S: COMMITTED + hash legacy
  else Divergeix
   V->>S: REVIEW_REQUIRED
  end
 end
end
```

**Recuperació:** `reconcile-usoc-validation-decisions.php` llegeix només registres `REQUESTED`, contrasta el legacy i completa `COMMITTED` o deixa evidència de revisió/error sense repetir el correu ni la mutació original.

### 1.3. Validació manual, import de referència i curs gratuït USOC — contrast amb el circuit de PrisMa

**U-VAL — validació abans del descompte:** el circuit descrit identifica el descompte «Afiliat USOC» amb `TIPUS_DESC=4`, però la persona que el demana pot estar pendent de comprovació (`VALID_DESC=0`). La intranet ha de confirmar manualment l'afiliació amb USOC abans d'establir `VALID_DESC=1` i permetre la compra/facturació amb aquest descompte (UC-19). Si no es confirma, el circuit ha de recalcular l'import de compra abans de l'emissió; si ja existeix factura, no corregir-ne l'import amb un UPDATE silenciós. Que el builder exigeixi `VALID_DESC=1` no acredita que la comprovació externa s'hagi dut a terme.

**U-IMPORT — import observat, no tarifa universal:** el xat original i els fluxos del projecte descriuen com a cas habitual un primer pagament de **10 € de l'alumne** i el pagament de la diferència per USOC; el descompte d'afiliació es descriu com del **25 %**. Aquests valors han de sortir del preu i de les condicions confirmades **de l'operació concreta**; no es poden codificar com a constants universals en el SIF ni inferir l'import USOC simplement restant `A_PAGAR - PAGAMENT` d'un registre viu. Conservar snapshot d'import base, import de l'alumne, import assumit per USOC, descompte, condició validada, data/usuari de validació i receptors fiscals diferents.

**U-GRATUÏT — variant històrica «Altres: Curs gratuït USOC»:** el xat també descriu aquest circuit especial i el paràmetre `anticipi-preu-usoc`. No presumir que segueixi la regla 10 € + diferència, ni que pugui passar directament per `RedsysUsocInvoiceService`: la ruta actual de UC-19a/19b exigeix `student_amount > 0` i `entity_amount > 0` i, per tant, **no acredita la tramitació d'un import d'alumne zero**. Cal recuperar les condicions i els imports exactes del cas especial, decidir receptor i factura(s) corresponents i preparar un circuit fiscal validat abans d'adaptar la implementació; cap factura de 0 € o CHARGE fictici no es crea per completar artificialment les dues parts.

**U-ORIGEN — deute diferent d'ingrés:** emetre la factura USOC encara pendent no atribueix fons de l'entitat a la inscripció. Cada cobrament parcial de l'entitat es vincula exclusivament a la factura USOC, mentre que el CHARGE inicial Redsys de l'alumne continua pertanyent a la factura alumne. Un canvi/baixa ha de consultar les dues factures i titularitats abans de decidir reassignacions, retorns o saldos.

### 1.4. Proves d'acceptació afegides (no executades)

| ID | Cas | Resultat exigible |
| --- | --- | --- |
| US-01 | Alumne sol·licita USOC i VALID_DESC continua a 0 | Cap emissió USOC amb descompte fins a validació manual acreditada. |
| US-02 | Afiliació denegada abans de facturar | Recalcular compra sense descompte i no crear dues factures USOC fictícies. |
| US-03 | Cas habitual amb primer ingrés alumne de 10 € | Snapshot d'import de cada part i dues factures diferenciades, només el cobrament real de l'alumne al començament. |
| US-04 | «Curs gratuït USOC» amb part d'alumne igual a 0 | Circuit especial pendent de decisió; no forçar builder que exigeix imports positius ni CHARGE de 0 €. |
| US-05 | Entitat encara no ha pagat o paga parcialment | Factura entitat pendent/PARTIAL segons moviment real, factura alumne intacta. |
| US-06 | Baixa/canvi quan alumne ha pagat i USOC deu la seva part | No retornar diners no cobrats ni confondre titulars o rectificar automàticament les dues factures. |
## 2. Diagrama UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Alumne / pagador" as A
actor "Empresa/entitat USOC" as U
actor "Operador facturació" as O
actor "Redsys" as Bank
rectangle "SIF PrisMa · USOC" {
 usecase "UC-13\nOrquestrar doble facturació" as Main
 usecase "UC-19\nValidar afiliació/condicions" as Check
 usecase "UC-19a\nFacturar i cobrar part alumne" as Student
 usecase "UC-19b\nFacturar part entitat" as Entity
 usecase "UC-02\nCobrar part entitat posterior" as Later
}
A --> Main
U --> Main
O --> Main
Bank --> Student
Main ..> Check : <<include>> (prerequisit funcional)
Main ..> Student : <<include>>
Main ..> Entity : <<include>> (fase posterior)
O --> Later
note bottom of Later
 El pagament de l'entitat no
 neix automàticament amb la factura.
end note
@enduml
```

### Vista de casos d’ús per a GitHub (Mermaid)

```mermaid
flowchart LR
  actor_0["Alumne / pagador"]
  actor_1["Empresa/entitat USOC"]
  actor_2["Operador facturació"]
  actor_3["Redsys"]
  subgraph SIF_BOX["SIF PrisMa · USOC"]
    uc_0(["UC-13<br/>Orquestrar doble facturació"])
    uc_1(["UC-19<br/>Validar afiliació/condicions"])
    uc_2(["UC-19a<br/>Facturar i cobrar part alumne"])
    uc_3(["UC-19b<br/>Facturar part entitat"])
    uc_4(["UC-02<br/>Cobrar part entitat posterior"])
  end
  actor_0 --> uc_0
  actor_1 --> uc_0
  actor_2 --> uc_0
  actor_3 --> uc_2
  uc_0 -.->|include| uc_1
  uc_0 -.->|include| uc_2
  uc_0 -.->|include| uc_3
  actor_2 --> uc_4
```

## 3. Subdiagrama de classes — dos handlers sense orquestrador fictici

```mermaid
classDiagram
direction LR
class RedsysUsocInvoiceService {
 +issueFromIntentSnapshot(db,dsOrder,snapshot) array
 +issueStudentFromValidatedNotification(sifDb,legacyDb,dsOrder,usocAmount) array
}
class UsocEntityInvoiceService {
 +issueEntityFromExplicitInput(legacyDb,input) array
}
class LegacyUsocSnapshotRepository {
 +loadByIdpag(legacyDb,idpag,studentAmount,entityAmount) array
}
class LegacyUsocInvoicePayloadBuilder {
 +buildStudentPayload(snapshot) array
 +buildEntityPayload(snapshot,input) array
}
class RedsysInvoicePayloadBuilder {
 +buildFromValidatedNotification(db,dsOrder,payload) array
}
class InvoiceService {
 +issueInvoice(payload) array
}
class PaymentService {
 +registerPayment(payload) array
}
class EnrollmentFundMovementRepository {
 <<PROPOSTA no implementada>>
 +append(db,movement) string
}
RedsysUsocInvoiceService --> LegacyUsocSnapshotRepository : ruta legacy
RedsysUsocInvoiceService --> LegacyUsocInvoicePayloadBuilder : part alumne
RedsysUsocInvoiceService --> RedsysInvoicePayloadBuilder : CHARGE Redsys
RedsysUsocInvoiceService --> InvoiceService : factura alumne
UsocEntityInvoiceService --> LegacyUsocSnapshotRepository : snapshot validat
UsocEntityInvoiceService --> LegacyUsocInvoicePayloadBuilder : part entitat
UsocEntityInvoiceService --> InvoiceService : factura sense payment
```

`PaymentService` és el servei de cobrament de la factura entitat **posterior i independent**; no es dibuixa una dependència directa fictícia des de `UsocEntityInvoiceService`.

## 4. Seqüència principal — dues factures, cobraments separats

```mermaid
sequenceDiagram
autonumber
actor A as Alumne
participant Web as Ecommerce [adaptador pendent]
participant Bank as Redsys
participant Worker as Worker asíncron
participant SA as RedsysUsocInvoiceService
participant B as LegacyUsocInvoicePayloadBuilder
participant I as InvoiceService
participant UI as Intranet gestió USOC [IMPLEMENTADA · UI autònoma + panell contextual]
participant SE as UsocEntityInvoiceService
participant Pay as PaymentService
participant L as EnrollmentFundMovementRepository [PROPOSTA]
A->>Web: Confirmar part alumne i snapshot USOC
Web->>Bank: Redirecció amb intent USOC_ALUMNE
Bank->>Worker: Callback validat i job asíncron [via UC-03]
Worker->>SA: issueFromIntentSnapshot(snapshot,DS_ORDER)
SA->>B: buildStudentPayload(snapshot)
B-->>SA: Factura part alumne i descompte USOC
SA->>I: issueInvoice(payload + CHARGE alumne)
I-->>SA: UUID_FACTURA_ALUMNE, UUID_PAYMENT_ALUMNE
SA-->>Worker: entity_invoice_pending (import i UUID alumne)
opt Atribució alumne [DISSENY]
 Worker->>L: append(EXTERNAL→ID_INSC, import alumne, UUID_PAYMENT_ALUMNE)
end
UI->>SE: issueEntityFromExplicitInput(billing entitat,imports,UUID_FACTURA_ALUMNE)
SE->>B: buildEntityPayload(snapshot,input)
B-->>SE: Factura entitat sense payment
SE->>I: issueInvoice(payload entitat)
I-->>SE: UUID_FACTURA_ENTITAT
SE-->>UI: payment_registered=false
Note over UI,I: Import entitat pendent, encara cap cobrament d'entitat
UI->>Pay: registerPayment(CHARGE entitat) només si transferència confirmada
Pay-->>UI: UUID_PAYMENT_ENTITAT
opt Atribució entitat [DISSENY]
 UI->>L: append(EXTERNAL→ID_INSC, import entitat, UUID_PAYMENT_ENTITAT)
end
```

La fletxa simplificada de Redsys al worker **representa el camí via callback signat i cua descrit a UC-03**, no una crida directa de Redsys al worker ni l'existència d'un orquestrador USOC complet.

## 5. Diagrama d'estat de l'expedient (IMPLEMENTACIÓ PARCIAL + OBJECTIU)

```mermaid
stateDiagram-v2
 [*] --> PendentAlumne : USOC validat i intent creat
 PendentAlumne --> AlumneCobrat : callback autoritzat i factura alumne
 PendentAlumne --> Revisio : denegat o dades incoherents
 AlumneCobrat --> PendentEntitat : entity_invoice_pending
 PendentEntitat --> EntitatFacturada : emissió explícita amb billing entitat
 PendentEntitat --> Revisio : manca billing o discrepància
 EntitatFacturada --> EntitatParcial : cobrament parcial confirmat
 EntitatFacturada --> Tancat : cobrament íntegre confirmat i conciliat
 EntitatParcial --> Tancat : completar import pendent i conciliar
 Revisio --> PendentEntitat : dades corregides sense duplicar alumne
```

**Aquest diagrama és la proposta d'estats de l'expedient**, no una classe o taula de workflow `UsocOrchestrator` identificada en l'actual codi.

### 5.1. Acció independent: reconstruir l'expedient conjunt després de l'emissió de l'alumne — IMPLEMENTAT PARCIALMENT

**Disparador:** el worker ha confirmat factura/cobrament de l'alumne, però el resultat `entity_invoice_pending` no arriba al panell, s'ha perdut la resposta, o falta la factura de la part entitat. **Actor:** procés de conciliació USOC / gestió autoritzada. **Entrada:** `DS_ORDER`, `UUID_FACTURA_ALUMNE` i `ID_INSC` acreditats, imports i receptor de la intenció congelada, fets fiscals i bancaris SIF actuals. **Postcondició:** expedient reconstruït amb **dues línies de finançament** i estat separat per factura/pagament, amb pas pendent només per la part que falta; **no tornar a cobrar ni emetre la factura alumne** per recuperar les dades de la part entitat.

**Contrast de codi actualitzat 30/09/2026:** `RedsysUsocInvoiceService` persisteix `usoc_financing_case` després de la factura alumne. `LegacyUsocSnapshotRepository::loadByIdpag()` exigeix ara `ID_INSC` i consulta `IDPAG + ID`, sense `ORDER BY ID LIMIT 1`. `UsocEntityInvoiceService` exigeix `id_insc`; abans d'emetre, `UsocFinancingCaseRepository::requireForEntityInvoice()` comprova que existeixi el checkpoint amb la mateixa factura alumne i imports congelats, i `UsocStudentInvoiceLinkRepository` valida la relació amb `ID_INSC/IDPAG`. `UsocEntityPaymentService` registra el cobrament entitat i invoca automàticament `UsocCaseReconciler`. Continuen pendents l'E2E complet de negoci i el desplegament/configuració real de les UI.

```plantuml
@startuml
left to right direction
actor "Procés de conciliació USOC" as W
actor "Operador autoritzat" as O
rectangle "SIF PrisMa — UC-13 / RECUPERAR EXPEDIENT (IMPLEMENTAT PARCIAL)" {
 usecase "Reconciliar expedient després\nde factura alumne confirmada" as Reconcile
 usecase "Consultar factura i CHARGE alumne\nper ID_INSC + DS_ORDER" as Source
 usecase "Consultar si existeix factura entitat\ni deute/pagaments propis" as Entity
 usecase "UC-19b\nEmetre part entitat pendent" as Issue
 usecase "UC-02\nRegistrar ingrés entitat verificat" as Pay
}
W --> Reconcile
O --> Reconcile
Reconcile ..> Source : <<include>>
Reconcile ..> Entity : <<include>>
O --> Issue
O --> Pay
@enduml
```

```mermaid
sequenceDiagram
autonumber
actor O as Gestió/worker conciliació
participant C as UsocCaseReconciler [IMPLEMENTAT]
participant I as Factures/pagaments i fact_rels SIF [LECTURA]
participant L as Llegat inscripcions [LECTURA]
participant E as UsocEntityInvoiceService [PHP; UC-19b]
participant P as PaymentService [PHP; UC-02]
O->>C: recoverUsocCase(ID_INSC,DS_ORDER,UUID_FACTURA_ALUMNE)
C->>I: Verificar factura alumne A, UUID_PAYMENT real i DS_ORDER
C->>L: Contrastar ID_INSC concret, TIPUS_DESC/VALID_DESC i snapshot original
alt Alumne sense cobrament validat, UUID aliè o IDPAG ambigu
 I-->>C: Manca evidència/CONFLICT
 C-->>O: Incidència sense crear factura entitat ni un altre CHARGE alumne
else Factura i cobrament alumne confirmats
 C->>I: Cercar factura USOC_ENTITAT pel mateix cas i pagaments atribuïts
 alt Factura entitat E ja existeix
  I-->>C: UUID_FACTURA_ENTITAT i estat pendent/parcial/pagat
  C-->>O: Recuperar E i només les accions posteriors pendents
 else Encara no s'ha emès factura entitat
  I-->>C: Només factura alumne A confirmada
  C-->>O: Proposta UC-19b PENDING amb UUID A, cap segona emissió A
  opt Responsable valida receptor i import entitat [DISSENY]
   O->>E: issueEntityFromExplicitInput(legacyDb,input contrastat)
   E-->>O: UUID_FACTURA_ENTITAT, payment_registered=false
  end
 end
 opt Transferència entitat externa confirmada posteriorment
  O->>P: registerPayment(CHARGE entitat, UUID_FACTURA_ENTITAT)
  P-->>O: UUID_PAYMENT real, sense recrear factura alumne
 end
end
Note over C,P: Checkpoint i reconciliador implementats. La ruta específica `UsocEntityPaymentService` reconcilia després del cobrament entitat; el registre genèric de pagaments no incorpora aquest hook.
```

### 5.2. Acció independent: verificar i tancar l'expedient de finançament sense confondre dos pagadors — IMPLEMENTAT PARCIALMENT

**Disparador:** gestió vol marcar completat el finançament USOC d'una inscripció. **Precondicions:** factura alumne i factura entitat **diferents**, identitat fiscal/inscripció i import de cadascuna contrastats; assignacions de pagament reals per factura, i qualsevol `REFUND` o compensació posterior classificats per pagador. **Postcondició:** `FINANÇAMENT_CONCILIAT` com a **estat objectiu de l'expedient**, diferent d'estat acadèmic, certificat o acceptació AEAT; si la part entitat només està facturada o pagada parcialment, conservar deute i no declarar el cas complet.

```plantuml
@startuml
left to right direction
actor "Responsable gestió/cobraments" as O
rectangle "SIF PrisMa — UC-13 / TANCAMENT ECONÒMIC (IMPLEMENTAT PARCIAL)" {
 usecase "Conciliar dues parts USOC\ni estat del finançament" as Close
 usecase "Verificar factura i ingrés alumne" as Student
 usecase "Verificar factura i ingrés entitat" as Entity
 usecase "Contrastar imports per ID_INSC\ni moviments posteriors" as Funds
 usecase "UC-12\nGestionar deute entitat pendent" as Debt
}
O --> Close
Close ..> Student : <<include>>
Close ..> Entity : <<include>>
Close ..> Funds : <<include>>
O --> Debt
@enduml
```

```mermaid
sequenceDiagram
autonumber
actor O as Gestió
participant C as UsocCaseReconciler [IMPLEMENTAT]
participant F as Factures A/E i fact_rels SIF [LECTURA]
participant P as payment_transaction/allocation [LECTURA]
participant L as Inscripció/fons individuals [LECTURA/PROPOSTA]
O->>C: Reconciliar i tancar finançament per ID_INSC
C->>F: Consultar factura alumne A i entitat E amb receptors/imports
C->>P: Sumar CHARGE/REFUND/COMPENSATION acreditats per cada factura
C->>L: Verificar atribució quantitativa a ID_INSC i estat independent de matrícula
alt Falta factura entitat o no es pot acreditar relació amb A
 C-->>O: PENDING/CONFLICT, derivar UC-19b/53
else Part alumne real confirmada i part entitat només facturada
 C-->>O: FINANÇAMENT_PENDENT_ENTITAT, deute no convertit en CHARGE
else Ambdues parts cobrades i imports conciliats per origen
 C-->>O: FINANÇAMENT_CONCILIAT [estat d'expedient OBJECTIU]
end
Note over C,L: No hi ha coordinador o ledger per ID_INSC acreditat: un PAYMENT alumne no acredita que USOC hagi pagat la seva factura.
```

| Prova pendent | Escenari | Resultat exigible |
| --- | --- | --- |
| UO-13-07 | Factura/CHARGE alumne confirmats i resposta `entity_invoice_pending` perduda | Recuperar UUID alumne i estat de la part entitat sense segon CHARGE ni altra factura alumne. |
| UO-13-08 | IDPAG compartit amb diversos inscrits; factura alumne vinculada a un ID_INSC diferent del primer llegat | Bloquejar i reconciliar identitat, no facturar per al primer ID automàticament. |
| UO-13-09 | Factura entitat existent però encara sense transferència acreditada | Recuperar factura/deute, no tornar a emetre-la ni declarar finançament complet. |
| UO-13-10 | Entitat paga només una fracció del seu import | Expedient parcial i pendent restant, amb pagament alumne intacte. |
| UO-13-11 | Una factura alumne i una entitat amb dos pagaments reals diferents | Finançament conciliable només quan ambdós imports i orígens estan contrastats per ID_INSC; cap duplicació del moviment alumne. |

### 5.3. Planner de canvi/baixa per dos pagadors — IMPLEMENTAT

`UsocLifecyclePlanService` consumeix el `payer_snapshot` del guard i genera una acció independent per:
- pagador alumne;
- pagador entitat.

Invariants implementades:
- no creuar fons entre pagadors;
- no retornar imports no cobrats;
- rectificar cada factura de manera independent;
- mantenir bloquejada la mutació legacy fins que existeixi un flux SIF resolt.

Per una factura encara no emesa, el planner retorna `invoice_action=NONE`, `economic_action=NONE` i `max_refundable=0.00`. Per una factura amb cobraments reals, `max_refundable` queda limitat a `net_paid`, no al total nominal facturat.

La pantalla `alumnes-usoc-financament.php` exposa aquest pla en mode preview mitjançant `lifecycle_plan`; no executa cap rectificativa ni refund.

**Proves CI:** runs `36733404401` i `36733404387`, **744 passed / 0 failed**.

## 6. Traçabilitat

[UC-13 original](../06-fitxes-funcionals/uc-013.md) · [UC-19a original](../06-fitxes-funcionals/uc-019a.md) · [UC-19b original](../06-fitxes-funcionals/uc-019b.md) · [Revisió fons inscripció](00-revisio-moviments-inscripcions.md) · [RedsysUsocInvoiceService](../../sif/src/Service/RedsysUsocInvoiceService.php) · [UsocEntityInvoiceService](../../sif/src/Service/UsocEntityInvoiceService.php) · [LegacyUsocInvoicePayloadBuilder](../../sif/src/Service/LegacyUsocInvoicePayloadBuilder.php) · [LegacyUsocSnapshotRepository](../../sif/src/Repository/LegacyUsocSnapshotRepository.php) · [RedsysUsocInvoiceServiceTest](../../sif/tests/Integration/RedsysUsocInvoiceServiceTest.php) · [UsocEntityInvoiceServiceTest](../../sif/tests/Integration/UsocEntityInvoiceServiceTest.php).

**No acreditat encara:** classificació d'afiliació externa completa, desplegament real de les UI, secrets/rols d'entorn, variant curs gratuït i prova E2E completa. **Implementat al repositori:** identitat `ID_INSC`, validació factura alumne, checkpoint `usoc_financing_case`, emissió entitat protegida per checkpoint, `UsocEntityPaymentService`, `UsocCaseReconciler`, API interna signada, pantalla autònoma i panell contextual.


## 7. Auditoria específica 29/09/2026

### 7.1. Correcció sobre idempotència

Després del contrast de `InvoiceService` i `PayloadIdempotencyValidator`, el risc de reutilitzar silenciosament una factura amb la mateixa clau i un payload fiscal diferent **no és pendent** al nucli actual: abans de reutilitzar, `InvoiceService::existingResultWithPaymentIfPresent()` executa `assertMatches()` contra `IDEMPOTENCY_PAYLOAD_HASH`.

Per tant:
- mateix payload + mateixa clau → reutilització idempotent;
- mateixa clau + import/receptor/payload diferent → `CONFLICT`.

Es manté una prova específica UC-013 per impedir regressions.

### 7.2. Superfície ACTUAL incorporada a l'auditoria

El flux llegat contrastat abans del SIF és:

```mermaid
sequenceDiagram
autonumber
actor A as Alumne
participant W as Web inscripció USOC
participant J as mostrarInscripcionsAfiliats.min.js
participant E as enviarInscripcioAfiliat.php
participant DB as inscripcions
participant U as FEUSOC
participant G as Intranet validar descomptes
participant I as Intranet::sendMsgValidatCurosDescomptes
A->>W: Omplir dades i marcar afiliació USOC
W->>J: Formulari
J->>E: Enviar inscripció
E->>DB: INSERT TIPUS_DESC=4, VALID_DESC pendent
E->>U: Sol·licitar confirmació afiliació
G->>DB: Consultar VALID_DESC=0
G->>I: Decisió Sí/No
alt Validada
 I->>DB: VALID_DESC=1
 I-->>A: Instruccions de pagament
else Denegada
 I->>DB: VALID_DESC=2 i possible nou A_PAGAR
 I-->>A: Comunicar no aplicació USOC
end
```

### 7.3. Diagrames d'activitat per pàgina

La cobertura RM-037 completa del UC-013 s'ha separat en:

[UC-013 · activitats ACTUAL/FINAL per pàgina i apartat](uc-013-activitats-pagines-actual-final.md)

Inclou:
- pàgina informativa USOC;
- formulari dades personals;
- dades curriculars;
- curs/checkbox USOC;
- alta de sol·licitud;
- llistat de validació intranet;
- decisió positiva;
- decisió negativa;
- Redsys/factura alumne;
- factura entitat;
- cobrament entitat;
- conciliació;
- canvi/baixa.

### 7.4. Traçabilitat i estat

Vegeu [auditoria i matriu UC-013](uc-013-auditoria-tracabilitat-2026-09-29.md).

**Estat després de la revisió:**
- DOCUMENTAT: ampliat i específic.
- IMPLEMENTAT: parcial.
- VERIFICAT: estàticament contra codi.
- TEST EXECUTAT: sí per nucli USOC i protocol durable; resta navegador/preproducció.
- P0 estructurals IMPLEMENTATS EN REPOSITORI: identitat inequívoca `ID_INSC`, traça durable de validació `REQUESTED/COMMITTED/REVIEW_REQUIRED`, vinculació factura alumne↔ID_INSC/IDPAG/import, checkpoint financer, emissió entitat protegida, cobrament/reconciliació específica USOC, adaptadors d'intranet i planner lifecycle separat per pagador. Pendents: desplegament/preflight real, navegador/preproducció, execució fiscal específica de canvi/baixa i decisions funcionals.


## Paquet UML específic del UC-013

Per separar el model integrat dels diagrames de contrast:
- [Classes ACTUAL/FINAL](uc-013-classes-actual-final.md)
- [Seqüències ACTUAL/FINAL](uc-013-sequencies-actual-final.md)
- [Activitats ACTUAL/FINAL per pàgina i apartat](uc-013-activitats-pagines-actual-final.md)
- [Auditoria i traçabilitat](uc-013-auditoria-tracabilitat-2026-09-29.md)
