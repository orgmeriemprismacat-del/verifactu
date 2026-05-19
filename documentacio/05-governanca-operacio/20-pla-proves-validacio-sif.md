# 20 - Pla de proves i validacio del SIF

> Document per demostrar que el SIF funciona correctament abans de posar-lo en produccio i en cada canvi rellevant de versio.

## 1. Objectiu

Validar que el SIF garanteix:

- numeracio sense duplicats;
- idempotencia;
- integritat de hash chain;
- emissio correcta de factures;
- rectificatives;
- PDF/QR immutable;
- cua AEAT i reintents;
- notificacions d'errors;
- permisos i bloquejos;
- integracio Redsys, transferencia i compensacions.

## 2. Proves minimes

- curs individual Redsys;
- callback Redsys duplicat;
- pagament fraccionat;
- factura abans de cobrar i cobrament posterior;
- pack;
- grup;
- regal;
- USOC;
- canvi de curs amb diferencia;
- canvi de curs amb saldo;
- baixa amb devolucio;
- baixa amb saldo;
- rectificativa per canvi de dades fiscals;
- generacio PDF/QR;
- error AEAT i retry;
- error PDF;
- export de registres;
- acces al panell `pay.prisma.cat/sif`;
- consulta de declaracio responsable i versio activa;
- consulta d'incidencies SIF;
- consulta intranet alumne;
- consulta intranet empresa/responsable.

## 3. Evidencia de prova

Cada prova haura de guardar:

- data;
- versio;
- usuari/provador;
- dades d'entrada;
- resultat esperat;
- resultat obtingut;
- captura o log;
- incidencia si falla.

## 4. Escenari de proves abans de produccio

Abans de posar el SIF en produccio cal provar-lo en un escenari separat.

Objectiu:

```text
validar casos reals o equivalents sense contaminar numeracio, registres ni factures productives.
```

Opcions recomanades:

- BD fiscal de proves separada;
- configuracio SIF en mode proves;
- Redsys en mode test si s'utilitza en la prova;
- configuracio AEAT de proves si correspon;
- dades reals anonimitzades o duplicades en entorn controlat;
- numeracio de proves separada de la numeracio fiscal productiva;
- documents PDF/QR marcats com a prova;
- logs i captures conservats com a evidencia.

Regla:

```text
Cap prova pot crear factura productiva real ni consumir numeracio fiscal real.
```

## 5. Proves obligatories d'auditoria tecnica

### 5.1. Immutabilitat de factura

Prova:

```text
emetre factura
intentar modificar receptor/import/concepte des de app i BD amb usuari no autoritzat
verificar que no es pot modificar
generar rectificativa si cal canvi
```

Evidencia:

- factura original;
- intent bloquejat;
- log;
- rectificativa si correspon.

### 5.2. Idempotencia Redsys/retry

Prova:

```text
enviar dues vegades la mateixa peticio amb mateixa IDEMPOTENCY_KEY
verificar que retorna la mateixa factura
verificar que no augmenta fiscal_sequence dues vegades
```

Evidencia:

- request 1;
- request 2;
- resposta identica o marcada com duplicada;
- una sola factura;
- un sol numero fiscal.

### 5.3. Numeracio concurrent

Prova:

```text
llancar diverses emissions simultanies
verificar que no hi ha numeros duplicats
verificar que fiscal_order i hash chain queden lineals
```

### 5.4. Recorregut complet de factura

Prova:

```text
origen -> factura -> linies -> registre fiscal -> AEAT queue -> PDF/QR -> logs
```

S'ha d'executar almenys per:

- curs Redsys;
- pack;
- grup;
- regal;
- USOC;
- transferencia;
- factura abans de cobrament;
- rectificativa.
