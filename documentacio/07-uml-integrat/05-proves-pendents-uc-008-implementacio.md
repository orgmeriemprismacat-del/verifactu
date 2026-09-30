# UC-008 · Proves executades i pendents

Aquest document separa la **suite automatitzada ja executada** de les proves E2E, concurrència i preproducció que encara falten. Després d'integrar panell i resum UC-008, el run `36648545296` ha finalitzat amb **589 passed, 0 failed** i `Intranet AO batch checks` run `36647777483` amb **success**.

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
- [ ] dismiss amb justificació → `DISMISSED`.
- [ ] reobrir només des de `RESOLVED/DISMISSED`.
- [x] acció idempotent repetida no duplica `sif_incident_action`.
- [ ] dues accions concurrents sobre mateix incident mantenen estat coherent.

## 4. Redsys

- [x] conflicte 409/422 → queue INCIDENT + expedient.
- [x] max retries → queue INCIDENT + expedient.
- [ ] mateix job no crea expedients duplicats.
- [ ] `incident_id` retornat.
- [ ] simular error d'INSERT d'incidència després de markIncident → rollback deixa job sense canvi.
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
- [ ] auditor no veu controls de mutació i el backend també els denega.
- [ ] RESOLVED requereix evidència visible.
- [ ] DISMISSED mostra justificació.
- [ ] enllaços de reparació van al UC/pantalla correcte.
- [ ] cap botó “reintentar tot”.

## 8. Intranet VERI*FACTU

- [x] indicador/resum implementat al codi.
- [x] resum read-only implementat al codi.
- [ ] SIF indisponible → mostrar últim estat validat/indisponibilitat, no “0 incidències”.
- [ ] resolució deriva al panell SIF.
- [ ] la intranet no es converteix en font de veritat.

## 9. Evidència de tancament

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

**Estat actual:** SUITE SIF POST-UI VERIFICADA (**589 passed, 0 failed**) + INTRANET AO **SUCCESS**. Continuen pendents els ítems no marcats, especialment concurrència específica, E2E de navegador, permisos/secrets productius i preproducció.


## 10. Evidència CI

- Runs inicials **36638546735** i **36638546786**: 555 passed, 0 failed.
- Run **36648545296** després de la integració UI UC-008: **589 passed, 0 failed** sobre `main`.
- Run **36647777483** · Intranet AO batch checks: **success**.
## 10. CI automatitzada

S'ha afegit `.github/workflows/sif-tests.yml` per executar `php sif/tests/run-tests.php` amb PHP 8.4 i MySQL 8.4 en pull requests, canvis a `main` que afectin `sif/**` i execució manual (`workflow_dispatch`).

La suite SIF i els checks d'intranet ja disposen d'evidència CI satisfactòria després de la implementació de la UI. Continuen pendents E2E/preproducció i configuració productiva abans de marcar el panell verificat en runtime.

**Estat actual:** SIF CI 589/0 + INTRANET AO SUCCESS; UI IMPLEMENTADA AL CODI / E2E PENDENT.
