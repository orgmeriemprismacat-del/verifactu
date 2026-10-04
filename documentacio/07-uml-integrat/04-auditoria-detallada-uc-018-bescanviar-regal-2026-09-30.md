# Auditoria detallada UC-018 · Bescanviar regal · 2026-09-30

> **REVALIDACIÓ 03/10/2026:** les seccions 3–17 conserven el rastre històric de com va evolucionar l'auditoria i contenen afirmacions que van quedar superades pels PR #115/#117. L'estat vigent és el de la secció 20 i de `11-revalidacio-auditoria-uc-018-2026-10-03.md`. No s'han d'interpretar els antics «no implementat/NO-GO» com a estat actual.

## 1. Abast

Auditoria de la fitxa funcional, codi PHP/JS real, persistència, UML, proves, traçabilitat i mancances del cas **UC-018 · Bescanviar regal** sobre `main`.

## 2. Veredicte per capa — reconciliat 2026-10-02

| Capa | Estat actual | Evidència / lectura |
| --- | --- | --- |
| Fitxa funcional | AUDIT_CLOSED | `documentacio/06-fitxes-funcionals/uc-018.md` v1.4 |
| UML integrat | RECONCILIAT | classes, seqüències i activitats ACTUAL/FINAL actualitzades |
| Repository GIFT | IMPLEMENTAT | lock, claim, reserve, consume, release i events |
| Compra/origen UC-017 | IMPLEMENTAT | factura + `CHARGE` original + emissió dret GIFT |
| Context autoritatiu | IMPLEMENTAT | holder/preu resolts al SIF |
| Staging inscripció | IMPLEMENTAT | get-or-create i operació `INSCRIPCIO` no facturable |
| Bescanvi | IMPLEMENTAT | `GiftRedemptionService` + orchestrator |
| Aplicació econòmica | IMPLEMENTAT | `COMPENSATION_ALLOCATION`; cap segon `CHARGE` |
| Reconciliació legacy | IMPLEMENTAT | compare-and-set de `regal.USAT` |
| Recovery/replay | IMPLEMENTAT | mateixa operació/moviment després de resposta perduda |
| Concurrència | IMPLEMENTAT | prova multiprocés integrada al PR de tancament |
| Notificacions | IMPLEMENTAT | sis outbox idempotents post-SIF + claim/complete |
| E2E intern | IMPLEMENTAT | UC-017 → GIFT → inscripció → consum → replay |
| Preflight/preproducció | IMPLEMENTAT EN CODI | execució real condicionada a entorn/dades controlades |

Les seccions 3–17 documenten l'evolució històrica. El tall autoritatiu més recent és la **revalidació 03/10** de la secció 20: nucli/SIF verificat; patch navegador→legacy aplicat i pendent de CI; preproducció real pendent d'entorn.
## 3. Fitxa funcional — troballes

La fitxa original defineix correctament que el bescanvi:

- crea/vincula una inscripció;
- no crea una factura nova per defecte;
- no ha de crear un nou cobrament pel mateix valor;
- ha de ser idempotent i auditable.

Tanmateix, abans d'aquesta auditoria la fitxa podia induir a error perquè utilitzava formulacions genèriques comunes a molts UC i no distingia prou:

1. el **codi de compra UC-017**;
2. l'**esquema SQL disponible**;
3. el **codi d'entitlement específic UC-111**;
4. el **codi de bescanvi UC-018, que no existeix**.

## 4. Codi PHP real

### 4.1. Implementat i reutilitzable

#### `RedsysGiftInvoiceService`

Cobreix la compra/factura del regal. Valida el snapshot, la notificació Redsys, l'import i emet factura/pagament per UC-017.

**No fa:** validar un dret GIFT per codi, reservar-lo, crear inscripció, consumir-lo o registrar el bescanvi.

#### `LegacyGiftSnapshotRepository`

Permet carregar `regal` per `ID` o `CODI` des de la BD llegada.

**Risc si es reutilitza directament per UC-018:** treballa amb el codi llegat en clar i no és una frontera de seguretat de dret comercial. El disseny FINAL ha d'usar `CODE_HASH`/entitlement com a autoritat, no una cerca pública directa sobre `regal.CODI`.

#### `LegacyGiftInvoicePayloadBuilder`

Construeix la factura de compra amb relació `REGAL`, incloent metadata comercial.

**No és un builder de bescanvi.**

#### `EnrollmentFundMovementRepository`

