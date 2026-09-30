# Auditoria detallada UC-018 · Bescanviar regal · 2026-09-30

## 1. Abast

Auditoria de la fitxa funcional, codi PHP/JS real, persistència, UML, proves, traçabilitat i mancances del cas **UC-018 · Bescanviar regal** sobre `main`.

## 2. Veredicte per capa

| Capa | Estat | Evidència / lectura |
| --- | --- | --- |
| Fitxa funcional | DOCUMENTADA, genèrica | `documentacio/06-fitxes-funcionals/uc-018.md` |
| UML integrat | DOCUMENTAT | `uc-018-bescanviar-regal.md`; contracte OBJECTIU |
| Classes ACTUAL/FINAL | CREAT en aquesta auditoria | separa UC-017/UC-111 del bescanvi |
| Seqüències ACTUAL/FINAL | CREAT en aquesta auditoria | compra actual vs bescanvi final |
| Activitats per superfície | CREAT en aquesta auditoria | no hi ha UI UC-018 actual |
| Esquema entitlement | IMPLEMENTAT EN SQL | `commercial_entitlement`, `commercial_entitlement_event` |
| Compra de regal | IMPLEMENTADA | `RedsysGiftInvoiceService`, `LegacyGiftSnapshotRepository`, `LegacyGiftInvoicePayloadBuilder` |
| Bescanvi de regal | NO IMPLEMENTAT | no hi ha service/repository/controller/gateway específics |
| JS/UI bescanvi | NO LOCALITZAT | cap superfície acreditada |
| Proves UC-018 | NO | les proves de gift existents cobreixen UC-017 |
| E2E/preproducció | NO | no hi ha flux executable a provar |

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

## 13. Estat final de l'auditoria

### DOCUMENTAT

Sí: fitxa base, UML integrat, regles de fiscalitat/economia, relació UC-017/18a/119 i, després d'aquesta auditoria, classes/seqüències/activitats ACTUAL/FINAL.

### IMPLEMENTAT

Parcial i adjacent:

- compra UC-017: sí;
- esquema entitlement: sí;
- infraestructura de fons/incidències: parcial reutilitzable;
- bescanvi UC-018: **no**.

### VERIFICAT

- proves existents de `RedsysGiftInvoiceService` verifiquen compra UC-017;
- no hi ha proves que acreditin UC-018.

### PENDENT

- repository de dret GIFT;
- service de preview/redeem;
- frontera d'inscripció;
- API/UI;
- events de reserva/consum/release;
- aplicació del valor a la inscripció;
- tests unitaris/integració/concurrència;
- E2E/preproducció;
- decisions comercials de variants.

## 14. Classificació recomanada

```text
Estat documental: REVIEWED_CASE_SPECIFIC
Estat implementació: DESIGN_ONLY_WITH_ADJACENT_FOUNDATIONS
Producció UC-018: NO-GO
```

La compra de regal pot estar implementada sense que el bescanvi ho estigui; són casos diferents.


## 15. Addenda d'implementació posterior a l'auditoria

Després del tall documental inicial s'ha implementat una primera fase executable:

- `CommercialEntitlementRepository` amb transicions GIFT i events;
- `GiftRedemptionService::preview()` i `redeem()`;
- suite d'integració específica UC-018.

Aquesta addenda canvia l'estat de «bescanvi no implementat» a **nucli de bescanvi implementat parcialment**. No canvia el NO-GO de producció: manca encara materialització de la inscripció, API/UI, aplicació quantitativa del valor i E2E/preproducció.
