# UC-124 · Reconciliar accés acadèmic i certificat amb baixa, deute i pagador de grup

**Objectiu canònic:** l'estat d'inscripció, l'accés Moodle, el dret al certificat i l'estat econòmic són dimensions **diferents** que s'han de relacionar mitjançant decisions i esdeveniments identificables. **Bloquejant del catàleg:** quines combinacions de baixa, deute, pròrroga, pagador de grup i superació permeten accés i certificat. La fitxa no inventa una regla automàtica de bloqueig per impagament.

## 1. Evidència executable i dades disponibles

`LegacySyncService::syncAfterSifSuccess()` recorre relacions `INSCRIPCIO` de la factura i invoca `LegacySyncRepository::syncInscripcioSummary()`. Aquest repositori actualitza **`FACTURA_RELACIONADA` i `OBSERVACIONS`** de la taula llegada `inscripcions` amb número, estat de cobrament i UUID de factura. **No modifica ni verifica matrícula Moodle, accés, baixa o certificat.** A més, `OBSERVACIONS` rep text concatenat: aquest text no és un ledger quantitatiu de saldo ni una prova que l'estat acadèmic s'hagi sincronitzat.

La migració de cicle d'operació defineix `academic_economic_state_event` amb `ENROLLMENT_KEY`, `UUID_OPERATION`, `PARTICIPANT_PARTY_KEY`, `ACTION/RESULT`, estats acadèmic i d'accés **abans/després**, `ECONOMIC_STATE_SNAPSHOT` i hash, `RULE_VERSION`, actor, motiu, `CORRELATION_ID` i `CAUSATION_ID`. **No s'ha acreditat** un servei PHP que ompli aquests events ni un adaptador Moodle al SIF revisat. El camp de snapshot econòmic no substitueix `payment_transaction` ni les assignacions per factura/inscripció.

## 2. Fitxa específica

| Situació | Contracte |
| --- | --- |
| Actors | Alumne, gestió acadèmica, responsable de cobrament/empresa de grup i operador de Moodle/certificació amb permisos propis. |
| Identificadors | `ID_INSC` o `ENROLLMENT_KEY`, persona canònica, curs/edició, ID usuari/aula/matrícula Moodle, `UUID_OPERATION`, factura/es i pagaments reals, pagador i receptor fiscal. No deduir equivalència només de correu o `IDPAG`. |
| Dimensions independents | Inscripció activa/baixa, curs superat, accés actiu/suspès/caducat, certificat pendent/emès, factura pendent/pagada i situació de deute, pròrroga o empresa responsable. Els noms d'estat Moodle i la matriu de transicions **no estan definits pel PHP SIF**. |
| Font econòmica | Consultar factures, `payment_allocation`, `payment_transaction`, crèdits i el ledger individual **proposat** abans de concloure que la quota de la persona està cobrada. Una factura d'empresa pot estar pendent sense que l'alumne en sigui el pagador. |
| Regla de decisió | La matriu d'accés/certificat per combinació d'estats requereix aprovació de gestió acadèmica i cobraments; **no** bloquejar o emetre certificats automàticament a partir de `ESTAT_COBRAMENT` de la factura. |
| Persistència | Event d'estat acadèmic/econòmic amb abans/després, hash de la fotografia de deute i versió de regla, més resultat verificat en llegat/Moodle. La taula existeix com a **SQL**, no com a writer PHP acreditat. |
| Efecte fiscal i monetari | Activar/revocar accés o certificat **no emet factura, `CHARGE` ni `REFUND`**. Baixa o devolució real deriva a UC-72/28/74 amb import/titular originals; l'estat acadèmic no esborra un cobrament anterior. |

### Flux objectiu i alternatives

1. Reunir estat de la inscripció, matrícula i progrés Moodle, certificat existent, responsable del grup i situació de deute **per origen i per inscrit**. Comprovar identitat UC-126 i correspondència d'aula UC-129 abans de canviar cap accés.
2. Previsualitzar **per persona** la decisió segons matriu de regles versionada: baixa sense ingrés; baixa amb ingrés real; grup amb pagador empresa; pròrroga; superació amb deute pendent. Si no hi ha política aprovada, deixar revisió manual, no aplicar un bloqueig arbitrari.
3. Persistir petició/resultat i, quan la matriu existeixi, `academic_economic_state_event` amb snapshot econòmic correlacionat. L'escriptura del SQL és **disseny**, no comportament PHP actual acreditat.
4. Executar canvi d'accés o certificat **només després** de verificar la decisió; desar resultat per destinació i no afirmar èxit perquè s'ha anotat `OBSERVACIONS`.
5. Si hi ha baixa, canvi de curs o reassignació, coordinar UC-71/72/105 i mantenir l'únic ingrés bancari i les atribucions de diners per inscrit, sense crear ni eliminar un `CHARGE`.
6. Conciliar posteriorment SIF/llegat/Moodle/certificació. Si un sistema falla, deixar incidència amb mateixa correlació, repetir **només** l'efecte pendent i no revocar de nou un certificat ja processat.

