> **Pla vigent — 16/09/2026:** vegeu [Pla reconciliat R2](pla-reconciliat-r2.md): 38 paquets, 944 h de mínim proposat i 1.332 h probables per a l'abast ampli. Les estimacions i correccions pendents d'aquest document es conserven com a antecedent.

# Estimació detallada del treball restant — 15/09/2026

> **Base incompleta per traça universal:** s'ha detectat que els 36 paquets no pressupostaven explícitament `payment_action_event` i `PaymentActionGateway`. S'afegeix VT-37 a `cobertura-registres-gestio-pagaments.md`. Els totals següents es conserven com a base pendent de reconciliar, no com a estimació de cobertura íntegra del document 38/UC-86.

> **Disponibilitat corregida per l'usuari:** 15 h cadascun dels tres dies de VERI*FACTU i 30 h totals el cap de setmana: **75 h brutes/setmana**. Amb reserva del 25%, **56,25 h netes/setmana** i **855–877,5 h fins al 31/12**. La càrrega estimada dels paquets no canvia només perquè augmenti la disponibilitat. El calendari vigent és `pla-execucio-75h-2026-12-31.md`; les files de 8/9/10 h següents queden com a comparació d'escenaris.

## Mètode i límits

Estimació inicial de gestió basada en el codi i documents locals revisats, amb reutilització dels serveis, migracions i branca Redsys existents. Les xifres són judici de planificació, no hores mesurades ni resultat d'una auditoria executable de cada funció. La confiança és baixa o mitjana; no s'assigna cap percentatge probabilístic de lliurament.

Cada paquet inclou completar el contracte específic, implementar o adaptar, integrar al seu canal, provar localment i deixar evidència. VT-33 només afegeix acceptació i regressió entre sistemes, sense tornar a sumar les proves locals. VT-31 cobreix regressió de funcionalitats antigues; VT-04, les correccions de compatibilitat. VT-03 prepara entorns; VT-34 assaja i executa el tall i la recuperació. VT-12 implementa accés comú; VT-32 verifica rols, auditoria i exportació transversal.

Les tres estimacions de l'abast ampli assumeixen respectivament:
- **Favorable:** reutilització bona, dades representatives accessibles i poques regressions.
- **Probable:** completar les integracions i mancances conegudes, amb correccions locals normals.
- **Advers:** més incompatibilitats, mappings i criteris que cal refer; no inclou qualsevol ampliació imaginable.

No s'han estimat hores multiplicant el nombre de targetes, pantalles, línies de codi o tests. La suma adversa no és una previsió estadística: representa un escenari de desviació elevada en molts paquets simultàniament. Les esperes externes són durada de calendari i es controlen separadament.

**Abast ampli pressupostat:** completar els circuits de venda i operació descrits a VT-01…35, reutilitzant el sistema actual, més delimitar VT-36. No inclou construir una botiga/SL nova, tot un SIF de proveïdors, un redisseny estètic general ni completar qualsevol ampliació futura de l'assistent de fitxes. Aquests canvis requeririen pressupost propi.

**Abast limitat:** proposta de simplificació funcional, encara no aprovada, explicada fila a fila. Les hores són un escenari central d'aquest abast diferent, no el límit favorable del projecte ampli. Els zeros són funcions no activades en la proposta; no volen dir feina feta ni absència d'obligacions pendents.

## Resum per àrea

| Àrea | Favorable h | Probable h | Advers h | Limitat proposat h |
| --- | ---: | ---: | ---: | ---: |
| Preparació, servidor, compatibilitat i dades | 112 | 180 | 304 | 116 |
| Nucli fiscal, AEAT, correccions, documents i API | 132 | 216 | 352 | 200 |
| Redsys, compra, intranet, sincronització i portal | 132 | 204 | 340 | 164 |
| Operació, correus, panell, conciliació i canvis | 116 | 188 | 304 | 92 |
| Descomptes i variants comercials | 92 | 144 | 248 | 12 |
| Regressió, seguretat, acceptació, desplegament i expedient | 108 | 176 | 296 | 132 |
| Delimitació d'eines i ampliacions | 4 | 8 | 16 | 4 |
| **Total** | **696** | **1116** | **1860** | **720** |

