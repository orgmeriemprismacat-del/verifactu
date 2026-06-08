# Auditoria especifica - Interficies i proves

Data: 2026-06-08

Objectiu: revisar si falten targetes petites als Trellos d'intranet/interficies i de proves, i proposar recol.locacio de llistes.

## Fonts revisades

| Font | Resultat |
|---|---:|
| Trello Intranet/interficie/notificacions | 681 targetes obertes |
| Trello Proves/entorns/produccio | 1.309 targetes obertes |
| `documentacio/03-canvis-pendents/07-pantalles-intranet.md` | revisat |
| `documentacio/03-canvis-pendents/08-correus-i-plantilles.md` | revisat |
| `documentacio/05-governanca-operacio/20-pla-proves-validacio-sif.md` | revisat |
| `00-control/checklist-completitud.md` | revisat |

## Resposta curta

Si, falten moltes targetes als Trellos d'interficies i de proves.

El problema no es nomes quantitat. El problema es que hi ha molts blocs generals, pero falten targetes petites per:

- estat visual de cada pantalla;
- botons i bloquejos;
- avisos i missatges;
- permisos de servidor;
- URL de pagament i enllac segur;
- PDF/QR pendent o fallit;
- proves executables amb ID estable;
- evidencies de cada script `preflight`, `preview` i `process`;
- variants reals de `Passar pagaments`, factura abans, pack, grup i regal.

## Diagnosi d'estructura

### Trello Intranet/interficie/notificacions

| Llista actual | Estat | Problema |
|---|---|---|
| Sistema de notificacions | 232 targetes | Barreja BD, UI, notificacions i pantalles. Massa grossa. |
| Correus i plantilles | 165 targetes | Bona base, pero falten targetes per mapa real PHP/JS/AJAX/Template/subject/destinatari. |
| Intranet, pantalles i comunicacions | 48 targetes | Bona llista, pero massa resumida per les pantalles critiques. |
| Revisions | 43 targetes | Bona per codi concret, pero falta separar revisions de pantalla i revisions de correu. |
| Intranet - Passar pagaments | 7 targetes | Clarament insuficient per al flux real actual. |
| Intranet - Generar factura abans de pagar | 14 targetes | Bona base, pero falten targetes de validacio servidor, idempotencia i estats post-SIF. |

### Trello Proves/entorns/produccio

| Llista actual | Estat | Problema |
|---|---|---|
| Go-no-go i evidencies | 457 targetes | Massa grossa. Barreja preparacio, dades, execucio i evidencia. |
| Entorns i desplegament | 344 targetes | Barreja infra, serveis SIF, rollback i entorns. |
| Pendent de provar | 114 targetes | Correcta, pero hauria de rebre les proves Fase 11 concretes. |
| Testing i validacio | 65 targetes | Li falten proves executables de scripts locals nous. |
| Proves addicionals - casos, endpoints i evidencies | 39 targetes | Bona, pero incompleta per interfices i permisos servidor. |

## Recol.locacio recomanada

### Intranet/interficies

Mantindria aquest Trello per tot el que sigui:

- pantalla;
- modal;
- boto;
- avís visual;
- text de confirmacio;
- correu;
- plantilla;
- enllac segur;
- permis d'interficie i permis de servidor associat a una accio d'intranet.

Crearia o reforcaria aquestes llistes:

| Llista recomanada | Que hi hauria d'anar |
|---|---|
| Pantalla - Consulta / Modifica alumne | estat fiscal, dades de pagament, veure factura, canvis dades personals |
| Pantalla - Passar pagaments / conciliacio SIF | cerca, decisor `issueInvoice()` vs `registerPayment()`, confirmacions i incidencies |
| Pantalla - Generar factura abans de cobrament | seleccio inscripcions, receptor, linies, idempotencia i estats SIF |
| Pantalla - Consulta / Edita / Anula factura | rectificatives, anul.lacions, `E_FACT`, bloquejos i PDF/QR |
| URLs, pay.prisma.cat i enllac segur | URLs tipificades, factura ja pagada, document pendent, token segur |
| Correus i plantilles SIF | factura emesa, factura abans, rectificativa, incidencia, regal, pack, grup |
| Notificacions i incidencies SIF | avis persistent, incidencia, indicador, responsable i estat |
| Panell SIF / Apartat VERI*FACTU intranet | dashboard, factures, AEAT, documents, versions, exportacions |

