# 26 - Matriu de cobertura de casos

> Document intern de control. No es un document principal del SIF ni un document per inspeccio; serveix per no perdre casos mentre es documenten pantalles, fluxos, codi, correus, permisos i proves.

## 1. Llegenda

- `COBERT`: documentat amb criteri suficient per continuar disseny/implementacio.
- `DISSENY COBERT`: criteri funcional i tecnic decidit; queda implementar, provar o completar SQL/codi real.
- `PARCIAL`: documentat a nivell conceptual, pero falta detall de codi, pantalla, taules, correus o casuistica.
- `PENDENT`: identificat pero encara no desenvolupat.
- `DECISIO`: decisio documentada; no requereix flux complet ara mateix.

## 2. Canals de venda i pagament

| Cas | Estat | Documents principals | Falta completar |
| --- | --- | --- | --- |
| Curs normal Redsys | DISSENY COBERT | `03-canvis-pendents/04-fluxos-facturacio.md`, `03-canvis-pendents/06-integracio-redsys-pay-prisma.md` | Implementar substitucio de `realitzaPagamentAutomatic.php`, executar proves amb callback duplicat i confirmar payload final en codi. |
| Taller | DISSENY COBERT | `03-canvis-pendents/04-fluxos-facturacio.md` | Aplicar com a variant de curs amb concepte propi i provar URL/payload especific. |
| Jornada | DISSENY COBERT | `03-canvis-pendents/04-fluxos-facturacio.md` | Aplicar com a variant de curs amb concepte propi i provar URL/payload especific. |
| Pack | DISSENY COBERT | `03-canvis-pendents/04-fluxos-facturacio.md`, `04-estat-final/15-estat-final-sistema.md` | Falta incorporar esquema real de taules pack/preus i provar factura amb dues linies. |
| Grup de persones | DISSENY COBERT | `03-canvis-pendents/04-fluxos-facturacio.md`, `04-estat-final/15-estat-final-sistema.md` | Falta concretar pantalla/codi real i criteri final de DNI visible o nomes intern. |
| Regal | DISSENY COBERT | `03-canvis-pendents/04-fluxos-facturacio.md` | Falta incorporar SQL/taules reals de regals i provar bescanvi sense factura nova. |
| USOC | DISSENY COBERT | `03-canvis-pendents/04-fluxos-facturacio.md` | Implementar flux alumne + USOC i documentar dades fiscals de l'entitat USOC. |
| Empresa/responsable paga inscripcions | DISSENY COBERT | `03-canvis-pendents/10-procediments-intranet-ecommerce.md`, `03-canvis-pendents/11-inventari-canvis-pendents.md` | Implementar factura abans de cobrament, URL empresa i registre posterior del pagament amb `registerPayment()`. |
| Transferencia validada a intranet | PARCIAL | `03-canvis-pendents/10-procediments-intranet-ecommerce.md`, `03-canvis-pendents/07-pantalles-intranet.md` | Pantalla, JS/AJAX i consultes internes de pagament identificades; `efectuarPagament()` documentat. Falta cos de cerca/modal pagament, ordre d'updates i flux SIF final. |
| Compensacio/saldo | PARCIAL | `03-canvis-pendents/04-fluxos-facturacio.md`, `04-estat-final/05-model-bd-sif.md` | Criteri fiscal final de quan neix saldo i quan genera rectificativa. |
| Pagaments fraccionats | PARCIAL | `03-canvis-pendents/11-inventari-canvis-pendents.md` | Payloads, idempotencia per cada pagament i visualitzacio de factures/parcialitats. |
| Pagament morositat/reclamacio | PARCIAL | `03-canvis-pendents/04-fluxos-facturacio.md`, `03-canvis-pendents/08-correus-i-plantilles.md` | Rutes, plantilles i consultes internes de reclamacio identificades; falten metodes concrets, JS/AJAX i URL especifica de pagament. |

## 3. Facturacio i documents fiscals

