<?php

declare(strict_types=1);

namespace Tilttek\Handouts;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * A handout revealed to everyone at its table, or to one player: the revision shown then, the moment and the reference
 * that revealed it. Players see the handout's current revision; the disclosure keeps which one was shown.
 */
final readonly class Disclosure
{
    public function __construct(
        public ResourceReference $handout,
        public ResourceReference $table,
        public ?ResourceReference $player,
        public int $revision,
        public DateTimeImmutable $revealedAt,
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
