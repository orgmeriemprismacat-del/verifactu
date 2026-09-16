# Matriu de traçabilitat dels diagrames SIF PrisMa

Data de tall: 2026-09-15

## 1. Objectiu i abast

Aquesta matriu prova la cobertura entre el codi existent, la branca de cua asíncrona de Redsys, els fluxos funcionals documentats i els diagrames/matrius dels documents 31 a 38. No declara com a implementades les peces que només estan dissenyades o pendents.

Inventari verificat:

| Element | Base actual | Branca `feature/redsys-async-queue` | Cobertura documental |
|---|---:|---:|---|
| PHP totals dins `sif/` | 262 al checkout de treball actual | La comparació històrica de branca queda pendent de recalcular després de consolidar aquests canvis | 36, apartat 3 |
| SQL totals dins `sif/database` | 6: 4 migracions, 1 seed i 1 plantilla de permisos | `redsys_callback_queue` continua addicional a la branca asíncrona | 34 i 36, apartats 3 i 8 |
| Classes/interfícies de producció del SIF | 76 declaracions a `sif/src` | Les classes de l'operació comercial del document 31 continuen `[DISSENY]` | 31, apartats 2-17 |
| Classes `*Test` | 118 | Recompte actual del checkout; branca pendent de reconciliació | 31, apartat 13; 36, apartats 3 i 8 |
| Mètodes `test*` | 261 | Inclou les proves afegides al checkout de treball | 31, apartat 13; 36, apartats 3 i 8 |
| Fitxers PHP dins `sif/tests` | 122 | Inclou suport/runner a més de classes `*Test` | 31, apartat 13; 36, apartats 3 i 8 |
| Classes principals del llegat | 10 als candidats; ara també `IntranetAlumne`, `IntranetTutor` i el domini comercial web | Sense canvi a la branca SIF | 31, apartats 10, 14 i 15; 37, apartat 5 |
| Fitxers PHP de `codi-drive` | 1.920 en 7 carpetes: 25 candidats i 1.895 actuals/històrics | Sense canvi a la branca SIF | 36, apartat 4; 37, apartats 2 i 3 |
| Scripts operatius | 58 al checkout actual | La branca asíncrona afegeix scripts de cua; recompte final pendent de fusió | 32, apartat 18, i aquesta matriu |
| Endpoints públics | 3 | Els mateixos 3, amb canvi intern al callback | 31, apartat 7; 32, apartats 2, 23-26; 36, apartat 5 |
| Taules creades per les migracions locals | 40 | `redsys_callback_queue` continua present només a la branca asíncrona | 34, apartats 2, 10, 15 i 16 |
| Casos d'ús principals | 112 numèrics + 13 variants = 125 fitxes | Inclou fluxos base, asíncrons, parcials, de disseny, canal, transició, gestió, registre, auditoria de pagaments i operació comercial prèvia | 33, apartats 3-29 |
| Pantalles/apartats Trello 4 | 192 | Mateix inventari funcional | 33, apartat 22; 36, apartat 9 |
| Targetes petites reconciliades | 13.277 | Inventari de feina, no 13.277 casos d'ús | 36, apartat 11; `30-mapa-trello-repo.md` |

## 2. Traçabilitat de classes

