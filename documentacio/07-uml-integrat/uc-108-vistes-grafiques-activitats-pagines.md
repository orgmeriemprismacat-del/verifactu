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
  A([Entrar al formulari]) --> B["Servidor: resoldre tastet actiu i identitat; inscripció contínua sense convocatòria (DEC-108-02c)"]
  B --> C{"Oferta vàlida?"}
  C -->|No| D["Mostrar indisponibilitat sense alta"]
  C -->|Sí| E["Mostrar dades mínimes i elecció opcional Sí/No per al butlletí al formulari (DEC-108-04a)"]
  E --> F["Enviar petició amb request_id"]
  F --> G["Servidor: revalidar estat actiu del tastet en enviar, dades, actor i petició existent sota concurrència"]
  G --> H{"Petició compatible anterior?"}
  H -->|Sí| HP{"Sol·licitud d'alta al campus pendent?"}
  HP -->|Sí| HQ["Mostrar: ja tens una sol·licitud pendent; recuperar mateixa petició"]
  HQ --> HR([Fi sense cap altra inscripció ni desbloqueig])
  HP -->|No| HA{"Accés actiu al mateix tastet?"}
  HA -->|Sí| HB["Mostrar: ja estàs inscrita; no crear una altra sol·licitud"]
  HB --> HR
  HA -->|No| I{"Accés anterior caducat?"}
  I -->|Sí| IA{"Inscripció web desbloquejada per secretaria/suport per persona+tastet?"}
  IA -->|No| IB["Inscripció web bloquejada; contactar secretaria/suport perquè desbloquegi"]
  IA -->|Sí| IC["Secretaria/suport ja ha desbloquejat; la persona torna al formulari i l'envia"]
  I -->|No| ID{"La persona s'ha donat de baixa o secretaria ha denegat la petició anterior?"}
  ID -->|Sí| IDA["Permetre nova inscripció directa al formulari web, sense desbloqueig"]
  IDA --> IDB["Conservar historial de baixa/denegació; crear nova petició idempotent"]
  IDB --> L
  ID -->|No| IDC["Estat no classificat: no crear alta sense comprovació"]
  IC --> K["Crear nova sol·licitud autoritzada una vegada; FREE_SAMPLE si DEC-108-06"]
  H -->|No| J["Crear sol·licitud gratuïta una vegada"]
  J --> L["Vincular FREE_SAMPLE només si DEC-108-06 ho aprova"]
  K --> L
  IDC --> IE([Fi sense nova alta])
  IB --> IE
  L --> M{"La persona ha triat Sí explícit al butlletí opcional?"}
  M -->|Sí| N["UC-125: registrar Sí explícit amb evidència i donar d'alta directament al butlletí en enviar, sense correu de confirmació (DEC-108-04b)"]
  M -->|No| O["No donar d'alta al butlletí comercial; No no bloqueja tastet ni avisos operatius (DEC-108-04a)"]
  N --> NX["Registrar resultat comercial real; si falla, incidència sense afirmar subscripció ni bloquejar tastet"]
  NX --> P["Petició pendent: secretaria fa manualment l'alta al campus; avís operatiu de sol·licitud rebuda (DEC-108-05a)"]
  O --> P
  P --> Q["Mostrar petició rebuda; accés només després d'activació manual real per secretaria, no per enviament web"]
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
  C -->|Sí| E["Recuperar sol·licitud i estat acadèmic real; l'alta al campus és manual per secretaria (DEC-108-05a)"]
  E --> F{"Moodle confirma accés efectiu?"}
  F -->|Sí| G["Activació manual efectiva per secretaria: venciment 7 DIES DESPRÉS A LA MATEIXA HORA, si verificat (DEC-108-02b/d/05a)"]
  G --> GX{"Ja era membre del campus?"}
  GX -->|Sí| GM["Conserva les claus d'accés existents"]
  GX -->|No| GN["Secretaria crea MANUALMENT el compte nou; el CAMPUS envia AUTOMÀTICAMENT per correu les claus a la persona (DEC-108-05f)"]
  GM --> GE["Secretaria envia MANUALMENT la PLANTILLA EXISTENT: només informa que l'accés està activat, sense enllaç ni instruccions de claus; també amb butlletí No (DEC-108-05b/c/d/e)"]
  GN --> GE
  GN -.-> GI{"Circuit INDEPENDENT: correu de claus rebut?"}
  GI -->|No| GIN["Incidència de credencials per separat; NO bloqueja l’avís MANUAL (DEC-108-05g)"]
  GIN -.-> GPR["Si la incidència impedeix entrar durant part de la setmana: secretaria o Isa MODIFIQUEN el venciment al CAMPUS per una NOVA SETMANA COMPLETA des de la RESOLUCIÓ, fins 7 dies després a la MATEIXA HORA (DEC-108-02d/05j/k/l); verificar execució"]
  GPR -.-> GTRACK["DEC-108-05q: actualment NO hi ha seguiment específic de la incidència o la pròrroga en una eina/intranet; només actuació al campus i avís manual"]
  GPR --> GMAIL["DESPRÉS de concedir la pròrroga, ISA O SECRETARIA envien MANUALMENT el CORREU amb LA MATEIXA PLANTILLA DE L'AVÍS INICIAL adaptada (DEC-108-05o i DEC-108-05p): TINDRÀ 7 DIES D'ACCÉS (DEC-108-05m/n), SENSE DATA EXACTA DE VENCIMENT; NO enviament automàtic del campus"]
  GIN --> GS["Secretaria intenta reenviar o regenerar claus des del CAMPUS (DEC-108-05i)"]
  GS --> GR{"Incidència de claus resolta?"}
  GR -->|No| GT["Isa: suport tècnic (DEC-108-05h)"]
  GT --> GU{"Incidència resolta?"}
  GU -->|No| GV["Desenvolupament: Meriem; incidència separada (DEC-108-05h)"]
  F -->|No| H["Mostrar sol·licitud rebuda / alta MANUAL pendent per secretaria en 24–48 h laborals; una setmana només des de l'activació real (DEC-108-02a/b/05a)"]
  GE --> I{"Hi ha incidència de sincronització o comunicació?"}
  H --> I
  I -->|Sí| J["Mostrar estat pendent i via de contacte; incidència tècnica separada, sense alta Moodle automàtica"]
  I -->|No| K["Mostrar estat actual sense efecte fiscal"]
  J --> L([Fi])
  K --> L
  D --> L
