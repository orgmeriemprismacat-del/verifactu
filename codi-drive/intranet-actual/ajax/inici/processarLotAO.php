<?php
/**
 * Pujada d'aules obertes: prepara un CSV privat i actualitza PERENNE per ID_INSC.
 * No executa cap pujada posterior al campus, ni cap operació econòmica/fiscal.
 * El JS d'aquesta pàgina envia UN sol POST per lot, no un POST per participant.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function aoRespond(int $status, array $response): void
{
    http_response_code($status);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function aoAbort(string $code, int $status = 400): void
{
    throw new AOBatchException($code, $status);
}

final class AOBatchException extends RuntimeException
{
    public $httpStatus;

    public function __construct(string $code, int $httpStatus)
    {
        parent::__construct($code);
        $this->httpStatus = $httpStatus;
    }
}

// Sessió validada amb el mecanisme EXISTENT de la intranet; chdir manté els
// includes relatius de comprovarSessio.php i dels connectors de BD llegats.
$root = dirname(__DIR__, 2);
chdir($root);
ob_start();
require $root . '/inc/comprovarSessio.php';
ob_end_clean();
if (empty($configOk) || empty($_SESSION['usuari']) || !is_string($_SESSION['usuari'])) {
    aoRespond(401, ['ok' => false, 'error' => 'SESSIO_NO_VALIDA']);
}
require_once $root . '/ConnexioIntranet.php';
require_once $root . '/ConnexioWeb.php';
require_once $root . '/Text.php';
require_once $root . '/Usuari.php';
require_once $root . '/inc/AOBatchCsv.php';

$db = null;
$temp = null;
$final = null;
$handle = null;
$inTransaction = false;
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        aoAbort('METODE_NO_PERMES', 405);
    }
    $user = unserialize($_SESSION['usuari'], ['allowed_classes' => true]);
    if (!$user instanceof Usuari || !$user->getUsuari()) {
        aoAbort('SESSIO_NO_VALIDA', 401);
    }
    $actor = (string) $user->getUsuari()->get();
    $csrf = $_POST['csrf'] ?? '';
    if (!is_string($csrf) || !isset($_SESSION['ao_csrf']) ||
        !hash_equals((string) $_SESSION['ao_csrf'], $csrf)) {
        aoAbort('TOKEN_INVALID', 403);
    }
    $lotKey = $_POST['lot_key'] ?? '';
    if (!is_string($lotKey) || !preg_match('/^[a-zA-Z0-9_-]{16,80}$/D', $lotKey)) {
        aoAbort('LOT_INVALID', 422);
    }
    $rawIds = $_POST['ids'] ?? null;
    if (!is_string($rawIds) || strlen($rawIds) > 5000) {
        aoAbort('SELECCIO_INVALIDA', 422);
    }
    $ids = json_decode($rawIds, true);
    if (!is_array($ids) || count($ids) < 1 || count($ids) > 200 ||
        array_keys($ids) !== range(0, count($ids) - 1) ||
        count(array_unique($ids, SORT_REGULAR)) !== count($ids)) {
        aoAbort('SELECCIO_INVALIDA', 422);
    }
    foreach ($ids as $id) {
        if (!is_int($id) || $id <= 0) {
            aoAbort('SELECCIO_INVALIDA', 422);
        }
    }
    sort($ids, SORT_NUMERIC);
    $selectionHash = hash('sha256', json_encode($ids));

    // Autorització real al servidor segons el mateix registre d'apartats
    // que alimenta el control de visualització/edició de la pantalla.
    $intranetDb = new ConnexioIntranet();
    $intranetDb->connectarBD();
    try {
        $route = '/cursos/fi-cursos/pujar-aules-obertes/';
        $permission = $intranetDb->prepare(
            'SELECT ROLS_VISUALITZAR, ROLS_EDITAR FROM apartats WHERE URL = ? LIMIT 1'
        );
        if (!$permission) {
            aoAbort('PERMIS_NO_DISPONIBLE', 503);
        }
        $permission->bind_param('s', $route);
        if (!$permission->execute()) {
            aoAbort('PERMIS_NO_DISPONIBLE', 503);
        }
        $permission->bind_result($viewRoles, $editRoles);
        $permissionFound = $permission->fetch();
        $permission->close();
        if (!$permissionFound || !$viewRoles || !$editRoles ||
            !$user->tePermisVisualitzacio($viewRoles) ||
            !$user->tePermisVisualitzacio($editRoles)) {
            aoAbort('SENSE_PERMIS', 403);
        }
    } finally {
        $intranetDb->desconectarBD();
    }

    // El token de lot es conserva a sessió: mateix token amb altres files = conflicte.
    $prior = $_SESSION['ao_export_receipts'][$lotKey] ?? null;
    if (is_array($prior)) {
        if (($prior['actor'] ?? '') !== $actor ||
            ($prior['selection_hash'] ?? '') !== $selectionHash) {
            aoAbort('LOT_CLAU_REUTILITZADA', 409);
        }
        if (empty($prior['path']) || !is_file($prior['path'])) {
            aoAbort('FITXER_LOT_NO_DISPONIBLE', 410);
        }
        aoRespond(200, [
            'ok' => true, 'reused' => true, 'count' => count($ids),
            'token' => $prior['token'],
        ]);
    }

    $db = new ConnexioWeb();
    $db->connectarBD();
    $db->connexio->begin_transaction();
    $inTransaction = true;

    // Bloqueig de cada ID real, amb la mateixa condició d'elegibilitat que la
    // vista; EXISTS evita duplicar les files amb múltiples tutors vinculats.
    $sql = "SELECT i.ID, i.USUARI, i.ANY, i.MES, i.CURS, i.NOM,
                   i.COGNOMS, i.CORREU, i.Poblacio
            FROM inscripcions AS i
            WHERE i.ID = ? AND i.PERENNE = '0' AND i.`INSC CURS` = '1'
              AND EXISTS (
                SELECT 1 FROM curs AS c
                INNER JOIN aula ON c.ID_AULA = aula.ID_AULA
                INNER JOIN rel_cuho ON rel_cuho.id_cuho = aula.ID_CUHO
                INNER JOIN honoraris ON honoraris.ID = rel_cuho.id_hono
                       AND honoraris.PERFIL = 'TUTOR'
                INNER JOIN personal AS p ON p.DNI = honoraris.DNI_TUTOR
                WHERE c.ANY = i.ANY AND c.MES = i.MES AND c.CURS = i.CURS
                  AND aula.AULA = i.Grup
              ) FOR UPDATE";
    $select = $db->connexio->prepare($sql);
    if (!$select) {
        aoAbort('ERROR_CONSULTA', 500);
    }
    $id = 0;
    $select->bind_param('i', $id);
    $select->bind_result($foundId, $username, $any, $mes, $curs,
                         $name, $surname, $email, $city);
    $rows = [];
    foreach ($ids as $id) {
        if (!$select->execute() || !$select->store_result() || !$select->fetch()) {
            aoAbort('FILA_NO_DISPONIBLE', 409);
        }
        $rows[] = [
            'id' => (int) $foundId, 'username' => (string) $username,
            'name' => (string) $name, 'surname' => (string) $surname,
            'email' => (string) $email, 'city' => (string) $city,
            'course' => (string) $curs,
        ];
        $select->free_result();
    }
    $select->close();

    // CSV privat fora del document root. No acceptem un nom de fitxer del client.
    $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) .
           DIRECTORY_SEPARATOR . 'prisma-ao-exports';
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        aoAbort('ERROR_DIRECTORI_CSV', 500);
    }
    if (!is_writable($dir)) {
        aoAbort('ERROR_DIRECTORI_CSV', 500);
    }
    $token = bin2hex(random_bytes(24));
    $temp = $dir . '/.pujada-ao-' . $token . '.tmp';
    $final = $dir . '/pujada-ao-' . $token . '.csv';
    $handle = fopen($temp, 'x');
    if ($handle === false) {
        aoAbort('ERROR_CREACIO_CSV', 500);
    }
    chmod($temp, 0600);
    aoWriteCsvRow($handle, [
        'username', 'password', 'firstname', 'lastname', 'email', 'city',
        'lang', 'course1', 'autosubscribe', 'maildisplay',
    ]);
    foreach ($rows as $row) {
        aoWriteCsvRow($handle, [
            $row['username'], '', $row['name'], $row['surname'],
            $row['email'], $row['city'], 'ca', $row['course'], '0', '2',
        ]);
    }
    if (!fflush($handle) || !fclose($handle)) {
        $handle = null;
        aoAbort('ERROR_ESCRIPTURA_CSV', 500);
    }
    $handle = null;
    if (!rename($temp, $final)) {
        aoAbort('ERROR_FINALITZAR_CSV', 500);
    }
    $temp = null;

    // Actualització inequívoca per ID_INSC. Tot el lot passa o es reverteix.
    $update = $db->connexio->prepare(
        "UPDATE inscripcions SET PERENNE = '1'
         WHERE ID = ? AND `INSC CURS` = '1' AND PERENNE = '0'"
    );
    if (!$update) {
        aoAbort('ERROR_ACTUALITZACIO', 500);
    }
    $id = 0;
    $update->bind_param('i', $id);
    foreach ($ids as $id) {
        if (!$update->execute() || $update->affected_rows !== 1) {
            aoAbort('FILA_MODIFICADA_CONCURRENTMENT', 409);
        }
    }
    $update->close();
    $db->connexio->commit();
    $inTransaction = false;
    $db->desconectarBD();
    $db = null;

    $_SESSION['ao_export_receipts'][$lotKey] = [
        'actor' => $actor, 'selection_hash' => $selectionHash,
        'token' => $token, 'path' => $final, 'created' => time(),
    ];
    aoRespond(200, ['ok' => true, 'reused' => false,
                    'count' => count($rows), 'token' => $token]);
} catch (Throwable $error) {
    if ($handle !== null && is_resource($handle)) {
        fclose($handle);
    }
    if ($inTransaction && $db !== null) {
        $db->connexio->rollback();
    }
    if ($db !== null) {
        $db->desconectarBD();
    }
    if ($temp !== null && is_file($temp)) {
        unlink($temp);
    }
    if ($final !== null && is_file($final)) {
        unlink($final);
    }
    $isCsvValidation = $error instanceof AOCsvException;
    $status = $error instanceof AOBatchException ? $error->httpStatus :
        ($isCsvValidation && $error->getMessage() !== 'ERROR_ESCRIPTURA_CSV' ? 422 : 500);
    aoRespond($status, [
        'ok' => false,
        'error' => ($error instanceof AOBatchException || $isCsvValidation)
            ? $error->getMessage() : 'ERROR_INTERN_LOT',
    ]);
}
