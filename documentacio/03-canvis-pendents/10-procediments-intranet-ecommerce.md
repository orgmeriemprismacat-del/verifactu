# 10 - Procediments d'intranet, ecommerce i canals de facturacio

> Document viu. Recull el procediment funcional de cada apartat de la intranet, ecommerce i canals relacionats amb pagaments, factures, rectificatives, saldos, baixes i notificacions.

## 1. Objectiu

Aquest document serveix per documentar, apartat per apartat:

- que fa actualment;
- quines dades toca;
- quines taules consulta o modifica;
- quins correus envia;
- quins riscos te amb VERI*FACTU;
- quin comportament nou ha de tenir;
- si crida `issueInvoice()` o `registerPayment()`;
- quins errors han de generar notificacio.

## 2. Plantilla per documentar cada apartat

Cada apartat s'haura de documentar amb aquesta estructura:

```text
Nom de l'apartat:
URL / fitxer PHP:
Qui el pot usar:
Objectiu funcional:
Dades d'entrada:
Taules que consulta:
Taules que modifica:
Genera factura? si/no
Genera pagament? si/no
Genera rectificativa? si/no
Envia correus? quins?
Errors possibles:
Notificacions internes:
Canvi necessari per VERI*FACTU:
Endpoint SIF:
Notes:
```

## 3. Apartats identificats

### 3.0. Mapa de rutes actuals de la intranet

Segons el `.htaccess` de `intranet.prisma.cat`, les pantalles relacionades amb alumnes, pagaments, factures i reclamacions entren per URLs amigables que carreguen fitxers PHP concrets.

Rutes d'alumnes amb impacte VERI*FACTU:

| URL intranet | Fitxer PHP | Apartat funcional |
| --- | --- | --- |
| `/alumnes/mostrar-alumne/` | `alumnes-mostrar-alumne.php` | Consulta - Modifica alumne. |
| `/alumnes/pagaments/` | `alumnes-pagaments.php` | Passar pagaments. |
| `/alumnes/factura/` | `alumnes-factura.php` | Consulta - Edita - Anula factura. |
| `/alumnes/genera-factura-abans-pagar/` | `alumnes-genera-factura-abans-pagar.php` | Generar factura abans de pagar / abans de cobrament. |
| `/alumnes/genera-entitat/` | `alumnes-genera-entitat.php` | Crear/editar entitats relacionades amb facturacio d'empresa. |
| `/alumnes/validar-descomptes/` | `alumnes-validar-descomptes.php` | Validacio de descomptes amb impacte en snapshot fiscal. |

Rutes de facturacio/reclamacions amb impacte VERI*FACTU:

| URL intranet | Fitxer PHP | Apartat funcional |
| --- | --- | --- |
| `/facturacio/comprovar-idpags/` | `facturacio-comprovar-idpags.php` | Comprovacio/conciliacio d'IDPAGs i TPV. |
| `/facturacio/primera-reclamacio/` | `facturacio-primera-reclamacio-pagament.php` | Primera reclamacio de pagament. |
| `/facturacio/baixes/` | `facturacio-baixes-segona-setmana.php` | Seguiment de baixes amb possible impacte economic. |
| `/facturacio/recordatori-pagament/` | `facturacio-recordatori-pagament-final.php` | Recordatori de pagament. |
| `/facturacio/reclamacio-final/` | `facturacio-reclamacio-final.php` | Reclamacio final. |
| `/facturacio/morosos/` | `facturacio-control-morosos.php` | Control de morositat. |

Nota tecnica:

- el fitxer PHP de ruta pot actuar com a entrada/pantalla;
- la funcionalitat principal pot continuar estant dins `Intranet.php`;
- les accions concretes s'executen amb JS/AJAX contra metodes de la classe o endpoints auxiliars;
- per documentar cada apartat cal lligar: URL -> PHP entrada -> JS -> AJAX -> metode PHP -> taules afectades.

### 3.0.1. Constructor i diccionari intern de consultes de `Intranet.php`

El constructor de `Intranet` concentra una part important del mapa tecnic actual. La classe guarda:

- usuari serialitzat i context de pagina: `$usuari`, `$url`, `$qui`, `$depart`;
- diccionaris SQL interns: `$consultesBD_Intra`, `$consultesBD_Web`, `$consultesBD_Moodle`, `$consultesBD_MoodleAntic`;
- configuracio de correus/estil: `$colors_destacats`, `$contacte`;
- integracio Google: `$googleClient`;
- parametres operatius de comunicats: `$diesOberturaAules1` i `$diesOberturaAules2`.

El patró actual es:

```text
AJAX/PHP wrapper -> metode Intranet.php -> clau de consulta dins consultesBD_* -> Connexio*
```

Per tant, quan es documenta un metode de `Intranet.php`, cal anotar tambe les claus de consulta que utilitza.

BD Intranet (`consultesBD_Intra`) confirmada:

- `params`, per parametres interns vigents per data;
- `apartats`, `rols` i `usuaris`, per menu, permisos, rols i sessio;
- `entitats` i `entitats_resp`, per receptors/contactes d'entitat;
- `pendents`, per tasques internes.

Claus rellevants d'entitats:

- consulta: `buscarTotesEntitats`, `buscarEntitat`, `buscarEntitatById`, `buscarRespEntitatById`, `buscarRespEntitatByCIF`;
- alta: `insertPersRespEntitat`, `insertEntitat`;
- edicio: `updEntitat`, `updEntitatResp`.

BD Web (`consultesBD_Web`) confirmada:

- `inscripcions`, font operativa d'alumnes, imports, estats, `IDPAG`, `FACTURA_RELACIONADA`, reclamacions i dades de contacte;
- `factures`, taula historica de factures, numeracio, imports, forma de pagament, `E_FACT`, `NUM_COMANDA` i dades del receptor;
- `curs`, `aula`, `jornades`, `personal`, `honoraris`, per dades academiques i de comunicacio;
- `preu`, `descomptes`, `promocions`, `recent_titulat`, per preus/descomptes;
- `regal`, per codis regal;
- `respGrups`, per responsables de grup;
- `packs`, `info_pack`, per packs;
- `aobservacions`, per observacions generals.

Claus rellevants de factures:

- cerca i visualitzacio: `buscarFactura`, `buscarLastOrdreFact`, `buscarLastFact`, `buscarInfoFactura`, `buscarInfoFacturaByNum`, `buscarInfoFacturaGener`, `buscarInfoFacturaId`, `buscarInfoFacturaByFact`;
- cerca per pantalla de factura: `buscaIdFactCorreu`, `buscaIdFactCorreuID`, `buscaIdFactRel`, `buscaIdFactRelID`, `buscaIdFactNum`, `buscaIdFactNumID`, `buscaDniIdFact`, `buscaFactRelInsc`, `buscaFactRelFact`, `buscaIdFactCif`, `buscarIdFact`, `buscarTotesFactId`;
- relacio factura-inscripcio: `buscarInfoFactInsc`, `buscarInfoFactInsc2`, `buscarInscFactRel`, `searchMembresFactRel`;
- alta actual: `insertFactura`, `insertFacturaAut`;
- edicio/estat actual: `updFactGenerada`, `updDadesFact`, `updObsFact`;
- anul·lacio/retorn actual sobre inscripcions: `updInscAnulFact`, `updInscAnulFact2`, `updInscDataPagAnulFact`, `updInscFraccAnulFact`.

Claus rellevants de pagaments:

- cerca de deutes: `buscarPagaments`, `buscarPagamentsPack`, `buscarPagamentsGrup`, `buscarPagamentsByFact`;
- regal: `buscarRegNoPayByCodi`, `buscarRegNoPayByDni`, `buscarRegalById`, `updFactRegal`;
- grup: `buscarPersRespGrup`, `buscarPersRespGrup2`, `buscarPersGrup`, `buscarInfoPersRespGrup`, `buscarInfoEdGrup`, `buscarInfoEdGrupByFact`, `searchMembresGrup`, `searchMembresGrup2`;
- pack: `buscarInfoPack`, `cnsInscsPack`, `cnsDadesCursPack`;
- actualitzacio de pagament/fraccio: `updDateInscr`, `updPayInscr`, `updPayInscr2`, `updFactInscr`, `updPayInscrByIdPag`, `updPayInscrByIdPag2`, `updDateInscrByIdPag`, `updFraccBD`, `updFraccBDByFact`, `updPagObsByIdPag`.

Claus rellevants de reclamacio/morositat:

- deteccio: `cnsReclamacions`, `cnsRegBaixesSegonaSetnaba`, `cnsCursosRecordarPag`, `cnsAlumnesRecordarPag`, `cnsCursosClaimBaixes`, `cnsAlumnClaimPag`, `cnsAlumnClaimEntMoros`, `cnsAlumnClaimAlumnNoCertMoros`, `cnsAlumnClaimAlumnCertMoros`, `cnsEntMoros`;
- actualitzacio: `updPrimeraReclamacio`, `updClaimDonarBaixa`, `updClaimRecPag`, `updInscCursBaixaiMoros`, `updReclamatDefaulter`.

BD Moodle i Moodle antic:

- es fan servir per matriculacions, visibilitat de curs, usuaris, qualificacions, forums i baixes de Moodle;
- no son font fiscal;
- poden ser efectes operatius d'un flux de baixa, canvi o acces, pero no substitueixen factura, pagament ni rectificativa.

Impacte SIF del constructor:

- el constructor confirma que avui hi ha molts `UPDATE` directes sobre `web.inscripcions` i `web.factures`;
- les claus `insertFactura`, `insertFacturaAut`, `updDadesFact`, `updFactGenerada`, `updFactInscr`, `updPayInscr*`, `updInscAnulFact*` i `updInscDataPagAnulFact` son punts critics de migracio;
- les factures noves no han de dependre d'aquests `INSERT/UPDATE` directes, sino de `issueInvoice()`, `registerPayment()`, rectificatives i taules SIF;
- `web.factures` i `web.inscripcions` poden quedar com historic/compatibilitat o resum operatiu, pero no com a registre fiscal immutable.

### 3.1. Consulta - Modifica alumne

```text
Nom de l'apartat: Consulta - Modifica alumne
URL / fitxer PHP: /alumnes/mostrar-alumne/ -> alumnes-mostrar-alumne.php -> classe Intranet.php
Qui el pot usar: Meriem, Adam, Pablo i Isa per consulta segons rol intern
Objectiu funcional: consultar la fitxa d'un alumne, les seves inscripcions i iniciar accions administratives relacionades amb curs, baixa, factura o certificat
Dades d'entrada: DNI, correu, nom, cognoms o identificador intern d'inscripcio/alumne
Genera factura? no directament
Genera pagament? no directament
Genera rectificativa? no directament; pot iniciar fluxos que la generin
Envia correus? no com a consulta; els correus pertanyen als subfluxos de pagament, canvi, baixa o factura
```

Implementacio actual localitzada:

- classe principal: `Intranet.php`;
- ruta `.htaccess`: `/alumnes/mostrar-alumne/` -> `alumnes-mostrar-alumne.php`;
- la pantalla no es un PHP independent per accio, sino una pagina renderitzada per la classe `Intranet` amb crides JS/AJAX a metodes de la mateixa classe;
- ruta detectada: `/alumnes/mostrar-alumne/`;
- metode de pagina: `__mostrarPage_Alumnes_MostrarAlumne()`;
- cerca d'alumnes: `buscarUsuaris()` i `searUserByParam()`;
- taula de resultats: `mostrarTaulaUsuaris_Alumnes()` / `mostrarTaulaUsuaris2_Alumnes()`;
- fitxa de l'alumne: `mostrarInformacioUsuari_Alumnes()`;
- dades personals: `__mostrarDadesPersonals_resultatCerca()` i `guardarDadesPersonals_resultatCerca()`;
- blocs d'inscripcions: `__mostrarTotsCursos_resultatCerca()`, `__mostrarCursosPendents_resultatCerca()`, `__mostrarCursosActius_resultatCerca()`, `__mostrarCursosAcabats_resultatCerca()` i `__mostrarCursosCongelats_resultatCerca()`;
- files i icones d'accio: `__mostrarFilaResultatCerca()`;
- observacions generals: `mostrarObservacionsGenerals_resultatCerca()`, `afegirObservacioGeneral_resultatCerca()` i `amagarObservacioGeneral_resultatCerca()`;
- modal d'informacio: `modalConsultaInformacio_resultatCerca()`;
- dades de pagament: `guardarDadesPagament_modalsresultatCerca()` i `enviarNotificacioObsPagament_modalsresultatCerca()`;
- canvi de curs: `modalCanviCurs_resultatCerca()`, `buscarPreuAPagar_modalCanviCurs()`, `calcularDespesesGestio_modalCanviCurs()` i `realitzarCanviCurs_modalCanviCurs()`;
- baixa: `modalDonarBaixa_resultatCerca()` i `confirmaBaixa_modalDonarBaixa()`;
- factura: `modalConsultaFactura_resultatCerca()` i `generaFactura()`;
- certificat: `modalConsultaCertificat_resultatCerca()` i `generaCertificat()`.

Funcio actual:

- consulta dades personals de l'alumne;
- permet editar dades personals operatives, pero aquests canvis nomes afecten inscripcions pendents de comencar;
- consulta inscripcions pendents de comencar;
- consulta inscripcions acabades;
- mostra observacions generals associades a l'alumne/DNI;
- permet obrir dades del curs;
- permet obrir dades de pagament;
- permet iniciar canvi de curs;
- permet iniciar baixa;
- permet veure factura quan hi ha `FACTURA_RELACIONADA`;
- permet veure certificat quan correspon;
- mostra icones amb baixa opacitat quan l'accio no es pot executar.

Icones identificades:

- informacio de l'alumne/inscripcio;
- canvi de curs;
- baixa;
- veure factura;
- veure certificat.

Taules que consulta actualment o haura de consultar:

- `web.inscripcions`;
- taules de cursos, mesos/edicions i grups;
- taules de factures historiques de la BD web;
- `dades_fiscals.factura`;
- `dades_fiscals.factura_linia`;
- `dades_fiscals.factura_registres`;
- `dades_fiscals.factura_documents`;
- `dades_fiscals.factura_rectificacio`;
- `dades_fiscals.payment_transaction`;
- `dades_fiscals.payment_allocation`;
- `dades_fiscals.fact_rels`;
- `intranet.entitats` i `intranet.entitats_resp` quan la factura o pagament depen d'empresa/responsable;
- `intranet.notificacions` si es mostra resum d'avisos fiscals vinculats.

Taules que modifica actualment:

- `web.inscripcions`, per dades operatives de l'alumne o de la inscripcio;
- camps de pagament de `web.inscripcions` en alguns casos;
- estat academic/administratiu `INSC_CURS`;
- observacions;
- reclamacions i seguiment de cobrament a traves dels camps `reclamat`, `data_reclamacio` i `pag_observacions` de `inscripcions`.

Regla nova:

```text
Consulta - Modifica alumne
= pantalla de consulta i inici d'accions
no = pantalla per editar factures emeses
```

Decisions consolidades de l'apartat:

