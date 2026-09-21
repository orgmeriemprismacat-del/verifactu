# UC-95 · Gestionar l'estat acadèmic quan hi ha deute pendent

**Objectiu original:** l'estat acadèmic i el cobrament **són dimensions separades**; certificat, reclamació i visibilitat depenen de regles explícites. **Estat [DISSENY].** Una persona pot estar matriculada i tenir factura pendent, o haver pagat sense que la matrícula/accés de Moodle s'hagi executat correctament.

## Evidència tècnica concreta

`academic_economic_state_event` està **definida a SQL** amb `ENROLLMENT_KEY`, `UUID_OPERATION`, estats acadèmic i d'accés abans/després, `ECONOMIC_STATE_SNAPSHOT/HASH`, `RULE_VERSION`, actor, correlació i causació. **No s'ha acreditat** a `sif/src` un writer i un motor d'elegibilitat acadèmica que omplin aquesta taula de manera transversal. `PaymentService::registerPayment()` registra ingressos SIF per `UUID_PAYMENT` però no matricula a Moodle ni emet certificats. `LegacySyncRepository::syncInscripcioSummary()` només afegeix un resum fiscal a `OBSERVACIONS` i `FACTURA_RELACIONADA`, i no prova accés/baixa acadèmica.

`payment_allocation` distribueix ingressos entre factures, **no quantifica automàticament pagat per `ID_INSC`** quan un responsable abona un grup: `enrollment_fund_movement` continua sent proposta no implementada.

| Estat observat | Regla pròpia |
| --- | --- |
| Matriculat amb factura pendent de pagament | Mantenir diferenciats matrícula, dret d'accés, venciment, reclamació i import pendent real. La política sobre accés/certificat **ha de ser aprovada**; no declarar baixa automàtica pel simple `ESTAT_COBRAMENT=PENDING`. |
| Factura pagada però no hi ha matrícula Moodle | Conserva `UUID_PAYMENT` real; crear incidència de sincronització UC-129, no una segona factura ni un nou `CHARGE`. |
| Grup amb una factura i un pagament parcial | Mostrar per participant l'estat acadèmic i la **quantia individual acreditada** quan existeixi traça; fins aleshores declarar atribució monetària `UNKNOWN`, no donar tots per pagats ni tots per morosos de manera automàtica. |
| Baixa o bloqueig acadèmic | Exigeix regla/autorització, data i motiu, tractament d'inscripció i accés UC-124/129, i anàlisi de l'obligació fiscal/econòmica independent. La baixa no esborra factura, pagament real ni deute per decret. |
| Certificat i comunicacions | Només afirmar «certificat disponible» o «baixa efectuada» després de verificar la **destinació efectiva**; recordatoris UC-43 llegeixen saldo i pròrroga actuals. |

### Flux funcional i proves

1. El responsable obre `ID_INSC`, curs/edició i operació/factura; consulta SIF, assignacions, pròrrogues, llegat i Moodle per separat amb data de tall i permisos.
2. El classificador **pendent** calcula deute **per factura** a partir del document i pagaments reals i, si hi ha diverses inscripcions, marca expressament les atribucions individuals no acreditades. `A_PAGAR/PAGAMENT` llegats no són prova bancària.
3. Aplica `RULE_VERSION` d'accés, certificat i reclamació **aprovada per l'organització**, no una regla fiscal implícita. Proposa mantenir, revisar o canviar accés/estat amb evidència, actor i motiu.
4. Si hi ha canvi autoritzat, crear event `academic_economic_state_event` (writer pendent), executar adaptador acadèmic/Moodle i confirmar el resultat. Davant fallada entre BDs, conservar `PENDING_RECONCILIATION` **proposat** i UC-129; no mentir mostrant l'estat desitjat com a complet.
5. El pagament posterior de factura existent registra una vegada l'ingrés real, sense generar nou `ALTA` fiscal; per una venda facturable sense factura, classificar emissió UC-04/74 abans de donar per tancada la gestió.

