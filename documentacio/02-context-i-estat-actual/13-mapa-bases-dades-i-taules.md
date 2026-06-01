# 13 - Mapa de bases de dades i taules

> Document de suport per explicar a una persona externa quines bases de dades existeixen, quines taules son actuals, quines son noves i com es relacionen.

## 1. Bases de dades

El sistema actual esta fet amb PHP i JavaScript sobre MySQL i diverses bases de dades. La separacio principal a efectes documentals es:

- BD web/ecommerce: dades operatives de compra, inscripcions, pagaments i factures historiques;
- BD intranet: gestio interna, usuaris, rols, entitats, responsables, apartats i processos administratius;
- BD fiscal/SIF: nova base de dades fiscal per VERI*FACTU, que ha de ser la font de veritat fiscal quan entri en funcionament.

### BD web/ecommerce

Contingut:

- inscripcions;
- cursos;
- factures historiques;
- descomptes;
- codis promocionals;
- regals;
- TPV virtuals i identificadors de pagament;
- dades operatives de la web.

### BD intranet

Contingut:

- usuaris;
- rols;
- apartats;
- funcionalitats;
- entitats;
- responsables;
- parametres;
- notificacions;
- canvis de curs;
- baixes;
- reclamacions.

### BD dades fiscals / SIF

Estat:

```text
Hi ha una BD de dades fiscals parcial/experimental creada durant el projecte.
La implantacio final com a SIF central encara s'ha de consolidar.
```

Contingut:

- factures noves VERI*FACTU;
- linies;
- registres fiscals;
- hash chain;
- AEAT queue;
- PDFs;
- pagaments fiscals;
- rectificatives;
- relacions fiscals amb origen.

Notes recuperades del xat antic:

- inicialment hi havia taules fiscals com `errors_verifactu`, `factura`, `factura_log`, `factura_registres`, `reg_pagament` i `session_log`;
- algunes definicions inicials feien servir `ENGINE=MyISAM`, `UUID varchar(12)` i imports `double`;
- posteriorment es va indicar que les taules s'havien passat a `InnoDB` i que ja s'havia afegit idempotencia;
- el model final ha de considerar aquestes taules com a punt de partida o transicio, no com a esquema fiscal definitiu.

## 2. Taules actuals conegudes

### Intranet

- `apartats`
- `codis_errors_intranet`
- `entitats`
- `entitats_resp`
- `funcionalitats`
- `params`
- `rols`
- `usuaris`

### Intranet, taules noves creades o previstes per VERI*FACTU

- `motiu_canvi`

### Web/ecommerce

- `inscripcions`
- `factures`
- `curs`
- `descomptes`
- `descomptes_grup`
- taula de codis promocionals, pendent de documentar
- taula de regals, pendent de documentar

Camps actuals especialment rellevants detectats al xat antic:

- `inscripcions.IDPAG`: identificador de pagament i de l'enllac de pagament;
- `inscripcions.FACTURA_RELACIONADA`: vincle historic amb la factura o agrupacio de factura;
- `inscripcions.A_PAGAR`: total esperat;
- `inscripcions.PAGAMENT`: import real pagat o acumulat;
- `inscripcions.DATA PAG`: data de pagament quan Redsys confirma o quan es valida transferencia des de la intranet;
- `factures.NUM_COMANDA`: comanda Redsys / `Ds_Order`;
- camps de descompte com `TIPUS_DESCOMPTE` i `VALID_DESC`, quan apliquen.

## 3. Taules noves proposades

### BD fiscal nova / SIF

- `factura`
- `factura_linia`
- `factura_registres`
- `factura_rectificacio`
- `factura_documents`
- `fiscal_sequence`
- `fiscal_chain_state`
- `fiscal_queue`
- `payment_transaction`
- `payment_allocation`
- `fact_rels`
- `redsys_notifications`
- `errors_verifactu`, si es reutilitza com a taula d'incidencies SIF

### BD intranet

- `notificacions`
- `canvi_curs`
- `baixa_inscripcio`
- `reclamacio_pagament`
- `credit_balance`, preferiblement a BD fiscal si s'utilitza per compensar factures futures

## 3.1. Deutes de migracio detectats

La BD fiscal parcial no s'ha de prendre literalment com a disseny final.

Cal revisar i migrar:

- `UUID varchar(12)` cap a UUID complet `CHAR(36)` o format equivalent validat;
- imports `double` cap a `DECIMAL(12,2)`;
- taules que hagin estat `MyISAM` cap a `InnoDB`;
- `reg_pagament` cap a `payment_transaction` i `payment_allocation`, conservant-lo com a historic si cal;
- `factura_log` i `session_log` cap a un registre d'events auditable o com a historic;
- `errors_verifactu` cap a taula d'incidencies SIF o mantenir-la amb camps ampliats i valors tipificats.

## 4. Relacions principals

### Inscripcio a factura

```text
web.inscripcions.FACTURA_RELACIONADA
        |
        v
dades_fiscals.fact_rels
        |
        v
dades_fiscals.factura.UUID_FACTURA
```

Nota:

```text
FACTURA_RELACIONADA es mantindra com a identificador d'agrupacio/compatibilitat.
fact_rels relacionara aquest identificador amb les factures fiscals reals per UUID_FACTURA.
```

### Factura a linies

```text
factura.UUID_FACTURA
        |
        v
factura_linia.UUID_FACTURA
```

### Factura a pagament

```text
payment_transaction.UUID_PAYMENT
        |
        v
payment_allocation.UUID_PAYMENT
payment_allocation.UUID_FACTURA
        |
        v
factura.UUID_FACTURA
```

### Factura a document

```text
factura.UUID_FACTURA
        |
        v
factura_documents.UUID_FACTURA
```

### Rectificativa

```text
factura rectificativa UUID
        |
        v
factura_rectificacio
        |
        v
factura original UUID
```

## 5. Decisions pendents

- Si `canvi_curs` i `baixa_inscripcio` han de tenir copia/resum a BD fiscal.
- Si hi ha foreign keys entre BDs o relacio logica per UUID/ID.
- Com migrar `web.factures` historica.
- Com conservar compatibilitat amb `FACTURA_RELACIONADA`.

## 6. Decisions preses

- `fact_rels` es crea de nou per substituir la connexio antiga de factures relacionades.
- `E_FACT` es mantindra per factura electronica, si cal.
- Es creara `EMESA_ABANS_COBRAMENT` per distingir factura real emesa abans de cobrar.
- Generar factura abans de pagar implica `EMESA_ABANS_COBRAMENT = 1`, pero no implica automaticament `E_FACT = 1`.
- Ha d'existir una accio/opcio separada per marcar una factura com a factura electronica (`E_FACT = 1`) quan correspongui.
- No s'utilitzen proformes fiscals: si un document porta numero fiscal, sera factura real.
- Les factures antigues de `web.factures` es migraran al SIF com a historic no VERI*FACTU.
- La relacio nova entre inscripcions i factures sera per `FACTURA_RELACIONADA` + `UUID_FACTURA` dins `fact_rels`.
- Tots els imports nous del SIF han de ser `DECIMAL(12,2)` o equivalent, no `float` ni `double`.
