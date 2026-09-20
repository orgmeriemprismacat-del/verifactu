# Checklist de completitud

Aquest checklist controla si la informacio del xat antic ja ha estat revisada i incorporada als documents.

## Recuperacio inicial

- [x] Copiar documentacio recuperada a `documentacio/`.
- [x] Copiar xat antic a `xat-original/`.
- [x] Crear fitxers de control a `00-control/`.
- [x] Obrir xat pont.
- [x] Fer primera revisio inicial del xat antic per temes.
- [x] Proposar ordre de revisio per blocs.

## Revisio per arees

- [x] Context actual de PrisMa revisat contra el xat antic.
- [x] Casos de facturacio revisats contra el xat antic.
- [x] Pagaments, Redsys i `pay.prisma.cat` revisats contra el xat antic.
- [x] Base de dades i relacions revisades contra el xat antic.
- [x] Compliment AEAT i declaracio responsable revisats contra el xat antic.
- [x] Pantalles, permisos i operacio interna revisats contra el xat antic.
- [x] Correus, plantilles, PDF/QR i notificacions revisats contra el xat antic.
- [x] Proves, produccio i governanca revisades contra el xat antic.

## Documents clau

- [x] `documentacio/README.md` reflecteix l'estat actual.
- [x] `documentacio/00-index-i-pla/documentacio-verifactu.md` esta actualitzat.
- [x] `documentacio/00-index-i-pla/29-pla-implementacio-tecnica-sif.md` creat com a pla tecnic executable del nucli SIF.
- [ ] `documentacio/01-compliment-aeat/documentacio-sif-aeat.md` esta complet.
- [x] `documentacio/01-compliment-aeat/declaracio-responsable-sif-prisma.md` esta revisat.
- [ ] `documentacio/02-context-i-estat-actual/12-documentacio-sistema-ecommerce-intranet-sif.md` esta complet.
- [ ] `documentacio/02-context-i-estat-actual/13-mapa-bases-dades-i-taules.md` esta complet.
- [x] `documentacio/03-canvis-pendents/11-inventari-canvis-pendents.md` esta al dia per als fluxos fiscals especials tancats el 2026-06-14.
- [ ] `documentacio/04-estat-final/05-model-bd-sif.md` esta complet.
- [ ] `documentacio/04-estat-final/15-estat-final-sistema.md` esta complet.
- [ ] `documentacio/04-estat-final/25-panell-sif-pay-prisma.md` esta complet.
- [ ] `documentacio/05-governanca-operacio/20-pla-proves-validacio-sif.md` esta complet.

## Preparacio normativa i documental

- [x] Fonts oficials AEAT/BOE comprovades per al bloc normatiu del SIF.
- [x] Criteri de versio `0.1-BORRADOR` no signable i `1.0.0` signable reforcat.
- [x] Paquet documental minim per activar/signar `1.0.0` definit.
- [x] Criteri productor/titular intern documentat: Associacio PrisMa; Meriem com a responsable tecnica/documental; direccio com a signant o representacio legal quan correspongui.
- [x] Certificat digital de l'entitat o apoderament documentat com a requisit de configuracio/prova abans de produccio.
- [x] Rol auditor/AEAT nomes lectura reforcat amb acces temporal, logs i prohibicions.
- [x] Camps fiscals minims del registre d'alta, QR, AEAT, documents fiscals i declaracio responsable incorporats al diccionari.
- [ ] Dades reals de signatura de direccio completades.
- [ ] Certificat digital/apoderament configurat i provat amb evidencia real.
- [ ] Declaracio responsable `1.0.0` generada com a document tancat i signable.
- [ ] Declaracio responsable signada publicada dins del SIF.
- [ ] Decisio final sobre dades personals del contacte tecnic en document signat o expedient intern.

## Arquitectura tecnica SIF

- [x] Contracte `issueInvoice()` tancat: numeracio fiscal, linies, registre fiscal, hash chain, cua AEAT, relacions i idempotencia.
- [x] Contracte `registerPayment()` tancat: moviment economic, assignacio, estat de cobrament i auditoria sense numero fiscal ni hash chain.
- [x] Criteri tancat per factura i cobrament nascuts junts: `issueInvoice()` amb bloc `payment` dins una unica operacio idempotent.
- [x] `fact_rels` consolidat com a relacio logica auditada amb BD antiga, sense foreign keys entre BD fiscal i BD web/intranet.
- [x] Valors controlats ampliats per cobrament, pagaments, assignacions, origins, descomptes i relacions.
- [x] Contracte d'entrada de pagaments definit: Redsys -> `redsys_notifications`, transferencies/manuals -> `payment_transaction`, TPV -> analisi/conciliacio, assignacions -> `payment_allocation`.
- [x] Pla d'implementacio tecnica creat a partir de l'arquitectura tancada, amb fases per `issueInvoice()`, `registerPayment()`, idempotencia, hash chain, taules fiscals i relacio amb BD antiga.

## Implementacio tecnica SIF

