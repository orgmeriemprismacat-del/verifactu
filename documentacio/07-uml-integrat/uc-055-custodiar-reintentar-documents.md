# UC-55 · Generar, reintentar i custodiar documents fiscals

**Frontera funcional.** UC-36 defineix la generació/consulta d'un PDF, QR o XML concret; UC-55 gestiona **la cua documental, els reintents, la versió del generador, la integritat i la custòdia**. UC-07 controla qui pot veure/baixar el document. La factura fiscal, un cop emesa, no s'anul·la ni es reemet perquè falli el PDF.

**Evidència revisada:** `DocumentRepository::registerDocument()` existeix i registra a `factura_documents` tipus, path i SHA-256 dels bytes **rebuts**; `document_job` i `fiscal_document_access` estan definits en una migració SQL. **No s'ha identificat el generador final de PDF/QR/XML, el worker de `document_job`, el gestor d'emmagatzematge privat ni l'endpoint autoritzat de descàrrega** com a implementacions PHP completes. Per tant, el cicle complet dibuixat a continuació és **DISSENY/PARCIAL**, no prova de desplegament.

## 1. Fitxa de cas d'ús

| Camp | Comportament específic |
| --- | --- |
| Actor | Procés documental i responsable tècnica amb permís d'operació; receptor/auditor accedeix posteriorment sota UC-07. |
| Disparador | Factura/rectificativa confirmada, document absent, o recuperació d'un job fallit. |
| Entrada immutable | UUID i snapshot fiscal de la factura, tipus documental, versió del generador i correlació; no consultar el preu mutable del curs per «reconstruir» el document anterior. |
| Job definit al SQL | `document_job` té `UUID_JOB`, `UUID_FACTURA`, `DOCUMENT_TYPE`, `IDEMPOTENCY_KEY` única, `GENERATOR_VERSION`, `STATUS=PENDING`, `ATTEMPTS`, `MAX_ATTEMPTS=5` per defecte, lock, reintent, `FACTURA_DOCUMENT_ID`, `STORAGE_KEY`, `OUTPUT_HASH`, `LAST_ERROR` i `CORRELATION_ID`. **No és un worker ja implementat.** |
| Metadades implementades | `DocumentRepository::registerDocument(db,uuidFactura,type,path,contents)`: tipus `PDF/XML/QR`, SHA-256 i metadata `CREATED`. La funció **no desa els bytes** en storage ni prova que el path existeixi. |
| Resultat objectiu | Fitxer privat verificat, metadades i hash coherents, feina acabada amb referència al document, i accés posterior autoritzat/auditat. |
| Efecte fiscal/econòmic | Cap factura, registre AEAT o `CHARGE` nou com a efecte d'un reintent documental; la factura original continua emesa si el document falla. |

### 1.1. Flux objectiu de generació i custòdia

1. Després del commit de factura, l'orquestrador **proposat** encola un job idempotent identificat per factura, tipus i versió, conservant font fiscal congelada i correlació.
2. El worker **pendent** reclama un job i encarrega els bytes al generador versionat. Per al QR, llegenda i formats finals s'ha de verificar la regla oficial aplicable abans d'afirmar conformitat; `XmlCodec` de remissió AEAT no és per si sol un generador universal de documents de factura.
3. Desa els bytes en storage privat, torna a llegir o verifica integritat i calcula SHA-256. Només aleshores crida el mètode **existent** `DocumentRepository::registerDocument()`; un `HASH_FITXER` a BD no prova per si sol que el fitxer estigui custodiat.
4. El worker **proposat** enllaça `FACTURA_DOCUMENT_ID` i `STORAGE_KEY` al job i marca finalització, sense sobreescriure silenciosament un document de versió anterior.
5. En error de generació, storage, hash o inserció, conserva `LAST_ERROR`, programa un reintent idempotent o obre incidència UC-08. **La política efectiva i el writer de retries encara no s'han acreditat al PHP.**
6. A la consulta, UC-07 torna a autoritzar per actor, rol, receptor i document, serveix des de storage privat i deixa traça `fiscal_document_access`. Aquest pas és pendent de servei de lectura/descàrrega.

