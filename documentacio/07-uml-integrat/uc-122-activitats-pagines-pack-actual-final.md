# UC-122 — activitats per pàgina i apartat de pack, ACTUAL/FINAL

**Contrast:** 25/09/2026, codi `main`. **ACTUAL:** codi de catàleg, compra i generació fiscal del pack **inicial**; no existeix en les fonts revisades una pàgina de substitució individual d'un component. **FINAL:** disseny de recomposició versionada per línia; no implementat pel fet de dibuixar-lo. [Fitxa](../06-fitxes-funcionals/uc-122.md) · [auditoria lot 11](00-auditoria-casos-pendents-lot-11-uc-122-2026-09-25.md) · [UML](uc-122-composicio-pack-component-indisponible.md).

## P01 — llistat / filtres de «Packs de cursos»

**Fonts:** [`pagina_packs.php`](../../codi-drive/web-actual/pagina_packs.php), [`mostrar_packs.php`](../../codi-drive/web-actual/ajax/mostrar_packs.php), [`buscantPacksDisponibles.php`](../../codi-drive/web-actual/inc/buscantPacksDisponibles.php).

### ACTUAL
```plantuml
@startuml
title UC122 P01 ACTUAL - Llistat i filtres
start
:Persona obre llistat Packs de cursos;
:JS carrega filtres i resultat AJAX;
:PHP cerca packs publicats/disponibles segons criteris llegats;
if (Hi ha packs visibles?) then (Si)
 :Mostrar targetes i enllac a cada pack;
else (No)
 :Mostrar estat buit o error de llistat;
endif
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC122 P01 FINAL - Cataleg informatiu
start
:Consultar packs/version actual i elegibilitat comercial;
:Mostrar components i condicions sense prometre plaça;
if (Persona escull pack?) then (Si)
 :Carregar detall i disponibilitat inicial per component;
endif
:No crear reserva ni cobrar per mostrar llistat;
stop
@enduml
```

## P02 — fitxa individual, components/edicions/preu i «Inscriu-te»

