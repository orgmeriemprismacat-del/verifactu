# Documentacio 2 - Funcionament del SIF PrisMa per compliment AEAT / VERI*FACTU

> Document tecnic i organitzatiu del Sistema Informatic de Facturacio de PrisMa. Aquest document descriu com funciona el SIF, quins components el formen, qui el gestiona, com garanteix integritat, traçabilitat, conservacio, idempotencia, emissio de factures, registres de facturacio, PDFs/QR i enviament VERI*FACTU.

## 1. Identificacio del Projecte

Nom intern del projecte:

```text
Adaptacio del sistema de facturacio PrisMa a SIF / VERI*FACTU
```

Sistema informatic de facturacio:

```text
SIF PrisMa
```

Modalitat prevista:

```text
VERI*FACTU
```

Domini/subdomini previst:

```text
pay.prisma.cat
```

Nom intern proposat del sistema:

```text
SIF PrisMa
```

Primera versio signable prevista:

```text
1.0.0
```

Responsable funcional i tecnica del projecte:

```text
Meriem Abjil Bajja
```

Responsable legal / direccio:

```text
Adam Carmona
```

Empresa/entitat obligada tributaria:

```text
Associacio PrisMa
G17881988
c. Sant Hipolit, 16, bxs. 2a
17003 Girona
```

## 1.1. Abast normatiu i aplicabilitat

En la revisio del xat antic es va identificar que, abans de signar cap declaracio responsable, cal conservar una comprovacio formal de l'abast normatiu aplicable a l'entitat.

Criteri de partida del projecte:

- el SIF dona suport a la facturacio propia d'Associacio PrisMa;
- el sistema emet factures completes des d'un sistema informatic propi;
- l'entitat esta identificada amb NIF `G17881988` i domicili a Girona;
- el sistema es planteja per territori comu i modalitat `VERI*FACTU`.

Comprovacions pendents abans de signar la versio `1.0.0`:

- confirmar amb gestoria o documentacio interna si Associacio PrisMa presenta Impost sobre Societats;
- confirmar que l'entitat no esta adscrita al SII;
- confirmar que no queda subjecta a normativa foral basca o navarresa;
- confirmar que no existeix resolucio especifica d'exempcio o autoritzacio que alteri l'obligacio de facturar amb SIF adaptat.

Segons la nota informativa de l'AEAT consultada el 2026-09-16, i d'acord amb la modificacio introduida pel Reial decret llei 15/2025, els terminis generals d'adaptacio son:

- entitats que presenten Impost sobre Societats: abans de l'1 de gener de 2027;
- resta d'obligats tributaris afectats: abans de l'1 de juliol de 2027.

Per prudencia documental, la data aplicable a PrisMa s'haura de confirmar abans de tancar el calendari final de posada en produccio. El calendari intern del projecte pot mantenir fites anteriors, pero aquestes fites internes no substitueixen el termini legal aplicable ni converteixen una versio en signable.

Fonts oficials comprovades en aquesta revisio normativa documental (2026-09-16):

- AEAT, preguntes generals sobre qui esta obligat i quines operacions s'inclouen.
- AEAT, modalitats de compliment `VERI*FACTU` i no `VERI*FACTU`.
- AEAT, certificacio dels sistemes informatics i declaracio responsable.
- AEAT, nota informativa d'ampliacio de termini d'adaptacio SIF, amb dates 2027.
- AEAT, FAQ de sistemes `VERI*FACTU`, actualitzades a 21/07/2026.
- BOE, Reial decret llei 15/2025, modificacio de terminis del Reial decret 1007/2023.
- BOE, Orden HAC/1177/2024, article 15 i articles relacionats.

Criteri documental:

- aquest document no substitueix una validacio fiscal externa;
- les comprovacions d'abast normatiu s'han de conservar com a evidencia interna abans de signar la versio `1.0.0`;
- si una gestoria, assessor o revisio especialitzada modifica algun criteri, s'haura d'obrir una decisio nova al registre de decisions i actualitzar la declaracio responsable si afecta la versio signable.

## 1.2. Modalitat VERI*FACTU i model 036

La modalitat prevista del SIF PrisMa es `VERI*FACTU`.

