# UC-024 — Auditoria detallada i traçabilitat — 03/10/2026

**Cas:** UC-024 · Registrar cobrament de reclamació  
**Branca auditada:** audit/uc-024-2026-10-03 (base `main`)  
**Criteri:** una peça existent no es considera verificada només perquè compili o aparegui al repositori.

## 1. Veredicte executiu

UC-024 **existeix parcialment** al SIF, però **no està tancat de punta a punta**.

| Àmbit | Documentat | Implementat | Verificat en codi | Verificat E2E/preproducció | Estat |
| --- | --- | --- | --- | --- | --- |
| Registrar CHARGE contra factura existent | Sí | Sí | Sí | No | PARCIAL |
| Idempotència dins de la mateixa clau | Sí | Sí | Sí | No | PARCIAL |
| Cobrament parcial | Sí | Sí | Sí | No | PARCIAL |
| Segon ingrés real de la mateixa reclamació | Sí, com a gap | No resolt | Sí: avui pot fer CONFLICT/reús indegut segons payload | No | BLOQUEJAT |
| Conciliació intercanal UC-022/UC-023/Redsys | Sí, objectiu | No | No | No | PENDENT |
| Expedient de reclamació separat de la referència bancària | Sí, objectiu | No | Sí: avui estan barrejats | No | PENDENT |
| Actor/auditoria transversal de la mutació | Sí, objectiu | No acreditat en aquesta ruta | No | No | PENDENT |
| Pantalles de reclamació → ClaimPaymentService | Sí, objectiu | No | Sí: continuen en llegat | No | PENDENT |
| Autorització de mutació + CSRF als AJAX de reclamació | Sí, objectiu | No acreditat | Sí: no es veu guard propi als endpoints inspeccionats | No | PENDENT |
| Outbox per correus | Sí, objectiu | No en les pantalles llegades | Sí: SMTP síncron | No | PENDENT |
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

### F-024-01 — Identitat de reclamació i identitat bancària conflueixen — ALTA

El builder tracta `claim_reference` com la primera candidata a `payload['reference']`. El repositori desa aquest valor a `payment_transaction.REFERENCIA_BANCARIA`. Per tant, un codi intern d'expedient pot acabar a una columna que semànticament representa la referència bancària.

**Conseqüència:** la traça no diferencia de manera executable `claim_case_id` de `external_receipt_id`.

### F-024-02 — Segon ingrés parcial real amb el mateix claim_reference — ALTA

Dos ingressos diferents E1/E2 d'una mateixa reclamació generen la mateixa clau `CLAIM|REF:<claim>`. Si l'import o altres camps difereixen, `PaymentService` falla tancat amb 409; si tot el payload coincideix, no hi ha cap identificador extern que permeti demostrar que E2 és un altre fet real i el reintent es pot reutilitzar.

**Conseqüència:** el model actual és idempotent per petició/clau, però no modela correctament múltiples entrades econòmiques d'un mateix expedient.

### F-024-03 — Sense conciliació global del mateix fet econòmic — ALTA

Les famílies de claus `CLAIM|REF`, transferència, Redsys i fraccionaments no comparteixen necessàriament el mateix identificador. UC-024 no consulta un registre global del rebut extern abans de crear el `CHARGE`.

**Conseqüència:** un ingrés ja registrat per UC-022/UC-023/Redsys necessita un reconciliador explícit; la idempotència local de `PaymentService` no ho resol per si sola.

### F-024-04 — El servei no valida saldo pendent abans del CHARGE — MITJANA/ALTA

`ClaimPaymentService` localitza la factura però no calcula el deute pendent abans de registrar l'import. `PaymentStatusCalculator` contempla `OVERPAID`; és a dir, el ledger pot reflectir un sobrepagament en lloc de bloquejar-lo.

**Conseqüència:** cal decisió de negoci explícita: permetre i gestionar `OVERPAID` o impedir imports superiors al saldo pendent.

### F-024-05 — Lectura de factura fora de la transacció de PaymentService — MITJANA

La factura es resol abans d'entrar a la transacció de `PaymentService` i `ClaimPaymentService` crida el repositori sense `FOR UPDATE`. El repositori de pagaments sí bloqueja la factura quan recalcula l'estat, però la precondició llegida pel servei no forma una decisió atòmica de saldo/estat.

### F-024-06 — created_by no és auditoria d'actor persistent — ALTA

El builder admet `created_by`, però `PaymentRepository::createPayment()` no el desa en una columna específica del moviment. Tampoc s'ha acreditat en aquesta ruta l'escriptura de `payment_action_event`, `operational_event` o `sif_audit_event`.

**Conseqüència:** la fitxa anterior sobreafirmava la persistència mínima implementada.

### F-024-07 — UI de reclamacions no invoca ClaimPaymentService — ALTA

