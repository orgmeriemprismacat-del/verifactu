# UC-005 · Cas d'ús ACTUAL / FINAL — Rectificar factura

## ACTUAL

```mermaid
flowchart LR
  OP[Operador intranet] --> UI[/alumnes/factura/]
  UI --> Q[Consulta factura]
  UI --> E[Editar dades llegades]
  UI --> A[Anul·lar factura llegada]
  Q --> SIFQ[Consulta SIF read-only si feature activa]
  E --> GUARD[SifLegacyInvoiceMutationGuard]
  A --> GUARD
  GUARD --> LEGACY[Intranet.php + BD llegada]
```

El flux de consulta SIF és principalment read-only. Els botons de mutació continuen actuant sobre el llegat quan el guard ho permet; no invoquen `ManualRectificationService`.

## FINAL

```mermaid
flowchart LR
  OP[Operador autoritzat] --> UI[Factura SIF]
  UI --> C[Command UC-005]
  C --> AUTH[Auth + CSRF + scope]
  AUTH --> CLASS[UC-74 classificador fiscal]
  CLASS -->|RECTIFICATIVA| PRE[Preview snapshot abans/després]
  CLASS -->|ANUL·LACIÓ REGISTRE| UC30[UC-30]
  CLASS -->|SUBSANACIÓ| UC31[UC-31]
  PRE --> CONF[Confirmació]
  CONF --> RS[ManualRectificationService]
  RS --> IS[InvoiceService]
  IS --> DB[(SIF)]
  DB --> LINK[factura_rectificacio + estat original]
  LINK --> DOC[PDF/QR/XML]
  LINK --> OUT[Resposta tipificada + auditoria]
```

## Regles

- Una rectificativa no equival a una devolució monetària.
- Una baixa o canvi de curs pot provocar UC-005, però també UC-28/29/71/72 segons la decisió econòmica.
- El FINAL no permet editar in-place una factura emesa.
- La figura fiscal s'ha de decidir abans d'emetre la sèrie R.
