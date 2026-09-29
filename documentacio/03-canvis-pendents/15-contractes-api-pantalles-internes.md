# Contractes API de pantalles internes

Data de tall: 2026-09-23

Estat: contracte proposat i alineat amb els builders/serveis SIF existents; endpoints interns pendents d'implementar.

## 1. Abast i criteri

Aquest document fixa les peticions i respostes entre les pantalles d'intranet i la capa interna que adapta el SIF. No descriu una API publica ni autoritza a cridar directament `api/factures/issue.php` o `api/payments/register.php` des del navegador.

Principi de frontera:

```text
formulari UI -> DTO de pantalla -> endpoint intern autenticat
             -> validacio/autoritzacio -> payload SIF derivat
             -> servei SIF -> DTO de resposta UI
```

El client no envia ni decideix `actor`, `role`, `source_channel`, `created_by`, `series`, `movement_type`, `allocation_type`, `emesa_abans_cobrament` ni accions autoritzades. El servidor els deriva de la sessio, la ruta i el cas validat.

## 2. Convencions HTTP

### 2.1. Capcaleres

| Capcalera | Us |
|---|---|
| `Content-Type: application/json` | Obligatoria en `POST` |
| `Accept: application/json` | Obligatoria |
| `X-CSRF-Token` | Obligatoria en mutacions iniciades des de sessio web |
| `X-Request-Id` | Opcional al client; el servidor en genera un si falta |
| `Idempotency-Key` | Obligatoria a `confirm`; ha de coincidir amb el preview |

Les credencials de servei SIF, tokens complets i rols no viatgen en JavaScript ni en camps ocults.

### 2.2. Resposta comuna

```json
{
  "ok": true,
  "request_id": "6a927e7d-9dc8-4f22-a5d0-269f156a3561",
  "result": {},
  "notices": [],
  "audit_event_id": null
}
```

Cada avis te aquesta forma:

```json
{
  "code": "PAYMENT_WILL_BE_REGISTERED",
  "severity": "INFO",
  "message": "El cobrament s'associara a la factura existent.",
  "blocking": false,
  "required_action": null,
  "field": null
}
```

### 2.3. Errors i estats HTTP

| HTTP | Significat |
|---|---|
| `200` | Consulta, preview o reintent idempotent correcte |
| `201` | Confirmacio que crea factura, pagament o rectificativa |
| `400` | JSON o format de peticio invalid |
| `401` | Sessio absent o caducada |
| `403` | Permis o CSRF invalid |
| `404` | Recurs no disponible per al subjecte; resposta neutra en portals |
| `409` | Estat canviat, clau idempotent amb payload diferent o preview reutilitzat |
| `422` | Validacio funcional/fiscal bloquejant |
| `503` | SIF no disponible o dependencia temporalment inaccessible |

Resposta d'error:

```json
{
  "ok": false,
  "request_id": "6a927e7d-9dc8-4f22-a5d0-269f156a3561",
  "error": {
    "code": "PAYMENT_AMOUNT_INVALID",
    "message": "L'import no es valid.",
    "field": "amount",
    "retryable": false
  },
  "notices": []
}
```

No retornar noms de taula, SQL, stack trace, path, secret o payload intern complet.

## 3. Preview i confirmacio

El preview correcte retorna:

```json
{
  "preview_token": "token-opac",
  "expires_at": "2026-09-23T12:10:00+02:00",
  "state_version": "sha256:...",
  "idempotency_key": "valor-derivat-o-validat",
  "planned_action": "REGISTER_PAYMENT"
}
```

La confirmacio envia nomes:

```json
{
  "preview_token": "token-opac",
  "confirmation": true
}
```

El servidor recupera o verifica les dades congelades del preview. No accepta que confirm torni a enviar imports, receptor, motiu o linies diferents. `PREVIEW_EXPIRED`, `STATE_CHANGED` i `IDEMPOTENCY_CONFLICT` responen `409` sense escriptura.

## 4. Consulta de factures

### 4.1. `GET /api/internal/invoices`

Parametres admesos:

