# UC-16b · Treure un participant després d'emetre la factura de grup

**Funció específica:** retirar **una inscripció concreta** d'un grup amb factura ja emesa, conservant la història del grup, el receptor fiscal i la part dels altres participants. El catàleg exigeix **rectificativa i possible devolució/saldo**, no la reescriptura de la factura. **Estat del codi:** existeixen `ManualRectificationService`, `ManualRefundService`, `CreditBalanceService`, `OperationalEventRepository` i el builder de factura **inicial** de grup, però **no s'ha acreditat** un orquestrador PHP que executi la baixa individual, classifiqui la rectificació del document conjunt i decideixi el destí monetari individual.

## 1. Fitxa funcional específica

| Element | Regla |
| --- | --- |
| Actors | Operador autoritzat i empresa/responsable titular econòmic, si correspon. L'alumne de baixa pot no ser qui va ingressar els diners. |
| Entrada | `UUID_FACTURA` de grup, `ID_INSC` a retirar, línia/relació de factura corresponent, `IDPAG` o referència de cobrament, curs/edició, motiu, data, estat acadèmic i regla de devolució aplicable. |
| Precondicions | Participant efectivament inclòs a aquest grup i a la factura original; la inscripció no ha estat donada de baixa abans; factura emesa immutable; autorització i import **atribuït realment a la persona concreta**. |
| Efecte acadèmic | UC-27/72 registra la baixa de la inscripció i l'event amb abans/després; **no** dona de baixa altres membres del grup per error. |
| Efecte fiscal | Corregir la factura de grup pel procediment que correspon, conservant l'original i creant la rectificativa quan pertoqui (UC-05). La línia del participant retirat i el descompte del grup són dades per calcular la correcció; **no editar directament `factura_linia` original**. |
| Efecte econòmic | Distingir quantitat realment cobrada i atribuïda a l'inscrit, import no retornable justificat, devolució real al titular, import convertit en saldo i possibles diferències de preu sobre els altres participants. **Cada tram executat requereix traça per inscripció i titular.** |
| Resultat | Event de baixa amb UUID/correlació, decisió econòmica per tram, factura/rectificativa i `UUID_PAYMENT` de devolució **només si el retorn ha tingut lloc**; altres participants amb la mateixa atribució que abans, llevat de correcció expressament aprovada. |

### 1.1. Flux objectiu de la baixa parcial

1. L'operador localitza el participant i la **seva** `factura_linia.SOURCE_ID` i/o relació `fact_rels`; consulta el grup original, receptor, preu, descomptes i pagaments. **Una relació a factura no diu per si sola l'import cobrat atribuït a l'inscrit**: cal la traça quantitativa proposta.
2. La previsualització separa `IMPORT_FACTURAT_PARTICIPANT`, `FONS_ATRIBUITS`, `IMPORT_PENDENT`, `RETORNABLE`, `NO_RETORNABLE`, `SALDO` i efecte sobre el preu/descompte dels altres membres. Cap import es pren automàticament de la quota global del grup.
3. Es comproven actor/titular, condicions de baixa, conflicte de concurrència, que la inscripció encara és activa i que no s'ha fet ja el retorn o la baixa per una petició equivalent.
4. El contracte objectiu registra l'event de baixa amb snapshots i enllaç a inscripció/línia. `OperationalEventRepository::append()` existeix, però **no s'ha trobat el coordinador que l'executi amb la BD llegada**.
5. Es modifica únicament l'estat acadèmic del participant, amb històric i reconciliació; la factura original manté els N participants tal com van quedar emesos. La classificació fiscal decideix el document corrector i l'import corresponent.
6. **`ManualRectificationPayloadBuilder::forOriginalInvoice()` és un constructor genèric de rectificativa d'una línia amb import indicat, no un càlcul de línia de grup ni de descomptes postbaixa**: cal validar import i camp fiscal abans de cridar-lo.
7. Si hi ha diners cobrats atribuïts a l'inscrit i es retornen **de debò**, UC-28 registra `payment_transaction REFUND`; el ledger proposat registra `INSCRIPCIÓ → EXTERNAL` vinculant `UUID_PAYMENT` del retorn i el titular. Si es concedeix saldo de diner cobrat, UC-29 i moviment `INSCRIPCIÓ → CREDIT`; una baixa sense devolució no genera cap sortida fictícia.
8. Si el pagament global era d'empresa, es preserven els imports atribuïts a les altres inscripcions i no es retorna l'import a l'alumne per defecte. Si canvia l'economia de tot el grup per un descompte, es classifica **una altra correcció** expressa i es reassignen imports mitjançant moviments interns justificats.
9. Es mostra el resultat separat: inscripció de baixa, document fiscal corregit, devolució efectuada o pendent, saldo i conciliació entre sistemes. No afirmar «complet» si falta una fase de retorn/acreditació.

