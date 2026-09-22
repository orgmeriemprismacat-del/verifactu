<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Domain\NoviceEvidenceDeadlineCalculator;
use Prisma\Sif\Tests\Support\Assert;

final class NoviceEvidenceDeadlineCalculatorTest
{
    public function testWeekdaysCountAllHoursNotOnlyOfficeHours(): void
    {
        Assert::same('2026-09-23 10:00:00 +02:00', $this->expires('2026-09-21 10:00:00'));
    }

    public function testSkipsWeekendWithoutResettingPartialDay(): void
    {
        Assert::same('2026-09-29 15:00:00 +02:00', $this->expires('2026-09-25 15:00:00'));
    }

    public function testSkipsHolidaysInAdditionToWeekend(): void
    {
        Assert::same('2026-09-30 15:00:00 +02:00', $this->expires('2026-09-25 15:00:00', ['2026-09-28']));
    }

    public function testCanStartOnWeekendAndWaitUntilNextEligibleDay(): void
    {
        Assert::same('2026-09-30 00:00:00 +02:00', $this->expires('2026-09-26 12:00:00'));
    }

    public function testHandlesClockChangeOnExcludedSunday(): void
    {
        Assert::same('2026-10-28 10:00:00 +01:00', $this->expires('2026-10-26 10:00:00'));
    }

    public function testRejectsImpossibleHolidayDate(): void
    {
        Assert::throws(\InvalidArgumentException::class, function (): void {
            $this->expires('2026-09-25 15:00:00', ['2026-02-30']);
        });
    }

    private function expires(string $sentAt, array $holidays = []): string
    {
        $zone = new \DateTimeZone('Europe/Madrid');
        $sent = new \DateTimeImmutable($sentAt, $zone);
        $deadline = (new NoviceEvidenceDeadlineCalculator())->expiresAt($sent, $zone, $holidays);

        return $deadline->format('Y-m-d H:i:s P');
    }
}
