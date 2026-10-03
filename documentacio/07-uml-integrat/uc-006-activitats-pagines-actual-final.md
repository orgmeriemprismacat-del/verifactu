# UC-006 · Diagrames d’activitats ACTUAL / FINAL per pàgina i apartat

**Cas d'ús:** UC-006 — Registrar devolució, saldo o compensació  
**Data:** 2026-10-03  
**Criteri:** es documenten les superfícies que realment poden disparar o confondre UC-006. No es crea una “pàgina UC-006 actual” fictícia perquè no existeix.

## 1. Inventari de superfícies

| ID | Superfície | Apartat / acció | Estat UC-006 |
| --- | --- | --- | --- |
| P-UC006-01 | `/alumnes/mostrar-alumne/` | Donar de baixa | ACTUAL LLEGAT; només disparador |
| P-UC006-02 | `/alumnes/mostrar-alumne/` | Canviar de curs | ACTUAL LLEGAT + preview SIF opcional; execució econòmica no cablejada |
| P-UC006-03 | `/alumnes/factura/` | Anul·lar factura / A TORNAR | ACTUAL LLEGAT; barreja conceptes |
| P-UC006-04 | CLI SIF | Preview/process manual refund | IMPLEMENTAT TÈCNIC, no UI productiva |
| P-UC006-05 | CLI SIF | Preview/process credit balance | IMPLEMENTAT TÈCNIC, no UI productiva |
| P-UC006-06 | CLI SIF | Preview/process credit compensation | IMPLEMENTAT TÈCNIC, no UI productiva |
| P-UC006-F | Pantalla final UC-006 | Decisió econòmica i confirmació | PENDENT |

## 2. P-UC006-01 ACTUAL — baixa des de Consulta / Modifica alumne

```mermaid
flowchart TD
  A[Operador cerca alumne] --> B[Obre inscripció]
  B --> C[Prem Donar de baixa]
  C --> D[GET mostraModalDonarBaixa.php]
  D --> E[Mostra motiu i opció correu]
  E --> F{ID i motiu vàlids?}
  F -- No --> G[Error UI]
  F -- Sí --> H[POST confirmacioBaixa_DonarBaixa.php + CSRF]
  H --> I{Sessió + same-origin + permís + guard OK?}
  I -- No --> J[403/401/409/422]
  I -- Sí --> K[confirmaBaixa_modalDonarBaixa]
  K --> L[Baixa llegada]
  L --> M[Èxit a l'operador]
  M --> N[[UC-006 econòmic NO executat aquí]]
```

### Mancança

La baixa pot crear una necessitat econòmica, però no hi ha pas explícit “què fem amb l’import?” ni enllaç transaccional a devolució/saldo.

## 3. P-UC006-01 FINAL — baixa + decisió econòmica separada

```mermaid
flowchart TD
  A[Confirmar baixa operativa] --> B[COMMIT baixa / estat origen]
  B --> C[Uc006Controller.preview]
  C --> D[Carregar factura + pagaments + titular + dret disponible]
  D --> E{Dret econòmic?}
  E -- No --> F[NO_CHANGE]
  E -- Sí --> G{Classificació}
  G --> H[REFUND pendent/confirmat]
  G --> I[CREDIT_BALANCE]
  G --> J[REVIEW]
  H --> K[No registrar REFUND fins evidència externa]
  I --> L[Crear/reutilitzar saldo idempotent]
  J --> M[Incidència/revisió]
  F --> N[Fi]
  K --> N
  L --> N
  M --> N
  D -. paral·lel .-> O[Classificar impacte fiscal UC-05/74]
```

## 4. P-UC006-02 ACTUAL — canvi de curs

```mermaid
flowchart TD
  A[Operador selecciona curs destí] --> B[JS calcula/mostra apagar pagat pendent despeses]
  B --> C[POST realitzarCanviCurs_CanviCurs.php]
  C --> D{POST sessió CSRF permís OK?}
  D -- No --> X[Error]
  D -- Sí --> E{Preflight SIF enforced?}
  E -- No --> H[Executar canvi llegat]
  E -- Sí --> F[previewCourseChange]
  F --> G{Decisions esperades coincideixen?}
  G -- No --> X
  G -- Sí --> H
  H --> I[realitzarCanviCurs_modalCanviCurs]
  I --> J[Èxit canvi]
  J --> K[[economic_decision no executada com UC-28/29/29a en aquest endpoint]]
```

