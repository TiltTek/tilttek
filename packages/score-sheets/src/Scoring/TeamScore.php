<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets\Scoring;

use Tilttek\ScoreSheets\TeamCombine;
use Tilttek\ScoreSheets\TeamId;

/**
 * The score of one team, and how it was made: the total of each member and the way they combine.
 */
final readonly class TeamScore
{
    /**
     * @param list<array{participant: string, total: int}> $members the total of each member, in the team's order
     * @param int                                          $rank    from 1; tied teams share it
     */
    public function __construct(
        public TeamId $team,
        public array $members,
        public TeamCombine $combine,
        public int $total,
        public int $rank,
    ) {
    }

    /**
     * @return array{team: string, members: list<array{participant: string, total: int}>, combine: string, total: int, rank: int}
     */
    public function toArray(): array
    {
        return ['team' => $this->team->value, 'members' => $this->members, 'combine' => $this->combine->value, 'total' => $this->total, 'rank' => $this->rank];
    }
}
