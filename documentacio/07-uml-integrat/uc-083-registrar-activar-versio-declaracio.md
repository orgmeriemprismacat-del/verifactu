# UC-83 · Registrar i activar versió del SIF i declaració responsable

**Objectiu del catàleg:** relacionar de manera inequívoca versió, artefacte, configuració, migracions, proves, declaració i activació. **Estat [DISSENY/BLOQUEJANT].** UC-46 governa la decisió de posar en servei una candidata; UC-83 defineix la **traça de cada versió i declaració** i com es comprova que allò desplegat és exactament allò aprovat.

## 1. Evidència i model versionat

La migració d'auditoria defineix `sif_version` amb `UUID_VERSION`, `VERSION_CODE` únic, `GIT_REVISION`, `ARTIFACT_HASH`, `CONFIG_HASH`, `DATABASE_VERSION`, `STATUS`, `CREATED_BY` i `ACTIVATED_AT`. `sif_declaration` té `UUID_VERSION` com a FK, `DECLARATION_VERSION`, `DOCUMENT_HASH`, `STORAGE_KEY`, `APPROVED_BY/AT` i `STATUS`. **No s'ha acreditat un writer/activador PHP per aquestes taules**, i la FK no demostra que el document existeixi o que la declaració correspongui a la versió efectivament servida.

`MigrationRunner::inspect()` comprova el ledger `sif_schema_migration`, hashes de migracions i presència de taules/columnes. `go-no-go-preproduction.php` funciona en `test/preproduction` i comprova prerequisits i circuits presents; **cap dels dos certifica el funcionament complet ni la coincidència entre codi actiu i declaració**. `SoapTransport` revisat només admet `TEST_ENDPOINT`: un gate de preproducció no es pot reutilitzar com a prova d'una tramesa productiva.

## 2. Fitxa funcional específica

| Objecte | Regla |
| --- | --- |
| Candidata | El commit Git, artefacte distribuïble, hash de configuració **sense secrets**, versió BD, emissor/entorn i manifest de components formen una identitat indivisible. Un nou commit o paràmetre material requereix una nova candidata/revisió. |
| Proves | Relacionar report UC-39 amb la **mateixa identitat exacta** de la candidata i entorn; la presència de scripts o `ready=true` local no acredita la prova de restauració ni el contracte AEAT. |
| Declaració | Lligar document físic, versió, hash de **bytes**, aprovador, data i abast verificat a `UUID_VERSION`. Les condicions i el contingut formal s'han de validar en el circuit corresponent; una fila `STATUS=ACTIVE` no converteix una declaració en conformitat acreditada. |
| Activació | Registrar estat de versió anterior i candidata, evidència de configuració/codi **realment en servei**, instants i actor; la migració no mostra una restricció global que imposi exactament una sola versió `ACTIVE` i no proporciona un protocol de desplegament entre BD i FTP/runtime. |
| Auditoria | Guardar decisions i intents d'activació a `sif_audit_event` quan el writer existeixi, amb hashes anterior/nou, actor, resultat i correlació. No enviar secrets/configuració en clar a `CHANGESET_JSON`. |
| Cap efecte monetari | Activar o registrar versió no reemet factures, no altera `fiscal_chain_state` ni crea `CHARGE/REFUND`. Si el rollback coincideix amb cobrament/callback real, s'ha de conciliar l'operació, no restaurar l'estat econòmic per intuïció. |

### Flux objectiu i criteris de tancament

1. Registrar candidata `DRAFT` amb `VERSION_CODE`, commit i hashes del paquet/configuració/BD i referència d'entorn; verificar que els fitxers i migracions esperats són accessibles i que el hash del desplegable correspon al commit/artefacte anunciat.
2. Associar evidències de runner, preflight, integració de canals, retorn AEAT de l'**entorn objectiu**, backup/restore i observabilitat a aquella mateixa versió; UC-39 defineix el gate i els bloquejants funcionals.
3. Custodiar la declaració **aprovada realment** i comprovar `DOCUMENT_HASH` contra el document físic abans de vincular-la; sense proves o autorització adequada, conservar la versió en estat no activat.
4. Coordinar UC-46 per activar una única versió operacional i verificar per separat `sif_version.STATUS`, bytes de codi servits, hash de configuració, versió real de BD i workers en curs. **El SQL no fa aquest desplegament de manera automàtica.**
5. Si falla una destinació, registrar desplegament parcial, versions observades i incidència. Decidir rollback sense perdre factures/pagaments registrats entre el canvi i l'error.
6. Provar: mateix `VERSION_CODE` amb hash diferent, declaració signada per artefacte antic, dues activacions concurrents, PHP actual i BD anterior, migració parcial, secrets a evidències, worker antic amb cua nova i `go-no-go-preproduction.php` correcte però transport productiu indisponible.

