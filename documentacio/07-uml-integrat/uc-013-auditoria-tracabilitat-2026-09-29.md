# UC-013 · Auditoria detallada i matriu de traçabilitat

**Data:** 29/09/2026 · actualització d'implementació 30/09/2026  
**Repositori:** `orgmeriemprismacat-del/verifactu`  
**Branca d'auditoria:** `audit/uc-013-usoc-2026-09-29`

## 1. Llegenda d'estats

- **DOCUMENTAT**: existeix documentació específica del comportament.
- **IMPLEMENTAT**: existeix codi executable corresponent.
- **VERIFICAT**: contrast estàtic contra codi real del repositori.
- **PROVAT**: execució acreditada amb resultat.
- **PENDENT**: falta implementació, prova o decisió.

## 2. Matriu principal

| Acció | Pàgina/canal | JS/endpoint | PHP/servei | BD/efecte | UC relacionat | DOC | IMP | VER | TEST |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Consultar condicions USOC | web | `mostrarDescomptesUsoc.min.js` | `PaginaDescomptesUsoc.php` | lectura preus/descomptes | UC-013 | Sí | Sí | Sí | No |
| Sol·licitar USOC | web inscripció | `mostrarInscripcionsAfiliats.min.js` | `enviarInscripcioAfiliat.php` | `inscripcions`, TIPUS_DESC=4, VALID_DESC pendent | UC-019/013 | Sí | Sí | Sí | No |
| Comunicar a FEUSOC | web backend | endpoint alta | `enviarInscripcioAfiliat.php` | correu extern | UC-019 | Sí | Sí | Sí | No |
| Mostrar pendents | intranet | `alumnes-validar-descomptes.js` | `Intranet.php` | SELECT VALID_DESC=0 | UC-019 | Sí | Sí | Sí | No |
| Validar afiliació | intranet | POST `sendMsgValidatCurosDescomptes.php` + CSRF + requestId | `sendMsgValidatCurosDescomptes()` + `ROLS_EDITAR` | VALID_DESC=1 | UC-019/013 | Sí | Sí | Sí | test de regressió existent |
| Denegar afiliació | intranet | mateix endpoint | mateix mètode | VALID_DESC=2 i possible canvi A_PAGAR | UC-019 | Sí | Sí | Sí | No |
| Emetre/cobrar alumne | worker/SIF | callback UC-03 | `RedsysUsocInvoiceService` | factura + payment + allocation | UC-019a/013 | Sí | Sí | Sí | Tests existeixen |
| Persistir pendent entitat | SIF | resposta + checkpoint | `RedsysUsocInvoiceService` + `UsocFinancingCaseRepository` | `usoc_financing_case=PENDING_ENTITY_INVOICE` | UC-013 | Sí | Sí | Sí | VERIFICAT CI · run 36657971568 |
| Emetre factura entitat | UI autònoma + panell Consulta/Modifica alumne + API signada | `SifInternalUsocClient` / `/api/usoc/manage.php` | `UsocEntityInvoiceService` + `UsocFinancingCaseRepository::requireForEntityInvoice()` + `UsocStudentInvoiceLinkRepository` | valida checkpoint abans d'emetre; factura PENDING + `fact_rels` + `ENTITY_INVOICED` | UC-019b/013 | Sí | Sí | Sí | tests de servei/checkpoint PASS en CI; últim contracte UI pendent d'acreditar |
| Cobrar entitat | UI autònoma + panell contextual + API signada + ruta preproducció | `register_entity_payment` / `process-usoc-entity-payment.php` | `UsocEntityPaymentService` → `PaymentService` → `UsocCaseReconciler` | payment/allocation + actualització immediata `usoc_financing_case` | UC-002/022/024/013 | Sí | Sí | Sí | VERIFICAT CI · run 36657971568 |
| Conciliar dues parts | CLI/preproducció | `reconcile-usoc-case.php` | `UsocCaseReconciler` | actualitza `usoc_financing_case` segons estats de factura i imports | UC-013 | Sí | Sí | Sí | Test afegit, execució no acreditada |
| Canvi/baixa | intranet | fluxos compartits | UC-026/027/005 | rectificacions/moviments | UC-013+ | Parcial | Parcial | Parcial | No E2E |

## 3. Evidència específica

### A. Sol·licitud USOC
`enviarInscripcioAfiliat.php` força `TIPUS_DESC=4`, usa `anticipi-preu-usoc`, crea la inscripció i envia petició de confirmació a FEUSOC.

### B. Validació
`Intranet.php` conté:
- `cnsAlumnDescNoValidat` → `VALID_DESC=0`;
- `updValidDescByInsc`;
- `updValidDescByInscPreu`;
- lògica específica per `TIPUS_DESC==4`.

### C. Factura alumne
`RedsysUsocInvoiceService`:
- exigeix import entitat positiu;
- emet factura alumne;
- incorpora el cobrament Redsys;
- retorna `entity_invoice_pending`.

### D. Factura entitat
`UsocEntityInvoiceService`:
- exigeix dades fiscals explícites;
- emet sense payment;
- retorna `payment_registered=false`.