**Fonts:** [`pagina_pack.php`](../../codi-drive/web-actual/pagina_pack.php), [`mostrar_pack.php`](../../codi-drive/web-actual/ajax/mostrar_pack.php), [`InfoPack.php` L155–255 i L1772–1808](../../codi-drive/web-actual/InfoPack.php#L1772-L1808).

### ACTUAL
```plantuml
@startuml
title UC122 P02 ACTUAL - Detall i CTA
start
:Obrir fitxa d'un pack;
:InfoPack carrega components publicats, edicions i preu;
:Mostrar descripcio, edicions i disponibilitat comercial;
if (El botó d'inscripcio es mostra disponible?) then (Si)
 :Enllacar a formulari d'inscripcio del pack;
else (No)
 :Mostrar Inscripcio no disponible;
endif
note right
 Visibilitat/comprovacio comercial
 no equival a reserva de plaça
 de tots els components.
end note
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC122 P02 FINAL - Composicio i disponibilitat per component
start
:Carregar versio de pack i ordre comercial autentic;
repeat
 :Comprovar curs/edicio i places del component;
repeat while (Queden components?) is (Si) not (No)
if (Tots els obligatoris estan disponibles?) then (Si)
 :Mostrar oferta i condicions per linia;
 :Permetre iniciar esborrany;
else (No)
 :Deshabilitar confirmacio de pack no disponible;
 :Mostrar alternativa nomes si la regla autoritza substituir;
endif
stop
@enduml
```

## P03 — formulari d'inscripció: edicions, dades personals/curriculars, preu i comentaris

**Fonts:** [`pagina_inscripcio_pack.php`](../../codi-drive/web-actual/pagina_inscripcio_pack.php), [`InscripcioPack.php` L84–146 i L298–349](../../codi-drive/web-actual/InscripcioPack.php#L84-L146), [JS L172–390](../../codi-drive/web-actual/js1619773569/mostrarInscripcioPack.min.js#L172-L390).

### ACTUAL
```plantuml
@startuml
title UC122 P03 ACTUAL - Formulari pack
start
:Obrir formulari d'inscripcio pack;
:PHP consulta packs i cursos visibles ORDER BY c.DATAI;
:Mostrar edicions, dades personals/curriculars, preu, comentaris;
:Persona omple camps i prem Enviar dades;
:JS valida camps del formulari;
if (Validacio client favorable?) then (Si)
 :Cridar l'enviament P05;
else (No)
 :Mostrar errors en pantalla;
endif
note right
 No s'ha identificat selector
 de substitucio individual al
 formulari actual inspeccionat.
end note
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC122 P03 FINAL - Esborrany pack versionat
start
:Carregar oferta per linia i edicio amb UUID_OPERATION;
:Recollir participant i dades necessaries amb permisos;
:Validar cada component, preu, descompte i places al backend;
if (Algun component no disponible?) then (Si)
 :Obrir proposició de substitucio P08 segons regla;
else (No)
 :Mostrar resum del pack amb tots els imports per linia;
endif
:No confirmar factura/pagament amb dades de client no revalidades;
stop
@enduml
```

## P04 — consulta i presentació de preus totals del pack

**Fonts:** [`obtenirPreusPack.php`](../../codi-drive/web-actual/ajax/obtenirPreusPack.php), [JS L397–455 i L521–524](../../codi-drive/web-actual/js1619773569/mostrarInscripcioPack.min.js#L397-L455).

### ACTUAL
```plantuml
@startuml
title UC122 P04 ACTUAL - Preu del conjunt
start
:JS consulta idPack i ID_PREU del pack;
:GET obtenirPreusPack;
:PHP suma preu.IMPORT dels cursos publicats;
:PHP consulta preu.IMPORT vigent del pack;
:Retornar preuOriginal|preuPack;
:JS mostra els dos imports globals;
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC122 P04 FINAL - Tarifa per component
start
:Carregar versio i ordinal real del pack;
repeat
 :Calcular base, descompte, impost i net del component;
 :Anotar regla i versio de preu per linia;
repeat while (Queden components?) is (Si) not (No)
:Sumar imports amb decimals i validar total del pack;
:Retornar imports, composicio i condicions d'oferta;
stop
@enduml
```

## P05 — «Enviar dades», alta de tots els cursos i IDPAG compartit

**Fonts:** [JS L1237–1328](../../codi-drive/web-actual/js1619773569/mostrarInscripcioPack.min.js#L1237-L1328), [`enviarInscripcioPack.php` L1–78, 125–161, 196–224 i 486–529](../../codi-drive/web-actual/ajax/enviarInscripcioPack.php#L486-L529).

### ACTUAL
```plantuml
@startuml
title UC122 P05 ACTUAL - Alta dels components
start
:GET enviarInscripcioPack amb idPack, preuCursos i preuPack client;
:PHP busca pack actiu i cursos/edicions publicats;
:Consultar darrer IDPAG i incrementar 1 al PHP;
:Preparar URL de pagament xifrada per IDPAG;
:aux = preuPack rebut;
repeat
 :Llegir preu ordinari vigent del curs;
 :Assignar A_PAGAR del component segons aux i preu ordinari;
 :INSERT inscripcions TIPUS_INSC=P, IDPAG comu, OBSERVACIONS PACK|idPack;
repeat while (Hi ha mes cursos?) is (Si) not (No)
:Retornar token de confirmacio;
:JS redirigeix a packs/confirmacio;
note right
 No s'ha acreditat revalidacio
 server-side del total preuPack,
 ni reserva per component ni
 transaccio/idempotencia globals
 en aquests fragments.
end note
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC122 P05 FINAL - Commit del pack coherent
start
:Rebre ordre autenticada amb UUID_OPERATION i versio acceptada;
:Backend rellegeix preu/edicions i valida components obligatoris;
:Bloquejar disponibilitat i quota de cada component;
if (Totes les linies coherents?) then (Si)
 :Persistir en transaccio composicio, ordinal i imports;
 :Assignar identificadors unics i crear reserves per linia;
 :Congelar snapshot i crear intencio de pagament quan pertoqui;
else (No)
 :Rebutjar alta parcial i obrir proposta P08 si escau;
endif
stop
@enduml
```

## P06 — confirmació, enllaç i frontera de pagament

**Fonts:** [JS L1310–1328](../../codi-drive/web-actual/js1619773569/mostrarInscripcioPack.min.js#L1310-L1328), [`enviarInscripcioPack.php` L196–224](../../codi-drive/web-actual/ajax/enviarInscripcioPack.php#L196-L224).

### ACTUAL
```plantuml
@startuml
title UC122 P06 ACTUAL - Enllac del pack
start
:Alta retorna token/identificador de confirmacio;
:JS navega a packs/confirmacio;
:Missatge del PHP prepara URL de pagaments xifrant IDPAG;
:Flux bancari i emissio posteriors fora del punt de l'alta;
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC122 P06 FINAL - Pagament sobre snapshot valid
start
:Consultar operacio, linies i reserves vigents;
if (Algun component queda indisponible abans de cobrar?) then (Si)
 :Bloquejar intencio i derivar a P08;
else (No)
 :Pagar sobre snapshot acceptat sense reassignar preus;
 :Registrar nomes cobrament bancari efectiu;
endif
stop
@enduml
```

## P07 — lectura de components i construcció de factura inicial

**Fonts:** [`LegacyPackSnapshotRepository.php` L9–64, 103–119](../../sif/src/Repository/LegacyPackSnapshotRepository.php#L9-L64) i [`LegacyPackInvoicePayloadBuilder.php` L11–68, 119–189](../../sif/src/Service/LegacyPackInvoicePayloadBuilder.php#L119-L189).

### ACTUAL
```plantuml
@startuml
title UC122 P07 ACTUAL - Builder factura inicial llegat
start
:Carregar inscripcions TIPUS_INSC=P per IDPAG;
:Ordenar per A_PAGAR DESC i ID;
:Recuperar ID_PACK del marcador PACK a OBSERVACIONS;
if (Hi ha almenys dues linies?) then (Si)
 :Triar primera inscripcio per billing;
 repeat
  :Construir linia fiscal per component;
  if (Base explicita disponible?) then (Si)
   :Usar base/descompte explicit de la linia;
  else (No)
   :Per index zero, base=total sense descompte;
   :Des de index u, reconstruir base=total/0.75;
  endif
  :Assignar EXEMPT i IVA zero al builder;
 repeat while (Queden components?) is (Si) not (No)
 :Retornar payload amb relacions del pack i les inscripcions;
else (No)
 :Rebutjar pack amb menys de dues linies;
endif
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC122 P07 FINAL - Factura des de linies congelades
start
:Carregar UUID_OPERATION i UUID_LINE acceptats;
:Verificar preu base, descompte, net i regim de cada component;
if (Manca ordre/preu fiscal fiable d'un component?) then (Si)
 :Bloquejar emissio automatica i obrir incidencia;
else (No)
 :Construir factura inicial amb receptor validat i N linies;
 :Vincular factura_linia a cada UUID_LINE/ID_INSC;
endif
:No reconstruir ordinal comercial ordenant per A_PAGAR;
stop
@enduml
```

## P08 — component indisponible: recomposició del pack (NOMÉS FINAL)

**ACTUAL no acreditat:** cap pantalla/servei del recorregut públic inspeccionat que permeti substituir un component del pack i recalcular-ne l'efecte fiscal/econòmic després de l'alta. **No dibuixar una activitat ACTUAL fictícia.**

### FINAL
```plantuml
@startuml
title UC122 P08 FINAL - Gestio per component indisponible
start
:Identificar UUID_LINE, ID_INSC i producte/edicio afectats;
:Consultar plaça, obligatorietat, alternativa i estat de caixa/factura;
if (Pack encara no cobrat ni facturat?) then (Si)
 if (Component substituible i alternativa disponible?) then (Si)
  :Reservar alternativa, recalcular totes les linies i total;
  :Mostrar nova proposta per acceptacio;
  if (Acceptada?) then (Si)
   :Congelar versio nova i renovar intencio quan correspongui;
  else (No)
   :No cobrar; alliberar reserva provisional quan pertoqui;
  endif
 else (No)
  :Bloquejar alta/pagament o gestionar cancel.lacio segons regla;
 endif
else (No)
 :Preservar moviment bancari i factura/linies originals;
 :Classificar canvi, baixa parcial o cancel.lacio per component;
 :Determinar valor atribuït i titular, document fiscal i ajust monetari;
 :Registrar events, nova operacio i sincronitzacio academica si escau;
endif
stop
@enduml
```

## Matriu de cobertura

| Pàgina / component | ACTUAL observat | FINAL dissenyat |
| --- | --- | --- |
| P01 | Llistat/filtres de packs | Visibilitat sense promesa de plaça |
| P02 | Fitxa components/edicions/CTA | Disponibilitat per línia |
| P03 | Formulari amb components fixats per pack | Esborrany/composició versionada |
| P04 | Dos totals de BD, resposta al JS | Base/descompte/impost per línia |
| P05 | GET amb preu client + N INSERT i IDPAG comú | Transacció/lock/versió per línia |
| P06 | URL i confirmació d'alta, no cobrament provat | Validació comercial abans de TPV |
| P07 | Lector + builder de factura inicial llegat | Ordinal i preu fiscal congelats |
| P08 | **No hi ha gestor de substitució acreditat** | Canvi abans/després de factura per component |

**DOC:** 15 diagrames PlantUML per P01–P08, P08 només FINAL. **IMP:** consulta/alta i builders existents; recomposició versionada per component i reserva/ajust monetari no acreditats. **TEST:** UC122-T01–T16 definits, no executats. **PRODUCCIÓ:** no verificada.
