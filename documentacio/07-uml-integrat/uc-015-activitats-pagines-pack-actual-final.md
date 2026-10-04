# UC-015 · Activitats per pàgina i apartat ACTUAL / FINAL

**Data d'auditoria:** 2026-09-29 · **Revalidació final:** 2026-10-02  
**Objectiu:** cobrir RM-037 per a les pantalles i processos implicats en la compra d'un pack.

## Inventari

| ID | Pantalla / procés | ACTUAL | FINAL |
|---|---|---|---|
| PK-A01 | Llistat de packs | filtre d'edició + tots els components oberts | conservar catàleg, sense efecte fiscal |
| PK-A02 | Fitxa de pack | valida totes les edicions obertes | oferta versionada |
| PK-A03 | Formulari inscripció | **POST + `PublicWebMutationAuthorization` (`WEB_ALLOWED_ORIGINS`, X-Requested-With) + Sec-Fetch-Site + REQUEST_ID + revalidació de totes les edicions** | acceptació E2E/replay navegador-preproducció |
| PK-A04 | Alta N inscripcions | snapshot + transacció atòmica + suma exacta + replay idempotent + ordre v1 `DATAI, ID_CURS` implementats | evolució a posició manual/versionada només si negoci ho demana |
| PK-A05 | Creació URL/intenció | **intenció SIF implementada per PACK** | verificador CLI preparat; falta DS_ORDER real |
| PK-A06 | Callback Redsys | **callback SIF únic autoritatiu; callbacks productius legacy eliminats** | preflight/preview/process/verifier preparats; falta execució real |
| PK-A07 | Factura pack | **InvoiceService al flux SIF; callbacks productius d'emissió PACK legacy eliminats** | evidència runtime |
| PK-A08 | Distribució per inscripció | **ledger implementat** | evidència runtime |
| PK-A09 | Confirmació/correu | **enqueue de confirmació a outbox SIF implementat; correu inicial d'alta encara directe al web legacy** | lliurament/retries de l'outbox = UC-58; migració del correu inicial és millora separada |
| PK-A10 | Variant fraccionada | ecommerce PACK força pagament complet | excepció només intranet/reconciliació |

## PK-A01 · Llistat de packs

### ACTUAL
```mermaid
flowchart TD
A[Usuari obre /packs] --> B[mostrar_packs.php]
B --> C[buscantPacksDisponibles]
C --> D[Pack.php]
D --> E{tots els components oberts?}
E -- no --> F[No llistar]
E -- sí --> G[Mostrar pack i filtres]
```

### FINAL
```mermaid
flowchart TD
A[Usuari obre /packs] --> B[Catàleg]
B --> C[Consulta oferta publicada]
C --> D{disponible?}
D -- no --> E[No permetre checkout]
D -- sí --> F[Mostrar versió comercial]
F --> G[Sense efecte fiscal]
```

## PK-A02 · Fitxa del pack

### ACTUAL
```mermaid
flowchart TD
A[pagina_pack] --> B[mostrar_pack.php]
B --> C[InfoPack/Pack]
C --> D[Carregar components/edicions]
D --> E[Comprovar PUBLIC/ESTAT]
E --> F[Mostrar contingut i enllaç inscripció]
```

### FINAL
```mermaid
flowchart TD
A[Fitxa pack] --> B[Carregar oferta versionada]
B --> C[Components ordenats]
C --> D[Edicions i places]
D --> E[Preu i regles comercials]
E --> F{checkout encara vàlid?}
F -- no --> G[Bloquejar compra]
F -- sí --> H[Preparar snapshot]
```

## PK-A03 · Formulari d'inscripció

### ACTUAL
```mermaid
flowchart TD
A[mostrar_inscripcio_packs.php] --> B[InscripcioPack::mostrar]
B --> C[JS obté ID_PACK]
C --> D[JS obté ID_PREU]
D --> E[JS obté preus]
E --> F[Mostra preu]
F --> G[Usuari omple dades]
G --> H[Genera o reutilitza REQUEST_ID a sessionStorage]
H --> I[POST + X-Requested-With a enviarInscripcioPack.php]
I --> J[PublicWebMutationAuthorization]
J --> K{Origin/Referer a WEB_ALLOWED_ORIGINS?}
K -- no --> L[403 sense processar payload]
K -- sí --> M[Sec-Fetch-Site + REQUEST_ID]
```

