# UC-020 — Inventari executable PHP/JS ACTUAL, pont candidat i FINAL — 04/10/2026

**Objectiu:** mapar cada superfície P01–P06 a codi real, efectes, autoritat i proves.  
**Regla:** `DOCUMENTAT` ≠ `IMPLEMENTAT` ≠ `VERIFICAT` ≠ `DESPLEGAT`.

## 1. P01 · pàgina pública de descomptes

| Fitxer | Funció UC-020 | Efecte | Estat |
| --- | --- | --- | --- |
| `codi-drive/web-actual/PaginaDescomptes.php` | text «Alumnes PrisMa» + taula orientativa | lectura de tarifes; no muta inscripció | ACTUAL INSPECCIONAT |
| `PaginaDescomptes::__getSection(1)` | explica descompte automàtic en introduir DNI | presentació | DOCUMENTAT |
| `calcTableDesc(1)` | mostra base/descompte orientatiu | lectura | DOCUMENTAT; no és snapshot contractual |

**Observació:** el text indica descomptes no acumulables. La decisió final d'elegibilitat/preu no depèn d'aquesta pàgina.

## 2. P02 · formulari d'inscripció / preview / alta

| Fitxer | Responsabilitat | Escriptura | Estat |
| --- | --- | --- | --- |
| `codi-drive/web-actual/InscripcioCurs.php` | render formulari/curs/descomptes | no | ACTUAL |
| `js1619773569/mostrarInscripcions.min.js` | DNI, checks, promo, preview, submit, precheck duplicats | no BD | HARDENIT UC-020 |
| `ajax/calcularPreu.php` | tarifa/candidats + AP preview | no | HARDENIT |
| `inc/buscarAlumnePrisMa.php` | elegibilitat preview v2 executable | no | HARDENIT |
| `ajax/enviarInscripcio.php` | comanda d'alta | INSERT `inscripcions` + auxiliars | HARDENIT POST/AP SERVER AUTHORITY |
| `ajax/buscarSiHaRealitzatElCurs.php` | precheck curs ja realitzat | no | ACTUAL |
| `ajax/buscarCodiCursDeriva.php` | resol curs origen/derivat | no | ACTUAL |

### Guards implementats

- generació monotònica de càlcul de preu;
- callbacks AJAX obsolets descartats;
- submit bloquejat mentre el càlcul vigent és pendent;
- AP i promoció no queden simultàniament com a origen;
- precheck de curs únic, sense cadena duplicada;
- alta per POST, no GET;
- `TIPUS_CURS` derivat de servidor;
- AP revalida historial i tarifa abans de l'INSERT;
- AP + promoció inconsistent falla tancat.

### Proves

- `LegacyPrismaStudentEnrollmentAuthorityBoundaryTest`;
- `LegacyPrismaStudentPriceConcurrencyBoundaryTest`;
- `LegacyEnrollmentCompletionPrecheckBoundaryTest`;
- `PrismaStudentDiscountPolicyTest`.

**CI del HEAD actual:** pendent d'execució.

## 3. P03 · confirmació

### Shell/JS

- `pagina_confirmacio_inscripcio_automatic.php`
- `js1619773569/mostrarConfirmacioInscripcioAutomatic.min.js`

El JS extreu el token de la ruta i carrega:

- `ajax/mostrar_confirmacio_inscripcio_automatic.php`.

### Backend

`mostrar_confirmacio_inscripcio_automatic.php`:

1. recupera la clau de xifrat servidor;
2. rep `keyEncr` com a paràmetre estructurat i el delega a `LegacyPaymentToken::decode()`;
3. el decoder fa base64 estricte, longitud mínima i HMAC `hash_equals` **abans** del desxifrat AES-128-CBC;
4. exigeix identificador enter positiu;
5. resol `IDPAG` des de `inscripcions`;
6. instancia `PagamentCursAutomatic` i executa `mostrarPaginaConfirmacio()`.

Els JS de confirmació/pagament fan `encodeURIComponent(keyEncr)`; ja no es talla `REQUEST_URI` ni es depèn del cache-buster jQuery.

L'import no es deriva del JS.

**Estat:** ACTUAL/FALLBACK INSPECCIONAT.

## 4. P04 · pagament

### 4.1. ACTUAL/fallback inspeccionat

- `pagina_pagament_automatic.php`
- `js1619773569/mostrarPagamentAutomatic.min.js`
- `ajax/mostrar_pagina_pagament_automatic.php`
- `PagamentCursAutomatic.php`
- `pagina_efectuar_pagament_automatic.php`

`PagamentCursAutomatic` rellegeix per `IDPAG`:

- `A_PAGAR`;
- `PAGAMENT`;
- `FRACCIONAT`;
- `TIPUS_DESC`;
- `VALID_DESC`.

Targeta i transferència requereixen estat pagable; la transferència és instrucció offline.

`web-actual/pagina_efectuar_pagament_automatic.php`:

- aplica gate servidor;
- però genera localment `DS_ORDER`;
- construeix directament MerchantParameters Redsys;
- no usa `SifRedsysCourseIntentClient`.

**Classificació:** ACTUAL/FALLBACK, no checkout SIF acreditat.

### 4.2. PONT CANDIDAT `pay.prisma.cat`

