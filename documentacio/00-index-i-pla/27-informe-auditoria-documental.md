# 27 - Informe d'auditoria documental del projecte

> Revisio feta amb criteri d'auditoria interna. L'objectiu es valorar si, amb la informacio ja coneguda, la documentacio actual permetria entendre, validar i defensar el projecte VERI*FACTU de PrisMa davant direccio, un assessor, una auditoria o una inspeccio.

## 1. Conclusio executiva

Amb la informacio coneguda, la documentacio encara no es completa.

La situacio actual es bona com a base de projecte, pero no es suficient com a documentacio final del SIF.

Important:

```text
Quan aquest informe diu "falta documentar", no vol dir necessariament que Meriem no ho hagi explicat.
Vol dir que encara no esta transcrit dins els documents operatius amb el nivell de detall que caldria per auditar-ho sense conversa addicional.
```

Hi ha quatre nivells diferents:

| Nivell | Estat actual | Valoracio auditoria |
| --- | --- | --- |
| Decisions d'arquitectura | Bastant cobert | SIF centralitzat, BD fiscal nova, hash chain, idempotencia, PDF immutable i panell SIF estan ben decidits. |
| Casuistica funcional | Parcial | S'han explicat molts casos i alguns tenen captures o flux explicat, pero no tots estan convertits en procediments documentats. |
| Model de dades | Bastant avancat | Ja hi ha gairebe totes les taules, relacions i responsabilitats principals. Falta tancar SQL definitiu, indexos, camps finals i diccionari complet. |
| Evidencia auditora | Parcial | Hi ha captures actuals, criteris tecnics i payloads base. Falten captures finals del sistema implementat, proves executades, exemples de factures reals i logs reals. |

Resposta curta:

```text
Tenim prou documentacio per continuar dissenyant i implementant.
No tenim encara prou documentacio per signar la versio final del SIF ni per superar una auditoria completa sense explicacions verbals addicionals.
```

## 2. Criteri utilitzat

La revisio s'ha fet preguntant:

1. Una persona externa pot entendre com funciona PrisMa sense que Meriem ho expliqui verbalment?
2. Es pot seguir una factura des de l'origen fins al registre fiscal, PDF, QR, AEAT i logs?
3. Es pot saber qui pot fer cada accio i qui no?
4. Es pot demostrar que una factura emesa no es modifica silenciosament?
5. Es pot demostrar que un pagament duplicat o repetit no crea factura duplicada?
6. Es pot veure clarament que passa amb rectificatives, baixes, canvis de curs, saldos, devolucions i compensacions?
7. Es poden reconstruir els casos de pack, grup, regal, USOC, transferencia i factura abans de cobrament?
8. Es pot saber quins documents del SIF estan disponibles dins `pay.prisma.cat/sif`?
9. Es pot saber quina versio esta activa i quina declaracio responsable li correspon?
10. Es pot provar el sistema amb casos reals abans de produccio?

## 2.1. Respostes amb el disseny del nou SIF

### Es pot seguir una factura des de l'origen fins al registre fiscal, PDF, QR, AEAT i logs?

Amb el nou sistema, si s'implementa tal com esta dissenyat, si.

La traçabilitat hauria de quedar aixi:

```text
origen operatiu
    -> fact_rels
    -> factura
    -> factura_linia
    -> factura_registres
    -> fiscal_queue / estat AEAT
    -> factura_documents
    -> factura_log / event_log / incidencies
```

Exemples d'origen:

- `web.inscripcions.ID`;
- `IDPAG`;
- `Ds_Order`;
- `FACTURA_RELACIONADA`;
- pack;
- grup;
- regal;
- factura manual;
- factura abans de cobrament;
- canvi de curs;
- baixa/devolucio/saldo.

### Es pot demostrar que una factura emesa no es modifica silenciosament?

Amb el nou sistema, si. La demostracio no depen d'un sol camp, sino d'un conjunt de controls:

- snapshot fiscal immutable a `factura`;
- linies fiscals congelades a `factura_linia`;
- documents generats guardats a `factura_documents` amb hash del fitxer;
- prohibicio d'editar factures emeses des de la UI;
- permisos MySQL sense `UPDATE`/`DELETE` sobre factures emeses per usuaris no SIF;
- rectificatives per corregir dades fiscals, imports o conceptes;
- registre d'events/logs quan hi ha accio sobre factura;
- historial de documents i versions;
- rol auditor/AEAT nomes lectura.

Regla auditora:

```text
Una factura emesa no es corregeix.
Es rectifica, es vincula i es deixa rastre.
```

### Es pot demostrar que un pagament duplicat o repetit no crea factura duplicada?

Amb el nou sistema, si.

La garantia principal no es nomes `fiscal_sequence`. Cada part cobreix un risc diferent:

| Mecanisme | Que evita |
| --- | --- |
| `IDEMPOTENCY_KEY UNIQUE` a factura | Que la mateixa operacio facturable generi dues factures. |
| Comprovacio previa del SIF per idempotencia | Que un retry retorni factura nova en comptes de la ja creada. |
| `payment_transaction` amb referencia externa unica quan correspongui | Que el mateix cobrament Redsys/transferencia es registri dues vegades. |
| `fiscal_sequence` amb `FOR UPDATE` | Que dues factures diferents rebin el mateix numero fiscal. |
| `fact_rels` | Que es pugui veure si una inscripcio/pagament ja te factura fiscal vinculada. |
| Logs i incidencies | Que un duplicat detectat quedi registrat i revisable. |

Per tant:

```text
idempotencia evita duplicats
fiscal_sequence evita col·lisions de numeracio
fact_rels permet rastrejar relacions
```

### Que vol dir reconstruir els casos de pack, grup, regal, USOC, transferencia i factura abans de cobrament?

