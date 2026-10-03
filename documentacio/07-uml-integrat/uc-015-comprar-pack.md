# UC-15 · Comprar un pack — fitxa i UML integrats

**Objectiu:** facturar i cobrar una **operació de pack** amb múltiples inscripcions, cadascuna amb curs, edició, import i descompte que li correspon. Un pagament del pack no és N cobraments bancaris independents, i la factura global no significa que es pugui perdre el detall de quantitat atribuïda a cada inscripció.

**Estat (revalidat 2026-10-02):** codi i documentació UC-015 tancats. L'alta pública és POST-only, no envia PII a la query string, exigeix `PublicWebMutationAuthorization` (`WEB_ALLOWED_ORIGINS` + `X-Requested-With`) i conserva `Sec-Fetch-Site`; disposa d'idempotència server-side amb `REQUEST_ID`, fingerprint SHA-256 i replay `RID/RH1`. Totes les edicions es revaliden al servidor. L'ordre comercial v1 queda tancat com `ORDER BY c.DATAI, p.ID_CURS` i es congela a `PACK_ORDINAL`. Els dos callbacks fiscals PACK legacy productius han estat eliminats físicament. Resten només l'acceptació E2E real de preproducció i el lliurament/retries de notificacions sota UC-58.

**Codi consultat:** `PublicWebMutationAuthorization`, `enviarInscripcioPack.php`, `PackPaymentGate`, `SifPaymentIntentClient`, `RedsysPaymentIntentService`, `RedsysPackInvoiceService`, `LegacyPackInvoicePayloadBuilder`, `RedsysInvoicePayloadBuilder`, `InvoiceService`, ledger/outbox i infraestructura UC-63/03. El builder exigeix almenys dues línies i associa `PACK` i cada `INSCRIPCIO` a la factura. La composició, imports, receptor, ordinal i disponibilitat ja es validen al canal; el pendent és acreditar-ho en l'entorn real de preproducció.

## 1. Fitxa del cas d'ús

| Element | Comportament |
| --- | --- |
| Actors | Alumne/pagador via ecommerce, Redsys i worker SIF. |
| Entrada de producte | `pack.ID_PACK` positiu, títol de pack i `items` amb almenys dues entrades, cadascuna amb `inscription` i `course`; cada inscripció té identificador propi. |
| Identitat de l'operació | Alta web: `REQUEST_ID` UUID v4 persistent + `RID/RH1` + `IDPAG`. Fiscal/pagament: clau base `LEGACY|PACK|IDPAG:<IDPAG>` i, al flux Redsys, clau d'origen/IDPAG/DS_ORDER. |
| Factura | Una factura de pack amb **una línia per inscripció**, `source_type=INSCRIPCIO`, `source_id=ID`; relació principal `PACK` i relacions de cadascuna de les inscripcions; `visible_alumne=1` al constructor revisat. |
| Descompte de pack al builder actual | Amb base/descompte explícits, es conserva informació aportada i es valida que el descompte no sigui negatiu. El builder exigeix base, descompte, percentatge i total explícits per cada línia; si manca qualsevol dada o no quadra `base - descompte = total`, rebutja l'emissió. Ja no reconstrueix automàticament un 25 %. |
| Pagament | Una notificació Redsys `VALIDATED` aporta el **cobrament únic** del pack i l'assignació a factura, amb import total real de `DS_ORDER`. |
| Assignació a inscripcions | **Implementat:** `PackEnrollmentFundAllocationService` grava N moviments idempotents a `enrollment_fund_movement`, tots vinculats al mateix `UUID_PAYMENT`, i exigeix que la suma coincideixi amb cobrament i factura. |

### 1.1. Flux principal asíncron

