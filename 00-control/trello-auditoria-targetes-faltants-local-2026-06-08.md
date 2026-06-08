# Auditoria targetes faltants - feina local recent

Data: 2026-06-08

Objectiu: revisar els 6 Trellos actuals contra l'estat local del projecte, especialment la feina feta aquests dies al nucli SIF, Redsys, transferencies manuals, packs, grups, preproduccio i proves.

## Fonts revisades

| Font | Resultat |
|---|---:|
| Trellos actuals exportats | 6 |
| Targetes obertes als exports actuals | 8.436 |
| Branca local | `checkpoint/sif-fase-0-4` |
| Últim commit sincronitzat | `6893ad3 checkpoint: prepara Redsys fase 11` |
| Fitxers modificats sense commit | 7 |
| Fitxers nous sense commit visibles ara | 57, incloent aquest informe |

Fitxers locals nous destacats:

- `sif/scripts/preflight-redsys-course.php`, `preview-redsys-course.php`, `process-redsys-course.php`
- `sif/scripts/preflight-manual-course.php`, `preview-manual-course.php`, `process-manual-course.php`
- `sif/scripts/preflight-redsys-pack.php`, `preview-redsys-pack.php`, `process-redsys-pack.php`
- `sif/scripts/preflight-manual-pack.php`, `preview-manual-pack.php`, `process-manual-pack.php`
- `LegacyCourseSnapshotRepository`, `LegacyPackSnapshotRepository`, `LegacyGroupSnapshotRepository`
- `LegacyGiftSnapshotRepository`
- `LegacyCourseInvoicePayloadBuilder`, `LegacyPackInvoicePayloadBuilder`, `LegacyGroupInvoicePayloadBuilder`
- `LegacyGiftInvoicePayloadBuilder`
- `RedsysCourseInvoiceService`, `RedsysPackInvoiceService`
- `ManualCourseInvoiceService`, `ManualPackInvoiceService`
- tests d'integracio i unitat per curs, pack, grup, regal, preflight, preview i processadors.

## Resposta curta

Sí, falten targetes.

Els Trellos actuals cobreixen bastant bé els casos d'us generals: Redsys curs, pack, grup, transferencia validada, factura abans de cobrament, privacitat de grups, preproduccio i proves. Pero no cobreixen prou la feina tecnica concreta que ja s'ha fet aquests dies.

El parser dels 6 exports ha detectat 8.436 targetes obertes. Dels 59 noms tecnics locals revisats, 57 no apareixen literalment als Trellos actuals.

Cap d'aquests noms apareix literalment als Trellos actuals:

`RedsysInvoicePayloadBuilder`, `LegacyCourseInvoicePayloadBuilder`, `LegacyCourseSnapshotRepository`, `RedsysCourseInvoiceService`, `process-redsys-course.php`, `preflight-redsys-course.php`, `preview-redsys-course.php`, `ManualPaymentPayloadBuilder`, `ManualCourseInvoicePayloadBuilder`, `ManualCourseInvoiceService`, `LegacyPackSnapshotRepository`, `LegacyPackInvoicePayloadBuilder`, `RedsysPackInvoiceService`, `ManualPackInvoicePayloadBuilder`, `ManualPackInvoiceService`, `LegacyGroupSnapshotRepository`, `LegacyGroupInvoicePayloadBuilder`, `LegacyGiftSnapshotRepository`, `LegacyGiftInvoicePayloadBuilder`.

Aixo vol dir que la feina pot estar coberta conceptualment, pero no queda visible com a feina feta/programada/provada.

## Targetes que falten

### A. Control i decisions

| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| Control del projecte | Pla d'implementació tècnica SIF | Fase 11 - Estat local: curs normal, pack i grup preparats a nivell tècnic | CONTROL, SIF, FASE 11 |
| Control del projecte | Decisions ja preses | Decisió presa: no activar callbacks reals fins que preflight i preview siguin correctes | DECISIÓ PRESA, REDSYS, PRODUCCIÓ |
| Control del projecte | Decisions ja preses | Decisió presa: la sync legacy és opcional i només post-SIF amb `--sync-legacy` | DECISIÓ PRESA, LEGACY, SIF |
| Control del projecte | Decisions ja preses | Decisió presa: el regal factura al comprador i el bescanvi no genera segona factura | DECISIÓ PRESA, REGAL, SIF |
| Control del projecte | Revisió fina - targetes a crear o dividir | Separar targetes genèriques de Fase 11 en curs, pack, grup, manual i Redsys | CONTROL, REVISIÓ, TRELLO |

### B. Configuració i connexió legacy

| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| Proves, entorns i producció | Entorns i preflight | Configurar `legacy_db` amb `SIF_LEGACY_DB_DSN`, `SIF_LEGACY_DB_USER`, `SIF_LEGACY_DB_PASSWORD` | ENTORNS, BD LEGACY, SIF |
| Proves, entorns i producció | Programació pendent - Infraestructura BD i domini | Programar `ConnectionFactory::makeLegacy()` | PROGRAMACIÓ, BD LEGACY, SIF |
| Proves, entorns i producció | Testing i validació | Provar connexió SIF + legacy en entorn no productiu | PROVES, BD LEGACY, PREFLIGHT |

### C. Redsys - curs normal

| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| SIF pay.prisma.cat | Casos d'ús - Redsys - Curs normal | Programar `LegacyCourseInvoicePayloadBuilder` per snapshot `inscripcions` + `curs` | REDSYS, CURS NORMAL, PAYLOAD |
| SIF pay.prisma.cat | Casos d'ús - Redsys - Curs normal | Programar `LegacyCourseSnapshotRepository` de només lectura per `IDPAG` | REDSYS, BD LEGACY, CURS NORMAL |
| SIF pay.prisma.cat | Casos d'ús - Redsys - Curs normal | Programar `RedsysCourseInvoiceService` com a orquestrador de curs normal | REDSYS, SERVICE, CURS NORMAL |
| Proves, entorns i producció | Entorns i preflight | Crear `preflight-redsys-course.php` | PREFLIGHT, REDSYS, CURS NORMAL |
| Proves, entorns i producció | Testing i validació | Crear `preview-redsys-course.php` per revisar payload sense emetre factura | PREVIEW, REDSYS, CURS NORMAL |
| Proves, entorns i producció | Testing i validació | Crear `process-redsys-course.php` per processar manualment `DS_ORDER` validat | PREPRODUCCIÓ, REDSYS, CURS NORMAL |
| Proves, entorns i producció | Go-no-go i evidències | Executar evidència: preflight + preview + process Redsys curs normal | EVIDÈNCIES, REDSYS, GO-NO-GO |

### D. Factura abans de cobrament

| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| Fitxes funcionals i documentació de casos | Casos d'ús - Factura abans de cobrament + pagament posterior | Afegir subtargeta tècnica: `InvoiceBeforePaymentFlowTest` | CAS D'ÚS, PROVES, FACTURA ABANS |
| Proves, entorns i producció | Testing i validació | Provar `issueInvoice(emesa_abans_cobrament=1)` + `registerPayment()` sense segon registre fiscal | PROVES, SIF, FACTURA ABANS |
| Proves, entorns i producció | Go-no-go i evidències | Guardar evidència `SIF-FAC-001` de factura abans + pagament posterior | EVIDÈNCIES, SIF-FAC-001 |

### E. Transferència manual contra factura existent

| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| Fitxes funcionals i documentació de casos | Casos d’ús - Transferència validada a intranet | Afegir subtargeta tècnica: `ManualPaymentPayloadBuilder` per `registerPayment()` | TRANSFERÈNCIA, REGISTERPAYMENT, INTRANET |
| Proves, entorns i producció | Testing i validació | Provar idempotència de transferència manual amb referència bancària i fallback | PROVES, TRANSFERÈNCIA, IDEMPOTÈNCIA |
| Proves, entorns i producció | Go-no-go i evidències | Guardar evidència `SIF-PAY-001` per transferència contra factura existent | EVIDÈNCIES, SIF-PAY-001 |

### F. Transferència manual - curs sense factura SIF prèvia

| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| Fitxes funcionals i documentació de casos | Casos d’ús - Passar pagaments | Programar `ManualCourseInvoicePayloadBuilder` per `issueInvoice(payment)` | PASSAR PAGAMENTS, CURS NORMAL, PAYLOAD |
| Fitxes funcionals i documentació de casos | Casos d’ús - Passar pagaments | Programar `ManualCourseInvoiceService` | PASSAR PAGAMENTS, SERVICE, CURS NORMAL |
| Proves, entorns i producció | Entorns i preflight | Crear `preflight-manual-course.php` | PREFLIGHT, MANUAL, CURS NORMAL |
| Proves, entorns i producció | Testing i validació | Crear `preview-manual-course.php` | PREVIEW, MANUAL, CURS NORMAL |
| Proves, entorns i producció | Testing i validació | Crear `process-manual-course.php` | PREPRODUCCIÓ, MANUAL, CURS NORMAL |
| Proves, entorns i producció | Go-no-go i evidències | Executar evidència `SIF-PAY-001`: curs manual sense factura prèvia | EVIDÈNCIES, SIF-PAY-001 |

