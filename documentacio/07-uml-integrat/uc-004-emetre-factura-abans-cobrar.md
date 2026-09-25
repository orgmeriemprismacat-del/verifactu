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
| A1. Reintent amb la mateixa clau idempotent | **PHP main:** compara tota la petició contra IDEMPOTENCY_PAYLOAD_HASH original; només el payload equivalent recupera UUID/número sense nova seqüència. Un canvi o hash històric absent dóna conflicte. |
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

### 1.8. Pantalla real de selecció múltiple i riscos de recalcular al navegador

**Circuit antic concret.** `/alumnes/genera-factura-abans-pagar/` cerca per NIF/NIE mitjançant `mostrarInformacioInscripcio_generaFactura.php`, afegeix files d'inscripció amb `.add-inscripcio` i permet avançar només si el JS considera que les files seleccionades són del **mateix curs i edició**. Calcula `idsInsc`, `preuTotal` a partir de `#apagar-{id}` de l'HTML, `concepte1` i `concepte2`, aquest últim amb la crida asíncrona `calcularTextData.php`. El tercer pas envia `entitatMarcada`, concepte i preu a `generaFacturaElectronica_Factures.php` i després mostra dades/participants i previsualització. El mètode històric `generarFacturaElectronica_Alumnes` **emet una factura abans de pagar**, encara que el seu nom suggereixi `E_FACT`.

**Validacions d'integració pendents.** El JS pot acumular identificadors a `idsInsc` si es torna enrere; `entitatMarcada` és text visible i no una clau d'entitat amb dades fiscals congelades; el total procedeix del DOM; la unicitat de curs/edició i `tePermisEdicio` es comproven al navegador. El nou adaptador ha de reconstruir **al servidor** els ID seleccionats sense repeticions, verificar curs/edició i cobertura fiscal per cadascun, carregar per ID intern el receptor fiscal i recalcular les línies/total amb descomptes vigents de l'oferta confirmada. Ha d'esperar que `concepte2` estigui resolt abans de presentar la previsualització; no acceptar una ordre fiscal a partir d'un `preuTotal` manipulable del navegador.

**Factura prèvia i reintent no equivalent.** `InvoiceBeforePaymentPayloadBuilder` sí que rebutja `payment` inicial i força `EMESA_ABANS_COBRAMENT=1`; però una clau basada en una referència de formulari **no prova** que cap `ID_INSC` estigui ja facturat amb una altra clau. Abans d'invocar `issueInvoice()`, cercar factura real existent per inscripció i `fact_rels`, distingir la mateixa operació d'una selecció/receptor/import canviats i bloquejar/derivar un conflicte a revisió. Un reintent equivalent recupera **el mateix UUID i número**, i no reobre numeració fiscal; una petició amb la mateixa clau però contingut fiscal diferent ha de donar conflicte, no afirmar equivalència només perquè retorna `idempotency_reused`.

**URL, consulta i document.** Si s'emet a empresa/responsable, conservar relació exacta amb les inscripcions cobertes i desactivar al servidor els enllaços individuals incompatibles, sense impedir que un pagament Redsys iniciat abans sigui reconciliat (UC-33/50/51). La factura es mostra com a **emesa i pendent de cobrament**, amb `E_FACT` separat i amb PDF/QR `READY` o `PENDING`; el llegat regenera el PDF via `descarregaFactura.php` i elimina un fitxer temporal amb `eliminarArxiu.php`, que **no constitueixen custòdia immutable** (UC-36). Si cau el job documental o AEAT després de confirmar la factura, reprendre aquesta fase amb el UUID existent, no generar un document fiscal nou per error.

### 1.9. Proves de pantalla i cobertura prèvia (no executades)

