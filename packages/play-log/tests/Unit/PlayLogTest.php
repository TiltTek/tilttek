<?php

declare(strict_types=1);

namespace Tilttek\PlayLog\Tests\Unit;

use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tilttek\GameCatalog\Catalog;
use Tilttek\GameCatalog\Details;
use Tilttek\GameCatalog\Exception\EntryNotFound;
use Tilttek\GameCatalog\Store\InMemoryCatalogStore;
use Tilttek\PlayLog\Event\PlayCorrected;
use Tilttek\PlayLog\Event\PlayDeleted;
use Tilttek\PlayLog\Event\PlayRecorded;
use Tilttek\PlayLog\Event\SeatLinked;
use Tilttek\PlayLog\Event\SeatPending;
use Tilttek\PlayLog\Event\SeatUnlinked;
use Tilttek\PlayLog\Exception\InvalidPlay;
use Tilttek\PlayLog\Exception\PlayConflict;
use Tilttek\PlayLog\Exception\PlayNotFound;
use Tilttek\PlayLog\Exception\PlayRefused;
use Tilttek\PlayLog\Play;
use Tilttek\PlayLog\PlayLog;
use Tilttek\PlayLog\PlayQuery;
use Tilttek\PlayLog\Result;
use Tilttek\PlayLog\Seat;
use Tilttek\PlayLog\Store\InMemoryPlayStore;
use Tilttek\PlayLog\Tests\Unit\Fixture\Consent;
use Tilttek\PlayLog\Tests\Unit\Fixture\LazyStore;
use Tilttek\PlayLog\Tests\Unit\Fixture\Observed;

#[CoversClass(PlayLog::class)]
#[CoversClass(PlayRecorded::class)]
#[CoversClass(PlayCorrected::class)]
#[CoversClass(PlayDeleted::class)]
#[CoversClass(SeatLinked::class)]
#[CoversClass(SeatPending::class)]
#[CoversClass(SeatUnlinked::class)]
#[CoversClass(PlayNotFound::class)]
#[CoversClass(PlayRefused::class)]
final class PlayLogTest extends TestCase
{
    private Catalog $catalog;

    private InMemoryPlayStore $store;

    private Observed $observed;

    private PlayLog $log;

    #[Override]
    protected function setUp(): void
    {
        $this->catalog = new Catalog(new InMemoryCatalogStore(), new MockClock(new DateTimeImmutable('2027-03-01 10:00 UTC')));

        foreach (['tide', 'ebb'] as $game) {
            $this->catalog->add($game, new Details(ucfirst($game)));
        }

        $this->catalog->hide($this->catalog->add('hidden', new Details('Hidden'))->id);
        $this->store = new InMemoryPlayStore();
        $this->observed = new Observed();
        $this->log = new PlayLog($this->store, $this->catalog, $this->observed, new Consent(['cy' => ['ana']]));
    }

    public function testAPlayIsRecordedWithTheRecorderLinked(): void
    {
        $play = $this->log->record($this->play());

        self::assertSame(1, $play->version);
        self::assertSame([true, false, true, false], array_map(static fn (Seat $seat): bool => $seat->confirmed, $play->seats));
        self::assertEquals($play, $this->log->play('p1'));
        self::assertEquals([
            new PlayRecorded('p1', 'tide', 'ana'),
            new SeatLinked('p1', 'tide', 'ana'),
            new SeatLinked('p1', 'tide', 'cy'),
            new SeatPending('p1', 'tide', 'bo'),
        ], $this->observed->take());
    }

    public function testTheConfirmationGivenIsNeverRead(): void
    {
        $play = $this->log->record(new Play('p1', 'tide', 'ana', [new Seat('bo', confirmed: true)]));

        self::assertFalse($play->seats[0]->confirmed);
    }

    public function testWithoutConsentEveryOtherSeatWaits(): void
    {
        $log = new PlayLog($this->store, $this->catalog);
        $play = $log->record($this->play());

        self::assertSame([true, false, false, false], array_map(static fn (Seat $seat): bool => $seat->confirmed, $play->seats));
    }

    public function testARecorderMayNotHoldASeat(): void
    {
        $play = $this->log->record(new Play('p1', 'tide', 'ana', [new Seat('bo'), new Seat()]));

        self::assertSame([], $play->linkedMembers());
        self::assertEquals([new PlayRecorded('p1', 'tide', 'ana'), new SeatPending('p1', 'tide', 'bo')], $this->observed->take());
    }

