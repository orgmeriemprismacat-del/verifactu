# 36 - Mapa de components, punts d'entrada i integracions del SIF

Data de tall: 2026-09-15

## 1. Objectiu i límits

Aquest document completa les vistes de classes, seqüències, casos d'ús i dades amb els elements que no són classes: fitxers procedimentals, endpoints, scripts, configuració, migracions, proves, bases de dades, processos externs i fronteres de seguretat.

Llegenda:

- `[BASE]`: present al checkout `checkpoint/sif-fase-0-4`;
- `[ASYNC]`: present a `feature/redsys-async-queue`;
- `[LEGACY]`: còpies actuals o històriques dins `codi-drive`, no nou codi SIF;
- `[CANDIDAT]`: fitxers que pretenen incorporar canvis VERI*FACTU, encara no demostrats com a integració completa;
- `[DISSENY]`: component requerit però no implementat completament al repositori.

## 2. Context complet del sistema

```mermaid
flowchart LR
  Student[Alumne]
  Company[Empresa o responsable]
  Staff[Operador de facturació]
  Technical[Responsable tècnica]
  Auditor[Auditor temporal]

  Ecommerce[Ecommerce / web PrisMa]
  Intranet[Intranet principal]
  StudentPortal[Intranet alumne]
  TutorPortal[Intranet col·laboradors]
  Pay[pay.prisma.cat]

  subgraph SIF[SIF fiscal]
    API[3 endpoints HTTP]
    Services[Serveis de domini]
    CLI[56 scripts base / 58 async]
    Workers[Workers Redsys i AEAT]
    Panel[Panell SIF]
    Docs[Documents i exportacions]
  end

  SifDB[(BD SIF)]
  WebDB[(BD web)]
  IntranetDB[(BD intranet)]
  PayDB[(BD pay/factures legacy)]
  Redsys[Redsys]
  AEAT[AEAT VERI*FACTU]
  SMTP[Correu SMTP]
  Storage[Storage segur]
  Secrets[Certificat i secrets]

  Student --> Ecommerce
  Student --> StudentPortal
  Company --> Ecommerce
  Staff --> Intranet
  Technical --> Panel
  Auditor --> Panel
  Ecommerce --> Pay
  StudentPortal --> Pay
  TutorPortal -. factures de proveïdor fora de vendes SIF .-> Intranet
  Intranet --> API
  Pay --> API
  API --> Services
  CLI --> Services
  Workers --> Services
  Services --> SifDB
  Ecommerce --> WebDB
  Intranet --> IntranetDB
  Pay --> PayDB
  Pay <--> Redsys
  Workers -. pendent .-> AEAT
  Workers -. pendent .-> SMTP
  Docs -. pendent .-> Storage
  Workers -. requereix .-> Secrets
  Services -. sync posterior .-> WebDB
  Services -. sync posterior .-> IntranetDB
  Services -. sync posterior .-> PayDB
```

La font fiscal ha de ser la BD SIF. Les tres BDs llegades aporten snapshots o reben compatibilitat posterior, però no poden modificar una factura SIF emesa.

## 3. Topologia real del repositori

```mermaid
flowchart TB
  Repo[projecte-verifactu-pont]
  Repo --> SifBase[sif checkout base]
  Repo --> Legacy[codi-drive]
  Repo --> Docs[documentacio i 00-control]
  Repo --> Chat[xat-original no consultat]
  Repo -. worktree vinculat .-> Async[feature/redsys-async-queue]

  SifBase --> Config[config: 1 PHP]
  SifBase --> Public[public: 3 PHP]
  SifBase --> Src[src: 70 PHP<br/>69 classes + autoload]
  SifBase --> Scripts[scripts: 56 PHP]
  SifBase --> Tests[tests: 117 PHP<br/>112 Test / 234 mètodes]
  SifBase --> SQL[database: 2 migracions + 1 seed SQL]

  Async --> AConfig[config: 1 PHP]
  Async --> APublic[public: 3 PHP]
  Async --> ASrc[src: 77 PHP<br/>76 classes/interfícies + autoload]
  Async --> AScripts[scripts: 58 PHP]
  Async --> ATests[tests: 123 PHP<br/>118 Test / 276 mètodes]
  Async --> ASQL[database: 4 migracions + 1 seed SQL]

  Legacy --> Candidates[25 PHP candidats]
  Legacy --> Current[1.895 PHP en 5 còpies actuals]
```

