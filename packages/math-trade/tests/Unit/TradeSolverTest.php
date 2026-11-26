<?php

declare(strict_types=1);

namespace Tilttek\MathTrade\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\MathTrade\Exception\InvalidSeed;
use Tilttek\MathTrade\Exception\MathTradeException;
use Tilttek\MathTrade\Swap;
use Tilttek\MathTrade\Tests\Unit\Fixture\Lists;
use Tilttek\MathTrade\Trade;
use Tilttek\MathTrade\TradeCycle;
use Tilttek\MathTrade\TradeList;
use Tilttek\MathTrade\TradeResult;
use Tilttek\MathTrade\TradeSolver;

#[CoversClass(TradeSolver::class)]
#[CoversClass(TradeResult::class)]
#[CoversClass(TradeCycle::class)]
#[CoversClass(Trade::class)]
#[CoversClass(Swap::class)]
#[CoversClass(InvalidSeed::class)]
final class TradeSolverTest extends TestCase
{
    public function testTradesTwoOffersThatWishForEachOther(): void
    {
        $result = new TradeSolver()->solve(Lists::of(['alice' => ['a1' => ['b1']], 'bob' => ['b1' => ['a1']]]), 'seed');

        self::assertSame('seed', $result->seed);
        self::assertSame([['bob>b1>alice', 'alice>a1>bob']], Lists::cycles($result));
        self::assertSame(2, $result->cycles[0]->length());
        self::assertSame([], $result->unmatched);
        self::assertSame(2, $result->tradeCount());
        self::assertEquals(new Swap('alice', 'a1', 'bob', 'b1', 'bob'), $result->swapOf('a1'));
        self::assertEquals([new Swap('bob', 'b1', 'alice', 'a1', 'alice')], $result->forParticipant('bob'));
    }

    public function testFollowsALoopOfThree(): void
    {
        $list = Lists::of([
            'alice' => ['a1' => ['b1']],
            'bob' => ['b1' => ['c1']],
            'carol' => ['c1' => ['a1']],
        ]);
        $result = new TradeSolver()->solve($list, 'seed');

        self::assertSame([['bob>b1>alice', 'carol>c1>bob', 'alice>a1>carol']], Lists::cycles($result));
        self::assertEquals(new Swap('alice', 'a1', 'carol', 'b1', 'bob'), $result->swapOf('a1'));
        self::assertEquals(new Swap('bob', 'b1', 'alice', 'c1', 'carol'), $result->swapOf('b1'));
        self::assertEquals(new Swap('carol', 'c1', 'bob', 'a1', 'alice'), $result->swapOf('c1'));
        self::assertEquals(
            [new Trade('b1', 'bob', 'alice'), new Trade('c1', 'carol', 'bob'), new Trade('a1', 'alice', 'carol')],
            $result->trades(),
        );
    }

    public function testFindsNoTradeWhenNoWishIsReturned(): void
    {
        $result = new TradeSolver()->solve(Lists::of([
            'alice' => ['a1' => ['b1']],
            'bob' => ['b1' => ['c1']],
            'carol' => ['c1' => []],
        ]), 'seed');

        self::assertSame([], $result->cycles);
        self::assertSame([], $result->trades());
        self::assertSame([], $result->swaps());
        self::assertSame(['a1', 'b1', 'c1'], $result->unmatched);
        self::assertSame(0, $result->tradeCount());
        self::assertNull($result->swapOf('a1'));
        self::assertSame([], $result->forParticipant('alice'));
    }

    public function testSolvesAnEmptyList(): void
    {
        $result = new TradeSolver()->solve(new TradeList([]), 'seed');

        self::assertSame([], $result->cycles);
        self::assertSame([], $result->unmatched);
        self::assertSame('seed', $result->seed);
    }

    public function testKeepsAnOfferNobodyWishesFor(): void
    {
        $result = new TradeSolver()->solve(Lists::of([
            'alice' => ['a1' => ['b1'], 'a2' => ['b1']],
            'bob' => ['b1' => ['a1']],
        ]), 'seed');

        self::assertSame(['a2'], $result->unmatched);
        self::assertSame(['a1', 'b1'], array_map(static fn (Swap $swap): string => $swap->sent, $result->swaps()));
    }

