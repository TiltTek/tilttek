<?php

declare(strict_types=1);

namespace Tilttek\Badges;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * What one activity brings to one criterion of a badge: the badge, the criterion's path, the member, the activity's
 * idempotency key, its quantity and its moment. A criterion's progress is read from its contributions, never from a
 * counter written in place: a cancellation removes the activity's contributions, and a rebuild forgets those of a
 * period before the journal hands them again.
 */
final readonly class Contribution
{
    public function __construct(
        public string $badge,
        public string $path,
        public ResourceReference $member,
        public string $activity,
        public int $quantity,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
