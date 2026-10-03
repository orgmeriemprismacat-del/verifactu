# UC-12 · Gestionar el cicle de morositat i reclamació

**Objectiu del projecte:** controlar el deute, els avisos i les reclamacions **sense confondre morositat amb baixa acadèmica ni crear una rectificativa pel sol fet de reclamar**. Quan la reclamació acaba en un ingrés real, se'n registra el cobrament a la **factura que ja existeix** (UC-24), no s'emet una altra factura.

**Estat actual de la branca d'auditoria:** `ClaimPaymentService` continua implementant el cobrament real posterior i s'hi ha afegit `DebtClaimCoordinator`, `DebtSnapshotRepository`, `DebtClaimCaseRepository`, `debt_claim_case/debt_claim_event`, outbox idempotent, API HMAC i bridge d'intranet amb CSRF/rol. El nucli és **IMPLEMENTAT A BRANCA**; GitHub Actions continua en cua i encara no es classifica com a **VERIFICAT**. L'automatització temporal queda bloquejada per UC-096.

## 1. Fitxa del cas d'ús

| Element | Regla |
| --- | --- |
| Actors | Operador autoritzat de gestió; alumne, empresa o responsable del deute segons factura/relació; procés de recordatoris quan existeixi. No dirigir la reclamació només a la persona inscrita si l'obligat al pagament és una empresa. |
| Dades d'entrada | `UUID_FACTURA`, `NUM_VISIBLE`, receptor/titular del deute, total, pagaments assignats i devolucions, venciment pactat, `ID_INSC`/grup i eventual pròrroga UC-96. La data de venciment i l'escalat no es calculen a `ClaimPaymentService`. |
| Regles de deute | Factura emesa abans del cobrament **continua sent factura real** si està pendent; un recordatori no canvia el seu número, total fiscal, hash ni registre AEAT. Morositat, estat acadèmic i baixa són decisions separades (UC-95/96/72). |
| Registre de reclamació | `debt_claim_case` conserva estat/etapa/versió/saldo i `debt_claim_event` afegeix events append-only amb idempotència, payload hash, actor, request/correlation IDs i saldo abans/després. |
| Cobrament real implementat | `ClaimPaymentPayloadBuilder`: `movement_type=CHARGE`, mètode `TRANSFERENCIA` o `MANUAL`, canal `INTRANET`, import positiu, data real i assignació `CLAIM_PAYMENT` **a la factura existent**. |
| Idempotència del cobrament | Referència present: `CLAIM|REF:<referència>`. Sense referència, exigeix `created_by` i usa `CLAIM|FACT:<num_visible>|DATA:<data>|IMPORT:<import>|USUARI:<usuari>`. Reús d'una clau amb payload contradictori continua requerint control addicional. |
| Pagament per inscripció | Si una factura d'empresa cobreix N inscripcions, el cobrament real únic pot aplicar-se a la factura, però la seva atribució individual exigeix el registre de fons proposat; el simple status `PAID` de factura no permet calcular automàticament la quota de cada persona. |

### 1.1. Flux objectiu complet

1. El canal d'operació consulta factura fiscal existent, total, assignacions, devolucions i deute real. Determina titular, venciment, pròrrogues i si ja hi ha una reclamació oberta. La regla concreta d'escalat/terminis de PrisMa **no queda fixada pel servei de cobrament**.
2. `DebtClaimCoordinator::recordNotice()` obre/actualitza l'expedient amb actor, abans/després, saldo, motiu, idempotència i etapa. La baixa **acadèmica continua separada**, sense crear una rectificativa automàticament.
3. Per a cada comunicació, el coordinador resol el receptor de la factura SIF i encola una notificació idempotent. El delivery/plantilles UC-58 i qualsevol URL de pagament final continuen pendents d'acceptació operativa.
4. Si la persona/empresa paga després, l'operador verifica **ingrés real**, import/data i referència, i inicia UC-24 contra `UUID_FACTURA` o `NUM_VISIBLE`.
5. `ClaimPaymentService` carrega la factura existent, `ClaimPaymentPayloadBuilder` construeix el `CHARGE` amb `CLAIM_PAYMENT` i `PaymentService::registerPayment()` crea o reutilitza l'`UUID_PAYMENT` i actualitza l'estat econòmic de factura. **No** es crida `issueInvoice()`.
6. Si el deute era parcial o el pagament rebut no el cobreix tot, el nou saldo pendent es recalcula de les assignacions, sense donar per pagada tota la inscripció o el grup.
7. `DebtClaimCoordinator::reconcileAfterPayment()` recalcula el saldo: amb saldo parcial manté l'expedient obert; a zero el tanca com `RESOLVED` i cancel·la avisos `PENDING`. Els documents fiscals originals es preserven.

