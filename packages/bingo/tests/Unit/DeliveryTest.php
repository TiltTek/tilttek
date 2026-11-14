<?php

declare(strict_types=1);

namespace Tilttek\Bingo\Tests\Unit;

use Closure;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Entry;
use Tilttek\Activity\EntryKind;
use Tilttek\Bingo\Activity\BingoActivityObserver;
use Tilttek\Bingo\Bingo;
use Tilttek\Bingo\BingoGame;
use Tilttek\Bingo\Card;
use Tilttek\Bingo\CardDealer;
use Tilttek\Bingo\Event\BingoWon;
use Tilttek\Bingo\Event\SquareCalled;
use Tilttek\Bingo\Event\SquareTicked;
use Tilttek\Bingo\Exception\BingoConflict;
use Tilttek\Bingo\Grid;
use Tilttek\Bingo\Store\InMemoryBingoStore;
use Tilttek\Bingo\Tests\Unit\Fixture\FailingDispatcher;
use Tilttek\Bingo\Tests\Unit\Fixture\FrozenClock;
use Tilttek\Bingo\Tests\Unit\Fixture\Games;
use Tilttek\Bingo\Tests\Unit\Fixture\ScriptedStore;
use Tilttek\Bingo\TickMode;
use Tilttek\Bingo\WinPatterns;

#[CoversClass(Bingo::class)]
#[CoversClass(BingoGame::class)]
#[CoversClass(Card::class)]
#[CoversClass(BingoActivityObserver::class)]
#[CoversClass(SquareTicked::class)]
#[CoversClass(SquareCalled::class)]
#[CoversClass(BingoWon::class)]
final class DeliveryTest extends TestCase
{
    private FailingDispatcher $events;

    private Bingo $bingo;

    protected function setUp(): void
    {
        $this->start(static fn (object $event): bool => false);
    }

    public function testATickWhoseListenerFailedIsDispatchedWhenTheGestureIsRecordedAgain(): void
    {
        $this->start(static fn (object $event): bool => $event instanceof SquareTicked);
        $this->bingo->deal('con-2026', 'member:1');

        try {
            $this->bingo->record($this->gesture('k1', 'booth:11'));
            self::fail('The failure of the listener was hidden.');
        } catch (RuntimeException) {
        }

        self::assertSame([0], array_keys($this->bingo->card('con-2026', 1)->ticks()));
        self::assertCount(1, $this->bingo->card('con-2026', 1)->undelivered());

        $this->bingo->record($this->gesture('k1', 'booth:11'));
        $this->bingo->record($this->gesture('k1', 'booth:11'));

        self::assertEquals([new SquareTicked('con-2026', 1, 's11', 'k1')], $this->events->delivered);
    }

    public function testACallWhoseListenerFailedIsDispatchedWhenTheSquareIsCalledAgain(): void
    {
        $this->start(static fn (object $event): bool => $event instanceof SquareCalled, TickMode::Called);

        try {
            $this->bingo->call('con-2026', 's1');
            self::fail('The failure of the listener was hidden.');
        } catch (RuntimeException) {
        }

        self::assertSame(['s1'], $this->bingo->game('con-2026')->called());
        $this->bingo->call('con-2026', 's1');
        $this->bingo->call('con-2026', 's1');

        self::assertEquals([new SquareCalled('con-2026', 's1')], $this->events->delivered);
    }

    public function testAWinWhoseListenerFailedIsDispatchedByTheNextClaim(): void
    {
        $this->start(static fn (object $event): bool => $event instanceof BingoWon);
        $this->bingo->deal('con-2026', 'member:1');
        $this->bingo->record($this->gesture('k1', 'booth:11'));
        $this->bingo->record($this->gesture('k2', 'booth:5'));

        try {
            $this->bingo->record($this->gesture('k3', 'booth:1'));
            self::fail('The failure of the listener was hidden.');
        } catch (RuntimeException) {
        }

        self::assertSame('row', $this->bingo->card('con-2026', 1)->pattern());
        self::assertEquals([new BingoWon('con-2026', 1, 'member:1', 'row')], $this->bingo->card('con-2026', 1)->undelivered());

        self::assertTrue($this->bingo->claim('con-2026', 1, 'member:1')->isWon());
        self::assertTrue($this->bingo->claim('con-2026', 1, 'member:1')->isWon());

        $delivered = $this->events->delivered;
        self::assertCount(4, $delivered);
        self::assertEquals(new BingoWon('con-2026', 1, 'member:1', 'row'), $delivered[3]);
        self::assertSame([1], $this->bingo->game('con-2026')->winners());
    }

