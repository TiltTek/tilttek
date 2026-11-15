<?php

declare(strict_types=1);

namespace Tilttek\Bounties;

use DateTimeImmutable;
use Tilttek\Bounties\Exception\InvalidBounty;

/**
 * Points of a bounty its sponsor awards to a claim: paid to its claimant when the bounty is settled.
 */
final readonly class Award
{
    public DateTimeImmutable $awardedAt;

    public function __construct(
        public string $id,
        public string $claim,
        public int $amount,
        DateTimeImmutable $awardedAt,
    ) {
        Check::identifier($id, 'An award identifier');
        Check::identifier($claim, 'A claim identifier');

        if ($amount < 1) {
            throw InvalidBounty::because(\sprintf('An award is at least one point; %d is not.', $amount));
        }

        $this->awardedAt = Instant::utc($awardedAt);
    }

    /**
     * @return array{id: string, claim: string, amount: int, awarded_at: string}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'claim' => $this->claim, 'amount' => $this->amount, 'awarded_at' => Instant::write($this->awardedAt)];
    }
}
