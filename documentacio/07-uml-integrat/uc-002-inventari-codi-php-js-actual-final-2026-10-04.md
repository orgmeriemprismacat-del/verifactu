# UC-002 · Inventari PHP/JS i documentació ACTUAL / FINAL · 2026-10-04

## 1. Tall auditat

- Repositori: `orgmeriemprismacat-del/verifactu`
- Base: `main@2bd2a751832fc3f767b1e250b955922145a5577a`
- Branca: `audit/uc-002-reconciliada-2026-10-04`
- Abast: registrar cobrament sobre factura existent, pantalla intranet, nucli SIF, idempotència, persistència, auditoria, atribució per inscripció i proves.

## 2. Documentació

| Fitxer | Estat abans | Estat després |
|---|---|---|
| `documentacio/06-fitxes-funcionals/uc-002.md` | genèric/desalineat | **reconciliat** |
| `uc-002-registrar-cobrament-factura.md` | existent, amb parts històriques | enllaçat a l'auditoria actual |
| `uc-002-classes-actual-final.md` | no a main | **creat** |
| `uc-002-sequencies-actual-final.md` | no a main | **creat** |
| `uc-002-activitats-pagines-actual-final.md` | no a main | **creat** |
| inventari datat | no a main | **aquest fitxer** |
| auditoria/traçabilitat datada | no a main | **creada** |

## 3. Nucli SIF

| Fitxer | Responsabilitat | Estat |
|---|---|---|
| `sif/src/Service/PaymentService.php` | alta, reús, conflicte | IMPLEMENTAT |
| `sif/src/Service/PaymentPayloadValidator.php` | contracte i invariants monetaris | IMPLEMENTAT / ENDURIT |
| `sif/src/Service/PayloadIdempotencyValidator.php` | hash canònic v2 + compatibilitat | IMPLEMENTAT |
| `sif/src/Repository/PaymentRepository.php` | transaction + allocations + estat factura | IMPLEMENTAT |
| `sif/src/Domain/PaymentStatusCalculator.php` | estat econòmic | IMPLEMENTAT |
| `sif/public/api/payments/register.php` | frontera HTTP | IMPLEMENTAT / ENDURIT A BRANCA |
| `sif/config/sif.php` | path signat + rols | IMPLEMENTAT A BRANCA |

### Invariants confirmats

- imports estrictament positius;
- màxim dos decimals;
- suma exacta d'assignacions = moviment;
- idempotència per payload versionat;
- reintent equivalent reutilitza UUID;
- payload diferent amb mateixa clau falla;
- factura no es reemet;
- l'estat de cobrament deriva del ledger.

## 4. Persistència

| Objecte | Paper | Estat |
|---|---|---|
| `payment_transaction` | moviment econòmic | IMPLEMENTAT |
| `payment_allocation` | imputació a factura | IMPLEMENTAT |
| `factura.ESTAT_COBRAMENT` | estat agregat | IMPLEMENTAT |
| `payment_action_event` | traça funcional | MODEL + REPOSITORI; WIRING GENÈRIC PENDENT |
| `enrollment_fund_movement` | atribució per inscripció | MODEL + REPOSITORI; WIRING GENÈRIC PENDENT |

## 5. Frontera HTTP i seguretat

### `sif/public/api/payments/register.php`

A la branca:

- POST-only;
- `Cache-Control: private, no-store`;
- raw body signat;
- `InternalApiAuthenticator`;
- HMAC + timestamp;
- UUID request-id;
- replay guard via `InternalApiRequestRepository`;
- actor i rols signats;
- `payments.write_roles` obligatori i fail-closed;
- `internal_api.payment_signed_path`.

**Pendent:** connectar `PaymentActionGateway` i una política de negoci específica de factura/evidència externa.

## 6. Intranet llegada — fitxers de pàgina

| Fitxer | Paper | Estat |
|---|---|---|
| `codi-drive/intranet-actual/alumnes-pagaments.php` | shell de pantalla | IMPLEMENTAT |
| `codi-drive/intranet-actual/js/alumnes-pagaments.js` | cerca, modal, mutació | IMPLEMENTAT; POST A BRANCA |
| `ajax/alumnes/buscarInfomacioPagament.php` | cerca | IMPLEMENTAT |
| `ajax/alumnes/mostrarModalConfPag.php` | confirmació | IMPLEMENTAT |
| `ajax/alumnes/efectuarPagament.php` | mutació | IMPLEMENTAT; ENDURIT |
| còpia `intranet-nova-canvis-verifactu/ajax/alumnes/efectuarPagament.php` | mutació futura | ALINEADA |
| `codi-drive/intranet-actual/Intranet.php` | lògica real | **LOCALITZAT I AUDITAT COM BLOB** |
| `codi-drive/intranet-nova-canvis-verifactu/Intranet.php` | còpia de canvis | LOCALITZAT I CORREGIT |

## 7. Mètodes legacy localitzats

### `Intranet::mostrarPagaments()`

