<?php

interface LegacyUsocCourseChangePricingSourceInterface
{
    public function enrollment(int $idInsc): array;

    public function edition(string $year, string $month, string $course): array;

    public function activeStandardPrice(int $priceId): string;

    public function activeUsocPrice(
        int $priceId,
        string $course,
        string $month,
        string $effectiveDate
    ): string;

    public function managementFee(string $hours): string;
}
