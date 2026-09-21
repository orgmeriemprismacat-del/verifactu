# UC-68 · Retirar callbacks i escriptures fiscals llegades sense duplicar ingressos

**Objectiu original:** cada punt de processament actiu ha de tenir substitut, prova i evidència abans de retirar-lo; no poden quedar **dues vies que facturen o registren el mateix pagament**. **Estat [DISSENY].** El SIF ja disposa de callback i cua Redsys propis, però el repositori consultat **no acredita quins callbacks/escriptures de la web llegada són realment actius a producció** ni que s'hagin desactivat.

## Evidència de la via nova i limitacions

`sif/public/api/redsys/callback.php` verifica signatura amb `RedsysSignatureValidator` i passa dades a `RedsysCallbackService::receiveCallback()`; el servei relaciona `DS_ORDER` amb la intenció, comprova import/divisa/terminal, registra notificació i encola el job només davant resposta validada. `RedsysCallbackWorker::runOne()` reclama un job, el delega a `RedsysCallbackDispatcher` i marca processat/retry/incidència segons el resultat. Aquests components **no demostren per si sols que el callback llegat no segueixi executant escriptures**.

`LegacySyncService` és **una via de resum posterior al resultat SIF** que actualitza `FACTURA_RELACIONADA` i afegeix text a `OBSERVACIONS`; no s'ha d'utilitzar com a segon emissor fiscal. El sync actual amb `COALESCE` i `CONCAT` pot conservar una referència llegada contradictòria o duplicar una nota en retry. La retirada de callbacks **no implica retirar tota lectura o sincronització acadèmica llegada**.

## Contracte de retirada per punt d'entrada

| Pas | Invariant |
| --- | --- |
| Inventari | Localitzar **URLs de notificació Redsys configurades al comerç**, PHP web/intranet, crons, scripts de botiga i mètodes que emeten número/PDF, escriuen a les taules de facturació o marquen `PAGAMENT`. No suposar que tots són al directori `sif/`. |
| Matriu substitutiva | Per cada callback antic: origen, tipus d'operació, `IDPAG/DS_ORDER`, efecte fiscal/econòmic/acadèmic, substitut SIF concret, proves i data de tall. Definir si roman un adaptador de **lectura o resum no fiscal**. |
| Exclusivitat | Redirigir el flux perquè hi hagi un únic responsable del `CHARGE` i de l'emissió fiscal. Duplicat de la mateixa notificació Redsys ha de reusar `UUID_JOB/UUID_PAYMENT`, no executar emissor vell i nou. |
| Trànsit en vol | Acceptar/conciliar notificacions antigues o tardanes segons prova bancària, fins i tot després de canviar URL; no ignorar diners reals perquè l'edició o ruta antiga s'ha tancat. |
| Rollback | Si falla el SIF, no tornar a activar un escriptor fiscal llegat sense aïllar els efectes ja produïts. Qualsevol recuperació exigeix UC-82 i un pla de no duplicació. |
| Evidència final | Hash/codi de l'antiga ruta, configuració de comerç, proves negatives d'escriptura antiga, trànsit de callback nou, monitoratge i rollback aprovat. Sense accés al runtime real l'estat resta **pendent de verificació**. |

### Flux objectiu

1. Inventariar el **codi real en servei** (UC-64), la configuració Redsys i els canals manual, web i telèfon; per cada punt antic identificar qui modificava `inscripcions.PAGAMENT`, emetia factura/número o registrava un càrrec.
2. Construir una matriu de correspondència per canal i provar equivalent del nou SIF: signatura/intenció, cua, factura única, ingrés únic, pagaments parcials, packs/grups, callbacks duplicats, retorns i sync acadèmic posterior.
3. Preparar tall controlat de callback i flags/escriptures llegades amb exclusivitat del processador; registrar instant i correlació per als jobs previs/en vol. **No** executar automàticament codi de migració que marqui els cobraments antics com a nous `CHARGE`.
4. Reproduir en prova callback antic/tardà i mateix `DS_ORDER` repetit: la nova cua ha de reusar o reconciliar sense una factura ni pagament duplicats. El banc és la referència del moviment extern, el SIF la del registre fiscal.
5. Desactivar l'escriptura fiscal llegada i verificar des del **runtime**, DBs i configuració de Redsys que no es produeix doble efecte. Conservar només lectures/sync de resum autoritzades i idempotents.
6. Si una via antiga segueix escrivint o arriba un ingrés que no es pot atribuir, obrir incidència i bloquejar la duplicació; corregir posteriorment el llegat per comanda auditada, no reemetre documents originals.

