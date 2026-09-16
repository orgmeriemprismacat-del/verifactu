# Estat del projecte VERI*FACTU

Ultima actualitzacio: 2026-09-16

## Objectiu

Adaptar el sistema de facturacio de PrisMa a VERI*FACTU mitjancant un SIF centralitzat, amb documentacio tecnica, fiscal, operativa i de posada en produccio suficient per continuar el projecte sense dependre del xat antic.

## Fonts disponibles

- Documentacio principal: `documentacio/`
- Index documental: `documentacio/README.md`
- Document mare: `documentacio/00-index-i-pla/documentacio-verifactu.md`
- Xat antic complet: `xat-original/rollout-2026-05-13T22-15-39-019e22fb-0e44-71f3-81ef-e0b9d0dcd2a7.jsonl`

## Estat actual

- La documentacio principal existeix i esta separada per apartats.
- El xat antic esta copiat com a arxiu de consulta.
- Encara hi pot haver informacio donada al xat antic que no estigui reflectida als documents.
- El xat pont ja ha fet un inventari inicial del xat antic per cerques tematiques, sense carregar el JSONL complet.
- Bloc 1 revisat: context actual de PrisMa i canals reals. S'han incorporat matisos de stack tecnic, TPV virtuals, receptors, estat actual de factura/PDF, camps operatius i volum/concurrencia.
- Bloc 2 revisat: fluxos de facturacio i casos especials. S'han incorporat matisos sobre `IDPAG`, intents Redsys, transferencies, compensacions, factura abans de cobrament, proformes, canvis de curs, baixes, devolucions i rectificatives.
- Bloc 3 revisat: pagaments, Redsys, callbacks i conciliacio. S'han incorporat matisos sobre `realitzaPagamentAutomatic.php`, parametres reals del callback, `Ds_Order`/`NUM_COMANDA`, canals TPV, `Passar pagaments`, migracio a `pay.prisma.cat`, conciliacio i visibilitat de factura/PDF.
- Bloc 4 revisat: base de dades, hash chain, concurrencia i idempotencia. S'han incorporat matisos sobre BD fiscal parcial, `errors_verifactu`, `reg_pagament`, `factura_log`, `session_log`, migracio de `UUID varchar(12)` i `double`, `InnoDB`, `IDEMPOTENCY_KEY`, `FISCAL_ORDER`, hash chain global, `fiscal_queue.PAYLOAD_JSON` i permisos MySQL contra updates de factures emeses.
- Bloc 5 revisat: pantalles, permisos i operacio interna. S'han incorporat matisos sobre rutes d'intranet, `ROLS_VISUALITZAR`, `ROLS_EDITAR`, `ROLS_ENVIAR_MSG`, `consultaRolsEdiicio`, `consultaRolsUsuari`, `tePermisEdicio`, permisos d'Isa, accions critiques, reclamacions, morositat i apartat `VERI*FACTU` de la intranet.
- Bloc 6 revisat: compliment AEAT i declaracio responsable. S'han incorporat criteris sobre abast normatiu, terminis AEAT verificats, model 036, versio `0.1-BORRADOR` no signable, versio `1.0.0` signable, certificat digital de l'entitat, productor/titular, rol de Meriem, punts fiscals sensibles i cicle de versions.
- Bloc 7 revisat: correus, plantilles, PDF/QR i notificacions. S'han incorporat criteris sobre grups reals de correus, `Template`, correus directes en PHP, correu tecnic de `realitzaPagamentAutomatic.php`, URLs de pagament a `pay.prisma.cat`, enllac segur, PDF/QR en cua, `factura_documents` i nomenclatura `incidencia SIF`/`notificacio`/`avis`/`indicador`.
- Bloc 8 revisat: proves, produccio, auditoria documental i governanca. S'han incorporat criteris sobre entorn de preproduccio separat, go/no-go, regressions critiques, evidencies de prova, backups/restauracio, no rollback de factures emeses, versio `0.3-BORRADOR`, activacio de `1.0.0` i planificacio interna realista.
- La revisio transversal del xat antic per blocs queda completada. La prioritat immediata passa a ser obrir xats especialitzats per convertir la documentacio parcial en procediments executables, SQL, pantalles, proves i evidencies.
- Xat especialitzat d'intranet iniciat: subbloc `Consulta - Modifica alumne` revisat. S'ha consolidat que la pantalla es fitxa central d'alumne i inici d'accions, no editor de factures emeses. Queden pendents els cossos reals de metodes, implementacio final, proves i captures finals.
- Xat especialitzat d'intranet continuat: subbloc `Passar pagaments / Analitzar fitxer TPV` revisat. S'ha consolidat el criteri SIF per registrar cobraments, evitar factures duplicades, auditar TPV, separar `efact` de `E_FACT` i exigir idempotencia. Queden pendents cossos reals de cerca/modal info, implementacio final, proves i captures finals.
- Xat especialitzat d'intranet continuat: subbloc `Generar factura abans de pagar` revisat. S'ha consolidat que aquest flux emet factura real pendent de cobrament, amb `EMESA_ABANS_COBRAMENT = 1`, `E_FACT = 0` per defecte, snapshot fiscal del receptor, idempotencia i pagament posterior per `registerPayment()`. Queden pendents implementacio final, proves i captures finals.
- Xat especialitzat d'intranet continuat: subbloc `Intranet alumne, empresa/responsable i acces VERI*FACTU` revisat. S'ha consolidat que l'alumne nomes veu factures propies, que les factures d'empresa/grup nomes son visibles per empresa/responsable autoritzat, i que l'apartat `VERI*FACTU` de la intranet nomes resumeix i enllaça amb el panell SIF.
- Bloc especialitzat de pagaments continuat: `Redsys curs normal` revisat. S'ha consolidat que `realitzaPagamentAutomatic.php` no ha de calcular numero ni inserir a `web.factures`; el callback ha de validar signatura/import, deduplicar `DS_ORDER` i cridar `issueInvoice()` o `registerPayment()` segons si existeix factura real.
- Bloc especialitzat de pagaments continuat: `Packs` revisat. S'ha consolidat que el pack normal agrupa dues inscripcions amb el mateix `IDPAG`, genera una factura per pagament real, una linia per curs i el descompte `PACK` del 25% al segon curs. Queden pendents SQL real de pack/preus, implementacio SIF, proves i captures finals.
- Bloc especialitzat de pagaments continuat: `Grups` revisat. S'ha consolidat que el grup genera una factura per pagament real, una linia per participant, receptor fiscal empresa/escola o responsable particular i DNI intern excepte justificacio. Queden pendents implementacio SIF, SQL final de `descomptes_grup`/`respGrups`, proves i captures finals.
- Bloc especialitzat de pagaments continuat: `Regals` revisat. S'ha consolidat que el regal factura al comprador, usa `SOURCE_TYPE = REGAL`, genera codi de bescanvi i crea/vincula la inscripcio posterior del destinatari sense segona factura. Queden pendents implementacio SIF, SQL final de `regal`/`FACT_REL`, proves i captures finals.
- Bloc especialitzat de pagaments continuat: `USOC` revisat. S'ha consolidat que l'afiliacio USOC es valida manualment (`TIPUS_DESC = 4`, `VALID_DESC`), que l'alumne rep factura per la seva part i USOC rep factura per la diferencia. Ja hi ha preparacio SIF de snapshot/payload, circuit Redsys d'alumne i circuit separat `USOC_ENTITAT` amb billing explicit; queden pendents dades fiscals completes reals d'USOC, execucio en preproduccio, proves/captures i decisio final sobre `anticipi-preu-usoc`.
- Bloc especialitzat de pagaments continuat: `Codis promocionals` revisat. S'ha consolidat que el codi es valida a ecommerce/intranet, que `promocions` conserva codi, DNI, us i vigencia, i que el SIF nomes congela el resultat fiscal dins `factura_linia`. Ja hi ha suport inicial al builder de curs normal per congelar snapshot `discount`; queden pendents SQL final de `promocions`, proves/captures i criteri final de visibilitat del codi al PDF.
- Bloc especialitzat de pagaments continuat: `Transferencia validada a intranet` revisat. S'ha recuperat el cami antic de `efectuarPagamentFacturaGenerada()` i s'ha consolidat que una transferencia sobre factura existent ha d'anar per `registerPayment()` i `payment_allocation`, sense modificar la factura emesa. Queden pendents implementacio SIF, referencia bancaria/BANC final, proves/captures i cossos de cerca/modal info.
- Arquitectura tecnica SIF tancada: `issueInvoice()` queda com l'unic flux que assigna numero fiscal, crea linies, registre fiscal, hash chain, cua AEAT i relacions; `registerPayment()` queda limitat a moviments economics sobre factura existent, sense numero fiscal ni hash chain. Quan factura i cobrament neixen junts, el criteri final es `issueInvoice()` amb bloc `payment` dins la mateixa operacio idempotent.
- Model BD SIF consolidat: `fact_rels` queda com a pont logic amb BD antiga, sense foreign keys entre BD fiscal i BD web/intranet; `payment_transaction`, `payment_allocation`, `factura_linia`, `fiscal_chain_state`, `fiscal_queue` i valors controlats queden alineats entre model, relacions i diccionari.
- Entrada de pagaments al SIF definida: Redsys entra per callback a `pay.prisma.cat` i es deduplica a `redsys_notifications`; transferencies i pagaments manuals entren per `Passar pagaments`; fitxers TPV generen analisi/conciliacio auditada; el moviment economic real viu a `payment_transaction` i l'assignacio a factura viu a `payment_allocation`.
- Bloc especialitzat de proves i posada en produccio revisat: el checklist i el pla de proves ja tenen criteris executables de `GO`, `GO AMB LIMITACIONS` i `NO-GO`, bateria bloquejant amb IDs, fitxa d'evidencia, criteri de captures, backups/restauracio, incidencies i checklist final d'activacio. Queda pendent executar-ho en preproduccio/produccio i conservar evidencies reals.
- Continuacio del bloc de proves i posada en produccio: afegides plantilles operatives per executar proves, resumir campanyes go/no-go, documentar restauracions, registrar l'acta go/no-go i descriure versions candidates. Queda pendent usar aquestes plantilles amb evidencies reals quan hi hagi entorn de preproduccio i codi desplegable.
- Fluxos fiscals especials tancats: compensacio/saldo, pagaments fraccionats, rectificatives, devolucions, baixes, canvis de curs, factura manual i migracio de factures historiques queden definits com a fluxos separats. Cap d'aquests casos es resol modificant imports, dates o factures emeses; passen per `issueInvoice()`, `registerPayment()`, `payment_transaction`, `payment_allocation`, `credit_balance`, rectificatives o migracio `NO_VERIFACTU` segons el cas.
- Bloc normatiu/documental revisat: documentacio SIF AEAT, declaracio responsable, registre de versions, permisos i diccionari han quedat reforcats amb criteri de fonts oficials, versio `1.0.0` signable, certificat digital/apoderament, productor/titular intern, rol auditor nomes lectura i camps fiscals minims del registre d'alta, QR, AEAT, documents i declaracio.
- Pla d'implementacio tecnica del SIF creat: `documentacio/00-index-i-pla/29-pla-implementacio-tecnica-sif.md` converteix l'arquitectura tancada en fases executables, fitxers a crear, proves amb runner PHP propi, migracions SQL, endpoints interns, Redsys, documents, incidencies i preflight.
- Codi del Drive copiat com a referencia local a `codi-drive/`, sense copiar fitxers `parametres-connexio*` amb credencials. La copia inclou callbacks Redsys, pagaments automatics, `apiRedsys.php`, connexions sense parametres i el flux antic `Intranet.php`/`efectuarPagament.php`.
- Fase 0 i Fase 1 iniciades al repo de treball sense Composer: creats `sif/src/autoload.php`, `sif/tests/run-tests.php`, `sif/tests/Support/Assert.php`, `sif/config/sif.php`, bootstrap de tests, helper de BD, migracio SQL del nucli SIF, seed inicial, runner de migracions i test estructural d'esquema. No s'han pogut executar proves perque PHP no esta disponible al PATH d'aquest entorn.
- Fase 2 preparada al repo de treball: creades les classes `ConnectionFactory`, `TransactionRunner`, `UuidGenerator` i `SifException`, amb proves unitàries test-first per UUID, excepcions i transaccions. Les proves no s'han pogut executar per manca de PHP al PATH.
- Fase 3 preparada al repo de treball: creats `InvoicePayloadValidator` i `PaymentPayloadValidator`, amb proves unitàries test-first per payloads vàlids, camps obligatoris, serie de factura, tipus de moviment i assignacions. Les proves no s'han pogut executar per manca de PHP al PATH.
- Task 4 de la Fase 4 preparada al repo de treball: creat `HashCalculator` per calcular el hash fiscal intern de manera estable i canonica, amb `previous_hash`, ordenacio recursiva d'arrays associatius i conservacio de l'ordre de les llistes com `factura_linia`. Les proves unitàries del hash no s'han pogut executar per manca de PHP al PATH.
- Task 5 de la Fase 4 preparada al repo de treball: creats `FiscalSequenceRepository`, `InvoiceRepository`, `InvoiceService`, fixtures, test d'integracio `IssueInvoiceTest`, suport `TestDatabase::fresh()` i runner amb carpeta `Integration`. El primer `issueInvoice()` ja queda codificat per numeracio fiscal, factura, linies, registre fiscal, hash chain, cua AEAT, `fact_rels` i reutilitzacio per `IDEMPOTENCY_KEY`. Les proves no s'han pogut executar per manca de PHP al PATH.
- Task 6 de la Fase 4 preparada al repo de treball: reforçat el test d'idempotencia d'`issueInvoice()` amb comprovacio de `fiscal_sequence`, exposat `IssueInvoiceTest::serviceFor()` per tests d'integracio, afegit helper `makeService()` i creat `ConcurrencySmokeTest` per comprovar numeracio, ordre fiscal i hashes únics en emissions seqüencials. Les proves no s'han pogut executar per manca de PHP al PATH.
- Fase 5 preparada al repo de treball: creats `PaymentStatusCalculator`, `PaymentRepository`, `PaymentService`, `PaymentStatusCalculatorTest` i `RegisterPaymentTest`. `registerPayment()` valida payload, reutilitza per `IDEMPOTENCY_KEY`, crea `payment_transaction` i `payment_allocation`, recalcula `ESTAT_COBRAMENT` i no crea cap nou registre fiscal ni hash chain. Les proves no s'han pogut executar per manca de PHP al PATH.
- Fase 6 preparada al repo de treball: creats `LegacySyncRepository`, `LegacySyncService` i `LegacyRelationsTest`. `fact_rels` conserva `FACTURA_RELACIONADA`, `IDPAG`, `DS_ORDER`, `SOURCE_TYPE` i `SOURCE_ID`; la sincronitzacio cap a `web.inscripcions` queda com a resum explicit post-SIF mitjancant `syncAfterSifSuccess()`, sense integrar-se dins `issueInvoice()` ni `registerPayment()`. Les proves no s'han pogut executar per manca de PHP al PATH.
- Fase 7 preparada al repo de treball: creats `JsonResponse`, `sif/public/api/factures/issue.php`, `sif/public/api/payments/register.php` i `HttpEndpointsTest`. Els endpoints interns llegeixen JSON, construeixen `InvoiceService` o `PaymentService`, retornen JSON i no criden sincronitzacio legacy. Les proves i el servidor local no s'han pogut executar per manca de PHP al PATH.
- Fase 8 preparada al repo de treball: creats `RedsysNotificationRepository`, `RedsysCallbackService`, `sif/public/api/redsys/callback.php` i `RedsysCallbackTest`. El callback Redsys es deduplica per `DS_ORDER`, exigeix validacio de signatura passada com a boolea intern abans de gravar, no confia en cap camp del payload, i en aquesta fase no crida ni `issueInvoice()` ni `registerPayment()`. L'endpoint queda segur per defecte amb `$signatureValid = false` fins a connectar la validacio real de `apiRedsys.php`/PrisMa. Les proves no s'han pogut executar per manca de PHP al PATH.
- Fase 9 preparada al repo de treball: reforçat `IssueInvoiceTest` per comprovar `PAYLOAD_JSON` congelat a `factura_registres` i `fiscal_queue`, creats `DocumentRepository`, `IncidentRepository` i `DocumentsAndIncidentsTest`. Els documents fiscals es registren a `factura_documents` amb hash SHA-256 i estat `CREATED`; les incidencies SIF s'obren a `errors_verifactu` amb estat `OPEN`. La migracio ja incloia `errors_verifactu`, per tant no s'ha modificat SQL. Les proves no s'han pogut executar per manca de PHP al PATH.
- Fase 10 preparada al repo de treball: creats `sif/scripts/preflight-sif.php`, `sif/scripts/go-no-go-preproduction.php`, `PreflightScriptTest` i `GoNoGoPreproductionScriptTest`. El preflight comprova la base SIF; la bateria go/no-go comprova entorn no productiu, runner, migracions, connexio SIF/legacy, clau Redsys, taules fiscals minimes i circuits preparats abans de qualsevol pilot. No s'han pogut executar per manca de PHP al PATH.
- Fase 11 iniciada com a preparacio tecnica, sense activar canals reals: `issueInvoice()` accepta un bloc `payment` opcional i, quan factura i cobrament neixen junts, crea `payment_transaction` i `payment_allocation` dins la mateixa transaccio idempotent de la factura. L'endpoint intern `factures/issue.php` ja construeix les dependencies de pagament. S'ha afegit prova d'integracio per Redsys normal controlat a nivell de servei, incloent reintent idempotent que retorna el `uuid_payment` existent sense duplicar registres. La verificacio executable i l'activacio real en preproduccio queden pendents per manca de PHP al PATH, BD MySQL de test i validacio criptografica Redsys connectada.
- Checkpoint `269d4cb` pujat a `origin/checkpoint/sif-fase-0-4`: inclou Fase 10 i la primera preparacio tecnica de Fase 11.
- Fase 11 Redsys continuada el 2026-06-06: creat `RedsysSignatureValidator`, configurada la clau per entorn `SIF_REDSYS_MERCHANT_KEY` i cablejat `sif/public/api/redsys/callback.php` per validar `Ds_MerchantParameters`/`Ds_Signature` abans de registrar `redsys_notifications`. S'ha afegit prova unitària amb notificació Redsys signada de test, normalitzacio d'import en centims i classificacio `Ds_Response`: `VALIDATED` quan Redsys autoritza (`0..99`) i `ERROR` quan no autoritza. També s'ha preparat `RedsysInvoicePayloadBuilder` per construir `issueInvoice(payment)` de curs normal nomes des d'una notificacio `VALIDATED`, sense consultar encara la BD antiga ni activar endpoint final. El callback continua sense crear factura ni pagament; l'execucio real queda pendent de PHP al PATH, BD MySQL de test i secret Redsys configurat.
- Fase 11 curs normal continuada el 2026-06-06: creat `LegacyCourseInvoicePayloadBuilder` per convertir un snapshot legacy de `inscripcions` + `curs` en payload fiscal base validable per `issueInvoice()`. El builder conserva receptor, NIF, adreca, linia de curs, import actual del pagament, IVA exempt i relacio `INSCRIPCIO`; no usa `inscripcions.PAGAMENT` com a import perquè es pagat acumulat historic. `RedsysInvoicePayloadBuilder` continua sent qui injecta la idempotencia final `REDSYS|CURS|IDPAG:{IDPAG}|ORDER:{DS_ORDER}` i el bloc `payment` nomes si la notificacio esta `VALIDATED`. No s'ha activat lectura real de BD antiga ni crida automatica des del callback.
- Fase 11 curs normal continuada amb lectura legacy preparada: creat `LegacyCourseSnapshotRepository` per carregar `inscripcions` per `IDPAG` i `INSC CURS` `0`/`1`/`M`, carregar `curs` per `ANY`/`MES`/`CURS`, i retornar el snapshot que alimenta `LegacyCourseInvoicePayloadBuilder`. Aquesta lectura es de nomes lectura i encara no queda cablejada al callback real.
- Fase 11 curs normal continuada amb orquestrador preparat: creat `RedsysCourseInvoiceService` per composar notificacio `VALIDATED`, snapshot legacy, payload fiscal Redsys i `issueInvoice(payment)` en una prova d'integracio de servei amb reintent idempotent. El callback real continua sense cridar aquest orquestrador automaticament.
- Fase 11 curs normal continuada amb prova manual de preproduccio preparada: afegida configuracio `legacy_db` per variables `SIF_LEGACY_DB_*`, `ConnectionFactory::makeLegacy()` i script CLI `sif/scripts/process-redsys-course.php` per processar manualment un `DS_ORDER` ja `VALIDATED` en entorn no productiu. El script rebutja `SIF_ENV=production`, no llegeix POST Redsys i no substitueix el callback real.
- Fase 11 curs normal continuada amb preflight especific preparat: creat `sif/scripts/preflight-redsys-course.php` per comprovar entorn no productiu, clau Redsys, connexio SIF, connexio legacy, taules SIF/legacy necessaries i seed fiscal abans d'executar el processador manual. Es de nomes lectura i no emet factures.
- Fase 11 curs normal continuada amb preview preparada: creat `sif/scripts/preview-redsys-course.php` per construir i imprimir el payload `issueInvoice(payment)` d'un `DS_ORDER` ja `VALIDATED` sense crear factura ni pagament. Serveix per revisar receptor, import, linies, relacions i bloc `payment` abans del processament manual.
- Fase 11 curs normal continuada amb sincronitzacio legacy opcional preparada: `RedsysCourseInvoiceService` retorna metadades de relacions per sync posterior i `process-redsys-course.php DS_ORDER --sync-legacy` pot cridar `LegacySyncService::syncAfterSifSuccess()` nomes despres d'un resultat SIF `ok=true`. Sense `--sync-legacy`, el processador manual no escriu a la BD antiga.
- Fase 11 factura abans de cobrament preparada a nivell de prova: creat `InvoiceBeforePaymentFlowTest` per verificar `issueInvoice(emesa_abans_cobrament=1)` seguit de `registerPayment()`. El flux manté un sol registre fiscal i una sola cua AEAT; el pagament posterior nomes crea `payment_transaction` i `payment_allocation` i actualitza `ESTAT_COBRAMENT` a `PAID`.
- Fase 11 factura abans de cobrament continuada amb circuit CLI de preproduccio: creats `InvoiceBeforePaymentPayloadBuilder`, `InvoiceBeforePaymentService` i els scripts `preflight-invoice-before-payment.php`, `preview-invoice-before-payment.php` i `process-invoice-before-payment.php`. El flux emet factura real pendent de cobrament des d'un payload JSON, rebutja bloc `payment` inicial i deixa el cobrament posterior per `registerPayment()`.
- Fase 11 transferencies manuals preparada a nivell de builder: creat `ManualPaymentPayloadBuilder` per convertir una transferencia validada a `Passar pagaments` contra factura SIF existent en payload de `registerPayment()`. La idempotencia usa referencia bancaria quan existeix (`TRANSFERENCIA|REF:{REFERENCIA_BANCARIA}`) i fallback per factura/data/import/banc quan no hi ha referencia. Encara no s'ha activat cap pantalla real ni executat `SIF-PAY-001`.
- Fase 11 transferencies manuals contra factura SIF existent continuada amb circuit CLI de preproduccio: creats `ManualPaymentInvoiceRepository`, `ManualPaymentService` i els scripts `preflight-manual-payment.php`, `preview-manual-payment.php` i `process-manual-payment.php`. El flux localitza factura per `UUID_FACTURA` o `NUM_VISIBLE` i fa `registerPayment()` sense crear factura nova, sense `InvoiceService`, sense Redsys i sense legacy.
- Fase 11 devolucions manuals contra factura SIF existent preparada: creats `ManualRefundPayloadBuilder`, `ManualRefundService` i els scripts `preview-manual-refund.php` i `process-manual-refund.php`. El flux registra un moviment economic `REFUND` via `registerPayment()`, recalcula `ESTAT_COBRAMENT` a `PARTIALLY_REFUNDED`/`REFUNDED` segons imports i no crea factura, registre fiscal, hash chain ni sync legacy.
- Fase 11 transferencies manuals sense factura SIF prèvia preparada per curs normal: creat `ManualCourseInvoicePayloadBuilder` per convertir snapshot legacy d'inscripcio + curs i transferencia validada en payload `issueInvoice(payment)` amb `source_channel = INTRANET`, idempotencia per `IDPAG`/referencia o fallback per data/import/banc, i bloc `payment` inicial amb `provider_ref`, `REFERENCIA_BANCARIA` i `IDPAG`.
- Fase 11 transferencies manuals sense factura SIF prèvia continuada amb orquestrador: creat `ManualCourseInvoiceService` per carregar snapshot legacy per `IDPAG`, construir payload manual de curs i cridar `issueInvoice(payment)`. Retorna metadades `legacy_sync` per a sincronitzacio posterior, pero no escriu a la BD antiga ni substitueix encara la pantalla real de `Passar pagaments`.
- Fase 11 transferencies manuals sense factura SIF prèvia continuada amb preview: creat `sif/scripts/preview-manual-course.php` per construir en dry-run el payload `issueInvoice(payment)` d'un curs manual a partir d'`IDPAG`, import, data, referencia i banc. Es CLI-only, rebutja produccio i no crea factura, pagament ni sync legacy.
- Fase 11 transferencies manuals sense factura SIF prèvia continuada amb processador manual de preproduccio: creat `sif/scripts/process-manual-course.php` per executar `ManualCourseInvoiceService` amb `IDPAG`, import, data, referencia/banc/notes i `--sync-legacy` opcional. Rebutja produccio, no depen de Redsys i nomes sincronitza legacy despres d'un resultat SIF `ok=true`.
- Fase 11 transferencies manuals sense factura SIF prèvia continuada amb preflight especific: creat `sif/scripts/preflight-manual-course.php` per comprovar readiness de curs manual sense exigir Redsys. Es de nomes lectura i revisa entorn no productiu, BD SIF, BD legacy, taules fiscals/economiques minimes, `inscripcions`, `curs` i seed fiscal.
- Fase 11 packs iniciada el 2026-06-07 a nivell de snapshot i payload: creats `LegacyPackSnapshotRepository` i `LegacyPackInvoicePayloadBuilder` per convertir un pack legacy en payload fiscal `issueInvoice()` amb `source_type = PACK`, una linia per curs, descompte `PACK` del 25% a la segona linia quan no hi ha imports fiscals explicits, relacio `PACK`, relacions `INSCRIPCIO` i compatibilitat amb idempotencia Redsys `REDSYS|PACK|IDPAG:{IDPAG}|ORDER:{DS_ORDER}`. Encara no hi ha scripts ni endpoints de pack i l'execucio real queda pendent de PHP/MySQL de test.
- Fase 11 packs continuada amb circuit manual de preproduccio: creat `RedsysPackInvoiceService` i els scripts `preflight-redsys-pack.php`, `preview-redsys-pack.php` i `process-redsys-pack.php`. El flux processa manualment un `DS_ORDER` ja `VALIDATED`, construeix factura `PACK` multi-linia amb cobrament inicial, permet `--sync-legacy` opcional post-SIF i continua sense activar callback automatic ni endpoint de pack.
- Fase 11 packs continuada amb transferencia manual de preproduccio: creats `ManualPackInvoicePayloadBuilder`, `ManualPackInvoiceService` i els scripts `preflight-manual-pack.php`, `preview-manual-pack.php` i `process-manual-pack.php`. El flux processa un pack validat manualment a `Passar pagaments`, exigeix que l'import manual coincideixi amb el total fiscal del pack, crea `issueInvoice(payment)` amb `source_channel = INTRANET` i permet `--sync-legacy` opcional nomes despres d'exit SIF.
- Fase 11 grups iniciada el 2026-06-08 a nivell de snapshot i payload: creats `LegacyGroupSnapshotRepository` i `LegacyGroupInvoicePayloadBuilder` per convertir un grup legacy en payload fiscal `issueInvoice()` amb `source_type = GRUP`, receptor fiscal de `respGrups`, una linia per participant, relacions `GRUP`/`INSCRIPCIO` i `visible_alumne = 0`. El repositori carrega imports presents a `inscripcions` i deixa pendent incorporar SQL final de `descomptes_grup` abans de qualsevol circuit real.
- Fase 11 regals iniciada el 2026-06-08 a nivell de snapshot i payload: creats `LegacyGiftSnapshotRepository` i `LegacyGiftInvoicePayloadBuilder` per convertir un regal legacy en payload fiscal `issueInvoice()` amb `source_type = REGAL`, factura al comprador, una linia fiscal del regal, relacio `REGAL`, `visible_alumne = 0` i compatibilitat Redsys `REDSYS|REGAL|IDPAG:NULL|ORDER:{DS_ORDER}`. No es crea inscripcio del destinatari ni segona factura en aquest tall.
- Fase 11 regals continuada amb circuit Redsys manual de preproduccio: creat `RedsysGiftInvoiceService` i els scripts `preflight-redsys-gift.php`, `preview-redsys-gift.php` i `process-redsys-gift.php`. El flux processa manualment un `DS_ORDER` ja `VALIDATED` i un regal indicat explicitament per `ID` o `CODI`, comprova que l'import Redsys coincideixi amb `regal.IMPORT`, emet `REGAL` amb cobrament inicial i no sincronitza `regal.FACT_REL` ni crea inscripcio del destinatari.
- Fase 11 regals continuada amb circuit manual de `Passar pagaments`: creats `ManualGiftInvoicePayloadBuilder`, `ManualGiftInvoiceService` i els scripts `preflight-manual-gift.php`, `preview-manual-gift.php` i `process-manual-gift.php`. El flux processa un regal indicat per `ID` o `CODI`, exigeix que l'import manual coincideixi amb `regal.IMPORT`, emet `REGAL` amb cobrament inicial `source_channel = INTRANET` i no sincronitza `regal.FACT_REL` ni crea inscripcio del destinatari.
- Fase 11 USOC iniciada el 2026-06-08 a nivell de snapshot i payload: creats `LegacyUsocSnapshotRepository` i `LegacyUsocInvoicePayloadBuilder` per convertir una inscripcio `TIPUS_DESC = 4` i `VALID_DESC = 1` en doble payload fiscal: `USOC_ALUMNE` per la part pagada per alumne via Redsys i `USOC_ENTITAT` per la part assumida per USOC amb receptor fiscal explicit i relacio interna no visible a l'alumne. Les dades fiscals completes de l'entitat USOC i l'execucio real amb PHP/MySQL de test continuen pendents.
- Fase 11 USOC continuada amb circuit Redsys d'alumne de preproduccio: creat `RedsysUsocInvoiceService` i els scripts `preflight-redsys-usoc.php`, `preview-redsys-usoc.php` i `process-redsys-usoc.php`. El flux consumeix un `DS_ORDER` ja `VALIDATED`, exigeix `--usoc-amount=AMOUNT`, emet nomes la factura `USOC_ALUMNE` amb cobrament inicial i retorna `entity_invoice_pending` perquè la factura `USOC_ENTITAT` continuï condicionada a dades fiscals explicites. No sincronitza legacy ni crea la factura de l'entitat en aquest tall. Les proves no s'han pogut executar per manca de PHP al PATH.
- Fase 11 USOC continuada amb circuit d'entitat de preproduccio: creat `UsocEntityInvoiceService` i els scripts `preflight-usoc-entity.php`, `preview-usoc-entity.php` i `process-usoc-entity.php`. El flux exigeix un JSON explicit amb `idpag`, `student_amount`, `amount`, `student_invoice_uuid` i `billing`, emet `USOC_ENTITAT` pendent de cobrament i no registra pagament ni sincronitza legacy. Les proves no s'han pogut executar per manca de PHP al PATH.
- Fase 11 codis promocionals iniciada el 2026-06-08 a nivell de payload de curs normal: `LegacyCourseInvoicePayloadBuilder` accepta un snapshot `discount` ja validat per ecommerce/intranet i congela base, descompte, total i camps `DESC_*` de `factura_linia` (`CODI_PROMO` o `PROMOCIO_TEMPORAL`). El SIF no revalida el codi ni consulta `promocions` en aquest tall.
- Fase 11 codis promocionals continuada el 2026-06-12 amb circuit Redsys de curs normal: `RedsysCourseInvoiceService` pot rebre un snapshot `discount` explicit i els scripts `preview-redsys-course.php`/`process-redsys-course.php` accepten `--discount-file=discount.json` per provar el descompte ja validat abans de Redsys. Queden pendents el SQL final de `promocions`/`descomptes.TIPUS` 11-99, el punt real de creacio del snapshot i l'execucio amb PHP/MySQL de test.
- Fase 11 codis promocionals continuada el 2026-06-12 amb circuit manual de curs normal: `ManualCourseInvoiceService` pot rebre el mateix snapshot `discount` explicit i els scripts `preview-manual-course.php`/`process-manual-course.php` accepten `--discount-file=discount.json` per provar transferencies manuals o pagaments intranet amb descompte ja validat. El SIF continua sense consultar `promocions`/`descomptes` en aquest tall.
- Fase 11 codis promocionals reforçada el 2026-06-12 amb `DiscountSnapshotFileReader`: els scripts Redsys/manuals de curs comparteixen la lectura i validacio basica del `discount.json`, i queda afegida prova unitària del lector. L'execucio real continua pendent de PHP al PATH.
- Fase 11 compensacio/saldo preparada el 2026-06-12 amb circuit CLI: creats `CreditBalancePayloadBuilder`, `CreditBalanceRepository`, `CreditBalanceService` i els scripts `preview-credit-balance.php`, `process-credit-balance.php`, `preview-credit-compensation.php` i `process-credit-compensation.php`. El saldo es crea a `credit_balance` sense factura ni pagament; quan s'aplica a una factura existent genera `COMPENSATION`/`COMPENSACIO`, `payment_allocation` `CREDIT_COMPENSATION`, redueix `IMPORT_DISPONIBLE` de manera idempotent i no emet factura ni sincronitza legacy. Les proves no s'han pogut executar per manca de PHP al PATH.
- Fase 11 pagaments fraccionats manuals preparada el 2026-06-12 amb circuit CLI: creats `ManualInstallmentPaymentPayloadBuilder`, `ManualInstallmentPaymentService` i els scripts `preview-manual-installment.php` i `process-manual-installment.php`. Cada fraccio manual contra factura existent entra per `registerPayment()` amb idempotencia `MANUAL|FRACCIO|ID_INSC:{ID_INSC}|DATA:{DATA}|IMPORT:{IMPORT}|USUARI:{USUARI}`, assignacio `INSTALLMENT_PAYMENT`, recalcul d'`ESTAT_COBRAMENT` i sense factura nova, registre fiscal nou ni sync legacy. Les proves no s'han pogut executar per manca de PHP al PATH.
- Fase 11 rectificatives manuals preparada el 2026-06-12 amb circuit CLI: creats `ManualRectificationPayloadBuilder`, `RectificationRepository`, `ManualRectificationService` i els scripts `preview-manual-rectification.php` i `process-manual-rectification.php`. El flux emet una factura serie `R` amb `issueInvoice()`, insereix `factura_rectificacio`, marca l'original com `RECTIFIED` i no registra pagaments ni sincronitza legacy en aquest tall. Les proves no s'han pogut executar per manca de PHP al PATH.
- Fase 11 factura manual preparada el 2026-06-12 amb circuit CLI: creats `ManualInvoicePayloadBuilder`, `ManualInvoiceService` i els scripts `preview-manual-invoice.php` i `process-manual-invoice.php`. El flux normalitza payloads manuals d'intranet amb `source_type = MANUAL`, exigeix usuari intern, deriva idempotencia per referencia o usuari/data/hash, permet factura pendent o `issueInvoice(payment)` si neix cobrada, i no sincronitza legacy. Les proves no s'han pogut executar per manca de PHP al PATH.
- Fase 11 migracio historica preparada el 2026-06-12 amb circuit CLI: creats `HistoricalInvoicePayloadBuilder`, `HistoricalInvoiceMigrationRepository`, `HistoricalInvoiceMigrationService` i els scripts `preview-historical-invoice-migration.php` i `process-historical-invoice-migration.php`. El flux importa factures historiques com `NO_VERIFACTU`, conserva numero visible, linies, relacio `HISTORIC_LINK` i document antic amb hash si existeix, sense crear registre fiscal, cua AEAT, fiscal sequence ni hash chain. Les proves no s'han pogut executar per manca de PHP al PATH.
- Verificacio final dels fluxos fiscals especials executada el 2026-06-12 a nivell estatic: `git diff --check` no retorna errors de whitespace, la revisio de trailing whitespace sobre fitxers canviats no retorna coincidencies i les comprovacions negatives confirmen que preview/process de factura manual i migracio historica no salten els serveis fiscals previstos. El runner `php sif/tests/run-tests.php` continua bloquejat perque `php` no existeix al PATH ni en ubicacions Windows habituals revisades.
- Revisio documental continuada el 2026-06-14: `04-fluxos-facturacio.md` deixa de constar com a document pendent i `11-inventari-canvis-pendents.md` queda alineat amb els circuits tecnics preparats per factura manual, pagaments fraccionats, devolucions, rectificatives, baixes, canvis de curs, compensacio/saldo i migracio historica `NO_VERIFACTU`. Continua pendent l'execucio amb PHP/MySQL de test i evidencies de preproduccio.
- Fase 11 grups continuada el 2026-06-14 amb circuits de preproduccio: creats `RedsysGroupInvoiceService`, `ManualGroupInvoicePayloadBuilder`, `ManualGroupInvoiceService` i els scripts `preflight-redsys-group.php`, `preview-redsys-group.php`, `process-redsys-group.php`, `preflight-manual-group.php`, `preview-manual-group.php` i `process-manual-group.php`. El flux manté factura `GRUP` amb linies per participant, receptor `respGrups`, relacions no visibles a alumne, cobrament inicial Redsys/manual i `--sync-legacy` opcional post-SIF. L'activacio real continua pendent de PHP/MySQL de test i SQL final de `descomptes_grup`.
- Fase 11 reclamacio/morositat preparada el 2026-06-14: creats `ClaimPaymentPayloadBuilder`, `ClaimPaymentService` i els scripts `preflight-claim-payment.php`, `preview-claim-payment.php` i `process-claim-payment.php`. El flux localitza factura SIF per `UUID_FACTURA` o `NUM_VISIBLE` i registra `registerPayment()` amb assignacio `CLAIM_PAYMENT`, sense factura nova, sense registre fiscal nou, sense hash chain i sense sync legacy. Pendent d'integrar pantalla/URL/correus finals i executar PHP/MySQL de test.
- Fase 10 go/no-go reforçada el 2026-06-14: `go-no-go-preproduction.php` comprova tambe `credit_balance`, `manual_refund`, `redsys_usoc`, `usoc_entity`, `redsys_group`, `manual_group` i `claim_payment`, a més de les taules legacy de grup `respGrups`. Continua sent comprovacio de nomes lectura i no emet factures ni registra pagaments.
- Xat 3 iniciat el 2026-06-18 amb el bloc `rectificativa vs anul·lacio AEAT vs subsanacio`. S'ha corregit el criteri anterior que enviava genericament la pantalla antiga d'anul·lacio cap a rectificativa: ara hi ha decisor fiscal entre cancel·lacio operativa, factura rectificativa, `RegistroAnulacion`, subsanacio, nova alta o incidencia. La documentacio ja separa baixa, `REFUND`, rectificativa, anul·lacio de registre i subsanacio. Queden pendents el model/servei de `RegistroAnulacion`, subsanacions, camps XML AEAT, hash exacte, cua/respostes i proves XSD/AEAT.
- Bloc d'entrada de pagaments Redsys continuat el 2026-06-19: creada la migracio `redsys_payment_intent` per vincular `DS_ORDER` generat al servidor amb origen, `IDPAG`, import esperat i snapshot previ al TPV. El contracte documental ja prohibeix resoldre `IDPAG` des de query string i diferencia duplicat coherent, callback contradictori i col·lisio concurrent. Queden pendents el repositori/servei PHP, la connexio automatica del callback amb els orquestradors i les proves executables amb PHP/MySQL.
- Disseny asincron Redsys tancat el 2026-06-19 a `13-cua-asincrona-callbacks-redsys.md`: el callback persistira notificacio i job en una transaccio curta, i un worker separat processara curs, pack, grup, regal o USOC amb bloqueig, reintents i resultat persistent. S'ha triat `redsys_callback_queue` com a taula propia. La sincronitzacio legacy automatica queda fora del primer tall perque l'actual concatenacio a `OBSERVACIONS` no es idempotent.
- Pla d'implementacio de la cua Redsys preparat el 2026-06-19 a `14-pla-implementacio-cua-redsys.md`, dividit en les nou targetes Trello amb cicles RED/GREEN, fitxers exactes, migracions, worker, dispatcher, reintents, duplicats i proves de concurrencia. L'execucio continua bloquejada fins disposar de PHP/OpenSSL/PDO MySQL i BD de test.
- Revisio documental urgent iniciada el 2026-09-14: cal disposar aviat d'un document signat, pero la declaracio responsable reglamentaria no es pot presentar com a certificacio definitiva d'una versio que encara no sigui concreta, instal·lada i verificable. Abans de preparar el document final cal confirmar qui el demana i amb quina finalitat, per decidir entre declaracio responsable del SIF o declaracio provisional d'estat del projecte. La declaracio responsable no exigeix signatura electronica; el certificat qualificat es necessari per autenticar la remissio VERI*FACTU a AEAT. S'ha programat per al 2026-09-22 la revisio de si Associacio PrisMa ja disposa d'un certificat adequat, titular, vigencia, acces i eventual apoderament o sol·licitud.
- Arquitectura de certificat AEAT aclarida el 2026-09-14: el certificat client qualificat i la clau privada han d'estar disponibles per al backend/worker que fa la remissio SOAP/XML des del servidor, pero no han d'estar al webroot ni al repositori. Es pot usar un fitxer `PKCS#12`/`PEM` protegit, magatzem de certificats, gestor de secrets o HSM/key vault segons el hosting. El certificat client AEAT es diferent del certificat TLS public de `pay.prisma.cat` i de la signatura de la declaracio responsable. Encara no hi ha configuracio de certificat al codi SIF.
- Responsabilitat productor/desenvolupament intern aclarida el 2026-09-14: segons la FAQ AEAT vigent, si una empresa desenvolupa el SIF per a us propi, es la mateixa empresa qui l'ha de certificar. En el projecte, Associacio PrisMa continua com a productora/titular interna i Meriem com a responsable tecnica. VERI*FACTU no imposa una declaracio bilateral separada entre la responsable tecnica i l'empresa. Es recomana, com a governanca interna, un acord de designacio tecnica i aprovacio de direccio que no substitueixi la declaracio responsable del SIF ni traslladi automaticament la responsabilitat de productor a la persona treballadora.
- Acord intern corregit i ampliat el 2026-09-15: `documentacio/01-compliment-aeat/acord-intern-responsabilitats-sif-prisma.md` i `acord-intern-responsabilitats-sif-prisma.docx` reconeixen a Meriem Abjil Bajja autonomia delegada per decidir la preparació tècnica, activar, suspendre o substituir versions i iniciar, suspendre o reprendre la remissió sistemàtica a l'AEAT, sense autorització específica addicional per actuació. Qualsevol decisió o actuació que afecti el SIF, i qualsevol actuació sobre els circuits de cobrament o pagament gestionats pels sistemes de l'entitat, ha de passar prèviament per la seva intervenció i conformitat expressa; cap altra persona, membre de l'equip, col·laborador o proveïdor pot decidir-la o executar-la unilateralment. Les decisions fiscals, jurídiques i laborals del projecte es documenten com a compartides amb Adam Carmona i Pablo Martori Delupi, però no es poden adoptar ni executar sense Meriem. L'acord reconeix que Meriem ja disposa dels accessos administratius i estableix que s'han de mantenir personals, traçables i suficients. La previsió laboral regula només condicions i mitjans de treball i no limita les seves facultats. L'annex 1 de vistiplau continua pendent d'emissió fins que hi hagi una versió concreta instal·lada, provada i identificada. El DOCX s'ha revisat visualment en vuit pàgines i no substitueix la declaració responsable reglamentària.

