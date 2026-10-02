<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\InternalInvoiceIssuePayloadPolicy;
use Prisma\Sif\Tests\Support\Assert;

final class InternalInvoiceIssuePayloadPolicyTest
{
    public function testUsesAuthenticatedActorAndConfiguredIssuer(): void
    {
        $prepared = (new InternalInvoiceIssuePayloadPolicy(
            'G12345678',
            'Associacio PrisMa'
        ))->prepare([
            'source_channel' => 'INTRANET',
            'created_by' => 'spoofed-user',
            'aeat_fields' => ['SistemaInformatico' => ['TipoUsoPosibleSoloVerifactu' => 'S']],
            'aeat_header' => [
                'ObligadoEmision' => ['NIF' => '00000000T', 'NombreRazon' => 'Spoof'],
            ],
        ], ['actor_id' => 'adam']);

        Assert::same('adam', $prepared['created_by']);
        Assert::same('G12345678', $prepared['aeat_header']['ObligadoEmision']['NIF']);
        Assert::same('Associacio PrisMa', $prepared['aeat_header']['ObligadoEmision']['NombreRazon']);
    }


    public function testRejectsOfficialAeatPayloadWithPlaceholderIssuer(): void
    {
        Assert::throws(\RuntimeException::class, function (): void {
            (new InternalInvoiceIssuePayloadPolicy(
                'G00000000',
                'Associacio PrisMa'
            ))->prepare([
                'source_channel' => 'INTRANET',
                'aeat_fields' => ['SistemaInformatico' => ['TipoUsoPosibleSoloVerifactu' => 'S']],
            ], ['actor_id' => 'adam']);
        });
    }

    public function testRejectsLowercasePlaceholderIssuerToo(): void
    {
        Assert::throws(\RuntimeException::class, function (): void {
            (new InternalInvoiceIssuePayloadPolicy(
                'g00000000',
                'Associacio PrisMa'
            ))->prepare([
                'source_channel' => 'INTRANET',
                'aeat_fields' => ['SistemaInformatico' => ['TipoUsoPosibleSoloVerifactu' => 'S']],
            ], ['actor_id' => 'adam']);
        });
    }

    public function testRejectsRedsysInvoiceThroughGenericInternalEndpoint(): void
    {
        Assert::throws(SifException::class, function (): void {
            (new InternalInvoiceIssuePayloadPolicy('G12345678', 'Associacio PrisMa'))->prepare([
                'source_channel' => 'REDSYS',
            ], ['actor_id' => 'adam']);
        }, 422);
    }

    public function testRejectsRedsysPaymentThroughGenericInternalEndpoint(): void
    {
        Assert::throws(SifException::class, function (): void {
            (new InternalInvoiceIssuePayloadPolicy('G12345678', 'Associacio PrisMa'))->prepare([
                'source_channel' => 'INTRANET',
                'payment' => ['method' => 'REDSYS', 'source_channel' => 'REDSYS'],
            ], ['actor_id' => 'adam']);
        }, 422);
    }

    public function testRejectsInvoiceBeforePaymentBypass(): void
    {
        Assert::throws(SifException::class, function (): void {
            (new InternalInvoiceIssuePayloadPolicy('G12345678', 'Associacio PrisMa'))->prepare([
                'source_channel' => 'INTRANET',
                'emesa_abans_cobrament' => 1,
            ], ['actor_id' => 'adam']);
        }, 422);
    }
}
