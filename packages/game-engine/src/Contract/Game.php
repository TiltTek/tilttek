<?php

declare(strict_types=1);

namespace Tilttek\GameEngine\Contract;

use Tilttek\GameEngine\Exception\IllegalMove;
use Tilttek\GameEngine\GameDefinition;
use Tilttek\GameEngine\GameState;
use Tilttek\GameEngine\Move;
use Tilttek\GameEngine\Outcome;
use Tilttek\GameEngine\Preferences;
use Tilttek\GameEngine\Randomness;

/**
 * A game the engine runs: its rules, which the engine never holds. Every method is pure — it reads the state, the move
 * and the randomness it is given and nothing else, no clock, no storage —, so that replaying a log gives the same
 * state.
 */
interface Game
{
    /**
     * The game's phases, transitions, seats, options and rules about turns; the same at every call.
     */
    public function definition(): GameDefinition;

    /**
     * The game's data when a table opens, in its first phase.
     *
     * @return array<string, mixed>
     */
    public function setup(GameState $state, Randomness $random): array;

    /**
     * Whether the game allows a move — the engine's own included: a phase change, the initiative — before the engine
     * applies it. The engine has already checked the turn.
     */
    public function allows(GameState $state, Move $move): bool;

    /**
     * The game's data after one of its own moves.
     *
     * @return array<string, mixed>
     *
     * @throws IllegalMove when the move breaks the rules
     */
    public function play(GameState $state, Move $move, Randomness $random): array;

    /**
     * The game's data when a table enters a phase by a transition; `$state` is already in that phase.
     *
     * @return array<string, mixed>
     */
    public function enter(GameState $state, Randomness $random): array;

    /**
     * How the game ended, with the statistics it declares, or null while it goes on; read after every move.
     */
    public function outcome(GameState $state): ?Outcome;

    /**
     * What one seat sees of the state — the game's data with the others' secrets left out —, shaped by the preferences
     * of its player; what the table's audience sees when the seat is null. The preferences never change what a seat may
     * see, only how it is shown.
     *
     * @return array<string, mixed>
     */
    public function view(GameState $state, ?string $seat, Preferences $preferences): array;
}
