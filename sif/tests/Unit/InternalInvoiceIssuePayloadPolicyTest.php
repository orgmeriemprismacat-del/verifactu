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


    public function testPropagatesAuthenticatedRequestContextWithoutChangingBusinessFields(): void
    {
        $prepared = (new InternalInvoiceIssuePayloadPolicy(
            'G12345678',
            'Associacio PrisMa'
        ))->prepare([
            'idempotency_key' => 'INTRANET|TRACE|POLICY',
            'source_channel' => 'INTRANET',
            'correlation_id' => 'BUSINESS-CORRELATION-1',
        ], [
            'actor_id' => 'gestio-test',
            'roles' => ['ALTRES', 'FACTURACIO'],
            'invoice_issue_role' => 'FACTURACIO',
            'request_id' => '11111111-1111-4111-8111-111111111111',
        ]);

        Assert::same('gestio-test', $prepared['created_by']);
        Assert::same('11111111-1111-4111-8111-111111111111', $prepared['request_id']);
        Assert::same('BUSINESS-CORRELATION-1', $prepared['correlation_id']);
        Assert::same('FACTURACIO', $prepared['actor_role']);
        Assert::same('SYSTEM', $prepared['actor_type']);
    }

    public function testRequiresOfficialAeatSnapshotInQualifiedEnvironment(): void
    {
        Assert::throws(SifException::class, function (): void {
            (new InternalInvoiceIssuePayloadPolicy(
                'G12345678',
                'Associacio PrisMa',
                true
            ))->prepare([
                'idempotency_key' => 'INTRANET|AEAT|required',
                'source_channel' => 'INTRANET',
            ], [
                'actor_id' => 'gestio-test',
                'roles' => ['FACTURACIO'],
                'invoice_issue_role' => 'FACTURACIO',
                'request_id' => '11111111-1111-4111-8111-111111111111',
            ]);
        }, 422);
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
