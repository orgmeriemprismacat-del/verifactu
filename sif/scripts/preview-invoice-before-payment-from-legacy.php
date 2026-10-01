<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\InvoiceBeforePaymentBillingPartyRepository;
use Prisma\Sif\Repository\InvoiceBeforePaymentSelectionRepository;
use Prisma\Sif\Service\InvoiceBeforePaymentCommandService;
use Prisma\Sif\Service\InvoiceBeforePaymentLegacyPreparationService;
use Prisma\Sif\Service\InvoiceBeforePaymentPayloadBuilder;
use Prisma\Sif\Service\InvoiceBeforePaymentServerPayloadAssembler;
use Prisma\Sif\Service\PayloadIdempotencyValidator;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';

if (($config['env'] ?? 'local') === 'production') {
    fwrite(STDERR, "Refusing to preview legacy-backed invoice-before-payment with SIF_ENV=production.\n");
    exit(1);
}

$args = array_slice($argv, 1);

try {
    $inscriptionIds = inscriptionIds(option($args, '--inscriptions='));
    $entityId = positiveInt(option($args, '--entity-id='), 'entity ID');
    $actorId = requiredText(option($args, '--created-by='), 'Missing --created-by for invoice-before-payment preview');
    $context = context($args);

    $legacyWebDb = ConnectionFactory::makeLegacy($config);
    $legacyIntranetDb = ConnectionFactory::makeLegacyIntranet($config);

    $preparation = new InvoiceBeforePaymentLegacyPreparationService(
        new InvoiceBeforePaymentSelectionRepository(),
        new InvoiceBeforePaymentBillingPartyRepository(),
        new InvoiceBeforePaymentServerPayloadAssembler(),
        new InvoiceBeforePaymentPayloadBuilder(),
        new PayloadIdempotencyValidator()
    );

    $commands = new InvoiceBeforePaymentCommandService(
        $legacyWebDb,
        $legacyIntranetDb,
        $preparation
    );

    $result = $commands->preview(
        $inscriptionIds,
        $entityId,
        $actorId,
        $context
    );
    $result['dry_run'] = true;

    echo json_encode(
        $result,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ), PHP_EOL;
    exit(0);
} catch (\Throwable $exception) {
    echo json_encode([
        'ok' => false,
        'dry_run' => true,
        'error' => $exception->getMessage(),
        'code' => $exception->getCode(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(1);
}

function context(array $args): array
{
    $context = [];

    $observations = optionalText(option($args, '--observations='));
    if ($observations !== null) {
        $context['observations'] = $observations;
    }

    $idempotency = optionalText(option($args, '--idempotency-key='));
    if ($idempotency !== null) {
        $context['idempotency_key'] = $idempotency;
    }

    $fiscalYear = option($args, '--fiscal-year=');
    if ($fiscalYear !== null && $fiscalYear !== '') {
        if (!is_numeric($fiscalYear)) {
            throw SifException::validation('Invalid --fiscal-year');
        }
        $context['fiscal_year'] = (int) $fiscalYear;
    }

    return $context;
}

function inscriptionIds(?string $value): array
{
    if ($value === null || trim($value) === '') {
        usage();
    }

    return array_map('trim', explode(',', $value));
}

function positiveInt(?string $value, string $label): int
{
    if ($value === null || !ctype_digit(trim($value))) {
        throw SifException::validation("Invalid {$label}");
    }

    $int = (int) $value;
    if ($int <= 0) {
        throw SifException::validation("Invalid {$label}");
    }

    return $int;
}

function requiredText(?string $value, string $message): string
{
    $value = optionalText($value);
    if ($value === null) {
        throw SifException::validation($message);
    }

    return $value;
}

function option(array $args, string $prefix): ?string
{
    foreach ($args as $arg) {
        $arg = (string) $arg;
        if (str_starts_with($arg, $prefix)) {
            return trim(substr($arg, strlen($prefix)));
        }
    }

    return null;
}

function optionalText(?string $value): ?string
{
    if ($value === null) {
        return null;
    }

    $value = trim($value);

    return $value === '' ? null : $value;
}

function usage(): never
{
    fwrite(
        STDERR,
        "Usage: php sif/scripts/preview-invoice-before-payment-from-legacy.php "
        . "--inscriptions=ID,ID --entity-id=ID --created-by=USER "
        . "[--fiscal-year=YYYY] [--observations=TEXT] [--idempotency-key=KEY]\n"
    );
    exit(1);
}
