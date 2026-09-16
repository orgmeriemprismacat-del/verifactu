<?php

declare(strict_types=1);

use Prisma\Sif\FunctionalCard\CaseInputValidator;
use Prisma\Sif\FunctionalCard\SourceManifestBuilder;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/src/autoload.php';

try {
    $options = parseOptions($argv);
    if (!isset($options['input'])) {
        throw new InvalidArgumentException(
            'Usage: php sif/scripts/prepare-functional-card.php --input=case.json [--output=manifest.json]'
        );
    }

    $inputPath = requireReadableFile((string) $options['input'], 'input');
    $input = json_decode((string) file_get_contents($inputPath), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($input)) {
        throw new InvalidArgumentException('Input JSON must contain an object');
    }

    $repoRoot = dirname(__DIR__, 2);
    $config = require dirname(__DIR__) . '/tools/functional-card/sources.php';
    $normalized = (new CaseInputValidator())->validate($input);
    $manifest = (new SourceManifestBuilder($repoRoot, $config))->build($normalized);
    $json = json_encode(
        $manifest,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . PHP_EOL;

    if (isset($options['output'])) {
        $outputPath = safeNewOutputPath((string) $options['output'], $repoRoot);
        if (file_put_contents($outputPath, $json, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write output: ' . $outputPath);
        }
        fwrite(STDOUT, "Manifest written to {$outputPath}" . PHP_EOL);
    } else {
        fwrite(STDOUT, $json);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, '[ERROR] ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

function parseOptions(array $arguments): array
{
    $options = [];
    foreach (array_slice($arguments, 1) as $argument) {
        if (!str_starts_with($argument, '--') || !str_contains($argument, '=')) {
            throw new InvalidArgumentException('Unsupported argument: ' . $argument);
        }
        [$name, $value] = explode('=', substr($argument, 2), 2);
        if (!in_array($name, ['input', 'output'], true) || $value === '') {
            throw new InvalidArgumentException('Unsupported argument: ' . $argument);
        }
        $options[$name] = $value;
    }
    return $options;
}

function requireReadableFile(string $path, string $label): string
{
    $resolved = realpath($path);
    if ($resolved === false || !is_file($resolved) || !is_readable($resolved)) {
        throw new InvalidArgumentException(ucfirst($label) . ' file is not readable: ' . $path);
    }
    return $resolved;
}

function safeNewOutputPath(string $path, string $repoRoot): string
{
    if (file_exists($path)) {
        throw new InvalidArgumentException('Refusing to overwrite existing output: ' . $path);
    }

    $directory = realpath(dirname($path));
    if ($directory === false || !is_dir($directory)) {
        throw new InvalidArgumentException('Output directory does not exist: ' . dirname($path));
    }

    $documentRoot = strtolower((string) realpath($repoRoot . '/documentacio'));
    $normalizedDirectory = strtolower($directory);
    if ($documentRoot !== ''
        && ($normalizedDirectory === $documentRoot
            || str_starts_with($normalizedDirectory, $documentRoot . DIRECTORY_SEPARATOR))) {
        throw new InvalidArgumentException('The preview tool cannot write inside documentacio/');
    }

    return $directory . DIRECTORY_SEPARATOR . basename($path);
}