- [x] Pla d'implementacio tecnica documentat.
- [x] Codi necessari del Drive copiat a `codi-drive/` com a referencia local, sense fitxers de parametres amb credencials.
- [x] Fase 0 preparada al repo de treball sense Composer: autoload propi, runner de proves PHP pur, config SIF i bootstrap de tests.
- [x] Fase 1 preparada al repo de treball: migracio SQL, seed, runner i test estructural d'esquema.
- [x] Fase 2 preparada al repo de treball: `ConnectionFactory`, `TransactionRunner`, `UuidGenerator`, `SifException` i proves unitàries corresponents.
- [x] Fase 3 preparada al repo de treball: `InvoicePayloadValidator`, `PaymentPayloadValidator` i proves unitàries corresponents.
- [x] Task 4 de Fase 4 preparada al repo de treball: `HashCalculator` i proves unitàries de hash fiscal intern.
- [x] Task 5 de Fase 4 preparada al repo de treball: `FiscalSequenceRepository`, `InvoiceRepository`, `InvoiceService`, fixtures i test d'integracio d'`issueInvoice()`.
- [x] Task 6 de Fase 4 preparada al repo de treball: proves d'idempotencia i concurrencia seqüencial d'`issueInvoice()`.
- [x] Fase 5 preparada al repo de treball: `PaymentStatusCalculator`, `PaymentRepository`, `PaymentService` i proves de `registerPayment()`.
- [x] Fase 6 preparada al repo de treball: `LegacySyncRepository`, `LegacySyncService` i proves de `fact_rels`/sincronitzacio resum.
- [x] Fase 7 preparada al repo de treball: `JsonResponse` i endpoints interns `factures/issue` i `payments/register`.
- [x] Fase 8 preparada al repo de treball: `RedsysNotificationRepository`, `RedsysCallbackService`, endpoint `redsys/callback` segur per defecte i proves de deduplicacio `DS_ORDER`.
- [x] Contracte previ al TPV definit i migracio `redsys_payment_intent` creada amb `DS_ORDER` unic, origen, `IDPAG`, import/divisa/terminal esperats i snapshot.
- [x] Matriu documental de callback duplicat definida: coherent i idempotent, contradictori i bloquejant, o concurrent amb relectura del resultat persistent.
- [ ] Repositori i servei PHP de `redsys_payment_intent` implementats amb proves executables.
- [ ] Punts de creacio Redsys de curs, pack, grup, regal i USOC connectats a `redsys_payment_intent` abans de redirigir al TPV.
- [ ] Callback Redsys resol `DS_ORDER` al servidor, ignora `IDPAG` de query string i invoca automaticament l'orquestrador corresponent.
- [ ] Proves de duplicat idèntic, duplicat contradictori i callback concurrent executades amb PHP/MySQL.
- [x] Disseny de cua asincrona Redsys amb taula propia, worker, dispatcher, reintents i resultats documentat a `13-cua-asincrona-callbacks-redsys.md`.
- [x] Pla TDD de les nou targetes de cua Redsys documentat a `14-pla-implementacio-cua-redsys.md` amb fitxers, proves i comandes exactes.
- [ ] Migracio i repositori de `redsys_callback_queue` implementats amb prova executable.
- [ ] Worker/dispatcher asincron implementat per curs, pack, grup, regal i USOC.
- [ ] Sincronitzacio legacy reforcada amb idempotencia abans d'incorporar-la als reintents automatics.
- [x] Fase 9 preparada al repo de treball: `DocumentRepository`, `IncidentRepository`, proves de documents/incidencies i reforç de cua AEAT amb payload congelat.
- [x] Fase 10 preparada al repo de treball: `preflight-sif.php`, `go-no-go-preproduction.php`, `PreflightScriptTest` i `GoNoGoPreproductionScriptTest`.
- [x] Fase 10 go/no-go reforçada: la bateria de preproduccio comprova tambe circuits de saldo, devolucio, USOC, grups i reclamacio/morositat abans d'un pilot.
- [x] Fase 11 iniciada com a preparacio tecnica: `issueInvoice(payment)` crea factura, registre fiscal, hash chain, `payment_transaction` i `payment_allocation` en una mateixa transaccio idempotent quan factura i cobrament neixen junts.
- [x] Fase 11 Redsys preparada a nivell de signatura i resposta: `RedsysSignatureValidator`, `SIF_REDSYS_MERCHANT_KEY`, prova unitària amb notificacio signada de test, classificacio `VALIDATED`/`ERROR` i callback Redsys amb POST signat sense secret hardcoded.
- [x] Fase 11 Redsys preparada a nivell de payload: `RedsysInvoicePayloadBuilder` construeix `issueInvoice(payment)` nomes des de `redsys_notifications.STATUS = VALIDATED`.
- [x] Fase 11 curs normal preparada a nivell de snapshot legacy: `LegacyCourseInvoicePayloadBuilder` converteix `inscripcions` + `curs` en payload fiscal base validable sense consultar encara la BD antiga i exigeix import actual de pagament.
- [x] Fase 11 curs normal preparada a nivell de lectura legacy: `LegacyCourseSnapshotRepository` carrega `inscripcions` per `IDPAG`, `curs` per `ANY`/`MES`/`CURS` i retorna snapshot de nomes lectura.
- [x] Fase 11 curs normal preparada a nivell d'orquestrador de servei: `RedsysCourseInvoiceService` compon notificacio `VALIDATED`, snapshot legacy, payload Redsys i `issueInvoice(payment)`.
- [x] Fase 11 curs normal preparada a nivell de prova manual: `SIF_LEGACY_DB_*`, `ConnectionFactory::makeLegacy()` i `sif/scripts/process-redsys-course.php` per entorn no productiu.
- [x] Fase 11 curs normal preparada a nivell de preflight especific: `sif/scripts/preflight-redsys-course.php` comprova entorn, Redsys, BD SIF, BD legacy i taules minimes sense escriure dades.
- [x] Fase 11 curs normal preparada a nivell de preview: `sif/scripts/preview-redsys-course.php` construeix payload `issueInvoice(payment)` sense crear factura ni pagament.
- [x] Fase 11 curs normal preparada a nivell de sync legacy opcional: `process-redsys-course.php DS_ORDER --sync-legacy` pot cridar `LegacySyncService` nomes despres d'exit SIF.
- [x] Fase 11 factura abans de cobrament preparada a nivell de prova: `InvoiceBeforePaymentFlowTest` cobreix `issueInvoice(emesa_abans_cobrament=1)` + `registerPayment()` sense segon registre fiscal.
- [x] Fase 11 factura abans de cobrament preparada a nivell de circuit CLI: `InvoiceBeforePaymentPayloadBuilder`, `InvoiceBeforePaymentService`, `preflight-invoice-before-payment.php`, `preview-invoice-before-payment.php` i `process-invoice-before-payment.php` emeten factura pendent de cobrament sense registrar pagament inicial.
- [x] Fase 11 transferencies manuals preparada a nivell de builder: `ManualPaymentPayloadBuilder` construeix payload de `registerPayment()` per `Passar pagaments` contra factura existent amb idempotencia per referencia bancaria o fallback factura/data/import/banc.
- [x] Fase 11 transferencies manuals contra factura existent preparada a nivell de circuit CLI: `ManualPaymentInvoiceRepository`, `ManualPaymentService`, `preflight-manual-payment.php`, `preview-manual-payment.php` i `process-manual-payment.php` registren `registerPayment()` per `UUID_FACTURA` o `NUM_VISIBLE` sense emetre factura ni dependre de legacy/Redsys.
- [x] Fase 11 reclamacio/morositat preparada a nivell de circuit CLI: `ClaimPaymentPayloadBuilder`, `ClaimPaymentService`, `preflight-claim-payment.php`, `preview-claim-payment.php` i `process-claim-payment.php` registren `registerPayment()` amb assignacio `CLAIM_PAYMENT` contra factura existent, sense factura nova ni registre fiscal nou.
- [x] Fase 11 pagaments fraccionats manuals preparada a nivell de circuit CLI: `ManualInstallmentPaymentPayloadBuilder`, `ManualInstallmentPaymentService`, `preview-manual-installment.php` i `process-manual-installment.php` registren cada fraccio com a `registerPayment()` amb assignacio `INSTALLMENT_PAYMENT`, sense duplicar factura ni registre fiscal.
- [x] Fase 11 rectificatives manuals preparada a nivell de circuit CLI: `ManualRectificationPayloadBuilder`, `RectificationRepository`, `ManualRectificationService`, `preview-manual-rectification.php` i `process-manual-rectification.php` emeten factura serie `R`, creen `factura_rectificacio` i marquen l'original com `RECTIFIED`.
- [x] Fase 11 factura manual preparada a nivell de circuit CLI: `ManualInvoicePayloadBuilder`, `ManualInvoiceService`, `preview-manual-invoice.php` i `process-manual-invoice.php` normalitzen payloads manuals d'intranet i criden `issueInvoice()`, amb pagament inicial opcional dins el mateix payload.
- [x] Fase 11 migracio historica preparada a nivell de circuit CLI: `HistoricalInvoicePayloadBuilder`, `HistoricalInvoiceMigrationRepository`, `HistoricalInvoiceMigrationService`, `preview-historical-invoice-migration.php` i `process-historical-invoice-migration.php` importen factures historiques com `NO_VERIFACTU`, sense registre fiscal ni cua AEAT retroactiva.
- [x] Fase 11 devolucions manuals contra factura existent preparada a nivell de circuit CLI: `ManualRefundPayloadBuilder`, `ManualRefundService`, `preview-manual-refund.php` i `process-manual-refund.php` registren `REFUND` per `UUID_FACTURA` o `NUM_VISIBLE` sense emetre factura, sense registre fiscal nou i sense legacy sync.
- [x] Fase 11 compensacio/saldo preparada a nivell de circuit CLI: `CreditBalancePayloadBuilder`, `CreditBalanceRepository`, `CreditBalanceService`, `preview-credit-balance.php`, `process-credit-balance.php`, `preview-credit-compensation.php` i `process-credit-compensation.php` creen saldo a `credit_balance` i l'apliquen com a `COMPENSATION` idempotent contra factura existent.
- [x] Fase 11 transferencies manuals sense factura SIF prèvia preparada per curs normal: `ManualCourseInvoicePayloadBuilder` construeix payload `issueInvoice(payment)` amb idempotencia per `IDPAG`/referencia o fallback data/import/banc.
- [x] Fase 11 transferencies manuals sense factura SIF prèvia preparada a nivell d'orquestrador: `ManualCourseInvoiceService` carrega snapshot legacy per `IDPAG` i crida `issueInvoice(payment)` sense escriure a legacy.
- [x] Fase 11 transferencies manuals sense factura SIF prèvia preparada a nivell de preview: `sif/scripts/preview-manual-course.php` construeix payload manual de curs en dry-run sense factura, pagament ni sync legacy.
- [x] Fase 11 transferencies manuals sense factura SIF prèvia preparada a nivell de processador manual: `sif/scripts/process-manual-course.php` executa `issueInvoice(payment)` en preproduccio i permet `--sync-legacy` opcional post-SIF.
- [x] Fase 11 transferencies manuals sense factura SIF prèvia preparada a nivell de preflight: `sif/scripts/preflight-manual-course.php` comprova entorn, BD SIF, BD legacy, taules minimes i seed fiscal sense dependre de Redsys.
- [x] Fase 11 packs preparada a nivell de snapshot/payload: `LegacyPackSnapshotRepository` i `LegacyPackInvoicePayloadBuilder` construeixen payload fiscal `PACK` multi-linia amb descompte `PACK` del 25% a la segona linia i relacions `PACK`/`INSCRIPCIO`.
- [x] Fase 11 Redsys preparada per source type de pack: `RedsysInvoicePayloadBuilder` conserva `CURS` per defecte i genera `REDSYS|PACK|IDPAG:{IDPAG}|ORDER:{DS_ORDER}` quan el payload declara `source_type = PACK`.
- [x] Fase 11 packs preparada a nivell d'orquestrador Redsys: `RedsysPackInvoiceService` compon notificacio `VALIDATED`, snapshot legacy pack, payload Redsys i `issueInvoice(payment)`.
- [x] Fase 11 packs preparada a nivell de preproduccio manual: `preflight-redsys-pack.php`, `preview-redsys-pack.php` i `process-redsys-pack.php` permeten revisar/processar un pack validat sense activar callback automatic.
- [x] Fase 11 packs preparada a nivell de transferencia manual: `ManualPackInvoicePayloadBuilder` i `ManualPackInvoiceService` construeixen `issueInvoice(payment)` amb `source_channel = INTRANET`, idempotencia `TRANSFERENCIA|PACK|...` i import manual igual al total fiscal del pack.
- [x] Fase 11 packs preparada a nivell de circuit manual de `Passar pagaments`: `preflight-manual-pack.php`, `preview-manual-pack.php` i `process-manual-pack.php` permeten revisar/processar un pack manual sense Redsys i amb `--sync-legacy` opcional post-SIF.
- [x] Fase 11 grups preparada a nivell de snapshot/payload: `LegacyGroupSnapshotRepository` i `LegacyGroupInvoicePayloadBuilder` construeixen payload fiscal `GRUP` amb receptor `respGrups`, una linia per participant i relacions `GRUP`/`INSCRIPCIO` no visibles a alumne.
- [x] Fase 11 grups preparada a nivell de circuits Redsys/manuals: `RedsysGroupInvoiceService`, `ManualGroupInvoicePayloadBuilder`, `ManualGroupInvoiceService`, preflights, previews i processadors CLI de grup amb `--sync-legacy` opcional post-SIF.
- [x] Fase 11 regals preparada a nivell de snapshot/payload: `LegacyGiftSnapshotRepository` i `LegacyGiftInvoicePayloadBuilder` construeixen payload fiscal `REGAL` amb comprador com a receptor, una linia `REGAL`, relacio `REGAL` no visible a alumne i sense crear inscripcio del destinatari.
- [x] Fase 11 regals preparada a nivell de circuit Redsys manual: `RedsysGiftInvoiceService`, `preflight-redsys-gift.php`, `preview-redsys-gift.php` i `process-redsys-gift.php` processen un `DS_ORDER` validat i un regal explicit per `ID` o `CODI`, sense sync legacy en aquest tall.
- [x] Fase 11 regals preparada a nivell de circuit manual de `Passar pagaments`: `ManualGiftInvoicePayloadBuilder`, `ManualGiftInvoiceService`, `preflight-manual-gift.php`, `preview-manual-gift.php` i `process-manual-gift.php` processen un regal explicit per `ID` o `CODI`, amb import manual igual a `regal.IMPORT` i sense sync legacy en aquest tall.
- [x] Fase 11 USOC preparada a nivell de snapshot/payload: `LegacyUsocSnapshotRepository` i `LegacyUsocInvoicePayloadBuilder` construeixen doble payload fiscal `USOC_ALUMNE`/`USOC_ENTITAT`, exigeixen `TIPUS_DESC = 4`, `VALID_DESC = 1` i receptor fiscal explicit per l'entitat USOC.
- [x] Fase 11 USOC preparada a nivell de circuit Redsys d'alumne: `RedsysUsocInvoiceService`, `preflight-redsys-usoc.php`, `preview-redsys-usoc.php` i `process-redsys-usoc.php` processen `USOC_ALUMNE` des d'un `DS_ORDER` validat, amb `--usoc-amount` explicit i `entity_invoice_pending` per no crear `USOC_ENTITAT` sense dades fiscals completes.
- [x] Fase 11 USOC preparada a nivell de circuit d'entitat: `UsocEntityInvoiceService`, `preflight-usoc-entity.php`, `preview-usoc-entity.php` i `process-usoc-entity.php` creen/revisen `USOC_ENTITAT` des d'un JSON amb billing explicit, sense registrar pagament ni sincronitzar legacy.
- [x] Fase 11 codis promocionals preparada a nivell de payload de curs normal: `LegacyCourseInvoicePayloadBuilder` congela snapshot `discount` en totals i camps `DESC_*` de `factura_linia`, sense revalidar `promocions`.
- [x] Fase 11 codis promocionals preparada a nivell de circuit Redsys curs: `RedsysCourseInvoiceService`, `preview-redsys-course.php` i `process-redsys-course.php` accepten un snapshot `discount` explicit via `--discount-file=discount.json`, sense consultar encara `promocions`/`descomptes`.
- [x] Fase 11 codis promocionals preparada a nivell de circuit manual curs: `ManualCourseInvoiceService`, `preview-manual-course.php` i `process-manual-course.php` accepten un snapshot `discount` explicit via `--discount-file=discount.json`, sense consultar encara `promocions`/`descomptes`.
- [x] Fase 11 codis promocionals reforçada amb `DiscountSnapshotFileReader` compartit per llegir `discount.json` en circuits Redsys/manuals de curs, amb prova unitària de fitxer valid i casos invalids.
- [ ] Fase 0 executada amb PHP real: `php sif/tests/run-tests.php` carrega autoload i runner.
- [ ] Fase 1 executada amb PHP/MySQL de test: test d'esquema i migracio aplicats.
- [ ] Fase 2 executada amb runner propi: proves unitàries de UUID, excepcions i transaccions.
- [ ] Fase 3 executada amb runner propi: proves unitàries de validators de factura i pagament.
- [ ] Task 4 de Fase 4 executada amb runner propi: proves unitàries de hash fiscal intern.
- [ ] Task 5 de Fase 4 executada amb runner propi i MySQL de test: emissio basica i reutilitzacio per `IDEMPOTENCY_KEY`.
- [ ] Task 6 de Fase 4 executada amb runner propi i MySQL de test: idempotencia, numeracio lineal, ordre fiscal i hashes únics.
- [ ] Fase 5 executada amb runner propi i MySQL de test: `registerPayment()`, `payment_transaction`, `payment_allocation` i estat de cobrament.
- [ ] Fase 6 executada amb runner propi i MySQL de test: `fact_rels` i sincronitzacio legacy explicita post-SIF.
- [ ] Fase 7 executada amb runner propi i servidor local PHP: endpoints interns `issue` i `register`.
- [ ] Fase 8 executada amb runner propi i MySQL de test: `redsys_notifications`, deduplicacio `DS_ORDER` i endpoint callback.
- [ ] Fase 9 executada amb runner propi i MySQL de test: `fiscal_queue`, `factura_documents`, `errors_verifactu` i payload fiscal congelat.
- [ ] Fase 10 executada amb PHP/MySQL de test: `php sif/scripts/preflight-sif.php` retorna `ok=true`.
- [ ] Fase 11 activada en preproduccio: Redsys real validat criptograficament, `issueInvoice(payment)` executat amb BD test i reintents idempotents verificats.
- [ ] Validacio criptografica Redsys real executada amb `SIF_REDSYS_MERCHANT_KEY` i notificacio de test abans de permetre `issueInvoice()` o `registerPayment()` des del callback.
- [ ] Lectura real de `inscripcions`/`curs` per curs normal connectada en preproduccio i contrastada amb el snapshot fiscal abans de Redsys.
- [ ] Orquestrador de curs normal Redsys executat amb PHP/MySQL de test i evidencies reals abans d'activar cap endpoint.
- [ ] `preflight-redsys-course.php` executat en preproduccio amb `ok=true`.
- [ ] `preview-redsys-course.php DS_ORDER` executat en preproduccio i payload revisat abans de processar.
- [ ] Script manual `process-redsys-course.php` executat en preproduccio amb `SIF_ENV=test`, BD SIF test, BD legacy test i `DS_ORDER` validat.
- [ ] Sync legacy opcional executada en preproduccio amb `--sync-legacy` i revisio de resum antic.
- [ ] Flux factura abans de cobrament executat en preproduccio amb PHP/MySQL de test, `preflight-invoice-before-payment.php`, `preview-invoice-before-payment.php --payload-file=payload.json`, `process-invoice-before-payment.php --payload-file=payload.json`, cobrament posterior per `registerPayment()` i evidencia `SIF-FAC-001`.
- [ ] Flux transferencia manual executat en preproduccio amb PHP/MySQL de test, pantalla `Passar pagaments` i evidencia `SIF-PAY-001`.
- [ ] Flux reclamacio/morositat executat en preproduccio amb PHP/MySQL de test, `preflight-claim-payment.php`, `preview-claim-payment.php`, `process-claim-payment.php`, URL/correu de reclamacio i evidencia que no crea factura ni registre fiscal nou.
- [ ] Flux pagaments fraccionats manuals executat en preproduccio amb PHP/MySQL de test, `preview-manual-installment.php`, `process-manual-installment.php`, dues fraccions successives, reintent idempotent i evidencia d'estat `PARTIAL`/`PAID`.
- [ ] Flux rectificatives manuals executat en preproduccio amb PHP/MySQL de test, `preview-manual-rectification.php`, `process-manual-rectification.php`, import negatiu/positiu, mode `DIFERENCIES`/`SUBSTITUCIO`, reintent idempotent i evidencia de `factura_rectificacio`.
- [ ] Flux factura manual executat en preproduccio amb PHP/MySQL de test, `preview-manual-invoice.php --payload-file=payload.json`, `process-manual-invoice.php --payload-file=payload.json`, factura pendent, factura amb `payment` inicial, reintent idempotent i evidencia de registre fiscal/cua AEAT.
- [ ] Flux migracio historica executat en preproduccio amb PHP/MySQL de test, `preview-historical-invoice-migration.php --payload-file=payload.json`, `process-historical-invoice-migration.php --payload-file=payload.json`, factura `NO_VERIFACTU`, relacio `HISTORIC_LINK`, document antic amb hash i absencia de `factura_registres`/`fiscal_queue`.
- [ ] Flux devolucio manual executat en preproduccio amb PHP/MySQL de test, `preview-manual-refund.php`, `process-manual-refund.php`, prova parcial/total i evidencia d'estat `PARTIALLY_REFUNDED`/`REFUNDED`.
- [ ] Flux compensacio/saldo executat en preproduccio amb PHP/MySQL de test, `preview-credit-balance.php`, `process-credit-balance.php`, `preview-credit-compensation.php`, `process-credit-compensation.php`, prova parcial/total i evidencia de `credit_balance.IMPORT_DISPONIBLE`, `ACTIVE`/`USED` i `payment_transaction.TIPUS_MOVIMENT = COMPENSATION`.
- [ ] Flux transferencia manual sense factura SIF prèvia executat en preproduccio amb curs normal, PHP/MySQL de test i evidencia `SIF-PAY-001`.
- [ ] Orquestrador manual de curs executat en preproduccio amb `IDPAG` real de test i revisio de metadades `legacy_sync`.
- [ ] `preflight-manual-course.php` executat en preproduccio amb `ok=true`.
- [ ] `preview-manual-course.php IDPAG AMOUNT MOVEMENT_DATE` executat en preproduccio i payload revisat abans de processar.
- [ ] `process-manual-course.php IDPAG AMOUNT MOVEMENT_DATE` executat en preproduccio amb `SIF_ENV=test`, BD SIF test i BD legacy test.
- [ ] Payload de pack executat amb PHP/MySQL de test i dades legacy de preproduccio abans d'activar endpoints o callback automatic de pack.
- [ ] `preflight-redsys-pack.php` executat en preproduccio amb `ok=true`.
- [ ] `preview-redsys-pack.php DS_ORDER` executat en preproduccio i payload multi-linia revisat abans de processar.
- [ ] `process-redsys-pack.php DS_ORDER` executat en preproduccio amb `SIF_ENV=test`, BD SIF test i BD legacy test.
- [ ] Sync legacy opcional de pack executada en preproduccio amb `--sync-legacy` i revisio de les dues inscripcions.
- [ ] `preflight-manual-pack.php` executat en preproduccio amb `ok=true`.
- [ ] `preview-manual-pack.php IDPAG AMOUNT MOVEMENT_DATE` executat en preproduccio i payload multi-linia revisat abans de processar.
- [ ] `process-manual-pack.php IDPAG AMOUNT MOVEMENT_DATE` executat en preproduccio amb `SIF_ENV=test`, BD SIF test i BD legacy test.
- [ ] Sync legacy opcional de pack manual executada en preproduccio amb `--sync-legacy` i revisio de les dues inscripcions.
- [ ] SQL final de `descomptes_grup` validat i incorporat al snapshot de grup abans d'activacio real.
- [ ] Circuits de grup executats amb PHP/MySQL de test: `preflight-redsys-group.php`, `preview-redsys-group.php`, `process-redsys-group.php`, `preflight-manual-group.php`, `preview-manual-group.php`, `process-manual-group.php`, dades legacy de preproduccio i prova de privacitat `VISIBLE_ALUMNE = 0`.
- [ ] SQL final de `regal`, `FACT_REL`, `ORIGEN`, `DESTI`, `CODI` i relacio amb inscripcio posterior validat abans d'activar orquestradors Redsys/manuals de regal.
- [ ] `preflight-redsys-gift.php`, `preview-redsys-gift.php DS_ORDER (--gift-id=ID|--gift-code=CODI)` i `process-redsys-gift.php DS_ORDER (--gift-id=ID|--gift-code=CODI)` executats amb PHP/MySQL de test, dades legacy de preproduccio, callback duplicat i prova de bescanvi sense segona factura.
- [ ] Dades fiscals completes de l'entitat USOC confirmades abans d'activar cap factura `USOC_ENTITAT` real.
- [ ] `preflight-redsys-usoc.php` executat en preproduccio amb `ok=true`.
- [ ] `preview-redsys-usoc.php DS_ORDER --usoc-amount=AMOUNT` executat en preproduccio i payload `USOC_ALUMNE` revisat abans de processar.
- [ ] `process-redsys-usoc.php DS_ORDER --usoc-amount=AMOUNT` executat amb PHP/MySQL de test, notificacio Redsys validada, reintent idempotent i `entity_invoice_pending` revisat.
- [ ] `preflight-usoc-entity.php` executat en preproduccio amb `ok=true`.
- [ ] `preview-usoc-entity.php --payload-file=payload.json` executat en preproduccio i payload `USOC_ENTITAT` revisat amb dades fiscals completes.
- [ ] `process-usoc-entity.php --payload-file=payload.json` executat amb PHP/MySQL de test, factura pendent de cobrament, reintent idempotent i absencia de `payment_transaction` verificada.
- [ ] Payload USOC executat amb PHP/MySQL de test, dades legacy de preproduccio, factura alumne Redsys validada, factura entitat pendent/cobrada segons cas i prova de privacitat.
- [ ] SQL final de `promocions`, `descomptes.TIPUS` 11-99 i punt de creacio del snapshot fiscal de promocio validats abans d'activar casos reals.
- [ ] Curs normal amb codi promocional i promocio temporal executat amb PHP/MySQL de test, `preview-redsys-course.php DS_ORDER --discount-file=discount.json`, `process-redsys-course.php DS_ORDER --discount-file=discount.json`, `preview-manual-course.php IDPAG AMOUNT MOVEMENT_DATE --discount-file=discount.json`, `process-manual-course.php IDPAG AMOUNT MOVEMENT_DATE --discount-file=discount.json`, callback duplicat/reintent manual i verificacio de camps `DESC_*` immutables.
- [ ] Serveis `issueInvoice()` i `registerPayment()` verificats amb PHP/MySQL de test.
- [ ] Legacy sync final, preflight i bateria go/no-go executats amb PHP/MySQL de test i evidencia real.

