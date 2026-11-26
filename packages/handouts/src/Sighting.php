<?php

declare(strict_types=1);

namespace Tilttek\Handouts;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * What a player has seen of a handout: the latest revision they opened, when they first opened the handout and when
 * they last did.
 */
final readonly class Sighting
{
    public function __construct(
        public ResourceReference $handout,
        public ResourceReference $player,
        public int $revision,
        public DateTimeImmutable $firstSeenAt,
        public DateTimeImmutable $lastSeenAt,
    ) {
    }

    public function seenAgain(int $revision, DateTimeImmutable $at): self
    {
        return new self($this->handout, $this->player, $revision, $this->firstSeenAt, $at);
    }
}
