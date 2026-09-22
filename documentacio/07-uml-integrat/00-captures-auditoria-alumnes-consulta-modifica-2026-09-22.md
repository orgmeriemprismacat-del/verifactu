# Auditoria visual — Alumnes / Consulta - Modifica

**Pantalla:** `https://intranet.prisma.cat/alumnes/mostrar-alumne/`. **Data del contrast:** 22/09/2026. **Font visual:** set captures facilitades a la conversa; **els originals NO es publiquen en aquest repositori**, perquè contenen informació identificable d'alumnes i dades de facturació. Les captures de pantalla permeten identificar controls i formularis visibles, **no demostren que s'hagi executat una baixa, canvi de curs, pagament o emissió**. **Font de codi:** `main` al tall `e71958b3026549bde09fb4b25f2ec3ba370937ec`. **Abast:** àrea d'alumnes de la intranet principal; no forma part de la pàgina «Mostrar curs» de UC-114 ni de la pantalla d'estats UC-127. **Proves:** no executades; captures i codi revisats documentalment.

## 1. Inventari de les set vistes rebudes — sense reproduir dades privades

| Referència interna | Vista / element visible | Què acredita la captura; què NO acredita |
| --- | --- | --- |
| VIS-AL-01 | Pàgina de cerca i resultat d'alumne | Cerca bàsica per NIF/NIE, email, nom i cognoms; botons «Cerca» i «Cerca avançada». Resultat amb «Dades personals» (icona editar), llistats d'inscripcions pendents i acabades, columna de tipus/any/mes/curs/estat/títol i icones d'accions; «Mostra tots els registres», observacions generals i acció d'afegir observació. No mostra el formulari de cerca avançada o les validacions del desament personal. |
| VIS-AL-02 | Modal «Dades del curs» + «Dades personals» de la inscripció | Dades acadèmiques, curs/hores/dates, tutor, inscripcions i icones de consulta acadèmica/Moodle; dades de la inscripció amb estat, certificat, mailing, data i observacions. Les icones d'editar són visibles; no prova permisos al servidor ni sincronització amb Moodle. |
| VIS-AL-03 | Continuació inferior del mateix modal d'inscripció | «Dades pagament», import comercial, import registrat com a pagat, referència a operació i factura, data, observacions, fraccionament i camp de reclamació; avís visible de recordar les dates de pagament i botó «Tanca». **És una consulta visual**, no evidència de cobrament bancari, factura emesa per aquest clic ni modificació de pagaments. |
| VIS-AL-04 | Modal de visualització de factura | Factura mostrada en modal amb emissor, receptor, dades de document, concepte, import i text fiscal. No es presumeix que pertanyi a la mateixa inscripció que VIS-AL-02/03: les vistes aportades poden correspondre a registres diferents. No inferir pagament, efecte AEAT, correcció fiscal ni descàrrega d'un PDF a partir de la visualització. |
| VIS-AL-05 | «Mostra tots els registres» desplegat | Botó passa a «Oculta tots els registres», taula «Totes les inscripcions» i secció d'acabades amb etiquetes d'estat; icones que poden aparèixer actives o atenuades segons fila. **No** es pot concloure que una icona atenuada garanteixi denegació al servidor. |
| VIS-AL-06 | Modal «Dades inscripció / Dades baixa» | Desglossament econòmic informatiu, camp «Motiu baixa», casella «No enviar correu a l'alumne/a» i botó «Confirma la baixa». **Només captura prèvia a confirmar**, sense efecte provat; la necessitat de motiu es contrasta amb JS i servidor. |
| VIS-AL-07 | Modal de canvi de curs/edició | Dades d'origen (estat/imports/motiu), selectors any/mes/curs/variant, import nou, pagat, pendent, despeses, observacions, motiu, casella d'exclusió de correu i botó «Previsualitza el canvi». **No** inclou la pantalla de previsualització posterior, l'acceptació final ni el resultat al servidor: cap traspàs de fons, canvi fiscal o acadèmic està acreditat per aquesta imatge. |

**Protecció de dades:** les imatges originals contenen NIF/DNI, identitat, dades de contacte i domicili, dades de curs vinculables a persones, referències d'operacions i detalls de factura. **No reproduir-ne valors, ni copiar els originals o miniatures al repositori públic.** Per publicar proves visuals, generar i comprovar versions amb ocultació *opaca i irreversible* de totes aquestes dades, incloses les capçaleres de cerca, els registres de fons, els modals, les notes i els rebuts. No n'hi ha prou amb pixelació lleu, retall d'un sol camp o supressió de metadades. Fins que hi hagi còpies verificades aptes per a publicació, les referències VIS-AL són **índex de captures rebudes en conversa, no enllaços a imatges del repositori**.

