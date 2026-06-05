# Inventari de targetes Trello - VERI*FACTU / SIF

Data de preparacio: 2026-06-02

Aquest document prepara el contingut que despres es convertira a JSON per l'API de Trello. Les targetes estan redactades com a feina professional feta o pendent del projecte.

## Criteri de taulers

Recomanacio:

- Crear tres taulers Trello separats si encara no existeixen.
- Si ja hi ha un Trello unic de VERI*FACTU, no l'ompliria tot alla. El modificaria per separar-lo en aquests tres taulers o el deixaria com a arxiu historic.
- No barrejar aquest Trello amb marketing, SEO, incidencies generals, reunions o ecommerce no fiscal. Aixo pot anar en un altre tauler de carrega global.

Taulers recomanats:

1. `VeriFactu / SIF ▶ Control del projecte`
2. `VeriFactu / SIF ▶ Casos d'us`
3. `VeriFactu / SIF ▶ Desenvolupament i proves`

## Criteri de targetes

- Quan hi ha feina feta, la targeta comenca amb `He analitzat`, `He definit`, `He documentat`, `He revisat`, `He preparat` o `He ordenat`.
- Quan queda feina per fer, la targeta comenca amb `Programar`, `Completar`, `Validar`, `Executar`, `Preparar` o `Confirmar`.
- Un cas d'us important pot generar tres targetes: feina feta, implementacio pendent i prova/evidencia pendent.
- Les targetes han de conservar referencies a documents relacionats per demostrar que la feina esta traçada.
- El separador visual de Trello sera `▶`, no `·`, per mantenir coherencia amb les targetes historiques.

## Criteri final de reconciliacio

- Les targetes antigues granulars de WEB, INTRANET i SIF es copiaran o recrearan tambe dins dels tres taulers nous. No es deixaran nomes als taulers antics.
- Les targetes antigues es podran arxivar quan el traspas al nou esquema estigui fet i verificat.
- Les targetes de PHP 8.4, nou servidor, full d'estil, Font Awesome, JS i traspas d'entorn es consideren feina generada per VERI*FACTU/SIF i s'inclouran al projecte.
- Els noms antics es corregiran quan es generin targetes noves: `USCO` -> `USOC`, `PASSSAR` -> `PASSAR`, `CORRU` -> `CORREU`, `DADE` -> `DADES`.
- No es creara una seccio separada anomenada "informacio recuperada de l'historic"; aquesta informacio s'integrara al cas o bloc corresponent.
- Les targetes recuperades de l'historic del projecte portaran prefix de cas o bloc: `TRANSFERENCIA VALIDADA ▶ ...`, `REDSYS ▶ ...`, `BD SIF ▶ ...`, `MIGRACIO HISTORICA ▶ ...`.
- Encara que una informacio ja estigui parcialment documentada, es duplicara com a targeta petita si reflecteix feina feta o un pas concret pendent. Aixo permetra arxivar targetes antigues despres del traspas.
- El problema historic de `factures.entitat` queda tractat com a traspas historic: el camp indica l'entitat associada al pagament/transmissio en el sistema antic, no una separacio fiscal fiable entre Associacio i SL. Les factures antigues amb numeracio barrejada o compartida es conservaran com a dades historiques, sense convertir-les retroactivament en factures SIF productives.

## Passada de reconciliacio repo + historic

S'ha fet una segona passada de control despres de detectar que la primera base encara no reflectia prou be els documents del repositori ni l'historic original del projecte.

Resultat de la passada del 2026-06-02:

- Base revisable actualitzada: `00-control/trello-targetes-petites-reconciliades.md`.
- Auditoria creada: `00-control/trello-auditoria-buits-repo-historic.md`.
- Targetes finals a la base revisable despres d'aquesta passada: 13.183.
- Targetes afegides des del repositori: 1.387.
- Targetes afegides des de l'historic del projecte: 418.
- Total afegit en aquesta passada: 1.805.
- Separador revisat: `▶`.
- S'han eliminat dels noms de targeta les referencies a converses o eines; les targetes han de descriure feina del projecte.

---

## Passada incremental 2026-06-03

- S'han revisat canvis documentals i fitxers nous del nucli SIF.
- Targetes afegides en aquesta passada: 55.
- Base revisable actualitzada fins a TPR-13245.
- Targetes finals a la base revisable: 13.245.
- Blocs principals afegits: Fase 0, Fase 1, migracio SQL, seed, PHPUnit, codi de referencia del Drive, permisos server-side, Redsys/TPV, descomptes i evidencies.

---

## Llista de control - debats Adam/Pablo

- S'ha creat la llista proposada `A debatre amb Adam/Pablo` dins el tauler `VeriFactu / SIF ▶ Control del projecte`.
- Targetes afegides en aquesta llista: 7.
- Base revisable actualitzada fins a TPR-13245.
- Temes principals: visualitzacio de notificacions SIF a la intranet, funcionament operatiu per Pablo, criteri de botiga de llibres, separacio entre Associacio i SL, possible SIF separat per botiga/SL i documentacio de decisions abans de desenvolupar.

---

## Auditoria de sistema de targetes existents

S'ha revisat si les targetes existents i la base reconciliada segueixen el sistema actual de treball.

Resultat:

- Auditoria creada: `00-control/trello-auditoria-sistema-targetes-actuals.md`.
- Targetes a la base reconciliada: 13.245.
- Taulers fora del sistema actual: 0.
- Targetes amb llista no normalitzada: 3.758.
- Targetes amb problemes de format/titol a revisar: 5.248.
- Targetes obertes uniques detectades als exports Trello antics: 6.579.
- Targetes antigues cobertes o absorbides a la base nova: 5.144.
- Targetes antigues sense cobertura literal o clara: 1.435.
- Targetes antigues que no segueixen el sistema nou per nom/tauler/format: 4.095.

Conclusio: abans de generar el JSON final de Trello cal una passada especifica de normalitzacio de llistes i d'arxiu/substitucio de targetes antigues. La cobertura de feina actual ha millorat, pero encara no totes les targetes existents segueixen el sistema final.

---

## Auditoria flux pagament antic vs SIF actual

S'han revisat els esquemes antics de pagament i s'ha detectat que representaven un flux massa lineal per al sistema actual.

Resultat:

- Auditoria creada: `00-control/trello-auditoria-flux-pagament-antic-vs-sif.md`.
- Targetes afegides en aquesta passada: 32.
- Base revisable actualitzada fins a TPR-13277.
- Targetes finals a la base revisable: 13.277.
- Blocs principals afegits: control del flux antic, debats Adam/Pablo, pagament web, pagament intranet, transferencia validada, factura abans de cobrament, rectificatives, botiga, BD SIF, decisor SIF, Redsys, documents fiscals, notificacions, errors, permisos i proves.

Conclusio: les targetes antigues que assumeixen `pagament -> factura -> PDF -> correu` s'han de tractar com a referencia i no com a model final. El Trello ha de representar una matriu de canals i decisions SIF.

---

# Tauler 1 - VeriFactu / SIF ▶ Control del projecte

## Llistes

1. `Inbox / pendent de classificar`
2. `Decisions ja preses`
3. `Feina ja invertida`
4. `Documentacio creada o revisada`
5. `Blocs especialitzats`
6. `Decisions pendents`
7. `Dades pendents de confirmar`
8. `Riscos / direccio`
9. `Bloquejat`
10. `Validat / tancat`

## Etiquetes

- `DECISIO PRESA`
- `DECISIO PENDENT`
- `FEINA FETA`
- `DOCUMENTACIO`
- `REVISIO HISTORICA`
- `BLOC ESPECIALITZAT`
- `DIRECCIO`
- `LEGAL / AEAT`
- `RISC ALT`
- `BLOQUEJANT`
- `DEPENDENCIA EXTERNA`
- `PENDENT VALIDAR`

## Targetes

