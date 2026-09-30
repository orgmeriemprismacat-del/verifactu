# UC-004 · Diagrama de cas d'ús ACTUAL / FINAL

**Cas d'ús:** UC-004 — Emetre factura abans de cobrar  
**Data de revisió:** 2026-09-29  
**Objectiu:** separar explícitament el comportament de la pantalla llegada del contracte FINAL SIF.

## 1. ACTUAL — pantalla llegada

```plantuml
@startuml
left to right direction
actor "Operador intranet" as Op
actor "Pagament posterior" as Pay

rectangle "Intranet llegada" {
  usecase "UC-004 ACTUAL\nGenerar factura\nabans de pagar" as UC04A
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
  No passa per InvoiceBeforePaymentService.
  No hi ha idempotència fiscal SIF al circuit actual.
end note
@enduml
```

## 2. FINAL — contracte UC-004 sobre SIF

```plantuml
@startuml
left to right direction
actor "Operador autoritzat" as Op
actor "Procés / operador de cobrament" as Pay
actor "Worker fiscal" as Fiscal

rectangle "Intranet + SIF" {
  usecase "UC-004 FINAL\nEmetre factura\nabans de cobrar" as UC04
  usecase "Autoritzar actor\nal servidor" as Auth
  usecase "Reconstruir selecció\ni imports al servidor" as Rebuild
  usecase "Resoldre receptor\nper ID intern" as Billing
  usecase "Comprovar cobertura\nd'inscripcions" as Coverage
  usecase "UC-001\nEmetre o reutilitzar\nfactura SIF" as UC01
  usecase "Generar/custodiar\ndocument per UUID" as Doc
  usecase "UC-002\nRegistrar cobrament\nposterior" as UC02
  usecase "UC-009\nRemetre registre AEAT" as UC09
}

Op --> UC04
UC04 ..> Auth : <<include>>
UC04 ..> Rebuild : <<include>>
UC04 ..> Billing : <<include>>
UC04 ..> Coverage : <<include>>
UC04 ..> UC01 : <<include>>
UC04 ..> Doc : <<extend>>
Pay --> UC02
Fiscal --> UC09

note right of Coverage
  A la branca d'auditoria:
  - relations INSCRIPCIO/ORIGIN obligatòries
  - duplicats dins petició rebutjats
  - claim transaccional a invoice_before_payment_coverage
end note

note bottom of UC02
  El cobrament usa el mateix UUID_FACTURA.
  No reemet UC-004.
end note
@enduml
```

## 3. Estat de cada responsabilitat

| Responsabilitat | ACTUAL | FINAL a la branca |
| --- | --- | --- |
| Cerca/selecció | implementada al llegat | **reconstrucció per IDs implementada a la branca; pendent UI** |
| Permís visualització | servidor | existent |
| Permís d'emissió | UI/client parcial | backend específic pendent |
| Receptor | text de RAO | **entityId + snapshot implementats a la branca; pendent UI** |
| Imports | DOM/client | **total/línies servidor implementats amb `A_PAGAR`; pendent classificador transversal i UI** |
| Cobertura entre operacions UC-004 | sense guard SIF | **builder + claim transaccional específic implementats** |
| Emissió | BD llegada | `InvoiceBeforePaymentService` + SIF implementats |
| Idempotència | no | implementada per key + payload hash |
| Numeració | últim + 1 | `FiscalSequenceRepository` amb lock |
| Registre/hash/cua | no SIF | implementats |
| Document | PDF temporal llegat | integració UUID/snapshot pendent |
| Cobrament posterior | circuit separat llegat | servei SIF existent; integració pantalla/canal pendent |

## 4. Relació amb la resta d'artefactes

- Fitxa funcional: [uc-004.md](../06-fitxes-funcionals/uc-004.md)
- Síntesi integrada: [uc-004-emetre-factura-abans-cobrar.md](uc-004-emetre-factura-abans-cobrar.md)
- Classes: [uc-004-classes-actual-final.md](uc-004-classes-actual-final.md)
- Seqüències: [uc-004-sequencies-actual-final.md](uc-004-sequencies-actual-final.md)
- Activitats: [uc-004-activitats-actual-final.md](uc-004-activitats-actual-final.md)
- Auditoria/traçabilitat: [uc-004-auditoria-tracabilitat-mancances.md](uc-004-auditoria-tracabilitat-mancances.md)
- Inventari: [uc-004-inventari-artefactes.md](uc-004-inventari-artefactes.md)
