# Auditoria detallada UC-011 · Importar factura històrica

**Data:** 03/10/2026  
**Repositori:** orgmeriemprismacat-del/verifactu  
**Base inicial:** main  
**Branca de correcció:** audit/uc-011-2026-10-03

## 1. Abast revisat

S'han contrastat la fitxa funcional, l'UML integrat, scripts CLI, PHP de servei/builder/repository, proves unitàries i d'integració, esquema d'idempotència i restriccions documentades sobre numeració, documents i auditoria.

No s'ha localitzat codi JavaScript ni una pàgina d'intranet dedicada a executar UC-011. El canal executable localitzat és CLI i refusa production.

## 2. Artefactes existents abans de l'auditoria

- documentacio/06-fitxes-funcionals/uc-011.md.
- documentacio/07-uml-integrat/uc-011-importar-factura-historica.md.
- sif/scripts/preview-historical-invoice-migration.php.
- sif/scripts/process-historical-invoice-migration.php.
- HistoricalInvoiceMigrationService, HistoricalInvoicePayloadBuilder i HistoricalInvoiceMigrationRepository.
- HistoricalInvoicePayloadBuilderTest.
- HistoricalInvoiceMigrationServiceTest.
- Tests de frontera dels dos scripts.

Faltaven peces separades de classes ACTUAL/FINAL, seqüències ACTUAL/FINAL, activitats ACTUAL/FINAL i traçabilitat específica.

## 3. Troballes de codi

### A-011-01 · Reintent idempotent no comparava contingut — corregit a la branca

Abans: findByIdempotencyKey retornava la fila i existingResult la reutilitzava. Un mateix idempotency_key amb receptor, total, línies o document diferents podia aparèixer com a èxit reutilitzat.

Correcció: s'utilitza PayloadIdempotencyValidatorInterface, es desa el hash canònic a factura.IDEMPOTENCY_PAYLOAD_HASH i assertMatches bloqueja amb 409 qualsevol payload diferent. Un registre antic sense hash també falla tancat.

### A-011-02 · Data històrica podia convertir-se en data de migració — corregit

Abans: issue_date tenia default date('Y-m-d H:i:s').

Correcció: issue_date/data_emissio és obligatòria. Una migració no pot inventar silenciosament la data d'emissió original.

### A-011-03 · Components de numeració podien contradir NUM_VISIBLE — corregit

Abans: series, year i num_seq podien sobreescriure els valors derivats.

Correcció: qualsevol discrepància respecte el número visible produeix validació 422.

### A-011-04 · Estat de factura podia ser sobreescrit — corregit

Abans: invoice_status/estat_factura podia arribar de l'entrada.

Risc: una fila podia quedar amb estat no històric sense haver creat registre fiscal.

Correcció: invoice_status es força sempre a HISTORICAL i aeat_status continua forçat a NO_VERIFACTU.

### A-011-05 · VISIBLE_ALUMNE era permissiu per defecte — corregit

Abans: absència de valor implicava 1 tant al builder com al repository.

Correcció: absència implica 0. La visibilitat 1 s'ha d'aportar explícitament i després continuar sotmesa a les polítiques de consulta.

### A-011-06 · La fitxa funcional era massa genèrica i contradeia el codi — documentació corregida

La fitxa anterior atribuïa al cas pantalla/intranet genèrica, REQUEST_ID/CORRELATION_ID, operational_event/sif_audit_event, registre fiscal/cua AEAT i notificacions. Aquestes peces no formen part del commit de l'importador actual.

La fitxa reescrita diferencia el comportament històric NO_VERIFACTU del flux d'emissió ordinària.

## 4. Troballes que continuen pendents

### P-011-01 · Multiemissor i unicitat global — BLOQUEJANT

La taula factura imposa unicitat global sobre NUM_VISIBLE i sobre sèrie/any/seqüència. Dues entitats emissores o dos orígens amb el mateix número no es poden representar fidelment només amb una nova clau idempotent. No s'ha de renumerar l'històric per resoldre-ho.

### P-011-02 · Col·lisió amb fiscal_sequence — BLOQUEJANT

L'importador històric no avança fiscal_sequence. Una factura històrica pot ocupar un número que la seqüència d'emissió nova intenti reservar més tard. Cal preflight/model de coexistència.

