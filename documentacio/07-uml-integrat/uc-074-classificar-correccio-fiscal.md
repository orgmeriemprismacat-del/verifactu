# UC-74 · Classificar una correcció fiscal abans d'executar-la

**Objectiu del catàleg:** distingir **rectificativa, complementària, registre d'anul·lació, registre de subsanació o cap efecte fiscal** segons el fet i les evidències de l'operació; impedir l'`UPDATE` directe de la factura emesa. **Estat [DISSENY/BLOQUEJANT]:** al PHP revisat existeixen **executors concrets** per rectificativa (UC-05), registre d'anul·lació (UC-75) i subsanació (UC-76), però **no s'ha acreditat un `FiscalCorrectionClassifier` que decideixi automàticament la via correcta** ni les condicions reals de cada escenari. Les denominacions del catàleg **no són per si soles una validació jurídica de cada branca**: cal decisió fiscal documentada per cas.

## 1. Dades i distincions

| Via considerada | Fet a documentar i límit contrastat |
| --- | --- |
| Sense efecte fiscal | Canvi de contacte, accés Moodle, preferència documental o moviment intern que **no modifica el contingut fiscal**. Registrar decisió i derivar al cas operatiu corresponent, sense crear `factura_registres` nou. |
| Rectificativa | Correcció de prestació, import, receptor o altra dada fiscal quan la classificació aprovada determina aquesta via. `ManualRectificationService` sí que construeix factura sèrie `R` i crea vincle a `factura_rectificacio`; **no** retorna diners automàticament. |
| Complementària | El catàleg contempla aquesta decisió però **no s'ha localitzat un executor PHP separat ni un contracte tancat de selecció de tipus/import**. No representar-la com a funció actual de `FiscalRecordService`. |
| Registre d'anul·lació | `FiscalRecordService::createCancellationByUuid()` pot crear `ANULACIO` sobre factura SIF amb registre previ. **No és** baixa acadèmica, devolució o rectificativa de servei cancel·lat. El classificador ha de justificar quan la factura/registre és improcedent segons el criteri fiscal validat. |
| Registre de subsanació | `FiscalRecordService::createSubsanationByUuid()` accepta tipus de subsanació concrets i crea registre encadenat; **no** canvia `factura_linia` ni documenta per si sol un servei nou o un ajust d'import. |
| Situació econòmica | `CHARGE`, `REFUND`, `COMPENSATION` i traspassos per `ID_INSC` s'analitzen **separadament**. Una decisió fiscal no acredita entrada/sortida bancària ni duplica un moviment existent. |

### Flux objectiu de classificació

1. Consultar factura original, número, `UUID_FACTURA`, tipus, registres fiscals i estat de cua/AEAT, línies i receptor **immutables**, esdeveniment operatiu que origina la revisió, persones afectades i estat de cobrament real.
2. L'operador previsualitza el **fet**: error de dada a la factura, error de registre, anul·lació d'una prestació, descompte tardà, canvi d'edició, duplicitat de factura o només canvi de contacte. Distingir el fet **econòmic** i la necessitat de retorn de diners del tipus de document/registre fiscal.
3. El classificador **pendent** demana les proves i criteri fiscal aplicables, data/tipus de correcció, receptor, import i registre anterior. Si manca criteri per triar via o l'original no és localitzable, estat `PENDING_DECISION`, sense emetre per intuïció.
4. Registrar decisió amb actor, motiu, abans/després, `REQUEST_ID/CORRELATION_ID`, `UUID_FACTURA` original i referència de l'event. El servei actual de registres d'anul·lació/subsanació **no invoca automàticament aquesta aprovació**.
5. Derivar **una única via fiscal** per la decisió aprovada a UC-05/75/76 o a un executor de complementària **no acreditat**, preservant el document original i els registres previs. Executar per clau idempotent i comprovar resultat existent abans de qualsevol reintent.
6. Derivar independentment, quan pertoqui, UC-28/29/105 per retornar, acreditar saldo o reassignar el valor **que realment correspon a cada inscripció**, sense crear `REFUND` únicament per haver emès una rectificativa ni un segon `CHARGE` per una subsanació.
7. Si la cua AEAT falla després de crear un registre fiscal, UC-77 opera el **mateix registre**, no torna a classificar la incidència com a nova factura.

**Proves pendents:** dos errors de naturalesa distinta en la mateixa factura; sol·licitud d'alumne però receptor empresa; document ja rectificat o cancel·lat; `NO_VERIFACTU` històrica; pagament parcial i devolució tardana; reintent concurrent del mateix event; codis de resposta AEAT. No afirmar que el classificador o la complementària estan implementats.