    public function testANewPlayIsAtVersionZero(): void
    {
        $this->expectException(PlayRefused::class);
        $this->expectExceptionMessageIsOrContains('A new play is at version 0.');

        $this->log->record($this->play()->withVersion(1));
    }

    public function testAPlayIsRecordedOnce(): void
    {
        $this->log->record($this->play());
        $this->observed->take();

        try {
            $this->log->record($this->play());
            self::fail('A play is recorded twice.');
        } catch (PlayRefused $playRefused) {
            self::assertSame('A play has the identifier p1.', $playRefused->getMessage());
            self::assertSame([], $this->observed->take());
        }
    }

    public function testTheGameIsInTheCatalogue(): void
    {
        $this->expectException(EntryNotFound::class);

        $this->log->record(new Play('p1', 'unknown', 'ana', [new Seat('ana')]));
    }

    public function testAHiddenGameIsRefused(): void
    {
        $this->expectException(PlayRefused::class);
        $this->expectExceptionMessageIsOrContains('The game hidden is hidden in the catalogue.');

        $this->log->record(new Play('p1', 'hidden', 'ana', [new Seat('ana')]));
    }

    public function testAnUnknownPlayIsNotFound(): void
    {
        $this->expectException(PlayNotFound::class);
        $this->expectExceptionMessageIsOrContains('No play has the identifier p9.');

        $this->log->play('p9');
    }

    public function testAPlayIsReadByAnIdentifier(): void
    {
        $this->expectException(InvalidPlay::class);

        $this->log->play('p 9');
    }

    public function testThePlaysAreReadWithinALimit(): void
    {
        $this->log->record($this->play());
        $this->log->record(new Play('p2', 'ebb', 'ana', [new Seat('ana')], playedAt: new DateTimeImmutable('2027-01-01')));

        self::assertSame(['p1', 'p2'], array_map(static fn (Play $play): string => $play->id, [...$this->log->plays(PlayQuery::ofMember('ana'), 1000)]));
        self::assertCount(1, [...$this->log->plays(PlayQuery::ofMember('ana'), 1)]);

        foreach ([0, 1001] as $limit) {
            try {
                $this->log->plays(PlayQuery::ofMember('ana'), $limit);
                self::fail('A limit out of its bounds is taken.');
            } catch (InvalidPlay $invalid) {
                self::assertSame('At most 1 to 1000 plays are read at once.', $invalid->getMessage());
            }
        }
    }

    public function testACorrectionKeepsTheConfirmedSeats(): void
    {
        $kept = $this->log->record($this->play());
        $this->log->confirm('p1', 'bo');
        $this->observed->take();
        $read = $this->log->play('p1');

        $corrected = $this->log->correct(new Play('p1', 'ebb', 'ana', [new Seat('ana', 9, true), new Seat('bo', 3), new Seat('dee')], playedAt: $kept->playedAt, version: $read->version), 'ana');

        self::assertSame(3, $corrected->version);
        self::assertSame([true, true, false], array_map(static fn (Seat $seat): bool => $seat->confirmed, $corrected->seats));
        self::assertEquals([
            new PlayCorrected('p1', 'ebb', 'ana'),
            new SeatPending('p1', 'ebb', 'dee'),
            new SeatUnlinked('p1', 'tide', 'cy'),
        ], $this->observed->take());
    }

    public function testACorrectionWithTheSameSeatsTellsOnlyTheCorrection(): void
    {
        $kept = $this->log->record($this->play());
        $this->observed->take();
        $this->log->correct($kept, 'ana');

        self::assertEquals([new PlayCorrected('p1', 'tide', 'ana')], $this->observed->take());
    }

    public function testOnlyTheRecorderCorrects(): void
    {
        $kept = $this->log->record($this->play());

        $this->expectException(PlayRefused::class);
        $this->expectExceptionMessageIsOrContains('Only the member who recorded the play p1 changes it.');

        $this->log->correct($kept, 'bo');
    }