### Proves/entorns/produccio

Mantindria aquest Trello per tot el que sigui:

- prova executable;
- preflight;
- preview;
- process;
- evidencia;
- entorn;
- go/no-go;
- rollback tecnic;
- incidencia de prova.

Dividiria `Go-no-go i evidencies` en blocs mes petits:

| Llista recomanada | Que hi hauria d'anar |
|---|---|
| Fase 11 - Redsys curs normal | `preflight-redsys-course`, `preview-redsys-course`, `process-redsys-course`, `SIF-RED-001` |
| Fase 11 - Manuals curs normal | `preflight-manual-course`, `preview-manual-course`, `process-manual-course`, `SIF-PAY-001` |
| Fase 11 - Packs | Redsys pack, manual pack, scripts i evidencies |
| Fase 11 - Grups | SQL `descomptes_grup`, payload, privacitat i evidencia |
| Fase 11 - Regals | SQL `regal`, payload, codi regal, bescanvi i evidencia |
| Proves intranet i permisos | pantalles, endpoints, rols i accions bloquejades |
| Documents, PDF/QR i enllac segur | document pendent, error PDF/QR, hash i acces segur |
| Paquet go/no-go final | decisio `GO`, `GO AMB LIMITACIONS` o `NO-GO` |

## Targetes que falten - Intranet/interficies

### A. Consulta / Modifica alumne

| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| Intranet/interficies | Pantalla - Consulta / Modifica alumne | Mostrar estat fiscal resumit per inscripcio: factura, receptor, cobrament, AEAT, PDF/QR i URL | INTRANET, UI, SIF |
| Intranet/interficies | Pantalla - Consulta / Modifica alumne | Afegir avis: canviar dades personals no modifica factures ja emeses | INTRANET, AVIS, FACTURACIO |
| Intranet/interficies | Pantalla - Consulta / Modifica alumne | Bloquejar canvi fiscal de receptor/import/concepte i derivar a rectificativa | INTRANET, BLOQUEIG, RECTIFICATIVA |
| Intranet/interficies | Pantalla - Consulta / Modifica alumne | Redissenyar modal de dades de pagament separant dades operatives, cobrament, factura i URL | INTRANET, UI, PAGAMENTS |
| Intranet/interficies | Pantalla - Consulta / Modifica alumne | Mostrar motiu d'inactivacio de URL de pagament | INTRANET, PAY.PRISMA, UI |
| Intranet/interficies | Pantalla - Consulta / Modifica alumne | Diferenciar factura historica no VERI*FACTU de factura SIF amb PDF immutable | INTRANET, DOCUMENTS, SIF |
| Intranet/interficies | Pantalla - Consulta / Modifica alumne | Validar server-side les accions de les icones encara que la icona estigui oculta/desactivada | INTRANET, SEGURETAT, PERMISOS |

### B. Passar pagaments / conciliacio SIF

| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| Intranet/interficies | Pantalla - Passar pagaments / conciliacio SIF | Redissenyar cerca per permetre un sol criteri: `NIF/NIE`, `CODI REGAL` o `NUM FACTURA` | INTRANET, PAGAMENTS, UI |
| Intranet/interficies | Pantalla - Passar pagaments / conciliacio SIF | Mostrar decisor abans de confirmar: `registerPayment()`, `issueInvoice()` o incidencia | INTRANET, SIF, PAGAMENTS |
| Intranet/interficies | Pantalla - Passar pagaments / conciliacio SIF | Pantalla per transferencia contra factura SIF existent | INTRANET, TRANSFERENCIA, REGISTERPAYMENT |
| Intranet/interficies | Pantalla - Passar pagaments / conciliacio SIF | Pantalla per transferencia contra factura abans de cobrament | INTRANET, FACTURA ABANS, PAGAMENTS |
| Intranet/interficies | Pantalla - Passar pagaments / conciliacio SIF | Pantalla per transferencia sense factura SIF previa i venda facturable | INTRANET, ISSUEINVOICE, TRANSFERENCIA |
| Intranet/interficies | Pantalla - Passar pagaments / conciliacio SIF | Avisar i bloquejar import superior al pendent sense actualitzacio silenciosa | INTRANET, BLOQUEIG, PAGAMENTS |
| Intranet/interficies | Pantalla - Passar pagaments / conciliacio SIF | Mostrar idempotencia de referencia bancaria repetida o fallback factura/data/import/banc | INTRANET, IDEMPOTENCIA, PAGAMENTS |
| Intranet/interficies | Pantalla - Passar pagaments / conciliacio SIF | Afegir variants visibles per pack, grup, regal i pagament fraccionat | INTRANET, PACK, GRUP, REGAL |
| Intranet/interficies | Pantalla - Passar pagaments / conciliacio SIF | Guardar log/avis quan una factura historica no SIF es localitza per `NUM FACTURA` | INTRANET, LEGACY, INCIDENCIA |

