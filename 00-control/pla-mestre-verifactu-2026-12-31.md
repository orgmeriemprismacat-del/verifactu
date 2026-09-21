> **Pla vigent — 16/09/2026:** vegeu [Pla reconciliat R2](pla-reconciliat-r2.md): 38 paquets, 944 h de mínim proposat i 1.332 h probables per a l'abast ampli. Les estimacions i correccions pendents d'aquest document es conserven com a antecedent.

# Pla mestre VERI*FACTU PrisMa — objectiu 31/12/2026

> **Bloc obligatori afegit:** VT-37, traça universal de qualsevol acció sobre pagaments. Vegeu `cobertura-registres-gestio-pagaments.md`, amb el contracte de `payment_action_event`, UC-86 i la matriu de registres del document 38 cap als paquets. La cobertura genèrica de BD i auditoria dels 36 blocs no demostrava que aquest contracte estigués completament dimensionat.

> **Estimació i organització afegides el 15/09/2026:** consulteu `pla-execucio-gestio-2026-12-31.md` i `estimacio-detallada-verifactu-2026-09-15.md`. Aquest document conserva l'inventari de 36 paquets; els apartats que indicaven hores encara no dimensionades descriuen l'estat anterior a la revisió de gestió. Les estimacions noves són inicials i no substitueixen verificació executable.

Revisió: 15/09/2026, després de la correcció de l'usuari.

## 1. Correcció del plantejament

La disponibilitat és de **tres dies entre setmana per VERI*FACTU**, amb els caps de setmana disponibles. Els altres dos dies entre setmana ja estan destinats a altres feines i no s'inclouen en aquest pla. L'usuari pot treballar més de vuit hores diàries, però encara no hi ha un nombre d'hores fix compromès.

L'objectiu continua sent que abans del 31/12 un client pugui comprar un curs i es puguin passar pagaments des de la intranet. Això requereix el sistema que sosté aquests recorreguts: dades, fiscalitat, operació, compatibilitat, gestió de fallades i manteniment de les funcions existents.

El primer pla reduïa massa aquesta feina. Es retiren les 360–450 h i la data de llançament del 14/12 com a compromís. Tampoc queda aprovada l'exclusió de packs, grups, regals, USOC, descomptes o fraccionaments. Primer s'inventarien; després es decideix què cal activar, conservar, substituir o ajornar.

## 2. Quina cobertura s'ha recuperat

El fitxer `inventari-fonts-pla-2026-09-15.md` conserva el detall de les fonts:

- Set exports locals seleccionats per data de modificació local més recent de cada tauler: control, casos d'ús, fitxes, desenvolupament/BD/API, interfícies, proves i SIF/pay.
- 25.706 targetes no arxivades en aquests exports. **No són 25.706 tasques pendents:** hi ha duplicats, feina feta, decisions, disseny i proves del mateix cas. Els exports tenen dates locals de juny, no s'ha verificat Trello en directe.
- 192 pantalles/apartats de la matriu de pantalles.
- 81 identificadors de cas o variant extrets de les taules del document 33, incloent els sufixos. No s'ha convertit el nom UC-01…UC-68 en un recompte simplificat.
- Inventari de canvis pendents, pla tècnic, procediments, matriu de casos, auditoria de còpies de codi i deutes de migració.

La cobertura de fonts és ara més àmplia. Encara **no és una auditoria executable de totes les rutes ni una reconciliació semàntica individual de totes les targetes**. El registre de pantalles identifica la revisió pendent de cada fila.

## 3. Estat comprovat i conseqüència per al pla

| Evidència local | Què implica |
| --- | --- |
| Dos worktrees: checkpoint `4c16b52` i Redsys `2742135`. | Cal reconciliar la feina; tenir el worker en una branca no implica tenir-lo integrat a tot el sistema. |
| L'auditoria 37 no detecta integració SIF completa als PHP candidats de web/intranet. | No es pot pressupostar només desplegar una còpia ja acabada. |
| `HashCalculator` calcula SHA-256 d'un JSON canònic i el pla tècnic l'anomena hash intern. | La conformitat de la huella i del registre AEAT requereix un bloc propi d'implementació/contrast i proves; no s'ha certificat en aquesta revisió. |
| `DocumentRepository::registerDocument()` insereix ruta/hash/metadades. | Generador real, persistència del fitxer, protecció d'accés, cua i recuperació són feina diferenciada. |
| `LegacySyncRepository` actualitza `FACTURA_RELACIONADA` i concatena `OBSERVACIONS`. | Falta demostrar actualització completa dels resums operatius i reintent idempotent; no basta dir «sync després del SIF». |
| `api/payments/register.php` llegeix JSON i crida el servei; no mostra autenticació al mateix fitxer. | Cal verificar la capa d'accés completa i implementar-la on falti. No s'afirma que producció sigui accessible sense autenticació: no s'ha inspeccionat la infraestructura. |
| L'inventari 11 declara pendents `RegistroAnulacion` i subsanacions. | Les correccions registrals no es poden absorbir en una única targeta de rectificatives. |
| Hi ha serveis manuals i scripts de test/preproducció. | Servei existent, pantalla integrada, prova executada i activació productiva són quatre estats diferents. |

