# UC-020 — Matriu canònica de proves AP-01…AP-84

**Data de consolidació:** 03/10/2026  
**Abast:** UC-020 · Aplicar Alumne PrisMa, incloses dependències directes amb UC-116, canvi de curs, pagament i construcció fiscal.  
**Estat:** pla de proves. Cap cas passa a VERIFICAT sense execució registrada sobre commit/entorn concret.

> AP-01…AP-48 consoliden els escenaris recuperats de les diferents passades de l'auditoria. AP-49…AP-84 corresponen a la consolidació posterior. Les decisions UC20-DEC-001…006 estan tancades des del 02/10/2026; per això els antics `PENDENT_NEGOCI` es reclassifiquen com a cobertura de test o pendent d'execució/migració.

## Llegenda

- **UNIT**: servei/validador sense necessitat de flux complet.
- **INT**: integració amb BD SIF o adaptador llegat.
- **E2E**: recorregut transversal.
- **SEC**: seguretat/autorització.
- **CONC**: concurrència/idempotència.
- **PENDENT_NEGOCI**: etiqueta històrica; no s'ha de mantenir per UC20-DEC-001…006 després del 02/10/2026.
- **PENDENT_EXECUCIO**: especificat però no executat/acreditat.
- **VERIFICAT_CI_***: escenari amb test executat en PASS al commit indicat; el sufix especifica si és unitat, integració, frontera de codi o servei compartit.
- **TEST_NOU_PENDENT_CI_***: prova explícita creada a la branca però encara sense execució CI observada; no equival a verificat.

## AP-01…AP-16 · elegibilitat, preu i integritat bàsica

| ID | Nivell | Escenari | Resultat esperat | Estat |
| --- | --- | --- | --- | --- |
| AP-01 | INT | Persona sense cap historial admissible. | No aplicar Alumne PrisMa; conservar tarifa ordinària o altra oferta vàlida. | TEST_NOU_PENDENT_CI_POLICY |
| AP-02 | INT | Existeix una inscripció anterior pagada i una tarifa AP vigent. | Aplicar la tarifa AP definida per la política aprovada i conservar-ne origen/regla. | VERIFICAT_CI_CHECKOUT_0c1825c |
| AP-03 | INT | L'únic antecedent té un pagament parcial positiu. | És elegible sota `ALUMNE_PRISMA_WEB_LEGACY_V2` perquè `A_PAGAR>0 && PAGAMENT>0`. | VERIFICAT_CI_UNIT_POLICY_9a70516 |
| AP-04 | INT | L'únic antecedent és un curs regal. | És elegible i conserva motiu/evidència `GIFT_COURSE`. | VERIFICAT_CI_UNIT_POLICY_9a70516 |
| AP-05 | INT | L'historial disponible està en estat exclòs `INSC CURS=D/M`. | No usar aquests registres com a antecedent AP en coherència amb l'ACTUAL reconstruït. | VERIFICAT_CI_UNIT_POLICY_9a70516 |
| AP-06 | INT | Historial admès + `TIPUS=1` vigent per curs/edició. | Retornar preu AP exacte i origen `ALUMNE_PRISMA`. | VERIFICAT_CI_CHECKOUT_I_RESOLVER_0c1825c |
| AP-07 | INT | Cap registre de l'historial compleix les condicions. | No seleccionar `TIPUS=1`. | VERIFICAT_CI_INTEGRACIO_CHECKOUT_9a70516 |
| AP-08 | INT | Antecedent amb `A_PAGAR>0` i `PAGAMENT>0` però no totalment cobrat. | És elegible segons la rule v2 de compatibilitat. | VERIFICAT_CI_UNIT_POLICY_9a70516 |
| AP-09 | INT | Antecedent de curs regal i variant de factura relacionada sense pagament. | Curs regal acredita; factura només emesa/no cobrada no acredita. | VERIFICAT_CI_UNIT_POLICY_9a70516 |
| AP-10 | INT | Historial amb registres D/M i registres normals. | Només els antecedents admissibles poden justificar AP. | TEST_NOU_PENDENT_CI_POLICY |
| AP-11 | INT | Persona elegible però no existeix cap tarifa AP aplicable. | Estat `ELIGIBLE_NO_PRICE`/incidència equivalent; cap import nul o inventat pagable. | TEST_NOU_PENDENT_CI_FAIL_CLOSED |
| AP-12 | INT | Coincideixen AP i promoció. | AP + promoció falla tancat en l'alta AP; no s'acumulen silenciosament. | VERIFICAT_CI_FRONTERA_CODI_9a70516 |
| AP-13 | SEC | El client manipula `tipusDescompte`, `preuDescompte` o `tipusCurs`. | Per AP, el servidor rellegeix elegibilitat/tarifa i deriva `TIPUS_CURS` de metadades servidor; el navegador no és autoritat monetària. | VERIFICAT_CI_FRONTERA_CODI_9a70516 |
| AP-14 | CONC | La tarifa canvia entre previsualització i confirmació. | Confirmar només una oferta servidor vigent o retornar conflicte; no acceptar TOCTOU silenciós. | PENDENT_EXECUCIO |
| AP-15 | E2E | Oferta AP → cobrament → línia fiscal. | Base − descompte = net; import cobrat i línia fiscal són coherents amb el snapshot congelat. | PENDENT_EXECUCIO |
| AP-16 | CONC | Reintent equivalent de la mateixa operació. | Reutilitzar decisió/operació; no duplicar descompte, cobrament ni factura. | VERIFICAT_CI_INTEGRACIO_CHECKOUT_9a70516 |

