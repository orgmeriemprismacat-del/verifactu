# Targetes Trello petites curades - VERI*FACTU PrisMa

Generat: 2026-06-03

Objectiu: substituir la base massiva anterior per una base humana, petita i revisable. Aquest fitxer no copia literalment les 4.373 targetes expandides ni les 13.277 targetes reconciliades; conserva el coneixement útil, elimina repeticions i reescriu el flux antic de pagament segons el model SIF actual.

## Criteri principal

El model final no és:

```text
pagament -> factura -> PDF -> correu
```

El model final és:

```text
canal/origen
    -> validació de dades i permisos
    -> deduplicació / idempotència
    -> decisió SIF
    -> issueInvoice(), registerPayment(), rectificativa, saldo, incidència o cap acció fiscal
    -> relacions, documents, notificacions i proves
```

Regla curta:

```text
Els canals proposen operacions.
El SIF decideix l'acció fiscal.
```

## Fonts revisades

| Font | Què aporta |
|---|---|
| `xat-original/rollout-2026-05-13T22-15-39-019e22fb-0e44-71f3-81ef-e0b9d0dcd2a7.jsonl` | Decisions inicials, BD fiscal parcial, hash chain, Redsys, IDPAG, DS_ORDER i canvi conceptual. |
| `00-control/registre-decisions.md` | Decisions consolidades del projecte i subblocs especialitzats. |
| `documentacio/00-index-i-pla/26-matriu-cobertura-casos.md` | Estat real dels casos d'ús i buits pendents. |
| `documentacio/03-canvis-pendents/04-fluxos-facturacio.md` | Fluxos fiscals, pagaments, transferències, fraccionats, saldo i rectificatives. |
| `documentacio/04-estat-final/05-model-bd-sif.md` | BD SIF, taules, relacions i separació web/intranet/fiscal. |
| `documentacio/02-context-i-estat-actual/13-mapa-bases-dades-i-taules.md` | Mapa de BDs, traspàs, migració i deutes de dades. |
| Exports Trello `VERIFACTU - WEB`, `VERIFACTU - INTRANET`, `VERIFACTU - GESTIÓ GENÈRICA`, `Verifactu / SIF` | Targetes antigues a conservar com a coneixement, no com a estructura final. |
| Imatges `PAGAMENT.png` i `PAGAMENT INTRA [VERIFACTU].png` | Flux antic de pagament que s'ha de reescriure com a referència històrica. |

## Regles de neteja de targetes antigues

| Patró antic | Decisió |
|---|---|
| Targetes repetides per tipus amb `CALCULAR HASH_I`, `GENERAR EVENT HASH_I`, `GENERAR REGISTRE INALTERABLE` | Arxivar quan existeixi la targeta genèrica del nucli SIF i les proves per cas. El hash no es programa per cada tipus; viu a `issueInvoice()`. |
| Targetes per tipus amb `GUARDAR DADE FACT_RELS` | Reescriure com a `fact_rels` genèric + prova del cas d'ús concret. |
| Targetes per tipus amb `GUARDAR FACTURA PDF SENSE TENIR ACCESS`, `GENERAR CODI QR`, `GENERACIO FACTURA PDF` | Reescriure com a documents fiscals protegits + prova per cas. |
| Targetes `PASSAR PAGAMENT PER ...` basades en el diagrama antic | Reescriure com a casos d'ús de conciliació, `registerPayment()`, rectificativa o saldo segons el cas. |
| Targetes de correu repetides per tipus | Consolidar per estat del SIF: pendent de pagar, factura emesa, PDF pendent, incidència, rectificativa, saldo o devolució. |
| Targetes `ELABORAR CASOS D'ÚS ▶ XXX` | Substituir per casos d'ús concrets, amb targeta de disseny, programació i prova quan calgui. |
| Targetes de PHP 8.4, nou servidor, pay.prisma.cat i traspàs entorn | Conservar com a infraestructura del projecte, però fora dels casos fiscals. |
| Targetes de documentació AEAT, QR, declaració i especificacions | Conservar si tenen evidència o decisió; no duplicar per cada canal. |
| Targetes tancades o en `COMPLETADA` sense evidència en repo | Moure a `Validat / tancat` només si hi ha document, captura, codi o acta; si no, `Feina feta pendent d'evidència`. |
| Separadors, targetes buides, errades de nom i duplicats literals | Arxivar o eliminar del nou Trello. |

## Taulers i llistes finals

| Tauler | Llistes |
|---|---|
| `VeriFactu / SIF - Control del projecte` | `Decisions preses`, `Decisions pendents`, `Feina feta`, `Documentació feta`, `Documentació pendent`, `A debatre amb Adam/Pablo`, `Dades pendents`, `Arxivar/substituir antic`, `Validat / tancat` |
| `VeriFactu / SIF - Casos d'ús` | `Disseny cobert`, `Falta detall`, `Pendent d'implementació`, `Pendent de proves`, `Bloquejat per decisió/dada`, `Validat / tancat` |
| `VeriFactu / SIF - Desenvolupament i proves` | `Nucli SIF`, `BD i migracions`, `Pagaments i Redsys`, `Intranet i pantalles`, `Documents fiscals i correus`, `AEAT i incidències`, `Migració i traspàs`, `Proves`, `Go-no-go`, `Validat / tancat` |

---

# Tauler 1 - Control del projecte

## Decisions preses

