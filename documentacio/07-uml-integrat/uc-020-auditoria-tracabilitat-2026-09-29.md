# UC-020 — Auditoria consolidada i matriu de traçabilitat

**Data d'auditoria base:** 29/09/2026  
**Integració documental revisada:** 30/09/2026  
**Abast:** síntesi de troballes contrastades durant l'auditoria del UC-020 i destinació documental/implementació.  
**Mètode:** lectura estàtica del repositori. Les proves proposades no s'han executat en aquesta auditoria.

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
| UC020-48 | No s'ha localitzat runtime PHP per `commercial_operation`/`discount_validation`/`payment_link`. | VERIFICAT REPO | FINAL |
| UC020-49 | Tests comercials actuals verifiquen sobretot esquema; no UC-20 E2E. | VERIFICAT | Proves |
| UC020-50 | `payment_link` DDL ja modela import esperat, estat, expiració, revocació i substitució. | VERIFICAT DDL | FINAL |
| UC020-51 | `redsys_payment_intent` no conté UUID_OPERATION; l'enllaç runtime no està acreditat. | VERIFICAT | FINAL |
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
| UC020-69 | Resolució intranet usa GET amb efectes persistents. | VERIFICAT | P05 |
| UC020-70 | No s'ha localitzat protecció CSRF explícita per aquesta comanda. | VERIFICAT REPO | P05/seguretat |
| UC020-71 | Endpoint de comanda no inclou `comprovarSessio.php`; confia en objectes de sessió existents. | VERIFICAT | P05 |
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
- `CommercialOfferService`: crea/reutilitza transaccionalment `commercial_operation` + `commercial_operation_party` + `discount_validation`, valida aritmètica `gross-discount=net` i registra `COMMERCIAL_OFFER_CREATED` a `operational_event`.
- `PaymentLinkService`: genera token opac, només persisteix SHA-256, comprova import màxim respecte del net, expiració, revocació i resolució del link.

### 7.2. Tests nous

- `CommercialOfferServiceTest`: creació, reús idempotent, parts comercials, lifecycle declarat, conflicte de payload/clau i aritmètica inconsistent.
- `PaymentLinkServiceTest`: token/hash, resolució, import superior al net, expiració, revocació i idempotència de revocació.

Aquests tests tenen evidència CI posterior al refactor del PR #97; vegeu la secció 10.

### 7.3. Buits que continuen oberts

1. `PrismaStudentDiscountPolicy` ja existeix sota `ALUMNE_PRISMA_LEGACY_V1`; continuen pendents de ratificació `UC20-DEC-001…006` i qualsevol canvi requerirà una nova versió.
2. Alta/confirmació web i intranet encara no consumeixen una oferta servidor autoritativa; el camí de targeta sí que consumeix `PrismaStudentCourseCheckoutService` via `course-intent`.
3. Substitució de les rutes llegades de confirmació/pagament per `PaymentLinkService` i/o operació servidor autoritativa.
4. El nucli `PrismaStudentCourseCheckoutService → CommercialOfferService → RedsysPaymentIntentService → commercial_operation.UUID_INTENT` està implementat i integrat al canal real de targeta; resta coordinar-lo amb `payment_link`/transferència i altres canals.
5. Política completa de múltiples intents Redsys sobre una mateixa operació i substitució/revocació de links.
6. E2E historial → oferta/operació AP → intent → callback → factura i evidència de preproducció.


## 8. Tall executable UC-020 integrat — 30/09/2026

### 8.1. Codi específic incorporat

| Peça | Estat | Finalitat |
| --- | --- | --- |
| `PrismaStudentDiscountPolicy` | IMPLEMENTAT_COMPATIBILITAT | Reprodueix la regla web sota `ALUMNE_PRISMA_LEGACY_V1` i retorna evidència concreta sense tancar decisions futures. |
| `LegacyPrismaStudentHistoryRepository` | IMPLEMENTAT | Recupera fets d'historial per document sense decidir elegibilitat. |
| `CourseIntentSnapshotValidator` | IMPLEMENTAT | Valida source, inscripció, IDPAG, import i coherència del descompte per intencions CURS. |
| `LegacyPrismaStudentPriceSnapshotResolver` | IMPLEMENTAT | Obté snapshot de preu autoritatiu des de dades llegades. |
| `PrismaStudentCourseCheckoutService` | IMPLEMENTAT_CARD_CHECKOUT_VERIFICAT | Orquestra historial → policy → `CommercialOfferService` → operació/participant/validació → snapshot → intenció → vincle/lifecycle. |
| `RedsysCoursePaymentIntentService` / `RedsysPaymentIntentService` | MODIFICAT | Consumeixen i validen el contracte CURS/AP abans del TPV. |

Des del PR #97 aquesta capa ja no manté un writer SQL paral·lel: **reutilitza** la infraestructura comercial general de 7.1. `PaymentLinkService` continua sent infraestructura disponible però encara no forma part del camí AP de targeta.

