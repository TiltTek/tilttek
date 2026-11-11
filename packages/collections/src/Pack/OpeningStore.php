<?php

declare(strict_types=1);

namespace Tilttek\Collections\Pack;

use Phil\Resource\ResourceReference;

/**
 * Where the packs opened are kept, one per key.
 */
interface OpeningStore
{
    /**
     * Adds an opening; returns false, writing nothing, when an opening has its key.
     */
    public function add(PackOpening $opening): bool;

    public function find(string $key): ?PackOpening;

    /**
     * A member's openings, by moment and then by key.
     *
     * @return list<PackOpening>
     */
    public function ofMember(ResourceReference $member): array;

    /**
     * Removes a member's openings; returns how many.
     */
    public function eraseMember(ResourceReference $member): int;
}