### G. Packs - Redsys

| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| Fitxes funcionals i documentació de casos | Casos d’ús - Pack | Programar `LegacyPackSnapshotRepository` | PACK, BD LEGACY, SNAPSHOT |
| Fitxes funcionals i documentació de casos | Casos d’ús - Pack | Programar `LegacyPackInvoicePayloadBuilder` amb dues línies i descompte `PACK` | PACK, PAYLOAD, DESCOMPTE |
| SIF pay.prisma.cat | Casos d’ús - Redsys - Pack | Programar `RedsysPackInvoiceService` | REDSYS, PACK, SERVICE |
| Proves, entorns i producció | Entorns i preflight | Crear `preflight-redsys-pack.php` | PREFLIGHT, REDSYS, PACK |
| Proves, entorns i producció | Testing i validació | Crear `preview-redsys-pack.php` | PREVIEW, REDSYS, PACK |
| Proves, entorns i producció | Testing i validació | Crear `process-redsys-pack.php` | PREPRODUCCIÓ, REDSYS, PACK |
| Proves, entorns i producció | Go-no-go i evidències | Executar evidència Redsys pack: preflight + preview + process | EVIDÈNCIES, REDSYS, PACK |

### H. Packs - transferència manual

| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| Fitxes funcionals i documentació de casos | Casos d’ús - Pack | Programar `ManualPackInvoicePayloadBuilder` | PACK, TRANSFERÈNCIA, PAYLOAD |
| Fitxes funcionals i documentació de casos | Casos d’ús - Pack | Programar `ManualPackInvoiceService` | PACK, SERVICE, INTRANET |
| Proves, entorns i producció | Entorns i preflight | Crear `preflight-manual-pack.php` | PREFLIGHT, MANUAL, PACK |
| Proves, entorns i producció | Testing i validació | Crear `preview-manual-pack.php` | PREVIEW, MANUAL, PACK |
| Proves, entorns i producció | Testing i validació | Crear `process-manual-pack.php` | PREPRODUCCIÓ, MANUAL, PACK |
| Proves, entorns i producció | Go-no-go i evidències | Executar evidència pack manual: import manual igual al total fiscal | EVIDÈNCIES, PACK, SIF-PAY-001 |

### I. Grups

| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| Fitxes funcionals i documentació de casos | Casos d’ús - Grup de persones | Programar `LegacyGroupSnapshotRepository` | GRUP, BD LEGACY, SNAPSHOT |
| Fitxes funcionals i documentació de casos | Casos d’ús - Grup de persones | Programar `LegacyGroupInvoicePayloadBuilder` amb receptor `respGrups` | GRUP, PAYLOAD, RESPGRUPS |
| Fitxes funcionals i documentació de casos | Casos d’ús - Privacitat de participants en factura de grup/empresa | Afegir subtargeta tècnica: `visible_alumne = 0` per línies/relacions de grup | GRUP, PRIVACITAT, INTRANET |
| Proves, entorns i producció | Testing i validació | Provar payload de grup amb participants i receptor responsable | PROVES, GRUP, PAYLOAD |
| Proves, entorns i producció | Pendent de provar | Validar SQL final de `descomptes_grup` abans d'orquestradors Redsys/manuals de grup | PENDENT DADA, GRUP, BD |

### J. Regals

| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| Casos d'ús / Anàlisi funcional | Casos d’ús - Regal | Crear bloc cas d'ús Regal: comprador, destinatari, codi, bescanvi i factura única | CAS D'ÚS, REGAL, ANÀLISI |
| Fitxes funcionals i documentació de casos | Casos d’ús - Regal | Programar `LegacyGiftSnapshotRepository` per llegir `regal` per `ID` o `CODI` | REGAL, BD LEGACY, SNAPSHOT |
| Fitxes funcionals i documentació de casos | Casos d’ús - Regal | Programar `LegacyGiftInvoicePayloadBuilder` amb comprador com a receptor fiscal | REGAL, PAYLOAD, COMPRADOR |
| Fitxes funcionals i documentació de casos | Casos d’ús - Regal | Afegir regla tècnica: `visible_alumne = 0` i destinatari sense factura nova | REGAL, PRIVACITAT, SIF |
| SIF pay.prisma.cat | Casos d’ús - Redsys - Regal | Integrar payload regal amb Redsys i idempotència `REDSYS\|REGAL\|IDPAG:NULL\|ORDER:{DS_ORDER}` | REDSYS, REGAL, IDEMPOTÈNCIA |
| Proves, entorns i producció | Pendent de provar | Validar SQL final de `regal`, `FACT_REL`, `ORIGEN`, `DESTI`, `CODI` i vincle amb inscripció posterior | PENDENT DADA, REGAL, BD |
| Proves, entorns i producció | Testing i validació | Provar `LegacyGiftInvoicePayloadBuilderTest` i `LegacyGiftSnapshotRepositoryTest` | TESTS, REGAL, PAYLOAD |
| Proves, entorns i producció | Go-no-go i evidències | Executar evidència regal: compra, callback duplicat, bescanvi i no segona factura | EVIDÈNCIES, REGAL, GO-NO-GO |
| Control del projecte | Decisions pendents | Conservar i tancar criteri final de targeta regal PDF comercial vs factura fiscal SIF | DECISIÓ PENDENT, REGAL, PDF |

