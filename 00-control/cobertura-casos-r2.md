# Cobertura dels casos i accions - R2

> **Abast històric d'aquesta matriu R2.** Aquesta versió només inventaria UC-01 a UC-105 i les 13 variants amb sufix: **118 files**, i no s'ha de fer servir per concloure que falten UC-106 a UC-129 o per certificar cobertura actual. El catàleg i la matriu de fitxes UML actuals contenen **142 casos**. Vegeu [el catàleg canònic](../documentacio/04-estat-final/33-casos-us-sif.md) i [la matriu UML de cobertura i auditoria d'abast](../documentacio/07-uml-integrat/00-matriu-cobertura-cataleg.md). Els paquets R2 i les proves proposades d'aquest arxiu conserven només el seu abast original; no s'han reassignat de manera fictícia als 24 casos posteriors.

Font: document 33 vigent al tall. Cada cas/variant te paquet responsable i ID de prova proposat. La referencia documental conserva el criteri complet. Els IDs R2-* son proves per preparar, NO proves executades ni noms de tests existents. Un cas mapat no significa implementat. VT-37 es transversal a qualsevol accio sobre pagament, VT-38 a les gestions operatives, i VT-32/33 als permisos i acceptacio integrada.

| Cas | Funcio | Paquets de treball | Prova proposada |
| --- | --- | --- | --- |
| UC-01 | Emetre o reutilitzar factura | VT-07, VT-08 | R2-UC-01 |
| UC-02 | Registrar pagament sobre factura | VT-07, VT-16 | R2-UC-02 |
| UC-03 | Processar cobrament Redsys asíncron | VT-13, VT-14 | R2-UC-03 |
| UC-04 | Emetre factura abans de cobrar | VT-17 | R2-UC-04 |
| UC-05 | Crear rectificativa | VT-10 | R2-UC-05 |
| UC-06 | Registrar devolució, saldo o compensació | VT-24 | R2-UC-06 |
| UC-07 | Consultar factura, estat i document | VT-11, VT-19, VT-21 | R2-UC-07 |
| UC-08 | Gestionar incidència | VT-21 | R2-UC-08 |
| UC-09 | Remetre registre a AEAT | VT-09 | R2-UC-09 |
| UC-10 | Gestionar configuració i versió | VT-03, VT-35 | R2-UC-10 |
| UC-11 | Importar factura històrica | VT-06 | R2-UC-11 |
| UC-12 | Gestionar el cicle de morositat i reclamació | VT-25 | R2-UC-12 |
| UC-13 | Orquestrar la doble facturació USOC | VT-30 | R2-UC-13 |
| UC-14 | Comprar curs normal per Redsys | VT-15, VT-26 | R2-UC-14 |
| UC-14a | Comprar taller | VT-27 | R2-UC-14a |
| UC-14b | Comprar jornada | VT-27 | R2-UC-14b |
| UC-15 | Comprar pack | VT-27 | R2-UC-15 |
| UC-16 | Facturar grup | VT-28 | R2-UC-16 |
| UC-16a | Afegir participant després d'emetre | VT-28 | R2-UC-16a |
| UC-16b | Treure participant després d'emetre | VT-28 | R2-UC-16b |
| UC-17 | Comprar regal | VT-29 | R2-UC-17 |
| UC-18 | Bescanviar regal | VT-29 | R2-UC-18 |
| UC-18a | Gestionar regal caducat o duplicat | VT-29 | R2-UC-18a |
| UC-19 | Validar afiliació USOC | VT-30 | R2-UC-19 |
| UC-19a | Facturar part de l'alumne USOC | VT-30 | R2-UC-19a |
| UC-19b | Facturar diferència a USOC | VT-30 | R2-UC-19b |
| UC-20 | Aplicar Alumne PrisMa | VT-26 | R2-UC-20 |
| UC-20a | Validar Carnet Jove | VT-26 | R2-UC-20a |
| UC-20b | Aplicar descompte sensible | VT-26 | R2-UC-20b |
| UC-20c | Aplicar promoció temporal | VT-26 | R2-UC-20c |
| UC-20d | Aplicar codi promocional | VT-26 | R2-UC-20d |
| UC-21 | Empresa/responsable paga inscripcions | VT-17, VT-28 | R2-UC-21 |
| UC-22 | Registrar transferència | VT-16 | R2-UC-22 |
| UC-23 | Registrar fracció | VT-16, VT-25 | R2-UC-23 |
| UC-24 | Registrar cobrament de reclamació | VT-25 | R2-UC-24 |
| UC-25 | Analitzar fitxer TPV | VT-22 | R2-UC-25 |
| UC-25a | Comprovar IDPAG duplicats | VT-22 | R2-UC-25a |
| UC-26 | Canviar de curs | VT-23, VT-38 | R2-UC-26 |
| UC-27 | Donar de baixa | VT-24, VT-38 | R2-UC-27 |
| UC-28 | Registrar devolució | VT-24 | R2-UC-28 |
| UC-29 | Crear saldo | VT-24 | R2-UC-29 |
| UC-29a | Aplicar compensació | VT-24 | R2-UC-29a |
| UC-30 | Anul·lar registre improcedent | VT-10 | R2-UC-30 |
| UC-31 | Subsanar registre | VT-10 | R2-UC-31 |
| UC-32 | Marcar o desmarcar factura electrònica | VT-31, VT-32 | R2-UC-32 |
| UC-33 | Desactivar URL de pagament | VT-14, VT-17 | R2-UC-33 |
| UC-34 | Consultar dashboard | VT-21 | R2-UC-34 |
| UC-35 | Consultar registre, cadena i estat AEAT | VT-08, VT-09, VT-21 | R2-UC-35 |
| UC-36 | Generar/consultar PDF, QR o XML | VT-11 | R2-UC-36 |
| UC-37 | Exportar període fiscal | VT-32 | R2-UC-37 |
| UC-38 | Configurar SIF i certificat | VT-03, VT-32 | R2-UC-38 |
| UC-39 | Executar proves i go/no-go | VT-33 | R2-UC-39 |
| UC-40 | Fer backup i restauració | VT-34 | R2-UC-40 |
| UC-41 | Crear o editar entitat/responsable | VT-15, VT-17 | R2-UC-41 |
| UC-42 | Consultar/modificar alumne | VT-19, VT-31 | R2-UC-42 |
| UC-43 | Gestionar notificacions i recordatoris | VT-20, VT-25 | R2-UC-43 |
| UC-44 | Consultar i mantenir `fact_rels` i origen legacy | VT-05, VT-18 | R2-UC-44 |
| UC-45 | Activar auditor temporal | VT-32 | R2-UC-45 |
| UC-46 | Activar versió i declaració responsable | VT-35 | R2-UC-46 |
| UC-47 | Sincronitzar l'estat mínim cap al llegat després del commit SIF | VT-18 | R2-UC-47 |
| UC-48 | Crear o consultar una proforma no fiscal | VT-31 | R2-UC-48 |
| UC-49 | Enviar factura, document o avis per correu | VT-20 | R2-UC-49 |
| UC-50 | Crear, consultar, desactivar o caducar un enllaç de pagament | VT-14, VT-19 | R2-UC-50 |
| UC-51 | Tractar callback Redsys denegat, tardà, duplicat o contradictori | VT-13, VT-14 | R2-UC-51 |
| UC-52 | Operar la cua Redsys | VT-13, VT-21 | R2-UC-52 |
| UC-53 | Detectar i resoldre divergències SIF-llegat | VT-18, VT-22 | R2-UC-53 |
| UC-54 | Operar la cua fiscal i tractar la resposta AEAT | VT-09 | R2-UC-54 |
| UC-55 | Generar, reintentar i custodiar documents fiscals | VT-11 | R2-UC-55 |
| UC-56 | Cercar i assignar un cobrament | VT-16 | R2-UC-56 |
| UC-57 | Mantenir i optimitzar la BD SIF | VT-05 | R2-UC-57 |
| UC-58 | Gestionar l'outbox de notificacions | VT-20 | R2-UC-58 |
| UC-59 | Concedir, caducar i revocar accés auditor | VT-32 | R2-UC-59 |
| UC-60 | Monitorar salut, cues, documents, backups i versió activa | VT-21, VT-34 | R2-UC-60 |
| UC-61 | Consultar un import pendent i obtenir un enllaç de pagament | VT-19 | R2-UC-61 |
| UC-62 | Iniciar factura o cobrament des de la intranet | VT-12, VT-16, VT-17 | R2-UC-62 |
| UC-63 | Crear la intenció Redsys des de l'ecommerce | VT-13, VT-15 | R2-UC-63 |
| UC-64 | Reconciliar la candidata amb el codi actual | VT-01, VT-04 | R2-UC-64 |
| UC-65 | Presentar una factura o rebut de col·laborador | VT-31, VT-36 | R2-UC-65 |
| UC-66 | Consultar i gestionar cobraments d'un col·laborador | VT-31, VT-36 | R2-UC-66 |
| UC-67 | Externalitzar i rotar secrets de pagament | VT-03, VT-32 | R2-UC-67 |
| UC-68 | Retirar callbacks i escriptures fiscals llegades | VT-18 | R2-UC-68 |
| UC-69 | Confirmar i congelar dades fiscals | VT-15 | R2-UC-69 |
| UC-70 | Modificar dades mestres després d'emetre | VT-15 | R2-UC-70 |
| UC-71 | Registrar un canvi de curs complet | VT-23, VT-38 | R2-UC-71 |
| UC-72 | Registrar baixa i decisió econòmica | VT-24, VT-38 | R2-UC-72 |
| UC-73 | Documentar un ajust, descompte o despesa | VT-26, VT-38 | R2-UC-73 |
| UC-74 | Classificar una correcció fiscal | VT-10, VT-38 | R2-UC-74 |
| UC-75 | Crear un registre d'anul·lació | VT-10 | R2-UC-75 |
| UC-76 | Crear un registre de subsanació | VT-10 | R2-UC-76 |
| UC-77 | Operar enviament AEAT, retry i dead-letter | VT-09 | R2-UC-77 |
| UC-78 | Generar i custodiar PDF, QR i XML | VT-11 | R2-UC-78 |
| UC-79 | Enviar una comunicació fiscal auditable | VT-20 | R2-UC-79 |
| UC-80 | Servir i registrar accés a document fiscal | VT-11, VT-19, VT-32 | R2-UC-80 |
| UC-81 | Gestionar el cicle complet d'una incidència | VT-21 | R2-UC-81 |
| UC-82 | Reconciliar SIF amb la BD llegada | VT-18, VT-22 | R2-UC-82 |
| UC-83 | Registrar i activar versió i declaració | VT-35 | R2-UC-83 |
| UC-84 | Crear un paquet fiscal d'auditoria | VT-32 | R2-UC-84 |
| UC-85 | Executar backup, restauració i reconciliació | VT-34 | R2-UC-85 |
| UC-87 | Validar receptor estranger o amb dades fiscals incompletes | VT-15 | R2-UC-87 |
| UC-88 | Decidir agrupació i línies d'una factura multiconcepte | VT-07, VT-17 | R2-UC-88 |
| UC-89 | Canviar concepte després del cobrament o emissió | VT-10, VT-38 | R2-UC-89 |
| UC-90 | Resoldre un descompte validat després de la compra | VT-26, VT-38 | R2-UC-90 |
| UC-91 | Aplicar descompte de grup per trams | VT-28 | R2-UC-91 |
| UC-92 | Registrar venda manual des d'intranet o telèfon | VT-16, VT-17 | R2-UC-92 |
| UC-93 | Canviar el receptor fiscal sol·licitat després d'una compra particular | VT-10, VT-15, VT-38 | R2-UC-93 |
| UC-94 | Ajustar manualment l'import a pagar amb justificació | VT-26, VT-38 | R2-UC-94 |
| UC-95 | Gestionar estat acadèmic amb deute pendent | VT-25, VT-31 | R2-UC-95 |
| UC-96 | Concedir una pròrroga de pagament fins a la segona setmana | VT-25, VT-38 | R2-UC-96 |
| UC-97 | Consultar històric barrejat Associació/SL | VT-06 | R2-UC-97 |
| UC-98 | Classificar el circuit fiscal de botiga de llibres/SL | VT-36 | R2-UC-98 |
| UC-99 | Limitar la intranet de tutors a un circuit no fiscal | VT-31, VT-32, VT-36 | R2-UC-99 |
| UC-100 | Registrar una operació informativa o no facturable | VT-38 | R2-UC-100 |
| UC-101 | Operar domini, TLS i separació d'entorns de `pay.prisma.cat` | VT-03 | R2-UC-101 |
| UC-102 | Autoritzar l'accés de l'alumne sense rol d'intranet | VT-19, VT-32 | R2-UC-102 |
| UC-103 | Delegar anul·lació o canvi de pagament web al SIF | VT-14, VT-37 | R2-UC-103 |
| UC-104 | Gestionar un excés de cobrament | VT-07, VT-16, VT-24, VT-37 | R2-UC-104 |
| UC-105 | Reassignar o repartir un pagament | VT-07, VT-16, VT-37 | R2-UC-105 |
| UC-86 | Registrar qualsevol accio sobre un pagament | VT-37 | R2-UC-86 |

UC-65/66 son circuits adjacents: VT-31 verifica regressio i VT-36 en delimita ampliacions; no s'ha pressupostat construir un SIF nou de proveidors. Les variants comercials continuen incloses a l'abast ampli. La proposta limitada requereix decisio explicita, no s'ha aplicat cap exclusio.

## Accions obligatories del ledger

Font: document 24, seccio 8. Totes les accions tenen responsable VT-37; les proves es concreten per resultat aplicable i canal actiu, no per cada SELECT intern. El servei de domini conserva la seva tasca de negoci, sense duplicar el gateway.

- [ ] R2-PAY-CREATE_REQUEST - VT-37: intent, resultat i correlacio; validar bloqueig si falla la persistencia quan correspongui.
- [ ] R2-PAY-CREATE - VT-37: intent, resultat i correlacio; validar bloqueig si falla la persistencia quan correspongui.
- [ ] R2-PAY-IDEMPOTENCY_REUSE - VT-37: intent, resultat i correlacio; validar bloqueig si falla la persistencia quan correspongui.
- [ ] R2-PAY-DUPLICATE_DETECTED - VT-37: intent, resultat i correlacio; validar bloqueig si falla la persistencia quan correspongui.
- [ ] R2-PAY-SEARCH - VT-37: intent, resultat i correlacio; validar bloqueig si falla la persistencia quan correspongui.
- [ ] R2-PAY-VIEW - VT-37: intent, resultat i correlacio; validar bloqueig si falla la persistencia quan correspongui.
- [ ] R2-PAY-VIEW_ALLOCATIONS - VT-37: intent, resultat i correlacio; validar bloqueig si falla la persistencia quan correspongui.
- [ ] R2-PAY-EXPORT - VT-37: intent, resultat i correlacio; validar bloqueig si falla la persistencia quan correspongui.
- [ ] R2-PAY-ALLOCATE - VT-37: intent, resultat i correlacio; validar bloqueig si falla la persistencia quan correspongui.
- [ ] R2-PAY-REALLOCATE - VT-37: intent, resultat i correlacio; validar bloqueig si falla la persistencia quan correspongui.
- [ ] R2-PAY-UNALLOCATE - VT-37: intent, resultat i correlacio; validar bloqueig si falla la persistencia quan correspongui.
- [ ] R2-PAY-SPLIT_ALLOCATION - VT-37: intent, resultat i correlacio; validar bloqueig si falla la persistencia quan correspongui.
- [ ] R2-PAY-RECONCILE - VT-37: intent, resultat i correlacio; validar bloqueig si falla la persistencia quan correspongui.
- [ ] R2-PAY-MARK_PENDING_REVIEW - VT-37: intent, resultat i correlacio; validar bloqueig si falla la persistencia quan correspongui.
- [ ] R2-PAY-RESOLVE_RECONCILIATION - VT-37: intent, resultat i correlacio; validar bloqueig si falla la persistencia quan correspongui.
- [ ] R2-PAY-LINK_REFUND - VT-37: intent, resultat i correlacio; validar bloqueig si falla la persistencia quan correspongui.
- [ ] R2-PAY-LINK_COMPENSATION - VT-37: intent, resultat i correlacio; validar bloqueig si falla la persistencia quan correspongui.
- [ ] R2-PAY-LINK_CLAIM_PAYMENT - VT-37: intent, resultat i correlacio; validar bloqueig si falla la persistencia quan correspongui.
- [ ] R2-PAY-CANCEL_OPERATION - VT-37: intent, resultat i correlacio; validar bloqueig si falla la persistencia quan correspongui.
- [ ] R2-PAY-MARK_ERROR - VT-37: intent, resultat i correlacio; validar bloqueig si falla la persistencia quan correspongui.
- [ ] R2-PAY-RETRY - VT-37: intent, resultat i correlacio; validar bloqueig si falla la persistencia quan correspongui.
- [ ] R2-PAY-RECOVER_LOCK - VT-37: intent, resultat i correlacio; validar bloqueig si falla la persistencia quan correspongui.
- [ ] R2-PAY-REDSYS_CALLBACK - VT-37: intent, resultat i correlacio; validar bloqueig si falla la persistencia quan correspongui.
- [ ] R2-PAY-REDSYS_WORKER_RESULT - VT-37: intent, resultat i correlacio; validar bloqueig si falla la persistencia quan correspongui.
- [ ] R2-PAY-SYNC_LEGACY - VT-37: intent, resultat i correlacio; validar bloqueig si falla la persistencia quan correspongui.
- [ ] R2-PAY-IMPORT - VT-37: intent, resultat i correlacio; validar bloqueig si falla la persistencia quan correspongui.
- [ ] R2-PAY-ACCESS_DENIED - VT-37: intent, resultat i correlacio; validar bloqueig si falla la persistencia quan correspongui.
- [ ] R2-PAY-VALIDATION_REJECTED - VT-37: intent, resultat i correlacio; validar bloqueig si falla la persistencia quan correspongui.
- [ ] R2-PAY-IMMUTABILITY_BLOCKED - VT-37: intent, resultat i correlacio; validar bloqueig si falla la persistencia quan correspongui.

## Comprovacions de la planificacio

- Casos/variants mapats: 118.
- Accions ACTION extretes del diccionari: 29.
- Paquets existents: 38. Cap referencia a paquet desconegut.
- Sumes verificades: {"low":826.0,"probable":1332.0,"high":2256.0,"limited":944.0}.
- Fonts amb hash de tall: 20.
- La matriu detallada de taules/serveis/canals/criteris es troba a matriu-registres-r2.md. Aquest control verifica correspondencies i aritmetica, no executa PHP ni certifica produccio.
