<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Tests\Unit\Application;

use ArrayIterator;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Tilttek\DiceTower\Application\Message\RelayDrop;
use Tilttek\DiceTower\Application\RelayDropHandler;
use Tilttek\DiceTower\Application\TowerEntry;
use Tilttek\DiceTower\Application\TowerRelays;
use Tilttek\DiceTower\Tests\Unit\Fixture\RecordingLogger;
use Tilttek\DiceTower\Tests\Unit\Fixture\RecordingRelay;
use Tilttek\DiceTower\Tests\Unit\Fixture\Tower;

#[CoversClass(TowerRelays::class)]
#[CoversClass(RelayDrop::class)]
#[CoversClass(RelayDropHandler::class)]
final class TowerRelaysTest extends TestCase
{
    public function testEachRelayReceivesTheEntryAtOnceAndAFailureStopsNoOther(): void
    {
        $failing = new RecordingRelay(failing: true);
        $relay = new RecordingRelay();
        $logger = new RecordingLogger();
        $entry = $this->entry();

        new TowerRelays(new ArrayIterator([$failing, $relay]), logger: $logger)->dispatch($entry);

        self::assertSame([$entry], $relay->entries);
        self::assertCount(1, $logger->records);
        self::assertSame(LogLevel::WARNING, $logger->records[0][0]);
        self::assertSame('The entry 4 of the tower of "table:7" could not be relayed by '.RecordingRelay::class.': The channel is closed.', $logger->records[0][1]);
        self::assertSame('The channel is closed.', $logger->records[0][2]['message']);
        self::assertInstanceOf(RuntimeException::class, $logger->records[0][2]['exception']);
    }

    public function testAFailureWithoutLoggerIsSwallowed(): void
    {
        $relay = new RecordingRelay();
        new TowerRelays([new RecordingRelay(failing: true), $relay])->relay($this->entry());

        self::assertCount(1, $relay->entries);
    }

    public function testThroughABusOnlyTheTableAndThePositionTravel(): void
    {
        $bus = $this->bus();
        $relay = new RecordingRelay();

        new TowerRelays([$relay], $bus)->dispatch($this->entry());

        self::assertEquals([new RelayDrop('table:7', 4)], $bus->messages);
        self::assertSame([], $relay->entries);
    }

    public function testWithoutRelayNothingIsSent(): void
    {
        $bus = $this->bus();
        new TowerRelays([], $bus)->dispatch($this->entry());

        self::assertSame([], $bus->messages);
    }

    public function testTheHandlerReadsTheEntryFromTheLog(): void
    {
        $relay = new RecordingRelay();
        $tower = new Tower();
        $entry = $tower->tower->roll(Tower::table(), '1d6');
        $handler = new RelayDropHandler($tower->tower, new TowerRelays([$relay]));

        $handler(new RelayDrop('table:7', $entry->position));
        $handler(new RelayDrop('table:7', 99));
        $handler(new RelayDrop('table:8', $entry->position));

        self::assertEquals([$entry], $relay->entries);
    }

    private function entry(): TowerEntry
    {
        return new TowerEntry(4, 'table:7', 'abc', null, new DateTimeImmutable('2026-11-12'), []);
    }

    /**
     * @return MessageBusInterface&object{messages: list<object>}
     */
    private function bus(): MessageBusInterface
    {
        return new class implements MessageBusInterface {
            /** @var list<object> */
            public array $messages = [];

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->messages[] = $message;

                return new Envelope($message, $stamps);
            }
        };
    }
}