## Proves, preproduccio i posada en produccio

- [x] Criteris `GO`, `GO AMB LIMITACIONS` i `NO-GO` documentats.
- [x] Bateria bloquejant de proves amb IDs i evidencies minimes definida.
- [x] Fitxa d'evidencia i criteri de captures finals definits.
- [x] Prova minima de backup/restauracio definida.
- [x] Classificacio d'incidencies i efecte en go/no-go definits.
- [x] Checklist final d'activacio productiva definit.
- [x] Plantilla d'execucio de prova definida.
- [x] Plantilla de resum de campanya go/no-go definida.
- [x] Plantilla d'acta de restauracio definida.
- [x] Plantilla d'acta go/no-go definida.
- [x] Plantilla de versio candidata definida.
- [ ] Entorn de preproduccio o mode test separat implementat.
- [ ] Bateria go/no-go executada amb evidencia real.
- [ ] Captures finals i logs reals incorporats a l'expedient de versio.
- [ ] Backup i restauracio executats i documentats amb acta real.

## Xats especialitzats

- [x] Xat 3 / `Rectificativa vs anul·lacio AEAT vs subsanacio` revisat com a primer subbloc especialitzat.
- [x] Xat 5 / `Consulta - Modifica alumne` revisat com a subbloc especialitzat.
- [x] Xat 5 / `Passar pagaments` i analisi TPV revisats com a subbloc especialitzat.
- [x] Xat 5 / `Generar factura abans de pagar` revisat com a subbloc especialitzat.
- [x] Xat 5 / `Consulta - Edita - Anula factura` revisat com a subbloc especialitzat.
- [x] Xat 5 / Intranet alumne, empresa/responsable i acces `VERI*FACTU` revisats com a subbloc especialitzat.

