<?php

declare(strict_types=1);

namespace Tilttek\GameNight\Tests\Unit;

use Closure;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\GameNight\Exception\InvalidNight;
use Tilttek\GameNight\Exception\NightRefused;
use Tilttek\GameNight\GameNight;
use Tilttek\GameNight\NightStatus;
use Tilttek\GameNight\Registration;
use Tilttek\GameNight\RegistrationStatus;
use Tilttek\GameNight\Table;
use Tilttek\GameNight\Vote\VoteOutcome;

#[CoversClass(GameNight::class)]
#[CoversClass(Registration::class)]
#[CoversClass(NightRefused::class)]
#[CoversClass(InvalidNight::class)]
final class GameNightTest extends TestCase
{
    /**
     * @param list<Table> $tables
     */
    private function night(array $tables = [new Table('t-1', 4)], NightStatus $status = NightStatus::Published): GameNight
    {
        return new GameNight(
            'n-1',
            new DateTimeImmutable('2027-03-05 19:00 UTC'),
            new DateTimeImmutable('2027-03-05 23:00 UTC'),
            'venue-1',
            'host-1',
            $tables,
            $status,
        );
    }

    public function testANightCountsItsMinutesSeatsAndPlaces(): void
    {
        $night = $this->night([new Table('t-1', 4), new Table('t-2', 6)])
            ->registered('m-1', true)
            ->registered('m-2');

        self::assertSame(240, $night->minutes());
        self::assertSame(10, $night->capacity());
        self::assertSame(3, $night->confirmedPlaces());
        self::assertSame(7, $night->freeSeats());
        self::assertFalse($night->isFull());
        self::assertSame('t-2', $night->table('t-2')?->id);
        self::assertNull($night->table('t-3'));
        self::assertSame(NightStatus::Draft, new GameNight('n', new DateTimeImmutable('2027-01-01 10:00'), new DateTimeImmutable('2027-01-01 11:00'), 'v', 'h')->status);
    }

    public function testTheMinutesAreWholeMinutes(): void
    {
        $night = new GameNight('n', new DateTimeImmutable('2027-01-01 10:00:00'), new DateTimeImmutable('2027-01-01 10:01:59'), 'v', 'h');

        self::assertSame(1, $night->minutes());
    }

    public function testANightLastsAtMostADay(): void
    {
        $day = new GameNight('n', new DateTimeImmutable('2027-01-01 10:00'), new DateTimeImmutable('2027-01-02 10:00'), 'v', 'h');
        self::assertSame(GameNight::MAX_MINUTES, $day->minutes());

        $this->expectException(InvalidNight::class);
        $this->expectExceptionMessageIs('A night lasts at most 1440 minutes.');

        new GameNight('n', new DateTimeImmutable('2027-01-01 10:00'), new DateTimeImmutable('2027-01-02 10:01'), 'v', 'h');
    }

    public function testANightEndsAfterItStarts(): void
    {
        $this->expectException(InvalidNight::class);
        $this->expectExceptionMessageIs('A night ends after it starts.');

        new GameNight('n', new DateTimeImmutable('2027-01-01 10:00'), new DateTimeImmutable('2027-01-01 10:00'), 'v', 'h');
    }

