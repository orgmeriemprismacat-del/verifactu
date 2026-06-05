# Auditoria ▶ targetes existents contra sistema actual

Generat: 2026-06-03

Objectiu: comprovar si les targetes que ja existien als exports de Trello i la base reconciliada segueixen el sistema actual de tres taulers, targetes petites, prefixos `▶`, llistes coherents i feina actual del projecte.

## Resposta curta

No, fins ara no estava completament revisat com a auditoria de sistema. Estava revisada la cobertura de molta informacio, pero faltava aquesta comprovacio formal de coherencia de targetes existents contra el sistema actual. Aquesta auditoria cobreix aquest buit.

## Sistema actual esperat

- Tres taulers: Control del projecte, Casos d us, Desenvolupament i proves.
- Separador `▶`; no `·`.
- Targetes petites i accionables.
- Les targetes antigues WEB/INTRANET/SIF es tracten com a font historica i s han de copiar/recrear al sistema nou abans d arxivar-les.
- Sense noms de conversa/eina ni errades antigues.
- Llista nova de control: `A debatre amb Adam/Pablo`.

## Base reconciliada auditada

| Indicador | Valor |
|---|---:|
| Targetes auditades a la base reconciliada | 13245 |
| Targetes despres de la passada flux pagament antic vs SIF actual | 13277 |
| Targetes amb tauler no esperat | 0 |
| Targetes amb llista no normalitzada | 3758 |
| Targetes amb problemes de format/titol | 5248 |

Nota: els indicadors de normalitzacio corresponen a la fotografia auditada abans d'afegir les 32 targetes del flux pagament antic vs SIF actual. La conclusio no canvia: abans del JSON final cal normalitzar llistes i arxivar/substituir targetes antigues.

## Llistes no normalitzades detectades a la base reconciliada

Aquestes llistes venen sobretot de la primera importacio massiva. No vol dir que faltin targetes, sino que algunes encara no estan reclassificades amb el sistema final.

| Tauler + llista | Targetes |
|---|---:|
| VeriFactu / SIF ▶ Desenvolupament i proves / Preparat per programar | 1390 |
| VeriFactu / SIF ▶ Casos d'us / Casos d’ús coberts / disseny cobert | 740 |
| VeriFactu / SIF ▶ Desenvolupament i proves / Pendent de provar | 709 |
| VeriFactu / SIF ▶ Casos d'us / Casos d’ús parcials | 452 |
| VeriFactu / SIF ▶ Control del projecte / Blocs del repo a convertir en targetes | 164 |
| VeriFactu / SIF ▶ Casos d'us / Casos convertits a programació | 67 |
| VeriFactu / SIF ▶ Casos d'us / Casos convertits a proves | 67 |
| VeriFactu / SIF ▶ Casos d'us / Casos validats | 67 |
| VeriFactu / SIF ▶ Casos d'us / Decisions que no són fluxos | 44 |
| VeriFactu / SIF ▶ Control del projecte / Disseny funcional i tècnic | 22 |
| VeriFactu / SIF ▶ Desenvolupament i proves / Bloquejat per dades | 12 |
| VeriFactu / SIF ▶ Desenvolupament i proves / Bloquejat per decisió | 12 |
| VeriFactu / SIF ▶ Control del projecte / Producció / go-no-go / evidències | 10 |
| VeriFactu / SIF ▶ Casos d'us / Casos d’ús pendents | 2 |

## Exports Trello antics revisats

| Export | Targetes obertes uniques | Cobertes a la base nova | Amb format/sistema antic |
|---|---:|---:|---:|
| 7oad0pvV - verifactu-gestio-generica.json | 200 | 192 | 142 |
| E7qn03G6 - verifactu-sif-backlog-historic-granular.json | 2176 | 1706 | 700 |
| E7qn03G6 - verifactu-sif-targetes-actuals.json | 1645 | 1218 | 637 |
| isjbVlvS - verifactu-web.json | 1110 | 892 | 253 |
| XxpST420 - verifactu-intranet.json | 1448 | 1136 | 649 |

