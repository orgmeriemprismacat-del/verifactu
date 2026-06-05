# Auditoria de buits V9 - targetes i elements

Data: 2026-06-05

Aquest informe contrasta el fitxer canonic V9 amb els Trellos antics, els checklists antics, la documentacio del projecte i els 5 Trellos nous. No substitueix l'auditoria de pujada parcial: Meriem encara esta pujant targetes, per tant aquesta revisio se centra en si falten elements al model de targetes, no en si ja estan pujats a Trello.

## Resposta curta

Si: hi ha elements que probablement falten o han quedat massa comprimits al V9.

No sembla faltar el nucli del flux SIF nou. El V9 esta molt alineat amb `issueInvoice()`, `registerPayment()`, `payment_transaction`, `payment_allocation`, `fact_rels`, idempotencia, `DS_ORDER`, `redsys_notifications`, factura abans de cobrament i saldos.

El que si queda fluix es una altra cosa: elements petits que abans eren checklists o targetes molt concretes i que ara no apareixen al V9 amb prou visibilitat. Especialment plantilles/correus, XSD AEAT, traspas d'entorns, infraestructura, anomalies historiques i decisions de planificacio/capacitat.

## Fonts contrastades

| Font | Lectura |
|---|---|
| `targetes_v9_CANON_LABELS.json` | 7.164 targetes, 5 Trellos, totes amb etiquetes. |
| Exports antics Gestio generica, backlog historic, targetes actuals, web i intranet | 6.901 targetes obertes, 550 checklists i 5.188 checkitems. |
| 5 exports nous | Fotografies parcials de pujada; alguns taulers encara no reflecteixen el V9. |
| `trello-targetes-petites-reconciliades.md` | Base massiva anterior amb molts elements literals antics, pero amb estructura massa sorollosa. |
| Documentacio del repo | Decisions, casos, BD, fluxos, proves, governanca i pla tecnic. |

## Metode

- Comparacio de volum i etiquetes del V9.
- Comparacio literal de noms de targetes i checkitems antics contra el text del V9.
- Cerca conceptual de termes clau del flux SIF nou.
- Cerca de termes antics que no s'han de recuperar literalment.
- Contrast contra la matriu de cobertura de casos i documents de governanca.

Important: que una frase antiga no aparegui literalment no vol dir que falti. Moltes targetes antigues estaven basades en el flux vell de "passar pagament" i s'han de transformar al flux SIF actual, no copiar tal qual.

## Resultats numerics

| Indicador | Resultat |
|---|---:|
| Targetes V9 | 7.164 |
| Targetes V9 sense etiquetes | 0 |
| Targetes antigues obertes revisades | 6.901 |
| Checkitems antics revisats | 5.188 |
| Targetes antigues que no apareixen literalment al V9 | 6.877 |
| Checkitems antics que no apareixen literalment al V9 | 5.188 |

Aquesta dada no s'ha de llegir com "falten 12.065 targetes". Vol dir que la transformacio V9 ha canviat molt els noms, ha agrupat feina i ha descartat terminologia antiga. Per aixo cal revisar per blocs.

## Cobertura que si esta be

| Bloc | Estat | Evidencia |
|---|---|---|
| Flux fiscal nou | Cobert | `issueInvoice()` i `registerPayment()` apareixen de forma massiva al V9. |
| Pagaments sense crear factura fiscal | Cobert | `payment_transaction`, `payment_allocation`, `credit_balance`, saldos i Redsys estan representats. |
| Evitar flux antic per `HASH_I` / `REGISTROS_FACT` | Correcte | El V9 no conte `HASH_I`, `REGISTROS_FACT`, `REGSITROS_FACT`, `GUARDAR DADE` ni `PASSAR PAGAMENT PER`. |
| Go/no-go i evidencies | Cobert pero millorable | El V9 conte moltes targetes de go/no-go i evidencies, pero no usa el terme `expedient`. |
| Descomptes sensibles | Cobert | Hi ha targetes especifiques per discapacitat, familia nombrosa, monoparental i violencia de genere. |
| Exportacio, descarrega de factures, factura manual i consulta de pagaments | Cobert conceptualment | No cal recuperar totes les variants antigues una per una si el flux actual ja les cobreix. |

## Buits probables a afegir o revisar

Aquestes son les targetes que jo afegiria o validaria abans de donar el V9 per tancat.

### 1. Expedient d'evidencies de versio

Motiu: la documentacio parla d'expedient de versio i validacio, pero al V9 no apareix `expedient`. Hi ha moltes targetes d'evidencies, pero convindria una targeta clara que faci de contenidor formal.

| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| Trello 5 - Proves, entorns i produccio | Go-no-go i evidencies | Crear expedient d'evidencies per cada versio candidata | PROVES, GO-NO-GO, EVIDENCIES, DOCUMENTACIO |
| Trello 5 - Proves, entorns i produccio | Go-no-go i evidencies | Vincular captures, logs, PDF/QR/XML, hashes i consultes a l'expedient de versio | PROVES, EVIDENCIES, SIF |
| Trello 1 - Control i documentacio | Decisions preses | Decisio presa: cap versio productiva sense expedient d'evidencies tancat | DECISIO PRESA, GOVERNANCA, PRODUCCIO |

