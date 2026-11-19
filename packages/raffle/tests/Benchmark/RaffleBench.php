<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Tests\Benchmark;

use Tilttek\Raffle\Entrants;
use Tilttek\Raffle\RaffleProof;
use Tilttek\Raffle\Raffles;
use Tilttek\Raffle\RaffleVerifier;
use Tilttek\Raffle\Selection;
use Tilttek\Raffle\Stake;
use Tilttek\Raffle\Store\InMemoryRaffleStore;

/**
 * What a raffle costs: one entry — every entrant —, the draw of 10 winners and 10 alternates among 100 000 entrants,
 * and the proof of a raffle of 10 000 entrants read and checked whole — a player's page.
 */
final class RaffleBench
{
    private readonly Raffles $raffles;

    /** @var list<string> */
    private readonly array $crowd;

    private readonly string $proof;

    private readonly RaffleVerifier $verifier;

    private int $entrant = 0;

    public function __construct()
    {
        $this->raffles = new Raffles(new InMemoryRaffleStore());
        $this->raffles->open('bench', Stake::None);

        $this->verifier = new RaffleVerifier();
        $this->crowd = Entrants::canonical(array_map(static fn (int $entrant): string => 'member-'.$entrant, range(1, 100_000)));

        $raffles = new Raffles(new InMemoryRaffleStore());
        $raffles->open('proof', Stake::None, 10, 10);

        for ($entrant = 1; $entrant <= 10_000; ++$entrant) {
            $raffles->enter('proof', 'member-'.$entrant);
        }

        $raffles->seal('proof');
        $this->proof = $raffles->draw('proof')->toJson();
    }

    public function benchEnter(): void
    {
        $this->raffles->enter('bench', 'member-'.++$this->entrant);
    }

    /**
     * @Revs(10)
     *
     * @Iterations(3)
     */
    public function benchDraw20Among100000(): void
    {
        Selection::draw(str_repeat('ab', 32), $this->crowd, 20);
    }

    /**
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchCheckAProofOf10000Entrants(): void
    {
        $this->verifier->problems(RaffleProof::fromJson($this->proof));
    }
}
