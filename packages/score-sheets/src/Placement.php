<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets;

/**
 * The place of one participant — or of one team, with its members — in a closed match.
 */
final readonly class Placement
{
    /**
     * @param string       $id      the participant or the team
     * @param list<string> $members the members of a team; empty for a participant
     */
    public function __construct(
        public string $id,
        public int $rank,
        public int $total,
        public array $members = [],
    ) {
    }

    /**
     * @return array{id: string, rank: int, total: int, members: list<string>}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'rank' => $this->rank, 'total' => $this->total, 'members' => $this->members];
    }
}
