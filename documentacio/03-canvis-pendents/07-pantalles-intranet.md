# 07 - Pantalles d'intranet

> Document especific pendent de desenvolupar. Recollira els canvis de pantalles, permisos i accions.

## Pantalles inicials

- Consulta - Modifica alumne.
- Dades del curs.
- Dades de pagament.
- Canvi de curs.
- Baixa.
- Veure factura.
- Passar pagaments.
- Generar factura abans de pagar.
- Consulta - Edita - Anula factura.
- Notificacions fiscals.
- Descarrega de factures i registres.
- Apartat `VERI*FACTU` de la intranet principal.
- Panell SIF a `pay.prisma.cat/sif`.

## Mapa de rutes actuals

Segons el `.htaccess` de `intranet.prisma.cat`, les pantalles principals afectades per VERI*FACTU son:

| URL | Fitxer PHP | Pantalla / apartat |
| --- | --- | --- |
| `/alumnes/mostrar-alumne/` | `alumnes-mostrar-alumne.php` | Consulta - Modifica alumne. |
| `/alumnes/pagaments/` | `alumnes-pagaments.php` | Passar pagaments. |
| `/alumnes/factura/` | `alumnes-factura.php` | Consulta - Edita - Anula factura. |
| `/alumnes/genera-factura-abans-pagar/` | `alumnes-genera-factura-abans-pagar.php` | Generar factura abans de pagar / abans de cobrament. |
| `/alumnes/genera-entitat/` | `alumnes-genera-entitat.php` | Entitats i facturacio d'empresa. |
| `/alumnes/validar-descomptes/` | `alumnes-validar-descomptes.php` | Validacio de descomptes. |
| `/facturacio/comprovar-idpags/` | `facturacio-comprovar-idpags.php` | Conciliacio IDPAG/TPV. |
| `/facturacio/primera-reclamacio/` | `facturacio-primera-reclamacio-pagament.php` | Primera reclamacio. |
| `/facturacio/baixes/` | `facturacio-baixes-segona-setmana.php` | Baixes amb seguiment economic. |
| `/facturacio/recordatori-pagament/` | `facturacio-recordatori-pagament-final.php` | Recordatori de pagament. |
| `/facturacio/reclamacio-final/` | `facturacio-reclamacio-final.php` | Reclamacio final. |
| `/facturacio/morosos/` | `facturacio-control-morosos.php` | Morositat. |

Cada pantalla pot tenir:

- PHP d'entrada segons `.htaccess`;
- classe `Intranet.php` amb la logica principal;
- JS de pantalla;
- crides AJAX a metodes o endpoints auxiliars.

## Consulta - Modifica alumne

Estat actual conegut:

- pantalla de cerca per dades de l'alumne;
- mostra dades personals;
- mostra inscripcions pendents de comencar;
- mostra inscripcions acabades;
- mostra observacions generals;
- cada inscripcio te icones d'accio.

Implementacio actual:

- ruta: `/alumnes/mostrar-alumne/`;
- `.htaccess`: `/alumnes/mostrar-alumne/` -> `alumnes-mostrar-alumne.php`;
- fitxer/classe: `Intranet.php`;
- metode de pagina: `__mostrarPage_Alumnes_MostrarAlumne()`;
- funcionament: pagina renderitzada per la classe `Intranet` amb crides JS/AJAX a metodes de la mateixa classe;
- no s'ha de documentar com una pantalla aillada amb un PHP propi per cada accio.

Metodes principals detectats:

- `mostrarInformacioUsuari_Alumnes()`, per carregar la fitxa de l'alumne;
- `guardarDadesPersonals_resultatCerca()`, per guardar dades personals operatives;
- `__mostrarFilaResultatCerca()`, per pintar files, estats i icones d'accio;
- `modalConsultaInformacio_resultatCerca()`, per mostrar dades d'inscripcio/curs;
- `guardarDadesPagament_modalsresultatCerca()`, per guardar dades de pagament;
- `modalCanviCurs_resultatCerca()` i `realitzarCanviCurs_modalCanviCurs()`, per canvi de curs;
- `modalDonarBaixa_resultatCerca()` i `confirmaBaixa_modalDonarBaixa()`, per baixa;
- `modalConsultaFactura_resultatCerca()` i `generaFactura()`, per veure/descarregar factura actual;
- `modalConsultaCertificat_resultatCerca()` i `generaCertificat()`, per certificat.

Captures aportades de l'estat actual:

- vista general de `Alumnes / Consulta - Modifica`;
- modal de dades del curs;
- bloc de dades de pagament amb URL de pagament;
- modal de canvi de curs;
- modal de baixa;
- visualitzacio de factura;
- visualitzacio de certificat.

Per tant, aquesta pantalla no esta pendent d'identificar. Esta pendent de convertir les captures i explicacions en especificacio final de comportament, camps, permisos i proves.

Elements funcionals ja explicats:

- cerca per dades de l'alumne;
- edicio de dades personals operatives;
- llistat d'inscripcions pendents;
- llistat d'inscripcions acabades;
- observacions generals;
- accio de veure dades de curs;
- accio de veure dades de pagament;
- accio de canvi de curs;
- accio de baixa;
- accio de veure factura;
- accio de veure certificat.

Icones identificades:

- informacio de l'alumne/inscripcio;
- canvi de curs;
- baixa;
- veure factura;
- veure certificat.

Taules i dades implicades:

- `inscripcions`, com a origen principal de dades de l'alumne, estat academic, imports, pagaments i relacio amb factura;
- taules de cursos/edicions/grups, per construir dades del curs i conceptes;
- taules historiques de factures de la BD web, per factures anteriors a la migracio;
- taules SIF de la BD fiscal, per factures VERI*FACTU, documents, pagaments, rectificatives i registres AEAT;
- `entitats` i `entitats_resp`, quan la factura o pagament correspon a empresa/responsable;
- `notificacions`, quan hi hagi avisos fiscals o incidencies vinculades.

Regla nova:

```text
Consulta - Modifica alumne
= pantalla de consulta i inici d'accions
no = pantalla per editar factures emeses
```

Decisions ja parlades i consolidades:

- la pantalla continua sent la fitxa de treball principal de l'alumne;
- les accions fiscals no es resolen amb updates manuals sobre `inscripcions`;
- les dades personals editades aqui no canvien factures ja emeses;
- els canvis fiscals de receptor, concepte o import d'una factura emesa han d'anar per rectificativa o flux SIF;
- `INSC_CURS` es un estat academic/administratiu i no substitueix l'estat de factura, cobrament o AEAT;
- `FACTURA_RELACIONADA` es conserva per compatibilitat, pero la relacio final sera amb UUID de factura al SIF;
- les factures historiques i les noves s'han de veure diferent;
- les factures de grup/empresa no s'han de tractar com a factures individuals de cada participant;
- les URLs de pagament han de passar a `pay.prisma.cat` i han de distingir individu, pack, grup, regal, empresa, USOC, diferencia, morositat o reclamacio;
- ser moros no bloqueja el pagament;
- una factura d'empresa pendent pot tenir URL propia;
- una inscripcio coberta per factura d'empresa/responsable no ha de mantenir URL individual activa si pot generar duplicat;
- `E_FACT` i `EMESA_ABANS_COBRAMENT` son decisions diferents.

Riscos actuals que ha de resoldre l'adaptacio:

- evitar que un canvi de dades personals sembli una modificacio de factura emesa;
- evitar editar pagaments sense traçabilitat fiscal, especialment quan es toquen `A_PAGAR`, `PAGAMENT`, `DATA PAG`, `IDPAG`, `FACTURA_RELACIONADA`, `FRACCIONAT`, `FRACCIO`, `reclamat` o `data_reclamacio`;
- evitar que una URL individual generi duplicat quan ja hi ha factura d'empresa/responsable;
- evitar que una factura de grup sigui visible com si fos factura individual de cada alumne;
- separar clarament estat academic, estat de cobrament i estat fiscal;
- distingir factura historica no VERI*FACTU de factura emesa pel SIF;
- convertir canvi de curs i baixa en fluxos amb motiu i previsualitzacio fiscal quan calgui.
- substituir la factura actual construida amb `concepte1`, `concepte2` i `import` per una vista SIF amb linies, PDF immutable i QR quan sigui factura nova.

### Dades personals

Actualment es poden editar dades personals, pero nomes afecten inscripcions pendents de comencar.

Regla nova:

- editar alumne no modifica factures emeses;
- si hi ha factura emesa, el canvi de dades fiscals s'ha de fer per rectificativa;
- cal mostrar avis quan es canviin nom, cognoms, DNI o adreca.

Text orientatiu:

```text
Aquest canvi no modificara factures ja emeses.
Per canviar dades fiscals d'una factura cal generar rectificativa.
```

### Estat academic

Es mante `INSC_CURS` com a estat academic/administratiu:

- `0`: no matriculat;
- `1`: matriculat;
- `X`: baixa;
- `C`: canvi de curs;
- `M`: moros.

No es duplicara l'estat de pagament dins `inscripcions` si es pot calcular a partir de pagaments.

### Dades pagament

Decisio presa:

```text
Aquest bloc s'ha de redissenyar.
No pot continuar editant directament camps economics/fiscals amb impacte en factura o cobrament.
```

El comportament actual de `guardarDadesPagament_modalsresultatCerca()` queda com a punt a substituir o restringir. Els camps fiscals i economics han de separar-se en:

- dades operatives de la inscripcio;
- estat de cobrament calculat;
- pagaments registrats al SIF;
- factura associada;
- URL de pagament;
- seguiment administratiu de reclamacions.

Cal afegir informacio fiscal i de cobrament:

- factura: numero visible, per exemple `A2026/000123`;
- receptor: alumne / empresa / responsable;
- estat cobrament;
- estat AEAT;
- PDF disponible;
- URL de pagament activa/inactiva;
- motiu d'inactivacio.