| ID | Llista | Titol | Etiquetes | Descripcio resum | Documents relacionats |
|---|---|---|---|---|---|
| C-001 | Feina ja invertida | He obert i delimitat el projecte VERI*FACTU/SIF | `FEINA FETA`, `DIRECCIO`, `LEGAL / AEAT` | He delimitat que el projecte no es una adaptacio visual de factures, sino una transformacio del sistema fiscal, tecnic i operatiu. | `README.md`, `00-control/estat-projecte.md` |
| C-002 | Feina ja invertida | He reconduit l'enfocament abans de dissenyar arquitectura | `FEINA FETA`, `RISC ALT`, `DIRECCIO` | He aturat decisions prematures i he reordenat el projecte per entendre primer canals, pagaments, intranet i casuistica real. | `00-control/registre-decisions.md` |
| C-003 | Feina ja invertida | He creat l'estructura de control del projecte | `FEINA FETA`, `REVISIO HISTORICA`, `DOCUMENTACIO` | He preparat l'espai de control amb estat, decisions, checklist, mapa de blocs i informacio pendent de recuperar. | `00-control/*` |
| C-004 | Feina ja invertida | He revisat la informacio historica del projecte per blocs | `FEINA FETA`, `REVISIO HISTORICA`, `RISC ALT` | He recuperat informacio per temes concrets per evitar perdre decisions i detalls importants de la fase previa. | `00-control/informacio-a-recuperar-del-xat-antic.md` |
| C-005 | Feina ja invertida | He preparat l'inventari inicial de temes historics | `FEINA FETA`, `REVISIO HISTORICA`, `DOCUMENTACIO` | He identificat blocs de revisio: context, facturacio, pagaments, BD, pantalles, AEAT, correus i proves. | `00-control/estat-projecte.md` |
| C-006 | Feina ja invertida | He ordenat la revisio historica per blocs | `FEINA FETA`, `REVISIO HISTORICA` | He establert un ordre de revisio incremental per no saturar el projecte i poder documentar cada bloc. | `00-control/registre-decisions.md` |
| C-007 | Feina ja invertida | He mantingut el registre cronologic de decisions | `FEINA FETA`, `DOCUMENTACIO` | He deixat traça de les decisions funcionals, fiscals, tecniques i documentals preses durant la revisio. | `00-control/registre-decisions.md` |
| C-008 | Feina ja invertida | He mantingut el checklist de completitud del projecte | `FEINA FETA`, `DOCUMENTACIO` | He separat feina revisada, documents clau, preparacio normativa, arquitectura, proves i blocs especialitzats. | `00-control/checklist-completitud.md` |
| C-009 | Feina ja invertida | He creat i mantingut la matriu de cobertura de casos | `FEINA FETA`, `DOCUMENTACIO`, `RISC ALT` | He convertit canals, pantalles, fluxos i riscos en una matriu de cobertura amb estat per cas. | `documentacio/00-index-i-pla/26-matriu-cobertura-casos.md` |
| C-010 | Feina ja invertida | He preparat l'informe d'auditoria documental | `FEINA FETA`, `DOCUMENTACIO`, `DIRECCIO` | He ordenat que es pot defensar, que esta incomplet i quins riscos marcaria una auditoria. | `documentacio/00-index-i-pla/27-informe-auditoria-documental.md` |
| C-011 | Decisions ja preses | He definit que el SIF ha de ser centralitzat | `DECISIO PRESA`, `RISC ALT`, `BACKEND` | Els canals no decideixen numero fiscal, hash, PDF, QR ni estat fiscal final. | `documentacio/04-estat-final/15-estat-final-sistema.md` |
| C-012 | Decisions ja preses | He definit que `issueInvoice()` concentra l'emissio fiscal | `DECISIO PRESA`, `BACKEND`, `LEGAL / AEAT` | L'emissio inclou numeracio, linies, registre fiscal, hash chain, cua AEAT, relacions i idempotencia. | `documentacio/04-estat-final/05-model-bd-sif.md` |
| C-013 | Decisions ja preses | He definit que `registerPayment()` registra cobraments sense crear factura | `DECISIO PRESA`, `BACKEND`, `BD` | El cobrament queda separat de l'emissio fiscal i es vincula amb assignacions auditades. | `documentacio/04-estat-final/05-model-bd-sif.md` |
| C-014 | Decisions ja preses | He separat factura i pagament com a conceptes diferents | `DECISIO PRESA`, `RISC ALT`, `LEGAL / AEAT` | Un pagament no sempre crea factura, i una factura pot existir abans o despres del cobrament segons el cas. | `documentacio/03-canvis-pendents/04-fluxos-facturacio.md` |
| C-015 | Decisions ja preses | He definit que no es poden modificar factures emeses silenciosament | `DECISIO PRESA`, `LEGAL / AEAT`, `RISC ALT` | Els canvis posteriors han d'anar per rectificativa, saldo, devolucio o event administratiu segons el cas. | `documentacio/04-estat-final/18-estat-final-operacio-incidencies.md` |
| C-016 | Decisions ja preses | He definit `fact_rels` com a relacio logica auditada | `DECISIO PRESA`, `BD`, `AUDITORIA` | La BD fiscal i la BD antiga es relacionen sense foreign keys directes entre sistemes. | `documentacio/04-estat-final/17-estat-final-bd-relacions.md` |
| C-017 | Decisions ja preses | He definit el criteri de versions i declaracio responsable | `DECISIO PRESA`, `LEGAL / AEAT`, `DIRECCIO` | La versio `0.1-BORRADOR` no es signable; la `1.0.0` nomes quan hi hagi SIF real instal.lat i provat. | `documentacio/01-compliment-aeat/declaracio-responsable-sif-prisma.md` |
| C-018 | Decisions ja preses | He definit el rol de productor/titular intern | `DECISIO PRESA`, `LEGAL / AEAT`, `DIRECCIO` | Associacio PrisMa queda com a titular/productor intern; Meriem com a responsable tecnica/documental; direccio signa quan correspongui. | `00-control/checklist-completitud.md` |
| C-019 | Decisions ja preses | He definit el rol auditor/AEAT de nomes lectura | `DECISIO PRESA`, `SEGURETAT`, `LEGAL / AEAT` | Acces temporal, auditat, sense modificacions i amb prohibicions operatives clares. | `documentacio/05-governanca-operacio/21-seguretat-permisos-accessos.md` |
| C-020 | Decisions ja preses | He definit criteris GO, GO amb limitacions i NO-GO | `DECISIO PRESA`, `TESTING`, `DIRECCIO` | He establert criteris de decisio per passar o no a produccio segons proves, incidencies i evidencies. | `documentacio/05-governanca-operacio/20-pla-proves-validacio-sif.md` |
| C-021 | Documentacio creada o revisada | He preparat l'index documental del projecte | `DOCUMENTACIO`, `FEINA FETA` | He organitzat la documentacio per index, AEAT, context, canvis pendents, estat final i governanca. | `documentacio/README.md` |
| C-022 | Documentacio creada o revisada | He preparat el pla documental i d'auditoria | `DOCUMENTACIO`, `FEINA FETA` | He definit com s'ha de demostrar el projecte i com s'ha de mantenir la memoria documental. | `documentacio/00-index-i-pla/14-pla-documentacio-i-auditoria.md` |
| C-023 | Documentacio creada o revisada | He revisat el document base de documentacio VERI*FACTU | `DOCUMENTACIO`, `FEINA FETA` | He consolidat criteris generals de facturacio, descomptes, fluxos i relacions documentals. | `documentacio/00-index-i-pla/documentacio-verifactu.md` |
| C-024 | Documentacio creada o revisada | He preparat la documentacio SIF per AEAT en estat parcial | `DOCUMENTACIO`, `LEGAL / AEAT` | He deixat la base tecnica i normativa que caldra ajustar quan el SIF estigui implementat. | `documentacio/01-compliment-aeat/documentacio-sif-aeat.md` |
| C-025 | Documentacio creada o revisada | He preparat la declaracio responsable en versio borrador | `DOCUMENTACIO`, `LEGAL / AEAT`, `DIRECCIO` | He preparat criteris, versio i punts pendents abans de tenir una versio signable. | `documentacio/01-compliment-aeat/declaracio-responsable-sif-prisma.md` |
| C-026 | Documentacio creada o revisada | He documentat el sistema ecommerce/intranet actual | `DOCUMENTACIO`, `FEINA FETA` | He separat estat actual i necessitats futures per evitar confondre sistema existent amb SIF futur. | `documentacio/02-context-i-estat-actual/12-documentacio-sistema-ecommerce-intranet-sif.md` |
| C-027 | Documentacio creada o revisada | He documentat el mapa de bases de dades i taules | `DOCUMENTACIO`, `BD` | He recollit BD actual, taules implicades i relacions que cal respectar o transformar. | `documentacio/02-context-i-estat-actual/13-mapa-bases-dades-i-taules.md` |
| C-028 | Documentacio creada o revisada | He documentat els fluxos de facturacio i casos especials | `DOCUMENTACIO`, `FEINA FETA`, `RISC ALT` | He recollit cursos, packs, grups, regals, USOC, transferencies, rectificatives, baixes i canvis. | `documentacio/03-canvis-pendents/04-fluxos-facturacio.md` |
| C-029 | Documentacio creada o revisada | He documentat la integracio Redsys i pay.prisma.cat | `DOCUMENTACIO`, `TPV / REDSYS` | He recollit callback, DS_ORDER, IDPAG, notificacions, conciliacio i riscos de duplicat. | `documentacio/03-canvis-pendents/06-integracio-redsys-pay-prisma.md` |
| C-030 | Documentacio creada o revisada | He documentat pantalles d'intranet afectades | `DOCUMENTACIO`, `INTRANET` | He identificat pantalles existents, nous apartats i controls que cal aplicar amb el SIF. | `documentacio/03-canvis-pendents/07-pantalles-intranet.md` |
| C-031 | Documentacio creada o revisada | He documentat correus, plantilles, PDF, QR i notificacions | `DOCUMENTACIO`, `PDF / QR` | He separat comunicacions actuals, plantilles futures, PDF fiscal i necessitats de QR. | `documentacio/03-canvis-pendents/08-correus-i-plantilles.md` |
| C-032 | Documentacio creada o revisada | He preparat el checklist de posada en produccio | `DOCUMENTACIO`, `TESTING`, `DIRECCIO` | He definit passos, bloquejos, proves, entorn, backup i criteris d'activacio. | `documentacio/03-canvis-pendents/09-checklist-posada-en-produccio.md` |
| C-033 | Documentacio creada o revisada | He documentat procediments intranet/ecommerce | `DOCUMENTACIO`, `INTRANET`, `ECOMMERCE` | He recollit procediments actuals i canvis esperats per operar amb SIF. | `documentacio/03-canvis-pendents/10-procediments-intranet-ecommerce.md` |
| C-034 | Documentacio creada o revisada | He preparat l'inventari de canvis pendents | `DOCUMENTACIO`, `FEINA FETA` | He convertit buits i decisions pendents en inventari operatiu de canvis. | `documentacio/03-canvis-pendents/11-inventari-canvis-pendents.md` |
| C-035 | Documentacio creada o revisada | He documentat el model BD SIF | `DOCUMENTACIO`, `BD` | He preparat taules, relacions, camps i criteris fiscals del model futur. | `documentacio/04-estat-final/05-model-bd-sif.md` |
| C-036 | Documentacio creada o revisada | He documentat l'estat final del sistema | `DOCUMENTACIO`, `BACKEND` | He descrit el SIF centralitzat i com s'hauria d'integrar amb canals i operacio. | `documentacio/04-estat-final/15-estat-final-sistema.md` |
| C-037 | Documentacio creada o revisada | He documentat l'estat final de pantalles | `DOCUMENTACIO`, `INTRANET` | He definit com han de quedar pantalles d'alumne, administracio, factura i consulta. | `documentacio/04-estat-final/16-estat-final-pantalles.md` |
| C-038 | Documentacio creada o revisada | He documentat relacions entre BD fiscal i BD antiga | `DOCUMENTACIO`, `BD`, `AUDITORIA` | He definit relacions logiques auditades sense acoblaments perillosos entre sistemes. | `documentacio/04-estat-final/17-estat-final-bd-relacions.md` |
| C-039 | Documentacio creada o revisada | He documentat operacio i incidencies finals | `DOCUMENTACIO`, `OPERACIO`, `RISC ALT` | He recollit com tractar errors, baixes, canvis, devolucions i casos que no poden improvisar-se. | `documentacio/04-estat-final/18-estat-final-operacio-incidencies.md` |
| C-040 | Documentacio creada o revisada | He documentat el panell SIF de pay.prisma.cat | `DOCUMENTACIO`, `INTRANET`, `AEAT / XML` | He definit dashboard, factures, registres AEAT, incidencies, documents, versions i configuracio. | `documentacio/04-estat-final/25-panell-sif-pay-prisma.md` |
| C-041 | Documentacio creada o revisada | He preparat el registre de versions i canvis SIF | `DOCUMENTACIO`, `AUDITORIA` | He definit com demostrar quina versio esta activa i quina declaracio responsable li correspon. | `documentacio/05-governanca-operacio/19-registre-versions-i-canvis-sif.md` |
| C-042 | Documentacio creada o revisada | He preparat el pla de proves i validacio SIF | `DOCUMENTACIO`, `TESTING` | He definit bateria de proves, evidencies, resultats esperats i criteris bloquejants. | `documentacio/05-governanca-operacio/20-pla-proves-validacio-sif.md` |
| C-043 | Documentacio creada o revisada | He documentat seguretat, permisos i accessos | `DOCUMENTACIO`, `SEGURETAT / PERMISOS` | He recollit rols interns, auditor/AEAT, permisos i restriccions d'acces. | `documentacio/05-governanca-operacio/21-seguretat-permisos-accessos.md` |
| C-044 | Documentacio creada o revisada | He preparat el manual operatiu intern | `DOCUMENTACIO`, `OPERACIO` | He deixat la base del manual que caldra completar amb procediments finals. | `documentacio/05-governanca-operacio/22-manual-operatiu-intern.md` |
| C-045 | Documentacio creada o revisada | He preparat l'annex de captures finals | `DOCUMENTACIO`, `TESTING` | He definit criteris de captures i evidencies que caldra omplir quan hi hagi pantalles reals. | `documentacio/05-governanca-operacio/23-annex-captures-pantalla.md` |
| C-046 | Documentacio creada o revisada | He preparat el diccionari de camps i valors | `DOCUMENTACIO`, `BD` | He iniciat la normalitzacio de camps fiscals, valors controlats, pagaments, assignacions i relacions. | `documentacio/05-governanca-operacio/24-diccionari-camps-i-valors.md` |
| C-047 | Blocs especialitzats | He definit els blocs especialitzats necessaris | `BLOC ESPECIALITZAT`, `FEINA FETA` | He separat arquitectura, fluxos fiscals, pagaments, intranet, AEAT i proves en blocs de treball diferenciats. | `00-control/mapa-xats.md` |
| C-048 | Blocs especialitzats | He preparat el bloc d'arquitectura SIF/BD | `BLOC ESPECIALITZAT`, `BD`, `BACKEND` | Objectiu: tancar `issueInvoice()`, `registerPayment()`, BD, idempotencia i relacions. | `00-control/mapa-xats.md` |
| C-049 | Blocs especialitzats | He preparat el bloc de fluxos fiscals | `BLOC ESPECIALITZAT`, `LEGAL / AEAT`, `RISC ALT` | Objectiu: compensacio, fraccionats, rectificatives, devolucions, baixes, canvis i factures manuals. | `00-control/mapa-xats.md` |
| C-050 | Blocs especialitzats | He preparat el bloc de pagaments | `BLOC ESPECIALITZAT`, `TPV / REDSYS` | Objectiu: Redsys, transferencies, TPV, callbacks, DS_ORDER i conciliacio. | `00-control/mapa-xats.md` |
| C-051 | Blocs especialitzats | He preparat el bloc d'intranet i pantalles | `BLOC ESPECIALITZAT`, `INTRANET` | Objectiu: pantalles, procediments, permisos, avisos i operacio interna. | `00-control/mapa-xats.md` |
| C-052 | Blocs especialitzats | He preparat el bloc AEAT/documentacio | `BLOC ESPECIALITZAT`, `LEGAL / AEAT` | Objectiu: documentacio SIF, declaracio responsable, certificat, auditor i camps fiscals. | `00-control/mapa-xats.md` |
| C-053 | Blocs especialitzats | He preparat el bloc de proves i produccio | `BLOC ESPECIALITZAT`, `TESTING` | Objectiu: go/no-go, captures, evidencies, backup, incidencies i checklist final. | `00-control/mapa-xats.md` |
| C-054 | Decisions pendents | Decidir dades finals de signatura de direccio | `DECISIO PENDENT`, `DIRECCIO`, `LEGAL / AEAT` | Cal completar dades reals de signatura abans de versio signable. | `00-control/checklist-completitud.md` |
| C-055 | Decisions pendents | Decidir contacte tecnic i tractament de dades personals | `DECISIO PENDENT`, `LEGAL / AEAT`, `DIRECCIO` | Cal decidir si apareixen dades personals al document signat o nomes a expedient intern. | `00-control/checklist-completitud.md` |
| C-056 | Decisions pendents | Confirmar certificat digital o apoderament AEAT | `DECISIO PENDENT`, `DEPENDENCIA EXTERNA`, `LEGAL / AEAT` | Cal tenir certificat/apoderament configurat i provat amb evidencia real. | `documentacio/03-canvis-pendents/09-checklist-posada-en-produccio.md` |
| C-057 | Decisions pendents | Decidir dades fiscals finals d'USOC | `DECISIO PENDENT`, `DADES`, `USOC` | Cal confirmar receptor, dades fiscals i tractament final de la part USOC. | `documentacio/03-canvis-pendents/04-fluxos-facturacio.md` |
| C-058 | Decisions pendents | Confirmar format exacte de QR fiscal | `DECISIO PENDENT`, `PDF / QR`, `LEGAL / AEAT` | Cal validar dades exactes del QR i plantilla visual final. | `documentacio/00-index-i-pla/26-matriu-cobertura-casos.md` |
| C-059 | Decisions pendents | Confirmar esquema XML i entorn AEAT de proves | `DECISIO PENDENT`, `AEAT / XML`, `LEGAL / AEAT` | Cal ajustar el registre AEAT a l'esquema definitiu i entorn disponible. | `documentacio/04-estat-final/25-panell-sif-pay-prisma.md` |
| C-060 | Dades pendents de confirmar | Confirmar subdomini i SSL de pay.prisma.cat | `DEPENDENCIA EXTERNA`, `PENDENT VALIDAR` | Cal verificar configuracio real, certificat SSL i acces segur. | `documentacio/03-canvis-pendents/06-integracio-redsys-pay-prisma.md` |
| C-061 | Dades pendents de confirmar | Confirmar cossos finals de metodes intranet | `PENDENT VALIDAR`, `INTRANET` | Cal revisar cossos reals de cerca, modal, actualitzacio i anul.lacio abans d'implementar. | `documentacio/03-canvis-pendents/10-procediments-intranet-ecommerce.md` |
| C-062 | Dades pendents de confirmar | Confirmar SQL final de packs, grups i regals | `PENDENT VALIDAR`, `BD` | Cal validar taules i camps reals abans de programar integracions SIF. | `documentacio/00-index-i-pla/26-matriu-cobertura-casos.md` |
| C-063 | Riscos / direccio | Documentar risc de duplicar factures per pagament repetit | `RISC ALT`, `DIRECCIO`, `TPV / REDSYS` | El sistema ha de resistir callbacks duplicats, reintents i operacions manuals repetides. | `documentacio/05-governanca-operacio/20-pla-proves-validacio-sif.md` |
| C-064 | Riscos / direccio | Documentar risc de modificar factures ja emeses | `RISC ALT`, `LEGAL / AEAT`, `DIRECCIO` | Cal impedir edicions silencioses i reconduir canvis a rectificatives o events auditats. | `documentacio/04-estat-final/18-estat-final-operacio-incidencies.md` |
| C-065 | Riscos / direccio | Documentar risc de desplegar sense proves reals | `RISC ALT`, `TESTING`, `DIRECCIO` | El projecte no s'ha de posar en produccio sense bateria go/no-go, evidencies i backup provat. | `documentacio/03-canvis-pendents/09-checklist-posada-en-produccio.md` |
| C-066 | Riscos / direccio | Documentar risc de dependencia d'una sola persona | `RISC ALT`, `DIRECCIO`, `OPERACIO` | Cal visibilitzar carrega, interrupcions i necessitat de temps de treball profund. | `documentacio/00-index-i-pla/27-informe-auditoria-documental.md` |
| C-067 | Riscos / direccio | Preparar resum executiu de volum de feina | `DIRECCIO`, `DOCUMENTACIO` | Cal convertir el volum d'analisi, documentacio, decisions i programacio pendent en missatge de direccio. | Aquest document |
| C-068 | Decisions ja preses | He definit criteri de traspas historic per factures Asso/SL | `DECISIO PRESA`, `MIGRACIO`, `RISC ALT` | Les factures antigues amb numeracio compartida o barrejada entre Associacio i SL es conservaran com a historic, no com a factures SIF productives. | Aquest document |
| C-069 | Decisions ja preses | He identificat el risc de llegir `factures.entitat` com a emissor fiscal | `DECISIO PRESA`, `BD`, `RISC ALT` | En la taula antiga, `entitat` reflecteix l'entitat del pagament/transmissio i no permet reconstruir netament una separacio fiscal Asso/SL. | Aquest document |
| C-070 | Riscos / direccio | Documentar risc de migrar numeracio historica Asso/SL com si fos SIF | `RISC ALT`, `MIGRACIO`, `LEGAL / AEAT` | No s'ha de regenerar, renumerar ni enviar retroactivament a VERI*FACTU una numeracio historica que no separava correctament emissors. | Aquest document |

