# UC-120 — activitats ACTUAL/FINAL per superfície de canvi de dades personals

**Revisió:** 25/09/2026. **ACTUAL** = PHP/JS versionats observats; **FINAL** = contracte SIF proposat. [Fitxa funcional](../06-fitxes-funcionals/uc-120.md) · [auditoria lot 09](00-auditoria-casos-pendents-lot-09-uc-120-2026-09-25.md).

## P01 · Portal alumne — consultar i activar edició

### ACTUAL
```plantuml
@startuml
title UC120 P01 ACTUAL - Les meves dades
start
:Obrir dades-personals.php amb sessio valida;
:AJAX mostrarDades.php;
:IntranetAlumne mostra dades personals/curriculars;
if (Clic Edita?) then (Si)
 :AJAX mostrarDadesEditables.php;
 :Substituir vista per camps editables;
else (No)
 :Mantenir vista de consulta;
endif
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC120 P01 FINAL - Perfil i proposta
start
:Carregar perfil vigent autoritzat i origen de cada dada;
if (Persona vol canviar camps?) then (Si)
 :Crear esborrany local de canvis;
 :No mutar cap sistema encara;
 :Mostrar camps que requereixen justificacio o revisio;
endif
stop
@enduml
```

## P02 · Portal alumne — enviar petició

### ACTUAL
```plantuml
@startuml
title UC120 P02 ACTUAL - Sol.licitud per missatge
start
:Persona edita nom, cognoms, DNI, email, telefon, adreca, CP, poble, perfil/titulacio;
:JS valida camps obligatoris;
if (Hi ha errors?) then (Si)
 :Mostrar errors al formulari;
 stop
endif
:GET enviarMsgPeticioActualtizacio.php amb dades personals i comentari;
:Wrapper invoca enviarMsgSolicitantModificacioDades;
:Backend rellegeix inscripcio vinculada a usuari;
:Comparar camps actuals i nous;
if (Cap canvi?) then (Si)
 :Retornar No canvi;
else (No)
 :Construir missatge Dades actuals / Dades a modificar;
 :Preparar correu a Secretaria i confirmacio a alumne;
endif
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC120 P02 FINAL - Registrar peticio
start
:POST autenticat amb camps proposats i justificacio minima;
:Backend resol SUBJECT_KEY i perfil vigent;
:Construir changeset abans/despres server-side;
if (No hi ha canvi semantic?) then (Si)
 :Retornar NO_CHANGE idempotent;
else (No)
 :Crear/reutilitzar personal_data_change_request;
 :Registrar REQUESTED, actor, correlacio i operacions obertes;
 :Encolar notificacio de recepcio despres del commit;
endif
stop
@enduml
```

## P03 · Revisió de Secretaria/Gestió

### ACTUAL
No s'ha localitzat una pantalla específica de workflow «peticions de dades personals» amb estat REQUESTED/APPROVED/REJECTED. El correu és el mecanisme observat de traspàs a Secretaria.

```plantuml
@startuml
title UC120 P03 ACTUAL - Revisio manual no estructurada
start
:Secretaria rep comunicacio amb abans/despres;
:Revisio fora del workflow SIF acreditat;
if (Decideix aplicar canvi?) then (Si)
 :Operador usa pantalles internes disponibles;
else (No)
 :No s'ha acreditat registre estructurat de rebuig en aquest flux;
endif
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC120 P03 FINAL - Revisio tipificada
start
:Revisor autoritzat obre peticio i dades minimes;
:Comprovar subjecte, representacio, evidencia i camp;
:Determinar destinacions i operacions obertes afectades;
if (Aprovar?) then (Si)
 :Registrar APPROVED amb actor i versio base;
 :Crear pla de propagacio;
else (No)
 :Registrar REJECTED i causa;
 :Notificar resultat sense dades sobreres;
endif
stop
@enduml
```

## P04 · Intranet interna — editar «Dades personals»

