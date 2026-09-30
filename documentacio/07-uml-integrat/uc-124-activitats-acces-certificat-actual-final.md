# UC-124 — activitats ACTUAL/FINAL per accés, certificat, deute, baixa i pagador

**Revisió:** 26/09/2026. **ACTUAL** = comportament observable a `main`; **FINAL** = contracte proposat de reconciliació. Els diagrames no converteixen DDL o wrappers en serveis ja desplegats. [Fitxa](../06-fitxes-funcionals/uc-124.md) · [auditoria lot 13](00-auditoria-casos-pendents-lot-13-uc-124-2026-09-26.md) · [UML](uc-124-reconciliar-acces-certificat-baixa-deute.md).

## P01 · Portal alumne — cursos pendents/cursant

### ACTUAL
```plantuml
@startuml
title UC124 P01 ACTUAL - Cursos pendents/cursant
start
:Carregar inscripcions de l'usuari amb INSC CURS 0/1 i curs no acabat;
:Mostrar curs, preu, A_PAGAR-PAGAMENT i estat;
if (ENTITAT no buida?) then (Si)
 :Mostrar etiqueta PAGA L'ENTITAT;
 if (Hi ha pendent?) then (Si)
  :Mostrar "-" en lloc de botó individual;
 endif
else (No)
 if (Hi ha pendent?) then (Si)
  :Mostrar boto pay-IDPAG-TIPUS_INSC;
 endif
endif
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC124 P01 FINAL - Estat economic i pagador separats
start
:Carregar enrollment i operacio comercial canoniques;
:Resoldre responsable del pagament i assignacions reals;
:Mostrar deute informatiu de la persona/operacio;
if (Actor es pagador autoritzat i hi ha deute exigible?) then (Si)
 :Oferir via de regularitzacio autoritzada;
else (No)
 :Mostrar estat sense crear enllac individual;
endif
:No decidir certificat ni accés en aquesta vista;
stop
@enduml
```

## P02 · Portal alumne — cursos acabats, certificat i Aula Oberta

### ACTUAL
```plantuml
@startuml
title UC124 P02 ACTUAL - Certificat i Aula Oberta segons A_PAGAR-PAGAMENT
start
:Carregar curs acabat i camps CERTIFICAT, PERENNE, ENTITAT;
:Calcular faltaPagar = A_PAGAR - PAGAMENT;
if (faltaPagar > 0?) then (Si)
 :Mostrar boto de pagament per IDPAG/TIPUS_INSC;
 :Forcar estat certificat a pendent-pagar;
 :No mostrar accio Aula Oberta;
else (No)
 :Classificar CERTIFICAT per text llegat;
 if (PERENNE == 1) then (Si)
  :Mostrar ACCEDIR-HI;
 elseif (PERENNE == X) then (Si)
  :Mostrar DEMANAR-HI ACCES;
 elseif (PERENNE == 0) then (Si)
  :Mostrar ACCES EN TRAMIT;
 endif
endif
note right
 La branca de pendent no comprova ENTITAT,
 a diferencia de P01.
end note
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC124 P02 FINAL - Decisio academica versionada
start
:Carregar estat academic, superacio, certificat i accés;
:Carregar snapshot economic i responsable del pagament;
:Aplicar regla versionada aprovada;
if (Decisio determinista?) then (No)
 :Marcar REVIEW_REQUIRED;
else (Si)
 :Calcular dret de certificat i accés per separat;
endif
:Mostrar motiu i estat sense mutar pagaments;
stop
@enduml
```

## P03 · Portal alumne — obtenir URL de pagament

