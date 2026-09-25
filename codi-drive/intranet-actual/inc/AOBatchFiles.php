<?php
/**
 * Retenció de fitxers privats de pujada d'aules obertes.
 * Mai no esborra fora del directori rebut; ignora links simbòlics.
 */
declare(strict_types=1);

function aoCleanupExports(string $directory, ?int $now = null): int
{
    if (!is_dir($directory)) {
        return 0;
    }
    $now = $now ?? time();
    $removed = 0;
    foreach ([
        ['pujada-ao-*.csv', 86400],
        ['.pujada-ao-*.tmp', 3600],
    ] as [$pattern, $retentionSeconds]) {
        foreach (glob($directory . DIRECTORY_SEPARATOR . $pattern) ?: [] as $file) {
            if (!is_file($file) || is_link($file)) {
                continue;
            }
            $mtime = filemtime($file);
            if ($mtime !== false && $mtime < $now - $retentionSeconds && unlink($file)) {
                ++$removed;
            }
        }
    }
    return $removed;
}
