# UC-009 · Auditoria exhaustiva i traçabilitat — 2026-10-03

**Cas:** UC-009 · Remetre registre fiscal a AEAT  
**Repositori:** `orgmeriemprismacat-del/verifactu`  
**Base auditada:** `main@b0e8ff7150c5a8b415cc109d298d82f0db1f68df`  
**Branca de correcció:** `audit/uc-009-revalidacio-2026-10-03`

## 1. Veredicte per dimensions

| Dimensió | Estat | Conclusió |
| --- | --- | --- |
| Fitxa funcional | DOCUMENTAT | existeix i és extensa; requeria revalidar estat CI/canal real |
| Codi worker/cua | IMPLEMENTAT + VERIFICAT UC-009 | claim, fencing, intents, retry/dead-letter, REVIEW |
| Transport AEAT | IMPLEMENTAT PREPROD | SOAP/mTLS restringit deliberadament a endpoint de proves |
| Panell intranet | IMPLEMENTAT | PHP + JS + bridge + HMAC + API interna |
| Reconciliació REVIEW | IMPLEMENTAT + VERIFICAT UC-009 | sense segon SOAP, amb hash i latest attempt |
| Classes ACTUAL/FINAL | FALTAVA FITXER SEPARAT | creat en aquesta auditoria |
| Seqüències ACTUAL/FINAL | FALTAVA FITXER SEPARAT | creat en aquesta auditoria |
| Activitats ACTUAL/FINAL | DOCUMENTAT | existia; cal actualitzar tall i superfícies |
| Traçabilitat exhaustiva | PARCIAL | aquest document la completa |
| CI específic intranet UC-009 | MANCANÇA | no disparava/lintava els fitxers del panell; corregit a la branca |
| Preproducció real | PENDENT | no hi ha evidència de desplegament/rols/certificat/enviament real |
| Producció | BLOQUEJADA | el transport rebutja endpoints diferents del de proves |

## 2. Inventari 1:1 de superfícies i codi

| ID | Superfície | Codi ACTUAL | Estat |
| --- | --- | --- | --- |
| P-AEAT-01 | shell panell | `codi-drive/intranet-actual/sif-registres-aeat.php` | implementat |
| P-AEAT-02 | client navegador | `codi-drive/intranet-actual/js/sif-registres-aeat.js` | implementat |
| P-AEAT-03 | estil panell | `codi-drive/intranet-actual/css/sif-registres-aeat.css` | implementat |
| P-AEAT-04 | bridge AJAX | `codi-drive/intranet-actual/ajax/sif/sifAeat.php` | implementat |
| P-AEAT-05 | client HMAC intranet | `codi-drive/intranet-actual/SifInternalAeatClient.php` | implementat |
| P-AEAT-06 | API operativa | `sif/public/api/aeat/operations.php` | implementat |
| P-AEAT-07 | repositori lectura | `AeatOperationsReadRepository` | implementat/provat |
| P-AEAT-08 | worker CLI | `run-aeat-worker.php` + `SerialWorker` | implementat/provat |
| P-AEAT-09 | processador cua | `FiscalQueueProcessor` | implementat/provat |
| P-AEAT-10 | persistència cua | `FiscalQueueRepository` | implementat/provat |
| P-AEAT-11 | ledger intents | `AeatSubmissionAttemptRepository` | implementat/provat |
| P-AEAT-12 | SOAP/mTLS | `SoapTransport`, `ClientCertificate` | implementat per proves |
| P-AEAT-13 | XML/resposta | `XmlCodec`, `ResponseParser` | implementat/provat |
| P-AEAT-14 | evidència | `EvidenceStore` | implementat/provat localment |
| P-AEAT-15 | preflight | `AeatPreflight`, `preflight-aeat-worker.php` | implementat/provat |
| P-AEAT-16 | REVIEW | `AeatReviewReconciliationService` | implementat/provat |
| P-AEAT-17 | menú intranet | `apartats` d'entorn | pendent d'evidència |
| P-AEAT-18 | descobriment menú | `preflight-sif-registres-aeat-menu.php` | creat a la branca |

## 3. Inventari documental

### Ja existia

