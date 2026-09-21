# UC-92 · Registrar una venda manual iniciada per intranet o telèfon

**Objectiu original:** l'operador crea l'operació i el SIF emet/cobra segons l'estat **real**, amb idempotència equivalent a ecommerce. **Estat [DISSENY/PARCIAL].** UC-62 és l'adaptador tècnic d'ordres intranet; UC-92 és la **venda concreta**, amb oferta acceptada, responsable fiscal, prova d'ingrés si ja s'ha cobrat i reserva/matrícula corresponent. Una trucada no és prova d'un `CHARGE` ni d'un consentiment comercial.

## Evidència del PHP

`ManualCourseInvoiceService::issueFromLegacyCoursePayment()` consulta inscripció/curs per `IDPAG`, construeix payload i crida `InvoiceService::issueInvoice()`; `ManualInvoiceService::issueManualInvoice()` permet emissió a partir del payload preparat i `ManualInvoicePayloadBuilder` indica `source_channel=INTRANET`. El builder només inclou un bloc `payment` quan se li facilita; un operador que introdueixi un import **no acredita per si mateix** un ingrés bancari. `InvoiceBeforePaymentService` cobreix factura emesa abans de cobrar i `ManualPaymentService` cobra sobre factura existent. Els scripts `process-manual-course.php`, `process-manual-invoice.php` i `process-manual-payment.php` rebutgen **`SIF_ENV=production`**; no s'ha acreditat el formulari productiu amb sessió/rol i confirmació d'oferta.

`ManualCourseInvoiceService` retorna dades per sincronització llegada, però el resum opcional `--sync-legacy` del CLI s'executa **després** del resultat SIF; un error d'aquest pas no desfà la factura ni el pagament SIF.

## Fitxa funcional específica

| Pas de venda | Contracte |
| --- | --- |
| Identificar | Registrar canal d'origen real (`INTRANET/TELEFON` com a **proposta de traça**) i operador, persona interessada, `ID_INSC` o `UUID_OPERATION`, responsable/pagador i receptor fiscal diferenciats; la clau fiscal del builder segueix `source_channel=INTRANET`. |
| Composar oferta | Triar curs/edició, pack, grup o altre producte amb disponibilitat i preu/descomptes verificats, snapshot d'import/línies i acceptació del comprador. El preu dictat per telèfon no justifica `UPDATE` d'una factura ja emesa. |
| Situació econòmica | Distingir **sense cobrar**, **cobrament real acreditat**, **pagament parcial** i **transferència promesa**. Facturar abans de cobrar no crea `CHARGE`; un ingrés existent a banc no s'ha de tornar a registrar amb una nova clau. |
| Emissió | Si la venda és facturable, només el SIF assigna numeració fiscal i crea factura/registre encadenat/cua. L'alta acadèmica i Moodle són destins posteriors, no proves que la factura sigui pagada. |
| Grup i participant | Una venda telefònica de grup pot generar **una factura i un cobrament** per a N inscrits; conservar línies/relacions per participant però no multiplicar el total bancari ni enviar PDF d'empresa a cada alumne. |
| Traça i reintent | Guardar referència comercial estable i hash de l'oferta, actor, instant i idempotència; `ManualInvoicePayloadBuilder` genera clau per referència o usuari/data/hash, però **no implementa** una comprovació universal de duplicats de venda entre canals. |

### Flux objectiu

1. L'operador registra la petició i confirma curs/places, comprador, receptor i **acceptació concreta**; per operacions amb edició o preu canviat, congelar snapshot UC-112.
2. Previsualitza import i factura prèvia existent per referència/`IDPAG`; separa nova venda de cobrament d'una venda anterior, amb prova externa si afirma que està pagada.
3. Escull un dels circuits excloents: `InvoiceBeforePaymentService` si es factura pendent, `ManualInvoiceService` o variant concreta si hi ha prova d'ingrés ja vinculable, `ManualPaymentService` **només** quan hi ha factura existent i ingrés nou acreditat.
4. Després de resultat SIF, sincronitza inscripció/accés per destinacions separades; si el resum llegat falla, reintentar sync i **no** l'emissió ni el `CHARGE`.
5. Comunicar al comprador el **resultat real**: oferta/plaça, factura emesa, pagament pendent o confirmat, document disponible o pendent, matrícula pendent de Moodle.

