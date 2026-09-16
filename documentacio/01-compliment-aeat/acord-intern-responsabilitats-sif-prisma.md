# Acord intern de designació i responsabilitats del projecte SIF PrisMa

## Finalitat de l'acord

Aquest acord formalitza la designació de la responsable tècnica del projecte SIF PrisMa, el seu àmbit d'autonomia i decisió, la distribució de responsabilitats amb Associació PrisMa, la custòdia del certificat digital i el procés d'activació de versions. També incorpora, com a annex, el formulari de vistiplau tècnic que s'emplenarà quan es revisi una versió concreta.

El document es pot subscriure mentre el projecte continua en desenvolupament. No certifica que el SIF estigui acabat ni substitueix la declaració responsable exigida pel Reial decret 1007/2023. La seva signatura reconeix les facultats internes de la responsable tècnica per decidir la preparació tècnica, activar o suspendre versions i iniciar o aturar la remissió sistemàtica de registres. També estableix que tota decisió o actuació que afecti el SIF, i qualsevol actuació sobre els circuits de pagament gestionats pels sistemes de l'entitat, ha de passar per la seva intervenció i conformitat.

## Parts

**Associació PrisMa**, amb NIF `G17881988` i domicili a c. Sant Hipòlit, 16, baixos 2a, 17003 Girona, representada als efectes d'aquest acord per **Adam Carmona**, director, d'ara endavant, **l'entitat**.

**Meriem Abjil Bajja**, responsable tècnica, funcional i documental del projecte SIF PrisMa, d'ara endavant, **la responsable tècnica**.

Als efectes de la governança del projecte, **Adam Carmona**, **Pablo Martori Delupi** i **Meriem Abjil Bajja** formen l'equip intern que adopta de manera compartida les decisions fiscals, jurídiques, laborals, funcionals i operatives relacionades amb el SIF, segons la matèria i les funcions de cadascú. Aquesta menció no converteix Pablo Martori Delupi en part signant de l'acord.

Ambdues parts reconeixen la capacitat necessària per subscriure aquest acord intern i acorden les clàusules següents.

## Acorden

### Clàusula 1 Objecte i naturalesa

1. L'entitat designa Meriem Abjil Bajja com a responsable tècnica, funcional, documental, de desenvolupament, manteniment i posada en operativa del projecte SIF PrisMa i de la seva adaptació a la modalitat VERI*FACTU.
2. Associació PrisMa manté la condició de productora i titular interna del sistema desenvolupat per a ús propi, així com la responsabilitat organitzativa, tributària i de decisió que li correspongui com a entitat.
3. Aquest acord és de governança interna. No converteix la responsable tècnica en productora externa persona física, representant legal de l'entitat ni garant personal del compliment tributari de l'organització.
4. La declaració responsable reglamentària s'emetrà separadament per a cada versió concreta del SIF que correspongui certificar.

### Clàusula 2 Responsabilitats d'Associació PrisMa i col·laboració interna

Correspon a l'entitat i a la seva direcció:

- facilitar els recursos, accessos, infraestructura, temps i col·laboració interna necessaris per desenvolupar i provar el SIF;
- proporcionar o autoritzar les dades fiscals i corporatives que hagin de constar al sistema i a la declaració responsable;
- obtenir, renovar i mantenir el certificat electrònic qualificat de l'entitat o formalitzar la representació, l'apoderament o la col·laboració social que s'utilitzi davant l'AEAT;
- designar la persona amb representació suficient que subscriurà la declaració responsable del SIF en nom de l'entitat;
- reconèixer i facilitar l'autonomia de la responsable tècnica sobre l'arquitectura, el desenvolupament, els accessos administratius, les versions, les incidències i la posada en operativa del SIF;
- sotmetre a la intervenció i conformitat expressa de la responsable tècnica qualsevol decisió o actuació que afecti el SIF o els circuits de pagament gestionats pels sistemes de l'entitat, i impedir que altres persones o proveïdors les decideixin o executin unilateralment;
- adoptar de manera compartida, juntament amb Meriem Abjil Bajja i Pablo Martori Delupi, les decisions fiscals, jurídiques, laborals, funcionals i organitzatives relacionades amb el projecte, i deixar constància de les que afectin el compliment o l'operació;
- rebre informació sobre les activacions, incidències, riscos i mesures correctores rellevants, sense establir una autorització prèvia addicional per a cada actuació tècnica;
- garantir que les persones i proveïdors implicats compleixen les obligacions de confidencialitat, protecció de dades i seguretat aplicables;
- demanar assessorament fiscal, jurídic, laboral o de seguretat quan l'equip intern consideri que una decisió necessita criteri especialitzat extern.

