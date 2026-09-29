# UC-009 · Diagrames d'activitat ACTUAL / FINAL — remetre registre fiscal a AEAT

**Data d'auditoria:** 2026-09-29  
**Branca:** `audit/uc-009-completar-implementacio-2026-09-29`  
**Criteri:** cada superfície executable o prevista queda separada en ACTUAL i FINAL. Quan una pantalla no existeix, l'ACTUAL ho indica explícitament i el FINAL descriu el comportament que s'ha de construir.

## 1. Mapa de superfícies i apartats

| ID | Superfície / apartat | ACTUAL | FINAL |
| --- | --- | --- | --- |
| A09-01 | Worker fiscal CLI | Implementat en preproducció | Mateix flux + evidència i ownership persistents |
| A09-02 | Claim d'una entrada de cua | Implementat | Fencing obligatori per claim token |
| A09-03 | Validació d'immutabilitat | Implementat | Es manté bloquejant |
| A09-04 | Enviament SOAP/mTLS | Implementat només endpoint AEAT de proves | Qualificació explícita abans d'habilitar producció |
| A09-05 | Resposta ACCEPTED / WITH_ERRORS / REJECTED | Implementat | Persistència d'intent abans de tancar la cua |
| A09-06 | Error retryable | Implementat | Només errors sense resultat remot acreditat |
| A09-07 | Resultat remot incert | Abans es podia convertir en RETRY | Estat REVIEW, sense reenviament cec |
| A09-08 | Stale lock / recuperació | Implementat | Claim token invalidat en recuperar |
| A09-09 | Preflight | Implementat via CLI | També visible des del panell |
| A09-10 | Panell `/sif/registres-aeat` | No localitzat / no executable | Llista, detall, incidents, intents i evidència |
| A09-11 | Reconciliació manual | No hi ha UI específica | Acció protegida sobre un intent REVIEW |
| A09-12 | Producció AEAT | Bloquejada per codi | Activació només després de qualificació i evidència |

---

## 2. A09-01 · Worker fiscal CLI

### ACTUAL

```mermaid
flowchart TD
    A[run-aeat-worker.php] --> B{CLI + SIF_ENV=preproduction + --send-test?}
    B -- No --> X[Abortar sense enviar]
    B -- Sí --> C[Construir SoapTransport de proves]
    C --> D[SerialWorker::runOnce]
    D --> E{GET_LOCK disponible?}
    E -- No --> F[WORKER_BUSY]
    E -- Sí --> G[Consultar head de fiscal_queue]
    G --> H{PENDING o RETRY i due?}
    H -- No --> I[WAIT / HEAD_REQUIRES_REVIEW / EMPTY]
    H -- Sí --> J[FiscalQueueProcessor::processNext]
```

### FINAL

```mermaid
flowchart TD
    A[Worker programat] --> B[Preflight obligatori]
    B --> C{Entorn qualificat?}
    C -- No --> X[Bloqueig + incidència]
    C -- Sí --> D[Lock global emissor]
    D --> E[Seleccionar head ordenat]
    E --> F[Claim + CLAIM_TOKEN]
    F --> G[Crear aeat_submission_attempt STARTED]
    G --> H[Enviar snapshot immutable]
    H --> I{Resultat}
    I -- Cert --> J[Persistir intent i registre]
    I -- Incert --> K[REVIEW + incidència + cap retry automàtic]
```

---

## 3. A09-02 · Claim, ownership i fencing

### ACTUAL abans de la correcció auditada

```mermaid
flowchart TD
    A[PENDING / RETRY] --> B[SELECT FOR UPDATE]
    B --> C[PROCESSING + ATTEMPTS + LOCKED_AT]
    C --> D[send]
    D --> E[complete WHERE ID]
    E --> F[SENT]
```

El `WHERE ID` no acreditava que el procés que completava continués sent propietari del claim.

### FINAL implementat a la branca

```mermaid
flowchart TD
    A[PENDING / RETRY] --> B[SELECT FOR UPDATE]
    B --> C[Generar CLAIM_TOKEN UUID]
    C --> D[PROCESSING + ATTEMPTS + LOCKED_AT + CLAIM_TOKEN]
    D --> E[send]
    E --> F{complete/fail conserva token?}
    F -- Sí --> G[Transició vàlida i CLAIM_TOKEN=NULL]
    F -- No --> H[STALE / ownership perdut]
    H --> I[No sobreescriure estat actual]
```

---

## 4. A09-03 · Validació d'immutabilitat

### ACTUAL / FINAL

```mermaid
flowchart TD
    A[PAYLOAD_JSON de fiscal_queue] --> B[Localitzar factura_registres per UUID + FISCAL_ORDER]
    B --> C[Comparar payload congelat]
    C --> D[Recalcular hash de cadena]
    D --> E{Coincideix?}
    E -- Sí --> F[Permetre intent AEAT]
    E -- No --> G[DEAD_LETTER]
    G --> H[FISCAL_PAYLOAD_CONFLICT]
```

