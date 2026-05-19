# 12 - Documentacio del sistema ecommerce, intranet i SIF

> Document pensat per a una persona externa: auditor, assessor, inspeccio, direccio o nou desenvolupador. No pressuposa coneixement previ del funcionament de PrisMa.

## 1. Objectiu

Aquest document descriu com funciona el sistema complet de PrisMa en relacio amb:

- ecommerce;
- inscripcions;
- Redsys/TPV;
- transferencies;
- intranet;
- factures;
- SIF central;
- VERI*FACTU;
- notificacions i errors;
- consultes de factures.

No es un inventari de canvis. Es la documentacio operativa del sistema.

## 2. Actors principals

### Alumne

Persona que s'inscriu a un curs, taller o jornada.

Pot:

- inscriure's;
- pagar;
- consultar pagaments;
- consultar factures visibles, quan s'habiliti;
- descarregar certificats, si compleix requisits.

### Responsable / empresa / escola

Persona o entitat que pot pagar inscripcions d'una o diverses persones.

Pot:

- demanar factura abans de pagar;
- pagar un grup;
- rebre factura;
- rebre comunicacions.

### Administracio interna

Persones autoritzades a intranet.

Poden:

- passar pagaments des de `/alumnes/pagaments/`;
- generar factures manuals;
- generar factures abans de cobrar;
- gestionar baixes;
- gestionar canvis de curs;
- gestionar rectificatives i anul·lacions des de `/alumnes/factura/`;
- gestionar devolucions;
- revisar errors.

### SIF

Servei central de facturacio.

Funcio:

- emetre factures;
- assignar numero;
- crear linies;
- generar registre fiscal;
- encadenar hash;
- generar o preparar PDF/QR;
- enviar o posar en cua AEAT;
- conservar traçabilitat.

## 3. Sistemes i dominis

### Web/ecommerce

Gestiona:

- inscripcio;
- dades de l'alumne;
- seleccio de curs;
- descomptes;
- codis promocionals;
- Redsys;
- regals;
- packs;
- grups.

Amb VERI*FACTU:

- no ha de decidir numero fiscal;
- no ha d'inserir factura final directament;
- ha de cridar el SIF.

### Intranet

Gestiona:

- alumnes;
- inscripcions;
- entitats;
- pagaments;
- factures manuals;
- factures abans de cobrar;
- marca de factura electronica, quan correspongui;
- canvis de curs;
- baixes;
- morosos;
- consultes;
- notificacions fiscals.

Amb VERI*FACTU:

- no edita factures emeses;
- inicia operacions controlades;
- consulta estats del SIF;
- mostra PDFs immutables.

La marca de factura electronica es independent de l'emissio abans de cobrament:

```text
EMESA_ABANS_COBRAMENT = factura real emesa abans de cobrar.
E_FACT = factura marcada com a factura electronica.
```

### pay.prisma.cat

Subdomini previst per centralitzar:

- pagaments;
- callbacks Redsys;
- endpoints SIF;
- generacio/consulta controlada de documents fiscals;
- comunicacio amb AEAT.

## 3.1. Diagnosi de risc de l'estat inicial

Abans de la implantacio del SIF central, l'arquitectura pot arribar a comportar-se com una emissio distribuida si cada canal calcula imports fiscals, assigna o deriva numeracio i genera document final pel seu compte.

Aixo no es el model final desitjat.

Risc identificat:

```text
TPV / ecommerce / intranet calculen i creen factures pel seu compte
    -> logica fiscal replicada
    -> risc de duplicats
    -> risc d'incoherencia documental
    -> dificultat per demostrar una unica font de veritat fiscal
```

Criteri de migracio:

```text
Els canals poden continuar calculant imports preliminars i mostrar preus.
Pero la decisio fiscal final, la numeracio, el registre, la cadena hash,
el PDF/QR fiscal i la persistencia immutable han de recaure en el SIF.
```

Per tant, el projecte VERI*FACTU no defensa una arquitectura de facturacio distribuida, sino una migracio cap a un SIF central on els canals nomes proposen operacions facturables.

## 4. Cicle de vida simplificat

