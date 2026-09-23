# UC-42 · Consultar i modificar la fitxa operativa d'un alumne

**Objectiu del catàleg:** fitxa operativa de l'alumne; els canvis amb impacte fiscal deriven a un flux específic. **Estat [DISSENY/PARCIAL].** No equiparar el perfil acadèmic, el participant de la factura i el receptor o pagador.

## Evidència contrastada

`LegacyCourseSnapshotRepository::loadByIdpag()` recupera camps de `inscripcions` (`ID`, `NOM`, `COGNOMS`, `DNI`, `CORREU`, adreça, `ANY/MES/CURS`, `FACTURA_RELACIONADA`, `A_PAGAR`, `PAGAMENT`, etc.) per construir **un snapshot d'emissió**, no una API completa de consulta/edició d'alumnes. `LegacySyncRepository::syncInscripcioSummary()` només escriu `FACTURA_RELACIONADA` amb `COALESCE` i concatena dades fiscals a `OBSERVACIONS`; **no modifica el perfil, comprova identitat ni actualitza Moodle**. `personal_data_change_request` està definida a SQL amb `SUBJECT_KEY`, `CHANGESET_JSON`, revisió i resultats de propagació; no s'ha acreditat un servei PHP complet de gestió de dades personals al SIF.

## Fitxa funcional

| Operació | Regla específica |
| --- | --- |
| Consultar | Autoritzar operador/alumne per **subjecte i `ID_INSC`**; veure inscripcions i estat acadèmic corresponents. Els documents de grup amb `VISIBLE_ALUMNE=0` i les dades de pagador empresa no són visibles pel sol fet de pertànyer al grup. |
| Modificar contacte | Previsualitzar valor original/nou de nom de contacte, email, telèfon o adreça segons origen verificat; UC-126 resol identitats amb emails compartits i UC-120 governa petició/versionat/propagació. |
| Modificar identificació fiscal | Distingir dada actual de l'alumne i **receptor d'una factura ja emesa**. Si s'ha emès factura, preservar `BILLING_*` i classificar error fiscal per UC-74/93; no editar directament la factura. |
| Modificar curs/edició/baixa | No és un canvi de perfil: remetre a UC-71/72/124/129, amb plaça, accés, factura i titularitat econòmica per inscripció. |
| Diners i consentiment | Editar la fitxa no crea `CHARGE/REFUND`, no traspassa saldo, no subscriu l'alumne a comunicacions comercials (UC-125) ni prova que `A_PAGAR` sigui l'import bancari real. |

### Flux proposat

1. Resoldre subjecte canònic i `ID_INSC` abans de mostrar dades; distingir rol de gestió i rol d'alumne sense exposar factures d'empresa.
2. Comparar perfil actual de Prisma, propostes i estat d'operacions obertes; registrar camp, font, motiu, actor i versió. La BD llegada pot tenir una adreça actual distinta de la que consta a la factura emesa.
3. Per contacte simple, aprovar UC-120 i propagar amb resultat per destinació; si hi ha conflicte d'identitat, UC-126 exigeix resolució abans de fusionar dades.
4. Si el camp afecta el receptor fiscal de document existent, **separar** correcció fiscal de perfil acadèmic; una factura original i el seu hash no es reescriuen. Si afecta Moodle, remetre a UC-129 i verificar matrícula real.
5. Després de cada canvi, rellegir destinacions i mostrar estat parcial/pendent; un `UPDATE inscripcions` o nota a `OBSERVACIONS` no certifica sincronització global.

**Proves:** dues matrícules i correu compartit, factura pagada per empresa, alumne sense dret a PDF grup, canvi de DNI després de factura, Moodle inaccessible, canvi acadèmic sense efecte econòmic, modificació concurrent del contacte.

### Pantalla real «Consulta - Modifica alumne» i accions que no són edicions personals