Les sumes exactes permeten controlar canvis; en comunicació de previsió s'ha de parlar d'unes **1.100 h** per l'abast ampli i **720 h** per la proposta limitada. No hi ha precisió real d'una hora.

## Pressupost dels 36 paquets

Els criteris de finalització i l'abast complet es troben a `pla-mestre-verifactu-2026-12-31.md`.

| ID | Paquet | Favorable h | Probable h | Advers h | Limitat proposat h | Confiança |
| --- | --- | ---: | ---: | ---: | ---: | --- |
| VT-01 | Reconciliar codi i fonts | 12 | 20 | 32 | 12 | mitjana |
| VT-02 | Fitxes funcionals dels fluxos | 20 | 32 | 48 | 16 | mitjana |
| VT-03 | Servidor i entorns | 16 | 24 | 40 | 24 | mitjana |
| VT-04 | Compatibilitat del llegat | 24 | 40 | 72 | 20 | baixa |
| VT-05 | BD i migracions | 20 | 32 | 56 | 24 | baixa |
| VT-06 | Històric i transició | 20 | 32 | 56 | 20 | baixa |
| VT-07 | Facturació i cobraments del nucli | 16 | 24 | 40 | 24 | mitjana |
| VT-08 | Registres i encadenament AEAT | 20 | 32 | 56 | 32 | baixa |
| VT-09 | XML, certificat i cua AEAT | 32 | 56 | 88 | 56 | baixa |
| VT-10 | Rectificatives i correccions registrals | 24 | 40 | 64 | 32 | baixa |
| VT-11 | PDF, QR, XML i custòdia | 24 | 40 | 64 | 32 | mitjana |
| VT-12 | API, adaptadors i autorització | 16 | 24 | 40 | 24 | mitjana |
| VT-13 | Integrar Redsys asíncron | 12 | 20 | 36 | 20 | mitjana |
| VT-14 | Cicle de pagament i enllaços | 16 | 24 | 40 | 20 | mitjana |
| VT-15 | Web i checkout | 24 | 36 | 56 | 28 | mitjana |
| VT-16 | Passar pagaments | 32 | 48 | 80 | 32 | mitjana |
| VT-17 | Factura prèvia i entitats | 16 | 24 | 40 | 16 | mitjana |
| VT-18 | Sincronització i retirada del llegat | 20 | 32 | 56 | 32 | baixa |
| VT-19 | Portal alumne i consulta | 12 | 20 | 32 | 16 | mitjana |
| VT-20 | Correus i notificacions | 20 | 32 | 48 | 20 | mitjana |
| VT-21 | Panell operatiu SIF | 24 | 40 | 64 | 20 | mitjana |
| VT-22 | Conciliació TPV | 16 | 28 | 48 | 12 | mitjana |
| VT-23 | Canvis de curs | 24 | 40 | 64 | 12 | baixa |
| VT-24 | Baixa, devolució i saldo | 16 | 24 | 40 | 20 | mitjana |
| VT-25 | Reclamacions i fraccionaments | 16 | 24 | 40 | 8 | mitjana |
| VT-26 | Descomptes | 20 | 32 | 56 | 12 | baixa |
| VT-27 | Tallers, jornades i packs | 16 | 24 | 40 | 0 | mitjana |
| VT-28 | Grups i participants | 20 | 32 | 56 | 0 | baixa |
| VT-29 | Regals i bescanvi | 16 | 24 | 40 | 0 | mitjana |
| VT-30 | USOC | 20 | 32 | 56 | 0 | baixa |
| VT-31 | Regressió de funcions existents | 24 | 40 | 72 | 24 | baixa |
| VT-32 | Permisos, auditoria i export | 16 | 24 | 40 | 20 | mitjana |
| VT-33 | Acceptació integrada i correccions | 32 | 56 | 88 | 40 | mitjana |
| VT-34 | Desplegament, restauració i seguiment | 20 | 32 | 56 | 28 | mitjana |
| VT-35 | Expedient i formació | 16 | 24 | 40 | 20 | mitjana |
| VT-36 | Delimitar eines i ampliacions | 4 | 8 | 16 | 4 | mitjana |
| **TOTAL** | | **696** | **1116** | **1860** | **720** | |

