<?php

declare(strict_types=1);

namespace Tilttek\GameEngine\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\GameEngine\Exception\InvalidGame;
use Tilttek\GameEngine\GameLog;
use Tilttek\GameEngine\LoggedMove;
use Tilttek\GameEngine\Move;
use Tilttek\GameEngine\Randomness;

#[CoversClass(GameLog::class)]
#[CoversClass(LoggedMove::class)]
#[CoversClass(Move::class)]
#[CoversClass(Randomness::class)]
final class LogTest extends TestCase
{
    private const string SEED = '00112233445566778899aabbccddeeff00112233445566778899aabbccddeeff';

    public function testAMoveHoldsPlainValues(): void
    {
        $move = new Move('race.step', 'ann', ['steps' => 2, 'note' => 'fast', 'nested' => ['a' => [1.0]]]);

        self::assertSame([2, 'fast', null, null], [$move->int('steps'), $move->text('note'), $move->int('note'), $move->text('steps')]);
        self::assertNull($move->int('none'));
        self::assertFalse($move->isEngine());
        self::assertTrue(Move::endTurn(null)->isEngine());
        self::assertEquals(new Move('engine.end-turn', 'ann'), Move::endTurn('ann'));
        self::assertEquals(new Move('engine.transition', null, ['transition' => 'go']), Move::transition('go'));
        self::assertEquals(new Move('engine.initiative', 'gm', ['scores' => ['ann' => 3]]), Move::initiative(['ann' => 3], 'gm'));
        self::assertEquals(new Move('engine.delay', 'ann', ['after' => 'bob']), Move::delay('ann', 'bob'));
        self::assertCount(1, new Move('x', null, ['text' => str_repeat('a', Move::MAX_PAYLOAD - 11)])->payload);

        foreach ([
            [static fn (): Move => new Move('x', null, ['text' => str_repeat('a', Move::MAX_PAYLOAD - 10)]), 'The payload of the move "x" is longer than 16384 bytes.'],
            [static fn (): Move => new Move('x', null, ['deep' => [[[[[[[[1]]]]]]]]]), 'The payload of the move "x" is not plain values 8 levels deep: '],
            [static fn (): Move => new Move('x', null, ['bad' => "\xC3\x28"]), 'The payload of the move "x" is not plain values 8 levels deep: '],
            [static fn (): Move => new Move('x', ' ann'), 'The identifier of a seat " ann"'],
            [static fn (): Move => new Move('X'), 'The move type "X"'],
        ] as [$make, $message]) {
            try {
                $make();
                self::fail(\sprintf('"%s" was accepted.', $message));
            } catch (InvalidGame $exception) {
                self::assertStringStartsWith($message, $exception->getMessage());
            }
        }

        self::assertCount(1, new Move('x', null, ['deep' => [[[[[[1]]]]]]])->payload);
    }

