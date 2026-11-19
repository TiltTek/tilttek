<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Tests\Unit\Fixture;

use Symfony\Component\Clock\MockClock;
use Tilttek\EscapeRoom\EscapeRooms;
use Tilttek\EscapeRoomBundle\Application\ConfiguredRoomCatalog;
use Tilttek\EscapeRoomBundle\Application\Contract\GameStore;
use Tilttek\EscapeRoomBundle\Application\Contract\ResultStore;
use Tilttek\EscapeRoomBundle\Application\Contract\ScreenStore;
use Tilttek\EscapeRoomBundle\Application\GameDesk;
use Tilttek\EscapeRoomBundle\Infrastructure\Memory\InMemoryGameStore;
use Tilttek\EscapeRoomBundle\Infrastructure\Memory\InMemoryResultStore;
use Tilttek\EscapeRoomBundle\Infrastructure\Memory\InMemoryScreenStore;
use Tilttek\PuzzleBuilder\Limiter\InMemoryGuessLimiter;
use Tilttek\PuzzleBuilder\PuzzleEngine;

/**
 * A desk of the rooms of the tests on a clock that does not move, with stores in memory unless others are given.
 */
final readonly class Desk
{
    public MockClock $clock;

    public Recorder $events;

    public RecordingPublisher $publisher;

    public GameDesk $desk;

    public function __construct(
        public GameStore $games = new InMemoryGameStore(),
        public ResultStore $results = new InMemoryResultStore(),
        public ScreenStore $screens = new InMemoryScreenStore(),
        bool $publishing = true,
    ) {
        $this->clock = new MockClock('2026-11-14 20:00:00 UTC');
        $this->events = new Recorder();
        $this->publisher = new RecordingPublisher();
        $engine = new PuzzleEngine(new InMemoryGuessLimiter(100, 60, $this->clock), $this->events, $this->clock);
        $this->desk = new GameDesk(
            new EscapeRooms($engine, $this->events, $this->clock),
            new ConfiguredRoomCatalog(Rooms::config()),
            $this->games,
            $this->results,
            $this->screens,
            $engine,
            $this->clock,
            $publishing ? $this->publisher : null,
        );
    }
}
