# UC-013 · Auditoria detallada i matriu de traçabilitat

**Data:** 29/09/2026  
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
| Validar afiliació | intranet | GET `sendMsgValidatCurosDescomptes.php` | `sendMsgValidatCurosDescomptes()` | VALID_DESC=1 | UC-019/013 | Sí | Sí | Sí | No |
| Denegar afiliació | intranet | mateix endpoint | mateix mètode | VALID_DESC=2 i possible canvi A_PAGAR | UC-019 | Sí | Sí | Sí | No |
| Emetre/cobrar alumne | worker/SIF | callback UC-03 | `RedsysUsocInvoiceService` | factura + payment + allocation | UC-019a/013 | Sí | Sí | Sí | Tests existeixen |
| Retornar pendent entitat | SIF | resposta servei | `RedsysUsocInvoiceService` | només resposta transitòria | UC-013 | Sí | Sí | Sí | Tests existeixen |
| Emetre factura entitat | CLI/servei; pantalla pendent | `process-usoc-entity.php` | `UsocEntityInvoiceService` | factura PENDING, fact_rels USOC_ENTITY | UC-019b/013 | Sí | Sí | Sí | Tests existeixen |
| Cobrar entitat | flux genèric | pendent mapatge específic | `PaymentService` | payment/allocation | UC-002/022/024 | Sí | Parcial | Parcial | No E2E |
| Conciliar dues parts | pendent | no acreditat | reconciliador DISSENY | estat expedient | UC-013 | Sí FINAL | No | Sí absència | No |
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

### P0
1. **Identitat de la inscripció:** `LegacyUsocSnapshotRepository` usa `WHERE IDPAG=? ORDER BY ID LIMIT 1`; cal impedir una selecció silenciosa si hi ha més d'una inscripció candidata.
2. **Relació factura alumne:** abans d'emetre la factura entitat cal demostrar que `student_invoice_uuid` correspon al mateix `ID_INSC/IDPAG`.
3. **Checkpoint durable:** `entity_invoice_pending` és un array retornat; la continuació de l'expedient no pot dependre només de conservar la resposta en memòria/canal.

### P1
4. Mutació de validació via GET → POST segur.
5. `IDPAG` llegat generat per últim+1 → mecanisme concurrent-safe.
6. Adaptador/pantalla final de gestió de factura entitat.
7. Conciliació final de dos pagadors.
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
| US13-07 | UUID alumne aliè | bloqueig | PENDENT CODI |
| US13-08 | IDPAG ambigu | bloqueig | PENDENT CODI |
| US13-09 | factura entitat sense ingrés | PENDING, 0 payments | TEST EXISTENT |
| US13-10 | pagament parcial entitat | PARTIAL | PENDENT E2E |
| US13-11 | dues factures/dos cobraments | FINANÇAMENT_CONCILIAT | PENDENT CODI/E2E |
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

El UC-013 no es pot marcar TANCAT fins que els P0 tinguin implementació i prova reproduïble.