| ID | Targeta | Descripció curta | Etiquetes | Fonts |
|---|---|---|---|---|
| DEC-001 | Decisió presa - el SIF és la font fiscal oficial | La BD fiscal/SIF decideix factures, registres, documents, exportacions i incidències. | DECISIÓ, SIF | `registre-decisions.md`, `05-model-bd-sif.md` |
| DEC-002 | Decisió presa - web i intranet no emeten factura final | Web/intranet conserven operativa viva; el SIF emet i congela el snapshot fiscal. | DECISIÓ, ARQUITECTURA | `05-model-bd-sif.md` |
| DEC-003 | Decisió presa - `issueInvoice()` és l'únic flux que crea factura | La numeració, línies, hash, registre i cua AEAT viuen dins `issueInvoice()`. | DECISIÓ, BACKEND | `29-pla-implementacio-tecnica-sif.md` |
| DEC-004 | Decisió presa - `registerPayment()` no crea factura nova | Només registra moviment econòmic i assignació contra factura existent. | DECISIÓ, PAGAMENTS | `04-fluxos-facturacio.md` |
| DEC-005 | Decisió presa - idempotència abans de numeració fiscal | Cap retry pot consumir número ni crear duplicat abans de comprovar la clau idempotent. | DECISIÓ, RISC ALT | xat antic, `20-pla-proves-validacio-sif.md` |
| DEC-006 | Decisió presa - hash chain global per `FISCAL_ORDER` | La integritat fiscal no va per sèrie, sinó per ordre fiscal global del SIF. | DECISIÓ, HASH | xat antic, `05-model-bd-sif.md` |
| DEC-007 | Decisió presa - `IDPAG` no deduplica sol | Redsys es deduplica per `DS_ORDER`; `IDPAG` identifica origen/enllaç però pot tenir intents múltiples. | DECISIÓ, REDSYS | `04-fluxos-facturacio.md` |
| DEC-008 | Decisió presa - pagament i factura són fets diferents | Un pagament pot crear factura, pagar una factura existent, ser parcial, ser saldo o quedar en incidència. | DECISIÓ, PAGAMENTS | `04-fluxos-facturacio.md` |
| DEC-009 | Decisió presa - cap prova consumeix numeració productiva | Preproducció o mode test separat abans de go/no-go. | DECISIÓ, PROVES | `20-pla-proves-validacio-sif.md` |
| DEC-010 | Decisió presa - no editar factures SIF silenciosament | Canvis posteriors van per rectificativa, saldo, devolució o event auditat. | DECISIÓ, RISC ALT | `18-estat-final-operacio-incidencies.md` |
| DEC-011 | Decisió presa - factura abans de cobrament no és proforma | És factura real amb `EMESA_ABANS_COBRAMENT = 1`; el pagament posterior va per `registerPayment()`. | DECISIÓ, FACTURA | `13-mapa-bases-dades-i-taules.md` |
| DEC-012 | Decisió presa - `E_FACT` no vol dir emesa abans de cobrar | Factura electrònica i factura abans de cobrament són conceptes separats. | DECISIÓ, BD | `13-mapa-bases-dades-i-taules.md` |
| DEC-013 | Decisió presa - `fact_rels` és relació lògica auditada | No hi haurà foreign keys directes entre BD fiscal i web/intranet. | DECISIÓ, BD | `05-model-bd-sif.md` |
| DEC-014 | Decisió presa - imports fiscals nous en `DECIMAL` | No utilitzar `FLOAT` ni `DOUBLE` en imports fiscals finals. | DECISIÓ, BD | `05-model-bd-sif.md` |
| DEC-015 | Decisió presa - UUID complet i InnoDB al SIF final | La BD parcial antiga és context, no disseny final literal. | DECISIÓ, MIGRACIÓ | `13-mapa-bases-dades-i-taules.md` |
| DEC-016 | Decisió presa - PDF/QR/XML surten de snapshot SIF | Els documents fiscals tenen ruta protegida, hash i estat propi. | DECISIÓ, DOCUMENTS | `08-correus-i-plantilles.md`, `05-model-bd-sif.md` |
| DEC-017 | Decisió presa - correu no prova factura emesa | Només el retorn SIF amb UUID/número confirma emissió fiscal. | DECISIÓ, CORREUS | `registre-decisions.md` |
| DEC-018 | Decisió presa - `pay.prisma.cat/sif` és el panell oficial | La intranet mostra indicador/resum, però el control oficial és el panell SIF. | DECISIÓ, PANELL | `25-panell-sif-pay-prisma.md` |
| DEC-019 | Decisió presa - permisos visuals no autoritzen accions fiscals | Les accions crítiques es validen al servidor/SIF. | DECISIÓ, SEGURETAT | `21-seguretat-permisos-accessos.md` |
| DEC-020 | Decisió presa - alumne no veu factures completes d'empresa/grup | Visibilitat segons receptor fiscal i permisos. | DECISIÓ, PRIVACITAT | `16-estat-final-pantalles.md` |
| DEC-021 | Decisió presa - històric es migra com `NO_VERIFACTU` | No es crea hash ni registre AEAT retroactiu de factures antigues. | DECISIÓ, MIGRACIÓ | `26-matriu-cobertura-casos.md` |
| DEC-022 | Decisió presa - la declaració responsable no és signable encara | La versió signable queda per al SIF real, instal·lat i provat. | DECISIÓ, AEAT | `declaracio-responsable-sif-prisma.md` |
| DEC-023 | Decisió presa - les imatges de pagament són referència històrica | No defineixen el model final, només ajuden a recordar el flux antic. | DECISIÓ, CONTROL | `trello-auditoria-flux-pagament-antic-vs-sif.md` |
| DEC-024 | Decisió presa - els fitxers del repo són la memòria del projecte | Cada xat o iteració ha de llegir i actualitzar documents, no confiar en memòria implícita. | DECISIÓ, GOVERNANÇA | `registre-decisions.md` |

## Decisions pendents i dades a confirmar

| ID | Targeta | Descripció curta | Etiquetes | Fonts |
|---|---|---|---|---|
| DPD-001 | Decidir dades finals de signatura de direcció | Completar qui signa i amb quines dades abans de versió signable. | DECISIÓ PENDENT, AEAT | `checklist-completitud.md` |
| DPD-002 | Confirmar certificat digital o apoderament AEAT | Ubicació segura, permisos d'ús i prova quan correspongui. | DADA PENDENT, AEAT | `09-checklist-posada-en-produccio.md` |
| DPD-003 | Confirmar esquema XML i entorn AEAT de proves | Ajustar payload i proves al format definitiu. | DECISIÓ PENDENT, AEAT | `25-panell-sif-pay-prisma.md` |
| DPD-004 | Confirmar dades exactes del QR fiscal | Camps, URL de coteig i plantilla final. | DECISIÓ PENDENT, QR | `26-matriu-cobertura-casos.md` |
| DPD-005 | Escollir llibreria i plantilla final de PDF | Generació immutable, maquetació, ruta i hash. | DECISIÓ PENDENT, PDF | `08-correus-i-plantilles.md` |
| DPD-006 | Confirmar dades fiscals finals d'USOC | Receptor, CIF/NIF, text visible i tractament d'`anticipi-preu-usoc`. | DADA PENDENT, USOC | `04-fluxos-facturacio.md` |
| DPD-007 | Decidir visibilitat de codis promocionals al PDF | Codi visible, text genèric o referència interna. | DECISIÓ PENDENT, DESCOMPTES | `26-matriu-cobertura-casos.md` |
| DPD-008 | Confirmar SQL real de packs, grups i regals | Taules finals, camps i consultes reals abans de programar. | DADA PENDENT, BD | `26-matriu-cobertura-casos.md` |
| DPD-009 | Confirmar format de referència bancària en transferències | Clau idempotent quan no hi ha referència bancària explícita. | DADA PENDENT, PAGAMENTS | `04-fluxos-facturacio.md` |
| DPD-010 | Decidir tractament de botiga de llibres i SL | Confirmar si entra al mateix SIF, SIF separat o fase posterior. | DECISIÓ PENDENT, DIRECCIÓ | `trello-auditoria-flux-pagament-antic-vs-sif.md` |
| DPD-011 | Confirmar separació Associació/SL per dades noves | Evitar extrapolar criteris de l'històric amb numeració barrejada. | DECISIÓ PENDENT, MIGRACIÓ | `trello-inventari-targetes.md` |
| DPD-012 | Confirmar subdomini, SSL i configuració real de `pay.prisma.cat` | Validar entorn abans de callbacks i panell. | DADA PENDENT, INFRA | `06-integracio-redsys-pay-prisma.md` |
| DPD-013 | Confirmar cossos finals de mètodes intranet | Cerca, modal, actualització, anul·lació i consulta abans d'implementar. | DADA PENDENT, INTRANET | `26-matriu-cobertura-casos.md` |
| DPD-014 | Decidir procediment final empresa/responsable | Enllaç segur, correu o espai específic futur. | DECISIÓ PENDENT, PERMISOS | `16-estat-final-pantalles.md` |
| DPD-015 | Decidir criteri final de targeta regal PDF | Document de regal separat de factura al comprador. | DECISIÓ PENDENT, REGAL | `26-matriu-cobertura-casos.md` |
| DPD-016 | Decidir activació real de versió `1.0.0` | No activar fins a proves, declaració i evidències. | DECISIÓ PENDENT, GO-NO-GO | `19-registre-versions-i-canvis-sif.md` |

## Documentació feta

