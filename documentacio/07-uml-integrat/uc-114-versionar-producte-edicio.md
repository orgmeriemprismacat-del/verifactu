# UC-114 · Versionar canvis de producte o edició amb operacions obertes

**Objectiu de la fitxa original:** versionar **nom, dates, hores, preu, fiscalitat i regles**, mantenint els snapshots acceptats d'operacions obertes o obrint-ne un canvi explícit; mai substituir silenciosament les dades que un pagador ja va acceptar. **Decisions de negoci confirmades el 22/09/2026:** els canvis de data, horari, modalitat, hores i acreditació es notifiquen sense exigir acceptació prèvia; si a l'alumne no li van bé, se li ofereix canvi d'edició. La mateixa persona de l'equip amb accés a la intranet decideix el canvi, sense segona aprovació interna. Els termes econòmics d'ofertes ja acceptades es preserven; si es proposa canviar-los, es genera una oferta nova. La cancel·lació d'edició segueix UC-127, amb regla pròpia.

**Evidència del repositori:** `master_data_change_request` està definida amb `ENTITY_TYPE/KEY`, `BASE_VERSION`, `PROPOSED_VERSION`, `CHANGESET_JSON`, `AFFECTED_OPEN_OPERATIONS_JSON`, motiu, decisió, actor i correlació. El SQL imposa unicitat de `ENTITY_TYPE+ENTITY_KEY+PROPOSED_VERSION`. `commercial_operation_line` preserva `PRICE_RULE_VERSION` i `SNAPSHOT_JSON`; `RedsysPaymentIntentService::create()` rebutja reutilitzar el mateix `DS_ORDER` amb un snapshot/import/venciment canviats. **No s'ha acreditat** un `MasterDataChangeService` PHP que executi impact analysis, autorització i propagació al llegat.

## 1. Fitxa funcional específica

| Aspecte | Regla |
| --- | --- |
| Actors | Persona de l'equip amb accés a intranet que efectua i decideix el canvi sense segona aprovació interna (identificació i autorització en servidor), persones inscrites destinatàries de l'avís i, només si canvien termes econòmics acceptats, pagador destinatari d'una nova oferta. Si una correcció fiscal específica requereix tractament especial, es tramita pel seu UC, no es pressuposa una segona aprovació per editar l'edició. |
| Entrada | Producte/curs/edició, versió antiga i proposada, camps canviats (nom, dates, hores, preu, descomptes, aforament, fiscalitat), causa, `EFFECTIVE_AT` i instant de publicació; identitats d'operacions obertes afectades. |
| Versió de canvi | `master_data_change_request` conserva el changeset i la llista d'operacions obertes **com a JSON**. Són camps de proposta, no garantia d'identificar totes les operacions si no hi ha consulta/lock real. |
| Operacions ja acceptades | El snapshot històric, el preu pactat i la factura existent no es reescriuen. Els canvis excepcionals de data/horari/modalitat/hores/acreditació **es notifiquen, sense exigir acceptació prèvia**; si no van bé, s'ofereix canvi d'edició. Si es pretén substituir les condicions **econòmiques** acceptades, cal una nova oferta/ordre quan pertoqui (UC-112/121); separar la comunicació de l'eventual tractament individual fiscal o acadèmic. |
| Factures emeses | `InvoiceService` persisteix factura fiscal; UC-114 **no és permís** per actualitzar `factura_linia`, data/import o hash després d'emetre. Un servei efectivament modificat deriva a UC-71/74/72 segons l'operació real. |
| Diners i places | Canviar catàleg no és `CHARGE`, `REFUND` ni confirmació de capacitat. Si una reserva queda incompatible amb la nova edició, UC-115/121 classifica disponibilitat i oferta; pagaments reals anteriors es concilien per separat. |

### Flux objectiu

1. L'operador prepara una proposta `BASE_VERSION→PROPOSED_VERSION` i calcula una vista prèvia dels canvis de nom, dates/hores, preus, aforament i fiscalitat.
2. Un coordinador **pendent** busca operacions en curs, reserves, intents TPV i factures que referencien el producte/edició, i desa exactament quines ofertes/participants estan afectats. No identificar impacte només per les inscripcions que encara no s'han cobrat: pot haver-hi callback pendent o factura anterior.
3. Distingeix: (a) modificació informativa de data/horari/modalitat/hores/acreditació, que es **notifica** i ofereix canvi d'edició si no va bé, sense demanar consentiment previ; (b) modificació de condicions **econòmiques** d'una oferta ja acceptada, que no substitueix l'oferta històrica i requereix proposta nova UC-112/121; (c) anul·lació de l'edició, que segueix UC-127. Les autoritzacions de servidor i el tractament per operació encara s'han d'implementar/verificar.
4. La mateixa persona de l'equip amb accés a intranet decideix i publica la versió, **sense segona aprovació interna**; s'enregistra actor, data i resultat de propagació a la BD llegada, i, si falla, queda incidència/reconciliació sense afirmar que totes les operacions s'han actualitzat.
5. Les noves compres usen la versió publicada. Les existents preserven el snapshot històric; els canvis notificables no requereixen acceptació expressa abans de comunicar i aplicar l'edició modificada. **Un canvi dels termes econòmics ja acceptats** no s'incorpora silenciosament: cal una nova oferta acceptada, i una nova ordre quan pertoqui; `RedsysPaymentIntentService` rebutja canviar dades d'un mateix `DS_ORDER`.
6. Si ja s'havia emès factura, una modificació real de prestació/import segueix la classificació fiscal i els moviments per inscripció corresponents; el canvi del catàleg no edita l'original.

