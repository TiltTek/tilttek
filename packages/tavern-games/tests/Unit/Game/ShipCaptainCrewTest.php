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
use Tilttek\TavernGames\Contract\TavernGame;
use Tilttek\TavernGames\DiceCup;
use Tilttek\TavernGames\Game\ShipCaptainCrew;
use Tilttek\TavernGames\Odds\CargoChase;
use Tilttek\TavernGames\Tests\Unit\GameStates;

#[CoversClass(ShipCaptainCrew::class)]
final class ShipCaptainCrewTest extends TestCase
{
    private const string SEED = 'abababababababababababababababababababababababababababababababab';

    private ShipCaptainCrew $game;

    protected function setUp(): void
    {
        $this->game = new ShipCaptainCrew();
    }

    public function testTheDefinition(): void
    {
        self::assertSame('ship-captain-crew', $this->game->definition()->game);
        self::assertSame(2, $this->game->definition()->minimumSeats);
        self::assertSame(8, $this->game->definition()->maximumSeats);
        self::assertSame(['turns' => ['order' => ['ann', 'bob'], 'current' => 0], 'rolls' => 0, 'held' => 0, 'dice' => [], 'cargoes' => []], $this->game->setup(GameStates::of($this->game, ['ann', 'bob'], []), new Randomness(self::SEED, 0)));
    }

    /**
     * @return iterable<string, array{int, list<int>, int, list<int>}>
     */
    public static function rolls(): iterable
    {
        yield 'all three in order' => [0, [6, 5, 4, 2, 3], 3, [2, 3]];
        yield 'all three in any order' => [0, [4, 5, 1, 6, 1], 3, [1, 1]];
        yield 'no ship, nothing' => [0, [5, 4, 3, 2, 1], 0, [5, 4, 3, 2, 1]];
        yield 'a ship alone' => [0, [6, 4, 1, 1, 1], 1, [4, 1, 1, 1]];
        yield 'one ship of two' => [0, [6, 6, 1, 1, 1], 1, [6, 1, 1, 1]];
        yield 'the captain and crew after the ship' => [1, [5, 4, 2, 2], 3, [2, 2]];
        yield 'the crew after the captain' => [2, [3, 4, 6], 3, [3, 6]];
        yield 'the cargo stays' => [3, [4, 6], 3, [4, 6]];
    }

    /**
     * @param list<int> $roll
     * @param list<int> $dice
     */
    #[DataProvider('rolls')]
    public function testTheShipCaptainAndCrewAreSetAsideInOrder(int $held, array $roll, int $expectedHeld, array $dice): void
    {
        self::assertSame([$expectedHeld, $dice], ShipCaptainCrew::setAside($held, $roll));
    }

    public function testTheMovesOfTheSeatPlaying(): void
    {
        $fresh = $this->state(0, 0, 0, []);
        $loaded = $this->state(0, 1, 3, [2, 3]);

        self::assertTrue($this->game->allows($fresh, new Move(ShipCaptainCrew::ROLL, 'ann')));
        self::assertFalse($this->game->allows($fresh, new Move(ShipCaptainCrew::ROLL, 'bob')));
        self::assertFalse($this->game->allows($fresh, new Move(ShipCaptainCrew::STOP, 'ann')));
        self::assertFalse($this->game->allows($this->state(0, 2, 2, [1, 1, 1]), new Move(ShipCaptainCrew::STOP, 'ann')));
        self::assertFalse($this->game->allows($this->state(0, 0, 3, [2, 3]), new Move(ShipCaptainCrew::STOP, 'ann')));
        self::assertTrue($this->game->allows($loaded, new Move(ShipCaptainCrew::STOP, 'ann')));
        self::assertFalse($this->game->allows($loaded, new Move(ShipCaptainCrew::STOP, 'bob')));
        self::assertTrue($this->game->allows($loaded, new Move(TavernGame::TIMEOUT, null, ['seat' => 'ann'])));
        self::assertFalse($this->game->allows($loaded, new Move(TavernGame::TIMEOUT, null, ['seat' => 'bob'])));
        self::assertFalse($this->game->allows($loaded, new Move(TavernGame::TIMEOUT, 'ann', ['seat' => 'ann'])));
        self::assertFalse($this->game->allows($loaded, new Move('ship-captain-crew.sail', 'ann')));
    }

