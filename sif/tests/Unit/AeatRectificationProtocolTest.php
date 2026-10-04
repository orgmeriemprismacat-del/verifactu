<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Aeat\RecordFactory;
use Prisma\Sif\Aeat\XmlCodec;
use Prisma\Sif\Tests\Support\AeatFixtures;
use Prisma\Sif\Tests\Support\Assert;

final class AeatRectificationProtocolTest
{
    public function testIncrementalRectificationBuildsValidOfficialXsdSnapshot(): void
    {
        $original = AeatFixtures::snapshot();
        $snapshot = (new RecordFactory())->freeze(
            'RegistroAlta',
            $original['header'],
            $this->rectificationFields($original, 'I'),
            $original,
            new \DateTimeImmutable('2024-01-02T10:00:00+01:00')
        );

        $xml = (new XmlCodec())->request($snapshot);

        Assert::same('R1', $snapshot['record']['TipoFactura']);
        Assert::same('I', $snapshot['record']['TipoRectificativa']);
        Assert::same(false, array_key_exists('ImporteRectificacion', $snapshot['record']));
        Assert::stringContainsString('<sf:TipoRectificativa>I</sf:TipoRectificativa>', $xml);
        Assert::stringContainsString('<sf:FacturasRectificadas>', $xml);
        Assert::stringContainsString('<sf:NumSerieFactura>12345678/G33</sf:NumSerieFactura>', $xml);
    }

    public function testSubstitutiveRectificationRequiresAndSerializesReplacedAmounts(): void
    {
        $original = AeatFixtures::snapshot();
        $fields = $this->rectificationFields($original, 'S');
        $fields['ImporteRectificacion'] = [
            'BaseRectificada' => '100.00',
            'CuotaRectificada' => '21.00',
        ];

        $snapshot = (new RecordFactory())->freeze(
            'RegistroAlta',
            $original['header'],
            $fields,
            $original,
            new \DateTimeImmutable('2024-01-02T10:00:00+01:00')
        );

        $xml = (new XmlCodec())->request($snapshot);

        Assert::same('S', $snapshot['record']['TipoRectificativa']);
        Assert::same('100.00', $snapshot['record']['ImporteRectificacion']['BaseRectificada']);
        Assert::same('21.00', $snapshot['record']['ImporteRectificacion']['CuotaRectificada']);
        Assert::stringContainsString('<sf:TipoRectificativa>S</sf:TipoRectificativa>', $xml);
        Assert::stringContainsString('<sf:BaseRectificada>100.00</sf:BaseRectificada>', $xml);
        Assert::stringContainsString('<sf:CuotaRectificada>21.00</sf:CuotaRectificada>', $xml);
    }

    public function testRejectsSubstitutiveRectificationWithoutReplacedAmounts(): void
    {
        $original = AeatFixtures::snapshot();

        Assert::throws(\InvalidArgumentException::class, function () use ($original): void {
            (new RecordFactory())->freeze(
                'RegistroAlta',
                $original['header'],
                $this->rectificationFields($original, 'S'),
                $original,
                new \DateTimeImmutable('2024-01-02T10:00:00+01:00')
            );
        });
    }

    public function testRejectsIncrementalRectificationWithReplacedAmounts(): void
    {
        $original = AeatFixtures::snapshot();
        $fields = $this->rectificationFields($original, 'I');
        $fields['ImporteRectificacion'] = [
            'BaseRectificada' => '100.00',
            'CuotaRectificada' => '21.00',
        ];

        Assert::throws(\InvalidArgumentException::class, function () use ($original, $fields): void {
            (new RecordFactory())->freeze(
                'RegistroAlta',
                $original['header'],
                $fields,
                $original,
                new \DateTimeImmutable('2024-01-02T10:00:00+01:00')
            );
        });
    }

    public function testRejectsRectificationFieldsOnNonRectifyingInvoice(): void
    {
        $original = AeatFixtures::snapshot();
        $fields = $this->rectificationFields($original, 'I');
        $fields['TipoFactura'] = 'F1';

        Assert::throws(\InvalidArgumentException::class, function () use ($original, $fields): void {
            (new RecordFactory())->freeze(
                'RegistroAlta',
                $original['header'],
                $fields,
                $original,
                new \DateTimeImmutable('2024-01-02T10:00:00+01:00')
            );
        });
    }

    private function rectificationFields(array $original, string $method): array
    {
        return [
            'IDFactura' => [
                'IDEmisorFactura' => $original['record']['IDFactura']['IDEmisorFactura'],
                'NumSerieFactura' => 'R2024/000001',
                'FechaExpedicionFactura' => '02-01-2024',
            ],
            'NombreRazonEmisor' => $original['record']['NombreRazonEmisor'],
            'TipoFactura' => 'R1',
            'TipoRectificativa' => $method,
            'FacturasRectificadas' => [
                'IDFacturaRectificada' => [
                    $original['record']['IDFactura'],
                ],
            ],
            'DescripcionOperacion' => 'Rectificacio de prova',
            'Destinatarios' => $original['record']['Destinatarios'],
            'Desglose' => [
                'DetalleDesglose' => [[
                    'Impuesto' => '01',
                    'ClaveRegimen' => '01',
                    'CalificacionOperacion' => 'S1',
                    'TipoImpositivo' => '21.00',
                    'BaseImponibleOimporteNoSujeto' => '-50.00',
                    'CuotaRepercutida' => '-10.50',
                ]],
            ],
            'CuotaTotal' => '-10.50',
            'ImporteTotal' => '-60.50',
            'SistemaInformatico' => $original['record']['SistemaInformatico'],
        ];
    }
}