### 1.2. Variants i errors

| Variant | Efecte |
| --- | --- |
| Factura de grup emesa abans de cobrar, import participant encara no cobrat | Baixa i correcció fiscal quan pertoqui; **cap `REFUND`** de diners inexistents. |
| Pagament global de 500 € per cinc persones, una baixa de 100 € | Una entrada original de 500 €, quatre atribucions de 100 € no afectades, i una sortida de 100 € de l'inscripció de baixa **només si es retorna realment**; no cinc devolucions ni un segon `CHARGE`. Exemple il·lustratiu, no preus reals de PrisMa. |
| Retorn de 40 € i saldo de 60 € d'una quota de 100 € ja cobrada | Dos moviments independents per la mateixa inscripció: `REFUND_EXIT` 40 € i `CREDIT_CREATE` 60 €, correlacionats a l'event; no retornar 100 € addicionals. |
| Canvi de descompte de grup en quedar quatre persones | Revisar contracte comercial i facturació de **tots** els membres afectats; no alterar les seves línies fiscals existents ni els seus pagaments originals. |
| Participant de baixa però empresa no accepta devolució | Desar decisió/titular i condicions, sense sortir diners fins que es tramita un retorn real; efecte fiscal separat. |
| Retry de baixa, rectificativa o retorn | Cadascuna d'aquestes operacions exigeix clau pròpia idempotent i traça, amb recuperació del resultat anterior. |
| Falla la baixa llegada després de confirmar el document fiscal | Incidència amb correlació i conciliació; el segon intent no pot tornar a generar un altre document ni un altre retorn bancari. |

**Falta implementar:** càlcul fiscal específic de retirada del grup, orquestració entre BD acadèmica i fiscal, idempotència global, permisos, ledger d'atribució individual i proves d'import per participant. La fitxa original no acredita aquesta execució.

### 1.3. Retirada sobre factura prèvia i recàlcul del tram de grup — contrast amb el xat original

El xat confirma que una factura d'empresa **pot haver-se emès abans de pagar i després perdre un participant**. La baixa de la persona és UC-27/72, però la factura conjunta segueix existint: cal relacionar l'event amb l'`ID_INSC` retirat, la línia fiscal original, el receptor econòmic i les operacions que ja han cobrat **o encara no** han cobrat l'import. Quan la factura encara és PENDING, no existeix un `REFUND` monetari només perquè l'obligació del participant quedi reduïda; cal classificar la rectificació de la part facturada sense anul·lar automàticament les altres línies.

**G-TRAM — conseqüència per la resta del grup:** el xat situa el preu unitari en `descomptes_grup` segons el nombre de persones. Treure una persona pot modificar el tram/preu aplicable als restants, però **la política de recàlcul retroactiu no està decidida a la font**. La pantalla ha de mostrar explícitament el preu fiscal original de cada membre i el preu comercial eventual del grup reconfigurat; si la política aprovada altera obligacions d'altres participants, crear accions i correccions fiscals separades i justificades. No imputar a la persona que marxa les noves diferències de preu dels altres ni modificar el `PAGAMENT` global sense traça.

**G-TITULAR — retorn del pagador real:** si qui va pagar va ser una empresa o un responsable particular, la persona que deixa el curs no adquireix automàticament el dret a una transferència a nom seu. Determinar el titular, condicions de retorn i fonts dels imports atribuïts, i registrar UC-28 només quan es confirma la devolució real; UC-29 només quan neix saldo acceptat. La factura i el document corrector pertanyen al receptor fiscal apropiat, no al participant per defecte.