| Cas | Estat | Documents principals | Falta completar |
| --- | --- | --- | --- |
| Factura ordinaria A | COBERT | `04-estat-final/05-model-bd-sif.md`, `03-canvis-pendents/04-fluxos-facturacio.md` | SQL definitiu i payload final. |
| Factura rectificativa R | PARCIAL | `03-canvis-pendents/04-fluxos-facturacio.md`, `04-estat-final/05-model-bd-sif.md` | Tipus rectificativa per canvi de dades fiscals, devolucio parcial/total i substitucio. |
| Factura abans de cobrament | DISSENY COBERT | `03-canvis-pendents/11-inventari-canvis-pendents.md`, `03-canvis-pendents/10-procediments-intranet-ecommerce.md` | Codi JS/AJAX actual identificat; falta implementar crida SIF, idempotencia i document final PDF/QR. |
| Factura manual | PARCIAL | `03-canvis-pendents/11-inventari-canvis-pendents.md` | Pantalla final, origen dades entitat/manual, linies i correus. |
| Factura electronica `E_FACT` | DISSENY COBERT | `03-canvis-pendents/07-pantalles-intranet.md`, `03-canvis-pendents/10-procediments-intranet-ecommerce.md` | Ubicacio decidida: `Alumnes / Consulta - Edita - Anula factura`. Permisos: Meriem, Adam i Pablo. |
| Proformes | DECISIO | `00-index-i-pla/documentacio-verifactu.md` | Decisio: no s'utilitzen com a document fiscal. Si apareixen documents no fiscals, s'han de documentar separat. |
| PDF immutable | COBERT | `04-estat-final/05-model-bd-sif.md`, `03-canvis-pendents/11-inventari-canvis-pendents.md` | Eleccio llibreria PDF i plantilla final. |
| QR | PARCIAL | `03-canvis-pendents/11-inventari-canvis-pendents.md`, `05-governanca-operacio/20-pla-proves-validacio-sif.md` | Dades exactes QR i plantilla visual. |
| XML/registre AEAT | PARCIAL | `04-estat-final/25-panell-sif-pay-prisma.md`, `04-estat-final/05-model-bd-sif.md` | Esquema XML definitiu i entorn de proves AEAT. |
| Migracio factures historiques | PARCIAL | `03-canvis-pendents/11-inventari-canvis-pendents.md` | Regles de migracio, camps de marca "historic no VERI*FACTU" i validacio numeracio. |

## 4. Descomptes, preus i linies

| Cas | Estat | Documents principals | Falta completar |
| --- | --- | --- | --- |
| Descompte Alumne PrisMa | PARCIAL | `00-index-i-pla/documentacio-verifactu.md` | Document de descomptes amb formula i snapshot final. |
| Carnet Jove | PARCIAL | `00-index-i-pla/documentacio-verifactu.md` | Documentar API/verificacio i text visible a factura. |
| USOC descompte/validacio | PARCIAL | `00-index-i-pla/documentacio-verifactu.md` | Relacio validacio intranet + factures alumne/USOC. |
| Discapacitat/familia nombrosa/monoparental/violencia genere | PARCIAL | `00-index-i-pla/documentacio-verifactu.md` | Confirmar text generic visible i motiu intern sensible. |
| Promocions temporals | PARCIAL | `00-index-i-pla/documentacio-verifactu.md` | SQL complet i criteri de data/pagament. |
| Codi promocional | PARCIAL | Notes de projecte | La logica esta descrita; falta esquema SQL, snapshot fiscal i proves de percentatge/import fix. |
| Descompte grup | PARCIAL | `03-canvis-pendents/04-fluxos-facturacio.md` | Relacio `descomptes_grup`, linia per participant i preu per participant. |
| Descompte excepcional per canvi de curs | PARCIAL | `03-canvis-pendents/11-inventari-canvis-pendents.md` | Formulari/motiu intern i text visible. |
| Linies de factura | COBERT | `04-estat-final/05-model-bd-sif.md` | SQL definitiu i exemples per cada cas. |

## 5. Intranet actual i nous apartats

