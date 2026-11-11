<?php

declare(strict_types=1);

namespace Tilttek\Progression;

use Tilttek\Progression\Exception\InvalidProgression;

/**
 * The experience each level starts at: level 1 at 0, then a threshold per level, always higher than the last, up to
 * the curve's last level. A curve is linear — the same step each level —, quadratic — the step grows with the level
 * —, exponential — each step a factor larger than the one before —, or a table the application writes, such as the
 * thresholds of a game's rules.
 *
 * *A hundred experience per level, fifty levels*: `Curve::linear(100, 50)`.
 */
final readonly class Curve
{
    public const int MAX_LEVELS = 1000;

    public const int MAX_EXPERIENCE = 1_000_000_000_000_000;

    public const int DEFAULT_LEVELS = 100;

    /**
     * @var non-empty-list<int> the experience each level starts at, level 1 first
     */
    public array $thresholds;

    /**
     * @param list<int> $thresholds the experience levels 2, 3… start at, each higher than the one before
     */
    public function __construct(array $thresholds)
    {
        if ([] === $thresholds) {
            throw InvalidProgression::because('A curve has at least 2 levels; this one has 1.');
        }

        if (\count($thresholds) >= self::MAX_LEVELS) {
            throw InvalidProgression::because(\sprintf('A curve has at most %d levels; this one has %d.', self::MAX_LEVELS, \count($thresholds) + 1));
        }

        $previous = 0;

        foreach ($thresholds as $threshold) {
            if ($threshold <= $previous || $threshold > self::MAX_EXPERIENCE) {
                throw InvalidProgression::because(\sprintf('Each level of a curve starts higher than the one before, at most at %d; %d follows %d.', self::MAX_EXPERIENCE, $threshold, $previous));
            }

            $previous = $threshold;
        }

        $this->thresholds = [0, ...$thresholds];
    }

    /**
     * The same step each level: level 2 at `step`, level 3 at twice `step`….
     */
    public static function linear(int $step, int $levels = self::DEFAULT_LEVELS): self
    {
        return self::steps($levels, static fn (int $level): float => $step * ($level - 1.0));
    }

    /**
     * A step that grows with the level: level 2 at `base`, level 3 at four times `base`, level 4 at nine times….
     */
    public static function quadratic(int $base, int $levels = self::DEFAULT_LEVELS): self
    {
        return self::steps($levels, static fn (int $level): float => $base * ($level - 1.0) ** 2);
    }

    /**
     * Each step `factor` times the one before, the first one `base`, rounded: with 100 and 1.5, level 2 at 100,
     * level 3 at 250, level 4 at 475….
     */
    public static function exponential(int $base, float $factor, int $levels = self::DEFAULT_LEVELS): self
    {
        if ($factor <= 1.0 || $factor > 10.0) {
            throw InvalidProgression::because(\sprintf('The factor of an exponential curve is above 1 and at most 10; %s is not one.', $factor));
        }

        $total = 0.0;

        return self::steps($levels, static function (int $level) use ($base, $factor, &$total): float {
            $total += round($base * $factor ** ($level - 2));

            return $total;
        });
    }

    /**
     * The thresholds the application writes, for levels 2, 3…: `Curve::table([300, 900, 2700])`.
     *
     * @param list<int> $thresholds
     */
    public static function table(array $thresholds): self
    {
        return new self($thresholds);
    }

    public function levels(): int
    {
        return \count($this->thresholds);
    }

    /**
     * The experience the level starts at.
     *
     * @throws InvalidProgression when the curve has no such level
     */
    public function experienceFor(int $level): int
    {
        return $this->thresholds[$level - 1] ?? throw InvalidProgression::because(\sprintf('The curve has levels 1 to %d; %d is not one of them.', $this->levels(), $level));
    }

    /**
     * Where this much experience stands on the curve.
     */
    public function level(int $experience): Level
    {
        $number = 1;

        while (isset($this->thresholds[$number]) && $this->thresholds[$number] <= $experience) {
            ++$number;
        }

        return new Level($number, $experience, $this->thresholds[$number - 1], $this->thresholds[$number] ?? null);
    }

    /**
     * @param callable(int): float $threshold the experience a level from 2 on starts at
     */
    private static function steps(int $levels, callable $threshold): self
    {
        if ($levels < 2 || $levels > self::MAX_LEVELS) {
            throw InvalidProgression::because(\sprintf('A curve has 2 to %d levels; %d is not a number of levels.', self::MAX_LEVELS, $levels));
        }

        $thresholds = [];

        for ($level = 2; $level <= $levels; ++$level) {
            $value = $threshold($level);

            if ($value < 1 || $value > self::MAX_EXPERIENCE) {
                throw InvalidProgression::because(\sprintf('Level %d of the curve would start at %s experience; a level starts at 1 to %d.', $level, $value, self::MAX_EXPERIENCE));
            }

            $thresholds[] = (int) $value;
        }

        return new self($thresholds);
    }
}
