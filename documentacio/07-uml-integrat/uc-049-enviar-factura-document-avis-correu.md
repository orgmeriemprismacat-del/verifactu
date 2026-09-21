# UC-49 · Enviar factura, document o avís per correu

**Objectiu del catàleg:** enviament al destinatari autoritzat amb plantilla, document real i resultat auditable. **Estat [DISSENY].** UC-49 és la decisió **de contingut i destinatari per una comunicació concreta**; UC-58 és el motor persistent d'enviament. El correu d'una factura no és per si sol lliurament de factura electrònica en format/canal acordats (UC-123), ni tramesa AEAT (UC-77).

## Evidència

`notification_outbox` i `notification_delivery_attempt` estan definides al SQL d'auditoria amb plantilla/versionat, `RECIPIENT_TYPE/HASH`, `PAYLOAD_JSON`, enllaç opcional amb `UUID_FACTURA/UUID_PAYMENT`, estat, següent intent i referència del proveïdor. `DocumentRepository::registerDocument()` registra **només metadades i hash dels bytes aportats**, no comprova que `PATH_FITXER` tingui un fitxer físic servible. **No s'ha acreditat** al PHP revisat ni un controlador complet d'enviament fiscal de correu, ni un worker d'outbox, ni el custodi/servei de documents amb comprovació de permisos. Un camp `BILLING_EMAIL` o `CORREU` no prova automàticament que el destinatari pugui rebre la factura de grup/empresa.

## Contracte de contingut i autorització

| Acció | Control específic |
| --- | --- |
| Triar comunicació | Distingir avís de factura emesa, sol·licitud de pagament, confirmació de cobrament **real**, rectificativa, document informatiu i avís acadèmic. El text no pot afirmar «pagat» per `DS_ORDER` pendent ni «factura emesa» per una proforma. |
| Triar destinatari | Validar receptor fiscal/representant, pagador i contacte vigent. Per factura de grup creada amb responsable de `respGrups` i relacions `VISIBLE_ALUMNE=0`, **no adjuntar-la als participants** pel sol fet de compartir `IDPAG`. |
| Adjuntar o enllaçar | Confirmar `UUID_FACTURA`, tipus de document, existència i integritat **real dels bytes** UC-78, i permís UC-80. Preferir enllaç privat, caducable i limitat en abast quan la política ho requereixi; no posar una ruta de storage o token reutilitzable en un correu sense protecció. |
| Plantilla i sortida | Conservar `TEMPLATE_CODE/VERSION`, idioma, objecte i destinatari, causa, `REQUEST_ID` i correlació; censurar NIF, dades sensibles i secrets en assumpte/log. Un email de gestió no és una forma de donar consentiment comercial UC-125. |
| Enviar i auditar | Crear ordre idempotent **després** de confirmar el fet, registrar l'intent/canal/proveïdor, i diferenciar acceptació del servidor de correu, lliurament i lectura. Un timeout pot tenir resultat incert i exigeix evitar duplicar l'avís de manera indiscriminada. |
| Efecte fiscal i diner | Enviar o reenviar email no emet nova factura ni crea `CHARGE/REFUND` o entrada AEAT. Si falla només correu/document, recuperar **la mateixa factura** sense tornar a cobrir/rectificar l'operació original. |

### Flux objectiu i proves

1. L'operador identifica **el fet existent** i el tipus de comunicació, comprova receptor/pagador, grup, rol, fitxer i estat fiscal/econòmic real. Si falta document o autorització, l'avís queda pendent, **no** es fabrica un PDF en el correu.
2. Previsualitzar plantilla, destinatari i enllaç privat. Registrar acceptació/ordre amb idempotència per fet+tipus+receptor i persistir outbox. Per correus amb adjunt, relacionar document **exacte** i hash, no només `UUID_FACTURA`.
3. El worker UC-58 **pendent** envia amb prova d'intent. En resposta incerta conserva l'event original i consulta la referència del proveïdor abans de reintentar si el canal ho permet.
4. Mostrar separadament «preparat», «enviat», «lliurat si acreditat» i «document consultat». Un avís d'email no modifica `ESTAT_AEAT` ni `ESTAT_COBRAMENT`.
5. Provar: factura abans de cobrar, una factura de tres alumnes a empresa, email compartit, PDF absent amb metadada `CREATED`, token caducat, correu duplicat amb timeout, devolució autoritzada però encara no executada i baixa acadèmica amb Moodle pendent.

