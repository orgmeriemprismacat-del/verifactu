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
- Pagaments fraccionats: una factura pot rebre diversos cobraments i cada cobrament queda assignat.
- Compensacio/saldo: moviment economic documentat, no edicio d'import.
- Devolucio: moviment economic `REFUND` i rectificativa si redueix una factura emesa.
- Rectificativa: serie `R`, relacio directa amb factura rectificada i mode per diferencies o substitucio.
- Canvi de curs: event auditable amb accio fiscal si cal.
- Baixa: event administratiu; devolucio/saldo posterior.
- Factura manual: emissio controlada des de intranet amb `issueInvoice()`.
- Migracio historica: factures antigues conservades com `NO_VERIFACTU`, sense registre retroactiu.
- Morositat: reclamacio sense rectificativa automatica; si arriba cobrament sobre factura existent, entra com `registerPayment()` amb assignacio `CLAIM_PAYMENT`.

## 6. Fluxos fiscals finals

El sistema final separa clarament tres capes:

```text
event operatiu -> moviment economic -> accio fiscal
```

Regles finals:

- si el canvi passa abans d'emetre factura, es pot ajustar operacio o esborrany amb log;
- si el canvi passa despres d'emetre factura, no es modifica la factura: es genera rectificativa, complementaria, devolucio, saldo o compensacio segons cas;
- si factura i cobrament neixen junts, `issueInvoice()` incorpora el bloc `payment`;
- si la factura ja existeix, qualsevol cobrament posterior va per `registerPayment()`;
- una factura emesa abans de cobrament queda pendent fins que es registri el pagament;
- les baixes no toquen automaticament factura o pagament;
- les devolucions es registren despres del retorn real o decisio economica confirmada;
- els canvis de curs conserven historic, imports, descomptes, despeses de gestio i accio fiscal relacionada;
- la factura manual usa les mateixes garanties que qualsevol factura SIF;
- la migracio historica conserva informacio anterior sense crear registres VERI*FACTU retroactius.

## 7. Consulta final

La intranet i l'ecommerce han de permetre consultar factures segons permisos:

- alumne: factures on sigui receptor;
- empresa/responsable: factures emeses al seu nom;
- admin: consulta completa;
- participant d'un grup: no veu factura de grup si conte dades d'altres persones.

## 8. Documentacio i evidencia interna

La documentacio fiscal i tecnica que s'hagi de conservar com a evidencia interna estara disponible des del panell del SIF.

La intranet principal podra tenir un apartat `VERI*FACTU` amb acces a:

- panell SIF;
- documents SIF;
- incidencies pendents;
- exportacions;
- indicador visual de pendents.
