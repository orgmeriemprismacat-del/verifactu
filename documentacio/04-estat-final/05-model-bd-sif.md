# 05 - Model de base de dades del SIF

> Document especific del model de dades. Recollira l'esquema SQL, relacions, indexos, permisos, criteris d'immutabilitat i relacio entre BD fiscal, BD intranet i BD web/ecommerce.

## 1. Objectiu

Aquest document descriu:

- quines bases de dades existeixen;
- quines taules ja existeixen;
- quines taules noves s'han de crear;
- quines dades viuen a cada BD;
- com es relacionen les dades fiscals amb inscripcions, pagaments, entitats i usuaris;
- quines taules han de ser immutables o restringides.

## 2. Bases de dades identificades

### 2.1. BD web/ecommerce

Actualment conte taules operatives de la web i de facturacio historica.

Taules rellevants conegudes:

- `inscripcions`
- `factures`
- `curs`
- `descomptes`
- `descomptes_grup`
- taules de codis promocionals, pendent de documentar
- taules de regals, pendent de documentar

Canvi previst:

```text
La BD web/ecommerce deixara de ser el lloc on es decideix la factura fiscal nova.
Podra conservar historic i dades operatives.
El SIF central sera qui emeti les factures noves.
```

### 2.2. BD intranet

Conte gestio interna, usuaris, entitats, responsables, apartats i funcionalitats.

Taules rellevants conegudes:

- `apartats`
- `codis_errors_intranet`
- `entitats`
- `entitats_resp`
- `funcionalitats`
- `params`
- `rols`
- `usuaris`

Taules noves o a revisar en BD intranet:

- `notificacions`
- `canvi_curs`
- `baixa_inscripcio`
- `reclamacio_pagament`
- `motiu_canvi`
- relacions operatives amb factures, si es decideix no ubicar-les a BD fiscal

### 2.3. BD dades fiscals / SIF

BD nova o central per al sistema fiscal.

Ha de contenir:

- factura fiscal immutable;
- linies de factura;
- registres fiscals;
- hash chain;
- sequencies;
- cua AEAT;
- documents PDF/XML/QR;
- pagaments fiscals;
- assignacio de pagaments;
- rectificatives;
- relacio fiscal amb origen de negoci.

## 3. Criteri de separacio entre BDs

Regla general:

```text
BD intranet / web = dades operatives vives
BD fiscal / SIF = dades fiscals emeses, immutable o controlades
```

Regla d'imports:

```text
Tots els imports nous del SIF han de ser DECIMAL(12,2) o equivalent.
No s'han d'utilitzar FLOAT ni DOUBLE per imports fiscals.
```

Exemples:

| Dada | BD recomanada | Motiu |
| --- | --- | --- |
| Dades personals alumne vives | Web/Intranet | Poden canviar i no han de modificar factures emeses. |
| Snapshot fiscal d'una factura | BD fiscal | Ha de quedar congelat. |
| Inscripcio | Web/Intranet | Es dada academica/operativa. |
| Linia de factura | BD fiscal | Forma part del document fiscal. |
| Canvi de curs | Intranet, amb referencia fiscal si afecta factura | Event operatiu; pot generar accio fiscal. |
| Baixa | Intranet, amb referencia fiscal si afecta factura | Event operatiu; pot generar devolucio/saldo. |
| Payment fiscal | BD fiscal | Afecta cobrament de factures. |
| Reclamacio/morositat | Intranet | Proces operatiu de cobrament. |
| PDF factura | BD fiscal + fitxer protegit | Document fiscal immutable. |
| Notificacio interna | Intranet | Avis operatiu. |

## 4. Relacio entre BDs

Les BDs es relacionen per identificadors estables, no per modificar dades fiscals.

Relacions principals:

```text
web.inscripcions.FACTURA_RELACIONADA
        |
        v
dades_fiscals.fact_rels.FACTURA_RELACIONADA
dades_fiscals.fact_rels.UUID_FACTURA
        |
        v
dades_fiscals.factura.UUID_FACTURA
```

Nota:

