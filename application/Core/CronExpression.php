<?php
declare(strict_types=1);

namespace AP\Core;

use DateTimeImmutable;
use InvalidArgumentException;

/** Five-field cron expressions: minute hour day-of-month month day-of-week (supports * , - / and 7 = Sunday). */
final class CronExpression
{
    /** @var array<int, int[]> */
    private array $fields = [];
    private bool $domStar;
    private bool $dowStar;

    public function __construct(public readonly string $expression)
    {
        $parts = preg_split('/\s+/', trim($expression)) ?: [];
        if (count($parts) !== 5) {
            throw new InvalidArgumentException('A schedule needs five parts: minute hour day month weekday.');
        }
        $ranges = [[0, 59], [0, 23], [1, 31], [1, 12], [0, 7]];
        foreach ($parts as $i => $part) {
            $this->fields[$i] = $this->parse($part, $ranges[$i][0], $ranges[$i][1]);
        }
        $this->fields[4] = array_values(array_unique(array_map(static fn($d) => $d === 7 ? 0 : $d, $this->fields[4])));
        $this->domStar = str_starts_with($parts[2], '*');
        $this->dowStar = str_starts_with($parts[4], '*');
    }

    public static function valid(string $expression): bool
    {
        try {
            new self($expression);
            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /** @return int[] */
    private function parse(string $part, int $min, int $max): array
    {
        $values = [];
        foreach (explode(',', $part) as $piece) {
            if (!preg_match('#^(\*|\d+(?:-\d+)?)(?:/(\d+))?$#', $piece, $m)) {
                throw new InvalidArgumentException("Cannot read \"{$piece}\" in the schedule.");
            }
            $step = isset($m[2]) ? (int) $m[2] : 1;
            if ($step < 1) {
                throw new InvalidArgumentException('A step must be 1 or more.');
            }
            if ($m[1] === '*') {
                [$from, $to] = [$min, $max === 7 ? 6 : $max];
            } elseif (str_contains($m[1], '-')) {
                [$from, $to] = array_map('intval', explode('-', $m[1]));
            } else {
                $from = (int) $m[1];
                $to = isset($m[2]) ? $max : $from;
            }
            if ($from < $min || $to > $max || $from > $to) {
                throw new InvalidArgumentException("\"{$piece}\" is outside the allowed range {$min}-{$max}.");
            }
            for ($v = $from; $v <= $to; $v += $step) {
                $values[] = $v;
            }
        }
        sort($values);
        return array_values(array_unique($values));
    }

    public function matches(DateTimeImmutable $t): bool
    {
        if (!in_array((int) $t->format('i'), $this->fields[0], true) || !in_array((int) $t->format('G'), $this->fields[1], true)
            || !in_array((int) $t->format('n'), $this->fields[3], true)) {
            return false;
        }
        $dom = in_array((int) $t->format('j'), $this->fields[2], true);
        $dow = in_array((int) $t->format('w'), $this->fields[4], true);
        // Classic cron: when both day fields are restricted, either may match.
        if (!$this->domStar && !$this->dowStar) {
            return $dom || $dow;
        }
        return $dom && $dow;
    }

    /** First matching minute strictly after $after (null if none within about 5 years). */
    public function next(DateTimeImmutable $after): ?DateTimeImmutable
    {
        $t = $after->setTime((int) $after->format('G'), (int) $after->format('i'), 0)->modify('+1 minute');
        $limit = $after->modify('+5 years');
        while ($t < $limit) {
            if (!in_array((int) $t->format('n'), $this->fields[3], true)) {
                $t = $t->modify('first day of next month')->setTime(0, 0);
                continue;
            }
            $dom = in_array((int) $t->format('j'), $this->fields[2], true);
            $dow = in_array((int) $t->format('w'), $this->fields[4], true);
            $dayOk = (!$this->domStar && !$this->dowStar) ? ($dom || $dow) : ($dom && $dow);
            if (!$dayOk) {
                $t = $t->modify('+1 day')->setTime(0, 0);
                continue;
            }
            if (!in_array((int) $t->format('G'), $this->fields[1], true)) {
                $t = $t->modify('+1 hour')->setTime((int) $t->modify('+1 hour')->format('G'), 0);
                continue;
            }
            if (!in_array((int) $t->format('i'), $this->fields[0], true)) {
                $t = $t->modify('+1 minute');
                continue;
            }
            return $t;
        }
        return null;
    }

    /** A plain-English description for the common patterns (falls back to the expression). */
    public function describe(): string
    {
        $e = trim($this->expression);
        $simple = [
            '* * * * *' => 'Every minute', '*/5 * * * *' => 'Every 5 minutes', '*/10 * * * *' => 'Every 10 minutes',
            '*/15 * * * *' => 'Every 15 minutes', '*/30 * * * *' => 'Every 30 minutes', '0 * * * *' => 'Every hour',
        ];
        if (isset($simple[$e])) {
            return $simple[$e];
        }
        if (preg_match('/^(\d+) (\d+) \* \* \*$/', $e, $m)) {
            return sprintf('Every day at %02d:%02d', $m[2], $m[1]);
        }
        if (preg_match('/^(\d+) (\d+) \* \* ([0-7])$/', $e, $m)) {
            $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            return sprintf('Every %s at %02d:%02d', $days[(int) $m[3]], $m[2], $m[1]);
        }
        if (preg_match('/^(\d+) (\d+) (\d+) \* \*$/', $e, $m)) {
            return sprintf('Monthly on day %d at %02d:%02d', $m[3], $m[2], $m[1]);
        }
        return $e;
    }
}