---

# Tauler 2 - VeriFactu / SIF ▶ Casos d'us

## Llistes

1. `Inbox casos d'us`
2. `Disseny cobert`
3. `Parcial / falta detall`
4. `Pendent d'analisi`
5. `Pendent d'implementacio`
6. `Pendent de proves`
7. `Amb risc fiscal`
8. `Bloquejat per decisio o dada`
9. `Validat / tancat`

## Etiquetes

- `CURS NORMAL`
- `TPV / REDSYS`
- `TRANSFERENCIA`
- `ECOMMERCE`
- `INTRANET`
- `EMPRESA / RESPONSABLE`
- `PACK`
- `GRUP`
- `REGAL`
- `USOC`
- `DESCOMPTE / PROMO`
- `COMPENSACIO / SALDO`
- `RECTIFICATIVA`
- `DEVOLUCIO / BAIXA`
- `FACTURA MANUAL`
- `PDF / QR`
- `RISC FISCAL`
- `DISSENY COBERT`
- `PENDENT PROVAR`

## Targetes de casos d'us documentats o analitzats

| ID | Llista | Titol | Etiquetes | Descripcio resum | Documents relacionats |
|---|---|---|---|---|---|
| U-001 | Disseny cobert | He analitzat i documentat curs normal amb Redsys | `CURS NORMAL`, `TPV / REDSYS`, `DISSENY COBERT` | He identificat `realitzaPagamentAutomatic.php`, `DS_ORDER`, `IDPAG`, factura, inscripcio i risc de duplicat. | `04-fluxos-facturacio.md`, `06-integracio-redsys-pay-prisma.md` |
| U-002 | Pendent d'implementacio | Programar flux SIF per curs normal Redsys | `CURS NORMAL`, `TPV / REDSYS` | Adaptar callback i generacio fiscal a `issueInvoice()` i `registerPayment()`. | `06-integracio-redsys-pay-prisma.md` |
| U-003 | Pendent de proves | Provar curs normal Redsys amb pagament correcte i callback repetit | `CURS NORMAL`, `TPV / REDSYS`, `PENDENT PROVAR` | Validar que no hi ha duplicats i que PDF/registre queden coherents. | `20-pla-proves-validacio-sif.md` |
| U-004 | Disseny cobert | He documentat taller com a variant facturable | `ECOMMERCE`, `DISSENY COBERT` | He deixat taller com a variant de curs amb concepte propi i payload especific. | `04-fluxos-facturacio.md` |
| U-005 | Disseny cobert | He documentat jornada com a variant facturable | `ECOMMERCE`, `DISSENY COBERT` | He deixat jornada com a variant de curs amb concepte propi i payload especific. | `04-fluxos-facturacio.md` |
| U-006 | Disseny cobert | He analitzat i documentat packs | `PACK`, `ECOMMERCE`, `DISSENY COBERT` | Una factura per pagament real, una linia per curs, `IDPAG` agrupador i descompte pack traçat. | `04-fluxos-facturacio.md`, `15-estat-final-sistema.md` |
| U-007 | Pendent d'implementacio | Programar flux SIF per packs | `PACK`, `ECOMMERCE` | Implementar linies, relacions d'inscripcio, descompte i idempotencia per pack. | `05-model-bd-sif.md` |
| U-008 | Pendent de proves | Provar packs amb dues inscripcions i una factura | `PACK`, `PENDENT PROVAR` | Validar imports, linies, PDF, relacions i absencia de duplicats. | `20-pla-proves-validacio-sif.md` |
| U-009 | Disseny cobert | He analitzat i documentat grups de persones | `GRUP`, `EMPRESA / RESPONSABLE`, `DISSENY COBERT` | Una factura per pagament, una linia per participant i receptor empresa/escola o responsable. | `04-fluxos-facturacio.md`, `10-procediments-intranet-ecommerce.md` |
| U-010 | Pendent d'implementacio | Programar flux SIF per grups | `GRUP`, `EMPRESA / RESPONSABLE` | Implementar relacio participants, receptor fiscal, imports i descompte grup. | `05-model-bd-sif.md` |
| U-011 | Pendent de proves | Provar grups amb factura a responsable o empresa | `GRUP`, `PENDENT PROVAR` | Validar dades fiscals, linies per participant i permisos de consulta. | `20-pla-proves-validacio-sif.md` |
| U-012 | Disseny cobert | He analitzat i documentat regals | `REGAL`, `ECOMMERCE`, `DISSENY COBERT` | Factura al comprador, `SOURCE_TYPE = REGAL`, codi regal i bescanvi posterior sense factura nova. | `04-fluxos-facturacio.md` |
| U-013 | Pendent d'implementacio | Programar flux SIF per regals | `REGAL`, `ECOMMERCE` | Implementar compra, factura comprador, relacio regal i bescanvi sense nova emissio. | `05-model-bd-sif.md` |
| U-014 | Pendent de proves | Provar regal amb compra i bescanvi posterior | `REGAL`, `PENDENT PROVAR` | Validar que el bescanvi no genera factura duplicada. | `20-pla-proves-validacio-sif.md` |
| U-015 | Disseny cobert | He analitzat i documentat USOC | `USOC`, `DESCOMPTE / PROMO`, `DISSENY COBERT` | Validacio manual, factura alumne per la seva part i factura USOC per la diferencia. | `04-fluxos-facturacio.md` |
| U-016 | Bloquejat per decisio o dada | Confirmar dades fiscals i criteri final USOC | `USOC`, `BLOQUEJANT` | Cal confirmar dades fiscals, tractament de l'anticipi i evidencies. | `26-matriu-cobertura-casos.md` |
| U-017 | Disseny cobert | He analitzat empresa o responsable que paga inscripcions | `EMPRESA / RESPONSABLE`, `INTRANET`, `DISSENY COBERT` | Factura abans de cobrament, URL empresa i registre posterior del pagament. | `10-procediments-intranet-ecommerce.md` |
| U-018 | Disseny cobert | He analitzat transferencia validada a intranet | `TRANSFERENCIA`, `INTRANET`, `DISSENY COBERT` | He recuperat `efectuarPagament()`, `mostrarModalConfPag()`, `updFactGenerada` i criteri SIF final. | `04-fluxos-facturacio.md`, `06-integracio-redsys-pay-prisma.md` |
| U-019 | Pendent d'implementacio | Programar registre SIF de transferencia validada | `TRANSFERENCIA`, `INTRANET` | Usar `registerPayment()` si ja hi ha factura i `issueInvoice()` + pagament si encara no existeix. | `05-model-bd-sif.md` |
| U-020 | Pendent de proves | Provar transferencia amb factura existent i sense factura existent | `TRANSFERENCIA`, `PENDENT PROVAR` | Validar idempotencia, referencia bancaria i sincronitzacio amb BD antiga. | `20-pla-proves-validacio-sif.md` |
| U-021 | Disseny cobert | He tancat el criteri de compensacio i saldo | `COMPENSACIO / SALDO`, `DISSENY COBERT` | El saldo no edita imports, pot crear `credit_balance` i genera rectificativa si redueix factura emesa. | `04-fluxos-facturacio.md`, `18-estat-final-operacio-incidencies.md` |
| U-022 | Pendent d'implementacio | Programar compensacio i saldo | `COMPENSACIO / SALDO` | Implementar saldo, assignacio, rectificativa quan pertoqui i auditoria. | `05-model-bd-sif.md` |
| U-023 | Disseny cobert | He tancat criteri de pagaments fraccionats | `TPV / REDSYS`, `DISSENY COBERT` | Cada fraccio es `payment_transaction`, cada assignacio es `payment_allocation`, `FRACCIO` nomes resum historic. | `04-fluxos-facturacio.md` |
| U-024 | Pendent d'implementacio | Programar pagaments fraccionats | `TPV / REDSYS` | Implementar fraccions, assignacions parcials i estat de cobrament. | `05-model-bd-sif.md` |
| U-025 | Parcial / falta detall | He identificat pagament de morositat o reclamacio | `INTRANET`, `RISC FISCAL` | Hi ha rutes, plantilles i consultes internes, pero falten metodes concrets i URL de pagament. | `04-fluxos-facturacio.md`, `08-correus-i-plantilles.md` |
| U-026 | Disseny cobert | He documentat factura ordinaria serie A | `RISC FISCAL`, `DISSENY COBERT` | Emissio ordinaria amb numero fiscal, linies, registre i payload final. | `05-model-bd-sif.md` |
| U-027 | Disseny cobert | He tancat criteri de rectificatives serie R | `RECTIFICATIVA`, `DISSENY COBERT` | Serie `R`, factura rectificada, motiu controlat i mode diferencies/substitucio. | `04-fluxos-facturacio.md`, `18-estat-final-operacio-incidencies.md` |
| U-028 | Pendent d'implementacio | Programar motor de rectificatives | `RECTIFICATIVA` | Decidir quan toca rectificativa, saldo, devolucio, nova factura o bloqueig. | `18-estat-final-operacio-incidencies.md` |
| U-029 | Pendent de proves | Provar rectificatives per diferencia, substitucio i anul.lacio | `RECTIFICATIVA`, `PENDENT PROVAR` | Validar imports, vinculacio, PDF i traça d'auditoria. | `20-pla-proves-validacio-sif.md` |
| U-030 | Disseny cobert | He documentat factura abans de cobrament | `EMPRESA / RESPONSABLE`, `INTRANET`, `DISSENY COBERT` | Flux d'intranet per generar factura abans del pagament i vincular cobrament posterior. | `10-procediments-intranet-ecommerce.md` |
| U-031 | Amb risc fiscal | Controlar risc de doble factura en factura abans de pagar | `RISC FISCAL`, `EMPRESA / RESPONSABLE` | Si despres es passa pagament, el sistema ha de detectar factura previa i registrar nomes cobrament. | `26-matriu-cobertura-casos.md` |
| U-032 | Disseny cobert | He tancat criteri de devolucions | `DEVOLUCIO / BAIXA`, `DISSENY COBERT` | `REFUND` com moviment economic i rectificativa si redueix factura emesa. | `04-fluxos-facturacio.md` |
| U-033 | Pendent d'implementacio | Programar devolucions Redsys, transferencia i manual | `DEVOLUCIO / BAIXA` | Implementar moviment economic, rectificativa quan pertoqui i evidencia. | `18-estat-final-operacio-incidencies.md` |
| U-034 | Disseny cobert | He tancat criteri de baixes | `DEVOLUCIO / BAIXA`, `DISSENY COBERT` | Baixa com event administratiu, amb decisio posterior de retorn, saldo o no retorn. | `04-fluxos-facturacio.md` |
| U-035 | Disseny cobert | He tancat criteri de canvis de curs | `INTRANET`, `RECTIFICATIVA`, `DISSENY COBERT` | Historic obligatori, diferencia si mes car, retorn/saldo si mes barat i motius documentats. | `04-fluxos-facturacio.md`, `07-pantalles-intranet.md` |
| U-036 | Pendent d'implementacio | Programar canvi de curs amb criteri SIF | `INTRANET`, `RECTIFICATIVA` | Implementar historic, previsualitzacio, diferencies, saldo i rectificativa si cal. | `18-estat-final-operacio-incidencies.md` |
| U-037 | Disseny cobert | He documentat factura manual | `FACTURA MANUAL`, `INTRANET`, `DISSENY COBERT` | Pantalla autoritzada que crida `issueInvoice()`, linies estructurades, snapshot fiscal i pagament opcional. | `04-fluxos-facturacio.md` |
| U-038 | Pendent d'implementacio | Programar pantalla de factura manual | `FACTURA MANUAL`, `INTRANET` | Crear formulari, validacions fiscals, linies, permisos, PDF i correus. | `07-pantalles-intranet.md` |
| U-039 | Disseny cobert | He aclarit ubicacio de factura electronica `E_FACT` | `INTRANET`, `DISSENY COBERT` | Ubicacio a `Consulta - Edita - Anula factura`; permisos Meriem, Adam i Pablo. | `07-pantalles-intranet.md` |
| U-040 | Parcial / falta detall | Completar criteri final de QR fiscal | `PDF / QR`, `RISC FISCAL` | Falta confirmar dades exactes i plantilla visual final. | `11-inventari-canvis-pendents.md` |
| U-041 | Parcial / falta detall | Completar XML o registre AEAT | `LEGAL / AEAT`, `RISC FISCAL` | Falta esquema XML definitiu i entorn de proves AEAT. | `25-panell-sif-pay-prisma.md` |
| U-042 | Disseny cobert | He tancat criteri de migracio de factures historiques | `RISC FISCAL`, `DISSENY COBERT` | Migracio com `NO_VERIFACTU`, sense hash ni registre AEAT retroactiu. | `04-fluxos-facturacio.md` |
| U-043 | Pendent d'implementacio | Programar migracio de factures historiques | `BD`, `RISC FISCAL` | Implementar import historic, conservacio numeracio, relacions i informe de control. | `15-estat-final-sistema.md` |
| U-052 | Disseny cobert | MIGRACIO HISTORICA ▶ factures.entitat ▶ documentar significat real | `RISC FISCAL`, `BD`, `DISSENY COBERT` | Documentar que `factures.entitat` al sistema antic identifica l'entitat vinculada al pagament/transmissio, no una classificacio fiscal neta d'emissor Asso/SL. | Aquest document |
| U-053 | Disseny cobert | MIGRACIO HISTORICA ▶ Asso/SL ▶ conservar numeracio antiga com historic | `RISC FISCAL`, `MIGRACIO`, `DISSENY COBERT` | Les factures antigues amb numeracio no separada correctament entre Associacio i SL es deixaran com a historic i no es normalitzaran com a SIF productiu. | Aquest document |
| U-054 | Pendent d'implementacio | MIGRACIO HISTORICA ▶ marcar factures antigues com `NO_VERIFACTU` | `MIGRACIO`, `BD` | Assignar estat historic/no VERI*FACTU a factures antigues sense generar hash, QR, registre AEAT ni nova numeracio. | `15-estat-final-sistema.md` |
| U-055 | Pendent de proves | MIGRACIO HISTORICA ▶ validar que Asso/SL no es renumera | `MIGRACIO`, `PENDENT PROVAR`, `RISC FISCAL` | Provar que el traspas conserva consulta historica sense alterar numeracio ni reinterpretar emissors antics. | `20-pla-proves-validacio-sif.md` |
| U-044 | Parcial / falta detall | Completar descompte Alumne PrisMa | `DESCOMPTE / PROMO` | Falta formula final i snapshot fiscal a linies de factura. | `documentacio-verifactu.md` |
| U-045 | Parcial / falta detall | Completar Carnet Jove | `DESCOMPTE / PROMO` | Falta verificacio/API i text visible final a factura. | `documentacio-verifactu.md` |
| U-046 | Parcial / falta detall | Completar descomptes sensibles | `DESCOMPTE / PROMO`, `RISC FISCAL` | Discapacitat, familia nombrosa, monoparental i violencia de genere han de mostrar text generic i motiu intern sensible. | `documentacio-verifactu.md` |
| U-047 | Disseny cobert | He analitzat promocions temporals | `DESCOMPTE / PROMO`, `DISSENY COBERT` | Tipus `11-99` i snapshot a `factura_linia`; falta implementacio i proves. | `04-fluxos-facturacio.md` |
| U-048 | Disseny cobert | He analitzat codis promocionals | `DESCOMPTE / PROMO`, `ECOMMERCE`, `DISSENY COBERT` | Taula `promocions`, codi, caducitat, us, percentatge/import i snapshot. | `10-procediments-intranet-ecommerce.md` |
| U-049 | Parcial / falta detall | Completar descompte de grup | `DESCOMPTE / PROMO`, `GRUP` | Falta relacio final amb `descomptes_grup`, linies i preu per participant. | `04-fluxos-facturacio.md` |
| U-050 | Disseny cobert | He tancat criteri de descompte excepcional per canvi de curs | `DESCOMPTE / PROMO`, `RECTIFICATIVA`, `DISSENY COBERT` | No tocar `A_PAGAR` sense rastre; usar motiu intern i rectificativa si afecta factura emesa. | `11-inventari-canvis-pendents.md` |
| U-051 | Disseny cobert | He documentat linies de factura | `PDF / QR`, `BD`, `DISSENY COBERT` | Linies necessaries per cursos, packs, grups, descomptes i rectificatives. | `05-model-bd-sif.md` |

