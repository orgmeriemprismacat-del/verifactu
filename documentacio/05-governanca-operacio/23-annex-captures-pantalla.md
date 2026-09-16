# 23 - Annex de captures de pantalla

> Annex visual per documentar el sistema final amb captures reals quan estigui implementat.

## 1. Objectiu

Conservar evidencia visual de les pantalles finals del SIF, ecommerce i intranet.

## 2. Fitxa de captura

Cada captura ha d'incloure:

- nom de pantalla;
- URL o apartat;
- rol;
- versio SIF;
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

## 2.1. Captures minimes per pantalles internes

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

## 2.2. Criteri de privacitat de captures

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
