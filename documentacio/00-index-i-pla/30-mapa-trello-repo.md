# 30 — Mapa Trello ↔ repositori (vigent)

**Data de revisió: 2026-09-24.** La font operativa de l'estructura, els 12 taulers i els recomptes paginats actuals és [l'índex de control de Trello](../../00-control/trello/README.md). Aquest document substitueix el mapa antic que només descrivia quatre taulers i exports locals de juny.

## Relació entre responsabilitats i fonts

| Dimensió | Tauler | Evidència tècnica/documental |
|---|---|---|
| Decisions, riscos, pla | 01 Control | `00-control/estat-projecte.md`, `00-control/pla-reconciliat-r2.md`, `00-control/registre-decisions.md` |
| Cas d'ús i variants | 02a Casos | `documentacio/04-estat-final/33-casos-us-sif.md`, `documentacio/06-fitxes-funcionals/` |
| Fitxa funcional verificable | 02b Fitxes | `documentacio/06-fitxes-funcionals/` |
| Nucli i API compartida | 03 Desenvolupament | codi SIF, esquemes i contractes del repo |
| Pantalles, permisos i notificacions | 04 Intranet | `documentacio/03-canvis-pendents/07-pantalles-intranet.md`, contractes UI |
| Proves, entorns, desplegament | 05 Proves | `documentacio/05-governanca-operacio/20-pla-proves-validacio-sif.md`, evidències reals |
| Panell i servei pay.prisma.cat | 06 SIF pay | `documentacio/04-estat-final/25-panell-sif-pay-prisma.md`, codi pay |
| Redsys i conciliació | 07 Pagaments | `documentacio/03-canvis-pendents/06-integracio-redsys-pay-prisma.md`, callback i worker |
| Manuals i dossier d'auditoria | 08 Documentació | `documentacio/01-compliment-aeat/`, `documentacio/05-governanca-operacio/` |
| Web, ecommerce i checkout | 09 Web | codi web, checkout, adaptadors d'inici de pagament |
| Migració, històric, legacy | 10 Migració | migracions SQL, mapping i sincronització de resums legacy |
| Enviament AEAT i registre fiscal | 11 AEAT | model de registre, XML/XSD, cua AEAT, respostes i reintents |

## Control de qualitat

Per cada paquet crític: `UC / decisió -> contracte -> targeta propietària d'implementació -> codi/commit -> test executable -> evidència -> document de versió`. Un mateix flux pot necessitar diverses targetes si **cada una té un resultat diferent**; mai convertir el mateix resultat en 3 tasques de programació duplicades. La mera existència de targetes o fitxers no demostra que un canal estigui integrat ni provat. Cal evidència executable per marcar-lo com a finalitzat.

No traslladar automàticament les targetes antigues. Abans de moure-les, reconciliar per ID, l'abast i el codi real; mantenir la targeta originària quan sigui possible. Les fotografies del control Trello només són recomptes i **no** repliquen el contingut privat de les targetes en aquest repositori públic.
