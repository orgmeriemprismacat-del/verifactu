# UC-99 · Limitar la intranet de tutors a un circuit no fiscal de venda

**Objectiu original:** les funcions de consulta i honoraris del col·laborador **no permeten emetre, modificar ni veure factures de venda alienes**. **Estat [DISSENY/PENDENT].** UC-65/66 descriuen el document de proveïdor i la liquidació d'honoraris; UC-99 defineix el **perímetre d'autorització de totes les rutes** de la intranet de tutor.

## Evidència i límit de les API actuals

`sif/public/api/factures/issue.php` llegeix JSON i instancia directament `InvoiceService::issueInvoice()`; `sif/public/api/payments/register.php` instancia directament `PaymentService::registerPayment()`. **No hi consta en aquests dos fitxers cap verificació de sessió/rol**. Això no demostra que un tutor els pugui invocar des del desplegament real —les regles del servidor i de xarxa no s'han verificat—, però **tampoc acredita que s'hi apliqui una prohibició per rol de tutor**. Ocultar els botons de factura o devolució al navegador no substitueix el control del servidor.

`OperationalEventRepository::append()` és writer genèric de traça, no un autenticador. Les dades fiscals `factura, factura_linia, fact_rels` pertanyen al circuit de venda; la consulta d'una factura de tutor/col·laborador UC-65 és **document de proveïdor propi**, no autorització per llegir un PDF de factura d'empresa d'un grup d'alumnes.

| Recurs o acció | Capacitat prevista del tutor |
| --- | --- |
| Servei/activitat que imparteix | Consultar només edicions, participants i dades acadèmiques **necessàries i autoritzades** per la funció, sense exportació massiva de dades personals o bancàries. La matriu exacta de camps és **pendent** segons UC-99 original. |
| Factura/rebut d'honoraris propi | Presentar i consultar només el seu document i estat UC-65, amb identitat del proveïdor verificada; no utilitzar `InvoiceService` de vendes. |
| Cobraments/bestretes propis | Veure estat/honoraris realment corresponents a l'encàrrec UC-66; no invocar `PaymentService::registerPayment()` de vendes ni transferir-se bestretes per autoaprovació. |
| Factura de curs/alumne/empresa | Denegar lectura d'artefactes aliens, numeració, `BILLING_*` de tercer, NIF i altres dades de pagador. Les relacions `fact_rels` amb `VISIBLE_ALUMNE=0` no són autorització per al tutor. |
| API d'emissió, correcció, cobrament, refund o AEAT | Denegar **a nivell servidor** per rol de tutor, fins i tot cridant la ruta directament, canviant el JSON o reutilitzant una URL; registrar denegació i correlació sense exposar secrets. |

### Flux funcional

1. Un controlador real d'identitat **pendent** verifica sessió del tutor, `collaborator_id`, rol i encàrrecs/edicions assignats. No confondre mateixa persona amb rols d'alumne, tutor i pagador; cada rol ha d'acreditar drets per recurs.
2. Cada petició comprova subjecte, rol, propietat del document o adscripció a l'edició **abans** de llegir dades. Filtrar per `idTutor` a la interfície sense verificació backend no és suficient.
3. Les peticions d'honoraris es dirigeixen al circuit adjacent UC-65/66; qualsevol consulta a `factura_documents` o canvi econòmic de venda segueix autorització SIF independent i no deriva del rol de tutor.
4. Requerir control d'entrada autenticat/autoritzat per les API d'emissió i pagaments **i comprovació de segmentació d'entorn/xarxa UC-101**; les rutes PHP existents no porten un check visible de sessió i rol. No afirmar que el servidor produeix ja un 403 fins a provar-ho.
5. Registrar accessos, denegacions i ús de recursos amb actor/rol/abast; provar totes les rutes amb permisos de tutor, no només els menús. UC-45 d'auditor temporal és un rol diferent i no s'hereta automàticament.

**Proves:** tutor que altera `ID_INSC` en URL, col·laborador i alumne amb mateix email, tutor que intenta veure factura d'empresa del grup, POST directe a `factures/issue.php`, POST directe a `payments/register.php`, lectura de dades d'un altre tutor, canvi de rol en sessió oberta i llistat de participants amb NIF bancari.

