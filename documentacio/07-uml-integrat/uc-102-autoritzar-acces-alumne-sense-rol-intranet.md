# UC-102 · Autoritzar l'accés de l'alumne sense rol d'intranet

**Objectiu original:** identitat externa o token segur permet **només recursos propis**, amb denegacions auditables. **Estat [DISSENY/BLOQUEJANT].** L'alumne **no** hereta permisos de gestió, tutor, responsable de grup o emissor perquè comparteix `IDPAG`, email o un enllaç de pagament.

## Evidència del model i límits del PHP

La migració `2026_09_16_000006_add_cross_system_control_tables.sql` defineix `external_identity_link` amb `SUBJECT_KEY`, `SYSTEM_CODE`, `EXTERNAL_ID/IDENTITY_TYPE`, hashes de correu/document, `STATUS/VERIFIED_AT/VERIFIED_BY`, vigència i `SNAPSHOT_HASH`. `identity_conflict_case` defineix expedients quan diversos identificadors es contradiuen. **No s'ha acreditat** un writer d'aquestes taules ni un controlador PHP SIF que autentiqui, expedeixi/revoqui tokens d'alumne i transformi de manera segura la identitat verificada en permís sobre recursos.

`fact_rels` relaciona factura i origen, amb `VISIBLE_ALUMNE`; el builder de grup configura `VISIBLE_ALUMNE=0` per participants. `DocumentRepository::registerDocument()` registra **metadades**, no serveix el PDF físic. `fiscal_document_access` té `TOKEN_FINGERPRINT`, actor, rol, acció, resultat i `UUID_FACTURA`, però **la presència d'aquest camp no crea un sistema de tokens ni prova que el servidor revalidi cada descàrrega**.

| Sol·licitud | Autorització específica |
| --- | --- |
| Veure dades/inscripció pròpies | Validar identitat externa ↔ subjecte canònic ↔ `ID_INSC` concret i estat de l'enllaç; no confiar en `ID_INSC` subministrat pel client ni en un email compartit. |
| Veure factura o PDF | Validar `UUID_FACTURA`, receptor/pagador/representant, `fact_rels` i política `VISIBLE_ALUMNE`, amb comprovació del document físic i hash UC-80. Participar en un grup **no** autoritza a veure la factura de l'empresa/responsable. |
| Enllaç de pagament | Una URL/token per pagar una operació permet **únicament** l'acció monetària limitada corresponent, no consultar factures alienes, canviar el titular, demanar refund o actuar com a gestor. |
| Token d'accés | Proposta: secret opac, identificador de subjecte, recurs/acció, expiració, revocació i fingerprint/hash de servidor; no guardar token en clar al log ni reutilitzar secrets d'admin o de Redsys. El protocol complet encara és **pendent**. |
| Canvi de correu, duplicat i baixa | Revalidar `external_identity_link` i expedient UC-126; revocar/enfortir accés quan el subjecte és ambigu. Canviar email no transfereix propietat d'una factura ni d'un ingrés real. |
| Auditoria | Cada lectura/denegació deixa `REQUEST_ID`, subjecte, document, rol/canal i resultat a `fiscal_document_access` mitjançant writer **pendent**. Una resposta HTTP 200 no demostra que la persona hagi llegit realment el document. |

### Flux específic

1. Alumne inicia sessió amb proveïdor extern verificat o presenta token de consulta d'abast concret. El servidor **pendent** comprova emissor del token, vigència, revocació i vincle `external_identity_link` actiu; un conflicte UC-126 bloqueja només recursos lligats a identitat incerta.
2. Resoldre `SUBJECT_KEY`, inscripcions pròpies i paper respecte de cada factura. **Mai** autoritzar només per `IDPAG`, coincidència de NIF/email o per haver pagat un curs en benefici d'un tercer.
3. Quan hi ha factura autoritzada, UC-80 comprova `VISIBLE_ALUMNE`, propietari/receptor, document privat físic i hash; registrar tant intent denegat com consulta legítima. Per un grup/empresa, donar resposta sense dades de tercers ni pistes d'identificació.
4. Caducitat, revocació, canvi de receptor o nova resolució d'identitat han d'impedir futures descàrregues sense alterar PDF, factura, `CHARGE` ni drets d'altres inscrits.
5. La consulta no pot disparar un nou `InvoiceService::issueInvoice()`, `PaymentService::registerPayment()` ni canviar l'accés Moodle; aquestes ordres tenen autoritzacions i efectes independents.

**Proves:** parella amb email compartit, pagador d'una altra persona, factura de grup `VISIBLE_ALUMNE=0`, token de pagament reutilitzat per llegir PDF, URL d'altre `ID_INSC`, alumne que fa POST a API d'admin, canvi d'identitat externa, token revocat i factura amb metadada PDF però fitxer absent.

