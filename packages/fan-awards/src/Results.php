<?php

declare(strict_types=1);

namespace Tilttek\FanAwards;

/**
 * What a category's vote gave: the winners — several when tied, none when "no award" won or nobody voted —, whether
 * "no award" won, and the count of each phase in order, the detailed tallies `phil/poll` kept.
 */
final readonly class Results
{
    /**
     * @param list<Nominee>    $winners
     * @param list<PhaseCount> $counts
     */
    public function __construct(
        public Category $category,
        public array $winners,
        public bool $noAward,
        public array $counts,
    ) {
    }

    public function count(Phase $phase): ?PhaseCount
    {
        foreach ($this->counts as $count) {
            if ($count->phase === $phase) {
                return $count;
            }
        }

        return null;
    }
}