### Alternatives i proves

| Escenari | Control |
| --- | --- |
| Canvi de títol intern sense impacte en l'oferta | Registrar versió i política de presentació; no reescriure títol fiscal emès. |
| Canvi de data d'un taller amb reserves acceptades | Notificar les persones afectades; si el canvi no els va bé, oferir canvi d'edició. Preservar la història de l'oferta i classificar qualsevol afectació econòmica/fiscal per separat, **sense exigir acceptació prèvia de la nova data**. |
| Pujada de preu mentre existeix `DS_ORDER` pendent | No reutilitzar mateixa ordre amb total nou; nova acceptació i oferta separada quan sigui aplicable. |
| Callback vell després de publicar nova versió | Processar el fet bancari real i reconciliar oferta antiga/plaça, en lloc d'emetre automàticament al preu nou. |
| Dos operadors publiquen la mateixa `PROPOSED_VERSION` | La unicitat SQL evita dues files amb aquesta clau, però **no** garanteix la gestió de conflictes del catàleg llegat: lock/versionat aplicatiu pendent. |

**Pendents tècnics/documentals:** contrastar permisos al backend, ruta del canvi de preu, missatges reals, comparació entre esquemes, publicació i recuperació de dades mestres, totes les accions per pàgina, proves concurrents i callbacks d'ofertes antigues. **No estan pendents de decisió la regla de notificació ni l'absència de segona aprovació interna.** Sense proves PHP executades.

### 1.3. Edició modificada des de la intranet amb reserves, ofertes i factures obertes

**Punt real de canvi d'edició.** El document d'estat final identifica `Intranet::desarCanvisEstatEnviarMsg_PreviIniciCursos()`: passa curs/edició entre pendent, actiu i anul·lat, dona de baixa alumnes, consulta factura i forma de pagament, cerca edicions futures i envia avisos. És un **flux múltiple**, no una simple edició de la data o el preu d'una fitxa mestra. Aquesta dada contrasta amb `master_data_change_request`, que registra capçalera i operacions afectades en SQL però **no acredita** que el mètode llegat consulti, bloquegi o actualitzi aquesta taula.

**Operacions afectades abans i després del cobrament.** En previsualitzar el canvi de producte/edició, inventariar per `ID_INSC` i `UUID_OPERATION` les reserves i `DS_ORDER` pendents, factures reals ja emeses abans de cobrar, cobraments confirmats, packs/grups amb persones d'altres edicions i accessos Moodle. Una oferta congelada pot tenir data/preu anteriors a l'edició viva, i una factura real no es pot «actualitzar» amb el nou títol per correspondre amb la web. Si es canvia el servei contractat, el tractament individual és UC-71/74/127, no la substitució massiva del concepte en `factura_linia`.

**Avisos i callbacks en curs.** El mètode llegat pot enviar comunicacions quan canvia l'estat: el nou circuit ha de notificar **la decisió i el resultat real per inscrit**, no afirmar una baixa Moodle o una devolució no confirmades. Una intenció signada abans del canvi conserva el seu `SNAPSHOT_JSON`; el callback actual valida import/divisa/terminal, però **no rellegeix l'estat d'edició** en `assertMatchesIntent()`. Qualsevol ingrés tardà es reconcilia amb aquella oferta i reserva, no es factura silenciosament amb el preu de l'edició nova.

### 1.4. Proves de canvi d'edició amb operacions obertes (no executades)

| ID | Escenari | Resultat exigible |
| --- | --- | --- |
| VE-114-01 | Edició anul·lada mentre hi ha una factura real pendent de transferència | Document existent preservat; decisió econòmica i fiscal individual, no baixa automàtica de deute. |
| VE-114-02 | Canvi de preu després de signar `DS_ORDER` | Snapshot original intacte; nova oferta/ordre només si correspon i s'accepta. |
| VE-114-03 | Pack amb un curs afectat i un altre curs vigent | Inventari i decisió per línia/participant, no anul·lació indiscriminada de tot el pack. |
| VE-114-04 | El llegat comunica «baixa efectuada» però Moodle encara no la registra | Avís d'estat parcial i incidència UC-129, no confirmació fictícia. |
| VE-114-05 | Callback de l'edició antiga arriba després de publicar la versió nova | Conservar ingrés real i revisar oferta/plaça antiga, sense nova factura automàtica. |

