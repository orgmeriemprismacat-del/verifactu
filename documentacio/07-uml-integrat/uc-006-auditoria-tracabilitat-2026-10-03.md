# UC-006 · Auditoria detallada i traçabilitat — 2026-10-03

## 1. Objecte de l’auditoria

Auditoria estàtica exhaustiva de **UC-006 — Registrar devolució, saldo o compensació** sobre `main` a partir del commit base `b0e8ff7150c5a8b415cc109d298d82f0db1f68df`.

S’han revisat:
- fitxa funcional;
- fitxa/UML integrada;
- PHP/JS real de les superfícies llegades;
- serveis, builders i repositoris SIF;
- proves d’integració existents;
- scripts de preview/process;
- traçabilitat amb UC-05, UC-28, UC-29 i UC-29a;
- mancances de classes, seqüències i activitats ACTUAL/FINAL.

## 2. Conclusió executiva

UC-006 **no estava complet documentalment** segons el patró “fitxa → classes A/F → seqüències A/F → activitats per pàgina → auditoria”. Abans d’aquesta branca existien:
1. la fitxa funcional genèrica `documentacio/06-fitxes-funcionals/uc-006.md`;
2. la fitxa/UML integrada `uc-006-devolucio-saldo-compensacio.md`.

No existien peces dedicades UC-006 de classes ACTUAL/FINAL, seqüències ACTUAL/FINAL ni activitats per pàgina/apartat, i la fitxa funcional atribuïa com a “CONFIRMAT” garanties transversals que el codi concret no demostra.

### Estat real

- **DOCUMENTAT:** ara queda cobert el conjunt documental principal.
- **IMPLEMENTAT:** existeixen els serveis base UC-28/29/29a.
- **VERIFICAT ESTÀTICAMENT:** contractes, wiring absent i proves existents han estat contrastats al repositori.
- **PENDENT:** UC-006 com a orquestració de negoci i integració real.
- **PENDENT BLOQUEJANT:** titularitat/dret, límit retornable, origen idempotent de saldo, evidència externa del REFUND, auditoria transversal i E2E.

## 3. Inventari documental abans/després

| Artefacte | Abans | Branca d’auditoria |
| --- | --- | --- |
| Fitxa funcional | Sí, v1.1 genèrica | **Actualitzada a v2.0 reconciliada** |
| Fitxa/UML integrada | Sí | Sí; s’enllaça amb auditoria |
| Classes ACTUAL/FINAL | **No** | **Creat** |
| Seqüències ACTUAL/FINAL | **No** | **Creat** |
| Activitats ACTUAL/FINAL per pàgina | **No** | **Creat** |
| Auditoria/traçabilitat dedicada | **No** | **Creat** |
| Proves de servei refund | Sí | Identificades |
| Proves de servei saldo/compensació | Sí | Identificades |
| Prova E2E UC-006 | No localitzada | Pendent |

## 4. Inventari de codi PHP/JS

### 4.1 Superfície alumne

| Fitxer | Funció UC-006 | Estat |
| --- | --- | --- |
| `alumnes-mostrar-alumne.php` | shell de pàgina, CSRF lifecycle, carrega JS runtime | IMPLEMENTAT |
| `js/alumnes-mostrar-alumne.min.js` | baixa i canvi de curs reals | IMPLEMENTAT |
| `mostraModalDonarBaixa.php` | consulta modal baixa | IMPLEMENTAT |
| `confirmacioBaixa_DonarBaixa.php` | mutació baixa amb POST/CSRF/permís | IMPLEMENTAT |
| `realitzarCanviCurs_CanviCurs.php` | mutació canvi; preview SIF opcional | IMPLEMENTAT PARCIAL |
| Adaptador devolució/saldo després de baixa/canvi | execució UC-006 | **NO LOCALITZAT** |

**Nota important:** la pàgina executa `alumnes-mostrar-alumne.min.js?ver=1.6`; el fitxer minificat és evidència de runtime i no s’ha substituït per inferències del source no minificat.

### 4.2 Superfície factura

| Fitxer | Funció | Estat |
| --- | --- | --- |
| `alumnes-factura.php` | shell Consulta/Anul·la factura | IMPLEMENTAT |
| `js/alumnes-factura.js` | modal i POST d’anul·lació | IMPLEMENTAT |
| `mostrarModalAnulaFactura_Factures.php` | modal llegat, guard SIF | IMPLEMENTAT |
| `anularFactura_Factures.php` | POST autoritzat cap a `Intranet::anularFactura` | IMPLEMENTAT |
| Wiring a `ManualRefundService` | convertir retorn confirmat en REFUND | **NO LOCALITZAT** |

