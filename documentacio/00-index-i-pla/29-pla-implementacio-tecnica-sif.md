# SIF Technical Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar el nucli tecnic del SIF VERI*FACTU PrisMa amb `issueInvoice()`, `registerPayment()`, idempotencia, hash chain global, taules fiscals, pagaments i relacio auditada amb la BD antiga.

**Architecture:** El SIF viu com a servei central a `pay.prisma.cat/sif`: els canals proposen operacions i el SIF decideix factura, numero, hash, registre, cua AEAT, documents i relacions. `issueInvoice()` es l'unic flux que crea factura fiscal i hash chain; `registerPayment()` nomes registra moviments economics sobre factures existents. Les BDs web/intranet es relacionen logicament via `fact_rels`, sense foreign keys entre esquemes.

**Tech Stack:** PHP 8.x sense Composer obligatori, MySQL/InnoDB, SQL transaccional amb `SELECT ... FOR UPDATE`, autoload propi, runner de proves PHP pur, JSON per payload fiscal, SHA-256 per hash intern, servidor `pay.prisma.cat`.

---

## Convencions del pla

Aquest pla es per al repositori real d'implementacio de `pay.prisma.cat` o del SIF. Els camins de codi son relatius a l'arrel d'aquest repositori real:

```text
sif/
  config/
  database/
  public/
  scripts/
  src/
  tests/
```

El projecte pont actual conserva documentacio i control; no s'hi ha d'implementar codi productiu excepte si es decideix explicitament crear-hi un prototip separat.

No fer `commit` ni `push` fins que Meriem ho demani explicitament. Quan el pla digui "checkpoint", vol dir revisar `git diff` i deixar l'estat preparat, no confirmar canvis a Git.

Restriccio d'execucio:

```text
El servidor no ha de requerir composer install.
El SIF ha de poder carregar classes amb sif/src/autoload.php.
Les proves han de poder executar-se amb php sif/tests/run-tests.php, sense PHPUnit instal·lat.
```

Quan algun fragment antic d'aquest pla mostri `vendor/bin/phpunit`, `PHPUnit\Framework\TestCase` o `vendor/autoload.php`, s'ha d'interpretar com a criteri substituit pel runner propi, `Prisma\Sif\Tests\Support\Assert` i `sif/src/autoload.php`.

## Estructura de fitxers a crear en el repo d'implementacio

```text
sif/config/sif.php
sif/database/migrations/2026_06_02_000001_create_sif_core.sql
sif/database/seeds/2026_06_02_000001_seed_sif_core.sql
sif/public/api/factures/issue.php
sif/public/api/payments/register.php
sif/public/api/redsys/callback.php
sif/scripts/run-migrations.php
sif/scripts/preflight-sif.php
sif/src/Config/SifConfig.php
sif/src/autoload.php
sif/src/Database/ConnectionFactory.php
sif/src/Database/TransactionRunner.php
sif/src/Domain/Clock.php
sif/src/Domain/UuidGenerator.php
sif/src/Domain/HashCalculator.php
sif/src/Domain/Idempotency.php
sif/src/Domain/PaymentStatusCalculator.php
sif/src/Exception/SifException.php
sif/src/Http/JsonResponse.php
sif/src/Repository/FiscalSequenceRepository.php
sif/src/Repository/InvoiceRepository.php
sif/src/Repository/DocumentRepository.php
sif/src/Repository/IncidentRepository.php
sif/src/Repository/PaymentRepository.php
sif/src/Repository/RedsysNotificationRepository.php
sif/src/Repository/LegacySyncRepository.php
sif/src/Service/InvoicePayloadValidator.php
sif/src/Service/PaymentPayloadValidator.php
sif/src/Service/InvoiceService.php
sif/src/Service/PaymentService.php
sif/src/Service/RedsysCallbackService.php
sif/src/Service/LegacySyncService.php
sif/tests/bootstrap.php
sif/tests/run-tests.php
sif/tests/Support/Assert.php
sif/tests/Support/TestDatabase.php
sif/tests/Support/Fixtures.php
sif/tests/Database/SifSchemaTest.php
sif/tests/Unit/HashCalculatorTest.php
sif/tests/Unit/PaymentStatusCalculatorTest.php
sif/tests/Unit/InvoicePayloadValidatorTest.php
sif/tests/Unit/PaymentPayloadValidatorTest.php
sif/tests/Integration/IssueInvoiceTest.php
sif/tests/Integration/RegisterPaymentTest.php
sif/tests/Integration/RedsysCallbackTest.php
sif/tests/Integration/LegacyRelationsTest.php
sif/tests/Integration/ConcurrencySmokeTest.php
```

## Fase 0: Preparacio

### Task 0: Crear base de projecte PHP sense Composer i proves

**Files:**
- Create: `sif/src/autoload.php`
- Create: `sif/config/sif.php`
- Create: `sif/tests/bootstrap.php`
- Create: `sif/tests/Support/Assert.php`
- Create: `sif/tests/run-tests.php`

- [ ] **Step 1: Crear autoload propi**

```php
<?php
// sif/src/autoload.php
spl_autoload_register(static function (string $class): void {
    $prefix = 'Prisma\\Sif\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});
```

- [ ] **Step 2: Crear bootstrap de proves**

```php
<?php
// sif/tests/bootstrap.php
require dirname(__DIR__) . '/src/autoload.php';
date_default_timezone_set('Europe/Madrid');
```

- [ ] **Step 3: Crear config**

```php
<?php
// sif/config/sif.php
return [
    'env' => getenv('SIF_ENV') ?: 'local',
    'db' => [
        'dsn' => getenv('SIF_DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=sif_test;charset=utf8mb4',
        'user' => getenv('SIF_DB_USER') ?: 'sif_test',
        'password' => getenv('SIF_DB_PASSWORD') ?: '',
    ],
    'issuer' => [
        'nif' => 'G00000000',
        'name' => 'Associacio PrisMa',
    ],
    'series' => [
        'invoice' => 'A',
        'rectification' => 'R',
    ],
];
```

- [ ] **Step 4: Crear asserts i runner propi**

```php
<?php
// sif/tests/Support/Assert.php
namespace Prisma\Sif\Tests\Support;

final class Assert
{
    public static function same(mixed $expected, mixed $actual): void {}
    public static function notSame(mixed $unexpected, mixed $actual): void {}
    public static function matchesRegularExpression(string $pattern, string $actual): void {}
    public static function stringContainsString(string $needle, string $haystack): void {}
    public static function fail(string $message): void {}
}
```

- [ ] **Step 5: Verificar arrencada sense Composer**

Run:

```bash
php -r "require 'sif/src/autoload.php'; echo class_exists('Prisma\\Sif\\Domain\\UuidGenerator') ? 'autoload ok' : 'autoload pending';"
php sif/tests/run-tests.php
```

Expected:

```text
[PASS] ...
N passed, 0 failed
```

## Fase 1: Base de dades fiscal

### Task 1: Crear migracio SQL del nucli SIF

**Files:**
- Create: `sif/database/migrations/2026_06_02_000001_create_sif_core.sql`
- Create: `sif/database/seeds/2026_06_02_000001_seed_sif_core.sql`
- Create: `sif/scripts/run-migrations.php`
- Test: `sif/tests/Database/SifSchemaTest.php`

- [ ] **Step 1: Escriure test d'esquema**

```php
<?php
namespace Prisma\Sif\Tests\Database;

use Prisma\Sif\Tests\Support\TestDatabase;

final class SifSchemaTest
{
    public function testCoreTablesExistWithExpectedColumns(): void
    {
        $db = TestDatabase::fresh();

        $tables = [
            'factura',
            'factura_linia',
            'factura_registres',
            'factura_rectificacio',
            'factura_documents',
            'fiscal_sequence',
            'fiscal_chain_state',
            'fiscal_queue',
            'payment_transaction',
            'payment_allocation',
            'fact_rels',
            'redsys_notifications',
            'credit_balance',
            'errors_verifactu',
        ];

        foreach ($tables as $table) {
            $stmt = $db->query("SHOW TABLES LIKE " . $db->quote($table));
            self::assertSame($table, $stmt->fetchColumn(), "Missing table {$table}");
        }

        $columns = $db->query("SHOW COLUMNS FROM factura")->fetchAll(\PDO::FETCH_COLUMN);
        self::assertContains('UUID_FACTURA', $columns);
        self::assertContains('IDEMPOTENCY_KEY', $columns);
        self::assertContains('NUM_VISIBLE', $columns);
        self::assertContains('ESTAT_COBRAMENT', $columns);
    }
}
```

- [ ] **Step 2: Crear helper de BD de test**

```php
<?php
namespace Prisma\Sif\Tests\Support;

final class TestDatabase
{
    public static function fresh(): \PDO
    {
        $config = require dirname(__DIR__, 2) . '/config/sif.php';
        $db = new \PDO(
            $config['db']['dsn'],
            $config['db']['user'],
            $config['db']['password'],
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );

        $migration = file_get_contents(dirname(__DIR__, 2) . '/database/migrations/2026_06_02_000001_create_sif_core.sql');
        $seed = file_get_contents(dirname(__DIR__, 2) . '/database/seeds/2026_06_02_000001_seed_sif_core.sql');
        $db->exec($migration);
        $db->exec($seed);

        return $db;
    }
}
```

- [ ] **Step 3: Ejecutar test i verificar que falla**

Run:

```bash
php sif/tests/run-tests.php
```

Expected:

```text
FAILURES!
Missing table factura
```

- [ ] **Step 4: Crear migracio SQL**

La migracio ha d'incloure totes les taules del model tancat. Copiar el SQL de `documentacio/04-estat-final/05-model-bd-sif.md`, amb aquests ajustos obligatoris:

```sql
DROP TABLE IF EXISTS fact_rels;
DROP TABLE IF EXISTS payment_allocation;
DROP TABLE IF EXISTS payment_transaction;
DROP TABLE IF EXISTS redsys_notifications;
DROP TABLE IF EXISTS fiscal_queue;
DROP TABLE IF EXISTS factura_documents;
DROP TABLE IF EXISTS factura_rectificacio;
DROP TABLE IF EXISTS factura_registres;
DROP TABLE IF EXISTS factura_linia;
DROP TABLE IF EXISTS factura;
DROP TABLE IF EXISTS fiscal_chain_state;
DROP TABLE IF EXISTS fiscal_sequence;
DROP TABLE IF EXISTS credit_balance;
DROP TABLE IF EXISTS errors_verifactu;

CREATE TABLE factura (
    ID BIGINT AUTO_INCREMENT PRIMARY KEY,
    UUID_FACTURA CHAR(36) NOT NULL UNIQUE,
    IDEMPOTENCY_KEY VARCHAR(100) NOT NULL UNIQUE,
    TIPUS_SERIE CHAR(1) NOT NULL,
    ANY_FACT SMALLINT NOT NULL,
    NUM_SEQ INT NOT NULL,
    NUM_VISIBLE VARCHAR(30) NOT NULL UNIQUE,
    TIPUS_FACTURA VARCHAR(5) NOT NULL DEFAULT 'F1',
    DATA_EMISSIO DATETIME NOT NULL,
    DATA_OPERACIO DATETIME NULL,
    DATA_PAGAMENT DATETIME NULL,
    EMESA_ABANS_COBRAMENT TINYINT(1) NOT NULL DEFAULT 0,
    E_FACT TINYINT(1) NOT NULL DEFAULT 0,
    ESTAT_COBRAMENT VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    ESTAT_FACTURA VARCHAR(20) NOT NULL DEFAULT 'ISSUED',
    ESTAT_AEAT VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    BILLING_NOM_RAO VARCHAR(180) NOT NULL,
    BILLING_NIF_CIF VARCHAR(20) NOT NULL,
    BILLING_ADRECA VARCHAR(180) NULL,
    BILLING_CP VARCHAR(10) NULL,
    BILLING_POBLACIO VARCHAR(120) NULL,
    BILLING_PROVINCIA VARCHAR(120) NULL,
    BILLING_PAIS CHAR(2) NOT NULL DEFAULT 'ES',
    BILLING_EMAIL VARCHAR(180) NULL,
    IMPORT_BASE DECIMAL(12,2) NOT NULL,
    DESC_IMPORT DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    BASE_IMPOSABLE DECIMAL(12,2) NOT NULL,
    IVA_REGIM VARCHAR(20) NOT NULL DEFAULT 'EXEMPT',
    IVA_PCT DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    IVA_IMPORT DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    TOTAL DECIMAL(12,2) NOT NULL,
    SOURCE_CHANNEL VARCHAR(30) NOT NULL,
    CREATED_BY VARCHAR(80) NULL,
    CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_factura_num (TIPUS_SERIE, ANY_FACT, NUM_SEQ)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Afegir a la mateixa migracio la resta de taules exactes del model: `factura_linia`, `fiscal_sequence`, `fiscal_chain_state`, `factura_registres`, `factura_rectificacio`, `payment_transaction`, `payment_allocation`, `fact_rels`, `fiscal_queue`, `factura_documents`, `redsys_notifications`, `credit_balance`, `errors_verifactu`.

- [ ] **Step 5: Crear seed inicial**

```sql
INSERT INTO fiscal_chain_state (ID, LAST_FISCAL_ORDER, LAST_HASH)
VALUES (1, 0, NULL)
ON DUPLICATE KEY UPDATE LAST_FISCAL_ORDER = LAST_FISCAL_ORDER;
```

- [ ] **Step 6: Crear runner de migracions**

```php
<?php
// sif/scripts/run-migrations.php
$config = require dirname(__DIR__) . '/config/sif.php';
$db = new PDO($config['db']['dsn'], $config['db']['user'], $config['db']['password']);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

