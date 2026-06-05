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
| Curs normal Redsys | DISSENY COBERT | `03-canvis-pendents/04-fluxos-facturacio.md`, `03-canvis-pendents/06-integracio-redsys-pay-prisma.md`, `03-canvis-pendents/10-procediments-intranet-ecommerce.md`, `05-governanca-operacio/20-pla-proves-validacio-sif.md` | Subbloc especialitzat revisat. `realitzaPagamentAutomatic.php` identificat: signatura, `DS_ORDER`, `IDPAG`, `INSERT INTO factures` i updates d'inscripcio. Falta implementacio SIF i proves. |
| Taller | DISSENY COBERT | `03-canvis-pendents/04-fluxos-facturacio.md` | Aplicar com a variant de curs amb concepte propi i provar URL/payload especific. |
| Jornada | DISSENY COBERT | `03-canvis-pendents/04-fluxos-facturacio.md` | Aplicar com a variant de curs amb concepte propi i provar URL/payload especific. |
| Pack | DISSENY COBERT | `03-canvis-pendents/04-fluxos-facturacio.md`, `03-canvis-pendents/06-integracio-redsys-pay-prisma.md`, `05-governanca-operacio/20-pla-proves-validacio-sif.md`, `04-estat-final/15-estat-final-sistema.md` | Subbloc especialitzat revisat. Regla final: una factura per pagament real, una linia per curs, `IDPAG` agrupa inscripcions i `DESC_ORIGEN = PACK` al segon curs. Falta SQL real de pack/preus, implementacio i proves/captures. |
| Grup de persones | DISSENY COBERT | `03-canvis-pendents/04-fluxos-facturacio.md`, `03-canvis-pendents/06-integracio-redsys-pay-prisma.md`, `03-canvis-pendents/10-procediments-intranet-ecommerce.md`, `05-governanca-operacio/20-pla-proves-validacio-sif.md`, `04-estat-final/15-estat-final-sistema.md` | Subbloc especialitzat revisat. Regla final: una factura per pagament real, una linia per participant, receptor empresa/escola o responsable particular, DNI intern excepte justificacio. Falta implementacio, SQL final `descomptes_grup`/`respGrups` i proves/captures. |
| Regal | DISSENY COBERT | `03-canvis-pendents/04-fluxos-facturacio.md`, `03-canvis-pendents/06-integracio-redsys-pay-prisma.md`, `03-canvis-pendents/10-procediments-intranet-ecommerce.md`, `05-governanca-operacio/20-pla-proves-validacio-sif.md` | Subbloc especialitzat revisat. Regla final: factura al comprador, `SOURCE_TYPE = REGAL`, codi regal i bescanvi posterior sense factura nova. Falta implementacio, validacio SQL final de `regal`/`FACT_REL`, proves/captures i criteri final de targeta regal PDF. |
| USOC | DISSENY COBERT | `03-canvis-pendents/04-fluxos-facturacio.md`, `03-canvis-pendents/06-integracio-redsys-pay-prisma.md`, `03-canvis-pendents/10-procediments-intranet-ecommerce.md`, `05-governanca-operacio/20-pla-proves-validacio-sif.md` | Subbloc especialitzat revisat. Regla final: afiliacio USOC validada manualment, factura alumne per la seva part i factura USOC per la diferencia. Falta implementar, confirmar dades fiscals d'USOC, proves/captures i tractament final de `anticipi-preu-usoc`. |
| Empresa/responsable paga inscripcions | DISSENY COBERT | `03-canvis-pendents/10-procediments-intranet-ecommerce.md`, `03-canvis-pendents/11-inventari-canvis-pendents.md` | Implementar factura abans de cobrament, URL empresa i registre posterior del pagament amb `registerPayment()`. |
| Transferencia validada a intranet | DISSENY COBERT | `03-canvis-pendents/04-fluxos-facturacio.md`, `03-canvis-pendents/06-integracio-redsys-pay-prisma.md`, `03-canvis-pendents/10-procediments-intranet-ecommerce.md`, `03-canvis-pendents/07-pantalles-intranet.md`, `04-estat-final/05-model-bd-sif.md`, `05-governanca-operacio/20-pla-proves-validacio-sif.md` | Subbloc especialitzat revisat. Recuperats `mostrarModalConfPag()`, `efectuarPagament()`, `efectuarPagamentFacturaGenerada()`, `buscarPagamentsByFact`, `updFactGenerada`, `searchMembresFactRel` i fraccions. Falta implementar SIF, referencia bancaria/BANC final, proves/captures i cossos de cerca/modal info. |
| Compensacio/saldo | DISSENY COBERT | `03-canvis-pendents/04-fluxos-facturacio.md`, `03-canvis-pendents/11-inventari-canvis-pendents.md`, `04-estat-final/15-estat-final-sistema.md`, `04-estat-final/18-estat-final-operacio-incidencies.md` | Criteri tancat: saldo/compensacio no edita imports, pot crear `credit_balance`, s'usa com `COMPENSATION` i genera rectificativa si redueix factura emesa. Falta implementacio i proves. |
| Pagaments fraccionats | DISSENY COBERT | `03-canvis-pendents/04-fluxos-facturacio.md`, `03-canvis-pendents/11-inventari-canvis-pendents.md`, `04-estat-final/18-estat-final-operacio-incidencies.md` | Criteri tancat: cada fraccio es `payment_transaction`, cada assignacio es `payment_allocation`, deduplicacio Redsys per `DS_ORDER` i `FRACCIO` nomes resum historic. Falta implementacio i visualitzacio final. |
| Pagament morositat/reclamacio | PARCIAL | `03-canvis-pendents/04-fluxos-facturacio.md`, `03-canvis-pendents/08-correus-i-plantilles.md` | Rutes, plantilles i consultes internes de reclamacio identificades; falten metodes concrets, JS/AJAX i URL especifica de pagament. |

