# UC-022 — Auditoria detallada i matriu de traçabilitat

**Data:** 03/10/2026  
**Cas:** UC-022 · Registrar una transferència rebuda  
**Mètode:** lectura estàtica exhaustiva de documentació, PHP/JS llegat, nucli SIF i tests del repositori.  
**Criteri:** `DOCUMENTAT` descriu contracte; `IMPLEMENTAT` exigeix codi executable; `VERIFICAT CODI` implica contrast directe del codi; `VERIFICAT TEST` només s'usa quan consta execució. En aquesta auditoria no s'ha executat la suite.

## 1. Resum executiu

| Àrea | Estat | Evidència |
| --- | --- | --- |
| Fitxa funcional específica | DOCUMENTAT, REVISAT | `documentacio/06-fitxes-funcionals/uc-022.md` |
| UML integrat | DOCUMENTAT, REVISAT | `uc-022-registrar-transferencia.md` |
| Nucli SIF per factura existent | IMPLEMENTAT / VERIFICAT CODI | `ManualPaymentService`, `ManualPaymentPayloadBuilder`, `PaymentService`, `PaymentRepository` |
| Idempotència per payload V2 | IMPLEMENTAT / VERIFICAT CODI | `PaymentService::assertSamePayload()` |
| Cerca factura per UUID/número visible | IMPLEMENTAT / VERIFICAT CODI | `ManualPaymentInvoiceRepository` |
| Registre CHARGE + allocation + estat cobrament | IMPLEMENTAT / VERIFICAT CODI | `PaymentRepository` |
| Tests del servei manual | IMPLEMENTATS, NO EXECUTATS EN AQUESTA AUDITORIA | `ManualPaymentServiceTest` i `ManualPaymentPayloadBuilderTest` |
| Pantalla intranet «Pagaments» | IMPLEMENTAT LLEGAT / VERIFICAT CODI | `codi-drive/intranet-actual/alumnes-pagaments.php` + JS |
| Endpoint llegat de comanda | IMPLEMENTAT LLEGAT / VERIFICAT CODI | `ajax/alumnes/efectuarPagament.php` |
| Endpoint SIF intern → ManualPaymentService | **IMPLEMENTAT EN AQUESTA BRANCA** | `sif/public/api/payments/manual-transfer.php` + `ManualTransferCommandService` |
| Autorització específica, CSRF i POST | PENDENT | el flux ACTUAL usa GET i sessió serialitzada |
| Identitat bancària externa única | **IMPLEMENTADA AL CONTRACTE SIF** | `external_bank_event_id` → `PROVIDER_REF` + idempotència `BANK_EVENT` |
| Conciliació transversal entre canals | PENDENT | no localitzat resolvedor bancari |
| Una transferència → N factures | PENDENT UC-105 | builder manual crea una sola allocation |
| Dossier classes ACTUAL/FINAL | CREAT EN AQUESTA AUDITORIA | `uc-022-classes-actual-final.md` |
| Dossier seqüències ACTUAL/FINAL | CREAT EN AQUESTA AUDITORIA | `uc-022-sequencies-actual-final.md` |
| Dossier activitats per pàgina/apartat | CREAT EN AQUESTA AUDITORIA | `uc-022-activitats-pagines-actual-final.md` |

## 2. Inventari de superfícies i codi

### P01 — Pàgina intranet `/alumnes/pagaments/`
- `codi-drive/intranet-actual/alumnes-pagaments.php`: inclou `inc/comprovarSessio.php` abans de renderitzar.
- `codi-drive/intranet-actual/js/alumnes-pagaments.js`: cerca per DNI, codi regal o número de factura; només permet un criteri; valida al client import, data i banc.
- El JS previsualitza en determinats casos i finalment crida `aplicarPagament(...)`.

### P02 — Comanda llegat
- `aplicarPagament()` envia **GET** a `ajax/alumnes/efectuarPagament.php`.
- Envia `id`, `numFact`, `tipus`, `pagament`, `dataPag`, `banc`, `obs` i `efact`.
- L'endpoint fa `session_start()`, deserialitza `usuari`/`intranet` i delega a `Intranet::efectuarPagament(...)`.
- En el fitxer endpoint inspeccionat no hi ha `comprovarSessio.php`, token CSRF ni verb POST.

### P03 — Nucli SIF manual
- `ManualPaymentService::registerByUuid()` i `registerByNumVisible()` localitzen la factura i deleguen al builder.
- `ManualPaymentPayloadBuilder` exigeix import positiu i data; per defecte usa `TRANSFERENCIA`, `CHARGE`, `INTRANET` i una sola allocation.
- Amb `reference`, la clau és `TRANSFERENCIA|REF:<reference>`; sense referència, combina factura, data, import i banc.
- `PaymentService` valida, bloqueja per clau, compara payload hash V1/V2 i reutilitza només si el payload és equivalent.
- `PaymentRepository` persisteix `payment_transaction`, `payment_allocation` i recalcula `factura.ESTAT_COBRAMENT`.