## 2. UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Gestió acadèmica" as G
actor "Responsable comercial/fiscal" as A
actor "Pagador afectat" as P
rectangle "SIF · versió del catàleg" {
 usecase "UC-114\nVersionar producte o edició" as Main
 usecase "Comparar versions i afectació" as Compare
 usecase "Classificar operacions obertes" as Impact
 usecase "Publicar versió aprovada" as Publish
 usecase "UC-121\nAcceptar oferta nova quan cal" as Renew
}
G --> Main
A --> Main
Main ..> Compare : <<include>>
Main ..> Impact : <<include>>
Main ..> Publish : <<include>> (aprovat)
P --> Renew
@enduml
```

## 3. Diagrama de classes: SQL present, orquestració pendent

```mermaid
classDiagram
class MasterDataChangeService {
 <<DISSENY: no acreditat>>
 +propose(entity,base,changes) request
 +previewImpact(request) operations
 +approveAndPublish(request,actor) result
}
class MasterDataChangeRepository {
 <<DISSENY: SQL definit>>
 +append(db,request) result
 +recordDecision(db,uuid,decision) result
}
class OpenOperationsLookup {
 <<DISSENY: cerca entre sistemes>>
 +affectedBy(entity,version) operations
}
class RedsysPaymentIntentService {
 <<PHP existent: protegir DS_ORDER>>
 +create(db,input) array
}
MasterDataChangeService --> MasterDataChangeRepository : versions i decisió
MasterDataChangeService --> OpenOperationsLookup : snapshots afectats
```

## 4. Seqüència — canvi de data/preu amb ofertes obertes (DISSENY)

```mermaid
sequenceDiagram
actor G as Gestió
participant S as MasterDataChangeService [DISSENY]
participant R as MasterDataChangeRepository [DISSENY]
participant Q as OpenOperationsLookup [DISSENY]
participant Legacy as Catàleg llegat [integració pendent]
participant Offer as UC-121 Nova acceptació [DISSENY]
G->>S: Proposar canvi d'edició versió v1→v2
S->>R: append(v1,v2,CHANGESET_JSON)
S->>Q: affectedBy(edició,v1)
Q-->>S: Reserves, intencions i factures afectades
S-->>G: Impacte, opcions i bloquejants
G->>S: Aprovar canvi per rol autoritzat
S->>Legacy: Publicar edició v2 amb correlació
alt Error de propagació
 Legacy-->>S: Fallada
 S->>R: Registrar incidència/estat pendent
else Publicada
 Legacy-->>S: Versió v2 observada
 S->>R: Registrar resultat
 opt Oferta antiga requereix nova acceptació
  S->>Offer: Proposar oferta v2 sense mutar intent v1
 end