| Parametre | Tipus | Regla |
|---|---|---|
| `query_type` | enum | `UUID`, `NUM_VISIBLE`, `NIF`, `IDPAG`, `BANK_REFERENCE` |
| `query` | string | Un sol criteri actiu, normalitzat al servidor |
| `invoice_status` | enum opcional | Filtre controlat |
| `payment_status` | enum opcional | `PENDING`, `PARTIAL`, `PAID` o estats finals definits |
| `from`, `to` | data ISO opcional | Rang limitat |
| `page` | enter | Minim 1 |
| `page_size` | enter | Maxim 100 |

Resultat:

```json
{
  "items": [
    {
      "uuid_invoice": "uuid",
      "visible_number": "A2026/000123",
      "date": "2026-09-23",
      "recipient_display": "Entitat Exemple",
      "invoice_type": "F1",
      "total": "120.00",
      "currency": "EUR",
      "invoice_status": "ISSUED",
      "payment_status": "PENDING",
      "aeat_status": "PENDING",
      "available_actions": ["VIEW", "REGISTER_PAYMENT"]
    }
  ],
  "pagination": {"page": 1, "page_size": 25, "total": 1}
}
```

### 4.2. `GET /api/internal/invoices/{uuid}`

Retorna DTO explicit de capcalera, receptor, linies, totals, pagaments, documents, relacions, incidencies i historial. `available_actions` inclou objectes amb `code`, `enabled` i `reason`; mai es calcula al navegador.

Els camps interns de hash, payload, path o cua nomes es retornen a rols tecnics expressament autoritzats i en una vista separada.

## 5. Passar pagaments

### 5.1. `POST /api/internal/payments/preview`

Peticio UI:

```json
{
  "invoice": {
    "uuid": "11111111-1111-4111-8111-111111111111",
    "visible_number": "A2026/000123"
  },
  "amount": "120.00",
  "movement_date": "2026-09-23T10:30:00+02:00",
  "method": "TRANSFERENCIA",
  "reference": "TRF900",
  "bank": "CAIXA",
  "notes": "Transferencia validada"
}
```

Regles:

- cal UUID o numero visible; si arriben tots dos han de correspondre a la mateixa factura;
- `amount` es decimal positiu amb dues xifres;
- `method` admet `TRANSFERENCIA` o `MANUAL`, segons `ManualPaymentPayloadBuilder`;
- `movement_date` es ISO a la frontera i es normalitza al format intern;
- referencia i banc son opcionals, pero l'absencia de referencia genera idempotencia per factura/data/import/banc;
- actor, canal, tipus de moviment i assignacio els fixa el servidor.

Mapatge intern:

| DTO UI | Payload SIF |
|---|---|
| `invoice.uuid` | argument `uuidFactura` i `allocations[0].uuid_factura` |
| `amount` | `amount` i `allocations[0].amount` |
| `movement_date` | `movement_date` |
| `method` | `method` |
| `reference` | `reference`; base preferent d'idempotencia |
| `bank` | `bank` |
| `notes` | `notes` |
| derivat | `movement_type=CHARGE`, `source_channel=INTRANET`, `allocation_type=INVOICE_PAYMENT` |

Resposta preview: factura, total/cobrat/pendent actual, import proposat, estat resultant previst, `planned_action`, avisos i token.

### 5.2. `POST /api/internal/payments/confirm`

Executa `ManualPaymentService::registerByUuid()` o `registerByNumVisible()` amb les dades congelades. Resposta `result`:

```json
{
  "uuid_payment": "uuid",
  "uuid_invoice": "uuid",
  "visible_number": "A2026/000123",
  "idempotency_reused": false,
  "payment_status": "PAID"
}
```

`payment_status` es consulta despres de registrar; no es dedueix nomes de la resposta base del servei.

## 6. Factura abans del cobrament

### 6.1. `POST /api/internal/invoices/before-payment/preview`

