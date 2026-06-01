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

### Base de dades i relacions

Estat: pendent

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

### Compliment AEAT i declaracio responsable

Estat: pendent

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

### Pantalles, permisos i operacio interna

Estat: pendent

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

### Correus, plantilles, PDF i notificacions

Estat: pendent

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

### Proves, produccio i governanca

Estat: pendent

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