**Proves concretes pendents:** alumne supera curs però paga l'empresa amb retard; grup pagat amb una inscripció de baixa; pròrroga activa; certificat ja emès; desconnexió Moodle; reintent de baixa; accés correcte però deute llegat erroni; permisos de consulta de factura d'empresa.

### 2.1. Reclamacions llegades amb i sense certificat i accés a regularitzar

**Classificacions que ja utilitza la intranet.** El diccionari `consultesBD_Web` de `Intranet.php` inclou, entre d'altres, `cnsAlumnClaimAlumnNoCertMoros`, `cnsAlumnClaimAlumnCertMoros` i `cnsAlumnClaimEntMoros`. El circuit llegat distingeix, doncs, **alumnat morós amb/sense certificat i entitats amb deute** a les consultes de reclamació. Aquestes claus no especifiquen per si soles el criteri que permet obtenir, suspendre o retirar un certificat, ni demostren que la BD Moodle i la BD fiscal comparteixin una mateixa transició. La política d'accés, expedició i reclamació per estat continua pendent de formalitzar a UC-95/96.

**Una persona amb deute ha de poder regularitzar-lo.** Els procediments de `Consulta - Modifica alumne` indiquen expressament que **no s'ha de bloquejar la possibilitat de pagar a qui és morós**. Quan una factura és d'empresa i cobreix N participants, el canal individual no ha de recuperar una URL pròpia desactivada: oferir informació mínima d'estat i derivar a la via autoritzada del pagador. **Accés al curs** i **accés al pagament** són permisos diferents; la baixa operativa `INSC_CURS` i els camps `reclamat/data_reclamacio` no demostren per si sols deute individual bancari ni un mandat d'anul·lar la factura d'empresa.

**Certificat ja emès i ingrés tardà.** Si existeix certificat, conservar-ne l'evidència abans de decidir qualsevol canvi d'accés o de titulació; no eliminar-lo com a efecte indirecte d'una consulta de morositat. Si una transferència/TPV ha confirmat `UUID_PAYMENT` però l'antic `PAGAMENT` o la matrícula Moodle segueixen pendents, separar: (1) reconciliació fiscal/econòmica UC-53/82, (2) estat d'inscripció i regla de certificat UC-95/124 i (3) actualització efectiva del destí UC-129. **El pagament de factura de grup no és una imputació individual acreditada** si falta el repartiment per `ID_INSC`.

**Baixa acadèmica i baixa econòmica no són la mateixa acció.** Els writers llegats `updClaimDonarBaixa` i `updInscCursBaixaiMoros` són punts d'actualització operativa amb impacte acadèmic; una baixa no crea automàticament una rectificativa, un saldo o una devolució. Un reintent de sincronització de la baixa/certificat conserva `UUID_FACTURA` i `UUID_PAYMENT` originals, consulta el resultat al destí i reexecuta únicament el canvi acadèmic encara pendent.

### 2.2. Proves addicionals de certificació i reclamació (no executades)

| ID | Escenari | Resultat exigible |
| --- | --- | --- |
| AC-124-01 | Alumne morós classificat per `cnsAlumnClaimAlumnCertMoros` | Consultar certificat ja existent i regla aprovada; no revocar-lo automàticament. |
| AC-124-02 | Alumne morós sense certificat amb factura d'empresa pendent | Separar responsabilitat del pagador, estat acadèmic i elegibilitat del certificat. |
| AC-124-03 | Pagament real SIF però `PAGAMENT` llegat segueix 0 | Reparar només el resum/accés pendent, cap segon cobrament. |
| AC-124-04 | Baixa de participant de grup amb ingrés únic de l'empresa | Decisió individual d'accés i econòmica; cap `REFUND` al participant per defecte. |
| AC-124-05 | Estudiant morós vol regularitzar i no té URL individual vàlida | Via de pagament del titular autoritzat disponible; no desbloquejar URL coberta per factura d'empresa. |
| AC-124-06 | Baixa acadèmica confirmada i expedient fiscal encara pendent | Mostrar les dues fases separadament, sense donar l'operació sencera per resolta. |

