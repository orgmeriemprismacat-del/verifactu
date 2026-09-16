# 24 - Diccionari de camps i valors

> Document de referencia per tipificar els camps clau del SIF i evitar valors lliures incoherents.

## 1. Objectiu

Definir camps, significat, valors permesos i taula on viuen.

## 2. Camps inicials a tipificar

### factura.ESTAT_FACTURA

- `ISSUED`: factura emesa.
- `RECTIFIED`: factura rectificada.
- `CANCELLED`: factura cancel·lada/anul·lada fiscalment quan correspongui.
- `HISTORICAL`: factura importada de l'historic, conservada per consulta i relacio, sense alta VERI*FACTU retroactiva.

### factura.ESTAT_AEAT

- `PENDING`: pendent d'enviament.
- `SENT`: enviada.
- `ACCEPTED`: acceptada.
- `REJECTED`: rebutjada.
- `RETRY`: pendent de reintent.
- `FAILED`: fallida despres de reintents.
- `NO_VERIFACTU`: factura historica migrada o conservada per consulta, sense registre VERI*FACTU retroactiu ni cua AEAT.

### factura.ESTAT_COBRAMENT

- `PENDING`: factura pendent de cobrament.
- `PARTIAL`: factura parcialment cobrada.
- `PAID`: factura completament cobrada o compensada.
- `OVERPAID`: import assignat superior al total de factura i pendent de revisio.
- `PARTIALLY_REFUNDED`: factura amb devolucio parcial registrada.
- `REFUNDED`: factura amb import retornat o neutralitzat segons flux fiscal aplicable.

### payment_transaction.TIPUS_MOVIMENT

- `CHARGE`: cobrament.
- `REFUND`: devolucio.
- `COMPENSATION`: compensacio/saldo.

### payment_transaction.METODE

- `REDSYS`
- `TRANSFERENCIA`
- `COMPENSACIO`
- `MANUAL`

### payment_transaction.SOURCE_CHANNEL

- `REDSYS`: pagament confirmat per callback o conciliacio Redsys.
- `INTRANET`: pagament registrat manualment des de la intranet.
- `ECOMMERCE`: pagament iniciat a ecommerce abans de Redsys o pay.prisma.cat.
- `PAY_PRISMA`: pagament o callback gestionat directament a `pay.prisma.cat`.
- `MIGRACIO`: moviment creat en migracio/historic.
- `PROCESS_SIF`: moviment generat per proces automatic del SIF.
- `MANUAL`: ajust manual autoritzat i auditat.

### payment_transaction.ESTAT

- `CONFIRMED`: moviment validat i aplicable.
- `PENDING_REVIEW`: moviment registrat pero pendent de revisio abans d'assignar-lo.
- `CANCELLED`: moviment anul·lat operativament sense efecte fiscal final.
- `ERROR`: moviment rebut amb error tecnic o funcional.

### payment_allocation.TIPUS_ASSIGNACIO

- `INVOICE_PAYMENT`: cobrament assignat a factura.
- `PARTIAL_PAYMENT`: cobrament parcial assignat a factura.
- `REFUND`: devolucio assignada a factura.
- `COMPENSATION`: saldo o compensacio assignada a factura.
- `ADJUSTMENT`: ajust economic auditat que no encaixa en els casos anteriors.

### fiscal_queue.STATUS

- `PENDING`: pendent de processar.
- `PROCESSING`: agafat per un proces automatic.
- `RETRY`: pendent de reintent.
- `SENT`: enviat correctament.
- `FAILED`: fallit despres dels reintents previstos.

### factura_registres.ESTAT_ENVIO

- `PENDING`: registre fiscal creat, pendent d'enviament.
- `SENT`: enviat a AEAT.
- `ACCEPTED`: acceptat.
- `REJECTED`: rebutjat.
- `RETRY`: pendent de reintent.
- `FAILED`: fallida tecnica o funcional no resolta.

### redsys_notifications.STATUS

- `RECEIVED`: callback rebut.
- `VALIDATED`: signatura i dades basiques validades.
- `DUPLICATE`: `DS_ORDER` ja processat o ja registrat.
- `PROCESSED`: callback conciliat i aplicat al SIF.
- `ERROR`: callback amb error tecnic o funcional.

### fact_rels.SOURCE_TYPE

