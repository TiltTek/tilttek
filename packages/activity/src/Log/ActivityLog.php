<?php

declare(strict_types=1);

namespace Tilttek\Activity\Log;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Appended;
use Tilttek\Activity\Entry;
use Tilttek\Activity\Erasure;
use Tilttek\Activity\Exception\IdempotencyConflict;
use Tilttek\Activity\Exception\InvalidActivity;
use Tilttek\Activity\Filter;
use Tilttek\Activity\IdempotencyKey;
use Tilttek\Activity\Totals;

/**
 * Where the journal is kept. Entries are appended, never rewritten, at positions that only grow; an activity and its
 * cancellation are each written once per idempotency key, even when two processes write them at the same moment.
 * Erasing a member is the one exception to "never rewritten".
 */
interface ActivityLog
{
    /**
     * Appends the activity, or returns the entry already recorded under its key.
     *
     * @throws IdempotencyConflict when the key is recorded for another gesture
     */
    public function append(Activity $activity, DateTimeImmutable $at): Appended;

    /**
     * Appends the cancellation of the activity recorded under the key, or returns the cancellation already there;
     * null when no activity is recorded under the key.
     */
    public function cancel(IdempotencyKey $key, DateTimeImmutable $at): ?Appended;

    /**
     * The entry that recorded the activity of this key.
     */
    public function find(IdempotencyKey $key): ?Entry;

    public function isCancelled(IdempotencyKey $key): bool;

    /**
     * The entries after a position whose activity matches the filter, in the order of their positions, at most
     * `limit` of them.
     *
     * @return list<Entry>
     *
     * @throws InvalidActivity when the position is negative or the limit is below 1
     */
    public function read(int $after, int $limit, Filter $filter): array;

    /**
     * The position of the last entry kept; 0 for an empty journal. Positions are never given twice, even when an
     * erasure removed the last entries.
     */
    public function lastPosition(): int;

    /**
     * The activities recorded that match the filter and are not cancelled: how many, and their quantities summed.
     */
    public function totals(Filter $filter): Totals;

    /**
     * Deletes the entries of the member's gestures, and forgets the member as the object of other members' gestures.
     */
    public function eraseMember(ResourceReference $member): Erasure;
}
