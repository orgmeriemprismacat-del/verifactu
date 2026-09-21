# UC-51 · Callback Redsys denegat, duplicat, tardà o contradictori

**Frontera funcional.** UC-51 qualifica una notificació concreta de Redsys; UC-03 és el circuit ordinari de recepció, cua i emissió; UC-52 gestiona l'execució/recuperació dels jobs. **Estat verificat:** `RedsysCallbackService`, `RedsysNotificationRepository`, `RedsysPaymentIntentRepository`, `RedsysCallbackQueueRepository` i `IncidentRepository` existeixen. «Tardà» no té una regla de caducitat explícita verificada al servei de recepció: no s'afirma que rebutgi automàticament totes les notificacions fora de termini.

## 1. Fitxa funcional basada en el codi

| Situació | Comportament executable observat | Límit o decisió pendent |
| --- | --- | --- |
| Signatura invàlida | `receiveCallback(db,payload,false)` llança error **abans** d'entrar a la transacció i no desa notificació/job. | El tractament del log i la resposta HTTP depèn de l'adaptador extern. |
| Ordre desconeguda | `findByDsOrder(...,true)` no troba `redsys_payment_intent`; error i rollback. | El servei no inventa una intenció a posteriori. |
| Import, divisa o terminal diferents | `assertMatchesIntent` exigeix igualtat respecte de la intenció; error i rollback de notificació/job. | El codi consultat només obre `REDSYS_CALLBACK` automàticament per excepció de **conflicte 409**, no per tot error de validació 422. |
| Redsys denega | `statusForResponseCode`: sols codis numèrics 0–99 són `VALIDATED`; la resta `ERROR`. Desa notificació denegada i **no encua job**. | La denegació no cancel·la per si sola una factura ja emesa ni revoca una inscripció anterior. |
| Duplicat equivalent de `DS_ORDER` | `recordReceived` comprova import, codi resposta, divisa, terminal, versió de signatura i hash; retorna `duplicate=true`. Una notificació validada equivalent reutilitza el job perquè `enqueue` és idempotent per `NOTIFICATION_ID`. | No repetir `CHARGE`, factura ni atribució individual. |
| Duplicat contradictori | Si `DS_ORDER` existent té dades diferents, `SifException::conflict`; el servei fa rollback i després obre `errors_verifactu` tipus `REDSYS_CALLBACK`. | No permetre que el segon callback reescrigui l'import, receptor o snapshot del primer. |
| Callback tardà | En el codi de recepció consultat **no es verifica `EXPIRES_AT` ni l'estat acadèmic de la inscripció** abans d'encuar una notificació validada. | Cal decidir tractament de compra/inscripció canviada o anul·lada abans de l'emissió efectiva, amb conciliació i UC-71/72 si escau. |
| Resposta denegada després d'una altra resposta | Si comparteixen `DS_ORDER` però difereixen en codi o altres camps, es classifiquen com a contradictòries; no se substitueix la notificació existent. | Cal revisar el resultat del proveïdor abans de qualsevol moviment compensatori. |

**Precondicions del canal:** signatura verificada per `RedsysSignatureValidator`; `receiveAuthorizedCallback()` pressuposa que el canal ja li entrega dades signades verificades. **No s'ha d'exposar públicament aquest segon mètode sense la comprovació del primer.** No desar PAN/CVV ni publicar payloads complets en una incidència accessible a alumnes.

### 1.1. Efectes fiscals i econòmics

UC-51 **no crea una factura ni un `payment_transaction` directament**: el callback autoritzat i validat només crea/reutilitza notificació i feina de cua. UC-52/03 poden emetre la factura i registrar l'import bancari confirmat més endavant. Quan el callback es contradiu amb una factura ja processada, cal revisar `UUID_FACTURA`, `UUID_PAYMENT`, `DS_ORDER` i eventual atribució a cada inscripció: **no** inventar un segon cobrament o una devolució només per una resposta tardana.

