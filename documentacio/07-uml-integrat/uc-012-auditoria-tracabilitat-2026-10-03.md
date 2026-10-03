# UC-012 — Auditoria exhaustiva i matriu de traçabilitat

**Data:** 03/10/2026  
**Base:** `main@b0e8ff7150c5a8b415cc109d298d82f0db1f68df`  
**Branca:** `audit/uc-012-2026-10-03`

## 1. Resultat executiu

UC-012 **té fitxa funcional, UML integrat i codi legacy real**, i el repositori també conté un circuit SIF provat per registrar un **cobrament real posterior a una reclamació**. El cas, però, **no està tancat end-to-end**: no existeix encara un orquestrador SIF acreditat per detectar deute, registrar l'expedient, escalar recordatoris/reclamacions, generar notificacions idempotents i tancar/reprogramar el cas.

| Bloc | Documentat | Implementat | Verificat | Pendent |
| --- | --- | --- | --- | --- |
| Fitxa UC-012 | Sí | n/a | contrastada | — |
| UML integrat | Sí | n/a | contrastat | — |
| Classes ACTUAL/FINAL | Sí, creat en auditoria | ACTUAL sí | inspecció | FINAL |
| Seqüències/activitats per pàgina | Sí, creat en auditoria | ACTUAL sí | inspecció | FINAL |
| JS/PHP legacy | Sí, inventariat | Sí | inspecció estàtica | E2E |
| Recordatori/primera/final/morosos | Sí | Sí legacy | no automatitzat | migració SIF |
| Cobrament posterior | Sí | Sí SIF | tests existents | preproducció |
| Expedient de reclamació SIF | Sí com a disseny | No acreditat | No | P0 |
| Outbox idempotent UC-012 | Sí com a regla | No acreditat | No | P0 |
| Autorització/CSRF frontera legacy | Sí com a requisit | no acreditat als handlers | No | P0 |

## 2. Mapa P-MOR

| ID | Acció | ACTUAL | FINAL | Estat |
| --- | --- | --- | --- | --- |
| P-MOR-01 | Detectar deute i pagador | SQL sobre inscripcions i curs | snapshot SIF factura+pagaments+devolucions+pròrroga | PARCIAL |
| P-MOR-02 | Recordatori final | legacy UPDATE + SMTP | event + outbox idempotent | LEGACY |
| P-MOR-03 | Primera reclamació | legacy UPDATE + URL IDPAG + SMTP | event + outbox + pagador revalidat | LEGACY |
| P-MOR-04 | Reclamació final | legacy; acoblat a baixa en alguns fluxos | event; baixa deriva UC-72/95/96 | LEGACY |
| P-MOR-05 | Regularitzar/tancar | camps legacy; circuit SIF separat | ClaimPaymentService + recalcul/tancament | PARCIAL SIF |

## 3. Fitxers inspeccionats

### Documentació
- `documentacio/06-fitxes-funcionals/uc-012.md`
- `documentacio/07-uml-integrat/uc-012-morositat-reclamacio.md`
- `documentacio/07-uml-integrat/uc-024-registrar-cobrament-reclamacio.md`
- `documentacio/07-uml-integrat/uc-043-gestionar-notificacions-recordatoris.md`

### Intranet ACTUAL
- quatre pàgines `facturacio-*.php`;
- quatre JS principals;
- AJAX de cerca/actualització;
- `Intranet.php` i consultes/mutacions relacionades.

### SIF
- `ClaimPaymentService.php`
- `ClaimPaymentPayloadBuilder.php`
- scripts `preflight/preview/process-claim-payment.php`
- tests unit/integration de claim payment.

## 4. Troballes

### A. Deute i responsabilitat
El llegat usa principalment `A_PAGAR-PAGAMENT` i estat d'inscripció. Això és útil per operació actual, però no és suficient com a font final quan existeixen factura d'empresa, devolucions, múltiples assignacions, cobrament pendent de processament o pròrroga.