```text
FACTURA_RELACIONADA es mantindra com a identificador d'agrupacio i compatibilitat.
fact_rels relacionara aquesta agrupacio amb una o diverses factures fiscals per UUID.
```

Per packs:

```text
mateix IDPAG
varies inscripcions
una factura
varies factura_linia
```

Per grups:

```text
varies inscripcions
una factura receptor empresa/responsable
una linia per participant
```

Per regal:

```text
taula regal / ID_REGAL
        |
        v
factura al comprador
        |
        v
inscripcio posterior del destinatari sense nova factura
```

## Taules principals

- `factura`
- `factura_linia`
- `factura_registres`
- `fiscal_sequence`
- `fiscal_chain_state`
- `fiscal_queue`
- `factura_documents`
- `factura_rectificacio`
- `payment_transaction`
- `payment_allocation`
- `fact_rels`
- `credit_balance`

### 4.1. Responsabilitat de cada taula nova o adaptada

| Taula | BD | Responsabilitat |
| --- | --- | --- |
| `factura` | SIF / dades fiscals | Capcalera fiscal immutable: numero, receptor, imports totals, estats, origen i snapshot fiscal. |
| `factura_linia` | SIF / dades fiscals | Detall fiscal congelat: cursos, participants, packs, descomptes, codis promocionals, IVA i total de cada linia. |
| `factura_registres` | SIF / dades fiscals | Registre fiscal VERI*FACTU: ordre fiscal, hash, hash anterior, payload, XML i estat d'enviament. |
| `factura_rectificacio` | SIF / dades fiscals | Relacio directa entre factura rectificativa i factura rectificada, motiu i mode de rectificacio. |
| `factura_documents` | SIF / dades fiscals | Control de PDF/XML/QR generats, ruta protegida, hash del fitxer i estat d'enviament per correu/enllac. |
| `fiscal_sequence` | SIF / dades fiscals | Control transaccional de numeracio per serie i any: A/R + any + ultim numero. |
| `fiscal_chain_state` | SIF / dades fiscals | Estat global de la cadena hash: ultim ordre fiscal i ultim hash. |
| `fiscal_queue` | SIF / dades fiscals | Cua d'enviament AEAT/retries, intents, errors i bloqueig de processament. |
| `payment_transaction` | SIF / dades fiscals | Moviments economics confirmats: cobrament, devolucio, compensacio o saldo aplicat. |
| `payment_allocation` | SIF / dades fiscals | Assignacio d'un moviment economic a una o diverses factures. |
| `fact_rels` | SIF / dades fiscals | Enllac entre `FACTURA_RELACIONADA`, origen operatiu, IDPAG, Ds_Order i UUID de factura. |
| `redsys_notifications` | SIF o web/pay segons implantacio | Dedupe de callbacks Redsys per `DS_ORDER`, estat i import signat. |
| `credit_balance` | Preferentment SIF si s'aplica a factures | Saldo a favor utilitzable com compensacio posterior i traçable fiscalment. |
| `notificacions` | Intranet | Avisos visibles a la intranet quan fallen retries, AEAT o processos SIF. |
| `motiu_canvi` | Intranet | Taula nova VERI*FACTU per tipificar motius de canvi, especialment canvis de curs. |
| `canvi_curs` | Intranet | Historic operatiu de canvis: curs antic/nou, imports, descomptes, despeses, motiu i usuari. |
| `baixa_inscripcio` | Intranet | Historic de baixes i decisio posterior: retorn, saldo, no retorn o pendent de decisio. |
| `reclamacio_pagament` | Intranet | Historic de reclamacions i morositat, sense modificar la factura emesa. |

Les taules de packs, regals i codis promocionals ja existeixen o existeixen parcialment a la web/ecommerce. La seva logica es mantindra operativa, pero el SIF nomes rebra la foto fiscal congelada: preu base, descompte aplicat, motiu visible, motiu intern i total final.

## 5. Taules actuals que cal documentar amb detall

### 5.1. `web.inscripcions`

Camps rellevants coneguts:

- `ID`
- `ANY`
- `MES`
- `CURS`
- `Grup`
- `NOM`
- `COGNOMS`
- `DNI`
- `CORREU`
- `ADRECA`
- `Codi_Postal`
- `Poblacio`
- `A_PAGAR`
- `PAGAMENT`
- `DATA PAG`
- `IDPAG`
- `FACTURA_RELACIONADA`
- `ENTITAT`
- `INSC CURS`
- `TIPUS_DESC`
- `VALID_DESC`
- `FRACCIO`
- `DATA_BAIXA`
- `MOTIU_BAIXA`
- `QUI_BAIXA`

Funcio:

- conserva la inscripcio academica;
- conserva dades vives de l'alumne;
- conserva estat academic/administratiu;
- actualment conserva resum de pagaments;
- actualment vincula amb factura historica.

Canvi:

- no ha de ser la font fiscal immutable;
- pot conservar resum i relacions;
- les factures noves han de viure al SIF.

### 5.2. `web.factures`

Funcio actual:

- factures historiques;
- numero visible;
- conceptes;
- import;
- forma de pagament;
- relacio amb curs;
- `E_FACT`.

Canvi:

- deixara de ser la taula principal per factures noves VERI*FACTU;
- pot quedar com historic o compatibilitat;
- les noves factures han d'anar a `dades_fiscals.factura`.

### 5.3. `intranet.entitats` i `intranet.entitats_resp`

Funcio:

- dades d'empresa/entitat;
- responsable de contacte;
- correu de contacte.

Canvi:

- poden servir per omplir dades fiscals;
- la factura ha de guardar snapshot fiscal independent.

## 6. Taules noves de BD fiscal

### 6.1. `factura`

Document fiscal immutable.

Ha de guardar:

- UUID;
- idempotencia;
- serie;
- numero;
- data emissio;
- estat factura;
- estat AEAT;
- estat cobrament;
- indicador de factura electronica (`E_FACT` o equivalent);
- indicador de factura emesa abans de cobrament (`EMESA_ABANS_COBRAMENT`);
- receptor fiscal congelat;
- imports totals;
- origen.

Valors recomanats:

```text
ESTAT_FACTURA = ISSUED / RECTIFIED / CANCELLED
ESTAT_AEAT = PENDING / SENT / ACCEPTED / REJECTED / RETRY / FAILED
```

Separacio important:

```text
EMESA_ABANS_COBRAMENT = 1
    factura real emesa abans de cobrar.

E_FACT = 1
    factura marcada com a factura electronica.

Una factura abans de pagar no es automaticament factura electronica.
```

### 6.2. `factura_linia`

Linies de factura.

Serveix per:

- curs normal;
- pack;
- grup;
- regal;
- USOC;
- descomptes;
- despeses de gestio;
- rectificatives.

### 6.3. `factura_registres`

Registre fiscal i hash chain.

Camps temporals recomanats:

```sql
DATE_CREATED DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
DATE_SENT DATETIME NULL
```

`DATE_CREATED` es quan el SIF crea el registre fiscal.
`DATE_SENT` es quan el registre s'envia a AEAT.

### 6.4. `fiscal_sequence`

Sequencia de series A/R per any.

### 6.5. `fiscal_chain_state`

Estat de l'ultima posicio de la cadena hash.

### 6.6. `fiscal_queue`

Cua AEAT i reintents.

### 6.7. `factura_documents`

PDF/XML/QR i hash del fitxer.

### 6.8. `payment_transaction`

Cobrament, devolucio o compensacio.

### 6.9. `payment_allocation`

Assignacio d'un moviment economic a una o diverses factures.

### 6.10. `factura_rectificacio`

Vincle directe entre rectificativa i factura rectificada.

### 6.11. `fact_rels`

Relacio fiscal entre factura i origen de negoci.

Substitueix la connexio antiga basada nomes en `FACTURA_RELACIONADA`.

Objectiu:

- conservar compatibilitat amb `inscripcions.FACTURA_RELACIONADA`;
- permetre que una mateixa agrupacio/familia de factures tingui diverses factures fiscals;
- relacionar cada factura amb el seu `UUID_FACTURA`;
- permetre rectificatives, pagaments parcials, factures d'empresa/grup i historic;
- controlar visibilitat a alumne.

Proposta inicial:

```sql
CREATE TABLE fact_rels (
    ID BIGINT AUTO_INCREMENT PRIMARY KEY,
    FACTURA_RELACIONADA INT NOT NULL,
    UUID_FACTURA CHAR(36) NOT NULL,
    ID_INSC INT NULL,
    ID_FACTURA_LINIA BIGINT NULL,
    TIPUS_RELACIO VARCHAR(30) NOT NULL,
    -- INSCRIPCIO / PACK / GRUP / REGAL / EMPRESA / RECTIFICATIVA / HISTORIC
    VISIBLE_ALUMNE TINYINT(1) NOT NULL DEFAULT 1,
    IDPAG INT NULL,
    DS_ORDER VARCHAR(40) NULL,
    CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_fact_rel (FACTURA_RELACIONADA),
    KEY idx_uuid_factura (UUID_FACTURA),
    KEY idx_id_insc (ID_INSC)
) ENGINE=InnoDB;
```

## 7. Taules noves o revisades de BD intranet

### 7.1. `notificacions`

Avisos interns:

- error AEAT;
- retries fallits;
- factura en estat `FAILED`;
- anomalies de pagament/factura.

### 7.2. `canvi_curs`

Historic de canvis de curs:

- curs antic;
- curs nou;
- imports;
- descomptes;
- despeses;
- diferencia;
- accio fiscal;
- factures afectades.

### 7.3. `baixa_inscripcio`

Historic de baixes:

- motiu;
- import pagat;
- decisio client;
- retorn;
- saldo;
- rectificativa si cal.

### 7.4. `reclamacio_pagament`

Historic de reclamacions i morositat.

### 7.5. `credit_balance`

Decisio orientativa:

- ha de viure a BD fiscal si s'utilitza per compensar factures futures;
- pot tenir pantalla/consulta a intranet;
- quan s'utilitza per pagar una factura futura, ha de generar `payment_transaction` amb `TIPUS_MOVIMENT = COMPENSATION`.

Funcio:

- representar un saldo a favor d'un alumne, empresa o receptor fiscal;
- neix normalment d'una baixa, devolucio no monetaria o canvi de curs;
- es consumeix totalment o parcialment en factures futures.

Proposta inicial:

```sql
CREATE TABLE credit_balance (
    ID BIGINT AUTO_INCREMENT PRIMARY KEY,
    UUID_CREDIT CHAR(36) NOT NULL UNIQUE,
    HOLDER_TYPE VARCHAR(20) NOT NULL,
    -- ALUMNE / ENTITAT / CLIENT_FACTURACIO
    HOLDER_ID INT NULL,
    HOLDER_NIF_CIF VARCHAR(20) NULL,
    HOLDER_NOM_RAO VARCHAR(180) NOT NULL,
    IMPORT_ORIGINAL DECIMAL(12,2) NOT NULL,
    IMPORT_DISPONIBLE DECIMAL(12,2) NOT NULL,
    SOURCE_TYPE VARCHAR(30) NOT NULL,
    -- BAIXA / DEVOLUCIO / AJUST / CANVI_CURS
    SOURCE_ID BIGINT NULL,
    UUID_FACTURA_ORIGEN CHAR(36) NULL,
    UUID_FACTURA_RECTIFICATIVA CHAR(36) NULL,
    REVIEW_AFTER DATE NULL,
    ESTAT VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    -- ACTIVE / PARTIAL / USED / CANCELLED
    CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
```

Regla d'us:

```text
crear saldo -> credit_balance
usar saldo -> payment_transaction COMPENSATION + payment_allocation
```

## 8. SQL inicial proposat del SIF

Aquest SQL es la base inicial de treball. Pot requerir ajustos de noms, indexos o foreign keys segons la configuracio final de MySQL i la separacio real entre bases de dades.

### 8.1. `factura`

