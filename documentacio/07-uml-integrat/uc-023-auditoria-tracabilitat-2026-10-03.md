# UC-023 — Auditoria detallada, traçabilitat i mancances

**Data:** 03/10/2026  
**Cas d'ús:** UC-023 — Registrar una fracció de pagament  
**Abast:** fitxa funcional, codi PHP/JS real, UML ACTUAL/FINAL, traçabilitat, proves i buits.  
**Mètode:** lectura estàtica de `main` i correcció en branca `audit/uc-023-2026-10-03`. Els tests nous no es consideren verificats fins que GitHub Actions o un entorn `sif_test*` en conservi evidència.

## 1. Resum executiu

UC-023 **no estava absent**. A `main` ja hi havia:

- fitxa funcional `documentacio/06-fitxes-funcionals/uc-023.md`;
- dossier UML integrat `documentacio/07-uml-integrat/uc-023-registrar-fraccio.md`;
- runtime SIF: `ManualInstallmentPaymentService`, `ManualInstallmentPaymentPayloadBuilder`, `ManualPaymentInvoiceRepository`, `PaymentService`, `PaymentRepository` i `PaymentStatusCalculator`;
- proves unitàries i d'integració específiques.

Però la cobertura era **parcial**: el dossier no separava de forma sistemàtica classes, seqüències i activitats ACTUAL/FINAL per pàgina, i la pantalla real d'intranet inspeccionada continua en un flux llegat basat en GET sense evidència que invoqui el servei SIF d'UC-023.

## 2. Estat consolidat

| Àrea | Estat | Evidència |
| --- | --- | --- |
| Definició funcional | DOCUMENTAT | Fitxa UC-023 i dossier UML integrat |
| Nucli SIF de cobrament fraccionat | IMPLEMENTAT | `ManualInstallmentPaymentService` + builder + `PaymentService` |
| Persistència econòmica | IMPLEMENTAT | `payment_transaction`, `payment_allocation`, actualització `ESTAT_COBRAMENT` |
| Idempotència bàsica | IMPLEMENTAT | `IDEMPOTENCY_KEY` + hash payload V1/V2 |
| Reintent exactament equivalent | COBERT PER TEST | proves existents |
| Dues fraccions diferents | COBERT PER TEST | prova existent 40 + 80 |
| Dues fraccions reals idèntiques mateix dia | DEFECTE DETECTAT / CORREGIT PARCIALMENT | builder antic col·lisionava; branca admet `operation_id` immutable opcional |
| Vincle `ID_INSC ↔ UUID_FACTURA` | PENDENT | el servei localitza factura i accepta `id_insc` d'entrada sense contrastar relació |
| Validació import pendent abans del CHARGE | PENDENT | es pot arribar a `OVERPAID`; no hi ha guard específic d'UC-023 |
| Conciliació bancària/Redsys abans de MANUAL | PENDENT | no acreditada en aquest servei |
| Autorització backend específica del canal | PENDENT | endpoint llegat inspeccionat no acredita guard UC-023 |
| Adaptador intranet → SIF UC-023 | PENDENT | JS actual crida `ajax/alumnes/efectuarPagament.php` per GET |
| Activitats ACTUAL/FINAL per pàgina | CREAT EN AQUESTA BRANCA | `uc-023-activitats-pagines-actual-final.md` |
| Classes ACTUAL/FINAL | CREAT EN AQUESTA BRANCA | `uc-023-classes-actual-final.md` |
| Seqüències ACTUAL/FINAL | CREAT EN AQUESTA BRANCA | `uc-023-sequencies-actual-final.md` |
| Evidència d'execució | PENDENT FINS CI/PRE | un test existent no equival a test executat |

## 3. Codi PHP/JS real revisat

### 3.1. Pantalla i JavaScript llegats

`codi-drive/intranet-actual/alumnes-pagaments.php` carrega `js/alumnes-pagaments.js`.

El JavaScript:

1. busca pagaments amb GET a `ajax/alumnes/buscarInfomacioPagament.php`;
2. permet introduir import, data, banc i observacions;
3. calcula només visualment `pagat + pagament` i marca sobrepagament en la UI;
4. opcionalment mostra `mostrarModalConfPag.php`;
5. executa el cobrament amb GET a `ajax/alumnes/efectuarPagament.php`.

Troballes:

- la validació de sobrepagament de `suma()` és **visual**, no una precondició server-side;
- la mutació s'envia per **GET**;
- no es veu cap `request_id`, `correlation_id`, versió esperada ni clau idempotent del navegador;
- l'èxit de la UI es decideix per absència de la cadena `error` en HTML;
- l'endpoint delega a `$_SESSION['intranet']->efectuarPagament(...)`; en els fitxers inspeccionats no queda acreditat que aquesta crida arribi a `ManualInstallmentPaymentService`.

### 3.2. Altres endpoints llegats relacionats

`guardarDadesPagament_ConsultaInformacio.php` i `guardarEnviarDadesPagament_ConsultaInformacio.php` també reben per GET camps com `pagament`, `datapag`, `fraccio`, `comfraccio`, `factura` i altres valors de gestió. Són evidència que el model històric de fraccions existeix al llegat, però **no acrediten** el ledger SIF ni la idempotència d'UC-023.

### 3.3. Nucli SIF

`ManualInstallmentPaymentService`:

- accepta factura per UUID o `NUM_VISIBLE`;
- comprova que existeixi;
- construeix el payload i delega a `PaymentService`;
- retorna UUID de pagament + factura.

`ManualInstallmentPaymentPayloadBuilder` a `main`:

- valida import > 0, data, `id_insc` > 0 i usuari;
- crea `CHARGE/MANUAL/INTRANET`;
- assigna tot l'import a una única factura amb `INSTALLMENT_PAYMENT`;
- clau històrica: `ID_INSC + dia + import + usuari`;
- no inclou factura ni un identificador immutable del fet econòmic.

`PaymentService`:

- valida payload;
- fa lectura `FOR UPDATE` per clau;
- reusa només si el hash del payload coincideix;
- una mateixa clau amb payload diferent dona conflicte;
- captura col·lisió de clau concurrent i torna a comprovar payload.

`PaymentRepository`:

- persisteix moviment i assignacions dins la transacció del servei;
- recalcula `ESTAT_COBRAMENT`;
- no persisteix `ID_INSC` com a columna d'assignació.

`PaymentStatusCalculator` contempla `PENDING`, `PARTIAL`, `PAID`, `OVERPAID`, `REFUNDED` i `PARTIALLY_REFUNDED`.

## 4. Troballes

| ID | Troballa | Estat |
| --- | --- | --- |
| UC023-01 | Fitxa i UML ja existien; no era un cas documental buit. | VERIFICAT REPO |
| UC023-02 | Nucli SIF de fracció contra factura existent existeix. | VERIFICAT CODI |
| UC023-03 | Reintent equivalent reutilitza UUID_PAYMENT. | VERIFICAT CODI / TEST EXISTENT |
| UC023-04 | Una fracció no crea una segona factura fiscal. | VERIFICAT CODI / TEST EXISTENT |
| UC023-05 | La clau manual històrica pot col·lisionar per dos ingressos reals iguals el mateix dia. | VERIFICAT CODI |
| UC023-06 | El hash evita reutilitzar silenciosament una clau quan canvia factura o altres camps del payload. | VERIFICAT CODI |
| UC023-07 | El hash no pot distingir dos fets externs diferents si tot el payload és idèntic. | VERIFICAT CONCEPTUAL/CODI |
| UC023-08 | `ID_INSC` no es valida contra la factura abans del registre. | VERIFICAT CODI |
| UC023-09 | No hi ha bloqueig específic d'import > pendent abans de registrar; el resultat pot ser `OVERPAID`. | VERIFICAT CODI |
| UC023-10 | El canal intranet actual inspeccionat muta amb GET. | VERIFICAT CODI |
| UC023-11 | La pantalla actual no està acreditada com a adaptador de `ManualInstallmentPaymentService`. | VERIFICAT REPO |
| UC023-12 | El JS marca sobrepagament però no demostra una regla server-side equivalent. | VERIFICAT CODI |
| UC023-13 | No hi havia dossier específic d'activitats ACTUAL/FINAL per pàgina. | CORREGIT DOC |
| UC023-14 | No hi havia dossier separat de classes ACTUAL/FINAL. | CORREGIT DOC |
| UC023-15 | No hi havia dossier separat de seqüències ACTUAL/FINAL. | CORREGIT DOC |
| UC023-16 | La branca introdueix identificador immutable opcional de fracció sense trencar la clau històrica. | CORREGIT CODI |
| UC023-17 | Falta que el canal real generi/transporti aquest identificador des d'un fet verificat. | PENDENT INTEGRACIÓ |
| UC023-18 | Falta guard de relació factura-inscripció i import pendent. | PENDENT |
| UC023-19 | Falta conciliació transversal per evitar duplicar un ingrés ja registrat com TRANSFERENCIA/REDSYS. | PENDENT |
| UC023-20 | Falta evidència E2E i de preproducció. | PENDENT |

