<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Tests\Unit\Infrastructure\Store;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\EscapeRoom\Booking\BookingRefusal;
use Tilttek\EscapeRoom\Booking\SeatBooking;
use Tilttek\EscapeRoom\Booking\Session;
use Tilttek\EscapeRoom\Contract\SeatLedger;
use Tilttek\EscapeRoom\RoomResult;
use Tilttek\EscapeRoomBundle\Application\Contract\GameStore;
use Tilttek\EscapeRoomBundle\Application\Contract\PartyBookings;
use Tilttek\EscapeRoomBundle\Application\Contract\ResultStore;
use Tilttek\EscapeRoomBundle\Application\Contract\ScreenStore;
use Tilttek\EscapeRoomBundle\Application\Screen;
use Tilttek\EscapeRoomBundle\Application\ScreenKind;

/**
 * The scenarios every store of the bundle passes, in memory as in a database.
 */
abstract class StoresTestCase extends TestCase
{
    abstract protected function games(): GameStore;

    abstract protected function results(): ResultStore;

    abstract protected function screens(): ScreenStore;

    abstract protected function ledger(): SeatLedger&PartyBookings;

    public function testAGameIsWrittenAndReadBack(): void
    {
        $games = $this->games();
        $values = $this->values('team:owls');

        self::assertNull($games->find('g-1'));
        $games->save('g-1', $values, new DateTimeImmutable('2026-11-14 21:00:00 UTC'));
        self::assertSame($values, $games->find('g-1'));

        $ended = [...$values, 'ended_at' => '2026-11-14T20:30:00.000000Z', 'outcome' => 'escaped'];
        $games->save('g-1', $ended, null);
        self::assertSame($ended, $games->find('g-1'));
        self::assertSame([], $games->due(new DateTimeImmutable('2027-01-01 UTC'), 10));
    }

    public function testTheGamesDueAreTheEarliestFirst(): void
    {
        $games = $this->games();
        $games->save('late', $this->values('team:a'), new DateTimeImmutable('2026-11-14 21:00:00 UTC'));
        $games->save('early', $this->values('team:b'), new DateTimeImmutable('2026-11-14 20:30:00 UTC'));
        $games->save('paused', $this->values('team:c'), null);
        $games->save('later', $this->values('team:d'), new DateTimeImmutable('2026-11-14 22:00:00 UTC'));

        self::assertSame(['early', 'late'], $games->due(new DateTimeImmutable('2026-11-14 21:00:00 UTC'), 10));
        self::assertSame(['early'], $games->due(new DateTimeImmutable('2026-11-14 21:00:00 UTC'), 1));
        self::assertSame(['early'], $games->due(new DateTimeImmutable('2026-11-14 20:30:00 UTC'), 10));
        self::assertSame([], $games->due(new DateTimeImmutable('2026-11-14 20:29:59 UTC'), 10));
    }

    public function testTheGamesOfATeamArePseudonymised(): void
    {
        $games = $this->games();
        $games->save('g-1', $this->values('member:7'), null);
        $games->save('g-2', $this->values('team:owls'), null);
        $games->save('g-3', $this->values('member:7'), new DateTimeImmutable('2026-11-14 21:00:00 UTC'));

        $member = ResourceReference::of('member', '7');
        $erased = ResourceReference::of('erased', 'abc');

        self::assertSame(['g-1' => $this->values('member:7'), 'g-3' => $this->values('member:7')], $games->ofTeam($member));
        self::assertSame(2, $games->eraseTeam($member, $erased));
        self::assertSame([], $games->ofTeam($member));
        self::assertSame('erased:abc', $games->find('g-1')['team'] ?? null);
        self::assertSame(['g-1', 'g-3'], array_keys($games->ofTeam($erased)));
        self::assertSame('team:owls', $games->find('g-2')['team'] ?? null);
        self::assertSame(['g-3'], $games->due(new DateTimeImmutable('2026-11-14 21:00:00 UTC'), 10));
        self::assertSame(0, $games->eraseTeam($member, $erased));
    }

