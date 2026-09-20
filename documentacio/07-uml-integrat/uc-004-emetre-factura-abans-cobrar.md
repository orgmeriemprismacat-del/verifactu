# UC-04 · Emetre una factura abans de cobrar — fitxa i UML integrats

**Estat documental:** primera fitxa revisada per cas concret; no certifica el desplegament.
**Estat tècnic:** nucli de servei al repositori `main`; integració final de pantalla, autorització servidor i preproducció pendents segons la documentació existent.
**Relacions:** UC-01 (emissió comuna), UC-02 (cobrament posterior), UC-21 (empresa/responsable com a receptor/pagador), UC-22 (transferència, quan correspongui).
**Abast d'aquesta fitxa:** crear una factura fiscal sense registrar simultàniament un cobrament. El pagament posterior és un *altre* cas d'ús; es mostra únicament com a seqüència vinculada.

## 1. Fitxa de cas d'ús

| Camp | Especificació |
| --- | --- |
| Identificador | UC-04 |
| Nom | Emetre factura abans de cobrar |
| Actor principal | Operador autoritzat |
| Disparador | Cal emetre una factura real abans que arribi el cobrament |
| Canal objectiu | Intranet principal amb delegació al SIF; **no acreditat com a pantalla final operativa** |
| Precondicions | Operació i receptor identificats; dades necessàries per a la factura disponibles; autorització del canal pendent de verificació; petició amb clau idempotent o referència |
| Èxit | Factura emesa i persistida amb identificador/número fiscal, estat de cobrament pendent, registre fiscal encadenat i entrada a la cua fiscal; sense moviment de pagament inicial |
| Frontera funcional | Emissió de factura ≠ registre de cobrament; no es crea una segona factura quan el cobrament arriba després |

### 1.1. Entrades i validacions concretes del nucli executable

1. `InvoiceBeforePaymentPayloadBuilder::build(input)` rebutja un bloc `payment` que existeixi i no sigui `null`.
2. Si la petició no porta `idempotency_key` no buida, el constructor requereix una referència i genera una clau `INTRANET|FACTURA_ABANS_COBRAR|REF:<referència_normalitzada>`. Els noms acceptats per al camp de referència inclouen `reference`, `referencia`, `invoice_ref`, `external_ref` i `factura_relacionada`.
3. El constructor força `source_channel = INTRANET` i `emesa_abans_cobrament = 1`; conserva `created_by` quan consta i, si no, posa el valor per defecte `intranet-factura-abans-cobrar`.
4. El validador comú d'emissió requereix `idempotency_key`, `series`, `type`, `source_channel`, `billing`, `totals` i `lines`. Requereix `billing.name`, `billing.nif`, els imports `totals.import_base`, `totals.taxable_base`, `totals.total` i almenys una línia amb `concept`, `quantity`, `unit_price`, `base`, `total`. Aquestes són validacions **observades al codi**, no una afirmació que ja cobreixin tots els requisits fiscals finals.
5. Els criteris per determinar el receptor fiscal, la composició de línies, els permisos concrets de cada pantalla i totes les dades fiscals addicionals són objecte del disseny funcional i encara necessiten contrast específic per a aquest cas.

### 1.2. Flux principal: UC-04

| Pas | Actor / component | Acció i resultat |
| ---: | --- | --- |
| 1 | Operador / pantalla objectiu | Selecciona l'operació i el receptor fiscal, completa les dades i demana emetre la factura abans del cobrament. |
| 2 | Adaptador intranet **pendent d'acreditar** | Ha de validar la identitat i l'autorització i traslladar les dades al SIF. **La ruta final intranet → servei no es dona per implementada.** |
| 3 | `InvoiceBeforePaymentService` | Crida el constructor de payload per obtenir una petició d'emissió sense bloc de pagament. |
| 4 | `InvoiceBeforePaymentPayloadBuilder` | Verifica l'absència de pagament inicial, obté/genera la clau idempotent i fixa el canal i l'indicador d'emissió abans de cobrar. |
| 5 | `InvoiceService` | Valida el payload i inicia el procediment transaccional d'emissió o reutilització. |
| 6 | Repositoris del SIF | Cerquen la factura per clau idempotent; si no existeix, reserven número fiscal, bloquegen l'estat de la cadena i creen factura, línies, registre fiscal, cadena, entrada de cua i relacions d'origen. |
| 7 | SIF | Confirma la transacció i retorna `ok`, `uuid_factura`, `num_visible` i `idempotency_reused`. No es crea un moviment de pagament inicial en aquest cas. |