**Circuit recuperat.** `/alumnes/mostrar-alumne/` correspon a `alumnes-mostrar-alumne.php` i `Intranet::__mostrarPage_Alumnes_MostrarAlumne()`. La cerca passa per `buscarUsuaris()`/`searUserByParam()`, la llista per `mostrarTaulaUsuaris_Alumnes()`/`mostrarTaulaUsuaris2_Alumnes()` i la fitxa per `mostrarInformacioUsuari_Alumnes()`. `__mostrarDadesPersonals_resultatCerca()` i `guardarDadesPersonals_resultatCerca()` tracten dades personals; la documentació funcional antiga precisa que **aquestes edicions operatives només afecten inscripcions pendents de començar**. No suposar que una modificació del correu o DNI ha actualitzat totes les edicions, la BD fiscal o Moodle.

**Una fitxa amb subfluxos de naturalesa diferent.** La pantalla agrupa cursos pendents/actius/acabats/congelats, observacions i icones per veure la informació, canviar de curs, donar de baixa, consultar factura o certificat. Una icona atenuada al navegador **no és un bloqueig d'autorització al servidor**. El modal `guardarDadesPagament_modalsresultatCerca()` pot editar directament `A_PAGAR`, `PAGAMENT`, `DATA PAG`, `IDPAG`, `FRACCIO` i `FACTURA_RELACIONADA`: això **no és una edició personal** i s'ha de derivar a UC-62/02/73/74/105 segons el fet real. El canvi de curs i la baixa s'han de tramitar per UC-71/72 amb efectes fiscal/econòmic/acadèmic separats.

**Dada personal actual vs factura emesa.** Si s'edita `NOM/COGNOMS/DNI/ADRECA` de l'alumne que és **receptor fiscal** d'una factura ja emesa, presentar avís d'històric i conservar `factura.BILLING_*` i el PDF original. Si l'empresa és receptora d'una factura de grup, editar el DNI o correu d'un participant **no** el converteix en receptor ni li obre el PDF fiscal complet. La correcció d'un error de receptor/concepte fiscal és una decisió de UC-74/05, no `guardarDadesPersonals_resultatCerca()`. Si el canvi afecta accés acadèmic o Moodle, registrar-ne propagació per destinació, no donar-la per feta amb l'UPDATE de les inscripcions futures.

### Proves de fitxa multicanal (no executades)

| ID | Escenari | Resultat exigible |
| --- | --- | --- |
| AL-42-01 | Canviar correu amb una inscripció acabada i una de pendent | Mostrar abast real del canvi llegat i destins pendents, no assumir propagació universal. |
| AL-42-02 | Alumne de grup vol «Veure factura» d'empresa | Estat mínim autoritzat, no PDF complet per compartir inscripció/IDPAG. |
| AL-42-03 | Canviar DNI després de factura individual emesa | Perfil actualitzat segons procediment, factura original intacta i avís/expedient si hi ha error fiscal. |
| AL-42-04 | Modal de pagament modifica import sense moviment bancari | Derivar a ajust justificat; no crear CHARGE ni editar factura per l'UPDATE llegat. |
| AL-42-05 | Botó d'edició ocultat al navegador però endpoint invocat directament | Permisos de consulta i mutació verificats al servidor. |

## UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Gestió autoritzada" as G
actor "Alumne" as A
rectangle "Prisma/SIF · fitxa alumne" {
 usecase "UC-42\nConsultar/modificar alumne" as Main
 usecase "Validar subjecte, rol i ID_INSC" as Auth
 usecase "UC-120\nVersionar/propagar contacte" as Profile
 usecase "UC-74\nClassificar error fiscal existent" as Fiscal
 usecase "UC-129\nConciliar canvi d'accés Moodle" as Moodle
}
G --> Main
A --> Main
Main ..> Auth : <<include>>
Profile ..> Main : <<extend>> (canvi de contacte)
Fiscal ..> Main : <<extend>> (document fiscal afectat)
Moodle ..> Main : <<extend>> (matrícula afectada)
@enduml
```

## UML de classes

```mermaid
classDiagram
class StudentProfileService {
 <<DISSENY: no acreditat>>
 +getAuthorized(actor,idInsc) profile
 +proposeChange(actor,idInsc,changes) request
}
class LegacyCourseSnapshotRepository {
 <<PHP existent: lector per emissió>>
 +loadByIdpag(legacyDb,idpag,currentPaymentAmount) array
}
class PersonalDataChangeRepository {
 <<DISSENY: personal_data_change_request SQL>>
 +append(db,request) result
 +recordDestination(db,requestId,destination,result) result
}
class CanonicalIdentityResolutionService {
 <<DISSENY: UC-126>>
 +resolve(requestId,decision) mapping
}
StudentProfileService --> PersonalDataChangeRepository : canvis actuals
StudentProfileService --> CanonicalIdentityResolutionService : subjecte
StudentProfileService ..> LegacyCourseSnapshotRepository : font llegada, no autorització
```

## UML de seqüència — email actual amb factura antiga

```mermaid
sequenceDiagram
actor G as Gestió
participant S as StudentProfileService [DISSENY]
participant L as inscripcions [llegat]
participant P as personal_data_change_request [SQL]
participant F as factura [SIF, immutable]
participant M as Moodle [integració pendent]
G->>S: Modificar email d'ID_INSC
S->>L: Consultar subjecte/inscripcions
S->>F: Identificar receptor fiscal i docs previs
S-->>G: Abans/després i sistemes afectats
G->>S: Aprovar canvi de contacte
S->>P: Desar petició, causa i destins [writer pendent]
S->>L: Propagar email actual [adaptador pendent]
opt Existeix usuari Moodle a sincronitzar
 S->>M: Canviar contacte autoritzat i verificar
end
S-->>G: Estat per destí, BILLING_EMAIL històric intacte
Note over S,F: Canviar email no valida identitat fiscal ni autoritza veure factura de grup.
```

## Traçabilitat

[UC-42 original](../06-fitxes-funcionals/uc-042.md) · [UC-120 dades personals](uc-120-canvi-dades-personals-propagacio.md) · [UC-126 identitat](uc-126-identitat-contacte-conflicte-sistemes.md) · [UC-129 Moodle](uc-129-reconciliar-prisma-moodle-matricules.md) · [UC-74 correcció](uc-074-classificar-correccio-fiscal.md) · [LegacyCourseSnapshotRepository](../../sif/src/Repository/LegacyCourseSnapshotRepository.php) · [LegacySyncRepository](../../sif/src/Repository/LegacySyncRepository.php) · [Migració personal_data_change_request](../../sif/database/migrations/2026_09_16_000005_add_operation_lifecycle_tables.sql).

## 8. Contrast visual i traça d'accions de la fitxa alumne

**Set captures de la pantalla real** `/alumnes/mostrar-alumne/` rebudes el 22/09/2026, indexades **sense publicar els originals amb dades personals**: [auditoria visual, matriu d'accions i 6 diagrames d'activitat actual/final](00-captures-auditoria-alumnes-consulta-modifica-2026-09-22.md).

La captura de la pàgina revela cerca bàsica/avançada, dades personals editables, inscripcions pendents/acabades, «Mostra tots els registres», observacions generals i icones per fila (consulta, canvi de curs, baixa, factura i certificat). Dues captures del modal «Dades del curs» mostren dades acadèmiques, personals de la **inscripció** i pagament separades; una altra mostra factura; i dues més mostren els formularis de baixa i de canvi de curs **abans d'executar-los**. No assumir que totes les imatges pertanyen a la mateixa inscripció, ni que un camp `PAGAMENT` a la UI constitueix un cobrament verificat.

**Traça del codi existent:** [`alumnes-mostrar-alumne.php` L47–48](../../codi-drive/intranet-actual/alumnes-mostrar-alumne.php#L47-L48) carrega el JS **minificat**; [JS llegible L821–900](../../codi-drive/intranet-actual/js/alumnes-mostrar-alumne.js#L821-L900) documenta el botó de tots els registres i els modals; [L1009–1115](../../codi-drive/intranet-actual/js/alumnes-mostrar-alumne.js#L1009-L1115) separa edició de dades d'inscripció de dades de pagament. Comparar el minificat servit i el JS llegible abans de donar per demostrada la coincidència de cada handler al desplegament. «Mostrar factura» és consulta UC-007, **no emissió fiscal**.

**Límits dels UC:** UC-042 comprèn consulta/edició de la fitxa operativa i observacions; les accions de baixes corresponen a UC-027/072; el canvi de curs/edició a UC-026/071; moviment de fons a UC-105 i factura a UC-007/074 segons el fet real. La casella de «No enviar correu» forma part dels formularis visibles, no acredita que s'hagi enviat o suprimit un correu. El modal «Previsualitza el canvi» és anterior a la confirmació i a l'execució: **cap canvi efectiu es pot donar per acreditat només amb aquesta captura**.

**Estat de completitud:** evidència visual indexada i diagrames per pantalla + subfluxos baixa/canvi; falta veure variants de cerca avançada, formularis d'edició i de confirmació, comprovar el desplegament, autorització per objecte i executar proves. No publicar les captures originals al GitHub públic, ni substituir dades personals dels originals per dades aparentment reals a la documentació.

## 9. Diagrames d'activitat de la resta d'accions d'«Alumnes / Consulta - Modifica»

**Origen contrastat:** [inventari de pantalla i codi, apartats 7–8](00-captures-auditoria-alumnes-consulta-modifica-2026-09-22.md#7-cerca-avançada-dades-personals-observacions-i-certificats--contrast-sense-captures-noves), [fitxa funcional UC-042, apartat 24](../06-fitxes-funcionals/uc-042.md#24-fitxa-funcional-per-acció-cerca-avançada-perfil-observacions-i-certificats). **Aquests diagrames ACTUALS deriven dels JS i PHP de `main`, no de captures que encara no tenim ni de tests executats.** Els diagrames FINALS són el contracte proposat, incloses les distincions acadèmiques/fiscals i de privacitat. No confondre consulta de certificat amb elegibilitat acadèmica aprovada: la seva reconciliació és UC-124; no confondre desament de contacte amb reemissió d'una factura històrica.

### AL-CERCA — ACTUAL

```plantuml
@startuml
title AL-CERCA ACTUAL | Cerca bàsica, avançada i múltiples resultats
start
:Entrar a Consulta - Modifica;
:Omplir DNI, email, nom/cognoms o obrir cerca avançada;
if (Cerca avançada oculta?) then (Sí)
  :Ignorar filtres avançats a la petició;
