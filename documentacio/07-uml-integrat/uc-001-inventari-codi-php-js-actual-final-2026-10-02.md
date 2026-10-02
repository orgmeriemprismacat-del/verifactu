# UC-001 · Inventari PHP/JS ACTUAL / FINAL — 2026-10-02

## 1. PHP SIF directament implicat

| Fitxer | Responsabilitat ACTUAL | Estat |
| --- | --- | --- |
| `sif/public/api/factures/issue.php` | Entrada genèrica interna; POST signat, rol, policy i error 500 genèric. | Endurit a branca |
| `sif/src/Service/InternalApiAuthenticator.php` | HMAC, timestamp, actor/rol i anti-replay. | Main |
| `sif/src/Service/InternalInvoiceIssueScopeResolver.php` | Rol d'escriptura UC-001. | Nou |
| `sif/src/Service/InternalInvoiceIssuePayloadPolicy.php` | Actor servidor, no Redsys/no UC-004, emissor servidor si AEAT. | Nou |
| `sif/src/Service/InvoicePayloadValidator.php` | Estructura, exempció, idempotència i coherència monetària. | Endurit |
| `sif/src/Service/InvoiceService.php` | Idempotència, transacció, numeració, factura/reús i payment inicial. | Main |
| `sif/src/Repository/InvoiceRepository.php` | Factura/línies/registre/cua/relacions i vincle relació↔línia unívoc. | Endurit |
| `sif/src/Repository/FiscalSequenceRepository.php` | Numeració per sèrie/any. | Main |
| `sif/src/Aeat/RegistrationSnapshot.php` | Snapshot AEAT condicional. | Parcial |
| `sif/src/Aeat/RecordFactory.php`, `RecordHash.php`, `XmlCodec.php` | Freeze, huella i XML/XSD. | Implementat; qualificació pendent |
| `sif/src/Repository/PaymentRepository.php` | Ledger i estat de cobrament. | Main |

## 2. Vies especialitzades que reutilitzen UC-001

- UC-004: `/api/factures/before-payment.php`, command service i coverage repository.
- Redsys: callback, cua/worker i handlers `Redsys*InvoiceService`.
- Manual: `ManualInvoiceService`, serveis curs/pack/grup/regal i builders.
- Rectificació: serveis específics; no s'ha de reduir a una petició lliure al generic endpoint.

## 3. Intranet i JavaScript relacionat

| Superfície | Fitxers localitzats | Observació |
| --- | --- | --- |
| Factures | `js/alumnes-factura-sif.js`, `js/alumnes-factura.js`, `ajax/alumnes/sifFactures.php` | Consulta/gestió; permisos sempre al servidor. |
| Factura abans de pagar | `js/alumnes-genera-factura-abans-pagar.js`, `ajax/alumnes/sifFacturaAbansPagar.php`, `SifInternalApiClient.php` | Flux UC-004 dedicat. |
| Consulta alumne | `js/alumnes-mostrar-alumne-sif.js` i adapters PHP | Lectura/visibilitat, no emissió directa. |

**No s'ha identificat un JS que hagi de construir lliurement el payload fiscal complet per `/api/factures/issue.php`.** La classificació i el càlcul han de romandre a servidor/adaptador de domini.

## 4. FINAL encara absent o incomplet

- guard general de cobertura comercial entre claus diferents;
- writer d'`operation_line_invoice_link` des del flux canònic;
- audit writer amb `request_id/correlation_id` independent del fingerprint fiscal;
- assembler servidor del snapshot AEAT complet;
- projector de resultat amb estats AEAT, cobrament i document.
