<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Tests\Unit\Swiss;

use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Tournament\Exception\InvalidTournament;
use Tilttek\Tournament\Outcome;
use Tilttek\Tournament\Pairing;
use Tilttek\Tournament\PairingReason;
use Tilttek\Tournament\ReasonKind;
use Tilttek\Tournament\Records;
use Tilttek\Tournament\Result;
use Tilttek\Tournament\Round;
use Tilttek\Tournament\Scoring;
use Tilttek\Tournament\Swiss\BoundedMatcher;
use Tilttek\Tournament\Swiss\ExactMatcher;
use Tilttek\Tournament\Swiss\SwissPairer;

#[CoversClass(SwissPairer::class)]
#[CoversClass(ExactMatcher::class)]
#[CoversClass(BoundedMatcher::class)]
final class SwissPairerTest extends TestCase
{
    public function testTheFirstRoundPairsTheHigherHalfWithTheLowerHalf(): void
    {
        $tables = new SwissPairer()->pair(['p1', 'p2', 'p3', 'p4', 'p5', 'p6', 'p7', 'p8'], $this->records([]));

        self::assertSame([['p1', 'p5'], ['p6', 'p2'], ['p3', 'p7'], ['p8', 'p4']], $this->seats($tables));
        self::assertSame([1, 2, 3, 4], array_map(static fn (Pairing $pairing): int => $pairing->table, $tables));
        self::assertEquals([new PairingReason(ReasonKind::FirstPlayerByRank, 'p1', 'p5')], $tables[0]->reasons);
        self::assertEquals([new PairingReason(ReasonKind::FirstPlayerByRank, 'p6', 'p2')], $tables[1]->reasons);
    }

    public function testTheByeGoesToTheLowestRanked(): void
    {
        $tables = new SwissPairer()->pair(['p1', 'p2', 'p3', 'p4', 'p5'], $this->records([]));

        self::assertSame([['p1', 'p3'], ['p4', 'p2'], ['p5', null]], $this->seats($tables));
        self::assertEquals([new PairingReason(ReasonKind::Bye, 'p5')], $tables[2]->reasons);
        self::assertSame(3, $tables[2]->table);
        self::assertFalse($tables[2]->rest);
    }

    public function testTheByeGoesToTheLowestScoreWhoNeverHadOne(): void
    {
        // After round 1: p1 beat p3, p2 beat p4, p5 had the bye. Points: p1 2, p2 2, p5 2, p3 0, p4 0.
        $records = $this->records([
            [['p1', 'p3', Result::firstWins()], ['p4', 'p2', Result::secondWins()], ['p5', null, null]],
        ]);
        $tables = new SwissPairer()->pair(['p1', 'p2', 'p3', 'p4', 'p5'], $records);
        $bye = $tables[\count($tables) - 1];

        self::assertNull($bye->second);
        self::assertSame('p4', $bye->first, 'p3 and p4 have no point; p4 is ranked lower.');
        self::assertSame(ReasonKind::Bye, $bye->reasons[0]->kind);
    }

    public function testNoOneHasASecondByeWhileAnotherCanTakeIt(): void
    {
        // p3 had the bye and lost nothing; p1 and p2 drew: all three have points, p3 more, yet p3 does not get it again.
        $records = $this->records([
            [['p1', 'p2', Result::draw()], ['p3', null, null]],
        ]);
        $tables = new SwissPairer()->pair(['p1', 'p2', 'p3'], $records);

        self::assertNotSame('p3', $tables[1]->first);
        self::assertSame(ReasonKind::Bye, $tables[1]->reasons[0]->kind);
    }

    public function testASecondByeIsSaidSo(): void
    {
        $tables = new SwissPairer()->pair(['p1'], $this->records([[['p1', null, null]]]));

        self::assertEquals([new Pairing(1, 'p1', null, [new PairingReason(ReasonKind::ByeRepeated, 'p1')])], $tables);
    }

    public function testNoParticipantsNoTables(): void
    {
        self::assertSame([], new SwissPairer()->pair([], $this->records([])));
    }

    public function testTheWinnersMeetWithTheSidesAlternated(): void
    {
        $records = $this->records([
            [['p1', 'p3', Result::firstWins()], ['p4', 'p2', Result::secondWins()]],
        ]);
        $tables = new SwissPairer()->pair(['p1', 'p2', 'p3', 'p4'], $records);

        self::assertSame([['p2', 'p1'], ['p3', 'p4']], $this->seats($tables));
        self::assertEquals([new PairingReason(ReasonKind::FirstPlayerAlternated, 'p2', 'p1')], $tables[0]->reasons);
        self::assertEquals([new PairingReason(ReasonKind::FirstPlayerAlternated, 'p3', 'p4')], $tables[1]->reasons);
    }

    public function testARematchIsAvoidedAndSaidSo(): void
    {
        // Round 1: p1 beat p3, p2 beat p4; round 2: p2 beat p1, p3 beat p4. Points: p2 4, p1 2, p3 2, p4 0.
        $records = $this->records([
            [['p1', 'p3', Result::firstWins()], ['p4', 'p2', Result::secondWins()]],
            [['p2', 'p1', Result::firstWins()], ['p3', 'p4', Result::firstWins()]],
        ]);
        $tables = new SwissPairer()->pair(['p1', 'p2', 'p3', 'p4'], $records);

        self::assertSame([['p3', 'p2'], ['p1', 'p4']], $this->seats($tables));
        self::assertContainsEquals(new PairingReason(ReasonKind::Floated, 'p2', 'p3'), $tables[0]->reasons);
        self::assertContainsEquals(new PairingReason(ReasonKind::Floated, 'p3', 'p2'), $tables[0]->reasons);
        self::assertContainsEquals(new PairingReason(ReasonKind::RematchAvoided, 'p3', 'p1', 1), $tables[0]->reasons);
        self::assertContainsEquals(new PairingReason(ReasonKind::RematchAvoided, 'p1', 'p3', 1), $tables[1]->reasons);
        self::assertNotContainsEquals(new PairingReason(ReasonKind::RematchAvoided, 'p2', 'p1', 2), $tables[0]->reasons, 'p1 has fewer points than p2.');
        self::assertCount(4, $tables[0]->reasons);
    }

    public function testARematchNoPairingAvoidsAlternatesTheSides(): void
    {
        $records = $this->records([
            [['p1', 'p2', Result::firstWins()]],
            [['p2', 'p1', Result::draw()]],
        ]);
        $tables = new SwissPairer()->pair(['p1', 'p2'], $records);

        self::assertSame([['p1', 'p2']], $this->seats($tables));
        self::assertEquals([
            new PairingReason(ReasonKind::FirstPlayerAlternatedRematch, 'p1', 'p2'),
            new PairingReason(ReasonKind::Rematch, 'p1', 'p2', 2),
            new PairingReason(ReasonKind::Rematch, 'p2', 'p1', 2),
            new PairingReason(ReasonKind::Floated, 'p1', 'p2'),
            new PairingReason(ReasonKind::Floated, 'p2', 'p1'),
        ], $tables[0]->reasons);
    }

    public function testAForfeitIsNotAMeeting(): void
    {
        $records = $this->records([
            [['p1', 'p2', new Result(Outcome::SecondForfeits)], ['p3', 'p4', Result::firstWins()]],
        ]);
        $tables = new SwissPairer()->pair(['p1', 'p2', 'p3', 'p4'], $records);

        self::assertSame([['p1', 'p3'], ['p4', 'p2']], $this->seats($tables));
    }

    public function testTheParticipantMoreDueToPlayFirstDoes(): void
    {
        // a played second twice: they must play first; b played second once.
        $tables = new SwissPairer()->pair(['b', 'a'], $this->history(['a' => [false, false], 'b' => [false]]));

        self::assertSame([['a', 'b']], $this->seats($tables));
        self::assertSame(ReasonKind::FirstPlayerAlternated, $tables[0]->reasons[0]->kind);
    }

