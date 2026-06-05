# Auditoria parcial Trello V9 - pujada en curs

Data: 2026-06-05

Aquest informe és una fotografia parcial. Meriem encara està pujant targetes, per tant aquests números no s'han de llegir com a estat final ni com a llista definitiva de mancances.

## Fonts revisades

| Font | Tipus |
|---|---|
| `C:/Users/Usuario/Downloads/verifactu/trello_v10_reimport_canonical_labels/targetes_v9_CANON_LABELS.json` | Fitxer canònic V9 amb 7.164 targetes preparades |
| `C:/Users/Usuario/Downloads/xX0INm3z - verifactu-sif-control-del-projecte.json` | Export Trello Control |
| `C:/Users/Usuario/Downloads/rSn129mL - verifactu-sif-casos-dus.json` | Export Trello Casos d'ús |
| `C:/Users/Usuario/Downloads/9WoqDKQd - verifactu-sif-desenvolupament-sif-bd-i-api.json` | Export Trello Desenvolupament SIF, BD i API |
| `C:/Users/Usuario/Downloads/UoFWfk2t - verifactu-sif-intranet-interficie-i-notificacions.json` | Export Trello Intranet, interfície i notificacions |
| `C:/Users/Usuario/Downloads/1hh9pLmr - verifactu-sif-proves-entorns-i-produccio.json` | Export Trello Proves, entorns i producció |

## Resum curt

- Ara sí que hi ha 5 Trellos, no 4.
- El fitxer V9 té 7.164 targetes i totes tenen etiquetes.
- Els 5 exports actuals sumen 5.917 targetes, 5.916 obertes i 1 tancada.
- El camp `import_status` del fitxer V9 encara diu `NO_PUJAT` a totes les targetes, així que no serveix per saber què està pujat.
- La comparació útil és per nom normalitzat de targeta contra els exports actuals.
- Control està complet respecte al V9 i conté targetes addicionals/antigues.
- Casos d'ús està molt avançat però encara falten 289 targetes V9 per nom.
- Desenvolupament, Intranet i Proves encara no reflecteixen el gruix del V9; els exports actuals semblen tenir targetes antigues, V16/V17 o de transició.
- El V9 està molt més alineat amb el flux SIF correcte que les bases antigues: `issueInvoice()`, `registerPayment()`, `payment_transaction`, `payment_allocation`, `fact_rels`, idempotència, `DS_ORDER`, `redsys_notifications`, `EMESA_ABANS_COBRAMENT` i `credit_balance`.

## Estat per tauler

| Tauler V9 | Targetes V9 | Export obert | Coincideixen per nom | Falten del V9 | Targetes extra a export | Lectura |
|---|---:|---:|---:|---:|---:|---|
| Trello 1 - Control i documentació | 1.002 | 2.168 | 1.002 | 0 | 143 | V9 complet al tauler. Hi ha moltes targetes extra o duplicades antigues. |
| Trello 2 - Casos d'ús | 2.527 | 3.006 | 2.238 | 289 | 405 | Pujada molt avançada però no completa. |
| Trello 3 - Desenvolupament SIF, BD i API | 1.291 | 346 | 2 | 1.289 | 344 | Aquest tauler encara no correspon al V9, o falta pujar-ne gairebé tot. |
| Trello 4 - Intranet, interfície i notificacions | 1.248 | 186 | 2 | 1.246 | 184 | Aquest tauler encara no correspon al V9, o falta pujar-ne gairebé tot. |
| Trello 5 - Proves, entorns i producció | 1.096 | 210 | 2 | 1.094 | 208 | Aquest tauler encara no correspon al V9, o falta pujar-ne gairebé tot. |

Nota: `Targetes extra a export` no és igual a `Export obert - Coincideixen`, perquè la comparació és per nom normalitzat i els exports tenen duplicats per títol. Per detectar duplicats reals cal comparar títol + llista + descripció + origen/paquet.

## Duplicats per títol als exports

| Tauler exportat | Obertes | Títols únics | Grups duplicats | Targetes extra per duplicació de títol |
|---|---:|---:|---:|---:|
| Control del projecte | 2.168 | 1.114 | 523 | 1.054 |
| Casos d'ús | 3.006 | 2.516 | 349 | 490 |
| Desenvolupament SIF, BD i API | 346 | 324 | 21 | 22 |
| Intranet, interfície i notificacions | 186 | 173 | 13 | 13 |
| Proves, entorns i producció | 210 | 174 | 34 | 36 |

Els duplicats de títol no són sempre incorrectes: alguns poden venir de targetes mare, proves, programació o versions diferents. Però Control i Casos necessiten una revisió específica perquè el volum de duplicats per nom és alt.

## Duplicats ja existents al V9

| Tauler V9 | Targetes | Títols únics | Grups duplicats | Targetes extra per duplicació de títol |
|---|---:|---:|---:|---:|
| Control i documentació | 1.002 | 991 | 11 | 11 |
| Casos d'ús | 2.527 | 2.455 | 62 | 72 |
| Desenvolupament SIF, BD i API | 1.291 | 1.277 | 14 | 14 |
| Intranet, interfície i notificacions | 1.248 | 1.180 | 56 | 68 |
| Proves, entorns i producció | 1.096 | 1.092 | 4 | 4 |

Conclusió: el V9 ja conté alguns duplicats deliberats o tolerables, però els exports tenen molts més duplicats per títol, sobretot Control i Casos. Això apunta a targetes antigues coexistint amb les noves o a pujades repetides.

## Llistes V9 pendents més visibles

### Casos d'ús