### 4.3 SIF devolució

| Component | Observació | Estat |
| --- | --- | --- |
| `ManualRefundService` | cerca factura i registra payload via PaymentService | IMPLEMENTAT |
| `ManualRefundPayloadBuilder` | import/data/mètode + idempotency key | IMPLEMENTAT |
| `PaymentService` | clau + hash, reús i recovery duplicate key | IMPLEMENTAT |
| `PaymentRepository` | transaction/allocation + recàlcul cobrament | IMPLEMENTAT |
| `ManualRefundServiceTest` | parcial, total, missing invoice | PROVES EXISTENTS |
| evidència externa retorn | no forma part del servei | PENDENT |
| límit retornable | no comprovació específica localitzada | PENDENT BLOQUEJANT |

### 4.4 SIF saldo i compensació

| Component | Observació | Estat |
| --- | --- | --- |
| `CreditBalanceService::createCredit` | crea saldo ACTIVE | IMPLEMENTAT |
| `CreditBalanceService::applyCredit*` | locks, límit saldo/deute, consumeix | IMPLEMENTAT |
| `CreditBalancePayloadBuilder` | payload de saldo i compensació | IMPLEMENTAT |
| `CreditBalanceRepository` | persistència + outstanding invoice | IMPLEMENTAT |
| `CreditBalanceServiceTest` | creació, parcial, total, límits | PROVES EXISTENTS |
| idempotència creació saldo | no acreditada | PENDENT BLOQUEJANT |
| titularitat saldo vs factura | no acreditada | PENDENT BLOQUEJANT |

## 5. Contrast de la fitxa funcional antiga amb el codi

### 5.1 REQUEST_ID / CORRELATION_ID

La fitxa antiga deia que existien abans del commit. Els builders/serveis UC-006 examinats no exigeixen `request_id` ni `correlation_id`.

**Resultat d’auditoria:** **DISSENY / PENDENT D’INTEGRACIÓ**, no “confirmat”.

### 5.2 payment_action_event

La fitxa antiga deia que tota petició/consulta/denegació/reús/resultat genera `payment_action_event`.

`PaymentActionGateway` existeix. Però:
- `ManualRefundService` delega directament a `PaymentService`;
- `CreditBalanceService` opera directament amb repositoris;
- els scripts inspeccionats instancien aquests serveis;
- `public/api/payments/register.php` instancia `PaymentService` directament.

**Resultat:** la garantia no queda acreditada per UC-006.

### 5.3 Persistència mínima

La fitxa antiga agrupava `sif_audit_event`, `operational_event`, `payment_action_event`, `payment_transaction/payment_allocation` com a mínim confirmat.

**Codi observat en refund:** `payment_transaction` + `payment_allocation` + estat cobrament.  
**Codi observat en saldo:** `credit_balance`.  
**Codi observat en compensació:** transaction/allocation + credit balance.

**Resultat:** s’ha corregit la fitxa per separar implementació real de contracte objectiu.

## 6. Revisió funcional per variant

### 6.1 REFUND — UC-28

**Implementat**
- identificació de factura;
- import positiu;
- data;
- mètode manual/transferència;
- referència/banc opcionals;
- idempotència de pagament amb payload hash;
- estat de cobrament recalculat.

**No implementat/acreditat**
- dret retornable per inscripció/origen;
- retorn no superior al valor net disponible;
- titular;
- sortida bancària/Redsys confirmada;
- deduplicació cross-channel del mateix retorn extern;
- adaptador productiu UC-006.

### 6.2 CREDIT_BALANCE — UC-29

**Implementat**
- titular declarat;
- import;
- origen declarat;
- persistència ACTIVE.

**No implementat/acreditat**
- unicitat/idempotència del dret origen;
- prova que titular/origen provenen de dades autoritatives;
- reserva/consum del dret origen en la mateixa transacció;
- wiring UI.

### 6.3 COMPENSATION — UC-29a

**Implementat**
- lock saldo;
- lock/càrrega factura;
- saldo ACTIVE;
- import <= saldo disponible;
- import <= deute factura;
- moviment COMPENSATION;
- consum atòmic;
- reús idempotent.

**No implementat/acreditat**
- titular compatible;
- request ID independent de la key derivada;
- dues compensacions legítimes idèntiques diferenciables;
- gateway d’auditoria;
- wiring UI.

