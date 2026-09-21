# Revisió de gestió i pla d'execució VERI*FACTU

> **ESCENARI DE CAPACITAT SUPERAT.** L'usuari ha concretat 75 h/setmana, no les 50 h utilitzades aquí. La necessitat de suport i les projeccions d'aquest document no són la recomanació vigent. Consulteu `pla-execucio-75h-2026-12-31.md`. Les estimacions per paquet es mantenen; canvien capacitat, calendari i viabilitat.

Data de tall: 15/09/2026. Objectiu demanat: compra de curs i Passar pagaments operatius abans del 31/12/2026.

## 1. Dictamen de gestió

**Amb una sola persona, tres dies entre setmana i caps de setmana, l'abast ampli inventariat no té un calendari defensable per al 31/12.** El problema és la relació entre treball pendent i capacitat, no només l'ordre de les targetes.

La revisió dona una estimació inicial de **1.116 h pendents** per l'abast ampli, amb escenaris favorable/advers de **696–1.860 h**. Es reutilitza el que ja està programat. No són hores mesurades ni una promesa de precisió: hi ha incertesa especialment en compatibilitat del llegat, dades, AEAT i integració de pantalles.

La proposta limitada descrita a l'estimació suma **720 h**. Manté el nucli fiscal i la capacitat d'operar i corregir les vendes activades; simplifica automatitzacions i no activa totes les variants. **Aquesta reducció no està aprovada.** No s'aplica cap bloqueig, retirada de funcionalitat ni canvi a producció amb aquest document.

Amb cinc dies de 10 h i un 25% de reserva, hi ha **570–585 h planificables** fins al 31/12. Les 10 h/dia són un escenari de càlcul, no una dedicació confirmada de l'usuari.

### Escenaris

| Escenari | Treball estimat | Capacitat/condició | Lectura de gestió |
| --- | ---: | --- | --- |
| Abast ampli, una persona | 1116 h probables | 570–585 h amb 50 h brutes/setmana | No cap. Projecció de càrrega: abril de 2027 si es manté la dedicació. |
| Proposta limitada, una persona | 720 h | Mateixa capacitat | Tampoc cap en l'estimació central. Projecció: finals de gener de 2027. |
| Proposta limitada amb suport tècnic qualificat | 720 h + 32 h de transferència/integració addicional | 260 h netes de suport; 492 h de la responsable | Pot donar una finestra de llançament al desembre si es confirma abast, disponibilitat i dependències. |
| Abast ampli amb aquest mateix suport | 1116 + 32 − 260 = 888 h a càrrec de la responsable | 570–585 h disponibles | Continua sense cabre. Afegir una persona parcial no resol tot l'abast. |

**Recomanació:** començar immediatament la base comuna i gestionar el 31/12 com un objectiu condicionat a una decisió d'abast i recursos abans del 27/09. Si es manté una sola persona i tot l'abast, s'ha de moure el termini; no mantenir-lo com a compromís mentre el pressupost diu el contrari.

## 2. On és el pes real del treball

| Àrea | Hores probables, abast ampli | Per què pesa |
| --- | ---: | --- |
| Preparació, servidor, compatibilitat i dades | 180 | Canvi d'entorn, codi legacy, esquema real i convivència amb historials. |
| Nucli fiscal, AEAT, correccions, documents i API | 216 | Encara cal demostrar serveis fiscals i documents reals, amb correccions i fallades. |
| Redsys, compra, intranet, sincronització i portal | 204 | Connectar serveis preparats als punts reals i conservar coherència entre sistemes. |
| Operació, notificacions, conciliació i canvis | 188 | El personal ha de poder cobrar, consultar, reclamar i corregir sense duplicar ni perdre dades. |
| Descomptes i variants comercials | 144 | SQL, receptors, imports, privacitat i efectes diferents per cada variant activa. |
| Regressió, seguretat, acceptació, desplegament i expedient | 176 | Validació conjunta, recuperació, tall real, permisos i capacitat d'operar. |
| Delimitar eines i ampliacions | 8 | Decidir separadament suport de fitxes, botiga/SL/proveïdors; no construir-los tots. |
| **Total** | **1116** | |

