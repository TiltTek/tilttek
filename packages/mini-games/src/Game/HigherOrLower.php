<?php

declare(strict_types=1);

namespace Tilttek\MiniGames\Game;

use Tilttek\GameEngine\Exception\IllegalMove;
use Tilttek\GameEngine\Exception\InvalidGame;
use Tilttek\GameEngine\GameDefinition;
use Tilttek\GameEngine\GameState;
use Tilttek\GameEngine\Move;
use Tilttek\GameEngine\Outcome;
use Tilttek\GameEngine\Phase;
use Tilttek\GameEngine\Preferences;
use Tilttek\GameEngine\Randomness;
use Tilttek\GameEngine\Statistic;
use Tilttek\GameEngine\Statistics;
use Tilttek\MiniGames\Cell;
use Tilttek\MiniGames\Contract\MiniGame;
use Tilttek\MiniGames\Control;
use Tilttek\MiniGames\ScoreOrder;
use Tilttek\MiniGames\Scoring;
use Tilttek\MiniGames\ShareGrid;

/**
 * Higher or Lower, the old guessing game: a number from 1 to 100 is shown; call whether the next one is higher or
 * lower, ten times in a row. The numbers are all different. The more right calls, the better.
 *
 * Its one move is `higher-or-lower.call` (`{call: "higher"}` or `{call: "lower"}`). Ten right calls win; a wrong call
 * ends the game. Either way, the right calls are reported as the statistic `score` of the seat.
 */
final readonly class HigherOrLower implements MiniGame
{
    public const string NAME = 'higher-or-lower';

    public const string CALL = 'higher-or-lower.call';

    /** The calls of a game. */
    public const int CALLS = 10;

    private GameDefinition $definition;

    public function __construct()
    {
        $this->definition = new GameDefinition(
            self::NAME,
            [new Phase('calling')],
            minimumSeats: 1,
            maximumSeats: 1,
            statistics: [Statistic::seat('score')],
        );
    }

    public function definition(): GameDefinition
    {
        return $this->definition;
    }

    public function scoring(): Scoring
    {
        return new Scoring(ScoreOrder::HigherIsBetter);
    }

    public function controls(): array
    {
        return [new Control('higher', ['ArrowUp', 'h']), new Control('lower', ['ArrowDown', 'l'])];
    }

    public function setup(GameState $state, Randomness $random): array
    {
        return ['numbers' => \array_slice($random->shuffle(range(1, 100)), 0, self::CALLS + 1), 'calls' => []];
    }

    public function allows(GameState $state, Move $move): bool
    {
        return self::CALL === $move->type && null !== $move->seat;
    }

    public function play(GameState $state, Move $move, Randomness $random): array
    {
        [$numbers, $calls] = $this->read($state->data);
        $call = $move->text('call');

        if ('higher' !== $call && 'lower' !== $call) {
            throw IllegalMove::because('A call is "higher" or "lower".');
        }

        $shown = $numbers[\count($calls)];
        $next = $numbers[\count($calls) + 1];

        return ['numbers' => $numbers, 'calls' => [...$calls, 'higher' === $call ? $next > $shown : $next < $shown]];
    }

    public function enter(GameState $state, Randomness $random): array
    {
        return $state->data;
    }

    public function outcome(GameState $state): ?Outcome
    {
        [, $calls] = $this->read($state->data);
        $right = \count(array_filter($calls));
        $statistics = new Statistics([], [$state->seats[0] => ['score' => $right]]);

        if ($right < \count($calls)) {
            return Outcome::ended('missed', $statistics);
        }

        return self::CALLS === $right ? Outcome::won($state->seats, 'all-called', $statistics) : null;
    }

    /**
     * The numbers shown so far — never the next one —, whether each call was right, and the calls left.
     */
    public function view(GameState $state, ?string $seat, Preferences $preferences): array
    {
        [$numbers, $calls] = $this->read($state->data);

        return [
            'shown' => \array_slice($numbers, 0, \count($calls) + 1),
            'calls' => $calls,
            'left' => $state->isOver() ? 0 : self::CALLS - \count($calls),
        ];
    }

    /**
     * One row: a green square per right call, a black one for the wrong call, a white one per call not played; never
     * the direction of a call, which would give the numbers away.
     */
    public function share(GameState $state): ShareGrid
    {
        [, $calls] = $this->read($state->data);
        $row = array_map(static fn (bool $right): Cell => $right ? Cell::Hit : Cell::Miss, $calls);

        return new ShareGrid([[...$row, ...array_pad([], self::CALLS - \count($calls), Cell::Empty)]], \count(array_filter($calls)).'/'.self::CALLS);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{non-empty-list<int>, list<bool>}
     *
     * @throws InvalidGame when the data is not eleven numbers and up to ten calls
     */
    private function read(array $data): array
    {
        $numbers = $data['numbers'] ?? null;
        $calls = $data['calls'] ?? null;

        if (!\is_array($numbers) || !\is_array($calls) || \count($numbers) !== self::CALLS + 1 || \count($calls) > self::CALLS) {
            throw InvalidGame::because('The data of Higher or Lower is eleven numbers and up to ten calls.');
        }

        $read = [];

        foreach ($numbers as $number) {
            $read[] = \is_int($number) ? $number : throw InvalidGame::because('A number of Higher or Lower is a whole number.');
        }

        $called = [];

        foreach ($calls as $call) {
            $called[] = \is_bool($call) ? $call : throw InvalidGame::because('A call of Higher or Lower is right or wrong.');
        }

        return [$read, $called];
    }
}
