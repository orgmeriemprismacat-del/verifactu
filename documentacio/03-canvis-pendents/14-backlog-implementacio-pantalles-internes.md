# Backlog d'implementacio de pantalles internes

Data de tall: 2026-09-16

Estat: backlog preparat a partir de la copia de codi disponible; implementacio al repositori real pendent.

## 1. Objectiu

Aquest document transforma el contracte tecnic i les fitxes UI en tasques petites, ordenades i verificables. Les referencies a `codi-drive/intranet-nova-canvis-verifactu` son evidencia del codi disponible, no una autoritzacio per modificar aquella copia.

Documents d'entrada:

- `12-contracte-tecnic-pantalles-internes.md`;
- `13-fitxes-ui-pantalles-internes.md`;
- `../05-governanca-operacio/20-pla-proves-validacio-sif.md`;
- `../05-governanca-operacio/21-seguretat-permisos-accessos.md`.

## 2. Punts reals localitzats

| Area | Ruta o metode actual | Evidencia | Canvi principal |
|---|---|---|---|
| Router de pantalles | `Intranet.php`, rutes `/alumnes/pagaments/`, `/alumnes/genera-factura-abans-pagar/` i `/alumnes/factura/` | Bloc de seleccio de ruta | Mantenir URL si cal, substituir l'accio interna per adaptador SIF |
| Passar pagaments | `mostrarModalConfPag()` i `efectuarPagament()` | `Intranet.php` | Separar consulta, preview i confirmacio |
| AJAX pagament | `ajax/alumnes/efectuarPagament.php` | Llegeix `$_GET` i crida directament `efectuarPagament()` | Migrar a `POST` JSON o formulari protegit, CSRF i resposta estructurada |
| Variants de pagament | `efectuarPagamentRegal()`, `efectuarPagamentGrupal()`, `efectuarPagamentPack()`, `efectuarPagamentInscripcio()` | `Intranet.php` | Convertir-les en resolucio d'origen/payload; el SIF executa l'efecte fiscal |
| Factura ja generada | `efectuarPagamentFacturaGenerada()` | `Intranet.php` | Substituir actualitzacio llegada per `ManualPaymentService`/`registerPayment()` i sync posterior controlat |
| Factura abans de pagar | `__mostrarPage_Alumnes_GeneraFacturaAbansPagar()` i `generarFacturaElectronica_Alumnes()` | `Intranet.php` | Fer preview/confirm sobre `InvoiceBeforePaymentService` |
| Consulta de factures | `mostrarTotesFacturesUsuari_Factures()` i consultes `buscarInfoFactura*` | `Intranet.php` | Crear DTO de lectura SIF + legacy i `available_actions` |
| Edicio directa | consulta `updDadesFact` | `Intranet.php` | Limitar a dades no fiscals; bloquejar camps fiscals emesos |
| Anulacio | `__modalAnularFactura_Factures()`, `modalAnularFactura_Factures()` i `anularFactura()` | `Intranet.php` | Substituir per classificador i `ManualRectificationService`; no fer update destructiu |
| Marcatges llegats | `updFactGenerada`, `updGeneratFactura`, `updInscFacturaPrePag` | `Intranet.php` | Executar nomes com a sincronitzacio posterior al resultat SIF, amb auditoria |

## 3. Riscos que bloquegen una substitucio directa

1. `ajax/alumnes/efectuarPagament.php` rep import, data, banc, observacions, factura i tipus per `GET`. Aquest contracte no es pot conservar per a operacions economiques.
2. La sessio serialitza objectes `usuari` i `intranet`; abans de crear endpoints nous cal definir un bootstrap autenticat comu i evitar confiar en dades d'actor enviades pel client.
3. `Intranet.php` barreja HTML, SQL, decisions fiscals, correus i mutacions. No s'ha de reescriure tot en una sola entrega.
4. Les variants regal, grup, pack i inscripcio tenen efectes laterals propis. Cal inventariar-los i separar els que son fiscals dels administratius.
5. Les taules llegades continuen necessaries per operacio. La sincronitzacio ha de ser posterior al commit SIF i no pot convertir-se en font de veritat fiscal.
6. `updDadesFact` permet canviar camps fiscals d'una factura. Ha de quedar bloquejat per a factures SIF emeses abans d'activar la nova pantalla.
7. `anularFactura()` no es pot mapar automaticament a una unica accio: cal classificar rectificacio, anul·lacio registral, subsanacio, devolucio o cap efecte fiscal.

## 4. Estrategia de migracio

Aplicar una substitucio progressiva per ruta:

```text
Pantalla actual
  -> adaptador intranet autenticat
  -> endpoint intern preview/confirm
  -> servei SIF existent
  -> resultat SIF immutable
  -> sincronitzacio llegada controlada
  -> refresc de pantalla des del SIF
```

