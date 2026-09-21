# Matriu de resolució dels 25 elements de pantalla sense validació individual

**Revisió documental del 21/09/2026.** Font: [matriu de pantalles abans/després](../03-canvis-pendents/12-matriu-pantalles-abans-despres.md). Aquest document **només** classifica les 25 files el camp «estat actual» de les quals diu «pendent de validar en detall». No afirma que una pantalla ja estigui desenvolupada, que el seu enllaç Trello sigui una prova executada, ni que les 167 files restants estiguin completes.

**Resultat:** els 25 noms tenen un cas d'ús **candidat ja catalogat** o són activitats de documentació/ajuda. Per tant, aquesta comparació **no acredita cap UC-130 nova**; per declarar completa la cobertura cal verificar les accions, actors i mètodes PHP per pantalla. Els enllaços de cada fila són punts de partida documentals, no una certificació de correspondència funcional.

| # | Pantalla/apartat pendent | Casos actuals candidats | Contrast específic per tancar | Estat d'aquesta revisió |
| ---: | --- | --- | --- | --- |
| 1 | Ajuda contextual del panell | UC-34; document 25 (guies ràpides de pantalles internes) | Contingut d'ajuda del panell: identificar pantalla concreta i si només informa o activa una comanda. | MAPAT PROVISIONAL — documentació/UI |
| 2 | Captures finals de recorreguts crítics | UC-39; annex de captures | Evidència de prova, no cas d'ús de negoci independent; relacionar captura amb UC, ruta, versió i resultat reproduïble. | EVIDÈNCIA TRANSVERSAL |
| 3 | Cercador general de pagaments | UC-56; UC-02 | Cerca i consulta no són registre de cobrament; revisar filtres, permisos i destí de selecció. | MAPAT PROVISIONAL — consulta |
| 4 | Cercar pagament per NIF/NIE | UC-56; UC-126 | Identitat i accessos: el NIF de pagador pot no ser el d'inscrit o receptor fiscal. | MAPAT PROVISIONAL — variant de cerca |
| 5 | Compatibilitat intranet antiga | UC-64; UC-68; UC-47 | Inventariar scripts i rutes realment actius; establir substitut abans de retirar writers i sincronitzar després del commit. | MAPAT PROVISIONAL — integració |
| 6 | Curs no superat pendent de pagament | UC-95; UC-124 | Determinar estat acadèmic, deute i política de certificat independentment de factura/accés. | MAPAT PROVISIONAL — regla pendent |
| 7 | Curs superat pendent de pagament | UC-95; UC-124 | Distingir superació del curs, cobrament, certificat i accés Moodle; evitar deduir PAID d'un resultat acadèmic. | MAPAT PROVISIONAL — regla pendent |
| 8 | Despeses de gestió | UC-73; UC-94; UC-71 | Fixar moment, motiu i línia/import fiscal; si hi ha factura emesa, classificar correcció via UC-74. | MAPAT PROVISIONAL — decisió econòmica |
| 9 | Divergència BD antiga/SIF | UC-53; UC-82 | Separar detecció, diagnòstic, decisió i reparació; SIF com a origen dels fets fiscals. | MAPAT PROVISIONAL — reconciliació |
| 10 | Document històric no VERI*FACTU | UC-11; UC-97; UC-07 | Comprovar emissor real, document físic, bytes/hash i drets de consulta; cap registre AEAT retroactiu. | MAPAT PROVISIONAL — consulta històrica |
| 11 | Excés de cobrament | UC-104; UC-06; UC-28/29 | Distingir ingrés extern real, import no assignat i decisió documentada de devolució/saldo. | MAPAT PROVISIONAL — cas específic existent |
| 12 | fact_rels i origen legacy | UC-44; UC-01 | Enllaçar inscripcions/línies/operació sense atribuir imports automàticament per participant. | MAPAT PROVISIONAL — traçabilitat de dades |
| 13 | Factures antigues no VERI*FACTU | UC-11; UC-97 | Importació i consulta són operacions diferents; conservar emissor i numeració de l'original. | MAPAT PROVISIONAL — consulta/importació |
| 14 | Intranet tutor · documents visibles | UC-99; UC-07; UC-80 | Verificar naturalesa del document (honoraris o venda), titular, rol i abast: no donar accés fiscal per ser tutor. | MAPAT PROVISIONAL — PERMISOS A CONTRASTAR |
| 15 | Manual operatiu dins panell | Document 22 (manual operatiu intern); UC-34 | La consulta d'ajuda/manual no modifica estat fiscal; verificar si habilita comandes que ja tenen UC. | EVIDÈNCIA/UI — sense UC nova acreditada |
| 16 | Migració documents històrics | UC-11; UC-55; UC-78; UC-97 | Importar metadada no acredita fitxer físic; inventariar original, verificació de bytes i custòdia. | MAPAT PROVISIONAL — CAL COMPROVAR FLUX DOCUMENTAL |
| 17 | Mode auditor només lectura | UC-45; UC-59; UC-80 | Concessió/revocació i lectura no són la mateixa acció; revalidar grant a cada consulta. | MAPAT PROVISIONAL — control d'accés |
| 18 | Operació informativa | UC-100 | Acció explícita sense factura ni pagament; registrar motiu i classificació quan s'apliqui. | MAPAT PROVISIONAL — UC específica existent |
| 19 | Pagament duplicat | UC-25a; UC-02; UC-51; UC-86 | Diferenciar repetit per IDPAG, idempotència de moviment i callback Redsys duplicat; conciliar banc. | MAPAT PROVISIONAL — variants per origen |
| 20 | Pagament fraccionat | UC-23; UC-96; UC-12 | No confondre acord de quotes/pròrroga amb cobrament parcial efectiu; cada import ingressat té UUID_PAYMENT propi. | MAPAT PROVISIONAL — acord vs cobrament |
| 21 | Pagament parcial | UC-23; UC-02; UC-56 | Registrar un sol cobrament extern amb import parcial sobre factura existent; estat i saldo en cada reintent. | MAPAT PROVISIONAL — variant del cobrament |
| 22 | Rectificativa negativa | UC-05; UC-74; UC-28 | Determinar modalitat i signes/línies fiscals; un import negatiu al document no acredita REFUND bancari. | MAPAT PROVISIONAL — VARIANT FISCAL A VALIDAR |
| 23 | Rectificativa positiva | UC-05; UC-74; UC-02 | Determinar modalitat i signes/línies fiscals; import a favor de l'emissor no és CHARGE fins a ingrés efectiu. | MAPAT PROVISIONAL — VARIANT FISCAL A VALIDAR |
| 24 | Resum SIF sincronitzat a BD antiga | UC-47; UC-53; UC-82 | Comprovar idempotència del resum, divergències i recuperació després del commit, sense reescriptura de l'original fiscal. | MAPAT PROVISIONAL — integració |
| 25 | USOC | UC-13; UC-19; UC-19a; UC-19b | Validació, dos receptors/imports i conciliació posterior són accions relacionades però diferents. | MAPAT PROVISIONAL — orquestració |