end
Note over S,Offer: No es modifica cap factura emesa ni es crea pagament per canviar el catàleg.
```

## 5. Traçabilitat

[UC-114 original](../06-fitxes-funcionals/uc-114.md) · [UC-112 snapshot](uc-112-congelar-snapshot-abans-tpv.md) · [UC-121 renovar oferta](uc-121-repreuar-renovar-reserva-caducada.md) · [UC-115 capacitat](uc-115-reservar-alliberar-places.md) · [RedsysPaymentIntentService](../../sif/src/Service/RedsysPaymentIntentService.php) · [Migració master_data_change_request](../../sif/database/migrations/2026_09_16_000005_add_operation_lifecycle_tables.sql).

## 6. Auditoria dirigida dels punts d'entrada reals — 22/09/2026

**Font:** `main` al commit `e71958b3026549bde09fb4b25f2ec3ba370937ec`. [Auditoria detallada del lot 03, amb 4 accions, diagrames d'activitat parcials i proves proposades](00-auditoria-casos-pendents-lot-03-uc-114-2026-09-22.md). Aquest annex documenta la fase inicial de contrast dels wrappers. **Actualització posterior:** s'han llegit també els cossos complets dels mètodes d'`Intranet.php` i el SQL del constructor, descrits a la [fitxa funcional](../06-fitxes-funcionals/uc-114.md#23-fitxa-funcional-de-les-accions-reals-dedició--contrast-del-codi-complet-22092026) i als diagrames de la secció 7. No s'ha executat el desplegament real.

| Acció distingida | Punt d'entrada verificat | Implicació per al cas d'ús |
| --- | --- | --- |
| Desar dades d'edició | [`ajax/cursos/desarCanvisDadesEdicio.php`](../../codi-drive/intranet-actual/ajax/cursos/desarCanvisDadesEdicio.php#L16-L32) rep `idCurs`, nom, dates, hores, curs escolar, GTAF/FISS i altres dades; delega a `Intranet::desarCanvisDadesEdicio()`. **Aquest wrapper no rep `idPreu`.** | El cos del mètode **ja s'ha contrastat**: actualitza `cursos` i `curs` per separat i emet valors de depuració, sense transacció visible en el mètode. Localitzar la via diferent de reassignació d'`ID_PREU` des de la BD i no suposar que aquest formulari canvia el preu. Matriu camp × operacions afectades × decisió. |
| Desar dades d'aula | [`desarCanvisDadesAulaEdicio.php`](../../codi-drive/intranet-actual/ajax/cursos/desarCanvisDadesAulaEdicio.php#L16-L24) rep aula, ID d'aula, dates de revisió/informe i observacions i delega a un altre mètode. | El cos actual modifica dades d'informe/revisió/observacions a `cursos` i `aula` amb dos UPDATE; el control `idAula=0` és posterior al primer UPDATE, i no hi ha rollback visible. Separar dada administrativa d'eventual canvi de prestació en funció de les dades efectivament editades. |
| Canviar estat | [`desarCanvisEstatEnviarMsg.php`](../../codi-drive/intranet-actual/ajax/cursos/desarCanvisEstatEnviarMsg.php#L15-L27) rep curs/edició/estat antic/nou per **GET**, malgrat un comentari que indica canviar a POST, i invoca `desarCanvisEstatEnviarMsg_PreviIniciCursos()`. | Inventariar codis i transicions d'estat reals, efectes de matrícula, avisos, factures, saldo i baixes separadament; verificar permisos i mètodes interns abans de concloure si tenen protecció. |
| Importar/crear edicions CSV | [`cursos-afegir-modificar-edicions.js`](../../codi-drive/intranet-actual/js/cursos-afegir-modificar-edicions.js#L210-L310) llança una petició `inserirCurs.php` per fila seleccionada i una altra per al número de tràmit; [el wrapper d'inserció](../../codi-drive/intranet-actual/ajax/cursos/inserirCurs.php#L16-L33) rep també `idPreu` i `public`. | Distingir crear una edició nova de versionar-ne una d'existent; definir idempotència, resultat per fila, error parcial i publicació controlada. Al JS inspeccionat no s'espera la resolució de totes les insercions abans d'enviar el tràmit; comprovar transaccions internes del servidor abans d'inferir resultat persistent. |

**Contracte de dades:** `master_data_change_request` ja existeix a [la migració 000005](../../sif/database/migrations/2026_09_16_000005_add_operation_lifecycle_tables.sql#L196-L217) i `commercial_operation_line` defineix `PRICE_RULE_VERSION` i `SNAPSHOT_JSON`. L'esquema no acredita un writer/orquestrador PHP ni que la migració s'hagi aplicat. [`RedsysPaymentIntentService::create()`](../../sif/src/Service/RedsysPaymentIntentService.php#L19-L65) protegeix el mateix `DS_ORDER` davant un snapshot diferent, però no fa una anàlisi general d'impacte per edició.

**Proves:** V114-01–V114-08 al [lot 03](00-auditoria-casos-pendents-lot-03-uc-114-2026-09-22.md#6-proves-dacceptació-proposades--no-executades) són casos d'acceptació **definits, no executats**. Els diagrames inicials del lot eren **parcials**; els d'edició/aula/consulta/CSV i els circuits d'estat ja estan ampliats a les seccions 7 d'[UC-114](uc-114-versionar-producte-edicio.md#7-diagrames-dactivitat-contrastats-per-acció-de-la-pantalla) i [UC-127](uc-127-canvi-estat-edicio-operacions-afectades.md#7-diagrames-dactivitat-de-lestat-dedició-amb-el-php-complet). Encara resta tancar els JS/modals i les altres pàgines de RM-037.

**Estat de la fitxa:** contrast dels cossos PHP i consultes SQL de les accions A114-01/02/04 i A127-CAN/PEN/ACT **completat documentalment en el tall de codi**; no tancada funcionalment perquè falten handlers JS/captures, ruta d'edició directa d'ID_PREU, permisos efectius, verificació d'estat de BD, desplegament i execució de proves. UC-111 i UC-113 no s'han inclòs en aquesta auditoria.

### Regles operatives verificades amb la responsable de negoci · 22/09/2026

El preu habitual d'un curs es determina per les hores mitjançant `ID_PREU` i `preus`; excepcionalment es pot canviar des de la base de dades assignant un altre `ID_PREU`. No atribuir un selector de preus al formulari `desarCanvisDadesEdicio.php`, que no el rep. Els canvis d'edició són excepcionals, i la mateixa persona de l'equip amb accés a la intranet pot decidir-los sense una segona validació interna; distingir-ho del control d'autenticació, rol i traça a servidor.

**Canvi informatiu:** notificar el canvi de data (inclòs ajornar dos dies), hora, modalitat, hores o acreditació; si no agrada, oferir canvi d'edició. **No requerir acceptació prèvia** d'aquest canvi. **Canvi de preu ja acceptat:** conservar l'oferta econòmica acceptada i, si es pretén modificar-ne el contingut econòmic, presentar-ne una de nova. **Anul·lació de l'edició:** seguir [UC-127](uc-127-canvi-estat-edicio-operacions-afectades.md), no importar-ne la pauta al simple ajornament.

Aquesta decisió corregeix les formulacions anteriors que exigien «consentiment» o «nova acceptació expressa» indiscriminadament per canvis de data/horari; els diagrames de seqüència i d'activitat han de seguir aquesta distinció en revisar-los. [Fitxa funcional UC-114](../06-fitxes-funcionals/uc-114.md).


## 7. Diagrames d'activitat contrastats per acció de la pantalla

**Inventari de pàgina de consulta d'edicions:** `cursos-consultar-edicions-curs.php` → `ajax/mostrarMain.php` (comprova rol per al contingut dinàmic) → `Intranet::mostrarInformacioCurs_Cursos()` → apartats `dades-estat-inscripcio`, `dades-edicio`, `dades-aula` i modal d'alumnat [Intranet.php L17014–17038](../../codi-drive/intranet-actual/Intranet.php#L17014-L17038). Les dades d'edició comparen `cursos` amb `curs` i mostren divergències; les dades d'aula inclouen visibilitat Moodle i consulta d'alumnat. **Aquest inventari és de PHP generat, no substitueix encara la captura i verificació dels handlers JS de cada botó.** Els mètodes de desament i importació **sí que s'han llegit complets** [fitxa funcional contrastada](../06-fitxes-funcionals/uc-114.md#23-fitxa-funcional-de-les-accions-reals-dedició--contrast-del-codi-complet-22092026).

### A114-00 · Consulta d'edició/aules — ACTUAL

```plantuml
@startuml
title A114-00 ACTUAL | Consulta edició i apartats (PHP render)
start
:Entrar a cursos-consultar-edicions-curs.php;
:ajax/mostrarMain.php consulta rol de visualització;
if (Pot visualitzar pàgina?) then (Sí)
  :Carregar pàgina dinàmica;
  :GET mostrarInfoEdicioCurs amb idCurs i cercaPer;
  :Consultar curs, cursos i aula;
  :Calcular visibilitat inscripció i enllaç web;
  :Comparar valors de curs i cursos;
  if (Hi ha divergències?) then (Sí)
    :Mostrar avisos de divergència;
  endif
  :Mostrar apartat estat inscripció;
  :Mostrar dades edició i dades aula;
  :Consultar existència i visibilitat Moodle per aula;
  :Oferir consulta alumnat des de la vista d'aula;
