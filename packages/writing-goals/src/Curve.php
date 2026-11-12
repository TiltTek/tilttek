<?php

declare(strict_types=1);

namespace Tilttek\WritingGoals;

use Tilttek\WritingGoals\Exception\InvalidGoal;

/**
 * The shape of a plan: the weight of each writing day by its place among all the writing days of the plan. The daily
 * quota is the words that remain shared among the writing days left in proportion to their weights, so a day missed
 * or a day ahead only reshapes what follows.
 */
final readonly class Curve
{
    public const int MAX_POINTS = 366;

    public const int MAX_WEIGHT = 1000;

    /**
     * @param list<int> $points
     */
    private function __construct(
        public CurveShape $shape,
        public array $points,
    ) {
    }

    /**
     * The curve of a shape, as a store reads it back: the points only count for a custom curve.
     *
     * @param list<int> $points
     */
    public static function of(CurveShape $shape, array $points = []): self
    {
        return match ($shape) {
            CurveShape::Linear => self::linear(),
            CurveShape::FrontLoaded => self::frontLoaded(),
            CurveShape::BackLoaded => self::backLoaded(),
            CurveShape::Custom => self::custom($points),
        };
    }

    public static function linear(): self
    {
        return new self(CurveShape::Linear, []);
    }

    public static function frontLoaded(): self
    {
        return new self(CurveShape::FrontLoaded, []);
    }

    public static function backLoaded(): self
    {
        return new self(CurveShape::BackLoaded, []);
    }

    /**
     * @param list<int> $points 1 to 366 weights of 0 to 1000, at least one above 0, stretched over the writing days:
     *                          `[1, 2, 1]` asks for twice as much in the middle third of the plan
     */
    public static function custom(array $points): self
    {
        $count = \count($points);

        if ($count < 1 || $count > self::MAX_POINTS) {
            throw InvalidGoal::because(\sprintf('A custom curve has 1 to %d weights; %d were given.', self::MAX_POINTS, $count));
        }

        foreach ($points as $point) {
            if ($point < 0 || $point > self::MAX_WEIGHT) {
                throw InvalidGoal::because(\sprintf('A weight of a custom curve is 0 to %d; %d is not one.', self::MAX_WEIGHT, $point));
            }
        }

        if (0 === array_sum($points)) {
            throw InvalidGoal::because('A custom curve has at least one weight above 0.');
        }

        return new self(CurveShape::Custom, $points);
    }

    /**
     * The weight of a writing day by its place, from 0, among the plan's writing days.
     */
    public function weight(int $position, int $count): int
    {
        return match ($this->shape) {
            CurveShape::Linear => 1,
            CurveShape::FrontLoaded => 2 * $count - 1 - $position,
            CurveShape::BackLoaded => $count + $position,
            CurveShape::Custom => $this->points[intdiv($position * \count($this->points), $count)],
        };
    }
}
