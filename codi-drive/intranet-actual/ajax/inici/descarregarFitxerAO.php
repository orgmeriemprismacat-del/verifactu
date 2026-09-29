<?php
/**
 * Descàrrega autoritzada de CSV d'aules obertes, fora del directori web públic.
 * Únicament per a l'usuari/sessió que ha preparat el lot i amb rol actual.
 */
declare(strict_types=1);
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

function aoDownloadDeny(int $status): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Fitxer no disponible o accés no autoritzat.';
    exit;
}

$root = dirname(__DIR__, 2);
chdir($root);
ob_start();
require $root . '/inc/comprovarSessio.php';
ob_end_clean();
if (empty($configOk) || empty($_SESSION['usuari']) ||
    !is_string($_SESSION['usuari'])) {
    aoDownloadDeny(401);
}
require_once $root . '/ConnexioIntranet.php';
require_once $root . '/Text.php';
require_once $root . '/Usuari.php';

$user = unserialize($_SESSION['usuari'], ['allowed_classes' => true]);
if (!$user instanceof Usuari || !$user->getUsuari()) {
    aoDownloadDeny(401);
}
$actor = (string) $user->getUsuari()->get();
$token = $_GET['token'] ?? '';
if (!is_string($token) || !preg_match('/^[a-f0-9]{48}$/D', $token)) {
    aoDownloadDeny(404);
}

try {
    $auth = new ConnexioIntranet();
    $auth->connectarBD();
    try {
        $route = '/cursos/fi-cursos/pujar-aules-obertes/';
        $statement = $auth->prepare(
            'SELECT ROLS_VISUALITZAR, ROLS_EDITAR FROM apartats WHERE URL = ? LIMIT 1'
        );
        if (!$statement) {
            aoDownloadDeny(503);
        }
        $statement->bind_param('s', $route);
        $statement->execute();
        $statement->bind_result($viewRoles, $editRoles);
        $found = $statement->fetch();
        $statement->close();
        if (!$found || !$viewRoles || !$editRoles ||
            !$user->tePermisVisualitzacio($viewRoles) ||
            !$user->tePermisVisualitzacio($editRoles)) {
            aoDownloadDeny(403);
        }
    } finally {
        $auth->desconectarBD();
    }
} catch (Throwable $error) {
    aoDownloadDeny(503);
}

$receipt = null;
foreach (($_SESSION['ao_export_receipts'] ?? []) as $stored) {
    if (is_array($stored) && ($stored['token'] ?? '') === $token) {
        $receipt = $stored;
        break;
    }
}
if ($receipt === null || ($receipt['actor'] ?? '') !== $actor ||
    time() - (int) ($receipt['created'] ?? 0) > 86400) {
    aoDownloadDeny(404);
}
$path = $receipt['path'] ?? '';
$baseDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) .
           DIRECTORY_SEPARATOR . 'prisma-ao-exports';
if (!is_string($path) ||
    $path !== $baseDir . '/pujada-ao-' . $token . '.csv' ||
    !is_file($path) || !is_readable($path)) {
    aoDownloadDeny(404);
}
session_write_close();
header('Content-Type: text/csv; charset=ISO-8859-1');
header('Content-Disposition: attachment; filename="pujada-ao-' . $token . '.csv"');
header('Content-Length: ' . filesize($path));
readfile($path);
