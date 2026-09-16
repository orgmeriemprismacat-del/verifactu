# 40 - Auditoria de buits entre fitxes, codi i base de dades

Data de tall: 16/09/2026.

## 1. Conclusió executiva

Sí, faltaven responsabilitats funcionals importants. El resultat anterior
validava que existien fitxers i apartats, però no que cada fitxa contingués les
regles específiques del negoci. La declaració correcta és:

```text
125 fitxes estructurades != 125 especificacions funcionals tancades
185 targetes mare mapades != contingut de 185 targetes incorporat
21 apartats per fitxa != dades, variants i decisions específiques resoltes
```

La revisió ha afegit UC-106..UC-112, una capa de dades per a l'operació
comercial prèvia a factura/pagament i els camps fiscals normatius que no
existien físicament a les migracions. Tot el paquet continua `NO-GO`: no s'ha
integrat encara als canals ni s'ha provat en MySQL/preproducció.

## 2. Abast i mètode

S'han contrastat:

- els documents 01, 05, 24, 31, 33, 35, 38 i 39;
- les 125 fitxes generades i el seu generador;
- tres exports locals de Trello amb llista `Fitxes mare`;
- les set carpetes de `codi-drive` (6.848 fitxers, 1.920 PHP);
- les migracions, repositoris i proves de `sif/`;
- de forma focalitzada, els fluxos reals d'inscripció, tastet, descompte
  d'amics, subvenció, docent novell, preus/descomptes i callbacks.

No s'ha carregat el JSONL complet de `xat-original`. Tampoc s'ha modificat cap
fitxer PHP de les aplicacions llegades.

## 3. Problema de qualitat de les fitxes anteriors

L'anàlisi automàtica de les 118 fitxes anteriors va trobar:

| Indicador | Resultat | Lectura correcta |
| --- | ---: | --- |
| Claims totals | 3.304 | Volum de text, no cobertura. |
| Claims normalitzats únics | 294 | Reutilització molt alta. |
| Claims genèrics repetits a totes les fitxes | 14 | Flux, errors, proves i notificacions gairebé idèntics. |
| Fitxes en `NEEDS_DECISION` | 118/118 | Cap estava tancada funcionalment. |
| Fitxes amb `[PROPOSTA]` i `[PENDENT]` | 118/118 | El generador havia d'anomenar-les esborranys. |
| Fitxes que citaven l'evidència Trello | 0/118 | El mapatge era extern a la fitxa. |
| Fitxes amb IVA/base/exempció concreta | 0/118 | Omissió fiscal transversal. |

Correcció aplicada:

- l'estat passa a `STRUCTURED_DRAFT_NEEDS_CASE_REVIEW`;
- totes les fitxes citen l'auditoria Trello i expliciten que el mapatge no
  substitueix la revisió de contingut;
- els casos fiscals declaren les dades fiscals mínimes a congelar;
- UC-106..UC-112 incorporen inputs i regles específiques observades al codi;
- l'índex ja no presenta els documents generats com a “anàlisi completa”.

## 4. Cobertura Trello corregida

| Export | Llista | Targetes obertes |
| --- | --- | ---: |
| Tauler 2 — casos d'ús/anàlisi funcional | `Fitxes mare` | 145 |
| Tauler 3 — desenvolupament SIF/BD/API | `Fitxes mare` | 10 |
| Tauler 6 — SIF `pay.prisma.cat` | `Fitxes mare` | 30 |
| **Total** | | **185** |

En el tauler 2 hi ha 145 descripcions (49.578 caràcters), 5 checklists i 36
ítems; 31 descripcions declaren explícitament algun “Falta”. El document 39
conserva el mapatge individual de les 145 i afegeix les 40 fitxes tècniques.

Pendent real: transformar cada descripció/checklist rellevant en claims
específics, amb font, estat i bloqueig, i validar-los amb negoci. El recompte
185/185 només demostra inventari i classificació.

## 5. Fluxos funcionals que faltaven

| Cas nou | Evidència directa | Buit anterior |
| --- | --- | --- |
| UC-106 Reserva/inscripció abans de pagar | `ajax/enviarInscripcio.php`, classes `Inscripcio*` | Es saltava directament de venda a factura/pagament. |
| UC-107 Duplicat d'inscripció | comprovacions prèvies dels endpoints d'inscripció | No es distingia duplicat acadèmic d'idempotència fiscal. |
| UC-108 Tastet/repte gratuït | `InscripcioTastet.php`, `ajax/enviarInscripcioTastet.php`, `inscripcions_reptes` | Flux gratuït, accés temporal i mailing sense cas propi. |
| UC-109 Curs subvencionat | branca `tipusCurs == 'S'` a `ajax/enviarInscripcio.php` | Preu zero a l'alumne sense decisió sobre finançador/receptor/factura. |
| UC-110 Descompte d'amics | `DescompteAmic.php`, dues `inscripcions`, `respGrups`, un `IDPAG` | Dos participants/cursos i un pagador no representats. |
| UC-111 Docent novell | `novell`, `recent_titulat` i promesa de codi futur | Evidència, validació i dret comercial futur no modelats. |
| UC-112 Snapshot pre-TPV | preu/descompte/IDPAG calculats abans del pagament | Faltava congelar places, preu, descompte i fiscalitat abans del callback. |

