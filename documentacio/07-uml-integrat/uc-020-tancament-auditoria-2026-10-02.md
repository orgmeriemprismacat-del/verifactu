# UC-020 — Tancament d'auditoria

**Data:** 02/10/2026  
**Estat:** AUDIT_CLOSED_REVALIDATED_2026-10-03  
**Branca:** `refactor/uc-020-reconcile-runtime-2026-10-02`

## 1. Abast tancat

S'ha reconciliat la fitxa funcional, codi PHP/JS real, diagrames de classes, seqüències i activitats ACTUAL/FINAL, matriu AP-01…AP-84, runtime comercial SIF i canal actiu de pagament amb targeta.

## 2. Política canònica

La regla queda versionada com `ALUMNE_PRISMA_WEB_LEGACY_V2`.

1. `GENERAT=1` continua acreditant el dret.
2. Una factura relacionada però no cobrada, per si sola, no acredita AP.
3. La matrícula actual no pot acreditar-se a si mateixa.
4. Per matrícules llegades, `evaluation_at` és `DATA_INSC`; no compta historial posterior.
5. AP no s'acumula amb promocions; un estat mixt falla tancat.
6. La tarifa AP queda congelada al snapshot de matrícula mentre aquesta continuï pagable; la caducitat de links és independent.

## 3. Correccions executables d'aquesta passada

- Revalidació server-side d'AP a `enviarInscripcio.php` abans de persistir `TIPUS_DESC=1` i `A_PAGAR`.
- Rebuig d'AP + promoció simultanis.
- Selecció server-side de tarifa AP per `ID_PREU`, curs/hores, edició i vigència; zero o múltiples coincidències fallen tancat.
- `LegacyPrismaStudentHistoryRepository`: exclusió de matrícula actual i filtre temporal.
- `PrismaStudentDiscountPolicy`: v2 alineada amb el SQL públic executable.
- Tests nous contra autoacreditació, historial futur i factura només emesa.
- Recuperació de `CommercialOperationPartyRepository`, lectura d'intenció per UUID i actualització d'estat des de #110, preservant `operational_event` de #112.
- `calcularPreu.php`: inicialització segura quan no hi ha candidats.

## 4. Reconciliació d'intranet

La fotografia antiga GET/sense CSRF és obsoleta. La ruta actual `sendMsgValidatCurosDescomptes.php` exigeix POST, sessió, permís, CSRF, valida `idInsc/verificat/requestId` i reutilitza el resultat per `requestId`. Per USOC, a més, coordina begin/complete amb SIF.

## 5. Gates de desplegament/evolució que no reobren l'auditoria

- Migrar tota l'alta llegada a POST/CSRF i a una oferta SIF nativa amb `offer_id`.
- Fer `payment_link` la ruta canònica de tots els canals.
- Unificar transferència amb la mateixa autorització comercial que targeta.
- Executar i conservar evidència E2E navegador → callback → factura en preproducció.

Són gates de desplegament o tasques transversals ja identificades; no són incògnites d'auditoria.

## 6. Criteri de verificació final

El commit de tancament ha de mantenir el PR mergeable i les comprovacions automàtiques han d'acabar correctament. Si GitHub Actions no arrenca, la manca d'execució es registra com a **bloqueig d'infraestructura de verificació**, mai com a prova passada.
## 7. Decisió sobre el PR alternatiu #97

El PR #97 ha estat revisat abans del tancament. Aporta una abstracció interessant: `PrismaStudentCourseCheckoutService` delega la persistència comercial a `CommercialOfferService`. No s'ha portat tal qual perquè `TransactionRunner` no admet transacció ja activa i el flux #97 separa oferta, intenció i vincle en commits diferents.

Per UC-020 preval l'atomicitat del tall final de #112. Un futur refactor cap a `CommercialOfferService` haurà de permetre participar en la mateixa transacció/unit of work abans de substituir el writer actual.

## 8. Revalidació 03/10/2026

La reauditoria contra el `main` vigent ha detectat i corregit **UC020-94** i **UC020-97**. UC020-94: a l'alta web AP, el preu rellegit al servidor era sobreescrit posteriorment per `preuDescompte` procedent del navegador abans de l'INSERT. La reassignació tardana s'ha eliminat i s'ha afegit `LegacyPrismaStudentEnrollmentAuthorityBoundaryTest`. UC020-97: `tipusCurs` deixava al navegador activar el branch subvencionat que força preu zero; ara `TIPUS_CURS` es deriva de `informacio` al servidor abans de qualsevol override monetari. **UC020-98** elimina del preview la branca morta de factura relacionada sense cobrament i impedeix que una futura «correcció» de `NULL` contradigui la policy v2.

També s'ha reconciliat la documentació que encara conservava com a vigents decisions ja tancades i la fotografia antiga de la intranet GET/sense CSRF. Les peces documentals obligatòries queden completes: fitxa v1.6, UML integrat, classes, seqüències, activitats P01…P06, traçabilitat i matriu AP-01…AP-84.

El `main` nou incorpora el tall Redsys CURS amb flags de cutover/drain i proves E2E simulades de callback → worker → pagament/factura/sync/outbox. Es manté com a **gate de rollout** l'E2E real/controlat de navegador → Redsys/callback → factura, més la migració a `payment_link` canònic i la unificació de transferència.
