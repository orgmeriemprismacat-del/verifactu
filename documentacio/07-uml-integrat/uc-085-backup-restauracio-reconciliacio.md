# UC-85 · Executar backup, restauració i reconciliació abans de reprendre operacions

**Objectiu original:** evidència de backup i restauració, integritat, RPO/RTO i incidències. **Estat [DISSENY/BLOQUEJANT].** UC-40 descriu el procés de còpia/restauració; UC-85 exigeix demostrar la **recuperació íntegra i la reconciliació dels efectes externs ocorreguts després del tall**, abans d'autoritzar workers, callbacks o API d'emissió en un sistema restaurat.

## 1. Evidència del repositori

`backup_restore_evidence` està definida a SQL amb `OPERATION_TYPE`, `ENVIRONMENT`, `SCOPE_JSON`, `BACKUP_REFERENCE/HASH`, `RPO_MINUTES`, `RTO_MINUTES`, `INTEGRITY_RESULT`, `EXECUTED_BY`, `CORRELATION_ID` i `EVIDENCE_JSON`. **No s'ha acreditat** un writer PHP ni una restauració real verificada. `MigrationRunner::inspect()` compara hashes del ledger `sif_schema_migration`, taules i columnes declarades, però **no comprova per si sol els hashes de `factura_registres`, la seqüència fiscal, les transaccions bancàries, els fitxers privats ni el resultat remot AEAT**.

`FiscalQueueProcessor::recoverStaleLocks()` pot reactivar jobs que han quedat en `PROCESSING`. Això **no acredita** que l'AEAT no hagués rebut el registre abans de la pèrdua de resposta. `RedsysCallbackWorker` pot recuperar notificacions, però una còpia feta abans d'un cobrament real ha de reconciliar-se amb el banc/Redsys **abans** de reprendre els processadors restaurats.

## 2. Contracte d'execució específic

| Fase | Evidència necessària |
| --- | --- |
| Tall i inventari | Registre de l'instant exacte T i versions del codi/BD, posició de la cadena, seqüències fiscals, jobs AEAT/Redsys pendents o `PROCESSING`, fons per factura/inscripció, fitxers de documents i evidències privades. **El ledger individual encara és proposta:** assenyalar expressament aquesta limitació. |
| Backup | Còpia coherent i protegida de BD SIF, dades llegades relacionades i storage privat, manifest per component/hash, política de claus/retenció i prova que el backup es pot llegir; no incloure certificats ni secrets productius en un dump accessible. |
| Restauració aïllada | Reconstituir components sense contactar **banc/AEAT productius** ni deixar workers programats actius, inspeccionar migracions i validar número/sèrie, hash chain, taules d'assignació, cua, documents i permisos d'usuari. |
| Delta extern | Cercar factures, cobraments Redsys/transferències, devolucions, respostes AEAT i canvis llegats **posteriors a T** i durant la recuperació. La BD restaurada no és autoritat per negar transaccions bancàries o registres remots que no contenia a T. |
| Reconciliació | Comparar fonts per UUID, `DS_ORDER`, `UUID_PAYMENT`, `UUID_FACTURA`, `fiscal_order` i `ID_INSC`, registrar item i decisió UC-82. No reproduir callbacks ni `ALTA/ANULACIO/SUBSANACIO` fins comprovar idempotència i resultat remot; una factura històrica és immutable. |
| Gate de retorn | Aprovar RPO/RTO **mesurats**, integritat i llista de divergències resoltes/pendents, així com reobertura ordenada de workers/API; decisió `GO/NO_GO` de UC-39 amb actor, motiu i evidència. |
| Incidències | Si falta una prova o resta un pagament/registre remot incert, bloquejar **l'acció afectada**, obrir UC-81, preservar la còpia, registrar estat parcial i no afirmar que el sistema ja està completament recuperat. |

### Flux objectiu

1. Preparar exercici de recuperació o incident amb abast, prioritat, entorn i punt de tall T, assignar responsables i congelar el pla dels components afectats. Registrar inici i objectius de RPO/RTO, sense declarar-los aconseguits.
2. Restaurar còpia en entorn segregat, **amb treballadors i connexions externes productives inhabilitats**, i verificar bytes de documents/evidències, integritat del dump, migracions i files fiscals/registres/cues.
3. Consultar fonts independents dels efectes posteriors a T: acceptació/rebuig AEAT, cobraments i devolucions reals bancaris, callbacks i canvis al llegat. Generar `reconciliation_run/item` objectius (SQL definit, serveis de conciliació **pendents**) per diferències, imports i titulars.
4. Per cada diferència, autoritzar una reparació **idempotent i específica**: recuperar evidència de callback existent, associar cobrament únic a factura, preservar registre AEAT remot, reconstruir fitxer absent sense editar contingut fiscal, o resoldre canvi acadèmic. Si no se sap si el banc/AEAT ha completat una operació, no fer un retry cec.
5. Calcular RPO/RTO observats, resultat d'integritat i incidents pendents; guardar-los en `backup_restore_evidence` mitjançant servei pendent amb referència i hash de la còpia.
6. Executar gate UC-39/83 abans d'obrir progressivament cues, web i TPV. La reobertura no ha de reemetre factures per compensar el període de parada ni donar per pagada una inscripció per un text del llegat.

### Proves crítiques

