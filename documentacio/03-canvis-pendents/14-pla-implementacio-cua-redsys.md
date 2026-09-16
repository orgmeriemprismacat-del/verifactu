# Cua asincrona Redsys - Pla d'implementacio

> **Per a agents d'implementacio:** SUB-SKILL OBLIGATORIA: usar `superpowers:subagent-driven-development` (recomanat) o `superpowers:executing-plans` per executar aquest pla targeta per targeta. Els passos fan servir checkboxes (`- [ ]`) per registrar el progres.

**Objectiu:** Fer que el callback Redsys persisteixi una notificacio i un job durable, i que un worker separat emeti factura i pagament de manera idempotent per curs, pack, grup, regal o USOC.

**Arquitectura:** `redsys_payment_intent` fixa el context anterior al TPV, `redsys_notifications` conserva l'entrada signada i `redsys_callback_queue` controla execucio, bloqueig i reintents. El callback nomes valida i encola dins una transaccio curta; el worker consumeix el snapshot immutable i desa els UUID resultants.

**Stack tecnic:** PHP 8.x sense Composer, PDO MySQL/MariaDB, OpenSSL, runner propi `sif/tests/run-tests.php`, SQL de migracions MySQL.

---

## Condicions abans de comencar

- PHP ha d'estar disponible al `PATH` amb `openssl` i `pdo_mysql`.
- `SIF_ENV=test` i `SIF_DB_DSN` han d'apuntar a una base el nom de la qual contingui `test`.
- Cal executar `php sif/tests/run-tests.php` i conservar el recompte base abans del primer canvi.
- No s'ha d'executar cap migracio ni worker amb `SIF_ENV=production`.
- Els passos de commit d'aquest pla nomes s'executen si l'usuari ho autoritza explicitament. No es fa `push` automatic.

## Mapa de fitxers

| Fitxer | Responsabilitat |
|---|---|
| `sif/src/Repository/RedsysPaymentIntentRepository.php` | Persistencia i bloqueig de la intencio. |
| `sif/src/Service/RedsysPaymentIntentService.php` | Validacio i creacio idempotent de la intencio. |
| `sif/database/migrations/2026_06_19_000003_create_redsys_callback_queue.sql` | Taula durable de jobs. |
| `sif/src/Repository/RedsysCallbackQueueRepository.php` | Encolar, reclamar i finalitzar jobs. |
| `sif/src/Service/RedsysJobProcessor.php` | Contracte minim que el worker pot executar. |
| `sif/src/Service/RedsysIntentHandler.php` | Interficie comuna dels cinc orquestradors per snapshot. |
| `sif/src/Service/RedsysCallbackDispatcher.php` | Seleccio de l'orquestrador segons `SOURCE_TYPE`. |
| `sif/src/Service/RedsysCallbackWorker.php` | Execucio, reintents i incidencies. |
| `sif/src/Service/RedsysCallbackService.php` | Transaccio curta de recepcio i encolat. |
| `sif/src/Repository/RedsysNotificationRepository.php` | Notificacio immutable i duplicats coherents. |
| `sif/src/Service/RedsysSignatureValidator.php` | Camps signats normalitzats i versio admesa. |
| `sif/scripts/process-redsys-callback-queue.php` | Worker CLI. |
| `sif/scripts/preflight-redsys-callback-queue.php` | Comprovacions de nomes lectura. |
| `sif/tests/Support/TestDatabase.php` | Aplicacio de totes les migracions i neteja de taules. |

## Targeta 1 de 9 - Finalitzar mapping `DS_ORDER -> payment intent`

**Fitxers:**
- Crear: `sif/src/Repository/RedsysPaymentIntentRepository.php`
- Crear: `sif/src/Service/RedsysPaymentIntentService.php`
- Crear: `sif/tests/Integration/RedsysPaymentIntentTest.php`
- Modificar: `sif/tests/Support/TestDatabase.php`

- [ ] **Pas 1: escriure la prova fallida de creacio i relectura**

```php
public function testCreatesAndReloadsIntentByDsOrder(): void
{
    $db = TestDatabase::fresh();
    $service = new RedsysPaymentIntentService(
        new RedsysPaymentIntentRepository(),
        new UuidGenerator()
    );

    $created = $service->create($db, [
        'ds_order' => 'ORDERINTENT1',
        'idpag' => 700,
        'source_type' => 'CURS',
        'source_id' => '700',
        'expected_amount' => '120.00',
        'currency' => 'EUR',
        'terminal' => '1',
        'snapshot' => ['billing' => ['tax_id' => '12345678Z']],
        'created_by' => 'test',
    ]);

    $loaded = (new RedsysPaymentIntentRepository())->findByDsOrder($db, 'ORDERINTENT1');
    Assert::same($created['uuid_intent'], $loaded['UUID_INTENT']);
    Assert::same('120.00', number_format((float) $loaded['EXPECTED_AMOUNT'], 2, '.', ''));
}
```

- [ ] **Pas 2: executar la prova i confirmar RED**

Executar: `php sif/tests/run-tests.php`

Resultat esperat: `FAIL` a `RedsysPaymentIntentTest` per classe inexistent, no per connexio o configuracio.

- [ ] **Pas 3: fer que `TestDatabase` apliqui totes les migracions**

Substituir la lectura unica de `2026_06_02_000001_create_sif_core.sql` per:

```php
foreach (glob(dirname(__DIR__, 2) . '/database/migrations/*.sql') ?: [] as $migration) {
    $db->exec(file_get_contents($migration));
}
```

