<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Tests\Support\Assert;

require_once dirname(__DIR__, 3) . '/codi-drive/pay-prisma-cat-canvis-verifactu/inc/GroupPaymentGate.php';

final class GroupPaymentGateTest
{
    public function testAuthorizesFullGroupPaymentFromServerRows(): void
    {
        $checkout = \GroupPaymentGate::authorizeRows(
            $this->rows(),
            $this->responsible(),
            ['importPagare' => '200.00'],
            950
        );

        Assert::same(950, $checkout['idpag']);
        Assert::same('GRUP', $checkout['source_type']);
        Assert::same('950', $checkout['source_id']);
        Assert::same('200.00', $checkout['payment_amount']);
        Assert::same('200.00', $checkout['pending_amount']);
        Assert::same(2, count($checkout['snapshot']['items']));
        Assert::same('Responsable', $checkout['snapshot']['responsible']['NOM']);
        Assert::same(751, $checkout['snapshot']['items'][0]['inscription']['ID']);
        Assert::same('120.00', $checkout['snapshot']['items'][0]['inscription']['TOTAL']);
        Assert::same('80.00', $checkout['snapshot']['items'][1]['inscription']['TOTAL']);
    }

    public function testRejectsBrowserAmountDifferentFromServerPendingTotal(): void
    {
        Assert::throws(\RuntimeException::class, function (): void {
            \GroupPaymentGate::authorizeRows(
                $this->rows(),
                $this->responsible(),
                ['importPagare' => '199.99'],
                950
            );
        });
    }

    public function testRejectsPreviouslyPaidGroupFromAutomaticInitialPaymentPath(): void
    {
        $rows = $this->rows();
        $rows[0]['PAGAMENT'] = '20.00';

        Assert::throws(\RuntimeException::class, function () use ($rows): void {
            \GroupPaymentGate::authorizeRows(
                $rows,
                $this->responsible(),
                ['importPagare' => '180.00'],
                950
            );
        });
    }

    public function testRejectsDuplicatedParticipant(): void
    {
        $rows = $this->rows();
        $rows[1]['ID'] = $rows[0]['ID'];

        Assert::throws(\RuntimeException::class, function () use ($rows): void {
            \GroupPaymentGate::authorizeRows(
                $rows,
                $this->responsible(),
                ['importPagare' => '200.00'],
                950
            );
        });
    }

    public function testRejectsMissingResponsibleFiscalIdentity(): void
    {
        $responsible = $this->responsible();
        $responsible['DNI'] = '';

        Assert::throws(\RuntimeException::class, function () use ($responsible): void {
            \GroupPaymentGate::authorizeRows(
                $this->rows(),
                $responsible,
                ['importPagare' => '200.00'],
                950
            );
        });
    }

    private function responsible(): array
    {
        return [
            'NOM' => 'Responsable',
            'COGNOMS' => 'Grup',
            'DNI' => '44444444G',
            'CORREU' => 'responsable@example.test',
            'ADRECA' => 'Carrer Grup 4',
            'Codi_Postal' => '08001',
            'Poblacio' => 'Barcelona',
        ];
    }

    private function rows(): array
    {
        return [
            [
                'ID' => 751,
                'IDPAG' => 950,
                'ANY' => 2026,
                'MES' => '10',
                'CURS' => 'ABC',
                'NOM' => 'Anna',
                'COGNOMS' => 'Participant',
                'DNI' => '11111111H',
                'CORREU' => 'anna@example.test',
                'A_PAGAR' => '120.00',
                'PAGAMENT' => '0.00',
                'FRACCIONAT' => 0,
                'FACTURA_RELACIONADA' => null,
                'NOM_CURS' => 'Comunicacio assertiva',
            ],
            [
                'ID' => 752,
                'IDPAG' => 950,
                'ANY' => 2026,
                'MES' => '10',
                'CURS' => 'ABC',
                'NOM' => 'Biel',
                'COGNOMS' => 'Participant',
                'DNI' => '22222222J',
                'CORREU' => 'biel@example.test',
                'A_PAGAR' => '80.00',
                'PAGAMENT' => '0.00',
                'FRACCIONAT' => 0,
                'FACTURA_RELACIONADA' => null,
                'NOM_CURS' => 'Comunicacio assertiva',
            ],
        ];
    }
}
