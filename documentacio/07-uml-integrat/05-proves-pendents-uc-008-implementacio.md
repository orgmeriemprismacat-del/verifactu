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
- [x] rol manage pot obrir; cobert pels fluxos d'integració de lifecycle.
- [x] assignació crea acció i `IN_PROGRESS`.
- [x] evidència crea timeline sense canviar estat.
- [x] resolve sense evidència → 422.
- [x] resolve amb evidència → `RESOLVED` + data + criteri.
- [x] dismiss amb justificació → `DISMISSED`, i reobertura posterior verificada.
- [x] reobrir només des de `RESOLVED/DISMISSED`; `OPEN` retorna 409.
- [x] acció idempotent repetida no duplica `sif_incident_action`.
- [ ] dues accions concurrents sobre mateix incident mantenen estat coherent.

## 4. Redsys

- [x] conflicte 409/422 → queue INCIDENT + expedient.
- [x] max retries → queue INCIDENT + expedient.
- [x] mateix job Redsys no crea expedients duplicats; clau i payload d'incidència estables per `UUID_JOB`.
- [x] `incident_id` retornat en els fluxos d'incidència coberts.
- [x] error d'INSERT d'incidència després de `markIncident` → rollback verificat per `testIncidentInsertFailureRollsBackQueueIncidentTransition`.
- [x] `LAST_ERROR` i `DETAILS` Redsys redaccionen PAN/CVV/signatures/secrets abans de persistir.

## 5. AEAT

- [x] payload divergent → no transport + DEAD_LETTER + FISCAL_PAYLOAD_CONFLICT.
- [x] retry 1/2 → RETRY sense incidència final.
- [x] retry final → DEAD_LETTER + AEAT_DEAD_LETTER.
- [x] mateix `fiscal_queue.ID` reutilitza una sola incidència `AEAT_DEAD_LETTER`.
- [x] `REJECTED` remot es persisteix com a resultat terminal `SENT/REJECTED`, separat de retry/dead-letter local.
- [x] resposta remota incerta → `REVIEW` + incidència `AEAT_DELIVERY_UNCERTAIN`; no es retransmet a cegues.

## 6. API interna

- [x] GET → 405, contracte verificat a l'endpoint.
- [x] HMAC invàlid → 401.
- [x] timestamp caducat → 401.
- [x] request-id repetit → 409 anti-replay.
- [x] rols de lectura/gestió buits → 403 fail-closed.
- [x] action desconeguda → 422 via `SifException::validation`.
- [x] list aplica límit màxim configurat i clamp de la petició.
- [x] view desconegut → 404.
- [x] assign/resolve registren `ACTOR_ID` i `ACTOR_ROLE` al journal.
- [x] l'actor autenticat/retorns no exposen `secret`, `signature` ni `key_id`; secrets no formen part de la resposta.

## 7. UI implementada al codi · E2E pendent

- [x] llistat filtrable per estat, severitat, tipus i responsable (`assignee_id`), amb cobertura d'integració.
- [x] detall mostra resource/source/correlation i no exposa secrets de configuració.
- [x] timeline ordenat per `CREATED_AT, ID` i repositori d'accions append-only (sense UPDATE/DELETE).
- [x] auditor/read-only queda denegat al backend; la intranet no exposa accions de mutació. Pendent només comprovació visual E2E dels controls.
- [x] RESOLVED exigeix evidència tant a UI com backend i la mostra al timeline.
- [x] DISMISSED exigeix criteri/notes i el detall mostra criteri de tancament i resolució.
- [ ] enllaços de reparació van al UC/pantalla correcte.
- [x] no existeix cap acció massiva “retry all / reintentar tot” al panell.

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

**Estat actual:** SUITE SIF ampliada VERIFICADA (**645 passed, 0 failed**, run `36660840670`) + INTRANET AO **SUCCESS**. Continuen pendents els ítems no marcats, especialment concurrència específica, E2E real de preproducció, permisos/secrets productius i alta del menú de BD.


## 12. Evidència CI

- Runs inicials **36638546735** i **36638546786**: 555 passed, 0 failed.
- Run **36648545296** després de la integració UI UC-008: **589 passed, 0 failed**.
- Run **36658230379** després de preflight + go/no-go + frontera read-only: **618 passed, 0 failed**.
- Run **36660840670** després de deduplicació Redsys/AEAT, redacció sensible, API/UI i E2E tècnic: **645 passed, 0 failed**.
- Run **36647777483** · Intranet AO batch checks: **success**.
## 13. CI automatitzada

S'ha afegit `.github/workflows/sif-tests.yml` per executar `php sif/tests/run-tests.php` amb PHP 8.4 i MySQL 8.4 en pull requests, canvis a `main` que afectin `sif/**` i execució manual (`workflow_dispatch`).

La suite SIF i els checks d'intranet ja disposen d'evidència CI satisfactòria després de la implementació de la UI. Continuen pendents E2E/preproducció i configuració productiva abans de marcar el panell verificat en runtime.

**Estat actual:** SIF CI **645/0** + INTRANET AO SUCCESS; UI + PREFLIGHT + E2E TÈCNIC IMPLEMENTATS / EXECUCIÓ E2E PREPRODUCCIÓ PENDENT.
