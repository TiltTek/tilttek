<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards;

use Phil\Resource\ResourceReference;

/**
 * A line of a ranking as one viewer may see it: the rank and the score, and who holds them — the member, for one who
 * chose to appear under their name or for the viewer's own line; the pseudonym, for one who chose one; neither, for a
 * member who chose not to appear.
 */
final readonly class Row
{
    public function __construct(
        public int $rank,
        public int $value,
        public ?ResourceReference $member = null,
        public ?string $pseudonym = null,
        public bool $self = false,
    ) {
    }

    /**
     * What the line is shown as: the pseudonym, or else the member's reference, for the application to name; null for
     * a hidden member.
     */
    public function shownAs(): ?string
    {
        return $this->pseudonym ?? $this->member?->toString();
    }

    /**
     * Whether the viewer may not know whose line this is.
     */
    public function isHidden(): bool
    {
        return !$this->member instanceof ResourceReference && null === $this->pseudonym;
    }
}
