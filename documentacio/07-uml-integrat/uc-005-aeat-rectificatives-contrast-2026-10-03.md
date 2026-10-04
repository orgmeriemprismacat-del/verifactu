# UC-005 · Contrast AEAT de factures rectificatives

**Data de contrast:** 2026-10-03  
**Àmbit:** VERI*FACTU / registre de facturació d'alta de factures rectificatives  
**Estat:** contrast oficial completat; mapper AEAT UC-005 simple implementat en branca, casos complexos i evidència CI/preproducció pendents

## 1. Fonts oficials consultades

1. AEAT · Informació tècnica SIF / VERI*FACTU  
   https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/informacion-tecnica.html
2. AEAT · XSD `SuministroInformacion.xsd` publicat al portal de desenvolupadors  
   https://prewww2.aeat.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV1.0/cont/ws/SuministroInformacion.xsd
3. AEAT · Documento de validaciones y errores VERI*FACTU, versió 1.2.2  
   https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/VERI-FACTU/Validaciones_Errores_Veri-Factu.pdf

## 2. Contracte AEAT confirmat

Per a `TipoFactura = R1|R2|R3|R4|R5`:

- `TipoRectificativa` és obligatori.
- `TipoRectificativa = S` identifica rectificativa **sustitutiva**.
- `TipoRectificativa = I` identifica rectificativa **incremental / per diferències**.
- `FacturasRectificadas` només és admissible per R1-R5 i permet identificar la factura original amb:
  - `IDEmisorFactura`
  - `NumSerieFactura`
  - `FechaExpedicionFactura`
- `ImporteRectificacion`:
  - només s'ha d'incloure quan `TipoRectificativa = S`;
  - és obligatori quan `TipoRectificativa = S`;
  - conté `BaseRectificada`, `CuotaRectificada` i, si aplica, `CuotaRecargoRectificado`.
- El registre d'alta continua exigint `Desglose`, `CuotaTotal`, `ImporteTotal`, encadenament, informació del SIF, timestamp i huella.

## 3. Mapeig UC-005 → AEAT que sí és determinista

| UC-005 | AEAT |
|---|---|
| `mode=DIFERENCIES` | `TipoRectificativa=I` |
| `mode=SUBSTITUCIO` | `TipoRectificativa=S` |
| factura original congelada | `FacturasRectificadas.IDFacturaRectificada` |
| SUBSTITUCIO | requereix `ImporteRectificacion` amb imports substituïts originals |
| R1..R5 | `TipoRectificativa` obligatori |

## 4. Què NO es pot inferir només de `amount`

UC-005 no pot fabricar de forma segura un `Desglose` AEAT a partir d'un únic import quan:

- l'original té més d'un tipus d'IVA;
- hi ha diversos règims;
- hi ha inversió del subjecte passiu;
- hi ha recàrrec d'equivalència;
- hi ha operacions exemptes/no subjectes amb causes diferents;
- el tipus de rectificativa R1/R2/R3/R4/R5 requereix criteri fiscal específic.

Per tant, el sistema ha de fallar tancat si no disposa d'un snapshot fiscal prou complet.

## 5. Estat del codi de la branca

### Ja disponible

- `RecordFactory::ALTA` admet:
  - `TipoRectificativa`
  - `FacturasRectificadas`
  - `FacturasSustituidas`
  - `ImporteRectificacion`
- `RecordFactory::checkAlta()` exigeix `TipoRectificativa S|I` quan `TipoFactura` comença per `R`.
- `RegistrationSnapshot` incorpora qualsevol `aeat_fields` validat al snapshot congelat.
- `ManualRectificationPayloadBuilder` ja impedeix interpretar `amount` com base+total en factures subjectes a IVA sense bloc fiscal explícit.

### Implementat en la branca UC-005

- `AeatRectificationMapper` recupera `factura_registres.PAYLOAD_JSON.aeat` i valida que la identitat AEAT correspongui a la factura i a l'emissor SIF configurat;
- `TipoRectificativa` es deriva de `DIFERENCIES → I` i `SUBSTITUCIO → S`;
- `FacturasRectificadas` usa la identitat congelada del snapshot original;
- `ImporteRectificacion` de `SUBSTITUCIO` deriva base i quota originals;
- R1/R2/R3/R4/R5 és obligatori a la decisió UC-74 i el `type` enviat pel caller s'ignora;
- `SistemaInformatico` es reconstrueix amb configuració server-side, no amb dades del request;
- per un únic `DetalleDesglose`, el mapper conserva el perfil AEAT original i aplica la nova base/quota;
- al `confirm` es torna a llegir el snapshot fiscal sota lock i es recalcula el fingerprint, de manera que un canvi posterior al preview invalida la confirmació;
- el snapshot final continua passant per `RecordFactory` i `XmlCodec`, que validen les regles i XSD suportades.

### Encara pendent

- múltiples `DetalleDesglose` o diversos tipus/règims dins la mateixa factura;
- recàrrec d'equivalència;
- canvi de tipus impositiu o de perfil fiscal respecte l'original;
- ISP, no-subjecció i altres variants que necessiten regles fiscals específiques;
- productor/classificador UC-74 genèric amb criteris aprovats;
- conclusió CI de les noves proves de mapping i revalidació;
- prova d'enviament en entorn extern/preproducció i conservació d'evidència.

## 6. Decisió tècnica

**No activar l'emissió AEAT rectificativa de UC-005 només perquè el payload local SIF sigui correcte.** El mapper simple està implementat, però l'activació productiva continua condicionada als perfils fiscals suportats, CI/preproducció i configuració/certificat AEAT.

El backend local pot preparar i validar una rectificació, però l'alta VERI*FACTU no s'ha de considerar preparada fins que el mapper AEAT tingui:

1. tipus fiscal R1-R5 classificat;
2. `TipoRectificativa` S/I;
3. identitat congelada de la factura rectificada;
4. `ImporteRectificacion` quan sigui S;
5. desglose complet;
6. validació XSD i proves oficials.
