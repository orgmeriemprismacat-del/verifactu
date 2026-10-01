# Control dels taulers Trello — VERI*FACTU / SIF

Trello és la **font de treball en directe** del projecte. Aquest README documenta la taxonomia i les regles de traçabilitat; **no manté recomptes congelats de targetes**, perquè queden obsolets ràpidament.

## Estructura actual de treball

La planificació s'ha anat especialitzant. A l'01/10/2026 hi ha taulers actius, entre d'altres, per aquestes dimensions:

| Família | Propietat principal |
| --- | --- |
| 1 | control del projecte, decisions, riscos i dependències |
| 2a | casos d'ús i anàlisi funcional |
| 2b | fitxes funcionals |
| 2c | diagrames de seqüència |
| 2d | diagrames de classes |
| 2e | diagrames d'activitat |
| 3a | desenvolupament backend i API |
| 3b | BD i modelat / especialitzacions de desenvolupament |
| 4 / 4a / 4b / 4c | intranet, alumnes, facturació/cobrament, UX i notificacions |
| 5 / 5a / 5b / 5c | proves, entorns i producció per superfície |
| 6a / 6b | panell SIF i SIF a `pay.prisma.cat` |
| 7 | pagaments, Redsys i conciliació |
| 8 | documentació, manuals i auditoria |
| 9 | web, ecommerce i checkout |
| 10 | migració, històric i compatibilitat legacy |
| 11 | integració AEAT i registre fiscal |
| 12 | operació acadèmica, inscripcions i campus |
| 13 | seguretat, permisos i privacitat |
| 14 | operació, desplegament i recuperació |
| 15 | auditories, troballes i evidències |
| 16 | portals d'alumnes |
| 17 | portals de tutors i col·laboradors |

Poden existir taulers antics, duplicats històrics o taulers de transició. Per decidir on crear una targeta nova s'ha d'usar la **taxonomia actual** i, si hi ha dubte, revisar Trello en directe.

## Regla de propietat

Una mateixa feina no s'ha de copiar entre taulers només per fer-la visible. En canvi, **resultats diferents sí que mereixen targetes separades**.

Exemple per un mateix UC:

- auditoria funcional;
- actualització de fitxa;
- diagrama de classes;
- diagrama de seqüència;
- diagrama d'activitats;
- implementació backend;
- migració BD;
- prova unitària;
- prova d'integració/E2E;
- evidència CI/preproducció;
- actualització de manual/documentació.

Són peces diferents i poden tenir targetes diferents, sempre amb referència UC/PR/commit quan existeixi.

## Targetes petites

Per representar fidelment la feina feta, es prefereixen **targetes petites, concretes i verificables** davant d'una única targeta resum.

Format recomanat:

- títol amb verb + objecte;
- UC o paquet afectat;
- què s'ha fet;
- criteri d'acceptació;
- commit/PR/fitxer o evidència;
- estat: documentat / implementat / provat / evidenciat / desplegat.

## Auditoria i troballes

Les revisions transversals, comparacions de branques, inconsistències documentals i troballes que generen accions han d'anar al tauler **15 · Auditories, troballes i evidències**.

La correcció derivada d'una troballa s'ha de registrar també al tauler propietari de la feina si és una peça diferent.

## Reconciliació amb GitHub

Per revisar una feina:

```text
UC / troballa
→ targeta
→ branca / PR / commit
→ fitxer o codi
→ prova
→ evidència
→ documentació
```

No s'ha de marcar una targeta com a feta només perquè existeixi un fitxer amb nom semblant. Cal verificar el resultat.

## Privacitat

Aquest repositori és públic. No copiar-hi massivament descripcions, checklists, persones, identificadors privats, secrets ni dades fiscals de Trello. Aquest README conserva només regles de treball i estructura general.

## Històric

Els mapes i recomptes del 24/09/2026 es consideren **snapshot històric**. La font actual de targetes i taulers és Trello en directe.