    public function testGamesAreForgotten(): void
    {
        $games = $this->games();
        $games->save('g-1', $this->values('member:7'), null);
        $games->save('g-2', $this->values('team:owls'), new DateTimeImmutable('2026-11-14 21:00:00 UTC'));
        $games->save('g-3', $this->values('team:bats'), null);

        self::assertSame(0, $games->forget());
        self::assertSame(2, $games->forget('g-1', 'g-2', 'g-1', 'g-9'));
        self::assertNull($games->find('g-1'));
        self::assertNull($games->find('g-2'));
        self::assertSame([], $games->due(new DateTimeImmutable('2026-11-14 21:00:00 UTC'), 10));
        self::assertSame('team:bats', $games->find('g-3')['team'] ?? null);
        self::assertSame(0, $games->forget('g-1'));
    }

    public function testTheResultsShownAreThoseTheTeamsAgreedToShow(): void
    {
        $results = $this->results();
        $results->record('g-1', $this->escape('team:a', 600, 2, '20:10:00.250000', true));
        $results->record('g-2', $this->escape('team:b', 600, 1, '20:20:00.000000', true));
        $results->record('g-3', $this->escape('team:c', 500, 5, '20:30:00.000000', false));
        $results->record('g-4', $this->escape('team:d', 600, 2, '20:10:00.125000', true));
        $results->record('g-5', $this->escape('team:e', 100, 0, '20:00:00.000000', true, 'cell'));

        self::assertSame(['team:b', 'team:d', 'team:a'], $this->teams($results->shown('crypt', 10)));
        self::assertSame(['team:b', 'team:d'], $this->teams($results->shown('crypt', 2)));
        self::assertEquals($this->escape('team:d', 600, 2, '20:10:00.125000', true), $results->shown('crypt', 2)[1]);

        $results->consent('g-3', true);
        $results->consent('g-1', false);
        $results->consent('g-9', true);
        self::assertSame(['team:c', 'team:b', 'team:d'], $this->teams($results->shown('crypt', 10)));

        $results->record('g-2', $this->escape('team:b', 700, 1, '20:20:00.000000', true));
        self::assertSame(['team:c', 'team:d', 'team:b'], $this->teams($results->shown('crypt', 10)));
    }

    public function testTheResultsOfATeamAreDeletedAndWithdrawn(): void
    {
        $results = $this->results();
        $results->record('g-1', $this->escape('member:7', 600, 2, '20:10:00.000000', true));
        $results->record('g-2', $this->escape('team:b', 700, 1, '20:20:00.000000', true));

        $member = ResourceReference::of('member', '7');

        self::assertEquals(['g-1' => $this->escape('member:7', 600, 2, '20:10:00.000000', true)], $results->ofTeam($member));
        self::assertSame(1, $results->forgetTeam($member));
        self::assertSame([], $results->ofTeam($member));
        self::assertSame(['team:b'], $this->teams($results->shown('crypt', 10)));
        self::assertCount(1, $results->ofTeam(ResourceReference::of('team', 'b')));
        self::assertSame(0, $results->forgetTeam($member));
        $results->consent('g-1', true);
        self::assertSame(['team:b'], $this->teams($results->shown('crypt', 10)));
    }

    public function testAScreenShowsWhatTheHostShowedLast(): void
    {
        $screens = $this->screens();
        $host = ResourceReference::of('member', '3');
        $at = new DateTimeImmutable('2026-11-14 20:05:00 UTC');

        self::assertNull($screens->screen('g-1'));
        $screens->show('g-1', new Screen(ScreenKind::Hint, $host, $at, 'candle', 2, 30));
        self::assertEquals(new Screen(ScreenKind::Hint, $host, $at, 'candle', 2, 30), $screens->screen('g-1'));

        $screens->show('g-1', new Screen(ScreenKind::Message, $host, $at, message: 'Look behind the clock — ✓'));
        self::assertEquals(new Screen(ScreenKind::Message, $host, $at, message: 'Look behind the clock — ✓'), $screens->screen('g-1'));
        self::assertNull($screens->screen('g-2'));
    }

