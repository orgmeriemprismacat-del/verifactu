# UC-006 · Inventari d'artefactes, diagrames, codi i proves

**Data de tall:** 2026-10-04  
**Branca:** `audit/uc-006-2026-10-03`

Aquest inventari respon la pregunta: **tenim totes les fitxes, diagrames i codi necessaris per considerar UC-006 tancat?**

**Resposta:** la cobertura documental d'auditoria queda completa en aquesta branca; el nucli econòmic té peces executables importants, però la decisió UC-006, la integració amb les pantalles i alguns guards de negoci continuen pendents.

## 1. Artefactes documentals

| Tipus exigit | Fitxer | Estat |
| --- | --- | --- |
| Fitxa funcional | `documentacio/06-fitxes-funcionals/uc-006.md` | EXISTEIX · RECONCILIADA |
| Fitxa/UML integrada | `uc-006-devolucio-saldo-compensacio.md` | EXISTEIX · ACTUALITZADA |
| Classes ACTUAL/FINAL | `uc-006-classes-actual-final.md` | CREAT |
| Seqüències ACTUAL/FINAL | `uc-006-sequencies-actual-final.md` | CREAT |
| Activitats ACTUAL/FINAL per pàgina | `uc-006-activitats-pagines-actual-final.md` | CREAT |
| Auditoria i traçabilitat | `uc-006-auditoria-tracabilitat-2026-10-03.md` | CREAT |
| Inventari mestre | aquest document | CREAT |
| Pla de verificació test/preproducció | `uc-006-verificacio-test-preproduccio-2026-10-04.md` | CREAT · EXECUCIÓ PENDENT |
| Model transversal de fons per inscripció | `00-revisio-moviments-inscripcions.md` | EXISTEIX · RECONCILIAT AMB CODI |

No cal crear fitxers buits addicionals. El paquet documental objectiu queda cobert.

## 2. Superfícies ACTUALS localitzades

| Superfície | Fitxer principal | Funció | Estat |
| --- | --- | --- | --- |
| Consulta/Modifica alumne | `codi-drive/intranet-actual/alumnes-mostrar-alumne.php` | shell baixa/canvi | EXISTEIX |
| JS runtime alumne | `js/alumnes-mostrar-alumne.min.js` | baixa i canvi reals | EXISTEIX |
| Modal baixa | `ajax/alumnes/mostraModalDonarBaixa.php` | lectura/modal | EXISTEIX |
| Confirmació baixa | `ajax/alumnes/confirmacioBaixa_DonarBaixa.php` | POST baixa | EXISTEIX |
| Canvi curs | `ajax/alumnes/realitzarCanviCurs_CanviCurs.php` | POST + preview SIF opcional | EXISTEIX PARCIAL |
| Consulta/Anul·la factura | `alumnes-factura.php` + `js/alumnes-factura.js` | UI A TORNAR/data devolució | EXISTEIX |
| Modal anul·lació | `mostrarModalAnulaFactura_Factures.php` | lectura + guard | EXISTEIX |
| Anul·lació | `anularFactura_Factures.php` | POST autoritzat llegat | EXISTEIX |
| Pantalla UC-006 final | — | decisió REFUND/CREDIT/REVIEW | NO LOCALITZADA |

## 3. Codi SIF econòmic localitzat

### 3.1 Devolució

| Peça | Fitxer | Estat |
| --- | --- | --- |
| Servei | `ManualRefundService.php` | IMPLEMENTAT |
| Builder | `ManualRefundPayloadBuilder.php` | IMPLEMENTAT |
| Lookup factura | `ManualPaymentInvoiceRepository.php` | IMPLEMENTAT |
| Servei comú moviment | `PaymentService.php` | IMPLEMENTAT |
| Repositori moviment | `PaymentRepository.php` | IMPLEMENTAT |
| Preview CLI | `preview-manual-refund.php` | IMPLEMENTAT NO PRODUCTIU |
| Process CLI | `process-manual-refund.php` | IMPLEMENTAT NO PRODUCTIU |
| Tests | `ManualRefundServiceTest.php` | EXISTEIXEN · AMPLIATS UC-006 |
| Runner selectiu | `sif/tests/run-uc006-tests.php` | CREAT · PENDENT EXECUCIÓ |
| Workflow selectiu | `.github/workflows/uc006-sif-checks.yml` | CREAT · WORKFLOW ENCOLAT |

### 3.2 Saldo i compensació

