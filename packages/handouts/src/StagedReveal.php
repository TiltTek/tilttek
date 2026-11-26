<?php

declare(strict_types=1);

namespace Tilttek\Handouts;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * A reveal prepared ahead, to everyone at the table or to one player, that happens on its trigger: at a moment, or on
 * a cue.
 */
final readonly class StagedReveal
{
    public function __construct(
        public ResourceReference $handout,
        public ResourceReference $table,
        public ?ResourceReference $player,
        public Trigger $trigger,
        public DateTimeImmutable $stagedAt,
        public ?ResourceReference $by = null,
    ) {
    }

    /**
     * `*` for everyone, or the player's reference.
     */
    public function key(): string
    {
        return $this->player?->toString() ?? Audience::EVERYONE;
    }
}
