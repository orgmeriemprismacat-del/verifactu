# UC-004 · Contracte AEAT pendent i frontera de responsabilitats · 2026-10-04

**Cas:** UC-004 — Emetre factura abans de cobrar  
**Objectiu:** definir què falta per construir el snapshot oficial AEAT sense acceptar dades fiscals autoritatives del navegador.

## 1. Situació actual

`InvoiceService::issueInvoice()` falla tancat en PREPROD/PROD si el payload no conté `aeat_fields`.

El builder UC-004 actual reconstrueix al servidor:

- receptor;
- línies;
- imports;
- total;
- règim intern `EXEMPT`;
- relacions d'origen;
- clau idempotent.

Però no construeix encara:

- `aeat_fields`;
- `aeat_header` complet per UC-004.

Conseqüència: LOCAL/DEV/TEST pot provar el graf fiscal intern; PREPROD/PROD no pot emetre UC-004 mentre falti el contracte oficial.

## 2. Infraestructura AEAT que ja existeix

`InvoiceRepository` ja delega a `Aeat\RegistrationSnapshot`.

Quan hi ha `aeat_fields`, aquest component:

- incorpora la identitat de factura;
- incorpora número/fecha d'expedició;
- incorpora emissor;
- incorpora tipus de factura;
- incorpora quota i import total;
- incorpora destinatari;
- encadena amb el registre anterior;
- congela el `RegistroAlta` que queda dins `factura_registres.PAYLOAD_JSON`.

La cua AEAT reutilitza aquest mateix payload congelat.

Per tant, UC-004 **no ha de construir manualment el hash/cadena ni el registre final**.

## 3. Política comuna existent

L'endpoint genèric d'emissió usa `InternalInvoiceIssuePayloadPolicy`.

Aquesta política ja resol responsabilitats que UC-004 també necessita:

- actor autenticat;
- `request_id`;
- `correlation_id`;
- rol;
- emissor configurat al servidor;
- `aeat_header.ObligadoEmision`;
- `SistemaInformatico` configurat al servidor;
- fail-closed quan falta identitat completa en entorn qualificat.

No es pot invocar directament per UC-004 perquè, correctament, rebutja el bypass d'operacions `emesa_abans_cobrament`.

**Conclusió arquitectònica:** convé extreure/reutilitzar la part comuna d'identitat AEAT en lloc de duplicar-la dins l'endpoint UC-004.

## 4. Frontera que encara no es pot inventar

El payload intern marca avui l'operació com `iva_regim=EXEMPT`, però això **no és suficient** per generar automàticament el detall oficial AEAT.

Cal una política fiscal autoritzada que determini, com a mínim, per aquesta operació:

- impost aplicable;
- clau de règim;
- classificació de l'operació;
- si és exempta, el codi concret d'exempció;
- base/importe no subjecte;
- descripció de l'operació.

Aquesta classificació ha de venir d'una regla fiscal versionada i server-side.

No s'ha d'escollir per defecte un codi d'exempció només perquè l'IVA intern sigui 0%.

## 5. Contracte objectiu

La construcció recomanada és:

```text
selecció/receptor/imports autoritatius
  → InvoiceBeforePaymentServerPayloadAssembler
  → perfil fiscal UC-004 versionat
  → AeatInvoiceFieldsBuilder compartit/server-side
      → aeat_fields
      → aeat_header
  → InvoiceBeforePaymentPayloadBuilder
  → InvoiceService
  → InvoiceRepository
  → RegistrationSnapshot
  → factura_registres + fiscal_queue
```

El navegador no pot enviar ni sobreescriure:

- `aeat_fields`;
- `aeat_header.ObligadoEmision`;
- `SistemaInformatico`;
- codi d'exempció;
- clau de règim.

## 6. Configuració mínima server-side

Abans de PREPROD/PROD s'ha de poder resoldre:

### Identitat SIF

- NIF emissor;
- raó social emissora;
- nom del sistema;
- ID del sistema;
- versió;
- número d'instal·lació.

Aquesta informació ja té suport a la configuració compartida.

### Perfil fiscal UC-004

Ha de ser explícit, versionat i sense defaults fiscals silenciosos.

Exemple de contracte conceptual, **no valors proposats**:

```text
profile_code
profile_version
tax_code
regime_code
operation_classification
exemption_code [si aplica]
operation_description
```

Si el perfil no està configurat o és incompatible amb la factura, PREPROD/PROD ha de continuar fallant tancat.

## 7. Proves necessàries

1. entorn qualificat sense perfil fiscal → 422/fail-closed;
2. emissor placeholder → error;
3. identitat SIF incompleta → error;
4. client intenta injectar `aeat_fields` → ignorat/rebutjat;
5. perfil fiscal server-side vàlid → snapshot oficial congelat;
6. retry idempotent → mateix UUID, número i mateix snapshot;
7. canvi de perfil amb mateixa clau → conflicte de payload;
8. `fiscal_queue.PAYLOAD_JSON` = registre fiscal congelat;
9. document posterior deriva del mateix snapshot;
10. no barrejar cadena interna prototip i cadena oficial en una mateixa BD.

## 8. Decisió de l'auditoria

**No s'implementa cap codi fiscal inventant `OperacionExenta` o `ClaveRegimen`.**

El bloqueig P0 es divideix així:

- **P0-A · infraestructura:** reutilitzar/extractar identitat AEAT server-side compartida per UC-004;
- **P0-B · regla fiscal:** definir i versionar el perfil fiscal autoritzat del cas abans de construir el `Desglose` oficial;
- **P0-C · proves:** verificar snapshot/cadena/cua en `sif_test` i PREPROD.

Aquesta separació evita convertir una decisió fiscal en un detall accidental de PHP.
