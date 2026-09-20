# Matriu completa de cobertura UML del catàleg SIF

**Base:** [142 fitxes originals](../06-fitxes-funcionals/README.md) i fitxes UML existents en aquesta branca. **Cobertura documental: 48 de 142 casos; 94 pendents de revisió específica.** Documentat no vol dir implementat, provat ni desplegat. Els estats originals poden estar desactualitzats respecte del codi verificat en cada fitxa nova.

## Cobertura per domini

| Domini del catàleg | Total | Revisats | Pendents |
| --- | ---: | ---: | ---: |
| facturació i registre fiscal | 33 | 18 | 15 |
| pagaments i conciliació | 30 | 14 | 16 |
| documents, accés i comunicacions | 11 | 3 | 8 |
| governança i operació | 29 | 7 | 22 |
| integració SIF | 1 | 0 | 1 |
| venda i descomptes | 7 | 3 | 4 |
| canvis posteriors | 2 | 2 | 0 |
| gestió operativa | 2 | 0 | 2 |
| circuit adjacent de col·laboradors | 2 | 0 | 2 |
| casuística recuperada | 1 | 0 | 1 |
| operació comercial i inscripció | 7 | 0 | 7 |
| cicle de vida comercial, acadèmic i documental | 12 | 1 | 11 |
| consentiment, identitat i coherència entre sistemes | 5 | 0 | 5 |

## Relació de tots els casos

