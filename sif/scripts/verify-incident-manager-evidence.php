<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$args = $argv;
array_shift($args);
if (count($args) !== 1 || !ctype_digit((string) $args[0]) || (int) $args[0] < 1) {
    fwrite(STDERR, "Usage: php sif/scripts/verify-incident-manager-evidence.php <incident_id>\n");
    exit(2);
}

$incidentId = (int) $args[0];
$baseDir = dirname(__DIR__);
$config = require $baseDir . '/config/sif.php';
$environment = (string) ($config['env'] ?? 'local');
$manageRoles = normalizeRoles((array) (($config['incidents'] ?? [])['manage_roles'] ?? []));

$result = [
    'ok' => false,
    'scope' => 'uc-008-manager-e2e-evidence',
    'production_authorized' => false,
    'read_only' => true,
    'environment' => $environment,
    'verified_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
    'incident_id' => $incidentId,
    'checks' => [
        'environment_not_production' => in_array($environment, ['test', 'preproduction'], true),
        'manage_roles_configured' => $manageRoles !== [],
    ],
];

try {
    $db = ConnectionFactory::make($config);

    $stmt = $db->prepare(
        'SELECT ID, UUID_INCIDENT, TIPUS_INCIDENCIA, SOURCE_TYPE, ESTAT, ASSIGNED_TO,
                CORRELATION_ID, RESOLVED_AT, RESOLUTION_NOTES, CLOSURE_CRITERIA
         FROM errors_verifactu
         WHERE ID = ?
         LIMIT 1'
    );
    $stmt->execute([$incidentId]);
    $incident = $stmt->fetch(PDO::FETCH_ASSOC);

    $result['checks']['incident_exists'] = is_array($incident);

    if (is_array($incident)) {
        $result['incident'] = [
            'id' => (int) $incident['ID'],
            'uuid_incident' => (string) $incident['UUID_INCIDENT'],
            'type' => (string) $incident['TIPUS_INCIDENCIA'],
            'source_type' => (string) ($incident['SOURCE_TYPE'] ?? ''),
            'status' => (string) $incident['ESTAT'],
            'correlation_id' => (string) ($incident['CORRELATION_ID'] ?? ''),
        ];

        $result['checks']['synthetic_manager_incident'] =
            strtoupper((string) $incident['TIPUS_INCIDENCIA']) === 'UC008_E2E_MANAGER'
            && strtoupper((string) ($incident['SOURCE_TYPE'] ?? '')) === 'UC008_E2E';
        $result['checks']['incident_resolved'] = (string) $incident['ESTAT'] === 'RESOLVED';
        $result['checks']['assignee_present'] = trim((string) ($incident['ASSIGNED_TO'] ?? '')) !== '';
        $result['checks']['resolved_at_present'] = trim((string) ($incident['RESOLVED_AT'] ?? '')) !== '';
        $result['checks']['resolution_notes_present'] =
            trim((string) ($incident['RESOLUTION_NOTES'] ?? '')) !== '';
        $result['checks']['closure_criteria_present'] =
            trim((string) ($incident['CLOSURE_CRITERIA'] ?? '')) !== '';

        $actions = actionRows($db, $incidentId);
        $result['action_count'] = count($actions);

        foreach (['ASSIGN', 'ADD_EVIDENCE', 'RESOLVE'] as $requiredAction) {
            $matches = array_values(array_filter(
                $actions,
                static fn (array $row): bool =>
                    strtoupper((string) ($row['ACTION_TYPE'] ?? '')) === $requiredAction
            ));
            $key = strtolower($requiredAction);
            $result['checks'][$key . '_present'] = $matches !== [];

            if ($matches !== []) {
                $last = $matches[count($matches) - 1];
                $actorRole = strtoupper(trim((string) ($last['ACTOR_ROLE'] ?? '')));
                $result['checks'][$key . '_manager_role'] =
                    $actorRole !== '' && in_array($actorRole, $manageRoles, true);
                $result['checks'][$key . '_actor_present'] =
                    trim((string) ($last['ACTOR_ID'] ?? '')) !== '';
                $result['checks'][$key . '_correlation_matches'] =
                    trim((string) ($last['CORRELATION_ID'] ?? '')) !== ''
                    && hash_equals(
                        (string) ($incident['CORRELATION_ID'] ?? ''),
                        (string) ($last['CORRELATION_ID'] ?? '')
                    );
            } else {
                $result['checks'][$key . '_manager_role'] = false;
                $result['checks'][$key . '_actor_present'] = false;
                $result['checks'][$key . '_correlation_matches'] = false;
            }
        }

        $result['checks']['add_evidence_payload_present'] =
            actionHasEvidence($actions, 'ADD_EVIDENCE');
        $result['checks']['resolve_evidence_payload_present'] =
            actionHasEvidence($actions, 'RESOLVE');
    } else {
        foreach ([
            'synthetic_manager_incident',
            'incident_resolved',
            'assignee_present',
            'resolved_at_present',
            'resolution_notes_present',
            'closure_criteria_present',
            'assign_present',
            'assign_manager_role',
            'assign_actor_present',
            'assign_correlation_matches',
            'add_evidence_present',
            'add_evidence_manager_role',
            'add_evidence_actor_present',
            'add_evidence_correlation_matches',
            'resolve_present',
            'resolve_manager_role',
            'resolve_actor_present',
            'resolve_correlation_matches',
            'add_evidence_payload_present',
            'resolve_evidence_payload_present',
        ] as $check) {
            $result['checks'][$check] = false;
        }
    }
} catch (Throwable $exception) {
    $result['checks']['database_query_ok'] = false;
    $result['error'] = $exception->getMessage();
}

if (!array_key_exists('database_query_ok', $result['checks'])) {
    $result['checks']['database_query_ok'] = true;
}

$failed = array_keys(array_filter(
    $result['checks'],
    static fn ($value): bool => $value !== true
));
$result['ok'] = $failed === [];
if ($failed !== []) {
    $result['failed'] = $failed;
}

echo json_encode(
    $result,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
), PHP_EOL;

exit($result['ok'] ? 0 : 1);

function actionRows(PDO $db, int $incidentId): array
{
    $stmt = $db->prepare(
        'SELECT ACTION_TYPE, ACTOR_ID, ACTOR_ROLE, EVIDENCE_JSON, CORRELATION_ID, CREATED_AT, ID
         FROM sif_incident_action
         WHERE INCIDENT_ID = ?
         ORDER BY CREATED_AT, ID'
    );
    $stmt->execute([$incidentId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function actionHasEvidence(array $actions, string $type): bool
{
    foreach ($actions as $action) {
        if (strtoupper((string) ($action['ACTION_TYPE'] ?? '')) !== $type) {
            continue;
        }
        $raw = trim((string) ($action['EVIDENCE_JSON'] ?? ''));
        if ($raw === '') {
            continue;
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && $decoded !== []) {
            return true;
        }
    }
    return false;
}

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