## AP-17…AP-25 · denegació, canvis posteriors i fiscalitat

| ID | Nivell | Escenari | Resultat esperat | Estat |
| --- | --- | --- | --- | --- |
| AP-17 | INT | Hi ha tarifa AP futura i encara no vigent. | No seleccionar-la abans de `DATAI`. | TEST_NOU_PENDENT_CI_TEMPORAL |
| AP-18 | INT | Persona elegible però el preu AP no es pot obtenir. | Bloquejar actualització/oferta i registrar incidència. | TEST_NOU_PENDENT_CI_FAIL_CLOSED |
| AP-19 | E2E | Es denega el descompte original després d'un cobrament. | Conservar el moviment real i classificar l'ajust econòmic; no sobreescriure el passat. | PENDENT_EXECUCIO |
| AP-20 | E2E | Es resol el dret després d'haver emès factura. | Preservar factura original i derivar l'ajust fiscal/rectificatiu que correspongui. | PENDENT_EXECUCIO |
| AP-21 | INT | Historial amb antecedents anteriors i posteriors a `DATA_INSC`. | Excloure matrícula actual i historial posterior a `DATA_INSC`. | VERIFICAT_CI_INTEGRACIO_CHECKOUT_9a70516 |
| AP-22 | CONC | Dues peticions comercials simultànies intenten actualitzar la mateixa oferta. | Una versió vàlida; l'altra reutilitza o rep conflicte, sense sobreescriptura desfasada. | PENDENT_EXECUCIO |
| AP-23 | SEC | Usuari autenticat sense permís específic intenta resoldre el descompte. | Denegació al servidor i cap canvi econòmic. | VERIFICAT_CI_FRONTERA_P05_0c1825c |
| AP-24 | INT | Snapshot monetàriament coherent però sense origen/regla AP. | No atribuir-lo arbitràriament a Alumne PrisMa ni a una promoció genèrica. | VERIFICAT_CI_INTENT_ORIGIN_GUARD_0c1825c |
| AP-25 | E2E | Operació AP amb pagament fraccionat. | Separar preu net total, descompte i cadascun dels cobraments; cap fracció és el total de l'oferta. | PENDENT_IMPLEMENTACIO · FAIL_CLOSED_VERIFICAT_CI_0c1825c |

## AP-26…AP-35 · promocions, confirmació i circuit bancari