| Família | Elements coberts | Diagrama o catàleg |
|---|---|---|
| Infraestructura i HTTP | `ConnectionFactory`, `TransactionRunner`, `SifException`, `JsonResponse`, `UuidGenerator`, `HashCalculator`, `PaymentStatusCalculator` | 31, apartats 2, 6 i 11.1 |
| Persistència fiscal | `InvoiceRepository`, `FiscalSequenceRepository`, `RectificationRepository`, `IncidentRepository`, `DocumentRepository` | 31, apartats 2, 3, 8 i 11.2 |
| Persistència de cobraments i integracions | `PaymentRepository`, `CreditBalanceRepository`, `RedsysNotificationRepository`, `ManualPaymentInvoiceRepository` | 31, apartats 3, 4, 5 i 11.3 |
| Instantànies del llegat | `LegacyCourseSnapshotRepository`, `LegacyGiftSnapshotRepository`, `LegacyGroupSnapshotRepository`, `LegacyPackSnapshotRepository`, `LegacyUsocSnapshotRepository` | 31, apartats 7, 8 i 11.3 |
| Migració i sincronització | `HistoricalInvoiceMigrationRepository`, `HistoricalInvoiceMigrationService`, `HistoricalInvoicePayloadBuilder`, `LegacySyncRepository`, `LegacySyncService` | 31, apartats 8 i 11.2-11.3 |
| Emissió i cobrament base | `InvoiceService`, `PaymentService`, `InvoiceBeforePaymentService`, `ClaimPaymentService`, `CreditBalanceService` | 31, apartats 2, 3 i 11.4 |
| Rectificacions i devolucions | `ManualRectificationService`, `ManualRefundService` i els seus builders | 31, apartats 3 i 11.5; 32, apartat 5 |
| Builders i validadors transversals | `InvoicePayloadValidator`, `PaymentPayloadValidator`, `DiscountSnapshotFileReader` i builders de pagament, crèdit, reclamació i factura prèvia | 31, apartats 4 i 11.5 |
| Emissió manual | Serveis i builders de curs, pack, grup, regal, quota i factura lliure | 31, apartats 8 i 11.6 |
| Redsys base | `RedsysSignatureValidator`, `RedsysCallbackService`, `RedsysInvoicePayloadBuilder`, serveis de curs, pack, grup, regal i USOC | 31, apartat 5; 32, apartats 4 i 14 |
| USOC | `LegacyUsocInvoicePayloadBuilder`, `UsocEntityInvoiceService`, `RedsysUsocInvoiceService` | 31, apartats 7, 9 i 11.7; 32, apartat 12 |
| Cua Redsys en branca | `RedsysCallbackQueueRepository`, `RedsysPaymentIntentRepository`, `RedsysCallbackDispatcher`, `RedsysCallbackWorker`, `RedsysIntentHandler`, `RedsysJobProcessor`, `RedsysPaymentIntentService` | 31, apartats 5 i 12; 32, apartat 4; 34, apartat 8 |
| Candidats PrisMa | `Intranet`, `ConnexioBBDDSTMT`, `ConnexioIntranet`, `ConnexioPay`, `ConnexioWeb`, `RedsysAPI`, `PagamentCursAutomatic`, `PagamentGrupAutomatic`, `PagamentRegal`, `PagamentTallerAutomatic` | 31, apartat 10; 37, apartat 3 |
| Canals i domini actuals | `IntranetAlumne`, `IntranetTutor`, classes de curs/edició/pack/inscripció/descompte/regal/pagament i punts d'entrada procedimentals | 31, apartats 10.1-10.3; 36, apartat 4; 37, apartat 5 |
| Operació comercial descoberta al codi | `ajax/enviarInscripcio.php`, `ajax/enviarInscripcioTastet.php`, `InscripcioTastet`, `DescompteAmic`, `recent_titulat`, `inscripcions_reptes`, `respGrups` | 33, apartat 29; 38, apartat 17; 40, apartats 5-7 |
| Gestió administrativa objectiu | Classificador fiscal, perfils, canvi de curs, baixa, ajusts i reclamacions | 31, apartat 16.1; 32, apartats 40-42; 38, apartats 4-7 |
| Registre, evidència i governança objectiu | Anul·lació/subsanació, worker AEAT, documents, outbox, incidències, auditoria, versions, exports, reconciliació i continuïtat | 31, apartats 16.2-16.3; 32, apartats 42-44; 34, apartats 13-14; 38 |

