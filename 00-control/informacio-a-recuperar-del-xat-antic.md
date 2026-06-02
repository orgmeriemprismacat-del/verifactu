# Informacio a recuperar del xat antic

Aquest fitxer serveix per apuntar possibles buits entre el que es va explicar al xat antic i el que apareix als documents.

## Com treballar aquest fitxer

Cada vegada que el xat pont revisi un bloc del xat antic, ha d'afegir:

- tema revisat;
- informacio trobada;
- document on ja apareix, si apareix;
- document que cal completar, si falta;
- estat: pendent, incorporat, descartat o dubtos.

## Inventari inicial del xat antic

Data: 2026-05-19

Estat: fet inventari inicial; revisio detallada pendent per blocs.

Metode utilitzat:

- no s'ha llegit el JSONL complet dins el context;
- s'ha comprovat que `xat-original/` conte el registre antic;
- s'han filtrat missatges reals d'usuari/agent;
- s'han fet cerques tematiques per detectar concentracions d'informacio.

Temes detectats amb possible informacio pendent de contrast:

1. Context real de PrisMa: ecommerce, TPV, intranet, cursos online, packs, regals, empreses/responsables, vendes manuals i dades de facturacio.
2. Fluxos de facturacio: quan neix la factura, pagament abans/despres, rectificatives per canvi de dades fiscals, devolucions, pagaments parcials, duplicats, agrupacions i baixes.
3. Pagaments: Redsys, transferencia, correus de confirmacio, callbacks duplicats, conciliacio diaria, fitxer TPV i relacio entre pagament, inscripcio i factura.
4. Base de dades i concurrencia: taules actuals i noves, `errors_verifactu`, `reg_pagament`, `UUID_FACTURA`, `IDEMPOTENCY_KEY`, `FISCAL_ORDER`, MyISAM/InnoDB, `FOR UPDATE`, cues i retries.
5. Compliment AEAT: certificat digital, declaracio responsable, QR/XML/CSV, model 036, terminis, criteris interns i punts a validar amb assessor fiscal.
6. Pantalles i operacio: pantalles actuals d'intranet, accions de consulta/edicio/anulacio, panell `pay.prisma.cat/sif`, permisos, exportacions i incidencies.
7. Correus i plantilles: correus que envien enllacos de pagament o factura, PDF/QR, adjunts/enllacos segurs i textos segons estat de factura.
8. Proves i produccio: pilot, migracio, proves Redsys/AEAT, concurrencia, idempotencia, evidencia documental i checklist de posada en produccio.

Ordre proposat de revisio:

1. Context actual de PrisMa i canals reals.
2. Fluxos de facturacio i casos especials.
3. Pagaments, Redsys, callbacks i conciliacio.
4. Base de dades, hash chain, concurrencia i idempotencia.
5. Pantalles, permisos i operacio interna.
6. Compliment AEAT i declaracio responsable.
7. Correus, plantilles, PDF/QR i notificacions.
8. Proves, produccio, auditoria documental i governanca.

## Temes prioritaris a buscar

### Context real de PrisMa

Estat: incorporat parcialment al bloc 1; queda obert si apareix mes context quan es revisin pantalles concretes.

Que cal buscar:

- com funciona avui l'ecommerce;
- com funciona avui la intranet;
- quines intranets existeixen;
- quins canals creen o mostren factures;
- dependències internes, proveidors o restriccions que no surtin als documents.

Documents relacionats:

- `documentacio/02-context-i-estat-actual/12-documentacio-sistema-ecommerce-intranet-sif.md`
- `documentacio/02-context-i-estat-actual/13-mapa-bases-dades-i-taules.md`

Informacio trobada i incorporada:

- stack actual: PHP i JavaScript, sense framework principal, MySQL i diverses bases de dades;
- ecommerce ven cursos online i canalitza cursos, packs, regals, grups, tallers, jornades i cas USOC;
- TPV virtuals diferenciats per tipus de venda;
- la intranet gestiona vendes manuals quan una empresa, escola, responsable o particular contacta directament;
- abans del SIF, TPV, intranet i fins i tot actuacions manuals de BD podien generar factures;
- actualment no sempre es genera PDF al moment;
- dades de client/contacte i dades fiscals no estaven prou separades;
- receptors possibles: particulars, empreses, escoles, entitats i estrangers;
- volum variable, amb possibles pics de molts pagaments simultanis i fins a 2000 factures/dia.

Documents actualitzats:

- `documentacio/02-context-i-estat-actual/12-documentacio-sistema-ecommerce-intranet-sif.md`
- `documentacio/02-context-i-estat-actual/13-mapa-bases-dades-i-taules.md`

### Casos de facturacio i excepcions

Estat: incorporat parcialment al bloc 2; queda obert per al detall de codi/pantalles en blocs posteriors.

Que cal buscar:

- pagaments parcials;
- canvis de curs;
- baixes;
- devolucions;
- rectificatives;
- saldos;
- packs;
- empreses;
- inscripcions manuals;
- casos que generen dubtes fiscals.

Documents relacionats:

- `documentacio/03-canvis-pendents/04-fluxos-facturacio.md`
- `documentacio/04-estat-final/18-estat-final-operacio-incidencies.md`

Informacio trobada i incorporada:

- un mateix `IDPAG` pot tenir diversos intents Redsys amb `Ds_Order` diferents;
- transferencia pot pagar diverses factures ja emeses;
- compensacio pot ser saldo a favor o descompte;
- si es paga de mes, es pregunta si es retorna o es deixa saldo;
- les devolucions poden fer-se per Redsys, transferencia o manualment;
- quan Adam fa el retorn, la rectificativa es genera despres del retorn de diners;
- factura abans de cobrament es factura real si s'emet, encara que quedi pendent de pagament;
- si s'afegeixen o treuen participants despres d'una factura real, cal rectificativa o factura complementaria segons el cas;
- no s'utilitzaran proformes fiscals ambigues;
- canvi de curs amb mateix import pero concepte diferent requereix rectificativa si la factura ja no descriu el servei real;
- les rectificatives actuals eren sobretot negatives, pero el SIF ha de suportar substitucio o diferencies.

Documents actualitzats:

- `documentacio/03-canvis-pendents/04-fluxos-facturacio.md`
- `documentacio/04-estat-final/18-estat-final-operacio-incidencies.md`

### Pagaments, Redsys i pay.prisma.cat

Estat: incorporat parcialment al bloc 3; queda obert el detall final de SQL/implementacio quan es revisi base de dades i concurrencia.

Que cal buscar:

- decisio de migrar Redsys;
- paper de `pay.prisma.cat`;
- callbacks;
- conciliacio de pagaments;
- relacio entre pagament, inscripcio i factura.