    /**
     * @return iterable<string, array{Closure(): GameNight, string}>
     */
    public static function invalidNights(): iterable
    {
        $at = new DateTimeImmutable('2027-01-01 10:00');
        $end = new DateTimeImmutable('2027-01-01 12:00');

        yield 'night' => [static fn (): GameNight => new GameNight('1', $at, $end, 'v', 'h'), 'The identifier of a night'];
        yield 'venue' => [static fn (): GameNight => new GameNight('n', $at, $end, 'a b', 'h'), 'The identifier of a venue'];
        yield 'host' => [static fn (): GameNight => new GameNight('n', $at, $end, 'v', 'host@example.org'), 'The identifier of a member'];
        yield 'too many tables' => [static fn (): GameNight => new GameNight('n', $at, $end, 'v', 'h', array_map(static fn (int $i): Table => new Table('t'.$i, 1), range(0, GameNight::MAX_TABLES))), 'A night has at most 50 tables.'];
        yield 'table twice' => [static fn (): GameNight => new GameNight('n', $at, $end, 'v', 'h', [new Table('t', 1), new Table('t', 2)]), 'A night has each table once.'];
        yield 'member twice' => [static fn (): GameNight => new GameNight('n', $at, $end, 'v', 'h', [new Table('t', 4)], NightStatus::Published, [new Registration('m'), new Registration('m')]), 'A night has each member signed up once.'];
        yield 'places over seats' => [static fn (): GameNight => new GameNight('n', $at, $end, 'v', 'h', [new Table('t', 1)], NightStatus::Published, [new Registration('m', true)]), 'The confirmed places of a night fit in its seats.'];
        yield 'waiting seated' => [static fn (): GameNight => new GameNight('n', $at, $end, 'v', 'h', [new Table('t', 4)], NightStatus::Published, [new Registration('m', false, RegistrationStatus::Waitlisted, 't')]), 'A member seated at a table holds their places at a table of the night.'];
        yield 'seated at no table' => [static fn (): GameNight => new GameNight('n', $at, $end, 'v', 'h', [new Table('t', 4)], NightStatus::Published, [new Registration('m', false, RegistrationStatus::Confirmed, 'u')]), 'A member seated at a table holds their places at a table of the night.'];
        yield 'table overfull' => [static fn (): GameNight => new GameNight('n', $at, $end, 'v', 'h', [new Table('t', 2), new Table('u', 4)], NightStatus::Published, [new Registration('m', true, RegistrationStatus::Confirmed, 't'), new Registration('o', false, RegistrationStatus::Confirmed, 't')]), 'The places taken at the table t fit in its seats.'];
        yield 'too many candidates' => [static fn (): GameNight => new GameNight('n', $at, $end, 'v', 'h', candidates: array_map(static fn (int $i): string => 'g'.$i, range(0, GameNight::MAX_CANDIDATES))), 'A night proposes at most 30 games to the vote.'];
        yield 'candidate twice' => [static fn (): GameNight => new GameNight('n', $at, $end, 'v', 'h', candidates: ['g', 'g']), 'A night proposes each game once.'];
        yield 'candidate shape' => [static fn (): GameNight => new GameNight('n', $at, $end, 'v', 'h', candidates: ['g g']), 'The identifier of a game'];
        yield 'vote not proposed' => [static fn (): GameNight => new GameNight('n', $at, $end, 'v', 'h', [new Table('t', 4)], NightStatus::Published, [new Registration('m')], ['g'], ['m' => 'h']), 'A vote is a member signed up for the night voting for a game proposed.'];
        yield 'vote not signed up' => [static fn (): GameNight => new GameNight('n', $at, $end, 'v', 'h', [new Table('t', 4)], NightStatus::Published, [new Registration('m')], ['g'], ['o' => 'g']), 'A vote is a member signed up for the night voting for a game proposed.'];
    }

