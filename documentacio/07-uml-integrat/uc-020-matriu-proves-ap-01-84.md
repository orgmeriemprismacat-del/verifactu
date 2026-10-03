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

## AP-01…AP-16 · elegibilitat, preu i integritat bàsica

| ID | Nivell | Escenari | Resultat esperat | Estat |
| --- | --- | --- | --- | --- |
| AP-01 | INT | Persona sense cap historial admissible. | No aplicar Alumne PrisMa; conservar tarifa ordinària o altra oferta vàlida. | PENDENT_EXECUCIO |
| AP-02 | INT | Existeix una inscripció anterior pagada i una tarifa AP vigent. | Aplicar la tarifa AP definida per la política aprovada i conservar-ne origen/regla. | PENDENT_EXECUCIO |
| AP-03 | INT | L'únic antecedent té un pagament parcial positiu. | És elegible sota `ALUMNE_PRISMA_WEB_LEGACY_V2` perquè `A_PAGAR>0 && PAGAMENT>0`. | COBERT_UNIT_POLICY |
| AP-04 | INT | L'únic antecedent és un curs regal. | És elegible i conserva motiu/evidència `GIFT_COURSE`. | COBERT_UNIT_POLICY |
| AP-05 | INT | L'historial disponible està en estat exclòs `INSC CURS=D/M`. | No usar aquests registres com a antecedent AP en coherència amb l'ACTUAL reconstruït. | PENDENT_EXECUCIO |
| AP-06 | INT | Historial admès + `TIPUS=1` vigent per curs/edició. | Retornar preu AP exacte i origen `ALUMNE_PRISMA`. | PENDENT_EXECUCIO |
| AP-07 | INT | Cap registre de l'historial compleix les condicions. | No seleccionar `TIPUS=1`. | PENDENT_EXECUCIO |
| AP-08 | INT | Antecedent amb `A_PAGAR>0` i `PAGAMENT>0` però no totalment cobrat. | És elegible segons la rule v2 de compatibilitat. | COBERT_UNIT_POLICY |
| AP-09 | INT | Antecedent de curs regal i variant de factura relacionada sense pagament. | Curs regal acredita; factura només emesa/no cobrada no acredita. | COBERT_UNIT_POLICY_PER_BRANCH |
| AP-10 | INT | Historial amb registres D/M i registres normals. | Només els antecedents admissibles poden justificar AP. | PENDENT_EXECUCIO |
| AP-11 | INT | Persona elegible però no existeix cap tarifa AP aplicable. | Estat `ELIGIBLE_NO_PRICE`/incidència equivalent; cap import nul o inventat pagable. | PENDENT_EXECUCIO |
| AP-12 | INT | Coincideixen AP i promoció. | AP + promoció falla tancat en l'alta AP; no s'acumulen silenciosament. | COBERT_PARCIALMENT_TEST_FRONTERA |
| AP-13 | SEC | El client manipula `tipusDescompte`, `preuDescompte` o `tipusCurs`. | Per AP, el servidor rellegeix elegibilitat/tarifa i deriva `TIPUS_CURS` de metadades servidor; el navegador no és autoritat monetària. | COBERT_PARCIALMENT_TEST_FRONTERA |
| AP-14 | CONC | La tarifa canvia entre previsualització i confirmació. | Confirmar només una oferta servidor vigent o retornar conflicte; no acceptar TOCTOU silenciós. | PENDENT_EXECUCIO |
| AP-15 | E2E | Oferta AP → cobrament → línia fiscal. | Base − descompte = net; import cobrat i línia fiscal són coherents amb el snapshot congelat. | PENDENT_EXECUCIO |
| AP-16 | CONC | Reintent equivalent de la mateixa operació. | Reutilitzar decisió/operació; no duplicar descompte, cobrament ni factura. | PENDENT_EXECUCIO |

## AP-17…AP-25 · denegació, canvis posteriors i fiscalitat

