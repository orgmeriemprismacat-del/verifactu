# UC-120 · Tramitar una sol·licitud de canvi de dades personals i propagar-la

**Objectiu canònic:** registrar abans/després, justificació, decisió, actor i sistemes afectats; **cap factura emesa ni snapshot històric es reescriu**. La fitxa original deixa pendent determinar qui aprova cada camp, a quins sistemes es propaga i el tractament de rectificació/supressió sense alterar documents històrics.

**Evidència SQL:** `personal_data_change_request` inclou `SUBJECT_KEY`, `REQUESTER_ACTOR_ID`, `CHANGESET_JSON`, `JUSTIFICATION`, `EVIDENCE_STORAGE_REF/HASH`, estat i revisió, `PROPAGATION_STATUS`, `PROPAGATION_RESULT_JSON`, `AFFECTED_OPEN_OPERATIONS_JSON`, correlació i timestamps. **No s'ha acreditat** una classe PHP que gestioni la petició, autoritzi l'actor, modifiqui les dades acadèmiques i propagui per tots els canals. L'existència d'aquesta taula **no** implica que el canvi s'hagi completat en llegat, facturació, aula o enviaments.

## 1. Fitxa funcional específica

| Aspecte | Regla i distinció |
| --- | --- |
| Actors | Persona interessada o representant verificat; gestió amb permisos per camp; responsable de protecció de dades quan la sol·licitud requereixi revisió; procés de sincronització entre sistemes. |
| Entrada | Subjecte identificat, petició/actor, camp i valor anterior/proposat, motiu, prova mínima quan cal, llista de sistemes afectats, estat de les operacions comercials obertes i correlació. No incloure credencials, documents complets o proves sensibles en `CHANGESET_JSON`. |
| Tipus de canvi | Separar contacte/accés (per exemple email), identitat fiscal d'operació **encara oberta**, dades acadèmiques vigents i errors d'una factura **ja emesa**. Cada categoria té permisos i efectes diferents. |
| Persistència | `personal_data_change_request` pot registrar proposta, revisió, estat de propagació i incidències; el SQL **no** aplica el canvi a `inscripcions`, `factura`, `fact_rels` ni a altres sistemes automàticament. |
| Operacions obertes | Revisar snapshot i receptor/pagador de cada oferta pendent; si un canvi d'identitat fiscal afecta un checkout acceptat, fer nova classificació/acceptació quan correspongui, no substituir silenciosament la instantània usada per `DS_ORDER`. |
| Factura emesa | El document fiscal, emissor/receptor congelats, número, línies i cadena **no s'editen** a causa d'un canvi al perfil. Si l'error afecta dades fiscals d'un document emès, classificar la via de correcció fiscal aplicable amb UC-05/74; no deduir que qualsevol canvi personal exigeix rectificativa. |
| Dades històriques i supressió | Minimització/restricció i política de conservació a definir per tipus i sistema; no fer `DELETE` automàtic de factures històriques o evidències necessàries perquè una persona canvia el correu de contacte. |

### Flux objectiu

1. El canal verifica subjecte/representació, captura camp/s a corregir i justificant mínim, i limita qui pot veure dades anteriors. Registra `personal_data_change_request` amb correlació.
2. Un revisor habilitat determina camps/sistemes i comprova si la petició afecta únicament perfil acadèmic/contacte o també dades fiscals d'operacions obertes/factures emeses. Denegació amb causa tipificada i informació de resultat al sol·licitant.
3. Quan és aprovada, el coordinador **pendent** crea un pla de propagació idempotent amb destinacions i versions/estats, sense assumir una transacció distribuïda entre BD llegada, SIF i altres aplicacions.
4. Actualitza les dades **vigents** en cadascun dels sistemes pertinents i registra resultat per destinació a `PROPAGATION_RESULT_JSON`; un error parcial deixa `PROPAGATION_STATUS` pendent i un reintent del mateix canvi, mai un historial que diu «complet» sense verificar cada sistema.
5. Per operacions encara obertes, UC-112/114 classifica si el snapshot acceptat requereix nova aprovació del pagador; no reescriure la intenció `DS_ORDER` anterior.
6. Per factures ja emeses, mantenir història i derivar únicament els errors fiscals reals al procediment corrector corresponent. No propagar el nou NIF o nom al camp de receptor d'una factura antiga per simple actualització de perfil.
7. Tancar petició quan la propagació ha quedat registrada, les incidències pendents estan identificades i les restriccions de visibilitat/retenció han estat aplicades.

### Alternatives i proves