## 7. Revisió de seguretat de superfícies ACTUALS

### Baixa
`confirmacioBaixa_DonarBaixa.php`:
- exigeix POST;
- valida sessió;
- valida CSRF;
- same-origin;
- `assertCanEdit`;
- valida ID i motiu;
- usa guard USOC.

**Valoració:** hardening real present, però és del lifecycle llegat, no autorització econòmica UC-006.

### Canvi de curs
`realitzarCanviCurs_CanviCurs.php`:
- POST;
- sessió;
- CSRF;
- same-origin;
- `assertCanEdit`;
- guard lifecycle;
- preview SIF opcional i detecció de canvi de decisió.

**Valoració:** bona base d’integració; falta executar/traçar el resultat econòmic.

### Anul·lació factura
`anularFactura_Factures.php`:
- POST;
- sessió;
- same-origin;
- `assertCanEdit`;
- `SifLegacyInvoiceMutationGuard`.

**Valoració:** endpoint llegat endurit, però no és un endpoint REFUND.

### API genèrica de pagaments
`sif/public/api/payments/register.php` no mostra en el fitxer una capa explícita d’autorització ni `PaymentActionGateway`.

**Valoració:** no s’ha de presentar aquest endpoint per si sol com a endpoint final UC-006. La possible protecció externa del servidor/proxy no ha estat acreditada per aquesta auditoria de codi.

## 8. Idempotència i concurrència

### Correcte/localitzat

- `PaymentService`: cerca `FOR UPDATE`, hash de payload i recuperació de duplicate key.
- `CreditBalanceService::applyCredit*`: transacció, locks i recovery duplicate key.
- consum de saldo i alta de compensació queden dins una operació transaccional.

### Buit

- `createCredit()` no té contracte d’idempotency key.
- key de compensació = saldo + factura + import: pot col·lidir amb una segona intenció legítima exactament igual.
- key de refund sense referència = factura + data + import + banc: dos retorns reals idèntics el mateix dia poden necessitar un identificador extern més fort.
- amb `reference`, el builder usa `REFUND|REF:<reference>`; cal garantir semàntica global i canal.

## 9. Traçabilitat amb altres casos

| Relació | Tipus | Regla |
| --- | --- | --- |
| UC-006 → UC-28 | include/especialització | retorn monetari |
| UC-006 → UC-29 | include/especialització | creació de saldo |
| UC-006 → UC-29a | include/especialització | consum de saldo |
| UC-006 ↔ UC-05/74 | relació separada | fiscalitat no implica moviment econòmic |
| UC-026 → UC-006 | disparador possible | canvi de curs amb diferència a favor |
| UC-027 → UC-006 | disparador possible | baixa amb dret econòmic |
| UC-002 | infraestructura comuna | payment transaction/allocation |
| UC-056/105 | conciliació/reutilització | evitar duplicar un pagament/retorn existent |

## 10. Matriu DOCUMENTAT / IMPLEMENTAT / VERIFICAT / PENDENT

| Element | Documentat | Implementat | Verificat | Pendent |
| --- | :---: | :---: | :---: | --- |
| Decisió mare UC-006 | Sí | No | No | orquestrador |
| Devolució base | Sí | Sí | tests existents + contrast estàtic | guards negoci/E2E |
| Saldo base | Sí | Sí | tests existents + contrast estàtic | idempotència origen/E2E |
| Compensació base | Sí | Sí | tests existents + contrast estàtic | titular/E2E |
| Baixa actual | Sí | Sí | contrast PHP/JS | derivació econòmica |
| Canvi actual | Sí | Sí parcial | contrast PHP/JS | execució economic_decision |
| Anul·lació factura actual | Sí | Sí | contrast PHP/JS | separar retorn real |
| Classes A/F | Sí | N/A | revisat | — |
| Seqüències A/F | Sí | N/A | revisat | — |
| Activitats per pàgina A/F | Sí | N/A | revisat | — |
| Audit gateway UC-006 | Sí objectiu | No acreditat | No | integrar |
| Preproducció | Sí criteris | No acreditat | No | executar i conservar evidència |

## 11. Mancances prioritzades

### P0 — bloquejants abans d’operar diners

1. **UC006-GAP-P0-01 · Dret econòmic únic per origen/inscripció.**  
   Evitar que el mateix valor es transformi en refund i saldo, o dos saldos.

