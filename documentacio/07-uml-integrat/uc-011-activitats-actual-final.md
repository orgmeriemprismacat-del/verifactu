# UC-011 · Diagrames d'activitat ACTUAL / FINAL

**Objectiu:** separar activitats per punt d'entrada real. UC-011 no té una pàgina JS/intranet pròpia al tall 03/10/2026; les dues superfícies executables són scripts CLI.

## 1. preview-historical-invoice-migration.php · ACTUAL

~~~plantuml
@startuml
title UC-011 | preview CLI | ACTUAL
start
:Comprovar PHP_SAPI=cli;
if (SIF_ENV=production?) then (sí)
  :Rebutjar;
  stop
endif
:Llegir --payload-file;
:Decodificar JSON;
:HistoricalInvoicePayloadBuilder.build();
if (Payload vàlid?) then (no)
  :Retornar JSON ok=false/dry_run=true;
  stop
endif
:Retornar JSON ok=true/dry_run=true;
note right
  No grava BD.
  No verifica que el lot sigui complet.
  No comprova bytes del document.
end note
stop
@enduml
~~~

## 2. preview CLI · FINAL de la branca

~~~plantuml
@startuml
title UC-011 | preview CLI | FINAL branca
start
:Llegir payload;
:Exigir NUM_VISIBLE;
:Derivar sèrie/any/seq;
if (Components explícits coincideixen?) then (no)
  :Error 422;
  stop
endif
:Exigir issue_date original;
:Forçar source_channel=MIGRACIO;
:Forçar invoice_status=HISTORICAL;
:Forçar aeat_status=NO_VERIFACTU;
if (No hi ha relations?) then (sí)
  :Crear HISTORIC_LINK;
  :VISIBLE_ALUMNE=0 per defecte;
endif
:Validar metadata de document si existeix;
:Mostrar payload normalitzat sense escriure;
stop
@enduml
~~~

## 3. process-historical-invoice-migration.php · ACTUAL a main abans de l'auditoria

~~~plantuml
@startuml
title UC-011 | process CLI | ACTUAL main
start
:Comprovar CLI i no-production;
:Llegir payload JSON;
:Builder.build();
:BEGIN;
:SELECT per IDEMPOTENCY_KEY FOR UPDATE;
if (Clau ja existeix?) then (sí)
  :Retornar factura anterior;
  note right
    No es comparava el contingut.
  end note
else (no)
  :INSERT factura HISTORICAL/NO_VERIFACTU;
  :INSERT línies;
  :INSERT relacions;
  if (document metadata?) then (sí)
    :INSERT factura_documents;
  endif
endif
:COMMIT;
:Retornar resultat;
stop
@enduml
~~~

## 4. process CLI · FINAL implementat a la branca

~~~plantuml
@startuml
title UC-011 | process CLI | FINAL branca
start
:Normalitzar i validar payload;
:BEGIN;
:SELECT per IDEMPOTENCY_KEY FOR UPDATE;
if (Clau existent?) then (sí)
  :Llegir IDEMPOTENCY_PAYLOAD_HASH;
  :Calcular hash canònic del payload actual;
  if (Hash coincideix?) then (sí)
    :Reutilitzar UUID existent;
    :COMMIT sense nova factura;
  else (no)
    :Llançar 409 conflict;
    :ROLLBACK;
  endif
else (no)
  :Calcular hash canònic;
  :INSERT factura + hash;
  :INSERT línies;
  :INSERT relacions amb visible=0 si absent;
  if (document metadata?) then (sí)
    :INSERT metadata;
  endif
  :COMMIT;
endif
:Retornar NO_VERIFACTU;
note right
  No crea factura_registres,
  fiscal_queue, payment_transaction
  ni fiscal_sequence.
end note
stop
@enduml
~~~

## 5. Pàgina/intranet/JS · estat

~~~plantuml
@startuml
title UC-011 | UI web/intranet | ESTAT AUDITAT
start
:Buscar pàgina/endpoint/JS específic UC-011;
if (Localitzat?) then (no)
  :Cap superfície web específica acreditada;
  :No dibuixar una UI fictícia;
endif
:Canal actual = CLI de no-producció;
stop
@enduml
~~~

## 6. FINAL productiu requerit però pendent

~~~plantuml
@startuml
title UC-011 | migració productiva governada | OBJECTIU
start
:Autenticar operador i rol;
:Assignar request_id + correlation_id + actor;
:Carregar inventari per emissor/origen/ID;
:Preflight numeració i col·lisions;
if (Conflictes?) then (sí)
  :Bloquejar fila/lot i obrir incidència;
  stop
endif
:Preview determinista;
:Autorització explícita del lot;
:Import transaccional;
:Registrar operational_event/audit;
if (Original físic disponible?) then (sí)
  :Llegir bytes;
  :Calcular SHA-256 físic;
  :Custodiar en storage privat;
else (no)
  :Marcar evidència documental pendent;
endif
:Reconciliar recompte i imports origen vs SIF;
stop
@enduml
~~~

## 7. Conclusió

La documentació anterior barrejava pantalla/gateway genèrics amb un cas que en realitat només té CLI. Aquesta separació evita atribuir al UC-011 autorització web, JavaScript, auditoria operativa o custòdia documental que encara no existeixen.