else (No)
  :Llegir filtres avançats i normalitzar "qualsevol";
endif
if (Tots els camps aplicables són buits?) then (Sí)
  :Mostrar avís «Omple un camp»;
else (No)
  :Per cada criteri llançar GET searchUserByCamp;
  :Recollir identificadors de cada resposta;
  :Intersectar llistes al navegador;
  if (Cap coincidència?) then (Sí)
    :Mostrar avís sense resultats;
  elseif (Més de 2000?) then (Sí)
    :Mostrar avís per acotar la cerca;
  elseif (Una sola coincidència?) then (Sí)
    :GET mostrarInformacioUsuari per subjecte;
    :Mostrar fitxa i inscripcions;
  else (Diverses)
    :GET mostrarTaulaUsuaris;
    :Mostrar llista ordenable i selecció de subjecte;
  endif
endif
stop
@enduml
```

### AL-CERCA — FINAL

```plantuml
@startuml
title AL-CERCA FINAL | Filtrar subjectes autoritzats
start
:Autenticar actor i carregar formulari;
:Seleccionar cerca bàsica o avançada;
:Normalitzar criteris i eliminar filtres ocults;
if (Cerca sense criteris vàlids?) then (Sí)
  :Mostrar error sense executar consulta;
else (No)
  :Enviar consulta autoritzada amb identificador de cerca;
  :Descartar resultats tardans de cerques anteriors;
  :Aplicar filtres i límits al servidor;
  if (Sense resultats?) then (Sí)
    :Mostrar cap coincidència;
  elseif (Excés de resultats?) then (Sí)
    :Demanar filtres addicionals;
  elseif (Una coincidència?) then (Sí)
    :Obrir fitxa si actor té dret a veure-la;
  else (Diverses)
    :Mostrar llista mínima i ordenable amb autorització;
    :Obrir només el subjecte autoritzat seleccionat;
  endif