### FINAL
```mermaid
flowchart TD
A[Formulari] --> B[Usuari envia dades]
B --> C[POST + X-Requested-With]
C --> D[PublicWebMutationAuthorization]
D --> E{WEB_ALLOWED_ORIGINS autoritza Origin/Referer?}
E -- no --> F[403 sense mutació]
E -- sí --> G[Sec-Fetch-Site + REQUEST_ID]
G --> H[Named lock del request]
H --> I{RID ja persistent?}
I -- mateix hash --> J[REUSED: retornar confirmació existent]
I -- hash diferent/inconsistent --> K[409 sense mutació]
I -- no --> L[Validar formulari i rellegir oferta]
L --> M[Backend calcula preu]
M --> N[Valida receptor]
N --> O[Congela snapshot + RID/RH1]
O --> P[Crea IDPAG / operació]
```

**Correcció aplicada 02/10:** `mostrarInscripcioPack.min.js` envia POST + `X-Requested-With` + `REQUEST_ID`. Abans de processar el payload, `PublicWebMutationAuthorization` exigeix Origin/Referer dins `WEB_ALLOWED_ORIGINS`; l'endpoint conserva `Sec-Fetch-Site`. Després resol `RID/RH1` i, només per una alta nova, revalida que totes les edicions segueixin obertes abans de preus o `IDPAG`. Mateix request+hash reutilitza l'alta; mateixa clau amb payload diferent retorna 409.

## PK-A04 · Alta de components

### ACTUAL
```mermaid
flowchart TD
A[REQUEST_ID nou] --> B[Rellegir preus servidor]
B --> C[GET_LOCK allocator IDPAG]
C --> D[MAX IDPAG + 1 sota lock]
D --> E[BEGIN]
E --> F{per cada edició}
F --> G[calcular base/descompte/total]
G --> H[INSERT inscripcions + PACK_ORDINAL + RID/RH1]
H --> F
F -->|fi| I{sum línies = preu PACK?}
I -- no --> J[ROLLBACK]
I -- sí --> K[COMMIT]
K --> L[RELEASE IDPAG + REQUEST locks]
L --> M[retornar hash]
```

### FINAL
```mermaid
flowchart TD
A[Request autoritzat i idempotent] --> B[Rellegir oferta/preus servidor]
B --> C[Generar N components amb ordre v1 estable]
C --> D[BEGIN + inserir N ID_INSC amb snapshot]
D --> E{sum línies = preu PACK?}
E -- no --> F[ROLLBACK]
E -- sí --> G[COMMIT + IDPAG]
G --> H[Checkout crea intenció Redsys SIF]
```


**Revalidació 02/10:** les N insercions es fan dins una única transacció legacy. Una fallada intermèdia provoca rollback; abans del commit s'exigeix `suma(A_PAGAR)=preu PACK` i restant zero en cèntims. El `REQUEST_ID` queda congelat com `RID/RH1`, de manera que un reintent equivalent no entra en aquest bloc sinó que retorna el resultat existent. `PackEnrollmentAtomicityBoundaryTest` i `PackEnrollmentIdempotencyBoundaryTest` blinden els dos contractes.

## PK-A04b · Confirmació d'alta i continuació al pagament

### ACTUAL observat a `main@6c8137f...` abans de la correcció

```mermaid
flowchart TD
A[Alta commitada] --> B[Token legacy IV + HMAC ciphertext + ciphertext]
B --> C[JS posa token al path /packs/confirmacio/TOKEN]
C --> D[pagina_confirmacio_grup_automatic.php]
D --> E[Analytics pot veure page_location amb token]
D --> F[JS envia keyEncr per GET]
F --> G[AJAX extreu token de REQUEST_URI amb substr màgic]
G --> H[Desxifra AES-CBC amb IV]
H --> I[HMAC només sobre ciphertext]
I --> J{HMAC coincideix?}
J -- sí --> K[PagamentGrupAutomatic / dades i continuació pagament]
J -- no --> L[Error 1501]
```

**Troballa SEC-015-01:** l'HMAC no autenticava l'IV i el desxifrat s'executava abans de validar la MAC. En CBC, una alteració de l'IV podia modificar el primer bloc del plaintext sense canviar l'HMAC. A més, el token quedava al path/access logs i el consumidor depenia d'un `substr(...,-16)` lligat al cache-buster de jQuery.

### FINAL implementat al PR #171

