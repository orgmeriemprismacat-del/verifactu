# Pla reconciliat R2 — VERI*FACTU PrisMa
Data: 16/09/2026. Document vigent de planificació; substitueix les previsions de 360 h i 720 h. Estimació de feina pendent, no certificació de funcionalitats implementades.

## 1. Conclusió de gestió
**Amb 75 hores brutes setmanals, el mínim operatiu proposat requereix 944 hores netes. No és defensable prometre'l per al 31/12/2026 treballant sol amb un 25% de reserva.** La previsió de càrrega acaba entre el 9 i l'11 de gener de 2027. L'abast ampli estimat requereix 1.332 hores i acaba entre el 27 de febrer i l'1 de març de 2027.

La reserva és una hipòtesi de gestió per interrupcions, investigació i desviacions; les proves i integracions conegudes ja tenen pressupost propi. Les dates assumeixen continuïtat de la mateixa disponibilitat, també en festius, sense absències addicionals ni bloquejos externs.

La reducció funcional de 944 h **no està aprovada**. No s'ha eliminat cap garantia del registre de gestions per fer encaixar el calendari. Si cal mantenir totes les variants comercials, la referència és l'abast ampli.

## 2. Quina feina s'ha reconciliat
- 118 casos i variants del document 33, amb paquet responsable i prova d'acceptació proposada.
- 29 accions de pagament del diccionari 24.
- 24 grups de registres de base de dades, que cobreixen les 17 responsabilitats del document 38, el registre universal i taules de suport.
- El document 39 reconcilia 145 targetes mare locals amb aquests 118 casos. Això cobreix aquella fotografia local, no un Trello actualitzat en directe.
- Les 192 pantalles/estats són interfícies i variants: no s'han comptat com 192 funcionalitats independents.

La cadena de revisió és **requisit → cas → taula/servei → canal → paquet d'hores → acceptació**:
- [Matriu de registres, serveis, canals i proves](matriu-registres-r2.md).
- [Cobertura individual dels casos i accions](cobertura-casos-r2.md).
- [Model de càlcul dels 38 paquets](estimacio-reconciliada-r2.json).
- [Verificació mecànica i fotografia de fonts](verificacio-pla-r2.json).
- [Reconciliació de fitxes mare](../documentacio/04-estat-final/39-auditoria-fitxes-funcionals.md).

La cobertura documental no equival a implementació. Actualització del 16/09: existeixen migració, PaymentActionEventRepository i proves unitàries de validació. No s'ha localitzat el gateway; persistència real, atomicitat i cobertura de canals continuen sense acreditar. El hash intern existent no acredita la huella AEAT; el repositori de documents no acredita generació de PDF; la sincronització existent no acredita el circuit complet. Els tests històrics de Redsys no substitueixen executar-los amb la base de dades disponible. Aquests riscos expliquen els intervals amplis.

## 3. Correcció d'hores sense duplicacions
| Component | Mínim proposat | Abast ampli probable |
|---|---:|---:|
| Estimació anterior | 720 | 1.116 |
| Persistència i fluxos específics infravalorats | +100 | +92 |
| VT-37 registre universal de pagaments | +120 | +120 |
| VT-38 classificador i registre operatiu comú | +32 | +32 |
| Treball ja pressupostat que es trasllada als paquets nous | −28 | −28 |
| **Total net** | **944** | **1.332** |

L'abast ampli té interval inicial **826–2.256 h**, amb valor de treball probable 1.332 h. No és un interval estadístic. Les 826 h són l'escenari optimista de l'abast ampli, no un compromís ni el mínim funcional.

Els 28 h traslladats són: 8 d'auditoria específica de VT-12, 8 de cronologia de VT-32, 4 de proves de VT-33 i 8 de classificador de VT-10. No es tornen a sumar.

### Registre universal: 120 h
| Treball VT-37 | Hores |
|---|---:|
| Esquema immutable, índexs i migració | 12 |
| Gateway, identitat del servidor i correlació | 24 |
| Atomicitat, errors, rollback i intents sense resultat | 24 |
| Connectors web, intranet, callbacks, workers, imports i CLI | 28 |
| Consulta de cronologia, export i monitor | 12 |
| Proves específiques de totes les accions i fallades | 20 |
| **Total** | **120** |