| ID | Nivell | Escenari | Resultat esperat | Estat |
| --- | --- | --- | --- | --- |
| AP-26 | INT | Aplicar codi promocional després d'haver calculat AP. | Tipus/origen registrat coincideix amb l'import final realment aplicat. | PENDENT_EXECUCIO |
| AP-27 | INT | Canviar d'edició amb tarifes diferents. | L'oferta usa la tarifa exacta de l'edició final. | PENDENT_EXECUCIO |
| AP-28 | CONC | Dues confirmacions simultànies de la mateixa inscripció. | No duplicar inscripció ni operació comercial. | PENDENT_EXECUCIO |
| AP-29 | CONC | Dues altes simultànies requereixen nou `IDPAG`. | Identificadors inequívocs; cap col·lisió de l'allocator. | IMPLEMENTAT_GET_LOCK · TEST_EXISTENT_LEGACY_IDPAG · PENDENT_CI_HEAD |
| AP-30 | INT | Error tècnic durant el càlcul de preu. | Cap confirmació amb oferta indeterminada; resposta estructurada d'error. | PENDENT_EXECUCIO |
| AP-31 | INT | Descompte pendent o denegat en obrir confirmació/pagament. | Pantalles mostren el mateix estat comercial i només mètodes autoritzats. | IMPLEMENTAT_BOUNDARY_VALID_DESC · TEST_NOU_PENDENT_CI |
| AP-32 | SEC | Manipular l'import enviat al formulari de pagament. | El servidor usa import autoritzat/persistent, no el valor manipulat. | TEST_NOU_PENDENT_CI_AMOUNT_AUTHORITY |
| AP-33 | E2E | Callback amb ordre o import diferent de la intenció. | Rebuig/incidència; cap cobrament atribuït incorrectament. | VERIFICAT_CI_AMOUNT_MISMATCH_0c1825c · TEST_ORDER_PENDENT_CI |
| AP-34 | SEC | Callback amb signatura invàlida. | Rebuig abans de registrar cobrament. | VERIFICAT_CI_UNSIGNED_BOUNDARY_0c1825c · TEST_V2_INVALID_PENDENT_CI |
| AP-35 | CONC | Mateixa inscripció/oferta amb dues ordres bancàries. | Tractar intents sense duplicar benefici comercial ni factura; política de reintent explícita. | VERIFICAT_CI_SECOND_DS_ORDER_CONFLICT_0c1825c |

## AP-36…AP-48 · errors de selector, notificacions i ajustos

| ID | Nivell | Escenari | Resultat esperat | Estat |
| --- | --- | --- | --- | --- |
| AP-36 | INT | Cap tarifa disponible i Carnet Jove marcat. | Resposta controlada; cap accés a arrays/índexs inexistents. | TEST_NOU_PENDENT_CI_SAFE_PREVIEW |
| AP-37 | INT | Persona AP aplica després un codi promocional. | Origen comercial, import cobrat i origen fiscal continuen alineats. | PENDENT_EXECUCIO |
| AP-38 | INT | Simple consulta/previsualització de preu. | No crear factura, cobrament ni UUID fiscal. | TEST_NOU_PENDENT_CI_READ_ONLY_PREVIEW |
| AP-39 | INT | L'únic antecedent possible és la mateixa inscripció. | No autoacreditar AP. | COBERT_INTEGRACIO_CHECKOUT |
| AP-40 | INT | Oferta AP d'una edició s'intenta usar en una altra. | Revalidació o conflicte segons política; mai trasllat silenciós. | PENDENT_EXECUCIO |
| AP-41 | INT | Snapshot amb imports vàlids però origen comercial incorrecte. | Detectar contradicció abans de crear intenció o factura. | VERIFICAT_CI_INTENT_ORIGIN_GUARD_0c1825c |
| AP-42 | INT | La tarifa canvia després d'haver congelat una oferta AP. | El snapshot comercial congelat no es reescriu; la migració completa de la intranet a oferta SIF continua pendent. | PENDENT_EXECUCIO_TRANSVERSAL |
| AP-43 | E2E | Denegació documental + persona AP, sense pagaments previs. | Mateixa inscripció, tarifa AP i `VALID_DESC=1`; pagament habilitat. | IMPLEMENTAT_P05_BOUNDARY · TEST_NOU_PENDENT_CI · E2E_PENDENT |
| AP-44 | E2E | Mateix cas amb cobrament parcial previ. | Correu i UI distingeixen total/cobrat/pendent; cap cobrament duplicat pel total. | PENDENT_EXECUCIO |
| AP-45 | CONC | Dos operadors resolen la mateixa sol·licitud de forma contradictòria. | Una sola resolució sobre la versió esperada; l'altra rep conflicte. | PENDENT_EXECUCIO |
| AP-46 | E2E | La decisió es desa però falla SMTP. | No repetir mutació comercial; notificació queda fallida/pendent de reintent. | PENDENT_EXECUCIO |
| AP-47 | E2E | Denegació + AP i consulta de confirmació/pagament. | Mateixa autorització `VALID_DESC=1` per targeta i transferència. | IMPLEMENTAT_BOUNDARY · TEST_NOU_PENDENT_CI · E2E_PENDENT |
| AP-48 | E2E | La inscripció ja està facturada quan es denega/canvia el descompte. | No reescriure factura; derivar ajust econòmic/fiscal. | PENDENT_EXECUCIO |