### 1.2. Alternatives i invariants

| Escenari | Resposta |
| --- | --- |
| PDF falla després d'emetre factura | Factura fiscal intacta; job pendent/incidència i posterior generació, **sense un altre número de factura**. |
| Mateixa petició repetida | Reutilitzar el job/document quan coincideixen UUID, tipus, versió i bytes; la clau SQL única no acredita el writer idempotent fins que s'implementi. |
| Fitxer desat però falla l'INSERT de metadata | Detectar fitxer orfe i reprendre sense perdre hash/versió; no mostrar `CREATED` fals. |
| Metadata `CREATED` sense bytes o amb SHA-256 diferent | Bloquejar descàrrega, incidència de custòdia i recerca de la còpia correcta. |
| Canvi de plantilla/versió | Preservar la còpia històrica i justificar una nova representació; no modificar factura ni hash anterior. |
| Rectificativa | Document propi de la nova factura, vinculat a la factura original sense reemplaçar-ne el document. |
| Grup/empresa | UC-07 restringeix la consulta segons receptor i visibilitat: disposar d'un UUID o d'un path no concedeix accés. |
| Error de QR/XML | Verificar que el resultat correspon al snapshot correcte i al tipus documental, sense confondre XML de remissió amb XML lliurable. |

**Proves detectades, no executades:** `DocumentsAndIncidentsTest` comprova metadades i hash de `DocumentRepository`, però no el cicle de storage, reintents, permisos o generació real del PDF/QR.

### 1.3. Prioritat del document de factura prèvia i coherència amb el correu

**Necessitat del circuit d'empresa.** A `/alumnes/genera-factura-abans-pagar/`, PrisMa emet una factura **real** que pot necessitar-se per enviar al responsable/empresa i cobrar més endavant. El pas de `issueInvoice()` confirma `UUID_FACTURA/NUM_VISIBLE` encara que la generació del PDF/QR sigui asíncrona. El contracte de pantalla ha de mostrar **factura emesa + document `PENDING`** quan el worker encara no ha desat els bytes i prioritzar el job documental de la factura prèvia segons l'operativa acordada; no ajornar l'existència de la factura fins que estigui llest el PDF.

**Tall del lliurament.** Si un correu al responsable ha d'incloure el PDF o l'enllaç de consulta, no crear una notificació que presenti l'adjunt com a disponible fins que es comprovi el document real i l'autorització del receptor (UC-49/58/80). Si falla només el PDF, reprendre **el mateix `document_job`/UUID_FACTURA**; si el correu ha fallat després de generar els bytes, reprendre només la notificació. Ni una incidència documental ni un canvi d'`E_FACT` impliquen tornar a generar el registre fiscal o registrar cobrament nou.

**Origen immutable davant regeneració llegada.** El llegat exposa `mostraModalPrevFactura_Factures.php`, `descarregaFactura.php` i `generaFactura($id,true)`, que poden reconstruir un PDF amb dades vives; `eliminarArxiu.php` rep un nom de fitxer temporal per esborrar-lo. A la ruta SIF, el document s'ha de generar del snapshot fiscal congelat i conservar un hash dels **bytes realment escrits** en storage privat. No convertir el generador llegat ni la neteja via filename en la ruta de custòdia de la factura fiscal original.

**Original, rectificativa i nou renderitzat.** La factura original i la rectificativa són dos `UUID_FACTURA` i requereixen documents independents. Si es modifica una plantilla, conservar identificador de versió i la representació anterior; reintentar un job fallit de **la mateixa versió** no ha de sobreescriure silenciosament un document custodiat ni retornar dos PDFs originals incompatibles.

### 1.4. Proves addicionals de disponibilitat i comunicació (no executades)

