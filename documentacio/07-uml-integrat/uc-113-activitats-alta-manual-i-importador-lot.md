# UC-113 — Diagrames d'activitat d'alta manual i importador existent de matrícules en lot

**Revisió:** 22/09/2026, codi `main` a `e71958b3026549bde09fb4b25f2ec3ba370937ec`; decisions de [fitxa UC-113](../06-fitxes-funcionals/uc-113.md). **Identificació resolta:** Meriem ha aportat la URL exacta de l'«importador de matrícules en lot», https://intranet.prisma.cat/cursos/inici-cursos/generar-fitxer-pujada-alumnes/ . El codi d'aquesta pantalla de la intranet genera el CSV de pujada d'**inscripcions ja existents a PrisMa**; no es tracta d'una caixa negra ni demostra cap parser separat que creï altes noves a la BD web. Per decisió de negoci, les activitats de la URL es detallen com a **cas propi** [amb diagrames de pàgina completa i de tots els apartats ACTUAL/FINAL](uc-moodle-pujada-alumnes-fitxa-activitats.md); l'alta web d'UC-113 i el canvi de curs UC-026 es mantenen com a accions diferents. La càrrega real posterior a Moodle no és observable en el PHP d'aquesta URL.

## Índex de pàgines, apartats i frontera

| ID | Àmbit real | Apartats/activitats | Estat de traça |
| --- | --- | --- | --- |
| P113-01 | Web, inscripció curs / alta manual secretaria | Accés i curs, dades/preus excepcionals, alta i resultat | Alta web acreditada; mecanisme d'excepcions secretaria CONFIRMAT PER NEGOCI, codi específic no mapat. |
| P113-02 | Web, inscripció de grup | Responsables, participants i resultat per participant | Existència confirmada; diferenciar handlers de grup ordinari, amics i pack en el catàleg corresponent. |
| P113-03 | [Intranet: URL exacta «Generar fitxer pujada alumnes»](uc-moodle-pujada-alumnes-fitxa-activitats.md) | Preparar CSV en lot de matrícules prèvies i modificar INSC CURS/GRUP; NO crear una inscripció nova. | URL, PHP/JS i endpoints acreditats. Cas d'ús de pujada **separat** d'UC-113 i del d'aula oberta; 2 diagrames de pàgina completa i 12 d'apartats al seu document. |
| P113-04 | Intranet, «Mostrar la informació de l'alumne > Canvi de curs» | Derivació a UC-026, **fora** del cas UC-113 | Acció confirmada; diagrames complets del canvi corresponden a UC-026. |

## Pàgines i apartats — activitats actuals i finals

### P113-01.A · Accés i tria de curs/edició a la web

**Fonts:** [`InscripcioCurs.php`](../../codi-drive/web-actual/InscripcioCurs.php), [`enviarInscripcio.php`](../../codi-drive/web-actual/ajax/enviarInscripcio.php), decisió de negoci sobre secretaria.

**ACTUAL**

```plantuml
@startuml
title P113-01.A · Accés i tria de curs/edició a la web | ACTUAL
start
:Persona o secretaria obre la pàgina d'inscripcions web;
:Consultar oferta i edicions que presenta el canal;
if (Actor = secretaria?) then (Sí)
  :Permís de preu, descompte o curs excepcional CONFIRMAT PER NEGOCI;
  note right
    El mecanisme web exacte que
    identifica i autoritza secretaria
    no queda acreditat al PHP revisat.
  end note
else (No)
  :Oferta pública i condicions comunes;
endif
:Seleccionar curs i edició;
stop
@enduml
```

**FINAL / contracte d'adaptació**

```plantuml
@startuml
title P113-01.A · Accés i tria de curs/edició a la web | FINAL OBJECTIU
start
:Carregar catàleg, actor i permisos del servidor;
if (Actor de secretaria autoritzat?) then (Sí)
  :Mostrar curs ocult i condicions excepcionals autoritzades;
  :Registrar actor i motiu quan hi ha excepció;
else (No)
  :Mostrar només oferta i preus públics;
endif
:Validar curs i edició al backend abans d'acceptar l'alta;
stop
@enduml
```

### P113-01.B · Formulari i condicions comercials