**Pendents:** plantilla i política de destinatari, storage i link segur, worker/outbox, tractament de lliurament, idempotència en proveïdor i proves de privacitat. Cap correu real enviat ni test executat en aquesta revisió.

### Missatgeria real del llegat i notificació correcta d'una factura d'empresa

**Distingir receptor fiscal i contacte.** A les pantalles antigues d'entitats, `entitats_resp.CORREU` identifica un **contacte/responsable** que pot rebre URL de pagament, factura o enllaç segur; la documentació del projecte precisa que el responsable **no és necessàriament el receptor fiscal**. Per a cada comunicació de grup/empresa, recuperar la factura i el seu receptor fiscal, identificar la persona autoritzada a rebre-la i verificar a quin correu concret s'ha d'enviar. La coincidència amb el correu de l'alumne o amb `IDPAG` compartit no acredita aquesta autorització, i la factura completa no s'ha de lliurar per defecte a tots els participants.

**Missatge segons fase real.** El procés de «Generar factura abans de pagar» crea una factura **real** amb `EMESA_ABANS_COBRAMENT=1`; el primer correu al responsable pot indicar número, concepte i **pendent de cobrament** i oferir una URL específica del responsable. Si després «Passar pagaments» confirma una transferència, el correu històric pot informar d'import, data, concepte i resta pendent si és fraccionat. L'adaptació ha de consultar `payment_transaction/payment_allocation` per no afirmar «pagada» abans d'un `CHARGE` real. Quan la factura **ja està pagada**, el missatge ha d'oferir consulta de factura/PDF/QR, no insistir en un enllaç de pagament individual obsolet; cap participant ha de rebre la factura fiscal completa de l'empresa només perquè la seva matrícula hi figura.

**Document pendent i format de lliurament.** Un `UUID_FACTURA` confirmat no demostra que `factura_documents` contingui un PDF físic íntegre. Si l'avís requereix adjunt o consulta documental, esperar l'artefacte verificat i l'autorització UC-55/78/80, o redactar un avís de «factura emesa, document pendent» que **no presenti un PDF inexistent com a adjunt**. El projecte no fixa una opció universal **adjunt vs enllaç segur** per a totes les plantilles: l'elecció ha de quedar vinculada al cas, receptor i política aprovada. Un correu amb URL segura no prova el lliurament d'un format electrònic específic (UC-123) sense evidència separada.

**Independència dels retries.** La notificació té el seu propi identificador i clau idempotent per fet, plantilla/versió i destinatari autoritzat. Una fallada de correu després d'emetre/registrar el cobrament només reintenta la comunicació UC-58, **no** l'emissió de factura o el moviment econòmic. Un canvi de responsable, baixa o URL revocada abans d'enviar exigeix revalidar destinatari/enllaç i cancel·lar l'avís obsolet, conservant qualsevol intent real ja efectuat.

### Proves de comunicació d'empresa i factura prèvia (no executades)

| ID | Escenari | Resultat exigible |
| --- | --- | --- |
| MC-49-01 | Empresa rep factura prèvia real sense cobrament | Missatge «pendent», URL autoritzada i cap confirmació fictícia de pagament. |
| MC-49-02 | Responsable és contacte però no receptor fiscal directe | Verificar autorització i correu concret abans de lliurar factura completa. |
| MC-49-03 | Alumne de grup comparteix IDPAG i demana PDF de l'empresa | No adjuntar/lliurar document complet sense dret verificat. |
| MC-49-04 | Factura pagada, recordatori de pagament encara pendent d'enviar | Cancel·lar l'avís antic i oferir consulta documental quan estigui disponible. |
| MC-49-05 | Factura emesa però job PDF pendent | No prometre adjunt ni enllaç funcional a bytes absents. |
| MC-49-06 | Correu falla després d'emetre factura i cobrar | Reintentar només UC-58; mateix UUID_FACTURA i UUID_PAYMENT. |

## UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Gestió" as G
actor "Receptor/pagador autoritzat" as R
rectangle "SIF · enviament documental" {
 usecase "UC-49\nEnviar factura, document o avís" as Main
 usecase "Validar tipus de fet i destinatari" as Auth
 usecase "UC-80\nVerificar document i accés" as Doc
 usecase "UC-58\nEncolar i trametre" as Outbox
 usecase "Registrar resultat del canal" as Proof
}
G --> Main
R --> Main
Main ..> Auth : <<include>>
Doc ..> Main : <<extend>> (comunicació amb document)
Main ..> Outbox : <<include>>
Outbox ..> Proof : <<include>>
@enduml
```

### Vista de casos d’ús per a GitHub (Mermaid)

```mermaid
flowchart LR
  actor_0["Gestió"]
  actor_1["Receptor/pagador autoritzat"]
  subgraph SIF_BOX["SIF · enviament documental"]
    uc_0(["UC-49<br/>Enviar factura, document o avís"])
    uc_1(["Validar tipus de fet i destinatari"])
    uc_2(["UC-80<br/>Verificar document i accés"])
    uc_3(["UC-58<br/>Encolar i trametre"])
    uc_4(["Registrar resultat del canal"])
  end
  actor_0 --> uc_0
  actor_1 --> uc_0
  uc_0 -.->|include| uc_1
  uc_2 -.->|extend| uc_0
  uc_0 -.->|include| uc_3
  uc_3 -.->|include| uc_4
```

## UML de classes

```mermaid
classDiagram
class FiscalEmailCompositionService {
 <<DISSENY: no acreditat>>
 +preview(event,recipient,template) message
 +scheduleApproved(message) uuidNotification
}
class DocumentAuthorizationPolicy {
 <<DISSENY: permís per factura i actor>>
 +canRead(actor,uuidFactura) decision
}
class DocumentRepository {
 <<PHP existent: només metadades>>
 +registerDocument(db,uuidFactura,type,path,contents) array
}
class NotificationOutboxRepository {
 <<DISSENY: notification_outbox SQL>>
 +insertOrReuse(db,message) result
}
class NotificationDeliveryAttemptRepository {
 <<DISSENY: notification_delivery_attempt SQL>>
 +record(db,attempt) result
}
FiscalEmailCompositionService --> DocumentAuthorizationPolicy : si adjunt
FiscalEmailCompositionService --> NotificationOutboxRepository : ordre després del fet
NotificationOutboxRepository ..> NotificationDeliveryAttemptRepository : worker UC-58 pendent
```

## UML de seqüència — factura d'empresa i destinatari no autoritzat

```mermaid
sequenceDiagram
actor G as Gestió
participant C as FiscalEmailCompositionService [DISSENY]
participant F as Factura + fact_rels [SQL]
participant A as DocumentAuthorizationPolicy [DISSENY]
participant D as Storage documental verificat [DISSENY]
participant Q as notification_outbox [SQL]
participant W as Worker de notificacions UC-58 [DISSENY]
G->>C: Proposar enviar factura de grup a participant
C->>F: Consultar receptor real, VISIBLE_ALUMNE i estat
C->>A: Validar rol/destinatari sobre UUID_FACTURA
alt Participant no autoritzat
 A-->>C: DENIED
 C-->>G: No revelar factura ni enllaç
else Receptor/representant autoritzat
 A-->>C: ALLOWED
 C->>D: Verificar PDF real, hash i token privat si cal
 C->>Q: Encolar plantilla/versió/document/destinatari [writer pendent]
 W->>Q: Reclamar notificació [pendent]
 W-->>G: Resultat d'intent, no reemissió fiscal
end
Note over C,W: Enviar correu no acredita recepció ni canvia factura o pagament.
```

## Traçabilitat

[UC-49 original](../06-fitxes-funcionals/uc-049.md) · [UC-58 outbox original](../06-fitxes-funcionals/uc-058.md) · [UC-79 comunicació fiscal](uc-079-comunicacio-fiscal-auditable.md) · [UC-78 documents](uc-078-generar-custodiar-pdf-qr-xml.md) · [UC-80 autorització](uc-080-servir-registrar-acces-document-fiscal.md) · [UC-123 electrònica](uc-123-lliurar-factura-electronica.md) · [DocumentRepository](../../sif/src/Repository/DocumentRepository.php) · [SQL outbox](../../sif/database/migrations/2026_09_15_000003_add_functional_audit_control.sql).
