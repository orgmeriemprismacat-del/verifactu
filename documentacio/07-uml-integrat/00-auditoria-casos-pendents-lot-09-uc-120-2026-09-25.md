# Lot 09 — auditoria UC-120 · canvi de dades personals i propagació

**Tall:** 25/09/2026. **Codi revisat:** `main` del repositori, intranet d'alumne, intranet interna i DDL SIF. **Branca documental:** `docs/registre-mestre-auditoria-2026-09-22`. **DOC:** vies ACTUALS de sol·licitud d'alumne i edició directa interna traçades; disseny FINAL de petició/revisió/propagació separat. **IMP del coordinador de canvi personal:** no acreditada. **TEST:** no executat. **PRODUCCIÓ:** no verificada.

[Fitxa UC-120](../06-fitxes-funcionals/uc-120.md) · [UML UC-120](uc-120-canvi-dades-personals-propagacio.md) · [activitats ACTUAL/FINAL](uc-120-activitats-dades-personals-actual-final.md).

## 1. Superfícies i accions ACTUALS

| ID | Superfície | Evidència directa | Què fa realment / límit |
| --- | --- | --- | --- |
| P01 | Intranet alumne · «Les meves dades» | [`dades-personals.php`](../../codi-drive/intranet-alumne-actual/dades-personals.php), [`js/dades-personals.js`](../../codi-drive/intranet-alumne-actual/js/dades-personals.js), [`ajax/dades/mostrarDades.php`](../../codi-drive/intranet-alumne-actual/ajax/dades/mostrarDades.php), [`mostrarDadesEditables.php`](../../codi-drive/intranet-alumne-actual/ajax/dades/mostrarDadesEditables.php) | L'alumne consulta dades, activa edició visual i envia proposta; no executa UPDATE directe al perfil en el flux inspeccionat. |
| P02 | Alumne · enviar petició | [JS `enviarMsgSolicitantModificacioDades()`](../../codi-drive/intranet-alumne-actual/js/dades-personals.js), [`ajax/dades/enviarMsgPeticioActualtizacio.php`](../../codi-drive/intranet-alumne-actual/ajax/dades/enviarMsgPeticioActualtizacio.php) | GET amb dades personals i comentari; el wrapper invoca `IntranetAlumne::enviarMsgSolicitantModificacioDades()`. |
| P03 | Backend alumne · construir sol·licitud | [`IntranetAlumne.php` L607–765](../../codi-drive/intranet-alumne-actual/IntranetAlumne.php#L607-L765) | Llegeix la inscripció associada a l'usuari, compara camp a camp, construeix «Dades actuals / Dades a modificar», prepara correu a Secretaria i confirmació a l'alumne. Si no hi ha canvis retorna `No canvi`. **No persisteix una fila de petició SIF ni modifica `inscripcions` en aquest mètode.** |
| P04 | Intranet interna · «Consulta / Modifica alumne» · Dades personals | [`alumnes-mostrar-alumne.php`](../../codi-drive/intranet-actual/alumnes-mostrar-alumne.php), [JS L639–717](../../codi-drive/intranet-actual/js/alumnes-mostrar-alumne.js#L639-L717), [`ajax/alumnes/guardarDadesPersonals.php`](../../codi-drive/intranet-actual/ajax/alumnes/guardarDadesPersonals.php) | Si `tePermisEdicio` al client, converteix camps en inputs, valida i envia GET amb dades personals. Wrapper crida `guardarDadesPersonals_resultatCerca(idInsc,...)`. La [traça de l'auditoria UC-042](00-captures-auditoria-alumnes-consulta-modifica-2026-09-22.md#7-cerca-avancada-dades-personals-observacions-i-certificats--contrast-sense-captures-noves) situa el mètode a `Intranet.php` L6099–6123 i documenta UPDATE d'un registre d'`inscripcions` per ID. |
| P05 | Intranet interna · modal «Dades de la inscripció» | [JS L1090–1210](../../codi-drive/intranet-actual/js/alumnes-mostrar-alumne.js#L1090-L1210), [`guardarDadesPersonals_ConsultaInformacio.php`](../../codi-drive/intranet-actual/ajax/alumnes/guardarDadesPersonals_ConsultaInformacio.php) | Permet enviar, a més de personals, data inscripció, estat, aula oberta, mailing, certificat, generat, observacions, baixa/motiu/actor. La traça UC-042 situa `guardarDadesPersonals_modalsresultatCerca()` a L7473–7510 i descriu UPDATE directe d'`inscripcions` per ID. **No equival a propagar identitat a Moodle, factures o altres matrícules.** |
| P06 | DDL SIF de petició personal | [migració 000005 L219–240](../../sif/database/migrations/2026_09_16_000005_add_operation_lifecycle_tables.sql#L219-L240) | Defineix `personal_data_change_request` amb `CHANGESET_JSON`, justificació/evidència, revisió, `PROPAGATION_STATUS/RESULT`, operacions obertes i correlació. **No s'ha localitzat un writer PHP executable que substitueixi les dues vies llegades anteriors.** |

## 2. Diferència crítica: «sol·licitar» no és «aplicar»

### 2.1. Alumne
El flux de l'alumne **crea una comunicació**, no una mutació de dades. `IntranetAlumne::enviarMsgSolicitantModificacioDades()` rellegeix la inscripció vinculada a l'usuari, compara **nom, cognoms, email, DNI, telèfon, adreça, CP, població, perfil i titulació**, i prepara dos correus. La UI diu que «en 24/48h laborables realitzarem el canvi». Aquesta frase és una promesa operativa del text actual; **no és prova que el sistema hagi registrat una petició estructurada, assignat un revisor ni propagat res**.

### 2.2. Gestió interna
La intranet principal pot fer un UPDATE directe d'una inscripció concreta. La ruta de dades personals envia `idInsc` i 10 camps; el modal ampli envia també camps acadèmics/administratius. L'efecte documentat a la traça UC-042 és **un registre d'`inscripcions`**, no un perfil mestre verificat amb propagació a totes les matrícules o sistemes.

### 2.3. FINAL
UC-120 no ha de convertir automàticament qualsevol edició interna en «petició pendent». El contracte final separa:
1. petició de la persona,
2. edició administrativa autoritzada,
3. revisió/decisió quan el camp ho requereix,
4. actualització de dades vigents per destinació,
5. reclassificació d'operacions obertes,
6. preservació de snapshots i documents històrics.

## 3. Camps, destinacions i immutabilitat

| Categoria | ACTUAL observat | FINAL |
| --- | --- | --- |
| Nom/cognoms | Dades de la inscripció i petició per correu | Perfil vigent + matrícules obertes segons política; història preservada. |
| DNI/NIF | Editable/peticionable com a dada de matrícula | Canvi d'identitat requereix validació reforçada; no substituir receptor de factura emesa. |
| Email/telèfon/adreça/CP/població | Editables/peticionables | Contacte vigent; revalidar destinatari de notificacions pendents, sense alterar PDF fiscal antic. |
| Perfil/titulació | Editables/peticionables | Propagació acadèmica concreta; no efecte fiscal automàtic. |
| Estat matrícula, mailing, certificat, baixa | Editables al modal intern ampli | Són dominis diferents: estat acadèmic, consentiment comercial, certificació i baixa requereixen UC/traça pròpia; no han de compartir un UPDATE sense classificació. |
| Factura/receptor històric | No s'ha observat propagació des dels endpoints | Immutable; si la factura conté error real, obrir UC corrector, no UPDATE del document. |

## 4. Riscos i mancances

| ID | Evidència | Risc / acció |
| --- | --- | --- |
| UC120-P0-01 · dades personals per GET | Portal alumne i intranet interna envien nom, DNI, email, adreça, etc. en GET. | Migrar a POST/JSON protegit, minimitzar logs/URLs, CSRF i autorització backend. No afirmar que proxies/logs productius hagin retingut les dades sense verificar-ho. |
| UC120-P0-02 · mutació per inscripció, no perfil canònic | UPDATE llegat per `idInsc`; petició d'alumne llegeix una inscripció associada a l'usuari. | Definir `SUBJECT_KEY` canònic i mapa de destinacions; no declarar «dades personals canviades» si només una matrícula ha canviat. |
| UC120-P0-03 · falta d'historial estructurat | Sol·licitud de l'alumne és correu; l'edició interna directa no acredita before/after/auditoria SIF. | Persistir `personal_data_change_request` o event equivalent amb changeset, actor, decisió i resultats. |
| UC120-P1-04 · resposta d'èxit UI | JS intern considera èxit si resposta textual no conté «Error/error» i repinta valors. | Resposta tipificada amb versió, camps aplicats i destinacions; read-back posterior. |
| UC120-P1-05 · cancel·lació UI | Traça UC-042 documenta que cancel·lar pot repintar el valor actual de l'input en lloc de l'original sense desar-lo. | Restaurar snapshot de lectura real i provar cancel·lació. |
| UC120-P1-06 · modal barreja dominis | Mateix endpoint ampli accepta personals, estat, mailing, certificat, baixa i observacions. | Separar ordres per domini/permisos; consentiment comercial, baixa i certificat no són «dades personals» genèriques. |
| UC120-P1-07 · propagació parcial | DDL preveu `PROPAGATION_STATUS/RESULT`, però no writer/worker identificat. | Jobs idempotents per destinació i estat parcial; retry només del destí fallit. |
| UC120-P1-08 · documents emesos | Perfil actual i `inscripcions` són mutables; factura SIF és històrica. | No propagar nom/NIF nou a documents emesos; correcció fiscal només quan procedeixi. |

## 5. Proves d'acceptació proposades — CAP EXECUTADA

| ID | Escenari / resultat |
| --- | --- |
| UC120-T01 | Alumne canvia només email: es crea petició estructurada, no mutació immediata; abans/després correctes. |
| UC120-T02 | Alumne envia sense canvis: resultat NO_CHANGE, cap petició/avís duplicat innecessari. |
| UC120-T03 | DNI d'una altra persona o actor no autoritzat: rebuig sense revelar dades. |
| UC120-T04 | Gestió canvia una matrícula concreta: traça actor/abans/després i read-back; altres matrícules només canvien si política ho indica. |
| UC120-T05 | Mateixa persona amb dues inscripcions, una pendent i una històrica: cobertura de propagació explícita per destí. |
| UC120-T06 | Canvi de nom/DNI amb factura emesa: document i snapshot fiscal antics immutables; possible incidència UC corrector, no UPDATE. |
| UC120-T07 | Canvi de receptor en checkout encara no congelat vs `DS_ORDER` ja creat: recalcular/reacceptar només quan correspon. |
| UC120-T08 | Moodle/portal actualitza però web falla: estat PARTIAL i retry de destinació pendent. |
| UC120-T09 | Doble submit/retry mateixa petició: un únic UUID/petició i resultats idempotents. |
| UC120-T10 | Dues decisions concurrents sobre el mateix camp/base version: una acceptada, l'altra conflicte/revisió. |
| UC120-T11 | Cancel·lar edició interna després de modificar camps: UI torna al valor persistent original. |
| UC120-T12 | Mailing/certificat/baixa enviats pel modal: ordres separades i permisos propis; no registrar-los com a simple canvi de contacte. |
| UC120-T13 | Canvi d'email amb notificació fiscal ja encolada: destinatari revalidat, sense reenviar PDF aliè. |
| UC120-T14 | Evidència adjunta sensible: storage protegit, hash, permisos i retenció; no dins `CHANGESET_JSON` o logs. |

## 6. Estat

**DOC:** recorregut alumne + edició interna + DDL de petició personal contrastats. **IMP:** no s'ha acreditat un `PersonalDataChangeService` ni un worker de propagació. **TEST:** T01–T14 definides i no executades. **PRODUCCIÓ:** no verificada. **Decisions pendents:** matriu camp→qui aprova→destins, quines dades es consideren perfil canònic, política de representació/evidència, i abast de propagació per operacions obertes. Factures i snapshots històrics no es tracten com a destí mutable.
