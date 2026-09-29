# UC-007 · Auditoria detallada de consulta de factura, estat i document — 2026-09-29

**Estat documental:** AUDITAT EN DETALL / CANVIS DOCUMENTALS APLICATS.  
**Estat runtime:** CONSULTA INTERNA READ-ONLY + HMAC + PONT INTRANET + UI SIF IMPLEMENTATS PARCIALMENT; PROVES RUNTIME AJORNADES; UC-080 BYTES PENDENT.  
**Abast:** pantalla llegada <code>/alumnes/factura/</code>, entrades AL-16–AL-18 de la fitxa d'alumne, model SIF i frontera UC-07/36/55/78/80.  
**No acredita:** desplegament productiu, permisos reals de servidor web, dades productives, execució dels tests, integritat física de documents ni conformitat fiscal externa.

## 1. Fonts de codi i documentació revisades

- <code>codi-drive/intranet-actual/alumnes-factura.php</code>
- <code>codi-drive/intranet-actual/ajax/mostrarMain.php</code>
- <code>codi-drive/intranet-actual/js/general.js</code>
- <code>codi-drive/intranet-actual/js/alumnes-factura.js</code>
- <code>codi-drive/intranet-actual/js/alumnes-mostrar-alumne.js</code>
- <code>codi-drive/intranet-actual/Intranet.php</code>
- wrappers AJAX de consulta, previsualització, descàrrega, edició, anul·lació i neteja de temporals.
- <code>codi-drive/intranet-actual/Usuari.php</code> i <code>inc/comprovarSessio.php</code>.
- migració SIF core, <code>InvoiceRepository</code>, <code>FiscalRecordRepository</code>, <code>PaymentRepository</code> i <code>DocumentRepository</code>; després de l'auditoria s'han afegit <code>InvoiceReadRepository</code>, <code>InvoiceQueryService</code> i <code>InvoiceVisibilityPolicyInterface</code>.
- fitxes UC-007, UC-036, UC-055, UC-078, UC-080 i catàleg final de casos d'ús.
- tests existents de documents, històrics i endpoints; localitzats però no executats.

## 2. Inventari de subaccions

| Ref | Acció ACTUAL | Estat de l'auditoria |
| --- | --- | --- |
| F01 | Carregar pantalla, breadcrumb i control visual/rol | VERIFICAT ESTÀTICAMENT |
| F02 | Cercar per DNI, email, factura relacionada o número | VERIFICAT ESTÀTICAMENT |
| F03 | Mostrar selector de candidats | VERIFICAT ESTÀTICAMENT |
| F04 | Mostrar llistat de factures i accions | VERIFICAT ESTÀTICAMENT |
| F05 | Consultar informació i edició llegada adjacent | VERIFICAT ESTÀTICAMENT |
| F06 | Previsualitzar factura reconstruïda | VERIFICAT ESTÀTICAMENT |
| F07 | Descarregar PDF temporal | VERIFICAT ESTÀTICAMENT |
| AL-16 | Navegar des del camp factura de la fitxa alumne | VERIFICAT ESTÀTICAMENT |
| AL-17 | Obrir modal directe des de la fitxa alumne | VERIFICAT ESTÀTICAMENT |
| AL-18 | Descarregar i eliminar temporal des del modal | VERIFICAT ESTÀTICAMENT |
| Runtime/E2E | Permisos, navegador, BD, storage, concurrència | PENDENT |

## 3. Invariants FINAL

1. La identitat canònica és <code>UUID_FACTURA</code>; <code>FACTURA_RELACIONADA</code> només és traça llegada.
2. Original i rectificativa tenen UUID, número, hash i document propis.
3. Consultar, paginar o descarregar no crea factura, registre fiscal, cobrament, devolució, saldo ni rectificació.
4. <code>ESTAT_FACTURA</code>, <code>ESTAT_COBRAMENT</code>, <code>ESTAT_AEAT</code> i estat documental són dimensions independents.
5. L'autorització de pantalla no substitueix autorització per factura ni autorització per document.
6. UC-07 consulta factura i metadades; UC-80 autoritza/serveix bytes; UC-55/78 genera, reintenta i custodia.
7. Una fila documental CREATED no prova bytes disponibles.
8. <code>VISIBLE_ALUMNE=1</code> no autoritza per si sola una factura d'empresa/grup.
9. Històric reconstruït no és original verificat i no es converteix en VERI*FACTU retroactiu.
10. La vista FINAL no invoca un helper d'escriptura per recalcular estats.