## 4. Unitats de treball que han d'entrar al pressupost

Llegenda de tractament:

- **Base:** necessari per llançar els recorreguts objectiu amb garanties operatives del projecte.
- **Dependència:** cal resoldre'n l'impacte; la implementació exacta depèn dels canals/casos que continuïn actius. No és una exclusió.
- **Abast a decidir:** feina del projecte que es manté visible, però no es declara bloquejant de la primera venda sense justificar-ho.

Cap fila està marcada com a acabada. «Base preparada» vol dir reutilitzar i completar, no reprogramar-ho tot ni assignar-hi zero hores.

### A. Preparació, infraestructura i dades

| ID | Paquet i treball concret | Tractament | Criteri per tancar |
| --- | --- | --- | --- |
| VT-01 | Reconciliar fonts, branques i còpies actives; relacionar tasca amb fitxer, ruta, prova i versió; deduplicar feina per resultat. | Base | Un inventari mestre sense tasques orfes ni suma doble del mateix lliurable. |
| VT-02 | Fitxes funcionals necessàries: disparador, dades, permisos, decisions, estats, errors i efectes laterals de cada recorregut. | Base | Qui programa i qui opera comparteixen criteris verificables. La documentació funcional no s'ajorna en bloc. |
| VT-03 | Servidor, PHP 8.4/versió objectiu, extensions, dependències, DNS/TLS, cron/workers, correu, BD i secrets; separar test/preproducció/producció. | Base | Entorn reproduïble i comprovacions de requisits correctes. |
| VT-04 | Compatibilitat PHP del llegat, sessions, rutes/includes, JS/AJAX, CSS/Font Awesome i recursos de pantalles afectades. | Dependència | Els fluxos actius funcionen al servidor objectiu; cada canvi tècnic necessari queda estimat. Redisseny estètic separat. |
| VT-05 | Mapa real de BDs i SQL; UUID complet, DECIMAL, InnoDB, índexs, relacions i permisos; migracions i seeds repetibles. | Base | Migració assajada sobre esquema representatiu, dades consistents i temps de tall mesurat. |
| VT-06 | Històric i transició: numeració antiga, Associació/SL, `reg_pagament`, logs, documents antics, factures pendents i assignacions. | Base | Cap reemissió indeguda i consulta/cobrament de pendents definits. Importació massiva separable només amb convivència resolta. |

### B. Nucli fiscal i servei central

| ID | Paquet i treball concret | Tractament | Criteri per tancar |
| --- | --- | --- | --- |
| VT-07 | Completar/validar `issueInvoice`, `registerPayment`, càlculs monetaris, línies, dates, sèries, idempotència i assignacions parcials/múltiples. | Base preparada | Reintents i concurrència no alteren imports ni dupliquen operacions. |
| VT-08 | Registre fiscal i encadenament AEAT: format, camps, dates, huella, ordenació i validació amb casos coneguts; diferenciar hash intern. | Base | Registre reconstruïble i validat amb especificacions oficials durant implementació. |
| VT-09 | XML/XSD, transport AEAT, certificat, cua, respostes globals/per registre, errors, reintents, recuperació i observació. | Base | Enviament real de proves, resposta persistent i recuperació demostrada. |
| VT-10 | Rectificativa, anul·lació registral, subsanació, rebuig previ i nova alta; decisor i permisos d'operació. | Base | Cada tipus d'error té un circuit executable correcte, sense edició directa. |
| VT-11 | PDF, QR i XML conservats; generació des de snapshot, storage, hashes, cua, recuperació i descàrrega autoritzada. | Base | Document real immutable, consultable i recuperable. |
| VT-12 | API/adaptadors autenticats, autorització, validació del servidor, CSRF segons canal, errors, traça i identitat de l'operador. | Base | Només canals/usuaris autoritzats poden iniciar una operació. |