Vol dir que una persona externa pugui agafar una factura i reconstruir-ne el cami sense preguntar-ho a Meriem:

```text
qui va iniciar l'operacio
quin origen tenia
quin pagament o document la va provocar
quin receptor fiscal tenia
quines linies la formen
quin descompte o ajust s'ha aplicat
quin pagament la cobra
quin registre fiscal genera
quin PDF/QR/XML li correspon
quin estat AEAT te
quines incidencies o rectificatives hi ha vinculades
```

Aixo s'ha de poder fer per cada cas especial:

- pack: dues inscripcions, una factura, diverses linies;
- grup: N participants, una factura a empresa/responsable, visibilitat restringida;
- regal: factura al comprador, codi regal, inscripcio posterior del destinatari sense factura nova;
- USOC: factura alumne per la seva part i factura USOC per la diferencia;
- transferencia: factura existent o nova factura segons cas, cobrament validat a intranet;
- factura abans de cobrament: factura real emesa, cobrament posterior amb `registerPayment()`.

### Quins documents del SIF han d'estar disponibles dins `pay.prisma.cat/sif`?

Com a minim:

- declaracio responsable signada de la versio activa;
- versio activa del SIF;
- historial de versions;
- documentacio tecnica del SIF;
- diccionari de camps i valors;
- documentacio de permisos i rols;
- PDFs de factures;
- XML/registres fiscals quan correspongui;
- QR;
- exportacions fiscals;
- registre d'incidencies;
- registre d'events/logs;
- evidencies de proves o validacions internes quan calgui.

### Es pot saber quina versio esta activa i quina declaracio responsable li correspon?

Ha de quedar dins l'apartat de documentacio del SIF a `pay.prisma.cat/sif`.

Regla:

```text
cada versio activa del SIF
    -> te una declaracio responsable associada
    -> te data d'entrada en produccio
    -> te responsable tecnica
    -> te responsable legal/direccio
    -> te resum de canvis
```

### Es pot provar el sistema amb casos reals abans de produccio?

Si. S'ha de fer amb escenari de proves/preproduccio.

Opcions:

- entorn `pay.prisma.cat` no productiu o mode proves;
- BD fiscal de proves separada;
- credencials/certificat d'entorn de proves si correspon;
- Redsys en mode test si s'utilitza;
- casos reals anonimitzats o duplicats controlats;
- factures de prova marcades clarament com no productives;
- proves de callbacks, retries, concurrencia, PDF, QR, exportacions i permisos.

Les proves no han de contaminar la numeracio fiscal productiva.

## 2.2. Lectura correcta de "falta"

En aquest informe, "falta" no sempre vol dir que el tema sigui desconegut.

Pot voler dir tres coses diferents:

- falta implementar-ho en codi o BD;
- falta executar-ho i conservar evidencia real;
- falta transcriure-ho de forma auditable dins els documents, encara que al xat antic ja s'hagi explicat.

Exemple important:

```text
Les captures actuals de Consulta - Modifica alumne, dades del curs, dades de pagament, canvi, baixa i veure factura ja existeixen o ja s'han comentat.
El que falta son les captures finals post-VERI*FACTU, amb versio, data, pantalla final i evidencia de permisos.
```

## 3. Que ja esta prou ben decidit

Aquests punts estan suficientment definits a nivell conceptual:

- SIF centralitzat a `pay.prisma.cat`.
- Panell intern `pay.prisma.cat/sif` com a font oficial.
- Intranet principal amb apartat `VERI*FACTU` i indicador visual.
- Canals clients del SIF: ecommerce/web, Redsys/TPV i intranet.
- Dues series fiscals: `A` ordinaria i `R` rectificativa.
- No hi ha series separades per canal.
- Hash chain global unica.
- Numeracio i hash dins transaccio.
- InnoDB, bloquejos i concurrencia.
- Idempotencia obligatoria.
- Separacio entre factura i pagament.
- Separacio entre `issueInvoice()` i `registerPayment()`.
- `FACTURA_RELACIONADA` com agrupador de compatibilitat.
- `fact_rels` com relacio nova amb `UUID_FACTURA`.
- `E_FACT` separat de `EMESA_ABANS_COBRAMENT`.
- Imports nous amb `DECIMAL(12,2)`.
- PDF/QR immutable, guardat i amb hash.
- Factures historiques migrades com a historic no VERI*FACTU.
- Proformes: no es consideren factures fiscals si no tenen numeracio fiscal.
- Control d'immutabilitat: dissenyat amb snapshot fiscal, permisos, rectificatives, logs i documents amb hash.
- Control de duplicats: dissenyat amb idempotencia, claus uniques i separacio entre factura i pagament.

## 4. Punts que un auditor marcaria com a incomplets

### 4.1. Procediments funcionals per pantalla

Hi ha captures i explicacio funcional de les pantalles principals de `Consulta - Modifica alumne`. No falten com a coneixement del sistema actual. El que falta es:

- guardar-les a l'annex de captures amb nom, data i descripcio;
- indicar quin canvi VERI*FACTU rebra cada pantalla;
- afegir captures finals quan la implementacio estigui feta.

Pantalles actuals ja tractades amb captura o explicacio:

- `Consulta - Modifica alumne`.
- Dades del curs.
- Dades de pagament.
- Canvi de curs.
- Baixa.
- Veure factura.

Pantalles/apartats encara pendents de documentar amb el mateix nivell:

- `Passar pagaments`.
- `Generar factura abans de pagar`.
- `Consulta - Edita - Anula factura`.
- `Analitzar fitxer TPV`.
- Intranet personalitzada de l'alumne.
- Canal de consulta o enviament per empresa/responsable, sense acces a la intranet principal.
- Apartat `VERI*FACTU` de la intranet.
- Panell `pay.prisma.cat/sif`.