Altres variants que continuen requerint revisió individual:

- curs CDD/acreditació, `cursEsRepte` i edicions especials;
- reserva de plaça, aforament complet, llista d'espera i edició cancel·lada;
- tots els valors reals de `TIPUS_DESC`/`VALID_DESC` i la documentació que
  exigeix cada descompte;
- punts d'entrada actius versus còpies datades o de prova;
- efectes acadèmics posteriors: accés al curs, superació, certificat i baixa.

## 6. Buit de centralització a `pay.prisma.cat`

La carpeta candidata `pay-prisma-cat-canvis-verifactu` té 23 fitxers PHP,
mentre que `web-actual` en té 443. La candidata cobreix principalment classes
de pagament/callback automàtic per curs, taller, grup, regal i pack, però no és
un inventari de tots els punts actius d'inscripció i gestió.

Entre els elements sense substitut equivalent confirmat hi ha:

- `PagamentCurs.php` i `PagamentRegal.php` no automàtics;
- endpoints d'inscripció de curs, afiliat, pack, taller, tastet i regal;
- `DescompteAmic.php`, `DescompteGrup.php` i validacions/promocions;
- pàgines OK/KO, consulta de producte/preu i regeneració d'enllaços;
- operacions de la intranet que encara escriuen camps econòmics o fiscals.

Conclusió: centralitzar no significa copiar cinc callbacks. Cada canal ha de
deixar de decidir efectes fiscals, crear una comanda al SIF i registrar intent,
decisió i resultat.

## 7. Buits de dades fiscals

La documentació ja exigia emissor, receptor, sèrie/número, dates, tipus de
factura, rectificació, descripció, base, règim, tipus i quota d'IVA, inversió
del subjecte passiu, recàrrec d'equivalència, causa d'exempció/no subjecció,
hash anterior, identificació/versió/productor del SIF, zona horària i QR.

Les migracions anteriors només materialitzaven una part. La migració additiva
`2026_09_16_000004_add_commercial_operation_and_fiscal_fields.sql` incorpora:

- emissor, descripció i tractament fiscal complet a factura/línies;
- identificació del SIF, versió, productor i zona horària al registre;
- dades de retry/resposta AEAT a `fiscal_queue`;
- `STORAGE_REF`, versió, URL/hash QR i text VERI*FACTU als documents;
- `commercial_operation` per separar operació/inscripció de factura/pagament;
- `commercial_operation_party` per pagador, participant i receptor;
- `discount_validation` per regla, evidència i resultat;
- `payment_link` per token hash, caducitat, revocació i substitució.

La migració no s'ha aplicat. Les columnes fiscals noves són temporalment
nullable per no trencar els writers actuals; abans del `GO` cal backfill,
validació `NOT NULL` on pertoqui i adaptació de repositoris/serveis.

## 8. Fronteres de BD encara pendents

No s'ha fingit que les decisions següents estiguin tancades:

- `notificacions` visible a intranet versus `notification_outbox` tècnic;
- ubicació final de `motiu_canvi`, `canvi_curs`, `baixa_inscripcio` i
  `reclamacio_pagament` entre BD intranet i BD SIF;
- incorporació de `redsys_callback_queue`, que existeix a la branca asíncrona
  però no a les migracions base;
- estructura real de promocions, regals, packs i descomptes de grup;
- política de retenció i minimització d'evidències sensibles.

Aquestes decisions són de desplegament i propietat de dades; no s'han de
resoldre duplicant taules sense responsable.

## 9. Estat de completitud per capes

| Capa | Estat | Què falta |
| --- | --- | --- |
| Inventari de casos | `[AMPLIAT/PARCIAL]` | Validar variants actives i incorporar contingut de targetes. |
| Fitxes de 21 apartats | `[ESBORRANY ESTRUCTURAT]` | Regles, exemples, errors i proves particulars per cas. |
| Diagrames/traçabilitat | `[AMPLIAT/PARCIAL]` | Actualitzar-los amb implementació real i punts d'entrada actius. |
| Esquema SQL | `[DISSENY MATERIALITZAT/NO APLICAT]` | Executar, provar i integrar migracions/repositoris. |
| Ledger de pagaments | `[ESQUEMA I REPOSITORI/PARCIAL]` | Fer passar tots els canals/gateways i consultes. |
| Codi candidat `pay.prisma.cat` | `[NO-GO]` | Adaptadors, operació comercial, fiscalitat, cues, permisos i proves. |
| Producció | `[NO ACREDITADA]` | Snapshot real, preproducció, evidències i aprovació. |

## 10. Criteri de tancament

No es tornarà a declarar “no falta res” per recompte de fitxers. Per cada cas
cal demostrar:

1. origen (targeta, codi, decisió o norma) incorporat claim a claim;
2. actors, permisos, dades, variants i errors específics;
3. classificació fiscal/econòmica aprovada;
4. taules/camps, propietari de BD i política d'immutabilitat;
5. idempotència, concurrència i auditoria des de tots els canals;
6. pantalla/API/worker final i retirada de l'escriptura llegada;
7. proves amb dades representatives, preproducció i evidència;
8. absència de decisions bloquejants.

Fins aleshores, el catàleg és útil per treballar i estimar, però no acredita
completitud funcional ni preparació productiva.
