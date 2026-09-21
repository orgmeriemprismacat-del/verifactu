# UC-62 · Iniciar una factura o un cobrament des de la intranet

**Objectiu original:** l'adaptador de la intranet ha d'enviar una ordre autenticada a `InvoiceService` o `PaymentService`; no hi pot haver una segona via d'escriptura fiscal. **Estat [LEGACY/DISSENY] del canal:** el nucli PHP existeix, però no s'ha acreditat un controlador integrat i autoritzat de la intranet productiva.

## 1. Evidència executable i límits

`ManualInvoiceService::issueManualInvoice()` transforma dades amb `ManualInvoicePayloadBuilder` i crida `InvoiceService::issueInvoice()`. El builder marca `source_channel=INTRANET`, `source_type=MANUAL`, requereix usuari intern i prepara opcionalment un bloc `payment`. **Que el payload digui INTRANET no acredita que la petició provingui d'una sessió autenticada de la intranet.**

`ManualPaymentService::registerByUuid()/registerByNumVisible()` localitza factura existent i registra un `CHARGE` mitjançant `ManualPaymentPayloadBuilder` i `PaymentService`; no emet un nou registre fiscal en aquesta funció. `process-manual-invoice.php`, `process-manual-course.php` i `process-manual-payment.php` són scripts **CLI que rebutgen `SIF_ENV=production`**; no constitueixen endpoints web productius. `sif/public/api/payments/register.php` exposa `PaymentService::registerPayment()` a partir de JSON i, en el codi consultat, **no s'hi identifica cap comprovació de sessió/rol de la intranet**: no equiparar disponibilitat d'API amb autorització.

## 2. Regles específiques

| Decisió de l'operador | Comanda i invariant |
| --- | --- |
| Emetre abans de cobrar | UC-04/69 confirma receptor, import, inscripcions i línies; el payload inicial **sense `payment`** crea factura i cua fiscal, amb cobrament pendent. No registrar `CHARGE` anticipat. |
| Emetre amb ingrés ja acreditat | `ManualInvoiceService` pot rebre bloc `payment`; només incloure'l quan s'ha contrastat la referència/valor real del moviment i que no existeix un `UUID_PAYMENT` previ. No inventar ingrés perquè el formulari té `PAGAMENT=1`. |
| Registrar ingrés d'una factura existent | `ManualPaymentService` reutilitza `UUID_FACTURA` i no crida `issueInvoice()`; mostrar pendent i comprovar quantia i titular del pagament real abans de confirmar. |
| Idempotència | La clau de factura i la de cobrament són diferents. `PaymentService` retorna un pagament existent per clau **sense comparar-ne el payload nou**; una nova clau per la mateixa transferència pot crear segon `CHARGE`. Cal comparar proveïdor/referència/import/data i respondre conflicte si és contradictori. |
| Autorització i auditoria | El servidor valida sessió, rol, `ID_INSC` o entitat i capacitat d'emetre o cobrar. `PaymentActionGateway` pot escriure events per accions que **hi passen**, però la ruta `public/api/payments/register.php` revisada instancia `PaymentService` directament: no afirmar traça del gateway universal. |
| Grup/pack | Una factura pot contenir múltiples participants i un cobrament extern únic; `fact_rels` i `payment_allocation` no acrediten import atribuït a cadascun. No duplicar `CHARGE` per alumne. |

### Flux objectiu

