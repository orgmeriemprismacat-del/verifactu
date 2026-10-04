<?php

interface LegacyUsocCourseChangeDestinationStoreInterface
{
    public function acquire(string $lockName, int $timeoutSeconds = 10): void;

    public function release(string $lockName): void;

    public function source(int $idInsc): array;

    public function findByMarker(string $marker): ?array;

    public function insertFromSource(
        int $sourceId,
        string $targetYear,
        string $targetMonth,
        string $targetCourse,
        string $targetStudentTotal,
        string $marker
    ): array;
}