| ID | Escenari | Resultat exigible |
| --- | --- | --- |
| FP-01 | Tornar enrere i tornar a afegir les mateixes files | Un `ID_INSC` una sola vegada al payload final, línies/total recalculats al servidor. |
| FP-02 | DOM presenta mateix curs/edició però una fila de BD és diferent | Servidor rebutja l'emissió malgrat el JS. |
| FP-03 | Text `entitatMarcada` diferent del receptor real | Carregar entitat per ID i snapshot fiscal complet abans d'emetre. |
| FP-04 | Un participant ja figura en factura prèvia d'empresa amb altra clau | Recuperar cobertura i impedir factura duplicada per inscripció. |
| FP-05 | Doble clic equivalent i després mateix identificador amb import/receptor nou | Retorn mateix UUID per repetició exacta; conflicte per canvi substancial. |
| FP-06 | Arriba el pagament sobre la factura ja emesa | `registerPayment()` contra UUID existent, sense nou `issueInvoice()`. |
| FP-07 | Factura confirmada però PDF encara no generat | Número real i document `PENDING`; no segona factura ni correu que prometi el PDF absent. |


### Actualització de reús d'emissió a main: la factura prèvia no admet afegir payment a la mateixa petició fiscal

InvoiceService de main desa IDEMPOTENCY_PAYLOAD_HASH de la **petició completa** d'emissió i l'exigeix en reús; mateixa clau amb dades fiscals modificades o amb bloc payment afegit després → CONFLICT per assertMatches(). Una factura històrica sense fingerprint complet original no es reutilitza a cegues. El reintent equivalent de la factura **sense payment** sí recupera el mateix UUID/NUM_VISIBLE, si hi ha hash verificable. Aquest guard per K no acredita que no existeixi **una altra** factura que cobreixi la inscripció amb una clau distinta: el control entre claus de fact_rels/ID_INSC/receptor continua pendent.

L'ingrés posterior de la factura prèvia es registra exclusivament per UC-02 amb clau de **fet bancari real** i la factura original com a destí; el hash de la petició fiscal no prova CHARGE ni assignació. [UC-01, seccions 1.6 i 4.1](uc-001-emetre-o-reutilitzar-factura.md) i [UC-02](uc-002-registrar-cobrament-factura.md). Prova definida a main: PayloadIdempotencyFlowTest::testRetryCannotAddAnInitialPaymentToAnAlreadyIssuedInvoice (no executada en aquesta revisió).

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
    IS-->>TR: resultat amb idempotency_reused=true
  else Factura nova
    IS->>FS: next(series, year)
    FS->>DB: Reservar número fiscal
    IS->>IR: lockChainState()
    IR->>DB: Bloquejar fiscal_chain_state
    IS->>IR: createInvoiceGraph(payload, seq, chainState)
    IR->>DB: INSERT factura, línies, registre, cua i relacions
    IR->>DB: UPDATE cadena fiscal
    IR-->>IS: uuid_factura, num_visible
    IS-->>TR: resultat amb idempotency_reused=false, UUID i número
  end
  TR->>DB: COMMIT
  TR-->>IS: resultat després del COMMIT
  IS-->>IBP: resultat d'emissió confirmada
  IBP-->>Canal: UUID, número i indicador de reús
  Canal-->>Op: Confirmació de l'emissió
