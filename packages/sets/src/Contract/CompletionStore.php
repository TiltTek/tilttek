<?php

declare(strict_types=1);

namespace Tilttek\Sets\Contract;

use Phil\Resource\ResourceReference;
use Tilttek\Sets\Completion;

/**
 * Where the completions are kept: one per set, tier and holder — {@see Completion::key()} —, written once even when
 * two processes record it at the same moment, each with whether its event was delivered.
 */
interface CompletionStore
{
    /**
     * Adds a completion; returns false, writing nothing, when the store holds one of the same key.
     */
    public function add(Completion $completion): bool;

    /**
     * A holder's completions not yet marked delivered, in every set, in the order they were added. A completion is
     * added undelivered.
     *
     * @return list<Completion>
     */
    public function undelivered(ResourceReference $holder): array;

    /**
     * Marks a completion delivered, its event dispatched without an exception; nothing when the store holds no
     * completion of its key, or holds it delivered.
     */
    public function markDelivered(Completion $completion): void;

    /**
     * A holder's completions in a set, in the order they were added.
     *
     * @return list<Completion>
     */
    public function of(ResourceReference $holder, string $set): array;

    /**
     * Every completion of a holder, in the order they were added.
     *
     * @return list<Completion>
     */
    public function ofHolder(ResourceReference $holder): array;

    /**
     * Removes a holder's completions; returns how many.
     */
    public function eraseHolder(ResourceReference $holder): int;
}
