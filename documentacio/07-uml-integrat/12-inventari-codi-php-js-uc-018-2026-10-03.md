# UC-018 · Inventari de codi PHP/JS ACTUAL/FINAL · 03/10/2026

## 1. Objectiu

Inventariar les superfícies reals del cas d'ús **UC-018 · Bescanviar regal** i separar:

- codi executable actual;
- infraestructura SIF;
- UI/JS cablejat;
- còpies o fitxers històrics;
- documentació/UML;
- peces que no pertanyen al flux final.

## 2. Entrada web i navegació

| Fitxer | Rol UC-018 | Estat |
| --- | --- | --- |
| `codi-drive/web-actual/.htaccess` | routing públic UC-018 | ACTIU / PATCH 04-10; `/bescanvia-regal` → `pagina_bescanvia.php` |
| `codi-drive/web-actual/pagina_bescanvia.php` | pàgina d'entrada | ACTIU; cablejat a `mostrarBescanvia.min.js?ver=6.0` |
| `codi-drive/web-actual/js1619773569/mostrarBescanvia.min.js` | controlador navegador del bescanvi | ACTIU / PATCH POST |
| `codi-drive/web-actual/ajax/mostrar_pagina_bescanvia.php` | render inicial del formulari de codi | ACTIU |
| `codi-drive/web-actual/BescanviaRegal.php` | lògica legacy de presentació/validació/cursos | ACTIU; resposta neutra + match exacte |
| `codi-drive/web-actual/RegalCurs.php` | origen del secret bearer a UC-017 | ADJACENT/CRÍTIC; generació CSPRNG de 10 caràcters |
| `codi-drive/web-actual/ajax/codiRegalValid.php` | prevalidació del codi | ACTIU / POST-only |
| `codi-drive/web-actual/ajax/buscarCursRegalat.php` | resol curs/modalitat legacy | ACTIU / POST-only + revalidació |
| `codi-drive/web-actual/ajax/bescanviaUnCurs.php` | render de formulari per curs/edició | ACTIU; GET informatiu sense secret de regal |
| `codi-drive/web-actual/ajax/inscripcioDuplicada.php` | comprovació de matrícula prèvia | ACTIU/transversal; POST en UC-018 per no posar DNI a URL |
| `codi-drive/web-actual/ajax/buscarSiHaRealitzatElCurs.php` | comprova curs ja realitzat per DNI | ACTIU/transversal; POST en UC-018, fallback GET per compatibilitat externa |
| `codi-drive/web-actual/ajax/enviamentPubli.php` | consulta estat mailing segons correu | ACTIU/transversal; POST en UC-018, fallback GET per compatibilitat externa |
| `codi-drive/web-actual/ajax/enviarInscripcioBescanvia.php` | writer legacy + guard de pagament/curs/hores pre-commit + crida SIF + govern de correus | ACTIU / POST-only / PATCH 04-10 |

## 3. Confirmació web

| Fitxer | Rol | Estat |
| --- | --- | --- |
| `codi-drive/web-actual/inc/GiftRedemptionConfirmationToken.php` | emet/valida token v2 AES-256-GCM | ACTIU / PATCH 04-10 |
| `codi-drive/web-actual/pagina_confirmacio_bescanvia.php` | shell `no-store/no-referrer` | ACTIU / PATCH 04-10 |
| `codi-drive/web-actual/js1619773569/mostrarConfirmacioBescanvia.min.js` | llegeix token del fragment i el valida per POST | ACTIU / PATCH 04-10 |
| `codi-drive/web-actual/ajax/mostrar_confirmacio_bescanvia.php` | POST-only; AEAD→ID_INSC; verifica `regal.USAT`; no mostra codi i emmascara correu | ACTIU / PATCH 04-10 |

La confirmació **no** mostra el codi regal. El token només encapsula l'`ID_INSC`, es valida per POST i la resposta pública mostra únicament una confirmació genèrica amb el correu emmascarat.

## 4. Frontera legacy → SIF

| Fitxer | Responsabilitat | Estat |
| --- | --- | --- |
| `codi-drive/web-actual/inc/SifGiftRedemptionClient.php` | POST/HMAC de redeem i claim/complete de notificacions | IMPLEMENTAT |
| `sif/public/api/gifts/redemption/redeem.php` | endpoint intern autenticat | IMPLEMENTAT |
| `sif/public/api/gifts/redemption/notifications.php` | claim/complete GIFT_REDEEM_* | IMPLEMENTAT |

## 5. Nucli SIF

