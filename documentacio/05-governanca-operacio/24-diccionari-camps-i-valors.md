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
- `OFFICIAL_SOURCES_REVIEWED_AT`: data de revisio de fonts AEAT/BOE usada per la versio.
- `LEGAL_DEADLINE_CRITERION`: criteri aplicat al termini legal de l'entitat.
- `DECLARACIO_SIGNER_NIF`: NIF de la persona que signa formalment, si es conserva a l'expedient.
- `DECLARACIO_SIGNED_PLACE`: lloc de signatura.
- `TECHNICAL_APPROVAL_REF`: referencia interna al vistiplau tecnic de la versio.
- `AEAT_AUTH_SUBJECT`: titular/subjecte del certificat o representacio.
- `AEAT_AUTH_ISSUER`: emissor del certificat o autoritat equivalent.
- `AEAT_AUTH_SERIAL_PARTIAL`: numero de serie parcial o resum segur.
- `AEAT_AUTH_FINGERPRINT_PARTIAL`: empremta parcial o resum segur no reutilitzable.
- `AEAT_AUTH_VALID_UNTIL`: caducitat.
- `AEAT_AUTH_LAST_TEST_AT`: data i hora de l'ultima prova.
- `AEAT_AUTH_LAST_TEST_RESULT`: resultat de l'ultima prova.
- `AUDITOR_ACCESS_EXPIRES_AT`: caducitat de l'usuari auditor temporal, si existeix.
- `AUDITOR_EXPORT_REASON`: motiu declarat per una exportacio fiscal d'auditoria.

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

### UC-111 · Validació i concessió novell — esquema preparat, no integrat

- `discount_validation.DISCOUNT_TYPE = NOVICE_TEACHER`: valor tècnic previst per identificar una validació de titulació novell en la compra JASOM; `STATUS=VALIDATED` exigeix data, actor i persona participant corresponent. La capa d'adaptació del llegat `recent_titulat` a aquesta validació SIF NO està implementada encara.
- `commercial_entitlement.ENTITLEMENT_TYPE = FUTURE_DISCOUNT`, `RULE_VERSION = NOVICE_JASOM_V1`: saldo promocional concedit addicionalment a JASOM pagat, **NO** fons monetaris prepagats ni una nova transacció CHARGE. `CODE_HASH=NULL` i `STATUS=ISSUED` identifiquen un dret ja registrat però encara **sense codi bescanviable activat/lliurat**; no deduir que ha estat notificat.
- `novice_promotion_grant` (migració additiva [000008](../../sif/database/migrations/2026_09_22_000008_add_novice_promotion_grant.sql)): `UUID_ENTITLEMENT` (PK i FK al dret), `HOLDER_PARTY_KEY` (UNIQUE per persona), `ORIGIN_UUID_OPERATION` (UNIQUE), `UUID_VALIDATION` (UNIQUE), `UUID_FACTURA`, `ORIGINAL_CASH_AMOUNT` i `AVAILABLE_AMOUNT`. La BD exigeix `ORIGINAL_CASH_AMOUNT > 0` i `0 <= AVAILABLE_AMOUNT <= ORIGINAL_CASH_AMOUNT`. Els imports estan expressats en euros `DECIMAL(12,2)`, no en cèntims.
- `NovicePromotionGrantService::issueForOperation` ([PHP](../../sif/src/Service/NovicePromotionGrantService.php)): consulta ella mateixa la validació, persona, vinculació factura/inscripció i cobraments confirmats del SIF. Només concedeix si factura JASOM emesa i totalment pagada amb diners efectivament atribuïts a l'operació; bloqueja reemissió en una altra inscripció JASOM de la mateixa persona. L'adaptador de validació i el hook de postpagament són PENDENTS, així com els consums múltiples, derivacions i anul·lacions.
### UC-111 · Enllaç d'alta, decisió i pagament novell — tall de desenvolupament