## Blocs especialitzats de canals i casos

- [x] `Redsys curs normal` revisat com a bloc especialitzat.
- [x] `Packs` revisat com a bloc especialitzat.
- [x] `Grups` revisat com a bloc especialitzat.
- [x] `Regals` revisat com a bloc especialitzat.
- [x] `USOC` revisat com a bloc especialitzat.
- [x] `Codis promocionals` revisat com a bloc especialitzat.
- [x] `Transferencia validada a intranet` revisat com a bloc especialitzat.

## Fluxos fiscals especials

- [x] `Compensacio/saldo` tancat com a flux fiscal separat.
- [x] Circuit tecnic de `Compensacio/saldo` preparat amb `credit_balance`, preview/process CLI i aplicacio idempotent com a `COMPENSATION`.
- [x] `Pagaments fraccionats` tancats amb criteri d'idempotencia i assignacio.
- [x] Circuit tecnic de `Pagaments fraccionats` manuals preparat amb preview/process CLI i fraccions idempotents com a `registerPayment()`.
- [x] `Rectificatives de negoci` tancades documentalment amb serie `R`, motiu i mode.
- [x] Circuit tecnic de `Rectificatives` manuals preparat amb preview/process CLI, `factura_rectificacio` i factura serie `R`.
- [x] `Rectificativa`, `RegistroAnulacion`, subsanacio, baixa i `REFUND` separats documentalment.
- [x] Model i servei intern de `RegistroAnulacion` implementats amb registre immutable, encadenament i cua AEAT idempotent.
- [ ] Transport i resposta AEAT real de `RegistroAnulacion` implementats i provats.
- [x] Flux intern de subsanacio implementat amb `Subsanacion`, `RechazoPrevio`, `SinRegistroPrevio` i mateix identificador.
- [ ] Proves XML/XSD/AEAT de: alta normal, alta rebutjada, acceptada amb errors, subsanacio, anul·lacio i nova alta posterior.
- [x] `Devolucions` tancades com a `REFUND` + rectificativa quan pertoqui.
- [x] `Baixes` tancades com a event administratiu amb decisio posterior retorn/saldo/no retorn.
- [x] `Canvis de curs` tancats amb historic, diferencia, retorn/saldo i despeses/descomptes documentats.
- [x] `Factura manual` tancada com a flux `issueInvoice()` des d'intranet autoritzada.
- [x] Circuit tecnic de `Factura manual` preparat amb preview/process CLI, usuari intern obligatori, idempotencia manual i pagament inicial opcional via `issueInvoice(payment)`.
- [x] `Migracio de factures historiques` tancada com a historic `NO_VERIFACTU` sense registre retroactiu.
- [x] Circuit tecnic de `Migracio de factures historiques` preparat amb preview/process CLI, relacio `HISTORIC_LINK`, document historic opcional i bloqueig de registre fiscal/cua AEAT retroactiva.
- [x] `Morositat/reclamacio` tancada com a reclamacio sense rectificativa automatica ni factura nova.
- [x] Circuit tecnic de `Morositat/reclamacio` preparat amb preview/process/preflight CLI i assignacio `CLAIM_PAYMENT` contra factura existent.
- [x] Passada estatica final dels fluxos fiscals especials feta el 2026-06-12: `git diff --check`, trailing whitespace i comprovacions negatives de crides indegudes en scripts critics.
- [ ] Runner PHP complet dels fluxos fiscals especials executat amb `php sif/tests/run-tests.php` i BD SIF/legacy de test disponible.

## Criteri per marcar una area com a tancada

Una area es pot marcar com a revisada quan:

- el xat pont ha buscat informacio del tema al xat antic;
- s'han comparat els resultats amb els documents existents;
- les diferencies importants s'han incorporat o justificat;
- `registre-decisions.md` recull les decisions noves;
- `estat-projecte.md` diu que l'area esta revisada.

<<<<<<< HEAD
## Checkpoint Redsys asincron 2026-06-20
=======
## Circuit Redsys asincron 2026-06-20
>>>>>>> feature/redsys-async-queue

- [x] Mapping `DS_ORDER -> redsys_payment_intent` implementat i provat.
- [x] `redsys_callback_queue` durable creada amb FKs i index de disponibilitat.
- [x] Callback autoritzat encola una vegada; denegat no crea job.
- [x] Worker amb reclamacio unica, resultat persistent, retry, incidencia, maxim cinc intents i lock caducat.
- [x] Dispatcher cobreix `CURS`, `PACK`, `GRUP`, `REGAL` i `USOC_ALUMNE` des de `SNAPSHOT_JSON`.
- [x] Camps signats i duplicats contradictoris endurits; callback sense `IDPAG` extern.
<<<<<<< HEAD
- [x] Suite MySQL 8.0/PHP 8.4 executada: `276 passed, 0 failed`.
- [x] Targeta 9/9: scripts worker/preflight, prova asincrona integral, dos workers, go/no-go i evidencia documental.
- [x] Circuit Redsys asincron sense bloquejos propis al go/no-go (`redsys_async_circuit_present`, intencio i cua en `true`).
- [ ] BD legacy de preproduccio configurada i connectada per obtenir un `GO` global.
- [ ] Activacio en preproduccio i produccio autoritzada.

## Document signat i certificat digital - seguiment 2026-09

- [ ] Confirmar qui demana el document signat i amb quina finalitat abans de convertir l'esborrany en document definitiu.
- [x] Decidit separar un acord intern bilateral signable ara de la declaració responsable reglamentària de la futura versió verificable del SIF.
- [ ] Evitar signar `0.1-BORRADOR` com a certificacio de compliment d'una versio incompleta o no verificable.
- [x] Confirmat amb fonts oficials que la declaracio responsable no exigeix obligatoriament firma electronica.
- [x] Seguiment programat per al 2026-09-22 a les 09:00, hora de Madrid, per revisar el certificat digital.
- [ ] Comprovar si Associacio PrisMa ja disposa de certificat qualificat adequat per AEAT, el titular, el tipus i la vigencia.
- [ ] Comprovar disponibilitat segura del certificat i de la clau privada per a la integracio maquina a maquina.
- [ ] Si no existeix o no es adequat, decidir i iniciar sol·licitud de certificat de representant de persona juridica o formalitzar l'apoderament/col·laboracio social corresponent.
- [x] Distingit el certificat client AEAT del certificat TLS/SSL public de `pay.prisma.cat` i de la signatura de la declaracio responsable.
- [x] Definit que el certificat client i la clau privada han de ser utilitzables pel backend/worker de remissio, sense quedar al repositori ni al webroot.
- [ ] Confirmar si el certificat existent es exportable amb clau privada en `PKCS#12` o si s'utilitzara un magatzem/HSM/key vault.
- [ ] Confirmar amb Comvive compatibilitat amb certificat client TLS, PHP OpenSSL, SOAP/HTTP, WSDL AEAT i connexions de sortida.
- [ ] Definir ubicacio fora del webroot, permisos de l'usuari worker i mecanisme separat per a la contrasenya/secret.
- [ ] Definir copia de seguretat xifrada, custodia, renovacio, rotacio i revocacio.
- [ ] Implementar configuracio de certificat i client AEAT al codi SIF; actualment no detectats.
- [ ] Provar l'autenticacio des del mateix entorn del worker contra el servei AEAT corresponent i conservar evidencia.
- [x] Confirmat amb FAQ AEAT que, en desenvolupament propi per a us propi, Associacio PrisMa es qui certifica el SIF com a productora interna.
- [x] Confirmat que VERI*FACTU no exigeix una declaracio responsable bilateral separada entre Meriem i Associacio PrisMa.
- [x] Preparat l'acord intern no reglamentari de designació i autonomia de la responsable tècnica en Markdown i DOCX signable, amb l'annex 1 de vistiplau tècnic integrat.
- [x] Corregit l'acord perquè Meriem pugui decidir i executar l'activació, suspensió o substitució de versions i l'inici, suspensió o represa de la remissió AEAT sense autorització específica addicional per actuació.
- [x] Documentades com a compartides entre Meriem Abjil Bajja, Adam Carmona i Pablo Martori Delupi les decisions fiscals, jurídiques i laborals del projecte.
- [x] Reservades a la intervenció i conformitat expressa de Meriem totes les decisions o actuacions que afectin el SIF i els circuits de cobrament o pagament gestionats pels sistemes de l'entitat.
- [x] Prohibit que una altra persona de l'entitat, membre de l'equip, col·laborador o proveïdor decideixi, ordeni o executi unilateralment actuacions sobre el SIF o els pagaments sense Meriem.
- [x] Configurades les garanties d'atribució de responsabilitats sense presentar-les com a límits del rol tècnic, funcional o operatiu.
- [x] Reconegut que Meriem ja disposa dels accessos administratius necessaris i establert que s'han de mantenir personals, traçables i suficients.
- [x] Reformulada la previsió laboral com a garantia de condicions i mitjans de treball, sense limitar l'autonomia ni les facultats de la responsable tècnica.
- [x] Incorporats el dret a registrar desacords i riscos, la revocació escrita de facultats i l'absència d'obligació de disponibilitat permanent o d'aportar certificat, equips, comptes o diners personals.
- [x] Separada dins del mateix acord la signatura inicial de designació de l'emissió posterior del vistiplau tècnic de versió.
- [ ] Revisió final de direcció o assessoria de l'acord intern i signatura d'Adam Carmona i Meriem Abjil Bajja.
- [ ] Completar i signar l'annex 1 només quan existeixi una versió concreta instal·lada, provada, identificada i sustentada per evidències.
- [ ] Completar, verificar i aprovar la declaració responsable reglamentària de la versió `1.0.0` abans de presentar-la com a definitiva.

## Diagrames i casos d'us - 2026-09-14

