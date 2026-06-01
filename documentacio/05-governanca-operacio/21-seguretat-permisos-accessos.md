# 21 - Seguretat, permisos i accessos

> Document d'estat final sobre qui pot veure, crear, rectificar, exportar o administrar dades fiscals. Els permisos es documenten segons l'organitzacio real de la intranet, no com una matriu generica artificial.

## 1. Objectiu

Garantir que:

- les factures emeses no es poden modificar silenciosament;
- les accions fiscals crítiques queden restringides;
- la BD fiscal queda protegida;
- els accessos es poden entendre segons els apartats reals de treball de PrisMa;
- el SIF conserva logs de les accions sensibles.

## 2. Criteri de documentacio

No es fara una matriu exhaustiva de tots els botons de la intranet en aquest document, perque s'allargaria massa i seria poc mantenible.

Es documenta en dos nivells:

1. Mapa funcional d'apartats i persones amb acces.
2. Regles critiques del SIF que s'han de complir sempre.

Regla:

```text
La matriu detallada de cada pantalla es pot concretar quan s'implementi o es revisi cada apartat.
Aquest document fixa les regles principals i l'organitzacio real dels accessos.
```

## 3. Persones internes

| Persona | Funcio principal |
| --- | --- |
| Meriem Abjil Bajja | Responsable funcional i tecnica del projecte, desenvolupament, administracio tecnica, configuracio SIF, incidencies i posada en operatiu. |
| Adam Carmona | Direccio de l'entitat i usuari intern amb acces a moviments de facturacio. |
| Pablo Martori Delupi | Gestio/secretaria i operativa diaria d'alumnes, pagaments, factures i reclamacions. |
| Isa | Suport relacionat amb Moodle i suport a Secretaria quan Pablo no treballa. No es rol fiscal ordinari del SIF. |

Meriem te, a mes, acces de desenvolupament i pot provar el sistema assumint temporalment el rol funcional de Pablo, Adam, Isa o el seu propi acces.

## 4. Apartats reals de la intranet

### 4.1. Alumnes

Inclou, entre altres:

- consulta i edita informacio de l'alumne;
- passar pagaments;
- gestionar factures;
- validar descomptes;
- crear/editar entitats;
- dades de factura vinculades a alumne, entitat o responsable.

Accessos:

| Apartat | Acces |
| --- | --- |
| Consulta i edita informacio de l'alumne | Meriem, Adam, Pablo, Isa |
| Passar pagaments (`/alumnes/pagaments/`) | Meriem, Adam, Pablo |
| Consulta - Edita - Anula factura (`/alumnes/factura/`) | Meriem, Adam, Pablo |
| Validar descomptes | Meriem, Adam, Pablo |
| Crear/editar entitats | Meriem, Adam, Pablo |

Notes:

- Meriem hi te acces com a responsable tecnica, desenvolupadora i gestio d'incidencies.
- Isa pot consultar informacio d'alumnes quan dona suport, pero no es rol fiscal ordinari.

### 4.1.1. Implementacio actual de permisos

La intranet actual guarda permisos per apartat a `apartats`:

- `ROLS_VISUALITZAR`;
- `ROLS_EDITAR`;
- `ROLS_ENVIAR_MSG`.

I guarda rols d'usuari a `usuaris.ROLS`.

La logica actual identificada es:

- `consultaRolsEdiicio($page)` retorna els rols d'edicio de la pagina;
- `consultaRolsUsuari()` retorna els rols de l'usuari;
- el JS compara rols i calcula `tePermisEdicio`;
- les pantalles poden mostrar o ocultar accions segons aquest resultat.

Regla SIF:

```text
El permis visual no autoritza per si sol una accio fiscal.
El servidor ha de validar rol, sessio, estat fiscal i motiu abans d'executar.
```

Per tant:

- el front pot desactivar icones o botons;
- l'endpoint AJAX o API SIF ha de repetir la validacio;
- qualsevol accio critica ha de deixar log d'usuari, data, pantalla/origen i motiu.

### 4.2. Cursos

Inclou apartats de gestio i consulta de cursos.

Accessos:

```text
Meriem, Adam, Pablo i Isa
```

Motiu:

- son apartats necessaris per la gestio general dels cursos;
- Isa pot necessitar informacio de cursos per tasques relacionades amb Moodle.

### 4.3. Gestio de cursos

Inclou tasques:

- abans de comencar cursos;
- quan els cursos han començat;
- quan els cursos han acabat;
- apartats de reclamacions.

Accessos:

| Apartat | Acces |
| --- | --- |
| Reclamacions | Meriem, Adam, Pablo |
| Apartats concrets vinculats a inici de cursos/Moodle | Isa, quan cal per la seva tasca |
| Resta d'apartats de gestio de cursos | Meriem i Pablo, segons operativa interna |

Regla sobre morositat:

- marcar morositat o registrar una reclamacio no modifica una factura emesa;
- una baixa administrativa tampoc genera rectificativa automatica;
- el pagament posterior, saldo, devolucio o rectificativa s'ha de tramitar pel flux fiscal corresponent.

### 4.4. Tasques propies de Meriem

Meriem disposa del seu propi entorn i rol de treball, que pot incloure:

- desenvolupament;
- proves de rols;
- incidencies informatiques;
- tasques pendents de programacio;
- marketing;
- mailings;
- xarxes;
- preparacio d'imatges;
- organitzacio de tasques internes.

Aquestes tasques no son necessàriament rols fiscals, pero formen part del seu espai de treball real.

## 5. Actors externs

### 5.1. Alumne

L'alumne no es un rol de la intranet principal.

Te una intranet personalitzada separada.

Pot consultar:

- dades propies;
- pagaments propis;
- factures visibles a nom seu;
- PDF/QR si la factura li correspon.

No pot veure:

- factures de grup o empresa on no sigui receptor fiscal;
- factures completes d'una empresa/responsable que paga per diversos participants.

### 5.2. Empresa o responsable

L'empresa/responsable no te acces a la intranet principal.

Si ha de consultar factures, es fara per:

- correu;
- enllac segur;
- gestio interna;
- o futur espai especific, si es decideix crear-lo.

## 6. Rols del SIF

El SIF es una capa separada de la intranet operativa.

Rols del SIF:

| Rol SIF | Persona/proces | Permisos principals |
| --- | --- | --- |
| Responsable tecnica SIF | Meriem | Configuracio, versions, incidencies SIF, exportacions, documents, proves i administracio. |
| Operador facturacio | Adam | Consulta, moviments de facturacio, exportacions fiscals, accions operatives permeses. |
| Operador gestio/secretaria | Pablo | Factures, pagaments, rectificatives operatives, descomptes i entitats segons apartat. |
| ISA_SUPORT | Isa | Acces generic de consulta si es defineix, sense accions fiscals critiques. |
| Auditor/AEAT nomes lectura | Usuari temporal/restringit | Consulta de declaracio, versio, registres, documents, logs i exportacions quan correspongui. |
| Proces automatic SIF | Tasca servidor | Cues, retries, generacio PDF/QR/XML, incidencies automatiques i actualitzacions tecniques amb log. |

## 7. Accions critiques del SIF

| Accio critica | Qui la pot fer |
| --- | --- |
| Canviar configuracio SIF | Meriem |
| Activar versio SIF | Meriem |
| Resoldre incidencia SIF | Meriem |
| Descarregar exportacions fiscals | Meriem i Adam |
| Marcar factura electronica (`E_FACT`) | Meriem, Adam i Pablo |
| Generar rectificativa operativa | Meriem, Adam i Pablo |
| Crear factura ordinaria/manual | Meriem, Adam i Pablo, segons pantalla |
| Registrar pagament | Meriem, Adam i Pablo, segons pantalla |
| Generar factura abans de cobrament | Meriem, Adam i Pablo |
| Reintentar enviament AEAT manual | Meriem, o proces automatic SIF si esta programat |

Regles:

- Isa no s'esmenta a les accions critiques perque no les fa.
- El proces automatic SIF no decideix accions funcionals; nomes executa processos predefinits.
- Auditor/AEAT nomes lectura no modifica res.

## 8. Processos automatics del SIF

El proces automatic del SIF no es una persona ni un rol de la intranet.

Pot ser:

- cron;
- script;
- proces en segon pla;
- tasca programada del servidor.

Pot fer:

- processar `fiscal_queue`;
- reintentar enviaments AEAT;
- generar PDF/QR/XML;
- crear incidencies automatiques;
- marcar errors tecnics;
- actualitzar estats tecnics.

No pot:

- decidir rectificatives manualment;
- resoldre incidencies funcionals;
- canviar configuracio sense ordre tecnica;
- actuar sense log.

## 9. BD fiscal

Criteris:

- nomes el SIF escriu factures emeses;
- no hi ha `UPDATE`/`DELETE` manual sobre factures emeses;
- rectificacions mitjancant rectificativa o event;
- fitxers PDF/XML fora de webroot;
- secrets i certificat digital fora de codi font;
- usuari app normal sense permisos directes per modificar factures emeses;
- usuari SIF amb permisos controlats;
- administracio BD reservada a Meriem.

## 10. Acces documental dins del SIF

La declaracio responsable i la informacio de versio han d'estar accessibles dins del SIF de forma rapida, clara i llegible.

Pantalla recomanada:

```text
pay.prisma.cat/sif/sistema/documentacio
```

La intranet principal pot tenir un enllac a aquesta pantalla i mostrar avisos, pero la font oficial de documentacio i versio activa sera el panell SIF de `pay.prisma.cat`.

## 11. Rol auditor fiscal / AEAT nomes lectura

Rol pensat per facilitar consulta en cas d'auditoria o inspeccio.

Permisos:

- veure declaracio responsable;
- veure versio activa;
- consultar registres fiscals;
- exportar registres quan correspongui;
- consultar registre d'events;
- consultar documents fiscals;
- consultar incidencies fiscals.

Ubicacio d'acces:

```text
pay.prisma.cat/sif
```

Prohibicions:

- no pot crear factures;
- no pot registrar pagaments;
- no pot modificar dades;
- no pot resoldre incidencies;
- no pot generar rectificatives;
- no pot accedir a dades no fiscals innecessaries.