### 8.2. Incidències resoltes o reduïdes

| ID | Troballa | Estat integrat |
| --- | --- | --- |
| UC020-74 | Intenció CURS acceptava snapshot insuficient. | **CORREGIT CODI** amb `CourseIntentSnapshotValidator`. |
| UC020-75 | `SOURCE_ID` no es contrastava amb la inscripció del snapshot. | **CORREGIT CODI**. |
| UC020-76 | `IDPAG` no es contrastava amb el snapshot. | **CORREGIT CODI**. |
| UC020-77 | `EXPECTED_AMOUNT` no es contrastava amb l'import de pagament. | **CORREGIT CODI**. |
| UC020-78 | Snapshot de descompte podia arribar sense origen/mode coherent. | **CORREGIT per al contracte CURS nou**; es mantenen fallbacks històrics on pertoqui. |
| UC020-79 | Política AP no encapsulada ni versionada. | **PARCIALMENT TANCAT** amb policy + historial; negoci futur pendent. |
| UC020-80 | Manca orquestrador server-side d'operació/validació. | **IMPLEMENTAT_CARD_CHECKOUT_VERIFICAT** a `PrismaStudentCourseCheckoutService` reutilitzant `CommercialOfferService`; alta web/intranet pendents. |
| UC020-81 | Manca vincle runtime `UUID_OPERATION ↔ UUID_INTENT`. | **IMPLEMENTAT_CARD_CHECKOUT_VERIFICAT** amb `CommercialOperationRepository::linkIntent()` + control de DS_ORDER; política de múltiples intents d'altres canals pendent. |
| UC020-82 | Invariant transversal factura vs cobrament. | **PENDENT TRANSVERSAL**; considerar fraccionaments. |
| UC020-83 | UC-020 mantenia un writer SQL propi de `commercial_operation`, `commercial_operation_party` i `discount_validation` en paral·lel al runtime compartit. | **TANCAT PR #97**: `PrismaStudentCourseCheckoutService` delega a `CommercialOfferService` i repositoris. |
| UC020-84 | La transició `READY_FOR_PAYMENT → INTENT_CREATED` no genera encara un `operational_event` específic. | **PENDENT AUDITORIA LIFECYCLE**; l'event de creació d'oferta sí està implementat. |

### 8.3. Decisions que continuen pendents

La implementació no modifica silenciosament la política de negoci. Es mantenen pendents, entre altres, pagament parcial com a prova, `GENERAT=1`, factura abans de cobrar, autoacreditació de la matrícula actual, prioritat amb altres descomptes i vigència temporal de l'oferta.

## 9. Evidència històrica del PR #54 i revalidació requerida

El tall original del PR #54 havia passat els tres workflows i la suite MySQL amb **716 passed / 0 failed**. Aquesta evidència és històrica del commit anterior a l'actualització amb `main`.

Després d'integrar el `main` actual, el criteri per autoritzar el merge és tornar a executar els workflows sobre el nou HEAD i exigir-los verds. L'E2E navegador → oferta/operació server-side → Redsys → factura i la preproducció continuen fora de l'abast d'aquesta evidència.


## 10. Revalidació PR #97 — runtime comercial compartit — 02/10/2026

El PR #97 substitueix el writer SQL específic d'Alumne PrisMa per la infraestructura comercial comuna.

### 10.1. Canvis verificats

- nou `CommercialOperationPartyRepository`;
- `CommercialOfferService` persisteix operació + participant + validació en la mateixa transacció i registra `COMMERCIAL_OFFER_CREATED`;
- el reús idempotent només admet els lifecycle states declarats explícitament;
- `PrismaStudentCourseCheckoutService` deixa de fer SQL directe sobre les taules comercials;
- `CommercialOperationRepository::transitionStatus()` formalitza la transició idempotent;
- `RedsysPaymentIntentRepository::findByUuid()` permet verificar la intenció ja vinculada;
- els timestamps d'una `discount_validation` creada pel writer anterior es reutilitzen per compatibilitat.

### 10.2. Evidència CI

Commit de codi verificat: `83b44eb98f4edfc468e90a14b6f9e2462ab2c47d`.

| Workflow | Run | Resultat |
| --- | ---: | --- |
| SIF PHP MySQL tests | 617 | **841 passed · 0 failed** |
| SIF checks | 343 | SUCCESS |
| UC-111 integration verification | 483 | SUCCESS |
| UC-004 SIF secure flow checks | 129 | SUCCESS |

### 10.3. Estat després del refactor

**Implementat i verificat:** policy de compatibilitat, historial, reconstrucció històrica de preu, oferta/participant/validació compartida, snapshot CURS, intenció Redsys de targeta, vincle d'intent i reintents idempotents.

**Continua pendent:** alta/confirmació web autoritativa (encara rep imports/tipus del navegador), intranet, integració `payment_link`/transferència, event específic de la transició d'intent, E2E complet fins factura i evidència de preproducció.
