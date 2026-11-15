<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Tests\Unit\Store;

use DateTimeImmutable;
use JsonException;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Leaderboards\Boards;
use Tilttek\Leaderboards\Contribution;
use Tilttek\Leaderboards\Score;
use Tilttek\Leaderboards\Store\Valkey\ValkeyScoreStore;
use Tilttek\Leaderboards\Tests\Unit\Fixture\ScriptedConnection;
use UnexpectedValueException;

/**
 * The score store in Valkey without a server: the keys and the arguments it sends, and how it reads what it is answered
 * — values it did not write, replies of another shape than a server gives.
 */
#[CoversClass(ValkeyScoreStore::class)]
final class ValkeyScoreStoreRepliesTest extends TestCase
{
    public function testTheKeysStartWithThePrefixAsAHashTag(): void
    {
        $connection = new ScriptedConnection(['EVAL' => 1]);
        $store = new ValkeyScoreStore($connection, self::boards(), 'tests');

        self::assertTrue($store->add(new Contribution('games@abc', '2026-11', ResourceReference::fromString('member:1'), 'partie/é', 4, new DateTimeImmutable('2026-11-03 10:00:00.000002 UTC'), [3])));

        $sent = $connection->sent[0];
        unset($sent[1]);

        self::assertSame([
            0 => 'EVAL',
            2 => '6',
            3 => '{tests}:contribution',
            4 => '{tests}:activity:["partie/é"]',
            5 => '{tests}:of:["games@abc","2026-11","member:1"]',
            6 => '{tests}:time',
            7 => '{tests}:board:["games"]',
            8 => '{tests}:member:["member:1"]',
            9 => '["games@abc","partie/é"]',
            10 => '["2026-11","member:1",4,1793700000000002,[3]]',
            11 => '1793700000.000002',
        ], $sent);
    }

