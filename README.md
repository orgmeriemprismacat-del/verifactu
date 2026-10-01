# VERI*FACTU / SIF PrisMa

Repositori tècnic i documental del projecte de centralització de facturació, cobrament i traçabilitat fiscal de PrisMa, amb el **SIF de `pay.prisma.cat`** com a nucli de confiança.

Aquest repositori ja no és només un “projecte pont” documental: conté **codi executable, model de dades, migracions, APIs, workers, proves, documentació funcional, UML, auditories, evidències i còpies legacy de contrast**.

> **Estat:** projecte en desenvolupament i auditoria. La presència de codi, una fitxa o una prova no implica desplegament a producció ni autorització de posada en marxa.

## Objectiu

El projecte busca substituir progressivament les escriptures fiscals i de cobrament disperses entre web, intranets i scripts legacy per una frontera centralitzada:

```text
web / ecommerce / intranet / portals
                 |
                 v
        adaptadors autenticats
                 |
                 v
        SIF · pay.prisma.cat
  factures · cobraments · Redsys
  documents · traça · incidències
                 |
                 v
          BD fiscal / AEAT
```

Principis: immutabilitat fiscal, rectificació en lloc d'edició destructiva, numeració central, cadena de hash, idempotència amb comparació de payload, cues i reintents, auditoria append-only i reconciliació amb sistemes legacy.

## Fonts de veritat

| Àmbit | Font principal |
| --- | --- |
| Codi SIF executable | `sif/` |
| Model físic | `sif/database/migrations/` |
| Proves executables | `sif/tests/` i workflows de `.github/` |
| Estat i decisions del projecte | `00-control/` |
| Documentació funcional i fiscal | `documentacio/` |
| Fitxes funcionals UC | `documentacio/06-fitxes-funcionals/` |
| UML i auditories UC | `documentacio/07-uml-integrat/` |
| Evidències versionades | `documentacio/09-evidencies/` i `documentacio/09-proves-qa/` |
| Codi anterior per contrast | `codi-drive/` |
| Planificació de treball | Trello en directe; el repo només en conserva regles i mapes no sensibles |

## Estructura del repositori

- `sif/`: **nucli executable** del SIF: domini, serveis, repositoris, API, scripts, migracions i proves.
- `documentacio/`: especificació funcional, compliment AEAT, arquitectura final, governança, fitxes, UML, QA i evidències.
- `00-control/`: estat del projecte, decisions, planificació, auditories i criteris de seguiment.
- `codi-drive/`: còpies de sistemes actuals/històrics per contrastar integració i migració; **no és el nou SIF desplegable**.
- `.github/`: automatització CI i controls del repositori.

## Estat: com s'ha de llegir

Per a qualsevol cas d'ús o component s'han de separar almenys cinc dimensions:

1. **Documentat** — hi ha requisit, fitxa o decisió.
2. **Implementat** — existeix codi executable integrat.
3. **Provat** — hi ha prova automatitzada o validació reproduïble.
4. **Evidenciat** — el resultat està vinculat a commit/CI/preproducció o evidència conservada.
5. **Desplegat** — s'ha aplicat a l'entorn corresponent.

No s'ha de deduir un estat dels altres. Una fitxa completa no prova implementació; una suite verda no acredita producció; un `GO` tècnic no és una autorització operativa.

## Casos d'ús i paquet documental objectiu

El catàleg manté **142 IDs/variants UC canònics**. Cada UC ha de poder traçar, quan aplica:

```text
UC-XXX
├── fitxa funcional
├── fitxa integrada / cas d'ús ACTUAL i FINAL
├── diagrama de classes ACTUAL / FINAL
├── diagrama de seqüència ACTUAL / FINAL
├── activitats ACTUAL / FINAL per pàgina o apartat
├── auditoria i matriu de traçabilitat
├── codi / migracions / API afectades
├── proves
└── evidències
```

L'objectiu no és fabricar diagrames perquè sí: cada peça ha de distingir **què existeix avui**, **què és el disseny final** i **què queda pendent**.

## Desenvolupament i proves

Punt d'entrada tècnic: [`sif/README.md`](sif/README.md).

Per a proves locals, migracions i preflight: [`sif/tests/README.md`](sif/tests/README.md).

Els resultats de CI i les evidències datades són fotografies d'un commit concret. Per decidir si un canvi es pot integrar o desplegar, cal contrastar sempre el **commit actual** amb els checks corresponents.

## Documentació

Punt d'entrada: [`documentacio/README.md`](documentacio/README.md).

Les carpetes `*_2026-..`, documents datats i snapshots es conserven com a història/evidència. No substitueixen els documents vius sense una indicació explícita.

## Control del projecte

Punt d'entrada: [`00-control/README.md`](00-control/README.md).

Trello és la font de treball en directe. Els README de control no han d'intentar congelar recomptes massius que queden obsolets: han de descriure taxonomia, propietat de les tasques, traçabilitat i criteris.

## Legacy

[`codi-drive/README.md`](codi-drive/README.md) documenta les còpies de web, intranets i altres sistemes. Aquestes còpies serveixen per entendre comportament actual, detectar mutacions que s'han de retirar i validar la transició al SIF.

**Legacy no és autoritat del disseny final** i no s'ha de desplegar des d'aquest directori.

## Criteri de tancament

Una peça no es considera completa només perquè existeixi. El tancament real exigeix, segons l'abast:

- requisit i decisions resoltes;
- codi integrat a `main`;
- model/migracions coherents;
- permisos i seguretat verificats;
- proves unitàries/integració/E2E pertinents;
- evidència vinculada al commit;
- documentació i UML actualitzats;
- preproducció quan sigui necessària;
- i, separadament, decisió formal de desplegament/activació.

## Històric del projecte pont

Els mecanismes antics de “xat pont”, recuperació de converses i snapshots es mantenen a `00-control/` com a **històric de gestió del coneixement**. Ja no defineixen la identitat principal del repositori.
