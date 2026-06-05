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
- [ ] `documentacio/03-canvis-pendents/11-inventari-canvis-pendents.md` esta al dia.
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
- [ ] Validacio criptografica Redsys real connectada a l'endpoint abans de passar `$signatureValid = true`.
- [ ] Serveis `issueInvoice()` i `registerPayment()` verificats amb PHP/MySQL de test.
- [ ] Legacy sync final, preflight i bateria go/no-go implementats i provats.

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
- [x] `Pagaments fraccionats` tancats amb criteri d'idempotencia i assignacio.
- [x] `Rectificatives` tancades amb serie `R`, motiu i mode.
- [x] `Devolucions` tancades com a `REFUND` + rectificativa quan pertoqui.
- [x] `Baixes` tancades com a event administratiu amb decisio posterior retorn/saldo/no retorn.
- [x] `Canvis de curs` tancats amb historic, diferencia, retorn/saldo i despeses/descomptes documentats.
- [x] `Factura manual` tancada com a flux `issueInvoice()` des d'intranet autoritzada.
- [x] `Migracio de factures historiques` tancada com a historic `NO_VERIFACTU` sense registre retroactiu.

## Criteri per marcar una area com a tancada

Una area es pot marcar com a revisada quan:

- el xat pont ha buscat informacio del tema al xat antic;
- s'han comparat els resultats amb els documents existents;
- les diferencies importants s'han incorporat o justificat;
- `registre-decisions.md` recull les decisions noves;
- `estat-projecte.md` diu que l'area esta revisada.
