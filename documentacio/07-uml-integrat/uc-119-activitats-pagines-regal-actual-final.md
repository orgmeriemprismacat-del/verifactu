# UC-119 — activitats per pàgina i apartat: ACTUAL / FINAL

**Codi font:** `main`, consulta 25/09/2026. **ACTUAL** = traça de PHP/JS versionats, no resultat productiu executat. **FINAL** = contracte de nou SIF no implementat; ni factura de compra ni cobrament es confonen amb previsualització, targeta o alta de beneficiari. El flux de devolució, canvi/baixa i caducitat sense pantalla concreta acreditada es documenta en la fitxa/UML com a **disseny**, no s'inventa una pàgina actual. [Fitxa funcional](../06-fitxes-funcionals/uc-119.md) · [auditoria lot 08](00-auditoria-casos-pendents-lot-08-uc-119-2026-09-25.md).

## P01 — Comprar — informació, tria d'hores o curs

**Fonts ACTUAL:** [RegalCurs.php](../../codi-drive/web-actual/RegalCurs.php#L71-L315) · [mostrarRegal.min.js](../../codi-drive/web-actual/js1619773569/mostrarRegal.min.js#L161-L186). RegalCurs.php L71–315; mostrarRegal.min.js L161–186, L576–610. El diagrama FINAL no és una prova que aquests controls ja operin al backend.

### P01 ACTUAL

```plantuml
@startuml
title UC119 P01 ACTUAL
start
:Obrir pagina regala un curs;
:PHP mostra cursos o hores i informacio de targeta;
if (Tria un curs o un nombre d'hores?) then (Si)
:JS obre formulari P02 pel producte triat;
else (No)
:Continuar consulta informativa;
endif
stop
@enduml
```

### P01 FINAL

```plantuml
@startuml
title UC119 P01 FINAL
start
:Mostrar productes/hores amb tarifa vigent del backend;
if (Persona tria producte/hores?) then (Si)
:Crear esborrany de compra sense dret activat;
:Guardar producte i versio de regla;
else (No)
:No crear cobrament ni dret;
endif
stop
@enduml
```

## P02 — Comprar — destinatari, origen, dedicatòria i preu

**Fonts ACTUAL:** [RegalCurs.php](../../codi-drive/web-actual/RegalCurs.php#L370-L650) · [mostrarRegal.min.js](../../codi-drive/web-actual/js1619773569/mostrarRegal.min.js#L187-L294). RegalCurs.php L370–490, L586–650; mostrarRegal.min.js L187–294. El diagrama FINAL no és una prova que aquests controls ja operin al backend.

### P02 ACTUAL

```plantuml
@startuml
title UC119 P02 ACTUAL
start
:JS consulta preu/hores/nom del curs via GET;
:PHP consulta regla de descompte de regal;
:Mostrar formulari de destinatari, origen i dedicatoria;
if (Continua amb camps validats pel JS?) then (Si)
:Guardar valors al navegador i obrir previsualitzacio P03;
else (No)
:Mostrar modal de camps incorrectes;
endif
stop
@enduml
```

### P02 FINAL

```plantuml
@startuml
title UC119 P02 FINAL
start
:Servidor calcula preu i condicions del regal segons curs/hores;
:Recollir destinatari/origen/dedicatoria de forma limitada;
if (Validesa de producte i dades?) then (Si)
:Guardar esborrany versionat sense exposar codi actiu;
else (No)
:Mostrar error sense emetre dret;
endif
stop
@enduml
```

## P03 — Comprar — previsualització de la targeta i canvi d'estil

**Fonts ACTUAL:** [RegalCurs.php](../../codi-drive/web-actual/RegalCurs.php#L655-L750) · [mostrarRegal.min.js](../../codi-drive/web-actual/js1619773569/mostrarRegal.min.js#L297-L351). RegalCurs.php L655–750; mostrarRegal.min.js L297–351. El diagrama FINAL no és una prova que aquests controls ja operin al backend.

### P03 ACTUAL

```plantuml
@startuml
title UC119 P03 ACTUAL
start
:PHP genera codi en previsualitzacio si encara es buit amb base_convert uniqid;
:Mostra codi i dades de targeta en HTML;
:JS canvia estil visual;
if (Boto Modifica?) then (Si)
:Tornar a P02;
else (Continua)
:JS llegeix codi de cnt-codi-regal i obre P04;
endif
stop
@enduml
```

### P03 FINAL

```plantuml
@startuml
title UC119 P03 FINAL
start
:Mostrar targeta de mostra sense credencial bescanviable activa;
if (Persona canvia estil/dades?) then (Si)
:Actualitzar nomes vista de l'esborrany;
else (Continua)
:Preparar dades del comprador sense activar dret;
endif
stop
@enduml
```

## P04 — Comprar — dades del comprador i crear comanda

**Fonts ACTUAL:** [enviarInscripcioRegal.php](../../codi-drive/web-actual/ajax/enviarInscripcioRegal.php) · [RegalCurs.php](../../codi-drive/web-actual/RegalCurs.php#L893-L1090). mostrarRegal.min.js L353–525/L664–715; ajax/enviarInscripcioRegal.php; RegalCurs.php L893–1090. El diagrama FINAL no és una prova que aquests controls ja operin al backend.

### P04 ACTUAL

```plantuml
@startuml
title UC119 P04 ACTUAL
start
:Recollir dades personals i fiscals del comprador;
:JS GET enviarInscripcioRegal amb codi, preu i percentatge del client;
:PHP prepara import i INSERT regal amb comprador, producte i codi;
if (Resposta no conte error?) then (Si)
:JS redirigeix a pagina confirmacio de comanda;
else (No)
:Mostra error;
endif
stop
@enduml
```

### P04 FINAL

```plantuml
@startuml
title UC119 P04 FINAL
start
:Validar comprador i receptor de factura al backend;
:Recalcular tarifa/regla de regal al servidor;
:Crear/reutilitzar comanda per clau idempotent i codi opac unic;
:Guardar estat PENDENT_PAGAMENT i snapshot econòmic;
:No activar dret per crear comanda;
:Retornar resultat tipificat sense valors secrets en URL;
stop
@enduml
```

## P05 — Comprar — generar PDF de targeta i pas a pagament

**Fonts ACTUAL:** [RegalCurs.php](../../codi-drive/web-actual/RegalCurs.php#L1418-L1445) · [PagamentRegal.php](../../codi-drive/web-actual/PagamentRegal.php). RegalCurs.php L1418–1445; PagamentRegal.php/PagamentRegalAutomatic.php. El diagrama FINAL no és una prova que aquests controls ja operin al backend.

### P05 ACTUAL

```plantuml
@startuml
title UC119 P05 ACTUAL
start
:RegalCurs genera PDF digital amb codi complet en nom del fitxer;
:Es desa targeta en targetes-regal i es prepara confirmacio;
:Pagina pagament regal utilitza la comanda/codi llegat;
if (Comprador inicia pagament?) then (Si)
:Anar a flux TPV/regal separat;
else (No)
:Regal continua pendent;
endif
stop
@enduml
```

### P05 FINAL

```plantuml
@startuml
title UC119 P05 FINAL
start
:Custodiar targeta i codi fora de rutes publiques persistents;
:Reservar el PDF comercial per entrega autoritzada despres de compra confirmada;
:Crear intent TPV sobre snapshot de compra immutable;
:No confondre visualitzacio/fitxer amb valor monetari ingressat;
stop
@enduml
```

## P06 — Comprar — resultat de Redsys i factura de compra

**Fonts ACTUAL:** [respostaPagamentRegal.php](../../codi-drive/web-actual/respostaPagamentRegal.php#L121-L321) · [builder SIF](../../sif/src/Service/LegacyGiftInvoicePayloadBuilder.php). respostaPagamentRegal.php L121–321; LegacyGiftSnapshotRepository.php; LegacyGiftInvoicePayloadBuilder.php. El diagrama FINAL no és una prova que aquests controls ja operin al backend.

### P06 ACTUAL

```plantuml
@startuml
title UC119 P06 ACTUAL
start
:Resoldre ordre i codi de regal a partir de la notificacio o retorn;
:Consultar dades regal i factura relacionada;
if (Camí de resultat exitós?) then (Si)
:Ruta llegada pot crear registre factures i UPDATE regal FACT_REL;
:Preparar correu amb codi i URL directa de targeta PDF;
else (No)
:Preparar resultat d'error i instruccions de pagament;
endif
stop
@enduml
```

### P06 FINAL

```plantuml
@startuml
title UC119 P06 FINAL
start
:Validar callback Redsys, import, firma i idempotencia al worker;
if (Cobrament extern confirmat?) then (Si)
:Registrar un CHARGE extern i factura de compra seguint UC fiscal;
:Activar dret GIFT unic per origen i emetre event;
:Encolar entrega autoritzada de targeta/codi sense segona factura;
else (No)
:No activar dret amb valor pagat ni declarar ingrés;
endif
stop
@enduml
```

## P07 — Bescanviar — introduir codi i consultar l'estat

**Fonts ACTUAL:** [BescanviaRegal.php](../../codi-drive/web-actual/BescanviaRegal.php#L145-L245) · [JS](../../codi-drive/web-actual/js1619773569/mostrarBescanvia.min.js#L139-L215). BescanviaRegal.php L145–245; mostrarBescanvia.min.js L139–215; ajax/codiRegalValid.php. El diagrama FINAL no és una prova que aquests controls ja operin al backend.

### P07 ACTUAL

```plantuml
@startuml
title UC119 P07 ACTUAL
start
:Persona introdueix codi regal en pagina bescanvia;
:JS GET codiRegalValid amb codi en URL;
:PHP consulta FACT_REL i USAT a regal;
if (No existeix, FACT_REL=0 o USAT no zero?) then (Si)
:Retorna text de codi invalid/pagament pendent/ja utilitzat;
else (No)
:JS GET buscarCursRegalat i recupera CCURS;
endif
stop
@enduml
```

### P07 FINAL

```plantuml
@startuml
title UC119 P07 FINAL
start
:Persona facilita codi per canal protegit i sense URL persistent;
:Backend valida dret, pagament origen real, estat i vigencia contractual;
if (Dret valid i disponible?) then (Si)
:Retornar opcions de curs sense dades del comprador ni token reutilitzable;
else (No)
:Retornar error minimitzat sense filtrar titular ni codi complet;
endif
:Consulta no consumeix el dret;
stop
@enduml
```

## P08 — Bescanviar — triar curs, edició i dades de la persona beneficiària

**Fonts ACTUAL:** [BescanviaRegal.php](../../codi-drive/web-actual/BescanviaRegal.php#L441-L787) · [JS](../../codi-drive/web-actual/js1619773569/mostrarBescanvia.min.js#L198-L230). mostrarBescanvia.min.js L198–230/L405–415/L775–820; BescanviaRegal.php L441–787. El diagrama FINAL no és una prova que aquests controls ja operin al backend.

### P08 ACTUAL

```plantuml
@startuml
title UC119 P08 ACTUAL
start
:JS interpreta CCURS com hores o codi de curs concret;
if (Regal per hores?) then (Si)
:Mostrar llistat de cursos compatibles per triar;
else (Curs concret)
:Obrir formulari del curs regalat;
endif
:Persona tria edicio i emplena dades personals/academiques;
:JS comprova camps i estat de formulari;
stop
@enduml
```

### P08 FINAL

```plantuml
@startuml
title UC119 P08 FINAL
start
:Servidor recupera tipus de dret, valor i regles de producte;
:Comprovar curs/hores/edicio elegibles i places disponibles;
if (Curs compatible i persona autoritzada?) then (Si)
:Preparar oferta/snapshot de bescanvi i import diferencial si permès;
else (No)
:Mostrar opcio alternativa o error sense reservar valor com a consum;
endif
stop
@enduml
```

## P09 — Bescanviar — alta d'inscripció i marcar regal utilitzat

**Fonts ACTUAL:** [enviarInscripcioBescanvia.php](../../codi-drive/web-actual/ajax/enviarInscripcioBescanvia.php#L422-L608) · [JS](../../codi-drive/web-actual/js1619773569/mostrarBescanvia.min.js#L923-L996). mostrarBescanvia.min.js L923–996; enviarInscripcioBescanvia.php L422–508/L602–608. El diagrama FINAL no és una prova que aquests controls ja operin al backend.

### P09 ACTUAL

```plantuml
@startuml
title UC119 P09 ACTUAL
start
:JS consulta inscripcioDuplicada per DNI/curs/edicio;
if (JS rep absencia de duplicat?) then (Si)
:GET enviarInscripcioBescanvia amb dades personals i codi;
:PHP llegeix FACT_REL de regal segons codi;
:INSERT inscripcions amb A_PAGAR=0 i FACTURA_RELACIONADA;
:Preparar missatges i token de confirmacio;
:UPDATE regal SET USAT=ID_INSC WHERE CODI=codi;
:JS redirigeix si la resposta no conte error;
else (No)
:Mostrar flux d'inscripcio duplicada;
endif
stop
@enduml
```

### P09 FINAL

```plantuml
@startuml
title UC119 P09 FINAL
start
:Backend autentica operacio, validesa del dret i absencia de duplicat;
:Bloquejar/reservar dret i places amb clau d'operacio idempotent;
if (Dret i curs/edicio encara disponibles?) then (Si)
:Crear/reutilitzar ID_INSC una sola vegada;
:Aplicar valor prepagat intern al curs amb referencia al CHARGE original;
:Registrar CONSUMED per la mateixa operacio i event traçable;
:Conciliar fallada entre BD de dret i alta llegada;
else (No)
:Cap doble alta, consum ni segon CHARGE;
endif
:Retornar estat confirmat sense exposar factura del comprador;
stop
@enduml
```

## Fronteres, traçabilitat i verificació

P01–P05: creació de targeta i comanda; P06: resultat bancari i factura del comprador; P07–P08: consulta del dret i preparació acadèmica; P09: vinculació d'inscripció i consum. **Canvi/baixa, reexpedició, caducitat i devolució** són accions diferenciades del cicle UC-119, amb pantalles reals no identificades exhaustivament en aquesta revisió; veure [UML UC-119](uc-119-cicle-complet-regal.md) i [auditoria de fonts i 14 proves](00-auditoria-casos-pendents-lot-08-uc-119-2026-09-25.md). **ACTUAL:** l'UPDATE de USAT segueix l'INSERT sense reserva/lock global acreditat. **FINAL:** un dret, una inscripció de destí per bescanvi d'ús únic i una sola entrada de caixa de compra. **DOC:** recorregut observat traçat; **IMP coordinador:** pendent; **TEST:** no executat; **PRODUCCIÓ:** no verificada.