    /**
     * @param Closure(): GameNight $build
     */
    #[DataProvider('invalidNights')]
    public function testAnInvalidNightIsRefused(Closure $build, string $message): void
    {
        $this->expectException(InvalidNight::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }

    public function testTheWaitingListHoldsAtMostItsBound(): void
    {
        $at = new DateTimeImmutable('2027-01-01 10:00');
        $end = new DateTimeImmutable('2027-01-01 12:00');
        $waiting = static fn (int $count): array => array_map(static fn (int $i): Registration => new Registration('m'.$i, false, RegistrationStatus::Waitlisted), range(1, $count));

        self::assertCount(GameNight::MAX_WAITLIST, new GameNight('n', $at, $end, 'v', 'h', registrations: $waiting(GameNight::MAX_WAITLIST))->waitlist());

        $this->expectException(InvalidNight::class);
        $this->expectExceptionMessageIs('The waiting list of a night holds at most 200 members.');

        new GameNight('n', $at, $end, 'v', 'h', registrations: $waiting(GameNight::MAX_WAITLIST + 1));
    }

    public function testTheBoundsHoldAtTheirLimits(): void
    {
        $at = new DateTimeImmutable('2027-01-01 10:00');
        $end = new DateTimeImmutable('2027-01-01 12:00');
        $tables = array_map(static fn (int $i): Table => new Table('t'.$i, 1), range(1, GameNight::MAX_TABLES));
        $candidates = array_map(static fn (int $i): string => 'g'.$i, range(1, GameNight::MAX_CANDIDATES));
        $night = new GameNight('n', $at, $end, 'v', 'h', $tables, NightStatus::Published, [new Registration('m', false, RegistrationStatus::Confirmed, 't1')], $candidates);

        self::assertSame(GameNight::MAX_TABLES, $night->capacity());
        self::assertSame(GameNight::MAX_TABLES * Table::MAX_SEATS, GameNight::MAX_PLACES);
        self::assertCount(GameNight::MAX_CANDIDATES, $night->candidates);
    }

    public function testATableSeatedFullIsKept(): void
    {
        $night = $this->night([new Table('t-1', 3)])->registered('m-1', true)->registered('m-2')
            ->seated('m-1', 't-1')->seated('m-2', 't-1');

        self::assertSame(3, $night->occupancy('t-1'));
        self::assertTrue($night->isFull());
    }

    public function testASignUpWithAGuestTakesTwoPlaces(): void
    {
        $night = $this->night()->registered('m-1', true);
        $registration = $night->registration('m-1');

        self::assertInstanceOf(Registration::class, $registration);
        self::assertTrue($registration->guest);
        self::assertSame(2, $registration->places());
        self::assertSame(RegistrationStatus::Confirmed, $registration->status);
        self::assertNull($night->registration('m-2'));
    }

    public function testASignUpWaitsWhenItsPlacesAreNotFree(): void
    {
        $night = $this->night([new Table('t-1', 3)])->registered('m-1', true)->registered('m-2', true)->registered('m-3');

        self::assertSame(['m-1'], array_map(static fn (Registration $r): string => $r->member, $night->confirmed()));
        self::assertSame(['m-2', 'm-3'], array_map(static fn (Registration $r): string => $r->member, $night->waitlist()));
        self::assertSame(1, $night->freeSeats());
    }

    public function testANewcomerWaitsBehindTheMembersWaiting(): void
    {
        $night = $this->night([new Table('t-1', 3)])->registered('m-1', true)->registered('m-2', true)->registered('m-3');

        self::assertSame(RegistrationStatus::Waitlisted, $night->registration('m-3')?->status);
    }

    public function testTheLastSeatIsTaken(): void
    {
        $night = $this->night([new Table('t-1', 2)])->registered('m-1')->registered('m-2');

        self::assertTrue($night->isFull());
        self::assertSame(0, $night->freeSeats());
        self::assertSame([], $night->waitlist());
    }

    public function testAMemberSignsUpOnce(): void
    {
        $this->expectException(NightRefused::class);
        $this->expectExceptionMessageIs('The member m-1 is signed up for the night.');

        $this->night()->registered('m-1')->registered('m-1', true);
    }

    public function testTheWaitingListIsBounded(): void
    {
        $night = $this->night([new Table('t-1', 1)])->registered('m-0');

        for ($i = 1; $i < GameNight::MAX_WAITLIST; ++$i) {
            $night = $night->registered('m-'.$i);
        }

        $night = $night->registered('m-last');
        self::assertCount(GameNight::MAX_WAITLIST, $night->waitlist());

        $this->expectException(NightRefused::class);
        $this->expectExceptionMessageIs('The night is full, and its waiting list holds 200 members.');

        $night->registered('m-over');
    }

    public function testADraftTakesNoSignUp(): void
    {
        $this->expectException(NightRefused::class);
        $this->expectExceptionMessageIs('The night is not open to sign-ups.');

        $this->night(status: NightStatus::Draft)->registered('m-1');
    }

    public function testAWithdrawalFreesPlacesForTheFirstWaiting(): void
    {
        $night = $this->night([new Table('t-1', 2)])->registered('m-1')->registered('m-2')->registered('m-3')->registered('m-4');
        $after = $night->withdrawn('m-1');

        self::assertNull($after->registration('m-1'));
        self::assertSame([0, 1, 2], array_keys($after->registrations));
        self::assertSame(RegistrationStatus::Confirmed, $after->registration('m-3')?->status);
        self::assertSame(RegistrationStatus::Waitlisted, $after->registration('m-4')?->status);
        self::assertSame(['m-3'], $after->promotedSince($night));
        self::assertSame([], $after->promotedSince($after));
    }

    public function testTheFirstWaitingWhoseGuestDoesNotFitHoldsTheOthersBack(): void
    {
        $night = $this->night([new Table('t-1', 2)])->registered('m-1')->registered('m-2')->registered('m-3', true)->registered('m-4');
        $after = $night->withdrawn('m-1');

        self::assertSame(RegistrationStatus::Waitlisted, $after->registration('m-3')?->status);
        self::assertSame(RegistrationStatus::Waitlisted, $after->registration('m-4')?->status);
        self::assertSame(1, $after->freeSeats());

        $served = $after->withdrawn('m-2');
        self::assertSame(['m-3'], $served->promotedSince($after));
        self::assertSame(RegistrationStatus::Waitlisted, $served->registration('m-4')?->status);
    }

    public function testSeveralWaitingAreServedWhenPlacesFit(): void
    {
        $night = $this->night([new Table('t-1', 2)])->registered('m-1', true)->registered('m-2')->registered('m-3')->registered('m-4');
        $after = $night->withdrawn('m-1');

        self::assertSame(['m-2', 'm-3'], $after->promotedSince($night));
        self::assertSame(['m-4'], array_map(static fn (Registration $r): string => $r->member, $after->waitlist()));
    }

    public function testAWithdrawalDropsTheVote(): void
    {
        $night = $this->night()->withCandidates(['g-1'])->registered('m-1')->voted('m-1', 'g-1')->withdrawn('m-1');

        self::assertSame([], $night->ballots);
    }

    public function testAWaitingMemberWithdraws(): void
    {
        $night = $this->night([new Table('t-1', 1)])->registered('m-1')->registered('m-2')->withdrawn('m-2');

        self::assertSame([], $night->waitlist());
        self::assertSame(['m-1'], array_map(static fn (Registration $r): string => $r->member, $night->confirmed()));
    }

    public function testAMemberNotSignedUpDoesNotWithdraw(): void
    {
        $this->expectException(NightRefused::class);
        $this->expectExceptionMessageIs('The member m-9 is not signed up for the night.');

        $this->night()->withdrawn('m-9');
    }

    public function testATableAddedServesTheWaitingList(): void
    {
        $night = $this->night([new Table('t-1', 1)])->registered('m-1')->registered('m-2', true);
        $after = $night->withTable(new Table('t-2', 2, 'g-1', 'm-1'));

        self::assertSame(3, $after->capacity());
        self::assertSame(['m-2'], $after->promotedSince($night));
        self::assertSame('g-1', $after->table('t-2')?->game);
    }

    public function testATableIsReplacedInPlace(): void
    {
        $night = $this->night([new Table('t-1', 4), new Table('t-2', 2)])->withTable(new Table('t-1', 6));

        self::assertSame(['t-1', 't-2'], array_map(static fn (Table $t): string => $t->id, $night->tables));
        self::assertSame(8, $night->capacity());
    }

    public function testATableKeepsTheSeatsTakenAtIt(): void
    {
        $night = $this->night([new Table('t-1', 4)])->registered('m-1', true)->registered('m-2')->seated('m-1', 't-1')->seated('m-2', 't-1');

        self::assertSame(3, $night->withTable(new Table('t-1', 3))->capacity());

        $this->expectException(NightRefused::class);
        $this->expectExceptionMessageIs('The table t-1 seats more places than 2.');

        $night->withTable(new Table('t-1', 2));
    }

    public function testATableIsRemovedAndItsMembersSeatedNowhere(): void
    {
        $night = $this->night([new Table('t-1', 4), new Table('t-2', 4)])->registered('m-1')->registered('m-2')->seated('m-1', 't-2')->seated('m-2', 't-1');
        $after = $night->withoutTable('t-2');

        self::assertSame(['t-1'], array_map(static fn (Table $t): string => $t->id, $after->tables));
        self::assertNull($after->registration('m-1')?->table);
        self::assertSame('t-1', $after->registration('m-2')?->table);
        self::assertSame([0], array_keys($night->withoutTable('t-1')->tables));
    }

    public function testTheSignUpsAreReadInTheirOrder(): void
    {
        $night = new GameNight(
            'n',
            new DateTimeImmutable('2027-01-01 10:00'),
            new DateTimeImmutable('2027-01-01 12:00'),
            'v',
            'h',
            [new Table('t', 1)],
            NightStatus::Published,
            [new Registration('m-1', false, RegistrationStatus::Waitlisted), new Registration('m-2')],
        );

        self::assertSame(['m-2'], array_map(static fn (Registration $r): string => $r->member, $night->confirmed()));
        self::assertSame(['m-1'], array_map(static fn (Registration $r): string => $r->member, $night->waitlist()));
    }

    public function testATableIsRemovedWhenTheOthersSeatTheConfirmedPlaces(): void
    {
        $night = $this->night([new Table('t-1', 2), new Table('t-2', 2)])->registered('m-1')->registered('m-2');

        self::assertSame(2, $night->withoutTable('t-2')->capacity());

        $this->expectException(NightRefused::class);
        $this->expectExceptionMessageIs('Without the table t-2, the night seats fewer places than are confirmed.');

        $night->registered('m-3')->withoutTable('t-2');
    }

    public function testAnUnknownTableIsNotRemoved(): void
    {
        $this->expectException(NightRefused::class);
        $this->expectExceptionMessageIs('The table t-9 is not one of the night.');

        $this->night()->withoutTable('t-9');
    }

    public function testADraftIsPublishedOnce(): void
    {
        $night = $this->night(status: NightStatus::Draft)->published();

        self::assertSame(NightStatus::Published, $night->status);

        $this->expectException(NightRefused::class);
        $this->expectExceptionMessageIs('Only a draft night is published.');

        $night->published();
    }

    public function testANightWithoutSeatsIsNotPublished(): void
    {
        $this->expectException(NightRefused::class);
        $this->expectExceptionMessageIs('A night is published with at least one table.');

        $this->night([], NightStatus::Draft)->published();
    }

    public function testACancelledNightNoLongerChanges(): void
    {
        $night = $this->night()->cancelled();

        self::assertSame(NightStatus::Cancelled, $night->status);

        foreach ([
            $night->cancelled(...),
            static fn (): GameNight => $night->withTable(new Table('t-2', 2)),
            static fn (): GameNight => $night->withoutTable('t-1'),
            static fn (): GameNight => $night->withCandidates([]),
            $night->published(...),
        ] as $change) {
            try {
                $change();
                self::fail('A cancelled night changed.');
            } catch (NightRefused $refused) {
                self::assertContains($refused->getMessage(), ['The night is cancelled.', 'Only a draft night is published.']);
            }
        }
    }

    public function testAConfirmedMemberIsSeatedAndMoved(): void
    {
        $night = $this->night([new Table('t-1', 2), new Table('t-2', 2)])->registered('m-1', true);
        $seated = $night->seated('m-1', 't-1');

        self::assertSame('t-1', $seated->registration('m-1')?->table);
        self::assertSame(2, $seated->occupancy('t-1'));
        self::assertSame('t-1', $seated->seated('m-1', 't-1')->registration('m-1')?->table);
        self::assertSame(2, $seated->seated('m-1', 't-2')->occupancy('t-2'));
        self::assertSame(0, $seated->seated('m-1', 't-2')->occupancy('t-1'));
        self::assertNull($seated->seated('m-1', null)->registration('m-1')?->table);
    }

    public function testAMemberIsNotSeatedAtAFullTable(): void
    {
        $night = $this->night([new Table('t-1', 2), new Table('t-2', 2)])->registered('m-1')->registered('m-2', true)->seated('m-1', 't-1');

        $this->expectException(NightRefused::class);
        $this->expectExceptionMessageIs('The table t-1 has not the free seats.');

        $night->seated('m-2', 't-1');
    }

    public function testAWaitingMemberIsNotSeated(): void
    {
        $night = $this->night([new Table('t-1', 1)])->registered('m-1')->registered('m-2');

        $this->expectException(NightRefused::class);
        $this->expectExceptionMessageIs('The member m-2 waits for places, and is seated once they hold them.');

        $night->seated('m-2', 't-1');
    }

    public function testAMemberIsNotSeatedAtAnUnknownTable(): void
    {
        $this->expectException(NightRefused::class);
        $this->expectExceptionMessageIs('The table t-9 is not one of the night.');

        $this->night()->registered('m-1')->seated('m-1', 't-9');
    }

    public function testAMemberVotesOnceForAGameProposed(): void
    {
        $night = $this->night()->withCandidates(['g-1', 'g-2'])->registered('m-1')->registered('m-2')
            ->voted('m-1', 'g-1')->voted('m-2', 'g-1')->voted('m-1', 'g-2');

        self::assertSame(['m-1' => 'g-2', 'm-2' => 'g-1'], $night->ballots);
        self::assertSame(VoteOutcome::Tie, $night->voteResult()->outcome);
        self::assertSame(['m-2' => 'g-1'], $night->unvoted('m-1')->ballots);
        self::assertSame(['m-2' => 'g-1'], $night->unvoted('m-1')->unvoted('m-1')->ballots);
    }

    public function testAVoteIsForAGameProposed(): void
    {
        $this->expectException(NightRefused::class);
        $this->expectExceptionMessageIs('The game g-3 is not proposed to the vote.');

        $this->night()->withCandidates(['g-1'])->registered('m-1')->voted('m-1', 'g-3');
    }

    public function testAVoteIsAMemberSignedUp(): void
    {
        $this->expectException(NightRefused::class);
        $this->expectExceptionMessageIs('The member m-1 is not signed up for the night.');

        $this->night()->withCandidates(['g-1'])->voted('m-1', 'g-1');
    }

    public function testVotesAreClosedOnADraft(): void
    {
        $this->expectException(NightRefused::class);

        $this->night(status: NightStatus::Draft)->unvoted('m-1');
    }

    public function testNewCandidatesDropTheVotesForGamesWithdrawn(): void
    {
        $night = $this->night()->withCandidates(['g-1', 'g-2'])->registered('m-1')->registered('m-2')
            ->voted('m-1', 'g-1')->voted('m-2', 'g-2')->withCandidates(['g-2', 'g-3']);

        self::assertSame(['g-2', 'g-3'], $night->candidates);
        self::assertSame(['m-2' => 'g-2'], $night->ballots);
    }

    public function testTheVersionIsKept(): void
    {
        $night = $this->night()->withVersion(3);

        self::assertSame(3, $night->withTable(new Table('t-2', 2))->version);
        self::assertSame('n-1', $night->id);
        self::assertSame('venue-1', $night->venue);
        self::assertSame('host-1', $night->host);
        self::assertSame('2027-03-05 19:00', $night->registered('m-1')->startsAt->format('Y-m-d H:i'));
        self::assertSame('2027-03-05 23:00', $night->registered('m-1')->endsAt->format('Y-m-d H:i'));
    }

    public function testAMemberSignedUpIsFoundByTheirSignUp(): void
    {
        $night = $this->night()->registered('m-1', true);

        self::assertTrue($night->signedUp('m-1')->guest);

        $this->expectException(NightRefused::class);
        $this->expectExceptionMessageIs('The member m-2 is not signed up for the night.');

        $night->signedUp('m-2');
    }

    public function testANewNightIsNotKeptYet(): void
    {
        self::assertSame(0, $this->night()->version);
    }

    public function testNoChangeOfSignUpsOrVotesIsMadeOnACancelledNight(): void
    {
        $night = $this->night()->withCandidates(['g-1'])->registered('m-1')->cancelled();
        $changes = [
            static fn (GameNight $kept): GameNight => $kept->withdrawn('m-1'),
            static fn (GameNight $kept): GameNight => $kept->seated('m-1', 't-1'),
            static fn (GameNight $kept): GameNight => $kept->voted('m-1', 'g-1'),
        ];

        foreach ($changes as $change) {
            try {
                $change($night);
                self::fail('The night is cancelled.');
            } catch (NightRefused $refused) {
                self::assertSame('The night is not open to sign-ups.', $refused->getMessage());
            }
        }
    }

    public function testAMemberIsSeatedAgainAtTheTableThatIsFull(): void
    {
        $night = $this->night([new Table('t-1', 2)])->registered('m-1', true)->seated('m-1', 't-1');

        self::assertSame('t-1', $night->seated('m-1', 't-1')->registration('m-1')?->table);
    }

    public function testTheSeatedMembersAreCheckedAfterTheOnesWithoutATable(): void
    {
        $this->expectException(InvalidNight::class);
        $this->expectExceptionMessageIs('A member seated at a table holds their places at a table of the night.');

        new GameNight(
            'n-1',
            new DateTimeImmutable('2027-03-05 19:00 UTC'),
            new DateTimeImmutable('2027-03-05 23:00 UTC'),
            'venue-1',
            'host-1',
            [new Table('t-1', 4)],
            NightStatus::Published,
            [new Registration('m-1'), new Registration('m-2', false, RegistrationStatus::Waitlisted, 't-1')],
        );
    }

    public function testTheTablesAreCheckedAfterTheMembersWithoutATable(): void
    {
        $this->expectException(InvalidNight::class);
        $this->expectExceptionMessageIs('The places taken at the table t-1 fit in its seats.');

        new GameNight(
            'n-1',
            new DateTimeImmutable('2027-03-05 19:00 UTC'),
            new DateTimeImmutable('2027-03-05 23:00 UTC'),
            'venue-1',
            'host-1',
            [new Table('t-1', 1), new Table('t-2', 3)],
            NightStatus::Published,
            [new Registration('m-1'), new Registration('m-2', true, table: 't-1')],
        );
    }
}
