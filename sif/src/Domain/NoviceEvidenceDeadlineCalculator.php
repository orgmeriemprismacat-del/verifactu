<?php

declare(strict_types=1);

namespace Prisma\Sif\Domain;

/**
 * Computes the novice-teacher evidence correction deadline.
 *
 * 48 elapsed hours are counted only while the local calendar date is a
 * working weekday (Monday to Friday) and is not in the supplied holiday list.
 * All hours of an eligible date count; opening hours are not considered.
 *
 * The caller must supply the applicable holiday calendar and timezone.
 * This calculator does not choose the jurisdiction, send notices, or deny
 * applications automatically.
 */
final class NoviceEvidenceDeadlineCalculator
{
    public function expiresAt(\DateTimeImmutable $requestSentAt, \DateTimeZone $timezone, array $holidayDates): \DateTimeImmutable
    {
        $holidays = [];
        foreach ($holidayDates as $date) {
            if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) {
                throw new \InvalidArgumentException('Invalid holiday date; expected YYYY-MM-DD');
            }

            [$year, $month, $day] = array_map('intval', explode('-', $date));
            if (!checkdate($month, $day, $year)) {
                throw new \InvalidArgumentException('Invalid holiday calendar date');
            }

            $holidays[$date] = true;
        }

        $cursor = $requestSentAt->setTimezone($timezone);
        $secondsRemaining = 48 * 60 * 60;

        while ($secondsRemaining > 0) {
            $date = $cursor->format('Y-m-d');
            $nextMidnight = $cursor->setTime(0, 0, 0)->modify('+1 day');
            $isWorkingDay = (int) $cursor->format('N') <= 5 && !isset($holidays[$date]);

            if ($isWorkingDay) {
                $availableSeconds = $nextMidnight->getTimestamp() - $cursor->getTimestamp();
                if ($secondsRemaining <= $availableSeconds) {
                    return (new \DateTimeImmutable('@' . ($cursor->getTimestamp() + $secondsRemaining)))
                        ->setTimezone($timezone);
                }

                $secondsRemaining -= $availableSeconds;
            }

            $cursor = $nextMidnight;
        }

        return $cursor;
    }
}