El catàleg de l'apartat 11 del document 31 conserva l'inventari individual de
69 classes del baseline i l'apartat 12, les 7 incorporacions de la branca
asíncrona. El checkout de treball ja conté 76 declaracions a `sif/src`; abans de
declarar un nou inventari exhaustiu cal reconciliar quines provenen de la
branca i quines dels canvis funcionals locals. L'apartat 17 marca explícitament
les classes de l'operació comercial com a disseny, no com a PHP existent.

## 3. Traçabilitat dels scripts operatius i de suport

### 3.1 Preflight: 14 scripts

`preflight-claim-payment.php`, `preflight-invoice-before-payment.php`, `preflight-manual-course.php`, `preflight-manual-gift.php`, `preflight-manual-group.php`, `preflight-manual-pack.php`, `preflight-manual-payment.php`, `preflight-redsys-course.php`, `preflight-redsys-gift.php`, `preflight-redsys-group.php`, `preflight-redsys-pack.php`, `preflight-redsys-usoc.php`, `preflight-sif.php` i `preflight-usoc-entity.php`.

### 3.2 Preview: 20 scripts

`preview-claim-payment.php`, `preview-credit-balance.php`, `preview-credit-compensation.php`, `preview-historical-invoice-migration.php`, `preview-invoice-before-payment.php`, `preview-manual-course.php`, `preview-manual-gift.php`, `preview-manual-group.php`, `preview-manual-installment.php`, `preview-manual-invoice.php`, `preview-manual-pack.php`, `preview-manual-payment.php`, `preview-manual-rectification.php`, `preview-manual-refund.php`, `preview-redsys-course.php`, `preview-redsys-gift.php`, `preview-redsys-group.php`, `preview-redsys-pack.php`, `preview-redsys-usoc.php` i `preview-usoc-entity.php`.

### 3.3 Process: 20 scripts

`process-claim-payment.php`, `process-credit-balance.php`, `process-credit-compensation.php`, `process-historical-invoice-migration.php`, `process-invoice-before-payment.php`, `process-manual-course.php`, `process-manual-gift.php`, `process-manual-group.php`, `process-manual-installment.php`, `process-manual-invoice.php`, `process-manual-pack.php`, `process-manual-payment.php`, `process-manual-rectification.php`, `process-manual-refund.php`, `process-redsys-course.php`, `process-redsys-gift.php`, `process-redsys-group.php`, `process-redsys-pack.php`, `process-redsys-usoc.php` i `process-usoc-entity.php`.

### 3.4 Infraestructura i cua

| Àmbit | Scripts | Traçabilitat |
|---|---|---|
| Base | `go-no-go-preproduction.php`, `run-migrations.php` | 32, apartat 18 |
| Branca asíncrona | `preflight-redsys-callback-queue.php`, `process-redsys-callback-queue.php` | 32, apartats 4 i 18; 34, apartat 8 |

## 4. Traçabilitat dels endpoints

| Endpoint | Responsabilitat | Diagrames |
|---|---|---|
| `POST /api/factures/issue` | Emetre una factura a partir d'una comanda validada | 31, apartat 7; 32, apartat 2; 36, apartat 5 |
| `POST /api/payments/register` | Registrar un moviment i assignar-lo a una factura existent | 31, apartat 7; 32, apartat 23; 36, apartat 5 |
| `POST /api/redsys/callback` | Validar i registrar el callback; en la branca, contrastar intenció i encolar-lo | 31, apartats 5 i 7; 32, apartats 4 i 24-27; 34, apartat 8; 36, apartats 5 i 7 |

`GET /api/incidencies` no existeix al checkout. La consulta d'incidències és un cas d'ús i una pantalla de disseny, no un quart endpoint implementat.

## 5. Traçabilitat de dades

