<?php

declare(strict_types=1);

namespace Tilttek\Collections\Store;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\Collections\Copy;
use Tilttek\Collections\Source;

/**
 * Where the copies are kept, one per key — the identifier derives from it —, written once even when two processes
 * write the same copy at the same moment.
 */
interface CopyStore
{
    /**
     * Adds a copy; returns false, writing nothing, when a copy with its identifier is there.
     */
    public function add(Copy $copy): bool;

    public function find(string $id): ?Copy;

    /**
     * A member's copies, by the moment they came and then by identifier.
     *
     * @return list<Copy>
     */
    public function ofMember(ResourceReference $member): array;

    /**
     * Gives the copy to another member, marked as having changed hands, if it still belongs to `$from`; returns
     * whether it did.
     */
    public function reassign(string $id, ResourceReference $from, ResourceReference $to): bool;

    /**
     * Removes a copy if it still belongs to the member and never changed hands; returns whether it did.
     */
    public function takeBack(string $id, ResourceReference $member): bool;

    /**
     * Removes the copies of a source that came from `$since` on — all of them when null — and never changed hands;
     * returns how many.
     */
    public function forget(Source $source, ?DateTimeImmutable $since): int;

    /**
     * Removes a member's copies; returns how many.
     */
    public function eraseOwner(ResourceReference $member): int;
}
