# UC-34 · Consultar el dashboard del SIF

**Àmbit:** obtenir una vista de **lectura i navegació** del SIF a `pay.prisma.cat/sif`: activitat de factura, cues Redsys i AEAT, incidències, cobrament i documents. **No** és el worker, la correcció fiscal, l'emissió de factura ni l'activació d'una versió; aquests són casos d'ús diferenciats.

**Estat verificat:** [disseny detallat del panell](../04-estat-final/25-panell-sif-pay-prisma.md); `FiscalQueueMetricsRepository::snapshot()` ofereix **mètriques agregades només de la cua fiscal**, i `sif/scripts/preflight-aeat-worker.php` calcula l'estat de preparació i algunes alertes CLI. No s'ha acreditat una classe/endpoint executable de dashboard que agregui totes les mètriques del panell, ni el control d'accés final de la ruta.

## 1. Fitxa específica

| Element | Contracte |
| --- | --- |
| Actors | Responsable tècnica i operadors segons rol. L'auditor només pot consultar dades del seu abast; suport no pot activar un retry o generar una factura per veure un indicador. |
| Entrada | Sessió/rol validats al servidor i interval temporal explícit per a comptadors de factures i pagaments. El rang «avui/mes/any» ha d'usar zona horària acordada i deixar clar el període de les mètriques. |
| Panell objectiu | Factures emeses avui/mes/any; registres AEAT pendents, acceptats/rebutjats/retry; incidències obertes; callbacks Redsys duplicats i pendents; pagaments no assignats; cua documental; última factura; estat de connexió/preflight AEAT; versió activa. |
| Mètrica fiscal **implementada** | `FiscalQueueMetricsRepository::snapshot(db,staleLockSeconds)` retorna `counts` (`PENDING`, `PROCESSING`, `RETRY`, `SENT`, `DEAD_LETTER`), `due`, `stale_locks` i `oldest_actionable_at`. **No** ofereix recompte d'acceptacions/rebuigs de registre, ni factures, ni pagaments. |
| Preflight implementat | `preflight-aeat-worker.php` combina `AeatPreflight` i mètriques per mostrar `ready_to_send`, alertes `DEAD_LETTER_THRESHOLD`, `DUE_QUEUE_THRESHOLD`, `STALE_WORKER_LOCK`. És un script **CLI**, no un endpoint públic de dashboard. |
| Sortida | Comptadors amb origen, instant d'actualització i rang, avisos de dades no disponibles, i enllaços a UC-07/08/35/52/54/55 segons permís. |
| Efecte | **Cap efecte de mutació** per obrir el panell o consultar una targeta; qualsevol acció operativa exigeix confirmació i permisos del cas d'ús de destí. |

### 1.1. Flux objectiu

1. L'usuari entra al panell SIF amb autenticació i autorització **al backend**; la intranet només ofereix enllaç/resum, no substitueix la font fiscal del SIF.
2. El servei de dashboard **pendent** consulta separat factures, registres/respostes AEAT, cues, pagaments i assignacions, incidències, documents i versió activa. No dedueix el número de factures cobrades del número de notificacions TPV.
3. Per la cua fiscal usa `FiscalQueueMetricsRepository::snapshot()` com a font d'agregats, però consulta `factura_registres.ESTAT_AEAT` per saber què està acceptat/rebutjat. **`fiscal_queue.STATUS=SENT` no vol dir acceptat.**
4. Per Redsys, separa notificació denegada/validada de feina `QUEUED`/`RETRY`/`PROCESSED`/`INCIDENT` i de `payment_transaction CHARGE` real. Un callback validat sense job processat no és factura cobrada.
5. Per documents, diferencia feina `document_job` de fitxer registrat a `factura_documents` i integritat efectiva de storage: tenir metadades no prova que el PDF sigui descarregable.
6. Mostra dades filtrades per rol/abast i enllaços a les pantalles detallades; errors de consulta es marquen com **mètrica no disponible**, no es converteixen en un zero aparentment sa.
7. Quan una targeta mostra incidència, navega a UC-08 o a la vista corresponent. **El dashboard mateix no executa** `issueInvoice`, `registerPayment`, `recoverStaleLocks` o `createSubsanation`.

### 1.2. Casos de prova i riscos

