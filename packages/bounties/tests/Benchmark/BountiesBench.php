<?php

declare(strict_types=1);

namespace Tilttek\Bounties\Tests\Benchmark;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Symfony\Component\Clock\MockClock;
use Tilttek\Bounties\Bounties;
use Tilttek\Bounties\Bounty;
use Tilttek\Bounties\BountyDraft;
use Tilttek\Bounties\BountyReason;
use Tilttek\Bounties\Escrow\InMemoryBountyEscrow;
use Tilttek\Bounties\Prize;
use Tilttek\Bounties\Store\InMemoryBountyStore;

/**
 * What a full bounty costs — 200 claims and 19 awards, near the most it takes —: an award sent again, checked on the
 * bounty read from its record, and the record read and checked as a whole.
 */
final class BountiesBench
{
    private readonly Bounties $bounties;

    /** @var array<array-key, mixed> */
    private readonly array $record;

    private readonly ResourceReference $sponsor;

    private int $award = 0;

    public function __construct()
    {
        $this->sponsor = ResourceReference::of('member', 'sponsor');
        $escrow = new InMemoryBountyEscrow();
        $escrow->fund($this->sponsor, 10_000);

        $this->bounties = new Bounties(new InMemoryBountyStore(), $escrow, clock: new MockClock('2026-11-14 01:00:00 UTC'));
        $this->bounties->offer(new BountyDraft('full', ResourceReference::of('question', '42'), BountyReason::Detail, new Prize(10_000), new DateTimeImmutable('2026-11-21T01:00:00Z')), $this->sponsor);

        for ($n = 0; $n < Bounty::MAX_CLAIMS; ++$n) {
            $this->bounties->claim('full', 'c'.$n, ResourceReference::of('member', 'm'.intdiv($n, Bounty::MAX_CLAIMS_PER_MEMBER)), 'answer:'.$n);
        }

        for ($n = 0; $n < Bounty::MAX_AWARDS - 1; ++$n) {
            $this->bounties->award('full', 'a'.$n, 'c'.$n, 1, $this->sponsor);
        }

        $this->record = $this->bounties->get('full')->toArray();
    }

    public function benchAwardAgainOnAFullBounty(): void
    {
        $award = $this->award++ % (Bounty::MAX_AWARDS - 1);
        $this->bounties->award('full', 'a'.$award, 'c'.$award, 1, $this->sponsor);
    }

    public function benchReadAFullRecord(): void
    {
        Bounty::fromArray($this->record);
    }
}
