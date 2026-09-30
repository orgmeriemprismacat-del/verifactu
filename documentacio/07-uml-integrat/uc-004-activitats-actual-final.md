# UC-004 · Diagrames d'activitat ACTUAL / FINAL per pàgina i apartat

**Cas d'ús:** UC-004 — Emetre factura abans de cobrar  
**Pantalla actual:** `/alumnes/genera-factura-abans-pagar/`  
**Data d'auditoria estàtica:** 2026-09-29  
**Cobertura RM-037:** pàgina completa + sis apartats funcionals; cada apartat té ACTUAL i FINAL.

## 1. Matriu de pàgina i apartats

| ID | Apartat / acció | Fonts principals | ACTUAL / FINAL |
| --- | --- | --- | --- |
| A004-P00 | Pàgina completa UC-004 | pàgina PHP, `mostrarMain.php`, JS UC-004, `Intranet.php` | 2 diagrames |
| A004-P01 | Accés, càrrega i permisos | `mostrarMain.php`, `general.js`, `Intranet::mostrarPage` | 2 diagrames |
| A004-P02 | Cerca, afegir i treure inscripcions | `mostrarInformacioInscripcio_generaFactura.php`, JS, `Intranet` | 2 diagrames |
| A004-P03 | Validar selecció i preparar imports/conceptes | JS UC-004, `calcularTextData.php` | 2 diagrames |
| A004-P04 | Receptor/entitat i dades de factura | vista `__mostrarPage_Alumnes_GeneraFacturaAbansPagar`, JS, BD intranet | 2 diagrames |
| A004-P05 | Emetre factura abans de cobrar | endpoint llegat + `Intranet::generarFacturaElectronica_Alumnes`; FINAL SIF | 2 diagrames |
| A004-P06 | Resultat, previsualització i document | endpoints de dades/preview/download/delete, `generaFactura` | 2 diagrames |

Total: **14 diagrames d'activitat**.

---

## 2. A004-P00 · PÀGINA COMPLETA

### A004-P00 — ACTUAL

```plantuml
@startuml
title UC-004 · A004-P00 · PÀGINA COMPLETA — ACTUAL
start
:Obrir /alumnes/genera-factura-abans-pagar/;
if (Sessió/configuració de pàgina vàlida?) then (Sí)
  :JS demana ajax/mostrarMain.php;
  :Servidor consulta ROLS_VISUALITZAR de l'apartat;
  if (Permís de visualització?) then (Sí)
    :Intranet::mostrarPage();
    :Carregar entitats com a textos de raó social;
    :Mostrar PAS 1, PAS 2 ocult i PAS 3 ocult;
    :general.js consulta rols d'edició i calcula tePermisEdicio al client;

    repeat
      :Introduir NIF/NIE i cercar;
      :AJAX mostrarInformacioInscripcio_generaFactura.php;
      :Mostrar files llegades;
      :Afegir o treure inscripcions al DOM;
    repeat while (Cal cercar/ajustar selecció?) is (Sí)

    if (Continua i tePermisEdicio?) then (Sí)
      :Recórrer files seleccionades;
      :Afegir IDs a idsInsc;
      :Sumar #apagar-ID del DOM;
      :Construir cursos, edicions i concepte1;
      :Demanar nom de mes per AJAX i construir concepte2;
      :Mostrar PAS 2;
      :Operador selecciona entitat per text;
      :Pot editar concepte1 i observacions;
      if (Entitat/concepte/preu aparentment informats?) then (Sí)
        :POST empresa, conceptes, preu, cursos, edicions i IDs;
        :Intranet cerca entitat per text;
        :Calcula ORDRE = últim + 1;
        :Calcula ID factura = últim + 1;
        :INSERT a factures llegades;
        :UPDATE de cada inscripció amb factura relacionada;
        :Retornar HTML d'èxit;
        :Consultar dades i inscripcions de la factura;
        :Mostrar PAS 3;
        if (Previsualitza?) then (Sí)
          :Reconstruir HTML factura desde BD llegada;
        endif
        if (Descarrega?) then (Sí)
          :Generar PDF temporal amb Dompdf;
          :Retornar filename al navegador;
          :Descarregar;
          :GET eliminarArxiu.php?filename=...;
          :unlink(filename);
        endif
      else (No)
        :Mostrar error client;
      endif
    else (No)
      :No continuar / mostrar sense permisos;
    endif
  else (No)
    :Mostrar "No tens permisos per visualitzar aquesta pàgina";
  endif
else (No)
  :Redirigir a inici intranet;
endif
stop
@enduml
```