VT-38 afegeix 32 h: esquema/event operatiu 8; classificador funcional/fiscal 12; controlador, previsualització, encaminament i proves 12. Els canvis de curs, baixes i altres serveis de negoci es pressuposten als seus paquets.

**Acceptació obligatòria del registre:** intent abans de l'operació; resultat final coherent i atòmic amb el canvi; persistència de fallades malgrat rollback; cap reescriptura de l'històric; actor/rol obtinguts al servidor; motiu, correlació i canvi abans/després sense secrets. S'inclouen consultes, cerques i exports segons la documentació acordada. Si no es pot registrar, es bloqueja l'acció i no es retornen dades. Un procés assistit també passa pel servei auditat: no es resol editant directament la BD.

## 4. Pressupost complet i abast
Hores netes pendents estimades. Cada fila inclou desenvolupament i verificació local; VT-33 cobreix la integració entre sistemes. Els responsables són paquets de treball; l'executor base és una sola persona.

| Paquet | Lliurable | Baix | Probable ampli | Alt | Mínim proposat |
|---|---|---:|---:|---:|---:|
| VT-01 | Reconciliar codi i fonts | 12 | 20 | 32 | 12 |
| VT-02 | Fitxes funcionals dels fluxos | 24 | 40 | 64 | 24 |
| VT-03 | Servidor i entorns | 16 | 24 | 40 | 24 |
| VT-04 | Compatibilitat del llegat | 24 | 40 | 72 | 20 |
| VT-05 | BD i migracions | 24 | 40 | 72 | 32 |
| VT-06 | Històric i transició | 20 | 32 | 56 | 20 |
| VT-07 | Facturació i cobraments del nucli | 16 | 24 | 40 | 24 |
| VT-08 | Registres i encadenament AEAT | 20 | 32 | 56 | 32 |
| VT-09 | XML, certificat i cua AEAT | 32 | 56 | 88 | 56 |
| VT-10 | Rectificatives i correccions registrals | 20 | 32 | 52 | 24 |
| VT-11 | PDF, QR, XML i custòdia | 28 | 48 | 80 | 40 |
| VT-12 | API, adaptadors i autorització | 12 | 16 | 28 | 16 |
| VT-13 | Integrar Redsys asíncron | 12 | 20 | 36 | 20 |
| VT-14 | Cicle de pagament i enllaços | 16 | 24 | 40 | 20 |
| VT-15 | Web i checkout | 28 | 44 | 72 | 36 |
| VT-16 | Passar pagaments | 32 | 48 | 80 | 32 |
| VT-17 | Factura prèvia i entitats | 16 | 24 | 40 | 16 |
| VT-18 | Sincronització i retirada del llegat | 20 | 32 | 56 | 32 |
| VT-19 | Portal alumne i consulta | 12 | 20 | 32 | 16 |
| VT-20 | Correus i notificacions | 24 | 40 | 64 | 28 |
| VT-21 | Panell operatiu SIF | 32 | 52 | 88 | 32 |
| VT-22 | Conciliació TPV | 20 | 36 | 64 | 24 |
| VT-23 | Canvis de curs | 28 | 48 | 80 | 24 |
| VT-24 | Baixa, devolució i saldo | 20 | 32 | 56 | 28 |
| VT-25 | Reclamacions i fraccionaments | 16 | 24 | 40 | 8 |
| VT-26 | Descomptes | 20 | 32 | 56 | 12 |
| VT-27 | Tallers, jornades i packs | 16 | 24 | 40 | 0 |
| VT-28 | Grups i participants | 20 | 32 | 56 | 0 |
| VT-29 | Regals i bescanvi | 16 | 24 | 40 | 0 |
| VT-30 | USOC | 20 | 32 | 56 | 0 |
| VT-31 | Regressió de funcions existents | 24 | 40 | 72 | 24 |
| VT-32 | Permisos, auditoria i export | 16 | 24 | 44 | 20 |
| VT-33 | Acceptació integrada i correccions | 30 | 52 | 80 | 36 |
| VT-34 | Desplegament, restauració i seguiment | 20 | 32 | 56 | 28 |
| VT-35 | Expedient i formació | 20 | 32 | 56 | 28 |
| VT-36 | Delimitar eines i ampliacions | 4 | 8 | 16 | 4 |
| VT-37 | Ledger universal d’accions sobre pagaments | 76 | 120 | 200 | 120 |
| VT-38 | Classificador funcional i registre operatiu comú | 20 | 32 | 56 | 32 |
| **Total** | | **826** | **1.332** | **2.256** | **944** |