## Hipòtesis i justificació per paquet

### VT-01 — Reconciliar codi i fonts

- **Estimació àmplia:** 12/20/32 h (favorable/probable/advers).
- **Base del càlcul:** Identificar versió activa, reconciliar la branca Redsys i traçar lliurables; no netejar exhaustivament 25.706 targetes.
- **Proposta limitada, 12 h:** Reconciliació centrada en els canals del tall.

### VT-02 — Fitxes funcionals dels fluxos

- **Estimació àmplia:** 20/32/48 h (favorable/probable/advers).
- **Base del càlcul:** Reutilitzar fitxes existents i completar decisions/acceptació; més feina si les variants no tenen contracte.
- **Proposta limitada, 16 h:** Fitxes dels circuits activats i excepcions essencials.

### VT-03 — Servidor i entorns

- **Estimació àmplia:** 16/24/40 h (favorable/probable/advers).
- **Base del càlcul:** Configurar servidor, PHP/extensions, test/preproducció, workers, correu i secrets; accés disponible com a hipòtesi.
- **Proposta limitada, 24 h:** Mateix entorn necessari.

### VT-04 — Compatibilitat del llegat

- **Estimació àmplia:** 24/40/72 h (favorable/probable/advers).
- **Base del càlcul:** Revisar i corregir PHP, sessions, includes, AJAX i recursos afectats; nombre real de regressions desconegut.
- **Proposta limitada, 20 h:** Compatibilitat de rutes afectades; altres canvis amb regressió controlada.

### VT-05 — BD i migracions

- **Estimació àmplia:** 20/32/56 h (favorable/probable/advers).
- **Base del càlcul:** Reutilitzar migracions SIF; contrastar esquema real, imports, UUID, índexs i assaig.
- **Proposta limitada, 24 h:** Migracions imprescindibles per operar.

### VT-06 — Històric i transició

- **Estimació àmplia:** 20/32/56 h (favorable/probable/advers).
- **Base del càlcul:** Resoldre pendents, numeració antiga, relacions i consulta; el volum i qualitat reals de dades són incerts.
- **Proposta limitada, 20 h:** Convivència històrica i pendents; sense importació massiva.

### VT-07 — Facturació i cobraments del nucli

- **Estimació àmplia:** 16/24/40 h (favorable/probable/advers).
- **Base del càlcul:** Serveis existents: completar validacions, imports, idempotència, assignacions i proves específiques.
- **Proposta limitada, 24 h:** Mateix nucli segur.

### VT-08 — Registres i encadenament AEAT

- **Estimació àmplia:** 20/32/56 h (favorable/probable/advers).
- **Base del càlcul:** Hash intern existent; falta acreditar/construir serialització i encadenament definitius de registres.
- **Proposta limitada, 32 h:** Mateix tractament fiscal.

### VT-09 — XML, certificat i cua AEAT

- **Estimació àmplia:** 32/56/88 h (favorable/probable/advers).
- **Base del càlcul:** Implementació real de transport, XML, certificat, worker, respostes i recuperació no demostrada.
- **Proposta limitada, 56 h:** Mateixa remissió real i recuperació.

### VT-10 — Rectificatives i correccions registrals

- **Estimació àmplia:** 24/40/64 h (favorable/probable/advers).
- **Base del càlcul:** Reutilitzar rectificativa interna; completar anul·lacions, subsanacions, estats i coherència transaccional.
- **Proposta limitada, 32 h:** Tots els tipus necessaris, interfície tècnica restringida.

### VT-11 — PDF, QR, XML i custòdia