La taula completa amb favorable/probable/advers, justificació i proposta limitada per cadascun dels 36 paquets és a `estimacio-detallada-verifactu-2026-09-15.md`. Les xifres editables són a `estimacio-hores-verifactu-2026-09-15.json`.

## 3. Regles d'abast per al llançament proposat

La proposta limitada **no és simplement curs + transferència**. Inclou:

- entorn i dades per operar sense barrejar proves, historials ni numeracions;
- motor fiscal, AEAT, correccions registrals i rectificatives necessàries;
- PDF/QR/XML reals, conservació i accés;
- Redsys, dades fiscals, notificacions i gestió de retorns/error/duplicat;
- Passar pagaments, factura prèvia, pagament parcial i tractament assistit de multi-factura;
- sincronització, portal d'alumne i efectes acadèmics del pagament;
- correus/avisos, panell operatiu, conciliació assistida;
- procediment segur de canvi, baixa, devolució i saldo;
- permisos, regressió, restauració, expedient i formació.

Simplificacions proposades que necessiten una decisió de negoci explícita:

1. Panell funcional senzill i una plantilla de factura, sense analítica ni redisseny general.
2. Conciliació assistida al principi en lloc de completar tot l'importador TPV automàtic.
3. Alguns canvis, altes d'empresa, assignacions múltiples i correccions com a procediment restringit, provat i documentat; no com a manipulació manual de taules.
4. Activar només els descomptes seleccionats i provats.
5. No activar inicialment noves vendes de tallers/jornades/packs, grups, regals i USOC en el nou circuit. **Abans cal comprovar que això és acceptable i resoldre expressament pagaments pendents, bescanvis, reclamacions i obligacions existents.** Si cal mantenir alguna variant, s'incorpora el seu cost al pla; no queda assumida dins dels zeros.

El cost operatiu repetit d'un procediment assistit no desapareix. Cal provar-lo amb volum representatiu i assignar una persona que el pugui executar. Si la càrrega és incompatible amb el funcionament de PrisMa, s'ha d'automatitzar i tornar a estimar.

Les fitxes funcionals necessàries continuen dins del pla. L'automatització del generador de fitxes es tracta com una eina de suport i només s'amplia si l'estalvi és demostrable. No es retira per defecte tota la feina documental.

## 4. Recursos i responsabilitats de l'escenari amb suport

Escenari hipotètic; no hi ha cap recurs contractat ni assignat externament.

| Rol | Responsabilitat | Dedicació de càlcul |
| --- | --- | --- |
| Responsable actual del projecte | Decisions de dades/negoci, codi legacy, web/intranet, sincronització, integració, acceptació i coordinació. | 492 h netes fins al 31/12, dins d'un escenari de 50 h brutes/setmana. |
| Suport amb experiència PHP/MySQL i integracions fiscals | Mòduls delimitats de servidor, BD, registre/XML/AEAT, documents i recuperació. | 260 h netes; aproximadament 30–35 h brutes/setmana durant 12 setmanes, 21/09–13/12. |
| Operació d'intranet, persona a designar | Validar cerca, passar pagaments, incidències, procediments assistits i càrrega real. | Proposta: 2 h/setmana durant les primeres 10 setmanes i 8 h d'UAT final; disponibilitat externa, no es resta dels tres dies de la responsable. |
| Direcció/assessoria i administració del servidor/banc | Resoldre abast, criteris, certificat/accessos i activació. | Temps extern pendent de confirmar; finestres sol·licitades al començament. |

La participació de l'operador i direcció no substitueix les hores tècniques. La preparació i acompanyament tècnic de les sessions ja és als paquets; la seva disponibilitat pròpia és una dependència de calendari.

### Distribució de les 260 h netes de suport

