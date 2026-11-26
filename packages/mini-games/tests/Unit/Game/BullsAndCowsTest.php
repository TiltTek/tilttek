<?php

declare(strict_types=1);

namespace Tilttek\MiniGames\Tests\Unit\Game;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\GameEngine\Exception\IllegalMove;
use Tilttek\GameEngine\Exception\InvalidGame;
use Tilttek\GameEngine\Move;
use Tilttek\GameEngine\Option;
use Tilttek\GameEngine\Outcome;
use Tilttek\GameEngine\Preferences;
use Tilttek\GameEngine\Randomness;
use Tilttek\GameEngine\Store\InMemoryLogStore;
use Tilttek\GameEngine\TableOptions;
use Tilttek\GameEngine\Tables;
use Tilttek\MiniGames\Cell;
use Tilttek\MiniGames\Control;
use Tilttek\MiniGames\Game\BullsAndCows;
use Tilttek\MiniGames\MiniGames;
use Tilttek\MiniGames\ScoreOrder;
use Tilttek\MiniGames\Tests\Unit\GameStates;

#[CoversClass(BullsAndCows::class)]
final class BullsAndCowsTest extends TestCase
{
    private BullsAndCows $game;

    protected function setUp(): void
    {
        $this->game = new BullsAndCows();
    }