| Grup de dades | Taules | Diagrames |
|---|---|---|
| Facturació | `factura`, `factura_linia`, `fiscal_sequence`, `fiscal_chain_state`, `factura_registres`, `factura_rectificacio` | 34, apartats 2, 4 i 5 |
| Cobraments i relacions | `payment_transaction`, `payment_allocation`, `fact_rels`, `credit_balance` | 34, apartats 2, 6 i 9 |
| Integracions i operació | `fiscal_queue`, `factura_documents`, `redsys_notifications`, `errors_verifactu` | 34, apartats 2, 7, 8 i 9 |
| Redsys asíncron | `redsys_payment_intent`, `redsys_callback_queue` | 34, apartats 2 i 8 |
| Gestió i traça funcional pendents | events operatius, historial de perfils, canvis de curs, baixes i ajusts | 34, apartat 13; 38, apartats 4-7 |
| Evidències i governança pendents | intents AEAT, jobs documentals, outbox, accessos, accions d'incidència, versions, exports, reconciliació i continuïtat | 34, apartats 13-14; 38, apartat 6 |
| Operació comercial prèvia | `commercial_operation`, `commercial_operation_party`, `discount_validation`, `payment_link` | 34, apartat 16; 38, apartat 17; 40, apartats 5-8 |

La taula `redsys_callback_queue` només és present a la branca `feature/redsys-async-queue`; la resta formen el model de la base actual o l'ampliació ja documentada al repositori.

## 6. Traçabilitat funcional

| Família funcional | Casos d'ús | Seqüències principals |
|---|---|---|
| Nucli de factura i cobrament | UC-01, UC-02, UC-04 a UC-06 | 32, apartats 2, 3, 5, 14, 23 |
| Redsys, intenció, callback i conciliació | UC-03, UC-25, UC-51, UC-52 | 32, apartats 4, 22, 24-27 |
| Consulta, incidències i AEAT | UC-07 a UC-09, UC-30, UC-31, UC-34 a UC-38, UC-54, UC-55, UC-60 | 32, apartats 6, 7, 18, 28-32 |
| Versió i històric | UC-10, UC-11, UC-39, UC-40, UC-44, UC-46 | 32, apartats 17, 19, 32, 34 |
| Morositat i reclamacions | UC-12, UC-24, UC-43, UC-49, UC-58 | 32, apartats 15 i 30 |
| USOC | UC-13, UC-19 i variants | 32, apartat 12 |
| Curs, taller i jornada | UC-14 i variants | 32, apartat 8 |
| Pack i grup | UC-15, UC-16 i variants | 32, apartats 9 i 10 |
| Regal i bescanvi | UC-17, UC-18 i variants | 32, apartat 11 |
| Descomptes i pagador empresa | UC-20 i variants, UC-21 | 32, apartats 8 i 12 |
| Transferència, fraccions i reclamacions cobrades | UC-22, UC-23, UC-24, UC-56 | 32, apartats 15 i 23 |
| Canvi de curs, baixa i efectes posteriors | UC-26 a UC-33 | 32, apartat 21 |
| Persones, entitats i visibilitat | UC-41, UC-42, UC-45, UC-59 | 32, apartats 28 i 29 |
| Compatibilitat i sincronització llegat | UC-44, UC-47, UC-53 | 32, apartats 20 i 31; 36, apartats 2 i 4 |
| Proformes, documents i correus | UC-36, UC-43, UC-48 a UC-50, UC-55, UC-58 | 32, apartats 18, 29 i 30 |
| Manteniment i continuïtat | UC-39, UC-40, UC-46, UC-57, UC-60 | 32, apartats 19, 32 i 34; 36, apartat 8 |
| Canals intranet, web i alumne | UC-61 a UC-63 | 32, apartats 35-37; 31, apartat 10.3; 36, apartat 12 |
| Transició i retirada del llegat | UC-64, UC-67, UC-68 | 32, apartat 38; 37, apartats 6-8 |
| Factures i cobraments de col·laboradors | UC-65, UC-66 | 32, apartat 39; 34, apartat 12.2; 37, apartat 5.4 |
| Dades mestres, canvis i ajusts | UC-69 a UC-73 | 32, apartats 40-41; 31, apartat 16.1; 38, apartats 3-8 |
| Correcció registral i AEAT | UC-74 a UC-77 | 32, apartat 42; 31, apartat 16.2; 34, apartats 13-14 |
| Documents, comunicacions i accessos | UC-78 a UC-80 | 32, apartat 43; 31, apartat 16.2; 34, apartat 13 |
| Incidències, reconciliació i governança | UC-81 a UC-85 | 32, apartat 44; 31, apartat 16.2; 34, apartats 13-14; 36, apartat 13 |

