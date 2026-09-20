# SIF PrisMa · Fitxes de casos d'ús i UML integrats

Aquesta carpeta conté les **fitxes revisades per acció concreta**, no una substitució automàtica ni una còpia massiva dels esborranys de `../06-fitxes-funcionals/`. S'ha obert en una branca de documentació perquè es pugui revisar abans de fusionar-la.

## Mapa dels 59 casos d'ús revisats

**[Model general de classes](00-model-classes-general.md)** · **[Matriu dels 142 casos originals](00-matriu-cobertura-cataleg.md)** · **[Traça quantitativa dels diners per inscripció](00-revisio-moviments-inscripcions.md)**

| ID | Cas d'ús | Fitxa integrada | Estat original |
| --- | --- | --- | --- |
| UC-01 | Emetre o reutilitzar factura | [Fitxa i UML](uc-001-emetre-o-reutilitzar-factura.md) | `[BASE]` |
| UC-02 | Registrar pagament sobre factura | [Fitxa i UML](uc-002-registrar-cobrament-factura.md) | `[BASE]` |
| UC-03 | Processar cobrament Redsys asíncron | [Fitxa i UML](uc-003-processar-cobrament-redsys-asincron.md) | `[ASYNC]` |
| UC-04 | Emetre factura abans de cobrar | [Fitxa i UML](uc-004-emetre-factura-abans-cobrar.md) | `[PARCIAL]` |
| UC-05 | Crear rectificativa | [Fitxa i UML](uc-005-rectificar-factura.md) | `[PARCIAL]` |
| UC-06 | Registrar devolució, saldo o compensació | [Fitxa i UML](uc-006-devolucio-saldo-compensacio.md) | `[PARCIAL]` |
| UC-07 | Consultar factura, estat i document | [Fitxa i UML](uc-007-consultar-factura-estat-document.md) | `[DISSENY]` |
| UC-08 | Gestionar incidència | [Fitxa i UML](uc-008-gestionar-incidencia-sif.md) | `[PARCIAL]` |
| UC-09 | Remetre registre a AEAT | [Fitxa i UML](uc-009-remetre-registre-aeat.md) | `[DISSENY]` |
| UC-10 | Gestionar configuració i versió | [Fitxa i UML](uc-010-gestionar-configuracio-versio.md) | `[DISSENY]` |
| UC-11 | Importar factura històrica | [Fitxa i UML](uc-011-importar-factura-historica.md) | `[BASE]` |
| UC-12 | Gestionar el cicle de morositat i reclamació | [Fitxa i UML](uc-012-morositat-reclamacio.md) | `[PARCIAL]` |
| UC-13 | Orquestrar la doble facturació USOC | [Fitxa i UML](uc-013-orquestrar-doble-facturacio-usoc.md) | `[PARCIAL]` |
| UC-14 | Comprar curs normal per Redsys | [Fitxa i UML](uc-014-comprar-curs-redsys.md) | `[ASYNC/PARCIAL]` |
| UC-14a | Comprar taller | [Fitxa i UML](uc-014a-comprar-taller.md) | `[PARCIAL]` |
| UC-14b | Comprar jornada | [Fitxa i UML](uc-014b-comprar-jornada.md) | `[PARCIAL]` |
| UC-15 | Comprar pack | [Fitxa i UML](uc-015-comprar-pack.md) | `[BASE/ASYNC/PARCIAL]` |
| UC-16 | Facturar grup | [Fitxa i UML](uc-016-facturar-grup.md) | `[BASE/ASYNC/PARCIAL]` |
| UC-16a | Afegir participant després d'emetre | [Fitxa i UML](uc-016a-afegir-participant-grup-emes.md) | `[DISSENY]` |
| UC-16b | Treure participant després d'emetre | [Fitxa i UML](uc-016b-treure-participant-grup-emes.md) | `[DISSENY]` |
| UC-17 | Comprar regal | [Fitxa i UML](uc-017-comprar-regal.md) | `[BASE/ASYNC/PARCIAL]` |
| UC-18 | Bescanviar regal | [Fitxa i UML](uc-018-bescanviar-regal.md) | `[DISSENY]` |
| UC-18a | Gestionar regal caducat o duplicat | [Fitxa i UML](uc-018a-regal-caducat-duplicat.md) | `[DISSENY]` |
| UC-19 | Validar afiliació USOC | [Fitxa i UML](uc-019-validar-afiliacio-usoc.md) | `[DISSENY]` |
| UC-19a | Facturar part de l'alumne USOC | [Fitxa i UML](uc-019a-facturar-part-alumne-usoc.md) | `[BASE/ASYNC/PARCIAL]` |
| UC-19b | Facturar diferència a USOC | [Fitxa i UML](uc-019b-facturar-part-entitat-usoc.md) | `[BASE/PARCIAL]` |
| UC-20 | Aplicar Alumne PrisMa | [Fitxa i UML](uc-020-aplicar-alumne-prisma.md) | `[PARCIAL]` |
| UC-20a | Validar Carnet Jove | [Fitxa i UML](uc-020a-validar-carnet-jove.md) | `[DISSENY]` |
| UC-20b | Aplicar descompte sensible | [Fitxa i UML](uc-020b-aplicar-descompte-sensible.md) | `[DISSENY]` |
| UC-20c | Aplicar promoció temporal | [Fitxa i UML](uc-020c-aplicar-promocio-temporal.md) | `[PARCIAL]` |
| UC-20d | Aplicar codi promocional | [Fitxa i UML](uc-020d-aplicar-codi-promocional.md) | `[PARCIAL]` |
| UC-21 | Empresa/responsable paga inscripcions | [Fitxa i UML](uc-021-empresa-responsable-paga-inscripcions.md) | `[PARCIAL]` |
| UC-22 | Registrar transferència | [Fitxa i UML](uc-022-registrar-transferencia.md) | `[BASE/PARCIAL]` |
| UC-23 | Registrar fracció | [Fitxa i UML](uc-023-registrar-fraccio.md) | `[BASE/PARCIAL]` |
| UC-24 | Registrar cobrament de reclamació | [Fitxa i UML](uc-024-registrar-cobrament-reclamacio.md) | `[BASE/PARCIAL]` |
| UC-25 | Analitzar fitxer TPV | [Fitxa i UML](uc-025-analitzar-fitxer-tpv.md) | `[DISSENY]` |
| UC-25a | Comprovar IDPAG duplicats | [Fitxa i UML](uc-025a-comprovar-idpag-duplicats.md) | `[DISSENY]` |
| UC-26 | Canviar de curs | [Fitxa i UML](uc-026-canviar-de-curs.md) | `[DISSENY/PARCIAL]` |
| UC-27 | Donar de baixa | [Fitxa i UML](uc-027-donar-de-baixa.md) | `[DISSENY/PARCIAL]` |
| UC-28 | Registrar devolució | [Fitxa i UML](uc-028-registrar-devolucio.md) | `[BASE/PARCIAL]` |
| UC-29 | Crear saldo | [Fitxa i UML](uc-029-crear-saldo.md) | `[BASE/PARCIAL]` |
| UC-29a | Aplicar compensació | [Fitxa i UML](uc-029a-aplicar-compensacio.md) | `[BASE/PARCIAL]` |
| UC-30 | Anul·lar registre improcedent | [Fitxa i UML](uc-030-anul-lar-registre-improcedent.md) | `[DISSENY]` |
| UC-31 | Subsanar registre | [Fitxa i UML](uc-031-subsanar-registre.md) | `[DISSENY]` |
| UC-34 | Consultar dashboard | [Fitxa i UML](uc-034-consultar-dashboard-sif.md) | `[DISSENY]` |
| UC-35 | Consultar registre, cadena i estat AEAT | [Fitxa i UML](uc-035-consultar-registre-cadena-estat-aeat.md) | `[DISSENY]` |
| UC-36 | Generar/consultar PDF, QR o XML | [Fitxa i UML](uc-036-generar-consultar-documents.md) | `[PARCIAL]` |
| UC-47 | Sincronitzar l'estat mínim cap al llegat després del commit SIF | [Fitxa i UML](uc-047-sincronitzar-estat-cap-llegat.md) | `[BASE/PARCIAL]` |
| UC-51 | Tractar callback Redsys denegat, tardà, duplicat o contradictori | [Fitxa i UML](uc-051-callback-redsys-anomal.md) | `[ASYNC]` |
| UC-52 | Operar la cua Redsys | [Fitxa i UML](uc-052-operar-cua-redsys.md) | `[ASYNC/PARCIAL]` |
| UC-53 | Detectar i resoldre divergències SIF-llegat | [Fitxa i UML](uc-053-detectar-resoldre-divergencies.md) | `[DISSENY]` |
| UC-54 | Operar la cua fiscal i tractar la resposta AEAT | [Fitxa i UML](uc-054-operar-cua-fiscal-respostes.md) | `[DISSENY]` |
| UC-55 | Generar, reintentar i custodiar documents fiscals | [Fitxa i UML](uc-055-custodiar-reintentar-documents.md) | `[PARCIAL/DISSENY]` |
| UC-56 | Cercar i assignar un cobrament | [Fitxa i UML](uc-056-cercar-assignar-cobrament.md) | `[PARCIAL]` |
| UC-63 | Crear la intenció Redsys des de l'ecommerce | [Fitxa i UML](uc-063-crear-intencio-redsys.md) | `[ASYNC/PARCIAL]` |
| UC-71 | Registrar un canvi de curs complet | [Fitxa i UML](uc-071-registrar-canvi-curs-complet.md) | `[DISSENY/PARCIAL]` |
| UC-72 | Registrar baixa i decisió econòmica | [Fitxa i UML](uc-072-registrar-baixa-decisio-economica.md) | `[DISSENY/PARCIAL]` |
| UC-86 | Registrar qualsevol acció sobre un pagament | [Fitxa i UML](uc-086-auditar-accio-pagament.md) | [DISSENY/BLOQUEJANT] |
| UC-119 | Gestionar el cicle complet d'un regal o codi de bescanvi | [Fitxa i UML](uc-119-cicle-complet-regal.md) | `[LEGACY/DISSENY/BLOQUEJANT]` |

