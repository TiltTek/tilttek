<?php

declare(strict_types=1);

namespace Tilttek\TavernGames\Tests\Unit\Game;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\GameEngine\Exception\InvalidGame;
use Tilttek\GameEngine\GameState;
use Tilttek\GameEngine\Move;
use Tilttek\GameEngine\Outcome;
use Tilttek\GameEngine\Preferences;
use Tilttek\GameEngine\Randomness;
use Tilttek\GameEngine\Statistic;
use Tilttek\GameEngine\TableOptions;
use Tilttek\TavernGames\Contract\TavernGame;
use Tilttek\TavernGames\DiceCup;
use Tilttek\TavernGames\Game\Pig;
use Tilttek\TavernGames\Tests\Unit\GameStates;
use Tilttek\TavernGames\Turns;

#[CoversClass(Pig::class)]
final class PigTest extends TestCase
{
    private const string SEED = 'abababababababababababababababababababababababababababababababab';

    private Pig $game;

    protected function setUp(): void
    {
        $this->game = new Pig();
    }

    public function testTheDefinition(): void
    {
        $definition = $this->game->definition();

        self::assertSame('pig', $definition->game);
        self::assertSame(2, $definition->minimumSeats);
        self::assertSame(8, $definition->maximumSeats);
        self::assertSame('target', $definition->options[0]->name);
    }

    public function testTheTargetIsTwentyToTwoHundredAndTheScoreIsReported(): void
    {
        $definition = $this->game->definition();

        self::assertSame(['score'], array_map(static fn (Statistic $statistic): string => $statistic->name, $definition->statistics));

        foreach ([20, 200] as $target) {
            self::assertSame($target, TableOptions::resolve($definition->options, ['target' => $target])->int('target'));
        }

        $refused = 0;

        foreach ([19, 201] as $target) {
            try {
                TableOptions::resolve($definition->options, ['target' => $target]);
            } catch (InvalidGame) {
                ++$refused;
            }
        }

        self::assertSame(2, $refused);
    }

    public function testEverySeatStartsAtZero(): void
    {
        $data = $this->game->setup(GameStates::of($this->game, ['ann', 'bob'], []), new Randomness(self::SEED, 0));

        self::assertSame(['turns' => ['order' => ['ann', 'bob'], 'current' => 0], 'scores' => ['ann' => 0, 'bob' => 0], 'total' => 0, 'last' => null], $data);
    }

    public function testTheSeatPlayingRollsAndHolds(): void
    {
        $state = $this->state(0, ['ann' => 0, 'bob' => 0], 0);

        self::assertTrue($this->game->allows($state, new Move(Pig::ROLL, 'ann')));
        self::assertTrue($this->game->allows($state, new Move(Pig::HOLD, 'ann')));
        self::assertFalse($this->game->allows($state, new Move(Pig::ROLL, 'bob')));
        self::assertFalse($this->game->allows($state, new Move(Pig::HOLD)));
        self::assertFalse($this->game->allows($state, new Move('pig.cheat', 'ann')));
    }

    public function testTheTableTimesOutTheSeatPlayingOnly(): void
    {
        $state = $this->state(0, ['ann' => 0, 'bob' => 0], 0);

        self::assertTrue($this->game->allows($state, new Move(TavernGame::TIMEOUT, null, ['seat' => 'ann'])));
        self::assertFalse($this->game->allows($state, new Move(TavernGame::TIMEOUT, null, ['seat' => 'bob'])));
        self::assertFalse($this->game->allows($state, new Move(TavernGame::TIMEOUT, 'ann', ['seat' => 'ann'])));
        self::assertFalse($this->game->allows($state, new Move(TavernGame::TIMEOUT)));
    }

    public function testAFaceAboveOneIsAddedToTheTurn(): void
    {
        $sequence = $this->sequenceRolling(static fn (int $face): bool => $face > 1);
        $face = new DiceCup()->roll(1, new Randomness(self::SEED, $sequence))[0];

        $data = $this->game->play($this->state(0, ['ann' => 3, 'bob' => 0], 5), new Move(Pig::ROLL, 'ann'), new Randomness(self::SEED, $sequence));

        self::assertSame(['turns' => ['order' => ['ann', 'bob'], 'current' => 0], 'scores' => ['ann' => 3, 'bob' => 0], 'total' => 5 + $face, 'last' => $face], $data);
    }