| Tall | PHP totals | SQL totals | Observació |
| --- | ---: | ---: | --- |
| `sif/` base | 247 | 3 | 69 classes de producció, 56 scripts, 3 endpoints i 117 fitxers de prova. |
| `sif/` branca asíncrona | 262 | 5 | Afegeix 7 peces OO, 2 scripts, 6 classes `*Test`, 42 mètodes de prova i 2 migracions. |
| `codi-drive/` | 1.920 | 0 | Set carpetes: 25 PHP candidats i 1.895 PHP de còpies actuals/històriques; inclou biblioteques, proves i còpies datades. |

## 4. Les set carpetes de `codi-drive` i els 25 PHP candidats

### 4.1. Inventari de carpetes

```mermaid
flowchart TB
  Drive[codi-drive]

  subgraph Candidates[Candidats VERI FACTU]
    NewIntranet[intranet-nova-canvis-verifactu<br/>2 PHP]
    NewPay[pay-prisma-cat-canvis-verifactu<br/>23 PHP]
  end

  subgraph CurrentCopies[Còpies sense canvis VERI FACTU]
    IntranetActual[intranet-actual<br/>342 PHP]
    WebActual[web-actual<br/>443 PHP]
    StudentActual[intranet-alumne-actual<br/>60 PHP]
    OldIntranet[old-intranet<br/>579 PHP]
    TutorActual[intranet-collaboradors<br/>471 PHP]
  end

  Drive --> Candidates
  Drive --> CurrentCopies
  NewIntranet -. comparar .-> IntranetActual
  NewPay -. comparar .-> WebActual
  StudentActual -->|enllaç o intenció| NewPay
  IntranetActual -->|pagament i factura| NewPay
  WebActual -->|ecommerce i Redsys| NewPay
  TutorActual -.->|proveïdors, no vendes| NewPay
  OldIntranet -.->|referència històrica| TutorActual
```

| Carpeta | Fitxers totals | PHP | Paper |
| --- | ---: | ---: | --- |
| `intranet-nova-canvis-verifactu` | 2 | 2 | Candidata d'intranet. |
| `pay-prisma-cat-canvis-verifactu` | 23 | 23 | Candidata de pagament. |
| `intranet-actual` | 1.638 | 342 | Operació interna actual. |
| `web-actual` | 792 | 443 | Ecommerce i pagament actual. |
| `intranet-alumne-actual` | 101 | 60 | Consulta i enllaç de pagament de l'alumne. |
| `old-intranet` | 769 | 579 | Històric procedimental. |
| `intranet-collaboradors` | 3.523 | 471 | Tutors, honoraris i factures/rebuts de proveïdor. |
| **Total** | **6.848** | **1.920** |  |

### 4.2. Els 25 fitxers de les carpetes candidates

```mermaid
flowchart LR
  subgraph IntranetLegacy[Intranet: 2 fitxers]
    Intranet[Intranet.php]
    IntranetAjax[ajax/alumnes/efectuarPagament.php]
  end

  subgraph PayEntry[Entrades i pàgines pay: 7 fitxers]
    Pages[pagina_efectuar_pagament_*<br/>4 fitxers]
    Ajax[ajax/efectuarPagament*<br/>3 fitxers]
  end

  subgraph PayDomain[Classes de presentació de pagament: 4]
    Course[PagamentCursAutomatic]
    Workshop[PagamentTallerAutomatic]
    Group[PagamentGrupAutomatic]
    Gift[PagamentRegal]
  end

  subgraph Callbacks[Callbacks i resultats: 5]
    CourseCb[realitzaPagamentAutomatic]
    WorkshopCb[realitzaPagamentTallerAutomatic]
    PackCb[realitzaPagamentPackAutomatic]
    GroupCb[realitzaPagamentGrupAutomatic]
    GiftCb[realitzaPagamentRegalAutomatic]
  end

  subgraph Infra[Connexió i utilitats: 7]
    DB[4 classes Connexio*]
    API[RedsysAPI]
    Hash[codificarHash.php]
    Doit[doit.php]
  end

  Pages --> PayDomain
  PayDomain --> Ajax
  Ajax --> API
  API --> Callbacks
  Callbacks --> DB
  IntranetAjax --> Intranet
  Intranet --> DB
  Hash --> DB
  Doit --> DB
```

