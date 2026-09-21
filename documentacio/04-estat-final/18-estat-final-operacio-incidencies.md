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
- crea relacions `fact_rels`;
- pot crear `payment_transaction` i `payment_allocation` si factura i cobrament neixen en el mateix flux;
- retorna numero i UUID.

Si la factura ja existeix per la mateixa `IDEMPOTENCY_KEY`, retorna la factura existent i no crea cap numero fiscal nou.

## 3. Registre de pagament

`registerPayment()`:

- valida metode, import, data, referencia i permisos;
- comprova idempotencia de pagament;
- localitza factura o factures existents;
- crea `payment_transaction`;
- crea `payment_allocation`;
- recalcula `ESTAT_COBRAMENT`;
- registra event/auditoria;
- sincronitza camps historics nomes com a resum operatiu quan cal.

No crea numero fiscal, no modifica hash chain, no crea registre fiscal i no modifica linies, totals ni receptor fiscal de cap factura emesa. Si el cobrament no te factura previa i crea obligacio fiscal, el flux correcte es `issueInvoice()` amb bloc de pagament.

## 4. Fluxos fiscals especials

### 4.1. Pagaments fraccionats

Cada fraccio es registra com un moviment economic propi. La factura conserva el total emes i el SIF calcula l'estat de cobrament amb `payment_allocation`.

Estats finals possibles:

- `PENDENT`;
- `PARCIAL`;
- `COBRADA`;
- `RETORNADA_PARCIAL`;
- `RETORNADA_TOTAL`;
- `COMPENSADA_PARCIAL`;
- `COMPENSADA_TOTAL`.

Una notificacio Redsys repetida no pot crear nova factura ni duplicar cobrament: el sistema retorna el moviment ja registrat per la mateixa idempotencia.

### 4.2. Compensacio i saldo

Una compensacio es registra com moviment economic `COMPENSATION`. Si prove d'un saldo, ha d'estar vinculada a `credit_balance`.

Regles:

- saldo nou per baixa/devolucio: crea credit i rectificativa si redueix una factura emesa;
- us futur del saldo: `payment_transaction` + `payment_allocation`;
- descompte abans d'emetre: es congela a `factura_linia`;
- ajust posterior sobre factura emesa: rectificativa o factura complementaria.

### 4.3. Devolucions

Una devolucio es registra com `REFUND` i s'assigna a la factura o linia afectada. Si redueix una factura emesa, s'ha de crear rectificativa vinculada.

La devolucio pot venir de Redsys, transferencia o registre manual. El sistema ha de conservar metode, data, referencia, import, usuari i relacio amb baixa, canvi de curs o incidencia si aplica.

### 4.4. Rectificatives

Les rectificatives usen serie `R`, tenen relacio directa amb la factura rectificada i indiquen motiu i mode:

- `DIFERENCIES`;
- `SUBSTITUCIO`.

La pantalla antiga d'anulacio no pot modificar factures emeses ni assumir que tot cas es una rectificativa: ha d'iniciar un decisor fiscal amb permisos i previsualitzacio.

### 4.4.1. Anul·lacio de registre AEAT

`RegistroAnulacion` s'utilitza quan un registre de facturacio es improcedent i la causa no s'ha de resoldre amb factura rectificativa.

Regles:

- no esborra factura ni registre original;
- crea registre fiscal immutable d'anul·lacio;
- participa en l'encadenament/huella;
- entra a `fiscal_queue`;
- conserva resposta i estat AEAT;
- si despres cal factura correcta, s'emet una nova alta diferenciada;
- suporta, quan pertoqui, anul·lacio normal, per rebuig i sense registre previ segons operativa AEAT vigent.

### 4.4.2. Subsanacio

La subsanacio corregeix dades d'un registre amb el mateix identificador de factura nomes quan la causa no exigeix factura rectificativa.

Pot derivar de:

- registre acceptat amb error admissible;
- registre rebutjat;
- dada incorrecta detectada posteriorment.

El SIF ha de guardar el registre anterior, el nou registre de subsanacio, indicadors AEAT, huella, intent, resposta i estat. Un error economic o fiscal que exigeixi rectificativa no pot entrar per aquest flux.

### 4.5. Baixes i canvis de curs

Una baixa marca la inscripcio i espera decisio economica del client: retorn, saldo o no retorn. No genera rectificativa automatica.

Un canvi de curs conserva historic. Si el canvi afecta servei, import, descompte, despeses de gestio o concepte d'una factura emesa, el SIF ha de crear rectificativa, complementaria o diferencia pendent.

### 4.6. Factura manual i migracio historica