Existeix com a infraestructura econòmica relacionada amb atribució de fons a inscripcions en altres casos. Pot ser una base reutilitzable, però **no s'ha acreditat cap crida UC-018**.

### 4.2. Codi adjacent que NO acredita UC-018

`NovicePromotionGrantService` escriu `commercial_entitlement` i `commercial_entitlement_event` per un dret `FUTURE_DISCOUNT` d'UC-111.

Això prova que les taules poden ser usades, però no implementa:

- tipus `GIFT`;
- cerca per hash;
- reserva;
- consum;
- release;
- reintent de bescanvi;
- vinculació amb inscripció.

## 5. Codi JS/UI

No s'ha acreditat cap mòdul JS, pàgina web, endpoint AJAX/API o pantalla de `pay.prisma.cat` que implementi UC-018.

Per tant:

- **ACTUAL**: no hi ha pàgina de bescanvi demostrada;
- **FINAL**: s'ha documentat la superfície mínima i els guards requerits.

## 6. Persistència

### 6.1. Disponible

`2026_09_16_000005_add_operation_lifecycle_tables.sql` defineix:

- `commercial_entitlement`;
- `commercial_entitlement_event`;
- camps de titular, hash, origen, operació consumidora, regla, valor, estat i dates.

### 6.2. Mancances d'integració

No hi ha repository genèric acreditat que imposi transicions. Sense aquesta capa, el model és només potencial.

Cal impedir updates lliures i garantir:

- lock;
- idempotència;
- transicions vàlides;
- event append-only;
- vincle a operació/inscripció;
- tractament de fallada parcial.

## 7. UML

### 7.1. UML existent

`uc-018-bescanviar-regal.md` ja descrivia un bon model objectiu, però barrejava en un únic document:

- evidència de compra existent;
- classes hipotètiques;
- seqüència final.

### 7.2. Millora aplicada

Aquesta auditoria separa:

- `uc-018-classes-actual-final.md`;
- `uc-018-sequencies-actual-final.md`;
- `uc-018-activitats-pagines-bescanvi-regal-actual-final.md`.

Això evita interpretar una classe dibuixada com a classe real.

## 8. Traçabilitat funcional

### UC relacionats

- **UC-017**: compra/factura/cobrament original del regal.
- **UC-018**: bescanvi i alta/vinculació de beneficiari.
- **UC-018a**: caducat, duplicat, disputat.
- **UC-119**: cicle complet del regal.
- **UC-071/072**: canvi/baixa posterior.
- **UC-028/029**: devolució/saldo quan hi hagi decisió econòmica real.
- **UC-074**: classificació fiscal si el bescanvi implica canvi material.
- **UC-008/081**: incidència/reconciliació.

### Invariant transversal

```text
compra UC-017 = entrada de diners real
bescanvi UC-018 = aplicació d'un dret ja finançat
```

No s'ha de convertir el bescanvi en un segon cobrament.

## 9. Seguretat

Bloquejos obligatoris:

1. codi mai en URL o log;
2. buscar per hash;
3. resposta neutra per codi desconegut;
4. autoritzar el beneficiari sense exposar dades del comprador;
5. no servir factura del comprador per posseir el codi;
6. CSRF i autenticació/autorització a confirmació;
7. idempotència vinculada també al payload/destí, no només a una clau opaca.

## 10. Concurrència i recuperació

Cas crític:

- dues peticions simultànies pel mateix codi;
- una sola pot passar a `RESERVED/CONSUMED`;
- la segona ha de ser `REUSED` si és equivalent o `CONFLICT` si no ho és.

Cas parcial:

- dret reservat;
- falla la creació/vinculació de l'alumne;
- cal `RELEASE` si és segur o incidència persistent;
- mai consumir i després “oblidar” la inscripció.

## 11. Decisions encara pendents de negoci/fiscalitat

No s'han de codificar per inferència:

- política exacta de romanent quan regal > curs;
- política de diferència quan regal < curs;
- transferibilitat del dret;
- caducitat/pròrroga;
- titular d'un eventual retorn;
- curs/edicions elegibles;
- si el regal és nominal o al portador en cada modalitat.

Aquestes decisions no impedeixen implementar el nucli segur de consulta/reserva/consum, però sí impedeixen tancar totes les variants.

## 12. Proves requerides

Vegeu `05-proves-pendents-uc-018-implementacio.md`.

Mínim abans de considerar-lo implementat:

- nominal;
- replay;
- payload contradictori;
- concurrència;
- codi desconegut;
- caducat;
- consumit;
- titular incorrecte;
- alta acadèmica fallida;
- cap segon CHARGE;
- control d'accés a factura;
- diferència de preu;
- rollback/reconciliació.

## 13. Estat final de l'auditoria — actualitzat 2026-10-02

### DOCUMENTAT

Fitxa funcional, classes, seqüències, activitats per superfície, matriu de proves, invariants econòmics/fiscals, recovery, concurrència, notificacions i preproducció estan traçats.

### IMPLEMENTAT

- emissió/reutilització del dret GIFT;
- context autoritatiu i claim del holder;
- staging de la inscripció;
- redeem idempotent;
- `COMPENSATION_ALLOCATION` sobre el pagament original;
- reconciliació `regal.USAT`;
- recovery/replay;
- concurrència multiprocés;
- sis notificacions idempotents post-SIF;
- preflight/verificador de preproducció.

### VERIFICAT

- proves d'integració existents del nucli;
- E2E intern incorporat al PR de tancament;
- concurrència multiprocés incorporada al PR de tancament;
- recovery després de resposta perduda incorporat al PR de tancament;
- boundaries de notificació i preproducció incorporats al PR de tancament;
- **CI final del PR de tancament: VERD — 858 passed / 0 failed; 49 PASS GIFT/UC-018; 4/4 workflows aplicables en SUCCESS**.

### PENDENT D'ENTORN, NO D'AUDITORIA

- executar el verificador amb `--execute` en preproducció configurada;
- conservar l'evidència de l'execució real i del transport SMTP desplegat.

### VARIANTS BLOQUEJADES PER POLÍTICA

Les diferències de valor regal/curs no formen part del flux base tancat. Continuen fail-closed fins a decisió funcional sobre complement de cobrament, romanent, saldo, devolució o consum parcial.

## 14. Classificació actual

```text
Estat documental: AUDIT_CLOSED
Estat implementació: IMPLEMENTED_AND_CI_VERIFIED
Code GO: VERIFIED / CI_GREEN
Environment GO: pendent preproducció
```
## 15. Addenda d'implementació posterior a l'auditoria

Després del tall documental inicial s'ha implementat una primera fase executable:

- `CommercialEntitlementRepository` amb transicions GIFT i events;
- `GiftRedemptionService::preview()` i `redeem()`;
- suite d'integració específica UC-018.

Aquesta addenda canvia l'estat de «bescanvi no implementat» a **nucli de bescanvi implementat parcialment**. No canvia el NO-GO de producció: manca encara materialització de la inscripció, API/UI, aplicació quantitativa del valor i E2E/preproducció.


## 16. Addenda — atribució econòmica a la inscripció

S'ha afegit una fase executable addicional al nucli UC-018:

- `EnrollmentFundMovementRepository::insertOrReuseCompensationAllocation()`;
- reutilització del `CHARGE` original del regal;
- moviment `COMPENSATION_ALLOCATION` cap a `ID_INSC_DESTI`;
- idempotència estable per dret+inscripció;
- atomicitat amb `RESERVE → ALLOCATION → CONSUME`;
- bloqueig explícit de diferències de preu fins que hi hagi decisió funcional.

Això tanca el buit «aplicació de fons explícita» sense crear una factura nova ni un segon cobrament. El NO-GO es manté per la materialització de l'alta acadèmica, API/UI, concurrència multiprocés i E2E/preproducció.


## 17. Addenda — staging acadèmic i frontera HMAC

S'ha implementat `GiftEnrollmentStager` com a pont post-commit entre `enviarInscripcioBescanvia.php` i el SIF. El contracte observat del llegat queda verificat (`A_PAGAR=0`, `FACTURA_RELACIONADA=FACT_REL`, codi a `pag_observacions`, curs regal i `USAT` no contradictori). El stager crea/reutilitza una operació `ENROLLMENT/INSCRIPCIO` no facturable, reserva el dret dins la mateixa transacció i impedeix un segon destí concurrent.

També existeix l'endpoint intern POST `/api/gifts/redemption/redeem.php`, protegit amb HMAC/anti-replay i rol explícit. Orquestra staging + redeem sense persistir el codi en snapshots ni retornar-lo. La mutació final de `regal.USAT` continua separada fins implementar compare-and-set/reconciliació legacy.


## 18. Tancament integral UC-018 — 2026-10-02

### 18.1. Invariant executable

