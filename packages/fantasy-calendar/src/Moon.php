<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar;

use Tilttek\FantasyCalendar\Exception\InvalidCalendar;

/**
 * A moon with a cycle of its own, as an exact fraction of days — 29.530588 days is 7382647/250000 —, and a day on
 * which it is new. Its phase on any day is computed with integers.
 */
final readonly class Moon
{
    public int $cycleNumerator;

    public int $cycleDenominator;

    /**
     * @param int $cycleNumerator   the length of a cycle in days, times its denominator
     * @param int $cycleDenominator 1 for a cycle of whole days
     * @param int $newMoon          the number of a day on which the moon is new
     *
     * @throws InvalidCalendar when the cycle is out of its bounds
     */
    public function __construct(public string $id, public string $name, int $cycleNumerator, int $cycleDenominator = 1, public int $newMoon = 0)
    {
        Limits::identifier($id, 'A moon identifier');
        Limits::label($name, 'A moon name');
        Limits::between($cycleDenominator, 1, Limits::MOON_PRECISION, 'The denominator of a moon cycle');
        Limits::between($cycleNumerator, $cycleDenominator, Limits::MOON_CYCLE * $cycleDenominator, 'The cycle of a moon, times its denominator');
        Limits::between($newMoon, -Limits::YEAR, Limits::YEAR, 'The day of a new moon');
        $divisor = $this->gcd($cycleNumerator, $cycleDenominator);
        $this->cycleNumerator = intdiv($cycleNumerator, $divisor);
        $this->cycleDenominator = intdiv($cycleDenominator, $divisor);
    }

    /**
     * A moon whose cycle is written as a decimal number of days, such as "29.530588", read exactly.
     *
     * @throws InvalidCalendar when the text is not a decimal number of at most six decimals, or is out of bounds
     */
    public static function ofDecimalCycle(string $id, string $name, string $cycle, int $newMoon = 0): self
    {
        if (1 !== preg_match('/^([0-9]{1,5})(?:\.([0-9]{1,6}))?$/', $cycle, $match)) {
            throw InvalidCalendar::because(\sprintf('The cycle of a moon is a decimal number of days with at most six decimals; "%s" is not.', mb_strimwidth($cycle, 0, 40, '…')));
        }

        $decimals = $match[2] ?? '';
        $denominator = 10 ** \strlen($decimals);

        return new self($id, $name, (int) $match[1] * $denominator + (int) ('0'.$decimals), $denominator, $newMoon);
    }

    /**
     * The cycle written as a decimal number of days, the form {@see self::ofDecimalCycle()} reads, when it has at most
     * six decimals; otherwise null.
     */
    public function decimalCycle(): ?string
    {
        for ($decimals = 0; $decimals <= 6; ++$decimals) {
            $scaled = $this->cycleNumerator * 10 ** $decimals;
            if (0 === $scaled % $this->cycleDenominator) {
                // A cycle lasts a day at least, so the quotient has a digit before its decimals.
                $digits = (string) intdiv($scaled, $this->cycleDenominator);

                return 0 === $decimals ? $digits : substr($digits, 0, -$decimals).'.'.substr($digits, -$decimals);
            }
        }

        return null;
    }

    /**
     * The phase of the moon on a day: the eighth of its cycle the day falls in, centred on each exact phase.
     */
    public function phaseOn(int $day): Phase
    {
        $position = Limits::mod(($day - $this->newMoon) * $this->cycleDenominator, $this->cycleNumerator);
        $eighth = Limits::mod(Limits::floorDiv(16 * $position + $this->cycleNumerator, 2 * $this->cycleNumerator), 8);

        return Phase::cases()[$eighth];
    }

    /**
     * The first day from the day given, that one included, on which the moon shows the phase; null when its cycle is so
     * short that it skips that phase.
     */
    public function nextPhase(int $from, Phase $phase): ?int
    {
        $days = intdiv($this->cycleNumerator, $this->cycleDenominator) + 1;
        for ($day = $from; $day <= $from + $days; ++$day) {
            if ($phase === $this->phaseOn($day)) {
                return $day;
            }
        }

        return null;
    }

    private function gcd(int $a, int $b): int
    {
        while (0 !== $b) {
            [$a, $b] = [$b, $a % $b];
        }

        return $a;
    }
}
