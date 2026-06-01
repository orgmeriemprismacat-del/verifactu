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

## 1.1. Principis de prova

Les proves del SIF s'han de preparar com a casos executables i repetibles, no nomes com a llista d'intencions.

Principis:

- cap prova pot consumir numeracio fiscal productiva;
- les dades de prova han d'estar separades de les factures reals;
- si es fan servir dades reals, han d'estar anonimitzades o duplicades en un entorn controlat;
- Redsys s'ha de provar en mode test o amb simulador quan no es vulgui provocar cobrament real;
- l'entorn AEAT de prova s'ha d'utilitzar si esta disponible i correspon al tipus d'integracio;
- cada prova ha de generar evidencia conservable;
- una prova fallida no es corregeix manualment sense deixar incidencia i resultat.

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

Fitxa minima per convertir una prova en executable:

| Camp | Contingut |
| --- | --- |
| ID prova | Identificador estable, per exemple `SIF-RED-001`. |
| Area | Redsys, factura, rectificativa, AEAT, permisos, PDF/QR, exportacio, etc. |
| Objectiu | Que es vol demostrar. |
| Dades entrada | Inscripcio, import, receptor, pagament, estat previ i usuari. |
| Passos | Accions concretes a executar. |
| Resultat esperat | Estat final de factura, pagament, documents, cua i logs. |
| Evidencia | Captura, export, log, hash, PDF, registre AEAT o incidencia. |
| Bloquejant | Si impedeix o no el pas a produccio. |

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

## 6. Proves de regressio recuperades del xat antic

El xat antic va insistir que el risc no es nomes crear factures noves, sino impedir que els mecanismes antics segueixin modificant dades fiscals sense control.

S'han d'afegir proves especifiques per demostrar que:

- no es pot corregir una factura emesa modificant `A_PAGAR`, receptor, concepte o imports sense rectificativa/event;
- no es poden moure pagaments entre inscripcions de manera que alteri una factura ja emesa;
- `Passar pagaments` no crea factura duplicada si ja existeix factura SIF;
- `Generar factura abans de pagar` crea factura real pendent de cobrament i el pagament posterior entra per `registerPayment()`;
- una URL individual de pagament queda bloquejada o substituida quan la factura correspon a empresa/responsable;
- els scripts antics, importadors, cron o eines Moodle no creen ni modifiquen factures fiscals;
- Moodle i altres APIs externes no tenen efecte fiscal directe;
- si el PDF/QR falla despres de la factura, la factura no es desfà i es crea incidencia SIF;
- si l'enviament AEAT falla, es conserva cua/retry i incidencia sense duplicar factura;
- un usuari sense permisos no pot executar accions fiscals critiques encara que vegi el boto o conegui l'endpoint;
- Meriem pot resoldre incidencia SIF o activar versio, pero Adam/Pablo/gestio no poden saltar controls de SIF;
- Isa pot fer suport segons rol, pero no queda com a operadora fiscal ordinaria.

## 7. Paquet de proves go/no-go

Abans de produccio s'ha de tancar un paquet go/no-go amb:

- identificador de versio provada;
- commit o paquet desplegat;
- migracions SQL aplicades;
- entorn utilitzat;
- llista de proves executades;
- resultat de cada prova;
- incidencies obertes i severitat;
- decisio final: `GO`, `GO AMB LIMITACIONS` o `NO-GO`;
- responsable tecnica;
- revisio de direccio/responsable legal quan correspongui.

Regla:

```text
Una versio no passa a produccio nomes perque compila o perque el flux ideal funciona.
Ha de superar els casos critics, regressions i evidencies minimes.
```
