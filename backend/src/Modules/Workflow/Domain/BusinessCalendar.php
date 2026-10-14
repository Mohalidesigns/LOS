<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Domain;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Business-hour arithmetic for SLA clocks (TRD §6.5): working days and a
 * daily window in the tenant's timezone. Public holidays come with the
 * tenant calendar (P3); until then only weekends are excluded.
 */
final readonly class BusinessCalendar
{
    /** @param list<int> $days ISO weekdays, 1 = Monday */
    public function __construct(public DateTimeZone $zone, public array $days, public int $startMinute, public int $endMinute) {}

    public static function lagos(): self
    {
        return new self(new DateTimeZone('Africa/Lagos'), [1, 2, 3, 4, 5], 8 * 60, 17 * 60);
    }

    /** @param array<string, mixed> $c {timezone, days, start "08:00", end "17:00"} */
    public static function of(array $c): self
    {
        $minutes = static fn (string $hhmm): int => (int) substr($hhmm, 0, 2) * 60 + (int) substr($hhmm, 3, 2);

        return new self(
            new DateTimeZone(is_string($c['timezone'] ?? null) ? $c['timezone'] : 'Africa/Lagos'),
            array_values(array_map('intval', is_array($c['days'] ?? null) ? $c['days'] : [1, 2, 3, 4, 5])),
            $minutes(is_string($c['start'] ?? null) ? $c['start'] : '08:00'),
            $minutes(is_string($c['end'] ?? null) ? $c['end'] : '17:00'),
        );
    }

    /** The instant that is $minutes business minutes after $from. */
    public function addBusinessMinutes(DateTimeImmutable $from, int $minutes): DateTimeImmutable
    {
        $t = $this->normalise($from->setTimezone($this->zone));
        $left = max(0, $minutes);
        while ($left > 0) {
            $endOfDay = $t->setTime(intdiv($this->endMinute, 60), $this->endMinute % 60);
            $available = intdiv($endOfDay->getTimestamp() - $t->getTimestamp(), 60);
            if ($left <= $available) {
                return $t->modify("+{$left} minutes");
            }
            $left -= $available;
            $t = $this->normalise($endOfDay->modify('+1 minute'));
        }

        return $t;
    }

    /** Business minutes between two instants (0 when $to is not after $from). */
    public function businessMinutesBetween(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        $a = $this->normalise($from->setTimezone($this->zone));
        $b = $to->setTimezone($this->zone);
        $total = 0;
        while ($a < $b) {
            $endOfDay = $a->setTime(intdiv($this->endMinute, 60), $this->endMinute % 60);
            $stop = $b < $endOfDay ? $b : $endOfDay;
            $total += max(0, intdiv($stop->getTimestamp() - $a->getTimestamp(), 60));
            if ($b <= $endOfDay) {
                break;
            }
            $a = $this->normalise($endOfDay->modify('+1 minute'));
        }

        return $total;
    }

    /** Move to the next working minute (inside a working day's window). */
    private function normalise(DateTimeImmutable $t): DateTimeImmutable
    {
        for ($i = 0; $i < 14; $i++) {
            $minute = (int) $t->format('G') * 60 + (int) $t->format('i');
            if (in_array((int) $t->format('N'), $this->days, true)) {
                if ($minute < $this->startMinute) {
                    return $t->setTime(intdiv($this->startMinute, 60), $this->startMinute % 60);
                }
                if ($minute < $this->endMinute) {
                    return $t;
                }
            }
            $t = $t->modify('+1 day')->setTime(intdiv($this->startMinute, 60), $this->startMinute % 60);
        }

        return $t;
    }
}
