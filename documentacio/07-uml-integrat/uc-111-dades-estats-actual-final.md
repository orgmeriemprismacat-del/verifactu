# UC-111 · Model de dades i diagrames d'estat ACTUAL / FINAL

**Data de tall:** 29/09/2026.  
**Branca:** \`feat/uc-111-termini-i-auditoria-2026-09-22\`.  
**Objectiu:** completar el paquet documental UC-111 amb la vista que faltava com a artefacte propi: **persistència + estats**. Aquest document no substitueix [classes](uc-111-classes-actual-final.md), [seqüències](uc-111-sequencies-actual-final.md), [activitats](uc-111-activitats-actual-final.md) ni [diagrames 1:1 per acció](uc-111-diagrames-per-accio.md).

> **Regla d'auditoria:** un estat/model present a la branca no implica migració aplicada, prova MySQL executada ni desplegament.

## 1. ACTUAL · dades legacy observables

El llegat distribueix la informació entre matrícula, sol·licitud/validació de docent novell i promocions. No existeix un ledger canònic únic que representi reserva, consum parcial, transferència, saldo derivat i devolució JASOM.

\`\`\`plantuml
@startuml
title UC-111 | ACTUAL | dades legacy observables
hide methods
hide stereotypes

entity inscripcions {
  * ID
  --
  IDPAG
  CURS
  DNI
  A_PAGAR
  PAGAMENT
}

entity recent_titulat {
  * ID_INSC
  --
  VALIDAT
}

entity promocions {
  * identificador legacy
  --
  DNI
  CURS
  USED
  percentatge
  tipus_calcul
  acumulable
  vigencia
}

inscripcions ||--o| recent_titulat : ID = ID_INSC
inscripcions ..> promocions : generació/ús legacy\nno ledger canònic

note right of recent_titulat
  VALIDAT:
  0 pendent
  1 aprovat
  2 denegat
end note

note bottom of promocions
  Consulta legacy no acredita
  reserva/consum fiscal atòmic,
  saldo parcial ni lineage.
end note
@enduml
\`\`\`

## 2. FINAL/branca · model de dades canònic UC-111

\`\`\`plantuml
@startuml
title UC-111 | FINAL/branca | model de dades i procedència
hide methods
skinparam linetype ortho

entity commercial_entitlement as entitlement {
  * UUID_ENTITLEMENT
  --
  HOLDER_PARTY_KEY
  STATUS
  CODE_HASH
  EXPIRES_AT
}

entity novice_promotion_grant as grant {
  * UUID_ENTITLEMENT
  --
  ORIGIN_UUID_OPERATION
  UUID_VALIDATION
  UUID_FACTURA
  ORIGINAL_CASH_AMOUNT
  AVAILABLE_AMOUNT
}

entity novice_promotion_application as app {
  * UUID_APPLICATION
  --
  UUID_ENTITLEMENT
  UUID_DESTINATION_OPERATION
  UUID_DESTINATION_FACTURA
  AMOUNT
  STATUS
  REASON_CODE
}

entity novice_promotion_application_transfer as transfer {
  * UUID_TRANSFER
  --
  ROOT_UUID_ENTITLEMENT
  UUID_ORIGINAL_APPLICATION
  UUID_DERIVED_APPLICATION
  PREVIOUS_UUID_TRANSFER
  FROM_UUID_OPERATION
  TO_UUID_OPERATION
  UUID_RECTIFICATIVE_FACTURA
  UUID_DESTINATION_FACTURA
  AMOUNT
  STATUS
  CLOSE_REASON
}

entity novice_promotion_derived_balance as balance {
  * UUID_DERIVED_BALANCE
  --
  ROOT_UUID_ENTITLEMENT
  PARENT_UUID_DERIVED_BALANCE
  SOURCE_UUID_APPLICATION
  SOURCE_UUID_DERIVED_APPLICATION
  SOURCE_UUID_TRANSFER
  UUID_RECTIFICATIVE_FACTURA
  PROMOTIONAL_ORIGIN_AMOUNT
  AVAILABLE_PROMOTIONAL_AMOUNT
  STATUS
  ISSUED_AT
  EXPIRES_AT
}

entity novice_promotion_derived_application as dapp {
  * UUID_DERIVED_APPLICATION
  --
  UUID_DERIVED_BALANCE
  ROOT_UUID_ENTITLEMENT
  UUID_DESTINATION_OPERATION
  UUID_DESTINATION_FACTURA
  AMOUNT
  STATUS
  RESERVATION_EXPIRES_AT
  REASON_CODE
}

entity novice_promotion_root_refund_review as refundreview {
  * UUID_REVIEW
  --
  ROOT_UUID_ENTITLEMENT
  ORIGIN_UUID_OPERATION
  PLAN_HASH
  STATUS
  APPROVAL_DECISION_ID
  ORIGIN_REFUND_EVIDENCE_ID
  RECOVERY_COMPLETED_AT
}

entity novice_promotion_root_refund_recovery as recovery {
  * UUID_RECOVERY
  --
  UUID_REVIEW
  ROOT_UUID_ENTITLEMENT
  LOGICAL_APPLICATION_ID
  SOURCE_KIND
  SOURCE_UUID
  UUID_DESTINATION_OPERATION
  AMOUNT
  STATUS
}

entity commercial_entitlement_event as event {
  * UUID_EVENT
  --
  UUID_ENTITLEMENT
  ACTION
  RESULT
  UUID_OPERATION
  CORRELATION_ID
  CAUSATION_ID
  REASON_CODE
}

entitlement ||--|| grant : mateix UUID dret arrel
grant ||--o{ app : consum original
grant ||--o{ transfer : lineage arrel
grant ||--o{ balance : descendents
balance ||--o{ dapp : N consums parcials
balance ||--o{ balance : PARENT_UUID_DERIVED_BALANCE
app ||..o| transfer : primer canvi
dapp ||..o| transfer : canvi des de saldo derivat
transfer ||..o| transfer : PREVIOUS_UUID_TRANSFER
app ||..o| balance : baixa original
dapp ||..o| balance : baixa derivada
transfer ||..o| balance : baixa curs traspassat
grant ||--o{ refundreview : devolució origen
refundreview ||--o{ recovery : usos vius a resoldre
entitlement ||--o{ event : auditoria

note bottom of transfer
  Un transfer CONFIRMED és
  atribució actual del MATEIX valor,
  no un segon consum.
end note

note bottom of balance
  El saldo derivat és PROMOCIONAL.
  No és credit_balance ni
  payment_transaction.
end note
@enduml
\`\`\`

## 3. Estat del dret novell arrel i freeze per refund

\`\`\`plantuml
@startuml
title UC-111 | commercial_entitlement | dret novell arrel
[*] --> ACTIVE : concessió + activació segura

ACTIVE --> REFUND_REVIEW : openReview()\nfreeze comercial
REFUND_REVIEW --> ACTIVE : review REJECTED/CANCELLED\nunhold
REFUND_REVIEW --> CANCELLED : refund JASOM real confirmat\n+ execute consequences

ACTIVE --> CANCELLED : altres causes autoritzades\nfora d'aquest subflux
ACTIVE --> EXPIRED : caducitat si política general ho aplica

note right of REFUND_REVIEW
  No és cancel·lació històrica.
  Serveix perquè reserve/transfer
  fallin tancat durant el review.
end note
@enduml
\`\`\`

## 4. Estat del consum original

\`\`\`plantuml
@startuml
title UC-111 | novice_promotion_application | consum del dret original
[*] --> RESERVED : reserve()\nAVAILABLE_AMOUNT -= AMOUNT
RESERVED --> APPLIED : factura/cash final concordants
RESERVED --> RELEASED : sense intent/factura\n+ fracàs/expiració acreditats

APPLIED --> REVERSED : canvi confirmat\nREASON=TRANSFERRED_TO_COURSE
APPLIED --> REVERSED : baixa aprovada\nREASON=CONVERTED_TO_DERIVED

note right of REVERSED
  REVERSED és història de lineage.
  El valor continua al successor
  transfer o dret derivat.
end note
@enduml
\`\`\`

## 5. Estat del saldo derivat i dels seus consums

\`\`\`plantuml
@startuml
title UC-111 | saldo derivat de baixa
[*] --> PENDING_FISCAL_REVIEW : review de baixa
PENDING_FISCAL_REVIEW --> REJECTED : decisió denegada\nmai emès
PENDING_FISCAL_REVIEW --> ACTIVE : aprovació independent\n+ rectificativa/cash/JASOM revalidats
ACTIVE --> EXPIRED : caducitat pròpia
ACTIVE --> CANCELLED : refund JASOM arrel\n/ altra causa autoritzada
EXPIRED --> CANCELLED : refund JASOM arrel

note right of ACTIVE
  AVAILABLE_PROMOTIONAL_AMOUNT
  pot alimentar N aplicacions.
  Cada dret derivat conserva
  la seva pròpia vigència.
end note
@enduml
\`\`\`

\`\`\`plantuml
@startuml
title UC-111 | novice_promotion_derived_application
[*] --> RESERVED : reserve()\nderived AVAILABLE -= AMOUNT
RESERVED --> APPLIED : factura/cash final
RESERVED --> RELEASED : release segur
APPLIED --> TRANSFERRED : canvi de curs confirmat
APPLIED --> CONVERTED_TO_DERIVED : baixa aprovada
APPLIED --> CANCELLED : tancament autoritzat\nsense successor
@enduml
\`\`\`

**Implementació associada a `CONVERTED_TO_DERIVED`:** la [migració 000028](../../sif/database/migrations/2026_09_29_000028_close_derived_application_into_child_balance.sql) exigeix `CLOSED_AT` i `REASON_CODE=CONVERTED_TO_DERIVED` quan una aplicació derivada deixa de ser exposició activa. `NovicePromotionDerivedApplicationCancellationReviewService` crea el dret fill `PENDING_FISCAL_REVIEW` amb `PARENT_UUID_DERIVED_BALANCE` + `SOURCE_UUID_DERIVED_APPLICATION`; `NovicePromotionDerivedApplicationCancellationActivationService` revalida aprovació, rectificativa, cash i JASOM i fa atòmicament `APPLIED → CONVERTED_TO_DERIVED` + fill `PENDING → ACTIVE`, sense retornar l'import al pare.

**Transferències successives i baixa:** `NovicePromotionTransferredDestinationCancellationReviewService::stageCurrentTransferredDestinationReview()` i `NovicePromotionTransferredCancellationActivationService` admeten l'últim `transfer.CONFIRMED` encara que provingui d'un `PREVIOUS_UUID_TRANSFER` o d'una `derived_application`. Abans de crear el saldo derivat, resolen el dret que la cadena transporta: parent NULL si és el dret JASOM original, o `PARENT_UUID_DERIVED_BALANCE=<right derivat>` si el transfer prové d'un saldo de baixa. Un transfer amb successor actiu no es pot donar de baixa com si encara fos el destí actual.
## 6. Estat dels traspassos de curs

\`\`\`plantuml
@startuml
title UC-111 | novice_promotion_application_transfer
[*] --> PENDING_FISCAL_REVIEW : stage review
PENDING_FISCAL_REVIEW --> CONFIRMED : aprovació + rectificativa\n+ factura nova + residual conciliat
PENDING_FISCAL_REVIEW --> CANCELLED : review cancel·lat/rebutjat\nsi workflow ho documenta

CONFIRMED --> CANCELLED : successor confirmat\nCLOSE_REASON=TRANSFERRED_TO_COURSE
CONFIRMED --> CANCELLED : baixa del curs actual\nCLOSE_REASON=CONVERTED_TO_DERIVED

note right of CONFIRMED
  És l'exposició activa actual
  fins que hi ha successor o baixa.
end note
@enduml
\`\`\`

## 7. Estat del refund JASOM i recovery

La branca conté dues migracions històriques amb prefix \`000025\` que redefinien el mateix CHECK. El `MigrationRunner` les distingeix perquè registra el **nom complet del fitxer** (`MIGRATION_FILE`) al ledger; per tant no hi ha col·lisió de clau entre els dos noms. La [migració correctiva 000027](../../sif/database/migrations/2026_09_27_000027_reconcile_novice_root_refund_states.sql) resol el conflicte real dels constraints i conserva la unió coherent d'estats sense reescriure l'historial.

\`\`\`plantuml
@startuml
title UC-111 | novice_promotion_root_refund_review
[*] --> PENDING_APPROVAL : openReview + plan hash + freeze

PENDING_APPROVAL --> REJECTED : decisió denegada
PENDING_APPROVAL --> CANCELLED : review cancel·lat
PENDING_APPROVAL --> APPROVED_WAITING_REFUND : estat de handoff previst
PENDING_APPROVAL --> EXECUTED : implementació actual\naprovació + refund origen confirmat

APPROVED_WAITING_REFUND --> EXECUTED : quan el refund origen\nestigui acreditat
EXECUTED --> RECOVERY_RESOLVED : tots els recovery items resolts

note right of APPROVED_WAITING_REFUND
  Estat admès pel model final després de 000027.
  La branca actual no té encara un servei
  separat que faci explícit aquest handoff;
  l'ExecutionService pot anar directament
  de PENDING_APPROVAL a EXECUTED quan ja
  disposa d'aprovació i refund confirmat.
end note
@enduml
\`\`\`

\`\`\`plantuml
@startuml
title UC-111 | novice_promotion_root_refund_recovery
[*] --> PENDING_RECOVERY : ús promocional actual\nencara aplicat
PENDING_RECOVERY --> RECOVERED : recuperació externa verificada
PENDING_RECOVERY --> WAIVED : waiver autoritzat + evidència
PENDING_RECOVERY --> CANCELLED : cancel·lació acreditada
@enduml
\`\`\`

## 8. Projecció SQL → graf lògic de procedència

\`\`\`plantuml
@startuml
title UC-111 | SQL ledger -> lineage lògic
left to right direction

rectangle "SQL real" {
  [novice_promotion_application] as OA
  [novice_promotion_derived_application] as DA
  [novice_promotion_application_transfer] as TR
  [novice_promotion_derived_balance] as DB
}

rectangle "NovicePromotionLineageProjectionPolicy" as P
rectangle "Graf lògic" {
  [ACTIVE] as ACTIVE
  [RESERVED] as RESERVED
  [RELEASED/CANCELLED] as CLOSED
  [REPLACED_BY_TRANSFER] as RBT
  [REPLACED_BY_DERIVED] as RBD
}

OA --> P
DA --> P
TR --> P
DB --> P
P --> ACTIVE
P --> RESERVED
P --> CLOSED
P --> RBT
P --> RBD

note bottom of P
  STATUS SQL + REASON_CODE/CLOSE_REASON
  decideixen si una fila és exposició viva
  o predecessor històric.
  PENDING_FISCAL_REVIEW falla tancat.
end note
@enduml
\`\`\`

## 9. Regles d'integritat documental

1. **Cap predecessor substituït compta dues vegades.** \`TRANSFERRED_TO_COURSE\` i \`CONVERTED_TO_DERIVED\` projecten el predecessor com a història.
2. **Una reserva ja ha debitat saldo.** Confirmar-la no torna a restar import; alliberar-la només restaura abans de qualsevol evidència fiscal/intent ambigu.
3. **Promoció ≠ diners.** \`payment_transaction\` i \`credit_balance\` no són els ledgers dels drets promocionals.
4. **PENDING no és dret actiu.** Reviews fiscals/comercials no poden gastar-se abans de decisió independent.
5. **Refund JASOM congela abans d'executar.** La projecció completa i el fingerprint han de romandre estables fins a l'evidència real del refund.
6. **Recovery és workflow, no CHARGE automàtic.** Cada item necessita resolució i evidència pròpies.
7. **MySQL no verificat.** Les migracions 000008–000027 no s'han executat en aquesta auditoria per decisió expressa; la consistència aquí és estàtica/documental.

## 10. Navegació

[Fitxa principal](../06-fitxes-funcionals/uc-111.md) · [fitxes A111-01…12](../06-fitxes-funcionals/uc-111-accions.md) · [casos d'ús](uc-111-casos-us-actual-final.md) · [classes](uc-111-classes-actual-final.md) · [seqüències](uc-111-sequencies-actual-final.md) · [activitats](uc-111-activitats-actual-final.md) · [48 UML per acció](uc-111-diagrames-per-accio.md) · [traçabilitat](uc-111-tracabilitat-implementacio.md)
