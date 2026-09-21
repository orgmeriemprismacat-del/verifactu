> **Pla vigent — 16/09/2026:** vegeu [Pla reconciliat R2](pla-reconciliat-r2.md): 38 paquets, 944 h de mínim proposat i 1.332 h probables per a l'abast ampli. Les estimacions i correccions pendents d'aquest document es conserven com a antecedent.

# Pla de treball VERI*FACTU amb 75 hores setmanals

> **CORRECCIÓ DE COBERTURA — 15/09/2026:** `cobertura-registres-gestio-pagaments.md` incorpora el requisit obligatori de traça universal `payment_action_event`/UC-86 com a VT-37 i relaciona els altres registres del document 38. Les 720 h i el marge de lliurament següents són la base anterior a aquesta correcció, no una previsió completa validada. Cal reestimar sense duplicar els imports genèrics d'auditoria ja inclosos. La disponibilitat de 75 h/setmana es manté.

Data: 15/09/2026. Disponibilitat aclarida per l'usuari: **15 h × 3 dies entre setmana + 30 h totals el cap de setmana = 75 h/setmana**. Els altres dos dies entre setmana són per a altres feines.

Aquest document substitueix les conclusions de calendari i recursos de `pla-execucio-gestio-2026-12-31.md`, calculades amb 50 h/setmana. Es conserven els 36 paquets i les estimacions de treball restant a `estimacio-detallada-verifactu-2026-09-15.md`.

## 1. Conclusió de gestió corregida

**La proposta operativa de 720 h pot encaixar abans del 31/12 amb una sola persona i aquesta disponibilitat. Ja no cal pressupostar suport extern com a requisit d'aquest escenari.** La data continua condicionada a l'abast, a l'estimació inicial i a les dependències externes.

**L'abast ampli de 1.116 h continua per sobre de la capacitat planificable.** Amb la nova dedicació, la seva projecció de càrrega passa d'abril a finals de gener/principis de febrer de 2027.

Les 720 h corresponen a una proposta amb automatitzacions simplificades i variants no activades; no representen tot el backlog ni una reducció aprovada per l'usuari. La disponibilitat de 75 h sí que ha estat indicada; l'abast reduït encara no.

## 2. Capacitat i marge

| Concepte | Capacitat |
| --- | ---: |
| Tres dies entre setmana | 45 h brutes/setmana |
| Dissabte + diumenge | 30 h brutes/setmana |
| Total disponible | **75 h brutes/setmana** |
| Reserva de planificació del 25% | 18,75 h/setmana |
| Treball pressupostable | **56,25 h netes/setmana** |

Entre el 15/09 i el 31/12, inclosos, hi ha 76–78 dies disponibles segons quins siguin els tres dies entre setmana. Per comptar les setmanes parcials s'han repartit les 30 h del cap de setmana en 15 h dissabte i 15 h diumenge; el total no canvia si es distribueixen d'una altra manera dins del mateix cap de setmana.

- Capacitat bruta: **1.140–1.170 h**.
- Capacitat després de la reserva: **855–877,5 h**.
- Marge respecte de 720 h: **135–157,5 h netes** fins al 31/12.
- Dèficit respecte de 1.116 h: **238,5–261 h netes**.

La reserva absorbeix coordinació habitual, interrupcions i variació. Les proves, documentació i tasques operatives necessàries ja estan dins les estimacions. No s'han de tornar a restar de la capacitat com un segon percentatge. Les hores de disponibilitat tampoc s'han de convertir totes en hores compromeses a funcionalitats.

L'abast ampli només sembla encaixar si es comparen 1.116 h amb les hores brutes: deixaria 24–54 h per a totes les interrupcions i desviacions del període. **Això no és una base robusta per comprometre el 31/12.**

## 3. Escenaris de lliurament