Afegir `redsys_payment_intent` al principi de `TestDatabase::TABLES`, abans de les taules de les quals pugui dependre una migracio posterior.

- [ ] **Pas 4: implementar repositori i servei minims**

API obligatoria del repositori:

```php
public function findByDsOrder(\PDO $db, string $dsOrder, bool $forUpdate = false): ?array;
public function insert(\PDO $db, array $intent): array;
```

API obligatoria del servei:

```php
public function create(\PDO $db, array $input): array;
```

El servei ha de normalitzar import a dos decimals, limitar `SOURCE_TYPE` a `CURS`, `PACK`, `GRUP`, `REGAL` o `USOC_ALUMNE`, exigir snapshot no buit i retornar el registre existent nomes si tots els camps coincideixen. Un `DS_ORDER` existent amb contingut diferent llanca `SifException::conflict()`.

- [ ] **Pas 5: afegir proves de reintent coherent i conflicte**

```php
public function testEquivalentIntentIsReusedAndDifferentAmountConflicts(): void
{
    $db = TestDatabase::fresh();
    $service = new RedsysPaymentIntentService(new RedsysPaymentIntentRepository(), new UuidGenerator());
    $input = [
        'ds_order' => 'ORDERINTENT2',
        'idpag' => 701,
        'source_type' => 'CURS',
        'source_id' => '701',
        'expected_amount' => '120.00',
        'currency' => 'EUR',
        'terminal' => '1',
        'snapshot' => ['billing' => ['tax_id' => '12345678Z']],
    ];

    $first = $service->create($db, $input);
    $second = $service->create($db, $input);
    Assert::same($first['uuid_intent'], $second['uuid_intent']);

    $changed = $input;
    $changed['expected_amount'] = '121.00';
    Assert::throws(SifException::class, fn () => $service->create($db, $changed), 409);
}
```

- [ ] **Pas 6: executar GREEN**

Executar: `php sif/tests/run-tests.php`

Resultat esperat: recompte base mes les noves proves, `0 failed`.

- [ ] **Pas 7: checkpoint Git condicionat a autoritzacio**

```powershell
git add sif/src/Repository/RedsysPaymentIntentRepository.php sif/src/Service/RedsysPaymentIntentService.php sif/tests/Integration/RedsysPaymentIntentTest.php sif/tests/Support/TestDatabase.php
git commit -m "feat(redsys): afegeix servei d'intencions"
```

## Targeta 2 de 9 - Crear `redsys_callback_queue`

**Fitxers:**
- Crear: `sif/database/migrations/2026_06_19_000003_create_redsys_callback_queue.sql`
- Crear: `sif/tests/Database/RedsysCallbackQueueSchemaTest.php`
- Modificar: `sif/tests/Support/TestDatabase.php`

- [ ] **Pas 1: escriure la prova fallida de l'esquema**

```php
public function testQueueMigrationDefinesDurableJobContract(): void
{
    $sql = file_get_contents(dirname(__DIR__, 2) . '/database/migrations/2026_06_19_000003_create_redsys_callback_queue.sql');
    Assert::stringContainsString('CREATE TABLE IF NOT EXISTS redsys_callback_queue', $sql);
    Assert::stringContainsString('UNIQUE KEY uq_redsys_callback_notification (NOTIFICATION_ID)', $sql);
    Assert::stringContainsString('KEY idx_redsys_callback_available (STATUS, AVAILABLE_AT)', $sql);
    Assert::stringContainsString('FOREIGN KEY (UUID_INTENT) REFERENCES redsys_payment_intent(UUID_INTENT)', $sql);
}
```

- [ ] **Pas 2: executar RED**

Executar: `php sif/tests/run-tests.php`

Resultat esperat: `FAIL` per migracio absent.

- [ ] **Pas 3: crear la migracio exacta de l'especificacio 13**

La taula ha d'incloure `UUID_JOB`, `NOTIFICATION_ID`, `UUID_INTENT`, `STATUS`, `ATTEMPTS`, `AVAILABLE_AT`, `LOCKED_AT`, `LOCKED_BY`, `LAST_ERROR`, `RESULT_JSON`, `UUID_FACTURA`, `UUID_PAYMENT`, `PROCESSED_AT`, `CREATED_AT` i `UPDATED_AT`, amb les claus i FKs descrites a `13-cua-asincrona-callbacks-redsys.md`.

- [ ] **Pas 4: incorporar la taula a la neteja de test**

Afegir `redsys_callback_queue` abans de `redsys_payment_intent` a `TestDatabase::TABLES`, ja que referencia intencio i notificacio.

- [ ] **Pas 5: executar GREEN i migracio de test**

Executar:

```powershell
php sif/tests/run-tests.php
$env:SIF_ENV='test'; php sif/scripts/run-migrations.php
```

Resultat esperat: proves amb `0 failed` i linia `Migrated sif/database/migrations/2026_06_19_000003_create_redsys_callback_queue.sql`.

- [ ] **Pas 6: checkpoint Git condicionat a autoritzacio**

```powershell
git add sif/database/migrations/2026_06_19_000003_create_redsys_callback_queue.sql sif/tests/Database/RedsysCallbackQueueSchemaTest.php sif/tests/Support/TestDatabase.php
git commit -m "feat(redsys): crea cua durable de callbacks"
```

## Targeta 3 de 9 - Fer que el callback generi jobs