Implementa cerques per DNI, grup, pack, regal i número de factura. Per factura usa `buscarPagamentsByFact`, que uneix `inscripcions` amb `factures` i retorna `E_FACT`.

### `Intranet::efectuarPagament()`

Bifurca per `efact`:

- `efact=1`: `efectuarPagamentFacturaGenerada()`;
- `efact=0`: calcula ordre/número i deriva a rutines d'inscripció, grup, pack o regal.

### `Intranet::efectuarPagamentFacturaGenerada()`

Abans de la correcció:

- executava debug `echo`;
- `updFactGenerada` feia `UPDATE factures SET data_pagament=?, IMPORT=?, FORMA_PAGAMENT=?`;
- una fracció inferior al pendent podia fer `PAGAMENT = última_fracció`, perdent l'acumulat previ.

Després de la correcció:

- no hi ha debug echoes executables dins el mètode;
- `factures.IMPORT` es conserva;
- una fracció parcial fa `PAGAMENT = PAGAMENT_anterior + nova_fracció`.

## 8. Consultes legacy rellevants

- `buscarPagamentsByFact`: agrupa inscripcions per `FACTURA_RELACIONADA`, només amb `A_PAGAR > PAGAMENT`.
- `updFactGenerada`: ara només data de pagament + forma, sense tocar import.
- `searchMembresFactRel`: membres de la factura ordenats per ID.
- `updPayInscr`: actualitza `PAGAMENT` i `FACTURA_RELACIONADA`.
- `updDateInscr`: actualitza `DATA PAG`.
- `updFraccBDByFact`: registra text de fraccions.

**Limitació:** el repartiment per ordre d'ID és lògica llegada; no és equivalent a un ledger explícit per `ID_INSC`.

## 9. Proves

| Prova | Cobertura |
|---|---|
| `RegisterPaymentTest` | alta, allocation, no nou registre fiscal, estat i reintent |
| `PayloadIdempotencyFlowTest` | conflictes i compatibilitat v1/v2 |
| `PaymentStatusCalculatorTest` | estats econòmics |
| `PaymentPayloadValidatorTest` | camps + imports + precisió + suma |
| `ManualPaymentServiceTest` | adaptador manual |
| `Uc002PaymentApiBoundaryTest` | POST/HMAC/rol/config |
| `Uc002LegacyPaymentBoundaryTest` | POST/sessió/origen/rol/input |
| `Uc002LegacyExistingInvoiceTest` | import fiscal immutable + acumulació parcial + no debug |

## 10. CI

El workflow `.github/workflows/sif-tests.yml` s'ha actualitzat perquè canvis als fitxers UC-002 de la intranet activin:

- lint PHP de les dues còpies `Intranet.php`;
- lint PHP dels endpoints;
- `node --check` del JS;
- suite `php sif/tests/run-tests.php`.

A la base `main@2bd2a751...`, el run de SIF estava encara en cua en el moment del tall. El commit immediatament anterior `04a484...` havia fallat. Per això **no es marca CI com a verificada** fins al run de la branca/PR.

## 11. Infraestructura existent però no integrada al UC-002 genèric

| Component | Existeix | Integrat al register genèric |
|---|---:|---:|
| `PaymentActionGateway` | sí | no |
| `PaymentActionEventRepository` | sí | no |
| `InternalApiAuthenticator` | sí | **sí a branca** |
| `InternalApiRequestRepository` | sí | **sí a branca** |
| `EnrollmentFundMovementRepository` | sí | no |
| `CourseEnrollmentFundAllocationService` | sí | només fluxos específics |

## 12. Codi FINAL encara necessari

1. adaptador intranet → SIF per factura existent;
2. generació/reús durable de request-id + idempotency key a la UI;
3. reconciliador d'evidència externa;
4. orquestració auditada sense transaccions imbricades;
5. atribució genèrica per `ID_INSC`;
6. sync legacy post-commit i retryable;
7. outbox de notificacions;
8. contracte JSON `CREATED/REUSED/CONFLICT/PENDING_RETRY/ERROR`;
9. E2E/preproducció amb evidència.

## 13. Fitxers modificats/creats en aquesta auditoria

### Codi/config/CI

- `sif/src/Service/PaymentPayloadValidator.php`
- `sif/tests/Unit/PaymentPayloadValidatorTest.php`
- `sif/public/api/payments/register.php`
- `sif/config/sif.php`
- `sif/tests/Integration/Uc002PaymentApiBoundaryTest.php`
- `codi-drive/intranet-actual/js/alumnes-pagaments.js`
- les dues còpies de `ajax/alumnes/efectuarPagament.php`
- les dues còpies de `Intranet.php`
- `sif/tests/Integration/Uc002LegacyPaymentBoundaryTest.php`
- `sif/tests/Integration/Uc002LegacyExistingInvoiceTest.php`
- `.github/workflows/sif-tests.yml`

### Documentació

- fitxa funcional UC-002;
- classes ACTUAL/FINAL;
- seqüències ACTUAL/FINAL;
- activitats per pàgina;
- aquest inventari;
- auditoria/traçabilitat datada.