    public function testPrefersMoreTradesToBetterWishes(): void
    {
        // Alice and Bob would swap at their first wishes, but the loop of three trades one offer more.
        $result = new TradeSolver()->solve(Lists::of([
            'alice' => ['a1' => ['b1', 'c1']],
            'bob' => ['b1' => ['a1']],
            'carol' => ['c1' => ['b1']],
        ]), 'seed');

        self::assertSame(3, $result->tradeCount());
        self::assertSame('c1', $result->swapOf('a1')?->received);
    }

    public function testPrefersBetterWishesAmongAsManyTrades(): void
    {
        $list = Lists::of([
            'alice' => ['a1' => ['c1', 'b1']],
            'bob' => ['b1' => ['a1']],
            'carol' => ['c1' => ['a1']],
        ]);
        $result = new TradeSolver()->solve($list, 'seed');

        self::assertSame('c1', $result->swapOf('a1')?->received);
        self::assertSame(['b1'], $result->unmatched);
        self::assertSame(2, Lists::cost($list, $result));
    }

    public function testTradesAnyOfferOfAGroupAtOnePriority(): void
    {
        $list = Lists::of([
            'alice' => ['a1' => [['b1', 'c1'], 'd1']],
            'bob' => ['b1' => ['d1']],
            'carol' => ['c1' => ['a1']],
            'dave' => ['d1' => ['b1']],
        ]);
        $result = new TradeSolver()->solve($list, 'seed');

        self::assertSame(4, $result->tradeCount());
        self::assertSame('c1', $result->swapOf('a1')?->received);
    }

    public function testSplitsSeveralLoops(): void
    {
        $result = new TradeSolver()->solve(Lists::of([
            'alice' => ['a1' => ['b1']],
            'bob' => ['b1' => ['a1']],
            'carol' => ['c1' => ['e1']],
            'dave' => ['d1' => []],
            'erin' => ['e1' => ['c1']],
        ]), 'seed');

        self::assertSame([['bob>b1>alice', 'alice>a1>bob'], ['erin>e1>carol', 'carol>c1>erin']], Lists::cycles($result));
        self::assertSame(['d1'], $result->unmatched);
    }

    public function testAParticipantWithSeveralOffersTradesEach(): void
    {
        $result = new TradeSolver()->solve(Lists::of([
            'alice' => ['a1' => ['b1'], 'a2' => ['c1']],
            'bob' => ['b1' => ['a1']],
            'carol' => ['c1' => ['a2']],
        ]), 'seed');

        self::assertEquals(
            [new Swap('alice', 'a1', 'bob', 'b1', 'bob'), new Swap('alice', 'a2', 'carol', 'c1', 'carol')],
            $result->forParticipant('alice'),
        );
    }

    public function testAParticipantReceivesAGameOnceAtMost(): void
    {
        // Alice offers two games and wishes for Azul from both; two copies of Azul are offered.
        $list = Lists::of([
            'alice' => ['a1' => ['b1@azul', 'c1@azul'], 'a2' => ['b1@azul', 'c1@azul']],
            'bob' => ['b1@azul' => ['a1', 'a2']],
            'carol' => ['c1@azul' => ['a1', 'a2']],
        ]);

        foreach (['one', 'two', 'three', 'four', 'five'] as $seed) {
            $result = new TradeSolver()->solve($list, $seed);
            $received = array_map(static fn (Swap $swap): string => $swap->received, $result->forParticipant('alice'));

            self::assertCount(1, $received);
            self::assertSame(2, $result->tradeCount());
        }
    }

    public function testWithoutGamesTheSameParticipantMayReceiveBothCopies(): void
    {
        $result = new TradeSolver()->solve(Lists::of([
            'alice' => ['a1' => ['b1', 'c1'], 'a2' => ['b1', 'c1']],
            'bob' => ['b1' => ['a1', 'a2']],
            'carol' => ['c1' => ['a1', 'a2']],
        ]), 'seed');

        self::assertSame(4, $result->tradeCount());
    }

    public function testCopiesOfAGameWishedFromOneOfferNeedNoGuard(): void
    {
        $result = new TradeSolver()->solve(Lists::of([
            'alice' => ['a1' => ['b1@azul', 'c1@azul'], 'a2' => ['d1']],
            'bob' => ['b1@azul' => ['a1']],
            'carol' => ['c1@azul' => ['a1']],
            'dave' => ['d1' => ['a2']],
        ]), 'seed');

        self::assertSame(4, $result->tradeCount());
        self::assertSame('b1', $result->swapOf('a1')?->received);
    }

