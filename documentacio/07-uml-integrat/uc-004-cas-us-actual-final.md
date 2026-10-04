# UC-004 · Diagrama de cas d'ús ACTUAL / FINAL

**Cas d'ús:** UC-004 — Emetre factura abans de cobrar  
**Data de revisió:** 2026-10-04  
**Base auditada:** `main@6c8137ff1652ac89a1a81ad18cf79fc4689b1757`  
**Criteri:** es diferencia l'ACTUAL històric/llegat, l'ACTUAL versionat segur i el FINAL operatiu. L'ACTUAL versionat no inclou encara document SIF per UUID ni `aeat_fields` oficials per entorns qualificats.

## 1. ACTUAL històric — circuit llegat observat

```plantuml
@startuml
left to right direction
actor "Operador intranet" as Op
actor "Pagament posterior" as Pay

rectangle "Intranet llegada" {
  usecase "UC-004 ACTUAL històric\nGenerar factura\nabans de pagar" as UC04A
  usecase "Cercar i seleccionar\ninscripcions" as Sel
  usecase "Seleccionar entitat\nper text" as Ent
  usecase "Calcular import i\nconceptes al navegador" as Calc
  usecase "Inserir factura\na BD llegada" as LegacyIssue
  usecase "Actualitzar\ninscripcions" as Rel
  usecase "Previsualitzar /\ndescarregar PDF temporal" as Pdf
  usecase "Registrar pagament\nposterior al circuit llegat" as LegacyPay
}

Op --> UC04A
UC04A ..> Sel : <<include>>
UC04A ..> Ent : <<include>>
UC04A ..> Calc : <<include>>
UC04A ..> LegacyIssue : <<include>>
UC04A ..> Rel : <<include>>
UC04A ..> Pdf : <<extend>>
Pay --> LegacyPay

note bottom of UC04A
  Històricament escrivia fiscalment a la BD llegada,
  amb imports/receptor preparats des del client.
  Aquest mutador queda retirat amb 410 Gone a la branca auditada.
end note
@enduml
```

## 2. ACTUAL versionat 04/10/2026 — pantalla + bridge segur + SIF

```plantuml
@startuml
left to right direction
actor "Operador autoritzat" as Op
actor "Worker documental [PENDENT]" as DocWorker
actor "Worker fiscal AEAT" as Fiscal
actor "Procés de cobrament posterior" as Pay

rectangle "Intranet" {
  usecase "Seleccionar inscripcions" as Sel
  usecase "Resoldre entitat per ID" as EntId
  usecase "Preview UC-004" as Preview
  usecase "Confirmar UC-004" as Confirm
  usecase "Sessió + permís + CSRF" as Access
  usecase "Bridge servidor-servidor HMAC" as Bridge
}

rectangle "SIF" {
  usecase "Anti-replay + rol d'escriptura" as Auth
  usecase "Rellegir selecció/receptor/imports" as Rebuild
  usecase "Fingerprint optimistic concurrency" as Fingerprint
  usecase "Cobertura INSCRIPCIO/ORIGIN" as Coverage
  usecase "UC-001\nEmetre/reutilitzar factura" as UC01
  usecase "Operational event atòmic" as Audit
  usecase "Document SIF per UUID\n[PENDENT]" as QueueDoc
  usecase "Renderitzar/custodiar document\n[PENDENT]" as ProcessDoc
  usecase "UC-002\nRegistrar cobrament posterior" as UC02
  usecase "UC-009\nRemetre registre AEAT" as UC09
}

Op --> Sel
Sel --> EntId
Op --> Preview
Op --> Confirm
Preview ..> Access : <<include>>
Confirm ..> Access : <<include>>
Access ..> Bridge : <<include>>
Bridge ..> Auth : <<include>>
Auth ..> Rebuild : <<include>>
Preview ..> Fingerprint : <<include>>
Confirm ..> Fingerprint : <<include>>
Confirm ..> Coverage : <<include>>
Confirm ..> UC01 : <<include>>
UC01 ..> Audit : <<include>>
Confirm ..> QueueDoc : <<include>>
DocWorker --> ProcessDoc
QueueDoc ..> ProcessDoc : <<extend>>
Pay --> UC02
Fiscal --> UC09

note right of Coverage
  Preview detecta cobertura existent.
  Confirm conserva el guard UNIQUE transaccional.
  Retry idempotent de la mateixa K reutilitza UUID.
end note

note bottom of ProcessDoc
  Aquesta és arquitectura FINAL.
  Job/worker/snapshot/storage/renderer no són al main actual.
end note
@enduml
```