foreach (glob(dirname(__DIR__) . '/database/migrations/*.sql') as $file) {
    $db->exec(file_get_contents($file));
    echo "Migrated {$file}\n";
}

foreach (glob(dirname(__DIR__) . '/database/seeds/*.sql') as $file) {
    $db->exec(file_get_contents($file));
    echo "Seeded {$file}\n";
}
```

- [ ] **Step 7: Reexecutar test**

Run:

```bash
php sif/tests/run-tests.php
```

Expected:

```text
OK (1 test)
```

## Fase 2: Infraestructura comuna

### Task 2: Connexio BD, transaccions, UUID i excepcions

**Files:**
- Create: `sif/src/Database/ConnectionFactory.php`
- Create: `sif/src/Database/TransactionRunner.php`
- Create: `sif/src/Domain/UuidGenerator.php`
- Create: `sif/src/Exception/SifException.php`
- Test: `sif/tests/Unit/HashCalculatorTest.php`

- [ ] **Step 1: Crear excepcio SIF**

```php
<?php
namespace Prisma\Sif\Exception;

final class SifException extends \RuntimeException
{
    public static function validation(string $message): self
    {
        return new self($message, 422);
    }

    public static function conflict(string $message): self
    {
        return new self($message, 409);
    }
}
```

- [ ] **Step 2: Crear `ConnectionFactory`**

```php
<?php
namespace Prisma\Sif\Database;

final class ConnectionFactory
{
    public static function make(array $config): \PDO
    {
        $db = new \PDO(
            $config['db']['dsn'],
            $config['db']['user'],
            $config['db']['password'],
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        $db->exec("SET NAMES utf8mb4");
        return $db;
    }
}
```

- [ ] **Step 3: Crear `TransactionRunner`**

```php
<?php
namespace Prisma\Sif\Database;

final class TransactionRunner
{
    public function __construct(private \PDO $db) {}

