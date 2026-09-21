# UC-108 · Registrar un tastet o repte gratuït com a operació no facturable

**Objectiu canònic:** registrar la inscripció i la classificació `NON_BILLABLE/FREE_SAMPLE` sense factura, pagament ni enllaç de pagament. El consentiment de mailing és una decisió **independent** de la gratuïtat. La fitxa original indica com a qüestions pendents la prova del consentiment i la regla que impedeix duplicar l'accés gratuït.

**Evidència revisada:** el diccionari defineix `commercial_operation.CLASSIFICATION=NON_BILLABLE` i `FREE_SAMPLE`; la migració defineix `commercial_operation`, `commercial_operation_party` i les línies. **No s'ha acreditat al PHP SIF** un coordinador d'alta gratuïta, control d'accés temporal ni servei de consentiment. `InvoiceService` i `PaymentService` són rutes fiscals/econòmiques separades, **no** passos d'UC-108.

## 1. Fitxa funcional específica

| Element | Regla |
| --- | --- |
| Actors | Participant, ecommerce/intranet i gestió autoritzada per casos dubtosos. El participant pot accedir al tastet sense haver consentit rebre correus comercials. |
| Entrada | Identitat i `ID_INSC` si existeix, tastet/repte i edició, accés ofert, període/venciment acordat, `REQUEST_ID`, `CORRELATION_ID` i clau idempotent; elecció de mailing amb instant i text de consentiment separats. |
| Classificació | Crear/reutilitzar una operació comercial de `NON_BILLABLE` amb motiu `FREE_SAMPLE`, import efectiu zero i producte/participant identificats. Els valors són documentats al catàleg; **el writer comercial encara no està acreditat al PHP**. |
| Alta acadèmica | Una matrícula/grant d'accés gratuït identificable al llegat, amb protecció contra segona alta de la mateixa persona/edició segons UC-107. La durada exacta de l'accés gratuït és **pendent de política**, tot i que la fitxa base preveu provar una setmana. |
| Efectes prohibits | **Cap** `factura`, `factura_registres`, `fiscal_queue`, `payment_transaction`, `payment_allocation`, `redsys_payment_intent`, `payment_link` ni entrada al ledger de fons. Import zero no és un `CHARGE` de zero. |
| Consentiment | Elecció afirmativa o negativa i evidència diferenciada, control de finalitat i revocació segons el sistema de comunicació aprovat; no deduir consentiment de la inscripció. El servei concret de mailing no ha estat identificat. |
| Resultat | `UUID_OPERATION` i `ID_INSC`/identificador d'accés reals, classe no facturable, dates, duplicats/resolució, i dada de mailing separada, sense simular documents fiscals. |

### 1.1. Flux objectiu

1. El canal valida que el producte/edició és efectivament un tastet/repte **gratuït**. Un curs subvencionat o una compra amb preu final zero per aplicació de crèdit **no** es classifica automàticament com `FREE_SAMPLE`.
2. UC-107 detecta una alta equivalent per persona, producte i edició; si existeix, torna a mostrar l'accés anterior sense crear una segona operació.
3. Un coordinador **pendent** crea l'operació `NON_BILLABLE/FREE_SAMPLE` i l'alta acadèmica amb una comanda idempotent, guardant estat/termini d'accés. Si falla el llegat, deixa situació reconciliable; una fila SIF no és per si sola accés concedit.
4. Es registra la decisió de mailing a part, amb prova de què es va acceptar o rebutjar; això no altera la classificació gratuïta.
5. Es comunica l'accés quan l'alta acadèmica està confirmada i el destinatari és l'alumne correcte. En reintent equivalent no es genera una nova entrada fiscal/econòmica ni s'atorguen dos accessos contradictoris.

### 1.2. Alternatives i proves

| Cas | Resposta |
| --- | --- |
| Mateix usuari clica dues vegades | Reús idempotent de l'operació i la inscripció; no duplicar mail ni termini sense regla expressa. |
| Tastet expirat | Aplicar política d'accés/reinscripció; no renovar l'accés automàticament a partir d'una intenció TPV, que aquí no existeix. |
| Preu comercial passa de zero a import positiu | Una altra classificació/oferta i acceptació UC-112; no convertir retrospectivament la reserva gratuïta en factura cobrada. |
| Mailing no consentit | Alta gratuïta igualment possible; registrar `NO` sense marcar-lo com a consentiment implícit. |
| El llegat falla després d'enregistrar l'operació | Reintentar l'alta amb el mateix identificador i reconciliar UC-53, mai emetre factura o `CHARGE` com a compensació tècnica. |

**Pendents de tancament:** integració real de tastets/repte, identificador de recurs, durada d'accés, duplicats, consentiment i proves de regressió; no s'han executat proves PHP.

### 1.3. Endpoint llegat de tastet i diferència entre alta gratuïta i mailing

**Ruta de negoci recuperada.** `33-casos-us-sif.md` identifica `web-actual/ajax/enviarInscripcioTastet.php` com el handler que registra l'alta a **`inscripcions_reptes`**, declara el tastet **gratuït** i gestiona el mailing **sense una operació de cobrament**. Aquesta dada és més específica que una venda `CURS` amb `A_PAGAR=0`: el SIF no ha de registrar cap `payment_transaction`, intenció Redsys, factura o registre AEAT només per crear aquesta alta. El codi web-actual es descriu a la documentació, però **no està disponible a la branca GitHub consultada per certificar cada camp o cada condició del handler**.

**Alta gratuïta i subscripció són dues decisions.** Que el handler llegat gestioni l'opció de mailing no acredita la confirmació vigent ni una cronologia de text/finalitat/retirada. UC-125 ha de distingir sol·licitud i consentiment confirmat, per subjecte i canal, sense reutilitzar el `CORREU` de `inscripcions_reptes` com a prova d'autorització promocional. Si el participant tria `NO`, no s'impedeix per aquest motiu l'alta gratuïta ni els avisos operatius d'accés necessaris; tampoc se li ha de crear una subscripció per haver rebut l'email d'accés.

