# Auditoria de frontera UC-113 · lot 04 · URL real de l'importador de matrícules en lot

**Data:** 22/09/2026. **URL aportada per Meriem:** https://intranet.prisma.cat/cursos/inici-cursos/generar-fitxer-pujada-alumnes/ . **Identificació CORREGIDA:** aquest és l'apartat de la intranet que Meriem anomena «importador de matrícules en lot», no una altra pàgina amb executable desconegut. La [pàgina PHP versionada](../../codi-drive/intranet-actual/cursos-inici-cursos-pujar-alumnes.php) té el títol «Generar fitxer pujada alumnes», incorpora el JS de la pàgina i carrega contingut via `ajax/mostrarMain.php`. Aquest endpoint resol la URL mitjançant el camp `apartats.URL` de la BD intranet i invoca `Intranet::mostrarPage`. **Correspon a un lot de matrícules EXISTENTS que prepara CSV de destinació Moodle; el codi d'aquesta URL no executa INSERT de noves altes a PrisMa.**

**Font d'auditoria estàtica:** `main` a `e71958b3026549bde09fb4b25f2ec3ba370937ec`. La ruta de negoci és una declaració directa de Meriem i el PHP/JS concorden amb el nom de pantalla. La configuració `rewrite` productiva i el resultat d'importació Moodle posterior **no han estat observats**; això **no** significa que la pàgina/importador no existeixi. **Estat de documentació de la pàgina:** recorregut principal, apartats i errors de codi analitzats; [fitxa pròpia amb dos diagrames de PÀGINA COMPLETA i dotze d'apartats ACTUAL/FINAL](uc-moodle-pujada-alumnes-fitxa-activitats.md). **Proves i desplegament:** no verificats.

## 1. Correcció d'interpretació i d'abast funcional

**La premissa anterior «existeix un importador de matrícules en lot a la intranet però no en coneixem la pantalla» era errònia.** Meriem n'ha proporcionat l'URL exacta i el codi de pantalla, els endpoints, la consulta SQL i la generació del CSV estan localitzats. L'acció és la pujada **en lot d'alumnes existents** al campus, i per acord de negoci s'ha de documentar com a cas **independent** de l'alta manual inicial, i la pujada d'aules obertes és un altre cas independent. UC-129 comprova la correspondència posterior PrisMa↔Moodle.

**No s'afegeix per suposició un quart procés «importar un fitxer a PrisMa que crea altes noves» només perquè el nom original de UC-113 deia «importar ... en lot» o perquè el model SQL `enrollment_import_run/item` és al repositori.** Aquestes taules són disseny SIF, no codi de la URL indicada. La [fitxa funcional d'UC-113](../06-fitxes-funcionals/uc-113.md) conserva l'alta web ordinària/grup i la via excepcional de secretaria; «Mostrar la informació de l'alumne > Canvi de curs» de la intranet es deriva a UC-026.

## 2. Inventari complet del recorregut de la URL

