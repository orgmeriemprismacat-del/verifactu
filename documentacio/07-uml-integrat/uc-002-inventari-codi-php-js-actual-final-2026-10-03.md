# UC-002 · Inventari de codi PHP/JS ACTUAL / FINAL · 2026-10-03

## 1. Tall auditat

- Repositori: `orgmeriemprismacat-del/verifactu`
- Base: `main@b0e8ff7150c5a8b415cc109d298d82f0db1f68df`
- Branca d’auditoria: `audit/uc-002-2026-10-03`
- Abast: cobrament sobre factura existent, pantalla llegada, nucli SIF, persistència, idempotència, auditoria, ledger per inscripció i proves.

## 2. Documentació existent abans de la passada

| Fitxer | Existia | Observació |
|---|---:|---|
| `documentacio/06-fitxes-funcionals/uc-002.md` | sí | massa genèric i amb estat parcial antic |
| `documentacio/07-uml-integrat/uc-002-registrar-cobrament-factura.md` | sí | conté UML integrat i notes útils |
| classes ACTUAL/FINAL separat | no | creat en aquesta auditoria |
| seqüències ACTUAL/FINAL separat | no | creat en aquesta auditoria |
| activitats per pàgina ACTUAL/FINAL | no | creat en aquesta auditoria |
| inventari de codi | no | aquest document |
| auditoria/traçabilitat datada | no | creada en aquesta auditoria |

## 3. Codi SIF — nucli UC-002

| Fitxer | Paper | Estat |
|---|---|---|
| `sif/src/Service/PaymentService.php` | orquestra alta/reús i conflictes | implementat |
| `sif/src/Service/PaymentPayloadValidator.php` | contracte d’entrada | implementat i endurit 03/10 |
| `sif/src/Service/PayloadIdempotencyValidator.php` | hash canònic v2 | implementat |
| `sif/src/Repository/PaymentRepository.php` | transacció econòmica i assignacions | implementat |
| `sif/src/Domain/PaymentStatusCalculator.php` | estat de cobrament | implementat |
| `sif/public/api/payments/register.php` | endpoint genèric | implementat però frontera de seguretat incompleta |

### Persistència

- `payment_transaction`: moviment extern/econòmic.
- `payment_allocation`: imputació a factura.
- `factura.ESTAT_COBRAMENT`: estat agregat.
- `PAYLOAD_HASH_VERSION=2`: persistit pel repositori actual.

## 4. Adaptadors manuals

| Fitxer | Estat |
|---|---|
| `sif/src/Service/ManualPaymentService.php` | implementat |
| `sif/src/Service/ManualPaymentPayloadBuilder.php` | implementat |
| `sif/src/Repository/ManualPaymentInvoiceRepository.php` | implementat |
| `sif/scripts/preflight-manual-payment.php` | implementat |
| `sif/scripts/preview-manual-payment.php` | implementat |
| `sif/scripts/process-manual-payment.php` | implementat |

També hi ha adaptadors relacionats de reclamació i fraccionament que deleguen a `PaymentService`; són casos relacionats, no prova que la pantalla llegada de UC-002 ja usi SIF.

## 5. Pantalla/intranet llegada

| Fitxer | Troballa |
|---|---|
| `codi-drive/intranet-actual/alumnes-pagaments.php` | shell de la pàgina |
| `codi-drive/intranet-actual/js/alumnes-pagaments.js` | cerca, modal, validacions i mutació |
| `codi-drive/intranet-actual/ajax/alumnes/buscarInfomacioPagament.php` | cerca per GET |
| `codi-drive/intranet-actual/ajax/alumnes/mostrarModalInfoPag.php` | consulta |
| `codi-drive/intranet-actual/ajax/alumnes/mostrarModalConfPag.php` | confirmació |
| `codi-drive/intranet-actual/ajax/alumnes/efectuarPagament.php` | mutació; canviada a POST + guard servidor |
| `codi-drive/intranet-nova-canvis-verifactu/ajax/alumnes/efectuarPagament.php` | còpia alineada amb el guard |
| `codi-drive/intranet-actual/Intranet.php` | **0 bytes / buit al repo** |
| `codi-drive/intranet-nova-canvis-verifactu/Intranet.php` | **0 bytes / buit al repo** |

