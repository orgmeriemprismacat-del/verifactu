# Mapa de xats del projecte

Aquest document indica quins xats crear i quin text enganxar a cada un.

## Xat 0 - Xat pont de recuperacio documental

Quan crear-lo:
Ara, abans de crear xats especialitzats.

Objectiu:
Recuperar informacio del xat antic i completar documents.

Text inicial:
Fer servir el text de `00-control/guia-xat-pont.md`.

Documents que ha de llegir:

- `README.md`
- `00-control/estat-projecte.md`
- `00-control/registre-decisions.md`
- `00-control/informacio-a-recuperar-del-xat-antic.md`
- `00-control/checklist-completitud.md`
- `00-control/mapa-xats.md`
- `documentacio/README.md`

## Xat 1 - Compliment AEAT i declaracio responsable

Quan crear-lo:
Quan el xat pont hagi revisat els temes AEAT del xat antic.

Text inicial:

```text
Treballarem l'apartat de compliment AEAT i declaracio responsable del projecte VERI*FACTU PrisMa.

Carpeta del projecte:
C:\Users\Usuario\Documents\Codex\2026-05-19\hola-com-faig-per-recuperar-tota\projecte-verifactu-pont

Llegeix primer:
- 00-control/estat-projecte.md
- 00-control/registre-decisions.md
- 00-control/checklist-completitud.md
- documentacio/README.md
- documentacio/01-compliment-aeat/documentacio-sif-aeat.md
- documentacio/01-compliment-aeat/declaracio-responsable-sif-prisma.md
- documentacio/05-governanca-operacio/19-registre-versions-i-canvis-sif.md
- documentacio/05-governanca-operacio/21-seguretat-permisos-accessos.md
- documentacio/05-governanca-operacio/24-diccionari-camps-i-valors.md

Objectiu:
Revisar i completar la documentacio de compliment AEAT, declaracio responsable, versio activa, seguretat i camps fiscals.

No llegeixis tot el xat antic si no es imprescindible. Si detectes que falta context, demana al xat pont que busqui el tema concret.
```

## Xat 2 - Arquitectura SIF, BD i hash chain

Quan crear-lo:
Quan el xat pont hagi revisat arquitectura, bases de dades i relacions.

Text inicial:

```text
Treballarem l'arquitectura del SIF, la base de dades fiscal i la cadena hash del projecte VERI*FACTU PrisMa.

Carpeta del projecte:
C:\Users\Usuario\Documents\Codex\2026-05-19\hola-com-faig-per-recuperar-tota\projecte-verifactu-pont

Llegeix primer:
- 00-control/estat-projecte.md
- 00-control/registre-decisions.md
- documentacio/README.md
- documentacio/00-index-i-pla/documentacio-verifactu.md
- documentacio/02-context-i-estat-actual/13-mapa-bases-dades-i-taules.md
- documentacio/04-estat-final/05-model-bd-sif.md
- documentacio/04-estat-final/17-estat-final-bd-relacions.md
- documentacio/05-governanca-operacio/24-diccionari-camps-i-valors.md

Objectiu:
Consolidar el model tecnic del SIF centralitzat, taules, relacions, sequencies, hash chain i punts d'integracio amb sistemes existents.
```

## Xat 3 - Fluxos de facturacio i casos fiscals

Quan crear-lo:
Quan el xat pont hagi revisat casos de facturacio i excepcions.

Text inicial:

```text
Treballarem els fluxos de facturacio, rectificatives, devolucions, baixes, canvis de curs, pagaments parcials i excepcions fiscals.

Carpeta del projecte:
C:\Users\Usuario\Documents\Codex\2026-05-19\hola-com-faig-per-recuperar-tota\projecte-verifactu-pont

Llegeix primer:
- 00-control/estat-projecte.md
- 00-control/registre-decisions.md
- documentacio/README.md
- documentacio/03-canvis-pendents/04-fluxos-facturacio.md
- documentacio/03-canvis-pendents/11-inventari-canvis-pendents.md
- documentacio/04-estat-final/15-estat-final-sistema.md
- documentacio/04-estat-final/18-estat-final-operacio-incidencies.md

Objectiu:
Completar els fluxos fiscals i assegurar que cada cas operatiu te tractament documental clar.
```