Segons la FAQ de l'AEAT sobre sistemes `VERI*FACTU`, no cal comunicar en el model 036 que es remetran registres de facturacio mitjancant un SIF en modalitat `VERI*FACTU`. L'opcio es produeix pel fet d'iniciar sistematicament la remissio dels registres de facturacio.

Regla interna:

```text
No es crea una tasca de model 036 per activar VERI*FACTU,
tret que una revisio normativa posterior ho exigeixi.
```

Quan el SIF comenci a remetre registres de facturacio, el panell haura de conservar:

- data del primer enviament efectiu;
- mode actiu del SIF;
- versio activa;
- declaracio responsable associada;
- certificat o configuracio d'identificacio electronica usada.

Regles addicionals de modalitat:

- el SIF PrisMa es documenta com a modalitat `VERI*FACTU`;
- un cop iniciada la remissio `VERI*FACTU`, la renuncia o canvi de modalitat s'haura de tractar com a canvi rellevant de compliment i no com a opcio operativa menor;
- en modalitat `VERI*FACTU`, la signatura electronica dels registres remesos no es documenta com a requisit ordinari del flux, pero el sistema igualment ha de disposar de certificat digital de l'entitat o apoderament/configuracio equivalent per identificar-se i operar davant AEAT;
- si en el futur PrisMa operes en modalitat no `VERI*FACTU`, o si calgues respondre requeriments en un escenari diferent, s'hauria d'obrir una revisio especifica de firma electronica, registre d'events i conservacio.

## 2. Objectiu del SIF

El SIF PrisMa centralitza la generacio de factures i registres fiscals derivats dels canals de venda i gestio de PrisMa. Els casos operatius es documenten de forma detallada en la documentacio interna del projecte i en els documents funcionals especifics.

Casos inicialment identificats:

- ecommerce propi;
- retorns i notificacions de Redsys;
- pagaments per transferencia validats a la intranet;
- factures manuals;
- factures abans de cobrament;
- packs;
- grups;
- regals;
- USOC;
- canvis de curs amb diferencia, saldo o devolucio;
- baixes;
- morositat i reclamacions;
- rectificatives;
- devolucions;
- compensacions i saldos.

El SIF evita que cada canal decideixi numero fiscal o crei factures pel seu compte.

Regla de funcionament:

```text
Els canals proposen operacions facturables.
El SIF emet la factura fiscal.
```

### 2.1. Motiu de centralitzacio

La situacio inicial de PrisMa pot incloure fluxos historics on ecommerce, Redsys/TPV o intranet generaven factures directament o compartien logica de facturacio.

Aquesta situacio es considera un risc tecnic i documental, perque podria interpretar-se com emissio distribuida si diferents canals calculen, documenten i persisteixen factures finals pel seu compte.

El disseny del SIF corregeix aquest risc:

- els canals poden preparar dades i calculs previs;
- els canals no assignen numero fiscal definitiu;
- els canals no creen el registre fiscal;
- els canals no generen el document fiscal immutable pel seu compte;
- la BD fiscal i la cadena hash son uniques;
- la API central del SIF es la font de veritat fiscal.

Per tant, el SIF documentat no es un conjunt de sistemes coordinats que emeten factures, sino un sistema central que rep operacions dels canals i emet la factura fiscal.

## 3. Components del Sistema

### 3.1. Canals i processos clients del SIF

Els canals i processos clients del SIF son:

- web/ecommerce;
- Redsys/TPV;
- intranet nova;
- processos interns executats des de la intranet nova:
  - pagament per transferencia;
  - compensacions;
  - factura manual;
  - factura abans de cobrament;
  - rectificacio;
  - devolucio;
  - canvi de curs;
  - baixa.

Els canals/processos clients no poden assignar numero fiscal definitiu.

### 3.2. API central de facturacio

La API central exposa, com a minim:

```text
POST /api/factures/issue
POST /api/payments/register
```

Funcions:

- validar dades d'entrada;
- aplicar idempotencia;
- assignar serie i numero;
- crear factura;
- crear linies;
- crear registre fiscal;
- encadenar hash;
- posar registre a cua AEAT;
- preparar document PDF/QR;
- retornar referencia fiscal al canal.