end
```

**Ordre transaccional verificat:** `TransactionRunner::run()` retorna el resultat del callback únicament després de confirmar `COMMIT`; si hi ha una excepció, executa `ROLLBACK` quan la transacció continua activa i propaga l'error. El diagrama representa la via d'èxit; la col·lisió SQL d'idempotència (`23000`) es recupera a `InvoiceService` mitjançant una **segona transacció** i rellegint la factura existent. No s'ha de comunicar èxit a l'operador abans d'aquesta confirmació. Aquest control transaccional no substitueix el guard pendent d'equivalència fiscal del payload.

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

## 7. Diagrames d'activitat de la pantalla real «Generar factura abans de pagar»

**Fonts:** [auditoria detallada de la pantalla i taula d'accions FAP-01–06](02-auditoria-pantalla-factura-abans-pagar-2026-09-25.md), [fitxa funcional UC-004, apartat 22](../06-fitxes-funcionals/uc-004.md#22-fitxa-funcional-per-accions-reals-de-generar-factura-abans-de-pagar-25092026), JS i PHP llegats referenciats en aquests documents. **ACTUAL = codi llegat llegit, no prova de desplegament; FINAL = integració fiscal SIF objectiu, no comportament implantat.** La previsualització del llegat és POSTERIOR a l'INSERT: no confondre-la amb una confirmació fiscal anterior a emetre. Els vuit diagrames representen la pàgina i subaccions separades; no compten com a vuit casos d'ús nous.

### FAP-PAG · Pàgina completa — ACTUAL

```plantuml
@startuml
title FAP-PAG ACTUAL | Generar factura abans de pagar en 3 passos
start
:Entrar a pantalla amb sessió;
:Pas 1 cercar inscripcions per DNI;
:Afegir o treure registres localment;
if (Selecció no buida i permís client?) then (Sí)
  :Calcular IDs, total, cursos i edicions al navegador;
  if (Més d'un curs o edició?) then (Sí)
    :Mostrar error i romandre al pas 1;
  else (No)
    :Sol·licitar text de mes per AJAX;
    :Mostrar pas 2 sense esperar la resposta del mes;
    :Escollir text entitat, concepte i observacions;
    if (Clic continuar amb camps visibles?) then (Sí)
      :POST a emissió llegada amb dades del navegador;
      :INSERT factura llegada i UPDATE inscripcions;
      if (Resposta textual sense error?) then (Sí)
        :Mostrar «Factura creada!»;
        :Consultar dades de factura i registres en paral·lel;
        :Mostrar pas 3, previsualització i possible PDF;
      else (No)
        :Mostrar error; estat fiscal real no reconciliat aquí;
      endif
    endif
  endif
else (No)
  :Mostrar cap selecció o manca de permís al client;
endif
stop
@enduml
```

### FAP-PAG · Pàgina completa — FINAL

```plantuml
@startuml
title FAP-PAG FINAL | Selecció autoritzada i factura única SIF
start
:Autenticar actor i autoritzar canal d'emissió;
:Consultar al servidor inscripcions facturables i ja facturades;
:Seleccionar IDs únics i receptor fiscal per ID intern;
:Carregar pagador, oferta original, cobraments i documents;
if (Inscripció incompatible o factura existent?) then (Sí)
  :Mostrar conflicte i document relacionat; no reemetre;
else (No)
  :Recalcular import i línies al servidor;
  :Generar previsualització immutable amb versió i clau d'operació;
  if (Operador confirma l'oferta fiscal?) then (Sí)
    :Validar de nou versió, imports, receptor i idempotència;
    :Emetre una factura al SIF amb numeració i cadena fiscal;
    if (Commit fiscal confirmat o mateixa petició recuperada?) then (Sí)
      :Projectar vincles llegats després del commit;
      :Mostrar UUID, número i estat pendent de cobrament;
      :Preparar/consultar PDF i QR sense reemetre;
    else (No)
      :Mostrar error o estat desconegut; reconciliar abans de reintentar;
    endif
  else (No)
    :No crear factura ni cobrament;
  endif
endif
stop
@enduml
```

### FAP-SEL · Selecció i validació de curs/edició — ACTUAL

```plantuml
@startuml
title FAP-SEL ACTUAL | Afegir/treure i avançar des del DOM
start
:Introduir DNI no buit i cercar;
:Mostrar files sense factura llegada relacionada;
while (Operador tria files?) is (Sí)
  if (Afegir?) then (Sí)
    :Copiar HTML de la fila a selecció;
    :Desactivar acció local d'afegir;
  else (Treure)
    :Eliminar fila seleccionada i reactivar icona;
  endif
endwhile (No)
if (Clic continuar al pas 2?) then (Sí)
  :Recórrer files del DOM;
  :Afegir IDs a idsInsc global sense buidar-lo;
  :Sumar A_PAGAR visible com float;
  :Agrupar curs i edició dels textos del DOM;
  if (Hi ha dos cursos o dues edicions?) then (Sí)
    :Mostrar error;
  else (No)
    :Mostrar pas 2 amb preu i concepte preparats;
  endif
endif
stop
@enduml
```

### FAP-SEL · Selecció i validació — FINAL

```plantuml
@startuml
title FAP-SEL FINAL | Selecció per IDs i dades del servidor
start
:Consultar registres autoritzats sense dades excessives;
:Afegir o treure IDs estables en conjunt sense repetits;
if (Clic continuar?) then (Sí)
  :Recarregar cada inscripció al servidor amb estat i versió;
  if (Alguna inscripció no és elegible o ja està facturada?) then (Sí)
    :Bloquejar emissió i mostrar causa per fila;
  else (No)
    :Validar compatibilitat curs/edició i receptor;
    :Calcular línies i totals decimals de l'oferta acceptada;
    :Mostrar proposta traçable sense mutació fiscal;
  endif
endif
stop
@enduml
```

### FAP-EMISSIO · Confirmar factura a empresa — ACTUAL

```plantuml
@startuml
title FAP-EMISSIO ACTUAL | Emissor llegat sense SIF
start
:Pas 2 amb entitat en text, concepte i total DOM;
:Clic continuar al pas 3;
:POST generaFacturaElectronica_Factures.php;
:Buscar entitat per RAO LIKE text i llegir primera coincidència;
:Consultar ordre i factura relacionada més recents;
:Calcular cadascun dels números com últim + 1;
:INSERT a factures llegades;
while (Queda un ID dins inscripcions rebudes?) is (Sí)
  :UPDATE factura relacionada, reclamació,
  observacions de pagament i entitat per ID;
endwhile (No)
:Retornar HTML amb identificador de factura;
note right
  No es veu lock/transaction únics
  ni verificació de factura existent per ID
  en aquest mètode.
end note
stop
@enduml
```

### FAP-EMISSIO · Confirmar factura — FINAL

```plantuml
@startuml
title FAP-EMISSIO FINAL | Commit SIF i projecció llegada posterior
start
:Confirmar previsualització amb versió i actor autoritzat;
:Comprovar receptor, línies, totals i estat fiscal real per IDs;
if (Ja existeix factura d'operació equivalent?) then (Sí)
  :Recuperar UUID i número sense nova emissió;
elseif (Conflicte de receptor, estat o import?) then (Sí)
  :Denegar i obrir incidència; no usar una clau nova a cegues;
else (No)
  :Cridar emissor únic SIF sense payment inicial;
  :Reservar sèrie i número i persistir factura i registre fiscal;
  :Confirmar transacció, hash i cua corresponents;
  :Projectar relacions llegades correlacionades;
endif
:Retornar UUID, número, estat cobrament i estat documental;
stop
@enduml
```

### FAP-DOC · Consultar factura ja emesa — ACTUAL

```plantuml
@startuml
title FAP-DOC ACTUAL | Consulta/PDF posterior a INSERT
start
:Rebre resposta textual del POST d'emissió;
if (Text no conté error?) then (Sí)
  :Mostrar «Factura creada!» i extreure ID de l'HTML;
  :Llançar consultes de dades i persones vinculades;
  if (Consulta de dades completa?) then (Sí)
    :Mostrar pas 3 i botó Previsualitza;
    if (Clic previsualitzar?) then (Sí)
      :GET mostraPrevFactura i obrir modal;
      if (Clic descarregar?) then (Sí)
        :GET descarregaFactura per fitxer temporal;
        :Iniciar descàrrega i GET neteja del temporal;
      endif
    endif
  else (No)
    :Mostrar error de consulta després d'emetre;
  endif
else (No)
  :Mostrar error sense prova de rollback fiscal;
endif
stop
@enduml
```

### FAP-DOC · Consultar factura — FINAL

```plantuml
@startuml
title FAP-DOC FINAL | Document immutable després del commit
start
:Consultar UUID fiscal existent amb actor autoritzat;
:Mostrar estat real de factura, AEAT, cobrament i document;
if (Document READY i accessible?) then (Sí)
  :Previsualitzar PDF existent amb QR i dades de la factura;
  if (Clic descarregar?) then (Sí)
    :Lliurar document immutable per endpoint autoritzat;
  endif
elseif (Document PENDING o fallit?) then (Sí)
  :Mostrar pendent/incidència i reprendre només el job documental;
  :No emetre una segona factura;
else (No)
  :Denegar sense exposar document ni receptor d'altri;
endif
stop
@enduml
```

**Estat:** prova d'emissió, comprovació de numeració i autorització real, previsualització prèvia i connexió de l'adaptador intranet-SIF **pendents**. No modificar cap factura real per obtenir captures. [Punts de prova FAP-T01–06](02-auditoria-pantalla-factura-abans-pagar-2026-09-25.md#1-fitxes-funcionals-de-cada-acció-i-rastre-del-codi-actual).