## 3. UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Gestió acadèmica" as G
actor "Cobraments / empresa pagadora" as C
actor "Moodle / certificació" as M
rectangle "SIF · estat acadèmic i econòmic" {
 usecase "UC-124\nReconciliar accés i certificat" as Main
 usecase "Verificar inscripció, deute i pagador" as Review
 usecase "Decidir accés amb regla versionada" as Decide
 usecase "UC-129\nSincronitzar matrícula Moodle" as Sync
 usecase "Registrar canvi amb evidència" as Audit
}
G --> Main
C --> Review
M --> Sync
Main ..> Review : <<include>>
Main ..> Decide : <<include>>
Main ..> Audit : <<include>>
@enduml
```

## 4. UML de classes: resum llegat real i coordinació pendent

```mermaid
classDiagram
class AcademicEconomicReconciliationService {
 <<DISSENY: no acreditat>>
 +preview(enrollmentKey) decision
 +apply(enrollmentKey,ruleVersion) result
}
class AcademicEconomicStateEventRepository {
 <<DISSENY: SQL definit>>
 +append(db,event) result
}
class AcademicMoodleGateway {
 <<DISSENY: no acreditat>>
 +readEnrollment(person,course) state
 +applyAccessDecision(command) result
}
class LegacySyncService {
 <<PHP existent: només resum fiscal>>
 +syncAfterSifSuccess(legacyDb,relations,uuidFactura,numVisible,estatCobrament) void
}
class LegacySyncRepository {
 <<PHP existent>>
 +syncInscripcioSummary(legacyDb,idInsc,facturaRelacionada,uuidFactura,numVisible,estatCobrament) void
}
AcademicEconomicReconciliationService --> AcademicEconomicStateEventRepository : traça abans/després
AcademicEconomicReconciliationService --> AcademicMoodleGateway : accés i certificat
LegacySyncService --> LegacySyncRepository : FACTURA_RELACIONADA / OBSERVACIONS
```

## 5. UML de seqüència — baixa d'una inscripció de grup (DISSENY/PARCIAL)

```mermaid
sequenceDiagram
autonumber
actor G as Gestió
participant S as AcademicEconomicReconciliationService [DISSENY]
participant Fiscal as Factures i pagaments SIF
participant Events as academic_economic_state_event [SQL]
participant M as AcademicMoodleGateway [DISSENY]
participant L as Inscripcions Prisma llegades
G->>S: Revisar baixa d'un participant amb pagador empresa
S->>Fiscal: Llegir factura grup, titular, CHARGE real i atribució individual
S->>M: Llegir matrícula, accés, superació i certificat
S->>L: Llegir ID_INSC i estat acadèmic
S-->>G: Previsualització sense equiparar deute empresa amb deute alumne
G->>S: Aplicar decisió acadèmica/econòmica aprovada
S->>Events: append(abans,després,hash econòmic,regla,actor)
S->>M: Aplicar canvi d'accés autoritzat
alt Moodle no confirma
 M-->>S: Error
 S-->>G: Incidència de sincronització, sense inventar pagament
else Canvi confirmat
 M-->>S: Estat nou verificat
 S-->>G: Resultat separat de factura i moviment bancari
end
Note over S,M: Coordinador i adaptador Moodle NO acreditats al PHP SIF consultat.
```

## 6. Traçabilitat

[UC-124 original](../06-fitxes-funcionals/uc-124.md) · [UC-129 matrícula Moodle original](../06-fitxes-funcionals/uc-129.md) · [UC-72 baixa](uc-072-registrar-baixa-decisio-economica.md) · [UC-105 reassignació](uc-105-reassignar-repartir-pagament.md) · [UC-95 deute acadèmic original](../06-fitxes-funcionals/uc-095.md) · [LegacySyncService](../../sif/src/Service/LegacySyncService.php) · [LegacySyncRepository](../../sif/src/Repository/LegacySyncRepository.php) · [Esquema d'events](../../sif/database/migrations/2026_09_16_000005_add_operation_lifecycle_tables.sql) · [Traça individual de diners](00-revisio-moviments-inscripcions.md).
