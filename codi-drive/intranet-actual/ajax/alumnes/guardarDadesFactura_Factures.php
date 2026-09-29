<?php

$root = dirname(__DIR__, 2);
if (!chdir($root)) {
    http_response_code(500);
    echo 'Error: no es pot resoldre l’arrel de la intranet';
    return;
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    http_response_code(405);
    echo 'Error: mètode no permès';
    return;
}

ob_start();
require_once $root . '/inc/comprovarSessio.php';
ob_end_clean();

if (!isset($configOk) || !$configOk || !isset($_SESSION['usuari']) || !isset($_SESSION['intranet'])) {
    http_response_code(401);
    echo 'Error: sessió no autoritzada';
    return;
}

require_once $root . '/Intranet.php';
require_once $root . '/SifInternalApiClient.php';
require_once $root . '/SifLegacyInvoiceMutationGuard.php';
require_once $root . '/LegacyInvoiceMutationAuthorization.php';

$usuariObject = null;
$intranetObject = null;

try {
    $usuariObject = unserialize($_SESSION['usuari']);
    $intranetObject = unserialize($_SESSION['intranet']);

    if (!is_object($usuariObject) || !is_object($intranetObject)) {
        throw new RuntimeException('Sessió no vàlida', 401);
    }

    LegacyInvoiceMutationAuthorization::assertSameOrigin();
    LegacyInvoiceMutationAuthorization::assertCanEdit(
        $usuariObject,
        $intranetObject,
        '/alumnes/factura/'
    );

    $id = trim((string) ($_POST['id'] ?? ''));
    if (!ctype_digit($id) || (int) $id <= 0) {
        throw new InvalidArgumentException('Identificador de factura no vàlid', 422);
    }

    (new SifLegacyInvoiceMutationGuard())->assertLegacyMutationAllowed(
        $usuariObject,
        (int) $id
    );

    $facturaActual = legacyInvoiceRelationForUpdate((int) $id);
    $facturaEnviada = trim((string) ($_POST['factura'] ?? ''));
    if ($facturaEnviada !== '' && $facturaEnviada !== $facturaActual) {
        throw new RuntimeException('La factura relacionada és immutable en aquest flux', 409);
    }
    $factura = $facturaActual;

    $rao = (string) ($_POST['rao'] ?? '');
    $cif = (string) ($_POST['cif'] ?? '');
    $cp = (string) ($_POST['cp'] ?? '');
    $poblacio = (string) ($_POST['poblacio'] ?? '');
    $adreca = (string) ($_POST['adreca'] ?? '');
    $concepte1 = (string) ($_POST['concepte1'] ?? '');
    $concepte2 = (string) ($_POST['concepte2'] ?? '');
    $obs = (string) ($_POST['obs'] ?? '');

    echo $intranetObject->guardarDadesFactura_Factures(
        (int) $id,
        $factura,
        $rao,
        $cif,
        $cp,
        $poblacio,
        $adreca,
        $concepte1,
        $concepte2,
        $obs
    );
} catch (Throwable $exception) {
    $code = (int) $exception->getCode();
    http_response_code($code >= 400 && $code <= 599 ? $code : 500);
    echo 'Error: ' . $exception->getMessage();
} finally {
    if (is_object($usuariObject)) {
        $_SESSION['usuari'] = serialize($usuariObject);
    }
    if (is_object($intranetObject)) {
        $_SESSION['intranet'] = serialize($intranetObject);
    }
}


function legacyInvoiceRelationForUpdate(int $invoiceId): string
{
    $connection = new ConnexioWeb();

    try {
        $connection->connectarBD();
        $stmt = $connection->prepare(
            'SELECT factura_relacionada FROM factures WHERE ID = ? LIMIT 1'
        );
        $stmt->bind_param('i', $invoiceId);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows() <= 0) {
            $connection->closeStmt();
            throw new RuntimeException('Factura llegada no trobada', 404);
        }

        $stmt->bind_result($relation);
        $stmt->fetch();
        $connection->closeStmt();

        return (string) $relation;
    } finally {
        if (isset($connection->connexio) && $connection->connexio instanceof mysqli) {
            $connection->desconectarBD();
        }
    }
}