    public function testARollSetsAsideWhatItCan(): void
    {
        $random = new Randomness(self::SEED, 1);
        $roll = new DiceCup()->roll(5, new Randomness(self::SEED, 1));
        [$held, $dice] = ShipCaptainCrew::setAside(0, $roll);

        $data = $this->game->play($this->state(0, 0, 0, []), new Move(ShipCaptainCrew::ROLL, 'ann'), $random);

        self::assertSame(['turns' => ['order' => ['ann', 'bob'], 'current' => 0], 'rolls' => 1, 'held' => $held, 'dice' => $dice, 'cargoes' => []], $data);
        self::assertTrue($random->drawn());
    }

    public function testTheDiceNotSetAsideAreRolled(): void
    {
        foreach ([[1, 4], [2, 3], [3, 2]] as [$held, $count]) {
            $data = $this->game->play($this->state(0, 1, $held, []), new Move(ShipCaptainCrew::ROLL, 'ann'), new Randomness(self::SEED, 2));
            self::assertIsArray($data['dice']);
            self::assertIsInt($data['held']);
            self::assertCount($count - ($data['held'] - $held), $data['dice']);
            self::assertSame(2, $data['rolls']);
        }
    }

    public function testTheThirdRollEndsTheTurn(): void
    {
        $roll = new DiceCup()->roll(2, new Randomness(self::SEED, 3));
        $data = $this->game->play($this->state(0, 2, 3, [1, 1]), new Move(ShipCaptainCrew::ROLL, 'ann'), new Randomness(self::SEED, 3));

        self::assertSame(['turns' => ['order' => ['ann', 'bob'], 'current' => 1], 'rolls' => 0, 'held' => 0, 'dice' => [], 'cargoes' => ['ann' => array_sum($roll)]], $data);
    }

    public function testATurnWithoutTheCrewCarriesNothing(): void
    {
        $data = $this->game->play($this->state(1, 2, 2, [6, 6, 6], ['ann' => 7]), new Move(TavernGame::TIMEOUT, null, ['seat' => 'bob']), new Randomness(self::SEED, 4));

        self::assertSame(['ann' => 7, 'bob' => 0], $data['cargoes']);
        self::assertSame(['order' => ['ann', 'bob'], 'current' => 0], $data['turns']);
    }

    public function testStoppingKeepsTheCargo(): void
    {
        $random = new Randomness(self::SEED, 4);
        $data = $this->game->play($this->state(0, 1, 3, [5, 6]), new Move(ShipCaptainCrew::STOP, 'ann'), $random);

        self::assertSame(['ann' => 11], $data['cargoes']);
        self::assertFalse($random->drawn());
    }

    public function testTheMostCargoWinsOnceEverySeatHasPlayed(): void
    {
        self::assertNull($this->game->outcome($this->state(1, 0, 0, [], ['ann' => 7])));

        $outcome = $this->game->outcome($this->state(0, 0, 0, [], ['ann' => 7, 'bob' => 9]));
        self::assertInstanceOf(Outcome::class, $outcome);
        self::assertSame(['bob'], $outcome->winners);
        self::assertSame('most-cargo', $outcome->reason);
        self::assertSame(7, $outcome->statistics->ofSeat('ann', 'cargo'));
    }

    public function testEqualCargoesShareTheWin(): void
    {
        $outcome = $this->game->outcome(GameStates::of($this->game, ['ann', 'bob', 'cid'], $this->data(0, 0, 0, [], ['ann' => 9, 'bob' => 4, 'cid' => 9], ['ann', 'bob', 'cid'])));

        self::assertSame(['ann', 'cid'], $outcome?->winners);
        self::assertSame('tie', $outcome->reason);
    }

    public function testNoCargoNoWinner(): void
    {
        $outcome = $this->game->outcome($this->state(0, 0, 0, [], ['ann' => 0, 'bob' => 0]));

        self::assertSame([], $outcome?->winners);
        self::assertSame('no-cargo', $outcome->reason);
        self::assertSame(0, $outcome->statistics->ofSeat('bob', 'cargo'));
    }