### 1.2. Casos alternatius i controls

| Cas | Resposta |
| --- | --- |
| Factura abans de cobrar | Es reclama sobre la factura existent; **cap segon número fiscal** quan arribi la transferència. |
| Reclamació sense cobrament | No crear `payment_transaction` només per enviar correu, fer una trucada o generar URL. |
| Deute parcial | Una nova fracció real és un `CHARGE` amb import efectiu, que no pot excedir arbitràriament el saldo pendent sense classificar sobrant; no crear factura nova pel recordatori. |
| Pagament detectat al fitxer TPV/transferència | UC-25/56 comprova si ja hi ha `UUID_PAYMENT` abans de registrar; un ingrés no es registra dues vegades per haver-se reclamat. |
| Reclamació d'empresa per grup | Una factura/deute a l'empresa, N participants acadèmics; retorn/saldo només segons pagador i atribució individual, no a cada alumne per defecte. |
| Pròrroga vigent | UC-96 separa ajornament de baixa i reclamació; el codi `ClaimPaymentService` no valida automàticament terminis ni prohibeix recordatoris durant una pròrroga. |
| Morositat amb baixa posterior | UC-72 classifica baixa i eventual impacte fiscal; l'impagament per si sol **no** anul·la la factura o el seu registre AEAT. |
| Dos avisos idèntics | Implementat: mateixa clau + mateix payload reutilitza event/outbox; mateixa clau + payload contradictori falla amb conflicte. |

**Buits de tancament actuals:** CI verda, evidència `sif_test*`/preproducció, delivery UC-58, cutover dels POST legacy i model autoritatiu de venciment/pròrroga UC-096. El nucli d'expedient/outbox/coordinació ja està implementat a la branca.

### 1.3. Rutes de reclamació, primera reclamació i baixa del llegat

**Entrades identificades.** El mapa de rutes documenta `/facturacio/primera-reclamacio/` → `facturacio-primera-reclamacio-pagament.php`, `/facturacio/reclamacio-final/` → `facturacio-reclamacio-final.php` i `/facturacio/morosos/` → `facturacio-control-morosos.php`. El diccionari de `Intranet.php` conté `cnsReclamacions`, `cnsCursosRecordarPag`, `cnsAlumnesRecordarPag`, `cnsCursosClaimBaixes`, `cnsAlumnClaimPag`, `cnsAlumnClaimEntMoros`, `cnsAlumnClaimAlumnNoCertMoros`, `cnsAlumnClaimAlumnCertMoros` i `cnsEntMoros`; les actualitzacions inclouen `updPrimeraReclamacio`, `updClaimDonarBaixa`, `updClaimRecPag`, `updInscCursBaixaiMoros` i `updReclamatDefaulter`. La documentació identifica **vies separades de reclamació i baixa**, però no acredita que el servei nou d'UC-12 coordini aquests handlers.

**Reclamació no equival a baixa ni factura rectificada.** `web.inscripcions.reclamat`, `data_reclamacio` i `pag_observacions` serveixen de seguiment administratiu. `INSC_CURS` és un estat acadèmic i `FACTURA_RELACIONADA` un vincle històric: cap dels dos acredita per si sol un moviment bancari nou. Una primera o última reclamació deixa la factura original vigent i no crea un `ALTA`, `REFUND` o rectificativa pel fet d'emetre l'avís. Si hi ha una **baixa real**, la decisió posterior sobre deute, retorn o saldo es tramita per UC-72/74/28/29 amb evidència per inscrit i pagador.

**Via de regularització encara que hi hagi morositat.** El procediment de la fitxa d'alumne estableix que la persona morosa **ha de poder regularitzar el pagament**. Abans de cada recordatori, identificar si qui deu diners és l'alumne, una empresa o el responsable del grup, i si hi ha pròrroga, fracció, cobrament `UUID_PAYMENT` ja confirmat o `DS_ORDER` amb resultat pendent. Una factura d'empresa pot tenir URL pròpia: no reactivar el pagament individual d'un participant cobert, ni enviar-li el PDF complet o el deute conjunt per coincidència de correu.

