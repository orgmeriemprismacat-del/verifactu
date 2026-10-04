# UC-024 — Auditoria detallada i traçabilitat — 03/10/2026

**Cas:** UC-024 · Registrar cobrament de reclamació  
**Branca auditada:** audit/uc-024-2026-10-03 (base `main`)  
**Criteri:** una peça existent no es considera verificada només perquè compili o aparegui al repositori.

## 1. Veredicte executiu

UC-024 disposa ara d’una implementació funcional completa en branca darrere feature flag, però **encara no està tancat de punta a punta** perquè manca executar el HEAD actual en CI/preproducció i conservar evidència E2E.

| Àmbit | Documentat | Implementat | Verificat en codi | Verificat E2E/preproducció | Estat |
| --- | --- | --- | --- | --- | --- |
| Registrar CHARGE contra factura existent | Sí | Sí | Sí | No | PARCIAL |
| Idempotència dins de la mateixa clau | Sí | Sí | Sí | No | PARCIAL |
| Cobrament parcial | Sí | Sí | Sí | No | PARCIAL |
| Segon ingrés real de la mateixa reclamació | Sí | Resolució implementada en el contracte nou amb external_receipt_id diferent | Sí en proves de branca | No preproducció | PARCIAL |
| Conciliació intercanal UC-022/UC-023/Redsys | Sí | Sí: `BANK_REFERENCE`, `DS_ORDER`, `PROVIDER_REF` | Sí per inspecció/proves de branca | No | PARCIAL |
| Expedient separat de la referència bancària | Sí | Sí al contracte/API/auditoria; sense taula `claim_case` dedicada | Sí | No | PARCIAL |
| Actor/auditoria transversal de la mutació | Sí | Sí | Sí: `PaymentActionGateway` + `payment_action_event` + `SYNC_LEGACY` | No | PARCIAL |
| Pantalles de reclamació → ClaimPaymentService | Sí | Sí, bridge + JS feature-flagged | Sí | No | PARCIAL |
| Autorització de mutació + CSRF | Sí | Sí al bridge nou | Sí: POST, Same-Origin/AJAX, CSRF, permís d’edició, HMAC intern | No | PARCIAL |
| Outbox per correus | UC-12/UC-43, no requisit de persistència econòmica UC-024 | No en les pantalles llegades | Sí: SMTP síncron | No | FORA D'ABAST UC-024 |
| Proves unitàries/integració SIF | Sí | Sí | Sí, fitxers presents | pendent d'execució sobre el commit d'aquesta branca | PARCIAL |

## 2. Inventari real localitzat

### 2.1. Fitxes i UML

- `documentacio/06-fitxes-funcionals/uc-024.md`: existeix; abans de l'auditoria era un esborrany genèric i atribuïa persistències transversals no acreditades a aquesta ruta.
- `documentacio/07-uml-integrat/uc-024-registrar-cobrament-reclamacio.md`: existeix i ja contenia una anàlisi útil del servei SIF i del problema de `claim_reference`, però no tenia dossiers independents ACTUAL/FINAL per classes, seqüències i activitats.
- Aquesta auditoria crea els dossiers específics ACTUAL/FINAL i una matriu de proves.

### 2.2. Codi SIF

- `sif/src/Service/ClaimPaymentService.php`
- `sif/src/Service/ClaimPaymentPayloadBuilder.php`
- `sif/src/Service/PaymentService.php`
- `sif/src/Service/PaymentPayloadValidator.php`
- `sif/src/Repository/ManualPaymentInvoiceRepository.php`
- `sif/src/Repository/PaymentRepository.php`
- `sif/src/Domain/PaymentStatusCalculator.php`
- `sif/scripts/preview-claim-payment.php`
- `sif/scripts/process-claim-payment.php`

### 2.3. Proves SIF

- `sif/tests/Integration/ClaimPaymentServiceTest.php`
- `sif/tests/Unit/ClaimPaymentPayloadBuilderTest.php`
- `sif/tests/Integration/ClaimPaymentPreproductionScriptTest.php`
- `sif/tests/Integration/ClaimPaymentPreviewScriptTest.php`