## Decisions base ja assumides

- El SIF sera centralitzat.
- Els canals no han de crear factures fiscals finals pel seu compte.
- El SIF ha de decidir numero fiscal, hash, registre, estat AEAT, PDF i QR.
- `pay.prisma.cat/sif` es la ubicacio funcional prevista per al panell intern del SIF.
- La intranet pot mostrar alertes i accessos, pero no ha de ser la font fiscal principal.

## Inventari inicial del xat antic

Revisio inicial feta el 2026-05-19.

Metode:

- no s'ha carregat el JSONL complet al context;
- s'han filtrat missatges reals d'usuari/agent;
- s'han fet cerques tematiques per detectar blocs amb mes risc de buit documental.

Temes amb mes risc de contenir detalls pendents de contrast:

1. Context real de canals, productes, TPV, ecommerce, intranet, cursos, packs, regals, empreses i vendes manuals.
2. Fluxos de facturacio i excepcions: factura emesa, dades fiscals, rectificatives, devolucions, pagaments parcials, duplicats, canvis de curs i baixes.
3. Pagaments, Redsys, transferencia, callbacks, conciliacio TPV i migracio cap a `pay.prisma.cat`.
4. Base de dades, hash chain, concurrencia, idempotencia, MyISAM/InnoDB, cues, retries i logs fiscals.
5. Compliment AEAT, certificat digital, declaracio responsable, QR/XML/CSV i criteris a validar amb assessor fiscal.
6. Pantalles, permisos, operacio interna, incidencies, exportacions, auditories i rols.
7. Proves, produccio, migracio, checklist, evidencia i governanca.
8. Correus, plantilles, PDF/QR, enllacos segurs i notificacions.

