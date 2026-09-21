# UC-40 · Fer backup i restauració sense perdre traça fiscal

**Objectiu original:** procediment definit; execució i evidència pendents. **Estat [DISSENY].** La migració defineix `backup_restore_evidence` però no s'ha acreditat un backup/restore runner PHP complet del SIF ni una restauració verificada de les dues BDs i dels fitxers privats. Una còpia creada **no és** una restauració comprovada; UC-85 desenvolupa el procés transversal i els criteris RPO/RTO.

## 1. Fitxa específica

| Unitat | Contracte |
| --- | --- |
| Actor | Administrador autoritzat de BD/infraestructura i responsable tècnica; un perfil auditor de només lectura no pot executar restauracions. |
| Abast | `factura`, línies, `factura_registres`, `fiscal_sequence`, `fiscal_chain_state`, `fiscal_queue`, pagaments, assignacions, notificacions, incidents i taules de governança, més repositoris de bytes fiscals/evidència i dependències de BD llegada. No suposar que un dump MySQL conté els fitxers de PDF, P12 o evidència SOAP. |
| Còpia | Instant i punt de tall coherents, versió migracions, sistema origen/destí, xifrat i custòdia restringida, hash verificat, retenció, `BACKUP_REFERENCE` i `BACKUP_HASH`. **No** copiar certificats/claus privades en clar a un artefacte compartible. |
| Restauració | Entorn aïllat amb credencials pròpies, comprovació d'integritat, seqüència/cadena/hash fiscals, correlació de cua/registre, idempotència de Redsys, fitxers i permisos, i comparació amb fotografia d'origen. **No** restaurar un dump antic sobre producció viva sense pla de reconciliació de moviments posteriors. |
| Evidència SQL | `backup_restore_evidence`: `UUID_EVIDENCE`, `OPERATION_TYPE`, `ENVIRONMENT`, `SCOPE_JSON`, `BACKUP_REFERENCE/HASH`, `STATUS`, `RPO_MINUTES`, `RTO_MINUTES`, `INTEGRITY_RESULT`, executor, correlació i `EVIDENCE_JSON`. **No acredita que cap prova de restauració s'hagi executat.** |
| Efecte fiscal/econòmic | Backup/restore no emet factures ni genera `CHARGE/REFUND`. Davant un desfasament amb AEAT/banc o llegat, **reconciliar abans de reprendre cues/cobraments**, sense inventar o duplicar un registre d'emissió. |

### Flux objectiu

1. Definir finestra, objectius de recuperació i instant de tall: registrar versió del codi/BD, estat de workers i consistència entre BD fiscal, llegada i storage d'evidències.
2. Fer còpia consistent i protegida, conservar manifest per component i hash, i verificar que bytes i fitxers privats figuren al pla segons retenció. Registrar resultat a `backup_restore_evidence` mitjançant writer **pendent**.
3. Restaurar **en entorn separat**, executar migracions/inspecció amb versions corresponents i verificar documents/UUID/numeració, cadena de registres i que cada fila de cua correspon al seu `fiscal_order`. Comprovar pagaments i assignacions per factura.
4. Comparar punt de tall i operacions posteriors: callbacks Redsys, cobrament extern, enviament AEAT que podria haver tingut resposta remota, factura creada després del backup i edicions al llegat. Un snapshot antic no prova que un cobrament actual sigui inexistent.
5. Reconciliar UC-82/85 amb banc i AEAT **abans de permetre a un worker restaurat retransmetre o registrar operacions en vol**; qualsevol incertesa obre incidència UC-81.
6. Mesurar RPO/RTO **observats**, no només objectius declarats; conservar prova d'integritat i decisió de go/no-go UC-39, sense presentar `STATUS=SUCCESS` amb hashes incomplets.