| Fallada injectada | Criteri verificable |
| --- | --- |
| Backup T, pagament Redsys real a T+1 abans de caiguda | No cobrar de nou ni perdre el primer ingrés; verificar `DS_ORDER`/bank, recuperar `UUID_PAYMENT` o incidència, evitar factura duplicada. |
| SOAP AEAT acceptat i resposta perduda abans del backup | Consultar evidència/estat remot; no crear nova factura ni segon registre fiscal com a «restauració». |
| Dump restaurat sense fitxer PDF que constava `CREATED` | `INTEGRITY_RESULT` incomplet i reconstitució documental verificable; no mostrar fitxer com a present. |
| `fiscal_chain_state` o numeració divergeixen dels registres històrics | Bloquejar nova emissió fins a revisió de la cadena i seqüència; no fer `UPDATE` improvisat de hashes fiscals. |
| Canvi de curs només en BD llegada després de T | Conciliar estat acadèmic i fons per inscrit, sense reescriure factura fiscal restaurada. |
| Recuperació llesta tècnicament però no hi ha aprovació d'activació | Mantenir `NO_GO` per l'abast sense prova/aprovador, tot preservant evidència de la restauració. |

**Pendents:** pla de còpia coherent entre sistemes, script provat de restauració, exercicis d'injecció de fallades, taula/writer de conciliació executable, verificació amb banc/AEAT i política signada de reobertura. Cap backup ni restauració real s'ha executat en aquesta revisió.

## 3. UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Administrador infraestructura" as A
actor "Responsable tècnica" as T
actor "Responsable fiscal" as F
rectangle "SIF · recuperació segura" {
 usecase "UC-85\nRestaurar i reconciliar abans de reobrir" as Main
 usecase "Verificar backup i cadena fiscal" as Integrity
 usecase "Recuperar delta bancari i AEAT posterior al tall" as Delta
 usecase "UC-82\nResoldre divergències per item" as Reconcile
 usecase "UC-39\nAprovar go/no-go de recuperació" as Gate
}
A --> Main
T --> Main
F --> Gate
Main ..> Integrity : <<include>>
Main ..> Delta : <<include>>
Main ..> Reconcile : <<include>>
Main ..> Gate : <<include>>
@enduml
```

## 4. UML de classes — evidència SQL vs recuperació completa pendent

```mermaid
classDiagram
class SifDisasterRecoveryService {
 <<DISSENY: no acreditat>>
 +restoreIsolated(manifest) result
 +reconcileAfterCutoff(cutoff,scope) differences
 +authorizeResume(report,actor) decision
}
class BackupRestoreEvidenceRepository {
 <<DISSENY: backup_restore_evidence SQL>>
 +append(db,evidence) result
}
class MigrationRunner {
 <<PHP existent: estructura i hashes migració>>
 +inspect(db) array
}
class ExternalEffectsReconciliationGateway {
 <<DISSENY: banc, AEAT i llegat>>
 +listAfter(cutoff,scope) events
}
class SifLegacyReconciliationService {
 <<DISSENY: UC-82>>
 +compare(scope) differences
}
SifDisasterRecoveryService --> BackupRestoreEvidenceRepository : prova i RPO/RTO
SifDisasterRecoveryService --> MigrationRunner : esquema
SifDisasterRecoveryService --> ExternalEffectsReconciliationGateway : delta extern
SifDisasterRecoveryService --> SifLegacyReconciliationService : divergències
```

## 5. UML de seqüència — recuperació amb cobrament posterior al backup

```mermaid
sequenceDiagram
autonumber
actor A as Administrador
actor T as Responsable tècnica
participant R as SifDisasterRecoveryService [DISSENY]
participant E as backup_restore_evidence [SQL]
participant B as Entorn restaurat sense workers
participant X as Banc, AEAT i BD llegada [reconciliació pendent]
participant I as Reconciliation UC-82 [DISSENY]
participant G as Gate UC-39 [DISSENY]
A->>R: Restaurar backup T en entorn aïllat
R->>B: Recuperar BD/fitxers, verificar schema/cadena/cua
B-->>R: Resultat d'integritat
R->>X: Cercar cobraments, retorns i registres posteriors a T
X-->>R: Ingrés Redsys a T+1 i possible resposta AEAT a T+2
R->>I: Crear diferències per UUID_PAYMENT, DS_ORDER i fiscal_order
I-->>R: Resoltes o pendents amb evidència
R->>E: Registrar hashes, INTEGRITY_RESULT, RPO/RTO observats
alt Efecte extern incert o diferència sense resoldre
 R-->>T: NO_GO de les accions afectades; sense reexecutar CHARGE/ALTA
else Recuperació validada
 T->>G: Revisar evidència i decidir reobertura de serveis
 G-->>T: Decisió per versió/entorn
end
Note over R,X: Ni migració correcta ni backup creat impliquen reconciliació del que va passar després de T.
```

## 6. Traçabilitat

[UC-85 original](../06-fitxes-funcionals/uc-085.md) · [UC-40 còpia/restauració](uc-040-backup-restauracio.md) · [UC-82 conciliació](uc-082-reconciliar-sif-bd-llegada.md) · [UC-39 go/no-go](uc-039-proves-gate-go-no-go.md) · [UC-83 versió](uc-083-registrar-activar-versio-declaracio.md) · [MigrationRunner](../../sif/src/Database/MigrationRunner.php) · [Esquema backup_restore_evidence](../../sif/database/migrations/2026_09_15_000003_add_functional_audit_control.sql) · [Moviments per inscrit](00-revisio-moviments-inscripcions.md).