| Component | Responsabilitat | Estat |
| --- | --- | --- |
| `GiftEntitlementIssuerService` | emetre/reutilitzar dret GIFT des d'UC-017 | IMPLEMENTAT |
| `CommercialEntitlementRepository` | lock, claim, reserve, consume, release, events | IMPLEMENTAT |
| `GiftRedemptionTrustedContextResolver` | participant/snapshot autoritatiu + `CCURS=N` + mateix curs o swap concret de mateixes hores | IMPLEMENTAT / PATCH 04-10 |
| `GiftEnrollmentStager` | revalidar contracte immutable i elegibilitat per hores abans de crear/reutilitzar ENROLLMENT/INSCRIPCIO | IMPLEMENTAT / PATCH 04-10 |
| `GiftRedemptionService` | aplicar dret, fons i consum idempotent | IMPLEMENTAT |
| `EnrollmentFundMovementRepository` | `COMPENSATION_ALLOCATION` sobre pagament original | IMPLEMENTAT |
| `LegacyGiftUsageReconciler` | compare-and-set de `regal.USAT` | IMPLEMENTAT |
| `GiftRedemptionOrchestrator` | saga/replay/recovery | IMPLEMENTAT |
| `GiftRedemptionNotificationBundleService` | sis notificacions idempotents | IMPLEMENTAT |
| `NotificationOutboxDeliveryService` | govern d'estat de lliurament | IMPLEMENTAT |

## 6. Operació i preproducció

- `sif/scripts/preflight-gift-redemption.php` — read-only.
- `sif/scripts/verify-gift-redemption-preproduction.php` — dry-run per defecte; `--execute` explícit.
- `sif/scripts/go-no-go-preproduction.php` — gate agregat de preproducció.

La presència del codi està verificada; l'execució real continua sent [ENV].

## 7. Proves específiques localitzades

Com a mínim:

- `GiftEnrollmentStagerTest`;
- `GiftRedemptionConcurrencyTest`;
- `GiftRedemptionEndToEndTest`;
- `GiftRedemptionEndpointBoundaryTest`;
- `GiftRedemptionLegacyMailBoundaryTest`;
- `GiftRedemptionNotificationBundleServiceTest`;
- `GiftRedemptionPreproductionBoundaryTest`;
- `GiftRedemptionRecoveryCliBoundaryTest`;
- `GiftRedemptionServiceTest`;
- `GiftRedemptionTrustedContextResolverTest`;
- `GiftRedemptionWebClientBoundaryTest`;
- `HistoricalGiftEntitlementBackfillServiceTest`;
- `HistoricalGiftEntitlementPreflightScriptTest`;
- `NotificationOutboxDeliveryServiceTest`.

La revalidació 03–04/10 amplia `GiftRedemptionWebClientBoundaryTest` perquè tota la PII navegador→legacy i el guard pre-commit quedin governats; amplia `GiftRedemptionTrustedContextResolverTest`/`GiftEnrollmentStagerTest` amb regal genèric, swap concret same-hours, mismatch i tampering del snapshot; i afegeix `GiftRedemptionConfirmationBoundaryTest` per routing, token AEAD, tampering, fragment URL i POST de confirmació.

## 8. Fitxers històrics/no canònics

| Fitxer | Classificació |
| --- | --- |
| `ajax/enviarInscripcioBescanviaProva.php` | còpia/prova; no forma part del flux canònic documentat |
| `ajax/enviarInscripcioBescanvia_2026-04-23_17-49.php` | snapshot històric |
| `ajax/previsualitza_regal.php` | superfície de regal/compra adjacent; no és el redeem final UC-018 |
| `ajax/mostrar_formulari_afortunat_regal.php` | superfície adjacent de regal; no és el writer final UC-018 |

No s'han d'utilitzar aquestes còpies per acreditar l'estat FINAL del cas.

## 9. Documentació que ha d'estar sincronitzada

- `documentacio/06-fitxes-funcionals/uc-018.md`;
- `uc-018-bescanviar-regal.md`;
- `uc-018-classes-actual-final.md`;
- `uc-018-sequencies-actual-final.md`;
- `uc-018-activitats-pagines-bescanvi-regal-actual-final.md`;
- `04-auditoria-detallada-uc-018-bescanviar-regal-2026-09-30.md`;
- `05-proves-pendents-uc-018-implementacio.md`;
- `10-tancament-auditoria-uc-018-2026-10-02.md`;
- `11-revalidacio-auditoria-uc-018-2026-10-03.md`;
- aquest inventari;
- `13-revalidacio-auditoria-uc-018-2026-10-04.md`;
- `14-execucio-selectiva-uc-018-sif-test-preproduccio.md`.

## 10. Conclusió

Sí que existeixen les peces principals de fitxa, codi PHP/JS, classes, seqüències, activitats, proves i scripts operatius. La revalidació ha localitzat i corregit: **PII residual en query strings**, **bundle no rastrejable**, **routing públic cap a un fitxer prova inexistent**, **enumeració d'estat**, **CSPRNG insuficient**, **fixtures sense snapshot real**, **regal genèric no suportat**, **swap de regal concret per hores no suportat**, **validació massa tardana del curs al writer** i **token de confirmació CBC amb IV no autenticat/parser fràgil**. L'acceptació final queda subjecta al CI nou i al gate real de preproducció; `FAILED/SENDING` de notificacions requereix reconciliació OPS separada i la configuració `dies-inscriu-cursos` s'ha de validar contra ambigüitats.