## 3. Facturacio i documents fiscals

| Cas | Estat | Documents principals | Falta completar |
| --- | --- | --- | --- |
| Factura ordinaria A | COBERT | `04-estat-final/05-model-bd-sif.md`, `03-canvis-pendents/04-fluxos-facturacio.md` | SQL definitiu i payload final. |
| Factura rectificativa R | DISSENY COBERT | `03-canvis-pendents/04-fluxos-facturacio.md`, `04-estat-final/15-estat-final-sistema.md`, `04-estat-final/18-estat-final-operacio-incidencies.md`, `04-estat-final/05-model-bd-sif.md` | Criteri tancat: serie `R`, relacio directa amb factura rectificada, motiu controlat i mode `DIFERENCIES` o `SUBSTITUCIO`. Falta validacio fiscal puntual i implementacio. |
| Factura abans de cobrament | DISSENY COBERT | `03-canvis-pendents/11-inventari-canvis-pendents.md`, `03-canvis-pendents/10-procediments-intranet-ecommerce.md` | Codi JS/AJAX actual identificat; falta implementar crida SIF, idempotencia i document final PDF/QR. |
| Devolucions | DISSENY COBERT | `03-canvis-pendents/04-fluxos-facturacio.md`, `03-canvis-pendents/11-inventari-canvis-pendents.md`, `04-estat-final/18-estat-final-operacio-incidencies.md` | Criteri tancat: `REFUND` com moviment economic, rectificativa si redueix factura emesa, possible combinacio amb saldo. Falta implementar i provar Redsys/transferencia/manual. |
| Factura manual | DISSENY COBERT | `03-canvis-pendents/04-fluxos-facturacio.md`, `03-canvis-pendents/11-inventari-canvis-pendents.md`, `04-estat-final/15-estat-final-sistema.md` | Criteri tancat: intranet autoritzada -> `issueInvoice()`, linies estructurades, snapshot fiscal, correus/enllac segur i pagament opcional dins payload. Falta pantalla final i proves. |
| Factura electronica `E_FACT` | DISSENY COBERT | `03-canvis-pendents/07-pantalles-intranet.md`, `03-canvis-pendents/10-procediments-intranet-ecommerce.md` | Ubicacio decidida: `Alumnes / Consulta - Edita - Anula factura`. Permisos: Meriem, Adam i Pablo. |
| Proformes | DECISIO | `00-index-i-pla/documentacio-verifactu.md` | Decisio: no s'utilitzen com a document fiscal. Si apareixen documents no fiscals, s'han de documentar separat. |
| PDF immutable | COBERT | `04-estat-final/05-model-bd-sif.md`, `03-canvis-pendents/11-inventari-canvis-pendents.md` | Eleccio llibreria PDF i plantilla final. |
| QR | PARCIAL | `03-canvis-pendents/11-inventari-canvis-pendents.md`, `05-governanca-operacio/20-pla-proves-validacio-sif.md` | Dades exactes QR i plantilla visual. |
| XML/registre AEAT | PARCIAL | `04-estat-final/25-panell-sif-pay-prisma.md`, `04-estat-final/05-model-bd-sif.md` | Esquema XML definitiu i entorn de proves AEAT. |
| Migracio factures historiques | DISSENY COBERT | `03-canvis-pendents/04-fluxos-facturacio.md`, `03-canvis-pendents/11-inventari-canvis-pendents.md`, `04-estat-final/15-estat-final-sistema.md` | Criteri tancat: migrar com `NO_VERIFACTU`, conservar numeracio i relacions, no crear hash/registre AEAT retroactiu, i crear informe de control. Falta implementacio i validacio de totals. |