**Fitxers:**
- Crear: `sif/src/Repository/RedsysCallbackQueueRepository.php`
- Modificar: `sif/src/Repository/RedsysNotificationRepository.php`
- Modificar: `sif/src/Service/RedsysCallbackService.php`
- Modificar: `sif/public/api/redsys/callback.php`
- Modificar: `sif/tests/Integration/RedsysCallbackTest.php`

- [ ] **Pas 1: escriure la prova fallida de callback autoritzat encolat**

```php
public function testAuthorizedCallbackCreatesOneJobWithoutIssuingInvoice(): void
{
    $db = TestDatabase::fresh();
    $intent = $this->createIntent($db, 'ORDERQUEUE1', 'CURS', '80.00');
    $service = $this->callbackService();

    $result = $service->receiveCallback($db, [
        'ds_order' => 'ORDERQUEUE1',
        'amount' => '80.00',
        'response_code' => '0000',
        'currency' => 'EUR',
        'currency_code' => '978',
        'terminal' => '1',
        'signature_version' => 'HMAC_SHA256_V1',
        'payload_hash' => str_repeat('a', 64),
    ], true);

    Assert::same('QUEUED', $result['queue_status']);
    Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM redsys_callback_queue')->fetchColumn());
    Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
}

private function createIntent(\PDO $db, string $dsOrder, string $sourceType, string $amount): array
{
    return (new RedsysPaymentIntentService(
        new RedsysPaymentIntentRepository(),
        new UuidGenerator()
    ))->create($db, [
        'ds_order' => $dsOrder,
        'idpag' => 700,
        'source_type' => $sourceType,
        'source_id' => '700',
        'expected_amount' => $amount,
        'currency' => 'EUR',
        'terminal' => '1',
        'snapshot' => ['billing' => ['tax_id' => '12345678Z']],
    ]);
}

private function callbackService(): RedsysCallbackService
{
    return new RedsysCallbackService(
        new RedsysPaymentIntentRepository(),
        new RedsysNotificationRepository(),
        new RedsysCallbackQueueRepository(new UuidGenerator())
    );
}
```

- [ ] **Pas 2: executar RED**

Executar: `php sif/tests/run-tests.php`

Resultat esperat: `FAIL` perquè `RedsysCallbackService` encara no rep repositori d'intencions/cua ni retorna `queue_status`.

- [ ] **Pas 3: implementar `enqueue()` idempotent**

API del repositori:

```php
public function enqueue(\PDO $db, int $notificationId, string $uuidIntent): array;
public function findByNotificationId(\PDO $db, int $notificationId): ?array;
```

`enqueue()` genera `UUID_JOB` amb `UuidGenerator`, inserta `QUEUED` i, davant clau unica, rellegeix el job de la mateixa notificacio.

- [ ] **Pas 4: convertir recepcio, notificacio i job en una transaccio curta**

Constructor objectiu:

```php
public function __construct(
    private RedsysPaymentIntentRepository $intents,
    private RedsysNotificationRepository $notifications,
    private RedsysCallbackQueueRepository $queue
) {
}
```

`receiveAuthorizedCallback()` ha de fer `beginTransaction()`, bloquejar intencio per `DS_ORDER`, comparar import/divisa/terminal, registrar notificacio, encolar nomes respostes `0000`-`0099`, confirmar i retornar. En excepcio, fa rollback.

- [ ] **Pas 5: provar denegat i duplicat**

```php
$service = $this->callbackService();
$denied = [
    'ds_order' => 'ORDERQUEUE2',
    'amount' => '80.00',
    'response_code' => '0101',
    'currency' => 'EUR',
    'currency_code' => '978',
    'terminal' => '1',
    'signature_version' => 'HMAC_SHA256_V1',
    'payload_hash' => str_repeat('b', 64),
];
$this->createIntent($db, 'ORDERQUEUE2', 'CURS', '80.00');
$service->receiveCallback($db, $denied, true);
Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM redsys_callback_queue')->fetchColumn());

$authorized = $denied;
$authorized['ds_order'] = 'ORDERQUEUE3';
$authorized['response_code'] = '0000';
$authorized['payload_hash'] = str_repeat('c', 64);
$this->createIntent($db, 'ORDERQUEUE3', 'CURS', '80.00');
$service->receiveCallback($db, $authorized, true);
$service->receiveCallback($db, $authorized, true);
Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM redsys_callback_queue')->fetchColumn());
```

- [ ] **Pas 6: actualitzar l'endpoint**

L'endpoint ha de construir els tres repositoris i deixar d'utilitzar `$_GET` com a font d'`IDPAG`. L'unic context extern al POST signat que es permet es metainformacio tecnica no fiscal.

- [ ] **Pas 7: executar GREEN**

Executar: `php sif/tests/run-tests.php`

Resultat esperat: `0 failed`, una notificacio i un job per callback autoritzat equivalent.

- [ ] **Pas 8: checkpoint Git condicionat a autoritzacio**

```powershell
git add sif/src/Repository/RedsysCallbackQueueRepository.php sif/src/Repository/RedsysNotificationRepository.php sif/src/Service/RedsysCallbackService.php sif/public/api/redsys/callback.php sif/tests/Integration/RedsysCallbackTest.php
git commit -m "feat(redsys): encola callbacks autoritzats"
```

## Targeta 4 de 9 - Implementar el worker

**Fitxers:**
- Crear: `sif/src/Service/RedsysJobProcessor.php`
- Crear: `sif/src/Service/RedsysCallbackWorker.php`
- Modificar: `sif/src/Repository/RedsysCallbackQueueRepository.php`
- Crear: `sif/tests/Integration/RedsysCallbackWorkerTest.php`