- `Consulta - Modifica alumne` es manté com a pantalla central de fitxa d'alumne, pero no sera la font fiscal oficial;
- les dades personals que es modifiquen des d'aqui son dades operatives de la inscripcio;
- editar nom, cognoms, DNI/NIF o adreca no modifica cap factura emesa;
- si es vol canviar una dada fiscal d'una factura ja emesa, s'ha d'obrir flux de rectificativa;
- les inscripcions continuen tenint estat academic/administratiu amb `INSC_CURS`;
- `INSC_CURS` no substitueix l'estat de cobrament ni l'estat fiscal;
- `reclamat`, `data_reclamacio` i `pag_observacions` continuen sent seguiment administratiu de reclamacions, no registre fiscal;
- `FACTURA_RELACIONADA` es conserva com a compatibilitat i agrupador historic, pero la relacio nova amb el SIF s'haura de fer per UUID de factura i taula de relacions;
- les factures historiques de `web.factures` es mostraran com a historiques no VERI*FACTU;
- les factures noves es consultaran des del SIF amb estat AEAT, PDF immutable, QR, pagaments i rectificatives;
- la factura d'empresa/grup no s'ha de mostrar com si fos factura individual de cada alumne participant;
- l'alumne nomes ha de veure factures on sigui receptor fiscal o documents marcats com a visibles per a ell;
- una factura d'empresa pendent de cobrament pot tenir URL propia de pagament;
- un alumne moros no s'ha de bloquejar per pagar, perque interessa que pugui regularitzar el deute;
- si una inscripcio individual esta coberta per factura d'empresa/responsable, la URL individual s'ha de desactivar o substituir per la URL correcta;
- `E_FACT` indica factura electronica;
- `EMESA_ABANS_COBRAMENT` indica factura real emesa abans de cobrar;
- una factura abans de cobrar no implica automaticament `E_FACT = 1`;
- veure factura es sempre lectura; editar, anul·lar o corregir passa per fluxos separats.

Riscos actuals detectats:

- editar dades personals pot donar la impressio que es modifica tambe una factura ja emesa, pero no ha de ser aixi;
- `guardarDadesPagament_modalsresultatCerca()` pot modificar directament `A_PAGAR`, `PAGAMENT`, `DATA PAG`, `IDPAG`, `FRACCIONAT`, `FRACCIO`, `FACTURA_RELACIONADA`, `data_reclamacio` i `reclamat`;
- modificar dades de pagament directament pot trencar la traçabilitat entre pagament, factura i registre fiscal;
- canviar `INSC_CURS` manualment pot saltar el flux fiscal que tocaria;
- una factura d'empresa/grup pot aparèixer vinculada a inscripcions de diversos alumnes;
- la URL individual de pagament pot quedar activa tot i existir una factura d'empresa/responsable;
- les factures historiques i les noves VERI*FACTU necessiten identificacio visual diferent;
- el canvi de curs pot recalcular imports, descomptes i despeses de gestio, i per tant pot requerir rectificativa o nou pagament;
- la baixa no implica automaticament retorn o rectificativa, pero pot acabar generant-los segons la decisio economica posterior.
- `generaFactura()` construeix la visualitzacio actual a partir de `factures.concepte1`, `factures.concepte2` i `factures.import`, sense linies fiscals estructurades ni QR.

Decisio presa:

```text
El bloc de dades de pagament s'ha de modificar.
No pot continuar funcionant com a editor directe de camps economics/fiscals.
```

Concretament, `guardarDadesPagament_modalsresultatCerca()` s'ha de substituir o limitar:

- `A_PAGAR` es podra modificar si es necessari, pero no com a canvi silencios; el modal haura de marcar que es un ajust manual, demanar motiu i indicar l'impacte fiscal;
- si hi ha factura emesa, el canvi de `A_PAGAR` ha d'anar per canvi de curs, descompte justificat, rectificativa, saldo o un altre flux controlat;
- si encara no hi ha factura emesa, el canvi pot quedar com a dada operativa de la inscripcio, pero igualment ha de quedar marcat amb motiu quan no vingui d'un calcul automatic;
- `PAGAMENT`, `DATA PAG` i `IDPAG` no han de representar el registre fiscal definitiu del cobrament; el cobrament real ha d'anar a `payment_transaction` i `payment_allocation`;
- `FACTURA_RELACIONADA` no s'ha de canviar manualment sense crear o actualitzar la relacio SIF corresponent;
- `FRACCIONAT` i `FRACCIO` han de quedar com a dada operativa o informativa, pero la factura de cada fraccio ha de sortir del SIF;
- `reclamat`, `data_reclamacio` i `pag_observacions` poden continuar com a seguiment administratiu, pero no han de substituir el registre fiscal de pagament.

Canvis VERI*FACTU:

- no editar factures emeses des d'aquesta pantalla;
- mostrar estat de factura: `ISSUED`, `RECTIFIED` o `CANCELLED`;
- mostrar estat AEAT: `PENDING`, `SENT`, `ACCEPTED`, `REJECTED`, `RETRY` o `FAILED`;
- mostrar estat de cobrament calculat a partir de pagaments i assignacions;
- mostrar si la factura es historica no VERI*FACTU;
- mostrar PDF immutable i QR quan existeixi `factura_documents`;
- mostrar si la factura es electronica (`E_FACT = 1`);
- mostrar si la factura va ser emesa abans del cobrament (`EMESA_ABANS_COBRAMENT = 1`);
- controlar visibilitat de factura d'empresa/grup per no mostrar al participant una factura completa amb altres persones;
- si una inscripcio esta coberta per factura d'empresa/responsable, desactivar o contextualitzar la URL individual;
- si hi ha URL de pagament activa, indicar tipus: individu, pack, grup, regal, empresa, USOC, diferencia canvi curs o morositat;
- canvis de curs amb previsualitzacio fiscal abans de confirmar;
- baixes com event administratiu inicial, sense rectificativa automatica fins que es decideixi retorn, saldo o no retorn;
- canvis d'estat `INSC_CURS` nomes per flux controlat i amb log;
- afegir avis quan es canviin nom, cognoms, DNI o adreca si hi ha factures emeses relacionades.
- substituir la visualitzacio de factura nova per lectura des del SIF i `factura_documents`;
- mantenir `generaFactura()` nomes per factures historiques o fins que es migri la visualitzacio;
- moure les accions fiscals de dades de pagament cap a fluxos controlats, no a actualitzacions directes de camps.

Subfluxos que surten d'aquesta pantalla:

- dades del curs: consulta academica i administrativa;
- dades de pagament: consulta/regularitzacio controlada de pagaments, URLs i estat de cobrament;
- canvi de curs: recalcul economic, motiu, diferencia, saldo o retorn i accio fiscal prevista;
- baixa: registre de baixa i decisio economica posterior;
- veure factura: lectura de factura, PDF, QR, rectificatives i estats;
- certificat: consulta o emissio de certificat, sense impacte fiscal directe.

#### 3.1.0. Revisio especialitzada i criteri de tancament

La revisio especialitzada del xat antic confirma que `Consulta - Modifica alumne` es la pantalla central d'events sobre inscripcions.

No ha de quedar documentada com a pantalla que "arregla" factures. Ha de quedar documentada com a pantalla que:

- consulta estat academic, economic i fiscal;
- mostra accions disponibles segons estat i permisos;
- inicia fluxos controlats;
- deriva cap al SIF o cap a pantalles especialitzades quan hi ha impacte fiscal.

Mapa de decisio dels subfluxos:

| Subflux | Genera factura | Genera pagament | Genera rectificativa | Criteri final |
| --- | --- | --- | --- | --- |
| Dades personals | No | No | No directament | Canvia dades operatives. Si afecta factura emesa, obre flux de rectificativa de dades fiscals. |
| Dades del curs | No | No | No | Consulta academica/administrativa. |
| Dades de pagament | No directament | Pot iniciar registre | No directament | Ha de deixar de ser editor lliure de `A_PAGAR`, `PAGAMENT`, `DATA PAG`, `IDPAG` i `FACTURA_RELACIONADA`. |
| Canvi de curs | Pot preparar factura/diferencia | Pot preparar cobrament | Pot preparar rectificativa | Recalcula import, descompte i despeses; exigeix motiu i previsualitzacio fiscal. |
| Baixa | No en el primer pas | No | No automatica | Primer baixa administrativa; despres retorn, saldo o no retorn. |
| Veure factura | No | No | No | Nomes lectura, PDF/QR i estats; accions de rectificar/anul·lar viuen a `/alumnes/factura/`. |
| Certificat | No | No | No | Sense impacte fiscal directe. |

Proves que ha de tenir aquest apartat:

- editar dades personals amb factura emesa i comprovar que la factura no canvia;
- intentar modificar `A_PAGAR` amb factura emesa i comprovar que exigeix motiu i flux fiscal;
- veure una factura historica i comprovar etiqueta `historic no VERI*FACTU`;
- veure una factura SIF i comprovar UUID, PDF/QR, estat cobrament i estat AEAT;
- canvi de curs a import superior, inferior i mateix import;
- canvi de curs on el descompte original deixa d'aplicar;
- baixa sense pagament, baixa amb retorn, baixa amb saldo i baixa sense retorn;
- alumne moros amb URL de pagament activa o alternativa;
- factura d'empresa/responsable vinculada a inscripcio individual sense URL individual duplicada.

Pendent de codi per acabar la fitxa executable:

- ordre real d'updates i validacions de `guardarDadesPagament_modalsresultatCerca()`;
- ordre real de `realitzarCanviCurs_modalCanviCurs()`;
- ordre real de `confirmaBaixa_modalDonarBaixa()`;
- diferenciacio final de `generaFactura()` per factura historica i factura SIF;
- missatges exactes que veura l'usuari quan una icona esta desactivada.

#### 3.1.1. Dades del curs

Decisio:

- es manté com a modal de consulta de dades academiques i administratives;
- mostra dades de curs, edicio, aula/grup, dates, tutor, estat, inscripcions i observacions;
- no genera factura, pagament ni rectificativa;
- pot ajudar a contextualitzar canvi de curs, baixa o certificat;
- no ha de modificar dades fiscals.

#### 3.1.2. Dades de pagament

Decisio:

- s'ha de redissenyar com a vista d'estat i punt d'entrada a fluxos controlats;
- pot mostrar dades heretades de `inscripcions`, pero el SIF sera la font de veritat per factura, cobrament i rectificatives;
- `A_PAGAR` pot canviar si cal, pero el modal ho ha de marcar com a ajust manual quan no sigui calcul automatic;
- qualsevol ajust manual de `A_PAGAR` ha de tenir motiu;
- si no hi ha factura emesa, l'ajust pot quedar a la inscripcio com a dada operativa abans de facturar;
- si hi ha factura emesa, l'ajust ha d'obrir el flux fiscal corresponent;
- `PAGAMENT`, `DATA PAG` i `IDPAG` deixen de ser prova fiscal suficient del cobrament;
- el cobrament real ha d'anar a `payment_transaction` i `payment_allocation`;
- `FRACCIONAT` i `FRACCIO` queden com a informacio operativa; cada fraccio que es factura ha de quedar al SIF;
- `FACTURA_RELACIONADA` no s'ha de canviar manualment com a solucio d'incidencia;
- la URL de pagament ha de passar a `pay.prisma.cat`;
- cal mostrar si la URL esta activa, inactiva o substituida per una altra URL.

#### 3.1.3. Canvi de curs

Decisio:

- el canvi de curs continua iniciant-se des de la fitxa de l'alumne;
- el sistema recalcula automaticament el nou `A_PAGAR` segons curs nou i descompte original si encara aplica;
- si el descompte original no aplica al curs nou, el sistema ho ha de detectar;
- les despeses de gestio es calculen automaticament segons el tipus de canvi;
- `A_PAGAR` i despeses poden ajustar-se manualment en casos puntuals, pero han de quedar marcats amb motiu;
- el modal ha de mostrar import anterior, import nou, pagat, pendent, despeses, diferencia, saldo o retorn;
- si canvia el curs o concepte d'una factura ja emesa, no es pot deixar com a simple canvi intern;
- si el nou import es superior, s'ha de generar l'impacte fiscal per diferencia positiva quan correspongui;
- si el nou import es inferior, s'ha de generar l'impacte fiscal per diferencia negativa, saldo o retorn segons decisio;
- si el nou import es igual pero canvia el servei/concepte facturat, cal valorar rectificativa per canvi de concepte;
- el canvi ha de quedar registrat a `canvi_curs` o taula equivalent amb motiu, usuari, data, import antic, import nou, factura original i accio fiscal.

#### 3.1.4. Baixa

Decisio:

- la baixa marca primer l'event administratiu de la inscripcio;
- no genera rectificativa automatica en el moment de clicar baixa;
- primer s'ha de saber que es fara economicament: retorn, saldo a favor o no retorn;
- si posteriorment hi ha retorn, saldo o anul·lacio economica, llavors s'obre el flux fiscal corresponent;
- el motiu de baixa, qui la fa i la data han de quedar registrats;
- si afecta Moodle o correus, aquests passos son operatius i no substitueixen la part fiscal.

#### 3.1.5. Veure factura

Decisio:

- la pantalla/modal de factura sera nomes lectura;
- `generaFactura()` pot quedar per factures historiques mentre es migra;
- les factures noves s'han de llegir del SIF;
- ha de mostrar numero visible, receptor, linies, descomptes visibles, total, estat factura, estat AEAT, estat cobrament, PDF, QR, rectificatives i marca `E_FACT`;
- no es pot editar receptor, concepte ni import des d'aquesta vista;
- no es pot anul·lar des d'aquesta vista sense flux de rectificativa;
- el PDF de factura nova ha de ser immutable i registrat a `factura_documents`.

Endpoints SIF previstos:

- `GET /api/factures?source_type=INSCRIPCIO&source_id={id}`;
- `GET /api/factures/{uuid}`;
- `GET /api/factures/{uuid}/documents`;
- `GET /api/payments?source_type=INSCRIPCIO&source_id={id}`;
- `POST /api/payments/register`, nomes des dels fluxos autoritzats;
- `POST /api/factures/rectify`, nomes des dels fluxos autoritzats.

Notes pendents:

- confirmar si tots els AJAX d'aquest apartat entren sempre per `Intranet.php` o si hi ha alguna excepcio externa;
- documentar el formulari exacte de dades de pagament quan s'analitzi l'apartat de `Passar pagaments`.

### 3.1.6. Genera/Edita entitats i responsable d'entitat

URL / fitxer:

```text
https://intranet.prisma.cat/alumnes/genera-entitat/
/alumnes/genera-entitat/ -> alumnes-genera-entitat.php
JS: alumnes-genera-entitat.js
```

Entrada PHP actual:

- `alumnes-genera-entitat.php` comprova sessio amb `inc/comprovarSessio.php`;
- si `$configOk` no es correcte, redirigeix a `https://intranet.prisma.cat/`;
- carrega Bootstrap, jQuery, Popper, Fontawesome i els CSS comuns;
- carrega `css/alumnes-genera-entitat.css`;
- carrega `general.js`;
- carrega `alumnes-genera-entitat.js`;
- crea nomes els contenidors `.sidebar` i `.mainpanel`;
- la pantalla real es carrega posteriorment via JS/AJAX, seguint el patro de `mostrarMain.php` i classe `Intranet.php`.