| Cas | Comportament |
| --- | --- |
| 3 jobs `SENT`, però 1 `REJECTED` | La targeta d'enviats i la de rebutjats es calculen amb fonts diferents; no presentar 3 registres acceptats. |
| Callback `VALIDATED` i job `RETRY` | No incrementar factures cobrades sense `UUID_PAYMENT`/moviment real. |
| Pagament extern únic sobre N inscripcions | Comptar una transacció bancària real; el detall de N atribucions s'ha d'obtenir del registre per inscripció proposat quan existeixi, sense multiplicar ingressos. |
| Job documental existent però fitxer físic absent | Indicador de document pendent o amb incidència, no «disponible». |
| Error de BD en una secció | Mostrar dada no disponible amb instant de la darrera lectura fiable; no substituir per 0. |
| Perfil auditor o suport | Cap botó accionable fora de permís i cap fuga de noms/dades de grup des del resum. |
| «AEAT connectada» segons preflight local | Mostrar comprovació local i data; no afirmar recepció/acceptació AEAT per un test de configuració. |

**Proves localitzades, no executades:** `FiscalQueueMetricsRepositoryTest` i script de preflight; no equivalen a proves de la pantalla agregadora ni del filtratge de dades per rol.

## 2. Diagrama UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Responsable tècnica" as T
actor "Operador autoritzat" as O
rectangle "SIF · Dashboard" {
 usecase "UC-34\nConsultar dashboard" as Main
 usecase "Validar rol i abast" as Auth
 usecase "Consultar comptadors\namb origen i data" as Metrics
 usecase "UC-08\nConsultar incidències" as Inc
 usecase "UC-35\nConsultar registres AEAT" as Fiscal
 usecase "UC-52\nOperar cua Redsys" as Redsys
 usecase "UC-54\nOperar cua fiscal" as Queue
}
T --> Main
O --> Main
Main ..> Auth : <<include>>
Main ..> Metrics : <<include>>
T --> Inc
T --> Fiscal
T --> Redsys
T --> Queue
note bottom of Main
 Lectura. Les accions de destí
 tenen una autorització pròpia.
end note
@enduml
```

## 3. Diagrama de classes — nucli parcial i agregador objectiu

```mermaid
classDiagram
direction LR
class FiscalQueueMetricsRepository {
 <<PHP existent>>
 +snapshot(db,staleLockSeconds) array
}
class AeatPreflight {
 <<PHP existent>>
 +check(config) array
}
class SifDashboardService {
 <<DISSENY: no acreditat>>
 +overview(actor,period) result
}
class SifDashboardReadRepository {
 <<DISSENY: no acreditat>>
 +invoices(period) totals
 +redsys(period) totals
 +payments(period) totals
 +documents(period) totals
 +incidents(period) totals
}
class DashboardVisibilityPolicy {
 <<DISSENY: no acreditada>>
 +canRead(actor,metric) bool
}
SifDashboardService --> SifDashboardReadRepository : agregats
SifDashboardService --> FiscalQueueMetricsRepository : cua fiscal
SifDashboardService --> DashboardVisibilityPolicy : permisos
```

`AeatPreflight` s'executa en el script CLI existent; no es dibuixa com a dependència implementada d'un `SifDashboardService` que encara és disseny.

## 4. Seqüència — visualització sense operacions (DISSENY/PARCIAL)

```mermaid
sequenceDiagram
autonumber
actor O as Operador autoritzat
participant UI as pay.prisma.cat/sif [pendent]
participant S as SifDashboardService [DISSENY]
participant Auth as DashboardVisibilityPolicy [DISSENY]
participant R as SifDashboardReadRepository [DISSENY]
participant Q as FiscalQueueMetricsRepository [PHP existent]
participant DB as BD SIF
O->>UI: Consultar dashboard i període
UI->>S: overview(actor,period)
S->>Auth: Validar rol i abast
alt Accés no autoritzat
 Auth-->>S: Denegat
 S-->>UI: Error sense dades
else Accés autoritzat
 Auth-->>S: Permès
 S->>R: Factures, Redsys, pagaments, documents i incidències
 R->>DB: SELECT agregats per període
 R-->>S: Comptadors per estat i font
 S->>Q: snapshot(db,900)
 Q->>DB: SELECT fiscal_queue estats, due, stale locks
 Q-->>S: Mètriques de cua fiscal
 S-->>UI: Comptadors amb data, rang i estat d'obtenció
 O->>UI: Obrir detall d'incidència o cua
 Note over UI,S: Navegació a cas específic amb autorització pròpia; no mutació en UC-34
end
```

## 5. Traçabilitat

[UC-34 original](../06-fitxes-funcionals/uc-034.md) · [Disseny del panell](../04-estat-final/25-panell-sif-pay-prisma.md) · [UC-08 incidències](uc-008-gestionar-incidencia-sif.md) · [UC-35 consulta fiscal](uc-035-consultar-registre-cadena-estat-aeat.md) · [FiscalQueueMetricsRepository](../../sif/src/Repository/FiscalQueueMetricsRepository.php) · [Script de preflight](../../sif/scripts/preflight-aeat-worker.php) · [UC-55 documents](uc-055-custodiar-reintentar-documents.md) · [Model de fons](00-revisio-moviments-inscripcions.md).
