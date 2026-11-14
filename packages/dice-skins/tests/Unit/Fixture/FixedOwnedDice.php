<?php

declare(strict_types=1);

namespace Tilttek\DiceSkins\Tests\Unit\Fixture;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\DiceSkins\Contract\OwnedDice;
use Tilttek\DiceSkins\OwnedDie;

/**
 * Owned dice given by the test: the dice of every member, and the one chosen for each number of sides.
 */
final class FixedOwnedDice implements OwnedDice
{
    /**
     * @param array<string, list<OwnedDie>> $owned  by member
     * @param array<string, OwnedDie>       $chosen by member and number of sides, `member:1/6`
     */
    public function __construct(
        public array $owned = [],
        public array $chosen = [],
    ) {
    }

    #[Override]
    public function ownedBy(ResourceReference $member): array
    {
        return $this->owned[$member->toString()] ?? [];
    }

    #[Override]
    public function chosen(ResourceReference $member, int $sides): ?OwnedDie
    {
        return $this->chosen[$member->toString().'/'.$sides] ?? null;
    }
}
