<?php

declare(strict_types=1);

namespace Tilttek\MathTrade;

/**
 * The trades the solver found, with the seed that makes them again: the loops of trades, what each participant sends
 * and receives, and the offers no loop takes.
 */
final readonly class TradeResult
{
    /**
     * @var array<string, Swap>
     */
    private array $swaps;

    /**
     * @param string           $seed      the seed the result was solved with, to publish beside it
     * @param list<TradeCycle> $cycles    the loops of trades, ordered by the identifier of their first offer
     * @param list<string>     $unmatched the offers no loop takes, by identifier
     * @param list<Swap>       $swaps     what each traded offer means for its owner, by identifier of the sent offer
     */
    public function __construct(
        public string $seed,
        public array $cycles,
        public array $unmatched,
        array $swaps,
    ) {
        $byOffer = [];

        foreach ($swaps as $swap) {
            $byOffer[$swap->sent] = $swap;
        }

        $this->swaps = $byOffer;
    }

    /**
     * Every trade, loop after loop.
     *
     * @return list<Trade>
     */
    public function trades(): array
    {
        $trades = [];

        foreach ($this->cycles as $cycle) {
            array_push($trades, ...$cycle->trades);
        }

        return $trades;
    }

    /**
     * The number of offers that change hands.
     */
    public function tradeCount(): int
    {
        return \count($this->swaps);
    }

    /**
     * Every swap, by identifier of the sent offer.
     *
     * @return list<Swap>
     */
    public function swaps(): array
    {
        return array_values($this->swaps);
    }

    /**
     * What the participant sends and receives, one swap per offer of theirs that is traded, by identifier of the sent
     * offer; none when nothing of theirs is traded.
     *
     * @return list<Swap>
     */
    public function forParticipant(string $participant): array
    {
        return array_values(array_filter(
            $this->swaps,
            static fn (Swap $swap): bool => $swap->participant === $participant,
        ));
    }

    /**
     * What becomes of the offer: the swap it is sent in, or null when it is not traded.
     */
    public function swapOf(string $offer): ?Swap
    {
        return $this->swaps[$offer] ?? null;
    }
}
