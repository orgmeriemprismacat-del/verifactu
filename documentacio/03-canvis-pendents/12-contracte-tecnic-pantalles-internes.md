# Contracte tecnic de pantalles internes

Data de tall: 2026-09-16

Estat: especificacio operativa preparada; implementacio d'intranet i endpoints interns pendent.

## 1. Objectiu i limit

Aquest document converteix els procediments funcionals en un contracte implementable per a:

- `Passar pagaments`;
- `Consulta - Edita - Anula factura`;
- `Generar factura abans de pagar`;
- intranet alumne;
- empresa/responsable;
- permisos, indicadors i avisos VERI*FACTU.

No substitueix les regles fiscals del SIF ni autoritza a implementar codi productiu dins del projecte pont. La implementacio s'ha de fer al repositori real de la intranet i de `pay.prisma.cat`, conservant el SIF com a font de veritat.

## 2. Principis obligatoris

1. La pantalla proposa l'operacio; el SIF valida i decideix el resultat.
2. Cap validacio visible al navegador substitueix la validacio al servidor.
3. `issueInvoice()` es l'unic flux que crea una factura fiscal.
4. `registerPayment()` registra el moviment economic sobre una factura existent i no crea una segona factura.
5. Una factura emesa no es modifica fiscalment en lloc: si cal corregir-la, s'utilitza el flux de rectificacio.
6. Qualsevol accio critica exigeix actor, rol, motiu, referencia idempotent i registre d'auditoria.
7. Els avisos han d'indicar severitat, causa, accio requerida i si l'operacio queda bloquejada.
8. Intranet i canals externs nomes poden mostrar documents autoritzats pel servidor.

## 3. Peces SIF ja disponibles

| Capacitat | Peca existent | Us des de pantalla |
|---|---|---|
| Emetre factura | `InvoiceService::issueInvoice()` | No cridar directament des del navegador; usar endpoint intern autenticat |
| Registrar cobrament | `PaymentService::registerPayment()` | Accio final de `Passar pagaments` quan la factura ja existeix |
| Pagament manual per UUID o numero | `ManualPaymentService` | Adaptador principal de `Passar pagaments` |
| Factura abans del cobrament | `InvoiceBeforePaymentService` | Accio final de `Generar factura abans de pagar` |
| Rectificacio manual | `ManualRectificationService` | Accio fiscal de correccio des de consulta de factura |
| Auditoria d'accions de pagament | `PaymentActionGateway` i `PaymentActionEventRepository` | Envoltar confirmacions i conservar resultat terminal |
| Consulta base per UUID o numero | `ManualPaymentInvoiceRepository` | Base tecnica; falta una consulta de lectura preparada per UI |
| Resposta JSON | `JsonResponse` | Normalitzar exit i error dels endpoints interns |

Els endpoints publics actuals `api/factures/issue.php` i `api/payments/register.php` son peces de baix nivell. No s'han d'exposar directament a la intranet sense autenticacio, autoritzacio, CSRF, auditoria d'actor i contracte de preview.

## 4. Contracte comu de peticio i resposta

### 4.1. Context obligatori d'actor

El servidor ha d'obtenir la identitat de la sessio. El navegador no pot decidir el rol efectiu.

```json
{
  "actor": {
    "user_id": "intern-123",
    "role": "GESTIO_FACTURACIO",
    "session_id": "sessio-servidor",
    "request_id": "uuid-peticio"
  }
}
```

Els camps d'actor retornats a logs provenen de la sessio validada, no del JSON enviat pel client.

### 4.2. Resposta comuna

```json
{
  "ok": true,
  "request_id": "uuid-peticio",
  "result": {},
  "notices": [
    {
      "code": "PAYMENT_WILL_BE_REGISTERED",
      "severity": "INFO",
      "message": "El cobrament s'associara a la factura existent.",
      "blocking": false,
      "required_action": null
    }
  ],
  "audit_event_id": "uuid-event"
}
```

En error, `ok` es `false`, `result` no conte cap resultat fiscal parcial i cada error te un codi estable. Els missatges tecnics, SQL, secrets i traces no es retornen a la UI.

### 4.3. Doble fase

Tota operacio fiscal o economica manual usa dues fases:

1. `preview`: valida permisos i dades, calcula l'accio prevista i retorna avisos; no escriu factura ni pagament.
2. `confirm`: rep la mateixa referencia idempotent i un token curt de preview; torna a validar al servidor i executa una sola vegada.

El token de preview ha d'estar signat o persistit, tenir caducitat curta i quedar invalidat si canvia l'estat de la factura.

## 5. Matriu d'endpoints interns

Les rutes son contractes proposats. Excepte els endpoints de baix nivell ja indicats, consten com a pendents d'implementar.