Per cada apartat hauria de constar:

- objectiu;
- qui hi pot accedir;
- quines taules llegeix;
- quines taules escriu;
- quines accions fiscals pot desencadenar;
- quins errors/incidencies pot generar;
- quins correus envia;
- quines restriccions te quan ja existeix factura emesa.

### 4.2. Fluxos fiscals per cas

Els casos estan identificats i molts fluxos ja s'han explicat verbalment, amb codi o amb captures. El que falta no es tornar a descobrir-los, sino consolidar-los dins el document de fluxos amb una fitxa auditora per cas:

- curs normal Redsys, ja explicat amb `realitzaPagamentAutomatic.php`;
- taller;
- jornada;
- pack, ja explicat funcionalment;
- grup de persones;
- regal, ja explicat funcionalment;
- USOC;
- empresa/responsable;
- transferencia;
- compensacio;
- saldo;
- fraccionat;
- morositat;
- devolucio parcial;
- devolucio total;
- canvi de dades fiscals;
- canvi de curs amb mateix import;
- canvi de curs amb import superior;
- canvi de curs amb import inferior;
- factura manual;
- factura abans de cobrament;
- rectificativa per substitucio;
- rectificativa negativa.

Per cada cas cal deixar escrit en el document final:

- identificador unic de negoci;
- `IDEMPOTENCY_KEY`;
- receptor de factura;
- pagador;
- origen;
- linies de factura;
- descompte aplicat;
- import base;
- import final;
- estat de cobrament;
- visibilitat a intranet personalitzada de l'alumne i canal empresa/responsable;
- correus;
- rectificativa si hi ha canvi posterior.

### 4.3. Model de dades

El mapa actual i `05-model-bd-sif.md` ja identifiquen gairebe totes les taules principals, moltes relacions i la responsabilitat funcional de cada grup de taules. El que encara no es suficient per auditoria final es el SQL definitiu, el diccionari complet de camps, indexos, claus uniques i la relacio formal amb permisos i processos.

Falta un diccionari detallat de:

- totes les taules noves del SIF;
- totes les taules noves de la intranet;
- camps obligatoris i opcionals;
- tipus de dada definitiu;
- claus uniques;
- indexs;
- relacions;
- valors permesos dels estats;
- qui pot inserir;
- qui pot actualitzar;
- que no es pot actualitzar mai;
- dades migrades;
- dades historiques no VERI*FACTU.

Taules ja identificades que han de quedar tancades amb especial detall:

- `factura`;
- `factura_linia`;
- `factura_registres`;
- `factura_rectificacio`;
- `factura_documents`;
- `fiscal_sequence`;
- `fiscal_chain_state`;
- `fiscal_queue`;
- `payment_transaction`;
- `payment_allocation`;
- `fact_rels`;
- `notificacions`;
- `motiu_canvi`;
- `canvi_curs`;
- `baixa_inscripcio`;
- `reclamacio_pagament`;
- `credit_balance`;
- taules de packs;
- taules de regals;
- taules de codis promocionals.

### 4.4. Permisos i segregacio d'accions

La matriu inicial de permisos queda documentada a `../05-governanca-operacio/21-seguretat-permisos-accessos.md`.

Rols interns documentats:

- Meriem / responsable funcional i tecnica SIF;
- Adam / direccio i moviments de facturacio;
- Pablo / gestio-secretaria;
- Isa / suport relacionat amb Moodle i suport a Secretaria;
- auditor/AEAT nomes lectura;
- proces automatic SIF.

Actors externs documentats:

- alumne amb intranet personalitzada, no rol de la intranet principal;
- empresa/responsable sense acces a la intranet principal.

El que queda pendent no es decidir aquests rols, sino aplicar-los en codi, BD i pantalles.

Regla:

```text
Alumne i empresa/responsable no son rols de la intranet principal.
El proces automatic SIF no es una persona.
```

### 4.5. Correus i plantilles

La documentacio de correus ja te un primer inventari de la classe `Template`, placeholders, plantilles de reclamacio final, control de morosos i classes d'enviament.

Encara cal completar el mapa operatiu de cada correu.

Cal documentar:

- pagament acceptat;
- pagament denegat;
- factura abans de cobrament;
- factura manual;
- factura electronica;
- rectificativa;
- devolucio;
- baixa;
- canvi de curs;
- saldo;
- morositat;
- reclamacions;
- factura empresa/grup;
- factura USOC;
- regal;
- pack;
- error fiscal o incidencia si cal.

Per cada correu:

- destinatari;
- copia interna;
- condicions d'enviament;
- si porta PDF adjunt o enllac segur;
- plantilla;
- variables;
- log d'enviament;
- que passa si falla l'enviament.

Criteri decidit:

- no es migraran tots els correus antics de cop;
- quan un apartat s'hagi de reprogramar de manera important per VERI*FACTU, s'aprofitara per convertir els correus construits directament dins PHP a plantilles `Template`;
- els correus nous del SIF s'haurien de crear ja com a plantilles.

### 4.6. Proves i evidencies

El pla de proves existeix com a document i, despres del bloc 8 del xat pont, ja incorpora criteris de preproduccio, regressions critiques i paquet go/no-go.

Tot i aixi, encara ha de convertir-se en casos executables amb evidencia real.

Cal tenir proves per:

- callback Redsys duplicat;
- retry SIF;
- retry AEAT;
- tall de connexio;
- pagament cobrat sense factura;
- factura abans de cobrar posteriorment pagada;
- dues peticions simultanies;
- numeracio concurrent;
- hash chain;
- PDF generat i hash valid;
- factura de grup no visible a alumne;
- canvi de dades fiscals amb rectificativa;
- canvi de curs;
- baixa amb devolucio;
- baixa amb saldo;
- codi promocional;
- descompte sensible amb text generic;
- exportacio fiscal;
- usuari sense permisos intentant editar factura emesa.

