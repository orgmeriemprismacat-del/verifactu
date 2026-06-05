# Comprovacio de targetes enganxades contra V9

Data: 2026-06-05

Lectura: aquesta comprovacio contrasta la llista enganxada amb el V9 canonic, els 5 exports nous i els exports antics. `Nomes antic` vol dir que la targeta o element existia en fonts antigues, pero no queda prou representat al V9 amb el mateix nivell de visibilitat.

## Resum

| Estat | Quantitat |
|---|---:|
| Sí al V9 | 9 |
| Sí, conceptual | 11 |
| Dubte/revisar V9 | 27 |
| Només antic | 52 |
| No trobat | 9 |

## Resum per bloc

| Bloc | Sí V9 | Conceptual | Dubte | Només antic | No trobat |
|---|---:|---:|---:|---:|---:|
| Targetes demanades | 7 | 1 | 2 | 2 | 2 |
| Generar factura manual | 2 | 2 | 0 | 1 | 0 |
| Migracio de dades | 0 | 1 | 0 | 1 | 1 |
| Posada en produccio | 0 | 2 | 11 | 3 | 1 |
| Elements que haurien de tenir targeta | 0 | 0 | 0 | 0 | 3 |
| Targetes marcades com fetes | 0 | 5 | 14 | 45 | 2 |

## Taula completa