## 4. Classes ACTUAL

~~~mermaid
classDiagram
direction TB
class Intranet {
  +buscarUsuaris_Factures(...)
  +mostrarTaulaUsuaris2_Alumnes(...)
  +mostrarTotesFacturesUsuari_Factures(...)
  +modalConsultaInformacio_Factures(id)
  +modalPrevisualitzaFactura_Factures(id)
  +generaFactura(factRel,descarrega)
  +guardarDadesFactura_Factures(...)
  +anularFactura(...)
}
class ConnexioWeb {
  +connectarBD()
  +prepare(sql)
  +closeStmt()
  +desconectarBD()
}
class Usuari {
  +getRols()
  +tePermisVisualitzacio(rolsPage)
  +setRols(rols)
}
class Dompdf {
  +load_html(html)
  +render()
  +output()
}
Intranet --> ConnexioWeb
Intranet --> Dompdf : F07
Usuari ..> Intranet : control de pàgina extern
~~~

**Precisió:** wrappers AJAX i JavaScript són adaptadors/procediments, no classes de domini. El mètode de consulta no rep actor ni resol autorització de recurs.

## 5. Classes FINAL

~~~mermaid
classDiagram
direction LR
class InvoiceQueryService {
  <<DISSENY UC-007>>
  +search(actor,criteria)
  +view(actor,uuidFactura)
}
class InvoiceVisibilityPolicy {
  <<DISSENY UC-007>>
  +canView(actor,invoice,relations)
}
class InvoiceReadRepository {
  <<DISSENY UC-007>>
  +findByUuid(uuid)
  +search(criteria,scope)
  +findLines(uuid)
  +findRectifications(uuid)
}
class InvoiceStateReadRepository {
  <<DISSENY UC-007>>
  +paymentState(uuid)
  +fiscalState(uuid)
  +aeatState(uuid)
}
class DocumentReadRepository {
  <<DISSENY UC-007>>
  +listMetadata(uuid)
}
class InvoiceDocumentAccessService {
  <<DISSENY UC-080>>
  +listAuthorized(actor,scope)
  +download(actor,documentId,tokenOrSession)
}
class DocumentAvailabilityService {
  <<DISSENY UC-055/078>>
  +check(documentId)
}
InvoiceQueryService --> InvoiceVisibilityPolicy
InvoiceQueryService --> InvoiceReadRepository
InvoiceQueryService --> InvoiceStateReadRepository
InvoiceQueryService --> DocumentReadRepository
InvoiceDocumentAccessService --> DocumentAvailabilityService
InvoiceQueryService ..> InvoiceDocumentAccessService : només quan es demanen bytes
~~~

## 6. Seqüència transversal ACTUAL F01–F07

~~~mermaid
sequenceDiagram
autonumber
actor O as Operador
participant UI as alumnes-factura.js
participant A as AJAX wrappers
participant I as Intranet.php
participant DB as BD llegada
participant FS as filesystem temporal
O->>UI: Obrir /alumnes/factura/
UI->>A: mostrarMain(url)
A->>DB: apartats + rols/breadcrumb
A-->>UI: HTML pantalla
O->>UI: Cercar
UI->>A: GET criteris F02
A->>I: buscarUsuaris_Factures
I->>DB: consultes successives / interseccions
I-->>UI: cadena #CIF|CIF
UI->>A: F03/F04 candidat
A->>I: llistat
I->>DB: factures INNER JOIN inscripcions
I-->>UI: HTML files
O->>UI: Info F05
UI->>A: GET id factura
A->>I: modalConsultaInformacio
I->>DB: factura + resum inscripció
I-->>UI: HTML
O->>UI: Preview F06
UI->>A: GET id factura
A->>I: modalPrevisualitzaFactura
I->>DB: ID -> FACTURA_RELACIONADA
I->>I: generaFactura(rel,false)
I-->>UI: HTML de totes les files relacionades
O->>UI: Download F07
UI->>A: GET descarregaFactura(rel)
A->>I: generaFactura(rel,true)
I->>DB: SELECT files i possible UPDATE GENERAT
I->>FS: file_put_contents(PDF temporal)
A-->>UI: filename
UI->>FS: GET del temporal
UI->>A: segon GET de descàrrega al circuit principal
~~~