| Apartat | Estat | Documents principals | Falta completar |
| --- | --- | --- | --- |
| Consulta - Modifica alumne | DISSENY COBERT | `03-canvis-pendents/07-pantalles-intranet.md`, `03-canvis-pendents/10-procediments-intranet-ecommerce.md` | Captures actuals rebudes; falta implementar pantalla final i afegir captures finals. |
| Dades del curs / dades pagament | DISSENY COBERT | `03-canvis-pendents/07-pantalles-intranet.md` | Captures actuals rebudes; falta implementar estats finals de URL/factura/pagament. |
| Canvi de curs | DISSENY COBERT | `03-canvis-pendents/04-fluxos-facturacio.md`, `03-canvis-pendents/07-pantalles-intranet.md` | Criteri fiscal definit; falta tancar taula canvi_curs/motiu_canvi i programar previsualitzacio. |
| Baixa | DISSENY COBERT | `03-canvis-pendents/04-fluxos-facturacio.md`, `03-canvis-pendents/07-pantalles-intranet.md` | Criteri baixa/saldo/devolucio definit; falta correus i pantalla final. |
| Veure factura | DISSENY COBERT | `03-canvis-pendents/07-pantalles-intranet.md` | Captura actual rebuda; falta implementar vista final amb VERI*FACTU/no VERI*FACTU, PDF i QR. |
| Passar pagaments | PARCIAL | `03-canvis-pendents/10-procediments-intranet-ecommerce.md`, `03-canvis-pendents/07-pantalles-intranet.md` | URL, pantalla, JS/AJAX i consultes internes documentades; `efectuarPagament()` i `mostrarModalConfPag()` identificats. Falta PHP de cerca/modal info, condicions exactes i migracio SIF. |
| Generar factura abans de pagar | DISSENY COBERT | `03-canvis-pendents/10-procediments-intranet-ecommerce.md`, `03-canvis-pendents/07-pantalles-intranet.md` | URL, PHP, JS i AJAX actuals documentats; falta substituir generacio local per SIF i validar `EMESA_ABANS_COBRAMENT`. |
| Consulta - Edita - Anula factura | PARCIAL | `03-canvis-pendents/10-procediments-intranet-ecommerce.md`, `03-canvis-pendents/07-pantalles-intranet.md` | URL, captures, JS/AJAX i wrappers `Intranet.php` documentats. Falta transformar edicio/anul·lacio en rectificatives SIF i bloquejar edicio directa. |
| Analitzar fitxer TPV / comprovar IDPAGs | PARCIAL | `03-canvis-pendents/10-procediments-intranet-ecommerce.md`, `03-canvis-pendents/07-pantalles-intranet.md` | Format CSV i codi actual de conciliacio documentats. Falta migrar conciliacio contra SIF/payment_transaction i criteri de reprocessament segur. |
| Factures manuals | PARCIAL | `03-canvis-pendents/11-inventari-canvis-pendents.md` | Pantalla, permisos, linies i correus. |
| Descarrega factures/registres | PARCIAL | `03-canvis-pendents/11-inventari-canvis-pendents.md`, `04-estat-final/25-panell-sif-pay-prisma.md` | Formats export i permisos. |
| Apartat VERI*FACTU intranet | COBERT | `03-canvis-pendents/07-pantalles-intranet.md`, `04-estat-final/25-panell-sif-pay-prisma.md` | Implementacio UI i endpoint resum. |
| Intranet alumne | PARCIAL | `03-canvis-pendents/10-procediments-intranet-ecommerce.md`, `04-estat-final/16-estat-final-pantalles.md` | Pantalla final, permisos, PDF/QR. |
| Empresa/responsable | DECISIO | `04-estat-final/16-estat-final-pantalles.md`, `05-governanca-operacio/21-seguretat-permisos-accessos.md` | No te acces a la intranet principal; consulta per correu, enllac segur o espai especific futur si es decideix. |
| Intranet tutor | DECISIO | `04-estat-final/16-estat-final-pantalles.md` | Confirmar que no te impacte fiscal. |

## 6. Panell SIF pay.prisma.cat/sif

| Apartat | Estat | Documents principals | Falta completar |
| --- | --- | --- | --- |
| Dashboard | COBERT | `04-estat-final/25-panell-sif-pay-prisma.md`, `03-canvis-pendents/10-procediments-intranet-ecommerce.md` | Endpoint i SQL. |
| Factures | COBERT | `04-estat-final/25-panell-sif-pay-prisma.md` | Endpoint i SQL. |
| Registres AEAT | COBERT | `04-estat-final/25-panell-sif-pay-prisma.md` | Endpoint i SQL. |
| Incidencies | COBERT | `04-estat-final/25-panell-sif-pay-prisma.md` | Es reutilitza `errors_verifactu` si no cal una taula nova; es pot afegir log auxiliar si el flux ho requereix. |
| Documents | COBERT | `04-estat-final/25-panell-sif-pay-prisma.md` | Rutes, permisos i hash. |
| Versions | COBERT | `04-estat-final/25-panell-sif-pay-prisma.md` | Flux d'activacio i declaracio signada. |
| Exportacions | COBERT | `04-estat-final/25-panell-sif-pay-prisma.md` | Formats i criteris d'export. |
| Configuracio | COBERT | `04-estat-final/25-panell-sif-pay-prisma.md` | Taules configuracio i secrets. |

## 7. Operacio, seguretat i compliment