| Peça | Fitxer | Estat |
| --- | --- | --- |
| Servei | `CreditBalanceService.php` | IMPLEMENTAT PARCIAL · `createCredit()` idempotent amb clau i reús de compensació protegit per hash de payload |
| Builder | `CreditBalancePayloadBuilder.php` | IMPLEMENTAT |
| Repositori | `CreditBalanceRepository.php` | IMPLEMENTAT |
| Preview saldo | `preview-credit-balance.php` | IMPLEMENTAT NO PRODUCTIU |
| Process saldo | `process-credit-balance.php` | IMPLEMENTAT NO PRODUCTIU |
| Preview compensació | `preview-credit-compensation.php` | IMPLEMENTAT NO PRODUCTIU |
| Process compensació | `process-credit-compensation.php` | IMPLEMENTAT NO PRODUCTIU |
| Tests | `CreditBalanceServiceTest.php` | EXISTEIXEN · AMPLIATS UC-006 |

## 4. Ledger de fons per inscripció — estat executable 04/10/2026

| Peça | Estat |
| --- | --- |
| `2026_09_30_000030_add_enrollment_fund_movement.sql` | IMPLEMENTADA · atribució base |
| `2026_10_04_000034_extend_enrollment_fund_exits.sql` | AFEGIDA A LA BRANCA · `UUID_CREDIT`, `REFUND_EXIT`, `CREDIT_CREATE` |
| `EnrollmentFundMovementRepository.php` | IMPLEMENTAT AMPLIAT |
| `CourseEnrollmentFundAllocationService.php` | IMPLEMENTAT |
| `PackEnrollmentFundAllocationService.php` | IMPLEMENTAT |
| `CreditBalanceService.php` | CABLEJAT a `CREDIT_CREATE` i `COMPENSATION_ALLOCATION` quan hi ha inscripció explícita |
| `ManualRefundService.php` | CABLEJAT a `REFUND_EXIT` quan hi ha inscripció explícita |
| proves de servei | AFEGIDES · EXECUCIÓ CI/PREPROD PENDENT |

Moviments disponibles al model actual de la branca:
- `EXTERNAL_ALLOCATION`: cobrament extern → inscripció;
- `CREDIT_CREATE`: inscripció → `credit_balance`;
- `REFUND_EXIT`: inscripció → exterior, vinculat a REFUND confirmat;
- `COMPENSATION_ALLOCATION`: `credit_balance`/COMPENSATION → factura/línia/inscripció;
- `INTERNAL_TRANSFER`: **IMPLEMENTAT com a primitiva** amb `EnrollmentFundTransferService`, builder, repositori, CLI i proves;
- `REVERSAL`: **IMPLEMENTAT de forma restringida** per `INTERNAL_TRANSFER`; reús idempotent, guard de saldo destí i rebuig d'altres tipus.

El repositori també implementa `availableAmountForInscription()` i impedeix que les sortides `CREDIT_CREATE`/`REFUND_EXIT` superin el dret net atribuït. Les sortides i el moviment econòmic corresponent comparteixen transacció quan el caller identifica la inscripció.

**Límit actual:** la UI/orquestrador encara no obliga aquests identificadors; la titularitat i l'evidència externa de refund continuen pendents.

## 5. Auditoria funcional i events

| Peça | Estat |
| --- | --- |
| `payment_action_event` schema | EXISTEIX |
| `PaymentActionEventRepository` | EXISTEIX |
| `PaymentActionGateway` | EXISTEIX · cablejat a transfer/reversal |
| `operational_event` schema | EXISTEIX |
| `OperationalEventRepository` | EXISTEIX |
| `course_change_event` | EXISTEIX A MIGRACIÓ |
| `enrollment_cancellation_event` | EXISTEIX A MIGRACIÓ |
| Wiring universal de refund/saldo/compensació a aquests events | PENDENT |

La infraestructura no és el problema principal: el buit és la **integració obligatòria** del flux UC-006 amb aquests components.

## 6. Implementació que encara falta

> **Gap de traça encara obert:** `INTERNAL_TRANSFER` és quantitativament segur per inscripció, però no conserva encara un enllaç físic obligatori al `UUID_PAYMENT`/`EXTERNAL_ALLOCATION` que finança cada tram quan una inscripció agrega diversos cobraments.