```mermaid
flowchart TD
A[Alta commitada] --> B[PackConfirmationToken::encode]
B --> C[AES-256-CBC amb clau derivada]
C --> D[HMAC SHA-256 amb clau MAC separada sobre domini + IV + ciphertext]
D --> E[Payload ID_INSC + issued_at · TTL 24h]
E --> F[Token v2 Base64URL]
F --> G[JS redirigeix /packs/confirmacio/#TOKEN]
G --> H[Pàgina no-store / no-referrer / noindex]
H --> I[Analytics sense page_view automàtic]
I --> J[JS llegeix fragment i envia encodeURIComponent keyEncr]
J --> K[AJAX usa $_GET keyEncr]
K --> L[PackConfirmationToken::decode]
L --> M{MAC vàlida i token vigent?}
M -- no --> N[HTTP 400 + error 1501]
M -- sí --> O[Desxifrar i validar ID_INSC]
O --> P[PagamentGrupAutomatic::mostrarPaginaConfirmacio]
```

Controls addicionals:
- el fragment `#TOKEN` no arriba al servidor ni als access logs en clients actualitzats;
- es manté temporalment fallback de lectura d'un token **v2** al path per absorbir JS antic durant el desplegament;
- els tokens legacy no versionats fallen tancat;
- `.htaccess` admet `/packs/confirmacio/` sense token al path;
- el correu de l'alta ja conté el canal `/pagaments/...` separat, de manera que la caducitat de 24 h del token de confirmació no bloqueja pagaments posteriors;
- `PackConfirmationTokenTest` i `PackConfirmationTokenBoundaryTest` blinden integritat, TTL, URL segura i ordre MAC→decrypt.

## PK-A05 · Intenció de pagament

### ACTUAL
```mermaid
flowchart TD
A[IDPAG pack] --> B[PagamentGrupAutomatic TIPUS_INSC=P]
B --> C[PackPaymentGate rellegeix BD]
C --> D[Valida snapshot/import/receptor/ordinal]
D --> E[SifPaymentIntentClient]
E --> F[Intenció SOURCE_TYPE=PACK]
F --> G[TPV Redsys amb callback SIF]
```

### FINAL
```mermaid
flowchart TD
A[Snapshot] --> B[RedsysPaymentIntentService]
B --> C[DS_ORDER únic]
C --> D[EXPECTED_AMOUNT]
D --> E[SNAPSHOT_JSON]
E --> F[TPV]
```

## PK-A06 · Callback

### ACTUAL
```mermaid
flowchart TD
A[Callback Redsys SIF] --> B[RedsysCallbackService]
B --> C[Validar signatura]
C --> D[Buscar intenció DS_ORDER]
D --> E[Comparar import/moneda/terminal]
E --> F{coherent?}
F -- no --> G[Rebuig/incidència · cap mutació fiscal]
F -- sí --> H[Registrar notificació VALIDATED]
H --> I[Encolar redsys_callback_queue]
I --> J[Worker PACK]
```

> Els dos callbacks productius `realitzaPagamentPackAutomatic.php` s'han eliminat físicament. El fitxer `realitzaPagamentPackAutomaticProva.php` és només un harness de test/preproducció fail-closed.

### FINAL
```mermaid
flowchart TD
A[Callback Redsys] --> B[Validar signatura]
B --> C[Buscar intent DS_ORDER]
C --> D[Comparar import/moneda/terminal]
D --> E{coherent?}
E -- no --> F[Incidència / no mutació fiscal]
E -- sí --> G[Registrar notificació]
G --> H[Encolar worker]
```

## PK-A07 · Emissió factura

### ACTUAL
```mermaid
flowchart TD
A[Worker PACK] --> B[RedsysPackInvoiceService]
B --> C[Builder N línies]
C --> D[Validar total = import Redsys]
D --> E[InvoiceService]
E --> F[seqüència fiscal central]
F --> G[factura + línies + registre + CHARGE]
```

### FINAL
```mermaid
flowchart TD
A[Worker PACK] --> B[Builder N línies]
B --> C[Total línies]
C --> D[Import Redsys validat]
D --> E{iguals?}
E -- no --> F[409 / incidència]
E -- sí --> G[InvoiceService]
G --> H[seqüència fiscal central]
H --> I[factura + línies + registre + payment]
```

## PK-A08 · Distribució monetària

### ACTUAL
```mermaid
flowchart TD
A[UUID_PAYMENT únic] --> B[PackEnrollmentFundAllocationService]
B --> C[Validar suma snapshot = payment = factura]
C --> D{cada ID_INSC}
D --> E[find invoice line]
E --> F[insertOrReuse EXTERNAL_ALLOCATION]
F --> D
D -->|fi| G[2..N moviments sense CHARGE addicional]
```

### FINAL
```mermaid
flowchart TD
A[UUID_PAYMENT únic] --> B[Imports congelats per component]
B --> C{cada ID_INSC}
C --> D[append atribució monetària]
D --> C
C -->|fi| E[sum atribucions = cobrament]
```