## 6.1. Cobertura de pantalles

Les 192 files de `03-canvis-pendents/12-matriu-pantalles-abans-despres.md` es conserven com a catàleg individual. El document 33, apartat 22, en demostra la suma per 12 famílies disjuntes: 35 factura/document, 32 consulta/visibilitat, 28 panell SIF, 25 pendents de validar, 16 Redsys/TPV, 14 operacions administratives, 12 comunicacions, 9 URL/estat web, 8 cobraments legacy, 6 descomptes, 4 morositat i 3 factura abans de cobrar.

## 7. Peces pendents que els diagrames no han de presentar com a acabades

| Peça | Situació documentada | Impacte |
|---|---|---|
| Client real d'enviament a l'AEAT i gestió de certificat | Pendent | La seqüència AEAT és el contracte objectiu, no una integració productiva certificada |
| Treballador complet de `fiscal_queue` | Pendent | No es pot tancar el cicle `PENDING` a `ACCEPTED`/`REJECTED` en producció |
| Registres d'anul·lació i subsanació | Pendent | Cal modelar i implementar les operacions fiscals addicionals |
| Panell SIF, autorització i auditoria | Parcial o pendent | Els casos d'ús descriuen el comportament requerit, no una pantalla final disponible |
| Generació, custòdia i lliurament segur de PDF/QR/XML | Parcial o pendent | El model de documents existeix però falta el circuit productiu complet |
| Analitzador i conciliador TPV | Pendent | La seqüència de conciliació és funcional, no una automatització acabada |
| Orquestració de canvi de curs i baixa | Pendent d'integració | Cal connectar les accions del llegat amb cobrament, factura i rectificació |
| Bescanvi complet de regals | Pendent d'integració | Compra i bescanvi s'han de distingir en la integració final |
| Integració definitiva amb ecommerce i intranet | Pendent | Els adaptadors i serveis SIF no substitueixen encara tots els fluxos llegats |
| Cua Redsys asíncrona | Implementada en branca, no integrada a la base | Requereix revisió, merge i desplegament controlat abans de considerar-la operativa |
| Endpoint/adaptador per crear intencions Redsys | Pendent d'integració | `RedsysPaymentIntentService` existeix a la branca, però no hi ha un endpoint públic nou |
| Outbox de correus i notificacions | Pendent | Els enviaments legacy directes no aporten persistència, retry i auditoria SIF |
| Reconciliació automàtica SIF-llegat | Pendent | Cal detectar divergències sense convertir la BD antiga en font fiscal |
| Backup/restauració executable i provat | Pendent | El procediment documental no equival a una restauració amb evidència |
| Capa comuna d'autenticació, CSRF i auditoria dels endpoints | Pendent | Els tres entrypoints actuals componen serveis però no mostren aquest control transversal |
| Reconciliació entre `*-actual` i `*-canvis-verifactu` | Pendent i bloquejant | La candidata d'intranet té menys codi que l'actual i no es pot desplegar com a substitució directa |
| Adaptadors de pagament d'intranet, web i intranet alumne | Pendent | El pagament amb efecte fiscal s'ha de programar a `pay.prisma.cat`; els candidats encara no invoquen els endpoints SIF |
| Secrets incorporats a còpies de codi | Pendent i bloquejant | Cal sanejar, externalitzar i rotar abans de commit o desplegament |

## 8. Regla de manteniment