**Cobertura documental: 59/142.** Els 83 casos restants encara no tenen una fitxa UML específica revisada. L'estat original del catàleg no acredita implementació ni desplegament; cada fitxa nova distingeix les evidències. El ledger `enrollment_fund_movement` continua sent una **proposta** i no una migració/repo PHP implementats.

## Com llegir el paquet de cada acció

1. **Fitxa de cas d'ús:** actor, disparador, entrades/precondicions, passos concrets, variants, errors i postcondicions.
2. **Diagrama de casos d'ús:** font PlantUML editable amb actors, frontera de sistema i relacions UML `<<include>>`/`<<extend>>`, sense confondre una acció posterior amb una crida del mateix cas.
3. **Diagrama de classes:** subvista Mermaid de classes **PHP reals**. Les pantalles, endpoints procedurals i taules SQL no es converteixen en classes fictícies. Les classes del disseny futur es marquen expressament si s'hi han d'afegir.
4. **Diagrames de seqüència:** implementació del camí principal i, quan aporta informació pròpia, camins alternatius o excepcions. Els límits de transacció i els processos asíncrons es representen explícitament.
5. **Traçabilitat:** enllaços a la fitxa anterior, als documents de referència i al codi/proves concrets.

**Visualització:** GitHub representa els blocs `mermaid` de classes i seqüència. Els blocs `plantuml` són la **font UML editable** dels casos d'ús, però GitHub pot mostrar-los com a codi; per obtenir-ne la imatge cal renderitzar-los amb PlantUML o incorporar-ne un SVG generat. No confondre la disponibilitat del codi del diagrama amb una imatge ja exportada.