    public function testAFailedEventLetsTheOthersThroughThenIsThrownAgain(): void
    {
        $this->start(static fn (object $event): bool => $event instanceof SquareTicked && 'con-2026' === $event->game);
        $this->bingo->create('other', Games::pool(12), new Grid(3), WinPatterns::line(new Grid(3)));
        $mine = $this->bingo->deal('con-2026', 'member:1');
        $other = $this->bingo->deal('other', 'member:1');
        $square = (string) current(array_intersect(array_filter($mine->layout, static fn (?string $square): bool => null !== $square), $other->layout));

        try {
            $this->bingo->record($this->gesture('k1', 'booth:'.substr($square, 1)));
            self::fail('The failure of the listener was hidden.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The handler failed, time 1.', $runtimeException->getMessage());
        }

        self::assertEquals([new SquareTicked('other', 1, $square, 'k1')], $this->events->delivered);
        self::assertEquals([new SquareTicked('con-2026', 1, $square, 'k1')], $this->bingo->card('con-2026', 1)->undelivered());
        self::assertSame([], $this->bingo->card('other', 1)->undelivered());
    }

    public function testDeliverResumesTheCallsOfAGameAndTheEventsOfACard(): void
    {
        $this->start(static fn (object $event): bool => true, TickMode::Called, 3);

        foreach (['s1', 's2'] as $square) {
            try {
                $this->bingo->call('con-2026', $square);
                self::fail('The failure of the listener was hidden.');
            } catch (RuntimeException) {
            }
        }

        self::assertSame(2, $this->bingo->deliver('con-2026'));
        self::assertSame(0, $this->bingo->deliver('con-2026'));
        self::assertEquals([new SquareCalled('con-2026', 's1'), new SquareCalled('con-2026', 's2')], $this->events->delivered);
        self::assertSame([], $this->bingo->game('con-2026')->undelivered());

        $this->start(static fn (object $event): bool => true, TickMode::Activity, 1);
        $this->bingo->deal('con-2026', 'member:1');

        try {
            $this->bingo->record($this->gesture('k1', 'booth:11'));
        } catch (RuntimeException) {
        }

        $this->events->failures = 1;

        try {
            $this->bingo->deliver('con-2026', 1);
            self::fail('The failure of the listener was hidden.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The handler failed, time 2.', $runtimeException->getMessage());
        }

        self::assertSame(1, $this->bingo->deliver('con-2026', 1));
        self::assertSame(0, $this->bingo->deliver('con-2026', 1));
        self::assertEquals([new SquareTicked('con-2026', 1, 's11', 'k1')], $this->events->delivered);
    }

    public function testDeliverCountsTheGameAndTheCardAndThrowsTheFirstFailure(): void
    {
        $this->start(static fn (object $event): bool => true, TickMode::Called, 100);
        $this->bingo->deal('con-2026', 'member:1');

        foreach (['s11', 's5', 's1'] as $square) {
            try {
                $this->bingo->call('con-2026', $square);
            } catch (RuntimeException) {
            }
        }

        try {
            $this->bingo->claim('con-2026', 1, 'member:1');
            self::fail('The failure of the listener was hidden.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The handler failed, time 7.', $runtimeException->getMessage());
        }

        try {
            $this->bingo->deliver('con-2026', 1);
            self::fail('The failure of the listener was hidden.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The handler failed, time 8.', $runtimeException->getMessage(), 'The first failure, of the game.');
        }

        $this->events->failures = 0;

        self::assertSame(4, $this->bingo->deliver('con-2026', 1));
        self::assertInstanceOf(BingoWon::class, $this->events->delivered[3]);
        self::assertSame([1], $this->bingo->game('con-2026')->winners());
    }