| ID | Targeta | Descripció curta | Etiquetes | Document |
|---|---|---|---|---|
| DOCF-001 | Documentació feta - índex documental del projecte | Estructura general de documents i carpetes. | DOCUMENTACIÓ FETA | `documentacio/README.md` |
| DOCF-002 | Documentació feta - document base VERI*FACTU | Context fiscal i criteris generals. | DOCUMENTACIÓ FETA | `documentacio/00-index-i-pla/documentacio-verifactu.md` |
| DOCF-003 | Documentació feta - pla documental i auditoria | Com demostrar decisions, evidències i estat del projecte. | DOCUMENTACIÓ FETA | `documentacio/00-index-i-pla/14-pla-documentacio-i-auditoria.md` |
| DOCF-004 | Documentació feta - matriu de cobertura de casos | Casos, estat, documents i falta completar. | DOCUMENTACIÓ FETA, CASOS | `documentacio/00-index-i-pla/26-matriu-cobertura-casos.md` |
| DOCF-005 | Documentació feta - informe d'auditoria documental | Què està cobert, incomplet o amb risc. | DOCUMENTACIÓ FETA | `documentacio/00-index-i-pla/27-informe-auditoria-documental.md` |
| DOCF-006 | Documentació feta - revisió d'apunts d'altres IAs | Control de criteris recuperats o descartats. | DOCUMENTACIÓ FETA | `documentacio/00-index-i-pla/28-revisio-apunts-altres-ias.md` |
| DOCF-007 | Documentació feta - pla tècnic del SIF | Fases i fitxers previstos per implementar nucli SIF. | DOCUMENTACIÓ FETA, PROGRAMACIÓ | `documentacio/00-index-i-pla/29-pla-implementacio-tecnica-sif.md` |
| DOCF-008 | Documentació feta - documentació SIF AEAT | Base normativa i tècnica pendent d'ajust final. | DOCUMENTACIÓ FETA, AEAT | `documentacio/01-compliment-aeat/documentacio-sif-aeat.md` |
| DOCF-009 | Documentació feta - declaració responsable borrador | Versió no signable, criteris i pendents. | DOCUMENTACIÓ FETA, AEAT | `documentacio/01-compliment-aeat/declaracio-responsable-sif-prisma.md` |
| DOCF-010 | Documentació feta - sistema ecommerce/intranet actual | Funcionament actual i canvi necessari cap al SIF. | DOCUMENTACIÓ FETA | `documentacio/02-context-i-estat-actual/12-documentacio-sistema-ecommerce-intranet-sif.md` |
| DOCF-011 | Documentació feta - mapa de BDs i taules | Web, intranet, SIF, deutes de migració i relacions. | DOCUMENTACIÓ FETA, BD | `documentacio/02-context-i-estat-actual/13-mapa-bases-dades-i-taules.md` |
| DOCF-012 | Documentació feta - fluxos de facturació | Casos fiscals, pagaments, saldo, rectificatives i històric. | DOCUMENTACIÓ FETA, CASOS | `documentacio/03-canvis-pendents/04-fluxos-facturacio.md` |
| DOCF-013 | Documentació feta - integració Redsys/pay.prisma.cat | Callback, DS_ORDER, IDPAG, conciliació i riscos. | DOCUMENTACIÓ FETA, REDSYS | `documentacio/03-canvis-pendents/06-integracio-redsys-pay-prisma.md` |
| DOCF-014 | Documentació feta - pantalles intranet | Pantalles afectades i estat final esperat. | DOCUMENTACIÓ FETA, INTRANET | `documentacio/03-canvis-pendents/07-pantalles-intranet.md` |
| DOCF-015 | Documentació feta - correus i plantilles | Correus antics, nous i condicions SIF. | DOCUMENTACIÓ FETA, CORREUS | `documentacio/03-canvis-pendents/08-correus-i-plantilles.md` |
| DOCF-016 | Documentació feta - checklist producció | Go/no-go, backups, proves i activació. | DOCUMENTACIÓ FETA, PRODUCCIÓ | `documentacio/03-canvis-pendents/09-checklist-posada-en-produccio.md` |
| DOCF-017 | Documentació feta - procediments intranet/ecommerce | Passar pagaments, factura abans de pagar, consulta i accions. | DOCUMENTACIÓ FETA, INTRANET | `documentacio/03-canvis-pendents/10-procediments-intranet-ecommerce.md` |
| DOCF-018 | Documentació feta - inventari canvis pendents | Buits i canvis convertits en feina operativa. | DOCUMENTACIÓ FETA | `documentacio/03-canvis-pendents/11-inventari-canvis-pendents.md` |
| DOCF-019 | Documentació feta - model BD SIF | Taules, responsabilitats, relacions, immutabilitat. | DOCUMENTACIÓ FETA, BD | `documentacio/04-estat-final/05-model-bd-sif.md` |
| DOCF-020 | Documentació feta - estat final sistema | SIF centralitzat i canals com a origen. | DOCUMENTACIÓ FETA, SIF | `documentacio/04-estat-final/15-estat-final-sistema.md` |
| DOCF-021 | Documentació feta - estat final pantalles | Pantalles finals i criteris de consulta. | DOCUMENTACIÓ FETA, INTRANET | `documentacio/04-estat-final/16-estat-final-pantalles.md` |
| DOCF-022 | Documentació feta - relacions BD final | Relació lògica entre BD fiscal i BD antiga. | DOCUMENTACIÓ FETA, BD | `documentacio/04-estat-final/17-estat-final-bd-relacions.md` |
| DOCF-023 | Documentació feta - operació i incidències | Errors, baixes, canvis, devolucions i governança. | DOCUMENTACIÓ FETA, OPERACIÓ | `documentacio/04-estat-final/18-estat-final-operacio-incidencies.md` |
| DOCF-024 | Documentació feta - panell SIF | Dashboard, factures, AEAT, incidències, documents i versions. | DOCUMENTACIÓ FETA, PANELL | `documentacio/04-estat-final/25-panell-sif-pay-prisma.md` |
| DOCF-025 | Documentació feta - versions i canvis SIF | Activació de versió i declaració corresponent. | DOCUMENTACIÓ FETA, VERSIONS | `documentacio/05-governanca-operacio/19-registre-versions-i-canvis-sif.md` |
| DOCF-026 | Documentació feta - pla de proves | Bateria bloquejant, evidències i estats. | DOCUMENTACIÓ FETA, PROVES | `documentacio/05-governanca-operacio/20-pla-proves-validacio-sif.md` |
| DOCF-027 | Documentació feta - seguretat i permisos | Rols, auditor, AEAT i restriccions. | DOCUMENTACIÓ FETA, SEGURETAT | `documentacio/05-governanca-operacio/21-seguretat-permisos-accessos.md` |
| DOCF-028 | Documentació feta - manual operatiu intern base | Procediments interns pendents de completar amb pantalles reals. | DOCUMENTACIÓ FETA, OPERACIÓ | `documentacio/05-governanca-operacio/22-manual-operatiu-intern.md` |
| DOCF-029 | Documentació feta - annex de captures | Criteris de captures i evidències finals. | DOCUMENTACIÓ FETA, EVIDÈNCIES | `documentacio/05-governanca-operacio/23-annex-captures-pantalla.md` |
| DOCF-030 | Documentació feta - diccionari camps i valors | Camps fiscals, pagaments, assignacions, orígens i estats. | DOCUMENTACIÓ FETA, BD | `documentacio/05-governanca-operacio/24-diccionari-camps-i-valors.md` |

## Documentació pendent