```sql
CREATE TABLE factura (
    ID BIGINT AUTO_INCREMENT PRIMARY KEY,
    UUID_FACTURA CHAR(36) NOT NULL UNIQUE,
    IDEMPOTENCY_KEY VARCHAR(100) NOT NULL UNIQUE,

    TIPUS_SERIE CHAR(1) NOT NULL,
    ANY_FACT SMALLINT NOT NULL,
    NUM_SEQ INT NOT NULL,
    NUM_VISIBLE VARCHAR(30) NOT NULL UNIQUE,

    TIPUS_FACTURA VARCHAR(5) NOT NULL DEFAULT 'F1',
    DATA_EMISSIO DATETIME NOT NULL,
    DATA_OPERACIO DATETIME NULL,
    DATA_PAGAMENT DATETIME NULL,

    EMESA_ABANS_COBRAMENT TINYINT(1) NOT NULL DEFAULT 0,
    E_FACT TINYINT(1) NOT NULL DEFAULT 0,
    ESTAT_COBRAMENT VARCHAR(20) NOT NULL DEFAULT 'PENDENT',
    ESTAT_FACTURA VARCHAR(20) NOT NULL DEFAULT 'ISSUED',
    ESTAT_AEAT VARCHAR(20) NOT NULL DEFAULT 'PENDING',

    BILLING_NOM_RAO VARCHAR(180) NOT NULL,
    BILLING_NIF_CIF VARCHAR(20) NOT NULL,
    BILLING_ADRECA VARCHAR(180) NULL,
    BILLING_CP VARCHAR(10) NULL,
    BILLING_POBLACIO VARCHAR(120) NULL,
    BILLING_PROVINCIA VARCHAR(120) NULL,
    BILLING_PAIS CHAR(2) NOT NULL DEFAULT 'ES',
    BILLING_EMAIL VARCHAR(180) NULL,

    IMPORT_BASE DECIMAL(12,2) NOT NULL,
    DESC_IMPORT DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    BASE_IMPOSABLE DECIMAL(12,2) NOT NULL,
    IVA_REGIM VARCHAR(20) NOT NULL DEFAULT 'EXEMPT',
    IVA_PCT DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    IVA_IMPORT DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    TOTAL DECIMAL(12,2) NOT NULL,

    SOURCE_CHANNEL VARCHAR(30) NOT NULL,
    CREATED_BY VARCHAR(80) NULL,
    CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_factura_num (TIPUS_SERIE, ANY_FACT, NUM_SEQ)
) ENGINE=InnoDB;
```

Responsabilitat:

- document fiscal immutable;
- snapshot del receptor fiscal;
- totals fiscals congelats;
- estat AEAT i cobrament.

### 8.2. `factura_linia`

```sql
CREATE TABLE factura_linia (
    ID BIGINT AUTO_INCREMENT PRIMARY KEY,
    UUID_FACTURA CHAR(36) NOT NULL,
    ORDRE INT NOT NULL,

    CONCEPTE VARCHAR(255) NOT NULL,
    DETALL TEXT NULL,

    QUANTITAT DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    PREU_UNITARI DECIMAL(12,2) NOT NULL,

    DESC_ORIGEN VARCHAR(30) NULL,
    DESC_MODE VARCHAR(20) NULL,
    DESC_TIPUS INT NULL,
    DESC_ID INT NULL,
    DESC_CODI_PROMO VARCHAR(80) NULL,
    DESC_PCT DECIMAL(5,2) NULL,
    DESC_IMPORT DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    DESC_TEXT_VISIBLE VARCHAR(255) NULL,
    DESC_MOTIU_INTERN VARCHAR(255) NULL,

    BASE_LINIA DECIMAL(12,2) NOT NULL,
    IVA_REGIM VARCHAR(20) NOT NULL DEFAULT 'EXEMPT',
    IVA_PCT DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    IVA_IMPORT DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    TOTAL_LINIA DECIMAL(12,2) NOT NULL,

    SOURCE_TYPE VARCHAR(30) NULL,
    SOURCE_ID INT NULL,

    FOREIGN KEY (UUID_FACTURA) REFERENCES factura(UUID_FACTURA)
) ENGINE=InnoDB;
```