| Llista V9 | Targetes pendents per nom |
|---|---:|
| Casos d'ús parcials | 103 |
| Casos validats | 67 |
| Decisions que no són fluxos | 44 |
| Casos d'ús per pantalles intranet | 24 |
| Revisió fina - targetes a crear o dividir | 24 |
| Disseny funcional i tècnic - diagrames | 12 |
| Casos d'ús pendents de fitxa funcional | 11 |
| Casos d'ús pendents | 2 |

### Desenvolupament SIF, BD i API

| Llista V9 | Targetes pendents per nom |
|---|---:|
| BD i modelatge | 617 |
| Serveis SIF i API | 393 |
| pay.prisma.cat / panell SIF backend | 137 |
| Integració Redsys backend | 93 |
| Preparat per programar | 11 |
| Bloquejat per decisió | 9 |
| PDF actual i futur PDF SIF | 8 |
| Programació pendent - Pla tècnic SIF per fases | 8 |

### Intranet, interfície i notificacions

| Llista V9 | Targetes pendents per nom |
|---|---:|
| Disseny d'interfície i pantalles | 354 |
| Sistema de notificacions | 231 |
| Correus i plantilles | 165 |
| Preparat per programar | 135 |
| Pagaments, morositat i gestió de cursos | 69 |
| Programació pendent - intranet | 58 |
| Validació de descomptes | 43 |
| Pendent de provar | 33 |

### Proves, entorns i producció

| Llista V9 | Targetes pendents per nom |
|---|---:|
| Go-no-go i evidències | 452 |
| Entorns i desplegament | 343 |
| Pendent de provar | 114 |
| Testing i validació | 64 |
| Preparat per programar | 41 |
| Programació pendent - serveis SIF | 15 |
| Programació pendent - Infraestructura BD i domini | 10 |
| Programació pendent - Tests suport i unitat | 7 |

## Etiquetes

| Font | Targetes sense etiqueta |
|---|---:|
| V9 canònic | 0 de 7.164 |
| Control exportat | 30 de 2.168 obertes |
| Casos d'ús exportat | 8 de 3.006 obertes |
| Desenvolupament exportat | 1 de 346 obertes |
| Intranet exportat | 0 de 186 obertes |
| Proves exportat | 2 de 210 obertes |

Les targetes sense etiqueta dels exports són sobretot targetes antigues, separadors o targetes de transició. No sembla un problema del V9.

## Flux SIF correcte al V9

S'han buscat marcadors del flux SIF actual dins del V9:

| Marcador | Targetes V9 |
|---|---:|
| `issueInvoice()` | 111 |
| `registerPayment()` | 103 |
| `payment_transaction` | 51 |
| `payment_allocation` | 54 |
| `fact_rels` | 74 |
| idempotència / `IDEMPOTENCY_KEY` | 205 |
| `DS_ORDER` / `Ds_Order` | 40 |
| `redsys_notifications` | 28 |
| `EMESA_ABANS_COBRAMENT` / factura abans de cobrament | 138 |
| `credit_balance` / saldo | 204 |

També s'han buscat patrons antics:

| Patró antic | Targetes V9 |
|---|---:|
| `HASH_I` | 0 |
| `REGSITROS_FACT` / `REGISTROS_FACT` | 0 |
| `GUARDAR DADE` | 0 |
| `PASSAR PAGAMENT PER` / `PASSSAR PAGAMENT` | 0 |
| `GENERAR CODI QR` / `GENERACIO FACTURA PDF` com a repetició antiga per tipus | 0 |

Conclusió: el V9 ja està molt més ben orientat que les targetes antigues. No sembla que el problema sigui que el V9 mantingui el flux vell; el risc actual és que la pujada parcial conviu amb targetes antigues/duplicades als Trellos.

## Rastres antics als exports

Als exports actuals només apareixen 2 coincidències amb `HASH_I` o `PASSAR PAGAMENT PER`, totes dues a Control:

- `Decisió presa: targetes antigues repetides es transformen, no es copien literalment`
- `Documentar transformació de targetes antigues a model SIF`

Això és correcte: són mencions de control per explicar que el model antic es transforma, no targetes que perpetuïn el flux antic.

## Lectura provisional

1. No s'ha de reduir volum. El volum és necessari perquè hi ha molts casos, proves, BD, pantalles i traspàs.
2. El V9 sí que té etiquetes i taulers. El que falta ara és acabar la pujada i després reconciliar duplicats/antigues.
3. Control ja conté tot el V9 i, a més, moltes targetes antigues o duplicades. Aquest tauler necessita neteja després de completar la importació.
4. Casos d'ús està força pujat, però encara falten 289 targetes del V9. No s'ha de jutjar com final.
5. Desenvolupament, Intranet i Proves encara estan clarament pendents respecte al V9. No convé auditar-los com a incomplets fins que acabi la pujada.
6. El flux correcte no és el diagrama lineal de pagament. El V9 ja reflecteix bastant bé el flux SIF: decisió fiscal central, `issueInvoice()`, `registerPayment()`, pagaments separats, idempotència, BD SIF i proves.

## Propera auditoria quan acabi la pujada

Quan Meriem indiqui que ha acabat de pujar targetes, cal repetir aquesta auditoria amb aquests criteris:

1. Comparar V9 contra els 5 exports finals per `external_id` si existeix a la descripció o per títol + llista + descripció.
2. Detectar targetes V9 no pujades.
3. Detectar targetes exportades que no són V9.
4. Separar targetes antigues a arxivar de targetes noves legítimes.
5. Revisar duplicats per títol, però només marcar-los com a problema si també coincideixen llista i descripció.
6. Revisar que totes les targetes tinguin etiquetes canòniques.
7. Revisar que cap targeta operativa torni al flux antic `pagament -> factura -> PDF -> correu` sense decisor SIF.
8. Generar una taula final per targeta: conservar, reescriure, fusionar, dividir, arxivar o pendent de decisió.