| Cas | Estat | Documents principals | Falta completar |
| --- | --- | --- | --- |
| Declaracio responsable | PARCIAL | `01-compliment-aeat/declaracio-responsable-sif-prisma.md` | Signatura final i versio 1.0.0. |
| Documentacio SIF AEAT | PARCIAL | `01-compliment-aeat/documentacio-sif-aeat.md` | Ajust final quan el SIF estigui implementat. |
| Versions | COBERT | `05-governanca-operacio/19-registre-versions-i-canvis-sif.md` | Registre real de desplegaments. |
| Permisos i rols | DISSENY COBERT | `05-governanca-operacio/21-seguretat-permisos-accessos.md` | No es fara matriu exhaustiva inicial; falta aplicar permisos en codi, BD i pantalles. |
| Rol auditor/AEAT | PARCIAL | `05-governanca-operacio/21-seguretat-permisos-accessos.md` | Procediment d'activacio temporal i registre d'accessos. |
| Certificat digital AEAT | PENDENT | `03-canvis-pendents/09-checklist-posada-en-produccio.md` | Confirmar certificat o apoderament de l'entitat, ubicacio segura, permisos d'us i prova amb entorn AEAT quan correspongui. |
| Subdomini/SSL pay.prisma.cat | PARCIAL | `03-canvis-pendents/06-integracio-redsys-pay-prisma.md` | Configuracio real gestionada per Meriem i prova SSL. |
| Preproduccio / entorn de proves | DISSENY COBERT | `03-canvis-pendents/09-checklist-posada-en-produccio.md`, `05-governanca-operacio/20-pla-proves-validacio-sif.md` | Implementar BD o mode test separat, numeracio de proves, Redsys test/simulador i AEAT test si correspon. |
| Backups | PARCIAL | `03-canvis-pendents/09-checklist-posada-en-produccio.md` | Pla de backup BD fiscal + documents ja definit a nivell de criteri; falta implementacio real i prova de restauracio. |
| Proves i validacio | DISSENY COBERT | `05-governanca-operacio/20-pla-proves-validacio-sif.md` | Convertir proves en casos executables, executar paquet go/no-go i conservar evidencia real. |
| Manual operatiu intern | PARCIAL | `05-governanca-operacio/22-manual-operatiu-intern.md` | Omplir procediments per Adam/Pablo/gestio. |
| Captures finals | PENDENT | `05-governanca-operacio/23-annex-captures-pantalla.md` | Afegir quan hi hagi pantalles implementades. |
| Diccionari camps/valors | PARCIAL | `05-governanca-operacio/24-diccionari-camps-i-valors.md` | Afegir tots els valors finals de BD. |

## 8. Buidats prioritaris detectats

Aquest apartat es una llista interna de control, no un bloqueig per continuar treballant. Els punts es van tancant a mesura que es revisa cada apartat amb pantalla, JS/AJAX, metode de `Intranet.php`, taules, correus i permisos.

1. `Passar pagaments`: pantalla, JS/AJAX i consultes internes actuals identificats; falta PHP de cerca/modal info, condicions exactes i flux `issueInvoice()`/`registerPayment()`.
2. `Consulta - Edita - Anula factura`: captures, JS/AJAX i wrappers actuals identificats; falta rectificatives, devolucions i bloqueig d'edicio directa.
3. `Generar factura abans de pagar`: codi actual JS/AJAX identificat; falta implementar SIF, idempotencia, `EMESA_ABANS_COBRAMENT` i URL empresa.
4. `Analitzar fitxer TPV`: format actual i conciliacio contra `web.factures` identificats; falta conciliacio amb Redsys/SIF i reprocessament segur.
5. Codis promocionals: SQL, logica, snapshot i factura_linia.
6. Regals: SQL, codi regal, comprador, destinatari i bescanvi.
7. Packs: SQL, relacio inscripcions, preu pack i descompte.
8. Correus i plantilles: destinataris, adjunt/enllaç segur i casos especials.
9. Intranet alumne/empresa: consulta PDF/QR i privacitat.
10. Aplicacio dels permisos documentats en codi, BD i pantalles.

## 9. Governanca interna i planificacio

| Cas | Estat | Documents principals | Falta completar |
| --- | --- | --- | --- |
| Estimacio global del projecte | DECISIO | `00-index-i-pla/27-informe-auditoria-documental.md` | Es conserva com a criteri intern de planificacio: projecte de 4 a 8 mesos reals, 6 mesos plausible, no com a document AEAT. |
| Disponibilitat real de desenvolupament | DECISIO | `00-index-i-pla/27-informe-auditoria-documental.md` | La planificacio ha de comptar aproximadament 3 dies reals/setmana per VERI*FACTU i evitar tractar dijous com a dia dispers. |
| Capacitat de l'equip | DECISIO | `00-index-i-pla/27-informe-auditoria-documental.md`, `05-governanca-operacio/21-seguretat-permisos-accessos.md` | Meriem concentra desenvolupament fiscal/SIF; suport extern pot ajudar Moodle, incidencies o HTML/PHP/JS simple, pero no assumir arquitectura fiscal. |
