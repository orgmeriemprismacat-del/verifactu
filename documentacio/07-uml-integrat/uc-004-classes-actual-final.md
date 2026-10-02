# UC-004 · Diagrames de classes ACTUAL / FINAL

**Cas d'ús:** UC-004 — Emetre factura abans de cobrar  
**Data d'auditoria estàtica:** 2026-09-29  
**Estat:** documentació d'auditoria. ACTUAL = codi llegat observat. FINAL = arquitectura objectiu, distingint classes ja implementades de components encara pendents.

## 1. Fonts directes contrastades

- `codi-drive/intranet-actual/alumnes-genera-factura-abans-pagar.php`
- `codi-drive/intranet-actual/js/alumnes-genera-factura-abans-pagar.js`
- `codi-drive/intranet-actual/js/general.js`
- `codi-drive/intranet-actual/ajax/mostrarMain.php`
- `codi-drive/intranet-actual/ajax/alumnes/mostrarInformacioInscripcio_generaFactura.php`
- `codi-drive/intranet-actual/ajax/alumnes/generaFacturaElectronica_Factures.php`
- `codi-drive/intranet-actual/ajax/alumnes/mostraDadesFacturaElectronica_Factures.php`
- `codi-drive/intranet-actual/ajax/alumnes/mostraInscripcionsFacturaElectronica_Factures.php`
- `codi-drive/intranet-actual/ajax/alumnes/mostraPrevFactura_Factures.php`
- `codi-drive/intranet-actual/ajax/alumnes/descarregaFactura.php`
- `codi-drive/intranet-actual/ajax/alumnes/eliminarArxiu.php`
- `codi-drive/intranet-actual/Intranet.php`
- `sif/src/Service/InvoiceBeforePaymentService.php`
- `sif/src/Service/InvoiceBeforePaymentPayloadBuilder.php`
- `sif/src/Service/InvoiceService.php`
- `sif/src/Service/InvoicePayloadValidator.php`
- `sif/src/Service/PayloadIdempotencyValidator.php`
- `sif/src/Repository/InvoiceRepository.php`
- `sif/src/Repository/FiscalSequenceRepository.php`
- `sif/public/api/factures/issue.php`
- `sif/tests/Integration/InvoiceBeforePaymentServiceTest.php`

## 2. Llegenda d'estat

- **[EXISTEIX]** classe o component localitzat al repositori.
- **[LLEGAT]** component existent del circuit actual de la intranet.
- **[PROPOSAT]** responsabilitat necessària per al FINAL però no acreditada com a classe executable actual.
- **[PENDENT INTEGRACIÓ]** codi existent que encara no està connectat al circuit real UC-004.

## 3. Classes ACTUAL — circuit llegat

El circuit real continua sent navegador → AJAX llegat → `Intranet` → BD web/intranet. La pantalla no invoca `InvoiceBeforePaymentService`.

