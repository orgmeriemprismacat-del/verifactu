<?php
/**
 * Job opcional de retenció. Programar-lo amb l'usuari PHP de la intranet
 * (mateix sys_get_temp_dir); NO invocable per HTTP.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require_once dirname(__DIR__) . '/inc/AOBatchFiles.php';
$dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) .
       DIRECTORY_SEPARATOR . 'prisma-ao-exports';
$removed = aoCleanupExports($dir);
echo "Neteja AO: " . $removed . " fitxers caducats eliminats.\n";