- `CURS`
- `INSCRIPCIO`
- `PACK`
- `GRUP`
- `REGAL`
- `USOC`
- `ENTITAT`
- `EMPRESA`
- `FACTURA_ABANS_COBRAMENT`
- `CANVI_CURS`
- `BAIXA`
- `RECTIFICATIVA`
- `RECLAMACIO`
- `MANUAL`
- `HISTORIC_WEB_FACTURES`

### fact_rels.RELATION_TYPE

- `ORIGIN`: origen principal de la factura.
- `LINE_SOURCE`: origen d'una linia concreta de factura.
- `PAYER`: pagador economic relacionat.
- `RECEIVER`: receptor fiscal relacionat.
- `RECTIFIES`: relacio entre rectificativa i factura rectificada.
- `HISTORIC_LINK`: vincle de compatibilitat amb historic.
- `VISIBILITY_LINK`: relacio usada per permisos o visibilitat.

### factura_linia.DESC_ORIGEN

- `CAP`: sense descompte.
- `PACK`: descompte de pack.
- `GRUP`: preu o descompte de grup.
- `CODI_PROMO`: codi promocional introduit pel client o gestio.
- `PROMOCIO_TEMPORAL`: promocio temporal definida a taules operatives.
- `USOC`: descompte o subvencio USOC.
- `MANUAL`: ajust manual autoritzat.
- `ALTRE`: cas excepcional auditat.

### factura_linia.DESC_MODE

- `PERCENT`: percentatge.
- `AMOUNT`: import fix.
- `FIXED_PRICE`: preu final fixat.

### URL_STATUS

- `ACTIVE`
- `INACTIVE`
- `EXPIRED`
- `PAID`
- `REPLACED`

### SIF_DB_ROLE

- `INTRANET_OPERATIVA`: consulta dades necessaries i no modifica factures fiscals emeses.
- `API_SIF`: crea factures, registres, pagaments i relacions mitjancant fluxos controlats.
- `PROCESS_SIF`: processa cues, documents, retries i incidencies automatiques.
- `AUDITOR_READONLY`: consulta sense escriptura.
- `ADMIN_BD`: administracio tecnica reservada.

## 3. Regla general

Els estats fiscals no haurien de ser text lliure. Si cal un estat nou, s'ha d'afegir primer a aquest diccionari i despres a la BD/codi.

## 4. Rols, actors i processos tipificats inicials

### ROL_SIF

- `MERIEM_RESP_TECNICA`: responsable funcional i tecnica del SIF.
- `ADAM_DIRECCIO_FACTURACIO`: direccio i moviments de facturacio.
- `PABLO_GESTIO_SECRETARIA`: gestio/secretaria.
- `ISA_SUPORT`: suport relacionat amb Moodle i suport a Secretaria, sense rol fiscal ordinari.
- `AUDITOR_READONLY`: auditor fiscal o AEAT nomes lectura.
- `PROCESS_SIF_AUTOMATIC`: proces automatic del SIF.

### ACTOR_EXTERN

- `ALUMNE_INTRANET_PERSONAL`: alumne autenticat a la seva intranet personalitzada, no a la intranet principal.
- `EMPRESA_RESPONSABLE_SENSE_INTRANET`: empresa o responsable receptor de factura sense acces a la intranet principal.

## 5. Accions critiques tipificades

### SIF_ACTION

- `CREATE_INVOICE`: crear factura ordinaria.
- `REGISTER_PAYMENT`: registrar pagament.
- `ISSUE_BEFORE_PAYMENT`: generar factura abans de cobrament.
- `MARK_E_FACT`: marcar factura electronica.
- `CREATE_RECTIFICATION`: generar rectificativa.
- `VIEW_GROUP_INVOICE`: veure factura de grup.
- `VIEW_COMPANY_INVOICE`: veure factura d'empresa.
- `DOWNLOAD_PDF`: consultar/descarregar PDF.
- `SEND_SECURE_LINK`: enviar o generar enllac segur de consulta de factura.
- `EXPORT_FISCAL_DATA`: descarregar exportacions fiscals.
- `RESOLVE_INCIDENT`: resoldre incidencia SIF.
- `CHANGE_CONFIG`: canviar configuracio SIF.
- `ACTIVATE_VERSION`: activar versio SIF.
- `VIEW_DECLARATION`: accedir a declaracio responsable.

## 6. Valors normatius i documentals del SIF

### SIF_MODE