## Matriu de traçabilitat i pendents

La [matriu de cobertura completa](00-matriu-cobertura-cataleg.md) vincula cada UC original amb la seva fitxa UML quan existeix i mostra els casos pendents, sense marcar com a acabat un cas perquè té una plantilla. Les dependències i fonts concretes consten a cada fitxa i al [model de classes general](00-model-classes-general.md).

## Criteris per ampliar aquest catàleg

- Continuar pels expedients detallats UC-71 i UC-72 i pels processos transversals de canvi i baixa i, en paral·lel, documentar els processos transversals no resolts (classificació fiscal, enviament AEAT, documents, incidències, controls d'operació).
- Revisar la cardinalitat dels actors i les relacions `include`/`extend` per cada acció, sense dibuixar una cadena automàtica on només hi ha una operació futura separada.
- Triangular **xat pont, fitxa anterior, codi del cas i model de dades** quan hi hagi divergències; distingir sempre `codi observat`, `contracte documental` i `pendent de decisió`. Les referències al xat pont només es donaran per verificades després d'identificar-ne el fragment concret.
- No declarar un cas tancat només perquè té els quatre apartats UML: requereix validació funcional, revisió de permisos, prova del flux d'extrem a extrem i evidència de comportament correcte a l'entorn corresponent.

Referències comunes: [Casos d'ús generals](../04-estat-final/33-casos-us-sif.md), [classes del SIF](../04-estat-final/31-diagrames-classes-sif.md), [seqüències del SIF](../04-estat-final/32-diagrames-sequencia-sif.md), [matriu de traçabilitat](../04-estat-final/35-matriu-tracabilitat-diagrames.md) i [fitxes anteriors](../06-fitxes-funcionals/README.md).
