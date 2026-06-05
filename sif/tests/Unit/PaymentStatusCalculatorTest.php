<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Domain\PaymentStatusCalculator;
use Prisma\Sif\Tests\Support\Assert;

final class PaymentStatusCalculatorTest
{
    public function testPendingWhenNoAmountAssigned(): void
    {
        Assert::same('PENDING', (new PaymentStatusCalculator())->calculate('120.00', '0.00', '0.00'));
    }

    public function testPaidWhenAssignedEqualsTotal(): void
    {
        Assert::same('PAID', (new PaymentStatusCalculator())->calculate('120.00', '120.00', '0.00'));
    }

    public function testPartialWhenAssignedIsLowerThanTotal(): void
    {
        Assert::same('PARTIAL', (new PaymentStatusCalculator())->calculate('120.00', '60.00', '0.00'));
    }

    public function testOverpaidWhenAssignedIsHigherThanTotal(): void
    {
        Assert::same('OVERPAID', (new PaymentStatusCalculator())->calculate('120.00', '130.00', '0.00'));
    }

    public function testPartiallyRefundedWhenRefundLeavesPositiveNetAmount(): void
    {
        Assert::same('PARTIALLY_REFUNDED', (new PaymentStatusCalculator())->calculate('120.00', '120.00', '40.00'));
    }

    public function testRefundedWhenRefundCancelsNetAmount(): void
    {
        Assert::same('REFUNDED', (new PaymentStatusCalculator())->calculate('120.00', '120.00', '120.00'));
    }
}