La URL de pagament s'haura de moure a `pay.prisma.cat`.

La URL de pagament ha de gestionar-se segons el tipus de pagament i receptor. No s'ha de bloquejar nomes pel fet que hi hagi una factura d'empresa pendent de cobrament, ja que una factura d'empresa tambe pot cobrar-se per URL especifica.

Tipus d'URL de pagament identificades:

- individu;
- pack;
- grup;
- regal;
- empresa / factura abans de cobrament;
- USOC;
- pagament de diferencia per canvi de curs;
- pagament de morositat o reclamacio.

Regles generals:

- si una factura d'empresa esta pendent de cobrament, pot existir URL de pagament per aquella factura;
- si un alumne esta moros, normalment interessa mantenir o generar una URL perque pugui pagar;
- si una inscripcio individual esta coberta per una factura d'empresa/responsable, s'ha de desactivar la URL individual per evitar duplicats;
- si l'alumne o responsable ja ha pagat, la pantalla i els correus han d'oferir opcio de veure la factura/PDF/QR en lloc de mostrar nomes enllac de pagament;
- si l'enllac esta desactivat, cal mostrar motiu d'inactivacio;
- el sistema ha de distingir entre "no es pot pagar" i "s'ha de pagar per una altra URL".

Camps que deixen de ser edicio fiscal lliure:

- `A_PAGAR`;
- `PAGAMENT`;
- `DATA PAG`;
- `IDPAG`;
- `FACTURA_RELACIONADA`;
- `FRACCIONAT`;
- `FRACCIO`.

Matís sobre `A_PAGAR`:

- es pot modificar quan sigui necessari;
- el modal ha de marcar clarament que s'ha fet un ajust manual;
- cal demanar motiu de l'ajust;
- cal mostrar si l'ajust afecta una factura existent o nomes la inscripcio abans d'emetre factura;
- si ja hi ha factura emesa, el sistema ha de portar l'usuari cap al flux fiscal corresponent.

Aquests valors es podran mostrar, pero qualsevol canvi amb impacte economic o fiscal haura de passar pel flux corresponent:

- `issueInvoice()`;
- `registerPayment()`;
- rectificativa;
- canvi de curs;
- baixa amb decisio economica;
- compensacio/saldo;
- reclamacio o morositat.

Motius possibles d'inactivacio:

- cobert per factura d'empresa/responsable;
- substituit per URL de grup;
- substituit per URL de pack;
- baixa sense pagament pendent;
- pagament complet;
- anul·lat manualment;
- canvi de curs pendent de recalcul;
- error o incidencia.

### Factura electronica

Cal afegir en algun apartat de gestio de factura l'opcio de marcar una factura com a factura electronica.

Ubicacio decidida:

```text
Alumnes / Consulta - Edita - Anula factura
```

Regla:

```text
Factura abans de pagar -> EMESA_ABANS_COBRAMENT = 1
Factura electronica -> E_FACT = 1
```

No son el mateix camp ni la mateixa decisio.

Permisos per marcar/desmarcar:

- Meriem;
- Adam;
- Pablo.

### Canvi de curs

La pantalla actual permet canviar any, mes, curs, import, despeses i motiu.

Decisions:

- afegir previsualitzacio fiscal abans de confirmar;
- registrar event a `canvi_curs`;
- mostrar:
  - import actual;
  - import nou calculat;
  - descompte reaplicat o no aplicable;
  - despeses de gestio;
  - pagat fins ara;
  - diferencia a pagar / saldo / retorn;
  - accio fiscal prevista.
- recalcular automaticament `A_PAGAR` segons el curs nou i els descomptes aplicables;
- detectar automaticament si el descompte original ja no aplica;
- permetre ajust manual d'import o despeses nomes si queda marcat i amb motiu;
- contemplar curs mes car, mes barat, mateix import i canvi de concepte;
- si ja hi ha factura emesa, generar o preparar la rectificativa/factura de diferencia/saldo/retorn que correspongui;
- conservar relacio amb factura original i inscripcio antiga.

Si hi ha factura emesa, no es pot modificar silenciosament el resultat fiscal.

### Baixa

La baixa marca la inscripcio com a baixa.

Decisio presa:

No ha de generar rectificativa automaticament en el moment de donar de baixa.

Primer:

- es marca baixa;
- es demana decisio al client:
  - retorn diners;
  - saldo a favor;
  - no retorn;
- quan es confirma la decisio economica, es genera la part fiscal si cal.
- el motiu de baixa, data i usuari han de quedar registrats;
- Moodle/correus son efectes operatius, no registre fiscal.

### Veure factura

Ha de ser nomes lectura.

Ha de mostrar:

- numero de factura;
- data emissio;
- receptor fiscal;
- linies;
- total;
- estat VERI*FACTU;
- estat cobrament;
- PDF immutable;
- rectificatives relacionades.
- indicador de factura electronica, si `E_FACT = 1`.

Si la factura es antiga:

```text
Factura historica no VERI*FACTU
```

## Generar factura abans de pagar

URL:

```text
https://intranet.prisma.cat/alumnes/genera-factura-abans-pagar/
```

Pantalla actual:

- cerca inscripcions per `NIF/NIE`;
- mostra resultats amb `ANY`, `MES`, `CURS`, `INCRIT`, `NOM`, `COGNOMS`, `DNI`, `A PAGAR`, `IDPAG` i accio `+`;
- permet seleccionar diverses inscripcions relacionades;
- mostra taula d'inscripcions seleccionades i permet treure-les;
- no permet continuar si es barregen cursos o edicions diferents;
- calcula el preu total sumant `A_PAGAR`;
- construeix `CONCEPTE1` amb curs i participants;
- construeix `CONCEPTE2` amb convocatoria;
- demana `ENTITAT`, permet revisar `CONCEPTE1`, mostra `PREU` i `OBSERVACIONS`;
- genera factura i mostra dades finals amb numero visible, import i inscripcions relacionades;
- permet previsualitzar i descarregar PDF.

Fitxers actuals identificats:

- `alumnes-genera-factura-abans-pagar.php`;
- `js/alumnes-genera-factura-abans-pagar.js`;
- `ajax/alumnes/mostrarInformacioInscripcio_generaFactura.php`;
- `ajax/alumnes/generaFacturaElectronica_Factures.php`;
- `ajax/alumnes/mostraDadesFacturaElectronica_Factures.php`;
- `ajax/alumnes/mostraInscripcionsFacturaElectronica_Factures.php`;
- `ajax/alumnes/mostraPrevFactura_Factures.php`;
- `ajax/alumnes/descarregaFactura.php`.

Passos actuals de pantalla:

1. La ruta PHP nomes valida sessio i carrega estructura, CSS i JS.
2. `general.js` carrega menu, usuari, rols i modals globals.
3. `alumnes-genera-factura-abans-pagar.js` carrega el contingut amb `mostrarMain.php`.
4. Es cerca per `NIF/NIE`.
5. Es seleccionen inscripcions amb el boto `+`.
6. Es valida que totes siguin del mateix curs i mateixa edicio.
7. Es calcula `preuTotal` amb la suma d'`A_PAGAR`.
8. Es generen `concepte1` i `concepte2`.
9. Es tria entitat/receptor, observacions i es genera la factura.
10. Es mostren dades finals, inscripcions vinculades i opcio de previsualitzar/descarregar.

Punts de pantalla que s'han de corregir:

- no basar la factura final nomes en dades copiades de l'HTML;
- no enviar nomes el nom visible de l'entitat, sino receptor fiscal complet;
- no permetre doble emissio per doble clic o reintent;
- no regenerar PDF a partir de dades vives;
- no eliminar fitxers temporals amb un `filename` rebut directament del navegador;
- repetir al servidor totes les validacions que ara fa el JS.

Regla final:

```text
Aquest apartat crea factura real abans de cobrament.
Marca SIF: EMESA_ABANS_COBRAMENT = 1.
No marca automaticament E_FACT.
```

Canvis de pantalla:

- mostrar clarament que la factura s'emet abans de cobrar;
- mostrar receptor fiscal complet, no nomes el nom d'entitat;
- mostrar linies fiscals estructurades abans de confirmar;
- mostrar avis si alguna inscripcio ja te factura relacionada;
- desactivar o substituir URLs individuals quan les inscripcions queden cobertes per factura d'empresa/responsable;
- mostrar estat SIF, estat AEAT, estat cobrament, PDF i QR despres d'emetre.

Accio SIF:

- `issueInvoice()` amb idempotencia per operacio i inscripcions seleccionades;
- creacio de `fact_rels` per cada inscripcio;
- PDF/QR a `factura_documents`;
- si falla SIF, crear incidencia a `errors_verifactu`, no generar factura alternativa local.

## Genera/Edita entitats

URL:

```text
https://intranet.prisma.cat/alumnes/genera-entitat/
```

Fitxers actuals identificats:

- `alumnes-genera-entitat.php`;
- `js/general.js`;
- `js/alumnes-genera-entitat.js`;
- `css/alumnes-genera-entitat.css`.

Entrada PHP actual:

- valida sessio amb `inc/comprovarSessio.php`;
- carrega la carcassa `.sidebar` i `.mainpanel`;
- carrega CSS comuns de formularis, taules, alertes i modals;
- carrega `general.js` i el JS especific de la pantalla;
- no executa la logica d'alta/edicio directament; aquesta depen de JS/AJAX i metodes o endpoints PHP auxiliars.

AJAX i accions actuals:

- `mostrarMain.php`: carrega el contingut principal;
- `generaEntitats.php`: crea entitat i responsable amb `creaEmpresa_Alumnes(...)`;
- `mostrarModalEditaEntitat_Entitats.php`: mostra modal d'edicio amb `mostrarModalEditaEntitat_Alumnes($idEntitat, $idResponsable)`;
- `actualitzaEditaEntitat.php`: desa canvis amb `actualitzaEditaEntitat_Alumnes(...)`;
- `#afegeix-entitat`: valida camps obligatoris i crea;
- `.edita-entitat`: obre modal;
- `.save-edicio`: desa canvis.

