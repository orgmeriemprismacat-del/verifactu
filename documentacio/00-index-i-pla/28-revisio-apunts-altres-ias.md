# 28 - Revisio d'apunts d'altres IA

> Document de triatge. Serveix per revisar aportacions externes o d'altres converses d'IA i decidir que s'incorpora al projecte, que queda descartat i que s'ha de matisar abans d'usar-ho.

Estat: `TRIATGE TANCAT`.

Decisio:

- es conserva com a annex intern de contrast;
- no forma part de la documentacio que s'ha de publicar o lliurar com a document principal del SIF;
- no s'ha de continuar ampliant excepte si arriba una aportacio externa nova molt rellevant;
- les decisions valides ja s'han d'incorporar als documents principals;
- les propostes descartades o matisades queden aqui com a rastre de per que no s'han adoptat.

## 1. Criteri de revisio

Els apunts d'una altra IA no s'incorporen automaticament.

Per cada proposta es classifica:

- `INCORPORAR`: encaixa amb el sistema PrisMa i reforca el disseny.
- `JA COBERT`: ja esta documentat amb igual o millor criteri.
- `MATISAR`: conte una idea util, pero cal adaptar-la al cas real.
- `DESCARTAR`: pot portar a una arquitectura incorrecta o menys segura.
- `PENDENT DE FONT OFICIAL`: afecta normativa, terminis o interpretacio fiscal i cal validar-ho amb documentacio oficial o criteri intern documentat.

## 2. Aportacions que es queden

### 2.1. SIF centralitzat

Classificacio: `INCORPORAR / JA COBERT`

La idea correcta es:

```text
TPV, intranet i ecommerce son canals clients.
El SIF central emet la factura fiscal.
```

Aixo ja esta incorporat als documents principals.

Documents:

- `documentacio-verifactu.md`
- `01-compliment-aeat/documentacio-sif-aeat.md`
- `02-context-i-estat-actual/12-documentacio-sistema-ecommerce-intranet-sif.md`
- `03-canvis-pendents/06-integracio-redsys-pay-prisma.md`

### 2.2. Diagnosi de risc multi-canal

Classificacio: `INCORPORAR`

Es correcte conservar la idea que l'estat inicial podia semblar emissio distribuida si cada canal calculava i generava factures pel seu compte.

La conclusio final no es defensar aquest model, sino justificar la migracio:

```text
Risc inicial: emissio distribuida o logica fiscal replicada.
Solucio: SIF central amb API, BD fiscal unica i cadena hash global.
```

### 2.3. Idempotencia real

Classificacio: `INCORPORAR / JA COBERT`

Es correcte:

- generar una `IDEMPOTENCY_KEY` estable;
- persistir-la;
- imposar `UNIQUE`;
- retornar la mateixa factura si la peticio ja existia;
- no generar numero abans de comprovar idempotencia.

Ja queda incorporat a:

- `04-estat-final/05-model-bd-sif.md`
- `03-canvis-pendents/06-integracio-redsys-pay-prisma.md`
- `03-canvis-pendents/04-fluxos-facturacio.md`

### 2.4. Retry + incidencia

Classificacio: `INCORPORAR AMB MATIS`

Es correcte que el sistema tingui retries, logs i incidencia visible a la intranet/SIF quan falla AEAT o un procés fiscal.

Matis important:

```text
El retry durable ha de viure al SIF, amb fiscal_queue.
El canal pot fer retry curt de connexio, pero no ha de substituir la cua fiscal persistent.
```

### 2.5. Notificacions i documents

Classificacio: `INCORPORAR / JA COBERT`

Es correcte crear:

- `notificacions` a BD intranet;
- `factura_documents` a BD fiscal;
- registre de PDF/XML/QR amb hash de fitxer.

Ja queda documentat a:

- `04-estat-final/05-model-bd-sif.md`
- `03-canvis-pendents/07-pantalles-intranet.md`
- `04-estat-final/25-panell-sif-pay-prisma.md`

### 2.6. Intranet alumne amb consulta de factura

Classificacio: `INCORPORAR`

La intranet personalitzada de l'alumne haura de permetre consultar factures on l'alumne sigui receptor fiscal, amb PDF/QR quan correspongui.

Limit:

```text
L'alumne no ha de veure factures de grup o empresa si no n'es receptor fiscal.
```

## 3. Propostes que cal matisar

### 3.1. Classe PHP de crida API amb retry

Classificacio: `MATISAR`

La idea de cridar l'API central amb cURL es correcta.

Pero no es correcte que la clau idempotent es generi amb:

```php
md5($endpoint . json_encode($dades) . date('YmdH'))
```

Problemes:

- si el retry cau en una altra hora, canvia la clau;
- no esta vinculada prou a l'operacio real;
- no es prou clara per auditoria;
- no serveix be per fraccionaments o `Ds_Order`.

Criteri PrisMa:

```text
La idempotency_key s'ha de generar a partir de l'identificador de negoci:
REDSYS|CURS|IDPAG:{IDPAG}|ORDER:{DS_ORDER}
REDSYS|PACK|IDPAG:{IDPAG}|ORDER:{DS_ORDER}
TRANSFERENCIA|{DATA}|REF:{REFERENCIA}
INTRANET|FACTURA_ABANS_COBRAR|FACT_REL:{FACTURA_RELACIONADA}
```