### C. Generar factura abans de cobrament

| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| Intranet/interficies | Pantalla - Generar factura abans de cobrament | Mostrar banner: factura real emesa abans de cobrament | INTRANET, UI, FACTURA ABANS |
| Intranet/interficies | Pantalla - Generar factura abans de cobrament | Recalcular al servidor inscripcions, curs, edicio, receptor i import abans d'emetre | INTRANET, BACKEND, VALIDACIO |
| Intranet/interficies | Pantalla - Generar factura abans de cobrament | Bloquejar doble clic/reintent i retornar mateixa factura per idempotencia | INTRANET, IDEMPOTENCIA, SIF |
| Intranet/interficies | Pantalla - Generar factura abans de cobrament | Mostrar linies fiscals estructurades abans de confirmar | INTRANET, FACTURACIO, UI |
| Intranet/interficies | Pantalla - Generar factura abans de cobrament | Desactivar o substituir URLs individuals quan queda cobert per factura empresa/responsable | INTRANET, PAY.PRISMA, EMPRESA |
| Intranet/interficies | Pantalla - Generar factura abans de cobrament | Mostrar resultat post-SIF: numero, estat AEAT, estat cobrament, PDF i QR | INTRANET, SIF, DOCUMENTS |
| Intranet/interficies | Pantalla - Generar factura abans de cobrament | Indicar que el pagament posterior entra per `registerPayment()` | INTRANET, REGISTERPAYMENT, FACTURA ABANS |

### D. Consulta / Edita / Anula factura

| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| Intranet/interficies | Pantalla - Consulta / Edita / Anula factura | Mostrar factura original, rectificatives, pagaments, devolucions i PDF/QR | INTRANET, FACTURACIO, DOCUMENTS |
| Intranet/interficies | Pantalla - Consulta / Edita / Anula factura | Bloquejar edicio directa de receptor, CIF, concepte o import | INTRANET, BLOQUEIG, SIF |
| Intranet/interficies | Pantalla - Consulta / Edita / Anula factura | Crear flux visual de rectificativa amb motiu, usuari, data i factura original | INTRANET, RECTIFICATIVA, UI |
| Intranet/interficies | Pantalla - Consulta / Edita / Anula factura | Crear flux visual d'anul.lacio amb devolucio, saldo o no retorn | INTRANET, ANUL.LACIO, PAGAMENTS |
| Intranet/interficies | Pantalla - Consulta / Edita / Anula factura | Afegir control separat de `E_FACT` sense confondre'l amb factura abans de cobrament | INTRANET, E_FACT, DECISIO |
| Intranet/interficies | Pantalla - Consulta / Edita / Anula factura | Rebutjar accions critiques per `GET` i exigir POST/permis/motiu | INTRANET, SEGURETAT, BACKEND |

### E. URLs, documents i enllac segur

| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| Intranet/interficies | URLs, pay.prisma.cat i enllac segur | Tipificar URLs de pagament: individu, pack, grup, regal, empresa, USOC, canvi curs, morositat | PAY.PRISMA, URL, PAGAMENTS |
| Intranet/interficies | URLs, pay.prisma.cat i enllac segur | Crear estat visual per factura ja pagada: veure factura en comptes de pagar | INTRANET, UI, DOCUMENTS |
| Intranet/interficies | URLs, pay.prisma.cat i enllac segur | Crear estat visual `PDF/QR pendent` sense regenerar document | INTRANET, PDF, QR |
| Intranet/interficies | URLs, pay.prisma.cat i enllac segur | Crear estat visual d'incidencia documental quan falla PDF/QR | INTRANET, INCIDENCIA, DOCUMENTS |
| Intranet/interficies | URLs, pay.prisma.cat i enllac segur | Crear endpoint d'enllac segur que consulta SIF i permisos sense exposar ruta interna | INTRANET, SEGURETAT, DOCUMENTS |
| Intranet/interficies | URLs, pay.prisma.cat i enllac segur | Bloquejar visibilitat de factura completa de grup a participants | INTRANET, GRUP, PRIVACITAT |
| Intranet/interficies | URLs, pay.prisma.cat i enllac segur | Permetre acces segur d'empresa/responsable a factura/PDF/QR | INTRANET, EMPRESA, ENLLAC SEGUR |

### F. Correus i plantilles

| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| Intranet/interficies | Correus i plantilles SIF | Mapar per cada apartat: PHP, JS, AJAX, metode `Intranet.php`, Template/codi directe i moment d'enviament | CORREUS, DOCUMENTACIO, INTRANET |
| Intranet/interficies | Correus i plantilles SIF | Revisar tots els correus amb `[URL_PAGAMENT]` o URL TPV manual | CORREUS, PAY.PRISMA, REVISIO |
| Intranet/interficies | Correus i plantilles SIF | Afegir URL de consulta factura quan el pagament ja esta fet | CORREUS, DOCUMENTS, ENLLAC SEGUR |
| Intranet/interficies | Correus i plantilles SIF | Decidir PDF adjunt vs enllac segur segons estat `factura_documents` | CORREUS, PDF, SEGURETAT |
| Intranet/interficies | Correus i plantilles SIF | Correu factura abans de cobrament: no prometre pagament fet | CORREUS, FACTURA ABANS, TEXT |
| Intranet/interficies | Correus i plantilles SIF | Correu regal: separar targeta comercial de factura fiscal SIF | CORREUS, REGAL, DOCUMENTS |
| Intranet/interficies | Correus i plantilles SIF | Correu pack/grup: no exposar dades fiscals alienes ni factura completa a participants | CORREUS, PACK, GRUP |
| Intranet/interficies | Correus i plantilles SIF | Convertir correu intern de `realitzaPagamentAutomatic.php` en log/notificacio SIF o template controlat | CORREUS, REDSYS, NOTIFICACIO |

### G. Notificacions i panell SIF

| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| Intranet/interficies | Notificacions i incidencies SIF | Distingir visualment avis, notificacio, incidencia i indicador | INTRANET, NOTIFICACIONS, UI |
| Intranet/interficies | Notificacions i incidencies SIF | Mostrar responsable, prioritat, estat i log d'incidencia SIF | INTRANET, INCIDENCIA, OPERACIO |
| Intranet/interficies | Notificacions i incidencies SIF | Definir accions per incidencia PDF/QR pendent o fallit | INTRANET, PDF, INCIDENCIA |
| Intranet/interficies | Notificacions i incidencies SIF | Definir accions per incidencia AEAT/retry | INTRANET, AEAT, INCIDENCIA |
| Intranet/interficies | Panell SIF / Apartat VERI*FACTU intranet | Dashboard amb pendents: AEAT, PDF/QR, incidencies, cues i versions | PANELL SIF, UI, INTRANET |
| Intranet/interficies | Panell SIF / Apartat VERI*FACTU intranet | Vista factures SIF amb filtres i estat de cobrament/document/AEAT | PANELL SIF, FACTURACIO, UI |
| Intranet/interficies | Panell SIF / Apartat VERI*FACTU intranet | Vista documents SIF sense ruta interna | PANELL SIF, DOCUMENTS, SEGURETAT |
| Intranet/interficies | Panell SIF / Apartat VERI*FACTU intranet | Vista versions SIF i declaracio responsable activa | PANELL SIF, VERSIONS, DOCUMENTACIO |

## Targetes que falten - Proves

### A. Fase 11 Redsys curs normal

| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| Proves/entorns/produccio | Fase 11 - Redsys curs normal | Executar `preflight-redsys-course.php` en preproduccio | PREFLIGHT, REDSYS, CURS NORMAL |
| Proves/entorns/produccio | Fase 11 - Redsys curs normal | Executar `preview-redsys-course.php DS_ORDER` i guardar payload | PREVIEW, REDSYS, EVIDENCIA |
| Proves/entorns/produccio | Fase 11 - Redsys curs normal | Executar `process-redsys-course.php DS_ORDER` amb `SIF_ENV=test` | PROCESS, REDSYS, PREPRODUCCIO |
| Proves/entorns/produccio | Fase 11 - Redsys curs normal | Evidencia `SIF-RED-001`: Redsys curs normal crea factura/cobrament una sola vegada | SIF-RED-001, REDSYS, EVIDENCIA |
| Proves/entorns/produccio | Fase 11 - Redsys curs normal | Provar callback duplicat mateix `DS_ORDER` sense duplicar factura, pagament ni numeracio | REDSYS, IDEMPOTENCIA, TESTING |
| Proves/entorns/produccio | Fase 11 - Redsys curs normal | Provar signatura incorrecta abans de tocar BD fiscal o legacy | REDSYS, SEGURETAT, TESTING |
| Proves/entorns/produccio | Fase 11 - Redsys curs normal | Provar `Ds_Amount` diferent i crear incidencia sense emetre automaticament | REDSYS, INCIDENCIA, TESTING |

### B. Fase 11 manuals curs normal

| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| Proves/entorns/produccio | Fase 11 - Manuals curs normal | Executar `preflight-manual-course.php` | PREFLIGHT, MANUAL, CURS NORMAL |
| Proves/entorns/produccio | Fase 11 - Manuals curs normal | Executar `preview-manual-course.php IDPAG AMOUNT MOVEMENT_DATE` | PREVIEW, MANUAL, EVIDENCIA |
| Proves/entorns/produccio | Fase 11 - Manuals curs normal | Executar `process-manual-course.php IDPAG AMOUNT MOVEMENT_DATE` | PROCESS, MANUAL, PREPRODUCCIO |
| Proves/entorns/produccio | Fase 11 - Manuals curs normal | Evidencia `SIF-PAY-001`: transferencia contra factura SIF existent | SIF-PAY-001, TRANSFERENCIA, EVIDENCIA |
| Proves/entorns/produccio | Fase 11 - Manuals curs normal | Evidencia `SIF-PAY-001B`: transferencia sense factura SIF previa emet `issueInvoice(payment)` | TRANSFERENCIA, ISSUEINVOICE, EVIDENCIA |
| Proves/entorns/produccio | Fase 11 - Manuals curs normal | Provar referencia bancaria repetida i fallback factura/data/import/banc | IDEMPOTENCIA, TRANSFERENCIA, TESTING |
| Proves/entorns/produccio | Fase 11 - Manuals curs normal | Provar import superior al pendent com a bloqueig o incidencia | TRANSFERENCIA, INCIDENCIA, TESTING |

### C. Factura abans de cobrament

| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| Proves/entorns/produccio | Proves intranet i permisos | Evidencia `SIF-FAC-001`: factura abans + pagament posterior per `registerPayment()` | SIF-FAC-001, FACTURA ABANS, EVIDENCIA |
| Proves/entorns/produccio | Proves intranet i permisos | Provar barreja de cursos bloquejada al servidor | FACTURA ABANS, VALIDACIO, TESTING |
| Proves/entorns/produccio | Proves intranet i permisos | Provar barreja d'edicions bloquejada al servidor | FACTURA ABANS, VALIDACIO, TESTING |
| Proves/entorns/produccio | Proves intranet i permisos | Provar doble clic/reintent retorna mateixa factura | FACTURA ABANS, IDEMPOTENCIA, TESTING |
| Proves/entorns/produccio | Proves intranet i permisos | Provar receptor fiscal incomplet bloquejat | FACTURA ABANS, RECEPTOR, TESTING |
| Proves/entorns/produccio | Proves intranet i permisos | Provar que `E_FACT = 0` i `EMESA_ABANS_COBRAMENT = 1` | FACTURA ABANS, E_FACT, TESTING |

### D. Packs

| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| Proves/entorns/produccio | Fase 11 - Packs | Executar `preflight-redsys-pack.php` | PREFLIGHT, REDSYS, PACK |
| Proves/entorns/produccio | Fase 11 - Packs | Executar `preview-redsys-pack.php DS_ORDER` i revisar dues linies | PREVIEW, REDSYS, PACK |
| Proves/entorns/produccio | Fase 11 - Packs | Executar `process-redsys-pack.php DS_ORDER` | PROCESS, REDSYS, PACK |
| Proves/entorns/produccio | Fase 11 - Packs | Executar `preflight-manual-pack.php` | PREFLIGHT, MANUAL, PACK |
| Proves/entorns/produccio | Fase 11 - Packs | Executar `preview-manual-pack.php IDPAG AMOUNT MOVEMENT_DATE` | PREVIEW, MANUAL, PACK |
| Proves/entorns/produccio | Fase 11 - Packs | Executar `process-manual-pack.php IDPAG AMOUNT MOVEMENT_DATE` | PROCESS, MANUAL, PACK |
| Proves/entorns/produccio | Fase 11 - Packs | Evidencia pack Redsys: una factura, dues linies i descompte `PACK` | PACK, REDSYS, EVIDENCIA |
| Proves/entorns/produccio | Fase 11 - Packs | Evidencia pack manual: import manual igual al total fiscal | PACK, MANUAL, EVIDENCIA |

### E. Grups

| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| Proves/entorns/produccio | Fase 11 - Grups | Validar SQL final de `descomptes_grup` | GRUP, BD, PENDENT DADA |
| Proves/entorns/produccio | Fase 11 - Grups | Executar payload grup amb receptor `respGrups` | GRUP, PAYLOAD, TESTING |
| Proves/entorns/produccio | Fase 11 - Grups | Provar `VISIBLE_ALUMNE = 0` per participants | GRUP, PRIVACITAT, TESTING |
| Proves/entorns/produccio | Fase 11 - Grups | Provar que participant no veu factura completa del grup | GRUP, INTRANET, PRIVACITAT |
| Proves/entorns/produccio | Fase 11 - Grups | Provar callback duplicat de grup sense duplicar factura ni linies | GRUP, REDSYS, IDEMPOTENCIA |

### F. Regals

| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| Proves/entorns/produccio | Fase 11 - Regals | Validar SQL final de `regal`, `FACT_REL`, `ORIGEN`, `DESTI` i `CODI` | REGAL, BD, PENDENT DADA |
| Proves/entorns/produccio | Fase 11 - Regals | Executar payload regal amb comprador com a receptor fiscal | REGAL, PAYLOAD, TESTING |
| Proves/entorns/produccio | Fase 11 - Regals | Provar callback duplicat de regal sense duplicar factura ni actualitzar dues vegades `FACT_REL` | REGAL, REDSYS, IDEMPOTENCIA |
| Proves/entorns/produccio | Fase 11 - Regals | Provar bescanvi posterior del codi sense segona factura | REGAL, BESCANVI, TESTING |
| Proves/entorns/produccio | Fase 11 - Regals | Provar que la targeta regal comercial no exposa factura fiscal al destinatari | REGAL, PRIVACITAT, DOCUMENTS |

### G. Proves intranet i permisos

| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| Proves/entorns/produccio | Proves intranet i permisos | Provar endpoint bloquejat per usuari sense permis encara que conegui la URL | INTRANET, PERMISOS, SEGURETAT |
| Proves/entorns/produccio | Proves intranet i permisos | Provar `Passar pagaments` amb cap criteri informat | INTRANET, PAGAMENTS, TESTING |
| Proves/entorns/produccio | Proves intranet i permisos | Provar `Passar pagaments` amb mes d'un criteri informat | INTRANET, PAGAMENTS, TESTING |
| Proves/entorns/produccio | Proves intranet i permisos | Provar `Consulta / Modifica alumne`: editar dades personals amb factura emesa no canvia factura | INTRANET, FACTURACIO, TESTING |
| Proves/entorns/produccio | Proves intranet i permisos | Provar `Consulta / Edita / Anula factura`: accions critiques per `GET` rebutjades | INTRANET, SEGURETAT, TESTING |
| Proves/entorns/produccio | Proves intranet i permisos | Provar enllac segur valid, caducat, invalid i d'una altra factura | ENLLAC SEGUR, DOCUMENTS, TESTING |
| Proves/entorns/produccio | Proves intranet i permisos | Provar PDF/QR pendent mostra estat i no regenera dades vives | PDF, QR, INTRANET |