```json
{
  "reference": "ENTITAT-2026-900",
  "billing": {
    "name": "Entitat Exemple",
    "nif": "B12345678",
    "address": "Carrer Exemple 1",
    "cp": "08001",
    "city": "Barcelona",
    "province": "Barcelona",
    "country": "ES",
    "email": "facturacio@example.test"
  },
  "lines": [
    {
      "concept": "Curs de llengua",
      "detail": "Edicio setembre",
      "quantity": "1.00",
      "unit_price": "120.00",
      "base": "120.00",
      "total": "120.00",
      "source_type": "INSCRIPCIO",
      "source_id": 900
    }
  ],
  "relations": [
    {
      "source_type": "INSCRIPCIO",
      "source_id": 900,
      "factura_relacionada": 900,
      "visible_alumne": 0
    }
  ]
}
```

El servidor calcula/valida totals, serie, tipus i exercici segons configuracio. Com a minim, el payload SIF final conte `idempotency_key`, `series`, `type`, `source_channel`, `billing`, `totals` i `lines`. `InvoiceBeforePaymentPayloadBuilder` força `source_channel=INTRANET`, `emesa_abans_cobrament=1`, `created_by` de sessio i elimina qualsevol `payment`.

Qualsevol camp `payment` rebut del client provoca `422 PAYMENT_NOT_ALLOWED_BEFORE_COLLECTION`.

### 6.2. `POST /api/internal/invoices/before-payment/confirm`

Executa `InvoiceBeforePaymentService::issueBeforePayment()`. Resposta:

```json
{
  "uuid_invoice": "uuid",
  "visible_number": "A2026/000124",
  "idempotency_reused": false,
  "invoice_status": "ISSUED",
  "payment_status": "PENDING",
  "emitted_before_payment": true
}
```

No retorna `uuid_payment` ni crea `payment_transaction`. El cobrament posterior passa pel contracte de l'apartat 5.

## 7. Rectificacio

### 7.1. `POST /api/internal/invoices/{uuid}/rectifications/preview`

```json
{
  "amount": "-40.00",
  "reason": "DEVOLUCIO_PARCIAL",
  "mode": "DIFERENCIES",
  "concept": "Rectificacio parcial curs",
  "detail": "Retorn parcial per baixa",
  "reference": "RECT-EXP-900"
}
```

Regles alineades amb `ManualRectificationPayloadBuilder`:

- `amount` numeric i diferent de zero; pot ser negatiu o positiu segons el cas classificat;
- `reason` obligatori i normalitzat a majuscules; el cataleg final ha de ser controlat;
- `mode` nomes `DIFERENCIES` o `SUBSTITUCIO`;
- serie `R`, tipus per defecte `R1`, canal i actor derivats;
- receptor fiscal es copia de l'original i no s'accepta des del client;
- la relacio `RECTIFIES` i l'original es deriven de `{uuid}`.

El preview mostra original i proposta, efecte economic separat i avisos. Una rectificativa no registra automaticament una devolucio.

### 7.2. `POST /api/internal/invoices/{uuid}/rectifications/confirm`

Executa `ManualRectificationService::issueByUuid()`. Resposta:

```json
{
  "uuid_invoice": "uuid-rectificativa",
  "visible_number": "R2026/000001",
  "uuid_rectified_invoice": "uuid-original",
  "rectified_visible_number": "A2026/000123",
  "idempotency_reused": false,
  "original_invoice_status": "RECTIFIED"
}
```

Els casos de `RegistroAnulacion`, subsanacio, devolucio o saldo no reutilitzen aquest endpoint si no corresponen a una rectificativa.

## 8. Resum VERI*FACTU

### `GET /api/internal/verifactu/summary`

```json
{
  "status": "WARNING",
  "updated_at": "2026-09-23T11:30:00+02:00",
  "last_successful_sync_at": "2026-09-23T11:29:40+02:00",
  "counts": {
    "queue_pending": 2,
    "errors": 0,
    "incidents_open": 1,
    "documents_pending": 0
  },
  "required_action": "Revisar una incidencia oberta",
  "links": [
    {"code": "OPEN_INCIDENTS", "url": "/sif/incidencies?status=open"}
  ]
}
```

Si el SIF no respon, l'endpoint retorna `503 SIF_UNAVAILABLE` amb l'ultima sincronitzacio valida si es disponible. No retorna comptadors zero com si fossin dades actuals.

