<?php

declare(strict_types=1);

namespace Tilttek\MathTrade\Tests\Unit\Fixture;

use LogicException;
use Tilttek\MathTrade\Contract\Wish;
use Tilttek\MathTrade\Limits;
use Tilttek\MathTrade\Offer;
use Tilttek\MathTrade\Participant;
use Tilttek\MathTrade\TradeList;
use Tilttek\MathTrade\TradeResult;
use Tilttek\MathTrade\Want;
use Tilttek\MathTrade\WantGroup;

/**
 * Trade lists written compactly: participant => offer => wishes, a wish being an offer or a list of offers for a
 * group; the game of an offer, when it has one, is given as "offer@game", which a wish may repeat.
 */
final class Lists
{
    /**
     * @param array<array-key, array<array-key, list<string|list<string>>>> $participants keys made of digits are integers
     */
    public static function of(array $participants, Limits $limits = new Limits()): TradeList
    {
        $list = [];

        foreach ($participants as $participant => $offers) {
            $built = [];

            foreach ($offers as $offer => $wishes) {
                $parts = explode('@', (string) $offer, 2);
                $built[] = new Offer(
                    $parts[0],
                    array_map(static fn (string|array $wish): Wish => \is_string($wish) ? new Want(self::id($wish)) : new WantGroup(array_map(self::id(...), $wish)), $wishes),
                    $parts[1] ?? null,
                );
            }

            $list[] = new Participant((string) $participant, $built);
        }

        return new TradeList($list, $limits);
    }

    private static function id(string $offer): string
    {
        return explode('@', $offer, 2)[0];
    }

    /**
     * The result as "receiver<-offer" pairs per cycle, for comparisons at a glance.
     *
     * @return list<list<string>>
     */
    public static function cycles(TradeResult $result): array
    {
        $cycles = [];

        foreach ($result->cycles as $cycle) {
            $trades = [];

            foreach ($cycle->trades as $trade) {
                $trades[] = $trade->giver.'>'.$trade->offer.'>'.$trade->receiver;
            }

            $cycles[] = $trades;
        }

        return $cycles;
    }

    /**
     * The total priority of the wishes a result fulfils: the priority, in each sent offer's list, of the offer received.
     */
    public static function cost(TradeList $list, TradeResult $result): int
    {
        $cost = 0;

        foreach ($result->swaps() as $swap) {
            $cost += $list->offer($swap->sent)->priorityOf($swap->received) ?? throw new LogicException('An unwished trade.');
        }

        return $cost;
    }
}
