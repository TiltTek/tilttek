<?php

declare(strict_types=1);

namespace Tilttek\GameEngine\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\GameEngine\Engine;
use Tilttek\GameEngine\Exception\InvalidGame;
use Tilttek\GameEngine\Fields;
use Tilttek\GameEngine\GameLog;
use Tilttek\GameEngine\GameState;
use Tilttek\GameEngine\Move;
use Tilttek\GameEngine\Option;
use Tilttek\GameEngine\OptionValues;
use Tilttek\GameEngine\Outcome;
use Tilttek\GameEngine\Scene\RoleplayingScene;
use Tilttek\GameEngine\Statistics;
use Tilttek\GameEngine\Store\InMemoryLogStore;
use Tilttek\GameEngine\TableOptions;
use Tilttek\GameEngine\Tests\Unit\Fixture\Race;
use Tilttek\GameEngine\Turn;
use Tilttek\GameEngine\TurnOrder;

/**
 * A state written as plain values and read back, as a shared cache keeps it, and everything else refused.
 */
#[CoversClass(GameState::class)]
#[CoversClass(Turn::class)]
#[CoversClass(Outcome::class)]
#[CoversClass(TableOptions::class)]
#[CoversClass(OptionValues::class)]
#[CoversClass(Fields::class)]
#[CoversClass(InMemoryLogStore::class)]
final class StateTest extends TestCase
{
    private const string SEED = '00112233445566778899aabbccddeeff00112233445566778899aabbccddeeff';

    public function testAStateIsWrittenAndReadBackAsPlainValues(): void
    {
        $engine = new Engine();
        $scene = new RoleplayingScene();
        $table = $engine->start($scene, 'scene:1', ['gm', 'ann', 'bob'], [], self::SEED);

        foreach ([Move::initiative(['ann' => 12, 'bob' => 3], 'gm'), Move::transition('start-encounter', 'gm'), new Move('scene.act', 'ann')] as $move) {
            $table = $engine->play($scene, $table, $move);
        }

        $array = $table->state->toArray();
        self::assertSame(['kind' => 'initiative', 'round' => 1, 'order' => ['ann', 'bob', 'gm'], 'current' => 0, 'waiting' => [], 'moves' => 1], $array['turn']);
        self::assertSame(['game_id' => 'scene:1', 'game' => 'roleplaying-scene', 'sequence' => 3, 'seats' => ['gm', 'ann', 'bob'], 'options' => ['leader' => true], 'phase' => 'encounter'], \array_slice($array, 0, 6));
        self::assertSame(['ann' => 12, 'bob' => 3], $array['initiative']);
        self::assertNull($array['outcome']);
        self::assertEquals($table->state, GameState::fromArray($array));
        $json = json_decode((string) json_encode($array), true, 32, \JSON_THROW_ON_ERROR);
        self::assertIsArray($json);
        self::assertEquals($table->state, GameState::fromArray($json));

        $over = $engine->play($scene, $table, new Move('scene.end', 'gm'));
        self::assertSame(['winners' => [], 'reason' => 'scene-ended', 'statistics' => ['table' => ['encounters' => 1, 'notes' => 0], 'seats' => ['gm' => ['actions' => 0], 'ann' => ['actions' => 1], 'bob' => ['actions' => 0]]]], $over->state->toArray()['outcome']);
        self::assertEquals($over->state, GameState::fromArray($over->state->toArray()));

        $simultaneous = new GameState('t', 'race', 0, ['ann', 'bob'], TableOptions::read(['target' => 10]), 'race', Turn::start(TurnOrder::Simultaneous, ['ann', 'bob'], []));
        self::assertEquals($simultaneous, GameState::fromArray($simultaneous->toArray()));
        self::assertSame(['ann', 'bob'], $simultaneous->toArray()['turn']['waiting']);
        $empty = new Turn(TurnOrder::Free, 1, []);
        self::assertEquals($empty, Turn::fromArray($empty->toArray()));
        self::assertSame(['a' => true, 'b' => 2, 'c' => 'x'], TableOptions::read(['a' => true, 'b' => 2, 'c' => 'x'])->values);

        foreach ([[0 => true], ['a' => null], ['a' => [1]]] as $values) {
            try {
                TableOptions::read($values);
                self::fail('Options of the wrong type were read.');
            } catch (InvalidGame $exception) {
                self::assertSame('The options of the table hold a value that is not a boolean, a whole number or a text under a name.', $exception->getMessage());
            }
        }
    }

