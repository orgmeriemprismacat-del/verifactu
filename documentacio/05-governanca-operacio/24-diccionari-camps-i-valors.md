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
