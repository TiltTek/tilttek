<?php

declare(strict_types=1);

namespace Tilttek\Handouts;

use DateTimeImmutable;

/**
 * A handout as one player finds it: the handout, the earliest moment it was revealed to them — to everyone or to them
 * by name —, and what they have seen of it.
 */
final readonly class Visible
{
    public function __construct(
        public Handout $handout,
        public DateTimeImmutable $revealedAt,
        public Freshness $freshness,
        public ?Sighting $sighting,
    ) {
    }
}