### 3.2. Retry al canal

Classificacio: `MATISAR`

Un retry curt al canal pot ajudar contra errors de xarxa immediats, pero no garanteix recuperacio fiscal.

El que garanteix recuperacio es:

- registre persistent de la factura;
- `fiscal_queue`;
- worker;
- intents;
- incidencia si falla;
- idempotencia.

### 3.3. `factura_linies`

Classificacio: `INCORPORAR AMB NOM FINAL A DECIDIR`

La conversa va generar confusio amb `factura_linies`, pero la idea es correcta: cal tenir linies fiscals estructurades.

Nom en la documentacio PrisMa:

```text
factura_linia
```

Responsabilitat:

- curs/taller/jornada;
- pack amb una linia per curs;
- grup amb una linia per participant;
- regal;
- descompte visible i motiu intern;
- base, IVA exempt, total.

### 3.4. Factures abans de cobrar

Classificacio: `MATISAR`

La conversa va introduir el terme "proforma", pero PrisMa no treballa amb proformes en aquest cas.

Criteri final:

```text
Factura abans de cobrar = factura real amb numero fiscal.
EMESA_ABANS_COBRAMENT = 1.
E_FACT nomes indica factura electronica.
```

El pagament posterior no crea factura nova; registra cobrament amb `registerPayment()`.

## 4. Propostes descartades

### 4.1. `LOCK TABLES`

Classificacio: `DESCARTAR`

No es recomana usar:

```sql
LOCK TABLES factures WRITE, comptadors WRITE;
```

Criteri PrisMa:

```text
InnoDB + START TRANSACTION + SELECT ... FOR UPDATE
```

Motiu:

- bloqueig mes fi;
- millor concurrencia;
- menys risc d'efectes laterals;
- encaixa amb `fiscal_sequence` i `fiscal_chain_state`.

### 4.2. `SELECT HASH_FACT ORDER BY ID DESC LIMIT 1 FOR UPDATE`

Classificacio: `DESCARTAR COM A DISSENY FINAL`

Pot semblar una solucio simple, pero el disseny final es mes robust amb:

```text
fiscal_chain_state.ID = 1
LAST_FISCAL_ORDER
LAST_HASH
```

El SIF bloqueja aquesta fila amb `FOR UPDATE` i evita forks de hash chain.

### 4.3. Hash chain per serie

Classificacio: `DESCARTAR PER PRISMA`

Per simplicitat auditora i coherencia global, PrisMa adopta:

```text
hash chain global unica
```

No es fa una cadena per serie ni per canal.

### 4.4. Generar hash despres de crear factura

Classificacio: `DESCARTAR`

La factura, linies, registre fiscal, hash i actualitzacio de chain state han d'anar dins la mateixa transaccio.

No es correcte:

```text
crear factura
despres en un altre procés generar hash fiscal
```

El worker pot enviar AEAT o generar documents, pero no decidir el registre fiscal base despres.

## 5. Punts que no s'han d'assumir sense verificacio

### 5.1. Terminis i dates legals

Classificacio: `PENDENT DE FONT OFICIAL`

Qualsevol afirmacio sobre dates d'obligatorietat o terminis s'ha de verificar amb AEAT/BOE abans d'incorporar-la.

No s'ha d'arrossegar cap data d'una conversa d'IA sense contrast oficial.

### 5.2. Tipus exacte de rectificativa

Classificacio: `PENDENT DE CRITERI FISCAL DOCUMENTAT`

Cal decidir i documentar per cas:

- rectificativa per substitucio;
- rectificativa per diferencies;
- canvi de dades fiscals;
- canvi de curs;
- devolucio parcial;
- devolucio total;
- saldo/compensacio.

Si no hi ha assessor fiscal, el criteri s'ha de documentar com a criteri intern i basar-se en fonts oficials disponibles.

### 5.3. Estructura final XML/QR AEAT

Classificacio: `PENDENT D'ESPECIFICACIO FINAL`

La BD i fluxos estan dissenyats per suportar-ho, pero els camps exactes del XML i QR s'han d'ajustar amb l'especificacio tecnica aplicable abans d'entrar en produccio.

## 6. Conclusions practiques

La conversa amb Claude ajuda en:

- confirmar l'arquitectura centralitzada;
- reforçar idempotencia i retries;
- detectar que PDF/QR/notificacions impliquen programacio real;
- posar en evidencia la carrega de feina;
- ordenar preguntes sobre intranet, descomptes i canvis de curs.

No ajuda prou en:

- precisio normativa final;
- criteris de rectificatives;
- codi definitiu de SIF;
- disseny de concurrencia final;
- evitar repetir preguntes ja resoltes.

Decisio:

```text
Els apunts externs s'utilitzen com a contrast.
El disseny rector continua sent la documentacio PrisMa actual:
SIF central, BD fiscal unica, hash chain global, fiscal_sequence, fiscal_chain_state,
idempotencia real, fiscal_queue i rectificatives controlades.
```
