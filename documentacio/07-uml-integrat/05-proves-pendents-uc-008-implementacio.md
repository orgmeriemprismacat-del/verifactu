# UC-008 · Proves executades i pendents

Aquest document separa la **suite automatitzada ja executada** de l'acceptació que encara depèn de l'entorn real. L'auditoria de codi queda tancada: el tall executable `5cc0410018929bed53d0e2e2078f4b4c4f2bf6f7`, run `36943995075`, ha finalitzat amb **844 passed, 0 failed** i inclou **74 proves PASS relacionades amb incidències/UC-008**. La comparació fins al `main` observat `f7fa0822f82be96e842d9f2d031e643ab07f617c` només afegeix documentació d'altres UC, sense canvis executables UC-008. `Intranet AO batch checks` run `36647777483` continua com a evidència històrica en **success**. La concurrència, preflight, deep-links i gate d'evidències estan coberts per tests; el pendent és exclusivament d'acceptació real de preproducció i conservació de les evidències.

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

- [x] `open()` legacy continua creant fila `OPEN` amb identitat estable.
- [x] openDetailed retorna `incident_id` i `uuid_incident`.
- [x] factura existent acceptada i vinculada a la incidència.
- [x] factura desconeguda → 404 i cap fila orfe.
- [x] pagament existent acceptat i vinculat a `UUID_PAYMENT`.
- [x] pagament desconegut → 404 i no persisteix fila orfe.
- [x] `resource_type` i `resource_id` són parella obligatòria; absència d'un dels dos → 422.
- [x] mateixa idempotency key + mateix payload → reuse.
- [x] mateixa idempotency key + payload diferent → 409.
- [x] dues obertures concurrents reals amb la mateixa key → una sola capçalera i una sola acció `OPEN`; recuperació del duplicate amb current read `FOR UPDATE`.

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
- [x] dues assignacions concurrents reals sobre el mateix incident queden serialitzades per `FOR UPDATE`, amb dues accions coherents i un únic estat final.

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
- [x] enllaços de reparació/navegació implementats sobre superfícies existents: factura SIF → `alumnes-factura.php?uuid_factura=...`; registre AEAT → `sif-registres-aeat.php?queue_id=...`. Només es generen amb UUID/queue vàlids i no executen cap reparació automàtica.
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
- [x] preflight integrat al verificador agregat; l'execució real queda consolidada al punt únic de preproducció de l'apartat 10.

## 10. E2E read-only automatitzable

- [x] `sif/scripts/e2e-incidents-panel.php` creat.
- [x] bloqueig explícit de `production`.
- [x] URL obligatòriament HTTPS.
- [x] contracte read-only: només `summary`, `list` i `logout`.
- [x] verificació de 303, cookie, CSRF, actor sense controls de gestió i logout.
- [x] test que impedeix introduir mutacions al script.
- [x] E2E integrat al verificador agregat i sanitització d'evidència provada.
- [ ] **PENDENT D'ENTORN:** generar i conservar **tres JSON d'evidència** amb configuració real: (1) `uc-008-preproduction-evidence.json` amb preflight + E2E read-only; (2) `uc-008-menu-evidence.json` amb el menú en `ALREADY_PRESENT`; (3) `uc-008-manager-e2e-evidence.json` després d'exercitar el flux gestor real sobre la incidència sintètica. Només després es pot generar `uc-008-closure-validation.json`.

## 11. Procediment únic de tancament d'entorn

Executar sobre els entorns corresponents, sense reutilitzar secrets en fitxers:

```bash
# SIF / pay.prisma.cat
php sif/scripts/verify-incidents-panel-preproduction.php   | tee uc-008-preproduction-evidence.json

# Intranet / intranet.prisma.cat
cd codi-drive/intranet-actual
php preflight-sif-verifactu-menu.php   | tee uc-008-menu-evidence.json
```

Després de generar els tres fitxers, executar:

```bash
php sif/scripts/validate-uc008-evidence.php \
  uc-008-preproduction-evidence.json \
  uc-008-menu-evidence.json \
  uc-008-manager-e2e-evidence.json \
  | tee uc-008-closure-validation.json
```

El tancament d'entorn només és vàlid si aquest últim JSON retorna `ok=true`. El validador falla tancat i exigeix, a més:

- evidència superior amb `scope=uc-008-preproduction-verification`;
- `environment=preproduction` — una execució amb `SIF_ENV=test` **no pot tancar** el cas;
- `checks.preflight_ok=true`;
- `checks.e2e_ok=true`;
- `production_authorized=false`;
- evidència de menú amb `ok=true` i `scope=uc-008-intranet-menu-discovery`;
- `read_only=true`, `target_url=/sif-verifactu.php`, exactament una fila i `status=ALREADY_PRESENT`;
- absència de claus amb secrets/passwords/signatures;
- `uc-008-manager-e2e-evidence.json` acredita en lectura el flux gestor real `ASSIGN → ADD_EVIDENCE → RESOLVE` sobre una incidència sintètica `UC008_E2E_MANAGER / UC008_E2E`;
- `uc-008-closure-validation.json` incorpora `validated_at` i els SHA-256 dels **tres** inputs, i el gate exigeix que tots tres hashes siguin vàlids.

Això evita que un JSON parcial, d'un altre script o generat només en entorn de test es pugui interpretar com a tancament de preproducció.

Criteri de tancament:

1. `uc-008-preproduction-evidence.json` → `ok=true` i `environment=preproduction`;
2. el JSON no conté secrets ni passwords;
3. `uc-008-menu-evidence.json` → `existing_target_count <= 1`;
4. si `status=CONFIRM_PARENT_ROLES_ORDER_BEFORE_INSERT`, usar els candidats retornats per completar l'alta idempotent descrita a `07-alta-menu-intranet-uc-008.md`;
5. després de l'alta, tornar a executar el preflight de menú i exigir `status=ALREADY_PRESENT`;
6. executar el flux gestor real sobre una incidència sintètica i generar `uc-008-manager-e2e-evidence.json` amb `verify-incident-manager-evidence.php`; ha de donar `ok=true` i `environment=preproduction`;
7. comprovar amb un usuari read-only i un gestor que els deep-links de factura i AEAT obren el recurs esperat.

## 12. Evidència de tancament

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

**Estat actual (02/10/2026): AUDITORIA/CODI TANCATS.** Darrer tall executable aplicable `5cc0410018929bed53d0e2e2078f4b4c4f2bf6f7`, run `36943995075`: **844/0**, amb **74 PASS relacionats amb incidències/UC-008**. Resta executar únicament els controls reals d'acceptació d'entorn, completar l'alta de menú si cal, exercitar el flux gestor sobre la incidència sintètica i validar les tres evidències amb `validate-uc008-evidence.php`. Aquests passos condicionen `ENVIRONMENT-CLOSED`, no `AUDIT-CLOSED`.


## 13. Evidència CI

- Runs inicials **36638546735** i **36638546786**: 555 passed, 0 failed.
- Run **36648545296** després de la integració UI UC-008: **589 passed, 0 failed**.
- Run **36658230379** després de preflight + go/no-go + frontera read-only: **618 passed, 0 failed**.
- Run **36660840670** després de deduplicació Redsys/AEAT, redacció sensible, API/UI i E2E tècnic: **645 passed, 0 failed**.
- Run **36661335874** després de la correcció de cursa idempotent i tests de concurrència real: **648 passed, 0 failed**.
- Run **36661808598** després del verificador agregat i integració go/no-go: **651 passed, 0 failed**.
- Run **36664237975** després dels deep-links de reparació a factura/AEAT: **670 passed, 0 failed**.
- Run **36664788129** després del validador final d'evidències i reconciliació de regressions paral·leles: **677 passed, 0 failed**.
- Run **36647777483** · Intranet AO batch checks: **success**.
- Run **36732555122** · regressió completa sobre `main` `e2fd82215...`: **740 passed, 0 failed**; 61 PASS relacionats amb incidències/UC-008.
- Run **36942641296** · gate UC-008 de tres evidències + preparador/verificador E2E gestor: **837 passed, 0 failed**. Inclou 8 proves del validador final, 5 del preparador sintètic i 3 del verificador read-only del gestor.
- Run **36943995075** · revalidació posterior al fix del journal multirol i regressió del tall executable aplicable a `main`: **844 passed, 0 failed**; **74 PASS relacionats amb incidències/UC-008**.
## 14. CI automatitzada

S'ha afegit `.github/workflows/sif-tests.yml` per executar `php sif/tests/run-tests.php` amb PHP 8.4 i MySQL 8.4 en pull requests, canvis a `main` que afectin `sif/**` i execució manual (`workflow_dispatch`).

La suite SIF i els checks d'intranet ja disposen d'evidència CI satisfactòria després de la implementació de la UI. Continuen pendents E2E/preproducció i configuració productiva abans de marcar el panell verificat en runtime.

**Estat actual:** `AUDIT_CLOSED + CODE_COMPLETE + CI_844_0`. Backend/UI/preflight/E2E tècnic/concurrència/readiness/deep-links/evidence-gate estan verificats al repositori. `ENVIRONMENT_ACCEPTANCE_PENDING`: execució real de preproducció, configuració/comprovació del menú BD i tres evidències reals.


Vegeu també [Evidència E2E del flux gestor](08-evidencia-gestor-uc-008.md).
