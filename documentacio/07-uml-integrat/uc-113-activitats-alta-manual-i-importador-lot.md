# UC-113 — Diagrames d'activitat d'alta manual i importador existent de matrícules en lot

**Revisió:** 22/09/2026; font PHP de `main` a `e71958b3026549bde09fb4b25f2ec3ba370937ec`; decisions de negoci confirmades a [fitxa UC-113](../06-fitxes-funcionals/uc-113.md). **No confondre:** importar matrícules en lot (existeix, codi concret pendent de mapar), crear una alta web, generar CSV per Moodle, importar el CSV al campus i canviar un curs a la intranet. Els diagrames de la web mostren les seccions funcionalment rellevants d'UC-113: no pretenen substituir els diagrames de totes les altres ofertes/variants del catàleg RM-037.

**Estats de font:** ACTUAL = comportament observat al PHP o confirmat per negoci; **L'IMPORTADOR EN LOT ESTÀ A LA INTRANET** segons negoci; la pàgina `cursos-inici-cursos-pujar-alumnes.php` i `Intranet::pujar_Inscripcions()` constitueixen components localitzats de la preparació de lots CSV, però encara cal verificar la correspondència completa amb l'importador de matrícules esmentat. ACTUAL — INTERIOR NO ACREDITAT = existeix el procés a la intranet segons negoci però no podem explicar-ne honestament totes les branques internes; FINAL OBJECTIU = especificació per adaptar i provar, **no** codi desplegat. No afirmar que l'importador funciona d'una manera no aportada per les fonts. Tots els diagrames separen el resultat acadèmic d'ingrés i factura.

## Índex de pàgines, apartats i frontera

| ID | Àmbit real | Apartats/activitats | Estat de traça |
| --- | --- | --- | --- |
| P113-01 | Web, inscripció curs / alta manual secretaria | Accés i curs, dades/preus excepcionals, alta i resultat | Alta web acreditada; mecanisme d'excepcions secretaria CONFIRMAT PER NEGOCI, codi específic no mapat. |
| P113-02 | Web, inscripció de grup | Responsables, participants i resultat per participant | Existència confirmada; diferenciar handlers de grup ordinari, amics i pack en el catàleg corresponent. |
| P113-03 | **Intranet: importador de matrícules en lot EXISTENT** | Entrada/execució i errors/repeticions; [pantalla de pujada d'alumnes](../../codi-drive/intranet-actual/cursos-inici-cursos-pujar-alumnes.php) i [mètode de generació de fila CSV](../../codi-drive/intranet-actual/Intranet.php#L4151-L4201) identificats per al recorregut de pujada. | Canal intranet confirmat; correspondència exacta de l'importador amb la preparació CSV i la seva càrrega final pendent d'acreditar: no inventar el parser. |
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

### P113-03.A · Importador existent: entrada i execució

**Fonts:** Confirmació de negoci que l'importador en lot existeix; implementació no identificada inequívocament en les fonts consultades.

**ACTUAL**

```plantuml
@startuml
title P113-03.A · Importador existent: entrada i execució | ACTUAL — INTERIOR NO ACREDITAT
start
:Operador utilitza l'importador EXISTENT de matrícules en lot;
note right
  Existència i canal INTRANET confirmats.
  La pàgina de pujada d'alumnes i el
  CSV existeixen en el repositori.
  Les classes internes de l'importador
  i el destí encara no s'han acreditat.
end note
:Executar importació en lot;
:Resultat real per fila pendent de recuperar de la font;
stop
@enduml
```

**FINAL / contracte d'adaptació**

```plantuml
@startuml
title P113-03.A · Importador existent: entrada i execució | FINAL OBJECTIU — ADAPTACIÓ DE L'EXISTENT
start
:Recuperar format i entrada REALS de l'importador existent;
:Autoritzar operador i validar origen;
:Registrar identificador de lot i resum verificable;
repeat
  :Validar fila i identitat de matrícula;
  if (Ja existeix alta equivalent?) then (Sí)
    :Reutilitzar destí o declarar conflicte;
  else (No)
    :Importar pel procés actual adaptat i recuperar ID real;
  endif
  :Persistir resultat o error d'aquesta fila;
repeat while (Hi ha més files?) is (Sí)
:Mostrar recompte i incidències reals;
:No crear CHARGE ni factura inferits d'una fila;
stop
@enduml
```

### P113-03.B · Lot repetit, error i represa

**Fonts:** Confirmació de negoci que l'importador en lot existeix; implementació no identificada inequívocament en les fonts consultades.

**ACTUAL**

```plantuml
@startuml
title P113-03.B · Lot repetit, error i represa | ACTUAL — NO ACREDITAT
start
:Operador reobre importador existent;
:Comportament de duplicats i errors NO localitzat en el codi;
:No afirmar reintent automàtic ni transacció global;
stop
@enduml
```

**FINAL / contracte d'adaptació**

```plantuml
@startuml
title P113-03.B · Lot repetit, error i represa | FINAL OBJECTIU
start
:Localitzar execució per clau i hash de font;
if (Lot anterior amb mateixes files?) then (Sí)
  :Recuperar estats de files realment processades;
else (No)
  :Registrar nova execució/version de font;
endif
:Reintentar només files pendents amb comprovació del destí;
:Conciliar altes confirmades després de timeout;
:Mostrar errors per fila, sense esborrar altes ja fetes;
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

| Traça | Evidència per acceptar-ne el tancament |
| --- | --- |
| P113-01 | Ruta i permisos reals de secretaria, oferta extraordinària i validació servidor; captura completa de cada pantalla real del formulari. |
| P113-02 | Desglossar grup ordinari vs amics/pack i les seves pantalles reals; comprovar cada receptor, participant i import. |
| P113-03 | Localitzar i llegir codi/procediment real de l'importador EXISTENT, mostrar format de dades anonimitzat i flux intern actual per cada apartat abans de substituir el seu diagrama de frontera per un diagrama complet. |
| P113-04 | Remetre a diagrames propis d'UC-026; no sumar-lo erròniament a importació. |
| Tot el cas | Tests [113-AT-01–10](../06-fitxes-funcionals/uc-113.md#9-proves-dacceptació-per-executar-no-resultats), autoritzacions i absència de cobraments/factures inventats. **NO EXECUTATS**. |

**Resultat honest:** el contracte funcional de la part confirmada està escrit. **No és possible certificar un diagrama d'activitat ACTUAL complet de l'importador només a partir de la seva existència:** cal verificar-ne l'artefacte executable o un procediment que descrigui les branques internes. Això és una feina de recuperació de codi/traça, no una nova pregunta genèrica a la usuària.
