<?php

declare(strict_types=1);

namespace Tilttek\Bounties;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\Bounties\Exception\InvalidBounty;

/**
 * The points held for a bounty: under the key `bounty:` and the bounty's identifier, taken from its sponsor,
 * from the moment it was offered until its grace ends at the latest. Every operation of the escrow takes the whole
 * hold, so that an adapter builds what its own ledger needs from it.
 */
final readonly class BountyHold
{
    public const string KEY_PREFIX = 'bounty:';

    public DateTimeImmutable $heldAt;

    public DateTimeImmutable $until;

    public function __construct(
        public string $key,
        public ResourceReference $payer,
        public int $amount,
        public string $unit,
        DateTimeImmutable $heldAt,
        DateTimeImmutable $until,
    ) {
        if ($amount < 1) {
            throw InvalidBounty::because(\sprintf('A hold is at least one point; %d is not.', $amount));
        }

        if ($until <= $heldAt) {
            throw InvalidBounty::because(\sprintf('The hold "%s" ends before it starts.', $key));
        }

        $this->heldAt = Instant::utc($heldAt);
        $this->until = Instant::utc($until);
    }

    public static function of(Bounty $bounty): self
    {
        return new self(self::KEY_PREFIX.$bounty->id, $bounty->sponsor, $bounty->prize->amount, $bounty->prize->unit, $bounty->openedAt, $bounty->graceEndsAt);
    }

    /**
     * Whether the other hold takes the same points: the same key, payer, amount and unit.
     */
    public function equals(self $other): bool
    {
        return $this->key === $other->key && $this->payer->equals($other->payer) && $this->amount === $other->amount && $this->unit === $other->unit;
    }
}