### H. Fitxer TPV i entrada unica de pagaments

| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| Proves/entorns/produccio | Proves intranet i permisos | Provar fitxer TPV pujat dues vegades amb hash idempotent | TPV, IDEMPOTENCIA, TESTING |
| Proves/entorns/produccio | Proves intranet i permisos | Provar linia TPV clara contra factura pendent proposa `registerPayment()` | TPV, REGISTERPAYMENT, TESTING |
| Proves/entorns/produccio | Proves intranet i permisos | Provar linia TPV clara sense factura proposa `issueInvoice(payment)` nomes si quadra origen/import/receptor | TPV, ISSUEINVOICE, TESTING |
| Proves/entorns/produccio | Proves intranet i permisos | Provar linia TPV amb import o titular ambigu crea incidencia | TPV, INCIDENCIA, TESTING |
| Proves/entorns/produccio | Proves intranet i permisos | Provar una transferencia que paga diverses factures amb una transaccio i diverses allocations | TRANSFERENCIA, ALLOCATION, TESTING |

### I. Documents, AEAT i go/no-go

| Trello | Llista | Targeta proposada | Etiquetes |
|---|---|---|---|
| Proves/entorns/produccio | Documents, PDF/QR i enllac segur | Evidencia `SIF-DOC-001`: PDF/QR/XML guardat a `factura_documents` amb hash | SIF-DOC-001, DOCUMENTS, EVIDENCIA |
| Proves/entorns/produccio | Documents, PDF/QR i enllac segur | Provar error PDF/QR crea incidencia i no desfà factura | PDF, QR, INCIDENCIA |
| Proves/entorns/produccio | Documents, PDF/QR i enllac segur | Provar descarrega document sense exposar `PATH_FITXER` | DOCUMENTS, SEGURETAT, TESTING |
| Proves/entorns/produccio | Documents, PDF/QR i enllac segur | Evidencia `SIF-AEA-001`: error AEAT crea cua/retry i incidencia | SIF-AEA-001, AEAT, EVIDENCIA |
| Proves/entorns/produccio | Paquet go/no-go final | Crear paquet go/no-go Fase 11 amb commit, entorn, migracions, proves i resultat | GO-NO-GO, FASE 11, EVIDENCIA |
| Proves/entorns/produccio | Paquet go/no-go final | Decidir `GO`, `GO AMB LIMITACIONS` o `NO-GO` despres de proves bloquejants | GO-NO-GO, PRODUCCIO, DECISIO |

## Que recol.locaria ara mateix

1. Moure o duplicar les targetes de `Preproduccio / entorn de proves` que estan a Fitxes cap a Proves.
2. Treure del calaix `Sistema de notificacions` les targetes que son pantalles concretes i portar-les a una llista de pantalla.
3. Dividir `Go-no-go i evidencies` en llistes per fase/flux, perque 457 targetes fan dificil veure que falta.
4. Mantenir `Correus i plantilles`, pero afegir targetes de mapa real de correu per apartat.
5. Crear les llistes Fase 11 a Proves abans d'afegir mes targetes, perque si no es tornaran a barrejar.

## Prioritat

1. `Passar pagaments / conciliacio SIF`, perque es el canvi de flux mes gran respecte al sistema antic.
2. `Generar factura abans de cobrament`, perque genera factura real abans del pagament i pot duplicar si no queda ben provat.
3. Scripts Fase 11 (`preflight`, `preview`, `process`) i evidencies per curs/pack/manual.
4. Grups i regals, per privacitat i receptor fiscal diferent.
5. Enllac segur, PDF/QR pendent i correus, per evitar comunicacions incorrectes.

## Conclusio

La teva intuicio es correcta: en aquests dos Trellos falten targetes. Hi ha bona cobertura documental, pero encara no hi ha prou targeta petita per executar, provar i validar pantalla per pantalla.

Les targetes que falten no son "mes del mateix"; son les que fan visible el comportament real: que veu l'usuari, quin boto queda bloquejat, quin avís surt, quina crida SIF es fa, quin test ho demostra i quina evidencia queda guardada.
