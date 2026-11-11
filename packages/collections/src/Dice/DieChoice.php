<?php

declare(strict_types=1);

namespace Tilttek\Collections\Dice;

use Phil\Resource\ResourceReference;

/**
 * The die a member chose to roll for a number of sides: one of their copies.
 */
final readonly class DieChoice
{
    public function __construct(
        public ResourceReference $member,
        public int $sides,
        public string $copy,
    ) {
    }

    /**
     * @return array{member: string, sides: int, copy: string}
     */
    public function toArray(): array
    {
        return ['member' => $this->member->toString(), 'sides' => $this->sides, 'copy' => $this->copy];
    }
}
