# UC-020 — Auditoria consolidada i matriu de traçabilitat

**Data d'auditoria base:** 29/09/2026  
**Integració documental revisada:** 30/09/2026  
**Abast:** síntesi de troballes contrastades durant l'auditoria del UC-020 i destinació documental/implementació.  
**Mètode:** auditoria base per lectura estàtica; les seccions de reconciliació posteriors incorporen evidència de CI i estat runtime integrat.

> Els identificadors de troballa mantenen la numeració de l'auditoria conversacional. Aquesta consolidació recull les troballes UC020-16…UC020-73 que afecten directament la versió 1.2 de la fitxa i els UML nous.

## 1. Matriu de troballes

| ID | Troballa consolidada | Estat | Destí |
| --- | --- | --- | --- |
| UC020-16 | Hi ha ruta SIF amb snapshot congelat i ruta de compatibilitat/legacy; el transport existeix però l'origen AP autoritzat no està acreditat. | VERIFICAT CODI | UML classes/seqüència |
| UC020-17 | Un pagament fraccionat no és automàticament el preu net total de l'operació. | VERIFICAT CODI | Fitxa §13 |
| UC020-18 | Idempotència basada en DS_ORDER no evita per si sola dues operacions comercials equivalents amb ordres diferents. | VERIFICAT CODI | Fitxa §16 |
| UC020-19 | Text públic «esteu fent o hàgiu fet» no equival literalment a les condicions SQL. | VERIFICAT | Fitxa §7 / P01 |
| UC020-20 | Taula pública i càlcul real poden usar criteris diferents de selecció de tarifa. | VERIFICAT | P01 |
| UC020-21 | El navegador conserva tipus/import com a variables globals modificables. | VERIFICAT | P02 |
| UC020-22 | Promocions i AP poden competir/substituir-se sense una identitat única d'oferta. | VERIFICAT | P02 / FINAL offer_id |
| UC020-23 | La denegació d'intranet pot usar la mateixa inscripció com a antecedent AP si compleix les condicions. | RISC DEDUÏT | Decisió negoci |
| UC020-24 | `idPreu` inicial i edició seleccionada poden no representar inequívocament la mateixa tarifa. | RISC DEDUÏT | Proves |
| UC020-25 | Comprovació de duplicat i INSERT són peticions separades. | VERIFICAT | Concurrència |
| UC020-26 | Generació llegada d'IDPAG requereix contrast d'atomicitat/concurrència. | RISC DEDUÏT | Cas pagament |
| UC020-27 | Inscripció/càlcul llegats transporten dades personals per GET. | VERIFICAT | Seguretat/privacitat |
| UC020-28 | Contracte textual d'errors de preu i validació comercial no independent. | VERIFICAT | P02 |
| UC020-29 | Preparació llegada del pagament usa import enviat pel navegador en el circuit inspeccionat. | VERIFICAT | Cas pagament |
| UC020-30 | Circuit llegat i callback SIF nou tenen garanties diferents; no s'han de confondre. | VERIFICAT | Dependència Redsys |
| UC020-31 | La fitxa anterior era massa genèrica i marcava requisits finals com si descrivissin el cas concret. | CORREGIT DOC | Fitxa v1.2 |
| UC020-32 | Absència de descomptes + Carnet Jove pot entrar en branques amb arrays/índexs no inicialitzats. | VERIFICAT CODI | Errors/proves |
| UC020-33 | Aprovar descompte documental canvia VALID_DESC però no torna a persistir A_PAGAR en la branca inspeccionada. | VERIFICAT | UC-116/P05 |
| UC020-34 | Correu de resolució combina variables anteriors/posteriors al canvi. | VERIFICAT | Notificacions |
| UC020-35 | Permís de visualització no equival a permís de comanda; endpoint no acredita control específic. | VERIFICAT | Seguretat |
| UC020-36 | Persistència i resultat SMTP no formen una operació fiable/idempotent. | VERIFICAT | Notificacions |
| UC020-37 | `TIPUS_DESC=1, VALID_DESC=2` genera interpretacions diferents del pagament. | VERIFICAT | P03/P04 |
| UC020-38 | Doble clic/dos operadors poden repetir resolució; no hi ha estat/versió esperada. | VERIFICAT CODI | P05 |
| UC020-39 | El correu de resolució pot comunicar preu total sense restar cobraments ja efectuats. | VERIFICAT | Notificacions |
| UC020-40 | El mètode disposa de pagament/factura però no els usa com a precondició abans de canviar A_PAGAR. | VERIFICAT | Fitxa §13 |
| UC020-41 | El diagrama compartit UC-116 referenciava `Descomptes.php` en lloc de la classe activa `PaginaDescomptes`. | CORREGIT DOC | UC-116 |
| UC020-42 | Una resolució comercial pot desaparèixer de la cua però quedar interpretada diferent segons pantalla/mètode de pagament. | VERIFICAT / REFORMULAT | P03/P04/P05 |
| UC020-43 | Confirmació i pàgina de pagament no apliquen exactament la mateixa porta a transferència. | VERIFICAT | P03/P04 |
| UC020-44 | Correu posterior a denegació pot oferir targeta però la pàgina no mostrar-la amb VALID_DESC=2. | VERIFICAT | P04 |
| UC020-45 | El token tècnic de pagament es genera abans de saber si un dret documental serà aprovat. | VERIFICAT | P02/P04 |
| UC020-46 | El correu inicial pendent no envia el link de pagament. | VERIFICAT POSITIU | UC-116/P05 |
| UC020-47 | La incoherència principal apareix al correu posterior a la resolució/denegació. | VERIFICAT | P05→P04 |
| UC020-48 | No s'havia localitzat runtime PHP per `commercial_operation`/`discount_validation`/`payment_link` en el tall base. | HISTÒRIC_SUPERAT · vegeu §7–§12 | FINAL |
| UC020-49 | Tests comercials actuals verifiquen sobretot esquema; no UC-20 E2E. | VERIFICAT | Proves |
| UC020-50 | `payment_link` DDL ja modela import esperat, estat, expiració, revocació i substitució. | VERIFICAT DDL | FINAL |
| UC020-51 | `redsys_payment_intent` no conté UUID_OPERATION; en el tall base l'enllaç runtime no estava acreditat. | HISTÒRIC_SUPERAT · vincle via `commercial_operation.UUID_INTENT` | FINAL |
| UC020-52 | Token llegat pot continuar íntegre encara que l'oferta comercial hagi canviat. | VERIFICAT CONCEPTUAL | P04/FINAL |
| UC020-53 | `commercial_operation.PRICE_SNAPSHOT_JSON` permet congelar el passat sense recalcular tarifa actual. | VERIFICAT DDL | FINAL |
| UC020-54 | `GENERAT=1` pot fer elegible web i no intranet. | VERIFICAT | Decisió negoci |
| UC020-55 | Canvi de curs implementa elegibilitat històrica relativa a DATA_INSC original. | VERIFICAT | P06 |
| UC020-56 | La frase pública és més ampla que la regla implementada. | VERIFICAT | P01 |
| UC020-57 | `FACTURA_RELACIONADA != NULL` no és una prova SQL efectiva de no-nul·litat. | VERIFICAT | Regla pendent |
| UC020-58 | Web, denegació i canvi de curs seleccionen tarifa AP amb filtres diferents. | VERIFICAT | P02/P05/P06 |
| UC020-59 | Denegació pot veure tarifa futura perquè falta límit DATAI. | VERIFICAT | P05 |
| UC020-60 | Elegible AP sense tarifa no queda tipificat de manera segura. | VERIFICAT | FINAL ELIGIBLE_NO_PRICE |
| UC020-61 | L'UML anterior deixava com desconegudes regles que ja s'han pogut reconstruir per canal. | CORREGIT DOC | UML integrat |
| UC020-62 | «Historial no verificable → revisió manual» és disseny FINAL, no comportament web ACTUAL. | CORREGIT DOC | UML integrat |
| UC020-63 | AJAX de preu poden respondre fora d'ordre i sobreescriure globals. | VERIFICAT CODI | P02 |
| UC020-64 | `change` + `blur` poden duplicar càlcul. | VERIFICAT CODI | P02 |
| UC020-65 | Confirmació no espera explícitament l'últim càlcul. | VERIFICAT CODI | P02 |
| UC020-66 | `preuInscripcio<=0` no és una precondició independent de confirmació. | VERIFICAT CODI | P02 |
| UC020-67 | Promoció modifica import però no garanteix canvi de `tipusPreuAplicat`. | VERIFICAT CODI | P02 |
| UC020-68 | AP/promoció poden deixar globals provinents de decisions diferents. | RISC DEDUÏT | P02/tests |
| UC020-69 | Resolució intranet usava GET amb efectes persistents en el tall base. | HISTÒRIC_SUPERAT 02/10 · ara POST | P05 |
| UC020-70 | No s'havia localitzat protecció CSRF explícita en el tall base. | HISTÒRIC_SUPERAT 02/10 · CSRF validat | P05/seguretat |
| UC020-71 | El tall base no acreditava una protecció completa de comanda. | HISTÒRIC_SUPERAT 02/10 · sessió/objectes + permís específic revalidats | P05 |
| UC020-72 | UPDATE no exigeix estat/versió esperada. | VERIFICAT | P05 |
| UC020-73 | UI de resolució interpreta èxit per absència del text «error». | VERIFICAT | P05 |

