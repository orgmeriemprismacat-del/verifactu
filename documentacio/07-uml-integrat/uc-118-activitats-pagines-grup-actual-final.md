# UC-118 — activitats de la pàgina de grup, per pas/control, ACTUAL i FINAL

**Fonts:** `main` inspeccionat el 25/09/2026: [`DescompteGrup.php`](../../codi-drive/web-actual/DescompteGrup.php), [`mostrarDescompteGrup.min.js`](../../codi-drive/web-actual/js1619773569/mostrarDescompteGrup.min.js), wrappers [`ajax/`](../../codi-drive/web-actual/ajax/) i [lectors/constructors SIF de grup](../../sif/src/Repository/LegacyGroupSnapshotRepository.php). **ACTUAL** significa recorregut identificat al PHP/JS versionat, no prova de producció. **FINAL** és disseny a implementar; cap diagrama diu que el coordinador previ de grup estigui acabat. [Auditoria del lot 07](00-auditoria-casos-pendents-lot-07-uc-118-2026-09-25.md) · [fitxa funcional](../06-fitxes-funcionals/uc-118.md).

## P01 · Informació de descomptes per grup, taula de trams i botons d'hores/curs

**ACTUAL —** [`DescompteGrup::__obtenirContingutInici` L187–405](../../codi-drive/web-actual/DescompteGrup.php#L187-L405): textos de mínim 3, no acumulació i aula exclusiva 15+, trams `descomptes_grup`, opcions de curs/hores.

```plantuml
@startuml
title UC118 P01 ACTUAL - Informacio i trams
start
:Obrir pagina descompte per grup;
:Carregar text de 3+ persones, taula i cursos en linia;
:Consultar descomptes_grup vigent per trams;
:Consultar hores i cursos disponibles;
:Mostrar preu ordinari i preu per persona segons tram;
:Mostrar missatge comercial aula exclusiva 15+;
if (Persona tria hores o curs?) then (Si)
 :Mostrar cursos o iniciar formulari del curs;
else (No)
 :Continuar a la pagina informativa;
endif
stop
@enduml
```

**FINAL —** mateix contingut informatiu, però el preu de taula no reserva places ni tanca oferta.

```plantuml
@startuml
title UC118 P01 FINAL - Consulta informativa
start
:Consultar cursos/edicions i trams actius amb la seva versio;
:Mostrar preus per persona i requisits de grup;
:Explicar que l'aula exclusiva necessita confirmacio de capacitat;
if (Persona selecciona curs?) then (Si)
 :Iniciar esborrany de grup sense emetre ni cobrar;
else (No)
 :Conservar estat purament informatiu;
endif
stop
@enduml
```

## P02 · «Formulari d'inscripció per a grups» — curs, tram i botons Grup / Centre escolar / Tornar / Inscripció grupal

**ACTUAL —** [PHP L457–694](../../codi-drive/web-actual/DescompteGrup.php#L457-L694) i [JS L249–342](../../codi-drive/web-actual/js1619773569/mostrarDescompteGrup.min.js#L249-L342). La pàgina del curs normal tria un curs per grup, mostra preus per trams i exigeix seleccionar modalitat només al JS.

```plantuml
@startuml
title UC118 P02 ACTUAL - Curs i modalitat
start
:Tria de curs des de P01 o URL del curs;
:PHP cerca ID_PREU, preu ordinari i descomptes_grup;
:Mostra trams per persona i botons Grup / Centre escolar;
if (Torna o tria altre curs?) then (Si)
 :JS torna a la pagina anterior;
 stop
else (No)
 :Persona marca modalitat;
endif
if (Continua amb modalitat seleccionada?) then (Si)
 :JS carrega P03 dades de contacte;
else (No)
 :Mostra modal amb error de tipus de grup;
endif
stop
@enduml
```

**FINAL —** crear identificador d'esborrany i validar la modalitat/curs al servidor.

```plantuml
@startuml
title UC118 P02 FINAL - Esborrany tipificat
start
:Seleccionar producte i modalitat Grup / Centre escolar;
:Backend comprova curs/edicio, elegibilitat i politica de grup;
if (Opcions valides?) then (Si)
 :Crear o recuperar UUID_OPERATION esborrany idempotent;
 :Fixar curs, tipus de grup i versio inicial;
 :Mostrar P03 amb dades coherents;
else (No)
 :Retornar error tipificat sense obrir cobrament;
endif
stop
@enduml
```

## P03 · «Dades de contacte» — centre, persona, document, correu, adreça i «Continua / Enrere»

**ACTUAL —** [PHP L695–890](../../codi-drive/web-actual/DescompteGrup.php#L695-L890); [JS L504–610](../../codi-drive/web-actual/js1619773569/mostrarDescompteGrup.min.js#L504-L610). Per al centre, el formulari demana nom del centre i CIF, però la persistència llegada posa les dades de contacte a `respGrups`.

```plantuml
@startuml
title UC118 P03 ACTUAL - Contacte grup o centre
start
:Mostrar formulari de contacte de P03;
if (Modalitat Centre escolar?) then (Si)
 :Demanar nom centre i CIF al formulari;
else (No)
 :Demanar document de la persona de contacte;
endif
:Recollir nom, cognoms, telefon, email i adreca;
if (Boto Enrere?) then (Si)
 :Tornar a P02;
 stop
endif
:JS valida camps de contacte localment;
if (Validacio JS favorable?) then (Si)
 :Guardar valors al navegador i demanar P04 per GET;
else (No)
 :Mostrar modal amb llista de camps erronis;
endif
stop
@enduml
```

**FINAL —** responsable, contacte, pagador i receptor no són sinònims.

```plantuml
@startuml
title UC118 P03 FINAL - Parts i atribucio
start
:Recollir responsable, persona de contacte i modalitat;
if (Centre escolar?) then (Si)
 :Identificar entitat amb CIF i persona representant;
else (No)
 :Identificar responsable particular;
endif
:Identificar pagador previst i receptor fiscal proposat separadament;
:Backend valida actor, representacio, identificadors i permis sobre grup;
if (Parts coherents per a la composicio?) then (Si)
 :Guardar esborrany versionat amb parts diferenciades;
 :Continuar a P04;
else (No)
 :Retornar error i conservar esborrany sense emetre ni cobrar;
endif
stop
@enduml
```

## P04 · «Dades del grup», edició, afegir participant, modal, tornar i continuar

**ACTUAL —** [PHP L900–904 i L1007–1115](../../codi-drive/web-actual/DescompteGrup.php#L1007-L1115); [JS L781–948](../../codi-drive/web-actual/js1619773569/mostrarDescompteGrup.min.js#L781-L948). El botó afegeix dades a l'objecte serialitzat de sessió. **No s'observa un botó de baixa individual al formulari d'aquest apartat**, ni que l'append de sessió reservi places; no inventar-ne els efectes.

```plantuml
@startuml
title UC118 P04 ACTUAL - Afegir membres en sessio
start
:Mostrar llistat de dadesGrup, modal Afegeix alumne i edicio;
if (Persona prem Afegeix alumne?) then (Si)
 :Mostrar modal amb camps personals i curriculars;
 :JS valida dades del participant;
 if (Validacio JS favorable?) then (Si)
  :GET afegirAlumne_desompteGrup amb dades personals;
  :PHP append del vector a dadesGrup de sessio;
  if (Callback AJAX success?) then (Si)
   :Incrementar comptador JS i refrescar P04;
  else (No)
   :Mostrar error de peticio;
  endif
 else (No)
  :Mostrar errors de camps;
 endif
endif
if (Boto Enrere?) then (Si)
 :Tornar a dades de contacte;
 stop
endif
:Persona tria edicio i com ha conegut el curs;
if (Continua amb edicio i dadesGrup.length > 2?) then (Si)
 :JS obre P05 resum;
else (No)
 :Mostrar error d'edicio, origen o minim de 3;
endif
stop
@enduml
```

**FINAL —** afegir/treure només en estat obert amb versió, duplicats i places.

```plantuml
@startuml
title UC118 P04 FINAL - Membres i edicio del grup obert
start
:Mostrar estat real i versio de grup, participants i edicio;
if (Afegir, modificar o treure participant?) then (Si)
 :Validar actor, edicio, persona i absencia de duplicat;
 if (Grup continua obert i hi ha places?) then (Si)
  :Modificar membres en transaccio i registrar event/version;
  :Recalcular tram i totes les linies afectades;
 else (No)
  :Rebutjar i classificar canvi si oferta o TPV ja congelats;
 endif
endif
if (Continua a resum?) then (Si)
 :Verificar minim de membres elegibles al backend;
 :Verificar places/edicions i tram aplicable;
 if (Composicio valida?) then (Si)
  :Passar a P05 sense marcar reserva com a cobrament;
 else (No)
  :Mostrar errors i mantenir esborrany;
 endif
endif
stop
@enduml
```

## P05 · «Resum de les dades introduïdes» — import total, responsable, participants, «Torna / Envia dades»

**ACTUAL —** [PHP L1201–1404](../../codi-drive/web-actual/DescompteGrup.php#L1201-L1404): compte efectiu `count(dadesGrup)`, busca el tram dins `$this->preus` de sessió, mostra total i contactes, permet comentaris. [JS L990–1027](../../codi-drive/web-actual/js1619773569/mostrarDescompteGrup.min.js#L990-L1027) envia per AJAX en confirmar.

```plantuml
@startuml
title UC118 P05 ACTUAL - Resum de grup
start
:Obrir resum amb edicio, contacte, membres i imports;
:PHP recalcula recompte sobre dadesGrup de sessio;
:Escollir tram de preus desat en objecte de sessio;
:Mostrar preu grup per participant i import total;
if (Boto Enrere?) then (Si)
 :Tornar a P04;
else (No)
 :Persona confirma Envia dades;
 :JS crida el handler d'enviament;
endif
stop
@enduml
```

**FINAL —** no permetre presentar resum caducat com si fos import autoritzat.

```plantuml
@startuml
title UC118 P05 FINAL - Oferta coherent abans del TPV
start
:Carregar grup i versio del backend;
:Revalidar membres, curs/edicions, disponibilitat i trams vigents;
:Calcular base/descompte/total per membre i total del grup;
:Confirmar contacte, pagador, receptor fiscal i agrupacio de factura;
if (Canvia composicio, tarifa o persona fiscal?) then (Si)
 :Mostrar nova oferta i demanar acceptacio explicita;
endif
if (Persona confirma mateixa versio coherent?) then (Si)
 :Congelar snapshot de grup amb membres, imports, parts i regla;
 :Crear o recuperar intencio de pagament seguint UC-112/63;
else (No)
 :Conservar esborrany, no cobrar ni emetre;
endif
stop
@enduml
```

## P06 · «Envia dades» — crear contacte, inscripcions i enllaç de pagament

**ACTUAL —** [JS L1191–1232](../../codi-drive/web-actual/js1619773569/mostrarDescompteGrup.min.js#L1191-L1232) GET `enviaDades_DescompteGrup.php`; [PHP L1405–1430, 1569–1611, 1874–1955, 2090–2098](../../codi-drive/web-actual/DescompteGrup.php#L1405-L1430): calcula tram des del vector i recompte, incrementa `IDPAG` llegit per SELECT global, insereix `respGrups` i N `inscripcions`, prepara comunicacions i retorna token de confirmació. Aquest pas no és un callback bancari.

```plantuml
@startuml
title UC118 P06 ACTUAL - Persistencia llegada de grup
start
:GET enviaDades_DescompteGrup amb tipus, mailing i comentaris;
:Recuperar descompteGrup de la sessio;
:PHP selecciona tram de preus per count dadesGrup;
:Consultar darrer IDPAG i fer IDPAG + 1;
:INSERT respGrups amb contacte i IDPAG;
:Construir missatges i enllac de pagament;
repeat
 :Preparar dades del participant;
 :INSERT inscripcions TIPUS_INSC=G, A_PAGAR i IDPAG comu;
repeat while (Hi ha mes membres?) is (Si) not (No)
:Preparar notificacions i retornar token de confirmacio;
:JS redirigeix a pagina confirmacio si la resposta no conté error;
note right
 No s'ha identificat atomia global,
 clau idempotent ni reserva de places
 en aquest cos. Enviament SMTP
 i pagament reals no s'han provat.
end note
stop
@enduml
```

**FINAL —** persistir o recuperar el grup coherent i només comunicar resultat real.

```plantuml
@startuml
title UC118 P06 FINAL - Commit de grup idempotent
start
:Rebre ordre autenticada amb UUID_OPERATION i versio congelada;
:Verificar autoritzacio, idempotencia i hash del snapshot acceptat;
if (Versio, titulars, places i tarifes segueixen valids?) then (Si)
 :Reservar o confirmar places de tots els membres;
 :Persistir en transaccio operacio, responsable i N linies;
 :Assignar ID/UUID unics sense SELECT MAX + 1 concurrent;
 if (Commit complet?) then (Si)
  :Crear notificacions en outbox i instruccions de pagament per la versio;
  :Retornar un sol estat i token restringit;
 else (No)
  :Rollback o registrar reparacio traçable sense confirmar cobrament;
 endif
else (No)
 :Retornar conflicte/necessitat de nova oferta;
endif
stop
@enduml
```

## P07 · Frontera UC-118 → pagament de grup i construcció fiscal

**ACTUAL observat:** [`PagamentGrupAutomatic.php`](../../codi-drive/web-actual/PagamentGrupAutomatic.php) usa l'IDPAG del grup; [`LegacyGroupSnapshotRepository.php`](../../sif/src/Repository/LegacyGroupSnapshotRepository.php) recupera `respGrups` i els inscrits de tipus G; [`LegacyGroupInvoicePayloadBuilder.php`](../../sif/src/Service/LegacyGroupInvoicePayloadBuilder.php) produeix N línies per membre i receptor a partir de `respGrups`. No és prova que UC-118 creï factura abans de completar el seu grup.

```plantuml
@startuml
title UC118 P07 ACTUAL - Frontera llegat/pagament/factura
start
:Grup amb respGrups, N inscripcions i IDPAG comu;
:Pagina PagamentGrupAutomatic consulta dades per IDPAG;
:Repositori SIF pot carregar snapshot llegat segons IDPAG;
:Builder SIF pot generar N linies des de membres del snapshot;
:Billing del builder pren responsable de respGrups;
note right
 No es demostra aqui pagament real
 ni receptor fiscal autoritzat
 per totes les composicions.
end note
stop
@enduml
```

```plantuml
@startuml
title UC118 P07 FINAL - Transferir snapshot tancat a UC de cobrament
start
:Operacio de grup amb composicio, regla, receptor i pagador aprovats;
:Passar snapshot immutable a UC-112/63 abans de Redsys;
if (Cobrament real confirmat?) then (Si)
 :UC de pagament registra moviment bancari real;
 :UC fiscal genera/reutilitza factura segons receptor validat;
 :Enllacar N linies i membres sense exposar PDF complet a tercers;
else (No)
 :No afirmar pagament ni factura emesa;
 :Conservar estat de grup i incidencia/reintent quan pertoqui;
endif
:Canvis posteriors a factura van a UC-16a/16b i altres UC;
stop
@enduml
```

## Traçabilitat d'accions i límits

| Pàgina | Accions i transicions dins UC-118 | Evidència i estat |
| --- | --- | --- |
| P01/P02 | Consultar tram, triar curs, modalitat Grup/Centre escolar, tornar/continuar | PHP/JS ACTUAL documentats; política de composició multiproducte no acreditada en el formulari. |
| P03 | Contacte, CIF centre, validació, tornar/continuar | Dades personals via GET i relació contacte↔receptor no certificada. |
| P04 | Triar edició, afegir participant, modal, tornar/continuar, mínim 3 | Append de sessió actual, no reserva/alta real. Cap botó específic d'eliminar participant acreditat en el pas inspeccionat. |
| P05 | Veure resum, tram, total, tornar/enviar | Recompte de sessió però tarifa prèvia; no snapshot bloquejat actual. |
| P06 | Alta persistent contacte/membres, missatges i enllaç | IDPAG SELECT darrer+1, INSERT separats, no transacció/idempotència globals acreditades. |
| P07 | Pagament i receptor factura | Frontera amb UC-63, UC-16; builder real no substitueix gestor de grup ni decididor fiscal. |

**DOC:** 14 diagrames ACTUAL/FINAL per set passos/superfícies, amb PHP/JS llegat i disseny separats. **IMP/TEST:** no executats en aquesta auditoria. Altres variants de grup, gestió per intranet, alta/baixa postfactura i pagament bancari en viu no es declaren completats per aquests diagrames.