## Com convertir el mapatge en cobertura demostrable

Per a cada fila, localitzar la ruta de pantalla/producte real, cada botó o acció (incloses accions asíncrones), actor autoritzat, l'endpoint/mètode al PHP de la **versió desplegada**, les taules afectades, l'UC i el pas del flux corresponent, i la prova específica amb resultat. Si un botó inicia una acció distinta, separar el flux encara que comparteixi vista. Si és ajuda o captura, vincular-lo a la UC que documenta: no crear una UC fiscal fictícia.

**Punts que requereixen especial decisió abans de tancar:** (a) diferència entre factura històrica i registre fiscal SIF; (b) signe, modalitat, taxes i receptor reals de rectificatives positives/negatives; (c) acord de fraccionament versus ingrés efectiu; (d) accés de tutors a documents segons naturalesa i titular; (e) despeses de gestió amb o sense factura emesa; (f) titular real de cada factura USOC. La pantalla genèrica no resol aquestes decisions.

## Fonts de casos d'ús d'especial interès

- [UC-05 rectificacions](uc-005-rectificar-factura.md), [UC-23 fraccions](uc-023-registrar-fraccio.md), [UC-56 cercar/assignar](uc-056-cercar-assignar-cobrament.md), [UC-95 estat acadèmic](uc-095-estat-academic-deute-pendent.md).
- [UC-11 històric](uc-011-importar-factura-historica.md), [UC-97 multiemissor històric](uc-097-consultar-historic-associacio-sl.md), [UC-99 tutors](uc-099-limitar-intranet-tutors-no-fiscal.md), [UC-104 excés](uc-104-gestionar-exces-cobrament.md).
- [UC-45 auditor temporal](uc-045-activar-auditor-temporal.md), [UC-59 revocació](uc-059-concedir-caducar-revocar-acces-auditor.md), [UC-47 sincronització](uc-047-sincronitzar-estat-cap-llegat.md), [UC-53 divergències](uc-053-detectar-resoldre-divergencies.md).

**Límit:** l'apartat 22 del [catàleg](../04-estat-final/33-casos-us-sif.md) classifica pantalles per família i inclou 25 pendents de validar; el mapatge anterior no s'ha obtingut d'una inspecció de cadascuna de les seves implementacions. És una primera traçabilitat documental a contrastar, no una auditoria dels 192 recorreguts.