Responsabilitat:

- cursos;
- participants;
- packs;
- regals;
- descomptes;
- codis promocionals;
- despeses de gestio;
- rectificatives parcials.

Regla:

```text
El SIF no recalcula la logica de descompte.
El SIF rep i congela la foto fiscal del preu, descompte i total.
```

### 8.3. Sequencia i hash chain

```sql
CREATE TABLE fiscal_sequence (
    TIPUS_SERIE CHAR(1) NOT NULL,
    ANY_FACT SMALLINT NOT NULL,
    LAST_NUM INT NOT NULL DEFAULT 0,
    UPDATED_AT TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (TIPUS_SERIE, ANY_FACT)
) ENGINE=InnoDB;

CREATE TABLE fiscal_chain_state (
    ID TINYINT PRIMARY KEY,
    LAST_FISCAL_ORDER BIGINT NOT NULL DEFAULT 0,
    LAST_HASH CHAR(64) NULL,
    UPDATED_AT TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO fiscal_chain_state (ID, LAST_FISCAL_ORDER, LAST_HASH)
VALUES (1, 0, NULL);
```

Responsabilitat:

- `fiscal_sequence` evita col·lisions de numeracio per serie/any;
- `fiscal_chain_state` controla l'ultima posicio global de la cadena hash.

Regla transaccional obligatoria:

```text
No es pot calcular mai el numero fiscal amb SELECT MAX(NUM)+1.
No es pot calcular mai el hash anterior llegint simplement l'ultima factura sense bloqueig.
```

Flux correcte dins `issueInvoice()`:

```text
START TRANSACTION
    comprovar idempotency_key
    bloquejar fiscal_sequence per TIPUS_SERIE + ANY_FACT amb FOR UPDATE
    bloquejar fiscal_chain_state ID=1 amb FOR UPDATE
    incrementar NUM_SEQ
    calcular NUM_VISIBLE
    calcular HASH_FACT amb LAST_HASH
    inserir factura
    inserir factura_linia
    inserir factura_registres
    actualitzar fiscal_sequence
    actualitzar fiscal_chain_state
    inserir fiscal_queue
COMMIT
```

El bloqueig de `fiscal_sequence` evita duplicats de numeracio. El bloqueig de `fiscal_chain_state` evita forks de hash chain.

Ordre recomanat de bloqueig per reduir deadlocks:

```text
1. idempotencia
2. fiscal_sequence
3. fiscal_chain_state
4. inserts de factura i registres
```

No es recomana obtenir el hash anterior amb:

```sql
SELECT HASH_FACT
FROM factura_registres
ORDER BY ID DESC
LIMIT 1
FOR UPDATE;
```

Encara que pot semblar correcte, es mes clar i robust tenir una fila unica a `fiscal_chain_state` que representa l'estat oficial de la cadena.

### 8.4. `factura_registres`

```sql
CREATE TABLE factura_registres (
    ID BIGINT AUTO_INCREMENT PRIMARY KEY,
    UUID_FACTURA CHAR(36) NOT NULL,
    FISCAL_ORDER BIGINT NOT NULL UNIQUE,

    TIPUS_REGISTRE VARCHAR(20) NOT NULL,
    HASH_FACT CHAR(64) NOT NULL,
    HASH_FACT_ANT CHAR(64) NULL,

    PAYLOAD_JSON JSON NOT NULL,
    XML_ENVIAT MEDIUMTEXT NULL,
    CSV VARCHAR(64) NULL,

    DATE_CREATED DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    DATE_SENT DATETIME NULL,
    ESTAT_ENVIO VARCHAR(30) NOT NULL DEFAULT 'PENDING',

    FOREIGN KEY (UUID_FACTURA) REFERENCES factura(UUID_FACTURA)
) ENGINE=InnoDB;
```

Responsabilitat:

- registre fiscal;
- hash chain;
- payload fiscal congelat;
- estat d'enviament.

### 8.5. `factura_rectificacio`