| ID | Targeta | Descripció curta | Etiquetes | Fonts |
|---|---|---|---|---|
| DOCP-001 | Completar payload final de `issueInvoice()` per cada cas | Definir camps mínims i diferències per curs, pack, grup, regal, USOC i manual. | DOCUMENTACIÓ PENDENT, SIF | `29-pla-implementacio-tecnica-sif.md` |
| DOCP-002 | Completar payload final de `registerPayment()` | Incloure parcial, multi-factura, transferència, compensació i pagament posterior. | DOCUMENTACIÓ PENDENT, PAGAMENTS | `04-fluxos-facturacio.md` |
| DOCP-003 | Completar SQL final de packs, grups i regals | Documentar taules físiques i consultes abans de programar. | DOCUMENTACIÓ PENDENT, BD | `26-matriu-cobertura-casos.md` |
| DOCP-004 | Completar fitxa final de USOC | Receptor, doble factura, anticipi i dades fiscals finals. | DOCUMENTACIÓ PENDENT, USOC | `04-fluxos-facturacio.md` |
| DOCP-005 | Completar fitxa de codis promocionals | Snapshot fiscal, text visible, ús, caducitat i proves. | DOCUMENTACIÓ PENDENT, DESCOMPTES | `26-matriu-cobertura-casos.md` |
| DOCP-006 | Completar document de transferència validada | Referència bancària, BANC, observacions, idempotència i evidència. | DOCUMENTACIÓ PENDENT, PAGAMENTS | `04-fluxos-facturacio.md` |
| DOCP-007 | Completar document d'analitzar fitxer TPV | Format CSV, auditoria, conciliació i reprocessament segur. | DOCUMENTACIÓ PENDENT, TPV | `26-matriu-cobertura-casos.md` |
| DOCP-008 | Completar manual Adam/Pablo per operar el SIF | Accions diàries, incidències, què no tocar i quan escalar. | DOCUMENTACIÓ PENDENT, OPERACIÓ | `22-manual-operatiu-intern.md` |
| DOCP-009 | Completar captures finals de pantalles | Afegir captures reals quan hi hagi UI implementada. | DOCUMENTACIÓ PENDENT, EVIDÈNCIES | `23-annex-captures-pantalla.md` |
| DOCP-010 | Completar acta de backup/restauració | Executar i documentar prova mínima. | DOCUMENTACIÓ PENDENT, GO-NO-GO | `09-checklist-posada-en-produccio.md` |
| DOCP-011 | Completar declaració responsable 1.0.0 | Només quan hi hagi SIF real, proves i versió final. | DOCUMENTACIÓ PENDENT, AEAT | `declaracio-responsable-sif-prisma.md` |
| DOCP-012 | Completar document de botiga llibres/SL | Decidir abast fiscal i relació amb SIF PrisMa. | DOCUMENTACIÓ PENDENT, DIRECCIÓ | `trello-auditoria-flux-pagament-antic-vs-sif.md` |
| DOCP-013 | Completar document de migració històrica | Validació de totals, relacions i marca `NO_VERIFACTU`. | DOCUMENTACIÓ PENDENT, MIGRACIÓ | `13-mapa-bases-dades-i-taules.md` |
| DOCP-014 | Completar diccionari de valors finals | Estats de factura, pagament, AEAT, documents, incidències i assignacions. | DOCUMENTACIÓ PENDENT, BD | `24-diccionari-camps-i-valors.md` |
| DOCP-015 | Completar fitxes de prova amb evidència esperada | Per cada cas crític, indicar captura/log/export requerit. | DOCUMENTACIÓ PENDENT, PROVES | `20-pla-proves-validacio-sif.md` |
| DOCP-016 | Documentar criteri d'arxiu Trello antic | Marcar quines targetes antigues queden absorbides per les noves. | DOCUMENTACIÓ PENDENT, TRELLO | aquest fitxer |

## A debatre amb Adam/Pablo

| ID | Targeta | Descripció curta | Etiquetes |
|---|---|---|---|
| DEB-001 | Debatre amb Adam/Pablo - què han de veure a la intranet | Resum SIF, alertes, enllaços i accions disponibles. | ADAM/PABLO, INTRANET |
| DEB-002 | Debatre amb Adam/Pablo - operació de Passar pagaments | Què confirma l'usuari i què valida sempre el servidor. | ADAM/PABLO, PAGAMENTS |
| DEB-003 | Debatre amb Adam/Pablo - criteri d'incidència SIF | Qui rep avisos, qui reintenta i qui desbloqueja. | ADAM/PABLO, INCIDÈNCIES |
| DEB-004 | Debatre amb Adam/Pablo - empresa/responsable i visibilitat | Enllaç segur, correu o espai futur. | ADAM/PABLO, PRIVACITAT |
| DEB-005 | Debatre amb Adam/Pablo - botiga llibres i SL | Confirmar si entra al SIF actual o queda fase separada. | ADAM/PABLO, DIRECCIÓ |
| DEB-006 | Debatre amb Adam/Pablo - dades USOC | Receptor fiscal, comunicacions i validació manual. | ADAM/PABLO, USOC |
| DEB-007 | Debatre amb Adam/Pablo - factura abans de cobrament | Què es notifica, com es paga després i com es reclama. | ADAM/PABLO, FACTURA |
| DEB-008 | Debatre amb Adam/Pablo - arxiu de targetes antigues | Acceptar que el Trello vell és font històrica i no pla final. | ADAM/PABLO, TRELLO |

## Arxivar o substituir del Trello antic

| ID | Targeta | Acció |
|---|---|---|
| ARC-001 | Arxivar targetes duplicades de `HASH_I` per tipus | Substituïdes per DEV-006, DEV-007, TEST-006 i TEST-007. |
| ARC-002 | Arxivar targetes duplicades de `QR` per tipus | Substituïdes per DEV-026, DEV-027, TEST-027 i proves de cas. |
| ARC-003 | Arxivar targetes duplicades de `PDF` per tipus | Substituïdes per DEV-025, DEV-027 i TEST-028. |
| ARC-004 | Reescriure `PASSAR PAGAMENT ▶ NORMAL/PACK/GRUP/REGAL/USOC` | Moure a casos CU-014 a CU-022 i DEV-018 a DEV-021. |
| ARC-005 | Reescriure `PASSAR PAGAMENT PER DESPESES DE GESTIÓ` | Moure a canvi de curs, saldo/rectificativa i proves específiques. |
| ARC-006 | Reescriure `GENERAR EVENT SEND_EVENT/NEW_DOC_VERIFACTU` | Convertir en cua AEAT/documents/incidències genèriques. |
| ARC-007 | Arxivar separadors i targetes buides | No passen al nou sistema. |
| ARC-008 | Corregir noms antics `USCO`, `PASSSAR`, `CORRU`, `DADE` | Crear targetes noves netes i arxivar les antigues. |
| ARC-009 | Mantenir targetes de PHP/servidor només a Infraestructura | No barrejar-les amb casos fiscals. |
| ARC-010 | Marcar com històriques les imatges de flux lineal | Útils per entendre l'origen, però no per implementar. |

---

# Tauler 2 - Casos d'ús

## Casos d'ús curats

