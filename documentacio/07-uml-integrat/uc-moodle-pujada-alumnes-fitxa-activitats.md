# Cas d'ús específic — Pujada d'alumnes als cursos de Moodle

**ID funcional provisional:** CAND-UC-MOODLE-ALUMNES-01. La usuària confirma que és un UC **independent de la pujada d'aules obertes i d'UC-113**; atribuir ID numèric definitiu després de comprovar els 142 identificadors del catàleg. **Revisió:** 22/09/2026, codi `main` a `e71958b3026549bde09fb4b25f2ec3ba370937ec`. L'**importador de matrícules en lot EXISTENT** no s'ha de confondre amb el generador de CSV; si aquest importador és el destí del fitxer, el registre de resultat real s'ha de traçar al mètode/procediment corresponent, no fingir una crida Moodle al PHP d'aquesta pantalla.

## 1. Fitxa funcional detallada

| Camp | Funcionament actual contrastat i contracte funcional |
| --- | --- |
| Actor principal | Gestió acadèmica/operador de la intranet amb selecció de participants. Comprovació real de permisos a servidor del cas complet NO acreditada: el JS consulta `tePermisEdicio`. |
| Disparador i pàgina | Intranet → Cursos → Inici de cursos → Pujada d'alumnes. [Vista](../../codi-drive/intranet-actual/cursos-inici-cursos-pujar-alumnes.php), [JS](../../codi-drive/intranet-actual/js/cursos-inici-cursos-pujar-alumnes.js), [Intranet.php](../../codi-drive/intranet-actual/Intranet.php#L3571-L3801). |
| Precondicions | Inscripció ja EXISTENT a la BD web, curs/edició i aula coneguts; consulta de candidatures i avisos. Una fila no es crea de zero al confirmar la pujada. |
| Consultes i avisos | `__mostrarPage_Inici_Pujada_Inscripcions`, `__mostrarPage_Cursos_Pujada_Inscripcions`; mostra REALITZAT, DUPLICADA, DEUTOR; amb deute detectat marca No Pujar inicialment. Això no acredita una prohibició acadèmica universal. |
| Apartats | Accés des d'inici; taula per edició; filtres, avisos, marca Pujar/No Pujar, canvi d'aula; consulta i edició de dades; confirmar; modal d'estat; CSV/descàrrega; importació/validació a Moodle és un pas diferent. |
| Efecte web real | [`updPujadaInsc`](../../codi-drive/intranet-actual/Intranet.php#L1073-L1076): `INSC CURS='1', GRUP=?` sobre `USUARI,CURS,ANY,MES,INSC CURS='0'`. La identitat del `WHERE` no és ID_INSC: revisar col·lisions d'un usuari amb més d'una alta a la mateixa edició. |
| Fitxer real | [`crearFitxerPujadaInscripcions`](../../codi-drive/intranet-actual/Intranet.php#L4120-L4149) genera CSV amb capçalera Moodle; [`pujar_Inscripcions`](../../codi-drive/intranet-actual/Intranet.php#L4151-L4201) escriu dades de cada participant després de l'UPDATE. La columna `password` del CSV creat queda buida en la fila revisada. |
| Sortida visible | [JS L149–165](../../codi-drive/intranet-actual/js/cursos-inici-cursos-pujar-alumnes.js#L149-L165) afegeix missatges per fila i URL a fitxer a partir de resposta de l'última fila del bucle, sense barrera explícita per les altres respostes. |
| Resultat acadèmic | `INSC CURS=1` significa una escriptura llegada; no prova per si sola la matrícula efectiva ni l'accés confirmat Moodle. Verificar destí amb UC-129. |
| Efectes econòmics/fiscals | Aquest procés és acadèmic; **no** crear factura, CHARGE, devolució ni canviar receptor fiscal. Deute i accés es decideixen segons UC-95/124, no per una marca de CSV. |

## 2. Contracte de resultat i errors

**Resultat actual:** l'operador tria persones i aula, el sistema prepara fitxer, marca les inscripcions elegides i ofereix el CSV. **Resultat final previst:** a) fitxer preparat i custodiat; b) resultat per participant; c) importació/matrícula Moodle confirmada separadament; d) incidències per fitxer incomplet, error de destí o discrepància d'usuari/curs; e) idempotència de reexecució; f) cap efecte fiscal inferit. El procediment d'importació en lot **existeix** per confirmació de negoci, però el seu punt d'entrada i destí concret no són identificables amb certesa en aquest recorregut PHP.

**Errors contrastats:** cap fila marcada, error en crear fitxer, error en UPDATE, fallada d'escriptura CSV, POSTs concurrents i resultats desordenats, usuari sense permisos client, cursos/aules mal associats, alumne ja inscrit o amb deute, duplicat a Moodle i fitxer descarregable amb dades personals. No assumir que un correu de confirmació o l'estat `INSC CURS=1` acrediten per ells sols l'èxit a Moodle.

## 3. Matriu de pàgina i apartats (RM-037)

| Secció | Acció | Font | Diagrames |
| --- | --- | --- | --- |
| A01 | Entrada des de l'inici i càrrega de pàgina | `Intranet::__mostrarPage_Inici_Pujada_Inscripcions` | ACTUAL/FINAL A01 |
| A02 | Consulta, avisos, selecció i aula | `Intranet::__mostrarPage_Cursos_Pujada_Inscripcions`, JS | ACTUAL/FINAL A02 |
| A03 | Consulta i edició de persona | `modalEditaInscripcio_pujadaAlumnes`, `actualitzaDadesPersonals_pujadaAlumnes` | ACTUAL/FINAL A03 |
| A04 | Confirmar i crear CSV | `crearFitxerPujadaInscripcions.php` i mètode PHP | ACTUAL/FINAL A04 |
| A05 | Actualització de la matrícula origen i fila CSV | `pujarInscripcions.php`, `updPujadaInsc` | ACTUAL/FINAL A05 |
| A06 | Resum/descàrrega i frontera importador campus | JS, ruta de fitxer; destinació d'importador per acreditar | ACTUAL/FINAL A06 |

## 4. Diagrames d'activitat de cada apartat

### A01 · Inici: accés a «Pujada d'alumnes»

**ACTUAL (codi versionat)**

```plantuml
@startuml
title A01 · Inici: accés a «Pujada d'alumnes» — ACTUAL
start
:Entrar a la intranet amb sessió;
:Intranet consulta si hi ha altes candidates;
if (Hi ha alumnes per pujar?) then (Sí)
 :Mostrar botó Pujar alumnes en els cursos;
 :Obrir pantalla de pujada d'alumnes;
else (No)
 :Mostrar No hi ha resultats;
endif
stop
@enduml
```

**FINAL (adaptació proposada)**

```plantuml
@startuml
title A01 · Inici: accés a «Pujada d'alumnes» — FINAL
start
:Verificar sessió, rol i permís per edició;
:Consultar inscripcions pendents i estat acadèmic real;
if (Hi ha candidates?) then (Sí)
 :Mostrar accés al lot i nombre de casos;
else (No)
 :Mostrar estat buit sense crear fitxers ni canviar dades;
endif
stop
@enduml
```

### A02 · Pàgina: consulta, avisos i selecció d'aula

**ACTUAL (codi versionat)**

```plantuml
@startuml
title A02 · Pàgina: consulta, avisos i selecció d'aula — ACTUAL
start
:AJAX mostrarMain carrega llista d'inscripcions;
:Mostrar ANY MES CURS, aula, participant i dades;
:Mostrar etiquetes REALITZAT, DUPLICADA o DEUTOR segons comprovacions llegades;
:Marcar Pujar o No Pujar; amb deute detectat, No Pujar inicial;
:Operador canvia marques i pot seleccionar aula;
stop
@enduml
```

**FINAL (adaptació proposada)**

```plantuml
@startuml
title A02 · Pàgina: consulta, avisos i selecció d'aula — FINAL
start
:Consultar inscripcions amb ID_INSC real i estat Moodle actual;
:Mostrar alertes separades de duplicat, curs fet, deute i destinatari del pagament;
:Aplicar política d'accés acadèmic aprovada UC-95/124;
:Seleccionar persones i aula autoritzada per a cada participant;
:Mostrar motiu i actor de qualsevol excepció per permisos;
stop
@enduml
```

### A03 · Apartat: consultar i editar dades de l'alumne

**ACTUAL (codi versionat)**

```plantuml
@startuml
title A03 · Apartat: consultar i editar dades de l'alumne — ACTUAL
start
:Prémer icona editar o consultar en una inscripció;
:AJAX mostrarModalEditaInscripcio recupera les dades de la fila;
:Mostrar modal amb NOM COGNOMS DNI CORREU i contacte;
if (Operador desa els canvis?) then (Sí)
 :Validació de camps al JS;
 :AJAX envia valors i Intranet actualitza dades de la inscripció;
else (No)
 :Tancar modal sense guardar;
endif
stop
@enduml
```

**FINAL (adaptació proposada)**

```plantuml
@startuml
title A03 · Apartat: consultar i editar dades de l'alumne — FINAL
start
:Rebre ID_INSC de la fila seleccionada;
:Autoritzar lectura i modificació per rol i camp al servidor;
if (Desa canvis?) then (Sí)
 :Validar identitat/contacte i motiu de correcció;
 :Desar canvis de matrícula sense alterar factura emesa;
 :Conciliar canvi de correu o identitat amb UC-126/129 quan pertoqui;
else (No)
 :No modificar dades;
endif
stop
@enduml
```

### A04 · Apartat: confirmar i crear fitxer CSV

**ACTUAL (codi versionat)**

```plantuml
@startuml
title A04 · Apartat: confirmar i crear fitxer CSV — ACTUAL
start
:Prémer Confirma;
if (tePermisEdicio al JavaScript?) then (Sí)
 :AJAX crearFitxerPujadaInscripcions;
 :Crear fitxer amb capçalera de càrrega;
 if (Resposta conté error?) then (Sí)
  :Mostrar avís;
 else (No)
  :Recórrer files marcades al DOM;
 endif
else (No)
 :Mostrar No tens permisos;
endif
stop
@enduml
```

**FINAL (adaptació proposada)**

```plantuml
@startuml
title A04 · Apartat: confirmar i crear fitxer CSV — FINAL
start
:Autoritzar confirmació a backend sobre files reals;
:Congelar selecció d'ID_INSC, aula i destí Moodle;
if (No hi ha cap fila admissible?) then (Sí)
 :Retornar resultat buit sense generar CSV;
else (No)
 :Crear o recuperar lot acadèmic amb clau idempotent;
 :Preparar CSV temporal privat amb format validat;
endif
stop
@enduml
```

### A05 · Apartat: actualitzar cada inscripció i afegir fila

**ACTUAL (codi versionat)**

```plantuml
@startuml
title A05 · Apartat: actualitzar cada inscripció i afegir fila — ACTUAL
start
:Per cada fila marcada el JS envia POST pujarInscripcions.php;
:Intranet executa UPDATE INSC CURS=1, GRUP=aula;
:Després obre el mateix fitxer CSV i afegeix fila amb dades del DOM;
note right
 Si fwrite falla, UPDATE ja pot estar confirmat.
 Peticions AJAX per fila no esperen ordre estable.
end note
:Mostrar missatge d'aquella fila en respondre;
stop
@enduml
```

**FINAL (adaptació proposada)**

```plantuml
@startuml
title A05 · Apartat: actualitzar cada inscripció i afegir fila — FINAL
start
:Recuperar una fila del lot per ID_INSC i destinació;
:Comprovar selecció, permisos, estat real i duplicat Moodle;
if (Ja consta exportada o matriculada?) then (Sí)
 :Reusar identificador i no duplicar;
else (No)
 :Normalitzar/escapar CSV per camps, separadors i codificació;
 :Persistir fila i resultat de fitxer amb recuperació atòmica;
endif
:Marcar PREPARAT, no MATRICULAT, fins a confirmar Moodle;
stop
@enduml
```

### A06 · Apartat: resum, descàrrega i càrrega al campus

**ACTUAL (codi versionat)**

```plantuml
@startuml
title A06 · Apartat: resum, descàrrega i càrrega al campus — ACTUAL
start
:Quan respon el POST corresponent a l'última posició del bucle;
:Afegir enllaç /fitxers/nom.csv al modal;
:Operador pot descarregar el CSV;
:Mecanisme posterior de càrrega/confirmació Moodle NO present al JS revisat;
stop
@enduml
```

**FINAL (adaptació proposada)**

```plantuml
@startuml
title A06 · Apartat: resum, descàrrega i càrrega al campus — FINAL
start
:Esperar que TOTES les files del lot hagin acabat o tinguin error;
:Mostrar resum per fila i accés autoritzat al CSV;
:Operador o integració existent carrega el lot al campus;
:Recuperar resultat real d'importació per persona i curs;
if (Alta Moodle verificada?) then (Sí)
 :Desar identificador Moodle i confirmar estat acadèmic;
else (No)
 :Registrar incidència, error i reintent UC-129;
endif
:No crear CHARGE ni factura;
stop
@enduml
```

## 5. Proves obligatòries (NO EXECUTADES)

| ID | Acció i resultat esperat |
| --- | --- |
| MO-AL-01 | Obrir pàgina sense inscripcions: no hi ha botó de pujada ni cap CSV creat. |
| MO-AL-02 | Una persona amb dues inscripcions legítimes: es marca només ID_INSC seleccionat, no tot USUARI indiscriminadament. |
| MO-AL-03 | Amb deute en grup/empresa: aplicar política acadèmica aprovava per participant, no bloqueig econòmic implícit. |
| MO-AL-04 | Persona REALITZAT o DUPLICADA: avís i decisió explícita; cap segona matrícula al destí sense raó. |
| MO-AL-05 | Error després d'UPDATE i abans d'escriure CSV: recuperar estat sense afirmar importació correcta. |
| MO-AL-06 | Deu POST fora d'ordre: mostrar resultat complet quan tots han finalitzat, amb recompte exacte. |
| MO-AL-07 | Crear fitxer amb zero participants: missatge sense modificació de BD ni descàrrega falsa. |
| MO-AL-08 | Usuari no autoritzat invoca endpoint directe: cap UPDATE i cap fitxer exposat; comprovar servidor. |
| MO-AL-09 | Correu/nom amb separadors de CSV o salts de línia: format estable sense dades sobreres. |
| MO-AL-10 | CSV preparat però importació Moodle rebutjada: estat de preparació independent de matrícula verificada i incidència UC-129. |
| MO-AL-11 | Dades personals editades després de factura real: no alteració retroactiva de document fiscal. |
| MO-AL-12 | Reexecutar mateixa selecció després de timeout: reús del destí, no duplicar matrícula ni pagament. |

## 6. Traçabilitat i estat

**DOC:** funció actual, apartats i diagrames de pàgina documentats a partir del PHP i el JS, **frontera del sistema importador existent pendent de mapar en el punt concret de càrrega**. **IMP:** el codi llegat existeix; la consistència per fila, autorització, idempotència i reconciliació final no s'han acreditat. **TEST/producció:** no executat/no comprovat. **No és UC-113**, no és [pujada d'aules obertes](uc-moodle-aules-obertes-fitxa-activitats.md) i no és UC-129, que comprova la correspondència posterior.

[UC-113](../06-fitxes-funcionals/uc-113.md) · [UC-129](uc-129-reconciliar-prisma-moodle-matricules.md) · [UC-95](uc-095-estat-academic-deute-pendent.md) · [registre mestre](../00-index-i-pla/42-registre-mestre-cobertura-funcional-implementacio-documentacio.md).
