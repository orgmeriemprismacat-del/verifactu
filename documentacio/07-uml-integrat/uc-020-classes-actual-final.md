# UC-020 — Diagrames de classes ACTUAL i FINAL

**Data d'auditoria:** 30/09/2026 · reconciliació runtime 02/10/2026  
**Abast:** aplicació del descompte «Alumne PrisMa» en alta web, preparació de pagament i futura facturació SIF.  
**Regla d'evidència:** ACTUAL = executable llegat inspeccionat. FINAL = codi existent a `main`/branca de reconciliació quan s'indica `IMPLEMENTAT`; `PENDENT` quan encara falta integració runtime.

## 1. Classes/components ACTUALS

```mermaid
classDiagram
direction LR

class MostrarInscripcionsJS {
  <<JS legacy>>
  +calcularPreu()
  +calcularPreuSenseCodiPromo()
  +enviarInscripcio()
  tipusPreuAplicat
  preuCurs
  preuInscripcio
}

class CalcularPreuPHP {
  <<PHP legacy>>
  +consulta preu
  +consulta descomptes
  +inclou buscarAlumnePrisMa
  +retorna TIPUS|PREU|MISSATGE
}

class BuscarAlumnePrisMaPHP {
  <<PHP legacy>>
  +consulta inscripcions per DNI
  +retorna boolea alumnePrisma
}

class EnviarInscripcioPHP {
  <<PHP legacy>>
  +rep tipusDescompte
  +rep preuCar
  +rep preuDescompte
  +insereix inscripcio
}

class InscripcionsLegacy {
  <<table>>
  ID
  DNI
  A_PAGAR
  PAGAMENT
  GENERAT
  IDPAG
  FACTURA_RELACIONADA
  TIPUS_DESC
  VALID_DESC
}

MostrarInscripcionsJS --> CalcularPreuPHP : AJAX preview
CalcularPreuPHP --> BuscarAlumnePrisMaPHP : TIPUS=1
BuscarAlumnePrisMaPHP --> InscripcionsLegacy : historial
MostrarInscripcionsJS --> EnviarInscripcioPHP : confirma valors client
EnviarInscripcioPHP --> InscripcionsLegacy : INSERT
```

### Riscos ACTUALS

- el navegador manté import i tipus de descompte com a globals;
- la confirmació envia aquests imports al backend;
- la consulta antiga conté la branca SQL `FACTURA_RELACIONADA != NULL`;
- la consulta redueix l'evidència a un booleà i perd la inscripció que acredita el dret;
- preview i confirmació no comparteixen una oferta servidor immutable.

## 2. Classes FINAL — estat runtime reconciliat

