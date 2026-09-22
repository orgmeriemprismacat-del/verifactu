# Pujada d'aules obertes — fitxa UML i diagrames de pàgina i apartats

**URL exacta confirmada per Meriem:** https://intranet.prisma.cat/cursos/fi-cursos/pujar-aules-obertes/ . **Fitxa funcional completa de 21 apartats:** [Pujada d'aules obertes](../06-fitxes-funcionals/uc-moodle-pujada-aules-obertes.md). **Codi base:** `main`, `e71958b3026549bde09fb4b25f2ec3ba370937ec`. **Frontera documental acordada:** només la pantalla, `PERENNE`, el CSV, el modal i els errors; no s'inclou ni es descriu el procés posterior de càrrega en un altre sistema. **Cas propi independent** de [generar fitxer de pujada d'alumnes a l'inici de curs](uc-moodle-pujada-alumnes-fitxa-activitats.md), de l'alta web UC-113 i del canvi de curs UC-026.

**Llegenda:** ACTUAL = lectura estàtica del PHP/JS versionat + ubicació de negoci; FINAL = objectiu d'adaptació no acreditat com a implantat; TEST = pendent d'execució. El número `CAND-UC-MOODLE-AO-01` és provisional fins a reconciliar el catàleg, però el cas funcional i la seva URL estan delimitats.

## 1. Identificació de pàgina i de les seves SET accions