| Cas | Resultat |
| --- | --- |
| Correu nou abans de comprar | Actualitzar contacte vigent autoritzat; no tocar imports ni factura antiga. |
| Canvi de NIF amb compra pendent | Revalidar receptor fiscal i snapshot, determinar si cal nova acceptació/intenció segons l'estat de la compra. |
| Receptor equivocat en factura ja emesa | Expedient de correcció fiscal; no `UPDATE factura.BILLING_NIF` ni regeneració opaca del PDF. |
| Un sistema confirma i l'altre falla | Reintentar només la destinació pendent i mantenir `PROPAGATION_STATUS` de no completat. |
| Sol·licitant no acreditat o canvi d'una altra persona | Denegar accés i decisió sense exposar les dades personals de l'altre subjecte. |
| Sol·licitud de supressió incompatible amb alguna conservació aplicable | Classificar i documentar les dades i destinacions segons el règim aplicable, sense aplicar un esborrat global que alteri registres fiscals. |

**Pendents:** matriu de permisos per camp i destinació, esquema de propagation jobs, validació de representació/justificants, tractament d'operacions obertes i documents emesos, política de retenció, proves de fallada parcial i auditoria. No s'han executat proves PHP.

## 2. UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Persona/representant acreditat" as P
actor "Gestió autoritzada" as G
actor "Procés de sincronització" as S
rectangle "SIF · canvi de dades personals" {
 usecase "UC-120\nTramitar petició de canvi" as Main
 usecase "Verificar subjecte i permisos" as Verify
 usecase "Revisar camps i operacions afectades" as Impact
 usecase "Propagar a sistemes vigents" as Sync
 usecase "Classificar document fiscal ja emès" as Fiscal
}
P --> Main
G --> Main
S --> Sync
Main ..> Verify : <<include>>
Main ..> Impact : <<include>>
Fiscal ..> Main : <<extend>> (dada de factura emesa)
@enduml
```

## 3. Diagrama de classes — SQL definit, propagació pendent

```mermaid
classDiagram
class PersonalDataChangeService {
 <<DISSENY: no acreditat>>
 +submit(subject,change) request
 +review(uuidRequest,actor,decision) result
 +propagate(uuidRequest) status
}
class PersonalDataChangeRepository {
 <<DISSENY: SQL definit>>
 +append(db,request) result
 +recordReview(db,uuid,decision) result
 +recordDestinationResult(db,uuid,destination) result
}
class PersonalDataPropagationGateway {
 <<DISSENY: llegat i canals pendents>>
 +applyVersioned(subject,changes,destination) result
}
class LegacySyncService {
 <<PHP existent: altra responsabilitat>>
 +syncInvoice(result) array
}
PersonalDataChangeService --> PersonalDataChangeRepository : petició i resultats
PersonalDataChangeService --> PersonalDataPropagationGateway : dades vigents
```

`LegacySyncService` existent fa sincronització de **resultats de factura**; no s'ha d'interpretar com a gestor implementat de canvis de dades personals, per això no es dibuixa com a dependència executable d'UC-120.

## 4. Seqüència — actualització de perfil i document fiscal preservat

```mermaid
sequenceDiagram
actor P as Persona
actor G as Gestió
participant C as PersonalDataChangeService [DISSENY]
participant R as personal_data_change_request [SQL]
participant L as Perfil acadèmic llegat [integració pendent]
participant O as Operacions comercials obertes [SQL]
participant F as Factures fiscals històriques [SIF]
P->>C: Sol·licitar canvi de contacte o identificació
C->>R: append(REQUESTED,subject,changeset,justificació)
G->>C: Revisar identitat, camp i sistemes afectats
alt Petició no acreditada
 C->>R: recordReview(REJECTED,causa)
 C-->>P: Rebuig sense revelar dades alienes
else Petició aprovada
 C->>R: recordReview(APPROVED,actor)
 C->>L: applyVersioned(dades vigents)
 L-->>C: Resultat o incidència
 C->>O: Comprovar snapshots oberts i necessitat de nova acceptació
 opt Error de dades d'una factura emesa
  C->>F: Consultar document immutable i obrir classificació fiscal
  Note over C,F: Cap UPDATE de factura original dins d'UC-120
 end
 C->>R: recordDestinationResult(estat real per sistema)
 C-->>P: Canvi complet o parcial amb incidències indicades
end
Note over C,F: Orquestració i propagació no acreditades al PHP actual.
```

## 5. Traçabilitat

[UC-120 original](../06-fitxes-funcionals/uc-120.md) · [UC-114 versions](uc-114-versionar-producte-edicio.md) · [UC-112 snapshot](uc-112-congelar-snapshot-abans-tpv.md) · [UC-05 correcció](uc-005-rectificar-factura.md) · [UC-53 reconciliació](uc-053-detectar-resoldre-divergencies.md) · [Migració personal_data_change_request](../../sif/database/migrations/2026_09_16_000005_add_operation_lifecycle_tables.sql) · [LegacySyncService: sincronització fiscal diferent](../../sif/src/Service/LegacySyncService.php).
