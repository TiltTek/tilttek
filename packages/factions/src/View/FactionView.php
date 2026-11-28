<?php

declare(strict_types=1);

namespace Tilttek\Factions\View;

use Tilttek\Factions\Membership;
use Tilttek\Factions\Rank;

/**
 * A faction as shown to its reader: to the game master, every standing and every member; to a player, the standing of
 * the party and their own, and their own rank.
 */
final readonly class FactionView
{
    /**
     * @param list<Rank>         $ranks     from the lowest to the highest
     * @param list<StandingView> $standings
     * @param list<Membership>   $members
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $summary,
        public bool $secret,
        public array $ranks,
        public array $standings,
        public array $members,
    ) {
    }

    /**
     * @return array{key: string, name: string, summary: string, secret: bool, ranks: list<array{key: string, title: string}>, standings: list<array{subject: string, value: int, tier: string, label: string}>, members: list<array{character: string, rank: string}>}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'summary' => $this->summary,
            'secret' => $this->secret,
            'ranks' => array_map(static fn (Rank $rank): array => ['key' => $rank->key, 'title' => $rank->title], $this->ranks),
            'standings' => array_map(static fn (StandingView $standing): array => $standing->toArray(), $this->standings),
            'members' => array_map(static fn (Membership $member): array => ['character' => $member->character, 'rank' => $member->rank], $this->members),
        ];
    }
}
