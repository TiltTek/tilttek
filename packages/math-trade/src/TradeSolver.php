<?php

declare(strict_types=1);

namespace Tilttek\MathTrade;

use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\MathTrade\Exception\InvalidSeed;
use Tilttek\MathTrade\Internal\Identifier;
use Tilttek\MathTrade\Internal\MinCostAssignment;

/**
 * Finds the most trades a list allows, as loops in which each participant gives one offer and receives another.
 *
 * Each offer is a node; a wish is an edge from the offer to the wished one, whose cost is the wish's priority, and each
 * offer also has an edge to itself, the offer kept, dearer than any set of wishes. A minimum-cost cover of the nodes
 * by cycles — a minimum-cost assignment of each offer to the offer its owner receives — then trades the most offers
 * first, and among those covers, the one whose wishes have the best priorities.
 *
 * A participant who offers several copies and wishes, from two offers or more, for a game of which two copies or more
 * are offered goes through a node of their own for that game, which leads to one copy only: they receive the game once
 * at most. Copies of a game are then interchangeable: each of those offers may receive any copy one of their offers
 * wishes for, at the best priority it gave a copy.
 *
 * The seed shuffles the nodes before the assignment, which breaks ties between covers of equal cost: the same list
 * and the same seed give the same result, whatever the order the list was given in. The solver keeps no state between
 * two calls.
 */
final class TradeSolver
{
    public const int MAX_SEED_BYTES = 256;

    /**
     * @throws InvalidSeed when the seed is empty, longer than 256 bytes, not UTF-8 or holds a control character
     */
    public function solve(TradeList $list, string $seed): TradeResult
    {
        if (!Identifier::isValid($seed, self::MAX_SEED_BYTES)) {
            throw InvalidSeed::because(\sprintf('A seed is 1 to %d bytes of UTF-8 without a control character.', self::MAX_SEED_BYTES));
        }

        $offers = $list->offers();
        usort($offers, static fn (Offer $a, Offer $b): int => strcmp($a->id, $b->id));
        $count = \count($offers);

        if (0 === $count) {
            return new TradeResult($seed, [], [], []);
        }

        $owners = array_map(static fn (Offer $offer): string => $list->ownerOf($offer->id), $offers);
        [$wishes, $dummies] = $this->graph($offers, $owners);
        $nodes = $count + \count($dummies);
        $keep = 1;

        foreach ($offers as $offer) {
            $keep += \count($offer->wants);
        }

        $order = new Randomizer(new Xoshiro256StarStar(hash('sha256', $seed, true)))->shuffleArray(range(0, $nodes - 1));
        $edges = [];

        foreach ($wishes as $node => $costs) {
            $edges[$order[$node]] = $this->row($order, [$node => $keep] + $costs);
        }

        foreach ($dummies as $dummy => $targets) {
            $edges[$order[$count + $dummy]] = $this->row($order, [$count + $dummy => 0] + array_fill_keys($targets, 1));
        }

        ksort($edges);
        $assigned = MinCostAssignment::solve(array_values($edges));
        $node = array_flip($order);
        $next = [];

        foreach ($order as $from => $row) {
            $next[$from] = $node[$assigned[$row]];
        }

        return $this->result($seed, $offers, $owners, $next);
    }