### 1.2. Ordre antiga, cobertura d'empresa i devolució posterior — contrast amb el xat original

**L-ORDRE — tardà respecte a què?** El xat diferencia una intenció individual iniciada abans que una empresa assumeixi el pagament, una inscripció canviada de curs/baixa i un enllaç antic d'import desfasat. Revocar l'enllaç o canviar la inscripció **no és cancel·lar la transacció Redsys ja iniciada**. La recepció valida signatura i intenció original, però ha de distingir la validesa tècnica del callback de l'autorització de generar ara una **factura nova per l'antic servei**. Si el banc ha cobrat realment, conservar-ne la prova i tramitar assignació, excés, retorn o incidència segons la situació; no suprimir el `CHARGE` ni emetre una factura duplicada per resoldre el conflicte.

**L-COBERTURA — factura d'empresa anterior al callback.** Abans que el worker processi una intenció individual validada, contrastar `ID_INSC`, `UUID_FACTURA` de grup/empresa, `DS_ORDER`, intents actius i altres cobraments. La comparació de callback per la mateixa DS_ORDER detecta duplicació **d'aquesta ordre**, però no que una **altra** DS_ORDER o transferència ja hagi cobert l'operació. Si hi ha cobertura incompatible, preservar la notificació real, suspendre emissió/cobrament de venda incompatible i obrir conciliació. El bloqueig entre sistemes, la política de devolució i el coordinador continuen pendents.

**L-DENEGAT — mateixa referència operativa, intents diferents.** Un `IDPAG` pot tenir un primer intent denegat i un segon acceptat amb DS_ORDER diferent. L'ordre denegada pot figurar a `redsys_notifications` com `ERROR` i no genera job/CHARGE, però no impedeix per ella mateixa que una nova ordre vàlida es processi. En canvi, dos callbacks diferents per **la mateixa DS_ORDER** amb imports/respostes contradictoris exigeixen incidència i verificació externa, no agafar l'últim rebut com a veritat.

**L-FITXER — relació amb conciliació de CSV.** El fitxer TPV de la intranet és evidència addicional de l'operació bancària i pot descobrir callback perdut o job no processat; no crea una notificació signada retroactiva ni pot convertir un resultat `state=1` del comparador antic en cobrament SIF verificat. Revisió a UC-25 i recuperació de job a UC-52.

**Proves addicionals no executades:** mateixa IDPAG amb una denegació i una acceptació a DS_ORDER diferents; callback individual tardà després de factura d'empresa; callback de diferència de curs ja revertit; notificació de DS_ORDER antic amb import desfasat; callback validat encara en RETRY que apareix com a «no facturat» al CSV; en tots els casos conciliar el cobrament real sense una factura/assignació duplicada.