Qualsevol alta o baixa de classe, prova, script, endpoint, taula, pantalla o cas d'ús ha d'actualitzar, en la mateixa revisió, el diagrama corresponent i aquesta matriu. La comprovació mínima és:

1. cada classe apareix al catàleg del document 31;
2. cada flux executable té seqüència o referència explícita al document 32;
3. cada comportament d'usuari consta al document 33;
4. cada taula i estat persistent consten al document 34;
5. els punts d'entrada procedimentals, configuració, migracions, proves i fronteres consten al document 36;
6. les 192 pantalles mantenen traça individual a la matriu de pantalles;
7. cap peça pendent s'etiqueta com a implementada.

## 9. Fonts utilitzades

- `sif/src`, `sif/public/api`, `sif/config`, `sif/scripts` i `sif/tests` de la base actual;
- `sif/database/migrations`, `sif/database/seeds` i la documentació tècnica del repositori;
- branca vinculada `feature/redsys-async-queue` per a les set classes, dos scripts i una taula addicionals;
- les set carpetes de `codi-drive`: 25 PHP candidats i 1.895 PHP actuals/històrics, inventariats sense llegir fitxers de paràmetres ni reproduir secrets;
- inventaris, matrius de cobertura i procediments ja presents a `documentacio`.

No s'ha carregat ni recorregut el JSONL complet de `xat-original` per elaborar aquesta matriu.

## 10. Abast comprovat i límits de completitud

| Pregunta | Resposta comprovada |
| --- | --- |
| S'han inventariat totes les classes de producció presents a `sif/src`? | Sí: 69 a la base i 76 a la branca, sense comptar `autoload.php`. |
| S'han inclòs les proves? | Sí com a arquitectura i recompte actual: 118 classes `*Test`, 261 mètodes `test*` i 122 PHP dins `sif/tests`; la comparació de branca s'ha de recalcular després de consolidar el checkout. No es dibuixa una caixa per cada test perquè la relació útil és per suite i component. |
| S'han inclòs tots els scripts? | Sí: els 56 noms base i els 2 noms addicionals de cua consten a l'apartat 3. |
| S'han inclòs els endpoints reals? | Sí: exactament tres; s'ha eliminat la confusió amb un `GET /api/incidencies` inexistent. |
| S'ha inventariat `codi-drive`? | Sí a nivell de carpetes, fitxers, PHP, classes pròpies principals i famílies funcionals: 1.920 PHP en set carpetes. Els 25 candidats també s'han comparat per homòleg. |
| S'ha inventariat tota la intranet productiva? | S'ha inventariat la còpia local declarada com a actual: 342 PHP i `Intranet.php` amb 510 funcions. No es certifica que la còpia sigui idèntica al servidor productiu ni es modela cada biblioteca/còpia datada com a domini. |
| S'han cobert totes les pantalles planificades? | Sí a nivell de traça: 192 files individuals a la matriu existent i agrupació completa al document 33. La majoria no estan implementades. |
| S'han convertit les 13.277 targetes reconciliades en casos d'ús? | No, deliberadament: moltes són passos, proves, captures, errors o duplicats. El document 36 explica la relació i conserva la traça cap als inventaris Trello. |
| Els diagrames proven compliment o producció? | No. Mostren codi, contractes i buits; la certificació exigeix implementació, proves, preproducció i aprovació. |

## 11. Reconciliació de les carpetes candidates i actuals

