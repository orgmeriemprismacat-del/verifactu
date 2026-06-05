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

### Mecanisme actual de permisos

El xat antic confirma que la intranet ja te un sistema de permisos basat en rols per apartat:

- `apartats.ROLS_VISUALITZAR`;
- `apartats.ROLS_EDITAR`;
- `apartats.ROLS_ENVIAR_MSG`;
- `usuaris.ROLS`.

També s'han identificat els punts tecnics:

- `consultaRolsEdiicio($page)` consulta els rols d'edicio de la pagina; es conserva el nom real encara que tingui la grafia `Ediicio`;
- `consultaRolsUsuari()` consulta els rols de l'usuari autenticat;
- el JavaScript calcula `tePermisEdicio` comparant rols de pagina i rols d'usuari;
- `general.js` carrega menu, usuari, rols i modals globals.

Regla de migracio:

```text
Els permisos visuals del JS no son suficients per accions fiscals.
Cada endpoint AJAX o SIF ha de tornar a validar permisos al servidor.
```

Les accions fiscals critiques no s'han de protegir nomes amagant icones o botons. Han d'exigir validacio de sessio, rol, estat de factura i motiu.

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

### Revisio especialitzada del subbloc

Revisio feta despres de tancar el xat pont general.

Conclusio:

```text
Consulta - Modifica alumne esta identificada.
No cal tornar a descobrir la pantalla.
El que cal ara es convertir-la en especificacio executable, proves i captures finals.
```

El xat antic confirma el mapa funcional de la pantalla:

| Element | Estat actual explicat | Criteri VERI*FACTU |
| --- | --- | --- |
| Dades personals | Bloc amb boto d'edicio. | Modifica dades operatives; no toca factures emeses. Si cal corregir dades fiscals d'una factura, s'obre rectificativa/substitucio. |
| Inscripcions pendents | Taula amb `TIPUS`, `ANY`, `MES`, `CURS`, `INSCRIT`, `TITOL` i `ACCIONS`. | Ha de separar estat academic, cobrament, factura i AEAT. |
| Inscripcions acabades | Mateixa estructura que pendents. | Ha de mostrar historic, factura SIF o factura historica no VERI*FACTU segons cas. |
| Observacions generals | Bloc d'observacions de l'alumne. | Seguiment administratiu, no registre fiscal. |
| Icona 1 | Informacio de l'alumne/inscripcio. | Consulta; no modifica fiscalitat. |
| Icona 2 | Canvi de curs. | Ha d'obrir flux amb recalcul, motiu, previsualitzacio fiscal i registre d'event. |
| Icona 3 | Baixa. | Baixa administrativa inicial; no rectificativa automatica. |
| Icona 4 | Veure factura. | Nomes lectura i descarrega PDF; factura nova des de `factura_documents`. |
| Icona 5 | Veure certificat. | Sense impacte fiscal directe. |
| Icones amb baixa opacitat | Accio no disponible. | La desactivacio visual s'ha de repetir com a validacio de servidor/SIF. |

Punts especifics recuperats:

- quan l'alumne es moros, la pantalla pot permetre consultar factura si hi ha part pagada i veure que s'ha pagat;
- l'anul·lacio o rectificativa no s'ha de resoldre des d'aquesta fitxa, sino a `Consulta - Edita - Anula factura`;
- en canvi de curs, el sistema actual recalcula automaticament `A_PAGAR` segons el descompte original i si encara aplica al nou curs;
- les despeses de gestio del canvi de curs es calculen automaticament, pero poden ser editables en casos puntuals;
- l'`A_PAGAR` tambe pot ser editable en casos puntuals, pero amb SIF ha de portar motiu i impacte fiscal;
- la URL de pagament que es veu a dades de pagament s'ha de moure a `pay.prisma.cat`;
- la visualitzacio actual de factura es de lectura i descarrega PDF;
- el concepte de factura historica surt de camps estructurats de `web.factures`, especialment `concepte1` i `concepte2`;
- si hi ha part pagada i canvi a curs mes barat, Adam fa el retorn manualment i la rectificativa es genera des de l'apartat de factura;
- les despeses de gestio actualment estan incloses dins l'import final, pero en el SIF conve convertir-les en linia explicita quan generin factura nova o diferencia.

Criteri per donar el subbloc per tancat documentalment:

- guardar captures finals de vista general, dades del curs, dades de pagament, canvi de curs, baixa, veure factura i certificat;
- indicar per cada icona quan esta activa, quan queda desactivada i quin missatge/motiu es mostra;
- documentar el cos real dels metodes que encara faltin: `guardarDadesPagament_modalsresultatCerca()`, `realitzarCanviCurs_modalCanviCurs()`, `confirmaBaixa_modalDonarBaixa()` i `generaFactura()`;
- convertir cada subflux en proves: dades personals amb factura emesa, dades de pagament amb factura existent, canvi de curs mes car/barat/mateix import, baixa amb retorn/saldo/no retorn, veure factura historica i veure factura SIF.

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

