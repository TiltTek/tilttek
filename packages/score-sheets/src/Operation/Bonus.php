<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets\Operation;

use Tilttek\ScoreSheets\Comparison;
use Tilttek\ScoreSheets\Exception\InvalidSheet;
use Tilttek\ScoreSheets\Scoring\Step;

/**
 * Points when one category meets a condition — at least, at most or exactly a threshold —, none otherwise.
 */
final readonly class Bonus implements Operation
{
    /**
     * @throws InvalidSheet when the key is not one, or the threshold or the points are out of bounds
     */
    public function __construct(
        public string $of,
        public Comparison $comparison,
        public int $threshold,
        public int $points,
    ) {
        Operations::categories([$of], 'A bonus');
        Operations::constant($threshold, 'The threshold of a bonus');
        Operations::constant($points, 'The points of a bonus');
    }

    public function reads(): array
    {
        return [$this->of];
    }

    public function apply(string $category, array $table): array
    {
        $steps = [];

        foreach ($table as $participant => $values) {
            $value = $values[$this->of];
            $steps[$participant] = new Step($category, 'bonus', [$this->of => $value, '#threshold' => $this->threshold], $this->comparison->holds($value, $this->threshold) ? $this->points : 0);
        }

        return $steps;
    }

    public function toArray(): array
    {
        return ['type' => 'bonus', 'of' => $this->of, $this->comparison->value => $this->threshold, 'points' => $this->points];
    }
}
