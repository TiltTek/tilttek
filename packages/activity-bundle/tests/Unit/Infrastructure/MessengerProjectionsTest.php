<?php

declare(strict_types=1);

namespace Tilttek\ActivityBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Filter;
use Tilttek\Activity\Journal;
use Tilttek\Activity\Log\InMemoryActivityLog;
use Tilttek\Activity\Projection\InMemoryCheckpoints;
use Tilttek\Activity\Projection\Projections;
use Tilttek\Activity\Projection\Projector;
use Tilttek\ActivityBundle\Infrastructure\Messenger\CatchUpProjection;
use Tilttek\ActivityBundle\Infrastructure\Messenger\CatchUpProjectionHandler;
use Tilttek\ActivityBundle\Infrastructure\Messenger\MessengerProjections;
use Tilttek\ActivityBundle\Tests\Unit\Fixture\Clock;
use Tilttek\ActivityBundle\Tests\Unit\Fixture\Counter;

#[CoversClass(MessengerProjections::class)]
#[CoversClass(CatchUpProjection::class)]
#[CoversClass(CatchUpProjectionHandler::class)]
final class MessengerProjectionsTest extends TestCase
{
    public function testEachNewEntrySendsAMessageToTheProjectionsThatReadIt(): void
    {
        [$journal, $bus, $games, $all] = $this->kit();

        $journal->record($this->activity('k1', 'game.played'));
        $journal->record($this->activity('k2', 'news.commented'));
        $journal->record($this->activity('k1', 'game.played'));
        $journal->cancel('k1');

        self::assertEquals([
            new CatchUpProjection('all', 1),
            new CatchUpProjection('games', 1),
            new CatchUpProjection('all', 2),
            new CatchUpProjection('all', 3),
            new CatchUpProjection('games', 3),
        ], $bus->messages);
        self::assertSame([], $games->applied);
        self::assertSame([], $all->applied);
    }

    public function testAMessageCatchesItsProjectionUpOnce(): void
    {
        [$journal, $bus, $games, $all, $handler] = $this->kit();
        $journal->record($this->activity('k1', 'game.played'));
        $journal->record($this->activity('k2', 'news.commented'));
        $journal->record($this->activity('k3', 'game.played'));

        self::assertSame(2, $handler(new CatchUpProjection('games', 1)));
        self::assertSame([1, 3], $games->applied);
        self::assertSame(0, $handler(new CatchUpProjection('games', 3)));
        self::assertSame(0, $handler(new CatchUpProjection('games', 1)));
        self::assertSame([1, 3], $games->applied);
        self::assertSame([], $all->applied);
        self::assertSame(3, $handler(new CatchUpProjection('all', 2)));
        self::assertSame(0, $handler(new CatchUpProjection('removed', 3)));
        self::assertCount(5, $bus->messages);

        // A message whose entry the projection has seen leaves a later entry to the later message.
        $journal->record($this->activity('k4', 'game.played'));

        self::assertSame(0, $handler(new CatchUpProjection('games', 3)));
        self::assertSame([1, 3], $games->applied);
        self::assertSame(1, $handler(new CatchUpProjection('games', 4)));
        self::assertSame([1, 3, 4], $games->applied);
    }

    /**
     * @return array{Journal, object{messages: list<object>}&MessageBusInterface, Counter, Counter, CatchUpProjectionHandler}
     */
    private function kit(): array
    {
        $bus = new class implements MessageBusInterface {
            /**
             * @var list<object>
             */
            public array $messages = [];

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->messages[] = $message;

                return new Envelope($message, $stamps);
            }
        };
        $log = new InMemoryActivityLog();
        $checkpoints = new InMemoryCheckpoints();
        $games = new Counter('games', Filter::all()->verbs('game.*'));
        $all = new Counter('all');
        $projections = new Projections([$games, $all]);
        $journal = new Journal($log, new Clock('2026-11-07 12:00:00 UTC'), observers: [new MessengerProjections($bus, $projections)]);

        return [$journal, $bus, $games, $all, new CatchUpProjectionHandler(new Projector($log, $checkpoints), $projections, $checkpoints)];
    }

    private function activity(string $key, string $verb): Activity
    {
        return Activity::of($key, 'member:1', $verb, new DateTimeImmutable('2026-11-06 20:00:00 UTC'));
    }
}
