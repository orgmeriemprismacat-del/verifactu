# UC-127 · Canviar l'estat d'una edició i resoldre totes les operacions afectades

**Objectiu canònic:** l'activació, ajornament, tancament o cancel·lació d'una edició ha de produir un **event massiu identificable** i un inventari de reserves, inscripcions, factures i pagaments afectats. Cada inscrit/operació rep **una decisió individual**: mantenir, traslladar, cancel·lar, retornar, crear saldo, rectificar o no actuar. **Bloquejant:** la política per estat i situació de facturació/cobrament l'han de definir negoci, cobraments i assessoria fiscal; no hi ha una única operació «cancel·lar edició = retornar tots els cobraments».

## 1. Evidència revisada

`master_data_change_request` té `ENTITY_TYPE`, `ENTITY_KEY`, `BASE_VERSION`, `PROPOSED_VERSION`, `CHANGESET_JSON`, `AFFECTED_OPEN_OPERATIONS_JSON`, actor, motiu, decisió i correlació. `commercial_operation` i `commercial_operation_line` emmagatzemen producte/edició i snapshots comercials; `capacity_reservation` relaciona places amb l'operació. `academic_economic_state_event` permet una traça **per inscripció** d'estats acadèmics, d'accés i fotografia econòmica. **Les taules estan definides a SQL; no s'ha acreditat un `EditionLifecycleService` PHP que inventariï i resolgui N operacions.**

`LegacyCourseSnapshotRepository` consulta les dades vives del curs per `ANY`, `MES` i `CURS` quan carrega dades llegades per `IDPAG`. La factura amb snapshot congelat **no s'ha de regenerar a partir de l'edició mutable** després d'un ajornament. `RedsysPaymentIntentService` rebutja reutilitzar un mateix `DS_ORDER` amb snapshot/import/venciment diferents, però `RedsysCallbackService` no comprova l'estat actual de l'edició o `EXPIRES_AT` de l'oferta.

## 2. Fitxa específica i invariant de diners

| Unitat | Contracte |
| --- | --- |
| Actors | Gestió acadèmica, responsable de cobraments i, per canvis fiscals, validador autoritzat; alumne/pagador rep proposta o notificació segons la decisió individual. |
| Capçalera massiva | `EDITION_KEY` (any, mes, curs o recurs inequívoc), estat anterior/nou, versions, causa, data efectiva, actor, `REQUEST_ID`, correlació, conjunt d'operacions trobades i nombre d'incidències. **No s'ha acreditat taula específica de lot per edició** amb item/resultat idempotent. |
| Registre per afectat | `UUID_OPERATION`, `ID_INSC`, línia de pack/grup, titular, pagador, estat de plaça, inscripció, accés, factura/es, pagaments **reals**, import individual atribuït, decisió i resultat d'execució. |
| Abans del pagament | Revocar o renovar reserva/enllaç segons UC-115/121; sense ingrés real, **cap `REFUND`**. Si es proposa altra edició/preu, cal nova acceptació quan la política ho exigeixi. |
| Després de cobrar | Conservar `UUID_PAYMENT` original i decidir per **tram atribuït** a cada inscrit o component: trasllat intern UC-105, saldo UC-29, retorn **quan sigui efectiu** UC-28 o cap canvi, amb titular verificat. **No** tornar el total de la factura d'empresa indiscriminadament a cada alumne. |
| Després de facturar | La factura/registre original continuen immutables. UC-74/05 classifica si cal document corrector per servei/import/receptor realment modificats; canviar només l'estat de l'edició **no insereix una rectificativa automàtica**. |
| Estat acadèmic | UC-124/129 gestiona plaça, baixa/trasllat, Moodle i certificat independentment de la resolució fiscal. Cada resultat queda correlacionat i recuperable en cas de fallada parcial. |

### Flux objectiu

1. Gestió proposa `ACTIVE→POSTPONED/CLOSED/CANCELLED` o transició real acordada. El servei **pendent** consulta versions, publica previsualització amb nombre i IDs d'operacions afectades, incloses intencions TPV pendents i callbacks encara en cua, reserves, pagaments, factures i components de packs/grups.
2. Classifica el lot en **fitxes individuals de decisió**: sense cobrament i sense factura, factura abans de cobrar, pagament parcial, ingrés complet individual, pack, grup amb empresa pagadora, plaça ja traslladada, factura correctora existent o incidència.
3. Congela l'abast i aprova el canvi amb actor/regla/versionat. Cal definir un model append-only per a cada item i la seva clau idempotent; `AFFECTED_OPEN_OPERATIONS_JSON` **no prova que cada membre hagi estat resolt**.
4. Publica estat/versió d'edició i obre ordres individuals UC-71/72/115/121 segons decisió. Si canvia la data d'una prestació ja venuda, preservar el snapshot anterior i documentar acceptació de nova oferta quan correspon.
5. Per cada inscrit afectat amb diners reals, reconcilia import origen i saldo disponible **per `ID_INSC`**, titular i destí. Un moviment intern B→C no és un segon `CHARGE`; retorn només després de sortida real i amb idempotència.
6. Per cada document emès, classifica efecte fiscal i registra la correcció apropiada **només si correspon al cas individual**; no alterar de forma massiva `factura_linia` o `ESTAT_AEAT`.
7. Confirma resultats acadèmics/Moodle en un procés separat. El lot queda amb comptador d'items resolts/pendents/error, i els callbacks antics s'encaminen a conciliació, no es descarten perquè el curs està cancel·lat.