else (No)
  :Mostrar missatge de manca de permís;
endif
stop
@enduml
```

**Font:** [`mostrarMain.php`](../../codi-drive/intranet-actual/ajax/mostrarMain.php), [`Intranet.php` L17014–17038](../../codi-drive/intranet-actual/Intranet.php#L17014-L17038), [L17252–17325](../../codi-drive/intranet-actual/Intranet.php#L17252-L17325) i [L17960–18116](../../codi-drive/intranet-actual/Intranet.php#L17960-L18116). La comprovació de permís de la pàgina no demostra el control de recursos de cada endpoint AJAX.

### A114-00 · Consulta d'edició/aules — FINAL

```plantuml
@startuml
title A114-00 FINAL | Consulta versionada i accions autoritzades
start
:Autenticar actor i autoritzar consulta d'edició al servidor;
if (Actor/edició accessibles?) then (Sí)
  :Llegir versió, estat real, ofertes afectades i dades d'aula;
  :Comparar valors curs, cursos i aula amb versió SIF;
  if (Divergències?) then (Sí)
    :Mostrar diferències i incidència de reconciliació;
  endif
  :Mostrar apartats i accions autoritzades;
  :Consultar Moodle i import/factures per canal autoritzat;
  :No alterar snapshots ni documents en una simple consulta;
else (No)
  :Denegar sense revelar dades de l'edició;