## Ordre de revisio proposat

1. Context actual de PrisMa i canals reals.
2. Fluxos de facturacio i casos especials.
3. Pagaments, Redsys, callbacks i conciliacio.
4. Base de dades, hash chain, concurrencia i idempotencia.
5. Pantalles, permisos i operacio interna.
6. Compliment AEAT i declaracio responsable.
7. Correus, plantilles, PDF/QR i notificacions.
8. Proves, produccio, auditoria documental i governanca.

## Proper pas recomanat

Completar la verificacio executable de Fase 0, Fase 1, Fase 2, Fase 3, Tasks 4/5/6 de Fase 4, Fase 5, Fase 6, Fase 7, Fase 8, Fase 9, Fase 10 i preparacio tecnica de Fase 11 amb PHP disponible:

```text
php sif/tests/run-tests.php
SIF_ENV=test php sif/scripts/run-migrations.php
php sif/scripts/preflight-sif.php
php sif/scripts/preflight-redsys-usoc.php
php sif/scripts/preflight-usoc-entity.php
php sif/scripts/preview-redsys-course.php DS_ORDER --discount-file=discount.json
php sif/scripts/preview-manual-course.php IDPAG AMOUNT MOVEMENT_DATE --discount-file=discount.json
php sif/scripts/preview-redsys-group.php DS_ORDER
php sif/scripts/preview-manual-group.php IDPAG AMOUNT MOVEMENT_DATE
php sif/scripts/preview-claim-payment.php (--uuid-factura=UUID|--num-visible=NUM) AMOUNT MOVEMENT_DATE --claim-reference=REF
php sif/scripts/preview-manual-refund.php (--uuid-factura=UUID|--num-visible=NUM) AMOUNT MOVEMENT_DATE
php sif/scripts/preview-manual-installment.php (--uuid-factura=UUID|--num-visible=NUM) AMOUNT MOVEMENT_DATE --id-insc=ID --user=USER
php sif/scripts/preview-manual-rectification.php (--uuid-factura=UUID|--num-visible=NUM) AMOUNT --reason=REASON --mode=DIFERENCIES|SUBSTITUCIO
php sif/scripts/preview-manual-invoice.php --payload-file=payload.json
php sif/scripts/preview-historical-invoice-migration.php --payload-file=payload.json
php sif/scripts/preview-credit-balance.php AMOUNT --holder-type=STUDENT --holder-name=NAME --source-type=BAIXA
php sif/scripts/preview-credit-compensation.php --uuid-credit=UUID (--uuid-factura=UUID|--num-visible=NUM) AMOUNT MOVEMENT_DATE
```