- **Estimació àmplia:** 24/40/64 h (favorable/probable/advers).
- **Base del càlcul:** Metadades existents; construir generadors, storage protegit, cua, recuperació i descàrrega.
- **Proposta limitada, 32 h:** Documents funcionals amb una plantilla bàsica.

### VT-12 — API, adaptadors i autorització

- **Estimació àmplia:** 16/24/40 h (favorable/probable/advers).
- **Base del càlcul:** Endpoints bàsics existents; completar contracte autenticat i permisos de canal, errors i tests.
- **Proposta limitada, 24 h:** Mateix control d'accés.

### VT-13 — Integrar Redsys asíncron

- **Estimació àmplia:** 12/20/36 h (favorable/probable/advers).
- **Base del càlcul:** Reutilitzar branca amb worker i intents; integrar, configurar i repetir proves del conjunt.
- **Proposta limitada, 20 h:** Mateixa integració Redsys.

### VT-14 — Cicle de pagament i enllaços

- **Estimació àmplia:** 16/24/40 h (favorable/probable/advers).
- **Base del càlcul:** Caducitat, retorns, intents múltiples, terminals/entorns, doble pagament i experiència pendent/fallida.
- **Proposta limitada, 20 h:** Mateixes garanties; menys variants d'experiència.

### VT-15 — Web i checkout

- **Estimació àmplia:** 24/36/56 h (favorable/probable/advers).
- **Base del càlcul:** Adaptar checkout real, confirmació fiscal i preus del servidor; motor i documents comptats en altres paquets.
- **Proposta limitada, 28 h:** Checkout de curs amb receptor fiscal.

### VT-16 — Passar pagaments

- **Estimació àmplia:** 32/48/80 h (favorable/probable/advers).
- **Base del càlcul:** Cerca, modal, decisor, referència, import, parcial/múltiple, resultats i variants; reutilitza motor.
- **Proposta limitada, 32 h:** Curs, factura existent, transferència i parcial; multi-factura assistida validada.

### VT-17 — Factura prèvia i entitats

- **Estimació àmplia:** 16/24/40 h (favorable/probable/advers).
- **Base del càlcul:** Integrar servei previ, gestió de receptor, entitats i bloqueig d'enllaços individuals.
- **Proposta limitada, 16 h:** Cobrar factures prèvies i protegir URLs; alta d'empresa assistida.

### VT-18 — Sincronització i retirada del llegat

- **Estimació àmplia:** 20/32/56 h (favorable/probable/advers).
- **Base del càlcul:** Sync actual limitada; actualitzar resums necessaris, reintentar sense duplicar i retirar escriptures paral·leles.
- **Proposta limitada, 32 h:** Mateixa coherència i transició.

### VT-19 — Portal alumne i consulta

- **Estimació àmplia:** 12/20/32 h (favorable/probable/advers).
- **Base del càlcul:** Adaptar consulta, URL i visibilitat de documents; no construir de zero tot el portal.
- **Proposta limitada, 16 h:** Consulta essencial.

### VT-20 — Correus i notificacions

- **Estimació àmplia:** 20/32/48 h (favorable/probable/advers).
- **Base del càlcul:** Reutilitzar plantilles però canviar crides/estats, entrega fiable, destinatari i avisos.
- **Proposta limitada, 20 h:** Confirmació i avisos essencials; altres campanyes no refetes.

### VT-21 — Panell operatiu SIF

- **Estimació àmplia:** 24/40/64 h (favorable/probable/advers).
- **Base del càlcul:** Consulta i accions operatives per factura/cua/document/error/versió; sense analítica avançada.
- **Proposta limitada, 20 h:** Panell operatiu essencial, sense dashboard avançat.

### VT-22 — Conciliació TPV

- **Estimació àmplia:** 16/28/48 h (favorable/probable/advers).
- **Base del càlcul:** Adaptar importador i casos de conciliació amb fitxer real; no repetir lògica de cobrament.
- **Proposta limitada, 12 h:** Conciliació assistida amb informe, sense importador automàtic complet.

