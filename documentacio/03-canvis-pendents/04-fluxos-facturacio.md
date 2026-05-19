# 04 - Fluxos de facturacio

> Document especific pendent de desenvolupar. Recollira els fluxos funcionals i fiscals del SIF.

## Fluxos a documentar

- Curs normal Redsys.
- Pack.
- Grup.
- Regal.
- USOC.
- Factura abans de cobrament.
- Transferencia validada a intranet.
- Compensacio.
- Devolucio.
- Rectificativa.
- Canvi de curs.
- Baixa.
- Morositat i reclamacions.
- Proformes o documents previs no fiscals.

Nota de cobertura:

```text
Els fluxos de curs normal Redsys, pack i regal ja estan explicats funcionalment.
El que falta no es coneixement del cas, sino deixar-los tancats amb payload SIF,
taules definitives, idempotencia exacta, correus i proves.
```

## Regles generals ja definides

```text
Els canals proposen factures.
El SIF emet factures.
```

Una factura real:

- te serie i numero fiscal;
- genera registre fiscal;
- entra a hash chain;
- genera PDF/QR immutable;
- s'envia o queda en cua AEAT.

Un pagament no sempre crea factura:

- si ja hi ha factura emesa pendent de cobrament, el pagament es registra contra aquella factura;
- si no hi ha factura, el pagament pot generar factura;
- si es compensacio, es registra com moviment economic;
- si es devolucio, normalment s'associa a rectificativa.

Matissos operatius recuperats del xat antic:

- un mateix `IDPAG` pot tenir diversos intents Redsys amb `Ds_Order` diferents, especialment amb pagaments fraccionats o intents denegats i posteriorment acceptats;
- si un intent Redsys es denega i despres s'accepta, pot conservar el mateix `IDPAG`;
- una transferencia pot pagar diverses factures ja emeses;
- una factura pot acabar cobrada amb diversos pagaments;
- una compensacio pot ser saldo a favor o descompte, pero fiscalment s'ha de tipificar en el SIF;
- si una persona paga de mes, es pregunta si vol devolucio o deixar saldo per una altra inscripcio.

## Curs normal Redsys

Estat actual:

- Redsys confirma pagament a `realitzaPagamentAutomatic.php`;
- es busca inscripcio per `IDPAG`;
- es calcula factura i s'insereix a `web.factures`;
- s'actualitza `inscripcions.PAGAMENT`, `DATA PAG`, `FACTURA_RELACIONADA` i `FRACCIO`;
- s'envien correus.

Dades actuals conegudes:

- abans de pagar ja existeix una fila a `web.inscripcions`;
- `IDPAG` identifica el pagament/enllac de pagament;
- el mateix `IDPAG` pot tenir diversos intents Redsys;
- Redsys retorna `Ds_Order`, que actualment es guarda a `web.factures.NUM_COMANDA`;
- `A_PAGAR` es el total esperat;
- `PAGAMENT` es el pagat real acumulat;
- `DATA PAG` s'omple quan Redsys confirma o quan es valida transferencia des de intranet;
- `FACTURA_RELACIONADA` apunta a la familia/agrupacio de factura;
- nom del curs i convocatoria surten de `ANY`, `MES`, `CURS`, `Grup` i taula `curs`;
- `CONCEPTE1` exemple: `Curs Gestio Emocional en Moments de Perdua i Dol`;
- `CONCEPTE2` exemple: `Convocatoria juliol 2025`;
- IVA: 0%, amb mencio d'exempcio a la factura.

Canvi necessari:

```text
validar signatura Redsys
detectar DS_ORDER duplicat
carregar inscripcio per IDPAG
detectar si ja hi ha factura previa real
si existeix factura -> registerPayment()
si no existeix -> issueInvoice()
actualitzar relacions i pagament
enviar correus
```

Idempotencia orientativa:

```text
REDSYS|CURS|IDPAG:{IDPAG}|ORDER:{DS_ORDER}
```

Pendent de tancar:

- payload JSON definitiu cap al SIF;
- resposta esperada del SIF;
- tractament concret si Redsys repeteix notificacio;
- prova de concurrencia;
- correu final amb PDF o enllac segur.

## Pack

Regla futura:

```text
1 pagament -> 1 factura -> N linies
```

Normalment:

- 2 cursos;
- 2 inscripcions;
- mateix `IDPAG`;
- una linia per curs;
- descompte pack aplicat al segon curs.

Estat actual conegut:

- el pack sol incloure 2 cursos;
- es crea una inscripcio per cada curs;
- el camp que relaciona les inscripcions del pack es `IDPAG`;
- si hi ha un sol pagament, actualment pot acabar en una sola factura;
- si es fracciona o es gestiona manualment, pot haver-hi mes d'una factura;
- el preu del pack surt de la taula de preus relacionada amb la taula de packs;
- el descompte del 25% s'aplica sempre al segon curs.

Estat final:

```text
1 pagament de pack
    -> 1 factura
    -> 2 linies de factura
    -> descompte aplicat a la linia del segon curs
```

Idempotencia orientativa:

```text
REDSYS|PACK|IDPAG:{IDPAG}|ORDER:{DS_ORDER}
```

Pendent de tancar:

- SQL/estructura exacta de taules de pack;
- camps de preu base i descompte per linia;
- exemple complet de factura_linia per pack;
- correu associat.

## Grup

Regla futura:

```text
1 pagament -> 1 factura -> N linies
```

Una linia per participant.

Motiu:

- necessitat de justificacio per Tripartita/FUNDAE;
- traçabilitat per participant.

La factura de grup pot estar a nom d'una escola/empresa o d'un responsable particular.

## Regal

Regla:

- factura al comprador;
- destinatari no genera factura quan bescanvia el codi;
- la inscripcio posterior es vincula al regal/factura original.

Estat actual conegut:

- el comprador entra a l'apartat de regalar un curs;
- escull curs o tipus de curs;
- pot posar dedicatoria;
- posa les seves dades de facturacio;
- paga;
- rep un codi per bescanviar;
- el destinatari omple les seves dades mes endavant quan bescanvia el codi;
- la factura va al comprador, no al beneficiari.

Estat final:

```text
compra regal
    -> factura al comprador
    -> codi regal
    -> bescanvi posterior
    -> inscripcio del destinatari sense factura nova
```

Idempotencia orientativa:

```text
REDSYS|REGAL|IDPAG:{IDPAG}|ORDER:{DS_ORDER}
```

Pendent de tancar:

- SQL/estructura exacta de taules de regals;
- relacio entre codi regal, comprador, destinatari i factura;
- visibilitat de factura a intranet alumne;
- correu de compra regal i correu de bescanvi.

## USOC

Regla:

- l'alumne paga la seva part i rep factura per aquesta part;
- USOC paga la diferencia i rep factura per la diferencia.

## Factura abans de cobrament

Regla:

```text
Si porta A2026/x es factura real.
Si es document modificable, ha de ser proforma/pressupost sense numero fiscal.
```

Quan la factura abans de cobrament ja existeix:

```text
el pagament posterior no crea factura nova
el pagament posterior es registra amb registerPayment()
```

Context actual recuperat:

- historicament `E_FACT` podia confondre's amb factura electronica i factura abans de pagar; queda separat;
- quan es genera una factura abans de cobrar, normalment es coneixen import i participants, pero poden canviar per anulacio de curs o per afegir participant;
- encara que l'empresa no pagui, la factura abans de cobrar es una factura real si s'ha emes perque la necessiten per pagar;
- aquesta factura es comptabilitza/exporta com una factura cobrada, encara que economicament quedi pendent;
- el pagament posterior no pot crear una segona factura.

## Proformes i documents previs

PrisMa no treballara amb proformes fiscals separades dins del SIF.

Criteri:

- si un document porta numero fiscal o es tracta com a factura, es factura real i entra al SIF;
- si es un pressupost o document editable abans d'emetre, no porta serie fiscal, no entra al SIF i no es comptabilitza com a factura;
- les antigues "proformes" que en realitat es feien servir com a factura s'han de reclassificar documentalment com a factura real o eliminar com a concepte fiscal ambigu.

## Canvi de curs

Regla:

- si no hi ha factura emesa, es pot actualitzar inscripcio amb historial;
- si hi ha factura emesa i canvia concepte/import, cal accio fiscal;
- si el nou curs es mes car, queda diferencia pendent;
- si el nou curs es mes barat, hi pot haver retorn o saldo;
- si hi ha despeses de gestio, han de quedar documentades;
- si hi ha descompte excepcional, no s'ha de tocar nomes `A_PAGAR`, cal motiu intern.

Matisos del funcionament actual:

- el sistema calcula automaticament el nou `A_PAGAR` tenint en compte el descompte anterior si aplica;
- si el descompte original no aplica al nou curs, el sistema ho detecta automaticament;
- el primer canvi pot ser gratuit i despres poden aplicar-se despeses de gestio segons el cas;
- les despeses de gestio actualment estan incloses dins l'import final, no com a linia separada;
- si hi ha part pagada i el nou curs es mes barat, el retorn el fa Adam manualment i despres es genera la rectificativa quan pertoqui;
- si el curs canvia pero l'import es igual, igualment cal rectificativa si la factura ja no descriu el servei real.

## Baixa

Regla:

- baixa = event sobre inscripcio;
- devolucio/saldo = event economic posterior;
- rectificativa = nomes quan la decisio economica ho exigeix.

Funcionament actual recuperat:

- la baixa marca la inscripcio, pero no toca necessariament el pagament o la factura en aquell moment;
- si hi ha retorn de diners, primer es fa o confirma el retorn i despres es genera la factura negativa/rectificativa des de `Consulta - Edita - Anula factura`;
- les devolucions poden fer-se per Redsys, transferencia o manualment;
- una devolucio parcial pot afectar una linia concreta quan el cas ho permeti.

### Baixa amb saldo a favor

Criteri funcional recomanat:

```text
si el curs/servei original queda totalment o parcialment sense efecte
i el client decideix deixar els diners com a saldo
llavors el saldo neix en aquell moment
i s'ha de valorar rectificativa de la factura original en aquell moment
```

Flux:

```text
baixa confirmada
decisio client = saldo
crear credit_balance
generar rectificativa si el servei facturat original queda reduit/anul·lat
en factura futura: usar saldo com COMPENSATION
```

Si no hi ha devolucio ni saldo i la factura original continua corresponent a un servei prestat o import no retornable, la factura pot quedar igual.

## Morositat

Regla:

- morositat no es baixa;
- factura continua existint;
- no hi ha rectificativa nomes pel fet de reclamar;
- cal documentar reclamacions.

## Payloads SIF per cas

Aquest apartat fixa el criteri de payload. No substitueix el codi final, pero evita que cada canal inventi la factura de manera diferent.

### Estructura comuna

Tots els casos que creen factura han d'enviar:

```json
{
  "idempotency_key": "...",
  "tipus_serie": "A",
  "source_channel": "REDSYS | INTRANET | TRANSFERENCIA | MANUAL",
  "source_type": "CURS | PACK | GRUP | REGAL | USOC | MANUAL | FACTURA_ABANS_COBRAR | RECTIFICATIVA",
  "source_id": 123,
  "emesa_abans_cobrament": false,
  "e_fact": false,
  "billing": {
    "nom_rao": "...",
    "nif_cif": "...",
    "adreca": "...",
    "cp": "...",
    "poblacio": "...",
    "pais": "ES",
    "email": "..."
  },
  "lines": [],
  "payment": null,
  "references": {}
}
```

Regles:

- `billing` es snapshot fiscal; no es recalcula mes endavant des de dades vives.
- `lines` sempre inclou imports base, descompte, exempcio IVA i total final.
- `payment` nomes s'inclou si el pagament i la factura neixen al mateix flux.
- si la factura ja existia, no s'envia `issueInvoice()`: s'envia `registerPayment()`.