| Grup | Fitxers exactes | Nombre |
| --- | --- | ---: |
| Intranet | `Intranet.php`, `ajax/alumnes/efectuarPagament.php` | 2 |
| AJAX pay | `ajax/efectuarPagament.php`, `ajax/efectuarPagamentRegal.php`, `ajax/efectuarPagamentRegalAutomatic.php` | 3 |
| Connexions | `ConnexioBBDD_PreparedStatment.php`, `ConnexioIntranet.php`, `ConnexioPay.php`, `ConnexioWeb.php` | 4 |
| Utilitats | `codificarHash.php`, `doit.php`, `inc/apiRedsys.php` | 3 |
| Classes de pagament | `PagamentCursAutomatic.php`, `PagamentTallerAutomatic.php`, `PagamentGrupAutomatic.php`, `PagamentRegalAutomatic.php` | 4 |
| Pàgines | `pagina_efectuar_pagament_automatic.php`, `pagina_efectuar_pagament_taller_automatic.php`, `pagina_efectuar_pagament_grup_automatic.php`, `pagina_efectuar_pagament_regal_automatic.php` | 4 |
| Callbacks | `realitzaPagamentAutomatic.php`, `realitzaPagamentTallerAutomatic.php`, `realitzaPagamentPackAutomatic.php`, `realitzaPagamentGrupAutomatic.php`, `realitzaPagamentRegalAutomatic.php` | 5 |
| **Total** |  | **25** |

El pack té callback propi però no una classe/pàgina `PagamentPackAutomatic` separada en aquesta còpia; el comportament de pack apareix dins el flux de grup i fitxers associats. Aquesta asimetria s'ha de preservar en la migració i validar amb el codi actual.

La comparació dels 25 PHP dona 14 fitxers idèntics, 8 de diferents i 3 sense homòleg directe. No s'hi ha trobat cap crida als endpoints SIF; el detall i els criteris de comparació són al document 37.

## 5. Composició dels tres endpoints reals `[BASE]`

```mermaid
flowchart TB
  Issue[POST /api/factures/issue]
  Register[POST /api/payments/register]
  Callback[POST /api/redsys/callback]

  JSON[JsonResponse]
  Config[config/sif.php]
  Factory[ConnectionFactory]
  Invoice[InvoiceService]
  Payment[PaymentService]
  RedsysSig[RedsysSignatureValidator]
  RedsysCallback[RedsysCallbackService]
  DB[(BD SIF)]

  Issue --> JSON
  Issue --> Config
  Issue --> Factory
  Issue --> Invoice
  Register --> JSON
  Register --> Config
  Register --> Factory
  Register --> Payment
  Callback --> JSON
  Callback --> Config
  Callback --> Factory
  Callback --> RedsysSig
  Callback --> RedsysCallback
  Factory --> DB
  Invoice --> DB
  Payment --> DB
  RedsysCallback --> DB
```

No existeix `GET /api/incidencies` al checkout. La consulta d'incidències pertany al panell objectiu i s'ha de considerar `[DISSENY]` fins que hi hagi ruta, autorització i proves.

## 6. Fronteres de confiança i desplegament objectiu