### 4.7. Evidencia dins del SIF

El panell SIF esta ben plantejat, pero falta definir com es veura i com es conservara:

- versio activa;
- declaracio responsable signada;
- documentacio tecnica;
- diccionari de camps;
- permisos;
- exportacions;
- registre d'accessos;
- registre d'incidencies;
- historial de canvis de versio;
- fitxers PDF/XML/QR amb hash;
- logs de processos automatics del SIF.

### 4.8. Planificacio i capacitat interna

El xat antic va deixar una estimacio interna rellevant per governanca, no per compliment AEAT.

Lectura prudent:

```text
VERI*FACTU PrisMa no es nomes afegir un QR.
Es arquitectura fiscal, migracio, refactor de pantalles destructives, integracio Redsys/AEAT, proves, regressions, documentacio i operacio.
```

Estimacio orientativa recuperada:

| Bloc de treball | Rang aproximat |
| --- | --- |
| Arquitectura fiscal base: ledger, hash, cua, idempotencia, retries | 3-5 setmanes |
| Integracio real VERI*FACTU / AEAT | 2-4 setmanes |
| Refactor d'accions que avui modifiquen dades fiscals | 1-2 mesos |
| UI administrativa i panell SIF | 1 mes |
| Correus, PDF, QR i exportacions | 2-3 setmanes |
| QA, regressions i proves | 1-2 mesos en paral·lel |
| Migracio i desplegament a `pay.prisma.cat` | 1-3 setmanes |

Conclusio interna:

```text
4 a 8 mesos reals es una estimacio raonable.
6 mesos es un escenari plausible si es treballa de forma sostinguda.
```

Restriccions de capacitat:

- Meriem concentra desenvolupament, arquitectura fiscal, documentacio i decisions operatives del SIF;
- el projecte no s'ha de planificar com si hi hagues un equip complet dedicat;
- suport extern pot ajudar en Moodle, incidencies, HTML/PHP/JS senzill o tasques acotades, pero no s'hauria de comptar com a responsable de facturacio fiscal, Redsys, AEAT o arquitectura SIF;
- si el ritme real es dilluns, dimecres i dijous, el dijous hauria de reservar-se a tancament VERI*FACTU: QA, estabilitzacio, revisio i acabats, no reunions disperses.

Aquesta informacio no s'ha d'incloure a la declaracio responsable. Serveix per prioritzar, justificar terminis i evitar promeses internes irreals.

## 5. Riscos auditors actuals

| Risc | Severitat | Motiu |
| --- | --- | --- |
| Factures actuals editables o PDFs regenerats des de BD viva | Alta | Es risc de l'estat actual. El nou SIF ho resol amb PDF immutable, hash, permisos i rectificatives. |
| Callbacks Redsys duplicats o retries sense idempotencia real | Alta | Es risc de l'estat actual. El nou SIF ho resol amb `IDEMPOTENCY_KEY`, claus uniques i retorn de factura existent. |
| Factura abans de cobrament confosa amb factura electronica | Alta | `E_FACT` i `EMESA_ABANS_COBRAMENT` han d'estar separats. |
| Pagaments registrats a inscripcions sense model fiscal clar | Alta | Cal separar pagament, factura i assignacio. |
| Descomptes/codis promocionals sense snapshot fiscal | Mitjana/Alta | Cal poder justificar import final i descompte aplicat. |
| Canvis de curs modificant `A_PAGAR` sense rastre suficient | Alta | Amb factura emesa cal event i possible rectificativa. |
| Baixes/saldos/devolucions sense criteri fiscal documentat | Alta | Pot afectar rectificatives i cobraments. |
| Factures de grup visibles a alumnes | Alta | Risc de privacitat i acces indegut. |
| Permisos MySQL/app encara no implementats al detall | Alta | Risc de modificacio directa o no controlada si nomes queda documentat i no aplicat. |
| Certificat digital AEAT no confirmat | Alta | Bloqueig per proves/enviament AEAT. |
| Captures finals i proves executades encara no disponibles | Mitjana | Les captures actuals existeixen per diverses pantalles; faltaran captures finals i evidencia de proves quan s'implementi. |

## 6. Que es pot defensar avui

Avui es pot defensar:

- que el projecte esta identificat i en curs;
- que s'ha decidit centralitzar el SIF;
- que es coneixen els riscos principals del sistema actual;
- que s'ha separat l'estat actual, canvis pendents, estat final i governanca;
- que s'ha previst declaracio responsable;
- que s'ha previst panell SIF amb documentacio i incidencies;
- que s'ha previst bloquejar edicions directes;
- que s'ha previst idempotencia, hash chain, PDF immutable i logs.
- que ja hi ha captures i explicacio de les pantalles principals de consulta/modificacio d'alumne.
- que el model de dades del SIF esta bastant avançat, encara que no tancat en SQL final.

No es pot defensar encara com a final:

- que el SIF ja compleix completament;
- que tots els casos estan documentats de punta a punta en format auditable;
- que la declaracio responsable definitiva es signable;
- que els permisos estan tancats;
- que els procediments interns estan preparats per usuaris;
- que les proves estan executades;
- que la documentacio dins del SIF esta disponible, perque encara s'ha d'implementar a `pay.prisma.cat/sif`.

## 7. Documents que cal completar amb prioritat

Prioritat 1:

1. `03-canvis-pendents/10-procediments-intranet-ecommerce.md`
2. `03-canvis-pendents/04-fluxos-facturacio.md`
3. `04-estat-final/16-estat-final-pantalles.md`
4. `04-estat-final/17-estat-final-bd-relacions.md`
5. `05-governanca-operacio/24-diccionari-camps-i-valors.md`
6. `05-governanca-operacio/21-seguretat-permisos-accessos.md`

Prioritat 2:

1. `03-canvis-pendents/08-correus-i-plantilles.md`
2. `05-governanca-operacio/20-pla-proves-validacio-sif.md`
3. `05-governanca-operacio/22-manual-operatiu-intern.md`
4. `05-governanca-operacio/23-annex-captures-pantalla.md`
5. `04-estat-final/18-estat-final-operacio-incidencies.md`

Prioritat 3:

1. `01-compliment-aeat/documentacio-sif-aeat.md`
2. `01-compliment-aeat/declaracio-responsable-sif-prisma.md`
3. `05-governanca-operacio/19-registre-versions-i-canvis-sif.md`

## 8. Recomanacio auditoria

No convindria continuar generant documents nous sense omplir els documents clau amb detall operatiu.

Cal diferenciar tres estats:

```text
1. Explicat per Meriem.
2. Documentat en esborrany.
3. Documentat, validat i auditable.
```

Molts punts del projecte ja estan en l'estat 1 o 2. L'objectiu ara es passar-los a l'estat 3.

El pas seguent recomanat es fer una revisio per apartat real de la intranet i ecommerce, en aquest ordre:

1. `Consulta - Modifica alumne`
2. `Passar pagaments`
3. `Generar factura abans de pagar`
4. `Consulta - Edita - Anula factura`
5. `Analitzar fitxer TPV`
6. Redsys curs normal
7. Packs
8. Grups
9. Regals
10. USOC
11. Codis promocionals
12. Intranet personalitzada de l'alumne

Per cada apartat s'hauria de tancar:

- funcionament actual;
- problema VERI*FACTU;
- funcionament final;
- taules afectades;
- endpoints SIF;
- permisos;
- errors/incidencies;
- correus;
- proves.

## 8.1. Criteri per passar a xats especialitzats

Amb el bloc 8 revisat, el xat pont pot considerar completada la primera recuperacio transversal del xat antic.

El pas seguent no hauria de ser continuar ampliant el xat pont indefinidament, sino obrir xats especialitzats per convertir documentacio parcial en procediments executables.

Ordre recomanat:

1. Xat especialitzat de pantalles i procediments reals d'intranet/ecommerce.
2. Xat especialitzat de BD/SQL i migracio fiscal.
3. Xat especialitzat de Redsys, `pay.prisma.cat` i conciliacio.
4. Xat especialitzat de proves, preproduccio i paquet go/no-go.

Cada xat especialitzat hauria de llegir `00-control/mapa-xats.md`, aquest informe, la matriu de cobertura i els documents del seu apartat.

## 8.2. Primer subbloc especialitzat de pantalles

El primer subbloc especialitzat iniciat es:

```text
Xat 5 / Intranet, pantalles i operacio interna
Subbloc: Consulta - Modifica alumne
```

Resultat documental:

- la pantalla ja esta identificada i no cal recuperar-la de zero;
- el mapa de les cinc icones queda consolidat;
- es diferencia consulta, accio administrativa i accio fiscal;
- veure factura queda com a nomes lectura;
- canvi de curs i baixa queden com a fluxos que poden derivar a rectificativa, pagament, saldo o retorn;
- dades personals i observacions no modifiquen factures emeses;
- dades de pagament queda com a zona de risc que no pot continuar sent editor lliure de camps fiscals.

Pendent:

- revisar els cossos reals dels metodes de pagament, canvi de curs, baixa i visualitzacio de factura;
- implementar pantalla final;
- afegir captures finals;
- executar proves del subbloc.

Subbloc seguent revisat a continuacio:

```text
Passar pagaments / Analitzar fitxer TPV
```

## 8.3. Segon subbloc especialitzat de pantalles

El segon subbloc especialitzat revisat es:

```text
Xat 5 / Intranet, pantalles i operacio interna
Subbloc: Passar pagaments / Analitzar fitxer TPV
```

Resultat documental:

- el bloc TPV i el bloc de cerca/pagament queden separats funcionalment;
- la cerca queda limitada a un sol criteri: `NIF/NIE`, `CODI REGAL` o `NUM FACTURA`;
- el selector `ALUMNE / GRUP` queda documentat com a `tipusInsc = I/G`;
- els estats JSON de TPV (`1`, `2`, `0`) queden mapats a resultat, incidencia o error;
- `efectuarPagament()` queda identificat com a punt que ha de migrar a `registerPayment()` i, si cal, `issueInvoice()`;
- `mostrarModalConfPag()` queda marcat com a text/comportament antic: no s'ha d'actualitzar factura emesa, sino registrar cobrament;
- `efact` queda marcat com a nom ambigu que no s'ha de confondre amb `E_FACT`;
- es defineixen proves de duplicat, factura abans de cobrament, TPV reprocessat, devolucio i permisos.

Pendent:

- revisar cossos reals de `buscarInfomacioPagament.php` i `mostrarModalInfoPag.php`;
- implementar API/endpoint SIF amb `POST`, permisos, validacio i idempotencia;
- substituir `fitxers/analisis-fitxer.txt` com a unica evidencia per un registre auditable;
- executar proves i afegir captures finals.

Subbloc seguent revisat a continuacio:

```text
Generar factura abans de pagar
```

## 8.4. Tercer subbloc especialitzat de pantalles

El tercer subbloc especialitzat revisat es:

```text
Xat 5 / Intranet, pantalles i operacio interna
Subbloc: Generar factura abans de pagar
```

Resultat documental:

- el flux queda definit com a emissio fiscal real abans del cobrament, no com a proforma;
- `generarFacturaElectronica_Alumnes()` queda interpretat com a factura abans de cobrament, no com a marca automatica `E_FACT`;
- la marca final es `EMESA_ABANS_COBRAMENT = 1` i `E_FACT = 0` per defecte;
- es documenten riscos concrets del JS: `idsInsc`, entitat com a text visible, `concepte2` asíncron, comprovacions d'error fragils i PDF regenerat;
- la seleccio d'inscripcions ha de ser validada al servidor per curs, edicio, import, receptor i factura previa;
- el PDF/QR final ha de sortir de `factura_documents`;
- el pagament posterior ha de passar per `registerPayment()` contra la factura existent.

Pendent:

- implementar `issueInvoice()` amb idempotencia i snapshot fiscal del receptor;
- bloquejar o substituir URLs individuals de pagament quan la factura es d'empresa/responsable;
- revisar/corregir el JS final i els wrappers AJAX;
- executar proves i afegir captures finals.

Subbloc seguent revisat a continuacio:

```text
Consulta - Edita - Anula factura
```

## 8.5. Quart subbloc especialitzat de pantalles

El quart subbloc especialitzat revisat es:

```text
Xat 5 / Intranet, pantalles i operacio interna
Subbloc: Consulta - Edita - Anula factura
```

Resultat documental:

- la pantalla queda definida com a centre de control de factures ja emeses, no com a editor directe;
- `guardarDadesFactura_Factures()` i `updDadesFact` queden identificats com a risc principal per a factures SIF;
- `anularFactura()` queda interpretat com a flux historic que crea factura `R` negativa amb numeracio local i actualitza resums d'inscripcio;
- el SIF final ha de substituir l'anul·lacio historica per rectificativa vinculada, estat AEAT, PDF/QR immutable i registre de devolucio, saldo o compensacio;
- el cas de factura relacionada amb diverses inscripcions queda recuperat com a risc especific: les assignacions s'han de mostrar i guardar abans de confirmar;
- `E_FACT` queda confirmat com a accio administrativa separada amb permisos Meriem, Adam i Pablo.

Pendent:

- implementar accions SIF amb `POST`, idempotencia i validacio servidor;
- tancar cataleg de motius i correspondencia amb tipus de rectificativa;
- bloquejar `updDadesFact` per a factures SIF emeses;
- substituir updates manuals sobre `PAGAMENT` i `DATA PAG` per pagaments, assignacions, devolucions i saldos;
- executar proves finals i afegir captures.

Subbloc seguent revisat a continuacio:

```text
Intranet alumne, empresa/responsable i acces VERI*FACTU
```

## 8.6. Cinque subbloc especialitzat de pantalles

El cinque subbloc especialitzat revisat es:

```text
Xat 5 / Intranet, pantalles i operacio interna
Subbloc: Intranet alumne, empresa/responsable i acces VERI*FACTU
```

Resultat documental:

- la intranet de l'alumne queda definida com a consulta externa limitada, no com a rol de la intranet principal;
- l'alumne pot veure factures individuals propies, pero no factures completes d'empresa/grup si no n'es receptor fiscal;
- l'empresa o responsable no entra a la intranet principal: consulta per correu, enllac segur, gestio interna o espai futur especific;
- el PDF/QR s'ha de servir des de document SIF o `factura_documents`, guardat en espai no public de `pay.prisma.cat`, sense exposar paths interns;
- l'apartat `VERI*FACTU` de la intranet principal queda com a indicador/resum i porta d'entrada al panell SIF, no com a lloc de resolucio oficial;
- la frase recuperada del xat antic queda incorporada com a criteri de privacitat: factura d'alumne visible per alumne; factura empresa/grup visible nomes per empresa/responsable.

Pendent:

- implementar consulta de factures visibles per alumne;
- implementar enllac segur per empresa/responsable amb token, caducitat/revocacio si es defineix i validacio de receptor;
- implementar endpoint de document que no exposi ruta interna;
- provar participants de grup, factures d'empresa, PDF pendent i tokens invalids;
- afegir captures finals.

Bloc seguent revisat a continuacio:

```text
Redsys curs normal
```

## 8.7. Sise bloc especialitzat

El sise bloc especialitzat revisat es:

```text
Xat 4 / Pagaments, Redsys i pay.prisma.cat
Bloc: Redsys curs normal
```

Resultat documental:

- `realitzaPagamentAutomatic.php` queda identificat com el callback historic que barreja notificacio Redsys, pagament, emissio fiscal, updates d'inscripcio i correus;
- el xat antic recupera el punt critic: el bloc `Generem la factura` calcula `factura_relacionada`, `ANY`, `ORDRE`, `NUM`, insereix a `web.factures` i actualitza `web.inscripcions`;
- el flux final substitueix `SELECT ordre FROM factures... INSERT INTO factures...` per la decisio `issueInvoice()` o `registerPayment()`;
- `Ds_Order` passa a ser clau de deduplicacio de callback dins `redsys_notifications`;
- `IDPAG` no es clau unica de factura, perque pot tenir diversos intents, fraccionaments o pagament denegat i despres acceptat;
- la signatura Redsys i l'import signat `Ds_Amount` han de validar-se abans de tocar BD fiscal o operativa;
- la sincronitzacio de `PAGAMENT`, `DATA PAG`, `FACTURA_RELACIONADA` i `FRACCIO` queda subordinada a la resposta del SIF.

Pendent:

- implementar `redsys_notifications` i l'endpoint final a `pay.prisma.cat`;
- substituir la generacio local de numero i `INSERT INTO factures`;
- implementar la branca `issueInvoice()` per curs normal i `registerPayment()` quan ja hi ha factura real;
- provar duplicats, signatures invalides, imports divergents, fraccionaments i PDF/QR;
- confirmar payload final en codi real.