- [ ] **Pas 1: escriure la prova fallida de reclamacio unica**

```php
public function testWorkerClaimsQueuedJobOnce(): void
{
    $db = TestDatabase::fresh();
    $job = $this->queuedJob($db, 'ORDERWORK1');
    $queue = new RedsysCallbackQueueRepository(new UuidGenerator());

    $claimed = $queue->claimNext($db, 'worker-a', new \DateTimeImmutable('2026-06-19 10:00:00'));
    Assert::same($job['UUID_JOB'], $claimed['UUID_JOB']);
    Assert::same('PROCESSING', $claimed['STATUS']);
    Assert::same(1, (int) $claimed['ATTEMPTS']);
    Assert::same(null, $queue->claimNext($db, 'worker-b', new \DateTimeImmutable('2026-06-19 10:00:00')));
}

private function queuedJob(\PDO $db, string $dsOrder): array
{
    $intent = (new RedsysPaymentIntentService(
        new RedsysPaymentIntentRepository(),
        new UuidGenerator()
    ))->create($db, [
        'ds_order' => $dsOrder,
        'idpag' => 700,
        'source_type' => 'CURS',
        'source_id' => '700',
        'expected_amount' => '80.00',
        'currency' => 'EUR',
        'terminal' => '1',
        'snapshot' => ['billing' => ['tax_id' => '12345678Z']],
    ]);
    $notification = (new RedsysNotificationRepository())->recordReceived(
        $db,
        $dsOrder,
        700,
        '80.00',
        '0000',
        true,
        ['source' => 'test'],
        'VALIDATED'
    );

    return (new RedsysCallbackQueueRepository(new UuidGenerator()))->enqueue(
        $db,
        (int) $notification['id'],
        $intent['uuid_intent']
    );
}
```

Contracte del processador injectat al worker:

```php
interface RedsysJobProcessor
{
    public function process(\PDO $sifDb, array $job): array;
}
```

Helper reutilitzable a `RedsysCallbackWorkerTest.php` per a les targetes 4, 6 i 7:

```php
final class OutcomeRedsysJobProcessor implements RedsysJobProcessor
{
    public function __construct(private array|\Throwable $outcome)
    {
    }

    public function process(\PDO $sifDb, array $job): array
    {
        if ($this->outcome instanceof \Throwable) {
            throw $this->outcome;
        }

        return $this->outcome;
    }
}

private function workerWithOutcome(array|\Throwable $outcome): RedsysCallbackWorker
{
    return new RedsysCallbackWorker(
        new RedsysCallbackQueueRepository(new UuidGenerator()),
        new OutcomeRedsysJobProcessor($outcome),
        new IncidentRepository(),
        5
    );
}
```

- [ ] **Pas 2: executar RED**

Executar: `php sif/tests/run-tests.php`

Resultat esperat: `FAIL` per metode `claimNext` inexistent.

- [ ] **Pas 3: implementar reclamacio curta amb bloqueig**

`claimNext()` ha de:

```sql
SELECT q.*, n.DS_ORDER, i.SOURCE_TYPE, i.SOURCE_ID, i.SNAPSHOT_JSON
FROM redsys_callback_queue q
JOIN redsys_notifications n ON n.ID = q.NOTIFICATION_ID
JOIN redsys_payment_intent i ON i.UUID_INTENT = q.UUID_INTENT
WHERE q.STATUS IN ('QUEUED', 'RETRY') AND q.AVAILABLE_AT <= ?
ORDER BY q.AVAILABLE_AT, q.ID
LIMIT 1
FOR UPDATE
```

Despres actualitza el mateix `ID` a `PROCESSING`, incrementa `ATTEMPTS` i fixa `LOCKED_AT`/`LOCKED_BY` abans del commit.

- [ ] **Pas 4: implementar `runOne()`**

API objectiu:

```php
public function runOne(\PDO $db, string $workerId, \DateTimeImmutable $now): ?array;
```

Si no hi ha job retorna `null`. Si hi ha job, confirma la reclamacio abans de cridar el dispatcher.

- [ ] **Pas 5: executar GREEN**

Executar: `php sif/tests/run-tests.php`

Resultat esperat: `0 failed`; un segon worker no reclama el job `PROCESSING`.

- [ ] **Pas 6: checkpoint Git condicionat a autoritzacio**

```powershell
git add sif/src/Service/RedsysJobProcessor.php sif/src/Service/RedsysCallbackWorker.php sif/src/Repository/RedsysCallbackQueueRepository.php sif/tests/Integration/RedsysCallbackWorkerTest.php
git commit -m "feat(redsys): afegeix worker de callbacks"
```

## Targeta 5 de 9 - Crear el dispatcher per origen

**Fitxers:**
- Crear: `sif/src/Service/RedsysIntentHandler.php`
- Crear: `sif/src/Service/RedsysCallbackDispatcher.php`
- Modificar: `sif/src/Service/RedsysCourseInvoiceService.php`
- Modificar: `sif/src/Service/RedsysPackInvoiceService.php`
- Modificar: `sif/src/Service/RedsysGroupInvoiceService.php`
- Modificar: `sif/src/Service/RedsysGiftInvoiceService.php`
- Modificar: `sif/src/Service/RedsysUsocInvoiceService.php`
- Crear: `sif/tests/Integration/RedsysCallbackDispatcherTest.php`