## AP-49…AP-55 · estat de pagament i links

| ID | Nivell | Escenari | Resultat esperat | Estat |
| --- | --- | --- | --- | --- |
| AP-49 | INT | Descompte documental pendent a confirmació. | No oferir targeta ni transferència. | PENDENT_EXECUCIO |
| AP-50 | SEC | Pendent documental + accés directe a link de pagament. | Link vàlid no autoritza cobrament si l'oferta no és pagable. | IMPLEMENTAT_PAYMENT_LINK_GATE · TEST_NOU_PENDENT_CI |
| AP-51 | E2E | Denegació + AP elegible. | Nova oferta AP pagable coherent. | PENDENT_EXECUCIO |
| AP-52 | E2E | Obrir el link enviat després de denegació + AP. | Correu i mètodes visibles no es contradiuen. | PENDENT_EXECUCIO |
| AP-53 | SEC | Token de pagament manipulat. | Rebuig per integritat/hash. | TEST_NOU_PENDENT_CI_TOKEN_HASH |
| AP-54 | SEC | Token correcte però oferta no pagable. | Bloqueig comercial. | IMPLEMENTAT_PAYMENT_LINK_GATE · TEST_NOU_PENDENT_CI |
| AP-55 | CONC | Repetir resolució després de crear oferta alternativa. | Reutilització; cap segona decisió/notificació. | PENDENT_EXECUCIO |

## AP-56…AP-64 · operació comercial SIF

| ID | Nivell | Escenari | Resultat esperat | Estat |
| --- | --- | --- | --- | --- |
| AP-56 | INT | Crear oferta AP. | Crear `commercial_operation` + `discount_validation` coherents. | VERIFICAT_CI_SERVEI_COMERCIAL_9a70516 |
| AP-57 | CONC | Repetir exactament la mateixa creació. | Mateixa operació/validació per idempotència. | VERIFICAT_CI_SERVEI_COMERCIAL_9a70516 |
| AP-58 | INT | Canviar tarifa després d'acceptar oferta. | `PRICE_SNAPSHOT_JSON` original roman immutable. | TEST_NOU_PENDENT_CI_SNAPSHOT_IMMUTABLE |
| AP-59 | INT | Revocar link i substituir oferta/link. | Link antic `REVOKED`; nou link separat. | VERIFICAT_CI_SERVEI_PAYMENT_LINK_9a70516 |
| AP-60 | SEC | Utilitzar link revocat. | Rebuig abans de TPV. | VERIFICAT_CI_SERVEI_PAYMENT_LINK_9a70516 |
| AP-61 | INT | `EXPECTED_AMOUNT` del link supera/incompleix l'operació. | Conflicte abans de crear cobrament. | VERIFICAT_CI_SERVEI_PAYMENT_LINK_9a70516 |
| AP-62 | INT | Crear intenció Redsys des d'operació. | `UUID_INTENT` queda vinculat explícitament a `UUID_OPERATION`. | VERIFICAT_CI_INTEGRACIO_CHECKOUT_9a70516 |
| AP-63 | CONC | Mateix `DS_ORDER` amb snapshot comercial diferent. | Conflicte idempotent. | COBERT_PER_TEST_EXISTENT_REDSYS |
| AP-64 | INT | Dret original REJECTED + AP ACCEPTED. | Dues decisions diferenciades; una única oferta actual. | PENDENT_IMPLEMENTACIO |

