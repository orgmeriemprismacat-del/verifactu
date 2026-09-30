# UC-008 · Proves executades i pendents

Aquest document separa la **suite automatitzada ja executada** de les proves E2E, concurrència i preproducció que encara falten. Després d'integrar UI, preflight i contractes read-only UC-008, el run `36658230379` ha finalitzat amb **618 passed, 0 failed**; `Intranet AO batch checks` run `36647777483` continua en **success**.

## 1. Suite PHP/MySQL

Executar sobre `sif_test` o `sif_test_*`:

```powershell
./sif/scripts/local-test.ps1 -Action Start
./sif/scripts/local-test.ps1 -Action Migrate
./sif/scripts/local-test.ps1 -Action Test
./sif/scripts/local-test.ps1 -Action Preflight
```

o equivalent portable:

```text
php sif/scripts/run-migrations.php
php sif/tests/run-tests.php
php sif/scripts/preflight-sif.php
```

## 2. Repositori i identitat

- [ ] open legacy continua creant fila OPEN.
- [x] openDetailed retorna `incident_id` i `uuid_incident`.
- [ ] factura existent acceptada.
- [x] factura desconeguda → 404 i cap fila orfe.
- [ ] pagament existent acceptat.
- [ ] pagament desconegut → 404.
- [ ] resource_type sense resource_id → 422.
- [x] mateixa idempotency key + mateix payload → reuse.
- [x] mateixa idempotency key + payload diferent → 409.
- [ ] dues obertures concurrents mateixa key → una sola capçalera.

## 3. Lifecycle

- [x] rol read pot list/view.
- [x] rol read no pot assign/resolve/dismiss.
- [ ] rol manage pot obrir.
- [x] assignació crea acció i `IN_PROGRESS`.
- [x] evidència crea timeline sense canviar estat.
- [x] resolve sense evidència → 422.
- [x] resolve amb evidència → `RESOLVED` + data + criteri.
- [x] dismiss amb justificació → `DISMISSED`, i reobertura posterior verificada.
- [ ] reobrir només des de `RESOLVED/DISMISSED`.
- [x] acció idempotent repetida no duplica `sif_incident_action`.
- [ ] dues accions concurrents sobre mateix incident mantenen estat coherent.

## 4. Redsys

- [x] conflicte 409/422 → queue INCIDENT + expedient.
- [x] max retries → queue INCIDENT + expedient.
- [ ] mateix job no crea expedients duplicats.
- [x] `incident_id` retornat en els fluxos d'incidència coberts.
- [x] error d'INSERT d'incidència després de `markIncident` → rollback verificat per `testIncidentInsertFailureRollsBackQueueIncidentTransition`.
- [ ] dades de DETAILS no inclouen PAN/CVV/signatures/secrets.

## 5. AEAT

- [x] payload divergent → no transport + DEAD_LETTER + FISCAL_PAYLOAD_CONFLICT.
- [x] retry 1/2 → RETRY sense incidència final.
- [x] retry final → DEAD_LETTER + AEAT_DEAD_LETTER.
- [ ] mateix queue ID no duplica incidència.
- [ ] acceptació/rebuig remot no es confon amb error local.
- [ ] resposta remota incerta deriva a revisió UC-77 abans de retransmetre.

## 6. API interna

- [ ] GET → 405.
- [ ] HMAC invàlid → 401.
- [ ] timestamp caducat → 401.
- [ ] request-id repetit → 409 anti-replay.
- [ ] rol no configurat → 403 fail-closed.
- [ ] action desconeguda → 422.
- [ ] list aplica límit màxim.
- [ ] view desconegut → 404.
- [ ] assign/resolve registren actor i rol.
- [ ] cap secret de configuració surt a la resposta.

## 7. UI implementada al codi · E2E pendent

- [ ] llistat per estat/severitat/tipus/responsable.
- [ ] detall mostra resource/source/correlation sense exposar secrets.
- [ ] timeline ordenat i immutable.
- [x] auditor/read-only queda denegat al backend; la intranet no exposa accions de mutació. Pendent només comprovació visual E2E dels controls.
- [ ] RESOLVED requereix evidència visible.
- [ ] DISMISSED mostra justificació.
- [ ] enllaços de reparació van al UC/pantalla correcte.
- [ ] cap botó “reintentar tot”.

## 8. Intranet VERI*FACTU

- [x] indicador/resum implementat al codi.
- [x] resum read-only implementat al codi.
- [x] SIF indisponible → mostra últim resum validat de la sessió o estat indisponible; mai fals “0 incidències”.
- [x] resolució deriva al panell SIF mitjançant handoff signat.
- [x] la intranet es manté read-only i no es converteix en font de veritat.

## 9. Preflight de desplegament

- [x] `sif/scripts/preflight-incidents-panel.php` implementat.
- [x] prova GO amb rols/secrets/paths correctes.
- [x] prova NO-GO amb rols buits i secrets febles.
- [x] prova NO-GO si un rol gestor no té també lectura.
- [x] comprovació read-only: el preflight no emet factures, no registra pagaments i no tanca incidències.
- [x] integració dels checks UC-008 dins `go-no-go-preproduction.php`.
- [ ] executar el preflight amb secrets/rols reals de preproducció i conservar-ne la sortida.

## 10. E2E read-only automatitzable

- [x] `sif/scripts/e2e-incidents-panel.php` creat.
- [x] bloqueig explícit de `production`.
- [x] URL obligatòriament HTTPS.
- [x] contracte read-only: només `summary`, `list` i `logout`.
- [x] verificació de 303, cookie, CSRF, actor sense controls de gestió i logout.
- [x] test que impedeix introduir mutacions al script.
- [ ] executar-lo contra preproducció amb URL, secret i rol real.
- [ ] conservar la sortida JSON de l'execució real.

## 11. Evidència de tancament

Per marcar UC-008 com PROVAT conservar:

- sortida de suite;
- versió/commit;
- hash de migracions;
- captura o export de casos nominal/denegat;
- prova Redsys rollback;
- prova AEAT dead-letter;
- prova de permisos;
- prova de tancament amb evidència;
- resultat preproducció.

**Estat actual:** SUITE SIF POST-PREFLIGHT VERIFICADA (**618 passed, 0 failed**) + INTRANET AO **SUCCESS**. Continuen pendents els ítems no marcats, especialment concurrència específica, E2E de navegador, permisos/secrets productius i preproducció.


## 12. Evidència CI

- Runs inicials **36638546735** i **36638546786**: 555 passed, 0 failed.
- Run **36648545296** després de la integració UI UC-008: **589 passed, 0 failed**.
- Run **36658230379** després de preflight + go/no-go + frontera read-only: **618 passed, 0 failed**.
- Run **36647777483** · Intranet AO batch checks: **success**.
## 13. CI automatitzada

S'ha afegit `.github/workflows/sif-tests.yml` per executar `php sif/tests/run-tests.php` amb PHP 8.4 i MySQL 8.4 en pull requests, canvis a `main` que afectin `sif/**` i execució manual (`workflow_dispatch`).

La suite SIF i els checks d'intranet ja disposen d'evidència CI satisfactòria després de la implementació de la UI. Continuen pendents E2E/preproducció i configuració productiva abans de marcar el panell verificat en runtime.

**Estat actual:** SIF CI 618/0 + INTRANET AO SUCCESS; UI + PREFLIGHT IMPLEMENTATS / E2E PREPRODUCCIÓ PENDENT.