## 2. Diagrama UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Redsys" as Bank
actor "Responsable tècnica" as Tech
rectangle "SIF · callbacks" {
 usecase "UC-51\nQualificar callback anòmal" as Main
 usecase "Verificar signatura i intenció" as Verify
 usecase "Registrar notificació immutable" as Record
 usecase "UC-03\nEncolar autoritzat" as Queue
 usecase "UC-08\nRevisar contradicció" as Incident
}
Bank --> Main
Main ..> Verify : <<include>>
Main ..> Record : <<include>> (si és vàlid)
Tech --> Incident
Queue ..> Main : <<extend>> (autoritzat i validat)
@enduml
```

## 3. Diagrama de classes — dependències PHP

```mermaid
classDiagram
direction LR
class RedsysCallbackService {
 +receiveCallback(db,payload,signatureValid) array
 +receiveAuthorizedCallback(db,signedData) array
}
class RedsysPaymentIntentRepository {
 +findByDsOrder(db,dsOrder,forUpdate) array
}
class RedsysNotificationRepository {
 +recordReceived(db,dsOrder,idpag,amount,responseCode,valid,raw,status) array
}
class RedsysCallbackQueueRepository {
 +enqueue(db,notificationId,uuidIntent) array
}
class IncidentRepository {
 +open(db,uuidFactura,type,message) array
}
RedsysCallbackService --> RedsysPaymentIntentRepository : intenció congelada
RedsysCallbackService --> RedsysNotificationRepository : resposta i duplicats
RedsysCallbackService --> RedsysCallbackQueueRepository : només VALIDATED
RedsysCallbackService --> IncidentRepository : conflicte 409
```

### 3.1. Projecció de classes proposades: guard de cobertura abans de facturar un callback tardà

El diagrama següent és un **contracte pendent del worker/handler**, no una crida PHP verificada. `RedsysCallbackService` només valida la notificació i la intenció: cap dependència de cobertura per inscripció s'ha acreditat al seu constructor. Les classes executables del callback figuren al subdiagrama anterior.

```mermaid
classDiagram
direction LR
class RedsysCallbackService {
 <<PHP existent>>
 +receiveCallback(db,payload,signatureValid) array
}
class RedsysCallbackWorker {
 <<PHP existent>>
 +runOne(db,workerId,now) array
}
class LatePaymentCoverageGuard {
 <<DISSENY: no acreditat al PHP>>
 +check(intentSnapshot,actualEnrollment,invoiceCoverage) decision
}
class EnrollmentInvoiceCoverageReader {
 <<DISSENY: no acreditat al PHP>>
 +findByEnrollmentIds(ids) matches
}
class RedsysCallbackDispatcher {
 <<PHP existent: integració amb guard pendent>>
 +process(db,job) array
}
class IncidentRepository {
 <<PHP existent: l'obertura automàtica general depèn del camí>>
 +open(db,uuidFactura,type,message) array
}
RedsysCallbackWorker --> RedsysCallbackDispatcher : job validat
RedsysCallbackDispatcher ..> LatePaymentCoverageGuard : comprovació prèvia a emissió [PENDENT]
LatePaymentCoverageGuard --> EnrollmentInvoiceCoverageReader : cobertura actual
LatePaymentCoverageGuard ..> IncidentRepository : conflicte [PENDENT]
```
## 4. Seqüència — notificació i alternatives reals

```mermaid
sequenceDiagram
autonumber
actor Bank as Redsys
participant C as Canal/verificador de signatura
participant S as RedsysCallbackService
participant I as RedsysPaymentIntentRepository
participant N as RedsysNotificationRepository
participant Q as RedsysCallbackQueueRepository
participant E as IncidentRepository
Bank->>C: Callback signat
alt Signatura invàlida
 C--xBank: Rebuig; sense notificació/job al servei
else Signatura vàlida
 C->>S: receiveAuthorizedCallback(payload verificat)
 S->>I: findByDsOrder(DS_ORDER,true)
 alt Intenció absent o import/divisa/terminal no coincideixen
  S--xC: Validació i rollback
 else Intenció coherent
  S->>N: recordReceived(DS_ORDER,code,payload,status)
  alt Duplicat contradictori
   N--xS: conflict 409
   S->>E: open(REDSYS_CALLBACK,detalls) després de rollback
   S--xC: Conflicte sense nou job
  else Denegat
   N-->>S: ERROR
   S-->>C: status ERROR, sense job
  else Autoritzat o duplicat equivalent
   N-->>S: notification_id, duplicate?
   S->>Q: enqueue(notification_id,UUID_INTENT)
   Q-->>S: job existent o QUEUED nou
   S-->>C: Resposta HTTP lògica amb job; cap factura al callback
  end
 end
