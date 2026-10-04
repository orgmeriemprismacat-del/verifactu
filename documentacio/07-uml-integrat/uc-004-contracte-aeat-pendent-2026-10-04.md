# UC-004 · Contracte AEAT server-side i configuració pendent · 2026-10-04

**Cas:** UC-004 — Emetre factura abans de cobrar  
**Objectiu:** documentar el contracte fiscal implementat sense acceptar dades fiscals autoritatives del navegador.

## 1. Situació actual

El P0 tècnic detectat durant l'auditoria s'ha reduït.

A la branca del PR #166 existeix ara `InvoiceBeforePaymentAeatInputPolicy`, connectada al flux autoritatiu UC-004 abans del fingerprint.

La política:

- no accepta `aeat_fields` ni `aeat_header` del caller;
- només activa el snapshot oficial en `PREPROD/PREPRODUCTION/PROD/PRODUCTION`;
- obté emissor i identitat del SIF de configuració de servidor;
- exigeix mapping fiscal UC-004 explícit;
- afegeix causa d'exempció al snapshot intern de capçalera i línies;
- construeix `DescripcionOperacion`;
- construeix `Desglose/DetalleDesglose`;
- construeix `SistemaInformatico`;
- deixa que `RegistrationSnapshot` congeli identitat de factura, totals, destinatari, cadena, timestamp i huella.

El navegador continua sense ser autoritat fiscal.

## 2. Frontera server-side

```text
selecció/receptor/imports autoritatius
  → InvoiceBeforePaymentServerPayloadAssembler
  → InvoiceBeforePaymentAeatInputPolicy
      → emissor configurat
      → perfil fiscal configurat
      → aeat_header
      → aeat_fields
      → exemption_reason intern
  → InvoiceBeforePaymentPayloadBuilder
  → fingerprint preview
  → confirm + mateixa reconstrucció
  → InvoiceService
  → InvoiceRepository
  → RegistrationSnapshot
  → factura_registres + fiscal_queue
```

Això és important perquè `aeat_fields` participa en el fingerprint/idempotència: un canvi de configuració fiscal entre preview i confirm força conflicte i obliga a generar un preview nou.

## 3. Configuració fiscal requerida

No s'ha codificat cap causa d'exempció legal per defecte.

En entorns qualificats el servidor ha de definir explícitament:

- `SIF_UC004_AEAT_TAX_CODE`;
- `SIF_UC004_AEAT_REGIME_KEY`;
- `SIF_UC004_AEAT_EXEMPTION_REASON`.

També han d'estar configurats:

- `SIF_ISSUER_NIF`;
- `SIF_ISSUER_NAME`;
- `SIF_AEAT_SYSTEM_NAME`;
- `SIF_AEAT_SYSTEM_ID`;
- `SIF_AEAT_SYSTEM_VERSION`;
- `SIF_AEAT_INSTALLATION_ID`.

Si falta qualsevol dada obligatòria, el flux falla tancat.

## 4. Decisió fiscal que continua pendent

El codi **no decideix** si PrisMa ha d'utilitzar `E1`, `E2` o una altra causa d'exempció, ni quina `ClaveRegimen` correspon.

Aquesta decisió ha de quedar validada fiscalment i després configurada al servidor.

Per tant:

- **builder tècnic:** IMPLEMENTAT;
- **frontera server-owned:** IMPLEMENTADA;
- **mapping fiscal legal concret:** PENDENT DE VALIDACIÓ/CONFIGURACIÓ;
- **evidència PREPROD/AEAT:** PENDENT.

## 5. Proves versionades

`InvoiceBeforePaymentAeatSnapshotTest` cobreix:

1. construcció server-side del snapshot;
2. persistència de la causa d'exempció a totals i línies;
3. construcció de `Desglose`;
4. construcció de `SistemaInformatico`;
5. generació de `RegistroAlta`;
6. validació contra els XSD AEAT locals a través de `RegistrationSnapshot/XmlCodec`;
7. fail-closed sense mapping fiscal;
8. entorn de desenvolupament sense invenció d'`aeat_fields`;
9. rebuig de camps AEAT injectats.

L'endpoint també té una comprovació estàtica que exigeix el wiring de la política i les tres claus de configuració fiscal.

## 6. Criteri per considerar el P0 AEAT tancat operativament

Encara falta:

1. validar amb criteri fiscal els valors reals de `tax_code`, `regime_key` i `exemption_reason`;
2. configurar-los a `sif_test`/PREPROD;
3. configurar identitat SIF real no placeholder;
4. executar la suite i CI del HEAD;
5. executar emissió UC-004 en PREPROD;
6. verificar el `factura_registres.PAYLOAD_JSON` congelat;
7. verificar que `fiscal_queue.PAYLOAD_JSON` usa el mateix snapshot;
8. validar remissió/resposta AEAT sense regenerar el registre.

## 7. Veredicte

El bloqueig anterior «UC-004 no construeix `aeat_fields`» ja **no és correcte** per al HEAD actual del PR #166.

L'estat correcte és:

- **IMPLEMENTAT:** construcció server-side i fail-closed;
- **VERIFICACIÓ AUTOMATITZADA:** versionada, pendent del CI del HEAD;
- **PENDENT OPERATIU:** mapping fiscal validat + configuració + PREPROD/AEAT.

Aquesta separació evita convertir una decisió fiscal en un valor accidental dins PHP.