    public function run(callable $callback): mixed
    {
        $this->db->beginTransaction();
        try {
            $result = $callback($this->db);
            $this->db->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
```

- [ ] **Step 4: Crear generador UUID**

```php
<?php
namespace Prisma\Sif\Domain;

final class UuidGenerator
{
    public function generate(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
```

- [ ] **Step 5: Verificar autoload**

Run:

```bash
php -r "require 'sif/src/autoload.php'; echo (new Prisma\\Sif\\Domain\\UuidGenerator())->generate(), PHP_EOL;"
```

Expected:

```text
xxxxxxxx-xxxx-4xxx-xxxx-xxxxxxxxxxxx
```

## Fase 3: Payloads i validacio

### Task 3: Validar payloads de factura i pagament

**Files:**
- Create: `sif/src/Service/InvoicePayloadValidator.php`
- Create: `sif/src/Service/PaymentPayloadValidator.php`
- Test: `sif/tests/Unit/InvoicePayloadValidatorTest.php`
- Test: `sif/tests/Unit/PaymentPayloadValidatorTest.php`

- [ ] **Step 1: Escriure test de factura valida**

```php
<?php
namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Service\InvoicePayloadValidator;
use Prisma\Sif\Tests\Support\Assert;

final class InvoicePayloadValidatorTest
{
    public function testValidInvoicePayloadPasses(): void
    {
        $payload = [
            'idempotency_key' => 'REDSYS|CURS|IDPAG:123|ORDER:999999',
            'series' => 'A',
            'type' => 'F1',
            'source_channel' => 'REDSYS',
            'billing' => [
                'name' => 'Client Exemple',
                'nif' => '12345678Z',
                'country' => 'ES',
            ],
            'totals' => [
                'import_base' => '120.00',
                'discount' => '0.00',
                'taxable_base' => '120.00',
                'iva_regim' => 'EXEMPT',
                'iva_pct' => '0.00',
                'iva_import' => '0.00',
                'total' => '120.00',
            ],
            'lines' => [[
                'concept' => 'Curs individual',
                'quantity' => '1.00',
                'unit_price' => '120.00',
                'base' => '120.00',
                'total' => '120.00',
                'source_type' => 'INSCRIPCIO',
                'source_id' => 10,
            ]],
        ];

        Assert::same($payload, (new InvoicePayloadValidator())->validate($payload));
    }
}
```

- [ ] **Step 2: Implementar validador de factura**

```php
<?php
namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class InvoicePayloadValidator
{
    public function validate(array $payload): array
    {
        foreach (['idempotency_key', 'series', 'type', 'source_channel', 'billing', 'totals', 'lines'] as $key) {
            if (!array_key_exists($key, $payload)) {
                throw SifException::validation("Missing invoice field {$key}");
            }
        }
        if (!in_array($payload['series'], ['A', 'R'], true)) {
            throw SifException::validation('Invalid invoice series');
        }
        if (!is_array($payload['lines']) || count($payload['lines']) < 1) {
            throw SifException::validation('Invoice requires at least one line');
        }
        foreach (['name', 'nif'] as $key) {
            if (empty($payload['billing'][$key])) {
                throw SifException::validation("Missing billing field {$key}");
            }
        }
        foreach (['import_base', 'taxable_base', 'total'] as $key) {
            if (!isset($payload['totals'][$key]) || !is_numeric($payload['totals'][$key])) {
                throw SifException::validation("Invalid total {$key}");
            }
        }
        return $payload;
    }
}
```

- [ ] **Step 3: Escriure test de pagament**

```php
<?php
namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Service\PaymentPayloadValidator;
use Prisma\Sif\Tests\Support\Assert;

final class PaymentPayloadValidatorTest
{
    public function testValidPaymentPayloadPasses(): void
    {
        $payload = [
            'idempotency_key' => 'TRANSFERENCIA|REF:ABC123',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '120.00',
            'movement_date' => '2026-06-02 10:00:00',
            'allocations' => [[
                'uuid_factura' => '11111111-1111-4111-8111-111111111111',
                'amount' => '120.00',
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ];

        Assert::same($payload, (new PaymentPayloadValidator())->validate($payload));
    }
}
```

- [ ] **Step 4: Implementar validador de pagament**

```php
<?php
namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class PaymentPayloadValidator
{
    public function validate(array $payload): array
    {
        foreach (['idempotency_key', 'movement_type', 'method', 'source_channel', 'amount', 'movement_date', 'allocations'] as $key) {
            if (!array_key_exists($key, $payload)) {
                throw SifException::validation("Missing payment field {$key}");
            }
        }
        if (!in_array($payload['movement_type'], ['CHARGE', 'REFUND', 'COMPENSATION'], true)) {
            throw SifException::validation('Invalid movement type');
        }
        if (!in_array($payload['method'], ['REDSYS', 'TRANSFERENCIA', 'COMPENSACIO', 'MANUAL'], true)) {
            throw SifException::validation('Invalid payment method');
        }
        if (!is_numeric($payload['amount'])) {
            throw SifException::validation('Invalid payment amount');
        }
        if (!is_array($payload['allocations']) || count($payload['allocations']) < 1) {
            throw SifException::validation('Payment requires at least one allocation');
        }
        return $payload;
    }
}
```

- [ ] **Step 5: Executar proves de validadors**

Run:

```bash
php sif/tests/run-tests.php
```

Expected:

```text
[PASS] ...
N passed, 0 failed
```

## Fase 4: Numeracio, hash chain i idempotencia

### Task 4: Implementar hash fiscal intern

**Files:**
- Create: `sif/src/Domain/HashCalculator.php`
- Test: `sif/tests/Unit/HashCalculatorTest.php`

- [x] **Step 1: Escriure test de hash estable**

```php
<?php
namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Domain\HashCalculator;
use Prisma\Sif\Tests\Support\Assert;

final class HashCalculatorTest
{
    public function testHashIsStableForCanonicalPayload(): void
    {
        $payload = [
            'uuid_factura' => '11111111-1111-4111-8111-111111111111',
            'num_visible' => 'A2026/000001',
            'total' => '120.00',
        ];

        $hash = (new HashCalculator())->calculate($payload, null);

        Assert::same(64, strlen($hash));
        Assert::same($hash, (new HashCalculator())->calculate($payload, null));
    }
}
```

- [x] **Step 1b: Escriure test que l'ordre de claus associatives no altera el hash**

```php
public function testHashIgnoresAssociativeKeyOrder(): void
{
    $first = ['num_visible' => 'A2026/000001', 'billing' => ['nif' => '12345678Z', 'name' => 'Client Exemple']];
    $second = ['billing' => ['name' => 'Client Exemple', 'nif' => '12345678Z'], 'num_visible' => 'A2026/000001'];

    $calculator = new HashCalculator();

    Assert::same($calculator->calculate($first, null), $calculator->calculate($second, null));
}
```

- [x] **Step 2: Implementar `HashCalculator`**

```php
<?php
namespace Prisma\Sif\Domain;

final class HashCalculator
{
    public function calculate(array $payload, ?string $previousHash): string
    {
        $canonical = json_encode([
            'previous_hash' => $previousHash,
            'payload' => $this->canonicalize($payload),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);

        return hash('sha256', $canonical);
    }

    private function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if ($this->isList($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }
        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }
        return $value;
    }

    private function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }
}
```

- [ ] **Step 3: Executar test**

Run:

```bash
php sif/tests/run-tests.php
```

Expected:

```text
[PASS] ...
N passed, 0 failed
```

### Task 5: Repositoris de numeracio i factura

**Files:**
- Create: `sif/src/Repository/FiscalSequenceRepository.php`
- Create: `sif/src/Repository/InvoiceRepository.php`
- Test: `sif/tests/Integration/IssueInvoiceTest.php`

Nota d'implementacio 2026-06-05: aquest task s'ha implementat amb runner PHP propi, `Prisma\Sif\Tests\Support\Assert`, `TestDatabase::fresh()` protegit per DSN de test i noms de columna reals de la migracio (`IMPORT_BASE`, `BASE_IMPOSABLE`, `TOTAL`). No s'han d'usar els noms provisionals `BASE_LINIA`, `TOTAL_LINIA` ni `DESC_TIPUS`.

- [x] **Step 1: Escriure test d'emissio basica**

```php
<?php
namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\HashCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\FiscalSequenceRepository;
use Prisma\Sif\Repository\InvoiceRepository;
use Prisma\Sif\Service\InvoicePayloadValidator;
use Prisma\Sif\Service\InvoiceService;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class IssueInvoiceTest
{
    public function testIssueInvoiceCreatesFiscalRecordAndQueue(): void
    {
        $db = TestDatabase::fresh();
        $service = self::serviceFor($db);

        $result = $service->issueInvoice(Fixtures::invoicePayload());

        self::assertSame('A2026/000001', $result['num_visible']);
        self::assertFalse($result['idempotency_reused']);
        self::assertSame(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        self::assertSame(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        self::assertSame(1, (int) $db->query('SELECT COUNT(*) FROM fiscal_queue')->fetchColumn());
    }

    public static function serviceFor(\PDO $db): InvoiceService
    {
        return new InvoiceService(
            new TransactionRunner($db),
            new InvoicePayloadValidator(),
            new FiscalSequenceRepository(),
            new InvoiceRepository(new UuidGenerator(), new HashCalculator())
        );
    }
}
```

- [x] **Step 2: Crear fixtures**

```php
<?php
namespace Prisma\Sif\Tests\Support;

final class Fixtures
{
    public static function invoicePayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'idempotency_key' => 'REDSYS|CURS|IDPAG:123|ORDER:999999',
            'series' => 'A',
            'year' => 2026,
            'type' => 'F1',
            'source_channel' => 'REDSYS',
            'created_by' => 'test-runner',
            'billing' => [
                'name' => 'Client Exemple',
                'nif' => '12345678Z',
                'address' => 'Carrer Exemple 1',
                'cp' => '08001',
                'city' => 'Barcelona',
                'province' => 'Barcelona',
                'country' => 'ES',
                'email' => 'client@example.test',
            ],
            'totals' => [
                'import_base' => '120.00',
                'discount' => '0.00',
                'taxable_base' => '120.00',
                'iva_regim' => 'EXEMPT',
                'iva_pct' => '0.00',
                'iva_import' => '0.00',
                'total' => '120.00',
            ],
            'lines' => [[
                'concept' => 'Curs individual',
                'detail' => 'Curs de prova',
                'quantity' => '1.00',
                'unit_price' => '120.00',
                'base' => '120.00',
                'iva_regim' => 'EXEMPT',
                'iva_pct' => '0.00',
                'iva_import' => '0.00',
                'total' => '120.00',
                'source_type' => 'INSCRIPCIO',
                'source_id' => 10,
            ]],
            'relations' => [[
                'source_type' => 'INSCRIPCIO',
                'source_id' => 10,
                'factura_relacionada' => 500,
                'idpag' => 123,
                'ds_order' => '999999',
                'visible_alumne' => 1,
            ]],
        ], $overrides);
    }
}
```

- [x] **Step 3: Implementar `FiscalSequenceRepository`**

```php
<?php
namespace Prisma\Sif\Repository;

final class FiscalSequenceRepository
{
    public function next(\PDO $db, string $series, int $year): int
    {
        $stmt = $db->prepare('SELECT LAST_NUM FROM fiscal_sequence WHERE TIPUS_SERIE = ? AND ANY_FACT = ? FOR UPDATE');
        $stmt->execute([$series, $year]);
        $last = $stmt->fetchColumn();

        if ($last === false) {
            $db->prepare('INSERT INTO fiscal_sequence (TIPUS_SERIE, ANY_FACT, LAST_NUM) VALUES (?, ?, 0)')
                ->execute([$series, $year]);
            $last = 0;
        }

        $next = (int) $last + 1;
        $db->prepare('UPDATE fiscal_sequence SET LAST_NUM = ? WHERE TIPUS_SERIE = ? AND ANY_FACT = ?')
            ->execute([$next, $series, $year]);

        return $next;
    }
}
```

- [x] **Step 4: Implementar `InvoiceRepository` amb transaccio rebuda**

El repositori no obre ni tanca transaccions. Rep sempre la connexio ja transaccionada.

```php
<?php
namespace Prisma\Sif\Repository;

use Prisma\Sif\Domain\HashCalculator;
use Prisma\Sif\Domain\UuidGenerator;

final class InvoiceRepository
{
    public function __construct(
        private UuidGenerator $uuidGenerator,
        private HashCalculator $hashCalculator
    ) {}

    public function findByIdempotencyKey(\PDO $db, string $key): ?array
    {
        $stmt = $db->prepare('SELECT * FROM factura WHERE IDEMPOTENCY_KEY = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function lockChainState(\PDO $db): array
    {
        $row = $db->query('SELECT * FROM fiscal_chain_state WHERE ID = 1 FOR UPDATE')->fetch(\PDO::FETCH_ASSOC);
        return $row ?: ['LAST_FISCAL_ORDER' => 0, 'LAST_HASH' => null];
    }

    public function createInvoiceGraph(\PDO $db, array $payload, int $seq, array $chainState): array
    {
        $uuid = $this->uuidGenerator->generate();
        $year = (int) ($payload['year'] ?? date('Y'));
        $numVisible = sprintf('%s%d/%06d', $payload['series'], $year, $seq);
        $fiscalOrder = (int) $chainState['LAST_FISCAL_ORDER'] + 1;

        $recordPayload = [
            'uuid_factura' => $uuid,
            'num_visible' => $numVisible,
            'billing' => $payload['billing'],
            'totals' => $payload['totals'],
            'lines' => $payload['lines'],
        ];
        $hash = $this->hashCalculator->calculate($recordPayload, $chainState['LAST_HASH'] ?? null);

        $stmt = $db->prepare('INSERT INTO factura (
            UUID_FACTURA, IDEMPOTENCY_KEY, TIPUS_SERIE, ANY_FACT, NUM_SEQ, NUM_VISIBLE,
            TIPUS_FACTURA, DATA_EMISSIO, EMESA_ABANS_COBRAMENT, E_FACT, ESTAT_COBRAMENT,
            ESTAT_FACTURA, ESTAT_AEAT, BILLING_NOM_RAO, BILLING_NIF_CIF, BILLING_ADRECA,
            BILLING_CP, BILLING_POBLACIO, BILLING_PROVINCIA, BILLING_PAIS, BILLING_EMAIL,
            IMPORT_BASE, DESC_IMPORT, BASE_IMPOSABLE, IVA_REGIM, IVA_PCT, IVA_IMPORT,
            TOTAL, SOURCE_CHANNEL, CREATED_BY
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, 0, ?, "ISSUED", "PENDING", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

        $stmt->execute([
            $uuid,
            $payload['idempotency_key'],
            $payload['series'],
            $year,
            $seq,
            $numVisible,
            $payload['type'],
            !empty($payload['emesa_abans_cobrament']) ? 1 : 0,
            isset($payload['payment']) ? 'PAID' : 'PENDING',
            $payload['billing']['name'],
            $payload['billing']['nif'],
            $payload['billing']['address'] ?? null,
            $payload['billing']['cp'] ?? null,
            $payload['billing']['city'] ?? null,
            $payload['billing']['province'] ?? null,
            $payload['billing']['country'] ?? 'ES',
            $payload['billing']['email'] ?? null,
            $payload['totals']['import_base'],
            $payload['totals']['discount'] ?? '0.00',
            $payload['totals']['taxable_base'],
            $payload['totals']['iva_regim'] ?? 'EXEMPT',
            $payload['totals']['iva_pct'] ?? '0.00',
            $payload['totals']['iva_import'] ?? '0.00',
            $payload['totals']['total'],
            $payload['source_channel'],
            $payload['created_by'] ?? null,
        ]);

        foreach ($payload['lines'] as $index => $line) {
            $db->prepare('INSERT INTO factura_linia (
                UUID_FACTURA, ORDRE, CONCEPTE, DETALL, QUANTITAT, PREU_UNITARI,
                IMPORT_BASE, DESC_ORIGEN, DESC_MODE, DESC_ID, DESC_CODI_PROMO,
                DESC_PCT, DESC_IMPORT, DESC_TEXT_VISIBLE, DESC_MOTIU_INTERN,
                BASE_IMPOSABLE, IVA_REGIM, IVA_PCT, IVA_IMPORT, TOTAL,
                SOURCE_TYPE, SOURCE_ID
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([
                    $uuid, $index + 1, $line['concept'], $line['detail'] ?? null,
                    $line['quantity'], $line['unit_price'],
                    $line['discount_origin'] ?? null, $line['discount_mode'] ?? null,
                    $line['discount_type'] ?? null, $line['discount_id'] ?? null,
                    $line['discount_code'] ?? null, $line['discount_pct'] ?? null,
                    $line['discount_amount'] ?? '0.00', $line['discount_text'] ?? null,
                    $line['discount_internal_reason'] ?? null,
                    $line['base'], $line['iva_regim'] ?? 'EXEMPT', $line['iva_pct'] ?? '0.00',
                    $line['iva_import'] ?? '0.00', $line['total'],
                    $line['source_type'] ?? null, $line['source_id'] ?? null,
                ]);
        }

        $db->prepare('INSERT INTO factura_registres (
            UUID_FACTURA, FISCAL_ORDER, TIPUS_REGISTRE, HASH_FACT, HASH_FACT_ANT, PAYLOAD_JSON
        ) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$uuid, $fiscalOrder, 'ALTA', $hash, $chainState['LAST_HASH'] ?? null, json_encode($recordPayload)]);

        $db->prepare('UPDATE fiscal_chain_state SET LAST_FISCAL_ORDER = ?, LAST_HASH = ? WHERE ID = 1')
            ->execute([$fiscalOrder, $hash]);

        $db->prepare('INSERT INTO fiscal_queue (UUID_FACTURA, IDEMPOTENCY_KEY, PAYLOAD_JSON) VALUES (?, ?, ?)')
            ->execute([$uuid, 'AEAT|' . $payload['idempotency_key'], json_encode($recordPayload)]);

        foreach ($payload['relations'] ?? [] as $rel) {
            $db->prepare('INSERT INTO fact_rels (
                UUID_FACTURA, FACTURA_RELACIONADA, SOURCE_TYPE, SOURCE_ID, RELATION_TYPE,
                IDPAG, DS_ORDER, VISIBLE_ALUMNE
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([
                    $uuid,
                    $rel['factura_relacionada'] ?? null,
                    $rel['source_type'],
                    $rel['source_id'] ?? null,
                    $rel['relation_type'] ?? 'ORIGIN',
                    $rel['idpag'] ?? null,
                    $rel['ds_order'] ?? null,
                    $rel['visible_alumne'] ?? 1,
                ]);
        }

        return [
            'uuid_factura' => $uuid,
            'num_visible' => $numVisible,
            'fiscal_order' => $fiscalOrder,
            'hash' => $hash,
        ];
    }
}
```

- [x] **Step 5: Implementar `InvoiceService`**

```php
<?php
namespace Prisma\Sif\Service;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Repository\FiscalSequenceRepository;
use Prisma\Sif\Repository\InvoiceRepository;

final class InvoiceService
{
    public function __construct(
        private TransactionRunner $transactions,
        private InvoicePayloadValidator $validator,
        private FiscalSequenceRepository $sequences,
        private InvoiceRepository $invoices
    ) {}

    public function issueInvoice(array $payload): array
    {
        $payload = $this->validator->validate($payload);

        return $this->transactions->run(function (\PDO $db) use ($payload): array {
            $existing = $this->invoices->findByIdempotencyKey($db, $payload['idempotency_key']);
            if ($existing) {
                return [
                    'ok' => true,
                    'idempotency_reused' => true,
                    'uuid_factura' => $existing['UUID_FACTURA'],
                    'num_visible' => $existing['NUM_VISIBLE'],
                ];
            }

            $seq = $this->sequences->next($db, $payload['series'], (int) ($payload['year'] ?? date('Y')));
            $chainState = $this->invoices->lockChainState($db);
            $created = $this->invoices->createInvoiceGraph($db, $payload, $seq, $chainState);

            return [
                'ok' => true,
                'idempotency_reused' => false,
                'uuid_factura' => $created['uuid_factura'],
                'num_visible' => $created['num_visible'],
            ];
        });
    }
}
```

- [ ] **Step 6: Executar prova d'emissio**

Run:

```bash
php sif/tests/run-tests.php
```

Expected:

```text
OK (1 test, 5 assertions)
```

### Task 6: Idempotencia i concurrencia d'`issueInvoice()`

**Files:**
- Modify: `sif/tests/Integration/IssueInvoiceTest.php`
- Create: `sif/tests/Integration/ConcurrencySmokeTest.php`

Nota d'implementacio 2026-06-05: aquest task s'ha preparat amb `Prisma\Sif\Tests\Support\Assert`, helper `makeService()`, `IssueInvoiceTest::serviceFor()` public per reutilitzacio entre tests i `ConcurrencySmokeTest`. No ha requerit canvis de codi de produccio respecte al Task 5.

- [x] **Step 1: Afegir test d'idempotencia**

```php
public function testIssueInvoiceReusesSameInvoiceForSameIdempotencyKey(): void
{
    $db = TestDatabase::fresh();
    $service = $this->makeService($db);

    $first = $service->issueInvoice(Fixtures::invoicePayload());
    $second = $service->issueInvoice(Fixtures::invoicePayload());

    self::assertSame($first['uuid_factura'], $second['uuid_factura']);
    self::assertTrue($second['idempotency_reused']);
    self::assertSame(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
    self::assertSame(1, (int) $db->query('SELECT LAST_NUM FROM fiscal_sequence WHERE TIPUS_SERIE = "A" AND ANY_FACT = 2026')->fetchColumn());
}
```

- [x] **Step 2: Afegir helper `makeService()` al test**

```php
private function makeService(\PDO $db): InvoiceService
{
    return self::serviceFor($db);
}
```

- [x] **Step 3: Crear smoke test de concurrencia seqüencial**

```php
<?php
namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ConcurrencySmokeTest
{
    public function testMultipleInvoicesHaveLinearFiscalOrder(): void
    {
        $db = TestDatabase::fresh();
        $service = IssueInvoiceTest::serviceFor($db);

        for ($i = 1; $i <= 10; $i++) {
            $service->issueInvoice(Fixtures::invoicePayload([
                'idempotency_key' => "REDSYS|CURS|IDPAG:{$i}|ORDER:ORDER{$i}",
                'relations' => [[
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => $i,
                    'factura_relacionada' => 500 + $i,
                    'idpag' => $i,
                    'ds_order' => "ORDER{$i}",
                    'visible_alumne' => 1,
                ]],
            ]));
        }

        self::assertSame(10, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        self::assertSame(10, (int) $db->query('SELECT LAST_FISCAL_ORDER FROM fiscal_chain_state WHERE ID = 1')->fetchColumn());
        self::assertSame(10, (int) $db->query('SELECT COUNT(DISTINCT FISCAL_ORDER) FROM factura_registres')->fetchColumn());
    }
}
```

- [ ] **Step 4: Executar proves**

Run:

```bash
php sif/tests/run-tests.php
```

Expected:

```text
[PASS] ...
N passed, 0 failed
```

## Fase 5: `registerPayment()`

### Task 7: Implementar estat de cobrament i pagaments

**Files:**
- Create: `sif/src/Domain/PaymentStatusCalculator.php`
- Create: `sif/src/Repository/PaymentRepository.php`
- Create: `sif/src/Service/PaymentService.php`
- Test: `sif/tests/Unit/PaymentStatusCalculatorTest.php`
- Test: `sif/tests/Integration/RegisterPaymentTest.php`

Nota d'implementacio 2026-06-05: aquest task s'ha preparat amb runner PHP propi i `Prisma\Sif\Tests\Support\Assert`. `PaymentStatusCalculator` calcula amb enters en centims per evitar errors de coma flotant. `registerPayment()` crea nomes `payment_transaction` i `payment_allocation`, recalcula `ESTAT_COBRAMENT` i no crea cap registre fiscal nou a `factura_registres`.

- [x] **Step 1: Test de calcul d'estat de cobrament**

```php
<?php
namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Domain\PaymentStatusCalculator;

final class PaymentStatusCalculatorTest
{
    public function testPaidWhenAssignedEqualsTotal(): void
    {
        self::assertSame('PAID', (new PaymentStatusCalculator())->calculate('120.00', '120.00', '0.00'));
    }

    public function testPartialWhenAssignedIsLowerThanTotal(): void
    {
        self::assertSame('PARTIAL', (new PaymentStatusCalculator())->calculate('120.00', '60.00', '0.00'));
    }

    public function testOverpaidWhenAssignedIsHigherThanTotal(): void
    {
        self::assertSame('OVERPAID', (new PaymentStatusCalculator())->calculate('120.00', '130.00', '0.00'));
    }
}
```

- [x] **Step 2: Implementar calculador**

```php
<?php
namespace Prisma\Sif\Domain;

final class PaymentStatusCalculator
{
    public function calculate(string $invoiceTotal, string $charges, string $refunds): string
    {
        $net = round((float) $charges - (float) $refunds, 2);
        $total = round((float) $invoiceTotal, 2);

        if ($refunds !== '0.00' && $net <= 0.0) {
            return 'REFUNDED';
        }
        if ((float) $refunds > 0.0) {
            return 'PARTIALLY_REFUNDED';
        }
        if ($net === 0.0) {
            return 'PENDING';
        }
        if ($net < $total) {
            return 'PARTIAL';
        }
        if ($net > $total) {
            return 'OVERPAID';
        }
        return 'PAID';
    }
}
```

- [x] **Step 3: Test de `registerPayment()` contra factura existent**

```php
<?php
namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\PaymentStatusCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\PaymentRepository;
use Prisma\Sif\Service\PaymentPayloadValidator;
use Prisma\Sif\Service\PaymentService;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class RegisterPaymentTest
{
    public function testRegisterPaymentCreatesTransactionAndAllocationOnly(): void
    {
        $db = TestDatabase::fresh();
        $invoiceService = IssueInvoiceTest::serviceFor($db);
        $invoice = $invoiceService->issueInvoice(Fixtures::invoicePayload(['emesa_abans_cobrament' => 1]));

        $paymentService = self::paymentServiceFor($db);
        $result = $paymentService->registerPayment([
            'idempotency_key' => 'TRANSFERENCIA|REF:ABC123',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '120.00',
            'movement_date' => '2026-06-02 10:00:00',
            'reference' => 'ABC123',
            'allocations' => [[
                'uuid_factura' => $invoice['uuid_factura'],
                'amount' => '120.00',
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ]);

        self::assertTrue($result['ok']);
        self::assertSame(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        self::assertSame(1, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        self::assertSame(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        self::assertSame('PAID', $db->query('SELECT ESTAT_COBRAMENT FROM factura')->fetchColumn());
    }

    private static function paymentServiceFor(\PDO $db): PaymentService
    {
        return new PaymentService(
            new TransactionRunner($db),
            new PaymentPayloadValidator(),
            new PaymentRepository(new UuidGenerator(), new PaymentStatusCalculator())
        );
    }
}
```

- [x] **Step 4: Implementar repositori de pagaments**

```php
<?php
namespace Prisma\Sif\Repository;

use Prisma\Sif\Domain\PaymentStatusCalculator;
use Prisma\Sif\Domain\UuidGenerator;

final class PaymentRepository
{
    public function __construct(
        private UuidGenerator $uuidGenerator,
        private PaymentStatusCalculator $statusCalculator
    ) {}

    public function findByIdempotencyKey(\PDO $db, string $key): ?array
    {
        $stmt = $db->prepare('SELECT * FROM payment_transaction WHERE IDEMPOTENCY_KEY = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createPayment(\PDO $db, array $payload): array
    {
        $uuid = $this->uuidGenerator->generate();
        $db->prepare('INSERT INTO payment_transaction (
            UUID_PAYMENT, IDEMPOTENCY_KEY, TIPUS_MOVIMENT, METODE, SOURCE_CHANNEL,
            IMPORT, DATA_MOVIMENT, PROVIDER_REF, DS_ORDER, IDPAG, REFERENCIA_BANCARIA,
            PAYLOAD_HASH, ESTAT, NOTES
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "CONFIRMED", ?)')
            ->execute([
                $uuid,
                $payload['idempotency_key'],
                $payload['movement_type'],
                $payload['method'],
                $payload['source_channel'],
                $payload['amount'],
                $payload['movement_date'],
                $payload['provider_ref'] ?? null,
                $payload['ds_order'] ?? null,
                $payload['idpag'] ?? null,
                $payload['reference'] ?? null,
                hash('sha256', json_encode($payload)),
                $payload['notes'] ?? null,
            ]);

        foreach ($payload['allocations'] as $allocation) {
            $db->prepare('INSERT INTO payment_allocation (
                UUID_PAYMENT, UUID_FACTURA, IMPORT_ASSIGNAT, TIPUS_ASSIGNACIO
            ) VALUES (?, ?, ?, ?)')
                ->execute([
                    $uuid,
                    $allocation['uuid_factura'],
                    $allocation['amount'],
                    $allocation['allocation_type'],
                ]);
            $this->refreshInvoicePaymentStatus($db, $allocation['uuid_factura']);
        }

        return ['uuid_payment' => $uuid];
    }

    private function refreshInvoicePaymentStatus(\PDO $db, string $uuidFactura): void
    {
        $totalStmt = $db->prepare('SELECT TOTAL FROM factura WHERE UUID_FACTURA = ? FOR UPDATE');
        $totalStmt->execute([$uuidFactura]);
        $total = (string) $totalStmt->fetchColumn();

        $chargeStmt = $db->prepare('SELECT COALESCE(SUM(pa.IMPORT_ASSIGNAT), 0) FROM payment_allocation pa JOIN payment_transaction pt ON pt.UUID_PAYMENT = pa.UUID_PAYMENT WHERE pa.UUID_FACTURA = ? AND pt.TIPUS_MOVIMENT IN ("CHARGE", "COMPENSATION")');
        $chargeStmt->execute([$uuidFactura]);
        $charges = number_format((float) $chargeStmt->fetchColumn(), 2, '.', '');

        $refundStmt = $db->prepare('SELECT COALESCE(SUM(pa.IMPORT_ASSIGNAT), 0) FROM payment_allocation pa JOIN payment_transaction pt ON pt.UUID_PAYMENT = pa.UUID_PAYMENT WHERE pa.UUID_FACTURA = ? AND pt.TIPUS_MOVIMENT = "REFUND"');
        $refundStmt->execute([$uuidFactura]);
        $refunds = number_format((float) $refundStmt->fetchColumn(), 2, '.', '');

        $status = $this->statusCalculator->calculate($total, $charges, $refunds);
        $db->prepare('UPDATE factura SET ESTAT_COBRAMENT = ? WHERE UUID_FACTURA = ?')
            ->execute([$status, $uuidFactura]);
    }
}
```

- [x] **Step 5: Implementar `PaymentService`**

```php
<?php
namespace Prisma\Sif\Service;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Repository\PaymentRepository;

final class PaymentService
{
    public function __construct(
        private TransactionRunner $transactions,
        private PaymentPayloadValidator $validator,
        private PaymentRepository $payments
    ) {}

    public function registerPayment(array $payload): array
    {
        $payload = $this->validator->validate($payload);

        return $this->transactions->run(function (\PDO $db) use ($payload): array {
            $existing = $this->payments->findByIdempotencyKey($db, $payload['idempotency_key']);
            if ($existing) {
                return [
                    'ok' => true,
                    'idempotency_reused' => true,
                    'uuid_payment' => $existing['UUID_PAYMENT'],
                ];
            }

            $created = $this->payments->createPayment($db, $payload);

            return [
                'ok' => true,
                'idempotency_reused' => false,
                'uuid_payment' => $created['uuid_payment'],
            ];
        });
    }
}
```

- [ ] **Step 6: Executar proves de pagament**

Run:

```bash
php sif/tests/run-tests.php
```

Expected:

```text
[PASS] ...
N passed, 0 failed
```

Expected:

```text
OK
```

## Fase 6: Relacio amb BD antiga i sincronitzacio controlada

### Task 8: Implementar `fact_rels` i sincronitzacio resum

**Files:**
- Create: `sif/src/Repository/LegacySyncRepository.php`
- Create: `sif/src/Service/LegacySyncService.php`
- Test: `sif/tests/Integration/LegacyRelationsTest.php`

Nota d'implementacio 2026-06-05: aquest task s'ha preparat amb `Prisma\Sif\Tests\Support\Assert`, test de `fact_rels` i test amb `LegacySpyPdo` per verificar que la sincronitzacio legacy nomes passa quan es crida explicitament `syncAfterSifSuccess()`. `LegacySyncRepository` rep `factura_relacionada` des de la relacio SIF quan existeix i no s'integra dins `issueInvoice()` ni `registerPayment()`.

- [x] **Step 1: Test de relacions**

```php
<?php
namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class LegacyRelationsTest
{
    public function testFactRelsPreservesLegacyIdentifiers(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload());

        $stmt = $db->prepare('SELECT FACTURA_RELACIONADA, IDPAG, DS_ORDER, SOURCE_TYPE FROM fact_rels WHERE UUID_FACTURA = ?');
        $stmt->execute([$invoice['uuid_factura']]);
        $rel = $stmt->fetch(\PDO::FETCH_ASSOC);

        self::assertSame(500, (int) $rel['FACTURA_RELACIONADA']);
        self::assertSame(123, (int) $rel['IDPAG']);
        self::assertSame('999999', $rel['DS_ORDER']);
        self::assertSame('INSCRIPCIO', $rel['SOURCE_TYPE']);
    }
}
```

- [x] **Step 2: Implementar repositori de sincronitzacio**

```php
<?php
namespace Prisma\Sif\Repository;

final class LegacySyncRepository
{
    public function syncInscripcioSummary(\PDO $legacyDb, int $idInsc, string $uuidFactura, string $numVisible, string $estatCobrament): void
    {
        $legacyDb->prepare('UPDATE inscripcions SET FACTURA_RELACIONADA = COALESCE(FACTURA_RELACIONADA, ?), OBSERVACIONS = CONCAT(COALESCE(OBSERVACIONS, ""), "\nSIF ", ?, " ", ?) WHERE ID = ?')
            ->execute([$idInsc, $numVisible, $estatCobrament, $idInsc]);
    }
}
```

- [x] **Step 3: Implementar servei de sincronitzacio**

```php
<?php
namespace Prisma\Sif\Service;

use Prisma\Sif\Repository\LegacySyncRepository;

final class LegacySyncService
{
    public function __construct(private LegacySyncRepository $repository) {}

    public function syncAfterSifSuccess(\PDO $legacyDb, array $relations, string $uuidFactura, string $numVisible, string $estatCobrament): void
    {
        foreach ($relations as $relation) {
            if (($relation['source_type'] ?? '') === 'INSCRIPCIO' && isset($relation['source_id'])) {
                $this->repository->syncInscripcioSummary($legacyDb, (int) $relation['source_id'], $uuidFactura, $numVisible, $estatCobrament);
            }
        }
    }
}
```

- [x] **Step 4: Verificar que cap sincronitzacio corre abans del COMMIT SIF**

Run:

```bash
rg -n "syncAfterSifSuccess|syncInscripcioSummary" sif/src
```

Expected:

```text
sif/src/Service/LegacySyncService.php:...
```

La crida real a `syncAfterSifSuccess()` ha de quedar fora de la transaccio fiscal principal i nomes despres d'una resposta SIF `ok=true`.

## Fase 7: Endpoints HTTP interns

### Task 9: Crear endpoints `issue` i `register`

**Files:**
- Create: `sif/public/api/factures/issue.php`
- Create: `sif/public/api/payments/register.php`
- Create: `sif/src/Http/JsonResponse.php`

Nota d'implementacio 2026-06-05: aquest task s'ha preparat amb `JsonResponse::fromInput()`, `JsonResponse::fromThrowable()` i test estàtic `sif/tests/Integration/HttpEndpointsTest.php`. Els endpoints construeixen `InvoiceService` i `PaymentService` amb les dependencies del SIF, pero no executen sincronitzacio legacy.

- [x] **Step 1: Crear resposta JSON**

```php
<?php
namespace Prisma\Sif\Http;

final class JsonResponse
{
    public static function send(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
```

- [x] **Step 2: Crear endpoint d'emissio**

```php
<?php
require dirname(__DIR__, 3) . '/src/autoload.php';

use Prisma\Sif\Http\JsonResponse;

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    JsonResponse::send(['ok' => false, 'error' => 'Invalid JSON'], 400);
    return;
}

try {
    $config = require dirname(__DIR__, 3) . '/config/sif.php';
    $db = Prisma\Sif\Database\ConnectionFactory::make($config);
    $service = new Prisma\Sif\Service\InvoiceService(
        new Prisma\Sif\Database\TransactionRunner($db),
        new Prisma\Sif\Service\InvoicePayloadValidator(),
        new Prisma\Sif\Repository\FiscalSequenceRepository(),
        new Prisma\Sif\Repository\InvoiceRepository(
            new Prisma\Sif\Domain\UuidGenerator(),
            new Prisma\Sif\Domain\HashCalculator()
        )
    );
    JsonResponse::send($service->issueInvoice($payload));
} catch (Throwable $e) {
    JsonResponse::send(['ok' => false, 'error' => $e->getMessage()], $e->getCode() >= 400 ? $e->getCode() : 500);
}
```

- [x] **Step 3: Crear endpoint de pagament**

```php
<?php
require dirname(__DIR__, 3) . '/src/autoload.php';

use Prisma\Sif\Http\JsonResponse;

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    JsonResponse::send(['ok' => false, 'error' => 'Invalid JSON'], 400);
    return;
}

try {
    $config = require dirname(__DIR__, 3) . '/config/sif.php';
    $db = Prisma\Sif\Database\ConnectionFactory::make($config);
    $service = new Prisma\Sif\Service\PaymentService(
        new Prisma\Sif\Database\TransactionRunner($db),
        new Prisma\Sif\Service\PaymentPayloadValidator(),
        new Prisma\Sif\Repository\PaymentRepository(
            new Prisma\Sif\Domain\UuidGenerator(),
            new Prisma\Sif\Domain\PaymentStatusCalculator()
        )
    );
    JsonResponse::send($service->registerPayment($payload));
} catch (Throwable $e) {
    JsonResponse::send(['ok' => false, 'error' => $e->getMessage()], $e->getCode() >= 400 ? $e->getCode() : 500);
}
```

- [ ] **Step 4: Provar endpoints amb servidor local**

Run:

```bash
php -S 127.0.0.1:8085 -t sif/public
```

En una altra terminal:

```bash
curl -s -X POST http://127.0.0.1:8085/api/factures/issue.php \
  -H "Content-Type: application/json" \
  --data-binary @sif/tests/fixtures/invoice-curs-normal.json
```

Expected:

```json
{"ok":true,"idempotency_reused":false,"uuid_factura":"...","num_visible":"A2026/000001"}
```

## Fase 8: Redsys i entrada de pagaments

### Task 10: Deduplicar callback Redsys abans de tocar factura

**Files:**
- Create: `sif/src/Repository/RedsysNotificationRepository.php`
- Create: `sif/src/Service/RedsysCallbackService.php`
- Create: `sif/public/api/redsys/callback.php`
- Test: `sif/tests/Integration/RedsysCallbackTest.php`

- [x] **Step 1: Test de callback duplicat**

```php
<?php
namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\TestDatabase;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Repository\RedsysNotificationRepository;

final class RedsysCallbackTest
{
    public function testDuplicateDsOrderDoesNotCreateSecondNotification(): void
    {
        $db = TestDatabase::fresh();
        $repo = new RedsysNotificationRepository();

        $first = $repo->recordReceived($db, 'ORDER123', 123, '120.00', '0000', true, ['source' => 'test']);
        $second = $repo->recordReceived($db, 'ORDER123', 123, '120.00', '0000', true, ['source' => 'test']);

        Assert::same(false, $first['duplicate']);
        Assert::same(true, $second['duplicate']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM redsys_notifications')->fetchColumn());
    }
}
```

- [x] **Step 2: Implementar repositori Redsys**

```php
<?php
namespace Prisma\Sif\Repository;

final class RedsysNotificationRepository
{
    public function recordReceived(
        \PDO $db,
        string $dsOrder,
        ?int $idpag,
        mixed $amount,
        string $responseCode,
        bool $signatureValid,
        ?array $rawPayload = null,
        string $status = 'RECEIVED'
    ): array
    {
        try {
            $db->prepare('INSERT INTO redsys_notifications (DS_ORDER, IDPAG, IMPORT, RESPONSE_CODE, STATUS, RAW_PAYLOAD_JSON, SIGNATURE_VALID) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$dsOrder, $idpag, number_format((float) $amount, 2, '.', ''), $responseCode, $status, json_encode($rawPayload), $signatureValid ? 1 : 0]);
            return ['duplicate' => false, 'ds_order' => $dsOrder, 'status' => $status];
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['duplicate' => true, 'ds_order' => $dsOrder, 'status' => 'DUPLICATE'];
            }
            throw $e;
        }
    }
}
```

- [x] **Step 3: Implementar servei Redsys**

```php
<?php
namespace Prisma\Sif\Service;

use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Exception\SifException;

final class RedsysCallbackService
{
    public function __construct(private RedsysNotificationRepository $notifications) {}

    public function receiveCallback(\PDO $db, array $payload, bool $signatureValid = false): array
    {
        if (!$signatureValid) {
            throw SifException::validation('Invalid Redsys signature');
        }

        return $this->receiveAuthorizedCallback($db, $payload);
    }

    public function receiveAuthorizedCallback(\PDO $db, array $signedData): array
    {
        $record = $this->notifications->recordReceived(
            $db,
            $signedData['ds_order'],
            isset($signedData['idpag']) ? (int) $signedData['idpag'] : null,
            $signedData['amount'],
            $signedData['response_code'],
            true,
            $signedData
        );

        return ['ok' => true, 'duplicate' => (bool) $record['duplicate']];
    }
}
```

- [ ] **Step 4: Executar prova Redsys**

Run:

```bash
php sif/tests/run-tests.php
```

Expected:

```text
OK (1 test, 3 assertions)
```

Resultat local 2026-06-05:

```text
No executat: php no esta disponible al PATH d'aquest entorn.
```

Nota d'implementacio 2026-06-05:

- `sif/tests/Integration/RedsysCallbackTest.php` cobreix deduplicacio per `DS_ORDER`, rebuig de callback sense signatura validada i absencia d'efectes sobre `factura` i `payment_transaction`.
- `RedsysCallbackService::receiveCallback()` rep el boolea intern `$signatureValid`; no confia en cap camp del payload enviat pel client.
- `sif/public/api/redsys/callback.php` queda cablejat pero amb `$signatureValid = false` fins que s'hi connecti la validacio Redsys real del Drive o la funcio actual de PrisMa.

La validacio criptografica Redsys final s'ha de fer amb la llibreria/funcio actual usada per PrisMa, pero sempre abans de `recordReceived()` i abans de qualsevol crida a `issueInvoice()` o `registerPayment()`. El codi historic localitzat al Drive usa `inc/apiRedsys.php`, `decodeMerchantParameters()` i `createMerchantSignatureNotif()`; en activacio cal adaptar aquesta validacio sense hardcodejar secrets i sense Composer.

## Fase 9: Documents, cua AEAT i incidencies

### Task 11: Registrar cua AEAT i documents immutables

**Files:**
- Extend: `sif/tests/Integration/IssueInvoiceTest.php`
- Create: `sif/src/Repository/DocumentRepository.php`
- Create: `sif/src/Repository/IncidentRepository.php`
- Test: `sif/tests/Integration/DocumentsAndIncidentsTest.php`

- [x] **Step 1: Afegir assert de cua AEAT i payload congelat**

```php
$recordPayload = (string) $db->query('SELECT PAYLOAD_JSON FROM factura_registres LIMIT 1')->fetchColumn();
$queue = $db->query('SELECT IDEMPOTENCY_KEY, PAYLOAD_JSON, STATUS FROM fiscal_queue LIMIT 1')
    ->fetch(\PDO::FETCH_ASSOC);

Assert::same(JSON_ERROR_NONE, $this->jsonError($recordPayload));
Assert::same(JSON_ERROR_NONE, $this->jsonError((string) $queue['PAYLOAD_JSON']));
Assert::same($recordPayload, (string) $queue['PAYLOAD_JSON']);
Assert::same('PENDING', (string) $queue['STATUS']);
```

- [x] **Step 2: Crear repositori de documents**

```php
<?php
namespace Prisma\Sif\Repository;

final class DocumentRepository
{
    public function registerDocument(\PDO $db, string $uuidFactura, string $type, string $path, string $contents): array
    {
        $hash = hash('sha256', $contents);

        $db->prepare('INSERT INTO factura_documents (UUID_FACTURA, TIPUS, PATH_FITXER, HASH_FITXER, ESTAT) VALUES (?, ?, ?, ?, "CREATED")')
            ->execute([$uuidFactura, strtoupper($type), $path, $hash]);

        return ['ok' => true, 'hash' => $hash];
    }
}
```

- [x] **Step 3: Crear repositori d'incidencies**

```php
<?php
namespace Prisma\Sif\Repository;

final class IncidentRepository
{
    public function open(\PDO $db, ?string $uuidFactura, string $type, string $message): array
    {
        $db->prepare('INSERT INTO errors_verifactu (UUID_FACTURA, TIPUS_INCIDENCIA, ESTAT, DETAILS) VALUES (?, ?, "OPEN", ?)')
            ->execute([$uuidFactura, strtoupper($type), $message]);

        return ['ok' => true];
    }
}
```

- [x] **Step 4: Incloure `errors_verifactu` a la migracio inicial**

```sql
CREATE TABLE errors_verifactu (
    ID BIGINT AUTO_INCREMENT PRIMARY KEY,
    UUID_FACTURA CHAR(36) NULL,
    TIPUS_INCIDENCIA VARCHAR(50) NOT NULL,
    ESTAT VARCHAR(30) NOT NULL DEFAULT 'OPEN',
    DETAILS TEXT NOT NULL,
    CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UPDATED_AT TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_error_factura (UUID_FACTURA),
    KEY idx_error_estat (ESTAT)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Resultat local 2026-06-05:

```text
No executat: php no esta disponible al PATH d'aquest entorn.
```

Nota d'implementacio 2026-06-05:

- `InvoiceRepository` ja inseria `fiscal_queue`; la Fase 9 reforca el test per comprovar `PAYLOAD_JSON` congelat i `STATUS = PENDING`.
- `errors_verifactu` ja estava inclosa a la migracio inicial, de manera que no s'ha modificat l'SQL en aquesta fase.
- `DocumentRepository` registra metadades i `HASH_FITXER`, no genera ni emmagatzema el contingut del PDF/XML/QR.
- `IncidentRepository` obre incidencies SIF a `errors_verifactu` amb estat `OPEN`.

## Fase 10: Proves go/no-go

### Task 12: Automatitzar preflight tecnic SIF

**Files:**
- Create: `sif/scripts/preflight-sif.php`
- Test: `sif/tests/Integration/PreflightScriptTest.php`
- Test command: `php sif/scripts/preflight-sif.php`

- [x] **Step 1: Crear script de preflight**

```php
<?php
require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;

$config = require dirname(__DIR__) . '/config/sif.php';

$checks = [
    'database_connectivity' => false,
    'factura_table' => false,
    'factura_linia_table' => false,
    'factura_registres_table' => false,
    'fiscal_queue_table' => false,
    'payment_transaction_table' => false,
    'payment_allocation_table' => false,
    'redsys_notifications_table' => false,
    'factura_documents_table' => false,
    'errors_verifactu_table' => false,
    'fiscal_chain_state_seeded' => false,
];

$db = ConnectionFactory::make($config);
$checks['database_connectivity'] = true;

// ... comprovar taules crítiques i seed fiscal_chain_state ...

$failed = array_keys(array_filter($checks, fn ($ok) => !$ok));
echo json_encode(['ok' => count($failed) === 0, 'checks' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
exit(count($failed) === 0 ? 0 : 1);
```

- [ ] **Step 2: Executar preflight**

Run:

```bash
php sif/scripts/preflight-sif.php
```

Expected:

```json
{
  "ok": true,
  "environment": "test",
  "checks": {
    "database_connectivity": true,
    "factura_table": true,
    "factura_linia_table": true,
    "factura_registres_table": true,
    "fiscal_queue_table": true,
    "payment_transaction_table": true,
    "payment_allocation_table": true,
    "redsys_notifications_table": true,
    "factura_documents_table": true,
    "errors_verifactu_table": true,
    "fiscal_chain_state_seeded": true
  }
}
```

- [ ] **Step 3: Executar bateria minima abans de qualsevol pilot**

Run:

```bash
php sif/tests/run-tests.php
php sif/scripts/preflight-sif.php
php sif/scripts/go-no-go-preproduction.php
```

Expected:

```text
OK
{
  "ok": true,
  ...
}
```

Resultat local 2026-06-05:

```text
No executat: php no esta disponible al PATH d'aquest entorn.
```

Nota d'implementacio 2026-06-05:

- `preflight-sif.php` carrega `sif/src/autoload.php`, usa `ConnectionFactory` i no modifica la BD.
- El script comprova connexio, taules fiscals/economiques clau, Redsys, documents, incidencies i `fiscal_chain_state` sembrada.
- La sortida es JSON amb `ok`, `environment`, `checks`, `failed` i `errors` quan correspongui.
- `PreflightScriptTest` cobreix el contracte del script de manera estàtica fins que es pugui executar PHP.

Nota d'implementacio 2026-06-10:

- `go-no-go-preproduction.php` agrega una bateria bloquejant de preproduccio: entorn no productiu, preflight base, runner de proves, migracions, connexio SIF, connexio legacy, clau Redsys, taules fiscals minimes i circuits preparats de curs, pagament manual, packs i regals.
- `GoNoGoPreproductionScriptTest` cobreix de manera estàtica que la bateria retorna `go_no_go_decision`, pot acabar en `GO`/`NO-GO`, emet JSON i no crea factures, no registra pagaments i no sincronitza legacy.
- L'execucio real de la bateria queda pendent fins que hi hagi PHP, BD SIF/legacy de test i `SIF_REDSYS_MERCHANT_KEY` configurada.

## Fase 11: Integracio progressiva de canals

### Task 13: Ordre d'activacio de canals

**Files:**
- Modify: `sif/public/api/redsys/callback.php`
- Modify: intranet `Passar pagaments` al repo real
- Modify: ecommerce checkout al repo real

- [ ] **Step 1: Activar mode preproduccio**

Configurar `SIF_ENV=test` i BD separada. Prova vinculada: `SIF-PRE-001`.

- [ ] **Step 2: Activar Redsys curs normal en test**

Primer cas real controlat:

```text
Redsys valid -> redsys_notifications -> issueInvoice(payment) si no hi ha factura -> fact_rels -> payment_transaction -> payment_allocation
Duplicat mateix DS_ORDER -> resposta idempotent, sense nova factura ni pagament
```

Proves vinculades: `SIF-RED-001`, `SIF-RED-002`, `SIF-IDEM-001`.

Preparacio tecnica 2026-06-05:

- [x] Preparar el nucli `issueInvoice(payment)` per crear factura, registre fiscal, hash chain, `payment_transaction` i `payment_allocation` dins la mateixa transaccio idempotent quan factura i cobrament neixen junts.
- [x] Cablejar `sif/public/api/factures/issue.php` amb `PaymentPayloadValidator`, `PaymentRepository` i `PaymentStatusCalculator` per acceptar el bloc `payment` des de l'endpoint intern d'emissio.
- [x] Afegir prova d'integracio per Redsys normal controlat a nivell de servei: primera crida crea factura i pagament; segon reintent amb la mateixa clau reutilitza la factura, retorna el `uuid_payment` existent i no duplica registres.

Preparacio tecnica 2026-06-06:

- [x] Preparar `RedsysSignatureValidator` per validar notificacions Redsys amb `Ds_MerchantParameters` i `Ds_Signature`, sense copiar el secret antic del Drive ni hardcodejar cap clau.
- [x] Llegir la clau Redsys des de `SIF_REDSYS_MERCHANT_KEY` via `sif/config/sif.php`.
- [x] Cablejar `sif/public/api/redsys/callback.php` per acceptar el POST real de Redsys, verificar signatura i passar al SIF nomes un payload normalitzat (`ds_order`, `idpag`, `amount`, `response_code`).
- [x] Afegir prova unitària amb notificació Redsys signada de test que normalitza import en centims (`12000` -> `120.00`) i conserva `DS_ORDER`, `IDPAG` i `Ds_Response`.
- [x] Classificar la notificacio signada segons `Ds_Response`: `0..99` queda `VALIDATED`; resposta no autoritzada queda `ERROR`, sempre sense crear factura ni `payment_transaction`.
- [x] Preparar `RedsysInvoicePayloadBuilder` per construir el payload `issueInvoice(payment)` de curs normal nomes quan `redsys_notifications.STATUS = VALIDATED`, usant `DS_ORDER`/`IDPAG` signats com a font d'idempotencia i de relacio.
- [x] Preparar `LegacyCourseInvoicePayloadBuilder` per convertir un snapshot legacy de `inscripcions` + `curs` en payload fiscal base validable: receptor, linia de curs, import actual del pagament Redsys, IVA exempt i relacio `INSCRIPCIO`, sense consultar encara la BD antiga ni activar el callback fiscal real. El builder no usa `inscripcions.PAGAMENT` com a import de factura perquè es pagat acumulat historic.
- [x] Preparar `LegacyCourseSnapshotRepository` per carregar el snapshot antic de curs normal per `IDPAG`: consulta `inscripcions` amb `INSC CURS` `0`, `1` o `M`, consulta `curs` per `ANY`/`MES`/`CURS`, i adjunta l'import actual del pagament validat sense escriure a la BD antiga.
- [x] Preparar `RedsysCourseInvoiceService` com a orquestrador de servei: notificacio `VALIDATED` -> snapshot legacy -> payload fiscal base -> payload Redsys `issueInvoice(payment)` -> `InvoiceService`, amb reintent idempotent i sense escriure a legacy.
- [x] Preparar configuracio legacy per entorn (`SIF_LEGACY_DB_DSN`, `SIF_LEGACY_DB_USER`, `SIF_LEGACY_DB_PASSWORD`) i script CLI manual `sif/scripts/process-redsys-course.php` per provar un `DS_ORDER` ja `VALIDATED` en preproduccio. El script rebutja `SIF_ENV=production` i no parseja notificacions Redsys.
- [x] Preparar `sif/scripts/preflight-redsys-course.php` com a comprovacio de nomes lectura abans de processar curs normal: entorn no productiu, clau Redsys configurada, BD SIF, BD legacy, taules `redsys_notifications`, `payment_transaction`, `inscripcions`, `curs` i seed de `fiscal_chain_state`.
- [x] Preparar `sif/scripts/preview-redsys-course.php` com a dry-run de payload: llegeix `DS_ORDER` `VALIDATED`, carrega snapshot legacy, construeix payload `issueInvoice(payment)` i l'imprimeix en JSON sense crear factura, pagament, hash chain ni cua fiscal.
- [x] Preparar sincronitzacio legacy post-SIF com a opcio explicita del processador manual: `process-redsys-course.php DS_ORDER --sync-legacy` crida `LegacySyncService::syncAfterSifSuccess()` nomes despres d'un `issueInvoice(payment)` amb `ok=true`; sense el flag no escriu a legacy.
- [x] Mantenir el callback sense efectes fiscals ni economics: en aquesta subfase nomes registra `redsys_notifications`; encara no crida `issueInvoice()` ni `registerPayment()`.
- [ ] Executar aquest flux amb Redsys real en preproduccio. Requereix `php` disponible, BD MySQL de test, BD legacy de test, `SIF_REDSYS_MERCHANT_KEY` configurada, `SIF_LEGACY_DB_*` configurat, `preflight-sif.php` i `preflight-redsys-course.php` amb `ok=true`, `preview-redsys-course.php DS_ORDER` revisat i prova real de signatura Redsys.

- [ ] **Step 3: Activar factura abans de cobrament**

Flux:

```text
Intranet -> issueInvoice(EMESA_ABANS_COBRAMENT=1) -> pagament posterior -> registerPayment()
```

Prova vinculada: `SIF-FAC-001`.

Preparacio tecnica 2026-06-06:

- [x] Afegir `InvoiceBeforePaymentFlowTest` per verificar que una factura emesa abans de cobrar queda `EMESA_ABANS_COBRAMENT = 1`, `ESTAT_COBRAMENT = PENDING` i amb un sol registre fiscal/cua AEAT.
- [x] Verificar a nivell de prova que el pagament posterior per `registerPayment()` crea `payment_transaction` i `payment_allocation`, marca la factura com `PAID` i no afegeix cap nou `factura_registres`, `fiscal_queue` ni ordre fiscal.
- [x] Afegir `InvoiceBeforePaymentPayloadBuilder` i `InvoiceBeforePaymentService` per construir el payload de factura real pendent de cobrament, forçar `source_channel = INTRANET`, `EMESA_ABANS_COBRAMENT = 1`, idempotencia derivada de referencia i rebutjar qualsevol bloc `payment` inicial.
- [x] Afegir circuit CLI no productiu: `preflight-invoice-before-payment.php`, `preview-invoice-before-payment.php --payload-file=payload.json` i `process-invoice-before-payment.php --payload-file=payload.json`.
- [x] Afegir proves `InvoiceBeforePaymentServiceTest`, `InvoiceBeforePaymentPreviewScriptTest`, `InvoiceBeforePaymentPreproductionScriptTest` i `InvoiceBeforePaymentPreflightScriptTest` per assegurar que la preview/preflight no emeten factura, que el processador no registra pagaments i que no hi ha legacy/Redsys en aquest tall.
- [ ] Executar el flux amb PHP/MySQL de test i intranet/preproduccio abans d'activar-ho operativament: `preflight-invoice-before-payment.php`, `preview-invoice-before-payment.php --payload-file=payload.json`, `process-invoice-before-payment.php --payload-file=payload.json` i cobrament posterior per `registerPayment()`.

- [ ] **Step 4: Activar transferencies manuals**

Flux:

```text
Passar pagaments -> factura existent -> registerPayment()
Passar pagaments -> venda facturable sense factura -> issueInvoice(payment)
```

Prova vinculada: `SIF-PAY-001`.

Preparacio tecnica 2026-06-06:

- [x] Afegir `ManualPaymentPayloadBuilder` per construir el payload de `registerPayment()` quan administracio valida una transferencia a `Passar pagaments` contra una factura SIF existent.
- [x] Fixar la idempotencia documentada: amb referencia bancaria `TRANSFERENCIA|REF:{REFERENCIA_BANCARIA}`; sense referencia, fallback `TRANSFERENCIA|FACT:{NUM_FACT}|DATA:{DATA_PAG}|IMPORT:{IMPORT}|BANC:{BANC}`.
- [x] Afegir prova unitària `ManualPaymentPayloadBuilderTest` per validar import normalitzat, `source_channel = INTRANET`, assignacio unica a `payment_allocation` i compatibilitat amb `PaymentPayloadValidator`.
- [x] Afegir `ManualPaymentInvoiceRepository` i `ManualPaymentService` per localitzar una factura SIF existent per `UUID_FACTURA` o `NUM_VISIBLE` i executar `PaymentService::registerPayment()` amb el payload manual, sense crear cap factura nova ni tocar legacy.
- [x] Afegir prova d'integracio `ManualPaymentServiceTest` per validar pagament manual per UUID, pagament parcial per numero visible, idempotencia per referencia bancaria, `ESTAT_COBRAMENT` recalculat i absencia de nous registres fiscals.
- [x] Afegir `sif/scripts/preflight-manual-payment.php`, `sif/scripts/preview-manual-payment.php` i `sif/scripts/process-manual-payment.php`: preflight nomes SIF, preview dry-run del payload `registerPayment()` i processador CLI no productiu per factura existent.
- [x] Afegir proves estàtiques `ManualPaymentPreflightScriptTest`, `ManualPaymentPreviewScriptTest` i `ManualPaymentPreproductionScriptTest` per garantir que el circuit de factura existent no depen de legacy/Redsys, no construeix `InvoiceService` i no crida `issueInvoice()`.
- [x] Afegir `ManualCourseInvoicePayloadBuilder` per al subcas `efact == 0` antic de curs/inscripcio normal: snapshot legacy + transferencia validada -> payload `issueInvoice(payment)` amb `source_channel = INTRANET`.
- [x] Fixar idempotencia fiscal per transferencia de curs sense factura SIF prèvia: amb referencia `TRANSFERENCIA|CURS|IDPAG:{IDPAG}|REF:{REFERENCIA_BANCARIA}`; sense referencia, fallback `TRANSFERENCIA|CURS|IDPAG:{IDPAG}|DATA:{DATA_PAG}|IMPORT:{IMPORT}|BANC:{BANC}`.
- [x] Afegir prova d'integracio `ManualCourseInvoicePayloadBuilderTest` per validar que `issueInvoice(payment)` crea factura i cobrament inicial en una sola operacio, amb `payment_transaction.PROVIDER_REF`, `REFERENCIA_BANCARIA` i `IDPAG` preservats.
- [x] Afegir `ManualCourseInvoiceService` per orquestrar el curs manual: `IDPAG` -> `LegacyCourseSnapshotRepository` -> `ManualCourseInvoicePayloadBuilder` -> `InvoiceService::issueInvoice()`, retornant metadades `legacy_sync` sense escriure a legacy.
- [x] Afegir prova d'integracio `ManualCourseInvoiceServiceTest` per comprovar emissio idempotent de factura i pagament manual de curs, reutilitzacio en reintent i bloqueig d'`IDPAG` invalid abans de consultar legacy.
- [x] Afegir `sif/scripts/preview-manual-course.php` com a dry-run de preproduccio: carrega legacy per `IDPAG`, construeix payload `issueInvoice(payment)` manual i l'imprimeix en JSON sense crear factura, pagament, hash chain ni sync legacy.
- [x] Afegir prova estàtica `ManualCoursePreviewScriptTest` per garantir que la preview es CLI-only, rebutja produccio, no construeix `InvoiceService`, no crida `issueInvoice()` i no sincronitza legacy.
- [x] Afegir `sif/scripts/process-manual-course.php` com a processador manual de preproduccio: `IDPAG AMOUNT MOVEMENT_DATE` -> `ManualCourseInvoiceService` -> `issueInvoice(payment)`, amb `--sync-legacy` opcional nomes despres d'exit SIF.
- [x] Afegir prova estàtica `ManualCoursePreproductionScriptTest` per garantir que el processador es CLI-only, rebutja produccio, construeix serveis SIF propis, no depen de Redsys i exposa `legacy_sync_executed`.
- [x] Afegir `sif/scripts/preflight-manual-course.php` com a comprovacio de nomes lectura abans de preview/process manual: entorn no productiu, BD legacy configurada, BD SIF, taules fiscals/economiques minimes, taules legacy `inscripcions`/`curs` i seed de `fiscal_chain_state`.
- [x] Afegir prova estàtica `ManualCoursePreflightScriptTest` per garantir que el preflight manual no construeix l'orquestrador, no crida `issueInvoice()` i no depen de readiness Redsys.
- [ ] Executar `SIF-PAY-001` amb PHP/MySQL de test, `preflight-manual-payment.php`, `preview-manual-payment.php`, `process-manual-payment.php` i pantalla real de `Passar pagaments` abans d'activar el flux operatiu.

- [ ] **Step 5: Activar packs, grups, regals, USOC i codis promocionals**

Ordre recomanat:

```text
packs -> grups -> regals -> USOC -> codis promocionals
```

Motiu: packs nomes afegeixen diverses linies; grups afegeixen privacitat i receptor diferent; regals afegeixen comprador/destinatari; USOC afegeix doble factura alumne/entitat, receptor fiscal explicit per l'entitat i relacio interna amb la factura de l'alumne; codis promocionals no son canal propi, pero congelen el descompte dins `factura_linia`.

Preparacio tecnica 2026-06-07:

- [x] Afegir `LegacyPackInvoicePayloadBuilder` per convertir un snapshot legacy de pack en payload fiscal base `issueInvoice()`: `source_type = PACK`, una linia per curs, relacio `PACK`, relacions `INSCRIPCIO`, IVA exempt i totals agregats.
- [x] Fixar el primer criteri fiscal de pack normal: el primer curs queda sense descompte i la segona linia queda amb `DESC_ORIGEN = PACK`, `DESC_MODE = PERCENT`, `DESC_PCT = 25.00` i `DESC_IMPORT` congelat. Si el snapshot ja aporta imports fiscals explicits, es respecten; si no, el builder aplica nomes la regla documentada del pack normal.
- [x] Afegir `LegacyPackSnapshotRepository` com a lectura legacy read-only per `IDPAG`: carrega inscripcions `TIPUS_INSC = 'P'`, detecta `PACK|{ID_PACK}` a `OBSERVACIONS`, consulta `info_pack` i carrega `curs` per cada linia.
- [x] Generalitzar `RedsysInvoicePayloadBuilder` perquè la idempotencia Redsys mantingui `CURS` per defecte i pugui generar `REDSYS|PACK|IDPAG:{IDPAG}|ORDER:{DS_ORDER}` quan el payload base declara `source_type = PACK`.
- [x] Afegir proves `LegacyPackInvoicePayloadBuilderTest` i `LegacyPackSnapshotRepositoryTest` per validar payload multi-linia, descompte de pack, relacions, composicio amb Redsys validat i lectura legacy sense escriptures.
- [x] Afegir `RedsysPackInvoiceService` per orquestrar pack Redsys en preproduccio: notificacio `VALIDATED` -> snapshot legacy pack -> payload fiscal pack -> payload Redsys `issueInvoice(payment)` -> `InvoiceService`, amb reintent idempotent i metadades `legacy_sync`.
- [x] Afegir `sif/scripts/preview-redsys-pack.php` com a dry-run de payload: llegeix `DS_ORDER` `VALIDATED`, carrega pack legacy, construeix payload `PACK` i l'imprimeix en JSON sense crear factura, pagament, hash chain ni cua fiscal.
- [x] Afegir `sif/scripts/process-redsys-pack.php` com a processador manual de preproduccio: executa `RedsysPackInvoiceService` amb un `DS_ORDER` ja validat, rebutja produccio, no parseja POST Redsys i permet `--sync-legacy` nomes despres d'exit SIF.
- [x] Afegir `sif/scripts/preflight-redsys-pack.php` com a comprovacio de nomes lectura: entorn no productiu, clau Redsys, BD SIF, BD legacy, taules `factura_linia`, `fact_rels`, `payment_transaction`, `payment_allocation`, `redsys_notifications`, `inscripcions`, `curs`, `info_pack` i seed fiscal.
- [x] Afegir proves `RedsysPackInvoiceServiceTest`, `RedsysPackPreviewScriptTest`, `RedsysPackPreproductionScriptTest` i `RedsysPackPreflightScriptTest`.
- [x] Afegir `ManualPackInvoicePayloadBuilder` per convertir un pack legacy i una transferencia validada a `Passar pagaments` en payload `issueInvoice(payment)`: `source_channel = INTRANET`, idempotencia `TRANSFERENCIA|PACK|IDPAG:{IDPAG}|REF:{REFERENCIA_BANCARIA}` o fallback per data/import/banc, i bloc `payment` inicial.
- [x] Afegir `ManualPackInvoiceService` per orquestrar pack manual en preproduccio: `IDPAG` -> snapshot legacy pack -> payload fiscal pack manual -> `InvoiceService::issueInvoice()`, retornant metadades `legacy_sync` sense escriure a legacy.
- [x] Afegir `sif/scripts/preview-manual-pack.php`, `sif/scripts/process-manual-pack.php` i `sif/scripts/preflight-manual-pack.php` com a circuit manual equivalent al curs: preview dry-run, processador amb `--sync-legacy` opcional i preflight sense dependencia Redsys.
- [x] Afegir proves `ManualPackInvoicePayloadBuilderTest`, `ManualPackInvoiceServiceTest`, `ManualPackPreviewScriptTest`, `ManualPackPreproductionScriptTest` i `ManualPackPreflightScriptTest`.
- [x] Afegir `LegacyGroupSnapshotRepository` com a lectura legacy read-only per `IDPAG`: carrega inscripcions `TIPUS_INSC = 'G'`, responsable a `respGrups` i dades de curs per cada participant, sense escriure a la BD antiga.
- [x] Afegir `LegacyGroupInvoicePayloadBuilder` per convertir un snapshot legacy de grup en payload fiscal base `issueInvoice()`: `source_type = GRUP`, una linia per participant, receptor fiscal responsable/empresa, relacio `GRUP`, relacions `INSCRIPCIO` i `visible_alumne = 0` per privacitat.
- [x] Afegir proves `LegacyGroupInvoicePayloadBuilderTest` i `LegacyGroupSnapshotRepositoryTest` per validar receptor fiscal, linies per participant, descompte/import congelat quan el snapshot el porta, composicio amb Redsys `REDSYS|GRUP|...` i absencia de visibilitat completa a l'alumne.
- [ ] Validar SQL final de `descomptes_grup` i incorporar-lo al snapshot de grup abans d'activar cap circuit real de grup. Fins aquest punt, el builder respecta imports/descomptes explicits del snapshot i el repositori nomes carrega imports ja presents a `inscripcions`.
- [x] Afegir `LegacyGiftSnapshotRepository` com a lectura legacy read-only de `regal` per `ID` o `CODI`: carrega comprador, curs, codi regal, import, `FACT_REL`, origen/desti i observacions, sense actualitzar `FACT_REL`.
- [x] Afegir `LegacyGiftInvoicePayloadBuilder` per convertir un snapshot legacy de regal en payload fiscal base `issueInvoice()`: `source_type = REGAL`, factura al comprador, una linia fiscal `REGAL`, relacio `REGAL`, `visible_alumne = 0` i metadades comercials del regal sense crear inscripcio del destinatari.
- [x] Afegir proves `LegacyGiftInvoicePayloadBuilderTest` i `LegacyGiftSnapshotRepositoryTest` per validar comprador com a receptor fiscal, `SOURCE_TYPE = REGAL`, idempotencia base `LEGACY|REGAL|ID:{ID}`, composicio Redsys `REDSYS|REGAL|IDPAG:NULL|ORDER:{DS_ORDER}` i no emissio de factura al destinatari.
- [x] Afegir `RedsysGiftInvoiceService` com a orquestrador manual de preproduccio: notificacio `VALIDATED` + regal identificat per `ID` o `CODI` -> snapshot legacy -> payload `REGAL` -> payload Redsys -> `issueInvoice(payment)`, exigint que `redsys_notifications.IMPORT` coincideixi amb `regal.IMPORT`.
- [x] Afegir `sif/scripts/preflight-redsys-gift.php`, `sif/scripts/preview-redsys-gift.php` i `sif/scripts/process-redsys-gift.php`: preflight de nomes lectura, preview dry-run i processador CLI no productiu. El processador no parseja POST Redsys, no crida `LegacySyncService` i no actualitza `regal.FACT_REL` en aquest tall.
- [x] Afegir proves `RedsysGiftInvoiceServiceTest`, `RedsysGiftPreviewScriptTest`, `RedsysGiftPreproductionScriptTest` i `RedsysGiftPreflightScriptTest` per validar emissio idempotent, rebuig de notificacio no validada, rebuig d'import desquadrat i contracte dels scripts.
- [x] Afegir `ManualGiftInvoicePayloadBuilder` per transformar un snapshot `REGAL` i un cobrament validat manualment de `Passar pagaments` en `issueInvoice(payment)` amb `source_channel = INTRANET`, idempotencia per `REGAL.ID`/referencia o fallback data/import/banc, i `payment_transaction` inicial.
- [x] Afegir `ManualGiftInvoiceService` com a orquestrador de preproduccio: regal per `ID` o `CODI` -> snapshot legacy -> payload manual -> `InvoiceService::issueInvoice()`, retornant metadades de relacio sense escriure a legacy.
- [x] Afegir `sif/scripts/preflight-manual-gift.php`, `sif/scripts/preview-manual-gift.php` i `sif/scripts/process-manual-gift.php`: preflight de nomes lectura sense Redsys, preview dry-run i processador CLI no productiu. En aquest tall no hi ha `--sync-legacy`, no s'actualitza `regal.FACT_REL` i no es crea cap inscripcio del destinatari.
- [x] Afegir proves `ManualGiftInvoiceServiceTest`, `ManualGiftPreviewScriptTest`, `ManualGiftPreproductionScriptTest` i `ManualGiftPreflightScriptTest` per validar emissio idempotent, cerca per `ID`/`CODI`, rebuig d'import desquadrat i contracte dels scripts manuals.
- [ ] Validar SQL final de `regal`, `FACT_REL`, `ORIGEN`, `DESTI`, `CODI` i relacio amb inscripcio posterior abans d'activar circuits Redsys/manuals de regal.
- [x] Afegir `LegacyUsocSnapshotRepository` com a lectura legacy read-only per `IDPAG`: carrega inscripcio `TIPUS_DESC = 4`, exigeix `VALID_DESC = 1`, carrega `curs`, congela import pagat per l'alumne i nomes accepta import USOC si es passa explicitament al snapshot.
- [x] Afegir `LegacyUsocInvoicePayloadBuilder` per convertir el snapshot USOC en dos payloads fiscals separats: `USOC_ALUMNE` amb `source_channel = REDSYS`, descompte visible `USOC`, relacio `INSCRIPCIO` visible a l'alumne; i `USOC_ENTITAT` amb `source_channel = INTRANET`, receptor fiscal USOC explicit, relacio `USOC_ENTITY`, `visible_alumne = 0` i idempotencia vinculada a la factura de l'alumne.
- [x] Afegir proves `LegacyUsocInvoicePayloadBuilderTest` i `LegacyUsocSnapshotRepositoryTest` per validar doble factura, composicio Redsys `REDSYS|USOC_ALUMNE|IDPAG:{IDPAG}|ORDER:{DS_ORDER}`, factura d'entitat pendent de cobrament sense `payment_transaction`, i rebuig de descomptes no USOC o no validats.
- [ ] Confirmar dades fiscals completes de l'entitat USOC abans d'activar cap factura `USOC_ENTITAT` real.
- [x] Afegir suport a `LegacyCourseInvoicePayloadBuilder` per congelar codis promocionals i promocions temporals ja validades pel canal: `import_base`, `discount`, `total`, `DESC_ORIGEN`, `DESC_MODE`, `DESC_ID`, `DESC_CODI_PROMO`, `DESC_PCT`, `DESC_IMPORT`, text visible generic i motiu intern.
- [x] Afegir proves al `LegacyCourseInvoicePayloadBuilderTest` per validar snapshot `CODI_PROMO`, persistencia dels camps `DESC_*` en `factura_linia` i promocio temporal `descomptes.TIPUS` 11-99 amb `DESC_ID`.
- [ ] Confirmar SQL final de `promocions`, `descomptes.TIPUS` 11-99 i el punt exacte on ecommerce/intranet creen el snapshot fiscal abans de Redsys o `Passar pagaments`.
- [ ] Executar el circuit Redsys de pack amb PHP/MySQL de test i dades legacy de preproduccio abans d'activar cap endpoint o callback automatic de pack.
- [ ] Executar el circuit manual de pack amb PHP/MySQL de test, `Passar pagaments` i dades legacy de preproduccio abans d'integrar-lo operativament.
- [ ] Executar el payload de grup amb PHP/MySQL de test, dades legacy de preproduccio i prova de privacitat abans d'afegir orquestradors Redsys/manuals de grup.
- [ ] Executar els circuits de regal amb PHP/MySQL de test i dades legacy de preproduccio: Redsys (`preflight-redsys-gift.php`, `preview-redsys-gift.php DS_ORDER (--gift-id=ID|--gift-code=CODI)`, `process-redsys-gift.php DS_ORDER (--gift-id=ID|--gift-code=CODI)`) i manual (`preflight-manual-gift.php`, `preview-manual-gift.php (--gift-id=ID|--gift-code=CODI) AMOUNT MOVEMENT_DATE`, `process-manual-gift.php (--gift-id=ID|--gift-code=CODI) AMOUNT MOVEMENT_DATE`), amb prova d'import desquadrat, reintent idempotent i bescanvi sense segona factura.
- [ ] Executar el payload USOC amb PHP/MySQL de test, dades legacy de preproduccio, factura alumne Redsys validada, factura entitat amb receptor explicit i prova de privacitat.
- [ ] Executar curs normal amb codi promocional i promocio temporal amb PHP/MySQL de test, callback duplicat i verificacio dels camps `DESC_*` immutables.

## Self-review del pla

- Cobertura de `issueInvoice()`: coberta a Fases 1, 4, 7, 10 i 11.
- Cobertura de `registerPayment()`: coberta a Fase 5 i integracions de Fase 11.
- Cobertura idempotencia: coberta a Tasks 5, 6, 7 i 10.
- Cobertura hash chain: coberta a Tasks 4, 5 i 6.
- Cobertura taules fiscals: coberta a Task 1.
- Cobertura `factura_linia`: coberta a Tasks 1 i 5.
- Cobertura `payment_transaction` i `payment_allocation`: coberta a Tasks 1, 7 i 10.
- Cobertura relacio amb BD antiga: coberta a Task 8.
- Cobertura proves/go-no-go: coberta a Task 12 i Fase 11.

## Criteri de finalitzacio

El nucli SIF es pot considerar implementat tecnicament quan:

- passen les proves `SifSchemaTest`, `IssueInvoiceTest`, `RegisterPaymentTest`, `RedsysCallbackTest`, `LegacyRelationsTest` i `ConcurrencySmokeTest`;
- `issueInvoice()` retorna la mateixa factura en reintents amb la mateixa clau idempotent;
- dues factures diferents tenen `NUM_VISIBLE` unic i `FISCAL_ORDER` lineal;
- `registerPayment()` no crea `factura_registres` ni modifica hash chain;
- una factura abans de cobrament es cobra posteriorment sense duplicar factura;
- `fact_rels` conserva `FACTURA_RELACIONADA`, `IDPAG`, `DS_ORDER`, `SOURCE_TYPE` i visibilitat;
- la sincronitzacio amb BD antiga nomes passa despres de resposta SIF correcta;
- el preflight retorna `ok=true`;
- la bateria go/no-go documental conserva evidencia real en preproduccio.