end
```

### 4.1. Acció: rebre notificació denegada — el PHP NO encua cap feina fiscal

```mermaid
sequenceDiagram
autonumber
actor Bank as Redsys
participant EP as callback.php [ENDPOINT]
participant Sig as RedsysSignatureValidator [PHP]
participant S as RedsysCallbackService [PHP]
participant I as RedsysPaymentIntentRepository [PHP]
participant N as RedsysNotificationRepository [PHP]
participant DB as BD SIF
Bank->>EP: POST amb resposta fora de 0…99 i signatura
EP->>Sig: decodeAndVerify(POST)
alt Signatura invàlida
 Sig--xEP: Error abans d'enregistrar notificació
 EP-->>Bank: Resposta HTTP d'error
else Signatura vàlida i intenció coherent
 Sig-->>EP: Payload verificat
 EP->>S: receiveCallback(db,payload,true)
 S->>DB: BEGIN
 S->>I: findByDsOrder(ds_order,true) i contrastar import/divisa/terminal
 I-->>S: Intenció coherent
 S->>N: recordReceived(status=ERROR)
 N->>DB: INSERT redsys_notifications(ERROR) o detectar duplicat
 N-->>S: notification_id i duplicate
 S->>DB: COMMIT
 S-->>EP: status, duplicate, queue_status=null
 EP-->>Bank: Resposta HTTP
end
Note over S,DB: No INSERT a redsys_callback_queue ni creació de factura/CHARGE per resposta denegada.
```

### 4.2. Acció: callback repetit equivalent o contradictori — dues sortides diferents

```mermaid
sequenceDiagram
autonumber
actor Bank as Redsys
participant S as RedsysCallbackService [PHP]
participant N as RedsysNotificationRepository [PHP]
participant Q as RedsysCallbackQueueRepository [PHP]
participant Inc as IncidentRepository [PHP]
participant DB as BD SIF
Bank->>S: Callback signat i amb intenció coherent, DS_ORDER ja registrat
S->>DB: BEGIN
S->>N: recordReceived(ds_order,payload,status)
N->>DB: Intent INSERT sobre clau única DS_ORDER
alt Mateixa resposta/import/divisa/terminal/signature_version/hash
 N->>DB: SELECT notificació existent
 N-->>S: duplicate=true, notification_id existent, status=DUPLICATE
 opt El codi de resposta és VALIDATED
  S->>Q: enqueue(notification_id,uuid_intent)
  Q-->>S: job existent o recuperable sense segon job per notificació
 end
 S->>DB: COMMIT
 S-->>Bank: duplicate=true i resultat del job si escau
else Dades contradictòries per la mateixa DS_ORDER
 N--xS: conflict 409
 S->>DB: ROLLBACK
 S->>Inc: open(REDSYS_CALLBACK,detalls de conflicte) [intenta]
 S--xBank: Error; cap job nou ni substitució de notificació
end
Note over S,Q: Dues DS_ORDER diferents amb mateix IDPAG no són duplicat automàtic: cal conciliar cada fet bancari.
```

### 4.3. Acció: callback tardà després de factura d'empresa, canvi de curs o baixa — CONTROL PENDENT

El codi de `RedsysCallbackService::receiveAuthorizedCallback()` verifica signatura prèviament, intenció/ordre, import, divisa i terminal; en el camí revisat **no compara `EXPIRES_AT`, factura de grup ja emesa, baixa o canvi d'inscripció** abans d'encuar. El diagrama següent és el **guard previ a facturar en el worker que falta acreditar**, no la descripció d'un control executable del callback.

```plantuml
@startuml
left to right direction
actor "Redsys" as Bank
actor "Responsable de gestió" as O
rectangle "SIF PrisMa — ordre tardana" {
 usecase "UC-51\nAcceptar evidència signada\nd'un ingrés real" as Received
 usecase "UC-03 / UC-52\nProcessar job idempotent" as Worker
 usecase "Comprovar cobertura fiscal\ni estat actual per inscripció" as Coverage
 usecase "UC-53 / UC-81\nObrir conciliació/incidència" as Incident
 usecase "UC-104 / UC-28\nDecidir excedent o retorn\nsi correspon" as Money
}
Bank --> Received
Received ..> Worker : <<include>> [si VALIDATED]
Worker ..> Coverage : <<include>> [OBJECTIU]
O --> Incident
O --> Money
note bottom of Coverage
 Cap nova factura fiscal sobre una inscripció
 ja coberta només perquè arriba DS_ORDER antiga.