- [x] Diagrama de classes del nucli `InvoiceService` / `PaymentService` contrastat amb constructors i metodes reals.
- [x] Diagrames de classes dels orquestradors manuals, moviments economics i circuit asincron Redsys.
- [x] Diagrames de sequencia d'emissio idempotent, cobrament posterior, callback/worker Redsys i rectificacio/devolucio.
- [x] Seqüencies de panell/incidencies i remissio AEAT marcades explicitament com a disseny pendent.
- [x] Actors interns, externs i automatics consolidats en un diagrama general de casos d'us.
- [x] Matriu de cobertura i fitxes dels casos d'us principals amb estat `[BASE]`, `[ASYNC]`, `[PARCIAL]` o `[DISSENY]`.
- [x] Index documental actualitzat amb els tres documents nous.
- [x] Confirmat que no ha calgut consultar el JSONL de `xat-original`.
- [ ] Revisar els diagrames quan `feature/redsys-async-queue` es fusioni al checkout principal.
- [ ] Actualitzar UC-07, UC-08, UC-09 i UC-10 quan existeixin el panell, l'autoritzacio comuna, el worker AEAT i la gestio executable de versions.

## Auditoria de completitud dels diagrames - 2026-09-15

- [x] 69 de 69 classes del SIF base presents al cataleg de classes.
- [x] 7 de 7 classes exclusives de `feature/redsys-async-queue` presents i separades de la base.
- [x] 10 classes principals del llegat PrisMa representades.
- [x] 56 de 56 scripts base inventariats: 14 preflight, 20 preview, 20 process i 2 d'infraestructura.
- [x] 2 scripts addicionals de la cua Redsys inventariats.
- [x] 3 endpoints publics relacionats amb classes i sequencies.
- [x] 16 taules del model combinat representades, inclosa la taula de cua que només existeix a la branca asincrona.
- [x] Cicles d'estat de factura, cobrament, remissio AEAT i Redsys documentats.
- [x] Casos d'us UC-01 a UC-60 inventariats amb cobertura funcional, pantalles i permisos.
- [x] Productes i variants coberts: curs, taller, jornada, pack, grup, regal, USOC, pagador empresa i descomptes.
- [x] Operacions especials cobertes: factura previa, transferencia, terminis, reclamacio, credit, compensacio, rectificacio, devolucio, migracio, canvi de curs i baixa.
- [x] Operacio coberta: preflight, preview, process, go/no-go, incidencies, documents, sincronitzacio llegat i conciliacio TPV.
- [x] Matriu de tracabilitat creada amb distincio entre implementat, branca, parcial i disseny pendent.
- [x] 67 de 67 blocs Mermaid renderitzats sense errors amb Mermaid CLI 11.12.0.
- [x] Index documental actualitzat amb els documents 31 a 36.
- [x] 112 classes `*Test`, 234 metodes `test*` i 117 fitxers PHP de prova de la base incorporats al mapa; 118/276/123 a la branca asincrona.
- [x] 25 de 25 fitxers PHP copiats a `codi-drive` agrupats i enumerats.
- [x] Superficie d'`Intranet.php` mesurada: 495 funcions, 292 publiques, 117 privades i 86 sense visibilitat explicita.
- [x] 192 de 192 pantalles/apartats agrupats en 12 families disjuntes i mantinguts amb traca al cataleg individual.
- [x] Corregit l'inventari d'endpoints: `factures/issue`, `payments/register` i `redsys/callback`; incidencies continua sent disseny de panell.
- [x] Relacionades les 13.277 targetes petites reconciliades amb casos, codi, pantalles, proves i evidencies sense tractar cada targeta com a cas d'us.
- [x] Confirmat que aquesta ampliacio no ha requerit consultar `xat-original`.
- [ ] Implementar i incorporar als diagrames executables el client/worker AEAT, certificat, anul·lacio i subsanacio.
- [ ] Completar panell, autoritzacio/auditoria, generacio i lliurament segur de documents i conciliador TPV.
- [ ] Integrar definitivament ecommerce/intranet, canvi de curs, baixa i bescanvi de regal amb el SIF.
- [ ] Revisar la matriu quan `feature/redsys-async-queue` es fusioni a la branca principal.

## Reconciliació de `codi-drive` i centralització del pagament - 2026-09-15

- [x] Registrat el significat de les set carpetes copiades.
- [x] Inventariats 6.848 fitxers i 1.920 PHP: 25 candidats i 1.895 actuals/històrics.
- [x] Comparats els 25 candidats: 14 idèntics, 8 diferents i 3 sense homòleg directe.
- [x] Confirmada l'absència de crides als tres endpoints SIF dins els candidats.
- [x] Mesurades les dues versions d'`Intranet.php`: 510 funcions a l'actual i 495 a la candidata.
- [x] Incorporades les classes de producte, inscripció, descompte, regal i pagament de `web-actual`.
- [x] Incorporats `IntranetAlumne`, `IntranetTutor`, els seus punts d'entrada i la separació del flux de proveïdors.
- [x] Documentades les dades llegades de `inscripcions`, `factures`, `cobraments`, honoraris i relacions amb el SIF.
- [x] Fixada la frontera: intranet/web com a canals i pagament fiscal central a `pay.prisma.cat`.
- [x] Creat `37-auditoria-comparativa-codi-drive.md` i actualitzats els documents 31-36 i els índexs.
- [x] UC-01 a UC-68 inventariats.
- [x] 82 de 82 blocs Mermaid dels documents 31-34 i 36-37 renderitzats sense errors amb Mermaid CLI 11.12.0.
- [x] Confirmat que no s'ha llegit el JSONL de `xat-original`, ni modificat PHP, ni fet commit o push.
- [ ] Confirmar que cada carpeta `*-actual` coincideix amb el desplegament actiu i congelar una data de tall.
- [ ] Identificar i catalogar els punts d'entrada actius, separant còpies datades i fitxers de prova.
- [ ] Reconciliar `intranet-nova-canvis-verifactu/Intranet.php` amb `intranet-actual/Intranet.php`.
- [ ] Implementar adaptadors autenticats d'intranet, ecommerce i intranet alumne cap a `pay.prisma.cat`.
- [ ] Substituir les escriptures directes a `factures`/`inscripcions` per `InvoiceService`, `PaymentService` i sync posterior.
- [ ] Integrar la creació d'intencions i la cua asíncrona Redsys al canal web real.
- [ ] Externalitzar, rotar i provar claus Redsys, claus de xifrat i credencials abans de qualsevol commit o desplegament.
- [ ] Executar proves de canal i preproducció per curs, taller, jornada, pack, grup, regal, USOC, empresa i descomptes.
- [ ] Decidir separadament si les factures rebudes de tutors necessiten una integració comptable fora del SIF de vendes.

## 2026-09-15 - Planificació MVP desembre

- [x] Preparat calendari, pressupost inicial, tasques, dependències i acceptació a `pla-mvp-2026-12-31.md`.
- [ ] Confirmar abast de productes i dies laborals concrets.
- [ ] Reestimar el 27/09 amb entorn executable i versió integrada.
- [ ] Superar les portes de sortida del pla i conservar evidències abans d'activar.

## Assistent de fitxes funcionals - 2026-09-15

- [x] Confirmat que la implementació pertany a `projecte-verifactu-pont` i no a `New project 2`.
- [x] Confirmat que l'assistent és transversal i que Xat 3 conserva l'autoritat funcional i fiscal.
- [x] Creat el contracte JSON d'entrada i la fixture pilot UC-26 `Canvi de curs`.
- [x] Definides les 21 seccions exactes i el format atòmic de les afirmacions.
- [x] Implementat el manifest amb fragments, línies, context, estat Git i SHA-256.
- [x] Implementades les validacions dels cinc estats, fonts autoritzades, conflictes, pendents bloquejants i preparació per programar.
- [x] Bloquejades les rutes absolutes, externes, sensibles o no permeses i la sobreescriptura de sortides.
- [x] Garantit el mode `PREVIEW` sense escriptura automàtica a documentació canònica o Trello.
- [x] Registrada la decisió sobre despeses de gestió del canvi de curs.
- [x] Afegides proves unitàries del preparador i el validador.
- [x] Executar les 13 proves específiques de l'assistent amb PHP 8.4.22: `13 passed, 0 failed`.
- [x] Generar el manifest pilot: 23 fonts existents, 21 d'autoritzades i 23 amb fragments, incloses la migració i les proves d'auditoria funcional.
- [ ] Recuperar la BD de test i resoldre els errors previs per tornar a obtenir una suite global verda; resultat actual `167 passed, 85 failed`.
- [x] Generar la primera fitxa completa `PREVIEW` d'UC-26: 82 afirmacions i conflicte explícit entre el nom funcional antic i l'esquema físic actual.
- [ ] Revisar amb Xat 3 les decisions fiscals pendents d'UC-26 abans d'incorporar cap contingut al catàleg canònic.

## Transformació funcional i registral - 2026-09-15

- [x] Revisat l'abast més enllà de pagament, factura i Redsys.
- [x] Contrastats inventari, fluxos, pantalles, correus, estat final, incidències, panell, seguretat i diccionari de camps.
- [x] Inventariades les 192 pantalles i detectada la limitació de les descripcions genèriques de la matriu visual.
- [x] Revisats els mètodes llegats que editen dades de pagament/factura, canvi de curs, baixa i anul·lació.
- [x] Confirmada l'absència de registres/workflows SIF dins les dues carpetes candidates.
- [x] Separats el pla transaccional i el pla funcional/registral sense presentar-los com a sistemes independents.
- [x] Creada la matriu mestra de 27 àrees a `38-matriu-transformacio-funcional-verifactu.md`.
- [x] Afegits serveis de disseny per classificar canvis, dades mestres, events, correccions fiscals, AEAT, documents, outbox, incidències, auditoria, versions, exports, reconciliació i continuïtat.
- [x] Afegides seqüències de dades mestres, canvi/baixa, decisor fiscal, documents/comunicacions/accessos i incidències.
- [x] Afegits UC-69 a UC-86 i verificat el catàleg UC-01 a UC-86 sense buits.
- [x] Afegit model conceptual dels registres i ampliacions necessàries de les taules base.
- [x] Actualitzades traçabilitat, arquitectura de components, auditoria comparativa i índex documental.
- [x] Validats 100 de 100 blocs Mermaid amb Mermaid CLI 11.12.0.
- [x] Confirmat que no s'ha carregat el JSONL complet, modificat PHP ni fet commit/push.
- [ ] Reconciliar les còpies candidates amb les versions actuals i congelar punts d'entrada actius.
- [ ] Implementar gateway comú amb autenticació, autorització, CSRF, idempotència, concurrència i auditoria.
- [ ] Retirar edició directa de factures emeses i updates econòmics alternatius al SIF.
- [ ] Implementar events de dades mestres, canvi de curs, baixa, ajusts i reclamacions.
- [ ] Implementar classificador i circuits de rectificativa, complementària, anul·lació i subsanació.
- [ ] Completar camps normatius, worker AEAT, intents, retries, respostes i dead-letter.
- [ ] Implementar jobs/custòdia de PDF-QR-XML, outbox, intents d'entrega i registre d'accés.
- [ ] Completar workflow d'incidències, reconciliació, versions/declaració, exports i accés auditor.
- [ ] Executar backup/restauració, preproducció i proves/evidències per cada pantalla crítica abans de `GO`.