### A004-P00 — FINAL

```plantuml
@startuml
title UC-004 · A004-P00 · PÀGINA COMPLETA — FINAL
start
:Obrir pantalla UC-004;
:Autenticar sessió i actor al servidor;
:Autoritzar lectura i acció d'emissió per rol/abast;
if (Autoritzat?) then (Sí)
  :Carregar pantalla sense dades fiscals manipulables com a autoritat;
  repeat
    :Cercar inscripcions;
    :Servidor retorna IDs interns + dades necessàries;
    :Seleccionar o retirar inscripcions;
  repeat while (Cal ajustar selecció?) is (Sí)

  :Enviar IDs seleccionats per obtenir PREVISUALITZACIÓ;
  :Servidor deduplica IDs i rellegeix estat actual;
  :Validar curs/edició/regles de cobertura;
  :Resoldre receptor per ID intern i congelar snapshot;
  :Recalcular línies, descomptes, impostos i total al servidor;
  :Mostrar preview amb fingerprint/versió;

  if (Operador confirma?) then (Sí)
    :Enviar command + request/correlation/idempotency key;
    :Revalidar autorització, versió, selecció i cobertura;
    if (Conflicte o dades canviades?) then (Sí)
      :Retornar CONFLICT / NEEDS_REVIEW sense mutació fiscal;
    else (No)
      :InvoiceBeforePaymentService::issueBeforePayment();
      :InvoiceService crea o reutilitza factura;
      :COMMIT factura, línies, registre, cadena, cua i relacions;
      :Només després del COMMIT sincronitzar llegat si cal;
      :Generar/assegurar document per UUID de forma idempotent;
      :Mostrar UUID, número, cobrament PENDING, AEAT/document READY o PENDING;
    endif
  else (No)
    :No emetre factura;
  endif
else (No)
  :Denegar lectura/acció al servidor;
endif
stop
@enduml
```

---

## 3. A004-P01 · ACCÉS, CÀRREGA I PERMISOS

### A004-P01 — ACTUAL

```plantuml
@startuml
title UC-004 · A004-P01 · Accés i permisos — ACTUAL
start
:Carregar pàgina PHP;
if ($configOk?) then (Sí)
  :Carregar general.js i JS UC-004;
  :GET mostrarMain.php amb pathname;
  :Llegir apartat i ROLS_VISUALITZAR;
  if (tePermisVisualitzacio?) then (Sí)
    :Intranet::mostrarPage();
    :Construir HTML de UC-004;
    :general.js consulta ROLS_EDITAR i rols d'usuari;
    :Comparar arrays al navegador;
    :Assignar tePermisEdicio=true/false;
  else (No)
    :Mostrar denegació de visualització;
  endif
else (No)
  :Redirigir a intranet;
endif
stop
@enduml
```

### A004-P01 — FINAL

```plantuml
@startuml
title UC-004 · A004-P01 · Accés i permisos — FINAL
start
:Autenticar sessió;
:Resoldre actor estable;
:Autoritzar visualització UC-004 al servidor;
if (Pot visualitzar?) then (Sí)
  :Carregar pantalla;
  :Autoritzar capacitat ISSUE_INVOICE_BEFORE_PAYMENT;
  :Enviar capacitats de UI com a informació;
  note right
    El backend torna a validar el permís
    en cada command d'escriptura.
  end note
else (No)
  :HTTP/JSON denegat sense dades del cas;
endif
stop
@enduml
```