### ACTUAL
```plantuml
@startuml
title UC124 P03 ACTUAL - URL de pagament
start
:Clic al boto payInsc;
:GET tipusInsc i idPag;
:Backend llegeix keyEncriptar;
:Xifrar IDPAG;
if (idPag > 0 i clau disponible?) then (Si)
 if (TIPUS_INSC == G?) then (Si)
  :Retornar /pagaments/token;
 else (No)
  :Retornar /pagament/token;
 endif
endif
note right
 El metode no valida responsabilitat
 de pagament ni deute canonic.
end note
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC124 P03 FINAL - Regularitzacio autoritzada
start
:Rebre enrollment/operation, no IDPAG lliure;
:Verificar actor, pagador, import pendent i estat de checkout;
if (Actor pot pagar?) then (No)
 :Denegar sense revelar dades del pagador;
else (Si)
 :Crear/reutilitzar enllac de l'operacio correcta;
 :Retornar URL opaca amb caducitat/estat;
endif
stop
@enduml
```

## P04 · Portal alumne — Aula Oberta

### ACTUAL
```plantuml
@startuml
title UC124 P04 ACTUAL - Aula Oberta i PERENNE
start
if (PERENNE == 1?) then (Si)
 :GET curs Moodle per shortname;
 :Obrir URL del curs al campus nou o antic;
elseif (PERENNE == X?) then (Si)
 :Mostrar modal de consentiment;
 if (Persona demana accés?) then (Si)
  :GET solicitaAccesAulaOberta;
  :subscripcioAulaOberta posa PERENNE=0;
  :Preparar correu a Secretaria i alumne;
 endif
else (PERENNE == 0)
 :Mostrar ACCES EN TRAMIT;
endif
note right
 No s'acredita alta de matricula Moodle
 dins subscripcioAulaOberta.
end note
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC124 P04 FINAL - Gateway d'accés Moodle
start
:Consultar dret d'accés aprovat i estat Moodle real;
if (Ja aplicat?) then (Si)
 :Registrar NO_CHANGE/APPLIED;
else (No)
 :Crear ordre idempotent de sincronitzacio;
 :Aplicar alta/baixa/rol al gateway Moodle;
 :Fer read-back del desti;
 if (Read-back coherent?) then (Si)
  :Registrar APPLIED;
 else (No)
  :Registrar PARTIAL/ERROR i retry pendent;
 endif
endif
stop
@enduml
```

## P05 · Intranet — Control de morosos

### ACTUAL
```plantuml
@startuml
title UC124 P05 ACTUAL - Control de morosos per categories
start
:Obrir Control de morosos;
:Carregar tres llistats;
fork
 :Entitats amb reclamacio;
fork again
 :Alumnes aprovats sense certificat;
fork again
 :Alumnes amb certificat;
end fork
:Operador marca files;
:POST idInsc a writer especific de la categoria;
:Mostrar exit/error segons text retornat;
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC124 P05 FINAL - Reclamacio sense decidir dret academic
start
:Carregar deutes reals, pagador i participants;
:Classificar reclamacio economica;
:Mostrar certificat/access com dimensions informatives;
if (Enviar reclamacio?) then (Si)
 :Registrar accio de cobrament al responsable correcte;
endif
:No revocar certificat ni accés com a efecte lateral;
stop
@enduml
```

## P06 · Intranet — donar de baixa una inscripció

### ACTUAL
```plantuml
@startuml
title UC124 P06 ACTUAL - Baixa llegada
start
:Obrir modal Donar baixa per idInsc;
:Wrapper delega modalDonarBaixa_resultatCerca;
:Operador indica motiu i opcio de correu;
:GET confirmacioBaixa_DonarBaixa;
:Wrapper inclou connexions Moodle i delega confirmaBaixa_modalDonarBaixa;
note right
 El cos del metode gran Intranet.php
 no s'ha pogut re-verificar en aquest lot.
 No atribuir DELETE Moodle o REFUND.
end note
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC124 P06 FINAL - Baixa amb decisions separades
start
:Carregar enrollment i estat academic/economic actuals;
:Registrar peticio de baixa amb motiu/actor;
:Decidir estat academic i accessos;
:Derivar impacte economic/fiscal a UC corresponent;
:Aplicar canvis academics idempotents;
:Registrar resultats per desti;
:No crear REFUND ni rectificativa sense decisio economica explicita;
stop
@enduml
```