## 2. Correccions explícites d'auditoria

### 2.1. Rutes actives de pagament/confirmació

Queden superades les primeres observacions que atribuïen les rutes actives a `PagamentCurs`.

Segons `.htaccess`:

- `/confirmacio/...` usa la variant automàtica.
- `/pagament/...` usa la variant automàtica.
- `PagamentCursAutomatic` és la classe rellevant per al flux actiu principal.
- Les rutes `_antic` són les que continuen vinculades al circuit antic.

### 2.2. Política AP

No afirmar «no hi ha regla». Hi ha **tres regles llegades reconstruïdes**; el pendent és unificar-les/ratificar-les.

### 2.3. Preu AP

No descriure AP ACTUAL com un percentatge fix. El codi usa `descomptes.PREU` com a tarifa final configurada.

## 3. Inventari documental després de la implementació

| Artefacte | Abans | Després |
| --- | --- | --- |
| Fitxa funcional UC-020 | existent, genèrica v1.1 | actualitzada v1.2 amb auditoria específica |
| UML integrat UC-020 | existent, sobretot FINAL/SIF | actualitzat amb ACTUAL + FINAL |
| Classes UC-020 | parcials dins UML | `uc-020-classes-actual-final.md` + resum integrat |
| Seqüències UC-020 | una seqüència mixta | `uc-020-sequencies-actual-final.md` + resum integrat |
| Activitats per pàgina | **no existia dossier específic** | creat `uc-020-activitats-pagines-actual-final.md` |
| Traçabilitat d'auditoria | dispersa | aquest document |
| Matriu AP-01…AP-84 | dispersa/incompleta | creada `uc-020-matriu-proves-ap-01-84.md` |
| Runtime `commercial_operation` | només DDL | repositori + `CommercialOfferService` en aquesta branca |
| Runtime `discount_validation` | només DDL | repositori + persistència transaccional en aquesta branca |
| Runtime `payment_link` | només DDL | repositori + `PaymentLinkService` en aquesta branca |
| Pàgina compartida UC-116 | contenia referència P01 incorrecta | corregida en aquesta branca |

