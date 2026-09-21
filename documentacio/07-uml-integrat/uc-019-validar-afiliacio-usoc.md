# UC-19 · Validar afiliació USOC abans de la doble facturació

**Finalitat:** decidir si una inscripció pot utilitzar el circuit de finançament USOC abans de preparar les factures a l'alumne (UC-19a) i a l'entitat (UC-19b). El catàleg original identifica `TIPUS_DESC=4` i `VALID_DESC` i indica que la **pantalla final és pendent**. **No** s'ha acreditat un servei PHP que verifiqui automàticament afiliació vigent, documentació aportada, consentiments ni autorització de l'entitat: les comprovacions del repositori fiscal llegat només llegeixen camps ja marcats.

## 1. Fitxa específica i evidència

| Element | Regla |
| --- | --- |
| Actors | Persona inscrita que al·lega la condició; operador autoritzat que comprova el document; entitat USOC quan cal confirmar una condició de finançament. |
| Identitat del cas | `ID_INSC`, `IDPAG`, curs i edició, titular de la condició, import part alumne i part entitat. **No deduir la vigència de l'afiliació només del valor `TIPUS_DESC`.** |
| Dades llegades observades | `LegacyUsocSnapshotRepository::loadByIdpag()` selecciona `inscripcions` per `IDPAG`, amb `TIPUS_DESC` i `VALID_DESC`, i comprova que siguin exactament **4** i **1** respectivament. Si no coincideixen, llança conflicte. |
| Dades fiscals posteriors | `LegacyUsocInvoicePayloadBuilder::buildStudentPayload()` torna a exigir `TIPUS_DESC=4`/`VALID_DESC=1` i construeix la factura alumne. `buildEntityPayload()` rep les dades fiscals explícites de l'entitat; **els camps de validació no aporten per si sols aquestes dades fiscals**. |
| Font i protecció de l'evidència | Proposta de custòdia de justificants i dades de validació amb accés limitat. La simple fotografia fiscal no hauria d'incloure còpia de document privat si no és necessari; la migració pot definir `discount_evidence`, però el writer i els permisos s'han de verificar. |
| Resultat funcional objectiu | Decisió tipificada i datada, `ID_INSC`, actor, motiu, vigència i import/drets autoritzats. **Els estats exactes i l'origen de la verificació requereixen acord amb el negoci.** |
| Efecte fiscal/econòmic immediat | **Cap `CHARGE`, cap factura i cap aplicació de fons.** La validació és prerequisit de UC-13/19a/19b, no una entrada bancària ni una emissió per si mateixa. |

### 1.1. Flux funcional objectiu

1. L'usuari selecciona o demana el tractament USOC en una inscripció. El canal protegeix la identitat i recull el mínim necessari per verificar la condició, amb data, edició i responsable de la decisió.
2. La persona autoritzada verifica la condició i, quan correspongui, evidència, vigència i condicions de l'entitat. **Aquests passos són un contracte objectiu, no mètodes PHP identificats al SIF.**
3. La decisió s'associa a `ID_INSC` amb actor/motiu i es registra en el llegat o servei acordat; l'orquestració de BD llegat ↔ BD SIF és pendent. No marcar `VALID_DESC=1` per la simple selecció d'un descompte al formulari.
4. Quan `TIPUS_DESC=4` i `VALID_DESC=1` estan confirmats i la informació comercial és coherent, `LegacyUsocSnapshotRepository::loadByIdpag()` **sí que comprova aquests valors** en recuperar el snapshot per a facturació. Rebutja inscrits sense la marca.
5. La factura alumne UC-19a es prepara amb import alumne i import de l'entitat separats; la factura de l'entitat UC-19b s'emet amb dades fiscals específiques quan pertoqui. **No** inferir que l'entitat ja ha pagat perquè la condició estigui validada.
6. Si la condició perd vigència o es descobreix un error **després** d'emetre, cal classificar correcció fiscal/econòmica i relacionar-la amb UC-05/71/72; no reescriure retrospectivament `factura_linia`.

### 1.2. Alternatives i riscos

| Situació | Comportament |
| --- | --- |
| `TIPUS_DESC` diferent de 4 | El repositori de snapshot rebutja la ruta USOC. |
| `VALID_DESC` diferent de 1 | El repositori i el builder no emeten per la ruta USOC a partir d'aquest snapshot. |
| Marcadors correctes però justificació caducada o fraudulentament aportada | **Buit funcional:** els mètodes fiscals comproven camps, no identitat/validesa externa. Bloquejar o revisar per política final, registrar actor i prova. |
| Import de l'entitat no definit o no acceptat | No facturar suposant automàticament un pagador; UC-19a/19b requereixen imports i receptor separats. |
| Canvi de curs abans del cobrament | Revalidar si la condició i la política comercial s'apliquen a la nova edició; qualsevol import preexistent del llegat no és diner ingressat. |
| Canvi/baixa després de cobrar | Cal reconstruir les dues factures i **els dos orígens de pagament efectius**, no retornar a l'alumne la part d'entitat per defecte. |

**No s'ha acreditat una prova de validació d'afiliació real.** Les proves d'emissió USOC exerciten el control dels marcadors, no la comprovació externa del dret.

### 1.3. Pantalla i estats de validació USOC al llegat

La pantalla `/alumnes/validar-descomptes/` presenta les inscripcions pendents mitjançant `cnsAlumnDescNoValidat`; el mètode `__mostrarPage_Inici_ValidarDescomptes` informa que hi ha descomptes pendents i `__mostrarPage_Alumnes_ValidarDescomptes` mostra «Afiliat USOC». Els valors històrics són `TIPUS_DESC=4` per la sol·licitud USOC, i `VALID_DESC=0` pendent, `1` validat i vàlid, `2` validat i no vàlid. `updValidDescByInsc` actualitza la validació; `updValidDescByInscPreu` també pot canviar `TIPUS_DESC` i `A_PAGAR`. Són rutines llegades identificades, no una comprovació externa implementada al SIF.

