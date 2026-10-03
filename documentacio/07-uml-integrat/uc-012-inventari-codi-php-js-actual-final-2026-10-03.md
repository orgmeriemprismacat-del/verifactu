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

## 5. Allò que NO existeix com a circuit SIF complet

No s'ha localitzat un `DebtClaimCoordinator` o equivalent que executi P-MOR-01..05 de punta a punta. Tampoc queda acreditat per UC-012:
- expedient durable de reclamació;
- estat/versió de reclamació amb concurrència;
- outbox idempotent específica;
- política central de terminis;
- revalidació de pròrroga abans d'enviar;
- resolució canònica del pagador per factura/grup/empresa;
- cancel·lació d'avisos pendents quan entra un cobrament;
- adaptador intranet → SIF per les quatre pantalles legacy;
- proves E2E del cicle complet.

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
**FINAL:** disseny documentat però orquestració SIF incompleta.  
**COBRAMENT POST-RECLAMACIÓ:** implementat i amb proves.  
**UC-012 E2E:** pendent.