**Identitat, accés i reincidència.** Abans de crear una segona alta al tastet, comparar participant real, repte/producte i edició/convocatòria segons política **pendent**; dues persones poden compartir email i una mateixa persona pot participar en edicions diferents quan s'hagi autoritzat. Un `ID_INSC` de curs de pagament o un `IDPAG` no s'han d'inventar si el handler només ha creat un ID a `inscripcions_reptes`. La disponibilitat de Moodle/accés ha de verificar-se al destí UC-129, i l'èxit de la inscripció no implica que la matrícula Moodle s'hagi confirmat.

**Canvi posterior de classificació.** Un tastet inicialment gratuït no es converteix en factura històrica si més endavant s'ofereix un curs complet de pagament o un curs subvencionat (UC-109). Cal obrir **una operació nova identificable**, congelar oferta i receptor quan sigui facturable i relacionar-la amb l'origen acadèmic, sense alterar la gratuïtat inicial ni utilitzar el mailing per deduir acceptació d'una compra.

### 1.4. Proves addicionals del handler gratuït (no executades)

| ID | Escenari | Resultat exigible |
| --- | --- | --- |
| TG-108-01 | `enviarInscripcioTastet.php` dona alta a `inscripcions_reptes` | Alta gratuïta identificable; cap factura, intenció, CHARGE ni PDF fiscal. |
| TG-108-02 | Participant tria NO al mailing | Accés gratuït segons política i cap alta promocional per aquest fet. |
| TG-108-03 | Dos participants comparteixen email | Altes/consentiments separats per identitat acreditada, no fusió automàtica. |
| TG-108-04 | Retry d'alta amb repte i edició equivalents | Recuperar l'alta real segons política de duplicats, sense duplicar accés o subscripció. |
| TG-108-05 | Alta al tastet registrada però accés Moodle no confirmat | Estat acadèmic pendent/UC-129, sense crear factura per reparar-lo. |
| TG-108-06 | Participant contracta un curs de pagament més endavant | Nova operació comercial/fiscal quan correspon, no conversió retrospectiva del tastet. |

## 2. UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Participant" as P
actor "Gestió" as G
rectangle "SIF + alta gratuïta" {
 usecase "UC-108\nRegistrar tastet/repte gratuït" as Main
 usecase "UC-107\nEvitar alta duplicada" as Dup
 usecase "Registrar operació FREE_SAMPLE" as Op
 usecase "Crear/vincular inscripció i accés" as Access
 usecase "Registrar consentiment de mailing separat" as Mail
}
P --> Main
G --> Main
Main ..> Dup : <<include>>
Main ..> Op : <<include>>
Main ..> Access : <<include>>
P --> Mail
@enduml
```

## 3. Diagrama de classes — disseny i model SQL

```mermaid
classDiagram
direction LR
class FreeSampleEnrollmentService {
 <<DISSENY: no acreditat>>
 +register(command) result
}
class CommercialOperationRepository {
 <<DISSENY: SQL definit>>
 +createOrReuseFreeSample(db,command) operation
}
class LegacyEnrollmentGateway {
 <<DISSENY: integració no acreditada>>
 +createOrReuseEnrollment(command) enrollment
}
class MailingConsentGateway {
 <<DISSENY: sistema i política pendents>>
 +recordChoice(person,choice,evidence) result
}
FreeSampleEnrollmentService --> CommercialOperationRepository : NON_BILLABLE/FREE_SAMPLE
FreeSampleEnrollmentService --> LegacyEnrollmentGateway : alta i accés
FreeSampleEnrollmentService --> MailingConsentGateway : decisió independent
```

Cap servei fiscal, de pagaments o d'intencions Redsys participa en aquest diagrama perquè **no hi ha import a cobrar**.

## 4. Seqüència objectiu

```mermaid
sequenceDiagram
autonumber
actor P as Participant
participant UI as Canal de tastets [pendent]
participant S as FreeSampleEnrollmentService [DISSENY]
participant O as commercial_operation [SQL definit]
participant L as BD acadèmica llegada
participant M as MailingConsentGateway [DISSENY]
P->>UI: Sol·licitar tastet/repte i indicar opció de mailing
UI->>S: register(persona,producte,edició,requestId)
S->>S: Comprovar gratuïtat i duplicat UC-107
alt Alta equivalent anterior
 S-->>UI: Reutilitzar ID_INSC i accés
else Nova alta vàlida
 S->>O: Persistir NON_BILLABLE/FREE_SAMPLE [writer pendent]
 S->>L: Crear/vincular matrícula i accés idempotent [pendent]
 L-->>S: ID_INSC i estat acadèmic
 S-->>UI: Operació no facturable i accés confirmat
end
UI->>M: recordChoice(persona,SÍ/NO,evidència) [pendent]
UI-->>P: Estat d'accés, sense factura ni cobrament
Note over O,M: El consentiment no és conseqüència automàtica de la gratuïtat
```

## 5. Traçabilitat

[UC-108 original](../06-fitxes-funcionals/uc-108.md) · [UC-107 inscripció duplicada](uc-107-detectar-inscripcio-duplicada.md) · [UC-106 reserva](uc-106-crear-reserva-abans-pagament.md) · [UC-109 subvenció](../06-fitxes-funcionals/uc-109.md) · [Migració operació comercial](../../sif/database/migrations/2026_09_16_000004_add_commercial_operation_and_fiscal_fields.sql) · [Diccionari de classificació](../05-governanca-operacio/24-diccionari-camps-i-valors.md).