### C. Pagaments i compra efectiva

| ID | Paquet i treball concret | Tractament | Criteri per tancar |
| --- | --- | --- | --- |
| VT-13 | Integrar branca Redsys: intent, snapshot, signatura, callback curt, worker, locks, intents, resultat i incidències. | Base preparada en branca | Execució integrada amb TPV de proves i callback repetit sin duplicar. |
| VT-14 | Cicle de pagament: retorn del banc, 3DS quan afecti la integració, cancel·lació, timeout, intents abandonats, duplicats multicanal, terminals/entorns, tokens i caducitat. | Base | Estat comprensible al client i cap doble cobrament o confirmació falsa. Les variants suportades pel TPV s'han de provar. |
| VT-15 | Web/checkout: selecció, dades personals i fiscals, receptor diferent, preu i descompte al servidor, confirmació i enllaços pay. | Base | Compra completa del curs amb dades congelades i resultat visible. |
| VT-16 | Passar pagaments: cerca per criteri, resultats, modal, decisor, factura existent/nova, referència bancària, data, import, parcial, excés, multi-factura i incidència. | Base | Operador completa cada variant que continuï activa; registres i pantalles coherents. No és només afegir un botó. |
| VT-17 | Factura abans de cobrar, empresa/responsable i entitats: selecció d'inscripcions, receptor, emissió, bloqueig d'URLs individuals i cobrament posterior. | Dependència | Es pot cobrar el pendent sense nova factura ni doble pagament de l'alumne. |
| VT-18 | Sincronització i transició del llegat: `GENERAT`, `PAGAMENT`, dates/fraccions, factura relacionada, estat acadèmic dependent, reintent i conciliació. Retirada de callbacks antics. | Base | Fallada entre SIF i llegat recuperable, sense doble escriptura fiscal ni observacions repetides. |
| VT-19 | Portal d'alumne i consulta de factura: import pendent, URL, document, estats pendents/fallits i visibilitat empresa/grup. | Base | Client accedeix només al que correspon i no rep una petició de pagament ja satisfeta. |

### D. Operació real de la intranet

| ID | Paquet i treball concret | Tractament | Criteri per tancar |
| --- | --- | --- | --- |
| VT-20 | Correus i notificacions: mapa de crides/plantilles, destinataris, estats, enllaç segur, adjunts, outbox/reintents, no duplicació i avisos persistents. | Base | Confirmació/avís correcte per estat i recuperació si falla l'enviament. |
| VT-21 | Panell SIF i resum intranet: factures, registres, cues, documents, incidències, versions/configuració i accions autoritzades. | Base | Operador/tècnica poden saber què passa i recuperar fallades sense manipular taules. Analítica avançada separable. |
| VT-22 | Analitzar fitxer TPV/comprovar IDPAG: parser, importació, coincidències, duplicats, imports discrepants, cobraments sense factura i export/tancament. | Dependència | Conciliació demostrada amb fitxer representatiu; procediment alternatiu només si cobreix el mateix control. |
| VT-23 | Canvi de curs: mateix/major/menor import, diferència, motiu, descompte excepcional, preview, saldo, correus, inscripció i Moodle. | Dependència | Canvi existent pot operar-se sense editar una factura emesa ni perdre l'efecte acadèmic. |
| VT-24 | Baixa, despeses de gestió, devolució, saldo i compensació; rectificativa separada quan toca; retorn bancari i conciliació. | Base per incidències de la venda; variants segons negoci | El cas no queda resolt només registrant un REFUND si encara no s'ha executat/conciliat el retorn real. |
| VT-25 | Reclamacions, morositat, recordatoris i fraccionaments: saldo pendent, dates, correus, URLs i cobrament sobre factura existent. | Dependència | Processos actuals no generen una altra factura ni reclamen un import ja cobrat. |

### E. Variants comercials que no es poden oblidar

| ID | Paquet i treball concret | Tractament | Criteri per tancar |
| --- | --- | --- | --- |
| VT-26 | Descomptes: Alumne PrisMa, Carnet Jove, sensibles, promocions temporals, codis; validació, vigència, SQL i text fiscal/privacitat. | Dependència | Cada descompte ofert funciona en checkout i intranet i queda congelat al snapshot. |
| VT-27 | Taller, jornada i pack: preus, línies, agrupació IDPAG, descomptes, callbacks i pagament manual. | Dependència | Prova de cada variant activa, inclosa la distribució d'imports. |
| VT-28 | Grup/empresa: dades del responsable, participants, descomptes, una línia per participant, privacitat i altes/baixes posteriors. | Dependència | Venda i canvis posteriors complets sense exposició de factura empresarial a l'alumne. |
| VT-29 | Regal: comprador/beneficiari, pagament, factura, document regal, codi, bescanvi, caducitat i duplicats. | Dependència | Bescanvi genera l'efecte acadèmic correcte sense facturar una segona vegada. |
| VT-30 | USOC: afiliació, preus/aportacions, dades fiscals entitat, factura alumne i entitat, cobrament i conciliació. | Dependència | Imports i receptors consistents en tots dos circuits; no deixar la part d'entitat sense resoldre. |