- [ ] **Pas 1: escriure la prova fallida de rutes**

```php
final class RecordingRedsysIntentHandler implements RedsysIntentHandler
{
    public function __construct(private string $type, private \ArrayObject $calls)
    {
    }

    public function sourceType(): string
    {
        return $this->type;
    }

    public function issueFromIntentSnapshot(\PDO $sifDb, string $dsOrder, array $snapshot): array
    {
        $this->calls->append($this->type);

        return ['ok' => true, 'uuid_factura' => $dsOrder, 'uuid_payment' => $dsOrder];
    }
}

public function testRoutesEverySupportedSourceType(): void
{
    $db = TestDatabase::fresh();
    $calls = new \ArrayObject();
    $types = ['CURS', 'PACK', 'GRUP', 'REGAL', 'USOC_ALUMNE'];
    $handlers = array_map(
        fn (string $type): RedsysIntentHandler => new RecordingRedsysIntentHandler($type, $calls),
        $types
    );
    $dispatcher = new RedsysCallbackDispatcher($handlers);

    foreach ($types as $type) {
        $dispatcher->process($db, [
            'DS_ORDER' => 'ORDER-' . $type,
            'SOURCE_TYPE' => $type,
            'SOURCE_ID' => $type === 'REGAL' ? '77' : '700',
            'SNAPSHOT_JSON' => json_encode(['source_type' => $type]),
        ]);
    }

    Assert::same($types, $calls->getArrayCopy());
}
```

- [ ] **Pas 2: executar RED**

Executar: `php sif/tests/run-tests.php`

Resultat esperat: `FAIL` per dispatcher inexistent.

- [ ] **Pas 3: afegir entrada per snapshot als cinc orquestradors**

Interficie comuna:

```php
interface RedsysIntentHandler
{
    public function sourceType(): string;

    public function issueFromIntentSnapshot(\PDO $sifDb, string $dsOrder, array $snapshot): array;
}
```

Els cinc serveis implementen `RedsysIntentHandler` i aquesta signatura d'execucio:

```php
public function issueFromIntentSnapshot(\PDO $sifDb, string $dsOrder, array $snapshot): array;
```

Cada metode usa el builder especialitzat i `RedsysInvoicePayloadBuilder`, pero no rep `legacyDb` ni torna a consultar legacy. Els metodes manuals `issueFromValidatedNotification()` es mantenen per preproduccio.

Regles especials:

```php
// REGAL
$giftId = (int) ($snapshot['gift']['ID'] ?? $snapshot['gift']['id'] ?? 0);
if ($giftId <= 0) {
    throw SifException::validation('Invalid Redsys gift snapshot ID');
}

// USOC_ALUMNE
$entityAmount = $snapshot['usoc']['entity_amount'] ?? null;
if ($entityAmount === null || !is_numeric($entityAmount) || (float) $entityAmount <= 0) {
    throw SifException::validation('Invalid Redsys USOC entity amount snapshot');
}
```

- [ ] **Pas 4: implementar registre exhaustiu del dispatcher**

```php
final class RedsysCallbackDispatcher implements RedsysJobProcessor
{
    private array $handlers = [];

public function __construct(iterable $handlers)
{
    foreach ($handlers as $handler) {
        if (!$handler instanceof RedsysIntentHandler) {
            throw new \InvalidArgumentException('Invalid Redsys intent handler');
        }

        $type = $handler->sourceType();
        if (isset($this->handlers[$type])) {
            throw new \InvalidArgumentException('Duplicate Redsys intent handler');
        }

        $this->handlers[$type] = $handler;
    }
}

public function process(\PDO $sifDb, array $job): array
{
    $sourceType = strtoupper(trim((string) ($job['SOURCE_TYPE'] ?? '')));
    $handler = $this->handlers[$sourceType] ?? null;
    if ($handler === null) {
        throw SifException::validation('Unsupported Redsys source type');
    }

    $snapshot = json_decode((string) ($job['SNAPSHOT_JSON'] ?? ''), true);
    if (!is_array($snapshot)) {
        throw SifException::validation('Invalid Redsys intent snapshot');
    }

    return $handler->issueFromIntentSnapshot($sifDb, (string) $job['DS_ORDER'], $snapshot);
}
}
```

- [ ] **Pas 5: provar que el worker no consulta legacy**

La prova crea un snapshot complet, no configura `SIF_LEGACY_DB_DSN` i exigeix que el dispatcher arribi a `InvoiceService` sense obrir connexio legacy.

- [ ] **Pas 6: executar GREEN**

Executar: `php sif/tests/run-tests.php`

Resultat esperat: `0 failed` i cinc rutes cobertes.

- [ ] **Pas 7: checkpoint Git condicionat a autoritzacio**

```powershell
git add sif/src/Service/RedsysIntentHandler.php sif/src/Service/RedsysCallbackDispatcher.php sif/src/Service/RedsysCourseInvoiceService.php sif/src/Service/RedsysPackInvoiceService.php sif/src/Service/RedsysGroupInvoiceService.php sif/src/Service/RedsysGiftInvoiceService.php sif/src/Service/RedsysUsocInvoiceService.php sif/tests/Integration/RedsysCallbackDispatcherTest.php
git commit -m "feat(redsys): enruta jobs des del snapshot"
```

## Targeta 6 de 9 - Persistir el resultat

**Fitxers:**
- Modificar: `sif/src/Repository/RedsysCallbackQueueRepository.php`
- Modificar: `sif/src/Service/RedsysCallbackWorker.php`
- Modificar: `sif/tests/Integration/RedsysCallbackWorkerTest.php`