**Pendents:** model de report de proves per versió, writer de `sif_version` i `sif_declaration`, exclusivitat `ACTIVE`, verificació del runtime desplegat, procediment d'aprovació formal i proves d'activació/rollback. No s'ha activat cap versió ni executat gate en aquesta revisió.

### 2.1. Fitxa de verificació del desplegament per procés i declaració associada

**Prova de preproducció amb abast limitat.** `sif/scripts/go-no-go-preproduction.php` només accepta `SIF_ENV=test/preproduction` a `environment_not_production` i retorna expressament `production_authorized=false`. `preflight-aeat-worker.php` informa de prerequisits locals i alertes de cua. **Cap dels dos compara en una petició productiva el hash dels bytes PHP servits amb `sif_version.ARTIFACT_HASH` o `sif_declaration.DOCUMENT_HASH`**. Un resultat favorable en un informe de proves no pot esdevenir una activació per copiar `STATUS=ACTIVE` a SQL; cal comprovar l'artefacte, l'entorn, el transport real i l'abast que la declaració documenta.

**No hi ha una sola «versió del servidor» si els processos discrepen.** La ruta de compra/redirecció, `sif/public/api/redsys/callback.php`, el worker de notificacions Redsys, el worker fiscal i la generació de PDF poden usar **processos i moments de desplegament diferents**. La fitxa objectiu registra per component **Git revision, hash executable, configuració sense secrets, migració SQL observada, emissor configurat, instant i entorn**; correlaciona aquests valors amb `UUID_VERSION` i la declaració aprovada. No atribuir a tot `pay.prisma.cat` la versió que només ha acreditat el procés HTTP de consulta.

**Moment de tall i callbacks en vol.** Quan s'activa una candidata mentre una `DS_ORDER` antiga té callbacks en cua i hi ha jobs AEAT `PROCESSING`, conservar l'origen, snapshot i UUIDs de cadascun; decidir si poden acabar-se amb el procés anterior o amb un successor compatible. Un canvi de versió **no** crea una altra intenció, factura, registre ni moviment bancari; si la nova versió modifica l'esquema o la signatura, comprovar compatibilitat amb dades en cua abans de reactivar el worker. Si el procés antic no es pot retirar de seguida, marcar explícitament estat de desplegament **mixt**, no `ACTIVE` homogeni.

**Declaració i evidència real.** `sif_declaration.DOCUMENT_HASH` i `STORAGE_KEY` són metadades de SQL, **no** prova que el PDF/document aprovat existeixi físicament ni que el hash coincideixi amb els bytes. Verificar fitxer protegit, aprovador, abast, versió i entorn de la candidata **abans** de donar-li caràcter de declaració associada. Si manca un document o la declaració correspon a un artefacte antic, registrar incidència i impedir l'afirmació de conformitat; no regenerar una declaració a partir d'una plantilla i signar-la automàticament per omplir la fila.

### 2.2. Proves de versió efectiva i tall (no executades)

| ID | Escenari | Resultat exigible |
| --- | --- | --- |
| VT-83-01 | Preflight preproducció favorable i `production_authorized=false` | No declarar versió productiva autoritzada per aquest resultat. |
| VT-83-02 | HTTP serveix nova versió mentre el worker AEAT continua a l'antiga | Evidència per procés i estat mixt, sense donar per homogeni `ACTIVE`. |
| VT-83-03 | Document de declaració SQL present però bytes de `STORAGE_KEY` absents | Evidència incompleta i bloqueig de l'afirmació d'associació/verificació. |
| VT-83-04 | `DS_ORDER` en cua durant canvi incompatible de worker | Conservar intent/cobrament i classificar compatibilitat abans de reprendre; cap segona venda. |
| VT-83-05 | Activació BD confirmada però falta una migració al runtime | Estat parcial i gate de desplegament no superat, sense reemetre per reparar. |
| VT-83-06 | Restaurar codi anterior després de cobrar en versió nova | Conciliació dels fets posteriors al tall, no restauració cega de dades fiscals. |