| ID | Escenari | Resultat exigible |
| --- | --- | --- |
| DC-55-01 | Factura real abans de cobrar confirmada, PDF encara PENDING | Mostrar UUID/número i document pendent; no reemetre per obtenir PDF. |
| DC-55-02 | Job documental falla, cua AEAT/ingrés ja confirmats | Reintentar document exclusivament; conservar estats extern i econòmic. |
| DC-55-03 | Correu de factura requereix PDF però bytes absents | Comunicació documental pendent o avís adequat sense afirmar PDF disponible. |
| DC-55-04 | PDF original existent i rectificativa emesa | Dos UUIDs i dos documents, sense substituir el primer. |
| DC-55-05 | Fitxer antic eliminat amb nom procedent de GET | La ruta nova no accepta esborrat d'artefactes fiscals per filename aportat pel navegador. |

## 2. Diagrama UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Procés documental" as Worker
actor "Responsable tècnica" as Tech
rectangle "SIF · operació documental" {
 usecase "UC-55\nCustodiar i recuperar documents" as Main
 usecase "Reclamar job i generar bytes" as Gen
 usecase "Comprovar storage, hash i versió" as Check
 usecase "Registrar metadades del document" as Meta
 usecase "Programar reintent o incidència" as Retry
 usecase "UC-07\nConsulta autoritzada" as View
}
Worker --> Main
Tech --> Retry
Main ..> Gen : <<include>>
Main ..> Check : <<include>>
Main ..> Meta : <<include>>
Retry ..> Main : <<extend>> (fallada)
Tech --> View
@enduml
```

### Vista de casos d’ús per a GitHub (Mermaid)

```mermaid
flowchart LR
  actor_0["Procés documental"]
  actor_1["Responsable tècnica"]
  subgraph SIF_BOX["SIF · operació documental"]
    uc_0(["UC-55<br/>Custodiar i recuperar documents"])
    uc_1(["Reclamar job i generar bytes"])
    uc_2(["Comprovar storage, hash i versió"])
    uc_3(["Registrar metadades del document"])
    uc_4(["Programar reintent o incidència"])
    uc_5(["UC-07<br/>Consulta autoritzada"])
  end
  actor_0 --> uc_0
  actor_1 --> uc_4
  uc_0 -.->|include| uc_1
  uc_0 -.->|include| uc_2
  uc_0 -.->|include| uc_3
  uc_4 -.->|extend| uc_0
  actor_1 --> uc_5
```

## 3. Diagrama de classes — PHP existent i disseny separat

```mermaid
classDiagram
direction LR
class DocumentRepository {
 <<PHP existent>>
 +registerDocument(db,uuidFactura,type,path,contents) array
}
class DocumentJobRepository {
 <<DISSENY: writer no acreditat>>
 +enqueue(command) job
 +claimNext() job
 +complete(job,documentId,hash) result
 +fail(job,error,nextAttempt) result
}
class FiscalDocumentGenerator {
 <<DISSENY: no acreditat>>
 +generate(snapshot,type,version) bytes
}
class PrivateDocumentStore {
 <<DISSENY: no acreditat>>
 +writeAndVerify(bytes) key
 +readVerified(key,sha256) bytes
}
class DocumentWorker {
 <<DISSENY: no acreditat>>
 +runOne(now) result
}
class InvoiceDocumentAccessService {
 <<DISSENY: no acreditat>>
 +download(actor,documentId,token) bytes
}
DocumentWorker --> DocumentJobRepository : cua
DocumentWorker --> FiscalDocumentGenerator : generar
DocumentWorker --> PrivateDocumentStore : custòdia
DocumentWorker --> DocumentRepository : metadades/hash
InvoiceDocumentAccessService --> PrivateDocumentStore : lectura autoritzada
```

## 4. Seqüència principal — generació i error recuperable (DISSENY)

```mermaid
sequenceDiagram
autonumber
participant Inv as Factura SIF ja emesa
participant J as DocumentJobRepository [DISSENY]
participant W as DocumentWorker [DISSENY]
participant G as FiscalDocumentGenerator [DISSENY]
participant Store as PrivateDocumentStore [DISSENY]
participant R as DocumentRepository [EXISTENT]
Inv->>J: Encolar UUID_FACTURA + tipus + versió
W->>J: claimNext() idempotent
J-->>W: Job amb font immutable
W->>G: generate(snapshot,type,version)
alt Generació/custòdia correctes
 G-->>W: bytes
 W->>Store: writeAndVerify(bytes)
 Store-->>W: path privat, SHA-256 verificat
 W->>R: registerDocument(db,UUID_FACTURA,type,path,bytes)
 R-->>W: Metadata factura_documents
 W->>J: complete(job,documentId,hash)