### Auditoria universal de pagaments

- [x] Fixada la regla que qualsevol acció externa o decisió funcional sobre un pagament deixa traça, independentment de l'entorn.
- [x] Incloses consultes, exportacions, mutacions, reutilitzacions, denegacions, errors, callbacks, workers, CLI, migració, conciliació i sync llegada.
- [x] Definit `payment_action_event` append-only amb actor/procés, rol, entorn, canal, acció, resultat, motiu, request/correlació, hashes i timestamps.
- [x] Definit `fail closed`: sense auditoria no hi ha acció ni retorn de dades.
- [x] Definida atomicitat entre mutació correcta i event terminal.
- [x] Tipificats `ACTION`, `RESULT`, `SOURCE_ENVIRONMENT`, `SOURCE_CHANNEL` i `ACTOR_TYPE` al diccionari.
- [x] Afegits el diagrama de classes 16.4, la seqüència 45 i UC-86.
- [x] Validats els diagrames afectats i recompte global de 100 blocs Mermaid.
- [x] Crear la migració SQL de `payment_action_event`, índexs i permisos append-only.
- [x] Preparar `PaymentActionGateway`, `PaymentActionEventWriter` i repositori append-only inicial amb validació de valors controlats.
- [ ] Implementar `PaymentActionAuditService` i monitor de correlacions incompletes.
- [ ] Integrar el gateway amb `PaymentService`, consultes, conciliació, Redsys, CLI, migració i sincronització llegada.
- [ ] Impedir escriptures directes a `payment_transaction`, `payment_allocation` i `payment_action_event` fora dels serveis autoritzats.
- [x] Afegides proves unitàries del primer tall: fallada del ledger abans de la mutació, intent previ, event terminal, reutilització idempotent i rollback quan falla l'auditoria terminal.
- [ ] Afegir proves de cobertura de cada acció/resultat i de caiguda del ledger en tots els canals abans de considerar el flux preparat per producció.

## 2026-09-15 - Reconciliació de l'abast complet per planificar

- [x] Marcat el primer pla com a superat i retirada l'estimació sense base suficient.
- [x] Inventariades fonts locals de 7 taulers, 81 casos/variants i 192 pantalles/apartats.
- [x] Creat pla mestre de 36 paquets amb infraestructura, fiscalitat, dades, operació, variants, regressió i expedient.
- [ ] Reconciliar estat actual de cada paquet amb codi integrat i proves executables.
- [ ] Classificar individualment cada pantalla/cas com a modificar, conservar, retirar o ajornar amb motiu.
- [ ] Dimensionar hores restants baix/probable/alt i confirmar capacitat/data sense reduccions implícites d'abast.

## 2026-09-15 - Estimació inicial i calendari de 75 h/setmana

- [x] Estimats els 36 paquets amb tres escenaris i justificació; separat l'abast limitat proposat.
- [x] Incorporada disponibilitat de 75 h brutes/setmana i calculades les deu combinacions de tres dies entre setmana.
- [x] Creat calendari individual de 720 h, amb càrrega setmanal i portes de control.
- [x] Corregida la conclusió anterior de necessitat de suport per al llançament limitat.
- [ ] Confirmar l'abast de llançament; cap exclusió de variants està acceptada.
- [ ] Contrastar estimacions amb execució real i reestimar el 27/09.

## 2026-09-15 - Cobertura dels registres al pla

- [x] Contrastat el contracte explícit de traça universal amb document 38, UC-86 i diccionari.
- [x] Incorporat VT-37 i matriu de responsabilitats de persistència cap als paquets del pla.
- [x] Marcades les estimacions i el marge anteriors com a incomplets respecte d'aquest contracte.
- [ ] Reconciliar hores de VT-37 i ampliacions registrals sense duplicar auditoria/API/proves ja pressupostades.
- [ ] Verificar requisit → taula/registre → servei → canal → hores → prova per tota la documentació acordada.

## Planificació reconciliada R2 — 16/09/2026
- Pla vigent: [Pla reconciliat R2](pla-reconciliat-r2.md).
- Cobertura planificada: 118 casos/variants, 29 accions de pagament, 24 grups de registres, 38 paquets.
- Estimació: 944 h mínim proposat (reduccions no aprovades); 1.332 h probables abast ampli. La traça i el classificador comuns tenen pressupost explícit, sense duplicar 28 h traslladades.
- Capacitat des del 16/09: 75 h brutes/setmana; 843,75–866,25 h netes fins al 31/12 amb reserva del 25%. El mínim en solitari apunta al 9–11/01/2027.
- [x] Correspondències documentals i sumes comprovades; model i matrius guardats.
- [ ] Implementació, proves de producte i acceptació de producció pendents. Cap reducció funcional ni contractació de suport aprovada per aquest registre.

## Pantalles i procediments interns - 2026-09-16

- [x] `07-pantalles-intranet.md` actualitzat amb fluxos finals, panells d'accio, avisos, bloquejos i acces extern per a pantalles internes.
- [x] `10-procediments-intranet-ecommerce.md` actualitzat amb procediments interns finals, sortides esperades i matrius d'accio.
- [x] `16-estat-final-pantalles.md` actualitzat amb components obligatoris de pantalles finals i comportament d'indicadors/avisos.
- [x] `21-seguretat-permisos-accessos.md` actualitzat amb regles transversals de bloqueig i avis servidor.
- [x] `22-manual-operatiu-intern.md` actualitzat amb comprovacions practiques abans de confirmar accions internes.
- [x] Fitxers de control actualitzats amb l'estat i la decisio del tall documental.
- [ ] Implementar pantalles/endpoints reals amb aquestes regles.
- [ ] Preparar captures finals de `Passar pagaments`, `Generar factura abans de pagar`, `Consulta - Edita - Anula factura`, intranet alumne, empresa/responsable i apartat `VERI*FACTU`.
- [ ] Executar proves de servidor per bloquejos, permisos, idempotencia, PDF/QR immutable i visibilitat externa.

## Fitxes funcionals i persistència registral - 2026-09-16

- [x] Reconciliades 145/145 targetes obertes de `Fitxes mare`; 0 sense mapa i classificació META explícita quan no correspon un UC independent.
- [x] Afegits UC-87 a UC-105 i verificat el catàleg UC-01..UC-105 sense buits.
- [x] Generades 118 fitxes funcionals separades: 105 casos numèrics i 13 variants.
- [x] Verificats mecànicament els 21 apartats de cada fitxa, l'absència de placeholders i la correspondència entre catàleg i fitxers físics.
- [x] Conservats manifest, rutes i hash SHA-256 de les fonts de cada fitxa.
- [x] Creat l'informe de reconciliació `39-auditoria-fitxes-funcionals.md` amb les 145 correspondències.
- [x] Creada la migració additiva amb les 21 taules de control funcional, fiscal i registral acordades.
- [x] Materialitzats `payment_action_event`, índexs i plantilla de permisos append-only.
- [x] Afegits repositoris append-only inicials per accions de pagament i events operatius.
- [x] Afegides proves d'esquema/permisos i de les invariants de resultats de pagament.
- [x] Afegit primer tall de `PaymentActionGateway` amb proves unitàries de fail-closed, intent previ, event terminal, reutilització idempotent i rollback si falla l'auditoria terminal.
- [x] Renderitzats correctament 14/14 blocs Mermaid del document 34, inclòs el nou ER físic.
- [x] Confirmat que no s'ha carregat el JSONL antic, no s'ha modificat PHP llegat i no s'ha fet commit/push.
- [ ] Revisar i aprovar les decisions `NEEDS_DECISION` indicades a les fitxes; tenir 21 apartats no converteix una decisió pendent en validada.
- [ ] Integrar `PaymentActionGateway` i els repositoris amb `PaymentService`, consultes, Redsys, workers, CLI, migracions, conciliació i sincronització llegada, sense generar falsos `FAILED` en reutilització idempotent o concurrència.
- [ ] Implementar serveis i polítiques d'escriptura per la resta de les 21 taules, inclòs el monitor de correlacions incompletes.
- [ ] Aplicar la migració i els rols en una BD MySQL de prova, revocar privilegis heretats incompatibles i provar restriccions/índexs.
- [ ] Executar lint i proves PHP; no s'han pogut executar en aquesta sessió perquè l'executable PHP no està disponible al `PATH` actual.
- [ ] Completar adaptadors de tots els canals, worker AEAT, documents/notificacions segures, conciliació, preproducció i expedient d'acceptació abans del `GO`.

## Correcció de completitud fitxes-codi-BD - 2026-09-16

- [x] Marcat com a superat el criteri que equiparava 21 apartats amb una fitxa
  funcional completa.
- [x] Auditades les 118 fitxes anteriors: repetició genèrica, estat pendent,
  absència de font Trello dins la fitxa i absència de camps fiscals concrets.
- [x] Reconciliats tres exports locals amb 145 + 10 + 30 = 185 `Fitxes mare`,
  SHA-256 verificat i 0 títols sense mapar.
- [x] Afegits UC-106..UC-112 amb evidència directa de `web-actual`.
- [x] Generades i validades 125 fitxes: 112 numèriques, 13 variants i 21
  apartats; totes marcades com a esborrany estructurat.
- [x] Afegit a totes les fitxes el límit del mapatge Trello; afegides dades
  fiscals mínimes a 55 casos i entrada/regla/flux/prova específics als set casos
  nous.
- [x] Creat `40-auditoria-buits-fitxes-codi-bd.md` i actualitzats documents 05,
  31, 32, 33, 34, 35, 38, 39, diccionari i índexs.
- [x] Afegida la migració 000004 amb 5 ampliacions de taula i 4 taules noves
  d'operació comercial, parts, validació de descompte i enllaços.
- [x] Afegida protecció de migracions per nom/SHA-256 a
  `sif_schema_migration`.
- [x] Afegides proves estructurals de la migració 000004.
- [x] Renderitzats correctament els 6 diagrames nous: 1 de classes, 3 de
  seqüència i 2 de dades/estats.
- [x] Confirmat que no s'ha carregat el JSONL antic, no s'ha modificat PHP
  llegat i no s'ha fet commit/push.
- [ ] Incorporar claim a claim les descripcions i checklists de les 185 targetes
  a les fitxes corresponents; 185/185 és inventari, no tancament funcional.
- [ ] Confirmar quins endpoints/classes/còpies datades de la web i intranets són
  realment actius a producció.
- [ ] Tancar reserva/aforament/llista d'espera, definició de duplicat,
  consentiment de tastet, fiscalitat del curs subvencionat, receptor del
  descompte d'amics i naturalesa/caducitat del dret de docent novell.
- [ ] Reconciliar la planificació que encara pressuposa 118 casos/variants i
  reestimar UC-106..UC-112 i la nova persistència.
