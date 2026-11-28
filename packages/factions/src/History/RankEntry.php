<?php

declare(strict_types=1);

namespace Tilttek\Factions\History;

use DateTimeImmutable;
use Override;
use Tilttek\Factions\Reason;

/**
 * The rank a character holds within a faction changed: they joined, rose, fell or left.
 */
final readonly class RankEntry implements Entry
{
    /**
     * @param string|null $before the key of the rank held before, or null when they were not a member
     * @param string|null $after  the key of the rank held after, or null when they left
     */
    public function __construct(
        public string $campaign,
        public string $faction,
        public string $character,
        public ?string $before,
        public ?string $after,
        public Reason $reason,
        public string $by,
        public DateTimeImmutable $at,
    ) {
    }

    #[Override]
    public function concerns(string $faction): bool
    {
        return $faction === $this->faction;
    }
}