    public function testACorrectionKeepsTheRecorder(): void
    {
        $this->log->record($this->play());

        $this->expectException(PlayRefused::class);
        $this->expectExceptionMessageIsOrContains('A correction keeps the member who recorded the play.');

        $this->log->correct(new Play('p1', 'tide', 'bo', [new Seat('bo')], version: 1), 'ana');
    }

    public function testACorrectionToAHiddenGameIsRefused(): void
    {
        $this->log->record($this->play());

        $this->expectException(PlayRefused::class);
        $this->expectExceptionMessageIsOrContains('The game hidden is hidden in the catalogue.');

        $this->log->correct(new Play('p1', 'hidden', 'ana', [new Seat('ana')], version: 1), 'ana');
    }

    public function testAGameHiddenSinceStaysOnItsPlays(): void
    {
        $kept = $this->log->record(new Play('p1', 'ebb', 'ana', [new Seat('ana')]));
        $this->catalog->hide('ebb');

        self::assertSame(2, $this->log->correct($kept->withSeats([new Seat('ana', 4)]), 'ana')->version);
    }

    public function testAStaleCorrectionIsRefused(): void
    {
        $kept = $this->log->record($this->play());
        $this->log->correct($kept, 'ana');

        $this->expectException(PlayConflict::class);

        $this->log->correct($kept, 'ana');
    }

    public function testACorrectionTakesAMember(): void
    {
        $kept = $this->log->record($this->play());

        $this->expectException(InvalidPlay::class);

        $this->log->correct($kept, 'ana@example.org');
    }

    public function testTheRecorderDeletesAPlay(): void
    {
        $this->log->record($this->play());
        $this->log->confirm('p1', 'bo');

        $this->observed->take();
        $this->log->delete('p1', 'ana');

        self::assertNull($this->store->play('p1'));
        self::assertEquals([
            new PlayDeleted('p1', 'tide', 'ana'),
            new SeatUnlinked('p1', 'tide', 'ana'),
            new SeatUnlinked('p1', 'tide', 'bo'),
            new SeatUnlinked('p1', 'tide', 'cy'),
        ], $this->observed->take());
    }

    public function testOnlyTheRecorderDeletes(): void
    {
        $this->log->record($this->play());

        try {
            $this->log->delete('p1', 'bo');
            self::fail('Another member deleted the play.');
        } catch (PlayRefused) {
            self::assertInstanceOf(Play::class, $this->store->play('p1'));
        }
    }

    public function testAMemberConfirmsTheirSeat(): void
    {
        $this->log->record($this->play());
        $this->observed->take();
        $play = $this->log->confirm('p1', 'bo');

        self::assertTrue($play->seat('bo')?->confirmed);
        self::assertSame(2, $play->version);
        self::assertEquals([new SeatLinked('p1', 'tide', 'bo')], $this->observed->take());
        self::assertSame($play->version, $this->log->confirm('p1', 'bo')->version);
        self::assertSame([], $this->observed->take());
    }

    public function testOnlyAMemberOfThePlayConfirms(): void
    {
        $this->log->record($this->play());

        $this->expectException(PlayRefused::class);
        $this->expectExceptionMessageIsOrContains('The member dee holds no seat of the play p1.');

        $this->log->confirm('p1', 'dee');
    }

    public function testAConfirmationTakesAMember(): void
    {
        $this->log->record($this->play());

        $this->expectException(InvalidPlay::class);

        $this->log->confirm('p1', 'b o');
    }

    public function testAMemberDeclinesTheirSeat(): void
    {
        $this->log->record($this->play());
        $this->observed->take();
        $play = $this->log->decline('p1', 'cy');

        self::assertSame(['ana', 'bo', null, null], array_map(static fn (Seat $seat): ?string => $seat->member, $play->seats));
        self::assertSame(5, $play->seats[2]->score);
        self::assertTrue($play->seats[2]->winner);
        self::assertEquals([new SeatUnlinked('p1', 'tide', 'cy')], $this->observed->take());
    }

    public function testDecliningAPendingSeatTellsNothing(): void
    {
        $this->log->record($this->play());
        $this->observed->take();
        $this->log->decline('p1', 'bo');

        self::assertSame([], $this->observed->take());
        self::assertNull($this->log->play('p1')->seat('bo'));
    }

