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

Segons la nota informativa de l'AEAT actualitzada el 26/03/2026, els terminis generals d'adaptacio son:

- entitats que presenten Impost sobre Societats: abans de l'1 de gener de 2027;
- resta d'obligats tributaris afectats: abans de l'1 de juliol de 2027.

Per prudencia documental, la data aplicable a PrisMa s'haura de confirmar abans de tancar el calendari final de posada en produccio.

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

## 13.3. Criteris interns pendents de validacio externa

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
- domini i subdomini configurats;
- components finals identificats;
- BD fiscal en estat productiu;
- endpoints i processos del SIF verificats;
- generacio PDF/QR/XML definida;
- certificat digital de l'entitat o apoderament configurat;
- proves principals executades i conservades;
- dades completes de signatura: data, lloc, NIF de la persona signant per direccio i carrec;
- declaracio responsable accessible dins del propi SIF.

Quan una versio signada evolucioni amb canvis rellevants de compliment, s'haura d'obrir una nova versio documental i vincular-la al registre de versions del SIF.

## 15. Referencies Oficials

- AEAT - Certificacion de los sistemas informaticos: https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/cuestiones-generales/certificacion-sistemas-informaticos_.html
- BOE - Orden HAC/1177/2024, articulo 15, contenido de la declaracion responsable: https://www.boe.es/buscar/act.php?id=BOE-A-2024-22138
- AEAT - Ejemplos de declaraciones responsables: https://sede.agenciatributaria.gob.es/static_files/Sede/Tema/IVA/Verifactu/EjemplosDeclaracionResponsable%28V0.5.1%29.pdf
- AEAT - Modalidades de cumplimiento VERI*FACTU / NO VERI*FACTU: https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/cuestiones-generales/modalidades-cumplimiento-obligaciones.html
- AEAT - Nota informativa de ampliacion de plazo de adaptacion SIF: https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/nota-informativa-ampliacion-plazo-adaptacion-facturacion.html
- AEAT - Quienes estan obligados y que operaciones se incluyen: https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/cuestiones-generales/quienes-estan-obligados-que-operaciones-incluyen.html
- AEAT - FAQ sistemas VERI*FACTU y modelo 036: https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/preguntas-frecuentes/sistemas-verifactu.html