    public function testOfTwoDueToPlaySecondTheLessDuePlaysFirst(): void
    {
        $tables = new SwissPairer()->pair(['a', 'b'], $this->history(['a' => [true, true], 'b' => [true]]));

        self::assertSame([['b', 'a']], $this->seats($tables));
        self::assertSame(ReasonKind::FirstPlayerRepeated, $tables[0]->reasons[0]->kind);
    }

    public function testOneDueToPlaySecondLetsTheOtherPlayFirst(): void
    {
        $tables = new SwissPairer()->pair(['a', 'b'], $this->history(['a' => [true], 'b' => []]));

        self::assertSame([['b', 'a']], $this->seats($tables));
        self::assertSame(ReasonKind::FirstPlayerAlternated, $tables[0]->reasons[0]->kind);
    }

    public function testEquallyDueTheOneWhoPlayedSecondMostRecentlyPlaysFirst(): void
    {
        $tables = new SwissPairer()->pair(['a', 'b'], $this->history(['a' => [false, false, true], 'b' => [false, true, false]]));

        self::assertSame([['b', 'a']], $this->seats($tables));
        self::assertSame(ReasonKind::FirstPlayerAlternated, $tables[0]->reasons[0]->kind);

        $tables = new SwissPairer()->pair(['b', 'a'], $this->history(['a' => [false, false, true], 'b' => [false, true, false]]));
        self::assertSame([['b', 'a']], $this->seats($tables));
    }

    public function testTheSameHistoryGivesTheHigherRankedTheirDueSide(): void
    {
        $tables = new SwissPairer()->pair(['a', 'b'], $this->history(['a' => [false], 'b' => [false]]));
        self::assertSame([['a', 'b']], $this->seats($tables));
        self::assertSame(ReasonKind::FirstPlayerAlternated, $tables[0]->reasons[0]->kind);

        $tables = new SwissPairer()->pair(['a', 'b'], $this->history(['a' => [true], 'b' => [true]]));
        self::assertSame([['b', 'a']], $this->seats($tables));
        self::assertSame(ReasonKind::FirstPlayerRepeated, $tables[0]->reasons[0]->kind);
    }

    public function testTwoDueToTheSameSideMeetOnlyWhenNoBetterPairingExists(): void
    {
        // a and c played first twice; b and d played second twice; all have the same points: a meets b or d, never c.
        $tables = new SwissPairer()->pair(['a', 'c', 'b', 'd'], $this->history(['a' => [true, true], 'c' => [true, true], 'b' => [false, false], 'd' => [false, false]]));

        self::assertCount(2, $tables);

        foreach ($tables as $table) {
            self::assertFalse($table->involves('a') && $table->involves('c'));
            self::assertFalse($table->involves('b') && $table->involves('d'));
        }
    }

    public function testTheBoundedSearchPairsLargeRoundsWithoutRematches(): void
    {
        $participants = array_map(static fn (int $n): string => 'p'.$n, range(1, 24));
        $pairer = new SwissPairer(4, 3);
        $rounds = [];

        for ($number = 1; $number <= 5; ++$number) {
            $records = Records::build($participants, $rounds, $this->firstWinsAll($rounds), new Scoring());
            $rounds[] = new Round('s', $number, $pairer->pair($participants, $records));
        }

        $met = [];

        foreach ($rounds as $round) {
            self::assertCount(12, $round->pairings);

            foreach ($round->pairings as $pairing) {
                $key = min($pairing->first, (string) $pairing->second).'|'.max($pairing->first, (string) $pairing->second);
                self::assertArrayNotHasKey($key, $met);
                $met[$key] = true;
            }
        }
    }

    public function testTheBoundsOfThePairerAreChecked(): void
    {
        self::assertCount(1, new SwissPairer(2, 1)->pair(['a', 'b'], $this->records([])));
        self::assertCount(1, new SwissPairer(20, 100)->pair(['a', 'b'], $this->records([])));

        foreach ([[1, 20], [21, 20], [16, 0], [16, 101]] as [$exact, $passes]) {
            try {
                new SwissPairer($exact, $passes);
                self::fail('The bounds are checked.');
            } catch (InvalidTournament $exception) {
                self::assertStringStartsWith('The ', $exception->getMessage());
            }
        }
    }

    public function testARoundPairsItsLimitOfParticipants(): void
    {
        $tables = new SwissPairer(2, 1)->pair(array_map(static fn (int $n): string => 'p'.$n, range(1, 512)), $this->records([]));

        self::assertCount(256, $tables);
    }

    public function testARoundPairsAtMostItsLimit(): void
    {
        $this->expectException(InvalidTournament::class);
        $this->expectExceptionMessageIs('A Swiss round pairs 512 participants at most.');
        new SwissPairer()->pair(array_map(static fn (int $n): string => 'p'.$n, range(1, 513)), $this->records([]));
    }

    public function testTheExactMatcherFindsTheLowestCost(): void
    {
        // Greedy pairs 0-1 (1) then 2-3 (100): 101; the best is 0-2 (2) and 1-3 (2): 4.
        $costs = [
            [0, 1, 2, 50],
            [1, 0, 50, 2],
            [2, 50, 0, 100],
            [50, 2, 100, 0],
        ];

        self::assertSame([[0, 2], [1, 3]], new ExactMatcher()->match($costs));
        self::assertSame([[0, 2], [1, 3]], new BoundedMatcher()->match($costs), 'An exchange of partners finds it.');
        self::assertSame([[0, 1]], new ExactMatcher()->match([[0, 7], [7, 0]]));
        self::assertSame([[0, 1]], new BoundedMatcher()->match([[0, 7], [7, 0]]));
    }

    public function testTheExactMatcherKeepsTheFirstOfEqualCosts(): void
    {
        self::assertSame([[0, 1], [2, 3]], new ExactMatcher()->match(array_fill(0, 4, array_fill(0, 4, 1))));
        self::assertSame([[0, 1], [2, 3]], new BoundedMatcher()->match(array_fill(0, 4, array_fill(0, 4, 1))));
    }

    public function testTheBoundedMatcherSwapsPartnersTheOtherWay(): void
    {
        // Greedy pairs 0-1 and 2-3 (1 + 100); crossing 0-3 and 1-2 costs 2 + 2.
        $costs = [
            [0, 1, 50, 2],
            [1, 0, 2, 50],
            [50, 2, 0, 100],
            [2, 50, 100, 0],
        ];

        self::assertSame([[0, 3], [1, 2]], new BoundedMatcher()->match($costs));
        self::assertSame([[0, 3], [1, 2]], new ExactMatcher()->match($costs));
    }

    public function testTheBoundedMatcherStopsAfterItsPasses(): void
    {
        // Six vertices where each pass of exchanges improves once more; one pass stops before the best.
        $costs = [];

        for ($i = 0; $i < 6; ++$i) {
            $row = [];

            for ($j = 0; $j < 6; ++$j) {
                $row[] = $i === $j ? 0 : 10 * abs($i - $j) + ($i + $j) % 3;
            }

            $costs[] = $row;
        }

        $exact = new ExactMatcher()->match($costs);
        $bounded = new BoundedMatcher(100)->match($costs);
        self::assertSame($this->total($costs, $exact), $this->total($costs, $bounded));
        self::assertGreaterThanOrEqual($this->total($costs, $exact), $this->total($costs, new BoundedMatcher(1)->match($costs)));
    }

    /**
     * Who plays first, and why, for every pair of side histories of up to four matches, as the pairer decides it now:
     * the row is the history of `a`, the columns those of `b`, in the order of `HISTORIES`; `a` or `b` plays first, and
     * the reason is A for a side alternated, R for a side repeated, K for the higher rank.
     */
    private const array HISTORIES = ['-', 'F', 'T', 'FF', 'TF', 'FT', 'TT', 'FFF', 'TFF', 'FTF', 'TTF', 'FFT', 'TFT', 'FTT', 'TTT', 'FFFF', 'TFFF', 'FTFF', 'TTFF', 'FFTF', 'TFTF', 'FTTF', 'TTTF', 'FFFT', 'TFFT', 'FTFT', 'TTFT', 'FFTT', 'TFTT', 'FTTT', 'TTTT'];

