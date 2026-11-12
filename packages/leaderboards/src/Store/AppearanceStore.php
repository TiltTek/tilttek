<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Store;

use Phil\Resource\ResourceReference;
use Tilttek\Leaderboards\Appearance;

/**
 * Where the members' choices of appearance are kept. A member without one is hidden.
 */
interface AppearanceStore
{
    /**
     * Keeps the member's choice; choosing to be hidden forgets the one kept.
     */
    public function choose(ResourceReference $member, Appearance $appearance): void;

    /**
     * The choices of those of the members who appear, by `kind:identifier`; the hidden ones are left out.
     *
     * @param list<ResourceReference> $members
     *
     * @return array<string, Appearance>
     */
    public function of(array $members): array;

    /**
     * Forgets the member's choice.
     *
     * @return int how many were forgotten, 0 or 1
     */
    public function eraseMember(ResourceReference $member): int;
}
