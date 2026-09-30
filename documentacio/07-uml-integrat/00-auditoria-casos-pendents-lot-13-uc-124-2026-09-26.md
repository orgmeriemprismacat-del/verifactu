# Lot 13 — UC-124 · reconciliar accés acadèmic i certificat amb baixa, deute i pagador de grup

**Tall:** 26/09/2026. **Fonts contrastades:** portal alumne `meus-cursos`, control de morosos, wrappers de baixa/certificat, `IntranetAlumne.php`, `LegacySyncService/Repository` i DDL SIF. **Limitació explícita:** `codi-drive/intranet-actual/Intranet.php` és un blob de ~1,47 MB i el connector no n'ha retornat fragments en aquesta revisió; els endpoints de certificat que el JS actual referencia tampoc apareixen al tree actual de `main`. Les afirmacions sobre els seus cossos anteriors es mantenen només com a traça documental prèvia, no com a re-verificació d'aquest lot. **No s'ha executat cap prova ni producció.**

[Fitxa v2](../06-fitxes-funcionals/uc-124.md) · [UML ACTUAL/FINAL](uc-124-reconciliar-acces-certificat-baixa-deute.md) · [activitats P01–P08](uc-124-activitats-acces-certificat-actual-final.md).

## 1. Evidència ACTUAL per superfície