1. L'alta pública envia POST + `X-Requested-With` + `REQUEST_ID`. Abans de processar dades, `PublicWebMutationAuthorization` valida `Origin`/`Referer` contra `WEB_ALLOWED_ORIGINS`; l'endpoint conserva `Sec-Fetch-Site`. Després el servidor serialitza la clau amb named lock i busca `RID/RH1`: un reintent equivalent reutilitza l'alta i una variant contradictòria retorna 409; només una clau nova valida el formulari, revalida que **tots els components** estiguin oberts, rellegeix preus, reserva `IDPAG` i crea atòmicament N inscripcions. El **checkout de pagament** rellegeix BD amb `PackPaymentGate`, valida composició, preu/descomptes, receptor i ordinal, i crea una intenció SIF `PACK` amb `DS_ORDER`, import total i snapshot. No s'emet factura per una intenció sense cobrament.
2. Redsys comunica resultat signat; el callback UC-03 valida ordre i import, desa notificació i encua job només si autoritzat.
3. El worker selecciona `RedsysPackInvoiceService` mitjançant `SOURCE_TYPE=PACK` i li lliura `SNAPSHOT_JSON`.
4. `LegacyPackInvoicePayloadBuilder::build()` valida l'ID del pack i les inscripcions, genera les línies i totals, relacions `PACK` i `INSCRIPCIO` i congela els descomptes.
5. `RedsysInvoicePayloadBuilder::buildFromValidatedNotification()` incorpora el bloc `payment` de la notificació `VALIDATED`, amb `DS_ORDER`/`IDPAG` a les relacions.
6. `InvoiceService::issueInvoice()` crea/reutilitza una factura fiscal i un pagament inicial; el worker desa `UUID_FACTURA`/`UUID_PAYMENT` i estat `PROCESSED`.
7. `PackEnrollmentFundAllocationService` registra N atribucions `EXTERNAL_ALLOCATION → INSCRIPCIÓ` segons imports congelats, vinculades al mateix `UUID_PAYMENT`, i bloqueja si suma, factura o línies no coincideixen.
8. `RedsysLegacySyncingProcessor` executa la sincronització legacy només després de l'èxit SIF mitjançant `LegacySyncService`; això cobreix la marca econòmica/relacions legacy, però no converteix l'outbox de notificacions en correu enviat.

### 1.2. Alternatives i controls necessaris

| Cas | Regla |
| --- | --- |
| Pack amb menys de dues inscripcions al snapshot | El builder rebutja `items` amb menys de dues entrades. |
| Identificador de pack o inscripció invàlid | Error abans d'emetre factura. |
| Una inscripció canvia de curs després del cobrament | UC-71 registra traspàs dels seus fons i valoració fiscal; les altres línies i atribucions del pack original no es reescriuen. |
| Cancel·lació d'un curs del pack | UC-72/27 i eventual UC-28/29/05 sobre la part identificada, conservant descompte de pack i política de retorn per validar. |
| Diferència entre suma de línies congelades i import Redsys | **Implementat 2026-09-29:** `RedsysPackInvoiceService` rebutja amb conflicte abans d'emetre si el total fiscal i l'import Redsys validat no coincideixen. |
| Duplicat de callback o worker | Reutilitzar factura, pagament i atribucions N, no registrar pagaments addicionals. |
| Descompte comercial per component | El web congela `PACK_BASE`, `PACK_DISCOUNT`, `PACK_DISCOUNT_PCT` i `PACK_TOTAL` per cada ordinal. El builder fiscal exigeix aquestes dades explícites i coherents; no aplica ni reconstrueix cap percentatge fix. |
| Una sola persona fa totes les inscripcions del pack | La factura pot ser una, però els `ID_INSC` de cada curs/edició continuen independents per permetre canvis, baixes i consulta. |

**Proves localitzades:** `RedsysPackInvoiceServiceTest`, `PackPaymentGateTest`, `LegacyPackInvoicePayloadBuilderTest`, `LegacyPackCallbackBoundaryTest` i scripts de preflight/preview. El paquet UC-015 fusionat a `41d6968...` té `SIF PHP MySQL tests` en **success** (run `36741186555`). La revisió de codi del PR a `0b32fa2...` va passar els quatre workflows del repositori, inclòs `SIF PHP MySQL tests` (run `36943484891`). Qualsevol commit o resincronització posterior ha de tornar a passar CI abans del merge.

### 1.3. Regla comercial executable — PACK N

**Contracte vigent:** un PACK té **N components (N ≥ 2)**. Les edicions es presenten i processen en l'ordre canònic `c.DATAI, p.ID_CURS`, que es congela com `PACK_ORDINAL`. El servidor rellegeix el preu del pack i els preus base dels components abans de persistir l'alta.

La distribució legacy de l'import és determinista i queda congelada al moment de l'alta:

1. `aux = preuPack`;
2. per cada component, en ordre `PACK_ORDINAL`, `PACK_TOTAL = min(aux, PACK_BASE)`;
3. `PACK_DISCOUNT = PACK_BASE - PACK_TOTAL` i se'n deriva `PACK_DISCOUNT_PCT`;
4. es resta `PACK_TOTAL` d'`aux`;
5. abans del commit s'exigeix, en cèntims, `aux = 0` i `Σ PACK_TOTAL = preuPack`.

Aquesta regla pot fer que, en ofertes històriques de dos cursos, el descompte quedi concentrat en el segon component; això és una **conseqüència del snapshot i de l'ordre comercial**, no una regla fiscal universal de «25 % al segon curs». Amb PACK N, el descompte pot afectar qualsevol component posterior segons el preu global pactat.

**Builder fiscal:** `LegacyPackInvoicePayloadBuilder` no redistribueix el preu ni inventa descomptes. Exigeix base, descompte, percentatge i total explícits de cada línia, comprova `base - descompte = total` i preserva el snapshot comercial.

**Comunicació PACK N:** el correu d'alta usa `[CURSOS_PACK]` i la llista dinàmica de totes les edicions; no pressuposa dos cursos.

**Pagament ecommerce:** UC-015 només admet el pagament complet del pack al canal ecommerce. `FRACCIONAT=0` és server-side i `PackPaymentGate` bloqueja packs amb cobrament previ o import diferent del pendent complet.

**Variant excepcional de gestió:** si en un procés intern es decideix dividir comercialment un pack en diversos cobraments/factures, aquesta decisió necessita un contracte propi de gestió/fiscalitat i no forma part del flux executable ecommerce UC-015. No és un pendent intern que impedeixi tancar aquest UC.

**Identitats:** `IDPAG` agrupa les N inscripcions de la mateixa alta comercial. `DS_ORDER` identifica cada intent/cobrament Redsys. El flux nominal UC-015 exigeix un únic cobrament confirmat per l'import complet del pack i no deduplica conceptes diferents només perquè comparteixin `IDPAG`.

### 1.4. Escenaris d'acceptació runtime específics del PACK N

| ID | Escenari | Resultat exigible |
| --- | --- | --- |
| PK-01 | PACK de N components, un DS_ORDER acceptat | N inscripcions amb mateix IDPAG, una factura amb N línies, un CHARGE i N atribucions internes. |
| PK-02 | Preu PACK inferior a la suma de bases | Cada línia conserva base/descompte/total explícits segons l'ordre congelat; suma de línies = preu PACK. |
| PK-03 | PACK amb més de dues línies o percentatges diferents | Cap 25 % implícit: factura i ledger consumeixen exactament el snapshot comercial de cada component. |
| PK-04 | Ecommerce intenta fraccionament o hi ha cobrament previ | Bloqueig fail-closed; no crear nova intenció PACK fins reconciliació/gestió fora del flux nominal. |
| PK-05 | Callback Redsys duplicat | Mateixa factura/payment/ledger/outbox; cap duplicació. |
| PK-06 | Reintent amb el mateix REQUEST_ID i mateix payload | Reutilitza l'alta existent; cap nova inscripció ni nou IDPAG. |
| PK-07 | Mateix REQUEST_ID amb payload divergent | HTTP 409 i zero mutació. |

### 1.5. Ordre comercial v1 — contracte tancat

Les compres noves PACK utilitzen un únic ordre determinista de presentació i alta: `ORDER BY c.DATAI, p.ID_CURS`. El bucle d'alta congela aquesta posició a `PACK_ORDINAL`; el repository/builder fiscal consumeix l'ordinal congelat, l'ordena i exigeix una seqüència contigua.

Això resol el problema històric d'intentar inferir l'ordre a partir de `A_PAGAR DESC, ID`: el saldo no decideix l'ordre comercial. Una futura necessitat de reordenació manual haurà d'introduir una posició comercial explícita/versionada per a **noves** ofertes, sense reinterpretar snapshots ni factures existents.

El checkout continua obligat a contrastar `ID_INSC`, curs/edició, ordinal, base, descompte, total, receptor i import cobrat abans de crear la intenció/emissió.

### 1.6. Escenaris de regressió/acceptació de l'ordinal