| ID | Nivell | Escenari | Resultat esperat | Estat |
| --- | --- | --- | --- | --- |
| AP-17 | INT | Hi ha tarifa AP futura i encara no vigent. | No seleccionar-la abans de `DATAI`. | PENDENT_EXECUCIO |
| AP-18 | INT | Persona elegible però el preu AP no es pot obtenir. | Bloquejar actualització/oferta i registrar incidència. | PENDENT_EXECUCIO |
| AP-19 | E2E | Es denega el descompte original després d'un cobrament. | Conservar el moviment real i classificar l'ajust econòmic; no sobreescriure el passat. | PENDENT_EXECUCIO |
| AP-20 | E2E | Es resol el dret després d'haver emès factura. | Preservar factura original i derivar l'ajust fiscal/rectificatiu que correspongui. | PENDENT_EXECUCIO |
| AP-21 | INT | Historial amb antecedents anteriors i posteriors a `DATA_INSC`. | Excloure matrícula actual i historial posterior a `DATA_INSC`. | COBERT_INTEGRACIO_CHECKOUT |
| AP-22 | CONC | Dues peticions comercials simultànies intenten actualitzar la mateixa oferta. | Una versió vàlida; l'altra reutilitza o rep conflicte, sense sobreescriptura desfasada. | PENDENT_EXECUCIO |
| AP-23 | SEC | Usuari autenticat sense permís específic intenta resoldre el descompte. | Denegació al servidor i cap canvi econòmic. | PENDENT_EXECUCIO |
| AP-24 | INT | Snapshot monetàriament coherent però sense origen/regla AP. | No atribuir-lo arbitràriament a Alumne PrisMa ni a una promoció genèrica. | PENDENT_EXECUCIO |
| AP-25 | E2E | Operació AP amb pagament fraccionat. | Separar preu net total, descompte i cadascun dels cobraments; cap fracció és el total de l'oferta. | PENDENT_EXECUCIO |

## AP-26…AP-35 · promocions, confirmació i circuit bancari

| ID | Nivell | Escenari | Resultat esperat | Estat |
| --- | --- | --- | --- | --- |
| AP-26 | INT | Aplicar codi promocional després d'haver calculat AP. | Tipus/origen registrat coincideix amb l'import final realment aplicat. | PENDENT_EXECUCIO |
| AP-27 | INT | Canviar d'edició amb tarifes diferents. | L'oferta usa la tarifa exacta de l'edició final. | PENDENT_EXECUCIO |
| AP-28 | CONC | Dues confirmacions simultànies de la mateixa inscripció. | No duplicar inscripció ni operació comercial. | PENDENT_EXECUCIO |
| AP-29 | CONC | Dues altes simultànies requereixen nou `IDPAG`. | Identificadors inequívocs o migració a identificador segur; cap col·lisió. | PENDENT_EXECUCIO |
| AP-30 | INT | Error tècnic durant el càlcul de preu. | Cap confirmació amb oferta indeterminada; resposta estructurada d'error. | PENDENT_EXECUCIO |
| AP-31 | INT | Descompte pendent o denegat en obrir confirmació/pagament. | Pantalles mostren el mateix estat comercial i només mètodes autoritzats. | PENDENT_EXECUCIO |
| AP-32 | SEC | Manipular l'import enviat al formulari de pagament. | El servidor usa import autoritzat/persistent, no el valor manipulat. | PENDENT_EXECUCIO |
| AP-33 | E2E | Callback amb ordre o import diferent de la intenció. | Rebuig/incidència; cap cobrament atribuït incorrectament. | PENDENT_EXECUCIO |
| AP-34 | SEC | Callback amb signatura invàlida. | Rebuig abans de registrar cobrament. | PENDENT_EXECUCIO |
| AP-35 | CONC | Mateixa inscripció/oferta amb dues ordres bancàries. | Tractar intents sense duplicar benefici comercial ni factura; política de reintent explícita. | PENDENT_EXECUCIO |

## AP-36…AP-48 · errors de selector, notificacions i ajustos