### Conseqüència

No es pot inspeccionar ni provar des del repositori:
- `Intranet::efectuarPagament()`;
- `Intranet::mostrarPagaments()`;
- les consultes internes `buscarPagamentsByFact` o equivalents;
- l’ordre real d’escriptura a taules legacy;
- si hi ha doble escriptura econòmica/fiscal;
- si existeix idempotència durable llegada.

Qualsevol afirmació històrica sobre aquests mètodes queda classificada com **documentada, no verificada al snapshot GitHub** fins recuperar la font real.

## 6. Auditoria i actor

| Fitxer | Estat |
|---|---|
| `sif/src/Service/PaymentActionGateway.php` | existeix |
| `sif/src/Repository/PaymentActionEventWriter.php` | existeix |
| `sif/src/Repository/PaymentActionEventRepository.php` | existeix |
| `sif/database/migrations/2026_09_15_000003_add_functional_audit_control.sql` | model d’auditoria existeix |
| `sif/src/Service/InternalApiAuthenticator.php` | HMAC/replay guard existeix |
| `sif/public/api/payments/register.php` | **no els utilitza** |

## 7. Ledger per inscripció

| Fitxer | Estat |
|---|---|
| `sif/database/migrations/2026_09_30_000030_add_enrollment_fund_movement.sql` | implementat |
| `sif/src/Repository/EnrollmentFundMovementRepository.php` | implementat |
| Integració directa des de `PaymentRepository::createPayment()` | no |
| Atribució genèrica UC-002 per `ID_INSC` | pendent |

Això resol l’absència de model, però no la integració del cas genèric.

## 8. Proves localitzades

- `sif/tests/Integration/RegisterPaymentTest.php`
  - crea moviment + assignació;
  - no crea un segon registre fiscal;
  - actualitza estat;
  - reintent reutilitza UUID.
- `sif/tests/Integration/PayloadIdempotencyFlowTest.php`
  - conflicte si mateixa clau porta import/assignacions diferents;
  - compatibilitat hash v1/v2.
- `sif/tests/Unit/PaymentStatusCalculatorTest.php`
  - tots els estats econòmics principals.
- `sif/tests/Unit/PaymentPayloadValidatorTest.php`
  - ampliat amb import positiu, assignació positiva, precisió i suma exacta.
- `sif/tests/Integration/ManualPaymentServiceTest.php`
  - UUID, número visible, idempotència i parcial.
- `sif/tests/Integration/Uc002LegacyPaymentBoundaryTest.php`
  - creat per blindar POST, sessió/origen/rol, validació server-side i paritat de còpies.

## 9. CI

Al tall de partida, el workflow `SIF PHP MySQL tests` de `main@b0e8ff...` (run `37061206441`) estava **failure** i el pas fallit era `Executar suite SIF`. No s’ha pogut recuperar el log detallat amb el connector disponible.

Per tant:
- existència d’una prova ≠ execució verda al HEAD;
- cap element nou d’aquesta auditoria es classifica com “verificat en CI vigent” fins que el PR tingui el workflow verd o s’aïlli una fallada preexistent.

## 10. Codi FINAL encara necessari

1. endpoint intern UC-002 POST-only + `InternalApiAuthenticator`;
2. allowlist de rols de pagaments;
3. integració segura de `PaymentActionGateway` sense transaccions ni auditoria incoherents;
4. reconciliació d’evidència externa abans del `CHARGE`;
5. adaptador intranet → SIF amb idempotency key durable i resposta JSON tipificada;
6. atribució genèrica a `enrollment_fund_movement` quan la factura cobreix inscripcions;
7. sincronització legacy post-commit amb reintent, no nova entrada de caixa;
8. recuperar/afegir el codi real de `Intranet::efectuarPagament()` al repositori per poder-lo auditar.