## 4. Paquet de proves prioritzat

### 4.1. Pagament/estat

- AP-49: pendent documental → confirmació sense pagament.
- AP-50: pendent documental + accés directe al link → FINAL sense mètodes executables.
- AP-51: denegació + AP → nova oferta pagable coherent.
- AP-52: correu de resolució vs mètodes visibles.
- AP-53: token manipulat → bloqueig.
- AP-54: token vàlid però oferta no pagable → bloqueig comercial.
- AP-55: repetir resolució → no duplicar decisió/notificació.

### 4.2. Operació comercial SIF

- AP-56: crear `commercial_operation` + `discount_validation`.
- AP-57: reintent idempotent.
- AP-58: tarifa canvia després → snapshot original estable.
- AP-59: revocar/substituir `payment_link`.
- AP-60: link antic revocat → cap intent.
- AP-61: link/import vs NET_AMOUNT discrepant → conflicte.
- AP-62: operació → intenció amb relació explícita.
- AP-63: mateix DS_ORDER + snapshot diferent → conflicte.
- AP-64: dret original denegat + AP acceptat → dues decisions, una oferta actual.

### 4.3. Elegibilitat i tarifa

- AP-65: únic antecedent `GENERAT=1`.
- AP-66: única evidència = inscripció actual amb pagament parcial.
- AP-67: antecedent posterior a la data original en canvi de curs.
- AP-68: factura relacionada sense pagament.
- AP-69: mateix ID_PREU amb tarifes AP per curs/mes diferents.
- AP-70: tarifa AP actual + futura.
- AP-71: elegible sense tarifa AP.
- AP-72: canvi de curs amb tarifa específica per curs.
- AP-73: mateixa operació per web/intranet → mateixa política.

