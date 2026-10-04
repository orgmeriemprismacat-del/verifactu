# UC-005 · Contracte de consum de decisió UC-74

**Data:** 2026-10-04  
**Estat:** contracte consumidor implementat; productor/classificador UC-74 pendent.

## 1. Objectiu

UC-005 no decideix si una correcció fiscal és rectificativa, subsanació o anul·lació, ni pot escollir R1–R5 des del navegador. Només executa una rectificativa quan existeix una decisió UC-74 persistent, immutable i vinculada a la correcció concreta.

## 2. Event requerit

La decisió s'ha de persistir a `sif_audit_event` amb:

- `ACTION = FISCAL_CORRECTION_CLASSIFIED`;
- `RESULT = SUCCEEDED`;
- `RESOURCE_TYPE = FACTURA`;
- `RESOURCE_ID = UUID_FACTURA` de l'original;
- `REASON_CODE` coherent amb la classificació;
- `CHANGESET_JSON.classification`;
- `CHANGESET_JSON.correction_fingerprint`;
- `CHANGESET_JSON.correction` normalitzat quan la decisió s'ha de consumir des de la UI UC-005.

L'endpoint UC-005 rep només `classification_event_uuid`; no accepta una classificació fiscal inline com a font d'autoritat.

## 3. Payload immutable de classification

`CHANGESET_JSON.classification` ha de contenir com a mínim:

```json
{
  "decision": "RECTIFICATION",
  "source_uc": "UC-74",
  "reason_code": "AMOUNT_DECREASE",
  "policy_version": "2026-10",
  "invoice_type": "R1",
  "rectification_mode": "DIFERENCIES"
}
```

Regles:

- `decision` ha de ser `RECTIFICATION`;
- `source_uc` ha de ser `UC-74`;
- `invoice_type` ha de ser `R1|R2|R3|R4|R5`;
- `rectification_mode` ha de ser `DIFERENCIES|SUBSTITUCIO`;
- el mode de la petició ha de coincidir amb el classificat;
- el `REASON_CODE` de l'event ha de coincidir amb `classification.reason_code`.

## 4. Vinculació a la correcció exacta

`correction_fingerprint` és el SHA-256 canònic calculat per `RectificationDecisionFingerprint` sobre la correcció que UC-74 ha classificat.

S'exclouen només camps que són server-owned o ignorats pel command UC-005:

- `created_by`, `user`, `usuari`;
- `aeat_header`, `aeat_fields`;
- `type`, `tipus_factura`.

La resta de la correcció —import, motiu, mode, fiscalitat, receptor, concepte, detall i referència quan existeixen— queda vinculada a la decisió. Si canvia, `FiscalCorrectionDecisionResolver` retorna conflicte i exigeix una decisió UC-74 nova.

Per al consumidor intranet, `CHANGESET_JSON.correction` conserva el mateix snapshot normalitzat que va originar `correction_fingerprint`. `InvoiceQueryService` només el projecta a usuaris amb vista FULL; la projecció MINIMAL no exposa ni la decisió ni la correcció. La UI no recalcula imports ni permet editar R1–R5: envia exactament aquest snapshot al preview/confirm.

## 5. Frontera de confiança

El flux autoritzat és:

```text
cas real
  ↓
UC-74 classifica
  ↓
sif_audit_event immutable
  ├─ classification
  ├─ correction_fingerprint
  └─ correction [snapshot executable per UI]
  ↓
classification_event_uuid
  ↓
UC-005 resolver
  ↓
preview
  ↓
fingerprint UC-005
  ↓
confirm
  ↓
revalidació factura + snapshot AEAT sota transacció
  ↓
emissió R
```

Per tant:

- el navegador no decideix R1–R5;
- el navegador no construeix `aeat_fields`;
- una decisió UC-74 no es pot reutilitzar amb un import o receptor diferent;
- la UI només és executable si la decisió projectada porta `correction` i `ready_for_uc005_ui=true`;
- un canvi posterior al preview obliga a repetir preview/decisió quan correspongui;
- un retry idempotent de la mateixa correcció pot reutilitzar la mateixa evidència.

## 6. Pendent del UC-74 productor

Aquest document defineix el contracte que UC-005 ja consumeix. Encara falta implementar el component que:

1. recull els fets de negoci/fiscals;
2. aplica la política aprovada;
3. determina RECTIFICATION/SUBSANATION/CANCELLATION;
4. determina R1–R5 i S/I quan és rectificativa;
5. calcula `RectificationDecisionFingerprint`;
6. persisteix l'event `FISCAL_CORRECTION_CLASSIFIED`;
7. retorna el seu UUID al canal intern autoritzat.

Fins que aquest productor no existeixi, la UI UC-005 no ha d'oferir cap selector manual de tipus fiscal.