### 2. Decisions de planificacio i capacitat

Motiu: la documentacio recull criteris importants (`4 a 8 mesos`, `6 mesos`, `3 dies reals/setmana`, suport extern limitat), pero el V9 no els representa literalment. Aixo es feina de governanca i ajuda a fer visible la carrega real del projecte.

| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| Trello 1 - Control i documentacio | Decisions preses | Decisio presa: estimacio global 4 a 8 mesos, amb 6 mesos com escenari plausible | DECISIO PRESA, GOVERNANCA, PLANIFICACIO |
| Trello 1 - Control i documentacio | Decisions preses | Decisio presa: planificacio basada en 3 dies reals/setmana per VERI*FACTU | DECISIO PRESA, GOVERNANCA, PLANIFICACIO |
| Trello 1 - Control i documentacio | Decisions preses | Decisio presa: Meriem concentra arquitectura fiscal i suport extern no substitueix criteri SIF | DECISIO PRESA, GOVERNANCA, RISC PROJECTE |

### 3. Plantilles, correus i mapa de substitucio

Motiu: el V9 cobreix correus i plantilles de forma general, pero no apareixen termes antics concrets com `plantilla activa`, `mapa de substitucio` i `paraules clau`. Aquests elements eren checkitems de programacio i poden quedar amagats si no tenen targeta propia.

| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| Trello 4 - Intranet, interfície i notificacions | Correus i plantilles | Documentar motor de plantilles: plantilla activa, paraules clau i mapa de substitucio | INTRANET, CORREUS, DOCUMENTACIO |
| Trello 3 - Desenvolupament SIF, BD i API | Serveis SIF i API | Programar obtencio de plantilla activa des de BD | DESENVOLUPAMENT, API, CORREUS |
| Trello 3 - Desenvolupament SIF, BD i API | Serveis SIF i API | Programar substitucio de paraules clau en correus i notificacions | DESENVOLUPAMENT, API, CORREUS |
| Trello 5 - Proves, entorns i produccio | Testing i validacio | Provar correus amb plantilla activa i mapa de substitucio | PROVES, CORREUS, INTRANET |

### 4. XSD AEAT i especificacio tecnica

Motiu: la base massiva anterior tenia targetes per XSD AEAT (`SuministroLR.xsd`, `RespuestaSuministro.xsd`, `ConsultaLR.xsd`, `EventosSIF.xsd`, etc.). Al V9 canonic no apareixen aquests noms. Potser no cal una targeta per cada XSD si ja esta documentat, pero si que cal que el treball sigui visible.

| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| Trello 1 - Control i documentacio | Compliment AEAT i declaracio responsable | Inventariar XSD AEAT requerits pel SIF PrisMa | AEAT, DOCUMENTACIO, SIF |
| Trello 3 - Desenvolupament SIF, BD i API | Serveis SIF i API | Validar mapping intern contra XSD AEAT | AEAT, DESENVOLUPAMENT, API |
| Trello 5 - Proves, entorns i produccio | Testing i validacio | Provar alta/consulta/anulacio amb esquemes AEAT corresponents | AEAT, PROVES, SIF |

Si es vol conservar maxima granularitat, afegir subtargetes o targetes separades per:

- `SuministroLR.xsd`
- `RespuestaSuministro.xsd`
- `ConsultaLR.xsd`
- `RespuestaConsultaLR.xsd`
- `SuministroInformacion.xsd`
- `EventosSIF.xsd`
- `RespuestaValRegistNoVeriFactu.xsd`

### 5. Infraestructura, traspas d'entorn i compatibilitat

Motiu: el repositori antic ja deia que PHP 8.4, nou servidor, JS, full d'estil, Font Awesome i traspas d'entorn s'havien d'incloure com a feina VERI*FACTU/SIF. Al V9 canonic no apareixen prou literalment `PHP 8.4`, `test.prisma.cat`, `traspas entorn` o `.htaccess`.

| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| Trello 5 - Proves, entorns i produccio | Entorns i desplegament | Documentar traspas d'entorn test/preproduccio/produccio | ENTORNS, DESPLEGAMENT, DOCUMENTACIO |
| Trello 5 - Proves, entorns i produccio | Entorns i desplegament | Revisar compatibilitat PHP 8.4 del codi afectat per VERI*FACTU | ENTORNS, PHP 8.4, DESENVOLUPAMENT |
| Trello 4 - Intranet, interfície i notificacions | Disseny d'interfície i pantalles | Revisar rutes `.htaccess` de pantalles afectades per VERI*FACTU | INTRANET, PANTALLES, ENTORNS |
| Trello 5 - Proves, entorns i produccio | Entorns i desplegament | Provar SSL/subdominis `pay.prisma.cat` i entorn de test | ENTORNS, PAY.PRISMA.CAT, PROVES |

### 6. Bases de dades, replica i registre d'accessos