---

## 4. A004-P02 · CERCA I SELECCIÓ D'INSCRIPCIONS

### A004-P02 — ACTUAL

```plantuml
@startuml
title UC-004 · A004-P02 · Cerca i selecció — ACTUAL
start
:Operador escriu NIF/NIE;
if (Camp aparentment vàlid al client?) then (Sí)
  :GET mostrarInformacioInscripcio_generaFactura.php?dni=...;
  :Intranet consulta inscripcions per DNI;
  :Retornar taula HTML;
  if (Operador prem +?) then (Sí)
    :Copiar dades de la fila al bloc seleccionat;
    :Desactivar botó + d'aquella fila visible;
  endif
  if (Operador elimina selecció?) then (Sí)
    :Eliminar fila del bloc;
    :Reactivar + si la fila continua present;
  endif
else (No)
  :Mostrar error client;
endif
stop
@enduml
```

### A004-P02 — FINAL

```plantuml
@startuml
title UC-004 · A004-P02 · Cerca i selecció — FINAL
start
:Enviar criteri de cerca normalitzat;
:Autoritzar consulta al servidor;
:Consultar inscripcions elegibles;
:Retornar ID_INSC intern + dades mínimes de selecció;
:Operador selecciona IDs;
:Servidor no assumeix com a autoritatives dades del DOM;
if (Es demana avançar?) then (Sí)
  :Deduplicar IDs;
  :Rellegir cadascuna per ID intern;
  :Comprovar existència, estat i abast de l'actor;
  if (Alguna fila no és admissible?) then (Sí)
    :Retornar errors per fila;
  else (No)
    :Construir SelectionSnapshot versionat;
  endif
endif
stop
@enduml
```

---

## 5. A004-P03 · VALIDAR SELECCIÓ, IMPORTS I CONCEPTES

### A004-P03 — ACTUAL

```plantuml
@startuml
title UC-004 · A004-P03 · Selecció, imports i conceptes — ACTUAL
start
:Prem Continua;
if (tePermisEdicio al navegador?) then (Sí)
  :Reiniciar cursos, edicions i preuTotal;
  note right
    idsInsc és global i no s'ha observat
    un reset equivalent en aquest pas.
  end note
  :Recórrer cel·les del DOM;
  :idsInsc.push(ID);
  :preuTotal += parseFloat(#apagar-ID);
  :Construir arrays de curs i edició;
  if (Selecció compleix comprovacions JS de curs/edició?) then (Sí)
    :Construir concepte1;
    :Per cada edició fer AJAX calcularTextData.php;
    :Afegir text a concepte2 quan respon cada AJAX;
    :Mostrar preu i PAS 2;
  else (No)
    :Mostrar error;
  endif
else (No)
  :Mostrar sense permisos;
endif
stop
@enduml
```

### A004-P03 — FINAL

```plantuml
@startuml
title UC-004 · A004-P03 · Selecció, imports i conceptes — FINAL
start
:Rebre únicament IDs seleccionats + versió de selecció;
:Deduplicar IDs al servidor;
:Rellegir inscripcions, edició, curs, oferta i descomptes;
if (No compleixen regles de combinació?) then (Sí)
  :Rebutjar amb detall per ID;
else (No)
  :Congelar pricing snapshot;
  :Construir línies fiscals des de dades servidor;
  :Calcular decimals, base, descompte, IVA i total;
  :Construir conceptes deterministes;
  :Retornar preview complet i fingerprint;
endif
stop
@enduml
```

---

## 6. A004-P04 · RECEPTOR I DADES DE FACTURA

### A004-P04 — ACTUAL

