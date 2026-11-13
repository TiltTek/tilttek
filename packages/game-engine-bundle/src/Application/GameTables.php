<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Application;

use Closure;
use Tilttek\GameEngine\Exception\ConcurrentMove;
use Tilttek\GameEngine\Exception\GameInProgress;
use Tilttek\GameEngine\Exception\GameOver;
use Tilttek\GameEngine\Exception\IllegalMove;
use Tilttek\GameEngine\Exception\InvalidGame;
use Tilttek\GameEngine\Exception\UnknownTable;
use Tilttek\GameEngine\GameLog;
use Tilttek\GameEngine\GameState;
use Tilttek\GameEngine\Move;
use Tilttek\GameEngine\Tables;
use Tilttek\GameEngineBundle\Application\Contract\TableLock;
use Tilttek\GameEngineBundle\Application\Exception\IdentifyingSeat;

/**
 * The tables of the application, as the bundle wires them: the library's `Tables` on the configured log store, state
 * cache and publisher, each move and each move taken back made while holding the lock of its table when one is
 * configured. It keeps no state of its own between two requests.
 */
final readonly class GameTables
{
    public function __construct(
        private Tables $tables,
        private ?TableLock $lock = null,
    ) {
    }

    /**
     * Opens a table of a game.
     *
     * @param list<string>         $seats   opaque identifiers of the table, never one that names a person
     * @param array<string, mixed> $options
     *
     * @throws IdentifyingSeat when a seat looks like an email address or a reference to a member
     * @throws InvalidGame
     * @throws UnknownTable    when no game has that name
     */
    public function open(string $game, string $gameId, array $seats, array $options = [], ?string $seed = null): GameState
    {
        SeatIdentifiers::check($seats);

        return $this->hold($gameId, fn (): GameState => $this->tables->open($game, $gameId, $seats, $options, $seed));
    }

    /**
     * Makes a move at a table.
     *
     * @param ?int $expected the moves the player saw at the table; a move made since is refused with `ConcurrentMove`
     *
     * @throws IllegalMove
     * @throws ConcurrentMove
     * @throws InvalidGame
     * @throws UnknownTable
     */
    public function play(string $gameId, Move $move, ?int $expected = null): GameState
    {
        return $this->hold($gameId, fn (): GameState => $this->tables->play($gameId, $move, $expected));
    }

    /**
     * Takes the last move of a table back.
     *
     * @throws GameOver       when the game is over: its log, seed included, may have been shown
     * @throws IllegalMove
     * @throws ConcurrentMove
     * @throws UnknownTable
     */
    public function undo(string $gameId, ?string $seat = null): GameState
    {
        return $this->hold($gameId, fn (): GameState => $this->tables->undo($gameId, $seat));
    }

    /**
     * @throws UnknownTable
     */
    public function state(string $gameId): GameState
    {
        return $this->tables->state($gameId);
    }

    /**
     * What a seat sees of a table, shaped by its player's preferences; what its audience sees when the seat is null.
     *
     * @param array<string, mixed> $preferences
     *
     * @return array<string, mixed>
     *
     * @throws IllegalMove  when the seat is not at the table
     * @throws InvalidGame  when a preference is refused
     * @throws UnknownTable
     */
    public function view(string $gameId, ?string $seat = null, array $preferences = []): array
    {
        return $this->tables->view($gameId, $seat, $preferences);
    }

    /**
     * The log of a table, seed included: what the players are shown once the game is over, never before.
     *
     * @throws UnknownTable
     */
    public function log(string $gameId): GameLog
    {
        return $this->tables->log($gameId);
    }

    /**
     * The log of a table whose game is over, seed included, checked to be the one of its finished state.
     *
     * @throws GameInProgress when the game goes on
     * @throws ConcurrentMove when the log read is not the one of the finished state
     * @throws InvalidGame
     * @throws UnknownTable
     */
    public function finishedLog(string $gameId): GameLog
    {
        return $this->tables->finishedLog($gameId);
    }

    /**
     * The commitment to the seed of a table, which its players may be shown from the opening, read without its moves.
     *
     * @throws UnknownTable
     */
    public function commitment(string $gameId): string
    {
        return $this->tables->commitment($gameId);
    }

    /**
     * @template T
     *
     * @param Closure(): T $work
     *
     * @return T
     */
    private function hold(string $gameId, Closure $work): mixed
    {
        return $this->lock instanceof TableLock ? $this->lock->hold($gameId, $work) : $work();
    }
}