end note
@enduml
```

```mermaid
sequenceDiagram
autonumber
actor Bank as Redsys
participant S as RedsysCallbackService [PHP]
participant Q as Cua Redsys [SQL/worker PHP]
participant Guard as Guard de cobertura/estat [DISSENY]
participant F as Factures/inscripcions actuals SIF + llegat
participant P as Handler de venda i emissió [PHP]
participant I as Incidències/conciliació [PENDENT]
Bank->>S: Notificació signada de DS_ORDER antiga però ingressada
S->>Q: Guardar i encuar si VALIDATED
S-->>Bank: Recepció confirmada, no factura encara
Q->>Guard: Abans d'emetre, rellegir operació congelada i estat vigent [OBJECTIU]
Guard->>F: Consultar UC-04/21 ja emesa, baixa/canvi i altres ingressos
alt Factura existent/operació canviada o import incompatible
 F-->>Guard: Cobertura incompatible o deute ja satisfet
 Guard->>I: Conservar ingrés real i obrir conciliació sense nova factura
 I-->>Q: Job en incidència fins a decisió traçada [OBJECTIU]
else Cobertura i pagament coherents amb oferta congelada
 Guard-->>Q: Autoritzar un únic processament
 Q->>P: Continuar handler idempotent UC-03
 P-->>Q: UUID_FACTURA i UUID_PAYMENT confirmats
end
Note over Guard,P: El worker existent no acredita aquest guard transversal; no descartar diners ni atorgar una plaça automàticament en conflicte.
```

| ID de prova pendent | Escenari | Sortida a acreditar |
| --- | --- | --- |
| CB-51-01 | Resposta signada denegada amb intenció existent | Notificació `ERROR`; cap job fiscal ni `CHARGE`. |
| CB-51-02 | Dues notificacions equivalents mateixa DS_ORDER | Una notificació lògica, job reutilitzat quan VALIDATED; una sola factura i ingrés al worker. |
| CB-51-03 | Mateixa DS_ORDER, import/codi/hash contradictori | Conflicte 409, rollback i incidència; no sobreescriure l'original. |
| CB-51-04 | IDPAG compartit però dues DS_ORDER i dos ingressos legítims | Conciliar com a dos fets reals sense col·lapsar-los per IDPAG. |
| CB-51-05 | Callback validat d'intenció individual després de factura d'empresa | No duplicar factura; conservar prova bancària i classificar imputació/sobrant. |
| CB-51-06 | Callback d'una inscripció canviada/baixa amb reserva vençuda | No concedir plaça ni facturar oferta obsoleta sense revisió; no descartar cobrament real. |
## 5. Proves i traçabilitat

Proves localitzades, **no executades en aquesta revisió**: `RedsysCallbackTest` cobreix signatura, denegat, duplicat equivalent/contradictori, import diferent, ordre repetida i ús d'`IDPAG` de la intenció. Queden pendents les proves de **caducitat real**, callback després d'una baixa o canvi de curs i la conciliació de fons per inscripció.

[UC-51 original](../06-fitxes-funcionals/uc-051.md) · [UC-03](uc-003-processar-cobrament-redsys-asincron.md) · [UC-52](../06-fitxes-funcionals/uc-052.md) · [UC-63](uc-063-crear-intencio-redsys.md) · [RedsysCallbackService](../../sif/src/Service/RedsysCallbackService.php) · [RedsysNotificationRepository](../../sif/src/Repository/RedsysNotificationRepository.php) · [RedsysCallbackTest](../../sif/tests/Integration/RedsysCallbackTest.php).