### Què simplifica el mínim proposat
- **VT-01:** Reconciliació centrada en els canals del tall.
- **VT-02:** Fitxes dels circuits activats i excepcions essencials.
- **VT-04:** Compatibilitat de rutes afectades; altres canvis amb regressió controlada.
- **VT-05:** Migracions imprescindibles per operar.
- **VT-06:** Convivència històrica i pendents; sense importació massiva.
- **VT-10:** Tots els tipus necessaris, interfície tècnica restringida.
- **VT-11:** Documents funcionals amb una plantilla bàsica.
- **VT-14:** Mateixes garanties; menys variants d'experiència.
- **VT-15:** Checkout de curs amb receptor fiscal.
- **VT-16:** Curs, factura existent, transferència i parcial; multi-factura assistida validada.
- **VT-17:** Cobrar factures prèvies i protegir URLs; alta d'empresa assistida.
- **VT-19:** Consulta essencial.
- **VT-20:** Confirmació i avisos essencials; altres campanyes no refetes.
- **VT-21:** Panell operatiu essencial, sense dashboard avançat.
- **VT-22:** Conciliació assistida amb informe, sense importador automàtic complet.
- **VT-23:** Procediment assistit verificat; sense automatització completa de variants.
- **VT-24:** Procediment assistit amb retorn econòmic i correcció traçats.
- **VT-25:** Conservar/controlar scripts necessaris, sense redissenyar campanyes.
- **VT-26:** Només famílies de descompte seleccionades per al tall.
- **VT-27:** No activar aquestes noves vendes; pendents han de tenir solució expressa.
- **VT-28:** No activar noves vendes de grup; pendents han de tenir solució expressa.
- **VT-29:** No activar noves vendes de regal; bescanvis/obligacions pendents han de tenir solució expressa.
- **VT-30:** No activar noves vendes USOC; pendents han de tenir solució expressa.
- **VT-31:** Regressió dels fluxos afectats; disposició de la resta.
- **VT-32:** Mateixos controls essencials; consulta/export bàsic.
- **VT-33:** Acceptació completa de l'abast reduït.
- **VT-34:** Mateix restore/tall segur, operació acotada.
- **VT-35:** Mateixa documentació exigida per la versió activada.
- **VT-36:** Només delimitació.

Els paquets a zero no desapareixen del projecte: queden ajornats en aquesta proposta. Qualsevol obligació ja venuda —packs, grups, regals, USOC— necessita una disposició segura i provada abans del tall. Si no es pot atendre amb els circuits previstos, cal recuperar les hores del paquet corresponent i recalcular la data. VT-36 delimita botiga/SL i altres ampliacions: no pressuposta construir-les.

## 5. Capacitat fins al 31 de desembre
- 3 dies entre setmana × 15 h = 45 h brutes.
- Cap de setmana = 30 h brutes.
- Total 75 h; amb reserva del 25%, **56,25 h netes per setmana completa**.
- Els altres dos dies entre setmana continuen reservats als altres projectes.
- Del 16/09 al 31/12 hi ha entre 75 i 77 jornades disponibles segons quins tres dies siguin: **1.125–1.155 h brutes / 843,75–866,25 h netes**.
- Dèficit del mínim: **77,75–100,25 h netes**. Dèficit de l'abast ampli: **465,75–488,25 h netes**.