Motiu: l'estructura, el SQL, la infraestructura comuna, els validators, el hash fiscal intern, `issueInvoice()`, `registerPayment()`, la sincronitzacio legacy controlada, els endpoints interns, la porta Redsys deduplicada amb validador de signatura preparat, els repositoris de documents/incidencies, el preflight tecnic, la bateria go/no-go de preproduccio, el primer `issueInvoice(payment)`, la lectura de snapshot legacy, el payload base de curs normal, l'orquestrador de servei Redsys curs, el script manual de preproduccio Redsys, el preflight especific Redsys curs, la preview de payload Redsys, la sync legacy opcional post-SIF, el flux de factura abans de cobrament, el circuit CLI de factura abans de cobrament, el builder de transferencia manual contra factura existent, el circuit CLI de transferencia manual contra factura existent, el circuit CLI de fraccions manuals, el circuit CLI de rectificatives manuals, el circuit CLI de factura manual, el circuit CLI de migracio historica `NO_VERIFACTU`, el circuit CLI de devolucio manual contra factura existent, el circuit CLI de saldo/compensacio, el builder de transferencia manual de curs sense factura SIF prèvia, l'orquestrador manual de curs, la preview manual de curs, el processador manual de curs, el preflight manual de curs, el payload/snapshot de pack, el circuit manual Redsys pack, el circuit manual de transferencia pack, el payload/snapshot de grup, els circuits Redsys/manuals de grup, el circuit de reclamacio/morositat com a `CLAIM_PAYMENT`, el payload/snapshot de regal, el circuit manual Redsys regal, el circuit manual de transferencia regal, el payload/snapshot USOC de doble factura, el circuit Redsys USOC d'alumne, el circuit `USOC_ENTITAT` amb billing explicit i la congelacio de codis promocionals en `factura_linia` ja estan creats sense dependencia de Composer, pero falta executar la verificacio real amb PHP i BD SIF/legacy de test configurades. Un cop passi aquesta base, el seguent pas tecnic sera activar-ho en preproduccio controlada, mai directament en produccio.