### 1.3. Fluxos alternatius i errors

| Escenari | Comportament verificat / estat |
| --- | --- |
| A1. Reintent amb la mateixa clau idempotent | El servei retorna l'UUID i número de la factura existent amb `idempotency_reused = true`; no reserva un segon número. |
| A2. Clau duplicada per concurrència | `InvoiceService` captura la col·lisió de clau, obre una nova transacció i rellegeix la factura existent. |
| E1. El payload inclou un `payment` no nul | El constructor rebutja la petició abans de cridar el servei d'emissió. |
| E2. No hi ha clau idempotent ni referència utilitzable | El constructor rebutja la petició. |
| E3. Falta un camp requerit pel validador comú | L'emissió es rebutja; no es pot atribuir al validador actual una verificació fiscal exhaustiva. |
| E4. Error de persistència | La transacció de creació no s'ha de confirmar parcialment; cal contrastar els errors concrets del canal i les evidències operatives. |
| A3. Arriba el cobrament més tard | S'inicia **UC-02** (o la seva variant per transferència, etc.) sobre el mateix `uuid_factura`; no es torna a emetre UC-04. |

### 1.4. Dades persistides i resultat

La implementació d'emissió crea registres a `factura`, `factura_linia`, `factura_registres`, `fiscal_chain_state`, `fiscal_queue` i, quan s'han proporcionat les relacions, `fact_rels`. La factura es crea amb `EMESA_ABANS_COBRAMENT = 1`, `ESTAT_COBRAMENT = PENDING` i `ESTAT_FACTURA = ISSUED`. L'emissió no crea `payment_transaction` ni `payment_allocation` inicials. La cua fiscal **no** és prova d'acceptació per l'AEAT: la remissió i el seu resultat són processos separats.

### 1.5. Accions posteriors relacionades, però independents

- **UC-02 / UC-22:** després de validar un cobrament, registrar-lo amb una clau idempotent pròpia i assignar-lo a la factura preexistent; la factura pot quedar parcialment cobrada o cobrada. No repetir l'emissió.
- **UC-21:** quan qui paga o rep la factura és una empresa o responsable, concretar la identitat de l'emissor/receptor i la vinculació amb les inscripcions dins d'aquest cas específic. UC-04 no resol automàticament tota la casuística empresarial.
- **UC-09 / UC-54 / UC-77:** tractar la remissió i resposta fiscal independentment de l'estat econòmic.

### 1.6. Proves i punts pendents

**Proves localitzades al repositori (no executades en aquesta revisió):** `InvoiceBeforePaymentServiceTest::testIssuesInvoiceBeforePaymentWithoutCreatingPayment`, `testBuilderDerivesIdempotencyAndForcesInvoiceBeforePaymentFlags` i `testRejectsPaymentBlockBeforeIssuingInvoice`. També hi ha proves de flux i de scripts en `sif/tests/Integration/`.

**Pendent de demostrar per tancar funcionalment UC-04:** pantalla i accés real de l'operador; autorització al servidor; origen i fotografia de dades del receptor i de les línies; previsualització/confirmació i avís a l'operador; registre transversal d'auditoria quan correspongui; tractament d'errors en el canal; prova d'integració intranet → SIF → cobrament posterior; validació de les dades fiscals definitives.

### 1.7. Revisió: factura pendent ≠ diners atribuïts — PENDENT

En emetre UC-04, **no** es crea cap entrada de fons per inscripció: la factura és real, però el cobrament encara no existeix. Quan l'empresa, responsable o alumne paga, UC-02 ha de registrar el moviment confirmat i atribuir-lo explícitament a les inscripcions cobertes, conservant la mateixa factura original i la procedència del pagament. Un canvi de curs produït **entre** emissió i cobrament exigeix revisar la factura/concepte i l'assignació abans d'atribuir els diners; no s'ha d'assignar automàticament a dades vives diferents de les emeses.