| ID | Llista | Targeta | Acció SIF esperada | Descripció curta |
|---|---|---|---|---|
| CU-001 | Disseny cobert | Cas d'ús - curs normal Redsys | `issueInvoice()` amb bloc payment | Redsys confirma, dedupe per `DS_ORDER`, factura nova si no existeix. |
| CU-002 | Disseny cobert | Cas d'ús - taller Redsys | `issueInvoice()` amb variant taller | Igual que curs normal però amb concepte i payload específic. |
| CU-003 | Disseny cobert | Cas d'ús - jornada Redsys | `issueInvoice()` amb variant jornada | Variant de curs amb text i proves pròpies. |
| CU-004 | Disseny cobert | Cas d'ús - pack | `issueInvoice()` amb diverses línies | Una factura per pagament real, una línia per curs, descompte pack congelat. |
| CU-005 | Disseny cobert | Cas d'ús - grup de persones | `issueInvoice()` amb receptor empresa/responsable | Una factura, línies per participant, visibilitat restringida. |
| CU-006 | Disseny cobert | Cas d'ús - regal | `issueInvoice()` al comprador | El bescanvi posterior no genera segona factura. |
| CU-007 | Disseny cobert | Cas d'ús - USOC alumne + USOC | `issueInvoice()` doble o separada segons decisió | Alumne paga part seva; USOC rep factura per diferència. |
| CU-008 | Falta detall | Cas d'ús - `anticipi-preu-usoc` | Decisió pendent | Confirmar si és bestreta, descompte, saldo o flux USOC separat. |
| CU-009 | Disseny cobert | Cas d'ús - codi promocional | `issueInvoice()` amb snapshot de descompte | No recalcular factura si el codi canvia després. |
| CU-010 | Disseny cobert | Cas d'ús - promoció temporal | `issueInvoice()` amb snapshot de promoció | Vigència i ús són operatius; SIF congela resultat fiscal. |
| CU-011 | Falta detall | Cas d'ús - Carnet Jove | `issueInvoice()` amb descompte validat | Falta confirmar text visible i verificació. |
| CU-012 | Falta detall | Cas d'ús - discapacitat/família/monoparental/violència | `issueInvoice()` amb motiu sensible controlat | Text visible genèric i motiu intern protegit. |
| CU-013 | Disseny cobert | Cas d'ús - empresa/responsable paga inscripcions | `issueInvoice()` al receptor correcte | L'alumne no veu factura completa si no n'és receptor. |
| CU-014 | Disseny cobert | Cas d'ús - transferència validada a intranet | `issueInvoice()` o `registerPayment()` | Administració valida cobrament i SIF decideix factura o cobrament. |
| CU-015 | Disseny cobert | Cas d'ús - passar pagament de factura ja generada | `registerPayment()` | No actualitzar factura; registrar moviment i assignació. |
| CU-016 | Disseny cobert | Cas d'ús - passar pagament curs normal | `issueInvoice()` o `registerPayment()` | La pantalla ja no crea factura local per tipus. |
| CU-017 | Disseny cobert | Cas d'ús - passar pagament taller | `issueInvoice()` o `registerPayment()` | Variant de curs amb validació de dades i idempotència. |
| CU-018 | Disseny cobert | Cas d'ús - passar pagament pack | `issueInvoice()` o `registerPayment()` | Ha d'identificar totes les inscripcions del pack. |
| CU-019 | Disseny cobert | Cas d'ús - passar pagament grup | `issueInvoice()` o `registerPayment()` | Ha d'identificar receptor i participants. |
| CU-020 | Disseny cobert | Cas d'ús - passar pagament regal | `issueInvoice()` o `registerPayment()` | Factura al comprador; controlar codi regal. |
| CU-021 | Disseny cobert | Cas d'ús - passar pagament USOC | `issueInvoice()` o `registerPayment()` | Separar part alumne i part USOC. |
| CU-022 | Disseny cobert | Cas d'ús - analitzar fitxer TPV | Incidència/proposta o `registerPayment()` | El fitxer no emet per si sol si hi ha ambigüitat. |
| CU-023 | Disseny cobert | Cas d'ús - pagament fraccionat | `payment_transaction` + `payment_allocation` | Una factura pot tenir diversos moviments. |
| CU-024 | Disseny cobert | Cas d'ús - factura abans de cobrament | `issueInvoice()` amb `EMESA_ABANS_COBRAMENT = 1` | El pagament posterior va per `registerPayment()`. |
| CU-025 | Disseny cobert | Cas d'ús - factura manual intranet | `issueInvoice()` autoritzat | Línies estructurades, snapshot fiscal i permisos forts. |
| CU-026 | Disseny cobert | Cas d'ús - marcar factura electrònica `E_FACT` | Acció separada, no emissió | No confondre amb factura abans de cobrament. |
| CU-027 | Disseny cobert | Cas d'ús - canvi de dades fiscals després d'emetre | Rectificativa si cal | No modificar factura original silenciosament. |
| CU-028 | Disseny cobert | Cas d'ús - canvi de curs a major import | Rectificativa / nova factura / cobrament segons cas | Diferència positiva, despeses, pagament pendent i evidència. |
| CU-029 | Disseny cobert | Cas d'ús - canvi de curs a menor import | Rectificativa + saldo/devolució | Diferència negativa i decisió de retorn o saldo. |
| CU-030 | Disseny cobert | Cas d'ús - canvi de curs mateix import | Event operatiu o rectificativa si canvia descripció fiscal | No tocar factura sense criteri fiscal. |
| CU-031 | Disseny cobert | Cas d'ús - despeses de gestió | Línia, ajust, rectificativa o cobrament segons moment | Reescriu targetes antigues de passar pagament per despeses. |
| CU-032 | Disseny cobert | Cas d'ús - baixa amb devolució | `REFUND` + rectificativa si redueix factura | Baixa és event administratiu; devolució és moviment econòmic. |
| CU-033 | Disseny cobert | Cas d'ús - baixa amb saldo | `credit_balance` + possible rectificativa | El saldo no és descompte silenciós. |
| CU-034 | Disseny cobert | Cas d'ús - aplicar saldo futur | `COMPENSATION` + `payment_allocation` | El saldo s'aplica com moviment econòmic traçable. |
| CU-035 | Disseny cobert | Cas d'ús - devolució Redsys | `REFUND` | Associar a factura/moviment original. |
| CU-036 | Disseny cobert | Cas d'ús - devolució transferència/manual | `REFUND` | Requereix usuari, motiu, referència i evidència. |
| CU-037 | Parcial | Cas d'ús - morositat/reclamació | Sense acció fiscal o `registerPayment()` quan cobra | Falten rutes i plantilles finals. |
| CU-038 | Disseny cobert | Cas d'ús - URL de pagament d'alumne substituïda per empresa | Desactivar/substituir enllaç | Evita que l'alumne pagui factura d'empresa. |
| CU-039 | Disseny cobert | Cas d'ús - intranet alumne consulta factures pròpies | Consulta protegida | No exposar PDF directe ni dades de tercers. |
| CU-040 | Disseny cobert | Cas d'ús - empresa/responsable consulta factura | Enllaç segur o espai futur | Accés controlat i auditable. |
| CU-041 | Disseny cobert | Cas d'ús - Consulta/Edita/Anula factura | Rectificatives, no edició directa | Bloquejar `updDadesFact` per factures SIF. |
| CU-042 | Disseny cobert | Cas d'ús - veure factura | Consulta PDF/QR/estat | Mostrar VERI*FACTU/no VERI*FACTU i documents. |
| CU-043 | Disseny cobert | Cas d'ús - error AEAT o SIF | Incidència + retry | Panell SIF, log i notificació interna. |
| CU-044 | Disseny cobert | Cas d'ús - PDF/QR pendent | Incidència documental | La factura pot existir amb document pendent controlat. |
| CU-045 | Disseny cobert | Cas d'ús - migració `web.factures` històrica | `NO_VERIFACTU` | Conservar numeració i relacions sense registre retroactiu. |
| CU-046 | Disseny cobert | Cas d'ús - històric Associació/SL barrejat | Històric, no SIF productiu | No reconstruir separació fiscal que no existia netament. |
| CU-047 | Bloquejat per decisió/dada | Cas d'ús - botiga de llibres / SL | Decidir abast | Pot requerir SIF separat o fase posterior. |
| CU-048 | Disseny cobert | Cas d'ús - factura existent cobrada després | `registerPayment()` | No crear factura nova quan ja existeix. |
| CU-049 | Disseny cobert | Cas d'ús - callback Redsys duplicat | Recuperar operació idempotent | Mateix `DS_ORDER` no genera duplicat. |
| CU-050 | Disseny cobert | Cas d'ús - Redsys denegat i després acceptat | Registrar intents i només acceptat fiscal | `IDPAG` pot repetir-se; `DS_ORDER` diferencia intents. |
| CU-051 | Disseny cobert | Cas d'ús - diversos intents amb mateix `IDPAG` | Dedupe per notificació i estat | Evitar bloquejar un pagament bo per un intent KO previ. |
| CU-052 | Disseny cobert | Cas d'ús - una transferència paga diverses factures | Un `payment_transaction`, diverses `payment_allocation` | Assignació parcial o total per factura. |
| CU-053 | Disseny cobert | Cas d'ús - una factura cobrada amb diversos pagaments | Diverses `payment_allocation` | Estat de cobrament parcial/cobrat. |
| CU-054 | Disseny cobert | Cas d'ús - pagament de més | Saldo o devolució | Preguntar i registrar decisió. |
| CU-055 | Disseny cobert | Cas d'ús - client demana canviar dades fiscals després | Rectificativa si afecta factura | No editar receptor original sense rastre. |
| CU-056 | Disseny cobert | Cas d'ús - document no fiscal / proforma | Sense número fiscal | Evitar confusió amb factura real. |
| CU-057 | Disseny cobert | Cas d'ús - operació no facturable | Incidència o cap acció fiscal | Registrar criteri i evitar factura indeguda. |
| CU-058 | Disseny cobert | Cas d'ús - correu pendent de pagar | Notificació sense prova fiscal | Text segons factura existent o pendent. |
| CU-059 | Disseny cobert | Cas d'ús - correu de factura emesa | Enllaç segur/document SIF | Només després de retorn SIF correcte. |
| CU-060 | Disseny cobert | Cas d'ús - notificació interna SIF | `notificacions` intranet o panell | Avís operatiu, no estat fiscal primari. |
| CU-061 | Disseny cobert | Cas d'ús - cercar pagaments per criteri únic | Consulta/proposta, no emissió automàtica | La cerca no ha de modificar factures. |
| CU-062 | Disseny cobert | Cas d'ús - previsualitzar canvi de curs | Simulació sense emetre | Mostrar impacte abans de confirmar. |
| CU-063 | Falta detall | Cas d'ús - exportacions SIF | Export segons format final | Falta CSV/XML/PDF/ZIP definitiu. |

