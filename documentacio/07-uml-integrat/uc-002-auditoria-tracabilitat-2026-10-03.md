# UC-002 · Auditoria exhaustiva i traçabilitat · 2026-10-03

## 1. Abast i criteri

Auditoria sobre `main@b0e8ff7150c5a8b415cc109d298d82f0db1f68df`, amb canvis aplicats a `audit/uc-002-2026-10-03`.

S’han contrastat:
- fitxa funcional;
- UML integrat;
- PHP/JS real;
- persistència;
- classes, seqüències i activitats ACTUAL/FINAL;
- idempotència;
- auditoria;
- ledger per inscripció;
- proves i CI.

**Regla d’estat:**
- **DOCUMENTAT**: existeix contracte o diagrama.
- **IMPLEMENTAT**: hi ha codi executable/persistència.
- **VERIFICAT**: hi ha evidència de prova executada vigent o comprovació directa suficient.
- **PENDENT**: manca implementació, integració o evidència.

## 2. Inventari de lliurables

| Lliurable | Abans | Després |
|---|---:|---:|
| Fitxa funcional | sí | revisada |
| UML integrat | sí | reconciliat amb auditoria |
| Classes ACTUAL/FINAL | no | sí |
| Seqüències ACTUAL/FINAL | no | sí |
| Activitats per pàgina ACTUAL/FINAL | no | sí |
| Inventari PHP/JS | no | sí |
| Auditoria/traçabilitat datada | no | sí |
| Prova frontera llegada UC-002 | no | sí |

## 3. Matriu executiva

| Àrea | Documentat | Implementat | Verificat | Pendent |
|---|---|---|---|---|
| Nucli `PaymentService` | sí | sí | tests definits + inspecció | CI HEAD actual vermell |
| Idempotència payload v2 | sí | sí | prova específica definida | revalidar CI PR |
| Conservació monetària | sí | **sí, corregit 03/10** | proves unitàries noves | revalidar CI PR |
| Estat de cobrament | sí | sí | proves unitàries existents | cap gap intern detectat |
| Manual payment adapter | sí | sí | tests existents | evidència runtime/pre |
| Intranet GET→POST | sí | **sí, corregit 03/10** | boundary test nou | revalidar CI PR |
| Sessió/origen/rol intranet | sí | **sí, afegit 03/10** | boundary test nou | CSRF token explícit si política l’exigeix |
| Idempotència intranet llegada | sí | no acreditada | no | bloquejant |
| Endpoint SIF autenticat | sí com FINAL | no | no | bloquejant |
| Audit event del registre genèric | sí | infraestructura sí, wiring no | no | bloquejant |
| Ledger per factura | sí | sí | tests existents | — |
| Ledger per `ID_INSC` | sí | model/repositori sí | en fluxos específics | wiring genèric UC-002 |
| Codi real `Intranet::efectuarPagament` | referenciat | **absent al snapshot** | no | bloquejant |
| Evidència externa banc/TPV | sí | no genèrica | no | per canal/cas |
| Resposta JSON tipificada intranet | sí com FINAL | no | no | pendent |

## 4. Troballes i correccions

### F-UC002-01 · Paquet documental incomplet — CORREGIT
Només existien la fitxa funcional i l’UML integrat. S’han creat classes, seqüències, activitats, inventari i auditoria separats seguint el patró dels UC més avançats.

**Estat:** DOCUMENTAT + CORREGIT.

### F-UC002-02 · Fitxa funcional desalineada amb main — CORREGIT DOCUMENTALMENT
La fitxa continuava com `STRUCTURED_DRAFT_NEEDS_CASE_REVIEW / PARTIAL_CODE_AVAILABLE` tot i que el nucli SIF, hash v2, manual adapter, audit infrastructure i ledger per inscripció han evolucionat.

**Estat:** fitxa actualitzada; els buits reals es mantenen explícits.

### F-UC002-03 · El validador acceptava imports zero/negatius — CORREGIT
`PaymentPayloadValidator` només comprovava `is_numeric`.

**Canvi:** import del moviment i cada assignació han de ser estrictament positius i expressables amb màxim dos decimals.

**Proves:** casos zero/negatiu/precisió afegits.

### F-UC002-04 · No es comprovava `SUM(allocations)=amount` — CORREGIT
Això permetia persistir un moviment per un import i distribuir-ne una quantitat diferent.

