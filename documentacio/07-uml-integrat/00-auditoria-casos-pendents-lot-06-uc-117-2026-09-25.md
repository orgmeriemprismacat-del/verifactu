# Lot 06 — UC-117 · cicle de vida d'un codi promocional o dret futur

**Tall de codi revisat:** `main` @ `e71958b3026549bde09fb4b25f2ec3ba370937ec` (referència documental del 22/09/2026); revisió de fonts el **25/09/2026**. **Branca documental:** `docs/registre-mestre-auditoria-2026-09-22`. **Abast:** UC-117, codis promocionals de la inscripció de curs normal, consulta i alta llegades i model SIF de drets. No es reauditen UC-111, UC-113 o UC-114; bescanvi de regal prepagat UC-018/119 i aplicació puntual de codi UC-020d tenen casos propis. **DOC revisada sobre les fonts identificades; IMP del gestor SIF no acreditada; TEST NO EXECUTAT; producció NO VERIFICADA.**

[Fitxa funcional UC-117](../06-fitxes-funcionals/uc-117.md) · [fitxa UML](uc-117-cicle-vida-codi-dret-futur.md) · [diagrames ACTUAL/FINAL](uc-117-activitats-codis-actual-final.md) · [registre mestre](../00-index-i-pla/42-registre-mestre-cobertura-funcional-implementacio-documentacio.md).

## 1. Mapatge de superfícies, dades i efectes ACTUALS

