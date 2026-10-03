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
| Fitxa funcional UC-020 | existent, genèrica v1.1 | actualitzada v1.5 amb runtime integrat i pendents reals |
| UML integrat UC-020 | existent, sobretot FINAL/SIF | actualitzat amb ACTUAL + FINAL |
| Classes UC-020 | parcials dins UML | `uc-020-classes-actual-final.md` + resum integrat |
| Seqüències UC-020 | una seqüència mixta | `uc-020-sequencies-actual-final.md` + resum integrat |
| Activitats per pàgina | **no existia dossier específic** | creat `uc-020-activitats-pagines-actual-final.md` |
| Traçabilitat d'auditoria | dispersa | aquest document |
| Matriu AP-01…AP-84 | dispersa/incompleta | creada `uc-020-matriu-proves-ap-01-84.md` |
| Runtime `commercial_operation` | només DDL | `CommercialOperationRepository` + `CommercialOfferService` + orquestrador UC-020 |
| Runtime `discount_validation` | només DDL | `DiscountValidationRepository` + `CommercialOfferService` + orquestrador UC-020 |
| Runtime `payment_link` | només DDL | `PaymentLinkRepository` + `PaymentLinkService`; infraestructura disponible, no obligatòria al camí directe `course-intent` |
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

### 7.3. Buits que continuen oberts

1. `PrismaStudentDiscountPolicy` ja existeix sota `ALUMNE_PRISMA_LEGACY_V1`; continuen pendents de ratificació `UC20-DEC-001…006` i qualsevol canvi requerirà una nova versió.
2. El **pagament real** ja està integrat: `pay.prisma.cat → SifRedsysCourseIntentClient → /api/redsys/course-intent.php → RedsysCoursePaymentIntentService → PrismaStudentCourseCheckoutService`.
3. Continua pendent que l'alta/preview web deixi de persistir `TIPUS_DESC/A_PAGAR` prenent els valors del navegador com a autoritat; el pagament posterior ara revalida preu/estat i falla tancat si no coincideix.
4. `payment_link` és infraestructura disponible però no és requisit del camí directe de CURS actual; si s'incorpora al flux AP caldrà coordinar-ne revocació/substitució amb la política de múltiples intents.
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
| `PrismaStudentCourseCheckoutService` | IMPLEMENTAT_PAGAMENT_REAL | Orquestra historial → policy → operació/validació/participant → snapshot → intenció → vincle d'intent i és invocat pel canal real de pagament. |
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
| UC020-79 | Política AP no encapsulada ni versionada. | **PARCIALMENT TANCAT** amb policy + historial; negoci futur pendent. |
| UC020-80 | Manca orquestrador server-side d'operació/validació. | **TANCAT EN PAGAMENT**: implementat i connectat a `course-intent`; continua pendent server-authority en alta/preview. |
| UC020-81 | Manca vincle runtime `UUID_OPERATION ↔ UUID_INTENT`. | **TANCAT EN PAGAMENT**: el vincle s'escriu i es protegeix contra reassociació incompatible; política avançada de múltiples intents pendent. |
| UC020-82 | Invariant transversal factura vs cobrament. | **PENDENT TRANSVERSAL**; considerar fraccionaments. |

### 8.3. Decisions que continuen pendents

La implementació no modifica silenciosament la política de negoci. Es mantenen pendents, entre altres, pagament parcial com a prova, `GENERAT=1`, factura abans de cobrar, autoacreditació de la matrícula actual, prioritat amb altres descomptes i vigència temporal de l'oferta.

## 9. Evidència històrica del PR #54 i revalidació requerida

El tall original del PR #54 havia passat els tres workflows i la suite MySQL amb **716 passed / 0 failed**. Aquesta evidència és històrica del commit anterior a l'actualització amb `main`.

Després d'integrar el `main` actual, el criteri per autoritzar el merge és tornar a executar els workflows sobre el nou HEAD i exigir-los verds. L'E2E navegador → oferta/operació server-side → Redsys → factura i la preproducció continuen fora de l'abast d'aquesta evidència.


## 10. Reconciliació sobre `main` — 03/10/2026

### 10.1. Estat real del canal de pagament

La revisió del `main` integrat confirma que el pagament de curs ja recorre:

```text
pay.prisma.cat
  -> SifRedsysCourseIntentClient
  -> POST signat /api/redsys/course-intent.php
  -> RedsysCoursePaymentIntentService
  -> [TIPUS_DESC=1] PrismaStudentCourseCheckoutService
  -> LegacyPrismaStudentPriceSnapshotResolver
  -> commercial_operation / discount_validation / commercial_operation_party
  -> RedsysPaymentIntentService
  -> redsys_payment_intent
```

Per tant, ja no és correcte etiquetar com a pendent la connexió de l'orquestrador AP amb el **checkout de pagament real**.

### 10.2. Autoritat del navegador: pendent acotat

La mancança de trust boundary continua existint **abans del pagament**, a l'alta/preview llegat: el navegador encara envia `tipusDescompte`, `preuCar` i `preuDescompte` a `enviarInscripcio.php`.

El pagament AP posterior, però, ja no confia cegament en aquests imports: recupera `TIPUS_DESC/VALID_DESC`, reconstrueix la tarifa històrica aplicable a `DATA_INSC` i exigeix que el net autoritatiu coincideixi amb `A_PAGAR`. Una matrícula manipulada/incoherent queda **fail-closed** abans de crear la intenció.

### 10.3. Refactor de persistència compartida

`PrismaStudentCourseCheckoutService` mantenia SQL propi sobre les mateixes taules que ja disposaven de repositories genèrics. En aquest tall s'ha refactoritzat perquè reutilitzi:

- `CommercialOperationRepository`;
- `DiscountValidationRepository`;
- nou `CommercialOperationPartyRepository`;
- `CommercialOperationRepository::linkIntent()`;
- `CommercialOperationRepository::updateStatus()`.

Això preserva la transacció única UC-020 i evita usar `CommercialOfferService` dins d'una transacció ja oberta, cosa que provocaria transaccions niades amb el `TransactionRunner` actual.

### 10.4. Baseline CI abans d'aquest tall

El `main` base `b0e8ff7150c5a8b415cc109d298d82f0db1f68df` ja estava en vermell abans del refactor UC-020:

- **SIF PHP MySQL tests #1250:** 917 passed / 6 failed.
- **UC-111 integration verification #1143:** mateix conjunt de 6 fallades.

Les 6 fallades observades són de límits PACK/privacitat i una expectativa de `RedsysSignatureValidatorTest`; els tests de `RedsysPaymentIntent` relacionats amb coherència CURS/import continuen passant. Per validar aquest tall cal exigir **cap fallada nova UC-020** respecte d'aquest baseline, encara que el repositori global continuï vermell per incidències alienes.

### 10.5. Pendents reals després de la reconciliació

1. Server-authority a l'alta/preview web abans de persistir la matrícula.
2. Decisions de negoci `UC20-DEC-001…006` i nova `RULE_VERSION` quan es ratifiquin.
3. Model explícit de fraccionament/reanudació per Alumne PrisMa; avui falla tancat.
4. E2E historial → alta → pagament → callback → factura amb `DESC_ORIGEN=ALUMNE_PRISMA`.
5. Evidència sobre `sif_test/sif_pre` i acceptació operativa.