| Comprovació | Resultat | Conseqüència documental |
| --- | --- | --- |
| PHP de les cinc còpies actuals | 1.895 | Substitueix l'afirmació anterior que `codi-drive` només era una selecció de 25 PHP. |
| Comparació dels 25 candidats | 14 idèntics, 8 diferents i 3 sense homòleg directe | El nom de carpeta no permet marcar-los com a integrats. |
| `Intranet.php` actual contra candidata | 510 contra 495 funcions; 39.229 contra 37.603 línies | Reconciliació obligatòria abans d'aplicar canvis. |
| Crides als tres endpoints SIF dins els candidats | 0 detectades | Falten els adaptadors cap a `pay.prisma.cat`. |
| Escriptures llegades dins candidats de pagament | Detectades en 7 scripts | Cal substituir-les o encapsular-les darrere del commit SIF. |
| Intranet alumne | Genera `/pagament` o `/pagaments` a partir d'`IDPAG` xifrat | S'ha de convertir en petició d'enllaç/intenció segura al SIF. |
| Intranet col·laboradors i `old-intranet` | Gestionen honoraris, `cobraments` i factures/rebuts de tutors | Circuit adjacent de proveïdors, separat de vendes SIF. |

La cobertura exhaustiva no significa dibuixar 1.920 caixes. Significa que cap família amb efecte en pagament o factura queda sense classificació, i que cada punt d'entrada actiu haurà de tenir una traça concreta abans de passar a `GO`.

## 12. Traçabilitat de la transformació funcional i registral

La revisió ampliada demostra que centralitzar el pagament és només una part del projecte. El document 38 passa a ser la matriu de control per a gestions, registres i evidències.

| Responsabilitat | Evidència actual | Vista de disseny | Casos d'ús | Persistència requerida | Estat |
| --- | --- | --- | --- | --- | --- |
| Dades fiscals i snapshots | Builders SIF i dades mestres llegades | Classes 16.1; seqüència 40 | UC-69, UC-70 | snapshot + historial de perfil | Parcial/pendent |
| Canvi de curs | `realitzarCanviCurs_modalCanviCurs()` i documentació funcional | Classes 16.1; seqüència 41 | UC-26, UC-71 | event, abans/després, imports i relacions | Pendent d'integració |
| Baixa i decisió econòmica | `confirmaBaixa_modalDonarBaixa()` i fluxos de baixa | Classes 16.1; seqüència 41 | UC-27, UC-72 | event de baixa + decisió separada | Pendent d'integració |
| Ajusts/descomptes/despeses | `A_PAGAR`, validacions i snapshots parcials | Classes 16.1 | UC-20, UC-73 | event d'ajust + línia si té import | Parcial/pendent |
| Rectificativa/anul·lació/subsanació | Rectificativa manual base; altres dos circuits absents | Classes 16.2; seqüència 42 | UC-30, UC-31, UC-74 a UC-76 | registres immutables i cadena | Bloquejant |
| Enviament AEAT | Cua base sense consumidor productiu | Classes 16.2 | UC-09, UC-54, UC-77 | intents, resposta, retry i dead-letter | Bloquejant |
| Documents | `DocumentRepository` i taula base | Classes 16.2; seqüència 43 | UC-36, UC-55, UC-78, UC-80 | jobs, documents, hashes i accessos | Parcial/pendent |
| Comunicacions | `Template` i correus directes llegats | Classes 16.2; seqüència 43 | UC-43, UC-49, UC-58, UC-79 | outbox i intents d'entrega | Pendent |
| Incidències | `IncidentRepository` i `errors_verifactu` mínims | Classes 16.2; seqüència 44 | UC-08, UC-81 | responsable, prioritat i historial | Parcial/pendent |
| Reconciliació | `LegacySyncService` només en sentit SIF cap al llegat | Classes 16.2 | UC-53, UC-82 | runs, items, resolució i evidència | Pendent |
| Governança | Documents de versió, proves i declaració | Classes 16.2 | UC-46, UC-83 a UC-85 | versió, declaració, export i continuïtat | Pendent |
| Seguretat comuna | Controls dispersos de sessió/rol | Classes 16.3 | tots els UC crítics | auditoria i registre d'accés | Bloquejant |
| Traça universal de pagaments | `PaymentService`/`PaymentRepository` sense ledger d'accions | Classes 16.4; seqüència 45 | UC-86 i tots els UC que toquen pagaments | `payment_action_event` append-only | Bloquejant |

## 13. Nova regla de completitud

