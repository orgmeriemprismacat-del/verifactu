# 23 - Annex de captures de pantalla

> Annex visual per documentar el sistema final amb captures reals quan estigui implementat.

## 1. Objectiu

Conservar evidencia visual de les pantalles finals del SIF, ecommerce i intranet.

## 2. Fitxa de captura

Cada captura ha d'incloure:

- ID de captura;
- nom de pantalla;
- URL o apartat;
- rol;
- versio SIF;
- entorn (`TEST`, `PREPROD` o `PROD`);
- data de captura;
- resum de que es veu;
- camps fiscals visibles;
- accions fiscals possibles;
- notes de privacitat si hi ha dades personals.

Per pantalles amb impacte fiscal tambe ha d'incloure:

- estat previ de factura i cobrament;
- avisos visibles abans de confirmar;
- accio prevista pel sistema: `issueInvoice()`, `registerPayment()`, rectificativa, devolucio/saldo, consulta o incidencia;
- resultat posterior si la captura forma part d'una prova;
- referencia a l'ID de prova quan existeixi, per exemple `SIF-PANT-PAY-001`.

## 2.1. Nomenclatura de captures i evidencies

Les captures i fitxers d'evidencia s'han de poder ordenar sense obrir-los.

Format recomanat:

```text
VERSIO_ENTORN_IDPROVA_NUM_DESCRIPCIO.ext
```

Exemples:

```text
SIF-1.0.0_PREPROD_SIF-RED-002_01_callback-duplicat-request.png
SIF-1.0.0_PREPROD_SIF-RED-002_02_callback-duplicat-resultat.png
SIF-1.0.0_PREPROD_SIF-BCK-001_01_acta-restauracio.md
SIF-1.0.0_PREPROD_SIF-DOC-001_03_hash-pdf.txt
```

Criteris:

- no incloure noms complets, DNI/NIF, correus ni tokens complets al nom del fitxer;
- usar sempre l'ID de prova quan la captura forma part d'una prova;
- numerar captures consecutives quan hi ha flux abans/despres;
- conservar logs, exports i actes amb el mateix ID de prova que les captures relacionades.

## 2.2. Index d'evidencies

Cada campanya de proves ha de tenir un index d'evidencies.

Plantilla recomanada:

| ID evidencia | ID prova | Tipus | Fitxer o ubicacio | Que demostra | Dades ocultades | Resultat |
| --- | --- | --- | --- | --- | --- | --- |
| `EVID-001` | `SIF-RED-002` | captura | `...png` | Callback duplicat no duplica factura | `DS_ORDER` parcial | `PASS` |
| `EVID-002` | `SIF-RED-002` | log | `...log` | Una sola transaccio registrada | Imports reals si cal | `PASS` |

Tipus d'evidencia:

- `captura`;
- `log`;
- `export`;
- `PDF`;
- `XML`;
- `QR`;
- `hash`;
- `acta`;
- `consulta BD`;
- `resposta API`;
- `correu`;
- `incidencia`.

## 2.3. Criteri de captura valida

Una captura no es considera evidencia suficient si no permet saber:

- quina versio o entorn s'estava provant;
- quin rol o usuari executava l'accio;
- quin estat inicial o final es vol demostrar;
- quin ID de prova o incidencia hi esta vinculat;
- si hi ha dades personals, quines s'han ocultat i per que.

Una captura pot ser complementaria, pero no substitueix un log o export quan la prova vol demostrar idempotencia, numeracio, hash chain, backup/restauracio o resposta AEAT.

## 2.4. Evidencies minimes per tipus de prova

| Tipus prova | Evidencia visual | Evidencia tecnica |
| --- | --- | --- |
| Redsys duplicat | Captura de panell o resultat conciliacio | Dos callbacks, una sola factura/pagament, registre `redsys_notifications`. |
| Idempotencia factura | Captura de resposta o factura existent | Mateixa `IDEMPOTENCY_KEY`, un sol numero fiscal, consulta `fiscal_sequence`. |
| Concurrencia | Captura de resum de prova | Llistat de numeros, `FISCAL_ORDER`, hash chain lineal. |
| PDF/QR | Captura document disponible | Fitxer, hash, registre `factura_documents`. |
| AEAT retry | Captura incidencia/cua | Registre `fiscal_queue`, intents i resposta. |
| Permisos | Captura error o acces denegat | Log servidor o auditoria d'acces. |
| Backup/restauracio | Captura factura restaurada | Acta, origen backup, verificacio UUID/numero/hash/documents. |
| Visibilitat alumne/empresa | Captura de cada rol | Log d'acces o prova de token quan correspongui. |

## 2.5. Captures minimes per pantalles internes