### Mancança

El preview ja permet distingir impacte econòmic, però la confirmació final no materialitza la decisió amb un contracte UC-006.

## 5. P-UC006-02 FINAL — canvi de curs amb partició econòmica

```mermaid
flowchart TD
  A[Preview canvi de curs] --> B[Snapshot origen i destí]
  B --> C[Calcular diferència econòmica al servidor]
  C --> D{Resultat}
  D -- Igual --> E[NO_CHANGE econòmic]
  D -- Alumne deu més --> F[Deute/cobrament posterior UC-02]
  D -- PrisMa deu --> G[UC-006 preview dret de sortida]
  G --> H{Decisió autoritzada}
  H -- Retorn --> I[RETURN_PENDING fins evidència externa]
  H -- Saldo --> J[CREDIT_BALANCE]
  H -- Revisió --> K[REVIEW]
  A -.-> L[Decisió fiscal independent UC-05/74]
  E --> M[Confirmar canvi]
  F --> M
  I --> M
  J --> M
  K --> N[No confirmar efecte econòmic ambigu]
  L --> M
```

## 6. P-UC006-03 ACTUAL — Consulta / Anul·la factura

```mermaid
flowchart TD
  A[Operador cerca factura] --> B[Prem anul·lar]
  B --> C[GET mostrarModalAnulaFactura_Factures.php]
  C --> D{Guard de mutació llegada permet?}
  D -- No --> E[Error/bloqueig]
  D -- Sí --> F[Modal amb A TORNAR + DATA DEVOLUCIÓ + OBS]
  F --> G[Operador introdueix import i data]
  G --> H{Número i data vàlids?}
  H -- No --> I[Error UI]
  H -- Sí --> J[POST anularFactura_Factures.php]
  J --> K{Same-origin + permís + guard?}
  K -- No --> E
  K -- Sí --> L[Intranet::anularFactura]
  L --> M[Èxit: Factura anul·lada]
  M --> N[[Cap evidència que un REFUND SIF o bancari s'hagi executat]]
```

### Risc

La paraula “devolució” al camp de data i “A TORNAR” poden induir a interpretar una intenció/registre administratiu com si fos una sortida de diners confirmada.

## 7. P-UC006-03 FINAL — separar fiscal/administratiu d’econòmic

```mermaid
flowchart TD
  A[Operador selecciona factura/operació] --> B[Preview estat fiscal i econòmic]
  B --> C{Cal correcció fiscal?}
  C -- Sí --> D[UC-05/74: proposta/rectificació]
  C -- No --> E[Cap acció fiscal]
  B --> F{Hi ha dret econòmic de sortida?}
  F -- No --> G[NO_CHANGE]
  F -- Sí --> H[UC-006: decidir REFUND/CREDIT/REVIEW]
  H --> I{REFUND?}
  I -- Sí --> J[Crear RETURN_PENDING]
  J --> K{Retorn extern confirmat?}
  K -- No --> L[Conservar pendent]
  K -- Sí --> M[Registrar REFUND SIF]
  I -- No --> N[Crear/reutilitzar saldo]
  D --> O[Resultats separats però correlacionats]
  E --> O
  G --> O
  L --> O
  M --> O
  N --> O
```

## 8. P-UC006-04 ACTUAL — CLI devolució

```mermaid
flowchart TD
  A[preview-manual-refund.php] --> B{SIF_ENV production?}
  B -- Sí --> C[Refús]
  B -- No --> D[Construir payload sense mutar]
  D --> E[Revisió tècnica]
  E --> F[process-manual-refund.php]
  F --> G{SIF_ENV production?}
  G -- Sí --> C
  G -- No --> H[ManualRefundService]
  H --> I[PaymentService]
  I --> J[REFUND + allocation]
```

**Cobertura:** útil per prova/preproducció; no substitueix actor/autorització/evidència externa/UI.

## 9. P-UC006-05 ACTUAL — CLI crear saldo