endif
stop
@enduml
```

### A114-01 · Desar dades d'edició — ACTUAL, mètode complet

```plantuml
@startuml
title A114-01 ACTUAL | Desar edició en dues taules
start
:POST desarCanvisDadesEdicio amb idCurs i camps;
:Normalitzar dates no buides, altrament NULL;
:Obrir connexió a BD web;
:Mostrar SQL i valors de depuració a la resposta;
if (Es pot preparar UPDATE cursos?) then (Sí)
  :UPDATE cursos WHERE id_Curs LIKE idCurs prefix;
else (No)
  :Llançar error 4313;
  stop
endif
:Mostrar SQL i valors de depuració a la resposta;
if (Es pot preparar UPDATE curs?) then (Sí)
  :UPDATE curs WHERE ID_CURS = idCurs;
  :Retornar OK;
else (No)
  :Llançar error 4314;
endif
note right
  Al mètode no es veu transaction/rollback.
  La consulta amb LIKE pot afectar
  diversos registres de cursos.
  No rep idPreu.
end note
stop
@enduml
```

**Font:** [`Intranet.php` L18238–18324](../../codi-drive/intranet-actual/Intranet.php#L18238-L18324), SQL dels dos UPDATE [L1108–1113](../../codi-drive/intranet-actual/Intranet.php#L1108-L1113). No atribuir a la consulta `LIKE` canvis de files no comprovats en BD: el risc és potencial i cal test amb dues aules.

### A114-01 · Desar dades d'edició — FINAL, regla negoci incorporada

```plantuml
@startuml
title A114-01 FINAL | Versió i avís sense consentiment indiscriminat
start
:POST de canvi amb id edició, versió base, actor i motiu;
:Verificar permís servidor, claus exactes i validar dades;
if (Versió concurrent o dades invàlides?) then (Sí)
  :Denegar sense UPDATE i mostrar conflicte/error;
else (No)
  :Consultar registres de curs/cursos i operacions afectades;
  if (Canvien termes econòmics ja acceptats?) then (Sí)
    :Mantenir preu i snapshot antic;
    :Proposar nova oferta per acceptació expressa;
  else (No)
    :Aplicar canvi versionat i consistent a taules afectades;
    if (Canvien dates, hores, horari, modalitat o acreditació?) then (Sí)
      :Registrar avisos a inscrits després del commit;
      :Oferir canvi d'edició si no els va bé;
      note right
        El canvi es notifica.
        No cal acceptació prèvia.
      end note
    endif
  endif
  :Retornar estat real sense SQL ni dades de depuració;
endif
stop
@enduml
```

### A114-02 · Desar dades d'aula — ACTUAL, error parcial acreditat al codi

```plantuml
@startuml
title A114-02 ACTUAL | Edició de dades aula
start
:POST idCurs, idAula, aula, dates i observacions;
:Normalitzar data revisió i informe;
if (Es pot preparar UPDATE cursos?) then (Sí)
  :UPDATE cursos WHERE id_Curs = idCurs concatenat amb aula;
else (No)
  :Llançar error 4315;
  stop
endif
if (idAula és zero?) then (Sí)
  :Llançar error 4317 DESPRÉS del primer UPDATE;
  stop
else (No)
  if (Es pot preparar UPDATE aula?) then (Sí)
    :UPDATE aula WHERE ID_AULA i AULA coincideixen;
  else (No)
    :Llançar error 4316;
  endif
endif
note right
  No es veu rollback ni return final.
  Hi ha referència debug a dataBloq
  no definida dins d'aquest mètode.
end note
stop
@enduml
```

**Font:** [`Intranet.php` L18334–18385](../../codi-drive/intranet-actual/Intranet.php#L18334-L18385) i SQL [L1114–1115](../../codi-drive/intranet-actual/Intranet.php#L1114-L1115). Una excepció no acredita automàticament rollback d'un `UPDATE` previ.

### A114-02 · Desar dades d'aula — FINAL

```plantuml
@startuml
title A114-02 FINAL | Validació abans de les dues escriptures
start
:Identificar actor, edició, aula i versió base;
:Autoritzar i validar idAula, aula, dades i existència;
if (idAula zero o edició/aula invàlida?) then (Sí)
  :Denegar sense cap UPDATE;
else (No)
  :Consultar estat anterior i possible impacte de la modificació;
  :Iniciar transacció i actualitzar registres coherents;
  if (Les dues escriptures han completat?) then (Sí)
    :Commit i registrar versió, actor i resultat;
    :Retornar confirmació tipificada;
  else (No)
    :Rollback i registrar error sense confirmar canvi;
  endif
