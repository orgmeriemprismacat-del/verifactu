# Fitxa funcional — Pujada d'aules obertes (cas d'ús independent)

**URL real confirmada per negoci (22/09/2026):** https://intranet.prisma.cat/cursos/fi-cursos/pujar-aules-obertes/ . **Nom de la pantalla:** «Pujada aules obertes» (títol literal al PHP). **Identificador de treball:** CAND-UC-MOODLE-AO-01, sense atribuir-li un número dels 142 casos fins a contrastar el catàleg; **és un cas diferent** de [«Generar fitxer pujada alumnes»](../07-uml-integrat/uc-moodle-pujada-alumnes-fitxa-activitats.md) i de la creació d'inscripcions a PrisMa (UC-113). **Versió base auditada:** `main`, `e71958b3026549bde09fb4b25f2ec3ba370937ec`. **Àmbit TANCAT del present cas:** accions i resultat de LA PÀGINA, incloent selecció, `PERENNE`, fitxer, modal, errors i represa; **no inclou cap pas de càrrega posterior al campus**, per decisió expressa de l'usuària. La denominació «pujada» no acredita cap sincronització externa automàtica.

**Llegenda:** ACTUAL = font PHP/JS versionada, amb URL confirmada per negoci; FINAL = comportament objectiu documentat per poder adaptar la pantalla, no desplegat; TEST = prova definida però no executada. No atribuir rols concrets o regles de deute no observats al servidor.

## 1. Metadades, actor, objectiu i abast

**Actor iniciador:** usuari de la intranet amb accés de visualització a l'apartat i permís d'edició, segons les comprovacions realment implementades; el nom de rol administratiu concret no queda establert únicament per aquesta pantalla. **Objectiu:** seleccionar inscripcions EXISTENTS de cursos per preparar el CSV d'aules obertes i registrar a la BD llegada l'estat `PERENNE=1` de les files seleccionades. **No crea cap nova inscripció, no canvia l'aula de curs de la persona i no registra un cobrament o factura.**

**Entrades:** URL exacta de «Fi de cursos > Pujar aules obertes», llistat de candidates, marques «Pujar/No Pujar», botó «Confirma». **Sortides:** missatges de resultat per fila, fitxer `pujada-ao-<data-hora>.csv`, enllaç `/fitxers/<nom>`, actualització de `PERENNE` a la BD web. **Fora d'abast:** cap càrrega/importació posterior del CSV en sistemes externs, ni decidir-ne el procediment; el cas s'acaba amb el resultat de la pantalla i la disponibilitat coherent del fitxer.

## 2. Manifest de fonts i punt d'entrada