[Registre proposat de fons per inscripció](00-revisio-moviments-inscripcions.md).

## 2. Diagrama UML de casos d'ús (font PlantUML)

El diagrama diferencia la petició inicial del cobrament posterior; `UC-01` és el nucli d'emissió reutilitzat per `UC-04`. PlantUML es conserva com a font UML editable.

```plantuml
@startuml
left to right direction
actor "Operador autoritzat" as Op
actor "Procés/operador de cobrament" as Cob
rectangle "SIF PrisMa" {
  usecase "UC-04\nEmetre factura\nabans de cobrar" as UC04
  usecase "UC-01\nEmetre o reutilitzar\nfactura" as UC01
  usecase "UC-02\nRegistrar cobrament\nposterior" as UC02
}
Op --> UC04
Cob --> UC02
UC04 ..> UC01 : <<include>>
note bottom of UC02
  Acció posterior independent:
  no torna a emetre UC-04
end note
@enduml
```

### Vista navegable a GitHub

```mermaid
flowchart LR
    OP["👤 Operador autoritzat"]
    COB["👤 Procés / operador de cobrament"]
    subgraph SIF["SIF PrisMa"]
      UC04(["UC-04 · Emetre factura abans de cobrar"])
      UC01(["UC-01 · Emetre o reutilitzar factura"])
      UC02(["UC-02 · Registrar cobrament posterior"])
    end
    OP --> UC04
    UC04 -. "«include»" .-> UC01
    COB --> UC02
```

## 3. Diagrama de classes del cas

Aquest és un **subdiagrama del model de classes del SIF**, no un segon model incompatible. Mostra només classes i dependències presents al codi consultat. La pantalla objectiu no es dibuixa com si fos una classe PHP existent.

```mermaid
classDiagram
direction LR
class InvoiceBeforePaymentService {
  +issueBeforePayment(input) array
}
class InvoiceBeforePaymentPayloadBuilder {
  +build(input) array
}
class InvoiceService {
  +issueInvoice(payload) array
}
class InvoicePayloadValidator {
  +validate(payload) array
}
class TransactionRunner {
  +run(callback) mixed
}
class FiscalSequenceRepository {
  +next(db, series, year) int
}
class InvoiceRepository {
  +findByIdempotencyKey(db, key, forUpdate) array
  +lockChainState(db) array
  +createInvoiceGraph(db, payload, seq, chainState) array
}
class HashCalculator {
  +calculate(payload, previousHash) string
}
class UuidGenerator {
  +generate() string
}
InvoiceBeforePaymentService --> InvoiceBeforePaymentPayloadBuilder : prepara payload
InvoiceBeforePaymentService --> InvoiceService : delega emissió
InvoiceService --> InvoicePayloadValidator : valida
InvoiceService --> TransactionRunner : transacció
InvoiceService --> FiscalSequenceRepository : reserva número
InvoiceService --> InvoiceRepository : consulta / crea
InvoiceRepository --> HashCalculator : empremta
InvoiceRepository --> UuidGenerator : UUID
```

El `PaymentService` no és dependència de `InvoiceBeforePaymentService`: participa en el **cas posterior UC-02**, no en l'emissió sense cobrament.

## 4. Diagrama de seqüència — UC-04 (emissió)

```mermaid
sequenceDiagram
autonumber
actor Op as Operador autoritzat
participant Canal as Adaptador intranet [pendent]
participant IBP as InvoiceBeforePaymentService
participant PB as InvoiceBeforePaymentPayloadBuilder
participant IS as InvoiceService
participant IV as InvoicePayloadValidator
participant TR as TransactionRunner
participant IR as InvoiceRepository
participant FS as FiscalSequenceRepository
participant DB as BD fiscal SIF
Op->>Canal: Sol·licitar factura abans de cobrar
Note over Canal,IBP: Integració/autorització final pendents de verificar
Canal->>IBP: issueBeforePayment(input)
IBP->>PB: build(input)
alt Bloc payment no nul, o sense clau ni referència
  PB-->>Canal: Error de validació
else Entrada preparada
  PB-->>IBP: payload (INTRANET, emesa_abans_cobrament=1, sense payment)
  IBP->>IS: issueInvoice(payload)
  IS->>IV: validate(payload)
  IV-->>IS: payload validat
  IS->>TR: run(transacció)
  TR->>DB: BEGIN
  IS->>IR: findByIdempotencyKey(key, true)
  IR->>DB: SELECT factura FOR UPDATE
  alt Factura existent
    IR-->>IS: UUID i número existents
    IS-->>Canal: idempotency_reused=true
  else Factura nova
    IS->>FS: next(series, year)
    FS->>DB: Reservar número fiscal
    IS->>IR: lockChainState()
    IR->>DB: Bloquejar fiscal_chain_state
    IS->>IR: createInvoiceGraph(payload, seq, chainState)
    IR->>DB: INSERT factura, línies, registre, cua i relacions
    IR->>DB: UPDATE cadena fiscal
    IR-->>IS: uuid_factura, num_visible
    IS-->>Canal: idempotency_reused=false, UUID i número
  end
  TR->>DB: COMMIT
  Canal-->>Op: Resultat de l'emissió
end
```