    private const array SIDES = [
        '-' => 'aK bA aA bA bA aA aA bA bA bA aA bA aA aA aA bA bA bA bA bA bA bA aA bA aA aA aA aA aA aA aA',
        'F' => 'aA aA aA bA bA aA aA bA bA bA aA aA aA aA aA bA bA bA bA bA bA bA aA bA aA aA aA aA aA aA aA',
        'T' => 'bA bA bR bA bA aR aR bA bA bA bA bA aR aR aR bA bA bA bA bA bA bA aR bA aR aR aR aR aR aR aR',
        'FF' => 'aA aA aA aA aA aA aA bA bA aA aA aA aA aA aA bA bA bA bA aA aA aA aA aA aA aA aA aA aA aA aA',
        'TF' => 'aA aA aA bA aA aA aA bA bA bA aA aA aA aA aA bA bA bA bA bA bA bA aA bA aA aA aA aA aA aA aA',
        'FT' => 'bA bA bR bA bA bR aR bA bA bA bA bA aR aR aR bA bA bA bA bA bA bA aR bA aR aR aR aR aR aR aR',
        'TT' => 'bA bA bR bA bA bR bR bA bA bA bR bA bR aR aR bA bA bA bA bA bA bA bA bA bR bR bA aR aR aR aR',
        'FFF' => 'aA aA aA aA aA aA aA aA aA aA aA aA aA aA aA bA bA aA aA aA aA aA aA aA aA aA aA aA aA aA aA',
        'TFF' => 'aA aA aA aA aA aA aA bA aA aA aA aA aA aA aA bA bA bA bA aA aA aA aA aA aA aA aA aA aA aA aA',
        'FTF' => 'aA aA aA bA aA aA aA bA bA aA aA aA aA aA aA bA bA bA bA bA bA aA aA bA aA aA aA aA aA aA aA',
        'TTF' => 'bA bA aA bA bA aA aR bA bA bA bR bA aA aR aR bA bA bA bA bA bA bA aR bA aA aA aR aR aR aR aR',
        'FFT' => 'aA bA aA bA bA aA aA bA bA bA aA aA aA aA aA bA bA bA bA bA bA bA aA bA aA aA aA aA aA aA aA',
        'TFT' => 'bA bA bR bA bA bR aR bA bA bA bA bA bR aR aR bA bA bA bA bA bA bA aR bA bA aR aR aR aR aR aR',
        'FTT' => 'bA bA bR bA bA bR bR bA bA bA bR bA bR bR aA bA bA bA bA bA bA bA bA bA bR bR bA aR aR aA aA',
        'TTT' => 'bA bA bR bA bA bR bR bA bA bA bR bA bR bA bR bA bA bA bA bA bA bA bA bA bR bR bA bA bA aR aR',
        'FFFF' => 'aA aA aA aA aA aA aA aA aA aA aA aA aA aA aA aA aA aA aA aA aA aA aA aA aA aA aA aA aA aA aA',
        'TFFF' => 'aA aA aA aA aA aA aA aA aA aA aA aA aA aA aA bA aA aA aA aA aA aA aA aA aA aA aA aA aA aA aA',
        'FTFF' => 'aA aA aA aA aA aA aA bA aA aA aA aA aA aA aA bA bA aA aA aA aA aA aA aA aA aA aA aA aA aA aA',
        'TTFF' => 'aA aA aA aA aA aA aA bA aA aA aA aA aA aA aA bA bA bA aA aA aA aA aA aA aA aA aA aA aA aA aA',
        'FFTF' => 'aA aA aA bA aA aA aA bA bA aA aA aA aA aA aA bA bA bA bA aA aA aA aA aA aA aA aA aA aA aA aA',
        'TFTF' => 'aA aA aA bA aA aA aA bA bA aA aA aA aA aA aA bA bA bA bA bA aA aA aA bA aA aA aA aA aA aA aA',
        'FTTF' => 'aA aA aA bA aA aA aA bA bA bA aA aA aA aA aA bA bA bA bA bA bA aA aA bA aA aA aA aA aA aA aA',
        'TTTF' => 'bA bA bR bA bA bR aA bA bA bA bR bA bR aA aA bA bA bA bA bA bA bA bR bA bR bR aA aA aA aA aA',
        'FFFT' => 'aA aA aA bA aA aA aA bA bA aA aA aA aA aA aA bA bA bA bA bA aA aA aA aA aA aA aA aA aA aA aA',
        'TFFT' => 'bA bA bR bA bA bR aR bA bA bA bA bA aA aR aR bA bA bA bA bA bA bA aR bA bR aA aR aR aR aR aR',
        'FTFT' => 'bA bA bR bA bA bR aR bA bA bA bA bA bR aR aR bA bA bA bA bA bA bA aR bA bA bR aR aR aR aR aR',
        'TTFT' => 'bA bA bR bA bA bR aA bA bA bA bR bA bR aA aA bA bA bA bA bA bA bA bA bA bR bR bR aA aA aA aA',
        'FFTT' => 'bA bA bR bA bA bR bR bA bA bA bR bA bR bR aA bA bA bA bA bA bA bA bA bA bR bR bA bR aA aA aA',
        'TFTT' => 'bA bA bR bA bA bR bR bA bA bA bR bA bR bR aA bA bA bA bA bA bA bA bA bA bR bR bA bA bR aA aA',
        'FTTT' => 'bA bA bR bA bA bR bR bA bA bA bR bA bR bA bR bA bA bA bA bA bA bA bA bA bR bR bA bA bA bR aA',
        'TTTT' => 'bA bA bR bA bA bR bR bA bA bA bR bA bR bA bR bA bA bA bA bA bA bA bA bA bR bR bA bA bA bA bR',
    ];

    public function testTheSidesOfAPairFollowTheirHistories(): void
    {
        $kinds = ['A' => ReasonKind::FirstPlayerAlternated, 'R' => ReasonKind::FirstPlayerRepeated, 'K' => ReasonKind::FirstPlayerByRank];

        foreach (self::SIDES as $row => $line) {
            $cells = explode(' ', $line);

            foreach (self::HISTORIES as $column => $other) {
                $tables = new SwissPairer()->pair(['a', 'b'], $this->history(['a' => $this->played($row), 'b' => $this->played($other)]));

                self::assertSame($cells[$column][0], $tables[0]->first, 'a '.$row.' against b '.$other.': who plays first.');
                self::assertSame($kinds[$cells[$column][1]], $tables[0]->reasons[0]->kind, 'a '.$row.' against b '.$other.': why.');
            }
        }
    }

    /**
     * @return list<bool>
     */
    private function played(string|int $history): array
    {
        return '-' === $history ? [] : array_map(static fn (string $side): bool => 'T' === $side, str_split((string) $history));
    }