### F. Regressió, governança i sortida

| ID | Paquet i treball concret | Tractament | Criteri per tancar |
| --- | --- | --- | --- |
| VT-31 | Regressió transversal: consulta/modifica alumne, dades de curs/pagament, Moodle, certificats, proformes, marca E_FACT i funcionalitats compartides amb col·laboradors. | Base per dependències afectades | Matriu de les 192 pantalles amb decisió explícita: canviar/conservar/retirar/ajornar i prova quan toca. No reprogramar totes per defecte. |
| VT-32 | Permisos i auditoria: rols, visibilitat, accés temporal, logs, exports, límits de consulta i protecció de secrets. | Base | Comprovacions al servidor, a BD i en descàrregues; evidència per rol. |
| VT-33 | Proves: fixtures reals anonimitzats, suite, contractes, concurrència, fallades parcials, casos per canal, navegador i acceptació de l'operador; correccions. | Base | Resultats sobre una versió integrada identificada i defectes bloquejants resolts. |
| VT-34 | Migració/desplegament: backups, restauració, recuperació, monitoratge, càrrega representativa, aturada segura, transició de URLs/callbacks i suport inicial. | Base | Assaig i restauració funcionals; activació sense perdre operacions pendents. |
| VT-35 | Expedient i operació: criteris fiscals validats, versió, declaració, vistiplau, manual, formació, responsables, evidències i decisió d'activació. | Base | Documentació vinculada a una versió real, no signatura usada com a prova de funcionalitat. |
| VT-36 | Assistent de fitxes i eines de suport; botiga/SL i circuits de proveïdors si es proposa ampliar-ne l'abast. | Abast a decidir | Separar necessitat funcional, automatització útil i ampliació de negoci. El generador no substitueix completar les fitxes necessàries de VT-02. |

## 5. Dependències i ordre d'execució

1. **Base comuna:** VT-01/02 → VT-03/04/05/06. Es poden avançar dades i fitxes mentre es resolen accessos.
2. **Nucli usable:** VT-07/08/09/10/11/12. AEAT, documents i correccions són blocs propis, no una única setmana de remats.
3. **Canals:** VT-13/14/15/16/17/18/19. Connectar primer un cas de prova complet ajuda a validar contractes, però no certifica que la resta del projecte estigui coberta.
4. **Operació i variants:** VT-20…30 segons les dependències reals; assegurar la regressió VT-31 i els permisos VT-32 durant la implementació.
5. **Tancament:** VT-33/34/35; les proves per paquet es fan abans i l'acceptació integrada es fa al final.

Documents i correus comparteixen infraestructura; hash, numeració i permisos també. S'estimen una vegada com a implementació comuna i després s'afegeixen integració i proves de cada variant. No se sumen les targetes de disseny/programació/prova com si fossin tres implementacions.

## 6. Capacitat disponible, sense confondre-la amb esforç pendent

Del 15/09 al 31/12 hi ha aproximadament 15,4 setmanes. Amb cinc dies disponibles per setmana, les magnituds orientatives són:

| Hores per dia disponible | Hores brutes/setmana | Total brut aproximat | Capacitat planificable amb un 25% de reserva |
| --- | ---: | ---: | ---: |
| 8 h | 40 h | 617 h | 463 h |
| 9 h | 45 h | 694 h | 521 h |
| 10 h | 50 h | 771 h | 579 h |

És una extrapolació, no el recompte exacte del calendari: falta fixar els tres dies concrets, absències i festius treballats. La reserva del 25% és una hipòtesi de planificació per interrupcions i variació, no inclou una segona vegada les proves ja estimades dins les tasques. Esperar el banc o un certificat pot moure dates sense consumir totes aquestes hores.

No es pot concloure que el projecte cap en 463/521/579 h només perquè aquesta sigui la disponibilitat. **Les hores pendents de VT-01…36 encara no estan dimensionades amb prou evidència.** S'ha evitat substituir les 360 h retirades per una altra xifra arbitrària.

