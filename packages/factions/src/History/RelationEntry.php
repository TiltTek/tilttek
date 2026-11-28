<?php

declare(strict_types=1);

namespace Tilttek\Factions\History;

use DateTimeImmutable;
use Override;
use Tilttek\Factions\Reason;
use Tilttek\Factions\Relation;

/**
 * The relation between two factions changed: it was set, changed or ended. `faction` and `other` are the pair, the
 * lower key first.
 */
final readonly class RelationEntry implements Entry
{
    /**
     * @param Relation|null $before the relation before, or null when there was none
     * @param Relation|null $after  the relation after, or null when it ended
     */
    public function __construct(
        public string $campaign,
        public string $faction,
        public string $other,
        public ?Relation $before,
        public ?Relation $after,
        public Reason $reason,
        public string $by,
        public DateTimeImmutable $at,
    ) {
    }

    #[Override]
    public function concerns(string $faction): bool
    {
        return $faction === $this->faction || $faction === $this->other;
    }
}