| Secció / acció | Codi actual verificat | Resultat actual i límit |
| --- | --- | --- |
| Entrada i càrrega de pàgina | [`cursos-inici-cursos-pujar-alumnes.php`](../../codi-drive/intranet-actual/cursos-inici-cursos-pujar-alumnes.php) inclou `comprovarSessio.php`; [JS L1–37](../../codi-drive/intranet-actual/js/cursos-inici-cursos-pujar-alumnes.js#L1-L37) demana [`ajax/mostrarMain.php`](../../codi-drive/intranet-actual/ajax/mostrarMain.php) amb `window.location.pathname`. | Vista dinàmica amb `apartats.URL`, rol de visualització a `mostrarMain.php`; no confondre autorització de vista amb autorització del POST de cada fila. |
| Seleccionar edicions candidates | [`__mostrarPage_Inici_Pujada_Inscripcions`](../../codi-drive/intranet-actual/Intranet.php#L3571-L3613) i [`__mostrarPage_Cursos_Pujada_Inscripcions`](../../codi-drive/intranet-actual/Intranet.php#L3619-L3832) llegeixen `params` amb `oberturaAules` i `IniciPujadaInsc_vella`. | SQL `INSC CURS=0`, dates del curs segons paràmetre i exclusions llegades de cursos; si no hi ha candidates, mostra «No hi ha resultats». |
| Mostrar informació, avisos i aula | [`Intranet.php L3654–3800`](../../codi-drive/intranet-actual/Intranet.php#L3654-L3800) presenta grup/edició, dades personals, comentaris, comptadors d'aula i `REALITZAT`/`DUPLICADA`/`DEUTOR!`. | Marca inicial `No Pujar` quan es detecta deute; altres avisos són visibles però el botó de pujar continua marcat per defecte si no hi ha deute. No elevar una etiqueta a política universal de bloqueig. |
| Marcar persones i escollir aula | [JS L38–82](../../codi-drive/intranet-actual/js/cursos-inici-cursos-pujar-alumnes.js#L38-L82). | Canvis al navegador fins a confirmar; l'ID HTML de la fila es forma amb idCurs+usuari. La lògica servidor de l'UPDATE **no selecciona per `ID_INSC`**. |
| Consultar/editar dades personals | [JS L195–389](../../codi-drive/intranet-actual/js/cursos-inici-cursos-pujar-alumnes.js#L195-L389), [`Intranet.php L4076–4097`](../../codi-drive/intranet-actual/Intranet.php#L4076-L4097), `ajax/inici/mostrarModalEditaInscripcio.php`, `ajax/inici/actualitzaDadesPersonals.php`. | Correcció d'identitat/contacte de la inscripció per ID; no equival a correcció retroactiva de la factura ni de la identitat Moodle. El JS del guardat usa GET en el codi revisat; valorar POST i controls de servidor al FINAL. |
| Confirmar el lot i crear CSV | [JS L85–105](../../codi-drive/intranet-actual/js/cursos-inici-cursos-pujar-alumnes.js#L85-L105) i [`ajax/inici/crearFitxerPujadaInscripcions.php`](../../codi-drive/intranet-actual/ajax/inici/crearFitxerPujadaInscripcions.php) → [`Intranet::crearFitxerPujadaInscripcions`](../../codi-drive/intranet-actual/Intranet.php#L4124-L4149). | Fitxer `pujada-inscripcions-<data-hora>.csv` a `fitxers/`; capçalera `username;password;firstname;lastname;email;city;lang;course1;autosubscribe;maildisplay`. El fitxer es crea **abans** de comprovar si hi ha alguna fila marcada. |
| Enviar cada fila i canviar l'estat web | [JS L106–168](../../codi-drive/intranet-actual/js/cursos-inici-cursos-pujar-alumnes.js#L106-L168), [`ajax/inici/pujarInscripcions.php`](../../codi-drive/intranet-actual/ajax/inici/pujarInscripcions.php), [`Intranet::pujar_Inscripcions`](../../codi-drive/intranet-actual/Intranet.php#L4158-L4201). | Per `USUARI,CURS,ANY,MES,INSC CURS=0`, executar UPDATE `INSC CURS=1, GRUP=aula`, després `fwrite` de la fila CSV. Això **no crea una nova inscripció PrisMa** ni confirma matrícula Moodle. |
| Resum, fitxer i tancament | [JS L149–194](../../codi-drive/intranet-actual/js/cursos-inici-cursos-pujar-alumnes.js#L149-L194). | Modal per fila, enllaç `/fitxers/<fitxer>` després de resposta de l'última posició del bucle, no necessàriament de totes; tancar modal recarrega pantalla. El CSV incorpora dades personals i la protecció real del directori no s'ha verificat. |
| Acció acadèmica de destí | **No hi ha POST d'importació Moodle ni resposta de matrícula Moodle en el PHP/JS d'aquesta URL.** | La càrrega real del CSV al campus, manual o per altre procés, és posterior i requereix evidència independent UC-129; no presentar `INSC CURS=1` com a accés real confirmat. |

## 3. Defectes de seqüència i traçabilitat identificats

**1. Confirmació sense selecció.** L'endpoint de creació de fitxer s'invoca abans de comprovar cap botó marcat. Així, un «No has marcat cap canvi» pot arribar després d'haver creat un CSV només amb capçalera.

**2. Desajust entre BD i fitxer.** `pujar_Inscripcions()` actualitza primer `inscripcions` i després obre i escriu el CSV. Si `fopen`/`fwrite` falla, `INSC CURS=1` pot quedar enregistrat sense una fila de fitxer vàlida; no s'ha identificat compensació atòmica en aquest tram.

**3. POST per fila sense barrera.** El JavaScript llança peticions individuals i publica l'enllaç quan acaba l'element d'última posició del bucle, no quan consten totes les respostes. Pot oferir el CSV abans que s'hi hagin afegit altres files.

**4. Abast de l'UPDATE.** El `WHERE` de l'actualització conté `USUARI,CURS,ANY,MES,INSC CURS`, però no `ID_INSC`; el cas final ha de provar l'aïllament de cada inscripció real, especialment davant més d'una fila equivalent o canvi d'aula.

**5. Autorització i dades personals.** El JS mostra/oculta accions en funció de `tePermisEdicio`; els dos endpoints AJAX revisats recuperen l'objecte de sessió i invoquen mètodes sense una comprovació d'actor/edició explícita en aquest recorregut. Cal comprovar l'entorn i l'autorització global abans de concloure manca de protecció productiva. El fitxer descarregable conté dades personals: protegir-lo a servidor, retenció i permisos.

**6. Fets que no s'han de confondre.** Alta real a PrisMa, fila seleccionada per exportar, CSV complet, matrícula Moodle verificada, cobrament real i factura emesa són fets diferents, encara que la secretaria utilitzi la paraula «importar» per al lot.

## 4. Documentació funcional i diagrames d'activitat definitius d'aquesta revisió

La fitxa que cobreix **aquesta URL i el seu procés de lot** és [«Pujada d'alumnes»](uc-moodle-pujada-alumnes-fitxa-activitats.md): inclou **dos diagrames ACTUAL/FINAL de la PÀGINA COMPLETA** i **dotze diagrames de sis apartats**, la matriu de fonts i els criteris de prova. La [fitxa UC-113](../06-fitxes-funcionals/uc-113.md) conserva els **21 apartats funcionals** de l'alta web i diferencia aquesta operació adjacent; [diagrames i UML UC-113](uc-113-activitats-alta-manual-i-importador-lot.md) i [cas d'ús/class/seqüència actual](uc-113-importar-inscripcions-manualment-lot.md) també estan rectificats. [Pujada d'aules obertes](uc-moodle-aules-obertes-fitxa-activitats.md) és un **cas independent** amb la seva pantalla i diagrames. La numeració definitiva dels casos candidats de Moodle està pendent de comprovació amb el catàleg, però **no** la seva localització funcional.

## 5. Proves d'acceptació que NO s'han executat

| ID | Escenari | Resultat final esperat |
| --- | --- | --- |
| UC-LOT-01 | Obre la URL amb sessió i permisos diferents | Vista i accions segons rol; denegació real al backend quan falta permís. |
| UC-LOT-02 | No hi ha candidats | Mostrar estat buit i no modificar inscripcions. |
| UC-LOT-03 | Confirmar zero persones marcades | No crear cap CSV ni UPDATE i mostrar avís. |
| UC-LOT-04 | Seleccionar aula A/B i un sol ID_INSC | Només canviar la inscripció elegida, respectar destí de curs/aula. |
| UC-LOT-05 | Alumne REALITZAT, DUPLICADA o DEUTOR | Mostrar motiu, aplicar política acadèmica autoritzada, no deduir la regla només d'una etiqueta. |
| UC-LOT-06 | Falla el CSV després d'UPDATE | Distingir estat preparat i acadèmic, garantir recuperació sense fila perduda. |
| UC-LOT-07 | Respostes AJAX de deu files fora d'ordre | No oferir «lot complet» fins a rebre tots els resultats i validar el fitxer. |
| UC-LOT-08 | L'usuari repeteix confirmació després de timeout | Recuperar lot i fila, cap doble exportació/matrícula ni cobrament inferit. |
| UC-LOT-09 | CSV conté accent, punt i coma o salt de línia | Format/escaping/codificació compatible amb campus, una fila per inscripció. |
| UC-LOT-10 | CSV generat i Moodle en rebutja una fila | No presentar accés com a confirmat; incidència per fila i conciliació UC-129. |
| UC-LOT-11 | Descarregar URL fitxer sense autorització | No exposar dades personals ni permetre reutilització d'enllaç públic. |
| UC-LOT-12 | Inscripció amb factura emesa | Canvi acadèmic no altera document fiscal, receptor, cobrament ni cua AEAT. |

## 6. Estat de l'auditoria — sense falsos pendents

**RESOLT DOCUMENTALMENT:** nom i URL exactes de l'importador en lot, pàgina PHP, càrrega dinàmica, consultes, dades visibles, modal, marques, selecció d'aula, endpoints, SQL UPDATE, generació del CSV i sortida; separació dels dos casos d'ús de pujada al campus i de l'alta inicial a PrisMa.

**PENDENT DE VERIFICACIÓ TÈCNICA, no de recordar la URL:** configuració efectiva de servidor/rol i directori de fitxers; càrrega REAL del CSV a Moodle si ocorre fora d'aquesta pantalla; proves de concurrència, error parcial, reintent i integració SIF; desplegament productiu. **No dir «el codi de l'importador no està localitzat» ni obrir una tasca de desenvolupar un parser d'altes a PrisMa a partir d'aquesta URL.**

[Fitxa funcional UC-113](../06-fitxes-funcionals/uc-113.md) · [fitxa + activitats de la URL de lot](uc-moodle-pujada-alumnes-fitxa-activitats.md) · [fitxa aules obertes](uc-moodle-aules-obertes-fitxa-activitats.md) · [registre mestre](../00-index-i-pla/42-registre-mestre-cobertura-funcional-implementacio-documentacio.md).
