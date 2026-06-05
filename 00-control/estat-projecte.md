# Estat del projecte VERI*FACTU

Ultima actualitzacio: 2026-06-05

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
- Bloc especialitzat de pagaments continuat: `USOC` revisat. S'ha consolidat que l'afiliacio USOC es valida manualment (`TIPUS_DESC = 4`, `VALID_DESC`), que l'alumne rep factura per la seva part i USOC rep factura per la diferencia. Queden pendents implementacio SIF, dades fiscals completes d'USOC, proves/captures i decisio final sobre `anticipi-preu-usoc`.
- Bloc especialitzat de pagaments continuat: `Codis promocionals` revisat. S'ha consolidat que el codi es valida a ecommerce/intranet, que `promocions` conserva codi, DNI, us i vigencia, i que el SIF nomes congela el resultat fiscal dins `factura_linia`. Queden pendents implementacio, SQL final de `promocions`, proves/captures i criteri final de visibilitat del codi al PDF.
- Bloc especialitzat de pagaments continuat: `Transferencia validada a intranet` revisat. S'ha recuperat el cami antic de `efectuarPagamentFacturaGenerada()` i s'ha consolidat que una transferencia sobre factura existent ha d'anar per `registerPayment()` i `payment_allocation`, sense modificar la factura emesa. Queden pendents implementacio SIF, referencia bancaria/BANC final, proves/captures i cossos de cerca/modal info.
- Arquitectura tecnica SIF tancada: `issueInvoice()` queda com l'unic flux que assigna numero fiscal, crea linies, registre fiscal, hash chain, cua AEAT i relacions; `registerPayment()` queda limitat a moviments economics sobre factura existent, sense numero fiscal ni hash chain. Quan factura i cobrament neixen junts, el criteri final es `issueInvoice()` amb bloc `payment` dins la mateixa operacio idempotent.
- Model BD SIF consolidat: `fact_rels` queda com a pont logic amb BD antiga, sense foreign keys entre BD fiscal i BD web/intranet; `payment_transaction`, `payment_allocation`, `factura_linia`, `fiscal_chain_state`, `fiscal_queue` i valors controlats queden alineats entre model, relacions i diccionari.
- Entrada de pagaments al SIF definida: Redsys entra per callback a `pay.prisma.cat` i es deduplica a `redsys_notifications`; transferencies i pagaments manuals entren per `Passar pagaments`; fitxers TPV generen analisi/conciliacio auditada; el moviment economic real viu a `payment_transaction` i l'assignacio a factura viu a `payment_allocation`.
- Bloc especialitzat de proves i posada en produccio revisat: el checklist i el pla de proves ja tenen criteris executables de `GO`, `GO AMB LIMITACIONS` i `NO-GO`, bateria bloquejant amb IDs, fitxa d'evidencia, criteri de captures, backups/restauracio, incidencies i checklist final d'activacio. Queda pendent executar-ho en preproduccio/produccio i conservar evidencies reals.
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
- Fase 10 preparada al repo de treball: creats `sif/scripts/preflight-sif.php` i `PreflightScriptTest`. El preflight es de nomes lectura, carrega l'autoload propi, usa `ConnectionFactory`, comprova connexio, taules SIF clau, Redsys, documents, incidencies i seed de `fiscal_chain_state`, i retorna JSON amb `ok`, `environment`, `checks`, `failed` i `errors` quan correspongui. El preflight no s'ha pogut executar per manca de PHP al PATH.
- Fase 11 iniciada com a preparacio tecnica, sense activar canals reals: `issueInvoice()` accepta un bloc `payment` opcional i, quan factura i cobrament neixen junts, crea `payment_transaction` i `payment_allocation` dins la mateixa transaccio idempotent de la factura. L'endpoint intern `factures/issue.php` ja construeix les dependencies de pagament. S'ha afegit prova d'integracio per Redsys normal controlat a nivell de servei, incloent reintent idempotent que retorna el `uuid_payment` existent sense duplicar registres. La verificacio executable i l'activacio real en preproduccio queden pendents per manca de PHP al PATH, BD MySQL de test i validacio criptografica Redsys connectada.

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
```

Motiu: l'estructura, el SQL, la infraestructura comuna, els validators, el hash fiscal intern, `issueInvoice()`, `registerPayment()`, la sincronitzacio legacy controlada, els endpoints interns, la porta Redsys deduplicada, els repositoris de documents/incidencies, el preflight tecnic i el primer `issueInvoice(payment)` ja estan creats sense dependencia de Composer, pero falta executar la verificacio real amb PHP i una BD de test configurada. Un cop passi aquesta base, el seguent pas tecnic sera activar Fase 11 en preproduccio: Redsys amb signatura real, factura abans de cobrament, transferencies manuals i canals especials en ordre controlat.

## Com s'ha de tancar cada sessio

Abans d'acabar qualsevol xat, demanar:

```text
Actualitza els fitxers de control del projecte: estat-projecte.md, registre-decisions.md i checklist-completitud.md amb el que hem decidit o completat en aquesta sessio.
```
