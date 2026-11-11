<?php

declare(strict_types=1);

namespace Tilttek\Collections\Store;

use Phil\Resource\ResourceReference;
use Tilttek\Collections\SetCompletion;

/**
 * Where the sets completed are kept, one per set and member.
 */
interface CompletionStore
{
    /**
     * Adds a completion; returns false, writing nothing, when the member completed the set before.
     */
    public function add(SetCompletion $completion): bool;

    /**
     * A member's completions, by moment and then by set.
     *
     * @return list<SetCompletion>
     */
    public function ofMember(ResourceReference $member): array;

    /**
     * Removes a member's completions; returns how many.
     */
    public function eraseMember(ResourceReference $member): int;
}
