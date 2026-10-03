# UC-005 · Cas d'ús ACTUAL / FINAL — Rectificar factura

## ACTUAL — branca d'auditoria

```mermaid
flowchart LR
  OP[Operador intranet] --> UI[/alumnes/factura/]
  UI --> Q[Consulta factura]
  UI --> E[Editar dades llegades]
  UI --> A[Anul·lar factura llegada]
  Q --> SIFQ[Consulta SIF read-only]
  E --> GUARD[SifLegacyInvoiceMutationGuard]
  A --> GUARD
  GUARD --> LEGACY[Intranet.php + BD llegada]

  API[POST intern /api/factures/rectify.php] --> HMAC[InternalApiAuthenticator + replay guard]
  HMAC --> ROLE[InternalRectificationScopeResolver]
  ROLE --> CMD[RectificationCommandService]
  CMD --> U74[Guard decisió UC-74]
  U74 --> PRE[Preview + fingerprint]
  U74 --> CONF[Confirm + revalidació]
  CONF --> RS[ManualRectificationService]
  RS --> TX[InvoiceService transaction]
  TX --> DB[(SIF)]
  TX --> AUD[sif_audit_event + operational_event]

  UI -. adaptador encara pendent .-> API
```

L'ACTUAL de la branca ja disposa d'un **backend UC-005 específic** i fail-closed, però la pantalla intranet encara no el consumeix. La consulta SIF de `/alumnes/factura/` continua read-only; les mutacions llegades protegides no equivalen al command UC-005.

## FINAL

```mermaid
flowchart LR
  OP[Operador autoritzat] --> UI[Factura SIF]
  UI --> PX[Proxy intranet<br/>sessió + permís + CSRF]
  PX --> API[POST signat UC-005]
  API --> AUTH[HMAC + replay + role scope]
  AUTH --> CLASS[UC-74 classificador fiscal executable]
  CLASS -->|RECTIFICATION| PRE[Preview + fingerprint]
  CLASS -->|ANUL·LACIÓ REGISTRE| UC30[UC-30]
  CLASS -->|SUBSANACIÓ| UC31[UC-31]
  CLASS -->|NO CHANGE| NONE[Cap mutació fiscal]
  PRE --> CONF[Confirmació usuari]
  CONF --> CMD[RectificationCommandService]
  CMD --> RS[ManualRectificationService]
  RS --> IS[InvoiceService]
  IS --> DB[(SIF)]
  DB --> LINK[factura R + factura_rectificacio + original RECTIFIED]
  LINK --> AUD[sif_audit_event + operational_event]
  LINK --> DOC[PDF/QR/XML]
  LINK --> OUT[Resposta tipificada + consulta posterior]
```

## Regles

- Una rectificativa no equival a una devolució monetària.
- Una baixa o canvi de curs pot provocar UC-005, però també UC-28/29/71/72 segons la decisió econòmica.
- El FINAL no permet editar in-place una factura emesa.
- La figura fiscal s'ha de decidir abans d'emetre la sèrie R; el guard backend ja ho exigeix, però el classificador UC-74 general encara s'ha d'implementar.
- El backend intern no substitueix la protecció web: el proxy intranet FINAL ha de validar sessió, permís i CSRF abans de signar la petició SIF.
- Un reintent equivalent ha de reutilitzar la mateixa R i quedar auditat com `REUSED`.