- [ ] **Pas 1: escriure la prova fallida de finalitzacio**

```php
public function testSuccessfulWorkerPersistsInvoiceAndPaymentResult(): void
{
    $db = TestDatabase::fresh();
    $this->queuedJob($db, 'ORDERRESULT1');
    $worker = $this->workerWithOutcome([
        'ok' => true,
        'uuid_factura' => '11111111-1111-4111-8111-111111111111',
        'uuid_payment' => '22222222-2222-4222-8222-222222222222',
        'num_visible' => 'A2026/1',
    ]);

    $worker->runOne($db, 'worker-a', new \DateTimeImmutable('2026-06-19 10:00:00'));
    $job = $db->query('SELECT * FROM redsys_callback_queue')->fetch(\PDO::FETCH_ASSOC);

    Assert::same('PROCESSED', $job['STATUS']);
    Assert::same('11111111-1111-4111-8111-111111111111', $job['UUID_FACTURA']);
    Assert::same('22222222-2222-4222-8222-222222222222', $job['UUID_PAYMENT']);
}
```

- [ ] **Pas 2: executar RED**

Executar: `php sif/tests/run-tests.php`

Resultat esperat: `FAIL` perquè el worker encara no marca `PROCESSED`.

- [ ] **Pas 3: implementar `markProcessed()`**

```php
public function markProcessed(\PDO $db, int $id, array $result, \DateTimeImmutable $now): void
{
    $db->prepare(
        'UPDATE redsys_callback_queue
         SET STATUS = \'PROCESSED\', RESULT_JSON = ?, UUID_FACTURA = ?, UUID_PAYMENT = ?,
             PROCESSED_AT = ?, LOCKED_AT = NULL, LOCKED_BY = NULL, LAST_ERROR = NULL
         WHERE ID = ? AND STATUS = \'PROCESSING\''
    )->execute([
        json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $result['uuid_factura'] ?? null,
        $result['uuid_payment'] ?? null,
        $now->format('Y-m-d H:i:s'),
        $id,
    ]);
}
```

Cal exigir una fila actualitzada; zero files implica conflicte de propietat del job.

- [ ] **Pas 4: executar GREEN**

Executar: `php sif/tests/run-tests.php`

Resultat esperat: `0 failed`, UUID i `RESULT_JSON` persistits.

- [ ] **Pas 5: checkpoint Git condicionat a autoritzacio**

```powershell
git add sif/src/Repository/RedsysCallbackQueueRepository.php sif/src/Service/RedsysCallbackWorker.php sif/tests/Integration/RedsysCallbackWorkerTest.php
git commit -m "feat(redsys): persisteix resultat del worker"
```

## Targeta 7 de 9 - Implementar reintents i incidencies

**Fitxers:**
- Modificar: `sif/src/Repository/RedsysCallbackQueueRepository.php`
- Modificar: `sif/src/Service/RedsysCallbackWorker.php`
- Modificar: `sif/tests/Integration/RedsysCallbackWorkerTest.php`

- [ ] **Pas 1: escriure proves fallides de RETRY i INCIDENT**

```php
public function testTechnicalFailureSchedulesRetry(): void
{
    $db = TestDatabase::fresh();
    $this->queuedJob($db, 'ORDERRETRY1');
    $worker = $this->workerWithOutcome(new \PDOException('temporary connection failure'));
    $worker->runOne($db, 'worker-a', new \DateTimeImmutable('2026-06-19 10:00:00'));
    $job = $db->query('SELECT * FROM redsys_callback_queue')->fetch(\PDO::FETCH_ASSOC);
    Assert::same('RETRY', $job['STATUS']);
    Assert::same('2026-06-19 10:01:00', $job['AVAILABLE_AT']);
}

public function testFunctionalConflictBecomesIncidentWithoutRetry(): void
{
    $db = TestDatabase::fresh();
    $this->queuedJob($db, 'ORDERINCIDENT1');
    $worker = $this->workerWithOutcome(SifException::conflict('amount mismatch'));
    $worker->runOne($db, 'worker-a', new \DateTimeImmutable('2026-06-19 10:00:00'));
    $job = $db->query('SELECT * FROM redsys_callback_queue')->fetch(\PDO::FETCH_ASSOC);
    Assert::same('INCIDENT', $job['STATUS']);
    Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM errors_verifactu WHERE ESTAT = \'OPEN\'')->fetchColumn());
}
```

- [ ] **Pas 2: executar RED**

Executar: `php sif/tests/run-tests.php`

Resultat esperat: `FAIL` perquè no existeixen `markRetry`/`markIncident`.

- [ ] **Pas 3: implementar classificacio i backoff**

```php
private function retryDelayMinutes(int $attempts): int
{
    return match ($attempts) {
        1 => 1,
        2 => 5,
        3 => 15,
        default => 60,
    };
}
```

`SifException` amb codi 409 o 422 passa directament a `INCIDENT`. Altres excepcions passen a `RETRY` mentre `ATTEMPTS < 5`; al cinque intent passen a `INCIDENT`.

- [ ] **Pas 4: implementar recuperacio de bloqueig caducat**

```sql
UPDATE redsys_callback_queue
SET STATUS = 'RETRY', AVAILABLE_AT = ?, LOCKED_AT = NULL, LOCKED_BY = NULL,
    LAST_ERROR = 'Recovered stale processing lock'
WHERE STATUS = 'PROCESSING' AND LOCKED_AT < ?
```

