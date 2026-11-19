<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom\Tests\Benchmark;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\EscapeRoom\EscapeRooms;
use Tilttek\EscapeRoom\Game;
use Tilttek\EscapeRoom\Leaderboard;
use Tilttek\EscapeRoom\Room;
use Tilttek\EscapeRoom\RoomResult;
use Tilttek\PuzzleBuilder\Answer\AnswerKey;
use Tilttek\PuzzleBuilder\Hint;
use Tilttek\PuzzleBuilder\Limiter\InMemoryGuessLimiter;
use Tilttek\PuzzleBuilder\Puzzle;
use Tilttek\PuzzleBuilder\PuzzleEngine;

/**
 * What a room costs at its busiest: a guess and the timer of a game of 50 chained puzzles with 49 solved and 100 hints
 * revealed, a stored game read back, and the public leaderboard of 10 000 results.
 */
final readonly class EscapeRoomsBench
{
    private Room $room;

    private EscapeRooms $rooms;

    private Game $game;

    /** @var array<array-key, mixed> */
    private array $stored;

    /** @var list<RoomResult> */
    private array $results;

    public function __construct()
    {
        $puzzles = [];

        for ($index = 1; $index <= 50; ++$index) {
            $puzzles[] = new Puzzle('p'.$index, 'Puzzle '.$index, new AnswerKey(['answer '.$index]), [new Hint('First'), new Hint('Second')]);
        }

        $this->room = Room::chain('long', 'The long room', $puzzles, 86_400, 60, 300, 1, 10);
        $this->rooms = new EscapeRooms(new PuzzleEngine(new InMemoryGuessLimiter(1_000_000, 60)));
        $game = $this->rooms->start($this->room, ResourceReference::of('team', '1'));

        for ($index = 1; $index < 50; ++$index) {
            $game = $this->rooms->requestHint($this->room, $game, 'p'.$index)->game;
            $game = $this->rooms->requestHint($this->room, $game, 'p'.$index)->game;
            $game = $this->rooms->guess($this->room, $game, 'p'.$index, 'answer '.$index)->game;
        }

        $this->game = $game;
        $this->stored = $game->toArray();
        $at = new DateTimeImmutable('2026-11-13 20:00 UTC');
        $this->results = array_map(static fn (int $index): RoomResult => new RoomResult('long', ResourceReference::of('team', (string) $index), null, ($index * 7_919) % 3_600, 0, 0, $index % 5, 0, $at, 0 === $index % 3), range(1, 10_000));
    }

    public function benchGuessTheLastOf50Puzzles(): void
    {
        $this->rooms->guess($this->room, $this->game, 'p50', 'answer 50');
    }

    public function benchReadTheTimer(): void
    {
        $this->rooms->timer($this->room, $this->game);
    }

    public function benchReadAStoredGameBack(): void
    {
        Game::fromArray($this->room, $this->stored);
    }

    public function benchRank10000Results(): void
    {
        Leaderboard::rank('long', $this->results, 100);
    }
}
