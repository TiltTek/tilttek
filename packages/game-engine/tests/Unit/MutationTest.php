<?php

declare(strict_types=1);

namespace Tilttek\GameEngine\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\GameEngine\Contract\Game;
use Tilttek\GameEngine\Engine;
use Tilttek\GameEngine\Exception\IllegalMove;
use Tilttek\GameEngine\Exception\InvalidGame;
use Tilttek\GameEngine\GameDefinition;
use Tilttek\GameEngine\GameLog;
use Tilttek\GameEngine\GameState;
use Tilttek\GameEngine\Move;
use Tilttek\GameEngine\Option;
use Tilttek\GameEngine\Outcome;
use Tilttek\GameEngine\Phase;
use Tilttek\GameEngine\Phases;
use Tilttek\GameEngine\Preferences;
use Tilttek\GameEngine\Randomness;
use Tilttek\GameEngine\Scene\RoleplayingScene;
use Tilttek\GameEngine\Statistics;
use Tilttek\GameEngine\TableOptions;
use Tilttek\GameEngine\Transition;
use Tilttek\GameEngine\Turn;
use Tilttek\GameEngine\TurnOrder;

/**
 * The boundaries and the exact values the other tests do not pin: what the mutation testing found alive.
 */
#[CoversClass(Randomness::class)]
#[CoversClass(Engine::class)]
#[CoversClass(InvalidGame::class)]
#[CoversClass(GameDefinition::class)]
#[CoversClass(GameLog::class)]
#[CoversClass(Move::class)]
#[CoversClass(Phases::class)]
#[CoversClass(RoleplayingScene::class)]
#[CoversClass(TableOptions::class)]
#[CoversClass(Transition::class)]
#[CoversClass(Turn::class)]
final class MutationTest extends TestCase
{
    private const string SEED = '00112233445566778899aabbccddeeff00112233445566778899aabbccddeeff';

    public function testTheSetupDrawsFromTheSequenceZero(): void
    {
        $game = new class implements Game {
            public function definition(): GameDefinition
            {
                return new GameDefinition('lottery', [new Phase('play')]);
            }

            public function setup(GameState $state, Randomness $random): array
            {
                return ['draw' => $random->int(0, \PHP_INT_MAX)];
            }

            public function allows(GameState $state, Move $move): bool
            {
                return false;
            }

            public function play(GameState $state, Move $move, Randomness $random): array
            {
                return $state->data;
            }

            public function enter(GameState $state, Randomness $random): array
            {
                return $state->data;
            }

            public function outcome(GameState $state): ?Outcome
            {
                return null;
            }

            public function view(GameState $state, ?string $seat, Preferences $preferences): array
            {
                return $state->data;
            }
        };

        $table = new Engine()->start($game, 'lottery:1', ['ann'], [], self::SEED);

        self::assertSame(new Randomness(self::SEED, 0)->int(0, \PHP_INT_MAX), $table->state->data['draw']);
        self::assertNotSame(new Randomness(self::SEED, 1)->int(0, \PHP_INT_MAX), $table->state->data['draw']);
        self::assertNotSame(new Randomness(self::SEED, -1)->int(0, \PHP_INT_MAX), $table->state->data['draw']);
    }

    public function testRandomnessIsSeededByTheSeedAndTheSequence(): void
    {
        $random = new Randomness(self::SEED, 3);
        $expected = new Randomizer(new Xoshiro256StarStar(hash('sha256', self::SEED.':3', true)));

        self::assertSame($expected->getInt(0, 1_000_000), $random->int(0, 1_000_000));
        self::assertSame($random->randomizer(), $random->randomizer());
        self::assertTrue($random->drawn());
        self::assertFalse(new Randomness(self::SEED, 3)->drawn());
        self::assertSame(['a'], array_values(array_unique(array_map(static fn (int $sequence): string => new Randomness(self::SEED, $sequence)->pick(['a']), range(1, 60)))));
        self::assertSame(64, \strlen(Randomness::seed()));
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/D', Randomness::seed());
    }