## 9. Portal alumne i empresa/responsable

### 9.1. `GET /api/portal/documents`

La identitat i l'abast provenen de sessio externa o token validat. No s'admet `student_id`, `company_id` o llista de participants arbitraris.

Cada item conte identificador opac, numero visible, concepte resumit, data, total, cobrament, disponibilitat del document i accions externes. No conte UUID tecnic, path, hashes interns, cues o dades d'altres participants.

### 9.2. `GET /api/portal/documents/{public_id}/download`

Valida identitat/token, abast, caducitat i revocacio abans de servir el fitxer immutable. Registra acces o denegacio. Una denegacio respon `404 RESOURCE_NOT_AVAILABLE` de forma neutra.

## 10. Codis d'error minims

| Codi | HTTP | Condicio |
|---|---:|---|
| `INVALID_JSON` | 400 | Cos no JSON o estructura invalida |
| `AUTHENTICATION_REQUIRED` | 401 | Sessio absent/caducada |
| `PERMISSION_DENIED` | 403 | Rol o abast insuficient |
| `CSRF_INVALID` | 403 | Token absent o invalid |
| `RESOURCE_NOT_AVAILABLE` | 404 | Recurs absent o no visible externament |
| `STATE_CHANGED` | 409 | Estat diferent del preview |
| `PREVIEW_EXPIRED` | 409 | Preview caducat o ja consumit |
| `IDEMPOTENCY_CONFLICT` | 409 | Mateixa clau amb payload diferent |
| `INVOICE_NOT_FOUND` | 422 | Factura interna no localitzada |
| `INVOICE_NOT_OPERABLE` | 422 | Estat no admet l'accio |
| `PAYMENT_AMOUNT_INVALID` | 422 | Import absent, no numeric o no positiu |
| `PAYMENT_METHOD_INVALID` | 422 | Metode manual no admes |
| `RECIPIENT_INCOMPLETE` | 422 | Receptor fiscal incomplet |
| `PAYMENT_NOT_ALLOWED_BEFORE_COLLECTION` | 422 | Factura previa amb bloc de pagament |
| `RECTIFICATION_MODE_INVALID` | 422 | Mode diferent dels dos admesos |
| `FISCAL_DATA_IMMUTABLE` | 422 | Intent d'edicio directa de factura emesa |
| `SIF_UNAVAILABLE` | 503 | Timeout o dependencia SIF inaccessible |

## 11. Camps sensibles i logs

Es poden registrar `request_id`, actor intern, ruta, accio, UUID intern, referencia parcial, codi d'error, durada i resultat. S'han d'ocultar tokens, CSRF, cookies, credencials de servei, certificat, claus Redsys, NIF/correu complets quan no siguin imprescindibles i contingut complet de documents.

## 12. Compatibilitat i versionat

- prefix o capcalera de versio per al contracte intern abans d'activar produccio;
- afegir camps opcionals es compatible; eliminar, canviar tipus o significat requereix nova versio;
- els codis d'error i `planned_action` son valors estables;
- el client ha d'ignorar camps desconeguts i no assumir que una llista d'accions es completa fora de `available_actions`;
- durant la migracio, l'adaptador pot traduir noms llegats (`pagament`, `dataPag`, `banc`, `obs`, `numFact`) al DTO canonic, pero aquests aliases no formen part del contracte nou.

## 13. Proves de contracte obligatories

- esquema valid i camp obligatori absent per cada endpoint;
- actor/rol injectat pel servidor i camp client ignorat o rebutjat;
- `GET` rebutjat per a mutacions i CSRF obligatori;
- preview sense escriptura i confirm amb mateixa instantania;
- preview caducat, reutilitzat i invalidat per canvi d'estat;
- clau idempotent reutilitzada amb mateix payload i conflicte amb payload diferent;
- import decimal, data ISO i enums invalids;
- error `503` sense fals estat correcte;
- resposta externa sense identificadors o dades fora d'abast;
- correspondencia entre DTO UI i payload observat al servei SIF.

Aquestes proves complementen `SIF-PANT-SEC-*` i les proves funcionals del pla de validacio; no les substitueixen.