endif
stop
@enduml
```

### AL-PERSONAL — ACTUAL

```plantuml
@startuml
title AL-PERSONAL ACTUAL | Desar o cancel·lar dades personals
start
:Obrir resultat alumne i prémer editar;
if (tePermisEdicio al navegador?) then (Sí)
  :Convertir camps visibles en inputs;
  :Modificar nom, contacte o altres dades;
  if (Clic cancel·lar?) then (Sí)
    :Convertir inputs a text amb VALOR ACTUAL;
    :No enviar UPDATE;
    note right
      Pot mostrar un canvi que NO
      està desat en base de dades.
    end note
  elseif (Clic desar?) then (Sí)
    :Validar camps obligatoris i telèfon al JS;
    if (Validació client correcta?) then (Sí)
      :GET guardarDadesPersonals amb dades a URL;
      :PHP UPDATE inscripcions per idInsc;
      :Mostrar guardat si text no conté Error/error;
    else (No)
      :Mostrar camps erronis;
    endif
  endif
else (No)
  :Mostrar avís de manca de permisos;
endif
stop
@enduml
```

### AL-PERSONAL — FINAL

```plantuml
@startuml
title AL-PERSONAL FINAL | Perfil amb dades originals i traça
start
:Carregar camps originals i versió de la inscripció;
:Autoritzar actor i àmbit de l'edició al servidor;
if (Actor autoritzat?) then (Sí)
  :Editar camps en memòria sense escriptura;
  if (Cancel·la?) then (Sí)
    :Restaurar valors persistits originals;
  elseif (Desa?) then (Sí)
    :POST de canvis amb versió base i camps permesos;
    :Validar dada i destinacions al servidor;
    if (Conflicte o dades invàlides?) then (Sí)
      :Mostrar errors i estat real, sense escriptura;
    else (No)
      :Registrar abans/després i aplicar canvi;
      :Preservar receptor i factura històrics;
      :Confirmar només el desament verificat;
      :Rellegir camps persistits;
    endif
  endif
else (No)
  :Denegar sense exposar ni modificar dades;
endif
stop
@enduml
```

### AL-OBS — ACTUAL

```plantuml
@startuml
title AL-OBS ACTUAL | Afegir o ocultar observació general
start
:Mostrar observacions generals de l'alumne;
if (Clic afegir observació?) then (Sí)
  :Obrir modal i introduir text;
  if (Text no buit?) then (Sí)
    :GET afegirObservacio amb text i DNI;
    :INSERT aobservacions amb actor de sessió;
    :GET mostrarObservacions per refrescar;
    :Mostrar «guardat» si resposta sense Error/error;
  else (No)
    :Mostrar avís camp buit;
  endif
elseif (Clic amagar observació?) then (Sí)
  if (tePermisEdicio al navegador?) then (Sí)
    :GET amagarObservacio amb ID de la nota;
    :UPDATE aobservacions VISIBLE = 0;
    :Eliminar fila de la vista si text sense Error/error;
  else (No)
    :Mostrar avís sense permís;
  endif
endif
stop
@enduml
```

### AL-OBS — FINAL

```plantuml
@startuml
title AL-OBS FINAL | Registre i ocultació traçables
start
:Identificar actor, alumne i registre existent;
if (Afegir observació?) then (Sí)
  :Validar text i permís al servidor;
  :POST amb clau d'operació i actor;
  :INSERT i confirmar ID, data i estat real;
  :Rellegir llista de notes visibles;
elseif (Ocultar observació?) then (Sí)
  :Validar permisos i pertinença de la nota a l'alumne;
  :POST per ocultar amb motiu/traça si escau;
  :Canviar visibilitat sense esborrar historial;
  :Confirmar ID i estat real de la nota;
else (No)
  :No alterar observacions;
endif
:Mostrar resultats només al subjecte autoritzat;
stop
@enduml
```

### AL-CERT — ACTUAL

```plantuml
@startuml
title AL-CERT ACTUAL | Consulta, previsualització i PDF
start
:Prémer icona certificat o «cursant» per inscripció;
:GET mostraModalConsultaCertificat per idInsc i tipus;
:PHP genera vista inicial del certificat;
if (Tipus INSCRIT?) then (Sí)
  :Mostrar botó «Cursant el curs»;
else (No)
  :Mostrar variants Digital, Paper i Sobre;
endif
if (Selecciona variant?) then (Sí)
  :GET mostrarCertificat download=false;
  :Substituir HTML de la previsualització;
