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

### 4.1.2. Consulta - Modifica alumne

La pantalla `Consulta - Modifica alumne` te accions de nivells diferents.

| Accio | Acces orientatiu | Regla fiscal |
| --- | --- | --- |
| Consultar fitxa, inscripcions i observacions | Meriem, Adam, Pablo, Isa quan dona suport | Consulta sense efecte fiscal. |
| Editar dades personals operatives | Segons rol intern d'edicio | No modifica factures emeses. Si afecta dades fiscals d'una factura, cal flux separat de rectificativa. |
| Veure dades del curs | Meriem, Adam, Pablo, Isa quan dona suport | Consulta academica/administrativa. |
| Obrir dades de pagament | Meriem, Adam, Pablo | No pot ser editor lliure de pagaments fiscals. |
| Iniciar canvi de curs | Meriem, Adam, Pablo | Requereix motiu, recalcul i validacio de servidor; pot derivar a rectificativa, saldo, retorn o nou cobrament. |
| Iniciar baixa | Meriem, Adam, Pablo | Baixa administrativa inicial; no rectifica factura automaticament. |
| Veure factura | Meriem, Adam, Pablo; Isa nomes si el suport ho requereix i el rol ho permet | Nomes lectura; sense edicio directa. |
| Veure certificat | Meriem, Adam, Pablo, Isa quan dona suport | Sense impacte fiscal directe. |

Les icones amb baixa opacitat nomes indiquen que l'accio no esta disponible a la UI. El servidor ha de bloquejar igualment l'accio si l'usuari intenta cridar l'endpoint directament.

### 4.1.3. Passar pagaments i TPV

`Passar pagaments` i `Analitzar fitxer TPV` son accions critiques perque poden crear o registrar cobraments fiscals.

| Accio | Acces orientatiu | Regla de seguretat |
| --- | --- | --- |
| Veure pantalla `/alumnes/pagaments/` | Meriem, Adam, Pablo | Acces limitat a rols fiscals/administratius. |
| Cercar pagaments | Meriem, Adam, Pablo | Validar criteri unic i registrar accio quan deriva en pagament. |
| Registrar pagament manual o transferencia | Meriem, Adam, Pablo | Validacio servidor/SIF, idempotencia i log obligatori. |
| Pujar fitxer TPV | Meriem, Adam, Pablo | Validar fitxer, usuari, hash i resultat d'analisi. |
| Resoldre incidencia TPV/SIF | Meriem; Adam/Pablo segons rol final | Motiu obligatori i traça d'auditoria. |
| Consulta de suport sense accio fiscal | Isa nomes si el rol ho permet | Sense registre de pagament ni pujada TPV. |

Regles:

- cap dada de pagament critica ha de quedar autoritzada nomes pel front;
- l'accio final no ha d'anar per `GET` amb import, data, banc o observacions;
- cada registre ha de conservar usuari, data, origen, import, metode, referencia i motiu quan calgui;
- el reprocessament TPV ha de ser idempotent;
- el nom `efact` de la pantalla no pot decidir permisos ni confondre's amb `E_FACT`.

### 4.1.4. Generar factura abans de cobrament

La pantalla `/alumnes/genera-factura-abans-pagar/` crea una factura fiscal real i per tant es una accio critica.

| Accio | Acces orientatiu | Regla de seguretat |
| --- | --- | --- |
| Cercar inscripcions candidates | Meriem, Adam, Pablo | Consulta operativa; si deriva en emissio, queda auditada. |
| Seleccionar inscripcions | Meriem, Adam, Pablo | Validacio servidor de curs, edicio, import i factura previa. |
| Seleccionar entitat/receptor | Meriem, Adam, Pablo | Cal ID intern i snapshot fiscal complet. |
| Emetre factura abans de cobrament | Meriem, Adam, Pablo | `issueInvoice()` amb permisos, idempotencia i log. |
| Descarregar PDF/QR | Meriem, Adam, Pablo | Document immutable del SIF; no regeneracio lliure. |
| Marcar `E_FACT` | Meriem, Adam, Pablo | Accio separada, no automatica en aquest flux. |

Regles:

- el front no pot ser l'unic punt que impedeix barrejar cursos, edicions o inscripcions ja facturades;
- el servidor ha de bloquejar emissio sense receptor fiscal complet;
- l'emissio ha de conservar usuari, origen, inscripcions, receptor, import, motiu/observacions i idempotency key;
- si el PDF/QR falla, no es desfà la factura, es crea incidencia SIF;
- Isa pot consultar dades de suport nomes si el rol ho permet, pero no emet aquesta factura.

### 4.1.5. Consulta - Edita - Anula factura

La pantalla `/alumnes/factura/` tracta factures ja emeses i per tant les accions de canvi son critiques.

| Accio | Acces orientatiu | Regla de seguretat |
| --- | --- | --- |
| Consultar factura i PDF/QR | Meriem, Adam, Pablo | Nomes lectura; les factures historiques s'etiqueten com a no VERI*FACTU quan calgui. |
| Rectificar dades fiscals | Meriem, Adam, Pablo | Accio SIF amb motiu, validacio servidor, log i factura rectificativa/substitutiva. |
| Rectificar import | Meriem, Adam, Pablo | Calcul servidor, decimal controlat, enllac a factura original i estat AEAT. |
| Registrar devolucio o saldo | Meriem, Adam, Pablo | Ha de vincular-se a pagament, factura i rectificativa; no es resol amb `GET`. |
| Marcar/desmarcar `E_FACT` | Meriem, Adam, Pablo | Accio administrativa separada amb usuari, data i motiu. |
| Editar factura SIF emesa directament | Cap rol | Prohibit; `updDadesFact` no pot aplicar-se a factures SIF. |
| Esborrar factura o PDF | Cap rol | Prohibit; nomes rectificatives i documents immutables. |

Regles:

- el servidor ha de validar permisos encara que el JS amagui o desactivi icones;
- cap rectificativa, anul·lacio, devolucio o marca `E_FACT` ha d'anar per `GET`;
- cal registrar motiu, usuari, data, factura original, factura nova i estat de l'accio;
- si hi ha diverses inscripcions vinculades, el sistema ha de validar assignacions abans de confirmar;
- l'antic nom `.confirma-baixa` no s'ha d'usar com a criteri funcional o de permisos.

### 4.1.6. Regles transversals de bloqueig i avis

Aquestes regles apliquen a `Passar pagaments`, `Generar factura abans de cobrament`, `Consulta - Edita - Anula factura`, accessos externs i apartat `VERI*FACTU`.

| Situacio | Resposta esperada |
| --- | --- |
| Usuari sense rol suficient | Bloqueig servidor, avis llegible i log d'intent si l'accio es critica. |
| Dades manipulades al navegador | Recalcular al servidor i rebutjar si no coincideix. |
| Accio per `GET` amb impacte fiscal | Prohibit; migrar a `POST`/API SIF amb idempotencia. |
| Factura SIF ja emesa | Lectura o flux de rectificativa; mai update directe. |
| PDF/QR pendent o fallit | Mostrar estat/incidencia; no regenerar amb dades vives. |
| Token extern invalid o caducat | No mostrar dades fiscals; avis de link no valid. |
| SIF no disponible | Avis tecnic, sense assumir estat correcte ni fer fallback fiscal local. |

Els avisos no son simples textos decoratius. Han d'ajudar l'usuari a triar l'accio segura: obrir factura existent, registrar pagament contra factura, crear incidencia, esperar document, corregir receptor o anar al panell SIF.

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
- estat de cobertura o pagament quan una empresa/responsable ha pagat la inscripcio, sense veure la factura completa.

No pot veure:

- factures de grup o empresa on no sigui receptor fiscal;
- factures completes d'una empresa/responsable que paga per diversos participants.
- paths interns de documents fiscals.

### 5.2. Empresa o responsable

L'empresa/responsable no te acces a la intranet principal.

Si ha de consultar factures, es fara per:

- correu;
- enllac segur;
- gestio interna;
- o futur espai especific, si es decideix crear-lo.

Pot consultar:

- factures on l'empresa/responsable sigui receptor fiscal o contacte autoritzat;
- estat de cobrament;
- PDF/QR servit pel SIF;
- URL de pagament d'empresa/responsable si la factura esta pendent.

