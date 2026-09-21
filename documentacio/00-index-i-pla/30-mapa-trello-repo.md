# 30 - Mapa Trello - Repo VERI*FACTU/SIF

> Revisio de coherencia entre els taulers Trello 1, 4, 5 i 6, el repositori local, la documentacio del projecte i el xat original recuperat.

## 1. Fonts revisades

- Export Trello 1: `C:/Users/Usuario/Downloads/EL6BCeUI - 1-verifactu-sif-control-del-projecte.json`
- Export Trello 4: `C:/Users/Usuario/Downloads/O2xZJHSp - 4-verifactu-sif-intranet-interficie-i-notificacions (1).json`
- Export Trello 5: `C:/Users/Usuario/Downloads/ZOXW1AyY - 5-verifactu-sif-proves-entorns-i-produccio.json`
- Export Trello 6: `C:/Users/Usuario/Downloads/MQ8dR2NZ - 6-verifactu-sif-sif-payprismacat (1).json`
- Repo local: `checkpoint/sif-fase-0-4`, commit base revisat `2db68e1`.
- Xat original recuperat: `recuperacio-verifactu/xat-original/rollout-2026-05-13T22-15-39-019e22fb-0e44-71f3-81ef-e0b9d0dcd2a7.jsonl`.
- Documents principals: `documentacio/03-canvis-pendents/07-pantalles-intranet.md`, `documentacio/04-estat-final/16-estat-final-pantalles.md`, `documentacio/04-estat-final/25-panell-sif-pay-prisma.md`, `documentacio/05-governanca-operacio/20-pla-proves-validacio-sif.md`.

## 2. Responsabilitat de cada Trello

| Trello | Tauler | Cobreix | No hauria de ser la font principal de |
| --- | --- | --- | --- |
| Trello 1 | Control del projecte | Control, documentacio, decisions, governanca, criteris de tancament, mapa de cobertura, estat del projecte. | Programacio detallada, proves executables o captures finals com a feina principal. |
| Trello 4 | Intranet, interfície i notificacions | Pantalles, interfície, avisos, permisos visibles, correus, notificacions, captures visuals i evidencies de pantalla. | Motor SIF backend, migracions fiscals o proves de servidor sense component visual. |
| Trello 5 | Proves, entorns i produccio | Proves, entorns, preproduccio, go/no-go, regressio, evidencies, captures, logs, PDF/QR, exports i validacio final. | Decisions funcionals principals o desenvolupament del motor SIF. |
| Trello 6 | SIF pay.prisma.cat | Motor SIF, API, BD fiscal, serveis, casos d'us, programacio backend, scripts, workers, hash chain, idempotencia, AEAT i panell SIF. | Evidencia final de prova si ja correspon al Trello 5. |

Regla practica: una targeta pot aparèixer en mes d'un Trello si el nivell canvia. Exemple: `Redsys pack` pot tenir decisio a Trello 1, pantalla a Trello 4, prova a Trello 5 i implementacio a Trello 6.

## 3. Estat resum dels exports del 2026-06-14

| Trello | Targetes obertes | Llistes obertes | Sense etiqueta | Sense descripcio | Duplicats per nom |
| --- | ---: | ---: | ---: | ---: | ---: |
| Trello 1 - Control | 3.241 | 79 | 27 | 17 | 495 grups / 978 sobrants |
| Trello 4 - Interficie | 4.101 | 148 | 0 | 0 | 146 grups / 356 sobrants |
| Trello 5 - Proves | 3.194 | 137 | 1 | 0 | 470 grups / 500 sobrants |
| Trello 6 - SIF pay.prisma.cat | 3.528 | 148 | 3 | 209 | 91 grups / 95 sobrants |

Lectura: el volum ja existeix. El risc principal no es falta absoluta de targetes, sino traçabilitat, duplicats, targetes generiques i equivalencies poc visibles entre repo, Trello i flux real.

## 4. Traçabilitat minima obligatoria

Cada flux critic ha de poder seguir aquesta cadena:

```text
decisio o cas d'us
    -> criteri fiscal i funcional
    -> servei/script/API SIF
    -> pantalla o canal afectat
    -> prova Trello 5
    -> evidencia
    -> documentacio repo
```

Si una baula falta, el cas pot estar analitzat o programat, pero no s'ha de considerar tancat.

## 5. Coherencia repo -> Trello per fluxos implementats

