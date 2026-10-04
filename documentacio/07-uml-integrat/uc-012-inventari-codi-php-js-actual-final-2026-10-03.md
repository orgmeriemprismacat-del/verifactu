# UC-012 — Inventari executable PHP/JS ACTUAL i FINAL

**Data:** 03/10/2026  
**Base auditada:** `main@b0e8ff7150c5a8b415cc109d298d82f0db1f68df`

## 1. Llegenda

- **DOCUMENTAT**: existeix fitxa, UML o regla.
- **IMPLEMENTAT**: existeix codi executable.
- **VERIFICAT**: existeix test automatitzat o evidència executable.
- **PENDENT**: manca implementació, prova o evidència.

## 2. Superfícies legacy

| ID | Pàgina | JS | AJAX / backend | Efecte real | Estat |
| --- | --- | --- | --- | --- | --- |
| P-MOR-UI-01 | `facturacio-recordatori-pagament-final.php` | `js/facturacio-recordatori-pagament-final.js` | `buscarRegistresRecordatorisPagament.php`, `updDadesRecordatoriPagament.php` → `Intranet::updateSendMsg_Facturacio_Recordatori_Pagament()` | calcula pendent amb dades legacy, actualitza marca i envia correu | IMPLEMENTAT / no E2E |
| P-MOR-UI-02 | `facturacio-primera-reclamacio-pagament.php` | `js/facturacio-primera-reclamacio-pagament.js` | `buscarRegistresReclamacio.php`, `updDadesPrimeraReclamacio.php` → `Intranet::__updateSendMsg_Facturacio_Primera_Reclamacio()` | marca primera reclamació, genera URL legacy, envia correu | IMPLEMENTAT / no E2E |
| P-MOR-UI-03 | `facturacio-reclamacio-final.php` | `js/facturacio-reclamacio-final.js` | `showMePeopleLastClaimPay.php`, `updLastClaimPay.php` → `Intranet::updateSendMsg_LastClaimPay()` | reclamació final i lògica vinculada a baixa | IMPLEMENTAT / no E2E |
| P-MOR-UI-04 | `facturacio-control-morosos.php` | `js/facturacio-control-morosos.min.js` | handlers d'entitat/alumne certificat/no certificat | gestiona morosos ja classificats | IMPLEMENTAT / no E2E |

## 3. Consultes i mutacions legacy identificades

Consultes de `Intranet.php`:
`cnsCursosRecordarPag`, `cnsAlumnesRecordarPag`, `cnsCursosClaimBaixes`, `cnsAlumnClaimPag`, `cnsAlumnClaimEntMoros`, `cnsAlumnClaimAlumnNoCertMoros`, `cnsAlumnClaimAlumnCertMoros`, `cnsEntMoros`.

Mutacions/efectes:
`updPrimeraReclamacio`, `updClaimRecPag`, `updClaimDonarBaixa`, `updInscCursBaixaiMoros`, `updReclamatDefaulter` i enviament SMTP immediat.

## 4. Implementació SIF localitzada

| Component | Funció | Implementat | Verificat |
| --- | --- | --- | --- |
| `ClaimPaymentService` | registrar cobrament real contra factura existent | Sí | `ClaimPaymentServiceTest` |
| `ClaimPaymentPayloadBuilder` | construir CHARGE/CLAIM_PAYMENT idempotent | Sí | `ClaimPaymentPayloadBuilderTest` |
| `ManualPaymentInvoiceRepository` | resoldre factura per UUID/NUM_VISIBLE | Sí | cobert indirectament |
| `PaymentService` | persistir moviment i assignació | Sí | suite pagaments |
| scripts `preflight/preview/process-claim-payment.php` | operar cobrament de reclamació | Sí | tests d'integració dedicats |
| `DebtSnapshotRepository` | saldo factura + allocations/refunds + receptor fiscal | **Sí, afegit en auditoria** | tests nous; CI en cua |
| `DebtClaimCaseRepository` | expedient/event versionat i idempotent | **Sí, afegit en auditoria** | tests nous; CI en cua |
| `DebtClaimCoordinator` | preview, escalat d'avisos i reconciliació | **Sí, afegit en auditoria** | tests nous; CI en cua |
| `notification_outbox` UC-012 | avisos + cancel·lació a saldo zero | **Sí, afegit en auditoria** | tests nous; CI en cua |
| API + bridge intranet | HMAC + CSRF + rol + factura explícita | **Sí, desactivat per flag** | boundary/contract tests; CI en cua |

## 5. Troballa inicial i correcció aplicada

En la base inicial **no** existia un `DebtClaimCoordinator`. Aquesta mancança va ser confirmada per l’auditoria i corregida a la mateixa branca.

Ja implementat:
- expedient `debt_claim_case`;
- events append-only `debt_claim_event`;
- control de versió/idempotència/payload hash;
- saldo SIF i receptor fiscal;
- P-MOR-02/03/04 com events + outbox;
- P-MOR-05 amb reconciliació i tancament;
- cancel·lació d’avisos pendents;
- API HMAC i bridge intranet amb CSRF/rol;
- proves i scripts de preproducció.

Encara no existeix o no està acreditat:
- venciment/pròrroga canònics: UC-096 continua DISSENY;
- delivery real de les tres plantilles: UC-58;
- cutover dels handlers legacy;
- evidència CI/preproducció.
## 6. Riscos de frontera legacy

Els endpoints POST auditats consumeixen directament `idInsc` i invoquen mètodes de `Intranet`. En aquests fitxers no s'acredita explícitament:
1. token CSRF;
2. permís/rol específic de gestió de cobraments;
3. clau idempotent;
4. correlació/request-id;
5. outbox durable abans d'enviar email.

Això no prova absència de controls globals en una altra capa, però impedeix marcar aquesta frontera com a **VERIFICADA**.

## 7. Conclusió

**ACTUAL:** implementació funcional legacy real i fragmentada.  
**FINAL:** nucli SIF i bridge segur implementats a la branca d’auditoria.  
**COBRAMENT POST-RECLAMACIÓ:** implementat; reconciliació del claim també afegida.  
**VERIFICACIÓ:** tests escrits, però Actions continua en cua.  
**UC-012 E2E operatiu:** pendent de delivery, cutover i preproducció; scheduler bloquejat per UC-096.