## 5. Correcció de codi aplicada

La branca accepta opcionalment `operation_id`, `installment_id`, `external_event_id` o `receipt_id`.

Si existeix:

- clau: `MANUAL|FRACCIO|EVENT:<id>`;
- `provider_ref` conserva també `EVENT:<id>`;
- dos ingressos reals diferents amb mateix import/data/usuari poden obtenir UUID_PAYMENT diferents;
- reintentar el mateix event reutilitza el moviment.

Si **no** existeix, es manté exactament la clau històrica. Això és deliberat per no convertir reintents antics en nous cobraments després del desplegament.

Aquesta correcció **no** declara verificat l'origen de l'identificador: l'adaptador final haurà d'obtenir-lo d'una referència bancària/DS_ORDER/fet manual auditable i autoritzat.

## 6. Proves

### Existents a main

- dues fraccions contra una factura sense duplicar registre fiscal;
- reintent idempotent de la primera;
- registre per número visible;
- factura desconeguda → rebuig;
- unit test del builder.

### Afegides en aquesta branca

- builder amb identificador d'event explícit;
- dues fraccions de mateix import/data/usuari amb events diferents → dos moviments;
- reintent del mateix event → reutilització.

### Encara necessàries

- `ID_INSC` aliè a factura → bloqueig;
- import superior al pendent → regla de negoci explícita;
- event de transferència ja registrat → no segon `MANUAL CHARGE`;
- DS_ORDER denegat → cap CHARGE;
- concurrència de dos intents del mateix event;
- autorització/CSRF/POST de l'adaptador;
- E2E navegador → SIF → ledger → estat factura;
- evidència MySQL real en `sif_test*`/preproducció.

## 7. Criteri de tancament

UC-023 només es pot considerar **TANCAT** quan:

1. el canal real deixa de mutar el cobrament per GET i usa una comanda autenticada/autoritzada;
2. la comanda arriba al servei SIF;
3. identifica de manera immutable el fet econòmic;
4. valida `ID_INSC ↔ factura`, destinatari i saldo pendent;
5. reconcilia ingressos ja existents d'altres canals;
6. els tests passen sobre MySQL controlat;
7. es conserva evidència de preproducció i traça completa.

## 8. Relacions

- [Fitxa UC-023](../06-fitxes-funcionals/uc-023.md)
- [UML integrat UC-023](uc-023-registrar-fraccio.md)
- [Classes ACTUAL/FINAL](uc-023-classes-actual-final.md)
- [Seqüències ACTUAL/FINAL](uc-023-sequencies-actual-final.md)
- [Activitats per pàgina ACTUAL/FINAL](uc-023-activitats-pagines-actual-final.md)