### K. Tests que també han de tenir traça

| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| Proves, entorns i producció | Testing i validació | Crear/validar tests `LegacyCourse*`, `RedsysCourse*` | TESTS, REDSYS, CURS NORMAL |
| Proves, entorns i producció | Testing i validació | Crear/validar tests `ManualCourse*` | TESTS, MANUAL, CURS NORMAL |
| Proves, entorns i producció | Testing i validació | Crear/validar tests `LegacyPack*`, `RedsysPack*`, `ManualPack*` | TESTS, PACK |
| Proves, entorns i producció | Testing i validació | Crear/validar tests `LegacyGroup*` | TESTS, GRUP |
| Proves, entorns i producció | Testing i validació | Crear/validar tests `LegacyGift*` | TESTS, REGAL |
| Proves, entorns i producció | Go-no-go i evidències | Executar runner PHP quan hi hagi PHP/MySQL i guardar sortida de proves | EVIDÈNCIES, PHP, MYSQL |

## Targetes que hi són però són massa genèriques

| Bloc | Cobertura actual | Problema |
|---|---|---|
| Curs normal Redsys | Hi ha casos d'ús de Redsys curs normal | No hi ha targetes per snapshot legacy, payload builder, orquestrador, preflight, preview i processador manual. |
| Transferència validada | Hi ha cas d'ús i impacte sobre `registerPayment()` | No hi ha targetes per `ManualPaymentPayloadBuilder` ni per curs manual sense factura SIF prèvia. |
| Factura abans de cobrament | Hi ha cas d'ús | No hi ha traça de `InvoiceBeforePaymentFlowTest` ni de l'evidència tècnica concreta. |
| Packs | Hi ha casos d'ús pack i proves genèriques | No hi ha targetes per snapshot/payload pack, Redsys pack, manual pack i scripts. |
| Grups | Hi ha casos d'ús, `descomptes_grup` i privacitat | No hi ha targetes per `LegacyGroupSnapshotRepository`, `LegacyGroupInvoicePayloadBuilder` ni `visible_alumne = 0`. |
| Regals | Hi ha casos d'ús de regal a Fitxes i SIF pay, i proves genèriques | No hi ha targetes per `LegacyGiftSnapshotRepository`, `LegacyGiftInvoicePayloadBuilder`, tests `LegacyGift*` ni bloc obert a `Casos d'ús / Anàlisi funcional`. |
| Preproducció | Hi ha llistes de preproducció/proves | No hi ha targetes per cada script `preflight/preview/process` que ja s'ha creat. |

## No afegir com a targeta separada

No recomano crear una targeta per cada fitxer de test si el Trello es torna inmanejable. Pero sí cal una targeta per cada paquet de proves:

- tests curs Redsys;
- tests curs manual;
- tests pack Redsys;
- tests pack manual;
- tests grup;
- tests regal;
- execució real del runner PHP/MySQL.

## Prioritat

1. Afegir les targetes de preproducció i scripts (`preflight`, `preview`, `process`) perquè són les que eviten activar fluxos reals sense evidència.
2. Afegir les targetes de builders/orquestradors de curs, pack, grup i regal perquè representen feina de programació ja feta.
3. Afegir les targetes de proves/evidències perquè ara mateix la feina existeix localment però encara no s'ha executat amb PHP/MySQL.
4. Revisar si cal moure algunes targetes a "Feina ja feta" quan el codi quedi commitejat.

## Conclusió

Els Trellos actuals tenen volum i cobreixen molts casos, però no reflecteixen encara tota la feina tècnica feta aquests dies. Les targetes faltants principals són de Fase 11: adaptadors legacy, builders de payload, serveis orquestradors, scripts de preproducció i paquets de tests per Redsys/manual/curs/pack/grup/regal.