    /**
     * Whole Swiss tournaments played from a seed, five rounds each, with the pairing of every round: a table is
     * `first>second`, a bye `first-`.
     */
    public static function tournaments(): Iterator
    {
        yield 'default 3 players, seed 1' => [null, null, 3, 7922, 'p1>p2 p3-|p2>p3 p1-|p3>p1 p2-|p3>p2 p1-|p1>p3 p2-'];
        yield 'default 3 players, seed 2' => [null, null, 3, 15841, 'p1>p2 p3-|p3>p1 p2-|p2>p3 p1-|p2>p1 p3-|p3>p2 p1-'];
        yield 'default 3 players, seed 3' => [null, null, 3, 23760, 'p1>p2 p3-|p3>p1 p2-|p2>p3 p1-|p1>p3 p2-|p3>p2 p1-'];
        yield 'default 4 players, seed 1' => [null, null, 4, 7923, 'p1>p3 p4>p2|p2>p1 p3>p4|p1>p4 p2>p3|p1>p2 p4>p3|p3>p1 p2>p4'];
        yield 'default 4 players, seed 2' => [null, null, 4, 15842, 'p1>p3 p4>p2|p3>p4 p2>p1|p2>p3 p4>p1|p4>p3 p1>p2|p3>p1 p2>p4'];
        yield 'default 4 players, seed 3' => [null, null, 4, 23761, 'p1>p3 p4>p2|p2>p1 p3>p4|p3>p2 p4>p1|p1>p4 p2>p3|p4>p3 p1>p2'];
        yield 'default 5 players, seed 1' => [null, null, 5, 7924, 'p1>p3 p4>p2 p5-|p3>p4 p5>p1 p2-|p4>p5 p2>p3 p1-|p1>p4 p5>p2 p3-|p3>p5 p2>p1 p4-'];
        yield 'default 5 players, seed 2' => [null, null, 5, 15843, 'p1>p3 p4>p2 p5-|p2>p1 p5>p4 p3-|p1>p4 p3>p5 p2-|p5>p1 p2>p3 p4-|p4>p3 p5>p2 p1-'];
        yield 'default 5 players, seed 3' => [null, null, 5, 23762, 'p1>p3 p4>p2 p5-|p3>p4 p5>p1 p2-|p2>p3 p4>p5 p1-|p5>p3 p1>p2 p4-|p1>p4 p2>p5 p3-'];
        yield 'default 6 players, seed 1' => [null, null, 6, 7925, 'p1>p4 p5>p2 p3>p6|p3>p1 p2>p4 p6>p5|p1>p2 p5>p3 p4>p6|p5>p1 p2>p6 p4>p3|p6>p1 p3>p2 p4>p5'];
        yield 'default 6 players, seed 2' => [null, null, 6, 15844, 'p1>p4 p5>p2 p3>p6|p2>p1 p4>p3 p6>p5|p6>p2 p3>p1 p5>p4|p1>p6 p2>p4 p5>p3|p3>p2 p4>p6 p1>p5'];
        yield 'default 6 players, seed 3' => [null, null, 6, 23763, 'p1>p4 p5>p2 p3>p6|p2>p1 p6>p5 p4>p3|p5>p1 p3>p2 p4>p6|p3>p5 p1>p6 p2>p4|p5>p4 p1>p3 p6>p2'];
        yield 'default 7 players, seed 1' => [null, null, 7, 7926, 'p1>p4 p5>p2 p3>p6 p7-|p2>p3 p7>p1 p4>p5 p6-|p7>p2 p3>p4 p6>p1 p5-|p4>p7 p1>p2 p5>p6 p3-|p6>p4 p7>p3 p1>p5 p2-'];
        yield 'default 7 players, seed 2' => [null, null, 7, 15845, 'p1>p4 p5>p2 p3>p6 p7-|p6>p1 p7>p5 p2>p3 p4-|p5>p6 p1>p7 p4>p2 p3-|p1>p5 p2>p7 p3>p4 p6-|p2>p1 p6>p4 p7>p3 p5-'];
        yield 'default 7 players, seed 3' => [null, null, 7, 23764, 'p1>p4 p5>p2 p3>p6 p7-|p2>p1 p6>p7 p4>p3 p5-|p7>p2 p1>p5 p6>p4 p3-|p5>p7 p2>p6 p3>p1 p4-|p7>p4 p1>p6 p3>p5 p2-'];
        yield 'default 8 players, seed 1' => [null, null, 8, 7927, 'p1>p5 p6>p2 p3>p7 p8>p4|p6>p3 p5>p8 p4>p1 p2>p7|p5>p6 p3>p4 p7>p8 p1>p2|p3>p5 p8>p6 p7>p1 p2>p4|p8>p3 p5>p2 p6>p1 p4>p7'];
        yield 'default 8 players, seed 2' => [null, null, 8, 15846, 'p1>p5 p6>p2 p3>p7 p8>p4|p2>p1 p4>p3 p5>p8 p7>p6|p1>p4 p3>p2 p8>p7 p6>p5|p7>p1 p5>p3 p2>p4 p6>p8|p5>p7 p1>p3 p8>p2 p4>p6'];
        yield 'default 8 players, seed 3' => [null, null, 8, 23765, 'p1>p5 p6>p2 p3>p7 p8>p4|p4>p1 p7>p6 p2>p3 p5>p8|p1>p6 p3>p4 p8>p7 p5>p2|p3>p1 p2>p8 p6>p4 p7>p5|p1>p8 p6>p3 p4>p5 p7>p2'];
        yield 'default 9 players, seed 1' => [null, null, 9, 7928, 'p1>p5 p6>p2 p3>p7 p8>p4 p9-|p7>p1 p2>p9 p4>p6 p5>p8 p3-|p6>p7 p8>p2 p1>p3 p9>p4 p5-|p8>p6 p2>p1 p7>p9 p3>p5 p4-|p1>p6 p7>p8 p4>p3 p5>p9 p2-'];
        yield 'default 9 players, seed 2' => [null, null, 9, 15847, 'p1>p5 p6>p2 p3>p7 p8>p4 p9-|p5>p3 p4>p6 p9>p1 p2>p8 p7-|p5>p4 p1>p3 p6>p9 p7>p2 p8-|p6>p5 p3>p4 p2>p9 p8>p7 p1-|p5>p8 p3>p6 p4>p1 p9>p7 p2-'];
        yield 'default 9 players, seed 3' => [null, null, 9, 23766, 'p1>p5 p6>p2 p3>p7 p8>p4 p9-|p8>p1 p9>p3 p2>p4 p5>p6 p7-|p1>p9 p3>p8 p4>p5 p7>p2 p6-|p2>p1 p9>p8 p5>p3 p6>p7 p4-|p4>p9 p1>p6 p5>p2 p8>p7 p3-'];
        yield 'default 10 players, seed 1' => [null, null, 10, 7929, 'p1>p6 p7>p2 p3>p8 p9>p4 p5>p10|p4>p7 p8>p5 p2>p1 p6>p9 p10>p3|p8>p4 p7>p6 p5>p2 p1>p10 p3>p9|p4>p1 p6>p8 p2>p3 p7>p5 p9>p10|p6>p4 p8>p2 p5>p9 p10>p7 p1>p3'];
        yield 'default 10 players, seed 2' => [null, null, 10, 15848, 'p1>p6 p7>p2 p3>p8 p9>p4 p5>p10|p5>p1 p9>p3 p2>p4 p8>p7 p6>p10|p9>p5 p1>p2 p3>p7 p4>p6 p10>p8|p5>p3 p1>p9 p2>p6 p8>p4 p7>p10|p3>p1 p6>p5 p4>p7 p10>p9 p8>p2'];
        yield 'default 10 players, seed 3' => [null, null, 10, 23767, 'p1>p6 p7>p2 p3>p8 p9>p4 p5>p10|p7>p3 p6>p9 p4>p5 p10>p1 p2>p8|p3>p6 p9>p7 p1>p4 p5>p2 p8>p10|p6>p7 p2>p1 p4>p3 p8>p5 p10>p9|p7>p4 p6>p2 p1>p5 p3>p10 p9>p8'];
        yield 'default 11 players, seed 1' => [null, null, 11, 7930, 'p1>p6 p7>p2 p3>p8 p9>p4 p5>p10 p11-|p8>p1 p9>p5 p11>p7 p2>p3 p4>p10 p6-|p11>p9 p5>p8 p1>p2 p3>p6 p7>p4 p10-|p4>p11 p6>p9 p8>p2 p1>p5 p10>p3 p7-|p6>p11 p2>p4 p9>p8 p5>p7 p10>p1 p3-'];
        yield 'default 11 players, seed 2' => [null, null, 11, 15849, 'p1>p6 p7>p2 p3>p8 p9>p4 p5>p10 p11-|p2>p5 p8>p11 p6>p9 p4>p1 p10>p3 p7-|p1>p2 p9>p8 p5>p7 p11>p10 p3>p6 p4-|p8>p1 p2>p9 p11>p5 p7>p3 p10>p4 p6-|p2>p8 p1>p11 p9>p3 p6>p10 p4>p7 p5-'];
        yield 'default 11 players, seed 3' => [null, null, 11, 23768, 'p1>p6 p7>p2 p3>p8 p9>p4 p5>p10 p11-|p7>p1 p11>p3 p4>p5 p10>p9 p2>p6 p8-|p1>p11 p10>p7 p3>p5 p8>p4 p9>p2 p6-|p1>p10 p5>p7 p11>p8 p2>p4 p6>p3 p9-|p8>p1 p6>p5 p4>p10 p7>p11 p3>p9 p2-'];
        yield 'default 12 players, seed 1' => [null, null, 12, 7931, 'p1>p7 p8>p2 p3>p9 p10>p4 p5>p11 p12>p6|p5>p1 p2>p10 p12>p3 p4>p8 p6>p9 p7>p11|p3>p5 p1>p2 p10>p6 p8>p12 p9>p7 p11>p4|p1>p3 p8>p10 p2>p5 p6>p11 p7>p12 p4>p9|p3>p8 p10>p1 p11>p2 p5>p7 p4>p6 p12>p9'];
        yield 'default 12 players, seed 2' => [null, null, 12, 15850, 'p1>p7 p8>p2 p3>p9 p10>p4 p5>p11 p12>p6|p8>p1 p12>p5 p4>p3 p9>p10 p2>p7 p6>p11|p1>p12 p5>p4 p10>p8 p3>p2 p7>p6 p11>p9|p8>p12 p4>p1 p2>p10 p7>p5 p6>p9 p11>p3|p12>p2 p1>p5 p4>p8 p3>p6 p10>p11 p9>p7'];
        yield 'default 12 players, seed 3' => [null, null, 12, 23769, 'p1>p7 p8>p2 p3>p9 p10>p4 p5>p11 p12>p6|p2>p4 p9>p5 p6>p1 p7>p12 p11>p3 p10>p8|p12>p2 p4>p9 p3>p6 p5>p7 p1>p10 p8>p11|p2>p9 p4>p3 p12>p1 p6>p5 p7>p8 p11>p10|p9>p6 p1>p2 p3>p12 p8>p4 p10>p5 p11>p7'];
        yield 'default 15 players, seed 1' => [null, null, 15, 7934, 'p1>p8 p9>p2 p3>p10 p11>p4 p5>p12 p13>p6 p7>p14 p15-|p4>p9 p12>p7 p10>p15 p8>p13 p2>p1 p6>p3 p14>p5 p11-|p15>p4 p7>p2 p13>p12 p5>p8 p10>p6 p9>p11 p1>p14 p3-|p12>p15 p4>p7 p9>p5 p6>p1 p3>p13 p2>p8 p11>p10 p14-|p15>p6 p12>p4 p5>p3 p7>p9 p10>p2 p1>p13 p14>p11 p8-'];
        yield 'default 15 players, seed 2' => [null, null, 15, 15853, 'p1>p8 p9>p2 p3>p10 p11>p4 p5>p12 p13>p6 p7>p14 p15-|p2>p1 p11>p3 p13>p5 p15>p7 p4>p9 p6>p12 p8>p14 p10-|p1>p5 p3>p7 p10>p4 p14>p6 p15>p11 p9>p13 p12>p2 p8-|p5>p3 p4>p1 p7>p13 p14>p11 p2>p6 p10>p8 p9>p15 p12-|p3>p4 p11>p5 p1>p7 p13>p2 p6>p10 p15>p14 p8>p12 p9-'];
        yield 'default 15 players, seed 3' => [null, null, 15, 23772, 'p1>p8 p9>p2 p3>p10 p11>p4 p5>p12 p13>p6 p7>p14 p15-|p8>p3 p9>p5 p15>p7 p4>p13 p6>p11 p10>p1 p2>p12 p14-|p7>p8 p4>p9 p1>p6 p11>p2 p3>p14 p5>p15 p13>p10 p12-|p9>p7 p14>p8 p15>p11 p2>p1 p6>p4 p12>p3 p13>p5 p10-|p7>p11 p14>p9 p4>p2 p12>p6 p8>p15 p1>p13 p10>p5 p3-'];
        yield 'default 16 players, seed 1' => [null, null, 16, 7935, 'p1>p9 p10>p2 p3>p11 p12>p4 p5>p13 p14>p6 p7>p15 p16>p8|p12>p1 p13>p10 p8>p14 p15>p3 p11>p7 p2>p16 p4>p6 p9>p5|p1>p13 p11>p8 p10>p15 p5>p2 p14>p12 p6>p3 p7>p4 p16>p9|p15>p1 p13>p11 p2>p14 p4>p16 p8>p5 p6>p10 p3>p12 p9>p7|p11>p15 p1>p4 p5>p14 p2>p13 p10>p3 p16>p6 p9>p8 p12>p7'];
        yield 'default 16 players, seed 2' => [null, null, 16, 15854, 'p1>p9 p10>p2 p3>p11 p12>p4 p5>p13 p14>p6 p7>p15 p16>p8|p2>p5 p11>p16 p4>p14 p15>p1 p9>p7 p13>p12 p8>p3 p6>p10|p11>p2 p15>p4 p5>p9 p7>p13 p14>p8 p16>p1 p12>p6 p10>p3|p2>p4 p9>p11 p8>p7 p10>p15 p13>p16 p1>p14 p6>p5 p3>p12|p4>p11 p15>p2 p7>p1 p13>p8 p16>p9 p5>p10 p3>p6 p14>p12'];
        yield 'default 16 players, seed 3' => [null, null, 16, 23773, 'p1>p9 p10>p2 p3>p11 p12>p4 p5>p13 p14>p6 p7>p15 p16>p8|p4>p1 p5>p3 p6>p10 p8>p7 p15>p14 p2>p16 p9>p12 p11>p13|p4>p5 p8>p6 p3>p15 p1>p10 p16>p11 p13>p2 p7>p12 p14>p9|p15>p4 p5>p8 p16>p1 p2>p6 p12>p14 p10>p3 p11>p7 p9>p13|p8>p15 p1>p5 p4>p16 p7>p2 p6>p12 p14>p10 p13>p3 p9>p11'];
        yield 'bounded 3 players, seed 1' => [4, 3, 3, 7922, 'p1>p2 p3-|p2>p3 p1-|p3>p1 p2-|p3>p2 p1-|p1>p3 p2-'];
        yield 'bounded 3 players, seed 2' => [4, 3, 3, 15841, 'p1>p2 p3-|p3>p1 p2-|p2>p3 p1-|p2>p1 p3-|p3>p2 p1-'];
        yield 'bounded 3 players, seed 3' => [4, 3, 3, 23760, 'p1>p2 p3-|p3>p1 p2-|p2>p3 p1-|p1>p3 p2-|p3>p2 p1-'];
        yield 'bounded 4 players, seed 1' => [4, 3, 4, 7923, 'p1>p3 p4>p2|p2>p1 p3>p4|p1>p4 p2>p3|p1>p2 p4>p3|p3>p1 p2>p4'];
        yield 'bounded 4 players, seed 2' => [4, 3, 4, 15842, 'p1>p3 p4>p2|p3>p4 p2>p1|p2>p3 p4>p1|p4>p3 p1>p2|p3>p1 p2>p4'];
        yield 'bounded 4 players, seed 3' => [4, 3, 4, 23761, 'p1>p3 p4>p2|p2>p1 p3>p4|p3>p2 p4>p1|p1>p4 p2>p3|p4>p3 p1>p2'];
        yield 'bounded 5 players, seed 1' => [4, 3, 5, 7924, 'p1>p3 p4>p2 p5-|p5>p4 p2>p1 p3-|p3>p4 p1>p5 p2-|p5>p3 p4>p1 p2-|p3>p2 p4>p5 p1-'];
        yield 'bounded 5 players, seed 2' => [4, 3, 5, 15843, 'p1>p3 p4>p2 p5-|p5>p1 p3>p4 p2-|p1>p2 p4>p5 p3-|p4>p1 p2>p3 p5-|p2>p1 p5>p3 p4-'];
        yield 'bounded 5 players, seed 3' => [4, 3, 5, 23762, 'p1>p3 p4>p2 p5-|p3>p4 p5>p1 p2-|p5>p3 p2>p1 p4-|p4>p5 p3>p2 p1-|p2>p5 p1>p4 p3-'];
        yield 'bounded 6 players, seed 1' => [4, 3, 6, 7925, 'p1>p4 p5>p2 p3>p6|p3>p1 p2>p4 p6>p5|p1>p2 p5>p3 p4>p6|p6>p1 p2>p3 p4>p5|p1>p5 p3>p4 p2>p6'];
        yield 'bounded 6 players, seed 2' => [4, 3, 6, 15844, 'p1>p4 p5>p2 p3>p6|p2>p1 p4>p3 p6>p5|p6>p2 p3>p1 p5>p4|p1>p6 p2>p4 p5>p3|p3>p2 p4>p6 p1>p5'];
        yield 'bounded 6 players, seed 3' => [4, 3, 6, 23763, 'p1>p4 p5>p2 p3>p6|p6>p1 p4>p5 p2>p3|p5>p6 p1>p2 p3>p4|p1>p5 p6>p4 p3>p2|p6>p5 p3>p1 p2>p4'];
        yield 'bounded 7 players, seed 1' => [4, 3, 7, 7926, 'p1>p4 p5>p2 p3>p6 p7-|p2>p3 p7>p1 p4>p5 p6-|p7>p2 p3>p4 p6>p1 p5-|p4>p7 p2>p6 p5>p3 p1-|p6>p4 p3>p7 p1>p5 p2-'];
        yield 'bounded 7 players, seed 2' => [4, 3, 7, 15845, 'p1>p4 p5>p2 p3>p6 p7-|p6>p1 p7>p5 p2>p3 p4-|p5>p6 p1>p7 p4>p2 p3-|p3>p5 p2>p1 p7>p4 p6-|p6>p2 p7>p3 p5>p4 p1-'];
        yield 'bounded 7 players, seed 3' => [4, 3, 7, 23764, 'p1>p4 p5>p2 p3>p6 p7-|p2>p1 p6>p7 p4>p3 p5-|p7>p2 p1>p5 p6>p4 p3-|p3>p7 p2>p6 p5>p4 p1-|p7>p1 p2>p3 p6>p5 p4-'];
        yield 'bounded 8 players, seed 1' => [4, 3, 8, 7927, 'p1>p5 p6>p2 p3>p7 p8>p4|p6>p3 p5>p8 p4>p1 p2>p7|p5>p6 p3>p4 p7>p8 p1>p2|p3>p5 p8>p6 p7>p1 p2>p4|p8>p3 p5>p2 p6>p1 p4>p7'];
        yield 'bounded 8 players, seed 2' => [4, 3, 8, 15846, 'p1>p5 p6>p2 p3>p7 p8>p4|p2>p1 p4>p3 p5>p8 p7>p6|p1>p4 p3>p2 p8>p7 p6>p5|p7>p1 p5>p3 p2>p4 p6>p8|p2>p7 p1>p6 p3>p8 p4>p5'];
        yield 'bounded 8 players, seed 3' => [4, 3, 8, 23765, 'p1>p5 p6>p2 p3>p7 p8>p4|p4>p1 p7>p6 p2>p3 p5>p8|p1>p6 p3>p4 p8>p7 p5>p2|p3>p1 p2>p8 p6>p4 p7>p5|p1>p8 p6>p3 p4>p5 p7>p2'];
        yield 'bounded 9 players, seed 1' => [4, 3, 9, 7928, 'p1>p5 p6>p2 p3>p7 p8>p4 p9-|p7>p1 p2>p9 p4>p6 p5>p8 p3-|p6>p7 p8>p2 p1>p3 p9>p4 p5-|p1>p6 p2>p7 p9>p8 p3>p5 p4-|p4>p1 p7>p9 p6>p5 p8>p3 p2-'];
        yield 'bounded 9 players, seed 2' => [4, 3, 9, 15847, 'p1>p5 p6>p2 p3>p7 p8>p4 p9-|p5>p3 p4>p6 p9>p1 p2>p8 p7-|p5>p4 p1>p6 p3>p9 p7>p2 p8-|p6>p5 p4>p3 p8>p1 p9>p7 p2-|p1>p4 p2>p9 p3>p6 p7>p8 p5-'];
        yield 'bounded 9 players, seed 3' => [4, 3, 9, 23766, 'p1>p5 p6>p2 p3>p7 p8>p4 p9-|p8>p1 p9>p3 p2>p4 p5>p6 p7-|p1>p9 p3>p5 p4>p7 p6>p8 p2-|p7>p1 p5>p8 p9>p2 p4>p3 p6-|p7>p5 p9>p4 p1>p2 p3>p6 p8-'];
        yield 'bounded 10 players, seed 1' => [4, 3, 10, 7929, 'p1>p6 p7>p2 p3>p8 p9>p4 p5>p10|p4>p7 p8>p5 p10>p1 p6>p3 p2>p9|p8>p4 p5>p6 p1>p2 p7>p10 p3>p9|p4>p6 p7>p8 p2>p5 p9>p1 p10>p3|p2>p4 p6>p8 p1>p7 p5>p3 p9>p10'];
        yield 'bounded 10 players, seed 2' => [4, 3, 10, 15848, 'p1>p6 p7>p2 p3>p8 p9>p4 p5>p10|p5>p1 p9>p3 p2>p10 p6>p7 p4>p8|p9>p5 p1>p2 p3>p7 p10>p4 p8>p6|p5>p3 p2>p9 p4>p1 p7>p8 p6>p10|p3>p2 p8>p5 p1>p9 p6>p4 p10>p7'];
        yield 'bounded 10 players, seed 3' => [4, 3, 10, 23767, 'p1>p6 p7>p2 p3>p8 p9>p4 p5>p10|p7>p3 p6>p9 p4>p5 p10>p1 p2>p8|p3>p6 p1>p7 p9>p2 p10>p4 p8>p5|p6>p4 p7>p9 p2>p3 p5>p1 p8>p10|p4>p7 p5>p6 p1>p8 p10>p2 p9>p3'];
        yield 'bounded 11 players, seed 1' => [4, 3, 11, 7930, 'p1>p6 p7>p2 p3>p8 p9>p4 p5>p10 p11-|p8>p1 p9>p5 p11>p7 p4>p3 p2>p6 p10-|p11>p9 p5>p8 p1>p2 p3>p10 p7>p4 p6-|p8>p11 p2>p9 p10>p4 p1>p5 p6>p3 p7-|p11>p2 p10>p8 p9>p7 p4>p6 p5>p3 p1-'];
        yield 'bounded 11 players, seed 2' => [4, 3, 11, 15849, 'p1>p6 p7>p2 p3>p8 p9>p4 p5>p10 p11-|p2>p5 p8>p11 p6>p9 p4>p1 p10>p3 p7-|p1>p2 p9>p8 p5>p7 p11>p10 p3>p6 p4-|p8>p1 p2>p9 p11>p5 p7>p3 p10>p4 p6-|p2>p8 p1>p11 p9>p3 p6>p10 p4>p7 p5-'];
        yield 'bounded 11 players, seed 3' => [4, 3, 11, 23768, 'p1>p6 p7>p2 p3>p8 p9>p4 p5>p10 p11-|p7>p1 p11>p3 p4>p5 p10>p9 p2>p6 p8-|p1>p11 p10>p7 p3>p5 p8>p4 p9>p2 p6-|p8>p1 p11>p7 p2>p10 p6>p3 p5>p9 p4-|p10>p8 p5>p11 p1>p3 p7>p4 p9>p6 p2-'];
        yield 'bounded 12 players, seed 1' => [4, 3, 12, 7931, 'p1>p7 p8>p2 p3>p9 p10>p4 p5>p11 p12>p6|p5>p1 p2>p10 p12>p3 p4>p8 p6>p9 p7>p11|p3>p2 p1>p10 p8>p5 p11>p6 p7>p12 p9>p4|p8>p3 p2>p1 p6>p5 p10>p12 p11>p9 p4>p7|p3>p11 p1>p8 p5>p2 p7>p10 p12>p9 p4>p6'];
        yield 'bounded 12 players, seed 2' => [4, 3, 12, 15850, 'p1>p7 p8>p2 p3>p9 p10>p4 p5>p11 p12>p6|p8>p1 p12>p5 p4>p3 p9>p10 p2>p7 p6>p11|p1>p12 p5>p4 p10>p8 p3>p2 p7>p6 p11>p9|p4>p12 p10>p1 p7>p8 p2>p5 p6>p9 p11>p3|p12>p10 p1>p5 p4>p7 p3>p6 p8>p11 p9>p2'];
        yield 'bounded 12 players, seed 3' => [4, 3, 12, 23769, 'p1>p7 p8>p2 p3>p9 p10>p4 p5>p11 p12>p6|p2>p4 p9>p5 p6>p1 p7>p12 p11>p3 p10>p8|p12>p2 p4>p9 p3>p6 p5>p7 p1>p10 p8>p11|p2>p3 p4>p1 p9>p8 p6>p5 p11>p12 p7>p10|p3>p4 p6>p2 p12>p9 p1>p8 p10>p5 p11>p7'];
        yield 'short 4 players, seed 1' => [6, 1, 4, 7923, 'p1>p3 p4>p2|p2>p1 p3>p4|p1>p4 p2>p3|p1>p2 p4>p3|p3>p1 p2>p4'];
        yield 'short 4 players, seed 2' => [6, 1, 4, 15842, 'p1>p3 p4>p2|p3>p4 p2>p1|p2>p3 p4>p1|p4>p3 p1>p2|p3>p1 p2>p4'];
        yield 'short 4 players, seed 3' => [6, 1, 4, 23761, 'p1>p3 p4>p2|p2>p1 p3>p4|p3>p2 p4>p1|p1>p4 p2>p3|p4>p3 p1>p2'];
        yield 'short 5 players, seed 1' => [6, 1, 5, 7924, 'p1>p3 p4>p2 p5-|p3>p4 p5>p1 p2-|p4>p5 p2>p3 p1-|p1>p4 p5>p2 p3-|p3>p5 p2>p1 p4-'];
        yield 'short 5 players, seed 2' => [6, 1, 5, 15843, 'p1>p3 p4>p2 p5-|p2>p1 p5>p4 p3-|p1>p4 p3>p5 p2-|p5>p1 p2>p3 p4-|p4>p3 p5>p2 p1-'];
        yield 'short 5 players, seed 3' => [6, 1, 5, 23762, 'p1>p3 p4>p2 p5-|p3>p4 p5>p1 p2-|p2>p3 p4>p5 p1-|p5>p3 p1>p2 p4-|p1>p4 p2>p5 p3-'];
        yield 'short 6 players, seed 1' => [6, 1, 6, 7925, 'p1>p4 p5>p2 p3>p6|p3>p1 p2>p4 p6>p5|p1>p2 p5>p3 p4>p6|p5>p1 p2>p6 p4>p3|p6>p1 p3>p2 p4>p5'];
        yield 'short 6 players, seed 2' => [6, 1, 6, 15844, 'p1>p4 p5>p2 p3>p6|p2>p1 p4>p3 p6>p5|p6>p2 p3>p1 p5>p4|p1>p6 p2>p4 p5>p3|p3>p2 p4>p6 p1>p5'];
        yield 'short 6 players, seed 3' => [6, 1, 6, 23763, 'p1>p4 p5>p2 p3>p6|p2>p1 p6>p5 p4>p3|p5>p1 p3>p2 p4>p6|p3>p5 p1>p6 p2>p4|p5>p4 p1>p3 p6>p2'];
        yield 'short 7 players, seed 1' => [6, 1, 7, 7926, 'p1>p4 p5>p2 p3>p6 p7-|p2>p3 p7>p1 p4>p5 p6-|p7>p2 p3>p4 p6>p1 p5-|p6>p7 p2>p4 p5>p3 p1-|p3>p7 p1>p5 p4>p6 p2-'];
        yield 'short 7 players, seed 2' => [6, 1, 7, 15845, 'p1>p4 p5>p2 p3>p6 p7-|p6>p1 p7>p5 p2>p3 p4-|p5>p6 p1>p7 p4>p2 p3-|p4>p5 p2>p1 p3>p7 p6-|p1>p3 p6>p2 p7>p4 p5-'];
        yield 'short 7 players, seed 3' => [6, 1, 7, 23764, 'p1>p4 p5>p2 p3>p6 p7-|p2>p1 p6>p7 p4>p3 p5-|p7>p2 p1>p5 p6>p4 p3-|p3>p7 p2>p6 p5>p4 p1-|p7>p1 p2>p3 p6>p5 p4-'];
        yield 'short 8 players, seed 1' => [6, 1, 8, 7927, 'p1>p5 p6>p2 p3>p7 p8>p4|p6>p3 p5>p8 p4>p1 p2>p7|p5>p6 p3>p4 p7>p8 p1>p2|p3>p5 p8>p6 p7>p1 p2>p4|p8>p3 p5>p2 p6>p1 p4>p7'];
        yield 'short 8 players, seed 2' => [6, 1, 8, 15846, 'p1>p5 p6>p2 p3>p7 p8>p4|p2>p1 p4>p3 p5>p8 p7>p6|p1>p4 p3>p2 p8>p7 p6>p5|p7>p1 p5>p3 p2>p4 p6>p8|p2>p7 p1>p6 p3>p8 p4>p5'];
        yield 'short 8 players, seed 3' => [6, 1, 8, 23765, 'p1>p5 p6>p2 p3>p7 p8>p4|p4>p1 p7>p6 p2>p3 p5>p8|p1>p6 p3>p4 p8>p7 p5>p2|p3>p1 p2>p8 p6>p4 p7>p5|p1>p8 p6>p3 p4>p5 p7>p2'];
        yield 'short 9 players, seed 1' => [6, 1, 9, 7928, 'p1>p5 p6>p2 p3>p7 p8>p4 p9-|p7>p1 p2>p9 p4>p6 p5>p8 p3-|p6>p7 p8>p2 p1>p3 p9>p4 p5-|p1>p6 p2>p7 p9>p8 p3>p5 p4-|p7>p9 p6>p8 p5>p2 p4>p3 p1-'];
        yield 'short 9 players, seed 2' => [6, 1, 9, 15847, 'p1>p5 p6>p2 p3>p7 p8>p4 p9-|p5>p3 p4>p6 p9>p1 p2>p8 p7-|p5>p4 p1>p6 p3>p9 p7>p2 p8-|p9>p5 p4>p3 p2>p1 p8>p7 p6-|p2>p4 p6>p3 p1>p8 p7>p9 p5-'];
        yield 'short 9 players, seed 3' => [6, 1, 9, 23766, 'p1>p5 p6>p2 p3>p7 p8>p4 p9-|p8>p1 p9>p3 p2>p4 p5>p6 p7-|p1>p9 p3>p5 p4>p7 p6>p8 p2-|p7>p1 p5>p8 p9>p2 p4>p3 p6-|p9>p4 p1>p6 p8>p7 p2>p3 p5-'];
        yield 'default 15 players, a group the exact search keeps (seed 2)' => [null, null, 15, 2, 'p1>p8 p9>p2 p3>p10 p11>p4 p5>p12 p13>p6 p7>p14 p15-|p10>p5 p8>p13 p14>p9 p4>p15 p2>p11 p6>p1 p12>p3 p7-|p5>p8 p11>p14 p1>p4 p13>p7 p9>p12 p15>p10 p3>p2 p6-|p14>p5 p4>p13 p8>p9 p10>p11 p12>p15 p2>p1 p7>p6 p3-|p13>p14 p5>p4 p9>p7 p11>p12 p2>p8 p15>p3 p6>p10 p1-'];
        yield 'default 16 players, a group the exact search keeps (seed 1)' => [null, null, 16, 1, 'p1>p9 p10>p2 p3>p11 p12>p4 p5>p13 p14>p6 p7>p15 p16>p8|p10>p3 p12>p5 p8>p14 p15>p1 p9>p7 p2>p16 p4>p11 p6>p13|p5>p10 p14>p15 p8>p2 p3>p9 p11>p6 p7>p12 p1>p16 p13>p4|p15>p10 p9>p5 p11>p7 p1>p8 p2>p14 p6>p3 p13>p12 p16>p4|p10>p9 p7>p8 p15>p11 p5>p2 p14>p13 p3>p1 p4>p6 p12>p16'];
    }