```mermaid
classDiagram
direction LR

class BrowserUC004 {
  <<LLEGAT>>
  +cercarDni()
  +afegirInscripcio()
  +eliminarInscripcio()
  +calcularPreuTotalDOM()
  +construirConceptes()
  +generarFactura()
  +previsualitzar()
  +descarregar()
}

class GeneralJS {
  <<LLEGAT>>
  +consultaRolsEdicio()
  +consultaRolsUsuari()
  +tePermisEdicio
}

class MostrarMainEndpoint {
  <<LLEGAT>>
  +GET(url)
}

class CercaInscripcioEndpoint {
  <<LLEGAT>>
  +GET(dni)
}

class GeneraFacturaEndpoint {
  <<LLEGAT>>
  +POST(empresa, concepte1, concepte2, preu, cursos, edicions, inscripcions, observacions)
}

class DadesFacturaEndpoint {
  <<LLEGAT>>
  +POST(factura)
}

class InscripcionsFacturaEndpoint {
  <<LLEGAT>>
  +POST(factura)
}

class PrevisualitzaFacturaEndpoint {
  <<LLEGAT>>
  +GET(factura)
}

class DescarregaFacturaEndpoint {
  <<LLEGAT>>
  +GET(id)
}

class EliminarArxiuEndpoint {
  <<LLEGAT>>
  +GET(filename)
}

class Intranet {
  <<LLEGAT>>
  -__mostrarPage_Alumnes_GeneraFacturaAbansPagar()
  +mostrarInformacioInscripcio_generaFactura_Alumnes(dni)
  +generarFacturaElectronica_Alumnes(...)
  +mostraDadesFacturaElectronica_Alumnes(factura)
  +mostraInscripcionsFacturaElectronica_Alumnes(factura)
  +modalConsultaFactura_Factures(factura)
  +generaFactura(factura, descarrega)
}

class Usuari {
  <<LLEGAT>>
  +tePermisVisualitzacio(rols)
}

class ConnexioIntranet {
  <<LLEGAT>>
  +connectarBD()
  +prepare(sql)
}

class ConnexioWeb {
  <<LLEGAT>>
  +connectarBD()
  +prepare(sql)
}

class LegacyFactures {
  <<LLEGAT DB>>
  +NUM
  +ANY
  +ORDRE
  +NUM_FACTURA
  +IMPORT
  +E_FACT
}

class LegacyInscripcions {
  <<LLEGAT DB>>
  +ID
  +FACTURA_RELACIONADA
  +PAG_OBSERVACIONS
  +RECLAMAT
  +CIF
}

class DompdfFilesystem {
  <<LLEGAT>>
  +render()
  +file_put_contents()
  +unlink(filename)
}

BrowserUC004 --> GeneralJS : permís edició al client
BrowserUC004 --> MostrarMainEndpoint : carrega pantalla
MostrarMainEndpoint --> Usuari : permís visualització
MostrarMainEndpoint --> Intranet : mostrarPage()
BrowserUC004 --> CercaInscripcioEndpoint
CercaInscripcioEndpoint --> Intranet
BrowserUC004 --> GeneraFacturaEndpoint
GeneraFacturaEndpoint --> Intranet
BrowserUC004 --> DadesFacturaEndpoint
BrowserUC004 --> InscripcionsFacturaEndpoint
DadesFacturaEndpoint --> Intranet
InscripcionsFacturaEndpoint --> Intranet
BrowserUC004 --> PrevisualitzaFacturaEndpoint
PrevisualitzaFacturaEndpoint --> Intranet
BrowserUC004 --> DescarregaFacturaEndpoint
DescarregaFacturaEndpoint --> Intranet
BrowserUC004 --> EliminarArxiuEndpoint
Intranet --> ConnexioIntranet : entitats / rols
Intranet --> ConnexioWeb : factura / inscripcions
ConnexioWeb --> LegacyFactures
ConnexioWeb --> LegacyInscripcions
Intranet --> DompdfFilesystem : PDF temporal
EliminarArxiuEndpoint --> DompdfFilesystem : unlink(filename)
```

### 3.1 Observacions del model ACTUAL

1. `mostrarMain.php` sí que comprova el rol de **visualització** de la pàgina.
2. `general.js` calcula `tePermisEdicio` al navegador consultant rols; aquest valor protegeix la interacció de la UI, no acredita una autorització de l'acció fiscal dins `generaFacturaElectronica_Factures.php`.
3. L'endpoint de generació rep del navegador el receptor com a text visible (`empresa`), conceptes, preu, cursos, edicions i IDs d'inscripció.
4. `Intranet::generarFacturaElectronica_Alumnes()` busca l'entitat per text, calcula número amb patró “últim + 1”, insereix a la taula llegada `factures` i després actualitza una a una les inscripcions.
5. No s'ha observat una transacció que englobi l'alta de factura i totes les actualitzacions d'inscripcions.
6. La previsualització i la descàrrega tornen a construir el document a partir de dades llegades; la descàrrega crea un fitxer temporal.
7. `eliminarArxiu.php` executa `unlink($filename)` amb un nom de fitxer rebut per GET. Ha de desaparèixer del contracte FINAL o quedar estrictament encapsulat i validat.

## 4. Classes FINAL — arquitectura objectiu UC-004

El `main` del 2026-10-02 ja implementa la frontera interna SIF, l'autorització, el command i la reconstrucció autoritativa. El principal adaptador encara **PROPOSAT** és el bridge de la pantalla intranet llegada cap a aquesta API interna. El classificador de cobertura transversal i la integració documental/post-COMMIT també continuen pendents.