    public function testTheScreensOfAHostArePseudonymised(): void
    {
        $screens = $this->screens();
        $host = ResourceReference::of('member', '3');
        $other = ResourceReference::of('member', '4');
        $erased = ResourceReference::of('erased', 'abc');
        $at = new DateTimeImmutable('2026-11-14 20:05:00 UTC');
        $screens->show('g-1', new Screen(ScreenKind::Cleared, $host, $at));
        $screens->show('g-2', new Screen(ScreenKind::Cleared, $other, $at));

        self::assertEquals(['g-1' => new Screen(ScreenKind::Cleared, $host, $at)], $screens->ofHost($host));
        self::assertSame(1, $screens->eraseHost($host, $erased));
        self::assertSame([], $screens->ofHost($host));
        self::assertEquals(new Screen(ScreenKind::Cleared, $erased, $at), $screens->screen('g-1'));
        self::assertEquals(new Screen(ScreenKind::Cleared, $other, $at), $screens->screen('g-2'));
    }

    public function testScreensAreForgotten(): void
    {
        $screens = $this->screens();
        $host = ResourceReference::of('member', '3');
        $at = new DateTimeImmutable('2026-11-14 20:05:00 UTC');
        $screens->show('g-1', new Screen(ScreenKind::Cleared, $host, $at));
        $screens->show('g-2', new Screen(ScreenKind::Cleared, $host, $at));
        $screens->show('g-3', new Screen(ScreenKind::Cleared, $host, $at));

        self::assertSame(0, $screens->forget());
        self::assertSame(2, $screens->forget('g-1', 'g-3', 'g-3', 'g-9'));
        self::assertNull($screens->screen('g-1'));
        self::assertNull($screens->screen('g-3'));
        self::assertEquals(['g-2' => new Screen(ScreenKind::Cleared, $host, $at)], $screens->ofHost($host));
    }

    public function testTheLedgerKeepsWhatItsDecisionAccepts(): void
    {
        $ledger = $this->ledger();
        $session = self::session('s-1');
        $ana = ResourceReference::of('member', 'ana');
        $seen = [];

        self::assertNull($ledger->hold($session, new SeatBooking('a', 's-1', $ana, 2, false), static function (array $held) use (&$seen): ?BookingRefusal {
            $seen[] = \count($held);

            return null;
        }));
        self::assertSame(BookingRefusal::Taken, $ledger->hold($session, new SeatBooking('b', 's-1', $ana, 1, false), static function (array $held) use (&$seen): BookingRefusal {
            $seen[] = \count($held);

            return BookingRefusal::Taken;
        }));
        self::assertNull($ledger->hold($session, new SeatBooking('c', 's-1', ResourceReference::of('team', 'owls'), 3, true), static fn (array $held): ?BookingRefusal => null));

        self::assertSame([0, 1], $seen);
        self::assertEquals([new SeatBooking('a', 's-1', $ana, 2, false), new SeatBooking('c', 's-1', ResourceReference::of('team', 'owls'), 3, true)], $ledger->bookings($session));
        self::assertSame([], $ledger->bookings(self::session('s-2')));
    }

    public function testTheBookingsOfAPartyArePseudonymised(): void
    {
        $ledger = $this->ledger();
        $ana = ResourceReference::of('member', 'ana');
        $erased = ResourceReference::of('erased', 'abc');
        $accept = static fn (array $held): ?BookingRefusal => null;
        $ledger->hold(self::session('s-1'), new SeatBooking('a', 's-1', $ana, 2, false), $accept);
        $ledger->hold(self::session('s-1'), new SeatBooking('b', 's-1', ResourceReference::of('member', 'ben'), 1, false), $accept);
        $ledger->hold(self::session('s-2'), new SeatBooking('c', 's-2', $ana, 4, true), $accept);

        self::assertEquals([new SeatBooking('a', 's-1', $ana, 2, false), new SeatBooking('c', 's-2', $ana, 4, true)], $ledger->ofParty($ana));
        self::assertSame(2, $ledger->eraseParty($ana, $erased));
        self::assertSame([], $ledger->ofParty($ana));
        self::assertEquals([new SeatBooking('a', 's-1', $erased, 2, false), new SeatBooking('b', 's-1', ResourceReference::of('member', 'ben'), 1, false)], $ledger->bookings(self::session('s-1')));
    }