## Com s'ha de tancar cada sessio

Abans d'acabar qualsevol xat, demanar:

```text
Actualitza els fitxers de control del projecte: estat-projecte.md, registre-decisions.md i checklist-completitud.md amb el que hem decidit o completat en aquesta sessio.
```

## 2026-06-20 - Implementacio asincrona Redsys: 9 de 9 completades

- Preparat un worktree aillat `feature/redsys-async-queue` amb PHP 8.4.22 i MySQL 8.0.40 de test.
- Completades les targetes 1-9: `redsys_payment_intent`, `redsys_callback_queue`, callback transaccional, worker, dispatcher dels cinc origens, resultat persistent, reintents/incidencies, duplicats contradictoris i operacio CLI/preflight.
- El callback ja no usa `IDPAG` de query string; valida import/divisa/terminal contra la intencio i conserva camps signats normalitzats.
- Els workers consumeixen `SNAPSHOT_JSON` sense connexio legacy i no executen sincronitzacio legacy automatica.
- Verificacio actual: `276 passed, 0 failed`; worker CLI amb cua buida `ok=true`, preflight Redsys `ok=true` i migracions `000003`/`000004` aplicades sobre MySQL 8.0.40.
- El go/no-go confirma `redsys_async_circuit_present = true` i les dues taules noves; el resultat global continua `NO-GO` exclusivament per manca de configuracio/connexio/taules de la BD legacy de preproduccio.
- No s'ha fet commit ni push.