Documents relacionats:

- `documentacio/03-canvis-pendents/06-integracio-redsys-pay-prisma.md`
- `documentacio/04-estat-final/25-panell-sif-pay-prisma.md`

Informacio trobada i incorporada:

- `realitzaPagamentAutomatic.php` rep valors com `codiCurs`, `dni`, `import`, `frac`, `idPag` i `order`;
- el codi actual envia correu intern de pagament automatic amb DNI, import, fraccio, `IDPAG` i `ORDER`;
- `Ds_Order` es conserva historicament a `web.factures.NUM_COMANDA`;
- el callback actual insereix a `web.factures` camps fiscals i operatius i actualitza `web.inscripcions`;
- les dades signades Redsys (`Ds_Order`, `Ds_Amount`) han de prevaldre sobre imports o ordres rebuts per `GET`;
- hi ha diversos canals TPV: curs individual, regal, pack, grup, taller/jornada, USOC, empresa/responsable, morositat, reclamacio o diferencia pendent;
- `Passar pagaments` ha de validar transferencia, pagament manual o compensacio i cridar `registerPayment()` o `issueInvoice()` segons si ja existeix factura SIF;
- la deteccio historica de factura abans de pagar basada en `E_FACT` s'ha de substituir per `EMESA_ABANS_COBRAMENT`, `UUID_FACTURA` i `FACTURA_RELACIONADA`;
- la confirmacio de pagament i callbacks s'han de moure de `prisma.cat` a `pay.prisma.cat`;
- les URLs antigues poden quedar com a redireccions o clients, pero no com a font fiscal;
- la conciliacio de fitxer TPV ha de buscar `redsys_notifications`, `payment_transaction`, factura i assignacio abans de crear res;
- el PDF exacte generat en emissio s'ha de conservar en espai controlat de `pay.prisma.cat`;
- les factures d'empresa, grup o responsable no han de ser visibles automaticament a l'alumne si no n'es receptor fiscal.

Documents actualitzats:

- `documentacio/03-canvis-pendents/06-integracio-redsys-pay-prisma.md`
- `documentacio/04-estat-final/25-panell-sif-pay-prisma.md`
- `documentacio/04-estat-final/17-estat-final-bd-relacions.md`

#### Bloc especialitzat: Redsys curs normal

Estat: incorporat com a bloc especialitzat de pagaments; queda obert per implementar `redsys_notifications`, substituir `realitzaPagamentAutomatic.php`, executar proves Redsys i confirmar payload final en codi.

Informacio concreta recuperada i consolidada:

- `realitzaPagamentAutomatic.php` es el callback historic de Redsys per curs normal;
- el fitxer llegeix `Ds_SignatureVersion`, `Ds_MerchantParameters` i `Ds_Signature`;
- calcula `$firma = createMerchantSignatureNotif(...)` i ha de comparar-la amb `Ds_Signature` abans de tocar BD;
- recupera `Ds_Order`, `Ds_Date`, `Ds_Hour`, `Ds_Amount` i `Ds_Response`;
- considera autoritzada la transaccio quan `Ds_Response` esta entre `0` i `99`;
- busca la inscripcio per `IDPAG` i estats `INSC CURS` `0`, `1` o `M`;
- recupera `FACTURA_RELACIONADA`, `A_PAGAR`, `PAGAMENT` i `FRACCIO` per decidir el comportament antic;
- el bloc `Generem la factura` calcula `factura_relacionada`, `ANY`, `ORDRE` i `NUM`;
- si no hi ha factura relacionada, busca l'ultim valor de `factura_relacionada`; si ja hi havia pagament, reutilitza la relacio;
- genera `A{any}/{ordre}` i insereix directament a `web.factures`;
- desa `Ds_Order` a `NUM_COMANDA`;
- actualitza `web.inscripcions` amb `PAGAMENT`, `FACTURA_RELACIONADA`, `DATA PAG` i `FRACCIO`;
- el flux final ha de substituir aquest bloc per `issueInvoice()` o `registerPayment()`;
- `IDPAG` no es clau idempotent suficient, perque pot haver-hi diversos intents Redsys o fraccionaments; `DS_ORDER` deduplica callback, i la decisio funcional considera factura previa real.

Documents actualitzats:

- `documentacio/03-canvis-pendents/06-integracio-redsys-pay-prisma.md`
- `documentacio/03-canvis-pendents/10-procediments-intranet-ecommerce.md`
- `documentacio/05-governanca-operacio/20-pla-proves-validacio-sif.md`
- `documentacio/05-governanca-operacio/21-seguretat-permisos-accessos.md`
- `documentacio/05-governanca-operacio/22-manual-operatiu-intern.md`
- `documentacio/00-index-i-pla/26-matriu-cobertura-casos.md`
- `documentacio/00-index-i-pla/27-informe-auditoria-documental.md`

#### Bloc especialitzat: Packs

Estat: incorporat com a bloc especialitzat de pagaments; queda obert per incorporar SQL real de pack/preus, implementar payload final de `issueInvoice()`, executar proves i afegir captures finals.

Informacio concreta recuperada i consolidada:

- el pack normal inclou 2 cursos;
- es crea una inscripcio per cada curs;
- les inscripcions del mateix pack comparteixen `IDPAG`;
- el futur desitjat es factura amb linies, no dues factures per decisio del client;
- si hi ha un sol pagament real, el SIF genera una sola factura amb una linia per curs;
- si excepcionalment intranet registra mes d'un pagament real, cada pagament te factura/idempotencia propia;
- el preu del pack surt de la taula de preus relacionada amb la taula de packs;
- el descompte del 25% s'aplica sempre al segon curs;
- `DESC_ORIGEN = PACK` s'aplica nomes a la linia del curs amb descompte;
- cada linia apunta a la seva `inscripcions.ID`;
- la idempotencia es `REDSYS|PACK|IDPAG:{IDPAG}|ORDER:{DS_ORDER}`;
- `buscarPagamentsPack` agrupa per `IDPAG`;
- `buscarInfoPack` consulta `info_pack`;
- `cnsInscsPack` i `cnsDadesCursPack` identifiquen les inscripcions i dades de curs que alimenten les linies fiscals.

Documents actualitzats:

- `documentacio/03-canvis-pendents/04-fluxos-facturacio.md`
- `documentacio/03-canvis-pendents/06-integracio-redsys-pay-prisma.md`
- `documentacio/05-governanca-operacio/20-pla-proves-validacio-sif.md`
- `documentacio/00-index-i-pla/26-matriu-cobertura-casos.md`
- `documentacio/00-index-i-pla/27-informe-auditoria-documental.md`

#### Bloc especialitzat: Grups