- `documentacio/06-fitxes-funcionals/uc-009.md`
- `documentacio/07-uml-integrat/uc-009-remetre-registre-aeat.md`
- `documentacio/07-uml-integrat/uc-009-activitats-actual-final.md`
- `documentacio/05-governanca-operacio/uc-009-panell-registres-aeat-desplegament.md`
- `documentacio/09-evidencies/UC-009/00-tancament-tecnic-2026-09-30.md`

### Creat en aquesta auditoria

- `uc-009-classes-actual-final.md`
- `uc-009-sequencies-actual-final.md`
- `uc-009-auditoria-tracabilitat-2026-10-03.md`

Per tant, abans d'aquesta passada **no** hi havia totes les peces separades exigides pel patró d'auditoria avançat: classes i seqüències ACTUAL/FINAL estaven dins la fitxa integrada, però no en artefactes dedicats.

## 4. Contrast funcional ↔ codi

### 4.1. Remissió fiscal

**Documentat:** el UC envia un registre ja emès/congelat.  
**Implementat:** la cua conté snapshot fiscal, es verifica immutabilitat abans de xarxa i es crea un intent.  
**Verificat:** els tests de workflow/processador del run CI 02/10 passen.  
**Pendent:** una prova externa real contra AEAT de preproducció.

### 4.2. Idempotència i concurrència

**Documentat:** no repetir efectes fiscals ni sobreescriure ownership antic.  
**Implementat:** `CLAIM_TOKEN`, `FOR UPDATE`, lock global d'emissor, `REQUEST_HASH`, attempt number.  
**Verificat:** test d'obsolete claim i tests de competing worker passen.  
**Pendent:** evidència operativa amb dos processos reals no és necessària per acreditar el contracte de codi, però sí que pot formar part del runbook de preproducció.

### 4.3. Resultat remot incert

**Documentat:** no fer retry cec.  
**Implementat:** `AeatDeliveryUncertainException` → `REVIEW`; no `NEXT_RETRY_AT`.  
**Verificat:** test `testUncertainDeliveryMovesQueueToReviewAndNeverBlindlyRetries` passa.  
**Pendent:** procediment humà sobre un cas real.

### 4.4. Reconciliació

**Documentat:** persistir un resultat terminal ja guardat sense segon SOAP.  
**Implementat:** només últim attempt del mateix queue, estat terminal, hash de request coincident i transacció.  
**Verificat:** tests de reconciliació passen.  
**Correcció 03/10:** validació d'`attempt_uuid` passa de patró permissiu a estructura UUID 8-4-4-4-12.

### 4.5. Panell

**Documentat:** resum, cua, detall, intents, incidències, preflight i reconcile.  
**Implementat:** existeixen tots aquests blocs al PHP/JS.  
**Seguretat implementada:** sessió intranet, HMAC server-side, anti-replay intern, read/reconcile roles i CSRF de la mutació.  
**Mancança trobada:** els fitxers intranet UC-009 no estaven explícitament dins els paths/lint del workflow SIF.  
**Correcció 03/10:** s'afegeixen trigger, lint PHP/JS i `AeatIntranetUiContractTest`.

## 5. Proves i CI: estat exacte

### Evidència històrica 30/09

El paquet UC-009 conserva l'evidència de **558 passed / 0 failed** associada al seu tall de PR/merge. És una evidència històrica vàlida del commit que documenta, però no valida automàticament `main` posterior.

### Revalidació de `main` 02/10

Workflow `SIF PHP MySQL tests`, run `37061206441` sobre `main@b0e8ff7...`:

- **917 passed / 6 failed** globalment.
- Els tests AEAT/UC-009 mostrats al log passen.
- Les 6 fallades són de PACK/Redsys i no del UC-009.

Això implica:

- **UC-009 específic:** verificació automàtica disponible i verda dins del run.
- **pipeline global actual de main:** vermell; no s'ha de descriure com a “suite global passada”.
- **branca 03/10:** la nova prova UI/UUID/CI queda pendent del seu workflow.

## 6. Mancances detectades i tractament

