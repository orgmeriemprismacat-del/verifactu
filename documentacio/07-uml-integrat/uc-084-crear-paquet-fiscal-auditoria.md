# UC-84 · Crear un paquet fiscal d'auditoria amb integritat i traça d'accés

**Objectiu original:** conservar filtres, motiu, sol·licitant, fitxer, hash i descàrregues. **Estat [DISSENY].** UC-37 exporta un període fiscal; UC-84 defineix el **paquet auditable**, el seu inventari, les dependències de cada registre i la custòdia/accés per un auditor amb abast autoritzat. Un paquet no és una factura nova ni una prova automàtica de compliment.

## 1. Fonts del SIF i abast mínim

La migració defineix `fiscal_export` (`UUID_EXPORT`, `EXPORT_TYPE`, `FILTERS_JSON`, rol, sol·licitant, `REASON_CODE`, `STORAGE_KEY`, `FILE_HASH`, estat i correlació) i `fiscal_export_access` (acció, resultat, actor, rol, `REQUEST_ID` i instants). **No s'ha acreditat** un generador de paquet PHP, un inventari criptogràfic per fitxer o un servidor de descàrrega autoritzada que executi aquestes taules.

Les fonts documentals són `factura`, `factura_linia`, `factura_registres`, `factura_rectificacio`, `fiscal_chain_state`, `fiscal_queue`, `aeat_submission_attempt` quan **realment estigui emplenada**, `factura_documents` més **fitxers existents i verificats**, i events/operacions originals. El `EvidenceStore` de transport conserva fitxers privats en el seu circuit, però no equival a una fila d'intent SQL ni a una factura PDF validada. El registre d'auditoria no ha d'incloure secrets, credencials, dades de targeta ni justificants sensibles de descompte fora de l'abast.

## 2. Fitxa funcional específica

| Element | Contracte |
| --- | --- |
| Petició | Auditor/operador identificat, finalitat i motiu, emissor, període, sèries, filtres, tipus de registres i nivell de dades personals aprovat. El grant UC-45/59 ha de ser efectiu abans de preparar i **cada vegada que es descarrega** el paquet. |
| Manifest | Identificador únic del paquet, data/hora de tall, versió d'esquema/exportador, filtres exactes, consulta/criteri temporal, llista ordenada de registres/fitxers, hash **per artefacte** i hash global del paquet. **Aquest manifest detallat és disseny pendent:** `fiscal_export.FILE_HASH` només verifica el fitxer principal. |
| Contingut | Factures i rectificatives, registres `ALTA/ANULACIO/SUBSANACIO` rellevants, hash/cadena i estats/respostes AEAT, documents fiscals **físicament disponibles**, incidències que n'expliquen els buits. No representar `SENT` com a acceptat, ni fitxer absent com a PDF disponible. |
| Preparació | Snapshot llegit amb data de tall coherent. Si un enviament entra a cua o canvia d'estat després, conservar l'instant de tall i la referència; no alterar registres originals ni reemetre'ls per «fer quadrar» el paquet. |
| Custòdia | Storage privat, xifrat/restricció segons política, hash verificat dels bytes reals i retenció aprovada. `STORAGE_KEY` no és URL pública; les descàrregues han de tenir token/rol restringit i traça també en denegacions. |
| Efecte fiscal i diners | Generar el paquet no crea `factura`, `factura_registres`, `CHARGE`, `REFUND` ni assignacions. Si hi ha un pagament/informe econòmic inclòs, és evidència de l'operació existent, no un moviment nou. |

### Flux objectiu

1. Aprovar petició d'auditoria per finalitat, període, emissor i abast de persones; registrar `UUID_EXPORT` i filtres immutable amb actor/motiu. Un `REQUEST_ID` repetit amb paràmetres contradictoris ha de rebutjar-se; el SQL **no té clau idempotent pròpia** per a `fiscal_export`.
2. Fer consulta temporal coherent de factures/registres i **resoldre les dependències**: rectificativa→original, registre de subsanació/anul·lació→registre previ, cua→`fiscal_order`, document→`UUID_FACTURA`, resposta AEAT→job/registre.
3. Preparar manifest amb «present/verificat/pendent/no acreditat» per cada artefacte. Un `factura_documents.CREATED` sense bytes verificables i un `aeat_submission_attempt` sense writer real **no** són evidència completa; reportar les mancances com a tals.
4. Serialitzar el paquet de format aprovat, verificar cardinalitats i hashes, desar bytes privats, i completar `fiscal_export.FILE_HASH/STORAGE_KEY`. No incloure claus privades, tokens o secrets de serveis.
5. Quan l'auditor el consulta o descarrega, UC-59 valida permís/vigència i `fiscal_export_access` registra resultat i correlació; ni un hash correcte ni el coneixement d'`UUID_EXPORT` concedeixen accés.
6. En cas d'error o reconstrucció, emetre **una versió nova del paquet** i mantenir manifest/data de tall de l'anterior. Repetir la mateixa exportació per timeout no ha de generar diversos paquets contradictoris atribuïts a la mateixa petició.