**G-ENLLAÇ — després de retirar:** revisar si la inscripció deixa de formar part de l'obligació de grup i si la seva URL individual antiga o la URL global de l'empresa encara indiquen imports correctes. No reactivar automàticament una URL individual antiga pel fet de treure-la d'una factura; si cal una nova obligació, UC-50/121 la genera amb snapshot/preu acceptats i control d'intencions bancàries.

### 1.4. Proves de retirada afegides (no executades)

| ID | Cas | Resultat exigible |
| --- | --- | --- |
| GB-01 | Treure una persona d'una factura prèvia encara PENDING | Baixa traçada i efecte fiscal classificat; cap REFUND de diner no cobrat. |
| GB-02 | Empresa ha pagat i un participant se'n va | Decisió de retorn/saldo al titular legitimat i per la part atribuïda; altres membres no perden fons. |
| GB-03 | Retirada modifica eventual tram de descompte | Preus originals preservats; política de preu dels restants explicitada i correccions separades si pertoquen. |
| GB-04 | Participant reactivat després de devolució confirmada | Cap reactivació de pagament ni factura per simple canvi d'estat; tramitar nova operació. |
| GB-05 | URL de pagament individual/global desfasada després de la retirada | Recalcular pendent al servidor, revocar/substituir enllaç quan calgui i conciliar TPV en curs. |
## 2. Diagrama UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Operador autoritzat" as O
actor "Empresa/responsable" as E
rectangle "SIF + gestió del grup" {
 usecase "UC-16b\nTreure participant facturat" as Main
 usecase "Localitzar línia i fons individuals" as Locate
 usecase "UC-27/72\nRegistrar baixa acadèmica" as Drop
 usecase "UC-05\nClassificar i emetre correcció fiscal" as Rect
 usecase "UC-28\nRegistrar retorn real" as Refund
 usecase "UC-29\nCrear saldo aprovat" as Credit
}
O --> Main
E --> Main
Main ..> Locate : <<include>>
Main ..> Drop : <<include>>
O --> Rect
O --> Refund
O --> Credit
note bottom of Main
 Baixa, rectificació i retorn són
 fets separats i idempotents.
end note
@enduml
```

### Vista de casos d’ús per a GitHub (Mermaid)

```mermaid
flowchart LR
  actor_0["Operador autoritzat"]
  actor_1["Empresa/responsable"]
  subgraph SIF_BOX["SIF + gestió del grup"]
    uc_0(["UC-16b<br/>Treure participant facturat"])
    uc_1(["Localitzar línia i fons individuals"])
    uc_2(["UC-27/72<br/>Registrar baixa acadèmica"])
    uc_3(["UC-05<br/>Classificar i emetre correcció fiscal"])
    uc_4(["UC-28<br/>Registrar retorn real"])
    uc_5(["UC-29<br/>Crear saldo aprovat"])
  end
  actor_0 --> uc_0
  actor_1 --> uc_0
  uc_0 -.->|include| uc_1
  uc_0 -.->|include| uc_2
  actor_0 --> uc_3
  actor_0 --> uc_4
  actor_0 --> uc_5
