<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges\Tests\Unit;

use Closure;
use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\FriendChallenges\Challenge;
use Tilttek\FriendChallenges\ChallengeDraft;
use Tilttek\FriendChallenges\ChallengeRules;
use Tilttek\FriendChallenges\Check;
use Tilttek\FriendChallenges\Exception\ChallengeConflict;
use Tilttek\FriendChallenges\Exception\EscrowConflict;
use Tilttek\FriendChallenges\Exception\InvalidChallenge;
use Tilttek\FriendChallenges\Instant;
use Tilttek\FriendChallenges\Payout;
use Tilttek\FriendChallenges\Prize;
use Tilttek\FriendChallenges\PrizeHold;
use Tilttek\FriendChallenges\Report;
use Tilttek\FriendChallenges\ReportReason;
use Tilttek\FriendChallenges\Tests\Unit\Fixture\World;
use Tilttek\FriendChallenges\Validation;
use Tilttek\FriendChallenges\Visibility;

#[CoversClass(Prize::class)]
#[CoversClass(Payout::class)]
#[CoversClass(PrizeHold::class)]
#[CoversClass(Report::class)]
#[CoversClass(ChallengeDraft::class)]
#[CoversClass(ChallengeRules::class)]
#[CoversClass(Check::class)]
#[CoversClass(Instant::class)]
#[CoversClass(InvalidChallenge::class)]
#[CoversClass(ChallengeConflict::class)]
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
        yield 'a unit in capitals' => [5, 'Gems'];
        yield 'a unit starting with a digit' => [5, '1gems'];
        yield 'a unit of money' => [5, 'CAD'];
        yield 'a unit ending in a new line' => [5, "gems\n"];
    }

    #[DataProvider('badPrizes')]
    public function testABadPrizeIsRefused(int $amount, string $unit): void
    {
        $this->expectException(InvalidChallenge::class);

        new Prize($amount, $unit);
    }

    public function testAPayoutIsAtLeastOnePoint(): void
    {
        self::assertSame(1, new Payout(World::bea(), 1)->amount);

        $this->expectException(InvalidChallenge::class);

        new Payout(World::bea(), 0);
    }

    public function testAHoldIsKeyedByTheChallengeAndWrittenInUtc(): void
    {
        $at = new DateTimeImmutable('2026-11-13 20:00 America/Toronto');
        $challenge = Challenge::create(new ChallengeDraft('c1', 'Paint', new Prize(40, 'gems'), $at->modify('+1 day'), [World::bea()]), World::ana(), $at, new ChallengeRules());

        $hold = PrizeHold::of($challenge);

        self::assertSame(['friend-challenge:c1', 'member:ana', 40, 'gems'], [$hold->key, $hold->payer->toString(), $hold->amount, $hold->unit]);
        self::assertSame('2026-11-14T01:00:00+00:00', $hold->heldAt->format(\DATE_ATOM));
        self::assertSame('2026-11-18T01:00:00+00:00', $hold->until->format(\DATE_ATOM));
        self::assertTrue($hold->equals(new PrizeHold('friend-challenge:c1', World::ana(), 40, 'gems', $at->modify('-1 year'), $at->modify('+2 years'))));
        self::assertFalse($hold->equals(new PrizeHold('friend-challenge:c2', World::ana(), 40, 'gems', $at, $at->modify('+1 day'))));
        self::assertFalse($hold->equals(new PrizeHold('friend-challenge:c1', World::bea(), 40, 'gems', $at, $at->modify('+1 day'))));
        self::assertFalse($hold->equals(new PrizeHold('friend-challenge:c1', World::ana(), 41, 'gems', $at, $at->modify('+1 day'))));
        self::assertFalse($hold->equals(new PrizeHold('friend-challenge:c1', World::ana(), 40, 'points', $at, $at->modify('+1 day'))));
        self::assertSame(1, new PrizeHold('k', World::ana(), 1, 'points', $at, $at->modify('+1 second'))->amount);
    }

    public function testABadHoldIsRefused(): void
    {
        $at = new DateTimeImmutable('2026-11-14T01:00:00Z');

        foreach ([
            static fn (): PrizeHold => new PrizeHold('k', World::ana(), 0, 'points', $at, $at->modify('+1 day')),
            static fn (): PrizeHold => new PrizeHold('k', World::ana(), 5, 'points', $at, $at),
        ] as $n => $hold) {
            try {
                $hold();
                self::fail(\sprintf('Hold %d is refused.', $n));
            } catch (InvalidChallenge) {
                self::assertGreaterThanOrEqual(0, $n);
            }
        }
    }

    public function testAReportKeepsItsNoteTrimmedAndItsTimeInUtc(): void
    {
        $report = new Report('c1', World::cy(), ReportReason::Unsafe, new DateTimeImmutable('2026-11-13 20:00 America/Toronto'), 'p1', "  Line one\nline two  ");

        self::assertSame("Line one\nline two", $report->note);
        self::assertSame('2026-11-14T01:00:00+00:00', $report->at->format(\DATE_ATOM));
        self::assertSame(500, mb_strlen(new Report('c1', World::cy(), ReportReason::Other, new DateTimeImmutable(), note: str_repeat('é', 500))->note));
    }

    public function testAReportNeedsNoNote(): void
    {
        self::assertSame('', new Report('c1', World::cy(), ReportReason::Spam, new DateTimeImmutable())->note);
        self::assertSame('', new Report('c1', World::cy(), ReportReason::Spam, new DateTimeImmutable(), note: "  \n ")->note);
    }

    /**
     * @return iterable<string, array{Closure(): Report}>
     */
    public static function badReports(): iterable
    {
        $at = new DateTimeImmutable();

        yield 'a bad challenge' => [static fn (): Report => new Report('', World::cy(), ReportReason::Spam, $at)];
        yield 'a bad proof' => [static fn (): Report => new Report('c1', World::cy(), ReportReason::Spam, $at, 'p 1')];
        yield 'a note too long' => [static fn (): Report => new Report('c1', World::cy(), ReportReason::Spam, $at, note: str_repeat('a', 501))];
        yield 'a note with a tab' => [static fn (): Report => new Report('c1', World::cy(), ReportReason::Spam, $at, note: "a\tb")];
    }

    /**
     * @param Closure(): Report $report
     */
    #[DataProvider('badReports')]
    public function testABadReportIsRefused(Closure $report): void
    {
        $this->expectException(InvalidChallenge::class);

        $report();
    }

    public function testTheRulesHaveTheirBounds(): void
    {
        $rules = new ChallengeRules();
        self::assertSame([3600, 30 * 86_400, 3 * 86_400, 10_000], [$rules->shortest, $rules->longest, $rules->review, $rules->largestPrize]);
        self::assertSame(60, new ChallengeRules(shortest: 60, longest: 60)->longest);
        self::assertSame(366 * 86_400, new ChallengeRules(longest: 366 * 86_400)->longest);
        self::assertSame(0, new ChallengeRules(review: 0)->review);
        self::assertSame(30 * 86_400, new ChallengeRules(review: 30 * 86_400)->review);
        self::assertSame(1, new ChallengeRules(largestPrize: 1)->largestPrize);
        self::assertSame(Prize::MAX_AMOUNT, new ChallengeRules(largestPrize: Prize::MAX_AMOUNT)->largestPrize);
    }

    /**
     * @return iterable<string, array{int, int, int, int}>
     */
    public static function badRules(): iterable
    {
        yield 'shorter than a minute' => [59, 3600, 0, 10];
        yield 'the longest shorter than the shortest' => [3600, 3599, 0, 10];
        yield 'longer than a year' => [3600, 366 * 86_400 + 1, 0, 10];
        yield 'a negative review' => [3600, 3600, -1, 10];
        yield 'a review over thirty days' => [3600, 3600, 30 * 86_400 + 1, 10];
        yield 'no prize' => [3600, 3600, 0, 0];
        yield 'a prize over a million' => [3600, 3600, 0, Prize::MAX_AMOUNT + 1];
    }

    #[DataProvider('badRules')]
    public function testBadRulesAreRefused(int $shortest, int $longest, int $review, int $largest): void
    {
        $this->expectException(InvalidChallenge::class);

        new ChallengeRules($shortest, $longest, $review, $largest);
    }

    public function testADraftIsBetweenFriendsAndCheckedField(): void
    {
        $at = new DateTimeImmutable('2026-11-13 20:00 America/Toronto');
        $draft = new ChallengeDraft('c1', '  Paint  ', new Prize(5), $at, [World::bea(), World::bea(), World::cy()], description: "  Ten.\nNow.  ");

        self::assertSame('Paint', $draft->title);
        self::assertSame("Ten.\nNow.", $draft->description);
        self::assertSame(['member:bea', 'member:cy'], array_map(static fn (ResourceReference $member): string => $member->toString(), $draft->invitees));
        self::assertSame([Visibility::Friends, Validation::Creator, 1], [$draft->visibility, $draft->validation, $draft->votesNeeded]);
        self::assertSame('2026-11-14T01:00:00+00:00', $draft->endsAt->format(\DATE_ATOM));
        self::assertSame(ChallengeDraft::MAX_VOTES, new ChallengeDraft('c1', 'P', new Prize(5), $at, [World::bea()], validation: Validation::Vote, votesNeeded: ChallengeDraft::MAX_VOTES)->votesNeeded);
        self::assertCount(Challenge::MAX_INVITEES, new ChallengeDraft('c1', 'P', new Prize(5), $at, self::members(Challenge::MAX_INVITEES))->invitees);
        self::assertSame(str_repeat('é', 120), new ChallengeDraft('c1', str_repeat('é', 120), new Prize(5), $at, [World::bea()])->title);
        self::assertSame(2000, mb_strlen(new ChallengeDraft('c1', 'P', new Prize(5), $at, [World::bea()], description: str_repeat('é', 2000))->description));
    }

    /**
     * @return iterable<string, array{Closure(): ChallengeDraft, string}>
     */
    public static function badDrafts(): iterable
    {
        $at = new DateTimeImmutable();
        $prize = new Prize(5);

        yield 'an empty identifier' => [static fn (): ChallengeDraft => new ChallengeDraft('', 'P', $prize, $at, [World::bea()]), 'A challenge identifier is 1 to 100'];
        yield 'an identifier with a space' => [static fn (): ChallengeDraft => new ChallengeDraft('c 1', 'P', $prize, $at, [World::bea()]), 'A challenge identifier'];
        yield 'an identifier starting with a dash' => [static fn (): ChallengeDraft => new ChallengeDraft('-c1', 'P', $prize, $at, [World::bea()]), 'A challenge identifier'];
        yield 'an identifier too long' => [static fn (): ChallengeDraft => new ChallengeDraft(str_repeat('c', 101), 'P', $prize, $at, [World::bea()]), 'A challenge identifier'];
        yield 'an identifier ending in a new line' => [static fn (): ChallengeDraft => new ChallengeDraft("c1\n", 'P', $prize, $at, [World::bea()]), 'A challenge identifier'];
        yield 'no title' => [static fn (): ChallengeDraft => new ChallengeDraft('c1', '   ', $prize, $at, [World::bea()]), 'is required'];
        yield 'a title too long' => [static fn (): ChallengeDraft => new ChallengeDraft('c1', str_repeat('a', 121), $prize, $at, [World::bea()]), '120 characters at most'];
        yield 'a title on two lines' => [static fn (): ChallengeDraft => new ChallengeDraft('c1', "a\nb", $prize, $at, [World::bea()]), 'control character'];
        yield 'a title not in UTF-8' => [static fn (): ChallengeDraft => new ChallengeDraft('c1', "\xC3", $prize, $at, [World::bea()]), 'is not UTF-8'];
        yield 'terms too long' => [static fn (): ChallengeDraft => new ChallengeDraft('c1', 'P', $prize, $at, [World::bea()], description: str_repeat('a', 2001)), '2000 characters at most'];
        yield 'terms with a bell' => [static fn (): ChallengeDraft => new ChallengeDraft('c1', 'P', $prize, $at, [World::bea()], description: "a\x07"), 'control character'];
        yield 'terms with a delete' => [static fn (): ChallengeDraft => new ChallengeDraft('c1', 'P', $prize, $at, [World::bea()], description: "a\x7Fb"), 'control character'];
        yield 'no vote needed' => [static fn (): ChallengeDraft => new ChallengeDraft('c1', 'P', $prize, $at, [World::bea()], validation: Validation::Vote, votesNeeded: 0), 'A vote needs 1 to 25'];
        yield 'too many votes needed' => [static fn (): ChallengeDraft => new ChallengeDraft('c1', 'P', $prize, $at, [World::bea()], validation: Validation::Vote, votesNeeded: 26), 'A vote needs 1 to 25'];
        yield 'votes without a vote' => [static fn (): ChallengeDraft => new ChallengeDraft('c1', 'P', $prize, $at, [World::bea()], votesNeeded: 2), 'only when the proofs are put to a vote'];
        yield 'an open challenge inviting' => [static fn (): ChallengeDraft => new ChallengeDraft('c1', 'P', $prize, $at, [World::bea()], Visibility::Open), 'invites nobody in particular'];
        yield 'friends without a friend' => [static fn (): ChallengeDraft => new ChallengeDraft('c1', 'P', $prize, $at), 'at least one friend'];
        yield 'too many friends' => [static fn (): ChallengeDraft => new ChallengeDraft('c1', 'P', $prize, $at, self::members(Challenge::MAX_INVITEES + 1)), '50 friends at most'];
    }

    /**
     * @param Closure(): ChallengeDraft $draft
     */
    #[DataProvider('badDrafts')]
    public function testABadDraftIsRefused(Closure $draft, string $message): void
    {
        $this->expectException(InvalidChallenge::class);
        $this->expectExceptionMessageIsOrContains($message);

        $draft();
    }

    public function testIdentifiersAndReferences(): void
    {
        self::assertSame('01JCX9.a_b:c-d', Check::identifier('01JCX9.a_b:c-d', 'An identifier'));
        self::assertSame(str_repeat('a', 100), Check::identifier(str_repeat('a', 100), 'An identifier'));
        self::assertSame('https://example.org/p?q=1', Check::reference('https://example.org/p?q=1', 'A reference'));
        self::assertSame(str_repeat('r', 500), Check::reference(str_repeat('r', 500), 'A reference'));

        foreach (['', str_repeat('r', 501), 'a b', "a\x00", "a\x7F", "a\n"] as $bad) {
            try {
                Check::reference($bad, 'A reference');
                self::fail(\sprintf('"%s" is no reference.', $bad));
            } catch (InvalidChallenge $invalidChallenge) {
                self::assertSame('A reference is 1 to 500 bytes without whitespace or control characters.', $invalidChallenge->getMessage());
            }
        }

        self::assertTrue(Check::member('member:ana')->equals(World::ana()));

        $this->expectException(InvalidChallenge::class);

        Check::member('Member:ana');
    }

    public function testTimesAreWrittenAndReadInUtc(): void
    {
        $at = new DateTimeImmutable('2026-11-13 20:15:03.25 America/Toronto');

        self::assertSame('2026-11-14T01:15:03.250000Z', Instant::write($at));
        self::assertSame('UTC', Instant::utc($at)->getTimezone()->getName());
        self::assertSame('2026-11-14T01:15:03.250000Z', Instant::write(Instant::read('2026-11-14T01:15:03.250000Z')));
        self::assertSame('UTC', Instant::read('2026-11-14T01:15:03.250000Z')->getTimezone()->getName());

        $this->expectException(InvalidChallenge::class);

        Instant::read('2026-11-14T01:15:03Z');
    }

    public function testConflictsSayWhatHappened(): void
    {
        self::assertSame('A challenge "c1" is stored already.', ChallengeConflict::exists('c1')->getMessage());
        self::assertSame('The challenge "c1" is no longer at version 4.', ChallengeConflict::stale('c1', 4)->getMessage());
        self::assertSame('Held twice.', EscrowConflict::because('Held twice.')->getMessage());
    }

    /**
     * @return list<ResourceReference>
     */
    private static function members(int $count): array
    {
        return array_map(static fn (int $n): ResourceReference => ResourceReference::of('member', 'm'.$n), range(1, $count));
    }
}