**Rutes de reclamació i cobrament posterior.** Si la reclamació deriva en transferència real, contrastar referència, import i data i localitzar `UUID_FACTURA` existent abans d'UC-24. Si el cobrament és visible al banc però només falta sincronitzar el llegat, recuperar `UUID_PAYMENT` i reprendre UC-47/53; no registrar un segon `CHARGE` per «tancar la reclamació». El recordatori futur UC-43 s'ha de cancel·lar o actualitzar quan es modifica el deute o entra en vigor una pròrroga.

### 1.4. Proves addicionals de reclamació i baixa separades (no executades)

| ID | Escenari | Resultat exigible |
| --- | --- | --- |
| MR-12-01 | Primera reclamació sense ingrés real | Expedient/avís, factura original vigent i cap moviment monetari nou. |
| MR-12-02 | Pròrroga aprovada abans d'un recordatori programat | Revalidar venciment i no enviar reclamació obsoleta. |
| MR-12-03 | Factura d'empresa amb tres participants i una reclamació | Destinatari i via de pagament del pagador legítim; no tres deutes individuals ficticis. |
| MR-12-04 | Transferència cobrada i `PAGAMENT` llegat encara pendent | Reconciliar `UUID_PAYMENT` existent, no segona factura/CHARGE. |
| MR-12-05 | Baixa acadèmica després de reclamació | Classificar efecte fiscal/econòmic independent, sense anul·lació de factura automàtica. |
| MR-12-06 | Alumne morós vol pagar i URL individual ha estat desactivada per factura d'empresa | Oferir la via autoritzada del responsable, sense restablir l'URL individual. |

## 2. Diagrama UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Operador de cobrament" as O
actor "Responsable del deute" as R
rectangle "SIF · morositat" {
 usecase "UC-12\nGestionar cicle de reclamació" as Main
 usecase "Consultar deute i venciment" as Debt
 usecase "Registrar avisos i decisions" as Remind
 usecase "UC-96\nConcedir pròrroga quan correspongui" as Delay
 usecase "UC-24\nRegistrar cobrament real" as Pay
 usecase "UC-95/72\nRevisar estat acadèmic" as Academy
}
O --> Main
R --> Pay
Main ..> Debt : <<include>>
Main ..> Remind : <<include>>
O --> Delay
O --> Academy
@enduml
```

### Vista de casos d’ús per a GitHub (Mermaid)

```mermaid
flowchart LR
  actor_0["Operador de cobrament"]
  actor_1["Responsable del deute"]
  subgraph SIF_BOX["SIF · morositat"]
    uc_0(["UC-12<br/>Gestionar cicle de reclamació"])
    uc_1(["Consultar deute i venciment"])
    uc_2(["Registrar avisos i decisions"])
    uc_3(["UC-96<br/>Concedir pròrroga quan correspongui"])
    uc_4(["UC-24<br/>Registrar cobrament real"])
    uc_5(["UC-95/72<br/>Revisar estat acadèmic"])
  end
  actor_0 --> uc_0
  actor_1 --> uc_4
  uc_0 -.->|include| uc_1
  uc_0 -.->|include| uc_2
  actor_0 --> uc_3
  actor_0 --> uc_5
