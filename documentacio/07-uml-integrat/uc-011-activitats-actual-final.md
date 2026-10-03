# UC-011 · Diagrames d'activitat ACTUAL / FINAL

**Objectiu:** separar activitats per punt d'entrada real. UC-011 no té una pàgina JS/intranet que executi la migració al tall 03/10/2026; les dues superfícies d'importació són scripts CLI. La pàgina llegada `alumnes-factura` es modela separadament com a **origen/upstream** perquè pot consultar, editar, anul·lar i regenerar la factura abans del cut-over.

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
:Exigir i validar issue_date original;
:Validar billing/totals/línies/relacions;
:Normalitzar emissor i camps fiscals;
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
  :Projectar dades materialment persistides;
  :Calcular hash canònic material;
  if (Hash coincideix?) then (sí)
    :Reutilitzar UUID existent;
    :COMMIT sense nova factura;
  else (no)
    :Llançar 409 conflict;
    :ROLLBACK;
  endif
else (no)
  :Projectar dades materialment persistides;
  :Calcular hash canònic material;
  :INSERT factura + hash + emissor/descripció/fiscalitat;
  :INSERT línies + fiscalitat de línia;
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

## 5. Pàgina/intranet/JS llegada · ACTUAL upstream

~~~plantuml
@startuml
title UC-011 | alumnes-factura | ORIGEN ACTUAL
start
:Usuari obre alumnes-factura.php;
:alumnes-factura.js construeix criteris;
:Intentar consulta SIF via sifFactures.php;
if (SIF retorna factura?) then (sí)
  :Mostrar resultat SIF;
else (no/fallback)
  :Consultar factura llegada;
endif

if (Usuari vol editar/anul·lar/descarregar?) then (sí)
  :Endpoint valida sessió/origen/permisos;
  :Executar SifLegacyInvoiceMutationGuard;
  if (Factura ja governada pel SIF i flags actius?) then (sí)
    :Bloquejar amb 409;
    stop
  else (no)
    if (Descàrrega?) then (sí)
      :Intranet->generaFactura(id,true);
      :Crear PDF temporal des de dades vives;
      note right
        Representació regenerada.
        No prova de bytes originals.
      end note
    else (no)
      :Modificar/anul·lar llegat;
    endif
  endif
endif
stop
@enduml
~~~

Aquest flux explica per què el cut-over forma part de la migració: si el guard no està actiu, la font pot continuar canviant després d'haver preparat el payload o fins i tot després de migrar.

## 6. Pàgina d'execució UC-011 · estat

~~~plantuml
@startuml
title UC-011 | UI d'importació | ESTAT AUDITAT
start
:Buscar pàgina/endpoint/JS que executi UC-011;
if (Localitzat?) then (no)
  :Cap superfície HTTP/JS d'importació acreditada;
  :Canal actual = CLI de no-producció;
endif
stop
@enduml
~~~

## 7. FINAL productiu requerit però pendent

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
:Congelar o protegir origen llegat;
:Provar SIF_BLOCK_LEGACY_INVOICE_MUTATIONS + UC-007;
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

## 8. Conclusió

La documentació anterior barrejava pantalla/gateway genèrics amb un cas que importa per CLI. L'auditoria afegeix la UI llegada només en el paper correcte: **font mutable/upstream**, no executor. Això evita atribuir a UC-011 una UI d'importació que no existeix i, alhora, documenta el risc real de modificar/regenerar la factura origen si el guard de cut-over no està actiu.