```mermaid
flowchart TD
  A[preview-credit-balance.php] --> B{production?}
  B -- Sí --> X[Refús]
  B -- No --> C[Builder valida titular import origen]
  C --> D[Payload dry-run]
  D --> E[process-credit-balance.php]
  E --> F{production?}
  F -- Sí --> X
  F -- No --> G[CreditBalanceService::createCredit]
  G --> H[INSERT credit_balance ACTIVE]
```

**Mancança:** no hi ha guard idempotent d’origen acreditat.

## 10. P-UC006-06 ACTUAL — CLI aplicar saldo

```mermaid
flowchart TD
  A[preview-credit-compensation.php] --> B[Carregar saldo i factura]
  B --> C[Mostrar payload COMPENSATION]
  C --> D[process-credit-compensation.php]
  D --> E[Lock saldo + factura]
  E --> F{Saldo ACTIVE?}
  F -- No --> X[422]
  F -- Sí --> G{Import <= saldo i <= deute?}
  G -- No --> X
  G -- Sí --> H{K existent?}
  H -- Sí --> I[REUSED]
  H -- No --> J[INSERT COMPENSATION]
  J --> K[Consumir credit_balance]
  K --> L[ACTIVE o USED]
```

**Mancança:** no s’acredita titularitat compatible.

## 11. P-UC006-F FINAL — pantalla unificada

### Apartat A · Context

Ha de mostrar, només en lectura:
- origen (baixa/canvi/operació);
- inscripció/linia;
- factura i receptor;
- pagador/titular;
- cobraments reals;
- devolucions ja registrades;
- saldos ja creats/consumits;
- dret encara disponible;
- estat fiscal separat.

### Apartat B · Decisió

Opcions:
- cap moviment;
- retorn monetari;
- saldo a favor;
- revisió.

“Compensació” només apareix en aplicar un saldo existent a una factura/deute compatible.

### Apartat C · Evidència de retorn

Només per devolució:
- canal;
- identificador extern estable;
- data real;
- import;
- pagador/receptor;
- estat `PENDING | CONFIRMED | FAILED`.

### Apartat D · Confirmació

Mostra fingerprint/snapshot, motiu, actor i efectes. Si l’estat ha canviat des del preview, força nou preview.

### Apartat E · Resultat

Mostra per separat:
- decisió econòmica;
- UUID payment/credit si existeix;
- estat de cobrament;
- decisió fiscal / UUID rectificativa si correspon;
- sync llegat;
- incidència si alguna fase posterior falla.

## 12. Activitat FINAL global

```mermaid
flowchart TD
  A[Obrir UC-006 des de baixa/canvi/factura] --> B[Autoritzar actor]
  B --> C[Carregar snapshot autoritatiu]
  C --> D[Calcular dret disponible i titular]
  D --> E{Ambigüitat?}
  E -- Sí --> R[REVIEW + incidència]
  E -- No --> F[Preview decisió]
  F --> G{NO_CHANGE / REFUND / CREDIT}
  G -- NO_CHANGE --> Z[Registrar decisió/auditoria]
  G -- CREDIT --> H[Lock dret i buscar saldo d'origen]
  H --> I{Ja existeix?}
  I -- Sí --> J[Reutilitzar]
  I -- No --> K[Crear credit_balance]
  J --> Z
  K --> Z
  G -- REFUND --> L[Autoritzar retorn]
  L --> M{Evidència externa confirmada?}
  M -- No --> N[RETURN_PENDING]
  M -- Sí --> O[Deduplicar per operació externa]
  O --> P[Registrar REFUND]
  N --> Z
  P --> Z
  Z --> Q[Sync llegat post-COMMIT]
  Q --> S[Mostrar resultat]
  C -.-> T[Classificar fiscalitat UC-05/74 per separat]
  T --> S
```

## 13. Criteris d’acceptació per superfície

- **P-UC006-01:** una baixa no pot afirmar devolució ni saldo sense UC-006.
- **P-UC006-02:** una diferència positiva/negativa de canvi ha de derivar explícitament al cas econòmic corresponent.
- **P-UC006-03:** “A TORNAR” no pot persistir-se com REFUND confirmat sense evidència.
- **P-UC006-04/05/06:** eines CLI continuen sent no productives; han de servir per verificació controlada.
- **P-UC006-F:** cap import/titular autoritatiu depèn només del navegador; tota confirmació rellegeix i bloqueja l’estat rellevant.
