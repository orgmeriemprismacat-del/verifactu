# SIF PrisMa · Fitxes de casos d'ús i UML integrats

Aquesta carpeta conté les **fitxes revisades per acció concreta**, no una substitució automàtica ni una còpia massiva dels esborranys de `../06-fitxes-funcionals/`. S'ha obert en una branca de documentació perquè es pugui revisar abans de fusionar-la.

## Mapa dels 30 casos d'ús revisats

**[Model general de classes del SIF](00-model-classes-general.md)** · **[Cobertura de tots els 142 casos originals](00-matriu-cobertura-cataleg.md)** · **[Revisió del registre de fons per inscripció](00-revisio-moviments-inscripcions.md)**

| ID | Denominació del catàleg | Document específic | Estat original del catàleg |
| --- | --- | --- | --- |
| UC-01 | Emetre o reutilitzar factura | [Fitxa + UML](uc-001-emetre-o-reutilitzar-factura.md) | `[BASE]` |
| UC-02 | Registrar pagament sobre factura | [Fitxa + UML](uc-002-registrar-cobrament-factura.md) | `[BASE]` |
| UC-03 | Processar cobrament Redsys asíncron | [Fitxa + UML](uc-003-processar-cobrament-redsys-asincron.md) | `[ASYNC]` |
| UC-04 | Emetre factura abans de cobrar | [Fitxa + UML](uc-004-emetre-factura-abans-cobrar.md) | `[PARCIAL]` |
| UC-05 | Crear rectificativa | [Fitxa + UML](uc-005-rectificar-factura.md) | `[PARCIAL]` |
| UC-06 | Registrar devolució, saldo o compensació | [Fitxa + UML](uc-006-devolucio-saldo-compensacio.md) | `[PARCIAL]` |
| UC-07 | Consultar factura, estat i document | [Fitxa + UML](uc-007-consultar-factura-estat-document.md) | `[DISSENY]` |
| UC-08 | Gestionar incidència | [Fitxa + UML](uc-008-gestionar-incidencia-sif.md) | `[PARCIAL]` |
| UC-09 | Remetre registre a AEAT | [Fitxa + UML](uc-009-remetre-registre-aeat.md) | `[DISSENY]` |
| UC-13 | Orquestrar la doble facturació USOC | [Fitxa + UML](uc-013-orquestrar-doble-facturacio-usoc.md) | `[PARCIAL]` |
| UC-14 | Comprar curs normal per Redsys | [Fitxa + UML](uc-014-comprar-curs-redsys.md) | `[ASYNC/PARCIAL]` |
| UC-15 | Comprar pack | [Fitxa + UML](uc-015-comprar-pack.md) | `[BASE/ASYNC/PARCIAL]` |
| UC-16 | Facturar grup | [Fitxa + UML](uc-016-facturar-grup.md) | `[BASE/ASYNC/PARCIAL]` |
| UC-17 | Comprar regal | [Fitxa + UML](uc-017-comprar-regal.md) | `[BASE/ASYNC/PARCIAL]` |
| UC-19a | Facturar part de l'alumne USOC | [Fitxa + UML](uc-019a-facturar-part-alumne-usoc.md) | `[BASE/ASYNC/PARCIAL]` |
| UC-19b | Facturar diferència a USOC | [Fitxa + UML](uc-019b-facturar-part-entitat-usoc.md) | `[BASE/PARCIAL]` |
| UC-21 | Empresa/responsable paga inscripcions | [Fitxa + UML](uc-021-empresa-responsable-paga-inscripcions.md) | `[PARCIAL]` |
| UC-22 | Registrar transferència | [Fitxa + UML](uc-022-registrar-transferencia.md) | `[BASE/PARCIAL]` |
| UC-23 | Registrar fracció | [Fitxa + UML](uc-023-registrar-fraccio.md) | `[BASE/PARCIAL]` |
| UC-24 | Registrar cobrament de reclamació | [Fitxa + UML](uc-024-registrar-cobrament-reclamacio.md) | `[BASE/PARCIAL]` |
| UC-26 | Canviar de curs | [Fitxa + UML](uc-026-canviar-de-curs.md) | `[DISSENY/PARCIAL]` |
| UC-27 | Donar de baixa | [Fitxa + UML](uc-027-donar-de-baixa.md) | `[DISSENY/PARCIAL]` |
| UC-28 | Registrar devolució | [Fitxa + UML](uc-028-registrar-devolucio.md) | `[BASE/PARCIAL]` |
| UC-29 | Crear saldo | [Fitxa + UML](uc-029-crear-saldo.md) | `[BASE/PARCIAL]` |
| UC-29a | Aplicar compensació | [Fitxa + UML](uc-029a-aplicar-compensacio.md) | `[BASE/PARCIAL]` |
| UC-30 | Anul·lar registre improcedent | [Fitxa + UML](uc-030-anul-lar-registre-improcedent.md) | `[DISSENY]` |
| UC-31 | Subsanar registre | [Fitxa + UML](uc-031-subsanar-registre.md) | `[DISSENY]` |
| UC-63 | Crear la intenció Redsys des de l'ecommerce | [Fitxa + UML](uc-063-crear-intencio-redsys.md) | `[ASYNC/PARCIAL]` |
| UC-71 | Registrar un canvi de curs complet | [Fitxa + UML](uc-071-registrar-canvi-curs-complet.md) | `[DISSENY/PARCIAL]` |
| UC-72 | Registrar baixa i decisió econòmica | [Fitxa + UML](uc-072-registrar-baixa-decisio-economica.md) | `[DISSENY/PARCIAL]` |

**Abast actual:** 30 fitxes individuals amb UML revisat i 112 casos pendents de revisió específica. Les etiquetes d'estat de la darrera columna són les del catàleg original; el codi consultat pot haver evolucionat (UC-09/30/31 ja tenen nucli executable, però no acreditació productiva). **Documentat no significa implementat ni provat**. Els documents UC-26/27/71/72 distingeixen orquestració objectiu de nuclis PHP existents.

### Traçabilitat econòmica bloquejant

Les accions que cobren, retornen, compensen o reassignen imports d'inscripcions han de conservar import, origen, destí i pagament original. La proposta `enrollment_fund_movement` **no és una migració ni un repositori implementats**. Un traspàs entre inscripcions no crea un segon CHARGE. [Model i invariants](00-revisio-moviments-inscripcions.md).

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