2. **UC006-GAP-P0-02 · Límit de devolució.**  
   `REFUND` no pot superar el que s’ha cobrat i continua disponible per retornar.

3. **UC006-GAP-P0-03 · Evidència externa.**  
   Diferenciar `RETURN_PENDING` de `REFUND CONFIRMED`.

4. **UC006-GAP-P0-04 · Titularitat.**  
   Retorn i saldo han d’anar al titular econòmic correcte; compensació ha de validar compatibilitat.

5. **UC006-GAP-P0-05 · Idempotència de creació de saldo.**  
   Mateix origen/dret no pot crear dos `UUID_CREDIT`.

6. **UC006-GAP-P0-06 · Orquestrador/endpoint autoritzat.**  
   Cap superfície llegada ha de decidir diners només amb camps DOM/llegats.

### P1 — traça i integració

7. `PaymentActionGateway` o equivalent per REQUESTED/terminal.
8. correlació/request id estable.
9. cross-channel dedup Redsys/manual.
10. sync llegat només post-COMMIT.
11. incidència automàtica en divergència.

### P2 — UX i operació

12. pantalla UC-006 final amb context complet;
13. indicadors RETURN_PENDING/CONFIRMED;
14. consulta del saldo i historial de consums;
15. explicació separada de l’efecte fiscal.

## 12. Proves d’acceptació requerides

| ID | Prova | Esperat |
| --- | --- | --- |
| UC006-T01 | baixa sense dret econòmic | NO_CHANGE |
| UC006-T02 | baixa amb 40 € retornables, encara no retornats | RETURN_PENDING, cap REFUND |
| UC006-T03 | retorn bancari confirmat 40 € | un REFUND |
| UC006-T04 | reintent mateix external operation id | mateix UUID_PAYMENT |
| UC006-T05 | mateix retorn registrat via Redsys i manual | un sol fet econòmic |
| UC006-T06 | demanar 120 € amb només 100 € retornables | bloqueig |
| UC006-T07 | factura grup, només una inscripció afectada | límit/traça per inscripció |
| UC006-T08 | crear saldo dues vegades mateix origen | mateix saldo o conflicte, mai duplicat |
| UC006-T09 | aplicar saldo a factura d’altre titular | bloqueig/revisió |
| UC006-T10 | compensació > saldo | bloqueig |
| UC006-T11 | compensació > deute | bloqueig |
| UC006-T12 | dues compensacions legítimes del mateix import | diferenciades per request/operació |
| UC006-T13 | fiscal rectificativa però cap retorn | cap REFUND automàtic |
| UC006-T14 | REFUND real però sync llegat falla | SIF es manté; només reintentar sync |
| UC006-T15 | concurrent refund/saldo sobre mateix dret | només un consumeix el dret |
| UC006-T16 | permís denegat | zero efecte |
| UC006-T17 | payload mateixa K però diferent | 409/CONFLICT |
| UC006-T18 | E2E des de baixa/canvi | resultat traçable de punta a punta |

## 13. Fitxers creats/modificats en aquesta auditoria

- **MODIFICAT** `documentacio/06-fitxes-funcionals/uc-006.md`
- **CREAT** `documentacio/07-uml-integrat/uc-006-classes-actual-final.md`
- **CREAT** `documentacio/07-uml-integrat/uc-006-sequencies-actual-final.md`
- **CREAT** `documentacio/07-uml-integrat/uc-006-activitats-pagines-actual-final.md`
- **CREAT** aquest document
- **A ACTUALITZAR** índex UML i fitxa integrada amb enllaços d’auditoria

## 14. Decisió sobre canvis de codi

Aquesta auditoria **no modifica la lògica PHP econòmica** perquè els gaps bloquejants requereixen una decisió explícita del model de dret econòmic i de la font de veritat del titular/origen. Afegir un `if` local a `ManualRefundService` o `CreditBalanceService` sense aquest contracte podria:
- bloquejar retorns legítims;
- permetre retorns incorrectes en factures agrupades;
- consumir saldo del titular equivocat;
- crear una falsa sensació de seguretat.

La feina de codi queda especificada amb contractes i proves perquè es pugui implementar sense inventar regles.

## 15. Criteri de tancament de l’auditoria

**Documentació:** tancable amb aquesta branca un cop l’índex i la fitxa integrada quedin actualitzats.  
**Implementació UC-006:** **NO tancada**.  
**Acceptació operativa:** **NO tancada**.  
**Motiu:** falten els P0 d’apartat 11 i evidència E2E/preproducció.