### Proves específiques

| Escenari | Resultat requerit |
| --- | --- |
| Cancel·lació d'edició amb 20 inscripcions: 10 pagades, 5 facturades sense cobrar, 5 gratuïtes | 20 decisions **individuals**; 10 imports reals analitzats, cap retorn automàtic de les altres, tractament fiscal per document existent. |
| Pack de tres cursos amb un component cancel·lat | Afectar únicament component/inscripció pertinent i reconsiderar descompte pack segons contracte, no retornar tres cops el mateix `UUID_PAYMENT`. |
| Grup amb factura i pagador empresa | Preservar identitat de qui va pagar i imports atribuïts per participant; no prometre ingrés al compte d'una altra persona. |
| Callback Redsys de l'edició antiga després de publicar cancel·lació | Reconciliar ingrés bancari real i estat de plaça; no ocultar `CHARGE` ni confirmar servei inexistent. |
| Reintent d'un item resolt després d'una fallada de Moodle | No duplicar factura correctora/retorn; repetir **només** el pas acadèmic no confirmat. |

**Pendents de tancament:** política d'estats per edició, actor que aprova el lot i els casos individuals, model/worker d'items idempotents, snapshot del deute individual, execució dels canvis acadèmics i fiscalitat específica per variant.

### 2.1. Operació llegada d'estat d'edició: un únic clic, N decisions individuals

**Origen concret de l'event massiu.** `Intranet::desarCanvisEstatEnviarMsg_PreviIniciCursos()` canvia una edició entre pendent, activa i anul·lada, pot donar de baixa alumnes, consulta factures i formes de pagament, cerca edicions futures i envia avisos. El mètode llegat reuneix actuacions que afecten **un conjunt d'inscripcions**, però el `master_data_change_request.AFFECTED_OPEN_OPERATIONS_JSON` previst al SIF és només **una capçalera amb una llista**, no una prova que s'hagin processat tots els inscrits ni que s'hagin comprovat els ingressos externs. L'orquestració objectiu necessita un resultat individual per `ID_INSC/UUID_OPERATION`, amb fase acadèmica, econòmica, fiscal i comunicació diferenciades.

**Inventari que evita perdre casos.** En preparar l'anul·lació o ajornament, incloure no només persones que `INSC_CURS` marca com a actives, sinó també reserva sense pagament, intenció `DS_ORDER` encara pendent, callback validat en cua, factura d'empresa emesa abans de cobrar, inscripció canviada de curs, participant d'un pack/grup amb altres línies vigents, pagament parcial i expedient ja rectificat. En cap cas l'estat d'edició actual de la web determina, per ell mateix, si s'ha cobrat realment o si la factura antiga és fiscalment improcedent.

**Matriu d'efectes per afectat.** (a) Reserva sense ingrés ni factura: decidir alliberament/alternativa UC-115/121, sense `REFUND`. (b) Factura real emesa abans de cobrar: preservar document i resoldre obligació/rectificació **segons el cas**, no transformar-la en proforma. (c) Ingrés confirmat de l'alumne o empresa: conservar `UUID_PAYMENT`, decidir canvi UC-71/105, saldo UC-29 o devolució **quan s'executi realment** UC-28. (d) Grup/pack: quantificar per participant/component només amb atribucions acreditades; no retornar a cada alumne el total ingressat per l'empresa. (e) Matrícula/accés Moodle: UC-124/129 aplica i verifica la decisió acadèmica, independentment que l'adaptador fiscal hagi acabat.

**Reintents i notificacions.** Un fallada després de canviar l'edició però abans d'avisar la tercera persona no justifica repetir baixa, emissió, `CHARGE` o reemborsament de les dues anteriors. Recuperar el mateix event massiu i **només els items/fases pendents**; revalidar la plantilla UC-43/58 perquè el missatge no afirmi «devolució efectuada» o «baixa a Moodle confirmada» fins a disposar del resultat real. Un callback d'una ordre anterior al canvi és evidència de banc a conciliar, no un registre que pugui descartar-se per estar l'edició anul·lada.

### 2.2. Proves addicionals de lot amb estats mixtos (no executades)