## P07 · Intranet — modal de certificat

### ACTUAL
```plantuml
@startuml
title UC124 P07 ACTUAL - JS de certificat amb endpoints no localitzats
start
:Icona/accio obre mostrarModalConsultaCertificat(idInsc,tipus);
:GET mostraModalConsultaCertificat.php;
if (Endpoint respon?) then (Si)
 :Mostrar variants de certificat;
 :GET mostrarCertificat.php per preview;
 if (tePermisEdicio i descarregar?) then (Si)
  :GET mostrarCertificat.php download=true;
  :Crear enllac de descarrega;
  :Demanar eliminar fitxer temporal;
 endif
else (No)
 :Mostrar error;
endif
note right
 Els dos endpoints referenciats
 no apareixen al tree actual de main.
 El JS si que hi es.
end note
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC124 P07 FINAL - Dret i document de certificat
start
:Verificar subjecte/idInsc i permís backend;
:Consultar superacio, estat academic i regla de certificat;
:Consultar estat economic només com a input de regla aprovada;
if (Elegible?) then (Si)
 :Generar/servir certificat privat i immutable;
 :Registrar document i accés/lliurament;
else (No)
 :Mostrar motiu pendent/rebutjat sense exposar dades alienes;
endif
:No alterar factura o pagament per generar certificat;
stop
@enduml
```

## P08 · SIF — reconciliació i event acadèmic/econòmic

### ACTUAL
```plantuml
@startuml
title UC124 P08 ACTUAL - Sync fiscal parcial
start
:LegacySyncService rep relacions INSCRIPCIO despres d'exit SIF;
repeat
 :LegacySyncRepository actualitza FACTURA_RELACIONADA si null;
 :Concatena numero, estat cobrament i UUID a OBSERVACIONS;
repeat while (Queden relacions?) is (Si) not (No)
note right
 No actualitza Moodle, PERENNE,
 CERTIFICAT ni estat academic.
 academic_economic_state_event
 existeix només com a DDL.
end note
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC124 P08 FINAL - Reconciliacio canònica
start
:Carregar enrollment, operacio, participant i pagador;
:Construir snapshot economic des de ledger/allocations;
:Carregar estat Moodle, certificat, baixa i superacio;
:Aplicar RULE_VERSION;
:Persistir academic_economic_state_event REQUESTED/decision;
repeat
 :Aplicar efecte pendent al desti corresponent;
 :Fer read-back i registrar resultat;
repeat while (Queden destins pendents?) is (Si) not (No)
if (Alguna divergencia?) then (Si)
 :Obrir incidencia correlacionada i retry selectiu;
else (No)
 :Marcar reconciliacio completada;
endif
:No modificar movement bancari per ajustar estat academic;
stop
@enduml
```

## Cobertura i estat

| Pas | ACTUAL acreditat | FINAL |
| --- | --- | --- |
| P01 | Diferencia ENTITAT vs pagament individual en curs actiu | Pagador canònic i regularització |
| P02 | Certificat/AO bloquejats per pendent llegat | Regla versionada per dimensions |
| P03 | URL xifrada per IDPAG | Autorització de pagador/operació |
| P04 | PERENNE X/0/1 + sol·licitud/correu | Gateway Moodle amb read-back |
| P05 | Reclamacions entitat/sense cert/amb cert | Cobrament separat de dret acadèmic |
| P06 | Wrapper de baixa a Intranet gran | Baixa acadèmica + decisió econòmica separada |
| P07 | JS de certificat; endpoints absents del tree main | Certificat privat, elegibilitat i auditoria |
| P08 | Sync només factura/observacions + DDL event | Coordinador/event/gateways idempotents |

**DOC:** 16 diagrames ACTUAL/FINAL. **IMP:** lògica llegada i sync fiscal parcial existents; reconciliador SIF no acreditat. **TEST:** UC124-T01–T18 no executats. **PRODUCCIÓ:** no verificada.