## Resum targetes antigues

- Targetes obertes uniques dels exports: **6579**.
- Targetes antigues cobertes o absorbides a la base nova: **5144**.
- Targetes antigues sense cobertura literal o clara: **1435**.
- Targetes antigues que no segueixen el sistema nou per nom/tauler/format: **4095**.

## Feina actual que ja apareix a la base nova

| Bloc actual | Cobert |
|---|---|
| Fase 0 / Composer / PHPUnit | Si |
| Fase 1 / migracio SQL core | Si |
| Codi Drive de referencia | Si |
| Adam/Pablo debats | Si |
| Notificacions intranet | Si |
| Botiga / SL / SIF separat | Si |
| Flux pagament antic vs SIF actual | Si |

## Mostra de targetes antigues no cobertes literalment

- ---- (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- CANVI PER CANVI A MAJOR IMPORT ▶ FACTURA RECTIFICATIVA IMPORT NEGATIU ▶ GENERAR REGISTRE INALTERABLE REGSITROS_FACT (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- CANVI PER CANVI A MAJOR IMPORT ▶ FACTURA RECTIFICATIVA IMPORT NEGATIU ▶ GESTIÓ DE CAUSERISTIQUES DE PAGAMENT RECTIFICATIU (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- CANVI PER CANVI A MAJOR IMPORT ▶ FACTURA RECTIFICATIVA IMPORT POSITIU ▶ GENERAR REGISTRE INALTERABLE REGSITROS_FACT (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- CANVI PER CANVI A MAJOR IMPORT ▶ FACTURA RECTIFICATIVA IMPORT POSITIU ▶ GESTIÓ DE CAUSERISTIQUES DE PAGAMENT RECTIFICATIU (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- CAS PER CANVI DE CURS - MENOR PREU ▶ FACTURA RECTIFICATIVA IMPORT NEGATIU ▶ GENERAR REGISTRE INALTERABLE REGSITROS_FACT (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- CAS PER CANVI DE CURS - MENOR PREU ▶ FACTURA RECTIFICATIVA IMPORT NEGATIU ▶ GESTIÓ DE CAUSERISTIQUES DE PAGAMENT RECTIFICATIU (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- NORMAL ▶ PASSAR PAGAMENT PER DESPES DE GESTIÓ (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSSAR PAGAMENT PER CANVI DE CURS - DISMINUCIÓ D'IMPORT ▶ NORMAL ▶ VISUALITZAR L'ESQUEMA DE PLANTILLA (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSSAR PAGAMENT PER DESPESES DE GESTIÓ ▶ NORMAL ▶ GESTIÓ DE CAUSERISTIQUES DE PAGAMENT RECTIFICATIU (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSSAR PAGAMENT PER CANVI DE CURS - AUGMENT D'IMPORT ▶ NORMAL ▶ GENERAR REGISTRE INALTERABLE REGSITROS_FACT (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSSAR PAGAMENT PER CANVI DE CURS - DISMINUCIÓ D'IMPORT ▶ NORMAL ▶ GENERAR CODI PER OBTENIR LA PLANTILLA ACTIVA DES DE LA BD (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSSAR PAGAMENT PER CANVI DE CURS - DISMINUCIÓ D'IMPORT ▶ NORMAL ▶ CREAR PLANTILLA TEXT (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSSAR PAGAMENT PER CANVI DE CURS - AUGMENT D'IMPORT ▶ NORMAL ▶ GESTIÓ DE CAUSERISTIQUES DE PAGAMENT RECTIFICATIU (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSSAR PAGAMENT PER CANVI DE CURS - DISMINUCIÓ D'IMPORT ▶ NORMAL ▶ GENERAR REGISTRE INALTERABLE REGSITROS_FACT (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- NORMAL ▶ PAGAMENT PER DESPES DE GESTIÓ ▶ ENVIAR CORREU MISSATGE DE CONFIRMACIÓ INDICANT EL PAGAMENT PER DESPESES DE GESTIÓ I LA FACTURA RECTIFICADA (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSSAR PAGAMENT PER DESPESES DE GESTIÓ ▶ NORMAL ▶ VISUALITZAR L'ESQUEMA DE PLANTILLA (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSSAR PAGAMENT PER DESPESES DE GESTIÓ ▶ NORMAL ▶ GENERAR CODI PER OBTENIR LA PLANTILLA ACTIVA DES DE LA BD (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSSAR PAGAMENT PER DESPESES DE GESTIÓ ▶ NORMAL ▶ CREAR PLANTILLA TEXT (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSSAR PAGAMENT PER DESPESES DE GESTIÓ ▶ NORMAL ▶ GENERAR REGISTRE INALTERABLE REGSITROS_FACT (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSSAR PAGAMENT PER CANVI DE CURS - DISMINUCIÓ D'IMPORT ▶ NORMAL ▶ GESTIÓ DE CAUSERISTIQUES DE PAGAMENT RECTIFICATIU (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSSAR PAGAMENT PER CANVI DE CURS - AUGMENT D'IMPORT ▶ NORMAL ▶ VISUALITZAR L'ESQUEMA DE PLANTILLA (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSSAR PAGAMENT PER CANVI DE CURS - AUGMENT D'IMPORT ▶ NORMAL ▶ GENERAR CODI PER OBTENIR LA PLANTILLA ACTIVA DES DE LA BD (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSSAR PAGAMENT PER CANVI DE CURS - AUGMENT D'IMPORT ▶ NORMAL ▶ CREAR PLANTILLA TEXT (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- GRUP ▶ PASSAR PAGAMENT PER DESPES DE GESTIÓ (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- GRUP ▶ PAGAMENT PER DESPES DE GESTIÓ ▶ ENVIAR CORREU MISSATGE DE CONFIRMACIÓ INDICANT EL PAGAMENT PER DESPESES DE GESTIÓ I LA FACTURA RECTIFICADA (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER CANVI DE CURS - DISMINUCIÓ D'IMPORT ▶ GRUP ▶ VISUALITZAR L'ESQUEMA DE PLANTILLA (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER CANVI DE CURS - DISMINUCIÓ D'IMPORT ▶ GRUP ▶ GENERAR CODI PER OBTENIR LA PLANTILLA ACTIVA DES DE LA BD (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER CANVI DE CURS - DISMINUCIÓ D'IMPORT ▶ GRUP ▶ CREAR PLANTILLA TEXT (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER CANVI DE CURS . AUGMENT D'IMPORT ▶ GRUP ▶ GENERAR REGISTRE INALTERABLE REGSITROS_FACT (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER CANVI DE CURS - DISMINUCIÓ D'IMPORT ▶ GRUP ▶ GENERAR REGISTRE INALTERABLE REGSITROS_FACT (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER CANVI DE CURS . AUGMENT D'IMPORT ▶ GRUP ▶ GESTIÓ DE CAUSERISTIQUES DE PAGAMENT RECTIFICATIU (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER DESPES DE GESTIÓ ▶ GRUP ▶ VISUALITZAR L'ESQUEMA DE PLANTILLA (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER DESPES DE GESTIÓ ▶ GRUP ▶ GENERAR CODI PER OBTENIR LA PLANTILLA ACTIVA DES DE LA BD (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER DESPES DE GESTIÓ ▶ GRUP ▶ CREAR PLANTILLA TEXT (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER DESPES DE GESTIÓ ▶ GRUP ▶ ENVIAR CORREU A L'ALUMNE I A NOSALTRES (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER DESPES DE GESTIÓ ▶ GRUP ▶ COMENTAR CORRU AMB EN PABLO (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER DESPES DE GESTIÓ ▶ GRUP ▶ CREAR TEXT PER ENVIAR CORREU A L'ALUMNE (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER DESPES DE GESTIÓ ▶ GRUP ▶ GUARDAR FACTURA PDF SENSE TENIR ACCESS (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER DESPES DE GESTIÓ ▶ GRUP ▶ GENERAR EVENT NEW_FACT_PDF (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER DESPES DE GESTIÓ ▶ GRUP ▶ GENERACIO FACTURA PDF (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER DESPES DE GESTIÓ ▶ GRUP ▶ GENERACIO CODI VERIFACTU ALFANUMERIC (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER DESPES DE GESTIÓ ▶ GRUP ▶ GENERAR EVENT QR (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER DESPES DE GESTIÓ ▶ GRUP ▶ GENERAR CODI QR (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER DESPES DE GESTIÓ ▶ GRUP ▶ ENVIAMENT VERIFACTU ▶ CAS NO OK ▶ GESTIONAR ERRORS ENVIAMENT VERIFACTU (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER DESPES DE GESTIÓ ▶ GRUP ▶ ENVIAMENT VERIFACTU ▶ CAS OK ▶ GUARDAR ESTAT I IDENTIFICADOR (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER DESPES DE GESTIÓ ▶ GRUP ▶ GENERAR REGISTRE INALTERABLE REGSITROS_FACT (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER DESPES DE GESTIÓ ▶ GRUP ▶ GENERAR EVENT SEND_EVENT (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER DESPES DE GESTIÓ ▶ GRUP ▶ GENERAR EVENT NEW_DOC_VERIFACTU (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER DESPES DE GESTIÓ ▶ GRUP ▶ ENVIAMENT VERIFACTU (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER DESPES DE GESTIÓ ▶ GRUP ▶ CREAR ARXIU VERIFACTU (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER DESPES DE GESTIÓ ▶ GRUP ▶ GENERAR EVENT HASH_I (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER CANVI DE CURS - DISMINUCIÓ D'IMPORT ▶ GRUP ▶ GESTIÓ DE CAUSERISTIQUES DE PAGAMENT RECTIFICATIU (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER DESPES DE GESTIÓ ▶ GRUP ▶ CALCULAR EL HASH_I (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER DESPES DE GESTIÓ ▶ GRUP ▶ GUARDAR DADE FACT_RELS (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER DESPES DE GESTIÓ ▶ GRUP ▶ GENERAR EVENT UPD_FACT_RECT (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER CANVI DE CURS . AUGMENT D'IMPORT ▶ GRUP ▶ CREAR PLANTILLA TEXT (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER DESPES DE GESTIÓ ▶ GRUP ▶ ASSOCIAR FACTURA RECTIFICADA A FACTURA ORIGINAL (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER CANVI DE CURS . AUGMENT D'IMPORT ▶ GRUP ▶ VISUALITZAR L'ESQUEMA DE PLANTILLA (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER DESPES DE GESTIÓ ▶ GRUP ▶ GENERAR EVENT NEW FACT (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER DESPES DE GESTIÓ ▶ GRUP ▶ GUARDAR DADES FISCALS DE FACTURA RECTIFICADA (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER CANVI DE CURS . AUGMENT D'IMPORT ▶ GRUP ▶ GENERAR CODI PER OBTENIR LA PLANTILLA ACTIVA DES DE LA BD (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER DESPES DE GESTIÓ ▶ GRUP ▶ OBTENIR DADES DE LA FACTURA ORIGINAL (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER DESPES DE GESTIÓ ▶ GRUP ▶ CALCULAR NUMERO FACTURA I UUID (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER DESPES DE GESTIÓ ▶ GRUP ▶ ACTUALITZAR DADES DE PAGAMENT A INSCRIPCIONS (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER DESPES DE GESTIÓ ▶ GRUP ▶ OBTENIR FACTURA_RELACIONADA (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER DESPES DE GESTIÓ ▶ GRUP ▶ ASSIGNAR DADES DE RECTIFICACIÓ (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER DESPES DE GESTIÓ ▶ GRUP ▶ OBTENIR ELS CAMPS NECESSARIS PEL TIPUS DE RECTIFICACIÓ (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER DESPES DE GESTIÓ ▶ GRUP ▶ RECTIFICACIÓ PER DIFERENCIA (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER DESPES DE GESTIÓ ▶ GRUP ▶ GESTIÓ DE CAUSERISTIQUES DE PAGAMENT RECTIFICATIU (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSAR PAGAMENT PER DESPES DE GESTIÓ ▶ GRUP ▶ GUARDAR INTENT DE PAGAMENT (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PACK ▶ PASSAR PAGAMENT PER DESPES DE GESTIÓ (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PACK ▶ PAGAMENT PER DESPES DE GESTIÓ ▶ ENVIAR CORREU MISSATGE DE CONFIRMACIÓ INDICANT EL PAGAMENT PER DESPESES DE GESTIÓ I LA FACTURA RECTIFICADA (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSSAR PAGAMENT PER CANVI DE CURS - DISMINUCIÓ D'IMPORT ▶ PACK ▶ VISUALITZAR L'ESQUEMA DE PLANTILLA (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSSAR PAGAMENT PER CANVI DE CURS - DISMINUCIÓ D'IMPORT ▶ PACK ▶ GENERAR CODI PER OBTENIR LA PLANTILLA ACTIVA DES DE LA BD (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSSAR PAGAMENT PER CANVI DE CURS - AUGMENT D'IMPORT ▶ PACK ▶ GENERAR REGISTRE INALTERABLE REGSITROS_FACT (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSSAR PAGAMENT PER CANVI DE CURS - DISMINUCIÓ D'IMPORT ▶ PACK ▶ CREAR PLANTILLA TEXT (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSSAR PAGAMENT PER CANVI DE CURS - AUGMENT D'IMPORT ▶ PACK ▶ GESTIÓ DE CAUSERISTIQUES DE PAGAMENT RECTIFICATIU (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSSAR PAGAMENT PER CANVI DE CURS - DISMINUCIÓ D'IMPORT ▶ PACK ▶ GENERAR REGISTRE INALTERABLE REGSITROS_FACT (Verifactu / SIF · Targetes actuals / Pendent (verifactu))
- PASSSAR PAGAMENT PER DESPESES DE GESTIÓ ▶ PACK ▶ VISUALITZAR L'ESQUEMA DE PLANTILLA (Verifactu / SIF · Targetes actuals / Pendent (verifactu))

## Mostra de llistes a normalitzar

- VeriFactu / SIF ▶ Desenvolupament i proves / Preparat per programar: 1390
- VeriFactu / SIF ▶ Casos d'us / Casos d’ús coberts / disseny cobert: 740
- VeriFactu / SIF ▶ Desenvolupament i proves / Pendent de provar: 709
- VeriFactu / SIF ▶ Casos d'us / Casos d’ús parcials: 452
- VeriFactu / SIF ▶ Control del projecte / Blocs del repo a convertir en targetes: 164
- VeriFactu / SIF ▶ Casos d'us / Casos convertits a programació: 67
- VeriFactu / SIF ▶ Casos d'us / Casos convertits a proves: 67
- VeriFactu / SIF ▶ Casos d'us / Casos validats: 67
- VeriFactu / SIF ▶ Casos d'us / Decisions que no són fluxos: 44
- VeriFactu / SIF ▶ Control del projecte / Disseny funcional i tècnic: 22
- VeriFactu / SIF ▶ Desenvolupament i proves / Bloquejat per dades: 12
- VeriFactu / SIF ▶ Desenvolupament i proves / Bloquejat per decisió: 12
- VeriFactu / SIF ▶ Control del projecte / Producció / go-no-go / evidències: 10
- VeriFactu / SIF ▶ Casos d'us / Casos d’ús pendents: 2

## Recomanacio

Abans de generar el JSON final de Trello, cal fer una passada de normalitzacio: mapar totes les llistes no normalitzades a les llistes actuals, marcar les targetes antigues cobertes com a arxivables, i revisar manualment les targetes no cobertes per decidir si son feina real, duplicats antics o soroll historic.
