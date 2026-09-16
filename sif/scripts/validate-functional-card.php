<?php

declare(strict_types=1);

use Prisma\Sif\FunctionalCard\FunctionalCardValidator;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/src/autoload.php';

try {
    $options = parseValidationOptions($argv);
    foreach (['card', 'manifest'] as $required) {
        if (!isset($options[$required])) {
            throw new InvalidArgumentException(
                'Usage: php sif/scripts/validate-functional-card.php --card=card.md --manifest=manifest.json'
            );
        }
    }

    $cardPath = requireValidationInput((string) $options['card'], 'card');
    $manifestPath = requireValidationInput((string) $options['manifest'], 'manifest');
    $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($manifest)) {
        throw new InvalidArgumentException('Manifest JSON must contain an object');
    }

    $repoRoot = dirname(__DIR__, 2);
    $result = (new FunctionalCardValidator($repoRoot))->validate(
        (string) file_get_contents($cardPath),
        $manifest
    );

    fwrite(STDOUT, json_encode(
        $result,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ) . PHP_EOL);
    exit($result['valid'] ? 0 : 1);
} catch (Throwable $exception) {
    fwrite(STDERR, '[ERROR] ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

function parseValidationOptions(array $arguments): array
{
    $options = [];
    foreach (array_slice($arguments, 1) as $argument) {
        if (!str_starts_with($argument, '--') || !str_contains($argument, '=')) {
            throw new InvalidArgumentException('Unsupported argument: ' . $argument);
        }
        [$name, $value] = explode('=', substr($argument, 2), 2);
        if (!in_array($name, ['card', 'manifest'], true) || $value === '') {
            throw new InvalidArgumentException('Unsupported argument: ' . $argument);
        }
        $options[$name] = $value;
    }
    return $options;
}

function requireValidationInput(string $path, string $label): string
{
    $resolved = realpath($path);
    if ($resolved === false || !is_file($resolved) || !is_readable($resolved)) {
        throw new InvalidArgumentException(ucfirst($label) . ' file is not readable: ' . $path);
    }
    return $resolved;
}
