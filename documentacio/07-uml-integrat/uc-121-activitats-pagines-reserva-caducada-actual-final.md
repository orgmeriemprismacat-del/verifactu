# UC-121 — activitats de renovació de reserva/enllaç per pàgina i acció ACTUAL/FINAL

**Revisió:** 25/09/2026. **ACTUAL** = flux estàticament observable al codi `main`; **FINAL** = contracte no implementat íntegrament. UC-121 no té una pàgina llegada específica «Renova reserva» acreditada: P01–P03 són els punts d'entrada actuals de pagament; P04–P05 són components backend reals; P06 és **només FINAL**. [Fitxa](../06-fitxes-funcionals/uc-121.md) · [UML](uc-121-repreuar-renovar-reserva-caducada.md) · [auditoria lot 10](00-auditoria-casos-pendents-lot-10-uc-121-2026-09-25.md).

## P01 · Web pagament de curs, consulta de dades i imports

**Font:** [`PagamentCursAutomatic.php` L26–117, L265–317](../../codi-drive/web-actual/PagamentCursAutomatic.php#L265-L317). Llegeix `IDPAG` i `A_PAGAR/PAGAMENT`; consulta dades d'edició `DATAI/DATAF`, que no són el venciment d'una reserva de plaça.

### ACTUAL
```plantuml
@startuml
title UC121 P01 ACTUAL - Mostrar pagament de curs llegat
start
:Obrir pagina de pagament amb IDPAG;
:Buscar inscripcio i edicio al llegat;
:Recuperar A_PAGAR, PAGAMENT, fraccionament i descompte;
:Calcular faltaPagar = A_PAGAR - PAGAMENT;
if (Hi ha import pendent?) then (Si)
 :Mostrar opcions targeta/transferencia segons les branques del curs;
else (No)
 :Mostrar import pagat en totalitat;
endif
note right
 En aquest metode no hi ha
 renovacio de capacity_reservation
 ni nou snapshot de tarifa.
end note
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC121 P01 FINAL - Consultar dret comercial abans de pagar
start
:Carregar operacio, intencio, enllac, reserva i pagaments reals;
:Validar actor/identitat, vigencia de cada element i places;
if (Ja hi ha cobrament real o factura?) then (Si)
 :Reconciliar i derivar a UC economic/fiscal; no renovar com impagat;
else (No)
 if (Oferta/reserva caducada?) then (Si)
  :Bloquejar inici de pagament antic;
  :Obrir proposta de renovacio P06;
 else (No)
  :Mostrar import vigent del snapshot acceptat;
 endif
endif
stop
@enduml
```

## P02 · Web confirmació/targeta d'inscripció pendent

**Font:** [`PagamentCursAutomatic.php` L328–395, L431–498](../../codi-drive/web-actual/PagamentCursAutomatic.php#L431-L498); [JS confirmació L140–248](../../codi-drive/web-actual/js1619773569/mostrarConfirmacioInscripcioAutomatic.min.js#L140-L248).

### ACTUAL
```plantuml
@startuml
title UC121 P02 ACTUAL - Formulari pagament llegat
start
:Mostrar confirmacio amb import de la inscripcio;
if (Branca de validacio permet targeta?) then (Si)
 :Generar formulari amb titular, document, import i IDPAG;
 :JS valida els camps del formulari;
 if (Persona confirma?) then (Si)
  :Enviar al flux efectPagAuto;
 endif
else (No)
 :Mostrar missatge/altres formes segons branca actual;
endif
note right
 L'enllac IDPAG no es un registre
 payment_link SIF acreditat.
 No es mostra proposta renovada.
end note
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC121 P02 FINAL - Formulari condicionat a oferta acceptada
start
:Carregar snapshot de la versio de compra vigent;
:Validar enllac/reserva/edicio i cobrament ja existent;
if (Intent o plaça caducats?) then (Si)
 :Deshabilitar checkout vell;
 :Mostrar proposta renovada P06 si encara disponible;
else (No)
 :Mostrar import, receptor i condicions de la intencio valida;
 :Acceptar les dades del pagador;
 :Enviar ordre TPV segons UC-63 sense modificar snapshot;
endif
stop
@enduml
```

## P03 · Portal alumne, enllaç a pagament d'una inscripció

**Font:** [`ajax/cursos/obtenirUrlPagament.php`](../../codi-drive/intranet-alumne-actual/ajax/cursos/obtenirUrlPagament.php), [`IntranetAlumne.php` L2947–2978](../../codi-drive/intranet-alumne-actual/IntranetAlumne.php#L2947-L2978).

### ACTUAL
```plantuml
@startuml
title UC121 P03 ACTUAL - Obtenir URL des d'intranet alumne
start
:AJAX GET tipusInsc i idPag;
:Recuperar claus configurades per al xifrat;
:Xifrar idPag amb IV/HMAC;
if (Clau no buida i idPag positiu?) then (Si)
 :Tornar URL pagament o pagaments per tipus G;
else (No)
 :No construir URL valida;
endif
note right
 El metode no consulta EXPIRES_AT,
 tarifa nova o places disponibles.
end note
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC121 P03 FINAL - Enllac d'oferta vigent
start
:Verificar actor/inscripcio i dret a consultar checkout;
:Carregar operacio, intencio, link, reserva i estat bancari;
if (Pagament real pendent de conciliar?) then (Si)
 :Mostrar estat pendent/incident sense segon TPV;
else (No)
 if (Link i reserva vigents?) then (Si)
  :Tornar URL curta opaca de l'operacio acceptada;
 else (No)
  :Marcar antic inaccessible per iniciar pagament;
  :Oferir P06 amb nova proposta, si correspon;
 endif
endif
stop
@enduml
```

## P04 · SIF, crear/reutilitzar una intenció per DS_ORDER

**Font:** [`RedsysPaymentIntentService.php` L19–85](../../sif/src/Service/RedsysPaymentIntentService.php#L19-L85). És PHP real del SIF però no gestiona per si sol UC-121 complet.

### ACTUAL
```plantuml
@startuml
title UC121 P04 ACTUAL - Intent Redsys existent
start
:Rebre ds_order, snapshot, import i expires_at opcional;
:Validar snapshot i camps de la intencio;
:Buscar intent existent per la mateixa DS_ORDER;
if (Ja existeix?) then (Si)
 if (Tot el payload coincideix inclos venciment?) then (Si)
  :Reutilitzar UUID_INTENT anterior;
 else (No)
  :Llençar conflicte sense alterar ordre antiga;
 endif
else (No)
 :Inserir nou registre redsys_payment_intent PENDING;
endif
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC121 P04 FINAL - Intencio de nova oferta validada
start
:Rebre nova operacio/versio i acceptacio del pagador;
:Verificar reserva vigent, tarifes/parts i absencia de doble cobrament;
if (Snapshot acceptat i lloc disponible?) then (Si)
 :Generar DS_ORDER diferent de l'antiga;
 :Crear intent amb snapshot i expires_at de la nova oferta;
 :Vincular UUID_INTENT amb operacio i enllac nous;
else (No)
 :Denegar creacio o deixar incident sense activar TPV;
endif
stop
@enduml
```

## P05 · SIF, callback signat de l'ordre antiga o renovada

**Font:** [`RedsysCallbackService.php` L21–90, L134–153](../../sif/src/Service/RedsysCallbackService.php#L134-L153). Confirmació de la notificació **no és confirmació de plaça comercial**.

### ACTUAL
```plantuml
@startuml
title UC121 P05 ACTUAL - Callback Redsys parcial
start
:Rebre callback signat o dades preautoritzades;
:Validar signatura d'entrada i format;
:Carregar intencio per DS_ORDER amb lock;
:Comprovar import, divisa i terminal;
if (Codi Redsys acceptat?) then (Si)
 :Registrar notificacio VALIDATED i encolar job;
else (No)
 :Registrar resultat ERROR;
endif
note right
 No compara EXPIRES_AT de l'intent,
 payment_link o reserva de plaça
 en aquest metode.
end note
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC121 P05 FINAL - Callback tardà i cobrament real
start
:Validar callback i registrar evidencia de moviment real;
:Relacionar DS_ORDER amb operacio/versio old o new;
if (Intencio antiga, reserva vençuda o replaced?) then (Si)
 :No concedir plaça ni servei automaticament;
 :Conciliar ingressos reals, intents i possible nova ordre;
 if (Cobrament doble real?) then (Si)
  :Obrir incidencia d'exces/retorn segons titularitat;
 else (No)
  :Classificar aplicacio o recuperacio autoritzada del pagament;
 endif
else (No)
 :Processar operacio vigent amb idempotencia;
endif
:Preservar factura existent i historial de moviments;
stop
@enduml
```

## P06 · Gestió SIF de renovar reserva caducada — només FINAL

**No hi ha pàgina/worker ACTUAL acreditat que compari un preu nou, revoqui enllaç, reservi plaça i demani acceptació.** La definició SQL de `payment_link` i `capacity_reservation` no es dibuixa com una pantalla ja operativa.

### FINAL
```plantuml
@startuml
title UC121 P06 FINAL - Nova proposta de reserva i preu
start
:Obrir expedient d'operacio/reserva caducada;
:Consultar callback/jobs i pagaments reals de DS_ORDER antiga;
if (Moviment bancari real o estat incert?) then (Si)
 :Conciliar abans de crear altra intencio;
 if (No es pot resoldre?) then (Si)
  :Deixar incident pendent i bloquejar segon TPV;
  stop
 endif
endif
:Revalidar edicio, places, tarifa i regles vigents;
if (Sense plaça/edicio inviable?) then (Si)
 :No obrir nova intencio de cobrament;
 :Comunicar alternativa nomes si esta permesa;
 stop
endif
:Preparar reserva nova i proposta versionada;
:Mostrar comparacio amb antiga i venciment nou;
if (Pagador accepta explicitament?) then (Si)
 :Revocar enllac antic i associar REPLACED_BY_UUID;
 :Congelar snapshot nou;
 :Crear nou enllac i DS_ORDER amb import nou;
 :Confirmar reserva segons politica;
else (No)
 :No cobrar ni facturar nova proposta;
 :Alliberar reserva provisional quan correspongui;
endif
stop
@enduml
```

## Traçabilitat i límits

P01–P03 són **pàgines/punts web ACTUALS de pagament**, no pàgines específiques de renovació. P04–P05 són serveis SIF reals d'intenció/callback, **no** evidència d'un coordinador comercial de reserva. P06 és només disseny FINAL. La tolerància numèrica, política de disponibilitat sense plaça, acceptació amb import idèntic i gestió de reserva de descompte continuen com a decisions de negoci per formalitzar; no se n'ha inventat cap valor. **DOC:** 11 activitats PlantUML; **IMP:** parcial només en intenció/callback; **TEST:** cap executat; **PRODUCCIÓ:** no verificada.
