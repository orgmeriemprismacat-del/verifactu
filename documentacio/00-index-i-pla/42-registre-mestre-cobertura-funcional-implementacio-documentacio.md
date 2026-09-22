# Registre mestre de treball — cobertura funcional, implementació i documentació del SIF PrisMa

**Data de tall:** 22/09/2026 · **Base tècnica:** main, commit e71958b3026549bde09fb4b25f2ec3ba370937ec.  
**Tipus:** document viu de treball i auditoria de buits, **no** acta de conformitat, test executat, certificació fiscal ni autorització de producció.  
**Estat inicial:** OBERT — catàleg de 142 UC existent, però cobertura exhaustiva de les funcionalitats actuals no acreditada.  
**Àmbit:** web actual, intranet actual, intranet d'alumnes, codi candidat de pay.prisma.cat, processos asíncrons i automàtics, nucli SIF, model de dades i documentació.

> **Objectiu operatiu:** per a CADA acció real executable que afecti una inscripció, un cobrament, una factura, un document fiscal, una autorització, un estat acadèmic derivat o una integració relacionada, documentar el comportament actual; assignar UC/variant; definir canvi necessari; vincular codi actual, codi objectiu, dades, actor/permisos i prova reproduïble. «Existeix la fitxa» no equival a «la funcionalitat està coberta». «Existeix la taula/classe» no equival a «està implementat». «Existeix el test» no equival a «s'ha executat».

## 0. Com treballar amb aquest registre

**Codis d'estat documental:** NO INVENTARIAT; MAPAT PROVISIONAL; CONTRASTAT AMB CODI; FITXA AMPLIADA; VALIDAT FUNCIONALMENT.  
**Codis d'estat tècnic independents:** DISSENY; SQL DEFINIT; SQL APLICAT I COMPROVAT; PHP PARCIAL; PHP IMPLEMENTAT; CANAL INTEGRAT; TEST EXECUTAT; PREPRODUCCIÓ ACREDITADA; PRODUCCIÓ ACREDITADA.  
**Estat de cada fila:** OBERT; EN CURS; BLOQUEJAT; TANCAT AMB EVIDÈNCIA; NO APLICA JUSTIFICAT. La prioritat P0 és bloquejant per a l'entrada en producció del flux afectat; P1 és necessària per completar cobertura/fiabilitat; P2 és millora o decisió d'abast que cal resoldre.

No substituir automàticament les 142 fitxes: modificar cada fitxa quan existeixi evidència específica; obrir un UC nou només si l'acció té actor, disparador, resultat i frontera propis que cap UC/variant existent no representa. Les suboperacions internes compartides poden ser serveis, criteris d'acceptació o variants, no necessàriament UC nous.

**Fitxa mínima per acció** (copiar i emplenar per a cada ruta/botó/job):
- ID d'acció i actor autoritzat; canal, pantalla/botó, ruta HTTP/CLI/cron i estat **realment actiu** del desplegament (verificat / desconegut).
- Comportament ACTUAL: entrada, validacions, regles, ramificacions, errors, efectes externs i resultat visible; fitxer, classe i mètode reals; altres escriptors del mateix estat.
- Dades actuals: taules, claus, camps llegits/escrits, BD d'origen i exemple de dada **anonimitzat**. Separar import de negoci, import bancari, import fiscal i import atribuït a inscrit.
- Cas canònic actual + variants; què hi falta de funcionalitat i quins requisits/dades procedeixen del xat original (amb referència interna, sense reproduir secrets).
- Comportament FINAL pas a pas, precondicions, drets d'accés i resultat fiscal / monetari / comercial / acadèmic / documental / notificació **per separat**.
- Canvi de pantalla/adaptador; mètodes/classes PHP reals o marcats DISSENY; taules/relacions i migració; compatibilitat/migració del llegat i ordre de desplegament.
- Proves amb IDs, valors d'entrada, resposta/estat/efecte esperat, camins alternatius, reintent, concurrència i fallada parcial; registre de l'execució real (commit, versió de BD, entorn, data, resultat i evidència).
- Estat **documental**, **de codi**, **de BD**, **d'integració**, **de prova** i **de desplegament**, responsable, bloquejos i decisió pendent. No marcar «tancat» per una captura sense comprovar efectes persistents.

**Ritme de revisió:** una fila passa a TANCAT AMB EVIDÈNCIA quan existeixen origen actual verificat, fitxa/variant completada, implementació de la versió correcta, prova executada i traça dels efectes. Un cas que no requereix factura ha de demostrar explícitament que NO crea factura/cobrament indeguts.

## 1. Fonts, versió i confidencialitat