**Proves:** dos callbacks a URLs diferents pel mateix cobrament, callback signat tardà després de canviar URL, intenció absent, transacció d'empresa/grup amb diversos inscrits, worker antic del cron encara actiu, sync llegat repetit i rollback amb pagaments posteriors.

## UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Responsable tècnica" as T
actor "Administrador TPV" as P
rectangle "Web llegada → SIF" {
 usecase "UC-68\nRetirar doble processament llegat" as Main
 usecase "Inventariar URLs i escriptors actius" as Inventory
 usecase "Provar callback/cua SIF de substitució" as Test
 usecase "Desactivar vies fiscals duplicades" as Disable
 usecase "Conciliar notificacions en vol" as Reconcile
}
T --> Main
P --> Disable
Main ..> Inventory : <<include>>
Main ..> Test : <<include>>
Main ..> Disable : <<include>>
Main ..> Reconcile : <<include>>
@enduml
```

## UML de classes

```mermaid
classDiagram
class LegacyFiscalCutoverService {
 <<DISSENY: no acreditat>>
 +inventory(environment) routes
 +approveCutover(matrix) plan
 +verifyExclusiveProcessor(plan) result
}
class RedsysCallbackService {
 <<PHP existent>>
 +receiveCallback(db,payload,signatureValid) array
}
class RedsysCallbackWorker {
 <<PHP existent>>
 +runOne(db,workerId,now) array
}
class LegacySyncService {
 <<PHP existent: resum posterior>>
 +syncAfterSifSuccess(legacyDb,relations,uuidFactura,numVisible,estatCobrament) void
}
class SifLegacyReconciliationService {
 <<DISSENY: UC-82>>
 +compare(scope) differences
}
LegacyFiscalCutoverService --> RedsysCallbackService : substitut de recepció
LegacyFiscalCutoverService --> RedsysCallbackWorker : prova de processament
LegacyFiscalCutoverService ..> LegacySyncService : només resum llegat
LegacyFiscalCutoverService --> SifLegacyReconciliationService : incidents en tall
```

## UML de seqüència — callback duplicat durant canvi d'URL (DISSENY/PARCIAL)

```mermaid
sequenceDiagram
actor T as Responsable tècnica
participant C as LegacyFiscalCutoverService [DISSENY]
participant Old as Callback llegat [runtime per verificar]
participant New as RedsysCallbackService [PHP]
participant W as RedsysCallbackWorker [PHP]
participant R as Reconciliation UC-82 [DISSENY]
T->>C: Aprovar tall després d'inventari/proves
C->>Old: Desactivar efectes fiscals/pagament [pendent]
C->>New: Activar ruta SIF per DS_ORDER i signatura
alt Arriba notificació tardana també a ruta antiga
 Old-->>C: Detectar doble arribada/effecte antic
 C->>R: Comprovar banc, UUID_PAYMENT i factura ja creada
else Callback arriba a ruta nova
 New->>New: Validar intenció/import/terminal i encolar o reutilitzar
 New->>W: Processar job únic amb idempotència
 W-->>C: UUID_FACTURA/UUID_PAYMENT o incidència
end
C-->>T: Evidència runtime de via única o tall encara pendent
Note over Old,New: No s'ha verificat desactivació de cap callback del web productiu.
```

## Traçabilitat

[UC-68 original](../06-fitxes-funcionals/uc-068.md) · [UC-64 candidata](uc-064-reconciliar-candidata-codi-actual.md) · [UC-82 conciliació](uc-082-reconciliar-sif-bd-llegada.md) · [UC-77 AEAT](uc-077-operar-enviament-aeat-retry-dead-letter.md) · [Callback SIF](../../sif/public/api/redsys/callback.php) · [RedsysCallbackService](../../sif/src/Service/RedsysCallbackService.php) · [RedsysCallbackWorker](../../sif/src/Service/RedsysCallbackWorker.php) · [LegacySyncService](../../sif/src/Service/LegacySyncService.php).