### 4.4. Concurrència i seguretat

- AP-74: DNI A lent → DNI B ràpid.
- AP-75: canvi d'edició amb càlcul pendent.
- AP-76: check de descompte marcat/desmarcat ràpidament.
- AP-77: AP calculat → codi promocional.
- AP-78: promoció → resposta tardana AP.
- AP-79: confirmar mentre recalcula.
- AP-80: mateixa resolució/idempotency key.
- AP-81: resolució sobre estat ja resolt.
- AP-82: sessió sense permís específic.
- AP-83: comanda sense CSRF/origen autoritzat.
- AP-84: resposta backend estructurada i UI coherent.

## 5. Criteri de verificació

Cap fila «PENDENT EXECUCIÓ» passa a VERIFICADA només perquè existeixi un test. Cal conservar:

- data/hora d'execució;
- commit SHA;
- entorn;
- dades fixture o identificador de prova;
- resultat;
- evidència/log;
- incidència relacionada si falla.

## 6. Relacions

- [Fitxa UC-020](../06-fitxes-funcionals/uc-020.md)
- [UML integrat UC-020](uc-020-aplicar-alumne-prisma.md)
- [Classes ACTUAL/FINAL](uc-020-classes-actual-final.md)
- [Seqüències ACTUAL/FINAL](uc-020-sequencies-actual-final.md)
- [Activitats UC-020](uc-020-activitats-pagines-actual-final.md)
- [UC-116 compartit](uc-116-activitats-pagines-justificants-actual-final.md)
- [Matriu AP-01…AP-84](uc-020-matriu-proves-ap-01-84.md)


## 7. Implementació posterior a l'auditoria base — 30/09/2026

Aquesta secció no reescriu les troballes històriques UC020-16…UC020-73; registra què queda implementat després de l'auditoria estàtica base.

### 7.1. Runtime nou