Consultes internes confirmades:

- `buscarTotesEntitats`, `buscarEntitat`, `buscarEntitatById`;
- `buscarRespEntitatById`, `buscarRespEntitatByCIF`;
- `insertPersRespEntitat`, `insertEntitat`;
- `updEntitat`, `updEntitatResp`.

Riscos actuals:

- validacio nomes client-side;
- no es veu control de duplicats per CIF;
- actualitzacio per `GET`;
- no hi ha avís si l'entitat te factures ja emeses;
- cal confirmar si el responsable es unic o multiple i si l'edicio tanca sempre el responsable anterior amb `DATAF`.

Pantalla actual:

- bloc `AFEGEIX L'ENTITAT`;
- seccio `DADES DE L'ENTITAT`;
- seccio `DADES DE LA PERSONA QUE GESTIONA L'ENTITAT`;
- bloc `ENTITATS TROBADES`;
- modal d'edicio de dades d'entitat.

Camps de l'entitat:

- `CIF`;
- `RAO`;
- `ADRECA`;
- `CP`;
- `POBLACIO`.

Camps de la persona responsable:

- `NOM`;
- `COGNOMS`;
- `CORREU`.

Taula de resultats:

- `ID`;
- `CIF`;
- `RAO`;
- `ADRECA`;
- `CP`;
- `POBLACIO`;
- `NOM`;
- `COGNOMS`;
- `CORREU`;
- `ACCIO` amb llapis d'edicio.

Modal d'edicio:

- permet modificar dades de l'entitat;
- permet modificar dades de la persona responsable de la gestio;
- botons `CANCELAR` i `DESA`.

Relacio amb SIF:

- l'entitat pot ser receptor fiscal d'una factura d'empresa/responsable;
- la persona responsable pot ser contacte per correus, pagament o consulta de factura;
- en emetre factura s'ha de copiar snapshot fiscal complet al SIF;
- editar l'entitat no modifica factures ja emeses.

Canvis de pantalla:

- avisar si s'edita una entitat amb factures emeses;
- mostrar que els canvis afecten futures factures, no documents ja emesos;
- validar CIF/CP/correu;
- avisar de possible duplicat si ja existeix el CIF;
- diferenciar visualment dades fiscals de dades de contacte;
- seleccionar entitat per ID intern a la resta de pantalles, no pel text visible.

## Consulta - Edita - Anula factura

URL:

```text
https://intranet.prisma.cat/alumnes/factura/
```

Fitxers actuals identificats:

- `alumnes-factura.php`;
- `js/general.js`;
- `js/alumnes-factura.js`;
- `css/alumnes-factura.css`.

Entrada PHP actual:

- valida sessio amb `inc/comprovarSessio.php`;
- el `<title>` del fitxer diu `Consulta / Anul·la factura`;
- carrega la carcassa `.sidebar` i `.mainpanel`;
- carrega CSS comuns de formularis, taules, alertes i modals;
- carrega `general.js` i el JS especific de la pantalla;
- no resol la cerca, anul·lacio o PDF dins el fitxer PHP d'entrada; aquestes accions depenen de JS/AJAX i metodes de `Intranet.php`.

AJAX i accions actuals:

- `mostrarMain.php`: carrega el contingut principal;
- `consultaUsuarisFacturaRelacionada.php`: crida `buscarUsuaris_Factures($dni, $email, $factRel, $factNum)`;
- `mostrarTaulaUsuaris2.php`: crida `mostrarTaulaUsuaris2_Alumnes($dnies, $orderBy, $asc)`;
- `mostrarTotesFacturesUsuari_Factures.php`: crida `mostrarTotesFacturesUsuari_Factures($dni, $cercaPer)`;
- `mostraModalConsultaInformacio_Factures.php`: crida `modalConsultaInformacio_Factures($id)`;
- `guardarDadesFactura_Factures.php`: crida `guardarDadesFactura_Factures(...)` i guarda edicio directa de factura actual;
- `mostrarModalAnulaFactura_Factures.php`: crida `modalAnularFactura_Factures($id)`;
- `anularFactura_Factures.php`: crida `anularFactura($id, $tornar, $dataDevol, $obs)`;
- `mostraModalPrevFactura_Factures.php`: crida `modalPrevisualitzaFactura_Factures($id)`;
- `descarregaFactura.php`: crida `generaFactura($id, true)` i descarrega/genera PDF;
- URL hash: `#/dni/{valor}`, `#/factRel/{valor}` i `#/factNum/{valor}`.

Consultes internes confirmades:

- cerca: `buscaIdFactCorreu`, `buscaIdFactRel`, `buscaIdFactNum`, `buscaIdFactCif`, `buscarIdFact`, `buscarTotesFactId`;
- dades: `buscarInfoFacturaId`, `buscarInfoFacturaByFact`, `buscarInfoFactInsc`, `buscarInfoFactInsc2`;
- edicio directa actual: `updDadesFact`;
- anul·lacio/retorn actual: `updInscAnulFact`, `updInscAnulFact2`, `updInscDataPagAnulFact`, `updInscFraccAnulFact`, `updObsFact`.

Pantalla actual:

- cerca per `NIF/NIE`;
- cerca per `Email`;
- cerca per `Factura relacionada`;
- cerca per `Num. factura`;
- resultats amb `FACTURA REL.`, `ANY`, `N. FACT`, `RAO`, `CIF`, `CURS` i `ACCIONS`;
- accions: veure informacio, anul·lar, veure/descarregar PDF.
- mostra factures `A` i rectificatives `R`;
- pot mostrar diverses factures amb la mateixa factura relacionada;
- les accions poden estar actives o desactivades visualment.

Passos actuals de pantalla:

1. L'usuari cerca per DNI/NIE, email, factura relacionada o numero de factura.
2. La pantalla mostra totes les factures trobades.
3. La icona d'informacio obre modal amb dades d'inscripcio i factura.
4. La icona d'anul·lacio obre modal per confirmar import a tornar i observacions.
5. La icona PDF obre previsualitzacio amb opcio de descarrega.

Modal d'informacio actual:

- mostra dades d'inscripcio: fraccionat, fraccio, observacions de pagament, DNI i `A PAGAR`;
- mostra dades de factura: numero, ordre, data, data pagament, rao, CIF, adreca, poblacio, import, curs, hores, conceptes i observacions;
- te llapis d'edicio, que amb SIF no pot ser edicio directa de factura emesa.
- pot mostrar imports negatius en factures rectificatives historiques;
- barreja informacio d'inscripcio i informacio fiscal, que s'haura de separar visualment millor.
- el JS actual permet editar `rao`, `cif`, `cp`, `poblacio`, `adreca`, `concepte1`, `concepte2` i `observacions` i enviar-ho a `guardarDadesFactura_Factures.php`.

Modal d'anul·lacio actual:

- mostra `ANY-CURS`, `N. FACT`, `PAGAT`, `A TORNAR`, `DATA PAGAMENT`, `DATA DEVOLUCIO` i `OBSERVACIONS NOVA FACTURA`;
- confirma anul·lacio.
- `A TORNAR` s'haura de convertir en retorn, saldo o rectificativa segons el cas;
- `OBSERVACIONS NOVA FACTURA` ha de passar a ser motiu fiscal/administratiu estructurat.

PDF actual:

- previsualitza factura antiga amb logo, receptor, concepte i import;
- pot mostrar factura ordinaria o rectificativa negativa;
- pot tenir diverses pagines;
- pot marcar `ES COPIA`;
- amb SIF, el PDF nou ha de venir de `factura_documents`.

Riscos actuals:

- edicio directa de factura emesa per `guardarDadesFactura_Factures.php`;
- anul·lacio per `GET` amb `A TORNAR`, data i observacions;
- `A TORNAR` no queda modelat encara com a devolucio/saldo/rectificativa;
- descarrega actual pot regenerar PDF;
- errors detectats per text HTML;
- cal corregir la comprovacio de volum de cerca i substituir-la per una longitud real de resultats.

Regla final:

- no editar factura emesa;
- qualsevol canvi de receptor, concepte o import ha d'anar per rectificativa o substitucio controlada;
- l'anul·lacio ha de generar rectificativa quan pertoqui;
- devolucio i saldo han de quedar vinculats a factura i pagament;
- `E_FACT` es marca/desmarca aqui, amb permisos Meriem, Adam i Pablo;
- el sistema ha de demanar motiu i decidir el tipus de rectificativa segons cas.

Canvis de pantalla:

- substituir llapis d'edicio directa per accions SIF;
- afegir estat `VERI*FACTU / historic no VERI*FACTU`;
- afegir estat AEAT;
- afegir estat cobrament;
- afegir historial de rectificatives;
- afegir PDF/QR immutable;
- afegir log visible de qui ha marcat `E_FACT`, qui ha rectificat i quan.
- separar clarament factures historiques no VERI*FACTU de factures SIF;
- convertir el llapis en accions controlades, no edicio directa.

Accions finals esperades:

- veure informacio;
- veure PDF/QR;
- iniciar rectificativa per dades fiscals;
- iniciar rectificativa per import;
- registrar devolucio o saldo;
- marcar/desmarcar `E_FACT`;
- consultar historial d'accions.

## Pagaments i analisi TPV

URL:

```text
https://intranet.prisma.cat/alumnes/pagaments/
```

Fitxers actuals identificats:

- `alumnes-pagaments.php`;
- `js/general.js`;
- `js/alumnes-pagaments.js`;
- `css/alumnes-pagaments.css`.

Entrada PHP actual:

- valida sessio amb `inc/comprovarSessio.php`;
- carrega la carcassa `.sidebar` i `.mainpanel`;
- carrega CSS comuns de formularis, taules, alertes i modals;
- carrega `general.js` i el JS especific de la pantalla;
- no analitza fitxer TPV ni passa pagaments directament; aquestes accions depenen de JS/AJAX i metodes de `Intranet.php`.

AJAX i accions actuals:

- `mostrarMain.php`: carrega el contingut principal;
- `buscarInfomacioPagament.php`: cerca pagaments per DNI, codi regal o numero factura;
- `mostrarModalInfoPag.php`: modal d'informacio del pagament;
- `mostrarModalConfPag.php`: crida `mostrarModalConfPag($numFact)`;
- `efectuarPagament.php`: crida `efectuarPagament($idTipus, $tipus, $pagament, $dataPag, $banc, $obs, $numFact, $efact)`;
- `analitzarFitxerTPV.php`: analitza fitxer TPV i torna JSON;
- `.tipusInsc`: canvia entre alumne (`I`) i grup (`G`);
- `.upd-inscripcio`: confirma el pagament de la fila;
- `#formTPV`: envia fitxer TPV amb `FormData`.

Consultes internes confirmades:

- deutes individuals: `buscarPagaments`;
- packs: `buscarPagamentsPack`, `buscarInfoPack`, `cnsInscsPack`, `cnsDadesCursPack`;
- grups: `buscarPersRespGrup`, `buscarPersRespGrup2`, `buscarPersGrup`, `buscarPagamentsGrup`;
- factura: `buscarPagamentsByFact`, `buscarNomResPagamentsByFact`;
- regals: `buscarRegNoPayByCodi`, `buscarRegNoPayByDni`, `buscarRegalById`;
- updates actuals: `updDateInscr`, `updPayInscr`, `updPayInscrByIdPag`, `updDateInscrByIdPag`, `updFraccBD`, `updFraccBDByFact`, `updPagObsByIdPag`, `updFactRegal`.

Pantalla actual:

- bloc `ANALITZA FITXER` amb selector de fitxer TPV, boto d'analisi i ultim analisi;
- bloc `CERCA` amb `NIF/NIE`, `CODI REGAL`, `NUM FACTURA`;
- selector `ALUMNE / GRUP`;
- resultats amb `TIPUS`, `ANY`, `MES`, `CURS`, `DNI`, `A PAGAR`, `PAGAT`, `PAGAMENT`, `DATA PAG`, `BANC`, `OBSERVACIONS`, `FRACCIO` i accio de confirmacio.

Lectura funcional de la pantalla:

- el bloc superior serveix per analitzar/concordar fitxer TPV;
- el bloc inferior serveix per buscar pagaments pendents o deutes i passar-los manualment;
- la cerca pot ser per alumne, grup, codi regal o numero de factura;
- cada fila representa una inscripcio/concepte susceptible de cobrament o regularitzacio;
- l'usuari pot informar import, data i banc abans de confirmar.

Detalls visibles:

- `Ultim analisi` mostra data i hora de l'ultim fitxer TPV processat;
- `PAGAT` pot tenir imports parcials;
- `OBSERVACIONS` pot contenir reclamacions, pagaments denegats, retorns o notes administratives;
- `FRACCIO` pot mostrar informacio de pagament fraccionat;
- `ACCIONS` confirma el pas del pagament de la fila.
- el JS recalcula visualment `PAGAT` quan s'informa `PAGAMENT`;
- valida data `YYYY-MM-DD`, data no futura i avisa si fa mes de 5 dies.

Riscos actuals:

- el nom de l'endpoint es `buscarInfomacioPagament.php`, amb aquesta grafia;
- falta el PHP de `buscarInfomacioPagament.php` i `mostrarModalInfoPag.php` per documentar metode concret i condicions exactes de cerca;
- pagament manual enviat per `GET` a `efectuarPagament.php`;
- `efact` dins aquest JS pot confondre's amb la marca `E_FACT`;
- falta idempotencia visible per evitar doble pagament;
- el calcul de pagat/pendent es visual i s'ha de recalcular al servidor/SIF;
- el fitxer TPV ja te format i logica actual documentada a `10-procediments-intranet-ecommerce.md`.

Regla final:

```text
Factura existent -> registerPayment()
Factura inexistent i venda facturable -> issueInvoice() + registerPayment()
Factura abans de cobrament -> nomes registerPayment()
```

Canvis de pantalla:

- mostrar factura SIF associada, si existeix;
- mostrar si la factura es abans de cobrament;
- mostrar pendent calculat pel SIF;
- mostrar si el pagament ve de TPV, transferencia, compensacio, regal, grup o registre manual;
- detectar pagament duplicat per `IDPAG`, referencia o import/data;
- avisar si la inscripcio esta coberta per factura d'empresa/responsable;
- permetre compensacio/saldo com a flux identificat, no com a simple import escrit;
- quan el pagament queda registrat, donar opcio de veure factura/PDF/QR;
- si el fitxer TPV te anomalies, crear revisio/incidencia i no fer updates silenciosos.
- mostrar diferencia residual si `A PAGAR` i `PAGAT + PAGAMENT` no quadren exactament;
- bloquejar confirmacio si falta data, import o metode quan siguin obligatoris.

