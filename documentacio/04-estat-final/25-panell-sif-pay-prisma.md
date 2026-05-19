# 25 - Panell intern del SIF a pay.prisma.cat

> Document d'estat final. Defineix el panell intern del SIF que viura a `pay.prisma.cat` i la seva relacio amb la intranet principal.

## 1. Decisio

`pay.prisma.cat` sera la font oficial del SIF PrisMa.

La intranet principal no substituira el panell del SIF. La intranet tindra un apartat `VERI*FACTU` amb indicador visual de pendents, resum d'incidencies i accessos connectats al SIF, pero la informacio fiscal oficial viura al SIF i a la BD fiscal.

## 2. URL prevista

```text
https://pay.prisma.cat/sif
```

La URL exacta pot ajustar-se quan la responsable tecnica configuri el subdomini i SSL. Comvive queda com a proveidor d'infraestructura/servidor si aplica, pero la configuracio funcional del subdomini i SSL la gestiona Meriem.

## 3. Modul principal

```text
SIF PrisMa
    Dashboard
    Factures
    Registres AEAT
    Incidencies
    Documents
    Versions
    Exportacions
    Configuracio
```

## 3.1. Dashboard

Objectiu:

- donar una visio rapida de l'estat fiscal del SIF.

Ha de mostrar:

- factures emeses avui/mes/any;
- registres AEAT pendents, acceptats, rebutjats o en retry;
- incidencies obertes per prioritat;
- cua PDF/QR pendent;
- ultima factura emesa;
- estat general de connexio AEAT;
- versio activa del SIF.

Accions:

- anar a incidencies;
- anar a registres AEAT;
- anar a factures;
- anar a exports.

## 3.2. Factures

Objectiu:

- consultar factures fiscals emeses pel SIF.

Ha de permetre:

- cercar per numero visible;
- cercar per UUID;
- cercar per NIF/CIF receptor;
- cercar per `FACTURA_RELACIONADA`;
- filtrar per estat factura;
- filtrar per estat AEAT;
- filtrar per estat cobrament;
- veure linies de factura;
- veure pagaments associats;
- veure rectificatives;
- veure documents PDF/XML/QR;
- veure origen: ecommerce, Redsys, intranet, transferencia, manual, grup, pack, regal, USOC.

Accions:

- descarregar PDF;
- veure QR;
- consultar registre fiscal;
- anar a factura relacionada;
- iniciar rectificativa si el rol ho permet;
- marcar factura electronica si correspon i el rol ho permet.

No ha de permetre:

- editar una factura emesa directament;
- canviar imports, receptor o concepte sense rectificativa/event.

## 3.3. Registres AEAT

Objectiu:

- controlar l'estat dels registres fiscals i l'enviament a AEAT.

Ha de mostrar:

- registre de factura;
- tipus registre;
- hash;
- hash anterior;
- fiscal order;
- estat AEAT;
- data creacio;
- data enviament;
- resposta AEAT;
- errors;
- intents;
- proper retry.

Accions:

- veure detall tecnic;
- reintentar enviament si el rol ho permet;
- exportar registres;
- crear incidencia si hi ha error no resolt.

## 3.4. Incidencies

Objectiu:

- gestionar problemes fiscals, tecnics o documentals del SIF.

Taula base decidida:

- `errors_verifactu`, si no cal crear una taula nova;
- es podra afegir un log auxiliar d'accions si la resolucio d'incidencies necessita historial detallat.

Tipus:

- error AEAT;
- retries fallits;
- PDF/QR no generat;
- pagament cobrat sense factura;
- factura pendent sense cobrament;
- callback Redsys duplicat;
- dades fiscals incompletes;
- exportacio requerida;
- revisio manual.

Ha de mostrar:

- prioritat;
- estat;
- factura afectada;
- origen;
- error tecnic;
- responsable assignat;
- data creacio;
- ultima actualitzacio;
- historial d'accions.

Accions:

- assignar responsable;
- afegir nota;
- marcar com revisada;
- marcar com resolta;
- anar a factura o registre afectat;
- crear notificacio a la intranet si cal.

## 3.5. Documents

Objectiu:

- consultar documents generats o conservats pel SIF.

Inclou:

- PDF factura;
- XML registre/factura si correspon;
- QR;
- declaracio responsable signada;
- documentacio tecnica SIF;
- documents de versio.

Ha de mostrar:

- tipus document;
- UUID factura si aplica;
- ruta interna;
- hash del fitxer;
- data generacio;
- estat;
- si s'ha enviat per correu.

Accions:

- descarregar;
- regenerar nomes documents regenerables i deixant log;
- veure hash;
- veure historial.

## 3.6. Versions

Objectiu:

- controlar versions del SIF i documentacio associada.

Ha de mostrar:

- versio activa;
- data d'entrada en produccio;
- responsable tecnic;
- responsable legal/direccio;
- canvis inclosos;
- declaracio responsable associada;
- estat: esborrany, activa, historica.

Accions:

- consultar versio;
- descarregar declaracio responsable;
- consultar historial;
- marcar nova versio com activa nomes si el rol ho permet.

## 3.7. Exportacions

Objectiu:

- generar i conservar exportacions fiscals per revisio, auditoria o requeriment.

Ha de permetre exportar:

- factures per periode;
- registres AEAT;
- registres d'events;
- incidencies;
- documents vinculats;
- relacions factura-inscripcio/pagament.

Ha de guardar:

- qui ha fet l'export;
- quan;
- criteris utilitzats;
- fitxer generat;
- hash del fitxer;
- motiu si es per requeriment.

## 3.8. Configuracio

Objectiu:

- administrar parametres tecnics del SIF.

Ha d'incloure:

- dades emissor fiscal;
- series actives;
- mode VERI*FACTU;
- endpoints AEAT;
- certificat digital o configuracio segura associada;
- parametres de retries;
- rutes de documents;
- permisos/rols del panell;
- estat de processos automatics.

Acces:

- nomes responsable tecnic o administrador SIF.

No ha de permetre canvis sense log.

## 4. Documentacio dins del SIF

Apartat:

```text
SIF / Sistema / Documentacio
```

Ha de permetre consultar:

- declaracio responsable signada;
- versio activa;
- documentacio tecnica del SIF;
- diccionari de camps i valors;
- documentacio de permisos;
- historial de versions.

### 4.1. Inventari documental minim

L'apartat de documentacio del SIF ha de mostrar com a minim:

| Document o evidencia | Obligatori dins SIF | Observacions |
| --- | --- | --- |
| Declaracio responsable signada | Si | Associada a la versio activa. |
| Versio activa del SIF | Si | Nom intern, codi, data entrada produccio i responsables. |
| Historial de versions | Si | Versions actives i historiques. |
| Documentacio tecnica del SIF | Si | Resum tecnic/organitzatiu. |
| Diccionari de camps i valors | Si | Estats, rols, accions i codis controlats. |
| Documentacio de permisos | Si | Matriu de rols i accions. |
| Registres fiscals | Si | Consulta/exportacio. |
| PDFs/XML/QR de factures | Si | Amb hash i control de permisos. |
| Exportacions fiscals | Si | Amb registre de qui, quan i criteris. |
| Incidencies SIF | Si | Amb historial d'accions. |
| Logs/events | Si | Consulta tecnica i auditora. |
| Pla de proves/evidencies | Recomanat | Especialment per canvis de versio. |

### 4.2. Versio activa i declaracio responsable

La pantalla ha de permetre veure:

- nom intern del SIF;
- versio activa;
- data d'entrada en produccio;
- responsable funcional i tecnica;
- responsable legal/direccio;
- declaracio responsable associada;
- estat de la declaracio: esborrany / signada / historica;
- canvis inclosos a la versio;
- data de propera revisio si aplica.

Regla:

```text
No hi ha versio activa sense registre de versio.
No hi ha declaracio signada sense associar-la a una versio concreta.
```

## 4.3. Processos automatics del SIF

Els processos automatics del SIF no son usuaris humans.

Inclouen:

- proces de cua AEAT;
- proces de generacio PDF/QR/XML;
- proces de retries;
- tasques programades de manteniment o alertes.

Han de tenir:

- permisos tecnics minims;
- accions predefinides;
- log obligatori;
- identificador d'execucio;
- registre d'errors;
- control per evitar doble processament.

No poden:

- decidir rectificatives manualment;
- resoldre incidencies funcionals;
- canviar configuracio sense ordre tecnica;
- actuar sense deixar rastre.

## 5. Incidencies del SIF

Apartat:

```text
SIF / Incidencies
```

Ha de permetre:

- veure incidencies obertes;
- filtrar per prioritat, tipus i estat;
- veure factura afectada;
- veure error tecnic;
- assignar responsable;
- registrar accions;
- marcar com resolta;
- conservar log de resolucio.

La intranet principal pot mostrar notificacions d'aquestes incidencies, pero la resolucio oficial s'ha de fer al SIF.

## 6. Relacio amb la intranet principal

La intranet principal tindra un apartat:

```text
VERI*FACTU + indicador visual de pendents
    Obrir panell SIF
    Resum d'incidencies pendents
    Documents SIF
    Exportacions
```

Regla:

```text
La intranet mostra resum, indicador visual i accessos.
El SIF conserva i gestiona la informacio fiscal oficial.
```

## 7. Acces auditor / AEAT

El panell SIF ha de poder tenir un rol de nomes lectura:

```text
AUDITOR_FISCAL
AEAT_READONLY
```

Permisos:

- veure declaracio responsable;
- veure versio activa;
- consultar registres fiscals;
- exportar registres si correspon;
- consultar registre d'events;
- consultar documents fiscals;
- consultar incidencies fiscals.

No pot:

- crear factures;
- registrar pagaments;
- modificar dades;
- resoldre incidencies;
- generar rectificatives.