| ID | Nivell | Escenari | Resultat esperat | Estat |
| --- | --- | --- | --- | --- |
| AP-36 | INT | Cap tarifa disponible i Carnet Jove marcat. | Resposta controlada; cap accés a arrays/índexs inexistents. | PENDENT_EXECUCIO |
| AP-37 | INT | Persona AP aplica després un codi promocional. | Origen comercial, import cobrat i origen fiscal continuen alineats. | PENDENT_EXECUCIO |
| AP-38 | INT | Simple consulta/previsualització de preu. | No crear factura, cobrament ni UUID fiscal. | PENDENT_EXECUCIO |
| AP-39 | INT | L'únic antecedent possible és la mateixa inscripció. | No autoacreditar AP. | COBERT_INTEGRACIO_CHECKOUT |
| AP-40 | INT | Oferta AP d'una edició s'intenta usar en una altra. | Revalidació o conflicte segons política; mai trasllat silenciós. | PENDENT_EXECUCIO |
| AP-41 | INT | Snapshot amb imports vàlids però origen comercial incorrecte. | Detectar contradicció abans de crear intenció o factura. | PENDENT_EXECUCIO |
| AP-42 | INT | La tarifa canvia després d'haver congelat una oferta AP. | El snapshot comercial congelat no es reescriu; la migració completa de la intranet a oferta SIF continua pendent. | PENDENT_EXECUCIO_TRANSVERSAL |
| AP-43 | E2E | Denegació documental + persona AP, sense pagaments previs. | Mateixa inscripció, nova oferta AP correcta i pagament habilitat. | PENDENT_EXECUCIO |
| AP-44 | E2E | Mateix cas amb cobrament parcial previ. | Correu i UI distingeixen total/cobrat/pendent; cap cobrament duplicat pel total. | PENDENT_EXECUCIO |
| AP-45 | CONC | Dos operadors resolen la mateixa sol·licitud de forma contradictòria. | Una sola resolució sobre la versió esperada; l'altra rep conflicte. | PENDENT_EXECUCIO |
| AP-46 | E2E | La decisió es desa però falla SMTP. | No repetir mutació comercial; notificació queda fallida/pendent de reintent. | PENDENT_EXECUCIO |
| AP-47 | E2E | Denegació + AP i consulta de confirmació/pagament. | Mateixa autorització de cobrament a totes les pantalles i mètodes. | PENDENT_EXECUCIO |
| AP-48 | E2E | La inscripció ja està facturada quan es denega/canvia el descompte. | No reescriure factura; derivar ajust econòmic/fiscal. | PENDENT_EXECUCIO |

## AP-49…AP-55 · estat de pagament i links

| ID | Nivell | Escenari | Resultat esperat | Estat |
| --- | --- | --- | --- | --- |
| AP-49 | INT | Descompte documental pendent a confirmació. | No oferir targeta ni transferència. | PENDENT_EXECUCIO |
| AP-50 | SEC | Pendent documental + accés directe a link de pagament. | Link vàlid no autoritza cobrament si l'oferta no és pagable. | PENDENT_EXECUCIO |
| AP-51 | E2E | Denegació + AP elegible. | Nova oferta AP pagable coherent. | PENDENT_EXECUCIO |
| AP-52 | E2E | Obrir el link enviat després de denegació + AP. | Correu i mètodes visibles no es contradiuen. | PENDENT_EXECUCIO |
| AP-53 | SEC | Token de pagament manipulat. | Rebuig per integritat/hash. | PENDENT_EXECUCIO |
| AP-54 | SEC | Token correcte però oferta no pagable. | Bloqueig comercial. | PENDENT_EXECUCIO |
| AP-55 | CONC | Repetir resolució després de crear oferta alternativa. | Reutilització; cap segona decisió/notificació. | PENDENT_EXECUCIO |

## AP-56…AP-64 · operació comercial SIF

| ID | Nivell | Escenari | Resultat esperat | Estat |
| --- | --- | --- | --- | --- |
| AP-56 | INT | Crear oferta AP. | Crear `commercial_operation` + `discount_validation` coherents. | COBERT_PARCIALMENT_PER_TEST_NOU |
| AP-57 | CONC | Repetir exactament la mateixa creació. | Mateixa operació/validació per idempotència. | COBERT_PARCIALMENT_PER_TEST_NOU |
| AP-58 | INT | Canviar tarifa després d'acceptar oferta. | `PRICE_SNAPSHOT_JSON` original roman immutable. | PENDENT_EXECUCIO |
| AP-59 | INT | Revocar link i substituir oferta/link. | Link antic `REVOKED`; nou link separat. | COBERT_PARCIALMENT_PER_TEST_NOU |
| AP-60 | SEC | Utilitzar link revocat. | Rebuig abans de TPV. | COBERT_PARCIALMENT_PER_TEST_NOU |
| AP-61 | INT | `EXPECTED_AMOUNT` del link supera/incompleix l'operació. | Conflicte abans de crear cobrament. | COBERT_PARCIALMENT_PER_TEST_NOU |
| AP-62 | INT | Crear intenció Redsys des d'operació. | `UUID_INTENT` queda vinculat explícitament a `UUID_OPERATION`. | COBERT_INTEGRACIO_CHECKOUT |
| AP-63 | CONC | Mateix `DS_ORDER` amb snapshot comercial diferent. | Conflicte idempotent. | COBERT_PER_TEST_EXISTENT_REDSYS |
| AP-64 | INT | Dret original REJECTED + AP ACCEPTED. | Dues decisions diferenciades; una única oferta actual. | PENDENT_IMPLEMENTACIO |