### 2.4. Superfícies llegades de reclamació/morositat

- `facturacio-primera-reclamacio-pagament.php` + JS + AJAX.
- `facturacio-recordatori-pagament-final.php` + JS + AJAX.
- `facturacio-reclamacio-final.php` + JS + AJAX.
- `facturacio-control-morosos.php` + JS + AJAX.
- Lògica de negoci principal dins `codi-drive/intranet-actual/Intranet.php`.

## 3. Flux executable SIF ACTUAL

1. `ClaimPaymentService` localitza una factura per UUID o número visible.
2. `ClaimPaymentPayloadBuilder` construeix un `CHARGE`, canal `INTRANET`, assignació `CLAIM_PAYMENT`.
3. La referència es busca, per ordre, entre `claim_reference`, `reclamation_ref`, `reclamacio_ref`, `reference`, `referencia`, `referencia_bancaria`.
4. Amb referència, la clau és `CLAIM|REF:<ref>`; sense referència, deriva de factura/data/import/usuari.
5. `PaymentService` executa la persistència en transacció, compara payload en reús de clau i rebutja payload diferent amb conflicte.
6. `PaymentRepository` insereix `payment_transaction`, `payment_allocation` i recalcula `factura.ESTAT_COBRAMENT`.
7. No s'emet factura, rectificativa ni nou registre fiscal.

## 4. Troballes de codi

### F-024-01 — Identitat de reclamació i identitat bancària conflueixen a la ruta legacy — ALTA / MITIGAT A BRANCA

El builder tracta `claim_reference` com la primera candidata a `payload['reference']`. El repositori desa aquest valor a `payment_transaction.REFERENCIA_BANCARIA`. Per tant, un codi intern d'expedient pot acabar a una columna que semànticament representa la referència bancària.

**Conseqüència a `main`:** la traça no diferencia de manera executable `claim_case_id` de `external_receipt_id`.

**Canvi de la branca:** la nova API interna exigeix `claim_case_id` i `external_receipt_id` separats; elimina camps legacy ambigus del payment input, usa l’ingrés extern per `CLAIM|RECEIPT:*` i conserva l’expedient al `CHANGESET_JSON` de `payment_action_event`.

### F-024-02 — Segon ingrés parcial real amb el mateix claim_reference — RESOLT EN CONTRACTE NOU / LEGACY PENDENT

Dos ingressos diferents E1/E2 d'una mateixa reclamació generen la mateixa clau `CLAIM|REF:<claim>`. Si l'import o altres camps difereixen, `PaymentService` falla tancat amb 409; si tot el payload coincideix, no hi ha cap identificador extern que permeti demostrar que E2 és un altre fet real i el reintent es pot reutilitzar.

**Conseqüència a legacy:** el model antic és idempotent per `claim_reference`, però no modela correctament múltiples entrades econòmiques d'un mateix expedient.

**Canvi de la branca:** `ClaimPaymentPayloadBuilder` prioritza `external_receipt_id` i deriva `CLAIM|RECEIPT:<id>`; dues entrades E1/E2 amb identificadors externs diferents es registren com dos moviments diferents encara que pertanyin al mateix expedient.

### F-024-03 — Conciliació global del mateix fet econòmic — RESOLT EN CONTRACTE NOU / PREPROD PENDENT

`ClaimPaymentReceiptResolver` cerca el rebut extern a `payment_transaction` segons `BANK_REFERENCE`, `DS_ORDER` o `PROVIDER_REF`. Si ja existeix, exigeix mateixa factura, mateix `IDPAG` i mateix import abans de reutilitzar el `UUID_PAYMENT`. Per `DS_ORDER` i `PROVIDER_REF`, UC-024 no crea manualment el moviment si el canal autoritatiu encara no l’ha registrat.

**Conseqüència:** la deduplicació ja no depèn només de la família de clau local `CLAIM|*`; falta validar-la E2E amb dades controlades.