- `CommercialOperationRepository`: lectura per UUID/clau idempotent, inserció i primitive de vinculació optimista de `UUID_INTENT`.
- `DiscountValidationRepository`: lectura idempotent i inserció de decisions versionades.
- `PaymentLinkRepository`: persistència, resolució per hash, accés i revocació.
- `CommercialOfferService`: crea/reutilitza transaccionalment `commercial_operation` + `discount_validation`, valida aritmètica `gross-discount=net` i registra `operational_event`.
- `PaymentLinkService`: genera token opac, només persisteix SHA-256, comprova import màxim respecte del net, expiració, revocació i resolució del link.

### 7.2. Tests nous

- `CommercialOfferServiceTest`: creació, reús idempotent, conflicte de payload/clau i aritmètica inconsistent.
- `PaymentLinkServiceTest`: token/hash, resolució, import superior al net, expiració, revocació i idempotència de revocació.

Aquests tests estan **creats però no es declaren verificats** fins que s'executi la suite sobre `sif_test*` i es conservi l'evidència.

### 7.3. Buits oberts a 30/09/2026 — fotografia històrica, supersedida per §11 i §12

1. **SUPERAT EL 02/10/2026.** `PrismaStudentDiscountPolicy` existeix sota `ALUMNE_PRISMA_WEB_LEGACY_V2`; les decisions `UC20-DEC-001…006` van quedar tancades posteriorment i qualsevol canvi futur requerirà una nova versió.
2. Alta/preview web i resolució intranet → oferta servidor canònica (`CommercialOfferService` o equivalent); el checkout de targeta actiu ja deriva a `PrismaStudentCourseCheckoutService` via `course-intent`.
3. Substitució de les rutes llegades de confirmació/pagament per `PaymentLinkService` i/o operació servidor autoritativa.
4. El nucli `PrismaStudentCourseCheckoutService → RedsysPaymentIntentService → commercial_operation.UUID_INTENT` està implementat **i integrat al canal de targeta actiu**; resta coordinar-lo amb `payment_link` i amb l'oferta creada en alta/preview.
5. Política completa de múltiples intents Redsys sobre una mateixa operació i substitució/revocació de links.
6. E2E historial → oferta/operació AP → intent → callback → factura i evidència de preproducció.


## 8. Tall executable UC-020 integrat — 30/09/2026

### 8.1. Codi específic incorporat

| Peça | Estat | Finalitat |
| --- | --- | --- |
| `PrismaStudentDiscountPolicy` | IMPLEMENTAT_COMPATIBILITAT | Reprodueix la regla web sota `ALUMNE_PRISMA_WEB_LEGACY_V2` i retorna evidència concreta sense tancar decisions futures. |
| `LegacyPrismaStudentHistoryRepository` | IMPLEMENTAT | Recupera fets d'historial per document sense decidir elegibilitat. |
| `CourseIntentSnapshotValidator` | IMPLEMENTAT | Valida source, inscripció, IDPAG, import i coherència del descompte per intencions CURS. |
| `LegacyPrismaStudentPriceSnapshotResolver` | IMPLEMENTAT | Obté snapshot de preu autoritatiu des de dades llegades. |
| `PrismaStudentCourseCheckoutService` | IMPLEMENTAT_NUCLI | Orquestra historial → policy → operació/validació → snapshot → intenció → vincle d'intent. |
| `RedsysCoursePaymentIntentService` / `RedsysPaymentIntentService` | MODIFICAT | Consumeixen i validen el contracte CURS/AP abans del TPV. |

Aquesta capa és **complementària**, no substitutiva, de la infraestructura comercial general descrita a 7.1 (`CommercialOfferService`, repositoris comercials i `PaymentLinkService`).

### 8.2. Incidències resoltes o reduïdes

