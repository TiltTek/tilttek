<?php

declare(strict_types=1);

namespace Tilttek\MathTrade\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\MathTrade\Internal\MinCostAssignment;
use Tilttek\MathTrade\Offer;
use Tilttek\MathTrade\Participant;
use Tilttek\MathTrade\TradeList;
use Tilttek\MathTrade\TradeResult;
use Tilttek\MathTrade\TradeSolver;
use Tilttek\MathTrade\Want;
use Tilttek\MathTrade\WantGroup;

/**
 * Properties held on random trade lists: each offer changes hands once at most, each trade is wished for, nobody
 * receives two copies of one game, the result is the same for the same seed whatever the order of the list, and,
 * without games, it trades as many offers as a search of every cover does, at the best total priority.
 */
#[CoversClass(TradeSolver::class)]
#[CoversClass(MinCostAssignment::class)]
#[CoversClass(TradeResult::class)]
final class TradeSolverPropertyTest extends TestCase
{
    public function testEveryOfferChangesHandsOnceAtMostAndEveryTradeIsWished(): void
    {
        $random = new Randomizer(new Xoshiro256StarStar(11));
        $solver = new TradeSolver();

        for ($trial = 0; $trial < 200; ++$trial) {
            $list = $this->randomList($random, 8, true);
            $result = $solver->solve($list, 'trial '.$trial);
            $this->assertConsistent($list, $result);
        }
    }

    public function testTradesTheMostOffersAtTheBestPriorities(): void
    {
        $random = new Randomizer(new Xoshiro256StarStar(23));
        $solver = new TradeSolver();

        for ($trial = 0; $trial < 300; ++$trial) {
            $list = $this->randomList($random, 4, false);
            $result = $solver->solve($list, 'trial '.$trial);
            [$trades, $cost] = self::bestCover($list);

            self::assertSame($trades, $result->tradeCount(), 'Trial '.$trial);
            self::assertSame($cost, $this->cost($list, $result), 'Trial '.$trial);
        }
    }

    public function testTheSameSeedGivesTheSameResultWhateverTheOrder(): void
    {
        $random = new Randomizer(new Xoshiro256StarStar(31));
        $solver = new TradeSolver();

        for ($trial = 0; $trial < 100; ++$trial) {
            $list = $this->randomList($random, 8, true);
            $shuffled = new TradeList(array_map(
                static fn (Participant $participant): Participant => new Participant($participant->id, $random->shuffleArray($participant->offers)),
                $random->shuffleArray($list->participants),
            ));

            self::assertEquals($solver->solve($list, 'seed '.$trial), $solver->solve($shuffled, 'seed '.$trial));
        }
    }

    private function assertConsistent(TradeList $list, TradeResult $result): void
    {
        $sent = [];
        $received = [];
        $games = [];

        foreach ($result->swaps() as $swap) {
            self::assertSame($list->ownerOf($swap->sent), $swap->participant);
            self::assertSame($list->ownerOf($swap->received), $swap->receiveFrom);
            self::assertNotSame($swap->participant, $swap->receiveFrom);
            self::assertNotSame($swap->participant, $swap->sendTo);
            self::assertArrayNotHasKey($swap->sent, $sent);
            self::assertArrayNotHasKey($swap->received, $received);
            $sent[$swap->sent] = true;
            $received[$swap->received] = true;
            self::assertTrue($this->wishes($list, $list->offer($swap->sent), $list->offer($swap->received)), 'An unwished trade.');
            $game = $list->offer($swap->received)->game;

            if (null !== $game) {
                self::assertArrayNotHasKey($swap->participant.'/'.$game, $games, 'A participant receives a game twice.');
                $games[$swap->participant.'/'.$game] = true;
            }
        }

        self::assertEqualsCanonicalizing(array_keys($sent), array_keys($received));
        self::assertCount(\count($list->offers()), array_merge(array_keys($sent), $result->unmatched));

        foreach ($result->trades() as $trade) {
            self::assertSame($trade->receiver, $result->swapOf($trade->offer)?->sendTo);
            self::assertSame($trade->giver, $list->ownerOf($trade->offer));
        }
    }

    /**
     * Whether the offer wishes for the other, or for a copy of its game.
     */
    private function wishes(TradeList $list, Offer $offer, Offer $other): bool
    {
        if (null !== $offer->priorityOf($other->id)) {
            return true;
        }

        foreach ($offer->wants as $wish) {
            foreach ($wish->offers() as $wished) {
                if (null !== $other->game && $list->offer($wished)->game === $other->game) {
                    return true;
                }
            }
        }

        return false;
    }

    private function cost(TradeList $list, TradeResult $result): int
    {
        $cost = 0;

        foreach ($result->swaps() as $swap) {
            $cost += (int) $list->offer($swap->sent)->priorityOf($swap->received);
        }

        return $cost;
    }

    /**
     * The most trades of any cover of the offers by loops, and the best total priority among those covers.
     *
     * @param array<string, true> $taken
     *
     * @return array{int, int}
     */
    private static function bestCover(TradeList $list, int $index = 0, array $taken = []): array
    {
        $offers = $list->offers();

        if ($index === \count($offers)) {
            return [0, 0];
        }

        $offer = $offers[$index];
        $best = isset($taken[$offer->id]) ? [-1, 0] : self::bestCover($list, $index + 1, $taken + [$offer->id => true]);

        foreach ($offer->wants as $priority => $wish) {
            foreach ($wish->offers() as $wished) {
                if (!isset($taken[$wished])) {
                    [$trades, $cost] = self::bestCover($list, $index + 1, $taken + [$wished => true]);

                    if ($trades >= 0 && ($trades + 1 > $best[0] || ($trades + 1 === $best[0] && $cost + $priority + 1 < $best[1]))) {
                        $best = [$trades + 1, $cost + $priority + 1];
                    }
                }
            }
        }

        return $best;
    }

    private function randomList(Randomizer $random, int $maxOffers, bool $games): TradeList
    {
        $owners = [];
        $count = $random->getInt(1, $maxOffers);

        for ($offer = 0; $offer < $count; ++$offer) {
            $owners['o'.$offer] = 'p'.$random->getInt(0, max(1, intdiv($count, 2)));
        }

        $byParticipant = [];

        foreach ($owners as $id => $owner) {
            $others = array_keys(array_filter($owners, static fn (string $other): bool => $other !== $owner));
            $others = \array_slice($random->shuffleArray($others), 0, $random->getInt(0, \count($others)));
            $wants = [];

            while ([] !== $others) {
                $size = min(\count($others), $random->getInt(1, 3));
                $line = array_splice($others, 0, $size);
                $wants[] = 1 === $size ? new Want($line[0]) : new WantGroup($line);
            }

            $byParticipant[$owner][] = new Offer($id, $wants, $games ? 'g'.$random->getInt(0, 3) : null);
        }

        return new TradeList(array_map(
            static fn (string $participant, array $offers): Participant => new Participant($participant, $offers),
            array_keys($byParticipant),
            array_values($byParticipant),
        ));
    }
}
