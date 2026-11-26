<?php

declare(strict_types=1);

namespace Tilttek\Guilds;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * Points a member brought to a goal, under the idempotency key the application gave. The member is null once erased:
 * the points stay with the guild.
 */
final readonly class Contribution
{
    public function __construct(
        public string $goal,
        public ?ResourceReference $member,
        public int $points,
        public ?string $key,
        public DateTimeImmutable $at,
    ) {
    }
}