---

# Tauler 3 - Desenvolupament i proves

## Programació

| ID | Llista | Targeta | Descripció curta | Fonts |
|---|---|---|---|---|
| DEV-001 | Nucli SIF | Programar autoload, config i runner de proves sense Composer | Base tècnica del SIF al servidor `pay.prisma.cat`. | `29-pla-implementacio-tecnica-sif.md` |
| DEV-002 | BD i migracions | Crear migració SQL del nucli SIF | `factura`, línies, registres, queue, payments, relacions i documents. | `05-model-bd-sif.md` |
| DEV-003 | BD i migracions | Crear seeds de seqüències i valors controlats | Sèries A/R, estats, tipus de pagament i incidències. | `24-diccionari-camps-i-valors.md` |
| DEV-004 | Nucli SIF | Programar `ConnectionFactory` i `TransactionRunner` | Transaccions i errors DB. | `29-pla-implementacio-tecnica-sif.md` |
| DEV-005 | Nucli SIF | Programar generador UUID complet | Substituir UUID curt històric. | `13-mapa-bases-dades-i-taules.md` |
| DEV-006 | Nucli SIF | Programar idempotència genèrica del SIF | Crear/recuperar operacions sense duplicats. | `04-fluxos-facturacio.md` |
| DEV-007 | Nucli SIF | Programar hash chain global per `FISCAL_ORDER` | Lock, hash anterior i registre fiscal dins una transacció. | xat antic |
| DEV-008 | Nucli SIF | Programar repositori de seqüència fiscal | `SELECT ... FOR UPDATE` per sèrie/any. | `29-pla-implementacio-tecnica-sif.md` |
| DEV-009 | Nucli SIF | Programar validador de payload `issueInvoice()` | Receptor, línies, imports, origen, idempotència i dates. | `29-pla-implementacio-tecnica-sif.md` |
| DEV-010 | Nucli SIF | Programar servei `issueInvoice()` | Crea factura, línies, registre, hash, queue, relacions i opcional payment. | `29-pla-implementacio-tecnica-sif.md` |
| DEV-011 | Pagaments i Redsys | Programar validador de payload `registerPayment()` | Moviments, import, data, mètode, referència i assignacions. | `04-fluxos-facturacio.md` |
| DEV-012 | Pagaments i Redsys | Programar servei `registerPayment()` | Registra moviment econòmic sense número fiscal. | `04-fluxos-facturacio.md` |
| DEV-013 | Pagaments i Redsys | Programar `payment_allocation` parcial i multi-factura | Una transferència pot cobrir diverses factures. | `05-model-bd-sif.md` |
| DEV-014 | Pagaments i Redsys | Programar `redsys_notifications` | Dedupe per `DS_ORDER`, estat, import i payload signat. | `06-integracio-redsys-pay-prisma.md` |
| DEV-015 | Pagaments i Redsys | Migrar callback `realitzaPagamentAutomatic.php` | Deixar de crear `web.factures`; cridar SIF. | xat antic, codi-drive |
| DEV-016 | Pagaments i Redsys | Migrar callbacks taller/jornada/pack/grup/regal | Reutilitzar SIF amb variants de payload. | `codi-drive/` |
| DEV-017 | Pagaments i Redsys | Programar resposta idempotent a Redsys | Repetir notificació retorna estat existent. | `06-integracio-redsys-pay-prisma.md` |
| DEV-018 | Intranet i pantalles | Reprogramar Passar pagaments com a conciliació SIF | Buscar, proposar i confirmar sense inserir factura local. | `10-procediments-intranet-ecommerce.md` |
| DEV-019 | Intranet i pantalles | Programar transferència validada a intranet | Crear `payment_transaction` o `issueInvoice()` segons decisió. | `04-fluxos-facturacio.md` |
| DEV-020 | Intranet i pantalles | Programar analitzar fitxer TPV | Importar, comparar, generar incidències/propostes i reprocessar segur. | `26-matriu-cobertura-casos.md` |
| DEV-021 | Intranet i pantalles | Programar factura abans de cobrament | `issueInvoice()` amb `EMESA_ABANS_COBRAMENT = 1`. | `10-procediments-intranet-ecommerce.md` |
| DEV-022 | Intranet i pantalles | Bloquejar edició directa de factures SIF | Substituir `updDadesFact` per flux controlat. | `18-estat-final-operacio-incidencies.md` |
| DEV-023 | Intranet i pantalles | Substituir anul·lació històrica per rectificativa SIF | Reemplaçar `anularFactura()` quan la factura sigui SIF. | `10-procediments-intranet-ecommerce.md` |
| DEV-024 | Nucli SIF | Programar servei de rectificatives | Sèrie R, motiu, mode i relació amb factura original. | `05-model-bd-sif.md` |
| DEV-025 | Nucli SIF | Programar saldo i compensació | `credit_balance`, `COMPENSATION` i assignació a factura futura. | `04-fluxos-facturacio.md` |
| DEV-026 | Nucli SIF | Programar devolucions `REFUND` | Relació amb pagament/factura original i possible rectificativa. | `18-estat-final-operacio-incidencies.md` |
| DEV-027 | Documents fiscals i correus | Programar generació PDF immutable | Des de snapshot SIF, no des de dades vives. | `08-correus-i-plantilles.md` |
| DEV-028 | Documents fiscals i correus | Programar QR fiscal | Dades exactes pendents de confirmar. | `documentacio-sif-aeat.md` |
| DEV-029 | Documents fiscals i correus | Programar `factura_documents` i ruta protegida | Guardar hash, ruta, estat i accés segur. | `05-model-bd-sif.md` |
| DEV-030 | Documents fiscals i correus | Reprogramar correus segons estat SIF | Factura emesa, pendent, PDF pendent, incidència i saldo. | `08-correus-i-plantilles.md` |
| DEV-031 | AEAT i incidències | Programar `fiscal_queue` i retries | Enviament AEAT, intents, error, pròxim retry. | `05-model-bd-sif.md` |
| DEV-032 | AEAT i incidències | Programar incidències SIF | Reutilitzar/ampliar `errors_verifactu` o taula final. | `25-panell-sif-pay-prisma.md` |
| DEV-033 | Intranet i pantalles | Programar notificacions intranet | Avisos operatius derivats del SIF. | `21-seguretat-permisos-accessos.md` |
| DEV-034 | Intranet i pantalles | Programar panell SIF dashboard | Factures, registres, incidències, documents, versions i export. | `25-panell-sif-pay-prisma.md` |
| DEV-035 | Intranet i pantalles | Programar indicador VERI*FACTU a intranet | Resum i enllaç al panell, sense substituir-lo. | `16-estat-final-pantalles.md` |
| DEV-036 | Intranet i pantalles | Programar consulta alumne amb permisos | Factures pròpies, PDF/QR segur, no dades de tercers. | `16-estat-final-pantalles.md` |
| DEV-037 | Intranet i pantalles | Programar consulta empresa/responsable | Enllaç segur o espai futur amb permisos i logs. | `21-seguretat-permisos-accessos.md` |
| DEV-038 | Migració i traspàs | Migrar `web.factures` històrica com `NO_VERIFACTU` | Conservar número, data, receptor, import i relació. | `13-mapa-bases-dades-i-taules.md` |
| DEV-039 | Migració i traspàs | Migrar `reg_pagament` històric | Conservar o transformar a `payment_transaction` segons criteri. | `05-model-bd-sif.md` |
| DEV-040 | Migració i traspàs | Migrar logs antics si cal | `factura_log` i `session_log` com històric o events auditables. | `05-model-bd-sif.md` |
| DEV-041 | Migració i traspàs | Sincronitzar resum cap a BD antiga després d'èxit SIF | `PAGAMENT`, `DATA PAG`, `FRACCIO`, `FACTURA_RELACIONADA` com compatibilitat. | `04-fluxos-facturacio.md` |
| DEV-042 | BD i migracions | Aplicar permisos MySQL restrictius | Evitar updates/deletes manuals sobre taules fiscals emeses. | `21-seguretat-permisos-accessos.md` |
| DEV-043 | BD i migracions | Crear `notificacions` a BD intranet | Avisos operatius de SIF. | `05-model-bd-sif.md` |
| DEV-044 | BD i migracions | Crear o consolidar `motiu_canvi` | Canvis de curs, baixa, rectificativa i motius. | xat antic |
| DEV-045 | Intranet i pantalles | Programar previsualització de canvi de curs | Simular impacte abans d'emetre res. | `07-pantalles-intranet.md` |
| DEV-046 | Intranet i pantalles | Programar baixa com event administratiu | Retorn, saldo, no retorn o pendent de decisió. | `18-estat-final-operacio-incidencies.md` |
| DEV-047 | Nucli SIF | Programar càlcul d'estat de cobrament | Pendent, parcial, cobrat, sobrepagat, compensat. | `04-fluxos-facturacio.md` |
| DEV-048 | Go-no-go | Programar script `preflight-sif.php` | Comprovar config, BD, permisos, versions i entorn. | `29-pla-implementacio-tecnica-sif.md` |
| DEV-049 | Go-no-go | Preparar entorn de preproducció o mode test | Separar numeració i dades de prova. | `09-checklist-posada-en-produccio.md` |
| DEV-050 | Migració i traspàs | Preparar informe de traspàs de targetes Trello | Mapar targetes antigues a noves i arxivables. | aquest fitxer |