| Codi real | Evidència |
| --- | --- |
| [Pantalla `cursos-fi-cursos-pujar-aules-obertes.php`](../../codi-drive/intranet-actual/cursos-fi-cursos-pujar-aules-obertes.php) | Comprova `inc/comprovarSessio.php`; si no hi ha configuració/sessió vàlida redirigeix a l'inici; carrega el JS específic i el contenidor dinàmic `.mainpanel`. |
| [JS `cursos-fi-cursos-pujar-aules-obertes.js`](../../codi-drive/intranet-actual/js/cursos-fi-cursos-pujar-aules-obertes.js) | Obtén `window.location.pathname`, crida `ajax/mostrarMain.php`, altera marques; confirma, crea fitxer, envia POST per fila, mostra modal i enllaç, recarrega en tancar-lo. |
| [`ajax/mostrarMain.php`](../../codi-drive/intranet-actual/ajax/mostrarMain.php) | Busca `apartats.URL`, llegeix `ROLS_VISUALITZAR` i comprova `tePermisVisualitzacio` abans de `Intranet::mostrarPage()`. El valor exacte del registre d'`apartats` no s'ha consultat en BD productiva; la URL ha estat aportada per negoci. |
| [`Intranet::__mostrarPage_Inici_Pujar_AO`](../../codi-drive/intranet-actual/Intranet.php#L3278-L3301) | Mostra el botó d'accés si existeixen candidates; altrament, «No hi ha registres». |
| [`Intranet::__mostrarPage_Cursos_Pujada_AO`](../../codi-drive/intranet-actual/Intranet.php#L3307-L3461) | Consulta les candidates, construeix la taula de cursos, mostra les dades i el botó per fila i el botó «Confirma». |
| [`ajax/inici/crearFitxerAO.php`](../../codi-drive/intranet-actual/ajax/inici/crearFitxerAO.php) → [`Intranet::crearFitxerAO`](../../codi-drive/intranet-actual/Intranet.php#L3488-L3513) | Crea el fitxer amb capçalera CSV abans de recórrer les files seleccionades. |
| [`ajax/inici/pujarAulesObertes.php`](../../codi-drive/intranet-actual/ajax/inici/pujarAulesObertes.php) → [`Intranet::pujar_AO`](../../codi-drive/intranet-actual/Intranet.php#L3522-L3565) | Fa UPDATE `PERENNE=1`, després hi afegeix una fila CSV i retorna el nom de fitxer. |
| [`Intranet.php`, SQL actual](../../codi-drive/intranet-actual/Intranet.php#L499-L509) i [UPDATE](../../codi-drive/intranet-actual/Intranet.php#L1073-L1074) | `exPujadaAO` selecciona `PERENNE=0 AND INSC CURS=1`; `updPujadaAO` actualitza per USUARI/CURS/ANY/MES/INSC CURS/PERENNE. |

## 3. Precondicions i dades d'origen

La consulta de candidates `exPujadaAO` parteix de `inscripcions` amb `PERENNE='0'` i `INSC CURS='1'`. **No** parteix d'inscripcions sense curs: és un procés de fi de curs, no d'alta inicial. La vista només pot materialitzar les candidates que encaixin amb les unions reals de `curs`, `aula`, `rel_cuho`, `honoraris` i `personal` del tutor (condicions d'unió a [Intranet.php L3337–3346](../../codi-drive/intranet-actual/Intranet.php#L3337-L3346)); això pot explicar que una inscripció d'`exPujadaAO` no es mostri si manquen dades relacionades.

Les dates de finalització de curs (`DATAF`) es **mostren**; no hi ha, a `exPujadaAO`, un filtre SQL de data actual que es pugui afirmar com a requisit automàtic d'elegibilitat. No afegir un bloqueig per pagament pendent, certificat o data finalitzada com si existís: les columnes es mostren, però el codi revisat no les utilitza per desmarcar per defecte una candidata.

## 4. Pantalla real, apartats i camps visibles

La pàgina carrega un `mainpanel` des del servidor, amb navegació i títol de l'apartat de la BD; el contenidor principal és `#pujar-ao`. La vista genera una taula quan hi ha candidates; el codi agrupador obre una taula nova quan canvia el codi CURS de l'element anterior. Les columnes són: **Any, Mes, Curs, Nom, Cognoms, Email, User, Perenne** (botó que el PHP renderitza inicialment amb text «Qualifica» i el JS converteix en «Pujar»), **Població, Codi GTAF, Data fi, Certificat, Obs cert, Perfil, Titulació, A pagar, Pagament, Nom i cognoms tutor, Pendent i Reclamat**.

**No són controls d'aquest apartat:** editar dades personals, consultar modal d'inscripció, reassignar aula, emetre factura, registrar pagament, modificar certificat o seleccionar tutors. El JS compartit conté també codi per a `#pujada-inscr`, però aquest selector és d'una **altra pantalla**: no descriure'l com un formulari, botó o apartat d'aules obertes.

## 5. Actors, permisos i separació de lectura/escriptura

**ACTUAL:** `comprovarSessio.php` a la pàgina i `tePermisVisualitzacio(ROLS_VISUALITZAR)` al `mostrarMain.php`; `tePermisEdicio` al **JavaScript** abans de confirmar. Els dos endpoints de creació/pujada recuperen objectes de sessió i invocen mètodes, però **no s'observa en aquest tram una comprovació explícita de l'actor/permís d'edició i de la seva edició/curs**. Això és un buit d'evidència d'aquest recorregut, **no** una afirmació que el servidor productiu sigui públic o estigui desprotegit.

**FINAL:** comprovar al servidor sessió, permís d'acció, curs/edició i fitxer corresponent al lot; negar lectura, POST o descàrrega no autoritzats amb registre d'intent. No transmetre dades personals al client de qui no tingui accés, i no basar autorització en el fet que el botó sigui visible.

## 6. Flux principal ACTUAL — inici i consulta

1. L'operador obre la URL confirmada i la pàgina comprova la sessió; el JS demana el `main` amb la ruta.
2. `mostrarMain.php` reconstrueix títol/breadcrumb d'`apartats`, comprova el rol de visualització i invoca el renderitzat de l'apartat.
3. Des de l'inici es mostra el botó «Vull pujar alumnes a l'aula oberta» només si `exPujadaAO` detecta candidates. A la pàgina de destí es genera el llistat amb les unions indicades; si no hi ha resultats es mostra «No hi ha resultats».
4. La fila conté dades d'identificació, edició, estat acadèmic/econòmic **de consulta** i una marca «Pujar» per defecte. No hi ha cap altra acció d'edició de dades en la fila.

## 7. Flux principal ACTUAL — seleccionar i confirmar

5. L'operador pot alternar «Pujar» ↔ «No Pujar» en una o diverses files. El JS afegeix/treu les classes `marcat`/`no_marcat`; encara no fa cap UPDATE.
6. En prémer «Confirma», si `tePermisEdicio` és fals el navegador mostra modal de denegació. Si és cert, es buida el modal de resultat i es crida `crearFitxerAO.php` **abans** de comprovar si hi ha cap fila marcada.
7. Si crear el fitxer retorna un text que inclou «error», es mostra alerta; si la petició falla, es crida `rerrorFunction` (nom que apareix literalment en aquesta branca del JS). Si aparentment funciona, se'n conserva el nom.
8. Per cada botó encara marcat, el JS recupera de la taula `idCurs`, `usuari`, any, mes, curs, nom, cognoms, correu i població, i fa POST independent a `pujarAulesObertes.php`; no envia `ID_INSC` ni aula com a clau de destinació. Les peticions no s'encadenen ni esperen l'èxit de les altres abans de sortir del bucle.

## 8. Flux principal ACTUAL — persistència, fitxer i resultat

9. Cada POST crida `Intranet::pujar_AO()`: executa `UPDATE inscripcions SET PERENNE='1'` per coincidència de `USUARI LIKE ?, CURS=?, ANY=?, MES=?, INSC CURS=1, PERENNE=0`; **no filtra per `ID_INSC` ni per grup/aula**. En el codi mostrat, l'èxit de `prepare/execute` no comprova el nombre de files realment afectades.
10. Tot seguit s'obre el fitxer i s'afegeix la fila CSV amb `username;[password buit];firstname;lastname;email;city;lang=ca;course1=curs;autosubscribe=0;maildisplay=2`. **El camp `course1` d'aquest procés és `$curs`**, no el `$any.$curs.$mes.$aula` de la pujada inicial d'alumnes.
11. En resposta, el JS incorpora el missatge «S'ha actualitzat el perenne…» al modal; si hi troba «error», mostra una alerta. **L'enllaç al fitxer s'afegeix quan respon la fila d'última posició del bucle, encara que altres peticions continuïn en curs**; no es prova que tot el fitxer estigui acabat.
12. L'enllaç visible és `https://intranet.prisma.cat/fitxers/<nom fitxer>` amb text «Fitxer pujada aules obertes». Quan es tanca el modal `#modalActualitzarPerenne`, el JavaScript recarrega la pàgina. **Aquí acaba l'abast d'aquest cas.**

## 9. Formats, dades i persistència — diccionari de camps

| Camp | Consulta, ús o modificació |
| --- | --- |
| `inscripcions.ID` | Identifica les candidates a la consulta inicial; **no** es propaga al POST `pujarAulesObertes.php` ni al WHERE d'UPDATE. |
| `INSC CURS` | Condició d'elegibilitat `1`; no s'actualitza en aquest cas. |
| `PERENNE` | Condició inicial `0`; **únic camp de `inscripcions` que modifica `updPujadaAO`**, a `1`. |
| `USUARI`, `CURS`, `ANY`, `MES` | Valors del POST obtinguts del DOM i clau de cerca actual; no són suficients per garantir exactament un `ID_INSC`. |
| `GRUP`/`aula` | S'utilitza a la unió de la consulta de pantalla; **no és camp del POST ni del WHERE d'UPDATE ni del valor `course1` en aquest cas**. |
| `A_PAGAR`, `PAGAMENT`, `pendent`, `reclamat` | Només informatius en la vista; el cas no realitza cap escriptura comptable, bancària o fiscal. |
| `CERTIFICAT`, `OBS CERT`, `DATAF`, tutor, `GTAF_AULA` | Mostrats per contextualitzar la fila; no s'alteren en confirmar. |
| `fitxer`, `nom`, `cognoms`, `email`, `poblacio` | Valors enviats pel DOM i escrits al CSV; **el servidor no rellegeix explícitament les dades de fila abans d'escriure-les** al mètode auditat. |
| CSV i codificació | Nom `pujada-ao-<d-m-Y_H:i:s>.csv`, camí relatiu `../../fitxers/`, separador punt i coma i `mb_convert_encoding(…, 'ISO-8859-1','UTF-8')`; en el codi revisat no consta ús de `fputcsv`, escapada dels separadors ni lock de fitxer. |

## 10. Regles actuals i diferències de l'objectiu FINAL

**ACTUAL:** candidatura = `PERENNE=0 && INSC CURS=1`; la vista pot requerir unions amb la informació del tutor; marques «Pujar» per defecte; confirmació crea CSV i fa POST per fila; UPDATE abans de fwrite; resum per callbacks asíncrons. **FINAL:** conservar aquestes regles d'elegibilitat com a base, revalidar-les al servidor per **ID_INSC** i compte acadèmic real, limitar a una fila de destinatari per inscripció; impedir un lot buit; guardar/recuperar cada resultat i evitar exposició de dades. **No** introduir com a regla aprovada un bloqueig general per deute, data fi, certificat o altres condicions que el codi no implementa i negoci no ha definit per aquest apartat.

## 11. Fluxos alternatius i errors actuals

| Escenari | Comportament observat i tractament FINAL |
| --- | --- |
| Sense candidates | Botó d'inici absent i text «No hi ha registres» o «No hi ha resultats»; FINAL manté estat buit sense fitxer. |
| Una o totes les files desmarcades | Es crea **abans** el fitxer de capçalera; després s'avisa «No has marcat cap canvi». FINAL comprova selecció abans de crear fitxer. |
| Permís d'edició fals al JS | Modal de denegació, sense POST des del flux normal; FINAL denegació equivalent al servidor per petició directa. |
| Error de creació CSV | Resposta textual amb «error» → alerta; fallada AJAX → `rerrorFunction`, que s'ha de revisar com a nom real de callback. FINAL error tipificat, neteja del fitxer temporal i resultat no confirmat. |
| UPDATE correcte però falla `fopen/fwrite` | La BD pot tenir `PERENNE=1` sense fila CSV; FINAL traça recuperable i coherència entre BD i fitxer. |
| UPDATE no afecta cap fila | El codi no comprova `affected_rows` abans d'escriure CSV; FINAL no afegeix una fila com a «actualitzada» si no hi ha inscripció vàlida o resultat idempotent recuperable. |
| POST asíncrons arriben fora d'ordre | Es pot mostrar enllaç amb fitxer incomplet; FINAL espera totes les files i valida recompte/fitxer abans d'oferir descàrrega. |
| Dues inscripcions amb la mateixa clau de WHERE | L'UPDATE pot afectar més d'una fila sense relació amb la selecció; FINAL actualització per ID_INSC i control de concurrència. |
| Nom de fitxer o contingut manipulat al POST | Mètode actual usa el nom de fitxer rebut i dades de navegador; FINAL referència opaca de lot associada a sessió/usuari i dades reobtingudes de BD. |
| Copiar l'enllaç `/fitxers/` | La protecció efectiva del directori no s'ha inspeccionat; FINAL descàrrega autoritzada i retenció limitada. |

## 12. Límits i incompatibilitats conegudes d'aquest codi

La pàgina d'aules obertes **no** inclou selector d'aula, modal d'edició personal ni botó de cerca de l'alumne; són accions de l'altra pàgina de pujada que apareixen reutilitzades al final del JS, sota `#pujada-inscr`. En aquesta vista el DOM rellevant és `#pujar-ao`. El text inicial del botó d'una fila és «Qualifica» al HTML i «Pujar» un cop s'executa el JS; documentar tots dos sense definir «Qualifica» com una altra acció de qualificació acadèmica.

El SQL actual construeix una llista `$ids` concatenant predicats `i.ID=...` amb `OR`; `$cnt` s'inicialitza a zero i **no s'incrementa** ([L3319–3324](../../codi-drive/intranet-actual/Intranet.php#L3319-L3324)). En aquest bucle, per **dues o més** files, la condició `$cnt < num_rows-1` roman certa i intercala `OR` després de la primera; **no és correcte atribuir-li per això sol una manca d'operador en una tercera fila**. El risc contrastable és la construcció dinàmica de SQL amb una llista d'IDs recuperats, el manteniment difícil d'aquesta condició i la dependència d'un segon `SELECT` amb `INNER JOIN` que pot descartar candidates. El FINAL pot utilitzar consulta parametrizada/joins sobre l'estat de les candidates sense una concatenació manual de predicats.

## 13. Disseny FINAL de l'operació de lot de la pantalla

Una confirmació té identificador de lot, actor, snapshot de files seleccionades i resultat per `ID_INSC`. La selecció es valida en el servidor contra `INSC CURS=1`, `PERENNE=0`, pertinença a edició/curs i permís; qualsevol excepció ha de quedar explícita, sense assumir que es pot forçar des del navegador. Es genera el CSV íntegre amb camp `course1` real i format vàlid, i es registra l'estat **FITXER PREPARAT / ERROR PARCIAL / ERROR** de cada fila; l'escriptura del fitxer i `PERENNE` han de ser recuperables de forma coherent. El modal mostra el recompte final i permet descarregar el fitxer només a qui està autoritzat. **Això és una adaptació de la pantalla existent, no una nova pantalla o importador de matrícula, ni un pas extern posterior.**

## 14. Impacte en BD, SIF, idempotència i auditories

**BD web actual:** `inscripcions.PERENNE` actualitzat, `INSC CURS` condició; dades de curs/aula/tutor llegides. **BD SIF final si s'adapta:** registrar identitat acadèmica real, lot/fitxer i resultat per fila en un historial d'exportació **diferenciat** d'`enrollment_import_run/item` quan aquest model té semàntica d'alta comercial; no establir una nova taula ni un nou estat fiscal només per haver generat CSV. **Idempotència:** una petició duplicada o un timeout ha de recuperar el resultat de la fila, no reescriure una segona vegada el CSV; conservar el significat de `PERENNE` llegat fins a la migració aprovada.

## 15. Impacte econòmic, fiscal i VERI*FACTU

Els camps `A_PAGAR`, `PAGAMENT`, `pendent` i `reclamat` són valors de visualització: **no són ordres de cobrar, liquidar, perdonar deute ni establir el receptor fiscal**. Preparar el fitxer i marcar `PERENNE` no crea factura, `CHARGE`, `REFUND`, saldo nou, rectificativa ni registre AEAT. Qualsevol actuació econòmica independent requereix el seu cas i evidència propis; no integrar-ne una per defecte en aquest botó.

## 16. Comunicacions i notificacions

L'única comunicació explícita en aquesta pàgina és **interfície**: modal de resultat per fila, alerta d'error, enllaç al fitxer i recàrrega en tancar-lo. No s'ha observat en aquests endpoints l'enviament de correus ni la creació d'una notificació d'accés, i no s'hi ha d'inventar. El FINAL pot registrar una incidència i mostrar-la al panell autoritzat, però això no certifica que existeixi una cua de correus o de campus per a aquest cas.

## 17. Contracte de seguretat i dades personals

Controlar al backend l'actor, el rol i el curs, protegir la ruta de descàrrega i no acceptar un `fitxer` arbitrari del POST com a ruta d'escriptura. Els camps personals han de sortir de la BD en la versió validada, no d'HTML mutable; normalitzar i escapar valors CSV per evitar trencar files/columnes i fórmules accidentals. No conservar indefinidament fitxers nominatius ni incloure'n el contingut en logs públics.

## 18. Proves d'acceptació de pàgina (proposades, NO EXECUTADES)

| ID | Pas/escenari | Acceptació FINAL |
| --- | --- | --- |
| AO-AT-01 | Obrir URL confirmada amb sessió vàlida / caducada | Càrrega de l'apartat segons rol / redirecció-denegació quan manca sessió. |
| AO-AT-02 | Sense inscripcions candidates | Botó no disponible; no hi ha CSV ni canvis a BD. |
| AO-AT-03 | Inscripció amb `INSC CURS=0` o `PERENNE=1` | No pot ser seleccionada ni actualitzada forçant POST; resposta tipificada. |
| AO-AT-04 | Tres candidates que compleixen `exPujadaAO` i les unions amb curs/aula/tutor | Llistat mostra les tres; el tractament de la llista d'IDs no perd candidates i no confon absència de JOIN amb `PERENNE` ja actualitzat. |
| AO-AT-05 | Una candidata sense dades relacionades de tutor | Resultat coherent i explícit per vista/consulta; no atribuir el buit al camp `PERENNE` si el JOIN no la retorna. |
| AO-AT-06 | Botó inicial «Qualifica» / JS carregat | Funció visual real «Pujar/No Pujar»; sense funció de qualificar el curs. |
| AO-AT-07 | Desmarcar totes les files i confirmar | Avís sense crear fitxer de capçalera ni modificar BD. |
| AO-AT-08 | Confirmar una sola fila amb permís vàlid | Només un ID_INSC actualitzat i una fila CSV amb `course1=$curs`, sense canviar `GRUP` ni `INSC CURS`. |
| AO-AT-09 | Mateix USUARI/CURS/ANY/MES amb inscripcions diferents | El POST no canvia una altra fila no seleccionada. |
| AO-AT-10 | Segon POST idèntic o timeout | Retornar resultat de fila/lot ja resolt, no duplicar CSV. |
| AO-AT-11 | Falla `fopen/fwrite` després d'UPDATE | Registrar l'error i recuperar coherència entre `PERENNE` i el fitxer. |
| AO-AT-12 | Fila sense UPDATE efectiu | No afegir-la al CSV com si hagués estat actualitzada. |
| AO-AT-13 | POSTs de 10 files en ordre aleatori | Mostrar modal/fitxer només després de totes les respostes i amb recompte exacte. |
| AO-AT-14 | Correu o població contenen `;` o salt de línia | Escapar CSV correctament, amb una fila per persona. |
| AO-AT-15 | Usuari sense rol invoca AJAX o URL de fitxer directament | Cap UPDATE, cap escriptura i cap descàrrega de dades personals. |
| AO-AT-16 | Inscripció amb pagament pendent, factura o certificat existent | Preparar el fitxer sense crear cap moviment bancari/document fiscal ni modificar el certificat; cap regla nova de bloqueig pressuposta. |
| AO-AT-17 | Tancar modal de resultat | Recarregar la pàgina i mostrar estat real actual sense files falsament pendents. |

## 19. Matriu de traçabilitat dels apartats i diagrames

| Apartat real | Acció/fonament | Diagrama ACTUAL i FINAL |
| --- | --- | --- |
| AO-P00 Pàgina completa | Sessió → `mostrarMain` → llista → marques → CSV → UPDATE → modal/fitxer | [Pàgina ACTUAL / FINAL](../07-uml-integrat/uc-moodle-aules-obertes-fitxa-activitats.md) |
| AO-A01 Entrada i estat buit | Pàgina, URL, `exPujadaAO` i botó inici | [A01](../07-uml-integrat/uc-moodle-aules-obertes-fitxa-activitats.md) |
| AO-A02 Llistat i dades | Consulta SQL, agrupació, camps informatius i marques | [A02](../07-uml-integrat/uc-moodle-aules-obertes-fitxa-activitats.md) |
| AO-A03 Marcar i desmarcar | JS `marcat/no_marcat` sense modificar BD | [A03](../07-uml-integrat/uc-moodle-aules-obertes-fitxa-activitats.md) |
| AO-A04 Confirmar i fitxer inicial | Permís client; crear CSV; cap fila | [A04](../07-uml-integrat/uc-moodle-aules-obertes-fitxa-activitats.md) |
| AO-A05 Actualització per fila | POST, UPDATE PERENNE, fwrite, resultats i error parcial | [A05](../07-uml-integrat/uc-moodle-aules-obertes-fitxa-activitats.md) |
| AO-A06 Resum i descarregar | Modal, enllaç CSV, recàrrega de pantalla | [A06](../07-uml-integrat/uc-moodle-aules-obertes-fitxa-activitats.md) |
| AO-A07 Errors, peticions repetides i concurrència | Alertes i divergència BD/CSV; política final de represa | [A07](../07-uml-integrat/uc-moodle-aules-obertes-fitxa-activitats.md) |

## 20. Decisions confirmades i assumptes que NO s'han de reobrir

**Confirmats per Meriem:** URL exacta de pujada d'aules obertes; cas diferent de pujada d'alumnes de l'inici de curs; el treball es dona per acabat a la **pàgina i el seu fitxer**, sense descriure cap operació posterior. **Confirmat al codi:** `PERENNE` és el camp escrit; `INSC CURS` és condició d'elegibilitat; les dades econòmiques i de certificat són informatives; CSV separador `;`, destinació `course1=$curs`. **No pendent de negoci:** trobar la URL, saber si hi ha aquesta pàgina, afegir un selector d'aula o un modal d'edició que el codi no mostra.

## 21. Estat de tancament i definició de «fet»

**DOC/ANÀLISI DE CODI:** fitxa de pàgina, accions, dades, rutes, SQL, regles observades, excepcions, riscos i [diagrames ACTUAL/FINAL de pàgina completa i tots els set apartats](../07-uml-integrat/uc-moodle-aules-obertes-fitxa-activitats.md) redactats en aquesta revisió; URL exacta identificada; **pas posterior exclòs per decisió de l'usuària**. **IMPLEMENTACIÓ FINAL:** no s'ha afirmat que les correccions proposades estiguin desplegades. **PROVES:** les AO-AT-01…17 NO estan executades ni s'ha inspeccionat producció. Això no deixa oberta la documentació del recorregut actual d'aquesta URL; delimita l'estat tècnic sense inventar resultats.

[Diagrames de pàgina i apartats d'aquest cas](../07-uml-integrat/uc-moodle-aules-obertes-fitxa-activitats.md) · [cas independent de pujada d'alumnes](../07-uml-integrat/uc-moodle-pujada-alumnes-fitxa-activitats.md) · [registre mestre de cobertura](../00-index-i-pla/42-registre-mestre-cobertura-funcional-implementacio-documentacio.md).