| ID | Mancança | Impacte | Acció |
| --- | --- | --- | --- |
| GAP09-01 | classes A/F no separades | documental | creat fitxer |
| GAP09-02 | seqüències A/F no separades | documental | creat fitxer |
| GAP09-03 | docs encara parlaven de panell “pendent”/branca antiga | traçabilitat | actualitzar |
| GAP09-04 | referència a `pay.prisma.cat/sif/registres-aeat` | exactitud | corregir a intranet + API SIF |
| GAP09-05 | 558/0 podia llegir-se com estat CI vigent | evidència | etiquetar històric + revalidació 917/6 |
| GAP09-06 | workflow no cobria canvis només a UI UC-009 | regressió | paths + lint |
| GAP09-07 | no hi havia contracte específic UI/proxy | regressió/seguretat | crear test |
| GAP09-08 | no hi havia preflight específic de menú UC-009 | desplegament | crear script read-only |
| GAP09-09 | regex UUID reconciliació massa permissiva | validació | endurir + test |
| GAP09-10 | shell PHP no fa un gate local de rol abans de renderitzar | defensa en profunditat | API continua fail-closed; valorar gate local quan rols intranet reals estiguin definits |
| GAP09-11 | `AeatSubmissionAttemptRepository` fixa `preproduction` | futur multi-entorn | acceptable mentre transport només admet proves; refactor abans de producció |
| GAP09-12 | cap evidència de xarxa/certificat/AEAT real | operativa | pendent preproducció |
| GAP09-13 | alta `apartats` no acreditada | operativa | executar preflight + alta controlada |
| GAP09-14 | pipeline global main vermell per 6 errors aliens | release | resoldre/baseline abans de considerar release global verd |

## 7. Traçabilitat requisit → implementació → prova

| Requisit | Implementació | Prova/evidència |
| --- | --- | --- |
| no duplicar ownership | `CLAIM_TOKEN` | `FiscalQueueProcessorTest::testObsoleteClaimCannotCompleteFiscalQueueItem` |
| ordre serial emissor | `SerialWorker::GET_LOCK` | `AeatWorkflowTest::testCompetingWorkerCannotClaimOrRecoverWhileLockIsHeld` |
| snapshot immutable | `assertImmutablePayload` | `PayloadIdempotencyFlowTest::testFiscalQueueTamperingIsQuarantinedWithoutSending` |
| intent abans de xarxa | `AeatSubmissionAttemptRepository::begin` | `AeatWorkflowTest::testPersistsSubmissionAttemptBeforeAndAfterAcceptedDelivery` |
| no retry cec | `REVIEW` | `testUncertainDeliveryMovesQueueToReviewAndNeverBlindlyRetries` |
| separar SENT/resultat fiscal | cua + `ESTAT_AEAT` | processor tests + panell |
| no exposar payload/XML | read repository projection | `AeatOperationsReadRepositoryTest` |
| HMAC/anti-replay | `InternalApiAuthenticator` | `InternalApiAuthenticatorTest` |
| reconcile sense resend | `AeatReviewReconciliationService` | `AeatReviewReconciliationServiceTest` |
| boundary browser segur | bridge + client server-side | `AeatIntranetUiContractTest` (branca) |
| certificat/evidència | `ClientCertificate`, `EvidenceStore` | unit tests; entorn real pendent |

## 8. Criteri de tancament

### Tancat a nivell de codi/documentació quan el CI de la branca confirmi

- fitxa reconciliada amb el main;
- classes, seqüències i activitats A/F presents;
- contracte UI UC-009 passat;
- regressió UUID passada;
- lint del panell passat;
- cap nova fallada UC-009.

### Pendent per tancament operatiu

1. desplegar SIF/intranet a preproducció;
2. aplicar/verificar migracions;
3. configurar secrets i rols;
4. executar preflight de menú i donar d'alta `/sif-registres-aeat.php`;
5. provar usuari autoritzat/no autoritzat;
6. provar summary/list/detail/preflight contra SIF desplegat;
7. provar un `REVIEW` controlat;
8. acreditar certificat i enviament AEAT de preproducció;
9. conservar evidència;
10. només després definir el mecanisme explícit d'habilitació de producció.

**Conclusió:** UC-009 té el nucli funcional i operatiu de codi molt avançat i específicament provat, però no s'ha de marcar “producció llesta”. El que faltava principalment era el paquet d'auditoria separat, la cobertura CI de la frontera intranet i l'evidència d'entorn.