```sql
CREATE TABLE factura_rectificacio (
    ID BIGINT AUTO_INCREMENT PRIMARY KEY,
    UUID_FACTURA_RECTIFICATIVA CHAR(36) NOT NULL,
    UUID_FACTURA_RECTIFICADA CHAR(36) NOT NULL,

    MOTIU VARCHAR(50) NOT NULL,
    MODE_RECTIFICACIO CHAR(1) NOT NULL,
    DESCRIPCIO TEXT NULL,

    CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (UUID_FACTURA_RECTIFICATIVA) REFERENCES factura(UUID_FACTURA),
    FOREIGN KEY (UUID_FACTURA_RECTIFICADA) REFERENCES factura(UUID_FACTURA)
) ENGINE=InnoDB;
```

Valors orientatius:

- `MODE_RECTIFICACIO = S`: substitucio.
- `MODE_RECTIFICACIO = I`: diferencies.

### 8.6. Pagaments

```sql
CREATE TABLE payment_transaction (
    ID BIGINT AUTO_INCREMENT PRIMARY KEY,
    UUID_PAYMENT CHAR(36) NOT NULL UNIQUE,
    IDEMPOTENCY_KEY VARCHAR(100) NOT NULL UNIQUE,

    TIPUS_MOVIMENT VARCHAR(20) NOT NULL,
    METODE VARCHAR(30) NOT NULL,
    IMPORT DECIMAL(12,2) NOT NULL,
    DATA_MOVIMENT DATETIME NOT NULL,

    DS_ORDER VARCHAR(40) NULL,
    IDPAG INT NULL,
    REFERENCIA_BANCARIA VARCHAR(100) NULL,

    ESTAT VARCHAR(20) NOT NULL DEFAULT 'CONFIRMED',
    NOTES TEXT NULL,
    CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE payment_allocation (
    ID BIGINT AUTO_INCREMENT PRIMARY KEY,
    UUID_PAYMENT CHAR(36) NOT NULL,
    UUID_FACTURA CHAR(36) NOT NULL,

    IMPORT_ASSIGNAT DECIMAL(12,2) NOT NULL,
    TIPUS_ASSIGNACIO VARCHAR(20) NOT NULL,
    CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (UUID_PAYMENT) REFERENCES payment_transaction(UUID_PAYMENT),
    FOREIGN KEY (UUID_FACTURA) REFERENCES factura(UUID_FACTURA)
) ENGINE=InnoDB;
```

Responsabilitat:

- `payment_transaction`: cobrament, retorn o compensacio real.
- `payment_allocation`: com s'aplica aquell moviment a una o diverses factures.

### 8.7. `fact_rels`

Nom final decidit: `fact_rels`.

Es la relacio nova amb `FACTURA_RELACIONADA` i substitueix la connexio antiga de factures relacionades.

Responsabilitat:

```sql
CREATE TABLE fact_rels (
    ID BIGINT AUTO_INCREMENT PRIMARY KEY,
    UUID_FACTURA CHAR(36) NOT NULL,
    FACTURA_RELACIONADA INT NULL,
    SOURCE_TYPE VARCHAR(30) NOT NULL,
    SOURCE_ID INT NOT NULL,
    ID_FACTURA_LINIA BIGINT NULL,
    IDPAG INT NULL,
    DS_ORDER VARCHAR(40) NULL,
    VISIBLE_ALUMNE TINYINT(1) NOT NULL DEFAULT 1,
    CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (UUID_FACTURA) REFERENCES factura(UUID_FACTURA),
    KEY idx_source (SOURCE_TYPE, SOURCE_ID),
    KEY idx_idpag (IDPAG),
    KEY idx_factura_relacionada (FACTURA_RELACIONADA)
) ENGINE=InnoDB;
```

Responsabilitat:

- vincular factura amb inscripcio, pack, grup, regal, entitat o historic;
- conservar compatibilitat amb `FACTURA_RELACIONADA`;
- controlar visibilitat a l'alumne.

### 8.8. Cua AEAT i documents

