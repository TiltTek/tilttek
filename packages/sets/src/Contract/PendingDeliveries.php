<?php

declare(strict_types=1);

namespace Tilttek\Sets\Contract;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * What a completion store adds to be read by a scheduled delivery: the holders that still have a completion not yet
 * delivered, and when a holder whose delivery failed is told again. A store implements it beside
 * {@see CompletionStore}; `Sets::deliverPending()` needs it, the rest of the library does not.
 */
interface PendingDeliveries
{
    /**
     * The holders with at least one completion not yet marked delivered and no attempt postponed after `$now`, each
     * once, at most `$limit` of them: in the order their oldest undelivered completion was added, so that the holder
     * waiting the longest comes first. None when `$limit` is below 1.
     *
     * @return list<ResourceReference>
     */
    public function holdersWithUndelivered(int $limit, DateTimeImmutable $now): array;

    /**
     * How many deliveries of the holder failed in a row since the last one that succeeded; 0 for none.
     */
    public function failedDeliveries(ResourceReference $holder): int;

    /**
     * Keeps that the holder's delivery failed `$failures` times in a row, and that the holder is not listed before
     * `$until`.
     */
    public function postpone(ResourceReference $holder, int $failures, DateTimeImmutable $until): void;

    /**
     * Forgets the holder's failures and postponement, once a delivery succeeded or the holder is erased; nothing when
     * there are none.
     */
    public function forgetFailures(ResourceReference $holder): void;
}