## Targetes d'apartats nous o modificats de la intranet

| ID | Llista | Titol | Etiquetes | Descripcio resum | Documents relacionats |
|---|---|---|---|---|---|
| I-001 | Disseny cobert | He analitzat Consulta - Modifica alumne | `INTRANET`, `DISSENY COBERT` | He revisat pantalla, dades personals, inscripcions, observacions i accions que poden afectar factura. | `07-pantalles-intranet.md` |
| I-002 | Pendent d'implementacio | Programar adaptacions SIF a Consulta - Modifica alumne | `INTRANET` | Bloquejar o reconduir accions fiscals quan hi hagi factura emesa. | `16-estat-final-pantalles.md` |
| I-003 | Disseny cobert | He documentat dades del curs i dades de pagament | `INTRANET`, `DISSENY COBERT` | Cal mostrar estats finals de URL, factura i pagament. | `07-pantalles-intranet.md` |
| I-004 | Disseny cobert | He analitzat Veure factura | `INTRANET`, `PDF / QR`, `DISSENY COBERT` | Vista final amb VERI*FACTU/no VERI*FACTU, PDF i QR. | `07-pantalles-intranet.md` |
| I-005 | Disseny cobert | He analitzat Passar pagaments | `INTRANET`, `TRANSFERENCIA`, `DISSENY COBERT` | He identificat modal, validacio de pagament, factura existent i risc de mutacio indeguda. | `10-procediments-intranet-ecommerce.md` |
| I-006 | Pendent d'implementacio | Programar Passar pagaments amb `registerPayment()` | `INTRANET`, `TRANSFERENCIA` | Registrar cobrament, assignacio i sincronitzacio historica nomes despres d'exit SIF. | `05-model-bd-sif.md` |
| I-007 | Disseny cobert | He analitzat Generar factura abans de pagar | `INTRANET`, `EMPRESA / RESPONSABLE`, `DISSENY COBERT` | Flux critic per empresa/responsable i pagament posterior. | `10-procediments-intranet-ecommerce.md` |
| I-008 | Pendent d'implementacio | Programar Generar factura abans de pagar amb `issueInvoice()` | `INTRANET`, `EMPRESA / RESPONSABLE` | Generar factura fiscal real, PDF/QR i relacio posterior amb cobrament. | `05-model-bd-sif.md` |
| I-009 | Disseny cobert | He analitzat Consulta - Edita - Anula factura | `INTRANET`, `RECTIFICATIVA`, `DISSENY COBERT` | He identificat `updDadesFact` i `anularFactura()` historica; cal reconduir a SIF. | `10-procediments-intranet-ecommerce.md` |
| I-010 | Pendent d'implementacio | Programar Consulta - Edita - Anula factura amb criteri SIF | `INTRANET`, `RECTIFICATIVA` | Bloquejar edicio directa i crear rectificatives/anul.lacions controlades. | `18-estat-final-operacio-incidencies.md` |
| I-011 | Disseny cobert | He analitzat Analitzar fitxer TPV / comprovar IDPAGs | `INTRANET`, `TPV / REDSYS`, `DISSENY COBERT` | Format CSV, resposta JSON, auditoria, reprocessament i deteccio d'incidencies. | `10-procediments-intranet-ecommerce.md` |
| I-012 | Pendent d'implementacio | Programar conciliacio TPV i comprovacio IDPAGs | `INTRANET`, `TPV / REDSYS` | Importar CSV, comparar amb notificacions, marcar incidencies i evitar duplicats. | `20-pla-proves-validacio-sif.md` |
| I-013 | Disseny cobert | He documentat apartat VERI*FACTU dins intranet | `INTRANET`, `DISSENY COBERT` | Ha de mostrar indicador, resum i enllac al panell SIF. | `07-pantalles-intranet.md` |
| I-014 | Pendent d'implementacio | Programar indicador VERI*FACTU dins intranet | `INTRANET` | Crear endpoint resum, estat visible i navegacio cap al panell SIF. | `25-panell-sif-pay-prisma.md` |
| I-015 | Disseny cobert | He documentat intranet alumne | `INTRANET`, `DISSENY COBERT` | Alumne veu factures propies; no veu factures completes d'empresa/grup si no n'es receptor. | `16-estat-final-pantalles.md` |
| I-016 | Pendent d'implementacio | Programar acces alumne a factures propies | `INTRANET`, `SEGURETAT / PERMISOS` | Aplicar permisos, PDF segur i restriccions per empresa/grup. | `21-seguretat-permisos-accessos.md` |
| I-017 | Disseny cobert | He documentat empresa/responsable sense intranet principal | `EMPRESA / RESPONSABLE`, `DISSENY COBERT` | Consulta per correu, enllac segur o espai futur, amb PDF servit per permisos. | `22-manual-operatiu-intern.md` |
| I-018 | Parcial / falta detall | Completar descarrega de factures i registres | `INTRANET`, `PDF / QR` | Falta format d'exportacio i permisos finals. | `25-panell-sif-pay-prisma.md` |
| I-019 | Disseny cobert | He documentat permisos Meriem, Adam i Pablo | `INTRANET`, `SEGURETAT / PERMISOS` | Cal aplicar permisos reals en codi, BD, pantalles i procediments. | `21-seguretat-permisos-accessos.md` |
| I-020 | Parcial / falta detall | Completar procediment auditor/AEAT a intranet o SIF | `LEGAL / AEAT`, `SEGURETAT / PERMISOS` | Falta procediment final d'activacio temporal i registre d'accessos. | `21-seguretat-permisos-accessos.md` |

