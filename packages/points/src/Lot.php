<?php

declare(strict_types=1);

namespace Tilttek\Points;

use DateTimeImmutable;

/**
 * A credit to a member's account and what is left of it once the debits that followed took their share: the unit
 * the expiry counts, first in first out.
 */
final readonly class Lot
{
    public function __construct(
        public MovementKey $key,
        public DateTimeImmutable $creditedAt,
        public int $remaining,
        public ?DateTimeImmutable $expiresAt,
    ) {
    }

    public function isLiveAt(DateTimeImmutable $moment): bool
    {
        return !$this->expiresAt instanceof DateTimeImmutable || $this->expiresAt > $moment;
    }

    /**
     * When the lot's points expired, if some are left and its lifetime ended at the moment or before; null otherwise.
     */
    public function expiredBy(DateTimeImmutable $moment): ?DateTimeImmutable
    {
        return $this->remaining > 0 && $this->expiresAt instanceof DateTimeImmutable && $this->expiresAt <= $moment ? $this->expiresAt : null;
    }

    public function take(int $amount): self
    {
        return new self($this->key, $this->creditedAt, $this->remaining - $amount, $this->expiresAt);
    }
}