    public function testSavingAGameLeavesTheOthersAlone(): void
    {
        $games = $this->games();
        $games->save('g-1', $this->values('team:a'), new DateTimeImmutable('2026-11-14 21:00:00 UTC'));
        $games->save('g-2', $this->values('team:b'), new DateTimeImmutable('2026-11-14 22:00:00 UTC'));
        $games->save('g-1', [...$this->values('team:a'), 'paused_seconds' => 5], new DateTimeImmutable('2026-11-14 23:00:00 UTC'));

        self::assertSame($this->values('team:b'), $games->find('g-2'));
        self::assertSame(['g-2'], $games->due(new DateTimeImmutable('2026-11-14 22:30:00 UTC'), 10));
        self::assertSame(5, $games->find('g-1')['paused_seconds'] ?? null);
    }

    public function testTheGamesDueAreNamedByStrings(): void
    {
        $games = $this->games();
        $games->save('42', $this->values('team:a'), new DateTimeImmutable('2026-11-14 21:00:00 UTC'));

        self::assertSame(['42'], $games->due(new DateTimeImmutable('2026-11-14 21:00:00 UTC'), 10));
    }

    public function testAtLeastOneGameDueIsGivenWhateverTheLimit(): void
    {
        $games = $this->games();
        $games->save('late', $this->values('team:a'), new DateTimeImmutable('2026-11-14 21:00:00 UTC'));
        $games->save('early', $this->values('team:b'), new DateTimeImmutable('2026-11-14 20:30:00 UTC'));

        self::assertSame(['early'], $games->due(new DateTimeImmutable('2026-11-14 21:00:00 UTC'), 0));
        self::assertSame(['early'], $games->due(new DateTimeImmutable('2026-11-14 21:00:00 UTC'), -3));
    }

    public function testErasingATeamKeepsTheRestOfItsGames(): void
    {
        $games = $this->games();
        $games->save('g-1', $this->values('member:7'), null);
        $games->eraseTeam(ResourceReference::of('member', '7'), ResourceReference::of('erased', 'abc'));

        self::assertSame([...$this->values('member:7'), 'team' => 'erased:abc'], $games->find('g-1'));
        $games->save('g-2', [...$this->values('member:8'), 'public' => true], null);
        $games->eraseTeam(ResourceReference::of('member', '8'), ResourceReference::of('erased', 'def'));
        self::assertSame([...$this->values('member:8'), 'team' => 'erased:def', 'public' => false], $games->find('g-2'));
    }

    public function testTheResultsOfATeamAreAllGiven(): void
    {
        $results = $this->results();
        $results->record('g-1', $this->escape('team:a', 600, 2, '20:10:00.000000', true));
        $results->record('g-2', $this->escape('team:a', 500, 1, '20:20:00.000000', false, 'cell'));

        self::assertSame(['g-1', 'g-2'], array_keys($results->ofTeam(ResourceReference::of('team', 'a'))));
    }

    public function testTheConsentOfAResultIsReadBack(): void
    {
        $results = $this->results();
        $results->record('g-1', $this->escape('team:a', 600, 2, '20:10:00.000000', true));
        $results->record('g-2', $this->escape('team:b', 600, 2, '20:10:00.000000', false));

        $results->consent('g-1', false);
        $results->consent('g-2', true);

        self::assertFalse($results->ofTeam(ResourceReference::of('team', 'a'))['g-1']->public);
        self::assertTrue($results->ofTeam(ResourceReference::of('team', 'b'))['g-2']->public);
    }