---

# Tauler 3 - VeriFactu / SIF ▶ Desenvolupament i proves

## Llistes

1. `Preparacio tecnica`
2. `BD / migracions`
3. `Backend SIF / API`
4. `Numeracio / hash / idempotencia`
5. `Pagaments / Redsys / TPV`
6. `Ecommerce`
7. `Intranet`
8. `Rectificatives / anul.lacions`
9. `PDF / QR / documents fiscals`
10. `Errors / reintents / auditoria`
11. `AEAT / XML / declaracio responsable`
12. `Testing / evidencies`
13. `Migracio / posada en produccio`
14. `Bloquejat`
15. `Implementat / validat`

## Etiquetes

- `PENDENT PROGRAMAR`
- `PENDENT PROVAR`
- `PENDENT DOCUMENTAR`
- `IMPLEMENTAT`
- `VALIDAT`
- `BACKEND`
- `BD`
- `API SIF`
- `TPV / REDSYS`
- `ECOMMERCE`
- `INTRANET`
- `PDF / QR`
- `AEAT / XML`
- `RECTIFICATIVES`
- `AUDITORIA / LOGS`
- `TESTING`
- `SEGURETAT / PERMISOS`
- `MIGRACIO`
- `RISC ALT`
- `BLOQUEJANT`