| ID | Superfície/codi | Fet verificat | Límit |
| --- | --- | --- | --- |
| P01 | [`IntranetAlumne.php` L878–1072](../../codi-drive/intranet-alumne-actual/IntranetAlumne.php#L878-L1072) | «Cursos pendents/cursant» mostra preu, `A_PAGAR-PAGAMENT`, estat i accions. Si `ENTITAT` existeix i queda import pendent, **no mostra botó de pagament individual**: mostra «-». | Aquesta vista tracta l'entitat com a pagador operatiu, però no prova imputació individual ni estat bancari canònic. |
| P02 | [`IntranetAlumne.php` L1435–1695](../../codi-drive/intranet-alumne-actual/IntranetAlumne.php#L1435-L1695) | «Cursos acabats» torna a calcular `faltaPagar=A_PAGAR-PAGAMENT`. Si és >0, força estat de certificat `pendent-pagar`; només mostra Aula Oberta si <=0. **Aquí no hi ha l'excepció d'`ENTITAT` del P01 per al botó de pagar.** | Regla llegada = deute de la fila; no distingeix responsable econòmic ni ledger SIF. Pot classificar una persona com a pendent encara que la responsabilitat sigui d'una entitat. |
| P03 | [`meus-cursos.js` L62–90](../../codi-drive/intranet-alumne-actual/js/meus-cursos.js#L62-L90), [`obtenirUrlPagament.php`](../../codi-drive/intranet-alumne-actual/ajax/cursos/obtenirUrlPagament.php), [`IntranetAlumne.php` L2943–2984](../../codi-drive/intranet-alumne-actual/IntranetAlumne.php#L2943-L2984) | Clicar `payInsc` envia GET amb `tipusInsc,idPag`; backend xifra l'`IDPAG` i torna `/pagament/... ` o `/pagaments/...` si tipus G. | El mètode de URL no valida pagador, deute real, factura d'empresa, estat de reserva o si aquell usuari ha de poder iniciar el pagament. |
| P04 | [`meus-cursos.js` L223–405](../../codi-drive/intranet-alumne-actual/js/meus-cursos.js#L223-L405), [`solicitaAccesAulaOberta.php`](../../codi-drive/intranet-alumne-actual/ajax/cursos/solicitaAccesAulaOberta.php), [`IntranetAlumne.php` L1919–2005](../../codi-drive/intranet-alumne-actual/IntranetAlumne.php#L1919-L2005) | Si `PERENNE=1` la UI obre el curs Moodle; si X permet demanar accés; la sol·licitud posa `PERENNE=0` i prepara correus. | `subscripcioAulaOberta()` no verifica en el fragment l'estat econòmic, ni crea matrícula Moodle; actualitza una bandera llegada i comunica. La UI només ofereix l'acció quan P02 ja ha calculat pendent<=0. |
| P05 | [`facturacio-control-morosos.php`](../../codi-drive/intranet-actual/facturacio-control-morosos.php), [JS minificat](../../codi-drive/intranet-actual/js/facturacio-control-morosos.min.js) i wrappers | La pantalla separa **entitats**, **alumnes aprovats sense certificat** i **alumnes amb certificat**, i envia POST per `idInsc` a writers diferents. | La classificació de reclamació no defineix per si sola política de certificat ni prova que el deute sigui individual. |
| P06 | [`mostraModalDonarBaixa.php`](../../codi-drive/intranet-actual/ajax/alumnes/mostraModalDonarBaixa.php), [`confirmacioBaixa_DonarBaixa.php`](../../codi-drive/intranet-actual/ajax/alumnes/confirmacioBaixa_DonarBaixa.php) | Baixa llegada per `idInsc`, motiu i opció de correu; wrappers inclouen connexions Moodle i deleguen a `Intranet`. | Cos real de `Intranet::confirmaBaixa_modalDonarBaixa` no re-verificat; no atribuir automàticament DELETE Moodle, devolució o rectificativa. |
| P07 | [`alumnes-mostrar-alumne.js` L2327–2495](../../codi-drive/intranet-actual/js/alumnes-mostrar-alumne.js#L2327-L2495) | JS intenta obrir modal de certificat per `idInsc,tipus`, previsualitzar i descarregar; la descàrrega requereix `tePermisEdicio` al client. | **Deriva repo/runtime:** els endpoints `mostraModalConsultaCertificat.php` i `mostrarCertificat.php` referenciats pel JS no són blobs del tree actual de `main`; el cos PHP no s'ha revalidat. |
| P08 | [`LegacySyncService.php`](../../sif/src/Service/LegacySyncService.php), [`LegacySyncRepository.php`](../../sif/src/Repository/LegacySyncRepository.php), [DDL L270–297](../../sif/database/migrations/2026_09_16_000005_add_operation_lifecycle_tables.sql#L270-L297) | Després del SIF, el sync llegat només actualitza `FACTURA_RELACIONADA` i concatena número/estat/UUID a `OBSERVACIONS`. El DDL defineix `academic_economic_state_event` amb abans/després, snapshot econòmic/hash, regla i causalitat. | No s'ha identificat writer PHP de l'event ni adaptador SIF Moodle/certificat. Text d'`OBSERVACIONS` no és ledger ni confirmació d'accés. |

## 2. Incoherència ACTUAL principal: qui paga i què bloqueja

La mateixa classe `IntranetAlumne` tracta el pagador d'entitat de dues maneres. En cursos **no acabats**, si `ENTITAT` no és buit i `A_PAGAR-PAGAMENT>0`, mostra «PAGA L'ENTITAT» i substitueix el botó de pagament individual per «-». En cursos **acabats**, continua mostrant «PAGA L'ENTITAT» al preu, però el bloc de pendent de pagament genera un botó `pay-<IDPAG>-<TIPUS_INSC>` sense comprovar `ENTITAT`. A continuació, el mateix pendent força el certificat a `pendent-pagar` i impedeix qualsevol CTA d'Aula Oberta.

Per tant, **l'estat econòmic de la fila llegada actua com a proxy de dret acadèmic**, però la responsabilitat de pagament no està integrada en aquella decisió. Això no prova un incident productiu concret, però sí una regla incoherent al codi i una necessitat clara de separar:
1. import pendent de l'operació,
2. titular/responsable del pagament,
3. atribució individual de l'ingrés,
4. dret acadèmic,
5. certificat,
6. accés/Moodle.

## 3. Moodle i Aula Oberta: què fa i què no fa el portal

`IntranetAlumne` conté queries Moodle per trobar curs/usuaris/matrícules i sentències de DELETE, però a la classe revisada **no s'han trobat invocacions** de `deleteMdlUserEnrol` o `deleteMdlRoleAssig`. El recorregut d'Aula Oberta del portal:
- consulta el curs Moodle pel `shortname`,
- obre directament la URL del curs si `PERENNE=1`,
- o, si `PERENNE='X'`, demana consentiment i envia `solicitaAccesAulaOberta`,
- `subscripcioAulaOberta` canvia `PERENNE` a `0` i comunica una sol·licitud.

Això és **sol·licitud/accés visual**, no prova d'alta de matrícula Moodle, de baixa efectiva, de sincronització bidireccional o de verificació de dret després d'un pagament.

## 4. Riscos i mancances prioritzades

| ID | Risc suportat | Acció |
| --- | --- | --- |
| UC124-P0-01 | Certificat i Aula Oberta depenen directament de `A_PAGAR-PAGAMENT`, sense considerar `ENTITAT` en P02. | Matriu versionada per responsable, estat acadèmic i deute canònic; cap bloqueig per simple fila llegada. |
| UC124-P0-02 | P01 evita pagament individual d'entitat; P02 pot tornar a oferir-lo. | Una sola política backend de «qui pot pagar què» i una sola font de dret de cobrament. |
| UC124-P0-03 | `obtenirUrlPagament()` xifra qualsevol `idPag>0` que rep i decideix singular/plural només per `TIPUS_INSC='G'`. | Autoritzar l'operació i el pagador al servidor abans de crear/retornar enllaç. |
| UC124-P0-04 | Baixa acadèmica i reclamació comparteixen `idInsc`, però no hi ha event SIF coordinador acreditat. | Registrar petició/decisió acadèmica amb snapshot econòmic i no executar efecte monetari implícit. |
| UC124-P1-05 | `PERENNE` X/0/1 serveix de workflow d'Aula Oberta; canvi a 0 significa «en tràmit», no matrícula confirmada. | Gateway Moodle idempotent amb read-back; separar REQUESTED/APPLIED/FAILED. |
| UC124-P1-06 | Control morosos diferencia amb/sense certificat i entitats però els wrappers deleguen a cos PHP no rellegit. | Documentar exactament consulta/writer, no usar categoria de reclamació com a política de certificació. |
| UC124-P1-07 | JS certificat referencia endpoints absents al tree actual. | Resoldre deriva de repositori/desplegament abans de considerar la funcionalitat traçable. |
| UC124-P1-08 | `LegacySyncRepository` només concatena resum fiscal a `OBSERVACIONS`. | No usar text com a prova de pagament, deute, accés o certificat; read model estructurat. |
| UC124-P1-09 | Accions sensibles de portal i baixa s'inicien amb GET en diversos endpoints. | POST/command, CSRF, control d'objecte/subjecte i resposta tipificada. |

## 5. Proves proposades — UC124-T01–T18, **NO EXECUTADES**

| ID | Escenari / resultat exigible |
| --- | --- |
| T01 | Alumne individual, curs acabat, pagament real complet: certificat/access segons estat acadèmic i regla, no només `PAGAMENT`. |
| T02 | Alumne individual amb deute real: mostrar via de regularització; aplicar política acadèmica aprovada, no inventar revocació. |
| T03 | `ENTITAT` responsable, factura pendent: alumne no rep botó individual si no és pagador autoritzat. |
| T04 | Mateixa entitat després de curs acabat: la política de pagament no canvia només pel pas del temps. |
| T05 | Entitat paga un únic moviment per N participants: dret individual es deriva de l'assignació/regla, no d'un `IDPAG` compartit. |
| T06 | Participant de grup de baixa després de pagament de l'empresa: cap `REFUND` al participant per defecte. |
| T07 | Certificat ja emès i deute posterior/reconciliat: conservar document/històric; acció nova segons regla. |
| T08 | Curs superat sense certificat, entitat encara pendent: decisió separada entre reclamació i dret de certificat. |
| T09 | `PAGAMENT` llegat 0 però `payment_transaction/allocation` SIF confirmat: reparar read model, cap segon cobrament. |
| T10 | `PERENNE=X` i sol·licitud duplicada Aula Oberta: una sola petició efectiva/estat REQUESTED. |
| T11 | `PERENNE=0`, Moodle ja dona accés: reconciliar a APPLIED sense repetir alta. |
| T12 | `PERENNE=1`, Moodle no té matrícula/permís: detectar divergència, no mostrar «accés correcte» només per la bandera. |
| T13 | Baixa confirmada al llegat però Moodle falla: PARTIAL/ERROR i retry només del destí acadèmic. |
| T14 | Baixa acadèmica amb factura emesa: factura immutable i cap moviment econòmic automàtic. |
| T15 | Control morosos «amb certificat»: enviar reclamació sense revocar certificat com a efecte lateral. |
| T16 | Certificat JS apunta a endpoint no versionat: preproducció falla de manera visible i obre incidència, no 200 fictici. |
| T17 | Usuari altera `idInsc`/IDPAG a GET: backend verifica pertinença/representació i denega objecte aliè. |
| T18 | Event final: `academic_economic_state_event` conserva regla, actor, abans/després, hash econòmic i correlació sense modificar ledger. |

## 6. Estat

**DOC:** fluxos del portal alumne, control morosos, wrappers de baixa, JS de certificat, sync SIF i DDL contrastats. **IMP:** portal i lògica llegada existents; coordinador de reconciliació, writer d'events i gateway SIF Moodle/certificat no acreditats. **TEST:** T01–T18 definits, cap executat. **PRODUCCIÓ:** no verificada. **Decisions pendents:** matriu de dret a accés/certificat per baixa/superació/deute/pagador/pròrroga, política de regularització de grup/empresa, significat canònic de `PERENNE`, i resolució del drift dels endpoints de certificat.