endif
stop
@enduml
```

### A114-04 · Importació CSV — ACTUAL, tres escriptures per fila

```plantuml
@startuml
title A114-04 ACTUAL | Importació CSV i 3 INSERT per edició
start
:Seleccionar CSV, resolució i número de tràmit;
if (Validació del formulari superada?) then (Sí)
  :Analitzar CSV i mostrar files seleccionables;
  if (Hi ha files marcades?) then (Sí)
    :Per cada fila llançar POST a inserirCurs.php;
    :insertCurs executa INSERT curs;
    :insertCurs executa INSERT aula;
    :insertCurs executa INSERT cursos;
    note right
      Insercions successives al PHP
      sense transaction visible;
      cada crida gestiona la seva resposta.
    end note
    :Enviar petició separada a inserirNumTramit.php;
    :Mostrar èxit/error de files i tràmit per callbacks;
  else (No)
    :Mostrar cap fila seleccionada;
  endif
else (No)
  :Mostrar errors de fitxer, resolució o tràmit;
endif
stop
@enduml
```

**Font:** [JS L50–115 i L210–310](../../codi-drive/intranet-actual/js/cursos-afegir-modificar-edicions.js#L210-L310), [`insertCurs()` L18855–18900](../../codi-drive/intranet-actual/Intranet.php#L18855-L18900) i [`inserirNumTramit()` L18907–18923](../../codi-drive/intranet-actual/Intranet.php#L18907-L18923). Una fila fallida pot haver executat INSERT previs; verificar en BD de proves la consistència de cada cas.

### A114-04 · Importació CSV — FINAL

```plantuml
@startuml
title A114-04 FINAL | Importació de fila i tràmit idempotents
start
:Validar actor i fitxer al servidor;
:Analitzar CSV i classificar files noves, repetides i conflictes;
:Mostrar previsualització i files elegibles;
if (Hi ha files autoritzades?) then (Sí)
  :Crear identificador de lot/filera i claus idempotents;
  :Per cada fila elegible validar referències i versió;
  if (Fila ja creada idempotentment?) then (Sí)
    :Recuperar resultat existent sense duplicar INSERT;
  else (No)
    :Escriure curs, aula i cursos coherentment;
    if (Fila completada?) then (Sí)
      :Commit i conservar resultat de fila;
    else (No)
      :Rollback de fila i registrar incidència;
    endif
  endif
  :Esperar i conciliar els resultats de totes les files;
  :Registrar tràmit segons política del lot i resultats reals;
  :Retornar resum per fila i no afirmar èxit total si hi ha errors;
else (No)
  :Mostrar motius de denegació o cap fila vàlida;
endif
stop
@enduml
```

**Criteri de revisió i proves:** [fitxa funcional UC-114, secció 23](../06-fitxes-funcionals/uc-114.md) fixa orígens, resultats, alternatives i P114-01–06. Els diagrames ACTUALS reprodueixen codi observable, inclosos errors; els FINALS són contracte objectiu i no impliquen implementació ni proves executades. Encara cal connectar tots els controladors JS, controls/accions dels modals i captures reals de la pàgina a RM-037.


## 8. Diagrames d'activitat de la interacció UI real per apartat

**Ruta de la interfície d'edició verificada:** [`curs-mostrar-curs.php`](../../codi-drive/intranet-actual/curs-mostrar-curs.php) i [`js/curs-mostrar-curs.js`](../../codi-drive/intranet-actual/js/curs-mostrar-curs.js). Aquesta és la pantalla que implementa els controls `.editar-apartat`, `.save-result`, `.cancelar-apartat`, `.alumnes` i `.urlCurs`. La ruta `cursos-consultar-edicions-curs.php` té JS de càrrega del main, de manera que la correspondència entre pantalles no s'ha de deduir només del títol; l'acció «Info» del llistat d'estats obre `/curs/mostrar-curs/#/...` [JS estats](../../codi-drive/intranet-actual/js/cursos-previ-inici-cursos-estat-cursos.js#L251-L259).

### UI114-01 · Pantalla «Mostrar curs» — ACTUAL, cerca i accions

```plantuml
@startuml
title UI114-01 ACTUAL | Mostrar curs - cerca, seccions i accions
start
:Obrir curs-mostrar-curs.php;
:Carregar main i formulari de cerca;
:Introduir identificador o filtres de curs;
if (Cerca validada al client?) then (Sí)
  :GET consultaCurs / mostrarInfoEdicioCurs;
  if (Resultat no buit i sense cadena error?) then (Sí)
    :Pintar estat, dades edició i dades per aula;
    if (Clic editar/desar dades edició?) then (Sí)
      :Comprovar tePermisEdicio al client;
      :Validar camps i enviar POST desarCanvisDadesEdicio;
      if (Resposta HTML sense cadena error?) then (Sí)
        :Mostrar èxit i recarregar cerca;
      else (No)
        :Mostrar modal error;
      endif
    elseif (Clic editar/desar dades aula?) then (Sí)
      :Comprovar tePermisEdicio al client;
      :Validar dates i enviar POST desarCanvisDadesAulaEdicio;
      if (Resposta HTML sense cadena error?) then (Sí)
        :Mostrar èxit i recarregar cerca;
      else (No)
        :Mostrar modal error;
      endif
    elseif (Clic cancel·lar edició?) then (Sí)
      :Convertir valors ACTUALS dels inputs a text no editable;
      :No enviar petició de desament;
    elseif (Clic ALUMNES?) then (Sí)
      :GET mostrarModalConsultaAlumnes;
      :Obrir modal i possible fitxa d'alumne;
    elseif (Clic INFO web o MOODLE?) then (Sí)
      :Consultar enllaç web o construir URL campus;
      :Obrir destí en nova finestra;
    endif
  else (No)
    :Mostrar modal d'error o curs inexistent;
  endif
else (No)
  :Mostrar errors de cerca;
endif
stop
@enduml
```

