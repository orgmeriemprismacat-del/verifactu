<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Exception\SifException;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';
$env = (string) ($config['env'] ?? 'local');
if ($env === 'production' && getenv('SIF_UC023_EVIDENCE_ALLOW_PRODUCTION') !== '1') {
    fwrite(STDERR, "Refusing UC-023 evidence query with SIF_ENV=production.\n");
    exit(1);
}

$selector = parseSelector(array_slice($argv, 1));
if ($selector === null) {
    usage();
}

try {
    $db = ConnectionFactory::make($config);
    $payment = loadPayment($db, $selector);
    if ($payment === null) {
        throw SifException::validation('UC-023 payment not found');
    }

    $allocStmt = $db->prepare(
        'SELECT pa.UUID_FACTURA, pa.IMPORT_ASSIGNAT, pa.TIPUS_ASSIGNACIO,
                f.NUM_VISIBLE, f.TOTAL, f.ESTAT_COBRAMENT, f.ESTAT_FACTURA, f.ESTAT_AEAT,
                (SELECT COUNT(*) FROM factura_registres fr WHERE fr.UUID_FACTURA = f.UUID_FACTURA) AS FISCAL_REGISTER_COUNT
         FROM payment_allocation pa
         INNER JOIN factura f ON f.UUID_FACTURA = pa.UUID_FACTURA
         WHERE pa.UUID_PAYMENT = ?
         ORDER BY pa.ID'
    );
    $allocStmt->execute([$payment['UUID_PAYMENT']]);
    $allocations = $allocStmt->fetchAll(\PDO::FETCH_ASSOC);

    $terminalStmt = $db->prepare(
        "SELECT CORRELATION_ID
         FROM payment_action_event
         WHERE UUID_PAYMENT = ?
           AND IS_TERMINAL = 1
         ORDER BY ID DESC
         LIMIT 1"
    );
    $terminalStmt->execute([$payment['UUID_PAYMENT']]);
    $correlationId = trim((string) ($terminalStmt->fetchColumn() ?: ''));

    $paymentEvents = [];
    $operationalEvents = [];
    $auditEvents = [];

    if ($correlationId !== '') {
        $stmt = $db->prepare(
            'SELECT UUID_EVENT, UUID_PAYMENT, REQUEST_ID, CORRELATION_ID,
                    ACTION, RESULT, IS_TERMINAL, ACTOR_ID, ACTOR_ROLE,
                    REASON_CODE, ERROR_CODE, OCCURRED_AT
             FROM payment_action_event
             WHERE CORRELATION_ID = ?
             ORDER BY ID'
        );
        $stmt->execute([$correlationId]);
        $paymentEvents = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $stmt = $db->prepare(
            'SELECT UUID_OPERATIONAL_EVENT, OPERATION_TYPE, SOURCE_TYPE, SOURCE_ID,
                    UUID_FACTURA, UUID_PAYMENT, FISCAL_IMPACT, ECONOMIC_IMPACT,
                    STATUS, REASON_CODE, ACTOR_ID, ACTOR_ROLE, CORRELATION_ID,
                    OCCURRED_AT
             FROM operational_event
             WHERE CORRELATION_ID = ?
             ORDER BY ID'
        );
        $stmt->execute([$correlationId]);
        $operationalEvents = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $stmt = $db->prepare(
            'SELECT UUID_EVENT, REQUEST_ID, CORRELATION_ID, ACTION, RESULT,
                    RESOURCE_TYPE, RESOURCE_ID, ACTOR_ID, ACTOR_ROLE,
                    REASON_CODE, ERROR_CODE, OCCURRED_AT
             FROM sif_audit_event
             WHERE CORRELATION_ID = ?
             ORDER BY ID'
        );
        $stmt->execute([$correlationId]);
        $auditEvents = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    $paymentResults = array_map(
        static fn (array $row): string => (string) ($row['RESULT'] ?? ''),
        $paymentEvents
    );

    $checks = [
        'single_payment_match' => true,
        'has_allocation' => count($allocations) > 0,
        'has_correlation' => $correlationId !== '',
        'has_requested_payment_event' => in_array('REQUESTED', $paymentResults, true),
        'has_terminal_payment_event' =>
            in_array('SUCCEEDED', $paymentResults, true)
            || in_array('REUSED', $paymentResults, true),
        'has_operational_event' => count($operationalEvents) > 0,
        'has_sif_audit_event' => count($auditEvents) > 0,
        'fiscal_register_count_is_observation_only' => true,
    ];

    $output = [
        'ok' => !in_array(false, $checks, true),
        'evidence_type' => 'UC023_INSTALLMENT_PAYMENT',
        'env' => $env,
        'payment' => [
            'uuid_payment' => $payment['UUID_PAYMENT'],
            'idempotency_key' => $payment['IDEMPOTENCY_KEY'],
            'movement_type' => $payment['TIPUS_MOVIMENT'],
            'method' => $payment['METODE'],
            'source_channel' => $payment['SOURCE_CHANNEL'],
            'amount' => $payment['IMPORT'],
            'movement_date' => $payment['DATA_MOVIMENT'],
            'provider_ref' => $payment['PROVIDER_REF'],
            'ds_order' => $payment['DS_ORDER'],
            'reference' => $payment['REFERENCIA_BANCARIA'],
            'status' => $payment['ESTAT'],
        ],
        'correlation_id' => $correlationId !== '' ? $correlationId : null,
        'allocations' => array_map(
            static fn (array $row): array => [
                'uuid_factura' => $row['UUID_FACTURA'],
                'num_visible' => $row['NUM_VISIBLE'],
                'amount' => $row['IMPORT_ASSIGNAT'],
                'allocation_type' => $row['TIPUS_ASSIGNACIO'],
                'invoice_total' => $row['TOTAL'],
                'invoice_payment_status' => $row['ESTAT_COBRAMENT'],
                'invoice_status' => $row['ESTAT_FACTURA'],
                'invoice_aeat_status' => $row['ESTAT_AEAT'],
                'fiscal_register_count' => (int) $row['FISCAL_REGISTER_COUNT'],
            ],
            $allocations
        ),
        'payment_action_events' => array_map(
            static fn (array $row): array => [
                'uuid_event' => $row['UUID_EVENT'],
                'uuid_payment' => $row['UUID_PAYMENT'],
                'request_id' => $row['REQUEST_ID'],
                'correlation_id' => $row['CORRELATION_ID'],
                'action' => $row['ACTION'],
                'result' => $row['RESULT'],
                'is_terminal' => (int) $row['IS_TERMINAL'] === 1,
                'actor_id' => $row['ACTOR_ID'],
                'actor_role' => $row['ACTOR_ROLE'],
                'reason_code' => $row['REASON_CODE'],
                'error_code' => $row['ERROR_CODE'],
                'occurred_at' => $row['OCCURRED_AT'],
            ],
            $paymentEvents
        ),
        'operational_events' => array_map(
            static fn (array $row): array => [
                'uuid_operational_event' => $row['UUID_OPERATIONAL_EVENT'],
                'operation_type' => $row['OPERATION_TYPE'],
                'source_type' => $row['SOURCE_TYPE'],
                'source_id' => $row['SOURCE_ID'],
                'uuid_factura' => $row['UUID_FACTURA'],
                'uuid_payment' => $row['UUID_PAYMENT'],
                'fiscal_impact' => $row['FISCAL_IMPACT'],
                'economic_impact' => $row['ECONOMIC_IMPACT'],
                'status' => $row['STATUS'],
                'reason_code' => $row['REASON_CODE'],
                'actor_id' => $row['ACTOR_ID'],
                'actor_role' => $row['ACTOR_ROLE'],
                'correlation_id' => $row['CORRELATION_ID'],
                'occurred_at' => $row['OCCURRED_AT'],
            ],
            $operationalEvents
        ),
        'sif_audit_events' => array_map(
            static fn (array $row): array => [
                'uuid_event' => $row['UUID_EVENT'],
                'request_id' => $row['REQUEST_ID'],
                'correlation_id' => $row['CORRELATION_ID'],
                'action' => $row['ACTION'],
                'result' => $row['RESULT'],
                'resource_type' => $row['RESOURCE_TYPE'],
                'resource_id' => $row['RESOURCE_ID'],
                'actor_id' => $row['ACTOR_ID'],
                'actor_role' => $row['ACTOR_ROLE'],
                'reason_code' => $row['REASON_CODE'],
                'error_code' => $row['ERROR_CODE'],
                'occurred_at' => $row['OCCURRED_AT'],
            ],
            $auditEvents
        ),
        'checks' => $checks,
    ];

    echo json_encode(
        $output,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ), PHP_EOL;
    exit(0);
} catch (\Throwable $exception) {
    echo json_encode([
        'ok' => false,
        'evidence_type' => 'UC023_INSTALLMENT_PAYMENT',
        'error' => $exception->getMessage(),
        'code' => $exception->getCode(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(1);
}

function parseSelector(array $args): ?array
{
    $selectors = [];
    foreach ($args as $arg) {
        $arg = (string) $arg;
        foreach ([
            'uuid_payment' => '--uuid-payment=',
            'reference' => '--reference=',
            'ds_order' => '--ds-order=',
        ] as $type => $prefix) {
            if (str_starts_with($arg, $prefix)) {
                $value = trim(substr($arg, strlen($prefix)));
                if ($value !== '') {
                    $selectors[] = ['type' => $type, 'value' => $value];
                }
            }
        }
    }

    return count($selectors) === 1 ? $selectors[0] : null;
}

function loadPayment(\PDO $db, array $selector): ?array
{
    $type = (string) ($selector['type'] ?? '');
    $value = (string) ($selector['value'] ?? '');

    $columns = [
        'uuid_payment' => 'UUID_PAYMENT',
        'reference' => 'REFERENCIA_BANCARIA',
        'ds_order' => 'DS_ORDER',
    ];
    if (!isset($columns[$type])) {
        return null;
    }

    $sql = 'SELECT * FROM payment_transaction WHERE ' . $columns[$type] . ' = ? ORDER BY ID ASC';
    $stmt = $db->prepare($sql);
    $stmt->execute([$value]);
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    if (count($rows) > 1) {
        throw SifException::conflict('UC-023 evidence selector matches more than one payment');
    }

    return $rows[0] ?? null;
}

function usage(): void
{
    fwrite(
        STDERR,
        "Usage: php sif/scripts/verify-manual-installment-evidence.php (--uuid-payment=UUID|--reference=REF|--ds-order=ORDER)\n"
    );
    exit(1);
}
