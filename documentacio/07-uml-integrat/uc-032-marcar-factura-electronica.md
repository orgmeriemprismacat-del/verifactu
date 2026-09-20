# UC-32 · Marcar o desmarcar factura electrònica

**Objectiu canònic:** gestionar la preferència o sol·licitud de factura electrònica com a **acció separada** amb usuari, data i motiu, sense reescriure factura fiscal. **No és** generar un document electrònic ni acreditar-ne el lliurament: aquests passos corresponen a UC-123.

## 1. Evidència i fitxa

`factura.E_FACT` existeix, però `InvoiceRepository::insertInvoice()` insereix el valor literal **`0`** en el moment d'emetre la factura, al costat d'`EMESA_ABANS_COBRAMENT` i `ESTAT_COBRAMENT`. **No s'ha acreditat** a `sif/src` un servei PHP de marcar/desmarcar amb actor, motiu i auditoria. `electronic_invoice_delivery` és una **taula SQL definida**, no un efecte automàtic d'`E_FACT=1`; conté format, versió, document, hash del destinatari, canal, estat i evidència de lliurament.

| Pas | Contracte |
| --- | --- |
| Actor | Receptor fiscal o representant autoritzat i operador amb permís. La persona inscrita no és automàticament el receptor de la factura d'una empresa/grup. |
| Entrada | `UUID_FACTURA`, receptor, estat actual de preferència, opció nova, canal/format sol·licitat quan es coneixen, actor, motiu, moment, identificador idempotent i correlació. |
| Precondició | Factura existent i permís del sol·licitant; comprovar preferència d'emissió/lliurament, no inferir consentiment d'un correu o de compartir `IDPAG`. |
| Efecte propi | Registrar **una decisió de lliurament o preferència** amb traça abans/després i data, sense modificar número, receptor fiscal original, línies, total, `factura_registres` ni cadena. Cal decidir si i com s'actualitza `E_FACT` de forma controlada; un `UPDATE` directe no és expedient complet. |
| Lliurament | UC-123 decideix format/canal/destinatari, crea document i registra resultat. `E_FACT=1` sol **no prova** que hi hagi PDF/XML, enviament o acceptació del receptor. |
| Economia | Cap `CHARGE`, `REFUND`, saldo ni assignació de fons per canviar una preferència documental. |

### Flux objectiu

1. Verificar identitat del receptor/representant i autorització sobre `UUID_FACTURA`; llegir preferència i lliuraments anteriors.
2. Registrar petició/decisió amb `REQUEST_ID`, actor, motiu i versió; si el mateix valor ja és vigent, retornar `NO_CHANGE` amb historial, no duplicar enviaments.
3. Si s'activa, determinar format, canal, destí i consentiment necessari segons el circuit real de l'entitat (UC-123), sense fingir que `E_FACT` el resol. Si es desactiva, preservar documents i proves de lliurament **ja emesos**.
4. Mantenir el document fiscal intacte; només un error real de la factura entra al circuit de correcció UC-05/74, no a UC-32.
5. Un canvi després d'haver iniciat un enviament no elimina intents/documentos ja generats: cancel·lar o reprogramar **els lliuraments pendents** segons política i conservar-ne traça.

### Alternatives de prova

| Cas | Resultat |
| --- | --- |
| Factura a empresa, alumne demana E_FACT | Validar poder de representació; no oferir el document fiscal complet sense autorització. |
| Marcar dues vegades | Un canvi d'estat efectiu i reús/`NO_CHANGE` al segon intent; no dos correus automàtics. |
| Desmarcar després d'un lliurament confirmat | Lliurament/document històrics es conserven; afecta només futures actuacions segons política. |
| Marcar E_FACT=1 sense format o receptor validads | Preferència pendent d'execució, **no** «factura electrònica enviada». |
| Emissió de factura nova | El PHP actual grava `E_FACT=0`; l'adaptador ha de definir com reflecteix la preferència, no assumir que el payload la insereix automàticament. |

**Pendents:** identitat i permisos, estat/versions de la sol·licitud, model de decisió auditable, integració amb UC-123 i proves del valor literal `E_FACT=0` a l'emissió.

## 2. UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Receptor autoritzat" as R
actor "Gestió" as G
rectangle "SIF · preferència electrònica" {
 usecase "UC-32\nMarcar/desmarcar factura electrònica" as Main
 usecase "Validar receptor/permís" as Auth
 usecase "Registrar decisió i motiu" as Record
 usecase "UC-123\nLliurar en format acordat" as Deliver
}
R --> Main
G --> Main
Main ..> Auth : <<include>>
Main ..> Record : <<include>>
Deliver ..> Main : <<extend>> (lliurament autoritzat)
@enduml
```

## 3. UML de classes — absència de servei de preferència

```mermaid
classDiagram
class ElectronicInvoicePreferenceService {
 <<DISSENY: no acreditat>>
 +changePreference(uuidFactura,actor,value,reason) result
}
class PreferenceDecisionRepository {
 <<DISSENY: model d'events pendent>>
 +appendDecision(db,request) result
}
class InvoiceRepository {
 <<PHP existent: crea E_FACT igual a 0>>
 +createInvoiceGraph(db,payload,seq,chainState) array
}
class ElectronicInvoiceDeliveryService {
 <<DISSENY: UC-123, no acreditat>>
 +schedule(uuidFactura,format,channel,recipient) result
}
ElectronicInvoicePreferenceService --> PreferenceDecisionRepository : traça de preferència
ElectronicInvoicePreferenceService ..> ElectronicInvoiceDeliveryService : lliurament separat
```

## 4. UML de seqüència — marcar, desmarcar i document existent (DISSENY)

```mermaid
sequenceDiagram
actor R as Receptor autoritzat
participant S as ElectronicInvoicePreferenceService [DISSENY]
participant A as Servei d'autorització [pendent]
participant DB as factura.E_FACT + historial [parcial]
participant D as electronic_invoice_delivery [SQL definit]
R->>S: Marcar factura com a electrònica
S->>A: Verificar receptor/representació
A-->>S: Autorització
S->>DB: Consultar preferència i factures/documents
S->>DB: Registrar decisió actor/data/motiu [writer pendent]
S-->>R: Preferència enregistrada, NO lliurament acreditat
opt Format/canal/recipient validats per UC-123
 S->>D: Programar lliurament separat [servei pendent]
end
R->>S: Desmarcar preferència posteriorment
S->>DB: Registrar nou estat i conservar història fiscal
S-->>R: Preferència actualitzada; enviaments antics intactes
Note over S,D: E_FACT no equival a generar, enviar o lliurar una factura electrònica
```

## 5. Traçabilitat

[UC-32 original](../06-fitxes-funcionals/uc-032.md) · [UC-123 lliurament original](../06-fitxes-funcionals/uc-123.md) · [UC-36 documents](uc-036-generar-consultar-documents.md) · [InvoiceRepository](../../sif/src/Repository/InvoiceRepository.php) · [Migració electronic_invoice_delivery](../../sif/database/migrations/2026_09_16_000005_add_operation_lifecycle_tables.sql).
