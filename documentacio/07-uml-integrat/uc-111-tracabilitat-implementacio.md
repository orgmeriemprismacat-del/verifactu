# UC-111 · Matriu de traçabilitat funcional, codi, UML i proves

**Data de tall documental:** 29/09/2026.  
**Branca contrastada:** `feat/uc-111-termini-i-auditoria-2026-09-22`.  
**Regla:** "fitxer present" no equival a "provat" ni a "desplegat".

## 0. Inventari de fitxes i diagrames UC-111

| Artefacte requerit | Fitxer | Estat 29/09/2026 |
| --- | --- | --- |
| fitxa funcional canònica | [uc-111.md](../06-fitxes-funcionals/uc-111.md) | EXISTIA · actualitzada |
| fitxes per acció A111-01…12 | [uc-111-accions.md](../06-fitxes-funcionals/uc-111-accions.md) | CREADA |
| casos d'ús ACTUAL/FINAL | [uc-111-casos-us-actual-final.md](uc-111-casos-us-actual-final.md) | CREAT |
| classes ACTUAL/FINAL | [uc-111-classes-actual-final.md](uc-111-classes-actual-final.md) | CREAT |
| seqüències ACTUAL/FINAL | [uc-111-sequencies-actual-final.md](uc-111-sequencies-actual-final.md) | CREAT |
| activitats ACTUAL/FINAL | [uc-111-activitats-actual-final.md](uc-111-activitats-actual-final.md) | CREAT |
| 4 UML per cada acció | [uc-111-diagrames-per-accio.md](uc-111-diagrames-per-accio.md) | CREAT · 12 × 4 = 48 blocs |
| traçabilitat funcional/codi/proves | aquest document | CREAT |
| UML integrat cronològic | [uc-111-docent-novell-dret-futur.md](uc-111-docent-novell-dret-futur.md) | EXISTIA · actualitzat/enllaçat |
| auditoria inicial 22/09 | [lot 02](00-auditoria-casos-pendents-lot-02-uc-111-2026-09-22.md) | EXISTIA · marcada HISTÒRICA |
| auditoria tècnica evolutiva | [circuit cobrament/promoció](00-auditoria-circuit-cobrament-promocio-novell-2026-09-22.md) | EXISTIA · activa |
| classes generals SIF | [31](../04-estat-final/31-diagrames-classes-sif.md) | ACTUALITZAT amb UC-111 |
| seqüències generals SIF | [32](../04-estat-final/32-diagrames-sequencia-sif.md) | ACTUALITZAT amb UC-111 |
| matriu general de diagrames | [35](../04-estat-final/35-matriu-tracabilitat-diagrames.md) | ACTUALITZADA amb paquet UC-111 |

**Conclusió de l'inventari:** dins del paquet documental definit per aquesta auditoria **ja no falta cap tipus de peça** (fitxa, cas d'ús, classes, seqüència, activitat o traçabilitat). El que continua pendent és **validació del contingut contra runtime i proves**, no la mera existència documental. Els fitxers suplementaris no creen nous IDs: el catàleg continua en 142 UC/variants canònics.

## 1. Cobertura per acció