## Proves i evidències

| ID | Llista | Targeta | Resultat esperat | Fonts |
|---|---|---|---|---|
| TEST-001 | Proves | Provar migració SQL del nucli SIF | Taules i columnes principals existeixen amb InnoDB i DECIMAL. | `29-pla-implementacio-tecnica-sif.md` |
| TEST-002 | Proves | Provar idempotència abans de numeració | Una repetició no consumeix número nou. | `20-pla-proves-validacio-sif.md` |
| TEST-003 | Proves | Provar callback Redsys duplicat | Mateix `DS_ORDER` retorna factura existent. | `06-integracio-redsys-pay-prisma.md` |
| TEST-004 | Proves | Provar Redsys denegat i acceptat amb mateix `IDPAG` | Només l'acceptat crea factura o pagament fiscal. | `04-fluxos-facturacio.md` |
| TEST-005 | Proves | Provar diversos `DS_ORDER` per un `IDPAG` | Es registren intents sense duplicar factura indeguda. | `04-fluxos-facturacio.md` |
| TEST-006 | Proves | Provar concurrència de `issueInvoice()` | Sense números duplicats ni forks de hash. | `29-pla-implementacio-tecnica-sif.md` |
| TEST-007 | Proves | Provar hash chain global | `FISCAL_ORDER` manté cadena única entre A i R. | xat antic |
| TEST-008 | Proves | Provar `issueInvoice()` amb payment dins payload | Crea factura, moviment i assignació coherent. | `04-fluxos-facturacio.md` |
| TEST-009 | Proves | Provar `registerPayment()` sobre factura existent | No crea factura nova ni hash fiscal nou. | `04-fluxos-facturacio.md` |
| TEST-010 | Proves | Provar transferència que paga diverses factures | Un moviment, diverses assignacions. | `04-fluxos-facturacio.md` |
| TEST-011 | Proves | Provar factura amb diversos pagaments parcials | Estat parcial/cobrat correcte. | `04-fluxos-facturacio.md` |
| TEST-012 | Proves | Provar pagament de més | Queda saldo o devolució segons decisió. | `18-estat-final-operacio-incidencies.md` |
| TEST-013 | Proves | Provar curs normal Redsys | Factura SIF, relació, PDF/QR pendent o generat i correu correcte. | `26-matriu-cobertura-casos.md` |
| TEST-014 | Proves | Provar pack | Una factura, diverses línies i descompte pack. | `26-matriu-cobertura-casos.md` |
| TEST-015 | Proves | Provar grup | Receptor correcte, línies participants i visibilitat restringida. | `26-matriu-cobertura-casos.md` |
| TEST-016 | Proves | Provar regal | Factura al comprador i bescanvi sense segona factura. | `26-matriu-cobertura-casos.md` |
| TEST-017 | Proves | Provar USOC | Part alumne i part USOC no es barregen. | `26-matriu-cobertura-casos.md` |
| TEST-018 | Proves | Provar codi promocional | Snapshot fiscal no canvia quan el codi canvia després. | `26-matriu-cobertura-casos.md` |
| TEST-019 | Proves | Provar descomptes sensibles | Text visible no exposa dades sensibles innecessàries. | `documentacio-verifactu.md` |
| TEST-020 | Proves | Provar factura abans de cobrament | `EMESA_ABANS_COBRAMENT = 1` i cobrament posterior via `registerPayment()`. | `10-procediments-intranet-ecommerce.md` |
| TEST-021 | Proves | Provar canvi de dades fiscals després d'emetre | Es bloqueja edició i es dirigeix a rectificativa. | `18-estat-final-operacio-incidencies.md` |
| TEST-022 | Proves | Provar canvi de curs a major import | Es genera impacte fiscal i pagament pendent correcte. | `07-pantalles-intranet.md` |
| TEST-023 | Proves | Provar canvi de curs a menor import | Rectificativa/saldo/devolució segons decisió. | `07-pantalles-intranet.md` |
| TEST-024 | Proves | Provar canvi de curs mateix import | No es toca factura si no hi ha impacte fiscal. | `07-pantalles-intranet.md` |
| TEST-025 | Proves | Provar despeses de gestió | No es resolen amb targetes antigues de pagament lineal. | `04-fluxos-facturacio.md` |
| TEST-026 | Proves | Provar baixa amb devolució | `REFUND` i rectificativa quan pertoqui. | `18-estat-final-operacio-incidencies.md` |
| TEST-027 | Proves | Provar baixa amb saldo | `credit_balance` disponible i traçable. | `18-estat-final-operacio-incidencies.md` |
| TEST-028 | Proves | Provar PDF/QR protegit | No hi ha ruta pública directa i el hash coincideix. | `08-correus-i-plantilles.md` |
| TEST-029 | Proves | Provar correu de factura emesa | Només s'envia quan el SIF retorna UUID/número. | `08-correus-i-plantilles.md` |
| TEST-030 | Proves | Provar correu pendent de pagar | Text i URL segons factura existent o pendent. | `08-correus-i-plantilles.md` |
| TEST-031 | Proves | Provar error AEAT amb retry | Incidència, intents, next retry i log visibles. | `25-panell-sif-pay-prisma.md` |
| TEST-032 | Proves | Provar PDF/QR fallit | Factura existeix, document queda pendent i genera incidència. | `25-panell-sif-pay-prisma.md` |
| TEST-033 | Proves | Provar permisos server-side | Usuari sense rol no pot executar acció fiscal encara que vegi botó. | `21-seguretat-permisos-accessos.md` |
| TEST-034 | Proves | Provar auditor/AEAT només lectura | Accés temporal, auditat i sense modificacions. | `21-seguretat-permisos-accessos.md` |
| TEST-035 | Proves | Provar intranet alumne | Només veu factures pròpies i documents autoritzats. | `16-estat-final-pantalles.md` |
| TEST-036 | Proves | Provar empresa/responsable | Veu factura corresponent sense exposar altres alumnes. | `16-estat-final-pantalles.md` |
| TEST-037 | Proves | Provar migració històrica `web.factures` | Marca `NO_VERIFACTU`, totals coherents i cap registre AEAT retroactiu. | `13-mapa-bases-dades-i-taules.md` |
| TEST-038 | Proves | Provar relació `fact_rels` | `FACTURA_RELACIONADA`, `UUID_FACTURA`, `SOURCE_TYPE`, `IDPAG`, `DS_ORDER` coherents. | `17-estat-final-bd-relacions.md` |
| TEST-039 | Proves | Provar sincronització resum a BD antiga | Camps antics són resum posterior, no prova fiscal primària. | `04-fluxos-facturacio.md` |
| TEST-040 | Proves | Provar `Passar pagaments` com a proposta | Cercar no modifica factura fins confirmació controlada. | `10-procediments-intranet-ecommerce.md` |
| TEST-041 | Proves | Provar analitzar fitxer TPV ambigu | Crea incidència/proposta, no factura automàtica. | `26-matriu-cobertura-casos.md` |
| TEST-042 | Proves | Provar anul·lació històrica substituïda | Factura SIF no s'anul·la amb mecanisme antic. | `10-procediments-intranet-ecommerce.md` |
| TEST-043 | Proves | Provar backup/restauració mínim | Acta i evidència abans de producció. | `09-checklist-posada-en-produccio.md` |
| TEST-044 | Go-no-go | Executar paquet go/no-go `0.3-BORRADOR` | Resultats PASS/FAIL/BLOCKED amb evidències. | `20-pla-proves-validacio-sif.md` |
| TEST-045 | Go-no-go | Executar paquet go/no-go `1.0.0` | Només si tot el crític està provat i documentat. | `19-registre-versions-i-canvis-sif.md` |
| TEST-046 | Go-no-go | Verificar declaració responsable publicada al SIF | Versió signada accessible i coherent amb versió activa. | `declaracio-responsable-sif-prisma.md` |