```

**DEC-108-05a ACORDADA:** en aquesta fase secretaria fa manualment l'alta al campus després de rebre la sol·licitud web; l'accés no s'activa automàticament amb el formulari ni amb un worker. L'automatització es vol per al 2027 però no s'inclou al flux actual. **DEC-108-05b ACORDADA:** després de completar l'alta manual efectiva, secretaria envia un correu operatiu d'accés a la persona, independent del «Sí/No» del butlletí, també amb «No». **DEC-108-05c ACORDADA:** secretaria prepara i envia el correu MANUALMENT; no hi ha cap correu d'accés automàtic que s'hagi de programar ara. **DEC-108-05d ACORDADA:** secretaria utilitza una plantilla de correu ja preparada, no redacta cada missatge de zero. Cal contrastar-ne el text literal i la ubicació reals. **DEC-108-05e ACORDADA:** aquesta plantilla manual només comunica que l'accés ja s'ha activat, sense enllaç al campus ni instruccions per obtenir les claus. Si la persona ja era membre conserva les credencials existents. **DEC-108-05f ACORDADA:** si encara no era membre, quan secretaria crea MANUALMENT el compte el campus envia AUTOMÀTICAMENT per correu les claus d'accés a la persona; aquest correu del campus és diferent del correu posterior manual de secretaria i no implica automatitzar l'alta al campus. **DEC-108-05g ACORDADA:** si no rep les claus, secretaria envia IGUALMENT la plantilla manual informativa després de l'activació real; es gestiona la incidència de claus per separat. Enviar l'avís manual no acredita recepció de credencials ni entrada real al campus. **DEC-108-05h/i ACORDADES:** secretaria atén primer la incidència i prova reenviar o regenerar claus des del campus; si no la resol, es trasllada a Isa (suport tècnic) i després a desenvolupament (Meriem), sense condicionar l'avís manual posterior a l'activació ni duplicar comptes. **DEC-108-05j, DEC-108-05k i DEC-108-05l ACORDADES:** si una incidència de claus impedeix entrar durant part de la setmana, **concedir una NOVA SETMANA COMPLETA a partir de la resolució de la incidència** (no sumar només els dies perduts); **secretaria o Isa modifiquen el venciment al campus**, sense atribuir-ne exclusivament l'execució a cap de les dues. **DEC-108-05m PRECISADA:** després de concedir la pròrroga s'avisa per correu la persona que **tindrà 7 dies d'accés**, sense indicar cap data exacta de venciment en el missatge. El venciment real es modifica al campus i s'ha de verificar separadament. **DEC-108-05n ACORDADA:** Isa o secretaria envien MANUALMENT aquest correu de pròrroga; no hi ha un enviament automàtic del campus vinculat al canvi de venciment. **DEC-108-02d ACORDADA:** tant l'accés ordinari com el concedit per pròrroga venç SET DIES DESPRÉS A LA MATEIXA HORA del seu inici (activació real o resolució de la incidència, respectivament); NO a les 23.59 h. Comprovar la configuració efectiva de Moodle/BD sense pressuposar seguiment formal de la incidència. **DEC-108-05q — SITUACIÓ ACTUAL:** no hi ha cap circuit específic de seguiment de la incidència o la pròrroga a la intranet ni en una eina addicional; la modificació efectiva del venciment al campus i l'enviament manual del correu continuen sent les actuacions a contrastar. No pressuposar ni requerir un sistema de tiquets ja existent. **DEC-108-05o i DEC-108-05p ACORDADES:** l'avís de pròrroga reutilitza LA MATEIXA plantilla existent de l'avís inicial d'accés activat, adaptant-ne el missatge a 7 dies; són dos enviaments manuals separats. Text literal, ubicació, camps variables, destinatari exacte i traça pendents de contrastar. Pendent verificar l'instant de resolució, l'hora final/inclusivitat, permisos i modificació efectiva del venciment al campus; cap nova inscripció web. Pendent de comprovar permisos i circuit real. Pendent de verificar-ne el disparador, el destinatari i el resultat efectiu. Separar-lo del correu de petició rebuda; resten per verificar dates, contingut, destinatari i traça de l'enviament, sense pressuposar-ne el mecanisme tècnic. **DEC-108-04a ACORDADA:** subscripció al butlletí opcional amb elecció «Sí/No» al formulari de tastet; «No» no impedeix la sol·licitud ni els avisos operatius. Cal eliminar la subscripció automàtica al PHP/JS. **DEC-108-04b ACORDADA:** si tria «Sí» explícit, subscripció directa en enviar el formulari sense correu/enllaç de confirmació addicional, un cop registrada correctament; pendent de definir text/evidència i de provar alta/error/reintents. **DEC-108-02c ACORDADA:** es pot sol·licitar el tastet en qualsevol moment mentre estigui actiu, sense dates de convocatòria; revalidar-ne l'estat al servidor abans d'acceptar l'alta. **DEC-108-02a ACORDADA:** mantenir el termini comunicat de 24–48 hores laborals per a l'alta al campus després de la sol·licitud. Aquest avís no acredita un accés immediat. **DEC-108-02b ACORDADA:** una setmana d'accés des del moment en què secretaria activa realment el campus, no des de l'enviament del formulari; pendent de verificar les dates reals i el venciment PHP/Moodle. **DEC-108-03d ACORDADA:** després de baixa de la persona o petició denegada per secretaria, es permet una nova inscripció des del formulari web sense desbloqueig; es conserva l'historial i s'evita la doble alta. **DEC-108-03c ACORDADA:** accés actiu al mateix tastet → mostrar «ja estàs inscrita» sense crear una segona sol·licitud ni exigir desbloqueig. **DEC-108-03b ACORDADA:** si la primera sol·licitud encara està pendent d'alta al campus, avisar que ja hi ha una petició pendent i no crear cap altra alta; no exigir desbloqueig de secretaria/suport. **DEC-108-03a ACORDADA:** accés caducat → secretaria/suport desbloqueja la inscripció web per persona+tastet i és la persona qui torna a emplenar i enviar el formulari; secretaria/suport no inscriu directament. El PHP actual no acredita aquest control. **Abans de considerar la proposta FINAL aprovada:** concretar el mecanisme de l'autorització i resoldre la resta de [DEC-108-01…07](../06-fitxes-funcionals/uc-108.md#20-decisions-que-volem-definir-amb-negoci-abans-de-passar-a-un-altre-uc). Els subdiagrames PlantUML dels dotze apartats tenen estats independents i no s'han substituït per aquesta vista resumida.
