# Matriu reconciliada de registres, serveis i proves — R2

## Abast i lectura

Aquesta matriu reconcilia el document 38 (§4, §6 i §14), les ampliacions del model del document 34, els contractes de BD/operació/seguretat i el catàleg actual UC-01…UC-105 amb variants. Els 24 grups de sota cobreixen tots els registres enumerats al §6 del document 38 i el ledger del §14, a més de les taules base que hi interactuen.

Els serveis de disseny consten com a treball a implementar o completar, no com a implementacions certificades. Els noms finals d'algunes taules poden variar segons el document 38; el resultat auditable no pot desaparèixer.

**Cada registre té un propietari pressupostari.** Una fila amb diversos paquets reparteix responsabilitats (esquema general, domini i integració), no suma una implementació per cada paquet. Els imports són als 38 paquets de `estimacio-reconciliada-r2.json`; aquesta taula NO és una segona suma d'hores.

Els codis de prova R2-* són requisits de prova proposats, no tests executats ni noms de fitxers existents. La cobertura funcional detallada de tots els casos és `cobertura-casos-r2.md`.

| ID | Registre o taules | Propietari del treball | Servei responsable, existent o de disseny | Canal/gestió | Criteri verificable | Prova proposada | Font |
| --- | --- | --- | --- | --- | --- | --- | --- |
| RG-01 | `payment_action_event` | VT-37 | PaymentActionGateway / PaymentActionAuditService / PaymentActionEventRepository | Tots els canals actius de pagament | Intent abans d’actuar; resultat terminal atòmic o error correlacionat; sense auditoria no hi ha acció ni retorn de dades. | R2-PAY-* | 38 §14; 24 §8–9; UC-86 |
| RG-02 | `operational_event` | VT-38 | OperationalChangeController / FiscalImpactClassifier / OperationalEventRepository | Canvis, ajusts, baixes, reclamacions i gestions informatives | Motiu, actor, abans/després i decisió, també quan no hi ha moviment ni factura. | R2-UC-74 / R2-UC-100 | 38 §5–6; 31 §16.1 |
| RG-03 | `billing_profile_history` | VT-15 | BillingProfileService / BillingProfileHistoryRepository | Checkout, entitats i fitxa de client | Versionar dades mestres; factura emesa conserva snapshot; validar receptor incomplet/estranger. | R2-UC-69 / 70 / 87 | 38 §6; UC-69/70/87 |
| RG-04 | `course_change_event` | VT-23 | CourseChangeService | Canvi de curs intranet | Origen/destí, import, diferència, despeses, motiu i efectes fiscals/operatius relacionats. | R2-UC-71 | 38 §6; UC-26/71 |
| RG-05 | `enrollment_cancellation_event` | VT-24 | EnrollmentCancellationService | Baixa i decisió econòmica | Separar baixa i retorn/saldo/no retorn; preservar actor, dates i referències. | R2-UC-72 | 38 §6; UC-27/72 |
| RG-06 | `factura / factura_linia / factura_registres` | VT-05, VT-07, VT-08, VT-10 | InvoiceService / FiscalCorrectionService / FiscalRecordRepository | Emissió i correcció fiscal | Snapshot, emissor, tipus/indicadors, registre anterior, XML, cadena i estats; multiconcepte explícit. | R2-UC-01 / 74 / 75 / 76 / 88 | 34 §13.1; 38 §4–6; 17 |
| RG-07 | `aeat_submission_attempt / fiscal_queue` | VT-09 | AeatSubmissionWorker / AeatAttemptRepository | Worker AEAT i reintent autoritzat | Cada intent/resposta, request hash, lock/retry/dead-letter; no només últim error. | R2-UC-77 | 38 §6; 34 §13.1 |
| RG-08 | `document_job / factura_documents` | VT-11 | DocumentJobService / DocumentRepository | Generació PDF/QR/XML | Job, generador/versió, storage privat, hash, estat, reintents i incidència. | R2-UC-78 | 38 §6; 34 §13.1 |
| RG-09 | `notification_outbox` | VT-20 | NotificationOutboxService | Correus i notificacions | Missatge posterior al commit, plantilla/versió, destinatari, context i idempotència. | R2-UC-79 | 38 §6; document 08 |
| RG-10 | `notification_delivery_attempt` | VT-20 | NotificationOutboxService | Entrega i reintents | Cada intent/resultat persistent; evitar dues comunicacions pel mateix efecte. | R2-UC-79-DELIVERY | 38 §6 |
| RG-11 | `sif_incident_action / errors_verifactu` | VT-21 | IncidentWorkflowService | Panell i processos | Prioritat, actor, responsable, estat anterior/nou, resolució i historial. | R2-UC-81 | 38 §6; document 25 |
| RG-12 | `sif_audit_event` | VT-32 | AuditService; adaptació al context comú de VT-12/37 | Accions sensibles fora del pagament | Auditoria comuna; identitat fiable; referenciar correlació sense duplicar events de domini. | R2-AUD-COMMON | 38 §6; document 21 |
| RG-13 | `fiscal_document_access` | VT-11 | SecureDocumentService / AccessLogService | Portal, intranet, panell i auditor | Conservar consulta, descàrrega o denegació; no exposar documents aliens. | R2-UC-80 | 38 §6; UC-80/102 |
| RG-14 | `sif_version` | VT-35 | VersionGovernanceService | Preparació i activació | Versió, artefacte/configuració no secreta, estat, dates, actor i evidència associada. | R2-UC-83-VERSION | 38 §6; document 19 |
| RG-15 | `sif_declaration` | VT-35 | VersionGovernanceService | Governança de versió | Document/hash, signant/estat i versió exacta; no equival a una carpeta externa sense vinculació. | R2-UC-83-DECLARATION | 38 §6; document 19 |
| RG-16 | `fiscal_export` | VT-32 | FiscalExportService | Export fiscal i inspecció | Filtres, motiu, sol·licitant, fitxer/hash, estat i accessos; export del pagament passa també per VT-37. | R2-UC-84 | 38 §6; document 25 |
| RG-17 | `reconciliation_run/item` | VT-22 | ReconciliationService | Conciliació bancària/TPV/SIF-llegat | Execució, criteris, diferències, decisió, actor i resolució; també en gestió assistida. | R2-UC-82 | 38 §6; UC-25/53/82 |
| RG-18 | `backup_restore_evidence` | VT-34 | ContinuityEvidenceService | Còpies i restauració | Abast, hashes, dates, custòdia, objectius de recuperació, resultat i incidències; restore assajat. | R2-UC-85 | 38 §6; document 09 |
| RG-19 | `fact_rels` | VT-05, VT-18 | Repositori relacions / adaptador legacy | Canals, consulta i sincronització | Origen, event operatiu, subjecte/política de visibilitat i justificació; no derivar permisos només del legacy. | R2-REL / R2-UC-44 | 34 §13.1; document 05/17 |
| RG-20 | `payment_transaction / payment_allocation` | VT-07, VT-16, VT-37 | PaymentService / PaymentCorrectionService | Cobraments, parcials, repartiment, excés i correccions | Assignacions anteriors preservades; contramoviment/versió compensatòria; atomicitat amb event terminal. | R2-UC-104 / 105 | 38 §14; UC-02/23/104/105 |
| RG-21 | `redsys_payment_intent / redsys_notifications / redsys_callback_queue` | VT-13, VT-14, VT-37 | Serveis i worker Redsys existents a integrar | TPV, callback, retorn i worker | Order/snapshot/resultat correlacionats; duplicats/retry traçats; no ús fiscal autoritatiu d’IDPAG extern. | R2-UC-03 / 51 / 52 | documents 13/14 cua; 38 §14 |
| RG-22 | `credit_balance i moviments relacionats` | VT-24 | CreditBalanceService / ManualRefundService | Devolucions, compensació i reclamació cobrada | Titular, origen, saldo consumible, assignació, retorn real i traça sense editar el passat. | R2-UC-06 / 28 / 29 | document 05; 38 §4 |
| RG-23 | `fiscal_sequence / fiscal_chain_state` | VT-08 | FiscalSequenceRepository / registre fiscal | Emissió concurrent | Numeració/ordre únics, locks, cadena vàlida i recuperació; distingir hash intern i registre AEAT. | R2-FISC-CONCURRENCY | documents 05/17/20 |
| RG-24 | `motiu_canvi / seguiment reclamació i pròrroga` | VT-25, VT-26, VT-38 | AdjustmentService / ClaimWorkflowService | Ajusts, descomptes tardans, morositat i pròrroga | Motiu, valor anterior/nou, aprovació quan toca, termini/resultat; separació d’estat acadèmic i cobrament. | R2-UC-73 / 90 / 94 / 95 / 96 | documents 10/11; 38 §4; UC-90/94/96 |