| ID | Troballa | Estat integrat |
| --- | --- | --- |
| UC020-74 | Intenció CURS acceptava snapshot insuficient. | **CORREGIT CODI** amb `CourseIntentSnapshotValidator`. |
| UC020-75 | `SOURCE_ID` no es contrastava amb la inscripció del snapshot. | **CORREGIT CODI**. |
| UC020-76 | `IDPAG` no es contrastava amb el snapshot. | **CORREGIT CODI**. |
| UC020-77 | `EXPECTED_AMOUNT` no es contrastava amb l'import de pagament. | **CORREGIT CODI**. |
| UC020-78 | Snapshot de descompte podia arribar sense origen/mode coherent. | **CORREGIT per al contracte CURS nou**; es mantenen fallbacks històrics on pertoqui. |
| UC020-79 | Política AP no encapsulada ni versionada. | **TANCAT per AP v2** amb policy + historial; qualsevol canvi futur requereix nova RULE_VERSION. |
| UC020-80 | Manca orquestrador server-side d'operació/validació. | **IMPLEMENTAT I INTEGRAT AL PAGAMENT ACTIU** a `PrismaStudentCourseCheckoutService` via `/api/redsys/course-intent.php`; alta/preview/intranet pendents. |
| UC020-81 | Manca vincle runtime `UUID_OPERATION ↔ UUID_INTENT`. | **IMPLEMENTAT_NUCLI**; integració de canal i política de múltiples intents pendents. |
| UC020-82 | Invariant transversal factura vs cobrament. | **PENDENT TRANSVERSAL**; considerar fraccionaments. |
| UC020-83 | El primer resolver històric AP filtrava `HORES` com a columna separada i no replicava el selector web `(CURS=codi OR CURS=hores OR TOTS)`. | **CORREGIT CODI + TEST**; el pagament AP reconstrueix ara amb la semàntica llegada coneguda i continua fallant tancat davant múltiples coincidències. |

### 8.3. Decisions que eren pendents en aquest tall — fotografia històrica

A 30/09/2026 aquestes decisions encara estaven obertes. **Estat posterior:** `GENERAT=1`, factura abans de cobrar, autoacreditació, instant d'avaluació, no-acumulació AP+promoció i vigència del snapshot van quedar tancats a `UC20-DEC-001…006`; vegeu §11.2. El pagament AP fraccionat continua fail-closed fins a disposar d'un model fiscal explícit.

## 9. Evidència històrica del PR #54 i revalidació requerida

El tall original del PR #54 havia passat la suite MySQL amb **716 passed / 0 failed**. Després, el merge del runtime UC-020 al `main` (`a66b0afa19d61778dbb1fcd094fef9f954806f1e`) va executar `SIF PHP MySQL tests` run **#367** amb **727 passed / 0 failed**. Això acredita la integració del nucli i els tests específics existents, però no substitueix l'E2E navegador → callback → factura ni la preproducció.

## 10. Reconciliació post-merge — 02/10/2026

### 10.1. Canal actiu acreditat

El codi actual acredita la cadena:

`pagina_efectuar_pagament_automatic.php`
→ `SifRedsysCourseIntentClient`
→ `POST /api/redsys/course-intent.php`
→ `RedsysCoursePaymentIntentService`
→ (si `TIPUS_DESC=1`) `LegacyPrismaStudentPriceSnapshotResolver`
→ `PrismaStudentCourseCheckoutService`
→ `RedsysPaymentIntentService`.

Això reclassifica les troballes històriques que deien «adaptador de pagament pendent»: són vàlides com a fotografia anterior, però **ja no descriuen el runtime actual**.

### 10.2. Autoritat monetària

Per al pagament AP actiu:

- SIF rellegeix matrícula i saldo;
- la tarifa AP històrica es reconstrueix a `DATA_INSC` replicant la selecció llegada `CURS=codi | hores | TOTS` i `MES=edicio`;
- es rebutja una tarifa absent, ambigua o que no reprodueixi `A_PAGAR`;
- el snapshot fixa base/descompte/net;
- `CourseIntentSnapshotValidator` contrasta source, IDPAG i import;
- `pay.prisma.cat` utilitza l'`amount` retornat per la intenció SIF per a Redsys.

Continua obert el problema d'autoritat al **moment d'alta/preview** del llegat, abans que existeixi aquesta matrícula persistida.

