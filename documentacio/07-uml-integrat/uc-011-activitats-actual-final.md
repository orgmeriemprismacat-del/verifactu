# UC-011 · Diagrames d'activitat ACTUAL / FINAL

## 1. Preview CLI ACTUAL

~~~plantuml
@startuml
start
:Comprovar CLI/no-production;
:Llegir JSON;
:Builder.build();
if (vàlid?) then (sí)
  :Mostrar payload normalitzat;
else (no)
  :Error;
endif
stop
@enduml
~~~

El preview no grava BD i, per tant, no executa el preflight transaccional contra numeració.

## 2. Process CLI · ACTUAL abans de l'auditoria

~~~plantuml
@startuml
start
:Llegir payload;
:Builder;
:BEGIN;
:Buscar IDEMPOTENCY_KEY;
if (existeix?) then (sí)
  :REUSE sense comparar contingut;
else (no)
  :INSERT factura/línies/rels/document;
endif
:COMMIT;
stop
@enduml
~~~

## 3. Process CLI · FINAL implementat

~~~plantuml
@startuml
start
:Llegir payload;
:Validar número/data/billing/imports/fiscalitat;
:BEGIN;
:Preflight número ocupat + fiscal_sequence;
if (conflicte?) then (sí)
  :409;
  :ROLLBACK;
  stop
endif
:Buscar IDEMPOTENCY_KEY;
if (existeix?) then (sí)
  :Comparar hash material;
  if (igual?) then (sí)
    :REUSE;
  else (no)
    :409 + ROLLBACK;
    stop
  endif
else (no)
  :INSERT factura HISTORICAL/NO_VERIFACTU;
  :INSERT línies/relacions;
  if (document metadata?) then (sí)
    :INSERT factura_documents;
  endif
endif
:INSERT operational_event;
:INSERT sif_audit_event;
:COMMIT;
:Retornar correlation_id;
stop
@enduml
~~~

## 4. Pàgina/JS llegada · ACTUAL upstream

~~~plantuml
@startuml
start
:Consultar SIF;
if (hi ha resultats?) then (sí)
  :Mostrar factura SIF;
else (no)
  :Fallback llegat;
endif
if (mutació/regeneració llegada?) then (sí)
  :SifLegacyInvoiceMutationGuard;
  if (governada pel SIF + flags?) then (sí)
    :409 bloquejat;
  else (no)
    :Permetre llegat;
  endif
endif
stop
@enduml
~~~

## 5. FINAL productiu pendent

~~~plantuml
@startuml
start
:Inventariar lot per emissor/origen/ID;
:Autoritzar operador i lot;
:Preflight global;
:Congelar/protegir llegat;
:Importar unitats;
:Custodiar originals físics;
:Reconciliar recompte/imports/documents;
:Conservar evidències;
stop
@enduml
~~~