```sql
CREATE TABLE fiscal_queue (
    ID BIGINT AUTO_INCREMENT PRIMARY KEY,
    UUID_FACTURA CHAR(36) NOT NULL,
    IDEMPOTENCY_KEY VARCHAR(100) NOT NULL UNIQUE,

    STATUS VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    ATTEMPTS TINYINT NOT NULL DEFAULT 0,
    NEXT_RETRY DATETIME NULL,
    LAST_ERROR TEXT NULL,
    LOCKED_AT DATETIME NULL,
    LOCKED_BY VARCHAR(80) NULL,

    CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UPDATED_AT TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (UUID_FACTURA) REFERENCES factura(UUID_FACTURA),
    KEY idx_queue (STATUS, NEXT_RETRY)
) ENGINE=InnoDB;

CREATE TABLE factura_documents (
    ID BIGINT AUTO_INCREMENT PRIMARY KEY,
    UUID_FACTURA CHAR(36) NOT NULL,
    TIPUS VARCHAR(10) NOT NULL DEFAULT 'PDF',
    PATH_FITXER VARCHAR(255) NOT NULL,
    HASH_FITXER CHAR(64) NULL,
    ENVIAT_CORREU TINYINT(1) NOT NULL DEFAULT 0,
    DATA_ENVIAMENT DATETIME NULL,
    CORREU_DEST VARCHAR(180) NULL,
    GENERATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (UUID_FACTURA) REFERENCES factura(UUID_FACTURA)
) ENGINE=InnoDB;
```

### 8.9. `redsys_notifications`

Taula auxiliar per evitar reprocessar callbacks Redsys duplicats:

```sql
CREATE TABLE redsys_notifications (
    ID BIGINT AUTO_INCREMENT PRIMARY KEY,
    DS_ORDER VARCHAR(30) NOT NULL UNIQUE,
    IDPAG INT NOT NULL,
    ID_INSC INT NULL,
    IMPORT DECIMAL(12,2) NOT NULL,
    RESPONSE_CODE VARCHAR(10) NOT NULL,
    STATUS VARCHAR(20) NOT NULL,
    CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
```

## 9. Taules intranet vinculades

### 9.1. `notificacions`

```sql
CREATE TABLE notificacions (
    ID BIGINT AUTO_INCREMENT PRIMARY KEY,
    TIPUS VARCHAR(30) NOT NULL,
    PRIORITAT TINYINT NOT NULL DEFAULT 2,
    TITOL VARCHAR(255) NOT NULL,
    MISSATGE TEXT NOT NULL,
    UUID_REF CHAR(36) NULL,
    LLEGIDA TINYINT(1) NOT NULL DEFAULT 0,
    ID_USUARI INT NULL,
    CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UPDATED_AT TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
```

Responsabilitat:

- avisos interns;
- retries fallits;
- error AEAT;
- factura en estat `FAILED`;
- anomalies pagament/factura.

### 9.2. `motiu_canvi`, `canvi_curs`, `baixa_inscripcio`, `reclamacio_pagament`

Responsabilitat:

- documentar events operatius que poden tenir impacte fiscal;
- evitar modificar `A_PAGAR` sense rastre;
- guardar motius, imports, usuari i dates;
- vincular amb factura/rectificativa si cal.

## 10. Regla d'immutabilitat

```text
factura = document fiscal immutable
factura_linia = detall fiscal congelat
payment_transaction = moviment economic real o compensacio
payment_allocation = aplicacio del moviment a factura
factura_registres = hash chain + registre fiscal
fiscal_queue = enviament/retry AEAT
factura_documents = PDF/XML/QR immutable
```

## 11. Pendent de completar

- Revisar SQL final de `fact_rels` abans d'implantacio.
- Confirmar foreign keys possibles entre esquemes.
- Estrategia si MySQL no permet FK entre BDs segons configuracio.
- Permisos MySQL per usuari SIF, usuari intranet i usuari lectura.
- Migracio de `web.factures` historic.
- Taules de codis promocionals.
- Taules de regals.
- Taules de packs.
- Indexos finals segons consultes reals.
