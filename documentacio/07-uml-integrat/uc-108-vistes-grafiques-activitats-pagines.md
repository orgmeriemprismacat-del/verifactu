# UC-108 — Vista gràfica dels diagrames d'activitat de les quatre pàgines

**Document per llegir i decidir amb Meriem.** Aquesta pàgina utilitza diagrames Mermaid, que GitHub representa gràficament. Els **32 diagrames d'activitat UML en PlantUML, inclosos els diagrames per cada apartat funcional**, tenen la seva font canònica a [UC-108 — diagrames d'activitat detallats](uc-108-activitats-pagines-tastets-actual-final.md). Aquesta visualització resumeix *cada pàgina completa*; els detalls per apartat i la traçabilitat estan al document principal.

**Llegenda:** ACTUAL = comportament observat al codi `main` del 22/09/2026; FINAL = proposta que encara requereix resoldre DEC-108-01…07 a la [fitxa funcional](../06-fitxes-funcionals/uc-108.md#20-decisions-que-volem-definir-amb-negoci-abans-de-passar-a-un-altre-uc). No s'han executat proves ni confirmat el desplegament.

## 1. Llistat `/tastets`

**ACTUAL — Seccions:** A títol/GRATUÏT; B portada; C informació i targetes; D modal opcional d'avisos, que no inscriu al tastet.

```mermaid
flowchart TD
  A([Visitar /tastets]) --> B["JS: mostrar_pagina_tastets.php"]
  B --> C["Tastets: títol i etiqueta GRATUÏT / portada / informació"]
  C --> D["SELECT reptes ESTAT=1; pintar targetes"]
  D --> E{"Acció del visitant"}
  E -->|Seleccionar tastet| F["Navegar a fitxa individual"]
  E -->|Obrir avisos| G["Modal opcional d'avisos UC-125"]
  G --> H["Validació client de checkbox i correu"]
  H --> I["Resposta visible del modal; alta efectiva no acreditada en el tram revisat"]
  E -->|Cap acció| J([Continua navegació])
  F --> K([Pàgina de fitxa])
  I --> J
```

**FINAL PROPOSAT — Sense alta fiscal ni acadèmica en consultar targetes.**

```mermaid
flowchart TD
  A([Entrar al llistat]) --> B["Validar catàleg i oferta pública vigent"]
  B --> C{"Hi ha tastets publicables?"}
  C -->|No| D["Mostrar estat buit/error sense crear alta"]
  C -->|Sí| E["Presentar gratuïtat, informació, portada i targetes actives"]
  E --> F{"Acció"}
  F -->|Fitxa| G["Revalidar oferta seleccionada i navegar al mateix slug"]
  F -->|Avisos| H["UC-125: decidir finalitat avís i possible butlletí separats"]
  H --> I["Mostrar resultat real de la sol·licitud d'avisos"]
  G --> J([Pàgina de fitxa])
  D --> K([Fi])
  I --> K
```

## 2. Fitxa individual `/tastets/{slug}`

**ACTUAL — Seccions:** A títol i retorn; B portada/GRATUÏT; C presentació; D CTA inscripció; E curs original i previsualització si disponible.

```mermaid
flowchart TD
  A([Obrir fitxa del tastet]) --> B["AJAX mostrar_pagina_tastet.php"]
  B --> C["Tastet consulta reptes per ID_URL actiu"]
  C --> D["Mostrar títol, portada, GRATUÏT i presentació"]
  D --> E["Mostrar botó inscripció i curs original si existeix"]
  E --> F{"Acció de visitant"}
  F -->|Inscripció gratuïta| G["Navegar a /inscripcions/tastets/{slug}"]
  F -->|Curs original| H["Obrir oferta del curs complet diferenciada"]
  F -->|Previsualitzar| I["AJAX mostrar_resum.php; modal i possible vídeo"]
  F -->|Tastets| J["Tornar al llistat"]
  G --> K([Pàgina formulari])
  H --> L([Una altra oferta])
  I --> M([Romandre a la fitxa])
  J --> N([Pàgina llistat])
```

**FINAL PROPOSAT — La visita al curs original o al vídeo no és una inscripció.**

```mermaid
flowchart TD
  A([Visitar fitxa]) --> B["Resoldre producte i estat públic"]
  B --> C{"Oferta encara activa?"}
  C -->|No| D["Mostrar indisponibilitat; bloquejar inscripció"]
  C -->|Sí| E["Mostrar dades i condicions aprovades del tastet gratuït"]
  E --> F{"Acció"}
  F -->|Inscriure's| G["Validar producte i navegar a formulari amb ID/slug traçable"]
  F -->|Curs complet| H["Navegar a una altra operació comercial, no convertir el tastet"]
  F -->|Previsualitzar o tornar| I["Consulta sense efectes acadèmics/fiscals"]
  G --> J([Pàgina formulari])
  D --> K([Fi])
  H --> K
  I --> K
```

## 3. Formulari `/inscripcions/tastets/{slug}`

**ACTUAL — Seccions:** A càrrega AJAX; B dades personals; C com ens ha conegut/comentaris/mailing automàtic; D errors i modal de duplicat; E alta llegada.

```mermaid
flowchart TD
  A([Obrir formulari]) --> B["mostrar_inscripcio_tastets.php i codiCurs per AJAX"]
  B --> C["Mostrar nom, cognoms, document NIF/NIE o passaport, email i confirmació, població"]
  C --> D["Mostrar com ens has conegut, Altres, comentaris i avís d'alta automàtica al butlletí"]
  D --> E["Prem Enviar dades; validar camps al JS"]
  E --> F{"Validació client OK?"}
  F -->|No| G["Modal d'errors; romandre al formulari"]
  F -->|Sí| H["AJAX CURS+DNI+INSC_CURS=1"]
  H --> I{"Ja consta inscripció en estat 1?"}
  I -->|Sí| J["Modal Ja t'has inscrit amb botó Tanca"]
  I -->|No| K["GET enviarInscripcioTastet.php amb mailing=yes"]
  K --> L["INSERT inscripcions_reptes; generar token"]
  L --> M["Mailing forçat i tramitació de correus"]
  M --> N{"JS rep token sense error?"}
  N -->|Sí| O["Redirigir a /tastets/confirmacio/{slug}/{token}"]
  N -->|No| P["Modal error"]
  O --> Q([Pàgina confirmació])
  G --> R([Formulari])
  J --> R
  P --> R
```

**FINAL PROPOSAT — Decidir estat de la petició, alta acadèmica i mailing de manera independent.**

```mermaid
flowchart TD
  A([Entrar al formulari]) --> B["Servidor: resoldre tastet actiu, identitat i possible convocatòria"]
  B --> C{"Oferta vàlida?"}
  C -->|No| D["Mostrar indisponibilitat sense alta"]
  C -->|Sí| E["Mostrar dades mínimes i opció comercial independent"]
  E --> F["Enviar petició amb request_id"]
  F --> G["Servidor: validar dades, actor, producte i petició existent sota concurrència"]
  G --> H{"Petició compatible anterior?"}
  H -->|Sí| HP{"Sol·licitud d'alta al campus pendent?"}
  HP -->|Sí| HQ["Mostrar: ja tens una sol·licitud pendent; recuperar mateixa petició"]
  HQ --> HR([Fi sense cap altra inscripció ni desbloqueig])
  HP -->|No| I{"Accés anterior caducat?"}
  I -->|Sí| IA{"Inscripció web desbloquejada per secretaria/suport per persona+tastet?"}
  IA -->|No| IB["Inscripció web bloquejada; contactar secretaria/suport perquè desbloquegi"]
  IA -->|Sí| IC["Secretaria/suport ja ha desbloquejat; la persona torna al formulari i l'envia"]
  I -->|No| ID["Retornar estat anterior; política dels altres estats pendent DEC-108-03"]
  IC --> K["Crear nova sol·licitud autoritzada una vegada; FREE_SAMPLE si DEC-108-06"]
  H -->|No| J["Crear sol·licitud gratuïta una vegada"]
  J --> L["Vincular FREE_SAMPLE només si DEC-108-06 ho aprova"]
  K --> L
  ID --> IE([Fi sense nova alta])
  IB --> IE
  L --> M{"Opció de màrqueting expressa?"}
  M -->|Sí| N["UC-125: registrar opció i confirmar segons DEC-108-04"]
  M -->|No| O["No inscriure al mailing comercial"]
  N --> P["Tramitar accés acadèmic i avís operatiu de forma separada"]
  O --> P
  P --> Q["Mostrar sol·licitud rebuda o estat acadèmic real"]
  Q --> R([Pàgina confirmació; cap factura ni CHARGE])
  D --> S([Fi])
```

## 4. Confirmació `/tastets/confirmacio/{slug}/{token}`

**ACTUAL — Seccions:** A HMAC i consulta de fila; B missatge de sol·licitud enviada amb termini orientatiu; C contacte en cas d'incidència.

```mermaid
flowchart TD
  A([Obrir confirmació]) --> B["JS: AJAX mostrar_confirmacio_inscripcio_tastet_automatic.php"]
  B --> C["Descodificar token, desxifrar ID, comprovar HMAC"]
  C --> D{"Token íntegre?"}
  D -->|No| E["Missatge d'error"]
  D -->|Sí| F["Buscar inscripcions_reptes.ID i INSC_CURS en 0/1"]
  F --> G{"Fila trobada?"}
  G -->|No| E
  G -->|Sí| H["Mostrar sol·licitud enviada, email, gratuïtat i revisió del correu"]
  H --> I["Indicar previsió 24/48h i setmana d'accés després d'alta"]
  I --> J["Mostrar via de contacte en cas d'incidència"]
  J --> K([Fi; no acredita accés Moodle])
  E --> K
```

**FINAL PROPOSAT — Mostrar l'estat verificat i protegir dades del titular.**

```mermaid
flowchart TD
  A([Obrir resultat]) --> B["Verificar token, titularitat i estat d'accés al resultat segons DEC-108-01"]
  B --> C{"Consulta autoritzada?"}
  C -->|No| D["Error sense revelar dades d'altres persones"]
  C -->|Sí| E["Recuperar sol·licitud i estat acadèmic real"]
  E --> F{"Moodle confirma accés efectiu?"}
  F -->|Sí| G["Mostrar dates d'accés aprovades, si estan verificades"]
  F -->|No| H["Mostrar sol·licitud rebuda / alta pendent, sense afirmar accés"]
  G --> I{"Hi ha incidència de sincronització o comunicació?"}
  H --> I
  I -->|Sí| J["Mostrar estat pendent i via de contacte; reintent separat al servidor"]
  I -->|No| K["Mostrar estat actual sense efecte fiscal"]
  J --> L([Fi])
  K --> L
  D --> L
```

**DEC-108-03b ACORDADA:** si la primera sol·licitud encara està pendent d'alta al campus, avisar que ja hi ha una petició pendent i no crear cap altra alta; no exigir desbloqueig de secretaria/suport. **DEC-108-03a ACORDADA:** accés caducat → secretaria/suport desbloqueja la inscripció web per persona+tastet i és la persona qui torna a emplenar i enviar el formulari; secretaria/suport no inscriu directament. El PHP actual no acredita aquest control. **Abans de considerar la proposta FINAL aprovada:** concretar el mecanisme de l'autorització i resoldre la resta de [DEC-108-01…07](../06-fitxes-funcionals/uc-108.md#20-decisions-que-volem-definir-amb-negoci-abans-de-passar-a-un-altre-uc). Els subdiagrames PlantUML dels dotze apartats tenen estats independents i no s'han substituït per aquesta vista resumida.