## 3. FINAL operatiu — condicions encara pendents

El FINAL funcional no requereix reescriure l'emissió: requereix completar i acreditar l'última milla operativa sobre el mateix contracte versionat.

```plantuml
@startuml
left to right direction
actor "Operador autoritzat" as Op
actor "Worker documental desplegat" as Doc
actor "Procés de cobrament" as Pay
actor "AEAT" as AEAT

rectangle "UC-004 FINAL" {
  usecase "Emetre factura real\nabans de cobrar" as Issue
  usecase "Cobertura comercial/fiscal\ntransversal validada" as Cross
  usecase "PDF/QR/XML fiscal\nrenderitzat i custodiat" as Render
  usecase "E2E preproducció\namb evidència" as E2E
  usecase "Cobrament posterior\nsobre el mateix UUID" as Collect
}

Op --> Issue
Issue ..> Cross : <<include>>
Doc --> Render
Issue ..> Render : <<extend>>
Issue ..> E2E : <<include>>
Pay --> Collect
AEAT --> Issue : resposta fiscal posterior

note bottom of Collect
  El cobrament no reemet la factura
  ni crea un segon registre ALTA.
end note
@enduml
```

## 4. Estat de responsabilitats

| Responsabilitat | ACTUAL històric | ACTUAL versionat 04/10 | FINAL / pendent |
| --- | --- | --- | --- |
| Cerca/selecció | HTML + DOM | UI existent; IDs enviats al bridge | E2E real |
| Permís d'emissió | control client parcial | **sessió + rol + CSRF + rol SIF** | evidència preproducció |
| Receptor | text visible | **`entity_id` + snapshot servidor** | validar casuística transversal |
| Imports | DOM/client | **rellegits/recalculats al servidor** | classificador fiscal/comercial transversal |
| Preview | llegat | **server-authoritative + fingerprint** | E2E manipulació DOM |
| Emissió | BD llegada | **`InvoiceBeforePaymentService` + `InvoiceService`** | desplegament |
| Idempotència | no | **key + payload hash** | prova concurrent d'entorn |
| Cobertura UC-004 | no | **preview + UNIQUE transaccional** | cobertura entre altres canals |
| Numeració/cadena/cua | llegat | **SIF amb lock/hash/fiscal_queue** | operació real |
| Auditoria | insuficient | **`ISSUE_INVOICE` + `sif_audit_event` atòmics** | inspecció d'evidència |
| Mutador llegat | executable | **410 Gone** | retirar dependències mortes quan sigui segur |
| Document | temporal/regenerat | **encara pendent al SIF UC-004** | job/worker/snapshot/storage + renderer fiscal + validació |
| Cobrament posterior | circuit separat | serveis SIF existents | E2E sobre mateix UUID |
| AEAT | no SIF | registre/cua SIF | preproducció/acceptació real |

## 5. Relació amb els artefactes detallats

- Fitxa funcional: [uc-004.md](../06-fitxes-funcionals/uc-004.md)
- Síntesi integrada: [uc-004-emetre-factura-abans-cobrar.md](uc-004-emetre-factura-abans-cobrar.md)
- Classes: [uc-004-classes-actual-final.md](uc-004-classes-actual-final.md)
- Seqüències: [uc-004-sequencies-actual-final.md](uc-004-sequencies-actual-final.md)
- Activitats per pàgina/apartat: [uc-004-activitats-actual-final.md](uc-004-activitats-actual-final.md)
- Auditoria/traçabilitat: [uc-004-auditoria-tracabilitat-mancances.md](uc-004-auditoria-tracabilitat-mancances.md)
- Inventari: [uc-004-inventari-artefactes.md](uc-004-inventari-artefactes.md)

**Conclusió del diagrama:** el circuit segur de pantalla → SIF és codi versionat. El document per UUID, l'assembler `aeat_fields` per PREPROD/PROD, el classificador transversal i l'E2E/preproducció continuen pendents.