    public function testASeedIsExactly64LowercaseHexadecimalDigits(): void
    {
        self::assertSame(self::SEED, Randomness::check(self::SEED));

        foreach (['x'.self::SEED, self::SEED.'x', self::SEED."\n", strtoupper(self::SEED), substr(self::SEED, 1)] as $seed) {
            try {
                Randomness::check($seed);
                self::fail('A bad seed was accepted.');
            } catch (InvalidGame $invalidGame) {
                self::assertSame('A seed is 64 lowercase hexadecimal digits.', $invalidGame->getMessage());
                self::assertSame(0, $invalidGame->getCode());
            }
        }
    }

    public function testAVersionMayBe64CharactersLong(): void
    {
        self::assertSame(str_repeat('v', 64), new GameDefinition('lottery', [new Phase('play')], version: str_repeat('v', 64))->version);

        $this->expectException(InvalidGame::class);
        new GameDefinition('lottery', [new Phase('play')], version: str_repeat('v', 65));
    }

    public function testALogMoveThatNeverSaidItDrewDidNot(): void
    {
        $data = new GameLog('table:1', 'race', '1', self::SEED, ['ann'], [])->toArray();
        $data['moves'] = [['type' => 'race.roll', 'seat' => 'ann', 'payload' => []]];

        self::assertFalse(GameLog::fromArray($data)->move(1)->drew);
    }

    public function testAPayloadMayBeEightLevelsDeep(): void
    {
        self::assertCount(1, new Move('x', null, ['deep' => [[[[[[[1]]]]]]]])->payload);

        $this->expectException(InvalidGame::class);
        new Move('x', null, ['deep' => [[[[[[[[1]]]]]]]]]);
    }

    public function testATransitionNamesEveryPhaseItLeavesFrom(): void
    {
        try {
            new Phases('g', [new Phase('a'), new Phase('b')], [new Transition('go', ['a', 'nowhere'], 'b')], 'a');
            self::fail('A phase the game lacks was accepted.');
        } catch (InvalidGame $invalidGame) {
            self::assertStringContainsString('names the phase "nowhere"', $invalidGame->getMessage());
        }
    }

    public function testThePhasesListTheirTransitionsByName(): void
    {
        $phases = new Phases('g', [new Phase('a'), new Phase('b')], [new Transition('go', ['a'], 'b'), new Transition('stay', ['a'], 'a'), new Transition('back', ['b'], 'a')], 'a');

        self::assertSame(['go', 'stay'], $phases->from('a'));
        self::assertSame(['back'], $phases->from('b'));
    }

    public function testATransitionNameIsChecked(): void
    {
        $this->expectException(InvalidGame::class);
        new Transition('Not A Name', ['a'], 'b');
    }

    public function testAnOptionOfTheWrongKindIsRefusedByType(): void
    {
        try {
            TableOptions::resolve([Option::boolean('fast', true)], ['fast' => 'yes']);
            self::fail('A text was accepted for a boolean.');
        } catch (InvalidGame $invalidGame) {
            self::assertStringContainsString('of type "bool"', $invalidGame->getMessage());
        }
    }

    public function testTheSceneSeatsOneToThirtyTwo(): void
    {
        $scene = new RoleplayingScene();
        $engine = new Engine();

        self::assertSame(['gm'], $engine->start($scene, 'scene:1', ['gm'], [], self::SEED)->state->seats);
        self::assertCount(32, $engine->start($scene, 'scene:2', array_map(static fn (int $index): string => 'seat'.$index, range(1, 32)), [], self::SEED)->state->seats);
        self::assertSame(1, $scene->definition()->minimumSeats);
        self::assertSame(32, $scene->definition()->maximumSeats);

        $this->expectException(InvalidGame::class);
        $engine->start($scene, 'scene:3', array_map(static fn (int $index): string => 'seat'.$index, range(1, 33)), [], self::SEED);
    }

    public function testTheSceneTakesAMoveBack(): void
    {
        $scene = new RoleplayingScene();
        $engine = new Engine();
        $table = $engine->start($scene, 'scene:1', ['gm', 'ann'], [], self::SEED);
        $played = $engine->play($scene, $table, new Move('scene.note', 'ann', ['text' => 'A note.']));

        self::assertTrue($scene->definition()->undo);
        self::assertEquals($table, $engine->undo($scene, $played, 'ann'));
    }