```text
Inscripcio / comanda / operacio
        |
        v
Dades de facturacio confirmades
        |
        v
Pagament o factura abans de cobrament
        |
        v
SIF emet factura o registra cobrament
        |
        v
PDF/QR + registre fiscal + cua AEAT
        |
        v
Consulta intranet / alumne / responsable segons permisos
```

## 5. Regla fonamental de factura i pagament

Factura i pagament son coses diferents.

```text
Factura = document fiscal
Pagament = moviment economic
```

Una factura pot existir abans del cobrament.

Un pagament pot:

- crear factura si no existia;
- pagar factura existent;
- pagar parcialment;
- cobrir diverses factures;
- ser devolucio;
- ser compensacio.

## 6. Casos principals

### Curs normal

Una inscripcio, un pagament, una factura, una linia.

### Pack

Normalment dues inscripcions i un pagament.

Factura futura:

- una factura;
- una linia per curs;
- descompte pack aplicat al segon curs.

### Grup

Diverses inscripcions.

Factura futura:

- una factura;
- una linia per participant;
- receptor empresa/responsable;
- no visible a cada alumne si conte dades del grup.

### Regal

Compra una persona, rep el curs una altra.

Factura:

- al comprador;
- el destinatari no genera nova factura quan bescanvia.

### USOC

Dos pagadors:

- alumne paga una part i rep factura per aquella part;
- USOC paga diferencia i rep factura per la diferencia.

### Factura abans de cobrar

Quan una empresa necessita factura abans de pagar.

Si es factura real:

- porta `A2026/x`;
- va al SIF;
- queda pendent de cobrament;
- el pagament posterior no crea factura nova.

Aquesta situacio no implica automaticament que la factura sigui factura electronica. Si cal marcar-la com a factura electronica, s'ha de fer amb una opcio separada.

### Proforma / pressupost

Si el document pot canviar i no ha de ser fiscal:

- no porta `A2026/x`;
- no va al SIF;
- no es comptabilitza com factura.

## 7. Canvis de curs

Un canvi de curs pot afectar:

- concepte;
- import;
- descompte;
- despeses de gestio;
- pagament pendent;
- saldo;
- devolucio;
- rectificativa.

Per tant, s'ha de registrar com event.

No es pot resoldre nomes modificant `A_PAGAR`.

## 8. Baixes

Una baixa marca la inscripcio.

No genera rectificativa automaticament.

Despres de la baixa:

- es pregunta al client que vol fer amb els diners;
- si hi ha retorn, es registra devolucio i rectificativa;
- si hi ha saldo, es registra saldo/compensacio;
- si no hi ha retorn, la factura pot quedar igual.

## 9. Morositat

Morositat no es baixa.

Si l'alumne ha fet curs i no paga:

- es reclama;
- pot quedar `INSC_CURS = M`;
- la factura continua existint;
- no es genera rectificativa nomes per ser moros.

## 10. Notificacions i errors

El sistema ha de notificar:

- error AEAT;
- reintents fallits;
- pagament sense factura;
- factura sense pagament;
- callback duplicat;
- problema de PDF/QR;
- incidencia de cadena hash;
- operacions pendents d'intervencio.

## 11. Consulta de factures

La consulta de factures ha de respectar permisos.

Criteri general:

- el receptor d'una factura ha de poder accedir a la factura emesa;
- en modalitat VERI*FACTU, la factura incorporara QR per poder verificar-la a la seu electronica de l'AEAT;
- la intranet de l'alumne ha de permetre consultar i descarregar les factures on l'alumne sigui receptor;
- les factures d'empresa/grup no s'han de mostrar a participants si contenen dades o linies d'altres persones.

Exemples:

- alumne veu factures on ell es receptor;
- empresa veu factures emeses a l'empresa;
- alumne no veu factura de grup amb altres participants;
- admin veu factures i estats fiscals.

## 12. Documents relacionats

- `documentacio-verifactu.md`
- `04-fluxos-facturacio.md`
- `05-model-bd-sif.md`
- `07-pantalles-intranet.md`
- `10-procediments-intranet-ecommerce.md`
- `11-inventari-canvis-pendents.md`
- `documentacio-sif-aeat.md`