## AP-65…AP-73 · divergències d'elegibilitat

| ID | Nivell | Escenari | Resultat esperat | Estat |
| --- | --- | --- | --- | --- |
| AP-65 | INT | Únic antecedent `GENERAT=1`, sense cobrament. | És elegible sota rule v2. | COBERT_UNIT_POLICY |
| AP-66 | INT | Única evidència = inscripció actual, encara que `GENERAT=1` o tingui estat favorable. | No autoacreditar. | COBERT_INTEGRACIO_CHECKOUT |
| AP-67 | INT | Antecedent posterior a `DATA_INSC` de la matrícula tarifada. | No acreditar retroactivament. | COBERT_INTEGRACIO_CHECKOUT |
| AP-68 | INT | Factura relacionada sense cobrament. | No acredita AP per si sola; no dependre de `!= NULL`. | COBERT_UNIT_POLICY |
| AP-69 | INT | Mateix `ID_PREU` amb tarifes AP específiques de curs/mes. | Tots els canals seleccionen la tarifa canònica exacta. | PENDENT_EXECUCIO |
| AP-70 | INT | Tarifa AP actual + futura. | Només la vigent és seleccionable. | PENDENT_EXECUCIO |
| AP-71 | INT | Elegible sense tarifa AP. | `ELIGIBLE_NO_PRICE`; no oferta pagable. | PENDENT_EXECUCIO |
| AP-72 | INT | Canvi de curs amb tarifa específica per curs. | No recuperar una fila d'un altre curs per filtre incomplet. | PENDENT_EXECUCIO |
| AP-73 | E2E | Mateixa persona/operació per web i intranet. | Mateixa política versionada i mateixa justificació del dret. | PENDENT_IMPLEMENTACIO |

## AP-74…AP-84 · concurrència, seguretat i contractes

| ID | Nivell | Escenari | Resultat esperat | Estat |
| --- | --- | --- | --- | --- |
| AP-74 | CONC | DNI A lent → DNI B ràpid. | Resposta A obsoleta no pot sobreescriure l'oferta B. | PENDENT_IMPLEMENTACIO |
| AP-75 | CONC | Canviar edició amb càlcul pendent. | Només queda activa l'oferta de l'edició final. | PENDENT_IMPLEMENTACIO |
| AP-76 | CONC | Marcar/desmarcar check de descompte ràpidament. | Cap combinació de tipus/import de moments diferents. | PENDENT_IMPLEMENTACIO |
| AP-77 | INT | AP calculat → aplicar codi promocional. | L'oferta final té un únic origen coherent. | PENDENT_IMPLEMENTACIO |
| AP-78 | CONC | Promoció aplicada → arriba resposta AP antiga. | La resposta obsoleta es descarta. | PENDENT_IMPLEMENTACIO |
| AP-79 | CONC | Confirmar mentre el preu es recalcula. | Bloqueig o acceptació per `offer_id` servidor vigent. | PENDENT_IMPLEMENTACIO |
| AP-80 | CONC | Repetir mateixa resolució amb clau idempotent. | Una sola decisió i una sola notificació. | PENDENT_IMPLEMENTACIO |
| AP-81 | CONC | Resolver sobre estat que ja no és pendent. | `ALREADY_APPLIED` o `VERSION_CONFLICT`; cap sobreescriptura. | PENDENT_IMPLEMENTACIO |
| AP-82 | SEC | Sessió existent però sense permís específic. | 403/denegació equivalent i cap mutació. | PENDENT_IMPLEMENTACIO |
| AP-83 | SEC | Comanda sense CSRF/origen autoritzat. | Rebuig de la comanda. | PENDENT_IMPLEMENTACIO |
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
- `LegacyPrismaStudentEnrollmentAuthorityBoundaryTest`: cobreix UC020-94/97, impedint que el valor de preu del navegador sobreescrigui la tarifa AP servidor, comprovant el guard AP+promoció i exigint que `TIPUS_CURS` provingui de `informacio`.
- `RedsysCourseEndToEndSimulatedTest` al `main`: cobreix el circuit tècnic simulat callback → worker → pagament/factura/sync/outbox, incloent duplicats, parcials i exactitud de cèntims; no substitueix l'E2E real de navegador/Redsys.
