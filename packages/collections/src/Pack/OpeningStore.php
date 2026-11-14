<?php

declare(strict_types=1);

namespace Tilttek\Collections\Pack;

use Phil\Resource\ResourceReference;

/**
 * Where the packs opened are kept, one per key. An opening is first added with no copies, which reserves its key, then
 * completed with the copies given: of two requests that open the same key at the same time, only the one whose
 * addition wrote gives copies.
 */
interface OpeningStore
{
    /**
     * Adds an opening; returns false, writing nothing, when an opening has its key. The check and the write are one
     * atomic step: of two additions of the same key at the same time, one returns false.
     */
    public function add(PackOpening $opening): bool;

    /**
     * Records the copies given for the opening added under the key; does nothing when no opening has it.
     *
     * @param list<string> $copies
     */
    public function complete(string $key, array $copies): void;

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