| Escenari | Esforç | Lectura amb 75 h/setmana |
| --- | ---: | --- |
| Operació limitada proposada | 720 h | Encaixa en capacitat; finestra objectiu d'activació 14–20/12, estabilització fins al 31/12. |
| Abast ampli en condicions favorables | 696 h | Podria encaixar si es confirma reutilització molt bona i poques incidències. No és l'escenari probable i no es pot prometre ara. |
| Abast ampli probable | 1116 h | Projecció per càrrega: 31/01–01/02/2027, mantenint la dedicació després del 31/12 i sense absències addicionals. |
| Operació limitada amb desviació del 20% | 864 h | Gairebé consumeix tota la capacitat neta; segons els dies exactes faltaria fins a 9 h o quedarien 13,5 h. La finestra anticipada de llançament estaria en risc. |

Les dates de càrrega no incorporen retards del banc, certificat o hosting ni una simulació detallada de totes les dependències. Serveixen per decidir què pressupostar, no per declarar el projecte tècnicament verificat.

Si s'exigeix l'abast ampli sencer abans del 31/12, cal reduir les hores pendents demostrant més reutilització, obtenir ajuda focalitzada o canviar la data. Un eventual suport ha de cobrir el dèficit i també la transferència/integració; no n'hi ha prou amb assignar exactament 239–261 h externes i assumir que tot es pot executar en paral·lel.

## 4. Quina feina inclou el calendari de 720 h

No es retallen integritat fiscal, controls de pagament, recuperació ni proves. La proposta conté:

| Àrea | Hores |
| --- | ---: |
| Preparació, entorns, compatibilitat, BD i convivència històrica | 116 |
| Motor fiscal, AEAT, correccions, documents i API | 200 |
| Redsys, checkout, Passar pagaments, factura prèvia, sincronització i portal | 164 |
| Correus, panell operatiu i procediments assistits de conciliació/canvis/devolucions/reclamacions | 92 |
| Descomptes seleccionats per al tall | 12 |
| Regressió, permisos, acceptació, restauració, desplegament, expedient i formació | 132 |
| Delimitar eines i ampliacions | 4 |
| **Total** | **720** |

Les limitacions exactes de cada VT consten a l'estimació detallada. En particular, no es pressuposa activar totes les noves vendes de pack, grup, regal o USOC ni automatitzar tots els processos de gestió. Cap pendent, bescanvi o obligació existent pot quedar sense circuit: si requereix una variant, s'incorpora al pressupost.

El marge de 135–157,5 h no autoritza automàticament totes les variants. Completar els paquets comercials VT-26…30 fins al seu pressupost ampli afegiria 132 h, abans de revisar increments relacionats de pantalles, correus, factures prèvies, canvis i proves. Això consumiria gairebé tot el marge. Cal valorar el cost complet de la variant, no només el seu servei.

## 5. Calendari de càrrega d'una sola persona

**Calendari condicionat a la proposta de 720 h**, no compromís de completar l'abast ampli en les mateixes dates. No inclou un segon desenvolupador ni les 32 h addicionals de coordinació del pla amb suport.

Cap setmana supera 56,25 h netes; la primera parcial es limita a 45 h i l'última a 22,5 h. Això és compatible amb el mínim de 76 dies disponibles sense ocupar els dos dies reservats a altres feines.

| Setmana | Hores netes previstes | Treball prioritari |
| --- | ---: | --- |
| 15–20/09 | 45 | Versió real, entorn executable, fitxes dels fluxos, dependències externes i primer contrast del pressupost. |
| 21–27/09 | 55 | Compatibilitat, BD, dades/històric i decisió concreta d'abast. |
| 28/09–04/10 | 55 | Acabar dades, nucli de factura/cobrament, API i autorització. |
| 05–11/10 | 55 | Registres, encadenament, XML i connexió AEAT de proves. |
| 12–18/10 | 55 | Cua/respostes AEAT, errors i correccions registrals. |
| 19–25/10 | 55 | PDF/QR/XML, custòdia, descàrrega i comprovació conjunta del nucli. |
| 26/10–01/11 | 55 | Integrar Redsys i checkout amb dades fiscals i descomptes actius. |
| 02–08/11 | 55 | Retorns/duplicats, sincronització, inscripció i portal d'alumne. |
| 09–15/11 | 55 | Passar pagaments, factura prèvia, parcials, receptors i procediments assistits. |
| 16–22/11 | 55 | Completar canals; correus, panell, conciliació i excepcions operatives. |
| 23–29/11 | 55 | Tancar operació i fer regressió, rols i acceptació amb l'usuari d'intranet. |
| 30/11–06/12 | 45 | Correccions, restauració i assaig del tall. |
| 07–13/12 | 40 | Versió candidata, expedient, formació, comprovacions finals i decisió d'activació. |
| 14–20/12 | 20 | Activació si passa els criteris i seguiment de les primeres operacions. |
| 21–27/12 | 12 | Estabilització, conciliació i incidències. |
| 28–31/12 | 8 | Seguiment final i tancament operatiu. |
| **Total** | **720** | |

