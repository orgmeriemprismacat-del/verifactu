# Lot 14 — UC-125 · consentiment / preferències de comunicacions separats de la inscripció

**Tall de revisió:** 27/09/2026. **Fonts:** web pública, tastets, trobades, intranet d'alumnes i DDL SIF de `main`; fitxa/UML de la branca documental. **Decisió funcional vigent específica UC-108 (25/09/2026):** per al tastet gratuït, la subscripció al butlletí és **obligatòria com a condició del tastet** i la persona es pot donar de baixa posteriorment. Aquesta decisió **substitueix** la redacció del 22/09 que la qualificava d'opcional amb Sí/No. La qualificació jurídica/base de tractament i el text exacte continuen pendents de validació específica; no denominar automàticament «consentiment» una base que encara no s'ha classificat.

**No s'han executat proves ni s'ha verificat producció.** [Fitxa v2](../06-fitxes-funcionals/uc-125.md) · [UML](uc-125-consentiment-comunicacions-separat.md) · [activitats P01–P08](uc-125-activitats-comunicacions-actual-final.md).

## 1. Inventari de canals i superfícies ACTUALS

| ID | Canal / codi | Comportament verificat | Diferència amb el contracte FINAL |
| --- | --- | --- | --- |
| P01 | Home / footer de butlletí: [`mostrar_butlleti_electronic_home.php`](../../codi-drive/web-actual/ajax/mostrar_butlleti_electronic_home.php), [`mostrarHome.min.js`](../../codi-drive/web-actual/js1619773569/mostrarHome.min.js#L607-L624), [`mostrarHomePostLoad.min.js` L320–360](../../codi-drive/web-actual/js1619773569/mostrarHomePostLoad.min.js#L320-L360) | Mostra camp email; `news(idButlleti)` valida l'ID rebut però després llegeix **`#adreca-electronica` fix** i `#butlletiSpam`, tot i que el formulari de home usa `#butlleti-adreca-electronica` i `#comprovaSpam`. Crida `mailingNou.php`. | Risc de desacoblament UI/valor enviat. Cal enviar el valor validat del mateix formulari, amb request id, propòsit, avís versionat i resposta tipificada. |
| P02 | Alta general de butlletí: [`mailingNou.php`](../../codi-drive/web-actual/ajax/mailingNou.php) | GET `correu/comprova`; INSERT immediat a `subscriptors(CORREU,DATA)`; després envia correu amb enllaç `/mailing/subscripcio.php?mail=...`. El JS diu «T'has subscrit correctament» i alhora que rebrà un missatge de confirmació. | **Sol·licitud, fila interna i confirmació externa no són el mateix estat.** El target `mailing/subscripcio.php` no és un blob del tree actual; no es pot acreditar aquí què confirma o mou. |
| P03 | Pregunta de mailing dins formularis: [`mailing.php`](../../codi-drive/web-actual/ajax/mailing.php), [`inscripcio_mailing.php`](../../codi-drive/web-actual/ajax/inscripcio_mailing.php) | Si l'email no existeix a `mailing`, mostra Sí/No; si existeix, amaga la pregunta. Les consultes interpolen l'email a SQL en aquests scripts. | Existència d'un string d'email a `mailing` no prova subjecte, finalitat, canal, versió, data ni estat vigent. L'adaptació ha de usar query preparada i estat canònic per subjecte/abast. |
| P04 | Tastet gratuït: [`enviarInscripcioTastet.php`](../../codi-drive/web-actual/ajax/enviarInscripcioTastet.php) | Llegeix `mailing` del GET però fixa `$mailingBD='1'`; si l'email no existeix, INSERT a `mailing`; el correu diu que «amb aquesta inscripció has acceptat rebre» i recorda que es pot donar de baixa. | **ACTUAL és compatible amb la nova regla de obligatorietat només en l'efecte d'alta**, però no hi ha traça versionada de text/base/finalitat/subjecte ni mecanisme de baixa auditat en aquest handler. El paràmetre client `mailing` és redundant/enganyós si la regla és obligatòria. |
| P05 | Fitxa d'alumne: [`guardarDadesPersonals_ConsultaInformacio.php`](../../codi-drive/intranet-actual/ajax/alumnes/guardarDadesPersonals_ConsultaInformacio.php) | GET barreja dades personals, estat acadèmic, Aula Oberta, `mailing`, certificat i baixa i delega a `guardarDadesPersonals_modalsresultatCerca`. La traça anterior UC-042 havia documentat UPDATE directe d'`INSC_MAILING`. | Un flag d'una inscripció no és cronologia de preferència per subjecte/finalitat/canal. Separar command i evidència; editar perfil no ha de crear alta comercial. |
| P06 | Avís d'una trobada: [`Trobada.php` L620–655, L920–962](../../codi-drive/web-actual/Trobada.php#L620-L655), [JS L278–398](../../codi-drive/web-actual/js1619773569/mostrarTrobada.min.js#L278-L398) | El checkbox «Avisa'm 30 minuts abans» és obligatori per enviar; «Vull rebre més informació de cursos i serveis» és opcional. Primer INSERT a `mailing_trobades`; si el segon està marcat, crida també `mailingNou.php`. | **Dues finalitats diferenciades ja existeixen a UI**, però no tenen ledger canònic comú. Un avís de trobada no és subscripció general de cursos. |
| P07 | Avís de totes les trobades: [`Trobades.php` L300–335, L356–395](../../codi-drive/web-actual/Trobades.php#L300-L335), [`mostrarTrobades.min.js`](../../codi-drive/web-actual/js1619773569/mostrarTrobades.min.js) | `addMailingAllTrobades()` recorre trobades futures actives i afegeix el mateix email a cada `mailing_trobades`; la UI també diferencia avís de xerrades i butlletí general. El wrapper llegeix `mailingCursos` però el PHP mostrat no l'utilitza; el JS gestiona el butlletí general amb una crida separada. | Cal finalitat/scope explícits, idempotència i retirada per abast. No crear N proves independents si la decisió era una sola regla «totes les trobades». |
| P08 | SIF DDL: [`000006_add_cross_system_control_tables.sql` L5–53](../../sif/database/migrations/2026_09_16_000006_add_cross_system_control_tables.sql#L5-L53) | Defineix `communication_consent` i `communication_consent_event`: subjecte, propòsit, canal, scope, `NOTICE_VERSION`, `LAWFUL_BASIS`, estat, origen, captura/retirada/caducitat, current event, action/result/hash/actor/correlació. | **DDL ≠ migració aplicada ≠ servei/repository/connector.** No s'ha acreditat un writer PHP o gateway de mailing que utilitzi aquest model. |

## 2. Decisió específica UC-108 — correcció documental necessària

La decisió del **25/09/2026** és:
- el tastet és gratuït;
- la subscripció al butlletí forma part obligatòria de les condicions d'aquest cas;
- el formulari no necessita selector Sí/No per aquesta subscripció;
- la persona es pot donar de baixa posteriorment.

Per tant, queden **superades** les frases de 22/09 que exigien «Sí/No» opcional i «No» sense alta comercial. Aquesta correcció afecta UC-108, UC-125 i el registre mestre, però **no canvia** les altres superfícies: a trobades, per exemple, l'avís de l'esdeveniment i el butlletí general continuen sent dos checkboxes diferents; i el formulari general del butlletí continua essent una alta independent.

**Punt de governança pendent:** la taula SIF exigeix `LAWFUL_BASIS`. La decisió de negoci «obligatori perquè és gratuït» no determina per si sola quin valor jurídic s'hi ha d'emmagatzemar ni quin text informatiu és correcte. El sistema ha de guardar la base **aprovada**, no inventar-la.

## 3. Troballes tècniques prioritàries

| ID | Evidència | Tractament |
| --- | --- | --- |
| UC125-P0-01 · home envia un altre camp | `news(idButlleti)` valida `#<idButlleti>` però assigna `correu=$('#adreca-electronica').val()`; el markup home usa un altre ID. | Fer servir exactament el valor validat; test home i footer separats. No afirmar incidència productiva sense navegador. |
| UC125-P0-02 · confirmació amb estat ambigu | `mailingNou.php` fa INSERT a `subscriptors` abans del mail de confirmació; UI diu subscripció correcta abans d'acreditar clic. | Estats REQUESTED/PENDING_CONFIRMATION/ACTIVE/REJECTED/WITHDRAWN segons política concreta; no reutilitzar la mateixa paraula per tots. |
| UC125-P0-03 · endpoint de confirmació fora del tree | El correu apunta a `/mailing/subscripcio.php?mail=...`; no consta al tree actual. | Localitzar/versionar endpoint real i validar token/identitat/idempotència. No usar email en clar com a prova suficient. |
| UC125-P0-04 · tastet sense prova versionada | L'alta a `mailing` és directa i el text pressuposa acceptació; no registra notice/base/source event. | Adaptar a la decisió 25/09 amb text/condicions aprovats, event immutable i mecanisme de baixa. |
| UC125-P0-05 · baixa no acreditada | Diversos textos diuen «podràs donar-t'hi de baixa», però no s'ha localitzat en aquest tree un endpoint de retirada de la llista general. | Identificar i auditar la baixa real; si no existeix al repo, implementar-la abans de considerar complet el compromís visible. |
| UC125-P1-06 · email com a identitat | Taules/scripts llegats busquen per string email. | Resoldre `SUBJECT_KEY`/identitat UC-126; email compartit o canviat no transfereix automàticament una decisió. |
| UC125-P1-07 · INSC_MAILING mixt | El modal de l'alumne edita `mailing` amb dades acadèmiques/personals. | Separar comanda i permisos; mantenir `INSC_MAILING` només com read model si cal. |
| UC125-P1-08 · finalitats de trobades | `mailing_trobades` i butlletí general són efectes diferents disparats des de la mateixa modal. | `PURPOSE_CODE/SCOPE_CODE` diferents; retirada d'un no afecta l'altre tret de decisió explícita. |
| UC125-P1-09 · SQL interpolat | `mailing.php` i `inscripcio_mailing.php` interpolen email. | Migrar a prepared statements; no es documenta cap tècnica d'explotació ni es presumeix compromís. |

## 4. Matriu de significats: no confondre flags, files i decisions

| Dada llegada | Què acredita | Què **no** acredita |
| --- | --- | --- |
| fila a `mailing` | email present a la llista llegada | identitat del subjecte, versió del text, base, finalitat, historial o retirada |
| fila a `subscriptors` | sol·licitud inserida per `mailingNou.php` | clic de confirmació ni alta efectiva al destí |
| fila a `mailing_trobades` | email associat a una trobada concreta | butlletí general o consentiment per altres campanyes |
| `INSC_MAILING` | flag guardat a una inscripció | decisió canònica d'una persona per tots els cursos/canals |
| alta tastet + `mailing` | efecte llegat actual de la regla UC-108 | `NOTICE_VERSION/LAWFUL_BASIS/EVIDENCE_HASH` o baixa posterior |
| `communication_consent` DDL | esquema previst per l'estat | que el servei existeixi o la fila estigui desplegada |

## 5. Proves UC125-T01–T20 — **NO EXECUTADES**

| ID | Escenari |
| --- | --- |
| T01 | Home: escriure només `butlleti-adreca-electronica`; la petició ha d'enviar exactament aquest email, no el footer. |
| T02 | Footer: mateixa funció amb el seu input; no barrejar home/footer. |
| T03 | Honeypot home: ID real del markup s'ha de validar i bloquejar bots; cap mismatch d'ID. |
| T04 | `mailingNou`: INSERT correcte però correu de confirmació falla → estat no es declara ACTIVE si la política exigeix confirmació. |
| T05 | Confirmació duplicada/reintent → un sol estat lògic i event idempotent. |
| T06 | Enllaç de confirmació alterat/email aliè → denegació; no activar per conèixer l'email. |
| T07 | Alta general + email ja existent → resultat NO_CHANGE/estat actual, no nova prova inventada. |
| T08 | Retirada general → event WITHDRAWN, futurs enviaments bloquejats i historial mínim preservat. |
| T09 | Canvi d'email després de retirada → no reactivar per string nou sense subjecte resolt. |
| T10 | Dues persones comparteixen email → decisions separades quan el model/abast ho requereixi. |
| T11 | Tastet 25/09: alta gratuïta crea l'alta de butlletí exigida pel contracte funcional i registra text/base aprovats; no selector Sí/No obsolet. |
| T12 | Tastet: persona es dona de baixa del butlletí després → retirada possible sense esborrar la inscripció acadèmica ni factura inexistent. |
| T13 | Reinscripció posterior al tastet amb butlletí retirat → aplicar la regla vigent del nou contracte i conservar la cronologia, no sobreescriure l'event antic. |
| T14 | Avís d'una trobada sense checkbox de cursos → només `mailing_trobades`, cap alta general. |
| T15 | Avís d'una trobada + checkbox cursos → dues finalitats amb resultats independents; fallada d'una no desfà l'altra. |
| T16 | «Totes les trobades» → una decisió/scope amb N destins, idempotent, sense duplicats per reintent. |
| T17 | Edició `INSC_MAILING=1` a intranet sense evidència canònica → no crear alta externa automàtica. |
| T18 | Retirada mentre una campanya comercial és pendent → revalidar estat abans del transport; no reactivar per resposta tardana. |
| T19 | Comunicació operativa (pagament, accés, factura) a persona no activa al butlletí → segueix la seva base/cas propi i no crea subscripció general. |
| T20 | SIF indisponible/connector mailing falla després del commit → event PENDING/FAILED i retry idempotent, sense falsejar ACTIVE. |

## 6. Estat

**DOC:** superfícies web, tastet, trobades, intranet i DDL contrastats; decisió UC-108 del 25/09 incorporada i marcada com a substitutiva de la del 22/09. **IMP:** scripts llegats reals; servei/repository SIF de decisions i connector de comunicacions no acreditats. **TEST:** T01–T20 definits, cap executat. **PRODUCCIÓ:** no verificada. **Bloquejants de definició:** base jurídica/valor `LAWFUL_BASIS`, textos/versionat, estat exacte de confirmació general, endpoint/flux real de baixa, retenció de proves i mapping de finalitats/canals.