**Proves pendents:** backup amb documents absents, corrupció hash, restaurar sense `fiscal_chain_state`, còpia amb job `PROCESSING`, timeout AEAT immediatament abans del backup, callback Redsys posterior al punt de tall, desalineació SIF/llegat, dades personals a un entorn de test i recuperació sense certificat productiu.

## 2. UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Administrador infraestructura" as A
actor "Responsable tècnica SIF" as T
rectangle "SIF · backup i recuperació" {
 usecase "UC-40\nFer backup i prova de restauració" as Main
 usecase "Congelar punt de tall i abast" as Cutoff
 usecase "Crear còpia/manifest/hash" as Backup
 usecase "Restaurar i verificar integritat" as Restore
 usecase "UC-82/85\nReconciliar operacions posteriors" as Reconcile
}
A --> Main
T --> Main
Main ..> Cutoff : <<include>>
Main ..> Backup : <<include>>
Main ..> Restore : <<include>>
Reconcile ..> Main : <<extend>> (reincorporació)
@enduml
```

## 3. UML de classes — model SQL i execució pendent

```mermaid
classDiagram
class BackupRestoreService {
 <<DISSENY: runner no acreditat>>
 +backup(scope,cutoff) manifest
 +restoreToIsolated(manifest) result
 +verify(manifest,restored) evidence
}
class BackupRestoreEvidenceRepository {
 <<DISSENY: taula SQL definida>>
 +append(db,evidence) result
}
class SifDataSnapshotGateway {
 <<DISSENY: BD fiscal/llegada i storage>>
 +createConsistentCopy(scope) manifest
 +verifyChainsAndPayments(manifest) result
}
class SifLegacyReconciliationService {
 <<DISSENY: UC-82 no acreditat>>
 +compare(scope) differences
}
BackupRestoreService --> SifDataSnapshotGateway : còpia i integritat
BackupRestoreService --> BackupRestoreEvidenceRepository : resultat per execució
BackupRestoreService ..> SifLegacyReconciliationService : abans de reincorporar producció
```

## 4. UML de seqüència — restauració anterior a un pagament real

```mermaid
sequenceDiagram
actor A as Administrador
participant S as BackupRestoreService [DISSENY]
participant B as BackupRestoreEvidenceRepository [SQL definit]
participant C as Còpia SIF + storage privat [pendent]
participant R as Entorn aïllat restaurat
participant P as Banc/AEAT i BD llegada [conciliació pendent]
A->>S: Crear backup amb instant de tall T i abast
S->>C: createConsistentCopy(scope,T)
C-->>S: Manifest, hash i versions
S->>B: Registrar backup i evidència
A->>S: Provar restauració sense tocar producció
S->>R: Restaurar BD/fitxers i inspeccionar cadena/cua
R-->>S: Estat de hash, documents, seqüència i pagaments
S->>P: Comparar operacions posteriors a T i respostes remotes
alt Pagament real posterior a T o AEAT incerta
 P-->>S: Desfasament que exigeix decisió/conciliació
 S->>B: Registrar INTEGRITY_RESULT i incidència
 S-->>A: No reactivar workers restaurats encara
else Estat verificat i reconciliat
 P-->>S: Fonts concordants en abast
 S->>B: Registrar RPO/RTO observats i resultat
 S-->>A: Evidència de recuperació per gate UC-39
end
Note over S,P: Cap script de restauració SIF complet acreditat en aquesta revisió.
```

## 5. Traçabilitat

[UC-40 original](../06-fitxes-funcionals/uc-040.md) · [UC-85 continuïtat original](../06-fitxes-funcionals/uc-085.md) · [UC-39 go/no-go](uc-039-proves-gate-go-no-go.md) · [UC-82 conciliació](uc-082-reconciliar-sif-bd-llegada.md) · [UC-77 cua AEAT](uc-077-operar-enviament-aeat-retry-dead-letter.md) · [Esquema backup_restore_evidence](../../sif/database/migrations/2026_09_15_000003_add_functional_audit_control.sql).