    public function testTheView(): void
    {
        $state = $this->state(1, 1, 3, [2, 5], ['ann' => 7]);

        self::assertSame(['turn' => 'bob', 'rolls' => 1, 'held' => 3, 'dice' => [2, 5], 'cargo' => 7, 'cargoes' => ['ann' => 7]], $this->game->view($state, null, Preferences::resolve([])));
        self::assertNull($this->game->view($this->state(1, 1, 2, [2, 5, 1]), null, Preferences::resolve([]))['cargo']);
        self::assertSame('bob', $this->game->toAct($state));
        self::assertSame($state->data, $this->game->enter($state, new Randomness(self::SEED, 1)));
    }

    public function testTheChaseOfTheSeatPlaying(): void
    {
        $chase = $this->game->chase($this->state(1, 1, 3, [2, 5], ['ann' => 7]));

        self::assertInstanceOf(CargoChase::class, $chase);
        self::assertSame(3, $chase->held);
        self::assertSame([2, 5], $chase->dice);
        self::assertSame(2, $chase->rolls);
        self::assertSame(7, $chase->target);
        self::assertSame(0, $this->game->chase($this->state(0, 0, 0, []))?->target);
        self::assertSame(9, $this->game->chase(GameStates::of($this->game, ['ann', 'bob', 'cid'], $this->data(2, 0, 0, [], ['ann' => 9, 'bob' => 4], ['ann', 'bob', 'cid'])))?->target);
    }

    public function testNoChaseNorTurnOnceTheGameIsOver(): void
    {
        $state = $this->state(0, 0, 0, [], ['ann' => 7, 'bob' => 9], Outcome::won(['bob'], 'most-cargo'));

        self::assertNull($this->game->chase($state));
        self::assertNull($this->game->toAct($state));
        self::assertNull($this->game->view($state, 'ann', Preferences::resolve([]))['turn']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function corrupted(): iterable
    {
        $turns = ['order' => ['ann', 'bob'], 'current' => 0];

        yield 'no rolls' => [['turns' => $turns, 'held' => 0, 'dice' => [], 'cargoes' => []]];
        yield 'no held' => [['turns' => $turns, 'rolls' => 0, 'dice' => [], 'cargoes' => []]];
        yield 'no dice' => [['turns' => $turns, 'rolls' => 0, 'held' => 0, 'cargoes' => []]];
        yield 'no cargoes' => [['turns' => $turns, 'rolls' => 0, 'held' => 0, 'dice' => []]];
        yield 'a die that is a text' => [['turns' => $turns, 'rolls' => 0, 'held' => 0, 'dice' => ['6'], 'cargoes' => []]];
        yield 'a cargo that is a text' => [['turns' => $turns, 'rolls' => 0, 'held' => 0, 'dice' => [], 'cargoes' => ['ann' => '7']]];
        yield 'no turns' => [['rolls' => 0, 'held' => 0, 'dice' => [], 'cargoes' => []]];
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

    public function testTheCargoOfEachSeatIsReported(): void
    {
        self::assertSame(['cargo'], array_map(static fn (Statistic $statistic): string => $statistic->name, $this->game->definition()->statistics));
    }

    public function testACargoBelowZeroIsNoCargo(): void
    {
        $outcome = $this->game->outcome($this->state(0, 0, 0, [], ['ann' => -5, 'bob' => -2]));

        self::assertSame([], $outcome?->winners);
        self::assertSame('no-cargo', $outcome->reason);
    }

    /**
     * @param list<int>          $dice
     * @param array<string, int> $cargoes
     */
    private function state(int $current, int $rolls, int $held, array $dice, array $cargoes = [], ?Outcome $outcome = null): GameState
    {
        return GameStates::of($this->game, ['ann', 'bob'], $this->data($current, $rolls, $held, $dice, $cargoes), [], $outcome);
    }

    /**
     * @param list<int>          $dice
     * @param array<string, int> $cargoes
     * @param list<string>       $seats
     *
     * @return array<string, mixed>
     */
    private function data(int $current, int $rolls, int $held, array $dice, array $cargoes, array $seats = ['ann', 'bob']): array
    {
        return ['turns' => ['order' => $seats, 'current' => $current], 'rolls' => $rolls, 'held' => $held, 'dice' => $dice, 'cargoes' => $cargoes];
    }
}