### Clàusula 3 Responsabilitats de la responsable tècnica

Correspon a la responsable tècnica, amb els recursos i accessos facilitats per l'entitat:

- definir i mantenir l'arquitectura tècnica i funcional del SIF PrisMa;
- implementar o coordinar els components de facturació, registres fiscals, hash, idempotència, cua AEAT, documents, pagaments, permisos i incidències;
- administrar els entorns, configuracions, desplegaments, versions, processos automàtics, cues i accessos tècnics necessaris per operar el SIF;
- preparar i mantenir la documentació tècnica, funcional, operativa i de versions;
- definir i executar, quan l'entorn ho permeti, les proves tècniques i funcionals, conservar-ne les evidències i informar del resultat;
- identificar i comunicar riscos, bloquejos, mancances o desviacions que puguin afectar el compliment o la seguretat;
- decidir el resultat tècnic `GO`, `GO AMB LIMITACIONS` o `NO-GO`, amb les limitacions i evidències corresponents;
- activar, suspendre o substituir versions productives i iniciar, suspendre o reprendre la remissió sistemàtica de registres a l'AEAT, sense necessitat d'una autorització específica addicional per a cada actuació;
- adoptar canvis tècnics urgents per protegir la integritat, la disponibilitat, la seguretat o el compliment del sistema, documentant-los tan aviat com sigui possible;
- intervenir necessàriament i emetre conformitat abans que qualsevol altra persona decideixi o executi una actuació que afecti el SIF o els circuits de pagament gestionats pels sistemes de l'entitat;
- adoptar amb Adam Carmona i Pablo Martori Delupi les decisions fiscals, jurídiques, laborals, funcionals i operatives vinculades al projecte;
- configurar o coordinar l'accés tècnic del worker SIF al certificat client AEAT sense exposar-ne la clau privada;
- mantenir fora del repositori, del webroot, dels logs i de la documentació qualsevol certificat, clau privada, contrasenya o secret tècnic;
- aturar l'activació, el servei o la remissió quan falti una condició bloquejant o no hi hagi evidència suficient.

### Clàusula 4 Autonomia responsabilitat i garanties

1. L'entitat delega internament en la responsable tècnica la facultat de decidir quan una versió està tècnicament preparada, desplegar-la, activar-la, suspendre-la o substituir-la i iniciar, suspendre o reprendre les remissions a l'AEAT. Aquesta facultat no atribueix representació general de l'entitat fora de l'àmbit tècnic i operatiu del SIF.
2. Les decisions fiscals, jurídiques i laborals relacionades amb el projecte s'adopten de manera compartida per Meriem Abjil Bajja, Adam Carmona i Pablo Martori Delupi. Meriem hi intervé com a persona decisora, no únicament com a assessora o executora tècnica. Quan una decisió exigeixi representació formal de l'entitat o l'actuació d'un professional habilitat, la seva intervenció s'ha d'incorporar sense excloure la participació de la responsable tècnica en la definició i execució de la decisió.
3. Tota decisió o actuació que afecti el SIF, i qualsevol actuació que intervingui en els circuits de cobrament o pagament gestionats pels sistemes de l'entitat, ha de passar prèviament per la intervenció i conformitat expressa de la responsable tècnica. Aquesta reserva inclou la creació o modificació de fluxos, imports, estats o dades; l'alta, registre, autorització, captura, devolució, anul·lació, compensació, fraccionament o conciliació d'operacions; i les integracions, callbacks, automatismes, canvis manuals o desplegaments relacionats amb Redsys o qualsevol altre mitjà de pagament.
4. Cap altra persona de l'entitat, membre de l'equip, col·laborador o proveïdor pot decidir, ordenar, configurar, executar o posar en producció unilateralment una actuació inclosa en l'apartat anterior sense la participació i conformitat de la responsable tècnica. En les matèries compartides, Adam Carmona i Pablo Martori Delupi decideixen amb Meriem Abjil Bajja; la decisió no es pot adoptar ni executar sense ella. Aquesta regla no limita les actuacions que la responsable tècnica pot adoptar dins de les facultats que li reconeixen aquest acord.
5. La responsable tècnica respon de l'execució diligent de les actuacions que realitzi dins de les funcions assumides i sobre la base de la informació, els recursos i els accessos disponibles.
6. Sense perjudici de les responsabilitats que no es puguin excloure legalment, aquest acord no trasllada a la responsable tècnica les obligacions tributàries, corporatives, laborals o de representació pròpies d'Associació PrisMa. Tampoc se li atribuiran internament les conseqüències de decisions de l'entitat o d'altres persones, ni les derivades de manca d'entorn, informació, accessos, certificat, dades, recursos o col·laboració que no li siguin imputables.
7. La responsable tècnica pot deixar constància escrita del seu criteri, dels desacords i dels riscos detectats, i pot adoptar mesures tècniques preventives o de contenció quan siguin necessàries per protegir el sistema o els registres.
8. Les garanties d'aquesta clàusula delimiten l'atribució de responsabilitats i no redueixen l'autonomia, les facultats ni l'abast real de les funcions tècniques, funcionals i operatives reconegudes en aquest acord.

