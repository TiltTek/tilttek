<?php

declare(strict_types=1);

namespace Tilttek\MiniGames\Tests\Unit\Game;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\GameEngine\Exception\IllegalMove;
use Tilttek\GameEngine\Exception\InvalidGame;
use Tilttek\GameEngine\GameState;
use Tilttek\GameEngine\Move;
use Tilttek\GameEngine\Outcome;
use Tilttek\GameEngine\Preferences;
use Tilttek\GameEngine\Randomness;
use Tilttek\GameEngine\Statistic;
use Tilttek\MiniGames\Cell;
use Tilttek\MiniGames\Control;
use Tilttek\MiniGames\Game\HigherOrLower;
use Tilttek\MiniGames\ScoreOrder;
use Tilttek\MiniGames\Tests\Unit\GameStates;

#[CoversClass(HigherOrLower::class)]
final class HigherOrLowerTest extends TestCase
{
    private const array NUMBERS = [50, 70, 20, 90, 10, 30, 60, 40, 80, 5, 95];

    private HigherOrLower $game;

    protected function setUp(): void
    {
        $this->game = new HigherOrLower();
    }

    public function testTheDefinition(): void
    {
        self::assertSame('higher-or-lower', $this->game->definition()->game);
        self::assertSame(1, $this->game->definition()->maximumSeats);
        self::assertSame(ScoreOrder::HigherIsBetter, $this->game->scoring()->order);
        self::assertSame(['higher', 'lower'], array_map(static fn (Control $control): string => $control->action, $this->game->controls()));
        self::assertSame(['ArrowUp', 'h'], $this->game->controls()[0]->keys);
        self::assertSame(['ArrowDown', 'l'], $this->game->controls()[1]->keys);
    }

    public function testElevenDifferentNumbersFromOneToAHundred(): void
    {
        $data = $this->game->setup(GameStates::of($this->game, []), new Randomness(str_repeat('ab', 32), 0));

        self::assertIsArray($data['numbers']);
        self::assertCount(11, $data['numbers']);
        self::assertCount(11, array_unique(array_filter($data['numbers'], is_int(...))));
        self::assertGreaterThanOrEqual(1, min($data['numbers']));
        self::assertLessThanOrEqual(100, max($data['numbers']));
        self::assertSame([], $data['calls']);
    }

    public function testTheNumbersOfAKnownSeed(): void
    {
        self::assertSame(['numbers' => [88, 65, 61, 64, 99, 96, 59, 75, 94, 76, 98], 'calls' => []], $this->game->setup(GameStates::of($this->game, []), new Randomness(str_repeat('ab', 32), 0)));
    }

    public function testEveryNumberFromOneToAHundredCanBeDrawn(): void
    {
        $seen = [];

        for ($seed = 0; $seed < 300; ++$seed) {
            $data = $this->game->setup(GameStates::of($this->game, []), new Randomness(hash('sha256', (string) $seed), 0));
            self::assertIsArray($data['numbers']);

            foreach ($data['numbers'] as $number) {
                self::assertIsInt($number);
                $seen[$number] = true;
            }
        }

        ksort($seen);

        self::assertSame(range(1, 100), array_keys($seen));
    }

    public function testTheGameReportsTheScoreOfTheSeat(): void
    {
        self::assertSame(['score'], array_map(static fn (Statistic $statistic): string => $statistic->name, $this->game->definition()->statistics));
    }

    public function testTwoEqualNumbersMakeEveryCallWrong(): void
    {
        $state = GameStates::of($this->game, ['numbers' => [50, 50, 1, 2, 3, 4, 5, 6, 7, 8, 9], 'calls' => []]);

        foreach (['higher', 'lower'] as $call) {
            self::assertSame([false], $this->game->play($state, new Move(HigherOrLower::CALL, 'ann', ['call' => $call]), new Randomness(str_repeat('ab', 32), 1))['calls']);
        }
    }

    public function testOnlyACallOfASeatIsAllowed(): void
    {
        $state = $this->state([]);

        self::assertTrue($this->game->allows($state, new Move(HigherOrLower::CALL, 'ann', ['call' => 'higher'])));
        self::assertFalse($this->game->allows($state, new Move(HigherOrLower::CALL, null, ['call' => 'higher'])));
        self::assertFalse($this->game->allows($state, new Move('higher-or-lower.skip', 'ann')));
    }