| ID | Escenari | Resultat exigible |
| --- | --- | --- |
| ED-127-01 | Anul·lar edició amb factura prèvia pendent i cap ingrés | Classificació individual d'obligació/document; cap devolució fictícia. |
| ED-127-02 | Grup d'empresa pagat, només un participant canvia d'edició | Fons i document analitzats per part afectada; cap retorn indiscriminat a participants. |
| ED-127-03 | Callback `DS_ORDER` antiga arriba després d'anul·lar | Preservar cobrament extern i obrir conciliació de la plaça/servei. |
| ED-127-04 | Fallada d'avís al tercer de cinc inscrits després de canvis confirmats | Reprendre missatge pendent i verificar fases per inscrit, no tornar a executar els efectes confirmats. |
| ED-127-05 | Operació mostra edició cancel·lada però accés Moodle encara actiu | Mostrar estat parcial i UC-129 fins a verificació acadèmica. |
| ED-127-06 | Pack amb dues línies i una sola edició cancel·lada | Resolució per línia/inscripció, sense crear una factura o un CHARGE nous per l'altra. |

## 3. UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Gestió acadèmica" as G
actor "Cobraments" as C
actor "Responsable fiscal" as F
rectangle "SIF · canvi massiu d'edició" {
 usecase "UC-127\nCanviar estat edició" as Main
 usecase "Inventariar operacions i imports individuals" as Inventory
 usecase "Decidir trasllat/baixa per afectat" as Decide
 usecase "UC-105/28/29\nResoldre fons per inscrit" as Funds
 usecase "UC-74\nClassificar document fiscal" as Fiscal
 usecase "UC-124/129\nConciliar accés i matrícula" as Acad
}
G --> Main
C --> Funds
F --> Fiscal
Main ..> Inventory : <<include>>
Main ..> Decide : <<include>>
Funds ..> Main : <<extend>> (inscrit amb fons)
Fiscal ..> Main : <<extend>> (document afectat)
Acad ..> Main : <<extend>> (accés afectat)
@enduml
```

## 4. UML de classes — capçalera SQL i coordinació d'items pendent

```mermaid
classDiagram
class EditionLifecycleService {
 <<DISSENY: no acreditat>>
 +preview(editionKey,newState) impacted
 +applyApprovedBatch(changeRequest) batch
 +retryItem(batchId,idInsc) result
}
class MasterDataChangeRepository {
 <<DISSENY: SQL definit, UC-114>>
 +append(db,request) result
}
class EditionChangeItemRepository {
 <<DISSENY: taula d'items no acreditada>>
 +appendDecision(db,item) result
 +markStep(db,itemId,step) result
}
class AcademicEconomicStateEventRepository {
 <<DISSENY: SQL definit>>
 +append(db,event) result
}
class LegacyCourseSnapshotRepository {
 <<PHP existent: llegeix curs vigent per IDPAG>>
 +loadByIdpag(legacyDb,idpag,currentPaymentAmount) array
}
EditionLifecycleService --> MasterDataChangeRepository : versió d'edició
EditionLifecycleService --> EditionChangeItemRepository : decisions per persona
EditionLifecycleService --> AcademicEconomicStateEventRepository : efecte acadèmic separat
```

## 5. UML de seqüència — cancel·lació de pack/grup i cobrament tardà (OBJECTIU)

```mermaid
sequenceDiagram
autonumber
actor G as Gestió
participant S as EditionLifecycleService [DISSENY]
participant B as EditionChangeItemRepository [DISSENY]
participant P as Factures/cobraments reals SIF
participant F as Classificador UC-74 [pendent]
participant A as Prisma/Moodle UC-124/129 [pendent]
G->>S: Cancel·lar edició E amb motiu i versió
S->>P: Inventariar reserves, factures, pagaments, callbacks en cua
S->>B: Guardar una decisió pendent per UUID_OPERATION/ID_INSC
S-->>G: Previsualització d'afectats i fons per component
G->>S: Aprovar resolució individualitzada
loop Cada inscripció afectada
 S->>B: Bloquejar item / llegir passos ja resolts
 S->>P: Consultar titular i import atribuït original
 alt Hi ha document fiscal que cal corregir
  S->>F: Classificar i executar correcció idempotent
 end
 opt Cal trasllat/retorn/saldo aprovat
  S->>P: Executar operació econòmica específica sense segon CHARGE
 end
 S->>A: Aplicar canvi acadèmic i verificar matrícula/acces
 A-->>S: Resultat per destí o incidència
 S->>B: Desar resultat individual i passos pendents
end
Note over S,A: El lot i el ledger quantitatiu són DISSENY; la factura original no es reescriu.
```

## 6. Traçabilitat

[UC-127 original](../06-fitxes-funcionals/uc-127.md) · [UC-114 versió producte](uc-114-versionar-producte-edicio.md) · [UC-122 component pack](uc-122-composicio-pack-component-indisponible.md) · [UC-105 traspassos](uc-105-reassignar-repartir-pagament.md) · [UC-124 estats acadèmics](uc-124-reconciliar-acces-certificat-baixa-deute.md) · [UC-121 reserva caducada](uc-121-repreuar-renovar-reserva-caducada.md) · [LegacyCourseSnapshotRepository](../../sif/src/Repository/LegacyCourseSnapshotRepository.php) · [Migració master_data_change_request i events](../../sif/database/migrations/2026_09_16_000005_add_operation_lifecycle_tables.sql) · [Model de fons individuals](00-revisio-moviments-inscripcions.md).