| ID | Escenari | Resultat exigible |
| --- | --- | --- |
| PK-08 | Dos o més components comparteixen `DATAI` | Desempat estable per `ID_CURS`; `PACK_ORDINAL` 1..N sense duplicats ni buits. |
| PK-09 | Després de l'alta canvien saldos o `A_PAGAR` legacy | Snapshot original manté ordinal, base, descompte i total; no es reordena ni es reconstrueix la factura. |
| PK-10 | Components del mateix `IDPAG` presenten receptor fiscal divergent | Bloqueig abans d'emetre; el receptor fiscal de l'operació ha de ser coherent. |
| PK-11 | Falta base/descompte/percentatge/total explícit d'un component | Conflicte i no emissió; el builder no infereix imports amb fórmules o percentatges implícits. |

## 2. Diagrama UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Alumne / pagador" as Student
actor "Redsys" as Bank
actor "Worker SIF" as Worker
rectangle "SIF · pack" {
 usecase "UC-15\nComprar pack" as Pack
 usecase "UC-63\nIntenció i snapshot N cursos" as Intent
 usecase "UC-03\nProcessar pagament" as Callback
 usecase "UC-01\nEmetre factura pack" as Invoice
 usecase "Atribuir fons a\ncada inscripció" as Funds
 usecase "UC-71\nCanvi d'un curs del pack" as Change
}
Student --> Pack
Pack ..> Intent : <<include>>
Bank --> Callback
Worker --> Callback
Callback ..> Invoice : <<include>> (autoritzat)
Worker --> Funds
Student --> Change
note bottom of Funds
 Atribució per inscripció IMPLEMENTADA.
 Un únic cobrament bancari, N atribucions internes.
end note
@enduml
```

### Vista de casos d’ús per a GitHub (Mermaid)

```mermaid
flowchart LR
  actor_0["Alumne / pagador"]
  actor_1["Redsys"]
  actor_2["Worker SIF"]
  subgraph SIF_BOX["SIF · pack"]
    uc_0(["UC-15<br/>Comprar pack"])
    uc_1(["UC-63<br/>Intenció i snapshot N cursos"])
    uc_2(["UC-03<br/>Processar pagament"])
    uc_3(["UC-01<br/>Emetre factura pack"])
    uc_4(["Atribuir fons a<br/>cada inscripció"])
    uc_5(["UC-71<br/>Canvi d'un curs del pack"])
  end
  actor_0 --> uc_0
  uc_0 -.->|include| uc_1
  actor_1 --> uc_2
  actor_2 --> uc_2
  uc_2 -.->|include| uc_3
  actor_2 --> uc_4
  actor_0 --> uc_5
