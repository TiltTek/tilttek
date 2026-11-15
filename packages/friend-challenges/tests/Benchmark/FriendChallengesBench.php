<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges\Tests\Benchmark;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Symfony\Component\Clock\MockClock;
use Tilttek\FriendChallenges\Challenge;
use Tilttek\FriendChallenges\ChallengeDraft;
use Tilttek\FriendChallenges\Escrow\InMemoryPrizeEscrow;
use Tilttek\FriendChallenges\FriendChallenges;
use Tilttek\FriendChallenges\Prize;
use Tilttek\FriendChallenges\Store\InMemoryChallengeStore;
use Tilttek\FriendChallenges\Store\InMemoryFriendship;
use Tilttek\FriendChallenges\Store\InMemoryModeration;
use Tilttek\FriendChallenges\Visibility;

/**
 * What a full challenge costs — a hundred participants and two hundred proofs, the most it takes —: a vote-free review
 * read from its record and written back, and the record read and checked as a whole.
 */
final class FriendChallengesBench
{
    private readonly FriendChallenges $challenges;

    /** @var array<array-key, mixed> */
    private readonly array $record;

    private readonly ResourceReference $creator;

    private int $proof = 0;

    public function __construct()
    {
        $this->creator = ResourceReference::of('member', 'creator');
        $clock = new MockClock('2026-11-14 01:00:00 UTC');
        $escrow = new InMemoryPrizeEscrow();
        $escrow->fund($this->creator, 100);

        $store = new InMemoryChallengeStore();
        $this->challenges = new FriendChallenges($store, $escrow, new InMemoryFriendship(), new InMemoryModeration(), clock: $clock);
        $this->challenges->create(new ChallengeDraft('full', 'Paint', new Prize(100), new DateTimeImmutable('2026-11-21T01:00:00Z'), visibility: Visibility::Open), $this->creator);

        for ($n = 0; $n < Challenge::MAX_PARTICIPANTS; ++$n) {
            $member = ResourceReference::of('member', 'm'.$n);
            $this->challenges->join('full', $member);
            $this->challenges->submitProof('full', 'a'.$n, $member, 'upload:a'.$n);
            $this->challenges->submitProof('full', 'b'.$n, $member, 'upload:b'.$n);
        }

        $this->record = $this->challenges->get('full')->toArray();
    }

    public function benchRejectAProofOfAFullChallenge(): void
    {
        $this->challenges->reject('full', 'a'.$this->proof++ % Challenge::MAX_PARTICIPANTS, $this->creator);
    }

    public function benchReadAFullRecord(): void
    {
        Challenge::fromArray($this->record);
    }
}