### 3.3. Base de dades fiscal i base de dades d'intranet

El sistema diferencia entre:

- BD fiscal: conserva dades fiscals immutables, registres, documents, pagaments fiscals i enviaments AEAT.
- BD intranet: conserva dades operatives, academiques i administratives de l'alumne, inscripcions, baixes, canvis, notificacions i fluxos interns.

La BD fiscal conserva:

- factures;
- linies de factura;
- registres de facturacio;
- sequencies fiscals;
- estat de cadena hash;
- cua AEAT;
- documents;
- pagaments;
- assignacions de pagament;
- rectificatives;
- relacions fiscals amb origen de negoci quan calgui;
- logs i incidencies.

La BD intranet conserva o pot conservar:

- inscripcions;
- dades d'alumnes;
- entitats i responsables;
- canvis de curs;
- baixes;
- saldos operatius, si no son estrictament fiscals;
- notificacions internes;
- reclamacions i morositat;
- relacions operatives amb factures fiscals.

Quan una dada operativa afecta fiscalment una factura ja emesa, el SIF registra la rectificativa, pagament, devolucio o compensacio corresponent.

### 3.4. Intranet

La intranet permet o permetra:

- consultar factures;
- veure estat de cobrament;
- veure estat AEAT;
- veure PDF;
- iniciar canvis de curs;
- iniciar baixes;
- registrar pagaments;
- gestionar rectificatives;
- rebre notificacions d'incidencies.

La intranet no modifica factures emeses directament.

## 4. Principis de Funcionament

### 4.1. Centralitzacio

Tota factura fiscal nova ha de passar pel SIF.

### 4.2. Immutabilitat

Una factura emesa no es modifica. Si cal corregir dades fiscals, import, concepte, curs, descompte o receptor, es genera la rectificativa o accio fiscal corresponent.

### 4.3. Snapshot fiscal

La factura guarda una copia fiscal de les dades del receptor en el moment d'emissio:

- nom o rao social;
- NIF/CIF;
- domicili;
- CP;
- poblacio;
- pais;
- email.

La factura no depen de dades vives d'alumne, entitat o responsable.

### 4.4. Idempotencia

Cada operacio facturable porta una clau unica.

Exemples:

```text
REDSYS|CURS|IDPAG:123|ORDER:999999
REDSYS|PACK|IDPAG:123|ORDER:999999
INTRANET|FACTURA_ABANS_PAGAR|GRUP:456
TRANSFERENCIA|REF:ABC123|DATA:2026-05-15
```

Si el canal repeteix la peticio, el SIF retorna la mateixa factura.

### 4.5. Concurrencia

La numeracio fiscal i la cadena hash es protegeixen amb transaccions InnoDB.

El SIF bloqueja:

- la sequencia fiscal corresponent;
- l'estat global de cadena hash.

### 4.6. Cua d'enviament

L'enviament a AEAT es pot fer de manera asincrona.

Si falla:

- queda a `fiscal_queue`;
- es reintenta;
- si persisteix l'error, es genera incidencia i notificacio a intranet.

## 5. Series i Numeracio

Series previstes:

```text
A = factura ordinaria/positiva
R = factura rectificativa
```

Format visible:

```text
A2026/000001
R2026/000001
```

No hi ha series per canal, TPV, curs o intranet.

## 6. Registre Fiscal i Cadena Hash

Cada factura emesa genera un registre fiscal.

Cada registre fiscal disposa de:

- ordre fiscal global;
- hash propi;
- hash anterior;
- payload de dades;
- XML enviat, quan correspongui;
- estat d'enviament.

L'ordre fiscal global no es confon amb el numero visible de factura.

Exemple:

```text
FISCAL_ORDER 1001 -> A2026/000010
FISCAL_ORDER 1002 -> R2026/000002
FISCAL_ORDER 1003 -> A2026/000011
```

## 7. Factures i Linies

La factura es el document fiscal.

La linia detalla el servei, curs, participant, pack, descompte o despesa.

Casos:

- curs normal: una linia;
- pack: una linia per curs;
- grup: una linia per participant;
- regal: una linia al comprador;
- canvi de curs: linies de diferencia i despeses de gestio;
- rectificativa: linies segons la correccio.

