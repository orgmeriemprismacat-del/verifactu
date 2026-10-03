# UC-003 — Diagrames d'activitat ACTUAL/FINAL per superfície i apartat

**Data d'auditoria:** 03/10/2026  
**Nota de modelatge:** UC-003 no és una pantalla d'usuari. Per complir la traçabilitat "per pàgina/apartat", es documenten les superfícies executables que intervenen en el cas. El JS pre-TPV es documenta com a dependència de frontera, no com a processador del callback.

## Índex de superfícies

| ID | Superfície | Tipus | Estat |
| --- | --- | --- | --- |
| P-RED-03-01 | `web-actual/pagina_efectuar_pagament_automatic.php` + JS | pre-TPV ACTUAL | existent |
| P-RED-03-02 | candidata `pay.../pagina_efectuar_pagament_automatic.php` | pont de cutover | existent; JS referenciat no present al snapshot |
| P-RED-03-03 | callback llegat `realitzaPagamentAutomatic.php` / `doit.php` | callback ACTUAL | existent temporal |
| P-RED-03-04 | `sif/public/api/redsys/callback.php` | endpoint FINAL | existent |
| P-RED-03-05 | `RedsysCallbackService` | recepció/dedupe FINAL | existent |
| P-RED-03-06 | `RedsysCallbackWorker` + queue repository | worker FINAL | existent |
| P-RED-03-07 | dispatcher + handlers | domini FINAL | existent |
| P-RED-03-08 | scripts de worker/preflight | operació FINAL | existent; runtime pendent d'evidència |

## P-RED-03-01 — checkout ACTUAL i JS

```mermaid
flowchart TD
  A[Usuari arriba al pagament] --> B[PHP valida gate servidor]
  B --> C[PHP crea dades Redsys]
  C --> D[Renderitza formulari #frm]
  D --> E[mostrarEfectuarPagamentAutomatic.js]
  E --> F{Confirmar?}
  F -->|Sí| G[submit #frm al gateway]
  F -->|No| H[mostra pagament ajornat]
  G --> I[Redsys]
```

**ACTUAL:** el JS només governa UX/submit. No valida signatura ni registra cobrament.  
**FINAL:** aquesta responsabilitat continua fora del callback; la preparació de la intenció correspon a UC-063/variant comercial.

## P-RED-03-02 — pont candidat de cutover

```mermaid
flowchart TD
  A[POST del checkout] --> B[JasomNovicePaymentGate]
  B --> C[SifRedsysCourseIntentClient]
  C --> D[API SIF crea/reutilitza intent]
  D --> E{cutover / drain}
  E -->|0 / qualsevol| F[MerchantURL legacy doit.php]
  E -->|1 / 0| G[Fail closed: no nova sessió]
  E -->|1 / 1| H[MerchantURL SIF HTTPS]
  H --> I[Redsys]
  F --> I
```

**Mancança documental/executable:** el PHP candidat carrega `https://pay.prisma.cat/js/mostrarEfectuarPagament.js`, però el fitxer no és dins de `codi-drive/pay-prisma-cat-canvis-verifactu`. Cal incorporar la còpia exacta desplegable o una evidència immutable del seu origen abans de declarar el frontal candidat completament auditat.

## P-RED-03-03 — callback llegat ACTUAL

```mermaid
flowchart TD
  A[Callback Redsys] --> B[Valida envelope/signatura]
  B --> C[Valida MerchantData, order, amount, EUR, terminal, comerç]
  C --> D{Autoritzat?}
  D -->|No| E[tractament denegat llegat]
  D -->|Sí| F[SELECT inscripció/curs]
  F --> G[correus operatius]
  G --> H[calcula número factura llegat]
  H --> I[INSERT factures]
  I --> J[UPDATE PAGAMENT/FACTURA_RELACIONADA/DATA PAG/FRACCIO]
```

**Risc:** segueix sent una escriptura fiscal/econòmica fora del SIF mentre el callback llegat sigui actiu. Els guards de cutover redueixen risc però no substitueixen la retirada final.

## P-RED-03-04 — endpoint FINAL

```mermaid
flowchart TD
  A[POST Redsys] --> B[carrega config]
  B --> C{merchant key/code configurats?}
  C -->|No| X[fail closed]
  C -->|Sí| D[RedsysSignatureValidator]
  D --> E{signatura i envelope vàlids?}
  E -->|No| X
  E -->|Sí| F[RedsysCallbackService]
  F --> G[JsonResponse]
```

**Invariant:** no hi ha JavaScript ni efecte fiscal directe a l'endpoint.

## P-RED-03-05 — recepció, correlació i dedupe FINAL