- [ ] Implementar repositoris/serveis/adaptadors per `commercial_operation`,
  parts, descomptes i enllaços; retirar les escriptures llegades equivalents.
- [ ] Aplicar 000004 en MySQL de preproducció, executar backfill, convertir camps
  obligatoris a restriccions fortes i provar fallada parcial/recuperació.
- [ ] Executar lint i proves PHP/MySQL; l'host actual no disposa dels executables
  necessaris al `PATH`.
- [ ] Mantenir estat `[NO-GO]` fins a integrar canals, permisos, AEAT,
  documents, notificacions, conciliació i proves/evidències de preproducció.

## Segona auditoria de superfície executable i cicles - 2026-09-16

- [x] Inventariats els volums de PHP/AJAX de les set carpetes sense carregar el
  JSONL antic ni modificar les aplicacions llegades.
- [x] Contrastades les escriptures principals de web/intranet i mètodes
  d'importació, edició, aforament, descomptes, certificats, Moodle, pagament i
  canvi de dades personals.
- [x] Afegits UC-113..UC-124 amb entrada, regla, flux, prova, decisió bloquejant
  i evidència específica de codi.
- [x] Generades i validades 137 fitxes: 124 numèriques, 13 variants, 21 apartats
  i estat honest `STRUCTURED_DRAFT_NEEDS_CASE_REVIEW`.
- [x] Creada la matriu 41 de superfícies executables amb classificació de
  cobertura, persistència i retirada/integració.
- [x] Afegida la migració 000005 amb 12 taules i 16 claus foranes per cicles de
  línies, places, evidències, drets, importacions, canvis, entrega i acadèmic.
- [x] Actualitzats model BD, diccionari, transformació, auditoria, traçabilitat,
  índexs i controls sense modificar els diagrames abans d'estabilitzar el model.
- [x] Reconciliades novament 185/185 `Fitxes mare` amb 0 títols sense mapar.
- [x] Executat `git diff --check` sense errors.
- [ ] Confirmar amb versió/ruta/crons quins endpoints i còpies són actius a
  producció i marcar cadascuna com a migrar, conservar, retirar o adjacent.
- [ ] Incorporar claim a claim les 185 descripcions/checklists i validar les 137
  fitxes amb negoci, assessoria fiscal i protecció de dades.
- [ ] Tancar les decisions bloquejants de capacitat, retenció d'evidències,
  drets/promocions/regals, grups, packs, factura electrònica i estat acadèmic.
- [ ] Implementar repositoris, serveis, permisos, adaptadors i pantalles de
  000005; impedir doble escriptura i retirar URLs/writers llegats.
- [ ] Aplicar 000001..000005 en MySQL de preproducció, provar claus, índexs,
  concurrència, fallada parcial, backup/restauració i backfill.
- [ ] Executar `OperationLifecycleSchemaTest` i la suite PHP quan l'host disposi
  de PHP/MySQL; cap validació estàtica substitueix aquesta prova.
- [ ] Reconciliar de nou hores i calendari: qualsevol pla basat en 118 o 125
  fitxes queda superat per aquesta ampliació.
- [ ] Mantenir `[NO-GO]` fins que cada superfície activa tingui cas, servei,
  persistència, prova i evidència de retirada del llegat.

## Tercera auditoria de controls transversals - 2026-09-16

- [x] Revisats de manera focalitzada els endpoints de mailing/confirmació, les
  discrepàncies DNI/correu/usuaris, el canvi massiu d'estat d'edició, els
  writers a `poblacions_validar` i les comprovacions Prisma/Moodle.
- [x] Confirmat que proforma, morositat, duplicat `IDPAG` i entitat/responsable
  ja tenien casos explícits i no s'han duplicat amb identificadors nous.
- [x] Afegits UC-125..UC-129 amb entrada, regla, flux, prova, decisió bloquejant
  i fonts específiques.
- [x] Generades i validades 142 fitxes: 129 numèriques, 13 variants i 21
  apartats; 0 errors als hashes dels manifests.
- [x] Afegida la migració 000006 amb 8 taules i 8 claus foranes; comprovades 8
  taules úniques i 0 referències a taules inexistents dins les migracions.
- [x] Afegida `CrossSystemControlSchemaTest` i actualitzats documents 05, 24,
  33, 35, 38, 39, 40, 41, índexs i controls.
- [x] Confirmat 0 canvis a `codi-drive`, cap lectura del JSONL antic i cap
  commit/push.
- [ ] Decidir finalitats, canals, versions de text, doble confirmació, caducitat
  i retirada del consentiment, i migrar l'estat vigent sense donar-lo per
  consentit per defecte.
- [ ] Definir identificador canònic i política de vinculació/separació entre
  web, intranet, Moodle antic/nou i llegat, amb revisió de falsos positius.
- [ ] Aprovar la matriu d'impacte d'activació/ajornament/tancament/cancel·lació
  d'una edició per reserva, pagament, factura, grup, pack i regal.
- [ ] Definir font de normalització i responsable de la cua
  `poblacions_validar`, inclosa l'exclusió de factures/snapshots emesos.
- [ ] Definir autoritat per camp i accions automàtiques/manuals de la
  reconciliació Prisma/Moodle.
- [ ] Actualitzar diagrames de classes, seqüència i dades quan aquest model
  transversal sigui validat funcionalment.
- [ ] Implementar serveis, permisos i adaptadors de 000006, migrar dades i
  demostrar que no existeixen efectes laterals sobre pagaments/factures.
- [ ] Aplicar 000001..000006 i executar `CrossSystemControlSchemaTest` i la
  suite completa en PHP/MySQL de preproducció.

## Publicació del checkpoint confirmat - 2026-09-16

- [x] Autorització explícita rebuda per comprovar, commitejar i publicar.
- [x] Publicat el commit funcional `efdbeef` a
  `origin/checkpoint/sif-fase-0-4`.
- [x] Verificat que `HEAD` i la referència remota coincideixen.
- [x] Confirmat que la publicació no inclou `codi-drive`, `xat-original`,
  secrets detectables ni canvis de PHP llegat.
- [ ] Executar proves PHP/MySQL i validació de preproducció abans de retirar
  l'estat `[NO-GO]`.

## Proves i captures de pantalles internes - 2026-09-16

- [x] `20-pla-proves-validacio-sif.md` ampliat amb proves transversals de pantalles, avisos i bloquejos.
- [x] `23-annex-captures-pantalla.md` ampliat amb fitxa fiscal de captura, captures mínimes i criteri de privacitat.
- [x] Fitxers de control actualitzats amb el tall de proves/captures.
- [ ] Executar `SIF-PANT-PAY-001`, `SIF-PANT-PAY-002`, `SIF-PANT-FAC-001`, `SIF-PANT-FACT-001`, `SIF-PANT-FACT-002`, `SIF-VIS-002`, `SIF-AVI-001` i `SIF-AVI-002`.
- [ ] Fer captures reals de les pantalles crítiques quan existeixin en entorn implementat.
- [ ] Vincular cada captura a ID de prova, versió SIF, rol, entorn i evidència conservada.

## Evidencies auditables i incidencies de prova - 2026-09-16

- [x] Nomenclatura estable de captures i evidencies definida.
- [x] Index d'evidencies definit amb prova, versio, entorn, resultat, ubicacio i integritat.
- [x] Criteri de captura valida definit.
- [x] Evidencia minima definida per prova funcional, integracio, concurrencia, permisos, backup/restauracio i go/no-go.
- [x] Incidencies vinculades a `ID_PROVA`, `ID_EVIDENCIA` i `CLOSURE_CRITERIA`.
- [x] Criteri de tancament d'incidencia definit amb reproduccio, correccio i reexecucio.
- [x] Panell SIF ampliat amb vista de versions, evidencies i go/no-go.
- [ ] Crear l'index real d'evidencies de la primera campanya de preproduccio.
- [ ] Executar les proves, conservar els artefactes i verificar-ne integritat, privacitat i accessibilitat.
- [ ] Demostrar el tancament complet d'almenys una incidencia de prova abans de l'acta final go/no-go.

## Revisio normativa i documentacio signable - 2026-09-16

- [x] Revisades fonts oficials AEAT/BOE actuals sobre terminis, certificacio/declaracio responsable, modalitats `VERI*FACTU`, registre d'alta, signatura i certificat/apoderament.
- [x] Actualitzat `documentacio-sif-aeat.md` amb revisio 2026-09-16, dates 2027 i recordatori que el calendari intern no fa signable una versio.
- [x] Reforcada `declaracio-responsable-sif-prisma.md` com a borrador candidat `1.0.0`, amb control previ i matriu interna de productor/titular, obligat usuari, responsable tecnica, signant i certificat/apoderament.
- [x] Afegit a `19-registre-versions-i-canvis-sif.md` el control bloquejant de signatura `1.0.0`.
- [x] Reforçat `21-seguretat-permisos-accessos.md` amb metadades permeses del certificat i controls addicionals del rol auditor/AEAT nomes lectura.
- [x] Ampliat `24-diccionari-camps-i-valors.md` amb camps de fonts oficials, termini legal, signant, vistiplau tecnic, prova de certificat i auditoria.
- [x] Actualitzat `documentacio/README.md` amb el control afegit per a la declaracio responsable.
- [ ] Confirmar amb gestoria o criteri intern formal si PrisMa presenta Impost sobre Societats, no esta en SII, no esta en territori foral i quin termini legal exacte aplica.
- [ ] Confirmar signant formal, NIF, carrec i facultats suficients abans de retirar el segell de borrador.
- [ ] Provar certificat/apoderament des del servidor o worker SIF real i conservar evidencia no secreta.
- [ ] Completar mapa camp normatiu -> taula/camp intern -> XML/PDF/QR -> prova per registre d'alta/anulacio.
- [ ] Crear/provar rol auditor/AEAT nomes lectura, sense secrets ni escriptura, i amb caducitat/exportacions auditades.
- [ ] Mantenir `1.0.0` com a no signable fins que paquet desplegat, BD, proves, certificat, auditoria, declaracio i validacio fiscal coincideixin.

## Annexos de governanca i vistiplau tecnic - 2026-09-16

- [x] Harmonitzat l'acord intern amb termini aplicable, signant formal,
  certificat/apoderament provat des de l'entorn real i criteris de `1.0.0`.
- [x] Ampliat el vistiplau tecnic amb fonts oficials, mapa de camps, rol
  auditor/AEAT nomes lectura i documentacio accessible dins el SIF.
- [x] Separades l'activacio tecnica dins facultats delegades i la subscripcio
  formal de la declaracio responsable per la representacio de l'entitat.
- [x] Indexats l'acord intern i el vistiplau tecnic a `documentacio/README.md`.
- [ ] Regenerar els `.docx` de l'acord i el vistiplau, verificar-los visualment
  i confirmar que coincideixen amb les versions Markdown vigents.
- [ ] Emplenar les evidencies VT dels dos annexos i emetre el resultat formal
  nomes quan les comprovacions bloquejants siguin satisfactories.
- [ ] Obtenir la recepcio i constancia de direccio sense substituir ni ampliar
  indegudament el vistiplau tecnic o les facultats delegades.