### Clàusula 5 Certificat digital i secrets

1. El certificat client utilitzat per autenticar la remissió VERI*FACTU és un actiu de l'entitat o d'un tercer que actuï amb representació, apoderament o habilitació suficient.
2. La titularitat, renovació, revocació i decisió sobre qui pot utilitzar-lo corresponen a l'entitat o al titular legítim del certificat.
3. Mitjançant aquest acord, l'entitat autoritza internament la responsable tècnica a instal·lar, configurar i gestionar l'ús operatiu del certificat per part del SIF, una vegada el titular o custodi legítim l'hagi facilitat i s'hagin establert les condicions de seguretat. Aquesta autorització interna no substitueix els apoderaments o habilitacions externes que siguin necessaris davant l'AEAT.
4. El certificat i la clau privada han d'estar protegits fora del directori públic i del repositori, amb permisos mínims, contrasenya separada, còpia de seguretat xifrada i registre de renovacions.
5. El certificat TLS de `pay.prisma.cat`, el certificat client AEAT i la signatura de la declaració responsable són elements diferents i no s'han de reutilitzar o confondre sense validació tècnica.
6. Qualsevol pèrdua, exposició, caducitat o sospita de compromís s'ha de comunicar immediatament i pot bloquejar la posada en producció.
7. La responsable tècnica no queda obligada a aportar un certificat personal, equips personals, comptes personals ni recursos econòmics propis per executar les funcions d'aquest acord.

### Clàusula 6 Versions i declaració responsable del SIF

1. Cada versió que es posi en operació ha de quedar identificada, registrada i vinculada al paquet tècnic i documental corresponent.
2. La declaració responsable del SIF ha d'identificar Associació PrisMa com a productora interna quan el desenvolupament sigui propi i per a ús propi.
3. La declaració responsable ha de ser assumida i subscrita per Associació PrisMa com a productora interna, mitjançant una persona amb representació suficient. Ha d'indicar la data i el lloc de subscripció. La normativa no exigeix que porti signatura electrònica; per al seu expedient intern, l'entitat la formalitzarà amb una signatura visible del seu representant. La responsable tècnica emetrà el vistiplau incorporat a l'annex 1 d'aquest acord, que no substitueix la subscripció de l'entitat.
4. No es podrà presentar com a definitiva una declaració que descrigui components no instal·lats, proves no executades o una versió que no sigui verificable.
5. La primera versió productiva prevista, `1.0.0`, només podrà declarar-se preparada quan s'hagin tancat els camps obligatoris, les proves, el certificat o apoderament, la remissió AEAT, els documents fiscals i les incidències bloquejants, la responsable tècnica hagi emès el resultat corresponent i l'entitat hagi subscrit la declaració responsable.
6. Qualsevol canvi substancial d'abast, arquitectura, modalitat fiscal, proveïdor, certificat o components s'ha de documentar, vincular a una versió i validar abans o, en una actuació urgent, immediatament després del canvi.

### Clàusula 7 Validació tècnica i entrada en producció

Abans de l'entrada en producció s'ha de conservar, com a mínim:

- identificador exacte de versió i referència del codi desplegat;
- migracions de base de dades i configuració d'entorn aplicades;
- proves executades i evidències de resultats;
- estat del certificat o apoderament i prova d'autenticació davant l'AEAT;
- verificació de PDF, QR, XML, cua, reintents, incidències, permisos, backups i restauració;
- declaració responsable completa de la versió;
- resultat tècnic emès i constància de les decisions compartides que afectin aspectes fiscals, jurídics, laborals o organitzatius.

La decisió tècnica correspon a la responsable tècnica i ha de quedar documentada com a `GO`, `GO AMB LIMITACIONS` o `NO-GO`. El resultat `GO` permet l'activació dins de les facultats delegades per aquest acord. Ningú més pot substituir o deixar sense efecte aquest resultat per activar una versió o ordenar una actuació sobre pagaments sense la conformitat de la responsable tècnica. Quan un `GO AMB LIMITACIONS` comporti efectes fiscals, jurídics, laborals o organitzatius, les limitacions s'han de tractar i documentar amb Adam Carmona i Pablo Martori Delupi segons la matèria. La subscripció de la declaració responsable continua corresponent a Associació PrisMa.

### Clàusula 8 Informació, confidencialitat i traçabilitat

1. Ambdues parts han de conservar la confidencialitat de les dades fiscals, personals, credencials, claus i informació tècnica a la qual accedeixin.
2. Les decisions rellevants, canvis de versió, proves, incidències i aprovacions s'han de registrar en l'expedient del projecte.
3. Les claus privades i contrasenyes no s'han d'incorporar mai als documents de projecte ni als registres de decisions.
4. L'entitat ha de garantir la continuïtat operativa i l'accés ordenat a la documentació si canvia la persona responsable o el proveïdor tècnic.
5. Les instruccions, decisions compartides, desacords, riscos acceptats i canvis urgents que puguin afectar el compliment o la producció han de quedar registrats amb la data i les persones participants.
6. La conformitat de la responsable tècnica exigida per a qualsevol decisió o actuació sobre el SIF o els circuits de pagament ha de quedar registrada amb la identificació de l'actuació, la data i les persones participants.

### Clàusula 9 Vigència recursos i condicions d'exercici

1. Aquest acord entra en vigor en la data de l'última signatura i es manté mentre la responsable tècnica conservi l'encàrrec o fins que sigui substituït per un acord posterior.
2. S'ha de revisar quan canviin substancialment les funcions, la persona responsable, la representació de l'entitat, la modalitat del SIF o el mecanisme de certificat/apoderament.
3. L'entitat reconeix que, en la data de signatura, la responsable tècnica ja disposa dels accessos administratius necessaris al codi, bases de dades, servidor, logs, configuració, entorns, còpies de seguretat, serveis i gestió segura dels secrets. Aquests accessos s'han de mantenir personals, traçables i suficients mentre duri l'encàrrec.
4. Les funcions s'exerceixen dins de la relació laboral o professional existent, amb temps, mitjans i suport adequats. Aquesta previsió regula les condicions i els mitjans de treball i no limita l'autonomia ni les facultats reconegudes a la responsable tècnica. La disponibilitat permanent, les guàrdies fora d'horari i l'ús d'equips, certificats, comptes o diners personals no formen part d'aquest acord; qualsevol pacte sobre aquestes qüestions s'ha de formalitzar separadament.
5. Qualsevol modificació o revocació de les facultats tècniques reconegudes s'ha de comunicar per escrit i no afecta la validesa de les actuacions efectuades mentre eren vigents.
6. La finalització de l'encàrrec ha d'incloure lliurament ordenat de documentació i revocació dels accessos que ja no siguin necessaris.

### Clàusula 10 Acceptació

Les parts manifesten que han llegit i entès aquest acord, que reflecteix l'autonomia i les funcions reals de la responsable tècnica, reserva la seva intervenció i conformitat en qualsevol decisió o actuació sobre el SIF o els circuits de pagament, diferencia les responsabilitats internes de la declaració responsable reglamentària del SIF i estableix les garanties i recursos necessaris per exercir l'encàrrec.

Lloc de signatura: Girona

Data: ____ de ____________________ de 2026

**Per Associació PrisMa**

Adam Carmona
Director

