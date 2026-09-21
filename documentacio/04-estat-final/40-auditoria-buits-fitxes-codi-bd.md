# 40 - Auditoria de buits entre fitxes, codi i base de dades

Data de tall: 16/09/2026.

## 1. Conclusió executiva

Sí, faltaven responsabilitats funcionals importants. El resultat anterior
validava que existien fitxers i apartats, però no que cada fitxa contingués les
regles específiques del negoci. La declaració correcta és:

```text
142 fitxes estructurades != 142 especificacions funcionals tancades
185 targetes mare mapades != contingut de 185 targetes incorporat
21 apartats per fitxa != dades, variants i decisions específiques resoltes
```

Les tres passades han afegit UC-106..UC-129, una capa de dades per a l'operació
comercial prèvia a factura/pagament, els seus cicles de vida i els camps
fiscals normatius que no existien físicament a les migracions. Tot el paquet
continua `NO-GO`: no s'ha integrat encara als canals ni s'ha provat en
MySQL/preproducció.

## 2. Abast i mètode

S'han contrastat:

- els documents 01, 05, 24, 31, 33, 35, 38 i 39;
- les 142 fitxes generades i el seu generador;
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

## 11. Segona passada sobre superfícies executables

La revisió posterior no s'ha limitat als endpoints d'inscripció inicials. S'ha
contrastat la superfície de 1.920 PHP, els 510 mètodes d'`Intranet.php`, els 68
de `IntranetAlumne.php` i les famílies d'escriptura més freqüents. La base
`inscripcions` apareix en 39 escriptures de 21 fitxers de `web-actual` i en 83
escriptures de la intranet; també hi ha mutacions recurrents de `factures`,
`cursos`, `aula`, `promocions`, `regal`, responsables i taules Moodle.

Això ha fet aflorar UC-113..UC-124. No són sinònims dels set casos anteriors:

| Buit | Per què necessita cicle propi |
| --- | --- |
| Alta/importació manual o en lot | Una fila acadèmica no prova cobrament; cal resultat i error per fila. |
| Canvi de curs/edició mestre | Pot afectar reserves obertes, però mai snapshots o factures emesos. |
| Aforament i llista d'espera | Requereix reserva atòmica, caducitat i alliberament, no només un `COUNT`. |
| Evidència de descompte | Conté dades sensibles i necessita custòdia, accés i retenció. |
| Promoció/dret futur | Té emissió, reserva, consum, caducitat i reversió pròpies. |
| Grup abans del cobrament | Participants, tram, places, pagador i receptor canvien abans del lock. |
| Regal/bescanvi | Compra, dret i inscripció de beneficiari són objectes i moments diferents. |
| Canvi de dades personals | El correu no substitueix expedient, aprovació i propagació. |
| Reserva caducada/repreuament | Preu o plaça antics no es poden reactivar sense nova acceptació. |
| Pack | Components necessiten línia, plaça, impost i tractament de baixa propi. |
| Factura electrònica | `E_FACT` no acredita fitxer, format, destinatari ni entrega. |
| Estat acadèmic/econòmic | Accés, Moodle i certificat consulten el deute però no poden mutar-lo. |

El catàleg passa a 129 casos numèrics i 13 variants: 142 fitxes. Totes
continuen `STRUCTURED_DRAFT_NEEDS_CASE_REVIEW`.

## 12. Segona ampliació de base de dades

La migració 000005 incorpora 12 taules additives:

- `commercial_operation_line` i `operation_line_invoice_link`;
- `capacity_reservation`;
- `discount_evidence`;
- `commercial_entitlement` i `commercial_entitlement_event`;
- `enrollment_import_run` i `enrollment_import_item`;
- `master_data_change_request` i `personal_data_change_request`;
- `electronic_invoice_delivery`;
- `academic_economic_state_event`.

La decisió de model evita crear taules diferents per cada nom comercial de
promoció o regal: `commercial_entitlement` conserva el tipus i la regla, i el
ledger append-only conserva cada transició. En canvi, importació, reserva de
places, canvi de dades i entrega electrònica es mantenen separats perquè tenen
permisos, retenció i recuperació diferents.

No s'ha aplicat la migració. La integritat sintàctica i les claus foranes s'han
de provar en MySQL amb les migracions 000001..000006, còpia de seguretat i
recuperació d'una fallada parcial.

## 13. Què encara pot faltar

Aquesta revisió redueix buits demostrables, però no autoritza dir “ja no falta
res”. Continuen pendents:

1. confirmar quines còpies, rutes, crons i virtual hosts són productius;
2. incorporar claim a claim les 185 targetes mare;
3. revisar altres famílies de mètodes de la intranet no relacionades per nom
   amb inscripció/pagament però que puguin alterar dades d'origen;
4. validar el model amb negoci, fiscalitat i protecció de dades;
5. construir serveis, adaptadors i pantalles, i retirar writers llegats;
6. provar PHP/MySQL, concurrència, permisos, preproducció i restauració.

La matriu 41 és el control viu per no tornar a confondre cas documentat amb
punt d'entrada migrat.

## 14. Tercera passada dirigida: dependències no econòmiques amb impacte

La classificació anterior encara agregava massa comportament sota “dades
personals”, “canvi d'edició” i “Moodle”. La lectura dels mètodes concrets ha
afegit cinc casos, UC-125..UC-129:

| Buit | Evidència executable | Per què no queda cobert |
| --- | --- | --- |
| Consentiment de comunicacions | `mailing.php`, `mailingNou.php`, `inscripcio_mailing.php`, `INSC_MAILING`, `mailing`, `subscriptors` | UC-43/58 envien missatges però no acrediten el permís; UC-108 només l'esmentava dins el tastet. |
| Identitat entre sistemes | comparació de correu BD/Moodle, cerques per DNI/correu i controls de duplicat | UC-120 canvia camps, però no decideix si dos identificadors pertanyen o no a la mateixa persona. |
| Estat massiu d'edició | `desarCanvisEstatEnviarMsg_PreviIniciCursos()` actualitza edició, baixa alumnes, consulta factura/pagament i avisa | UC-27 és una baixa individual i UC-114 versiona dades mestres; cap dels dos inventaria i resol tots els afectats. |
| Qualitat de l'adreça | writers a `poblacions_validar` en múltiples altes web | La cua detectada no conserva original/proposta/regla/decisió ni exclou snapshots emesos. |
| Reconciliació Prisma/Moodle | comparació massiva d'usuaris, correus, rols, cursos/aules i matrícules | UC-124 regula una decisió acadèmica; no modelava execució, ítems, autoritat per camp i resolució de divergències. |

La migració 000006 afegeix vuit taules additives:

- `communication_consent` i `communication_consent_event`;
- `external_identity_link` i `identity_conflict_case`;
- `edition_lifecycle_event` i `edition_operation_impact`;
- `address_validation_case`;
- `academic_reconciliation_item`, vinculada a `reconciliation_run`.

No s'ha creat un UC separat per a una gestió integral de drets de protecció de
dades: el codi localitzat anuncia aquests drets als peus de correu, però no
defineix un circuit executable complet. Les sol·licituds de rectificació i
propagació continuen a UC-120, amb aquesta possible ampliació d'abast marcada
per a decisió funcional.

La nova migració tampoc acredita implementació. S'ha de provar amb MySQL,
definir autoritat i retenció, migrar l'estat vigent, resoldre cues acumulades i
demostrar que cap d'aquests adaptadors modifica pagaments o factures com a
efecte lateral.
