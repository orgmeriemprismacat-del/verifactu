# UC-125 — activitats ACTUAL/FINAL per butlletí, tastet, trobades i intranet

**Revisió:** 27/09/2026. **ACTUAL** = comportament observable al codi versionat; **FINAL** = contracte proposat. **Decisió específica UC-108 vigent des del 25/09/2026:** al tastet gratuït la subscripció al butlletí és obligatòria dins d'aquell cas, amb baixa posterior possible; substitueix el Sí/No opcional documentat el 22/09. [Fitxa](../06-fitxes-funcionals/uc-125.md) · [auditoria](00-auditoria-casos-pendents-lot-14-uc-125-2026-09-27.md) · [UML](uc-125-consentiment-comunicacions-separat.md).

## P01 · Home / footer — camp de butlletí i funció `news()`

### ACTUAL
```plantuml
@startuml
title UC125 P01 ACTUAL - Home/footer i IDs desacoblats
start
:Carregar formulari de butlleti;
:Usuari escriu email;
:news(idButlleti) valida #idButlleti;
if (Email valid?) then (Si)
 :Llegir correu des de #adreca-electronica fix;
 :Llegir honeypot #butlletiSpam;
 :GET mailingNou.php;
 if (Resposta == ok?) then (Si)
  :Mostrar subscripcio correcta + correu de confirmacio;
 else (No)
  :Mostrar error textual;
 endif
else (No)
 :Mostrar error de validacio;
endif
note right
 La home usa
 #butlleti-adreca-electronica
 i #comprovaSpam.
end note
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC125 P01 FINAL - Formulari consistent
start
:Identificar formulari/canal/finalitat;
:Llegir exactament l'email del control validat;
:Validar honeypot i format al servidor;
:Crear request idempotent amb notice version;
:Retornar estat tipificat;
:No declarar ACTIVE fins al punt definit per la politica del canal;
stop
@enduml
```

## P02 · Alta general `mailingNou.php`

### ACTUAL
```plantuml
@startuml
title UC125 P02 ACTUAL - Fila subscriptors abans de confirmacio
start
:GET correu + comprova;
if (Spam no buit o correu buit?) then (Si)
 :Retornar error textual;
else (No)
 :INSERT subscriptors(correu,data);
 :Enviar correu intern de sol.licitud;
 :Enviar correu al titular amb link /mailing/subscripcio.php?mail=...;
 :Retornar ok;
endif
note right
 El target de confirmacio
 no apareix al tree actual.
end note
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC125 P02 FINAL - Request, confirmacio i estat separats
start
:Crear o reutilitzar REQUESTED/PENDING segons politica;
:Persistir subjecte, finalitat, canal, text i evidencia;
if (Canal requereix confirmacio?) then (Si)
 :Emetre token opac d'un sol us;
 :Esperar confirmacio autentica;
 if (Token valid i vigent?) then (Si)
  :Registrar event CONFIRMED/ACTIVE;
 else (No)
  :Registrar rebuig/expiracio sense activar;
 endif
else (No)
 :Activar segons base i contracte aprovats;
endif
stop
@enduml
```

## P03 · Formularis de curs — `mailing.php` / `inscripcio_mailing.php`

### ACTUAL
```plantuml
@startuml
title UC125 P03 ACTUAL - Pregunta per existencia d'email
start
:Rebre email;
:SELECT mail FROM mailing WHERE mail = email;
if (No existeix?) then (Si)
 :Mostrar pregunta Si/No i valmail=1;
else (No)
 :Amagar pregunta i valmail=0;
endif
note right
 L'existencia del string email
 fa de proxy de decisio vigent.
end note
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC125 P03 FINAL - Estat per subjecte/finalitat/canal
start
:Resoldre SUBJECT_KEY;
:Consultar PURPOSE_CODE + CHANNEL + SCOPE_CODE;
:Carregar estat/event actual i notice version;
if (Cal demanar nova decisio?) then (Si)
 :Mostrar text versionat i opcio corresponent;
else (No)
 :Mostrar estat aplicable sense inferir per email;
endif
stop
@enduml
```

## P04 · Tastet gratuït — alta obligatòria de butlletí segons decisió 25/09

### ACTUAL
```plantuml
@startuml
title UC125 P04 ACTUAL - Tastet força alta mailing
start
:GET dades tastet inclos parametre mailing;
:Validar/normalitzar dades basiques;
:Fixar mailingBD = 1;
:INSERT inscripcions_reptes;
:Consultar mailing per email;
if (Email no existeix?) then (Si)
 :INSERT mailing(mail,nom,usuari);
endif
:Incloure al correu text que pressuposa alta i baixa posterior;
note right
 El valor mailing rebut
 no governa l'efecte actual.
end note
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC125 P04 FINAL - Regla obligatoria del tastet amb traça
start
:Mostrar condicio informativa del tastet i versio del text;
:Persona envia la sol.licitud gratuïta;
:Registrar alta academica independent;
:Registrar alta de butlleti exigida per la regla UC108;
:Guardar base aprovada, purpose, channel, scope, source i evidencia;
if (Fallada efecte comunicacions?) then (Si)
 :Conservar alta academica valida;
 :Deixar sincronitzacio pendent/reintentable;
endif
:Permetre retirada posterior sense esborrar historial ni inscripcio;
stop
@enduml
```