## 7. Seqüència transversal FINAL

~~~mermaid
sequenceDiagram
autonumber
actor A as Actor autoritzat
participant UI as Canal
participant Q as InvoiceQueryService
participant P as InvoiceVisibilityPolicy
participant R as Repositoris de lectura
participant D as InvoiceDocumentAccessService UC-080
participant V as Disponibilitat UC-055/078
A->>UI: Cercar/obrir factura
UI->>Q: search/view(actor,criteria|uuid)
Q->>P: resoldre abast
P-->>Q: scope autoritzat
Q->>R: SELECT factura, línies, relacions, rectificacions i estats
R-->>Q: projecció autoritativa
Q-->>UI: vista sense mutacions
alt Actor demana document
  UI->>D: download(actor,documentId,token/sessió)
  D->>P: revalidar recurs/document
  D->>V: comprovar bytes/hash/origen
  alt autoritzat i íntegre
    V-->>D: bytes verificats
    D-->>UI: stream privat + audit access
  else denegat/absent/hash incorrecte
    D-->>UI: resultat tipificat + audit/incident
  end
end
~~~

# 8. Activitats ACTUAL/FINAL per subacció

## 8.1. F01 — càrrega i autorització de pantalla

### ACTUAL
~~~mermaid
flowchart TD
A[alumnes-factura.php] --> B[comprovarSessio.php]
B --> C[AJAX mostrarMain]
C --> D[SELECT apartat per URL]
D --> E[Recórrer pares pel breadcrumb]
E --> F[rols1 queda sobreescrit pels pares]
F --> G{tePermisVisualitzacio rols1}
G -->|sí| H[mostrarPage]
G -->|no| I[Missatge sense permisos]
~~~

### FINAL
~~~mermaid
flowchart TD
A[Entrar a pantalla] --> B[Autenticar sessió actual]
B --> C[Autoritzar funció UC-007]
C --> D[Carregar shell]
D --> E[Cada API revalida actor + recurs]
E --> F[No confiar en tePermisEdicio del client]
~~~

## 8.2. F02 — cerca