Aquest flux ja és coherent amb l'objectiu FINAL i s'ha de mantenir bloquejant.

---

## 5. A09-04 · Enviament SOAP/mTLS

### ACTUAL

```mermaid
flowchart TD
    A[Payload amb snapshot aeat] --> B[XmlCodec]
    B --> C[ClientCertificate::inspect]
    C --> D[EvidenceStore::begin]
    D --> E[cURL HTTPS + mTLS]
    E --> F{HTTP 200 i SOAP correlacionable?}
    F -- Sí --> G[ResponseParser]
    F -- No --> H[AeatDeliveryUncertainException]
```

`SoapTransport` només accepta `TEST_ENDPOINT`.

### FINAL

```mermaid
flowchart TD
    A[Entorn declarat] --> B{PRE / PROD}
    B --> C[Endpoint allowlist versionat]
    C --> D[Certificat qualificat]
    D --> E[Esquemes verificats]
    E --> F[SOAP/mTLS]
    F --> G[Evidència immutable]
    G --> H[Resultat correlacionat amb attempt UUID]
```

Producció no s'ha d'activar simplement canviant una URL.

---

## 6. A09-05 · Resposta AEAT certa

### ACTUAL corregit

```mermaid
flowchart TD
    A[Resposta correlacionada] --> B{EstadoRegistro}
    B -- Correcto --> C[ACCEPTED]
    B -- AceptadoConErrores --> D[ACCEPTED_WITH_ERRORS]
    B -- Incorrecto --> E[REJECTED]
    C --> F[aeat_submission_attempt]
    D --> F
    E --> F
    F --> G[complete amb CLAIM_TOKEN]
    G --> H[fiscal_queue=SENT]
    G --> I[factura_registres.ESTAT_AEAT]
    G --> J[factura.ESTAT_AEAT]
```

`SENT` és estat de transport; no equival a `ACCEPTED`.

---

## 7. A09-06 · Error retryable

### ACTUAL / FINAL

```mermaid
flowchart TD
    A[Error sense resultat remot acreditat] --> B[aeat_submission_attempt=FAILED]
    B --> C{ATTEMPTS < max?}
    C -- Sí --> D[RETRY + backoff]
    C -- No --> E[DEAD_LETTER]
    E --> F[Incidència i bloqueig del head]
```

Només els errors que no tenen resultat remot conegut o potencialment rebut poden entrar en aquest camí.

---

## 8. A09-07 · Resultat remot incert

### ACTUAL abans de la correcció

```mermaid
flowchart TD
    A[AEAT pot haver rebut XML] --> B[Timeout / SOAP ambigu / commit local falla]
    B --> C[catch genèric]
    C --> D[RETRY]
    D --> E[Risc de reenviament cec]
```

### FINAL implementat a la branca

```mermaid
flowchart TD
    A[AEAT pot haver rebut XML] --> B[AeatDeliveryUncertainException o resultat remot no persistible]
    B --> C[aeat_submission_attempt=UNCERTAIN o resultat ja persistit]
    C --> D[fiscal_queue=REVIEW]
    D --> E[Incidència específica]
    E --> F[HEAD_REQUIRES_REVIEW]
    F --> G[Cap reenviament automàtic]
```

---

## 9. A09-08 · Recuperació de stale lock

### ACTUAL corregit / FINAL

```mermaid
flowchart TD
    A[PROCESSING antic] --> B{Lock global del worker disponible}
    B -- No --> C[No recuperar]
    B -- Sí --> D[recoverStaleLocks]
    D --> E[RETRY]
    E --> F[LOCKED_AT=NULL]
    F --> G[CLAIM_TOKEN=NULL]
    G --> H[Nou claim obté token nou]
```

Un procés antic no pot completar amb el token anterior.

---

## 10. A09-09 · Preflight

### ACTUAL

```mermaid
flowchart TD
    A[preflight-aeat-worker.php] --> B[AeatPreflight]
    B --> C[Certificat]
    B --> D[cURL/DOM/OpenSSL]
    B --> E[Esquemes]
    B --> F[Endpoint de proves]
    B --> G[EvidenceStore privat]
    A --> H[Metrics fiscal_queue]
    H --> I[DEAD_LETTER / due / stale]
```

### FINAL

El mateix resultat s'ha d'exposar al panell intern en mode lectura, sense revelar secrets, paths de certificat ni contingut sensible.

---

## 11. A09-10 · Panell `pay.prisma.cat/sif/registres-aeat`

### ACTUAL implementat a la branca 2026-09-30

```mermaid
flowchart TD
    A[GET /sif-registres-aeat.php] --> B[Sessió intranet]
    B --> C[Proxy ajax/sif/sifAeat.php]
    C --> D[HMAC servidor-servidor]
    D --> E[/api/aeat/operations.php]
    E --> F[summary / list / detail / preflight]
    F --> G[Resum + cua + registre + intents + incidències]
```

