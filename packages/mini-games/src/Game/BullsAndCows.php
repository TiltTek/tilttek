<?php

declare(strict_types=1);

namespace Tilttek\MiniGames\Game;

use Tilttek\GameEngine\Exception\IllegalMove;
use Tilttek\GameEngine\Exception\InvalidGame;
use Tilttek\GameEngine\GameDefinition;
use Tilttek\GameEngine\GameState;
use Tilttek\GameEngine\Move;
use Tilttek\GameEngine\Option;
use Tilttek\GameEngine\Outcome;
use Tilttek\GameEngine\Phase;
use Tilttek\GameEngine\Preferences;
use Tilttek\GameEngine\Randomness;
use Tilttek\GameEngine\Statistic;
use Tilttek\GameEngine\Statistics;
use Tilttek\MiniGames\Cell;
use Tilttek\MiniGames\Contract\MiniGame;
use Tilttek\MiniGames\Control;
use Tilttek\MiniGames\Exception\InvalidMiniGame;
use Tilttek\MiniGames\ScoreOrder;
use Tilttek\MiniGames\Scoring;
use Tilttek\MiniGames\ShareGrid;

/**
 * Bulls and Cows, the old guessing game of pencil and paper: find a code of different digits; each guess tells how
 * many of its digits are in the right place — the bulls — and how many are in the code at another place — the cows.
 * The fewer guesses, the better.
 *
 * Its one move is `bulls-and-cows.guess` (`{guess: "0472"}`). Its options are `length`, the digits of the code (4, from
 * 3 to 5), and `attempts`, the guesses allowed (10, from 6 to 12). A code found reports the guesses it took as the
 * statistic `score` of the seat; running out of guesses ends the game without a score.
 */
final readonly class BullsAndCows implements MiniGame
{
    public const string NAME = 'bulls-and-cows';

    public const string GUESS = 'bulls-and-cows.guess';

    private GameDefinition $definition;

    public function __construct()
    {
        $this->definition = new GameDefinition(
            self::NAME,
            [new Phase('guessing')],
            minimumSeats: 1,
            maximumSeats: 1,
            options: [Option::integer('length', 4, Code::MIN_LENGTH, Code::MAX_LENGTH), Option::integer('attempts', 10, 6, 12)],
            statistics: [Statistic::seat('score')],
        );
    }

    public function definition(): GameDefinition
    {
        return $this->definition;
    }

    public function scoring(): Scoring
    {
        return new Scoring(ScoreOrder::LowerIsBetter);
    }

    public function controls(): array
    {
        return [
            new Control('digit', ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9']),
            new Control('erase', ['Backspace']),
            new Control('guess', ['Enter']),
        ];
    }

    public function setup(GameState $state, Randomness $random): array
    {
        $digits = $random->shuffle(['0', '1', '2', '3', '4', '5', '6', '7', '8', '9']);

        return ['secret' => implode('', \array_slice($digits, 0, $state->options->int('length'))), 'guesses' => []];
    }

    public function allows(GameState $state, Move $move): bool
    {
        return self::GUESS === $move->type && null !== $move->seat;
    }

    public function play(GameState $state, Move $move, Randomness $random): array
    {
        [$secret, $guesses] = $this->read($state->data);

        try {
            $guess = Code::read($move->text('guess') ?? '', $state->options->int('length'));
        } catch (InvalidMiniGame $invalidMiniGame) {
            throw IllegalMove::because($invalidMiniGame->getMessage());
        }

        return ['secret' => $secret, 'guesses' => [...$guesses, $guess->digits]];
    }

    public function enter(GameState $state, Randomness $random): array
    {
        return $state->data;
    }

    public function outcome(GameState $state): ?Outcome
    {
        [$secret, $guesses] = $this->read($state->data);

        if (\in_array($secret, $guesses, true)) {
            return Outcome::won($state->seats, 'solved', new Statistics([], [$state->seats[0] => ['score' => \count($guesses)]]));
        }

        return \count($guesses) < $state->options->int('attempts') ? null : Outcome::ended('out-of-guesses');
    }

    /**
     * The guesses with their bulls and cows, the guesses left, and the code once the game is over.
     */
    public function view(GameState $state, ?string $seat, Preferences $preferences): array
    {
        [$secret, $guesses] = $this->read($state->data);
        $code = Code::read($secret, $state->options->int('length'));
        $rows = [];

        foreach ($guesses as $guess) {
            [$bulls, $cows] = $code->score(Code::read($guess, $state->options->int('length')));
            $rows[] = ['guess' => $guess, 'bulls' => $bulls, 'cows' => $cows];
        }

        return [
            'length' => $state->options->int('length'),
            'guesses' => $rows,
            'left' => $state->options->int('attempts') - \count($guesses),
            'code' => $state->isOver() ? $secret : null,
        ];
    }

    /**
     * One row per guess: a green square per bull, a yellow one per cow, the bulls first, so that the places stay
     * hidden; the guesses taken out of those allowed, `X` when the code was not found.
     */
    public function share(GameState $state): ShareGrid
    {
        [$secret, $guesses] = $this->read($state->data);
        $length = $state->options->int('length');
        $code = Code::read($secret, $length);
        $rows = [];

        foreach ($guesses as $guess) {
            [$bulls, $cows] = $code->score(Code::read($guess, $length));
            $rows[] = [...array_pad([], $bulls, Cell::Hit), ...array_pad([], $cows, Cell::Near), ...array_pad([], $length - $bulls - $cows, Cell::Miss)];
        }

        $taken = \in_array($secret, $guesses, true) ? \count($guesses) : ($state->isOver() ? 'X' : '-');

        return new ShareGrid($rows, $taken.'/'.$state->options->int('attempts'));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{string, list<string>}
     *
     * @throws InvalidGame when the data is not the secret and a list of guesses
     */
    private function read(array $data): array
    {
        $secret = $data['secret'] ?? null;
        $guesses = $data['guesses'] ?? null;

        if (!\is_string($secret) || !\is_array($guesses)) {
            throw InvalidGame::because('The data of Bulls and Cows is a secret and a list of guesses.');
        }

        $list = [];

        foreach ($guesses as $guess) {
            $list[] = \is_string($guess) ? $guess : throw InvalidGame::because('A guess of Bulls and Cows is a text.');
        }

        return [$secret, $list];
    }
}
