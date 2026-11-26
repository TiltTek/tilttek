<?php

declare(strict_types=1);

namespace Tilttek\Kudos\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Appended;
use Tilttek\Activity\Entry;
use Tilttek\Activity\Erasure;
use Tilttek\Activity\Filter;
use Tilttek\Activity\IdempotencyKey;
use Tilttek\Activity\Log\ActivityLog;
use Tilttek\Activity\Log\InMemoryActivityLog;
use Tilttek\Activity\Totals;

/**
 * A log whose entries are erased between the moment they are found and the moment they are cancelled, as another
 * request erasing their member would: it finds every entry, and cancels none.
 */
final readonly class ErasedBeforeCancel implements ActivityLog
{
    public function __construct(
        private ActivityLog $log = new InMemoryActivityLog(),
    ) {
    }

    #[Override]
    public function append(Activity $activity, DateTimeImmutable $at): Appended
    {
        return $this->log->append($activity, $at);
    }

    #[Override]
    public function cancel(IdempotencyKey $key, DateTimeImmutable $at): ?Appended
    {
        return null;
    }

    #[Override]
    public function find(IdempotencyKey $key): ?Entry
    {
        return $this->log->find($key);
    }

    #[Override]
    public function isCancelled(IdempotencyKey $key): bool
    {
        return $this->log->isCancelled($key);
    }

    #[Override]
    public function read(int $after, int $limit, Filter $filter): array
    {
        return $this->log->read($after, $limit, $filter);
    }

    #[Override]
    public function lastPosition(): int
    {
        return $this->log->lastPosition();
    }

    #[Override]
    public function totals(Filter $filter): Totals
    {
        return $this->log->totals($filter);
    }

    #[Override]
    public function eraseMember(ResourceReference $member): Erasure
    {
        return $this->log->eraseMember($member);
    }
}