```

## 3. Classes FINAL — implementació de la branca

```mermaid
classDiagram
direction LR
class DebtClaimCoordinator {
 <<IMPLEMENTAT>>
 +preview(actor,criteria) array
 +recordNotice(actor,payload) array
 +reconcileAfterPayment(actor,payload) array
}
class DebtClaimCaseRepository {
 <<IMPLEMENTAT>>
 +findByInvoice() case
 +create() case
 +findReusableEvent() event
 +appendEvent() result
 +updateCase() void
}
class DebtSnapshotRepository {
 <<IMPLEMENTAT>>
 +findByUuid() snapshot
 +findByNumVisible() snapshot
}
class NotificationOutboxRepository {
 <<IMPLEMENTAT>>
 +enqueue() result
 +cancelPendingForInvoice() int
}
class OperationalEventRepository {
 <<REUTILITZAT>>
 +append() uuid
}
class ClaimPaymentService {
 <<IMPLEMENTAT>>
 +registerByUuid() array
 +registerByNumVisible() array
}
DebtClaimCoordinator --> DebtClaimCaseRepository
DebtClaimCoordinator --> DebtSnapshotRepository
DebtClaimCoordinator --> NotificationOutboxRepository
DebtClaimCoordinator --> OperationalEventRepository
```

**Límit:** `DebtSnapshotRepository` calcula saldo i receptor fiscal, però no inventa venciment/pròrroga; aquesta autoritat queda a UC-096.

## 4. Seqüència A — reclamació sense moviment monetari (IMPLEMENTADA A BRANCA)

```mermaid
sequenceDiagram
actor O as Operador
participant UI as Intranet + bridge CSRF
participant API as API interna HMAC
participant C as DebtClaimCoordinator
participant S as DebtSnapshotRepository
participant CR as DebtClaimCaseRepository
participant OX as NotificationOutbox
participant AE as OperationalEvent
O->>UI: Confirmar etapa sobre factura SIF
UI->>API: POST signat + actor/rol + operation_id
API->>C: recordNotice()
C->>S: rellegir factura + allocations/refunds
S-->>C: saldo + receptor fiscal
C->>CR: create/reuse + append event idempotent
C->>OX: enqueue plantilla versionada
C->>AE: append impacte fiscal/econòmic NONE
C-->>API: RECORDED/REUSED/CONFLICT
API-->>UI: JSON
Note over C,OX: Cap factura nova, CHARGE o registre AEAT per reclamar
```
## 5. Seqüència B — cobrament de reclamació executable

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant UI as Intranet/adapter [integració pendent]
participant C as ClaimPaymentService
participant R as ManualPaymentInvoiceRepository
participant B as ClaimPaymentPayloadBuilder
participant P as PaymentService
participant DB as BD SIF
O->>UI: Confirmar transferència ingressada d'una factura reclamada
UI->>C: registerByUuid(db,UUID_FACTURA,amount,date,reference)
C->>R: findByUuid(UUID_FACTURA)
R-->>C: Factura emesa existent
C->>B: forExistingInvoice(UUID_FACTURA,input)
B-->>C: CHARGE i allocation CLAIM_PAYMENT a la mateixa factura
C->>P: registerPayment(payload)
P->>DB: INSERT payment_transaction + payment_allocation o reús idempotent
P-->>C: UUID_PAYMENT i idempotency_reused
C-->>UI: UUID_FACTURA original i UUID_PAYMENT
Note over C,DB: No es crea una nova factura ni registre AEAT per cobrar una reclamació
```

## 6. Traçabilitat

[UC-12 original](../06-fitxes-funcionals/uc-012.md) · [UC-24 cobrament](uc-024-registrar-cobrament-reclamacio.md) · [UC-96 pròrroga original](../06-fitxes-funcionals/uc-096.md) · [UC-95 estat acadèmic original](../06-fitxes-funcionals/uc-095.md) · [UC-25 TPV](uc-025-analitzar-fitxer-tpv.md) · [UC-56 assignar](uc-056-cercar-assignar-cobrament.md) · [ClaimPaymentService](../../sif/src/Service/ClaimPaymentService.php) · [ClaimPaymentPayloadBuilder](../../sif/src/Service/ClaimPaymentPayloadBuilder.php) · [Fluxos de morositat](../03-canvis-pendents/04-fluxos-facturacio.md) · [Traça dels fons](00-revisio-moviments-inscripcions.md).


## 7. Revalidació de l'auditoria — 03/10/2026

La troballa inicial «només P-MOR-05 és executable al SIF» queda superada per la implementació de la branca. P-MOR-01 (saldo/receptor), P-MOR-02, P-MOR-03, P-MOR-04 i la reconciliació P-MOR-05 disposen de nucli executable.

Continuen oberts:
- GitHub Actions/MySQL: en cua, sense resultat verd encara;
- UC-096: venciment/pròrroga autoritatius;
- UC-58: plantilles/delivery real;
- cutover dels handlers legacy;
- evidència de preproducció.

Documents de control:
- [Inventari PHP/JS ACTUAL/FINAL](uc-012-inventari-codi-php-js-actual-final-2026-10-03.md)
- [Classes ACTUAL/FINAL](uc-012-classes-actual-final.md)
- [Seqüències i activitats ACTUAL/FINAL](uc-012-sequencies-activitats-actual-final.md)
- [Implementació SIF](uc-012-implementacio-sif-2026-10-03.md)
- [Tancament d'auditoria](uc-012-tancament-auditoria-2026-10-03.md)
