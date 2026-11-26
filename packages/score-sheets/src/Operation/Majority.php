<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets\Operation;

use Tilttek\ScoreSheets\Direction;
use Tilttek\ScoreSheets\Exception\InvalidSheet;
use Tilttek\ScoreSheets\Scoring\Step;

/**
 * Points by place in one category, across the participants: the most — or the fewest — takes the first points, the
 * next value the second, and so on. Tied participants all take the points of their place, and the places they fill
 * are skipped: two first, then a third. A participant below `atLeast`, when given, takes no place.
 */
final readonly class Majority implements Operation
{
    public const int MAX_PLACES = 8;

    /** @var non-empty-list<int> */
    public array $points;

    /**
     * @param list<int> $points the points of the first place, then of the second, and so on
     *
     * @throws InvalidSheet when the points are none, more than 8 or out of bounds, or the key is not one
     */
    public function __construct(
        public string $of,
        array $points,
        public Direction $direction = Direction::Most,
        public ?int $atLeast = null,
    ) {
        Operations::categories([$of], 'A majority');

        if ([] === $points || \count($points) > self::MAX_PLACES) {
            throw InvalidSheet::because(\sprintf('A majority gives points to 1 to %d places.', self::MAX_PLACES));
        }

        foreach ($points as $point) {
            Operations::constant($point, 'The points of a majority');
        }

        if (null !== $atLeast) {
            Operations::constant($atLeast, 'The threshold of a majority');
        }

        $this->points = $points;
    }

    public function reads(): array
    {
        return [$this->of];
    }

    public function apply(string $category, array $table): array
    {
        $ranked = [];

        foreach ($table as $participant => $values) {
            if (null === $this->atLeast || $values[$this->of] >= $this->atLeast) {
                $ranked[$participant] = $values[$this->of];
            }
        }

        Direction::Most === $this->direction ? arsort($ranked) : asort($ranked);
        $places = [];
        $place = 0;
        $previous = null;

        foreach (array_keys($ranked) as $index => $participant) {
            if ($ranked[$participant] !== $previous) {
                $place = $index + 1;
                $previous = $ranked[$participant];
            }

            $places[$participant] = $place;
        }

        $steps = [];

        foreach ($table as $participant => $values) {
            $place = $places[$participant] ?? 0;
            $steps[$participant] = new Step($category, 'majority', [$this->of => $values[$this->of], '#place' => $place], $this->points[$place - 1] ?? 0);
        }

        return $steps;
    }

    public function toArray(): array
    {
        return ['type' => 'majority', 'of' => $this->of, 'points' => $this->points, 'prefer' => $this->direction->value, 'at_least' => $this->atLeast];
    }
}