El llindar es `now - 15 minutes`.

- [ ] **Pas 5: obrir incidencia amb context de job**

Usar `IncidentRepository::open()` amb `UUID_FACTURA` nullable, tipus `REDSYS_CALLBACK` i un JSON a `DETAILS` que inclogui `ds_order`, `uuid_job`, `attempts`, classe d'excepcio i missatge.

- [ ] **Pas 6: executar GREEN**

Executar: `php sif/tests/run-tests.php`

Resultat esperat: `0 failed`; backoff 1/5/15/60, maxim cinc intents i recuperacio de lock coberts.

- [ ] **Pas 7: checkpoint Git condicionat a autoritzacio**

```powershell
git add sif/src/Repository/RedsysCallbackQueueRepository.php sif/src/Service/RedsysCallbackWorker.php sif/tests/Integration/RedsysCallbackWorkerTest.php
git commit -m "feat(redsys): afegeix reintents i incidencies"
```

## Targeta 8 de 9 - Validar duplicats i contradiccions

**Fitxers:**
- Crear: `sif/database/migrations/2026_06_19_000004_harden_redsys_notifications.sql`
- Modificar: `sif/src/Service/RedsysSignatureValidator.php`
- Modificar: `sif/src/Repository/RedsysNotificationRepository.php`
- Modificar: `sif/src/Service/RedsysCallbackService.php`
- Modificar: `sif/tests/Unit/RedsysSignatureValidatorTest.php`
- Modificar: `sif/tests/Integration/RedsysCallbackTest.php`

- [ ] **Pas 1: escriure proves fallides de camps signats**

```php
$validator = new RedsysSignatureValidator('MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3');
$payload = $validator->decodeAndVerify([
    'Ds_SignatureVersion' => 'HMAC_SHA256_V1',
    'Ds_MerchantParameters' => 'eyJEc19PcmRlciI6Ik9SREVSMTIzIiwiRHNfQW1vdW50IjoiMTIwMDAiLCJEc19SZXNwb25zZSI6IjAwMDAiLCJEc19DdXJyZW5jeSI6Ijk3OCIsIkRzX1Rlcm1pbmFsIjoiMSIsIkRzX0RhdGUiOiIwNi8wNi8yMDI2IiwiRHNfSG91ciI6IjEwOjMwIn0=',
    'Ds_Signature' => 'Sf9vai8reepW5G-M5aE8DEs6Z6UAfeRIhYh8oXS6110=',
]);

Assert::same('978', $payload['currency_code']);
Assert::same('EUR', $payload['currency']);
Assert::same('1', $payload['terminal']);
Assert::same('HMAC_SHA256_V1', $payload['signature_version']);
Assert::same('8d4b744ee7c64f817594c7102b10d191ed99a26619a9f5da4501539984d079e1', $payload['payload_hash']);
Assert::same(false, array_key_exists('idpag', $payload));
```

La fixture signada inclou `Ds_Currency = 978` i `Ds_Terminal = 1`. El validator ha de conservar `currency_code = 978`, normalitzar `currency = EUR` per comparar amb `redsys_payment_intent.CURRENCY` i rebutjar codis no admesos. La prova tambe exigeix que una versio diferent de `HMAC_SHA256_V1` sigui rebutjada amb 422.

- [ ] **Pas 2: executar RED**

Executar: `php sif/tests/run-tests.php`

Resultat esperat: `FAIL` perquè el validator encara retorna `idpag` de context i no normalitza divisa/terminal/hash.

- [ ] **Pas 3: crear migracio de notificacio normalitzada**

```sql
ALTER TABLE redsys_notifications
    ADD COLUMN CURRENCY_CODE VARCHAR(3) NULL AFTER IMPORT,
    ADD COLUMN TERMINAL VARCHAR(20) NULL AFTER CURRENCY_CODE,
    ADD COLUMN SIGNATURE_VERSION VARCHAR(30) NULL AFTER SIGNATURE_VALID,
    ADD COLUMN PAYLOAD_HASH CHAR(64) NULL AFTER SIGNATURE_VERSION;
```

Despres de desplegar codi i verificar callbacks nous, aquests camps es podran endurir a `NOT NULL` en una migracio posterior; no es fa en aquest tall per compatibilitat amb notificacions historiques.

- [ ] **Pas 4: comparar duplicat contra registre persistent**

La comparacio minima es:

```php
private function sameNotification(array $existing, array $candidate): bool
{
    return number_format((float) $existing['IMPORT'], 2, '.', '') === $candidate['amount']
        && (string) $existing['RESPONSE_CODE'] === $candidate['response_code']
        && (string) $existing['CURRENCY_CODE'] === $candidate['currency_code']
        && (string) $existing['TERMINAL'] === $candidate['terminal']
        && (string) $existing['SIGNATURE_VERSION'] === $candidate['signature_version']
        && hash_equals((string) $existing['PAYLOAD_HASH'], $candidate['payload_hash']);
}
```

Només l'error MySQL 1062 sobre la clau `DS_ORDER` activa aquesta relectura. Altres SQLSTATE `23000` es tornen a llancar.

- [ ] **Pas 5: provar contradiccio i concurrencia**

Un segon callback amb import o resposta diferents ha de llancar conflicte 409, conservar un sol job i obrir incidencia. Dues insercions equivalents han de retornar el mateix `UUID_JOB`.

- [ ] **Pas 6: executar GREEN**

Executar: `php sif/tests/run-tests.php`