| Paquet | Hores del suport | Frontera del lliurable |
| --- | ---: | --- |
| VT-03 | 16 | Entorns i processos de servidor. |
| VT-05 | 16 | Revisió/migracions de BD amb contracte acordat. |
| VT-08 | 32 | Registres i encadenament. |
| VT-09 | 56 | XML, certificat, transport i worker AEAT. |
| VT-10 | 24 | Correccions registrals del backend. |
| VT-11 | 32 | Generació, custòdia i recuperació de documents. |
| VT-13 | 12 | Integració tècnica de la cua Redsys. |
| VT-14 | 8 | Errors/intents/caducitats del backend. |
| VT-21 | 12 | Consulta operativa de cues i incidències. |
| VT-32 | 12 | Revisió de permisos i auditoria. |
| VT-33 | 16 | Acceptació integrada amb focus backend. |
| VT-34 | 24 | Restauració, tall i comprovacions productives. |
| **Total suport** | **260** | |

Càlcul de càrrega de la responsable: **720 − 260 + 32 = 492 h**. Les 32 h són feina nova de preparació de contractes de lliurament, revisió i integració amb el suport; la reserva habitual de disponibilitat continua separada. No s'ha dividit la data per dos pel simple fet d'afegir una persona.

Cada lliurable extern ha de portar versió, migració si toca, proves i instruccions d'operació. La responsable conserva l'acceptació i integració de negoci. Si el suport necessita aprendre tot el llegat abans de produir, aquest pressupost i aquest calendari deixen de ser vàlids.

## 5. Ordre de treball i dependències

### Prioritat 1 — Desbloquejar el sistema real

VT-01/02/03/04/05/06. Determinar què corre de veritat, assegurar entorn i dades i fixar contractes. Iniciar gestions de certificat, TPV i hosting immediatament perquè la seva espera no es pot resoldre fent més hores el diumenge.

### Prioritat 2 — Tancar el nucli compartit

VT-07/08/09/10/11/12, amb VT-13. Han de funcionar factura, cobrament, registres, documents, autenticació i recuperació. Reutilitzar serveis i worker existents; verificar els buits abans d'afirmar que estan integrats.

### Prioritat 3 — Connectar els canals i els efectes posteriors

VT-14/15/16/17/18/19/26. Un recorregut vertical de curs demostra contractes, però no tanca variants ni regressió de la intranet. Descomptes actius i dades fiscals s'han de resoldre abans del snapshot i de pagar.

### Prioritat 4 — Fer-lo operable

VT-20…25 i variants activades VT-27…30. Consulta, correus, conciliació i correccions necessàries formen part del llançament. Les que depenen de serveis comuns es desenvolupen quan aquests contractes estan disponibles.

### Prioritat 5 — Acceptar, assajar el tall i activar

VT-31…35. Les proves locals comencen amb cada mòdul; aquesta fase reserva temps per comprovar el conjunt, corregir defectes, restaurar i formar l'operador.

**Camí que condiciona la data:** versió/dades/entorn → nucli fiscal i contractes → integració real de canals i sincronització → acceptació/recuperació → activació. AEAT/certificat, compatibilitat i disponibilitat de suport poden convertir-se en el bloqueig principal. És una xarxa inicial de dependències, no un càlcul formal del camí crític amb totes les subtasques desglossades.

## 6. Calendari amb càrrega: només escenari limitat + suport

Les setmanes següents respecten la capacitat de càlcul de la responsable: 30 h netes la primera setmana parcial, 37,5 h/setmana completa i almenys 15 h la darrera setmana parcial. Aquesta distribució usa un mínim de 76 dies disponibles. La data concreta dels tres dies es pot moure sense assignar feina als altres dos.