```mermaid
flowchart TB
  Internet[Internet]

  subgraph PublicZone[Zona pública]
    Ecommerce[Ecommerce]
    PayPages[Pàgines pay]
    Callback[Callback Redsys]
  end

  subgraph PrivateZone[Zona d'aplicació autenticada]
    Intranet[Intranet]
    Panel[Panell SIF]
    Adapters[Adaptadors servidor]
  end

  subgraph WorkerZone[Processos sense navegador]
    RedsysWorker[Worker Redsys]
    AeatWorker[Worker AEAT pendent]
    DocWorker[Worker documents pendent]
    MailWorker[Worker notificacions pendent]
  end

  subgraph DataZone[Zona de dades]
    SifDB[(BD SIF)]
    LegacyDB[(BDs llegades)]
    DocumentStore[(Storage fiscal)]
    SecretStore[(Secrets i certificat)]
  end

  Redsys[Redsys]
  AEAT[AEAT]
  SMTP[SMTP]

  Internet --> Ecommerce
  Internet --> PayPages
  Redsys --> Callback
  Ecommerce --> Adapters
  PayPages --> Adapters
  Intranet --> Adapters
  Panel --> Adapters
  Callback --> SifDB
  Adapters --> SifDB
  Adapters --> LegacyDB
  RedsysWorker --> SifDB
  AeatWorker --> SifDB
  AeatWorker --> SecretStore
  AeatWorker --> AEAT
  DocWorker --> SifDB
  DocWorker --> DocumentStore
  MailWorker --> SifDB
  MailWorker --> SMTP
```

Controls necessaris a cada travessa: TLS, autenticació servidor, rol i abast, CSRF quan hi ha sessió web, validació de signatura Redsys, idempotència, mínim privilegi de BD, secrets fora del repo/webroot i auditoria de les operacions crítiques.

## 7. Runtime asíncron Redsys `[ASYNC]`

```mermaid
flowchart LR
  Channel[Adaptador ecommerce pendent]
  IntentSvc[RedsysPaymentIntentService]
  Intent[(redsys_payment_intent)]
  Callback[Callback HTTP curt]
  Notification[(redsys_notifications)]
  Queue[(redsys_callback_queue)]
  Worker[process-redsys-callback-queue.php]
  Dispatcher[RedsysCallbackDispatcher]
  Handlers[CURS / PACK / GRUP / REGAL / USOC_ALUMNE]
  Invoice[InvoiceService]
  Payment[PaymentService]
  Incident[(errors_verifactu)]

  Channel --> IntentSvc --> Intent
  Callback --> Intent
  Callback --> Notification
  Callback --> Queue
  Worker --> Queue
  Worker --> Dispatcher
  Dispatcher --> Handlers
  Handlers --> Invoice
  Handlers --> Payment
  Worker --> Incident
```

La branca implementa el circuit intern, però l'adaptador que crea la intenció abans del TPV i la integració/desplegament sobre la base principal continuen pendents.

## 8. Proves, migracions i porta de desplegament

```mermaid
flowchart LR
  Change[Canvi de codi o esquema]
  Unit[23 proves unitàries]
  Integration[88 base / 93 async integració]
  Database[1 base / 2 async BD]
  Runner[run-tests.php]
  Migrations[run-migrations.php]
  Preflight[14 base / 15 async preflight]
  Preview[20 previews]
  Process[20 base / 21 async processors]
  Gate[go-no-go-preproduction.php]
  Evidence[Evidència i decisió]
  Deploy[Preproducció / producció]

  Change --> Unit
  Change --> Integration
  Change --> Database
  Unit --> Runner
  Integration --> Runner
  Database --> Runner
  Runner --> Migrations
  Migrations --> Preflight
  Preflight --> Preview
  Preview --> Process
  Process --> Gate
  Gate --> Evidence
  Evidence -->|GO autoritzat| Deploy
  Gate -->|NO-GO| Change
```

Els recomptes de preflight/process de la branca inclouen els dos scripts nous de cua; preview no incorpora cap script nou. Un resultat de proves correcte no substitueix la connexió a preproducció, el certificat AEAT, les proves de permisos ni l'autorització formal.

## 9. Arquitectura d'informació de les 192 pantalles/apartats

```mermaid
flowchart TB
  Screens[192 pantalles/apartats Trello 4]
  Screens --> Fiscal[Factura/document legacy: 35]
  Screens --> Customer[Consulta, visibilitat i dades: 32]
  Screens --> Panel[Panell o operació SIF: 28]
  Screens --> Review[Validació detallada pendent: 25]
  Screens --> Redsys[Redsys, TPV i callbacks: 16]
  Screens --> Changes[Operació administrativa: 14]
  Screens --> Comms[Comunicacions: 12]
  Screens --> PayWeb[URL o estat de pagament web: 9]
  Screens --> Collection[Cobrament i camps legacy: 8]
  Screens --> Commercial[Descomptes: 6]
  Screens --> Debt[Morositat: 4]
  Screens --> Before[Factura abans de cobrar: 3]
```