### P04 — API genèrica SIF
- `sif/public/api/payments/register.php` exposa el `PaymentService` genèric.
- No instancia `ManualPaymentService` ni acredita la vinculació directa de la pantalla d'intranet al servei manual.

## 3. Troballes detallades

| ID | Troballa | Estat |
| --- | --- | --- |
| UC022-01 | La fitxa funcional existia però continuava com `STRUCTURED_DRAFT_NEEDS_CASE_REVIEW`. | DOCUMENTAT |
| UC022-02 | L'UML integrat ja descrivia el servei manual i riscos, però no separava dossiers complets ACTUAL/FINAL per classes, seqüències i activitats. | DOCUMENTAT |
| UC022-03 | El circuit real d'intranet usa JS llegat + GET amb efectes persistents. | VERIFICAT CODI |
| UC022-04 | La pàgina principal sí inclou `comprovarSessio.php`; l'endpoint de comanda inspeccionat no. | VERIFICAT CODI |
| UC022-05 | No s'ha localitzat CSRF específic a l'endpoint `efectuarPagament.php`. | VERIFICAT REPO |
| UC022-06 | El navegador envia import, data, banc i observacions; el control visible és client-side. | VERIFICAT CODI |
| UC022-07 | El nucli SIF manual no està connectat documentalment ni per codi a aquest endpoint llegat. | VERIFICAT REPO |
| UC022-08 | `ManualPaymentService` rebutja factura inexistent abans de registrar moviment. | VERIFICAT CODI |
| UC022-09 | `ManualPaymentPayloadBuilder` força CHARGE/INTRANET i una allocation a una factura. | VERIFICAT CODI |
| UC022-10 | El builder valida import > 0, data no buida i mètode admès. | VERIFICAT CODI |
| UC022-11 | Amb referència, la idempotency key no inclou factura/import; la seguretat davant payload contradictori depèn del hash V2 de `PaymentService`. | VERIFICAT CODI |
| UC022-12 | `PaymentService` compara el payload existent i llança conflicte si mateixa clau té payload diferent. | VERIFICAT CODI |
| UC022-13 | `PaymentRepository` actualitza l'estat `PARTIAL/PAID/OVERPAID` a partir del ledger. | VERIFICAT CODI |
| UC022-14 | La persistència no acredita identitat bancària externa immutable; `REFERENCIA_BANCARIA` és text. | VERIFICAT CODI / GAP |
| UC022-15 | Dues transferències reals amb la mateixa referència textual poden col·lidir amb la política actual de clau. | RISC DEDUÏT |
| UC022-16 | Sense referència, factura+dia+import+banc és heurística de reintent, no identificador bancari extern. | VERIFICAT CODI / GAP |
| UC022-17 | La conciliació del titular/pagador i l'existència real de l'abonament queden fora del servei revisat. | VERIFICAT LÍMIT |
| UC022-18 | Una transferència repartida entre diverses factures no és suportada pel builder manual. | VERIFICAT CODI |
| UC022-19 | El repositori genèric suporta múltiples allocations, però cal contracte UC-105 i control del saldo de l'entrada. | IMPLEMENTAT BASE / PENDENT CAS |
| UC022-20 | Els tests existents cobreixen alta per UUID, número visible, reintent equivalent, parcial i factura absent. | VERIFICAT CODI |
| UC022-21 | Els tests existents no acrediten, per si sols, execució en MySQL/preproducció. | PENDENT VERIFICACIÓ |
| UC022-22 | No hi ha prova E2E intranet → SIF → ledger → sincronització llegat. | PENDENT |
| UC022-23 | No hi ha evidència específica de permisos de comanda, CSRF, idempotency key del canal i resposta JSON tipificada. | PENDENT |
| UC022-24 | L'API genèrica de pagaments no substitueix un adaptador manual autoritzat perquè accepta payload complet del client. | VERIFICAT ARQUITECTURA |
| UC022-25 | El flux FINAL ha de separar «identificar entrada bancària» de «assignar-la a factura/es». | DECISIÓ ARQUITECTÒNICA DOCUMENTADA |

## 4. ACTUAL vs FINAL

| Capacitat | ACTUAL | FINAL |
| --- | --- | --- |
| Cerca | DNI/codi regal/factura des del JS | cerca server-side amb permisos i objecte factura SIF |
| Comanda | GET llegat | POST autenticat + CSRF + permís específic |
| Import | enviat pel navegador | validat contra entrada bancària i saldo disponible |
| Identitat transferència | referència/banc/data/import | `external_bank_event_id` o equivalent immutable |
| Idempotència | no acreditada al canal llegat | clau de canal + identitat externa + payload hash |
| Persistència | llegat `Intranet::efectuarPagament` | `payment_transaction` + allocations SIF |
| Multifactura | flux llegat no reconciliat amb SIF | un CHARGE + N allocations, UC-105 |
| Sincronització llegat | escriptura directa llegat | només després de commit SIF, reintent idempotent |
| Errors | text HTML / cerca de paraula `error` | JSON tipificat: CREATED/REUSED/CONFLICT/VALIDATION/ERROR |
| Auditoria | no acreditada end-to-end | payment_action_event/operational_event + correlation |