| Setmana | Responsable h netes | Suport h netes | Prioritat i resultat esperat |
| --- | ---: | ---: | --- |
| 15–20/09 | 30 | 0 | Versió de partida, casos activables, dependències i primera comprovació d'entorn. |
| 21–27/09 | 32 | 22 | Compatibilitat/dades/històric; suport prepara entorns i BD. Decisió d'abast i recursos. |
| 28/09–04/10 | 35 | 22 | Nucli i API; contracte de registre i encadenament. |
| 05–11/10 | 35 | 22 | Factura/cobrament i sincronització; XML i connexió AEAT de proves. |
| 12–18/10 | 35 | 23 | Redsys i primer checkout de proves; remissió i recuperació AEAT. |
| 19–25/10 | 35 | 23 | Retorns, idempotència, import i efecte a inscripció; factura/alta AEAT de prova amb resposta persistent. |
| 26/10–01/11 | 36 | 22,5 | Passar pagaments, factura prèvia, receptors i descomptes actius; documents/correccions del backend. |
| 02–08/11 | 36 | 22,5 | Parcials, multi-factura assistida, sincronització i consulta; storage i recuperació de documents. |
| 09–15/11 | 36 | 22,5 | Baixes/canvis/devolució/saldo assistits, portal, correus i permisos. |
| 16–22/11 | 37 | 22,5 | Conciliació, panell i avisos; dos circuits complets i excepcions del tall funcionant. |
| 23–29/11 | 35 | 20 | Regressió, rols, errors/concurrència, proves de l'operador i correccions. |
| 30/11–06/12 | 35 | 20 | Acceptació final, backup/restore i assaig de transició. |
| 07–13/12 | 35 | 18 | Candidata, correccions finals, expedient, formació i decisió d'activació. |
| 14–20/12 | 20 | 0 | Finestra d'activació condicionada; control de primeres operacions i conciliació. |
| 21–27/12 | 12 | 0 | Estabilització i incidències d'operació. |
| 28–31/12 | 8 | 0 | Tancament i seguiment de cues, documents, factures i cobraments. |
| **Total** | **492** | **260** | **752 h incloent transferència/integració addicional.** |

És una assignació inicial de càrrega per setmana i lliurables; no acredita encara que totes les subtasques encaixin sense retards. Al detall setmanal s'han de limitar els treballs simultanis i respectar el contracte necessari per començar cada integració. Els 32 h addicionals estan distribuïts dins la càrrega de la responsable, no s'afegeixen una segona vegada al calendari.

Cap activació el 14/12 si no s'han superat les portes. La capacitat restant de desembre és reserva real, no una promesa de noves funcionalitats.

## 7. Fites amb evidència i decisió si fallen

| Fita | Evidència exigida | Si no es compleix |
| --- | --- | --- |
| 27/09 — Base i recursos | Entorn executable, versió integrada identificada, casos de tall i responsable de suport confirmats. | Recalcular termini/càrrega; no continuar presentant el calendari assistit com si el suport existís. |
| 25/10 — Nucli real | Factura de prova, registre/alta AEAT amb resposta persistent, callback acceptat/duplicat i traça d'error. | Replanificar el bloc fiscal i els canals; prioritat sobre millores de pantalla. |
| 22/11 — Operació completa | Compra i pagament d'intranet amb documents, sincronització, permisos i excepcions seleccionades. | Aturar ampliacions; revisar si resten hores suficients per provar i desplegar abans de Nadal. |
| 06/12 — Acceptació i recuperació | UAT, regressió bloquejant, conciliació, restauració i tall assajats. | No donar el llançament per confirmat; quantificar defectes i nova data. |
| 13/12 — Decisió d'activació | Versió i expedient complets, cap incidència bloquejant, operador format. | No activar; utilitzar reserva només si resol els problemes amb evidència. |

## 8. Riscos que cal gestionar activament