    #[DataProvider('tournaments')]
    public function testWholeTournamentsArePairedAsTheyAreNow(?int $exactLimit, ?int $passes, int $players, int $seed, string $expected): void
    {
        $names = array_map(static fn (int $n): string => 'p'.$n, range(1, $players));
        $pairer = null === $exactLimit || null === $passes ? new SwissPairer() : new SwissPairer($exactLimit, $passes);
        $state = $seed;
        $rounds = [];
        $results = [];
        $seen = [];

        for ($number = 1; $number <= 5; ++$number) {
            $pairings = $pairer->pair($names, Records::build($names, $rounds, $results, new Scoring()));
            $round = new Round('s', $number, $pairings);
            $rounds[] = $round;
            $seen[] = implode(' ', array_map(static fn (Pairing $pairing): string => null === $pairing->second ? $pairing->first.'-' : $pairing->first.'>'.$pairing->second, $pairings));

            foreach ($pairings as $pairing) {
                if ($pairing->isBye()) {
                    continue;
                }

                $state = ($state * 1103515245 + 12345) & 0x7FFFFFFF;
                $results[$round->key($pairing->table)] = match (($state >> 8) % 4) {
                    0, 3 => Result::firstWins(),
                    1 => Result::secondWins(),
                    default => Result::draw(),
                };
            }
        }

        self::assertSame($expected, implode('|', $seen));
    }