### ACTUAL
```plantuml
@startuml
title UC120 P04 ACTUAL - Edicio directa inscripcio
start
:Operador obre Consulta / Modifica alumne;
if (JS tePermisEdicio?) then (Si)
 :Convertir camps visibles a inputs;
 :Editar dades personals;
 :Validar camps i telefon al navegador;
 if (Desar?) then (Si)
  :GET guardarDadesPersonals.php amb idInsc i dades;
  :Wrapper crida guardarDadesPersonals_resultatCerca;
  :UPDATE llegat de la inscripcio per ID segons traça UC042;
  :UI repinta valors si resposta no conte Error/error;
 else (Cancel.lar)
  :UI repinta valors de l'input sense escriptura;
 endif
else (No)
 :Mostrar modal de falta de permisos al client;
endif
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC120 P04 FINAL - Edicio administrativa autoritzada
start
:Operador obre subjecte/inscripcio amb permís backend per camp;
:Rellegir versio i valor persistent;
:Editar camps autoritzats;
if (Desar?) then (Si)
 :Crear ordre administrativa amb abans/despres i motiu;
 :Validar concurrencia i identitat;
 if (Camp requereix revisio?) then (Si)
  :Crear peticio PENDING;
 else (No)
  :Aplicar canvi vigent i registrar event;
 endif
 :Programar propagacio només a destinacions admeses;
else (Cancel.lar)
 :Descartar buffer i rellegir valor persistent;
endif
stop
@enduml
```

## P05 · Intranet interna — modal «Dades de la inscripció»

### ACTUAL
```plantuml
@startuml
title UC120 P05 ACTUAL - Modal ampli
start
:Obrir modal d'una inscripcio concreta;
:Editar personals + data/estat/aula/mailing/certificat/generat/baixa/observacions;
:JS valida diversos camps;
:GET guardarDadesPersonals_ConsultaInformacio.php;
:Wrapper crida guardarDadesPersonals_modalsresultatCerca;
:UPDATE directe d'inscripcions per ID segons traça UC042;
:Mostrar missatge textual d'exit/error;
note right
 Una sola ruta barreja dominis
 personals, academics, comercials
 i administratius.
end note
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC120 P05 FINAL - Ordres per domini
start
:Classificar cada camp modificat;
if (Contacte/identitat?) then (Si)
 :UC120 amb permisos i propagacio;
endif
if (Estat/baixa?) then (Si)
 :Derivar a UC academic/economic corresponent;
endif
if (Mailing?) then (Si)
 :Gestionar consentiment/comunicacio amb traça propia;
endif
if (Certificat?) then (Si)
 :Gestionar dret/document academic separat;
endif
:No combinar efectes heterogenis en un UPDATE opac;
stop
@enduml
```

## P06 · Propagació multi-sistema i documents històrics

### ACTUAL
No s'ha acreditat un writer/worker PHP que utilitzi `personal_data_change_request.PROPAGATION_STATUS` i `PROPAGATION_RESULT_JSON`.

```plantuml
@startuml
title UC120 P06 ACTUAL - Sense coordinador acreditat
start
:Existeixen canvis puntuals al llegat i peticions per correu;
:DDL SIF defineix peticio i estat de propagacio;
:No s'ha identificat servei executable de propagacio general;
stop
@enduml
```

### FINAL
```plantuml
@startuml
title UC120 P06 FINAL - Propagacio versionada
start
:Carregar peticio APPROVED i pla de destinacions;
repeat
 :Seleccionar destinacio pendent;
 :Comprovar versio/estat i aplicar només dades vigents;
 :Registrar resultat per sistema;
repeat while (Queden destinacions?) is (Si) not (No)
if (Alguna destinacio ha fallat?) then (Si)
 :PROPAGATION_STATUS=PARTIAL/ERROR;
 :Reintentar només les pendents;
else (No)
 :PROPAGATION_STATUS=COMPLETED;
endif
if (Hi ha operacions comercials obertes afectades?) then (Si)
 :Revalidar snapshot/receptor abans de nova intencio;
endif
if (Hi ha factura ja emesa?) then (Si)
 :No modificar-la;
 :Si hi ha error fiscal real, obrir UC corrector;
endif
stop
@enduml
```

## Traçabilitat resumida

| Pas | ACTUAL acreditat | FINAL |
| --- | --- | --- |
| P01 | Consulta/edició visual en portal alumne | Esborrany sense mutació |
| P02 | GET + comparació + correus, sense fila SIF acreditada | Petició estructurada/idempotent |
| P03 | Revisió per correu sense workflow observat | Aprovar/rebutjar per camp/rol |
| P04 | UPDATE directe d'una inscripció | Ordre administrativa + versió + traça |
| P05 | UPDATE ampli barreja dominis | UCs/ordres diferenciats |
| P06 | DDL existeix, propagador no acreditat | Jobs per destí + estat parcial + immutabilitat fiscal |

**DOC:** 12 diagrames ACTUAL/FINAL. **IMP:** coordinador/worker no acreditat. **TEST:** no executat. **PRODUCCIÓ:** no verificada.