**Proves:** alumne actiu amb venciment prorrogat, grup de tres i pagament parcial del responsable, transferència acreditada amb matrícula Moodle pendent, baixa sense refund, certificat pendent i factura emesa abans de cobrar, retry del sync acadèmic després del commit SIF.

**Pendents:** política escrita d'accés/certificat/reclamació, motor/writer de decisions, atribució de fons per inscrit, comprovació d'operacions en llegat/Moodle, rols i tests de concurrència.

### Incidències llegades de reclamació, deute i consulta acadèmica

**Punts de consulta recuperats.** El procediment `10-procediments-intranet-ecommerce.md` identifica `/alumnes/mostrar-alumne/` com la pantalla que reuneix `INSC_CURS`, les inscripcions, `reclamat`, `data_reclamacio`, `pag_observacions` i la consulta de factura/certificat. Les consultes del constructor `Intranet.php` inclouen `cnsReclamacions`, `cnsCursosRecordarPag`, `cnsAlumnesRecordarPag`, `cnsCursosClaimBaixes`, `cnsAlumnClaimPag`, `cnsAlumnClaimEntMoros`, `cnsAlumnClaimAlumnNoCertMoros`, `cnsAlumnClaimAlumnCertMoros` i `cnsEntMoros`; les actualitzacions inclouen `updPrimeraReclamacio`, `updClaimDonarBaixa`, `updClaimRecPag`, `updInscCursBaixaiMoros` i `updReclamatDefaulter`. Són **vies de gestió del llegat**, no una matriu executada de drets sobre accés i certificats dins del SIF.

**Pagar no és una acció que s'hagi de bloquejar per morositat.** El procediment de Prisma estableix que l'alumne morós **no s'ha de bloquejar per pagar**, perquè interessa que pugui regularitzar el deute; si la factura individual ha quedat coberta per una d'empresa, s'ha d'oferir la URL que correspongui al pagador, **no** reactivar un pagament individual indegut. Bloquejar, mantenir o recuperar **l'accés acadèmic** és una decisió diferent segons política aprovada, no una conseqüència automàtica de la disponibilitat del botó de pagament. `reclamat/data_reclamacio` i les observacions són seguiment administratiu, **no** prova d'ingrés real ni ordre per rectificar la factura.

**Corregir la discrepància per dimensió.** Una inscripció en `INSC_CURS` de baixa pot coexistir amb una factura encara exigible o amb una devolució real pendent; un curs superat pot tenir certificat subjecte a una política encara no definida, encara que la factura sigui d'empresa. Una transferència confirmada al SIF amb `web.inscripcions.PAGAMENT` antic i accés Moodle absent requereix dues verificacions diferents: UC-47/53 per resum de pagament i UC-129 per matrícula/accés. Ni una nota a `OBSERVACIONS` ni un pagament de grup assignat **només a factura** demostren la quota individual d'un participant.

### Proves complementàries sobre morositat i accés (no executades)

| ID | Escenari | Resultat exigible |
| --- | --- | --- |
| ED-95-01 | Inscripció morosa amb deute exigible i enllaç legítim del pagador | Possibilitat de regularitzar el pagament, sense habilitar una URL individual ja coberta per empresa. |
| ED-95-02 | `INSC_CURS` és «baixa» però factura original segueix pendent | Estat acadèmic i deute mostrats separadament; cap rectificativa automàtica per la baixa. |
| ED-95-03 | Alumne supera curs mentre l'empresa pagadora té una factura pendent | Política de certificat/acreditació individual revisada, no imputar-li per defecte el deute de l'empresa. |
| ED-95-04 | Pagament SIF real amb `PAGAMENT` llegat antic i matrícula Moodle absent | Reparar per destinació, sense segon `CHARGE` ni nova factura. |
| ED-95-05 | Marcar `reclamat=1` sense prova bancària | No modificar `ESTAT_COBRAMENT` ni calcular un moviment real d'ingrés. |

## UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Gestió acadèmica" as G
actor "Alumne" as A
rectangle "SIF + Prisma/Moodle · estat acadèmic" {
 usecase "UC-95\nGestionar matrícula amb deute" as Main
 usecase "Consultar import/estat acadèmic separats" as Inspect
 usecase "Aplicar regla versionada accés/certificat" as Rules
 usecase "UC-129\nVerificar matrícula/accés Moodle" as Sync
 usecase "UC-43\nEnviar recordatori vigent" as Reminder
}
G --> Main
A --> Inspect
Main ..> Inspect : <<include>>
Main ..> Rules : <<include>>
Sync ..> Main : <<extend>> (canvi acadèmic autoritzat)
Reminder ..> Main : <<extend>> (deute i canal legítims)
@enduml
```

## UML de classes

```mermaid
classDiagram
class AcademicDebtStateService {
 <<DISSENY: no acreditat>>
 +inspect(enrollmentKey) combinedState
 +applyAuthorizedRule(enrollmentKey,ruleVersion) result
}
class AcademicEconomicStateEventRepository {
 <<DISSENY: taula SQL definida>>
 +append(db,stateTransition) result
}
class PaymentService {
 <<PHP existent: registra pagaments de factura>>
 +registerPayment(payload) array
}
class MoodleEnrollmentGateway {
 <<DISSENY: prova efectiva pendent>>
 +readAccess(enrollmentKey) state
 +applyApprovedChange(enrollmentKey,decision) result
}
class EnrollmentFundMovementRepository {
 <<PROPOSTA: imports per inscripció>>
 +balanceByEnrollment(enrollmentKey) money
}
AcademicDebtStateService --> AcademicEconomicStateEventRepository : decisió i prova
AcademicDebtStateService --> MoodleEnrollmentGateway : accés efectiu
AcademicDebtStateService ..> PaymentService : fonts econòmiques SIF
AcademicDebtStateService ..> EnrollmentFundMovementRepository : atribució individual no implementada
```

## UML de seqüència — pagament real però accés Moodle absent

```mermaid
sequenceDiagram
actor G as Gestió
participant S as AcademicDebtStateService [DISSENY]
participant P as SIF factura/payment_allocation
participant M as MoodleEnrollmentGateway [DISSENY]
participant E as academic_economic_state_event [SQL]
G->>S: Consultar matrícula i deute d'ID_INSC
S->>P: Llegir factura i pagaments reals
S->>M: Llegir estat d'accés acadèmic
alt Factura cobrada i accés pendent
 P-->>S: UUID_PAYMENT acreditat
 M-->>S: Alumne sense accés
 S->>E: Registrar divergència i causació [writer pendent]
 S->>M: Reintentar només matrícula autoritzada
 S-->>G: Pagament existent, accés en verificació
else Factura pendent i accés vigent
 S->>E: Registrar estat diferenciat per regla [pendent]
 S-->>G: Matrícula i deute separats; no baixa automàtica
end
Note over S,M: La consulta/actuació Moodle no està acreditada com a servei SIF complet.
```

## Traçabilitat

[UC-95 original](../06-fitxes-funcionals/uc-095.md) · [UC-96 pròrroga original](../06-fitxes-funcionals/uc-096.md) · [UC-124 accés/baixa](uc-124-reconciliar-acces-certificat-baixa-deute.md) · [UC-129 Moodle](uc-129-reconciliar-prisma-moodle-matricules.md) · [UC-43 avisos](uc-043-gestionar-notificacions-recordatoris.md) · [PaymentService](../../sif/src/Service/PaymentService.php) · [LegacySyncRepository](../../sif/src/Repository/LegacySyncRepository.php) · [SQL estat acadèmic/econòmic](../../sif/database/migrations/2026_09_16_000005_add_operation_lifecycle_tables.sql) · [Fons per inscrit](00-revisio-moviments-inscripcions.md).