## Xat 4 - Pagaments, Redsys i pay.prisma.cat

Quan crear-lo:
Quan el xat pont hagi revisat tota la informacio sobre pagaments i Redsys.

Text inicial:

```text
Treballarem pagaments, Redsys, callbacks, conciliacio i el paper de pay.prisma.cat dins el projecte VERI*FACTU PrisMa.

Carpeta del projecte:
C:\Users\Usuario\Documents\Codex\2026-05-19\hola-com-faig-per-recuperar-tota\projecte-verifactu-pont

Llegeix primer:
- 00-control/estat-projecte.md
- 00-control/registre-decisions.md
- documentacio/README.md
- documentacio/03-canvis-pendents/06-integracio-redsys-pay-prisma.md
- documentacio/04-estat-final/25-panell-sif-pay-prisma.md
- documentacio/04-estat-final/17-estat-final-bd-relacions.md
- documentacio/03-canvis-pendents/04-fluxos-facturacio.md

Objectiu:
Definir com els pagaments entren al SIF, com es concilien i que ha de quedar a pay.prisma.cat.
```

## Xat 5 - Intranet, pantalles i operacio interna

Quan crear-lo:
Quan el xat pont hagi revisat pantalles, rols i operacio.

Text inicial:

```text
Treballarem pantalles d'intranet, panell SIF, permisos, avisos, procediments interns i operacio diaria.

Carpeta del projecte:
C:\Users\Usuario\Documents\Codex\2026-05-19\hola-com-faig-per-recuperar-tota\projecte-verifactu-pont

Llegeix primer:
- 00-control/estat-projecte.md
- 00-control/registre-decisions.md
- documentacio/README.md
- documentacio/03-canvis-pendents/07-pantalles-intranet.md
- documentacio/03-canvis-pendents/10-procediments-intranet-ecommerce.md
- documentacio/04-estat-final/16-estat-final-pantalles.md
- documentacio/04-estat-final/25-panell-sif-pay-prisma.md
- documentacio/05-governanca-operacio/21-seguretat-permisos-accessos.md
- documentacio/05-governanca-operacio/22-manual-operatiu-intern.md

Objectiu:
Convertir la documentacio tecnica en pantalles, permisos i procediments interns clars.
```

## Xat 6 - Proves, produccio i auditoria documental

Quan crear-lo:
Quan el xat pont hagi revisat posada en produccio, proves i governanca.

Text inicial:

```text
Treballarem proves, checklist de posada en produccio, auditoria documental i governanca del SIF.

Carpeta del projecte:
C:\Users\Usuario\Documents\Codex\2026-05-19\hola-com-faig-per-recuperar-tota\projecte-verifactu-pont

Llegeix primer:
- 00-control/estat-projecte.md
- 00-control/registre-decisions.md
- 00-control/checklist-completitud.md
- documentacio/README.md
- documentacio/03-canvis-pendents/09-checklist-posada-en-produccio.md
- documentacio/05-governanca-operacio/20-pla-proves-validacio-sif.md
- documentacio/00-index-i-pla/26-matriu-cobertura-casos.md
- documentacio/00-index-i-pla/27-informe-auditoria-documental.md
- documentacio/05-governanca-operacio/19-registre-versions-i-canvis-sif.md

Objectiu:
Assegurar que hi ha proves, evidencies, checklist i criteri de tancament per activar el SIF.
```

## Regla de tancament de qualsevol xat

Abans de tancar qualsevol xat, escriure:

```text
Abans d'acabar, actualitza els fitxers de control del projecte amb el que hem fet:
- 00-control/estat-projecte.md
- 00-control/registre-decisions.md
- 00-control/checklist-completitud.md

I indica quins documents de documentacio/ has modificat o recomanes revisar.
```