## 7. Calendari de control cap al 31/12

Aquest calendari fixa portes de control; la càrrega d'implementació només es podrà assignar després de VT-01/02. No pressuposa que tots els paquets caben dins aquestes dates.

| Període | Treball/decisió | Resultat que permet avançar |
| --- | --- | --- |
| 15–27/09 | Reconciliar feina real, matriu de pantalles/casos, entorn executable, dependències i estimació per paquet. | Pressupost d'hores baix/probable/alt i abast concret de llançament. Aquesta és la primera porta, no un mes d'implementació ja promès. |
| 28/09–25/10 | Prioritzar base comuna, integritat fiscal, AEAT/documents i primer recorregut vertical en preproducció. | Contractes i riscos principals demostrats amb codi i proves; reestimar cada setmana amb hores restants. |
| 26/10–22/11 | Completar canals, operació i variants exigides pel tall, amb regressió i permisos. | Tots els casos activables tenen prova; no només compra de curs normal. |
| 23/11–13/12 | Integració final, correccions, migració/restore, acceptació, expedient i formació. | Versió candidata sense problemes bloquejants. |
| 14–20/12 | Finestra objectiu d'activació, només si s'ha superat la porta anterior. | Primera operació real controlada i conciliació del sistema. |
| 21–31/12 | Reserva de correcció i estabilització. | Objectiu final complert amb seguiment i operació possible. |

Si el pressupost reconciliat supera capacitat, cal explicitar la diferència: hores addicionals realment disponibles, suport assignat o reducció funcional concreta. No es resol escurçant silenciosament proves, migració o correccions fiscals ni retirant variants encara actives.

## 8. Primera tanda de treball concreta

Distribuir-la sobre els tres dies de VERI*FACTU i el cap de setmana, sense usar els dos dies reservats a altres feines:

1. **Dia VERI*FACTU A:** fixar versió/còpies actives; separar implementat local, integrat, provat i pendent. Identificar les dependències més incertes.
2. **Dia VERI*FACTU B:** desglossar Passar pagaments i compra per variants, fitxers, SQL, pantalles, efectes Moodle/correu i proves. Recollir gestions de servidor/TPV/certificat.
3. **Dia VERI*FACTU C:** revisar el motor fiscal real, API, documents i sincronització; dimensionar cadascun amb criteri de finalització.
4. **Dissabte:** classificar les pantalles afectades i les variants de llançament; contrastar amb les targetes/fitxes i eliminar dobles recomptes de lliurables.
5. **Diumenge:** consolidar hores baix/probable/alt per paquet, dependències externes, capacitat setmanal i càrrega fins al 31/12; preparar la següent tanda amb marge.

La primera tanda és feina tècnica de planificació/verificació. No cal esperar a acabar una neteja exhaustiva de totes les targetes de Trello per començar els blocs inequívocs del nucli.

## 9. Criteri de finalització del pla i del producte

**El pla es pot considerar dimensionat** quan cada paquet activable té hores restants, dependències, responsable, criteri de finalització, prova i evidència de l'estat actual, i totes les pantalles/casos tenen una disposició sense buits. Cal confirmar-ho sobre fonts actuals, no sobre l'estat de juny sense contrast.

**El producte es pot activar** quan la compra i els pagaments de la intranet funcionen, les variants que es mantinguin actives estan cobertes, no hi ha doble emissió/cobrament, els documents i registres es conserven, les fallades es recuperen i l'operador pot treballar. Cal incloure l'accés al curs i els efectes acadèmics que depenguin del pagament, no quedar-se en l'aprovació bancària.

## 10. Fonts de contrast

- `inventari-fonts-pla-2026-09-15.md`: set exports, casos, pantalles i inventari de canvis.
- `trello-targetes-petites-curades-prisma.md`: infraestructura, decisions, programació i proves, incloses tasques INF-001…006.
- Documents 10, 11, 12 i 13: procediments, canvis, pantalles i deutes de migració.
- Documents 26, 29, 30, 33 i 37: casos, pla tècnic, Trello, casos ampliats i comparativa de còpies.
- `sif/src/Domain/HashCalculator.php`, `sif/src/Repository/DocumentRepository.php`, `sif/src/Repository/LegacySyncRepository.php`, `sif/public/api/payments/register.php`: revisió estàtica acotada.

No s'han executat proves ni modificat codi de producció en aquesta revisió. La següent validació executable no es pot donar per feta amb aquesta documentació.
