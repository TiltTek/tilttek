<?php

declare(strict_types=1);

namespace Tilttek\DiceSkins;

use Phil\Resource\ResourceReference;

/**
 * The skin a member chose, for one number of sides or, with `$sides` null, for all their dice.
 */
final readonly class SkinChoice
{
    public function __construct(
        public ResourceReference $member,
        public string $skin,
        public ?int $sides = null,
    ) {
    }

    /**
     * @return array{member: string, skin: string, sides: int|null}
     */
    public function toArray(): array
    {
        return ['member' => $this->member->toString(), 'skin' => $this->skin, 'sides' => $this->sides];
    }
}
