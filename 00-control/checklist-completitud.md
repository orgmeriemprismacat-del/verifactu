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
- [ ] Entorn de preproduccio o mode test separat implementat.
- [ ] Bateria go/no-go executada amb evidencia real.
- [ ] Captures finals i logs reals incorporats a l'expedient de versio.
- [ ] Backup i restauracio executats i documentats amb acta real.

## Xats especialitzats

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
- [x] `Rectificatives` tancades amb serie `R`, motiu i mode.
- [x] Circuit tecnic de `Rectificatives` manuals preparat amb preview/process CLI, `factura_rectificacio` i factura serie `R`.
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