    public function testAMemberIsForgotten(): void
    {
        $this->log->record($this->play());
        $this->log->record(new Play('p2', 'ebb', 'bo', [new Seat('bo'), new Seat('ana', 4)]));
        $this->log->record(new Play('p3', 'ebb', 'cy', [new Seat('cy'), new Seat('ana')]));
        $this->log->confirm('p3', 'ana');
        $this->log->record(new Play('p4', 'ebb', 'cy', [new Seat('cy')]));

        $this->observed->take();

        self::assertSame(3, $this->log->forget('ana', 10));
        self::assertNull($this->store->play('p1'));
        self::assertSame(['bo', null], array_map(static fn (Seat $seat): ?string => $seat->member, $this->log->play('p2')->seats));
        self::assertSame(4, $this->log->play('p2')->seats[1]->score);
        self::assertSame(['cy', null], array_map(static fn (Seat $seat): ?string => $seat->member, $this->log->play('p3')->seats));
        self::assertEquals([
            new PlayDeleted('p1', 'tide', 'ana'),
            new SeatUnlinked('p1', 'tide', 'ana'),
            new SeatUnlinked('p1', 'tide', 'cy'),
            new SeatUnlinked('p3', 'ebb', 'ana'),
        ], $this->observed->take());
        self::assertSame(0, $this->log->forget('ana', 10));
    }

    public function testForgettingGoesByBatches(): void
    {
        foreach (['p1', 'p2'] as $id) {
            $this->log->record(new Play($id, 'tide', 'ana', [new Seat('ana')]));
        }

        foreach (['p3', 'p4', 'p5'] as $id) {
            $this->log->record(new Play($id, 'tide', 'cy', [new Seat('cy'), new Seat('ana')]));
        }

        $this->log->confirm('p3', 'ana');

        self::assertSame(2, $this->log->forget('ana', 2));
        self::assertNotNull($this->log->play('p3')->seat('ana'));
        self::assertSame(2, $this->log->forget('ana', 2));
        self::assertNull($this->log->play('p3')->seat('ana'));
        self::assertSame(1, $this->log->forget('ana', 1000));
        self::assertSame(0, $this->log->forget('ana', 1000));

        $this->expectException(InvalidPlay::class);

        $this->log->forget('ana', 1001);
    }

    public function testForgettingReadsEveryPlayBeforeChangingThem(): void
    {
        $store = new LazyStore();
        $log = new PlayLog($store, $this->catalog, null, new Consent(['cy' => ['ana']]));

        foreach (['p1', 'p2'] as $id) {
            $log->record(new Play($id, 'tide', 'ana', [new Seat('ana')]));
        }

        foreach (['p3', 'p4'] as $id) {
            $log->record(new Play($id, 'tide', 'cy', [new Seat('cy'), new Seat('ana')]));
        }

        $store->journal = [];

        self::assertSame(4, $log->forget('ana', 10));
        self::assertSame([
            'read p1', 'read p2', 'delete p1', 'delete p2',
            'read p3', 'read p4', 'save p3', 'save p4',
        ], $store->journal);
        self::assertNull($store->play('p1'));
        self::assertNull($log->play('p3')->seat('ana'));
    }

    public function testEveryPendingSeatIsTold(): void
    {
        $log = new PlayLog($this->store, $this->catalog, $this->observed);
        $log->record(new Play('p1', 'tide', 'ana', [new Seat('ana'), new Seat('bo'), new Seat('cy'), new Seat('dee')]));

        self::assertEquals([
            new SeatPending('p1', 'tide', 'bo'),
            new SeatPending('p1', 'tide', 'cy'),
            new SeatPending('p1', 'tide', 'dee'),
        ], \array_slice($this->observed->take(), -3));
    }

    public function testForgettingTakesALimit(): void
    {
        $this->expectException(InvalidPlay::class);

        $this->log->forget('ana', 0);
    }

    public function testAPlayIsToldWithoutAnObserver(): void
    {
        $log = new PlayLog($this->store, $this->catalog);

        self::assertSame(1, $log->record($this->play())->version);
        self::assertSame([], $this->observed->take());
    }

    private function play(): Play
    {
        return new Play('p1', 'tide', 'ana', [new Seat('ana', 10), new Seat('bo', 7), new Seat('cy', 5, true), new Seat()], Result::Competitive, new DateTimeImmutable('2027-02-01 19:00 UTC'), 60, 'club', 'Back room');
    }
}