Bloc seguent revisat a continuacio:

```text
Packs
```

## 8.8. Sete bloc especialitzat

El sete bloc especialitzat revisat es:

```text
Xat 4 / Pagaments, Redsys i pay.prisma.cat
Bloc: Packs
```

Resultat documental:

- el pack normal queda fixat com 2 cursos, 2 inscripcions i mateix `IDPAG`;
- la regla final es una factura per pagament real, amb una linia per curs;
- el descompte de pack del 25% s'aplica a la linia del segon curs amb `DESC_ORIGEN = PACK`;
- cada linia apunta a la seva `inscripcions.ID` amb `SOURCE_TYPE = INSCRIPCIO`;
- la idempotencia del callback no es fa per `inscripcions.ID`, sino per `REDSYS|PACK|IDPAG:{IDPAG}|ORDER:{DS_ORDER}`;
- `buscarPagamentsPack` agrupa per `IDPAG` i `buscarInfoPack` consulta `info_pack`, cosa que confirma el punt operatiu que cal migrar a snapshot fiscal;
- si hi ha fraccionament excepcional des d'intranet, es genera una factura per cada pagament real;
- ecommerce no ha de permetre que el client divideixi el pack en diverses factures.

Pendent:

- incorporar SQL real de taules de pack/preus i relacio exacta amb `info_pack`;
- implementar payload final de pack a `issueInvoice()`;
- provar pack d'un sol pagament, callback duplicat, fraccionament excepcional i PDF/QR amb dues linies;
- afegir captures finals i exemple real de preproduccio.

Bloc seguent revisat a continuacio:

```text
Grups
```

## 8.9. Vuite bloc especialitzat

El vuite bloc especialitzat revisat es:

```text
Xat 4 / Pagaments, Redsys i pay.prisma.cat
Bloc: Grups
```

Resultat documental:

- el grup queda fixat com N participants amb una fila a `inscripcions` per participant;
- la regla final es una factura per pagament real, amb una linia per participant;
- el receptor fiscal pot ser escola/empresa o responsable particular, segons l'origen del grup;
- el preu/descompte per participant surt de `descomptes_grup`;
- `TIPUS_INSC = G` identifica inscripcions de grup;
- `respGrups` relaciona responsable i `IDPAG`;
- `buscarPersRespGrup2`, `buscarPersGrup`, `buscarPagamentsGrup`, `searchMembresGrup` i `searchMembresGrup2` queden identificades com a consultes operatives a migrar;
- el nom del participant pot sortir a la linia, especialment per justificacio FUNDAE/Tripartita;
- el DNI del participant no s'imprimeix per defecte: queda intern o en annex si cal justificacio forta;
- els participants no poden veure la factura completa del grup si conte altres persones.

Pendent:

- implementar payload final de grup a `issueInvoice()` i `registerPayment()`;
- validar SQL final de `descomptes_grup`, `respGrups` i relacions amb `inscripcions`;
- provar receptor empresa/responsable, callback duplicat, pagament parcial, factura abans de cobrament i privacitat de participants;
- afegir captures finals i exemple real de preproduccio.

Bloc seguent revisat a continuacio:

```text
Regals
```

## 8.10. Nove bloc especialitzat

El nove bloc especialitzat revisat es:

```text
Xat 4 / Pagaments, Redsys i pay.prisma.cat
Bloc: Regals
```

Resultat documental:

- el regal queda fixat com venda facturada al comprador, no al futur alumne;
- en el moment de compra encara no hi ha inscripcio definitiva del destinatari;
- el comprador tria curs, pot posar dedicatoria, introdueix dades de facturacio i rep un codi regal;
- `SOURCE_TYPE = REGAL` i `SOURCE_ID = regal.ID` identifiquen la linia fiscal;
- quan el destinatari bescanvia el codi, es crea o vincula la inscripcio sense factura nova;
- `buscarRegNoPayByCodi`, `buscarRegNoPayByDni`, `buscarRegalById` i `updFactRegal` queden identificades com a consultes/updates operatius a migrar;
- `FACT_REL` historica no substitueix `UUID_FACTURA` ni `fact_rels`, pero serveix com a compatibilitat;
- la targeta regal PDF es document comercial; la factura fiscal immutable surt de `factura_documents`;
- el correu historic envia codi regal i enllaç a targeta regal, i indica validesa d'un any.

Pendent:

- implementar payload final de regal a `issueInvoice()` i idempotencia Redsys/manual;
- validar SQL final de `regal`, `FACT_REL`, `ORIGEN`, `DESTI`, `CODI` i relacio amb inscripcio posterior;
- provar compra, callback duplicat, cerca per codi, targeta regal, bescanvi i no emissio de segona factura;
- afegir captures finals i exemple real de preproduccio.

Bloc seguent revisat a continuacio:

```text
USOC
```

## 8.11. Dese bloc especialitzat

El dese bloc especialitzat revisat es:

```text
Xat 4 / Pagaments, Redsys i pay.prisma.cat
Bloc: USOC
```

Resultat documental:

- USOC queda fixat com a cas de dos pagadors/receptors reals: factura alumne per la seva part i factura USOC per la diferencia;
- el canal historic es `curs afiliat d'USOC`;
- `TIPUS_DESC = 4` identifica `Afiliat USOC`;
- `VALID_DESC` diferencia pendent, validat valid i validat no valid;
- el descompte recuperat es del 25% i la validacio es manual a intranet despres de confirmar afiliacio amb USOC;
- el cas habitual recuperat indica pagament inicial de l'alumne de 10 euros i pagament posterior de la diferencia per USOC;
- el concepte historic podia indicar que el pagament de la diferencia el realitza l'entitat USOC;
- `cnsAlumnDescNoValidat`, `__mostrarPage_Inici_ValidarDescomptes`, `__mostrarPage_Alumnes_ValidarDescomptes`, `updValidDescByInsc` i `updValidDescByInscPreu` queden identificats com a punts operatius a migrar/controlar;
- el cas especial `Altres: Curs gratüit USOC` i el parametre `anticipi-preu-usoc` queden marcats per decisio final;
- la factura USOC es factura ordinaria separada, no rectificativa ni linia informal de la factura alumne.