Motiu: el V9 cobreix molt be BD SIF i relacions, pero alguns noms antics i operatius no apareixen prou: `GESTIO_REPLICA`, traspas de dades i registre d'accessos. Com que l'usuari ha remarcat bases de dades i traspas de dades, aquest bloc no s'hauria de deixar implicit.

| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| Trello 3 - Desenvolupament SIF, BD i API | BD i modelatge | Decidir si `GESTIO_REPLICA` es conserva, es migra o s'arxiva | BD, MIGRACIO, DECISIO PENDENT |
| Trello 3 - Desenvolupament SIF, BD i API | BD i modelatge | Documentar traspas de dades entre BD web, intranet, gestio i BD SIF | BD, MIGRACIO, DOCUMENTACIO |
| Trello 3 - Desenvolupament SIF, BD i API | BD i modelatge | Definir registre d'accessos a BD nova i accions sensibles | BD, SEGURETAT, AUDITORIA |
| Trello 5 - Proves, entorns i produccio | Testing i validacio | Provar traspas de dades amb dades reals anonimitzades o mostra controlada | BD, MIGRACIO, PROVES |

### 7. Anomalies i casos antics que no s'han de perdre

Motiu: alguns elements antics no son arquitectura, sino casos reals o anomalies. No convé convertir-los en soroll massiu, pero tampoc perdre'ls.

| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| Trello 2 - Casos d'ús | Casos d'ús pendents | Cas d'us: factures SLI sense entitat | CAS D'US, BD, RISC FISCAL |
| Trello 2 - Casos d'ús | Casos d'ús pendents | Cas d'us: factures amb concepte unic | CAS D'US, FACTURACIO, RISC FISCAL |
| Trello 2 - Casos d'ús | Casos d'ús pendents | Cas d'us: anul·lar pagament sense anul·lar factura fiscal | CAS D'US, PAGAMENTS, SIF |
| Trello 2 - Casos d'ús | Casos d'ús per pantalles intranet | Cas d'us: info alumne i impacte fiscal indirecte | CAS D'US, INTRANET, PERMISOS |

### 8. Pagament antic transformat al flux nou

Motiu: les targetes de "passar pagament segons tipus" venien del sistema inicial. El projecte ha canviat: ara el centre ha de ser `issueInvoice()` i `registerPayment()`. No s'han de recuperar literalment, pero si s'ha de conservar el mapa de variants.

| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| Trello 1 - Control i documentacio | Arquitectura tancada i criteris tècnics | Decisio presa: les targetes antigues `PASSAR PAGAMENT PER...` es transformen al flux SIF actual | DECISIO PRESA, ARQUITECTURA, PAGAMENTS |
| Trello 2 - Casos d'ús | Casos d'ús parcials | Mapa de variants antigues de pagament contra `issueInvoice()` i `registerPayment()` | CAS D'US, PAGAMENTS, SIF |
| Trello 3 - Desenvolupament SIF, BD i API | Serveis SIF i API | Implementar adaptador de flux antic de pagament cap a serveis SIF | DESENVOLUPAMENT, PAGAMENTS, SIF |
| Trello 5 - Proves, entorns i produccio | Testing i validacio | Provar regressio de variants antigues de pagament amb flux SIF nou | PROVES, PAGAMENTS, REGRESSIO |

## Elements que no recuperaria literalment

No afegiria de nou aquestes expressions com a targetes finals, perque representen el disseny vell o errades antigues:

- `HASH_I`
- `REGSITROS_FACT` / `REGISTROS_FACT` com a nom de flux manual
- `GUARDAR DADE...`
- `PASSAR PAGAMENT PER...` com a flux principal
- targetes que diuen que el PDF/QR es genera abans del registre SIF
- targetes que fan dependre el registre fiscal del TPV o del pagament

Aquestes peces nomes s'han de conservar si es reformulen com a:

- decisio de descart;
- mapping antic -> flux SIF actual;
- prova de regressio;
- nota historica arxivada.

## Falsos positius importants

| Element que semblava faltar | Lectura correcta |
|---|---|
| Casos d'us de descomptes sensibles | Si que hi son al V9. |
| Go/no-go | Si que hi es al V9; falta reforçar `expedient`. |
| Exportacio/descarrega/factura manual | Hi ha cobertura conceptual; no cal recuperar totes les variants antigues. |
| Pagaments, Redsys i saldos | Coberts en el flux nou. |
| Morositat i gestio cursos | Hi ha paquet V8 especific; cal revisar-lo quan acabi la pujada. |

## Conclusio operativa

El V9 no s'ha de reduir: el volum es necessari. Pero tampoc s'ha de donar per complet sense una passada de buits.

Jo faria tres accions:

1. Afegir o validar les targetes proposades en aquest informe, sobretot BD/traspas, plantilles, XSD AEAT, infraestructura i governanca.
2. Marcar les targetes antigues de "passar pagament" com a transformades al flux SIF, no com a eliminades sense traça.
3. Quan la pujada als 5 Trellos estigui acabada, repetir la comparacio contra exports finals i revisar duplicats/targetes antigues coexistents.

La conclusio principal es: Meriem tenia rao a sospitar que faltaven elements. No falta el cor del SIF, pero si falten peces petites que fan visible tota la feina real.