Aquesta agrupació reprodueix les 12 famílies disjuntes de la matriu i totalitza 192, però no substitueix el seguiment individual de les quatre fases de cada pantalla.

## 10. Allò que aquest mapa no pot afirmar

- Les carpetes `*-actual` són còpies locals declarades com a actuals, però aquesta auditoria no certifica que coincideixin byte a byte amb cada servidor productiu.
- Les 510 funcions d'`intranet-actual/Intranet.php` inclouen molta funcionalitat no fiscal; només s'han de portar al SIF les operacions amb impacte en factura, cobrament, document, incidència o auditoria.
- El volum de 1.920 PHP inclou biblioteques, proves, còpies datades i codi procedimental; no equival a 1.920 components a migrar.
- El nom `canvis-verifactu` no prova integració: els 25 candidats encara no invoquen els endpoints SIF detectats.
- Els 112/118 tests descriuen el que el repositori prova, no certifiquen per si sols producció ni compliment AEAT.
- Panell, client/worker AEAT, documents segurs, outbox, auditoria transversal, backup executable i reconciliació SIF-llegat continuen pendents o parcials.
- Qualsevol diagrama del sistema productiu complet requerirà contrastar els fitxers no copiats i l'entorn real, sense importar credencials al repositori.

## 11. Relació amb el volum Trello i el backlog granular

Els casos d'ús no són sinònims de targetes. El repositori conté una base reconciliada de 13.277 targetes petites, procedent de documents, Trellos històrics i 6.842 ítems de checklist. Els exports del 2026-06-14 mostren 3.241 targetes obertes a Trello 1, 4.101 a Trello 4, 3.194 a Trello 5 i 3.528 a Trello 6. Són fotografies i conjunts amb deduplicació diferent; no s'han de sumar ni equiparar sense reconciliació.

```mermaid
flowchart TB
  Decisions[Decisions i casos d'ús<br/>UC-01 a UC-86]
  Repo[Classes, scripts, endpoints i migracions]
  Screens[192 pantalles/apartats<br/>4 fases cadascun]
  Evidence[Proves i evidències]

  T1[Trello 1 Control<br/>3.241 obertes a l'export]
  T4[Trello 4 Interfície<br/>4.101 obertes a l'export]
  T5[Trello 5 Proves<br/>3.194 obertes a l'export]
  T6[Trello 6 Motor SIF<br/>3.528 obertes a l'export]
  Reconciled[Base reconciliada<br/>13.277 targetes]

  Decisions --> T1
  Screens --> T4
  Evidence --> T5
  Repo --> T6
  T1 --> Reconciled
  T4 --> Reconciled
  T5 --> Reconciled
  T6 --> Reconciled
  Reconciled -. traça inversa .-> Decisions
  Reconciled -. traça inversa .-> Repo
  Reconciled -. traça inversa .-> Screens
  Reconciled -. traça inversa .-> Evidence
```

No es dibuixen 13.277 nodes: moltes targetes són passos d'implementació, proves, captures, textos, errors o duplicats històrics, no casos d'ús independents. La completitud correcta és conservar la cadena `decisió/cas -> criteri -> codi -> pantalla -> prova -> evidència -> documentació`, amb `30-mapa-trello-repo.md` i els inventaris de `00-control` com a traça granular.

## 12. Frontera d'integració de pagaments acordada

