# Lot 10 — UC-121 · Repreuar o renovar una reserva caducada abans del pagament

**Tall:** 25/09/2026, contrast del codi `main` amb la fitxa i l'UML de la branca documental. **Abast:** consulta/continuació del pagament de curs llegat, obtenció d'URL al portal alumne, intenció Redsys i callback del SIF, esquemes d'enllaç i plaça. **No s'ha acreditat cap pantalla ACTUAL que ofereixi explícitament «renovar reserva caducada» amb comparació/acceptació de preu nou.** No s'ha executat PHP, Redsys ni prova de producció. [Fitxa funcional](../06-fitxes-funcionals/uc-121.md) · [UML](uc-121-repreuar-renovar-reserva-caducada.md) · [activitats P01–P06](uc-121-activitats-pagines-reserva-caducada-actual-final.md).

## 1. Traçabilitat directa de components i superfícies

| Ref / apartat | Font `main` i fet acreditat | ACTUAL versus FINAL |
| --- | --- | --- |
| P01 · pàgina de curs pendent | [`PagamentCursAutomatic.php` L26–117](../../codi-drive/web-actual/PagamentCursAutomatic.php#L26-L117) llegeix una fila `inscripcions` per `IDPAG`, import `A_PAGAR`, `PAGAMENT`, fraccionament, descompte i edició; [L265–317](../../codi-drive/web-actual/PagamentCursAutomatic.php#L265-L317) calcula `faltaPagar=A_PAGAR-PAGAMENT` i mostra targeta/transferència o «pagat» segons condicions particulars. | La consulta d'una edició i de les seves dates no és una comprovació de `capacity_reservation.EXPIRES_AT`. No s'observa en el tram una reoferta versionada del preu/plaça. |
| P02 · formulari i confirmació de pagament | [`PagamentCursAutomatic.php` L328–395](../../codi-drive/web-actual/PagamentCursAutomatic.php#L328-L395) mostra confirmació amb branches de descompte/validació; [L431–498](../../codi-drive/web-actual/PagamentCursAutomatic.php#L431-L498) forma targeta amb `idPag`, curs, titular i import de la inscripció, destí `/efectPagAuto/`; [JS de confirmació L140–248](../../codi-drive/web-actual/js1619773569/mostrarConfirmacioInscripcioAutomatic.min.js#L140-L248) valida camps i envia al pagament. | És una **pàgina llegada de pagar inscripció**, no una implementació provada de revocació d'enllaç, nou preu, acceptació de nova reserva o control de callback vell. El tractament de JASOM és propi de UC-111, no es reaudita. |
| P03 · portal alumne, URL pagament | [`ajax/cursos/obtenirUrlPagament.php`](../../codi-drive/intranet-alumne-actual/ajax/cursos/obtenirUrlPagament.php), [`IntranetAlumne.php` L2947–2978](../../codi-drive/intranet-alumne-actual/IntranetAlumne.php#L2947-L2978): GET `tipusInsc,idPag`, xifra `idPag` i genera URL `/pagament/` o `/pagaments/` segons tipus G. | En aquest mètode no es consulta venciment, quota, preu vigent, estat de checkout ni `payment_link.STATUS`. Un URL xifrat no és per si sol oferta renovada. |
| P04 · servei SIF de crear intenció | [`RedsysPaymentIntentService.php` L19–85](../../sif/src/Service/RedsysPaymentIntentService.php#L19-L85): exigeix snapshot i desa `EXPIRES_AT` opcional; consulta per `DS_ORDER` i només reutilitza la intenció si **tots els camps rellevants**, inclòs venciment, coincideixen. | **IMP parcial i contrastada en codi:** existeix intent i conflicte per mateixa `DS_ORDER` amb data/import/snapshot nous. No crea per si mateix oferta/plaça/enllaç renovats ni comprova si el temps actual supera `EXPIRES_AT`. |
| P05 · callback Redsys | [`RedsysCallbackService.php` L21–90 i L134–153](../../sif/src/Service/RedsysCallbackService.php#L134-L153): valida signatura d'entrada, resol `DS_ORDER` amb lock, compara import/divisa/terminal i, si resposta `VALIDATED`, registra notificació i encua job. | **IMP parcial i contrastada en codi:** el mètode no comprova `EXPIRES_AT`, `payment_link` ni estat de plaça. Una notificació signada tardana **no valida el dret comercial actual**, però qualsevol moviment bancari real requereix conciliació i no es pot descartar. |
| P06 · model SIF de renovació | [SQL `redsys_payment_intent`](../../sif/database/migrations/2026_06_19_000002_create_redsys_payment_intent.sql), [`payment_link` 000004 L129–151](../../sif/database/migrations/2026_09_16_000004_add_commercial_operation_and_fiscal_fields.sql#L129-L151), [`capacity_reservation` 000005 L54–73](../../sif/database/migrations/2026_09_16_000005_add_operation_lifecycle_tables.sql#L54-L73) defineixen `EXPIRES_AT`, estats, revocació/substitució i reserva amb `LOCK_VERSION`. | **DDL definit ≠ migració aplicada/servei renovador verificat.** No s'ha identificat en les fonts contrastades un writer que atòmicament revoqui enllaç, alliberi plaça i generi oferta acceptada. |

## 2. Diferència entre venciments

El **venciment de la intenció** (`redsys_payment_intent.EXPIRES_AT`), el **venciment de l'enllaç** (`payment_link.EXPIRES_AT`), el **venciment de plaça** (`capacity_reservation.EXPIRES_AT`) i la **vigència de tarifa/edició** tenen dades i transicions diferents. Cap dels tres primers camps, per si sol, ordena regenerar un preu, reobrir una plaça o negar-se a registrar un cobrament real. En el FINAL, UC-121 consulta cadascun amb una política explícita, classifica el moviment bancari abans d'emetre una nova intenció i documenta els lligams entre l'operació vella i la nova.

## 3. Troballes i riscos de disseny/implementació

| ID | Dada contrastada | Acció pendent |
| --- | --- | --- |
| UC121-P0-01 · callback tardà | `assertMatchesIntent()` només compara import/divisa/terminal; el callback pot encolar job d'ordre antiga encara que la plaça hagi vençut. | Comprovació posterior de vigència comercial i reserva abans de concedir plaça o completar prestació; classificar ingrés real o resultat incert sense perdre'l. |
| UC121-P0-02 · canvi de preu sota ordre vella | `sameIntent()` rebutja mateix `DS_ORDER` amb `EXPIRES_AT` o snapshot diferents. | Crear proposta/snapshot i ordre **nous** amb acceptació; no mutar `EXPECTED_AMOUNT`/snapshot de l'ordre existent. |
| UC121-P0-03 · dues ordres, dos cobraments | La idempotència per `DS_ORDER` no és deduplicació entre ordre antiga i renovada. | Conciliar per moviment bancari real i relació entre dues operacions, evitar segona matrícula/atribució doble i classificar excés quan pertoqui. |
| UC121-P1-04 · plaça/capacitat | DDL `capacity_reservation` amb estat, expiració i versió, sense motor de renovació acreditat en aquestes fonts. | Nova disponibilitat i reserva sota lock abans d'oferta; sense plaça, no generar nou TPV de la prestació. |
| UC121-P1-05 · enllaç antic | DDL `payment_link` preveu revocació/substitució, però el mètode d'URL del portal llegat només xifra `IDPAG`. | Autoritzar o denegar consum d'enllaç segons estat/expiració al servidor; un enllaç antic no es reactiva per poder-lo obrir. |
| UC121-P1-06 · tarifa llegadament mostrada | Pàgina `PagamentCursAutomatic` usa `A_PAGAR-PAGAMENT`, sense recuperar una tarifa nova en els fragments inspeccionats. | Comparar tarifes/regles vigents amb snapshot original, obtenir nova acceptació fins i tot si preu és igual quan plaça/condicions han vençut. |
| UC121-P1-07 · documents emesos | Si hi ha factura emesa o import real cobrat, el cas ja no és una mera reserva no pagada. | Dirigir a UC d'incidència/pagament/correcció; cap UPDATE retroactiu de factura/snapshot. |

## 4. Proves — propostes UC121-T01–T14, **NO EXECUTADES**

| ID | Escenari FINAL |
| --- | --- |
| UC121-T01 | Reserva/enllaç encara vigents: mateixa oferta, no crear nova versió per error. |
| UC121-T02 | Venciment amb mateix preu i places: nova comprovació i acceptació segons regla; ordre antiga no recupera plaça automàticament. |
| UC121-T03 | Pujada/baixada de tarifa: nova base/descompte/impost calculats al servidor i nova acceptació. |
| UC121-T04 | Plaça esgotada o edició tancada: bloquejar nou TPV i oferir alternativa només si prevista. |
| UC121-T05 | Pagador rebutja nova proposta: no cobrar ni facturar-la. |
| UC121-T06 | Intentar crear el nou snapshot/data amb la mateixa `DS_ORDER`: conflicte, no UPDATE. |
| UC121-T07 | URL antiga xifrada però vençuda/revocada: backend nega nou checkout, conserva consultable només l'estat autoritzat. |
| UC121-T08 | Callback antic vàlid arriba després de venciment i sense nova ordre: reconciliar ingrés real i comprovar plaça, cap dret automàtic. |
| UC121-T09 | Dues `DS_ORDER` i **un** ingrés real notificat dues vegades: un moviment extern, una atribució. |
| UC121-T10 | Dues `DS_ORDER` i **dos** ingressos bancaris reals: conservar ambdós, una prestació i incidència/excés del segon si escau. |
| UC121-T11 | Dues renovacions concurrents per mateix recurs/versió: una sola reserva efectiva, una acceptació/versionat coherent. |
| UC121-T12 | Diferència zero però `EXPIRES_AT` nou i plaça alliberada: no reutilitzar ordre/plaça antiga per identitat d'import. |
| UC121-T13 | Factura emesa o cobrat abans de caducar: no reobrir UC121 com si estigués impagat; cas econòmic/fiscal específic. |
| UC121-T14 | Fallada en revocar enllaç, reservar plaça, crear intenció o comunicar: estat parcials/retry idempotent, no doble enllaç operatiu. |

**Evidència de tests de projecte:** [`RedsysPaymentIntentTest.php`](../../sif/tests/Integration/RedsysPaymentIntentTest.php) **defineix** tests de reutilització/conflicte de `DS_ORDER` i validacions d'entrada; això no és prova de la renovació comercial ni s'han executat en aquesta auditoria.

## 5. Decisions encara no fixades

`DEC121-01`: durada i tolerància específica de cada tipus de reserva/enllaç/intenció; `DEC121-02`: moment exacte de nova acceptació, inclòs preu igual però plaça alliberada; `DEC121-03`: política en absència de plaça i tractament d'una oferta alternativa; `DEC121-04`: procediment de conciliació davant callback anterior, import cobrat i nova oferta; `DEC121-05`: política de reserva de codis/descomptes afectats en renovar. Les invariants documentades no impliquen una durada numèrica universal.

**DOC del recorregut identificat i del FINAL: REVISADA AMB LÍMITS. IMP:** intenció/callback parcialment reals, servei integral de renovació pendent d'acreditar. **TEST:** no executat. **PRODUCCIÓ:** no verificada.
