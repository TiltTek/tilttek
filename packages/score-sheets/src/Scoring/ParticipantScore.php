<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets\Scoring;

use Tilttek\ScoreSheets\ParticipantId;

/**
 * The score of one participant: the value of every category, the total of those that count, the rank, and the steps
 * that explain each value.
 */
final readonly class ParticipantScore
{
    /**
     * @param array<string, int> $values by category, in the declared order
     * @param list<Step>         $steps  one by category, in the declared order
     * @param int                $rank   from 1; tied participants share it
     */
    public function __construct(
        public ParticipantId $participant,
        public array $values,
        public int $total,
        public int $rank,
        public array $steps,
    ) {
    }

    /**
     * @return array{participant: string, values: array<string, int>, total: int, rank: int, steps: list<array{category: string, operation: string, operands: array<string, int>, value: int}>}
     */
    public function toArray(): array
    {
        return [
            'participant' => $this->participant->value,
            'values' => $this->values,
            'total' => $this->total,
            'rank' => $this->rank,
            'steps' => array_map(static fn (Step $step): array => $step->toArray(), $this->steps),
        ];
    }
}
