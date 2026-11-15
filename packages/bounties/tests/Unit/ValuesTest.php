<?php

declare(strict_types=1);

namespace Tilttek\Bounties\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Bounties\Award;
use Tilttek\Bounties\Bounty;
use Tilttek\Bounties\BountyDraft;
use Tilttek\Bounties\BountyHold;
use Tilttek\Bounties\BountyReason;
use Tilttek\Bounties\BountyRules;
use Tilttek\Bounties\Check;
use Tilttek\Bounties\Claim;
use Tilttek\Bounties\Exception\BountyConflict;
use Tilttek\Bounties\Exception\EscrowConflict;
use Tilttek\Bounties\Exception\InvalidBounty;
use Tilttek\Bounties\Instant;
use Tilttek\Bounties\Payout;
use Tilttek\Bounties\Prize;
use Tilttek\Bounties\Tests\Unit\Fixture\World;

#[CoversClass(Prize::class)]
#[CoversClass(Payout::class)]
#[CoversClass(BountyHold::class)]
#[CoversClass(BountyDraft::class)]
#[CoversClass(BountyRules::class)]
#[CoversClass(Claim::class)]
#[CoversClass(Award::class)]
#[CoversClass(Check::class)]
#[CoversClass(Instant::class)]
#[CoversClass(InvalidBounty::class)]
#[CoversClass(BountyConflict::class)]
#[CoversClass(EscrowConflict::class)]
final class ValuesTest extends TestCase
{
    public function testAPrizeIsOneToAMillionPointsOfAUnit(): void
    {
        self::assertSame([1, 'points'], [new Prize(1)->amount, new Prize(1)->unit]);
        self::assertSame(Prize::MAX_AMOUNT, new Prize(Prize::MAX_AMOUNT, 'gems')->amount);
        self::assertSame('a', new Prize(5, 'a')->unit);
        self::assertSame(str_repeat('g', 32), new Prize(5, str_repeat('g', 32))->unit);
        self::assertTrue(new Prize(5, 'gems')->equals(new Prize(5, 'gems')));
        self::assertFalse(new Prize(5, 'gems')->equals(new Prize(6, 'gems')));
        self::assertFalse(new Prize(5, 'gems')->equals(new Prize(5, 'points')));
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function badPrizes(): iterable
    {
        yield 'zero' => [0, 'points'];
        yield 'over a million' => [Prize::MAX_AMOUNT + 1, 'points'];
        yield 'an empty unit' => [5, ''];
        yield 'a unit too long' => [5, str_repeat('g', 33)];
        yield 'a unit of money' => [5, 'EUR'];
        yield 'a unit starting with a digit' => [5, '1gems'];
        yield 'a unit ending in a new line' => [5, "gems\n"];
    }

    #[DataProvider('badPrizes')]
    public function testABadPrizeIsRefused(int $amount, string $unit): void
    {
        $this->expectException(InvalidBounty::class);

        new Prize($amount, $unit);
    }

    public function testAPayoutIsAtLeastOnePoint(): void
    {
        self::assertSame(1, new Payout(World::bea(), 1)->amount);

        $this->expectException(InvalidBounty::class);

        new Payout(World::bea(), 0);
    }

    public function testAHoldIsKeyedByTheBountyAndRunsToTheEndOfItsGrace(): void
    {
        $at = new DateTimeImmutable('2026-11-13 20:00 America/Toronto');
        $bounty = Bounty::offer(new BountyDraft('b1', World::question(), BountyReason::Detail, new Prize(40, 'gems'), $at->modify('+2 days')), World::ana(), $at, new BountyRules(grace: 3600));

        $hold = BountyHold::of($bounty);

        self::assertSame(['bounty:b1', 'member:ana', 40, 'gems'], [$hold->key, $hold->payer->toString(), $hold->amount, $hold->unit]);
        self::assertSame('2026-11-14T01:00:00+00:00', $hold->heldAt->format(\DATE_ATOM));
        self::assertSame('2026-11-16T02:00:00+00:00', $hold->until->format(\DATE_ATOM));
        self::assertTrue($hold->equals(new BountyHold('bounty:b1', World::ana(), 40, 'gems', $at->modify('-1 year'), $at)));
        self::assertFalse($hold->equals(new BountyHold('bounty:b2', World::ana(), 40, 'gems', $at, $at->modify('+1 day'))));
        self::assertFalse($hold->equals(new BountyHold('bounty:b1', World::bea(), 40, 'gems', $at, $at->modify('+1 day'))));
        self::assertFalse($hold->equals(new BountyHold('bounty:b1', World::ana(), 41, 'gems', $at, $at->modify('+1 day'))));
        self::assertFalse($hold->equals(new BountyHold('bounty:b1', World::ana(), 40, 'points', $at, $at->modify('+1 day'))));
        self::assertSame(1, new BountyHold('k', World::ana(), 1, 'points', $at, $at->modify('+1 second'))->amount);
    }

    public function testABadHoldIsRefused(): void
    {
        $at = new DateTimeImmutable('2026-11-14T01:00:00Z');

        try {
            new BountyHold('k', World::ana(), 0, 'points', $at, $at->modify('+1 day'));
            self::fail('A hold is at least one point.');
        } catch (InvalidBounty) {
            $this->expectException(InvalidBounty::class);
        }

        new BountyHold('k', World::ana(), 5, 'points', $at, $at);
    }

    public function testTheRulesHaveTheirBounds(): void
    {
        $rules = new BountyRules();
        self::assertSame([86_400, 30 * 86_400, 86_400, 10, 10_000, 5], [$rules->shortest, $rules->longest, $rules->grace, $rules->smallestPrize, $rules->largestPrize, $rules->openPerSponsor]);
        self::assertSame(1, new BountyRules(openPerSponsor: 1)->openPerSponsor);
        self::assertSame(100, new BountyRules(openPerSponsor: 100)->openPerSponsor);
        self::assertSame(3600, new BountyRules(shortest: 3600, longest: 3600)->longest);
        self::assertSame(90 * 86_400, new BountyRules(longest: 90 * 86_400)->longest);
        self::assertSame(0, new BountyRules(grace: 0)->grace);
        self::assertSame(7 * 86_400, new BountyRules(grace: 7 * 86_400)->grace);
        self::assertSame(1, new BountyRules(smallestPrize: 1, largestPrize: 1)->largestPrize);
        self::assertSame(Prize::MAX_AMOUNT, new BountyRules(largestPrize: Prize::MAX_AMOUNT)->largestPrize);
    }

    /**
     * @return iterable<string, array{0: int, 1: int, 2: int, 3: int, 4: int, 5?: int}>
     */
    public static function badRules(): iterable
    {
        yield 'shorter than an hour' => [3599, 86_400, 0, 1, 10];
        yield 'the longest shorter than the shortest' => [7200, 7199, 0, 1, 10];
        yield 'longer than ninety days' => [3600, 90 * 86_400 + 1, 0, 1, 10];
        yield 'a negative grace' => [3600, 3600, -1, 1, 10];
        yield 'a grace over a week' => [3600, 3600, 7 * 86_400 + 1, 1, 10];
        yield 'no smallest prize' => [3600, 3600, 0, 0, 10];
        yield 'the largest under the smallest' => [3600, 3600, 0, 11, 10];
        yield 'a prize over a million' => [3600, 3600, 0, 1, Prize::MAX_AMOUNT + 1];
        yield 'no open bounty allowed' => [3600, 3600, 0, 1, 10, 0];
        yield 'over a hundred open bounties' => [3600, 3600, 0, 1, 10, 101];
    }

    #[DataProvider('badRules')]
    public function testBadRulesAreRefused(int $shortest, int $longest, int $grace, int $smallest, int $largest, int $open = 5): void
    {
        $this->expectException(InvalidBounty::class);

        new BountyRules($shortest, $longest, $grace, $smallest, $largest, $open);
    }

    public function testADraftKeepsItsEndInUtc(): void
    {
        $draft = new BountyDraft('b-1.x:y_z', World::question(), BountyReason::Sources, new Prize(5), new DateTimeImmutable('2026-11-13 20:00 America/Toronto'));

        self::assertSame('2026-11-14T01:00:00+00:00', $draft->endsAt->format(\DATE_ATOM));
        self::assertSame(BountyReason::Sources, $draft->reason);

        $this->expectException(InvalidBounty::class);
        $this->expectExceptionMessageIsOrContains('A bounty identifier is 1 to 100');

        new BountyDraft('', World::question(), BountyReason::Sources, new Prize(5), new DateTimeImmutable());
    }

    public function testClaimsAndAwardsAreChecked(): void
    {
        $at = new DateTimeImmutable('2026-11-13 20:00 America/Toronto');

        self::assertSame(['id' => 'c1', 'claimant' => 'member:bea', 'evidence' => 'answer:1', 'claimed_at' => '2026-11-14T01:00:00.000000Z'], new Claim('c1', World::bea(), 'answer:1', $at)->toArray());
        self::assertSame(['id' => 'a1', 'claim' => 'c1', 'amount' => 1, 'awarded_at' => '2026-11-14T01:00:00.000000Z'], new Award('a1', 'c1', 1, $at)->toArray());

        foreach ([
            static fn (): Claim => new Claim('c 1', World::bea(), 'answer:1', $at),
            static fn (): Claim => new Claim('c1', World::bea(), 'an answer', $at),
            static fn (): Award => new Award('a 1', 'c1', 1, $at),
            static fn (): Award => new Award('a1', 'c 1', 1, $at),
            static fn (): Award => new Award('a1', 'c1', 0, $at),
        ] as $n => $value) {
            try {
                $value();
                self::fail(\sprintf('Value %d is refused.', $n));
            } catch (InvalidBounty) {
                self::assertGreaterThanOrEqual(0, $n);
            }
        }
    }

    public function testIdentifiersAndReferences(): void
    {
        self::assertSame(str_repeat('a', 100), Check::identifier(str_repeat('a', 100), 'An identifier'));
        self::assertSame(str_repeat('r', 500), Check::reference(str_repeat('r', 500), 'A reference'));

        foreach (['', str_repeat('a', 101), '-a', "a\n"] as $bad) {
            try {
                Check::identifier($bad, 'An identifier');
                self::fail(\sprintf('"%s" is no identifier.', $bad));
            } catch (InvalidBounty) {
                self::assertNotSame('a', $bad);
            }
        }

        foreach (['', str_repeat('r', 501), 'a b', "a\x7F"] as $bad) {
            try {
                Check::reference($bad, 'A reference');
                self::fail(\sprintf('"%s" is no reference.', $bad));
            } catch (InvalidBounty) {
                self::assertNotSame('a', $bad);
            }
        }

        self::assertTrue(Check::member('member:ana')->equals(World::ana()));

        $this->expectException(InvalidBounty::class);

        Check::member('ana');
    }

    public function testTimesAreWrittenAndReadInUtc(): void
    {
        $at = new DateTimeImmutable('2026-11-13 20:15:03.25 America/Toronto');

        self::assertSame('2026-11-14T01:15:03.250000Z', Instant::write($at));
        self::assertSame('UTC', Instant::utc($at)->getTimezone()->getName());
        self::assertSame('2026-11-14T01:15:03.250000Z', Instant::write(Instant::read('2026-11-14T01:15:03.250000Z')));

        $this->expectException(InvalidBounty::class);

        Instant::read('2026-13-14T01:15:03.250000Z');
    }

    public function testConflictsSayWhatHappened(): void
    {
        self::assertSame('A bounty "b1" is stored already.', BountyConflict::exists('b1')->getMessage());
        self::assertSame('An open bounty on question:42 is stored already.', BountyConflict::subjectTaken('question:42')->getMessage());
        self::assertSame('The bounty "b1" is no longer at version 4.', BountyConflict::stale('b1', 4)->getMessage());
        self::assertSame('The bounty "b1" changed under every one of 3 attempts.', BountyConflict::busy('b1', 3)->getMessage());
        self::assertSame('Held twice.', EscrowConflict::because('Held twice.')->getMessage());
    }
}