- `VERIFACTU`: modalitat prevista i activa del SIF PrisMa.
- `NO_VERIFACTU`: no es mode productiu del SIF PrisMa; nomes s'usa com a marca de consulta/migracio per factures historiques conservades sense registre VERI*FACTU retroactiu.

### DECLARACIO_RESPONSABLE_STATUS

- `BORRADOR`: document de treball no signable.
- `PREPARADA_PER_SIGNAR`: versio completa pendent de signatura.
- `SIGNADA_ACTIVA`: declaracio signada associada a la versio productiva activa.
- `SUPERADA`: declaracio d'una versio anterior substituida per una nova versio.
- `ANULADA`: declaracio retirada per error documental o canvi de criteri abans d'activar-la.

### AEAT_AUTH_METHOD

- `CERT_ENTITAT`: certificat digital de l'entitat.
- `APODERAMENT`: apoderament o mecanisme equivalent admis per AEAT.
- `CERT_TECNIC_TEST`: certificat o credencial de proves, nomes per entorn test/preproduccio.
- `PENDENT`: no configurat encara.

### AEAT_AUTH_STATUS

- `PENDING_CONFIG`: pendent de configuracio.
- `CONFIGURED`: configurat tecnicament.
- `TESTED`: provat correctament en l'entorn corresponent.
- `EXPIRED`: certificat o credencial caducada.
- `REVOKED`: certificat o credencial revocada.
- `ERROR`: configuracio incorrecta o no operativa.

### FISCAL_DOCUMENT_TYPE

- `DECLARACIO_RESPONSABLE`: declaracio responsable del SIF.
- `VERSIO_ACTIVA`: fitxa o pantalla de versio activa.
- `FACTURA_PDF`: PDF fiscal de factura.
- `FACTURA_XML`: XML o registre tecnic associat a factura.
- `QR_DATA`: dades o URL del QR/verificacio.
- `AEAT_RESPONSE`: resposta AEAT, CSV o detall tecnic de remissio.
- `EVIDENCIA_PROVA`: evidencia de proves o go/no-go.
- `ANNEX_DOCUMENTAL`: annex o document intern associat.

### FISCAL_DOCUMENT_STATUS

- `PENDING`: pendent de generar o adjuntar.
- `GENERATED`: generat.
- `SIGNED`: signat electronicament o manualment, segons document.
- `PUBLISHED_IN_SIF`: accessible dins del SIF.
- `ARCHIVED`: conservat com a evidencia historica.
- `ERROR`: error de generacio, signatura o publicacio.

## 7. Camps normatius minims a conservar

Aquest apartat no substitueix l'esquema XML oficial d'AEAT. Serveix per assegurar que el model intern del SIF conserva les dades necessaries per construir registres, PDF/QR, declaracio responsable i evidencies.

### factura / factura_registres

- `EMISSOR_NIF`: NIF de l'obligat a expedir factura.
- `EMISSOR_NOM`: nom o rao social de l'emissor.
- `RECEPTOR_NIF`: NIF/NIE/CIF o identificador fiscal del destinatari quan correspongui.
- `RECEPTOR_NOM`: nom, cognoms o rao social del destinatari.
- `SERIE_FACTURA`: serie visible de la factura.
- `NUM_FACTURA`: numero visible de factura.
- `DATA_EXPEDICIO`: data d'expedicio de factura.
- `DATA_OPERACIO`: data d'operacio o pagament anticipat si es diferent.
- `TIPUS_FACTURA`: tipus fiscal de factura.
- `ES_RECTIFICATIVA`: marca de factura rectificativa.
- `FACTURA_RECTIFICADA`: factura o factures rectificades quan sigui preceptiu.
- `DESCRIPCIO_OPERACIO`: descripcio general de les operacions.
- `IMPORT_TOTAL`: import total de la factura.
- `REGIM_IVA`: regim o regims aplicats.
- `INVERSIO_SUBJECTE_PASSIU`: indicador d'inversio del subjecte passiu si aplica.
- `BASE_IMPOSABLE`: base imposable.
- `TIPUS_IVA`: tipus impositiu aplicat.
- `QUOTA_IVA`: quota d'IVA.
- `CAUSA_EXEMPCIO_NO_SUBJECTA`: causa d'exempcio o no subjeccio quan no es repercuteix IVA.
- `FISCAL_ORDER`: ordre fiscal global del registre.
- `HASH`: huella/hash del registre.
- `PREV_HASH`: huella/hash del registre anterior.
- `PREV_NUM_FACTURA`: serie/numero del registre anterior quan calgui conservar-lo.
- `GENERATED_AT`: data i hora de generacio del registre.
- `TIMEZONE`: hus horari de generacio.
- `SIF_CODE`: codi identificador del sistema informatic.
- `SIF_VERSION`: versio concreta del SIF que genera el registre.
- `PRODUCTOR_NIF`: NIF/CIF del productor/titular intern documentat.

