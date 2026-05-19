# 15 - Estat final del sistema

> Document d'estat final. Explica com ha de funcionar el sistema complet quan l'adaptacio VERI*FACTU estigui acabada.

## 1. Objectiu

Descriure el sistema final sense parlar de canvis pendents.

Quan el projecte estigui complet, PrisMa tindra:

- un SIF centralitzat;
- ecommerce i intranet com a clients del SIF;
- numeracio fiscal central;
- hash chain global;
- cua AEAT;
- PDF/QR immutable;
- pagaments separats de factures;
- relacions fiscals per UUID;
- consultes controlades per permisos.

## 2. Arquitectura final

```text
Ecommerce / Intranet / Redsys / Transferencies
        |
        v
pay.prisma.cat - API SIF + panell intern SIF
        |
        v
BD fiscal SIF
        |
        +--> factures i linies
        +--> registres fiscals i hash chain
        +--> cua AEAT
        +--> documents PDF/XML/QR
        +--> pagaments i assignacions
```

El domini `pay.prisma.cat` sera la font oficial del SIF.

La intranet principal continuara sent l'eina de treball diaria, pero en materia fiscal:

- enllaçara al panell SIF;
- mostrara indicador visual de pendents i resum d'incidencies;
- podra consultar dades del SIF amb permisos;
- no sera la font de veritat de factures, registres, documents, versions o incidencies fiscals.

Panell previst:

```text
pay.prisma.cat/sif
    /dashboard
    /factures
    /registres
    /incidencies
    /documents
    /versions
    /exports
    /configuracio
```

## 3. Regla principal

```text
Els canals creen operacions.
El SIF crea factures.
```

## 4. Factura i pagament

Factura i pagament son elements diferents.

Una factura pot:

- estar pendent de cobrament;
- estar parcialment cobrada;
- estar cobrada;
- estar rectificada;
- estar cancel·lada.

Un pagament pot:

- pagar una factura nova;
- pagar una factura ja emesa;
- pagar diverses factures;
- ser parcial;
- ser devolucio;
- ser compensacio.

## 5. Casos finals principals

- Curs normal: una factura, una linia.
- Pack: una factura, una linia per curs.
- Grup: una factura, una linia per participant.
- Regal: factura al comprador, inscripcio posterior sense nova factura.
- USOC: factura alumne per la seva part i factura USOC per la diferencia.
- Factura abans de cobrar: factura real pendent de cobrament.
- Canvi de curs: event auditable amb accio fiscal si cal.
- Baixa: event administratiu; devolucio/saldo posterior.
- Morositat: reclamacio sense rectificativa automatica.

## 6. Consulta final

La intranet i l'ecommerce han de permetre consultar factures segons permisos:

- alumne: factures on sigui receptor;
- empresa/responsable: factures emeses al seu nom;
- admin: consulta completa;
- participant d'un grup: no veu factura de grup si conte dades d'altres persones.

## 7. Documentacio i evidencia interna

La documentacio fiscal i tecnica que s'hagi de conservar com a evidencia interna estara disponible des del panell del SIF.

La intranet principal podra tenir un apartat `VERI*FACTU` amb acces a:

- panell SIF;
- documents SIF;
- incidencies pendents;
- exportacions;
- indicador visual de pendents.