## 2026-09-14 - Cataleg de diagrames i casos d'us

- Creat `documentacio/04-estat-final/31-diagrames-classes-sif.md` amb el nucli d'emissio/cobrament, orquestradors manuals i circuit asincron Redsys.
- Creat `documentacio/04-estat-final/32-diagrames-sequencia-sif.md` amb les sequencies d'`issueInvoice()`, factura abans de cobrar, callback/worker Redsys, rectificacio/devolucio, incidencies i remissio AEAT prevista.
- Creat `documentacio/04-estat-final/33-casos-us-sif.md` amb actors, diagrama general, matriu de cobertura, fitxes funcionals i autoritzacions transversals.
- Els documents diferencien codi `[BASE]` del checkout `checkpoint/sif-fase-0-4`, codi `[ASYNC]` de `feature/redsys-async-queue`, integracions `[PARCIAL]` i funcionalitat `[DISSENY]` encara pendent.
- No s'ha consultat `xat-original`: el codi, les migracions, les proves i la documentacio actual han estat suficients per aquest inventari.
- No s'ha modificat codi, ni s'ha fet commit o push.

## 2026-09-15 - Auditoria i ampliacio exhaustiva dels diagrames

- Revisada la primera versio del cataleg i confirmat que era una vista introductoria, no una cobertura suficient de tot el projecte.
- Ampliat `31-diagrames-classes-sif.md` fins a cobrir les 69 classes del SIF base, les 7 classes addicionals de `feature/redsys-async-queue` i les 10 classes principals del llegat PrisMa.
- Ampliat `32-diagrames-sequencia-sif.md` a 33 sequencies: nucli fiscal, cobraments, endpoints reals, curs/taller/jornada, pack, grup, regal, USOC, processos manuals, Redsys base i asincron, seguretat, documents, notificacions, recuperacio, legacy, conciliacio i AEAT pendent.
- Ampliat `33-casos-us-sif.md` a sis vistes i un inventari UC-01 a UC-60, amb subcasos, pantalles, permisos i cobertura agrupada de les 192 pantalles/apartats Trello 4.
- Creat `34-diagrames-dades-estats-sif.md` amb el model combinat de 16 taules i els estats de factura, cobrament, cua AEAT i cua/intencions Redsys.
- Creat `35-matriu-tracabilitat-diagrames.md` per demostrar la cobertura de codi, proves, classes, scripts, endpoints, taules, pantalles, casos d'us i peces pendents.
- Creat `36-mapa-components-integracions-sif.md` amb el context, la topologia del repo, els 25 PHP de `codi-drive`, els tres endpoints reals, les fronteres de seguretat, el desplegament, les proves i la relacio amb les 13.277 targetes reconciliades.
- Detectada i corregida una errada de la primera ampliacio: els endpoints reals són `factures/issue`, `payments/register` i `redsys/callback`; `GET /api/incidencies` no existeix i queda marcat com a disseny de panell.
- Afegida la cobertura que faltava de 112 classes `*Test` i 234 metodes de prova a la base, 118/276 a la branca, i de la classe `Intranet` amb 495 funcions, 292 de publiques.
- Verificacio automatica: cap classe de produccio, script, taula, endpoint o UC-01..UC-60 de l'inventari ha quedat fora; els 67 blocs Mermaid dels documents 31-34 i 36 s'han renderitzat correctament amb Mermaid CLI 11.12.0.
- Es mantenen com a pendents, sense presentar-los com a acabats, el client/worker AEAT, certificat, anul·lacio/subsanacio, panell i permisos finals, documents segurs, conciliador TPV i integracio definitiva ecommerce/intranet.
- No s'ha consultat `xat-original`, no s'ha modificat codi i no s'ha fet commit ni push.

## 2026-09-15 - Reconciliació de les set còpies i frontera de pagament

- Incorporat el significat de les set carpetes de `codi-drive`: dues candidates amb canvis VERI*FACTU i cinc còpies actuals o històriques sense aquests canvis.
- Inventari local comprovat: 6.848 fitxers, 1.920 PHP; 25 PHP candidats i 1.895 PHP a `intranet-actual`, `web-actual`, `intranet-alumne-actual`, `old-intranet` i `intranet-collaboradors`.
- Comparats els 25 candidats amb els homòlegs disponibles: 14 són idèntics, 8 diferents i 3 no tenen homòleg directe. No s'hi ha detectat cap crida als tres endpoints SIF.
- `intranet-actual/Intranet.php` té 39.229 línies i 510 funcions; la candidata en té 37.603 i 495. La candidata no es pot desplegar com a substitució sense reconciliar versions.
- Decidit que la intranet i la web continuen com a canals, però el pagament amb efecte fiscal s'ha de programar i centralitzar al SIF de `pay.prisma.cat` mitjançant adaptadors autenticats.
- La intranet alumne entra al perímetre quan consulta el pendent i obté l'enllaç/intenció de pagament. La intranet de col·laboradors i `old-intranet` documenten factures/rebuts i honoraris de tutors com a circuit adjacent de proveïdors.
- Creat `37-auditoria-comparativa-codi-drive.md` i actualitzats els documents 31-36, l'índex i `codi-drive/README.md`.
- Catàleg actual: 14 diagrames al document 31, 38 seqüències al 32, 7 vistes al 33, 10 diagrames al 34, 11 al 36 i 2 al 37; total de 82 blocs Mermaid validats amb Mermaid CLI 11.12.0.
- Detectat risc de secrets/configuracions incorporats a les còpies. No se n'han reproduït valors; cal sanejar i externalitzar abans de commit o desplegament.
- No s'ha consultat el JSONL de `xat-original`, no s'ha modificat cap PHP i no s'ha fet commit ni push.

## 2026-09-15 - Pla mínim de llançament al 31/12/2026

Creat `00-control/pla-mvp-2026-12-31.md`: calendari, 31 tasques agrupades, dependències i acceptació per compra de curs i Passar pagaments. Llançament proposat 14/12, reserva fins al 31/12; 3 dies entre setmana i caps de setmana, base 40 h/setmana i 360 h de tasques inicials. Abast de cursos individuals/transferències provisional pendent de resposta; reestimació el 27/09. No s'ha verificat producció ni executat proves en aquesta planificació.

## 2026-09-15 - MVP de l'assistent de fitxes funcionals

- Implementat sota `sif/` un MVP local que valida l'entrada del cas, prepara un manifest de fonts amb fragments, línies, estat Git i hash SHA-256, i valida l'estructura i la traçabilitat de la fitxa.
- Fixades 21 seccions i els estats `CONFIRMAT`, `PROPOSTA`, `PENDENT`, `CONFLICTE` i `NO APLICABLE`, amb regles mecàniques per impedir confirmacions sense una font autoritzada existent.
- Preparada l'entrada pilot d'UC-26 `Canvi de curs` i la seva llista explícita de fonts. El resultat és `PREVIEW` i no escriu automàticament a `documentacio/` ni a Trello.
- El generador és transversal; Xat 3 conserva l'autoritat sobre decisions funcionals i fiscals. UC-26 continua tenint com a ubicació canònica `documentacio/04-estat-final/33-casos-us-sif.md`.
- Afegides proves unitàries del contracte d'entrada, extracció de fragments, cites autoritzades, conflictes, hash obsolet, rutes insegures i porta `READY_FOR_PROGRAMMING`.
- Verificats els JSON, les 21 capçaleres i les 23 rutes de fonts; els 7 PHP nous passen el lint. Les 13 proves específiques de l'assistent passen i el preparador genera el manifest d'UC-26 amb 23 fonts existents, 21 d'autoritzades i fragments a totes 23.
- La suite global s'ha executat amb el PHP 8.4.22 local i dona `167 passed, 85 failed`: els errors són principalment la connexió rebutjada a la BD de test i també hi ha assercions preexistents alienes a l'assistent. No s'ha configurat ni modificat cap BD en aquesta sessió.
- Generada la primera fitxa completa `PREVIEW` d'UC-26 fora de `documentacio/`: 82 afirmacions classificades, inclòs el conflicte de nomenclatura `canvi_curs` versus `course_change_event`; estat `NEEDS_DECISION` per decisions fiscals, comercials, tècniques i de pantalla encara pendents dels xats responsables.

## 2026-09-15 - Revisió funcional i registral completa