endif
if (Clic descarregar i tePermisEdicio al JS?) then (Sí)
  :GET mostrarCertificat download=true;
  :Generar arxiu PDF temporal amb nom basat en DNI i curs;
  :Construir enllaç de descàrrega;
  :Sol·licitar eliminarArxiu després del clic;
  :Mostrar resultat segons callbacks;
else (No)
  :Tancar o mantenir vista sense descàrrega;
endif
stop
@enduml
```

### AL-CERT — FINAL

```plantuml
@startuml
title AL-CERT FINAL | Certificat autoritzat i fitxer privat
start
:Identificar actor, inscripció i modalitat de certificat;
:Verificar dret acadèmic, tipus i autorització de lectura;
if (Consulta admissible?) then (Sí)
  :Mostrar només variants legítimes;
  :Previsualitzar document per canal autenticat;
  if (Demana descarregar?) then (Sí)
    :Validar autorització novament al servidor;
    :Generar fitxer amb identificador opac en ubicació privada;
    :Lliurar per endpoint segur sense exposar DNI a ruta pública;
    :Registrar accés si correspon i destruir temporal amb seguretat;
  endif
else (No)
  :Mostrar estat no disponible sense dades d'altri;
endif
stop
@enduml
```

**Tancament:** les vuit representacions cobreixen cerca, canvi de dades personals, observacions i certificat. Queden proves de navegador i autorització per objecte, captura de variants i confirmació real dels resultats. La [incidència de fitxers de certificat generats al repositori](00-captures-auditoria-alumnes-consulta-modifica-2026-09-22.md#8-incidència-de-protecció-de-dades-detectada-al-repositori-sense-reproduir-cap-document) està separada del flux fiscal del SIF. No copiar ni publicar arxius amb dades personals a la documentació.

## 10. Edició d'inscripció i notificació fraccionament: activitats separades

**Fonts ACTUALS contrastades:** [fitxa funcional UC-042, apartat 25](../06-fitxes-funcionals/uc-042.md#25-edició-de-la-inscripció-i-doble-acció-de-pagament-desar--desar-i-enviar), [auditoria de pantalla, apartat 9](00-captures-auditoria-alumnes-consulta-modifica-2026-09-22.md#9-edició-del-modal-dinscripció-i-notificació-de-pagament--contrast-de-codi), JS de `alumnes-mostrar-alumne.js` i mètodes PHP de `Intranet.php`. Les imatges facilitades mostren dades en consulta, no desaments o lliuraments executats. **No confondre** una edició directa de `INSC CURS` amb una baixa/alta coordinada en Moodle, ni `INSC_MAILING` amb evidència d'acceptació de comunicacions comercials, ni `PAGAMENT` llegat amb ingrés bancari real.

### AL-INSC-EDIT — ACTUAL

```plantuml
@startuml
title AL-INSC-EDIT ACTUAL | Desar dades d'inscripció al registre llegat
start
:Obrir modal informació d'una inscripció;
:Prémer editar dades inscripció;
:Modificar contacte, estat matrícula, mailing,
certificat, baixa o observacions;
if (Clic desar i validació JS correcta?) then (Sí)
  :GET guardarDadesPersonals_ConsultaInformacio
  amb tots els camps i idinsc;
  :Convertir dates i fer UPDATE inscripcions WHERE ID;
  if (Resposta textual sense «Error/error»?) then (Sí)
    :Mostrar avís de guardat;
  else (No)
    :Mostrar error;
  endif
  :Convertir inputs a text segons valor actual
  després de l'animació del callback;
else (No)
  :Mostrar validació o mantenir edició;
endif
note right
  L'UPDATE directe de INSC CURS,
  INSC_MAILING i CERTIFICAT
  no prova efectes complets en
  Moodle, consentiment o SIF.
end note
stop
@enduml
```

### AL-INSC-EDIT — FINAL

```plantuml
@startuml
title AL-INSC-EDIT FINAL | Edició per domini i estat reconciliat
start
:Autoritzar actor, inscripció i camps permesos;
:Consultar valors originals i versió;
:Previsualitzar canvis separats per domini;
if (Només contacte/administratiu?) then (Sí)
  :Validar i desar camps, traça i versió;