    public function testASingleCopyWishedFromTwoOffersNeedsNoGuard(): void
    {
        $result = new TradeSolver()->solve(Lists::of([
            'alice' => ['a1' => ['b1@azul', 'c1'], 'a2' => ['b1@azul']],
            'bob' => ['b1@azul' => ['a2']],
            'carol' => ['c1' => ['a1']],
        ]), 'seed');

        self::assertSame(4, $result->tradeCount());
        self::assertSame('b1', $result->swapOf('a2')?->received);
        self::assertSame('c1', $result->swapOf('a1')?->received);
    }

    public function testTheGuardKeepsTheBestPriorityOfAnyCopy(): void
    {
        // Through the guard, a1 takes Azul at the priority of its first wish: Bob's Catan stays.
        $list = Lists::of([
            'alice' => ['a1' => ['b1@azul', 'd1', 'c1@azul'], 'a2' => ['c1@azul']],
            'bob' => ['b1@azul' => ['x1']],
            'carol' => ['c1@azul' => ['a1']],
            'dave' => ['d1' => ['a1']],
            'xavier' => ['x1' => []],
        ]);
        $result = new TradeSolver()->solve($list, 'seed');

        self::assertSame('c1', $result->swapOf('a1')?->received);
        self::assertSame(['a2', 'b1', 'd1', 'x1'], $result->unmatched);
    }

    public function testGamesOfDifferentParticipantsAreGuardedApart(): void
    {
        $result = new TradeSolver()->solve(Lists::of([
            'alice' => ['a1' => ['c1@azul', 'd1@azul'], 'a2' => ['c1@azul', 'd1@azul']],
            'bob' => ['b1' => ['c1@azul', 'd1@azul'], 'b2' => ['c1@azul', 'd1@azul']],
            'carol' => ['c1@azul' => ['a1', 'b1']],
            'dave' => ['d1@azul' => ['a2', 'b2']],
        ]), 'seed');

        self::assertSame(4, $result->tradeCount());
        self::assertCount(1, $result->forParticipant('alice'));
        self::assertCount(1, $result->forParticipant('bob'));
    }

    public function testReadsIdentifiersMadeOfDigits(): void
    {
        $result = new TradeSolver()->solve(Lists::of([
            '1' => ['10@7' => ['20@8', '30@8'], '11@7' => ['20@8', '30@8']],
            '2' => ['20@8' => ['10@7']],
            '3' => ['30@8' => ['11@7']],
        ]), '42');

        self::assertSame(2, $result->tradeCount());
        self::assertSame(['11', '30'], $result->unmatched);
        self::assertEquals([new Swap('1', '10', '2', '20', '2')], $result->forParticipant('1'));
    }

    public function testTheSameSeedGivesTheSameResult(): void
    {
        $list = $this->symmetric();
        $solver = new TradeSolver();

        self::assertEquals($solver->solve($list, 'convention-2026'), $solver->solve($list, 'convention-2026'));
    }

    public function testTheSeedBreaksTiesBetweenEqualResults(): void
    {
        $list = $this->symmetric();
        $solver = new TradeSolver();
        $outcomes = [];

        foreach (range(1, 30) as $seed) {
            $result = $solver->solve($list, 'seed '.$seed);
            self::assertSame(3, $result->tradeCount());
            $outcomes[$result->swapOf('a1')->received ?? 'none'] = true;
        }

        self::assertEqualsCanonicalizing(['b1', 'c1'], array_keys($outcomes));
    }

    public function testTradesAnOfferWishedForAtASecondPriorityOnlyWhenNothingBetterTrades(): void
    {
        // The two copies of the game g are interchangeable for p0, whose offers o1 and o2 each wish for one; o2 and o3
        // wish for each other at their first wishes, so the offer o0, which p1 wishes for second, never trades.
        $list = Lists::of([
            'p0' => ['o0@h' => ['o3'], 'o1@g' => ['o4'], 'o2@g' => ['o4', 'o3']],
            'p1' => ['o3@g' => ['o2', 'o0'], 'o4@g' => ['o2', 'o0', 'o1']],
        ]);

        foreach (range('a', 'l') as $seed) {
            $result = new TradeSolver()->solve($list, $seed);

            self::assertSame(2, $result->tradeCount(), $seed);
            self::assertNull($result->swapOf('o0'), $seed);
        }
    }