### Curs normal

Idempotencia:

```text
REDSYS|CURS|IDPAG:{IDPAG}|ORDER:{DS_ORDER}
```

Linies:

```text
1 linia = 1 curs / taller / jornada
SOURCE_TYPE = INSCRIPCIO
SOURCE_ID = inscripcions.ID
```

La linia ha de conservar:

- `concepte`: `Curs ...` o titol de jornada/taller;
- `detall`: `Convocatoria ...`;
- `preu_unitari`;
- descompte congelat, si existeix;
- `iva_regim = EXEMPT`;
- mencio d'exempcio IVA en PDF.

### Pack

Idempotencia:

```text
REDSYS|PACK|IDPAG:{IDPAG}|ORDER:{DS_ORDER}
```

Regla:

```text
1 pagament de pack -> 1 factura -> una linia per curs
```

Normalment:

- linia 1: primer curs, sense descompte pack;
- linia 2: segon curs, amb `DESC_ORIGEN = PACK` i descompte del 25%;
- cada linia apunta a la seva `inscripcions.ID`;
- el preu del pack surt de les taules de pack/preu existents, pendent d'incorporar al document quan es passi el SQL.

### Grup de persones

Idempotencia:

```text
REDSYS|GRUP|IDPAG:{IDPAG}|ORDER:{DS_ORDER}
```

Regla:

```text
1 pagament de grup -> 1 factura -> una linia per participant
```

La factura va al receptor fiscal:

- escola/empresa, si el grup el paga una entitat;
- responsable particular, si el grup no es d'empresa.

La linia ha d'identificar el participant prou per justificacio interna. El nom del participant pot sortir a la factura. El DNI nomes s'ha de mostrar si es necessari per justificacio; si no, es pot conservar internament vinculat a `SOURCE_ID = inscripcions.ID`.

### Regal

Idempotencia:

```text
REDSYS|REGAL|IDPAG:{IDPAG}|ORDER:{DS_ORDER}
```

Regla:

```text
compra regal -> factura al comprador -> codi regal -> bescanvi posterior sense factura nova
```

La factura:

- receptor: comprador;
- `SOURCE_TYPE = REGAL`;
- `SOURCE_ID = ID_REGAL`;
- concepte visible: `Regal curs ...` o `Regal curs de X hores`;
- destinatari: no es receptor de factura en el moment de compra.

Quan el destinatari bescanvia el codi, es crea o completa inscripcio, pero no es crea una factura nova.

### USOC

Regla:

```text
alumne paga la seva part -> factura a l'alumne
USOC paga diferencia -> factura a USOC
```

Factura alumne:

- receptor: alumne;
- import final: import pagat per l'alumne;
- linia amb preu base i descompte visible generic;
- motiu intern: `TIPUS_DESC = 4 / USOC`.

Factura USOC:

- receptor: USOC;
- import: diferencia assumida per USOC;
- linia vinculada al curs/alumne i convocatoria;
- relacio interna amb la factura de l'alumne i la inscripcio.

### Factura manual

Regla:

```text
intranet -> issueInvoice()
```

Origen:

- dades fiscals manuals; o
- entitat seleccionada de `entitats` / `entitats_resp`.

Sempre ha d'incloure:

- receptor fiscal;
- linies estructurades;
- import base/descompte/total;
- responsable intern que l'emet;
- correu o enllac segur al receptor quan correspongui.

### Factura abans de cobrar

Regla:

```text
factura real abans de cobrament -> issueInvoice() amb EMESA_ABANS_COBRAMENT = 1
pagament posterior -> registerPayment()
```

No s'ha de fer servir `E_FACT` per indicar que es abans de cobrar.

`E_FACT` nomes indica factura electronica. Per tant, la intranet ha de tenir una accio separada per marcar una factura com electronica.

Si s'afegeixen o treuen participants despres d'emetre factura real, cal rectificativa o factura complementaria segons el cas fiscal.