```

## 3. Subdiagrama de classes: existent i orquestració pendent

```mermaid
classDiagram
direction LR
class GroupParticipantRemovalCoordinator {
 <<DISSENY: no implementada>>
 +preview(command) result
 +confirm(command) result
}
class OperationalEventRepository {
 <<PHP existent>>
 +append(db,event) string
}
class ManualRectificationService {
 <<PHP existent: genèrica>>
 +issueByUuid(db,uuidFactura,input) array
}
class ManualRefundService {
 <<PHP existent>>
 +registerByUuid(db,uuidFactura,input) array
}
class CreditBalanceService {
 <<PHP existent>>
 +createCredit(input) array
}
class EnrollmentFundMovementRepository {
 <<PROPOSTA: no implementada>>
 +balanceForEnrollment(db,idInsc) decimal
 +append(db,movement) string
}
GroupParticipantRemovalCoordinator --> OperationalEventRepository : event previst
GroupParticipantRemovalCoordinator --> ManualRectificationService : correcció fiscal classificada
GroupParticipantRemovalCoordinator --> ManualRefundService : retorn real
GroupParticipantRemovalCoordinator --> CreditBalanceService : saldo aprovat
GroupParticipantRemovalCoordinator --> EnrollmentFundMovementRepository : trams per inscripció
```

## 4. Seqüència objectiu — baixa individual d'un grup facturat

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant UI as Intranet [pendent]
participant C as GroupParticipantRemovalCoordinator [DISSENY]
participant Legacy as Grup i inscripcions llegades
participant L as Ledger per inscripció [PROPOSTA]
participant Ev as OperationalEventRepository [PHP]
participant F as Classificació fiscal [pendent]
participant Rect as ManualRectificationService [PHP]
participant R as ManualRefundService [PHP]
participant Cr as CreditBalanceService [PHP]
O->>UI: Retirar inscrit A de factura de grup F
UI->>C: preview(F,A,motiu)
C->>Legacy: Comprovar A i altres participants
C->>L: Saldo cobrat atribuït exclusivament a A
L-->>C: Origen del pagament i import disponible
C->>F: Import/servei del grup abans/després
F-->>C: Criteri de correcció pendent de validar
C-->>UI: Previsualització baixa, rectificativa i retorn/saldo
O->>UI: Confirmar amb actor, titular i idempotència
UI->>C: confirm(command)
C->>Ev: append(event baixa A, snapshots)
C->>Legacy: Donar de baixa només A amb històric
opt Correcció fiscal classificada
 C->>Rect: issueByUuid(F, input validat)
 Rect-->>C: UUID_FACTURA_RECTIFICATIVA
end
alt Sense diners cobrats/retorn aprovat
 C-->>UI: Baixa, document i deute, sense REFUND
else Retorn real executat
 C->>R: registrar REFUND amb import d'A
 R-->>C: UUID_PAYMENT_REFUND
 C->>L: append(A→EXTERNAL,import retornat,UUID_PAYMENT_REFUND)
else Import d'A transformat en saldo
 C->>Cr: createCredit(titular,import)
 Cr-->>C: UUID_CREDIT
 C->>L: append(A→CREDIT,import,UUID_CREDIT)
end
C-->>UI: Fases executades i pendents
Note over C,L: No alterar les atribucions dels altres participants ni cobrar una segona vegada
```

### 4.1. Seqüència — baixa parcial sense cobrament i canvi de tram (OBJECTIU)

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant G as Grup/intranet [adaptació pendent]
participant I as Inscripció i factura de grup
participant Price as descomptes_grup [consulta a verificar]
participant F as Classificador fiscal [PENDENT]
participant M as Devolució/saldo [segons cobrament]
O->>G: Treure ID_INSC d'una factura de grup existent
G->>I: Llegir factura, línia, pagador, fons i estat PENDING/PARTIAL/PAID
G->>Price: Consultar eventual canvi de tram per la resta
G-->>O: Previsualitzar participant retirat i afectació separada als altres
alt Factura prèvia encara sense cobrament
 G->>F: Classificar correcció fiscal de part retirada i d'altres si procedeix
 G-->>O: Cap REFUND ni CHARGE ficticis
else Part efectivament cobrada
 G->>M: Validar titular i decidir retorn/saldo per import atribuït
 G->>F: Classificar rectificació de part retirada i tram si pertoca
 G-->>O: Efectes econòmics executats i pendents per separat
end
Note over G,M: L'orquestrador de retirada, la política de reprecificació i el ledger per inscripció no són implementació acreditada.
```
## 5. Traçabilitat

[UC-16b original](../06-fitxes-funcionals/uc-016b.md) · [UC-16 grup](uc-016-facturar-grup.md) · [UC-27 baixa](uc-027-donar-de-baixa.md) · [UC-72 expedient](uc-072-registrar-baixa-decisio-economica.md) · [UC-05 correcció](uc-005-rectificar-factura.md) · [UC-28 retorn](uc-028-registrar-devolucio.md) · [UC-29 saldo](uc-029-crear-saldo.md) · [Revisió fons individual](00-revisio-moviments-inscripcions.md) · [ManualRectificationService](../../sif/src/Service/ManualRectificationService.php) · [OperationalEventRepository](../../sif/src/Repository/OperationalEventRepository.php).

**No s'han executat proves PHP ni s'ha acreditat la classificació fiscal d'aquest cas a un entorn real.**