## AP-65…AP-73 · divergències d'elegibilitat

| ID | Nivell | Escenari | Resultat esperat | Estat |
| --- | --- | --- | --- | --- |
| AP-65 | INT | Únic antecedent `GENERAT=1`, sense cobrament. | És elegible sota rule v2. | VERIFICAT_CI_UNIT_POLICY_9a70516 |
| AP-66 | INT | Única evidència = inscripció actual, encara que `GENERAT=1` o tingui estat favorable. | No autoacreditar. | VERIFICAT_CI_INTEGRACIO_CHECKOUT_9a70516 |
| AP-67 | INT | Antecedent posterior a `DATA_INSC` de la matrícula tarifada. | No acreditar retroactivament. | VERIFICAT_CI_INTEGRACIO_CHECKOUT_9a70516 |
| AP-68 | INT | Factura relacionada sense cobrament. | No acredita AP per si sola; no dependre de `!= NULL`. | VERIFICAT_CI_POLICY_I_FRONTERA_9a70516 |
| AP-69 | INT | Mateix `ID_PREU` amb tarifes AP específiques de curs/mes. | Tots els canals seleccionen la tarifa canònica exacta. | PENDENT_EXECUCIO |
| AP-70 | INT | Tarifa AP actual + futura. | Només la vigent és seleccionable. | TEST_NOU_PENDENT_CI_TEMPORAL |
| AP-71 | INT | Elegible sense tarifa AP. | `ELIGIBLE_NO_PRICE`; no oferta pagable. | COBERT_PARCIAL_TEST_NOU_PENDENT_CI |
| AP-72 | INT | Canvi de curs amb tarifa específica per curs. | No recuperar una fila d'un altre curs per filtre incomplet. | IMPLEMENTAT_P06 · TEST_NOU_PENDENT_CI |
| AP-73 | E2E | Mateixa persona/operació per web i intranet. | Mateixa política versionada i mateixa justificació del dret. | PENDENT_IMPLEMENTACIO |

## AP-74…AP-84 · concurrència, seguretat i contractes

| ID | Nivell | Escenari | Resultat esperat | Estat |
| --- | --- | --- | --- | --- |
| AP-74 | CONC | DNI A lent → DNI B ràpid. | Resposta A obsoleta no pot sobreescriure l'oferta B. | IMPLEMENTAT_UI_GENERATION_GUARD · TEST_NOU_PENDENT_CI |
| AP-75 | CONC | Canviar edició amb càlcul pendent. | Només queda activa l'oferta de l'edició final. | IMPLEMENTAT_UI_GENERATION_GUARD · TEST_NOU_PENDENT_CI |
| AP-76 | CONC | Marcar/desmarcar check de descompte ràpidament. | Cap combinació de tipus/import de moments diferents. | IMPLEMENTAT_UI_GENERATION_GUARD · TEST_NOU_PENDENT_CI |
| AP-77 | INT | AP calculat → aplicar codi promocional. | L'oferta final té un únic origen coherent. | IMPLEMENTAT_UI_ORIGIN_EXCLUSIVE + SERVER_FAIL_CLOSED · TEST_NOU_PENDENT_CI |
| AP-78 | CONC | Promoció aplicada → arriba resposta AP antiga. | La resposta obsoleta es descarta. | IMPLEMENTAT_UI_GENERATION_GUARD · TEST_NOU_PENDENT_CI |
| AP-79 | CONC | Confirmar mentre el preu es recalcula. | La UI bloqueja la confirmació fins acabar el càlcul vigent; `offer_id` continua com a migració FINAL. | IMPLEMENTAT_UI_PENDING_GUARD · TEST_NOU_PENDENT_CI |
| AP-80 | CONC | Repetir mateixa resolució amb clau idempotent. | Una sola decisió i una sola notificació. | PENDENT_IMPLEMENTACIO |
| AP-81 | CONC | Resolver sobre estat que ja no és pendent. | `ALREADY_APPLIED` o `VERSION_CONFLICT`; cap sobreescriptura. | PENDENT_IMPLEMENTACIO |
| AP-82 | SEC | Sessió existent però sense permís específic. | 403/denegació equivalent i cap mutació. | VERIFICAT_CI_FRONTERA_P05_0c1825c |
| AP-83 | SEC | Comanda sense CSRF/origen autoritzat. | Rebuig de la comanda. | VERIFICAT_CI_CSRF_P05_0c1825c |
| AP-84 | INT | Backend retorna error/estat estructurat. | UI interpreta el codi/estat, no la presència textual de la paraula «error». | PENDENT_IMPLEMENTACIO |