else Error de bytes, storage o metadata
 G--xW: Error
 W->>J: fail(job,error,nextAttempt)
 Note over W,J: Incidència/retry pendents d'implementar
end
Note over Inv,R: DocumentRepository existeix, la resta del workflow és OBJECTIU
```

## 5. Seqüència de custòdia i descàrrega (DISSENY)

```mermaid
sequenceDiagram
actor A as Receptor/auditor
participant UI as Canal [pendent]
participant Access as InvoiceDocumentAccessService [DISSENY]
participant Store as PrivateDocumentStore [DISSENY]
participant Audit as fiscal_document_access [taula definida]
A->>UI: Sol·licitar documentId
UI->>Access: download(actor,documentId,token/sessió)
alt Identitat/visibilitat denegada
 Access->>Audit: Registrar DENIED
 Access-->>UI: Accés denegat sense path
else Identitat/visibilitat autoritzada
 Access->>Store: readVerified(storageKey,hash)
 alt Bytes no disponibles o hash incorrecte
  Store--xAccess: Error custòdia
  Access->>Audit: Registrar FAIL
  Access-->>UI: Incidència, no servir fitxer incorrecte
 else Bytes íntegres
  Store-->>Access: Bytes
  Access->>Audit: Registrar DOWNLOAD
  Access-->>UI: Stream segur
 end
end
```

### 5.1. Acció independent: encolar un document després de confirmar la factura — DISSENY

**Disparador:** `InvoiceService` ja ha confirmat la factura o s'ha autoritzat generar una representació que no existeix. **Actor:** procés documental; el client o el worker no pot crear un nou número fiscal per aconseguir un PDF. **Entrades:** `UUID_FACTURA`, `DOCUMENT_TYPE`, `GENERATOR_VERSION`, referència a la font fiscal congelada, `REQUEST_ID` i correlació. **Postcondició:** exactament un job pendent per la **mateixa petició lògica** o recuperació del job preexistent; no s'afirma disponibilitat dels bytes ni acceptació AEAT. `document_job.IDEMPOTENCY_KEY` és única al SQL, però la taula **no imposa** `UNIQUE(UUID_FACTURA,DOCUMENT_TYPE,GENERATOR_VERSION)`: la política de versió i la clau del productor han de determinar si dues peticions són equivalents o dues representacions diferents.

```plantuml
@startuml
left to right direction
actor "Procés després de commit factura" as P
actor "Responsable documental" as R
rectangle "SIF PrisMa — UC-55 / ENCOLAR [DISSENY]" {
 usecase "Programar generació d'un document\nper factura/tipus/versió" as Enqueue
 usecase "Comprovar factura confirmada\ni snapshot immutable" as Check
 usecase "Distingir reintent equivalent\nde nova versió autoritzada" as Idp
 usecase "Registrar document_job pendent" as Save
}
P --> Enqueue
R --> Enqueue
Enqueue ..> Check : <<include>>
Enqueue ..> Idp : <<include>>
Enqueue ..> Save : <<include>> [si no existeix job equivalent]
@enduml
```

### Vista de casos d’ús per a GitHub (Mermaid)

```mermaid
flowchart LR
  actor_0["Procés després de commit factura"]
  actor_1["Responsable documental"]
  subgraph SIF_BOX["SIF PrisMa — UC-55 / ENCOLAR [DISSENY]"]
    uc_0(["Programar generació d'un document<br/>per factura/tipus/versió"])
    uc_1(["Comprovar factura confirmada<br/>i snapshot immutable"])
    uc_2(["Distingir reintent equivalent<br/>de nova versió autoritzada"])
    uc_3(["Registrar document_job pendent"])
  end
  actor_0 --> uc_0
  actor_1 --> uc_0
  uc_0 -.->|include| uc_1
  uc_0 -.->|include| uc_2
  uc_0 -.->|include| uc_3