```plantuml
@startuml
title UC-004 · A004-P04 · Receptor i dades — ACTUAL
start
:Vista carrega entitats actives;
:Converteix entitats en llista de RAO textual;
:Operador selecciona una RAO;
:JS desa entitatMarcada = text visible;
:Operador pot editar concepte1 i observacions;
:Preu es mostra amb valor calculat al client;
if (Prem Genera factura?) then (Sí)
  if (entitatMarcada/concepte/preu no buits?) then (Sí)
    :Preparar POST amb text d'entitat i valors client;
  else (No)
    :Mostrar error client;
  endif
endif
stop
@enduml
```

### A004-P04 — FINAL

```plantuml
@startuml
title UC-004 · A004-P04 · Receptor i dades — FINAL
start
:Mostrar receptors per identificador intern opac;
:Operador selecciona entityId;
:Servidor resol entityId autoritzat;
:Congelar nom/raó, NIF/CIF, adreça, CP, població, país i email fiscal;
if (Receptor incomplet o ambigu?) then (Sí)
  :Bloquejar emissió i demanar correcció de dades mestres;
else (No)
  :Combinar BillingSnapshot + SelectionSnapshot + PricingSnapshot;
  :No acceptar total fiscal calculat pel navegador;
  :Mostrar preview final abans de confirmar;
endif
stop
@enduml
```

---

## 7. A004-P05 · EMISSIÓ DE FACTURA ABANS DE COBRAR

### A004-P05 — ACTUAL

```plantuml
@startuml
title UC-004 · A004-P05 · Emissió — ACTUAL
start
:POST generaFacturaElectronica_Factures.php;
:Llegir empresa, conceptes, preu, cursos, edicions, inscripcions i observacions;
:Deserialitzar Intranet de sessió;
:Buscar entitat amb RAO LIKE text rebut;
:Extreure CIF/RAO/adreça/CP/població;
:Consultar darrer ORDRE fiscal A de l'any;
:ordreFact = lastOrdreFact + 1;
:Consultar darrer ID de factura;
:factura = lastFactRel + 1;
:INSERT factura llegada amb E_FACT=1;
if (INSERT correcte?) then (Sí)
  :Per cada ID d'inscripció rebut;
  :UPDATE FACTURA_RELACIONADA, reclamat, pag_observacions i CIF;
  :Retornar HTML d'èxit;
else (No)
  :Llançar error;
endif
stop
@enduml
```

### A004-P05 — FINAL

```plantuml
@startuml
title UC-004 · A004-P05 · Emissió — FINAL
start
:Rebre command de confirmació;
:Autoritzar actor al servidor;
:Comprovar request_id, correlation_id, versió i idempotency_key;
:Rellegir inscripcions per ID + receptor per entityId;
:Reconstruir línies/total al servidor;
:Calcular fingerprint actual;
if (Fingerprint diferent del preview?) then (Sí)
  :Retornar CONFLICT i exigir nou preview;
  stop
endif
:Classificar cobertura transversal existent per SOURCE_ID/receptor;
if (Ja existeix cobertura incompatible?) then (Sí)
  :Retornar CONFLICT/NEEDS_REVIEW;
else (No)
  :Preparar input sense bloc payment;
  :InvoiceBeforePaymentPayloadBuilder::build();
  :Forçar INTRANET i EMESA_ABANS_COBRAMENT=1;
  :InvoiceService::issueInvoice();
  :Validar payload;
  :BEGIN;
  if (IDEMPOTENCY_KEY ja existeix?) then (Sí)
    :assertMatches amb IDEMPOTENCY_PAYLOAD_HASH;
    if (Payload equivalent?) then (Sí)
      :Reutilitzar UUID i número;
    else (No)
      :ROLLBACK / CONFLICT;
      stop
    endif
  else (No)
    :Reservar seqüència fiscal;
    :Lock fiscal_chain_state;
    :Crear factura + línies + registre + fact_rels + fiscal_queue;
    :Claim INSCRIPCIO/ORIGIN a invoice_before_payment_coverage;
    if (Una altra operació UC-004 ja ha reclamat l'origen?) then (Sí)
      :UNIQUE uq_invoice_before_payment_source;
      :ROLLBACK de tot el graf fiscal;
      :Retornar CONFLICT;
      stop
    endif
  endif
  :COMMIT;
  :Retornar CREATED o REUSED amb UUID/número;
endif
stop
@enduml
```

