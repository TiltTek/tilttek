<?php

declare(strict_types=1);

namespace Tilttek\GameEngine;

use Tilttek\GameEngine\Contract\Game;
use Tilttek\GameEngine\Exception\GameOver;
use Tilttek\GameEngine\Exception\IllegalMove;
use Tilttek\GameEngine\Exception\InvalidGame;

/**
 * Runs a table: opens it with a seed, applies a move to its state and appends it to its log, rebuilds a state by
 * replaying a log or brings a cached one up to date, takes the last move back, and gives what a seat sees. It holds no rules — the game does — and no
 * state of its own.
 */
final readonly class Engine
{
    /**
     * A new table: its log, with its seed, and its state after the game's setup.
     *
     * @param list<string>         $seats   the seats, in the order of the table
     * @param array<string, mixed> $options the options chosen for the table; the game's defaults for the others
     * @param ?string              $seed    64 hexadecimal digits; drawn from the system's secure source when null
     *
     * @throws InvalidGame
     */
    public function start(Game $game, string $gameId, array $seats, array $options = [], ?string $seed = null): Table
    {
        $definition = $game->definition();
        $this->seats($definition, $seats);
        $log = new GameLog($gameId, $definition->game, $definition->version, $seed ?? Randomness::seed(), $seats, TableOptions::resolve($definition->options, $options)->values);

        return new Table($log, $this->initial($game, $definition, $log->header()));
    }

    /**
     * The table after one more move: the move applied to the state, and appended to the log with whether it drew at
     * random.
     *
     * @throws IllegalMove
     * @throws InvalidGame
     */
    public function play(Game $game, Table $table, Move $move): Table
    {
        $step = $this->step($game, $table->log->header(), $table->state, $move);

        return new Table($table->log->with($step->move), $step->state);
    }

    /**
     * One more move at a table known by the header of its log and its current state, without its moves: the move to
     * append, with its place and whether it drew at random, and the state it leads to.
     *
     * @throws IllegalMove
     * @throws InvalidGame when the state is not the one after every move of the log
     */
    public function step(Game $game, LogHeader $header, GameState $state, Move $move): Step
    {
        if ($state->gameId !== $header->gameId || $state->sequence !== $header->sequence) {
            throw InvalidGame::because(\sprintf('The state of the table "%s" after %d moves is not the one of the log of the table "%s" after %d moves.', $state->gameId, $state->sequence, $header->gameId, $header->sequence));
        }

        $sequence = $header->sequence + 1;
        $random = new Randomness($header->seed, $sequence);
        $next = $this->apply($game, $game->definition(), $state, $move, $random);

        return new Step(new LoggedMove($sequence, $move, $random->drawn()), $next);
    }

    /**
     * The state a log leads to: the setup, then every move, each with the chance of its place in the log.
     *
     * @throws InvalidGame when the log is of another game or another version of its rules, or when a move no longer
     *                     applies or draws differently — the game is not pure, or its rules changed
     */
    public function replay(Game $game, GameLog $log): GameState
    {
        $header = $log->header();

        return $this->advance($game, $header, $this->setup($game, $header), $log->moves);
    }

    /**
     * The state of a table before its first move: the game's setup, with the chance of the place 0 of its log.
     *
     * @throws InvalidGame when the log is of another game or another version of its rules
     */
    public function setup(Game $game, LogHeader $header): GameState
    {
        return $this->initial($game, $this->definition($game, $header), $header);
    }

    /**
     * A state brought forward by the moves that follow it in the log, each with the chance of its place: the setup and
     * every move replay a log; a cached state and the moves made since bring it up to date. The moves are read as they
     * come, so that a store gives them window by window rather than all at once.
     *
     * @param iterable<LoggedMove> $moves the moves that follow the state, in order
     *
     * @throws InvalidGame when the log is of another game or another version of its rules, when a move does not follow
     *                     the state, or when it no longer applies or draws differently
     */
    public function advance(Game $game, LogHeader $header, GameState $state, iterable $moves): GameState
    {
        $definition = $this->definition($game, $header);

        if ($state->gameId !== $header->gameId || $state->game !== $header->game) {
            throw InvalidGame::because(\sprintf('The state of the table "%s" is not one of the log of the table "%s".', $state->gameId, $header->gameId));
        }

        foreach ($moves as $logged) {
            if ($logged->sequence !== $state->sequence + 1 || $logged->sequence > $header->sequence) {
                throw InvalidGame::because(\sprintf('The move %d of the table "%s" does not follow its %d moves.', $logged->sequence, $header->gameId, $state->sequence));
            }

            $random = new Randomness($header->seed, $logged->sequence);

            try {
                $state = $this->apply($game, $definition, $state, $logged->move, $random);
            } catch (IllegalMove $exception) {
                throw InvalidGame::because(\sprintf('The move %d of the table "%s" no longer applies: %s', $logged->sequence, $header->gameId, $exception->getMessage()), $exception);
            }

            if ($random->drawn() !== $logged->drew) {
                throw InvalidGame::because(\sprintf('The move %d of the table "%s" draws differently when it is replayed: the game is not pure, or its rules changed.', $logged->sequence, $header->gameId));
            }
        }

        return $state;
    }

    /**
     * The table without its last move, when the game allows it, the game is not over, the move drew nothing at random,
     * and it was the seat's own; the table itself, a null seat, takes back any such move.
     *
     * @throws GameOver    when the game is over: its seed may have been shown, which tells the draws to come
     * @throws IllegalMove
     * @throws InvalidGame
     */
    public function undo(Game $game, Table $table, ?string $seat = null): Table
    {
        $this->checkUndo($game, $table->state, $table->log->last(), $seat);
        $log = $table->log->withoutLast();

        return new Table($log, $this->replay($game, $log));
    }

    /**
     * Whether the last move of a table may be taken back, by a seat or, when it is null, by the table itself: refused
     * once the game is over, since its seed may then be shown and tell the draws to come, when the game takes no move
     * back, when there is none, when it drew at random, or when it is another seat's.
     *
     * @param GameState   $state the current state of the table
     * @param ?LoggedMove $last  the last move of its log
     *
     * @throws GameOver
     * @throws IllegalMove
     */
    public function checkUndo(Game $game, GameState $state, ?LoggedMove $last, ?string $seat = null): void
    {
        if ($state->isOver()) {
            throw GameOver::table($state->gameId);
        }

        $reason = match (true) {
            !$game->definition()->undo => 'The game does not take moves back.',
            !$last instanceof LoggedMove => 'The table has no move to take back.',
            $last->drew => 'The last move drew at random: what it revealed cannot be taken back.',
            null !== $seat && $last->move->seat !== $seat => \sprintf('The last move is not the seat "%s"\'s to take back.', $seat),
            default => null,
        };

        if (null !== $reason) {
            throw IllegalMove::because($reason);
        }
    }

    /**
     * The state a log leads to, once its seed is shown to be the one the table committed to when it opened: the log
     * replayed, every move drawing its chance from that seed.
     *
     * @param string $commitment the commitment the players were shown, `GameLog::commitment()`
     *
     * @throws InvalidGame when the seed is not the one committed to, or when the log cannot be replayed
     */
    public function verify(Game $game, GameLog $log, string $commitment): GameState
    {
        if (!hash_equals($log->commitment(), $commitment)) {
            throw InvalidGame::because(\sprintf('The seed of the table "%s" is not the one it committed to.', $log->gameId));
        }

        return $this->replay($game, $log);
    }

    /**
     * What a seat sees of a state, shaped by the preferences of its player; what the audience sees when the seat is null.
     *
     * @param array<string, mixed> $preferences the values the player chose; the game's defaults for the others
     *
     * @return array<string, mixed>
     *
     * @throws IllegalMove when the seat is not at the table
     * @throws InvalidGame when a preference is not one the game offers, or its value one it allows
     */
    public function view(Game $game, GameState $state, ?string $seat = null, array $preferences = []): array
    {
        $this->seated($state, $seat);

        return $game->view($state, $seat, Preferences::resolve($game->definition()->preferences, $preferences));
    }

    /**
     * The game's definition, once the log is shown to be of that game and that version of its rules.
     *
     * @throws InvalidGame
     */
    private function definition(Game $game, LogHeader $header): GameDefinition
    {
        $definition = $game->definition();

        if ($header->game !== $definition->game || $header->version !== $definition->version) {
            throw InvalidGame::because(\sprintf('The log of the table "%s" is of the game "%s" version "%s", not "%s" version "%s".', $header->gameId, $header->game, $header->version, $definition->game, $definition->version));
        }

        return $definition;
    }

    private function initial(Game $game, GameDefinition $definition, LogHeader $log): GameState
    {
        $phase = $definition->initialPhase();
        $state = new GameState($log->gameId, $definition->game, 0, $log->seats, TableOptions::resolve($definition->options, $log->options), $phase->name, Turn::start($phase->turnOrder, $log->seats, []));

        return $state->withData($game->setup($state, new Randomness($log->seed, 0)));
    }

    private function apply(Game $game, GameDefinition $definition, GameState $state, Move $move, Randomness $random): GameState
    {
        if ($state->isOver()) {
            throw IllegalMove::because(\sprintf('The game at the table "%s" is over.', $state->gameId));
        }

        $this->seated($state, $move->seat);
        $inTurn = $move->isEngine() ? \in_array($move->type, [Move::END_TURN, Move::DELAY], true) : !\in_array($move->type, $definition->outOfTurn, true);

        if ($inTurn && !$state->turn->allows($move->seat)) {
            throw IllegalMove::because(\sprintf('It is not the turn of the seat "%s".', $move->seat));
        }

        if (!$game->allows($state, $move)) {
            throw IllegalMove::because(\sprintf('The game refuses the move "%s" now.', $move->type));
        }

        $next = match ($move->type) {
            Move::END_TURN => $this->endTurn($definition, $state, $move),
            Move::TRANSITION => $this->transition($game, $definition, $state, $move, $random),
            Move::INITIATIVE => $this->initiative($state, $move),
            Move::DELAY => $this->delay($state, $move),
            default => $move->isEngine()
                ? throw IllegalMove::because(\sprintf('The engine has no move "%s".', $move->type)) : $this->move($game, $definition, $state, $move, $random),
        };
        $next = $next->with($state->sequence + 1, $next->phase, $next->turn, $next->initiative);

        return $next->with($next->sequence, $next->phase, $next->turn, $next->initiative, $this->outcome($game, $definition, $next));
    }

    /**
     * The game's outcome, its statistics checked against those it declares.
     *
     * @throws InvalidGame when the game reports a statistic it does not declare, or one for a seat not at the table
     */
    private function outcome(Game $game, GameDefinition $definition, GameState $state): ?Outcome
    {
        $outcome = $game->outcome($state);

        if (!$outcome instanceof Outcome) {
            return null;
        }

        foreach (array_keys($outcome->statistics->table) as $name) {
            if (!$definition->statistic($name, StatisticScope::Table) instanceof Statistic) {
                throw InvalidGame::because(\sprintf('The game "%s" reports the statistic of the table "%s", which it does not declare.', $definition->game, $name));
            }
        }

        foreach ($outcome->statistics->seats as $seat => $statistics) {
            if (!\in_array($seat, $state->seats, true)) {
                throw InvalidGame::because(\sprintf('The game "%s" reports statistics of the seat "%s", which is not at the table.', $definition->game, $seat));
            }

            foreach (array_keys($statistics) as $name) {
                if (!$definition->statistic($name, StatisticScope::Seat) instanceof Statistic) {
                    throw InvalidGame::because(\sprintf('The game "%s" reports the statistic of a seat "%s", which it does not declare.', $definition->game, $name));
                }
            }
        }

        return $outcome;
    }

    private function endTurn(GameDefinition $definition, GameState $state, Move $move): GameState
    {
        if (TurnOrder::Free === $definition->phase($state->phase)->turnOrder) {
            throw IllegalMove::because(\sprintf('The phase "%s" has no turns to end.', $state->phase));
        }

        return $state->with($state->sequence, $state->phase, $state->turn->end($move->seat), $state->initiative);
    }

    private function transition(Game $game, GameDefinition $definition, GameState $state, Move $move, Randomness $random): GameState
    {
        $transition = $move->text('transition') ?? throw IllegalMove::because('A change of phase names its transition: {"transition": "…"}.');
        $phase = $definition->phase($definition->machine->apply($state->phase, $transition));
        $entered = $state->with($state->sequence, $phase->name, Turn::start($phase->turnOrder, $state->seats, $state->initiative), $state->initiative);

        return $entered->withData($game->enter($entered, $random));
    }

    private function initiative(GameState $state, Move $move): GameState
    {
        $scores = $move->payload['scores'] ?? null;
        $initiative = $state->initiative;

        if (!\is_array($scores) || [] === $scores) {
            throw IllegalMove::because('The initiative gives scores by seat: {"scores": {"seat": 12}}.');
        }

        foreach ($scores as $seat => $score) {
            if (!\is_int($score) || !\in_array($seat, $state->seats, true)) {
                throw IllegalMove::because(\sprintf('The initiative of "%s" is not a whole number for a seat of the table.', $seat));
            }

            $initiative[$seat] = $score;
        }

        $turn = TurnOrder::Initiative === $state->turn->kind ? $state->turn->reordered($state->seats, $initiative) : $state->turn;

        return $state->with($state->sequence, $state->phase, $turn, $initiative);
    }

    private function delay(GameState $state, Move $move): GameState
    {
        $after = $move->text('after');
        $turn = TurnOrder::Initiative === $state->turn->kind && null !== $move->seat && null !== $after ? $state->turn->delayed($after) : null;

        if (!$turn instanceof Turn) {
            throw IllegalMove::because('A seat delays its own turn, in a phase by initiative, after a seat that acts later: {"after": "seat"}.');
        }

        return $state->with($state->sequence, $state->phase, $turn, $state->initiative);
    }

    private function move(Game $game, GameDefinition $definition, GameState $state, Move $move, Randomness $random): GameState
    {
        $data = $game->play($state, $move, $random);
        $turn = \in_array($move->type, $definition->outOfTurn, true) ? $state->turn : $state->turn->counted($move->seat, $definition->phase($state->phase)->movesPerTurn);

        return $state->withData($data)->with($state->sequence, $state->phase, $turn, $state->initiative);
    }

    /**
     * @param list<string> $seats
     *
     * @throws InvalidGame
     */
    private function seats(GameDefinition $definition, array $seats): void
    {
        $count = \count($seats);

        if ($count < $definition->minimumSeats || $count > $definition->maximumSeats) {
            throw InvalidGame::because(\sprintf('The game "%s" seats %d to %d players, not %d.', $definition->game, $definition->minimumSeats, $definition->maximumSeats, $count));
        }

        foreach ($seats as $seat) {
            Name::identifier($seat, 'a seat');
        }

        if (\count(array_unique($seats)) !== $count) {
            throw InvalidGame::because('A seat is given twice.');
        }
    }

    private function seated(GameState $state, ?string $seat): void
    {
        if (null !== $seat && !\in_array($seat, $state->seats, true)) {
            throw IllegalMove::because(\sprintf('The seat "%s" is not at the table "%s".', $seat, $state->gameId));
        }
    }
}