La factura manual es un flux `issueInvoice()` iniciat per usuari autoritzat. No es permet alta manual a `web.factures`.

Les factures historiques migrades es consulten com `NO_VERIFACTU` i no entren a hash chain ni cua AEAT retroactivament. Una rectificativa nova sobre historic, si cal, es crea com operacio SIF nova amb referencia clara a la factura antiga.

### 4.7. Morositat i reclamacions

Una reclamacio no modifica ni rectifica automaticament la factura. Si la factura reclamada continua vigent i arriba un cobrament, el flux operatiu registra `registerPayment()` contra la factura existent amb assignacio `CLAIM_PAYMENT`.

Aquest cobrament no crea factura nova, no crea registre fiscal, no modifica hash chain i nomes recalcula `ESTAT_COBRAMENT`. Les plantilles i URLs de reclamacio han de dirigir-se a una URL controlada de `pay.prisma.cat` o a una accio interna que acabi en aquest mateix contracte.

## 5. PDF/QR

La generacio de PDF/QR pot anar en cua.

Si falla:

- no es desfà factura;
- es crea incidencia;
- es notifica al panell SIF;
- es pot mostrar avís a la intranet.

## 6. Enviament AEAT

La cua AEAT:

- envia registres;
- reintenta errors temporals;
- marca acceptats/rebutjats;
- genera notificacions si falla.

## 7. Notificacions

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
- compensacio aplicada sense credit o motiu;
- saldo antic pendent de revisio;
- reclamacio cobrada sense referencia o sense usuari intern identificat;
- canvi de curs amb diferencia no cobrada;
- factura historica amb relacio incompleta;
- factura manual pendent de document o correu.

## 8. Gestio d'incidencies SIF

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
- `ID_PROVA`
- `ID_EVIDENCIA`
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
- `CLOSURE_CRITERIA`

La incidencia ha de poder apuntar a factura, pagament o operacio d'origen. Aixo es important en casos com pagaments fraccionats, transferencies que paguen diverses factures, saldos/compensacions i factures abans de cobrament.

També ha de poder apuntar a:

- baixa;
- canvi de curs;
- credit/saldo;
- devolucio;
- rectificativa;
- factura historica migrada;
- factura manual.

Estats recomanats:

- `OPEN`
- `ACKNOWLEDGED`
- `IN_PROGRESS`
- `RESOLVED`
- `DISMISSED`

Cada canvi d'estat ha de generar un event/log.

### 8.1. Incidencies originades per proves

Quan una prova del paquet go/no-go dona `FAIL` o `BLOCKED`, s'ha d'obrir o vincular una incidencia SIF.

Relacio minima:

| Camp | Criteri |
| --- | --- |
| `ID_PROVA` | ID estable de la prova, per exemple `SIF-RED-002`. |
| `ID_EVIDENCIA` | Captura, log, export, acta o consulta que demostra el problema. |
| `ORIGEN` | `PROVA`, `PREPRODUCCIO`, `PRODUCCIO`, `AEAT`, `REDSYS`, `PDF_QR`, `BACKUP`, `PERMISOS` o equivalent. |
| `PRIORITAT` | `CRITICA`, `ALTA`, `MITJANA` o `BAIXA`, segons efecte go/no-go. |
| `CLOSURE_CRITERIA` | Que cal demostrar per tancar-la. |

Regla:

```text
Una incidencia oberta per prova no es tanca nomes perque s'ha canviat codi.
Es tanca quan la prova es repeteix, passa i conserva nova evidencia.
```

### 8.2. Evidencia necessaria per tancar incidencia

Per tancar una incidencia cal conservar:

- descripcio de la causa;
- accio correctora aplicada;
- versio o paquet on queda corregida;
- prova repetida o verificacio equivalent;
- evidencia nova;
- usuari/responsable que valida el tancament;
- data de tancament.

Si la incidencia queda `DISMISSED`, cal explicar per que no afecta compliment fiscal, numeracio, hash chain, AEAT, PDF/QR, permisos ni conservacio de dades.

## 9. Acces d'auditoria / AEAT

El sistema ha de permetre consultar la informacio amb transcendencia tributaria separada d'informacio confidencial no fiscal.

Recomanacio:

- crear rol `AUDITOR_FISCAL` o `AEAT_READONLY`;
- nomes lectura;
- acces temporal o activable quan calgui;
- sense dades no fiscals innecessaries;
- amb registre d'accessos;
- sense permis per crear, editar, anul·lar o rectificar.

## 10. Versions

Primera versio signable:

```text
1.0.0
```

Cada canvi rellevant haura de quedar documentat.