**Pendents:** proveïdor d'identitat de l'alumne, emissor de tokens i revocació, model/worker d'enllaços canònics, controlador servidor per recurs, integració UC-80 de storage/document i tests de permís denegat.

### Una URL de pagament i una identitat externa no són permisos de lectura fiscal

**Cas real de factura emesa abans de cobrar.** La pantalla llegada `/alumnes/genera-factura-abans-pagar/` pot agrupar diverses inscripcions en un document **d'empresa o responsable** i el circuit de `Passar pagaments` permet cercar-les per DNI, número fiscal i `FACTURA_RELACIONADA`. Aquestes referències són **claus de cerca/agrupació**, no secrets d'autenticació ni proves de representació de l'entitat. Una URL de pagament d'una operació pot ser legítima per al pagador, però **no** obre per això la consulta del PDF complet a cadascun dels participants; una persona que coneix `NUM_VISIBLE` o el token monetari tampoc ha d'obtenir `BILLING_*` de tercers.

**Vincle verificat subjecte–inscripció–document.** `external_identity_link` preveu en SQL `SUBJECT_KEY/SYSTEM_CODE/EXTERNAL_ID`, hashes de correu/document, vigència i verificació. Abans d'autoritzar el portal d'alumne, el servei **pendent** ha de comprovar prova vigent del subjecte, relació amb el **`ID_INSC` concret** i paper respecte de **`UUID_FACTURA`**: receptor, persona representant acreditada o només participant. `fact_rels.VISIBLE_ALUMNE=0` al builder de grup limita la visibilitat prevista; però una eventual fila `VISIBLE_ALUMNE=1` importada per defecte de l'històric **no ha de sobreescriure** la comprovació del receptor ni autoritzar la factura d'empresa sencera. Mostrar estat de cobertura autoritzat sense exposar noms, NIF o imports d'altres persones.

**Separar dos tipus de token i els seus efectes.** El token comercial per pagar s'ha de limitar a operació/import/acció de pagament, mentre que un token documental ha de limitar recurs, destinatari, versió, expiració i revocació. `fiscal_document_access.TOKEN_FINGERPRINT` és una **columna d'auditoria**, no prova d'una implementació que emeti o validi tokens. El controlador de descàrrega ha de comprovar el document físic i hash UC-80 i la titularitat **a cada petició**, no una sola vegada en crear l'enllaç; revocar o canviar un enllaç no modifica el document fiscal, `UUID_PAYMENT` ni els drets dels altres inscrits.

**Múltiples perfils i intents parcials.** Una persona pot ser simultàniament alumne, gestor d'una empresa i tutor, però la coincidència de `CORREU`, `IDPAG` o DNI de cerca no fusiona subjectes ni permet acumular permisos de diferents sessions. Davant d'un conflicte d'identitat UC-126, protegir la consulta afectada i conservar la via de regularització del **pagador autoritzat** quan existeix deute real; no denegar una inscripció acadèmica ja confirmada ni crear factura per resoldre un error d'accés. Si el PDF és `PENDING` o manquen els bytes reals, respondre «document no disponible» **després d'autoritzar** sense substituir-lo per un PDF antic regenerat del llegat.

### Proves de separació de token, rol i recurs (no executades)

| ID | Escenari | Resultat exigible |
| --- | --- | --- |
| AX-102-01 | Alumne coneix `FACTURA_RELACIONADA` de tres membres d'empresa | Estat mínim propi segons dret; cap PDF o dades fiscals de l'empresa/altres persones. |
| AX-102-02 | Usar token d'URL de pagament per cridar endpoint de PDF | Denegació documental; token de pagament no és autorització de lectura. |
| AX-102-03 | Import històric crea `VISIBLE_ALUMNE=1` en una relació de grup | Autorització de receptor/representant addicional obligatòria per factura completa. |
| AX-102-04 | Alumne, tutor i contacte d'entitat comparteixen correu | Permisos per identitat/rol i recurs comprovats, cap fusió automàtica. |
| AX-102-05 | Token documental vàlid però arxiu amb hash discordant | No retornar el fitxer; incidència UC-78/80 i accés auditat. |
| AX-102-06 | Enllaç revocat mentre el PDF original continua custodiat | Denegar noves descàrregues amb aquell token sense alterar factura, PDF o cobrament. |

## UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Alumne" as A
actor "Gestió d'identitat autoritzada" as G
rectangle "Portal alumne → SIF · accés propi" {
 usecase "UC-102\nAutoritzar consulta d'alumne" as Main
 usecase "Resoldre identitat externa i ID_INSC" as Identity
 usecase "Verificar rol i receptor real del document" as Parties
 usecase "UC-80\nServir PDF privat i auditar denegació" as Read
 usecase "UC-126\nResoldre conflicte d'identitat" as Conflict
}
A --> Main
G --> Conflict
Main ..> Identity : <<include>>
Main ..> Parties : <<include>>
Read ..> Main : <<extend>> (document autoritzat)
Conflict ..> Main : <<extend>> (identitat ambigua)
@enduml
```

### Vista de casos d’ús per a GitHub (Mermaid)

```mermaid
flowchart LR
  actor_0["Alumne"]
  actor_1["Gestió d'identitat autoritzada"]
  subgraph SIF_BOX["Portal alumne → SIF · accés propi"]
    uc_0(["UC-102<br/>Autoritzar consulta d'alumne"])
    uc_1(["Resoldre identitat externa i ID_INSC"])
    uc_2(["Verificar rol i receptor real del document"])
    uc_3(["UC-80<br/>Servir PDF privat i auditar denegació"])
    uc_4(["UC-126<br/>Resoldre conflicte d'identitat"])
  end
  actor_0 --> uc_0
  actor_1 --> uc_4
  uc_0 -.->|include| uc_1
  uc_0 -.->|include| uc_2
  uc_3 -.->|extend| uc_0
  uc_4 -.->|extend| uc_0
```

## UML de classes

```mermaid
classDiagram
class StudentResourceAuthorizationService {
 <<DISSENY: no acreditat al PHP SIF>>
 +resolveStudent(token) subject
 +authorize(subject,resource,action) decision
}
class ExternalIdentityLinkRepository {
 <<DISSENY: external_identity_link SQL>>
 +findVerified(systemCode,externalId) subject
}
class IdentityConflictCaseRepository {
 <<DISSENY: identity_conflict_case SQL>>
 +openOrResolve(conflict) result
}
class FiscalDocumentAccessRepository {
 <<DISSENY: fiscal_document_access SQL>>
 +append(db,attempt) result
}
class StudentDocumentGateway {
 <<DISSENY: document físic/permís UC-80>>
 +serveAuthorized(subject,uuidFactura) bytes
}
StudentResourceAuthorizationService --> ExternalIdentityLinkRepository : subjecte verificat
StudentResourceAuthorizationService --> IdentityConflictCaseRepository : bloquejar ambigüitat
StudentResourceAuthorizationService --> FiscalDocumentAccessRepository : lectura i denegació
StudentResourceAuthorizationService --> StudentDocumentGateway : recurs autoritzat
```

## UML de seqüència — participant de grup intenta veure factura d'empresa

```mermaid
sequenceDiagram
actor A as Alumne
participant S as StudentResourceAuthorizationService [DISSENY]
participant I as external_identity_link [SQL definit]
participant R as fact_rels + factura [SIF]
participant D as FiscalDocumentAccessRepository [SQL, writer pendent]
participant G as StudentDocumentGateway UC-80 [DISSENY]
A->>S: Demanar PDF de factura F amb token
S->>I: Verificar identitat externa i subjecte canònic
I-->>S: SUBJECT_KEY i enllaç actiu
S->>R: Comprovar ID_INSC, receptor/pagador i VISIBLE_ALUMNE
alt Factura d'empresa amb VISIBLE_ALUMNE=0
 R-->>S: L'alumne no és receptor/representant autoritzat
 S->>D: Registrar intent denegat [pendent]
 S-->>A: Accés denegat, sense dades de l'empresa
else Recurs propi autoritzat
 R-->>S: Accés document concret aprovat
 S->>G: Verificar PDF real i hash, servir bytes
 S->>D: Registrar resultat de consulta [pendent]
 S-->>A: Document propi, cap acció fiscal nova
end
Note over S,G: Les taules d'identitat i d'accés no implementen per si soles un token segur.
```

## Traçabilitat

[UC-102 original](../06-fitxes-funcionals/uc-102.md) · [UC-80 document](uc-080-servir-registrar-acces-document-fiscal.md) · [UC-126 identitat](uc-126-identitat-contacte-conflicte-sistemes.md) · [UC-44 relacions](uc-044-consultar-mantenir-fact-rels-origen-legacy.md) · [UC-99 tutor](uc-099-limitar-intranet-tutors-no-fiscal.md) · [Migració identitat](../../sif/database/migrations/2026_09_16_000006_add_cross_system_control_tables.sql) · [Migració accés documental](../../sif/database/migrations/2026_09_15_000003_add_functional_audit_control.sql) · [DocumentRepository](../../sif/src/Repository/DocumentRepository.php).