## Targetes

| ID | Llista | Titol | Etiquetes | Descripcio resum | Documents relacionats |
|---|---|---|---|---|---|
| D-001 | Preparacio tecnica | Preparar estructura base del modul SIF | `PENDENT PROGRAMAR`, `BACKEND`, `API SIF` | Crear ubicacio, configuracio, serveis, errors i contractes base del SIF. | `15-estat-final-sistema.md` |
| D-002 | BD / migracions | Programar taula de factures fiscals SIF | `PENDENT PROGRAMAR`, `BD` | Crear taula principal de factura fiscal immutable. | `05-model-bd-sif.md` |
| D-003 | BD / migracions | Programar taula de linies de factura | `PENDENT PROGRAMAR`, `BD` | Crear linies per cursos, packs, grups, descomptes i rectificatives. | `05-model-bd-sif.md` |
| D-004 | BD / migracions | Programar taula de sequencies fiscals | `PENDENT PROGRAMAR`, `BD` | Controlar series, anys, concurrencia i numeracio sense duplicats. | `05-model-bd-sif.md` |
| D-005 | BD / migracions | Programar taula d'idempotencia | `PENDENT PROGRAMAR`, `BD`, `RISC ALT` | Evitar duplicats per callback, reintent, doble clic o importacio repetida. | `05-model-bd-sif.md` |
| D-006 | BD / migracions | Programar `payment_transaction` | `PENDENT PROGRAMAR`, `BD` | Registrar cobraments Redsys, transferencia, manual, devolucions i compensacions. | `05-model-bd-sif.md` |
| D-007 | BD / migracions | Programar `payment_allocation` | `PENDENT PROGRAMAR`, `BD` | Vincular cobraments amb factures o saldos parcialment o totalment. | `05-model-bd-sif.md` |
| D-008 | BD / migracions | Programar relacions auditades amb BD antiga | `PENDENT PROGRAMAR`, `BD`, `AUDITORIA / LOGS` | Implementar `fact_rels` o mecanisme equivalent sense FK directa entre BDs. | `17-estat-final-bd-relacions.md` |
| D-009 | BD / migracions | Programar cua fiscal o cua AEAT | `PENDENT PROGRAMAR`, `BD`, `AEAT / XML` | Guardar operacions pendents, intents, errors i estat d'enviament. | `25-panell-sif-pay-prisma.md` |
| D-010 | BD / migracions | Programar auditoria del SIF | `PENDENT PROGRAMAR`, `AUDITORIA / LOGS` | Registrar accions, usuaris, errors, reintents, canvis d'estat i consultes sensibles. | `21-seguretat-permisos-accessos.md` |
| D-011 | Backend SIF / API | Programar `issueInvoice()` | `PENDENT PROGRAMAR`, `BACKEND`, `API SIF` | Crear factura fiscal amb numero, linies, hash, registre, PDF/QR i relacions. | `05-model-bd-sif.md` |
| D-012 | Backend SIF / API | Programar `registerPayment()` | `PENDENT PROGRAMAR`, `BACKEND`, `API SIF` | Registrar cobrament sense numero fiscal ni hash de factura. | `05-model-bd-sif.md` |
| D-013 | Backend SIF / API | Programar emissio conjunta factura + pagament | `PENDENT PROGRAMAR`, `BACKEND`, `RISC ALT` | Permetre que una compra pagada generi factura i cobrament dins una operacio idempotent. | `05-model-bd-sif.md` |
| D-014 | Backend SIF / API | Programar consulta de factura SIF | `PENDENT PROGRAMAR`, `BACKEND`, `API SIF` | Consultar factura, linies, estat, PDF, QR, pagaments i relacions. | `25-panell-sif-pay-prisma.md` |
| D-015 | Backend SIF / API | Programar validacio abans d'emissio | `PENDENT PROGRAMAR`, `BACKEND`, `RISC ALT` | Validar dades fiscals, import, receptor, linies, duplicats i permisos. | `05-model-bd-sif.md` |
| D-016 | Numeracio / hash / idempotencia | Programar servei de numeracio fiscal | `PENDENT PROGRAMAR`, `BD`, `RISC ALT` | Generar series A/R amb bloqueig transaccional i sense duplicats. | `05-model-bd-sif.md` |
| D-017 | Numeracio / hash / idempotencia | Programar hash chain | `PENDENT PROGRAMAR`, `BACKEND`, `LEGAL / AEAT` | Calcular hash, hash anterior i verificacio d'integritat. | `05-model-bd-sif.md` |
| D-018 | Numeracio / hash / idempotencia | Programar servei d'idempotencia | `PENDENT PROGRAMAR`, `BACKEND`, `RISC ALT` | Deduplicar per `DS_ORDER`, referencia bancaria, factura/data/import i claus internes. | `06-integracio-redsys-pay-prisma.md` |
| D-019 | Numeracio / hash / idempotencia | Programar verificacio d'integritat de cadena | `PENDENT PROGRAMAR`, `AUDITORIA / LOGS` | Detectar trencaments, registres alterats o inconsistencies. | `25-panell-sif-pay-prisma.md` |
| D-020 | Pagaments / Redsys / TPV | Programar entrada Redsys a `redsys_notifications` | `PENDENT PROGRAMAR`, `TPV / REDSYS` | Guardar notificacions, signatures, estat i errors abans d'emetre o registrar. | `06-integracio-redsys-pay-prisma.md` |
| D-021 | Pagaments / Redsys / TPV | Programar callback Redsys cap al SIF | `PENDENT PROGRAMAR`, `TPV / REDSYS`, `BACKEND` | Transformar callback en factura/pagament idempotent. | `06-integracio-redsys-pay-prisma.md` |
| D-022 | Pagaments / Redsys / TPV | Programar conciliacio TPV amb CSV | `PENDENT PROGRAMAR`, `TPV / REDSYS`, `INTRANET` | Comparar fitxer TPV amb notificacions, IDPAG i factures. | `10-procediments-intranet-ecommerce.md` |
| D-023 | Pagaments / Redsys / TPV | Programar incidencia: pagament correcte pero factura pendent | `PENDENT PROGRAMAR`, `RISC ALT`, `TPV / REDSYS` | Visibilitzar i reintentar factures que no s'han pogut completar despres del cobrament. | `18-estat-final-operacio-incidencies.md` |
| D-024 | Pagaments / Redsys / TPV | Programar transferencies i pagaments manuals | `PENDENT PROGRAMAR`, `TRANSFERENCIA`, `INTRANET` | Registrar referencia, banc, data, import i assignacio amb idempotencia. | `04-fluxos-facturacio.md` |
| D-025 | Ecommerce | Programar payload fiscal des de checkout | `PENDENT PROGRAMAR`, `ECOMMERCE` | Preparar receptor, linies, imports, descomptes i origen per al SIF. | `04-fluxos-facturacio.md` |
| D-026 | Ecommerce | Programar validacions fiscals al formulari | `PENDENT PROGRAMAR`, `ECOMMERCE`, `RISC ALT` | Validar dades necessaries abans de pagament o emissio. | `12-documentacio-sistema-ecommerce-intranet-sif.md` |
| D-027 | Ecommerce | Programar guardat de resposta SIF a ecommerce | `PENDENT PROGRAMAR`, `ECOMMERCE`, `BD` | Guardar UUID/ID fiscal, numero, estat i relacions amb inscripcio. | `17-estat-final-bd-relacions.md` |
| D-028 | Intranet | Programar panell SIF a `pay.prisma.cat/sif` | `PENDENT PROGRAMAR`, `INTRANET` | Dashboard, factures, registres AEAT, incidencies, documents, versions i configuracio. | `25-panell-sif-pay-prisma.md` |
| D-029 | Intranet | Programar permisos SIF per administracio | `PENDENT PROGRAMAR`, `SEGURETAT / PERMISOS`, `INTRANET` | Aplicar permisos Meriem/Adam/Pablo i restriccions d'accio. | `21-seguretat-permisos-accessos.md` |
| D-030 | Intranet | Programar bloqueig d'edicio fiscal despres d'emissio | `PENDENT PROGRAMAR`, `INTRANET`, `RISC ALT` | Evitar canvis directes de receptor, import o dades fiscals de factura emesa. | `18-estat-final-operacio-incidencies.md` |
| D-031 | Rectificatives / anul.lacions | Programar rectificativa per diferencia d'import | `PENDENT PROGRAMAR`, `RECTIFICATIVES` | Crear linies rectificatives, vincular original i generar PDF. | `04-fluxos-facturacio.md` |
| D-032 | Rectificatives / anul.lacions | Programar rectificativa per anul.lacio total | `PENDENT PROGRAMAR`, `RECTIFICATIVES` | Anul.lar fiscalment sense eliminar ni modificar la factura original. | `18-estat-final-operacio-incidencies.md` |
| D-033 | Rectificatives / anul.lacions | Programar tractament de canvi de titular fiscal | `PENDENT PROGRAMAR`, `RECTIFICATIVES`, `INTRANET` | Decidir i aplicar rectificativa/nova factura segons cas. | `18-estat-final-operacio-incidencies.md` |
| D-034 | PDF / QR / documents fiscals | Programar generacio de PDF fiscal immutable | `PENDENT PROGRAMAR`, `PDF / QR` | Generar PDF des de dades fiscals guardades i evitar regeneracio inconsistent. | `08-correus-i-plantilles.md` |
| D-035 | PDF / QR / documents fiscals | Programar generacio de QR | `PENDENT PROGRAMAR`, `PDF / QR`, `LEGAL / AEAT` | Generar, guardar i inserir QR fiscal llegible. | `11-inventari-canvis-pendents.md` |
| D-036 | PDF / QR / documents fiscals | Programar conservacio segura de PDFs | `PENDENT PROGRAMAR`, `PDF / QR`, `AUDITORIA / LOGS` | Definir ruta, nom, permisos, hash i recuperacio. | `25-panell-sif-pay-prisma.md` |
| D-037 | AEAT / XML / declaracio responsable | Programar o preparar registre AEAT/XML | `PENDENT PROGRAMAR`, `AEAT / XML` | Preparar estructura d'enviament o registre segons especificacio final. | `25-panell-sif-pay-prisma.md` |
| D-038 | AEAT / XML / declaracio responsable | Completar documentacio tecnica SIF final | `PENDENT DOCUMENTAR`, `LEGAL / AEAT` | Ajustar documentacio a la versio implementada, no nomes al disseny. | `documentacio-sif-aeat.md` |
| D-039 | AEAT / XML / declaracio responsable | Generar declaracio responsable `1.0.0` signable | `PENDENT DOCUMENTAR`, `LEGAL / AEAT`, `DIRECCIO` | Preparar document tancat quan hi hagi versio real, provada i documentada. | `declaracio-responsable-sif-prisma.md` |
| D-040 | AEAT / XML / declaracio responsable | Publicar declaracio responsable signada dins del SIF | `PENDENT DOCUMENTAR`, `LEGAL / AEAT` | Fer accessible la declaracio signada al panell SIF amb versio corresponent. | `25-panell-sif-pay-prisma.md` |
| D-041 | Errors / reintents / auditoria | Programar worker de cua fiscal | `PENDENT PROGRAMAR`, `AUDITORIA / LOGS`, `BACKEND` | Reintentar operacions pendents o fallides respectant idempotencia. | `18-estat-final-operacio-incidencies.md` |
| D-042 | Errors / reintents / auditoria | Programar classificacio d'errors | `PENDENT PROGRAMAR`, `AUDITORIA / LOGS` | Validacio, dades fiscals, numeracio, hash, PDF, QR, connexio, permisos i desconegut. | `18-estat-final-operacio-incidencies.md` |
| D-043 | Errors / reintents / auditoria | Programar visibilitat d'incidencies a intranet/SIF | `PENDENT PROGRAMAR`, `INTRANET`, `AUDITORIA / LOGS` | Mostrar errors, prioritat, reintent controlat, comentaris i usuari. | `25-panell-sif-pay-prisma.md` |
| D-044 | Testing / evidencies | Preparar entorn de preproduccio o mode test separat | `PENDENT PROGRAMAR`, `TESTING`, `RISC ALT` | BD, configuracio, logs, TPV simulat i dades ficticies sense tocar produccio. | `09-checklist-posada-en-produccio.md` |
| D-045 | Testing / evidencies | Executar bateria go/no-go | `PENDENT PROVAR`, `TESTING` | Executar proves bloquejants amb resultat PASS/FAIL/BLOCKED i evidencia. | `20-pla-proves-validacio-sif.md` |
| D-046 | Testing / evidencies | Preparar captures finals i logs reals | `PENDENT PROVAR`, `TESTING`, `DOCUMENTACIO` | Guardar captures finals, logs i evidencies per expedient de versio. | `23-annex-captures-pantalla.md` |
| D-047 | Testing / evidencies | Provar backup i restauracio amb acta real | `PENDENT PROVAR`, `TESTING`, `RISC ALT` | Executar restauracio minima i conservar evidencia real. | `09-checklist-posada-en-produccio.md` |
| D-048 | Testing / evidencies | Provar concurrencia de numeracio i hash | `PENDENT PROVAR`, `BD`, `RISC ALT` | Simular emissions simultanies, rollback i series diferents. | `20-pla-proves-validacio-sif.md` |
| D-049 | Testing / evidencies | Provar casos d'us principals end-to-end | `PENDENT PROVAR`, `TESTING` | Curs normal, pack, grup, regal, USOC, transferencia, factura manual i rectificativa. | `26-matriu-cobertura-casos.md` |
| D-050 | Migracio / posada en produccio | Definir data d'inici SIF | `PENDENT VALIDAR`, `MIGRACIO`, `DIRECCIO` | Decidir data de tall, convivencia i tractament de factures antigues. | `15-estat-final-sistema.md` |
| D-051 | Migracio / posada en produccio | Programar migracio o consulta de factures antigues | `PENDENT PROGRAMAR`, `MIGRACIO`, `BD` | Conservar historic `NO_VERIFACTU` i diferenciar-lo del SIF nou. | `04-fluxos-facturacio.md` |
| D-052 | Migracio / posada en produccio | Preparar pla de desplegament per fases | `PENDENT DOCUMENTAR`, `MIGRACIO`, `DIRECCIO` | Decidir pilot, rollback, monitoritzacio, responsables i data de tall. | `09-checklist-posada-en-produccio.md` |
| D-053 | Migracio / posada en produccio | Activar versio productiva nomes amb criteri GO | `BLOQUEJANT`, `TESTING`, `DIRECCIO` | No activar si falta declaracio, proves, backup, certificat o evidencies bloquejants. | `20-pla-proves-validacio-sif.md` |
| D-054 | Migracio / posada en produccio | MIGRACIO HISTORICA ▶ identificar factures amb numeracio Asso/SL compartida | `PENDENT PROGRAMAR`, `MIGRACIO`, `BD`, `RISC ALT` | Detectar factures antigues on la numeracio no permet separar correctament Associacio i SL com a emissors fiscals. | Aquest document |
| D-055 | Migracio / posada en produccio | MIGRACIO HISTORICA ▶ no generar hash chain per factures antigues Asso/SL | `BLOQUEJANT`, `MIGRACIO`, `LEGAL / AEAT` | Evitar que el traspas historic generi hash, QR o registre AEAT retroactiu sobre factures que no son SIF productives. | Aquest document |
| D-056 | Migracio / posada en produccio | MIGRACIO HISTORICA ▶ informe de control de factures `NO_VERIFACTU` | `PENDENT DOCUMENTAR`, `MIGRACIO`, `AUDITORIA / LOGS` | Crear evidencia interna que expliqui data de tall, criteri historic, numeracio conservada i limitacions de `factures.entitat`. | Aquest document |