### P-011-03 · Custòdia física del document — BLOQUEJANT per evidència documental

El builder valida format de path/hash i el repository desa metadata. No llegeix bytes, no recalcula SHA-256, no copia l'original a storage privat i no acredita que el PDF sigui l'original històric.

### P-011-04 · Inventari i reconciliació de lot — PENDENT

El servei importa un payload ja preparat. No extreu web.factures ni prova que s'hagin importat totes les files, sèries, imports, A/R i documents.

### P-011-05 · Auditoria d'operador — PENDENT

OperationalEventRepository existeix, però UC-011 no el cableja. Tampoc s'insereix sif_audit_event. Els scripts no tenen identitat/rol/request/correlation obligatoris.

### P-011-06 · Execució productiva — PENDENT

Els scripts preview i process rebutgen SIF_ENV=production. Això és segur per desenvolupament/preproducció, però significa que encara no hi ha un canal productiu autoritzat.

## 5. PHP i JavaScript

### PHP
Hi ha implementació real i específica: scripts, Service, Builder i Repository. Aquesta és la superfície principal auditada.

### JavaScript
No s'ha localitzat cap JS dedicat al UC-011. No s'ha creat cap diagrama de JS fictici. Si en el futur es construeix una pantalla d'intranet, haurà de ser només un adaptador autoritzat al servei, no una segona implementació fiscal.

## 6. Persistència confirmada pel codi

Escriu factura, factura_linia, fact_rels i factura_documents opcional. A la branca també escriu IDEMPOTENCY_PAYLOAD_HASH.

No escriu factura_registres, factura_registre_control, fiscal_queue, fiscal_sequence, payment_transaction, payment_allocation, operational_event ni sif_audit_event.

## 7. Proves existents

HistoricalInvoiceMigrationServiceTest ja comprovava doble crida equivalent, una sola factura/línia/relació/document, absència de registre fiscal/cua/seqüència i LAST_FISCAL_ORDER=0.

HistoricalInvoicePayloadBuilderTest ja comprovava parsing del número, idempotency key, metadata i número obligatori.

Els tests de scripts comproven frontera CLI, bloqueig de production, absència d'InvoiceService/PaymentService i dry-run.

## 8. Proves afegides

- issue_date obligatòria.
- discrepància NUM_VISIBLE vs series/year/num_seq.
- estat HISTORICAL forçat.
- VISIBLE_ALUMNE=0 per defecte.
- mateixa idempotency key amb payload diferent -> 409.
- persistència d'un hash SHA-256 canònic.
- visibilitat privada efectiva a MySQL.

Fins que GitHub Actions executi la branca, aquests nous tests són IMPLEMENTATS/PENDENTS DE RESULTAT, no VERIFIED_PASS.

## 9. Documentació creada

- uc-011-classes-actual-final.md.
- uc-011-sequencies-actual-final.md.
- uc-011-activitats-actual-final.md.
- uc-011-tracabilitat-implementacio.md.
- aquest informe.

## 10. Estat resum

| Àrea | Documentat | Implementat | Verificat | Pendent |
| --- | --- | --- | --- | --- |
| Import històric local | sí | sí | tests existents; CI de branca pendent | execució productiva |
| No VERIFACTU retroactiu | sí | sí | integration existent | inventari real |
| Idempotència per contingut | sí | sí branca | CI pendent | migrats antics sense hash |
| Data/número/estat | sí | sí branca | CI pendent | validació de lot |
| Visibilitat | sí | sí branca | CI pendent | política final UC-80 |
| Document original | sí | metadata sí | format només | bytes/storage/hash físic |
| Auditoria operador | sí objectiu | no | absència contrastada | cablejat real |
| Multiemissor/numeració | sí | no | risc estàtic contrastat | disseny + proves E2E |

## 11. Criteri de tancament

L'auditoria documental i de codi queda estructurada amb totes les peces sol·licitades. UC-011 no s'ha de marcar com a complet operatiu mentre faltin preflight multiemissor/numeració, inventari complet, custòdia documental i canal productiu auditat. Els reforços de codi d'aquesta branca sí poden passar a verificats quan la suite MySQL i checks del PR finalitzin correctament.