---

## 8. A004-P06 · RESULTAT, PREVISUALITZACIÓ I DOCUMENT

### A004-P06 — ACTUAL

```plantuml
@startuml
title UC-004 · A004-P06 · Resultat i document — ACTUAL
start
:Després de l'HTML d'èxit, obtenir ID factura del DOM;
:POST mostraDadesFacturaElectronica_Factures.php;
:POST mostraInscripcionsFacturaElectronica_Factures.php;
:Mostrar PAS 3 amb dades i participants;
if (Operador prem Previsualitza?) then (Sí)
  :GET mostraPrevFactura_Factures.php;
  :Intranet::generaFactura(factura,false);
  :Llegir factura llegada;
  :Renderitzar HTML amb text d'exempció fiscal fix;
endif
if (Operador prem Descarrega?) then (Sí)
  :GET descarregaFactura.php?id=...;
  :Intranet::generaFactura(factura,true);
  :Marcar GENERAT si buit;
  :Dompdf renderitza i file_put_contents(filename);
  :Navegador descarrega filename;
  :GET eliminarArxiu.php?filename=...;
  :unlink(filename rebut);
endif
stop
@enduml
```

### A004-P06 — FINAL

```plantuml
@startuml
title UC-004 · A004-P06 · Resultat i document — FINAL
start
:Rebre resultat confirmat del COMMIT SIF;
:Consultar factura per UUID i autoritzar lectura;
:Mostrar número, receptor snapshot, línies, total;
:Mostrar ESTAT_COBRAMENT=PENDING;
:Mostrar ESTAT_AEAT independent;
:Consultar estat documental per UUID;
if (Document READY?) then (Sí)
  :Servir document custodiat/versionat amb autorització;
else (No)
  :Mostrar PENDING/ERROR i possibilitat de reintent idempotent;
  :No reemetre factura per regenerar document;
endif
:QR, fiscalitat i text legal provenen del snapshot fiscal;
:No acceptar nom de fitxer arbitrari del client per eliminar;
stop
@enduml
```

## 9. Criteris transversals derivats dels 14 diagrames

1. **Autoritat servidor:** IDs, receptor, preus, descomptes, impostos i cobertura es rellegeixen abans del commit.
2. **Idempotència i cobertura:** mateixa clau + mateix payload = reús; dues claus UC-004 sobre el mateix origen = conflicte transaccional. La cobertura entre canals/pagadors diferents es classifica separadament i no es resol amb una UNIQUE global.
3. **Atomicitat fiscal:** número, factura, línies, registre, hash, cadena, cua i relacions dins del domini transaccional SIF.
4. **Llegat posterior:** qualsevol sincronització amb `factures`/inscripcions antigues és posterior al COMMIT SIF i recuperable.
5. **Cobrament separat:** UC-004 deixa `ESTAT_COBRAMENT=PENDING`; el moviment econòmic posterior no reemet la factura.
6. **Document separat de la factura:** error de PDF/QR no anul·la ni torna a numerar una factura ja confirmada.
7. **Proves de pantalla obligatòries:** tornar enrere, doble clic, duplicats d'ID, canvis concurrents, canvi de receptor/import, selecció ja coberta i error després del commit.

## 10. Estat

- **ACTUAL:** reconstruït estàticament des del codi versionat.
- **FINAL:** selecció per IDs, receptor per entityId, línies/total des de servidor, fingerprint preview→confirm i claim concurrent UC-004 ja estan implementats a la branca en serveis/CLI. Continuen pendents el classificador transversal, autorització/CSRF i l'adaptador HTTP de la pantalla.
- **Execució de proves:** no feta en aquesta auditoria documental.
- **Integració pantalla UC-004 → SIF:** pendent; **integració CLI no productiva BDs llegades → preparació → SIF:** implementada a la branca, no executada aquí.