## Evidència mínima d'execució

Per donar qualsevol AP-* per verificat cal registrar:

1. commit SHA;
2. entorn (`sif_test*`, preproducció o altre autoritzat);
3. data/hora;
4. fixture o identificador de dades;
5. resultat esperat i resultat real;
6. log/evidència;
7. incidència relacionada si falla.

## Relacions

- [Fitxa UC-020](../06-fitxes-funcionals/uc-020.md)
- [UML integrat UC-020](uc-020-aplicar-alumne-prisma.md)
- [Activitats per pàgina UC-020](uc-020-activitats-pagines-actual-final.md)
- [Auditoria i traçabilitat UC-020](uc-020-auditoria-tracabilitat-2026-09-29.md)
- [UC-116 compartit](uc-116-activitats-pagines-justificants-actual-final.md)


## Actualització d'evidència — 03/10/2026

- `PrismaStudentDiscountPolicyTest`: cobreix pagament positiu/parcial, curs regal, `GENERAT=1`, factura no cobrada i estats D/M.
- `PrismaStudentCourseCheckoutServiceTest`: cobreix snapshot autoritatiu, idempotència, conflicte de segon DS_ORDER, ineligible, no autoacreditació, tall temporal a `DATA_INSC` i mismatch de preu.
- `LegacyPrismaStudentPriceSnapshotResolverTest`: cobreix reconstrucció històrica, selector per hores, mismatch i tarifa ambigua.
- `RedsysCoursePaymentIntentPrismaStudentTest` al `main`: cobreix staging AP abans de la intenció, snapshot de descompte i fail-closed del fraccionament AP sense model fiscal.
- `LegacyPrismaStudentEnrollmentAuthorityBoundaryTest`: cobreix UC020-94/97/98, impedint que el valor de preu del navegador sobreescrigui la tarifa AP servidor, comprovant el guard AP+promoció, exigint que `TIPUS_CURS` provingui de `informacio` i evitant reintroduir la drecera de factura no cobrada al preview.
- `RedsysCourseEndToEndSimulatedTest` al `main`: cobreix el circuit tècnic simulat callback → worker → pagament/factura/sync/outbox, incloent duplicats, parcials i exactitud de cèntims; no substitueix l'E2E real de navegador/Redsys.


## Evidència CI exacta — commit `9a70516`

La revalidació del 03/10/2026 permet promocionar a `VERIFICAT_CI_*` únicament els AP-* amb una correspondència directa amb tests observats en PASS. Vegeu [uc-020-evidencia-ci-2026-10-03.md](uc-020-evidencia-ci-2026-10-03.md).

La suite compartida va acabar amb **924 passed / 6 failed**. Les 6 fallades registrades no són proves UC-020; per això no rebaixen els AP-* anteriors, però impedeixen descriure el PR com a globalment verd. Els E2E reals de navegador/Redsys/preproducció continuen `PENDENT_EXECUCIO`.


## Ampliació de cobertura — continuació 03/10/2026

S'han afegit proves explícites per evitar que escenaris ja suportats pel codi continuïn figurant només com a hipòtesis:

- `PrismaStudentDiscountPolicyTest::testEmptyHistoryIsNotEligible` → AP-01.
- `PrismaStudentDiscountPolicyTest::testEligibleNormalHistoryStillWinsWhenExcludedRowsArePresent` → AP-10.
- `LegacyPrismaStudentPriceSnapshotResolverTest::testRejectsMissingHistoricalPrismaStudentTariff` → AP-11/AP-18/AP-71 parcial.
- `LegacyPrismaStudentPriceSnapshotResolverTest::testIgnoresFuturePrismaStudentTariffAtEnrollmentTime` → AP-17/AP-70.
- `LegacyPrismaStudentPriceSnapshotResolverTest::testUsesUniqueCourseAndEditionScopedTariff` → reforç AP-06.
- `RedsysCoursePaymentIntentPrismaStudentTest::testPrismaStudentCheckoutRejectsMissingHistoricalDiscountPrice` → fail-closed integrat abans de crear operació/intenció.
- `RedsysCoursePaymentIntentPrismaStudentTest::testPrismaStudentCheckoutIgnoresFutureTariffAndUsesEnrollmentSnapshot` → tall temporal integrat.

AP-02 i AP-06 es poden marcar ja com a `VERIFICAT_CI_*` perquè el checkout integrat i el resolver equivalent consten en PASS al log del head `0c1825c`. Les proves noves anteriors continuen `PENDENT_CI` fins observar-ne l'execució.


## Reclassificació P05 — seguretat/intranet

Al head `0c1825c` consten en PASS:

- `LegacyUsocDiscountValidationSecurityTest::testLegacyDiscountValidationUsesPostCsrfAndEditPermission`;
- `UsocValidationDecisionBoundaryContractTest::testLegacyMutationIsStrictlyBetweenRequestedAndCommittedSifPhases`;
- `UsocValidationDecisionBoundaryContractTest::testSignedUsocApiExposesTwoPhaseValidationActionsAndRecoveryComponents`.

Això permet reclassificar AP-23/AP-82/AP-83 com a verificats **a nivell de frontera de codi/contracte**. No equival a una prova navegador multioperador ni converteix la memoització de sessió per `requestId` en idempotència persistent; AP-80/AP-81 continuen oberts.


## Reclassificació Redsys / autoritat monetària

Evidència observada al head `0c1825c`:

- AP-24/AP-41: `RedsysPaymentIntentTest::testRejectsCourseDiscountSnapshotWithoutOrigin` — PASS.
- AP-33 (import): `RedsysCallbackTest::testMismatchedAmountRollsBackNotificationAndJob` — PASS.
- AP-34 (frontera): `RedsysCallbackTest::testUnsignedCallbackIsRejectedBeforeRecording` — PASS.
- AP-35: `PrismaStudentCourseCheckoutServiceTest::testRetryWithAnotherDsOrderCannotReplaceLinkedIntent` — PASS.
- AP-25: `RedsysCoursePaymentIntentPrismaStudentTest::testPrismaStudentFractionalPaymentFailsClosedUntilFiscalModelExists` — PASS. Això **no implementa fraccionament AP**; acredita que queda bloquejat de manera segura.

Proves noves creades i encara pendents de CI:

- AP-32: import de pagament AP proposat pel client inferior al saldo autoritatiu → conflicte i zero estat comercial/intenció.
- AP-33: `DS_ORDER` desconegut → cap notificació/cua/cobrament.
- AP-34: signatura `HMAC_SHA512_V2` incorrecta → rebuig 422.


## Reconciliació preview, payment_link i snapshot comercial

- AP-36: el preview inicialitza `$descomptes=[]`, `$i=0`, no entra al bucle sense candidats i retorna `0|0|0|0`; prova de frontera creada.
- AP-38: prova creada perquè `calcularPreu.php` + `buscarAlumnePrisMa.php` continuïn sense mutacions d'inscripció, cobrament o fiscalitat.
- AP-50/AP-54: `PaymentLinkService` comprova ara `CLASSIFICATION=BILLABLE` i `STATUS in {READY_FOR_PAYMENT, PAYMENT_PENDING}` tant a `issue()` com a `resolve()`. Un link actiu deixa de resoldre si l'operació esdevé no pagable.
- AP-53: el token només es conserva com SHA-256; prova nominal creada perquè un token manipulat no resolgui el link original.
- AP-58: `PrismaStudentCommercialSnapshotImmutabilityTest` crea una oferta AP, intenta repetir-la amb un `price_rule_version` diferent i exigeix 409 conservant el snapshot i la intenció originals.
- El checkout AP crea ara `CLASSIFICATION=BILLABLE` i `STATUS=READY_FOR_PAYMENT` abans de vincular la intenció.