## 5. Verificat vs no verificat

### VERIFICAT CODI
- existència i forma del flux JS/GET;
- validacions del builder;
- cerca de factura;
- persistència del ledger;
- càlcul de l'estat de cobrament;
- idempotència V1/V2 de `PaymentService`;
- tests existents al repositori.

### NO VERIFICAT EN EXECUCIÓ
- execució de la suite;
- MySQL `sif_test*`;
- preproducció;
- permisos reals del canal;
- protecció CSRF;
- prova d'una transferència real;
- sincronització posterior al commit;
- E2E amb factura prèvia, parcial, complet, conflicte i multifactura.

## 6. Mancances prioritzades

### P0 — abans d'activar el canal
1. Connectar l'adaptador intranet server-side amb l'endpoint signat `POST /api/payments/manual-transfer.php` ja implementat.
2. Substituir GET per POST i validar sessió, rol, permís específic i CSRF.
3. No acceptar com autoritatius import/factura/estat només perquè els envia el client.
4. Fer que la intranet subministri l'`external_bank_event_id` immutable exigit pel nou contracte; definir la font exacta d'aquest identificador en la conciliació bancària.
5. Definir resposta JSON tipificada i gestió explícita de `CONFLICT`.
6. Garantir que cap fallback escriu directament al llegat si falla el SIF.

### P1
7. Implementar/ratificar UC-105 per multifactura.
8. Registrar auditoria funcional/correlació de la comanda.
9. Sincronització llegat post-commit amb cua/reintent idempotent.
10. Proves d'integració de canal i evidència MySQL/preproducció.

## 7. Criteris de tancament UC-022

UC-022 no s'ha de marcar `COMPLETE` fins que:
- la pantalla real cridi el contracte SIF;
- la comanda sigui POST autoritzada i protegida;
- la intranet aporti i conservi l'`external_bank_event_id` estable que el SIF ja exigeix;
- els reintents equivalents reutilitzin i els contradictoris retornin conflicte;
- parcial/complet/sobrepagament quedin demostrats;
- la sincronització llegat no pugui duplicar el CHARGE;
- existeixi evidència d'execució de tests i, com a mínim, una prova de preproducció.

## 8. Relacions

- [Fitxa funcional](../06-fitxes-funcionals/uc-022.md)
- [UML integrat](uc-022-registrar-transferencia.md)
- [Classes ACTUAL/FINAL](uc-022-classes-actual-final.md)
- [Seqüències ACTUAL/FINAL](uc-022-sequencies-actual-final.md)
- [Activitats per pàgina/apartat](uc-022-activitats-pagines-actual-final.md)
- UC-002: registre de cobrament genèric.
- UC-104: excés/saldo.
- UC-105: una transferència repartida entre diverses factures.


## 9. Implementació posterior dins la branca d'auditoria

### 9.1. Endpoint intern signat

S'ha incorporat `sif/public/api/payments/manual-transfer.php`, exclusivament **POST**, autenticat amb el mecanisme existent `InternalApiAuthenticator`. Això aporta:

- HMAC SHA-256 sobre mètode, path, timestamp, request UUID, actor, rols i hash del body;
- finestra temporal configurable;
- anti-replay persistent amb `internal_api_request`;
- identitat d'actor i rols procedents de la intranet/server caller;
- resposta JSON SIF homogènia.

### 9.2. Autorització de la comanda

`ManualTransferCommandService` exigeix:
- actor autenticat;
- intersecció amb `SIF_MANUAL_TRANSFER_ROLES`;
- exactament un selector de factura (`uuid_factura` o `num_visible`);
- `external_bank_event_id` no buit;
- mètode forçat a `TRANSFERENCIA`.

### 9.3. Identitat de l'entrada bancària

`ManualPaymentPayloadBuilder` ara prioritza:

`TRANSFERENCIA|BANK_EVENT:<external_bank_event_id>`

i persisteix aquest identificador a `payment_transaction.PROVIDER_REF`.

La `reference` lliure continua a `REFERENCIA_BANCARIA`, però ja no és la identitat principal quan existeix l'event immutable.

### 9.4. Tests nous

- `ManualTransferCommandServiceTest`: rol autoritzat, rol denegat, event bancari obligatori i conflicte del mateix event sobre factura diferent.
- `ManualPaymentPayloadBuilderTest`: prioritat de `external_bank_event_id` respecte de la referència lliure.
- `ManualPaymentServiceTest`: mateix `reference` amb payload/factura diferent retorna conflicte.

Aquests tests continuen com **CREATS / PENDENTS DE RESULTAT** fins que finalitzi el workflow MySQL del PR.