AJAX actuals documentats:

- `ajax/mostrarMain.php`, carrega la pantalla a partir de la URL;
- `ajax/alumnes/generaEntitats.php`, crea una entitat nova i el seu responsable. Crida `creaEmpresa_Alumnes($rao, $cif, $adreca, $cp, $poble, $nomResp, $cogResp, $correu)`;
- `ajax/alumnes/mostrarModalEditaEntitat_Entitats.php`, obre el modal d'edicio. Crida `mostrarModalEditaEntitat_Alumnes($idEntitat, $idResponsable)`;
- `ajax/alumnes/actualitzaEditaEntitat.php`, actualitza dades de l'entitat i del responsable. Crida `actualitzaEditaEntitat_Alumnes($id, $rao, $cif, $adreca, $cp, $poblacio, $nomResp, $cogResp, $correu)`.

Consultes internes confirmades al constructor:

- `buscarTotesEntitats`: omple el llistat d'entitats actives amb `entitats` + `entitats_resp`;
- `buscarEntitat`, `buscarEntitatById`: recuperen dades fiscals/operatives de l'entitat;
- `buscarRespEntitatById`, `buscarRespEntitatByCIF`: recuperen responsable actiu;
- `insertPersRespEntitat` i `insertEntitat`: creen responsable i entitat;
- `updEntitat`: modifica dades de l'entitat;
- `updEntitatResp`: tanca el responsable actiu anterior posant `DATAF = CURRENT_TIME`.

Seqüencia JS actual:

1. `requestMain` carrega `ajax/mostrarMain.php` amb `urlPagina`.
2. El boto `#afegeix-entitat` comprova `tePermisEdicio`.
3. Llegeix `cif`, `rao`, `adreca`, `cp`, `poblacio`, `nomResp`, `cognomResp` i `correuResp`.
4. Valida al navegador que tots els camps estiguin omplerts.
5. Envia `POST` a `generaEntitats.php`.
6. En cas d'exit mostra modal de success i recarrega la pagina.
7. El llapis `.edita-entitat` obre `mostrarModalEditaEntitat_Entitats.php`.
8. El boto `.save-edicio` envia `GET` a `actualitzaEditaEntitat.php`.
9. En cas d'exit mostra modal de success i recarrega la pagina.

Riscos tecnics actuals:

- la validacio de camps es fa nomes al navegador;
- no hi ha validacio visible de format CIF, CP o correu;
- no hi ha comprovacio visible de duplicats per CIF abans de crear;
- l'actualitzacio es fa per `GET`, amb dades fiscals i correu a la URL;
- el JS rep `idResponsable` per obrir el modal, pero l'actualitzacio posterior nomes envia `idEntitat`, per tant cal confirmar com s'actualitza el responsable;
- no hi ha avís visible quan una entitat ja te factures emeses;
- no hi ha log visible d'historial de canvis de dades fiscals d'entitat.

Objectiu funcional:

- crear entitats que poden actuar com a receptor fiscal de factures d'empresa/responsable;
- guardar la persona que gestiona l'entitat;
- editar dades d'entitat i responsable;
- alimentar desplegables o seleccions d'entitat en pantalles com `Generar factura abans de pagar`.

Pantalla actual rebuda:

- titol `Alumnes / Genera/Edita entitats`;
- bloc `AFEGEIX L'ENTITAT`;
- seccio `DADES DE L'ENTITAT`;
- seccio `DADES DE LA PERSONA QUE GESTIONA L'ENTITAT`;
- boto `AFEGEIX`;
- bloc `ENTITATS TROBADES`;
- taula d'entitats amb accio d'edicio.

Camps actuals de dades de l'entitat:

- `CIF`;
- `RAO`;
- `ADRECA`;
- `CP`;
- `POBLACIO`.

Camps actuals de la persona responsable/gestora:

- `NOM`;
- `COGNOMS`;
- `CORREU`.

Taula `ENTITATS TROBADES`:

- `ID`;
- `CIF`;
- `RAO`;
- `ADRECA`;
- `CP`;
- `POBLACIO`;
- `NOM`;
- `COGNOMS`;
- `CORREU`;
- `ACCIO`.

Modal d'edicio actual:

- titol `Modifica les dades de l'entitat`;
- repeteix camps d'entitat i responsable;
- botons `CANCELAR` i `DESA`.

Impacte VERI*FACTU:

- aquestes dades poden ser origen del receptor fiscal d'una factura;
- en emetre una factura, el SIF ha de copiar snapshot fiscal complet: CIF, rao, adreca, CP i poblacio;
- editar l'entitat despres no pot modificar factures ja emeses;
- si una factura ja emesa te CIF/rao/adreca incorrectes, cal flux de rectificativa/substitucio, no editar l'entitat esperant que canviï la factura;
- el responsable d'entitat es contacte/gestor i destinatari possible de correus, pero no sempre es el receptor fiscal.

Canvis necessaris:

- validar format de CIF/NIF/NIE quan sigui possible;
- evitar duplicats per CIF, o avisar quan ja existeix una entitat amb el mateix CIF;
- separar clarament dades fiscals de l'entitat i dades de contacte del responsable;
- guardar historial de canvis d'entitat si les dades s'usen per facturacio;
- en pantalles de factura, seleccionar entitat per ID intern, no nomes pel text visible;
- quan s'emet factura, guardar snapshot al SIF i relacio amb entitat origen;
- si es modifica una entitat amb factures emeses, mostrar avís: "Aquest canvi afectara futures factures, no factures ja emeses".

Correus:

- el correu del responsable pot servir per enviar URL de pagament, factura o enllac segur;
- si ja s'ha pagat, els correus a responsable han de donar opcio de consultar factura/PDF/QR;
- cal documentar en cada flux si el destinatari es l'entitat, el responsable o un correu intern.

Pendent d'incorporar:

- regles de deduplicacio per CIF;
- confirmar si l'edicio crea sempre un nou responsable historic o si en alguns casos sobreescriu;
- si pot existir mes d'un responsable actiu per entitat o nomes un.

### 3.2. Passar pagaments

URL / fitxer:

```text
https://intranet.prisma.cat/alumnes/pagaments/
/alumnes/pagaments/ -> alumnes-pagaments.php
JS: alumnes-pagaments.js
```

Entrada PHP actual:

- `alumnes-pagaments.php` comprova sessio amb `inc/comprovarSessio.php`;
- si `$configOk` no es correcte, redirigeix a `https://intranet.prisma.cat/`;
- carrega Bootstrap, jQuery, Popper, Fontawesome i els CSS comuns;
- carrega `css/alumnes-pagaments.css`;
- carrega `general.js`;
- carrega `alumnes-pagaments.js`;
- crea nomes els contenidors `.sidebar` i `.mainpanel`;
- la pantalla real es carrega posteriorment via JS/AJAX, seguint el patro de `mostrarMain.php` i classe `Intranet.php`.

AJAX actuals documentats:

- `ajax/mostrarMain.php`, carrega la pantalla a partir de la URL;
- `ajax/alumnes/buscarInfomacioPagament.php`, cerca pagaments per DNI, codi regal o numero de factura;
- `ajax/alumnes/mostrarModalInfoPag.php`, mostra detall informatiu del pagament o registre;
- `ajax/alumnes/mostrarModalConfPag.php`, mostra modal de confirmacio quan el registre requereix previsualitzacio. Crida `mostrarModalConfPag($numFact)`;
- `ajax/alumnes/efectuarPagament.php`, aplica el pagament manual. Crida `efectuarPagament($idTipus, $tipus, $pagament, $dataPag, $banc, $obs, $numFact, $efact)`;
- `ajax/alumnes/analitzarFitxerTPV.php`, analitza el fitxer TPV pujat amb `FormData`.

Consultes internes de pagament confirmades al constructor:

- cerca individual: `buscarPagaments`;
- cerca packs: `buscarPagamentsPack`, `buscarInfoPack`, `cnsInscsPack`, `cnsDadesCursPack`;
- cerca grups: `buscarPersRespGrup`, `buscarPersRespGrup2`, `buscarPersGrup`, `buscarPagamentsGrup`, `buscarInfoPersRespGrup`, `buscarInfoEdGrup`, `buscarInfoEdGrupByFact`, `searchMembresGrup`, `searchMembresGrup2`;
- cerca per numero de factura: `buscarPagamentsByFact` i `buscarNomResPagamentsByFact`;
- regal: `buscarRegNoPayByCodi`, `buscarRegNoPayByDni`, `buscarRegalById`;
- actualitzacions actuals de pagament: `updDateInscr`, `updPayInscr`, `updPayInscr2`, `updFactInscr`, `updPayInscrByIdPag`, `updPayInscrByIdPag2`, `updDateInscrByIdPag`;
- fraccionament: `updFraccBD`, `updFraccBDByFact`;
- observacions i regal: `updPagObsByIdPag`, `updFactRegal`.

Lectura SIF d'aquestes consultes:

- les consultes de cerca ajuden a identificar l'origen operatiu del pagament (`INSCRIPCIO`, `GRUP`, `PACK`, `REGAL`, `FACTURA`);
- les consultes `updPay*`, `updDate*`, `updFact*` i `updFracc*` no poden ser la font fiscal final;
- en migracio, aquests updates nomes poden quedar com a sincronitzacio/resum despres de `issueInvoice()` i/o `registerPayment()`.

Seqüencia JS actual de cerca i pas de pagament:

1. `requestMain` carrega `ajax/mostrarMain.php` amb `urlPagina`.
2. L'usuari marca tipus d'inscripcio: `ALUMNE` (`I`) o `GRUP` (`G`).
3. El boto `#cercar-pagament` llegeix `dni`, `codiRegal`, `numFact` i `tipusInsc`.
4. El JS obliga a informar un sol criteri de cerca: DNI o codi regal o numero de factura.
5. Crida `buscarInfomacioPagament.php`.
6. La resposta HTML omple `#resultats-cerca`.
7. La icona/tipus de cada fila pot obrir `mostrarModalInfoPag.php`.
8. En modificar `PAGAMENT`, el JS recalcula visualment `PAGAT`.
9. En modificar `DATA PAG`, valida format `YYYY-MM-DD`, data no futura i avisa si fa mes de 5 dies.
10. En confirmar fila `.upd-inscripcio`, valida import, data i banc.
11. Si `efact` de la fila val `1`, obre `mostrarModalConfPag.php` abans de confirmar.
12. Si no, o despres de confirmar, crida `efectuarPagament.php` amb id, numero factura, tipus, import, data, banc, observacions i `efact`.

Seqüencia JS actual d'analisi TPV:

1. El formulari `#formTPV` comprova que hi hagi fitxer seleccionat.
2. Envia el fitxer a `analitzarFitxerTPV.php` per `POST` amb `FormData`.
3. Espera resposta JSON amb `state`.
4. `state = 1`: mostra success.
5. `state = 2`: mostra error controlat i pinta `registresPagErrors` a `#pagaments-tpv`.
6. Els errors poden obrir fitxa d'alumne o factura amb enllacos:
   - `https://intranet.prisma.cat/alumnes/mostrar-alumne/#/{dni}`;
   - `https://intranet.prisma.cat/alumnes/factura/#/{dni}`.
7. `state = 0`: mostra alerta.

Riscos tecnics actuals:

- `buscarInfomacioPagament.php` esta escrit amb el nom `Infomacio`, cal respectar el nom real mentre no es reanomeni;
- encara falta incorporar el PHP de `buscarInfomacioPagament.php` i `mostrarModalInfoPag.php` per saber quin metode concret de `Intranet.php` criden i en quin ordre fan servir les consultes confirmades;
- el recalcul de `PAGAT` es visual i surt de l'HTML, no del SIF;
- l'import es valida amb JavaScript i despres s'envia com a text;
- la data per passar pagament es valida al navegador, pero el servidor tambe l'haura de validar;
- el camp `efact` del JS pot confondre's amb la marca fiscal `E_FACT`; cal aclarir o reanomenar el concepte en la migracio;
- `efectuarPagament.php` rep dades per `GET`, incloent import, data, banc i observacions;
- el sistema detecta errors cercant `error` dins HTML en alguns fluxos;
- no hi ha idempotencia visible per evitar duplicar pagaments per doble clic o reintent;
- la conciliacio TPV depen de la resposta de `analitzarFitxerTPV.php`, pero encara falta documentar el format real del fitxer i les claus de conciliacio.

Pantalla actual rebuda:

- bloc `ANALITZA FITXER`;
- selector de fitxer TPV;
- boto `ANALITZA EL FITXER`;
- indicador d'ultim analisi;
- bloc `CERCA`;
- camps de cerca: `NIF/NIE`, `CODI REGAL`, `NUM FACTURA`;
- selector de tipus d'inscripcio: `ALUMNE` / `GRUP`;
- taula de resultats per passar pagament.
- el bloc d'analisi de fitxer i el bloc de cerca conviuen dins la mateixa pantalla;
- el bloc de cerca permet localitzar deutes/pagaments per alumne, grup, codi regal o numero de factura;
- el boto de confirmacio de cada fila passa o registra el pagament corresponent.

Bloc `ANALITZA FITXER`:

- camp de seleccio de fitxer;
- boto `ANALITZA EL FITXER`;
- mostra `Ultim analisi` amb data i hora;
- serveix per revisar fitxer TPV i detectar pagaments que s'han de conciliar.

Bloc `CERCA`:

- `NIF/NIE`;
- `CODI REGAL`;
- `NUM FACTURA`;
- selector `ALUMNE`;
- selector `GRUP`;
- boto `CERCA`.

Columnes actuals de la taula de resultats:

- `TIPUS`;
- `ANY`;
- `MES`;
- `CURS`;
- `DNI`;
- `A PAGAR`;
- `PAGAT`;
- `PAGAMENT`;
- `DATA PAG`;
- `BANC`;
- `OBSERVACIONS`;
- `FRACCIO`;
- `ACCIONS`.

Detall funcional de columnes:

- `TIPUS` indica si el registre correspon a alumne/grup o tipologia interna;
- `A PAGAR` es l'import total esperat de la inscripcio o concepte;
- `PAGAT` mostra el que consta pagat fins ara;
- `PAGAMENT` permet informar import nou a registrar;
- `DATA PAG` permet informar la data del pagament;
- `BANC` permet seleccionar el banc/metode;
- `OBSERVACIONS` mostra reclamacions, pagaments denegats, avisos i notes administratives;
- `FRACCIO` mostra si el pagament correspon a una fraccio;
- `ACCIONS` confirma la regularitzacio o pas de pagament.

Funcio prevista:

- registrar transferencia o pagament manual;
- registrar compensacio o saldo a favor;
- registrar pagament de morositat/reclamacio;
- registrar pagament de fraccio;
- registrar pagament relacionat amb codi regal quan correspongui;
- registrar pagament localitzat per numero de factura;
- registrar pagament de grup quan el selector sigui `GRUP`;
- cobrir una factura abans de cobrament;
- crear factura si encara no existeix factura fiscal i el cas ho requereix;
- evitar que passar pagament generi una segona factura quan ja hi ha una factura abans de cobrament.

Regla SIF:

```text
si existeix factura real -> registerPayment()
si no existeix factura -> issueInvoice()
```

Regla operativa mes concreta:

- si la inscripcio te factura SIF existent, el boto de confirmar pagament crida `registerPayment()`;
- si la factura es `EMESA_ABANS_COBRAMENT = 1`, nomes es registra cobrament contra aquella factura;
- si no hi ha factura i el pagament correspon a una venda que s'ha de facturar, el flux ha de fer `issueInvoice()` amb bloc `payment` dins una operacio controlada;
- si el pagament es una compensacio, s'ha de registrar el moviment i l'assignacio, no fer un simple canvi de `PAGAT`;
- si el registre ve d'un fitxer TPV, la conciliacio ha de buscar primer transaccio existent per referencia/IDPAG abans de crear res;
- si el registre s'informa manualment, cal guardar usuari, data, metode, import i motiu/observacio;
- els camps antics `PAGAMENT`, `DATA PAG`, `BANC`, `PAGAT` i `OBSERVACIONS` poden quedar sincronitzats com a compatibilitat, pero la font fiscal ha de ser `payment_transaction` i `payment_allocation`.

Contracte d'entrada SIF des de `Passar pagaments`:

| Tipus d'entrada | Clau de deduplicacio | Primer efecte SIF | Accio posterior |
| --- | --- | --- | --- |
| Transferencia amb referencia bancaria | `TRANSFERENCIA|REF:{REFERENCIA_BANCARIA}` | `payment_transaction` | `payment_allocation` contra factura existent o `issueInvoice()` amb bloc `payment` |
| Transferencia sense referencia | `TRANSFERENCIA|FACT:{NUM_FACT}|DATA:{DATA_PAG}|IMPORT:{IMPORT}|BANC:{BANC}` | `payment_transaction` | revisio de duplicats abans d'assignar |
| Pagament manual/regularitzacio | `MANUAL|{USUARI}|{ORIGEN}|{DATA}|{IMPORT}|{MOTIU}` | `payment_transaction` | assignacio explicita i log d'usuari |
| Pagament TPV conciliat manualment | `TPV|ORDER:{DS_ORDER}|IMPORT:{IMPORT}` o clau de linia TPV | `payment_transaction` si no existia | assignacio o incidencia segons coincidencies |

`payment_transaction` ha de guardar com a minim origen, metode, import, data del moviment, referencia externa, usuari o proces, estat i clau idempotent. `payment_allocation` ha de guardar quina part d'aquest moviment s'aplica a cada factura. Aixi es pot representar:

- una factura cobrada amb diversos pagaments;
- una transferencia que cobreix diverses factures;
- un pagament parcial;
- una devolucio o compensacio que no modifica la factura original;
- un reintent o doble clic que retorna el mateix resultat sense duplicar el moviment.

Errors que han de generar alerta o incidencia:

- factura abans de cobrament no detectada;
- pagament superior a pendent sense motiu;
- pagament duplicat pel mateix `IDPAG`/referencia bancaria;
- import amb decimals diferent del total pendent;
- pagament d'una inscripcio coberta per factura d'empresa/responsable;
- intent de passar pagament contra factura anul·lada o rectificada totalment;
- pagament sense dades fiscals suficients per emetre factura.
- pagament TPV acceptat que no es pot relacionar amb cap inscripcio/factura;
- pagament denegat o retornat que apareix com a pagat;
- registre amb `PAGAT` gairebe igual a `A PAGAR` pero amb diferencia residual, com pot passar per comissions, retorns o ajustos;
- diverses files candidates per al mateix pagament.

Correus:

- si el pagament queda registrat i existeix factura, el correu ha de donar opcio a consultar factura/PDF/QR;
- si el pagament queda pendent d'incidencia SIF, no s'ha d'enviar correu de factura definitiva fins que el SIF confirmi factura i document;
- si el pagament correspon a morositat/reclamacio, el text ha d'evitar repetir un enllac de pagament antic.

Pendent d'incorporar:

- metode exacte de `Intranet.php` per `buscarInfomacioPagament.php` i `mostrarModalInfoPag.php`;
- condicions exactes que decideixen si s'actualitza per `ID`, `IDPAG`, `FACTURA_RELACIONADA`, pack, regal o grup;
- criteri final de compensacio/saldo;
- si `BANC` es taula tancada, llista fixa o text lliure.

#### 3.2.1. Revisio especialitzada i criteri de tancament

El xat antic aporta tres riscos que han de quedar tancats abans d'implementar:

- el sistema actual pot "passar pagament" i, si cal, crear o actualitzar factura historica dins `efectuarPagament()`;
- el modal de confirmacio de factura existent parla d'actualitzar la factura, pero amb SIF final nomes s'ha de registrar cobrament contra la factura ja emesa;
- `efact` en aquesta pantalla no es la marca fiscal `E_FACT`, i s'ha de reanomenar o blindar per evitar decisions fiscals equivocades.

Taula de decisio final:

| Cas | Decisio SIF |
| --- | --- |
| Cerca per DNI, codi regal o factura | Acceptar nomes un criteri, validar al servidor i conservar auditoria de cerca quan deriva en accio fiscal. |
| Pagament manual amb factura SIF existent | `registerPayment()` contra `UUID_FACTURA`; no modificar receptor, import, concepte ni numero. |
| Factura emesa abans de cobrament | `registerPayment()` i actualitzacio d'estat de cobrament; no crear factura nova. |
| Venda facturable sense factura | `issueInvoice()` amb bloc `payment` dins una operacio idempotent. |
| Compensacio o saldo | Crear moviment identificat i assignacio; no escriure nomes `PAGAT`. |
| TPV autoritzat no conciliat | Crear incidencia o proposta de conciliacio; no marcar pagat automaticament sense clau estable. |
| Devolucio TPV | Flux de devolucio/rectificativa o incidencia, segons factura i estat; no simple import negatiu ocult. |
| Pagament superior al pendent o amb diferencia residual | Bloqueig o incidencia amb motiu; no update silencios. |

Regles d'implementacio:

- substituir el `GET` de `efectuarPagament.php` per una accio servidor/SIF amb `POST`, sessio, permisos i token;
- recalcular pendent al servidor amb dades SIF, no confiar en el recalcul visual de `PAGAT`;
- guardar usuari, data, metode, origen, import, referencia, observacio i idempotency key;
- separar sincronitzacio operativa antiga (`updPay*`, `updDate*`, `updFracc*`) de la font fiscal final (`payment_transaction` i `payment_allocation`);
- mantenir compatibilitat visual nomes despres que el SIF hagi acceptat l'operacio.

#### 3.2.2. Revisio especialitzada de Transferencia validada a intranet

El xat antic confirma el cas operatiu: quan un pagament es fa per transferencia, administracio el valida des de `Passar pagaments` i, a partir d'aquell moment, el flux final ha de cridar el SIF.

Informacio recuperada del codi antic:

- el JS `aplicarPagament()` envia a `ajax/alumnes/efectuarPagament.php` per `GET` els camps `id`, `numFact`, `tipus`, `pagament`, `dataPag`, `banc`, `obs` i `efact`;
- `efectuarPagament.php` deserialitza sessio i crida `efectuarPagament($idTipus, $tipus, $pagament, $dataPag, $banc, $obs, $numFact, $efact)`;
- si `efact == 0`, `efectuarPagament()` calcula any fiscal, tipus `A` o `R` segons signe de l'import, `buscarLastOrdreFact`, `numFact`, data actual i deriva per tipus: `R` regal, `G` grup, `P` pack o `I` inscripcio;
- si `efact != 0`, deriva a `efectuarPagamentFacturaGenerada($numFact, $tipus, $obs, $dataPagament, $importPag, $formaPagament)`;
- `mostrarModalConfPag($numFact)` consulta `buscarInfoFacturaByNum`, busca responsable d'entitat amb `buscarRespEntitatByCIF` i mostra un avis historic dient que s'actualitzara la factura i s'enviara o no missatge a l'entitat;
- `efectuarPagamentFacturaGenerada()` consulta `buscarPagamentsByFact`, recupera `A_PAGAR`, `PAGAMENT`, `FACTURA_RELACIONADA`, `FRACCIO`, `pag_observacions`, `IDPAG`, `cif` i `E_FACT`, i calcula `pendentPagar = A_PAGAR - PAGAMENT - importPag`;
- si la factura correspon a pack, extreu `PACK|id` de `OBSERVACIONS` i consulta `cnsDadesCursPack`; si no, consulta `buscarInfoEdGrupByFact`;
- el codi historic actualitza `factures` amb `updFactGenerada`: `data_pagament`, `IMPORT` i `FORMA_PAGAMENT` segons `NUM`;
- despres reparteix l'import sobre els membres de la factura amb `searchMembresFactRel`, `updPayInscr` i `updDateInscr`, omplint `DATA PAG` nomes quan cada registre queda totalment pagat;
- si queda fraccionat, afegeix `(<import> EUR <data>)` a `FRACCIO` i usa `updFraccBDByFact`;
- el correu de confirmacio a l'entitat/responsable informa concepte, correu, import, resultat acceptat, data i import pendent si n'hi ha.

Decisio SIF:

```text
transferencia validada a intranet
    -> validar permis, sessio, import, data, metode, observacio i referencia si existeix
    -> recalcular pendent al servidor
    -> si factura SIF existent: registerPayment()
    -> si no hi ha factura i el cobrament crea obligacio fiscal: issueInvoice() amb bloc payment
    -> sincronitzar PAGAMENT, DATA PAG, FRACCIO i factures historiques nomes com a resum
```

El comportament historic `updFactGenerada` no pot modificar una factura VERI*FACTU ja emesa. En el SIF, el cobrament per transferencia es un `payment_transaction` amb `METODE = TRANSFERENCIA` o el banc/metode final, i es vincula a la factura amb `payment_allocation`.

Idempotencia recomanada si no hi ha referencia bancaria externa:

```text
TRANSFERENCIA|FACT:{NUM_FACT}|DATA:{DATA_PAG}|IMPORT:{IMPORT}|BANC:{BANC}
```

Si hi ha referencia bancaria, aquesta ha de prevaldre:

```text
TRANSFERENCIA|REF:{REFERENCIA_BANCARIA}
```

Pendent especific:

- recuperar o confirmar els cossos de `buscarInfomacioPagament.php` i `mostrarModalInfoPag.php`;
- definir llista final de `BANC`/metodes i correspondencia amb `payment_transaction.METODE`;
- decidir si la referencia bancaria sera obligatoria en transferencies d'import alt o d'empresa;
- implementar bloqueig de doble clic/reintent i comprovacio de pagament duplicat;
- provar transferencia parcial, transferencia que tanca una factura abans de cobrament, transferencia contra factura d'empresa/responsable i transferencia amb import superior al pendent.

### 3.3. Generar factura abans de pagar

URL / fitxer:

```text
https://intranet.prisma.cat/alumnes/genera-factura-abans-pagar/
/alumnes/genera-factura-abans-pagar/ -> alumnes-genera-factura-abans-pagar.php
JS: alumnes-genera-factura-abans-pagar.js
```

AJAX actuals documentats:

- `ajax/mostrarMain.php`, carrega la pantalla a partir de la URL;
- `ajax/alumnes/mostrarInformacioInscripcio_generaFactura.php`, crida `mostrarInformacioInscripcio_generaFactura_Alumnes($dni)`;
- `ajax/alumnes/calcularTextData.php`, converteix mes numeric a text;
- `ajax/alumnes/generaFacturaElectronica_Factures.php`, crida `generarFacturaElectronica_Alumnes(...)`;
- `ajax/alumnes/mostraDadesFacturaElectronica_Factures.php`, crida `mostraDadesFacturaElectronica_Alumnes($factura)`;
- `ajax/alumnes/mostraInscripcionsFacturaElectronica_Factures.php`, crida `mostraInscripcionsFacturaElectronica_Alumnes($factura)`;
- `ajax/alumnes/mostraPrevFactura_Factures.php`, crida `modalConsultaFactura_Factures($factura)`;
- `ajax/alumnes/descarregaFactura.php`, crida `generaFactura($id, true)`;
- `ajax/alumnes/eliminarArxiu.php`, elimina el fitxer temporal descarregat.

Consultes internes relacionades confirmades al constructor:

- inscripcions candidates sense factura: `buscarInfoInscDniData`;
- cerca d'inscripcions ja vinculades: `buscarInscFactRel`;
- numeracio local historica: `buscarLastOrdreFact`, `buscarLastFact`;
- alta local historica de factura: `insertFactura`;
- vinculacio d'inscripcions a factura abans de pagar: `updInscFacturaPrePag`;
- dades finals de factura: `buscarInfoFactura`, `buscarInfoFacturaByNum`, `buscarInfoFacturaGener`;
- dades de factura per PDF/modal: `buscarInfoFacturaId`, `buscarInfoFacturaByFact`.

Lectura SIF:

- `insertFactura` i `updInscFacturaPrePag` son els punts que cal substituir per `issueInvoice()` i creacio de `fact_rels`;
- la marca final ha de ser `EMESA_ABANS_COBRAMENT = 1` al SIF, no nomes una observacio textual;
- les inscripcions seleccionades han de quedar vinculades per UUID de factura, encara que es mantingui `FACTURA_RELACIONADA` per compatibilitat.

Entrada PHP actual:

- `alumnes-genera-factura-abans-pagar.php` comprova sessio amb `inc/comprovarSessio.php`;
- si `$configOk` no es correcte, redirigeix a `https://intranet.prisma.cat/`;
- carrega estructura buida amb `.sidebar` i `.mainpanel`;
- carrega `general.js`;
- carrega `alumnes-genera-factura-abans-pagar.js`;
- la pantalla real es renderitza despres via AJAX amb `mostrarMain.php`.

Dependencia de `general.js`:

- carrega menu lateral i usuari;
- carrega apartats del menu;
- consulta rols d'edicio amb `consultaRolsEdicio.php`;
- consulta rols de l'usuari amb `consultaRolsUsuari.php`;
- omple `tePermisEdicio`;
- proporciona modals globals de loading, error, success i validacions.

Seqüencia JS actual:

1. `requestMain` carrega `ajax/mostrarMain.php?url={urlPagina}`.
2. L'usuari cerca per `NIF/NIE`; el boto `#cercar-alumne` crida `mostrarInformacioInscripcio_generaFactura.php`.
3. El resultat omple `#resultats-cerca` amb les inscripcions candidates.
4. L'usuari afegeix files amb `.add-inscripcio`; el JS copia els `td` de la taula de resultats i els posa a `#insc-rel-fact-rel`.
5. `#continue-pas-2` comprova `tePermisEdicio`, inscripcions seleccionades, curs unic i edicio unica.
6. El JS calcula:
   - `idsInsc`;
   - `preuTotal`;
   - `cursos`;
   - `edicions`;
   - `concepte1`;
   - `concepte2`.
7. `#continue-pas-3` valida `entitatMarcada`, `concepte1` i `preu`, i crida `generaFacturaElectronica_Factures.php`.
8. Si el PHP retorna factura creada, el JS demana dades de factura i inscripcions relacionades.
9. La previsualitzacio crida `mostraPrevFactura_Factures.php`.
10. La descarrega crida `descarregaFactura.php` i despres `eliminarArxiu.php`.