    public function testAnythingElseIsRefused(): void
    {
        $engine = new Engine();
        $race = new Race();
        $valid = $engine->start($race, 'table:1', ['ann', 'bob'], [], self::SEED)->state->toArray();
        $turn = $valid['turn'];
        $won = Outcome::won(['ann'], 'x', new Statistics(['rounds' => 2], ['ann' => ['position' => 3]]))->toArray();

        foreach ([
            [['game_id' => 3] + $valid, 'The field "game_id" of a state is not a text.'],
            [['game_id' => '1'] + $valid, 'The identifier of a table "1" must be'],
            [['game' => 'Race'] + $valid, 'The name of a game "Race" must be'],
            [['sequence' => -1] + $valid, 'The field "sequence" of a state is not a whole number of 0 or more.'],
            [['sequence' => '1'] + $valid, 'The field "sequence" of a state is not a whole number of 0 or more.'],
            [['seats' => [1]] + $valid, 'An element of the field "seats" of a state is not a text.'],
            [['seats' => ['a' => 'ann']] + $valid, 'The field "seats" of a state is not a list.'],
            [['options' => ['target' => 1.5]] + $valid, 'The options of the table hold a value that is not a boolean, a whole number or a text under a name.'],
            [['options' => ['x']] + $valid, 'The field "options" of a state is not a mapping.'],
            [['phase' => null] + $valid, 'The field "phase" of a state is not a text.'],
            [['turn' => 'x'] + $valid, 'The field "turn" of a state is not a mapping.'],
            [['turn' => ['kind' => 'other'] + $turn] + $valid, "The turn order of a turn is not one of the engine's."],
            [['turn' => ['round' => 0] + $turn] + $valid, 'A turn has a round from 1, a seat acting among its order, and waiting seats among it.'],
            [['turn' => ['current' => 2] + $turn] + $valid, 'A turn has a round from 1, a seat acting among its order, and waiting seats among it.'],
            [['turn' => ['current' => 1, 'order' => []] + $turn] + $valid, 'A turn has a round from 1, a seat acting among its order, and waiting seats among it.'],
            [['turn' => ['waiting' => ['cy']] + $turn] + $valid, 'A turn has a round from 1, a seat acting among its order, and waiting seats among it.'],
            [['turn' => ['moves' => -1] + $turn] + $valid, 'The field "moves" of a turn is not a whole number of 0 or more.'],
            [['initiative' => ['ann' => 'high']] + $valid, 'An element of the field "initiative" of a state is not a whole number.'],
            [['data' => 'x'] + $valid, 'The field "data" of a state is not a mapping.'],
            [['outcome' => 'x'] + $valid, 'The field "outcome" of a state is not a mapping.'],
            [['outcome' => ['reason' => 3] + $won] + $valid, 'The field "reason" of an outcome is not a text.'],
            [['outcome' => ['winners' => [3]] + $won] + $valid, 'An element of the field "winners" of an outcome is not a text.'],
            [['outcome' => ['statistics' => ['table' => ['rounds' => 'x']]] + $won] + $valid, 'An element of the field "table" of its statistics is not a whole number.'],
            [['outcome' => ['statistics' => ['seats' => ['ann' => 'x']]] + $won] + $valid, 'The field "ann" of its statistics is not a mapping.'],
            [['outcome' => ['statistics' => ['seats' => ['ann' => ['position' => 'x']]]] + $won] + $valid, 'An element of the field "ann" of its statistics is not a whole number.'],
            [['outcome' => ['statistics' => ['seats' => [7 => ['position' => 1]]]] + $won] + $valid, 'The statistics of a seat are under "7", which is not a seat identifier.'],
            [['outcome' => ['statistics' => ['seats' => ['7a' => ['position' => 1]]]] + $won] + $valid, 'The identifier of a seat "7a" must be an ASCII letter followed by up to 127 ASCII letters, digits, ":", ".", "_" or "-".'],
            [['outcome' => ['statistics' => ['seats' => [['position' => 1]]]] + $won] + $valid, 'The field "seats" of its statistics is not a mapping.'],
            [['outcome' => ['statistics' => ['seats' => 'x']] + $won] + $valid, 'The field "seats" of its statistics is not a mapping.'],
        ] as [$data, $message]) {
            try {
                GameState::fromArray($data);
                self::fail(\sprintf('"%s" was accepted.', $message));
            } catch (InvalidGame $exception) {
                self::assertStringStartsWith($message, $exception->getMessage());
            }
        }

        $read = GameState::fromArray(['outcome' => $won] + $valid);
        self::assertEquals(Outcome::won(['ann'], 'x', new Statistics(['rounds' => 2], ['ann' => ['position' => 3]])), $read->outcome);
        self::assertEquals(Outcome::ended('x'), Outcome::fromArray(['reason' => 'x']));
        self::assertSame([], GameState::fromArray(['initiative' => [], 'data' => []] + $valid)->data);
    }

    public function testTheOptionsChosenKeepTheirTypeAndValue(): void
    {
        $offered = [Option::boolean('fast', false), Option::integer('target', 5, 1, 9), Option::choice('track', 'short', ['short', 'long'])];

        self::assertSame(['fast' => true, 'target' => 7, 'track' => 'long'], OptionValues::resolve($offered, ['fast' => true, 'target' => 7, 'track' => 'long'], 'the options of the table'));

        foreach ([['fast', 'yes', 'bool'], ['target', 'x', 'int'], ['track', 5, 'string']] as [$name, $value, $type]) {
            try {
                OptionValues::resolve($offered, [$name => $value], 'the options of the table');
                self::fail(\sprintf('A wrong type was accepted for "%s".', $name));
            } catch (InvalidGame $invalidGame) {
                self::assertStringContainsString(\sprintf('of type "%s"', $type), $invalidGame->getMessage());
            }
        }
    }

    public function testAListFieldIsReadAsItIsOrRefused(): void
    {
        self::assertSame([1, 2], Fields::list(['moves' => [1, 2]], 'moves', 'a log'));
        self::assertSame([], Fields::list([], 'moves', 'a log'));

        foreach ([['moves' => 'x'], ['moves' => ['a' => 1]]] as $data) {
            try {
                Fields::list($data, 'moves', 'a log');
                self::fail('A field that is not a list was accepted.');
            } catch (InvalidGame $invalidGame) {
                self::assertSame('The field "moves" of a log is not a list.', $invalidGame->getMessage());
            }
        }
    }

    public function testTheLogStoreInMemoryForgetsEverythingWhenReset(): void
    {
        $logs = new InMemoryLogStore();
        $logs->create(new GameLog('table:1', 'race', '1', self::SEED, ['ann']));
        $logs->reset();

        self::assertNull($logs->load('table:1'));
    }
}