## 8. Pagaments

El SIF separa factura i pagament.

Una factura pot estar:

- pendent de cobrament;
- parcialment cobrada;
- cobrada;
- retornada parcialment;
- retornada totalment.

Els moviments economics es registren com:

- cobrament;
- devolucio;
- compensacio.

Una transferencia pot pagar una o diverses factures. Una factura pot tenir diversos pagaments.

## 9. Factures abans de cobrament

Quan una empresa o responsable necessita factura abans de pagar, es pot emetre factura real pendent de cobrament.

Regla:

```text
Si porta A2026/x, es factura real.
Si nomes es pressupost, no porta A2026/x.
```

Quan posteriorment es paga, no es crea nova factura. Es registra el pagament contra la factura existent.

## 10. Rectificatives

Les rectificatives es vinculen directament amb la factura rectificada.

Motivacions previstes:

- dades fiscals incorrectes;
- devolucio total;
- devolucio parcial;
- canvi de curs;
- canvi de concepte;
- canvi d'import;
- saldo/compensacio;
- anulacio d'operacio.

El sistema ha de permetre rectificatives per diferencies i per substitucio, segons criteri fiscal aplicable.

## 11. Documents PDF, XML i QR

La factura PDF es genera en el moment d'emissio o en una tasca immediata associada.

El document:

- es guarda en espai no public;
- queda vinculat a `factura_documents`;
- conserva hash de fitxer;
- no es regenera a partir de dades vives;
- s'accedeix des de intranet amb permisos.

El QR i el text corresponent s'han d'incloure segons especificacions AEAT per factures verificables.

### 11.1. Camps fiscals minims del registre d'alta i QR

El SIF ha de conservar les dades fiscals necessaries per construir el registre de facturacio d'alta, el PDF/QR i l'enviament `VERI*FACTU`.

Com a minim, el disseny documental i tecnic ha de cobrir:

- NIF i nom/rao social de l'emissor;
- NIF i nom/rao social del destinatari quan correspongui;
- indicacio de tercer o destinatari expedidor material, si algun dia aplica;
- serie, numero i data d'expedicio de la factura;
- data d'operacio o data de pagament anticipat si es diferent de la data d'expedicio;
- tipus de factura, incloent factura completa/simplificada si en el futur es fes servir;
- marca de rectificativa i identificacio de la factura rectificada quan sigui preceptiu;
- descripcio general de les operacions;
- import total;
- regim o regims aplicats a les operacions;
- inversio del subjecte passiu si algun dia aplica;
- base imposable, tipus impositiu, quota IVA, recarrec d'equivalencia si algun dia aplica;
- causa d'exempcio o no subjeccio quan no hi hagi IVA repercutit;
- referencia al registre fiscal anterior i a la seva huella/hash quan correspongui;
- identificacio del sistema informatic, versio i productor/titular intern;
- data, hora, minut, segon i hus horari de generacio del registre;
- URL i dades necessaries del QR: NIF emissor, serie/numero, data expedicio i import total.

El detall dels noms interns d'aquests camps es mantindra al diccionari `24-diccionari-camps-i-valors.md`.

Per tancar la versio `1.0.0`, cada camp anterior haura de tenir una correspondencia verificable amb taula/camp intern, generador XML o payload AEAT, PDF/QR i prova associada. Una llista documental no acredita per si sola que el registre d'alta estigui implementat.

## 12. Seguretat i Permisos

Objectiu:

```text
Cap factura emesa pot modificar-se silenciosament.
```

Mesures:

- BD fiscal separada;
- acces restringit;
- permisos MySQL diferenciats;
- aplicacio sense edicio directa de factures emeses;
- rectificatives per corregir;
- logs d'events;
- notificacions d'incidencies.

## 13. Rol de la Responsable del Projecte

La responsable del projecte:

- dirigeix l'adaptacio tecnica i funcional;
- documenta fluxos i casos;
- defineix el model de dades fiscal;
- coordina la migracio dels canals al SIF central;
- valida la coherencia de processos interns;
- mantindra el registre documental del projecte;
- gestionara la preparacio de la declaracio responsable del SIF amb direccio;
- decideix quan el SIF esta tecnicament preparat per entrar en funcionament;
- decideix la posada en operativa del SIF en coordinacio amb les necessitats internes de PrisMa;
- defineix els criteris tecnics de canvi de versio, migracio i activacio dels canals cap al SIF.