```

```mermaid
sequenceDiagram
autonumber
participant A as Adaptador després de COMMIT [PENDENT]
participant F as Consulta factura/snapshot immutable [LECTURA]
participant J as DocumentJobRepository [DISSENY]
participant DB as document_job [SQL definit]
A->>F: Consultar UUID_FACTURA confirmat, tipus i versió aprovada
alt Factura absent, tipus no admès o instant anterior al commit
 F-->>A: Denegar l'encolat, cap nova emissió fiscal
else Factura existent
 F-->>A: Font congelada i identificadors persistents
 A->>J: enqueue(UUID_FACTURA,tipus,versió,requestId)
 J->>DB: Cercar IDEMPOTENCY_KEY i contrastar dades
 alt Reintent equivalent
  DB-->>J: UUID_JOB preexistent
  J-->>A: Reutilitzar job/estat sense document duplicat
 else Mateixa clau però factura/tipus/versió diferents
  DB-->>J: CONFLICT
  J-->>A: Incidència, no reutilitzar resultat aliè
 else Clau nova amb petició legitimada
  J->>DB: INSERT document_job STATUS=PENDING [OBJECTIU]
  DB-->>J: UUID_JOB
  J-->>A: Job acceptat, document encara no disponible
 end
end
Note over A,DB: El SQL té clau única de job, no s'ha acreditat productor/enqueue PHP ni el guard de payload.
```

### 5.2. Acció independent: recuperar un job amb resultat incert després d'escriure els bytes — DISSENY

**Límit addicional del codi i esquema:** `DocumentRepository::registerDocument()` retorna `ok` i `hash`, **no retorna `factura_documents.ID`**. La taula `factura_documents` tampoc no té una columna de versió ni un `UNIQUE(UUID_FACTURA,TIPUS,PATH_FITXER,HASH_FITXER)`; el workflow ha de recuperar i contrastar de manera inequívoca el registre creat i conservar la versió/identitat del `document_job` abans de marcar-lo complet. **No** deduir l'ID de document d'un mètode que no el proporciona.

**Disparador:** el worker cau entre l'escriptura en storage privat, l'alta de `factura_documents` i el marcatge `document_job.STATUS=COMPLETED`. **Precondició:** mateixa factura, tipus, versió i `UUID_JOB` originals. **Postcondició:** reconciliar el fitxer real, `HASH_FITXER`, `FACTURA_DOCUMENT_ID` i `OUTPUT_HASH`; si ja hi ha document idèntic, recuperar-lo en lloc de generar-ne un altre, i si hi ha dues representacions divergents, bloquejar la publicació i registrar incidència. Un `UNIQUE(IDEMPOTENCY_KEY)` del job **no** protegeix per si sol de dobles `INSERT factura_documents`, ja que `DocumentRepository::registerDocument()` insereix un registre nou per cada crida i no cerca `UUID_JOB` ni un document equivalent.

```plantuml
@startuml
left to right direction
actor "Worker documental" as W
actor "Responsable tècnica" as R
rectangle "SIF PrisMa — UC-55 / RECUPERAR [DISSENY]" {
 usecase "Recuperar un job documental incert" as Recover
 usecase "Revalidar storage, bytes i SHA-256" as Hash
 usecase "Localitzar metadata/document preexistents" as Existing
 usecase "Finalitzar job o obrir incidència" as Finish
}
W --> Recover
R --> Finish
Recover ..> Hash : <<include>>
Recover ..> Existing : <<include>>
Recover ..> Finish : <<include>>
@enduml
```

### Vista de casos d’ús per a GitHub (Mermaid)

```mermaid
flowchart LR
  actor_0["Worker documental"]
  actor_1["Responsable tècnica"]
  subgraph SIF_BOX["SIF PrisMa — UC-55 / RECUPERAR [DISSENY]"]
    uc_0(["Recuperar un job documental incert"])
    uc_1(["Revalidar storage, bytes i SHA-256"])
    uc_2(["Localitzar metadata/document preexistents"])
    uc_3(["Finalitzar job o obrir incidència"])
  end
  actor_0 --> uc_0
  actor_1 --> uc_3
  uc_0 -.->|include| uc_1
  uc_0 -.->|include| uc_2
  uc_0 -.->|include| uc_3