- `NovicePromotionEnrollmentStager::stage` ([codi](../../sif/src/Service/NovicePromotionEnrollmentStager.php)): origen `commercial_operation` de matrícula JASOM preparat amb `SOURCE_TYPE=CURS`, `SOURCE_ID=inscripcions.ID`, `PRODUCT_CODE=JASOM`, `STATUS=PENDING_VALIDATION` i `CLASSIFICATION=PENDING_VALIDATION`. Crea `commercial_operation_party` amb `PARTY_ROLE=PARTICIPANT` i `PARTY_KEY` canònic del backend; comprova `legacy.A_PAGAR=NET_AMOUNT=GROSS_AMOUNT−DISCOUNT_AMOUNT`, preservant descomptes comercials i snapshot fiscal. NOMÉS s'ha d'invocar des del backend d'inscripció abans d'oferir el TPV; el formulari actual NO hi està connectat.
- `NovicePromotionSecretaryDecisionProjector::projectDecision` ([codi](../../sif/src/Service/NovicePromotionSecretaryDecisionProjector.php)): després de l'acció autenticada i del commit del llegat, exigeix `recent_titulat.VALIDAT=1` (SIF `discount_validation.STATUS=VALIDATED`) o `=2` (`REJECTED`), compara JASOM i identitat del participant, registra actor i instant i obre `commercial_operation.STATUS=READY_FOR_PAYMENT`. Si `VALIDAT=0`, no projectar decisió, no obrir cobrament. SIF i llegat NO comparteixen una transacció distribuïda. El servei NO s'ha integrat amb l'acció real de secretaria.
- `NovicePromotionInvoiceLinkService::attach` ([codi](../../sif/src/Service/NovicePromotionInvoiceLinkService.php)): després del commit d'`InvoiceService`, requereix coincidència exacta de `fact_rels.SOURCE_TYPE=INSCRIPCIO` i `SOURCE_ID` entre cada factura i l'operació JASOM preparada. El camp singular `commercial_operation.UUID_FACTURA` conserva la **factura d'origen inicial**; les altres fraccions facturades s'agrupen pel mateix `fact_rels.SOURCE_ID`, sense sumar múltiples vegades la mateixa factura. Si `SUM(factura.TOTAL)` és inferior al net aprovat o no totes les factures estan pagades, queda `PAYMENT_PENDING`; quan coincideix i s'han cobrat íntegrament, l'operació passa a `PAID`. Si no hi ha operació preparada, resultat operatiu **`NOT_STAGED`**, NO un nou estat de BD ni prova que no s'hagi sol·licitat novell.
- `NovicePromotionGrantService::issueForOperation`: valida per cada factura ordinària d'origen F1/F2 `ESTAT_FACTURA=ISSUED`, `ESTAT_COBRAMENT=PAID` i `CHARGE` confirmat net de `REFUND` igual al seu total; suma factures i cobraments i exigeix import original igual al `NET_AMOUNT` de JASOM. Rebutja imports mixtos COMPENSATION/CHARGE sense tractament de negoci definit. L'event `ISSUE`, `ORIGINAL_CASH_AMOUNT`, `AVAILABLE_AMOUNT` i `EXPIRES_AT` es persisteixen en una sola transacció amb UNIQUE per persona. No confondre l'alta d'un dret en estat `ISSUED` amb un codi efectivament activat o notificat.
- `NovicePromotionGrantReconciler::run` ([codi](../../sif/src/Service/NovicePromotionGrantReconciler.php)) recorre només operacions SIF JASOM amb aprovació i factura cobrada, sense dret enllaçat. [CLI de test](../../sif/scripts/reconcile-novice-promotions.php) exigeix `SIF_ENV=test` i BD real `sif_test*`. No és un cron de producció, no reconstitueix les dades absent del llegat ni substitueix la porta de pagament al servidor.
### UC-111 · Verificació de correu i lliurament privat — tall 6