Les quatre superfícies de reclamació/morositat inspeccionades actualitzen camps llegats i envien comunicacions. Els seus AJAX criden mètodes d'`Intranet.php`; no hi ha una crida a `ClaimPaymentService` per registrar l'ingrés.

**Conseqüència:** el servei SIF existeix, però no és encara el backend canònic de les pantalles de reclamació.

### F-024-08 — Autorització de visualització sí; autorització de mutació independent no acreditada — ALTA

`comprovarSessio.php` revalida la sessió de la pàgina i `mostrarMain*.php` comprova `tePermisVisualitzacio()`. En canvi, els endpoints AJAX de mutació inspeccionats fan `session_start()`, deserialitzen els objectes i executen la mutació; no inclouen el guard de pàgina, no s'hi ha localitzat comprovació específica de rol de mutació ni token CSRF.

### F-024-09 — Comunicació síncrona i estat llegat abans/després del correu — MITJANA

Els mètodes llegats actualitzen camps de reclamació i creen directament `MailSMTPComvive`. No s'ha acreditat outbox transaccional. Una incidència SMTP pot deixar estat de negoci i comunicació sense una correlació robusta.

### F-024-10 — Errors/bugs llegats localitzats i corregits en aquesta branca — RESOLT A BRANCA

- S’ha eliminat de `facturacio-recordatori-pagament-final.js` el handler mort `#upd-baixes → confirmaReclamacio()`; el flux útil continua a `#confirma-reclamacio → confirmaRecordatori()`.
- Les dues branques de morositat per alumnat sense/amb certificat que passaven `$titol` no definit ara passen `$nomCurs`, que és el valor realment carregat.
- `ClaimPaymentLegacyBoundaryTest` protegeix ambdues correccions de regressions estàtiques.

## 5. Seguretat i límits

**Documentat:** backend autenticat, rol, idempotència, correlació, outbox.  
**Implementat a la pàgina:** revalidació de sessió i permís de visualització.  
**Implementat al nucli SIF:** idempotència transaccional per clau/payload.  
**No acreditat:** CSRF, permís específic de mutació, actor persistent del cobrament, correlació de la reclamació i l'ingrés extern, outbox en les pantalles llegades.

## 6. Fiscalitat

El cobrament posterior a una factura existent **no crea** un registre fiscal nou. Les proves existents comproven que el recompte de `factura`, `factura_registres` i `fiscal_queue` no augmenta pel reintent del cobrament. La reclamació, la morositat, la baixa acadèmica i l'estat fiscal són dimensions separades.

## 7. Matriu documentat / implementat / verificat / pendent

| Element | Documentat | Implementat | Verificat per inspecció | Pendent |
| --- | --- | --- | --- | --- |
| Fitxa UC-024 | Sí | N/A | Sí | mantenir sincronitzada |
| Servei de cobrament | Sí | Sí | Sí | integració UI |
| Builder | Sí | Sí | Sí | separar identitats |
| Idempotència payload | Sí | Sí | Sí | identitat global intercanal |
| Cobrament parcial | Sí | Sí | Sí | E2 mateix expedient |
| OVERPAID | Parcial | Sí | Sí | política de negoci |
| Actor/auditoria | Sí | Parcial/no acreditat | Sí | persistència |
| Scripts preview/process | Sí | Sí | Sí | evidència preproducció |
| Primera reclamació | Sí | Llegat | Sí | adaptador SIF |
| Recordatori final | Sí | Llegat | Sí | adaptador SIF |
| Reclamació final | Sí | Llegat | Sí | adaptador SIF |
| Control morosos | Sí | Llegat | Sí | adaptador SIF |
| CSRF mutacions | Sí, com a requisit transversal | No localitzat | Sí | implementar |
| Outbox | Sí, objectiu | No en llegat | Sí | implementar |
| E2E real amb MySQL controlat | Sí | N/A | No | executar i guardar evidència |

## 8. Criteri de tancament

UC-024 només es podrà marcar **TANCAT** quan:

1. les pantalles/endpoint autoritatiu de reclamacions registrin el cobrament via SIF;
2. `claim_case_id` i l'identificador de cada ingrés real siguin diferents i persistents;
3. existeixi deduplicació/conciliació intercanal;
4. el saldo pendent i la política d'`OVERPAID` estiguin definits i provats;
5. les mutacions tinguin autenticació, permís específic, CSRF/idempotència i actor auditable;
6. els correus siguin post-commit/outbox o tinguin una estratègia equivalent traçable;
7. la suite SIF passi sobre el commit objectiu i hi hagi evidència de preproducció sobre `sif_test*`/`sif_pre`;
8. els diagrames ACTUAL/FINAL i la matriu de proves continuïn alineats amb el codi.

**Estat al tall d'aquesta auditoria:** **NO TANCAT — implementació SIF parcial i integració llegat pendent**.
