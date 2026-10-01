# Superfície HTTP i panell SIF

Aquesta carpeta conté les peces exposables del SIF.

## Carpetes

- `api/`: endpoints HTTP consumits per canals interns o integracions.
- `sif/`: superfície del panell/operació pròpia de `pay.prisma.cat`.

## Regles de frontera

Els endpoints han de ser prims: autenticar, validar contracte, aplicar controls de seguretat i delegar la lògica als serveis del SIF.

Per operacions mutables cal revisar, quan aplica:

- mètode HTTP correcte;
- autenticació i permisos;
- CSRF o signatura server-to-server;
- same-origin/CORS quan correspongui;
- idempotència;
- validació de payload;
- resposta d'estat explícita;
- log/auditoria;
- proves de contracte i integració.

## Seguretat

No s'han d'acceptar com a autoritatius valors sensibles procedents directament del navegador si el SIF els pot resoldre des de sessió, BD o un sistema de confiança.

La presència d'un endpoint al repositori no implica que estigui activat en producció: comprovar flags, configuració, preflight, CI i evidència d'entorn.