Gestió confirma manualment l'afiliació amb USOC i comunica el resultat a l'alumne. En una denegació, el canal recalcula el preu sense descompte **abans** de facturar o cobrar i invalida l'oferta anterior incompatible. El cas normal descrit indica descompte del 25 % i pagament inicial de 10 € per l'alumne; són dades del circuit recuperat, no valors universals que el builder hagi d'imposar. La variant «Curs gratuït USOC» vinculada a `anticipi-preu-usoc` requereix classificació separada (UC-13).

### 1.4. Proves addicionals (no executades)

| ID | Escenari | Resultat |
| --- | --- | --- |
| UV-01 | TIPUS_DESC=4, VALID_DESC=0 | Pendent de comprovació; no emetre amb descompte. |
| UV-02 | Afiliació confirmada | VALID_DESC=1 i import acceptat congelat abans del TPV. |
| UV-03 | Afiliació denegada | VALID_DESC=2; preu ordinari i nova oferta si escau, sense factura USOC. |
| UV-04 | Validació posterior a una factura | Expedient de correcció, no UPDATE fiscal directe. |

## 2. Diagrama UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Alumne" as A
actor "Gestió autoritzada" as G
actor "Entitat USOC" as U
rectangle "Validació de descompte USOC" {
 usecase "UC-19\nValidar condició USOC" as Main
 usecase "Comprovar identitat i vigència" as Proof
 usecase "Registrar decisió i evidència" as Record
 usecase "UC-19a\nFacturar part alumne després" as Student
 usecase "UC-19b\nFacturar part entitat després" as Entity
}
A --> Main
G --> Main
U --> Proof
Main ..> Proof : <<include>>
Main ..> Record : <<include>>
G --> Student
G --> Entity
note bottom of Main
 La comprovació fiscal del camp VALID_DESC
 no és un servei de validació d'afiliació.
end note
@enduml
```

## 3. Classes existents i classes de disseny separades

```mermaid
classDiagram
direction LR
class UsocEligibilityService {
 <<DISSENY: no acreditada al PHP>>
 +validate(inscription,evidence,actor) decision
}
class UsocValidationRepository {
 <<DISSENY: writer no acreditat>>
 +recordDecision(db,decision) result
}
class LegacyUsocSnapshotRepository {
 <<PHP existent>>
 +loadByIdpag(legacyDb,idpag,studentAmount,entityAmount) array
}
class LegacyUsocInvoicePayloadBuilder {
 <<PHP existent>>
 +buildStudentPayload(snapshot) array
 +buildEntityPayload(snapshot,input) array
}
UsocEligibilityService --> UsocValidationRepository : decisió i prova
LegacyUsocInvoicePayloadBuilder --> LegacyUsocSnapshotRepository : dades prèviament validades
```

**Precisió:** la relació final del diagrama és **dependència funcional de dades, no una crida PHP directa**: el servei `RedsysUsocInvoiceService` o `UsocEntityInvoiceService` obté el snapshot abans d'invocar el builder. El validador d'afiliació i el seu writer **no estan acreditats**.

## 4. Seqüència — comprovació de la condició i ús posterior

```mermaid
sequenceDiagram
autonumber
actor A as Alumne
actor O as Gestió autoritzada
participant UI as Canal de validació [pendent]
participant V as UsocEligibilityService [DISSENY]
participant DB as BD d'inscripcions llegada
participant R as LegacyUsocSnapshotRepository [PHP existent]
participant B as LegacyUsocInvoicePayloadBuilder [PHP existent]
A->>UI: Sol·licitar tractament USOC amb prova de dret
UI->>V: validate(ID_INSC,actor,evidència)
V->>V: Comprovar condició, vigència i política
alt No acreditat
 V-->>UI: Decisió denegada/revisió
 UI-->>A: No activar circuit USOC
else Verificació confirmada
 V->>DB: Registrar TIPUS_DESC=4 i VALID_DESC=1 amb historial [DISSENY]
 V-->>UI: Validat amb data i justificació
 O->>R: loadByIdpag(IDPAG,studentAmount,entityAmount)
 R->>DB: SELECT inscripció i comprovar marcadors 4/1
 alt Marcadors absents o incoherents
  R--xO: Conflicte
 else Snapshot fiscal elegible
  R-->>O: Inscripció, curs, student_amount, entity_amount
  O->>B: buildStudentPayload(snapshot) [UC-19a posterior]
  B-->>O: Factura alumne preparada, no cobrament encara
 end
end
Note over V,B: L'afiliació externa i les escriptures de validació són disseny pendent
```

## 5. Traçabilitat

[UC-19 original](../06-fitxes-funcionals/uc-019.md) · [UC-13 doble facturació](uc-013-orquestrar-doble-facturacio-usoc.md) · [UC-19a alumne](uc-019a-facturar-part-alumne-usoc.md) · [UC-19b entitat](uc-019b-facturar-part-entitat-usoc.md) · [LegacyUsocSnapshotRepository](../../sif/src/Repository/LegacyUsocSnapshotRepository.php) · [LegacyUsocInvoicePayloadBuilder](../../sif/src/Service/LegacyUsocInvoicePayloadBuilder.php) · [Diccionari](../05-governanca-operacio/24-diccionari-camps-i-valors.md) · [Revisió dels fons](00-revisio-moviments-inscripcions.md).