| Pas / component | Evidència observada directament al codi versionat | Limitació i diferència respecte del SIF final |
| --- | --- | --- |
| Formulari curs normal | [`InscripcioCurs.php` L558–608](../../codi-drive/web-actual/InscripcioCurs.php#L558-L608) mostra «Dades del curs», preu i `#promo` als cursos no subvencionats; [`pagina_inscripcions.php`](../../codi-drive/web-actual/pagina_inscripcions.php) i [`ajax/mostrar_inscripcio.php`](../../codi-drive/web-actual/ajax/mostrar_inscripcio.php) preparen la pàgina; JS [`mostrarInscripcions.min.js`](../../codi-drive/web-actual/js1619773569/mostrarInscripcions.min.js). | El camp `#promo` aplica un descompte **en el formulari**, no demostra emissió ni reserva del dret al SIF. |
| Consulta d'un codi personal | [`ajax/obtenirDadesPromo.php` L10–55](../../codi-drive/web-actual/ajax/obtenirDadesPromo.php#L10-L55) rep GET `promo,codi` i consulta `promocions` (`CODI_DESCOMPTE`, `DNI`, `MES`, `CURS`, `PERCENTATGE`, `USED`, `DATAI/DATAF`, `TIPUS_CALC`, `PREU`, `PREU_FIX`, `ACUM`). Comprova activitat temporal i retorna la fila en cadena delimitada per `|`, **incloent DNI i valors comercials**. | L'endpoint no acredita identitat/titularitat de la persona que consulta i no emmascara `DNI` en la resposta; no tornar dades personals per conèixer el codi. La consulta no reserva ni consumeix res. |
| Validació al navegador | [JS L1033–1207](../../codi-drive/web-actual/js1619773569/mostrarInscripcions.min.js#L1033-L1207) crida el GET anterior, divideix la resposta, comprova existència, estat de dates, `USED`, titular-DNI introduït, mes, curs o hores; [L1208–1261](../../codi-drive/web-actual/js1619773569/mostrarInscripcions.min.js#L1208-L1261) calcula descompte percentual, import fix o preu fix amb `parseFloat` i prepara `promocioAplicada`. | Les comprovacions són **client-side** a partir de dades retornades al navegador; no acreditar autorització/compatibilitat ni regla de preu final al backend. `ACUM` es rep però en el tram d'aplicació inspeccionat no intervé en la fórmula; no afirmar acumulabilitat correcta. |
| Alta de curs | [JS L2232–2258](../../codi-drive/web-actual/js1619773569/mostrarInscripcions.min.js#L2232-L2258) tramet `preuDescompte` i `promocioAplicada` al GET; [`enviarInscripcio.php` L75–85](../../codi-drive/web-actual/ajax/enviarInscripcio.php#L75-L85) els rep i [L575–609](../../codi-drive/web-actual/ajax/enviarInscripcio.php#L575-L609) prepara text amb codi i desa inscripció/`A_PAGAR`. | En els fragments revisats l'import de l'alta deriva de valors rebuts al GET: no s'ha localitzat una revalidació de promoció, titular, dates, ús o tarifa en aquest punt. No exposar codi personal complet a justificants, PDF o correu indiscriminadament. |
| Marca d'ús | [`enviarInscripcio.php` L670–681](../../codi-drive/web-actual/ajax/enviarInscripcio.php#L670-L681) executa `UPDATE promocions SET USED=1 WHERE CODI_DESCOMPTE=?` **després d'inserir l'alta** si `promocioAplicada` no és buida. | Aquest UPDATE no conté en el WHERE ni `USED=0`, ni DNI, dates, producte, edició ni reserva/ID_INSC. Sense guardar resultat de files afectades com a criteri d'acceptació visible al fragment. Marca el codi utilitzat durant **l'alta**, no després de confirmar cobrament. No afirmar recuperació automàtica quan el TPV falla. |
| Codi temporal de trobades | [`ajax/obtenirCodisPromo.php`](../../codi-drive/web-actual/ajax/obtenirCodisPromo.php) consulta `trobades`, calcula vigència respecte de `DATA_INICI` i dies, i retorna entrades `codi|percentatge|edició`. [JS L929–1027](../../codi-drive/web-actual/js1619773569/mostrarInscripcions.min.js#L929-L1027) carrega aquestes entrades, valida codi/edició al client i calcula percentatge. | És un canal diferent de `promocions`; la cadena concatenada pel PHP usa `$` i el JS divideix per coma: discrepància visible per a diverses entrades, **cal provar-la**, no extrapolar que falla sempre. L'UPDATE de `promocions.USED` no acredita consum d'aquests codis de trobades. |
| SQL SIF | [migració 000005, L96–153](../../sif/database/migrations/2026_09_16_000005_add_operation_lifecycle_tables.sql#L96-L153) **defineix** `commercial_entitlement` i `commercial_entitlement_event`: tipus, `CODE_HASH`, titular, origen, valor/percentatge, estat, dates, operació de consum i events. | DDL definit ≠ migració aplicada ni servei PHP del cicle de drets. Un únic `CONSUMED_UUID_OPERATION` no documenta múltiples usos d'un codi públic; `RESERVED_AT` sol no identifica operació propietària ni venciment de reserva. No hi ha en les fonts analitzades un writer del nou ledger. |

## 2. Incidències / riscos a gestionar (no explotats ni provats)

| ID | Evidència concreta | Tasca FINAL separada |
| --- | --- | --- |
| UC117-P0-01 · dades personals | `obtenirDadesPromo.php` retorna `DNI` de la fila de promoció a la petició GET; el JS el desa a `dadesPromo` i té `console.log(dadesPromo)`. La resposta pot revelar titular associat a qui coneix el codi. | Minimitzar resposta pública (booleà/resultat tipificat i import sense DNI), verificar titular/rol a servidor, eliminar log de payload personal, auditar accessos i revisar controls d'HTTP/cache/log. És una troballa **al codi versionat**, no un atac o fuita de producció demostrats. |
| UC117-P0-02 · ús/preu | `enviarInscripcio.php` rep `promocioAplicada` i `preuDescompte` del navegador, insereix i marca `USED=1` amb WHERE només de codi. | Revalidar regla/titular/preu/estat en el servidor, reservar codi d'ús únic transaccionalment i associar-lo a una operació/ID_INSC; definir alliberament davant alta/cobrament fallits, consum únic després de fita econòmica acordada, idempotència i locks. |
| UC117-P1-03 · dades de campanya | PHP de `trobades` concatena amb `$`; JS separa per `,`; el JS no fa validació server-side de codi/tarifa en l'alta inspeccionada. | Test de campanyes múltiples i unificar format JSON/serialització amb validació al backend. |
| UC117-P1-04 · model de reserva | DDL `commercial_entitlement` té `RESERVED_AT` però no `RESERVED_UUID_OPERATION` o `RESERVATION_EXPIRES_AT`; `CODE_HASH UNIQUE` i un camp de consum representen un dret singular. | Dissenyar vincle de reserva, TTL, alliberament, comptador/sublínies per a multiús i límit de disponibilitat; no assumir que el DDL actual resol els casos. |
| UC117-P1-05 · conceptes econòmics | La fitxa anterior barreja codi temporal, dret futur promocional i regal prepagat en la mateixa descripció genèrica. | Tipificar dret `PROMOTION_CODE` / promoció pública / dret futur; distingir regal/crèdit monetari de descompte, sense inventar ingrés nou en emetre un codi. |

## 3. Matriu de variants sense inferir regles de negoci universals

| Variant | ACTUAL acreditat | FINAL i frontera |
| --- | --- | --- |
| Codi personal `promocions` | Consulta per codi+producte, data, `USED`, DNI opcional, curs/hores/mes i tipus de càlcul; `USED=1` després d'alta. | Reserva/consum per regla i operació; titular verificat al servidor; data de consum coherent amb efecte real del dret. |
| Codi temporal `trobades` | Consulta per curs/data i percentatge/edició; càlcul i prova de text al navegador. | No convertir-lo per defecte en codi personal d'ús únic; disponibilitat/sostre de campanya segons regla pròpia. |
| Codi futur originat per una altra operació | El catàleg contempla emissió posterior condicionada, però no queda acreditat un únic servei implementat en aquest lot. | Guardar `ORIGIN_UUID_OPERATION`, regla, titular, moment d'activació i valor de dret; fronteres amb UC d'origen (no es reaudita UC-111). |
| Regal/codi prepagat | Té formularis, pagament i bescanvi diferenciats al repo, no revisats dins aquest lot. | UC-018/119: diner prepagat/reassignació de valor, no `DISCOUNT_AMOUNT` inventat. |
| Codi públic multiús | El model SQL singular no comptabilitza per si sol tots els usos del mateix codi. | Comptador/assignació per ús amb event i claus úniques, o drets separats per beneficiari. |
| Cancel·lació/canvi de curs | La fitxa UML preexistent documenta `updDataFPromocio` i tancament de vigència al llegat; no se n'ha recuperat directament el cos en aquesta revisió. | Event de cancel·lació/reversió amb motiu i operació; política de reobertura/no retorn de dret per decidir segons categoria. |

## 4. Pantalles, apartats i límits RM-037

[P01: inscripció de curs, dades del curs → promocions](uc-117-activitats-codis-actual-final.md#p01--formulari-dinscripcio-curs-normal--apartat-de-codi-promocional): camp `#promo`, consulta i errors, import recalculat; [P02: confirmació de l'alta i aplicació](uc-117-activitats-codis-actual-final.md#p02--confirmar-inscripcio-i-marcar-codi): validació client, GET d'alta, INSERT i UPDATE d'ús; [P03: codi temporal de trobades](uc-117-activitats-codis-actual-final.md#p03--descompte-temporal-de-trobades): consulta/codi/edició, frontera amb codi personal; [P04: gestió del dret en el nou SIF](uc-117-activitats-codis-actual-final.md#p04--cicle-de-vida-del-dret-sif-disseny-sense-pagina-actual-acreditada): **només FINAL**, no existeix una pàgina actual de gestió SIF acreditada; el diagrama ACTUAL documenta l'absència de superfície comprovada, no inventa botons. Els altres formularis comercials (packs, regal i grup) s'han de vincular als UC propis abans de presentar-los com a pàgines completes de UC117.

## 5. Proves proposades — NO EXECUTADES

| ID | Escenari i resultat exigible al FINAL |
| --- | --- |
| UC117-T01 | Codi personal vàlid i mateixa persona/producte/edició; backend retorna preu legítim i snapshot de regla sense retornar DNI aliè. |
| UC117-T02 | Codi incorrecte/usat/caducat/diferent titular/curs/hores/mes; cap preu descomptat ni dada personal del titular al navegador. |
| UC117-T03 | Dues altes o dos checkouts simultanis amb un sol codi d'un ús: una sola reserva/consum i resultat idempotent per retry equivalent. |
| UC117-T04 | Alta creada i cobrament denegat, caducat o callback duplicat: definir/reproduir alliberament o recuperació segons regla, sense marcar consum definitivament de manera indeguda. |
| UC117-T05 | Percentatge, import fix i preu fix; càlcul decimal server-side, compatible amb producte i sense import negatiu/arbitrari. |
| UC117-T06 | Dues promocions temporals simultànies: càrrega i separador coherent, edició correcta, cap reutilització indeguda de `promocions.USED`. |
| UC117-T07 | Codi multiús públic i personal d'ús únic amb el mateix string de campanya: consum/quotes per regla, sense falsa `CONSUMED_UUID_OPERATION` global. |
| UC117-T08 | Prova que codi de dret futur no crea ingrés ni factura en emissió; un regal prepagat enllaça diner extern real en UC propi. |
| UC117-T09 | Canvi/anul·lació després de consum, amb i sense factura emesa: event nou i decisió de reversió, mai UPDATE de factura original. |
| UC117-T10 | Rol/titular aliè interroga codi i prova de cache/log: resposta minimitzada, backend protegeix regla i titular, errors no filtren DNI ni codi complet. |
| UC117-T11 | Dret encara no activat, expirat o reserva que venç: cap consum prematur, alliberament o denegació segons política explícita. |
| UC117-T12 | Migració de fila llegada `USED=1` i de codi temporal públic: estat/usos conservats, no es reemet un dret usat. |

**Resultat de proves en aquesta auditoria: CAP.** No s'ha executat PHP, MySQL, Selenium, TPV, SMTP, renderitzador PlantUML ni comprovació d'entorn productiu.

## 6. Estat i tasques diferenciades

**DOC-117-01:** fitxa funcional individual i matriu de variants/pàgina actuals i finals; **DOC-117-02:** UML cas/classes/seqüència amb tipus de dret i events; **DOC-117-03:** decisions específiques de validesa, consum i reversió marcades com a pendents quan les fonts no les fixen; **IMP-117-01:** validació de titular/preu al servidor i minimització de dades; **IMP-117-02:** reserva/concurrència/idempotència; **IMP-117-03:** ledger, TTL i model multiús; **IMP-117-04:** comunicacions, migració de codis i conciliació fiscal/econòmica; **TEST-117:** T01–T12 i evidència de despliegament.

**Tancament diferenciat:** es pot acabar la DOCUMENTACIÓ dels fluxos auditats sense esperar a implementar aquests serveis. No donar la UC-117 per «implementada» ni «testada» basant-se en SQL i UML. Les polítiques comercials generals (transferibilitat, acumulació entre famílies, expiració de cada tipus, alliberament després de pagament fallit) es mantenen com a decisions a contrastar per variant, no s'inventen.