Durant la transicio, una bandera per funcionalitat pot activar el flux nou per usuaris de prova. No es permet doble escriptura fiscal: si el flux SIF esta actiu, la branca llegada no emet ni renumera factures.

## 5. Epic A - Base comuna d'integracio

### UI-INT-001 - Bootstrap autenticat d'endpoints interns

Objectiu: resoldre usuari, rol, sessio, entorn, `request_id` i CSRF en un unic punt.

Fitxers previstos al repositori real:

- nou bootstrap HTTP intern;
- adaptador de sessio de la intranet;
- middleware o helper de permisos;
- proves d'accés autoritzat i denegat.

Criteris d'acceptacio:

- el rol no s'accepta des del cos de la peticio;
- una sessio absent o invalida rep `401`/`403` sense dades de l'objecte;
- les mutacions exigeixen `POST` i CSRF;
- cada resposta inclou `request_id`.

### UI-INT-002 - Client SIF intern

Objectiu: encapsular URL, timeout, autenticacio de servei, JSON i errors del SIF.

Criteris:

- secrets fora del codi i del navegador;
- timeout i error de connexio es mapen a `SIF_UNAVAILABLE`;
- no hi ha reintent automatic d'una confirmacio sense idempotencia;
- logs sense tokens ni dades bancaries completes.

### UI-INT-003 - Contracte d'avisos i errors

Implementar `INFO`, `WARNING`, `BLOCKING` i `INCIDENT`, amb els codis definits al contracte tecnic. Afegir component comu de pantalla i proves de renderitzat/escape.

### UI-INT-004 - Preview token

Crear token signat o persistent amb actor, accio, objecte, hash de dades, caducitat i estat observat. Confirmar invalida el token; qualsevol canvi material exigeix nou preview.

## 6. Epic B - Consulta de factura

### UI-FACT-001 - Repositori de lectura per UI

Crear una consulta que compongui factura SIF, pagaments, documents, rectificatives, relacions, incidencies i origen legacy. No retornar `SELECT *` a la UI.

### UI-FACT-002 - Calcul d'accions disponibles

Retornar `available_actions` segons rol, tipus, estat fiscal, cobrament, AEAT, document i incidencies. Provar com a minim `VIEW`, `DOWNLOAD_PDF`, `REGISTER_PAYMENT`, `RECTIFY` i `DIRECT_EDIT` bloquejat.

### UI-FACT-003 - Adaptar llistat i detall

Adaptar `/alumnes/factura/` per consumir la consulta nova. Mantenir temporalment la cerca legacy nomes com a origen auxiliar; el detall fiscal surt del SIF.

### UI-FACT-004 - Tallar edicio fiscal directa

Separar camps administratius dels fiscals. Per factures SIF emeses, impedir que `updDadesFact` canviï receptor, concepte, base, impostos, import, data, serie o numero.

## 7. Epic C - Passar pagaments

### UI-PAY-001 - Consulta i seleccio

Adaptar `__mostrarPage_Alumnes_Pagaments()` i `mostrarModalConfPag()` per mostrar factura SIF, pendent calculat, origen i conciliacio.

### UI-PAY-002 - Preview de pagament

Crear endpoint intern `POST /api/internal/payments/preview`. Normalitzar UUID/numero, data, import, metode, referencia i observacions. El servidor decideix `registerPayment()`, emissio amb pagament o bloqueig.

### UI-PAY-003 - Confirmacio idempotent

Crear `POST /api/internal/payments/confirm` sobre `ManualPaymentService` i auditoria. Una referencia repetida retorna el resultat existent o bloqueig coherent.

### UI-PAY-004 - Substituir AJAX legacy

Retirar l'us operatiu de `ajax/alumnes/efectuarPagament.php` per al flux nou. No enviar dades economiques per query string. Mantenir una resposta de compatibilitat controlada mentre existeixin crides antigues, sense executar doble efecte.

### UI-PAY-005 - Sincronitzacio posterior

Despres de resultat SIF confirmat, actualitzar els camps administratius llegats estrictament necessaris mitjancant un servei separat i auditat. Si falla, crear incidencia de divergencia sense revertir ni repetir el pagament SIF.

## 8. Epic D - Factura abans del cobrament

### UI-PRE-001 - Seleccio i receptor

Adaptar `__mostrarPage_Alumnes_GeneraFacturaAbansPagar()` als tres passos de la fitxa UI i validar cobertura, receptor i agrupacio.

### UI-PRE-002 - Preview fiscal

Crear `POST /api/internal/invoices/before-payment/preview` sobre `InvoiceBeforePaymentPayloadBuilder`, sense cridar `issueInvoice()` ni incloure `payment`.

### UI-PRE-003 - Confirmacio

Crear `POST /api/internal/invoices/before-payment/confirm` sobre `InvoiceBeforePaymentService`, amb idempotencia, actor i auditoria.

### UI-PRE-004 - Substituir generacio llegada

