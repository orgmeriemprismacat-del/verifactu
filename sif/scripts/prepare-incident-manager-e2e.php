<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Repository\IncidentActionRepository;
use Prisma\Sif\Repository\IncidentRepository;
use Prisma\Sif\Service\IncidentLifecycleService;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$args = $argv;
array_shift($args);
$runId = trim((string) ($args[0] ?? ''));

if (
    count($args) !== 1
    || $runId === ''
    || strlen($runId) > 80
    || preg_match('/^[A-Za-z0-9._:-]+$/D', $runId) !== 1
) {
    fwrite(STDERR, "Usage: php sif/scripts/prepare-incident-manager-e2e.php <run_id>\n");
    exit(2);
}

$baseDir = dirname(__DIR__);
$config = require $baseDir . '/config/sif.php';
$environment = (string) ($config['env'] ?? 'local');
$incidentConfig = (array) ($config['incidents'] ?? []);
$readRoles = normalizeRoles((array) ($incidentConfig['read_roles'] ?? []));
$manageRoles = normalizeRoles((array) ($incidentConfig['manage_roles'] ?? []));
$managerRole = strtoupper(trim((string) (getenv('SIF_E2E_INCIDENT_MANAGER_ROLE') ?: '')));
$ack = (string) (getenv('SIF_UC008_MANAGER_E2E_PREPARE') ?: '');

$result = [
    'ok' => false,
    'scope' => 'uc-008-manager-e2e-prepare',
    'production_authorized' => false,
    'environment' => $environment,
    'run_id' => $runId,
    'mutation_scope' => 'synthetic_incident_only',
    'fiscal_or_payment_mutation_allowed' => false,
    'prepared_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
    'checks' => [
        'environment_not_production' => in_array($environment, ['test', 'preproduction'], true),
        'explicit_mutation_ack' => hash_equals('YES', $ack),
        'manage_roles_configured' => $manageRoles !== [],
        'manager_role_configured' => $managerRole !== '',
        'manager_role_allowed' => $managerRole !== '' && in_array($managerRole, $manageRoles, true),
        'manager_role_can_read' => $managerRole !== '' && in_array($managerRole, $readRoles, true),
    ],
];

$failed = failedChecks($result['checks']);
if ($failed !== []) {
    $result['failed'] = $failed;
    output($result, 1);
}

try {
    $db = ConnectionFactory::make($config);
    $repository = new IncidentRepository();
    $service = new IncidentLifecycleService(
        $db,
        new TransactionRunner($db),
        $repository,
        new IncidentActionRepository(),
        $readRoles,
        $manageRoles
    );

    $correlationId = 'UC008-E2E-MANAGER:' . $runId;
    $actor = [
        'actor_id' => 'uc008-e2e-preparer',
        'roles' => [$managerRole],
        'request_id' => $correlationId,
    ];

    $opened = $service->open($actor, [
        'source_type' => 'UC008_E2E',
        'source_id' => $runId,
        'type' => 'UC008_E2E_MANAGER',
        'message' => 'Synthetic UC-008 manager E2E incident for run ' . $runId,
        'severity' => 'LOW',
        'reason_code' => 'E2E_OPEN',
        'idempotency_key' => 'UC008|MANAGER-E2E|' . $runId,
        'correlation_id' => $correlationId,
    ]);

    $incident = $repository->findById($db, (int) $opened['incident_id']);
    $result['checks']['incident_created_or_reused'] = is_array($incident);
    $result['checks']['synthetic_type_exact'] =
        is_array($incident)
        && (string) ($incident['TIPUS_INCIDENCIA'] ?? '') === 'UC008_E2E_MANAGER';
    $result['checks']['synthetic_source_exact'] =
        is_array($incident)
        && (string) ($incident['SOURCE_TYPE'] ?? '') === 'UC008_E2E';
    $result['checks']['starts_open'] =
        is_array($incident)
        && (string) ($incident['ESTAT'] ?? '') === 'OPEN';
    $result['checks']['correlation_exact'] =
        is_array($incident)
        && hash_equals($correlationId, (string) ($incident['CORRELATION_ID'] ?? ''));

    $result['incident'] = [
        'incident_id' => (int) $opened['incident_id'],
        'uuid_incident' => (string) $opened['uuid_incident'],
        'reused' => (bool) ($opened['reused'] ?? false),
        'correlation_id' => $correlationId,
        'type' => 'UC008_E2E_MANAGER',
        'source_type' => 'UC008_E2E',
        'status' => (string) ($incident['ESTAT'] ?? ''),
    ];
} catch (Throwable $exception) {
    $result['checks']['database_operation_ok'] = false;
    $result['error'] = $exception->getMessage();
}

if (!array_key_exists('database_operation_ok', $result['checks'])) {
    $result['checks']['database_operation_ok'] = true;
}

$failed = failedChecks($result['checks']);
$result['ok'] = $failed === [];
if ($failed !== []) {
    $result['failed'] = $failed;
}

output($result, $result['ok'] ? 0 : 1);

function normalizeRoles(array $roles): array
{
    $normalized = [];
    foreach ($roles as $role) {
        $role = strtoupper(trim((string) $role));
        if ($role !== '') {
            $normalized[$role] = true;
        }
    }
    $result = array_keys($normalized);
    sort($result, SORT_STRING);
    return $result;
}

function failedChecks(array $checks): array
{
    return array_keys(array_filter($checks, static fn ($value): bool => $value !== true));
}

function output(array $result, int $exitCode): never
{
    echo json_encode(
        $result,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ), PHP_EOL;
    exit($exitCode);
}
