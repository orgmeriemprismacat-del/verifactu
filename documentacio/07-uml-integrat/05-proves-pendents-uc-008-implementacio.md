# UC-008 · Proves pendents d'execució

Aquest document separa **tests escrits** de **tests realment executats**. La presència d'un fitxer de test no acredita PASS.

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
- [ ] openDetailed retorna `incident_id` i `uuid_incident`.
- [ ] factura existent acceptada.
- [ ] factura desconeguda → 404 i cap fila orfe.
- [ ] pagament existent acceptat.
- [ ] pagament desconegut → 404.
- [ ] resource_type sense resource_id → 422.
- [ ] mateixa idempotency key + mateix payload → reuse.
- [ ] mateixa idempotency key + payload diferent → 409.
- [ ] dues obertures concurrents mateixa key → una sola capçalera.

## 3. Lifecycle

- [ ] rol read pot list/view.
- [ ] rol read no pot assign/resolve/dismiss.
- [ ] rol manage pot obrir.
- [ ] assignació crea acció i `IN_PROGRESS`.
- [ ] evidència crea timeline sense canviar estat.
- [ ] resolve sense evidència → 422.
- [ ] resolve amb evidència → `RESOLVED` + data + criteri.
- [ ] dismiss amb justificació → `DISMISSED`.
- [ ] reobrir només des de `RESOLVED/DISMISSED`.
- [ ] acció idempotent repetida no duplica `sif_incident_action`.
- [ ] dues accions concurrents sobre mateix incident mantenen estat coherent.

## 4. Redsys

- [ ] conflicte 409/422 → queue INCIDENT + expedient.
- [ ] max retries → queue INCIDENT + expedient.
- [ ] mateix job no crea expedients duplicats.
- [ ] `incident_id` retornat.
- [ ] simular error d'INSERT d'incidència després de markIncident → rollback deixa job sense canvi.
- [ ] dades de DETAILS no inclouen PAN/CVV/signatures/secrets.

## 5. AEAT

- [ ] payload divergent → no transport + DEAD_LETTER + FISCAL_PAYLOAD_CONFLICT.
- [ ] retry 1/2 → RETRY sense incidència final.
- [ ] retry final → DEAD_LETTER + AEAT_DEAD_LETTER.
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

## 7. UI futura

- [ ] llistat per estat/severitat/tipus/responsable.
- [ ] detall mostra resource/source/correlation sense exposar secrets.
- [ ] timeline ordenat i immutable.
- [ ] auditor no veu controls de mutació i el backend també els denega.
- [ ] RESOLVED requereix evidència visible.
- [ ] DISMISSED mostra justificació.
- [ ] enllaços de reparació van al UC/pantalla correcte.
- [ ] cap botó “reintentar tot”.

## 8. Intranet VERI*FACTU

- [ ] indicador de pendents.
- [ ] resum read-only.
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

**Estat actual:** PENDENT D'EXECUCIÓ.
