# Còpies de codi PrisMa per contrastar la integració SIF

Aquest directori conserva còpies locals de diferents aplicacions PrisMa. No és el nou nucli SIF i no s'ha de desplegar directament des d'aquí. Serveix per identificar els punts de pagament i facturació que s'han d'adaptar al SIF centralitzat a `pay.prisma.cat`.

## Carpetes i significat

| Carpeta | Significat declarat | Ús en l'anàlisi |
| --- | --- | --- |
| `intranet-nova-canvis-verifactu` | Proposta d'intranet amb canvis VERI*FACTU | Candidata que s'ha de comparar amb `intranet-actual`; no es considera integrada només pel nom. |
| `pay-prisma-cat-canvis-verifactu` | Proposta de `pay.prisma.cat` amb canvis VERI*FACTU | Candidata de pagament; s'ha de connectar amb el nucli `sif/`. |
| `intranet-actual` | Intranet actual sense canvis VERI*FACTU | Font del flux intern de pagaments, factures, devolucions, baixes, entitats, descomptes i reclamacions. |
| `web-actual` | Web/ecommerce actual sense canvis VERI*FACTU | Font d'inscripcions, productes, pàgines de pagament, Redsys i callbacks llegats. |
| `intranet-alumne-actual` | Intranet d'alumnes actual sense canvis VERI*FACTU | Canal de consulta i generació d'enllaços de pagament. |
| `old-intranet` | Intranet vella sense canvis VERI*FACTU | Històric procedimental i referència de migració. |
| `intranet-collaboradors` | Intranet de tutors/col·laboradors sense canvis VERI*FACTU | Circuit adjacent de cobraments i factures de proveïdors/tutors; no s'equipara a vendes SIF. |

## Tall d'inventari del 2026-09-15

| Carpeta | Fitxers totals | PHP |
| --- | ---: | ---: |
| `intranet-nova-canvis-verifactu` | 2 | 2 |
| `pay-prisma-cat-canvis-verifactu` | 23 | 23 |
| `intranet-actual` | 1.638 | 342 |
| `web-actual` | 792 | 443 |
| `intranet-alumne-actual` | 101 | 60 |
| `old-intranet` | 769 | 579 |
| `intranet-collaboradors` | 3.523 | 471 |
| **Total** | **6.848** | **1.920** |

Els 1.920 PHP inclouen còpies datades, proves, biblioteques incorporades i els 25 fitxers candidats. El recompte no equival a 1.920 components de domini ni a 1.920 fitxers que s'hagin de migrar.

## Resultat de la comparació inicial

S'han comparat els 25 PHP de les dues carpetes candidates amb l'homòleg de `intranet-actual` o `web-actual` quan existeix:

- 14 fitxers són idèntics;
- 8 fitxers són diferents;
- 3 fitxers no tenen homòleg directe amb el mateix camí funcional;
- no s'ha detectat cap crida a `POST /api/factures/issue`, `POST /api/payments/register` ni `POST /api/redsys/callback`.

Per tant, les carpetes candidates expressen una intenció de canvi, però encara no demostren la substitució completa de les escriptures llegades per adaptadors cap al SIF. El detall és a `documentacio/04-estat-final/37-auditoria-comparativa-codi-drive.md`.

## Frontera arquitectònica acordada

La intranet i la web continuen sent canals de negoci. L'apartat de pagament que avui executen s'ha de programar al SIF de `pay.prisma.cat`:

```text
intranet / web / intranet alumne
              |
              v
adaptador autenticat de servidor
              |
              v
SIF a pay.prisma.cat -> factura, cobrament, Redsys, documents i traça fiscal
```

Les bases de dades llegades aporten la instantània necessària o reben una sincronització posterior al commit SIF. No són la font de veritat d'una factura SIF emesa.

## Seguretat i ús del directori

Les còpies actuals poden contenir configuració o literals sensibles. Els patrons de fitxers `parametres-connexio*.php` estan ignorats, però això no garanteix que la resta del codi estigui sanejat. Abans d'afegir qualsevol còpia a Git cal fer una revisió de secrets, eliminar credencials del codi i carregar-les des d'un magatzem o entorn protegit.

No s'ha de fer commit, push ni desplegament d'aquestes carpetes sense una decisió explícita i una revisió prèvia.