    public function testAGuardedGameCostsAsMuchAsAnOfferWishedForAtTheSamePriority(): void
    {
        // o1 receives o3 at its first priority, or a copy of g at its second, which it prefers less.
        $list = Lists::of([
            'p0' => ['o0' => ['o4'], 'o1@g' => ['o3', ['o2', 'o4']]],
            'p1' => ['o2@g' => ['o1'], 'o3' => [['o1', 'o4']]],
            'p2' => ['o4@g' => []],
        ]);

        foreach (range('a', 'l') as $seed) {
            $result = new TradeSolver()->solve($list, $seed);

            self::assertSame('o3', $result->swapOf('o1')?->received, $seed);
        }
    }

    /**
     * The tie a seed picks between equal results does not change from one version to the next.
     *
     * @param array<string, array<string, list<string|list<string>>>> $participants
     * @param list<string>                                            $sent         the offers sent, in the order of the swaps
     */
    #[DataProvider('pinnedTies')]
    public function testASeedPicksTheSameTieOnEveryVersion(array $participants, string $seed, array $sent): void
    {
        $result = new TradeSolver()->solve(Lists::of($participants), $seed);

        self::assertSame($sent, array_map(static fn (Swap $swap): string => $swap->sent, $result->swaps()));
    }

    /**
     * @return iterable<string, array{array<string, array<string, list<string|list<string>>>>, string, list<string>}>
     */
    public static function pinnedTies(): iterable
    {
        yield 'two wishers of one copy' => [
            ['p0' => ['o0' => [], 'o1' => ['o3'], 'o2' => ['o3']], 'p1' => ['o3@g' => [['o1', 'o2'], 'o0']]],
            'b',
            ['o2', 'o3'],
        ];
        yield 'a group over two copies' => [
            ['p0' => ['o0@g' => [['o2', 'o1']]], 'p1' => ['o1@h' => ['o0']], 'p2' => ['o2@g' => ['o0']]],
            'a',
            ['o0', 'o1'],
        ];
        yield 'two copies wished from one offer' => [
            ['p0' => ['o0@h' => [['o2', 'o1']]], 'p1' => ['o1@h' => ['o0'], 'o2@h' => ['o0']]],
            'a',
            ['o0', 'o1'],
        ];
    }

    public function testTheOrderOfTheListDoesNotChangeTheResult(): void
    {
        $solver = new TradeSolver();
        $one = $solver->solve(Lists::of([
            'alice' => ['a1' => [['b1', 'c1']], 'a2' => [['c1', 'b1']]],
            'bob' => ['b1' => [['a1', 'a2', 'c1']]],
            'carol' => ['c1' => [['a2', 'a1', 'b1']]],
        ]), 'seed');
        $other = $solver->solve(Lists::of([
            'carol' => ['c1' => [['b1', 'a1', 'a2']]],
            'bob' => ['b1' => [['c1', 'a2', 'a1']]],
            'alice' => ['a2' => [['b1', 'c1']], 'a1' => [['c1', 'b1']]],
        ]), 'seed');

        self::assertEquals($one->cycles, $other->cycles);
        self::assertEquals($one->swaps(), $other->swaps());
        self::assertSame($one->unmatched, $other->unmatched);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidSeeds(): iterable
    {
        yield 'empty' => [''];
        yield 'too long' => [str_repeat('s', 257)];
        yield 'a control character' => ["seed\n"];
        yield 'not UTF-8' => ["\xc3"];
    }

    #[DataProvider('invalidSeeds')]
    public function testRefusesASeed(string $seed): void
    {
        $this->expectException(InvalidSeed::class);
        $this->expectExceptionMessageIsOrContains('A seed is 1 to 256 bytes of UTF-8 without a control character.');

        new TradeSolver()->solve(new TradeList([]), $seed);
    }

    public function testAcceptsASeedOf256Bytes(): void
    {
        self::assertSame(str_repeat('s', 256), new TradeSolver()->solve(new TradeList([]), str_repeat('s', 256))->seed);
    }

    public function testASeedRefusalIsAnExceptionOfTheLibrary(): void
    {
        self::assertInstanceOf(MathTradeException::class, InvalidSeed::because('x'));
    }

    /**
     * Three participants who each wish for both others equally: two loops of three trade everything.
     */
    private function symmetric(): TradeList
    {
        return Lists::of([
            'alice' => ['a1' => [['b1', 'c1']]],
            'bob' => ['b1' => [['a1', 'c1']]],
            'carol' => ['c1' => [['a1', 'b1']]],
        ]);
    }
}
