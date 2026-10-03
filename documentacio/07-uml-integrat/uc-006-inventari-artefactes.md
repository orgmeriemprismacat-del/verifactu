# UC-006 · Inventari d'artefactes, diagrames, codi i proves

**Data de tall:** 2026-10-03  
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
| Tests | `ManualRefundServiceTest.php` | EXISTEIXEN |

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
| Tests | `CreditBalanceServiceTest.php` | EXISTEIXEN |

## 4. Ledger de fons per inscripció — troballa addicional

La base de traçabilitat quantitativa **sí existeix parcialment**:

| Peça | Estat |
| --- | --- |
| `2026_09_30_000030_add_enrollment_fund_movement.sql` | IMPLEMENTADA AL REPOSITORI |
| `EnrollmentFundMovementRepository.php` | IMPLEMENTAT PARCIAL |
| `CourseEnrollmentFundAllocationService.php` | IMPLEMENTAT |
| `PackEnrollmentFundAllocationService.php` | IMPLEMENTAT |
| `CourseEnrollmentFundAllocationServiceTest.php` | PROVES EXISTENTS |

La migració accepta:
- `EXTERNAL_ALLOCATION`;
- `INTERNAL_TRANSFER`;
- `REVERSAL`;
- `COMPENSATION_ALLOCATION`.

El repositori implementa explícitament:
- `insertOrReuseExternalAllocation()`;
- `insertOrReuseCompensationAllocation()`;
- idempotència per `IDEMPOTENCY_KEY`;
- comprovació del payload en reús;
- lock del `payment_transaction`.

### Límit respecte UC-006

No s'ha localitzat wiring genèric des de:
- `ManualRefundService` cap a una sortida per `ID_INSC`;
- `CreditBalanceService::createCredit()` cap al ledger;
- `CreditBalanceService::applyCredit*()` cap a `insertOrReuseCompensationAllocation()`.

Tampoc existeix al contracte actual de la taula una referència directa a `UUID_CREDIT` per modelar de manera completa `INSCRIPCIÓ → CREDIT` i `CREDIT → INSCRIPCIÓ`.

Per tant, el ledger passa de **“inexistent”** a **“base implementada, integració UC-006 parcial”**.

## 5. Auditoria funcional i events

| Peça | Estat |
| --- | --- |
| `payment_action_event` schema | EXISTEIX |
| `PaymentActionEventRepository` | EXISTEIX |
| `PaymentActionGateway` | EXISTEIX |
| `operational_event` schema | EXISTEIX |
| `OperationalEventRepository` | EXISTEIX |
| `course_change_event` | EXISTEIX A MIGRACIÓ |
| `enrollment_cancellation_event` | EXISTEIX A MIGRACIÓ |
| Wiring universal de refund/saldo/compensació a aquests events | PENDENT |

La infraestructura no és el problema principal: el buit és la **integració obligatòria** del flux UC-006 amb aquests components.

## 6. Implementació que encara falta

| ID | Peça | Estat |
| --- | --- | --- |
| C-01 | `Uc006Controller`/command segur | FALTA |
| C-02 | `Uc006DecisionService` o equivalent | FALTA |
| C-03 | Loader autoritatiu de dret econòmic per origen/inscripció | FALTA GENERALITZAR |
| C-04 | Càlcul de disponibilitat a partir d'`enrollment_fund_movement` | FALTA |
| C-05 | Sortida de refund vinculada a inscripció | FALTA |
| C-06 | Idempotència tècnica de `createCredit()` | **IMPLEMENTADA A LA BRANCA** · falta derivar/obligar clau de dret de negoci |
| C-07 | Traça del `UUID_CREDIT` al ledger per crear/aplicar saldo | FALTA MODELAR |
| C-08 | Política de titularitat saldo/factura | FALTA |
| C-09 | Evidència externa de refund i estat pending/confirmed | FALTA GENÈRIC |
| C-10 | Wiring de `PaymentActionGateway` | FALTA UC-006 |
| C-11 | Wiring amb baixa | FALTA |
| C-12 | Wiring amb canvi curs | FALTA |
| C-13 | Separació definitiva de la UI “A TORNAR” | FALTA |
| C-14 | Sync llegat post-COMMIT | FALTA / CAL VALIDAR |
| C-15 | E2E a `sif_test*` / `sif_pre` | FALTA EVIDÈNCIA |

## 7. Proves existents vs proves que falten

### Existents

- refund parcial;
- refund complet;
- refund sobre factura inexistent;
- saldo creat;
- compensació parcial;
- compensació completa;
- mateixa K de compensació amb payload diferent → conflicte (**test afegit; CI pendent**);
- compensació > saldo;
- compensació > deute;
- atribució inicial de curs per inscripció;
- reintent idempotent d'atribució;
- fraccions separades.

### Falten per tancament UC-006

- mateixa clau/payload de saldo reutilitza UUID i mateixa clau/payload diferent conflicta (**tests afegits; CI pendent**);
- falta provar doble dret/origen amb claus de negoci derivades pel futur orquestrador;
- refund superior al fons atribuït encara disponible;
- refund de només una inscripció d'una factura conjunta;
- refund Redsys/manual del mateix fet extern;
- ledger de sortida d'una devolució;
- ledger de creació d'un saldo;
- ledger de consum d'un saldo;
- titular incompatible;
- concurrència refund vs saldo sobre el mateix dret;
- audit REQUESTED + terminal;
- E2E baixa → decisió → efecte econòmic;
- E2E canvi → repartiment → refund/saldo;
- fallada de sync després de COMMIT SIF.

## 8. Criteri de tancament

### Documentació

**COMPLETA per a l'auditoria estàtica UC-006.**

### Nucli de dades i serveis

**PARCIALMENT IMPLEMENTAT.** Hi ha més base del que indicava la primera lectura: ledger d'atribució, events i serveis econòmics existeixen.

### Integració funcional

**PENDENT.** No hi ha encara un flux únic que consumeixi aquestes peces i garanteixi una sola decisió econòmica segura.

### Verificació

**PENDENT D'EXECUCIÓ.** Els tests existents al repositori no equivalen a evidència d'aquesta branca fins que CI o una execució controlada els confirmi.