| ID | Denominació del catàleg | Estat original | Fitxa original | Fitxa UML revisada |
| --- | --- | --- | --- | --- |
| UC-01 | Emetre o reutilitzar factura | `[BASE]` | [uc-001.md](../06-fitxes-funcionals/uc-001.md) | [Fitxa i diagrames](uc-001-emetre-o-reutilitzar-factura.md) |
| UC-02 | Registrar pagament sobre factura | `[BASE]` | [uc-002.md](../06-fitxes-funcionals/uc-002.md) | [Fitxa i diagrames](uc-002-registrar-cobrament-factura.md) |
| UC-03 | Processar cobrament Redsys asíncron | `[ASYNC]` | [uc-003.md](../06-fitxes-funcionals/uc-003.md) | [Fitxa i diagrames](uc-003-processar-cobrament-redsys-asincron.md) |
| UC-04 | Emetre factura abans de cobrar | `[PARCIAL]` | [uc-004.md](../06-fitxes-funcionals/uc-004.md) | [Fitxa i diagrames](uc-004-emetre-factura-abans-cobrar.md) |
| UC-05 | Crear rectificativa | `[PARCIAL]` | [uc-005.md](../06-fitxes-funcionals/uc-005.md) | [Fitxa i diagrames](uc-005-rectificar-factura.md) |
| UC-06 | Registrar devolució, saldo o compensació | `[PARCIAL]` | [uc-006.md](../06-fitxes-funcionals/uc-006.md) | [Fitxa i diagrames](uc-006-devolucio-saldo-compensacio.md) |
| UC-07 | Consultar factura, estat i document | `[DISSENY]` | [uc-007.md](../06-fitxes-funcionals/uc-007.md) | [Fitxa i diagrames](uc-007-consultar-factura-estat-document.md) |
| UC-08 | Gestionar incidència | `[PARCIAL]` | [uc-008.md](../06-fitxes-funcionals/uc-008.md) | [Fitxa i diagrames](uc-008-gestionar-incidencia-sif.md) |
| UC-09 | Remetre registre a AEAT | `[DISSENY]` | [uc-009.md](../06-fitxes-funcionals/uc-009.md) | [Fitxa i diagrames](uc-009-remetre-registre-aeat.md) |
| UC-10 | Gestionar configuració i versió | `[DISSENY]` | [uc-010.md](../06-fitxes-funcionals/uc-010.md) | [Fitxa i diagrames](uc-010-gestionar-configuracio-versio.md) |
| UC-11 | Importar factura històrica | `[BASE]` | [uc-011.md](../06-fitxes-funcionals/uc-011.md) | [Fitxa i diagrames](uc-011-importar-factura-historica.md) |
| UC-12 | Gestionar el cicle de morositat i reclamació | `[PARCIAL]` | [uc-012.md](../06-fitxes-funcionals/uc-012.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-13 | Orquestrar la doble facturació USOC | `[PARCIAL]` | [uc-013.md](../06-fitxes-funcionals/uc-013.md) | [Fitxa i diagrames](uc-013-orquestrar-doble-facturacio-usoc.md) |
| UC-14 | Comprar curs normal per Redsys | `[ASYNC/PARCIAL]` | [uc-014.md](../06-fitxes-funcionals/uc-014.md) | [Fitxa i diagrames](uc-014-comprar-curs-redsys.md) |
| UC-14a | Comprar taller | `[PARCIAL]` | [uc-014a.md](../06-fitxes-funcionals/uc-014a.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-14b | Comprar jornada | `[PARCIAL]` | [uc-014b.md](../06-fitxes-funcionals/uc-014b.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-15 | Comprar pack | `[BASE/ASYNC/PARCIAL]` | [uc-015.md](../06-fitxes-funcionals/uc-015.md) | [Fitxa i diagrames](uc-015-comprar-pack.md) |
| UC-16 | Facturar grup | `[BASE/ASYNC/PARCIAL]` | [uc-016.md](../06-fitxes-funcionals/uc-016.md) | [Fitxa i diagrames](uc-016-facturar-grup.md) |
| UC-16a | Afegir participant després d'emetre | `[DISSENY]` | [uc-016a.md](../06-fitxes-funcionals/uc-016a.md) | [Fitxa i diagrames](uc-016a-afegir-participant-grup-emes.md) |
| UC-16b | Treure participant després d'emetre | `[DISSENY]` | [uc-016b.md](../06-fitxes-funcionals/uc-016b.md) | [Fitxa i diagrames](uc-016b-treure-participant-grup-emes.md) |
| UC-17 | Comprar regal | `[BASE/ASYNC/PARCIAL]` | [uc-017.md](../06-fitxes-funcionals/uc-017.md) | [Fitxa i diagrames](uc-017-comprar-regal.md) |
| UC-18 | Bescanviar regal | `[DISSENY]` | [uc-018.md](../06-fitxes-funcionals/uc-018.md) | [Fitxa i diagrames](uc-018-bescanviar-regal.md) |
| UC-18a | Gestionar regal caducat o duplicat | `[DISSENY]` | [uc-018a.md](../06-fitxes-funcionals/uc-018a.md) | [Fitxa i diagrames](uc-018a-regal-caducat-duplicat.md) |
| UC-19 | Validar afiliació USOC | `[DISSENY]` | [uc-019.md](../06-fitxes-funcionals/uc-019.md) | [Fitxa i diagrames](uc-019-validar-afiliacio-usoc.md) |
| UC-19a | Facturar part de l'alumne USOC | `[BASE/ASYNC/PARCIAL]` | [uc-019a.md](../06-fitxes-funcionals/uc-019a.md) | [Fitxa i diagrames](uc-019a-facturar-part-alumne-usoc.md) |
| UC-19b | Facturar diferència a USOC | `[BASE/PARCIAL]` | [uc-019b.md](../06-fitxes-funcionals/uc-019b.md) | [Fitxa i diagrames](uc-019b-facturar-part-entitat-usoc.md) |
| UC-20 | Aplicar Alumne PrisMa | `[PARCIAL]` | [uc-020.md](../06-fitxes-funcionals/uc-020.md) | [Fitxa i diagrames](uc-020-aplicar-alumne-prisma.md) |
| UC-20a | Validar Carnet Jove | `[DISSENY]` | [uc-020a.md](../06-fitxes-funcionals/uc-020a.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-20b | Aplicar descompte sensible | `[DISSENY]` | [uc-020b.md](../06-fitxes-funcionals/uc-020b.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-20c | Aplicar promoció temporal | `[PARCIAL]` | [uc-020c.md](../06-fitxes-funcionals/uc-020c.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-20d | Aplicar codi promocional | `[PARCIAL]` | [uc-020d.md](../06-fitxes-funcionals/uc-020d.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-21 | Empresa/responsable paga inscripcions | `[PARCIAL]` | [uc-021.md](../06-fitxes-funcionals/uc-021.md) | [Fitxa i diagrames](uc-021-empresa-responsable-paga-inscripcions.md) |
| UC-22 | Registrar transferència | `[BASE/PARCIAL]` | [uc-022.md](../06-fitxes-funcionals/uc-022.md) | [Fitxa i diagrames](uc-022-registrar-transferencia.md) |
| UC-23 | Registrar fracció | `[BASE/PARCIAL]` | [uc-023.md](../06-fitxes-funcionals/uc-023.md) | [Fitxa i diagrames](uc-023-registrar-fraccio.md) |
| UC-24 | Registrar cobrament de reclamació | `[BASE/PARCIAL]` | [uc-024.md](../06-fitxes-funcionals/uc-024.md) | [Fitxa i diagrames](uc-024-registrar-cobrament-reclamacio.md) |
| UC-25 | Analitzar fitxer TPV | `[DISSENY]` | [uc-025.md](../06-fitxes-funcionals/uc-025.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-25a | Comprovar IDPAG duplicats | `[DISSENY]` | [uc-025a.md](../06-fitxes-funcionals/uc-025a.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-26 | Canviar de curs | `[DISSENY/PARCIAL]` | [uc-026.md](../06-fitxes-funcionals/uc-026.md) | [Fitxa i diagrames](uc-026-canviar-de-curs.md) |
| UC-27 | Donar de baixa | `[DISSENY/PARCIAL]` | [uc-027.md](../06-fitxes-funcionals/uc-027.md) | [Fitxa i diagrames](uc-027-donar-de-baixa.md) |
| UC-28 | Registrar devolució | `[BASE/PARCIAL]` | [uc-028.md](../06-fitxes-funcionals/uc-028.md) | [Fitxa i diagrames](uc-028-registrar-devolucio.md) |
| UC-29 | Crear saldo | `[BASE/PARCIAL]` | [uc-029.md](../06-fitxes-funcionals/uc-029.md) | [Fitxa i diagrames](uc-029-crear-saldo.md) |
| UC-29a | Aplicar compensació | `[BASE/PARCIAL]` | [uc-029a.md](../06-fitxes-funcionals/uc-029a.md) | [Fitxa i diagrames](uc-029a-aplicar-compensacio.md) |
| UC-30 | Anul·lar registre improcedent | `[DISSENY]` | [uc-030.md](../06-fitxes-funcionals/uc-030.md) | [Fitxa i diagrames](uc-030-anul-lar-registre-improcedent.md) |
| UC-31 | Subsanar registre | `[DISSENY]` | [uc-031.md](../06-fitxes-funcionals/uc-031.md) | [Fitxa i diagrames](uc-031-subsanar-registre.md) |
| UC-32 | Marcar o desmarcar factura electrònica | `[DISSENY]` | [uc-032.md](../06-fitxes-funcionals/uc-032.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-33 | Desactivar URL de pagament | `[DISSENY]` | [uc-033.md](../06-fitxes-funcionals/uc-033.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-34 | Consultar dashboard | `[DISSENY]` | [uc-034.md](../06-fitxes-funcionals/uc-034.md) | [Fitxa i diagrames](uc-034-consultar-dashboard-sif.md) |
| UC-35 | Consultar registre, cadena i estat AEAT | `[DISSENY]` | [uc-035.md](../06-fitxes-funcionals/uc-035.md) | [Fitxa i diagrames](uc-035-consultar-registre-cadena-estat-aeat.md) |
| UC-36 | Generar/consultar PDF, QR o XML | `[PARCIAL]` | [uc-036.md](../06-fitxes-funcionals/uc-036.md) | [Fitxa i diagrames](uc-036-generar-consultar-documents.md) |
| UC-37 | Exportar període fiscal | `[DISSENY]` | [uc-037.md](../06-fitxes-funcionals/uc-037.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-38 | Configurar SIF i certificat | `[DISSENY]` | [uc-038.md](../06-fitxes-funcionals/uc-038.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-39 | Executar proves i go/no-go | `[BASE/PARCIAL]` | [uc-039.md](../06-fitxes-funcionals/uc-039.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-40 | Fer backup i restauració | `[DISSENY]` | [uc-040.md](../06-fitxes-funcionals/uc-040.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-41 | Crear o editar entitat/responsable | `[DISSENY]` | [uc-041.md](../06-fitxes-funcionals/uc-041.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-42 | Consultar/modificar alumne | `[DISSENY]` | [uc-042.md](../06-fitxes-funcionals/uc-042.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-43 | Gestionar notificacions i recordatoris | `[DISSENY]` | [uc-043.md](../06-fitxes-funcionals/uc-043.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-44 | Consultar i mantenir `fact_rels` i origen legacy | `[BASE/PARCIAL]` | [uc-044.md](../06-fitxes-funcionals/uc-044.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-45 | Activar auditor temporal | `[DISSENY]` | [uc-045.md](../06-fitxes-funcionals/uc-045.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-46 | Activar versió i declaració responsable | `[DISSENY]` | [uc-046.md](../06-fitxes-funcionals/uc-046.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-47 | Sincronitzar l'estat mínim cap al llegat després del commit SIF | `[BASE/PARCIAL]` | [uc-047.md](../06-fitxes-funcionals/uc-047.md) | [Fitxa i diagrames](uc-047-sincronitzar-estat-cap-llegat.md) |
| UC-48 | Crear o consultar una proforma no fiscal | `[LEGACY/DISSENY]` | [uc-048.md](../06-fitxes-funcionals/uc-048.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-49 | Enviar factura, document o avis per correu | `[LEGACY/DISSENY]` | [uc-049.md](../06-fitxes-funcionals/uc-049.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-50 | Crear, consultar, desactivar o caducar un enllaç de pagament | `[DISSENY]` | [uc-050.md](../06-fitxes-funcionals/uc-050.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-51 | Tractar callback Redsys denegat, tardà, duplicat o contradictori | `[ASYNC]` | [uc-051.md](../06-fitxes-funcionals/uc-051.md) | [Fitxa i diagrames](uc-051-callback-redsys-anomal.md) |
| UC-52 | Operar la cua Redsys | `[ASYNC/PARCIAL]` | [uc-052.md](../06-fitxes-funcionals/uc-052.md) | [Fitxa i diagrames](uc-052-operar-cua-redsys.md) |
| UC-53 | Detectar i resoldre divergències SIF-llegat | `[DISSENY]` | [uc-053.md](../06-fitxes-funcionals/uc-053.md) | [Fitxa i diagrames](uc-053-detectar-resoldre-divergencies.md) |
| UC-54 | Operar la cua fiscal i tractar la resposta AEAT | `[DISSENY]` | [uc-054.md](../06-fitxes-funcionals/uc-054.md) | [Fitxa i diagrames](uc-054-operar-cua-fiscal-respostes.md) |
| UC-55 | Generar, reintentar i custodiar documents fiscals | `[PARCIAL/DISSENY]` | [uc-055.md](../06-fitxes-funcionals/uc-055.md) | [Fitxa i diagrames](uc-055-custodiar-reintentar-documents.md) |
| UC-56 | Cercar i assignar un cobrament | `[PARCIAL]` | [uc-056.md](../06-fitxes-funcionals/uc-056.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-57 | Mantenir i optimitzar la BD SIF | `[DISSENY]` | [uc-057.md](../06-fitxes-funcionals/uc-057.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-58 | Gestionar l'outbox de notificacions | `[DISSENY]` | [uc-058.md](../06-fitxes-funcionals/uc-058.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-59 | Concedir, caducar i revocar accés auditor | `[DISSENY]` | [uc-059.md](../06-fitxes-funcionals/uc-059.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-60 | Monitorar salut, cues, documents, backups i versió activa | `[DISSENY]` | [uc-060.md](../06-fitxes-funcionals/uc-060.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-61 | Consultar un import pendent i obtenir un enllaç de pagament | `[LEGACY/OBJECTIU]` | [uc-061.md](../06-fitxes-funcionals/uc-061.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-62 | Iniciar factura o cobrament des de la intranet | `[LEGACY/DISSENY]` | [uc-062.md](../06-fitxes-funcionals/uc-062.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-63 | Crear la intenció Redsys des de l'ecommerce | `[ASYNC/PARCIAL]` | [uc-063.md](../06-fitxes-funcionals/uc-063.md) | [Fitxa i diagrames](uc-063-crear-intencio-redsys.md) |
| UC-64 | Reconciliar la candidata amb el codi actual | `[CONTROL/PENDENT]` | [uc-064.md](../06-fitxes-funcionals/uc-064.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-65 | Presentar una factura o rebut de col·laborador | `[LEGACY/ADJACENT]` | [uc-065.md](../06-fitxes-funcionals/uc-065.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-66 | Consultar i gestionar cobraments d'un col·laborador | `[LEGACY/ADJACENT]` | [uc-066.md](../06-fitxes-funcionals/uc-066.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-67 | Externalitzar i rotar secrets de pagament | `[PENDENT/BLOQUEJANT]` | [uc-067.md](../06-fitxes-funcionals/uc-067.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-68 | Retirar callbacks i escriptures fiscals llegades | `[DISSENY]` | [uc-068.md](../06-fitxes-funcionals/uc-068.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-69 | Confirmar i congelar dades fiscals | `[DISSENY/PARCIAL]` | [uc-069.md](../06-fitxes-funcionals/uc-069.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-70 | Modificar dades mestres després d'emetre | `[DISSENY]` | [uc-070.md](../06-fitxes-funcionals/uc-070.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-71 | Registrar un canvi de curs complet | `[DISSENY/PARCIAL]` | [uc-071.md](../06-fitxes-funcionals/uc-071.md) | [Fitxa i diagrames](uc-071-registrar-canvi-curs-complet.md) |
| UC-72 | Registrar baixa i decisió econòmica | `[DISSENY/PARCIAL]` | [uc-072.md](../06-fitxes-funcionals/uc-072.md) | [Fitxa i diagrames](uc-072-registrar-baixa-decisio-economica.md) |
| UC-73 | Documentar un ajust, descompte o despesa | `[DISSENY]` | [uc-073.md](../06-fitxes-funcionals/uc-073.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-74 | Classificar una correcció fiscal | `[DISSENY/BLOQUEJANT]` | [uc-074.md](../06-fitxes-funcionals/uc-074.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-75 | Crear un registre d'anul·lació | `[DISSENY/BLOQUEJANT]` | [uc-075.md](../06-fitxes-funcionals/uc-075.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-76 | Crear un registre de subsanació | `[DISSENY/BLOQUEJANT]` | [uc-076.md](../06-fitxes-funcionals/uc-076.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-77 | Operar enviament AEAT, retry i dead-letter | `[DISSENY/BLOQUEJANT]` | [uc-077.md](../06-fitxes-funcionals/uc-077.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-78 | Generar i custodiar PDF, QR i XML | `[PARCIAL/DISSENY]` | [uc-078.md](../06-fitxes-funcionals/uc-078.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-79 | Enviar una comunicació fiscal auditable | `[DISSENY]` | [uc-079.md](../06-fitxes-funcionals/uc-079.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-80 | Servir i registrar accés a document fiscal | `[DISSENY]` | [uc-080.md](../06-fitxes-funcionals/uc-080.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-81 | Gestionar el cicle complet d'una incidència | `[PARCIAL/DISSENY]` | [uc-081.md](../06-fitxes-funcionals/uc-081.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-82 | Reconciliar SIF amb la BD llegada | `[DISSENY]` | [uc-082.md](../06-fitxes-funcionals/uc-082.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-83 | Registrar i activar versió i declaració | `[DISSENY/BLOQUEJANT]` | [uc-083.md](../06-fitxes-funcionals/uc-083.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-84 | Crear un paquet fiscal d'auditoria | `[DISSENY]` | [uc-084.md](../06-fitxes-funcionals/uc-084.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-85 | Executar backup, restauració i reconciliació | `[DISSENY/BLOQUEJANT]` | [uc-085.md](../06-fitxes-funcionals/uc-085.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-86 | Registrar qualsevol acció sobre un pagament | [DISSENY/BLOQUEJANT] | [uc-086.md](../06-fitxes-funcionals/uc-086.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-87 | Validar receptor estranger o amb dades fiscals incompletes | `[DISSENY/BLOQUEJANT]` | [uc-087.md](../06-fitxes-funcionals/uc-087.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-88 | Decidir agrupació i línies d'una factura multiconcepte | `[DISSENY/PARCIAL]` | [uc-088.md](../06-fitxes-funcionals/uc-088.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-89 | Canviar concepte després del cobrament o emissió | `[DISSENY/BLOQUEJANT]` | [uc-089.md](../06-fitxes-funcionals/uc-089.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-90 | Resoldre un descompte validat després de la compra | `[DISSENY/BLOQUEJANT]` | [uc-090.md](../06-fitxes-funcionals/uc-090.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-91 | Aplicar descompte de grup per trams | `[DISSENY/PARCIAL]` | [uc-091.md](../06-fitxes-funcionals/uc-091.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-92 | Registrar venda manual des d'intranet o telèfon | `[DISSENY/PARCIAL]` | [uc-092.md](../06-fitxes-funcionals/uc-092.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-93 | Canviar el receptor fiscal sol·licitat després d'una compra particular | `[DISSENY/BLOQUEJANT]` | [uc-093.md](../06-fitxes-funcionals/uc-093.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-94 | Ajustar manualment l'import a pagar amb justificació | `[DISSENY/BLOQUEJANT]` | [uc-094.md](../06-fitxes-funcionals/uc-094.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-95 | Gestionar estat acadèmic amb deute pendent | `[DISSENY]` | [uc-095.md](../06-fitxes-funcionals/uc-095.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-96 | Concedir una pròrroga de pagament fins a la segona setmana | `[DISSENY]` | [uc-096.md](../06-fitxes-funcionals/uc-096.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-97 | Consultar històric barrejat Associació/SL | `[DISSENY/BLOQUEJANT]` | [uc-097.md](../06-fitxes-funcionals/uc-097.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-98 | Classificar el circuit fiscal de botiga de llibres/SL | `[PENDENT/BLOQUEJANT]` | [uc-098.md](../06-fitxes-funcionals/uc-098.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-99 | Limitar la intranet de tutors a un circuit no fiscal | `[DISSENY/PENDENT]` | [uc-099.md](../06-fitxes-funcionals/uc-099.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-100 | Registrar una operació informativa o no facturable | `[DISSENY]` | [uc-100.md](../06-fitxes-funcionals/uc-100.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-101 | Operar domini, TLS i separació d'entorns de `pay.prisma.cat` | `[PARCIAL/BLOQUEJANT]` | [uc-101.md](../06-fitxes-funcionals/uc-101.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-102 | Autoritzar l'accés de l'alumne sense rol d'intranet | `[DISSENY/BLOQUEJANT]` | [uc-102.md](../06-fitxes-funcionals/uc-102.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-103 | Delegar anul·lació o canvi de pagament web al SIF | `[DISSENY/BLOQUEJANT]` | [uc-103.md](../06-fitxes-funcionals/uc-103.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-104 | Gestionar un excés de cobrament | `[DISSENY/BLOQUEJANT]` | [uc-104.md](../06-fitxes-funcionals/uc-104.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-105 | Reassignar o repartir un pagament | `[DISSENY/BLOQUEJANT]` | [uc-105.md](../06-fitxes-funcionals/uc-105.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-106 | Crear una reserva o inscripció abans del pagament | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-106.md](../06-fitxes-funcionals/uc-106.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-107 | Detectar una inscripció duplicada i evitar efectes econòmics dobles | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-107.md](../06-fitxes-funcionals/uc-107.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-108 | Registrar un tastet o repte gratuït com a operació no facturable | `[LEGACY/DISSENY]` | [uc-108.md](../06-fitxes-funcionals/uc-108.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-109 | Registrar una inscripció a curs subvencionat sense cobrament individual | `[LEGACY/PENDENT/BLOQUEJANT]` | [uc-109.md](../06-fitxes-funcionals/uc-109.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-110 | Gestionar el descompte d'amics amb dues inscripcions i un pagador | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-110.md](../06-fitxes-funcionals/uc-110.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-111 | Validar docent novell i generar un dret de descompte futur | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-111.md](../06-fitxes-funcionals/uc-111.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-112 | Congelar preu, descompte, places i classificació fiscal abans del TPV | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-112.md](../06-fitxes-funcionals/uc-112.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-113 | Importar o crear inscripcions manualment o en lot sense inventar cobrament | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-113.md](../06-fitxes-funcionals/uc-113.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-114 | Versionar canvis de producte o edició amb operacions obertes | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-114.md](../06-fitxes-funcionals/uc-114.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-115 | Reservar i alliberar places amb aforament, caducitat i concurrència | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-115.md](../06-fitxes-funcionals/uc-115.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-116 | Custodiar i revisar evidències sensibles de descompte | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-116.md](../06-fitxes-funcionals/uc-116.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-117 | Gestionar el cicle de vida d'un codi promocional o dret futur | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-117.md](../06-fitxes-funcionals/uc-117.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-118 | Gestionar un grup abans d'emetre o cobrar | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-118.md](../06-fitxes-funcionals/uc-118.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-119 | Gestionar el cicle complet d'un regal o codi de bescanvi | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-119.md](../06-fitxes-funcionals/uc-119.md) | [Fitxa i diagrames](uc-119-cicle-complet-regal.md) |
| UC-120 | Tramitar una sol·licitud de canvi de dades personals i la seva propagació | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-120.md](../06-fitxes-funcionals/uc-120.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-121 | Repreuar o renovar una reserva caducada abans del pagament | `[DISSENY/BLOQUEJANT]` | [uc-121.md](../06-fitxes-funcionals/uc-121.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-122 | Gestionar la composició d'un pack i la indisponibilitat d'un component | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-122.md](../06-fitxes-funcionals/uc-122.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-123 | Generar i lliurar una factura electrònica en format i canal acordats | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-123.md](../06-fitxes-funcionals/uc-123.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-124 | Reconciliar accés acadèmic i certificat amb baixa, deute i pagador de grup | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-124.md](../06-fitxes-funcionals/uc-124.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-125 | Gestionar consentiment de comunicacions separat de la inscripció | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-125.md](../06-fitxes-funcionals/uc-125.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-126 | Resoldre identitat i dades de contacte en conflicte entre sistemes | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-126.md](../06-fitxes-funcionals/uc-126.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-127 | Canviar l'estat d'una edició i resoldre totes les operacions afectades | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-127.md](../06-fitxes-funcionals/uc-127.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-128 | Validar i normalitzar adreça, codi postal i població abans de congelar dades fiscals | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-128.md](../06-fitxes-funcionals/uc-128.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |
| UC-129 | Reconciliar inscripcions, usuaris, cursos i matrícules entre Prisma i Moodle | `[LEGACY/DISSENY/BLOQUEJANT]` | [uc-129.md](../06-fitxes-funcionals/uc-129.md) | **PENDENT DE REVISIÓ ESPECÍFICA** |

## Criteri de revisió

Una fitxa específica conté anàlisi de l'acció, UML de casos d'ús, classes i seqüència, i identifica què existeix al PHP, què està definit només a SQL i què és disseny pendent. **No** acredita integració amb les pantalles, autorització de servidor, execució de proves, llibre quantitatiu de fons per inscripció ni acceptació de l'AEAT. [Índex](README.md) · [Classes generals](00-model-classes-general.md) · [Traçabilitat econòmica](00-revisio-moviments-inscripcions.md).