```mermaid
classDiagram
direction LR

class IntranetUc004Bridge {
  <<PROPOSAT · PANTALLA REAL>>
  +preview(selectionIds, entityId)
  +confirm(selectionIds, entityId, fingerprint)
  +signInternalRequest()
}

class BeforePaymentHttpEndpoint {
  <<EXISTEIX AL MAIN>>
  +POST action=preview
  +POST action=confirm
}

class InternalApiAuthenticator {
  <<EXISTEIX AL MAIN>>
  +authenticate(server, rawBody, method, path)
}

class InternalInvoiceBeforePaymentScopeResolver {
  <<EXISTEIX AL MAIN>>
  +resolve(authenticatedActor)
}

class InvoiceBeforePaymentCommandService {
  <<EXISTEIX AL MAIN>>
  +preview(ids, entityId, actorId, context)
  +confirm(ids, entityId, actorId, fingerprint, context)
}

class InvoiceBeforePaymentSelectionRepository {
  <<EXISTEIX AL MAIN>>
  +loadByIds(legacyWebDb, ids)
}

class InvoiceBeforePaymentBillingPartyRepository {
  <<EXISTEIX A LA BRANCA>>
  +loadByEntityId(legacyIntranetDb, entityId)
}

class InvoiceBeforePaymentServerPayloadAssembler {
  <<EXISTEIX A LA BRANCA>>
  +buildInput(selection, billingParty, context)
}

class InvoiceBeforePaymentLegacyPreparationService {
  <<EXISTEIX A LA BRANCA>>
  +prepare(legacyWebDb, legacyIntranetDb, ids, entityId, context)
}

class CrossChannelCoverageClassifier {
  <<PROPOSAT>>
  +classifyExistingCoverage(sourceIds, billing)
  +assertCompatibleOrReview()
}

class InvoiceBeforePaymentCoverageRepository {
  <<EXISTEIX A LA BRANCA>>
  +claim(db, relations, uuidFactura, idempotencyKey)
}

class InvoiceBeforePaymentCoverage {
  <<SIF DB · UC-004>>
  +SOURCE_TYPE
  +SOURCE_ID
  +UUID_FACTURA
  +IDEMPOTENCY_KEY
}

class OperationalEventRepository {
  <<EXISTEIX · INTEGRAT EN AQUESTA BRANCA>>
  +append(db, event)
}

class OperationalEvent {
  <<SIF DB>>
  +UUID_OPERATIONAL_EVENT
  +OPERATION_TYPE
  +UUID_FACTURA
  +FISCAL_IMPACT
  +ECONOMIC_IMPACT
  +ACTOR_ID
  +CORRELATION_ID
}

class InvoiceBeforePaymentPayloadBuilder {
  <<EXISTEIX>>
  +build(input) array
}

class InvoiceBeforePaymentService {
  <<EXISTEIX AL MAIN>>
  +issueBeforePayment(input) array
}

class InvoiceService {
  <<EXISTEIX>>
  +issueInvoice(payload) array
}

class InvoicePayloadValidator {
  <<EXISTEIX>>
  +validate(payload) array
}

class PayloadIdempotencyValidator {
  <<EXISTEIX>>
  +calculateHash(payload) string
  +assertMatches(payload, storedHash)
}

class TransactionRunner {
  <<EXISTEIX>>
  +run(callback) mixed
}

class FiscalSequenceRepository {
  <<EXISTEIX>>
  +next(db, series, year) int
}

class InvoiceRepository {
  <<EXISTEIX>>
  +findByIdempotencyKey(db, key, forUpdate)
  +lockChainState(db)
  +createInvoiceGraph(db, payload, seq, chainState)
}

class Factura {
  <<SIF DB>>
  +UUID_FACTURA
  +IDEMPOTENCY_KEY
  +IDEMPOTENCY_PAYLOAD_HASH
  +NUM_VISIBLE
  +EMESA_ABANS_COBRAMENT
  +ESTAT_COBRAMENT
  +ESTAT_FACTURA
  +ESTAT_AEAT
}

class FacturaLinia {
  <<SIF DB>>
  +UUID_FACTURA
  +CONCEPTE
  +TOTAL
  +SOURCE_TYPE
  +SOURCE_ID
}

class FactRels {
  <<SIF DB>>
  +UUID_FACTURA
  +SOURCE_TYPE
  +SOURCE_ID
  +RELATION_TYPE
}

class FacturaRegistres {
  <<SIF DB>>
  +FISCAL_ORDER
  +HASH_FACT
  +HASH_FACT_ANT
  +PAYLOAD_JSON
}

class FiscalQueue {
  <<SIF DB>>
  +UUID_FACTURA
  +IDEMPOTENCY_KEY
  +PAYLOAD_JSON
}

class LegacySync {
  <<PROPOSAT>>
  +syncAfterSifCommit(result)
}

class InvoiceDocumentService {
  <<PROPOSAT>>
  +ensureDocument(uuidFactura)
  +getDocumentStatus(uuidFactura)
}

IntranetUc004Bridge --> BeforePaymentHttpEndpoint : HMAC server-server
BeforePaymentHttpEndpoint --> InternalApiAuthenticator
BeforePaymentHttpEndpoint --> InternalInvoiceBeforePaymentScopeResolver
BeforePaymentHttpEndpoint --> InvoiceBeforePaymentCommandService
InvoiceBeforePaymentCommandService --> CrossChannelCoverageClassifier : PENDENT
InvoiceBeforePaymentCommandService --> InvoiceBeforePaymentLegacyPreparationService
InvoiceBeforePaymentCommandService --> InvoiceBeforePaymentService

InvoiceBeforePaymentLegacyPreparationService --> InvoiceBeforePaymentSelectionRepository
InvoiceBeforePaymentLegacyPreparationService --> InvoiceBeforePaymentBillingPartyRepository
InvoiceBeforePaymentLegacyPreparationService --> InvoiceBeforePaymentServerPayloadAssembler
InvoiceBeforePaymentLegacyPreparationService --> InvoiceBeforePaymentPayloadBuilder
InvoiceBeforePaymentLegacyPreparationService --> PayloadIdempotencyValidator

InvoiceBeforePaymentService --> InvoiceBeforePaymentPayloadBuilder
InvoiceBeforePaymentService --> InvoiceService
InvoiceService --> InvoicePayloadValidator
InvoiceService --> PayloadIdempotencyValidator
InvoiceService --> TransactionRunner
InvoiceService --> FiscalSequenceRepository
InvoiceService --> InvoiceRepository
InvoiceService --> InvoiceBeforePaymentCoverageRepository : només UC-004
InvoiceBeforePaymentCoverageRepository --> InvoiceBeforePaymentCoverage : claim transaccional
InvoiceService --> OperationalEventRepository : només UC-004
OperationalEventRepository --> OperationalEvent : mateix COMMIT

InvoiceRepository --> Factura
InvoiceRepository --> FacturaLinia
InvoiceRepository --> FactRels
InvoiceRepository --> FacturaRegistres
InvoiceRepository --> FiscalQueue

InvoiceBeforePaymentCommandService ..> LegacySync : PENDENT post-COMMIT
InvoiceBeforePaymentCommandService ..> InvoiceDocumentService : PENDENT document per UUID
```

