<?php

declare(strict_types=1);

namespace Tilttek\Sets\Contract;

use Phil\Resource\ResourceReference;

/**
 * What a completion store adds to be read by a scheduled delivery: the holders that still have a completion not yet
 * delivered. A store implements it beside {@see CompletionStore}; `Sets::deliverPending()` needs it, the rest of the
 * library does not.
 */
interface PendingDeliveries
{
    /**
     * The holders with at least one completion not yet marked delivered, each once, at most `$limit` of them: in the
     * order their oldest undelivered completion was added, so that the holder waiting the longest comes first. None
     * when `$limit` is below 1.
     *
     * @return list<ResourceReference>
     */
    public function holdersWithUndelivered(int $limit): array;
}