No pot:

- entrar a la intranet principal;
- veure dades internes d'alumnes fora de la relacio necessaria amb la factura;
- modificar factura, pagament, rectificativa o marca `E_FACT`;
- rebre una ruta directa a fitxers fiscals.

Regles d'enllac segur:

- token validat al servidor;
- relacio token-factura-receptor comprovada abans de servir document;
- caducitat o revocacio si es defineix;
- cap path intern dins la URL;
- registre d'acces si el document fiscal ho requereix.

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

### 8.1. Callback Redsys

El callback Redsys es un proces servidor, no una accio manual d'usuari.

Pot:

- rebre notificacio Redsys a l'endpoint configurat;
- validar signatura, `Ds_Response`, `Ds_Order` i `Ds_Amount`;
- registrar `redsys_notifications`;
- cridar `issueInvoice()` o `registerPayment()` segons si hi ha factura previa real;
- crear incidencia tecnica si la notificacio no es pot conciliar;
- sincronitzar resum operatiu d'inscripcio despres de resposta SIF.

No pot:

- confiar en imports o ordres rebuts per `GET`;
- calcular numero fiscal local;
- inserir directament a `web.factures` per a factures SIF;
- actualitzar `PAGAMENT`, `DATA PAG`, `FACTURA_RELACIONADA` o `FRACCIO` abans que el SIF accepti l'operacio;
- enviar correu de factura com si fos prova fiscal si el SIF no ha retornat document o estat controlat.

Regles de seguretat:

- secrets Redsys fora de codi font quan es desplegui la versio final;
- endpoint amb TLS i origen/configuracio controlada;
- idempotencia obligatoria per `DS_ORDER`;
- logs tecnics sense exposar dades sensibles innecessaries.

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

### 9.1. Certificat digital, apoderament i secrets

El certificat digital de l'entitat, l'apoderament o qualsevol credencial equivalent per operar amb AEAT s'ha de tractar com a secret tecnic critic.

Regles:

- no es guarda en repositori, webroot, fitxers publics ni logs;
- no es mostra mai complet en pantalla;
- nomes Meriem o el proces automatic autoritzat poden configurar-lo o provar-lo tecnicament;
- el panell SIF pot mostrar estat, caducitat, entorn i ultima prova, pero no claus privades ni contrasenyes;
- produccio i proves han de quedar separades;
- qualsevol error, caducitat o absencia de certificat/apoderament abans de `1.0.0` ha de generar incidencia SIF bloquejant o prebloquejant;
- la configuracio usada per remetre a AEAT ha de quedar vinculada a la versio activa i a la declaracio responsable corresponent.
- el certificat client AEAT no s'ha de confondre amb el certificat TLS/SSL public de `pay.prisma.cat`;
- el proces backend o worker que remet a AEAT es l'unic component que ha de poder utilitzar la clau privada;
- si s'usa un fitxer `PKCS#12` (`.p12`/`.pfx`) o `PEM`, ha de quedar fora del `webroot`, amb permisos minims i sense copia al repositori, SQL, logs o backups no xifrats;
- la contrasenya del contenidor o de la clau privada s'ha de conservar separada del fitxer mitjancant un secret d'entorn o gestor de secrets;
- el panell nomes pot mostrar metadades no secretes, com titular, emissor, caducitat, empremta parcial, ultima prova i estat;
- cal disposar d'una copia de seguretat xifrada, procediment de renovacio/rotacio, revocacio i responsable de custodia;
- la prova valida s'ha de fer des del mateix servidor o entorn del worker, contra l'endpoint AEAT corresponent, i no es suficient comprovar el certificat des d'un navegador personal.

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

Activacio del rol:

- usuari temporal o restringit, creat nomes si cal per auditoria, inspeccio o revisio fiscal;
- permisos de lectura/exportacio limitats a documentacio, registres fiscals, documents, logs i incidencies fiscalment necessaries;
- acces registrat amb usuari, data, IP si es conserva, accio i export realitzada;
- caducitat o desactivacio manual en acabar la revisio;
- cap acces a secrets tecnics, certificat digital, claus privades, contrasenyes, dades academiques no necessaries o pantalles d'edicio.