**Pendents:** inventari de rutes i permisos actuals de la intranet tutor, matriu de camps de participants autoritzats, autenticació API de venda, aplicació al proxy/servidor, auditoria d'accés i proves d'accés denegat a tot el perímetre.

### La mateixa persona pot tenir dos rols: perímetre de tutor i API SIF

**Dos circuits amb objecte diferent.** UC-65/66 descriuen documents i cobraments de **proveïdor/col·laborador**, mentre que `/alumnes/pagaments/` i `/alumnes/genera-factura-abans-pagar/` són operacions de **venda a alumnat o empreses**. Un tutor pot ser alhora alumne, contacte d'una empresa o comprador en nom propi, però la seva sessió de tutor **no concedeix automàticament** els permisos d'aquests altres subjectes. L'autorització per consultar honoraris propis ha de verificar identificador de proveïdor i encàrrec concret, sense reutilitzar `IDPAG`, `CORREU` o la matrícula dels seus alumnes com a vincle per llegir factures de grup.

**Rutes fiscals que requereixen una barrera efectiva.** Els endpoints `sif/public/api/factures/issue.php` i `sif/public/api/payments/register.php` reben JSON i deleguen en els serveis SIF, **sense comprovació explícita de sessió/rol visible en aquests arxius**. No és prova que siguin accessibles des d'Internet o des de la intranet tutor: encara s'han de revisar autenticació al proxy/servidor, rutes exposades i permisos productius. El contracte objectiu exigeix **autorització de servidor abans de l'efecte**, actor i rol verificats, recurs exacte, scope i denegació auditable. El valor `created_by` del payload o una URL privada **no** substitueixen aquesta autorització. Els intents de tutor d'emetre, cobrar, modificar receptor o descarregar el PDF d'una empresa han de denegar-se al mateix servidor encara que canviï el JSON o s'oculti el menú.

**Consulta docent amb minimització.** El tutor necessita les matrícules/edicions assignades i les dades pedagògiques autoritzades per impartir, no una vista completa de `BILLING_NIF_CIF`, comptes bancaris, preus de l'empresa o totes les matrícules del mateix `IDPAG`. `fact_rels.VISIBLE_ALUMNE=0` als participants d'una factura d'empresa **no és un rol alternatiu de tutor** ni demostra que l'expositor de documents faci el filtratge; UC-80/102 aplica verificació de cada consulta i descàrrega, i ha de registrar també intents denegats sense revelar metadades de tercers.

**Prova separada de lectura i d'escriptura.** L'inventari de rutes de la intranet tutor i les polítiques efectives del servidor **no estan acreditats en el repositori revisat**. La revisió d'activació ha de provar (a) que el tutor pot veure únicament el seu encàrrec/honoraris, (b) que no pot llegir dades fiscals alienes a través de cerca, PDF o URL directa, i (c) que les API SIF no accepten ordres de venda sota rol de tutor; repetir les proves amb la mateixa persona iniciant sessió en rols diferents, sense heretar permisos de la sessió anterior.

### Proves de frontera tutor i venda fiscal (no executades)

| ID | Escenari | Resultat exigible |
| --- | --- | --- |
| TU-99-01 | Tutor consulta document d'honoraris propi | Accés únicament al proveïdor/encàrrec corresponent, sense dades d'una factura de venda. |
| TU-99-02 | Tutor coneix `IDPAG` del grup que imparteix | No veure PDF complet de l'empresa ni dades bancàries per aquesta referència. |
| TU-99-03 | Tutor fa POST directe a `factures/issue.php` o `payments/register.php` | Denegació al punt d'entrada autoritzat, amb log, cap mutació SIF. |
| TU-99-04 | Mateixa persona actua en sessions de tutor i comprador particular | Scopes diferenciats per sessió/acció; cap permís acumulat implícit. |
| TU-99-05 | Tutor manipula `created_by` o l'ID del document a la petició | Cap escalada de rol ni consulta de factura de tercers. |
| TU-99-06 | La ruta només es protegeix amagant el botó a la interfície | Prova de crida directa detecta absència de control; no declarar permís verificat. |

## UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Tutor/col·laborador" as T
actor "Gestió autoritzada" as G
rectangle "Intranet tutor · perímetre no fiscal" {
 usecase "UC-99\nAccés no fiscal del tutor" as Main
 usecase "Validar tutor i encàrrec específic" as Auth
 usecase "UC-65/66\nDocuments i honoraris propis" as Adjacent
 usecase "Denegar emissió/consulta fiscal aliena" as Deny
 usecase "Auditar intent i resultat" as Audit
}
T --> Main
G --> Adjacent
Main ..> Auth : <<include>>
Main ..> Adjacent : <<include>> (consulta pròpia)
Main ..> Deny : <<include>> (petició no autoritzada)
Main ..> Audit : <<include>>
@enduml
```

### Vista de casos d’ús per a GitHub (Mermaid)

```mermaid
flowchart LR
  actor_0["Tutor/col·laborador"]
  actor_1["Gestió autoritzada"]
  subgraph SIF_BOX["Intranet tutor · perímetre no fiscal"]
    uc_0(["UC-99<br/>Accés no fiscal del tutor"])
    uc_1(["Validar tutor i encàrrec específic"])
    uc_2(["UC-65/66<br/>Documents i honoraris propis"])
    uc_3(["Denegar emissió/consulta fiscal aliena"])
    uc_4(["Auditar intent i resultat"])
  end
  actor_0 --> uc_0
  actor_1 --> uc_2
  uc_0 -.->|include| uc_1
  uc_0 -.->|include| uc_2
  uc_0 -.->|include| uc_3
  uc_0 -.->|include| uc_4
```

## UML de classes

```mermaid
classDiagram
class TutorAccessPolicy {
 <<DISSENY: control servidor no acreditat>>
 +authorize(actor,resource,action) decision
}
class TutorPortalController {
 <<DISSENY: adaptador per inventariar>>
 +listAssignedCourses(actor) items
 +ownHonoraria(actor) items
}
class CollaboratorDocumentSubmissionService {
 <<DISSENY: UC-65>>
 +submit(actor,serviceRef,document) receipt
}
class CollaboratorSettlementService {
 <<DISSENY: UC-66>>
 +balance(collaboratorId,period) money
}
class InvoiceService {
 <<PHP existent: venda; accés tutor denegat>>
 +issueInvoice(payload) array
}
class PaymentService {
 <<PHP existent: CHARGE venda; accés tutor denegat>>
 +registerPayment(payload) array
}
TutorPortalController --> TutorAccessPolicy : cada petició
TutorPortalController --> CollaboratorDocumentSubmissionService : document propi
TutorPortalController --> CollaboratorSettlementService : honoraris propis
TutorAccessPolicy ..> InvoiceService : no delegar rol tutor
TutorAccessPolicy ..> PaymentService : no delegar rol tutor
```

## UML de seqüència — tutor intenta invocar API de venda

```mermaid
sequenceDiagram
actor T as Tutor
participant P as TutorPortalController [DISSENY]
participant A as TutorAccessPolicy [DISSENY]
participant H as Honoraris de col·laborador UC-65/66
participant I as API SIF factures/issue.php [PHP actual]
participant E as Auditoria d'accés [pendent]
T->>P: Consultar els meus honoraris per encàrrec
P->>A: Validar tutor/encàrrec
A-->>P: Consulta del recurs propi autoritzada
P->>H: Consultar estat de proveïdor
H-->>T: Honoraris propis sense factura de venda
T->>I: POST directe de payload d'emissió de factura
Note over I,A: El fitxer PHP actual no acredita un check de sessió/rol.
I->>A: Comprovar rol/acció ABANS d'InvoiceService [integració pendent]
A-->>I: DENIED per rol tutor
I->>E: Registrar denegació i REQUEST_ID [pendent]
I-->>T: Accés denegat, cap factura ni número nou
```

## Traçabilitat

[UC-99 original](../06-fitxes-funcionals/uc-099.md) · [UC-65 document proveïdor](uc-065-presentar-factura-rebut-collaborador.md) · [UC-66 honoraris](uc-066-gestionar-cobraments-collaborador.md) · [UC-101 entorns original](../06-fitxes-funcionals/uc-101.md) · [UC-102 alumne original](../06-fitxes-funcionals/uc-102.md) · [Ruta emissió PHP](../../sif/public/api/factures/issue.php) · [Ruta cobrament PHP](../../sif/public/api/payments/register.php) · [OperationalEventRepository](../../sif/src/Repository/OperationalEventRepository.php).