Pendent:

- implementar la validacio USOC i el bloqueig/recalcul sense descompte si `VALID_DESC` no es valid;
- confirmar dades fiscals completes de l'entitat USOC per snapshot de factura;
- implementar relacio interna entre factura alumne, factura USOC i `inscripcions.ID`;
- provar callbacks duplicats, denegacio d'afiliacio, cas gratuït/anticipi i privacitat de dades fiscals USOC;
- afegir captures finals i exemple real de preproduccio.

Bloc seguent revisat a continuacio:

```text
Codis promocionals
```

## 8.12. Onzè bloc especialitzat

L'onzè bloc especialitzat revisat es:

```text
Xat 4 / Pagaments, Redsys i pay.prisma.cat
Bloc: Codis promocionals
```

Resultat documental:

- els codis promocionals queden documentats com a logica operativa d'ecommerce/intranet, no com a canal fiscal propi;
- `descomptes.TIPUS` 11-99 identifica promocions temporals;
- el formulari d'inscripcio pot tenir camp `Codi promocional`;
- `promocions` conserva `CODI_DESCOMPTE`, `DNI`, `MES`, `CURS`, `PERCENTATGE`, `USED`, `DATAI` i `DATAF`;
- `cnsSiTePromocioDispo` valida patró de codi, DNI, `USED = 0` i vigencia activa;
- `updDataFPromocio` pot tancar vigencia amb `DATAF = CURRENT_TIME`;
- `MACABODETITULAR#...` queda com a exemple de codi personal, intransferible, d'un sol us i valid fins a una data;
- el SIF no valida si el codi es valid, caducat o usat; rep el resultat final i el congela dins `factura_linia`;
- si el codi caduca o queda usat despres d'emetre, la factura/PDF/QR no canvia;
- el text visible recomanat es generic, per exemple `Descompte promocional aplicat`.

Pendent:

- implementar snapshot pre-Redsys de codi, import/percentatge i total final;
- confirmar SQL final de `promocions` i relacio amb `descomptes`;
- decidir si el codi concret es mostra al PDF o nomes queda com a dada interna;
- provar codi valid, caducat, usat, d'un altre DNI, callback duplicat i canvi de curs posterior;
- afegir captures finals i exemple real de preproduccio.

## 8.13. Dotze bloc especialitzat

Transferencia validada a intranet

Bloc: Transferencia validada a intranet

Documents revisats/actualitzats:

- `03-canvis-pendents/10-procediments-intranet-ecommerce.md`
- `03-canvis-pendents/07-pantalles-intranet.md`
- `03-canvis-pendents/06-integracio-redsys-pay-prisma.md`
- `03-canvis-pendents/04-fluxos-facturacio.md`
- `04-estat-final/05-model-bd-sif.md`
- `05-governanca-operacio/20-pla-proves-validacio-sif.md`

Resultat:

- el xat antic confirma que una transferencia es valida des de `Passar pagaments` i des d'aqui s'ha de cridar el SIF;
- `efectuarPagament.php` rep `id`, `tipus`, `pagament`, `dataPag`, `banc`, `obs`, `numFact` i `efact`;
- `efectuarPagament()` diferencia `efact == 0` (crear factura historica nova per `R/G/P/I`) i `efact != 0` (factura ja generada);
- `mostrarModalConfPag()` carrega `web.factures`, busca entitat/responsable per CIF i avisa historicament que s'actualitzara la factura;
- `efectuarPagamentFacturaGenerada()` usa `buscarPagamentsByFact`, `updFactGenerada`, `searchMembresFactRel`, `updPayInscr`, `updDateInscr` i `updFraccBDByFact`;
- el comportament historic `updFactGenerada` queda substituit per `payment_transaction` i `payment_allocation`;
- una factura VERI*FACTU emesa no canvia import, receptor, concepte ni numero quan arriba una transferencia;
- si no hi ha factura i el cas es facturable, el flux final fa `issueInvoice()` + `registerPayment()` de manera idempotent.

Pendent:

- implementar `registerPayment()` i assignacions per transferencia;
- recuperar o confirmar cossos de `buscarInfomacioPagament.php` i `mostrarModalInfoPag.php`;
- definir llista final de `BANC`/metodes i si cal referencia bancaria obligatoria;
- provar transferencia parcial, transferencia que tanca factura abans de cobrament, import superior al pendent, factura d'empresa/responsable i reintent duplicat;
- afegir captures finals i evidencia de preproduccio.

Seguent bloc recomanat:

```text
Compensacio/saldo
```

## 9. Veredicte

```text
Auditoria documental actual: PARCIAL.

El projecte esta ben orientat i els riscos principals estan identificats.
La documentacio encara depen massa de coneixement verbal de Meriem.
Cal convertir la casuistica coneguda en procediments, taules, permisos, proves i evidencies.
```

Fonts normatives de referencia:

- Real Decreto 1007/2023: https://www.boe.es/buscar/act.php?id=BOE-A-2023-24840
- Orden HAC/1177/2024: https://www.boe.es/buscar/act.php?id=BOE-A-2024-22138
- AEAT, certificacion de sistemas informaticos: https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/cuestiones-generales/certificacion-sistemas-informaticos_.html
