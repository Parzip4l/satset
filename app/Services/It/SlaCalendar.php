<?php

namespace App\Services\It;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/** Intervals are local wall times; consumption and persisted deadlines are seconds in UTC. */
class SlaCalendar
{
    public function instant(DateTimeInterface|string $time): CarbonImmutable
    {
        return CarbonImmutable::parse($time, 'UTC')->utc()->startOfSecond();
    }

    public function intervals(array $calendar, CarbonImmutable $day): array
    {
        $date = $day->format('Y-m-d');
        $override = collect($calendar['exceptions'])->firstWhere('date', $date);
        $windows = $override !== null ? $override['windows'] : (in_array($date, $calendar['holidays'], true) ? [] : ($calendar['weekly'][(string) $day->isoWeekday()] ?? []));
        $result = [];
        foreach ($windows as $w) {
            $start = CarbonImmutable::parse($date.' '.$w['start'], $calendar['timezone']);
            $end = $w['end'] === '24:00' ? $day->addDay()->startOfDay() : CarbonImmutable::parse($date.' '.$w['end'], $calendar['timezone']);
            if ($end->greaterThan($start)) {
                $result[] = [$start->utc(), $end->utc()];
            }
        }

        return $result;
    }

    public function seconds(array $calendar, DateTimeInterface|string $from, DateTimeInterface|string $to): int
    {
        $start = $this->instant($from);
        $end = $this->instant($to);
        if ($end->lessThanOrEqualTo($start)) {
            return 0;
        }
        if ($calendar['mode'] === '24x7') {
            return (int) $start->diffInSeconds($end);
        }
        $day = $start->setTimezone($calendar['timezone'])->startOfDay();
        $last = $end->setTimezone($calendar['timezone'])->startOfDay();
        $seconds = 0;
        $guard = 0;
        while ($day->lessThanOrEqualTo($last)) {
            if (++$guard > 10000) {
                throw new \RuntimeException('Rentang kalender SLA melebihi batas 10000 hari.');
            }
            foreach ($this->intervals($calendar, $day) as [$a,$b]) {
                $a = $a->max($start);
                $b = $b->min($end);
                if ($b->greaterThan($a)) {
                    $seconds += (int) $a->diffInSeconds($b);
                }
            }$day = $day->addDay();
        }

return $seconds;
    }

    public function deadline(array $calendar, DateTimeInterface|string $from, int $seconds): CarbonImmutable
    {
        $start = $this->instant($from);
        if ($seconds <= 0) {
            return $start;
        }
        if ($calendar['mode'] === '24x7') {
            return $start->addSeconds($seconds);
        }
        $day = $start->setTimezone($calendar['timezone'])->startOfDay();
        for ($guard = 0; $guard < 10000; $guard++,$day = $day->addDay()) {
            foreach ($this->intervals($calendar, $day) as [$a,$b]) {
                $a = $a->max($start);
                if ($b->lessThanOrEqualTo($a)) {
                    continue;
                }
                $available = (int) $a->diffInSeconds($b);
                if ($seconds <= $available) {
                    return $a->addSeconds($seconds);
                }$seconds -= $available;
            }
        }throw new \RuntimeException('Kalender tidak menyediakan kapasitas untuk target SLA.');
    }
}
