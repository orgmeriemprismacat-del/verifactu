# Revisio Trellos control i interficies - 2026-06-10

## Fonts revisades

- `C:/Users/Usuario/Downloads/EL6BCeUI - 1-verifactu-sif-control-del-projecte (4).json`
- `C:/Users/Usuario/Downloads/O2xZJHSp - 4-verifactu-sif-intranet-interficie-i-notificacions (11).json`
- `00-control/trello-auditoria-interficies-proves-faltants-2026-06-08.md`
- `00-control/trello-auditoria-targetes-faltants-local-2026-06-08.md`

## Verdict curt

El Trello de control esta molt mes a prop de poder-se considerar complet funcionalment: hi ha decisions, fase 11, mapa de cobertura i documentacio. El problema principal no es tant que faltin moltes peces, sino que hi ha massa duplicats, llistes antigues sense netejar i targetes sense etiquetes/descripcio.

El Trello d'interficies ha millorat molt i esta carregat amb estructura, etiquetes i descripcions, pero encara no el consideraria complet. Hi falten targetes petites concretes en fluxos clau, sobretot `Consulta / modifica alumne`, `Passar pagaments` i `Generar factura abans de pagar`. Moltes coses hi son de forma generica, pero no amb el nivell de targeta petita que vols per veure tota la feina real.

## 1. Trello control del projecte

Tauler: `1 VeriFactu / SIF · Control del projecte`

URL: https://trello.com/b/EL6BCeUI/1-verifactu-sif-control-del-projecte

### Estat observat

- Targetes obertes: 2.476
- Llistes: 26
- Targetes sense etiqueta: 126
- Targetes sense descripcio: 17
- Grups duplicats detectats per nom: 475
- Targetes duplicades sobrants estimades: 964

### El que esta be

- Les decisions principals de Fase 11 hi son.
- Hi ha llistes fortes de control documental, decisions preses, decisions pendents, mapa de cobertura i produccio/go-no-go.
- El tauler ja serveix com a visio de gestio del projecte si es netegen duplicats i restes antigues.
- De les targetes proposades a l'auditoria anterior per control, n'he trobat 5 de 6.

### Targeta que encara falta o convindria afegir

| Llista suggerida | Targeta |
|---|---|
| Revisio fina - targetes a crear o dividir | Separar targetes generiques de Fase 11 en curs, pack, grup, manual i Redsys |

### Problemes de qualitat del tauler

| Problema | Impacte | Que faria |
|---|---|---|
| Molts duplicats al mapa de cobertura d'intranet | Dona sensacio de volum, pero dificulta saber que esta realment pendent | Consolidar duplicats i deixar-ne una targeta mestra per pantalla/flux |
| `Verifactu - Backlog` conserva targetes antigues, separadors i targetes sense etiqueta/descripcio | Barreja memoria historica amb feina accionable | Reanomenar-la a backlog historic/triage o moure les targetes vives a llistes actuals |
| `Permisos i rols` te moltes targetes sense etiqueta | Costa filtrar i repartir feina | Etiquetar per `PERMISOS`, `INTRANET`, `SIF`, `DECISIO PENDENT` o `PROGRAMACIO` segons cas |
| Targetes generiques de cobertura | Poden quedar com a recordatori, pero no substitueixen targetes petites | Convertir-les en targetes concretes o checklists dins d'una targeta mestra |

### Recomanacio per control

Jo no pujaria moltes targetes noves a control ara mateix. Primer faria neteja:

1. Deduplicar `Mapa de cobertura · Intranet i fluxos afectats`.
2. Triar que queda viu dins `Verifactu - Backlog`.
3. Etiquetar `Permisos i rols`.
4. Afegir la targeta que falta sobre separar Fase 11 per curs, pack, grup, manual i Redsys.

## 2. Trello intranet, interficie i notificacions

Tauler: `4 VeriFactu / SIF · Intranet, interfície i notificacions`

URL: https://trello.com/b/O2xZJHSp/4-verifactu-sif-intranet-interf%C3%ADcie-i-notificacions

### Estat observat

- Targetes obertes: 2.694
- Llistes: 109
- Targetes sense etiqueta: 0
- Targetes sense descripcio: 0
- Grups duplicats detectats per nom: 48
- Targetes duplicades sobrants estimades: 48
- Targetes esperades de l'auditoria anterior per interficies: 52
- Targetes trobades exactes o equivalents: 30
- Targetes que encara falten amb el detall esperat: 22

### El que esta be

- El tauler esta molt mes treballat que abans: totes les targetes tenen etiqueta i descripcio.
- Hi ha cobertura bona per correus, plantilles, enllac segur, visualitzacio de factura i pantalla `Consulta / Edita / Anula factura`.
- Hi ha llistes especifiques per pantalles i fluxos importants.
- El volum no es el problema; el problema es que algunes targetes encara son massa generiques per representar feina real i comprovable.