```mermaid
classDiagram
direction LR

class PrismaStudentDiscountPolicy {
  <<IMPLEMENTAT>>
  +RULE_VERSION ALUMNE_PRISMA_LEGACY_V1
  +evaluate(history) array
}

class LegacyPrismaStudentHistoryRepository {
  <<IMPLEMENTAT>>
  +findByDocument(db,document) array
}

class CommercialOfferService {
  <<IMPLEMENTAT BASE>>
  +createOrReuse(input) array
}

class DiscountValidationRepository {
  <<IMPLEMENTAT>>
  +findByIdempotencyKey(db,key,forUpdate) array
  +insert(db,validation) array
}

class CommercialOperationRepository {
  <<IMPLEMENTAT>>
  +findByIdempotencyKey(db,key,forUpdate) array
  +insert(db,operation) array
  +linkIntent(db,operation,intent) void
  +updateStatus(db,operation,status) void
}

class CommercialOperationPartyRepository {
  <<IMPLEMENTAT>>
  +insert(db,party) array
  +find(db,operation,party,role,forUpdate) array
}

class PrismaStudentCourseCheckoutService {
  <<IMPLEMENTAT + CONNECTAT PAGAMENT>>
  +stageAndCreateIntent(...) array
}

class RedsysCoursePaymentIntentService {
  <<IMPLEMENTAT + ACTIU>>
  +create(sifDb,legacyDb,input) array
}

class CourseIntentSnapshotValidator {
  <<IMPLEMENTAT>>
  +validate(snapshot,idpag,sourceId,expectedAmount) void
}

class RedsysPaymentIntentService {
  <<IMPLEMENTAT>>
  +create(db,input) array
}

class RedsysPaymentIntentRepository {
  <<IMPLEMENTAT>>
  +findByDsOrder(db,dsOrder,forUpdate) array
  +insert(db,intent) array
}

class RedsysCourseInvoiceService {
  <<IMPLEMENTAT>>
  +issueFromIntentSnapshot(db,dsOrder,snapshot) array
}

class LegacyCourseInvoicePayloadBuilder {
  <<IMPLEMENTAT>>
  +build(snapshot) array
}

class InvoiceService {
  <<IMPLEMENTAT>>
  +issueInvoice(payload) array
}

LegacyPrismaStudentHistoryRepository --> PrismaStudentDiscountPolicy : fets legacy
PrismaStudentCourseCheckoutService --> LegacyPrismaStudentHistoryRepository
PrismaStudentCourseCheckoutService --> PrismaStudentDiscountPolicy
PrismaStudentCourseCheckoutService --> CommercialOperationRepository : operació
PrismaStudentCourseCheckoutService --> CommercialOperationPartyRepository : participant
PrismaStudentCourseCheckoutService --> DiscountValidationRepository : regla/evidència
PrismaStudentCourseCheckoutService --> RedsysPaymentIntentService : snapshot pagable
RedsysCoursePaymentIntentService --> PrismaStudentCourseCheckoutService : TIPUS_DESC=1
CommercialOfferService --> CommercialOperationRepository
CommercialOfferService --> DiscountValidationRepository
RedsysPaymentIntentService --> CourseIntentSnapshotValidator : SOURCE_TYPE=CURS
RedsysPaymentIntentService --> RedsysPaymentIntentRepository : persistencia
RedsysCourseInvoiceService --> LegacyCourseInvoicePayloadBuilder : snapshot congelat
RedsysCourseInvoiceService --> InvoiceService : factura + cobrament
```

## 3. Responsabilitats

| Component | Responsabilitat | Estat |
| --- | --- | --- |
| `LegacyPrismaStudentHistoryRepository` | Recuperar fets d'historial sense decidir la política | IMPLEMENTAT |
| `PrismaStudentDiscountPolicy` | Reproduir explícitament la regla web legacy sota versió `ALUMNE_PRISMA_LEGACY_V1` | IMPLEMENTAT |
| `CommercialOfferService` | Servei genèric d'oferta comercial per altres canals/casos | IMPLEMENTAT BASE |
| `DiscountValidationRepository` | Persistència idempotent de regla/evidència | IMPLEMENTAT |
| `CommercialOperationRepository` | Persistència i vincle d'intent de l'operació | IMPLEMENTAT |
| `CommercialOperationPartyRepository` | Persistència del participant de l'operació | IMPLEMENTAT EN AQUESTA RECONCILIACIÓ |
| `PrismaStudentCourseCheckoutService` | Orquestració AP atòmica fins intenció | IMPLEMENTAT I CONNECTAT AL PAGAMENT CURS |
| `RedsysCoursePaymentIntentService` | Entrada autoritativa del checkout de curs | IMPLEMENTAT I ACTIU |
| `CourseIntentSnapshotValidator` | Blindar coherència CURS abans del TPV | IMPLEMENTAT |
| `RedsysPaymentIntentService` | Crear/reutilitzar intenció | IMPLEMENTAT |
| `LegacyCourseInvoicePayloadBuilder` | Transformar snapshot en payload fiscal | IMPLEMENTAT |

## 4. Límits de la policy legacy

La policy implementada **no declara resoltes** les decisions de negoci sobre pagament parcial, `GENERAT=1`, factura abans de cobrar o autoacreditació de la mateixa inscripció. Les reprodueix sota una versió explícita de compatibilitat. Quan negoci ratifiqui una regla diferent s'ha de publicar una nova `RULE_VERSION`.

## 5. Traçabilitat

- [Fitxa UC-020](../06-fitxes-funcionals/uc-020.md)
- [UML integrat](uc-020-aplicar-alumne-prisma.md)
- [Seqüències ACTUAL/FINAL](uc-020-sequencies-actual-final.md)
- [Activitats ACTUAL/FINAL](uc-020-activitats-pagines-actual-final.md)
- [Auditoria i traçabilitat](uc-020-auditoria-tracabilitat-2026-09-29.md)