## Targetes d'infraestructura i traspàs

| ID | Llista | Targeta | Descripció curta |
|---|---|---|---|
| INF-001 | Migració i traspàs | Revisar PHP 8.4 al nou servidor | Conservar com a tasca tècnica, no com a cas fiscal. |
| INF-002 | Migració i traspàs | Revisar rutes antigues cap a `pay.prisma.cat` | URLs antigues poden iniciar/redirigir, però no decidir fiscalment. |
| INF-003 | Migració i traspàs | Revisar dependències JS/CSS/Font Awesome | Tasca d'entorn separada del SIF fiscal. |
| INF-004 | Migració i traspàs | Revisar connexions `ConnexioWeb`, `ConnexioPay`, `ConnexioIntranet` | Identificar quina BD toca cada flux. |
| INF-005 | Migració i traspàs | Revisar codi Drive com a referència | No copiar cegament; extreure punts d'entrada i SQL real. |
| INF-006 | Migració i traspàs | Preparar mapa de taules físiques finals | Web, intranet, SIF i històric. |

## Resum de cobertura

| Bloc | Targetes curades |
|---|---:|
| Decisions preses | 24 |
| Decisions pendents / dades | 16 |
| Documentació feta | 30 |
| Documentació pendent | 16 |
| Debats Adam/Pablo | 8 |
| Arxiu/substitució antigues | 10 |
| Casos d'ús | 63 |
| Programació | 50 |
| Proves / go-no-go | 46 |
| Infraestructura / traspàs | 6 |
| **Total** | **269** |

## Notes finals per preparar import a Trello

- Aquest fitxer és la base curada. El fitxer `trello-targetes-petites-reconciliades.md` queda com a històric massiu i font d'auditoria.
- Si es genera JSON/API, convé importar primer aquestes 269 targetes i després arxivar o mapar les targetes antigues.
- Les targetes de casos d'ús no s'han de reduir: són el cor del projecte i el lloc on es veu la complexitat real.
- Les targetes antigues de "passar pagament segons tipus" no s'han de copiar literalment: s'han de convertir en casos de conciliació i decisions SIF.
- La feina feta es veu a `Documentació feta`, `Decisions preses` i `Feina feta` implícita en les fonts. La feina pendent es veu a `Casos d'ús`, `Programació`, `Proves`, `Documentació pendent` i `Decisions pendents`.