    public function testAtLeastOneResultIsShownWhateverTheLimit(): void
    {
        $results = $this->results();
        $results->record('g-1', $this->escape('team:a', 600, 2, '20:10:00.000000', true));
        $results->record('g-2', $this->escape('team:b', 700, 2, '20:10:00.000000', true));

        self::assertSame(['team:a'], $this->teams($results->shown('crypt', 0)));
        self::assertSame(['team:a', 'team:b'], $this->teams($results->shown('crypt', 2)));
        self::assertSame(['team:a', 'team:b'], $this->teams($results->shown('crypt', 3)));
    }

    public function testTheMicrosecondsOfAnEscapeAreKept(): void
    {
        $results = $this->results();
        $results->record('g-1', $this->escape('team:a', 600, 2, '20:10:00.000125', true));

        self::assertEquals($this->escape('team:a', 600, 2, '20:10:00.000125', true), $results->shown('crypt', 1)[0]);
    }

    public function testTheScreensOfAHostAreAllGiven(): void
    {
        $screens = $this->screens();
        $host = ResourceReference::of('member', '3');
        $at = new DateTimeImmutable('2026-11-14 20:05:00 UTC');
        $screens->show('g-1', new Screen(ScreenKind::Cleared, $host, $at));
        $screens->show('g-2', new Screen(ScreenKind::Cleared, $host, $at));
        $screens->show('g-3', new Screen(ScreenKind::Cleared, ResourceReference::of('member', '4'), $at));
        $screens->show('g-2', new Screen(ScreenKind::Message, $host, $at, message: 'Hi'));

        self::assertSame(['g-1', 'g-2'], array_keys($screens->ofHost($host)));
        self::assertEquals(new Screen(ScreenKind::Cleared, ResourceReference::of('member', '4'), $at), $screens->screen('g-3'));
        self::assertEquals(new Screen(ScreenKind::Cleared, $host, $at), $screens->screen('g-1'));
        self::assertSame(0, $screens->screen('g-1')?->penalty);
    }

    public function testASavedResultLeavesTheOthersAlone(): void
    {
        $results = $this->results();
        $results->record('g-1', $this->escape('team:a', 600, 2, '20:10:00.000000', true));
        $results->record('g-2', $this->escape('team:b', 500, 1, '20:20:00.000000', true));
        $results->record('g-1', $this->escape('team:a', 700, 2, '20:10:00.000000', true));

        self::assertSame(['team:b', 'team:a'], $this->teams($results->shown('crypt', 10)));
        self::assertSame(500, $results->ofTeam(ResourceReference::of('team', 'b'))['g-2']->finalSeconds);
    }

    protected static function session(string $id): Session
    {
        return new Session($id, 'crypt', new DateTimeImmutable('2026-11-15 19:00 UTC'), new DateTimeImmutable('2026-11-15 20:00 UTC'));
    }

    /**
     * @return array{room: string, team: string, session: ?string, started_at: string, paused_at: ?string, paused_seconds: int, ended_at: ?string, outcome: ?string, progress: array<string, mixed>, public: bool}
     */
    private function values(string $team): array
    {
        return [
            'room' => 'crypt',
            'team' => $team,
            'session' => 's-1',
            'started_at' => '2026-11-14T20:00:00.000000Z',
            'paused_at' => null,
            'paused_seconds' => 0,
            'ended_at' => null,
            'outcome' => null,
            'progress' => ['opened' => ['candle' => '2026-11-14T20:00:00.000000Z'], 'note' => 'é'],
            'public' => false,
        ];
    }

    private function escape(string $team, int $final, int $hints, string $at, bool $public, string $room = 'crypt'): RoomResult
    {
        return new RoomResult($room, ResourceReference::fromString($team), 's-1', $final, $final - 60, 60, $hints, 1, new DateTimeImmutable('2026-11-14 '.$at.' UTC'), $public);
    }

    /**
     * @param list<RoomResult> $results
     *
     * @return list<string>
     */
    private function teams(array $results): array
    {
        return array_map(static fn (RoomResult $result): string => $result->team->toString(), $results);
    }
}
