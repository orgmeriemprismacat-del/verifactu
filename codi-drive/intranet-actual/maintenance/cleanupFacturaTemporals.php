<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Aquest manteniment només es pot executar per CLI.\n");
    exit(1);
}

$ttl = 3600;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with((string) $arg, '--ttl=')) {
        $value = substr((string) $arg, strlen('--ttl='));
        if (!ctype_digit($value) || (int) $value < 300) {
            fwrite(STDERR, "TTL invàlid. Mínim: 300 segons.\n");
            exit(1);
        }
        $ttl = (int) $value;
    }
}

$tempRoot = realpath(dirname(__DIR__) . '/ajax/alumnes');
if ($tempRoot === false || !is_dir($tempRoot)) {
    fwrite(STDERR, "No s'ha trobat el directori temporal de factures.\n");
    exit(1);
}

$cutoff = time() - $ttl;
$removed = 0;
$skipped = 0;

$iterator = new DirectoryIterator($tempRoot);
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->isLink()) {
        continue;
    }

    $name = $file->getFilename();

    // Patró exclusiu dels temporals creats pel generador llegat de factures.
    // No coincideix amb documents SIF ni amb paths de storage privat.
    if (preg_match('/^A\d{4}-\d+-\d+\.pdf$/D', $name) !== 1) {
        continue;
    }

    if ($file->getMTime() > $cutoff) {
        $skipped++;
        continue;
    }

    $resolved = realpath($file->getPathname());
    if ($resolved === false
        || !str_starts_with($resolved, rtrim($tempRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
        continue;
    }

    if (@unlink($resolved)) {
        $removed++;
    }
}

echo json_encode([
    'ok' => true,
    'ttl_seconds' => $ttl,
    'removed' => $removed,
    'kept_recent' => $skipped,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