    public function testTheBoundedMatcherStopsAtItsPassesAndGoesOnWhileItImproves(): void
    {
        $costs = [[0, 23, 2, 4, 6, 17], [23, 0, 16, 7, 15, 10], [2, 16, 0, 1, 34, 38], [4, 7, 1, 0, 35, 24], [6, 15, 34, 35, 0, 33], [17, 10, 38, 24, 33, 0]];

        self::assertSame([[0, 2], [1, 4], [3, 5]], new BoundedMatcher(1)->match($costs));
        self::assertSame([[0, 5], [1, 4], [2, 3]], new BoundedMatcher(2)->match($costs));
        self::assertSame([[0, 4], [1, 5], [2, 3]], new BoundedMatcher(3)->match($costs));
        self::assertSame([[0, 4], [1, 5], [2, 3]], new BoundedMatcher(50)->match($costs));
    }

    public function testTheBoundedMatcherGoesOnAfterACrossedExchange(): void
    {
        $costs = [[0, 11, 25, 15, 17, 6, 7, 21], [11, 0, 10, 15, 13, 24, 17, 18], [25, 10, 0, 2, 15, 9, 13, 4], [15, 15, 2, 0, 21, 20, 9, 23], [17, 13, 15, 21, 0, 3, 3, 15], [6, 24, 9, 20, 3, 0, 18, 1], [7, 17, 13, 9, 3, 18, 0, 5], [21, 18, 4, 23, 15, 1, 5, 0]];

        self::assertSame([[0, 4], [1, 2], [3, 6], [5, 7]], new BoundedMatcher(1)->match($costs));
        self::assertSame([[0, 1], [2, 3], [4, 6], [5, 7]], new BoundedMatcher(2)->match($costs));
    }