| Bloc | Targeta / element | Estat | Millor coincidencia V9 | Millor coincidencia antiga |
|---|---|---|---|---|
| Targetes demanades | OPTIMITZAR BD DADES FISCALS | No trobat | Decisió presa: BD fiscal immutable i BD web/intranet operativa (Decisions ja preses) | Traspassar dades taula factures a dades fiscals i fact_rels (DADES ACTUALS) |
| Targetes demanades | OPTIMITR BD GESTIO | Només antic |  | Optimitzar BD gestió (BD NOVA) |
| Targetes demanades | OPTIMITZAR BD FACTURES | Només antic |  | Optimitzar BD factures (BD NOVA) |
| Targetes demanades | COPIAR TOTES LES TAULES DE GESTIO A GESTIO_REPLICA (PER TREBALLAR-HI MENTRE DURA EL DESENVOLUPAMENT) | No trobat |  | COPIAR TOTES LES TAULES DE GESTIO A GESTIO_REPLICA (BD NOVA) |
| Targetes demanades | CANVI DE CURS | Sí al V9 | Decisió presa: tipificar `SOURCE_TYPE` (Decisions ja preses) | ELABORAR CASOS D'ÚS ▶ CANVI DE CURS (DIAGRAMES) |
| Targetes demanades | CANI DE CURS - MOSTRAR DADES PER REALITZAR EL CANVI DE CURS | Dubte/revisar V9 | Elaborar fitxa de cas d’ús: Canvi de curs (Disseny funcional i tècnic) | ELABORAR FITXA DE CASOS D'ÚS ▶ CANVI DE CURS ▶ MOSTRAR DADES PER REALITZAR UN CANVI DE CURS (DIAGRAMES) |
| Targetes demanades | CANVI DE CURS - CALCULAR DADES DEL PAGAMENT | Dubte/revisar V9 | Tancar fluxos alternatius: Dades del curs / dades pagament (Casos d’ús coberts / disseny cobert) | ELABORAR FITXA DE CASOS D'ÚS ▶ CANVI DE CURS ▶ CALCULAR DADES DE PAGAMENT (DIAGRAMES) |
| Targetes demanades | ANULAR PAGAMENT | Sí al V9 | Decidir amb Pablo la interfície per anul·lar pagament des de web/pay (Decisions pendents amb Pablo / Adam) | ELABORAR CASOS D'ÚS ▶ ANUL·LAR PAGAMENT (DIAGRAMES) |
| Targetes demanades | GENERAR FACTURA ABANS DE PAGAR | Sí al V9 | Decidir amb Pablo la interfície per generar factura abans de pagar (Decisions pendents amb Pablo / Adam) | GENERAR FACTURA ABANS DE PAGAR (Pendent (verifactu)) |
| Targetes demanades | ANALITZAR FITXER TPV | Sí al V9 | Decidir amb Pablo la interfície per analitzar fitxer TPV (Decisions pendents amb Pablo / Adam) | ELABORAR CASOS D'ÚS ▶ ANALITZAR FITXER TPV (DIAGRAMES) |
| Targetes demanades | DESCARREGAR FACTURES | Sí al V9 | Decidir amb Pablo el nou apartat de descàrrega de factures (Decisions pendents amb Pablo / Adam) | ELABORAR CASOS D'ÚS ▶ DESCARREGAR FACTURES (DIAGRAMES) |
| Targetes demanades | EXPORTAR FACTURES VERIFACTU | Sí al V9 | Cas d’ús: Exportar factures VeriFactu (Casos d’ús del SIF) | DESCARREGAR FACTURES DONAT UN MES ▶ EXPORTAR CSV (Pendent (verifactu)) |
| Targetes demanades | INFO ALUMNE | Sí, conceptual | Decidir amb Pablo la nova interfície d’informació de l’alumne (Decisions pendents amb Pablo / Adam) | ELABORAR CASOS D'ÚS ▶ INFO ALUMNE (DIAGRAMES) |
| Targetes demanades | CONSULTAR PAGAMENTS | Sí al V9 | Decidir amb Pablo la pantalla de consultar pagaments (Decisions pendents amb Pablo / Adam) | ELABORAR CASOS D'ÚS ▶ CONSULTAR PAGAMENTS (DIAGRAMES) |
| Generar factura manual | GENERAR FACTURA MANUAL | Només antic | Decidir tractament final de factura manual (Decisions pendents) | ELABORAR CASOS D'ÚS ▶ GENERAR FACTURA MANUALS (COMPLETADA) |
| Generar factura manual | PASSAR PAGAMENT | Sí al V9 | Analitzar pantalla actual: Passar pagaments (Disseny funcional i tècnic) | TRASPAS ENTORN DE PROVES A ACTUAL ▶ PAGAMENTS ▶ PASSAR PAGAMENTS (TRASPAS ENTORN) |
| Generar factura manual | CONSULTAR FACTURA | Sí al V9 | Cas d’ús: Consultar factura històrica no VERI*FACTU (Casos d’ús del SIF) | ELABORAR CASOS D'ÚS ▶ CONSULTAR FACTURA (COMPLETADA) |
| Generar factura manual | EDITAR FACTURA | Sí, conceptual | Considerar apartat intranet: Consulta / edita / anul·la factura (Mapa d’apartats intranet a revisar) | ELABORAR CASOS D'ÚS ▶ EDITAR FACTURA (COMPLETADA) |
| Generar factura manual | ANULAR FACTURA | Sí, conceptual | Identificar taules i camps afectats: Anul·lar pagament des de la web i moure el flux a pay.prisma.cat (Casos d’ús - Anul·lar pagament des de la web i moure el flux a pay.prisma.cat) | ELABORAR CASOS D'ÚS ▶ ANULAR FACTURA (COMPLETADA) |
| Migracio de dades | FACTURES A BD DADES FISCALS I A FACT_RELS DE BD GESTIÓ | Només antic | Decidir amb Adam la separació de dades de test i dades reals (Decisions pendents amb Pablo / Adam) | Traspassar dades taula factures a dades fiscals i fact_rels (DADES ACTUALS) |
| Migracio de dades | ELIMINAR TAULA FACTURES -> POSTA EN PRODUCCIO | No trobat |  | Eliminar taula factures de la BD actual (DADES ACTUALS) |
| Migracio de dades | CREAR TAULA FACT_RELS | Sí, conceptual | Programar taula `fact_rels` (BD i modelatge) | Programar traspas taula factures a taula fact_rels (DADES ACTUALS) |
| Posada en produccio | TRASPAS D'ENTORN DE PROVES A BD WEB | Dubte/revisar V9 | Traspassar anul·lació de pagament de web a `pay.prisma.cat` (Sistema de notificacions) | TRASPAS ENTORN DE PROVES A ACTUAL ▶ BD WEB (TRASPAS ENTORN) |
| Posada en produccio | TRASPAS D'ENTORN DE PROBES A BD INTRANET | Dubte/revisar V9 | He deixat pendent l’execució real de proves per manca de PHP al PATH local (Gestió i implementació inicial ja invertida) | TRASPAS ENTORN DE PROVES A ACTUAL ▶ BD INTRANET (TRASPAS ENTORN) |
| Posada en produccio | NETEJAR PROVES A BD FACTURES | Només antic | Decidir amb Adam la separació de dades de test i dades reals (Decisions pendents amb Pablo / Adam) | NETEJAR PROVES A BD FACTURES (TRASPAS ENTORN) |
| Posada en produccio | TRASPAS D'ENTORN DE PROVES A ACTUAL DEL PROCÉS DE PAGAMENT D'INSCRIPCIO NORMAL | Només antic | Tancar precondicions: Curs normal Redsys (Casos d’ús coberts / disseny cobert) | TRASPAS ENTORN DE PROVES A ACTUAL ▶ INSCRIPCIÓ NORMAL ▶ PROCÉS DE PAGAMENT (TRASPAS ENTORN) |
| Posada en produccio | TRASPAS D'ENTORN DE PROVES A ACTUAL DEL PROCÉS DE PAGAMENT D'INSCRIPCIO PACK | Dubte/revisar V9 | Definir dades d’entrada: prova pack (Go-no-go i evidències) | TRASPAS ENTORN DE PROVES A ACTUAL ▶ INSCRIPCIÓ NORMAL ▶ PROCÉS DE PAGAMENT (TRASPAS ENTORN) |
| Posada en produccio | TRASPAS D'ENTORN DE PROVES A ACTUAL DEL PROCÉS DE PAGAMENT D'INSCRIPCIO GRUP | Dubte/revisar V9 | Definir dades d’entrada: prova grup (Go-no-go i evidències) | TRASPAS ENTORN DE PROVES A ACTUAL ▶ INSCRIPCIÓ GRUP ▶ PROCÉS DE PAGAMENT (TRASPAS ENTORN) |
| Posada en produccio | TRASPAS D'ENTORN DE PROVES A ACTUAL DEL PROCÉS DE PAGAMENT D'INSCRIPCIO REGAL | Dubte/revisar V9 | Definir dades d’entrada: prova regal (Go-no-go i evidències) | TRASPAS ENTORN DE PROVES A ACTUAL ▶ INSCRIPCIÓ NORMAL ▶ PROCÉS DE PAGAMENT (TRASPAS ENTORN) |
| Posada en produccio | TRASPAS D'ENTORN DE PROVES A ACTUAL DEL PROCÉS DE PAGAMENT D'INSCRIPCIO USOC | Dubte/revisar V9 | Definir dades d’entrada: prova USOC (Go-no-go i evidències) | TRASPAS ENTORN DE PROVES A ACTUAL ▶ INSCRIPCIÓ NORMAL ▶ PROCÉS DE PAGAMENT (TRASPAS ENTORN) |
| Posada en produccio | TRASPAS D'ENTORN DE PROVES A ACTUAL DEL PROCÉS DE PAGAMENT D'INSCRIPCIO TALLER | Només antic | Tancar precondicions: Preproducció / entorn de proves (Casos d’ús coberts / disseny cobert) | TRASPAS ENTORN DE PROVES A ACTUAL ▶ INSCRIPCIÓ TALLER ▶ PROCÉS DE PAGAMENT (TRASPAS ENTORN) |
| Posada en produccio | TRASPAS D'ENTORN DE PROVES A ACTUAL DEL NOU APARTAT - FITXER D'EXPORTACIÓ DE FACTURES | Dubte/revisar V9 | Preparar captures finals: Exportació de factures VeriFactu (Entorns i desplegament) | TRASPAS ENTORN DE PROVES A ACTUAL ▶ NOU APARTAT ▶ FITXER D'EXPORTACIÓ DE LES FACTURES (TRASPAS ENTORN) |
| Posada en produccio | TRASPAS D'ENTORN DE PROVES A ACTUAL DEL NOU APARTAT - DESCARREGAR FACTURES DONAT UN MES | Dubte/revisar V9 | Revisar traspàs de cron o automatismes relacionats amb pagament/factura (Entorns i desplegament) | TRASPAS ENTORN DE PROVES A ACTUAL ▶ NOU APARTAT ▶ DESCARREGAR FACTURES DONAT UN MES (TRASPAS ENTORN) |
| Posada en produccio | TRASPAS D'ENTORN DE PROVES A ACTUAL DEL NOU APARTAT - GENERAR FACTURES MANUALMENT | No trobat | Revisar traspàs de scripts que avui modifiquen factures o pagaments (Entorns i desplegament) | TRASPAS ENTORN DE PROVES A ACTUAL ▶ NOU APARTAT ▶ GENERAR FACTURES MANUAMENT (TRASPAS ENTORN) |
| Posada en produccio | TRASPAS D'ENTORN DE PROVES A ACTUAL DEL APARTAT - FACTURES | Sí, conceptual | Revisar traspàs de scripts que avui modifiquen factures o pagaments (Entorns i desplegament) | NETEJAR PROVES A BD FACTURES (TRASPAS ENTORN) |
| Posada en produccio | TRASPAS D'ENTORN DE PROVES A ACTUAL DEL APARTAT - PASSAR PAGAMENTS | Sí, conceptual | Revisar traspàs de scripts que avui modifiquen factures o pagaments (Entorns i desplegament) | TRASPAS ENTORN DE PROVES A ACTUAL ▶ PAGAMENTS ▶ PASSAR PAGAMENTS (TRASPAS ENTORN) |
| Posada en produccio | TRASPAS D'ENTORN DE PROVES A ACTUAL DEL APARTAT - ANALITZAR FITXER TPV | Dubte/revisar V9 | Programar bloquejos SIF: Analitzar fitxer TPV / comprovar IDPAGs (Entorns i desplegament) | TRASPAS ENTORN DE PROVES A ACTUAL ▶ PAGAMENTS ▶ ANALITZAR FITXER TPV (TRASPAS ENTORN) |
| Posada en produccio | TRASPAS D'ENTORN DE PROVES A ACTUAL DEL APARTAT - CANVI DE CURS | Dubte/revisar V9 | Crear prova executable: canvi de curs amb diferència (Go-no-go i evidències) | TRASPAS ENTORN DE PROVES A ACTUAL ▶ ALUMNES ▶ CANVI CURS (TRASPAS ENTORN) |
| Posada en produccio | TRASPAS D'ENTORN DE PROVES A ACTUAL DEL APARTAT - DONAR DE BAIXA | Dubte/revisar V9 | Preparar captures finals: Baixa (Entorns i desplegament) | TRASPAS ENTORN DE PROVES A ACTUAL ▶ ALUMNES ▶ DONAR DE BAIXA (TRASPAS ENTORN) |
| Elements que haurien de tenir targeta | ELIMINAR DECISIÓ PRESA ANTERIORMENT DE CONCEPTE EN FACTURES UNIC | No trobat |  | Text informatiu per avisar a la pàgina de pagament a l'hora de confirmar dades de facturació (EFECTUAR PAGAMENT) |
| Elements que haurien de tenir targeta | DECISIÓ PRESA QUE ES MANTÉ DIFERENTS CONCEPTES | No trobat | Decisió presa: BD fiscal immutable i BD web/intranet operativa (Decisions ja preses) |  |
| Elements que haurien de tenir targeta | CAS DE: RECTIFICATIVA PER CONCEPTE (S'HAURIA DE GENERAR CAS D'ÚS, PROGRAMACIÓ, ETC). AL IGAUL QUE RECTIFICATIVA PER CANVI DE NOM, RECTIFICATIVA PER CANVI DE CURS PER AUGMENT D'IMPORT DE CURS, RECTIFICATIVA PER CANVI DE CURS PER MENOR IMPORT DE CURS, RECTIFICATIVA PER AUGMENT D'IMPORT PER DESPESES DE GESTIÓ (AQUEST CAS POT ESTAR UNIT ALS ALTRES CASOS), RECTIFICATIVA PER OFERIR-LI UN 25% DE DESCOMPTE QUAN EL CANVI DE CURS ES PRODUIT PER CULPA NOSTRE -> POT PASSAR QUE S'AUGMENTI IGUALMENT EL COST DEL CURS O QUE ES DISMINUEIXI EL COST DEL CURS O QUE ES MANTINGUI EL MATEIX. | No trobat |  |  |
| Targetes marcades com fetes | CONSERVAR HISTORIAL FACUTRES | Només antic | Crear proves de migració: `factures` (Entorns i desplegament) | Conservar historial factures (COMPLETADA) |
| Targetes marcades com fetes | CREAR TAULA REGISTRE DE PAGAMENTS | Només antic | Decidir amb Pablo el disseny de pantalles de consulta de factura (Decisions pendents amb Pablo / Adam) | Crear taula registre de pagaments (COMPLETADA) |
| Targetes marcades com fetes | AEAT - ESPECIFICACIONS TÈNCIQUES PER GENERAR EL CODI QR | Només antic |  | AEAT - ESPECIFICACIONS TÈNCIQUES PER GENERAR EL CODI QR (COMPLETADA) |
| Targetes marcades com fetes | AEAT - ESPECIFIACIONS TÈCNIQUES DE LA URL DE COTEIG DEL RECEPTOR DE LA FACTURA | Només antic |  | AEAT - ESPECIFIACIONS TÈCNIQUES DE LA URL DE COTEIG DEL RECEPTOR DE LA FACTURA (COMPLETADA) |
| Targetes marcades com fetes | FITXER AEAT DISSENY DE REGISTRE DE FACTURACIÓ | Dubte/revisar V9 | Analitzar pantalla actual: Descarregar factures i registres (Disseny funcional i tècnic) | FITXER AEAT DISSENY DE REGISTRE DE FACTURACIÓ (COMPLETADA) |
| Targetes marcades com fetes | AEAT - SIF- ESPECIFICACIONS TÈCNIQUES PER GENERAR EL HASH DELS REGISTRES DE FACTURACIÓ | Només antic |  | AEAT - SIF- ESPECIFICACIONS TÈCNIQUES PER GENERAR EL HASH DELS REGISTRES DE FACTURACIÓ (COMPLETADA) |
| Targetes marcades com fetes | AEAT - ESQUEMA DELS SERVEIS WEB | Dubte/revisar V9 | Definir implementació tècnica: XML / registre AEAT (Casos d’ús parcials) | AEAT - ESQUEMA DELS SERVEIS WEB (COMPLETADA) |
| Targetes marcades com fetes | AEAT - ESPECIFICACIONS TÈNCIQUES PER GENERAR LA FIRMA ELECTRÒNICA DELS REGISTRES DE FACTURACIÓ | Només antic |  | AEAT - ESPECIFICACIONS TÈNCIQUES PER GENERAR LA FIRMA ELECTRÒNICA DELS REGISTRES DE FACTURACIÓ (COMPLETADA) |
| Targetes marcades com fetes | FITXER AEAT SISTEMES INFORMATICS DE FACTURACIO | Només antic |  | FITXER AEAT SISTEMES INFORMATICS DE FACTURACIO  (COMPLETADA) |
| Targetes marcades com fetes | NOVA BD ▶ Aquesta base de dades cada vegada que s’accedeixi es registrarà en una taula de registre d’accessos. | Només antic |  | NOVA BD ▶ Aquesta base de dades cada vegada que s’accedeixi es registrarà en una taula de registre d’accessos. (COMPLETADA) |
| Targetes marcades com fetes | NOVA BD ▶ FACTURA_LOG ▶ BLOQUEJAR  MODIFICACIONS | Només antic |  | NOVA BD ▶ FACTURA_LOG ▶ BLOQUEJAR  MODIFICACIONS (COMPLETADA) |
| Targetes marcades com fetes | BLOCKCHAIN ▶ CREAR TAULA PER ASSEGURAR ELS REGISTRES INELTARABLES | Només antic |  | BLOCKCHAIN ▶ CREAR TAULA PER ASSEGURAR ELS REGISTRES INELTARABLES (COMPLETADA) |
| Targetes marcades com fetes | NOVA BD ▶ NOVA TAULA REGISTROS_FACT ▶ BLOQUEJAR  MODIFICACIONS | Només antic |  | NOVA BD ▶ NOVA TAULA REGISTROS_FACT ▶ BLOQUEJAR  MODIFICACIONS (COMPLETADA) |
| Targetes marcades com fetes | NOVA BD ▶ CREAR TAULA DE REGISTRE D'ESDEVENIMENTS FACTURA_LOG | Només antic |  | NOVA BD ▶ CREAR TAULA DE REGISTRE D'ESDEVENIMENTS FACTURA_LOG (COMPLETADA) |
| Targetes marcades com fetes | NOVA BD ▶ NOVA TAULA FACTURA_LOG ▶ BLOQUEJAR  MODIFICACIONS | Només antic |  | NOVA BD ▶ NOVA TAULA FACTURA_LOG ▶ BLOQUEJAR  MODIFICACIONS (COMPLETADA) |
| Targetes marcades com fetes | NOVA BD ▶ CREAR TAULA SESSION_LOG | Sí, conceptual | Programar taula `session_log` (BD i modelatge) | NOVA BD ▶ CREAR TAULA SESSION_LOG (COMPLETADA) |
| Targetes marcades com fetes | NOVA BD ▶ NOVA TAULA DADES FISCALS ▶ BLOQUEJAR  MODIFICACIONS | Només antic | Tancar precondicions: Canvi de curs (Casos d’ús coberts / disseny cobert) | NOVA BD ▶ NOVA TAULA DADES FISCALS ▶ BLOQUEJAR  MODIFICACIONS (COMPLETADA) |
| Targetes marcades com fetes | NOVA BD ▶ NOVA TAULA DADES FISCALS | Sí, conceptual | Tancar precondicions: Canvi de curs (Casos d’ús coberts / disseny cobert) | NOVA BD ▶ NOVA TAULA DADES FISCALS ▶ BLOQUEJAR  MODIFICACIONS (COMPLETADA) |
| Targetes marcades com fetes | Eliminar taula inscripcions_recuperades de la BD actual | Només antic |  | Eliminar taula inscripcions_recuperades de la BD actual (COMPLETADA) |
| Targetes marcades com fetes | Eliminar taula inscripcions_gratis de la BD actual | Només antic |  | Eliminar taula inscripcions_gratis de la BD actual (COMPLETADA) |
| Targetes marcades com fetes | Eliminar taula factures_sl de la BD actual | Només antic |  | Eliminar taula factures_sl de la BD actual (COMPLETADA) |
| Targetes marcades com fetes | Eliminar taula jornades_fetes de la BD actual | Només antic |  | Eliminar taula jornades_fetes de la BD actual (COMPLETADA) |
| Targetes marcades com fetes | Eliminar taula preus de la BD actual | Només antic |  | Eliminar taula preus de la BD actual (COMPLETADA) |
| Targetes marcades com fetes | Eliminar taula moment de la BD actual | Només antic |  | Eliminar taula moment de la BD actual (COMPLETADA) |
| Targetes marcades com fetes | Eliminar taula intentsPagament de la BD actual | Només antic |  | Eliminar taula intentsPagament de la BD actual (COMPLETADA) |
| Targetes marcades com fetes | Eliminar taula enquestes_tripartita de la BD actual | Només antic |  | Eliminar taula enquestes_tripartita de la BD actual (COMPLETADA) |
| Targetes marcades com fetes | Eliminar taula enquestes_preguntes de la BD actual | Només antic |  | Eliminar taula enquestes_preguntes de la BD actual (COMPLETADA) |
| Targetes marcades com fetes | Eliminar taula empreses de la BD actual | Només antic |  | Eliminar taula empreses de la BD actual (COMPLETADA) |
| Targetes marcades com fetes | Eliminar taula enquestes de la BD actual | Només antic |  | Eliminar taula enquestes de la BD actual (COMPLETADA) |
| Targetes marcades com fetes | Eliminar taula centre de la BD actual | Només antic |  | Eliminar taula centre de la BD actual (COMPLETADA) |
| Targetes marcades com fetes | Eliminar taula borsa de la BD actual | Només antic |  | Eliminar taula borsa de la BD actual (COMPLETADA) |
| Targetes marcades com fetes | Eliminar taula ELIMINAR_subscriptors de la BD actual | Només antic |  | Eliminar taula ELIMINAR_subscriptors de la BD actual (COMPLETADA) |
| Targetes marcades com fetes | Eliminar taula aules de la BD actual | Només antic | Definir implementació tècnica: Canvi de curs (Casos d’ús coberts / disseny cobert) | Eliminar taula aules de la BD actual (COMPLETADA) |
| Targetes marcades com fetes | Eliminar taula permisos de la BD actual | Només antic | Definir implementació tècnica: Canvi de curs (Casos d’ús coberts / disseny cobert) | Eliminar taula permisos de la BD actual (COMPLETADA) |
| Targetes marcades com fetes | Eliminar taula alacarta de la BD actual | Només antic |  | Eliminar taula alacarta de la BD actual (COMPLETADA) |
| Targetes marcades com fetes | Aplicar pay_modify en el diagrama de taules | Només antic |  | Aplicar pay_modify en el diagrama de taules (COMPLETADA) |
| Targetes marcades com fetes | Preparar nova taula pay_modify | Només antic | Programar taula `apartats` (BD i modelatge) | Preparar nova taula pay_modify (COMPLETADA) |
| Targetes marcades com fetes | Trobat solucio per aplicar els pagament després d'un canvi de curs | Només antic |  | Trobat solucio per aplicar els pagament després d'un canvi de curs (COMPLETADA) |
| Targetes marcades com fetes | CREAR BD COPIA DE BD SUPORT PER ENTORN DE PROVES VERIFACTU | Dubte/revisar V9 | Crear còpia de la intranet per desenvolupament (Entorns i desplegament) | CREAR BD COPIA DE BD SUPORT PER ENTORN DE PROVES VERIFACTU (COMPLETADA) |
| Targetes marcades com fetes | CREAR SISTEMA INTERN PROCÉS DE PAGAMENT DE PROVES VERIFACTU - INSCRIPCIÓ NORMAL | Dubte/revisar V9 | Tancar precondicions: Curs normal Redsys (Casos d’ús coberts / disseny cobert) | CREAR SISTEMA INTERN PROCÉS DE PAGAMENT DE PROVES VERIFACTU - INSCRIPCIÓ NORMAL (COMPLETADA) |
| Targetes marcades com fetes | CREAR SISTEMA INTERN PROCÉS DE PAGAMENT DE PROVES VERIFACTU - PACK DE CURSOS | Dubte/revisar V9 | Definir integració amb notificacions SIF: Gestió de cursos / Últimes tasques / Curs superat (Sistema de notificacions) | CREAR SISTEMA INTERN PROCÉS DE PAGAMENT DE PROVES VERIFACTU - PACK DE CURSOS (COMPLETADA) |
| Targetes marcades com fetes | CREAR SISTEMA INTERN PROCÉS DE PAGAMENT DE PROVES VERIFACTU - REGAL | Només antic | Considerar apartat intranet: Sistema de notificacions SIF (Mapa d’apartats intranet a revisar) | CREAR SISTEMA INTERN PROCÉS DE PAGAMENT DE PROVES VERIFACTU - REGAL (COMPLETADA) |
| Targetes marcades com fetes | CREAR SISTEMA INTERN PROCÉS DE PAGAMENT DE PROVES VERIFACTU - INSCRIPCIÓ GRUP | Dubte/revisar V9 | Definir dades d’entrada: prova grup (Go-no-go i evidències) | CREAR SISTEMA INTERN PROCÉS DE PAGAMENT DE PROVES VERIFACTU - INSCRIPCIÓ GRUP (COMPLETADA) |
| Targetes marcades com fetes | CREAR SISTEMA INTERN PROCÉS DE PAGAMENT DE PROVES VERIFACTU - USOC | Només antic | Considerar apartat intranet: Sistema de notificacions SIF (Mapa d’apartats intranet a revisar) | CREAR SISTEMA INTERN PROCÉS DE PAGAMENT DE PROVES VERIFACTU - USOC (COMPLETADA) |
| Targetes marcades com fetes | CREAR SISTEMA INTERN PROCÉS DE PAGAMENT DE PROVES VERIFACTU - INSCRIPCIÓ TALLER | Dubte/revisar V9 | Tancar precondicions: Taller (Casos d’ús coberts / disseny cobert) | CREAR SISTEMA INTERN PROCÉS DE PAGAMENT DE PROVES VERIFACTU - INSCRIPCIÓ TALLER (COMPLETADA) |
| Targetes marcades com fetes | SUMA DE PAGAMENTS ▶ ESPECIFICACIONS ACTUALS | Només antic |  | SUMA DE PAGAMENTS ▶ ESPECIFICACIONS ACTUALS (COMPLETADA) |
| Targetes marcades com fetes | CANVIAR VERSIO TEST.PRISMA.CAT A PHP 7 IGUAL QUE LA WEB PER UTILITZAR-LO PER ENTORN DE PROVES | Només antic |  | CANVIAR VERSIO TEST.PRISMA.CAT A PHP 7 IGUAL QUE LA WEB PER UTILITZAR-LO PER ENTORN DE PROVES (COMPLETADA) |
| Targetes marcades com fetes | CREAR SISTEMA INTERN INTRANET ALUMNE DE PROVES PER PROVES VERIFACTU | Dubte/revisar V9 | Considerar apartat intranet: Sistema de notificacions SIF (Mapa d’apartats intranet a revisar) | CREAR SISTEMA INTERN INTRANET ALUMNE DE PROVES PER PROVES VERIFACTU (COMPLETADA) |
| Targetes marcades com fetes | CREAR SISTEMA INTERN INTRANET TUTOR DE PROVES PER PROVES VERIFACTU | Dubte/revisar V9 | Considerar apartat intranet: Sistema de notificacions SIF (Mapa d’apartats intranet a revisar) | CREAR SISTEMA INTERN INTRANET TUTOR DE PROVES PER PROVES VERIFACTU (COMPLETADA) |
| Targetes marcades com fetes | CREAR SISTEMA INTERN INTRANET OLD.PRISMA.CAT DE PROVES PER PROVES VERIFACTU | Dubte/revisar V9 | Crear proves del panell SIF: Registres AEAT (Sistema de notificacions) | CREAR SISTEMA INTERN INTRANET OLD.PRISMA.CAT DE PROVES PER PROVES VERIFACTU (COMPLETADA) |
| Targetes marcades com fetes | CREAR SISTEMA INTERN PAY.PRISMA.CAT DE PROVES PER PROVES VERIFACTU | Dubte/revisar V9 | Crear proves del panell SIF: Registres AEAT (Sistema de notificacions) | CREAR SISTEMA INTERN PAY.PRISMA.CAT DE PROVES PER PROVES VERIFACTU (COMPLETADA) |
| Targetes marcades com fetes | CREAR SISTEMA INTERN INTRANET DE PROVES PER PROVES VERIFACTU | Sí, conceptual | Considerar apartat intranet: Sistema de notificacions SIF (Mapa d’apartats intranet a revisar) | CREAR SISTEMA INTERN INTRANET DE PROVES PER PROVES VERIFACTU (COMPLETADA) |
| Targetes marcades com fetes | ELABORAR DIAGRAMA DE CLASSES ENTRE BDS | Només antic | Elaborar diagrama de classes del SIF (Disseny funcional i tècnic) | ELABORAR DIAGRAMA DE CLASSES ENTRE BDS (COMPLETADA) |
| Targetes marcades com fetes | ELABORAR DIAGRAMA DE CLASSES ▶ BD FACTURES | Dubte/revisar V9 | Elaborar diagrama de classes del SIF (Disseny funcional i tècnic) | ELABORAR DIAGRAMA DE CLASSES ▶ BD FACTURES (COMPLETADA) |
| Targetes marcades com fetes | ELABORAR DIAGRAMA DE CLASSES ▶ BD INTRANET | Sí, conceptual | Elaborar diagrama de classes general del projecte SIF (Disseny funcional i tècnic) | ELABORAR DIAGRAMA DE CLASSES ▶ BD INTRANET (COMPLETADA) |
| Targetes marcades com fetes | ELABORAR DIAGRAMA DE CLASSES ▶ BD GESTIO | Dubte/revisar V9 | Elaborar diagrama de classes del SIF (Disseny funcional i tècnic) | ELABORAR DIAGRAMA DE CLASSES ▶ BD GESTIO (COMPLETADA) |
| Targetes marcades com fetes | AFEGIR SSL AL SERVIDOR | Només antic | Afegir validacions de servidor: getCompanySecureInvoice() (BD i modelatge) | AFEGIR SSL AL SERVIDOR (COMPLETADA) |
| Targetes marcades com fetes | FTP PAY.PRISMA.CAT BLOQUEJAT | Dubte/revisar V9 | Dibuixar diagrama de seqüència: Hash chain bloquejat (Casos d’ús del SIF) | FTP PAY.PRISMA.CAT BLOQUEJAT (COMPLETADA) |
| Targetes marcades com fetes | CREAR NOU SERVIDOR PAY.PRISMA.CAT | Sí, conceptual | Programar permisos servidor: Anul·lar pagament des de web i moure a pay.prisma.cat (Intranet - Anul·lar pagament des de web i moure a pay.prisma.cat) | CREAR NOU SERVIDOR PAY.PRISMA.CAT (COMPLETADA) |
| Targetes marcades com fetes | CREAR NOVA BASE DE DADES FACTURES | Només antic | Decisió presa: `fact_rels` és pont lògic amb la BD antiga (Decisions ja preses) | CREAR NOVA BASE DE DADES FACTURES (COMPLETADA) |
| Targetes marcades com fetes | Copiar taula *** a la nova BD historic -> TOTES LES TAULES ELIMINADES | No trobat |  | Copiar taula factures a la nova BD historic (COMPLETADA) |
| Targetes marcades com fetes | Revisar si hi ha efectes colaterals de la taula **** -> TOTES LES TAULES ELIMINADES | No trobat |  | Revisar si hi ha efectes colaterals de la taula permisos (COMPLETADA) |
| Targetes marcades com fetes | SUBSTITUIR TAULA PREUS PER PREU ▶ WEB | Només antic |  | SUBSTITUIR TAULA PREUS PER PREU ▶ WEB (COMPLETADA) |
| Targetes marcades com fetes | SUBSTITUIR TAULA PREUS PER PREU ▶ AJAX ▶ WEB | Només antic |  | SUBSTITUIR TAULA PREUS PER PREU ▶ AJAX ▶ WEB (COMPLETADA) |
| Targetes marcades com fetes | SUBSTITUIR TAULA PREUS PER PREU ▶ INTRANET_TUTOR | Només antic |  | SUBSTITUIR TAULA PREUS PER PREU ▶ INTRANET_TUTOR (COMPLETADA) |
| Targetes marcades com fetes | NOVA BD HISTORIC ANTERIOR VERIFACTU | Només antic | Decisió presa: PDF antic no es pot regenerar des de dades vives (Decisions ja preses) | NOVA BD HISTORIC ANTERIOR VERIFACTU (COMPLETADA) |

## Lectura operativa

- El bloc de casos d'us esta bastant cobert al V9, pero `Generar factura manual`, `anular factura` i algunes subparts de canvi de curs necessiten revisio manual per assegurar que no queden massa generals.
- El bloc de migracio/BD i traspas d'entorn es el mes feble: moltes peces existeixen als Trellos antics, pero no al V9 amb targeta clara.
- Les targetes marcades com fetes existeixen majoritariament a fonts antigues; si vols que es vegi tota la feina feta, caldria crear un bloc/llista d'historic fet o conservar-les amb etiqueta de completades/arxivades.
- Les targetes del flux antic de passar pagament no s'han de copiar literalment si contradiuen el flux SIF actual; s'han de transformar en casos, programacio i proves del flux `issueInvoice()`/`registerPayment()`.