```text
1 compra pagada
1 dret GIFT
1 inscripció
1 COMPENSATION_ALLOCATION
1 consum
0 CHARGE addicionals
0 factures addicionals
replay idempotent
```

### 18.2. Concurrència

La prova multiprocés usa dos processos PHP independents i connexions MySQL separades. Amb el mateix `ID_INSC`, tots dos convergeixen sobre una sola saga; amb destins diferents, un únic procés guanya i l'altre obté conflicte.

### 18.3. Recovery

Si el SIF completa la saga però el caller perd la resposta, el writer reutilitza la mateixa `ID_INSC` i torna a entrar al SIF. Un segon `execute()` recupera la mateixa operació, el mateix moviment de fons i la mateixa reconciliació sense duplicar diners ni factura, i permet reconstruir/reutilitzar l'outbox si el primer intent es va tallar abans de completar-lo.

### 18.4. Correu

Els sis correus legacy es creen com sis notificacions durables independents. Cada correu requereix `claim`; un estat `SENDING` ambigu no es reclama de nou automàticament. Els enviaments només es produeixen després de l'èxit SIF. L'endpoint UC-018 valida a més que el `TEMPLATE_CODE` pertanyi al prefix `GIFT_REDEEM_`, de manera que el rol GIFT no pot operar sobre files d'outbox d'altres dominis.

### 18.5. Preproducció

`preflight-gift-redemption.php` és read-only. `verify-gift-redemption-preproduction.php` és dry-run per defecte i només muta amb `--execute`, en entorn `test/preproduction` i amb dades de prova aportades per variables d'entorn.

### 18.6. Conclusió

L'auditoria tècnica queda **TANCADA**: el CI final del PR #115 ha finalitzat amb 858 passed / 0 failed i 4/4 workflows aplicables en `SUCCESS`. El desplegament continua separat i subjecte al gate de preproducció.


## 19. Referència de tancament final

L'evidència consolidada de tancament, runs de CI i frontera d'entorn queda registrada a `10-tancament-auditoria-uc-018-2026-10-02.md`.


## 20. Revalidació exhaustiva — 2026-10-03

### 20.1. Base contrastada

S'ha tornat a auditar contra `main@b0e8ff7150c5a8b415cc109d298d82f0db1f68df`. Els commits posteriors al tancament documental del PR #117 no modifiquen executables UC-018; el nucli del PR #115 continua sent la base funcional.

### 20.2. Divergències trobades

1. `uc-018-bescanviar-regal.md` continuava descrivint serveis ja existents com a «DISSENY/no implementats».
2. fitxa/activitats mantenien un replay antic amb retorn abans del SIF;
3. el JS públic enviava codi regal, DNI i resta de PII en query string;
4. els endpoints públics sensibles acceptaven `$_GET`;
5. la validació pública enumerava si el codi era inexistent, pendent o ja utilitzat;
6. el lookup de curs es podia invocar directament sense revalidar;
7. el writer no imposava explícitament `FACT_REL > 0` abans de materialitzar legacy;
8. `pagina_bescanvia.php` carregava `mostrarBescanvia_prova.min.js`, absent del repositori;
9. les proves boundary només protegien la frontera interna servidor→SIF.

### 20.3. Correccions aplicades

- quatre crides sensibles navegador→legacy passades a POST body;
- tres endpoints exclusius UC-018 POST-only i sense `$_GET`; `inscripcioDuplicada.php` manté compatibilitat GET per altres UCs, però el caller UC-018 usa POST;
- resposta pública de codi no bescanviable neutralitzada;
- lookup de curs amb revalidació server-side;
- writer amb bloqueig de regal no pagat;
- pàgina cablejada al bundle rastrejable `mostrarBescanvia.min.js?ver=6.0`;
- `GiftRedemptionWebClientBoundaryTest` ampliat;
- fitxa funcional, UML integrat, classes, seqüències, activitats i matriu de proves reconciliats;
- inventari PHP/JS i document de revalidació creats.

### 20.4. Estat vigent

```text
DOCUMENTAT: re-reconciliat 03/10
IMPLEMENTAT: flux base sí; hardening públic patchat
VERIFICAT: nucli/SIF sí (858/0 del 02/10)
VERIFICACIÓ NOVA: CI del patch públic pendent
ENVIRONMENT: preproducció/SMTP pendent
POLICY: diferències de valor fail-closed
```

La referència autoritativa d'aquesta passada és `11-revalidacio-auditoria-uc-018-2026-10-03.md`.
