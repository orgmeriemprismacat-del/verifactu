# UC-116 — diagrames d'activitat ACTUAL i FINAL per pàgina i apartat

**Revisió de contingut:** 22/09/2026. **Font PHP/JS:** main @ e71958b3026549bde09fb4b25f2ec3ba370937ec. **Cobertura del UC-116:** pàgina informativa de descomptes, formulari públic de curs normal (selecció i aportació de justificant), pantalla de confirmació derivada, pàgina de validació intranet (consulta/decisió). L'apartat de resguard de recent titulació de la mateixa pàgina és una acció DIFERENT: enllaç al seu UC; no reinterpretar-la com si fos UC-116. **Etiqueta ACTUAL:** observació estàtica del repo, no prova de desplegament. **Etiqueta FINAL:** contracte proposat, no implementat. [Fitxa UC-116](../06-fitxes-funcionals/uc-116.md#22-especificacio-consolidada-uc-116--codi-actual-i-contracte-final) · [auditoria](00-auditoria-casos-pendents-lot-05-uc-116-2026-09-22.md).

## P01 · Pàgina pública de descomptes — cinc apartats

**Fonts:** [pàgina](../../codi-drive/web-actual/pagina_descomptes.php), [endpoint de render](../../codi-drive/web-actual/ajax/mostrar_pagina_descomptes.php), [classe Descomptes.php](../../codi-drive/web-actual/Descomptes.php#L185-L265). Mapa d'apartats: 1 exalumne PrisMa (identificació interna), 2 Carnet Jove (marcar casella i càlcul), 3 socials (document + validació posterior), 4 USOC (comprovació externa de l'afiliació), 5 grups/centres (tarifes per nombre i enllaç a inscripció grupal). No totes les famílies exigeixen l'upload UC-116.

### P01 — ACTUAL

```plantuml
@startuml
title P01 Descomptes | ACTUAL | cinc apartats informatius
start
:Obrir pàgina de descomptes;
:Renderitzar preus i 5 apartats;
if (Apartat triat?) then (Alumne PrisMa)
 :Llegir regla de reconeixement com a alumne;
 :Informar del recàlcul en emplenar DNI;
elseif (Carnet Jove) then (Carnet Jove)
 :Informar que es marca casella i es recalcula preu;
elseif (Socials) then (Socials)
 :Informar de document justificatiu i revisió per PrisMa;
elseif (USOC) then (USOC)
 :Informar de comprovació d'afiliació per USOC;
else (Grups)
 :Mostrar trams de grup i enllaç al formulari grupal;
endif
:Anar al formulari corresponent si la persona ho decideix;
note right
  Aquesta pàgina NO desa justificants,
  NO aprova drets i NO acredita
  una custòdia o un cobrament.
end note
stop
@enduml
```

### P01 — FINAL

```plantuml
@startuml
title P01 Descomptes | FINAL | necessitat de prova per regla
start
:Mostrar les cinc categories i preus vigents de cada producte;
:Identificar producte/edició i dret invocat;
if (La regla exigeix prova documental?) then (Sí)
 :Informar del document mínim i de la revisió;
 :Enllaçar al formulari/expedient privat de la inscripció;
else (No)
 :No sol·licitar un document personal innecessari;
endif
if (Cal verificació per tercer?) then (Sí)
 :Explicar el procediment autoritzat sense exposar la prova;
endif
:Mostrar condicions i accés al flux de sol·licitud real;
stop
@enduml
```

## P02 · Pàgina pública d'inscripció a un curs — apartat «Descomptes a aplicar»

**Fonts:** [shell pagina_inscripcions.php](../../codi-drive/web-actual/pagina_inscripcions.php) carrega [mostrarInscripcions.min.js](../../codi-drive/web-actual/js1619773569/mostrarInscripcions.min.js); [ajax/mostrar_inscripcio.php](../../codi-drive/web-actual/ajax/mostrar_inscripcio.php) crea [InscripcioCurs.php](../../codi-drive/web-actual/InscripcioCurs.php#L487-L585). La vista mostra caselles exclusives de descompte i un input ocult `#carnet`; el JS el fa visible per discapacitat, família nombrosa, monoparental i una altra categoria sensible representada per `TIPUS_DESC=8`. Per USOC no mostra aquest input en el camí inspeccionat. Exalumne/Carnet Jove són altres comprovacions comercials. `validarFileCarnet()` exigeix que hi hagi un fitxer quan una de les quatre caselles el requereix: **és validació JS de presència, no validació servidor del document**.

### P02-A — ACTUAL · seleccionar descompte i fitxer

```plantuml
@startuml
title P02-A Formulari inscripcions | ACTUAL | selecció i validació client
start
:Renderitzar formulari curs, dades, descomptes i input carnet ocult;
:Persona tria casella de descompte;
:JS desmarca/invalida alternatives no acumulables;
if (Categoria seleccionada requereix fitxer al JS?) then (Sí)
 :Mostrar input carnet;
 :Persona adjunta fitxer;
 if (Existeix fitxer seleccionat?) then (Sí)
  :Eliminar error de camp;
 else (No)
  :Mostrar error de fitxer obligatori;
 endif
else (No)
 :Mantenir input carnet ocult;
endif
:Recalcular preu al navegador;
:Validar resta de camps al navegador;
if (Errors de formulari?) then (Sí)
 :Mostrar modal errors i no enviar alta;
else (No)
 :Iniciar comprovació de duplicat i alta de curs;
endif
stop
@enduml
```

### P02-A — FINAL · tria de dret i evidència mínima

```plantuml
@startuml
title P02-A Formulari inscripcions | FINAL | drets i preu servidor
start
:Mostrar opcions de descompte compatibles amb producte/edició;
:Persona tria un dret;
:Servidor valida regla, vigència, incompatibilitats i destinatari;
if (Regla requereix evidència?) then (Sí)
 :Sol·licitar fitxer mínim pel canal d'upload segur;
 :Comprovar permisos, tipus real, mida i integritat al servidor;
 if (Prova rebuda i custodiada?) then (Sí)
  :Enllaçar prova a UUID_VALIDATION i ID_INSC/operació;
  :Marcar dret PENDENT DE REVISIÓ, no aprovat;
 else (No)
  :Mostrar incidència/reintent sense afirmar custòdia;
 endif
else (No)
 :Documentar font de comprovació interna o externa;
endif
:Recalcular/import congelar al servidor segons estat del dret;
:No activar TPV amb preu condicionat a validació pendent;
stop
@enduml
```

## P02-B · Apartat «Confirmar inscripció» i càrrega asíncrona del justificant

**Fonts:** [JS línies 2113–2126 i 2198–2309](../../codi-drive/web-actual/js1619773569/mostrarInscripcions.min.js#L2113-L2309), [enviarInscripcio.php](../../codi-drive/web-actual/ajax/enviarInscripcio.php#L585-L604), [endpoint de prova](../../codi-drive/web-actual/ajax/enviarImatgeCarnetInscripcio.php#L12-L75) i [pàgina de confirmació](../../codi-drive/web-actual/pagina_confirmacio_inscripcio.php). `enviarInscripcio.php` estableix `VALID_DESC=0` quan `TIPUS_DESC` és 4–8; l'alta llegat és prèvia a l'upload. La crida a l'upload es fa després de rebre resposta d'alta per a qualsevol `tipusCurs != 'S'`, amb `$('#carnet')[0].files[0]` encara que no s'hagi seleccionat fitxer. El JS té callback `success`, però **no comprova que la resposta indiqui custòdia** i no s'ha identificat `fail` específic d'aquell upload en el fragment. El confirmatori també pot dependre de l'upload separat de resguard recent titulat, que correspon a un UC diferent.

### P02-B — ACTUAL · dos commits independents

```plantuml
@startuml
title P02-B Alta web | ACTUAL | inscripció i upload separats
start
:Persona confirma l'alta;
:JS consulta duplicat i executa GET enviarInscripcio.php;
if (Resposta d'alta considerada correcta?) then (Sí)
 :PHP insereix inscripcions amb TIPUS_DESC i VALID_DESC inicial;
 if (Curs de tipus S?) then (Sí)
  :Redirigir a confirmació sense upload carnet;
 else (No)
  :JS pren fitxer carnet, fins i tot si no hi ha cap seleccionat;
  :POST enviarImatgeCarnetInscripcio.php en petició separada;
  if (Callback HTTP success?) then (Sí)
   :Opcionalment tramitar resguard d'una altra promoció;
   :Redirigir a pàgina de confirmació;
  else (No)
   :No hi ha recuperació específica de l'upload en el fragment inspeccionat;
  endif
 endif
else (No)
 :Mostrar error o inscripció duplicada;
endif
note right
  Alta inscripcions i fitxer NO són atòmics.
  La resposta success pot contenir 0 i
  no acreditaria custòdia.
end note
stop
@enduml
```

### P02-B — FINAL · alta comercial amb obligació documental traçable

```plantuml
@startuml
title P02-B Alta web | FINAL | expedient i comprovació abans de TPV
start
:Persona confirma sol·licitud amb ID d'operació idempotent;
:Servidor comprova duplicat, regla i import autoritzat;
if (Regla requereix prova?) then (Sí)
 :Lligar fitxer privat validat a expedient i inscripció;
 if (Custòdia verificada?) then (Sí)
  :Crear o reutilitzar sol·licitud PENDENT DE VALIDACIÓ;
 else (No)
  :Deixar sol·licitud pendent de document amb reintent;
 endif
else (No)
 :Crear o reutilitzar alta segons regla i elegibilitat;
endif
:Mostrar estat real i només les instruccions econòmiques autoritzades;
:No comunicar aprovació ni factura/cobrament inexistents;
:No deixar cap prova personal al webroot o Git;
stop
@enduml
```

## P03 · Intranet /alumnes/validar-descomptes/ — apartat de lectura del justificant

**Fonts:** [shell](../../codi-drive/intranet-actual/alumnes-validar-descomptes.php), [mostrarMain.php](../../codi-drive/intranet-actual/ajax/mostrarMain.php), [Intranet.php, mostrarPage i __mostrarPage_Alumnes_ValidarDescomptes](../../codi-drive/intranet-actual/Intranet.php#L1401-L1404) i [JS de pàgina](../../codi-drive/intranet-actual/js/alumnes-validar-descomptes.js). `mostrarPage` construeix dos apartats independents en aquesta ruta: **recent titulació** i **validació de descomptes**. La consulta `cnsAlumnDescNoValidat` filtra inscripcions `VALID_DESC=0` i estats d'inscripció 0/1. El render mostra nom, cognoms, identificador personal, correu, categoria, un marcador SÍ/NO i botó ENVIA. Per TIPUS_DESC 5–8 prova extensions sobre una URL derivada d'edició+curs+documentació personal amb `url_exists` i mostra un enllaç al fitxer; per 4 mostra etiqueta USOC sense aquest enllaç. Per 8, el render utilitza per error la mateixa etiqueta gràfica que 7: corregir al canal final, però NO inventar que la prova està absent.

### P03-A — ACTUAL · consulta de documents per URL directa

```plantuml
@startuml
title P03-A Intranet | ACTUAL | consulta i enllaç a document
start
:Obrir apartat validar descomptes;
:Comprovar sessió i rol de visualització de la pàgina;
if (Rol visualització permès?) then (Sí)
 :Consultar inscripcions amb VALID_DESC=0 i estat 0/1;
 if (Hi ha pendents?) then (Sí)
  :Renderitzar taula amb categoria i botó ENVIA;
  if (TIPUS_DESC és 5, 6, 7 o 8?) then (Sí)
   :Construir base URL directa des de camps personals;
   :Comprovar existència de variants d'extensió via HEAD;
   :Mostrar link a prova original si s'ha resolt ruta;
   :Operador obre document amb link, sense autorització de lectura per fitxer visible al codi;
  else (No)
   :Mostrar etiqueta de validació USOC sense prova local;
  endif
 else (No)
  :Mostrar No hi ha resultats;
 endif
else (No)
 :Mostrar missatge de permís denegat a la pàgina;
endif
stop
@enduml
```

### P03-A — FINAL · lectura privada auditada

```plantuml
@startuml
title P03-A Intranet | FINAL | autorització d'accés a cada prova
start
:Operador obre expedients pendents autoritzats;
:Servidor valida rol, finalitat i abast per inscripció;
if (Dret a visualitzar expedient?) then (Sí)
 :Mostrar categoria necessària, estat i metadata mínima;
 if (Operador sol·licita veure prova?) then (Sí)
  :Backend comprova permís de LECTURA del document i vigència;
  :Verificar hash i llegir bytes des de storage privat;
  if (Document íntegre i accessible?) then (Sí)
   :Servir contingut pel canal autoritzat i auditar accés;
  else (No)
   :Mostrar prova absent/corrupta i obrir incidència;
  endif
 endif
else (No)
 :Denegar i auditar sense exposar dades o URL;
endif
stop
@enduml
```

## P03-B · Intranet, apartat «Validar descomptes» — decisió i comunicació

**Fonts:** [JS línies 28–94](../../codi-drive/intranet-actual/js/alumnes-validar-descomptes.js#L28-L94), [wrapper AJAX](../../codi-drive/intranet-actual/ajax/alumnes/sendMsgValidatCurosDescomptes.php) i [Intranet::sendMsgValidatCurosDescomptes línies 15530–16090](../../codi-drive/intranet-actual/Intranet.php#L15530-L16090). El JS commuta SÍ/NO i, en prémer ENVIA, envia GET `idInsc/verificat`. El mètode actual: consulta dades d'inscripció i preus; si s'accepta, calcula preu descomptat per TIPUS_DESC 4–8 i estableix `VALID_DESC=1`; **en aquesta branca no es veu UPDATE d'`A_PAGAR`**. Si es denega, compara amb elegibilitat d'exalumne; escriu `TIPUS_DESC`, `VALID_DESC=2`, `A_PAGAR` segons el preu alternatiu; encara després hi ha un segon UPDATE de `VALID_DESC=2`. Calcula textos per fraccionament i una variant especial USOC i construeix correus a secretaria i a la persona. La creació d'objectes de correu no prova el resultat SMTP. No es veu control de rol específic per acció, condició d'estat/versió d'operació ni comprovació de factura emesa en aquest mètode.

### P03-B — ACTUAL · aprovació/denegació

```plantuml
@startuml
title P03-B Intranet | ACTUAL | decisió, preu, estats i missatge
start
:Revisor canvia SÍ/NO al navegador;
:Premer ENVIA i executar GET amb idInsc/verificat;
:Wrapper recupera objectes de sessió i delega a Intranet;
:Consultar inscripció, edició i preus;
if (verificat == 1?) then (Sí)
 :Consultar preu reduït segons TIPUS_DESC 4..8;
 :Establir VALID_DESC = 1 a inscripcions;
 note right
  Aquest camí no fa UPDATE A_PAGAR
  en el mètode inspeccionat.
 end note
else (No)
 :Comprovar si persona és exalumne;
 if (És exalumne?) then (Sí)
  :Assignar preu d'exalumne i TIPUS_DESC=1;
 else (No)
  :Assignar preu normal i TIPUS_DESC=0;
 endif
 :UPDATE inscripcions TIPUS_DESC, VALID_DESC=2, A_PAGAR;
 :UPDATE inscripcions VALID_DESC=2;
endif
:Compondre text de pagament segons fracció i cas comercial;
:Compondre correus a secretaria i persona;
:Retornar OK;
:JS mostra modal sense verificar efectes fiscals o SMTP;
stop
@enduml
```

### P03-B — FINAL · decisió versionada i aplicació comercial/fiscal condicionada

```plantuml
@startuml
title P03-B Intranet | FINAL | revisió segura i efectes separats
start
:Revisor amb rol específic obre validació vigent;
:Servidor comprova actor/recurs, prova íntegra i regla de preu;
if (Autoritzat, evidència aplicable i versió vigent?) then (Sí)
 :Registrar decisió ACCEPTADA o DENEGADA amb motiu mínim i actor;
 :Recalcular preu comercial exacte segons producte i dret;
 if (Ja hi ha factura per la prestació?) then (Sí)
  :Preservar factura original;
  :Classificar necessitat de rectificativa/saldo/retorn per UC corresponents;
 else (No)
  :Actualizar oferta/obligació pendent abans d'emetre;
 endif
 :Sincronitzar estat llegat de manera controlada;
 :Notificar resultat real de forma idempotent amb text genèric;
else (No)
 :Denegar acció/obrir incidència sense canviar factura ni import;
endif
:Separar retenció de la prova de la conservació fiscal;
stop
@enduml
```

## Matriu d'accions i límits

| Pàgina / apartat | Control → punt d'entrada → classe/taula | UC principal i connexions | Estat documental |
| --- | --- | --- | --- |
| P01 / Descomptes (5 seccions) | pagina_descomptes.php → Descomptes::mostrarPagina → preus/catàleg | UC-116 només secció que requereix document; UC de preus/descomptes i grups | Contrastat al PHP de main. |
| P02 / Selecció i prova | pagina_inscripcions.php → JS → ajax/mostrar_inscripcio.php → InscripcioCurs.php; JS `validarFileCarnet` | UC-116, UC-20b i UC-107 quan duplicitat | Contrastat al PHP i JS de main. |
| P02 / Alta, upload, confirmació | JS → enviarInscripcio.php → inscripcions; després JS → enviarImatgeCarnetInscripcio.php → webroot; després confirmació | UC-116 per prova; UC alta/validació/preu/fiscal separats | Contrastat al PHP/JS; resultat runtime del correu/storage no provat. |
| P03 / Consulta de proves | shell intranet → mostrarMain → Intranet::mostrarPage / __mostrarPage_Alumnes_ValidarDescomptes; links per TIPUS_DESC 5..8 | UC-116, accés i decisió UC de validació de descomptes | Contrastat fins a render PHP inclòs. |
| P03 / Decisió | JS `.validat` → wrapper AJAX → Intranet::sendMsgValidatCurosDescomptes → inscripcions.VALID_DESC, TIPUS_DESC/A_PAGAR (segons branca), correus | UC-116 vincula prova; modificació econòmica i notificació als UC corresponents | Contrastat el cos PHP; no prova de deployed/SMTP/BD. |
| P03 / recent titulat | `#inscripcions_recent_titulat` → `validatResguard`, endpoint diferent | **Fora de l'abast UC-116**; consultar UC propi, no duplicar | Identificat com a apartat diferent; no reauditat aquí. |

**Tancament RM-037 en l'abast UC-116:** els apartats actuals visibles a les fonts inspeccionades tenen diagrama ACTUAL/FINAL, incloent la frontera amb els altres UC. **No s'afirma:** haver inspeccionat totes les altres pantalles i rutes del repositori, ni el desplegament, ni executat PlantUML contra un renderitzador, ni verificada l'aplicació d'aquest disseny.