**Fonts:** [`InscripcioCurs.php`](../../codi-drive/web-actual/InscripcioCurs.php), [`enviarInscripcio.php`](../../codi-drive/web-actual/ajax/enviarInscripcio.php), decisió de negoci sobre secretaria.

**ACTUAL**

```plantuml
@startuml
title P113-01.B · Formulari i condicions comercials | ACTUAL
start
:Omplir dades personals i condicions del formulari;
:Calcular/mostrar preu i descompte del recorregut utilitzat;
:Enviar dades a handler de web per tipus de producte;
note right
  enviarInscripcio.php cobreix curs.
  Altres tipus tenen handlers propis.
  No hi ha prova que el mateix handler
  doni l'excepció de secretaria.
end note
stop
@enduml
```

**FINAL / contracte d'adaptació**

```plantuml
@startuml
title P113-01.B · Formulari i condicions comercials | FINAL OBJECTIU
start
:Capturar persona, producte, edició i canal;
if (Preu/descompte o curs excepcional?) then (Sí)
  :Exigir actor de secretaria i motiu verificats al servidor;
  if (No autoritzat?) then (Sí)
    :Denegar sense crear alta;
    stop
  endif
else (No)
  :Calcular oferta segons regles ordinàries del servidor;
endif
:Registrar snapshot comercial i identitat de participant;
stop
@enduml
```

### P113-01.C · Enviament, persistència i resultat

**Fonts:** [`InscripcioCurs.php`](../../codi-drive/web-actual/InscripcioCurs.php), [`enviarInscripcio.php`](../../codi-drive/web-actual/ajax/enviarInscripcio.php), decisió de negoci sobre secretaria.

**ACTUAL**

```plantuml
@startuml
title P113-01.C · Enviament, persistència i resultat | ACTUAL
start
:Rebre sol·licitud al handler web corresponent;
:Validar i registrar alta llegada segons tipus de curs/grup;
:Desar inscripcions i relació IDPAG quan pertoqui;
:Preparar resultat i comunicacions de l'alta;
note right
  Crear inscripcio no acredita
  cobrament ni factura real.
end note
stop
@enduml
```

**FINAL / contracte d'adaptació**

```plantuml
@startuml
title P113-01.C · Enviament, persistència i resultat | FINAL OBJECTIU
start
:Verificar permisos, dades, preu i clau idempotent;
if (Alta equivalent existent?) then (Sí)
  :Recuperar inscripció i estat sense duplicar;
else (No)
  :Crear operació i alta acadèmica segons canal;
  :Guardar identificadors i resultat confirmat;
endif
:Retornar estat d'inscripció separat de pagament/factura;
:Notificar únicament fets ja confirmats;
stop
@enduml
```

### P113-02.A · Inscripció de grup a la web

**Fonts:** [`mostrar_formulari_inscripcions_grup.php`](../../codi-drive/web-actual/ajax/mostrar_formulari_inscripcions_grup.php), [`DescompteAmic.php`](../../codi-drive/web-actual/DescompteAmic.php) únicament com a variant comprovable.

**ACTUAL**

```plantuml
@startuml
title P113-02.A · Inscripció de grup a la web | ACTUAL
start
:Responsable inicia formulari de grup a la web;
:Capturar responsable, participants i edicions;
:Enviar al handler real de la modalitat de grup;
note right
  Cal discriminar grup ordinari,
  promoció amics i pack.
  No totes comparteixen constructor.
end note
:Registrar les inscripcions llegades i IDPAG segons modalitat;
stop
@enduml
```

**FINAL / contracte d'adaptació**

```plantuml
@startuml
title P113-02.A · Inscripció de grup a la web | FINAL OBJECTIU
start
:Identificar responsable i participants de grup;
:Validar cada curs, edició, preu i descompte;
:Fixar receptor fiscal per document segons contracte;
:Guardar operació comuna i línies per inscrit;
if (Una persona o línia és invàlida?) then (Sí)
  :Aplicar política de confirmació del grup sense inventar pagaments;
else (No)
  :Registrar resultat per participant i import individual;
endif
:No duplicar moviment bancari per nombre de participants;
stop
@enduml
```

### P113-03.A · URL REAL del lot: generar fitxer de pujada d'alumnes — FRONTERA DE CAS PROPI