| Metode i ruta proposada | Pantalla | Servei o font | Estat |
|---|---|---|---|
| `GET /api/internal/invoices` | Consulta de factura | Repositori de consulta UI pendent | Pendent |
| `GET /api/internal/invoices/{uuid}` | Detall i accions disponibles | Factura, pagaments, documents i permisos | Pendent |
| `POST /api/internal/payments/preview` | Passar pagaments | Builder/validador manual sense escriptura | Pendent |
| `POST /api/internal/payments/confirm` | Passar pagaments | `ManualPaymentService` + `PaymentActionGateway` | Pendent |
| `POST /api/internal/invoices/before-payment/preview` | Generar factura abans de pagar | `InvoiceBeforePaymentPayloadBuilder` sense emissio | Pendent |
| `POST /api/internal/invoices/before-payment/confirm` | Generar factura abans de pagar | `InvoiceBeforePaymentService` | Pendent |
| `POST /api/internal/invoices/{uuid}/rectifications/preview` | Edita/Anula | `ManualRectificationPayloadBuilder` | Pendent |
| `POST /api/internal/invoices/{uuid}/rectifications/confirm` | Edita/Anula | `ManualRectificationService` | Pendent |
| `GET /api/internal/verifactu/summary` | Indicador VERI*FACTU | Factures, cua, errors i incidencies SIF | Pendent |
| `GET /api/portal/documents` | Alumne o empresa/responsable | Consulta filtrada per subjecte autoritzat | Pendent |
| `GET /api/portal/documents/{uuid}/download` | Alumne o empresa/responsable | Document immutable amb autoritzacio | Pendent |

## 6. Pantalla `Passar pagaments`

### 6.1. Camps i estats

- cerca per UUID SIF, numero visible, referencia bancaria o referencia llegada autoritzada;
- resum de factura, receptor, total, cobrat, pendent i estat fiscal;
- data, import, metode, referencia i observacions del cobrament;
- actor i rol obtinguts de sessio;
- avisos de duplicat, import excedit, data incoherent o factura no operable;
- boto `Previsualitzar` i boto `Confirmar pagament`, inicialment deshabilitat.

### 6.2. Decisio del servidor

| Situacio | Resultat |
|---|---|
| Existeix factura SIF operable | Proposar `registerPayment()` |
| No existeix factura i el cas permet factura associada al pagament | Proposar `issueInvoice(payment)` segons el flux fiscal del cas |
| Referencia de pagament ja utilitzada | Bloquejar o retornar resultat idempotent existent |
| Import zero, negatiu o superior al maxim admissible | Bloquejar |
| Factura anul·lada, substituida o no autoritzada | Bloquejar |
| Pagament parcial valid | Registrar i recalcular estat de cobrament |

La UI no pot escollir lliurement entre emetre factura i registrar pagament. Ha de mostrar la decisio retornada pel preview.

### 6.3. Resultat visible

Despres de confirmar, mostrar UUID del pagament, factura associada, estat de cobrament, referencia idempotent, avisos no bloquejants i identificador d'auditoria. No reutilitzar el boto amb el mateix preview.

## 7. Pantalla `Generar factura abans de pagar`

### 7.1. Passos

1. Seleccionar inscripcio, operacio o conjunt cobert.
2. Confirmar receptor fiscal i adreca/dades obligatories.
3. Mostrar linies, impostos, total i marca `EMESA_ABANS_COBRAMENT`.
4. Previsualitzar sense bloc `payment`.
5. Confirmar emissio amb referencia idempotent.

### 7.2. Bloquejos

- receptor fiscal incomplet o inconsistent;
- referencia d'origen absent;
- factura previa per la mateixa operacio;
- inclusio accidental d'un bloc de pagament;
- actor sense permis d'emissio;
- canvi de dades entre preview i confirmacio.

El resultat ha d'incloure UUID i numero visible de factura, estat pendent de cobrament i enllac intern al detall. El cobrament posterior passa sempre per `registerPayment()`.

## 8. Pantalla `Consulta - Edita - Anula factura`

### 8.1. Consulta

El detall retorna dades fiscals immutables, estat, pagaments, documents, relacions, incidencies i una llista d'accions calculada pel servidor:

```json
{
  "available_actions": [
    {"code": "VIEW", "enabled": true},
    {"code": "DOWNLOAD_PDF", "enabled": true},
    {"code": "RECTIFY", "enabled": true},
    {"code": "DIRECT_EDIT", "enabled": false, "reason": "FISCAL_DATA_IMMUTABLE"}
  ]
}
```

### 8.2. Edita

Separar visualment:

- dades administratives no fiscals editables, si existeixen i el rol ho permet;
- dades fiscals emeses, sempre de nomes lectura;
- accio `Rectificar` per corregir dades fiscals mitjancant una nova factura rectificativa.

### 8.3. Anula

`Anula` no executa un `UPDATE` destructiu. Obre el flux de rectificacio/anulacio, exigeix motiu controlat, justificacio, preview i confirmacio. El resultat mostra la factura original, la rectificativa, els seus estats i la traça.

## 9. Intranet alumne

La consulta es resol des de la identitat autenticada de l'alumne. El client no envia un `student_id` arbitrari.

Pot veure:

- factures on es receptor o document autoritzat;
- estat de cobrament simplificat;
- PDF o document disponible;
- avisos que requereixin una accio seva.

No pot veure:

- dades completes d'altres participants;
- factures d'empresa o grup quan no n'es receptor;
- logs interns, cua AEAT, hashes, errors tecnics o incidencies internes;
- accions d'emissio, rectificacio o registre manual de pagament.

## 10. Empresa o responsable

No te acces implicit a la intranet principal. L'accés es fa per espai especific autenticat o enllac segur d'un sol us o caducitat limitada.

El servidor valida conjuntament:

- identitat del receptor o responsable;
- relacio activa amb les inscripcions cobertes;
- document concret autoritzat;
- vigencia i us del token;
- absencia de revocacio.

La resposta no ha d'exposar documents o participants fora de la cobertura. Les descàrregues queden auditades.

## 11. Permisos

| Accio | Gestio facturacio | Administracio SIF | Auditor/AEAT | Alumne | Empresa/responsable |
|---|---:|---:|---:|---:|---:|
| Consultar factura autoritzada | Si | Si | Si, nomes lectura | Nomes propia | Nomes coberta |
| Passar pagament | Si | Si | No | No | No |
| Generar factura abans de pagar | Si | Si | No | No | No |
| Rectificar/anul·lar | Segons delegacio | Si | No | No | No |
| Veure incidencies internes | Segons ambit | Si | Si, nomes lectura | No | No |
| Configurar o reprocessar | No per defecte | Si | No | No | No |

La comprovacio es fa a cada endpoint. Ocultar un boto no es una mesura d'autoritzacio suficient.

## 12. Avisos i severitats

| Severitat | Comportament UI | Efecte servidor |
|---|---|---|
| `INFO` | Context visible | No bloqueja |
| `WARNING` | Confirmacio explicita | Pot requerir motiu o segona confirmacio |
| `BLOCKING` | Accio deshabilitada i causa visible | Rebutja l'operacio |
| `INCIDENT` | Crea o enllaca incidencia i dona instruccio operativa | Rebutja o deixa l'operacio en estat controlat |

Codis minims: `DUPLICATE_REFERENCE`, `INVOICE_NOT_FOUND`, `INVOICE_NOT_OPERABLE`, `PAYMENT_AMOUNT_INVALID`, `RECIPIENT_INCOMPLETE`, `FISCAL_DATA_IMMUTABLE`, `PERMISSION_DENIED`, `PREVIEW_EXPIRED`, `STATE_CHANGED` i `SIF_UNAVAILABLE`.

## 13. Indicador VERI*FACTU

`GET /api/internal/verifactu/summary` ha de retornar un resum calculat, no una copia manual:

- estat general `OK`, `WARNING` o `INCIDENT`;
- nombre de pendents de cua;
- errors i incidencies obertes per severitat;
- data de l'ultima actualitzacio;
- enllac autoritzat al panell SIF;
- accio requerida, si existeix.

Una fallada de consulta no es mostra com `OK`; es mostra com a estat desconegut o incidencia de connexio.

## 14. Auditoria i dades sensibles

Per cada preview i confirmacio critica conservar:

- `request_id`, actor, rol i sessio tecnica;
- pantalla i accio;
- UUID o referencia de l'objecte;
- referencia idempotent;
- estat anterior resumit i hash de canvis quan pertoqui;
- avisos retornats;
- resultat `REQUESTED`, `SUCCEEDED`, `REUSED`, `BLOCKED` o `FAILED`;
- codi d'error estable, sense secrets.

No registrar contrasenyes, tokens complets, claus Redsys, certificat privat, dades bancaries completes ni payloads externs sense minimitzacio.

## 15. Criteris d'acceptacio

La capa de pantalles no es considera implementada fins que:

- els endpoints autentiquen, autoritzen i protegeixen contra CSRF/repeticio;
- preview i confirmacio comparteixen referencia idempotent i revaliden estat;
- la UI no pot forçar una accio SIF diferent de la decidida pel servidor;
- els bloquejos documentats es proven tambe contra peticions directes;
- les respostes no filtren dades d'altres alumnes, empreses o grups;
- els esdeveniments d'auditoria tenen resultat terminal;
- passen `SIF-PANT-PAY-*`, `SIF-PANT-FAC-001`, `SIF-PANT-FACT-*`, `SIF-VIS-002` i `SIF-AVI-*`;
- les captures exigides a `23-annex-captures-pantalla.md` queden incorporades a l'expedient.

## 16. Ordre d'implementacio recomanat

1. Autenticacio comuna, permisos i contracte d'errors.
2. Consulta de factura i calcul d'accions disponibles.
3. Preview/confirm de `Passar pagaments` sobre `ManualPaymentService`.
4. Preview/confirm de factura abans del cobrament.
5. Preview/confirm de rectificacio o anulacio.
6. Resum VERI*FACTU i avisos.
7. Consulta alumne i canal empresa/responsable.
8. Proves de servidor, proves de pantalla i captures finals.