    /**
     * The row of a node in the shuffled order: its columns, sorted, with their costs.
     *
     * @param list<int>                 $order the shuffled place of each node
     * @param non-empty-array<int, int> $costs the cost of each node this one may take
     *
     * @return non-empty-list<array{int, int}>
     */
    private function row(array $order, array $costs): array
    {
        $row = [];

        foreach ($costs as $node => $cost) {
            $row[] = [$order[$node], $cost];
        }

        usort($row, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return $row;
    }

    /**
     * The edges of each offer, by node, with their costs — to the offers it wishes for, and to the nodes of its owner's
     * games, numbered after the offers —, and the offers each of those game nodes leads to.
     *
     * @param list<Offer>  $offers sorted by identifier: offer n is node n
     * @param list<string> $owners the owner of each offer
     *
     * @return array{array<int, array<int, int>>, list<list<int>>}
     */
    private function graph(array $offers, array $owners): array
    {
        $count = \count($offers);
        $index = [];
        $byOwner = [];

        foreach ($offers as $node => $offer) {
            $index[$offer->id] = $node;
            $byOwner[$owners[$node]][] = $node;
        }

        $wishes = [];
        $dummies = [];

        foreach ($byOwner as $nodes) {
            $games = [];

            foreach ($this->gameCopies($offers, $index, $nodes) as $game => $copies) {
                $games[$game] = $count + \count($dummies);
                $dummies[] = $copies;
            }

            foreach ($nodes as $node) {
                $costs = [];

                foreach ($offers[$node]->wants as $position => $wish) {
                    foreach ($wish->offers() as $target) {
                        $to = $index[$target];
                        $dummy = $games[$offers[$to]->game ?? ''] ?? null;

                        if (null === $dummy) {
                            $costs[$to] ??= $position + 1;
                        } else {
                            $costs[$dummy] ??= $position;
                        }
                    }
                }

                $wishes[$node] = $costs;
            }
        }

        ksort($wishes);

        return [$wishes, $dummies];
    }

    /**
     * The games one participant receives through a node of their own — wished for from two of their offers or more,
     * with two copies or more wished for — and the copies each leads to, in the order they were met.
     *
     * @param list<Offer>           $offers
     * @param array<array-key, int> $index  the node of each offer, by identifier
     * @param non-empty-list<int>   $nodes  the participant's offers
     *
     * @return array<array-key, list<int>>
     */
    private function gameCopies(array $offers, array $index, array $nodes): array
    {
        /** @var array<array-key, array<int, true>> $wishers */
        $wishers = [];
        /** @var array<array-key, array<int, true>> $copies */
        $copies = [];

        foreach ($nodes as $node) {
            foreach ($offers[$node]->wants as $wish) {
                foreach ($wish->offers() as $target) {
                    $game = $offers[$index[$target]]->game;

                    if (null !== $game) {
                        $wishers[$game][$node] = true;
                        $copies[$game][$index[$target]] = true;
                    }
                }
            }
        }

        $games = [];

        foreach ($wishers as $game => $wishing) {
            if (\count($wishing) > 1 && \count($copies[$game]) > 1) {
                $games[$game] = array_keys($copies[$game]);
            }
        }

        return $games;
    }

    /**
     * @param list<Offer>     $offers sorted by identifier: offer n is node n, the nodes after are the games'
     * @param list<string>    $owners the owner of each offer
     * @param array<int, int> $next   the node each node is assigned to: its owner receives that offer
     */
    private function result(string $seed, array $offers, array $owners, array $next): TradeResult
    {
        $count = \count($offers);
        $receives = [];
        $sendsTo = [];

        foreach ($offers as $node => $offer) {
            $target = $next[$node];

            while ($target >= $count) {
                $target = $next[$target];
            }

            $receives[$node] = $target;
            $sendsTo[$target] = $node;
        }

        $cycles = [];
        $unmatched = [];
        $swaps = [];
        $seen = [];

        foreach ($offers as $start => $offer) {
            if ($receives[$start] === $start) {
                $unmatched[] = $offer->id;

                continue;
            }

            $received = $receives[$start];
            $swaps[] = new Swap($owners[$start], $offer->id, $owners[$sendsTo[$start]], $offers[$received]->id, $owners[$received]);

            if (isset($seen[$start])) {
                continue;
            }

            $trades = [];
            $node = $start;

            do {
                $seen[$node] = true;
                $received = $receives[$node];
                $trades[] = new Trade($offers[$received]->id, $owners[$received], $owners[$node]);
                $node = $received;
            } while ($node !== $start);

            $cycles[] = new TradeCycle($trades);
        }

        return new TradeResult($seed, $cycles, $unmatched, $swaps);
    }
}