### fiscal_queue / remissio AEAT

- `PAYLOAD_XML`: XML o payload preparat per AEAT.
- `PAYLOAD_HASH`: hash del payload enviat o pendent d'enviar.
- `AEAT_CSV`: codi segur de verificacio retornat per AEAT, si existeix.
- `AEAT_ERROR_CODE`: codi d'error AEAT o tecnic.
- `AEAT_ERROR_MESSAGE`: missatge resum d'error.
- `RETRY_COUNT`: nombre de reintents.
- `NEXT_RETRY_AT`: proper reintent previst.
- `FLOW_WAIT_SECONDS`: temps d'espera indicat o aplicat entre enviaments, si el servei el retorna.
- `FIRST_SENT_AT`: primer intent d'enviament.
- `LAST_SENT_AT`: ultim intent d'enviament.

### factura_documents / QR

- `DOCUMENT_TYPE`: tipus de document fiscal.
- `DOCUMENT_STATUS`: estat del document.
- `FILE_HASH`: hash del fitxer conservat.
- `FILE_STORAGE_REF`: referencia interna al fitxer, sense path public directe.
- `QR_URL`: URL continguda al QR.
- `QR_DATA_HASH`: hash o resum de les dades del QR si es conserva.
- `VERIFACTU_TEXT`: text visible associat a factura verificable.

### sif_versions / declaracio responsable

- `VERSION_CODE`: codi exacte de versio.
- `VERSION_STATUS`: estat de versio.
- `DECLARACIO_STATUS`: estat de la declaracio responsable.
- `DECLARACIO_DOCUMENT_REF`: referencia interna al document de declaracio.
- `DECLARACIO_SIGNED_AT`: data de signatura, si existeix.
- `DECLARACIO_SIGNER_NAME`: persona que signa formalment.
- `DECLARACIO_SIGNER_ROLE`: carrec o funcio de la persona signant.
- `PRODUCTOR_TITULAR`: entitat productora/titular interna del SIF.
- `CONTACTE_TECNIC`: responsable tecnic/documental intern.
- `AEAT_AUTH_METHOD`: metode d'identificacio/remissio.
- `AEAT_AUTH_STATUS`: estat de certificat/apoderament.
- `FIRST_VERIFACTU_SENT_AT`: data del primer enviament efectiu `VERI*FACTU`, quan existeixi.

## 8. Traça universal d'accions sobre pagaments

### payment_action_event.ACTION

- `CREATE_REQUEST`: petició de creació abans de disposar de `UUID_PAYMENT`.
- `CREATE`: creació confirmada del moviment.
- `IDEMPOTENCY_REUSE`: retorn del moviment existent per la mateixa clau idempotent.
- `DUPLICATE_DETECTED`: detecció d'un possible duplicat que requereix reutilització, bloqueig o revisió.
- `SEARCH`: cerca de pagaments.
- `VIEW`: consulta del detall d'un pagament.
- `VIEW_ALLOCATIONS`: consulta de les assignacions.
- `EXPORT`: exportació de pagaments o de la seva cronologia.
- `ALLOCATE`: assignació a una factura.
- `REALLOCATE`: correcció append-only d'una assignació.
- `UNALLOCATE`: neutralització controlada d'una assignació anterior.
- `SPLIT_ALLOCATION`: repartiment entre diverses factures.
- `RECONCILE`: conciliació TPV, bancària o amb el llegat.
- `MARK_PENDING_REVIEW`: pas del pagament a revisió manual.
- `RESOLVE_RECONCILIATION`: resolució auditada d'una diferència.
- `LINK_REFUND`: vinculació amb un moviment de devolució.
- `LINK_COMPENSATION`: vinculació amb saldo o compensació.
- `LINK_CLAIM_PAYMENT`: vinculació amb cobrament de reclamació/morositat.
- `CANCEL_OPERATION`: cancel·lació operativa sense esborrar el moviment.
- `MARK_ERROR`: marcatge d'error controlat.
- `RETRY`: reintent d'un procés relacionat amb el pagament.
- `RECOVER_LOCK`: recuperació d'un lock caducat.
- `REDSYS_CALLBACK`: recepció/tractament de callback relacionat.
- `REDSYS_WORKER_RESULT`: resultat del worker Redsys.
- `SYNC_LEGACY`: sincronització mínima posterior cap al llegat.
- `IMPORT`: importació o migració controlada.
- `ACCESS_DENIED`: petició bloquejada per permisos.
- `VALIDATION_REJECTED`: petició rebutjada per validació.
- `IMMUTABILITY_BLOCKED`: intent d'edició o esborrat directe bloquejat.