    public function testTheDefinition(): void
    {
        $definition = $this->game->definition();

        self::assertSame('bulls-and-cows', $definition->game);
        self::assertSame(1, $definition->minimumSeats);
        self::assertSame(1, $definition->maximumSeats);
        self::assertSame(['length', 'attempts'], array_map(static fn (Option $option): string => $option->name, $definition->options));
        self::assertSame(ScoreOrder::LowerIsBetter, $this->game->scoring()->order);
        self::assertSame('score', $this->game->scoring()->statistic);
        self::assertSame(['digit', 'erase', 'guess'], array_map(static fn (Control $control): string => $control->action, $this->game->controls()));
        self::assertSame(['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], $this->game->controls()[0]->keys);
        self::assertSame(['Backspace'], $this->game->controls()[1]->keys);
        self::assertSame(['Enter'], $this->game->controls()[2]->keys);
    }

    public function testTheSecretIsOfDifferentDigits(): void
    {
        foreach ([3, 4, 5] as $length) {
            $data = $this->game->setup(GameStates::of($this->game, [], ['length' => $length]), new Randomness(str_repeat('ab', 32), 0));

            self::assertIsString($data['secret']);
            self::assertMatchesRegularExpression('/^[0-9]{'.$length.'}$/D', $data['secret']);
            self::assertCount($length, array_unique(str_split($data['secret'])));
            self::assertSame([], $data['guesses']);
        }
    }

    public function testTheSecretOfAKnownSeed(): void
    {
        self::assertSame(['secret' => '0642', 'guesses' => []], $this->game->setup(GameStates::of($this->game, []), new Randomness(str_repeat('ab', 32), 0)));
    }

    public function testEveryDigitCanBeInTheSecret(): void
    {
        $digits = [];

        for ($seed = 0; $seed < 200; ++$seed) {
            $data = $this->game->setup(GameStates::of($this->game, [], ['length' => 5]), new Randomness(hash('sha256', (string) $seed), 0));
            self::assertIsString($data['secret']);

            foreach (str_split($data['secret']) as $digit) {
                $digits[] = $digit;
            }
        }

        $seen = array_unique($digits);
        sort($seen);

        self::assertSame(['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], $seen);
    }

    public function testTheAttemptsAreSixToTwelve(): void
    {
        $options = $this->game->definition()->options;

        foreach ([6, 12] as $attempts) {
            self::assertSame($attempts, TableOptions::resolve($options, ['attempts' => $attempts])->int('attempts'));
        }

        $refused = 0;

        foreach ([5, 13] as $attempts) {
            try {
                TableOptions::resolve($options, ['attempts' => $attempts]);
            } catch (InvalidGame) {
                ++$refused;
            }
        }

        self::assertSame(2, $refused);
    }

    public function testTheSecretComesFromTheSeed(): void
    {
        $state = GameStates::of($this->game, []);

        self::assertSame($this->game->setup($state, new Randomness(str_repeat('ab', 32), 0)), $this->game->setup($state, new Randomness(str_repeat('ab', 32), 0)));
        self::assertNotSame($this->game->setup($state, new Randomness(str_repeat('ab', 32), 0)), $this->game->setup($state, new Randomness(str_repeat('cd', 32), 0)));
    }

    public function testOnlyAGuessOfASeatIsAllowed(): void
    {
        $state = GameStates::of($this->game, ['secret' => '0472', 'guesses' => []]);

        self::assertTrue($this->game->allows($state, new Move(BullsAndCows::GUESS, 'ann', ['guess' => '1234'])));
        self::assertFalse($this->game->allows($state, new Move(BullsAndCows::GUESS)));
        self::assertFalse($this->game->allows($state, new Move('bulls-and-cows.peek', 'ann')));
        self::assertFalse($this->game->allows($state, Move::endTurn('ann')));
    }

    public function testAGuessIsAdded(): void
    {
        $state = GameStates::of($this->game, ['secret' => '0472', 'guesses' => ['1234']]);
        $data = $this->game->play($state, new Move(BullsAndCows::GUESS, 'ann', ['guess' => '0412']), new Randomness(str_repeat('ab', 32), 2));

        self::assertSame(['secret' => '0472', 'guesses' => ['1234', '0412']], $data);
        self::assertSame($state->data, $this->game->enter($state, new Randomness(str_repeat('ab', 32), 2)));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function refusedPayloads(): iterable
    {
        yield 'no guess' => [[]];
        yield 'a number' => [['guess' => 412]];
        yield 'a digit twice' => [['guess' => '0447']];
        yield 'too long for the code' => [['guess' => '04712']];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('refusedPayloads')]
    public function testARefusedGuess(array $payload): void
    {
        $this->expectException(IllegalMove::class);

        $this->game->play(GameStates::of($this->game, ['secret' => '0472', 'guesses' => []]), new Move(BullsAndCows::GUESS, 'ann', $payload), new Randomness(str_repeat('ab', 32), 1));
    }

    public function testTheGuessFollowsTheLengthOfTheTable(): void
    {
        $data = $this->game->play(GameStates::of($this->game, ['secret' => '04712', 'guesses' => []], ['length' => 5]), new Move(BullsAndCows::GUESS, 'ann', ['guess' => '12345']), new Randomness(str_repeat('ab', 32), 1));

        self::assertSame(['12345'], $data['guesses']);
    }

    public function testTheCodeFoundWinsWithTheGuessesTaken(): void
    {
        $outcome = $this->game->outcome(GameStates::of($this->game, ['secret' => '0472', 'guesses' => ['1234', '0472']]));

        self::assertInstanceOf(Outcome::class, $outcome);
        self::assertSame(['ann'], $outcome->winners);
        self::assertSame('solved', $outcome->reason);
        self::assertSame(2, $outcome->statistics->ofSeat('ann', 'score'));
    }

    public function testTheGameGoesOnWhileGuessesRemain(): void
    {
        self::assertNull($this->game->outcome(GameStates::of($this->game, ['secret' => '0472', 'guesses' => []])));
        self::assertNull($this->game->outcome(GameStates::of($this->game, ['secret' => '0472', 'guesses' => array_fill(0, 9, '1234')])));
        self::assertNull($this->game->outcome(GameStates::of($this->game, ['secret' => '0472', 'guesses' => array_fill(0, 6, '1234')], ['attempts' => 7])));
    }

    public function testRunningOutOfGuessesEndsWithoutAScore(): void
    {
        $outcome = $this->game->outcome(GameStates::of($this->game, ['secret' => '0472', 'guesses' => array_fill(0, 10, '1234')]));

        self::assertInstanceOf(Outcome::class, $outcome);
        self::assertSame([], $outcome->winners);
        self::assertSame('out-of-guesses', $outcome->reason);
        self::assertTrue($outcome->statistics->isEmpty());
        self::assertSame('out-of-guesses', $this->game->outcome(GameStates::of($this->game, ['secret' => '0472', 'guesses' => array_fill(0, 6, '1234')], ['attempts' => 6]))?->reason);
    }

    public function testTheViewHidesTheCodeUntilTheEnd(): void
    {
        $data = ['secret' => '0472', 'guesses' => ['1234', '0417']];
        $playing = $this->game->view(GameStates::of($this->game, $data), 'ann', Preferences::resolve([]));

        self::assertSame([
            'length' => 4,
            'guesses' => [['guess' => '1234', 'bulls' => 0, 'cows' => 2], ['guess' => '0417', 'bulls' => 2, 'cows' => 1]],
            'left' => 8,
            'code' => null,
        ], $playing);

        $over = $this->game->view(GameStates::of($this->game, $data, [], Outcome::ended('out-of-guesses')), 'ann', Preferences::resolve([]));
        self::assertSame('0472', $over['code']);
    }

    public function testTheShareShowsTheBullsFirstAndNoDigit(): void
    {
        $grid = $this->game->share(GameStates::of($this->game, ['secret' => '0472', 'guesses' => ['0241', '0472']], [], Outcome::won(['ann'], 'solved')));

        self::assertSame([[Cell::Hit, Cell::Near, Cell::Near, Cell::Miss], [Cell::Hit, Cell::Hit, Cell::Hit, Cell::Hit]], $grid->rows);
        self::assertSame('2/10', $grid->summary);
    }

    public function testTheShareOfAGameGoingOnOrLost(): void
    {
        $data = ['secret' => '0472', 'guesses' => ['1234']];

        self::assertSame('-/10', $this->game->share(GameStates::of($this->game, $data))->summary);
        self::assertSame('X/7', $this->game->share(GameStates::of($this->game, $data, ['attempts' => 7], Outcome::ended('out-of-guesses')))->summary);
        self::assertSame([[Cell::Near, Cell::Near, Cell::Miss]], $this->game->share(GameStates::of($this->game, ['secret' => '047', 'guesses' => ['471']], ['length' => 3]))->rows);
    }

    public function testAWholeGameAtATable(): void
    {
        $games = new MiniGames($this->game);
        $tables = new Tables($games->games(), new InMemoryLogStore());
        $state = $tables->open('bulls-and-cows', 't-1', ['ann'], ['attempts' => 6]);
        $secret = $state->data['secret'];
        self::assertIsString($secret);

        $state = $tables->play('t-1', new Move(BullsAndCows::GUESS, 'ann', ['guess' => $secret]));

        self::assertSame(['ann'], $state->outcome?->winners);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function corrupted(): iterable
    {
        yield 'no secret' => [['guesses' => []]];
        yield 'no guesses' => [['secret' => '0472']];
        yield 'a secret that is a number' => [['secret' => 472, 'guesses' => []]];
        yield 'guesses that are a text' => [['secret' => '0472', 'guesses' => '1234']];
        yield 'a guess that is a number' => [['secret' => '0472', 'guesses' => [1234]]];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('corrupted')]
    public function testCorruptedDataIsRefused(array $data): void
    {
        $this->expectException(InvalidGame::class);
        $this->expectExceptionMessageMatches('/^(The data of Bulls and Cows is a secret and a list of guesses\.|A guess of Bulls and Cows is a text\.)$/');

        $this->game->outcome(GameStates::of($this->game, $data));
    }
}