La direccio o responsable legal de l'entitat revisara i signara la documentacio que correspongui.

## 13.1. Governanca interna del SIF

El SIF PrisMa es un sistema desenvolupat internament per a us propi de l'Associacio PrisMa.

La governanca interna es defineix aixi:

```text
Titularitat i us del sistema:
Associacio PrisMa

Responsable tecnica, funcional, documental i de desenvolupament:
Meriem Abjil Bajja

Responsable de decisio tecnica i posada en operativa:
Meriem Abjil Bajja

Responsable legal / direccio:
Adam Carmona, en representacio de l'entitat

Persones autoritzades per generar rectificatives i operacions fiscals sensibles des de la intranet:
Adam Carmona
Pablo Martori Delupi
Meriem Abjil Bajja, quan correspongui per tasques tecniques o de supervisio
```

La responsable tecnica i funcional defineix l'arquitectura, fluxos, model de dades, integracions, criteris de posada en marxa i canvis de versio. La titularitat legal i l'us del sistema corresponen a l'Associacio PrisMa.

## 13.2. Rols i responsabilitats

Els rols documentats no limiten la feina real de la responsable tecnica. Serveixen per deixar clar qui fa cada funcio dins del projecte i qui assumeix la titularitat legal del sistema.

| Rol | Persona / entitat | Funcions |
| --- | --- | --- |
| Entitat titular i obligada tributaria | Associacio PrisMa | Utilitza el SIF per emetre factures pròpies. Assumeix l'us organitzatiu del sistema. |
| Responsable legal / direccio | Adam Carmona | Representacio de l'entitat, revisio i signatura de documentacio quan correspongui. |
| Responsable tecnica del projecte | Meriem Abjil Bajja | Arquitectura, desenvolupament, integracio, BD, SIF, fluxos, documentacio tecnica. |
| Responsable funcional del projecte | Meriem Abjil Bajja | Definicio de casos, fluxos d'intranet, ecommerce, pagaments, rectificatives i operativa. |
| Responsable documental | Meriem Abjil Bajja | Preparacio i manteniment de documentacio interna, tecnica, AEAT i declaracio responsable. |
| Responsable de posada en operativa | Meriem Abjil Bajja | Decideix quan el SIF esta tecnicament preparat i quan s'activen canals cap al SIF. |
| Responsable de manteniment tecnic | Meriem Abjil Bajja | Correccions, versions, incidencies, revisio de logs i evolucio del SIF. |
| Operadors de rectificatives / operacions sensibles | Adam Carmona, Pablo Martori Delupi, Meriem Abjil Bajja | Generacio o supervisio de rectificatives, devolucions, baixes, saldos i incidencies fiscals segons permisos. |
| Hosting / infraestructura | Comvive / proveidor servidor | Infraestructura de servidor, segons contracte o servei existent. La configuracio funcional del subdomini `pay.prisma.cat` i SSL la gestiona la responsable tecnica del projecte. |
| Certificat digital / apoderament AEAT | Certificat digital de l'entitat, pendent de disponibilitat/configuracio tecnica | Gestio del certificat o apoderament necessari per l'enviament VERI*FACTU. |

Nota: que Meriem Abjil Bajja assumeixi la direccio tecnica, funcional i operativa del projecte no implica, per defecte, que actuï com a productora externa persona fisica. El sistema es documenta com a desenvolupament intern per a us propi de l'Associacio PrisMa, llevat que en el futur es decideixi formalment una altra figura.

## 13.3. Productor/titular intern i contacte tecnic

Per a la documentacio interna i la declaracio responsable, el criteri de treball es:

```text
Productor/titular intern del sistema:
Associacio PrisMa

Obligat tributari usuari del sistema:
Associacio PrisMa

Responsable tecnica, funcional, documental i de desenvolupament:
Meriem Abjil Bajja

Responsable legal / direccio que revisa o signa quan correspongui:
Adam Carmona o la persona que representi formalment l'entitat
```

