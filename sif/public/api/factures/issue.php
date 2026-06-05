<?php

require dirname(__DIR__, 3) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\HashCalculator;
use Prisma\Sif\Domain\PaymentStatusCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Http\JsonResponse;
use Prisma\Sif\Repository\FiscalSequenceRepository;
use Prisma\Sif\Repository\InvoiceRepository;
use Prisma\Sif\Repository\PaymentRepository;
use Prisma\Sif\Service\InvoicePayloadValidator;
use Prisma\Sif\Service\InvoiceService;
use Prisma\Sif\Service\PaymentPayloadValidator;

$payload = JsonResponse::fromInput();
if ($payload === null) {
    return;
}

try {
    $config = require dirname(__DIR__, 3) . '/config/sif.php';
    $db = ConnectionFactory::make($config);
    $service = new InvoiceService(
        new TransactionRunner($db),
        new InvoicePayloadValidator(),
        new FiscalSequenceRepository(),
        new InvoiceRepository(
            new UuidGenerator(),
            new HashCalculator()
        ),
        new PaymentPayloadValidator(),
        new PaymentRepository(
            new UuidGenerator(),
            new PaymentStatusCalculator()
        )
    );

    JsonResponse::send($service->issueInvoice($payload));
} catch (\Throwable $exception) {
    JsonResponse::fromThrowable($exception);
}