## Auditoria d'executabilitat i entorn local - 2026-09-16

- [x] Confirmat que les 142 fitxes estan en
  `STRUCTURED_DRAFT_NEEDS_CASE_REVIEW` i `NOT_COMPLETE`.
- [x] Inventariades 60 taules de migració; 44 no tenen referència al PHP
  d'execució inspeccionat.
- [x] Confirmats només tres endpoints públics i absència d'integració de
  `PaymentActionGateway` als fluxos d'emissió i pagament.
- [x] Confirmada absència de client/worker AEAT, XML/XSD, signatura, QR,
  anul·lació/subsanació i capa executable d'autenticació/autorització.
- [x] Confirmat que el go/no-go comprova 10/60 taules i que
  `TestDatabase::fresh()` només aplica 000001.
- [x] Confirmats 119 fitxers de prova i 268 mètodes no executables en aquest
  host per manca de PHP i MySQL.
- [ ] Instal·lar PHP 8.4 CLI amb `openssl` i `pdo_mysql`; activar `mbstring` com
  a extensió recomanada i afegir PHP al `PATH`.
- [ ] Instal·lar/configurar MySQL 8 de prova amb usuari no productiu, contrasenya
  no versionada, `sif_test` i una BD legacy de prova amb dades anonimitzades.
- [ ] Executar lint PHP, runner complet, 000001..000006, concurrència,
  rollback, backup/restauració i `go-no-go-preproduction.php`.
- [ ] Ampliar `TestDatabase` i el go/no-go perquè apliquin/comprovin tot
  l'esquema vigent, no només el nucli 000001 i 10 taules.
- [ ] Implementar les 44 taules sense servei, integrar auditoria universal,
  seguretat, AEAT i adaptadors de tots els entorns abans de retirar `[NO-GO]`.

## Tracabilitat de pantalles internes - 2026-09-16

- [x] Actualitzada la matriu de cobertura amb l'estat `ESPECIFICACIO OPERATIVA` per a les pantalles internes prioritaries.
- [x] Enllacades les pantalles amb procediment, accio SIF o control, permisos, avisos, prova i captura minima.
- [x] Actualitzat l'informe d'auditoria per separar cobertura documental de implementacio i validacio real.
- [x] Incorporada a l'inventari la cadena de traçabilitat obligatoria per tancar cada pantalla.
- [ ] Implementar les pantalles i els controls de servidor descrits.
- [ ] Executar `SIF-PANT-PAY-*`, `SIF-PANT-FAC-001`, `SIF-PANT-FACT-*`, `SIF-VIS-002` i `SIF-AVI-*`.
- [ ] Incorporar les captures finals i les evidencies d'auditoria de cada prova executada.

## Registres fiscals d'anul·lacio i subsanacio - 2026-09-16

- [x] Implementats `FiscalRecordService`, `FiscalRecordRepository` i el constructor de payloads fiscals.
- [x] `ANULACIO` crea un registre immutable encadenat, una entrada idempotent a `fiscal_queue` i marca la factura `CANCELLED`.
- [x] `SUBSANACIO` conserva UUID i numero visible i admet `SUBSANACION`, `RECHAZO_PREVIO` i `SIN_REGISTRO_PREVIO`.
- [x] Bloquejades factures historiques `NO_VERIFACTU`, dobles anul·lacions diferents i subsanacions sobre factures cancel·lades.
- [x] Afegits preview sense escriptura, processador de preproduccio i proves d'integracio/estructura.
- [ ] Executar lint i suite PHP/MySQL; PHP continua absent del `PATH` d'aquest host.
- [ ] Implementar XML/XSD, signatura/certificat, transport/worker AEAT, retries, resposta i dead-letter abans de considerar complet el circuit AEAT.

## Lifecycle de cua AEAT - 2026-09-16

- [x] Definit contracte injectat `AeatTransport` sense dependència d'un client concret.
- [x] Implementada reclamacio transaccional de cues `PENDING` i `RETRY` amb increment d'intents.
- [x] Implementada persistencia de `ACCEPTED`, `ACCEPTED_WITH_ERRORS` i `REJECTED` a cua, registre fiscal i factura.
- [x] Implementats `RETRY` i `DEAD_LETTER` per excepcions de transport, amb error persistent.
- [x] Afegides proves d'acceptacio, XML/resposta persistent, cua buida, tres intents i dead-letter.
- [ ] Executar les proves PHP/MySQL quan PHP 8.4 i MySQL 8 estiguin disponibles.
- [ ] Implementar i validar el constructor XML contra XSD oficial per alta, anul·lacio i subsanacio.
- [ ] Implementar adaptador SOAP/HTTPS real amb certificat o apoderament, secrets protegits i endpoints de preproduccio.
- [x] Afegida recuperacio explicita de locks `PROCESSING` caducats amb llindar minim de 60 segons.
- [x] Afegit processament per lots limitat a 1..100 entrades i aturada al primer error per evitar reintents calents.
- [x] Implementat backoff exponencial persistent amb `NEXT_RETRY_AT`, espera base i maxim configurables.
- [ ] Afegir planificador extern del worker, metriques, alertes i operacio controlada.

## Preflight i observabilitat AEAT - 2026-09-16

- [x] Afegida configuracio AEAT per variables d'entorn sense versionar secrets.
- [x] Implementat preflight bloquejant de SOAP, DOM, OpenSSL, HTTPS, WSDL, XSD, certificat, emissor i identitat SIF.
- [x] Implementades metriques de cua per estat, registres exigibles, locks caducats i entrada accionable mes antiga.
- [x] Implementades alertes de `DEAD_LETTER`, volum exigible i lock orfe.
- [x] Afegit script CLI de nomes lectura que no instancia processador ni transport.
- [x] Afegides proves de configuracio insegura, secrets no exposats, metriques i estructura del preflight.
- [ ] Configurar PHP SOAP/DOM/OpenSSL, XSD oficial local, certificat/apoderament, emissor i identitat SIF en preproduccio.
- [ ] Ampliar i migrar el snapshot immutable amb tots els camps exigits pel registre AEAT, inclosos data d'expedicio i dades del SIF.
- [ ] Implementar constructor XML i adaptador SOAP només quan el snapshot anterior sigui complet i validable contra XSD.
- [ ] Executar preflight, proves XSD i enviaments controlats al portal de proves externes abans d'autoritzar produccio.

## Contracte tecnic de pantalles internes - 2026-09-16

- [x] Creat i indexat el contracte tecnic de pantalles internes.
- [x] Identificats els serveis SIF existents reutilitzables i els endpoints interns pendents.
- [x] Definit el patro `preview -> confirm` amb idempotencia i revalidacio d'estat.
- [x] Definits context d'actor, permisos al servidor, resposta comuna i codis d'avis.
- [x] Definits els contractes de `Passar pagaments`, factura abans de pagar i rectificacio/anulacio.
- [x] Definides la consulta alumne, la cobertura empresa/responsable i el resum VERI*FACTU.
- [ ] Implementar autenticacio comuna, autoritzacio, CSRF i auditoria HTTP al repositori real.
- [ ] Implementar els endpoints interns de consulta, preview i confirmacio.
- [ ] Integrar les pantalles de la intranet real i executar les proves/captures bloquejants.

## Manifest i portes de l'expedient go/no-go - 2026-09-16

- [x] Manifest mestre de l'expedient definit amb blocs, estats i control d'integritat.
- [x] Regla de congelacio i revisions successives del manifest definida.
- [x] Portes `G1` a `G7` definides amb condicio, responsable i evidencia.
- [x] Abast admissible de `GO AMB LIMITACIONS` restringit a canals o funcionalitats no activats.
- [x] Control de completitud i caducitat de campanya definit.
- [x] Fitxa de versio candidata ampliada amb manifest, portes, incidencies, restauracio i decisio.
- [ ] Crear i congelar el manifest real de la candidata `1.0.0`.
- [ ] Executar i superar `G1` a `G7` en preproduccio amb evidencies del mateix paquet.
- [ ] Emetre acta final i registrar les aprovacions aplicables abans d'activar produccio.

## Fitxes UI de pantalles internes - 2026-09-16

- [x] Definida l'estructura visual comuna i els estats de carrega, buit, error, bloqueig i confirmacio.
- [x] Preparada la fitxa UI de `Passar pagaments`.
- [x] Preparada la fitxa UI de `Generar factura abans de pagar`.
- [x] Preparada la fitxa UI de `Consulta - Edita - Anula factura`.
- [x] Preparades les vistes d'intranet alumne i empresa/responsable.
- [x] Preparats l'indicador, el resum i les severitats d'avisos VERI*FACTU.
- [x] Definits criteris d'accessibilitat, mobil i captures obligatories.
- [ ] Implementar les fitxes al repositori real amb `available_actions` del servidor.
- [ ] Verificar-les en escriptori i mobil i incorporar captures anonimitzades.

## Backlog d'implementacio de pantalles internes - 2026-09-16

- [x] Localitzades les rutes, metodes, consultes i AJAX llegats de les tres pantalles internes.
- [x] Documentats els riscos de `GET`, sessio serialitzada, doble escriptura i mutacio fiscal directa.
- [x] Definida l'estrategia progressiva pantalla -> adaptador -> SIF -> sync llegada.
- [x] Desglossades les tasques `UI-INT`, `UI-FACT`, `UI-PAY`, `UI-PRE`, `UI-RECT`, `UI-VIS` i `UI-AVI`.
- [x] Definit l'ordre de lliurament i la definicio de fet per tasca.
- [ ] Implementar i provar la base segura `UI-INT-001..004` al repositori real.
- [ ] Implementar primer la consulta de factura sense mutacions.
- [ ] Activar progressivament pagaments, factura previa, rectificacio, portals i avisos amb proves bloquejants.
- [ ] Retirar els camins llegats nomes despres de validar equivalencia, rollback i evidencies.

## Proves i evidencies del backlog UI - 2026-09-17

- [x] Afegides proves bloquejants de sessio/rol, CSRF/metode, preview token i indisponibilitat SIF.
- [x] Relacionades les tasques `UI-*` amb les proves de pantalla corresponents.
- [x] Definits sis paquets d'evidencia amb captures i comprovacions tecniques.
- [x] Establert que la base segura ha de passar abans d'activar qualsevol confirmacio.
- [x] Establert que permisos i idempotencia requereixen evidencia de servidor.
- [ ] Executar `SIF-PANT-SEC-001..004` al repositori i entorn reals.
- [ ] Generar, indexar i calcular hashes dels paquets `EVID-UI-*`.
- [ ] Revisar cada paquet amb responsable diferent de qui executa quan sigui possible.
=======
- [x] Worker CLI, preflight, prova asincrona, dues connexions, go/no-go i evidencia documental.
- [x] Suite MySQL 8.0/PHP 8.4 executada: `276 passed, 0 failed`.
- [ ] BD legacy de preproduccio configurada i connectada per obtenir un `GO` global.
- [ ] Activacio productiva autoritzada.
>>>>>>> feature/redsys-async-queue
