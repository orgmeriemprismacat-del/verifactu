# UC-111 · Fitxes d'acció auditables

**Objectiu:** descompondre UC-111 en accions verificables. La fitxa principal continua sent [uc-111.md](uc-111.md); aquest document evita que una única fitxa amagui subfluxos amb estat tècnic diferent.

**Criteri d'estat:** `DOCUMENTAT` = regla o flux escrit; `IMPLEMENTAT_BRANCA` = codi present a la branca; `VERIFICAT_CODI` = contrast estàtic amb el codi; `PROVAT` = prova executada amb resultat real. Les proves MySQL continuen ajornades, per tant cap acció que depengui de BD queda marcada com a PROVADA.

## A111-01 · Alta JASOM i obertura de sol·licitud de docent novell

| Camp | Valor |
| --- | --- |
| Actor | Alumne / web |
| Disparador | Inscripció JASOM amb opció de docent novell |
| ACTUAL observable | `ajax/enviarInscripcio.php` crea la inscripció i, en la branca JASOM+novell, `recent_titulat(ID_INSC)` |
| FINAL/branca | `NovicePromotionEnrollmentStager::stage` crea/consolida l'operació SIF i l'expedient pendent |
| Postcondició correcta | Sol·licitud pendent; **cap dret promocional** per la simple alta |
| Estat | DOCUMENTAT · IMPLEMENTAT_BRANCA · VERIFICAT_CODI · NO PROVAT MySQL |
| Riscos pendents | autenticació/adaptador web, deduplicació amb totes les rutes legacy, custòdia de l'evidència |

## A111-02 · Pujar i custodiar evidència de titulació

| Camp | Valor |
| --- | --- |
| Actor | Alumne |
| ACTUAL observable | `ajax/enviarImatgeSocRecentTitulat.php` mou un fitxer a una ruta legacy |
| FINAL requerit | storage privat, titularitat, MIME/mida, nom opac, hash, retenció i lectura autoritzada |
| Postcondició | Evidència vinculada a la sol·licitud; encara sense validació ni dret |
| Estat | DOCUMENTAT · LEGACY OBSERVAT · FINAL PENDENT |
| Bloqueig | No reutilitzar la ruta pública/nom de fitxer com a prova d'autorització |

## A111-03 · Decisió de secretaria sobre docent novell

| Camp | Valor |
| --- | --- |
| Actor | Secretaria autoritzada |
| ACTUAL observable | pantalla `alumnes-validar-descomptes.php`, JS i endpoint legacy; `recent_titulat.VALIDAT`: 0 pendent, 1 aprovat, 2 denegat |
| FINAL/branca | `NovicePromotionSecretaryDecisionProjector::projectDecision` projecta només una decisió llegida del llegat |
| Postcondició aprovada | validació SIF `VALIDATED` / operació preparada per al pagament |
| Postcondició denegada | `REJECTED`, sense concessió promocional |
| Estat | DOCUMENTAT · IMPLEMENTAT_BRANCA · VERIFICAT_CODI · NO PROVAT MySQL |
| Pendent | endpoint POST/CSRF/rol real i font canònica de decisió |

## A111-04 · Conciliar JASOM íntegrament pagat i concedir el dret únic

| Camp | Valor |
| --- | --- |
| Actors | SIF / worker de reconciliació |
| FINAL/branca | `NovicePromotionInvoiceLinkService`, `NovicePromotionGrantService::issueForOperation`, `NovicePromotionGrantReconciler` |
| Regla | dret per PERSONA una sola vegada; import = cobrament real elegible JASOM; totes les F1/F2 emeses i pagades |
| Control | CHARGE confirmats − REFUND = totals de factura = net de l'operació |
| Postcondició | dret comercial registrat, encara no necessàriament lliurat |
| Estat | DOCUMENTAT · IMPLEMENTAT_BRANCA · VERIFICAT_CODI · integracions MySQL NO PROVADES |

## A111-05 · Preparar, verificar destinatari i lliurar el codi

| Camp | Valor |
| --- | --- |
| Components | `NovicePromotionCodePreparationService`, `NovicePromotionEmailVerificationService`, `NovicePromotionDeliveryAttemptService`, `NovicePromotionPrivateMailWorker` |
| Regla | el token no es desa en clar; hash al dret i token xifrat a outbox; reintents amb claim recuperable |
| Seguretat | destinatari verificat de forma separada; el worker privat és l'únic que necessita desxifrar per enviar |
| Postcondició | dret `ACTIVE` i entrega traçada quan el transport real accepta el missatge |
| Estat | IMPLEMENTAT_BRANCA · transport/connector real PENDENT · NO PROVAT MySQL |

