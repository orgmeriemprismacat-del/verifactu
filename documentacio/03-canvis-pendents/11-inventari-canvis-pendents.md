# 11 - Inventari de canvis pendents VERI*FACTU

> Document viu. Recull tots els canvis detectats fins ara, incloent canvis funcionals, tecnics, de BD, de permisos, de pantalles, de correus i de fluxos.

## 1. Factures abans de pagar i responsables de grup

Problema:

- quan es genera una factura abans de pagar a nom d'una empresa/responsable, posteriorment es pot generar una altra factura en passar el pagament;
- passa especialment si el responsable posa el CIF de l'empresa com a DNI/contacte;
- es pot acabar amb dues factures per la mateixa operacio.

Canvi necessari:

- detectar factura previa real abans d'emetre una nova;
- si hi ha factura previa, registrar cobrament amb `registerPayment()`;
- desactivar enllacos de pagament individuals;
- tractar-ho com a factura real abans de cobrament, no com a proforma.

## 2. Anulacio d'enllac de pagament per factura abans de pagar

Quan una inscripcio queda inclosa en una factura abans de pagar:

- desactivar enllac TPV;
- mostrar missatge que pagara l'entitat/responsable;
- evitar que l'alumne pugui pagar individualment i generar duplicat.

## 3. Pagament posterior d'inscripcions incloses en factura abans de pagar

Quan es registra el pagament:

- no crear factura nova;
- registrar pagament contra factura existent;
- actualitzar estat cobrament.

## 4. Desactivacio URL TPV

Cal:

- trobar logica actual de generacio d'URL;
- afegir estat d'enllac actiu/inactiu;
- afegir motiu d'inactivacio;
- moure URL a `pay.prisma.cat`;
- mostrar missatge adequat si l'enllac esta desactivat;
- si el pagament ja esta fet, mostrar acces a consultar factura/PDF/QR en lloc d'insistir en pagar.

## 5. Analitzar fitxer TPV

Cal adaptar l'apartat a:

- SIF;
- idempotencia;
- `DS_ORDER`;
- pagaments sense factura;
- factures sense pagament;
- duplicats;
- incidencies.

## 6. Pas de dades de facturacio

Afegir a cada tipus d'inscripcio/pagament:

- pas de confirmacio de dades fiscals;
- per defecte, dades del client;
- boto per canviar dades de facturacio;
- guardar snapshot fiscal abans d'emetre factura.

## 7. Descarrega de factures i registres

Afegir apartat per:

- descarregar factures per mes;
- descarregar/exportar registres fiscals;
- consultar registres enviats/pendents/error;
- facilitar inspeccio o revisio interna.

## 8. Factures manuals

Afegir apartat per:

- generar factura manual;
- escollir entitat existent;
- introduir dades fiscals manualment;
- crear linies;
- cridar SIF;
- enviar correus.

Criteri tancat:

- una factura manual no s'insereix directament a `web.factures`;
- sempre passa per `issueInvoice()`;
- si neix cobrada, el payload inclou `payment`;
- si neix pendent, el cobrament posterior va per `registerPayment()`;
- ha de conservar usuari intern, snapshot fiscal, linies i relacio amb entitat/responsable si n'hi ha.

Estat tecnic 2026-06-14:

- circuit CLI preparat amb `ManualInvoicePayloadBuilder`, `ManualInvoiceService`, `preview-manual-invoice.php` i `process-manual-invoice.php`;
- el processador rebutja produccio, no sincronitza legacy i no crida `registerPayment()` directament per al pagament inicial;
- pendent d'executar amb PHP/MySQL de test, pantalla final, permisos, correus o enllac segur i evidencies.

## 8.1. Entitats i responsables d'entitat

L'apartat actual:

```text
https://intranet.prisma.cat/alumnes/genera-entitat/
```

serveix per crear i editar entitats i la persona responsable de la seva gestio.

Canvi necessari:

- validar i normalitzar CIF, rao, adreca, CP i poblacio;
- validar correu del responsable;
- evitar o avisar duplicats per CIF;
- guardar historial de canvis si les dades poden haver estat utilitzades en factures;
- en emetre factura, copiar snapshot fiscal al SIF;
- no usar l'edicio d'entitat per modificar factures ja emeses;
- distingir responsable de contacte i receptor fiscal.