Deixar `generarFacturaElectronica_Alumnes()` com a adaptador temporal o retirar-ne l'emissio directa. `updInscFacturaPrePag` i `updGeneratFactura` nomes s'executen com a sync posterior amb UUID SIF conservat.

## 9. Epic E - Rectificacio i anulacio

### UI-RECT-001 - Classificador previ

Abans de mostrar `Anul·lar`, classificar el cas: rectificativa per diferencies, substitucio, registre d'anul·lacio, subsanacio, devolucio/saldo o cap efecte fiscal. Els casos no suportats obren incidencia.

### UI-RECT-002 - Preview comparatiu

Crear preview amb original, proposta, motiu, imports i efecte sobre cobrament. No modificar l'original.

### UI-RECT-003 - Confirmacio de rectificativa

Integrar `ManualRectificationService` per als casos admesos. Retornar original i rectificativa relacionats.

### UI-RECT-004 - Desactivar mutacio destructiva

Per factures SIF, `anularFactura()` no pot esborrar, renumerar ni reescriure la factura original. Les branques llegades queden nomes per documents historics expressament classificats.

## 10. Epic F - Portals i avisos

### UI-VIS-001 - Consulta d'alumne

Resoldre l'alumne des de la sessio i filtrar per receptor/visibilitat. Provar URL manipulada, factura d'empresa i grup amb altres participants.

### UI-VIS-002 - Empresa/responsable

Implementar token amb caducitat, revocacio, abast de documents i registre de descàrrega. No reutilitzar la sessio interna de gestio.

### UI-AVI-001 - Resum VERI*FACTU

Crear resum real de cua, errors, incidencies i documents. Una fallada de connexio retorna estat desconegut, mai `OK`.

### UI-AVI-002 - Navegacio segons rol

Mostrar comptadors i enllacos filtrats. Auditor en lectura; alumne i empresa sense accés al resum intern.

## 11. Ordre de lliurament

| Tall | Tasques | Condicio de sortida |
|---|---|---|
| 1. Base segura | UI-INT-001..004 | Auth, permisos, CSRF, errors i preview token provats |
| 2. Lectura | UI-FACT-001..003 | Consulta sense mutacions i accions calculades |
| 3. Pagaments | UI-PAY-001..005 | `SIF-PANT-PAY-*` superades en preproduccio |
| 4. Factura previa | UI-PRE-001..004 | `SIF-PANT-FAC-001` superada |
| 5. Correccio | UI-FACT-004 + UI-RECT-001..004 | `SIF-PANT-FACT-*` superades |
| 6. Visibilitat i avisos | UI-VIS-* + UI-AVI-* | `SIF-VIS-002` i `SIF-AVI-*` superades |
| 7. Tancament | captures i go/no-go | Evidencies, rollback i permisos validats |

No començar els talls 3 a 6 sense haver tancat la base segura. Es pot implementar lectura abans que mutacio per validar integracio, permisos i disseny amb menys risc.

### 11.1. Correspondencia amb proves i paquets d'evidencia

| Tall | Proves bloquejants | Paquet |
|---|---|---|
| Base segura | `SIF-PANT-SEC-001..004` | `EVID-UI-BASE` |
| Pagaments | `SIF-PANT-PAY-001..002` | `EVID-UI-PAY` |
| Factura previa | `SIF-PANT-FAC-001` | `EVID-UI-FAC` |
| Correccio | `SIF-PANT-FACT-001..002` | `EVID-UI-RECT` |
| Visibilitat externa | `SIF-VIS-002` | `EVID-UI-VIS` |
| Indicadors i avisos | `SIF-AVI-001..002` | `EVID-UI-AVI` |

La definicio detallada de proves es troba a `20-pla-proves-validacio-sif.md`; la composicio i custodia dels paquets es troba a `23-annex-captures-pantalla.md`.

## 12. Definicio de fet per tasca

Una tasca no queda tancada fins que:

- el codi es al repositori real corresponent, no nomes a `codi-drive`;
- te prova automatica de cas correcte, permís denegat i error rellevant;
- no crea una segona font de veritat fiscal;
- conserva idempotencia i auditoria;
- no exposa secrets ni dades d'altres subjectes;
- s'ha provat en escriptori i mobil si afecta UI;
- la documentacio i la matriu de cobertura indiquen el resultat real;
- existeix captura o evidencia associada a l'ID de prova;
- no hi ha cap `TODO` que amagui un bloqueig fiscal o de seguretat.

## 13. Fora d'abast d'aquest backlog

- transport AEAT real, XML/XSD i certificat;
- reescriptura completa del monolit `Intranet.php`;
- migracio historica massiva;
- decisio fiscal de casos encara marcats com a bloquejants;
- activacio en produccio o eliminacio definitiva del flux llegat.

Aquests punts tenen plans propis i no s'han de donar per resolts pel fet d'implementar les pantalles.