**Proves pendents:** factura rectificada durant l'exportació, document absent, intent AEAT sense resposta, paquet d'un emissor vs factura d'un altre, grant revocat abans de descarregar, fitxer modificat, exportació concurrent i filtració de dades personals en manifest/log.

### Paquet d'auditoria mixt: registres del SIF i inventari històric no VERI*FACTU

**Dues procedències dins d'un únic paquet, si s'aprova.** La migració històrica d'UC-11 conserva `ESTAT_AEAT=NO_VERIFACTU` i no crea `factura_registres` ni `fiscal_queue`. Si una petició d'auditoria requereix tant registres emesos pel SIF com documents previs del llegat, el manifest ha de dividir-los en conjunts **«registre SIF amb cadena/AEAT»** i **«document històric de consulta, sense nova alta AEAT»**. Aquesta divisió és una especificació del paquet, **no** una afirmació que hi hagi un generador PHP mixt implementat o que la factura històrica disposi d'un QR de registre nou.

**Identitat original i informe de migració.** Per cada històric, incloure referència a la font (`web.factures`/ID quan s'hagi acreditat), emissor jurídic verificat, `NUM_VISIBLE`, sèrie, data d'emissió antiga, receptor, `FACTURA_RELACIONADA`, estat del document i incidència documental o d'import si n'hi ha. Si Associació i SL tenen numeracions coincidents, el número no és una clau global; si no es pot acreditar emissor/origen, mantenir la manca com a incidència i **no atribuir** el document a l'emissor actiu. Adjuntar el resum de control UC-11 per any/sèrie, primer/últim número, totals i files no importades quan l'abast sigui la migració.

**Evidència del que no es pot acreditar.** Un `factura_documents.HASH_FITXER` importat no confirma que el PDF físic sigui l'original ni que estigui custodiat. El manifest ha d'enumerar **per separat** els artefactes amb bytes i hash revisats, els documents només referenciats, els PDFs reconstruïts des del llegat i els no localitzats. Un rebut bancari històric o `ESTAT_COBRAMENT=PAID` importat no crea una `payment_transaction` actual. Per a AEAT, un job `SENT` sense resposta individual `ACCEPTED` s'etiqueta com a enviament, no com a registre acceptat.

**Reproducció i accés.** `fiscal_export`/`fiscal_export_access` són taules de governança encara sense generador/writer complet acreditat. El contracte objectiu desa filtres, data de tall, versió del manifest, cardinalitat per conjunt, hash dels fitxers **realment llegits** i resultat de cada accés autoritzat o denegat. Un auditor amb accés a registres fiscals no obté automàticament justificants sensibles ni el PDF complet d'una empresa per ser participant d'un grup; comprovar rol i abast a cada descàrrega (UC-45/59/80).

### Proves addicionals de paquet mixt (no executades)

| ID | Escenari | Resultat exigible |
| --- | --- | --- |
| PA-84-01 | Paquet amb factura de 2024 històrica i factura SIF recent | Separació d'origen, estat fiscal i existència de registre/AEAT. |
| PA-84-02 | Arxiu PDF històric no localitzat però metadada importada | Manifest indica document absent/no verificat, no hash de bytes suposats. |
| PA-84-03 | Mateix número visible a Associació i SL | Emissor i ID de font distingits o conflicte registrat, sense fusió. |
| PA-84-04 | Migració incompleta respecte del recompte `web.factures` | Diferència i incidències en el manifest, no declarar paquet complet. |
| PA-84-05 | Paquet fiscal consulta una resposta AEAT pendent per un registre SIF | Estat remot no acreditat i resposta absent, sense acceptació fictícia. |
| PA-84-06 | Persona sense permís de document d'empresa vol exportar-lo pel paquet | Denegació al servidor i event d'accés, sense eludir UC-80. |

## 3. UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Auditor amb abast limitat" as A
actor "Responsable fiscal" as F
rectangle "SIF · paquet auditor" {
 usecase "UC-84\nCrear paquet fiscal d'auditoria" as Main
 usecase "UC-59\nVerificar accés temporal" as Auth
 usecase "Inventariar registres i dependències" as Inventory
 usecase "Verificar fitxers i construir manifest" as Manifest
 usecase "Custodiar paquet i registrar descàrrega" as Access
}
A --> Main
F --> Main
Main ..> Auth : <<include>>
Main ..> Inventory : <<include>>
Main ..> Manifest : <<include>>
Main ..> Access : <<include>>
@enduml
```

### Vista de casos d’ús per a GitHub (Mermaid)

```mermaid
flowchart LR
  actor_0["Auditor amb abast limitat"]
  actor_1["Responsable fiscal"]
  subgraph SIF_BOX["SIF · paquet auditor"]
    uc_0(["UC-84<br/>Crear paquet fiscal d'auditoria"])
    uc_1(["UC-59<br/>Verificar accés temporal"])
    uc_2(["Inventariar registres i dependències"])
    uc_3(["Verificar fitxers i construir manifest"])
    uc_4(["Custodiar paquet i registrar descàrrega"])
  end
  actor_0 --> uc_0
  actor_1 --> uc_0
  uc_0 -.->|include| uc_1
  uc_0 -.->|include| uc_2
  uc_0 -.->|include| uc_3
  uc_0 -.->|include| uc_4
```

## 4. UML de classes — exportació SQL vs paquet complet pendent

```mermaid
classDiagram
class FiscalAuditPackageService {
 <<DISSENY: no acreditat>>
 +request(scope,actor,reason) uuidExport
 +generate(uuidExport,cutoff) manifest
 +download(uuidExport,actor) bytes
}
class FiscalExportRepository {
 <<DISSENY: fiscal_export SQL definit>>
 +create(db,filters,actor) export
 +complete(db,uuid,hash,storageKey) result
}
class FiscalExportAccessRepository {
 <<DISSENY: fiscal_export_access SQL definit>>
 +append(db,event) result
}
class FiscalEvidenceInventory {
 <<DISSENY: dependències i hash per artefacte>>
 +collect(db,filters,cutoff) entries
 +verify(entry) status
}
class ProtectedAuditPackageStorage {
 <<DISSENY: custòdia privada no acreditada>>
 +writeAndVerify(packageBytes) storageKey
}
class TemporaryAuditorAccessService {
 <<DISSENY: UC-59 pendent>>
 +authorize(grant,resource) decision
}
FiscalAuditPackageService --> FiscalEvidenceInventory : manifest
FiscalAuditPackageService --> FiscalExportRepository : petició i resultat
FiscalAuditPackageService --> ProtectedAuditPackageStorage : bytes
FiscalAuditPackageService --> FiscalExportAccessRepository : consulta/denegació
FiscalAuditPackageService --> TemporaryAuditorAccessService : permís
```

## 5. UML de seqüència — manifest i descàrrega denegada (DISSENY)

```mermaid
sequenceDiagram
actor F as Responsable fiscal
actor A as Auditor
participant S as FiscalAuditPackageService [DISSENY]
participant G as Accés temporal UC-59 [DISSENY]
participant R as fiscal_export [SQL]
participant I as FiscalEvidenceInventory [DISSENY]
participant P as ProtectedAuditPackageStorage [DISSENY]
participant L as fiscal_export_access [SQL]
F->>S: Demanar paquet amb emissor, període, motiu i abast
S->>G: Comprovar autorització del sol·licitant
S->>R: Registrar UUID_EXPORT, filtres i correlació
S->>I: Inventariar documents, registres, hash i cues al tall T
I-->>S: Manifest amb elements i buits explicitats
S->>P: Escriure paquet i verificar hash real
P-->>S: STORAGE_KEY, FILE_HASH
S->>R: Completar metadades
A->>S: Sol·licitar descàrrega de UUID_EXPORT
S->>G: Revalidar grant, emissor i vigència
alt Grant caducat o sense abast
 G-->>S: DENIED
 S->>L: Registrar accés denegat
 S-->>A: Sense dades fiscals
else Autoritzat
 G-->>S: ALLOWED
 S->>P: Llegir i verificar bytes
 S->>L: Registrar descàrrega i resultat
 S-->>A: Paquet immutable del tall T
end
Note over S,P: Manifest detallat, storage i enforcement no acreditats al PHP actual.
```

## 6. Traçabilitat

[UC-84 original](../06-fitxes-funcionals/uc-084.md) · [UC-37 exportar període](uc-037-exportar-periode-fiscal.md) · [UC-45 auditor](uc-045-activar-auditor-temporal.md) · [UC-80 accés documental](uc-080-servir-registrar-acces-document-fiscal.md) · [UC-77 tramesa AEAT](uc-077-operar-enviament-aeat-retry-dead-letter.md) · [Esquema fiscal_export i accessos](../../sif/database/migrations/2026_09_15_000003_add_functional_audit_control.sql).