    public function testTheStepOfAWinThatFailsKeepsItsEventUndelivered(): void
    {
        $store = new ScriptedStore();
        $this->events = new FailingDispatcher(static fn (object $event): bool => false);
        $this->bingo = new Bingo($store, new CardDealer(Games::SECRET), $this->events, new FrozenClock());
        $grid = new Grid(3);
        $this->bingo->create('con-2026', Games::pool(12), $grid, WinPatterns::line($grid));
        $this->bingo->deal('con-2026', 'member:1');
        $this->bingo->record($this->gesture('k1', 'booth:11'));
        $this->bingo->record($this->gesture('k2', 'booth:5'));

        $store->gameConflicts = Bingo::ATTEMPTS;

        try {
            $this->bingo->record($this->gesture('k3', 'booth:1'));
            self::fail('The failure of the step was hidden.');
        } catch (BingoConflict) {
        }

        self::assertSame([], $this->bingo->game('con-2026')->winners());
        self::assertEquals([new BingoWon('con-2026', 1, 'member:1', 'row')], $this->bingo->card('con-2026', 1)->undelivered());
        self::assertSame(1, $this->bingo->deliver('con-2026', 1));
        self::assertSame([1], $this->bingo->game('con-2026')->winners());
    }

    public function testATickAndItsDeliveryReadTheCardOnceEach(): void
    {
        $store = new ScriptedStore();
        $this->bingo = new Bingo($store, new CardDealer(Games::SECRET), $this->events, new FrozenClock());
        $grid = new Grid(3);
        $this->bingo->create('con-2026', Games::pool(12), $grid, WinPatterns::line($grid));
        $this->bingo->deal('con-2026', 'member:1');

        $store->cardReads = 0;

        $this->bingo->record($this->gesture('k1', 'booth:11'));

        self::assertSame(2, $store->cardReads);
        self::assertSame(2, $store->cardSaves);
    }

    public function testTheObserverHandedAnEntryAgainDispatchesWhatFailed(): void
    {
        $this->start(static fn (object $event): bool => $event instanceof SquareTicked);
        $this->bingo->deal('con-2026', 'member:1');
        $observer = new BingoActivityObserver($this->bingo);
        $entry = new Entry(1, EntryKind::Recorded, $this->gesture('k1', 'booth:11'), new DateTimeImmutable('2026-11-12 20:00:00 UTC'));

        try {
            $observer->appended($entry);
            self::fail('The failure of the listener was hidden.');
        } catch (RuntimeException) {
        }

        $observer->resume($entry);
        $observer->resume($entry);

        self::assertEquals([new SquareTicked('con-2026', 1, 's11', 'k1')], $this->events->delivered);

        $observer->resume(new Entry(2, EntryKind::Cancelled, $this->gesture('k1', 'booth:11'), new DateTimeImmutable('2026-11-12 20:00:00 UTC')));
        self::assertSame([], $this->bingo->card('con-2026', 1)->ticks());
    }

    public function testEveryEventHasAStableKey(): void
    {
        self::assertSame('con-2026/7/ticked/s4/k:1', new SquareTicked('con-2026', 7, 's4', 'k:1')->key());
        self::assertSame('con-2026/called/s4', new SquareCalled('con-2026', 's4')->key());
        self::assertSame('con-2026/7/won', new BingoWon('con-2026', 7, null, 'row')->key());
    }

    /**
     * @param Closure(object): bool $fails
     */
    private function start(Closure $fails, TickMode $mode = TickMode::Activity, int $failures = 1): void
    {
        $this->events = new FailingDispatcher($fails, $failures);
        $this->bingo = new Bingo(new InMemoryBingoStore(), new CardDealer(Games::SECRET), $this->events, new FrozenClock());
        $grid = new Grid(3);
        $this->bingo->create('con-2026', Games::pool(12), $grid, WinPatterns::line($grid), $mode);
    }

    private function gesture(string $key, string $object): Activity
    {
        return Activity::of($key, 'member:1', 'booth.visited', new DateTimeImmutable('2026-11-12 20:00:00 UTC'), $object);
    }
}