## 4. Descomptes, preus i linies

| Cas | Estat | Documents principals | Falta completar |
| --- | --- | --- | --- |
| Descompte Alumne PrisMa | PARCIAL | `00-index-i-pla/documentacio-verifactu.md` | Document de descomptes amb formula i snapshot final. |
| Carnet Jove | PARCIAL | `00-index-i-pla/documentacio-verifactu.md` | Documentar API/verificacio i text visible a factura. |
| USOC descompte/validacio | DISSENY COBERT | `03-canvis-pendents/04-fluxos-facturacio.md`, `03-canvis-pendents/10-procediments-intranet-ecommerce.md`, `05-governanca-operacio/20-pla-proves-validacio-sif.md` | `TIPUS_DESC = 4`, `VALID_DESC` i validacio manual identificats. Falta implementar pantalla final i evidencies reals. |
| Discapacitat/familia nombrosa/monoparental/violencia genere | PARCIAL | `00-index-i-pla/documentacio-verifactu.md` | Confirmar text generic visible i motiu intern sensible. |
| Promocions temporals | DISSENY COBERT | `00-index-i-pla/documentacio-verifactu.md`, `03-canvis-pendents/04-fluxos-facturacio.md`, `04-estat-final/05-model-bd-sif.md`, `05-governanca-operacio/20-pla-proves-validacio-sif.md` | Subbloc especialitzat revisat. `descomptes.TIPUS` 11-99 i snapshot de `factura_linia` definits. Falta SQL final, implementacio i proves/captures. |
| Codi promocional | DISSENY COBERT | `00-index-i-pla/documentacio-verifactu.md`, `03-canvis-pendents/04-fluxos-facturacio.md`, `03-canvis-pendents/10-procediments-intranet-ecommerce.md`, `05-governanca-operacio/20-pla-proves-validacio-sif.md` | Subbloc especialitzat revisat. `promocions`, `CODI_DESCOMPTE`, `USED`, `DATAI/DATAF`, `MACABODETITULAR#...` i `DESC_CODI_PROMO` identificats. Falta implementacio, SQL final i proves/captures. |
| Descompte grup | PARCIAL | `03-canvis-pendents/04-fluxos-facturacio.md` | Relacio `descomptes_grup`, linia per participant i preu per participant. |
| Descompte excepcional per canvi de curs | DISSENY COBERT | `03-canvis-pendents/04-fluxos-facturacio.md`, `03-canvis-pendents/11-inventari-canvis-pendents.md` | Criteri tancat: no tocar `A_PAGAR` sense rastre; ha de quedar com `DESCOMPTE_INCIDENCIA`, `DESCOMPTE_COMERCIAL` o `AJUST_MANUAL`, amb motiu intern i rectificativa si afecta factura emesa. |
| Linies de factura | COBERT | `04-estat-final/05-model-bd-sif.md` | SQL definitiu i exemples per cada cas. |