    public function testALogNumbersItsMovesAndIsReadBackFromPlainValues(): void
    {
        $log = new GameLog('table:1', 'race', '2', self::SEED, ['ann', 'bob'], ['target' => 12, 'fast' => true, 'track' => 'long']);
        $log = $log->with(new LoggedMove(1, new Move('race.roll', 'ann'), true))->with(new LoggedMove(2, Move::endTurn(null)));

        self::assertSame(2, $log->sequence());
        self::assertEquals(new LoggedMove(2, Move::endTurn(null)), $log->last());
        self::assertEquals(new LoggedMove(1, new Move('race.roll', 'ann'), true), $log->move(1));
        self::assertSame(1, $log->withoutLast()->sequence());
        self::assertNull($log->withoutLast()->withoutLast()->last());
        self::assertEquals($log, GameLog::fromArray($log->toArray()));
        self::assertSame(['type' => 'engine.end-turn', 'seat' => null, 'payload' => [], 'drew' => false], $log->toArray()['moves'][1]);

        try {
            $log->move(3);
            self::fail('A missing move was found.');
        } catch (InvalidGame $invalidGame) {
            self::assertSame('The log of the table "table:1" has no move 3.', $invalidGame->getMessage());
        }

        try {
            $log->move(0);
            self::fail('A move 0 was found.');
        } catch (InvalidGame $invalidGame) {
            self::assertSame('The log of the table "table:1" has no move 0.', $invalidGame->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('The move 2 of the log of the table "table:1" is numbered 3.');

        $log->withoutLast()->with(new LoggedMove(3, Move::endTurn(null)));
    }

    public function testAHeaderIsAtTheRevisionItIsGivenElseAtTheNumberOfMoves(): void
    {
        $log = new GameLog('table:1', 'race', '2', self::SEED, ['ann', 'bob'])->with(new LoggedMove(1, new Move('race.roll', 'ann')));

        self::assertSame(1, $log->header()->revision);
        self::assertSame(7, $log->header(7)->revision);
        self::assertSame(1, $log->header(1)->revision);
        self::assertSame(1, $log->header(7)->sequence);
    }

    public function testABrokenLogIsRefused(): void
    {
        $valid = new GameLog('table:1', 'race', '1', self::SEED, ['ann'])->toArray();

        foreach ([
            [['game_id' => 3] + $valid, 'The field "game_id" of a log is not a text.'],
            [['seed' => 'abc'] + $valid, 'A seed is 64 lowercase hexadecimal digits.'],
            [['seed' => strtoupper(self::SEED)] + $valid, 'A seed is 64 lowercase hexadecimal digits.'],
            [['seats' => 'ann'] + $valid, 'The field "seats" of a log is not a list.'],
            [['seats' => ['a' => 'ann']] + $valid, 'The field "seats" of a log is not a list.'],
            [['seats' => [3]] + $valid, 'An element of the field "seats" of a log is not a text.'],
            [['options' => ['a']] + $valid, 'The field "options" of a log is not a mapping.'],
            [['options' => 'a'] + $valid, 'The field "options" of a log is not a mapping.'],
            [['options' => ['a' => 1.5]] + $valid, 'The option "a" of a log is not a boolean, a whole number or a text.'],
            [['moves' => ['x']] + $valid, 'The move 1 of a log is not a mapping.'],
            [['moves' => [['seat' => 'ann']]] + $valid, 'The field "type" of a log is not a text.'],
            [['moves' => [['type' => 'x', 'seat' => 3]]] + $valid, 'The field "seat" of a log is not a text.'],
            [['moves' => [['type' => 'x', 'payload' => ['a']]]] + $valid, 'The field "payload" of a log is not a mapping.'],
            [['game' => 'Race'] + $valid, 'The name of a game "Race"'],
            [['game_id' => '1'] + $valid, 'The identifier of a table "1"'],
        ] as [$data, $message]) {
            try {
                GameLog::fromArray($data);
                self::fail(\sprintf('"%s" was accepted.', $message));
            } catch (InvalidGame $exception) {
                self::assertStringStartsWith($message, $exception->getMessage());
            }
        }

        $moves = GameLog::fromArray(['moves' => [['type' => 'x', 'drew' => 'yes'], ['type' => 'y', 'drew' => true, 'payload' => []]]] + $valid)->moves;
        self::assertSame([false, true], [$moves[0]->drew, $moves[1]->drew]);
        self::assertSame([], GameLog::fromArray(['options' => []] + $valid)->options);
    }

    public function testALogHoldsAtMostItsLimitOfMoves(): void
    {
        $moves = array_map(static fn (int $sequence): LoggedMove => new LoggedMove($sequence, Move::endTurn(null)), range(1, GameLog::MAX_MOVES + 1));

        self::assertSame(GameLog::MAX_MOVES, new GameLog('table:1', 'race', '1', self::SEED, ['ann'], [], \array_slice($moves, 0, -1))->sequence());

        $this->expectExceptionMessageIsOrContains('The log of the table "table:1" holds more than 100000 moves.');

        new GameLog('table:1', 'race', '1', self::SEED, ['ann'], [], $moves);
    }

    public function testChanceIsDrawnFromTheSeedAndThePlaceInTheLog(): void
    {
        $first = new Randomness(self::SEED, 1);

        self::assertFalse($first->drawn());
        $values = [$first->int(1, 1_000_000), $first->pick(['a', 'b', 'c', 'd', 'e', 'f', 'g']), $first->shuffle(range(1, 10))];
        self::assertTrue($first->drawn());

        $again = new Randomness(self::SEED, 1);
        self::assertSame($values, [$again->int(1, 1_000_000), $again->pick(['a', 'b', 'c', 'd', 'e', 'f', 'g']), $again->shuffle(range(1, 10))]);
        self::assertNotSame($values[0], new Randomness(self::SEED, 2)->int(1, 1_000_000));
        self::assertNotSame($values[0], new Randomness(str_repeat('0', 64), 1)->int(1, 1_000_000));
        self::assertSame(new Randomness(self::SEED, 3)->randomizer()->getInt(1, 1_000_000), new Randomness(self::SEED, 3)->int(1, 1_000_000));
        $shuffled = new Randomness(self::SEED, 4)->shuffle(range(1, 10));
        sort($shuffled);
        self::assertSame(range(1, 10), $shuffled);
        self::assertSame('x', new Randomness(self::SEED, 5)->pick(['x']));
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/D', Randomness::seed());
        self::assertNotSame(Randomness::seed(), Randomness::seed());

        $this->expectExceptionMessageIsOrContains('A seed is 64 lowercase hexadecimal digits.');

        new Randomness('zz', 1)->int(1, 2);
    }
}