```

```mermaid
sequenceDiagram
autonumber
actor W as Worker/operador autoritzat
participant J as DocumentJobRepository [DISSENY]
participant Store as PrivateDocumentStore [DISSENY]
participant D as DocumentRepository [PHP: insert metadata]
participant DB as document_job + factura_documents [SQL]
participant I as Incidència custòdia [DISSENY]
W->>J: recover(UUID_JOB) amb lock/versió
J->>DB: Llegir job, factura, tipus, versió, hash i documentId
J->>Store: Comprovar storage key i hash dels bytes físics
alt Bytes absents o hash diferent de la font acceptada
 Store-->>J: NOT_FOUND/MISMATCH
 J->>I: Registrar incidència, no mostrar document com a disponible
 J-->>W: ERROR/PENDING_REVIEW sense tocar factura fiscal
else Bytes íntegres
 Store-->>J: Bytes i hash real
 J->>DB: Consultar document preexistent per job i hash
 alt Metadata existent per mateix fitxer i font immutable
  DB-->>J: FACTURA_DOCUMENT_ID i hash coherents
  J->>DB: Lligar job al document i marcar complet [OBJECTIU]
  J-->>W: Reús del document existent
 else Metadata absent i cap document contradictori
  J->>D: registerDocument(db,uuidFactura,tipus,path,bytes) [PHP existent]
  D->>DB: INSERT factura_documents CREATED
  DB-->>D: Metadades inserides sense garantia pròpia de storage
  J->>DB: Recuperar ID de la fila inserida i verificar factura/path/hash
  J->>DB: Enllaçar FACTURA_DOCUMENT_ID i OUTPUT_HASH, COMMIT [OBJECTIU]
  J-->>W: Document recuperat i custòdia verificada
 else Metadata preexistent però hash/factura/versió contradictoris
  J->>I: Bloquejar publicació i investigar origen dels bytes
  J-->>W: CONFLICT sense regenerar o reemplaçar l'original
 end
end
Note over J,DB: No existeix al PHP acreditat el worker, la cerca de metadata equivalent ni una transacció atòmica amb storage. La recuperació és disseny.
```

### 5.3. Acció independent: comprovar disponibilitat i integritat abans de publicar el document — UC-55/80, DISSENY

**Disparador:** el panell anuncia una factura prèvia com a descarregable, una notificació vol adjuntar-ne el PDF, o un operador revisa una incidència de custòdia. **Actors:** procés de publicació/consulta i receptor només després del control UC-80. **Resultat:** disponibilitat **verificada** per a la versió/document correctes, o estat `PENDING/ERROR` amb incidència i sense entregar cap path/bytes. Aquest és un control de custòdia, **no substitueix** l'autorització per actor de UC-80.

```plantuml
@startuml
left to right direction
actor "Procés de notificació / panell" as P
actor "Responsable documental" as R
rectangle "SIF PrisMa — comprovació documental [DISSENY]" {
 usecase "UC-55 / VERIFICAR\nComprovar disponibilitat de l'artefacte" as Verify
 usecase "Verificar path privat i SHA-256 dels bytes" as Hash
 usecase "Comprovar UUID_FACTURA, tipus\ni versió/document autoritzat" as Metadata
 usecase "UC-80\nAutoritzar accés de l'actor" as Access
}
P --> Verify
R --> Verify
Verify ..> Hash : <<include>>
Verify ..> Metadata : <<include>>
P --> Access
@enduml
```

### Vista de casos d’ús per a GitHub (Mermaid)

```mermaid
flowchart LR
  actor_0["Procés de notificació / panell"]
  actor_1["Responsable documental"]
  subgraph SIF_BOX["SIF PrisMa — comprovació documental [DISSENY]"]
    uc_0(["UC-55 / VERIFICAR<br/>Comprovar disponibilitat de l'artefacte"])
    uc_1(["Verificar path privat i SHA-256 dels bytes"])
    uc_2(["Comprovar UUID_FACTURA, tipus<br/>i versió/document autoritzat"])
    uc_3(["UC-80<br/>Autoritzar accés de l'actor"])
  end
  actor_0 --> uc_0
  actor_1 --> uc_0
  uc_0 -.->|include| uc_1
  uc_0 -.->|include| uc_2
  actor_0 --> uc_3