## 5. Intranet actual i nous apartats

| Apartat | Estat | Documents principals | Falta completar |
| --- | --- | --- | --- |
| Consulta - Modifica alumne | DISSENY COBERT | `03-canvis-pendents/07-pantalles-intranet.md`, `03-canvis-pendents/10-procediments-intranet-ecommerce.md`, `04-estat-final/16-estat-final-pantalles.md` | Subbloc especialitzat revisat. Captures actuals rebudes; falta implementar pantalla final, revisar cossos reals de metodes, executar proves i afegir captures finals. |
| Dades del curs / dades pagament | DISSENY COBERT | `03-canvis-pendents/07-pantalles-intranet.md` | Captures actuals rebudes; falta implementar estats finals de URL/factura/pagament. |
| Canvi de curs | DISSENY COBERT | `03-canvis-pendents/04-fluxos-facturacio.md`, `03-canvis-pendents/07-pantalles-intranet.md`, `03-canvis-pendents/11-inventari-canvis-pendents.md` | Criteri fiscal tancat: historic obligatori, diferencia si nou curs mes car, retorn/saldo si mes barat, despeses de gestio i descomptes documentats. Falta taula final, previsualitzacio, implementacio i proves. |
| Baixa | DISSENY COBERT | `03-canvis-pendents/04-fluxos-facturacio.md`, `03-canvis-pendents/07-pantalles-intranet.md`, `03-canvis-pendents/11-inventari-canvis-pendents.md` | Criteri tancat: baixa com event administratiu, decisio posterior retorn/saldo/no retorn, `REFUND`/`credit_balance` i rectificativa quan pertoqui. Falta correus i pantalla final. |
| Veure factura | DISSENY COBERT | `03-canvis-pendents/07-pantalles-intranet.md` | Captura actual rebuda; falta implementar vista final amb VERI*FACTU/no VERI*FACTU, PDF i QR. |
| Passar pagaments | DISSENY COBERT | `03-canvis-pendents/10-procediments-intranet-ecommerce.md`, `03-canvis-pendents/07-pantalles-intranet.md`, `05-governanca-operacio/20-pla-proves-validacio-sif.md` | Subbloc especialitzat revisat. Queden pendents cossos reals de cerca/modal info, implementacio SIF, proves i captures finals. |
| Generar factura abans de pagar | DISSENY COBERT | `03-canvis-pendents/10-procediments-intranet-ecommerce.md`, `03-canvis-pendents/07-pantalles-intranet.md`, `05-governanca-operacio/20-pla-proves-validacio-sif.md` | Subbloc especialitzat revisat. Queden pendents implementacio SIF, proves, captures finals i comprovacio dels cossos finals de metodes. |
| Consulta - Edita - Anula factura | DISSENY COBERT | `03-canvis-pendents/10-procediments-intranet-ecommerce.md`, `03-canvis-pendents/07-pantalles-intranet.md`, `04-estat-final/16-estat-final-pantalles.md`, `05-governanca-operacio/20-pla-proves-validacio-sif.md` | Subbloc especialitzat revisat. `updDadesFact` i `anularFactura()` historica identificats; queden pendents implementacio SIF, cataleg de motius, proves i captures finals. |
| Analitzar fitxer TPV / comprovar IDPAGs | DISSENY COBERT | `03-canvis-pendents/10-procediments-intranet-ecommerce.md`, `03-canvis-pendents/07-pantalles-intranet.md`, `05-governanca-operacio/20-pla-proves-validacio-sif.md` | Format CSV, resposta JSON, riscos, auditoria i criteri de reprocessament documentats. Falta implementacio i proves reals. |
| Factures manuals | DISSENY COBERT | `03-canvis-pendents/04-fluxos-facturacio.md`, `03-canvis-pendents/11-inventari-canvis-pendents.md` | Criteri fiscal tancat: pantalla d'intranet autoritzada que crida `issueInvoice()`, amb linies, snapshot fiscal, permisos, correus/enllac segur i pagament opcional. Falta implementar pantalla i proves. |
| Descarrega factures/registres | PARCIAL | `03-canvis-pendents/11-inventari-canvis-pendents.md`, `04-estat-final/25-panell-sif-pay-prisma.md` | Formats export i permisos. |
| Apartat VERI*FACTU intranet | DISSENY COBERT | `03-canvis-pendents/07-pantalles-intranet.md`, `03-canvis-pendents/10-procediments-intranet-ecommerce.md`, `04-estat-final/25-panell-sif-pay-prisma.md` | Subbloc especialitzat revisat. Ha de mostrar indicador/resum i enllacar al panell SIF; falta implementacio UI, endpoint resum i captures finals. |
| Intranet alumne | DISSENY COBERT | `03-canvis-pendents/10-procediments-intranet-ecommerce.md`, `04-estat-final/16-estat-final-pantalles.md`, `05-governanca-operacio/20-pla-proves-validacio-sif.md`, `05-governanca-operacio/21-seguretat-permisos-accessos.md` | Subbloc especialitzat revisat. Alumne veu factures propies; no veu factures completes d'empresa/grup si no n'es receptor. Falta implementacio i proves. |
| Empresa/responsable | DISSENY COBERT | `03-canvis-pendents/10-procediments-intranet-ecommerce.md`, `04-estat-final/16-estat-final-pantalles.md`, `05-governanca-operacio/21-seguretat-permisos-accessos.md`, `05-governanca-operacio/22-manual-operatiu-intern.md` | Subbloc especialitzat revisat. No te acces a la intranet principal; consulta per correu, enllac segur o espai especific futur, amb PDF servit per permisos. |
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
| Preproduccio / entorn de proves | DISSENY COBERT | `03-canvis-pendents/09-checklist-posada-en-produccio.md`, `05-governanca-operacio/20-pla-proves-validacio-sif.md` | Criteris de separacio, dades de preproduccio, usuaris de prova, numeracio no productiva i evidencies definits. Falta implementar BD o mode test separat, Redsys test/simulador i AEAT test si correspon. |
| Backups | DISSENY COBERT | `03-canvis-pendents/09-checklist-posada-en-produccio.md` | Pla de backup BD fiscal + documents i prova minima de restauracio ja definits a nivell de criteri. Falta implementacio real, execucio de restauracio i acta d'evidencia. |
| Proves i validacio | DISSENY COBERT | `05-governanca-operacio/20-pla-proves-validacio-sif.md` | Bateria bloquejant amb IDs, resultats esperats, evidencies i estats `PASS`/`FAIL`/`BLOCKED` definida. Falta executar paquet go/no-go i conservar evidencia real. |
| Paquet go/no-go i expedient d'evidencies | DISSENY COBERT | `03-canvis-pendents/09-checklist-posada-en-produccio.md`, `05-governanca-operacio/20-pla-proves-validacio-sif.md`, `05-governanca-operacio/19-registre-versions-i-canvis-sif.md` | Criteris `GO`, `GO AMB LIMITACIONS` i `NO-GO`, checklist final d'activacio, captures minimes, incidencies bloquejants i expedient de versio definits. Falta executar-lo per `0.3-BORRADOR` i `1.0.0`. |
| Manual operatiu intern | PARCIAL | `05-governanca-operacio/22-manual-operatiu-intern.md` | Omplir procediments per Adam/Pablo/gestio. |
| Captures finals | PENDENT | `05-governanca-operacio/23-annex-captures-pantalla.md`, `03-canvis-pendents/09-checklist-posada-en-produccio.md` | Criteri de nom, versio, entorn, prova i resultat definit. Falta afegir captures finals quan hi hagi pantalles implementades i proves executades. |
| Diccionari camps/valors | PARCIAL | `05-governanca-operacio/24-diccionari-camps-i-valors.md` | Afegir tots els valors finals de BD. |

