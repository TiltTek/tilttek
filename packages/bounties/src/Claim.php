<?php

declare(strict_types=1);

namespace Tilttek\Bounties;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * A member's claim on a bounty: an opaque reference to what answers it — an answer, a post, an upload —, and when it
 * was made.
 */
final readonly class Claim
{
    public DateTimeImmutable $claimedAt;

    public function __construct(
        public string $id,
        public ResourceReference $claimant,
        public string $evidence,
        DateTimeImmutable $claimedAt,
    ) {
        Check::identifier($id, 'A claim identifier');
        Check::reference($evidence, 'The evidence of a claim');
        $this->claimedAt = Instant::utc($claimedAt);
    }

    /**
     * @return array{id: string, claimant: string, evidence: string, claimed_at: string}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'claimant' => $this->claimant->toString(), 'evidence' => $this->evidence, 'claimed_at' => Instant::write($this->claimedAt)];
    }
}