### payment_action_event.RESULT

- `REQUESTED`: intent rebut i conservat abans d'executar.
- `SUCCEEDED`: acció completada.
- `REUSED`: moviment existent retornat per idempotència.
- `NO_CHANGE`: acció vàlida sense canvi de negoci.
- `REJECTED`: permís, validació o regla de negoci han bloquejat l'acció.
- `FAILED`: error tècnic o funcional després d'iniciar l'execució.
- `QUEUED`: acció acceptada i pendent de procés asíncron.
- `PARTIAL`: només s'ha completat una part; requereix continuació o incidència.

### payment_action_event.SOURCE_ENVIRONMENT

- `PRODUCTION`
- `PREPRODUCTION`
- `TEST`
- `DEVELOPMENT`
- `MIGRATION`

### payment_action_event.SOURCE_CHANNEL

- `INTRANET`
- `ECOMMERCE`
- `STUDENT_PORTAL`
- `PAY_PRISMA`
- `SIF_PANEL`
- `SIF_API`
- `REDSYS_CALLBACK`
- `REDSYS_WORKER`
- `CLI`
- `SCHEDULED_PROCESS`
- `RECONCILIATION`
- `LEGACY_SYNC`
- `MIGRATION`
- `ADMIN_TOOL`

### payment_action_event.ACTOR_TYPE

- `HUMAN`: usuari intern o extern autenticat.
- `SYSTEM`: aplicació o integració identificada.
- `PROCESS`: worker, cron, CLI o procés automàtic versionat.

## 9. Regla d'immutabilitat de la traça de pagaments

`payment_action_event` és append-only. Els rols de l'aplicació només poden inserir i consultar segons permís; no poden actualitzar ni esborrar events. Una correcció genera un event nou.

No es pot executar ni retornar cap acció sobre pagaments si el sistema no pot conservar-ne la traça. Els resultats correctes s'han de confirmar atòmicament amb la mutació de domini. Els intents rebutjats o fallits també s'han de conservar amb la mateixa correlació.

## 10. Valors de gestió, correcció i operació incorporats

### operational_event.FISCAL_IMPACT

- `PENDING_CLASSIFICATION`: encara no es pot executar cap mutació fiscal.
- `NONE`: només event operatiu.
- `ISSUE`: emissió ordinària.
- `RECTIFICATION`: factura rectificativa.
- `COMPLEMENTARY`: increment facturable segons decisió validada.
- `CANCELLATION_RECORD`: `RegistroAnulacion`.
- `CORRECTION_RECORD`: subsanació registral.
- `BLOCKED`: impacte ambigu o actor sense permís.

### operational_event.ECONOMIC_IMPACT

- `NONE`
- `CHARGE`
- `REFUND`
- `CREDIT`
- `COMPENSATION`
- `REALLOCATION`
- `PENDING_DECISION`

### factura_registre_control.RECORD_ACTION

- `ALTA`
- `ANULACION`
- `SUBSANACION`

`CORRECTION_KIND` ha d'explicar la causa controlada; no substitueix els camps
normatius `REJECTION_PREVIOUS` i `WITHOUT_PREVIOUS_RECORD`.

### aeat_submission_attempt.STATUS

- `STARTED`
- `ACCEPTED`
- `ACCEPTED_WITH_ERRORS`
- `REJECTED`
- `RETRY`
- `DEAD_LETTER`
- `FAILED`

### document_job.STATUS i notification_outbox.STATUS

- `PENDING`
- `PROCESSING`
- `RETRY`
- `COMPLETED` o `SENT`, segons la taula.
- `FAILED`
- `DEAD_LETTER`
- `CANCELLED` només amb motiu i event d'auditoria.