### B. Comunicacions
Els mètodes inspeccionats actualitzen dades i envien SMTP en el mateix flux. No hi ha evidència específica d'un outbox UC-012 que faci el lliurament reintentable i idempotent.

### C. Seguretat de frontera
Els handlers POST inspeccionats llegeixen `$_POST['idInsc']` i deserialitzen sessió. No mostren, a la mateixa frontera, CSRF/idempotency-key/comprovació explícita de rol. Fins que s'acrediti una capa transversal, l'estat és **NO VERIFICAT**.

### D. Separació fiscal/acadèmica
El recordatori o la reclamació **no** és un fet fiscal. La baixa acadèmica tampoc implica automàticament anul·lació fiscal. Qualsevol rectificativa ha de passar pel classificador/UC corresponent.

### E. Cobrament posterior
`ClaimPaymentService` és la peça més madura: registra `CHARGE` i `CLAIM_PAYMENT` a la factura existent, és idempotent i té tests. No obstant això, és P-MOR-05, no tot UC-012.

## 5. Matriu de traçabilitat

| Requisit | Document | Codi | Test/evidència | Estat |
| --- | --- | --- | --- | --- |
| No crear factura nova per reclamar | fitxa + UML | ClaimPaymentService no issueInvoice | ClaimPaymentServiceTest | VERIFICAT per cobrament |
| Primera reclamació | UML + seqüència | legacy Intranet + AJAX | no localitzat | IMPLEMENTAT legacy |
| Recordatori final | UML + seqüència | legacy Intranet + AJAX | no localitzat | IMPLEMENTAT legacy |
| Reclamació final | UML + seqüència | legacy Intranet + AJAX | no localitzat | IMPLEMENTAT legacy |
| Morosos entitat/alumne | inventari | handlers legacy | no localitzat | IMPLEMENTAT legacy |
| Idempotència cobrament | UML | builder/payment service | tests unit/integration | VERIFICAT |
| Idempotència notificacions | fitxa | no acreditat UC-012 | no | PENDENT |
| Autorització servidor | fitxa | no acreditada a frontera inspeccionada | no | PENDENT |
| CSRF | requisit de seguretat | no visible handlers | no | PENDENT |
| Expedient de reclamació | disseny FINAL | no acreditat | no | PENDENT |
| Pròrroga abans d'avís | UML | no coordinada per ClaimPaymentService | no | PENDENT |
| Tancament després de pagament | UML | pagament sí; claim close no | parcial | PENDENT |

## 6. Proves mínimes per tancar

1. P-MOR-01: saldo zero, parcial, devolució, empresa/grup i pròrroga.
2. P-MOR-02/03/04: retry idempotent; mateix payload → REUSED, diferent payload → CONFLICT.
3. CSRF/rol denegat sense mutació ni email.
4. Fallada de SMTP/transport no desfà el commit i es reintenta via outbox.
5. Cobrament que arriba entre preview i confirmació → revalidació i NO_CHANGE.
6. Callback/transferència duplicada → un sol `UUID_PAYMENT`.
7. Pagament parcial → reclamació continua amb saldo nou.
8. Pagament complet → cancel·la avisos pendents i tanca expedient.
9. Baixa acadèmica després de morositat → cap anul·lació fiscal automàtica.
10. Factura d'empresa → comunicació només al pagador/responsable autoritzat.

## 7. Estat de tancament

**DOCUMENTACIÓ D'AUDITORIA:** completada.  
**ACTUAL LEGACY:** implementat però no verificat E2E en aquesta auditoria.  
**P-MOR-05 SIF:** implementat i amb proves de repositori.  
**UC-012 FINAL SIF:** pendent de programació i proves.  
**Auditoria tècnica:** queda oberta com a `AUDIT_COMPLETE / IMPLEMENTATION_PARTIAL / OPERATIONAL_ACCEPTANCE_PENDING`.