### E. Idempotència
`InvoiceService` reutilitza només després de:
`PayloadIdempotencyValidator::assertMatches(payload, IDEMPOTENCY_PAYLOAD_HASH)`.

Per tant el conflicte semàntic d'una mateixa clau amb payload diferent queda protegit al nucli.

## 4. Mancances tècniques prioritzades

### P0 — implementats en repositori el 30/09/2026
1. **Identitat de la inscripció — TANCAT EN CODI:** `LegacyUsocSnapshotRepository` exigeix `ID_INSC` i consulta `IDPAG + ID`; s'ha eliminat `ORDER BY ID LIMIT 1`.
2. **Relació factura alumne — TANCAT EN CODI:** `UsocStudentInvoiceLinkRepository` valida UUID, `ID_INSC`, `IDPAG`, canal/tipus Redsys USOC i total de la factura alumne.
3. **Checkpoint durable — IMPLEMENTAT:** `usoc_financing_case` + `UsocFinancingCaseRepository` persisteixen factura alumne, factura entitat, imports i estat.
4. **Conciliació — IMPLEMENTADA EN LA RUTA USOC:** `UsocEntityPaymentService` registra el cobrament i invoca `UsocCaseReconciler`; `reconcile-usoc-case.php` queda com a eina controlada de recuperació. L'endpoint genèric de pagaments no té aquest hook específic.

### P1
4. Validació legacy via POST + CSRF + `ROLS_EDITAR` — IMPLEMENTADA; falta traça persistent SIF de la decisió.
5. `IDPAG` legacy — IMPLEMENTAT allocator compartit amb named lock MySQL als fluxos actuals identificats.
6. Adaptador/pantalla final — IMPLEMENTAT EN REPOSITORI: pantalla autònoma + panell contextual a Consulta/Modifica alumne, sobre API interna HMAC i `capabilities.manage`.
7. Menú implementat de forma fail-closed a `mostrarSideBarMenu.php` amb `SIF_USOC_MENU_ROLES`. Pendent validar configuració/rols/secrets amb `preflight-usoc-intranet.php` i desplegament real.
8. Prova E2E amb callback duplicat i pagament entitat parcial/complet.

### Decisió funcional
9. Variant curs gratuït USOC / alumne=0.
10. Consolidar percentatge comercial vigent.
11. Confirmar classificació fiscal de totes les variants que utilitzen el builder EXEMPT.

## 5. Matriu de proves

| ID | Escenari | Esperat | Estat |
| --- | --- | --- | --- |
| US13-01 | VALID_DESC=0 | no emissió USOC | PENDENT EXECUCIÓ |
| US13-02 | validació positiva | snapshot coherent | PENDENT EXECUCIÓ |
| US13-03 | callback alumne duplicat | mateixa factura/payment | TEST EXISTENT, EXECUCIÓ NO ACREDITADA |
| US13-04 | factura entitat repetida equivalent | reús | TEST EXISTENT |
| US13-05 | mateixa clau, amount diferent | CONFLICT | TEST AFEGIT EN AQUESTA BRANCA |
| US13-06 | mateixa clau, NIF diferent | CONFLICT | TEST AFEGIT EN AQUESTA BRANCA |
| US13-07 | UUID alumne aliè | bloqueig | TEST AFEGIT · CODI IMPLEMENTAT |
| US13-08 | IDPAG ambigu | `ID_INSC` obligatori; no fallback | TEST AFEGIT · CODI IMPLEMENTAT |
| US13-09 | factura entitat sense ingrés | PENDING, 0 payments | TEST EXISTENT |
| US13-10 | cobrament entitat parcial real via PaymentService | `ENTITY_PARTIAL` | PASS CI · run 36657971568 |
| US13-11 | 10 € + 15 € sobre factura entitat de 25 € | `FINANCING_RECONCILED` | TEST INTEGRACIÓ AFEGIT · EXECUCIÓ NO ACREDITADA |
| US13-12 | alumne=0 | circuit especial o bloqueig explícit | PENDENT DECISIÓ |

## 6. Fitxers del paquet UC-013

- `documentacio/06-fitxes-funcionals/uc-013.md`
- `documentacio/07-uml-integrat/uc-013-orquestrar-doble-facturacio-usoc.md`
- `documentacio/07-uml-integrat/uc-013-activitats-pagines-actual-final.md`
- `documentacio/07-uml-integrat/uc-013-auditoria-tracabilitat-2026-09-29.md`

## 7. Estat de tancament

**DOC:** ampliada i específica.  
**IMP:** parcial.  
**VERIFICACIÓ ESTÀTICA:** sí.  
**TEST EXECUTAT:** no acreditat.  
**PREPRODUCCIÓ:** no acreditada.  
**PRODUCCIÓ:** no acreditada.

Els P0 estructurals estan implementats i les proves USOC corresponents consten PASS al run CI `36657971568`. El UC-013 encara no es marca TANCAT perquè falta desplegament/configuració real de la UI, integració al menú/routing habitual i decisions funcionals pendents (curs gratuït i regla percentual històrica). La suite global del run queda vermella per una única fallida aliena al UC-013 (`InvoiceQueryServiceTest`, 404 vs 422).