### ACTUAL
~~~mermaid
flowchart TD
A[Criteris DOM] --> B[GET consultaUsuarisFacturaRelacionada]
B --> C[LIKE DNI/email/factRel; num exacte]
C --> D[Interseccions per IDs]
D --> E[ID factura -> CIF]
E --> F[Serialitzar # i |]
F --> G[JS busca paraules error/No resultats]
G --> H[Un o diversos candidats]
~~~

### FINAL
~~~mermaid
flowchart TD
A[Criteris tipificats] --> B[Validar formats i wildcards]
B --> C[Resoldre scope actor]
C --> D[Query directa sobre factures]
D --> E[Filtrar autorització server-side]
E --> F[Resposta estructurada i paginada]
~~~

## 8.3. F03 — selector de candidats

### ACTUAL
~~~mermaid
flowchart TD
A[Cadena de CIF/DNI] --> B[mostrarTaulaUsuaris2]
B --> C[Una query d'inscripció per candidat]
C --> D[Nom/cognoms poden faltar per CIF empresa]
D --> E[HTML selector]
E --> F[Clic -> cercarUSuari]
~~~

### FINAL
~~~mermaid
flowchart TD
A[Resultats de factures autoritzades] --> B{Cal selector de receptor?}
B -->|no| C[Llistat de factures]
B -->|sí, requisit UX| D[Selector estructurat de subjectes]
D --> C
~~~

## 8.4. F04 — llistat de factures

### ACTUAL
~~~mermaid
flowchart TD
A[DNI/CIF candidat] --> B[Relacions inscripcions + factures]
B --> C[IDs per factura_relacionada]
C --> D[INNER JOIN inscripcions]
D --> E{Hi ha inscripció relacionada?}
E -->|no| F[Factura pot desaparèixer]
E -->|sí| G[GROUP BY f.id amb INSC CURS no agregat]
G --> H[Ordenació textual per num]
H --> I[Info / anul·lar / preview]
~~~

### FINAL
~~~mermaid
flowchart TD
A[Scope + criteris] --> B[SELECT factura]
B --> C[Ordenar per sèrie/any/seq]
C --> D[Afegir relacions com metadata]
D --> E[Mostrar tipus, històric/SIF, estats]
E --> F[Accions segons política server-side]
~~~

## 8.5. F05 — informació

### ACTUAL
~~~mermaid
flowchart TD
A[id factura] --> B[SELECT factura WHERE id]
B --> C[SELECT inscripcions GROUP BY IDPAG]
C --> D[Només primer grup fetch]
D --> E[HTML sense escaping contextual garantit]
E --> F[Edició adjacent per GET]
F --> G[UPDATE factura inclosa factura_relacionada]
~~~

### FINAL
~~~mermaid
flowchart TD
A[UUID_FACTURA] --> B[Autoritzar recurs]
B --> C[Llegir snapshot factura + línies]
C --> D[Llegir estats i moviments permesos]
D --> E[Llegir rectificatives/relacions]
E --> F[Resposta de només lectura]
F --> G[Qualsevol correcció deriva a UC específic]
~~~

## 8.6. F06 — previsualització

### ACTUAL
~~~mermaid
flowchart TD
A[id fila] --> B[Obtenir factura_relacionada]
B --> C[generaFactura rel,false]
C --> D[SELECT totes les files del grup]
D --> E[Una pàgina HTML per fila]
E --> F[Original + rectificativa poden aparèixer juntes]
~~~

### FINAL
~~~mermaid
flowchart TD
A[UUID_FACTURA seleccionat] --> B[Llistar documents d'aquell UUID]
B --> C{Document READY i autoritzat?}
C -->|sí| D[UC-080 preview/stream del mateix artefacte]
C -->|no| E[PENDING/UNAVAILABLE/INCIDENT]
~~~

## 8.7. F07 — descàrrega

### ACTUAL
~~~mermaid
flowchart TD
A[Clic download] --> B[Client comprova tePermisEdicio]
B --> C[GET descarregaFactura factRel]
C --> D[generaFactura true]
D --> E[Pot UPDATE GENERAT per num]
E --> F[Dompdf + fitxer temporal]
F --> G[Browser inicia GET]
G --> H[JS principal fa segon GET de generació]
H --> I[Risc de mateix filename/segon render/còpia]
~~~

### FINAL
~~~mermaid
flowchart TD
A[Clic document] --> B[UC-080 revalida actor i document]
B --> C[Comprovar storage/hash/UUID]
C --> D{OK?}
D -->|sí| E[Stream bytes exactes]
E --> F[Registrar access ALLOWED]
D -->|no| G[DENIED/UNAVAILABLE/INTEGRITY]
G --> H[Registrar intent i incidència si toca]
~~~

## 8.8. AL-16 — navegar des de fitxa alumne

### ACTUAL
~~~mermaid
flowchart TD
A[Clic #factura-insc] --> B[parseInt del text visible]
B --> C[URL #/factRel/numero]
C --> D[F02 de /alumnes/factura/]
~~~

### FINAL
~~~mermaid
flowchart TD
A[Relació inscripció-factura autoritzada] --> B[Usar UUID opac separat del text]
B --> C[Obrir UC-007 view UUID]
C --> D[Revalidar actor al servidor]
~~~

## 8.9. AL-17 — modal directe

### ACTUAL
~~~mermaid
flowchart TD
A[idInsc] --> B[SELECT FACTURA_RELACIONADA]
B --> C[generaFactura false]
C --> D[Inserir HTML al modal]
D --> E[Registrar handlers fletxes]
E --> F[Tornar a substituir modal-body]
F --> G[Handlers directes de fletxes es perden]
~~~

### FINAL
~~~mermaid
flowchart TD
A[idInsc] --> B[Resoldre UUID autoritzat]
B --> C[UC-007 consulta factura]
C --> D[Llistar metadata de documents]
D --> E[Preview UC-080 només si autoritzat]
~~~

## 8.10. AL-18 — descàrrega i cleanup

### ACTUAL
~~~mermaid
flowchart TD
A[GET descarregaFactura] --> B[Callback declarat res]
B --> C[Flux usa resD]
C --> D{resD existeix accidentalment?}
D -->|no| E[ReferenceError probable]
D -->|sí| F[link.click]
F --> G[GET eliminarArxiu filename client]
G --> H[unlink filename sense allowlist visible]
~~~

### FINAL
~~~mermaid
flowchart TD
A[Sol·licitar documentId] --> B[UC-080]
B --> C[Autorització i integritat]
C --> D[Stream des de storage privat]
D --> E[No eliminar original]
E --> F[Si existeix temporal tècnic, cleanup server-side per ID opac i TTL]
~~~

# 9. Troballes principals verificades estàticament

| Ref | Troballa |
| --- | --- |
| AUTH-01 | **CORREGIT a main:** `mostrarMain.php` conserva `ROLS_VISUALITZAR` de la pàgina en `rolsPagina`; els rols del breadcrumb ja no sobreescriuen la decisió. |
| AUTH-02 | tePermisVisualitzacio només compara rols; no autoritza factura/document. |
| AUTH-03 | **CORREGIT parcialment a main:** F02–F07 i AL-17 llegat passen per `LegacyInvoiceReadContext`, que refresca sessió/rol i comprova `ROLS_VISUALITZAR`. La política fina per recurs és la del camí SIF; el fallback llegat conserva autorització per rol de pàgina. |
| AUTH-04 | **CORREGIT a main:** `comprovarSessio.php` usa `replaceRols()` i substitueix els rols de sessió pels rols vigents de BD abans del pont SIF. La prova runtime de revocació continua ajornada. |
| F02-01 | existeixCerca pot usar-se abans d'inicialitzar-se. |
| F02-02 | %, _ mantenen semàntica LIKE si el client els envia. |
| F02-03 | contracte de resposta ad hoc amb # i | pot generar entrada buida. |
| F02-04 | JS compara dnies.split('|') amb 2000 en lloc de longitud. |
| F02-05 | peticions simultànies no tenen cancel·lació/versionat acreditat. |
| F04-01 | INNER JOIN pot ometre factura sense inscripció llegada. |
| F04-02 | INSC CURS no agregat pot condicionar una acció sobre tota la factura. |
| F04-03 | ordenació de num és textual, no tupla sèrie/any/seq. |
| F05-01 | GROUP BY IDPAG barreja SUM amb camps no agregats. |
| F05-02 | DNI i OBS PAG comparteixen id HTML. |
| F05-03 | E_FACT es recupera però no es presenta al modal. |
| F05-04 | UPDATE llegat pot canviar factura_relacionada encara que la UI no la presenti editable. |
| F05-05 | l'endpoint retorna OK sense comprovar affectedRows. |
| F06-01 | ID fila es converteix en FACTURA_RELACIONADA; la identitat seleccionada es perd. |
| F06-02 | original i rectificativa poden quedar com pàgines d'un únic PDF reconstruït. |
| F07-01 | GENERAT es modifica durant la descàrrega i s'usa també com a gate GTAF/anul·lació. |
| F07-02 | **CORREGIT a main:** F07 principal fa una sola crida a `descarregaFactura.php`; s'ha eliminat la segona generació. |
| F07-03 | filename temporal sempre prefix A, fins i tot per rectificatives R. |
| F07-04 | nom/estat pot provenir de l'última fila del grup sense ORDER BY. |
| F07-05 | file_put_contents no es comprova abans de retornar filename. |
| PDF-01 | emissor i text d'exempció estan hardcoded al PHP. |
| PDF-02 | logo prové d'una URL remota actual; regeneració no és immutable. |
| PDF-03 | el generador llegat no acredita QR/UUID/hash/estat AEAT de VERI*FACTU. |
| AL17-01 | el segon html(res) substitueix fletxes després de registrar-ne handlers; download és al footer i no queda substituït. |
| AL18-01 | callback usa resD tot i declarar res. |
| AL18-02 | **CORREGIT a main:** `eliminarArxiu.php` és POST, revalida sessió, accepta només basename PDF i limita `realpath` a `ajax/alumnes`; els documents SIF no passen per aquest endpoint. |
| AL18-03 | **CORREGIT funcionalment:** la factura llegada ja no esborra el temporal immediatament després de `link.click()`; s'ha afegit cleanup CLI amb TTL. La prova de regressió queda ajornada. |

# 10. Model SIF: dades existents i gaps de consulta

| Capacitat | Evidència | Estat |
| --- | --- | --- |
| Identitat fiscal | factura.UUID_FACTURA/NUM_VISIBLE/sèrie/any/seq | IMPLEMENTAT |
| Snapshot receptor/totals | factura | IMPLEMENTAT |
| Línies fiscals | factura_linia | IMPLEMENTAT |
| Original-rectificativa | factura_rectificacio | IMPLEMENTAT A ESQUEMA |
| Moviments i assignacions | payment_transaction/payment_allocation | IMPLEMENTAT |
| Estat de cobrament | factura.ESTAT_COBRAMENT | IMPLEMENTAT |
| Registres fiscals | factura_registres + latestForInvoice | IMPLEMENTAT/PARCIAL |
| Metadata documental | factura_documents + registerDocument | IMPLEMENTAT PARCIAL |
| Cerca de factura per UUID/NUM_VISIBLE/receptor/origen | `InvoiceReadRepository` + `InvoiceQueryCriteriaValidator` | IMPLEMENTAT PARCIAL |
| Política de visibilitat interna | `InternalInvoiceScopeResolver` + `ResolvedInvoiceVisibilityPolicy` | IMPLEMENTAT per canal intern; externa alumne/empresa pendent UC-102/126 |
| Pont intranet autenticat | `sifFactures.php` + `SifInternalApiClient` + HMAC/anti-replay | IMPLEMENTAT PARCIAL |
| Llistat documental autoritzat | metadata a UC-007 + política UC-080 | IMPLEMENTAT PARCIAL |
| Streaming privat + audit access | `InvoiceDocumentAccessService` + `PrivateDocumentStore` + `fiscal_document_access` | IMPLEMENTAT PARCIAL / rollout flag |
| Storage/generador/worker documental | UC-55/78 | PENDENT/PARCIAL segons artefacte |

**Gaps addicionals:** l'emissió base actual insereix E_FACT=0; fact_rels.VISIBLE_ALUMNE té default 1 i el productor també usa 1 si s'omet el camp. Cap d'aquests valors substitueix una decisió funcional/autorització.

# 11. Frontera UC-07/36/55/78/80

| UC | Propietat |
| --- | --- |
| UC-07 | cercar/consultar factura, estats i metadata documental autoritzada |
| UC-36 | cas funcional d'obtenció/generació d'una representació; delega infraestructura i accés |
| UC-55 | cua, retry, recuperació, integritat i custòdia operativa |
| UC-78 | generar/custodiar PDF, QR, XML des de snapshot immutable |
| UC-80 | autoritzar document concret, servir bytes, registrar VIEW/DOWNLOAD/DENIED |

# 12. Matriu mínima de proves pendent d'execució

## 12.1. Autorització

| ID | Escenari | Esperat |
| --- | --- | --- |
| AUTH-UC007-01 | sense rol | denegació sense dades |
| AUTH-UC007-02 | rol consulta, factura fora d'abast | denegació de recurs |
| AUTH-UC007-03 | alumne receptor + visible | consulta permesa |
| AUTH-UC007-04 | participant de factura empresa | no PDF complet per defecte |
| AUTH-UC007-05 | visible=1 però actor no receptor/representant | denegació |
| AUTH-UC007-06 | auditor vigent/revocat | lectura dins scope / denegació després de revocació |
| AUTH-UC007-07 | canviar UUID manualment | cap accés aliena |
| AUTH-UC007-08 | rol revocat durant sessió | nova petició aplica rol actual |

## 12.2. Identitat i rectificatives

| ID | Escenari | Esperat |
| --- | --- | --- |
| ID-UC007-01 | ordinària | mateix UUID de principi a fi |
| ID-UC007-02 | original + R | dos UUID/números/documents |
| ID-UC007-03 | diverses R | historial explícit |
| ID-UC007-04 | mateixa referència llegada | no fusionar |
| ID-UC007-05 | UUID inexistent | NOT_FOUND tipificat |

## 12.3. Estats i no-mutació

| ID | Escenari | Esperat |
| --- | --- | --- |
| STATE-UC007-01 | emesa abans de cobrar | ISSUED + PENDING |
| STATE-UC007-02 | parcial/pagat/refund | estat i moviments coherents |
| STATE-UC007-03 | AEAT pendent/acceptada | estat independent del pagament |
| STATE-UC007-04 | divergència factura vs últim registre AEAT | detectar/reconciliar |
| STATE-UC007-05 | consulta repetida/concurrent | zero UPDATE/INSERT fiscal/econòmic |

## 12.4. Documents

| ID | Escenari | Esperat |
| --- | --- | --- |
| DOC-UC007-01 | bytes/hash correctes | servir mateix artefacte |
| DOC-UC007-02 | hash incorrecte | denegar + incidència |
| DOC-UC007-03 | metadata CREATED, bytes absents | no disponible |
| DOC-UC007-04 | token/grant caducat | denegació auditada |
| DOC-UC007-05 | llistat i download | revalidar a download |
| DOC-UC007-06 | històric original verificat | servir original |
| DOC-UC007-07 | històric reconstruït | etiquetar reconstrucció |
| DOC-UC007-08 | download repetit | zero nova emissió/document fiscal |

## 12.5. Llegat específic

| ID | Escenari |
| --- | --- |
| LEG-UC007-F01 | pare/fill amb rols diferents per provar rols1 |
| LEG-UC007-F02 | cerca només email/factRel/num i warnings PHP |
| LEG-UC007-F02B | wildcard %, _, més de 2000 resultats, peticions solapades |
| LEG-UC007-F03 | CIF empresa sense inscripció i delimitador inicial |
| LEG-UC007-F04 | factura sense inscripció, multiinscripció i ordre 2/9/10/R |
| LEG-UC007-F05 | ID inexistent, múltiples IDPAG, escaping, affectedRows |
| LEG-UC007-F06 | ID inexistent i original+R |
| LEG-UC007-F07 | doble GET, mateixa-segon, write fallit, nom R, URL/cleanup |
| LEG-UC007-AL17 | multipàgina i inscripció sense factura |
| LEG-UC007-AL18 | res/resD, error handler, cleanup segur i cursa |

# 13. Tests existents localitzats que NO tanquen UC-007

- DocumentsAndIncidentsTest comprova metadata/hash registrats, no storage, autorització ni streaming.
- HistoricalInvoiceMigrationServiceTest comprova importació històrica sense registre/cua fiscal nous, no identitat d'emissor/document original.
- HttpEndpointsTest comprova textualment endpoints de factura/pagament/redsys, no existeix prova E2E de consulta autoritzada de factura/document.
- Les proves d'emissió/pagament/rectificació cobreixen efectes dels seus UCs, no la invariant «N lectures = zero mutacions».

# 14. Implementació iniciada

| Peça | Estat després de l'auditoria |
| --- | --- |
| `InvoiceReadRepository` | IMPLEMENTAT: lectura exacta, sense writes ni paths interns |
| `InvoiceQueryService` | IMPLEMENTAT PARCIAL: view/search amb política obligatòria |
| `InvoiceVisibilityPolicyInterface` | IMPLEMENTAT com a contracte |
| `ResolvedInvoiceVisibilityPolicy` | IMPLEMENTAT: scope fail-closed FULL/MINIMAL; per intranet el scope prové de rol autenticat via `InternalInvoiceScopeResolver` |
| Errors 403/404 | IMPLEMENTATS a `SifException` |
| `InvoiceQueryServiceTest` / `ResolvedInvoiceVisibilityPolicyTest` | PROVES ESCRITES; no executades en aquesta revisió |
| `query-invoice.php` / `InvoiceQueryScriptTest` | CLI read-only no productiu + prova real del script escrites |
| Endpoint HTTP UC-007 | IMPLEMENTAT INTERN: `POST /api/factures/query.php` amb HMAC, timestamp i anti-replay |
| UC-080 bytes/auditoria | IMPLEMENTAT PARCIAL: endpoint intern, storage privat, hash i `fiscal_document_access`; rollout/entorn i proves ajornats |

Les proves escrites cobreixen zero mutació, denegació, not found, cerca exacta sense wildcard implícit, absència de `PATH_FITXER` en metadata i separació entre cobrament i rectificativa.
# 15. Criteri de tancament

UC-007 es podrà marcar **IMPLEMENTAT I PROVAT** només quan existeixi una ruta de consulta server-side que apliqui política per recurs, retorni projecció estructurada per UUID, integri estats sense mutació, derivi bytes a UC-080, i la matriu anterior tingui evidència reproduïble de preproducció. Fins aleshores, el cas queda **DOCUMENTAT I AUDITAT ESTÀTICAMENT / IMPLEMENTACIÓ FINAL PENDENT**.


## 15.1. Implementació posterior a l'auditoria

Després del tancament estàtic s'han implementat el repositori/servei de lectura, política de scope, gateway, validació de criteris, API interna HMAC amb anti-replay, client server-to-server, pont AJAX autenticat, feature flag, cerca SIF i detall read-only, resolució participant/receptor i override AL-17. `replaceRols()` substitueix els rols de sessió pels rols vigents de BD durant `comprovarSessio.php`.

Les proves noves i de regressió es mantenen **AJORNADES** a [03-proves-pendents-uc-007-implementacio.md](03-proves-pendents-uc-007-implementacio.md). Aquest ajornament no converteix cap cas runtime en verificat.


## 16. Rollout controlat

- `SIF_UC007_QUERY_ENABLED=1` activa el pont de consulta SIF a la intranet; per defecte queda desactivat.
- `SIF_UC080_DOCUMENT_ENABLED=1` activa la descàrrega segura de documents; per defecte queda desactivada.
- `FEATURE_DISABLED` és l'únic bypass explícit de rollout cap al llegat; errors d'autenticació, HMAC, permisos o servei **no** fan fallback silenciós.
- AL-16 i AL-17 resolen primer la factura per `view_by_enrollment`; `NO_SIF` o flag desactivat conserva el llegat mentre dura la migració.
- Les proves de rollout, permisos, HMAC, storage i regressió continuen ajornades a [03-proves-pendents-uc-007-implementacio.md](03-proves-pendents-uc-007-implementacio.md).


### 16.1. Correccions del fallback llegat aplicades

- `mostrarMain.php`: rol de pàgina separat del rol dels ancestres del breadcrumb.
- F02–F07/AL-17 llegats: context comú `LegacyInvoiceReadContext` amb sessió refrescada i `ROLS_VISUALITZAR` server-side.
- Edició/anul·lació: POST, `ROLS_EDITAR` server-side, origen/XHR i `SifLegacyInvoiceMutationGuard` activable amb `SIF_BLOCK_LEGACY_INVOICE_MUTATIONS=1`.
- `descarregaFactura.php`: POST i validació d'origen; el JS fa una sola generació i usa el nom retornat sense afegir `.pdf` de nou.
- `eliminarArxiu.php`: POST, basename PDF, root fix i comprovació d'`unlink`.
- `maintenance/cleanupFacturaTemporals.php`: neteja CLI amb TTL només dels temporals `A<ANY>-<ORDRE>-<timestamp>.pdf` del generador llegat.
- AL-18 llegat: ja no fa cleanup immediat després del clic de descàrrega; evita la cursa browser/unlink.
- Les proves d'aquestes correccions continuen AJORNADES al document 03.