## 2. Identificació de la pàgina i els punts d'entrada reals

- [`alumnes-mostrar-alumne.php`](../../codi-drive/intranet-actual/alumnes-mostrar-alumne.php) carrega el JS minificat `alumnes-mostrar-alumne.min.js?ver=1.4` [L47–48](../../codi-drive/intranet-actual/alumnes-mostrar-alumne.php#L47-L48). La correspondència exacta entre el JS no minificat de repositori i l'asset servit s'ha de verificar abans de donar els números de línia següents com a proves del paquet desplegat.
- [`js/alumnes-mostrar-alumne.js`](../../codi-drive/intranet-actual/js/alumnes-mostrar-alumne.js) documenta els handlers llegibles. [L810–900](../../codi-drive/intranet-actual/js/alumnes-mostrar-alumne.js#L810-L900): «Mostra/Oculta tots els registres», consulta inscripció, canvi de curs, baixa, factura i certificats. [L905–995](../../codi-drive/intranet-actual/js/alumnes-mostrar-alumne.js#L905-L995): afegir/amagar observacions. La cerca es canalitza pels endpoints `searchUserBy*`, `mostrarTaulaUsuaris.php` i `mostrarInformacioUsuari.php`.
- [`mostrarModalConsultaInformacio.php`](../../codi-drive/intranet-actual/ajax/alumnes/mostraModalConsultaInformacio.php) llegeix `idInsc` i invoca `Intranet::modalConsultaInformacio_resultatCerca()`; el JS [L1009–1115](../../codi-drive/intranet-actual/js/alumnes-mostrar-alumne.js#L1009-L1115) carrega el modal i permet editar **dades d'inscripció** i, de manera diferent, **dades de pagament**.
- [`mostraModalConsultaFactura.php`](../../codi-drive/intranet-actual/ajax/alumnes/mostraModalConsultaFactura.php) fa consulta per `idInsc`; [JS L2169–2240](../../codi-drive/intranet-actual/js/alumnes-mostrar-alumne.js#L2169-L2240) distingeix error, manca de factura i modal amb factura, i ofereix una acció posterior de descàrrega. **Consulta de factura UC-007 ≠ emissió de factura**.
- [`mostraModalDonarBaixa.php`](../../codi-drive/intranet-actual/ajax/alumnes/mostraModalDonarBaixa.php) mostra el modal; [JS L2035–2135](../../codi-drive/intranet-actual/js/alumnes-mostrar-alumne.js#L2035-L2135) comprova `idInsc` i motiu no buit en client i envia una petició **GET** a `confirmacioBaixa_DonarBaixa.php`, inclosa la casella de no enviar correu. **No donar per provada la baixa mentre només hi ha la captura VIS-AL-06.** Casos propis: UC-027, UC-072 i efectes sobre UC-124.
- [`mostrarModalCanviCurs.php`](../../codi-drive/intranet-actual/ajax/alumnes/mostrarModalCanviCurs.php) obre el canvi per `idInsc`; [JS L1543–1695](../../codi-drive/intranet-actual/js/alumnes-mostrar-alumne.js#L1543-L1695) presenta selectors/inputs i passa a un **modal de confirmació** posterior a la previsualització; només en clicar `#confirmar-canvi` prepara GET a [`realitzarCanviCurs_CanviCurs.php`](../../codi-drive/intranet-actual/ajax/alumnes/realitzarCanviCurs_CanviCurs.php), que accepta imports (`apagar/pagat/pendent/despeses`), motiu i control de correu com a paràmetres. Que el navegador enviï aquests imports **no els fa imports verificats pel servidor**. Casos propis: UC-071 i UC-105 segons la situació real; cap pagament nou és implícit.

## 3. Matriu de traça d'accions, subcasos i proves pendents

| Acció identificada | UC responsable | Contrast ACTUAL i diferència FINAL | Prova necessària |
| --- | --- | --- | --- |
| Cerca bàsica/avançada i llista per estat | UC-042 | Cerca, resultats i control de mostrar/ocultar registres. FINAL: cerca per subjecte autoritzat, dades mínimes, estats de matrícula llegits realment. | AL-VIS-01: cerca amb diversos registres, absència de resultat, filtratge per rol i cerca avançada. |
| Editar dades personals i de la inscripció | UC-042/120/126 | Dues icones/panells diferents; el modal pot incloure mailing i estat. FINAL: traça del camp editat, destinacions reals i consentiment comercial separat; perfil actual ≠ dades fiscals d'una factura emesa. | AL-VIS-02: edició parcial d'inscripció, dues matrícules, mateix correu, DNI després d'emissió. |
| Consultar/editar «Dades pagament» | UC-042 com a entrada, UC-002/062/074/105 segons efecte | La vista mostra imports i factura vinculada; el modal permet edició diferenciada de dades de pagament. FINAL: no equiparar camp llegat `PAGAMENT` amb un cobrament bancari nou, ni editar factura immutable. | AL-VIS-03: discrepància entre camp llegat, assignació i ingrés real; autorització, audit i resultat. |
| Mostrar factura i descarregar-la | UC-007 i UC-049 si s'envia | Modal visual; possible descàrrega posterior. FINAL: autoritzar per receptor/subjecte/representació, distingir PDF antic i fiscal vigent, no generar fiscal per una consulta. | AL-VIS-04: cap factura, factura de grup amb tercer pagador, PDF existent i error de descàrrega. |
| Donar de baixa | UC-027/072/124 | Captura de modal, no de resultat; JS requereix motiu i envia GET. FINAL: petició identificada, decisió acadèmica, notificació, tractament econòmic/fiscal individual **separat**; no executar devolució per obrir modal. | AL-VIS-05: motiu buit, opció no enviar correu, dues peticions i factura/cobrament existents. |
| Canviar curs o edició | UC-026/071/105 | Captura només del formulari anterior a «Previsualitza»; JS té modal posterior `#modalConfirmacioCanvi` i petició GET d'execució separada. FINAL: imports calculats/validats al servidor, plaça/snapshot/inscripció origem-destí, fons realment ingressats i efecte fiscal classificat. | AL-VIS-06: mateix import, superior/inferior, canvis repetits, origen pagat per empresa, reintent i desistiment abans de confirmar. |
| Observacions generals i icones acadèmiques | UC-042 i cas acadèmic/Moodle corresponent | Accions de veure/afegir/amagar observacions i enllaços a consulta acadèmica, certificat/Moodle. FINAL: permisos de visualització i traça sense moviments fiscals pel simple clic. | AL-VIS-07: observació buida, ocultació, modal, consulta d'alumne diferent, enllaç sense autorització. |

## 4. Diagrames d'activitat ACTUAL/FINAL: pàgina i accions mostrades

**Llegenda:** ACTUAL = el que acredita conjuntament la captura i el controlador JS/PHP indicat; FINAL = contracte objectiu proposat a fitxes del SIF. Cap `FINAL` es considera implementat sense proves. El diagrama de pàgina dona visió global; els subdiagrames de baixa i canvi **no** s'han de fondre en UC-042 com si fossin edició personal.

### AL-PAG · Página completa «Consulta - Modifica» — ACTUAL

```plantuml
@startuml
title AL-PAG ACTUAL | Consulta - Modifica alumne
start
:Obrir pantalla i cercar alumne;
if (Cerca retorna registre?) then (Sí)
  :Mostrar dades personals, inscripcions per estat i observacions;
  if (Clic Mostra tots els registres?) then (Sí)
    :Mostrar tots els registres;
    :Botó canvia a Oculta tots els registres;
  endif
  if (Clic editar dades personals?) then (Sí)
    :Habilitar camps del perfil i opcions de desament;
  elseif (Clic informació d'una inscripció?) then (Sí)
    :GET mostraModalConsultaInformacio;
    :Mostrar dades curs, alumne i pagament;
  elseif (Clic factura?) then (Sí)
    :GET mostraModalConsultaFactura;
    :Mostrar PDF/modal si existeix o missatge d'absència/error;
  elseif (Clic donar baixa?) then (Sí)
    :Obrir modal amb motiu i casella no enviar correu;
  elseif (Clic canvi curs?) then (Sí)
    :Obrir modal de destinació i imports; oferir previsualització;
  elseif (Clic observacions?) then (Sí)
    :Afegir/consultar/amagar observació segons botó;
  endif
else (No)
  :Mostrar absència de resultat o error de cerca;
endif
stop
@enduml
```

### AL-PAG · Pàgina completa — FINAL

```plantuml
@startuml
title AL-PAG FINAL | Consulta - Modifica amb separació de subcasos
start
:Autenticar i autoritzar actor, subjecte i inscripcions;
:Consultar dades vigents i historial amb mínim privilegi;
if (Subjecte/inscripció accessibles?) then (Sí)
  :Mostrar dades personals i totes/filtrades les inscripcions;
  if (Consulta pura?) then (Sí)
    :Obrir informació curs, acadèmia, factura o observacions
    només si actor està autoritzat al recurs concret;
  elseif (Canvi de perfil o contacte?) then (Sí)
    :Versionar canvi UC-042/120 i mostrar resultat per destinació;
  elseif (Canvi de pagament o factura?) then (Sí)
    :Derivar a UC econòmic/fiscal amb resultat verificat;
  elseif (Baixa?) then (Sí)
    :Tramitar UC-027/072 per inscripció;
  elseif (Canvi de curs?) then (Sí)
    :Tramitar UC-071 amb previsualització i confirmació;
  endif
  :Rellegir estat persistent i mostrar completat/pendent/error;
else (No)
  :Denegar accés i no exposar dades de tercers;
endif
stop
@enduml
```

### AL-BAIXA · «Confirma la baixa» — ACTUAL vs FINAL

```plantuml
@startuml
title AL-BAIXA ACTUAL | Modal i petició de baixa
start
:Prémer icona baixa d'una inscripció;
:GET mostraModalDonarBaixa amb idInsc;
:Mostrar curs, imports, motiu i opció de no enviar correu;
if (Clic Confirma la baixa?) then (Sí)
  :Llegir idInsc, motiu i casella de correu;
  if (idInsc i motiu no buits?) then (Sí)
    :GET confirmacioBaixa_DonarBaixa.php;
    :Mostrar modal èxit si text resposta no conté error/404;
  else (No)
    :Mostrar avís de validació;
  endif
else (No)
  :Tancar sense executar la baixa;
endif
stop
@enduml
```

```plantuml
@startuml
title AL-BAIXA FINAL | Inscripció, comunicació i impacte econòmic
start
:Identificar actor i inscripció, llegir estat persistent;
:Mostrar motiu, informació econòmica i opció de comunicació;
if (Confirma i motiu vàlid?) then (Sí)
  :Enviar comanda autoritzada/idempotent per inscripció;
  :Registrar baixa acadèmica i estat de propagació;
  :Separar decisió econòmica per import i pagador;
  :Classificar factura existent sense reescriure-la;
  :Encolar comunicació segons opció escollida;
  :Mostrar resultat real: baixa / pagament / fiscal / correu;
else (No)
  :No executar efectes;
endif
stop
@enduml
```

### AL-CANVI · «Previsualitza el canvi» — ACTUAL vs FINAL

```plantuml
@startuml
title AL-CANVI ACTUAL | Formulari i confirmació separats
start
:Prémer icona canvi d'inscripció;
:GET mostrarModalCanviCurs amb idInsc;
:Mostrar origen i selectors any/mes/curs;
:Mostrar a pagar, pagat, pendent, despeses i motiu;
:Seleccionar destí i editar valors;
:Recalcular pendent en JS en canviar imports;
if (Clic Previsualitza el canvi?) then (Sí)
  :Preparar resum al modalConfirmacioCanvi;
  if (Clic confirmar-canvi posterior?) then (Sí)
    :GET realitzarCanviCurs_CanviCurs.php amb destí,
    imports, despeses, motiu i opció de correu;
    :Gestionar resposta HTML;
  else (No)
    :Tancar o tornar al formulari sense executar canvi;
  endif
else (No)
  :No enviar petició d'execució;
endif
note right
  La captura rebuda només mostra
  el formulari abans de previsualitzar.
  No evidencia confirmació ni resultat.
end note
stop
@enduml
```

```plantuml
@startuml
title AL-CANVI FINAL | Canvi acadèmic i fons reals
start
:Autoritzar actor, inscripció origen i destinació;
:Consultar disponibilitat, pagador, imports i factura;
:Calcular al servidor fons reals, diferència i efecte fiscal;
:Mostrar previsualització verificable;
if (Operador confirma canvi?) then (Sí)
  :Validar versió i clau idempotent;
  :Registrar relació inscripció origen i destinació;
  :Reassignar només fons realment cobrats si correspon;
  :Registrar deute pendent o decisió de retorn/saldo;
  :Classificar eventual rectificació sense mutar original;
  :Aplicar canvi acadèmic i reconciliar accés;
  :Encolar avís segons opció i mostrar estat de cada etapa;
else (No)
  :No executar canvi, càrrec ni rectificació;
endif
stop
@enduml
```

## 5. Captures i fases que FALTEN per donar per completa aquesta pàgina

Els set originals permeten reconstruir l'inventari visual principal però **no** mostren: cerca avançada oberta, edició de dades personals amb validació/guardat, accions d'editar inscripció i pagament en ús, formulari complet d'observacions, modal acadèmic accionat, previsualització posterior al botó de canvi, confirmació final, resultat/error de la baixa i del canvi, modal de factura sense document i gestió de permisos per cada acció. Cal contrastar el JS **minificat carregat** amb el codi llegible, mètodes PHP llargs de l'alumnat, autoritzacions de cada endpoint, BD i proves d'entorn. **No inferir** que l'absència de captura equival a absència de funcionalitat.

**Estat del lot visual:** inventari de set vistes i diagrames globals + dos subfluxos integrats documentalment; **NO** auditoria exhaustiva de totes les accions ni implementació. Les captures originals queden fora del repositori públic; no inventar enllaços d'imatges.
