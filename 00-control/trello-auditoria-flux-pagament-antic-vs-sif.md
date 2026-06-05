# Auditoria ▶ flux antic de pagament vs SIF actual

Generat: 2026-06-03

Objectiu: revisar els esquemes antics de pagament aportats i comprovar quin buit generaven a les targetes Trello. Els esquemes antics descriuen un flux lineal: pagament, verificacio de dades, confirmacio, TPV o gestio, generacio de factura, PDF, correu i guardat del document. El sistema actual de VERI*FACTU/SIF necessita una matriu de decisions mes complexa.

## Esquema antic detectat

- El pagament i la factura apareixen gairebe com una mateixa operacio.
- La confirmacio manual es tracta com un pas previ simple.
- El PDF, el QR i el correu apareixen despres de generar factura, pero no queda prou separat el snapshot fiscal.
- Els reintents es tracten com un bucle d'enviament, sense separar idempotencia, cua fiscal, lock i incidencia.
- No es veuen prou els casos de factura ja existent, cobrament posterior, pagament parcial, rectificativa, saldo, botiga/SL, transferencia validada o dades historiques.

## Sistema actual que s'ha de reflectir

El flux actual no pot ser unic. Cal separar:

- Origen: web TPV, intranet, transferencia, Redsys, factura abans de cobrament, botiga, casos historics.
- Estat fiscal: sense factura SIF, amb factura SIF existent, factura pendent, rectificativa necessaria, no facturable, incidencia.
- Accio SIF: `issueInvoice()`, `registerPayment()`, rectificativa, saldo/assignacio, incidencia o cap accio fiscal.
- Dades: `payment_transaction`, `payment_allocation`, `fact_rels`, factura, linies, registre fiscal, cua fiscal, documents i notificacions.
- Operacio: confirmacio d'Adam/Pablo, permisos server-side, avisos a intranet, reprocessament segur.
- Proves: idempotencia, reintents, duplicats, pagament parcial/posterior i canals diferents.

## Buits que generava Trello si es mantenia el flux antic

- Targetes massa grans o massa lineals per explicar casos reals.
- Risc de duplicar factura en reintents o retorns Redsys.
- Risc de crear factura nova quan nomes toca registrar cobrament.
- Risc de consumir numeracio abans de validar idempotencia.
- Risc de generar PDF/QR des de dades vives.
- Risc de barrejar Associacio, SL i botiga.
- Risc de no veure les decisions pendents amb Adam/Pablo abans de programar.

## Targetes afegides

S'han afegit **32 targetes petites**, de TPR-13246 a TPR-13277.

| Bloc | Targetes |
|---|---:|
| CONTROL | 3 |
| ADAM/PABLO | 3 |
| PAGAMENT WEB | 3 |
| PAGAMENT INTRANET / TRANSFERENCIA | 3 |
| FACTURA ABANS COBRAMENT | 1 |
| RECTIFICATIVES | 1 |
| BOTIGA LLIBRES | 1 |
| BD SIF | 3 |
| SIF | 3 |
| REDSYS / PAGAMENTS | 3 |
| DOCUMENTS FISCALS | 2 |
| NOTIFICACIONS / ERRORS | 2 |
| PERMISOS | 1 |
| PROVES | 3 |

## Criteri aplicat

- No s'ha substituit el diagrama antic; s'ha marcat com a referencia historica funcional.
- No s'han afegit targetes grans de resum; cada targeta nova representa una decisio, cas, implementacio o prova concreta.
- Les targetes noves mantenen el separador `▶`.
- Les targetes noves no mencionen cap eina externa ni conversa; descriuen feina i risc del projecte.
- La botiga i la separacio Associacio/SL es mantenen com a decisio pendent abans de desenvolupar.

## Recomanacio

Abans de generar el JSON final per l'API de Trello, cal una passada de normalitzacio que:

- arxivi o substitueixi les targetes antigues que nomes repliquen el flux lineal;
- mogui les targetes pendents a llistes finals normalitzades;
- conservi les noves targetes de flux complex com a base per implementar i provar el SIF.