### 10.3. Deute arquitectònic reduït en aquest tall

`PrismaStudentCourseCheckoutService` reutilitza ara `CommercialOperationRepository` i `DiscountValidationRepository` dins de la mateixa transacció que crea la intenció. No es delega directament a `CommercialOfferService` perquè aquest servei té el seu propi `TransactionRunner`; fer-ho així trencaria l'atomicitat operació/validació/intenció.

Continuen directes només les operacions específiques encara sense repository dedicat en aquest flux (p. ex. `commercial_operation_party` i transició d'estat), candidats a una refactorització posterior no bloquejant.

## 11. Passada final de tancament — 02/10/2026

### 11.1. Noves troballes i resolució

| ID | Troballa | Resolució |
| --- | --- | --- |
| UC020-84 | P05 encara es documentava com GET/sense CSRF, però el runtime havia evolucionat. | **TANCAT DOCUMENTACIÓ**: POST, sessió, permís, CSRF i `requestId` idempotent confirmats i UML reconciliat. |
| UC020-85 | L'orquestrador AP podia incloure matrícula actual i historial posterior. | **TANCAT CODI + TEST**: exclou `ID` actual i filtra `DATA_INSC <= evaluation_at`. |
| UC020-86 | La policy interpretava factura relacionada no nul·la com a elegible, però el SQL públic executable `!= NULL` no ho feia. | **TANCAT CODI + TEST**: `ALUMNE_PRISMA_WEB_LEGACY_V2`; factura només emesa no acredita. |
| UC020-87 | `enviarInscripcio.php` persistia AP i `A_PAGAR` provinents del navegador. | **TANCAT PER AP**: revalidació servidor d'historial i tarifes abans de l'INSERT. |
| UC020-88 | Promoció podia coexistir amb un estat client AP. | **TANCAT FAIL-CLOSED**: AP + promoció retorna 409. |
| UC020-89 | `calcularPreu.php` podia usar la llista de descomptes sense inicialitzar. | **TANCAT CODI**: inicialització i fallback segur. |
| UC020-90 | #112 havia preservat `operational_event` però reintroduït SQL directe ja encapsulat a #110. | **TANCAT CODI**: repositoris de party/intenció/estat recuperats. |
| UC020-91 | L'alta llegada completa continua sent GET amb PII i descomptes no-AP fora d'oferta SIF nativa. | **TRANSFERIT TRANSVERSAL**: hardening general de l'alta; ja no permet manipular AP després d'UC020-87. |
| UC020-92 | `payment_link` encara no és l'entrada canònica del checkout AP actiu. | **TRANSFERIT MIGRACIÓ**: el canal targeta actiu ja és server-authoritative via `course-intent`. |

### 11.2. Decisions UC20-DEC-001…006

Totes sis queden tancades a la fitxa v1.5 i materialitzades on afecten el runtime: `GENERAT=1` sí; factura només emesa no; no autoacreditació; `evaluation_at=DATA_INSC` per matrícula llegada; AP no acumulable amb promocions; snapshot AP persistit vàlid mentre la matrícula sigui pagable, amb caducitat del link separada.

### 11.3. Estat final de l'auditoria

**AUDIT_CLOSED** a 02/10/2026.

El tancament acredita exhaustivitat documental/codi per UC-020 i resolució o transferència explícita de totes les troballes. No substitueix el gate de desplegament: cal conservar una execució E2E navegador → callback Redsys → pagament → factura sobre entorn controlat abans de considerar el rollout productiu verificat.
### 11.4. Reconciliació de la branca alternativa PR #97

| ID | Troballa | Resolució |
| --- | --- | --- |
| UC020-93 | El PR #97 centralitza operació/participant/validació a `CommercialOfferService`, però `TransactionRunner::run()` sempre obre i commiteja una transacció pròpia. En aquell tall, l'oferta es confirma abans de crear la intenció i el vincle/estat es confirma en una transacció posterior. | **NO PORTAR AS-IS / TRANSFERIT REFACTOR**. #112 es manté canònic perquè conserva l'atomicitat operació → validació → intenció → vincle. Si es vol eliminar el writer específic d'UC-020, primer cal fer `CommercialOfferService` transaction-aware o introduir una unit of work compartida i revalidar concurrència/idempotència. |

Això no deixa una incògnita oberta d'UC-020: deixa una decisió arquitectònica explícita. El codi de #97 és una referència útil per al refactor, però no és segur fusionar-lo sobre el runtime final només per reduir duplicació.

## 12. Reauditoria sobre main — 03/10/2026

### 12.1. Inventari i cobertura

La reauditoria confirma que UC-020 disposa de totes les peces documentals exigides: fitxa funcional, UML integrat, classes ACTUAL/FINAL, seqüències ACTUAL/FINAL, activitats ACTUAL/FINAL per P01…P06, aquesta traçabilitat i la matriu AP-01…AP-84. No s'ha detectat cap categoria documental principal absent.

### 12.2. Troballes noves

| ID | Troballa | Estat 03/10/2026 |
| --- | --- | --- |
| UC020-94 | `enviarInscripcio.php` revalidava historial/tarifa AP al servidor, però després tornava a carregar `$preuDescompte` des del valor client abans de l'INSERT. | **TANCAT CODI + TEST**. Eliminada la reassignació tardana; `LegacyPrismaStudentEnrollmentAuthorityBoundaryTest` impedeix regressió. |
| UC020-95 | El `main` ha avançat 9 commits des de la base de #112, principalment en Redsys CURS/PACK, cutover, callback, worker, factura, sync i proves. | **REVALIDAT COMPATIBLE**. No altera la frontera AP: `RedsysCoursePaymentIntentService` continua derivant TIPUS_DESC=1 al checkout AP autoritatiu i el callback consumeix la intenció congelada. |
| UC020-96 | La documentació mantenia simultàniament estats antics “PENDENT_NEGOCI”, GET/sense CSRF i les decisions posteriors tancades. | **TANCAT DOCUMENTACIÓ**. Fitxa v1.6 i UML marquen explícitament fotografies històriques vs estat vigent. |\n| UC020-97 | `tipusCurs` arribava del navegador i podia activar el branch `S` que força `A_PAGAR=0`, fins i tot després de revalidar AP. | **TANCAT CODI + TEST**. `TIPUS_CURS` es deriva de `informacio` al servidor i la prova de frontera impedeix recuperar l'autoritat client. |
| UC020-98 | El preview AP conservava una branca `FACTURA_RELACIONADA != NULL` morta però semànticament contrària a la policy v2. | **TANCAT CODI + TEST**. Eliminada la branca; el preview declara i executa els mateixos criteris d'elegibilitat v2 rellevants. |

### 12.3. Verificació

**Verificat per inspecció de codi:** policy v2, exclusió de matrícula actual, tall temporal d'historial, resolver històric fail-closed, alta AP revalidada, checkout AP server-authoritative, intenció/callback congelats, intranet POST+CSRF+permís+requestId.

**Verificat per proves automatitzades existents:** policy, resolver, checkout AP, intent AP, callback/curs E2E simulat, idempotència i diverses fronteres de cutover. El head anterior de #112 tenia les suites de GitHub Actions en `success`.

**Pendent de verificació de rollout:** navegador real → pàgina de pagament → Redsys/callback → worker → factura en entorn controlat/preproducció; configuració efectiva de flags de cutover; migració canònica a `payment_link` i unificació de transferència.

### 12.4. Resultat

`AUDIT_CLOSED_REVALIDATED_2026-10-03`. UC020-94 era una regressió funcional real que impedia considerar l'alta AP completament server-authoritative; queda corregida abans de la revalidació final.