    public function testAOneLosesTheTurn(): void
    {
        $sequence = $this->sequenceRolling(static fn (int $face): bool => 1 === $face);

        $data = $this->game->play($this->state(0, ['ann' => 3, 'bob' => 0], 5), new Move(Pig::ROLL, 'ann'), new Randomness(self::SEED, $sequence));

        self::assertSame(['turns' => ['order' => ['ann', 'bob'], 'current' => 1], 'scores' => ['ann' => 3, 'bob' => 0], 'total' => 0, 'last' => 1], $data);
    }

    public function testHoldingBanksTheTurn(): void
    {
        $random = new Randomness(self::SEED, 9);
        $data = $this->game->play($this->state(1, ['ann' => 3, 'bob' => 4], 7), new Move(Pig::HOLD, 'bob'), $random);

        self::assertSame(['turns' => ['order' => ['ann', 'bob'], 'current' => 0], 'scores' => ['ann' => 3, 'bob' => 11], 'total' => 0, 'last' => null], $data);
        self::assertFalse($random->drawn());
    }

    public function testTheTimeoutHolds(): void
    {
        $data = $this->game->play($this->state(0, ['ann' => 3, 'bob' => 4], 7), new Move(TavernGame::TIMEOUT, null, ['seat' => 'ann']), new Randomness(self::SEED, 9));

        self::assertSame(['ann' => 10, 'bob' => 4], $data['scores']);
        self::assertSame(1, Turns::fromData($data['turns'])->current);
    }

    public function testTheTargetWins(): void
    {
        self::assertNull($this->game->outcome($this->state(0, ['ann' => 99, 'bob' => 0], 0)));

        $outcome = $this->game->outcome($this->state(0, ['ann' => 100, 'bob' => 40], 0));
        self::assertInstanceOf(Outcome::class, $outcome);
        self::assertSame(['ann'], $outcome->winners);
        self::assertSame('target-reached', $outcome->reason);
        self::assertSame(40, $outcome->statistics->ofSeat('bob', 'score'));
        self::assertSame(100, $outcome->statistics->ofSeat('ann', 'score'));
        self::assertSame(['bob'], $this->game->outcome($this->state(0, ['ann' => 19, 'bob' => 20], 0, ['target' => 20]))?->winners);
    }

    public function testTheViewShowsEverything(): void
    {
        $state = $this->state(1, ['ann' => 3, 'bob' => 4], 7, last: 6);

        self::assertSame(['turn' => 'bob', 'scores' => ['ann' => 3, 'bob' => 4], 'total' => 7, 'last' => 6, 'target' => 100], $this->game->view($state, 'ann', Preferences::resolve([])));
        self::assertSame('bob', $this->game->toAct($state));
        self::assertSame($state->data, $this->game->enter($state, new Randomness(self::SEED, 1)));
    }

    public function testNobodyActsOnceTheGameIsOver(): void
    {
        $state = $this->state(1, ['ann' => 100, 'bob' => 4], 0, outcome: Outcome::won(['ann'], 'target-reached'));

        self::assertNull($this->game->toAct($state));
        self::assertNull($this->game->view($state, null, Preferences::resolve([]))['turn']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function corrupted(): iterable
    {
        $turns = ['order' => ['ann', 'bob'], 'current' => 0];

        yield 'no scores' => [['turns' => $turns, 'total' => 0]];
        yield 'no total' => [['turns' => $turns, 'scores' => ['ann' => 0]]];
        yield 'a total that is a text' => [['turns' => $turns, 'scores' => ['ann' => 0], 'total' => '0']];
        yield 'a score that is a text' => [['turns' => $turns, 'scores' => ['ann' => '0'], 'total' => 0]];
        yield 'no turns' => [['scores' => ['ann' => 0], 'total' => 0]];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('corrupted')]
    public function testCorruptedDataIsRefused(array $data): void
    {
        $this->expectException(InvalidGame::class);

        $this->game->outcome(GameStates::of($this->game, ['ann', 'bob'], $data));
    }

    /**
     * @param array<string, int>   $scores
     * @param array<string, mixed> $options
     */
    private function state(int $current, array $scores, int $total, array $options = [], ?int $last = null, ?Outcome $outcome = null): GameState
    {
        return GameStates::of($this->game, ['ann', 'bob'], ['turns' => ['order' => ['ann', 'bob'], 'current' => $current], 'scores' => $scores, 'total' => $total, 'last' => $last], $options, $outcome);
    }

    /**
     * @param callable(int): bool $wanted
     */
    private function sequenceRolling(callable $wanted): int
    {
        $sequence = 1;

        for (; !$wanted(new DiceCup()->roll(1, new Randomness(self::SEED, $sequence))[0]); ++$sequence) {
        }

        return $sequence;
    }
}