**Canvi:** conservació exacta en cèntims abans de persistir.

**Proves:** suma incoherent rebutjada; split 70+50 sobre 120 acceptat.

### F-UC002-05 · La mutació llegada utilitzava GET — CORREGIT
`alumnes-pagaments.js` enviava import, data, banc, observacions i dades de factura a `efectuarPagament.php` per GET.

**Canvi:** POST-only, `Allow: POST`, `Cache-Control: no-store`, `$_POST`; les dues còpies de l’endpoint han quedat alineades.

### F-UC002-06 · Endpoint llegat sense control servidor de rol/origen — CORREGIT PARCIALMENT
S’han afegit:
- sessió obligatòria;
- `Sec-Fetch-Site` cross-site reject;
- `Origin/Referer` allowlist;
- `X-Requested-With=XMLHttpRequest`;
- rol de `/alumnes/pagaments/` via `consultaRolsEdiicio` + `tePermisVisualitzacio`;
- validació server-side d’import > 0, data, banc i `efact`.

**Resta:** no s’ha introduït token CSRF sincronitzador. El guard d’origen és una defensa server-side, però si la política exigeix token explícit, continua pendent.

### F-UC002-07 · Implementació central llegada absent — BLOQUEJANT
`codi-drive/intranet-actual/Intranet.php` i la còpia de canvis són buits. No és possible verificar `efectuarPagament()`.

**Impacte:** no es pot provar si el llegat duplica escriptures, quines taules toca, com tracta factura existent, ni si pot reexecutar un cobrament després d’una resposta perduda.

**Acció:** recuperar la font operativa real i incorporar-la al repositori o substituir el camí per un adaptador SIF explícit.

### F-UC002-08 · Idempotència SIF v2 — IMPLEMENTADA
`PaymentService` compara `PAYLOAD_HASH_VERSION`:
- v1: serialització històrica;
- v2: hash canònic;
- versió desconeguda: conflicte;
- mateixa clau + payload diferent: 409.

`PayloadIdempotencyFlowTest` conté casos específics.

**Estat:** IMPLEMENTAT; verificació CI del HEAD pendent perquè main és vermell.

### F-UC002-09 · Idempotència de pantalla llegada — PENDENT BLOQUEJANT
El POST nou no inventa una clau idempotent perquè no hi ha escriptor SIF/llegat auditable on persistir-la i comparar-la.

**FINAL:** request-id/idempotency-key generat abans de mutar, reús equivalent, conflicte si payload diferent, i resposta recuperable si es perd l’HTTP.

### F-UC002-10 · `api/payments/register.php` sense frontera interna segura — PENDENT BLOQUEJANT
El fitxer instancia directament `PaymentService`. No s’hi veu:
- mètode POST-only;
- `InternalApiAuthenticator`;
- HMAC/timestamp/request-id/replay guard;
- allowlist de rols.

Altres endpoints recents (`factures/before-payment.php`, `incidents/manage.php`, `aeat/operations.php`) sí segueixen aquest patró.

**Acció:** crear frontera de comandament UC-002 autenticada abans d’exposar aquest endpoint com a operatiu.

### F-UC002-11 · Infraestructura d’auditoria existent però no wired — PENDENT
`PaymentActionGateway` i `PaymentActionEventRepository` existeixen, però el camí genèric del pagament no els invoca.

**Risc:** la fitxa no pot marcar com a implementada la garantia “cada petició/resultat deixa audit event” per UC-002 genèric.

**Nota d’arquitectura:** no s’ha embolcallat mecànicament `PaymentService` amb `PaymentActionGateway` perquè ambdós gestionen transaccions; cal dissenyar el wiring evitant transaccions imbricades o events terminals inconsistents.

### F-UC002-12 · Ledger per inscripció: model sí, integració genèrica no — PARCIAL
La taula i `EnrollmentFundMovementRepository` ja existeixen i imposen imports positius, ordre, tipus i idempotència.

`PaymentRepository::createPayment()` només crea `payment_allocation` per factura.

**Acció:** quan una factura cobreixi inscripcions, afegir servei d’atribució per `ID_INSC` reconciliat amb el mateix `UUID_PAYMENT`, sense crear un segon `CHARGE`.

### F-UC002-13 · Evidència externa no forma part del nucli — PENDENT PER CANAL
Una clau idempotent no demostra que una transferència o Redsys existeixi. El servei genèric assumeix cobrament confirmat.