---

# Modificacions Trello recomanades

## Si encara no existeixen taulers

Crear els tres taulers recomanats i carregar primer llistes i etiquetes. Despres carregar targetes.

## Si ja existeix un Trello unic de VERI*FACTU

Recomano no continuar-lo com a tauler unic. Opcions:

1. Reanomenar-lo a `VeriFactu / SIF ▶ Control del projecte` i crear dos taulers nous per `Casos d'us` i `Desenvolupament i proves`.
2. Deixar-lo com a historic i crear els tres taulers nets.
3. Copiar o recrear les targetes granulars antigues als tres taulers nous, corregint noms i assignant llista/etiquetes noves.

## Si ja existeix un Trello general de feina de PrisMa

No el modificaria per posar-hi tot VERI*FACTU. Com a maxim hi afegiria una targeta resum que enllaci als tres taulers VERI*FACTU/SIF.

## Si hi ha taulers WEB, INTRANET, GESTIO GENERICA i SIF historic

Es faran servir com a fonts de traspas granular. Les targetes copiades al nou esquema podran arxivar-se als taulers antics quan estiguin verificades.

Les targetes de nou servidor, PHP 8.4, JS, full d'estil, Font Awesome i traspas d'entorn s'han d'incloure com a feina VERI*FACTU/SIF.

## Seguent pas

Quan aquesta estructura estigui revisada, cal preparar:

- JSON de creacio de llistes.
- JSON de creacio d'etiquetes.
- JSON de creacio de targetes.
- Opcionalment, un camp `documentsRelacionats` dins la descripcio de cada targeta.