| Risc | Senyal primerenc | Acció i propietari proposat |
| --- | --- | --- |
| Infraestimar llegat i dades | Nous punts de facturació, SQL o efectes acadèmics sense contracte. | Responsable: afegir-los al paquet corresponent i actualitzar hores, no absorbir-los silenciosament. |
| Codi preparat que no funciona integrat | Suite bloquejada, mocks o comprovació de fitxer sense prova funcional. | Responsable/suport: prova executable de la versió integrada a la primera fase. |
| Certificat, banc o hosting tardans | Sense data i responsable de lliurament la primera setmana. | Direcció/administració: gestió entre setmana i seguiment amb alternativa tècnica admissible. |
| Suport indisponible o massa lent | No hi ha persona confirmada el 27/09 o falla el primer lliurable. | Direcció/responsable: retirar el calendari assistit i reestimar; no comptar hores hipotètiques. |
| Canvis d'abast durant implementació | Una nova variant entra com a «petit canvi». | Responsable: sumar backend, pantalla, dades, correu, proves i operació abans d'acceptar-la al tall. |
| Doble feina entre fitxes, Trello i codi | Moltes targetes tancades sense lliurable usable. | Un registre mestre per paquet i evidència; limitar neteja administrativa al que ajuda a executar. |
| Procediment assistit massa costós | L'operador no pot resoldre el volum real dins la jornada. | Operació/responsable: provar-lo amb volum i estimar automatització si no és viable. |
| Disponibilitat menor que 50 h/setmana | Es perden dies o hores de manera recurrent. | Recalcular amb 40/45 h; no consumir automàticament els dies d'altres feines. |
| Desviació general del 20% | Diversos paquets consumeixen més del previst. | El limitat passaria de 720 a 864 h. Amb suport de 260 i 32 h addicionals, quedarien 636 h a la responsable, per sobre de 570–585: el termini tornaria a estar en risc. |

## 9. Organització pràctica de cada setmana

- **Dia VERI*FACTU A:** acabar la tasca principal que desbloqueja altres paquets.
- **Dia VERI*FACTU B:** implementar/integrar el següent lliurable, resoldre gestions externes i revisar suport si existeix.
- **Dia VERI*FACTU C:** provar el circuit amb dades representatives i deixar-lo en un estat integrable.
- **Dissabte:** tancar integració i excepcions; evitar començar una tercera línia de feina.
- **Diumenge:** regressió afectada, evidència i actualització del pla següent.

Els noms A/B/C permeten adaptar-ho als tres dies reals sense ocupar els altres dos. Màxim una tasca principal de desenvolupament i una de verificació obertes per persona. Una tasca superior a 16 h es divideix en lliurables de 4–12 h abans de començar; la suma continua sent la del paquet.

Revisió setmanal curta:
1. Quins circuits han passat realment a provats/integrats?
2. Quantes hores queden, segons el que ara sabem?
3. Quin bloqueig extern afecta la següent fita?
4. Quina càrrega entra la setmana següent sense consumir la reserva?

No s'ha programat cap recordatori automàtic ni modificat Trello amb aquest pla.

## 10. Primera setmana que es pot executar sense decidir encara les retallades

Pressupost: **30 h netes** en la setmana parcial del 15–20/09, escenari de 10 h/dia disponible. Si la dedicació real és menor, es desplaça càrrega a la setmana següent.

| Feina | Hores | Sortida |
| --- | ---: | --- |
| Reconciliar versió i branques dels canals objectiu | 8 | Referència exacta de codi i diferències a integrar. |
| Comprovar PHP/MySQL i executar proves amb dades de test | 6 | Resultat fresc; errors d'entorn separats d'errors de codi. |
| Desglossar compra i Passar pagaments amb variants/dependències | 8 | Llista de lliurables amb fitxers, dades i criteris de finalització. |
| Confirmar hosting, TPV, certificat i interlocutors | 2 | Dependències amb responsable i data de resposta. |
| Assignar abast/reutilització a les pantalles afectades i recalcular paquets més incerts | 6 | Primera correcció de l'estimació i prioritat de la setmana següent. |
| **Total** | **30** | |

Aquesta feina forma part de les hores pressupostades, no és un afegit al total. Les comprovacions executables d'aquesta setmana estan planificades, no s'han executat en aquest torn de revisió de gestió.

## 11. Com es dona la revisió per completada

S'ha lliurat una estimació inicial per paquet, una comparació amb capacitat, un diagnòstic de viabilitat, una proposta limitada explícita, un calendari amb càrrega i suport, responsables, riscos i portes de control. Això completa la revisió de gestió inicial.

No significa que s'hagi acabat l'auditoria executable del producte ni que l'abast reduït, les 10 h diàries o el recurs extern estiguin confirmats. La data es converteix en compromís només després de resoldre aquestes condicions i de contrastar l'estimació inicial amb execució real.