```

## 3. Subdiagrama de classes

```mermaid
classDiagram
direction LR
class RedsysPackInvoiceService {
 +sourceType() string
 +issueFromIntentSnapshot(db,dsOrder,snapshot) array
}
class RedsysIntentHandler {
 <<interface>>
 +issueFromIntentSnapshot(db,dsOrder,snapshot) array
}
class LegacyPackInvoicePayloadBuilder {
 +build(snapshot) array
}
class RedsysInvoicePayloadBuilder {
 +buildFromValidatedNotification(db,dsOrder,payload) array
}
class InvoiceService {
 +issueInvoice(payload) array
}
class InvoiceRepository {
 +createInvoiceGraph(db,payload,seq,chainState) array
}
class PaymentRepository {
 +createPayment(db,payload) array
}
class EnrollmentFundMovementRepository {
 <<IMPLEMENTAT>>
 +lockPayment(db,uuidPayment) array
 +findInvoiceLineForInscription(db,uuidFactura,idInsc) array
 +insertOrReuseExternalAllocation(db,movement) array
}
class PackPaymentNotificationService {
 <<IMPLEMENTAT · ENQUEUE>>
 +enqueue(db,dsOrder,snapshot,invoiceResult) array
}
class PublicWebMutationAuthorization {
 <<IMPLEMENTAT>>
 +assertSameOriginAjax()
}
class EnviarInscripcioPack {
 <<IMPLEMENTAT>>
 +POST + REQUEST_ID
 +atomicitat PACK N
}
class PackPaymentGate {
 <<IMPLEMENTAT>>
 +assertCanPrepare(db,post) array
}
class SifPaymentIntentClient {
 <<IMPLEMENTAT>>
 +create(payload) array
}
class NotificationOutboxRepository {
 <<IMPLEMENTAT · ENQUEUE>>
 +enqueue(db,message) array
}
EnviarInscripcioPack --> PublicWebMutationAuthorization : WEB_ALLOWED_ORIGINS
EnviarInscripcioPack --> PackPaymentGate : IDPAG/snapshot
PackPaymentGate --> SifPaymentIntentClient : intent HMAC
SifPaymentIntentClient --> RedsysPackInvoiceService : via callback/worker
RedsysPackInvoiceService ..|> RedsysIntentHandler
RedsysPackInvoiceService --> LegacyPackInvoicePayloadBuilder : N línies
RedsysPackInvoiceService --> RedsysInvoicePayloadBuilder : cobrament validat
RedsysPackInvoiceService --> InvoiceService : factura de pack
RedsysPackInvoiceService --> EnrollmentFundMovementRepository : N atribucions / mateix UUID_PAYMENT
RedsysPackInvoiceService --> PackPaymentNotificationService : event postfactura
PackPaymentNotificationService --> NotificationOutboxRepository
InvoiceService --> InvoiceRepository : factura i relacions
InvoiceService --> PaymentRepository : CHARGE inicial si payment
```

`EnrollmentFundMovementRepository` està implementat i és invocat per `PackEnrollmentFundAllocationService` des del handler PACK; no depèn d'`InvoiceService` perquè l'atribució econòmica es fa després d'obtenir `UUID_FACTURA` i `UUID_PAYMENT`.

### 3.1. Frontera pública de l'alta PACK

`PublicWebMutationAuthorization` és l'única font d'autoritat per Origin/Referer i llegeix `WEB_ALLOWED_ORIGINS`; exigeix `X-Requested-With: XMLHttpRequest`. `enviarInscripcioPack.php` conserva `Sec-Fetch-Site` com a defensa complementària. No existeix una segona allowlist fixa a l'endpoint.

## 4. Diagrama de seqüència — pack pagat, factura i distribució

```mermaid
sequenceDiagram
autonumber
actor A as Alumne/pagador
participant Web as Ecommerce PACK
participant Intent as RedsysPaymentIntentService
participant Bank as Redsys
participant Callback as RedsysCallbackService
participant Q as Cua callback
participant W as RedsysCallbackWorker
participant H as RedsysPackInvoiceService
participant B as LegacyPackInvoicePayloadBuilder
participant R as RedsysInvoicePayloadBuilder
participant I as InvoiceService
participant O as NotificationOutbox
participant L as EnrollmentFundMovementRepository
A->>Web: Comprar pack amb N inscripcions
Web->>Intent: create(PACK, DS_ORDER, import, snapshot N línies)
Intent-->>Web: UUID_INTENT
Web->>Bank: TPV
Bank->>Callback: Notificació signada
Callback->>Q: Encolar job autoritzat
W->>Q: Reclamar job PACK
W->>H: issueFromIntentSnapshot(db,dsOrder,snapshot)
H->>B: build(snapshot)
loop Cada inscripció del pack
 B->>B: Línia, descompte i relació INSCRIPCIO
end
B-->>H: Totals i relacions PACK + N inscripcions
H->>R: buildFromValidatedNotification()
R-->>H: Payload amb un CHARGE real
H->>I: issueInvoice(payload)
I-->>H: UUID_FACTURA i UUID_PAYMENT
loop Cada inscripció i import congelat
 H->>L: insertOrReuseExternalAllocation(UUID_PAYMENT,ID_INSC,import_i)
end
H->>O: enqueue notificació idempotent
H-->>W: Resultat + ledger + outbox
W->>Q: PROCESSED i UUIDs
Note over H,O: Un pagament bancari, N atribucions internes. L'outbox queda PENDING fins al worker UC-58.
```

### 4.1. Frontera del cas — variants de gestió fora del flux nominal

El flux executable UC-015 ecommerce és **pagament complet únic del PACK**: N inscripcions, una intenció/cobrament Redsys, una factura amb N línies i N atribucions internes.

Una eventual decisió interna de dividir un pack en diversos cobraments o factures no es representa com a «FINAL pendent» d'UC-015. Requereix un cas de gestió separat que defineixi parts de servei, imports, relacions i classificació fiscal abans d'executar cap cobrament. Aquesta variant no altera ni reinterpreta els snapshots UC-015 ja emesos.

```mermaid
sequenceDiagram
autonumber
actor P as Pagador
participant E as Ecommerce PACK
participant S as SIF
actor G as Gestió interna
P->>E: Comprar PACK N
E->>S: Intenció per l'import complet
S-->>E: DS_ORDER / snapshot congelat
E->>S: cobrament complet confirmat
S-->>E: una factura + un CHARGE + N atribucions
opt Gestió necessita una variant comercial diferent
 G->>G: obrir cas de gestió separat
 Note over G,S: Fora del flux nominal UC-015; no dividir ni reescriure la factura PACK existent.