## Reconciliació P06 — canvi de curs

AP-72 deixa de ser una mancança de codi: el canvi de curs AP ja no usa el selector genèric `ID_PREU + TIPUS`. `resolveLegacyPrismaStudentCourseChangePrice()` exigeix target únic, tarifa base única, tarifa AP única, vigència, curs o hores i mes de destí. `LegacyPrismaStudentCourseChangeBoundaryTest` protegeix també que `TIPUS_DESC/VALID_DESC` i els imports AP no tornin a quedar sota autoritat dels hidden inputs.

Continua `PENDENT_CI` fins observar el nou test en PASS. AP-69/AP-73 continuen oberts perquè la unificació de selectors/policy **entre tots els canals** encara no està completada.


## Robustesa addicional de reintents — 04/10/2026

Aquesta ampliació no crea AP-85+: manté la matriu canònica AP-01…AP-84 i registra proves internes de robustesa UC-020.

| ID intern | Escenari | Esperat | Estat |
| --- | --- | --- | --- |
| UC20-TEST-009 | Repetir el mateix checkout després d'alterar el nom del participant llegat. | 409; conservar un únic participant i snapshot original. | TEST CREAT · PENDENT CI HEAD |
| UC20-TEST-010 | Repetir el mateix checkout amb una clau canònica de participant diferent. | 409; no crear segon participant. | TEST CREAT · PENDENT CI HEAD |
| UC20-TEST-011 | Duplicar participant per rol o línia per `ORDRE`. | fail-closed, no reutilització ambigua. | GUARD IMPLEMENTAT · PENDENT CI HEAD |

Aquests tests complementen AP-58/AP-63 i la idempotència del checkout: el snapshot comercial no es limita a import/preu/intenció; inclou també identitat del participant i línia de producte.


## Hardening de concurrència P02 — 04/10/2026

- `LegacyPrismaStudentPriceConcurrencyBoundaryTest` cobreix generació monotònica, descart de callbacks obsolets, bloqueig de submit, exclusivitat AP/promoció i neteja del fallback.
- `LegacyEnrollmentCompletionPrecheckBoundaryTest` cobreix la cadena única curs actual → curs derivat i el handler namespaced de confirmació.
- `LegacyPrismaStudentEnrollmentAuthorityBoundaryTest` exigeix POST-only per `enviarInscripcio.php` i absència de `$_GET`/autoritat client de `tipusCurs`.

Aquest hardening redueix el risc monetari de P02 abans de la migració a `offer_id`; no converteix encara la UI llegada en una oferta SIF immutable.


## Reconciliació estat pagable i transferència — 04/10/2026

`LegacyPrismaStudentPaymentStateBoundaryTest` exigeix:

- AP alternatiu després de denegació → `VALID_DESC=1`;
- transferència web → només amb `validDesc == 1`;
- transferència del pont candidat → mateix gate;
- intenció Redsys AP SIF → rebutja `VALID_DESC != 1`.

La conciliació/registre efectiu d'una transferència és UC-022 i continua fora del commit comercial immediat d'UC-020.


## Token P03/P04 i concurrència d'alta — 04/10/2026

- `LegacyPaymentTokenTest`: token vàlid, manipulació, base64 invàlid, payload no positiu, endpoints sense `REQUEST_URI` i JS amb `encodeURIComponent`.
- `LegacyIdpagAllocatorSecurityTest`: `GET_LOCK`/ `RELEASE_LOCK`, allocator compartit i invariant `reserveIdPag() < INSERT < releaseIdPag()`.
- **AP-29:** reclassificat a implementat/protegit.
- **AP-28:** continua pendent; un allocator únic no és idempotència semàntica de matrícula. Destí transversal: UC-107.