**URL exacta confirmada per negoci:** https://intranet.prisma.cat/cursos/inici-cursos/generar-fitxer-pujada-alumnes/ . **Font del flux ACTUAL:** [pantalla de la intranet](../../codi-drive/intranet-actual/cursos-inici-cursos-pujar-alumnes.php), [JS](../../codi-drive/intranet-actual/js/cursos-inici-cursos-pujar-alumnes.js), [`__mostrarPage_Cursos_Pujada_Inscripcions`](../../codi-drive/intranet-actual/Intranet.php#L3619-L3832), [`crearFitxerPujadaInscripcions`](../../codi-drive/intranet-actual/Intranet.php#L4124-L4149) i [`pujar_Inscripcions`](../../codi-drive/intranet-actual/Intranet.php#L4158-L4201). **No resta cap altra pàgina d'importador en lot de PrisMa per localitzar sobre la base d'aquest requeriment.**

El procés és el que Meriem anomena **«importador de matrícules en lot» a la intranet**. En el PHP revisat es preparen **alumnes que ja són a `inscripcions`** i es crea un CSV per a Moodle. No s'importa cap fitxer d'entrada que faci INSERT d'altes noves a PrisMa. Per l'acord de separar els casos, l'activitat següent és un **enllaç de frontera d'UC-113 al cas específic de pujada**, no una segona manera de crear l'alta web.

**ACTUAL — diagrama de frontera amb els passos verificats (el [diagrama de pàgina COMPLETA i els 12 dels apartats estan al cas propi](uc-moodle-pujada-alumnes-fitxa-activitats.md))**

```plantuml
@startuml
title URL intranet lot alumnes | ACTUAL | cas diferenciat d'UC-113
start
:Obrir Generar fitxer pujada alumnes a la INTRANET;
:Recuperar matricules EXISTENTS amb INSC CURS=0 i cursos candidats;
:Mostrar grup, avisos REALITZAT/DUPLICADA/DEUTOR i seleccion de fila;
:Operador confirma alumnes i aula;
:AJAX crearFitxerPujadaInscripcions crea capcalera CSV;
while (Hi ha una altra fila marcada?) is (Sí)
  :POST pujarInscripcions per fila sense esperar les altres;
  :UPDATE INSC CURS=1 i GRUP segons usuari/curs/any/mes;
  :Afegir la fila al mateix CSV;
endwhile (No)
:Mostrar enllac del fitxer des de la resposta de la darrera posicio;
:No hi ha INSERT d'inscripcio nova a PrisMa en aquesta ruta;
:No hi ha resposta de matricula Moodle confirmada;
stop
@enduml
```

**FINAL — contracte del cas específic de generació/pujada de lot**

```plantuml
@startuml
title URL intranet lot alumnes | FINAL | cas separat
start
:Verificar rol, edicions i ID_INSC al servidor;
:Seleccionar candidats i aula i aplicar politica de duplicat/deute;
if (Hi ha almenys una fila admissible?) then (Sí)
  :Crear/reutilitzar lot i generar CSV privat;
  while (Queden files per processar?) is (Sí)
    :Escriure fila i persistir estat per ID_INSC;
    :No marcar resultat Moodle abans de la prova de desti;
  endwhile (No)
  :Mostrar resum i descarrega autoritzada del fitxer complet;
else (No)
  :No crear fitxer buit ni modificar inscripcions;
endif
:Contrastar carrega real a Moodle en operacio posterior/UC-129;
:No crear una nova alta a PrisMa, cobrament ni factura per aquest CSV;
stop
@enduml
```

### P113-03.B · Error, lot repetit i resultat Moodle — no són un parser desconegut a PrisMa

