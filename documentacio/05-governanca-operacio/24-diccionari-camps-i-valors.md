# 24 - Diccionari de camps i valors

> Document de referencia per tipificar els camps clau del SIF i evitar valors lliures incoherents.

## 1. Objectiu

Definir camps, significat, valors permesos i taula on viuen.

## 2. Camps inicials a tipificar

### factura.ESTAT_FACTURA

- `ISSUED`: factura emesa.
- `RECTIFIED`: factura rectificada.
- `CANCELLED`: factura cancel·lada/anul·lada fiscalment quan correspongui.

### factura.ESTAT_AEAT

- `PENDING`: pendent d'enviament.
- `SENT`: enviada.
- `ACCEPTED`: acceptada.
- `REJECTED`: rebutjada.
- `RETRY`: pendent de reintent.
- `FAILED`: fallida despres de reintents.

### payment_transaction.TIPUS_MOVIMENT

- `CHARGE`: cobrament.
- `REFUND`: devolucio.
- `COMPENSATION`: compensacio/saldo.

### payment_transaction.METODE

- `REDSYS`
- `TRANSFERENCIA`
- `COMPENSACIO`
- `MANUAL`

### fact_rels.SOURCE_TYPE

- `INSCRIPCIO`
- `PACK`
- `GRUP`
- `REGAL`
- `USOC`
- `ENTITAT`
- `CANVI_CURS`
- `BAIXA`
- `MANUAL`
- `HISTORIC_WEB_FACTURES`

### URL_STATUS

- `ACTIVE`
- `INACTIVE`
- `EXPIRED`
- `PAID`
- `REPLACED`

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
