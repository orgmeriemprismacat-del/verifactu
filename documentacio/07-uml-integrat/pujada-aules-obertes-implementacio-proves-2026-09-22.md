# Pujada d'aules obertes — implementació candidata i verificació de proves

**Data:** 22/09/2026. **Branca de CODI:** `fix/pujada-aules-obertes-lot-2026-09-22` (no fusionada a `main`). **URL de negoci:** https://intranet.prisma.cat/cursos/fi-cursos/pujar-aules-obertes/ . **Abast exclusiu:** aquesta pàgina, els seus permisos, inscripcions candidates, selecció, canvi de `PERENNE`, preparació i descàrrega del CSV i errors. **No inclou cap càrrega o confirmació posterior al campus**, expressament exclosa per Meriem.

## 1. Què s'ha implementat en la branca

| Fitxer | Canvi candidat verificable |
| --- | --- |
| [Pantalla AO](../../codi-drive/intranet-actual/cursos-fi-cursos-pujar-aules-obertes.php) | Crea token CSRF per sessió i el posa en meta; incrementa la versió de JS per evitar que el navegador conservi el flux antic. |
| [Intranet.php, renderitzat AO](../../codi-drive/intranet-actual/Intranet.php#L3337-L3409) | Afegeix `i.ID AS ID_INSC` al SELECT/bind_result, i `data-inscripcio-id` a cada botó de selecció. No es modifica la consulta d'inici de curs ni la de pujada de grups. |
| [JS d'aules obertes](../../codi-drive/intranet-actual/js/cursos-fi-cursos-pujar-aules-obertes.js) | No crea el fitxer si no hi ha cap ID seleccionat; envia UN sol POST amb tots els ID_INSC, clau de lot i CSRF; espera resposta del lot complet, mostra el modal amb recompte i enllaç de descàrrega autoritzada; conserva la clau quan es reintenta la mateixa selecció; manté la recàrrega en tancar el modal. |
| [processarLotAO.php](../../codi-drive/intranet-actual/ajax/inici/processarLotAO.php) | Reutilitza `inc/comprovarSessio.php` i comprova al servidor `ROLS_VISUALITZAR` i `ROLS_EDITAR` de la URL exacta, valida CSRF i llista d'IDs; comprova estat `INSC CURS=1/PERENNE=0` i curs/aula/tutor, bloqueja les files en transacció, escriu CSV **privat** abans d'actualitzar `PERENNE` per `ID`; comprova `affected_rows=1` i retorna token/recompte només després del commit. Davant error normal d'execució, rollback i eliminació del CSV provisional. |
| [AOBatchCsv.php](../../codi-drive/intranet-actual/inc/AOBatchCsv.php) | Fila CSV amb `fputcsv`, separador `;` i codificació ISO-8859-1 llegat; rebutja salt de línia, fórmula inicial i caràcter incompatible en lloc de truncar o donar per bona una fila corrupta. |
| [descarregarFitxerAO.php](../../codi-drive/intranet-actual/ajax/inici/descarregarFitxerAO.php) | CSV desat al directori privat de temporal del PHP, fora de `/fitxers/`; per descarregar-lo comprova sessió vàlida, actor, rols vigents, token aleatori i caducitat de 24 hores. |
| [crearFitxerAO.php](../../codi-drive/intranet-actual/ajax/inici/crearFitxerAO.php), [pujarAulesObertes.php](../../codi-drive/intranet-actual/ajax/inici/pujarAulesObertes.php) | Retornen HTTP 410; eviten usar de manera directa el flux antic per fila que actualitzava BD abans del CSV i que no exigia el permís per aquesta acció. Els canvis de pantalla, JS i endpoints antics s'han de desplegar junts, després de provar-los. |
| [Test unitari CSV](../../codi-drive/intranet-actual/tests/ao_csv_test.php) i [script de validació](../../codi-drive/intranet-actual/tests/run_ao_checks.sh) | 9 casos de CSV (accents, separadors, cometes, fórmula, salt de línia i caràcter incompatible); ordres de lint PHP/JS i de prova CLI. |

**Separació econòmica:** cap dels endpoints nous crea factura, càrrec, pagament, saldo, rectificativa ni registre AEAT; `A_PAGAR` i `PAGAMENT` continuen només visibles al llistat.

## 2. Estat de les proves, sense confondre codi amb productiu

**Verificació executada en aquest tall:** comprovació sintàctica de l'actual fitxer `AOBatchCsv.php` i de la prova `ao_csv_test.php` contra còpies els SHA de blob de les quals coincideixen amb GitHub; comprovació de sintaxi del JS complet llegit directament de la branca; nou recurs de comprovació amb 9 casos lògics que passa en un entorn local amb funcions de conversió emulades via `iconv`. **Limitació:** la CLI disponible per aquesta revisió NO té l'extensió PHP `mbstring`; executar el test del repositori sense emulació retorna **SKIP (77)**. Això **NO** acredita que els 9 casos hagin passat amb el `mbstring` real.

**No executat en aquest tall:** `php -l` del nou endpoint de lot i de l'endpoint de descàrrega contra bytes obtinguts de la branca, l'execució integral de `tests/run_ao_checks.sh`, cap test contra la BD de la intranet, cap sessió o rol real, cap prova a producció ni els 17 escenaris d'acceptació de la fitxa. **No afirmar 17/17, desplegament, ni `FINAL IMPLEMENTAT EN PRODUCCIÓ`.**

**Comprovacions reproduïbles per integrar el canvi:** des de la carpeta `codi-drive/intranet-actual`, executar `sh tests/run_ao_checks.sh` en un entorn amb PHP 8.x, `mbstring`, `mysqli` i Node.js; executar amb BD i sessió de proves les 17 situacions de la [fitxa funcional d'aules obertes](../06-fitxes-funcionals/uc-moodle-pujada-aules-obertes.md#18-proves-dacceptacio-de-pagina-proposades-no-executades). Verificar també que el directori retornat per `sys_get_temp_dir()` és writable, persistent per la finestra de descàrrega i queda realment fora del document root.

## 3. Matriu d'acceptació — què és auditable i què necessita entorn real

| Proves de la fitxa | Estat del codi candidat | Validació pendent |
| --- | --- | --- |
| AO-AT-01–03 | Sessió/rol via base existent, selecció no buida i estat de fila revalidat. | Sessions i rols reals; peticions directes i caducades. |
| AO-AT-04–06 | La consulta de vista llegat continua i el PHP envia ID_INSC; el JS canvia «Qualifica» a «Pujar». | Consulta amb 3+ candidates, dades de tutor absents, UI representada real. |
| AO-AT-07–09 | Un POST per lot, actualització per ID_INSC i CSV sense canviar grup. | SQL real, dues files de mateixa persona i consulta després de confirmar. |
| AO-AT-10 | La mateixa clau i selecció poden recuperar el resultat desat a la mateixa sessió; una selecció diferent amb igual clau dona conflicte. | Reintents després de timeout, caducitat de sessió i caiguda de PHP al voltant del commit. |
| AO-AT-11–13 | Generació de fitxer abans d'UPDATE, transacció SQL, `affected_rows=1` i resposta única del lot; es reverteix una fallada ordinària abans de commit. | Fallada forçada de disc/BD, transacció real i dos operadors concurrents. |
| AO-AT-14 | `fputcsv` suporta `;` i cometes; **els salts de línia i caràcters fora d'ISO-8859-1 són rebutjats** (no es tracten silenciosament com a èxit). | Aprovar aquest contracte de rebuig per a dades multilinia, i executar prova amb el `mbstring` real. |
| AO-AT-15–17 | Descàrrega amb sessió/rol/token; cap escriptura fiscal en nous endpoints; recàrrega després del modal. | Denegacions reals a servidor, fitxer privat, comptabilitat i UI amb dades anonimitzades. |

## 4. Límits d'implementació i pas segur abans de desplegar

**La clau d'idempotència del lot es conserva a la sessió, no en un registre persistent independent**: cobreix repeticions normals del mateix POST quan la resposta/receipt s'ha registrat, però **no garanteix recuperació automàtica** d'una mort del procés exactament després del commit de BD i abans de persistir el resultat de sessió. Per declarar l'operació totalment recuperable davant de caigudes del servidor, cal un registre durable per lot/fila (BD) o reconciliació de l'arxiu privat i de l'estat `PERENNE`, més una prova de caiguda real. No disfressar aquest punt com a resolt.

**Altres restriccions reals:** l'accés de descàrrega caduca al cap de 24 hores, però encara no hi ha un treball programat que esborri sistemàticament els CSV antics del directori temporal; definir-ne retenció efectiva abans de desplegar. La creació per `ID_INSC` està lligada al JOIN de tutor de la vista actual i pot rebutjar una fila que la consulta inicial detecta però no pot mostrar. La política de caràcters CSV incompatibles és rebuig explícit, no conversió amb pèrdua. Cal validar les regles reals `ROLS_EDITAR` de la URL a BD de proves i que `inc/comprovarSessio.php` pot ser inclòs des de l'endpoint nou amb l'arbre desplegat.

**Desplegament:** únicament després de revisar i executar les proves en una còpia no productiva. Cal desplegar **conjuntament** nova pàgina/versió JS, canvi mínim d'`Intranet.php`, endpoint únic, controlador de descàrrega, helper de CSV i substitució dels endpoints antics. La pàgina existent **no** ha de rebre peticions antigues mentre els endpoints de fila retornen HTTP 410. Reversió: restaurar conjuntament el JS/pàgina/endpoint antics i el renderitzat, evitant deixar-los barrejat; **la reversió no pot reescriure silenciosament `PERENNE` d'un lot ja processat**.

**Resum d'estat:** codi FINAL **candidat en branca**, documentació del comportament ACTUAL/FINAL completa, proves locals limitades, integració i recuperació davant de caiguda encara **NO ACREDITADES**. No hi ha cap qüestió oberta per a Meriem sobre la URL ni sobre la càrrega posterior al campus.