## 5. Responsabilitats que NO s'han de confondre

- `InvoiceBeforePaymentService` **existeix i ja és invocat** pel command/endpoint intern SIF; la pantalla llegada encara no passa per aquesta frontera.
- `sif/public/api/factures/issue.php` **existeix**, però instancia `InvoiceService` directament; per tant no acredita per si sol el contracte “abans de cobrar” ni l'ús de `InvoiceBeforePaymentPayloadBuilder`.
- `InvoiceBeforePaymentSelectionRepository`, `InvoiceBeforePaymentBillingPartyRepository`, `InvoiceBeforePaymentServerPayloadAssembler`, `InvoiceBeforePaymentLegacyPreparationService` i `InvoiceBeforePaymentCommandService` **ja existeixen al main** i eliminen del payload autoritatiu el total/receptor/conceptes construïts al navegador. El bridge de la pantalla és el que falta.
- `InvoicePayloadValidator` valida camps estructurals bàsics; no acredita tota la validació fiscal, comercial, de cobertura ni d'autorització necessària per UC-004.
- `PayloadIdempotencyValidator` protegeix la repetició de **la mateixa clau** comparant el hash complet. `InvoiceBeforePaymentCoverageRepository` impedeix que dues operacions UC-004 amb claus diferents reclamin el mateix origen. Encara falta el classificador de cobertura **transversal** entre altres canals/pagadors, perquè no tota doble relació d'una inscripció és necessàriament il·legítima.
- `OperationalEventRepository` s'integra en aquesta branca dins la mateixa transacció UC-004; un retry idempotent no ha de crear un segon event.
- `PaymentService` no forma part de l'emissió inicial UC-004. El cobrament posterior és UC-002/UC-022 segons canal.

## 6. Criteri de tancament del diagrama FINAL

Aquest diagrama passarà de **FINAL proposat** a **FINAL implementat/verificat** quan existeixi i s'hagi provat el camí:

`pantalla intranet → autorització servidor → InvoiceBeforePaymentLegacyPreparationService → fingerprint preview/confirm → classificador de cobertura transversal → InvoiceBeforePaymentService → InvoiceService → claim UC-004 + COMMIT SIF → sincronització llegada/document → resposta tipificada`.

Fins llavors, el nucli SIF és implementat però la integració completa UC-004 continua **PARCIAL**.
