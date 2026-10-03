# UC-011 · Diagrames de seqüència ACTUAL / FINAL

**Data de tall:** 03/10/2026.  
**Branca:** audit/uc-011-2026-10-03.

## 1. ACTUAL · preview CLI

~~~plantuml
@startuml
title UC-011 | ACTUAL | preview
actor Operador
participant "preview-historical-invoice-migration.php" as CLI
participant HistoricalInvoicePayloadBuilder as Builder

Operador -> CLI : --payload-file=fitxer.json
CLI -> CLI : comprovar CLI i SIF_ENV != production
CLI -> CLI : llegir i decodificar JSON
CLI -> Builder : build(input)
alt dades invàlides
  Builder --> CLI : SifException 422
  CLI --> Operador : ok=false, dry_run=true
else payload vàlid
  Builder --> CLI : payload normalitzat
  CLI --> Operador : ok=true, dry_run=true + payload
end
note over CLI,Builder
  No hi ha INSERT ni connexió d'escriptura.
end note
@enduml
~~~

## 2. ACTUAL a main abans de l'auditoria · import i reintent

~~~plantuml
@startuml
title UC-011 | ACTUAL main | import històric
actor Operador
participant "process-historical-invoice-migration.php" as CLI
participant HistoricalInvoiceMigrationService as Service
participant HistoricalInvoicePayloadBuilder as Builder
participant TransactionRunner as Tx
participant HistoricalInvoiceMigrationRepository as Repo
database SIF

Operador -> CLI : payload JSON
CLI -> Builder : indirectament via Service
CLI -> Service : importHistoricalInvoice(input)
Service -> Builder : build(input)
Builder --> Service : payload
Service -> Tx : run(callback)
Tx -> SIF : BEGIN
Service -> Repo : importHistoricalInvoice(db,payload)
Repo -> SIF : SELECT factura WHERE IDEMPOTENCY_KEY=? FOR UPDATE
alt clau existent
  SIF --> Repo : factura anterior
  Repo --> Service : reused=true
  note over Repo
    ACTUAL main: no comparava el payload.
  end note
else clau nova
  Repo -> SIF : INSERT factura HISTORICAL/NO_VERIFACTU
  Repo -> SIF : INSERT factura_linia
  Repo -> SIF : INSERT fact_rels
  opt metadata de document
    Repo -> SIF : INSERT factura_documents
  end
  Repo --> Service : reused=false
end
Tx -> SIF : COMMIT
Service --> CLI : UUID + NUM_VISIBLE + NO_VERIFACTU
CLI --> Operador : resultat JSON
@enduml
~~~

## 3. FINAL implementat a la branca · idempotència per contingut

~~~plantuml
@startuml
title UC-011 | FINAL branca | idempotència segura
actor Operador
participant HistoricalInvoiceMigrationService as Service
participant HistoricalInvoicePayloadBuilder as Builder
participant TransactionRunner as Tx
participant HistoricalInvoiceMigrationRepository as Repo
participant PayloadIdempotencyValidator as Idem
database SIF

Operador -> Service : importHistoricalInvoice(input)
Service -> Builder : build(input)
Builder -> Builder : exigir issue_date original
Builder -> Builder : validar NUM_VISIBLE vs sèrie/any/seq
Builder -> Builder : forçar HISTORICAL + NO_VERIFACTU
Builder --> Service : payload normalitzat
Service -> Tx : run(callback)
Tx -> SIF : BEGIN
Service -> Repo : importHistoricalInvoice(db,payload)
Repo -> SIF : SELECT ... FOR UPDATE
alt clau existent
  SIF --> Repo : factura + IDEMPOTENCY_PAYLOAD_HASH
  Repo -> Idem : assertMatches(payload, storedHash)
  alt mateix payload
    Idem --> Repo : OK
    Repo --> Service : reused=true
  else payload diferent o hash absent
    Idem --> Repo : SifException 409
    Tx -> SIF : ROLLBACK
    Service --> Operador : conflicte; no mutació
  end
else clau nova
  Repo -> Idem : calculateHash(payload)
  Idem --> Repo : SHA-256 canònic
  Repo -> SIF : INSERT factura + IDEMPOTENCY_PAYLOAD_HASH
  Repo -> SIF : INSERT línies + relacions
  opt document metadata
    Repo -> SIF : INSERT factura_documents
  end
  Tx -> SIF : COMMIT
  Service --> Operador : created
end
@enduml
~~~

## 4. OBJECTIU pendent · preflight, auditoria i custòdia

~~~plantuml
@startuml
title UC-011 | OBJECTIU pendent | lot governat
actor ResponsableMigracio
participant Preflight
participant Inventari
participant Custodia
participant HistoricalInvoiceMigrationService as Import
participant OperationalEventRepository as Audit
database SIF
database Storage

ResponsableMigracio -> Inventari : preparar lot per emissor + origen + ID
Inventari -> Preflight : números, dates, receptor, imports, documents
Preflight -> SIF : contrastar factura + fiscal_sequence + emissor
alt conflicte d'identitat/numeració
  Preflight --> ResponsableMigracio : bloquejar i registrar incidència
else compatible
  Preflight -> Import : payload acreditat + correlació
  Import -> SIF : import històric
  Import -> Audit : append resultat [pendent]
  opt original físic disponible
    ResponsableMigracio -> Custodia : bytes originals
    Custodia -> Custodia : calcular SHA-256 real
    Custodia -> Storage : desar immutable
  end
end
@enduml
~~~

## 5. Resultat de l'auditoria de seqüències

La seqüència executable real és CLI → Service → Builder → TransactionRunner → Repository → MySQL. No hi ha callback Redsys, InvoiceService ni cua AEAT en UC-011. Les seqüències d'auditoria, custòdia de bytes i preflight continuen sent objectiu, no execució acreditada.