### reconciliation_item.RESULT

- `MATCHED`
- `DUPLICATE`
- `MISSING_IN_SIF`
- `MISSING_IN_LEGACY`
- `AMOUNT_MISMATCH`
- `STATUS_MISMATCH`
- `AMBIGUOUS`
- `INCIDENT`

### sif_version.STATUS

- `DRAFT`
- `VALIDATING`
- `APPROVED`
- `ACTIVE`
- `RETIRED`
- `REJECTED`

## 11. Regla comuna de dates, identificadors i hashes

- UUIDs: text canònic de 36 caràcters fins que es decideixi migració binària.
- timestamps d'events: precisió de microsegons i zona horària definida a la capa
  d'aplicació; `OCCURRED_AT` no es dedueix de `RECORDED_AT`.
- imports: `DECIMAL(12,2)`, mai `float` de negoci.
- hashes: SHA-256 hexadecimal de 64 caràcters sobre representació canònica.
- `REQUEST_ID`, `CORRELATION_ID` i `CAUSATION_ID`: opacs, no contenen dades
  personals ni secrets.
- JSON de canvis/snapshots: normalitzat, mínim, redactat i sense credencials,
  PAN/CVV, signatures completes ni tokens reutilitzables.

## 12. Operació comercial prèvia a factura i pagament

### commercial_operation.CLASSIFICATION

- `BILLABLE`: operació amb dades suficients per congelar el snapshot i derivar
  a factura/intenció de pagament.
- `NON_BILLABLE`: operació informativa o administrativa amb motiu explícit.
- `FREE_SAMPLE`: tastet o repte gratuït, sense factura ni pagament.
- `SUBSIDISED_PENDING_DECISION`: l'alumne no paga, però finançador, receptor o
  obligació fiscal/documental encara no estan aprovats.
- `PENDING_VALIDATION`: falten dades, evidència o validació de descompte.

Cap classificació es dedueix només de `NET_AMOUNT = 0`.

### commercial_operation.STATUS

- `DRAFT`
- `RESERVED`
- `PENDING_VALIDATION`
- `READY_FOR_PAYMENT`
- `PAYMENT_PENDING`
- `PAID`
- `INVOICED`
- `COMPLETED`
- `EXPIRED`
- `CANCELLED`
- `INCIDENT`

L'estat comercial no substitueix `ESTAT_COBRAMENT`, `ESTAT_FACTURA` ni
`ESTAT_AEAT`.

### commercial_operation_party.PARTY_ROLE

- `PARTICIPANT`: persona que rep el curs/servei.
- `PAYER`: persona o entitat que ordena o suporta el pagament.
- `FISCAL_RECIPIENT`: destinatari fiscal proposat o confirmat.
- `RESPONSIBLE`: empresa, familiar o responsable vinculat.
- `FUNDER`: entitat que finança una operació subvencionada.

Una mateixa persona pot tenir diversos rols, però cada rol queda explícit i
versionat al snapshot.

### discount_validation.STATUS

- `REQUESTED`
- `EVIDENCE_PENDING`
- `VALIDATED`
- `REJECTED`
- `EXPIRED`
- `CONSUMED`
- `CANCELLED`

`VALIDATED` exigeix regla versionada, actor validador i evidència o justificació
admissible. `FUTURE_ENTITLEMENT_REF` no pot generar-se dues vegades per la
mateixa clau idempotent.

### payment_link.STATUS

- `ACTIVE`
- `USED`
- `EXPIRED`
- `REVOKED`
- `REPLACED`
- `INCIDENT`

Només es conserva `TOKEN_HASH`, mai el token reutilitzable. Revocar o substituir
un enllaç no modifica una factura ni un pagament; qualsevol acció genera
`payment_action_event`.

### Camps fiscals materialitzats per la migració 000004

La migració additiva crea físicament els camps normatius de l'apartat 7 que no
existien al core: emissor, descripció, inversió del subjecte passiu, causa
d'exempció/no subjecció, recàrrec d'equivalència, codi/versió/productor del SIF,
zona horària, dades de resposta/retry AEAT i metadades QR/VERI*FACTU.

Són nullable durant la transició. Això no els converteix en opcionals de
negoci: el validador ha d'exigir-los segons el tipus d'operació abans d'emetre.