## A111-06 · Reservar, aplicar o alliberar el saldo novell original

| Camp | Valor |
| --- | --- |
| Component | `NovicePromotionRedemptionService` |
| Operacions | `reserve`, `confirmApplied`, `release` |
| Regla | altres descomptes abans; consum parcial; reserva resta saldo una vegada; confirmació no el torna a restar |
| Fiscalitat | factura final i residual real conciliats; no crear CHARGE fictici si residual = 0 |
| Postcondició | `novice_promotion_application.RESERVED/APPLIED/RELEASED` |
| Estat | IMPLEMENTAT_BRANCA · checkout/pricing/facturació real PENDENTS · NO PROVAT MySQL |

## A111-07 · Canviar el curs de destinació

| Camp | Valor |
| --- | --- |
| Components primer canvi | `NovicePromotionCourseTransferReviewService`, `NovicePromotionFirstTransferConfirmationService` |
| Components successius | `NovicePromotionSuccessiveTransferReviewService`, `NovicePromotionSuccessiveTransferConfirmationService` |
| Regla | traspassar la **mateixa atribució promocional**, no concedir saldo nou ni consumir dues vegades |
| Evidència | rectificativa del curs anterior, nou preu/factura i decisió autoritzada |
| Estat | IMPLEMENTAT_BRANCA per primer i successius traspassos · connectors reals PENDENTS · NO PROVAT MySQL |

## A111-08 · Baixa d'un curs i concessió d'un saldo derivat

| Camp | Valor |
| --- | --- |
| Curs original | `NovicePromotionDestinationCancellationReviewService` + `NovicePromotionDerivedBalanceActivationService` |
| Curs traspassat | `NovicePromotionTransferredDestinationCancellationReviewService` + `NovicePromotionTransferredCancellationActivationService` — cobreix el curs actual assolit pel **primer traspàs confirmat** de l'aplicació original |
| Curs pagat amb saldo derivat | `NovicePromotionDerivedApplicationCancellationReviewService` + `NovicePromotionDerivedApplicationCancellationActivationService` — crea un dret fill amb `PARENT_UUID_DERIVED_BALANCE` i `SOURCE_UUID_DERIVED_APPLICATION`, sense recreditar el pare |
| Polítiques d'aprovació | `NovicePromotionApprovedCancellationPolicy`, `NovicePromotionApprovedTransferredCancellationPolicy`, `NovicePromotionApprovedDerivedCancellationPolicy` |
| Regla | separar component promocional de diners reals; saldo derivat amb **nou any propi**; predecessor històric no torna a ser exposició activa; no restaurar saldo JASOM ni el saldo pare ja consumit |
| Evidència | rectificativa real + aprovació independent + revalidació del cash i del JASOM abans d'activar |
| Encara no executable | baixa del curs actual després d'un **segon/tercer traspàs**: els traspasos successius ja existeixen, però falta un review+activation de baixa que segueixi l'últim `PREVIOUS_UUID_TRANSFER` |
| Estat | IMPLEMENTAT_BRANCA per baixa original, baixa del primer curs traspassat i baixa directa d'una `derived_application.APPLIED` · baixa de traspàs successiu PENDENT · adaptador real d'aprovació PENDENT · NO PROVAT MySQL |

## A111-09 · Consum parcial del saldo derivat

| Camp | Valor |
| --- | --- |
| Component | `NovicePromotionDerivedBalanceRedemptionService` |
| Política | `NovicePromotionDerivedBalanceEligibilityPolicy` |
| Regla | selecció interna per titular+UUID, sense segon codi NOV; N consums parcials; reserva/confirmació/release idempotents |
| Postcondició | `novice_promotion_derived_application.RESERVED/APPLIED/RELEASED` |
| Estat | IMPLEMENTAT_BRANCA · 5 tests purs escrits · MySQL/checkout real PENDENTS |

## A111-10 · Projectar la cadena de procedència