```mermaid
flowchart LR
  subgraph Channels[Canals que conserven la interacció]
    Intranet[Intranet principal]
    Web[Web i ecommerce]
    Student[Intranet alumne]
  end

  subgraph Adapters[Adaptadors servidor pendents]
    IA[Adaptador intranet]
    WA[Adaptador ecommerce]
    SA[Adaptador enllaç o intenció]
  end

  subgraph PaySif[pay.prisma.cat]
    API[API o façana SIF]
    Invoice[InvoiceService]
    Payment[PaymentService]
    Intent[RedsysPaymentIntentService]
    Callback[Callback i worker Redsys]
  end

  Legacy[(BDs llegades)]
  SifDB[(BD SIF)]

  Intranet --> IA
  Web --> WA
  Student --> SA
  IA --> API
  WA --> API
  SA --> API
  API --> Invoice
  API --> Payment
  API --> Intent
  Callback --> Invoice
  Callback --> Payment
  Invoice --> SifDB
  Payment --> SifDB
  Intent --> SifDB
  Legacy -. snapshot d'entrada .-> API
  SifDB -. sincronització mínima posterior .-> Legacy
```

La lògica de pagament amb efecte fiscal que avui està repartida entre intranet i web s'ha de programar i operar a `pay.prisma.cat`. Els canals no han de reproduir numeració, hash, factura ni cobrament en paral·lel; només preparen l'ordre, presenten el resultat i reben compatibilitat posterior.

## 13. Mapa complet de gestió, registre i control

La frontera de pagament anterior no cobreix tota la transformació. `pay.prisma.cat` també ha d'allotjar el pla de control del SIF: correccions fiscals, evidències, cues, incidències, auditoria i governança. La intranet manté la gestió diària, però delega qualsevol decisió que afecti una factura emesa o un registre fiscal.

```mermaid
flowchart TB
  subgraph Daily[Gestió diària als canals]
    Students[Alumnes i entitats]
    Enrollments[Inscripcions, canvis i baixes]
    Commercial[Descomptes, reclamacions i URLs]
    Sales[Venda i cobrament]
  end

  subgraph Gateway[Frontera comuna a pay.prisma.cat]
    Auth[Autenticació, rol i CSRF]
    Idem[Idempotència i concurrència]
    Preview[Previsualització]
    Classifier[Classificador funcional/fiscal]
    Audit[Auditoria]
  end

  subgraph Domain[Serveis de domini]
    Master[Perfils i snapshots]
    Operations[Events operatius]
    Invoice[Factures i rectificatives]
    Economic[Pagaments, retorns i saldos]
    Records[Alta, anul·lació i subsanació]
  end

  subgraph Async[Processament asíncron]
    Redsys[Redsys]
    AEAT[AEAT]
    Docs[PDF, QR i XML]
    Messages[Outbox]
  end

  subgraph Control[Control i evidència]
    Incidents[Incidències]
    Access[Accessos a documents]
    Reconcile[Reconciliació]
    Versions[Versions i declaració]
    Exports[Exports i auditoria]
    Continuity[Backups i restauració]
  end

  Daily --> Gateway
  Auth --> Idem --> Preview --> Classifier --> Audit
  Gateway --> Domain
  Domain --> Async
  Async --> Control
  Domain --> Control
```

### 13.1. Propietat de cada responsabilitat

| Responsabilitat | Canal | SIF `pay.prisma.cat` | Llegat |
| --- | --- | --- | --- |
| Capturar dades i intenció de l'usuari | Sí | Valida | Pot aportar dades d'origen |
| Decidir emissió, cobrament o correcció | No | Sí | No |
| Crear factura, registre, pagament o document | No | Sí | Només resum posterior |
| Registrar canvi de curs, baixa o reclamació | Inicia i consulta | Conserva event/impacte si és crític | Pot mantenir operativa sincronitzada |
| Autoritzar una acció fiscal | UI orientativa | Autoritat servidor | No |
| Gestionar AEAT, retries i dead-letter | No | Sí | No |
| Resoldre incidència fiscal | Enllaça i mostra resum | Sí | No |
| Servir document fiscal | Demana accés | Autoritza, registra i serveix | No exposa fitxer |
| Versionar, exportar i demostrar continuïtat | No | Sí | Font de contrast en reconciliació |

La matriu executable de canvi és `38-matriu-transformacio-funcional-verifactu.md`. Cap adaptador de pagament es pot donar per complet si les gestions que l'envolten continuen modificant factures o imports per una ruta alternativa.