    public function testTheSceneCountsFromWhatItFindsInItsData(): void
    {
        $scene = new RoleplayingScene();
        $random = new Randomness(self::SEED, 1);
        $state = static fn (array $data, string $phase = 'exploration'): GameState => new GameState('scene:1', 'roleplaying-scene', 0, ['ann'], TableOptions::resolve([], []), $phase, Turn::start(TurnOrder::Free, ['ann'], []), data: $data);

        self::assertSame(['ann' => 1], $scene->play($state([]), new Move('scene.act', 'ann'), $random)['actions']);
        self::assertSame(['ann' => 4, 'bob' => 2], $scene->play($state(['actions' => ['ann' => 3, 'bob' => 2]]), new Move('scene.act', 'ann'), $random)['actions']);
        self::assertSame(['ann' => 1], $scene->play($state(['actions' => ['ann' => 'many']]), new Move('scene.act', 'ann'), $random)['actions']);
        self::assertSame(['ann' => 1], $scene->play($state(['actions' => 'none']), new Move('scene.act', 'ann'), $random)['actions']);
        self::assertSame(1, $scene->play($state([]), new Move('scene.note', 'ann', ['text' => 'x']), $random)['notes']);
        self::assertSame(6, $scene->play($state(['notes' => 5]), new Move('scene.note', 'ann', ['text' => 'x']), $random)['notes']);
        self::assertSame(1, $scene->play($state(['notes' => 'many']), new Move('scene.note', 'ann', ['text' => 'x']), $random)['notes']);
        self::assertTrue($scene->play($state([]), new Move('scene.end', 'ann'), $random)['ended']);
        self::assertSame(1, $scene->enter($state([], 'encounter'), $random)['encounters']);
        self::assertSame(3, $scene->enter($state(['encounters' => 2], 'encounter'), $random)['encounters']);
        self::assertArrayNotHasKey('encounters', $scene->enter($state([]), $random));
        self::assertNull($scene->outcome($state([])));
        self::assertNull($scene->outcome($state(['ended' => false])));
        self::assertEquals(Outcome::ended('scene-ended', new Statistics(['encounters' => 0, 'notes' => 0])), $scene->outcome($state(['ended' => true])));
        self::assertEquals(
            Outcome::ended('scene-ended', new Statistics(['encounters' => 2, 'notes' => 0], ['ann' => ['actions' => 3], 'bob' => ['actions' => 0]])),
            $scene->outcome($state(['ended' => true, 'encounters' => 2, 'notes' => 'x', 'actions' => ['ann' => 3, 'bob' => 'x']])),
        );
    }

    public function testASeatThatActsLateIsPlacedAfterTheSeatItWaitsFor(): void
    {
        $turn = new Turn(TurnOrder::Initiative, 1, ['a', 'b', 'c', 'd']);

        self::assertSame(['b', 'a', 'c', 'd'], $turn->delayed('b')?->order);
        self::assertSame(['b', 'c', 'a', 'd'], $turn->delayed('c')?->order);
    }

    public function testSeatsWithoutInitiativeKeepTheOrderOfTheTable(): void
    {
        self::assertSame(['a', 'b', 'c', 'd', 'e'], Turn::start(TurnOrder::Initiative, ['a', 'b', 'c', 'd', 'e'], [])->order);
        self::assertSame(['c', 'a', 'b', 'd', 'e'], Turn::start(TurnOrder::Initiative, ['a', 'b', 'c', 'd', 'e'], ['c' => 5])->order);
        self::assertSame(['d', 'c', 'a', 'b', 'e'], Turn::start(TurnOrder::Initiative, ['a', 'b', 'c', 'd', 'e'], ['c' => 0, 'd' => 5])->order);
        self::assertSame(['c', 'a', 'b'], Turn::start(TurnOrder::Initiative, ['a', 'b', 'c'], ['c' => -3])->order);

        $seats = array_map(static fn (int $index): string => 'seat'.$index, range(1, 60));

        self::assertSame($seats, Turn::start(TurnOrder::Initiative, $seats, [])->order);
        self::assertSame(['seat7', 'seat3', ...array_values(array_diff($seats, ['seat7', 'seat3']))], Turn::start(TurnOrder::Initiative, $seats, ['seat7' => 9, 'seat3' => 2])->order);
    }

    public function testAnIllegalMoveStaysAnIllegalMove(): void
    {
        self::assertSame(0, IllegalMove::because('no')->getCode());
    }
}