Pendent:

- metode concret de `Intranet.php`;
- format del fitxer TPV ja documentat al procediment; falta decidir validacio final per capçalera i reprocessament;
- taula o log final que substituira o formalitzara `fitxers/analisis-fitxer.txt`;
- taula o llista de bancs/metodes;
- regles exactes per imports parcials, fraccions i compensacions.

### Certificat

No afecta directament la fiscalitat, pero cal mantenir coherencia amb baixes, morositat i estat de curs.

## Apartat VERI*FACTU de la intranet principal

Objectiu:

- donar acces rapid al SIF sense convertir la intranet en la font oficial fiscal.

Ubicacio:

```text
Intranet principal / VERI*FACTU
```

Element de menu:

```text
VERI*FACTU + indicador visual de pendents
```

Ha de mostrar:

- boto `Obrir panell SIF`;
- resum d'incidencies pendents;
- acces a documents SIF;
- acces a exportacions;
- estat general de cua AEAT, si cal;
- data/hora de darrera sincronitzacio amb el SIF.

Accions:

- obrir `pay.prisma.cat/sif`;
- obrir incidencia al panell SIF;
- obrir document SIF;
- obrir exportacions.

No ha de permetre:

- resoldre incidencies oficialment;
- modificar registres fiscals;
- editar factures;
- canviar configuracio SIF.

Font de dades:

```text
SIF / BD fiscal
```

## Panell SIF a pay.prisma.cat/sif

Objectiu:

- administrar el SIF des del domini oficial de facturacio.

Ubicacio:

```text
pay.prisma.cat/sif
```

### Dashboard SIF

Ha de mostrar:

- factures emeses avui/mes/any;
- registres AEAT pendents, acceptats, rebutjats o en retry;
- incidencies obertes per prioritat;
- PDF/QR pendents;
- ultima factura emesa;
- estat connexio AEAT;
- versio activa del SIF.

Accions:

- anar a Factures;
- anar a Registres AEAT;
- anar a Incidencies;
- anar a Exportacions.

### Factures SIF

Ha de permetre:

- cercar per numero visible;
- cercar per UUID;
- cercar per NIF/CIF;
- cercar per `FACTURA_RELACIONADA`;
- filtrar per estat factura;
- filtrar per estat AEAT;
- filtrar per estat cobrament;
- veure linies;
- veure pagaments;
- veure rectificatives;
- veure documents.

Accions permeses segons rol:

- descarregar PDF;
- veure registre fiscal;
- obrir factura relacionada;
- iniciar rectificativa;
- marcar factura electronica.

Prohibit:

- editar factura emesa directament.

### Registres AEAT

Ha de mostrar:

- registre fiscal;
- tipus registre;
- hash;
- hash anterior;
- fiscal order;
- estat AEAT;
- intents;
- proper retry;
- resposta o error AEAT.

Accions:

- veure detall tecnic;
- reintentar si el rol ho permet;
- exportar registres;
- crear incidencia.

### Incidencies SIF

Ha de mostrar:

- prioritat;
- estat;
- factura afectada;
- origen;
- error tecnic;
- responsable assignat;
- historial d'accions.

Accions:

- assignar responsable;
- afegir nota;
- marcar revisada;
- marcar resolta;
- anar a factura o registre afectat.

### Documents SIF

Ha de mostrar:

- PDF;
- XML si correspon;
- QR;
- declaracio responsable;
- documentacio tecnica;
- documentacio de versio;
- hash del fitxer;
- data generacio.

Accions:

- descarregar;
- veure hash;
- veure historial;
- regenerar nomes si el document es regenerable i deixant log.

### Versions SIF

Ha de mostrar:

- versio activa;
- data entrada produccio;
- responsable tecnic;
- responsable legal/direccio;
- declaracio responsable associada;
- historial.

Accions:

- consultar versio;
- descarregar declaracio;
- activar nova versio nomes amb rol autoritzat.

### Exportacions SIF

Ha de permetre:

- exportar factures per periode;
- exportar registres AEAT;
- exportar events;
- exportar incidencies;
- exportar documents vinculats;
- exportar relacions factura-inscripcio/pagament.

Ha de guardar:

- usuari;
- data;
- criteris;
- fitxer;
- hash;
- motiu si es per requeriment.

### Configuracio SIF

Ha de permetre gestionar:

- dades emissor fiscal;
- series actives;
- mode VERI*FACTU;
- endpoints AEAT;
- certificat digital/configuracio segura;
- retries;
- rutes de documents;
- permisos/rols;
- estat de workers.

Acces:

- nomes responsable tecnic o administrador SIF.

Regla:

```text
tot canvi de configuracio genera log
```