## Regles de no duplicació

- VT-37 construeix una vegada gateway/auditoria de pagaments, connectors i monitor. Els serveis de pagament, consulta o conciliació continuen als seus paquets; no es tornen a pressupostar dins del ledger.
- VT-38 construeix una vegada controlador, classificador i repositori d'event operatiu. Els canvis de curs, baixes i ajusts aporten només les dades/regles específiques.
- VT-11 és propietari de l'accés persistent a documents. VT-19 integra la pantalla i VT-32 verifica la política; no creen tres AccessLogService diferents.
- VT-22 és propietari de runs/items de conciliació. VT-18 sincronitza; no construeix un segon comparador.
- VT-35 manté versió/declaració en runtime. VT-34 usa aquests identificadors en deploy/restore, sense duplicar el registre.
- VT-09 inclou cada intent AEAT; el pressupost mantingut de 56 h per la proposta limitada cobreix transport, cua, intents, resposta i proves locals. No es cobra un segon worker per afegir el nom de la taula.
- Les 28 h traspassades de VT-10/12/32/33 es resten dels paquets antics abans d'afegir VT-37/38; la traça d'aquest càlcul és a `estimacio-reconciliada-r2.json`.

## Condicions comunes d'acceptació

1. Actor i rol resolts al servidor; identitat de procés i entorn.
2. Petició i causa correlacionades fins al resultat.
3. Estat/versió revalidats abans de confirmar; cap update alternatiu al llegat si el SIF rebutja.
4. Mutació i event terminal confirmats de manera atòmica quan toca.
5. Rebuigs/errors persistits sense perdre l'intent en un rollback; correlacions incompletes detectades.
6. Consultes/exportacions de pagament sense retorn de dades si no es pot guardar l'auditoria.
7. Correccions amb nous registres; secrets exclosos de la traça.
8. Prova amb dades representatives i evidència vinculada al canal, versió i registre.

## Detalls que no es poden simplificar per arribar a la data

Un procés assistit conserva permisos, motiu, abans/després, classificació i registres. Assistit vol dir interfície o automatització més senzilla, no editar taules manualment. Un pagament sense factura nova continua tenint event i moviment/assignació quan pertoqui. Una gestió sense efecte econòmic continua tenint event operatiu.

## Estat del contrast

La correspondència de planificació està feta; els criteris estan assignats a pressupost i prova. Les proves productives, migracions i integracions continuen pendents. No es marca implementat cap registre només per aparèixer en un diagrama ni es declara conformitat fiscal executada amb aquesta matriu.
