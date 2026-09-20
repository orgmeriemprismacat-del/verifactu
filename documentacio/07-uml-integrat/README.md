# SIF PrisMa · Fitxes de casos d'ús i UML integrats

Aquesta carpeta conté les **fitxes revisades per acció concreta**, no una substitució automàtica ni una còpia massiva dels esborranys de `../06-fitxes-funcionals/`. S'ha obert en una branca de documentació perquè es pugui revisar abans de fusionar-la.

## Mapa dels casos revisats

| ID | Cas | Fitxa amb diagrama de casos d'ús, classes i seqüència | Què s'ha contrastat |
| --- | --- | --- | --- |
| UC-01 | Emetre o reutilitzar una factura | [Fitxa + UML UC-01](uc-001-emetre-o-reutilitzar-factura.md) | Validador, transacció, numeració, cadena, cua, pagament inicial, idempotència i proves existents |
| UC-02 | Registrar un pagament sobre factura existent | [Fitxa + UML UC-02](uc-002-registrar-cobrament-factura.md) | Moviment, assignacions, càlcul de l'estat, adaptador manual i proves existents |
| UC-03 | Processar un cobrament Redsys asíncron | [Fitxa + UML UC-03](uc-003-processar-cobrament-redsys-asincron.md) | Signatura, intenció, notificació, duplicat, cua, worker, handlers, reintents i proves existents |
| UC-04 | Emetre una factura abans de cobrar | [Fitxa + UML UC-04](uc-004-emetre-factura-abans-cobrar.md) | Constructor específic, emissió sense pagament i cobrament posterior en UC-02 |
| UC-05 | Rectificar una factura | [Fitxa + UML UC-05](uc-005-rectificar-factura.md) | Localització original, construcció sèrie R, vinculació, estats i risc d'atomicitat |

**Abast actual:** aquests cinc casos estan redactats i contrastats documentalment amb les classes i proves citades; **això no vol dir que tot el catàleg UC estigui complet, que s'hagin executat els tests o que els casos estiguin desplegats**.

## Com llegir el paquet de cada acció

1. **Fitxa de cas d'ús:** actor, disparador, entrades/precondicions, passos concrets, variants, errors i postcondicions.
2. **Diagrama de casos d'ús:** font PlantUML editable amb actors, frontera de sistema i relacions UML `<<include>>`/`<<extend>>`, sense confondre una acció posterior amb una crida del mateix cas.
3. **Diagrama de classes:** subvista Mermaid de classes **PHP reals**. Les pantalles, endpoints procedurals i taules SQL no es converteixen en classes fictícies. Les classes del disseny futur es marquen expressament si s'hi han d'afegir.
4. **Diagrames de seqüència:** implementació del camí principal i, quan aporta informació pròpia, camins alternatius o excepcions. Els límits de transacció i els processos asíncrons es representen explícitament.
5. **Traçabilitat:** enllaços a la fitxa anterior, als documents de referència i al codi/proves concrets.

**Visualització:** GitHub representa els blocs `mermaid` de classes i seqüència. Els blocs `plantuml` són la **font UML editable** dels casos d'ús, però GitHub pot mostrar-los com a codi; per obtenir-ne la imatge cal renderitzar-los amb PlantUML o incorporar-ne un SVG generat. No confondre la disponibilitat del codi del diagrama amb una imatge ja exportada.

## Matriu de traçabilitat preliminar

| Acció | Fitxa | Cas d'ús UML | Classes | Seqüència | Dependències funcionals |
| --- | --- | --- | --- | --- | --- |
| UC-01 | Sí | Sí | Sí | Sí | UC-04, UC-05, UC-09, UC-14… |
| UC-02 | Sí | Sí | Sí | Sí (general + manual) | UC-04, UC-22, UC-23, UC-28 |
| UC-03 | Sí | Sí | Sí | Sí (HTTP + worker) | UC-01, UC-02, UC-51, UC-52, UC-63 |
| UC-04 | Sí | Sí | Sí | Sí (emissió + cobrament vinculat) | UC-01, UC-02, UC-21 |
| UC-05 | Sí | Sí | Sí | Sí (nominal + fallada intermèdia) | UC-01, UC-28, UC-30, UC-31, UC-74 |

## Criteris per ampliar aquest catàleg

- Continuar per fluxos de negoci concrets (UC-21 empresa/responsable; UC-22 transferència; UC-26/71 canvi de curs; UC-27/72 baixa) i, en paral·lel, documentar els processos transversals no resolts (classificació fiscal, enviament AEAT, documents, incidències, controls d'operació).
- Revisar la cardinalitat dels actors i les relacions `include`/`extend` per cada acció, sense dibuixar una cadena automàtica on només hi ha una operació futura separada.
- Triangular **xat pont, fitxa anterior, codi del cas i model de dades** quan hi hagi divergències; distingir sempre `codi observat`, `contracte documental` i `pendent de decisió`. Les referències al xat pont només es donaran per verificades després d'identificar-ne el fragment concret.
- No declarar un cas tancat només perquè té els quatre apartats UML: requereix validació funcional, revisió de permisos, prova del flux d'extrem a extrem i evidència de comportament correcte a l'entorn corresponent.

Referències comunes: [Casos d'ús generals](../04-estat-final/33-casos-us-sif.md), [classes del SIF](../04-estat-final/31-diagrames-classes-sif.md), [seqüències del SIF](../04-estat-final/32-diagrames-sequencia-sif.md), [matriu de traçabilitat](../04-estat-final/35-matriu-tracabilitat-diagrames.md) i [fitxes anteriors](../06-fitxes-funcionals/README.md).