end
```

## 5. Traçabilitat

**Auditoria específica:** [registre 2026-09-29](uc-015-auditoria-tracabilitat-2026-09-29.md) · [classes ACTUAL/FINAL](uc-015-classes-actual-final.md) · [seqüències ACTUAL/FINAL](uc-015-sequencies-actual-final.md) · [activitats ACTUAL/FINAL](uc-015-activitats-pagines-pack-actual-final.md).

[Fitxa UC-15 original](../06-fitxes-funcionals/uc-015.md) · [UC-03](uc-003-processar-cobrament-redsys-asincron.md) · [UC-63](uc-063-crear-intencio-redsys.md) · [UC-71](uc-071-registrar-canvi-curs-complet.md) · [Revisió de fons](00-revisio-moviments-inscripcions.md) · [RedsysPackInvoiceService](../../sif/src/Service/RedsysPackInvoiceService.php) · [LegacyPackInvoicePayloadBuilder](../../sif/src/Service/LegacyPackInvoicePayloadBuilder.php) · [RedsysPackInvoiceServiceTest](../../sif/tests/Integration/RedsysPackInvoiceServiceTest.php).


## Preproducció canònica

Els scripts Redsys de PACK consumeixen el `SNAPSHOT_JSON` de la intenció `SOURCE_TYPE=PACK`. El preview és read-only. L'acceptació productiva exigeix callback + `process-redsys-callback-queue.php` i, després, `verify-redsys-pack-preproduction.php <DS_ORDER> --verify-evidence`, que agrega preflight PACK, preflight de cua, preview i `RedsysPackEvidenceVerifier` sobre l'estat persistent. `--execute [--sync-legacy]` continua disponible com a processor manual de diagnòstic, però no substitueix l'evidència del worker/cua. La sortida es sanititza i no copia secrets ni el payload fiscal complet. Continua pendent executar aquest flux contra un `DS_ORDER` real de preproducció.


## Revalidació 2026-10-02

Auditoria canònica: [uc-015-auditoria-tracabilitat-2026-10-02.md](uc-015-auditoria-tracabilitat-2026-10-02.md).

Punts nous incorporats:
- el formulari d'alta pública s'ha migrat a POST-only amb frontera same-site/origin;
- idempotència server-side implementada: UUID v4 persistent al navegador, named lock, `RID/RH1`, replay equivalent i 409 per payload divergent;
- el bundle puja a `mostrarInscripcioPack.min.js?ver=7.5` per evitar caché del GET antic;
- corregida la disponibilitat: `EdicioPack` compara una data límit amb signe real i llistat/fitxa/POST exigeixen tots els components oberts;
- les N inscripcions del pack es creen dins una única transacció, amb rollback en error i alliberament garantit del lock `IDPAG`; abans del commit la suma dels imports congelats ha de coincidir exactament amb el preu PACK en cèntims;
- `pagFrac` ja no és entrada client: l'ecommerce fixa no fraccionament al servidor;
- el correu d'alta s'ha generalitzat a PACK N amb `[CURSOS_PACK]`;
- la seqüència real de postcommit és `RedsysLegacySyncingProcessor → LegacySyncService`;
- `AcademicEnrollmentSyncService` no forma part del flux executable UC-015;
- el text intern del descompte fiscal ja no pressuposa una línia/ordinal concreta;
- el verificador canònic `verify-redsys-pack-preproduction.php` ja suporta `--verify-evidence`; resta executar callback+worker real i aquesta verificació amb un `DS_ORDER` de preproducció;
- les dues còpies productives del callback legacy estan eliminades físicament; només queda el harness `Prova`, restringit a test/preproduction + flag explícit;
- el nucli PACK conserva evidència CI històrica i el HEAD final d'aquesta auditoria ha de tornar a passar la CI després dels enduriments web/idempotència/preproducció.