    public function testAContributionIsAddedOnlyWhenTheScriptSaysSo(): void
    {
        $store = new ValkeyScoreStore(new ScriptedConnection(['EVAL' => 0]), self::boards(), 'tests');

        self::assertFalse($store->add(new Contribution('games', '2026-11', ResourceReference::fromString('member:1'), 'k1', 1, new DateTimeImmutable('2026-11-03 10:00 UTC'))));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function corruptContributions(): iterable
    {
        yield 'a period that is not a string' => ['[1,"member:1",1,0,[]]'];
        yield 'a member that is not a string' => ['["2026-11",1,1,0,[]]'];
        yield 'an amount that is not an integer' => ['["2026-11","member:1","1",0,[]]'];
        yield 'a moment that is not an integer' => ['["2026-11","member:1",1,"0",[]]'];
        yield 'tie-breaks that are not a list' => ['["2026-11","member:1",1,0,"3"]'];
        yield 'a value missing' => ['["2026-11","member:1",1,0]'];
    }

    #[DataProvider('corruptContributions')]
    public function testAContributionTheStoreDidNotWriteIsRefused(string $held): void
    {
        $store = new ValkeyScoreStore(new ScriptedConnection(['ZRANGE' => ['["games","k1"]'], 'HMGET' => [$held]]), self::boards(), 'tests');

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains('The contribution "["games","k1"]" is not one the leaderboards wrote.');

        $store->contributions('games', '2026-11', ResourceReference::fromString('member:1'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function corruptScores(): iterable
    {
        yield 'a value that is not an integer' => ['["7",0,[],"key"]'];
        yield 'a moment that is not an integer' => ['[7,"0",[],"key"]'];
        yield 'tie-breaks that are not a list' => ['[7,0,"3","key"]'];
        yield 'not a list' => ['"7"'];
    }

    #[DataProvider('corruptScores')]
    public function testAScoreTheStoreDidNotWriteIsRefused(string $held): void
    {
        $store = new ValkeyScoreStore(new ScriptedConnection(['HGET' => $held]), self::boards(), 'tests');

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains('The score of "member:1" on "games" in "2026-11" is not one the leaderboards wrote.');

        $store->score('games', '2026-11', ResourceReference::fromString('member:1'));
    }

    public function testAHeldValueThatIsNotJsonIsRefused(): void
    {
        $store = new ValkeyScoreStore(new ScriptedConnection(['HGET' => '[7,0,[]']), self::boards(), 'tests');

        $this->expectException(JsonException::class);

        $store->score('games', '2026-11', ResourceReference::fromString('member:1'));
    }

    public function testAHeldValueNestsThreeArraysAtMost(): void
    {
        $threeArrays = new ValkeyScoreStore(new ScriptedConnection(['HGET' => '[7,0,[[3]],"key"]']), self::boards(), 'tests');

        self::assertSame([], $threeArrays->score('games', '2026-11', ResourceReference::fromString('member:1'))?->tieBreaks);

        $fourArrays = new ValkeyScoreStore(new ScriptedConnection(['HGET' => '[7,0,[[[3]]],"key"]']), self::boards(), 'tests');

        $this->expectException(JsonException::class);
        $this->expectExceptionMessageIsOrContains('Maximum stack depth exceeded');

        $fourArrays->score('games', '2026-11', ResourceReference::fromString('member:1'));
    }

    public function testOnlyTheIntegersOfHeldTieBreaksAreRead(): void
    {
        $store = new ValkeyScoreStore(new ScriptedConnection(['HGET' => '[7,1000002,["3",4],"key"]']), self::boards(), 'tests');

        $score = $store->score('games', '2026-11', ResourceReference::fromString('member:1'));

        self::assertInstanceOf(Score::class, $score);
        self::assertSame([4], $score->tieBreaks);
        self::assertSame(7, $score->value);
        self::assertSame('1970-01-01 00:00:01.000002', $score->reachedAt->format('Y-m-d H:i:s.u'));
    }

    public function testOnlyTheStringsOfAHeldFieldAreRead(): void
    {
        $store = new ValkeyScoreStore(new ScriptedConnection(['SMEMBERS' => ['[1,"games","2026-11"]'], 'HMGET' => ['[7,0,[],"key"]']]), self::boards(), 'tests');

        $scores = $store->of(ResourceReference::fromString('member:1'));

        self::assertCount(1, $scores);
        self::assertSame(['games', '2026-11', 'member:1', 7], [$scores[0]->board, $scores[0]->period, $scores[0]->member->toString(), $scores[0]->value]);
    }

    public function testOnlyTheStringsOfAListReplyAreRead(): void
    {
        $connection = new ScriptedConnection([
            'ZRANGE' => [7, '["games","k1"]', null],
            'HMGET' => ['["2026-11","member:1",1,0,[]]'],
        ]);
        $store = new ValkeyScoreStore($connection, self::boards(), 'tests');

        $contributions = $store->contributions('games', '2026-11', ResourceReference::fromString('member:1'));

        self::assertCount(1, $contributions);
        self::assertSame(['games', 'k1'], [$contributions[0]->board, $contributions[0]->activity]);
        self::assertSame(['HMGET', '{tests}:contribution', '["games","k1"]'], $connection->sent[1]);
    }

    public function testAnIntegerReplyIsReadFromAStringAndNothingIsZero(): void
    {
        self::assertSame(3, new ValkeyScoreStore(new ScriptedConnection(['ZCARD' => '3']), self::boards(), 'tests')->participants('games', '2026-11'));
        self::assertSame(3, new ValkeyScoreStore(new ScriptedConnection(['ZCARD' => 3]), self::boards(), 'tests')->participants('games', '2026-11'));
        self::assertSame(0, new ValkeyScoreStore(new ScriptedConnection(), self::boards(), 'tests')->participants('games', '2026-11'));
        self::assertSame(0, new ValkeyScoreStore(new ScriptedConnection(['ZCARD' => ['3']]), self::boards(), 'tests')->participants('games', '2026-11'));
    }

    public function testAListReplyOfAnotherShapeIsNothing(): void
    {
        $store = new ValkeyScoreStore(new ScriptedConnection(['ZRANGE' => 'OK']), self::boards(), 'tests');

        self::assertSame([], $store->contributions('games', '2026-11', ResourceReference::fromString('member:1')));
    }

    public function testTheBatchBoundsAreBatches(): void
    {
        $this->expectNotToPerformAssertions();

        new ValkeyScoreStore(new ScriptedConnection(), self::boards(), 'tests', 1);
        new ValkeyScoreStore(new ScriptedConnection(), self::boards(), 'tests', 10_000);
    }

    public function testAScoreIsLookedUpByItsBoardPeriodAndMember(): void
    {
        $connection = new ScriptedConnection();

        self::assertNotInstanceOf(Score::class, new ValkeyScoreStore($connection, self::boards(), 'tests')->score('games', '2026-11', ResourceReference::fromString('member:1')));
        self::assertSame([['HGET', '{tests}:score', '["games","2026-11","member:1"]']], $connection->sent);
    }

    /**
     * @return iterable<string, array{list<int>}>
     */
    public static function orders(): iterable
    {
        foreach ([[0, 1, 2, 3], [3, 2, 1, 0], [1, 0, 3, 2], [2, 0, 3, 1], [1, 3, 0, 2], [2, 3, 1, 0], [0, 2, 1, 3], [3, 0, 2, 1]] as $order) {
            yield implode('', $order) => [$order];
        }
    }

    /**
     * @param list<int> $order
     */
    #[DataProvider('orders')]
    public function testTheScoresOfAMemberAreSortedByBoardThenPeriod(array $order): void
    {
        $scores = [['arcade', '2026-11'], ['arcade', '2026-12'], ['games', '2026-11'], ['games', '2026-12']];
        $fields = [];
        $helds = [];

        foreach ($order as $index) {
            $fields[] = json_encode($scores[$index], \JSON_THROW_ON_ERROR);
            $helds[] = \sprintf('[%d,1793700000000002,[],"key"]', $index);
        }

        $store = new ValkeyScoreStore(new ScriptedConnection(['SMEMBERS' => $fields, 'HMGET' => $helds]), self::boards(), 'tests');

        self::assertSame(
            [['arcade', '2026-11', 0], ['arcade', '2026-12', 1], ['games', '2026-11', 2], ['games', '2026-12', 3]],
            array_map(static fn (Score $score): array => [$score->board, $score->period, $score->value], $store->of(ResourceReference::fromString('member:1'))),
        );
    }

    public function testAFieldWithoutItsValueAndAValueWithoutItsFieldAreLeftOut(): void
    {
        $store = new ValkeyScoreStore(new ScriptedConnection([
            'SMEMBERS' => ['["arcade","2026-11"]', '["games","2026-11"]'],
            'HMGET' => [null, '[3,0,[],"key"]', '[9,0,[],"key"]'],
        ]), self::boards(), 'tests');

        self::assertSame(
            [['games', '2026-11', 3]],
            array_map(static fn (Score $score): array => [$score->board, $score->period, $score->value], $store->of(ResourceReference::fromString('member:1'))),
        );
    }

    public function testNothingIsAskedForWithoutFields(): void
    {
        $connection = new ScriptedConnection(['SMEMBERS' => []]);

        self::assertSame([], new ValkeyScoreStore($connection, self::boards(), 'tests')->of(ResourceReference::fromString('member:1')));
        self::assertSame([['SMEMBERS', '{tests}:member-score:["member:1"]']], $connection->sent);
    }

    public function testEveryContributionIsReadWithItsOwnValues(): void
    {
        $store = new ValkeyScoreStore(new ScriptedConnection([
            'ZRANGE' => ['["games","k1"]', '["games","k2"]'],
            'HMGET' => ['["2026-11","member:1",4,1793700000000002,[3]]', '["2026-12","member:2",9,1796000000000007,[]]'],
        ]), self::boards(), 'tests');

        $read = array_map(
            static fn (Contribution $contribution): array => [
                $contribution->board,
                $contribution->period,
                $contribution->member->toString(),
                $contribution->activity,
                $contribution->amount,
                $contribution->occurredAt->format('U.u'),
                $contribution->tieBreaks,
            ],
            $store->contributions('games', '2026-11', ResourceReference::fromString('member:1')),
        );

        self::assertSame([
            ['games', '2026-11', 'member:1', 'k1', 4, '1793700000.000002', [3]],
            ['games', '2026-12', 'member:2', 'k2', 9, '1796000000.000007', []],
        ], $read);
    }

    private static function boards(): Boards
    {
        return Boards::fromArray([
            'games' => ['measure' => 'total', 'verbs' => ['game.played'], 'tie_breaks' => [['measure' => 'lowest', 'order' => 'asc'], ['measure' => 'highest']]],
        ]);
    }
}