### VT-23 — Canvis de curs

- **Estimació àmplia:** 24/40/64 h (favorable/probable/advers).
- **Base del càlcul:** Orquestració del canvi, diferències, motius, efectes acadèmics i pantalla; reutilitza saldo/rectificativa.
- **Proposta limitada, 12 h:** Procediment assistit verificat; sense automatització completa de variants.

### VT-24 — Baixa, devolució i saldo

- **Estimació àmplia:** 16/24/40 h (favorable/probable/advers).
- **Base del càlcul:** Reutilitzar serveis; completar circuit operatiu, retorn real i conciliació, no només moviment intern.
- **Proposta limitada, 20 h:** Procediment assistit amb retorn econòmic i correcció traçats.

### VT-25 — Reclamacions i fraccionaments

- **Estimació àmplia:** 16/24/40 h (favorable/probable/advers).
- **Base del càlcul:** Connectar saldo, venciments, recordatoris i pagament posterior; plantilles comunes a VT-20.
- **Proposta limitada, 8 h:** Conservar/controlar scripts necessaris, sense redissenyar campanyes.

### VT-26 — Descomptes

- **Estimació àmplia:** 20/32/56 h (favorable/probable/advers).
- **Base del càlcul:** Validacions i SQL per les famílies documentades; motor de descompte i snapshot compartits.
- **Proposta limitada, 12 h:** Només famílies de descompte seleccionades per al tall.

### VT-27 — Tallers, jornades i packs

- **Estimació àmplia:** 16/24/40 h (favorable/probable/advers).
- **Base del càlcul:** Adaptació específica de variants i packs; serveis base ja preparats, no reescriure motor comú.
- **Proposta limitada, 0 h:** No activar aquestes noves vendes; pendents han de tenir solució expressa.

### VT-28 — Grups i participants

- **Estimació àmplia:** 20/32/56 h (favorable/probable/advers).
- **Base del càlcul:** SQL/responsable/participants, privacitat i canvis posteriors; reutilitza emissió i cobrament.
- **Proposta limitada, 0 h:** No activar noves vendes de grup; pendents han de tenir solució expressa.

### VT-29 — Regals i bescanvi

- **Estimació àmplia:** 16/24/40 h (favorable/probable/advers).
- **Base del càlcul:** Compra, document regal, codi, bescanvi i errors; reutilitza pagament/factura comuns.
- **Proposta limitada, 0 h:** No activar noves vendes de regal; bescanvis/obligacions pendents han de tenir solució expressa.

### VT-30 — USOC

- **Estimació àmplia:** 20/32/56 h (favorable/probable/advers).
- **Base del càlcul:** Afiliació i dues parts de facturació, dades d'entitat i integració; serveis base existents.
- **Proposta limitada, 0 h:** No activar noves vendes USOC; pendents han de tenir solució expressa.

### VT-31 — Regressió de funcions existents

- **Estimació àmplia:** 24/40/72 h (favorable/probable/advers).
- **Base del càlcul:** Classificar 192 apartats i provar dependències de Moodle/certificats/fitxes; no són 192 pantalles a reprogramar.
- **Proposta limitada, 24 h:** Regressió dels fluxos afectats; disposició de la resta.

### VT-32 — Permisos, auditoria i export

- **Estimació àmplia:** 16/24/40 h (favorable/probable/advers).
- **Base del càlcul:** Revisió transversal de rols, BD, auditoria i export essencial; autenticació comuna a VT-12.
- **Proposta limitada, 20 h:** Mateixos controls essencials; consulta/export bàsic.

### VT-33 — Acceptació integrada i correccions

- **Estimació àmplia:** 32/56/88 h (favorable/probable/advers).
- **Base del càlcul:** Només regressió conjunta, UAT i correccions transversals; proves locals ja incloses en cada paquet.
- **Proposta limitada, 40 h:** Acceptació completa de l'abast reduït.

### VT-34 — Desplegament, restauració i seguiment