### F-024-04 — Saldo pendent / OVERPAID — RESOLT PER UC-024

`ClaimPaymentBalanceGuard` bloqueja un cobrament nou si la factura no té saldo pendent o si l’import excedeix el pendent calculat sobre `payment_allocation` i moviments confirmats.

**Conseqüència:** UC-024 adopta una política explícita de **no crear sobrepagaments nous**. Altres canals poden conservar la seva política pròpia.

### F-024-05 — Lectura/lock transaccional — RESOLT A LA NOVA RUTA

`PaymentService::registerPaymentInTransaction()` permet que `PaymentActionGateway` sigui propietari de la transacció; `ClaimPaymentService::registerByUuidInTransaction()` rellegeix la factura amb `FOR UPDATE`. El pagament i l’event terminal d’auditoria comparteixen commit/rollback.

### F-024-06 — created_by no era auditoria d'actor persistent — MITIGAT A NOVA API

El builder admet `created_by`, però `PaymentRepository::createPayment()` no el desa en una columna específica del moviment. Tampoc s'ha acreditat en aquesta ruta l'escriptura de `payment_action_event`, `operational_event` o `sif_audit_event`.

**Conseqüència a CLI/legacy:** `created_by` sol no acredita actor complet.

**Canvi de la branca:** l’API signada obté actor/rol de `InternalApiAuthenticator` i escriu `REQUESTED` + terminal a `payment_action_event` mitjançant `PaymentActionGateway`. El pagament i l’event terminal comparteixen transacció.

### F-024-07 — Bridge/UI de cobrament — IMPLEMENTAT DARRERE FEATURE FLAG

La branca incorpora `claim-payment-sif.js` i `ajax/facturacio/registerClaimPaymentSif.php`. Les files de les quatre superfícies exposen `data-id-insc`, però el navegador no controla factura, claim case ni actor. L’API SIF resol la factura d’origen des de `fact_rels`. La UI només es carrega amb `SIF_CLAIM_PAYMENT_UI_ENABLED=1`.

**Conseqüència:** no altera l’operativa actual per defecte; falta desplegar-la i validar-la a preproducció.

### F-024-08 — Autorització de mutació — RESOLT A LA NOVA FRONTERA

El bridge exigeix `POST`, sessió vàlida, `LegacyInvoiceMutationAuthorization::assertSameOrigin()`, token `csrf_claim_payment`, permís d’edició derivat de la pàgina/referer i `SifAuthenticatedActor`. El tram intranet→SIF utilitza HMAC, timestamp, request UUID i anti-replay via `InternalApiAuthenticator`.

### F-024-09 — Comunicació síncrona i estat llegat abans/després del correu — MITJANA

Els mètodes llegats actualitzen camps de reclamació i creen directament `MailSMTPComvive`. No s'ha acreditat outbox transaccional. Una incidència SMTP pot deixar estat de negoci i comunicació sense una correlació robusta.

### F-024-09B — Factura amb múltiples inscripcions d’origen — BLOQUEIG EXPLÍCIT

La projecció UC-024 actualitza una única fila `inscripcions`. `ClaimPaymentInvoiceLinkRepository` comprova que la factura tingui exactament una relació `INSCRIPCIO/ORIGIN`. Si en té més d’una, retorna conflicte en lloc de repartir implícitament l’import.

**Conseqüència:** packs, grups o factures agregades amb múltiples inscripcions necessiten un flux de repartiment explícit i no queden coberts per aquesta mutació individual.

### F-024-10 — Errors/bugs llegats localitzats i corregits en aquesta branca — RESOLT A BRANCA

- S’ha eliminat de `facturacio-recordatori-pagament-final.js` el handler mort `#upd-baixes → confirmaReclamacio()`; el flux útil continua a `#confirma-reclamacio → confirmaRecordatori()`.
- Les dues branques de morositat per alumnat sense/amb certificat que passaven `$titol` no definit ara passen `$nomCurs`, que és el valor realment carregat.
- `ClaimPaymentLegacyBoundaryTest` protegeix ambdues correccions de regressions estàtiques.