- `novice_promotion_email_challenge` ([migració 000011](../../sif/database/migrations/2026_09_23_000011_add_novice_promotion_email_challenge.sql)): `UUID_CHALLENGE` (PK), `UUID_ENTITLEMENT` (FK dret), `HOLDER_PARTY_KEY` (titular autenticat), `EMAIL` (adreça a verificar), `TOKEN_HASH` (SHA-256 d'un secret de 256 bits lligat a dret i desafiament; mai secret en clar), `CREATED_AT`, `EXPIRES_AT` (+15 minuts), `SENT_AT` (acceptació del transport de verificació, NO prova de lliurament), `CONSUMED_AT`, `INVALIDATED_AT` i `FAILED_ATTEMPTS` (0..5). Límit aplicatiu: 3 sol·licituds per dret i hora. Un nou desafiament invalida els previs pendents. Cap columna d'aquest esquema és un codi de bescanvi ni una transacció bancària.
- `novice_promotion_verified_recipient` (migració 000010): fila inserida/actualitzada únicament després de `NovicePromotionEmailVerificationService::confirm` amb repte correcte i titular autenticat; `VERIFICATION_REF=UUID_CHALLENGE`, `VERIFIED_AT` i `RECORDED_BY` preserven l'evidència de control de bústia. No importar automàticament `inscripcions.CORREU` com a verificat. El servei no autentica la sessió: aquest control i l'endpoint de confirmació encara són PENDENTS.
- `novice_promotion_code_outbox`: `CLAIM_ID`, `CLAIMED_AT`, `NEXT_ATTEMPT_AT` identifiquen la reserva recuperable; `WRAP_KEY_VERSION` identifica la clau externa necessària per obrir `TOKEN_CIPHERTEXT` (AES-256-GCM, vinculació a UUID del dret) i verificar `commercial_entitlement.CODE_HASH` abans de donar el token **només a un mailer privat**. `STATUS=SENDING` no garanteix recepció; `SENT` vol dir acceptació declarada pel proveïdor. [NovicePromotionPrivateMailWorker](../../sif/src/Service/NovicePromotionPrivateMailWorker.php) és una orquestració interna preparada, sense adapter SMTP o cron, sense missatge enviat, i sense implementar consum/romanent.
### UC-111 · Aplicacions múltiples del saldo novell — tall 7 (25/09/2026)

- `novice_promotion_application` ([migració 000012](../../sif/database/migrations/2026_09_25_000012_add_novice_promotion_application.sql)): una fila PER MATRÍCULA de destinació. `UUID_APPLICATION` (PK), `UUID_ENTITLEMENT` (FK dret novell), `UUID_DESTINATION_OPERATION` (FK i UNIQUE: com a màxim una aplicació novell per operació de destí), `UUID_DESTINATION_FACTURA` (FK opcional, només després de conciliació), `IDEMPOTENCY_KEY` UNIQUE, `REQUEST_FINGERPRINT` (hash de codi+titular+destí+tria d'import sense exposar el codi), `AMOUNT`, `DESTINATION_ORDINARY_NET_AMOUNT`, `STATUS`, `RESERVED_AT`, `RESERVATION_EXPIRES_AT`, `APPLIED_AT`, `RELEASED_AT`, `REVERSED_AT`, `REASON_CODE`. Imports decimal(12,2), `AMOUNT > 0`. Reserva i aplicació NO són `payment_transaction` ni cap `credit_balance` d'efectiu.
- `novice_promotion_application.STATUS`: `RESERVED` = valor provisionalment DESCOMPTAT de `novice_promotion_grant.AVAILABLE_AMOUNT`, bloquejant doble despesa; `APPLIED` = factura i liquidació final concordants, l'import NO es torna a restar; `RELEASED` = reserva sense intenció bancària/factura, import retornat UNA sola vegada al dret original i event registrat; `REVERSED` = estat PREVIST al model sense implementació de reversió. La reversió d'una matrícula facturada requereix canvi/baixa i documentació fiscal, no un UPDATE de reserva; saldos de baixa derivats tenen procedència i vigència pròpies.
- `NovicePromotionRedemptionService::reserve` ([codi intern](../../sif/src/Service/NovicePromotionRedemptionService.php)): exigeix codi hash i `HOLDER_PARTY_KEY` derivat de sessió autenticada, dret `ACTIVE`, outbox `SENT`, vigència i JASOM íntegrament cobrat; destinació CURS diferent/posterior i participant coincident, `STATUS=READY_FOR_PAYMENT`, sense `UUID_INTENT` ni `UUID_FACTURA`, snapshot autoritatiu `novice_promotion_quote` de preu NET després de descomptes ordinaris. La reserva descompta provisionalment amb bloqueig de files i escriu event d'auditoria; la durada s'ha d'alinear amb la intenció de pagament final del backend (l'API del servei no accepta venciments de més d'un dia).
- `NovicePromotionRedemptionService::confirmApplied`: exigeix snapshot FINAL del motor de preus `novice_promotion_application` amb el mateix UUID dret/aplicació, import i net ordinari, `commercial_operation.NET_AMOUNT` final i `factura.TOTAL` concordants, i pagaments `CHARGE` confirmats nets de `REFUND` iguals al residual. Cas de residual zero: només amb una factura legalment emesa de total 0 i liquidada en el SIF sense CHARGE fictici; l'adaptador de facturació d'aquest cas és PENDENT. La marca `APPLIED` NO torna a descomptar saldo. Una aplicació amb factura/intent ja iniciat NO es pot alliberar sense conciliació.
- `NovicePromotionRedemptionService::release`: només reserva `RESERVED` de matrícula amb `UUID_INTENT`/`UUID_FACTURA` buits i sense relació fiscal `fact_rels`; motiu `PAYMENT_FAILED`, `INTENT_EXPIRED` (només venciment real) o `CHECKOUT_CANCELLED`, després de confirmació de fracàs per un orquestrador INTERN de cobrament. Un navegador no és font acreditada per a retorn de saldo. Si l'operació té intenció Redsys pendent, cal un pas diferent de conciliació de l'estat terminal abans de permetre l'alliberament; cap d'aquests adapters de checkout existeix encara en el nou tall.
- `NovicePromotionAmountPolicy` ([codi](../../sif/src/Domain/NovicePromotionAmountPolicy.php)): política d'imports exactes en cèntims. `min(AVAILABLE_AMOUNT, ordinary net after other discounts)` per aplicació completa del valor possible; opcionalment import parcial positiu sense sobrepassar preu/saldo; romanent sempre en el MATEIX dret i amb la caducitat original. [Sis tests UNITARIS preparats](../../sif/tests/Unit/NovicePromotionAmountPolicyTest.php) sense dependència MySQL; proves BD/fiscals ajornades per decisió de la usuària.
### UC-111 · Procedència de drets derivats, traspassos i reclamació JASOM — tall 8

- `novice_promotion_derived_balance` ([000013](../../sif/database/migrations/2026_09_25_000013_add_novice_promotion_lineage.sql)): saldo de BAIXA diferent del dret inicial novell. `ROOT_UUID_ENTITLEMENT` arrel JASOM, `PARENT_UUID_DERIVED_BALANCE` per cadenes de baixes, `SOURCE_UUID_APPLICATION` (ús novell original) o `SOURCE_UUID_DERIVED_APPLICATION` (ús de dret derivat), `UUID_DESTINATION_OPERATION` del curs cancel·lat, `UUID_RECTIFICATIVE_FACTURA` de l'operació fiscal real, titular canònic, `PROMOTIONAL_ORIGIN_AMOUNT` i `AVAILABLE_PROMOTIONAL_AMOUNT` únicament del component PROMOCIONAL, `POLICY_SNAPSHOT_JSON`, timestamps i clau idempotent. `STATUS=PENDING_FISCAL_REVIEW` NO es pot gastar ni comptar com a concessió real; quan el futur servei de baixa acrediti la rectificativa i l'import elegible, podrà crear la concessió i fixar `ISSUED_AT/EXPIRES_AT` per UN ANY PROPI. Les FK/UNIQUE/CHECK del model no substitueixen les regles d'integració ni garanteixen que la rectificativa estigui emesa per si soles.
- `novice_promotion_derived_application`: un registre de cada ús sobre un dret DERIVAT amb referència explícita a la seva arrel JASOM, curs de destinació, eventual factura, import, dates, idempotència i estats `RESERVED/APPLIED/TRANSFERRED/CONVERTED_TO_DERIVED/RELEASED/CANCELLED`. Cadascun dels estats de traspàs o derivació exigeix futura traça fiscal i validació de la cadena; aquesta taula i els estats estan MODELATS però encara no tenen un servei PHP de consum derivat implementat. Valor promocional NO és ingrés bancari ni credit_balance d'efectiu.
- `novice_promotion_application_transfer`: un canvi de curs atribueix al nou destí el MATEIX import que havia estat promocionalment aplicat, sense duplicar el consum ni retornar-lo al dret inicial. `UUID_ORIGINAL_APPLICATION` o `UUID_DERIVED_APPLICATION` és l'origen d'un PRIMER traspàs; `PREVIOUS_UUID_TRANSFER` és l'origen dels següents traspassos en cadena, exclusiu respecte als camps de primer origen. Conservar `FROM_UUID_OPERATION`, `TO_UUID_OPERATION`, `UUID_RECTIFICATIVE_FACTURA`, import, estat `PENDING_FISCAL_REVIEW/CONFIRMED/CANCELLED`, idempotència i timestamps. No acceptar una nova destinació amb net inferior al valor de promoció sense el procediment separat d'ajust econòmic/fiscal; NO existeix encara un mètode que confirmi transfers ni que emeti rectificatives.
- `NovicePromotionDestinationAdjustmentPolicy` ([codi](../../sif/src/Domain/NovicePromotionDestinationAdjustmentPolicy.php)): calcula de manera PURA i explícita el component de promoció derivada i el component monetari elegible de baixa a partir de valors JA APROVATS pel circuit de baixa, i limita cadascun pel valor real del seu origen; la baixa pot donar un dret PROMOCIONAL separat amb venciment propi nou d'un any, però no allarga el dret de JASOM ni transforma promoció en CHARGE/REFUND de diners. En canvi de curs manté la promoció atribuint-la al nou destí sense nou consum.
- `NovicePromotionLineagePolicy` ([codi](../../sif/src/Domain/NovicePromotionLineagePolicy.php)): pla PURAMENT LÒGIC a partir d'un snapshot de drets i aplicacions amb proveniència verificada. `cancel_available` inclou romanent de JASOM i descendents; `recover_active_applications` conté NOMÉS ús promocional actualment aplicat a matrícula activa i ja NO substituït per traspàs o dret derivat. `REPLACED_BY_DERIVED/REPLACED_BY_TRANSFER` exclouen la reclamació històrica de l'aplicació predecessora; `RESERVED` no conciliat i grafs orfes/amb imports duplicats bloquegen el pla. `review_required=true` NO és una devolució ni una reclamació executada. [Tretze proves pures de procedència](../../sif/tests/Unit/NovicePromotionLineagePolicyTest.php) i [set d'ajust de baixa](../../sif/tests/Unit/NovicePromotionDestinationAdjustmentPolicyTest.php) escrites; MySQL ajornat expressament.
### UC-111 · Revisió de baixa/traspàs contra rectificativa real — tall 9

- [NovicePromotionRectificationEvidencePolicy](../../sif/src/Domain/NovicePromotionRectificationEvidencePolicy.php) contrasta factura original `TIPUS_FACTURA=F1/F2`, factura rectificativa `R1–R5`, ambdues `ESTAT_FACTURA=ISSUED`, dates i relació EXACTA a `factura_rectificacio` amb motiu i mode. Aquest enllaç no és aprovació comercial de l'import reconegut.
- `NovicePromotionDestinationCancellationReviewService::stageOriginalApplicationReview`: només una aplicació original `APPLIED` del mateix titular, factura ordinària enllaçada a l'operació de matrícula i moviments `CHARGE−REFUND` confirmats amb import coherent amb l'aplicació. El desglossament aprovat COM A PROPOSTA del backend es limita per l'ús promocional original i els diners externs, mai genera `credit_balance` o devolució. `novice_promotion_derived_balance.STATUS=PENDING_FISCAL_REVIEW`, `AVAILABLE_PROMOTIONAL_AMOUNT=0`, `ISSUED_AT/EXPIRES_AT=NULL` = dret NO gastable. L'activació i nova caducitat d'un any necessiten una transició separada encara no implementada.
- `NovicePromotionCourseTransferReviewService::stageFirstTransfer`: mateix titular al curs antic/nou, curs diferent en `READY_FOR_PAYMENT` i sense intenció/factura ni promoció addicional, snapshot de preu ordinari de SIF, import transferible íntegre i rectificativa vinculada al curs original. `novice_promotion_application_transfer.STATUS=PENDING_FISCAL_REVIEW` no modifica l'aplicació `APPLIED` original ni acredita un canvi complet.
- [Migració 000014](../../sif/database/migrations/2026_09_25_000014_add_novice_promotion_transfer_review_evidence.sql): `novice_promotion_application_transfer.REVIEW_ACTOR_ID` i `.POLICY_EVIDENCE_REF` conserven qui sol·licita la revisió i la política invocada. Els camps són informatius mentre no hi hagi autenticació i aprovació externa acreditada; la seva mera presència no implica consentiment ni autorització. Canvis successius i derivacions des de drets derivats romanen per integrar.

- `novice_promotion_derived_balance.STATUS=REJECTED` ([migració 000015](../../sif/database/migrations/2026_09_25_000015_reject_pending_novice_derived_review.sql)): proposta de baixa DENEGADA sense concessió prèvia, romanent 0, `ISSUED_AT=NULL`, `EXPIRES_AT=NULL`; diferent de `CANCELLED` sobre un dret que sí havia estat concedit. `rejectPendingReview` conserva `review_decision.reason_code`, `reviewed_by` i `reviewed_at_utc` al `POLICY_SNAPSHOT_JSON` amb idempotència. Cap denegació no recrea una promoció original, un `CHARGE` ni una rectificativa.
### UC-111 · Transicions de concessió de baixa i confirmació de primer traspàs — tall 10

- `NovicePromotionAdjustmentApprovalSourceInterface` ([contracte PHP](../../sif/src/Service/NovicePromotionAdjustmentApprovalSourceInterface.php)): consultes internes `approvedCancellation(UUID_DERIVED_BALANCE)` i `approvedFirstTransfer(UUID_TRANSFER)` han de retornar una decisió FINAL `APPROVED` AUTENTICADA i lligada a UUID revisió, identificador únic de decisió, actor verificat, referència de política, data posterior a la proposta, aplicació, rectificativa i import exacte. **NO s'ha implementat encara un adaptador real d'aquesta font**, ni endpoint segur de secretaria. Una dada de formulari/actor lliure o l'existència de `factura_rectificacio` NO substitueixen una decisió.
- `novice_promotion_application.STATUS=REVERSED`: quan s'activa una baixa original aprovada, `REASON_CODE=CONVERTED_TO_DERIVED`, `REVERSED_AT` marca que aquest ús és història de procedència i que la promoció consumida continua com a dret derivat separat. En primer canvi de curs confirmat, `REASON_CODE=TRANSFERRED_TO_COURSE` indica que el MATEIX import promocional continua al `novice_promotion_application_transfer` confirmat del curs successor. La transició NO torna valor a `novice_promotion_grant.AVAILABLE_AMOUNT` ni genera un segon CHARGE. Per construir el futur pla de devolució JASOM s'han d'interpretar conjuntament STATUS+REASON_CODE+registre derivat/traspàs CONFIRMAT; la lectura directa del status de l'aplicació original és insuficient.
- `NovicePromotionDerivedBalanceActivationService::activateApprovedOriginalCancellation` ([codi](../../sif/src/Service/NovicePromotionDerivedBalanceActivationService.php)): comprova aprovació independent amb [política de vinculació exacta](../../sif/src/Domain/NovicePromotionApprovedCancellationPolicy.php), dret arrel VALIDATED/ACTIVE, titular, expedient PENDING, factura original/rectificativa emeses i enllaç `factura_rectificacio`, JASOM íntegrament cobrat a totes les F1/F2 i imports aprovats sense excedir les fonts. En UNA transacció marca el primer consum històric `REVERSED/CONVERTED_TO_DERIVED`, activa el saldo de baixa `ACTIVE` amb romanent PROMOCIONAL elegible i `ISSUED_AT/EXPIRES_AT` propis (+1 any), snapshot de decisió i `commercial_entitlement_event.ACTION=DERIVED_ACTIVATE` al dret original amb UUID del derivat. Diners reals proposats romanen en circuit de caixa separat, sense que aquest servei emeti cap devolució o crèdit monetari.
- `novice_promotion_application_transfer` ([migració 000016](../../sif/database/migrations/2026_09_25_000016_confirm_novice_promotion_course_transfer.sql)): `UUID_DESTINATION_FACTURA` (FK factura final curs nou), `FINAL_NET_AMOUNT`, `ORDINARY_NET_BEFORE_PROMOTION`, `APPROVAL_DECISION_ID` (UNIQUE), `APPROVED_BY`, `APPROVED_AT`, `CONFIRMED_AT`; `TO_UUID_OPERATION` UNIQUE impedeix dos traspassos diferents al mateix destí. Per `STATUS=CONFIRMED`, el CHECK nou requereix prova de factura final, aprovació i relació `FINAL_NET_AMOUNT = ORDINARY_NET_BEFORE_PROMOTION − AMOUNT`. Revisió `PENDING_FISCAL_REVIEW` no és encara un traspàs actiu.
- `NovicePromotionFirstTransferConfirmationService::confirmApprovedFirstTransfer` ([codi](../../sif/src/Service/NovicePromotionFirstTransferConfirmationService.php)): només primer traspàs de l'aplicació novell original APPLIED, decisió FINAL vinculada amb [política pura](../../sif/src/Domain/NovicePromotionApprovedTransferPolicy.php), rectificativa del destí antic, participant invariant, factura nova F1/F2 final emesa/PAID, snapshot `novice_promotion_transfer` coherent i CHARGE−REFUND confirmats del residual exacte; torna a comprovar que JASOM continua completament pagat. Tanca l'aplicació antiga com `REVERSED/TRANSFERRED_TO_COURSE`, confirma el traspàs i escriu event `TRANSFER`, sense segona despesa promocional, sense crear factures, intencions Redsys o pagaments. **Integracions pendents:** emissor de factura residual 0 vàlida sense pagament inventat; segon canvi del curs successor, baixa del curs traspassat, aprovació autenticada i lector de procedència per a devolució de JASOM.
- Proves del tall: [cinc unitàries pures de vinculació d'aprovació de baixa](../../sif/tests/Unit/NovicePromotionApprovedCancellationPolicyTest.php) i [cinc de confirmació de primer traspàs](../../sif/tests/Unit/NovicePromotionApprovedTransferPolicyTest.php) ESCRITES, encara NO executades. Proves MySQL i migracions noves no executades, expressament ajornades.

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

## 13. Valors dels cicles ampliats

### commercial_operation_line.LINE_TYPE

- `COURSE`, `WORKSHOP`, `PACK`, `PACK_COMPONENT`, `GROUP_MEMBER`, `GIFT`,
  `SERVICE`, `ADJUSTMENT`.

`PARENT_UUID_LINE` és obligatori per `PACK_COMPONENT` i nul per una línia arrel.

### commercial_operation_line.STATUS

- `DRAFT`, `RESERVED`, `READY`, `LOCKED`, `MATERIALISED`, `CANCELLED`,
  `INCIDENT`.

### capacity_reservation.STATUS

- `HELD`, `WAITLISTED`, `CONFIRMED`, `EXPIRED`, `RELEASED`, `CANCELLED`,
  `INCIDENT`.

`HELD` ha de tenir `EXPIRES_AT`; només `CONFIRMED` consumeix plaça estable. Les
transicions usen `LOCK_VERSION` i clau idempotent.

### commercial_entitlement.ENTITLEMENT_TYPE

- `PROMOTION_CODE`, `FUTURE_DISCOUNT`, `GIFT`, `COMMERCIAL_CREDIT`.

### commercial_entitlement.STATUS

- `ISSUED`, `ACTIVE`, `RESERVED`, `CONSUMED`, `EXPIRED`, `CANCELLED`,
  `REVERSED`, `INCIDENT`.

### commercial_entitlement_event.ACTION

- `ISSUE`, `ACTIVATE`, `VALIDATE`, `RESERVE`, `RELEASE`, `CONSUME`, `EXPIRE`,
  `CANCEL`, `REVERSE`, `TRANSFER_REJECTED`, `ACCESS_DENIED`.

### enrollment_import_run.STATUS

- `PENDING`, `VALIDATING`, `PROCESSING`, `PARTIAL`, `COMPLETED`, `FAILED`,
  `CANCELLED`.

### enrollment_import_item.STATUS

- `PENDING`, `CREATED`, `REUSED`, `REJECTED`, `FAILED`, `SKIPPED`.

### master_data_change_request.STATUS i personal_data_change_request.STATUS

- `REQUESTED`, `UNDER_REVIEW`, `APPROVED`, `REJECTED`, `APPLYING`, `PARTIAL`,
  `COMPLETED`, `CANCELLED`, `INCIDENT`.

### electronic_invoice_delivery.STATUS

- `PENDING`, `GENERATING`, `READY`, `SENDING`, `DELIVERED`, `RETRY`, `FAILED`,
  `DEAD_LETTER`, `CANCELLED`.

### academic_economic_state_event.ACTION

- `GRANT_ACCESS`, `REVOKE_ACCESS`, `ENROL_MOODLE`, `UNENROL_MOODLE`,
  `MARK_PASSED`, `MARK_NOT_PASSED`, `ISSUE_CERTIFICATE`,
  `BLOCK_CERTIFICATE`, `RECONCILE`.

Els valors nous són contracte inicial de disseny. Qualsevol ampliació exigeix
migració/documentació, compatibilitat de lectors i prova; no s'admeten valors
lliures creats per una pantalla.

## 14. Valors de consentiment, identitat i coherència entre sistemes

### communication_consent.STATUS

- `PENDING_CONFIRMATION`
- `GRANTED`
- `DENIED`
- `WITHDRAWN`
- `EXPIRED`
- `SUPERSEDED`
- `INCIDENT`

`GRANTED` exigeix finalitat, canal, abast, versió del text, font, data i event
amb `EVIDENCE_HASH`. Absència de resposta no equival a consentiment.

### communication_consent_event.ACTION

- `REQUEST`, `CONFIRM`, `GRANT`, `DENY`, `WITHDRAW`, `RENEW`, `EXPIRE`,
  `SUPERSEDE`, `PROPAGATE`, `PROPAGATION_FAILED`.

### external_identity_link.STATUS

- `PROVISIONAL`, `VERIFIED`, `SUSPENDED`, `SUPERSEDED`, `REVOKED`, `INCIDENT`.

### identity_conflict_case.STATUS i DECISION

- estats: `OPEN`, `UNDER_REVIEW`, `WAITING_EVIDENCE`, `RESOLVED`, `REJECTED`,
  `CANCELLED`, `INCIDENT`;
- decisions: `LINK`, `KEEP_SEPARATE`, `RELINK`, `SUSPEND`, `ESCALATE`.

No es permet `LINK` automàtic si la coincidència pot barrejar participants,
pagadors, receptors, factures, pagaments o accessos acadèmics.

### edition_lifecycle_event.ACTION i STATUS_AFTER

- accions: `ACTIVATE`, `MARK_PENDING`, `POSTPONE`, `CLOSE`, `CANCEL`, `REOPEN`;
- estats: `DRAFT`, `PENDING`, `ACTIVE`, `POSTPONED`, `CLOSED`, `CANCELLED`.

### edition_operation_impact.REQUIRED_ACTION

- `KEEP`, `MOVE_EDITION`, `CANCEL_ENROLLMENT`, `REPRICE`, `RELEASE_CAPACITY`,
  `REVOKE_ACCESS`, `REFUND_REVIEW`, `CREDIT_REVIEW`, `FISCAL_REVIEW`,
  `MANUAL_REVIEW`.

`ECONOMIC_DECISION` i `FISCAL_DECISION` no admeten text lliure: han de
referenciar les classificacions canòniques de devolució/saldo/compensació i de
rectificativa/complementària/anul·lació/subsanació/cap efecte.

### address_validation_case.STATUS

- `PENDING`, `AUTO_MATCHED`, `UNDER_REVIEW`, `APPROVED`, `REJECTED`,
  `PROPAGATING`, `COMPLETED`, `CANCELLED`, `INCIDENT`.

### academic_reconciliation_item.DIFFERENCE_TYPE

- `MISSING_IN_PRISMA`, `MISSING_IN_MOODLE`, `EMAIL_MISMATCH`,
  `IDENTITY_MISMATCH`, `ROLE_MISMATCH`, `ENROLMENT_MISMATCH`,
  `COURSE_MISMATCH`, `CLASSROOM_MISMATCH`, `VISIBILITY_MISMATCH`,
  `DUPLICATE_EXTERNAL_USER`.

### academic_reconciliation_item.RESOLUTION_STATUS

- `PENDING`, `AUTO_RESOLVED`, `MANUAL_REVIEW`, `RESOLVED`, `REJECTED`,
  `RETRY`, `FAILED`, `INCIDENT`.

Cada reconciliació declara autoritat per camp i sistema. No es pot usar cap
d'aquests valors per inferir o modificar un pagament o una factura.