Quan la normativa parla de productor del sistema informatic, aquest projecte documenta Associacio PrisMa com a productora/titular interna del SIF desenvolupat per a us propi.

Meriem Abjil Bajja queda identificada com a responsable tecnica i contacte intern del projecte. Aquesta identificacio no s'ha de confondre amb una comercialitzacio externa del SIF ni amb una responsabilitat com a productora externa persona fisica, tret que en el futur es decideixi formalment una altra estructura.

Per preparar la declaracio `1.0.0` signable caldra decidir si les dades personals de contacte tecnic de Meriem consten dins de la declaracio publica/signada o si es conserven nomes a l'expedient intern del projecte.

Matriu de decisio per a la declaracio `1.0.0`:

| Punt | Criteri actual | Estat abans de signar |
| --- | --- | --- |
| Productor/titular intern | Associacio PrisMa | Confirmar que es manté com a desenvolupament intern per a us propi. |
| Obligat tributari usuari | Associacio PrisMa | Confirmar dades fiscals i obligacio aplicable amb criteri intern o gestoria. |
| Contacte tecnic | Meriem Abjil Bajja | Decidir si consta a la declaracio signada o nomes a l'expedient intern. |
| Signant formal | Adam Carmona o representant formal de l'entitat | Confirmar carrec, NIF i facultats suficients. |
| Vistiplau tecnic | Meriem Abjil Bajja | Conservar annex o acta tecnica sense substituir la signatura de l'entitat. |
| Versio declarada | `1.0.0` | Nomes quan el paquet desplegat, BD, proves, certificat i evidencies coincideixin. |

## 13.4. Certificat digital, apoderament i secrets

El SIF necessita una configuracio d'identificacio electronica per operar amb AEAT.

Criteri de treball:

- prioritat: certificat digital de l'entitat Associacio PrisMa;
- alternativa si escau: apoderament o mecanisme equivalent admis per AEAT;
- el certificat, claus privades i secrets tecnics no han de quedar al codi font, repositori, webroot ni logs;
- el panell SIF ha de mostrar l'estat funcional del certificat o apoderament sense exposar secrets;
- abans d'activar `1.0.0`, cal provar l'ús del certificat/apoderament en l'entorn que correspongui i conservar evidencia;
- si el certificat caduca, no es configura o falla, s'ha de generar incidencia SIF abans que afecti factures productives.

En modalitat `VERI*FACTU`, aquesta configuracio s'entén com a autenticacio/identificacio per remetre registres i operar amb AEAT. No s'ha de confondre amb la signatura electronica XAdES de registres, que queda com a materia especifica de modalitat no `VERI*FACTU` o d'escenaris que ho exigeixin.

### 13.4.1. On s'ha de configurar el certificat per a la remissio AEAT

La remissio `VERI*FACTU` es una comunicacio automatica maquina a maquina mitjancant serveis web SOAP/XML. Per tant, el proces backend o worker del SIF que fa l'enviament ha de poder accedir tecnicament a un certificat electronic qualificat admès per AEAT i a la seva clau privada.

Cal distingir tres elements diferents:

| Element | Funcio | Ubicacio o tractament |
| --- | --- | --- |
| Certificat TLS de `pay.prisma.cat` | Protegeix la connexio HTTPS dels usuaris amb el servidor. | Configuracio del servidor web o proveidor de hosting. No identifica per si sol Associacio PrisMa davant AEAT. |
| Certificat client per AEAT | Autentica el remitent en la connexio de sortida del SIF cap als serveis web AEAT. | Ha d'estar disponible per al proces servidor autoritzat, juntament amb la clau privada, mitjancant un mecanisme segur. |
| Signatura de la declaracio responsable | Subscriu el document de certificacio de la versio del SIF. | No exigeix obligatoriament signatura electronica segons la FAQ AEAT; requereix signant, data i lloc. |

Model tecnic recomanat per al projecte:

- obtenir o confirmar un certificat qualificat de representant de persona juridica adequat per a Associacio PrisMa, o documentar un tercer representant/apoderat/col·laborador social admès;
- conservar una copia exportable que inclogui certificat i clau privada, normalment en format `PKCS#12` (`.p12` o `.pfx`), si el proveidor i la llibreria d'integracio ho admeten;
- si la llibreria SOAP/TLS exigeix `PEM`, fer la conversio nomes en un entorn controlat i protegir igualment certificat, clau i contrasenya;
- guardar el material criptografic fora del `webroot`, fora del repositori i amb permisos de lectura limitats exclusivament a l'usuari del worker SIF;
- guardar la contrasenya en un gestor de secrets o configuracio d'entorn protegida, mai al codi, SQL, logs o documentacio;
- configurar PHP/OpenSSL/SOAP o la llibreria HTTP perquè presenti el certificat client en la connexio TLS de sortida a AEAT;
- registrar al panell nomes metadades no secretes: subjecte/titular, emissor, numero de serie parcial o empremta, data de caducitat, entorn, ultima prova i estat;
- definir copia de seguretat xifrada, responsable de custodia, procediment de renovacio/rotacio i revocacio per perdua o compromís;
- separar configuracions i endpoints de prova i produccio, encara que l'autoritat certificadora permeti utilitzar el mateix certificat per autenticar-se;
- provar la connexio des del mateix servidor o entorn que executara el worker, no nomes des d'un navegador o ordinador personal.

El certificat no ha d'estar necessàriament importat al magatzem global del sistema operatiu. Pot quedar en un fitxer protegit fora del webroot, en un magatzem de certificats, en un gestor de secrets o en un HSM/key vault, sempre que el proces SIF pugui usar la clau privada sense exposar-la. La decisio final dependra de les capacitats del hosting Comvive i de la llibreria PHP escollida.

Comprovacions a fer amb el proveidor de servidor abans de decidir el desplegament:

1. El PHP del servidor disposa d'OpenSSL i client SOAP/HTTP compatible amb certificat client TLS?
2. Es pot fer una connexio de sortida als endpoints AEAT i processar els WSDL oficials?
3. Es pot guardar un `.p12`/`.pfx` o `PEM` fora del directori public amb permisos restringits?
4. Es poden definir secrets d'entorn sense exposar-los al panell public ni al repositori?
5. Quin usuari executara el worker/cua AEAT i com es limitaran els permisos de lectura?
6. Com es fara la copia de seguretat xifrada, la renovacio i la substitucio sense aturar o exposar el servei?

Fonts oficials de criteri:

- AEAT, FAQ sistemes `VERI*FACTU`: la remissio es maquina a maquina i pot autenticar-se amb certificat qualificat del titular, representant, apoderat o col·laborador social.
- AEAT, descripcio dels serveis web: la remissio usa serveis SOAP/XML i el remitent ha de disposar d'un certificat electronic qualificat reconegut.
- AEAT, FAQ d'empreses de desenvolupament: per provar i operar el SIF cal disposar d'un certificat qualificat valid i admès instal·lat o configurat de forma utilitzable pel sistema.

Evidencia minima abans de `1.0.0`:

- metode triat: `CERT_ENTITAT`, `APODERAMENT` o mecanisme equivalent admès;
- subjecte/titular i emissor del certificat o representacio documentada;
- caducitat, estat i entorn on s'ha provat;
- prova feta des del mateix servidor, usuari o worker que fara la remissio;
- resultat de prova i incidencia SIF si falla;
- referencia segura al material de configuracio, sense exposar clau, contrasenya ni fitxer privat.

## 13.5. Criteris interns pendents de validacio externa

El xat antic va deixar constancia que PrisMa no disposava en aquell moment d'un assessor fiscal dedicat al projecte. Per tant, la documentacio pot fixar criteris interns de treball, pero els punts interpretatius s'han de mantenir com a pendents de validacio externa si mes endavant es disposa de gestoria, assessor o revisio especialitzada.

Punts marcats com a especialment sensibles:

- tipus de rectificativa per canvi de NIF, rao social o dades fiscals;
- tractament de factura abans de cobrament quan queda impagada;
- compensacions, saldos a favor i ajustos manuals;
- cursos exempts d'IVA i mencio exacta d'exempcio;
- privacitat en el text visible de descomptes o situacions personals sensibles.

Mencio d'exempcio IVA indicada per PrisMa com a text actual:

```text
Factura exempta d'IVA d'acord amb l'article 20.1.9 de la Llei 37/1992, de 28 de desembre, de l'Impost sobre el Valor Afegit (formacio i reciclatge professionals realitzats per entitats privades autoritzades per a l'exercici de les activitats).
```

Aquest text es conserva com a criteri intern conegut, pendent de contrastar si canvia la normativa, el tipus de servei o el criteri fiscal aplicable.

## 14. Declaracio Responsable

La declaracio responsable del sistema informatic haura d'estar disponible de forma visible i accessible dins del propi sistema o en document electronic associat.

Ha de declarar, per la versio concreta del SIF, que el sistema compleix la normativa aplicable.

La declaracio signable es mantindra en un document separat.

### 14.1. Cicle de vida de la declaracio

La declaracio responsable actual es un borrador documental i no s'ha de signar encara.

Cicle decidit:

```text
0.1-BORRADOR -> document viu durant desenvolupament
0.2-BORRADOR -> quan s'incorpori Redsys/pay.prisma.cat
0.3-BORRADOR -> quan s'incorporin PDF/QR/XML i proves principals
1.0.0 -> primera versio productiva signable
1.1.x -> canvis rellevants posteriors, amb nova declaracio o annex si afecta compliment
```

No cal signar cada petit canvi de desenvolupament. La signatura s'ha de fer quan existeixi una versio concreta, instal·lada, verificable i preparada per entrar en produccio.

### 14.2. Condicions abans de signar

Abans de signar la declaracio responsable `1.0.0`, cal tenir:

- versio exacta del SIF;
- revisio normativa AEAT/BOE datada i conservada a l'expedient;
- domini i subdomini configurats;
- components finals identificats;
- BD fiscal en estat productiu;
- endpoints i processos del SIF verificats;
- generacio PDF/QR/XML definida;
- certificat digital de l'entitat o apoderament configurat i provat des de l'entorn real del worker;
- correspondencia dels camps fiscals minims amb taules internes, payload XML/AEAT i proves;
- rol auditor/AEAT de nomes lectura preparat sense permisos d'escriptura ni secrets;
- proves principals executades i conservades;
- dades completes de signatura: data, lloc, NIF de la persona signant per direccio i carrec;
- declaracio responsable accessible dins del propi SIF.

Quan una versio signada evolucioni amb canvis rellevants de compliment, s'haura d'obrir una nova versio documental i vincular-la al registre de versions del SIF.

## 15. Referencies Oficials

Estat executable revisat el 2026-09-23:
[annex d'integració AEAT](annex-integracio-aeat.md). Inclou alta, anul·lació,
subsanació, huella, XSD, certificat, worker, retries, respostes i expedient.
El transport disponible és una candidata limitada a proves externes. Les
proves locals no acrediten remissió real ni converteixen `1.0.0` en signable.
`HASH_FACT` conserva el digest JSON intern; la huella oficial és dins el
snapshot `PAYLOAD_JSON.aeat.record.Huella`. No s'han de confondre.

- AEAT - Certificacion de los sistemas informaticos: https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/cuestiones-generales/certificacion-sistemas-informaticos_.html
- BOE - Orden HAC/1177/2024, articulo 15, contenido de la declaracion responsable: https://www.boe.es/buscar/act.php?id=BOE-A-2024-22138
- AEAT - Ejemplos de declaraciones responsables: https://sede.agenciatributaria.gob.es/static_files/Sede/Tema/IVA/Verifactu/EjemplosDeclaracionResponsable%28V0.5.1%29.pdf
- AEAT - Modalidades de cumplimiento VERI*FACTU / NO VERI*FACTU: https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/cuestiones-generales/modalidades-cumplimiento-obligaciones.html
- AEAT - Nota informativa de ampliacion de plazo de adaptacion SIF: https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/nota-informativa-ampliacion-plazo-adaptacion-facturacion.html
- AEAT - Quienes estan obligados y que operaciones se incluyen: https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/cuestiones-generales/quienes-estan-obligados-que-operaciones-incluyen.html
- AEAT - FAQ sistemas VERI*FACTU y modelo 036: https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/preguntas-frecuentes/sistemas-verifactu.html