Signatura:


____________________________________

**La responsable tècnica**

Meriem Abjil Bajja
Responsable tècnica, funcional i documental del projecte SIF PrisMa

Signatura:


____________________________________

## Annex 1 Vistiplau tècnic de versió

Aquest annex forma part de l'acord, però la signatura de l'acord no implica que el vistiplau tècnic ja s'hagi emès. L'annex s'ha de completar i signar més endavant, quan existeixi una versió concreta instal·lada, provada i identificada. Fins aleshores, el seu estat és **PENDENT D'EMISSIÓ**.

### Identificació de la revisió

- Sistema: SIF PrisMa
- Codi del sistema: SIF-PRISMA
- Versió revisada: ______________________________
- Entorn verificat: ______________________________
- Referència del codi o desplegament: ______________________________
- Data de la revisió: ____ de ____________________ de ______
- Responsable tècnica: Meriem Abjil Bajja

### Comprovacions prèvies

| ID | Comprovació | Estat | Evidència o observacions |
|---|---|---|---|
| VT-01 | Versió identificada i desplegada a l'entorn verificat | | |
| VT-02 | Migracions i esquema fiscal revisats | | |
| VT-03 | Numeració, registres fiscals, rectificatives i encadenament hash verificats | | |
| VT-04 | Idempotència, concurrència i duplicats verificats | | |
| VT-05 | XML i validacions del servei AEAT superats | | |
| VT-06 | Certificat o representació configurat i autenticació AEAT provada | | |
| VT-07 | Cua AEAT, reintents, respostes i incidències verificats | | |
| VT-08 | PDF, QR i accés als documents fiscals verificats | | |
| VT-09 | Permisos, secrets i absència de claus al repositori revisats | | |
| VT-10 | Backup i restauració executats amb evidència | | |
| VT-11 | Declaració responsable de la versió completada i accessible al SIF | | |
| VT-12 | Incidències bloquejants tancades o inexistents | | |

Estats admesos: `OK`, `NO OK`, `NO APLICA` amb justificació o `PENDENT`.

### Resultat del vistiplau

S'ha de marcar una única opció:

- [ ] **GO.** Versió tècnicament preparada per ser activada i perquè Associació PrisMa subscrigui la declaració responsable.
- [ ] **GO AMB LIMITACIONS.** Versió preparada per ser activada únicament dins de les limitacions descrites i documentades amb l'equip intern quan tinguin efectes no tècnics.
- [ ] **NO-GO.** Versió no preparada per entrar en producció ni per subscriure la declaració responsable com a versió conforme.

Limitacions, incidències o condicions:

________________________________________________________________________________

________________________________________________________________________________

### Emissió del vistiplau tècnic

La responsable tècnica confirma que el resultat reflecteix les comprovacions efectivament realitzades sobre la versió identificada i que ha comunicat les limitacions o incidències conegudes. Aquest vistiplau no li atribueix la condició de productora externa, representant legal ni garant personal de les obligacions tributàries d'Associació PrisMa.

Meriem Abjil Bajja
Responsable tècnica, funcional i documental del projecte SIF PrisMa

Data: ____ de ____________________ de ______

Signatura:


____________________________________

### Recepció i constància de direcció

La direcció confirma que ha rebut el resultat tècnic i deixa constància de la situació comunicada. Aquesta recepció no substitueix la conformitat de la responsable tècnica ni habilita la direcció o qualsevol altra persona a activar una versió o ordenar actuacions sobre pagaments al marge d'aquest acord:

- [ ] Ha rebut el resultat `GO` i la versió ha quedat tècnicament habilitada per a l'activació.
- [ ] Ha rebut el resultat `GO AMB LIMITACIONS` i deixa constància de les limitacions compartides.
- [ ] Ha rebut el resultat `NO-GO` i deixa constància que la versió no s'ha d'activar.

Adam Carmona
Director d'Associació PrisMa

Data: ____ de ____________________ de ______

Signatura:


____________________________________

## Marc de referència

- Reial decret 1007/2023, article 13, declaració responsable dels sistemes informàtics de facturació.
- Ordre HAC/1177/2024, article 15, contingut i ubicació de la declaració responsable.
- Preguntes freqüents de l'AEAT sobre certificació de sistemes informàtics i desenvolupament propi per a ús propi.