| Camp | Valor |
| --- | --- |
| Components | `NovicePromotionLineageSnapshotService`, `NovicePromotionLineageProjectionPolicy`, `NovicePromotionLineagePolicy` |
| Regla | predecessors substituïts no són exposició activa; transferències no són consums nous; drets derivats conserven el pare |
| Resultat | snapshot/graf coherent per decidir què queda disponible i què està actualment aplicat |
| Estat | IMPLEMENTAT_BRANCA · tests purs escrits · adaptació a totes les dades reals PENDENT |

## A111-11 · Obrir revisió per devolució del JASOM i congelar promoció

| Camp | Valor |
| --- | --- |
| Components | `NovicePromotionRootRefundPlanService`, `NovicePromotionRootRefundReviewService` |
| Regla | abans d'efectes comercials, projectar tota la cadena; congelar noves reserves; no confondre proposta amb refund bancari |
| Estats | `commercial_entitlement.ACTIVE → REFUND_REVIEW`; review `PENDING_APPROVAL` (model també admet `APPROVED_WAITING_REFUND` després de 000027), amb retorn a ACTIVE si es rebutja/cancel·la o continuació a EXECUTED quan hi ha aprovació + refund d'origen confirmat |
| Estat | IMPLEMENTAT_BRANCA · connector/autenticació real i proves MySQL PENDENTS |

## A111-12 · Executar conseqüències del refund JASOM i resoldre recuperacions

| Camp | Valor |
| --- | --- |
| Components | `NovicePromotionRootRefundExecutionService`, `NovicePromotionRootRefundRecoveryResolutionService`, `NovicePromotionRootRefundRecoveryCompletionService` |
| Polítiques | `NovicePromotionApprovedRootRefundPolicy`, `NovicePromotionOriginRefundEvidencePolicy`, `NovicePromotionRecoveryResolutionPolicy`, `NovicePromotionRecoveryCompletionPolicy` |
| Regla | només després d'evidència del refund d'origen: cancel·lar romanents vius i crear recovery items pels imports promocionals actualment gastats; tancar cada recovery amb evidència, sense inventar cobrament |
| Prohibició | no reclamar de nou consums històrics ja substituïts per transferència o saldo derivat |
| Postcondicions | review `EXECUTED` després de cancel·lar romanents/crear recoveries; `RECOVERY_RESOLVED` només quan tots els items tenen `RECOVERED/WAIVED/CANCELLED` amb evidència |
| Estat | IMPLEMENTAT_BRANCA · integració real de refund/recovery i MySQL PENDENTS |

## Cobertura UML 1:1 per acció

Cada A111-01…A111-12 disposa ara de **quatre diagrames propis** —cas d'ús, classes/components, seqüència i activitat— al document [UC-111 · diagrames 1:1 per acció](../07-uml-integrat/uc-111-diagrames-per-accio.md). Les vistes globals ACTUAL/FINAL continuen sent útils per entendre relacions entre accions, però aquest document 1:1 és la prova de cobertura de la porta «una acció → quatre diagrames». La vista transversal de persistència i lifecycle és [UC-111 · dades i estats ACTUAL/FINAL](../07-uml-integrat/uc-111-dades-estats-actual-final.md); **no crea cap acció nova**, sinó que tanca la cobertura de dades/estats dels mateixos A111-01…12.

## Matriu de cobertura documental de les accions

| Acció | Casos d'ús | Classes | Seqüència | Activitat | Dades/estats | Traçabilitat |
| --- | --- | --- | --- | --- | --- | --- |
| A111-01…03 | [casos d'ús](../07-uml-integrat/uc-111-casos-us-actual-final.md) | [classes](../07-uml-integrat/uc-111-classes-actual-final.md) | [seqüències](../07-uml-integrat/uc-111-sequencies-actual-final.md) | [activitats](../07-uml-integrat/uc-111-activitats-actual-final.md) | [dades/estats](../07-uml-integrat/uc-111-dades-estats-actual-final.md) | [matriu](../07-uml-integrat/uc-111-tracabilitat-implementacio.md) |
| A111-04…06 | idem | idem | idem | idem | idem | idem |
| A111-07…09 | idem | idem | idem | idem | idem | idem |
| A111-10…12 | idem | idem | idem | idem | idem | idem |

**Nota de control:** aquesta descomposició no substitueix la fitxa principal. Serveix perquè cada acció tingui actor, disparador, postcondició, codi i estat propis, seguint el criteri de revisió utilitzat a UC-04.