### Targetes concretes que encara falten

| Bloc | Targeta que falta |
|---|---|
| Consulta / modifica alumne | Mostrar estat fiscal resumit per inscripcio: factura, receptor, cobrament, AEAT, PDF/QR i URL |
| Consulta / modifica alumne | Afegir avis: canviar dades personals no modifica factures ja emeses |
| Consulta / modifica alumne | Bloquejar canvi fiscal de receptor/import/concepte i derivar a rectificativa |
| Consulta / modifica alumne | Redissenyar modal de dades de pagament separant dades operatives, cobrament, factura i URL |
| Consulta / modifica alumne | Mostrar motiu d'inactivacio de URL de pagament |
| Consulta / modifica alumne | Diferenciar factura historica no VERI*FACTU de factura SIF amb PDF immutable |
| Consulta / modifica alumne | Validar server-side les accions de les icones encara que la icona estigui oculta/desactivada |
| Passar pagaments / conciliacio SIF | Redissenyar cerca per permetre un sol criteri: NIF/NIE, CODI REGAL o NUM FACTURA |
| Passar pagaments / conciliacio SIF | Mostrar decisor abans de confirmar: registerPayment(), issueInvoice() o incidencia |
| Passar pagaments / conciliacio SIF | Pantalla per transferencia contra factura SIF existent |
| Passar pagaments / conciliacio SIF | Pantalla per transferencia contra factura abans de cobrament |
| Passar pagaments / conciliacio SIF | Pantalla per transferencia sense factura SIF previa i venda facturable |
| Passar pagaments / conciliacio SIF | Avisar i bloquejar import superior al pendent sense actualitzacio silenciosa |
| Passar pagaments / conciliacio SIF | Mostrar idempotencia de referencia bancaria repetida o fallback factura/data/import/banc |
| Passar pagaments / conciliacio SIF | Afegir variants visibles per pack, grup, regal i pagament fraccionat |
| Passar pagaments / conciliacio SIF | Guardar log/avis quan una factura historica no SIF es localitza per NUM FACTURA |
| Generar factura abans de pagar | Mostrar banner: factura real emesa abans de cobrament |
| Generar factura abans de pagar | Recalcular al servidor inscripcions, curs, edicio, receptor i import abans d'emetre |
| Generar factura abans de pagar | Bloquejar doble clic/reintent i retornar mateixa factura per idempotencia |
| Generar factura abans de pagar | Mostrar linies fiscals estructurades abans de confirmar |
| Generar factura abans de pagar | Desactivar o substituir URLs individuals quan queda cobert per factura empresa/responsable |
| Generar factura abans de pagar | Mostrar resultat post-SIF: numero, estat AEAT, estat cobrament, PDF i QR |

### Llistes que jo recol.locaria o revisaria

| Situacio | Que he vist | Proposta |
|---|---|---|
| Llistes antigues i noves conviuen | Hi ha patrons tipus `Intranet - Passar pagaments` i `Intranet · Passar pagaments` | Fusionar o arxivar les antigues quan la nova ja tingui les targetes bones |
| `Disseny d'interficie i pantalles` es molt gran | Te centenars de targetes i pot fer de calaix general | Moure targetes cap a llistes de pantalla/flux concret |
| Targetes generiques de pantalla | Ex.: documentar pantalla actual, definir UI, definir permisos | Mantenir-les si son utils, pero afegir les targetes petites de comportament concret |
| Duplicats exactes | 48 grups duplicats | Arxivar duplicats o convertir-los en checklist dins la targeta principal |
| Etiqueta duplicada/erronia | Hi ha `PANELL SIF` i tambe `PANElL SIF` | Unificar l'etiqueta amb el nom correcte |

### Recomanacio per interficies

Aquest tauler no el tancaria com a complet encara. Jo faria tres passos:

1. Afegir les 22 targetes concretes que falten.
2. Recol.locar les targetes de llistes antigues `Intranet - ...` cap a les llistes noves `Intranet · ...` o arxivar-les si son duplicades.
3. Dividir o buidar parcialment `Disseny d'interficie i pantalles`, portant les targetes accionables cap al flux real que pertoqui.

## Conclusio

Control: quasi correcte com a tauler de governanca, pero necessita neteja abans de donar-lo per bo.

Interficies: molt millor, pero encara falten peces funcionals importants. Especialment, falten targetes petites del flux real de pagaments i de factura abans de cobrament, que son just les zones on el projecte ha canviat mes respecte al plantejament inicial.