| Flux repo / xat original | Repo actual | Trello 1 | Trello 4 | Trello 5 | Trello 6 | Lectura |
| --- | --- | --- | --- | --- | --- | --- |
| Redsys curs normal | Scripts `preflight/preview/process-redsys-course.php` i serveis Redsys | Si | Si | Si | Si | Cobert de punta a punta; Trello 5 ha de conservar evidencies reals. |
| Redsys pack | Scripts `redsys-pack` i payload legacy pack | Si | Si | Si | Si | Cobert; revisar proves multi-linia i PDF/QR. |
| Redsys regal | Scripts `redsys-gift` i criteri comprador/bescanvi | Si | Si | Si | Si | Cobert; cal que el bescanvi no generi segona factura. |
| Redsys grup | Scripts `redsys-group`, serveis grup i relacions no visibles a alumne | Si | Si | Si | Si | Cobert recentment; prioritat alta de prova en preproduccio. |
| Redsys USOC | Scripts `redsys-usoc` i separacio alumne/entitat | Si | Si | Si | Si | Cobert; verificar doble receptor i idempotencia. |
| Manual curs | Scripts `manual-course` | Si | Si | Si | Si | Cobert; ha de passar per preview abans de processar. |
| Manual pack | Scripts `manual-pack` | Si | Si | Si | Si | Cobert; validar linies i imports. |
| Manual grup | Scripts `manual-group` | Si | Parcial | Parcial | Parcial | El repo el te, pero als Trellos no sempre apareix literalment com `manual-group`; cal deixar l'equivalencia visible. |
| Manual regal | Scripts `manual-gift` | Si | Si | Si | Si | Cobert; revisar comprador/beneficiari. |
| Pagament manual / transferencia | Scripts `manual-payment` i `registerPayment()` | Si | Si | Si | Si | Cobert; punt critic de Trello 4 i Trello 5. |
| Factura manual | Scripts `manual-invoice` | Si | Si | Parcial | Parcial | Al Trello 6 apareix sobretot com `Factura ordinaria A`; cal fer explicita l'equivalencia `manual-invoice` -> `Factura ordinaria A` / `Factures manuals`. |
| Pagament fraccionat | Scripts `manual-installment` | Si | Si | Si | Si | Cobert; validar assignacions parcials i estat de cobrament. |
| Rectificativa manual | Scripts `manual-rectification` | Si | Si | Si | Si | Cobert; revisar bloqueig d'edicio directa. |
| Devolucio manual | Scripts `manual-refund` | Si | Si | Si | Si | Cobert; vincular amb baixa, saldo o rectificativa segons cas. |
| Reclamacio de pagament | Scripts `claim-payment` i servei `ClaimPaymentService` | Si | Si | Si | Si | Cobert recentment; no crea factura nova, registra cobrament contra factura existent. |
| Saldo a favor | Scripts `credit-balance` | Si | Si | Si | Si | Cobert; validar no barrejar saldo amb rectificativa. |
| Compensacio de saldo | Scripts `credit-compensation` | Si | Si | Si | Si | Cobert; cal prova de compensacio futura. |
| Migracio factura historica | Scripts `historical-invoice-migration` | Si | Si | Si | Si | Cobert; ha de quedar clar que historic no SIF no genera PDF immutable SIF nou. |
| Factura abans de cobrament | Scripts `invoice-before-payment` | Si | Si | Si | Si | Cobert; es una zona critica de pantalla i prova. |
| Entitat USOC | Scripts `usoc-entity` | Si | Si | Si | Si | Cobert; revisar relacio amb factura alumne/entitat. |
| Preflight | `preflight-*` i `preflight-sif.php` | Si | Si | Si | Si | Cobert; ha de bloquejar activacio sense prerequisits. |
| Preview | `preview-*` | Si | Si | Si | Parcial | Trello 5 ho cobreix be; Trello 6 no sempre usa literal `preview`, pot estar integrat en casos d'us. |
| Process | `process-*` | Si | Si | Si | Parcial | Trello 6 parla de serveis/processament, pero convindria marcar scripts concrets en targetes de programacio. |
| Go/no-go | `go-no-go-preproduction.php` | Si | Si | Si | Si | Cobert; Trello 5 ha de ser la font d'evidencia. |

## 6. Documents repo que fan de pont