- `codi-drive/pay-prisma-cat-canvis-verifactu/PagamentCursAutomatic.php`
- `pagina_efectuar_pagament_automatic.php`
- `SifRedsysCourseIntentClient.php`
- callbacks/returns del mateix directori.

La candidata:

1. envia formulari a `https://pay.prisma.cat/confirmation/`;
2. rellegeix/gatea matrícula;
3. crida `SifRedsysCourseIntentClient`;
4. client signat → `POST /api/redsys/course-intent.php`;
5. rep `DS_ORDER` i import SIF;
6. construeix Redsys amb aquests valors;
7. permet cutover de callback només amb flags explícits.

**Classificació:** IMPLEMENTAT_PONT_CANDIDAT · DESPLEGAMENT NO VERIFICAT.

### 4.3. FINAL SIF

- `sif/public/api/redsys/course-intent.php`
- `RedsysCoursePaymentIntentService`
- `LegacyPrismaStudentPriceSnapshotResolver`
- `PrismaStudentCourseCheckoutService`
- `CourseIntentSnapshotValidator`
- `RedsysPaymentIntentService`
- `RedsysPaymentIntentRepository`
- `CommercialOperationRepository`
- `CommercialOperationPartyRepository`
- `CommercialOperationLineRepository`
- `DiscountValidationRepository`
- `PaymentLinkService`.

Per AP: `VALID_DESC=1`, tarifa autoritativa, operació `BILLABLE`, snapshot, validació i intenció.

### Proves

- `RedsysCoursePaymentIntentPrismaStudentTest`
- `PrismaStudentCourseCheckoutServiceTest`
- `PrismaStudentCommercialSnapshotImmutabilityTest`
- `LegacyPrismaStudentPaymentStateBoundaryTest`
- `LegacyPaymentTokenTest`
- `LegacyIdpagAllocatorSecurityTest`
- `PaymentLinkServiceTest`
- callbacks Redsys compartits.

## 5. P05 · intranet «Validar descomptes»

| Fitxer | Responsabilitat | Estat |
| --- | --- | --- |
| `codi-drive/intranet-actual/alumnes-validar-descomptes.php` | pantalla + CSRF | ACTUAL HARDENIT |
| `js/alumnes-validar-descomptes.js` | POST + requestId | ACTUAL HARDENIT |
| `ajax/alumnes/sendMsgValidatCurosDescomptes.php` | sessió, permís, CSRF, idempotència de sessió | ACTUAL HARDENIT |
| `Intranet.php::sendMsgValidatCurosDescomptes` | acceptació/denegació + fallback AP | HARDENIT UC-020 |
| `LegacyDiscountValidationLookup.php` | consulta segura de dades del dret | ACTUAL |

### Delta UC-020

Després de denegació documental i AP elegible:

- `TIPUS_DESC=1`;
- `VALID_DESC=1`;
- `A_PAGAR=tarifa AP`.

**Pendent:** idempotència/versió persistent multioperador. `requestId` actual és de sessió.

### Proves

- `LegacyUsocDiscountValidationSecurityTest`;
- `LegacyPrismaStudentDiscountDenialBoundaryTest`;
- `LegacyPrismaStudentPaymentStateBoundaryTest`;
- contractes de decisió SIF compartits.

## 6. P06 · canvi de curs

| Fitxer | Responsabilitat | Estat |
| --- | --- | --- |
| `codi-drive/intranet-actual/ajax/alumnes/realitzarCanviCurs_CanviCurs.php` | POST/CSRF/permís + mutació | HARDENIT AP |
| `Intranet.php::realitzarCanviCurs_modalCanviCurs` | efecte llegat | ACTUAL |
| `CourseChangePreviewService` / API interna | preview econòmic/fiscal | IMPLEMENTAT SIF |
| `LegacyPrismaStudentCourseChangeBoundaryTest` | frontera AP | TEST PENDENT CI HEAD |

Per AP:

- tipus/estat rellegits de BD;
- tarifa destí resolta per ID_PREU + curs/hores + mes + vigència;
- import pagat rellegit;
- pendent recalculat abans de preview/mutació.

**Pendent de policy:** decidir/migrar si el dret s'ha de reavaluar amb exactament `PrismaStudentDiscountPolicy v2` en el moment del canvi. No es canvia semàntica silenciosament.

## 7. Matriu resum

| Superfície | Documentada | Implementada/hardenitzada | Verificada | Pendent |
| --- | --- | --- | --- | --- |
| P01 | sí | actual | inspecció | font comuna oferta |
| P02 | sí | sí | històric + inspecció; HEAD CI pendent | offer_id/SIF natiu |
| P03 | sí | actual | inspecció | payment_link canònic |
| P04 actual | sí | gate servidor | inspecció | migració SIF |
| P04 pont candidat | sí | sí | codi/tests històrics | deploy/cutover/E2E |
| P05 | sí | sí | frontera històrica + inspecció | idempotència persistent |
| P06 | sí | parcial/hardenit AP | inspecció; HEAD CI pendent | policy comuna v2 |

## 8. Conclusió

No falta cap família principal de codi per poder auditar UC-020. El que faltava era **separar correctament fonts actuals, candidates i finals** i cobrir diverses fronteres que estaven documentades però no endurides.

Aquest inventari no declara `GO_PRODUCTION`. Els gates externs continuen sent CI del HEAD, deploy/cutover acreditat i E2E controlat.
