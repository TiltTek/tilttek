<?php

declare(strict_types=1);

namespace Tilttek\TavernGames\Odds;

/**
 * The odds of a position, counted over simulated endings: the wins, the ties and the losses apart, and the number of
 * endings they were counted on, so that a tie — a shared pot — is shown as itself, never folded into a win or a loss.
 */
final readonly class Estimate
{
    public function __construct(
        public int $wins,
        public int $ties,
        public int $losses,
    ) {
    }

    /**
     * The number of simulated endings.
     */
    public function samples(): int
    {
        return $this->wins + $this->ties + $this->losses;
    }

    /**
     * The share of wins, from 0 to 1.
     */
    public function winRate(): float
    {
        return $this->rate($this->wins);
    }

    /**
     * The share of ties, from 0 to 1.
     */
    public function tieRate(): float
    {
        return $this->rate($this->ties);
    }

    /**
     * The share of losses, from 0 to 1.
     */
    public function lossRate(): float
    {
        return $this->rate($this->losses);
    }

    /**
     * @return array{wins: int, ties: int, losses: int, samples: int}
     */
    public function toArray(): array
    {
        return ['wins' => $this->wins, 'ties' => $this->ties, 'losses' => $this->losses, 'samples' => $this->samples()];
    }

    private function rate(int $count): float
    {
        $samples = $this->samples();

        return 0 === $samples ? 0.0 : $count / $samples;
    }
}
