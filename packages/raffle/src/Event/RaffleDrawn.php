<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Event;

/**
 * Dispatched once a raffle is drawn and its proof kept: the application announces the winners, or plays a
 * celebration. It carries the raffle's identifier only; the listener reads the proof.
 */
final readonly class RaffleDrawn
{
    public function __construct(
        public string $raffle,
    ) {
    }
}