**Fonts:** [JS cerca/edició L284–456](../../codi-drive/intranet-actual/js/curs-mostrar-curs.js#L284-L456), [JS aula L550–649](../../codi-drive/intranet-actual/js/curs-mostrar-curs.js#L550-L649), [JS modal/enllaços L677–786](../../codi-drive/intranet-actual/js/curs-mostrar-curs.js#L677-L786). La comprovació `tePermisEdicio` del navegador **no substitueix** l'autorització al servidor per endpoint i objecte. El `.done()` comprova text, no estat de commit. La ruta de modal d'alumnes és consulta, no escriptura de factura.

### UI114-01 · Pantalla «Mostrar curs» — FINAL, estats verificats

```plantuml
@startuml
title UI114-01 FINAL | Mostrar curs amb permisos i retorns fiables
start
:Obrir pantalla i carregar dades de curs segons permís servidor;
if (Curs accessible?) then (Sí)
  :Mostrar estat, versió, edició, aules i discrepàncies;
  if (Acció consulta alumnes o enllaç?) then (Sí)
    :Autoritzar recurs consultat al servidor i retornar dades;
    :Obrir modal / URL segura sense efecte econòmic;
  elseif (Acció editar dades?) then (Sí)
    :Verificar permís i versió base al servidor;
    :Mostrar vista prèvia de camps/impacte;
    if (Cancel·la?) then (Sí)
      :Restaurar valors originals sense desar;
    else (Desa)
      :Validar dades al servidor;
      :Executar escriptures consistents i traça;
      if (Commit verificat?) then (Sí)
        :Mostrar confirmació amb versió efectiva;
        :Notificar canvi de condicions si correspon;
        :Rellegir dades de la BD;
      else (No)
        :Mostrar error/incidència i versió real, sense fals èxit;
      endif
    endif
  endif
else (No)
  :Denegar consulta sense revelar dades;
endif
stop
@enduml
```

### UI114-02 · Botó «Cancel·lar» dades d'edició/aula — ACTUAL i FINAL

```plantuml
@startuml
title UI114-02 ACTUAL | Cancel·lar sense restaurar valors originals
start
:Prémer icona editar a edició/aula;
:Convertir div a input editable;
:Canviar un camp sense desar;
:Prémer cancel·lar;
:cancelarEdicioApartat llegeix valor ACTUAL de cada input;
:Elimina input i crea div no-edit amb valor ACTUAL;
:No executa POST;
note right
  La pantalla pot mostrar com a vigent
  una dada NO DESADA a la BD.
end note
stop
@enduml
```

```plantuml
@startuml
title UI114-02 FINAL | Cancel·lar restaura la dada desada
start
:Prémer editar i conservar versió/valors originals;
:Modificar un o més camps;
:Prémer cancel·lar sense desar;
:Descartar valors temporals;
:Restaurar valor original o rellegir dada del servidor;
:Mostrar dades efectivament persistides;
:No enviar cap UPDATE ni notificació;
stop
@enduml
```

**Font:** [`cancelarEdicioApartat()` JS L340–352](../../codi-drive/intranet-actual/js/curs-mostrar-curs.js#L340-L352), control de cancel·lació d'edició [L535–549](../../codi-drive/intranet-actual/js/curs-mostrar-curs.js#L535-L549) i d'aula [L660–675](../../codi-drive/intranet-actual/js/curs-mostrar-curs.js#L660-L675). **Prova específica:** editar la data, cancel·lar, comparar valor visible amb la lectura real de BD i reobrir la pàgina; els tres han de coincidir sense haver escrit res.

**Límit:** s'han traçat controls UI, JS, wrapper, mètode PHP i SQL per les accions d'aquests apartats en el codi versionat. Falta contrast visual amb captures, desplegament, permisos per recurs, casos d'ús associats a qualsevol altre control no inventariat i execució de proves. [Fitxa funcional d'UI114](../06-fitxes-funcionals/uc-114.md#24-traça-completa-dels-controls-de-la-pantalla-mostrar-curs-js--php).