| Acció | Fitxa | Codi ACTUAL / font | Codi FINAL/branca | UML | Proves | Estat |
| --- | --- | --- | --- | --- | --- | --- |
| A111-01 Alta JASOM | [fitxa](../06-fitxes-funcionals/uc-111-accions.md#a111-01--alta-jasom-i-obertura-de-sollicitud-de-docent-novell) | `web-actual/ajax/enviarInscripcio.php` | `NovicePromotionEnrollmentStager` | casos §1/2, seq §1/2, act §1/2 | Integration stager escrita | IMPLEMENTAT_BRANCA / MySQL pendent |
| A111-02 Evidència | fitxes d'acció | `enviarImatgeSocRecentTitulat.php` | storage segur no implementat | act §3 | no | PENDENT/BLOQUEJANT |
| A111-03 Decisió | fitxes d'acció | pantalla/JS/endpoint intranet + `VALIDAT` | `NovicePromotionSecretaryDecisionProjector` | seq §1/2, act §4/5 | integration escrita | IMPLEMENTAT_BRANCA / endpoint segur pendent |
| A111-04 Concessió | fitxes d'acció | generació legacy no acreditada completament | `RedsysCourseInvoiceService` → `NovicePromotionInvoiceLinkService` → `NovicePromotionGrantService` després del commit econòmic; `GrantReconciler` per conciliació | classes §2, seq §2, act §7 | integration escrita | IMPLEMENTAT_BRANCA / post-pagament cablejat / MySQL-runtime pendent |
| A111-05 Codi/correu | fitxes d'acció | correu legacy corregit per no exposar codi literal; emissió legacy no canònica | `RedsysCourseInvoiceService` → `NovicePromotionCodePreparationService` després del grant; email verification + delivery + private worker | classes §2, seq §2/3, act §7 | unit/integration parcials | IMPLEMENTAT_BRANCA / preparació de codi cablejada / transport real pendent |
| A111-06 Consum original | fitxes d'acció | consulta `promocions` legacy | `NovicePromotionRedemptionService` | classes §3, seq §4, act §7 | amount policy + integration preparada | IMPLEMENTAT_BRANCA / checkout pendent |
| A111-07 Canvi curs | fitxes d'acció | canvi general legacy | first + successive transfer review/confirmation | classes §3, seq §5, act §8 | policies pures escrites | IMPLEMENTAT_BRANCA / connectors pendents |
| A111-08 Baixa i derivat | fitxes d'acció | sense model canònic complet | cancellation review/activation original + transferred | classes §3, seq §5, act §9 | policies pures escrites | IMPLEMENTAT_BRANCA / aprovació real pendent |
| A111-09 Consum derivat | fitxes d'acció | no canònic | `NovicePromotionDerivedBalanceRedemptionService` | classes §3, seq §6, act §10 | 5 tests pures elegibilitat | IMPLEMENTAT_BRANCA / MySQL pendent |
| A111-10 Procedència | fitxes d'acció | dispersa | snapshot + projection + lineage policies | classes §4, seq §6 | projection/lineage tests escrites | IMPLEMENTAT_BRANCA |
| A111-11 Review refund JASOM | fitxes d'acció | manual/dispers | plan + review service | classes §4, seq §6, act §11 | fingerprint/approval tests escrites | IMPLEMENTAT_BRANCA / evidència externa pendent |
| A111-12 Execució/recovery | fitxes d'acció | no canònic | execution + recovery resolution/completion | classes §4, seq §6, act §11 | policies pures escrites | IMPLEMENTAT_BRANCA / integració real pendent |

## 2. Traçabilitat de persistència per tall

| Migració | Propòsit UC-111 | Consumidor principal | Executada en aquesta auditoria |
| --- | --- | --- | --- |
| 000008 | concessió `novice_promotion_grant` | GrantService | no |
| 000009 | outbox del codi | CodePreparation | no |
| 000010 | destinatari/claims de delivery | DeliveryAttempt | no |
| 000011 | challenge de correu | EmailVerification | no |
| 000012 | `novice_promotion_application` | RedemptionService | no |
| 000013 | derivats + transfers | lifecycle de canvi/baixa | no |
| 000014 | evidència de review transfer | CourseTransferReview | no |
| 000015 | REJECTED de proposta no emesa | cancellation review | no |
| 000016 | confirmació primer transfer | FirstTransferConfirmation | no |
| 000017 | reserva/aplicació derivada | DerivedBalanceRedemption | no |
| 000018 | saldo derivat amb `SOURCE_UUID_TRANSFER` | transferred cancellation | no |
| 000019 | tancament del transfer | transferred cancellation activation | no |
| 000020 | fonts de transfer successiu | successive transfer services | no |
| 000021 | root refund review | RootRefundReview | no |
| 000022 | recovery items | RootRefundExecution | no |
| 000023 | evidència resolució recovery | RecoveryResolution | no |
| 000024 | evidència refund origen | RootRefundExecution | no |
| 000025* | waiting state / tancament workflow | root refund lifecycle | no |
| 000026 | conservar evidència després del tancament | RecoveryCompletion | no |

`000025*`: a la branca hi ha dues migracions amb prefix temporal 000025 i noms diferents. **Abans d'aplicar en un entorn real cal revisar l'ordre/ledger de migracions** i confirmar que el runner usa el nom complet o un identificador inequívoc; no assumir que el prefix repetit és innocu.

## 3. Traçabilitat UML

| Vista | Fitxer canònic nou | Cobertura |
| --- | --- | --- |
| casos d'ús | [uc-111-casos-us-actual-final.md](uc-111-casos-us-actual-final.md) | ACTUAL legacy + FINAL SIF + subcasos |
| classes | [uc-111-classes-actual-final.md](uc-111-classes-actual-final.md) | legacy, concessió/lliurament, consum/canvi, lineage/refund |
| seqüències | [uc-111-sequencies-actual-final.md](uc-111-sequencies-actual-final.md) | alta/decisió, concessió, delivery, consum, canvi/baixa, refund |
| activitats | [uc-111-activitats-actual-final.md](uc-111-activitats-actual-final.md) | per pàgina/apartat i per lifecycle |
| cobertura UML 1:1 | [uc-111-diagrames-per-accio.md](uc-111-diagrames-per-accio.md) | 12 accions × cas d'ús + classes + seqüència + activitat = 48 blocs |
| fitxa integrada històrica | [uc-111-docent-novell-dret-futur.md](uc-111-docent-novell-dret-futur.md) | font acumulativa; manté decisions/talls anteriors |
| auditoria dirigida | [00-auditoria-circuit-cobrament-promocio-novell-2026-09-22.md](00-auditoria-circuit-cobrament-promocio-novell-2026-09-22.md) | troballes i evolució tècnica |

## 4. Traçabilitat de classes FINAL → taules

| Component | Taules / recursos |
| --- | --- |
| EnrollmentStager / SecretaryDecisionProjector | `commercial_operation`, parties, `discount_validation`, legacy `recent_titulat` |
| GrantService | `commercial_entitlement`, `novice_promotion_grant`, factura/fact_rels/payment |
| CodePreparation / delivery | entitlement, code outbox, verified recipient/challenge |
| RedemptionService | grant + `novice_promotion_application` + operation/factura/payment |
| transfer services | `novice_promotion_application_transfer` + factura/rectificació |
| cancellation/derived activation | `novice_promotion_derived_balance` |
| DerivedBalanceRedemption | derived balance + derived application |
| lineage snapshot/projection | grant, original/derived applications, transfers, derived balances |
| root refund review/execution | review + recovery item + origin refund evidence |
| recovery resolution/completion | recovery items + resolution evidence + summary/closure |

## 5. Estat real: documentat / implementat / verificat / pendent

| Bloc | Documentat | Implementat en branca | Verificat estàticament | Proves pures escrites | MySQL executat | Integració real |
| --- | --- | --- | --- | --- | --- | --- |
| alta/decisió | sí | sí | sí | parcial | no | pendent |
| concessió | sí | sí | sí | sí/parcial | no | pendent |
| delivery | sí | sí | sí | parcial | no | pendent |
| consum original | sí | sí | sí | sí | no | pendent |
| canvi/baixa | sí | sí | sí | sí | no | pendent |
| saldo derivat | sí | sí | sí | sí | no | pendent |
| procedència | sí | sí | sí | sí | no | pendent |
| root refund/recovery | sí | sí | sí | sí | no | pendent |
| custòdia de justificants | sí | no completa | risc legacy verificat | no | n/a | pendent |

## 6. Gaps que continuen bloquejant "UC-111 acabat"

1. **Autenticació i endpoints:** no hi ha una ruta final acreditada que connecti web/intranet amb tots els serveis nous i imposi rol, CSRF, party identity i idempotència.
2. **Evidència documental:** la pujada legacy no compleix el model de storage privat auditable final.
3. **Aprovació externa:** les interfaces d'aprovació/evidència són contractes; cal adaptador real i auditat.
4. **Pricing/fiscalitat:** checkout real ha de persistir snapshots finals, rectificatives i factures zero sense pagaments inventats.
5. **Redsys/concurrència:** callbacks tardans i reserves han de compartir criteris de conciliació abans de release/freeze.
6. **Migracions:** no aplicades; revisar també el doble prefix `000025`.
7. **Proves:** MySQL i concurrència real ajornades; no marcar cap flux BD com a PROVAT.
8. **Desplegament:** no acreditat; `main` i producció no són la branca auditada.

## 7. Porta documental de tancament

UC-111 només passa a **AUDITADA_COMPLETA** quan:

- les 12 accions tenen fitxa, casos d'ús, classes, seqüència i activitat coherents;
- cada servei/taula/estat citat existeix al commit auditat;
- els adapters finals d'UI, autenticació, fiscalitat, pagament, correu i evidències estan traçats;
- les proves de happy path, reintent, error, concurrència i recuperació tenen resultat real;
- el root refund pot reconstruir i congelar la cadena sense doble recompte;
- la documentació general 31/32/35 i les matrius 07 apunten al mateix paquet canònic.

## 8. Navegació

[Fitxa UC-111](../06-fitxes-funcionals/uc-111.md) · [Fitxes d'acció](../06-fitxes-funcionals/uc-111-accions.md) · [Casos d'ús](uc-111-casos-us-actual-final.md) · [Classes](uc-111-classes-actual-final.md) · [Seqüències](uc-111-sequencies-actual-final.md) · [Activitats](uc-111-activitats-actual-final.md)