Baixar la reserva al 20% encara deixa només 900–924 h netes. Amb 15% hi hauria 956,25–981,75 h: encaixa aritmèticament el mínim, però deixa poca protecció davant els riscos detectats. No és la base recomanada.

## 6. Ordre de treball i dependències
| Fase | Hores mínimes | Sortida verificable |
|---|---:|---|
| A. Base, dades i accés: VT-01…06 i VT-12 | 148 | Branca base decidida, entorn funcional, contractes de dades i migració reversible |
| B. Traça base i classificador: 60 h de VT-37 + VT-38 | 92 | Cap mutació de pagament sense intent/resultat; decisions funcionals traçades |
| C. Nucli fiscal i documents: VT-07…11 | 176 | Emissió, correcció, cua i documents verificats amb errors recuperables |
| D. Canals i sincronització: VT-13…19 + connectors 28 h + VT-26 | 212 | Compra web i passar pagaments complets, sense duplicats, amb històric coherent |
| E. Operació: VT-20…25 + cronologia 12 h | 156 | Avisos, incidències, conciliació, canvis, baixes i reclamacions operables |
| F. Regressió i producció: VT-31…36 + proves de traça 20 h | 160 | Acceptació transversal, restauració, formació i tall verificats |
| **Total** | **944** | |

Camí de dependències: dades/identitat → traça comuna → nucli → connectors → gestió operativa → acceptació i tall. Es poden avançar preparació d'entorns i casos de prova, però una persona no genera dues hores productives simultànies. Les proves locals es fan durant cada fase; la fase F no és el primer moment de provar.

### Calendari d'una persona
Exemple conservador amb dilluns, dimarts i divendres més cap de setmana; **no assigna aquests dies com a preferència teva**. Altres combinacions mouen el final dins del rang indicat. Els decimals són repartiment de capacitat, no precisió de l'estimació.

| Període | Hores netes | Treball |
|---|---:|---|
| 16–20/09 | 33.75 | Base, dades i accés: 33.75 h |
| 21–27/09 | 56.25 | Base, dades i accés: 56.25 h |
| 28/09–04/10 | 56.25 | Base, dades i accés: 56.25 h |
| 05–11/10 | 56.25 | Base, dades i accés: 1.75 h; Traça i classificador comuns: 54.5 h |
| 12–18/10 | 56.25 | Traça i classificador comuns: 37.5 h; Nucli fiscal i documents: 18.75 h |
| 19–25/10 | 56.25 | Nucli fiscal i documents: 56.25 h |
| 26/10–01/11 | 56.25 | Nucli fiscal i documents: 56.25 h |
| 02–08/11 | 56.25 | Nucli fiscal i documents: 44.75 h; Canals, sincronització i connectors de traça: 11.5 h |
| 09–15/11 | 56.25 | Canals, sincronització i connectors de traça: 56.25 h |
| 16–22/11 | 56.25 | Canals, sincronització i connectors de traça: 56.25 h |
| 23–29/11 | 56.25 | Canals, sincronització i connectors de traça: 56.25 h |
| 30/11–06/12 | 56.25 | Canals, sincronització i connectors de traça: 31.75 h; Operació, registres específics i cronologia: 24.5 h |
| 07–13/12 | 56.25 | Operació, registres específics i cronologia: 56.25 h |
| 14–20/12 | 56.25 | Operació, registres específics i cronologia: 56.25 h |
| 21–27/12 | 56.25 | Operació, registres específics i cronologia: 19 h; Regressió, proves de traça i producció: 37.25 h |
| 28–31/12 | 22.5 | Regressió, proves de traça i producció: 22.5 h |
| 01–03/01 | 33.75 | Regressió, proves de traça i producció: 33.75 h |
| 04–10/01 | 56.25 | Regressió, proves de traça i producció: 56.25 h |
| 11–17/01 | 10.25 | Regressió, proves de traça i producció: 10.25 h |

