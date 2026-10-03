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

## 3. ACTUAL · pàgina llegada com a origen i tall de mutacions

~~~plantuml
@startuml
title UC-011 | ACTUAL upstream | alumnes-factura i guard SIF
actor "Usuari intranet" as User
participant "alumnes-factura.js" as JS
participant "sifFactures.php" as SifQuery
participant "consultaUsuarisFacturaRelacionada.php" as LegacySearch
participant "guardarDadesFactura_Factures.php" as Save
participant "anularFactura_Factures.php" as Cancel
participant "descarregaFactura.php" as Download
participant SifLegacyInvoiceMutationGuard as Guard
participant SifInternalApiClient as Client
participant Intranet
database "web.factures" as Legacy
database SIF

User -> JS : cercar factura
JS -> SifQuery : search(criteria)
SifQuery -> Client : searchInvoices(actor,roles,...)
Client -> SIF : consulta factura/fact_rels
alt factura SIF localitzada
  SIF --> JS : resultat SIF
else sense resultat / fallback permès
  JS -> LegacySearch : GET dni/email/factRel/factNum
  LegacySearch -> Intranet : buscarUsuaris_Factures(...)
  Intranet -> Legacy : SELECT llegat
  Legacy --> JS : resultat llegat
end

group Mutació o regeneració de factura llegada
  User -> JS : guardar / anul·lar / descarregar
  alt guardar
    JS -> Save : POST
    Save -> Guard : assertLegacyMutationAllowed(id)
  else anul·lar
    JS -> Cancel : POST
    Cancel -> Guard : assertLegacyMutationAllowed(id)
  else descarregar
    JS -> Download : POST
    Download -> Guard : assertLegacyRelationAllowed(relació)
  end
  Guard -> Client : searchInvoices(HISTORIC_WEB_FACTURES/source_id o factura_relacionada)
  Client -> SIF : consulta
  alt ja governada pel SIF + flags actius
    SIF --> Guard : match
    Guard --> JS : 409 bloquejat
  else encara no governada / guard desactivat
    Guard --> Save : permet continuar
    Guard --> Cancel : permet continuar
    Guard --> Download : permet continuar
    Save -> Intranet : guardarDadesFactura_Factures(...)
    Cancel -> Intranet : anularFactura(...)
    Download -> Intranet : generaFactura(id,true)
    Intranet -> Legacy : llegir/modificar dades
    note over Download,Legacy
      El PDF es regenera des de dades vives.
      No acredita els bytes originals emesos.
    end note
  end
end
@enduml
~~~

Aquesta seqüència no és un executor d'UC-011: és el **sistema origen**. El guard aprofita les relacions que crea la migració per impedir que, després del cut-over, es continuï modificant el llegat; la protecció depèn de `SIF_BLOCK_LEGACY_INVOICE_MUTATIONS` i `SIF_UC007_QUERY_ENABLED`.

## 4. FINAL implementat a la branca · idempotència i fidelitat històrica

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
Builder -> Builder : validar dates, billing/totals/línies/rels
Builder -> Builder : normalitzar emissor + camps fiscals
Builder --> Service : payload normalitzat
Service -> Tx : run(callback)
Tx -> SIF : BEGIN
Service -> Repo : importHistoricalInvoice(db,payload)
Repo -> SIF : SELECT ... FOR UPDATE
alt clau existent
  SIF --> Repo : factura + IDEMPOTENCY_PAYLOAD_HASH
  Repo -> Repo : projectar dades materialment persistides
  Repo -> Idem : assertMatches(materialPayload, storedHash)
  alt mateixes dades materials
    Idem --> Repo : OK
    Repo --> Service : reused=true
    note over Repo
      Aliases equivalents no creen
      un fals conflicte idempotent.
    end note
  else dades persistides diferents o hash absent
    Idem --> Repo : SifException 409
    Tx -> SIF : ROLLBACK
    Service --> Operador : conflicte; no mutació
  end
else clau nova
  Repo -> Repo : projectar dades materialment persistides
  Repo -> Idem : calculateHash(materialPayload)
  Idem --> Repo : SHA-256 canònic
  Repo -> SIF : INSERT factura + hash + emissor/descripció/fiscalitat
  Repo -> SIF : INSERT línies + fiscalitat de línia + relacions
  opt document metadata
    Repo -> SIF : INSERT factura_documents
  end
  Tx -> SIF : COMMIT
  Service --> Operador : created
end
@enduml
~~~

## 5. OBJECTIU pendent · preflight, auditoria i custòdia

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

## 6. Resultat de l'auditoria de seqüències

La seqüència d'importació executable real és CLI → Service → Builder → TransactionRunner → Repository → MySQL. En paral·lel, la pàgina llegada `alumnes-factura` continua sent la superfície upstream de consulta/mutació fins al cut-over, amb un guard SIF ja implementat però dependent de flags. No hi ha callback Redsys, InvoiceService ni cua AEAT en UC-011. Auditoria d'operador, custòdia de bytes originals i preflight multiemissor/numeració continuen sent objectiu, no execució acreditada.