## PK-A09 · Notificacions

### ACTUAL
```mermaid
flowchart TD
A[Factura/payment SIF] --> B[PackPaymentNotificationService]
B --> C[NotificationOutboxRepository]
C --> D[1 event idempotent PENDING]
D --> E[NotificationOutboxDeliveryService claim/complete]
E --> G[Transport SMTP/cutover PACK pendent]
A --> F[Correu inicial d'alta web: flux separat i directe]
```

### FINAL
```mermaid
flowchart TD
A[Commit SIF] --> B[Crear event outbox]
B --> C[Worker notificacions]
C --> D[Plantilla versionada]
D --> E[Enviament i traça]
```

## PK-A10 · Fraccionament

### ACTUAL
```mermaid
flowchart TD
A[Alta ecommerce PACK] --> B[FRACCIONAT=0]
B --> C[PackPaymentGate]
C --> D{PAGAMENT previ = 0 i import = pendent complet?}
D -- no --> E[PACK_PARTIAL_REQUIRES_RECONCILIATION / bloqueig]
D -- sí --> F[Un únic CHARGE]
```

### FINAL
```mermaid
flowchart TD
A[Ecommerce] --> B[Pagament únic del pack]
A --> C{Gestió autoritza excepció?}
C -- no --> B
C -- sí --> D[Circuit intranet justificat]
D --> E[Cada cobrament real amb DS_ORDER propi]
E --> F[Classificació fiscal explícita]
```

## Criteri de tancament RM-037 per UC-015

### Codi/documentació — complert
- els deu blocs tenen correspondència codi → UC → prova;
- alta POST + frontera `PublicWebMutationAuthorization` + `REQUEST_ID` + replay/conflicte estan implementats;
- disponibilitat de tots els components i ordre v1 `DATAI, ID_CURS` estan congelats;
- callbacks productius legacy eliminats;
- verificador canònic PACK de preproducció implementat.

### Acceptació runtime — pendent
1. executar `verify-redsys-pack-preproduction.php` amb un `DS_ORDER` real i conservar factura/payment + N moviments + outbox + sync legacy quan correspongui;
2. executar PK-01..PK-11 de navegador/preproducció, incloent GET/cross-site, doble clic, replay `REQUEST_ID` i component fora de finestra;
3. mantenir verd el gate selectiu `UC-015 SIF pack checks` i la suite global quan el canvi afecta infraestructura compartida.

El transport/retry/lliurament de notificacions queda a UC-58 i no reobre el codi UC-015.

## Evidència de proves automatitzades

El 2026-09-30 la suite SIF ha finalitzat amb **706 passed / 0 failed** al commit `c961f193...`. Aquesta evidència cobreix el contracte de checkout, snapshot, factura, conciliació, ledger i outbox del UC-015. Resta la validació visual/navegador i Redsys de preproducció.

**Revalidació 02/10 (històrica):** el paquet UC-015 disposava d'evidència CI positiva en talls previs. **Reconciliació 04/10:** el PR #149 va alinear els boundaries PACK/Redsys amb el codi vigent i el seu HEAD `8871e15...` va executar `SIF checks` i `SIF PHP MySQL tests` en success, amb **971 passed / 0 failed**. A partir d'aquesta passada, el gate selectiu `UC-015 SIF pack checks` és la porta específica del cas.


## Reconciliació de les activitats — 2026-10-04

- PK-A01..PK-A10 continuen presents i la revisió per pàgina ha afegit **PK-A04b · Confirmació d'alta**, que faltava com a frontera explícita ACTUAL/FINAL.
- PK-A03/PK-A04 reflecteixen el transport POST, guard configurable, REQUEST_ID, idempotència i atomicitat reals.
- PK-A05/PK-A06 reflecteixen intenció SIF i callback/cua/worker autoritatius.
- PK-A07/PK-A08 reflecteixen una factura/payment i N atribucions monetàries.
- PK-A09 s'actualitza perquè ja existeix `NotificationOutboxDeliveryService`; el pendent és el transport/cutover real de l'outbox PACK.
- PK-A10 continua bloquejant fraccionament al checkout públic.
- Evidència de regressió del baseline: PR #149, **971 passed / 0 failed**. La nova activitat PK-A04b/SEC-015-01 disposa de tests al PR #171 però la seva CI continua pendent.
- Vegeu [inventari PHP/JS 04/10](uc-015-inventari-codi-php-js-actual-final-2026-10-04.md) i [reconciliació main 04/10](uc-015-reconciliacio-main-2026-10-04.md).