1. L'operador obre una inscripció o factura en la intranet; el **gateway pendent** comprova usuari i permís, consulta dades vigents i identifica si ja existeix factura/pagament per aquell fet.
2. Previsualitza **acció única** (emetre, emetre abans de cobrar o registrar cobrament d'una factura existent), receptor, imports per línia i evidència bancària; si hi ha divergència, pendent UC-82.
3. Construir ordre amb `REQUEST_ID`, origen verificat, `IDEMPOTENCY_KEY` i snapshot congelat; rebutjar clau reutilitzada amb contingut diferent.
4. Executar servei SIF una vegada, conservar `UUID_FACTURA` i `UUID_PAYMENT` si realment existeixen. La sincronització al llegat és **posterior i no atòmica** amb la transacció SIF.
5. Rellegir resultat i mostrar separadament estat fiscal, monetari, documental i acadèmic. Si només falla el resum llegat, **no repetir emissió/cobrament**.

**Proves:** usuari sense permís que crida API directament; factura pendent pagada en dos trams; grup de tres persones amb un sol ingrés; clau idempotent repetida amb import diferent; `SIF_ENV=production` en script CLI; error de sync llegat després de commit SIF.

## UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Operador intranet" as O
rectangle "Intranet → SIF" {
 usecase "UC-62\nIniciar factura o cobrament" as Main
 usecase "Autoritzar i classificar acció" as Decide
 usecase "Emetre factura via InvoiceService" as Invoice
 usecase "Registrar ingrés real via PaymentService" as Payment
 usecase "Sincronitzar resum llegat després del commit" as Sync
}
O --> Main
Main ..> Decide : <<include>>
Invoice ..> Main : <<extend>> (emissió aprovada)
Payment ..> Main : <<extend>> (ingrés acreditat)
Sync ..> Main : <<extend>> (resultat SIF confirmat)
@enduml
```

## UML de classes

```mermaid
classDiagram
class IntranetFiscalActionController {
 <<DISSENY: autorització/integració no acreditada>>
 +submit(actor,command) result
}
class ManualInvoiceService {
 <<PHP existent>>
 +issueManualInvoice(input) array
}
class ManualPaymentService {
 <<PHP existent>>
 +registerByUuid(sifDb,uuidFactura,input) array
 +registerByNumVisible(sifDb,numVisible,input) array
}
class InvoiceService {
 <<PHP existent>>
 +issueInvoice(payload) array
}
class PaymentService {
 <<PHP existent: reús per clau sense comparar payload>>
 +registerPayment(payload) array
}
IntranetFiscalActionController --> ManualInvoiceService : si cal emetre
IntranetFiscalActionController --> ManualPaymentService : si factura existent
ManualInvoiceService --> InvoiceService : payload fiscal
ManualPaymentService --> PaymentService : ingrés
```

## UML de seqüència — factura ja emesa, transferència posterior

```mermaid
sequenceDiagram
actor O as Operador
participant C as IntranetFiscalActionController [DISSENY]
participant I as Consulta de factura existent [SIF]
participant M as ManualPaymentService [PHP]
participant P as PaymentService [PHP]
participant L as LegacySyncService [PHP, pas separat]
O->>C: Registrar transferència real per UUID_FACTURA
C->>C: Autoritzar sessió/rol i validar referència bancaria
C->>I: Llegir factura, estat i pagaments ja registrats
alt Transferència no acreditada o possible duplicat
 C-->>O: Pendent de conciliació, sense segon CHARGE
else Ingrés nou verificat
 C->>M: registerByUuid(sifDb,uuidFactura,input)
 M->>P: registerPayment(payload amb allocations)
 P-->>M: UUID_PAYMENT nou o reutilitzat
 M-->>C: UUID_FACTURA i UUID_PAYMENT
 C->>L: Sincronització posterior si correspon [no atòmica]
 C-->>O: Resultat SIF i estat llegat diferenciats
end
Note over C,P: Els scripts CLI de prova no acrediten controlador intranet productiu.
```

## Traçabilitat

[UC-62 original](../06-fitxes-funcionals/uc-062.md) · [UC-04 factura pendent](uc-004-emetre-factura-abans-cobrar.md) · [UC-86 auditar pagament](uc-086-auditar-accio-pagament.md) · [UC-82 conciliació](uc-082-reconciliar-sif-bd-llegada.md) · [ManualInvoiceService](../../sif/src/Service/ManualInvoiceService.php) · [ManualPaymentService](../../sif/src/Service/ManualPaymentService.php) · [PaymentService](../../sif/src/Service/PaymentService.php) · [Ruta pública de pagament](../../sif/public/api/payments/register.php) · [Script CLI manual](../../sif/scripts/process-manual-payment.php).