| Pantalla | Captures minimes | Que ha de quedar demostrat |
| --- | --- | --- |
| `Passar pagaments` | Analisi TPV, cerca amb criteri unic, fila amb factura existent, fila sense factura, confirmacio i resultat | La pantalla separa TPV, cerca, confirmacio i resultat; no edita factura existent. |
| `Passar pagaments` amb factura abans de cobrament | Abans de confirmar i resultat posterior | El cobrament posterior fa `registerPayment()` i no crea factura nova. |
| `Passar pagaments` amb empresa/responsable | Avis de URL individual bloquejada o substituida | La inscripcio coberta per empresa/responsable no genera cobrament individual duplicat. |
| `Generar factura abans de pagar` | Seleccio, receptor/snapshot, previsualitzacio, avis de factura real, resultat amb numero i pendent | `EMESA_ABANS_COBRAMENT = 1`, `E_FACT = 0` per defecte i URL/pagament posterior separat. |
| `Consulta - Edita - Anula factura` | Cerca, fitxa SIF, fitxa historica, rectificatives, pagaments, PDF/QR i historial | La factura SIF es immutable i les accions son controlades. |
| `Consulta - Edita - Anula factura` amb diverses inscripcions | Assignacions abans de devolucio/saldo | No es confirma cap retorn sense veure impacte per inscripcio. |
| Intranet alumne | Factura individual visible i inscripcio coberta per empresa/responsable | L'alumne veu el que li correspon i no veu factura completa d'empresa/grup. |
| Empresa/responsable | Enllac segur valid, token invalid/caducat i PDF pendent | El document es serveix sense path intern i amb permisos. |
| Apartat `VERI*FACTU` intranet | Indicador, resum, avis de SIF no disponible i enllac al panell | La intranet informa i enllaça, pero no resol incidencies oficials. |

### 2.5.1. Paquets d'evidencia per tall UI

Cada tall del backlog `14-backlog-implementacio-pantalles-internes.md` ha de lliurar un paquet coherent. Les captures no es poden reutilitzar entre versions si ha canviat la pantalla, el contracte API, els permisos o la logica del servei afectat.

| Paquet | Proves | Captures | Evidencia no visual obligatoria |
| --- | --- | --- | --- |
| `EVID-UI-BASE` | `SIF-PANT-SEC-001..004` | Acces valid, denegacio neutra, CSRF rebutjat, preview caducat i SIF no disponible | Respostes HTTP, logs d'acces, codis estables i prova d'absencia d'escriptura |
| `EVID-UI-PAY` | `SIF-PANT-PAY-001..002` | Cerca, preview, bloqueig, confirmacio i resultat | Event d'auditoria, payment/allocation, idempotencia i consulta posterior |
| `EVID-UI-FAC` | `SIF-PANT-FAC-001` | Tres passos, avis fiscal i resultat pendent | Payload sense pagament, factura/linies, idempotencia i sync llegada |
| `EVID-UI-RECT` | `SIF-PANT-FACT-001..002` | Original, accions, bloqueig d'edicio, comparacio i rectificativa | Relacio original-rectificativa, motiu, logs i `updDadesFact` absent |
| `EVID-UI-VIS` | `SIF-VIS-002` | Alumne, empresa/responsable, token invalid i recurs no disponible | Logs d'acces/denegacio, abast del token i prova de no filtracio |
| `EVID-UI-AVI` | `SIF-AVI-001..002` | Quatre severitats, resum i estat desconegut | Resposta resum, permisos per rol i error de connexio controlat |

Cada paquet ha d'incloure un index amb:

- versio de codi d'intranet i SIF;
- entorn i configuracio rellevant anonimitzada;
- IDs de prova i resultat `PASS`, `FAIL` o `BLOCKED`;
- llista de fitxers d'evidencia amb hash;
- incidencies obertes i decisio sobre el tall;
- responsable que executa i responsable que revisa.

## 2.6. Criteri de privacitat de captures

- Si la captura usa dades reals, cal anonimitzar DNI/NIF, correu, telefon, adreca i imports quan no siguin necessaris per entendre la prova.
- Les captures d'empresa/grup no han de mostrar dades de participants no necessaris.
- Les captures d'enllac segur no han d'exposar token complet ni ruta interna.
- Les captures de PDF/QR han de demostrar estat i disponibilitat, pero poden ocultar dades personals si no son objecte de la prova.

## 3. Captures pendents

- ecommerce dades facturacio;
- ecommerce confirmacio pagament;
- URL pagament individual;
- URL pagament grup/empresa;
- URL pagament regal;
- URL pagament USOC;
- panell SIF `pay.prisma.cat/sif`;
- documentacio SIF dins del panell;
- versions SIF;
- incidencies SIF;
- exportacions SIF;
- intranet consulta/modifica alumne;
- modal dades curs;
- modal dades pagament;
- canvi de curs;
- baixa;
- veure factura;
- consulta factura alumne;
- consulta factura empresa/responsable;
- passar pagaments (`/alumnes/pagaments/`);
- generar factura abans de cobrar;
- consulta/edita/anula factura (`/alumnes/factura/`);
- notificacions fiscals;
- export registres.