### Rectificativa

Regla:

```text
canvi posterior sobre factura emesa -> nova factura R o rectificativa per substitucio/diferencies
```

El payload ha d'indicar:

- `tipus_serie = R`;
- factura original rectificada;
- motiu: `DADES_FISCALS`, `DEVOLUCIO`, `CANVI_CURS`, `BAIXA`, `AJUST_IMPORT`, etc.;
- mode: substitucio o diferencies;
- linies rectificades;
- relacio a `factura_rectificacio`.

Casos que generen rectificativa:

- canvi de dades fiscals en factura ja emesa;
- canvi de curs amb canvi de concepte;
- devolucio parcial o total;
- baixa amb saldo/devolucio quan el servei facturat queda reduit o anul·lat;
- modificacio d'import/descompte sobre factura ja emesa.

Estat actual i canvi necessari:

- fins ara les rectificatives s'han tractat sobretot com a factures negatives;
- amb VERI*FACTU cal suportar rectificativa per substitucio o per diferencies segons el cas;
- el vincle historic `FACTURA_RELACIONADA` agrupa factures relacionades, pero no substitueix la relacio directa entre factura rectificativa i factura rectificada;
- el tipus concret de rectificativa per canvis de dades fiscals s'ha de validar amb criteri fiscal abans de produccio.

## Fluxos del panell SIF

Els fluxos següents no creen necessàriament factures, pero formen part del funcionament operatiu del SIF.

### Dashboard SIF

Flux:

```text
usuari entra a pay.prisma.cat/sif
SIF calcula resum operatiu
mostra indicadors de factures, AEAT, PDF/QR i incidencies
usuari obre el detall que calgui
```

No modifica dades fiscals.

### Consulta de factures SIF

Flux:

```text
usuari cerca factura
SIF mostra factura, linies, pagaments, documents i estat AEAT
si cal rectificar -> usuari autoritzat inicia flux de rectificativa
si cal descarregar -> SIF serveix PDF immutable
```

Regla:

```text
consulta no edita factura emesa
```

### Registres AEAT

Flux:

```text
usuari filtra registres
SIF mostra hash, hash anterior, fiscal order i estat AEAT
si hi ha error temporal -> es pot reintentar segons permisos
si hi ha error no resolt -> es crea incidencia
```

### Incidencies SIF

Flux:

```text
SIF detecta error o incidencia
crea incidencia fiscal
intranet pot mostrar indicador/resum
usuari autoritzat entra a pay.prisma.cat/sif/incidencies
assigna responsable
afegeix notes o accions
resol incidencia
SIF registra log de resolucio
```

Regla:

```text
la resolucio oficial de la incidencia es fa al SIF
```

### Documents SIF

Flux:

```text
factura emesa
worker genera PDF/QR/XML si correspon
SIF guarda document i hash
usuari consulta o descarrega document
si falla document -> incidencia SIF
```

Regla:

```text
document fiscal generat no es modifica silenciosament
```

### Versions SIF

Flux:

```text
es prepara nova versio
es documenten canvis
es passen proves
es vincula declaracio responsable si cal
usuari autoritzat marca versio activa
SIF conserva historial
```

### Exportacions SIF

Flux:

```text
usuari tria periode i tipus d'export
SIF genera fitxer
calcula hash
guarda registre d'exportacio
permet descarrega
```

### Configuracio SIF

Flux:

```text
usuari autoritzat entra a configuracio
modifica parametre tecnic
SIF valida permisos
SIF guarda canvi
SIF registra log
```

Regla:

```text
cap canvi de configuracio sense log
```

### Apartat VERI*FACTU de la intranet

Flux:

```text
usuari entra a intranet / VERI*FACTU
intranet consulta resum al SIF
mostra indicador visual de pendents
mostra resum d'incidencies
ofereix boto obrir panell SIF
ofereix acces a documents SIF i exportacions
```

Regla:

```text
la intranet resumeix i enllaça
el SIF conserva i gestiona
```