## 5. Seguretat i límits

**Documentat:** backend autenticat, rol, idempotència, correlació, outbox.  
**Implementat a la nova frontera:** sessió, Same-Origin/AJAX, CSRF, permís d’edició, actor/rol server-side, HMAC anti-replay, idempotència per rebut, relació factura↔inscripció, saldo pendent i auditoria.  
**Implementat al nucli SIF:** transacció compartida amb `PaymentActionGateway`, conciliació intercanal, projecció legacy fail-closed i auditoria `SYNC_LEGACY`.  
**Encara no acreditat en preproducció:** configuració real de rols/secrets/orígens, E2E navegador→intranet→SIF→legacy i retry operatiu. **Outbox:** continua pendent perquè els correus de reclamació segueixen el flux legacy separat.

## 6. Fiscalitat

El cobrament posterior a una factura existent **no crea** un registre fiscal nou. Les proves existents comproven que el recompte de `factura`, `factura_registres` i `fiscal_queue` no augmenta pel reintent del cobrament. La reclamació, la morositat, la baixa acadèmica i l'estat fiscal són dimensions separades.

## 7. Matriu documentat / implementat / verificat / pendent

| Element | Documentat | Implementat | Verificat per inspecció | Pendent |
| --- | --- | --- | --- | --- |
| Fitxa UC-024 | Sí | N/A | Sí | mantenir sincronitzada |
| Servei de cobrament | Sí | Sí | Sí | E2E preproducció |
| Builder | Sí | Sí | Sí | compatibilitat legacy mantinguda |
| Idempotència payload/rebut | Sí | Sí | Sí | E2E intercanal |
| Cobrament parcial | Sí | Sí | Sí | E2E E1/E2 |
| OVERPAID UC-024 | Sí | Bloqueig implementat | Sí | E2E límit de saldo |
| Actor/auditoria | Sí | Sí a API interna nova | Sí | evidència preproducció |
| Scripts preview/process | Sí | Sí | Sí | evidència preproducció |
| Primera reclamació | Sí | Flux comunicació llegat + UI cobrament feature-flagged | Sí | desplegar/provar |
| Recordatori final | Sí | Flux comunicació llegat + UI cobrament feature-flagged | Sí | desplegar/provar |
| Reclamació final | Sí | Flux comunicació llegat + UI cobrament feature-flagged | Sí | desplegar/provar |
| Control morosos | Sí | Flux comunicació llegat + UI cobrament feature-flagged | Sí | desplegar/provar |
| CSRF/permís mutació | Sí | Sí al bridge UC-024 | Sí | prova negativa preprod |
| Outbox | Sí, objectiu | No en llegat | Sí | implementar |
| E2E real amb MySQL controlat | Sí | N/A | No | executar i guardar evidència |

## 8. Criteri de tancament

UC-024 només es podrà marcar **TANCAT** quan:

1. executar la suite sobre el HEAD objectiu i separar qualsevol fallada global no relacionada;
2. configurar `intranet-pre` i `pay-pre` amb URL signada, secrets, rols i orígens;
3. executar E2E nominal, reintent, E1/E2, intercanal, saldo excedit, CSRF/permís denegat i projecció legacy/retry;
4. conservar evidència DB de `payment_transaction`, `payment_allocation`, `payment_action_event`, `factura.ESTAT_COBRAMENT` i `inscripcions.PAGAMENT`;
5. decidir si el cicle de reclamació necessita una entitat persistent `claim_case` més enllà del `changeset` auditat;
6. decidir si els correus de reclamació s’inclouen en UC-024 i, si és així, migrar-los a outbox post-commit;
7. mantenir UML i matriu alineats amb el commit validat.

**Estat al tall 04/10/2026:** **NO TANCAT — implementació preparada darrere feature flag; validació CI/E2E/preproducció pendent**.
