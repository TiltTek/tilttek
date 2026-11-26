<?php

declare(strict_types=1);

namespace Tilttek\MathTrade;

/**
 * A loop of trades: each participant of the loop gives one offer to the next and receives one from the previous. The
 * trades follow the loop, starting with the offer received by the owner of the loop's first offer, by identifier.
 */
final readonly class TradeCycle
{
    /**
     * @param non-empty-list<Trade> $trades
     */
    public function __construct(public array $trades)
    {
    }

    /**
     * The number of offers that change hands in the loop.
     *
     * @return positive-int
     */
    public function length(): int
    {
        return \count($this->trades);
    }
}