### FINAL — pàgina i apartats

```mermaid
flowchart TD
    A[GET /sif/registres-aeat] --> B[Autorització servidor]
    B --> C[Resum]
    B --> D[Cua]
    B --> E[Registres fiscals]
    B --> F[Intents]
    B --> G[Incidències]
    B --> H[Preflight]
    C --> C1[PENDING / PROCESSING / RETRY / REVIEW / DEAD_LETTER]
    D --> D1[queue ID + UUID factura + fiscal order + attempts]
    E --> E1[ACCEPTED / WITH_ERRORS / REJECTED / ERROR]
    F --> F1[attempt UUID + status + hashes + timestamps]
    G --> G1[Tipus + estat + correlació]
    H --> H1[checks segurs sense secrets]
```

### Apartat «Detall d'intent»

Ha de mostrar:
- `UUID_ATTEMPT`;
- `FISCAL_QUEUE_ID`;
- número d'intent;
- hash de request;
- estat `STARTED/FAILED/UNCERTAIN/ACCEPTED/ACCEPTED_WITH_ERRORS/REJECTED`;
- timestamps;
- CSV/codi d'error quan existeixen;
- referència d'evidència protegida quan estigui disponible.

No ha de mostrar:
- password del certificat;
- clau privada;
- PAN/CVV;
- paths interns sensibles;
- XML complet sense control d'accés.

---

## 12. A09-11 · Reconciliació de REVIEW

### ACTUAL implementat a la branca 2026-09-30

La UI mostra «Conciliar sense reenviar» només per un job `REVIEW` amb intent terminal remot `ACCEPTED`, `ACCEPTED_WITH_ERRORS` o `REJECTED`. El backend torna a validar el mateix `FISCAL_QUEUE_ID`, bloqueja files amb `FOR UPDATE`, regenera l'XML des del snapshot fiscal immutable i persisteix el resultat original sense cap segon SOAP. Un intent `UNCERTAIN` continua en `REVIEW`.

### FINAL

```mermaid
flowchart TD
    A[Job REVIEW] --> B[Responsable fiscal autoritzada]
    B --> C[Consultar attempt + EvidenceStore + registre]
    C --> D{Resultat remot acreditable?}
    D -- ACCEPTED / WITH_ERRORS / REJECTED --> E[Persistir resultat original sense nou SOAP]
    D -- No acreditable --> F[Mantenir REVIEW]
    F --> G[Decisió manual documentada]
    E --> H[Tancar incidència]
```

La reconciliació no pot crear una factura nova ni alterar el registre fiscal immutable.

---

## 13. A09-12 · Activació de producció

### ACTUAL

```mermaid
flowchart TD
    A[Intent endpoint producció] --> B[SoapTransport constructor]
    B --> C[Rebutjat]
```

### FINAL

```mermaid
flowchart TD
    A[Release candidata] --> B[Tests locals]
    B --> C[Tests integració sif_test]
    C --> D[Preproducció AEAT acreditada]
    D --> E[Certificat/representació validats]
    E --> F[Procediment rollback i incidències]
    F --> G[Aprovació de release]
    G --> H[Permetre endpoint producció]
```

---

## 14. Traçabilitat d'aquesta auditoria

| Necessitat | Codi / document |
| --- | --- |
| Claim i fencing | `FiscalQueueRepository`, migració `000010` |
| Intent persistent | `AeatSubmissionAttemptRepository`, `aeat_submission_attempt` |
| Resultat remot incert | `AeatDeliveryUncertainException`, `FiscalQueueProcessor::reviewHold()` |
| Lock d'emissor | `SerialWorker` |
| Control freqüència | `FlowControlledTransport`, `aeat_worker_state` |
| SOAP/mTLS | `SoapTransport`, `ClientCertificate` |
| XML / resposta | `XmlCodec`, `ResponseParser` |
| Evidència privada | `EvidenceStore` |
| Preflight | `AeatPreflight`, `preflight-aeat-worker.php` |
| Proves | `AeatWorkflowTest`, `FiscalQueueProcessorTest`, tests AEAT unit/integració |
| Panell | Implementat a la branca 2026-09-30; alta al menú de l'entorn pendent |

## 15. Estat de tancament

- **Documentat:** sí, inclosos ACTUAL/FINAL.
- **Implementat backend preproducció:** sí, amb fencing, ledger d'intents i REVIEW incorporats a la branca.
- **Panell web:** implementat; alta/configuració del menú de preproducció pendent.
- **Proves escrites:** sí; ampliades per intents, resultat incert, fencing, consulta operativa i reconciliació REVIEW.
- **Proves executades en entorn `sif_test*`:** pendents d'evidència.
- **Enviament AEAT real de preproducció:** pendent d'evidència.
- **Producció:** no habilitada.