    public function testTheBoundedMatcherGoesOnAfterASwappedExchange(): void
    {
        $costs = [[0, 4, 8, 1, 7, 5, 6, 1], [4, 0, 6, 2, 5, 8, 8, 7], [8, 6, 0, 7, 7, 3, 6, 7], [1, 2, 7, 0, 2, 7, 2, 4], [7, 5, 7, 2, 0, 8, 5, 1], [5, 8, 3, 7, 8, 0, 4, 6], [6, 8, 6, 2, 5, 4, 0, 7], [1, 7, 7, 4, 1, 6, 7, 0]];

        self::assertSame([[0, 7], [1, 4], [2, 5], [3, 6]], new BoundedMatcher(1)->match($costs));
        self::assertSame([[0, 1], [2, 5], [3, 6], [4, 7]], new BoundedMatcher(2)->match($costs));
    }

    public function testTheBoundedMatcherReturnsItsPairsInOrder(): void
    {
        $costs = [[0, 2, 10, 4, 7, 24], [2, 0, 16, 16, 9, 21], [10, 16, 0, 25, 22, 12], [4, 16, 25, 0, 20, 15], [7, 9, 22, 20, 0, 10], [24, 21, 12, 15, 10, 0]];

        self::assertSame([[0, 3], [1, 4], [2, 5]], new BoundedMatcher()->match($costs));
    }