## 2. UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Gestió autoritzada" as G
actor "Responsable fiscal" as F
rectangle "SIF · classificació de correccions" {
 usecase "UC-74\nClassificar correcció fiscal" as Main
 usecase "Comparar document original i fet" as Compare
 usecase "Aprovar via fiscal i motiu" as Decide
 usecase "UC-05\nRectificativa" as Rect
 usecase "UC-75\nRegistre anul·lació" as Cancel
 usecase "UC-76\nRegistre subsanació" as Subs
 usecase "Cap efecte fiscal" as None
}
G --> Main
F --> Decide
Main ..> Compare : <<include>>
Main ..> Decide : <<include>>
Rect ..> Main : <<extend>> (decisió rectificativa)
Cancel ..> Main : <<extend>> (decisió anul·lació)
Subs ..> Main : <<extend>> (decisió subsanació)
None ..> Main : <<extend>> (fet no fiscal)
@enduml
```

### Vista de casos d’ús per a GitHub (Mermaid)

```mermaid
flowchart LR
  actor_0["Gestió autoritzada"]
  actor_1["Responsable fiscal"]
  subgraph SIF_BOX["SIF · classificació de correccions"]
    uc_0(["UC-74<br/>Classificar correcció fiscal"])
    uc_1(["Comparar document original i fet"])
    uc_2(["Aprovar via fiscal i motiu"])
    uc_3(["UC-05<br/>Rectificativa"])
    uc_4(["UC-75<br/>Registre anul·lació"])
    uc_5(["UC-76<br/>Registre subsanació"])
    uc_6(["Cap efecte fiscal"])
  end
  actor_0 --> uc_0
  actor_1 --> uc_2
  uc_0 -.->|include| uc_1
  uc_0 -.->|include| uc_2
  uc_3 -.->|extend| uc_0
  uc_4 -.->|extend| uc_0
  uc_5 -.->|extend| uc_0
  uc_6 -.->|extend| uc_0
```

## 3. UML de classes — classificador absent, executors PHP reals

```mermaid
classDiagram
class FiscalCorrectionClassifier {
 <<DISSENY: no acreditat>>
 +preview(uuidFactura,event) options
 +approve(uuidFactura,decision,actor) classification
}
class FiscalCorrectionDecisionRepository {
 <<DISSENY: writer específic no acreditat>>
 +append(db,decision) result
}
class ManualRectificationService {
 <<PHP existent>>
 +issueByUuid(sifDb,uuidFactura,input) array
}
class FiscalRecordService {
 <<PHP existent>>
 +createCancellationByUuid(uuidFactura,input) array
 +createSubsanationByUuid(uuidFactura,input) array
}
class PaymentReallocationService {
 <<DISSENY: diner separat>>
 +apply(command) result
}
FiscalCorrectionClassifier --> FiscalCorrectionDecisionRepository : decisió i versions
FiscalCorrectionClassifier ..> ManualRectificationService : via aprovada [integració pendent]
FiscalCorrectionClassifier ..> FiscalRecordService : via aprovada [integració pendent]
```

## 4. UML de seqüència — canvi de curs amb diferència i document previ (DISSENY/PARCIAL)

```mermaid
sequenceDiagram
autonumber
actor G as Gestió
actor F as Responsable fiscal
participant C as FiscalCorrectionClassifier [DISSENY]
participant DB as Factura/registres originals [PHP/SQL]
participant D as Decisió de classificació [DISSENY]
participant R as ManualRectificationService [PHP]
participant A as FiscalRecordService [PHP]
participant Funds as Moviments per inscripció [UC-105/28]
G->>C: Revisar canvi de curs i factura F ja emesa
C->>DB: Llegir prestació, receptor, línies, registres i pagaments
C-->>F: Comparació i vies possibles amb evidència/motiu
alt Criteri fiscal insuficient
 F-->>C: Pendent de decisió
 C-->>G: No emetre corrector automàtic
else Via fiscal aprovada
 F->>C: Confirmar tipus, import si escau i idempotència
 C->>D: Registrar decisió i relació amb factura original
 alt Rectificativa segons classificació
  C->>R: issueByUuid(F,input)
  R-->>C: UUID_FACTURA_RECTIFICATIVA
 else Anul·lació de registre segons classificació
  C->>A: createCancellationByUuid(F,input)
  A-->>C: Registre ANULACIO i cua
 else Subsanació segons classificació
  C->>A: createSubsanationByUuid(F,input)
  A-->>C: Registre SUBSANACIO i cua
 else No efecte fiscal
  C-->>G: Decisió NO_CHANGE fiscal
 end
 opt Hi ha trasllat o retorn de fons acreditat i aprovat
  C->>Funds: Derivar cas econòmic per import individual
 end
end
Note over C,Funds: Executar una via no equival a decidir-la ni a moure diners.
```

## 5. Traçabilitat

[UC-74 original](../06-fitxes-funcionals/uc-074.md) · [UC-05 rectificativa](uc-005-rectificar-factura.md) · [UC-75 original](../06-fitxes-funcionals/uc-075.md) · [UC-76 original](../06-fitxes-funcionals/uc-076.md) · [UC-105 fons](uc-105-reassignar-repartir-pagament.md) · [ManualRectificationService](../../sif/src/Service/ManualRectificationService.php) · [FiscalRecordService](../../sif/src/Service/FiscalRecordService.php) · [FiscalRecordPayloadBuilder](../../sif/src/Service/FiscalRecordPayloadBuilder.php) · [Fluxos de facturació](../03-canvis-pendents/04-fluxos-facturacio.md).