Una pantalla no queda coberta perquè aparegui entre les 192 files ni perquè apunti a un UC genèric. Per tancar-la s'ha de poder seguir aquesta cadena:

```text
pantalla/mètode actual
-> regla abans/després
-> comanda autenticada i idempotent
-> classificador d'impacte
-> serveis executats
-> registres creats
-> estats resultants
-> error/retry/incidència
-> prova i evidència
```

La matriu de 192 pantalles conserva el catàleg visual; `38-matriu-transformacio-funcional-verifactu.md` defineix les obligacions transversals que les descripcions genèriques d'aquella matriu no detallaven.

Per a qualsevol flux de pagament, la cadena anterior ha d'incloure a més:

```text
REQUESTED -> resultat terminal -> UUID_PAYMENT/idempotency key -> actor -> entorn -> correlation id
```

La manca de `payment_action_event`, la impossibilitat d'escriure'l o una correlació sense resultat terminal són condicions bloquejants i poden obrir una incidència SIF.

## 14. Cobertura de les fitxes funcionals i persistència física

| Control | Evidència | Resultat actual |
| --- | --- | --- |
| Catàleg canònic | `33-casos-us-sif.md` | 112 UC numèrics + 13 variants = 125 fitxes. |
| Reconciliació del backlog funcional | `39-auditoria-fitxes-funcionals.md` | 185 `Fitxes mare` en tres taulers, inventariades/classificades; contingut encara pendent de validació claim a claim. |
| Fitxes estructurades | `documentacio/06-fitxes-funcionals/` | 125 fitxers, 21 apartats per fitxa; no es declaren completes. |
| Validació mecànica | `sif/tools/functional-card/generate-catalog.ps1 -ValidateOnly` | Comprova recompte, apartats i placeholders; no valida veritat funcional. |
| Persistència registral | migracions `2026_09_15_000003` i `2026_09_16_000004` | 21 taules de control + 4 taules d'operació comercial i ampliació de camps fiscals. |
| Escriptura append-only | repositoris `PaymentActionEventRepository` i `OperationalEventRepository` | Validació i `INSERT`; sense mètodes d'update/delete. |
| Permisos | `sif/database/permissions/functional-audit-roles.sql` | Plantilla sense `UPDATE`/`DELETE` per events; desplegament pendent. |
| Proves | `FunctionalAuditSchemaTest`, `PaymentActionEventRepositoryTest` | Proves afegides; execució PHP/MySQL pendent en aquest host. |

### 14.1. Regla de traçabilitat per fitxa

Cada fitxa declara com a mínim:

```text
UC -> actor/permís -> precondicions -> entrada -> regles -> flux
   -> impacte fiscal/econòmic -> taules -> auditoria -> notificació
   -> proves -> buits/decisions -> tasques de programació
```

La presència de la fitxa només demostra que existeix un contenidor estructurat.
El camp `Estat d'implementació`, la marca
`STRUCTURED_DRAFT_NEEDS_CASE_REVIEW` i els pendents bloquejants impedeixen
confondre'l amb `READY_FOR_PROGRAMMING` o amb producció.

### 14.2. Nous casos recuperats

UC-87 a UC-105 cobreixen explícitament receptor estranger/incomplet, factura
multiconcepte, canvi de concepte, descompte validat tard, descompte de grup,
venda manual, canvi de receptor posterior, ajust manual d'import, deute amb
estat acadèmic, pròrroga, emissors històrics, botiga/SL, tutors, operacions no
facturables, domini/TLS, alumne sense rol, delegació web, excés de cobrament i
reassignació/repartiment de pagaments.

UC-106 a UC-112 cobreixen la reserva prèvia al pagament, duplicats
d'inscripció, tastets gratuïts, cursos subvencionats, descompte d'amics,
docent novell/dret futur i snapshot complet abans del TPV. La seva evidència
prové del codi actual i no d'una deducció genèrica del flux de pagament.
