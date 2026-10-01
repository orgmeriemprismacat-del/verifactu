# Índex de fonts de codi-drive — 25/09/2026

> **Tall d'auditoria del 25/09/2026.** Es conserva com a evidència del codi revisat aquell dia. Per a l'estat actual, cal revalidar contra `main`, `codi-drive/README.md`, els UC i les proves posteriors.

Tall local: `1001d37cbc84d0241b57f58a1153c5c59567237e`. Inventari de **5837 fitxers**, amb **2252 fonts de codi/configuració textual inspeccionades automàticament** i 1998 continguts font diferents per SHA-256.

L’inventari cobreix les set aplicacions. La lectura semàntica dirigida es documenta a l’informe; no s’ha llegit manualment cada línia, executat les aplicacions ni acreditat el desplegament. Biblioteques i còpies es classifiquen per indicis de nom/capçalera, no per activitat efectiva.

[Índex complet de fonts, mètodes, entrades, SQL i referències](index-fonts.json). No conté cossos de funció, dades de formularis, contingut documental ni credencials. Els noms de mètode/taula provenen de patrons textuals i poden incloure comentaris; validar cada traça abans de modificar UML.

| Aplicació | Fitxers | Fonts | PHP | JS | Biblioteques (indici) | Còpia/prova (indici) | Fonts referenciades als documents |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| intranet-actual | 657 | 474 | 336 | 129 | 3 | 82 | 46 |
| intranet-alumne-actual | 101 | 69 | 60 | 9 | 6 | 6 | 1 |
| intranet-collaboradors | 3523 | 494 | 471 | 23 | 6 | 222 | 0 |
| intranet-nova-canvis-verifactu | 2 | 2 | 2 | 0 | 0 | 0 | 0 |
| old-intranet | 760 | 601 | 576 | 22 | 210 | 84 | 0 |
| pay-prisma-cat-canvis-verifactu | 23 | 23 | 23 | 0 | 0 | 0 | 0 |
| arrel | 1 | 0 | 0 | 0 | 0 | 0 | 0 |
| web-actual | 770 | 589 | 481 | 107 | 3 | 81 | 40 |

## Com reprendre una fitxa

1. Cercar la família i el punt d’entrada a `index-fonts.json`.
2. Llegir el codi concret a les línies indicades; seguir JS → endpoint → mètode → SQL.
3. Contrastar els documents ja referenciats, variants, permisos i efectes.
4. Preguntar només decisions no resoltes per codi/documentació; separar implementació actual i regla desitjada.

## Comparació actual de candidates

| Candidat | Homòleg de ruta | Resultat | Referència literal a endpoints SIF |
| --- | --- | --- | --- |
| intranet-nova-canvis-verifactu/ajax/alumnes/efectuarPagament.php | intranet-actual/ajax/alumnes/efectuarPagament.php | identic | No detectada |
| intranet-nova-canvis-verifactu/Intranet.php | intranet-actual/Intranet.php | diferent | No detectada |
| pay-prisma-cat-canvis-verifactu/ajax/efectuarPagament.php | web-actual/ajax/efectuarPagament.php | identic | No detectada |
| pay-prisma-cat-canvis-verifactu/ajax/efectuarPagamentRegal.php | web-actual/ajax/efectuarPagamentRegal.php | identic | No detectada |
| pay-prisma-cat-canvis-verifactu/ajax/efectuarPagamentRegalAutomatic.php | web-actual/ajax/efectuarPagamentRegalAutomatic.php | identic | No detectada |
| pay-prisma-cat-canvis-verifactu/codificarHash.php | intranet-actual/codificarHash.php | identic | No detectada |
| pay-prisma-cat-canvis-verifactu/ConnexioBBDD_PreparedStatment.php | web-actual/ConnexioBBDD_PreparedStatment.php | identic | No detectada |
| pay-prisma-cat-canvis-verifactu/ConnexioIntranet.php | intranet-actual/ConnexioIntranet.php | identic | No detectada |
| pay-prisma-cat-canvis-verifactu/ConnexioPay.php | No identificat | sense_homoleg_mateixa_ruta | No detectada |
| pay-prisma-cat-canvis-verifactu/ConnexioWeb.php | intranet-actual/ConnexioWeb.php | diferent | No detectada |
| pay-prisma-cat-canvis-verifactu/doit.php | No identificat | sense_homoleg_mateixa_ruta | No detectada |
| pay-prisma-cat-canvis-verifactu/inc/apiRedsys.php | web-actual/inc/apiRedsys.php | identic | No detectada |
| pay-prisma-cat-canvis-verifactu/PagamentCursAutomatic.php | web-actual/PagamentCursAutomatic.php | diferent | No detectada |
| pay-prisma-cat-canvis-verifactu/PagamentGrupAutomatic.php | web-actual/PagamentGrupAutomatic.php | diferent | No detectada |
| pay-prisma-cat-canvis-verifactu/PagamentRegalAutomatic.php | web-actual/PagamentRegalAutomatic.php | diferent | No detectada |
| pay-prisma-cat-canvis-verifactu/PagamentTallerAutomatic.php | web-actual/PagamentTallerAutomatic.php | identic | No detectada |
| pay-prisma-cat-canvis-verifactu/pagina_efectuar_pagament_automatic.php | web-actual/pagina_efectuar_pagament_automatic.php | diferent | No detectada |
| pay-prisma-cat-canvis-verifactu/pagina_efectuar_pagament_grup_automatic.php | web-actual/pagina_efectuar_pagament_grup_automatic.php | diferent | No detectada |
| pay-prisma-cat-canvis-verifactu/pagina_efectuar_pagament_regal_automatic.php | web-actual/pagina_efectuar_pagament_regal_automatic.php | diferent | No detectada |
| pay-prisma-cat-canvis-verifactu/pagina_efectuar_pagament_taller_automatic.php | web-actual/pagina_efectuar_pagament_taller_automatic.php | diferent | No detectada |
| pay-prisma-cat-canvis-verifactu/realitzaPagamentAutomatic.php | web-actual/realitzaPagamentAutomatic.php | diferent | No detectada |
| pay-prisma-cat-canvis-verifactu/realitzaPagamentGrupAutomatic.php | web-actual/realitzaPagamentGrupAutomatic.php | identic | No detectada |
| pay-prisma-cat-canvis-verifactu/realitzaPagamentPackAutomatic.php | web-actual/realitzaPagamentPackAutomatic.php | diferent | No detectada |
| pay-prisma-cat-canvis-verifactu/realitzaPagamentRegalAutomatic.php | web-actual/realitzaPagamentRegalAutomatic.php | diferent | No detectada |
| pay-prisma-cat-canvis-verifactu/realitzaPagamentTallerAutomatic.php | web-actual/realitzaPagamentTallerAutomatic.php | identic | No detectada |

## Famílies orientatives per nom

Un fitxer pot aparèixer en diverses famílies; no equival a casos d’ús independents.

| Família | Fonts a revisar (sense indicis de biblioteca/còpia) |
| --- | ---: |
| inscripcions | 79 |
| tastets | 27 |
| pagaments | 145 |
| factures | 67 |
| alumnes | 197 |
| cataleg | 381 |
| comunicacions | 48 |
| campus | 48 |
| descomptes | 52 |
| seguretat | 11 |
| documents | 0 |