Flux actual:

- cerca inscripcions per `NIF/NIE`;
- mostra resultats amb `ANY`, `MES`, `CURS`, `INCRIT`, `NOM`, `COGNOMS`, `DNI`, `A PAGAR`, `IDPAG` i accio `+`;
- permet afegir diverses inscripcions a `INSCRIPCIONS RELACIONADES AMB LA FACTURA A GENERAR`;
- nomes deixa continuar si hi ha inscripcions seleccionades;
- suma els imports `A_PAGAR`;
- agrupa cursos i edicions;
- rebutja seleccionar diferents cursos o diferents edicions;
- construeix `CONCEPTE1` amb curs i participants;
- construeix `CONCEPTE2` amb convocatoria;
- permet triar `ENTITAT`, revisar `CONCEPTE1`, veure `PREU` i afegir `OBSERVACIONS`;
- genera factura real amb numero visible tipus `A2026/774`;
- mostra dades de factura i inscripcions relacionades;
- permet previsualitzar i descarregar PDF.

Problema detectat:

- si posteriorment es passa pagament i no es detecta la factura previa, es pot generar una factura duplicada.
- el nom de metode `generarFacturaElectronica_Alumnes` pot confondre amb `E_FACT`, pero funcionalment aquest apartat crea una factura abans de cobrament;
- la marca correcta d'aquest cas no es `E_FACT` per defecte, sino `EMESA_ABANS_COBRAMENT = 1`;
- `E_FACT` s'ha de decidir a `Consulta - Edita - Anula factura` quan pertoqui;
- la previsualitzacio/descarrega actual regenera PDF a partir de dades vives; amb SIF el PDF ha de sortir de `factura_documents`.

Riscos tecnics del codi actual a resoldre en la migracio:

- `idsInsc` es global i no es reinicialitza dins `#continue-pas-2`; si es torna enrere o es repeteix el pas, pot acumular ids duplicats;
- `entitatMarcada` guarda text visible, no necessariament l'id intern ni el snapshot fiscal complet de receptor;
- el calcul de `preuTotal` surt de l'HTML (`#apagar-{id}`) i s'ha de recalcular/validar al servidor abans d'emetre factura;
- la validacio de curs/edicio es fa al navegador i s'ha de repetir al servidor/SIF;
- `concepte2` es calcula amb una crida AJAX asíncrona a `calcularTextData.php`; cal evitar emetre factura abans que el concepte estigui resolt;
- el sistema detecta errors buscant la paraula `error` dins HTML, no amb respostes estructurades;
- no hi ha idempotencia visible al client; un doble clic, reintent o recarrega pot intentar generar dues factures;
- `descarregaFactura.php` regenera factura amb `generaFactura($id, true)`; amb SIF s'ha de servir el PDF immutable ja generat;
- `eliminarArxiu.php` rep `filename` i fa `unlink($filename)`; cal substituir-ho per gestio segura de fitxers temporals o eliminar el patró si el document surt de `factura_documents`;
- cal comprovar permisos tambe al servidor, no nomes amb `tePermisEdicio` carregat per AJAX.

Canvi VERI*FACTU:

- considerar sempre aquest document com a factura real abans de cobrament;
- cridar `issueInvoice()` amb tipus d'operacio `FACTURA_ABANS_COBRAMENT`;
- crear factura, linies, registre fiscal, hash, PDF/QR i relacions en una operacio SIF;
- marcar `EMESA_ABANS_COBRAMENT = 1`;
- no marcar `E_FACT` automaticament;
- crear relacions a `fact_rels` per cada inscripcio seleccionada;
- guardar snapshot del receptor fiscal de l'entitat/responsable triat;
- generar linies fiscals estructurades a partir de les inscripcions, imports i descomptes;
- conservar `concepte1` i `concepte2` com a text visible, pero no com a unica font fiscal;
- generar clau d'idempotencia per evitar repetir factura si l'usuari reintenta la mateixa operacio;
- si posteriorment arriba pagament, nomes `registerPayment()`;
- desactivar enllacos TPV individuals de les inscripcions cobertes per aquesta factura quan el pagament s'ha de fer per URL d'empresa/responsable;
- permetre URL especifica de pagament per factura d'empresa abans de cobrament quan correspongui.
- en correus o missatges posteriors, si la factura ja esta pagada, mostrar opcio de consultar factura/PDF/QR en lloc d'insistir nomes en pagar.

Payload SIF esperat:

```text
operacio: FACTURA_ABANS_COBRAMENT
origen: INTRANET
source_type: INSCRIPCIO_MULTIPLE
source_ids: idsInsc
receptor_fiscal: snapshot de l'entitat/responsable
linies: linies estructurades per inscripcio/participant o agrupacio decidida
concepte_visible_1: concepte1
concepte_visible_2: concepte2
import_total: calculat i validat al servidor
observacions: observacions
emesa_abans_cobrament: 1
e_fact: 0 per defecte
idempotency_key: derivada de origen + operacio + inscripcions + receptor + import
```

Regla de pantalla final:

- pas 1: cerca i seleccio d'inscripcions;
- pas 2: receptor fiscal, concepte visible, linies calculades, import total, observacions, marca `EMESA_ABANS_COBRAMENT`;
- pas 3: factura emesa, numero visible, estat AEAT, estat cobrament pendent, PDF/QR i enllac de pagament si toca;
- si falla SIF, crear incidencia a `errors_verifactu` i notificacio interna, no crear una factura local alternativa.

#### 3.3.1. Revisio especialitzada i criteri de tancament

Aquest flux queda definit com a emissio fiscal abans del cobrament, no com a proforma ni com a factura electronica per defecte.

Taula de decisio final:

| Cas | Decisio SIF |
| --- | --- |
| Inscripcions del mateix curs i edicio, sense factura previa | `issueInvoice()` amb `EMESA_ABANS_COBRAMENT = 1`. |
| Inscripcio ja vinculada a factura SIF | Bloquejar o mostrar factura existent; no emetre duplicat. |
| Inscripcions de cursos o edicions diferents | Bloquejar al servidor, encara que el JS ja ho hagi bloquejat. |
| Receptor fiscal empresa/responsable | Guardar snapshot fiscal complet i relacio amb entitat origen. |
| Dades d'entitat insuficients | Bloquejar emissio i demanar correccio abans de crear factura. |
| Reintent, doble clic o recarrega | Retornar la mateixa factura per idempotencia, no crear-ne una altra. |
| Pagament posterior | `registerPayment()` contra la factura existent. |
| Necessitat de factura electronica | Accio separada `E_FACT`, no automatica en aquest flux. |

Regles d'implementacio:

- reinicialitzar `idsInsc` abans de recalcular el pas 2 o, millor, reconstruir la seleccio al servidor;
- no acceptar `preuTotal`, `concepte1`, `concepte2`, `cursos` o `edicions` com a font fiscal sense recalcul;
- convertir `empresa` de text visible a ID intern i snapshot fiscal;
- substituir respostes HTML amb cerca de `error` per resposta estructurada;
- garantir que `concepte2` estigui resolt abans de cridar `issueInvoice()`;
- corregir o eliminar punts fragils com `rerrorFunction` i comprovacions d'error sobre variables equivocades;
- eliminar el patro `descarregaFactura.php` + `eliminarArxiu.php` per documents SIF nous;
- conservar la factura encara que falli PDF/QR o AEAT, obrint incidencia o cua de retry.

Proves minimes:

- emissio correcta d'una factura abans de cobrament;
- reintent de la mateixa operacio sense duplicar factura;
- inscripcio ja facturada bloquejada;
- barreja de cursos o edicions bloquejada al servidor;
- entitat sense dades fiscals suficients bloquejada;
- PDF/QR servit des de document immutable;
- pagament posterior registrat amb `registerPayment()`;
- URL individual desactivada quan la factura es d'empresa/responsable.

### 3.4. Consulta - Edita - Anula factura

URL / fitxer:

```text
https://intranet.prisma.cat/alumnes/factura/
/alumnes/factura/ -> alumnes-factura.php
JS: alumnes-factura.js
```

Entrada PHP actual:

- `alumnes-factura.php` comprova sessio amb `inc/comprovarSessio.php`;
- si `$configOk` no es correcte, redirigeix a `https://intranet.prisma.cat/`;
- el `<title>` actual del fitxer es `Consulta / Anul·la factura`, tot i que l'apartat visible es `Consulta - Edita - Anul·la factura`;
- carrega Bootstrap, jQuery, Fontawesome i els CSS comuns;
- carrega `css/alumnes-factura.css`;
- carrega `general.js`;
- carrega `alumnes-factura.js`;
- crea nomes els contenidors `.sidebar` i `.mainpanel`;
- la pantalla real es carrega posteriorment via JS/AJAX, seguint el patro de `mostrarMain.php` i classe `Intranet.php`.

AJAX actuals documentats:

- `ajax/mostrarMain.php`, carrega la pantalla a partir de la URL;
- `ajax/alumnes/consultaUsuarisFacturaRelacionada.php`, cerca usuaris/factures per DNI, email, factura relacionada o numero de factura. Crida `buscarUsuaris_Factures($dni, $email, $factRel, $factNum)`;
- `ajax/alumnes/mostrarTaulaUsuaris2.php`, mostra taula intermedia quan hi ha mes d'un usuari candidat. Crida `mostrarTaulaUsuaris2_Alumnes($dnies, $orderBy, $asc)`;
- `ajax/alumnes/mostrarTotesFacturesUsuari_Factures.php`, mostra totes les factures d'un usuari/DNI. Crida `mostrarTotesFacturesUsuari_Factures($dni, $cercaPer)`;
- `ajax/alumnes/mostraModalConsultaInformacio_Factures.php`, mostra modal d'informacio de factura. Crida `modalConsultaInformacio_Factures($id)`;
- `ajax/alumnes/guardarDadesFactura_Factures.php`, guarda dades editades de factura. Crida `guardarDadesFactura_Factures($id, $factura, $rao, $cif, $cp, $poblacio, $adreca, $concepte1, $concepte2, $obs)`;
- `ajax/alumnes/mostrarModalAnulaFactura_Factures.php`, mostra modal d'anul·lacio. Crida `modalAnularFactura_Factures($id)`;
- `ajax/alumnes/anularFactura_Factures.php`, executa l'anul·lacio/devolucio actual. Crida `anularFactura($id, $tornar, $dataDevol, $obs)`;
- `ajax/alumnes/mostraModalPrevFactura_Factures.php`, mostra previsualitzacio PDF. Crida `modalPrevisualitzaFactura_Factures($id)`;
- `ajax/alumnes/descarregaFactura.php`, genera/descarrega factura. Crida `generaFactura($id, true)` amb Dompdf carregat.

Consultes internes de factura confirmades al constructor:

- cerca per correu, factura relacionada o numero: `buscaIdFactCorreu`, `buscaIdFactCorreuID`, `buscaIdFactRel`, `buscaIdFactRelID`, `buscaIdFactNum`, `buscaIdFactNumID`;
- cerca per CIF/DNI i relacions: `buscaDniIdFact`, `buscaFactRelInsc`, `buscaFactRelFact`, `buscaIdFactCif`, `buscarIdFact`;
- llistat de factures: `buscarTotesFactId`;
- dades de factura: `buscarInfoFacturaId`, `buscarInfoFacturaByFact`, `buscarInfoFactura`, `buscarInfoFacturaByNum`, `buscarInfoFacturaGener`;
- dades d'inscripcio vinculades a factura: `buscarInfoFactInsc`, `buscarInfoFactInsc2`, `buscarInscFactRel`;
- edicio directa actual: `updDadesFact`;
- anul·lacio/retorn actual sobre inscripcions: `updInscAnulFact`, `updInscAnulFact2`, `updInscDataPagAnulFact`, `updInscFraccAnulFact`;
- observacions de factura: `updObsFact`.

Lectura SIF d'aquestes consultes:

- `updDadesFact` es el punt critic que s'ha de bloquejar o reconvertir en flux de rectificativa/substitucio;
- `updInscAnulFact*` i `updInscDataPagAnulFact` indiquen que l'anul·lacio actual toca resum economic d'inscripcions, pero no modela encara `payment_transaction`, `payment_allocation`, saldo o devolucio;
- `buscarInfoFacturaId` i `buscarInfoFacturaByFact` confirmen que `E_FACT` viu a `web.factures` i que la pantalla actual el pot acabar gestionant com a marca administrativa.

Entrades per URL/hash:

- `#/dni/{valor}` omple el camp `dni` i dispara cerca;
- `#/factRel/{valor}` omple `fact-rel` i dispara cerca;
- `#/factNum/{valor}` omple `fact-num` i dispara cerca.

Seqüencia JS actual de cerca:

1. `requestMain` carrega `ajax/mostrarMain.php` amb `urlPagina`.
2. El boto `#cercar-factura` llegeix `dni`, `email`, `factRel` i `factNum`.
3. Permet cercar per un o diversos camps.
4. Construeix el text `RESULTATS DE LA CERCA PER ...`.
5. Crida `consultaUsuarisFacturaRelacionada.php`.
6. Si hi ha un sol DNI candidat, crida directament `cercarUSuari(dni)`.
7. Si hi ha mes d'un candidat, crida `mostrarTaulaUsuaris2.php`.
8. En seleccionar usuari, crida `mostrarTotesFacturesUsuari_Factures.php`.
9. Sobre la taula de factures activa accions:
   - `.cns-informacio`;
   - `.anula-factura`;
   - `.prev-factura`.

Seqüencia JS actual d'edicio de dades de factura:

1. `.cns-informacio` obre `mostraModalConsultaInformacio_Factures.php`.
2. El llapis `.editar-apartat` converteix elements `.editables` en inputs.
3. `.save-result` llegeix `id`, `factura`, `rao`, `cif`, `cp`, `poblacio`, `adreca`, `concepte1`, `concepte2` i `obs`.
4. Valida nomes que `rao`, `cif` i `concepte1` no estiguin buits.
5. Envia `GET` a `guardarDadesFactura_Factures.php`.
6. Si el resultat no conte `error`, substitueix inputs per text i mostra success.

Seqüencia JS actual d'anul·lacio:

1. `.anula-factura` comprova `tePermisEdicio`.
2. Obre `mostrarModalAnulaFactura_Factures.php`.
3. `.confirma-baixa` llegeix `id`, `A TORNAR`, `DATA DEVOLUCIO` i observacions.
4. Valida import numeric i data amb `validData()`.
5. Envia `GET` a `anularFactura_Factures.php`.
6. Si va be, torna a clicar `#cercar-factura`, mostra success i deixa la taula refrescada.

Seqüencia JS actual de PDF:

1. `.prev-factura` obre `mostraModalPrevFactura_Factures.php`.
2. El modal permet navegar pagines amb `.fletxa-left` i `.fletxa-right`.
3. `.download-factura` comprova `tePermisEdicio`.
4. Crida `descarregaFactura.php` amb l'id de factura.
5. Crea un link temporal i força descarrega del fitxer retornat.

Riscos tecnics actuals:

- `guardarDadesFactura_Factures.php` edita directament dades de factura ja emesa;
- l'edicio inclou receptor (`rao`, `cif`, adreca) i conceptes, que amb SIF no poden sobreescriure la factura;
- l'edicio i anul·lacio s'envien per `GET`;
- no hi ha motiu obligatori per editar receptor o conceptes;
- l'anul·lacio usa classe/boto `.confirma-baixa`, nom que pot confondre amb baixa d'inscripcio;
- `A TORNAR` queda validat com a numero, pero s'ha de vincular a devolucio, saldo o rectificativa;
- la descarrega crida `descarregaFactura.php` i dins del flux sembla tornar-la a cridar en lloc d'eliminar fitxer, cal revisar-ho quan es migri;
- la comprovacio de volum `dnies.split('|') > 2000` no comprova longitud; caldria `dnies.split('|').length`;
- el sistema detecta errors cercant `error` dins HTML;
- la previsualitzacio/descarrega actual pot regenerar factura a partir de dades vives, no garantir document immutable.

Pantalla actual rebuda:

- cerca per `NIF/NIE`;
- cerca per `Email`;
- cerca per `Factura relacionada`;
- cerca per `Num. factura`;
- resultats en taula amb `FACTURA REL.`, `ANY`, `N. FACT`, `RAO`, `CIF`, `CURS` i `ACCIONS`;
- accions visibles: informacio, anul·lacio i PDF.
- els resultats poden incloure factures normals `A...` i rectificatives `R...`;
- una mateixa `FACTURA REL.` pot aparèixer en diverses files si hi ha diverses factures o moviments relacionats;
- les icones d'accio poden aparèixer actives o amb opacitat/desactivades segons si l'accio es pot executar.

Passos actuals observats:

1. L'usuari informa un criteri de cerca: DNI/NIE, email, factura relacionada o numero de factura.
2. La pantalla mostra un bloc de resultats amb el text de cerca, per exemple `RESULTATS DE LA CERCA PER DNI`.
3. Es mostren totes les factures localitzades.
4. Des de cada fila es pot:
   - obrir informacio;
   - iniciar anul·lacio si la icona esta activa;
   - veure o descarregar PDF.

Modal actual d'informacio:

- `DADES INSCRIPCIO`: `FRACCIONAT`, `FRACCIO`, `OBS PAG`, `DNI`, `A PAGAR`;
- `DADES FACTURA`: `FACTURA`, `NUM`, `ANY`, `ORDRE`, `DATA`, `DATA PAGAMENT`, `RAO`, `CIF`, `CODI POSTAL`, `POBLACIO`, `ADRECA`, `IMPORT`, `CURS`, `HORES`, `CONCEPTE1`, `CONCEPTE2`, `OBSERVACIONS`;
- hi ha icona de llapis a dades de factura, que amb SIF no pot representar edicio directa d'una factura ja emesa.
- pot mostrar imports positius o negatius segons si es factura ordinaria o rectificativa historica;
- la informacio de `DADES INSCRIPCIO` i `DADES FACTURA` no sempre representa el mateix concepte: una cosa es la inscripcio i una altra el document fiscal emes.

Modal actual d'anul·lacio:

- `ANY-CURS`;
- `N. FACT`;
- `PAGAT`;
- `A TORNAR`;
- `DATA PAGAMENT`;
- `DATA DEVOLUCIO`;
- `OBSERVACIONS NOVA FACTURA`;
- boto `CONFIRMA L'ANUL·LACIO`.
- `A TORNAR` pot correspondre a devolucio total o parcial;
- `OBSERVACIONS NOVA FACTURA` s'ha de convertir en motiu/observacio vinculada a la rectificativa o devolucio, no en edicio lliure d'una factura existent.

Vista PDF actual:

- previsualitza factura historica;
- permet descarrega;
- pot mostrar factura normal o rectificativa negativa.
- mostra PDF antic generat amb logo, dades receptor, concepte, import i text d'exempcio IVA;
- pot mostrar diverses pagines en el modal;
- pot mostrar indicacio `ES COPIA` en factures antigues;
- amb SIF, la previsualitzacio ha de sortir de document immutable, no de regenerar HTML/PDF amb dades vives.

Aquest apartat sera el lloc principal per gestionar accions sobre factures ja emeses:

Canvi VERI*FACTU:

- no editar factura emesa;
- generar rectificativa;
- registrar devolucio quan correspongui;
- vincular rectificativa amb factura original.
- marcar o desmarcar una factura com a factura electronica (`E_FACT`) quan correspongui;
- registrar qui fa el canvi, data i motiu intern.
- substituir el llapis d'edicio directa per accions controlades: rectificar dades fiscals, rectificar import, registrar devolucio, marcar `E_FACT`, veure historial;
- exigir motiu quan es faci anul·lacio, devolucio o rectificativa;
- calcular automaticament el tipus de rectificativa segons el cas definit;
- conservar factura original i crear una factura rectificativa nova, no sobreescriure;
- si es canvia receptor fiscal, concepte o import, obrir flux de rectificativa/substitucio segons normativa aplicable;
- si es torna import, registrar devolucio i vincular-la a la rectificativa o saldo corresponent;
- mostrar estat SIF, estat AEAT, estat cobrament, PDF immutable i QR.

Riscos actuals a resoldre:

- el nom de pantalla inclou `Edita`, pero la factura emesa no es pot editar com a registre fiscal;
- el llapis pot portar a una edicio directa de dades de factura si no es reconverteix;
- anul·lar no pot ser un update o una factura negativa generada sense tipificacio fiscal;
- `A TORNAR` no pot quedar desconnectat del pagament, devolucio, saldo o rectificativa;
- els PDFs antics poden continuar essent consultables, pero s'han d'etiquetar com a historics no VERI*FACTU quan no surtin del SIF;
- si es canvia el CIF/rao social per corregir receptor, cal flux de rectificativa/substitucio, no sobreescriptura.

Accions finals proposades:

- `Veure informacio`: lectura de dades d'inscripcio, factura, pagaments i rectificatives;
- `Rectificar dades fiscals`: canvi receptor/CIF/rao/adreca amb motiu;
- `Rectificar import`: diferencia positiva o negativa segons cas;
- `Registrar devolucio`: sortida de diners vinculada a rectificativa o saldo;
- `Marcar E_FACT`: marca administrativa de factura electronica;
- `Veure PDF/QR`: document immutable;
- `Veure historial`: log d'accions, usuari, data, motiu i documents creats.

Regla:

```text
Factura abans de pagar -> EMESA_ABANS_COBRAMENT = 1
Factura electronica -> E_FACT = 1
```

La marca `E_FACT` es gestiona des de `Alumnes / Consulta - Edita - Anula factura`, no des de la generacio automatica de factura abans de cobrament.

Permisos:

- marcar/desmarcar `E_FACT`: Meriem, Adam i Pablo;
- anul·lar/rectificar: rols autoritzats segons decisio interna;
- consulta PDF: segons permisos actuals de consulta de factura.

Pendent d'incorporar:

- cataleg de motius que el sistema convertira en tipus de rectificativa;
- validar contra el codi productiu el cos complet de `anularFactura()` i l'ordre final d'updates, sabent que el xat antic ja confirma la creacio historica d'una factura `R` negativa i l'actualitzacio posterior de resums d'inscripcio.

#### 3.4.1. Revisio especialitzada i criteri de tancament

El xat antic confirma que el punt mes sensible no es la consulta, sino les accions que avui modifiquen una factura ja emesa:

- `guardarDadesFactura_Factures()` fa `updDadesFact` i modifica directament dades de `web.factures`;
- `anularFactura()` crea una factura historica `R` amb numeracio local `R{any}/{ordre}`, import negatiu i dades copiades de la factura original;
- despres de crear la factura `R`, el flux toca imports i observacions d'inscripcions per reflectir el retorn;
- el modal antic ja avisava, encara que comentat, que una factura relacionada amb diverses inscripcions podia requerir ajust manual de pagaments;
- l'antic camp `A TORNAR` no diferencia devolucio real, saldo intern, compensacio o rectificativa fiscal.

Decisio funcional final:

| Cas | Accio final |
| --- | --- |
| Consulta de factura | Lectura de factura original, rectificatives, pagaments, devolucions, PDF/QR i estat AEAT. |
| Canvi de receptor o dades fiscals | Rectificativa/substitucio amb motiu; mai `updDadesFact` sobre factura SIF emesa. |
| Canvi de concepte o import | Rectificativa per diferencia o substitucio segons cataleg de motius. |
| Anul·lacio total | Rectificativa SIF vinculada a l'original i registre de devolucio o saldo. |
| Retorn parcial | Rectificativa parcial i `payment_transaction` de devolucio, saldo o compensacio. |
| Factura historica no VERI*FACTU | Consulta i marca historica; no es converteix en precedent per modificar factures SIF. |
| Marca `E_FACT` | Accio administrativa separada amb usuari, data, motiu i log. |
| PDF | Consulta de `factura_documents`; els PDFs antics s'etiqueten com a historics o copia. |

Regles d'implementacio:

- eliminar o bloquejar l'us SIF de `guardarDadesFactura_Factures.php` per a factures emeses;
- substituir `anularFactura_Factures.php` per una accio SIF amb `POST`, idempotencia i validacio servidor;
- no recalcular imports amb `floatval`; els imports han d'anar amb decimal controlat;
- no actualitzar `PAGAMENT` o `DATA PAG` d'inscripcio com a font fiscal principal; el resum d'inscripcio s'ha de derivar de pagaments, assignacions, devolucions i rectificatives;
- quan hi ha diverses inscripcions en una factura, el sistema ha de mostrar i guardar assignacions abans de confirmar;
- cada rectificativa o devolucio ha de conservar factura original, usuari, data, motiu, import, document generat i estat AEAT;
- `E_FACT` no es clona automaticament des de la factura original sense una politica explicita.

### 3.5. Analitzar fitxer TPV

Ubicacio actual observada:

```text
https://intranet.prisma.cat/alumnes/pagaments/
Bloc: ANALITZA FITXER
```

Funcio actual:

- analitza un CSV de TPV;
- comprova si existeix factura associada als pagaments;
- permet detectar pagaments no passats.
- mostra l'ultim analisi realitzat.

AJAX actual:

- `ajax/alumnes/analitzarFitxerTPV.php`, rep el fitxer per `POST` i torna JSON amb `state`, `msg` i, si hi ha incidencies, `registresPagErrors`.

Dades de `web.factures` implicades segons constructor i codi TPV:

- `NUM_COMANDA`, per lligar Redsys/TPV amb factura quan existeix comanda;
- `data_pagament`, per comparar data del TPV;
- `import`, amb signe positiu per autoritzacio i negatiu per devolucio;
- `TIPUS`, amb `A` per autoritzacio/factura ordinaria i `R` per devolucio/rectificativa historica;
- `cif`, com a segon criteri quan no hi ha `NUM_COMANDA`;
- `factura_relacionada` i `num`, per obrir posteriorment la pantalla de factura o relacionar incidencies.

El constructor confirma que la intranet te moltes consultes per trobar factures per `num`, `factura_relacionada`, `cif`, `ID` i correu. En el SIF, aquesta conciliacio s'ha de moure a identificadors fiscals i de pagament mes estables: `UUID_FACTURA`, `IDPAG`, `DS_ORDER`, referencia bancaria/TPV i `payment_transaction`.

Format real del fitxer TPV que espera el codi actual:

- el fitxer es rep a `$_FILES['fitxer-tpv']`;
- es llegeix el fitxer temporal directament amb `fgetcsv`;
- el codi espera una linia CSV on el contingut real ve separat per `;`;
- la primera linia ha de tenir 13 camps segons la comprovacio `count($contentCSV)-1 == 12`;
- camps utilitzats per posicio:
  - `0`: data del pagament en format `DD/MM/YYYY` o compatible amb `Date::getDataFomatYYYYMMDD_HHMMSS()`;
  - `3`: tipus de transaccio (`Autorización`, `Devolución` o variants mal codificades);
  - `4`: numero de comanda;
  - `5`: accio/resultat de la transaccio, que ha de contenir `Autorizada`;
  - `6`: import CSV;
  - `8`: import en euros;
  - `9`: titular/CIF/DNI;
  - `10`: concepte;
  - `11`: import retornat, si existeix.

Lògica actual de conciliacio TPV:

1. Ignora registres que no siguin `Autorización` o `Devolución`, que no estiguin autoritzats, que tinguin imports no valids, data no valida, comanda 0 o titular no numeric.
2. Per autoritzacions positives assigna `TIPUS = A` i import positiu.
3. Per devolucions assigna `TIPUS = R` i import negatiu.
4. Converteix la data a format `YYYY-MM-DD...` i cerca a `web.factures`.
5. Primer cerca factura amb `data_pagament LIKE`, `num_comanda`, `import` i `TIPUS`.
6. Si no troba, cerca factura sense comanda amb `data_pagament LIKE`, `import`, `TIPUS` i `cif`.
7. Si no troba factura i el CIF no es `77922662L`, afegeix incidencia visual:
   - import positiu: enllac cap a alumne (`cnsAlumne-{cif}`);
   - import negatiu: enllac cap a factura (`cnsFactura-{cif}`).
8. Si tot quadra, retorna `state = 1`.
9. Si hi ha pagaments sense factura, retorna `state = 2` i HTML a `registresPagErrors`.
10. Si el format no quadra, retorna `state = 0`.
11. Escriu data i hora de l'analisi a `../../fitxers/analisis-fitxer.txt`.

Errors ignorats que informa el codi:

- la transaccio no ha estat autoritzada;
- el numero de comanda no es valid;
- l'import no es valid;
- l'import en euros no es valid;
- l'import retornat no es valid;
- la transaccio no ha acabat;
- la transaccio esta denegada;
- la transaccio s'ha cancel·lat;
- la data de la transaccio no es valida;
- el titular no es un DNI.

Riscos tecnics actuals del TPV:

- no es valida extensio/MIME real del fitxer abans de llegir-lo;
- es llegeix directament el fitxer temporal sense moure'l a una zona controlada;
- la comprovacio de format depen del nombre de camps, pero no de capçalera nominal;
- els imports es tracten amb `floatval`, no `DECIMAL`;
- el titular es valida amb `intval($cif)`, cosa que pot fallar per NIF/CIF amb lletra inicial;
- la cerca es fa contra `web.factures`, no contra taules SIF de pagaments/transaccions;
- el codi nomes detecta si falta factura, no registra pagament al SIF;
- hi ha un possible bug a `if ( $resposta->msg = "" )`, que assigna en lloc de comparar;
- cal confirmar si `ConnexioWeb2` es tanca sempre correctament;
- cal evitar que la mateixa linia TPV es pugui revisar o registrar dues vegades sense idempotencia.

Canvi VERI*FACTU:

- comprovar `DS_ORDER`, `IDPAG`, factura i pagament fiscal;
- si hi ha factura, registrar pagament pendent;
- si no hi ha factura, detectar si cal emetre;
- no duplicar factures;
- generar notificacions per anomalies.

Conciliacio final:

- cada linia del fitxer TPV ha de buscar primer si ja existeix una transaccio registrada;
- si existeix, no duplicar;
- si no existeix, buscar factura SIF per `IDPAG`, `DS_ORDER`, `FACTURA_RELACIONADA`, import o relacio amb inscripcio;
- si troba factura pendent de cobrament, proposar `registerPayment()`;
- si troba inscripcio sense factura, proposar `issueInvoice()` amb bloc `payment` nomes si el cas ho permet;
- si hi ha import diferent, factura anul·lada, receptor diferent o multiples coincidencies, crear incidencia/revisio manual;
- guardar resultat de cada analisi per auditoria interna.

Contracte final de conciliacio TPV:

```text
fitxer TPV pujat
    -> validar fitxer i calcular hash/resum
    -> normalitzar cada linia a DECIMAL, data, tipus, DS_ORDER/comanda, titular i import
    -> buscar redsys_notifications per DS_ORDER
    -> buscar payment_transaction per PROVIDER_REF o clau de linia
    -> buscar factura SIF o origen operatiu si encara no hi ha transaccio
    -> classificar linia: conciliada, duplicada, pendent d'assignacio, pendent d'emissio o incidencia
    -> no tocar web.factures ni web.inscripcions fins que una accio SIF sigui acceptada
```

Resultats possibles per linia TPV:

| Resultat | Criteri | Accio |
| --- | --- | --- |
| `CONCILIADA` | Existeix notificacio/transaccio/factura coherent. | Registrar auditoria de comprovacio. |
| `DUPLICADA` | La mateixa clau TPV ja esta registrada. | No crear nou moviment. |
| `PENDENT_ASSIGNACIO` | Hi ha cobrament clar i factura SIF pendent. | Proposar `registerPayment()`. |
| `PENDENT_EMISSIO` | Hi ha cobrament clar i venda facturable sense factura. | Proposar `issueInvoice()` amb bloc `payment`, si el cas ho permet. |
| `INCIDENCIA` | Import, titular, factura, estat o multiples candidats no quadren. | Crear incidencia SIF/manual. |

La conciliacio TPV pot ajudar a detectar callbacks perduts o no processats, pero no substitueix la validacio del callback Redsys ni converteix automaticament una linia del fitxer en factura.

Pendent d'incorporar:

- decidir si l'ultim analisi continua guardant-se a `fitxers/analisis-fitxer.txt` o si passa a taula SIF/log d'auditoria;
- criteri de reprocessament segur.

#### 3.5.1. Revisio especialitzada i criteri de tancament

El fitxer TPV no ha de ser nomes una comprovacio visual. En el SIF final, cada pujada i cada linia han de deixar rastre auditable.

Resposta actual:

| `state` | Significat actual | Tractament final |
| --- | --- | --- |
| `1` | Sense incidencies visibles. | Registrar analisi amb hash del fitxer, usuari, data, total de linies i resultat. |
| `2` | Hi ha pagaments sense factura o sense conciliacio. | Crear incidencies o propostes de conciliacio amb enllac a alumne/factura. |
| `0` | Format incorrecte o error de validacio. | Rebutjar fitxer i conservar error tecnic sense tocar pagaments. |

Regles finals:

- validar extensio, MIME, capçalera i estructura, no nomes nombre de camps;
- conservar hash del fitxer o resum equivalent, usuari que l'ha pujat i data/hora;
- generar clau idempotent de linia amb origen, data, comanda, import, titular, tipus i referencia quan existeixi;
- buscar primer `payment_transaction` existent abans de proposar `registerPayment()`;
- no usar `fitxers/analisis-fitxer.txt` com a unic registre d'auditoria;
- tractar imports amb `DECIMAL`, no amb `floatval`;
- no rebutjar NIF/CIF valids nomes per `intval($cif)`;
- confirmar i corregir el possible bug d'assignacio `if ( $resposta->msg = "" )`;
- definir si una devolucio TPV obre rectificativa, devolucio de pagament, saldo o incidencia.

Proves minimes:

- fitxer correcte sense incidencies;
- fitxer correcte amb un pagament no conciliat;
- fitxer amb format incorrecte;
- reprocessament del mateix fitxer;
- mateixa linia amb factura existent;
- mateixa linia amb venda sense factura;
- devolucio TPV;
- NIF/CIF amb lletra inicial;
- imports amb decimals i diferencia residual.

### 3.6. Ecommerce

Funcio:

- inscripcio/compra;
- aplicacio de descomptes;
- pantalla de dades de facturacio pendent d'afegir;
- pagament per Redsys;
- callback Redsys.

Canvis VERI*FACTU:

- afegir pas de dades de facturacio;
- guardar snapshot fiscal abans de pagar;
- no crear factura local en callback;
- cridar SIF;
- gestionar packs, grups, regals, USOC i codis promocionals.

#### 3.8.1. Revisio especialitzada de Redsys curs normal

El curs normal pagat per Redsys queda com a patro base de migracio dels callbacks.

Flux antic recuperat:

- `realitzaPagamentAutomatic.php` rep `Ds_MerchantParameters` i `Ds_Signature`;
- calcula signatura Redsys, pero cal verificar que es compara abans de tocar BD;
- usa `IDPAG` per carregar una inscripcio existent;
- si Redsys autoritza (`Ds_Response` entre `0` i `99`), calcula factura local `A{any}/{ordre}`;
- insereix a `web.factures`;
- guarda `Ds_Order` a `NUM_COMANDA`;
- actualitza `PAGAMENT`, `FACTURA_RELACIONADA`, `DATA PAG` i `FRACCIO`;
- envia correus interns de pagament automatic.

Flux final:

| Situacio | Accio SIF |
| --- | --- |
| Redsys acceptat, sense factura previa real | `issueInvoice()` amb `source_channel = REDSYS`, `source_type = CURS` i una linia de curs. |
| Redsys acceptat, amb factura abans de cobrament | `registerPayment()` contra la factura existent. |
| Callback duplicat mateix `DS_ORDER` | Retornar resultat idempotent sense efectes nous. |
| Mateix `IDPAG` amb diversos `DS_ORDER` | Tractar com intents o fraccions diferents segons estat; no deduplicar nomes per `IDPAG`. |
| Signatura incorrecta | Rebutjar i registrar incidencia tecnica, sense tocar factura ni pagament. |
| Import signat no coincideix | Incidencia o conciliacio manual, no emissio automatica. |

Regla:

```text
El callback Redsys no calcula numero fiscal, no insereix a web.factures i no decideix PDF.
El SIF retorna UUID_FACTURA, numero visible, estat AEAT, estat cobrament i document/cua PDF.
```

#### 3.8.2. Revisio especialitzada de Grups

El grup de persones queda com a cas de pagament amb multiples inscripcions i receptor fiscal diferent del participant individual.

Informacio recuperada:

- una empresa o persona pot pagar per N participants;
- cada participant te una fila a `inscripcions`;
- si el grup es d'una escola o empresa, el receptor fiscal es l'entitat;
- si el grup es d'amics o particular, el receptor fiscal pot ser el responsable particular;
- el preu per participant surt de `descomptes_grup`;
- el nom del participant pot sortir a la linia;
- el DNI nomes s'ha d'imprimir si es necessari per justificacio, i preferentment queda com a dada interna o annex.

Consultes i taules identificades:

- `TIPUS_INSC = G` identifica inscripcions de grup;
- `respGrups` relaciona responsable i `IDPAG`;
- `buscarPersRespGrup2` busca grups per DNI de responsable o participant i agrupa per `IDPAG`;
- `buscarPersGrup` llista participants del grup;
- `buscarPagamentsGrup` agrega `A_PAGAR`, `PAGAMENT`, `FACTURA_RELACIONADA`, fraccions i observacions per `IDPAG`;
- `searchMembresGrup` i `searchMembresGrup2` recorren membres del grup per aplicar pagaments en l'operativa historica;
- `updPayInscr`, `updDateInscr` i `updFraccBD` actualitzen l'estat operatiu de les inscripcions.

Flux final:

| Situacio | Accio SIF |
| --- | --- |
| Grup pagat per Redsys sense factura previa real | `issueInvoice()` amb `source_channel = REDSYS`, `source_type = GRUP` i una linia per participant. |
| Grup amb factura abans de cobrament | `registerPayment()` contra la factura existent. |
| Grup pagat per empresa/responsable des d'intranet | `issueInvoice()` o `registerPayment()` segons si la factura ja existeix. |
| Afegir participant despres d'emetre factura real | Rectificativa o factura complementaria, no modificacio directa de la factura original. |
| Treure participant despres d'emetre factura real | Rectificativa o devolucio/saldo segons el cas fiscal. |

Regla:

```text
1 pagament de grup
    -> 1 factura al receptor fiscal
    -> una linia per participant
    -> `SOURCE_TYPE = INSCRIPCIO`
    -> `SOURCE_ID = inscripcions.ID`
```

La visibilitat queda restringida: els participants no han de veure la factura completa del grup si inclou altres persones; nomes l'empresa o responsable autoritzat la pot consultar.

#### 3.8.3. Revisio especialitzada de Regals

El regal queda com a venda facturada al comprador, amb inscripcio posterior del destinatari sense factura nova.

Informacio recuperada:

- paga qui regala el curs;
- el receptor de factura es el comprador;
- el destinatari es la persona indicada al formulari, pero no omple les seves dades d'inscripcio fins que bescanvia el codi;
- el comprador tria curs, pot posar dedicatoria i introdueix les seves dades de facturacio;
- el sistema genera un codi regal;
- quan el destinatari bescanvia el codi, es crea o completa inscripcio sense emetre una segona factura.

Consultes i taules identificades:

- `regal` conserva el registre operatiu del regal;
- `buscarRegNoPayByCodi` cerca regals pendents de factura per `CODI` i `FACT_REL = 0`;
- `buscarRegNoPayByDni` cerca regals pendents per `NIFC` i `FACT_REL = 0`;
- `buscarRegalById` recupera `NOM_CURS`, `CCURS`, `NOMC`, `NIFC`, `MAILC`, adreca, `CODI`, `FACT_REL`, `ORIGEN` i `DESTI`;
- `updFactRegal` actualitza `regal.FACT_REL` amb la factura relacionada;
- el modal historic mostra `ORIGEN`, `DESTI`, `CODI REGAL` i `CURS REGAL`;
- el correu historic de confirmacio inclou el codi i enllaç a la targeta regal PDF.

Flux final:

| Situacio | Accio SIF |
| --- | --- |
| Regal pagat per Redsys sense factura previa real | `issueInvoice()` amb `source_channel = REDSYS`, `source_type = REGAL`, `source_id = regal.ID` i una linia al comprador. |
| Callback duplicat del mateix regal i `DS_ORDER` | Retornar resultat idempotent sense efectes nous. |
| Cerca/pagament manual de regal pendent | `issueInvoice()` o `registerPayment()` segons si ja existeix factura SIF. |
| Bescanvi posterior del codi | Crear/vincular inscripcio del destinatari sense factura nova. |
| Codi caducat, ja bescanviat o incoherent | Incidencia operativa, no emissio fiscal automatica. |

Regla:

```text
1 pagament de regal
    -> 1 factura al comprador
    -> `SOURCE_TYPE = REGAL`
    -> `SOURCE_ID = regal.ID`
    -> codi regal
    -> inscripcio posterior del destinatari sense factura nova
```

La targeta regal pot continuar sent un document comercial; la factura fiscal immutable ha de sortir del SIF i de `factura_documents`.

#### 3.8.4. Revisio especialitzada d'USOC

USOC queda com a cas de descompte/validacio manual i doble factura per dos pagadors reals.

Informacio recuperada:

- el canal TPV historic es `curs afiliat d'USOC`;
- `TIPUS_DESC = 4` identifica `Afiliat USOC`;
- el descompte indicat al xat antic es del 25%;
- la validacio es manual a la intranet, despres de confirmar afiliacio amb USOC;
- `VALID_DESC = 0` significa pendent de validar, `1` validat i valid, `2` validat i no valid;
- el concepte historic podia incloure que el pagament de la diferencia el realitza l'entitat USOC;
- en el cas recuperat, l'alumne paga inicialment 10 euros i USOC paga la diferencia;
- el cas especial `Altres: Curs gratüit USOC` pot usar el parametre `anticipi-preu-usoc`.

Consultes, camps i pantalles identificades:

- `inscripcions.TIPUS_DESC` i `inscripcions.VALID_DESC` governen el tipus i estat de validacio;
- `cnsAlumnDescNoValidat` carrega inscripcions amb descompte pendent;
- `__mostrarPage_Inici_ValidarDescomptes` avisa si hi ha descomptes pendents;
- `__mostrarPage_Alumnes_ValidarDescomptes` mostra la llista i etiqueta `Afiliat USOC`;
- `updValidDescByInsc` actualitza validacio;
- `updValidDescByInscPreu` pot actualitzar `TIPUS_DESC`, `VALID_DESC` i `A_PAGAR`;
- els missatges historics informen l'alumne si USOC confirma l'afiliacio o si no consta l'afiliacio.

Flux final:

| Situacio | Accio SIF / operativa |
| --- | --- |
| Alumne marca afiliacio USOC | Guardar `TIPUS_DESC = 4`, `VALID_DESC = 0` i no emetre amb descompte fins validacio. |
| Afiliacio validada | Congelar descompte/preu, generar o habilitar pagament de la part alumne. |
| Afiliacio denegada | Recalcular import sense descompte i no generar factura USOC. |
| Alumne paga part per Redsys | `issueInvoice()` a l'alumne per l'import real pagat. |
| USOC paga diferencia | `issueInvoice()` a USOC per la diferencia, vinculada a la mateixa inscripcio i factura alumne. |
| Doble callback o reintent | Retornar resultat idempotent sense duplicar cap de les dues factures. |

Regla:

```text
TIPUS_DESC = 4
    -> validacio manual USOC
    -> alumne paga part i rep factura alumne
    -> USOC paga diferencia i rep factura USOC
    -> dues factures ordinàries relacionades internament
```

La factura d'USOC no es una rectificativa ni un complement informal de la factura de l'alumne. Es una factura fiscal separada amb receptor fiscal USOC, snapshot propi i relacio interna amb la inscripcio i la factura de l'alumne.

#### 3.8.5. Revisio especialitzada de Codis promocionals

Els codis promocionals queden com a logica operativa d'ecommerce/intranet que afecta el preu final, pero no com a canal fiscal propi.

Informacio recuperada:

- un client pot introduir un codi al camp `Codi promocional` del formulari d'inscripcio;
- `descomptes.TIPUS` de l'11 al 99 identifica promocions temporals;
- les promocions temporals s'apliquen directament segons la taula `descomptes`;
- els codis promocionals tenen logica propia i poden venir de `promocions`;
- `promocions` conte com a minim `CODI_DESCOMPTE`, `DNI`, `MES`, `CURS`, `PERCENTATGE`, `USED`, `DATAI` i `DATAF`;
- hi ha codis personals tipus `MACABODETITULAR#...`, indicats com a personals, intransferibles, d'un sol us i valids fins a una data;
- en canvis de curs, el codi o promocio pot quedar consumit o tancat amb `updDataFPromocio`.

Consultes i updates identificats:

- `cnsSiTePromocioDispo` busca codis disponibles per patró, DNI, `USED = 0` i vigencia activa;
- `updDataFPromocio` tanca una promocio posant `DATAF = CURRENT_TIME`;
- en el flux de promocio de docents novells, es consulta `recent_titulat` per `ID_INSC` i `VALIDAT = 1`;
- el correu historic pot comunicar un codi `MACABODETITULAR#...` i indicar import de descompte i data de validesa.

Flux final:

| Situacio | Accio SIF / operativa |
| --- | --- |
| Codi valid abans de pagar | Guardar snapshot de codi, import/percentatge, base i total final abans de Redsys. |
| Codi invalid/caducat/usat abans de pagar | Recalcular sense codi o demanar revisio abans d'emetre. |
| Redsys cobra import amb codi aplicat | `issueInvoice()` amb linia que conserva `desc_origen = CODI_PROMO` i `desc_codi_promo`. |
| Promocio temporal `descomptes.TIPUS` 11-99 | `issueInvoice()` amb linia que conserva `DESC_ID`, percentatge/preu i vigencia usada. |
| Codi caduca o queda usat despres d'emetre | No afecta factura emesa; la linia fiscal ja es immutable. |
| Canvi de curs despres d'emetre | Rectificativa/factura nova segons import/concepte; no recalcul silencios de la factura original. |

Regla:

```text
codi promocional validat
    -> snapshot de descompte
    -> factura_linia amb CODI_PROMO
    -> factura immutable encara que promocions canviï
```

El text visible de factura ha de ser generic, per exemple `Descompte promocional aplicat`, i el codi pot quedar com a dada interna si no cal mostrar-lo al PDF.

### 3.9. Gestio de factura electronica

Ubicacio decidida:

```text
Alumnes / Consulta - Edita - Anula factura
```

Funcio futura:

- marcar una factura com a factura electronica (`E_FACT = 1`);
- desmarcar-la si s'ha marcat per error i encara no te efectes externs incompatibles;
- no confondre aquesta marca amb `EMESA_ABANS_COBRAMENT`;
- mostrar l'estat a la visualitzacio de factura;
- permetre filtre o llistat de factures electroniques si cal.

Permisos:

- Meriem;
- Adam;
- Pablo.

### 3.7. Intranet alumne

Funcio futura:

- consultar factures visibles de l'alumne;
- descarregar PDF;
- veure QR dins el PDF.

Regla de privacitat:

- no mostrar factures pagades per empresa/grup si contenen altres participants;
- nomes mostrar factures on l'alumne sigui receptor o on s'hagi marcat visibilitat individual.

### 3.8. Notificacions fiscals

Funcio futura:

- avisar errors AEAT;
- avisar fallada de retries;
- avisar factures en estat `FAILED`;
- avisar inconsistencies de cadena o pagament.

### 3.10. Panell SIF a pay.prisma.cat/sif

Funcio futura:

- administrar i consultar el SIF des del domini oficial de facturacio;
- gestionar factures, registres AEAT, incidencies, documents, versions, exportacions i configuracio.

Apartats:

- Dashboard;
- Factures;
- Registres AEAT;
- Incidencies;
- Documents;
- Versions;
- Exportacions;
- Configuracio.

Regla:

```text
pay.prisma.cat/sif = font oficial fiscal
intranet = accés, resum i avisos
```

#### 3.10.1. Dashboard

```text
Nom de l'apartat: Dashboard SIF
URL / fitxer PHP: pay.prisma.cat/sif/dashboard
Qui el pot usar: administracio SIF, responsable tecnic, direccio/lectura
Objectiu funcional: veure estat general del SIF
Dades d'entrada: filtres de periode, si cal
Taules que consulta: factura, factura_registres, fiscal_queue, factura_documents, errors_verifactu, sif_versions
Taules que modifica: cap
Genera factura? no
Genera pagament? no
Genera rectificativa? no
Envia correus? no
Errors possibles: no poder carregar resum, error connexio BD fiscal
Notificacions internes: no; nomes mostra resum
Canvi necessari per VERI*FACTU: crear pantalla i endpoint de resum
Endpoint SIF: GET /sif/dashboard/summary
Notes: no ha de ser font de gestio, nomes entrada visual
```

#### 3.10.2. Factures

```text
Nom de l'apartat: Factures SIF
URL / fitxer PHP: pay.prisma.cat/sif/factures
Qui el pot usar: administracio SIF, gestio autoritzada, auditor lectura
Objectiu funcional: consultar factures fiscals emeses
Dades d'entrada: numero, UUID, NIF/CIF, FACTURA_RELACIONADA, periode, estats
Taules que consulta: factura, factura_linia, factura_registres, factura_documents, payment_transaction, payment_allocation, fact_rels
Taules que modifica: cap en consulta; rectificativa nomes per flux separat
Genera factura? no en consulta
Genera pagament? no
Genera rectificativa? nomes si usuari inicia flux autoritzat
Envia correus? no en consulta
Errors possibles: factura no trobada, permisos insuficients
Notificacions internes: si es detecta anomalia greu
Canvi necessari per VERI*FACTU: crear cercador i fitxa immutable de factura
Endpoint SIF: GET /api/factures, GET /api/factures/{uuid}
Notes: prohibida edicio directa de factura emesa
```

#### 3.10.3. Registres AEAT

```text
Nom de l'apartat: Registres AEAT
URL / fitxer PHP: pay.prisma.cat/sif/registres-aeat
Qui el pot usar: administracio SIF, responsable tecnic, auditor lectura
Objectiu funcional: consultar registres fiscals i estat AEAT
Dades d'entrada: periode, estat AEAT, numero factura, UUID
Taules que consulta: factura_registres, fiscal_queue, factura
Taules que modifica: fiscal_queue nomes si es reintenta i el rol ho permet
Genera factura? no
Genera pagament? no
Genera rectificativa? no
Envia correus? no
Errors possibles: error AEAT, retry bloquejat, registre rebutjat
Notificacions internes: si estat FAILED o REJECTED
Canvi necessari per VERI*FACTU: crear consulta i accio de retry controlada
Endpoint SIF: GET /api/fiscal-records, POST /api/fiscal-queue/{id}/retry
Notes: tota accio de retry ha de quedar logada
```

#### 3.10.4. Incidencies

```text
Nom de l'apartat: Incidencies SIF
URL / fitxer PHP: pay.prisma.cat/sif/incidencies
Qui el pot usar: administracio SIF, responsable tecnic, Adam/Pablo segons cas
Objectiu funcional: gestionar problemes fiscals o tecnics del SIF
Dades d'entrada: tipus, prioritat, estat, UUID_FACTURA, responsable
Taules que consulta: errors_verifactu, factura, factura_registres, fiscal_queue
Taules que modifica: errors_verifactu
Genera factura? no
Genera pagament? no
Genera rectificativa? no directament
Envia correus? opcional segons prioritat
Errors possibles: resolucio sense permisos, incidencia sense factura vinculada
Notificacions internes: si incidencia alta o failed
Canvi necessari per VERI*FACTU: crear gestio oficial d'incidencies al SIF
Endpoint SIF: GET /api/incidents, POST /api/incidents/{id}/actions
Notes: la intranet nomes mostra resum; la resolucio oficial es fa aqui
```

#### 3.10.5. Documents

```text
Nom de l'apartat: Documents SIF
URL / fitxer PHP: pay.prisma.cat/sif/documents
Qui el pot usar: administracio SIF, gestio autoritzada, auditor lectura
Objectiu funcional: consultar i descarregar documents fiscals
Dades d'entrada: UUID_FACTURA, tipus document, periode
Taules que consulta: factura_documents, factura, sif_documents
Taules que modifica: factura_documents nomes si es genera/regenera document amb log
Genera factura? no
Genera pagament? no
Genera rectificativa? no
Envia correus? no en consulta
Errors possibles: fitxer no existeix, hash incorrecte, permisos insuficients
Notificacions internes: si falta PDF/QR o hash no coincideix
Canvi necessari per VERI*FACTU: crear repositori documental controlat
Endpoint SIF: GET /api/documents, GET /api/documents/{id}/download
Notes: PDFs emesos no s'han de modificar silenciosament
```

#### 3.10.6. Versions

```text
Nom de l'apartat: Versions SIF
URL / fitxer PHP: pay.prisma.cat/sif/versions
Qui el pot usar: responsable tecnic, direccio, auditor lectura
Objectiu funcional: controlar versio activa i historial del SIF
Dades d'entrada: versio, data, estat
Taules que consulta: sif_versions, sif_documents
Taules que modifica: sif_versions nomes amb rol autoritzat
Genera factura? no
Genera pagament? no
Genera rectificativa? no
Envia correus? no
Errors possibles: activar versio sense declaracio, permisos insuficients
Notificacions internes: si versio pendent de declaracio
Canvi necessari per VERI*FACTU: crear control de versio activa i declaracio associada
Endpoint SIF: GET /api/sif/versions, POST /api/sif/versions/{id}/activate
Notes: primera versio signable prevista 1.0.0
```

#### 3.10.7. Exportacions

```text
Nom de l'apartat: Exportacions SIF
URL / fitxer PHP: pay.prisma.cat/sif/exportacions
Qui el pot usar: administracio SIF, responsable tecnic, auditor lectura segons permisos
Objectiu funcional: generar exports fiscals i conservar evidencia d'exportacio
Dades d'entrada: periode, tipus export, estat, motiu
Taules que consulta: factura, factura_linia, factura_registres, factura_documents, payment_transaction, errors_verifactu
Taules que modifica: sif_exports
Genera factura? no
Genera pagament? no
Genera rectificativa? no
Envia correus? no
Errors possibles: export sense permisos, fitxer no generat, hash no calculat
Notificacions internes: si export falla
Canvi necessari per VERI*FACTU: crear generador i registre d'exports
Endpoint SIF: POST /api/exports
Notes: cada export ha de guardar usuari, data, criteris, fitxer i hash
```

#### 3.10.8. Configuracio

```text
Nom de l'apartat: Configuracio SIF
URL / fitxer PHP: pay.prisma.cat/sif/configuracio
Qui el pot usar: responsable tecnic / administrador SIF
Objectiu funcional: gestionar parametres tecnics del SIF
Dades d'entrada: emissor, series, AEAT, retries, rutes, permisos, workers
Taules que consulta: sif_config, fiscal_sequence, params_sif, sif_workers
Taules que modifica: sif_config, params_sif nomes amb log
Genera factura? no
Genera pagament? no
Genera rectificativa? no
Envia correus? no
Errors possibles: configuracio invalida, permisos insuficients
Notificacions internes: si canvi critic requereix revisio
Canvi necessari per VERI*FACTU: crear configuracio centralitzada del SIF
Endpoint SIF: GET /api/sif/config, POST /api/sif/config
Notes: cap canvi de configuracio sense log
```

### 3.11. Apartat VERI*FACTU de la intranet principal

Funcio futura:

- mostrar un unic apartat `VERI*FACTU` amb indicador visual de pendents;
- mostrar resum d'incidencies pendents;
- obrir el panell SIF;
- accedir a documents SIF;
- accedir a exportacions.

No ha de substituir el panell SIF.

```text
Nom de l'apartat: VERI*FACTU intranet
URL / fitxer PHP: intranet / apartat nou VERI*FACTU
Qui el pot usar: administracio/gestio autoritzada
Objectiu funcional: entrada rapida al SIF des de la intranet
Dades d'entrada: cap, nomes filtres de resum si cal
Taules que consulta: idealment endpoints SIF; notificacions intranet per avisos locals
Taules que modifica: cap, excepte marcar notificacions llegides si s'implementa
Genera factura? no
Genera pagament? no
Genera rectificativa? no
Envia correus? no
Errors possibles: SIF no disponible, permisos insuficients
Notificacions internes: mostra resum de pendents
Canvi necessari per VERI*FACTU: crear menu, indicador visual i accessos
Endpoint SIF: GET /api/sif/summary, GET /api/incidents?status=open
Notes: la intranet resumeix i enllaça; el SIF conserva i gestiona
```

### 3.12. Intranet alumne, empresa/responsable i accessos de factura

Aquest apartat no es una pantalla de gestio fiscal, sino una capa de consulta externa amb permisos estrictes.

Informacio recuperada del xat antic:

- la visibilitat per alumne/empresa es un flux nou; ara no es veu com a circuit complet;
- l'alumne no ha de veure factures pagades per una empresa;
- si una empresa paga un grup, cada participant no pot veure la factura completa;
- nomes l'empresa o responsable pot veure la factura d'empresa/grup;
- els PDFs s'han de conservar en un espai no public de `pay.prisma.cat`;
- la intranet ha de servir PDFs amb permisos, sense donar la ruta directa;
- `factura_documents` controla PDF/XML/QR, hash, estat d'enviament i document disponible;
- l'apartat `VERI*FACTU` de la intranet principal nomes resumeix i enllaça amb el SIF.

Taula de decisio final:

| Cas | Decisio |
| --- | --- |
| Alumne amb factura individual propia | Pot veure factura, estat de pagament i PDF/QR si el document existeix. |
| Alumne amb inscripcio pagada per empresa/responsable | Pot veure estat administratiu/cobertura, pero no la factura completa si no n'es receptor fiscal. |
| Grup pagat per empresa | Participants no veuen la factura completa; empresa/responsable si, per correu, enllac segur o espai futur. |
| Empresa/responsable amb factura pendent | Pot rebre URL de pagament d'empresa/responsable, no URL individual de l'alumne. |
| PDF/QR pendent o fallit | Mostrar estat pendent/incidencia; no regenerar document amb dades vives. |
| Apartat `VERI*FACTU` intranet | Mostrar indicador, resum i accessos; la resolucio oficial viu a `pay.prisma.cat/sif`. |

Regles d'implementacio:

- l'endpoint de document ha de comprovar sessio o token, relacio amb receptor, estat del document i permisos;
- els tokens d'enllac segur han de ser d'us acotat, revocables o amb caducitat, i no han d'incloure paths interns;
- l'alumne no ha de rebre dades fiscals completes d'una empresa o d'altres participants del grup;
- l'empresa/responsable no entra a la intranet principal;
- qualsevol enllac de correu ha de consultar el SIF abans de mostrar PDF/QR o URL de pagament;
- cada accio de consulta externa ha de ser lectura: no genera factura, pagament, rectificativa ni marca `E_FACT`;
- si el document falta a `factura_documents`, cal mostrar estat o incidencia SIF.

Proves minimes:

- alumne consulta factura individual propia;
- alumne intenta veure factura d'empresa/grup i queda bloquejat;
- responsable consulta factura d'empresa per enllac segur;
- participant de grup nomes veu estat de cobertura, no dades completes de factura;
- PDF pendent mostra estat i no regenera document;
- path intern de document no es visible al navegador;
- token caducat, invalid o d'una altra factura queda rebutjat;
- apartat `VERI*FACTU` mostra resum i enllaça al panell SIF sense permetre resolucio local.

## 4. Canals TPV identificats

- cursos;
- regalar un curs;
- packs;
- curs afiliat USOC;
- taller;
- jornada;
- grup de persones.

Cada URL/cas s'haura de documentar amb:

- origen;
- dades d'entrada;
- taules afectades;
- linies de factura;
- idempotencia;
- receptor fiscal;
- correus;
- errors.