**Proves:** telèfon amb transferència promesa, cobrament parcial real després de factura, grup pagat per empresa, doble clic de l'operador, la mateixa venda entrada també per ecommerce, `IDPAG` compartit per fraccions, error de sync llegat i intent de fer servir script CLI en producció.

### Venda telefònica i «Passar pagaments»: la pantalla llegada no prova l'ingrés

**Punt d'entrada real de gestió.** `/alumnes/pagaments/` mostra cerca per NIF/NIE, codi regal o número de factura i selector ALUMNE/GRUP; `buscarInfomacioPagament.php` retorna files que permeten introduir `PAGAMENT/DATA PAG/BANC/OBSERVACIONS`. El JS recalcula `PAGAT` sobre l'HTML i envia `efectuarPagament.php` per **GET** amb import, data, banc i observacions. La validació del navegador, el modal `mostrarModalConfPag.php` i el camp `efact` no acrediten ingrés ni autorització SIF: el procediment històric pot modificar `web.factures.IMPORT/DATA_PAGAMENT` a través de `updFactGenerada`, un efecte prohibit sobre una factura nova immutable. No reutilitzar aquesta ruta com a «API de pagament» sense substituir-ne l'escriptura fiscal.

**Telèfon: quatre estats comercials diferenciats.** La persona operadora pot (a) recollir interès o reservar una plaça sense venda acceptada, (b) confirmar una oferta i **emetre una factura real abans de cobrar** a l'empresa, (c) registrar un ingrés bancari **ja acreditat** sobre una factura existent o (d) emetre una venda nova amb ingrés acreditat quan correspongui. `ManualInvoicePayloadBuilder::build()` força `source_channel=INTRANET` i requereix un text d'usuari a `created_by`, però **ni aquest text ni un import introduït a mà comproven sessió, rol, mandat del comprador o evidència de banc**. La categoria «petició telefònica» és traça comercial del canal; no es converteix en `CHARGE` només perquè s'ha apuntat una promesa de transferència.

**Identificar la factura anterior abans d'escollir l'executor.** Si l'empresa ja té factura emesa abans de pagar, cercar `UUID_FACTURA` per `ID_INSC/fact_rels`, receptor i import confirmats; fer `registerPayment()` amb clau de **transacció externa real**. Si no hi ha factura però s'ha cobrat de debò, preparar i validar oferta/receptor/import i executar la via d'emissió/ingrés corresponent una sola vegada. `IDPAG` compartit de grup/pack, el número mostrat al formulari i una clau idempotent inventada pel doble clic **no** proven l'absència d'una factura anterior. Si hi ha import ajustat o saldo sense provar, derivar a UC-94/56/104 en comptes de generar moviment o document improvisat.

**Confirmació i fases posteriors.** Els scripts `process-manual-course.php`, `process-manual-invoice.php` i `process-manual-payment.php` rebutgen `SIF_ENV=production`: la seva existència és eina de preproducció, **no** una interfície telefònica d'operació productiva acreditada. La futura comanda ha de conservar actor autoritzat, operació/acceptació, prova externa si n'hi ha, factures trobades, idempotència de negoci i resultat `UUID_FACTURA/UUID_PAYMENT` individual; la sincronització de `PAGAMENT` llegat i Moodle es comprova després. Si falla només aquesta sincronització o el correu, retornar l'operació confirmada i reintentar la fase pendent, no tornar a facturar o cobrar.

### Proves de venda manual i evidència (no executades)

| ID | Escenari | Resultat exigible |
| --- | --- | --- |
| VM-92-01 | Trucada amb transferència promesa sense moviment bancari | Reserva/oferta o factura pendent segons acceptació; cap `CHARGE` fictici. |
| VM-92-02 | Gestió escriu `PAGAMENT=80` a l'HTML i el GET arriba dues vegades | Validació i idempotència al servidor; no dos moviments ni modificació de factura real. |
| VM-92-03 | Empresa paga després factura prèvia amb un `IDPAG` conjunt | Reutilitzar factura existent, un moviment real i assignacions justificades. |
| VM-92-04 | `created_by` indica nom d'usuari però sessió/rol no són vàlids | Rebutjar al servidor, no considerar el text una autorització. |
| VM-92-05 | SIF retorna UUIDs i falla l'UPDATE de `PAGAMENT` llegat | Reintentar només sincronització acadèmica/compatibilitat, no emissió ni cobrament. |
| VM-92-06 | Prova manual CLI en entorn productiu | Rebuig del script existent; l'entrada de producció requereix adaptador propi verificat. |

## UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Operador intranet/telèfon" as O
actor "Comprador o responsable" as C
rectangle "SIF · venda manual" {
 usecase "UC-92\nRegistrar venda manual" as Main
 usecase "Confirmar oferta i receptor" as Confirm
 usecase "UC-04\nFacturar abans de cobrar" as Before
 usecase "Emetre amb ingrés acreditat" as Paid
 usecase "UC-62\nRegistrar cobrament posterior" as Later
 usecase "Propagar alta acadèmica separada" as Acad
}
O --> Main
C --> Confirm
Main ..> Confirm : <<include>>
Before ..> Main : <<extend>> (venda pendent)
Paid ..> Main : <<extend>> (ingrés real disponible)
Later ..> Main : <<extend>> (factura existent)
Acad ..> Main : <<extend>> (venda confirmada)
@enduml
```

## UML de classes

```mermaid
classDiagram
class ManualSaleOrchestrator {
 <<DISSENY: no acreditat>>
 +preview(actor,offer,customer) decision
 +confirm(uuidOperation,paymentProof) result
}
class InvoiceBeforePaymentService {
 <<PHP existent>>
 +issueBeforePayment(input) array
}
class ManualInvoiceService {
 <<PHP existent>>
 +issueManualInvoice(input) array
}
class ManualCourseInvoiceService {
 <<PHP existent>>
 +issueFromLegacyCoursePayment(legacyDb,idpag,input,discountSnapshot) array
}
class ManualPaymentService {
 <<PHP existent>>
 +registerByUuid(sifDb,uuidFactura,input) array
}
ManualSaleOrchestrator --> InvoiceBeforePaymentService : sense ingrés
ManualSaleOrchestrator --> ManualInvoiceService : emissió manual
ManualSaleOrchestrator --> ManualCourseInvoiceService : curs llegat
ManualSaleOrchestrator --> ManualPaymentService : cobrament posterior
```

## UML de seqüència — venda per telèfon amb transferència pendent

```mermaid
sequenceDiagram
actor O as Operador
actor C as Comprador
participant S as ManualSaleOrchestrator [DISSENY]
participant F as InvoiceBeforePaymentService [PHP]
participant P as ManualPaymentService [PHP]
participant A as Prisma/Moodle [destins separats]
O->>S: Registrar curs/edició i comprador per telèfon
S->>C: Confirmar oferta, receptor i import
C-->>S: Acceptació de la venda
S->>S: Comprovar factura prèvia i congelar snapshot
alt Només compromís de transferir
 S->>F: issueBeforePayment(input sense payment)
 F-->>S: UUID_FACTURA i estat PENDING
 S-->>O: Factura emesa, CHARGE inexistent
else Transferència real després de la factura
 O->>S: Aportar referència bancària contrastada
 S->>P: registerByUuid(sifDb,uuidFactura,input)
 P-->>S: UUID_PAYMENT nou o reutilitzat
end
S->>A: Alta/sync acadèmic postcommit [adaptador pendent]
S-->>O: Estat de factura, cobrament i plaça separat
Note over S,A: Orquestrador/acceptació telefònica no acreditats al PHP; CLI refusa producció.
```

## Traçabilitat

[UC-92 original](../06-fitxes-funcionals/uc-092.md) · [UC-62 intranet](uc-062-iniciar-factura-cobrament-intranet.md) · [UC-04 abans de pagar](uc-004-emetre-factura-abans-cobrar.md) · [UC-112 snapshot](uc-112-congelar-snapshot-abans-tpv.md) · [UC-129 Moodle](uc-129-reconciliar-prisma-moodle-matricules.md) · [ManualCourseInvoiceService](../../sif/src/Service/ManualCourseInvoiceService.php) · [ManualInvoiceService](../../sif/src/Service/ManualInvoiceService.php) · [InvoiceBeforePaymentService](../../sif/src/Service/InvoiceBeforePaymentService.php) · [Script CLI manual](../../sif/scripts/process-manual-course.php).