| ID | Peça | Estat |
| --- | --- | --- |
| C-01 | `Uc006Controller`/command segur | FALTA |
| C-02 | `Uc006DecisionService` o equivalent | FALTA |
| C-03 | Disponibilitat quantitativa per inscripció | **IMPLEMENTADA** · `availableAmountForInscription()` |
| C-04 | Refund vinculat a inscripció | **IMPLEMENTAT OPCIONAL** · `REFUND_EXIT` amb rollback |
| C-05 | Alta saldo consumint dret origen | **IMPLEMENTADA OPCIONAL** · `CREDIT_CREATE` |
| C-06 | Idempotència tècnica de `createCredit()` | **IMPLEMENTADA** |
| C-07 | `UUID_CREDIT` al ledger i aplicació a inscripció | **IMPLEMENTAT** · FK + `COMPENSATION_ALLOCATION` |
| C-08 | Política de titularitat saldo/refund/factura | FALTA · BLOQUEJANT |
| C-09 | Evidència externa de refund / pending-confirmed | FALTA GENÈRIC |
| C-10 | Wiring de `PaymentActionGateway` | **PARCIAL** · `REALLOCATE`/`UNALLOCATE` implementats; resta refund/saldo/compensació/controller |
| C-11 | Wiring amb baixa | FALTA |
| C-12 | Primitiva `INTERNAL_TRANSFER` | **IMPLEMENTADA** · builder + servei + repo + CLI + proves |
| C-12c | `REVERSAL` de transfer | **IMPLEMENTAT RESTRINGIT** · només `INTERNAL_TRANSFER`, amb guard de disponible |
| C-12b | Wiring amb canvi curs / coordinator | FALTA |
| C-12d | Proveniència exacta transfer→cobrament origen | FALTA · model/partició per tram |
| C-13 | Separació definitiva UI “A TORNAR” | FALTA |
| C-14 | Sync llegat post-COMMIT | FALTA / CAL VALIDAR |
| C-15 | E2E a `sif_test*` / `sif_pre` | FALTA EVIDÈNCIA |
| C-16 | Concurrència real multi-sessió sobre mateix dret | FALTA EVIDÈNCIA |

## 7. Proves existents vs proves que falten

### Proves existents o afegides a la branca

- refund parcial/complet i factura inexistent;
- reintent REFUND idempotent;
- `REFUND_EXIT` contra inscripció;
- rollback de refund quan un saldo ja ha consumit prou dret;
- creació de saldo;
- mateixa K/payload de saldo → reús del mateix UUID;
- mateixa K amb payload diferent → conflicte;
- `correlation_id` diferent en reintent → mateix fet econòmic;
- `CREDIT_CREATE` i disponibilitat restant;
- rollback d'una segona alta de saldo per fons insuficients;
- compensació parcial/completa;
- compensació > saldo / > deute;
- mateixa K compensació amb payload diferent → conflicte;
- `COMPENSATION_ALLOCATION` cap a inscripció destí;
- rollback si la inscripció destí no pertany a la factura;
- atribució inicial de curs per inscripció, reintent i fraccions.
- transferència A→B, reintent amb correlació nova, K contradictòria i sobretraspàs;
- cadena A→B→C sense nou CHARGE;
- reversió idempotent d'A→B quan B conserva el valor;
- bloqueig de reversió si B ja ha gastat part del transfer;
- rebuig de reversió d'una `EXTERNAL_ALLOCATION` amb el servei de transfer;

### Falten per tancament UC-006

- titular incompatible en refund/saldo/compensació;
- refund Redsys/manual del mateix fet extern;
- generació/autorització de K explícita per ordres de compensació des de la UI (el servei ja suporta dues K diferents);
- wiring del `CourseChangeCoordinator` a `EnrollmentFundTransferService`;
- concurrència simultània real de dues sessions consumint el mateix dret;
- audit REQUESTED + terminal per refund/saldo/compensació/controller (transfer/reversal ja coberts);
- autorització endpoint;
- E2E baixa → decisió → efecte econòmic;
- E2E canvi → reassignació/refund/saldo;
- fallada de sync després de COMMIT SIF.

## 7.1. Infraestructura de verificació afegida

| Peça | Estat |
| --- | --- |
| `sif/tests/run-uc006-tests.php` | CREAT · suite selectiva amb BD MySQL de test |
| `.github/workflows/uc006-sif-checks.yml` | CREAT · MySQL 8.4 + lint + runner |
| Workflow UC-006 | GitHub Actions es dispara correctament, però les execucions continuen en estat `queued` |
| `uc-006-verificacio-test-preproduccio-2026-10-04.md` | CREAT · passos, queries i criteris d'evidència |
| Execució `sif_test` | PENDENT |
| Execució `sif_pre` controlada | PENDENT |

**Precaució:** el runner selectiu usa `TestDatabase::fresh()` i només s'ha d'executar sobre una BD de test descartable; la guia prohibeix usar-lo contra preproducció compartida o producció.

## 8. Criteri de tancament



### Documentació

**COMPLETA per a l'auditoria estàtica UC-006.**

### Nucli de dades i serveis

**PARCIALMENT IMPLEMENTAT.** Hi ha més base del que indicava la primera lectura: ledger d'atribució, events i serveis econòmics existeixen.

### Integració funcional

**PENDENT.** No hi ha encara un flux únic que consumeixi aquestes peces i garanteixi una sola decisió econòmica segura.

### Verificació

**PENDENT D'EXECUCIÓ.** Els tests existents al repositori no equivalen a evidència d'aquesta branca fins que CI o una execució controlada els confirmi.