| Àmbit | Secció | Efecte i codi |
| --- | --- | --- |
| AO-P00 | Pàgina completa | [PHP](../../codi-drive/intranet-actual/cursos-fi-cursos-pujar-aules-obertes.php), [JS](../../codi-drive/intranet-actual/js/cursos-fi-cursos-pujar-aules-obertes.js), [AJAX de main](../../codi-drive/intranet-actual/ajax/mostrarMain.php), [`Intranet.php L3278–3565`](../../codi-drive/intranet-actual/Intranet.php#L3278-L3565); 2 diagrames ACTUAL/FINAL. |
| AO-A01 | Entrada, sessió, resolució de l'URL i estat buit | [pàgina](../../codi-drive/intranet-actual/cursos-fi-cursos-pujar-aules-obertes.php), [mostrarMain](../../codi-drive/intranet-actual/ajax/mostrarMain.php), [Intranet.php L3278–3301](../../codi-drive/intranet-actual/Intranet.php#L3278-L3301). 2 diagrames ACTUAL/FINAL. |
| AO-A02 | Llistat de candidates, camps informatius i presentació per curs | [Intranet.php L3315–3461](../../codi-drive/intranet-actual/Intranet.php#L3315-L3461) i [consultes SQL](../../codi-drive/intranet-actual/Intranet.php#L499-L509). 2 diagrames ACTUAL/FINAL. |
| AO-A03 | Marcar i desmarcar participants | [JS L38–63](../../codi-drive/intranet-actual/js/cursos-fi-cursos-pujar-aules-obertes.js#L38-L63) i [HTML de la fila L3397–3429](../../codi-drive/intranet-actual/Intranet.php#L3397-L3429). 2 diagrames ACTUAL/FINAL. |
| AO-A04 | Confirmar, validar el permís client i crear CSV | [JS L64–103](../../codi-drive/intranet-actual/js/cursos-fi-cursos-pujar-aules-obertes.js#L64-L103), [crearFitxerAO.php](../../codi-drive/intranet-actual/ajax/inici/crearFitxerAO.php) i [Intranet.php L3488–3513](../../codi-drive/intranet-actual/Intranet.php#L3488-L3513). 2 diagrames ACTUAL/FINAL. |
| AO-A05 | Cada fila: POST, UPDATE de PERENNE i escriptura CSV | [JS L104–141](../../codi-drive/intranet-actual/js/cursos-fi-cursos-pujar-aules-obertes.js#L104-L141), [pujarAulesObertes.php](../../codi-drive/intranet-actual/ajax/inici/pujarAulesObertes.php), [Intranet.php L1073–1074](../../codi-drive/intranet-actual/Intranet.php#L1073-L1074) i [L3522–3565](../../codi-drive/intranet-actual/Intranet.php#L3522-L3565). 2 diagrames ACTUAL/FINAL. |
| AO-A06 | Modal de resultats, enllaç i tancament de la pàgina | [JS L57–63 i L116–141](../../codi-drive/intranet-actual/js/cursos-fi-cursos-pujar-aules-obertes.js#L57-L141), [modal PHP L3467–3482](../../codi-drive/intranet-actual/Intranet.php#L3467-L3482). 2 diagrames ACTUAL/FINAL. |
| AO-A07 | Errors, reintents, concurrència i dades personals | [JS L64–145](../../codi-drive/intranet-actual/js/cursos-fi-cursos-pujar-aules-obertes.js#L64-L145), [endpoints](../../codi-drive/intranet-actual/ajax/inici/pujarAulesObertes.php) i [Intranet.php L3488–3565](../../codi-drive/intranet-actual/Intranet.php#L3488-L3565). 2 diagrames ACTUAL/FINAL. |

**Pantalla de negoci:** el botó de fila és creat pel PHP amb el text `Qualifica` a la columna «Perenne», però el JS del selector `#pujar-ao` el presenta com a `Pujar` o `No Pujar`; **no executa una qualificació acadèmica**. La pàgina no ofereix aquí modal d'edició personal ni selector d'aula: el mateix JS conté handlers de `#pujada-inscr` d'una altra vista que **no s'han de comptar com a apartats d'aquesta URL**.

## 2. Diagrames d'activitat de PÀGINA COMPLETA

### AO-P00 · Pàgina completa — ACTUAL

```plantuml
@startuml
title AO-P00 Pujar aules obertes - PAGINA COMPLETA ACTUAL
start
:Obrir URL /cursos/fi-cursos/pujar-aules-obertes/;
if (Sessió vàlida?) then (Sí)
 :Carregar mainpanel amb mostrarMain i URL;
 if (Rol de visualització acceptat?) then (Sí)
  :Consultar exPujadaAO amb PERENNE=0 i INSC CURS=1;
  if (Hi ha candidates amb dades de curs/aula/tutor?) then (Sí)
   :Mostrar taula per curs amb camps informatius i marques Pujar;
   while (Operador canvia Pujar/No Pujar?) is (Sí)
    :Modificar classes marcat/no_marcat al navegador;
   endwhile (No)
   if (Prem Confirma i tePermisEdicio al JS?) then (Sí)
    :Crear capçalera CSV pujada-ao-datahora.csv;
    if (Error creació CSV?) then (Sí)
     :Mostrar alerta;
    else (No)
     if (Hi ha files marcades?) then (Sí)
      while (Queden files marcades?) is (Sí)
       :POST per fila sense esperar altres;
       :UPDATE PERENNE=1 per usuari/curs/any/mes;
       :Afegir fila amb course1=curs al mateix CSV;
       :Mostrar resposta o alerta individual;
      endwhile (No)
      :Enllaç CSV quan respon l'última posició del bucle;
      if (Tanca modal?) then (Sí)
       :Recarregar pàgina;
      endif
     else (No)
      :Mostrar cap canvi marcat; CSV capçalera ja creat;
     endif
    endif
   else (No)
    :No confirmar o mostrar falta de permisos al client;
   endif
  else (No)
   :Mostrar No hi ha resultats;
  endif
 else (No)
  :Mostrar denegació de visualització;
 endif
else (No)
 :Redirigir a inici intranet;
endif
stop
@enduml
```

### AO-P00 · Pàgina completa — FINAL

```plantuml
@startuml
title AO-P00 Pujar aules obertes - PAGINA COMPLETA FINAL
start
:Obrir la URL real de Pujar aules obertes;
:Backend valida sessió, rol, àmbit i edicions;
if (Autoritzat?) then (Sí)
 :Consultar candidates PERENNE=0, INSC CURS=1 i unions requerides;
 if (Hi ha candidates?) then (Sí)
  :Mostrar per curs dades acadèmiques i econòmiques només informatives;
  :Seleccionar per ID_INSC sense modificar GRUP ni dades personals;
  if (Hi ha files vàlides confirmades?) then (Sí)
   :Crear/reutilitzar lot idempotent i preparar CSV privat;
   while (Queden files seleccionades?) is (Sí)
    :Revalidar actor, ID_INSC, curs i estat;
    :Escriure fila validada i registrar resultat recuperable;
    :Coordinar canvi PERENNE i integritat del fitxer;
   endwhile (No)
   if (CSV coherent i totes les files amb resultat?) then (Sí)
    :Mostrar recompte i enllaç de descàrrega protegida;
   else (No)
    :Mostrar errors parcials i opció de represa sense duplicitat;
   endif
   if (Operador tanca modal?) then (Sí)
    :Recarregar vista de les candidates reals;
   endif
  else (No)
   :Mostrar selecció buida sense crear fitxer ni UPDATE;
  endif
 else (No)
  :Mostrar estat buit;
 endif
else (No)
 :Denegar acció i dades;
endif
:Fi de cas = estat BD i fitxer d'aquesta pàgina;
:Cap cobrament, factura, canvi de grup o certificat;
stop
@enduml
```

## 3. Diagrames d'activitat de TOTS ELS APARTATS de la pàgina

### AO-A01 · Entrada, sessió, resolució de l'URL i estat buit

**Fonts de l'apartat:** [pàgina](../../codi-drive/intranet-actual/cursos-fi-cursos-pujar-aules-obertes.php), [mostrarMain](../../codi-drive/intranet-actual/ajax/mostrarMain.php), [Intranet.php L3278–3301](../../codi-drive/intranet-actual/Intranet.php#L3278-L3301).

**ACTUAL (PHP/JS versionat)**

```plantuml
@startuml
title AO-A01 Entrada, sessió, resolució de l'URL i estat buit - ACTUAL
start
:Operador obre URL /cursos/fi-cursos/pujar-aules-obertes/;
if (comprovarSessio indica configOk?) then (Sí)
 :Carregar JS i GET mostrarMain amb pathname;
 if (tePermisVisualitzacio segons apartats.URL?) then (Sí)
  :Intranet comprova exPujadaAO;
  if (Hi ha PERENNE=0 i INSC CURS=1?) then (Sí)
   :Mostrar botó Vull pujar alumnes a l'aula oberta;
   :Obrir la pàgina específica;
  else (No)
   :Mostrar No hi ha registres;
  endif
 else (No)
  :Mostrar No tens permisos per visualitzar aquesta pàgina;
 endif
else (No)
 :Redirigir a intranet inicial;
endif
stop
@enduml
```

**FINAL (adaptació proposada, no implementada)**

```plantuml
@startuml
title AO-A01 Entrada, sessió, resolució de l'URL i estat buit - FINAL
start
:Obrir URL exacta de Pujar aules obertes;
:Verificar sessió i autorització de lectura al backend;
if (Autoritzat?) then (Sí)
 :Llegir candidates PERENNE=0 i INSC CURS=1;
 if (Hi ha candidates visualitzables?) then (Sí)
  :Oferir accés a la pàgina de selecció;
 else (No)
  :Mostrar estat buit sense generar fitxer;
 endif
else (No)
 :Denegar contingut i accions;
endif
stop
@enduml
```

### AO-A02 · Llistat de candidates, camps informatius i presentació per curs

**Fonts de l'apartat:** [Intranet.php L3315–3461](../../codi-drive/intranet-actual/Intranet.php#L3315-L3461) i [consultes SQL](../../codi-drive/intranet-actual/Intranet.php#L499-L509).

**ACTUAL (PHP/JS versionat)**

```plantuml
@startuml
title AO-A02 Llistat de candidates, camps informatius i presentació per curs - ACTUAL
start
:Recuperar IDs de exPujadaAO;
if (No hi ha candidates?) then (Sí)
 :Mostrar No hi ha resultats;
else (No)
 :Construir SELECT amb unió curs, aula, tutor i inscripcions;
 if (SELECT retorna registres?) then (Sí)
  while (Queda una inscripció retornada?) is (Sí)
   if (Canvia el codi CURS?) then (Sí)
    :Tancar taula anterior i obrir taula per curs;
   endif
   :Mostrar any, mes, curs, participant, correu, usuari i població;
   :Mostrar GTAF, data fi, certificat, perfil, titulació i tutor;
   :Mostrar A_PAGAR, PAGAMENT, pendent i reclamat només per consulta;
   :Mostrar botó Qualifica amb classe marcat a columna Perenne;
  endwhile (No)
  :Mostrar botó Confirma;
 else (No)
  :Mostrar No hi ha resultats;
 endif
endif
stop
@enduml
```

**FINAL (adaptació proposada, no implementada)**

```plantuml
@startuml
title AO-A02 Llistat de candidates, camps informatius i presentació per curs - FINAL
start
:Consultar candidates i fer unions de curs/aula/tutor de manera parametrizada;
if (Cap candidata visualitzable?) then (Sí)
 :Mostrar estat buit i raó tècnica quan sigui aplicable;
else (No)
 :Ordenar i agrupar per curs/edició;
 :Mostrar identificació, dades acadèmiques i pagaments com a informatives;
 :Fer visible la selecció per ID_INSC real;
 :No modificar certificat, grup, diners ni factura;
 :Mostrar Confirma habilitat si hi ha files elegibles;
endif
stop
@enduml
```

### AO-A03 · Marcar i desmarcar participants

**Fonts de l'apartat:** [JS L38–63](../../codi-drive/intranet-actual/js/cursos-fi-cursos-pujar-aules-obertes.js#L38-L63) i [HTML de la fila L3397–3429](../../codi-drive/intranet-actual/Intranet.php#L3397-L3429).

**ACTUAL (PHP/JS versionat)**

```plantuml
@startuml
title AO-A03 Marcar i desmarcar participants - ACTUAL
start
:El PHP ha generat botons Qualifica de classe marcat;
:El JS canvia text de marcat a Pujar;
:El JS canvia text de no_marcat a No Pujar;
while (Operador clica una marca?) is (Sí)
 if (Classe marcat?) then (Sí)
  :Canviar a no_marcat i text No Pujar;
 else (No)
  :Canviar a marcat i text Pujar;
 endif
endwhile (No)
:Cap modificació a BD fins a Confirma;
stop
@enduml
```

**FINAL (adaptació proposada, no implementada)**

```plantuml
@startuml
title AO-A03 Marcar i desmarcar participants - FINAL
start
:Mostrar candidates amb selector accessible i estat inicial explícit;
while (Operador modifica la selecció?) is (Sí)
 :Actualitzar únicament selecció local i resum de files marcades;
endwhile (No)
if (Hi ha files seleccionades?) then (Sí)
 :Permetre confirmar l'operació;
else (No)
 :Mostrar cap participant seleccionat sense crear fitxer;
endif
stop
@enduml
```

### AO-A04 · Confirmar, validar el permís client i crear CSV

**Fonts de l'apartat:** [JS L64–103](../../codi-drive/intranet-actual/js/cursos-fi-cursos-pujar-aules-obertes.js#L64-L103), [crearFitxerAO.php](../../codi-drive/intranet-actual/ajax/inici/crearFitxerAO.php) i [Intranet.php L3488–3513](../../codi-drive/intranet-actual/Intranet.php#L3488-L3513).

**ACTUAL (PHP/JS versionat)**

```plantuml
@startuml
title AO-A04 Confirmar, validar el permís client i crear CSV - ACTUAL
start
:Operador prem Confirma;
if (tePermisEdicio al navegador?) then (Sí)
 :Buidar modal de resultats;
 :POST crearFitxerAO.php;
 :Intranet obre fitxers/pujada-ao-datahora.csv amb a+;
 :Escriure capçalera CSV Moodle amb separador punt i coma;
 if (Resposta conté error o falla AJAX?) then (Sí)
  :Mostrar alerta o error de petició;
 else (No)
  :Retornar nom del fitxer;
  if (Hi ha almenys un botó marcat?) then (Sí)
   :Recórrer botons marcats i iniciar POST per fila;
  else (No)
   :Mostrar No has marcat cap canvi;
   note right
    El CSV de capçalera ja s'ha creat.
   end note
  endif
 endif
else (No)
 :Mostrar denegació al navegador;
endif
stop
@enduml
```

**FINAL (adaptació proposada, no implementada)**

```plantuml
@startuml
title AO-A04 Confirmar, validar el permís client i crear CSV - FINAL
start
:Operador confirma selecció;
:Backend valida sessió, rol, edicions i ID_INSC seleccionats;
if (No hi ha files autoritzades?) then (Sí)
 :Denegar o informar selecció buida sense crear fitxer;
else (No)
 :Crear/reutilitzar lot idempotent associat a operador i files;
 :Preparar CSV temporal privat amb capçalera i format comprovats;
 if (Error de fitxer?) then (Sí)
  :Registrar error i netejar temporal sense marcar PERENNE;
 else (No)
  :Processar files del lot segons apartat AO-A05;
 endif
endif
stop
@enduml
```

### AO-A05 · Cada fila: POST, UPDATE de PERENNE i escriptura CSV

**Fonts de l'apartat:** [JS L104–141](../../codi-drive/intranet-actual/js/cursos-fi-cursos-pujar-aules-obertes.js#L104-L141), [pujarAulesObertes.php](../../codi-drive/intranet-actual/ajax/inici/pujarAulesObertes.php), [Intranet.php L1073–1074](../../codi-drive/intranet-actual/Intranet.php#L1073-L1074) i [L3522–3565](../../codi-drive/intranet-actual/Intranet.php#L3522-L3565).

**ACTUAL (PHP/JS versionat)**

```plantuml
@startuml
title AO-A05 Cada fila: POST, UPDATE de PERENNE i escriptura CSV - ACTUAL
start
:JS llegeix any, mes, curs, usuari, nom, cognoms, correu i població del DOM;
:Enviar POST de fila i fitxer sense ID_INSC ni GRUP;
:Intranet pujar_AO prepara updPujadaAO;
:UPDATE inscripcions PERENNE=1 per USUARI/CURS/ANY/MES/INSC CURS=1/PERENNE=0;
:El mètode no comprova affected_rows;
:Obrir fitxer CSV rebut en POST;
:Afegir fila username;password buit;nom;cognoms;correu;població;ca;curs;0;2;
if (Fallada d'obertura o fwrite?) then (Sí)
 :Pot quedar PERENNE=1 sense fila vàlida al CSV;
else (No)
 :Retornar nom de fitxer;
endif
stop
@enduml
```

**FINAL (adaptació proposada, no implementada)**

```plantuml
@startuml
title AO-A05 Cada fila: POST, UPDATE de PERENNE i escriptura CSV - FINAL
start
:Recuperar ID_INSC i dades reals del servidor;
:Revalidar PERENNE=0, INSC CURS=1, permisos i destí;
if (Ja consta aquesta fila al mateix lot?) then (Sí)
 :Reutilitzar resultat sense duplicar;
else (No)
 :Generar fila CSV escapada, en codificació admesa;
 :Persistir resultat de preparació i canvi acadèmic recuperables;
 if (Fallada de BD o fitxer?) then (Sí)
  :Registrar incidència per fila i no afirmar èxit;
 else (No)
  :Desar resultat del fitxer i PERENNE de la inscripció exacta;
 endif
endif
stop
@enduml
```

### AO-A06 · Modal de resultats, enllaç i tancament de la pàgina

**Fonts de l'apartat:** [JS L57–63 i L116–141](../../codi-drive/intranet-actual/js/cursos-fi-cursos-pujar-aules-obertes.js#L57-L141), [modal PHP L3467–3482](../../codi-drive/intranet-actual/Intranet.php#L3467-L3482).

**ACTUAL (PHP/JS versionat)**

```plantuml
@startuml
title AO-A06 Modal de resultats, enllaç i tancament de la pàgina - ACTUAL
start
:Arriben respostes dels POST individuals;
while (Queda una resposta que arriba?) is (Sí)
 if (La resposta de la fila inclou error?) then (Sí)
  :Mostrar alerta d'actualització;
 else (No)
  :Obrir modal i afegir S'ha actualitzat el perenne;
 endif
 if (Índex de la fila és l'última posició del bucle?) then (Sí)
  :Afegir enllaç /fitxers/nom.csv al modal;
  note right
   Pot haver altres POST sense acabar.
  end note
 endif
endwhile (No)
if (Operador tanca modal?) then (Sí)
 :Recarregar la pàgina;
endif
stop
@enduml
```

**FINAL (adaptació proposada, no implementada)**

```plantuml
@startuml
title AO-A06 Modal de resultats, enllaç i tancament de la pàgina - FINAL
start
:Esperar tots els resultats de files seleccionades;
:Verificar recompte, estat per fila i coherència del CSV final;
if (Hi ha errors?) then (Sí)
 :Mostrar resum parcial i incidències recuperables;
else (No)
 :Mostrar resum complet de files preparades;
endif
:Oferir descàrrega del CSV acabat només a operador autoritzat;
if (Operador tanca modal?) then (Sí)
 :Recarregar la vista amb dades actualitzades de BD;
endif
stop
@enduml
```

### AO-A07 · Errors, reintents, concurrència i dades personals

**Fonts de l'apartat:** [JS L64–145](../../codi-drive/intranet-actual/js/cursos-fi-cursos-pujar-aules-obertes.js#L64-L145), [endpoints](../../codi-drive/intranet-actual/ajax/inici/pujarAulesObertes.php) i [Intranet.php L3488–3565](../../codi-drive/intranet-actual/Intranet.php#L3488-L3565).

**ACTUAL (PHP/JS versionat)**

```plantuml
@startuml
title AO-A07 Errors, reintents, concurrència i dades personals - ACTUAL
start
:El lot existent ha generat capçalera CSV;
:Iniciar POST independents per fila;
if (Falla petició o fitxer per una fila?) then (Sí)
 :La UI mostra error local;
 :No hi ha rollback BD/fitxer acreditat en aquest tram;
endif
if (Diversos POST escriuen al mateix CSV?) then (Sí)
 :No consta lock de fitxer ni barrera de finalització;
endif
if (Repeteix el botó o el POST?) then (Sí)
 :El WHERE pot no trobar PERENNE=0 però la fila CSV es pot escriure igual;
endif
:El fitxer conté dades personals i es retorna en una URL directa;
:No hi ha registre per fila durable acreditat en el codi revisat;
stop
@enduml
```

**FINAL (adaptació proposada, no implementada)**

```plantuml
@startuml
title AO-A07 Errors, reintents, concurrència i dades personals - FINAL
start
:Validar rol, ID_INSC, lot i nom de fitxer al backend;
if (Lot o fila equivalent ja processats?) then (Sí)
 :Retornar resultat anterior sense duplicar CSV;
else (No)
 :Preparar dades reals de BD i registrar resultat per fila;
 if (Error o timeout?) then (Sí)
  :Conciliar fitxer i PERENNE i permetre represa selectiva;
 else (No)
  :Confirmar fila preparada coherent;
 endif
endif
:Finalitzar lot només quan totes les files tenen resultat;
:Oferir fitxer protegit i aplicar retenció de dades personals;
stop
@enduml
```

## 4. UML de casos d'ús — només els controls de la pàgina

```plantuml
@startuml
left to right direction
actor "Usuari intranet amb permís" as O
rectangle "Pujar aules obertes - URL exacta" {
 usecase "Visualitzar candidates" as V
 usecase "Seleccionar Pujar o No Pujar" as S
 usecase "Confirmar i generar CSV AO" as C
 usecase "Actualitzar PERENNE i afegir fila" as P
 usecase "Llegir resultat i descarregar CSV" as R
 usecase "Reprendre fila o lot amb error" as E
}
O --> V
O --> S
O --> C
O --> R
C ..> P : inclou
E ..> C : recupera FINAL
@enduml
```

**No inclou:** crear una inscripció a PrisMa, canviar de grup, modificar un certificat ni una càrrega posterior en un campus.

## 5. UML de classes i components reals

```mermaid
classDiagram
direction LR
class PaginaAO {
  <<fitxer PHP existent>>
  +cursos-fi-cursos-pujar-aules-obertes.php
}
class ScriptAO {
  <<fitxer JS existent>>
  +cursos-fi-cursos-pujar-aules-obertes.js
}
class MostrarMain {
  <<endpoint PHP existent>>
  +GET(urlPagina)
}
class Intranet {
  <<classe PHP existent>>
  -__mostrarPage_Inici_Pujar_AO()
  -__mostrarPage_Cursos_Pujada_AO()
  -__modalActualitzarPerenne_Inici()
  +crearFitxerAO()
  +pujar_AO(usuari,any,mes,curs,fitxer,dades)
}
class CrearFitxerAO {
  <<endpoint PHP existent>>
  +POST()
}
class PujarAulesObertes {
  <<endpoint PHP existent>>
  +POST(any,mes,curs,usuari,fitxer,dades)
}
class ConnexioWeb {
  <<classe PHP existent>>
  +prepare(sql)
  +connectarBD()
}
class Inscripcions {
  <<taula BD web>>
  +ID
  +USUARI
  +CURS
  +ANY
  +MES
  +INSC_CURS
  +PERENNE
}
class FitxerCSV {
  <<artefacte real>>
  +pujada-ao-datahora.csv
}
PaginaAO --> ScriptAO
ScriptAO --> MostrarMain : mostrar taula
MostrarMain --> Intranet : renderitzar
ScriptAO --> CrearFitxerAO : confirma
ScriptAO --> PujarAulesObertes : per fila
CrearFitxerAO --> Intranet
PujarAulesObertes --> Intranet
Intranet --> ConnexioWeb
ConnexioWeb --> Inscripcions : SELECT i UPDATE
Intranet --> FitxerCSV : fopen i fwrite
```

**Llegenda:** `Intranet` i `ConnexioWeb` són classes PHP reals; `PaginaAO`, `ScriptAO`, `MostrarMain`, `CrearFitxerAO`, `PujarAulesObertes`, `Inscripcions` i `FitxerCSV` són etiquetes de fitxer/endpoint/taula/artefacte, **no noves classes PHP**.

## 6. UML de seqüència ACTUAL i FINAL

### Seqüència ACTUAL de la URL

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant J as JS pàgina AO
participant M as ajax/mostrarMain.php
participant I as Intranet.php
participant B as BD web
participant F as fitxers CSV
O->>J: Obre /cursos/fi-cursos/pujar-aules-obertes/
J->>M: GET mostrarMain(url=pathname)
M->>I: mostrarPage(usuari)
I->>B: SELECT exPujadaAO i vista amb JOIN curs/aula/tutor
B-->>I: Inscripcions amb INSC CURS=1 i PERENNE=0
I-->>J: Taules, dades informatives, botó Pujar i modal
O->>J: Alterna marques i prem Confirma
J->>I: POST ajax/inici/crearFitxerAO.php
I->>F: fopen a+ i fwrite capçalera
I-->>J: Nom pujada-ao-datahora.csv
loop Per cada fila marcada, AJAX independent
 J->>I: POST pujarAulesObertes.php
 I->>B: UPDATE PERENNE=1 per USUARI,CURS,ANY,MES i estats
 B-->>I: Execució SQL (sense comprovació affected_rows)
 I->>F: fopen a+ i fwrite fila amb course1=curs
 I-->>J: Nom fitxer
end
J-->>O: Modal, missatges i enllaç quan respon l'última posició del bucle
O->>J: Tanca modal
J->>J: window.location.reload()
Note over I,F: UPDATE precedeix fwrite; els POST no esperen els altres.
```

### Seqüència FINAL, limitada a la mateixa pantalla

```mermaid
sequenceDiagram
autonumber
actor O as Operador
participant P as Intranet AO
participant S as Servei de lot AO FINAL proposat
participant B as BD web
participant F as CSV privat
O->>P: Confirma IDs d'inscripció seleccionats
P->>S: prepararLot(actor,ID_INSC[],clau)
S->>B: Validar actor, edicions i PERENNE/INSC CURS de cada ID
alt Cap fila autoritzada
 S-->>P: Estat buit o denegació sense CSV
else Files vàlides
 loop Per cada ID_INSC real
  S->>B: Recuperar dades de la fila
  S->>F: Preparar fila CSV escapada
  S->>B: Persistir resultat de fila i PERENNE coherent
 end
 S->>F: Verificar recompte i finalitzar fitxer
 S-->>P: Resum complet o errors per fila recuperables
 P-->>O: Modal i descàrrega autenticada del fitxer acabat
end
Note over P,F: Aquesta seqüència FINAL és proposta: no està desplegada ni provada.
```

## 7. Matriu d'efectes i proves per a la pàgina

| Acció | ACTUAL observat | FINAL proposat | Test |
| --- | --- | --- | --- |
| Accedir i consultar | Sessió + rol lectura al `mostrarMain`, SQL `exPujadaAO`, unió de curs/aula/tutor | Autorització i candidats coherents | AO-AT-01…05 |
| Seleccionar | Classes JS `marcat`/`no_marcat`, «Pujar» per defecte | Selecció inequívoca per ID_INSC | AO-AT-06…08 |
| Confirmar | `tePermisEdicio` client; capçalera abans de comprovar zero files | Rol servidor; sense fitxer si selecció buida | AO-AT-07/15 |
| Escriure fila | UPDATE `PERENNE=1` abans de `fwrite`; WHERE sense ID ni grup | Execució per ID_INSC i coherència BD/CSV | AO-AT-08…12 |
| Resum i errors | POST per fila, enllaç a resposta darrera posició, recàrrega modal | Tots els resultats i arxiu íntegre abans de descàrrega | AO-AT-11…17 |
| Dades econòmiques i fiscals | Import/pagament/pendent/certificat **només informatius** | Cap cobrament ni factura al generar el fitxer | AO-AT-16 |

Els [17 tests reproduïbles estan detallats a la fitxa funcional](../06-fitxes-funcionals/uc-moodle-pujada-aules-obertes.md#18-proves-dacceptacio-de-pagina-proposades-no-executades). **NO EXECUTATS**: no s'ha inspeccionat el desplegament, les dades productives o l'autorització efectiva de la carpeta `fitxers`. **DOC tancable:** URL confirmada, fitxa real, activitats completes de pàgina/apartats i UML casos/classes/seqüència contrastats; això no certifica la implementació del FINAL ni les proves.

[Fitxa funcional completa](../06-fitxes-funcionals/uc-moodle-pujada-aules-obertes.md) · [Cas independent de pujada d'alumnes](uc-moodle-pujada-alumnes-fitxa-activitats.md) · [Registre mestre](../00-index-i-pla/42-registre-mestre-cobertura-funcional-implementacio-documentacio.md).