Estat: incorporat com a bloc especialitzat de pagaments; queda obert per implementar payload final de grup, validar SQL real de `descomptes_grup`/`respGrups`, executar proves i afegir captures finals.

Informacio concreta recuperada i consolidada:

- una empresa o persona pot pagar per N participants;
- hi ha una fila a `inscripcions` per participant;
- `TIPUS_INSC = G` identifica inscripcions de grup;
- `IDPAG` agrupa el grup i els seus membres;
- `respGrups` relaciona responsable i `IDPAG`;
- el receptor fiscal es escola/empresa si el grup es d'una entitat, o responsable particular si es un grup d'amics/particular;
- el preu per participant surt de `descomptes_grup`;
- la factura futura es una factura per pagament real amb una linia per participant;
- el nom del participant pot sortir a la linia, especialment per FUNDAE/Tripartita;
- el DNI del participant queda intern o en annex i nomes s'imprimeix si hi ha justificacio;
- `buscarPersRespGrup2` busca grups per DNI de responsable o participant;
- `buscarPersGrup` llista participants per `IDPAG`;
- `buscarPagamentsGrup` agrupa deutes/pagaments de grup per `IDPAG`;
- `searchMembresGrup` i `searchMembresGrup2` recorren membres per aplicar pagaments en l'operativa historica;
- els participants no han de veure la factura completa del grup si conte altres persones.

Documents actualitzats:

- `documentacio/03-canvis-pendents/04-fluxos-facturacio.md`
- `documentacio/03-canvis-pendents/06-integracio-redsys-pay-prisma.md`
- `documentacio/03-canvis-pendents/10-procediments-intranet-ecommerce.md`
- `documentacio/05-governanca-operacio/20-pla-proves-validacio-sif.md`
- `documentacio/00-index-i-pla/26-matriu-cobertura-casos.md`
- `documentacio/00-index-i-pla/27-informe-auditoria-documental.md`

#### Bloc especialitzat: Transferencia validada a intranet

Estat: incorporat com a bloc especialitzat; queda obert per implementacio SIF, proves reals, captures finals, referencia bancaria/BANC final i cossos de cerca/modal info.

Que cal buscar:

- `transferencia`, `Passar pagaments`, `efectuarPagament`, `efectuarPagamentFacturaGenerada`;
- `mostrarModalConfPag`, `buscarPagamentsByFact`, `updFactGenerada`;
- `searchMembresFactRel`, `updPayInscr`, `updDateInscr`, `updFraccBDByFact`;
- `dataPag`, `banc`, `obs`, `numFact`, `efact`;
- criteri de factura ja generada, fraccio i correu a entitat/responsable.

Documents relacionats:

- `documentacio/03-canvis-pendents/04-fluxos-facturacio.md`
- `documentacio/03-canvis-pendents/06-integracio-redsys-pay-prisma.md`
- `documentacio/03-canvis-pendents/07-pantalles-intranet.md`
- `documentacio/03-canvis-pendents/10-procediments-intranet-ecommerce.md`
- `documentacio/04-estat-final/05-model-bd-sif.md`
- `documentacio/05-governanca-operacio/20-pla-proves-validacio-sif.md`
- `documentacio/00-index-i-pla/26-matriu-cobertura-casos.md`
- `documentacio/00-index-i-pla/27-informe-auditoria-documental.md`

Informacio trobada i incorporada:

- quan un pagament es fa per transferencia, administracio el valida a `Passar pagaments` i des d'alla s'ha de cridar el SIF;
- `ajax/alumnes/efectuarPagament.php` rep per `GET` `id`, `tipus`, `pagament`, `dataPag`, `banc`, `obs`, `numFact` i `efact`;
- `efectuarPagament()` separa `efact == 0` (crear factura historica per `R/G/P/I`) i `efact != 0` (factura ja generada);
- `mostrarModalConfPag()` consulta `buscarInfoFacturaByNum`, busca entitat/responsable amb `buscarRespEntitatByCIF` i mostra avís historic d'actualitzar factura;
- `efectuarPagamentFacturaGenerada()` usa `buscarPagamentsByFact`, recupera `A_PAGAR`, `PAGAMENT`, `FACTURA_RELACIONADA`, `FRACCIO`, `IDPAG`, `cif` i `E_FACT`;
- el codi historic fa `updFactGenerada` sobre `web.factures` i despres reparteix import amb `searchMembresFactRel`, `updPayInscr`, `updDateInscr` i `updFraccBDByFact`;
- el SIF substitueix aquest comportament per `payment_transaction` i `payment_allocation`;
- una factura VERI*FACTU ja emesa no es modifica quan arriba la transferencia; nomes es registra cobrament;
- si no hi ha factura i el cobrament crea obligacio fiscal, cal `issueInvoice()` + `registerPayment()` idempotent;
- la idempotencia recomanada es `TRANSFERENCIA|REF:{REFERENCIA_BANCARIA}` o, si no hi ha referencia, `TRANSFERENCIA|FACT:{NUM_FACT}|DATA:{DATA_PAG}|IMPORT:{IMPORT}|BANC:{BANC}`.

Documents actualitzats:

- `documentacio/03-canvis-pendents/04-fluxos-facturacio.md`
- `documentacio/03-canvis-pendents/06-integracio-redsys-pay-prisma.md`
- `documentacio/03-canvis-pendents/07-pantalles-intranet.md`
- `documentacio/03-canvis-pendents/10-procediments-intranet-ecommerce.md`
- `documentacio/04-estat-final/05-model-bd-sif.md`
- `documentacio/05-governanca-operacio/20-pla-proves-validacio-sif.md`
- `documentacio/00-index-i-pla/26-matriu-cobertura-casos.md`
- `documentacio/00-index-i-pla/27-informe-auditoria-documental.md`

#### Bloc especialitzat: Codis promocionals

Estat: incorporat parcialment al bloc especialitzat; queda obert per implementacio, SQL final, proves reals i captures finals.

Que cal buscar:

- camp `Codi promocional` del formulari d'inscripcio;
- `promocions.CODI_DESCOMPTE`, `DNI`, `MES`, `CURS`, `PERCENTATGE`, `USED`, `DATAI`, `DATAF`;
- promocions temporals a `descomptes.TIPUS` 11-99;
- consultes `cnsSiTePromocioDispo` i `updDataFPromocio`;
- codis `MACABODETITULAR#...`;
- promocio de docents novells i `recent_titulat`;
- decisio SIF sobre snapshot de linia i no revalidacio del codi.

Documents relacionats:

- `documentacio/00-index-i-pla/documentacio-verifactu.md`
- `documentacio/04-estat-final/05-model-bd-sif.md`
- `documentacio/03-canvis-pendents/04-fluxos-facturacio.md`
- `documentacio/03-canvis-pendents/06-integracio-redsys-pay-prisma.md`
- `documentacio/03-canvis-pendents/10-procediments-intranet-ecommerce.md`
- `documentacio/05-governanca-operacio/20-pla-proves-validacio-sif.md`
- `documentacio/00-index-i-pla/26-matriu-cobertura-casos.md`
- `documentacio/00-index-i-pla/27-informe-auditoria-documental.md`

Informacio trobada i incorporada:

- el client pot introduir un codi promocional al formulari d'inscripcio;
- ara mateix a la factura antiga no sempre surt el detall del descompte, nomes l'import final ja aplicat;
- `descomptes.TIPUS` indica que de l'11 al 99 son promocions temporals;
- les promocions temporals es calculen segons la taula `descomptes`;
- `promocions` conserva codi, DNI, curs/edicio, percentatge, us i vigencia;
- `cnsSiTePromocioDispo` valida `CODI_DESCOMPTE LIKE ?`, DNI, `USED = 0`, `DATAI <= CURRENT_TIME` i `DATAF >= CURRENT_TIME`;
- `updDataFPromocio` tanca vigencia amb `DATAF = CURRENT_TIME` per codi/DNI actiu;
- `MACABODETITULAR#...` es un exemple de codi personal i intransferible, d'un sol us i valid fins a una data concreta;
- el flux de docents novells comprova `recent_titulat` per `ID_INSC` i `VALIDAT = 1` abans de comunicar el codi;
- el SIF no ha de validar si el codi es valid, caducat o usat: ha de rebre la foto fiscal ja calculada;
- `factura_linia` ha de conservar `desc_origen = CODI_PROMO`, `desc_codi_promo`, import/percentatge, text visible i total final;
- si el codi caduca o queda usat despres d'emetre, la factura no canvia;
- si el codi es invalid abans de pagar/facturar, cal recalcular sense codi o obrir incidencia.

Documents actualitzats:

- `documentacio/00-index-i-pla/documentacio-verifactu.md`
- `documentacio/04-estat-final/05-model-bd-sif.md`
- `documentacio/03-canvis-pendents/04-fluxos-facturacio.md`
- `documentacio/03-canvis-pendents/06-integracio-redsys-pay-prisma.md`
- `documentacio/03-canvis-pendents/10-procediments-intranet-ecommerce.md`
- `documentacio/05-governanca-operacio/20-pla-proves-validacio-sif.md`
- `documentacio/00-index-i-pla/26-matriu-cobertura-casos.md`
- `documentacio/00-index-i-pla/27-informe-auditoria-documental.md`

#### Bloc especialitzat: Regals

Estat: incorporat com a bloc especialitzat de pagaments; queda obert per implementar payload final de regal, validar SQL real de `regal`/`FACT_REL`, executar proves i afegir captures finals.

Informacio concreta recuperada i consolidada:

- paga qui regala el curs;
- la factura va al comprador, no al beneficiari;
- el comprador tria curs o tipus de curs;
- el comprador pot posar dedicatoria;
- el comprador introdueix les seves dades de facturacio;
- el destinatari encara no omple dades d'inscripcio en el moment de compra;
- es genera un codi regal per bescanviar;
- quan el destinatari bescanvia el codi, crea o completa inscripcio sense factura nova;
- `SOURCE_TYPE = REGAL` i `SOURCE_ID = regal.ID`;
- `buscarRegNoPayByCodi` cerca regals pendents per `CODI` i `FACT_REL = 0`;
- `buscarRegNoPayByDni` cerca regals pendents per `NIFC`;
- `buscarRegalById` recupera curs, comprador, adreca, codi, `FACT_REL`, `ORIGEN` i `DESTI`;
- `updFactRegal` actualitza `regal.FACT_REL` amb la factura relacionada historica;
- el modal historic mostra `ORIGEN`, `DESTI`, `CODI REGAL` i `CURS REGAL`;
- el correu historic envia el codi regal i enllaç a la targeta regal PDF;
- el missatge historic indica validesa d'un any des de la compra.

Documents actualitzats:

- `documentacio/03-canvis-pendents/04-fluxos-facturacio.md`
- `documentacio/03-canvis-pendents/06-integracio-redsys-pay-prisma.md`
- `documentacio/03-canvis-pendents/10-procediments-intranet-ecommerce.md`
- `documentacio/05-governanca-operacio/20-pla-proves-validacio-sif.md`
- `documentacio/00-index-i-pla/26-matriu-cobertura-casos.md`
- `documentacio/00-index-i-pla/27-informe-auditoria-documental.md`

### Base de dades i relacions

Estat: incorporat parcialment al bloc 4; queda obert per al SQL final, grants MySQL exactes i migracio historica quan s'implementi.

Que cal buscar:

- noms de bases de dades actuals;
- noms de taules actuals;
- taules noves previstes;
- claus de relacio;
- camps imprescindibles;
- dubtes sobre migracio o historics.

Documents relacionats:

- `documentacio/04-estat-final/05-model-bd-sif.md`
- `documentacio/04-estat-final/17-estat-final-bd-relacions.md`
- `documentacio/05-governanca-operacio/24-diccionari-camps-i-valors.md`

Informacio trobada i incorporada:

- existia una BD fiscal parcial amb `errors_verifactu`, `factura`, `factura_log`, `factura_registres`, `reg_pagament`, `session_log`, `fiscal_queue` i `fiscal_sequence`;
- l'esquema inicial contenia `MyISAM`, `UUID varchar(12)` i imports `double`, que s'han de tractar com a deutes de migracio;
- al xat antic es va indicar que les taules s'havien passat a `InnoDB` i que s'havia afegit `IDEMPOTENCY_KEY`;
- `reg_pagament` representa intents/registres antics de passarel·la i el model final el substitueix per `payment_transaction` i `payment_allocation`;
- `errors_verifactu` pot reutilitzar-se com a taula d'incidencies SIF si s'amplia i es tipifiquen valors;
- la hash chain ha de ser global per tot el SIF, no separada per serie;
- `FISCAL_ORDER` es l'ordre fiscal temporal global i `NUM_SEQ`/`NUM_VISIBLE` son numeracio humana per serie i any;
- `fiscal_sequence` bloqueja numeracio per serie/any i `fiscal_chain_state` bloqueja l'estat global de la cadena;
- no s'ha de fer `SELECT MAX(NUM)+1`;
- `fiscal_queue` ha de conservar payload o referencia suficient per reintentar sense reconstruir des de dades vives;
- a la BD ningu ha d'editar factures emeses directament; els updates s'han de bloquejar a nivell d'app i permisos MySQL.

Documents actualitzats:

- `documentacio/02-context-i-estat-actual/13-mapa-bases-dades-i-taules.md`
- `documentacio/04-estat-final/05-model-bd-sif.md`
- `documentacio/04-estat-final/17-estat-final-bd-relacions.md`
- `documentacio/05-governanca-operacio/24-diccionari-camps-i-valors.md`

### Compliment AEAT i declaracio responsable

Estat: incorporat parcialment al bloc 6; queda obert per a revisio final quan existeixi la versio `1.0.0`, el certificat/apoderament estigui configurat i es disposi de proves reals.

Que cal buscar:

- criteris legals comentats;
- dades de l'empresa o del sistema;
- responsable;
- versio activa;
- riscos detectats;
- parts que caldria validar amb assessor fiscal.

Documents relacionats:

- `documentacio/01-compliment-aeat/documentacio-sif-aeat.md`
- `documentacio/01-compliment-aeat/declaracio-responsable-sif-prisma.md`
- `documentacio/05-governanca-operacio/19-registre-versions-i-canvis-sif.md`

Informacio trobada i incorporada:

- el xat antic va separar tres peces: document intern de projecte, document tecnic/organitzatiu per AEAT i declaracio responsable signable;
- la declaracio responsable actual s'ha de tractar com a `0.1-BORRADOR`, no com a document final signable;
- la primera versio productiva signable prevista es `1.0.0`;
- no cal signar cada canvi durant desenvolupament; els canvis rellevants posteriors poden requerir nova declaracio o annex versionat;
- Associacio PrisMa queda com a entitat productora/titular interna del sistema per a us propi;
- Meriem Abjil Bajja queda com a responsable tecnica, funcional, documental, de desenvolupament, manteniment i posada en operativa, sense quedar identificada per defecte com a productora externa persona fisica;
- Adam Carmona queda com a responsable legal/direccio i signant/revisor quan correspongui;
- Pablo Martori Delupi queda com a operador de gestio/facturacio sensible quan el flux ho permeti;
- el certificat digital indicat al xat antic es el certificat de l'entitat;
- falta completar el NIF/carrec d'Adam, data i lloc de signatura abans de la declaracio definitiva;
- segons FAQ AEAT vigent revisada el 2026-06-01, no cal comunicar l'opcio `VERI*FACTU` via model 036;
- segons nota AEAT revisada el 2026-06-01, els terminis generals son 1 de gener de 2027 per entitats que presenten Impost sobre Societats i 1 de juliol de 2027 per la resta d'obligats afectats;
- cal confirmar formalment l'abast aplicable a PrisMa: Impost sobre Societats o altre regim, no SII, no normativa foral i sense exempcio especifica;
- sense assessor fiscal dedicat, els criteris interns es poden documentar, pero els punts interpretatius han de quedar pendents de validacio externa si mes endavant es possible;
- punts sensibles marcats: rectificatives per canvi de dades fiscals, factura abans de cobrament no pagada, compensacions/saldos, mencio d'exempcio IVA i textos visibles de descomptes sensibles;
- text actual d'exempcio IVA indicat per PrisMa: "Factura exempta d'IVA d'acord amb l'article 20.1.9 de la Llei 37/1992...".

Documents actualitzats:

- `documentacio/01-compliment-aeat/documentacio-sif-aeat.md`
- `documentacio/01-compliment-aeat/declaracio-responsable-sif-prisma.md`
- `documentacio/05-governanca-operacio/19-registre-versions-i-canvis-sif.md`
- `documentacio/03-canvis-pendents/09-checklist-posada-en-produccio.md`

### Pantalles, permisos i operacio interna

Estat: incorporat parcialment al bloc 5; queda obert per a captures finals i matriu detallada de permisos quan s'implementin pantalles.

Que cal buscar:

- pantalles actuals que cal tocar;
- rols d'usuari;
- permisos;
- alertes;
- procediments interns;
- qui ha de poder veure, corregir o exportar informacio.

Documents relacionats:

- `documentacio/03-canvis-pendents/07-pantalles-intranet.md`
- `documentacio/04-estat-final/16-estat-final-pantalles.md`
- `documentacio/05-governanca-operacio/21-seguretat-permisos-accessos.md`
- `documentacio/05-governanca-operacio/22-manual-operatiu-intern.md`

Informacio trobada i incorporada:

- rutes d'intranet ja identificades: alumnes, factures, pagaments, factura abans de cobrar, entitats, validar descomptes i pantalles de reclamacio/morositat;
- permisos actuals basats en `apartats.ROLS_VISUALITZAR`, `ROLS_EDITAR`, `ROLS_ENVIAR_MSG` i `usuaris.ROLS`;
- `consultaRolsEdiicio($page)` retorna rols d'edicio de pagina; es conserva el nom real malgrat la grafia;
- `consultaRolsUsuari()` retorna rols de l'usuari;
- `tePermisEdicio` es calcula al JS comparant rols, pero les accions fiscals han de validar-se tambe al servidor;
- Isa pot consultar i donar suport, especialment Moodle/Secretaria, pero no es rol fiscal ordinari;
- Meriem, Adam i Pablo concentren les accions fiscals critiques del dia a dia;
- `Consulta - Modifica alumne` es pantalla de consulta i inici d'accions, no lloc per editar factures emeses;
- `Consulta - Edita - Anula factura` s'ha de convertir en consulta, rectificativa, devolucio, marca `E_FACT`, PDF i historial;
- la morositat segueix fases: primer pagament o justificacio abans del curs, revisio segona setmana, reclamacio final, una setmana despres, un mes despres i morositat;
- morositat no es baixa i no rectifica factura per si sola;
- l'apartat `VERI*FACTU` de la intranet ha de mostrar indicador visual i resum, pero la resolucio oficial viu al SIF.

Documents actualitzats:

- `documentacio/03-canvis-pendents/07-pantalles-intranet.md`
- `documentacio/04-estat-final/16-estat-final-pantalles.md`
- `documentacio/05-governanca-operacio/21-seguretat-permisos-accessos.md`
- `documentacio/05-governanca-operacio/22-manual-operatiu-intern.md`

#### Subbloc especialitzat: Consulta - Modifica alumne

Estat: incorporat com a subbloc especialitzat d'intranet; queda obert per revisar cossos reals de metodes, implementar pantalla final, executar proves i afegir captures finals.

Informacio concreta recuperada i consolidada:

- pantalla amb dades personals, inscripcions pendents, inscripcions acabades i observacions generals;
- cinc icones per inscripcio: informacio, canvi de curs, baixa, veure factura i certificat;
- les icones amb baixa opacitat no es poden clicar, pero el SIF ha de validar igualment al servidor;
- si l'alumne es moros, es pot consultar factura si hi ha part pagada i veure que s'ha pagat;
- veure factura des d'aquesta pantalla es nomes lectura i descarrega PDF;
- la factura historica usa camps estructurats de `web.factures`, especialment `concepte1` i `concepte2`;
- el canvi de curs recalcula automaticament `A_PAGAR` segons curs nou, descompte original i si aquest descompte encara aplica;
- les despeses de gestio del canvi de curs es calculen automaticament, pero poden editar-se en casos puntuals;
- `A_PAGAR` pot ser editable en casos puntuals, pero amb SIF ha de portar motiu i impacte fiscal;
- la URL de pagament de dades de pagament s'ha de moure a `pay.prisma.cat`;
- si hi ha part pagada i el canvi es a curs mes barat, Adam fa el retorn manual i la rectificativa es tramita des de `Consulta - Edita - Anula factura`;
- les despeses de gestio actualment estan dins l'import final, pero en el SIF convé convertir-les en linia explicita quan generin factura nova o diferencia.

Documents actualitzats:

- `documentacio/03-canvis-pendents/07-pantalles-intranet.md`
- `documentacio/03-canvis-pendents/10-procediments-intranet-ecommerce.md`
- `documentacio/04-estat-final/16-estat-final-pantalles.md`
- `documentacio/05-governanca-operacio/21-seguretat-permisos-accessos.md`
- `documentacio/05-governanca-operacio/22-manual-operatiu-intern.md`
- `documentacio/00-index-i-pla/26-matriu-cobertura-casos.md`
- `documentacio/00-index-i-pla/27-informe-auditoria-documental.md`

#### Subbloc especialitzat: Passar pagaments / Analitzar fitxer TPV

Estat: incorporat com a subbloc especialitzat d'intranet; queda obert per revisar cossos reals de cerca/modal info, implementar endpoint/API SIF, executar proves i afegir captures finals.

Informacio concreta recuperada i consolidada:

- la pantalla combina `ANALITZA FITXER` i `CERCA`, pero el flux final ha de separar analisi TPV, incidencia, emissio i registre de pagament;
- el JS de cerca exigeix exactament un criteri: `NIF/NIE`, `CODI REGAL` o `NUM FACTURA`;
- el selector d'inscripcio envia `I` per alumne i `G` per grup;
- `#formTPV` envia `fitxer-tpv` per `POST` amb `FormData`;
- `analitzarFitxerTPV.php` retorna `state = 1`, `state = 2` amb `registresPagErrors`, o `state = 0`;
- les incidencies TPV poden obrir alumne o factura amb enllacos interns;
- `efectuarPagament.php` rep import, data, banc, observacions, factura i `efact`;
- `mostrarModalConfPag.php` avisa que s'actualitzara la factura, pero en SIF final s'ha de substituir per registre de cobrament contra factura existent;
- `efact` es ambigu i no s'ha de confondre amb la marca fiscal `E_FACT`;
- si hi ha factura SIF existent o factura abans de cobrament, el flux final es `registerPayment()`;
- si no hi ha factura i la venda es facturable, el flux final es `issueInvoice()` + `registerPayment()`;
- el TPV final ha de conservar auditoria de fitxer/linia, hash o resum, usuari, idempotencia i incidencies;
- `fitxers/analisis-fitxer.txt` no pot ser l'unica evidencia del darrer analisi;
- els pagaments manuals o transferencies han de validar permisos al servidor i no enviar dades critiques per `GET`.

Documents actualitzats:

- `documentacio/03-canvis-pendents/07-pantalles-intranet.md`
- `documentacio/03-canvis-pendents/10-procediments-intranet-ecommerce.md`
- `documentacio/04-estat-final/16-estat-final-pantalles.md`
- `documentacio/05-governanca-operacio/20-pla-proves-validacio-sif.md`
- `documentacio/05-governanca-operacio/21-seguretat-permisos-accessos.md`
- `documentacio/05-governanca-operacio/22-manual-operatiu-intern.md`
- `documentacio/00-index-i-pla/26-matriu-cobertura-casos.md`
- `documentacio/00-index-i-pla/27-informe-auditoria-documental.md`

#### Subbloc especialitzat: Generar factura abans de pagar

Estat: incorporat com a subbloc especialitzat d'intranet; queda obert per implementar `issueInvoice()`, revisar JS/AJAX final, executar proves i afegir captures finals.

Informacio concreta recuperada i consolidada:

- la pantalla actual cerca per `NIF/NIE` i selecciona inscripcions candidates amb el boto `+`;
- el JS copia cel·les HTML de la taula de resultats a la taula d'inscripcions relacionades;
- el sistema evita afegir dues vegades la mateixa fila canviant classes del boto, pero el servidor ho ha de validar igualment;
- `idsInsc` es construeix amb `push()` en continuar al pas 2 i pot duplicar IDs si es repeteix el pas sense reinicialitzar;
- la pantalla exigeix mateix curs i mateixa edicio, pero aquesta validacio es client i s'ha de repetir al servidor;
- `preuTotal`, `concepte1`, `concepte2`, `cursos` i `edicions` es calculen al navegador i no poden ser font fiscal unica;
- `concepte2` depen de `calcularTextData.php` i d'una crida asincrona;
- `generaFacturaElectronica_Factures.php` rep `empresa`, `concepte1`, `concepte2`, `preu`, `cursos`, `edicions`, `inscripcions` i `observacions`;
- `empresa` es text visible en el JS actual i s'ha de convertir en ID intern i snapshot fiscal del receptor;
- el nom `generarFacturaElectronica_Alumnes` no significa `E_FACT = 1`; aquest flux crea factura abans de cobrament;
- la factura final ha de tenir `EMESA_ABANS_COBRAMENT = 1` i `E_FACT = 0` per defecte;
- `descarregaFactura.php` regenera el PDF amb `generaFactura($id, true)` i `eliminarArxiu.php` fa `unlink($filename)` amb `filename` rebut per `GET`;
- en el SIF final, el PDF/QR ha de sortir de `factura_documents` i el pagament posterior ha de ser `registerPayment()`.

Documents actualitzats:

- `documentacio/03-canvis-pendents/07-pantalles-intranet.md`
- `documentacio/03-canvis-pendents/10-procediments-intranet-ecommerce.md`
- `documentacio/04-estat-final/16-estat-final-pantalles.md`
- `documentacio/05-governanca-operacio/20-pla-proves-validacio-sif.md`
- `documentacio/05-governanca-operacio/21-seguretat-permisos-accessos.md`
- `documentacio/05-governanca-operacio/22-manual-operatiu-intern.md`
- `documentacio/00-index-i-pla/26-matriu-cobertura-casos.md`
- `documentacio/00-index-i-pla/27-informe-auditoria-documental.md`

#### Subbloc especialitzat: Consulta - Edita - Anula factura