- Codi actual i candidat: [codi-drive](../../codi-drive/README.md), amb [web-actual](../../codi-drive/web-actual/), [intranet-actual](../../codi-drive/intranet-actual/), [intranet-alumne-actual](../../codi-drive/intranet-alumne-actual/), [old-intranet](../../codi-drive/old-intranet/), [pay-prisma-cat-canvis-verifactu](../../codi-drive/pay-prisma-cat-canvis-verifactu/) i [intranet-nova-canvis-verifactu](../../codi-drive/intranet-nova-canvis-verifactu/). Una còpia al repo **no prova** que aquella versió estigui activa al servidor.
- Catàleg [33-casos-us-sif](../04-estat-final/33-casos-us-sif.md); [142 fitxes funcionals](../06-fitxes-funcionals/README.md); [142 fitxes UML](../07-uml-integrat/README.md); [matriu de cobertura](../07-uml-integrat/00-matriu-cobertura-cataleg.md).
- [Matriu de 192 pantalles](../03-canvis-pendents/12-matriu-pantalles-abans-despres.md); [25 pantalles amb mapatge provisional](../07-uml-integrat/00-matriu-25-pantalles-per-validar.md); [matriu de superfície executable](../04-estat-final/41-matriu-superficie-executable-casos.md); [matriu d'accions revisades](../07-uml-integrat/00-matriu-traçabilitat-accions-revisades.md).
- Esquema: [migració del nucli](../../sif/database/migrations/2026_06_02_000001_create_sif_core.sql), [000004](../../sif/database/migrations/2026_09_16_000004_add_commercial_operation_and_fiscal_fields.sql), [000005](../../sif/database/migrations/2026_09_16_000005_add_operation_lifecycle_tables.sql), [000006](../../sif/database/migrations/2026_09_16_000006_add_cross_system_control_tables.sql), [000007](../../sif/database/migrations/2026_09_21_000007_add_idempotency_payload_hashes.sql).
- Contracte d'idempotència actual: [implementació de 21/09](../07-uml-integrat/00-contracte-idempotencia-payload-implementat.md). Les auditories més antigues conserven valor HISTÒRIC, però no poden substituir la lectura del PHP actual.
- **Xat original JSONL:** còpia adjuntada a la conversa de treball de 22/09/2026, 57.118.200 bytes; l'arxiu conté 145 missatges de rol usuari dins de 3.392 esdeveniments JSONL. És una font de requisits i decisions, NO una prova de comportament executable ni un document preparat per publicar. **No pujar el brut a aquest repositori públic:** conté referències a credencials i dades personals/operatives; custodiar-lo en dipòsit privat amb accés restringit. El seu contingut s'ha de reconciliar per requisit/decisió i referència interna anonimitzada, no copiar-lo íntegre a les fitxes. En aquesta versió NO s'afirma haver contrastat literalment cadascun dels 145 missatges contra els 142 UC.
- **Altres fonts pendents de confirmació:** exports Trello vius, cron/rutes/hosts en producció, esquemes i dades efectives de BDs, còpia completa d'intranet de col·laboradors si entra en abast. Els recomptes històrics dels inventaris no substitueixen l'arbre actual.

## 2. Invariants i contractes transversals que s'han de provar

**I-01 Identitat del fet bancari.** Dos intents amb la mateixa clau idempotent i payload diferent han de donar CONFLICT sense efectes; dues claus diferents per al MATEIX càrrec/transferència conciliable NO han de produir dos ingressos. Dues transferències realment diferents, encara que tinguin mateix import/data/pagador, han de poder registrar-se cadascuna una vegada. Separar identitat del pagament extern, clau de petició i hash de payload.

**I-02 Conservació monetària.** Tot cobrament extern té import immutable en cèntims/decimal exacte; suma de les assignacions efectives + import no assignat = import real disponible, amb signes i límits diferenciats per CHARGE/REFUND/COMPENSACIÓ. Cap assignació repetida o transferència interna no crea CHARGE fictici; saldo i devolució no excedeixen disponible. Comprovar a servei i BD sota concurrència, no només en formularis.

**I-03 Cobertura comercial/fiscal.** Per a cada operació, inscrit i prestació, identificar factura fiscal ja emesa i receptor correcte abans de decidir issueInvoice versus registerPayment. Una clau de callback nova no autoritza una segona factura de la mateixa prestació.

**I-04 Autoritat fiscal i permisos.** Només el SIF pot assignar número fiscal, encadenar registre i decidir la rectificativa; web i intranets només sol·liciten accions autoritzades. Identitat d'actor, rol i titularitat del recurs es comproven al SERVIDOR i s'auditen. Cap endpoint públic pot dependre exclusivament de validació JS o de conèixer un UUID.

**I-05 Traçabilitat per inscrit.** Un pagament únic de pack/grup/empresa pot cobrir N inscripcions amb imports diferents: conservar enllaç comercial → ID_INSC → línia fiscal → UUID_FACTURA → UUID_PAYMENT → imports atribuïts i moviments interns, sense multiplicar l'ingrés extern. Una relació documental a fact_rels no equival a un assentament monetari.

**I-06 Fronteres d'estat.** Pagament, emissió, acceptació AEAT, lliurament de PDF, cobrament d'empresa, accés Moodle, baixa acadèmica, consentiment de màrqueting i sincronització llegat tenen estats i proves separats. Retorn HTTP 200 o job SENT/PROCESSED no és per si mateix una prova de cada efecte posterior.

**I-07 Històric i immutabilitat.** Emesa la factura, no reescriure camps fiscals ni número; correccions segons operació i criteri fiscal documentats. Preservar origen, emissor, versió i situació NO_VERIFACTU dels documents històrics; no tornar-los a emetre sense classificació.

## 2 bis. Auditories de casos d'ús per lots (estat verificat)

- **Lot 01 — 22/09/2026:** [UC-106, 107, 108, 109, 110, 112, 115, 122 i 125 — auditoria contra el PHP web i SQL actual](../07-uml-integrat/00-auditoria-casos-pendents-lot-01-2026-09-22.md). S'han contrastat nou UC de manera dirigida, **no tancats funcionalment**, i s'han corregit notes històriques de les fitxes UC-108, UC-110 i UC-125. UC-108: alta gratuïta força mailing=1 malgrat rebre opció; UC-110: DescompteAmic sí fixa TIPUS_INSC='G'; UC-125: SQL 000006 i scripts de mailing sí estan a main. Proves, desplegament, pantalla completa, cas d'ús exhaustiu i diagrames d'activitat de totes les pàgines: **PENDENTS**. La pàgina d'alta del tastet té al lot 01 un **primer diagrama només de l'endpoint**, etiquetat parcial, i no substitueix el treball íntegre RM-037.
- **Pendents de revisar en lots següents:** 133 fitxes restants del catàleg de 142 i les variants d'acció identificades en cada pantalla; la xifra no vol dir que aquests 133 casos siguin absents ni que els nou revisats estiguin validats o tancats.

## 3. Registre d'accions — IMPLEMENTACIÓ i DOCUMENTACIÓ

Cada ID RM representa una feina de tancament amb dues pistes: **DOC** (actualitzar fitxes, pantalles, UML, dades i decisions) i **IMP** (PHP, SQL, adaptadors, autorització i proves). Quan una garantia ja existeix al core, NO reprogramar-la: provar-la i completar només els buits indicats.

### A. Descoberta funcional, cobertura i traçabilitat

**RM-001 · P0 · Inventari exhaustiu d'accions actuals.** DOC: revisar individualment web-actual, intranet-actual, intranet-alumne-actual, old-intranet, candidata pay i intranet nova; inventariar cada pàgina, botó, AJAX, script, cron, mètode que escriu estat i efecte sobre dades. Confirmar amb desplegament quins escriptors estan actius. Relacionar cada acció amb UC/variant i diferenciar accions de consulta, administratives, econòmiques, fiscals, documentals, acadèmiques i de comunicacions. IMP: no iniciar refactors massius fins a definir el tall i els escriptors. SORTIDA: una fila per acció amb ruta → classe/mètode → BD → UC → comportament final → prova; cap ruta activa sense classificació.

**RM-002 · P0 · Revisar contingut, no només existència, de les 142 fitxes.** DOC: cada cas ha de representar les regles reals, actors, precondicions, variants de negoci, dades, errors, permisos, accions posteriors i criteris d'acceptació. Separar seqüència existent i seqüència final. Els esborranys de 21 apartats no es donen per validats en bloc. IMP: detectar classes i taules citades però absents o sense writer; convertir-ho en tasques concretes. SORTIDA: matriu UC → accions → proves → estat independent per component.

**RM-003 · P0 · 192 pantalles i els seus estats.** DOC: per a cadascuna, desglossar botons, formularis, permisos, errors, accions asíncrones, correus i efectes; completar les 25 files amb mapatge provisional i revisar TAMBÉ les altres 167. Identificar captures actual/final i la ruta del codi real. IMP: adaptar cada acció fiscal/econòmica al SIF, no només modificar la maqueta. SORTIDA: cap acció sensible de pantalla sense UC i test d'usuari autoritzat/no autoritzat. Els diagrames d'activitat UML per pàgina i apartat de la web tenen una tasca específica RM-037; no es poden substituir per un únic diagrama genèric de web.

**RM-004 · P1 · Variants de negoci.** DOC: revisar sistemàticament curs normal, taller, jornada, CDD/acreditació, reptes i tastets gratuïts, edicions especials, fraccions, gratuït/subvencionat, packs de N components, grups i trams, pagador alumne/empresa/tercer, USOC, regal i bescanvi, codis, dret a descompte validat després, càrrec de gestió, curs anul·lat/posposat, canvi de curs, baixa, reserva i llista d'espera. Per cadascuna: actor, regla, dades, imports i efecte fiscal o ABSÈNCIA d'efecte. IMP: construir proves de regressió per variants que canvien import, receptor, IVA, titularitat, places o dret. SORTIDA: variant coberta amb evidència o candidat de UC/variant nou; no inventar IDs per analogia.

**RM-005 · P1 · Reconciliar el xat original amb el catàleg.** DOC: extraure decisions/restriccions del JSONL privat en un registre intern amb localitzador de torn i evidència, classificar com implementada/documentada/pendent/contradictòria i assignar UC o decisió; publicar només síntesis anonimitzades. Revisar especialment correus des de Template i PHP directe, factura abans de cobrar, política de baixes/devolucions, packs, grups, USOC, regal, permís empresa/alumne, panell SIF i distinció E_FACT/EMESA_ABANS_COBRAMENT. IMP: crear tasques només quan hi hagi diferència verificable. SORTIDA: cap decisió rellevant sense destinació o justificació explícita.

**RM-006 · P1 · Actualitzar fonts documentals històriques.** DOC: marcar les auditories pre-21/09 com HISTÒRIQUES quan afirmen que InvoiceService/PaymentService no comparen payload; actualitzar referències a main i la disponibilitat real de web-actual; distingir estat d'una branca antiga i estat del commit de tall. IMP: cap. SORTIDA: una font de veritat tècnica actual i informe de diferències, sense esborrar l'històric.

**RM-007 · P1 · Contrastar UML i PHP/BD real.** DOC: per a cada seqüència, diferenciar classe real, proposada i taula SQL; comprovar noms, signatures i línies de dades; seqüències de transacció, callbacks i errors; renderitzar PlantUML/Mermaid. IMP: completar interfícies, repositoris, workers i adaptadors que s'invoquen només en el disseny. SORTIDA: traça verificable UML → classe/mètode real o DISSENY → taula/migració → prova.

**RM-008 · P1 · Completar les fonts de codi i l'estat dels desplegaments.** DOC: revisar diferència entre 3.356 fitxers codi-drive del commit de tall i inventaris històrics més grans; identificar si intranet-col·laboradors, crons, rutes antigues i altres escriptors entren en l'abast i estan realment actius. IMP: establir un sol punt de producció fiscal per operació, amb pla d'aturada/compatibilitat dels escriptors vells. SORTIDA: inventari de superfície productiva signat, no deduït del nom d'una carpeta.

### B. Idempotència, diners i conciliació

**RM-009 · P0 · Identitat bancària ENTRE claus idempotents.** DOC: definir identitat immutable de càrrec Redsys (referències reals, DS_ORDER i comprovació de captura), transferència bancària, efectiu/TPV manual i retorn; distingir dos moviments iguals reals d'un duplicat; decidir gestió d'ingrés sense referència completa. IMP: índex/restricció i servei de conciliació sobre la clau externa vàlida segons mètode, amb incidència si hi ha dubte; el hash de petició ja implementat NO cobreix aquest cas. PROVES: mateix fet amb K1/K2 sense doble CHARGE; dos fets reals mateix dia/import amb dos UUID_PAYMENT; callback repetit/tardà.

**RM-010 · P0 · Conservació exacta dels imports.** DOC: expressar invariants de moviment, assignació, sobrant, saldo, devolució i reassignació amb exemples en cèntims; documentar l'abast de cada signe/tipus. IMP: endurir PaymentPayloadValidator i el repositori: imports positius on pertoqui, suma exacta d'allocations, límit de saldo i reemborsament, controls de concurrència, DECIMAL/cèntims sense float per càlcul monetari. PROVES: 100 € amb assignacions 80+80 → error; assignacions parcials i sobrant explícit; dues reassignacions simultànies → una sola admissible; límits de refunds.

**RM-011 · P0 · Ledger quantitatiu per inscripció.** DOC: decidir schema i semàntica d'enrollment_fund_movement o alternativa equivalent (tipus, import en cèntims, ID_INSC origen/destí, UUID_PAYMENT, UUID_FACTURA, línia, operació, correlació, reversió, ordre i idempotència). Diferenciar imputació històrica, ingrés extern i trasllat intern. IMP: migració, repositori, servei i projecció de saldo; enllaçar factura_linia / fact_rels quan correspongui. PROVES: pack amb dues inscripcions i un sol CHARGE; grup N persones i pagament parcial; canvi de curs/compensació sense CHARGE nou; reversió i refund amb traça.

**RM-012 · P0 · Fraccions manuals distintes i repetides (UC-23).** DOC: el constructor actual deriva K de ID_INSC, import, data i usuari; decidir identificador real de l'ingrés, factura destí i política de reintent i de dues fraccions idèntiques. IMP: ampliar el contracte de ManualInstallmentPaymentPayloadBuilder, identificador de fet bancari i controls K/identitat externa. PROVES: dos ingressos reals de 50 € mateix dia i operador → dos pagaments; reintent de cadascun → un sol pagament per fet.

**RM-013 · P0 · Cercar, reutilitzar i assignar un pagament EXISTENT (UC-56/105).** DOC: especificar cercador per NIF/NIE, IDPAG, DS_ORDER, referència bancària, regal i número de factura, amb pagador ≠ receptor ≠ inscrit i límits per rol. IMP: servei de cerca + assignació/reassignació de fons disponibles a una altra factura/inscripció sense PaymentRepository::createPayment ni CHARGE nou; bloqueig i comprovació de saldo. PROVES: reutilitzar UUID_PAYMENT existent, assignació parcial i concurrent, consulta sense efecte econòmic.

**RM-014 · P1 · Idempotència del payload ja implementada.** DOC: actualitzar les fitxes i auditories per reflectir InvoiceService, PaymentService (V1/V2), PayloadIdempotencyValidator i 000007; documentar tractament conservador de factures antigues sense hash i diferències de representació de decimals/dates. IMP: validar canonicalització als builders i executar tests de col·lisió/concurrència; NO tractar-ho com a classe completament absent. PROVES: mateixa K/payload reusa; mateixa K/import o receptor diferent → 409; V1 llegat; factura antiga sense hash.

**RM-015 · P1 · Excés, saldo, compensació i devolució.** DOC: separar import bancari, sobrant no atribuït, crèdit, destinació, factura rectificativa i devolució real; fluxos UC-06/28/56/104/105. IMP: serveis que preservin cada fet real, amb assignacions negatives/compensacions segons model aprovat, vinculació a línia/inscripció i controls anti-duplicació. PROVES: sobrant 20 €, retorn parcial, compensació sobre deute diferent i reintent sense augment de saldo.

### C. Cobertura fiscal/comercial i canals

**RM-016 · P0 · Factura ja emesa abans de confirmar un cobrament (UC-01/02/03/04/21).** DOC: per curs, pack, grup, regal i pagador empresa, establir com es consulta la cobertura fiscal prèvia per ID_INSC/operació/receptor i com es decideix entre issueInvoice i registerPayment. IMP: el handler RedsysCourseInvoiceService i altres handlers han de resoldre la factura ja emesa amb altra K i registrar-hi el cobrament, o obrir incidència si hi ha ambigüitat; no emetre per defecte una segona factura. PROVES: factura d'empresa pendent + TPV posterior + callback tardà; mateix inscrit, dues K; imports parcials; receptor diferent → bloqueig/conciliació.

**RM-017 · P0 · Autorització i autenticació dels punts d'entrada.** DOC: matriu actor × rol × operació × titular del recurs × endpoint, incloent alumne, empresa, operador, tutor i auditor; exigir denegacions i logs sense exposar dades personals. IMP: verificar configuració real del proxy i incorporar/validar al backend control d'identitat i permisos a issue.php, register.php i rutes de consulta/document/rectificació/pagament; mai confiar només en JS o en un enllaç. PROVES: POST anònim, tutor o alumne a emissió/cobrament → cap efecte fiscal; empresa A no veu factura B; rol auditor només lectura.

**RM-018 · P0 · Integració efectiva de TOTS els escriptors dels canals.** DOC: per cada ruta activa anterior, decidir delegació al SIF, sincronització posterior o retirada; marcar «candidat» separadament de «desplegat». IMP: adaptar web, intranet principal, intranet alumne, callbacks, crons i scripts de conciliació; prohibir número fiscal/updates de factura paral·lels després del tall. PROVES: recorregut HTTP real per cada variant i comparació abans/després BD web/intranet/SIF, Redsys i correus.

**RM-019 · P0 · Gestió completa de commercial_operation.** DOC: màquina d'estats de sol·licitud, validació, reserva, dret, expiració, pagament, facturació, cancel·lació i reconciliació; identitats d'operació/línia/part; especificar combinacions de pack/grup/empresa, promoció, gratuït i subvencionat. IMP: serveis/escriptors del cicle de vida, bloquejos per plaça i idempotència, endpoints i adaptadors; comprovar que les taules 000004–000006 s'apliquen i són utilitzades. PROVES: reserva concurrent/expirada, operació no facturable, canvi pre/post factura i rollback parcial.

**RM-020 · P0 · Regles i imports de grup (UC-16/91/118).** DOC: especificar font de preu, tram de descompte, congelació per participant, receptor i factura única, variació del grup abans/després d'emetre. IMP: LegacyGroupInvoicePayloadBuilder::lineAmounts ha de validar base − descompte + fiscalitat = total de cada línia i l'interval permès; no confiar en totals d'entrada incoherents. PROVES: base 100/descompte 20/total 90 → rebutjar; N participants; alta/baixa posterior, una sola factura i repartiment per inscrit.

**RM-021 · P1 · Packs i línies comercials/fiscals.** DOC: reconèixer tots els components i descomptes reals de Pack/EdicioPack, les edicions alternatives, la indisponibilitat d'un component i la diferència abans/després de TPV. IMP: lligam determinista d'operació/linia/ID_INSC amb factura_linia i import individual; snapshot congelat abans de cobrar. PROVES: pack de dos o N cursos, curs indisponible, preu modificat durant checkout, callback duplicat, canvi d'un component.

**RM-022 · P1 · Regal, dret i bescanvi.** DOC: separar comprador/receptor de factura i destinatari/inscripció; caducitat, duplicat, bescanvi parcial, canvi de curs i dret ja consumit. IMP: vincular dret i bescanvi a factura original, sense emissió ni CHARGE nou només per bescanviar; traçabilitat i accés segregats. PROVES: mateix codi dues vegades, receptor diferent, regal pagat/després bescanviat, canvi d'edició.

**RM-023 · P1 · USOC, grups, empresa i pagador tercer.** DOC: definir pagador, responsable, receptor de cada factura, import participant/entitat i doble facturació quan correspongui; guard contra factura doble entre canals. IMP: completar adapters per a fluxos de part alumne/part entitat i atribució per inscrit, incloent ingrés parcial i factura abans de cobrar. PROVES: import de dues factures = prestació classificada, pagament extern sense duplicació, empresa substitueix alumne abans/després TPV.

**RM-024 · P1 · Curs gratuït, repte/tastet i subvencionat.** DOC: revisar directament web-actual/ajax/enviarInscripcioTastet.php i UC-108; substituir la nota històrica «no disposem del codi web-actual» per contrast de camps, validacions, alta a inscripcions_reptes i mailing. Contrastar UC-109 i variants CDD/acreditació/cursEsRepte amb l'alta real. IMP: serveis/adapter gratuït, control d'accés i consentiment separats; cap factura, intenció TPV o CHARGE fictici per alta gratuïta. PROVES: alta vàlida/duplicada, inscripció gratuïta amb accés Moodle pendent, mailing desmarcat.

**RM-025 · P1 · Descomptes i promocions reals.** DOC: per cada TIPUS_DESC i VALID_DESC, regla, justificació, vigència, combinabilitat, càlcul, dret futur, visibilitat i snapshot fiscal; revisar descompte amic, grup, carnet, família i codis. IMP: validació servidor, custòdia de justificants, reserva/consum de promoció i correcció fiscal si s'aprova després de facturar. PROVES: promoció duplicada/caducada, justificació denegada, descompte tardà i canvi de curs.

**RM-026 · P1 · Factura manual i factura abans de pagar (UC-04/62/92).** DOC: completar pantalla, camps de receptor, selecció d'ID_INSC, import recalculat, avís/confirmació, permisos de rol, resultat visible i link posterior; E_FACT NO és sinònim d'EMESA_ABANS_COBRAMENT. IMP: adaptar ruta intranet real i comprovar-ho tot al servidor, sense dependència exclusiva del DOM; crear factura PENDING i posterior registerPayment, no segona emissió. PROVES: selecció repetida, import manipulat, receptor empresa i pagament posterior.

**RM-027 · P1 · Enllaços de pagament i consultes d'alumne/empresa.** DOC: distingir IDPAG, token d'enllaç, intenció Redsys, propietat, revocació/caducitat i consulta d'estat; revisar obtenirUrlPagament i URLs de correus. IMP: gestor de payment_link i adaptadors autoritzats, no només esquema SQL; evitar que el coneixement d'un URL doni accés fiscal il·limitat. PROVES: caducat/revocat, actor aliè, pagament iniciat abans de revocar, empresa i alumne amb visibilitats diferents.

**RM-028 · P1 · Correus i notificacions en tots els punts d'entrada.** DOC: inventari de plantilles Template I correus construïts directament en PHP, canals, destinatari, idioma, contingut, URL de pagament i document segur; no inferir enviament a partir de factura creada. IMP: outbox/worker o mecanisme demostrable idempotent, reenviament, incidències i permisos. PROVES: timeout després de factura, reintent sense doble correu erroni, factura d'empresa no enviada a alumne, enllaç caducat.

### D. Cicle posterior, documents, AEAT i governança

**RM-029 · P0 · Canvis de curs, baixes, devolucions i rectificatives end-to-end.** DOC: per UC-05/06/26–29/71–76/89/93, separar sol·licitud i decisió administrativa, import/receptor original, rectificativa positiva/negativa, ingrés/refund real, crèdit, historificació i accés acadèmic; anul·lar un curs NO equival automàticament a retornar diners. IMP: orquestrador transaccional/saga segons BDs externes (CourseChangeService és DISSENY), repositoris i accions de pantalla, conciliació quan una fase falla. PROVES: mateix import/concepte diferent, augment/disminució, rectificativa sense refund, refund sense doble moviment, fallada Moodle i reintent.

**RM-030 · P1 · Documents fiscals i accessos.** DOC: decidir generació, custòdia real, PDF/QR/registre/XML quan pertoqui, origen de bytes, estat de disponibilitat, URL i titular autoritzat, retenció/exportació. IMP: DocumentRepository només desa metadades: afegir storage real, verificació hash/bytes, servei d'accés i registre de lliurament; no mostrar CREATED com a document descarregable. PROVES: fitxer absent, hash incorrecte, URL caducada, factura de tercer, còpia històrica i exportació.

**RM-031 · P0 · Cua i transport AEAT, entorn i resposta.** DOC: separar QUEUED, SENT local, resposta per registre, acceptació/rebuig AEAT, incertesa després de tall de xarxa, reintent, incidència i prova d'enviament; font normativa oficial vigent abans del tall. IMP: mantenir assertImmutablePayload ja implementat, completar transport/configuració de producció amb controls d'entorn i certificat, reconciliació/consulta de resultats, bloqueig i monitoratge; SoapTransport actual només admet endpoint de proves. PROVES: payload de cua alterat → DEAD_LETTER sense enviar; caiguda després del SOAP; resposta parcial; reintent idempotent.

**RM-032 · P1 · Multiemissor, botiga/SL i històric.** DOC: per UC-97/98, identificar entitat venedora real, emissor, titular del TPV, sèrie, impostos, certificat i històric per producte; aprovar partició per emissor abans de cistelles mixtes. IMP: configuracions/seqüències/cadenes/BD i rutes segregades o model multiemissor aprovat; el fiscal_chain_state.ID=1 actual no implementa automàticament diversos emissors. PROVES: dos emissors mateixa numeració visible, cistella mixta, refund parcial, certificat creuat i consulta històrica.

**RM-033 · P1 · Moodle, estat acadèmic, pròrrogues i conciliació.** DOC: completar UC-95/96/113/124/127/129: regla de deute i certificat, pròrroga, baixa, propietari de cada camp, matrícula/rol/edició, conservació del progrés i execució per fila. IMP: adaptador Moodle idempotent, mapeig estable, worker i política de resolució de divergències; academic_economic_state_event SQL no és l'operació Moodle real. PROVES: baixa i pròrroga concurrents, alumne que paga i matrícula que falla, conciliació sense factura fictícia.

**RM-034 · P0 · Proves i evidència per canal.** DOC: matriu UC × actor × pantalla × endpoint × efectes de BD × prova/versió/resultat; marcar els tests definits però no executats. IMP: executar runner PHP/MySQL, test HTTP amb permisos, preproducció Redsys/AEAT, restauració, recuperació d'errors i go/no-go segons abast i versió. PROVES: incloure regressions de tots els RM P0, capturar UUID_FACTURA/UUID_PAYMENT/ID_INSC i respostes de tercers sense dades reals de clients.

**RM-035 · P1 · Diferenciar esquema definit, aplicat i utilitzat.** DOC: per cada taula de les migracions 000001–000007, registrar migració, versió de BD, FKs/índexs, servei writer/reader, UC i prova; no inferir que el SQL d'una taula és una funcionalitat completada. IMP: aplicar migracions en test/preproducció, verificar integritat, repositoris i tractament d'errors; mantenir pla de migració/dades històriques. SORTIDA: diccionari de camps i model de classes actualitzats amb estats reals.

**RM-036 · P1 · Governança de canvis i tancament de documents.** DOC: designar aquest registre com a índex de buits; no reescriure auditories antigues com si fossin proves presents. Per cada RM tancat: actualitzar UC, UML, matriu de pantalles, matriu de dades, backlog, manual i evidències; registrar commit, decisió i motiu. IMP: generar checks de traçabilitat i regressió quan sigui possible. SORTIDA: no hi ha dos «estats actuals» incompatibles sense data/versió.

**RM-037 · P0 · Diagrames d'activitat UML per CADA pàgina i apartat de la web.** DOC: inventariar les pàgines i URL reals de la web actual (incloses les que no generen factura), els seus apartats, formularis, botons, enllaços amb efectes, modals, AJAX i processos associats; elaborar **com a mínim un diagrama d'activitat UML del funcionament ACTUAL i un altre del funcionament FINAL per cada pàgina i per cada apartat amb un flux o decisions propis**. Si la pàgina té diverses accions independents (p. ex. consultar oferta, aplicar descompte, inscriure's, pagar, descarregar document), desglossar-les en subdiagrames identificats i referenciats des del diagrama de la pàgina; no substituir tot això per un diagrama genèric de «web» o un sol diagrama de cas d'ús. Fer servir PlantUML (bloc \`plantuml\`) i incorporar una representació llegible/renderitzada o instruccions de renderització validades. Les activitats han de mostrar: actor i sistema/partició responsable; inici i final; ruta/pantalla i acció disparadora; entrada de dades; validacions al client i al servidor diferenciades; decisions amb condicions de guarda; ramificacions i variants de negoci; bucles/reintents; excepcions, denegacions i cancel·lacions; efectes de BD; missatges/correus; crides a altres canals; processos asíncrons Redsys/SIF/AEAT quan pertoqui; retorn a pantalla i estat visible. Distingir explícitament el comportament observat al codi del comportament només proposat, i no inventar controls ni resultats sense font. IMP: dels canvis ACTUAL→FINAL, derivar tasques d'adaptació de front, endpoint, autorització, servei, model de dades, transaccions i proves; cap canvi de dibuix es dona per implementat sense codi i test. **Traçabilitat obligatòria a cada diagrama i subdiagrama:** ID de pàgina/apartat/acció; URL i ruta/fitxer/mètode PHP real; UC/variant; actors i permisos; taules/camps i escriptors; components SIF actuals o marcats DISSENY; prova d'acceptació; estat independent DOC/IMP/TEST; enllaç de retorn a la matriu de 192 pantalles i a RM-001/RM-003. Si una activitat no té UC, registrar-la com a buit candidat en lloc d'assignar-ne un d'imprecís. **SORTIDA:** índex complet pàgina → apartats → accions → diagrama d'activitat actual/final → UC/variant → codi/BD → modificació → prova, amb diagrames revisats i renderitzats. **CRITERI DE TANCAMENT:** cap pàgina ni apartat dins de l'abast sense diagrama actual i final o justificació documentada de la no aplicabilitat; comprovació acció per acció contra la font PHP de la versió auditada i verificació dels fluxos finals executats abans de marcar IMP/TEST com a tancats.

## 4. Inventari inicial d'accions dels canals per revisar UNA PER UNA

Aquesta taula és un **punt de partida verificable**, no l'inventari complet dels fitxers de codi-drive ni el mapa d'execució de producció. «UC candidat» no significa fitxa funcionalment validada. Les rutes són relatives a codi-drive llevat que s'indiqui el contrari.

| Acció / font actual o candidat | UC/variants a contrastar | Acció específica de revisió i modificació |
| --- | --- | --- |
| web-actual/ajax/enviarInscripcio.php · alta curs i IDPAG | UC-14, 106, 107, 112, 115 | Desglossar validació de dades, duplicat, edició, preu, URL, consentiment, alta i correus; centralitzar intenció i factura al SIF. |
| web-actual/ajax/enviarInscripcioTaller.php · taller | UC-14a, 106, 112, 115 | Comparar condicions pròpies de taller amb curs i documentar diferències. |
| web-actual/ajax/enviarInscripcioPack.php · alta pack | UC-15, 106, 112, 115, 122 | Relacionar components, edicions, imports, disponibilitat, ID_INSC, IDPAG i un pagament/una factura fiscal segons contracte. |
| web-actual/Pack.php i EdicioPack.php · construcció de producte | UC-15, 114, 115, 122 | Inventariar regles d'elegibilitat/preus/edicions i congelar la versió comercial; no confondre filtratge de catàleg amb emissió. |
| web-actual/DescompteGrup.php i enviament de grup | UC-16, 21, 91, 118 | Desglossar participant, responsable, tram, places i canvis posteriors. |
| web-actual/DescompteAmic.php | UC-110, 112, 115 | Dues persones, dret/condicions de descompte i un fet de pagament; aclarir relació fiscal. |
| web-actual/ajax/enviarInscripcioBescanvia.php | UC-17, 18, 18a, 119 | Distingir compra de dret i alta del destinatari; no generar nova factura per bescanvi sense nova prestació cobrada. |
| web-actual/ajax/enviarInscripcioTastet.php | UC-108, 125, 129 | Confrontar camps reals, inscripcions_reptes, alta i mailing; corregir nota UC-108 que deia no disposar del codi. |
| web-actual/ajax/enviarImatgeCarnetInscripcio.php | UC-90, 111, 116 | Custòdia de justificants, permís, validació, retenció i retirada d'accés públic. |
| web-actual/ajax/obtenirCorreusValidsPromo.php | UC-20d, 111, 117 | Codis creats/assignats, unicitat, reserva i consum sota concurrència. |
| web-actual/realitzaPagamentAutomatic.php | UC-03, 14–19, 51, 52, 68 | Verificar escriptor fiscal antic i retirar/delegar després del tall; signatura, DS_ORDER, factura existent i efectes de correu. |
| pay-prisma-cat-canvis-verifactu/realitzaPagamentAutomatic.php | UC-03, 63, 68, 86, 112 | Comparar candidata amb web real, signatura i efecte per producte; no assumir que substitueix callback productiu. |
| web-actual/ajax/efectuarPagament.php i candidata pay | UC-03, 50, 63 | Identificar origen d'intenció, credencials del canal, ordre sortint i confirmació. |
| intranet-actual/ajax/alumnes/efectuarPagament.php i Intranet::efectuarPagament | UC-02, 22, 23, 56, 62 | Separar cobrament real, atribució, factura pendent, fraccionament, actor i errors. |
| intranet-actual/Intranet.php · consulta/modifica alumne | UC-07, 21, 26–29, 42, 62, 70, 94, 100, 120 | Inventariar CADA acció/botó i el mètode corresponent; no classificar tota la pantalla com un sol UC. |
| intranet-actual/Intranet.php · passar pagaments / analitzar TPV | UC-02, 22, 23, 53, 56, 105 | Import real, referència de banc/TPV, reús, assignació, incidència i conciliació. |
| intranet-actual/Intranet.php · factura abans de pagar | UC-04, 21, 62, 92 | Previsualitzar, validar al servidor, emitir PENDING i cobrar posteriorment sense segona factura. |
| intranet-actual/Intranet.php · canvi de curs / baixa / anul·lació | UC-05, 06, 26–29, 71–76, 127 | Desglossar decisió administrativa, fiscal, diners i Moodle amb historial i reintents. |
| intranet-actual/Intranet.php · canvi de descompte / validació | UC-20–20d, 90, 111, 116, 117, 121 | Verificar totes les regles TIPUS_DESC / VALID_DESC i efectes postfactura. |
| intranet-actual/Intranet.php · edició i càrrega d'alumnes Moodle | UC-95, 113, 114, 124, 127, 129 | Identificar mètodes d'alta/baixa/importació i la correlació amb estat econòmic. |
| intranet-alumne-actual/ajax/cursos/obtenirUrlPagament.php | UC-33, 50, 61, 86, 102, 103 | URL segura, caducitat, deute real, propietat, pagador empresa i token revocable. |
| intranet-alumne-actual/IntranetAlumne.php · factura/estat/accés | UC-07, 32, 61, 80, 95, 102, 123, 124 | Identificar cada consulta/escriptura; alumne NO veu factures de tercer no autoritzades. |
| old-intranet i crons/processos històrics | UC-47, 53, 64, 68, 85 | Determinar què segueix actiu; tall controlat d'escriptors duplicats, sense eliminar dades històriques. |
| sif/public/api/factures/issue.php | UC-01, 04, 62, 99, 101 | AuthN/AuthZ per actor i recurs; validar integritat de petició i idempotència existent. |
| sif/public/api/payments/register.php | UC-02, 22, 23, 62, 99, 101 | AuthN/AuthZ, identitat externa, conservació d'imports i saldo. |
| sif/public/api/redsys/callback.php i worker | UC-03, 51, 52, 63, 68 | Validació signatura, intenció prèvia, callback duplicat/tardà, processament únic i conciliació. |
| sif/src/Service/RedsysCourseInvoiceService.php | UC-01–04, 14, 21 | Comprovar factura prèvia d'altra clau abans d'issueInvoice i registrar pagament sobre ella. |
| sif/src/Service/ManualInstallmentPaymentPayloadBuilder.php | UC-23, 56 | Diferenciar dos ingressos reals idèntics i el reintent del mateix fet. |
| sif/src/Service/LegacyGroupInvoicePayloadBuilder.php | UC-16, 91, 118 | Validar incoherència base/descompte/total per participant; una factura i import atribuït per inscrit. |
| sif/src/Repository/DocumentRepository.php | UC-07, 78, 80, 97 | Custòdia real dels bytes, hash verificat i descàrrega per actor autoritzat. |
| sif/src/Service/FiscalQueueProcessor.php i SoapTransport.php | UC-09, 54, 77, 81 | Preservar validació de payload congelat; completar transport real, respostes/reintents i observabilitat. |
| pantalles/correus de col·laboradors si entren en abast | UC-65, 66, 99 | Separar honoraris/proveïdors de factures comercials SIF; confirmar còpia de codi i permís. |
| correus Template + missatges directes PHP | UC-32, 58, 79, 80, 123, 125 | Mapa complet de destinatari, link segur, enviament efectiu, fallada i reintent. |

**A completar després d'inventariar les 192 pantalles i tots els escriptors:** accions de permisos, cercadors, empreses, rebuts històrics, devolucions/compensacions, grups modificats, regal caducat, edicions CDD, subvencions, reclamacions, baixa massiva, conciliació bancària, pròrrogues, registre de consentiment, incidències i exports. No declarar «totes les funcionalitats actuals» fins a disposar de les files concretes.

## 5. Matriu obligatòria de variants de negoci

| Família | Variants que s'han de comparar amb codi i dades reals | Evidència mínima |
| --- | --- | --- |
| Producte i oferta | Curs, taller, jornada, pack N, grup N, edicions especials, CDD/acreditació, repte/tastet, subvencionat, regal i llibres si entren en abast | Punt d'entrada, condició, preu, entitat venedora, places i fiscalitat. |
| Pagador/receptor | Alumne, particular responsable, centre/empresa, USOC parcial, tercer, regal comprador/destinatari | Identitat fiscal i accés documental per actor; imports per part. |
| Descompte | Alumne PrisMa, Carnet Jove, família/altres supòsits sensibles, amic, grup per trams, pack, promoció/codi, validació posterior, descompte excepcional | Tipus, prova, import, combinació, vigència, snapshot i rectificació posterior si aplica. |
| Temporalitat | Abans TPV, callback confirmat, factura emesa abans de cobrar, cobrament parcial, callback tardà, baixa/canvi posterior, reintent després d'error | Comparació d'estat abans/després i cap doble factura/CHARGE. |
| Modificacions | Canvi curs/edició, canvi de titular/receptor, afegir/treure participant, canvi de preu/descompte, baixa/anul·lació, posposar, importació històrica | Event, factura original intacta, rectificativa quan pertoqui, saldo i accés. |
| Excepcions | Sense referència bancària, duplicat amb K diferent, dues fraccions idèntiques reals, document absent, plaça esgotada, URL revocada, Moodle fallit, AEAT resposta desconeguda | Error/incident específic, sense efecte fictici, prova de recuperació. |

## 6. Traçabilitat de dades i classes: controls obligatoris

1. **Factura i pagament:** factura, factura_linia, factura_registres, fiscal_sequence, fiscal_chain_state, fiscal_queue, payment_transaction, payment_allocation, fact_rels, factura_rectificacio, credit_balance; camp i relació per ID_INSC/operació/UUID_PAYMENT. Registrar escriptor real, lector, índex, invariants i prova.
2. **Cicle comercial:** commercial_operation, commercial_operation_party, commercial_operation_line, operation_line_invoice_link, capacity_reservation, commercial_entitlement/event, discount_validation/evidence i payment_link. Registrar writer real i estats; SQL sol = SQL DEFINIT.
3. **Identitat, consentiment i acadèmic:** communication_consent/event, external_identity_link, identity_conflict_case, academic_economic_state_event, academic_reconciliation_item i les taules d'importació/canvi d'edició. Definir camp mestre, autorització i servei de reconciliació.
4. **Idempotència:** separar IDEMPOTENCY_PAYLOAD_HASH, PAYLOAD_HASH_VERSION, identitat d'ingrés extern, hash d'intenció Redsys, hash de callback i HASH_FACT fiscal. Una d'aquestes garanties no substitueix les altres.
5. **Classes:** a cada diagrama indicar EXISTEIX PHP / DISSENY / NOMÉS SQL; comparar constructor, mètodes, paràmetres i crides amb el commit de tall. Ex.: FreeSampleEnrollmentService (DISSENY) ≠ alta de tastet ja programada al codi llegat.
6. **Entitats/emissor:** distingir emissor, receptor, pagador, participant i usuari que consulta; no interpretar BILLING_NIF_CIF com a emissor ni IDPAG com a propietari de tots els documents.

## 7. Lot inicial de proves de sortida (identificador a vincular a fitxa i resultat)

| ID | Escenari i resultat esperat | RM/UC |
| --- | --- | --- |
| AT-001 | Mateixa K d'emissió/payload → mateixa factura; K igual i receptor diferent → 409, cap segona factura. | RM-014 / UC-01 |
| AT-002 | Mateix càrrec Redsys amb dues K → un únic fet econòmic; dues transferències reals d'igual import/data → dos fets legítims. | RM-009/012 / UC-02/23 |
| AT-003 | CHARGE 100 amb assignacions 80+80 → rebutjat; CHARGE 100 amb assignació 80 i 20 pendents → estat explícit i cap creació fictícia. | RM-010/015 / UC-02/104 |
| AT-004 | Pack de 2 inscripcions: 1 ingrés extern i atribució individual que suma l'import real, amb línies de factura correlacionades. | RM-011/021 / UC-15 |
| AT-005 | Empresa amb factura pendent: callback TPV crea el cobrament sobre la factura existent, cap segona factura encara que la K sigui nova. | RM-016/023 / UC-04/21 |
| AT-006 | POST anònim, tutor i alumne a issue/register → cap escriptura; empresa A no consulta factura B. | RM-017 / UC-62/99/102 |
| AT-007 | Participant grup base 100, descompte 20, total 90 → error abans d'emetre. | RM-020 / UC-91/118 |
| AT-008 | Tastet gratuït amb mailing desmarcat → alta acadèmica prevista, cap factura, cap CHARGE i consentiment separat. | RM-024 / UC-108/125 |
| AT-009 | Canvi curs amb import igual però concepte diferent: factura original preservada i tractament fiscal explícit; refund no automàtic. | RM-029 / UC-26/71 |
| AT-010 | XML/registre de cua manipulat → DEAD_LETTER i incidència sense SOAP; timeout post-SOAP → reconciliació abans de reenviar. | RM-031 / UC-09/77 |
| AT-011 | PDF metadata CREATED però bytes absents → no mostrar descarregable; URL aliena → denegació. | RM-030 / UC-78/80 |
| AT-012 | Pròrroga i baixa concurrent amb pagament recent → decisió acadèmica consistent, cap factura o ingrés fictici. | RM-033 / UC-95/96/124 |
| AT-013 | Crida real a cada punt d'entrada inventariat: únic emissor fiscal, resultat esperat a SIF i sincronització de l'estat llegat. | RM-001/018/034 / UC-64/68 |

## 8. Ordre d'execució i portes de tancament

**Fase A — font actual i cobertura (RM-001–008, RM-024 i RM-037 en paral·lel):** completar inventari i decisions, classificar totes les accions i variants, elaborar els diagrames d'activitat actual/final per cada pàgina i apartat de la web, actualitzar fitxes i diferenciar codi actual/final. **Porta A:** tota ruta activa crítica amb UC, dades, actor, variant, diagrama d'activitat pertinent i test definit; les absències consten com a candidates explícites, no com a «cobertes».

**Fase B — invariants i integritat (RM-009–017, RM-020):** identitat bancària entre K, conservació monetària, ledger individual, factura preexistent, autorització servidor i imports grup. **Porta B:** proves negatives i concurrents executades sense dobles factures, pagaments, imports inexistents o accessos impropis.

**Fase C — integració i cicle de vida (RM-018–029, RM-033):** tall ordenat dels escriptors llegats; operació comercial; canals/variants; rectificacions, documents i comunicacions. **Porta C:** les transicions de tots els fluxos reals es poden seguir per UUID_FACTURA, UUID_PAYMENT, ID_INSC/UUID_OPERATION amb prova visible i incidència recuperable.

**Fase D — AEAT, dades i operació (RM-030–036):** transport i respostes, documents, emissors, proves, migracions aplicades, backups/restauració, observabilitat i actualització documental. **Porta D:** resultat de proves en entorn definit, decisions fiscals aprovades, registre d'evidències i go/no-go explícit. El preflight tècnic per si sol NO autoritza producció.

**Plantilla de seguiment per cada RM:** Responsable: [pendent] · Estat: [obert] · UC afectats: [enllaços] · PR/commit: [pendent] · Prova executada/resultat: [pendent] · Evidència: [pendent] · Data de revisió: [pendent].

## 9. Definició de «fet» del document mestre

Aquest document deixa de ser un inventari de buits i passa a registre de tancament quan: (a) totes les funcionalitats ACTUALS dins l'abast, no només els 142 IDs, estan inventariades i traçades; (b) les variants de negoci i decisions del xat original estan reconciliades; (c) les fitxes expliquen **estat actual → modificacions → estat final** i referencien la classe i taula correctes; (d) tots els RM P0 aplicables tenen implementació i prova executada amb evidència; (e) els P1/P2 tenen decisió d'abast o planificació explícita; (f) els informes datats no s'utilitzen com a prova de versions posteriors; (g) la custòdia del xat original es manté privada.

**No s'afirma en aquesta versió:** que s'hagi executat la suite MySQL, que s'hagin inspeccionat els 192 recorreguts en producció, que les 142 fitxes s'hagin validat semànticament una per una, que s'hagi aprovat el règim fiscal de la botiga/SL o que el sistema ja pugui entrar en producció.