- **Estimació àmplia:** 20/32/56 h (favorable/probable/advers).
- **Base del càlcul:** Assaig de tall, backup/restore, configuració final, recuperació, operació inicial; entorns base a VT-03.
- **Proposta limitada, 28 h:** Mateix restore/tall segur, operació acotada.

### VT-35 — Expedient i formació

- **Estimació àmplia:** 16/24/40 h (favorable/probable/advers).
- **Base del càlcul:** Reutilitzar documents; completar versió/evidències, criteris, manual i formació.
- **Proposta limitada, 20 h:** Mateixa documentació exigida per la versió activada.

### VT-36 — Delimitar eines i ampliacions

- **Estimació àmplia:** 4/8/16 h (favorable/probable/advers).
- **Base del càlcul:** Només delimitar assistent/botiga/SL/proveïdors i recollir decisions; no pressuposta una ampliació completa.
- **Proposta limitada, 4 h:** Només delimitació.

## Capacitat i projecció amb una sola persona

Tres dies entre setmana i els dos dies de cap de setmana, des del 15/09 fins al 31/12 inclosos. S'han calculat les deu combinacions possibles de tres dies laborals: **76–78 dies disponibles**. Es reserva el 25% de les hores brutes per coordinació habitual, interrupcions i variació; no es torna a restar aquest marge als totals d'esforç.

Aquesta reserva no és una prova que qualsevol desviació adversa quedi coberta. Les hores de proves necessàries ja estan incloses als paquets. Les previsions posteriors al 31/12 assumeixen que es manté la mateixa dedicació també el 2027, sense vacances ni festius addicionals.

| Hores/dia disponible | Brut/setmana | Capacitat fins al 31/12 | Final limitat de 720 h | Final ampli de 1116 h |
| --- | ---: | ---: | --- | --- |
| 8 h | 40 h | 456–468 h | 2027-02-28 a 2027-03-01 | 2027-06-01 a 2027-06-03 |
| 9 h | 45 h | 513–526.5 h | 2027-02-10 a 2027-02-12 | 2027-05-04 a 2027-05-06 |
| 10 h | 50 h | 570–585 h | 2027-01-26 a 2027-01-28 | 2027-04-10 a 2027-04-11 |
| **15 h, disponibilitat indicada** | **75 h** | **855–877,5 h** | **2026-12-12 a 2026-12-13** | **2027-01-31 a 2027-02-01** |

Les dates provenen d'esforç/capacitat: són **projeccions de càrrega**, no dates contractuals ni un calendari crític validat. Si una dependència externa arriba tard, la finalització es pot moure encara que les hores no augmentin.

Amb 10 h/dia:
- Proposta limitada: dèficit **135–150 h netes** fins al 31/12.
- Abast ampli probable: dèficit **531–546 h netes**.
- Fins i tot el favorable ampli de 696 h supera la capacitat de 570–585 h.
- Cobrir 720 h sense ajuda exigiria aproximadament **12,3–12,6 h brutes per dia disponible** durant tot el període, amb la mateixa reserva. No és la base recomanada del pla.
- Cobrir 1116 h exigiria aproximadament **19,1–19,6 h/dia disponible**; no és una opció operativa realista.

## Control de canvis de l'estimació

Cada diumenge s'anoten hores consumides i, independentment, hores que queden. No es calcula el pendent restant simplement com a pressupost menys hores gastades. Quan hi ha evidència de reutilització millor o d'un buit nou, es modifica la fila amb data i motiu.

Recalcular en acabar les primeres dues setmanes o abans si es detecta un canvi important. Qualsevol augment superior al 20% d'un paquet probable, bloqueig extern superior a cinc dies de calendari o pèrdua d'una setmana de capacitat obliga a revisar les fites.

La base editable de les xifres és `estimacio-hores-verifactu-2026-09-15.json`. Les estimacions de 360–450 h de la primera proposta continuen retirades; aquestes xifres noves estan vinculades als 36 paquets i les hipòtesis anteriors.
