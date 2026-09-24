# Control vigent dels taulers Trello — VERI*FACTU / SIF

**Reorganització:** 2026-09-24. Aquest és el nou punt d'entrada al control dels taulers; substitueix els inventaris massius i auditories de targetes basats en exports antics de juny. Els 9 taulers originals romanen actius, i s'han creat 3 taulers nous privats.

## Mapa de taulers i fotografia de targetes obertes

| Codi | Tauler Trello | Registre de control | Llistes | Targetes obertes | Propietat principal |
|---|---|---|---:|---:|---|
| 01 | [Control del projecte](https://trello.com/b/SvAMw72z) | [Fitxer](01-control-projecte.md) | 104 | 3006 | decisions, riscos, planificació i dependències |
| 02a | [Casos d'ús i anàlisi funcional](https://trello.com/b/hQ2UjYEq) | [Fitxer](02a-casos-us.md) | 69 | 3630 | casos d'ús, variants i actors |
| 02b | [Fitxes funcionals i documentació de casos](https://trello.com/b/o2B8Hk5P) | [Fitxer](02b-fitxes-funcionals.md) | 60 | 1910 | fitxa mare, regles i acceptació |
| 03 | [Desenvolupament SIF, BD i API](https://trello.com/b/yP3igQoz) | [Fitxer](03-desenvolupament-sif-bd-api.md) | 40 | 2945 | nucli fiscal, models, repositoris i API compartida |
| 04 | [Intranet, interfície i notificacions](https://trello.com/b/3EQ4P4iV) | [Fitxer](04-intranet-interficie-notificacions.md) | 171 | 4500 | pantalles, rols visibles, correus i notificacions |
| 05 | [Proves, entorns i producció](https://trello.com/b/1lPFsrKi) | [Fitxer](05-proves-entorns-produccio.md) | 190 | 3956 | tests, evidències, preproducció i go/no-go |
| 06 | [SIF a pay.prisma.cat](https://trello.com/b/R47Ejs0b) | [Fitxer](06-sif-pay-prisma.md) | 159 | 2964 | panell i serveis propis de pay |
| 07 | [Pagaments Redsys i conciliació](https://trello.com/b/SgBqwmaR) | [Fitxer](07-pagaments-redsys-conciliacio.md) | 10 | 129 | passarel·la, callbacks, intent i conciliació |
| 08 | [Documentació, manuals i auditoria](https://trello.com/b/fBv1UTYz) | [Fitxer](08-documentacio-manuals-auditoria.md) | 19 | 1160 | documents, manuals i dossier AEAT |
| 09 | [Web, ecommerce i checkout](https://trello.com/b/ie8YOSQi) | [Fitxer](09-web-ecommerce-checkout.md) | 7 | 1 | compra, preus, formularis, URL de pagament |
| 10 | [Migració, històric i compatibilitat legacy](https://trello.com/b/VIBLvoO2) | [Fitxer](10-migracio-historic-legacy.md) | 7 | 1 | migració, convivència, compatibilitat i històric |
| 11 | [Integració AEAT i registre fiscal](https://trello.com/b/dWueO7zI) | [Fitxer](11-aeat-registre-fiscal.md) | 7 | 1 | registre fiscal, transport AEAT i resposta |

**Total en la fotografia:** 24.200 targetes obertes als 9 taulers originals; 3 targetes PANELL addicionals als taulers nous (24.203 en 12 taulers). És un recompte de targetes obertes, NO de tasques pendents, úniques ni validacions completades. No inclou targetes arxivades. L'inventari cobreix totes les llistes obertes via paginació a data 2026-09-24.

## Regla de propietat única

Cada **acció executable** té una sola targeta propietària. El cas d'ús i la fitxa funcional viuen al 02a/02b; la implementació general al 03, especialitzada als 04/06/07/09/10/11; la prova i l'evidència al 05; el document final al 08; la decisió i risc transversal al 01. A cada dimensió hi pot haver targeta pròpia quan representa un resultat diferent, sempre amb enllaços creuats i un identificador de cas/paquet estable. No comptar l'existència de targetes com a implementació ni prova.

En particular: 03 = nucli fiscal/API compartida; 06 = panell, servidor i integració de pay.prisma.cat; 07 = cicle de pagament i conciliació Redsys; 09 = checkout/web fins a iniciar el pagament; 10 = migració/legacy; 11 = construcció i transmissió del registre AEAT; 08 = compliment documental; 05 = validació executable. No fer còpies de les mateixes tasques entre aquests taulers.

## Com revisar i actualitzar les targetes més endavant

1. Llegir **totes les pàgines** de llistes/targetes obertes dels 12 Trellos en directe; per analitzar arxivaments, consultar també les targetes arxivades.
2. Comparar per **ID de targeta**, no només títol: altes, canvi de llista, canvi de tauler, arxiu, compleció i duplicats semàntics. El títol repetit és una alerta, no prova de duplicat.
3. Contrastar el resultat amb el codi real a `main`, branques encara obertes, fitxes funcionals, proves executables i evidències. Relacionar `UC/VT -> targeta propietària -> fitxer/commit/PR -> prova -> evidència -> documentació`.
4. Proposar moviments abans de fer-los; moure la targeta existent quan correspon, sense clonar-la. No arxivar ni donar per finalitzada cap tasca només perquè hi ha una targeta anomenada de manera semblant en un altre tauler.
5. Actualitzar **la data i recomptes per llista** al fitxer de cada tauler i anotar aquí els canvis estructurals i els bloquejos oberts. Les fotografies no es mantenen automàticament sincronitzades.

## Format mínim d'una targeta que representi feina nova

- Títol concret amb verb i objecte; un resultat per targeta.
- Descripció: abast i no-abast, referència UC/VT si existeix, ruta o contracte afectat, criteri d'acceptació, dependències i enllaços a les altres dimensions.
- Estat: identificar explícitament si és **definit**, **implementat**, **provat** o **desplegat**; no deduir-ho del simple fet que la targeta existeix.
- Quan una tasca cobreix diversos casos, enllaçar-la des de tots en lloc de recrear-la per cadascun.

## Privacitat i fonts

**Aquest repositori de GitHub és públic**; els tres taulers nous són privats. Evitar-hi una còpia massiva de títols, descripcions, checklists, persones, identificadors privats o dades fiscals de targetes Trello. Els fitxers de registre guarden únicament estructura, recomptes i regles de seguiment; la font de targetes és Trello en directe. Per a la història de desenvolupament, el codi i els commits de GitHub continuen sent la font tècnica.

## Control de versions

| Data | Decisió |
|---|---|
| 2026-09-24 | Baseline complet dels 9 taulers existents, 3 taulers nous i nous fitxers de control. Pendent reconciliació semàntica targeta a targeta i traspàs sense duplicats. |