Estat: incorporat com a subbloc especialitzat d'intranet; queda obert per implementar rectificatives SIF, cataleg de motius, bloqueig d'edicio directa, proves i captures finals.

Informacio concreta recuperada i consolidada:

- la pantalla actual permet cercar per `NIF/NIE`, email, factura relacionada i numero de factura;
- `guardarDadesFactura_Factures()` crida `updDadesFact`, modifica directament `web.factures` i retorna `OK` sense motiu fiscal ni historial;
- l'edicio directa inclou receptor, CIF, adreca, CP, poblacio, conceptes i observacions;
- `modalAnularFactura_Factures($id)` omple `A TORNAR` amb l'import de la factura i `DATA DEVOLUCIO` amb la data actual;
- el codi antic contenia un avis comentat per factures amb diverses inscripcions relacionades, que indicava ajust manual de pagaments i observacions;
- `anularFactura($idFact, $tornar, $dataDevol, $obsDev)` crea una factura historica `R{any}/{ordre}` amb import negatiu, dades fiscals copiades i la mateixa `factura_relacionada`;
- despres de crear la factura `R`, el flux antic actualitza resums d'inscripcio amb `updInscAnulFact`, `updInscAnulFact2`, `updInscDataPagAnulFact`, `updInscFraccAnulFact` i `updObsFact`;
- la classe JS `.confirma-baixa` s'usa per anul·lar factura i no ha de condicionar noms o permisos finals;
- `E_FACT` es gestiona en aquesta pantalla, pero ha de ser accio administrativa separada i no clonacio automatica sense criteri;
- el PDF antic pot ser consultat, pero el SIF final ha de llegir PDF/QR de `factura_documents`.

Documents actualitzats:

- `documentacio/03-canvis-pendents/07-pantalles-intranet.md`
- `documentacio/03-canvis-pendents/10-procediments-intranet-ecommerce.md`
- `documentacio/04-estat-final/16-estat-final-pantalles.md`
- `documentacio/05-governanca-operacio/20-pla-proves-validacio-sif.md`
- `documentacio/05-governanca-operacio/21-seguretat-permisos-accessos.md`
- `documentacio/05-governanca-operacio/22-manual-operatiu-intern.md`
- `documentacio/00-index-i-pla/26-matriu-cobertura-casos.md`
- `documentacio/00-index-i-pla/27-informe-auditoria-documental.md`

#### Subbloc especialitzat: Intranet alumne, empresa/responsable i acces VERI*FACTU

Estat: incorporat com a subbloc especialitzat d'intranet; queda obert per implementar consulta externa, enllacos segurs, endpoint de documents, proves i captures finals.

Informacio concreta recuperada i consolidada:

- la visibilitat de factures a la intranet de l'alumne i empresa/responsable es un flux nou; el xat antic deia que ara no es veu;
- una factura pagada per l'alumne pot ser visible a l'alumne;
- una factura pagada per empresa, grup o responsable no ha de ser visible automaticament als participants;
- si una empresa paga un grup, cada participant no pot veure la factura completa amb tots els participants;
- nomes l'empresa o responsable autoritzat pot veure la factura d'empresa/grup;
- el PDF exacte generat en emissio s'ha de conservar en un espai no public de `pay.prisma.cat`;
- la intranet o l'enllac segur han de servir el PDF/QR amb permisos, no donar la ruta directa;
- `factura_documents` controla document, hash, estat de generacio i enviament;
- si el PDF/QR esta pendent o ha fallat, es mostra estat o incidencia, no es regenera des de dades vives;
- l'apartat `VERI*FACTU` de la intranet principal ha de mostrar indicador/resum i enllacos, pero la resolucio oficial viu a `pay.prisma.cat/sif`.

Documents actualitzats:

- `documentacio/03-canvis-pendents/07-pantalles-intranet.md`
- `documentacio/03-canvis-pendents/10-procediments-intranet-ecommerce.md`
- `documentacio/04-estat-final/16-estat-final-pantalles.md`
- `documentacio/05-governanca-operacio/20-pla-proves-validacio-sif.md`
- `documentacio/05-governanca-operacio/21-seguretat-permisos-accessos.md`
- `documentacio/05-governanca-operacio/22-manual-operatiu-intern.md`
- `documentacio/00-index-i-pla/26-matriu-cobertura-casos.md`
- `documentacio/00-index-i-pla/27-informe-auditoria-documental.md`

### Correus, plantilles, PDF i notificacions

Estat: incorporat parcialment al bloc 7; queda obert el mapa detallat de cada correu per pantalla/metode quan es revisin apartats concrets de codi.

Que cal buscar:

- correus que s'envien quan hi ha pagament, factura, regal, pack, empresa, morositat o reclamacio;
- si s'adjunta PDF o es fa servir enllac segur;
- textos de factura o de pagament que poden quedar obsolets amb VERI*FACTU;
- moment exacte en que es pot enviar el correu de factura definitiva;
- relacio entre PDF, QR i estat SIF.

Documents relacionats:

- `documentacio/03-canvis-pendents/08-correus-i-plantilles.md`
- `documentacio/03-canvis-pendents/11-inventari-canvis-pendents.md`
- `documentacio/04-estat-final/16-estat-final-pantalles.md`
- `documentacio/04-estat-final/25-panell-sif-pay-prisma.md`

Informacio trobada i incorporada:

- els correus actuals no surten tots de `Template`: hi ha `Template`, correus directes en PHP i correus tecnics o futurs del SIF;
- `realitzaPagamentAutomatic.php` envia un correu intern de "pagament automatic" amb DNI, import, fraccio, `IDPAG` i `ORDER`, pero aixo es diagnosi interna i no prova que la factura SIF estigui emesa;
- les plantilles de reclamacio i morositat inclouen casos de baixa, certificat, responsable d'entitat, deutes antics i imports reservats;
- s'han detectat metodes addicionals de comunicats: `getTemplate_Comunicat_aPuntComencar`, `getTemplate_Comunicat_AulesObertes`, `getTemplate_Comunicat_IA`, `getTemplate_Comunicat_OberturaAules` i `getTemplate_Comunicat_ServeiAtencio`;
- `[DESPESES_GESTIO]` i `[PAGAMENT]` apareixen dins plantilles i s'han de confirmar dins l'esquema intern de placeholders;
- qualsevol correu amb `[URL_PAGAMENT]` o URL construida manualment queda dins l'abast de migracio a `pay.prisma.cat`;
- una factura d'empresa/responsable pendent pot tenir URL de pagament, pero ha de ser la URL d'empresa/responsable i no la individual de l'alumne;
- si ja existeix factura, el pagament posterior ha d'anar a `registerPayment()`, no a una nova factura duplicada;
- el PDF de factura nova ha de sortir de `factura_documents`, amb hash, i no de regeneracio lliure sobre dades vives;
- el PDF/QR pot anar en cua; si falla, es crea incidencia SIF i no es desfà la factura;
- l'enllac segur ha de consultar el SIF i permisos, sense exposar rutes internes;
- nomenclatura acordada: `incidencia SIF`, `notificacio`, `avis` i `indicador`; evitar `comptador`.

