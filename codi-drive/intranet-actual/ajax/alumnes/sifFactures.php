<?php

$root = dirname(__DIR__, 2);
chdir($root);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

$uc007Enabled = filter_var(
    getenv('SIF_UC007_QUERY_ENABLED') ?: '0',
    FILTER_VALIDATE_BOOLEAN
);

if (!$uc007Enabled) {
    http_response_code(200);
    echo json_encode([
        'ok' => true,
        'resolution' => 'FEATURE_DISABLED',
        'results' => [],
        'count' => 0,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return;
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    return;
}

ob_start();
require_once $root . '/inc/comprovarSessio.php';
$sessionValidationOutput = ob_get_clean();

if (!isset($configOk) || !$configOk || !isset($_SESSION['usuari'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Session not authorized']);
    return;
}

require_once $root . '/SifInternalApiClient.php';

$usuariObject = null;

try {
    $usuariObject = unserialize($_SESSION['usuari']);
    if (!is_object($usuariObject)) {
        throw new RuntimeException('Invalid authenticated session');
    }

    $actorText = $usuariObject->getUsuari();
    $actorId = is_object($actorText) && method_exists($actorText, 'get')
        ? trim((string) $actorText->get())
        : '';
    $roles = $usuariObject->getRols();
    if (!is_array($roles)) {
        $roles = [];
    }

    if ($actorId === '' || $roles === []) {
        throw new RuntimeException('Authenticated actor has no usable identity or roles');
    }

    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody === false ? '' : $rawBody, true);
    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
        return;
    }

    $client = new SifInternalApiClient();
    $action = strtolower(trim((string) ($payload['action'] ?? '')));

    if ($action === 'view') {
        $response = $client->viewInvoice(
            $actorId,
            $roles,
            (string) ($payload['uuid_factura'] ?? '')
        );
    } elseif ($action === 'view_by_enrollment') {
        $idInsc = (string) ($payload['id_insc'] ?? '');
        if (!ctype_digit($idInsc) || (int) $idInsc <= 0) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Invalid enrollment id']);
            return;
        }

        $matches = $client->searchInvoices(
            $actorId,
            $roles,
            ['source_ids' => [(int) $idInsc]],
            20
        );

        $matchStatus = (int) ($matches['_http_status'] ?? 200);
        if ($matchStatus >= 400) {
            $response = $matches;
        } else {
            $results = is_array($matches['results'] ?? null) ? $matches['results'] : [];

            if (count($results) === 0) {
                $response = [
                    'ok' => true,
                    'resolution' => 'NO_SIF',
                    'results' => [],
                    '_http_status' => 200,
                ];
            } elseif (count($results) === 1) {
                $uuid = (string) ($results[0]['uuid_factura'] ?? '');
                $response = $client->viewInvoice($actorId, $roles, $uuid);
                $response['resolution'] = 'VIEW';
            } else {
                $response = [
                    'ok' => true,
                    'resolution' => 'MULTIPLE',
                    'results' => $results,
                    'count' => count($results),
                    '_http_status' => 200,
                ];
            }
        }
    } elseif ($action === 'search') {
        $criteria = $payload['criteria'] ?? [];
        if (!is_array($criteria)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Invalid invoice search criteria']);
            return;
        }

        $limit = (int) ($payload['limit'] ?? 50);
        $participantDocument = strtoupper(trim((string) ($criteria['participant_document'] ?? '')));
        $participantEmail = strtolower(trim((string) ($criteria['participant_email'] ?? '')));
        unset($criteria['participant_document'], $criteria['participant_email']);

        if ($participantDocument !== '' || $participantEmail !== '') {
            if (strlen($participantDocument) > 32 || strlen($participantEmail) > 190) {
                http_response_code(422);
                echo json_encode(['ok' => false, 'error' => 'Invalid participant identity']);
                return;
            }

            $receiverCriteria = $criteria;
            if ($participantDocument !== '') {
                $receiverCriteria['billing_nif'] = $participantDocument;
            }
            if ($participantEmail !== '') {
                $receiverCriteria['billing_email'] = $participantEmail;
            }

            $responses = [
                $client->searchInvoices($actorId, $roles, $receiverCriteria, $limit),
            ];

            $sourceIds = enrollmentIdsByIdentity($participantDocument, $participantEmail);
            if ($sourceIds !== []) {
                $participantCriteria = $criteria;
                $participantCriteria['source_ids'] = $sourceIds;
                $responses[] = $client->searchInvoices(
                    $actorId,
                    $roles,
                    $participantCriteria,
                    $limit
                );
            }

            $response = mergeInvoiceSearchResponses($responses, $limit);
        } else {
            $response = $client->searchInvoices(
                $actorId,
                $roles,
                $criteria,
                $limit
            );
        }
    } else {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Unknown invoice query action']);
        return;
    }

    $status = (int) ($response['_http_status'] ?? 200);
    unset($response['_http_status']);

    if ($status === 401 || $status >= 500 || $status === 0) {
        http_response_code(502);
        echo json_encode([
            'ok' => false,
            'error' => 'SIF internal service unavailable',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }

    http_response_code($status >= 100 && $status <= 599 ? $status : 502);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'SIF invoice query failed',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} finally {
    if (is_object($usuariObject)) {
        $_SESSION['usuari'] = serialize($usuariObject);
    }
}


function enrollmentIdsByIdentity(string $document, string $email): array
{
    $connection = new ConnexioWeb();
    $ids = [];

    try {
        $connection->connectarBD();

        if ($document !== '' && $email !== '') {
            $stmt = $connection->prepare(
                'SELECT ID FROM inscripcions WHERE DNI = ? AND CORREU = ? ORDER BY ID DESC LIMIT 200'
            );
            $stmt->bind_param('ss', $document, $email);
        } elseif ($document !== '') {
            $stmt = $connection->prepare(
                'SELECT ID FROM inscripcions WHERE DNI = ? ORDER BY ID DESC LIMIT 200'
            );
            $stmt->bind_param('s', $document);
        } elseif ($email !== '') {
            $stmt = $connection->prepare(
                'SELECT ID FROM inscripcions WHERE CORREU = ? ORDER BY ID DESC LIMIT 200'
            );
            $stmt->bind_param('s', $email);
        } else {
            return [];
        }

        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($id);

        while ($stmt->fetch()) {
            $ids[(int) $id] = true;
        }

        $connection->closeStmt();
    } finally {
        if (isset($connection->connexio) && $connection->connexio instanceof mysqli) {
            $connection->desconectarBD();
        }
    }

    return array_keys($ids);
}

function mergeInvoiceSearchResponses(array $responses, int $limit): array
{
    $merged = [];
    $status = 200;

    foreach ($responses as $response) {
        $responseStatus = (int) ($response['_http_status'] ?? 200);
        if ($responseStatus >= 400) {
            return $response;
        }

        foreach (($response['results'] ?? []) as $invoice) {
            if (!is_array($invoice)) {
                continue;
            }

            $uuid = trim((string) ($invoice['uuid_factura'] ?? ''));
            if ($uuid !== '') {
                $merged[$uuid] = $invoice;
            }
        }
    }

    $results = array_values($merged);
    usort($results, static function (array $a, array $b): int {
        $year = ((int) ($b['any_fact'] ?? 0)) <=> ((int) ($a['any_fact'] ?? 0));
        if ($year !== 0) {
            return $year;
        }

        $series = strcmp((string) ($a['tipus_serie'] ?? ''), (string) ($b['tipus_serie'] ?? ''));
        if ($series !== 0) {
            return $series;
        }

        return ((int) ($b['num_seq'] ?? 0)) <=> ((int) ($a['num_seq'] ?? 0));
    });

    $limit = max(1, min(100, $limit));
    $results = array_slice($results, 0, $limit);

    return [
        'ok' => true,
        'results' => $results,
        'count' => count($results),
        '_http_status' => $status,
    ];
}
