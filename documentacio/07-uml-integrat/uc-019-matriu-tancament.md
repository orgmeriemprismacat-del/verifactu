# UC-019 — Matriu de tancament

Data de revisió: 2026-10-04.

| Element | Documentat | Implementat | Verificat | Pendent |
| --- | --- | --- | --- | --- |
| Fitxa funcional específica | Sí | — | Revisada | Evidència preproducció |
| Pantalla `/alumnes/validar-descomptes/` | Sí | Sí | CI anterior + contract tests | Prova desplegada |
| POST/CSRF/sessió/rol | Sí | Sí; refresc de rols vigents abans de mutar | CI anterior + test nou pendent del head actual | Repetir en `sif_pre` i provar rol revocat |
| `requestId` persistent navegador | Sí | Sí | Test de contracte pendent del darrer head | CI del head actual + preproducció |
| Payload-bound idempotency en sessió | Sí | Sí | Test de contracte pendent del darrer head | CI del head actual |
| HMAC intranet → SIF | Sí | Sí | CI anterior | Prova desplegada amb secret real |
| Ledger `usoc_validation_decision` | Sí | Sí | CI MySQL anterior | Verificar migracions desplegades |
| `REQUESTED → COMMITTED/REVIEW_REQUIRED` | Sí | Sí | CI anterior | Evidència `sif_test/sif_pre` |
| Una decisió activa per `ID_INSC` | Sí | Sí | CI MySQL anterior | Aplicar/validar migració 000033 a entorn |
| Carrera classificació intranet/SIF | Sí | Sí | Test nou pendent del darrer head | CI + preproducció |
| Aprovació USOC | Sí | Sí | Tests de servei | Flux complet desplegat |
| Denegació USOC reclassificada 4→0/1 | Sí | Sí | Tests nous pendents del darrer head | CI + preproducció |
| Reintent després de resposta incerta | Sí | Sí | Contract tests nous pendents | Simulació de xarxa/preproducció |
| Reconciliador CLI | Sí | Sí | CI anterior | Executar amb REQUESTED controlat |
| Contracte econòmic `A_PAGAR` | Sí | Llegat existent | Test nou pendent del darrer head | Confirmar dades de prova |
| Criteri d’afiliació USOC | Parcial | Humà/llegat | No acreditat automàticament | Documentar font/procediment/evidència |
| Correus secretaria/alumne | Sí | Sí, dins del monòlit | Auditat estàticament | Prova SMTP controlada |
| Outbox de notificacions UC-019 | FINAL documentat | No | — | Refactor posterior |
| Diagrama classes ACTUAL/FINAL | Sí | — | Revisat | Cap bloqueig tècnic |
| Seqüència ACTUAL/FINAL | Sí | — | Revisada | Incorporar evidència final de preprod |
| Activitats per pàgina ACTUAL/FINAL | Sí | — | Revisades | Incorporar evidència final de preprod |

## Bloquejos reals abans de marcar CLOSED / VERIFIED_PREPRODUCTION

1. Aplicar/verificar migracions `000031` i `000033` a `sif_test` / `sif_pre`.
2. Executar `php sif/scripts/preflight-usoc-intranet.php` amb `ok=true`.
3. Executar aprovació USOC nominal.
4. Executar denegació amb reclassificació a `TIPUS_DESC=0/1`.
5. Repetir mateix `requestId` i provar payload divergent.
6. Provar dues decisions concurrents sobre el mateix `ID_INSC`.
7. Simular interrupció entre mutació llegada i `complete`, després executar reconciliador.
8. Provar rol autoritzat, rol revocat durant sessió, CSRF, HMAC incorrecte i timestamp caducat.
9. Conservar evidència de BD abans/després.
10. Documentar el procediment real amb què Gestió decideix “afiliació confirmada / no confirmada”.
11. Executar prova controlada de fallada SMTP i registrar la incidència manual mentre no hi hagi outbox integrada.

## Criteri d'estat

- **DOCUMENTAT:** complet.
- **IMPLEMENTAT:** complet per al nucli de decisió USOC i recuperació.
- **VERIFICAT EN CI:** complet per la versió anterior; els últims canvis de carrera/retry/economia estan pendents del workflow del head actual.
- **VERIFICAT EN PREPRODUCCIÓ:** pendent.
- **TANCAT:** no encara.
