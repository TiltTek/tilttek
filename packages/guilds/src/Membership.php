<?php

declare(strict_types=1);

namespace Tilttek\Guilds;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * A member of a guild, their role and the moment they entered it.
 */
final readonly class Membership
{
    public function __construct(
        public ResourceReference $guild,
        public ResourceReference $member,
        public Role $role,
        public DateTimeImmutable $since,
    ) {
    }

    /**
     * The same membership under another role, entered at the same moment.
     */
    public function as(Role $role): self
    {
        return new self($this->guild, $this->member, $role, $this->since);
    }
}