## 9. Correus amb factura

Cada generacio de factura ha de enviar correus segons cas:

- client/alumne;
- empresa/responsable;
- participant, si correspon;
- gestio interna.

Decisio:

- adjuntar PDF nomes si el document ja existeix a `factura_documents` i correspon exactament a la factura emesa;
- enviar enllac segur quan calgui control de permisos, consulta posterior o quan el PDF/QR encara estigui en cua;
- no enviar correu de factura emesa abans que el SIF retorni `UUID_FACTURA` i `NUM_VISIBLE`;
- si el PDF/QR falla, crear incidencia SIF i no desfer la factura.

El correu de factura ha de diferenciar si comunica:

- factura emesa i document disponible;
- factura emesa amb document pendent;
- factura abans de cobrament;
- pagament pendent;
- incidencia fiscal o documental.

## 9.1. Correus amb enllac de pagament

Qualsevol correu que tingui enllac de pagament s'ha de modificar dins l'adaptacio VERI*FACTU/pay.prisma.cat.

Afecta:

- correus amb placeholder `[URL_PAGAMENT]`;
- correus amb URL de pagament construida directament dins el PHP;
- reclamacions;
- morositat;
- recordatoris de pagament;
- factures abans de cobrament;
- factures d'empresa/responsable amb URL propia;
- diferencies per canvi de curs;
- pagaments fraccionats.

Canvi necessari:

- substituir URL antiga per URL controlada de `pay.prisma.cat`;
- tipificar el tipus d'URL: individu, pack, grup, regal, empresa, USOC, diferencia, reclamacio o morositat;
- mantenir URL propia d'empresa/responsable quan una factura d'empresa pendent de cobrament s'ha de pagar per aquest canal;
- desactivar o substituir la URL individual si la inscripcio esta coberta per factura d'empresa/responsable;
- assegurar que el pagament posterior crida `registerPayment()` si la factura ja existeix;
- assegurar que no es genera factura duplicada des d'un correu/reintent;
- afegir opcio de veure factura/PDF/QR quan el destinatari ja ha pagat o quan la factura ja consta com emesa;
- revisar el text del correu per evitar que una persona que ja ha pagat torni a clicar una URL de pagament;
- si es reprograma l'apartat, moure el correu a `Template`.

Correus tecnics:

- el correu intern de `realitzaPagamentAutomatic.php` amb DNI, import, fraccio, `IDPAG` i `ORDER` es diagnosi interna;
- no prova que la factura fiscal estigui emesa;
- en el flux final ha de quedar com a log/notificacio o enviar-se nomes despres de validar Redsys i registrar l'estat real al SIF.

## 9.2. Pagaments de reclamacio i morositat

Criteri tancat:

- la morositat no es una baixa ni una rectificativa automatica;
- la factura original continua vigent;
- si la reclamacio acaba en cobrament, el SIF ha de registrar un pagament contra la factura existent;
- no s'emet factura nova i no es modifica la factura reclamada.

Estat tecnic 2026-06-14:

- circuit CLI preparat amb `ClaimPaymentPayloadBuilder`, `ClaimPaymentService`, `preflight-claim-payment.php`, `preview-claim-payment.php` i `process-claim-payment.php`;
- el flux localitza factura per `UUID_FACTURA` o `NUM_VISIBLE`;
- registra `payment_transaction` `CHARGE` i `payment_allocation` `CLAIM_PAYMENT`;
- usa idempotencia `CLAIM|REF:{REFERENCIA}` o fallback per factura/data/import/usuari quan no hi ha referencia;
- pendent d'integrar pantalla/URL/correus finals de reclamacio, permisos i evidencies amb PHP/MySQL de test.

## 9.3. Grups amb cobrament Redsys o manual

Criteri tancat:

- una factura de grup es una factura unica per pagament real;
- te una linia per participant;
- el receptor fiscal ve de `respGrups`;
- la factura completa no es visible per defecte a cada alumne participant.

Estat tecnic 2026-06-14:

- snapshot/payload de grup preparat amb `LegacyGroupSnapshotRepository` i `LegacyGroupInvoicePayloadBuilder`;
- circuit Redsys manual de preproduccio preparat amb `RedsysGroupInvoiceService`, `preflight-redsys-group.php`, `preview-redsys-group.php` i `process-redsys-group.php`;
- circuit manual de `Passar pagaments` preparat amb `ManualGroupInvoicePayloadBuilder`, `ManualGroupInvoiceService`, `preflight-manual-group.php`, `preview-manual-group.php` i `process-manual-group.php`;
- els processadors poden fer `--sync-legacy` nomes despres d'exit SIF;
- pendent d'executar amb PHP/MySQL de test, validar `descomptes_grup` abans d'activacio real i preparar captures/proves de privacitat.

## 10. Pagaments fraccionats i recordatoris

Hi ha molts apartats que mostren:

- pagat fins ara;
- pendent;
- data limit;
- URL de pagament.

Cal adaptar-los per mostrar:

- factures associades;
- estat cobrament;
- estat AEAT;
- enllac actiu/inactiu;
- saldo/compensacio si existeix.

Criteri tancat:

- cada fraccio es `payment_transaction`;
- cada assignacio parcial es `payment_allocation`;
- el mateix `IDPAG` pot tenir diversos intents Redsys i diversos `DS_ORDER`;
- la deduplicacio Redsys es fa per `DS_ORDER`;
- `FRACCIO` i camps antics nomes queden com a resum operatiu sincronitzat.

Estat tecnic 2026-06-14:

- circuit manual preparat amb `ManualInstallmentPaymentPayloadBuilder`, `ManualInstallmentPaymentService`, `preview-manual-installment.php` i `process-manual-installment.php`;
- la fraccio manual entra com `registerPayment()` amb assignacio `INSTALLMENT_PAYMENT`;
- pendent d'executar amb PHP/MySQL de test i integrar amb pantalla/URL final.

## 11. Optimitzacio BD

Tasques:

- revisar indexos;
- mantenir historics fora de taules actives;
- separar BD fiscal de BD intranet;
- evitar consultes lentes en pantalles de gestio;
- garantir InnoDB en taules fiscals;
- revisar permisos MySQL.

## 12. Informacio de l'alumne

Cal adaptar:

- icones de factura;
- estat factura;
- estat cobrament;
- estat AEAT;
- visibilitat de factura d'empresa/grup;
- dades de pagament;
- URL TPV;
- PDFs.

## 13. Veure factura

Cal modificar:

- visualitzacio de factura;
- mostrar VERI*FACTU / no VERI*FACTU;
- mostrar QR/PDF immutable;
- mostrar rectificatives;
- no permetre edicio de factura emesa.

## 14. Canvis de curs i pagament per diferencia

Cal:

- guardar historic de canvis;
- detectar si el pagament correspon a diferencia per canvi de curs;
- recalcular descompte;
- registrar despeses de gestio;
- generar factura/rectificativa/compensacio segons cas.

Criteri tancat:

- si no hi ha factura emesa, es pot ajustar la inscripcio amb historic i log;
- si hi ha factura emesa i canvia servei, import, concepte o descompte, cal rectificativa o factura complementaria;
- si el nou curs es mes car, la diferencia pendent es paga amb URL controlada i `SOURCE_TYPE = CANVI_CURS_DIFERENCIA`;
- si el nou curs es mes barat, cal decisio de client: retorn, saldo o no retorn justificat.

## 15. Canvi de curs amb curs mes car o mes barat

Casos:

- nou curs mes car: diferencia pendent;
- nou curs mes barat: retorn o saldo;
- pagaments traslladats;
- mes d'un canvi consecutiu;
- descompte reaplicat o recalculat.

Criteri tancat:

- el primer canvi pot ser gratuit si la politica interna ho permet;
- canvis posteriors poden generar despeses de gestio;
- el descompte original s'intenta reaplicar i, si no correspon, es recalcula;
- qualsevol rebaixa excepcional ha de tenir motiu intern i descompte/ajust documentat.

## 16. Descompte excepcional per canvi de curs

Problema actual:

- es modifica `A_PAGAR` directament.

Canvi necessari:

- crear descompte/ajust documentat;
- guardar motiu intern;
- mostrar text visible generic si cal;
- evitar modificar nomes import final sense rastre.

Criteri tancat:

- el descompte excepcional per canvi de curs no pot ser nomes una modificacio d'`A_PAGAR`;
- ha de quedar congelat a linia o relacio d'ajust amb origen `DESCOMPTE_INCIDENCIA`, `DESCOMPTE_COMERCIAL` o `AJUST_MANUAL`;
- si s'aplica despres d'una factura emesa, genera rectificativa quan redueix el que ja estava facturat.

## 17. Anulacio/baixa de curs

Flux nou:

- baixa de curs marca inscripcio;
- no toca pagament al moment;
- client decideix retorn/saldo/no retorn;
- Adam/Pablo registren devolucio o saldo;
- rectificativa quan pertoqui.

Criteri tancat:

- la baixa es un event sobre inscripcio i no genera rectificativa automatica;
- la devolucio es `payment_transaction` de tipus `REFUND`;
- el saldo es `credit_balance` i quan s'usa es `COMPENSATION`;
- si devolucio o saldo redueixen una factura emesa, cal rectificativa vinculada;
- els saldos per baixa no caduquen automaticament, pero secretaria pot revisar saldos molt antics.

Estat tecnic 2026-06-14:

- baixa i canvi de curs queden com decisions administratives que deriven a devolucio, saldo/compensacio, diferencia pendent o rectificativa segons factura existent i import final;
- el circuit de devolucio manual ja esta preparat amb `ManualRefundPayloadBuilder`, `ManualRefundService`, `preview-manual-refund.php` i `process-manual-refund.php`;
- el circuit de rectificativa manual ja esta preparat amb `ManualRectificationPayloadBuilder`, `RectificationRepository`, `ManualRectificationService`, `preview-manual-rectification.php` i `process-manual-rectification.php`;
- pendent d'executar amb PHP/MySQL de test i validar operativament els motius interns.

## 17.1. Compensacio, saldo i devolucions

Criteri tancat:

- compensacio/saldo no son edicions d'import;
- si existeixen abans d'emetre, es reflecteixen en linies/descomptes congelats;
- si neixen despres d'emetre i redueixen servei o import, cal rectificativa;
- una devolucio total o parcial s'ha de registrar com moviment economic i vincular amb factura original, baixa/canvi si aplica i rectificativa;
- un pagament duplicat no crea factura nova: genera devolucio, saldo o incidencia segons decisio interna.

Estat tecnic 2026-06-14:

- circuit de saldo/compensacio preparat amb `CreditBalancePayloadBuilder`, `CreditBalanceRepository`, `CreditBalanceService`, `preview-credit-balance.php`, `process-credit-balance.php`, `preview-credit-compensation.php` i `process-credit-compensation.php`;
- crear saldo no crea factura ni pagament; aplicar-lo a factura existent crea `payment_transaction` `COMPENSATION` i `payment_allocation` `CREDIT_COMPENSATION`;
- pendent d'executar amb PHP/MySQL de test i evidencies d'estat `ACTIVE`/`USED`.

## 18. Migracio a pay.prisma.cat

Cal traspassar:

- confirmacio de pagament;
- callbacks Redsys;
- URL pagament;
- generacio/control de factures;
- PDF/QR;
- endpoints SIF.

També cal crear un panell intern del SIF a `pay.prisma.cat/sif` per:

- documentacio i declaracio responsable;
- versions;
- factures i registres AEAT;
- incidencies SIF;
- exports;
- configuracio.

La intranet principal tindra un apartat `VERI*FACTU` amb indicador visual de pendents, resum d'incidencies i accessos, pero no sera la font oficial fiscal.

## 19. PDFs immutables

Canvi necessari:

- deixar de regenerar PDF des de dades vives;
- generar PDF en emissio;
- guardar fitxer;
- guardar hash;
- servir amb permisos.

## 20. Permisos i bloquejos

Cal:

- bloquejar edicio directa de factures emeses a app;
- restringir permisos MySQL;
- evitar que altres persones modifiquin BD fiscal manualment;
- documentar qui pot fer rectificatives.

## 21. Notificacions fiscals

Cal implementar:

- notificacio per error AEAT;
- notificacio per retries fallits;
- notificacio per factura `FAILED`;
- notificacio per anomalies de pagament/factura;
- indicador visual de pendents a l'apartat `VERI*FACTU` de la intranet;
- resum d'incidencies pendents a la intranet;
- gestio oficial de la incidencia al panell SIF.

Nomenclatura:

- `incidencia SIF`: problema real registrat al SIF, amb estat, prioritat, responsable i log.
- `notificacio`: avis persistent guardat per mostrar a usuaris interns.
- `avis`: text puntual dins una pantalla o correu.
- `indicador`: marca visual de pendents a l'apartat `VERI*FACTU`.

Evitar `comptador` per a aquests avisos, per no confondre'l amb numeracio fiscal o series.

## 22. Declaracio responsable i documentacio

Cal mantenir:

- document intern del projecte;
- document funcionament SIF AEAT;
- declaracio responsable;
- fluxos;
- model BD;
- integracio Redsys;
- pantalles intranet;
- correus;
- checklist produccio.

## 23. Migracio de factures historiques

Decisio:

- migrar `web.factures` al SIF;
- marcar-les com a historic no VERI*FACTU;
- conservar numeracio original;
- conservar relacio amb `FACTURA_RELACIONADA`;
- vincular-les a `fact_rels`;
- no generar registres VERI*FACTU retroactivament.

Criteri tancat:

- la migracio no entra a la hash chain nova;
- les consultes han d'indicar clarament `VERIFACTU` o `NO_VERIFACTU`;
- una rectificativa nova sobre factura historica, si cal, es crea com operacio SIF nova amb referencia a l'historic;
- la migracio ha d'incloure control de totals per any/serie, numeracio, imports i incidencies.

Estat tecnic 2026-06-14:

- circuit CLI preparat amb `HistoricalInvoicePayloadBuilder`, `HistoricalInvoiceMigrationRepository`, `HistoricalInvoiceMigrationService`, `preview-historical-invoice-migration.php` i `process-historical-invoice-migration.php`;
- conserva `NUM_VISIBLE`, serie/any/numero, receptor, totals, linies, relacio `HISTORIC_LINK` i document antic opcional amb hash;
- marca la factura com `NO_VERIFACTU` i no crea `factura_registres`, `fiscal_queue`, `fiscal_sequence` ni canvi de hash chain;
- pendent d'executar amb PHP/MySQL de test i preparar informe agregat de control.

## 24. fact_rels

Crear nova taula `fact_rels` per:

- mantenir compatibilitat amb `FACTURA_RELACIONADA`;
- relacionar factures fiscals per `UUID_FACTURA`;
- gestionar rectificatives;
- gestionar factures de grup/empresa;
- controlar visibilitat a alumne.

## 25. PDF en cua

Decisio:

- `issueInvoice()` crea factura i registre fiscal;
- la generacio PDF/QR pot anar en cua o worker;
- el sistema retorna factura creada encara que el PDF es generi immediatament despres;
- si falla PDF, es crea incidencia, no es desfà la factura.

Impacte sobre correus i consultes:

- el correu amb adjunt PDF espera que `factura_documents` tingui el document correcte;
- si no es vol esperar, es pot enviar enllac segur a la consulta de factura amb estat de document pendent;
- l'enllac segur no exposa rutes internes ni fitxers publics sense control;
- el PDF/QR consultat per alumne, empresa o responsable ha de sortir del SIF i respectar permisos de visibilitat.

## 26. Crear panell intern del SIF a pay.prisma.cat/sif

Cal crear una petita intranet/panell intern del SIF a:

```text
pay.prisma.cat/sif
```

Aquest panell sera la font oficial de:

- factures;
- registres AEAT;
- documents fiscals;
- declaracio responsable;
- versions;
- incidencies SIF;
- exportacions;
- configuracio tecnica.

Apartats a crear:

- Dashboard;
- Factures;
- Registres AEAT;
- Incidencies;
- Documents;
- Versions;
- Exportacions;
- Configuracio.

### 26.1. Dashboard

Crear pantalla resum amb:

- factures emeses avui/mes/any;
- registres AEAT pendents, acceptats, rebutjats o en retry;
- incidencies obertes per prioritat;
- PDF/QR pendents;
- ultima factura emesa;
- estat connexio AEAT;
- versio activa del SIF.

### 26.2. Factures

Crear pantalla de consulta de factures amb:

- cerca per numero, UUID, NIF/CIF, `FACTURA_RELACIONADA`;
- filtres per estat factura, estat AEAT i estat cobrament;
- linies de factura;
- pagaments associats;
- rectificatives;
- documents PDF/XML/QR;
- origen de la factura.

No permetre edicio directa de factura emesa.

### 26.3. Registres AEAT

Crear pantalla per:

- consultar registres fiscals;
- veure hash, hash anterior i fiscal order;
- veure estat AEAT;
- veure intents i proper retry;
- veure resposta/error AEAT;
- exportar registres;
- reintentar si el rol ho permet.

### 26.4. Incidencies

Crear pantalla oficial d'incidencies SIF:

- prioritat;
- estat;
- factura afectada;
- origen;
- error tecnic;
- responsable assignat;
- historial d'accions;
- resolucio.

La intranet principal només mostra resum/indicador; la resolucio oficial es fa aquí.

### 26.5. Documents

Crear pantalla de documents:

- PDF factura;
- XML si correspon;
- QR;
- declaracio responsable signada;
- documentacio tecnica;
- documents de versio;
- hash del fitxer;
- data de generacio.

### 26.6. Versions

Crear pantalla de versions:

- versio activa;
- data entrada produccio;
- responsable tecnic;
- responsable legal/direccio;
- declaracio responsable associada;
- historial de versions.

### 26.7. Exportacions

Crear pantalla per generar i conservar exports:

- factures per periode;
- registres AEAT;
- registre d'events;
- incidencies;
- documents vinculats;
- relacions factura-inscripcio/pagament.

Cada export ha de guardar:

- usuari;
- data;
- criteris;
- fitxer;
- hash;
- motiu si es per requeriment.

### 26.8. Configuracio

Crear pantalla restringida per:

- dades emissor fiscal;
- series actives;
- mode VERI*FACTU;
- endpoints AEAT;
- certificat digital/configuracio segura;
- retries;
- rutes de documents;
- permisos/rols;
- estat de workers.

Tots els canvis de configuracio han de generar log.

## 27. Crear apartat VERI*FACTU a la intranet principal

Cal crear un apartat unic a la intranet:

```text
VERI*FACTU + indicador visual de pendents
```

Aquest apartat ha de mostrar:

- boto per obrir `pay.prisma.cat/sif`;
- resum d'incidencies pendents;
- acces als documents SIF;
- acces a exportacions;
- estat general de cua AEAT si cal.

Regla:

```text
La intranet mostra resum i accessos.
El SIF conserva i gestiona la informacio fiscal oficial.
```

## 28. Com generar aquests apartats

Implementacio recomanada:

Cada pantalla nova no s'ha de quedar nomes amb el nom. Per cada apartat cal documentar, com a minim:

- URL i fitxer PHP o ruta del panell SIF;
- rols que hi poden entrar;
- camps de cerca i filtres;
- columnes de resultat;
- accions permeses;
- accions prohibides;
- endpoints o metodes que crida;
- taules principals;
- correus que pot enviar;
- errors que generen incidencia o notificacio;
- criteris de prova abans de donar-ho per tancat.

1. Crear estructura base de `pay.prisma.cat/sif`.
2. Crear autenticacio i rols del panell SIF.
3. Crear taules i endpoints SIF necessaris per dashboard, incidencies, documents, versions i exports.
4. Crear pantalla Dashboard.
5. Crear pantalla Factures.
6. Crear pantalla Registres AEAT.
7. Crear pantalla Incidencies.
8. Crear pantalla Documents.
9. Crear pantalla Versions.
10. Crear pantalla Exportacions.
11. Crear pantalla Configuracio.
12. Crear apartat `VERI*FACTU` a la intranet principal.
13. Connectar la intranet amb el SIF per mostrar indicador visual i resum d'incidencies.
14. Afegir permisos i rol auditor/AEAT nomes lectura.
15. Afegir proves al pla de validacio.
