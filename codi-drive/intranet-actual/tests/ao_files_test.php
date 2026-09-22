<?php
/** Prova CLI de caducitat de fitxers AO en un directori temporal de proves. */
declare(strict_types=1);
require_once dirname(__DIR__) . '/inc/AOBatchFiles.php';

$dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test-ao-' . bin2hex(random_bytes(8));
if (!mkdir($dir, 0700)) {
    throw new RuntimeException('No es pot crear directori temporal');
}
$now = time();
$oldCsv = $dir . '/pujada-ao-vell.csv';
$freshCsv = $dir . '/pujada-ao-nou.csv';
$oldTmp = $dir . '/.pujada-ao-vell.tmp';
$unrelated = $dir . '/altra-cosa.csv';
try {
    foreach ([$oldCsv, $freshCsv, $oldTmp, $unrelated] as $path) {
        if (file_put_contents($path, 'test') === false) {
            throw new RuntimeException('No es pot crear fitxer de prova');
        }
    }
    touch($oldCsv, $now - 90000);
    touch($oldTmp, $now - 4000);
    touch($freshCsv, $now - 3600);
    touch($unrelated, $now - 90000);
    clearstatcache();
    $removed = aoCleanupExports($dir, $now);
    if ($removed !== 2 || file_exists($oldCsv) || file_exists($oldTmp) ||
        !file_exists($freshCsv) || !file_exists($unrelated)) {
        throw new RuntimeException("La política de retenció no s'ha respectat");
    }
    echo "PASS: retenció AO (2 caducats eliminats, fitxers vigents conservats).\n";
} finally {
    foreach (glob($dir . '/*') ?: [] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
    rmdir($dir);
}