Resultat esperat: `0 failed`, cap `IDPAG` de query string i duplicats contradictoris bloquejats.

- [ ] **Pas 7: checkpoint Git condicionat a autoritzacio**

```powershell
git add sif/database/migrations/2026_06_19_000004_harden_redsys_notifications.sql sif/src/Service/RedsysSignatureValidator.php sif/src/Repository/RedsysNotificationRepository.php sif/src/Service/RedsysCallbackService.php sif/tests/Unit/RedsysSignatureValidatorTest.php sif/tests/Integration/RedsysCallbackTest.php
git commit -m "fix(redsys): valida duplicats signats"
```

## Targeta 9 de 9 - Provar i operar el circuit complet

**Fitxers:**
- Crear: `sif/scripts/process-redsys-callback-queue.php`
- Crear: `sif/scripts/preflight-redsys-callback-queue.php`
- Crear: `sif/tests/Integration/RedsysAsyncFlowTest.php`
- Crear: `sif/tests/Integration/RedsysCallbackQueueScriptTest.php`
- Modificar: `sif/scripts/go-no-go-preproduction.php`
- Modificar: `documentacio/05-governanca-operacio/20-pla-proves-validacio-sif.md`

- [ ] **Pas 1: escriure prova fallida del CLI segur**

```php
public function testWorkerScriptRefusesProductionAndUsesAsyncWorker(): void
{
    $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/process-redsys-callback-queue.php');
    Assert::stringContainsString("SIF_ENV=production", $source);
    Assert::stringContainsString('RedsysCallbackWorker', $source);
    Assert::stringContainsString('--limit=', $source);
    Assert::stringContainsString('--worker-id=', $source);
}
```

- [ ] **Pas 2: executar RED**

Executar: `php sif/tests/run-tests.php`

Resultat esperat: `FAIL` perquè els scripts encara no existeixen.

- [ ] **Pas 3: implementar worker CLI finit**

Contracte d'entrada:

```text
php sif/scripts/process-redsys-callback-queue.php --limit=25 --worker-id=pay-prisma-1
```

El script rebutja `production`, valida `limit` entre 1 i 100, recupera locks caducats, executa com a maxim `limit` jobs i imprimeix JSON amb `claimed`, `processed`, `retried` i `incidents`.

- [ ] **Pas 4: implementar preflight de nomes lectura**

Ha de comprovar entorn, OpenSSL, PDO MySQL, connexio SIF, les tres taules Redsys, camps normalitzats, fitxer del worker i configuracio de clau Redsys. No crea intencions, jobs, factures ni pagaments.

- [ ] **Pas 5: prova integral amb factura i pagament reals**

La prova `RedsysAsyncFlowTest` ha de:

1. crear intencio `CURS` amb snapshot complet;
2. enviar callback autoritzat al servei;
3. comprovar que abans del worker no hi ha factura ni pagament;
4. executar `runOne()`;
5. comprovar una factura, un `payment_transaction` i job `PROCESSED`;
6. tornar a executar el mateix job/reintent i comprovar els mateixos UUID;
7. repetir assertions equivalents per `PACK`, `GRUP`, `REGAL` i `USOC_ALUMNE`.

- [ ] **Pas 6: prova de dos workers**

Obrir dues connexions PDO contra la mateixa BD de test. La primera reclama el job; la segona ha de rebre `null`. Despres de finalitzar, el recompte de factura i pagament continua sent 1.

- [ ] **Pas 7: actualitzar go/no-go i pla de proves**

Afegir comprovacions bloquejants per migracions `000003`/`000004`, worker, preflight i taula `redsys_callback_queue`. Documentar evidencies per callback autoritzat, denegat, duplicat, reintent, lock caducat i cinc tipus d'origen.

- [ ] **Pas 8: executar verificacio completa**

Executar:

```powershell
php sif/tests/run-tests.php
$env:SIF_ENV='test'; php sif/scripts/run-migrations.php
php sif/scripts/preflight-redsys-callback-queue.php
php sif/scripts/go-no-go-preproduction.php
git diff --check
```

Resultat esperat: `0 failed`, migracions aplicades, preflight `ok=true`, go/no-go sense bloqueig del circuit Redsys asincron i cap error de whitespace.

- [ ] **Pas 9: revisio manual abans d'activar**

Confirmar que:

- el callback no obre connexio legacy;
- cap job processa `SOURCE_TYPE` desconegut;
- `REGAL` usa ID numeric congelat;
- `USOC_ALUMNE` conserva `entity_invoice_pending`;
- `LegacySyncService` no s'executa des del worker;
- no hi ha cap endpoint o script activat en produccio sense decisio go/no-go.

- [ ] **Pas 10: checkpoint Git condicionat a autoritzacio**

```powershell
git add sif/scripts/process-redsys-callback-queue.php sif/scripts/preflight-redsys-callback-queue.php sif/tests/Integration/RedsysAsyncFlowTest.php sif/tests/Integration/RedsysCallbackQueueScriptTest.php sif/scripts/go-no-go-preproduction.php documentacio/05-governanca-operacio/20-pla-proves-validacio-sif.md
git commit -m "feat(redsys): completa circuit asincron"
```

## Criteri de finalitzacio

La implementacio no es considera acabada fins que les nou targetes estan en verd, `php sif/tests/run-tests.php` informa `0 failed`, dues connexions no poden reclamar el mateix job, un reintent posterior a factura creada recupera els mateixos UUID i el worker no consulta ni modifica legacy.