**Acció:** cada adaptador ha de validar referència/DS_ORDER/evidència i derivar ambigüitats a conciliació.

### F-UC002-14 · Resposta llegada no és contracte tipificat — PENDENT
El JS interpreta text/HTML i la paraula “error”.

**FINAL:** JSON amb `CREATED`, `REUSED`, `CONFLICT`, `PENDING_RETRY` o `ERROR`, UUID i correlation id.

### F-UC002-15 · CI de main ja era vermell — VERIFICACIÓ BLOQUEJADA
Run `37061206441`, `SIF PHP MySQL tests`, HEAD `b0e8ff...`: `failure`; pas fallit `Executar suite SIF`.

No s’atribueix aquesta fallada al UC-002 sense log. La branca afegeix proves i lint, però el criteri de tancament exigeix separar qualsevol fallada preexistent de regressions UC-002.

## 5. Traçabilitat requisit → codi → prova

| Requisit | Codi | Prova/evidència | Estat |
|---|---|---|---|
| un moviment idempotent | PaymentService + hash v2 | PayloadIdempotencyFlowTest | implementat |
| import positiu | PaymentPayloadValidator | PaymentPayloadValidatorTest | implementat 03/10 |
| suma assignacions = moviment | PaymentPayloadValidator | PaymentPayloadValidatorTest | implementat 03/10 |
| persistir moviment | PaymentRepository | RegisterPaymentTest | implementat |
| recalcular estat | PaymentStatusCalculator/Repository | PaymentStatusCalculatorTest | implementat |
| no reemetre factura | PaymentRepository | RegisterPaymentTest manté 1 registre fiscal | implementat |
| POST a intranet | JS + efectuarPagament.php | Uc002LegacyPaymentBoundaryTest | implementat 03/10 |
| sessió/origen/rol | efectuarPagament.php | boundary test | implementat 03/10 |
| idempotència intranet | — | — | pendent |
| API SIF autenticada | InternalApiAuthenticator existeix, endpoint no l’usa | — | pendent |
| audit event | PaymentActionGateway existeix, wiring no | — | pendent |
| atribució ID_INSC | EnrollmentFundMovementRepository | proves d’altres fluxos | parcial |
| evidència banc/TPV | adaptadors específics | per cas | pendent genèric |
| CI HEAD verd | workflow | main actual failure | pendent |

## 6. Criteris de tancament UC-002

No marcar UC-002 com a tancat mentre falti algun bloquejant:

1. recuperar o substituir el codi real de `Intranet::efectuarPagament()`;
2. connectar la pantalla a un comandament SIF idempotent i retorn tipificat;
3. protegir `api/payments/register.php` amb autenticació/replay/rol o retirar-lo com a frontera operativa;
4. integrar audit events al flux sense transaccions incoherents;
5. decidir i implementar atribució `ID_INSC` per als casos que la requereixen;
6. executar prova de preproducció amb pagament parcial, complet, reintent idempotent, payload conflictiu i fallada de sync;
7. obtenir CI del PR verda o documentar i separar qualsevol fallada preexistent.

## 7. Fitxers modificats/creats per l’auditoria

### Codi
- `sif/src/Service/PaymentPayloadValidator.php`
- `sif/tests/Unit/PaymentPayloadValidatorTest.php`
- `sif/tests/Integration/Uc002LegacyPaymentBoundaryTest.php`
- `codi-drive/intranet-actual/js/alumnes-pagaments.js`
- `codi-drive/intranet-actual/ajax/alumnes/efectuarPagament.php`
- `codi-drive/intranet-nova-canvis-verifactu/ajax/alumnes/efectuarPagament.php`
- `.github/workflows/sif-tests.yml`

### Documentació
- `documentacio/06-fitxes-funcionals/uc-002.md`
- `documentacio/07-uml-integrat/uc-002-registrar-cobrament-factura.md`
- `documentacio/07-uml-integrat/uc-002-classes-actual-final.md`
- `documentacio/07-uml-integrat/uc-002-sequencies-actual-final.md`
- `documentacio/07-uml-integrat/uc-002-activitats-pagines-actual-final.md`
- `documentacio/07-uml-integrat/uc-002-inventari-codi-php-js-actual-final-2026-10-03.md`
- `documentacio/07-uml-integrat/uc-002-auditoria-tracabilitat-2026-10-03.md`
