# Auditoria detallada UC-011 · Importar factura històrica

**Data:** 03/10/2026  
**Repositori:** orgmeriemprismacat-del/verifactu  
**Base inicial:** main  
**Branca de correcció:** audit/uc-011-2026-10-03

## 1. Abast revisat

S'han contrastat la fitxa funcional, l'UML integrat, scripts CLI, PHP de servei/builder/repository, proves unitàries i d'integració, esquema d'idempotència i restriccions sobre numeració, documents i auditoria. També s'ha revisat el sistema origen llegat: `alumnes-factura.php`, `alumnes-factura.js`, endpoints AJAX de consulta/edició/anul·lació/descàrrega i els guards de lectura/mutació SIF.

No s'ha localitzat JavaScript ni una pàgina d'intranet dedicada a **executar** UC-011. El canal explícit d'importació és CLI i refusa production. Sí hi ha JavaScript/PHP llegat que modifica i regenera les factures d'origen; és upstream del UC-011 i condiciona la qualitat de l'evidència migrada.

## 2. Artefactes existents abans de l'auditoria

- documentacio/06-fitxes-funcionals/uc-011.md.
- documentacio/07-uml-integrat/uc-011-importar-factura-historica.md.
- sif/scripts/preview-historical-invoice-migration.php.
- sif/scripts/process-historical-invoice-migration.php.
- HistoricalInvoiceMigrationService, HistoricalInvoicePayloadBuilder i HistoricalInvoiceMigrationRepository.
- HistoricalInvoicePayloadBuilderTest.
- HistoricalInvoiceMigrationServiceTest.
- Tests de frontera dels dos scripts.
- codi-drive/intranet-actual/alumnes-factura.php i js/alumnes-factura.js.
- endpoints consultaUsuarisFacturaRelacionada.php, guardarDadesFactura_Factures.php, anularFactura_Factures.php i descarregaFactura.php.
- LegacyInvoiceReadContext, LegacyInvoiceMutationAuthorization i SifLegacyInvoiceMutationGuard.

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


### A-011-07 · Hash sobre payload cru podia produir falsos conflictes — corregit

El primer reforç calculava el hash sobre tot el payload retornat pel builder, que conserva aliases d'entrada. Dues peticions equivalents podien diferir només perquè una usava `num_visible` i una altra `num_factura`, o `issue_date` i `data_emissio`.

Correcció: el repository calcula i compara el hash sobre una projecció canònica de les dades **materialment persistides**. També normalitza defaults de billing/totals/línies/relacions i ordena les relacions abans del hash. Les línies mantenen ordre perquè ORDRE sí és semàntic.

### A-011-08 · Camps fiscals i emissor es perdien en la migració — corregit quan venen al payload

L'esquema SIF ja disposa d'`EMISSOR_NIF`, `EMISSOR_NOM`, `DESCRIPCIO_OPERACIO`, `INVERSIO_SUBJECTE_PASSIU`, `CAUSA_EXEMPCIO_NO_SUBJECTA` i `RECARREC_EQUIVALENCIA_*`, però `HistoricalInvoiceMigrationRepository` no els escrivia.

Correcció: el builder normalitza emissor/descripció i camps fiscals; el repository els persisteix a `factura` i, on correspon, a `factura_linia`. Això no resol la unicitat global multiemissor, però evita perdre l'emissor i fiscalitat històrica quan la font els aporta.

### A-011-09 · Validació estructural insuficient abans de MySQL — corregit

Abans només es comprovava que billing/totals/línies fossin arrays i que existís almenys una línia. Camps interns absents o imports no numèrics podien arribar al repository com notices/errors de DB.

Correcció: es validen billing name/nif, imports mínims de totals i línies, tipus F1/F2/R1-R5, dates, relacions explícites, flags fiscals i causes d'exempció E1-E8 amb règim EXEMPT.

### A-011-10 · El PDF llegat es regenera sota demanda — constatat, no resolt per UC-011

`descarregaFactura.php` invoca `Intranet->generaFactura($id, true)` i valida el fitxer temporal retornat. Això genera una representació des de dades vives; no acredita que els bytes siguin el PDF original emès anys enrere.

Conseqüència: el path/hash declarat a `factura_documents` no s'ha de marcar com «original verificat» fins que els bytes originals siguin localitzats, hashejats i custodiats.

### A-011-11 · Hi ha un tall de mutacions llegades ja implementat — implementat però condicionat a flags

`guardarDadesFactura_Factures.php`, `anularFactura_Factures.php` i `descarregaFactura.php` passen per `SifLegacyInvoiceMutationGuard`. El guard consulta el SIF per `source_type=HISTORIC_WEB_FACTURES/source_id` o `factura_relacionada` i retorna 409 si la factura ja és governada pel SIF.

