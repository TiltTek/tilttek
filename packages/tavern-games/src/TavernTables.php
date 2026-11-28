<?php

declare(strict_types=1);

namespace Tilttek\TavernGames;

use Tilttek\GameEngine\Exception\ConcurrentMove;
use Tilttek\GameEngine\Exception\IllegalMove;
use Tilttek\GameEngine\Exception\InvalidGame;
use Tilttek\GameEngine\Exception\UnknownTable;
use Tilttek\GameEngine\GameState;
use Tilttek\GameEngine\Move;
use Tilttek\GameEngine\Outcome;
use Tilttek\GameEngine\Tables;
use Tilttek\TavernGames\Contract\TavernGame;
use Tilttek\TavernGames\Contract\TurnTimer;
use Tilttek\TavernGames\Exception\InvalidTavernGame;
use Tilttek\TavernGames\Store\NullTurnTimer;

/**
 * The tavern tables: opens them, plays their moves, tells the turn timer whose turn starts, plays the timeout of a turn
 * when the timer runs out, and shows each seat its view and its ending. It keeps no state between two calls; the
 * `Tables` it is given keeps the states it read, until its `reset()`.
 */
final readonly class TavernTables
{
    /**
     * @param Tables $tables the engine's tables, which know the same games as `$games`
     */
    public function __construct(
        private Tables $tables,
        private TavernGames $games,
        private TurnTimer $timer = new NullTurnTimer(),
    ) {
    }

    /**
     * Opens a table of a tavern game; its first seat plays first.
     *
     * @param list<string>         $seats
     * @param array<string, mixed> $options
     *
     * @throws InvalidTavernGame when no tavern game has that name
     * @throws InvalidGame
     * @throws UnknownTable
     */
    public function open(string $game, string $tableId, array $seats, array $options = [], ?string $seed = null): GameState
    {
        $this->games->get($game);

        return $this->told($this->tables->open($game, $tableId, $seats, $options, $seed));
    }

    /**
     * A move at a table.
     *
     * @param ?int $expected the moves the seat saw; a move made since is refused with `ConcurrentMove`
     *
     * @throws IllegalMove
     * @throws ConcurrentMove
     * @throws InvalidGame
     * @throws UnknownTable
     * @throws InvalidTavernGame
     */
    public function play(string $tableId, Move $move, ?int $expected = null): GameState
    {
        return $this->told($this->tables->play($tableId, $move, $expected));
    }

    /**
     * The timeout of a seat's turn, as the turn timer reports it: played by the table when the seat still plays after
     * the same number of moves, ignored otherwise — the seat played in time, or the game is over.
     *
     * @param int $sequence the number of moves the timer was told when the turn started
     *
     * @throws InvalidGame
     * @throws UnknownTable
     * @throws InvalidTavernGame
     */
    public function timeout(string $tableId, string $seat, int $sequence): GameState
    {
        $state = $this->tables->state($tableId);

        if ($state->sequence !== $sequence || $this->games->get($state->game)->toAct($state) !== $seat) {
            return $state;
        }

        try {
            return $this->play($tableId, new Move(TavernGame::TIMEOUT, null, ['seat' => $seat]), $sequence);
        } catch (ConcurrentMove) {
            // The seat played between the read and the timeout: its move stands.
            return $this->tables->state($tableId);
        }
    }

    /**
     * The whole state of a table, secrets included: for the server only, a view for a seat.
     *
     * @throws InvalidGame
     * @throws ConcurrentMove
     * @throws UnknownTable
     */
    public function state(string $tableId): GameState
    {
        return $this->tables->state($tableId);
    }

    /**
     * What a seat sees of a table — the audience, with no seat.
     *
     * @return array<string, mixed>
     *
     * @throws IllegalMove
     * @throws InvalidGame
     * @throws ConcurrentMove
     * @throws UnknownTable
     */
    public function view(string $tableId, ?string $seat = null): array
    {
        return $this->tables->view($tableId, $seat);
    }

    /**
     * How the game ended for a seat — the audience, with no seat —, or null while it goes on.
     *
     * @throws InvalidGame
     * @throws ConcurrentMove
     * @throws UnknownTable
     */
    public function ending(string $tableId, ?string $seat = null): ?Ending
    {
        $outcome = $this->tables->state($tableId)->outcome;

        return $outcome instanceof Outcome ? Ending::of($outcome, $seat) : null;
    }

    /**
     * @throws InvalidTavernGame
     */
    private function told(GameState $state): GameState
    {
        $seat = $this->games->get($state->game)->toAct($state);

        if (null === $seat) {
            $this->timer->stopped($state->gameId);
        } else {
            $this->timer->started($state->gameId, $seat, $state->sequence);
        }

        return $state;
    }
}