### Revisio especialitzada del subbloc

El xat antic confirma que aquesta pantalla emet una factura real abans del cobrament, normalment per empresa, responsable o situacio on cal factura previa per poder cobrar.

Detalls recuperats de la pantalla actual:

- la cerca es fa per `NIF/NIE` i crida `mostrarInformacioInscripcio_generaFactura.php` per `GET`;
- el JS copia cel·les HTML de la taula de resultats cap a `INSCRIPCIONS RELACIONADES AMB LA FACTURA A GENERAR`;
- el boto `+` es desactiva afegint `no-disponible` i traient `add-inscripcio`;
- el boto de treure inscripcio torna a activar el boto `+` original;
- `continue-pas-2` comprova `tePermisEdicio`, curs unic i edicio unica;
- `preuTotal`, `cursos`, `edicions`, `concepte1` i `concepte2` es calculen al navegador;
- `concepte2` depen d'una crida AJAX a `calcularTextData.php`;
- `continue-pas-3` envia `empresa`, `concepte1`, `concepte2`, `preu`, `cursos`, `edicions`, `inscripcions` i `observacions` a `generaFacturaElectronica_Factures.php`;
- despres de crear factura, el JS consulta dades finals i inscripcions relacionades;
- la previsualitzacio i descarrega passen per `modalConsultaFactura_Factures()` i `descarregaFactura.php`.

Riscos concrets recuperats:

- `idsInsc` s'omple amb `push()` quan es continua al pas 2; si l'usuari torna enrere o repeteix el pas, pot acumular IDs duplicats si no es reinicialitza;
- `entitatMarcada` guarda text visible, no snapshot fiscal ni ID intern obligatori;
- la comprovacio d'errors a la resposta de dades de factura pot mirar la variable equivocada si no es revisa el JS final;
- hi ha una crida de fallada escrita com `rerrorFunction`, que s'ha de confirmar/corregir en implementacio;
- el servidor rep valors construits al client i ha de recalcular-ho tot abans d'emetre;
- `descarregaFactura.php` regenera PDF i `eliminarArxiu.php` rep `filename` per `GET` i fa `unlink($filename)`;
- el nom `generaFacturaElectronica` pot confondre's amb `E_FACT`, pero aquest flux no marca factura electronica per defecte.

Criteri de tancament del subbloc:

- la pantalla final ha de mostrar clarament `Factura emesa abans de cobrament`;
- la seleccio d'entitat ha de desar ID intern i snapshot fiscal complet;
- les inscripcions seleccionades s'han de validar al servidor: estat, curs, edicio, import, receptor i factura previa;
- la factura resultant ha de tenir `EMESA_ABANS_COBRAMENT = 1` i `E_FACT = 0` per defecte;
- cada inscripcio ha de quedar vinculada a la factura SIF per `fact_rels`;
- les URLs individuals de pagament han de quedar bloquejades o substituides quan el pagament correspon a la factura d'empresa/responsable;
- el PDF/QR s'ha de servir de `factura_documents`, no regenerar-se des de dades vives;
- el pagament posterior ha d'entrar per `Passar pagaments` o URL de factura i fer nomes `registerPayment()`.

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

### Revisio especialitzada del subbloc

Informacio concreta recuperada del xat antic:

- `guardarDadesFactura_Factures()` executa `updDadesFact` sobre `web.factures`, retorna text pla `OK` i no demana motiu, tipus de rectificativa ni log fiscal;
- l'edicio directa modifica `factura_relacionada`, rao, CIF, adreca, CP, poblacio, `concepte1`, `concepte2` i observacions de la factura existent;
- `modalAnularFactura_Factures($id)` obre el modal amb `A TORNAR` inicialitzat a l'import de la factura i `DATA DEVOLUCIO` amb la data actual;
- el codi antic contenia un avis comentat per factures relacionades amb mes d'una inscripcio: calia ajustar pagaments manualment a `inscripcions`, cosa que amb SIF ha de passar a assignacions, devolucions o saldo controlat;
- `anularFactura($idFact, $tornar, $dataDevol, $obsDev)` crea una factura historica `R{any}/{ordre}` amb import negatiu, copia dades fiscals/conceptes de l'original i conserva la mateixa `factura_relacionada`;
- despres de la factura `R`, el flux antic actualitza resums economics d'inscripcio amb `updInscAnulFact`, `updInscAnulFact2`, `updInscDataPagAnulFact`, `updInscFraccAnulFact` i `updObsFact`;
- la rectificativa historica clona el valor `E_FACT` de la factura original, pero en el SIF final la marca `E_FACT` ha de ser una accio administrativa separada i auditada;
- l'accio JS `.confirma-baixa` executa anul·lacio de factura i pot confondre's amb baixa d'inscripcio.

