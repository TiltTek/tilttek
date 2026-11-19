<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Tests\Unit\Fixture;

use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\Raffle\RaffleProof;
use Tilttek\Raffle\Raffles;
use Tilttek\Raffle\Stake;
use Tilttek\Raffle\Store\InMemoryRaffleStore;

/**
 * Raffles of tests, drawn: one with a prize, from the beacon of tests, and one without a stake, by commit-reveal.
 */
final class Draws
{
    public InMemoryRaffleStore $store;

    public FrozenClock $clock;

    public Raffles $raffles;

    public function __construct()
    {
        $this->store = new InMemoryRaffleStore();
        $this->clock = new FrozenClock();
        $this->raffles = new Raffles($this->store, new FakeBeacon(), clock: $this->clock, secure: new Randomizer(new Xoshiro256StarStar(5)));
    }

    public function prize(int $entrants = 6): RaffleProof
    {
        $this->raffles->open('door-prize', Stake::Prize, 2, 1);
        $this->enter('door-prize', $entrants);
        $this->raffles->seal('door-prize');
        $this->clock->move('+1 minute');

        return $this->raffles->draw('door-prize');
    }

    public function noStake(int $entrants = 4): RaffleProof
    {
        $this->raffles->open('first-speaker', Stake::None, 1, 2);
        $this->enter('first-speaker', $entrants);
        $this->raffles->seal('first-speaker');

        return $this->raffles->draw('first-speaker');
    }

    private function enter(string $raffle, int $entrants): void
    {
        for ($entrant = 1; $entrant <= $entrants; ++$entrant) {
            $this->raffles->enter($raffle, 'member-'.$entrant);
        }
    }
}