Documents actualitzats:

- `documentacio/03-canvis-pendents/08-correus-i-plantilles.md`
- `documentacio/03-canvis-pendents/11-inventari-canvis-pendents.md`
- `documentacio/04-estat-final/16-estat-final-pantalles.md`
- `documentacio/04-estat-final/25-panell-sif-pay-prisma.md`

### Proves, produccio i governanca

Estat: incorporat parcialment al bloc 8; queda obert per executar proves reals, configurar certificat/apoderament, desplegar preproduccio i tancar paquet go/no-go quan hi hagi codi.

Que cal buscar:

- proves comentades al xat antic;
- pilot o ordre de posada en produccio;
- migracio de numeracio o historics;
- certificat digital i entorn de proves;
- evidencies a conservar;
- criteris per considerar una area tancada.

Documents relacionats:

- `documentacio/03-canvis-pendents/09-checklist-posada-en-produccio.md`
- `documentacio/05-governanca-operacio/20-pla-proves-validacio-sif.md`
- `documentacio/00-index-i-pla/26-matriu-cobertura-casos.md`
- `documentacio/00-index-i-pla/27-informe-auditoria-documental.md`

Informacio trobada i incorporada:

- abans de produccio cal un periode de proves/preproduccio;
- el SIF central ha de concentrar numeracio, hash chain, cua AEAT i reintents;
- si PDF, correu o AEAT fallen despres d'emetre factura, no es desfà la factura: es crea incidencia i retry;
- cal provar que accions antigues no modifiquen factures emeses tocant `A_PAGAR`, movent pagaments o editant receptor/import/concepte;
- cap script antic, cron, importador, Moodle o API externa ha de crear o modificar factura fiscal sense passar pel SIF;
- les proves han de separar BD o mode test, numeracio de prova, Redsys test/simulador i AEAT test si correspon;
- les factures de prova han de quedar clarament separades de la numeracio fiscal productiva;
- cal conservar evidencia de versio, declaracio, proves, captures, permisos, exportacions, logs, PDF/XML/QR, hash, backups i restauracio;
- el panell `pay.prisma.cat/sif` ha de ser font oficial de versio, declaracio, documents, registres, logs, incidencies i evidencies;
- la planificacio interna realista es de 4 a 8 mesos, amb 6 mesos plausible si hi ha treball sostingut;
- el ritme de treball ha de comptar amb uns 3 dies reals/setmana per VERI*FACTU i un dijous enfocat a tancament/QA si es fa servir aquest calendari;
- Meriem concentra el desenvolupament fiscal/SIF; suport extern pot ajudar en Moodle, incidencies o tasques PHP/JS senzilles, pero no substituir la responsabilitat de l'arquitectura fiscal.

Documents actualitzats:

- `documentacio/03-canvis-pendents/09-checklist-posada-en-produccio.md`
- `documentacio/05-governanca-operacio/20-pla-proves-validacio-sif.md`
- `documentacio/05-governanca-operacio/19-registre-versions-i-canvis-sif.md`
- `documentacio/00-index-i-pla/14-pla-documentacio-i-auditoria.md`
- `documentacio/00-index-i-pla/26-matriu-cobertura-casos.md`
- `documentacio/00-index-i-pla/27-informe-auditoria-documental.md`

#### Bloc especialitzat: USOC

Estat: incorporat parcialment al bloc especialitzat; queda obert per implementacio, dades fiscals completes d'USOC, proves reals i captures finals.

Que cal buscar:

- canal TPV `curs afiliat d'USOC`;
- `TIPUS_DESC = 4`, `VALID_DESC` i validacio manual;
- import que paga l'alumne i import/diferencia que paga USOC;
- textos de concepte i correu associats;
- pantalla/metodes de validar descomptes;
- cas especial `Altres: Curs gratüit USOC` i `anticipi-preu-usoc`;
- relacio entre factura alumne, factura USOC i inscripcio.

Documents relacionats:

- `documentacio/03-canvis-pendents/04-fluxos-facturacio.md`
- `documentacio/03-canvis-pendents/06-integracio-redsys-pay-prisma.md`
- `documentacio/03-canvis-pendents/10-procediments-intranet-ecommerce.md`
- `documentacio/05-governanca-operacio/20-pla-proves-validacio-sif.md`
- `documentacio/00-index-i-pla/26-matriu-cobertura-casos.md`
- `documentacio/00-index-i-pla/27-informe-auditoria-documental.md`

Informacio trobada i incorporada:

- `curs afiliat d'USOC` es un canal/URL TPV propi;
- `TIPUS_DESC = 4` identifica `Afiliat USOC`;
- `VALID_DESC` diferencia pendent de validar, validat valid i validat no valid;
- el descompte USOC recuperat es del 25%;
- la validacio es manual a l'apartat `validar descomptes` de la intranet, despres de confirmar afiliacio amb USOC;
- el cas confirmat fiscalment es de dues factures: alumne paga la seva part i rep factura; USOC paga la diferencia i rep factura;
- el cas habitual recuperat indicava primer pagament de l'alumne de 10 euros i segon pagament de la diferencia per USOC;
- el concepte historic podia incloure `El pagament de la diferencia el realitza l'entitat USOC`;
- `cnsAlumnDescNoValidat` localitza inscripcions amb descompte pendent;
- `__mostrarPage_Inici_ValidarDescomptes` i `__mostrarPage_Alumnes_ValidarDescomptes` controlen la pantalla/llista de validacio;
- `updValidDescByInsc` i `updValidDescByInscPreu` actualitzen validacio i poden recalcular `A_PAGAR`;
- els missatges historics informen l'alumne si USOC confirma l'afiliacio o si no consta;
- `Altres: Curs gratüit USOC` i `anticipi-preu-usoc` queden marcats com a cas especial pendent de decisio final.

Documents actualitzats:

- `documentacio/03-canvis-pendents/04-fluxos-facturacio.md`
- `documentacio/03-canvis-pendents/06-integracio-redsys-pay-prisma.md`
- `documentacio/03-canvis-pendents/10-procediments-intranet-ecommerce.md`
- `documentacio/05-governanca-operacio/20-pla-proves-validacio-sif.md`
- `documentacio/00-index-i-pla/26-matriu-cobertura-casos.md`
- `documentacio/00-index-i-pla/27-informe-auditoria-documental.md`