elseif (Canvia estat de matrícula o baixa?) then (Sí)
  :Derivar a UC de baixa/canvi/accés amb Moodle;
elseif (Canvia certificat o generat?) then (Sí)
  :Verificar dret acadèmic i historial UC-124;
elseif (Canvia comunicació comercial?) then (Sí)
  :Aplicar UC-125 amb evidència i revocació pròpies;
elseif (Canvia receptor o dada fiscal històrica?) then (Sí)
  :Conservar document immutable i derivar a UC fiscal;
endif
:Retornar resultats i pendents per sistema;
:Rellegir dades efectivament persistides;
stop
@enduml
```

### AL-PAG-SAVE/SEND — ACTUAL

```plantuml
@startuml
title AL-PAG-SAVE/SEND ACTUAL | Desar resum vs desar i enviar
start
:Editar Dades pagament al modal de la inscripció;
:Introduir imports, dates, IDPAG, observacions i reclamació;
if (Clic Desar?) then (Sí)
  :GET guardarDadesPagament_ConsultaInformacio;
  :UPDATE resum llegat inscripcions;
elseif (Clic Desar i enviar?) then (Sí)
  :GET guardarEnviarDadesPagament_ConsultaInformacio;
  :UPDATE resum llegat inscripcions;
  :Consultar dades curs/inscripció;
  :Calcular pendent amb arguments de la petició;
  :Preparar correu alumne i còpia interna;
endif
if (Resposta textual sense error?) then (Sí)
  :Mostrar text de guardat;
else (No)
  :Mostrar error;
endif
:Callback repinta els valors editats en mode lectura;
note right
  No se separen resultat SQL
  i resultat del correu per destinatari.
  Pagament editat no prova CHARGE.
end note
stop
@enduml
```

### AL-PAG-SAVE/SEND — FINAL

```plantuml
@startuml
title AL-PAG-SAVE/SEND FINAL | Persistència i notificació independents
start
:Autoritzar actor i inscripció;
:Consultar cobrament real, pagador, factura i versió;
:Validar correcció econòmica vs nova transacció real;
if (Correcció admissible?) then (Sí)
  :Desar canvi justificat i versió;
  if (Operador demana enviar avís?) then (Sí)
    :Encolar comunicació idempotent després del commit;
    :Separar resultat de còpia interna i de destinatari;
    if (Algun avís pendent o fallit?) then (Sí)
      :Mostrar desament complet i enviament pendent;
      :Reintentar només destinatari pendent;
    else (No)
      :Mostrar estats d'enviament verificats;
    endif
  else (No)
    :No crear cap avís de fraccionament;
  endif
  :Rellegir estat econòmic reconciliat;
else (No)
  :Denegar canvis sense mutar saldo ni documents;
endif
stop
@enduml
```

### AL-PAG-UI — ACTUAL / FINAL

```plantuml
@startuml
title AL-PAG-UI ACTUAL | Error textual però valors repintats
start
:AJAX de desament retorna HTTP correcte;
if (Text conté Error/error?) then (Sí)
  :Mostrar avís d'error;
else (No)
  :Mostrar avís d'èxit;
endif
:Esperar animació;
:Convertir inputs editats a camps no editables;
:Retirar Desar i Cancel·lar;
stop
@enduml
```

```plantuml
@startuml
title AL-PAG-UI FINAL | Error no confirma dades no desades
start
:Rebre resposta estructurada per canvi i avís;
if (Commit de dades verificat?) then (Sí)
  :Rellegir i mostrar resum persistent;
  :Mostrar avís completat o pendent separadament;
else (No)
  :Mantenir edició i descartar falsa confirmació;
  :Mostrar error i valors originals disponibles;
endif
stop
@enduml
```

**Proves pendents T-AL-18–22:** inscripció i pagament editats amb cancel·lació; error SQL; canvis de matrícula i mailing; «Desar» sense avisar; «Desar i enviar» amb enviament parcial i reintent idempotent, separant sempre resultat del desament i de la comunicació. **Estat:** contrast de codi versionat sense proves ni desplegament verificats.
