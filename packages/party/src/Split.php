<?php

declare(strict_types=1);

namespace Tilttek\Party;

use Phil\Resource\ResourceReference;

/**
 * The share of a currency each member receives: the amount divided evenly, in whole coins, among the members it is
 * shared with; the remainder, fewer coins than there are members, stays in the purse.
 */
final readonly class Split
{
    /**
     * @param list<ResourceReference> $members   the members who receive a share, in the order of the party or the order given
     * @param int                     $share     the coins each receives
     * @param int                     $remainder the coins left in the purse
     */
    public function __construct(public string $currency, public array $members, public int $share, public int $remainder)
    {
    }

    /**
     * The coins given out in all: one share per member.
     */
    public function distributed(): int
    {
        return $this->share * \count($this->members);
    }

    /**
     * The share of a member, 0 when it receives none.
     */
    public function shareOf(ResourceReference $member): int
    {
        foreach ($this->members as $receiver) {
            if ($receiver->equals($member)) {
                return $this->share;
            }
        }

        return 0;
    }

    /**
     * @return array<string, int> each member's share, by reference written `kind:identifier`
     */
    public function shares(): array
    {
        $shares = [];

        foreach ($this->members as $member) {
            $shares[$member->toString()] = $this->share;
        }

        return $shares;
    }
}