Això aprofita directament les relacions creades per UC-011, però només protegeix si `SIF_BLOCK_LEGACY_INVOICE_MUTATIONS` està activat i UC-007 està operativa. El cut-over ha d'incloure prova d'aquests flags.

## 4. Troballes que continuen pendents

### P-011-01 · Multiemissor i unicitat global — BLOQUEJANT

La taula factura imposa unicitat global sobre NUM_VISIBLE i sobre sèrie/any/seqüència. Dues entitats emissores o dos orígens amb el mateix número no es poden representar fidelment només amb una nova clau idempotent. No s'ha de renumerar l'històric per resoldre-ho.

### P-011-02 · Col·lisió amb fiscal_sequence — BLOQUEJANT

L'importador històric no avança fiscal_sequence. Una factura històrica pot ocupar un número que la seqüència d'emissió nova intenti reservar més tard. Cal preflight/model de coexistència.

### P-011-03 · Custòdia física del document — BLOQUEJANT per evidència documental

El builder valida format de path/hash i el repository desa metadata. No llegeix bytes, no recalcula SHA-256 ni copia l'original a storage privat. A més, el flux llegat de descàrrega regenera el PDF amb `generaFactura(..., true)`, de manera que una regeneració actual no és prova dels bytes originals.

### P-011-04 · Inventari i reconciliació de lot — PENDENT

El servei importa un payload ja preparat. No extreu web.factures ni prova que s'hagin importat totes les files, sèries, imports, A/R i documents.

### P-011-05 · Auditoria d'operador — PENDENT

OperationalEventRepository existeix, però UC-011 no el cableja. Tampoc s'insereix sif_audit_event. Els scripts no tenen identitat/rol/request/correlation obligatoris.

### P-011-06 · Execució productiva — PENDENT

Els scripts preview i process rebutgen SIF_ENV=production. Això és segur per desenvolupament/preproducció, però significa que encara no hi ha un canal productiu autoritzat.

## 5. PHP i JavaScript

### PHP
Hi ha implementació específica del migrador: scripts, Service, Builder i Repository. També hi ha PHP upstream del llegat per consultar, editar, anul·lar i regenerar factures; els endpoints nous/endurits utilitzen context de lectura, autorització i guard SIF.

### JavaScript
No hi ha JS que executi UC-011. `alumnes-factura.js` és, però, rellevant com a **origen**: cerca primer al SIF/UC-007 amb fallback llegat i dispara endpoints d'edició, anul·lació i descàrrega. Per això queda representat als diagrames ACTUAL com a superfície upstream, no com a importador.

## 6. Persistència confirmada pel codi

Escriu factura, factura_linia, fact_rels i factura_documents opcional. A la branca també escriu IDEMPOTENCY_PAYLOAD_HASH sobre la projecció material persistent i conserva EMISSOR_NIF/EMISSOR_NOM, DESCRIPCIO_OPERACIO, INVERSIO_SUBJECTE_PASSIU, CAUSA_EXEMPCIO_NO_SUBJECTA i RECARREC_EQUIVALENCIA_* quan s'aporten.

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
- reintent equivalent amb aliases diferents sense fals conflict.
- persistència d'emissor, descripció d'operació i camps fiscals històrics.
- validació prèvia de billing/imports/dates/línies/relacions.
- validació de causa d'exempció.

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
| Idempotència per contingut normalitzat | sí | sí branca | CI pendent | migrats antics sense hash |
| Data/número/estat/estructura | sí | sí branca | CI pendent | validació de lot |
| Emissor i fiscalitat històrica | sí | sí quan s'aporten | CI pendent | extracció completa del llegat |
| Visibilitat | sí | sí branca | CI pendent | política final UC-80 |
| Document original | sí | metadata sí | regeneració llegada identificada | bytes originals/storage/hash físic |
| Auditoria operador | sí objectiu | no | absència contrastada | cablejat real |
| Tall de mutacions llegades | sí | guard existent | codi contrastat | prova amb flags en sif_pre |
| Multiemissor/numeració | sí | persistència emissor parcial; unicitat no | risc estàtic contrastat | disseny + proves E2E |

## 11. Criteri de tancament

L'auditoria documental i de codi queda estructurada amb totes les peces sol·licitades. UC-011 no s'ha de marcar com a complet operatiu mentre faltin preflight multiemissor/numeració, inventari complet, custòdia documental i canal productiu auditat. Els reforços de codi d'aquesta branca sí poden passar a verificats quan la suite MySQL i checks del PR finalitzin correctament.