```

```mermaid
sequenceDiagram
autonumber
actor P as Panell/notificació
participant V as DocumentAvailabilityService [DISSENY]
participant DB as factura_documents + document_job [SQL]
participant Store as Storage privat [DISSENY]
participant A as UC-80 autorització d'accés [DISSENY]
P->>V: Comprovar documentId i UUID_FACTURA per publicar
V->>DB: Llegir metadata, font fiscal, tipus, versió/job i OUTPUT_HASH
alt Sense document/job o metadata només declarada
 DB-->>V: PENDING/UNKNOWN
 V-->>P: No mostrar enllaç/adjunt com a disponible
else Referència existent
 V->>Store: Llegir bytes del path privat i SHA-256 real
 alt Fitxer absent, corrupte o versió incorrecta
  Store-->>V: NOT_FOUND/HASH_MISMATCH
  V-->>P: Bloquejar publicació, obrir incidència i reparar UC-55
 else Bytes i versió coherents
  Store-->>V: VERIFIED
  V-->>P: Artefacte disponible (sense bytes ni URL pública)
  opt El receptor sol·licita descàrrega
   P->>A: authorize(actor,documentId,READ) per UC-80
   A-->>P: Servei segur o DENIED, disponibilitat no concedeix permís
  end
 end
end
Note over V,Store: DocumentRepository només desa metadata i hash dels bytes rebuts, el verificador d'storage és DISSENY.
```

| Prova pendent | Escenari | Resultat exigible |
| --- | --- | --- |
| DC-55-01 | Dos encolats mateixa factura/tipus/versió i petició equivalent | Un UUID_JOB; cap doble representació atribuïda al reintent. |
| DC-55-02 | Job escrit en storage, metadades inserides, crash abans de marcar COMPLETED | Reprendre amb mateix document i hash; cap segon `factura_documents`. |
| DC-55-03 | Dos workers recuperen el mateix job parcial en paral·lel | Una única finalització i referència, amb incidència si divergeix el contingut. |
| DC-55-04 | Metadata `CREATED` però fitxer no existeix | `PENDING/ERROR` verificable, sense accés al document, encara que el PDF sigui urgent. |
| DC-55-05 | Fitxer físic present amb bytes diferents del `HASH_FITXER` | Denegar publicació/descàrrega, conservar evidència i obrir incidència; no substituir silenciosament. |
| DC-55-06 | Factura prèvia amb document correcte però receptor empresa no autoritzat al canal | Document íntegre **sense entrega** fins que UC-80 autoritzi l'actor. |

## 6. Traçabilitat

[UC-55 original](../06-fitxes-funcionals/uc-055.md) · [UC-36 generació puntual](uc-036-generar-consultar-documents.md) · [UC-07 accés](uc-007-consultar-factura-estat-document.md) · [UC-08 incidències](uc-008-gestionar-incidencia-sif.md) · [DocumentRepository](../../sif/src/Repository/DocumentRepository.php) · [Migració document_job/access](../../sif/database/migrations/2026_09_15_000003_add_functional_audit_control.sql) · [DocumentsAndIncidentsTest](../../sif/tests/Integration/DocumentsAndIncidentsTest.php).