    public function testACallIsJudgedAgainstTheNextNumber(): void
    {
        self::assertSame([true], $this->call([], 'higher')['calls']);
        self::assertSame([false], $this->call([], 'lower')['calls']);
        self::assertSame([true, true], $this->call([true], 'lower')['calls']);
        self::assertSame([true, false], $this->call([true], 'higher')['calls']);
        self::assertSame(self::NUMBERS, $this->call([], 'higher')['numbers']);
        self::assertSame($this->state([])->data, $this->game->enter($this->state([]), new Randomness(str_repeat('ab', 32), 1)));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function refusedCalls(): iterable
    {
        yield 'no call' => [[]];
        yield 'another word' => [['call' => 'same']];
        yield 'capitals' => [['call' => 'Higher']];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('refusedCalls')]
    public function testARefusedCall(array $payload): void
    {
        $this->expectException(IllegalMove::class);
        $this->expectExceptionMessageIsOrContains('A call is "higher" or "lower".');

        $this->game->play($this->state([]), new Move(HigherOrLower::CALL, 'ann', $payload), new Randomness(str_repeat('ab', 32), 1));
    }

    public function testAWrongCallEndsTheGameWithTheRightCalls(): void
    {
        $outcome = $this->game->outcome($this->state([true, true, false]));

        self::assertInstanceOf(Outcome::class, $outcome);
        self::assertSame([], $outcome->winners);
        self::assertSame('missed', $outcome->reason);
        self::assertSame(2, $outcome->statistics->ofSeat('ann', 'score'));
    }

    public function testTenRightCallsWin(): void
    {
        $outcome = $this->game->outcome($this->state(array_fill(0, 10, true)));

        self::assertInstanceOf(Outcome::class, $outcome);
        self::assertSame(['ann'], $outcome->winners);
        self::assertSame('all-called', $outcome->reason);
        self::assertSame(10, $outcome->statistics->ofSeat('ann', 'score'));
    }

    public function testTheGameGoesOnWhileEveryCallIsRight(): void
    {
        self::assertNull($this->game->outcome($this->state([])));
        self::assertNull($this->game->outcome($this->state(array_fill(0, 9, true))));
    }

    public function testTheViewNeverShowsTheNextNumber(): void
    {
        self::assertSame(['shown' => [50], 'calls' => [], 'left' => 10], $this->game->view($this->state([]), 'ann', Preferences::resolve([])));
        self::assertSame(['shown' => [50, 70, 20], 'calls' => [true, true], 'left' => 8], $this->game->view($this->state([true, true]), 'ann', Preferences::resolve([])));
        self::assertSame(0, $this->game->view($this->state([false], Outcome::ended('missed')), 'ann', Preferences::resolve([]))['left']);
    }

    public function testTheShareShowsRightAndWrongCallsOnly(): void
    {
        $grid = $this->game->share($this->state([true, true, false]));

        self::assertSame([[Cell::Hit, Cell::Hit, Cell::Miss, Cell::Empty, Cell::Empty, Cell::Empty, Cell::Empty, Cell::Empty, Cell::Empty, Cell::Empty]], $grid->rows);
        self::assertSame('2/10', $grid->summary);
        self::assertSame('10/10', $this->game->share($this->state(array_fill(0, 10, true)))->summary);
        self::assertCount(10, $this->game->share($this->state([]))->rows[0]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function corrupted(): iterable
    {
        yield 'no numbers' => [['calls' => []]];
        yield 'no calls' => [['numbers' => self::NUMBERS]];
        yield 'ten numbers' => [['numbers' => \array_slice(self::NUMBERS, 0, 10), 'calls' => []]];
        yield 'twelve numbers' => [['numbers' => [...self::NUMBERS, 1], 'calls' => []]];
        yield 'eleven calls' => [['numbers' => self::NUMBERS, 'calls' => array_fill(0, 11, true)]];
        yield 'a number that is a text' => [['numbers' => ['50', ...\array_slice(self::NUMBERS, 1)], 'calls' => []]];
        yield 'a call that is a number' => [['numbers' => self::NUMBERS, 'calls' => [1]]];
        yield 'calls that are a text' => [['numbers' => self::NUMBERS, 'calls' => 'true']];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('corrupted')]
    public function testCorruptedDataIsRefused(array $data): void
    {
        $this->expectException(InvalidGame::class);

        $this->game->outcome(GameStates::of($this->game, $data));
    }

    public function testTenCallsAreRead(): void
    {
        $calls = $this->game->view($this->state(array_fill(0, 10, true)), 'ann', Preferences::resolve([]))['calls'];

        self::assertIsArray($calls);
        self::assertCount(10, $calls);
    }

    /**
     * @param list<bool> $calls
     */
    private function state(array $calls, ?Outcome $outcome = null): GameState
    {
        return GameStates::of($this->game, ['numbers' => self::NUMBERS, 'calls' => $calls], [], $outcome);
    }

    /**
     * @param list<bool> $calls
     *
     * @return array<string, mixed>
     */
    private function call(array $calls, string $call): array
    {
        return $this->game->play($this->state($calls), new Move(HigherOrLower::CALL, 'ann', ['call' => $call]), new Randomness(str_repeat('ab', 32), 1));
    }
}