- Corregit l'abast de l'auditoria: centralitzar el pagament a `pay.prisma.cat` és necessari però no suficient. També s'han de transformar dades fiscals, edicions, canvis de curs, baixes, ajusts, descomptes, rectificatives, anul·lacions, subsanacions, documents, correus, permisos, incidències, reconciliació, versions, exports i continuïtat.
- Contrastats el document de compliment, l'inventari de canvis, els fluxos, les pantalles, els correus, les 192 files de la matriu de pantalles, l'estat final, el panell SIF, seguretat i el diccionari de camps.
- Contrastats els mètodes llegats crítics `guardarDadesPagament_modalsresultatCerca()`, `realitzarCanviCurs_modalCanviCurs()`, `confirmaBaixa_modalDonarBaixa()`, `efectuarPagament*()`, `guardarDadesFactura_Factures()` i `anularFactura()`.
- Confirmat que les dues carpetes candidates no contenen referències als registres SIF principals, anul·lació/subsanació, auditoria, outbox, accessos segurs o versions, i que continuen existint mutacions llegades. El seu estat continua `[CANDIDAT/PARCIAL]` i el desplegament és `[NO-GO]`.
- Confirmat que `sif/` aporta un nucli fiscal/econòmic real, però encara falten el pla de control funcional i registral i diverses ampliacions d'esquema/workflow.
- Creat `documentacio/04-estat-final/38-matriu-transformacio-funcional-verifactu.md` com a matriu mestra de 27 àrees, registres requerits, punts de codi, canvis per canal, prioritats i criteri de completitud.
- Ampliats els documents 31-37: serveis de gestió/registre/seguretat, sis seqüències noves, UC-69 a UC-86, model registral conceptual, traçabilitat, mapa complet de control i auditoria funcional de les còpies.
- Catàleg actual: 18 diagrames al document 31, 44 seqüències al 32, 8 vistes al 33, 13 diagrames al 34, 12 al 36, 2 al 37 i 3 al 38; total de 100 blocs Mermaid validats amb Mermaid CLI 11.12.0.
- No s'ha carregat el JSONL de `xat-original`, no s'ha modificat cap PHP i no s'ha fet commit ni push.

### Regla transversal afegida: auditoria universal de pagaments

- Qualsevol petició o decisió sobre un pagament, des de qualsevol entorn, ha de generar `payment_action_event` append-only.
- La traça inclou alta, reutilització idempotent, cerca, consulta, exportació, assignació, reassignació, conciliació, retorn, compensació, cancel·lació operativa, retry, importació, sincronització, denegació, error i intent de mutació bloquejat.
- Cada acció conserva actor o procés, rol, entorn, canal, `REQUEST_ID`, `CORRELATION_ID`, acció, resultat, motiu, timestamps i abans/després resumit quan correspongui.
- S'aplica `fail closed`: si el ledger d'auditoria no està disponible, no s'executa l'acció ni es retornen dades del pagament.
- Les mutacions correctes i el seu event terminal es confirmen atòmicament; els intents rebutjats o fallits també queden registrats.
- `PaymentService` i `PaymentRepository` actuals no implementen encara aquesta dependència; continua sent un bloqueig abans de producció.

## 2026-09-15 - Correcció del pla després de revisió de l'usuari

El primer pla infravalorava la feina i queda superat. Es retiren les 360–450 h i el tall de cursos individuals com a base validada. L'usuari dedica tres dies entre setmana a VERI*FACTU i els altres dos a altres feines; caps de setmana disponibles. Creats `pla-mestre-verifactu-2026-12-31.md` (36 paquets de treball, dependències, capacitat i portes de control) i `inventari-fonts-pla-2026-09-15.md` (7 exports locals, 25.706 targetes no arxivades, 81 casos/variants i 192 pantalles). Els recomptes no són tasques independents ni verificació actual de Trello. Falta dimensionar hores restants amb evidència de cada paquet; no s'ha certificat viabilitat del 31/12 ni executat proves. No hi ha exclusió aprovada dels casos especials.

## 2026-09-15 - Revisió de gestió amb disponibilitat real de 75 h/setmana

L'usuari concreta 15 h cadascun dels tres dies entre setmana dedicats a VERI*FACTU i 30 h totals el cap de setmana. El pla anterior amb 50 h/setmana i necessitat de suport queda superat per `pla-execucio-75h-2026-12-31.md`. Capacitat amb reserva del 25%: 855–877,5 h fins al 31/12. Estimació inicial detallada de 36 paquets: abast ampli 696/1116/1860 h (favorable/probable/advers); proposta limitada 720 h, encara no aprovada. Calendari limitat individual: 680 h fins al 13/12, 40 h de desplegament/seguiment posterior, total 720 h; finestra condicionada d'activació 14–20/12. L'abast ampli probable continua sense cabre, projecció per càrrega finals de gener/principis de febrer de 2027. Estimacions de gestió, no hores mesurades ni producte verificat. Fonts i hipòtesis a `estimacio-detallada-verifactu-2026-09-15.md` i dades editables al JSON homònim d'hores. No hi ha suport extern ni reduccions d'abast aprovats.

## 2026-09-15 - Correcció de cobertura: gestions i traça universal del pagament

L'usuari reclama confirmar el registre de qualsevol gestió que afecti un pagament i la cobertura de la BD/documentació acordada. Contrastats document 38 (apartats 4, 6, 14), UC-86, document 34 (13), document 35 (12/13), diccionari 24 (8/9) i decisions vigents. El pla no pressupostava explícitament `payment_action_event`/`PaymentActionGateway`; no s'ha trobat implementació als PHP/SQL revisats dels dos worktrees. Creat `cobertura-registres-gestio-pagaments.md` amb VT-37 obligatori, tasques, proves i correspondència dels registres documentals amb els paquets. Totals de 720/1116 h marcats com a base incompleta pendent de reconciliar; capacitat de 75 h/setmana mantinguda. No s'ha implementat codi, inspeccionat BD productiva ni certificat cobertura total.

## Planificació reconciliada R2 — 16/09/2026
- Pla vigent: [Pla reconciliat R2](pla-reconciliat-r2.md).
- Cobertura planificada: 118 casos/variants, 29 accions de pagament, 24 grups de registres, 38 paquets.
- Estimació: 944 h mínim proposat (reduccions no aprovades); 1.332 h probables abast ampli. La traça i el classificador comuns tenen pressupost explícit, sense duplicar 28 h traslladades.
- Capacitat des del 16/09: 75 h brutes/setmana; 843,75–866,25 h netes fins al 31/12 amb reserva del 25%. El mínim en solitari apunta al 9–11/01/2027.
- [x] Correspondències documentals i sumes comprovades; model i matrius guardats.
- [ ] Implementació, proves de producte i acceptació de producció pendents. Cap reducció funcional ni contractació de suport aprovada per aquest registre.

## 2026-09-16 - Fitxes funcionals completes i model registral materialitzat

- Reconciliades les 145 targetes obertes de la llista `Fitxes mare` de l'export local de Trello: 145 mapades a casos canònics o a elements META i 0 sense classificar. No s'ha interpretat aquest recompte com una lectura en viu de Trello.
- Ampliat el catàleg canònic amb UC-87 a UC-105. El resultat és de 105 casos numèrics i 13 variants amb lletra, 118 fitxes funcionals en total.
- Generades 118 fitxes separades a `documentacio/06-fitxes-funcionals/`, cadascuna amb 21 apartats obligatoris, manifest de fonts i hash, regles, fluxos, dades, permisos, auditoria, proves, traçabilitat, decisions pendents i tasques de desenvolupament.
- Creat `39-auditoria-fitxes-funcionals.md`, que conserva la correspondència detallada entre les 145 targetes mare i el catàleg, inclosos els elements de governança que no són casos d'ús independents.
- Materialitzat el disseny registral amb la migració additiva `2026_09_15_000003_add_functional_audit_control.sql`: 21 taules per auditoria transversal, accions de pagament, events operatius, històrics, control fiscal, intents AEAT, documents, comunicacions, accessos, incidències, versions, exports, conciliació i evidència de restauració.
- Afegida una plantilla de rols MySQL 8 que no concedeix `UPDATE` ni `DELETE` sobre els ledgers append-only, i repositoris append-only inicials per `payment_action_event` i `operational_event`.
- Tall VT-37.2 iniciat: afegits `PaymentActionGateway`, `PaymentActionEventWriter` i proves unitàries de fail-closed, intent previ, event terminal, reutilització idempotent i rollback si falla l'auditoria terminal. `PaymentActionEventRepository` valida ara `ACTION`, `RESULT`, `SOURCE_ENVIRONMENT`, `SOURCE_CHANNEL` i `ACTOR_TYPE` contra el diccionari.
- Afegides proves estructurals d'esquema/permisos i proves unitàries de les invariants de resultat del repositori i del gateway de pagaments. En aquesta execució no s'han pogut llançar perquè no hi ha cap executable PHP disponible al `PATH`; tampoc s'ha aplicat ni provat la migració contra MySQL.
- Validacions executades: 118 fitxes, 105 casos numèrics, 21 apartats per fitxa, 145/145 targetes reconciliades, cap marcador sense resoldre i 14/14 diagrames Mermaid del document 34 renderitzats amb Mermaid CLI 11.12.0.
- L'estat continua `[NO-GO]`: `PaymentActionGateway` existeix però falta integrar-lo amb `PaymentService` i tots els canals, implementar `PaymentActionAuditService`, el monitor, el worker AEAT i els serveis de les altres taules, desplegar permisos, provar PHP/MySQL i completar preproducció i acceptació.
- No s'ha carregat el JSONL de `xat-original`, no s'ha modificat cap PHP de les còpies llegades i no s'ha fet commit ni push.

## 2026-09-16 - Pantalles i procediments interns convertits a especificacio operativa

- Integrats als documents existents els procediments finals de `Passar pagaments`, `Generar factura abans de pagar`, `Consulta - Edita - Anula factura`, accessos d'alumne/empresa/responsable, permisos i avisos `VERI*FACTU`.
- `07-pantalles-intranet.md` ara baixa els criteris a fluxos de pantalla, panells d'accio, avisos obligatoris, procediment d'acces extern i comportament d'indicadors/notificacions/incidencies.
- `10-procediments-intranet-ecommerce.md` incorpora procediments interns finals, sortides esperades, matrius d'accio i regles de visibilitat per usuaris externs.
- `16-estat-final-pantalles.md` incorpora components obligatoris de les pantalles finals i criteri visual per avisos, consultes i bloquejos.
- `21-seguretat-permisos-accessos.md` incorpora regles transversals de bloqueig i avis aplicables a endpoints i accessos externs.
- `22-manual-operatiu-intern.md` incorpora comprovacions practiques abans de confirmar pagaments, factura abans de cobrament, rectificatives/devolucions i consultes externes.
- No s'ha consultat `xat-original`, no s'ha modificat codi i no s'ha fet commit ni push.

