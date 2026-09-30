<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Tests\Support\Assert;

require_once dirname(__DIR__, 3) . '/codi-drive/pay-prisma-cat-canvis-verifactu/inc/PackPaymentGate.php';

final class PackPaymentGateTest
{
    public function testAuthorizesFullPackPaymentFromFrozenCommercialRows(): void
    {
        $checkout = \PackPaymentGate::authorizeRows(
            $this->rows(),
            ['importPagare' => '210.00'],
            910
        );

        Assert::same(910, $checkout['idpag']);
        Assert::same(77, $checkout['pack_id']);
        Assert::same('210.00', $checkout['payment_amount']);
        Assert::same('210.00', $checkout['pending_amount']);
        Assert::same(false, $checkout['fraccionat']);
        Assert::same(1, $checkout['snapshot']['items'][0]['ordinal']);
        Assert::same(2, $checkout['snapshot']['items'][1]['ordinal']);
        Assert::same('120.00', $checkout['snapshot']['items'][0]['inscription']['TOTAL']);
        Assert::same('90.00', $checkout['snapshot']['items'][1]['inscription']['TOTAL']);
        Assert::same('25.00', $checkout['snapshot']['items'][1]['inscription']['DESC_PCT']);
    }

    public function testRejectsPartialAmountInEcommercePackCheckout(): void
    {
        Assert::throws(\RuntimeException::class, function (): void {
            \PackPaymentGate::authorizeRows(
                $this->rows(),
                ['importPagare' => '100.00'],
                910
            );
        });
    }

    public function testRejectsPreviouslyPaidPackForAutomaticFullInvoiceFlow(): void
    {
        $rows = $this->rows();
        $rows[0]['PAGAMENT'] = '20.00';

        Assert::throws(\RuntimeException::class, function () use ($rows): void {
            \PackPaymentGate::authorizeRows(
                $rows,
                ['importPagare' => '190.00'],
                910
            );
        });
    }

    public function testRejectsMissingCommercialOrdinal(): void
    {
        $rows = $this->rows();
        $rows[1]['OBSERVACIONS'] = str_replace(
            ' PACK_ORDINAL|2',
            '',
            $rows[1]['OBSERVACIONS']
        );

        Assert::throws(\RuntimeException::class, function () use ($rows): void {
            \PackPaymentGate::authorizeRows(
                $rows,
                ['importPagare' => '210.00'],
                910
            );
        });
    }

    private function rows(): array
    {
        return [
            [
                'ID' => 501,
                'IDPAG' => 910,
                'ANY' => 2026,
                'MES' => '06',
                'CURS' => 'ABC',
                'NOM' => 'Maria',
                'COGNOMS' => 'Exemple',
                'DNI' => '12345678Z',
                'CORREU' => 'maria@example.test',
                'ADRECA' => 'Carrer Exemple 1',
                'Codi_Postal' => '08001',
                'Poblacio' => 'Barcelona',
                'A_PAGAR' => '120.00',
                'PAGAMENT' => '0.00',
                'FRACCIONAT' => 0,
                'OBSERVACIONS' => 'PACK|77 PACK_ORDINAL|1 PACK_BASE|120.00 PACK_DISCOUNT|0.00 PACK_DISCOUNT_PCT|0.00 PACK_TOTAL|120.00',
                'NOM_CURS' => 'Gestio emocional',
            ],
            [
                'ID' => 502,
                'IDPAG' => 910,
                'ANY' => 2026,
                'MES' => '07',
                'CURS' => 'DEF',
                'NOM' => 'Maria',
                'COGNOMS' => 'Exemple',
                'DNI' => '12345678Z',
                'CORREU' => 'maria@example.test',
                'ADRECA' => 'Carrer Exemple 1',
                'Codi_Postal' => '08001',
                'Poblacio' => 'Barcelona',
                'A_PAGAR' => '90.00',
                'PAGAMENT' => '0.00',
                'FRACCIONAT' => 0,
                'OBSERVACIONS' => 'PACK|77 PACK_ORDINAL|2 PACK_BASE|120.00 PACK_DISCOUNT|30.00 PACK_DISCOUNT_PCT|25.00 PACK_TOTAL|90.00',
                'NOM_CURS' => 'Mindfulness a l aula',
            ],
        ];
    }
}