```mermaid
flowchart TD
  A[payload verificat] --> B[BEGIN]
  B --> C[find intent by DS_ORDER FOR UPDATE]
  C --> D{intent existeix?}
  D -->|No| X[rollback/error]
  D -->|Sí| E[compara amount/currency/terminal]
  E --> F{coherent?}
  F -->|No| X
  F -->|Sí| G[recordReceived]
  G --> H{duplicat compatible?}
  H -->|contradictori| Y[rollback + incidència]
  H -->|nou/exacte| I{response 0..99?}
  I -->|No| J[notification ERROR; no job]
  I -->|Sí| K[enqueue/reuse job]
  J --> L[COMMIT]
  K --> L
```

## P-RED-03-06 — worker/cua FINAL

```mermaid
flowchart TD
  A[runOne workerId] --> B[recoverStaleLocks]
  B --> C[claimNext]
  C --> D{job?}
  D -->|No| Z[null]
  D -->|Sí| E[processor.process]
  E --> F{resultat complet?}
  F -->|ok=true + factura + payment| G[markProcessed amb LOCKED_BY=workerId]
  F -->|incomplet/funcional| H[markIncident amb LOCKED_BY=workerId]
  E -->|error tècnic| I{intents màxims?}
  I -->|No| J[markRetry amb LOCKED_BY=workerId]
  I -->|Sí| H
  G --> K[retorna result]
  J --> K
  H --> L[obre incidència correlacionada]
```

**Canvi d'aquesta auditoria:** les transicions terminals del worker queden condicionades a `LOCKED_BY`, i `PROCESSED` exigeix `ok=true`, `uuid_factura` i `uuid_payment`.

## P-RED-03-07 — dispatcher i handlers FINAL

```mermaid
flowchart TD
  A[job + SNAPSHOT_JSON] --> B[RedsysCallbackDispatcher]
  B --> C{SOURCE_TYPE}
  C -->|CURS| D[RedsysCourseInvoiceService]
  C -->|PACK| E[RedsysPackInvoiceService]
  C -->|GRUP| F[RedsysGroupInvoiceService]
  C -->|REGAL| G[RedsysGiftInvoiceService]
  C -->|USOC_ALUMNE| H[RedsysUsocInvoiceService]
  D --> I[InvoiceService + CHARGE]
  E --> I
  F --> I
  G --> I
  H --> I
  D --> J[CourseEnrollmentFundAllocation]
  E --> K[PackEnrollmentFundAllocation]
  D --> L[legacy sync + outbox curs]
  E --> M[legacy sync pack + outbox pack]
  G --> N[gift entitlement]
  H --> O[USOC financing case]
```

**Cobertura desigual conscient:** l'atribució quantitativa `enrollment_fund_movement` és explícita per CURS i PACK. GRUP/REGAL/USOC tenen efectes específics diferents i s'han d'avaluar segons el seu contracte propi; UC-003 no ha de declarar-los equivalents sense prova.

## P-RED-03-08 — operació del worker

```mermaid
flowchart TD
  A[preflight-redsys-callback-queue.php] --> B{extensions/config/DB/taules OK?}
  B -->|No| X[exit 1]
  B -->|Sí| C[process-redsys-callback-queue.php]
  C --> D{SIF_ENV=production?}
  D -->|Sí i allow=0| X
  D -->|No o allow=1| E[valida --limit i --worker-id]
  E --> F[construeix serveis/handlers]
  F --> G[loop runOne fins limit o cua buida]
  G --> H[comptadors processed/retried/incidents]
```

**Pendent operatiu:** el repositori acredita script i preflight, però aquesta auditoria no acredita encara cron/supervisor, freqüència, alertes, secrets ni execució real a `sif_pre`/producció.

## 9. Matriu ACTUAL/FINAL per apartat

| Apartat | ACTUAL | FINAL | Estat |
| --- | --- | --- | --- |
| Preparació TPV | web PHP + JS | intenció SIF abans del TPV | candidat/SIF implementat |
| Callback | script llegat monolític | endpoint curt + servei | implementat |
| Signatura | RedsysAPI llegat endurit | `RedsysSignatureValidator` | implementat |
| Dedupe | no autoritat central | notification + queue unique/reuse | implementat |
| Factura | INSERT llegat | InvoiceService | implementat |
| Cobrament | camps acumulatius | payment_transaction/allocation | implementat |
| Concurrència | no modelada | claim + LOCKED_BY + stale recovery | implementat; fencing reforçat |
| Retry | no homogeni | 1/5/15/60 min | implementat |
| Incidència | ad hoc | errors_verifactu | implementat |
| JS | present a web-actual | no necessari al callback; candidat incomplet al snapshot | parcial |
| Runtime worker | no | script + preflight | codi sí, operació pendent |
| Cutover | legacy actiu | flags/drain + MerchantURL SIF | codi sí, execució pendent |

## 10. Estat

**DOCUMENTAT:** 8 superfícies ACTUAL/FINAL.  
**IMPLEMENTAT:** nucli FINAL i pont de cutover al repositori.  
**VERIFICAT:** proves automatitzades específiques existents; CI del head de l'auditoria encara pendent.  
**PENDENT:** JS candidat, preproducció Redsys, cron/supervisió, factura prèvia i retirada final dels callbacks llegats.