## 2026-09-16 - Correcció de completitud després de l'auditoria fitxes-codi-BD

- Queda superada la qualificació anterior “fitxes funcionals completes”. Les
  fitxes existien i tenien 21 apartats, però 14 claims genèrics es repetien a
  totes, 118/118 estaven `NEEDS_DECISION`, cap citava la reconciliació Trello i
  cap baixava IVA/base/exempció al nivell funcional.
- Corregit l'abast Trello: hi ha 185 targetes obertes `Fitxes mare` en tres
  exports locals (145 del tauler 2, 10 del tauler 3 i 30 del tauler 6), no només
  145. L'script de reconciliació comprova els tres recomptes, SHA-256 i 0 títols
  sense mapar.
- El mapatge 185/185 acredita inventari/classificació, no incorporació completa
  de les descripcions i checklists. El tauler 2 conté 145 descripcions, 49.578
  caràcters, 5 checklists i 36 ítems; la revisió claim a claim continua pendent.
- Afegits UC-106..UC-112 a partir del codi real: reserva abans de pagament,
  duplicat d'inscripció, tastet gratuït, curs subvencionat, descompte d'amics,
  docent novell/dret futur i snapshot complet abans del TPV.
- Catàleg actual: 112 casos numèrics + 13 variants = 125 fitxes. Totes declaren
  `STRUCTURED_DRAFT_NEEDS_CASE_REVIEW`; totes expliciten el límit Trello, 55
  incorporen els camps fiscals mínims i les set noves tenen entrada, regla,
  flux i prova específics amb evidència de codi.
- Creat `40-auditoria-buits-fitxes-codi-bd.md` amb la discrepància entre
  cobertura estructural i funcional, els punts d'entrada absents de la carpeta
  candidata i els buits de dades/persistència.
- Afegida la migració 000004: 5 `ALTER TABLE` per camps fiscals/documentals i 4
  taules (`commercial_operation`, `commercial_operation_party`,
  `discount_validation`, `payment_link`). No s'ha aplicat.
- `run-migrations.php` registra cada migració a `sif_schema_migration` amb
  SHA-256 i rebutja modificar una migració ja aplicada. Abans de producció cal
  provar migració, rollback operatiu/backups i fallades parcials en MySQL.
- Afegits i validats amb Mermaid CLI 1 diagrama de classes, 3 seqüències i 2
  diagrames de dades/estats sobre l'operació comercial prèvia.
- Validacions executades: 125/125 fitxes, 112 casos numèrics, 21 apartats,
  185/185 targetes mare sense títols pendents i 6/6 diagrames nous renderitzats.
- PHP i MySQL no estan disponibles al `PATH`; les proves noves i la migració no
  s'han executat. L'estat continua `[NO-GO]`.
- No s'ha carregat el JSONL de `xat-original`, no s'ha modificat PHP llegat i no
  s'ha fet commit ni push.

## 2026-09-16 - Segona auditoria de buits sobre la superfície executable

- Contrastats 1.920 fitxers PHP de les set carpetes, amb atenció específica als
  510 mètodes d'`Intranet.php`, 68 d'`IntranetAlumne.php`, 256 superfícies AJAX
  de la web i les famílies d'escriptura sobre inscripcions, cursos, factures,
  promocions, regals, grups i Moodle.
- Confirmats 12 cicles que encara no tenien cas/persistència suficient:
  importació d'inscripcions, versionat d'edicions, aforament, evidències
  sensibles, promocions/drets, grups, regals, canvi de dades personals,
  repreuament de reserva caducada, packs, factura electrònica i coherència
  acadèmica/econòmica.
- Afegits UC-113..UC-124. Catàleg vigent: 124 casos numèrics + 13 variants =
  137 fitxes, totes `STRUCTURED_DRAFT_NEEDS_CASE_REVIEW` i amb 21 apartats.
- Creat `41-matriu-superficie-executable-casos.md`, que classifica punts
  d'entrada com `MAPPED`, `PARTIAL`, `GAP`, `ADJACENT` o `UNKNOWN_ACTIVE` i
  impedeix donar una ruta per migrada només perquè existeixi documentació.
- Afegida la migració additiva 000005 amb 12 taules per línies/components,
  vincle a línia fiscal, places, evidències, drets i events, importacions,
  canvis mestres/personals, entrega electrònica i estat acadèmic/econòmic.
  El total de taules creades per migracions locals passa a 52.
- Afegida `OperationLifecycleSchemaTest` amb 3 comprovacions estructurals.
- Validacions executades: generador 137/137, 124 casos numèrics, 21 apartats;
  185/185 `Fitxes mare` reconciliades amb 0 sense mapar; 12/12 taules 000005,
  16 claus foranes; `git diff --check` sense errors.
- PHP i MySQL continuen no disponibles en aquest host: la prova PHP i
  l'aplicació real de 000005 no s'han executat. L'estat continua `[NO-GO]`.
- No s'ha carregat el JSONL antic, no s'ha modificat cap PHP de les aplicacions
  llegades i no s'ha fet commit ni push.

## 2026-09-16 - Tercera auditoria dirigida de controls transversals

- La revisió focalitzada de consentiments, discrepàncies d'identitat,
  cancel·lació/activació d'edicions, `poblacions_validar` i comparacions
  Prisma/Moodle ha confirmat cinc buits diferenciats que no quedaven tancats
  per UC-120, UC-124 o els casos de pagament.
- Afegits UC-125..UC-129: consentiment de comunicacions, identitat entre
  sistemes, cicle massiu d'estat d'una edició, validació/normalització
  d'adreça i reconciliació acadèmica Prisma/Moodle.
- Catàleg vigent: 129 casos numèrics + 13 variants = 142 fitxes, totes amb 21
  apartats i estat `STRUCTURED_DRAFT_NEEDS_CASE_REVIEW`.
- Ampliades les auditories 39, 40 i 41, la matriu de transformació, el model de
  BD, el diccionari i la traçabilitat. La matriu 35 declara explícitament que
  els diagrames encara no representen aquestes cinc extensions.
- Afegida la migració 000006 amb 8 taules: consentiment/events, identitats i
  conflictes, events d'edició i impactes per operació, validació d'adreça i
  ítems de reconciliació acadèmica. El total local passa a 60 taules.
- Afegida `CrossSystemControlSchemaTest` amb 3 comprovacions estructurals.
- Validacions executades: generador 142/142, 129 casos numèrics, 21 apartats;
  0 errors de hash als manifests; 8/8 taules 000006, 8 claus foranes i 0
  referències a taules inexistents; `git diff --check` sense errors; 0 canvis a
  `codi-drive`.
- PHP i MySQL no estan disponibles en aquest host: no s'ha executat la suite
  PHP ni s'han aplicat 000001..000006. L'estat continua `[NO-GO]`.
- No s'ha carregat el JSONL antic, no s'ha modificat PHP llegat i no s'ha fet
  commit ni push.

## 2026-09-16 - Publicació del checkpoint confirmat

- La publicació ha estat autoritzada explícitament després de les auditories.
- El commit funcional `efdbeef` (`feat(sif): amplia casos funcionals i model
  transversal`) s'ha publicat a `origin/checkpoint/sif-fase-0-4` i s'ha
  comprovat que el hash local i el remot coincideixen.
- La publicació conté només `00-control`, `documentacio` i `sif`; no inclou
  `codi-drive`, `xat-original`, secrets detectables ni canvis de PHP llegat.
- Aquesta publicació és un checkpoint documental i d'esquema. PHP i MySQL no
  estan disponibles en aquest host i, per tant, l'estat continua `[NO-GO]`.

## 2026-09-16 - Proves i captures de pantalles internes preparades

- Ampliat `20-pla-proves-validacio-sif.md` amb proves transversals de pantalles, avisos i bloquejos per `Passar pagaments`, `Generar factura abans de pagar`, `Consulta - Edita - Anula factura`, intranet alumne, empresa/responsable i apartat `VERI*FACTU`.
- Ampliat `23-annex-captures-pantalla.md` amb criteris de captura fiscal, matriu de captures mínimes per pantalla crítica i criteri de privacitat.
- Les proves noves comproven que les pantalles indiquen abans de confirmar si faran `registerPayment()`, `issueInvoice(payment)`, rectificativa/devolucio/saldo, consulta o incidencia.
- No s'han executat captures ni proves reals; queden pendents entorn, dades, pantalles implementades i evidència conservable.
- No s'ha carregat el JSONL antic, no s'ha modificat codi i no s'ha fet commit ni push en aquest tall.

## 2026-09-16 - Revisio normativa i paquet signable `1.0.0`

- Revisades fonts oficials AEAT/BOE del bloc SIF/VERI*FACTU: certificacio i declaracio responsable, modalitats, registre d'alta, FAQ actualitzades a 21/07/2026 i nota de terminis amb dates 2027 segons Reial decret llei 15/2025.
- `documentacio-sif-aeat.md` queda ajustat a revisio 2026-09-16 i explicita que les fites internes no substitueixen el termini legal ni fan signable una versio.
- `declaracio-responsable-sif-prisma.md` queda reforçada com a borrador candidat `1.0.0`, amb control previ de termini aplicable, certificat/apoderament provat des del worker, camps fiscals minims, rol auditor nomes lectura i matriu interna de signatura.
- `19-registre-versions-i-canvis-sif.md`, `21-seguretat-permisos-accessos.md`, `24-diccionari-camps-i-valors.md` i `documentacio/README.md` incorporen el control bloquejant de signatura, metadades no secretes del certificat, controls del rol auditor i camps de versio/declaracio necessaris.
- L'estat continua `[NO-GO]`: no hi ha versio `1.0.0` instal·lada, certificat/apoderament provat, mapa camp normatiu -> taula/XML/PDF/QR -> prova, rol auditor executat ni validacio fiscal externa.
- No s'ha carregat `xat-original` ni s'ha modificat codi. Aquest tall documental es tanca amb checkpoint Git autoritzat en aquest xat.