**Actual verificat:** el JS crea el CSV abans de comprovar si hi ha cap alumne marcat, envia peticions AJAX de fila de manera concurrent i afegeix l'enllaç quan respon l'última posició del bucle. [`pujar_Inscripcions`](../../codi-drive/intranet-actual/Intranet.php#L4158-L4201) fa l'UPDATE **abans** del `fwrite`; si falla l'escriptura, la inscripció pot quedar marcada sense una fila correcta al fitxer. **Ni la descàrrega ni l'UPDATE acrediten que la matrícula ja s'hagi carregat a Moodle.**

**ACTUAL**

```plantuml
@startuml
title Lot alumnes | ACTUAL | errors i represa
start
:Confirmar operacio amb seleccio actual;
:Crear fitxer fins i tot abans del test zero files;
if (Hi ha files marcades?) then (Sí)
  :Engegar POST separats per fila;
  :Cada POST fa UPDATE de BD i despres fwrite CSV;
  if (Falla fwrite o una peticio?) then (Sí)
    :Pot quedar BD marcada i fitxer incomplet;
    :Mostrar error local sense recuperacio atomica acreditada;
  endif
  :JS afegeix enllac en resposta de la darrera posicio;
else (No)
  :Mostrar cap canvi marcat; capcalera ja creada;
endif
:No confirmar resultat real de Moodle;
stop
@enduml
```

**FINAL PROPOSAT**

```plantuml
@startuml
title Lot alumnes | FINAL | errors i reintent
start
:Validar seleccio abans de crear fitxer;
if (Hi ha files?) then (Sí)
  :Crear o recuperar lot idempotent;
  :Generar CSV privat i persistir resultats per ID_INSC;
  if (Error de fitxer o fila?) then (Sí)
    :Marcar incidencia, restaurar o reconciliar marca academica;
    :Permetre represa sense fila duplicada;
  else (No)
    :Esperar final de totes les files i oferir CSV complet;
  endif
  :Contrastar importacio efectiva Moodle en pas posterior;
else (No)
  :Mostrar cap fila seleccionada, sense generar CSV;
endif
:No inventar cap ingrés, factura ni alta nova PrisMa;
stop
@enduml
```

### P113-04.A · Canvi de curs / regularització DESVINCULADA d'UC-113

**Fonts:** [UC-026](uc-026-canviar-de-curs.md) i [`Intranet.php`](../../codi-drive/intranet-actual/Intranet.php), ubicació funcional confirmada per negoci.

**ACTUAL**

```plantuml
@startuml
title P113-04.A · Canvi de curs / regularització DESVINCULADA d'UC-113 | ACTUAL
start
:Operador obre Intranet > Mostrar informació de l'alumne;
:Seleccionar inscripció existent i Canvi de curs;
:Aplicar procediment llegat específic de canvi/regularització;
:Registrar canvi en les inscripcions segons estat;
note right
  Pertany a UC-026, no es tracta
  com a importació en lot.
end note
stop
@enduml
```

**FINAL / contracte d'adaptació**

```plantuml
@startuml
title P113-04.A · Canvi de curs / regularització DESVINCULADA d'UC-113 | FINAL OBJECTIU
start
:Obrir UC-026 sobre inscripció real amb actor autoritzat;
:Comprovar factura, cobrament, saldo i fase actual;
:Previsualitzar servei nou i impacte fiscal/econòmic;
:Executar alta o baixa acadèmica i documents corresponents;
:Conservar traça de la inscripció i factura originals;
stop
@enduml
```

## Matriu de tancament documental i de proves

| Traça | Estat verificat / pendent |
| --- | --- |
| P113-01 | Alta web documentada amb codi de formulari/handler. La ruta i autorització tècnica de l'excepció de secretaria queden per acreditar; no crear cap nova pantalla d'intranet per aquest motiu. |
| P113-02 | Separar grup ordinari, amics i pack segons la ruta efectiva, sense inferir-ne el mateix handler. |
| P113-03 | **RESOLT:** URL real intranet «Generar fitxer pujada alumnes», PHP, JS, dos endpoints, SQL UPDATE i format CSV localitzats. [Fitxa específica i diagrames ACTUAL/FINAL de pàgina completa i apartats](uc-moodle-pujada-alumnes-fitxa-activitats.md). **No queda pendent buscar l'executable d'un segon importador d'altes a PrisMa.** |
| P113-04 | Procediment de canvi/regularització pertany a UC-026; conservar historial d'edició i estat fiscal. |
| Proves i operació | Resta validar comportament desplegat, permisos de servidor, errors concurrents, integritat BD/CSV i resultat real de càrrega a Moodle. Les proves no s'han executat; això no invalida que la pàgina estigui identificada. |

**Conclusió:** documentada la ruta real de la funcionalitat anomenada «importador de matrícules en lot» i separada conceptualment de l'alta inicial i del flux d'aules obertes. **No afirmar que s'han executat proves ni que aquesta pàgina sola matricula automàticament a Moodle.**