Criteri SIF per tancar aquest subbloc:

- la pantalla passa a ser centre de control de factures emeses, no editor directe;
- les factures SIF han de quedar en lectura per a receptor, concepte, import, numeracio, data, PDF/QR i registre AEAT;
- qualsevol canvi de receptor, concepte o import ha de crear rectificativa/substitucio amb motiu estructurat;
- anul·lacio total o parcial ha de generar rectificativa quan pertoqui i registrar devolucio, saldo o compensacio contra pagaments reals;
- si una factura te diverses inscripcions vinculades, la pantalla ha de mostrar assignacions i imports afectats abans de confirmar;
- els endpoints destructius no han d'anar per `GET` ni acceptar imports lliures sense validacio servidor;
- els PDFs nous no es regeneren amb dades vives; es llegeixen de `factura_documents`;
- les factures historiques no VERI*FACTU poden ser consultades, pero s'han d'etiquetar com a historiques i no poden usar-se com a model de modificacio SIF.

Proves finals especifiques:

- intentar editar una factura SIF i comprovar que no es fa `updDadesFact`;
- rectificar dades fiscals amb motiu i verificar nova factura/registre relacionat;
- rectificar import total i parcial amb devolucio o saldo vinculat;
- anul·lar una factura amb diverses inscripcions i revisar assignacions;
- marcar i desmarcar `E_FACT` amb usuari, data i motiu;
- descarregar PDF/QR immutable sense regeneracio;
- bloquejar anul·lacio o rectificativa si l'usuari no te permis servidor.

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
Factura inexistent i venda facturable -> issueInvoice() amb bloc payment
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

### Revisio especialitzada del subbloc

El xat antic confirma que aquesta pantalla concentra dos fluxos que s'han de tractar separats encara que comparteixin URL:

1. analisi de fitxer TPV per detectar pagaments cobrats pel banc que no consten conciliats;
2. pas manual de pagaments, transferencies, regals, grups, fraccions o regularitzacions.

Regles recuperades del JS actual:

- la cerca nomes pot tenir un criteri informat: `NIF/NIE`, `CODI REGAL` o `NUM FACTURA`;
- el selector `ALUMNE / GRUP` envia `tipusInsc = I` o `tipusInsc = G`;
- el formulari TPV envia `fitxer-tpv` amb `FormData` i espera JSON;
- `state = 1` significa analisi sense incidencies;
- `state = 2` mostra `registresPagErrors` i enllacos cap a alumne o factura;
- `state = 0` indica format o validacio incorrecta;
- els enllacos d'incidencia obren `alumnes/mostrar-alumne/#/{dni}` o `alumnes/factura/#/{dni}`.

Regles recuperades del PHP actual:

- `efectuarPagament.php` rep `id`, `tipus`, `pagament`, `dataPag`, `banc`, `obs`, `numFact` i `efact`;
- `mostrarModalConfPag.php` carrega dades de `web.factures` i avisa que el sistema "actualitzara la factura";
- aquest text i comportament no poden passar al SIF final com a actualitzacio de factura emesa;
- el flux final ha de dir "registrar pagament contra factura existent" quan ja hi ha factura;
- el camp `efact` d'aquesta pantalla s'ha de reanomenar o documentar com a `te_factura_generada` o equivalent, per no confondre'l amb la marca fiscal `E_FACT`;
- els imports, data, banc i observacions no han de viatjar per `GET` en el disseny final.

Subcas recuperat: transferencia validada a intranet.

- si `efact == 0`, `efectuarPagament()` encara calcula factura historica i deriva per tipus `R`, `G`, `P` o `I`;
- si `efact != 0`, crida `efectuarPagamentFacturaGenerada()` i el sistema antic actualitza `factures.data_pagament`, `factures.IMPORT` i `FORMA_PAGAMENT` amb `updFactGenerada`;
- aquest mateix flux reparteix l'import entre membres de la factura amb `searchMembresFactRel`, `updPayInscr` i `updDateInscr`;
- si el cobrament queda parcial, actualitza `FRACCIO` amb `updFraccBDByFact`;
- en pantalla final SIF, aquest subcas s'ha de mostrar com a registre de cobrament contra factura, no com a edicio de factura.

Canvis especifics de pantalla per transferencia:

- mostrar si la factura trobada ja es SIF, historica o factura abans de cobrament;
- mostrar pendent recalculat al servidor abans de permetre confirmar;
- demanar metode/banc i referencia bancaria quan correspongui;
- avisar si `NUM FACTURA` apunta a factura anul·lada, rectificada totalment o coberta per una altra assignacio;
- despres de confirmar, mostrar `UUID_PAYMENT`, factura assignada i estat de cobrament.

Criteri de tancament del subbloc:

- revisar o recuperar els cossos de `buscarInfomacioPagament.php` i `mostrarModalInfoPag.php`;
- conservar com a recuperats documentalment `mostrarModalConfPag.php`, `efectuarPagament.php`, `efectuarPagamentFacturaGenerada()` i `analitzarFitxerTPV.php`;
- definir la llista final de `BANC`/metodes i la correspondencia amb `payment_transaction.method`;
- definir idempotencia per `IDPAG`, referencia bancaria/TPV, `DS_ORDER`, import, data i origen;
- provar que una factura existent no es duplica i nomes rep `registerPayment()`;
- provar que una factura abans de cobrament queda pendent fins que es registra el cobrament;
- provar que un TPV reprocessat no crea moviments duplicats;
- provar que les incidencies TPV queden registrades i no es resolen amb updates silenciosos.

### Certificat

No afecta directament la fiscalitat, pero cal mantenir coherencia amb baixes, morositat i estat de curs.

## Reclamacions i morositat

El xat antic concreta la sequencia operativa de reclamacions:

```text
abans de comencar curs
    -> ha d'existir un primer pagament o justificacio
segona setmana de curs
    -> si no ha pagat res i no hi ha justificacio, es pot donar de baixa
final de curs
    -> es reclama el pendent
una setmana despres
    -> nova reclamacio
un mes despres
    -> nova reclamacio
despres
    -> es considera morositat i es continua reclamant
```

Cada fase te apartat propi a la intranet:

- `/facturacio/primera-reclamacio/`;
- `/facturacio/baixes/`;
- `/facturacio/recordatori-pagament/`;
- `/facturacio/reclamacio-final/`;
- `/facturacio/morosos/`.

Regla VERI*FACTU:

- morositat no es baixa;
- marcar un alumne com a moros no rectifica automaticament la factura;
- la factura continua existint mentre no hi hagi devolucio, rectificativa o decisio fiscal posterior;
- les reclamacions han de quedar com a events operatius amb import total, import pagat, import pendent, fase, data, missatge i estat.

## Intranet alumne, empresa/responsable i accessos externs

Aquest subbloc regula qui pot veure factures i documents fora de la intranet principal.

Informacio recuperada del xat antic:

- actualment aquesta visibilitat externa encara no existeix com a flux complet nou;
- una factura pagada per l'alumne pot ser visible a l'alumne;
- una factura pagada per empresa, grup o responsable no ha de ser visible automaticament als participants;
- si una empresa paga un grup, cada participant no pot veure la factura completa;
- la factura d'empresa/grup nomes la pot veure l'empresa o responsable autoritzat;
- el PDF generat en emissio s'ha de guardar en un espai no public de `pay.prisma.cat`;
- la intranet o l'enllac segur han de servir el document amb comprovacio de permisos, no donar mai la ruta directa del fitxer.

Regles de visibilitat:

| Cas | Visible per alumne | Visible per empresa/responsable | Criteri |
| --- | --- | --- | --- |
| Factura individual a nom de l'alumne | Si | No, excepte autoritzacio documentada | L'alumne pot consultar PDF/QR i estat si la factura li correspon. |
| Factura d'empresa/responsable per una inscripcio | No automaticament | Si | L'alumne pot veure que la inscripcio esta coberta, pero no la factura completa si no n'es receptor. |
| Factura de grup amb diversos participants | No | Si | Proteccio de dades i privacitat dels altres participants. |
| Factura historica no VERI*FACTU | Segons receptor i relacio | Segons receptor i relacio | Etiqueta historica; no es regenera PDF. |
| PDF/QR pendent | Estat visible, no PDF improvisat | Estat visible, no PDF improvisat | Esperar `factura_documents` o mostrar incidencia/pendent. |

Canvis necessaris:

- crear o adaptar la consulta de factures visibles de la intranet personalitzada de l'alumne;
- crear endpoint d'enllac segur per empresa/responsable quan calgui enviar factura o URL de pagament;
- validar token, receptor, relacio amb factura i estat del document abans de servir PDF/QR;
- no exposar rutes internes ni paths de `factura_documents`;
- mostrar estat de factura, cobrament i PDF/QR sense donar accions fiscals;
- registrar acces a documents fiscals quan sigui necessari per auditoria;
- distingir clarament "inscripcio coberta per empresa/responsable" de "factura visible per l'alumne".

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
