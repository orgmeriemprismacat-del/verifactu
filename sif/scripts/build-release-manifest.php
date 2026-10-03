<?php

require dirname(__DIR__) . '/src/autoload.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$baseDir = realpath(dirname(__DIR__));
$config = require dirname(__DIR__) . '/config/sif.php';
$destination = trim((string) ($config['version_governance']['release_manifest_path'] ?? ''));

if ($baseDir === false || $destination === '') {
    fwrite(STDERR, "SIF_RELEASE_MANIFEST_PATH must point to private storage.\n");
    exit(1);
}

$publicDir = realpath($baseDir . '/public');
$destinationDir = realpath(dirname($destination));
if ($destinationDir === false || !is_dir($destinationDir)) {
    fwrite(STDERR, "Release manifest directory does not exist.\n");
    exit(1);
}
if ($publicDir !== false
    && ($destinationDir === $publicDir || str_starts_with($destinationDir, $publicDir . DIRECTORY_SEPARATOR))
) {
    fwrite(STDERR, "Release manifest must be outside the public webroot.\n");
    exit(1);
}

$roots = ['src', 'public', 'config', 'scripts', 'database/migrations'];
$files = [];

foreach ($roots as $root) {
    $absolute = $baseDir . '/' . $root;
    if (!is_dir($absolute)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($absolute, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->isLink()) {
            continue;
        }
        $path = $file->getRealPath();
        if ($path === false || !str_starts_with($path, $baseDir . DIRECTORY_SEPARATOR)) {
            continue;
        }

        $relative = str_replace('\\', '/', substr($path, strlen($baseDir) + 1));
        $files[$relative] = hash_file('sha256', $path);
    }
}

ksort($files, SORT_STRING);
if ($files === []) {
    fwrite(STDERR, "No release files found.\n");
    exit(1);
}

$artifactHash = hash(
    'sha256',
    json_encode($files, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
);
$manifest = [
    'schema' => 1,
    'artifact_hash' => $artifactHash,
    'files' => $files,
];

$json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
$temp = $destination . '.tmp.' . bin2hex(random_bytes(6));

if (file_put_contents($temp, $json, LOCK_EX) === false || !rename($temp, $destination)) {
    @unlink($temp);
    fwrite(STDERR, "Could not write release manifest.\n");
    exit(1);
}
@chmod($destination, 0640);

echo json_encode([
    'ok' => true,
    'manifest_path' => $destination,
    'artifact_hash' => $artifactHash,
    'file_count' => count($files),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