| Necessitat | Document repo |
| --- | --- |
| Document mare i arquitectura global | `documentacio/00-index-i-pla/documentacio-verifactu.md` |
| Pla tecnic executable del SIF | `documentacio/00-index-i-pla/29-pla-implementacio-tecnica-sif.md` |
| Mapa Trello-repo | `documentacio/00-index-i-pla/30-mapa-trello-repo.md` |
| Cobertura de casos | `documentacio/00-index-i-pla/26-matriu-cobertura-casos.md` |
| Pantalles actuals i canvis pendents | `documentacio/03-canvis-pendents/07-pantalles-intranet.md` |
| Matriu abans/despres de pantalles | `documentacio/03-canvis-pendents/12-matriu-pantalles-abans-despres.md` |
| Estat final de pantalles | `documentacio/04-estat-final/16-estat-final-pantalles.md` |
| Panell SIF | `documentacio/04-estat-final/25-panell-sif-pay-prisma.md` |
| Proves, evidencies i go/no-go | `documentacio/05-governanca-operacio/20-pla-proves-validacio-sif.md` |
| Annex captures | `documentacio/05-governanca-operacio/23-annex-captures-pantalla.md` |
| Permisos i seguretat | `documentacio/05-governanca-operacio/21-seguretat-permisos-accessos.md` |

## 7. Buits i incoherencies detectades

### 7.1. Duplicats forts

- Trello 1 conserva duplicats de mapa de cobertura, especialment en pantalles d'intranet repetides.
- Trello 4 te targetes repetides per patró generic, per exemple `Documentar pantalla actual`, `Definir estat final UI SIF` o `Preparar captures finals` repetides per moltes pantalles.
- Trello 5 te molts duplicats per proves d'esquema, fixtures i proves negatives.

Accio recomanada: no eliminar volum sense revisar, pero convertir duplicats generics en checklists o vincular-los a targetes mare quan no aporten feina independent.

### 7.2. Trello 6 amb descripcions pendents

Trello 6 te 209 targetes obertes sense descripcio i 3 sense etiqueta. Per un tauler que fa de motor SIF, aquestes targetes poden ser perilloses si representen programacio real sense contracte, criteri ni prova associada.

Accio recomanada: prioritzar revisio de targetes sense descripcio dins llistes de programacio, API, serveis i casos d'us.

### 7.3. Equivalencies no literals

Hi ha fluxos del repo que no sempre apareixen literalment al Trello:

- `manual-invoice` equival a `Factura ordinaria A` i a pantalles de `Factures manuals`.
- `manual-group` pot aparèixer com a grup, responsable, participants o factura de grup.
- `claim-payment` apareix com a reclamacio, morositat o cobrament posterior.
- `historical-invoice-migration` apareix com a factura historica no VERI*FACTU o historic no SIF.

Accio recomanada: quan es revisi Trello, no buscar nomes slugs tecnics. Cal buscar nom funcional i deixar equivalencies dins la descripcio de targeta.

### 7.4. Pantalles amb feina generica

El Trello 4 ja te 192 targetes per cada fase de pantalla: preparar, definir, modificar i captura. Aixo es positiu, pero no substitueix les targetes petites de comportament concret quan el risc fiscal es alt.

Pantalles que continuen necessitant targetes concretes i evidencia:

- `Passar pagaments`
- `Generar factura abans de pagar`
- `Consulta / modifica alumne`
- `Consulta/Edita/Anula factura`
- `Analitzar fitxer TPV`
- `Canvi de curs`
- `Baixa`, devolucio i saldo futur
- Intranet alumne, empresa/responsable i factures de grup
- Panell SIF, incidencies, AEAT, documents i exportacions

## 8. Criteri de tancament

Una targeta critica no es pot considerar tancada si falta algun d'aquests elements:

- criteri funcional o fiscal;
- implementacio o decisio explicita de no implementar;
- relacio amb pantalla/canal si afecta usuari;
- prova Trello 5;
- evidencia adjunta o referenciada;
- documentacio repo actualitzada;
- traça d'auditoria si afecta factura, cobrament, AEAT, PDF/QR, permisos o dades fiscals.

## 9. Manteniment

Quan es faci un canvi de Trello o repo, revisar:

- si canvia una decisio: `00-control/registre-decisions.md`;
- si canvia l'estat real del projecte: `00-control/estat-projecte.md`;
- si canvia una pantalla: `documentacio/03-canvis-pendents/12-matriu-pantalles-abans-despres.md`;
- si canvia un flux SIF: `documentacio/00-index-i-pla/29-pla-implementacio-tecnica-sif.md`;
- si canvia una prova o evidencia: `documentacio/05-governanca-operacio/20-pla-proves-validacio-sif.md`;
- si canvia un criteri d'accés o visibilitat: `documentacio/05-governanca-operacio/21-seguretat-permisos-accessos.md`.