    public function testTheBoundedMatcherPrefersTheCrossedExchangeOfEqualCosts(): void
    {
        $costs = [[0, 1, 4, 4], [1, 0, 4, 4], [4, 4, 0, 9], [4, 4, 9, 0]];

        self::assertSame([[0, 2], [1, 3]], new BoundedMatcher()->match($costs));
    }

    public function testTheBoundedMatcherLeavesAnExchangeThatGainsNothing(): void
    {
        self::assertSame([[0, 1], [2, 3]], new BoundedMatcher(1)->match(array_fill(0, 4, array_fill(0, 4, 1))));
    }

    public function testTheMatchersReadOnlyTheUpperHalfOfTheCosts(): void
    {
        $upper = [[0, 23, 2, 4, 6, 17], [0, 0, 16, 7, 15, 10], [0, 0, 0, 1, 34, 38], [0, 0, 0, 0, 35, 24], [0, 0, 0, 0, 0, 33], [0, 0, 0, 0, 0, 0]];

        self::assertSame([[0, 5], [1, 4], [2, 3]], new BoundedMatcher(2)->match($upper));
        self::assertSame([[0, 4], [1, 5], [2, 3]], new ExactMatcher()->match($upper));
    }

    /**
     * @param list<list<int>>       $costs
     * @param list<array{int, int}> $pairs
     */
    private function total(array $costs, array $pairs): int
    {
        return array_sum(array_map(static fn (array $pair): int => $costs[$pair[0]][$pair[1]], $pairs));
    }

    /**
     * @param list<Pairing> $tables
     *
     * @return list<array{string, ?string}>
     */
    private function seats(array $tables): array
    {
        return array_map(static fn (Pairing $pairing): array => [$pairing->first, $pairing->second], $tables);
    }

    /**
     * The records of rounds written as tables: first, second (none for a bye) and result.
     *
     * @param list<list<array{string, ?string, ?Result}>> $rounds
     */
    private function records(array $rounds): Records
    {
        $built = [];
        $results = [];

        foreach ($rounds as $index => $tables) {
            $pairings = [];

            foreach ($tables as $table => [$first, $second, $result]) {
                $pairings[] = new Pairing($table + 1, $first, $second);

                if (null !== $result) {
                    $results['s#'.($index + 1).'#'.($table + 1)] = $result;
                }
            }

            $built[] = new Round('s', $index + 1, $pairings);
        }

        return Records::build([], $built, $results, new Scoring());
    }

    /**
     * Records where each participant played the sides given, true for first, each against an opponent of their own and
     * drawn, so that only the sides differ.
     *
     * @param array<string, list<bool>> $sides
     */
    private function history(array $sides): Records
    {
        $rounds = [];

        foreach ($sides as $participant => $played) {
            foreach ($played as $round => $first) {
                $opponent = 'x-'.$participant.'-'.$round;
                $rounds[$round][] = $first ? [$participant, $opponent, Result::draw()] : [$opponent, $participant, Result::draw()];
            }
        }

        ksort($rounds);

        return $this->records(array_values($rounds));
    }

    /**
     * Every table of the rounds won by the participant who played first.
     *
     * @param list<Round> $rounds
     *
     * @return array<string, Result>
     */
    private function firstWinsAll(array $rounds): array
    {
        $results = [];

        foreach ($rounds as $round) {
            foreach ($round->pairings as $pairing) {
                $results[$round->key($pairing->table)] = Result::firstWins();
            }
        }

        return $results;
    }
}
