# Cas d'ús específic — Pujada d'aules obertes al campus Moodle

**ID funcional provisional:** CAND-UC-MOODLE-AO-01. Cas independent de [Pujada d'alumnes als cursos](uc-moodle-pujada-alumnes-fitxa-activitats.md) i d'UC-113 per decisió de negoci. **Codi auditat:** `main` a `e71958b3026549bde09fb4b25f2ec3ba370937ec`, 22/09/2026. No és automàticament el mateix que `INSC CURS=1`: aquí l'escriptor modifica `PERENNE`, sobre inscripcions ja matriculades a curs. La pujada real al campus s'ha de contrastar amb l'importador de matrícules en lot **EXISTENT**; el seu executor concret no queda identificat només pel PHP d'aquesta pantalla.

## 1. Fitxa funcional específica

| Element | Contracte basat en codi i decisions |
| --- | --- |
| Actor i canal | Gestió acadèmica/operador amb accés a Intranet → Cursos → Fi de cursos → Pujar aules obertes; [pàgina](../../codi-drive/intranet-actual/cursos-fi-cursos-pujar-aules-obertes.php), [JS](../../codi-drive/intranet-actual/js/cursos-fi-cursos-pujar-aules-obertes.js). |
| Punt d'entrada | [`Intranet::__mostrarPage_Inici_Pujar_AO`](../../codi-drive/intranet-actual/Intranet.php#L3274-L3301) detecta candidates i ofereix botó. [`__mostrarPage_Cursos_Pujada_AO`](../../codi-drive/intranet-actual/Intranet.php#L3303-L3461) mostra la pàgina amb curs, dades de contacte, tutor, certificat, preu/pagament/deute i acció per participant. |
| Precondicions | Inscripcions ja existents i estat de curs; [`updPujadaAO`](../../codi-drive/intranet-actual/Intranet.php#L1073-L1074) requereix `INSC CURS=1` i `PERENNE=0`, a més de USUARI,CURS,ANY,MES. No crea una nova inscripció a PrisMa. |
| Selecció | [JS L38–63](../../codi-drive/intranet-actual/js/cursos-fi-cursos-pujar-aules-obertes.js#L38-L63) alterna Pujar/No Pujar. La consulta mostra import, pendent i certificat, però aquestes dades no són ordre de cobrar ni d'emetre factura. |
| Confirmació | [JS L64–141](../../codi-drive/intranet-actual/js/cursos-fi-cursos-pujar-aules-obertes.js#L64-L141) crea fitxer i envia POST independent per cada fila marcada. [`crearFitxerAO.php`](../../codi-drive/intranet-actual/ajax/inici/crearFitxerAO.php) crida [`Intranet::crearFitxerAO`](../../codi-drive/intranet-actual/Intranet.php#L3484-L3513). |
| Escriptura a BD i fitxer | [`pujarAulesObertes.php`](../../codi-drive/intranet-actual/ajax/inici/pujarAulesObertes.php) crida [`Intranet::pujar_AO`](../../codi-drive/intranet-actual/Intranet.php#L3515-L3565), que fa `UPDATE inscripcions SET PERENNE='1'` **abans** d'obrir el fitxer i escriure la fila CSV. La fila es genera amb USUARI, NOM, COGNOMS, EMAIL, POBLACIO, LANG, CURS; contrastar el shortname Moodle real abans de donar per confirmat el destí. |
| Sortida | [JS L121–136](../../codi-drive/intranet-actual/js/cursos-fi-cursos-pujar-aules-obertes.js#L121-L136): missatges per fila i enllaç a fitxer després de respondre la darrera posició del bucle. No queda acreditat que totes les peticions anteriors hagin acabat ni que s'hagi importat el fitxer. |
| Resultat real diferenciat | `PERENNE=1` és un estat del llegat; **NO** és una confirmació de matrícula Moodle, d'accés vigent, de certificat ni de pagament. La conciliació acadèmica correspon a UC-129. |
| Facturació | La pujada d'aula oberta no crea cap factura, `CHARGE`, `REFUND` ni alteració de la factura històrica; si hi ha deute, aplicar política UC-95/124 separada de la matrícula acadèmica. |

## 2. Pàgina i apartats independents (RM-037)

| Apartat | Dades visibles, decisió i efecte | Diagrames |
| --- | --- | --- |
| AO01 Accés | Botó d'inici i carregar edicions pendents | ACTUAL/FINAL |
| AO02 Llistat | Participants, tutor, certificat, import/pendent/reclamació i marques | ACTUAL/FINAL |
| AO03 Confirmació | Permís al navegador, crear fitxer i tractar errors inicials | ACTUAL/FINAL |
| AO04 Fila | POST, `PERENNE=1`, creació de línia CSV i fallades | ACTUAL/FINAL |
| AO05 Resultat | Modal, enllaç CSV, importació externa i verificació a Moodle | ACTUAL/FINAL; destí de lot existent per mapar |

## 2.1 Diagrames d'activitat de la PÀGINA COMPLETA — ACTUAL i FINAL

Aquest recorregut és propi de la URL/pàgina d'aules obertes i **NO** del generador de fitxer d'inici de cursos `/cursos/inici-cursos/generar-fitxer-pujada-alumnes/`, que disposa de la seva fitxa independent.

### P-MOODLE-AO-01 — Pàgina ACTUAL completa

```plantuml
@startuml
title Pujar aules obertes | PAGINA COMPLETA ACTUAL
start
:Obrir pagina intranet fi de cursos pujar aules obertes;
if (Sessio de pagina valida?) then (Si)
  :JS carrega ajax/mostrarMain amb pathname;
  if (Rol de visualitzacio autoritzat?) then (Si)
    :Consultar exPujadaAO i recuperar inscripcions EXISTENTS;
    if (Hi ha candidates?) then (Si)
      :Mostrar curs, participant, tutor, certificat, pagament i pendent;
      :Mostrar Pujar/No Pujar per fila;
      :Operador selecciona les persones per aula oberta;
      if (Confirma amb permis d'edicio al JS?) then (Si)
        :POST crearFitxerAO.php;
        if (Fitxer inicial creat?) then (Si)
          if (Hi ha persones marcades?) then (Si)
            while (Queda alguna fila marcada?) is (Si)
              :POST pujarAulesObertes.php sense esperar altres POST;
              :UPDATE inscripcions SET PERENNE=1;
              :Afegir fila al CSV despres de l'UPDATE;
              :Mostrar resposta/error individual;
            endwhile (No)
            :Afegir enllac CSV en la resposta de la darrera posicio;
          else (No)
            :Mostrar avís cap canvi seleccionat;
          endif
        else (No)
          :Mostrar error en crear fitxer;
        endif
      else (No)
        :No pujar o mostrar denegacio local;
      endif
    else (No)
      :Mostrar estat buit;
    endif
  else (No)
    :Mostrar denegacio de visualitzacio;
  endif
else (No)
  :Redirigir a inici intranet;
endif
:Cap resultat de carrega real Moodle verificat en aquesta pagina;
stop
@enduml
```

### P-MOODLE-AO-01 — Pàgina FINAL, proposta d'adaptació

```plantuml
@startuml
title Pujar aules obertes | PAGINA COMPLETA FINAL
start
:Validar sessio, rol i edicio al backend;
if (Autoritzat?) then (Si)
  :Carregar participants ja inscrits i estat PERENNE real;
  :Mostrar tutor, certificat i pendent sense inferir cobrament;
  if (Hi ha candidates?) then (Si)
    :Seleccionar files per ID_INSC i desti aula oberta;
    :Validar condicions acadèmiques i permisos al servidor;
    if (Hi ha files admissibles?) then (Si)
      :Crear o recuperar lot academic idempotent;
      :Preparar CSV privat amb codificacio i files valides;
      while (Queden files per preparar?) is (Si)
        :Desar resultat de la fila i estat PREPARAT;
      endwhile (No)
      if (Lot complet i fitxer coherent?) then (Si)
        :Oferir CSV a operador autoritzat amb recompte final;
      else (No)
        :Mostrar incidencies i recuperacio per fila;
      endif
    else (No)
      :Mostrar cap fila seleccionada sense crear fitxer;
    endif
  else (No)
    :Mostrar estat buit;
  endif
else (No)
  :Denegar consulta, exportacio i canvi d'estat;
endif
:Verificar posteriorment resultat de matrícula Moodle amb UC-129;
:No modificar factura, pagament ni certificat per generar CSV;
stop
@enduml
```

## 3. Diagrames d'activitat de pàgina i apartats

### AO01 · Accés i llista de candidates

**ACTUAL (PHP/JS versionat)**

```plantuml
@startuml
title AO01 · Accés i llista de candidates — ACTUAL
start
:Operador entra a la intranet i a Pujar aules obertes;
:Consulta exPujadaAO busca inscripcions elegibles;
if (Hi ha persones pendents?) then (Sí)
 :Mostrar el botó per obrir la pàgina;
 :Carregar curs-fi-cursos-pujar-aules-obertes.php i JS;
else (No)
 :Mostrar No hi ha registres;
endif
stop
@enduml
```

**FINAL (contracte d'adaptació)**

```plantuml
@startuml
title AO01 · Accés i llista de candidates — FINAL
start
:Validar sessió, rol i àmbit d'aula al servidor;
:Consultar candidats i estat real d'aula oberta;
if (Hi ha persones admissibles?) then (Sí)
 :Mostrar selecció per ID_INSC i destí;
else (No)
 :Mostrar estat buit, sense modificar PERENNE;
endif
stop
@enduml
```

### AO02 · Taula, informació i marques Pujar/No Pujar

**ACTUAL (PHP/JS versionat)**

```plantuml
@startuml
title AO02 · Taula, informació i marques Pujar/No Pujar — ACTUAL
start
:Intranet carrega informació d'edició i participant;
:Mostrar curs, dades personals, tutor, preu/pagament, pendent, certificat i reclamació;
:Mostrar botó de selecció per cada fila;
:Operador canvia Pujar o No Pujar al JavaScript;
stop
@enduml
```

**FINAL (contracte d'adaptació)**

```plantuml
@startuml
title AO02 · Taula, informació i marques Pujar/No Pujar — FINAL
start
:Consultar ID_INSC i estat acadèmic, certificat i aula oberta real;
:Separar deute per factura de dret d'accés de participant;
:Mostrar criteri de política i avisos comprovats;
:Operador selecciona persones per a aula oberta;
:No alterar imports ni certificat per seleccionar;
stop
@enduml
```

### AO03 · Confirmar i generar fitxer d'aules obertes

**ACTUAL (PHP/JS versionat)**

```plantuml
@startuml
title AO03 · Confirmar i generar fitxer d'aules obertes — ACTUAL
start
:Operador prem Confirma;
if (tePermisEdicio al JS?) then (Sí)
 :POST inici/crearFitxerAO.php;
 :Intranet crearFitxerAO escriu capçalera CSV;
 if (Error comunicat?) then (Sí)
  :Mostrar error;
 else (No)
  :Preparar enviament per files marcades;
 endif
else (No)
 :Mostrar denegació al navegador;
endif
stop
@enduml
```

**FINAL (contracte d'adaptació)**

```plantuml
@startuml
title AO03 · Confirmar i generar fitxer d'aules obertes — FINAL
start
:Autoritzar acció i membres del lot al backend;
:Validar estat INSC CURS, PERENNE, edició i identificador d'aula oberta;
if (Cap fila seleccionada?) then (Sí)
 :Retornar estat buit sense arxiu ni UPDATE;
else (No)
 :Crear/reutilitzar lot idempotent d'aules obertes;
 :Preparar CSV privat amb format i codificació provats;
endif
stop
@enduml
```

### AO04 · Cada fila: PERENNE i escriptura CSV

**ACTUAL (PHP/JS versionat)**

```plantuml
@startuml
title AO04 · Cada fila: PERENNE i escriptura CSV — ACTUAL
start
:Per fila marcada POST inici/pujarAulesObertes.php;
:Intranet pujar_AO fa UPDATE PERENNE=1 amb INSC CURS=1 i PERENNE=0;
:Afegir fila al CSV amb dades de l'operador;
note right
 UPDATE precedeix fwrite.
 Els POST es llancen sense esperar
 respostes de les files anteriors.
end note
stop
@enduml
```

**FINAL (contracte d'adaptació)**

```plantuml
@startuml
title AO04 · Cada fila: PERENNE i escriptura CSV — FINAL
start
:Recuperar inscripció real per ID_INSC i identificador del destí;
:Verificar autorització, estat i existència d'aula oberta;
if (Alta equivalent ja comprovada?) then (Sí)
 :Reusar resultat, no duplicar importació;
else (No)
 :Desar fila amb resultats independents de BD i fitxer;
 :Deixar PREPARADA fins a confirmació Moodle;
endif
:No modificar cap factura ni pagament;
stop
@enduml
```

### AO05 · Modal, descàrrega i comprovació de destí

**ACTUAL (PHP/JS versionat)**

```plantuml
@startuml
title AO05 · Modal, descàrrega i comprovació de destí — ACTUAL
start
:Quan respon el POST de la darrera posició del bucle;
:Mostrar missatge i enllaç fitxers/nom.csv al modal;
:Operador descarrega el CSV;
:Importació a Moodle i estat real de matrícula no comprovats en aquesta pàgina;
stop
@enduml
```

**FINAL (contracte d'adaptació)**

```plantuml
@startuml
title AO05 · Modal, descàrrega i comprovació de destí — FINAL
start
:Esperar totes les files o errors per lot;
:Mostrar resum complet i oferir descàrrega autoritzada;
:Carregar el fitxer a l'importador existent o destí confirmat;
:Consultar matrícula/aula oberta real per participant;
if (Importació i accés verificats?) then (Sí)
 :Registrar ID destí i canvi acadèmic efectiu;
else (No)
 :Registrar incidència, error i possible reintent UC-129;
endif
:No deduir cobrament, factura ni certificat per aquesta alta;
stop
@enduml
```

## 4. Requisits finals i proves de cada excepció (NO EXECUTADES)

| ID | Prova i resultat exigible |
| --- | --- |
| MO-AO-01 | Sense persones candidates: estat buit, no es genera fitxer. |
| MO-AO-02 | Un alumne amb `INSC CURS=0`: no marcar `PERENNE=1` per circumvenció directa de l'endpoint. |
| MO-AO-03 | Dues files del mateix USUARI però inscripcions diferents: operar per ID_INSC i destí, sense canviar totes per coincidència de clau parcial. |
| MO-AO-04 | POSTs paral·lels i en ordre diferent: CSV íntegre, un registre per fila, resultat només després de finalitzar tot el lot. |
| MO-AO-05 | UPDATE correcte però falla `fwrite`: registrar estat pendent i recuperar sense perdre fila. |
| MO-AO-06 | CSV generat però l'importador existent rebutja una fila: no mostrar-la com a aula oberta Moodle verificada. |
| MO-AO-07 | Repetició de pujada després de timeout: no duplicar matrícula/dret d'accés al destí. |
| MO-AO-08 | Persona amb factura d'empresa pendent: mostrar estat acadèmic separat d'obligació del pagador, sense CHARGE nou. |
| MO-AO-09 | Usuari sense permís invoca fitxer o POST directament: negar accés a dades i modificacions, també al servidor. |
| MO-AO-10 | Dades CSV amb accents/delimitadors: una fila per persona, sense exposició pública de dades personals. |
| MO-AO-11 | Moodle antic / curs diferent / baixa pendent: incidir a UC-129 i UC-124, no modificar factures ni certificats automàticament. |

## 5. Límit d'evidència i tancament

La fitxa documenta totes les accions d'aquesta **pàgina versionada** i el funcionament observable en el PHP/JS, amb una frontera explícita a l'importador de matrícules en lot existent. No s'ha inspeccionat la configuració productiva, el resultat de l'importador, permisos de descàrrega ni s'han executat les proves. **Aquest UC no equival al de pujada d'alumnes per a iniciar un curs; no reutilitzar les seves condicions INSC CURS en lloc de PERENNE.**

[Fitxa UC-113](../06-fitxes-funcionals/uc-113.md) · [Pujada d'alumnes](uc-moodle-pujada-alumnes-fitxa-activitats.md) · [Conciliació UC-129](uc-129-reconciliar-prisma-moodle-matricules.md) · [Registre mestre](../00-index-i-pla/42-registre-mestre-cobertura-funcional-implementacio-documentacio.md).
