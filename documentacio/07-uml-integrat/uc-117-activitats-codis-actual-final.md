# UC-117 — diagrames d'activitat per pàgina, apartat i estat ACTUAL/FINAL

**Revisió documental:** 25/09/2026 · **Codi base de contrast:** `main` de la revisió del repositori, `e71958b3026549bde09fb4b25f2ec3ba370937ec`. **ACTUAL:** codi observat a l'arbre, no desplegament ni prova d'execució. **FINAL:** contracte proposat de gestió de drets, no implementat. No atribuir un panell SIF actual si només hi ha DDL. [Fitxa funcional](../06-fitxes-funcionals/uc-117.md) · [Auditoria del lot 06](00-auditoria-casos-pendents-lot-06-uc-117-2026-09-25.md).

## P01 · Formulari d'inscripció curs normal — apartat de codi promocional

**Fonts:** [`InscripcioCurs.php` L558–607](../../codi-drive/web-actual/InscripcioCurs.php#L558-L607); [JS L1033–1207](../../codi-drive/web-actual/js1619773569/mostrarInscripcions.min.js#L1033-L1207) i [L1208–1261](../../codi-drive/web-actual/js1619773569/mostrarInscripcions.min.js#L1208-L1261); [`obtenirDadesPromo.php`](../../codi-drive/web-actual/ajax/obtenirDadesPromo.php). **Frontera:** la pàgina de curs té dades personals, curriculars, edició i pagament; només es desglossa aquí el subflux que pertany a UC-117 i UC-020d, sense assumir que totes les altres accions són d'aquest UC.

### P01 — ACTUAL: camp, consulta i càlcul al navegador

```plantuml
@startuml
title P01 ACTUAL | Formulari curs normal | promocions personals
start
:Mostrar formulari, dades del curs, preu i camp promo si no subvencionat;
:Persona introdueix document personal i opcionalment codi promocional;
if (Camp promo informat?) then (Si)
 :JS fa GET obtenirDadesPromo(promo,codiCurs);
 :PHP consulta promocions per codi i curs/hores/TOTS;
 if (Fila recuperada?) then (Si)
  :Retorna vigencia, USED, DNI, mes, curs, percentatge, tipus i imports;
  :JS comprova dates, USED, titular introduit, edicio i curs/hores;
  if (Comprovacions JS favorables?) then (Si)
   :JS calcula percentatge, descompte fix o preu fix;
   :Mostra preu i prepara promocioAplicada;
  else (No)
   :Mostra error i recalcula sense aquest codi;
  endif
 else (No)
  :Mostra codi inexistent i recalcula sense codi;
 endif
else (No)
 :Calcular preu sense codi promocional;
endif
note right
 La consulta retorna DNI al client.
 Validacio de navegador no prova
 elegibilitat al backend.
end note
stop
@enduml
```

### P01 — FINAL: consulta minimitzada i validació al servidor

```plantuml
@startuml
title P01 FINAL | Formulari curs normal | dret comercial segur
start
:Mostrar curs, edicio, preu i camp de codi quan pertoqui;
:Persona proporciona codi i identitat per canal autenticat o verificat;
:Backend resol tipus de dret, regla, titular, estat, producte i vigencia;
if (Dret aplicable a persona i operacio?) then (Si)
 :Calcular import al servidor amb decimals i compatibilitats;
 :Retornar nomes resultat comercial, preu i motiu public minim;
 :Mostrar oferta calculada, no dades de titular ni code hash;
else (No)
 :Retornar error tipificat sense revelar dades del dret aliè;
 :Mostrar preu alternatiu aplicable;
endif
:No marcar dret consumit per consultar-lo;
stop
@enduml
```

## P02 · Confirmar inscripció i marcar codi

**Fonts:** [JS L2188–2258](../../codi-drive/web-actual/js1619773569/mostrarInscripcions.min.js#L2188-L2258); [`enviarInscripcio.php` L75–85, 575–610, 670–681](../../codi-drive/web-actual/ajax/enviarInscripcio.php#L670-L681). La confirmació visible de la inscripció és diferent de la confirmació del cobrament per TPV.

### P02 — ACTUAL: INSERT d'alta seguit d'UPDATE d'ús

```plantuml
@startuml
title P02 ACTUAL | Alta curs i promocions
start
:Persona confirma dades de la inscripcio;
:JS comprova duplicat i prepara preuDescompte i promocioAplicada;
:GET enviarInscripcio.php amb import i promocio del client;
if (Alta considerada valida pel servidor?) then (Si)
 :INSERT a inscripcions i A_PAGAR;
 if (promocioAplicada no buida?) then (Si)
  :UPDATE promocions SET USED=1 WHERE CODI_DESCOMPTE=codi;
  note right
   WHERE sense USED=0, DNI,
   curs, edicio ni reserva.
   Ocorre a l'alta, no
   en callback de pagament.
  end note
 endif
 :Retornar identificador de la inscripcio;
 :Navegador continua al flux de confirmacio;
else (No)
 :Retornar error de l'alta;
endif
stop
@enduml
```

### P02 — FINAL: alta idempotent, reserva i consum segons resultat

```plantuml
@startuml
title P02 FINAL | Alta curs | codi d'us limitat
start
:Validar identitat, rol, curs, edicio, preu i regla al backend;
if (Codi requereix consum limitat?) then (Si)
 :Obtenir lock del dret i verificar versio i estat;
 if (Dret encara disponible?) then (Si)
  :Crear reserva amb UUID_OPERATION, termini i event;
 else (No)
  :Denegar promocio sense crear consum duplicat;
  stop
 endif
else (No)
 :Aplicar regla de codi public/campanya segons quota si escau;
endif
:Crear o reutilitzar alta comercial i oferta idempotent;
if (Cal pagament per confirmar l'operacio?) then (Si)
 :Mantenir reserva durant checkout;
 if (Cobrament confirmat i operacio conciliada?) then (Si)
  :Consumir dret elegible UNA vegada amb event i operacio;
 else (No)
  :Alliberar o recuperar reserva segons regla aprovada;
  :No marcar consum irreversible per alta sense pagament;
 endif
else (No)
 :Aplicar fita de consum propia del producte amb event i idempotencia;
endif
:Retornar estat real de reserva, alta i consum per separat;
stop
@enduml
```

## P03 · Descompte temporal de `trobades`

**Fonts:** [`obtenirCodisPromo.php`](../../codi-drive/web-actual/ajax/obtenirCodisPromo.php); [JS L929–1027](../../codi-drive/web-actual/js1619773569/mostrarInscripcions.min.js#L929-L1027). El PHP agrega les entrades amb `$`; el JS usa `split(",")`; és una discrepància de format observada estàticament que requereix prova amb més d'una promoció.

### P03 — ACTUAL: codi de trobades diferenciat de la taula promocions

```plantuml
@startuml
title P03 ACTUAL | Trobades | codi temporal de campanya
start
:En obrir dades del curs, cridar GET obtenirCodisPromo;
:Servidor consulta trobades per curs, activacio i estat;
:Calcular final de vigencia amb DATA_INICI i dies de codi;
if (Entrades vigents?) then (Si)
 :Concatenar codi, percentatge i edicio amb separador de PHP;
 :Navegador intenta separar entrades amb coma;
 :Mostrar camp codi-promo;
 :Persona introdueix codi;
 if (JS reconeix codi i edicio?) then (Si)
  :Calcular percentatge de descompte al client;
  :Preparar promocioATrobadaplicada;
 else (No)
  :Mostrar error de codi o d'edicio;
 endif
else (No)
 :No oferir codi de trobades;
endif
note right
 PHP concatena amb dolar,
 JS espera coma.
 Multi-entrada no provada.
end note
stop
@enduml
```

### P03 — FINAL: campanya temporal validada al servidor

```plantuml
@startuml
title P03 FINAL | Trobades | campanya tipificada
start
:Servidor consulta regla publicada i vigencia de campanya;
:Enviar al navegador format tipificat sense credencials ni dades personals;
:Persona introdueix codi temporal;
:Backend valida curs, edicio, dates i quota o limit d'usos configurat;
if (Campanya valida per l'operacio?) then (Si)
 :Calcular preu al servidor i registrar regla aplicable;
 :Si hi ha quota limitada, reservar-la amb identificador propi;
else (No)
 :Retornar error comercial sense exposar dades;
endif
:No executar UPDATE de promocions personals per una campanya trobades;
stop
@enduml
```

## P04 · Cicle de vida del dret SIF (DISSENY, sense pàgina actual de gestió acreditada)

**Font d'esquema:** [migració 000005, `commercial_entitlement` i `commercial_entitlement_event`](../../sif/database/migrations/2026_09_16_000005_add_operation_lifecycle_tables.sql#L96-L155); [UML UC117](uc-117-cicle-vida-codi-dret-futur.md). No s'ha acreditat una pantalla ACTUAL amb botons «Emetre/Reservar/Consumir/Revertir» en el SIF. **No generar un diagrama ACTUAL fictici per a un panell que no s'ha trobat.** Si es dissenya una pantalla futura, cada apartat i permís requerirà revisió quan s'implementi.

### P04 — FINAL: emissió, consulta, reserva, consum, venciment i reversió

```plantuml
@startuml
title P04 FINAL | SIF | cicle de vida de dret comercial
start
:Actor autoritzat o operacio origen sol.licita crear dret;
:Backend valida tipus, titular, regla, origen i idempotencia;
:Crear/reutilitzar comercial_entitlement i event d'emissio;
:Publicar notificacio segura despres del commit;
repeat
 :Consultar estat del dret en operacio autoritzada;
 if (Dret expirat o cancel.lat?) then (Si)
  :Rebutjar nous usos i conservar cronologia;
 else (No)
  if (Sol.licitud de reserva?) then (Si)
   :Comprovar quota/estat/titular i bloquejar concurrencia;
   :Registrar reserva i venciment amb event;
  endif
  if (Operacio consumidora confirmada?) then (Si)
   :Validar titular, valor i idempotencia;
   :Registrar consum per operacio, una vegada;
  else (No)
   :Alliberar reserva caducada/fallida segons regla tipificada;
  endif
 endif
 if (Cancel.lacio o canvi posterior?) then (Si)
  :Classificar drets, saldo promocional i possible impacte fiscal;
  :Registrar event nou de reversio/cancel.lacio segons politica;
 endif
repeat while (Cal altra operacio autoritzada?) is (Si) not (No)
:No editar una factura emesa ni transformar codi comercial en diners cobrats;
stop
@enduml
```

## Matriu de traçabilitat i estat

| Pàgina/apartat | Traça de codi o DDL | ACTUAL | FINAL |
| --- | --- | --- | --- |
| P01 curs normal / codi personal | `InscripcioCurs.php` → JS → `obtenirDadesPromo.php` → `promocions` | Consulta/client/preu documentats; dades personals a resposta | Validació backend, resposta minimitzada i regla tipificada. |
| P02 curs normal / confirmar | JS → `enviarInscripcio.php` → `inscripcions` + `promocions.USED` | UPDATE després alta, no després cobrament | Reserva/consum per fita real, lock i idempotència. |
| P03 curs normal / trobades | JS → `obtenirCodisPromo.php` → `trobades` | Campanya de vigència, format delimitador inconsistent | Regla server-side, format tipificat i quotes pròpies. |
| P04 SIF / cicle del dret | SQL 000005, UML i fitxa funcional | Pantalla PHP actual no acreditada | Disseny de drets/events i permisos; implementació/producció no acreditades. |

**Limitació expressa:** no s'han inspeccionat tots els productes/canals ni el cos de mètodes grans d'`Intranet.php`; no aplicar els diagrames P01–P03 a packs, regals o qualsevol altre canal sense contrastar-ne el PHP/JS. Els fluxos d'UC-111, UC-113 i UC-114 no s'han reauditat.
