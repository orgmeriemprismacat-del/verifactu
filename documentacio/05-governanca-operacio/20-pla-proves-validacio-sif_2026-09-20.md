# 20 - Pla de proves i validacio del SIF

> Document per demostrar que el SIF funciona correctament abans de posar-lo en produccio i en cada canvi rellevant de versio.

## 1. Objectiu

Validar que el SIF garanteix:

- numeracio sense duplicats;
- idempotencia;
- integritat de hash chain;
- emissio correcta de factures;
- rectificatives;
- PDF/QR immutable;
- cua AEAT i reintents;
- notificacions d'errors;
- permisos i bloquejos;
- integracio Redsys, transferencia i compensacions.

## 1.1. Principis de prova

Les proves del SIF s'han de preparar com a casos executables i repetibles, no nomes com a llista d'intencions.

Principis:

- cap prova pot consumir numeracio fiscal productiva;
- les dades de prova han d'estar separades de les factures reals;
- si es fan servir dades reals, han d'estar anonimitzades o duplicades en un entorn controlat;
- Redsys s'ha de provar en mode test o amb simulador quan no es vulgui provocar cobrament real;
- l'entorn AEAT de prova s'ha d'utilitzar si esta disponible i correspon al tipus d'integracio;
- cada prova ha de generar evidencia conservable;
- una prova fallida no es corregeix manualment sense deixar incidencia i resultat.

## 2. Proves minimes

- curs individual Redsys;
- callback Redsys duplicat;
- pagament fraccionat;
- factura abans de cobrar i cobrament posterior;
- pack;
- grup;
- regal;
- USOC;
- canvi de curs amb diferencia;
- canvi de curs amb saldo;
- baixa amb devolucio;
- baixa amb saldo;
- rectificativa per canvi de dades fiscals;
- generacio PDF/QR;
- error AEAT i retry;
- error PDF;
- export de registres;
- acces al panell `pay.prisma.cat/sif`;
- consulta de declaracio responsable i versio activa;
- consulta d'incidencies SIF;
- consulta intranet alumne;
- consulta intranet empresa/responsable.

## 3. Evidencia de prova

Cada prova haura de guardar:

- data;
- versio;
- usuari/provador;
- dades d'entrada;
- resultat esperat;
- resultat obtingut;
- captura o log;
- incidencia si falla.

Fitxa minima per convertir una prova en executable:

| Camp | Contingut |
| --- | --- |
| ID prova | Identificador estable, per exemple `SIF-RED-001`. |
| Area | Redsys, factura, rectificativa, AEAT, permisos, PDF/QR, exportacio, etc. |
| Objectiu | Que es vol demostrar. |
| Dades entrada | Inscripcio, import, receptor, pagament, estat previ i usuari. |
| Passos | Accions concretes a executar. |
| Resultat esperat | Estat final de factura, pagament, documents, cua i logs. |
| Evidencia | Captura, export, log, hash, PDF, registre AEAT o incidencia. |
| Bloquejant | Si impedeix o no el pas a produccio. |

## 4. Escenari de proves abans de produccio

Abans de posar el SIF en produccio cal provar-lo en un escenari separat.

Objectiu:

```text
validar casos reals o equivalents sense contaminar numeracio, registres ni factures productives.
```

Opcions recomanades:

- BD fiscal de proves separada;
- configuracio SIF en mode proves;
- Redsys en mode test si s'utilitza en la prova;
- configuracio AEAT de proves si correspon;
- dades reals anonimitzades o duplicades en entorn controlat;
- numeracio de proves separada de la numeracio fiscal productiva;
- documents PDF/QR marcats com a prova;
- logs i captures conservats com a evidencia.

Regla:

```text
Cap prova pot crear factura productiva real ni consumir numeracio fiscal real.
```

## 5. Proves obligatories d'auditoria tecnica

### 5.1. Immutabilitat de factura

Prova:

```text
emetre factura
intentar modificar receptor/import/concepte des de app i BD amb usuari no autoritzat
verificar que no es pot modificar
generar rectificativa si cal canvi
```

Evidencia:

- factura original;
- intent bloquejat;
- log;
- rectificativa si correspon.

### 5.2. Idempotencia Redsys/retry

Prova:

```text
enviar dues vegades la mateixa peticio amb mateixa IDEMPOTENCY_KEY
verificar que retorna la mateixa factura
verificar que no augmenta fiscal_sequence dues vegades
```

Evidencia:

- request 1;
- request 2;
- resposta identica o marcada com duplicada;
- una sola factura;
- un sol numero fiscal.

### 5.3. Numeracio concurrent

Prova:

```text
llancar diverses emissions simultanies
verificar que no hi ha numeros duplicats
verificar que fiscal_order i hash chain queden lineals
```

### 5.4. Recorregut complet de factura

Prova:

```text
origen -> factura -> linies -> registre fiscal -> AEAT queue -> PDF/QR -> logs
```

S'ha d'executar almenys per:

- curs Redsys;
- pack;
- grup;
- regal;
- USOC;
- transferencia;
- factura abans de cobrament;
- rectificativa.

## 6. Proves de regressio recuperades del xat antic

El xat antic va insistir que el risc no es nomes crear factures noves, sino impedir que els mecanismes antics segueixin modificant dades fiscals sense control.

S'han d'afegir proves especifiques per demostrar que:

- no es pot corregir una factura emesa modificant `A_PAGAR`, receptor, concepte o imports sense rectificativa/event;
- no es poden moure pagaments entre inscripcions de manera que alteri una factura ja emesa;
- `Passar pagaments` no crea factura duplicada si ja existeix factura SIF;
- `Generar factura abans de pagar` crea factura real pendent de cobrament i el pagament posterior entra per `registerPayment()`;
- una URL individual de pagament queda bloquejada o substituida quan la factura correspon a empresa/responsable;
- els scripts antics, importadors, cron o eines Moodle no creen ni modifiquen factures fiscals;
- Moodle i altres APIs externes no tenen efecte fiscal directe;
- si el PDF/QR falla despres de la factura, la factura no es desfà i es crea incidencia SIF;
- si l'enviament AEAT falla, es conserva cua/retry i incidencia sense duplicar factura;
- un usuari sense permisos no pot executar accions fiscals critiques encara que vegi el boto o conegui l'endpoint;
- Meriem pot resoldre incidencia SIF o activar versio, pero Adam/Pablo/gestio no poden saltar controls de SIF;
- Isa pot fer suport segons rol, pero no queda com a operadora fiscal ordinaria.

### 6.1. Proves especifiques de Passar pagaments i TPV

Aquest subbloc queda pendent d'execucio, pero el criteri de prova queda definit:

- cerca de pagament amb cap criteri informat: bloqueig;
- cerca de pagament amb mes d'un criteri informat: bloqueig;
- pagament manual contra factura SIF existent: nomes `registerPayment()`;
- pagament contra factura abans de cobrament: nomes cobrament, sense factura nova;
- venda sense factura i facturable: `issueInvoice()` + `registerPayment()`;
- doble clic o reintent de la mateixa accio: sense duplicar pagament;
- pagament superior al pendent: bloqueig o incidencia amb motiu;
- pagament de grup, pack, regal i fraccio: relacio correcta amb origen i factura;
- TPV correcte sense incidencies: analisi auditada;
- TPV amb pagament no conciliat: incidencia o proposta de revisio;
- TPV amb format incorrecte: rebutjat sense tocar pagaments;
- reprocessament del mateix TPV: idempotent;
- devolucio TPV: flux de devolucio/rectificativa o incidencia, segons estat;
- usuari sense permis: endpoint bloquejat encara que conegui la URL.

### 6.1.1. Proves especifiques de Transferencia validada a intranet

Aquest subbloc queda pendent d'execucio, pero el criteri de prova queda definit:

- transferencia contra factura SIF existent: crea `payment_transaction` i `payment_allocation`, sense modificar import, receptor, concepte ni numero de factura;
- transferencia contra factura abans de cobrament: nomes registra cobrament i canvia estat de cobrament, sense crear factura nova;
- transferencia sense factura previa i venda facturable: fa `issueInvoice()` + `registerPayment()` en una operacio idempotent;
- transferencia parcial: conserva import real, deixa pendent calculat pel SIF i sincronitza `FRACCIO` historic nomes com a resum;
- transferencia que tanca el pendent: omple estat de cobrament final i, si es sincronitza `DATA PAG`, ho fa nomes despres de resposta correcta del SIF;
- import superior al pendent: bloqueig o incidencia amb motiu i sense update silencios;
- mateixa referencia bancaria repetida: no duplica `payment_transaction`;
- sense referencia bancaria: la clau `TRANSFERENCIA|FACT|DATA|IMPORT|BANC` evita doble clic o reintent;
- factura historica localitzada per `NUM FACTURA`: el sistema informa si no es SIF i aplica flux de migracio o incidencia, no actualitza com si fos factura VERI*FACTU;
- `updFactGenerada` historic no s'executa com a font fiscal final sobre factura emesa;
- distribucio antiga per `searchMembresFactRel` queda substituida per assignacions explicites a `payment_allocation`;
- correu a entitat/responsable nomes s'envia quan el cobrament i, si cal, la factura/PDF/QR estan en estat coherent.

### 6.2. Proves especifiques de Generar factura abans de pagar

Aquest subbloc queda pendent d'execucio, pero el criteri de prova queda definit:

- emissio d'una factura abans de cobrament amb una inscripcio;
- emissio amb diverses inscripcions del mateix curs i edicio;
- barreja de cursos bloquejada al servidor;
- barreja d'edicions bloquejada al servidor;
- inscripcio ja vinculada a factura bloquejada o redirigida a factura existent;
- doble clic o reintent retorna la mateixa factura per idempotencia;
- receptor fiscal sense CIF, rao, adreca, CP o poblacio bloquejat;
- `E_FACT` queda a `0` per defecte i `EMESA_ABANS_COBRAMENT` queda a `1`;
- `fact_rels` inclou totes les inscripcions seleccionades;
- PDF/QR es consulta com a document immutable;
- si falla PDF/QR, factura conservada i incidencia SIF creada;
- pagament posterior entra per `registerPayment()` sense crear nova factura;
- URL individual de pagament queda anul·lada, substituida o bloquejada si la factura es d'empresa/responsable.

### 6.3. Proves especifiques de Consulta - Edita - Anula factura

Aquest subbloc queda pendent d'execucio, pero el criteri de prova queda definit:

- cerca de factura per DNI/NIE, email, factura relacionada i numero de factura;
- visualitzacio conjunta de factura original, rectificatives, pagaments, devolucions i PDF/QR;
- intent d'edicio directa de receptor, CIF, concepte o import bloquejat per servidor;
- rectificativa de dades fiscals amb motiu, usuari, data, registre SIF i PDF/QR nou;
- rectificativa d'import total i parcial amb calcul decimal i enllac a factura original;
- anul·lacio total amb devolucio real o saldo, sense actualitzar `PAGAMENT` com a font fiscal unica;
- factura amb diverses inscripcions: assignacions visibles i persistides abans de confirmar retorn;
- marca i desmarca `E_FACT` com a accio separada, amb permis i log;
- consulta de factura historica no VERI*FACTU etiquetada com a historica o copia;
- reintent de rectificativa/devolucio idempotent;
- accions per `GET` rebutjades;
- usuari sense permis bloquejat encara que conegui l'endpoint.

### 6.4. Proves especifiques d'intranet alumne, empresa/responsable i acces VERI*FACTU

Aquest subbloc queda pendent d'execucio, pero el criteri de prova queda definit:

- alumne consulta factura individual propia amb PDF/QR disponible;
- alumne consulta inscripcio coberta per empresa/responsable i no veu factura completa;
- participant d'un grup intenta accedir a factura de grup i queda bloquejat;
- empresa/responsable consulta factura per enllac segur valid;
- token invalid, caducat o d'una altra factura queda rebutjat;
- URL de pagament d'empresa/responsable no redirigeix cap a URL individual d'alumne;
- PDF/QR pendent mostra estat pendent o incidencia, sense regenerar dades vives;
- document fiscal es descarrega sense exposar `PATH_FITXER` ni ruta interna;
- acces a document queda registrat si s'estableix auditoria d'accessos;
- apartat `VERI*FACTU` de la intranet mostra indicador i resum, pero no permet resoldre incidencies oficialment.

### 6.5. Proves especifiques de Redsys curs normal

Aquest subbloc queda pendent d'execucio, pero el criteri de prova queda definit:

- callback Redsys autoritzat amb signatura valida crea factura SIF i cobrament una sola vegada;
- callback duplicat amb el mateix `DS_ORDER` queda marcat com a duplicat o retorna la mateixa resposta idempotent;
- signatura incorrecta es rebutjada abans de tocar BD fiscal o `inscripcions`;
- `Ds_Response` denegat no crea factura ni cobrament;
- `Ds_Amount` signat diferent de l'import esperat obre incidencia i no emet automaticament;
- mateix `IDPAG` amb `DS_ORDER` diferent es tracta com a intent/fraccio diferent segons estat, no com a duplicat simple;
- inscripcio sense factura previa real executa `issueInvoice()`;
- factura abans de cobrament existent executa `registerPayment()`;
- l'antic bloc de `INSERT INTO factures` no s'executa per factures SIF noves;
- `inscripcions.PAGAMENT`, `DATA PAG`, `FACTURA_RELACIONADA` i `FRACCIO` nomes se sincronitzen despres de resposta correcta del SIF;
- correu de confirmacio nomes s'envia quan el SIF ha acceptat o deixat en estat controlat la factura/pagament;
- PDF/QR queda a `factura_documents` o en cua/incidencia, sense regeneracio lliure.

### 6.6. Proves especifiques de Packs

Aquest subbloc queda pendent d'execucio, pero el criteri de prova queda definit:

- pack ecommerce amb un sol pagament Redsys crea una sola factura SIF;
- les inscripcions del pack comparteixen `IDPAG` i queden relacionades amb la mateixa factura;
- la factura conte una linia per curs/inscripcio;
- la primera linia no porta descompte pack;
- la segona linia porta `DESC_ORIGEN = PACK`, descompte del 25% i `SOURCE_ID` de la seva `inscripcions.ID`;
- el total de factura coincideix amb l'import Redsys signat (`Ds_Amount`);
- `buscarPagamentsPack`/`buscarInfoPack` identifiquen el pack sense barrejar inscripcions d'un altre `IDPAG`;
- callback duplicat amb el mateix `DS_ORDER` retorna resultat idempotent i no duplica factura ni linies;
- mateix `IDPAG` amb `DS_ORDER` diferent es tracta com a intent o fraccionament segons estat, no com a duplicat automatic;
- pack fraccionat excepcional des d'intranet genera una factura per cada pagament real, amb idempotencia propia;
- el client ecommerce no pot dividir el pack en diverses factures;
- PDF/QR mostra les dues linies i el descompte de pack sense recalcular sobre dades vives.

### 6.7. Proves especifiques de Grups

Aquest subbloc queda pendent d'execucio, pero el criteri de prova queda definit:

- grup amb N participants crea una sola factura SIF per pagament real;
- la factura es fa al receptor fiscal correcte: escola/empresa o responsable particular;
- cada participant te una fila a `inscripcions` i una linia de factura amb `SOURCE_TYPE = INSCRIPCIO` i `SOURCE_ID = inscripcions.ID`;
- el preu/descompte per participant queda congelat a partir de `descomptes_grup`;
- el nom del participant pot sortir a la linia;
- el DNI no s'imprimeix per defecte i nomes apareix si hi ha justificacio documentada;
- `buscarPersRespGrup2`, `buscarPersGrup`, `buscarPagamentsGrup` i `searchMembresGrup` no barregen grups d'un altre `IDPAG`;
- callback duplicat amb el mateix `DS_ORDER` no duplica factura ni linies;
- factura abans de cobrament existent rep `registerPayment()` i no una factura nova;
- pagament parcial o fraccionat queda a `payment_transaction`/`payment_allocation` i nomes sincronitza `inscripcions` despres de resposta SIF;
- participant afegit despres d'emetre factura real genera factura complementaria o rectificativa segons cas;
- participant eliminat o import reduit despres d'emetre factura real genera rectificativa, devolucio o saldo segons cas;
- un participant no pot veure la factura completa del grup si inclou altres persones;
- empresa/responsable autoritzat pot consultar PDF/QR via enllac segur o espai futur sense exposar ruta interna.

### 6.8. Proves especifiques de Regals

Aquest subbloc queda pendent d'execucio, pero el criteri de prova queda definit:

- regal pagat per Redsys crea una sola factura SIF al comprador;
- la linia fiscal porta `SOURCE_TYPE = REGAL` i `SOURCE_ID = regal.ID`;
- el concepte visible inclou curs o tipus de curs i pot incloure codi regal segons criteri final;
- `buscarRegNoPayByCodi` i `buscarRegNoPayByDni` nomes proposen regals amb `FACT_REL = 0` o sense factura SIF equivalent;
- `buscarRegalById` carrega comprador, curs, codi, origen i desti sense convertir el destinatari en receptor fiscal;
- callback duplicat amb el mateix `DS_ORDER` no duplica factura ni actualitza dues vegades el regal;
- `updFactRegal` historic es substitueix o queda subordinat a resposta correcta del SIF i relacio a `fact_rels`;
- el correu o targeta regal comercial no substitueix el PDF fiscal immutable de `factura_documents`;
- el destinatari pot bescanviar el codi i crear inscripcio sense generar factura nova;
- codi ja bescanviat, caducat o incoherent obre incidencia operativa i no emet factura automatica;
- l'enllac o PDF de targeta regal no exposa la factura fiscal si el destinatari no es receptor.

### 6.9. Proves especifiques d'USOC

Aquest subbloc queda pendent d'execucio, pero el criteri de prova queda definit:

- inscripcio amb `TIPUS_DESC = 4` queda pendent si `VALID_DESC = 0`;
- la pantalla `Validar descomptes` mostra el cas `Afiliat USOC` i permet validar o denegar;
- si USOC confirma afiliacio, el sistema congela preu/descompte i deixa facturar la part de l'alumne;
- si USOC no confirma afiliacio, el sistema recalcula sense descompte i no crea factura USOC;
- pagament Redsys de la part alumne crea una factura SIF a l'alumne per l'import real pagat;
- la factura de l'alumne conserva `TIPUS_DESC = 4`, `VALID_DESC = 1`, import base, descompte i import final;
- pagament de la diferencia per USOC crea una segona factura ordinaria a USOC, no una rectificativa;
- la factura USOC queda relacionada amb la mateixa `inscripcions.ID` i amb la factura de l'alumne;
- callback duplicat o reintent no duplica la factura de l'alumne ni la factura USOC;
- el cas especial `Altres: Curs gratüit USOC` aplica `anticipi-preu-usoc` si es manté operativament i queda provat separat;
- els textos de correu de validacio/denegacio no prometen factura fins que el SIF hagi emes o registrat estat controlat;
- l'alumne no pot veure dades fiscals completes d'USOC si no es receptor d'aquesta factura.

### 6.10. Proves especifiques de Codis promocionals

Aquest subbloc queda pendent d'execucio, pero el criteri de prova queda definit:

- codi promocional valid abans de pagar queda congelat a `factura_linia`;
- codi caducat, usat o d'un altre DNI queda rebutjat abans de Redsys o abans d'emetre;
- `cnsSiTePromocioDispo` comprova `CODI_DESCOMPTE`, DNI, `USED = 0`, `DATAI` i `DATAF`;
- `updDataFPromocio` no modifica una factura ja emesa, nomes l'estat/vigencia operativa del codi;
- promocio temporal `descomptes.TIPUS` 11-99 conserva `DESC_ID`, percentatge/preu i vigencia usada;
- codi personal `MACABODETITULAR#...` conserva import, data de validesa i relacio interna sense exigir que el codi sigui visible al PDF;
- callback Redsys amb import final amb codi aplicat no recalcula el descompte;
- callback duplicat no torna a consumir el codi ni duplica factura;
- si el codi caduca o es marca usat despres d'emetre, la factura/PDF/QR no canvia;
- canvi de curs despres d'una factura amb codi promocional obre flux fiscal controlat si canvia import o concepte;
- el text visible del PDF mostra descompte generic i evita exposar dades internes sensibles.

## 7. Paquet de proves go/no-go

Abans de produccio s'ha de tancar un paquet go/no-go amb:

- identificador de versio provada;
- commit o paquet desplegat;
- migracions SQL aplicades;
- entorn utilitzat;
- llista de proves executades;
- resultat de cada prova;
- incidencies obertes i severitat;
- decisio final: `GO`, `GO AMB LIMITACIONS` o `NO-GO`;
- responsable tecnica;
- revisio de direccio/responsable legal quan correspongui.

Regla:

```text
Una versio no passa a produccio nomes perque compila o perque el flux ideal funciona.
Ha de superar els casos critics, regressions i evidencies minimes.
```