## P05 · Intranet alumne — editar `INSC_MAILING` amb altres camps

### ACTUAL
```plantuml
@startuml
title UC125 P05 ACTUAL - Flag mailing dins UPDATE mixt
start
:Operador edita modal d'inscripcio;
:GET dades personals + estat + AO + mailing + certificat + baixa;
:Wrapper delega guardarDadesPersonals_modalsresultatCerca;
:Actualitzar dades llegades segons metode Intranet;
:Repintar resultat segons resposta textual;
note right
 No hi ha event separat
 de finalitat/text/evidencia
 en el wrapper inspeccionat.
end note
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC125 P05 FINAL - Command separat de preferencia
start
:Editar dades personals/academiques pel seu UC;
if (Operador demana canvi de comunicacions?) then (Si)
 :Verificar rol, subjecte, finalitat i motiu;
 :Executar command UC125 separat;
 :Registrar event immutable i resultat de propagacio;
endif
:No derivar alta comercial d'un flag administratiu sense evidencia;
stop
@enduml
```

## P06 · Trobada individual — avís i butlletí general

### ACTUAL
```plantuml
@startuml
title UC125 P06 ACTUAL - Dues finalitats a la mateixa modal
start
:Obrir modal d'avisos de trobada;
if (Avisa'm 30 minuts abans no marcat?) then (Si)
 :Bloquejar Enviar;
else (No)
 :Validar email;
 :GET afegeix_usuari_mailing_trobada;
 :INSERT mailing_trobades(ID_TROBADA,email);
 if (Checkbox cursos/serveis marcat?) then (Si)
  :GET mailingNou.php pel mateix email;
 endif
endif
:Mostrar resultat de l'avís i, si aplica, butlleti;
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC125 P06 FINAL - Dos purposes independents
start
:Registrar EVENT_ALERT per trobada com una finalitat/scope;
if (Persona demana també butlleti general?) then (Si)
 :Crear command separat NEWSLETTER_GENERAL;
endif
:Propagar cada finalitat amb estat propi;
:Fallada d'una no desfà l'altra;
:Retirada d'una no pressuposa retirada de l'altra;
stop
@enduml
```

## P07 · Totes les trobades — una decisió, N destinacions

### ACTUAL
```plantuml
@startuml
title UC125 P07 ACTUAL - Afegir email a totes les trobades futures
start
:Validar checkbox avisa'm i email al JS;
:GET afegeix_usuari_mailing_totes_trobades;
:Buscar trobades futures actives de l'any;
repeat
 :Crear objecte Trobada;
 :INSERT email a mailing_trobades de la trobada;
repeat while (Queden trobades?) is (Si) not (No)
if (Checkbox cursos/serveis marcat?) then (Si)
 :JS crida mailingNou.php separat;
endif
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC125 P07 FINAL - Scope ALL_EVENTS
start
:Registrar una decisio EVENT_ALERT / ALL_EVENTS;
:Calcular destinacions aplicables;
repeat
 :Aplicar subscripcio idempotent al desti;
 :Guardar resultat per trobada;
repeat while (Queden destins?) is (Si) not (No)
:Permetre retirada del scope i cancel.lar futurs avisos pendents;
:No confondre aquest scope amb newsletter general;
stop
@enduml
```

## P08 · SIF — `communication_consent` / event, NOMÉS FINAL EXECUTABLE

**ACTUAL documentat:** la migració 000006 defineix l'esquema, però no s'ha acreditat servei/repository/connector PHP que l'executi. Per tant, no es dibuixa un workflow ACTUAL fictici.

```plantuml
@startuml
title UC125 P08 FINAL - Ledger de decisions i propagacio
start
:Resoldre SUBJECT_KEY;
:Rebre purpose, channel, scope, notice version i base aprovada;
:Calcular estat actual i validar concurrencia/idempotencia;
:Append communication_consent_event amb evidence hash;
:Actualitzar current state sense esborrar events;
:Enviar command al connector de comunicacions;
if (Destí confirma?) then (Si)
 :Registrar SYNCED/ACTIVE o WITHDRAWN aplicat;
else (No)
 :Registrar PENDING/FAILED i programar retry;
endif
:Abans de cada transport comercial, revalidar estat vigent;
stop
@enduml
```

## Cobertura

| P | ACTUAL | FINAL |
| --- | --- | --- |
| P01 | Home/footer amb mismatch d'IDs potencial | Formulari coherent i request idempotent |
| P02 | INSERT `subscriptors` + email de confirmació | Estats request/confirmació/active |
| P03 | Existència email a `mailing` | Subjecte/finalitat/canal/scope |
| P04 | Tastet força mailing=1 | Regla obligatòria UC108 + traça + baixa |
| P05 | `INSC_MAILING` en UPDATE mixt | Command separat i event |
| P06 | Avís trobada + butlletí opcional | Dos purposes |
| P07 | N files `mailing_trobades` | Scope ALL_EVENTS amb resultats per destí |
| P08 | DDL definit, cap workflow runtime acreditat | Ledger + connector/retry |

**DOC:** 15 diagrames PlantUML. **IMP:** scripts llegats presents; model SIF executable no acreditat. **TEST:** UC125-T01–T20 no executats. **PRODUCCIÓ:** no verificada.