**Precisió tècnica:** aquest diagrama combina el flux implementat de servei amb l'adaptador intranet *objectiu* identificat com a pendent. L'endpoint actual `sif/public/api/factures/issue.php` instancia `InvoiceService` directament; no s'ha de presentar com una crida ja demostrada a `InvoiceBeforePaymentService`.

## 5. Diagrama de seqüència vinculat — UC-02 (cobrament posterior)

```mermaid
sequenceDiagram
autonumber
actor Op as Operador / procés de cobrament
participant Canal as Canal autoritzat [integració pendent]
participant PS as PaymentService
participant PV as PaymentPayloadValidator
participant TR as TransactionRunner
participant PR as PaymentRepository
participant DB as BD fiscal SIF
Op->>Canal: Comunicar cobrament confirmat i factura existent
Canal->>PS: registerPayment(payload amb uuid_factura a allocations)
PS->>PV: validate(payload)
PV-->>PS: payload validat
PS->>TR: run(transacció)
TR->>DB: BEGIN
PS->>PR: findByIdempotencyKey(paymentKey, true)
alt Cobrament ja registrat
  PR-->>PS: uuid_payment existent
else Cobrament nou
  PS->>PR: createPayment(payload)
  PR->>DB: INSERT payment_transaction i payment_allocation
  PR->>DB: Recalcular estat de cobrament de la factura
  PR-->>PS: uuid_payment nou
end
TR->>DB: COMMIT
PS-->>Canal: uuid_payment, idempotency_reused
Canal-->>Op: Confirmació del registre de cobrament
Note over PS,DB: No s'emet una altra factura en aquesta seqüència
```

## 6. Traçabilitat de les fonts consultades

- [Fitxa anterior UC-04](../06-fitxes-funcionals/uc-004.md) — base a revisar, no traslladada automàticament com a contingut específic.
- [Catàleg de casos d'ús, apartat UC-04](../04-estat-final/33-casos-us-sif.md).
- [Diagrames de classes del SIF](../04-estat-final/31-diagrames-classes-sif.md).
- [Diagrames de seqüència, factura abans de cobrar](../04-estat-final/32-diagrames-sequencia-sif.md).
- [InvoiceBeforePaymentService.php](../../sif/src/Service/InvoiceBeforePaymentService.php).
- [InvoiceBeforePaymentPayloadBuilder.php](../../sif/src/Service/InvoiceBeforePaymentPayloadBuilder.php).
- [InvoiceService.php](../../sif/src/Service/InvoiceService.php).
- [InvoicePayloadValidator.php](../../sif/src/Service/InvoicePayloadValidator.php).
- [InvoiceRepository.php](../../sif/src/Repository/InvoiceRepository.php).
- [FiscalSequenceRepository.php](../../sif/src/Repository/FiscalSequenceRepository.php).
- [PaymentService.php](../../sif/src/Service/PaymentService.php).
- [InvoiceBeforePaymentServiceTest.php](../../sif/tests/Integration/InvoiceBeforePaymentServiceTest.php).

**Criteri de revisió:** «classe executable», «flux documental previst» i «integració acreditada» són afirmacions diferents. Aquesta fitxa acredita l'existència de codi i de proves al repositori; **no afirma haver executat les proves ni haver verificat el desplegament**.