## 3. UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Responsable tècnica" as T
actor "Responsable fiscal" as F
actor "Operador desplegament" as O
rectangle "SIF · versió i declaració" {
 usecase "UC-83\nRegistrar i activar versió" as Main
 usecase "Congelar Git, artefacte, configuració i BD" as Freeze
 usecase "Relacionar proves i declaració aprovades" as Evidence
 usecase "UC-46\nActivar versió i verificar runtime" as Activation
 usecase "Registrar resultat i possible rollback" as Audit
}
T --> Main
F --> Evidence
O --> Activation
Main ..> Freeze : <<include>>
Main ..> Evidence : <<include>>
Main ..> Activation : <<include>> (gate favorable)
Main ..> Audit : <<include>>
@enduml
```

## 4. UML de classes — esquema definit, activador pendent

```mermaid
classDiagram
class SifReleaseRegistryService {
 <<DISSENY: no acreditat>>
 +registerCandidate(manifest) uuidVersion
 +attachDeclaration(uuidVersion,document,approval) result
 +recordActivation(uuidVersion,runtimeEvidence) result
}
class SifVersionRepository {
 <<DISSENY: sif_version SQL definit>>
 +insertCandidate(db,manifest) version
 +transitionWithLock(db,uuidVersion,status) result
}
class SifDeclarationRepository {
 <<DISSENY: sif_declaration SQL definit>>
 +registerApproved(db,uuidVersion,hash,key) declaration
}
class MigrationRunner {
 <<PHP existent>>
 +inspect(db) array
}
class ReleaseQualificationService {
 <<DISSENY: gate formal UC-39>>
 +decide(report,actor) decision
}
class RuntimeDeploymentVerifier {
 <<DISSENY: no acreditat>>
 +verifyHashes(artifact,config,dbVersion) result
}
SifReleaseRegistryService --> SifVersionRepository : candidata i activació
SifReleaseRegistryService --> SifDeclarationRepository : document aprovat
SifReleaseRegistryService --> ReleaseQualificationService : gate
SifReleaseRegistryService --> RuntimeDeploymentVerifier : codi actiu
SifReleaseRegistryService --> MigrationRunner : migracions
```

## 5. UML de seqüència — declaració no correspon a codi actiu (DISSENY)

```mermaid
sequenceDiagram
actor T as Responsable tècnica
actor F as Aprovador fiscal
participant S as SifReleaseRegistryService [DISSENY]
participant V as sif_version [SQL]
participant D as sif_declaration [SQL]
participant G as UC-39/46 Gate i activació [DISSENY]
participant R as RuntimeDeploymentVerifier [DISSENY]
T->>S: Registrar candidata C amb Git/artefacte/config/BD
S->>V: Inserir versió DRAFT i hashes
S->>G: Consultar proves i gate per C i entorn
alt Evidència insuficient
 G-->>S: NO_GO
 S-->>T: No activar ni reutilitzar declaració anterior
else Gate favorable i autorització efectiva
 F->>S: Aprovar document concret per C
 S->>D: Registrar hash, aprovador i UUID_VERSION de C
 S->>G: Orquestrar desplegament exclusiu
 S->>R: Verificar codi/config/BD reals
 alt Hash actiu no coincideix amb C
  R-->>S: Diferència
  S->>V: Registrar estat d'incidència [writer pendent]
  S-->>T: Activació no verificada, sense conformitat presumpta
 else Runtime coherent
  R-->>S: Concordança d'identitat
  S->>V: Registrar versió activa / retirar anterior
  S-->>T: Activació corroborada, preservant història
 end
end
Note over S,R: Les taules SQL no despleguen codi ni validen conformitat per si soles.
```

## 6. Traçabilitat

[UC-83 original](../06-fitxes-funcionals/uc-083.md) · [UC-46 activació](uc-046-activar-versio-declaracio-responsable.md) · [UC-39 gate](uc-039-proves-gate-go-no-go.md) · [UC-38 certificat](uc-038-configurar-sif-certificat.md) · [UC-40 backup](uc-040-backup-restauracio.md) · [MigrationRunner](../../sif/src/Database/MigrationRunner.php) · [Esquema de versions i declaracions](../../sif/database/migrations/2026_09_15_000003_add_functional_audit_control.sql).