## 8. Buidats prioritaris detectats

Aquest apartat es una llista interna de control, no un bloqueig per continuar treballant. Els punts es van tancant a mesura que es revisa cada apartat amb pantalla, JS/AJAX, metode de `Intranet.php`, taules, correus i permisos.

1. `Passar pagaments` i transferencia validada a intranet: subblocs especialitzats revisats; falta PHP de cerca/modal info, referencia bancaria/BANC final, implementacio SIF i proves.
2. `Consulta - Edita - Anula factura`: subbloc especialitzat revisat; criteri de rectificatives, devolucions i saldo tancat; falta implementar flux SIF, cataleg de motius a codi, bloqueig d'edicio directa i proves.
3. `Generar factura abans de pagar`: subbloc especialitzat revisat; falta implementar SIF, idempotencia, `EMESA_ABANS_COBRAMENT`, URL empresa i proves.
4. `Analitzar fitxer TPV`: subbloc especialitzat revisat; falta implementar conciliacio amb Redsys/SIF, auditoria de fitxer i reprocessament segur.
5. `Redsys curs normal`: subbloc especialitzat revisat; falta substituir `realitzaPagamentAutomatic.php`, implementar `redsys_notifications`, `issueInvoice()`/`registerPayment()` i proves.
6. Packs: subbloc especialitzat revisat; falta SQL real de pack/preus, implementacio SIF i proves/captures.
7. Grups: subbloc especialitzat revisat; falta implementacio SIF, proves/captures i validacio final de SQL `descomptes_grup`/`respGrups`.
8. Regals: subbloc especialitzat revisat; falta implementacio SIF, proves/captures, SQL final de `regal` i criteri de targeta regal PDF.
9. USOC: subbloc especialitzat revisat; falta implementacio, dades fiscals d'USOC, proves/captures i decisio final sobre `anticipi-preu-usoc`.
10. Codis promocionals: subbloc especialitzat revisat; falta implementacio, SQL final de `promocions`, proves/captures i criteri final de visibilitat del codi al PDF.
11. Correus i plantilles: destinataris, adjunt/enllaç segur i casos especials.
12. Intranet alumne/empresa: subbloc especialitzat revisat; falta implementar consulta externa, enllacos segurs, endpoint de documents i captures.
13. Aplicacio dels permisos documentats en codi, BD i pantalles.

## 9. Governanca interna i planificacio

| Cas | Estat | Documents principals | Falta completar |
| --- | --- | --- | --- |
| Estimacio global del projecte | DECISIO | `00-index-i-pla/27-informe-auditoria-documental.md` | Es conserva com a criteri intern de planificacio: projecte de 4 a 8 mesos reals, 6 mesos plausible, no com a document AEAT. |
| Disponibilitat real de desenvolupament | DECISIO | `00-index-i-pla/27-informe-auditoria-documental.md` | La planificacio ha de comptar aproximadament 3 dies reals/setmana per VERI*FACTU i evitar tractar dijous com a dia dispers. |
| Capacitat de l'equip | DECISIO | `00-index-i-pla/27-informe-auditoria-documental.md`, `05-governanca-operacio/21-seguretat-permisos-accessos.md` | Meriem concentra desenvolupament fiscal/SIF; suport extern pot ajudar Moodle, incidencies o HTML/PHP/JS simple, pero no assumir arquitectura fiscal. |
