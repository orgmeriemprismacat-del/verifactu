# 18 - Estat final d'operacio i incidencies

> Document d'estat final sobre operacio diaria, errors, notificacions, reintents i manteniment.

## 1. Objectiu

Descriure com funcionara el sistema en produccio:

- creacio de factures;
- generacio PDF/QR;
- enviament AEAT;
- reintents;
- notificacions;
- gestio d'incidencies;
- manteniment i versions.

## 2. Creacio de factura

`issueInvoice()`:

- valida payload;
- comprova idempotencia;
- crea factura;
- crea linies;
- crea registre fiscal;
- actualitza hash chain;
- crea cua AEAT;
- retorna numero i UUID.

## 3. PDF/QR

La generacio de PDF/QR pot anar en cua.

Si falla:

- no es desfà factura;
- es crea incidencia;
- es notifica al panell SIF;
- es pot mostrar avís a la intranet.

## 4. Enviament AEAT

La cua AEAT:

- envia registres;
- reintenta errors temporals;
- marca acceptats/rebutjats;
- genera notificacions si falla.

## 5. Notificacions

Tipus:

- error AEAT;
- retries fallits;
- PDF fallit;
- pagament sense factura;
- factura sense pagament;
- factura abans de cobrament pendent de pagament;
- devolucio feta sense rectificativa o rectificativa pendent;
- pagament fraccionat amb intents Redsys repetits;
- duplicat Redsys;
- incidencia de dades.

## 6. Gestio d'incidencies SIF

Les incidencies del SIF s'han de guardar a la BD fiscal/SIF.

La gestio oficial es fara al panell intern del SIF a `pay.prisma.cat/sif/incidencies`.

La intranet pot mostrar-les com a notificacions o avisos, pero no hauria de ser la font de veritat. La taula de notificacions de la intranet serveix per avisar; la incidencia fiscal ha de quedar registrada al SIF.

Ubicacio oficial:

```text
pay.prisma.cat/sif/incidencies
```

Ubicacio secundaria de consulta/avis:

```text
Intranet / VERI*FACTU / Incidencies
```

Camps minims recomanats:

- `ID`
- `UUID_FACTURA`
- `TIPUS_INCIDENCIA`
- `PRIORITAT`
- `ESTAT`
- `ORIGEN`
- `DETAILS`
- `LAST_ERROR`
- `ASSIGNED_TO`
- `CREATED_AT`
- `UPDATED_AT`
- `RESOLVED_AT`
- `RESOLUTION_NOTES`

La incidencia ha de poder apuntar a factura, pagament o operacio d'origen. Aixo es important en casos com pagaments fraccionats, transferencies que paguen diverses factures, saldos/compensacions i factures abans de cobrament.

Estats recomanats:

- `OPEN`
- `ACKNOWLEDGED`
- `IN_PROGRESS`
- `RESOLVED`
- `DISMISSED`

Cada canvi d'estat ha de generar un event/log.

## 7. Acces d'auditoria / AEAT

El sistema ha de permetre consultar la informacio amb transcendencia tributaria separada d'informacio confidencial no fiscal.

Recomanacio:

- crear rol `AUDITOR_FISCAL` o `AEAT_READONLY`;
- nomes lectura;
- acces temporal o activable quan calgui;
- sense dades no fiscals innecessaries;
- amb registre d'accessos;
- sense permis per crear, editar, anul·lar o rectificar.

## 8. Versions

Primera versio signable:

```text
1.0.0
```

Cada canvi rellevant haura de quedar documentat.