Al 31/12, aquest exemple consumeix 843,75 h i encara necessita 100,25 h de validació i posada en servei. No es pot declarar acabat només perquè la pantalla de compra ja funcioni.

## 7. Com mantenir la data del 31/12
**Escenari recomanat si la data és fixa: mínim funcional acordat + suport tècnic acotat.** No pressuposa que hagis aprovat retallades ni que ja existeixi aquesta ajuda.

Un exemple és externalitzar 128 h netes i reservar 16 h teves per coordinació/revisió: **944 − 128 + 16 = 832 h teves**. Cap dins la capacitat conservadora, amb 11,75 h netes addicionals de marge, a més de la reserva ja aplicada.

Paquet externalitzable: base del registre 60 h, cronologia 12, proves del registre 20, part del classificador 24, migracions 8 i accés a documents 4. Conserves connectors de llegat, decisions de negoci i acceptació final. Les proves externes han de ser revisades, no només lliurades.

Cal concentrar aproximadament 92 h netes de fonaments en les primeres cinc setmanes des del 21/09 (unes 25 h brutes setmanals de suport amb la mateixa reserva), i completar les 36 h restants abans de l'acceptació final. Un reforç que arribi al desembre no resol la dependència dels fonaments. El calendari detallat s'ha de reajustar quan hi hagi disponibilitat real; 832 h prova capacitat, no garanteix absència de bloquejos.

Si es mantenen totes les variants de l'abast ampli, aquest reforç és insuficient. Si continues sol amb reserva del 25%, la data coherent del mínim és gener. No recomano eliminar traça, recuperació ni proves per encaixar el 31/12.

## 8. Primeres actuacions i control setmanal
1. VT-01: fixar la branca integrable i reproduir els tests amb MySQL; registrar què funciona i què queda pendent.
2. VT-02: convertir cada cas actiu en criteri d'acceptació; resoldre els pendents de dades fiscals, variants comercials i obligacions existents.
3. VT-03/05/12: preparar preproducció, credencials/rols i migracions; provar restauració inicial. Repartir aquests treballs dins les 148 h de fase A, no afegir-les al total.
4. VT-37/38: tancar contractes comuns abans d'estendre pantalles que alteren pagaments. Provar primer duplicats, concurrència, rollback i indisponibilitat del registre.
5. Cada setmana: anotar hores reals, lliurables acceptats i hores pendents per paquet; recalcular previsió. Si augmenta l'abast o es consumeix reserva, reflectir-ho immediatament.

Un paquet només es marca acabat amb demostració del flux correcte i dels errors previstos, persistència inspeccionada i prova associada. La primera setmana serveix també per recalibrar aquestes estimacions amb dades reals.

## 9. Condicions de sortida a producció
- Compra de curs i cobrament des d'intranet complets, amb factura/document, estat coherent i accés del destinatari.
- Imports parcials, múltiples intents, callbacks duplicats, reintents i concurrència sense doble cobrament ni doble factura.
- Registre universal complet en tots els canals activats; cap via antiga que permeti saltar-se'l.
- Correccions, anul·lacions, devolucions, saldo i reassignacions preserven originals i deixen evidència.
- Dades fiscals i històric separats correctament; migració i sincronització conciliades.
- Cues, documents, notificacions i incidències recuperables; permisos i privacitat provats.
- Restauració demostrada, expedient de versió, formació i procediment de tall disponibles.

## 10. Límits de la verificació
S'han verificat correspondències i sumes del pla; els identificadors de proves són **proves per implementar/executar**, no resultats aprovats. Aquesta revisió no ha desplegat, canviat codi productiu ni executat la suite del producte. Les fonts locals i decisions pendents determinen el límit de cobertura. Una nova funcionalitat acordada s'ha d'incorporar amb impacte explícit en hores i calendari.


## 11. Preparació executable

La [cua executable](cua-executable-r2.md) divideix les 148 h de fase A en 21 tasques amb dependències i acceptació. El [registre de bloquejos](bloquejos-fitxes-r2.md) recull 66 marques de 22 fitxes per revisar i deduplicar. No afegeix hores a R2.
