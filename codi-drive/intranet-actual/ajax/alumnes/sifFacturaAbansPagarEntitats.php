<?php

$root = dirname(__DIR__, 2);
chdir($root);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    return;
}

ob_start();
require_once $root . '/inc/comprovarSessio.php';
ob_end_clean();

if (!isset($configOk) || !$configOk || !isset($_SESSION['usuari'], $_SESSION['intranet'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Session not authorized']);
    return;
}

require_once $root . '/SifInvoiceBeforePaymentAccess.php';

$user = null;
$intranet = null;
$connection = null;

try {
    $user = is_string($_SESSION['usuari'])
        ? unserialize($_SESSION['usuari'])
        : $_SESSION['usuari'];
    $intranet = is_string($_SESSION['intranet'])
        ? unserialize($_SESSION['intranet'])
        : $_SESSION['intranet'];

    if (!is_object($user) || !is_object($intranet)) {
        throw new RuntimeException('Invalid authenticated session', 401);
    }

    SifInvoiceBeforePaymentAccess::resolve($user, $intranet);

    $connection = new ConnexioIntranet();
    $connection->connectarBD();

    $stmt = $connection->prepare(
        'SELECT DISTINCT e.ID, e.RAO, e.CIF
         FROM entitats AS e
         INNER JOIN entitats_resp AS r ON r.ID_RESP = e.ID_RESP
         WHERE r.DATAI <= CURRENT_TIMESTAMP
           AND (r.DATAF IS NULL OR r.DATAF >= CURRENT_TIMESTAMP)
         ORDER BY e.RAO, e.ID'
    );

    if (!$stmt) {
        throw new RuntimeException('Could not prepare billing entity query');
    }

    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($id, $name, $nif);

    $entities = [];
    while ($stmt->fetch()) {
        $entityId = (int) $id;
        $entityName = trim((string) $name);
        $entityNif = trim((string) $nif);

        if ($entityId <= 0 || $entityName === '' || $entityNif === '') {
            continue;
        }

        $entities[] = [
            'id' => $entityId,
            'name' => $entityName,
            'nif' => $entityNif,
            'label' => $entityName . ' · ' . $entityNif,
        ];
    }

    $connection->closeStmt();

    echo json_encode([
        'ok' => true,
        'entities' => $entities,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    $status = (int) $exception->getCode();
    if ($status < 400 || $status > 599) {
        $status = 500;
    }

    http_response_code($status);
    echo json_encode([
        'ok' => false,
        'error' => $status === 403 ? 'Invoice-before-payment access denied' : 'Could not load billing entities',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} finally {
    if (is_object($connection) && isset($connection->connexio) && $connection->connexio instanceof mysqli) {
        $connection->desconectarBD();
    }
    if (is_object($user)) {
        $_SESSION['usuari'] = serialize($user);
    }
    if (is_object($intranet)) {
        $_SESSION['intranet'] = serialize($intranet);
    }
}