Distribució: **680 h fins al 13/12 i 40 h després**. La capacitat neta fins al 13/12 és 720–731,25 h: queden 40–51,25 h de marge abans de la finestra de llançament, a més de la reserva general del 25% ja aplicada. La resta del marge queda al desembre.

Cada fila descriu el focus, no obliga a esperar per provar ni a ajornar gestions externes. Les proves locals es fan dins de cada paquet i la consulta amb operació comença al principi. Les dependències que bloquegin una setmana obliguen a moure càrrega i actualitzar la fita.

## 6. Portes de control

- **27/09:** entorn i dades representatius, còpia activa identificada, abast de llançament decidit i primera reestimació. No s'ha de dedicar tot el període a netejar Trello: cal avançar també la base tècnica.
- **25/10:** factura de prova, registre/AEAT, correcció necessària i document real; cap confusió entre metadades o simulació i funcionament complet.
- **22/11:** compra i Passar pagaments complets amb els efectes al llegat i a l'alumne, més excepcions i variants seleccionades.
- **06/12:** regressió bloquejant, acceptació, conciliació, backup i restauració demostrats.
- **13/12:** candidata i expedient tancats, operador format i decisió d'activació.
- **14–31/12:** activació condicionada i estabilització. Cap prova bloquejant fallida queda compensada per tenir hores lliures.

## 7. Gestió de les hores i de la feina

La disponibilitat es pressuposta setmanalment. Les 15 h d'un dia no impliquen programar 15 h seguides: anàlisi, implementació, proves, revisió de dades, documentació i coordinació també són feina del projecte i es distribueixen segons el que desbloquegi el següent lliurable.

- Tres dies entre setmana: contractes/dades, implementació i gestions externes; fer les validacions que requereixen altres persones en aquests dies.
- Cap de setmana: completar integració, regressió, evidències i preparació de la setmana següent.
- Màxim una tasca principal i una de verificació obertes. Desglossar paquets grans en lliurables de 4–12 h abans d'executar-los.
- Cada diumenge: anotar hores consumides, estimar de nou les hores pendents i revisar la següent fita. Les hores gastades no són percentatge de funcionalitat acabada.
- No substituir la reserva per tasques noves només perquè la setmana anterior hagi anat bé.

### Llindar pràctic per no perdre el termini

Per conservar la finestra d'activació del 14–20/12, les hores pendents abans d'activar han de cabre en la capacitat restant fins al 13/12. Si no hi caben, s'ha d'actualitzar la data o el recurs/abast; no continuar mostrant el 14/12 per inèrcia.

## 8. Estat i següent execució

Disponibilitat de 75 h indicada per l'usuari. Estimació inicial feta sobre evidència local; no s'ha executat el producte durant aquesta revisió. Abast limitat encara proposat, no acceptat. Suport extern ja no es considera necessari per l'escenari limitat amb aquesta disponibilitat.

La feina que es pot començar independentment de les decisions de retall és la base comuna: reconciliar versions, tenir PHP/MySQL de test funcionant, contrastar dades i fer executables les proves del nucli. Els criteris, fonts i riscos detallats dels 36 paquets es conserven als documents mestres.
